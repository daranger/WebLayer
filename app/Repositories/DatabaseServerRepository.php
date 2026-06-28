<?php

namespace App\Repositories;

use PDO;

class DatabaseServerRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM database_servers ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM database_servers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): bool
    {
        $sql = "INSERT INTO database_servers (name, type, host, port, username, password, remote_access) 
                VALUES (:name, :type, :host, :port, :username, :password, :remote_access)";
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            'name' => $data['name'],
            'type' => $data['type'] ?? 'mysql',
            'host' => $data['host'] ?? 'localhost',
            'port' => $data['port'] ?? 3306,
            'username' => $data['username'] ?? 'root',
            'password' => $data['password'] ?? '',
            'remote_access' => $data['remote_access'] ?? 0
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE database_servers 
                SET name = :name, 
                    type = :type, 
                    host = :host, 
                    port = :port, 
                    username = :username, 
                    password = :password, 
                    remote_access = :remote_access 
                WHERE id = :id";
                
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            'name' => $data['name'],
            'type' => $data['type'] ?? 'mysql',
            'host' => $data['host'] ?? 'localhost',
            'port' => $data['port'] ?? 3306,
            'username' => $data['username'],
            'password' => $data['password'],
            'remote_access' => $data['remote_access'] ?? 0,
            'id' => $id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM database_servers WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
