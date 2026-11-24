<script setup>
/**
 * Панель игрока на главной: что можно сделать прямо сейчас и когда
 * откроется остальное. Питомцы (покормить, погладить, поиграть),
 * отметка в клане, бесплатный сундук, шахта, аукцион и надетый образ.
 * Ежедневное сбрасывается в полночь — таймер до сброса тикает на месте.
 */
import { computed, onMounted, ref } from 'vue'
import { useNow } from '@/composables/core/useNow.js'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'
import { walletApi } from '@/services/wallet/wallet.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import { PIXEL_SPRITES } from '@/data/shop/pixelSprites.js'
import { petModelStyle, PET_RARITY } from '@/data/players/petTraits.js'
import { TYPE_LABELS, RARITY_COLORS, formatCoins } from '@/data/wallet/economy.js'

const auth = useAuthStore()

const data = ref(null)
const now = useNow()

const PET_ACTIONS = [
  ['feed', 'meat', 'Покормить'],
  ['pet', 'heart', 'Погладить'],
  ['play', 'ball', 'Поиграть'],
]

async function load() {
  try {
    data.value = await api.get('/home/me')
  } catch {
    /* панель не критична — главная работает и без неё */
  }
}

function until(iso) {
  const ms = new Date(iso).getTime() - now.value
  if (ms <= 0) return null
  const h = Math.floor(ms / 3600000)
  const m = Math.floor((ms % 3600000) / 60000)
  const s = Math.floor((ms % 60000) / 1000)
  return h > 0 ? `${h} ч ${m} мин` : `${m}:${String(s).padStart(2, '0')}`
}

const resetIn = computed(() => (data.value ? until(data.value.reset_at) : null))
const minerWait = computed(() => (data.value?.miner?.cooldown_until ? until(data.value.miner.cooldown_until) : null))



const claiming = ref(false)
const bonusNote = ref('')

async function claimBonus() {
  claiming.value = true
  try {
    const result = await walletApi.claimDailyBonus()
    bonusNote.value = `+${formatCoins(result.amount)}`
    data.value.bonus.available = false
    data.value.coins = result.balance ?? data.value.coins
    auth.fetchMe()
  } catch (e) {
    bonusNote.value = ''
    if (data.value.bonus) data.value.bonus.available = false
  } finally {
    claiming.value = false
  }
}

function spriteOf(pet) {
  return PIXEL_SPRITES[pet.sprite] ? pet.sprite : 'paw'
}

onMounted(load)
</script>

