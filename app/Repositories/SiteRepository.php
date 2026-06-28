<?php

namespace App\Repositories;

use PDO;

class SiteRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function beginTransaction(): void { $this->db->beginTransaction(); }
    public function commit(): void { $this->db->commit(); }
    public function rollBack(): void { $this->db->rollBack(); }

    public function exists(string $domain): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM sites WHERE domain = ? LIMIT 1");
        $stmt->execute([$domain]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * 🔥 НАШ НОВЫЙ ИНСЕРТ ПО КАНОНУ
     * Ожидает ключи: domain, runtime_type, runtime_version, cms, root_path
     */
    public function insert(array $data): bool
    {
        // Поле workspace_id у тебя NOT NULL, временно пишем туда 'www'
        $sql = "INSERT INTO sites (workspace_id, domain, runtime_type, runtime_version, cms, ssl_status, root_path, status)
                VALUES ('www', :domain, :runtime_type, :runtime_version, :cms, 'none', :root_path, 'pending')";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'domain'          => $data['domain'],
            'runtime_type'    => $data['runtime_type'],
            'runtime_version' => $data['runtime_version'],
            'cms'             => $data['cms'] ?? 'none',
            'root_path'       => $data['root_path']
        ]);
    }

    public function getByDomain(string $domain): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM sites WHERE domain = ? LIMIT 1");
        $stmt->execute([$domain]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * 🔥 ОБНОВЛЕННЫЙ UPDATE ПОД НОВЫЕ ПОЛЯ
     */
    public function update(string $domain, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE sites 
            SET runtime_type = :runtime_type, 
                runtime_version = :runtime_version, 
                cms = :cms,
                root_path = :root_path 
            WHERE domain = :domain
        ");

        return $stmt->execute([
            'runtime_type'    => $data['runtime_type'],
            'runtime_version' => $data['runtime_version'],
            'cms'             => $data['cms'] ?? 'none',
            'root_path'       => $data['root_path'],
            'domain'          => $domain
        ]);
    }

    public function updateStatus(string $domain, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE sites SET status = ? WHERE domain = ?");
        return $stmt->execute([$status, $domain]);
    }

    /**
     * Запись ошибки от Docker-воркера напрямую в строку сайта
     */
    public function updateError(string $domain, string $errorMessage): bool
    {
        $stmt = $this->db->prepare("UPDATE sites SET status = 'error', error_message = ? WHERE domain = ?");
        return $stmt->execute([$errorMessage, $domain]);
    }

    public function delete(string $domain): bool
    {
        $stmt = $this->db->prepare("DELETE FROM sites WHERE domain = ?");
        return $stmt->execute([$domain]);
    }
}