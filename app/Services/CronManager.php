<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Exception;

class CronManager
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    private function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Синхронизирует активные задачи из базы данных с системным crontab
     */
    public function sync(string $workspaceId = 'www'): bool
    {
        $stmt = $this->db->prepare("SELECT command, schedule, email FROM cron_jobs WHERE workspace_id = ? AND is_active = 1");
        $stmt->execute([$workspaceId]);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cronContent = "# ВНИМАНИЕ: Этот файл сгенерирован автоматически панелью SiteManager.\n";
        $cronContent .= "# Все ручные изменения будут перезаписаны.\n\n";

        $currentEmail = null;

        foreach ($jobs as $job) {
            $email = $job['email'] ?: '""'; // пустой MAILTO отключает отправку
            if ($email !== $currentEmail) {
                $cronContent .= "MAILTO={$email}\n";
                $currentEmail = $email;
            }
            $cronContent .= "{$job['schedule']} {$job['command']}\n";
        }

        if (empty($jobs)) {
            $cronContent .= "\n";
        }

        if ($this->isWindows()) {
            @file_put_contents(sys_get_temp_dir() . '/mock_crontab.txt', $cronContent);
            return true;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'cron_');
        file_put_contents($tmpFile, $cronContent);

        $output = [];
        $returnVar = 1;
        
        exec("cat " . escapeshellarg($tmpFile) . " | crontab - 2>&1", $output, $returnVar);

        @unlink($tmpFile);

        if ($returnVar !== 0) {
            throw new Exception("Не удалось обновить crontab: " . implode("\n", $output));
        }

        return true;
    }
}
