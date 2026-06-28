<?php

namespace App\Services;

class SSLManager
{
    private string $storagePath;

    public function __construct(string $storagePath)
    {
        $this->storagePath = rtrim($storagePath, '/');
    }

    /**
     * Проверка, запущена ли панель на Windows
     */
    private function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Выпускает новый сертификат Let's Encrypt через Certbot.
     * Возвращает массив ['success' => bool, 'output' => string]
     */
    public function issue(string $domain, string $webroot, string $email = 'admin@localhost'): array
    {
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return ['success' => false, 'output' => 'Invalid domain'];
        }

        // 🔥 ХАК ДЛЯ ЛОКАЛЬНОЙ РАЗРАБОТКИ НА WINDOWS
        if ($this->isWindows()) {
            return ['success' => true, 'output' => "[SSLManager] [Windows Mock] Имитация успешного выпуска SSL-сертификата для {$domain}"];
        }

        // Защита: Certbot требует, чтобы папка webroot физически существовала
        if (!is_dir($webroot)) {
            return ['success' => false, 'output' => 'Webroot directory does not exist'];
        }

        $escapedDomain = escapeshellarg($domain);
        $escapedWebroot = escapeshellarg($webroot);
        $escapedEmail = escapeshellarg($email);

        $output = [];
        $returnVar = 1;

        // Команда выпуска сертификата в неинтерактивном режиме
        $cmd = sprintf(
            "sudo certbot certonly --webroot -w %s -d %s -d %s --non-interactive --agree-tos --email %s 2>&1",
            $escapedWebroot,
            $escapedDomain,
            escapeshellarg("www." . $domain),
            $escapedEmail
        );

        exec($cmd, $output, $returnVar);

        // Если Certbot вернул 0 — сертификаты успешно лежат в /etc/letsencrypt/live/$domain/
        return [
            'success' => $returnVar === 0,
            'output' => implode("\n", $output)
        ];
    }

    /**
     * Удаляет сертификаты из системы при удалении сайта
     */
    public function delete(string $domain): bool
    {
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return false;
        }

        // 🔥 ХАК ДЛЯ ЛОКАЛЬНОЙ РАЗРАБОТКИ НА WINDOWS
        if ($this->isWindows()) {
            echo "[SSLManager] [Windows Mock] Имитация удаления SSL-сертификата для {$domain}\n";
            return true;
        }

        $escapedDomain = escapeshellarg($domain);
        $output = [];
        $returnVar = 1;

        exec("sudo certbot delete --cert-name {$escapedDomain} --non-interactive 2>&1", $output, $returnVar);

        return $returnVar === 0;
    }
}