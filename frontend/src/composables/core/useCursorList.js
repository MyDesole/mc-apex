import { onUnmounted, ref, watch } from 'vue'

/**
 * Список с догрузкой по курсору: первая страница, затем следующие, когда
 * «маячок» внизу списка доходит до экрана.
 *
 * fetchPage({ cursor }) должен вернуть { items, next_cursor, ... }.
 * onFirstPage(data) получает весь ответ первой страницы — там шапка
 * (счётчики вкладок, баланс и т.п.).
 *
 * Ответ на устаревший запрос (сменили вкладку, пока грузилось)
 * отбрасывается по номеру запроса.
 */
export function useCursorList(fetchPage, { onFirstPage = null } = {}) {
    const items = ref([])
    const cursor = ref(null)
    const done = ref(false)
    const loading = ref(false)
    const loadingMore = ref(false)
    const error = ref('')
    const sentinel = ref(null)

    let request = 0
    let observer = null

    async function reload() {
        const id = ++request
        loading.value = true
        error.value = ''
        done.value = false
        cursor.value = null

        try {
            const data = await fetchPage({ cursor: null })
            if (id !== request) return

            items.value = data.items ?? []
            cursor.value = data.next_cursor ?? null
            done.value = !cursor.value
            onFirstPage?.(data)
        } catch (e) {
            if (id === request) error.value = e.message || 'Не удалось загрузить.'
        } finally {
            if (id === request) loading.value = false
        }
    }

    async function loadMore() {
        if (done.value || loading.value || loadingMore.value || !cursor.value) return

        const id = request
        loadingMore.value = true

        try {
            const data = await fetchPage({ cursor: cursor.value })
            if (id !== request) return

            items.value = [...items.value, ...(data.items ?? [])]
            cursor.value = data.next_cursor ?? null
            done.value = !cursor.value
        } catch (e) {
            if (id === request) error.value = e.message || 'Не удалось загрузить.'
        } finally {
            if (id === request) loadingMore.value = false
        }
    }

    // Маячок у низа списка: подгружаем заранее, за 600px до конца
    watch(sentinel, (el, old) => {
        if (old && observer) observer.unobserve(old)
        if (!el || typeof IntersectionObserver === 'undefined') return

        observer ??= new IntersectionObserver(
            (entries) => entries.some((e) => e.isIntersecting) && loadMore(),
            { rootMargin: '600px 0px' },
        )
        observer.observe(el)
    })

    // Запасной путь: где IntersectionObserver молчит — смотрим при прокрутке
    function onScroll() {
        const el = sentinel.value
        if (el && el.getBoundingClientRect().top < window.innerHeight + 600) loadMore()
    }

    if (typeof window !== 'undefined') window.addEventListener('scroll', onScroll, { passive: true })

    onUnmounted(() => {
        observer?.disconnect()
        if (typeof window !== 'undefined') window.removeEventListener('scroll', onScroll)
    })

    return { items, done, loading, loadingMore, error, sentinel, reload, loadMore }
}
