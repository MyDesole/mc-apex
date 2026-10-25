<script setup>
/**
 * Правила тир-тестов: что оценивается и как считается тир.
 *
 * Пороги совпадают с TierTestService::TIER_THRESHOLDS на бэкенде,
 * подписи аспектов — с data/players/aspects.js.
 */
import { RouterLink } from 'vue-router'
import { ASPECTS, ASPECT_MAX } from '@/data/players/aspects.js'
import { tierColors } from '@/data/players/profileCustomization.js'

const ASPECT_HINTS = {
  block_placing: 'Скорость и точность постройки под давлением: блок-клатчи, стены, отступ.',
  rotka: 'Работа удочкой: сбивка спринта, комбо и контроль дистанции.',
  movement: 'Стрейфы, уклонение, удержание выгодной позиции.',
  aim: 'Точность ударов и трекинг соперника.',
  game_sense: 'Чтение боя: когда давить, когда отходить и чем пользоваться.',
  pvp: 'Ближний бой в условиях BedWars.',
  bed_play: 'Защита своей кровати и снос чужих.',
  teamplay: 'Координация с командой, роли, коммуникация.',
  building: 'Мосты, укрепления, скорость передвижения по карте.',
}

const TIERS = [
  { tier: 'S', range: 'только за турниры', text: 'Выдаётся за победы в турнирах APEX, а не за тест.' },
  { tier: 'A', range: '71–100', text: 'Элита: стабильно сильна во всех аспектах.' },
  { tier: 'B', range: '56–70', text: 'Сильный игрок с заметными сильными сторонами.' },
  { tier: 'C', range: '41–55', text: 'Уверенная база, есть над чем работать.' },
  { tier: 'D', range: '21–40', text: 'Основы освоены, опыт набирается.' },
  { tier: 'E', range: '0–20', text: 'Начало пути.' },
]
</script>

<template>
  <div class="content-page">
    <header class="content-page__head">
      <h1>Правила тир-тестов</h1>
      <p>Как проходит тест, что оценивает тестер и как из оценок получается тир.</p>
    </header>

    <section class="content-card">
      <h2>Как проходит тест</h2>
      <ol class="steps">
        <li><strong>Заявка.</strong> В <RouterLink to="/profile">профиле</RouterLink> выбери режим (PvP или BedWars), ожидаемый тир и удобное время.</li>
        <li><strong>Связь.</strong> Свободный тестер берёт заявку и пишет тебе по указанному контакту. Заявка с приоритетом из магазина берётся первой.</li>
        <li><strong>Бой.</strong> Тестер проводит серию боёв на сервере и наблюдает за игрой.</li>
        <li><strong>Оценка.</strong> Каждый из 5 аспектов оценивается от 0 до {{ ASPECT_MAX }}. Сумма — это процент тира.</li>
        <li><strong>Результат.</strong> Тир сразу появляется в профиле, в рейтинге и в ленте «Сейчас на APEX».</li>
      </ol>
    </section>

    <section class="content-card">
      <h2>Что оценивается</h2>

      <div v-for="(list, mode) in ASPECTS" :key="mode" class="aspects">
        <h3>{{ mode === 'pvp' ? 'PvP' : 'BedWars' }}</h3>
        <dl>
          <div v-for="aspect in list" :key="aspect.key">
            <dt>{{ aspect.label }} <span>0–{{ ASPECT_MAX }}</span></dt>
            <dd>{{ ASPECT_HINTS[aspect.key] }}</dd>
          </div>
        </dl>
      </div>
    </section>

    <section class="content-card">
      <h2>Тиры</h2>
      <p>Тир определяется суммой пяти аспектов. В рейтинг идёт лучший результат из режимов.</p>

      <ul class="tiers">
        <li v-for="row in TIERS" :key="row.tier">
          <span class="tier-block" :style="{ '--tier': tierColors[row.tier] }">{{ row.tier }}</span>
          <div>
            <strong>{{ row.range }}</strong>
            <span>{{ row.text }}</span>
          </div>
        </li>
      </ul>
    </section>

    <section class="content-card">
      <h2>Честная игра</h2>
      <p>Тест проходит сам владелец аккаунта, со своего ника. Чужой аккаунт, читы и макросы — аннулирование результата и бан.</p>
      <p>Можно пройти тест повторно, когда станешь сильнее: новый результат заменит старый в этом режиме.</p>
      <p>Не согласен с оценкой? Напиши тестеру или модератору — команду можно найти на странице <RouterLink to="/about">«О проекте»</RouterLink>.</p>
    </section>
  </div>
</template>

<style scoped>
.steps {
  display: flex;
  flex-direction: column;
  gap: 10px;

  margin: 0;
  padding-left: 22px;
}

.aspects + .aspects {
  margin-top: 18px;
}

.aspects h3 {
  margin: 0 0 8px;

  color: var(--text);

  font-size: 15px;
}

.aspects dl {
  display: grid;
  gap: 8px;

  margin: 0;
}

.aspects dl > div {
  padding: 10px 12px;

  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);
}

.aspects dt {
  display: flex;
  justify-content: space-between;

  color: var(--text);

  font-weight: 600;
}

.aspects dt span {
  color: var(--text-muted);

  font-size: 13px;
  font-weight: 400;
}

.aspects dd {
  margin: 2px 0 0;

  color: var(--text-dim);

  font-size: 14px;
}

.tiers {
  display: grid;
  gap: 8px;

  margin: 4px 0 0;
  padding: 0;

  list-style: none;
}

.tiers li {
  display: flex;
  align-items: center;
  gap: 14px;
}

.tiers .tier-block {
  width: 40px;
  height: 40px;
  font-size: 16px;
}

.tiers div {
  display: flex;
  flex-direction: column;
}

.tiers strong {
  font-variant-numeric: tabular-nums;
}

.tiers span:not(.tier-block) {
  color: var(--text-dim);
  font-size: 14px;
}
</style>
