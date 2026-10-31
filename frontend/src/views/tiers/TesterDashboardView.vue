<script setup>
import { computed, onActivated, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '@/components/core/AppIcon.vue'
import PageLoader from '@/components/core/PageLoader.vue'
import TesterTierTestModal from '@/components/tiers/tester/TesterTierTestModal.vue'
import { testerCabinetApi } from '@/services/testers/cabinet.js'
import { testerApi } from '@/services/tiers/tester.js'
import { confirm as confirmDialog, confirmWithReason as confirmWithReasonDialog } from '@/utils/dialog.js'

/**
 * Кабинет тестера: расписание, заявки, заработок и профиль.
 *
 * Календарь показывает неделю: тестер открывает слоты («готов тестировать»),
 * игроки записываются на них, а занятые слоты подсвечены заявкой.
 */

const TABS = [
    ['calendar', 'Календарь', 'clock'],
    ['tests', 'Заявки', 'target'],
    ['money', 'Заработок', 'coin'],
    ['profile', 'Профиль', 'trophy'],
]

const tab = ref('calendar')
const data = ref(null)
const loading = ref(true)
const error = ref('')
const busy = ref(false)

const showModal = ref(false)
const selectedTest = ref(null)

/* ---------- Форма нового слота ---------- */

const todayKey = () => {
    const now = new Date()
    const month = String(now.getMonth() + 1).padStart(2, '0')
    const day = String(now.getDate()).padStart(2, '0')

    return `${now.getFullYear()}-${month}-${day}`
}

/*
 * Часы для выбора: с 8 до 22 начала и с 9 до 23 конца. Шаг час — этого
 * хватает, а получасовой шаг дал бы три десятка кнопок в ряд.
 */
const HOURS = Array.from({ length: 15 }, (_, i) => `${String(8 + i).padStart(2, '0')}:00`)
const END_HOURS = Array.from({ length: 15 }, (_, i) => `${String(9 + i).padStart(2, '0')}:00`)

const pickedDay = ref(todayKey())
const fromHour = ref('18:00')
const toHour = ref('20:00')
const slotNote = ref('')

const canOpen = computed(() => fromHour.value < toHour.value)

const dayLabel = computed(() => {
    const day = week.value.find((item) => item.key === pickedDay.value)

    return day ? `${day.label}, ${day.day}` : 'выберите день'
})

function pickFrom(hour) {
    fromHour.value = hour

    // Конец не может быть раньше начала — подтягиваем его сами
    if (toHour.value <= hour) {
        const next = Math.min(23, Number(hour.slice(0, 2)) + 2)

        toHour.value = `${String(next).padStart(2, '0')}:00`
    }
}

const profile = computed(() => data.value?.profile ?? {})
const stats = computed(() => data.value?.stats ?? {})
const earnings = computed(() => data.value?.earnings ?? {})
const rates = computed(() => data.value?.rates ?? {})
const myTests = computed(() => data.value?.tests ?? [])
const slots = computed(() => data.value?.slots ?? [])
const payouts = computed(() => data.value?.payouts ?? [])
const history = computed(() => data.value?.history ?? [])

const blocked = computed(() => Boolean(profile.value.blocked))

const tierRows = computed(() => Object.entries(stats.value.tiers ?? {}))

/* ---------- Неделя ---------- */

const WEEKDAYS = ['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс']

function dateKey(value) {
    const date = new Date(value)
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${date.getFullYear()}-${month}-${day}`
}

const week = computed(() => {
    const start = new Date()
    start.setHours(0, 0, 0, 0)

    const days = []

    for (let i = 0; i < 7; i++) {
        const day = new Date(start)
        day.setDate(start.getDate() + i)

        const key = dateKey(day)

        days.push({
            key,
            label: WEEKDAYS[(day.getDay() + 6) % 7],
            day: day.getDate(),
            today: i === 0,
            slots: slots.value
                .filter((slot) => dateKey(slot.starts_at) === key)
                .sort((a, b) => a.starts_at.localeCompare(b.starts_at)),
        })
    }

    return days
})

function timeRange(slot) {
    const format = (value) =>
        new Date(value).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })

    return `${format(slot.starts_at)}–${format(slot.ends_at)}`
}

function slotPlayer(slot) {
    return slot.tier_test?.user?.username ?? null
}

/* ---------- Загрузка ---------- */

async function load() {
    loading.value = true
    error.value = ''

    try {
        data.value = await testerCabinetApi.dashboard()
    } catch (e) {
        error.value = e.message ?? 'Не удалось загрузить кабинет'
    } finally {
        loading.value = false
    }
}

async function act(call) {
    busy.value = true
    error.value = ''

    try {
        await call()
        await load()
    } catch (e) {
        error.value = e.message ?? 'Не получилось'
    } finally {
        busy.value = false
    }
}

/* ---------- Действия ---------- */

const openSlot = () => act(async () => {
    const starts = new Date(`${pickedDay.value}T${fromHour.value}:00`)
    const ends = new Date(`${pickedDay.value}T${toHour.value}:00`)

    await testerCabinetApi.openSlot({
        starts_at: starts.toISOString(),
        ends_at: ends.toISOString(),
        note: slotNote.value || null,
    })

    slotNote.value = ''
})

const closeSlot = (slot) => act(async () => {
    const ok = await confirmDialog(
        `Снять слот ${timeRange(slot)}? Время снова станет недоступно для записи.`,
        { title: 'Снять слот', confirmText: 'Снять' },
    )

    if (!ok) return

    await testerCabinetApi.closeSlot(slot.id)
})

const requestPayout = () => act(async () => {
    await testerCabinetApi.requestPayout()
})

const cancelTest = (test) => act(async () => {
    const result = await confirmWithReasonDialog(
        'Отменить тест? Укажите причину — её увидит администратор.',
        { title: 'Отменить тест', danger: true, confirmText: 'Отменить тест' },
    )

    if (!result) return

    await testerApi.cancel(test.id, result.reason || null)
})

const unclaimTest = (test) => act(async () => {
    await testerApi.unclaim(test.id)
})

async function completeTest(test) {
    busy.value = true

    try {
        selectedTest.value = await testerApi.show(test.id)
        showModal.value = true
    } catch (e) {
        error.value = e.message ?? 'Не удалось открыть заявку'
    } finally {
        busy.value = false
    }
}

function onModalUpdated() {
    showModal.value = false
    selectedTest.value = null
    load()
}

const statusName = (status) => ({
    pending: 'Ожидает',
    in_progress: 'В работе',
    completed: 'Завершён',
    cancelled: 'Отменён',
}[status] ?? status)

onMounted(load)

// Компонент мог быть кэширован — при возврате данные перечитываем
onActivated(load)
</script>

<template>
    <div class="tc">
        <header class="tc-head">
            <div>
                <RouterLink to="/tester" class="tc-back"><span aria-hidden="true">←</span> Очередь заявок</RouterLink>
                <span class="tc-kicker">Кабинет тестера</span>
                <h1>Моя смена</h1>
                <p class="tc-sub">
                    Открывайте время, берите заявки и следите за заработком.
                    Игроки записываются только на свободные слоты.
                </p>
            </div>

            <div class="tc-stats">
                <span class="tc-stat">
                    <b>{{ stats.completed ?? 0 }}</b>
                    <small>тестов проведено</small>
                </span>
                <span class="tc-stat">
                    <b>{{ stats.avg_tier ?? '—' }}</b>
                    <small>средний тир</small>
                </span>
                <span class="tc-stat">
                    <b>{{ earnings.month_rub ?? 0 }} ₽</b>
                    <small>за месяц</small>
                </span>
                <span class="tc-stat">
                    <b>{{ earnings.month_coins ?? 0 }}</b>
                    <small>ApexCoin за месяц</small>
                </span>
            </div>
        </header>

        <p v-if="error" class="tc-error">{{ error }}</p>

        <div v-if="blocked" class="tc-blocked">
            <AppIcon icon="alert" :size="16" />
            <span>
                Календарь закрыт администратором{{ profile.blocked_reason ? ' · ' + profile.blocked_reason : '' }}.
                Открывать новые слоты нельзя.
            </span>
        </div>

        <nav class="tc-tabs">
            <button
                v-for="[id, label, icon] in TABS"
                :key="id"
                type="button"
                class="tc-tab"
                :class="{ 'is-on': tab === id }"
                @click="tab = id"
            >
                <AppIcon :icon="icon" :size="15" />
                {{ label }}
                <b v-if="id === 'tests' && myTests.length">{{ myTests.length }}</b>
            </button>
        </nav>

        <PageLoader v-if="loading && !data" />

        <div v-else-if="!data" class="tc-empty">
            <p>Не удалось загрузить кабинет.</p>
            <button type="button" class="tc-btn tc-btn--main" @click="load">
                Повторить
            </button>
        </div>

        <template v-else>
            <!-- ================= Календарь ================= -->
            <section v-if="tab === 'calendar'" class="tc-panel">
                <div class="tc-week">
                    <div
                        v-for="day in week"
                        :key="day.key"
                        class="tc-day"
                        :class="{ 'is-today': day.today, 'is-picked': day.key === pickedDay }"
                        role="button"
                        tabindex="0"
                        @click="pickedDay = day.key"
                        @keydown.enter="pickedDay = day.key"
                    >
                        <header class="tc-day__head">
                            <span>{{ day.label }}</span>
                            <b>{{ day.day }}</b>
                        </header>

                        <div v-if="!day.slots.length" class="tc-day__empty">свободно</div>

                        <button
                            v-for="slot in day.slots"
                            :key="slot.id"
                            type="button"
                            class="tc-slot"
                            :class="`is-${slot.status}`"
                            :title="slot.status === 'booked' ? 'Занят заявкой' : 'Нажмите, чтобы снять'"
                            :disabled="busy || slot.status === 'booked'"
                            @click.stop="closeSlot(slot)"
                        >
                            <b>{{ timeRange(slot) }}</b>
                            <small v-if="slot.status === 'booked'">{{ slotPlayer(slot) ?? 'заявка' }}</small>
                            <small v-else-if="slot.note">{{ slot.note }}</small>
                            <small v-else>открыт</small>
                        </button>
                    </div>
                </div>

                <!-- Панель открытия слота: день выбран, осталось два часа -->
                <div class="tc-picker" :class="{ 'is-disabled': blocked }">
                    <div class="tc-picker__head">
                        <b>{{ dayLabel }}</b>
                        <span class="tc-picker__range">{{ fromHour }}–{{ toHour }}</span>
                    </div>

                    <div class="tc-picker__row">
                        <span class="tc-picker__label">С</span>
                        <button
                            v-for="hour in HOURS"
                            :key="`from-${hour}`"
                            type="button"
                            class="tc-hour"
                            :class="{ 'is-on': fromHour === hour }"
                            :disabled="busy || blocked"
                            @click="pickFrom(hour)"
                        >
                            {{ hour }}
                        </button>
                    </div>

                    <div class="tc-picker__row">
                        <span class="tc-picker__label">До</span>
                        <button
                            v-for="hour in END_HOURS"
                            :key="`to-${hour}`"
                            type="button"
                            class="tc-hour"
                            :class="{ 'is-on': toHour === hour }"
                            :disabled="busy || blocked || hour <= fromHour"
                            @click="toHour = hour"
                        >
                            {{ hour }}
                        </button>
                    </div>

                    <div class="tc-picker__foot">
                        <input
                            v-model="slotNote"
                            type="text"
                            maxlength="120"
                            placeholder="Заметка (необязательно)"
                        />

                        <button
                            type="button"
                            class="tc-btn tc-btn--main"
                            :disabled="busy || blocked || !canOpen"
                            @click="openSlot"
                        >
                            <AppIcon icon="clock" :size="15" />
                            Открыть {{ fromHour }}–{{ toHour }}
                        </button>
                    </div>

                    <p class="tc-hint">
                        Слот длится от 30 минут до 4 часов. Нажмите на день в календаре,
                        чтобы выбрать другую дату.
                    </p>
                </div>
            </section>

            <!-- ================= Заявки ================= -->
            <section v-else-if="tab === 'tests'" class="tc-panel">
                <div class="tc-panel__head">
                    <h2>Мои заявки</h2>
                    <RouterLink to="/tester" class="tc-btn">
                        Свободные заявки
                        <b class="tc-badge">{{ data.queue }}</b>
                    </RouterLink>
                </div>

                <p v-if="!myTests.length" class="tc-hint">
                    Пока нет заявок в работе. Возьмите одну из общей очереди.
                </p>

                <article v-for="test in myTests" :key="test.id" class="tc-test">
                    <div class="tc-test__main">
                        <strong>{{ test.player }}</strong>
                        <small>
                            {{ test.mode === 'bedwars' ? 'BedWars' : 'PvP' }}
                            · {{ statusName(test.status) }}
                            <template v-if="test.scheduled_at">
                                · {{ new Date(test.scheduled_at).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) }}
                            </template>
                        </small>
                    </div>

                    <span v-if="test.is_priority" class="tc-tag">приоритет</span>

                    <div class="tc-test__actions">
                        <button type="button" class="tc-btn tc-btn--main" :disabled="busy" @click="completeTest(test)">
                            Завершить
                        </button>
                        <button type="button" class="tc-btn" :disabled="busy" @click="cancelTest(test)">
                            Отменить
                        </button>
                        <button
                            v-if="test.status === 'in_progress'"
                            type="button"
                            class="tc-btn tc-btn--ghost"
                            :disabled="busy"
                            @click="unclaimTest(test)"
                        >
                            Отпустить
                        </button>
                    </div>
                </article>
            </section>

            <!-- ================= Заработок ================= -->
            <section v-else-if="tab === 'money'" class="tc-panel">
                <div class="tc-money">
                    <div class="tc-money__card">
                        <span>Заработано за месяц</span>
                        <b>{{ earnings.month_rub }} ₽</b>
                        <small>{{ earnings.month_coins }} ApexCoin</small>
                    </div>
                    <div class="tc-money__card">
                        <span>Выплачено</span>
                        <b>{{ earnings.paid_rub }} ₽</b>
                        <small>{{ earnings.paid_coins }} ApexCoin</small>
                    </div>
                    <div class="tc-money__card">
                        <span>Ждёт выплаты</span>
                        <b>{{ earnings.pending_rub }} ₽</b>
                        <small>{{ earnings.pending_coins }} ApexCoin</small>
                    </div>
                    <div class="tc-money__card is-accent">
                        <span>Можно запросить</span>
                        <b>{{ earnings.available_rub }} ₽</b>
                        <small>{{ earnings.available_coins }} ApexCoin</small>
                        <button
                            type="button"
                            class="tc-btn tc-btn--main"
                            :disabled="busy || (!earnings.available_rub && !earnings.available_coins)"
                            @click="requestPayout"
                        >
                            Запросить выплату
                        </button>
                    </div>
                </div>

                <p class="tc-hint">
                    Ставка: {{ rates.pay_rub }} ₽ и {{ rates.pay_coins }} ApexCoin за тест.
                    За S+ платят больше, за E — меньше.
                </p>

                <h2 class="tc-h2">История тестов</h2>

                <table class="tc-table">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Игрок</th>
                            <th>Тир</th>
                            <th>Рубли</th>
                            <th>ApexCoin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in history" :key="row.id">
                            <td>{{ row.completed_at ? new Date(row.completed_at).toLocaleDateString('ru-RU') : '—' }}</td>
                            <td>{{ row.player }}</td>
                            <td><b class="tc-tier">{{ row.tier ?? '—' }}</b></td>
                            <td>{{ row.rub }} ₽</td>
                            <td>{{ row.coins }}</td>
                        </tr>
                        <tr v-if="!history.length">
                            <td colspan="5" class="tc-table__empty">Пока нет проведённых тестов</td>
                        </tr>
                    </tbody>
                </table>

                <template v-if="payouts.length">
                    <h2 class="tc-h2">Запросы на выплату</h2>

                    <ul class="tc-payouts">
                        <li v-for="payout in payouts" :key="payout.id" :class="`is-${payout.status}`">
                            <b>{{ payout.rub }} ₽</b>
                            <span>{{ payout.coins }} ApexCoin</span>
                            <small>{{ new Date(payout.created_at).toLocaleDateString('ru-RU') }}</small>
                            <em>{{ { pending: 'ждёт решения', paid: 'выплачено', rejected: 'отклонено' }[payout.status] }}</em>
                        </li>
                    </ul>
                </template>
            </section>

            <!-- ================= Профиль ================= -->
            <section v-else class="tc-panel">
                <h2>Эффективность</h2>

                <div class="tc-money">
                    <div class="tc-money__card">
                        <span>Проведено тестов</span>
                        <b>{{ stats.completed }}</b>
                        <small>за всё время</small>
                    </div>
                    <div class="tc-money__card">
                        <span>Средний тир</span>
                        <b>{{ stats.avg_tier ?? '—' }}</b>
                        <small>{{ stats.avg_score }}% в среднем</small>
                    </div>
                    <div class="tc-money__card">
                        <span>Отменено</span>
                        <b>{{ stats.cancelled }}</b>
                        <small>заявок</small>
                    </div>
                    <div class="tc-money__card" :class="{ 'is-bad': blocked }">
                        <span>Календарь</span>
                        <b>{{ blocked ? 'закрыт' : 'открыт' }}</b>
                        <small>{{ blocked ? profile.blocked_reason ?? 'по решению админа' : 'можно открывать слоты' }}</small>
                    </div>
                </div>

                <h2 class="tc-h2">Какие тиры ставлю</h2>

                <div v-if="tierRows.length" class="tc-tiers">
                    <div v-for="[tier, count] in tierRows" :key="tier" class="tc-tier-row">
                        <b>{{ tier }}</b>
                        <span class="tc-tier-row__bar">
                            <i :style="{ width: `${Math.max(6, (count / stats.completed) * 100)}%` }"></i>
                        </span>
                        <small>{{ count }}</small>
                    </div>
                </div>
                <p v-else class="tc-hint">Пока нет завершённых тестов — статистика появится после первого.</p>
            </section>
        </template>

        <TesterTierTestModal
            v-if="showModal && selectedTest"
            :tier-test="selectedTest"
            @close="showModal = false"
            @updated="onModalUpdated"
        />
    </div>
</template>

<style scoped>
@import "./TesterDashboardView.css";
</style>
