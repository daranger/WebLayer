<?php

namespace App\Services;

class SystemService
{
    /**
     * Чтение аптайма сервера (строго первое число, приведение к int)
     */
    public function getUptime(): int
    {
        if (!is_readable('/proc/uptime')) return 0;

        $content = trim(file_get_contents('/proc/uptime'));
        $parts = explode(' ', $content);

        return (int)($parts[0] ?? 0);
    }

    /**
     * Получение количества ядер процессора
     */
    public function getCpuCount(): int
    {
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            $count = substr_count($cpuinfo, 'processor');
            if ($count > 0) return $count;
        }
        return 1;
    }

    /**
     * Реальное потребление процессора (замеряет разницу за 100мс из /proc/stat)
     */
    public function getCpuUsagePercent(): int
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cpuLoad = shell_exec('wmic cpu get loadpercentage /value');
            if ($cpuLoad && preg_match('/LoadPercentage=(\d+)/i', (string)$cpuLoad, $matches)) {
                return (int)$matches[1];
            }
            return 0;
        }

        if (!is_readable('/proc/stat')) return 0;

        $stat1 = file('/proc/stat');
        usleep(100000); // 100ms
        $stat2 = file('/proc/stat');

        if (!isset($stat1[0]) || !isset($stat2[0])) return 0;

        $info1 = explode(" ", preg_replace("!cpu +!", "", $stat1[0]));
        $info2 = explode(" ", preg_replace("!cpu +!", "", $stat2[0]));

        if (count($info1) < 4 || count($info2) < 4) return 0;

        $dif = [
            'user' => (int)($info2[0] ?? 0) - (int)($info1[0] ?? 0),
            'nice' => (int)($info2[1] ?? 0) - (int)($info1[1] ?? 0),
            'sys'  => (int)($info2[2] ?? 0) - (int)($info1[2] ?? 0),
            'idle' => (int)($info2[3] ?? 0) - (int)($info1[3] ?? 0)
        ];

        $total = array_sum($dif);
        if ($total == 0) return 0;

        $cpu = round(100 * ($dif['user'] + $dif['nice'] + $dif['sys']) / $total);
        return (int)min(100, max(0, $cpu));
    }

    /**
     * Метрики RAM в байтах и чистых процентах
     */
    public function getRamUsage(): array
    {
        if (!is_readable('/proc/meminfo')) {
            return ['total' => 0, 'used' => 0, 'free' => 0, 'percent' => 0];
        }

        $data = [];
        foreach (file('/proc/meminfo') as $line) {
            if (preg_match('/^(MemTotal|MemAvailable):\s+(\d+)/', $line, $matches)) {
                $data[$matches[1]] = (int)$matches[2] * 1024; // переводим Кб в Байты
            }
        }

        $total = $data['MemTotal'] ?? 0;
        $available = $data['MemAvailable'] ?? 0;
        $used = $total - $available;
        $percent = $total > 0 ? (int)round(($used / $total) * 100) : 0;

        return [
            'total'   => $total,
            'used'    => $used,
            'free'    => $available,
            'percent' => $percent
        ];
    }

    /**
     * Метрики диска без подавления ошибок через @
     */
    public function getDiskUsage(string $path = '/'): array
    {
        $free = disk_free_space($path);
        $total = disk_total_space($path);

        if ($free === false || $total === false || $total === 0) {
            return ['total' => 0, 'used' => 0, 'free' => 0, 'percent' => 0];
        }

        $used = $total - $free;
        $percent = (int)round(($used / $total) * 100);

        return [
            'total'   => $total, // в байтах
            'used'    => $used,
            'free'    => $free,
            'percent' => $percent
        ];
    }

    /**
     * Умный парсинг IP с делением на публичные/приватные и фильтрацией Docker
     */
    public function getFilteredIPs(): array
    {
        $output = trim(shell_exec("ip -4 addr show | grep -oP '(?<=inet\s)\d+(\.\d+){3}'") ?? '');
        if (empty($output)) return ['public' => [], 'private' => []];

        $rawIps = explode("\n", $output);
        $result = ['public' => [], 'private' => []];

        foreach ($rawIps as $ip) {
            $ip = trim($ip);

            // Валидация IPv4
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;

            // Исключаем петлю и подсети Docker/локальных виртуальных мостов по умолчанию
            if ($ip === '127.0.0.1' || str_starts_with($ip, '172.17.') || str_starts_with($ip, '172.18.')) {
                continue;
            }

            // Проверяем, приватный ли IP (10.x, 192.168.x, 172.16.x-172.31.x)
            $isPrivate = !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

            if ($isPrivate) {
                $result['private'][] = $ip;
            } else {
                $result['public'][] = $ip;
            }
        }

        return $result;
    }

    public function getLoadAverage(): array
    {
        // Проверяем, существует ли функция в операционной системе (на Windows её нет)
        if (!function_exists('sys_getloadavg')) {
            return ['1m' => 0.0, '5m' => 0.0, '15m' => 0.0];
        }

        // Ставим обратный слэш \, чтобы вызывать строго глобальную функцию PHP
        $la = \sys_getloadavg();
        return [
            '1m'  => $la[0] ?? 0.0,
            '5m'  => $la[1] ?? 0.0,
            '15m' => $la[2] ?? 0.0
        ];
    }

    /**
     * Получение текущего количества процессов в системе
     */
    public function getProcessCount(): int
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $out = @shell_exec('tasklist 2>nul');
            if ($out) {
                return max(0, count(explode("\n", trim($out))) - 3); // Вычитаем 3 строки заголовка
            }
            return 0;
        }

        // Попытка 1: Читаем /proc (самый быстрый способ)
        if (@is_readable('/proc')) {
            $dirs = @scandir('/proc');
            if (is_array($dirs)) {
                $count = 0;
                foreach ($dirs as $dir) {
                    if (is_numeric($dir)) $count++;
                }
                if ($count > 0) return $count;
            }
        }
        
        // Попытка 2: Если /proc закрыт через open_basedir, используем ps
        $out = @shell_exec('ps -e | wc -l 2>/dev/null');
        if ($out) {
            $count = (int)trim($out);
            return $count > 1 ? $count - 1 : $count; // Вычитаем строку заголовка ps
        }

        return 0;
    }
}