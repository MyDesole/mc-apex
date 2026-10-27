/**
 * Примерка предметов магазина в студии профиля.
 *
 * Меняет поле в загруженном профиле (стор auth) — холст перерисовывается
 * сам, на сервер ничего не уходит. Исходное значение каждого поля
 * запоминаем один раз; студия при закрытии всё возвращает (restoreAll).
 */
import { ref } from 'vue'
import { useAuthStore } from '@/stores/core/auth.js'

const originals = new Map()
const trying = ref(null)

export function useTryOn() {
  const auth = useAuthStore()

  function tryOn(item, field, value) {
    if (!field || !auth.user) return
    if (!originals.has(field)) originals.set(field, auth.user[field] ?? null)
    auth.user[field] = value
    trying.value = item
  }

  function restoreAll() {
    if (auth.user) {
      for (const [field, value] of originals) auth.user[field] = value
    }
    originals.clear()
    trying.value = null
  }

  // Поле надето по-настоящему (купили) — откатывать его не нужно
  function forget(field) {
    originals.delete(field)
  }

  return { trying, tryOn, restoreAll, forget }
}
