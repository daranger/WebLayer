export const Api = {
    async request(url, options = {}) {
        const headers = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers
        };

        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options, // 🔥 Сюда автоматически упадет signal: signal
            headers
        });

// ... твой код в api.js
        const contentType = response.headers.get('content-type');
        const isJson = contentType && contentType.includes('application/json');

        // Переменная data теперь парсит JSON
        const data = isJson ? await response.json() : {};

        if (!response.ok) {
            // 🔥 ХАК: ищем сначала .error, затем .message, если ничего нет — пишем статус
            const errorText = data.error || data.message || `Ошибка сервера: ${response.status}`;
            throw new Error(errorText);
        }

        return data;
    },

    get(url, options = {}) {
        return this.request(url, { method: 'GET', ...options });
    },

    post(url, body = {}, options = {}) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(body),
            ...options
        });
    }
};