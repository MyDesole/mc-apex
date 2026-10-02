/**
 * Тестовый роутер.
 *
 * Маршруты берутся из настоящего router/index.js, а не дублируются:
 * иначе тесты начнут расходиться с приложением и пропускать ошибки
 * вида «нет маршрута с именем forum».
 */
import { createRouter, createMemoryHistory } from 'vue-router'
import { readFileSync } from 'node:fs'
import { join } from 'node:path'

/**
 * Разбирает пути и имена из определения роутера.
 *
 * Компоненты подменяются заглушкой: в тестах они не нужны, а лишние
 * импорты замедляют прогон и тянут зависимости.
 */
export function buildTestRouter() {
  const source = readFileSync(join(process.cwd(), 'src', 'router', 'index.js'), 'utf8')

  const routes = []

  // Записи вида { path: '/x', name: 'y', component: ... }
  for (const m of source.matchAll(
    /path:\s*'([^']+)'[\s\S]{0,200}?name:\s*'([^']+)'/g
  )) {
    routes.push({
      path: m[1],
      name: m[2],
      component: { template: '<div />' },
    })
  }

  // Страховка: если разбор не сработал, добавляем общий маршрут,
  // чтобы тесты падали по существу, а не на отсутствии роутера
  if (!routes.length) {
    routes.push({ path: '/:pathMatch(.*)*', name: 'catch-all', component: { template: '<div />' } })
  } else {
    routes.push({ path: '/:pathMatch(.*)*', name: 'catch-all', component: { template: '<div />' } })
  }

  return createRouter({ history: createMemoryHistory(), routes })
}

export default buildTestRouter
