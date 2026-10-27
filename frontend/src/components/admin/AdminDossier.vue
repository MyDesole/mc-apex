<script setup>
/**
 * Досье игрока — панель справа поверх любого раздела админки.
 *
 * Сверху — статус и панель действий (связь, модерация, аккаунт, игра),
 * ниже — рейтинг, жалобы, история модерации, форум, рефералы, тесты
 * и ApexCoin. Почта, пароль, ник и монеты — только администратору.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { adminApi } from '@/services/core/admin.js'
import { adminShopApi } from '@/services/shop/adminShop.js'
import { chatApi } from '@/services/chat/chat.js'
import { useAuthStore } from '@/stores/core/auth.js'
import { alert, confirm, confirmWithReason, prompt } from '@/utils/dialog.js'
import AdminAspectsModal from '@/components/admin/AdminAspectsModal.vue'
import AdminRoleModal from '@/components/admin/AdminRoleModal.vue'
import AdminAchievementsModal from '@/components/admin/AdminAchievementsModal.vue'
import AdminTierTestModal from '@/components/admin/AdminTierTestModal.vue'
import AdminDossierBridge from '@/components/admin/AdminDossierBridge.vue'
import AdminDossierInventory from '@/components/admin/AdminDossierInventory.vue'

const props = defineProps({
  userId: { type: Number, required: true },
})

// open — перейти к досье другого игрока (пригласившего, приглашённого)
const emit = defineEmits(['close', 'changed', 'open'])

const auth = useAuthStore()
const router = useRouter()
const isAdmin = computed(() => auth.user?.role === 'admin')

const data = ref(null)
const loading = ref(true)
const busy = ref(false)
const modal = ref(null)

const ROLE_LABELS = {
  user: 'Игрок', media: 'Медийка', tester: 'Тестер', moderator: 'Модератор',
  admin: 'Администратор', bridge_tester: 'Бридж-тестер', bridge_curator: 'Бридж-куратор',
}

const TEST_STATUS = { pending: 'ждёт', in_progress: 'в работе', completed: 'завершён', cancelled: 'отменён' }

const BAN_DAYS = [
  { value: 1, label: '1 день' },
  { value: 7, label: '7 дней' },
  { value: 30, label: '30 дней' },
  { value: null, label: 'Навсегда' },
]

const banDays = ref(7)

const user = computed(() => data.value?.user)
const isSelf = computed(() => user.value?.id === auth.user?.id)
// Персонал меняет только админ, администратора — никто, кроме него самого
const canModerate = computed(() =>
    user.value && (isAdmin.value || (!isSelf.value && !['admin', 'moderator', 'tester', 'bridge_tester', 'bridge_curator'].includes(user.value.role))),
)

async function load() {
  loading.value = true
  try {
    data.value = await adminApi.dossier(props.userId)
  } catch {
    data.value = null
  } finally {
    loading.value = false
  }
}

/** Выполнить действие, обновить досье и счётчики. */
async function run(fn, { reload = true } = {}) {
  busy.value = true
  try {
    const result = await fn()
    if (reload) await load()
    emit('changed')
    return result
  } catch (e) {
    await alert(e.message || 'Не удалось выполнить действие', { title: user.value?.username })
    return null
  } finally {
    busy.value = false
  }
}

/* ---------- Связь ---------- */

async function writeMessage() {
  const result = await run(() => chatApi.startDirect(user.value.id), { reload: false })
  if (result?.conversation?.id) {
    emit('close')
    router.push(`/messages/${result.conversation.id}`)
  }
}

/* ---------- Модерация ---------- */

async function ban() {
  const answer = await confirmWithReason(`Забанить ${user.value.username}?`, {
    danger: true,
    confirmText: 'Забанить',
    reasonLabel: 'Причина (увидит игрок)',
    reasonPlaceholder: 'Например: читы, подтверждено записью',
  })

  if (!answer) return
  if (!answer.reason.trim()) return alert('Укажите причину бана', { title: 'Бан' })

  const until = banDays.value ? new Date(Date.now() + banDays.value * 86400000).toISOString() : null

  await run(() => adminApi.ban(user.value.id, { reason: answer.reason.trim(), until }))
}