<template>
  <section v-if="data" class="hd">
    <header class="hd__head">
      <div>
        <h2>Личный кабинет</h2>
        <p>{{ auth.user?.username }}<span v-if="resetIn"> · обновление заданий через {{ resetIn }}</span></p>
      </div>
      <RouterLink to="/shop" class="hd__coins" title="Баланс ApexCoin">
        <AppIcon icon="coin" :size="16" />
        {{ formatCoins(data.coins) }}
      </RouterLink>
    </header>

    <div class="hd__grid">
      <!-- Питомцы -->
      <article class="hd-card hd-card--pets">
        <h3>
          <PixelSprite sprite="paw" :size="16" /> Питомцы
          <RouterLink to="/world?at=expeditions" class="hd-more">Экспедиции и арена</RouterLink>
        </h3>

        <div v-if="data.pets.length" class="hd-pets">
          <RouterLink
              v-for="pet in data.pets"
              :key="pet.inventory_id ?? pet.sprite"
              to="/profile"
              class="hd-pet"
              :style="{ '--rar': PET_RARITY[pet.traits?.rarity]?.color || '#a8a3b8' }"
          >
            <span class="hd-pet__sprite" :style="petModelStyle(pet.traits)">
              <PixelSprite :sprite="spriteOf(pet)" :size="34" />
            </span>
            <span class="hd-pet__info">
              <strong>{{ pet.name || pet.traits?.model?.name || 'Питомец' }}</strong>
              <small>Ур. {{ pet.level }}</small>
            </span>
            <span class="hd-pet__acts">
              <span
                  v-for="[action, sprite, label] in PET_ACTIONS"
                  :key="action"
                  class="hd-act"
                  :class="{ 'is-done': pet.done.includes(action) }"
                  :title="pet.done.includes(action) ? `${label}: уже сегодня, снова через ${resetIn}` : `${label}: можно сейчас`"
              >
                <PixelSprite :sprite="sprite" :size="14" />
              </span>
            </span>
          </RouterLink>
        </div>
        <p v-else class="hd-card__empty">
          Питомцев пока нет — они выпадают в <RouterLink to="/cases">сундуках</RouterLink> и продаются на <RouterLink to="/auction">аукционе</RouterLink>.
        </p>
      </article>

      <!-- Ежедневное -->
      <article class="hd-card">
        <h3><AppIcon icon="clock" :size="15" /> На сегодня</h3>

        <ul class="hd-list">
          <li v-if="data.bonus">
            <span class="hd-list__icon"><PixelSprite sprite="sparkle" :size="18" /></span>
            <span class="hd-list__text">
              <strong>Бонус за вход</strong>
              <small v-if="data.bonus.available">₳ {{ formatCoins(data.bonus.amount) }} ждут тебя</small>
              <small v-else>получен{{ bonusNote ? ` · ${bonusNote}` : '' }} · снова через {{ resetIn }}</small>
            </span>
            <button v-if="data.bonus.available" type="button" class="hd-go" :disabled="claiming" @click="claimBonus">Забрать</button>
            <span v-else class="hd-wait">{{ resetIn }}</span>
          </li>

          <li>
            <span class="hd-list__icon"><PixelSprite sprite="totem" :size="18" /></span>
            <span class="hd-list__text">
              <strong>Бесплатный сундук</strong>
              <small>{{ data.chest.available ? 'можно открыть' : `снова через ${resetIn}` }}</small>
            </span>
            <RouterLink v-if="data.chest.available" to="/cases" class="hd-go">Открыть</RouterLink>
            <span v-else class="hd-wait">{{ resetIn }}</span>
          </li>

          <li>
            <span class="hd-list__icon"><PixelSprite sprite="diamond" :size="18" /></span>
            <span class="hd-list__text">
              <strong>Шахтёр</strong>
              <small v-if="data.miner.active">забег не закончен</small>
              <small v-else-if="minerWait">кирка остывает</small>
              <small v-else>можно копать{{ data.miner.best ? ` · рекорд ₳ ${data.miner.best}` : '' }}</small>
            </span>
            <span v-if="minerWait && !data.miner.active" class="hd-wait">{{ minerWait }}</span>
            <RouterLink v-else to="/miner" class="hd-go">{{ data.miner.active ? 'Вернуться' : 'Копать' }}</RouterLink>
          </li>

          <li>
            <span class="hd-list__icon"><PixelSprite sprite="crown" :size="18" /></span>
            <span class="hd-list__text">
              <strong>Отметка в клане</strong>
              <small v-if="!data.clan">ты не в клане</small>
              <small v-else-if="data.clan.checked_in">[{{ data.clan.tag }}] отмечен · снова через {{ resetIn }}</small>
              <small v-else>[{{ data.clan.tag }}] ждёт отметки</small>
            </span>
            <RouterLink v-if="!data.clan" to="/clans" class="hd-go hd-go--ghost">Найти</RouterLink>
            <span v-else-if="data.clan.checked_in" class="hd-wait">{{ resetIn }}</span>
            <RouterLink v-else to="/my-clan" class="hd-go">Отметиться</RouterLink>
          </li>

          <li>
            <span class="hd-list__icon"><PixelSprite sprite="flame" :size="18" /></span>
            <span class="hd-list__text">
              <strong>Аукцион</strong>
              <small>лотов {{ data.auction.total }} · твоих {{ data.auction.mine }}/3 · лидируешь в {{ data.auction.leading }}</small>
            </span>
            <RouterLink to="/auction" class="hd-go hd-go--ghost">Открыть</RouterLink>
          </li>
        </ul>
      </article>

      <!-- Образ -->
      <article class="hd-card">
        <h3><AppIcon icon="palette" :size="15" /> Профиль</h3>

        <details class="hd-outfit">
          <summary>Оформление <span>{{ data.equipped.length }} предметов</span></summary>
        <ul v-if="data.equipped.length" class="hd-worn">
          <li v-for="(item, i) in data.equipped.slice(0, 8)" :key="i" :style="{ '--r': RARITY_COLORS[item.rarity] || '#8b8b9e' }">
            <small>{{ TYPE_LABELS[item.type] || item.type }}</small>
            <span>{{ item.name }}</span>
          </li>
        </ul>
        <p v-else class="hd-card__empty">Ничего не надето — загляни в <RouterLink to="/inventory">инвентарь</RouterLink>.</p>
        </details>

        <div class="hd-quick">
          <RouterLink to="/profile"><AppIcon icon="palette" :size="14" /> Студия</RouterLink>
          <RouterLink to="/inventory"><AppIcon icon="box" :size="14" /> Инвентарь</RouterLink>
          <RouterLink to="/shop"><AppIcon icon="gift" :size="14" /> Магазин</RouterLink>
          <RouterLink to="/friends"><AppIcon icon="handshake" :size="14" /> Друзья</RouterLink>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.hd {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.hd__head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
}

