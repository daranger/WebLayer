<?php

namespace App\Controllers;

use App\Services\NotificationService;
use App\Core\Container;
use PDO;
use Exception;

class NotificationController {
    private NotificationService $notificationService;

    public function __construct() {
        $container = Container::getInstance();
        $this->notificationService = new NotificationService($container->make(PDO::class));
    }

    public function index() {
        try {
            $notifications = $this->notificationService->getUnread();
            echo json_encode([
                'success' => true,
                'data' => $notifications
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function read() {
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            if (isset($data['id'])) {
                $this->notificationService->markAsRead((int)$data['id']);
            } else {
                $this->notificationService->markAllAsRead();
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