async function unban() {
  if (await confirm(`Разбанить ${user.value.username}?`, { confirmText: 'Разбанить' })) {
    await run(() => adminApi.unban(user.value.id))
  }
}

async function simple(action, question) {
  if (question && !(await confirm(question, { confirmText: 'Да' }))) return
  await run(() => adminApi.playerAction(user.value.id, action))
}

async function addNote() {
  const text = await prompt('Заметка видна только персоналу — в досье и журнале.', {
    title: 'Заметка об игроке',
    placeholder: 'Например: подозрение на твинк, проверить через неделю',
    confirmText: 'Сохранить',
    maxlength: 1000,
  })

  if (text?.trim()) await run(() => adminApi.playerAction(user.value.id, 'note', { text: text.trim() }))
}

/* ---------- Аккаунт (админ) ---------- */

async function rename() {
  const name = await prompt('Латинские буквы, цифры и подчёркивание, 3–32 символа.', {
    title: 'Сменить ник',
    initialValue: user.value.username,
    confirmText: 'Сменить',
    maxlength: 32,
  })

  if (name?.trim() && name.trim() !== user.value.username) {
    await run(() => adminApi.playerAction(user.value.id, 'rename', { username: name.trim() }))
  }
}

async function temporaryPassword() {
  const ok = await confirm(
      'Задать временный пароль? Старый перестанет работать, игрока выкинет со всех устройств. Пароль покажем один раз — передайте его игроку лично.',
      { danger: true, confirmText: 'Задать' },
  )
  if (!ok) return

  const result = await run(() => adminApi.playerAction(user.value.id, 'temporary-password'))
  if (result?.password) {
    await alert(`Временный пароль: ${result.password}\n\nСкопируйте его сейчас — больше он показан не будет. Попросите игрока сменить пароль после входа.`, {
      title: 'Пароль задан',
      confirmText: 'Скопировал',
    })
  }
}

async function grantCoins() {
  const amount = await prompt('Сколько ApexCoin начислить? Отрицательное число — списать.', {
    title: 'ApexCoin',
    placeholder: '500',
    confirmText: 'Дальше',
  })
  if (amount === null) return

  const value = Number.parseInt(amount, 10)
  if (!Number.isFinite(value) || value === 0) return alert('Нужно целое число, не ноль', { title: 'ApexCoin' })

  const reason = await prompt('За что? Увидит игрок в истории кошелька.', {
    title: 'ApexCoin',
    placeholder: 'Победа в ивенте',
    confirmText: value > 0 ? 'Начислить' : 'Списать',
  })
  if (reason === null) return

  await run(() => adminShopApi.grantCoins(user.value.id, { amount: value, reason: reason.trim() || null }))
}

/* ---------- Подарок предмета ---------- */

const giftItems = ref(null)
const giftItemId = ref(null)

// Подарили предмет — секция инвентаря перечитывает список
const inventoryKey = ref(0)

async function openGift() {
  try {
    giftItems.value = (await adminApi.control()).items ?? []
  } catch {
    giftItems.value = []
  }
}

async function giveItem() {
  const item = giftItems.value.find((i) => i.id === giftItemId.value)
  if (!item || !(await confirm(`Подарить «${item.name}» игроку ${user.value.username}?`, { confirmText: 'Подарить' }))) return

  const done = await run(() => adminApi.giveItem(user.value.id, item.id), { reload: false })
  if (done) {
    giftItems.value = null
    giftItemId.value = null
    inventoryKey.value++
    await alert('Подарок в инвентаре игрока, уведомление отправлено', { title: 'Готово' })
  }
}

async function toggleBadge() {
  if (data.value.user.is_verified) {
    await run(() => adminApi.unverifyUser(user.value.id))
  } else {
    const reason = await prompt('За что галочка (видно в профиле)?', { title: 'Верификация', placeholder: 'Стример, ютубер', confirmText: 'Выдать' })
    if (reason !== null) await run(() => adminApi.verifyUser(user.value.id, reason.trim() || null))
  }
}

