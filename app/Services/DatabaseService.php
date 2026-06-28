<?php

namespace App\Services;

use PDO;
use Exception;
use App\Contracts\DatabaseManagerInterface;

class DatabaseService
{
    private PDO $pdo;
    private DatabaseManagerInterface $dbManager;

    public function __construct(PDO $pdo, DatabaseManagerInterface $dbManager)
    {
        $this->pdo = $pdo;
        $this->dbManager = $dbManager;
    }

    private function getServerManager(?int $serverId): DatabaseManagerInterface
    {
        if (!$serverId) {
            return $this->dbManager;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM database_servers WHERE id = ?");
        $stmt->execute([$serverId]);
        $server = $stmt->fetch();
        if (!$server) {
            throw new Exception("Привязанный сервер баз данных не найден.");
        }

        $dsn = $server['type'] === 'postgres' 
            ? "pgsql:host={$server['host']};port={$server['port']}"
            : "mysql:host={$server['host']};port={$server['port']};charset=utf8mb4";
        
        try {
            $serverPdo = new PDO($dsn, $server['username'], $server['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
        } catch (\PDOException $e) {
            throw new Exception("Ошибка подключения к серверу баз данных (возможно, неверный пароль в панели): " . $e->getMessage());
        }

        $managerClass = ($server['type'] === 'postgres') 
            ? \App\Services\Database\PostgreSQLManager::class 
            : \App\Services\Database\MySQLManager::class;
        return new $managerClass($serverPdo);
    }

    public function getAllDatabases(): array
    {
        $stmt = $this->pdo->query("
            SELECT d.*, s.domain as site_domain, ds.name as server_name 
            FROM `databases` d 
            LEFT JOIN sites s ON d.site_id = s.id 
            LEFT JOIN database_servers ds ON d.server_id = ds.id
            ORDER BY d.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    public function createDatabase(string $dbName, string $dbUser, string $password, ?int $siteId = null, ?int $serverId = null): array
    {
        $manager = $this->getServerManager($serverId);

        // 1. Create DB in MySQL
        if (!$manager->createDatabase($dbName)) {
            throw new Exception("Не удалось создать базу данных на сервере. Проверьте правильность имени (только латиница, цифры и подчеркивание).");
        }

        // 2. Create User in MySQL
        if (!$manager->createUser($dbUser, $password)) {
            $manager->deleteDatabase($dbName);
            throw new Exception("Не удалось создать пользователя на сервере.");
        }

        // 3. Grant Privileges
        if (!$manager->grantPrivileges($dbName, $dbUser)) {
            $manager->deleteUser($dbUser);
            $manager->deleteDatabase($dbName);
            throw new Exception("Не удалось выдать права пользователю на сервере.");
        }

        // 4. Save to Panel DB
        $stmt = $this->pdo->prepare("INSERT INTO `databases` (db_name, db_user, db_pass, site_id, server_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$dbName, $dbUser, $password, $siteId, $serverId]);
        
        return [
            'id' => $this->pdo->lastInsertId(),
            'db_name' => $dbName,
            'db_user' => $dbUser
        ];
    }

    public function deleteDatabase(int $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `databases` WHERE id = ?");
        $stmt->execute([$id]);
        $db = $stmt->fetch();

        if (!$db) {
            throw new Exception("База данных не найдена в панели.");
        }

        $manager = $this->getServerManager($db['server_id'] ?? null);

        // Ошибки при удалении игнорируем (возможно, уже удалены вручную)
        try { $manager->deleteDatabase($db['db_name']); } catch(Exception $e) {}
        try { $manager->deleteUser($db['db_user']); } catch(Exception $e) {}

        // Remove from Panel DB
        $delStmt = $this->pdo->prepare("DELETE FROM `databases` WHERE id = ?");
        return $delStmt->execute([$id]);
    }
}
