import { reactive } from 'vue'

/**
 * Окно жалобы: одно на весь сайт (ReportDialog в AppLayout).
 * Любая страница открывает его через openReport().
 *
 * type — user | message | forum_topic | forum_reply, id — что обжалуют,
 * name — подпись в окне («сообщение от Steve»).
 */
export const reportState = reactive({
    open: false,
    type: null,
    id: null,
    name: '',
})

export function openReport({ type, id, name = '' }) {
    Object.assign(reportState, { open: true, type, id, name })
}

export function closeReport() {
    reportState.open = false
}