/* ---------- Форум ---------- */

async function deleteTopic(topic) {
  if (await confirm(`Удалить тему «${topic.title}»?`, { danger: true, confirmText: 'Удалить' })) {
    await run(() => adminApi.deleteForumTopic(topic.id))
  }
}

async function deleteReply(reply) {
  if (await confirm('Удалить этот ответ?', { danger: true, confirmText: 'Удалить' })) {
    await run(() => adminApi.deleteForumReply(reply.id))
  }
}

/* ---------- Модалки игры ---------- */

function onModalUpdated() {
  load()
  emit('changed')
}

function date(iso, withTime = false) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('ru-RU', {
    day: 'numeric', month: 'short', year: 'numeric',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
  })
}

function onKey(event) {
  if (event.key === 'Escape' && !modal.value) emit('close')
}

watch(() => props.userId, load)

onMounted(() => {
  load()
  window.addEventListener('keydown', onKey)
})

onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <Teleport to="body">
    <div class="dossier-backdrop" @click.self="emit('close')">
      <aside class="dossier" role="dialog" aria-label="Досье игрока">
        <button type="button" class="dossier__close" aria-label="Закрыть" @click="emit('close')">×</button>

        <div v-if="loading && !data" class="kit-empty">Загрузка…</div>
        <div v-else-if="!data" class="kit-empty">Не удалось загрузить досье</div>

        <template v-else>
          <!-- Шапка -->
          <header class="dossier__head">
            <span class="dossier__avatar">
              <img v-if="user.avatar_url" :src="user.avatar_url" alt="" />
              <template v-else>{{ user.username.charAt(0).toUpperCase() }}</template>
            </span>
            <div>
              <h2>{{ user.username }}</h2>
              <p class="dossier__tags">
                <span class="kit-tag kit-tag--blue">{{ ROLE_LABELS[user.role] ?? user.role }}</span>
                <span v-if="user.is_banned" class="kit-tag kit-tag--red">
                  Бан {{ user.banned_until ? `до ${date(user.banned_until)}` : 'навсегда' }}
                </span>
                <span v-if="user.is_verified" class="kit-tag kit-tag--green">Галочка</span>
                <span v-if="!user.email_verified" class="kit-tag kit-tag--yellow">Почта не подтверждена</span>
                <span v-if="user.clan" class="kit-tag">[{{ user.clan.tag }}] {{ user.clan.name }}</span>
              </p>
            </div>
          </header>

          <p v-if="user.is_banned && user.ban_reason" class="dossier__ban">Причина бана: {{ user.ban_reason }}</p>

          <!-- ============ ДЕЙСТВИЯ ============ -->
          <section class="dossier__panel">
            <div class="dossier__group">
              <span class="dossier__group-title">Связь</span>
              <RouterLink :to="`/user/${encodeURIComponent(user.username)}`" class="kit-btn" @click="emit('close')">Профиль</RouterLink>
              <button v-if="!isSelf" type="button" class="kit-btn" :disabled="busy" @click="writeMessage">Написать</button>
              <button type="button" class="kit-btn" :disabled="busy" @click="addNote">Заметка</button>
            </div>

            <div v-if="canModerate" class="dossier__group">
              <span class="dossier__group-title">Модерация</span>
              <template v-if="isAdmin && user.role !== 'admin'">
                <button v-if="user.is_banned" type="button" class="kit-btn" :disabled="busy" @click="unban">Разбанить</button>
                <span v-else class="dossier__inline">
                  <select v-model="banDays" class="kit-select" aria-label="Срок бана">
                    <option v-for="o in BAN_DAYS" :key="o.label" :value="o.value">{{ o.label }}</option>
                  </select>
                  <button type="button" class="kit-btn kit-btn--danger" :disabled="busy" @click="ban">Бан</button>
                </span>
              </template>
              <button v-if="user.has_avatar" type="button" class="kit-btn" :disabled="busy"
                      @click="simple('remove-avatar', 'Удалить аватар игрока?')">Удалить аватар</button>
              <button v-if="user.has_cover" type="button" class="kit-btn" :disabled="busy"
                      @click="simple('remove-cover', 'Удалить обложку профиля?')">Удалить обложку</button>
              <button v-if="user.bio || user.status || user.quote" type="button" class="kit-btn" :disabled="busy"
                      @click="simple('clear-profile', 'Стереть «о себе», статус и цитату?')">Очистить профиль</button>
            </div>

            <div v-if="isAdmin" class="dossier__group">
              <span class="dossier__group-title">Аккаунт</span>
              <button type="button" class="kit-btn" :disabled="busy" @click="rename">Сменить ник</button>
              <button v-if="user.email" type="button" class="kit-btn" :disabled="busy"
                      @click="simple('send-password-reset', `Отправить код сброса пароля на ${user.email}?`)">Письмо для сброса</button>
              <button v-if="canModerate" type="button" class="kit-btn kit-btn--warn" :disabled="busy" @click="temporaryPassword">Временный пароль</button>
              <button v-if="!user.email_verified" type="button" class="kit-btn" :disabled="busy"
                      @click="simple('verify-email', 'Подтвердить почту вручную?')">Подтвердить почту</button>
              <button v-if="canModerate" type="button" class="kit-btn" :disabled="busy"
                      @click="simple('logout-everywhere', 'Завершить все сессии игрока?')">Выйти везде</button>
              <button v-if="user.minecraft_username" type="button" class="kit-btn" :disabled="busy"
                      @click="simple('unlink-minecraft', `Отвязать Minecraft-аккаунт ${user.minecraft_username}?`)">Отвязать Minecraft</button>
              <button type="button" class="kit-btn" :disabled="busy" @click="toggleBadge">
                {{ user.is_verified ? 'Снять галочку' : 'Выдать галочку' }}
              </button>
            </div>

            <div v-if="isAdmin" class="dossier__group">
              <span class="dossier__group-title">Игра</span>
              <button type="button" class="kit-btn" :disabled="busy" @click="modal = 'aspects'">Аспекты</button>
              <button type="button" class="kit-btn" :disabled="busy" @click="modal = 'tierTest'">Провести тест</button>
              <button type="button" class="kit-btn" :disabled="busy" @click="modal = 'achievements'">Ачивки</button>
              <button v-if="!isSelf" type="button" class="kit-btn" :disabled="busy" @click="modal = 'role'">Роль</button>
              <button type="button" class="kit-btn" :disabled="busy" @click="grantCoins">ApexCoin</button>
              <button v-if="!giftItems" type="button" class="kit-btn" :disabled="busy" @click="openGift">Подарить предмет</button>
              <span v-else class="dossier__inline">
                <select v-model="giftItemId" class="kit-select" aria-label="Предмет">
                  <option :value="null" disabled>Предмет…</option>
                  <option v-for="i in giftItems" :key="i.id" :value="i.id">{{ i.name }}</option>
                </select>
                <button type="button" class="kit-btn kit-btn--primary" :disabled="busy || !giftItemId" @click="giveItem">Подарить</button>
              </span>
            </div>
          </section>

          <!-- Факты -->
          <dl class="dossier__facts">
            <div v-if="user.email"><dt>Почта</dt><dd>{{ user.email }}</dd></div>
            <div><dt>Minecraft</dt><dd>{{ user.minecraft_username || 'не привязан' }}</dd></div>
            <div><dt>Регистрация</dt><dd>{{ date(user.created_at) }}</dd></div>
            <div><dt>Последний визит</dt><dd>{{ date(user.last_seen_at, true) }}</dd></div>
            <div><dt>Тир</dt><dd>{{ user.tier || '—' }} · {{ Math.round(user.tier_score) }}%</dd></div>
            <div>
              <dt>Рейтинг</dt>
              <dd>{{ user.rating_points }} очк.<template v-if="data.rank.position"> · #{{ data.rank.position }} из {{ data.rank.total }}</template></dd>
            </div>
            <div v-if="user.apex_coins !== null"><dt>ApexCoin</dt><dd>{{ user.apex_coins.toLocaleString('ru-RU') }}</dd></div>
            <div><dt>Ачивок</dt><dd>{{ user.achievements_count }}</dd></div>
            <div v-if="data.tests_conducted"><dt>Провёл тестов</dt><dd>{{ data.tests_conducted }}</dd></div>
            <div>
              <dt>Жалоб на него / от него</dt>
              <dd :class="{ red: data.reports_against_total > 2 }">{{ data.reports_against_total }} / {{ data.reports_filed }}</dd>
            </div>
          </dl>

          <section v-if="user.bio || user.status || user.quote" class="dossier__section">
            <h3>Тексты профиля</h3>
            <p v-if="user.status" class="kit-muted">Статус: {{ user.status }}</p>
            <p v-if="user.quote" class="kit-muted">Цитата: {{ user.quote }}</p>
            <p v-if="user.bio" class="kit-muted">О себе: {{ user.bio }}</p>
          </section>

          <!-- Разбивка рейтинга -->
          <section v-if="data.rating" class="dossier__section">
            <h3>Очки рейтинга</h3>
            <p class="kit-muted">
              Тиры {{ data.rating.tiers_points }} (PvP {{ data.rating.pvp }}%, BW {{ data.rating.bedwars }}%, бридж {{ data.rating.bridge }}%)
              · матчи {{ data.rating.match_points }} · турниры {{ data.rating.tournament_points }}
              · войны {{ data.rating.war_points + data.rating.war_win_points }}
            </p>
          </section>

          <!-- Бридж: правка оценок тестера и звания — только админ -->
          <AdminDossierBridge v-if="isAdmin" :user-id="user.id" @changed="load(); emit('changed')" />

          <!-- Жалобы на игрока -->
          <section class="dossier__section">
            <h3>Жалобы на игрока</h3>
            <p v-if="!data.reports_against.length" class="kit-muted">Жалоб нет</p>
            <ul class="dossier__list">
              <li v-for="r in data.reports_against" :key="r.id">
                <span class="kit-tag kit-tag--red">{{ r.reason_label }}</span>
                <span>{{ r.reporter?.username }}<template v-if="r.comment">: «{{ r.comment }}»</template></span>
                <small class="kit-muted">{{ r.status === 'open' ? 'открыта' : r.resolution }} · {{ date(r.created_at) }}</small>
              </li>
            </ul>
          </section>

          <!-- История модерации и заметки -->
          <section class="dossier__section">
            <h3>История модерации и заметки</h3>
            <p v-if="!data.moderation.length" class="kit-muted">Пусто</p>
            <ul class="dossier__list">
              <li v-for="m in data.moderation" :key="m.id">
                <span class="kit-tag" :class="{ 'kit-tag--yellow': m.action === 'user.note' }">{{ m.label }}</span>
                <span>
                  {{ m.actor?.username ?? 'система' }}
                  <template v-if="m.details?.reason || m.details?.note"> — {{ m.details.reason || m.details.note }}</template>
                  <template v-else-if="m.action === 'user.rename'"> — {{ m.details.from }} → {{ m.details.to }}</template>
                </span>
                <small class="kit-muted">{{ date(m.created_at, true) }}</small>
              </li>
            </ul>
          </section>

          <!-- Форум -->
          <section class="dossier__section">
            <h3>Форум <span class="kit-muted">{{ data.forum.topics_count }} тем · {{ data.forum.replies_count }} ответов</span></h3>
            <p v-if="!data.forum.topics.length && !data.forum.replies.length" class="kit-muted">На форуме не писал</p>
            <ul class="dossier__list">
              <li v-for="t in data.forum.topics" :key="`t${t.id}`">
                <span class="kit-tag">Тема</span>
                <RouterLink :to="`/forum/${t.id}`" class="dossier__link" :class="{ deleted: t.deleted_at }" @click="emit('close')">
                  {{ t.title }}
                </RouterLink>
                <button v-if="!t.deleted_at" type="button" class="dossier__del" :disabled="busy" @click="deleteTopic(t)">Удалить</button>
                <small v-else class="kit-muted">удалена</small>
              </li>
              <li v-for="r in data.forum.replies" :key="`r${r.id}`">
                <span class="kit-tag">Ответ</span>
                <RouterLink :to="`/forum/${r.topic_id}`" class="dossier__link" :class="{ deleted: r.deleted }" @click="emit('close')">
                  {{ r.body }}
                  <small class="kit-muted">— в «{{ r.topic_title }}»</small>
                </RouterLink>
                <button v-if="!r.deleted" type="button" class="dossier__del" :disabled="busy" @click="deleteReply(r)">Удалить</button>
                <small v-else class="kit-muted">удалён</small>
              </li>
            </ul>
          </section>

          <!-- Рефералы -->
          <section class="dossier__section">
            <h3>Рефералы</h3>
            <p class="kit-muted">
              <template v-if="data.referrals.invited_by">
                Пришёл по приглашению
                <button type="button" class="kit-user" @click="emit('open', data.referrals.invited_by.id)">
                  <span>{{ data.referrals.invited_by.username }}</span>
                </button>.
              </template>
              <template v-else>Пришёл сам.</template>
              Пригласил: <b>{{ data.referrals.invited_count }}</b>
              <template v-if="data.referrals.coins_earned !== null"> · заработал {{ data.referrals.coins_earned }} ApexCoin</template>
              <template v-if="data.referrals.code"> · код {{ data.referrals.code }}</template>
            </p>
            <ul v-if="data.referrals.invited.length" class="dossier__list">
              <li v-for="r in data.referrals.invited" :key="r.id">
                <span class="kit-tag" :class="r.tested ? 'kit-tag--green' : ''">{{ r.tested ? `тир ${r.tier}` : 'без теста' }}</span>
                <span>
                  <button type="button" class="kit-user" @click="emit('open', r.id)"><span>{{ r.username }}</span></button>
                  <template v-if="r.is_banned"> · <b class="red">бан</b></template>
                </span>
                <small class="kit-muted">{{ date(r.created_at) }}</small>
              </li>
            </ul>
          </section>

          <!-- Тир-тесты -->
          <section class="dossier__section">
            <h3>Тир-тесты</h3>
            <p v-if="!data.tier_tests.length" class="kit-muted">Не проходил</p>
            <ul class="dossier__list">
              <li v-for="t in data.tier_tests" :key="t.id">
                <span class="kit-tag">{{ t.mode === 'pvp' ? 'PvP' : 'BedWars' }}</span>
                <span>
                  {{ TEST_STATUS[t.status] ?? t.status }}
                  <template v-if="t.result_tier"> · {{ t.result_tier }} ({{ Math.round(t.result_score) }}%)</template>
                  <template v-if="t.tester"> · тестер {{ t.tester.username }}</template>
                </span>
                <small class="kit-muted">{{ date(t.completed_at || t.created_at) }}</small>
              </li>
            </ul>
          </section>

          <!-- ApexCoin -->
          <section v-if="data.transactions" class="dossier__section">
            <h3>Последние операции ApexCoin</h3>
            <p v-if="!data.transactions.length" class="kit-muted">Операций нет</p>
            <ul class="dossier__list">
              <li v-for="tx in data.transactions" :key="tx.id">
                <b :class="tx.amount > 0 ? 'green' : 'red'">{{ tx.amount > 0 ? '+' : '' }}{{ tx.amount }}</b>
                <span>{{ tx.description || tx.source }}</span>
                <small class="kit-muted">{{ date(tx.created_at, true) }}</small>
              </li>
            </ul>
          </section>

          <!-- Инвентарь: посмотреть и управлять -->
          <AdminDossierInventory
              v-if="auth.user?.role === 'admin'"
              :user-id="user.id"
              :username="user.username"
              :refresh-key="inventoryKey"
          />
        </template>
      </aside>
    </div>

    <!-- Модалки игры: те же, что в списке пользователей -->
    <AdminAspectsModal v-if="modal === 'aspects'" :user="user" @close="modal = null" @updated="onModalUpdated" />
    <AdminTierTestModal v-if="modal === 'tierTest'" :user="user" @close="modal = null" @updated="onModalUpdated" />
    <AdminAchievementsModal v-if="modal === 'achievements'" :user="user" @close="modal = null" @updated="onModalUpdated" />
    <AdminRoleModal v-if="modal === 'role'" :user="user" @close="modal = null" @updated="onModalUpdated" />
  </Teleport>
