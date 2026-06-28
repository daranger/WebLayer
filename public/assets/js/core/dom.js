export const Dom = {
    one(selector, parent = document) {
        return parent.querySelector(selector);
    },
    all(selector, parent = document) {
        return [...parent.querySelectorAll(selector)];
    },
    /**
     * Умное делегирование событий
     */
    on(element, event, selector, handler) {
        element.addEventListener(event, (e) => {
            const target = e.target.closest(selector);
            if (target && element.contains(target)) {
                handler(e, target);
            }
        });
    }
};