<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AppIcon from '@/components/core/AppIcon.vue'
import { testerCabinetApi } from '@/services/testers/cabinet.js'
import { confirm as confirmDialog, prompt as promptDialog } from '@/utils/dialog.js'

/**
 * Управление тестерами: кто сколько провёл, сколько заработал, выплаты,
 * экономика (ставки и множители по тирам), глобальный календарь.
 */

const TABS = [
    ['testers', 'Тестеры'],
    ['payouts', 'Выплаты'],
    ['economy', 'Экономика'],
    ['calendar', 'Календарь'],
]

const TIERS = ['S+', 'S', 'A', 'B', 'C', 'D', 'E']

const tab = ref('testers')
const testers = ref([])
const payouts = ref([])
const slots = ref([])
const loading = ref(true)
const busy = ref(false)
const error = ref('')
const notice = ref('')

const form = ref({ pay_rub: 50, pay_coins: 500, tier_multipliers: {} })

const pendingPayouts = computed(() => payouts.value.filter((payout) => payout.status === 'pending'))

/* ---------- Календарь: неделя ---------- */

const WEEKDAYS = ['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс']

/** Понедельник недели, в которой лежит дата. */
function startOfWeek(date) {
    const day = new Date(date)
    day.setHours(0, 0, 0, 0)
    day.setDate(day.getDate() - ((day.getDay() + 6) % 7))

    return day
}

const weekStart = ref(startOfWeek(new Date()))

const weekTitle = computed(() => {
    const from = weekStart.value
    const to = new Date(from)
    to.setDate(to.getDate() + 6)

    const format = (date) => date.toLocaleDateString('ru-RU', { day: '2-digit', month: 'long' })

    return `${format(from)} — ${format(to)}`
})

