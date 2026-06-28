<?php

// Определяем текущую версию PHP (например 8.3, 8.4)
$phpVer = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

return [
    'nginx' => 'Nginx (Web-сервер)',
    "php{$phpVer}-fpm" => "PHP {$phpVer} FPM",
    'mariadb' => 'MariaDB (База данных)',
    'redis-server' => 'Redis (Кэш и очереди)',
    'sitemanager-worker' => 'SiteManager Worker (Фоновые задачи)',
    'sitemanager-monitor' => 'SiteManager Monitor (Сбор метрик)',
    'ssh' => 'SSH (Доступ к серверу)'
];
