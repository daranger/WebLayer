<?php

namespace App\Controllers;

use App\Core\Container;
use App\Services\ServiceManager;
use Predis\ClientInterface;

class ServiceController
{
    private ServiceManager $serviceManager;
    private ClientInterface $redis;

    public function __construct()
    {
        $container = Container::getInstance();
        $this->serviceManager = new ServiceManager();
        $this->redis = $container->make(ClientInterface::class);
    }

    public function index(): void
    {
        // Загружаем список важных служб для панели из конфигурации
        $configFile = __DIR__ . '/../../config/services.php';
        if (file_exists($configFile)) {
            $servicesList = require $configFile;
        } else {
            // Фолбэк на случай если файла нет
            $servicesList = [
                'nginx' => 'Nginx (Web-сервер)',
                'php8.3-fpm' => 'PHP 8.3 FPM',
                'mariadb' => 'MariaDB (База данных)',
                'redis-server' => 'Redis (Кэш и очереди)'
            ];
        }

        $services = [];
        foreach ($servicesList as $key => $name) {
            // Проверяем реальное наличие службы в системе перед добавлением в список
            if (!$this->serviceManager->isInstalled($key)) {
                continue;
            }
            
            $services[] = [
                'key' => $key,
                'name' => $name,
                'is_active' => $this->serviceManager->isActive($key)
            ];
        }

        echo view('services.index', [
            'title' => 'Службы',
            'services' => $services
        ]);
    }

    public function apiControl(): void
    {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $service = $input['service'] ?? '';
        $action = $input['action'] ?? '';

        if (empty($service) || empty($action)) {
            echo json_encode(['success' => false, 'error' => 'Не указана служба или действие']);
            return;
        }

        $validActions = ['start', 'stop', 'restart', 'reload'];
        if (!in_array($action, $validActions)) {
            echo json_encode(['success' => false, 'error' => 'Недопустимое действие']);
            return;
        }

        // Выполняем действие синхронно через root_helper
        try {
            $result = $this->serviceManager->control($service, $action);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Не удалось выполнить команду (возможно служба не существует или нет прав)']);
            }
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()]);
        }
        
        // Пытаемся завершить HTTP запрос ДО того как скрипт умрет из-за рестарта PHP/Nginx
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    public function reboot(): void
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        if ($isWindows) {
            echo json_encode(['success' => true]); // Mock
            return;
        }

        $helperPath = realpath(__DIR__ . '/../../bin/root_helper.php');
        $payload = json_encode(['action' => 'system_reboot']);
        
        $cmd = "sudo php " . escapeshellarg($helperPath);
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];
        
        $process = proc_open($cmd, $descriptorspec, $pipes);
        if (is_resource($process)) {
            fwrite($pipes[0], $payload);
            fclose($pipes[0]);
            
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            proc_close($process);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Не удалось выполнить команду перезагрузки']);
        }
    }
}