.hd__kicker {
  color: #4ade80;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.hd__head h2 { margin: 6px 0 4px; color: #fff; font-family: var(--font-display); font-size: clamp(22px, 3vw, 30px); line-height: 1.1; }
.hd__head p { margin: 0; color: #8a8598; font-size: 14px; }
.hd__head p b { color: #4ade80; }

.hd__coins {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;

  padding: 10px 14px;

  color: #fde68a;
  background: rgba(251, 191, 36, 0.08);
  border: 1px solid rgba(251, 191, 36, 0.25);
  border-radius: 12px;

  font-size: 18px;
  font-weight: 900;
  text-decoration: none;
}

.hd__grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr) minmax(0, 1fr);
  gap: 12px;
}

.hd-card {
  display: flex;
  flex-direction: column;
  gap: 12px;

  padding: 16px;

  background: var(--bg-card, #15131c);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 16px;
}

.hd-card h3 {
  display: flex;
  align-items: center;
  gap: 8px;

  margin: 0;

  color: #d6d2e2;

  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.hd-more {
  margin-left: auto;

  color: #c4b5fd;

  font-size: 11px;
  letter-spacing: 0;
  text-decoration: none;
  text-transform: none;
}

.hd-more:hover { color: #fff; }

.hd-card__empty { margin: 0; color: #8a8598; font-size: 13px; line-height: 1.5; }
.hd-card__empty a { color: #c4b5fd; }

/* ---------- Питомцы ---------- */

.hd-pets { display: flex; flex-direction: column; gap: 8px; }

.hd-pet {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  grid-template-areas: "sprite info" "sprite acts";
  align-items: center;
  column-gap: 12px;
  row-gap: 6px;

  padding: 10px;

  color: inherit;
  background: radial-gradient(circle at 0 50%, color-mix(in srgb, var(--rar) 18%, transparent), transparent 60%), rgba(255, 255, 255, 0.025);
  border: 1px solid color-mix(in srgb, var(--rar) 25%, rgba(255, 255, 255, 0.05));
  border-radius: 12px;

  text-decoration: none;
}

.hd-pet:hover { border-color: color-mix(in srgb, var(--rar) 60%, transparent); }

.hd-pet__sprite { grid-area: sprite; display: grid; place-items: center; width: 52px; height: 48px; }
.hd-pet__info { grid-area: info; display: flex; align-items: baseline; gap: 8px; min-width: 0; }
.hd-pet__info strong { overflow: hidden; color: #fff; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }
.hd-pet__info small { flex-shrink: 0; color: var(--rar); font-size: 12px; font-weight: 800; }
.hd-pet__acts { grid-area: acts; display: flex; gap: 5px; }

.hd-act {
  width: 30px;
  height: 26px;

  display: grid;
  place-items: center;

  background: rgba(74, 222, 128, 0.12);
  border: 1px solid rgba(74, 222, 128, 0.35);
  border-radius: 7px;
}

.hd-act.is-done { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.06); filter: grayscale(1); opacity: 0.45; }

/* ---------- Ежедневное ---------- */

.hd-list { display: flex; flex-direction: column; gap: 4px; margin: 0; padding: 0; list-style: none; }

.hd-list li {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;

  padding: 8px;

  border-radius: 10px;
}

.hd-list li:hover { background: rgba(255, 255, 255, 0.03); }

.hd-list__icon {
  width: 34px;
  height: 34px;

  display: grid;
  place-items: center;

  background: rgba(255, 255, 255, 0.04);
  border-radius: 9px;
}

.hd-list__text { display: flex; flex-direction: column; min-width: 0; }
.hd-list__text strong { color: #fff; font-size: 13.5px; }
.hd-list__text small { overflow: hidden; color: #8a8598; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }

.hd-go {
  padding: 6px 12px;

  color: #052e16;
  background: #4ade80;
  border: 0;
  border-radius: 8px;

  font: inherit;
  font-size: 12px;
  cursor: pointer;
  font-weight: 800;
  text-decoration: none;
  white-space: nowrap;
}

.hd-go:hover { background: #86efac; }
.hd-go--ghost { color: #d6d2e2; background: rgba(255, 255, 255, 0.06); }
.hd-go--ghost:hover { color: #fff; background: rgba(255, 255, 255, 0.12); }

.hd-wait {
  color: #a8a3b8;
  font-size: 12px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

/* ---------- Образ ---------- */

.hd-worn { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin: 0; padding: 0; list-style: none; }

.hd-worn li {
  display: flex;
  flex-direction: column;
  min-width: 0;

  padding: 7px 9px;

  background: rgba(255, 255, 255, 0.03);
  border-left: 2px solid var(--r);
  border-radius: 6px;
}

.hd-worn small { color: #6f6a80; font-size: 10px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; }
.hd-worn span { overflow: hidden; color: #e9e5f5; font-size: 12.5px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }

.hd-quick { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: auto; }

.hd-quick a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;

  min-height: 36px;

  color: #d6d2e2;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 9px;

  font-size: 12.5px;
  font-weight: 700;
  text-decoration: none;
}

.hd-quick a:hover { color: #fff; border-color: rgba(139, 92, 246, 0.6); }

@media (max-width: 980px) {
  .hd__grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
  .hd-card--pets { grid-column: 1 / -1; }
  .hd-pets { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
}

@media (max-width: 640px) {
  .hd__head { flex-direction: column; align-items: stretch; }
  .hd__coins { align-self: flex-start; }
  .hd__grid { grid-template-columns: minmax(0, 1fr); }
}

/* Compact account panel; actions retain their original handlers and limits. */
.hd { gap:12px; }
.hd__head { align-items:center; }
.hd__head h2 { margin:0 0 5px; font-family:inherit; font-size:19px; font-weight:600; line-height:1.3; }
.hd__head p { font-size:12px; }
.hd__coins { padding:7px 10px; color:var(--text); background:var(--bg-card); border-color:var(--border); border-radius:7px; font-size:14px; font-weight:600; }
.hd__grid { grid-template-columns:minmax(0,1fr) minmax(0,1.35fr) minmax(0,.85fr); align-items:start; gap:12px; }
.hd-card { padding:13px; gap:10px; border-radius:8px; }
.hd-card h3 { font-family:inherit; font-size:12px; font-weight:600; letter-spacing:0; text-transform:none; }
.hd-pet { padding:8px; gap:5px 9px; background:transparent; border:1px solid var(--border); border-radius:6px; }
.hd-pet__sprite { width:38px; height:40px; }
.hd-pet__info strong { font-size:12px; font-weight:500; }
.hd-pet__info small { font-size:10px; font-weight:500; }
.hd-act { width:26px; height:23px; border-radius:5px; border-color:rgba(160,150,180,.2); background:rgba(160,150,180,.05); }
.hd-list { gap:1px; }
.hd-list li { padding:5px 2px; gap:8px; border-radius:4px; }
.hd-list__icon { width:26px; height:26px; background:transparent; }
.hd-list__text strong { font-size:12px; font-weight:500; }
.hd-list__text small { font-size:10px; }
.hd-go { padding:5px 9px; color:#e9e2f5; background:#443456; border:1px solid #655078; border-radius:5px; font-size:10px; font-weight:500; }
.hd-go:hover { background:#57436e; }
.hd-go--ghost { color:var(--text-muted); background:transparent; border-color:var(--border); }
.hd-wait { font-size:10px; font-weight:400; }
.hd-outfit summary { display:flex; justify-content:space-between; gap:8px; padding:4px 0 10px; color:var(--text); font-size:12px; cursor:pointer; }
.hd-outfit summary span { color:var(--text-muted); font-size:10px; }
.hd-worn { margin:0 0 10px; gap:5px; }
.hd-worn li { padding:6px; background:transparent; border-left:1px solid var(--border); }
.hd-worn small { font-size:9px; font-weight:400; letter-spacing:0; text-transform:none; }
.hd-worn span { font-size:11px; font-weight:400; }
.hd-quick { gap:5px; margin-top:0; }
.hd-quick a { min-height:30px; font-size:10px; font-weight:500; border-radius:5px; background:transparent; }
@media(max-width:980px) { .hd__grid { grid-template-columns:1fr 1.3fr; } .hd__grid > .hd-card:last-child { grid-column:1/-1; } .hd-quick { grid-template-columns:repeat(4,1fr); } }
@media(max-width:600px) { .hd__head { flex-direction:row; align-items:flex-start; text-align:left; } .hd__head > div { min-width:0; flex:1; } .hd__grid { grid-template-columns:1fr; } .hd__head h2 { font-size:17px; } .hd__head p { max-width:220px; line-height:1.5; } .hd__coins { font-size:12px; } }
</style>
