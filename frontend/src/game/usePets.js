import { computed, ref } from 'vue'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import { MAX_PET_LEVEL, levelXp } from '@/data/players/petArena.js'

/**
 * Данные и действия питомцев для игровых сцен: одна загрузка GET /pets
 * и общие операции (уход, трюки, походы). Сцены только рисуют.
 */
export const CARE = [
  { id: 'pet', label: 'Погладить', xp: 1, reaction: 'hearts' },
  { id: 'feed', label: 'Покормить', xp: 2, reaction: 'dance' },
  { id: 'play', label: 'Поиграть', xp: 2, reaction: 'flip' },
]

export function usePets() {
  const auth = useAuthStore()
  const hub = ref(null)
  const error = ref('')
  const notice = ref('')
  const busy = ref(null)
  const shortage = ref(0)
  /** Какой трюк сейчас проигрывается у какого питомца. */
  const playing = ref({})

  const pets = computed(() => hub.value?.pets ?? [])
  const freePets = computed(() => pets.value.filter((p) => !p.busy))
  const trips = computed(() => pets.value.filter((p) => p.expedition).map((p) => ({ ...p.expedition, pet: p })))

  function say(text) {
    notice.value = text
    error.value = ''
    shortage.value = 0
  }

  function fail(e, fallback) {
    // Не хватает монет — подсказываем, где заработать
    if (e.data?.missing) {
      shortage.value = e.data.missing
      error.value = ''
    } else {
      shortage.value = 0
      error.value = e.message || fallback
    }
    notice.value = ''
  }

  async function load() {
    try {
      hub.value = await api.get('/pets')
    } catch (e) {
      fail(e, 'Не удалось загрузить питомцев.')
    }
  }

  /** Выполнить действие с общим busy и единым разбором ошибок. */
  async function run(key, fn, fallback = 'Не получилось.') {
    busy.value = key
    try {
      return await fn()
    } catch (e) {
      fail(e, fallback)
      return undefined
    } finally {
      busy.value = null
    }
  }

  function showTrick(p, trick = p.trick) {
    if (!trick) return
    playing.value = { ...playing.value, [p.inventory_id]: null }
    requestAnimationFrame(() => {
      playing.value = { ...playing.value, [p.inventory_id]: trick }
      setTimeout(() => { playing.value = { ...playing.value, [p.inventory_id]: null } }, 1700)
    })
  }

  /** Погладить, покормить или поиграть — раз в сутки каждое. */
  function care(p, c) {
    return run(`care-${p.inventory_id}-${c.id}`, async () => {
      const result = await api.post(`/players/${auth.user.id}/pet/${c.id}`, { pet: p.inventory_id })
      p.care = [...(p.care ?? []), c.id]
      if (c.id === 'feed') p.hungry = false
      p.xp = result.xp ?? p.xp
      if (result.level && result.level > p.level) {
        say(`${p.name} вырос до ${result.level} уровня!`)
        p.level = result.level
      }
      showTrick(p, c.reaction)
    })
  }

  function setTrick(p, trick) {
    return run(`trick-${p.inventory_id}`, async () => {
      await api.post(`/pets/${p.inventory_id}/trick`, { trick })
      p.trick = trick
      if (trick) showTrick(p, trick)
      say(trick ? 'Трюк выбран — питомец покажет его гостям профиля.' : 'Трюк убран.')
    })
  }

  /** Доля опыта до следующего уровня, 0–100. */
  function xpPct(p) {
    if (p.level >= MAX_PET_LEVEL) return 100
    const from = levelXp(p.level)
    const to = levelXp(p.level + 1)
    return Math.max(0, Math.min(100, Math.round(((p.xp - from) / (to - from)) * 100)))
  }

  return { auth, hub, pets, freePets, trips, error, notice, busy, shortage, playing, say, fail, load, run, showTrick, care, setTrick, xpPct }
}
