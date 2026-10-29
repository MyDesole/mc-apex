import { onScopeDispose, ref } from 'vue'

/**
 * Общие «часы» для таймеров обратного отсчёта.
 *
 * Раньше каждая страница (аукцион, питомцы, шахта, кейсы, панель главной)
 * заводила свой setInterval на секунду — и тикала даже в фоновой вкладке.
 * Теперь на всё приложение один интервал, и он стоит, пока вкладка скрыта:
 * при возвращении время сразу подтягивается.
 */
const now = ref(Date.now())
let users = 0
let timer = null

function hidden() {
  return typeof document !== 'undefined' && document.hidden
}

function start() {
  if (timer || hidden()) return
  now.value = Date.now()
  timer = setInterval(() => { now.value = Date.now() }, 1000)
}

function stop() {
  clearInterval(timer)
  timer = null
}

function onVisibility() {
  if (hidden()) stop()
  else if (users > 0) start()
}

/** Ref с текущим временем (мс), обновляется раз в секунду, пока компонент жив. */
export function useNow() {
  if (users++ === 0) {
    if (typeof document !== 'undefined') document.addEventListener('visibilitychange', onVisibility)
    start()
  }

  onScopeDispose(() => {
    if (--users === 0) {
      stop()
      if (typeof document !== 'undefined') document.removeEventListener('visibilitychange', onVisibility)
    }
  })

  return now
}

/** Вкладка скрыта — опросы сервера можно пропустить. */
export function pageHidden() {
  return hidden()
}
