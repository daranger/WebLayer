<?php

namespace App\Services;

use PDO;

class NotificationService {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getUnread(): array {
        $stmt = $this->db->prepare("SELECT * FROM notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 50");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markAsRead(int $id): bool {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function markAllAsRead(): bool {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
        return $stmt->execute();
    }

    public function create(string $title, string $message, string $type = 'info'): bool {
        $stmt = $this->db->prepare("INSERT INTO notifications (title, message, type) VALUES (:title, :message, :type)");
        return $stmt->execute([
            'title' => $title,
            'message' => $message,
            'type' => $type
        ]);
    }
}
