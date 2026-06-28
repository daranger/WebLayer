<?php

namespace App\Core;

class Session
{
    private static array $writeRoutes = [
        'login',
        'logout',
        'google.oauth',
    ];

    public static function start(): void
    {
        // 🔥 ЗАЩИТА: Если сессия уже запущена где-то выше — не стартуем её заново
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        session_start(
            self::needsWrite()
                ? []
                : ['read_and_close' => true]
        );
    }

    private static function needsWrite(): bool
    {
        $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

        foreach (self::$writeRoutes as $route) {
            if (preg_match("#(^|/){$route}$#", $path)) {
                return true;
            }
        }

        return false;
    }

}