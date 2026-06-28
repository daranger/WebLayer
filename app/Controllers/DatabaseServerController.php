<?php

namespace App\Controllers;

use App\Repositories\DatabaseServerRepository;
use PDO;

class DatabaseServerController
{
    private DatabaseServerRepository $repo;

    public function __construct(DatabaseServerRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(): void
    {
        $servers = $this->repo->getAll();
        
        if (empty($servers)) {
            $rootPass = function_exists('env') ? env('MYSQL_ROOT_PASS', '') : '';
            $this->repo->create([
                'name' => 'Local MySQL',
                'type' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'username' => 'root',
                'password' => $rootPass,
                'remote_access' => 0
            ]);
            $servers = $this->repo->getAll();
        }

        echo view('database_servers.index', [
            'title' => 'Серверы баз данных',
            'servers' => $servers
        ]);
    }

    public function create(): void
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['name'])) {
            echo json_encode(['success' => false, 'error' => 'Имя сервера обязательно']);
            return;
        }

        try {
            $this->repo->create($input);
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function update(): void
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['name'])) {
            echo json_encode(['success' => false, 'error' => 'Имя сервера обязательно']);
            return;
        }

        try {
            $id = (int) $input['id'];
            $server = $this->repo->getById($id);
            
            if (isset($input['password']) && $input['password'] !== '' && $input['password'] !== $server['password']) {
                $newPassword = $input['password'];
                $oldPassword = $server['password'];
                
                $dsn = $server['type'] === 'postgres' 
                    ? "pgsql:host={$server['host']};port={$server['port']}"
                    : "mysql:host={$server['host']};port={$server['port']};charset=utf8mb4";
                
                try {
                    $pdo = new PDO($dsn, $server['username'], $oldPassword, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 2
                    ]);
                } catch (\PDOException $e) {
                    throw new \Exception("Не удалось подключиться к серверу БД со старым паролем, чтобы обновить его: " . $e->getMessage());
                }
                
                if (isset($pdo)) {
                    if ($server['type'] === 'mysql') {
                        $u = $server['username'];
                        // Пробуем обновить для всех возможных вариантов хоста
                        $hosts = ['localhost', '127.0.0.1', '::1', '%'];
                        $changed = false;
                        $lastError = null;
                        
                        foreach ($hosts as $h) {
                            try {
                                $stmt = $pdo->prepare("ALTER USER '$u'@'$h' IDENTIFIED BY :pass");
                                $stmt->execute(['pass' => $newPassword]);
                                $changed = true;
                            } catch (\Exception $e) {
                                $lastError = $e;
                            }
                        }
                        
                        // Если ни один хост не обновился, и есть ошибка - пробрасываем её
                        if (!$changed && $lastError) {
                            throw $lastError;
                        }
                        
                        $pdo->exec("FLUSH PRIVILEGES");
                    } else if ($server['type'] === 'postgres') {
                        $stmt = $pdo->prepare("ALTER USER postgres WITH PASSWORD :pass");
                        $stmt->execute(['pass' => $newPassword]);
                    }
                }
            } else {
                if (empty($input['password'])) {
                    unset($input['password']);
                }
            }

            $input = array_merge($server, $input);
            $this->repo->update($id, $input);
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function delete(): void
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        try {
            $id = (int) $input['id'];
            $this->repo->delete($id);
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    public function get(): void
    {
        header('Content-Type: application/json');
        try {
            $id = (int) ($_GET['id'] ?? 0);
            $server = $this->repo->getById($id);
            if (!$server) {
                echo json_encode(['success' => false, 'error' => 'Сервер не найден']);
                return;
            }
            // Пароль обычно не отдают в открытом виде для редактирования, но если панель админская...
            // Отдадим, чтобы он подставился в инпут. Либо можно отдавать плейсхолдер.
            echo json_encode(['success' => true, 'data' => $server]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function testConnection(): void
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        
        $host = $input['host'] ?? 'localhost';
        $port = $input['port'] ?? 3306;
        $username = $input['username'] ?? 'root';
        $password = $input['password'] ?? '';
        $type = $input['type'] ?? 'mysql';
        
        try {
            if ($type === 'mysql') {
                $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
            } else if ($type === 'postgres') {
                $dsn = "pgsql:host=$host;port=$port";
            } else {
                throw new \Exception("Unsupported database type");
            }
            
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3 // таймаут 3 секунды
            ]);
            
            // Получаем версию
            $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            
            echo json_encode(['success' => true, 'version' => $version]);
        } catch (\PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Ошибка подключения: ' . $e->getMessage()]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function migrate(): void
    {
        try {
            $sql = "
            CREATE TABLE IF NOT EXISTS `database_servers` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `type` VARCHAR(50) NOT NULL DEFAULT 'mysql',
                `host` VARCHAR(255) NOT NULL DEFAULT 'localhost',
                `port` INT NOT NULL DEFAULT 3306,
                `username` VARCHAR(255) NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `remote_access` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            // use reflection to get PDO from repo
            $ref = new \ReflectionClass($this->repo);
            $prop = $ref->getProperty('db');
            $prop->setAccessible(true);
            $db = $prop->getValue($this->repo);
            $db->exec($sql);
            echo "Migrated successfully";
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }


}
