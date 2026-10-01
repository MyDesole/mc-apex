import { reactive } from 'vue'

/**
 * Модальные диалоги приложения: замена встроенным alert/confirm/prompt.
 *
 * Встроенные диалоги блокируют поток, выглядят чужеродно и не поддаются
 * оформлению. Здесь тот же сценарий, но через собственный компонент.
 *
 * Все функции возвращают Promise, поэтому вызовы читаются как прежде:
 *
 *   if (!(await confirmDialog('Удалить?'))) return
 *   await alertDialog('Готово')
 *   const name = await promptDialog('Название?')
 *
 * Диалоги встают в очередь: если во время показа прилетит второй,
 * он не перебьёт первый, а покажется после него.
 */

export const dialogState = reactive({
    current: null,
    queue: [],
})

let nextId = 1

/**
 * Добавить диалог в очередь.
 *
 * @returns {Promise<any>} ответ пользователя
 */
function enqueue(options) {
    return new Promise((resolve) => {
        const entry = {
            id: nextId++,
            kind: options.kind ?? 'confirm',
            title: options.title ?? null,
            message: options.message ?? '',
            // Подпись кнопки подтверждения и её стиль
            confirmText: options.confirmText ?? 'OK',
            cancelText: options.cancelText ?? 'Отмена',
            danger: options.danger ?? false,
            // prompt
            placeholder: options.placeholder ?? '',
            initialValue: options.initialValue ?? '',
            maxlength: options.maxlength ?? null,
            // Необязательная причина/комментарий в confirm
            withReason: options.withReason ?? false,
            reasonLabel: options.reasonLabel ?? 'Комментарий (необязательно)',
            reasonPlaceholder: options.reasonPlaceholder ?? '',
            // Только кнопка подтверждения (alert)
            showCancel: options.kind !== 'alert',
            resolve,
        }

        if (dialogState.current) {
            dialogState.queue.push(entry)
        } else {
            dialogState.current = entry
        }
    })
}

/**
 * Закрыть текущий диалог и показать следующий.
 */
export function settleDialog(value) {
    const entry = dialogState.current

    if (!entry) return

    entry.resolve(value)

    dialogState.current = dialogState.queue.shift() ?? null
}

/**
 * Сообщение с одной кнопкой.
 */
export function alert(message, options = {}) {
    return enqueue({
        kind: 'alert',
        message,
        confirmText: options.confirmText ?? 'Понятно',
        title: options.title ?? null,
        ...options,
    })
}

/**
 * Подтверждение действия.
 *
 * @returns {Promise<boolean>} true, если пользователь подтвердил
 */
export async function confirm(message, options = {}) {
    const result = await enqueue({
        kind: 'confirm',
        message,
        danger: options.danger ?? false,
        ...options,
    })

    return result === true
}

/**
 * Подтверждение с необязательным комментарием.
 *
 * @returns {Promise<{confirmed: boolean, reason: string}|null>}
 */
export async function confirmWithReason(message, options = {}) {
    const result = await enqueue({
        kind: 'confirm',
        message,
        withReason: true,
        danger: options.danger ?? false,
        ...options,
    })

    if (!result) return null

    return { confirmed: true, reason: result.reason ?? '' }
}

/**
 * Ввод строки.
 *
 * @returns {Promise<string|null>} null, если отменили
 */
export async function prompt(message, options = {}) {
    const result = await enqueue({
        kind: 'prompt',
        message,
        confirmText: options.confirmText ?? 'Сохранить',
        ...options,
    })

    return result === null || result === undefined ? null : String(result)
}

export default { alert, confirm, confirmWithReason, prompt, settleDialog, dialogState }
