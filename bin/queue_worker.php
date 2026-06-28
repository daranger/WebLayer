<?php

declare(strict_types=1);

// 1. ПОДКЛЮЧАЕМ АВТОЛОАД
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\Application;
use App\Exceptions\Handler;
use App\Services\NginxManager;
use App\Services\SSLManager;
use App\Services\CloudflareManager;
use App\Services\SiteService;

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

// 💾 1. БИНДИМ PDO (Подключение к БД из .env)
$container->bind(PDO::class, function() {
    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '3306');
    $db   = env('DB_NAME', 'panel');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', 'root');

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
});

// 🌐 2. БИНДИМ NginxManager
$container->bind(NginxManager::class, function() {
    $templatePath = realpath(__DIR__ . '/../app');
    $storagePath  = realpath(__DIR__ . '/../storage');

    if (!$templatePath || !$storagePath) {
        throw new \RuntimeException("[❌] Ошибка NginxManager: Проверь папки app/ и storage/");
    }
    return new NginxManager($templatePath, $storagePath);
});

// 🔒 3. БИНДИМ SSLManager (Передаем строки в конструктор)
$container->bind(SSLManager::class, function() {
    $certbotPath = '/usr/bin/certbot';
    $adminEmail  = 'admin@' . env('SERVER_IP', 'localhost');

    return new SSLManager($certbotPath, $adminEmail);
});

// 🌤️ 4. БИНДИМ CloudflareManager (Берём токен из конфигурации)
$container->bind(CloudflareManager::class, function() {
    $cfToken = env('CF_API_TOKEN', 'mock-token-for-local-dev');
    return new CloudflareManager($cfToken);
});

// 🚀 5. БИНДИМ SiteService и явно прокидываем все зависимости
$container->bind(SiteService::class, function($c) {
    return new SiteService(
        $c->make(PDO::class),
        $c->make(\App\Services\SystemManager::class),
        $c->make(\App\Services\FileManager::class),
        $c->make(NginxManager::class),
        $c->make(SSLManager::class),
        $c->make(CloudflareManager::class),
        $c->make(\App\Services\NotificationService::class)
    );
});

// Инициализируем Redis через правильный интерфейс Predis
$redis = $container->make(\Predis\ClientInterface::class);

echo "[*] Демон очередей панели запущен. Ожидание задач...\n";

$jobsProcessed = 0;
$maxJobs = 500;

while (true) {
    if ($jobsProcessed >= $maxJobs) {
        echo "[*] Достигнут лимит обработанных задач ({$maxJobs}). Перезапуск воркера...\n";
        exit(0);
    }

    try {
        // Твой оригинальный вызов blPop с большой буквой P
        $jobData = $redis->blPop(['queue:default'], 10);
    } catch (\Throwable $e) {
        echo "[⚠️ Ошибка Redis] Потеряно соединение: {$e->getMessage()}. Повтор через 3 сек...\n";
        sleep(3);
        continue;
    }

    if (!$jobData) {
        continue;
    }

    // 🔥 ИСПРАВЛЕНИЕ РАЗБОРА ДЛЯ ОБЫЧНЫХ И НАКОПЛЕННЫХ ЗАДАЧ:
    $rawJson = $jobData instanceof \Predis\Response\Tuple ? $jobData->getValue() : ($jobData[1] ?? null);

    if (!$rawJson) {
        echo "[⚠️] Получены пустые или битые данные из очереди. Пропускаем.\n";
        continue;
    }

    $payload  = json_decode((string)$rawJson, true);
    $jobId    = $payload['id'] ?? bin2hex(random_bytes(8));
    $jobClass = $payload['job'] ?? null;
    $data     = $payload['payload'] ?? [];
    $domain   = $data['domain'] ?? 'unknown';

    if (!$jobClass || !class_exists($jobClass)) {
        echo "[❌] [Job {$jobId}] Критическая ошибка: Класс задачи [{$jobClass}] не найден.\n";
        continue;
    }

    $lockKey = "lock:job:run:{$domain}";
    if (!$redis->set($lockKey, 'processing', 'NX', 'EX', 120)) {
        echo "[⚠️] [Job {$jobId}] Пропуск: Домен {$domain} уже обрабатывается другим процессом воркера.\n";
        continue;
    }

    try {
        echo "[+] [Job {$jobId}] Запуск: {$jobClass} для домена {$domain}\n";

        $jobInstance = $container->make($jobClass);
        $jobInstance->handle($data);

        echo "[✓] [Job {$jobId}] Задача успешно завершена.\n";

    } catch (\Throwable $e) {
        echo "[❌] [Job {$jobId}] Ошибка выполнения {$jobClass}: {$e->getMessage()}\n";

        $redis->rPush('queue:failed', json_encode([
            'id'        => $jobId,
            'job'       => $jobClass,
            'payload'   => $data,
            'error'     => $e->getMessage(),
            'trace'     => $e->getTraceAsString(),
            'failed_at' => time()
        ]));

    } finally {
        $redis->del($lockKey);
        $redis->del("lock:site:create:{$domain}");
        $jobsProcessed++;
    }
}