<?php

namespace App\Jobs;

class ServiceJob
{
    /**
     * @param array $data payload
     * 
     * Expects:
     * - service: nginx, php8.3-fpm, mariadb, etc.
     * - action: start, stop, restart, reload
     */
    public function handle(array $data): void
    {
        $service = $data['service'] ?? '';
        $action = $data['action'] ?? '';

        if (!preg_match('/^[a-zA-Z0-9\-_.]+$/', $service)) {
            throw new \RuntimeException("Недопустимое имя службы: {$service}");
        }

        if (!in_array($action, ['start', 'stop', 'restart', 'reload'])) {
            throw new \RuntimeException("Недопустимое действие: {$action}");
        }

        // Выполняем команду
        $cmd = sprintf("systemctl %s %s 2>&1", escapeshellcmd($action), escapeshellarg($service));
        
        echo "[ServiceJob] Выполняю: {$cmd}\n";
        
        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0) {
            $err = implode("\n", $output);
            throw new \RuntimeException("Ошибка systemctl (код {$returnVar}): {$err}");
        }
        
        echo "[ServiceJob] Успешно выполнено.\n";
    }
}
