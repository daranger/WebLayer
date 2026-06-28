<?php

declare(strict_types=1);

namespace App\Core;

use Predis\ClientInterface;
use Exception;

class Router
{
    private static array $routes = [];

    public static function get(string $uri, array $callback, bool $protected = true): void
    {
        self::$routes['GET'][$uri] = ['callback' => $callback, 'protected' => $protected];
    }

    public static function post(string $uri, array $callback, bool $protected = true): void
    {
        self::$routes['POST'][$uri] = ['callback' => $callback, 'protected' => $protected];
    }
    public static function delete(string $uri, array $callback, bool $protected = true): void
    {
        self::$routes['DELETE'][$uri] = ['callback' => $callback, 'protected' => $protected];
    }
    /**
     * Основной метод диспетчеризации
     */
    public static function dispatch(string $method, string $uri, ClientInterface $redis): void
    {
        if (!isset(self::$routes[$method][$uri])) {
            throw new Exception("Route not found", 404);
        }

        $route = self::$routes[$method][$uri];
        $request = new Request(); // Используем твой класс Request

        // 🔒 Проверка доступа по IP (белый список)
        self::checkIpWhitelist();

        // 🔒 Проверка доступа перед вызовом экшена
        self::handleAuthAccess($route['protected'], $redis, $request);

        [$controllerClass, $action] = $route['callback'];
        $controller = Container::getInstance()->make($controllerClass);
        $controller->$action();
    }

    private static function handleAuthAccess(bool $protected, ClientInterface $redis, Request $request): void
    {
        // Инициализируем твой безопасный менеджер сессий
        Session::start();

        // Проверяем, AJAX запрос от подгрузчика панелей или это прямой F5
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $sessionLoggedIn = isset($_SESSION['user_logged']) && $_SESSION['user_logged'] === true;

        if ($isAjax) {
            $token = $_SERVER['HTTP_X_PANEL_TOKEN'] ?? '';
            $redisExists = !empty($token) && $redis->exists("session:{$token}");

            // Юзер считается залогиненным для API, если токен есть в Redis ИЛИ если активна сессия браузера
            if ($redisExists) {
                $sessionData = json_decode($redis->get("session:{$token}"), true);
                if (env('PANEL_BIND_IP_SESSION', false) === 'true') {
                    $currentIp = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                    if (isset($sessionData['ip_address']) && $sessionData['ip_address'] !== $currentIp) {
                        $redisExists = false; // IP mismatch, invalidate token check
                        $redis->del("session:{$token}");
                    }
                }
            }

            $isLoggedIn = $redisExists || $sessionLoggedIn;

            // 🔥 ЕСЛИ ТОКЕНА В REDIS НЕТ, НО PHP-СЕССИЯ ЖИВА — НА ЛЕТУ ОБНОВЛЯЕМ ТОКЕН
            if ($protected && !$redisExists && $sessionLoggedIn) {
                // Генерируем новый токен
                $newToken = bin2hex(random_bytes(32));
                // Записываем в Redis на 30 минут
                $redis->setex("session:{$newToken}", 1800, json_encode([
                    'user' => $_SESSION['user_id'] ?? 'admin',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
                ]));

                // Выстреливаем заголовок ответа, чтобы твой app.js перехватил этот токен
                header("X-Refresh-Token: {$newToken}");

                // Подменяем токен в текущем глобальном массиве для успешного продления сессии ниже
                $_SERVER['HTTP_X_PANEL_TOKEN'] = $newToken;
                $isLoggedIn = true;
            }
        } else {
            // Для F5 переходов смотрим в стандартную сессию PHP
            $isLoggedIn = $sessionLoggedIn;
        }

        $currentPath = $request->path();

        $loginPath = '/' . ltrim(env('PANEL_LOGIN_PATH', 'login'), '/');

        // 1. Если роут защищенный, а юзер не авторизован
        if ($protected && !$isLoggedIn) {
            if ($isAjax) {
                http_response_code(401);
                exit;
            }
            header('Location: ' . $loginPath);
            exit('error12');
        }

        // 2. Если залогиненный юзер лезет на страницу логина (работает ТОЛЬКО для GET запросов)
        if (!$protected && $isLoggedIn && $currentPath === $loginPath && !$request->isPost()) {
            header('Location: /');
            exit('error11');
        }

        // Продлеваем сессию в Redis для живых асинхронных запросов панели
        if ($isAjax && $isLoggedIn && isset($_SERVER['HTTP_X_PANEL_TOKEN'])) {
            $redis->expire("session:{$_SERVER['HTTP_X_PANEL_TOKEN']}", 1800);
        }
    }

    private static function checkIpWhitelist(): void
    {
        $allowedIps = env('PANEL_ALLOWED_IPS', '0.0.0.0/0');
        if (empty(trim($allowedIps)) || trim($allowedIps) === '0.0.0.0/0') {
            return; // All allowed
        }

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        // If behind Cloudflare/Proxy, you might need HTTP_X_FORWARDED_FOR
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $clientIp = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }

        if (empty($clientIp)) {
            return;
        }

        $allowed = false;
        $ips = array_map('trim', explode(',', $allowedIps));
        
        foreach ($ips as $ip) {
            if (empty($ip)) continue;
            
            // CIDR support or exact match
            if (strpos($ip, '/') !== false) {
                list($subnet, $bits) = explode('/', $ip);
                $subnet = ip2long($subnet);
                $client = ip2long($clientIp);
                $mask = -1 << (32 - $bits);
                if (($client & $mask) == ($subnet & $mask)) {
                    $allowed = true;
                    break;
                }
            } else {
                if ($clientIp === $ip) {
                    $allowed = true;
                    break;
                }
            }
        }

        if (!$allowed) {
            http_response_code(403);
            exit("Access Denied: Your IP ($clientIp) is not whitelisted.");
        }
    }
}