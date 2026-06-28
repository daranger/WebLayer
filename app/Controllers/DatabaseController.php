<?php

namespace App\Controllers;

use App\Core\Container;
use App\Services\DatabaseService;
use PDO;
use Exception;

class DatabaseController
{
    private DatabaseService $databaseService;
    private PDO $db;

    public function __construct()
    {
        $container = Container::getInstance();
        
        $this->databaseService = $container->make(DatabaseService::class);
        $this->db = $container->make(PDO::class);

        $this->autoMigrate();
    }

    private function autoMigrate(): void
    {
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS `databases` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `site_domain` varchar(255) DEFAULT NULL,
                `site_id` int(11) DEFAULT NULL,
                `db_name` varchar(255) NOT NULL,
                `db_user` varchar(255) NOT NULL,
                `db_pass` varchar(255) DEFAULT NULL,
                `db_password_hash` varchar(255) DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `db_name` (`db_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Add missing columns if upgrading from old version
            try {
                $this->db->exec("ALTER TABLE `databases` ADD COLUMN `site_id` int(11) DEFAULT NULL");
            } catch (\Exception $e) {}
            
            try {
                $this->db->exec("ALTER TABLE `databases` ADD COLUMN `db_password_hash` varchar(255) DEFAULT NULL");
            } catch (\Exception $e) {}
            
            try {
                $this->db->exec("ALTER TABLE `databases` ADD COLUMN `server_id` int(11) DEFAULT NULL");
            } catch (\Exception $e) {}

        } catch (\Exception $e) {
            // Ignore if columns already exist or other errors
        }
    }

    public function index(): void
    {
        $databases = $this->databaseService->getAllDatabases();

        echo view('databases', [
            'title' => 'Базы данных - SiteManager',
            'databases' => $databases
        ]);
    }

    public function create(): void
    {
        $stmt = $this->db->query("SELECT id, domain FROM sites ORDER BY domain ASC");
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        $stmt2 = $this->db->query("SELECT id, name, type FROM database_servers ORDER BY name ASC");
        $servers = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        if (empty($servers)) {
            // Auto-seed default OSPanel MySQL server
            $rootPass = function_exists('env') ? env('MYSQL_ROOT_PASS', '') : '';
            $insertStmt = $this->db->prepare("INSERT INTO database_servers (name, type, host, port, username, password, remote_access) VALUES ('Local MySQL', 'mysql', '127.0.0.1', 3306, 'root', ?, 0)");
            $insertStmt->execute([$rootPass]);
            
            $stmt2 = $this->db->query("SELECT id, name, type FROM database_servers ORDER BY name ASC");
            $servers = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        echo view('databases_create', [
            'title' => 'Новая база данных - SiteManager',
            'sites' => $sites,
            'servers' => $servers
        ]);
    }

    public function store(): void
    {
        try {
            $dbName = $_POST['db_name'] ?? '';
            $dbUser = $_POST['db_user'] ?? '';
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';
            $siteId = !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null;
            $serverId = !empty($_POST['db_server']) ? (int)$_POST['db_server'] : null;

            if (empty($dbName) || empty($dbUser) || empty($password)) {
                throw new Exception('Необходимо заполнить все обязательные поля.');
            }
            if (empty($serverId)) {
                throw new Exception('У вас не добавлен ни один сервер баз данных, либо вы его не выбрали. Перейдите в раздел "Серверы баз данных" и добавьте сервер.');
            }

            if ($password !== $passwordConfirm) {
                throw new Exception('Пароли не совпадают');
            }

            $result = $this->databaseService->createDatabase($dbName, $dbUser, $password, $siteId, $serverId);

            echo json_encode([
                'success' => true,
                'message' => 'База данных успешно создана',
                'data' => $result
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function destroy(): void
    {
        try {
            // Получаем JSON payload
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            $id = isset($data['id']) ? (int)$data['id'] : 0;

            if ($id <= 0) {
                throw new Exception('Некорректный ID базы данных');
            }

            $this->databaseService->deleteDatabase($id);

            echo json_encode([
                'success' => true,
                'message' => 'База данных успешно удалена'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