</template>

<style scoped>
@import "@/components/admin/admin-kit.css";

.dossier-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2000;

  display: flex;
  justify-content: flex-end;

  background: rgba(0, 0, 0, 0.5);
}

.dossier {
  position: relative;

  width: min(560px, 100%);
  height: 100%;
  overflow-y: auto;

  display: flex;
  flex-direction: column;
  gap: 16px;

  padding: 24px;

  background: var(--bg);
  border-left: 1px solid var(--border);

  animation: dossier-in 0.18s ease-out;
}

@keyframes dossier-in {
  from { transform: translateX(30px); opacity: 0; }
}

.dossier__close {
  position: absolute;
  top: 12px;
  right: 14px;

  width: 32px;
  height: 32px;

  color: var(--text-muted);
  background: none;
  border: 0;

  font-size: 26px;
  line-height: 1;

  cursor: pointer;
}

.dossier__head {
  display: flex;
  align-items: center;
  gap: 14px;
  padding-right: 30px;
}

.dossier__head h2 {
  margin: 0 0 6px;
  font-family: var(--font-display);
  font-size: 22px;
  word-break: break-all;
}

.dossier__avatar {
  flex-shrink: 0;

  width: 64px;
  height: 64px;

  display: flex;
  align-items: center;
  justify-content: center;

  overflow: hidden;

  color: #fff;
  background: var(--accent);
  border-radius: 6px;

  font-family: var(--font-display);
  font-size: 26px;
  font-weight: 700;
}