const dayKey = (value) => {
    const date = new Date(value)
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${date.getFullYear()}-${month}-${day}`
}

const todayKey = dayKey(new Date())

const weekDays = computed(() => {
    const days = []

    for (let i = 0; i < 7; i++) {
        const date = new Date(weekStart.value)
        date.setDate(weekStart.value.getDate() + i)

        const key = dayKey(date)

        days.push({
            key,
            label: WEEKDAYS[i],
            num: date.getDate(),
            today: key === todayKey,
            slots: slots.value
                .filter((slot) => dayKey(slot.starts_at) === key)
                .sort((a, b) => a.starts_at.localeCompare(b.starts_at)),
        })
    }

    return days
})

async function loadCalendar() {
    const from = new Date(weekStart.value)
    const to = new Date(weekStart.value)
    to.setDate(to.getDate() + 6)
    to.setHours(23, 59, 59)

    slots.value = (await testerCabinetApi.adminCalendar({
        from: from.toISOString(),
        to: to.toISOString(),
    })).slots ?? []
}

function shiftWeek(delta) {
    const next = new Date(weekStart.value)
    next.setDate(next.getDate() + delta * 7)
    weekStart.value = next
    loadCalendar()
}

function thisWeek() {
    weekStart.value = startOfWeek(new Date())
    loadCalendar()
}

function when(slot) {
    return new Date(slot.starts_at).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
        + '–'
        + new Date(slot.ends_at).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}

const statusName = (status) => ({
    open: 'свободен',
    booked: 'занят',
    closed: 'снят',
}[status] ?? status)

async function load() {
    loading.value = true
    error.value = ''

    try {
        if (tab.value === 'testers') {
            testers.value = (await testerCabinetApi.adminTesters()).testers ?? []
        } else if (tab.value === 'payouts') {
            payouts.value = (await testerCabinetApi.adminPayouts()).payouts ?? []
        } else if (tab.value === 'economy') {
            const settings = await testerCabinetApi.adminSettings()

            form.value = {
                pay_rub: settings.pay_rub,
                pay_coins: settings.pay_coins,
                tier_multipliers: { ...settings.tier_multipliers },
            }
        } else {
            await loadCalendar()
        }
    } catch (e) {
        error.value = e.message ?? 'Не удалось загрузить раздел'
    } finally {
        loading.value = false
    }
}

async function act(call, message = '') {
    busy.value = true
    error.value = ''
    notice.value = ''

    try {
        await call()
        if (message) notice.value = message
        await load()
    } catch (e) {
        error.value = e.message ?? 'Не получилось'
    } finally {
        busy.value = false
    }
}

/* ---------- Действия ---------- */

const blockTester = (tester) => act(async () => {
    const days = await promptDialog('На сколько дней закрыть доступ? Например: 7', {
        title: `Закрыть календарь: ${tester.username}`,
        placeholder: '7',
    })

    if (days === null) return

    const until = new Date()
    until.setDate(until.getDate() + Math.max(1, Number(days) || 7))

    const reason = await promptDialog('Причина — её увидит тестер в кабинете.', {
        title: 'Причина',
        placeholder: 'Например: ушёл в афк',
    })

    await testerCabinetApi.blockTester(tester.id, {
        until: until.toISOString(),
        reason: reason || null,
    })
}, 'Календарь тестера закрыт')

const unblockTester = (tester) => act(async () => {
    const ok = await confirmDialog(
        `${tester.username} снова сможет открывать слоты. Открыть календарь?`,
        { title: 'Открыть календарь', confirmText: 'Открыть' },
    )

    if (!ok) return

    await testerCabinetApi.unblockTester(tester.id)
}, 'Календарь снова открыт')

const payPayout = (payout) => act(async () => {
    const comment = await promptDialog(
        `Выплатить ${payout.rub} ₽ и ${payout.coins} ApexCoin? Комментарий для истории (необязательно).`,
        { title: 'Отметить выплату', placeholder: 'перевёл на карту' },
    )

    // Отмена окна — выплату не отмечаем
    if (comment === null) return

    await testerCabinetApi.payPayout(payout.id, comment)
}, 'Выплата отмечена')

const rejectPayout = (payout) => act(async () => {
    const comment = await promptDialog('Причина отказа — тестер увидит её в кабинете.', {
        title: 'Отклонить выплату',
        placeholder: 'Например: неверные реквизиты',
    })

    if (!comment) return

    await testerCabinetApi.rejectPayout(payout.id, comment)
}, 'Выплата отклонена')

const saveSettings = () => act(async () => {
    await testerCabinetApi.saveSettings({
        pay_rub: Number(form.value.pay_rub),
        pay_coins: Number(form.value.pay_coins),
        tier_multipliers: Object.fromEntries(
            TIERS.map((tier) => [tier, Number(form.value.tier_multipliers[tier] ?? 1)]),
        ),
    })
}, 'Настройки сохранены')

const previewRub = (tier) => Math.round(Number(form.value.pay_rub) * Number(form.value.tier_multipliers[tier] ?? 1))

watch(tab, load)
onMounted(load)
</script>

<template>
    <div class="at">
        <header class="at-head">
            <p>Кто сколько провёл, сколько заработал, выплаты, экономика и календарь смен.</p>

            <span v-if="tab === 'payouts' && pendingPayouts.length" class="at-badge">
                {{ pendingPayouts.length }} ждёт выплаты
            </span>
        </header>

        <nav class="at-tabs">
            <button
                v-for="[id, label] in TABS"
                :key="id"
                type="button"
                class="at-tab"
                :class="{ 'is-on': tab === id }"
                @click="tab = id"
            >
                {{ label }}
            </button>
        </nav>

        <p v-if="error" class="at-alert at-alert--bad">{{ error }}</p>
        <p v-if="notice" class="at-alert at-alert--ok">{{ notice }}</p>

        <p v-if="loading" class="at-hint">Загрузка…</p>

        <!-- ================= Тестеры ================= -->
        <template v-else-if="tab === 'testers'">
            <p v-if="!testers.length" class="at-hint">Нет ни одного тестера. Выдайте роль в разделе «Пользователи».</p>

            <div class="at-list">
                <article v-for="tester in testers" :key="tester.id" class="at-tester" :class="{ 'is-blocked': tester.blocked_until }">
                    <div class="at-tester__id">
                        <strong>{{ tester.username }}</strong>
                        <small>{{ tester.role === 'admin' ? 'администратор' : 'тестер' }}</small>
                    </div>

                    <div class="at-tester__stats">
                        <span><b>{{ tester.completed }}</b><small>тестов</small></span>
                        <span><b>{{ tester.avg_score || '—' }}</b><small>средний балл</small></span>
                        <span><b>{{ tester.cancelled }}</b><small>отмен</small></span>
                        <span><b>{{ tester.earned_rub }} ₽</b><small>заработал</small></span>
                        <span><b>{{ tester.paid_rub }} ₽</b><small>выплачено</small></span>
                        <span v-if="tester.pending_rub"><b>{{ tester.pending_rub }} ₽</b><small>ждёт</small></span>
                    </div>

                    <div class="at-tester__actions">
                        <span v-if="tester.blocked_until" class="at-tag at-tag--bad">
                            закрыт до {{ new Date(tester.blocked_until).toLocaleDateString('ru-RU') }}
                        </span>

                        <button v-if="tester.blocked_until" type="button" class="at-btn" :disabled="busy" @click="unblockTester(tester)">
                            Открыть календарь
                        </button>
                        <button v-else type="button" class="at-btn at-btn--ghost" :disabled="busy" @click="blockTester(tester)">
                            Закрыть календарь
                        </button>
                    </div>
                </article>
            </div>
        </template>

        <!-- ================= Выплаты ================= -->
        <template v-else-if="tab === 'payouts'">
            <p v-if="!payouts.length" class="at-hint">Запросов на выплату пока нет.</p>

            <div v-else class="at-list">
                <article v-for="payout in payouts" :key="payout.id" class="at-payout" :class="`is-${payout.status}`">
                    <div class="at-payout__who">
                        <strong>{{ payout.tester?.username ?? 'тестер' }}</strong>
                        <small>{{ new Date(payout.created_at).toLocaleString('ru-RU') }}</small>
                    </div>

                    <div class="at-payout__sum">
                        <b>{{ payout.rub }} ₽</b>
                        <span>{{ payout.coins }} ApexCoin</span>
                    </div>

                    <span class="at-tag" :class="{ 'at-tag--bad': payout.status === 'rejected', 'at-tag--ok': payout.status === 'paid' }">
                        {{ { pending: 'ждёт решения', paid: 'выплачено', rejected: 'отклонено' }[payout.status] }}
                    </span>

                    <small v-if="payout.comment" class="at-payout__comment">{{ payout.comment }}</small>

                    <div v-if="payout.status === 'pending'" class="at-payout__actions">
                        <button type="button" class="at-btn at-btn--main" :disabled="busy" @click="payPayout(payout)">
                            Выплачено
                        </button>
                        <button type="button" class="at-btn at-btn--ghost" :disabled="busy" @click="rejectPayout(payout)">
                            Отклонить
                        </button>
                    </div>
                </article>
            </div>
        </template>

        <!-- ================= Экономика ================= -->
        <template v-else-if="tab === 'economy'">
            <div class="at-economy">
                <label class="at-field">
                    <span>Оплата за тест, ₽</span>
                    <input v-model.number="form.pay_rub" type="number" min="0" max="100000" />
                </label>

                <label class="at-field">
                    <span>Оплата за тест, ApexCoin</span>
                    <input v-model.number="form.pay_coins" type="number" min="0" max="1000000" />
                </label>
            </div>

            <h3 class="at-h3">Множитель по тиру</h3>
            <p class="at-hint">За S+ можно платить больше, чем за E. Значение 1 — базовая ставка.</p>

            <div class="at-mult">
                <label v-for="tier in TIERS" :key="tier" class="at-mult__row">
                    <b>{{ tier }}</b>
                    <input v-model.number="form.tier_multipliers[tier]" type="number" step="0.05" min="0" max="10" />
                    <small>{{ previewRub(tier) }} ₽ за тест</small>
                </label>
            </div>

            <button type="button" class="at-btn at-btn--main" :disabled="busy" @click="saveSettings">
                Сохранить настройки
            </button>
        </template>

        <!-- ================= Календарь ================= -->
        <template v-else>
            <div class="at-week__nav">
                <button type="button" class="at-btn at-btn--ghost" :disabled="busy" @click="shiftWeek(-1)">
                    ← неделя
                </button>

                <b>{{ weekTitle }}</b>

                <button type="button" class="at-btn at-btn--ghost" :disabled="busy" @click="thisWeek">
                    Сегодня
                </button>

                <button type="button" class="at-btn at-btn--ghost" :disabled="busy" @click="shiftWeek(1)">
                    неделя →
                </button>
            </div>

            <div class="at-week">
                <section
                    v-for="day in weekDays"
                    :key="day.key"
                    class="at-week__day"
                    :class="{ 'is-today': day.today }"
                >
                    <header class="at-week__head">
                        <span>{{ day.label }}</span>
                        <b>{{ day.num }}</b>
                    </header>

                    <small v-if="!day.slots.length" class="at-week__empty">—</small>

                    <div
                        v-for="slot in day.slots"
                        :key="slot.id"
                        class="at-week__slot"
                        :class="`is-${slot.status}`"
                        :title="slot.note ?? null"
                    >
                        <b>{{ when(slot) }}</b>
                        <span>{{ slot.tester?.username }}</span>
                        <small v-if="slot.tier_test">
                            → {{ slot.tier_test.user?.username ?? 'игрок' }}
                        </small>
                        <small v-else>{{ statusName(slot.status) }}</small>
                    </div>
                </section>
            </div>

            <p class="at-hint">
                Зелёные — заняты заявкой (тестер → игрок), серые — свободны, бледные — сняты тестером.
            </p>
        </template>
    </div>
</template>

<style scoped>
@import "./AdminTesters.css";
</style>
