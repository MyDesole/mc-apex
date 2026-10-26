<script setup>
/**
 * Роадмап: цели проекта и прогресс. Часть прогресса считается сама
 * (активные игроки, сыгранные КВ, турниры), остальное ведёт админ.
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { projectApi } from '@/services/project/project.js'

const items = ref([])
const loading = ref(true)
const error = ref('')

const COLUMNS = [
  { key: 'in_progress', label: 'В работе' },
  { key: 'planned', label: 'В планах' },
  { key: 'done', label: 'Готово' },
]

const grouped = computed(() =>
    COLUMNS
        .map((col) => ({ ...col, items: items.value.filter((i) => i.status === col.key) }))
        .filter((col) => col.items.length),
)

const doneCount = computed(() => items.value.filter((i) => i.status === 'done').length)

onMounted(async () => {
  try {
    items.value = (await projectApi.roadmap()).items ?? []
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить роадмап.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="roadmap">
    <header class="roadmap__head">
      <h1>Роадмап</h1>
      <p>
        Куда движется APEX. Выполнено целей: <b>{{ doneCount }} из {{ items.length }}</b>.
        Что уже сделано — в <RouterLink to="/changelog">чейнджлоге</RouterLink>.
      </p>
    </header>

    <div v-if="loading" class="roadmap__muted">Загружаем…</div>
    <div v-else-if="error" class="alert alert--error" role="alert">{{ error }}</div>

    <section v-for="col in grouped" :key="col.key" class="roadmap__col">
      <h2 class="roadmap__col-title" :class="`is-${col.key}`">
        <span class="roadmap__dot" />{{ col.label }}
      </h2>

      <div class="roadmap__grid">
        <article
            v-for="item in col.items"
            :key="item.id"
            class="goal"
            :class="`goal--${item.status}`"
        >
          <header class="goal__head">
            <h3>{{ item.title }}</h3>
            <span v-if="item.eta" class="goal__eta">{{ item.eta }}</span>
          </header>

          <p v-if="item.description" class="goal__desc">{{ item.description }}</p>

          <div v-if="item.progress_percent !== null" class="goal__progress">
            <div class="goal__bar">
              <span :style="{ width: `${item.progress_percent}%` }" />
            </div>
            <div class="goal__nums">
              <span v-if="item.progress_target">
                <b>{{ item.progress_current ?? 0 }}</b> / {{ item.progress_target }}
              </span>
              <span>{{ item.progress_percent }}%</span>
            </div>
            <small v-if="item.metric_label" class="goal__metric">Считается автоматически: {{ item.metric_label.toLowerCase() }}</small>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<style scoped>
.roadmap {
  max-width: 1000px;
  margin: 0 auto;
  padding: 28px 16px 56px;
}

.roadmap__head h1 {
  margin: 0 0 8px;
  font-family: var(--font-display);
  font-size: 30px;
}

.roadmap__head p,
.roadmap__muted {
  margin: 0 0 22px;
  color: var(--text-dim);
}

.roadmap__head a {
  color: var(--accent-light);
}

.roadmap__col {
  margin-bottom: 26px;
}

.roadmap__col-title {
  --c: #a78bfa;

  display: flex;
  align-items: center;
  gap: 8px;

  margin: 0 0 12px;

  font-size: 14px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.roadmap__col-title.is-in_progress { --c: #facc15; }
.roadmap__col-title.is-done { --c: #22c55e; }

.roadmap__dot {
  width: 9px;
  height: 9px;

  background: var(--c);
  box-shadow: 0 0 10px var(--c);
}

.roadmap__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 12px;
}

.goal {
  --c: #a78bfa;

  display: flex;
  flex-direction: column;
  gap: 10px;

  padding: 18px;

  background:
      linear-gradient(160deg, color-mix(in srgb, var(--c) 8%, transparent), transparent 60%),
      var(--bg-card);
  border: 1px solid var(--border);
  border-top: 3px solid var(--c);
  border-radius: 8px;
}

.goal--in_progress { --c: #facc15; }
.goal--done { --c: #22c55e; }

.goal__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.goal__head h3 {
  margin: 0;
  font-size: 16px;
  line-height: 1.3;
}

.goal--done .goal__head h3::before {
  content: '✓ ';
  color: var(--c);
}

.goal__eta {
  flex-shrink: 0;

  padding: 2px 8px;

  color: var(--text-dim);
  border: 1px solid var(--border);
  border-radius: 4px;

  font-size: 11.5px;
  white-space: nowrap;
}

.goal__desc {
  margin: 0;
  color: var(--text-dim);
  font-size: 13.5px;
  line-height: 1.55;
}

.goal__progress {
  display: grid;
  gap: 6px;

  margin-top: auto;
}

.goal__bar {
  height: 10px;

  overflow: hidden;

  background: rgba(255, 255, 255, 0.06);
  box-shadow: inset 0 -2px 0 rgba(0, 0, 0, 0.35);
}

.goal__bar span {
  display: block;
  height: 100%;

  background: var(--c);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.35), inset 0 -2px 0 rgba(0, 0, 0, 0.25);

  transition: width 0.6s ease;
}

.goal__nums {
  display: flex;
  justify-content: space-between;

  color: var(--text-dim);
  font-size: 12.5px;
}

.goal__nums b {
  color: var(--text);
}

.goal__metric {
  color: var(--text-muted);
  font-size: 11.5px;
}
</style>
