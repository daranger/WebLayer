<?php

use App\Core\Firewall;

if (!function_exists('env')) {
    /**
     * Получает значение переменной окружения из $_ENV с приведением типов.
     */
    function env(string $key, mixed $default = null): mixed
    {
        if (!isset($_ENV[$key])) {
            return $default;
        }

        $value = $_ENV[$key];

        // Корректно приводим строковые булевы и null типы
        switch (strtolower($value)) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
            case 'empty':
                return '';
        }

        return $value;
    }
}

function view(string $view, array $data = []): string
{
    // ... твой код проверок 404 / 401 / 403 ...

    extract($data, EXTR_SKIP);
    ob_start();
    include __DIR__ . "/../../resources/views/" . str_replace('.', '/', $view) . ".php";
    $content = ob_get_clean();

    // 1. Твоя проверка на PJAX (если нужна для совместимости)
    if (Firewall::$pjax) {
        header("X-Robots-Tag: noindex, nofollow");
        header('Content-Type: application/json');
        return json_encode([
            'title' => $title ?? '',
            'content' => $content,
            'update' => $update ?? 0,
            'type' => $page_type ?? 'page'
        ]);
    }

    // 🔥 2. СПАСИТЕЛЬНЫЙ ФИКС ДЛЯ ТВОЕГО АСИНХРОННОГО ДВИЖКА
    // Если запрос отправлен через fetch() из app.js, отдаем ТОЛЬКО чистый $content
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        return $content;
    }

    // 3. Обычный полный рендеринг для прямой перезагрузки страницы в браузере
    ob_start();
    include __DIR__ . '/../../resources/views/layout.php';
    return ob_get_clean();
}