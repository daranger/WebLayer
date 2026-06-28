<?php

declare(strict_types=1);

namespace App\Services;

use Exception;

class SystemManager
{
    /**
     * Проверка, запущена ли панель на Windows
     */
    private function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Создание системного пользователя Linux
     */
    public function createUser(string $username): bool
    {
        $safeUser = $this->sanitizeUsername($username);

        // 🔥 ХАК ДЛЯ WINDOWS (Локальная разработка)
        if ($this->isWindows()) {
            echo "[SystemManager] [Windows Mock] Имитация проверки и создания пользователя: {$safeUser}\n";
            return true;
        }

        // Боевой код для Linux (Ubuntu)
        if (posix_getpwnam($safeUser) !== false) {
            return true; // Пользователь уже есть
        }

        $command = sprintf("sudo useradd -M -s /usr/sbin/nologin %s 2>&1", escapeshellarg($safeUser));
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            error_log("Ошибка создания пользователя {$safeUser}. Code: {$returnCode}. Output: " . implode("\n", $output));
            return false;
        }

        return true;
    }

    /**
     * Удаление системного пользователя (Откат зомби)
     */
    public function deleteUser(string $username): bool
    {
        $safeUser = $this->sanitizeUsername($username);

        // 🔥 ХАК ДЛЯ WINDOWS (Локальная разработка)
        if ($this->isWindows()) {
            echo "[SystemManager] [Windows Mock] Имитация удаления пользователя: {$safeUser}\n";
            return true;
        }

        // Боевой код для Linux (Ubuntu)
        if (posix_getpwnam($safeUser) === false) {
            return true; // И так нет такого пользователя
        }

        // -r не используем, так как директории удаляет FileManager
        $command = sprintf("sudo userdel %s 2>&1", escapeshellarg($safeUser));
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            error_log("Ошибка удаления пользователя {$safeUser}. Code: {$returnCode}. Output: " . implode("\n", $output));
            return false;
        }

        return true;
    }

    private function sanitizeUsername(string $username): string
    {
        $safe = preg_replace('/[^a-z0-9_-]/i', '', $username);
        if (empty($safe)) {
            throw new Exception("Небезопасное имя пользователя Linux: {$username}");
        }
        return $safe;
    }
}