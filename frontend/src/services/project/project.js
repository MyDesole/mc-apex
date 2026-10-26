import { api } from '@/services/core/api.js'

/** Чейнджлог и роадмап: публичные страницы. */
export const projectApi = {
    changelog(page = 1) {
        return api.get(`/changelog?page=${page}`)
    },

    entry(slug) {
        return api.get(`/changelog/${encodeURIComponent(slug)}`)
    },

    roadmap() {
        return api.get('/roadmap')
    },
}

/** Админка: записи чейнджлога и цели роадмапа. */
export const adminProjectApi = {
    changelog() {
        return api.get('/admin/project/changelog')
    },

    createEntry(payload) {
        return api.post('/admin/project/changelog', payload)
    },

    updateEntry(id, payload) {
        return api.put(`/admin/project/changelog/${id}`, payload)
    },

    deleteEntry(id) {
        return api.delete(`/admin/project/changelog/${id}`)
    },

    roadmap() {
        return api.get('/admin/project/roadmap')
    },

    createItem(payload) {
        return api.post('/admin/project/roadmap', payload)
    },

    updateItem(id, payload) {
        return api.put(`/admin/project/roadmap/${id}`, payload)
    },

    deleteItem(id) {
        return api.delete(`/admin/project/roadmap/${id}`)
    },
}

/**
 * Текст записи → блоки для показа без v-html: абзацы и списки.
 * Строки, начинающиеся с «- » или «• », собираются в список,
 * пустая строка разделяет абзацы.
 */
export function bodyBlocks(text) {
    const blocks = []
    let list = null
    let paragraph = []

    const flushParagraph = () => {
        if (paragraph.length) blocks.push({ type: 'p', text: paragraph.join(' ') })
        paragraph = []
    }

    for (const raw of String(text ?? '').split('\n')) {
        const line = raw.trim()
        const item = line.match(/^[-•*]\s+(.*)$/)

        if (item) {
            flushParagraph()
            if (!list) {
                list = { type: 'ul', items: [] }
                blocks.push(list)
            }
            list.items.push(item[1])
            continue
        }

        list = null

        if (!line) {
            flushParagraph()
        } else {
            paragraph.push(line)
        }
    }

    flushParagraph()

    return blocks
}

export const CATEGORY_COLORS = {
    feature: '#22c55e',
    improvement: '#a78bfa',
    fix: '#f59e0b',
}

export function formatPostDate(iso) {
    return iso
        ? new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' })
        : ''
}
