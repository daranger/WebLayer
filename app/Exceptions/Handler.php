<?php

namespace App\Exceptions;

use ErrorException;
use Throwable;

class Handler
{
    protected string $logFile;
    protected bool $isHandling = false;
    private static ?Handler $instance = null;

    private function __construct(string $logFile = null)
    {
        // Упрощаем путь. Никаких проверок папок здесь быть не должно.
        $this->logFile = $logFile ?: __DIR__ . '/../../storage/logs/errors/' . date("d.m.y") . '.log';
        $this->register();
    }

    public static function init(string $logFile = null): Handler
    {
        if (self::$instance === null) {
            self::$instance = new self($logFile);
        }
        return self::$instance;
    }

    protected function register(): void
    {
        // Только Warnings, Notices и Deprecated
        set_error_handler(
            [$this, 'handleMinorError'],
            E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED
        );

        set_exception_handler([$this, 'handleFatalException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleMinorError($level, $message, $file, $line): bool
    {
        if (!(error_reporting() & $level)) {
            return false;
        }

        if ($this->isHandling) {
            return false;
        }

        $this->isHandling = true;
        try {
            $this->log(new ErrorException($message, 0, $level, $file, $line));
        } catch (Throwable $e) {
            // Dumb & Tiny: если не залогировалось, просто игнорируем
        } finally {
            $this->isHandling = false;
        }

        return false; // Позволяем PHP продолжить выполнение кода
    }

    public function handleFatalException(Throwable $e): void
    {
        if ($this->isHandling) {
            return;
        }

        $this->isHandling = true;

        try {
            $this->log($e);
            $this->render($e); // Скрипт завершится через exit внутри
        } catch (Throwable $fatalInternalError) {
            // Системный аварийный лог ОС, если наш файл недоступен
            error_log("Crash in Error Handler: " . $fatalInternalError->getMessage());
        } finally {
            $this->isHandling = false;
        }
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->handleFatalException(new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }

    protected function log(Throwable $e): void
    {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $url = isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']) ? $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] : 'CLI';

            $message = sprintf(
                "[%s] IP:%s | URL:%s | Error:%s | File:%s:%d\n",
                date('Y-m-d H:i:s'),
                $ip,
                $url,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );

            // Идеальное решение: Атомарная, неблокирующая запись на уровне ядра ОС.
            // Без LOCK_EX, безmkdir, без проверок.
            // Если папки нет или нет прав — метод просто тихо вернет false без зависания CPU.
            @error_log($message, 3, $this->logFile);

        } catch (Throwable $ex) {
            // Абсолютная тишина при любых проблемах со средой
        }
    }

    public function render(Throwable $e): void
    {
        if (php_sapi_name() === 'cli') {
            echo "\n[🚨 КРИТИЧЕСКАЯ ОШИБКА ВОРКЕРА]\n";
            echo "Сообщение: " . $e->getMessage() . "\n";
            echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
            echo "Стек вызовов:\n" . $e->getTraceAsString() . "\n\n";
            exit(1);
        }

        $code = $e->getCode();
        if ($code < 400 || $code > 599) {
            $code = 500;
        }

        if (!headers_sent()) {
            http_response_code($code);
        }
        $root=true; //admin
        if ($root) {
            include __DIR__ . '/../../resources/views/errors/debug.php';
            exit;
        }

        if ($this->isJson()) {
            if (!headers_sent()) {
                @header('Content-Type: application/json');
            }

            // Массив с понятными текстами для API
            $errorMessages = [
                429 => 'Too Many Requests',
                404 => 'Not Found',
                500 => 'Internal Server Error'
            ];

            $message = $errorMessages[$code] ?? 'Internal Server Error';

            echo json_encode([
                'error' => true,
                'status' => $code,
                'message' => $message
            ]);
            exit;
        }

        if ($code == 429) {
            require_once __DIR__ . '/../../resources/views/errors/429.html';
        } else {
            require_once __DIR__ . '/../../resources/views/errors/default.html';
        }

        exit;
    }

    protected function isJson(): bool
    {
        return (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
    }
}