.dossier__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.dossier__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 0;
}

.dossier__ban {
  margin: 0;
  padding: 10px 12px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border-radius: 6px;
  font-size: 14px;
}

/* ---------- Панель действий ---------- */

.dossier__panel {
  display: flex;
  flex-direction: column;
  gap: 10px;

  padding: 14px;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px;
}

.dossier__group {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.dossier__group + .dossier__group {
  padding-top: 10px;
  border-top: 1px solid var(--border);
}

.dossier__group-title {
  width: 100%;

  color: var(--text-muted);

  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.dossier__group .kit-btn {
  text-decoration: none;
}

.dossier__inline {
  display: inline-flex;
  gap: 6px;
}

/* ---------- Сведения ---------- */

.dossier__facts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px 16px;

  margin: 0;
  padding: 14px;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px;
}

.dossier__facts dt {
  color: var(--text-muted);
  font-size: 12px;
}

.dossier__facts dd {
  margin: 2px 0 0;
  font-weight: 600;
  font-size: 14px;
  word-break: break-word;
}

.dossier__section h3 {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin: 0 0 8px;
  font-size: 15px;
}

.dossier__section p {
  margin: 0 0 4px;
}

.dossier__list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.dossier__list li {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
  font-size: 14px;
}

.dossier__link {
  overflow: hidden;
  color: var(--text);
  text-decoration: none;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dossier__link:hover {
  text-decoration: underline;
}

.dossier__link.deleted {
  color: var(--text-muted);
  text-decoration: line-through;
}

.dossier__del {
  padding: 0;
  color: #fca5a5;
  background: none;
  border: 0;
  font: inherit;
  font-size: 12px;
  cursor: pointer;
}

.green { color: #4ade80; }
.red { color: #f87171; }

@media (max-width: 520px) {
  .dossier {
    padding: 18px 14px;
  }

  .dossier__facts {
    grid-template-columns: 1fr;
  }

  .dossier__list li {
    grid-template-columns: 1fr;
    gap: 2px;
  }
}
</style>
