<?php

declare(strict_types=1);

// 1. ПОДКЛЮЧАЕМ АВТОЛОАД
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\Application;
use App\Exceptions\Handler;
use App\Services\SystemService;

// Глобальный хелпер env(), если он не объявлен в другом месте
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }
}

// 2. ВКЛЮЧАЕМ ОБРАБОТЧИК ОШИБОК
Handler::init();

// 🔥 ХАК ДЛЯ ПУТЕЙ В CLI: Принудительно переносим контекст выполнения в корень проекта
chdir(__DIR__ . '/../');

// 3. ИНИЦИАЛИЗИРУЕМ ПРИЛОЖЕНИЕ И КОНТЕЙНЕР
$app = new Application();
$container = Container::getInstance();

$redis = $container->make(\Predis\ClientInterface::class);
$systemService = $container->make(SystemService::class);

echo "[*] Демон системного мониторинга запущен. Сбор данных каждые 10 сек...\n";

// Константы
$HISTORY_KEY = 'system:monitor:history';
$CURRENT_KEY = 'system:monitor:current';
$MAX_HISTORY_POINTS = 15; // 15 точек * 60 сек = 15 минут истории

$processorName = 'Unknown CPU';
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $cpuInfo = shell_exec('wmic cpu get name 2>nul');
    if ($cpuInfo && preg_match('/Name\s+(.+)/i', $cpuInfo, $matches)) {
        $processorName = trim($matches[1]);
    }
} else {
    if (is_readable('/proc/cpuinfo')) {
        $cpuinfo = file_get_contents('/proc/cpuinfo');
        if (preg_match('/model name\s+:\s+(.+)/i', $cpuinfo, $matches)) {
            $processorName = trim($matches[1]);
        }
    }
}

$nginxVersion = 'Unknown';
$out = shell_exec('nginx -v 2>&1');
if ($out && preg_match('/nginx\/([0-9\.]+)/i', $out, $matches)) {
    $nginxVersion = $matches[1];
}

$mysqlVersionRaw = 'Unknown';
try {
    $db = $container->make(\PDO::class);
    $mysqlVersionRaw = $db->query('SELECT VERSION()')->fetchColumn() ?: 'Unknown';
} catch (\Throwable $e) {}

$isMariaDB = stripos($mysqlVersionRaw, 'mariadb') !== false;
$mysqlVersion = $mysqlVersionRaw;
if (preg_match('/^([\d\.]+)/', $mysqlVersionRaw, $matches)) {
    $mysqlVersion = $matches[1] . ($isMariaDB ? ' MariaDB' : '');
}

$osName = php_uname('s') . ' ' . php_uname('r');
if (is_readable('/etc/os-release')) {
    $osRelease = file_get_contents('/etc/os-release');
    if (preg_match('/^PRETTY_NAME="([^"]+)"/m', $osRelease, $matches)) {
        $osName = $matches[1];
    }
}

while (true) {
    try {
        $ramData  = $systemService->getRamUsage();
        $diskData = $systemService->getDiskUsage('/var/www/workspaces');
        $laData   = $systemService->getLoadAverage();
        $uptimeSec = $systemService->getUptime();
        $processCount = $systemService->getProcessCount();
        
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        $cpuPercent = $systemService->getCpuUsagePercent();
        
        if ($isWindows) {
            $la = round($cpuPercent / 100, 4);
        } else {
            $laRaw = $systemService->getLoadAverage();
            $la = isset($laRaw['1m']) ? round((float)$laRaw['1m'], 4) : 0.0;
        }
        
        $days = (int)($uptimeSec / 86400);
        $hours = (int)(($uptimeSec % 86400) / 3600);
        $minutes = (int)(($uptimeSec % 3600) / 60);
        $uptimeString = $days > 0 ? "{$days}дн. {$hours}ч." : "{$hours}ч. {$minutes}мин.";

        $stats = [
            'time' => time(),
            'cpu' => [
                'percent' => $cpuPercent,
                'la' => $la
            ],
            'ram' => [
                'total' => round($ramData['total'] / 1024 / 1024 / 1024, 1),
                'used' => round($ramData['used'] / 1024 / 1024 / 1024, 1),
                'percent' => $ramData['percent']
            ],
            'disk' => [
                'total' => round($diskData['total'] / 1024 / 1024 / 1024, 1),
                'used' => round($diskData['used'] / 1024 / 1024 / 1024, 1),
                'percent' => $diskData['percent']
            ],
            'uptime' => $uptimeString,
            'kernel' => php_uname('r'),
            'php_version' => PHP_VERSION,
            'redis_version' => $redis->info()['Server']['redis_version'] ?? 'Unknown',
            'processor' => $processorName,
            'nginx_version' => $nginxVersion,
            'mysql_version' => $mysqlVersion,
            'os_name' => $osName,
            'process_count' => $processCount
        ];

        $jsonStats = json_encode($stats);
        
        $historyPoint = [
            'time' => time(),
            'cpu' => ['percent' => $cpuPercent],
            'ram' => ['percent' => $ramData['percent']],
            'disk' => ['percent' => $diskData['percent']]
        ];
        $jsonHistory = json_encode($historyPoint);

        // Пишем в Redis
        $redis->set($CURRENT_KEY, $jsonStats);
        $redis->setex($CURRENT_KEY . ':alive', 15, '1'); // Маркер жизни демона
        
        $redis->lpush($HISTORY_KEY, [$jsonHistory]);
        $redis->ltrim($HISTORY_KEY, 0, $MAX_HISTORY_POINTS - 1);
        
        // echo "[OK] Метрики записаны.\n";
    } catch (\Throwable $e) {
        echo "[⚠️] Ошибка сбора метрик: " . $e->getMessage() . "\n";
        
        // Пытаемся переподключиться к Redis при разрыве
        if (strpos($e->getMessage(), 'Stream') !== false || strpos($e->getMessage(), 'Connection') !== false) {
            try {
                $redis = App\Core\Container::getInstance()->make(Predis\ClientInterface::class);
                echo "[🔄] Переподключение к Redis выполнено.\n";
            } catch (\Throwable $ex) {
                echo "[❌] Не удалось переподключиться: " . $ex->getMessage() . "\n";
            }
        }
    }

    sleep(10);
}
