const API_URL = '/api'

function getCookie(name) {
    const match = document.cookie.match(
        new RegExp('(^|;\\s*)' + name + '=([^;]*)')
    )
    return match ? decodeURIComponent(match[2]) : null
}

async function request(url, options = {}) {
    const method = (options.method || 'GET').toUpperCase()
    const isFormData = options.body instanceof FormData

    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers || {}),
    }

    // Content-Type только для НЕ-FormData
    if (options.body && !isFormData) {
        headers['Content-Type'] = 'application/json'
    }

    // CSRF для небезопасных методов
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        const token = getCookie('XSRF-TOKEN')
        if (token) headers['X-XSRF-TOKEN'] = token
    }

    const response = await fetch(`${API_URL}${url}`, {
        credentials: 'include',
        ...options,
        headers,
    })

    const data = await response.json().catch(() => ({}))

    if (!response.ok) {
        throw {
            status: response.status,
            message: data.message || 'Произошла ошибка.',
            errors: data.errors || {},
        }
    }

    return data
}

export async function getCsrfCookie() {
    await fetch('/sanctum/csrf-cookie', {
        credentials: 'include',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
}

export const api = {
    get(url) {
        return request(url)
    },

    post(url, body = {}, options = {}) {
        const isFormData = body instanceof FormData

        return request(url, {
            method: 'POST',
            body: isFormData ? body : JSON.stringify(body),
            ...options,
        })
    },

    put(url, body = {}, options = {}) {
        const isFormData = body instanceof FormData

        return request(url, {
            method: 'PUT',
            body: isFormData ? body : JSON.stringify(body),
            ...options,
        })
    },

    delete(url, options = {}) {
        return request(url, {
            method: 'DELETE',
            ...options,
        })
    },
}