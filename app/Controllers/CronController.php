<?php

namespace App\Controllers;

use App\Core\Container;
use App\Services\CronManager;
use PDO;

class CronController
{
    private PDO $db;
    private CronManager $cronManager;

    public function __construct()
    {
        $container = Container::getInstance();
        $this->db = $container->make(PDO::class);
        $this->cronManager = new CronManager($this->db);
        $this->autoMigrate();
    }

    private function autoMigrate(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `cron_jobs` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `workspace_id` varchar(255) NOT NULL DEFAULT 'www',
              `command` varchar(255) NOT NULL,
              `schedule` varchar(50) NOT NULL,
              `description` varchar(255) DEFAULT NULL,
              `email` varchar(255) DEFAULT NULL,
              `is_active` tinyint(1) NOT NULL DEFAULT '1',
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function index(): void
    {
        $stmt = $this->db->query("SELECT * FROM cron_jobs WHERE workspace_id = 'www' ORDER BY id DESC");
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo view('cron.index', [
            'title' => 'Планировщик - SiteManager',
            'jobs' => $jobs
        ]);
    }

    public function create(): void
    {
        echo view('cron.create', [
            'title' => 'Новое задание - SiteManager'
        ]);
    }

    public function store(): void
    {
        $command = trim($_POST['command'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Handle the checkbox "Do not send report" (no_email) vs explicit email
        $noEmail = isset($_POST['no_email']);
        $email = trim($_POST['email'] ?? '');
        if ($noEmail) {
            $email = '""'; // Empty string for MAILTO means don't send
        }

        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $scheduleMode = $_POST['schedule_mode'] ?? 'basic'; // basic, expert
        $schedule = '* * * * *';

        if ($scheduleMode === 'expert') {
            $schedule = trim($_POST['schedule_expert'] ?? '* * * * *');
        } else {
            $runType = $_POST['run_type'] ?? 'daily';
            $hour = trim($_POST['hour'] ?? '0');
            $minute = trim($_POST['minute'] ?? '0');
            
            if ($runType === 'minutely') {
                $schedule = "* * * * *";
            } elseif ($runType === 'hourly') {
                $schedule = "{$minute} * * * *";
            } elseif ($runType === 'daily') {
                $schedule = "{$minute} {$hour} * * *";
            } elseif ($runType === 'weekly') {
                $schedule = "{$minute} {$hour} * * 1"; // Default Monday
            } elseif ($runType === 'monthly') {
                $schedule = "{$minute} {$hour} 1 * *"; // First day
            } elseif ($runType === 'yearly') {
                $schedule = "{$minute} {$hour} 1 1 *"; // Jan 1st
            }
        }

        if (empty($command)) {
            echo json_encode(['success' => false, 'error' => 'Команда обязательна']);
            return;
        }

        $stmt = $this->db->prepare("INSERT INTO cron_jobs (workspace_id, command, schedule, description, email, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['www', $command, $schedule, $description, $email, $isActive]);

        try {
            $this->cronManager->sync('www');
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            // Rollback is not strictly needed in DB since if sync fails, the user can fix it.
            // But we can inform them.
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function toggle(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 0);

        $stmt = $this->db->prepare("UPDATE cron_jobs SET is_active = ? WHERE id = ? AND workspace_id = 'www'");
        $stmt->execute([$isActive, $id]);

        try {
            $this->cronManager->sync('www');
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function destroy(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int)($data['id'] ?? 0);

        $stmt = $this->db->prepare("DELETE FROM cron_jobs WHERE id = ? AND workspace_id = 'www'");
        $stmt->execute([$id]);

        try {
            $this->cronManager->sync('www');
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $this->db->prepare("SELECT * FROM cron_jobs WHERE id = ? AND workspace_id = 'www'");
        $stmt->execute([$id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            echo "Задание не найдено";
            return;
        }

        echo view('cron.edit', [
            'title' => 'Редактировать cron - SiteManager',
            'job' => $job
        ]);
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $command = trim($_POST['command'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        $noEmail = isset($_POST['no_email']);
        $email = trim($_POST['email'] ?? '');
        if ($noEmail) {
            $email = '""'; 
        }

        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // В edit.php мы используем только экспертный режим
        $schedule = trim($_POST['schedule_expert'] ?? '* * * * *');

        if (empty($command)) {
            echo json_encode(['success' => false, 'error' => 'Команда обязательна']);
            return;
        }

        try {
            $stmt = $this->db->prepare("UPDATE cron_jobs SET command = ?, schedule = ?, is_active = ?, email = ?, description = ? WHERE id = ? AND workspace_id = 'www'");
            $stmt->execute([$command, $schedule, $isActive, $email, $description, $id]);
            
            $this->cronManager->sync('www');
            
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function run(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $this->db->prepare("SELECT command FROM cron_jobs WHERE id = ? AND workspace_id = 'www'");
        $stmt->execute([$id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            echo json_encode(['success' => false, 'error' => 'Задание не найдено']);
            return;
        }
        
        $output = [];
        $returnVar = 0;
        // OSPanel environment wrapper execution
        exec($job['command'] . ' 2>&1', $output, $returnVar);
        
        echo json_encode([
            'success' => true, 
            'output' => implode("\n", $output),
            'status' => $returnVar
        ]);
    }
}
