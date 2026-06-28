<?php

namespace App\Services;

class ServiceManager
{
    /**
     * Проверка существования (установки) службы в системе
     */
    public function isInstalled(string $service): bool
    {
        if (!preg_match('/^[a-zA-Z0-9\-_.]+$/', $service)) return false;

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return true; // На Windows отображаем все из конфига для отладки
        }

        $output = trim(shell_exec("systemctl show -p LoadState " . escapeshellarg($service) . " 2>/dev/null") ?? '');
        return strpos($output, 'LoadState=loaded') !== false;
    }

    /**
     * Проверка статуса службы
     */
    public function isActive(string $service): bool
    {
        if (!preg_match('/^[a-zA-Z0-9\-_.]+$/', $service)) return false;

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return false; // На Windows считаем неактивными
        }

        $status = trim(shell_exec("systemctl is-active " . escapeshellarg($service) . " 2>/dev/null") ?? '');
        return $status === 'active';
    }

    /**
     * Управление состоянием службы (мгновенно через root_helper)
     */
    public function control(string $service, string $action): bool
    {
        if (!preg_match('/^[a-zA-Z0-9\-_.]+$/', $service)) return false;
        if (!in_array($action, ['start', 'stop', 'restart', 'reload'])) return false;

        $helperPath = realpath(__DIR__ . '/../../bin/root_helper.php');
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        if ($isWindows) {
            // На Windows нет systemctl, эмулируем успех (или можно пропускать)
            return true;
        }

        $payload = json_encode([
            'action' => 'service_control',
            'service' => $service,
            'service_action' => $action
        ]);

        $cmd = "sudo /usr/bin/php " . escapeshellarg($helperPath);
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];
        
        $process = proc_open($cmd, $descriptorspec, $pipes);
        if (is_resource($process)) {
            fwrite($pipes[0], $payload);
            fclose($pipes[0]);
            
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            proc_close($process);
            
            $result = json_decode($output, true);
            return isset($result['success']) && $result['success'] === true;
        }

        return false;
    }

    /**
     * Получить реальное потребление памяти процессом Redis (в байтах)
     */
    public function getRedisMemory(): int
    {
        // Используем внутреннюю команду redis-cli для точности
        $raw = trim(shell_exec("redis-cli INFO memory | grep 'used_memory:'") ?? '');
        if (preg_match('/used_memory:(\d+)/', $raw, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }

    /**
     * Получить потребление памяти MySQL (сумма буферов + работающие треды)
     */
    public function getMySQLMemory(): int
    {
        // Парсим вывод pmap или ps для системного процесса mysqld
        $pid = trim(shell_exec("pidof mysqld") ?? '');
        if (empty($pid)) return 0;

        // Читаем RSS (Resident Set Size) память процесса в Кб из /proc
        if (is_readable("/proc/{$pid}/status")) {
            foreach (file("/proc/{$pid}/status") as $line) {
                if (preg_match('/^VmRSS:\s+(\d+)/', $line, $matches)) {
                    return (int)$matches[1] * 1024; // в байты
                }
            }
        }
        return 0;
    }
}