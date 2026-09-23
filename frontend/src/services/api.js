const API_URL = '/api'

async function request(url, options = {}) {
    const response = await fetch(`${API_URL}${url}`, {
        credentials: 'include',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            ...(options.headers || {}),
        },
        ...options,
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
        },
    })
}

export const api = {
    get(url) {
        return request(url)
    },

    post(url, body = {}) {
        return request(url, {
            method: 'POST',
            body: JSON.stringify(body),
        })
    },

    put(url, body = {}) {
        return request(url, {
            method: 'PUT',
            body: JSON.stringify(body),
        })
    },

    delete(url) {
        return request(url, {
            method: 'DELETE',
        })
    },
}
