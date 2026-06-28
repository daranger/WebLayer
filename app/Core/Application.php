<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\Handler;
use App\Contracts\DatabaseManagerInterface;
use App\Services\Database\MySQLManager;
use App\Services\Database\PostgreSQLManager;
use PDO;
use Exception;

class Application
{
    /**
     * Локальное хранилище для инстанса Redis (Predis\Client)
     */
    private $redis;

    public function __construct()
    {
        // 1. Загружаем переменные окружения
        $this->loadEnv(__DIR__ . '/../../.env');

        // 2. Инициализируем глобальный обработчик ошибок
        //Handler::init();

        // 3. Базовые заголовки безопасности (только для Веб-запросов, в CLI пропускаем)
        if (php_sapi_name() !== 'cli') {
            // Если запрашивают HTML-страницы (/pages/* или /), отдаем text/html, иначе JSON
            $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (str_starts_with($uri, '/api/')) {
                header("Content-Type: application/json; charset=UTF-8");
            } else {
                header("Content-Type: text/html; charset=UTF-8");
            }
            header("X-Content-Type-Options: nosniff");
            header("X-Frame-Options: DENY");
        }

        $this->registerShutdownHandler();

        // 4. Инициализируем и настраиваем DI Контейнер
        $this->bootstrapContainer();
    }

    public function run(): void
    {
        // Подгружаем карты маршрутов
        require_once __DIR__ . '/../../routes/api.php';
        require_once __DIR__ . '/../../routes/web.php';

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        try {
            // Передаем в роутер инстанс Redis (через интерфейс Predis)
            Router::dispatch($method, $uri, $this->redis);
        } catch (Exception $e) {
            if (php_sapi_name() !== 'cli') {
                $code = (int)$e->getCode();
                if ($code < 100 || $code > 599) {
                    $code = 500;
                }
                http_response_code($code);
            }

            // Если упал HTML-роут — отдаем красивую ошибку, если API — JSON
            if (str_starts_with($uri, '/pages/') || $uri === '/') {
                echo "<div style='padding:20px; font-family:sans-serif;'><h3>Ошибка:</h3><p>{$e->getMessage()}</p></div>";
            } else {
                echo json_encode([
                    'success' => false,
                    'error'   => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Конфигурация и наполнение DI Контейнера системными сервисами
     */
    private function bootstrapContainer(): void
    {
        $container = Container::getInstance();

        // 1. Идеальное решение проблемы типов: биндим Predis\ClientInterface
        $container->bind(\Predis\ClientInterface::class, function () {
            return new \Predis\Client([
                'scheme' => 'tcp',
                'host'   => env('REDIS_HOST', '127.0.0.1'),
                'port'   => (int)env('REDIS_PORT', 6379),
            ]);
        }, true);

        // Алиас для обратной совместимости, если в коде где-то остался тайпхинт \Redis
        $container->bind(\Redis::class, function ($c) {
            return $c->make(\Predis\ClientInterface::class);
        }, true);

        // Инициализируем локальное свойство через интерфейс Predis
        $this->redis = $container->make(\Predis\ClientInterface::class);

        // Делаем инстанс доступным через global $redis для старых узлов (например, RateLimiter)
        global $redis;
        $redis = $this->redis;

        // 2. Формируем DSN и регистрируем PDO с использованием хелпера env()
        $driver = env('DB_DRIVER', 'mysql');
        $host   = env('DB_HOST', '127.0.0.1');
        $port   = env('DB_PORT', '3306');
        $dbname = env('DB_NAME', 'panel');
        $user   = env('DB_USER', 'root');
        $pass   = env('DB_PASS', '');

        $dsn = "{$driver}:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

        $container->bind(\PDO::class, function () use ($dsn, $user, $pass) {
            return new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }, true);

        // 3. Привязываем интерфейс СУБД к конкретной реализации на основе .env
        $dbManagerClass = ($driver === 'pgsql') ? PostgreSQLManager::class : MySQLManager::class;
        $container->bind(DatabaseManagerInterface::class, $dbManagerClass, true);
    }

    /**
     * Легковесный парсер .env
     */
    private function loadEnv(string $path): void
    {
        if (!is_file($path)) {
            throw new Exception("Файл конфигурации .env не найден", 500);
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                $value = trim($value, '"\'');

                $_ENV[$key] = $value;
            }
        }

        // Автоматически генерируем защищенный путь для phpMyAdmin, если его нет
        if (!isset($_ENV['PANEL_PMA_PATH'])) {
            $randomString = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
            $pmaPath = '/phpmyadmin_' . $randomString;
            $_ENV['PANEL_PMA_PATH'] = $pmaPath;
            file_put_contents($path, "\nPANEL_PMA_PATH=\"{$pmaPath}\"\n", FILE_APPEND);
        }

        // "Правильный" фикс для Ubuntu: автоматически создаем symlink или переименовываем папку
        $publicDir = realpath(__DIR__ . '/../../public');
        if ($publicDir && isset($_ENV['PANEL_PMA_PATH'])) {
            $pmaName = ltrim($_ENV['PANEL_PMA_PATH'], '/');
            $targetPath = $publicDir . DIRECTORY_SEPARATOR . $pmaName;
            
            if (!file_exists($targetPath)) {
                // Сначала ищем и удаляем/переименовываем старые версии
                $existing = glob($publicDir . '/phpmyadmin*');
                foreach ($existing as $ex) {
                    if (is_link($ex)) {
                        @unlink($ex);
                    } elseif (is_dir($ex)) {
                        @rename($ex, $targetPath);
                    }
                }
                
                // Если папки всё еще нет, создаем symlink на глобальный phpMyAdmin Ubuntu
                if (!file_exists($targetPath) && file_exists('/usr/share/phpmyadmin')) {
                    @symlink('/usr/share/phpmyadmin', $targetPath);
                }
            }
        }
    }

    private function registerShutdownHandler(): void
    {
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                if (php_sapi_name() !== 'cli') {
                    http_response_code(500);
                }
                echo json_encode([
                    'success' => false,
                    'error'   => 'Internal Server Error',
                    'details' => env('PANEL_ENV', 'production') === 'development' ? $error['message'] : 'Произошла критическая ошибка'
                ]);
            }
        });
    }
}