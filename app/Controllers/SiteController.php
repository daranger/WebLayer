<?php

declare(strict_types=1);

namespace App\Controllers;

use Predis\ClientInterface;
use PDO;

class SiteController
{
    private ClientInterface $redis;
    private PDO $db;

    public function __construct(ClientInterface $redis, PDO $db)
    {
        $this->redis = $redis;
        $this->db = $db;
    }

    private function requireAuth(): void
    {
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjax) {
            $token = $_SERVER['HTTP_X_PANEL_TOKEN'] ?? '';
            if (empty($token) || !$this->redis->exists("session:{$token}")) {
                http_response_code(401);
                exit;
            }
        } else {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (!isset($_SESSION['user_logged']) || $_SESSION['user_logged'] !== true) {
                header('Location: /login');
                exit;
            }
        }
    }

    public function index(): void
    {
        $this->requireAuth();

        $stmt = $this->db->query("SELECT * FROM sites");
        $sites = $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $serverIp = $isWindows ? '127.0.0.1' : env('SERVER_IP', $_SERVER['SERVER_ADDR'] ?? '127.0.0.1');

        echo view('sites', [
            'title' => 'Сайты - SiteManager',
            'sites' => $sites,
            'serverIp' => $serverIp
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        
        $phpVer = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

        echo view('sites_create', [
            'title' => 'Новый сайт - SiteManager',
            'phpVer' => $phpVer
        ]);
    }

    public function edit(): void
    {
        $this->requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $this->db->prepare("SELECT * FROM sites WHERE id = ?");
        $stmt->execute([$id]);
        $site = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$site) {
            header('Location: /sites');
            exit;
        }

        $phpVer = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

        echo view('sites_edit', [
            'title' => 'Редактировать сайт - SiteManager',
            'site' => $site,
            'phpVer' => $phpVer
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $domain = isset($input['domain']) ? trim((string)$input['domain']) : '';

        // 🔥 Переходим на разбор нового объекта runtime
        $runtimeType    = $input['runtime']['type'] ?? 'php';
        $runtimeVersion = $input['runtime']['version'] ?? (PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION);
        $cms            = $input['cms'] ?? 'none';
        $ssl            = $input['ssl'] ?? 'none';

        $rootPath = '/var/www/workspaces/www/' . $domain;

        // Валидация домена (твоя отличная логика)
        if (empty($domain) || !filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Некорректный формат домена. Например: mysite.com или тест.рф']);
            return;
        }

        if (!str_contains($domain, '.') && $domain != 'localhost') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Доменное имя должно содержать зону (например, .com, .ru, .uz)']);
            return;
        }

        $allowedPrefix = '/var/www/workspaces/';

        if (str_contains($domain, '..') || str_contains($domain, '/') || str_contains($domain, '\\')) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Попытка обхода директории или некорректные символы в домене']);
            return;
        }

        if (!str_starts_with($rootPath, $allowedPrefix) || rtrim($rootPath, '/') === rtrim($allowedPrefix, '/')) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Критическая ошибка безопасности: Недопустимый путь для сайта']);
            return;
        }

        $checkStmt = $this->db->prepare("SELECT id FROM sites WHERE domain = ?");
        $checkStmt->execute([$domain]);
        if ($checkStmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Сайт с таким доменным именем уже добавлен в панель']);
            return;
        }

        try {
            $this->db->beginTransaction();

            // 🔥 ФИКС БАЗЫ: Пишем в новые колонки. created_at убрали — MySQL заполнит сама!
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $siteIp = $isWindows ? '127.0.0.1' : env('SERVER_IP', $_SERVER['SERVER_ADDR'] ?? '127.0.0.1');

            $stmt = $this->db->prepare("
                INSERT INTO sites (workspace_id, domain, runtime_type, runtime_version, cms, ssl_status, root_path, status, ip_address) 
                VALUES ('www', ?, ?, ?, ?, 'none', ?, 'pending', ?)
            ");
            $stmt->execute([$domain, $runtimeType, $runtimeVersion, $cms, $rootPath, $siteIp]);
            $siteId = (int)$this->db->lastInsertId();

            $jobId = bin2hex(random_bytes(16));

            // 🔥 ФИКС ОЧЕРЕДИ: Исправили $data на $input и упаковали чистый payload
            $jobData = [
                'id'      => $jobId,
                'job'     => \App\Jobs\CreateSiteJob::class,
                'payload' => [
                    'site_id'   => $siteId,
                    'domain'    => $domain,
                    'runtime'   => [
                        'type'    => $runtimeType,
                        'version' => $runtimeVersion
                    ],
                    'cms'       => $cms,
                    'ssl'       => $ssl,
                    'root_path' => $rootPath
                ]
            ];

            // Наш изначальный rPush большими буквами
            $this->redis->rPush('queue:default', json_encode($jobData));

            $this->db->commit();

            http_response_code(202);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Сайт успешно добавлен в очередь на развертывание',
                'job_id'  => $jobId
            ]);

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            // Убрали дебажный print_r() и die(), чтобы логирование в Docker работало как надо
            error_log("[Критическая ошибка очереди]: " . $e->getMessage());

            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error'   => 'Система очередей временно недоступна. Пожалуйста, повторите попытку позже.'
            ]);
        }
    }

    /**
     * DELETE /api/sites
     * Надежный перевод в статус deleting и постановка в очередь
     */
    public function destroy(): void
    {
        $this->requireAuth();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $siteId = (int)($input['id'] ?? 0);

        if ($siteId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Неверный ID сайта']);
            return;
        }

        // 1. Ищем сайт в базе данных
        $stmt = $this->db->prepare("SELECT * FROM sites WHERE id = ?");
        $stmt->execute([$siteId]);
        $site = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$site) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Сайт не найден']);
            return;
        }

        try {
            // 2. ЖЕЛЕЗНО МЕНЯЕМ СТАТУС В БАЗЕ СРАЗУ (без общей транзакции с Redis)
            $update = $this->db->prepare("UPDATE sites SET status = 'deleting' WHERE id = ?");
            $update->execute([$siteId]);

            // 3. Собираем и пушим задачу в Redis
            $jobId = bin2hex(random_bytes(16));
            $jobData = [
                'id'         => $jobId,
                'job'        => \App\Jobs\DeleteSiteJob::class,
                'created_at' => time(),
                'payload'    => [
                    'site_id' => $site->id,
                    'domain'  => $site->domain
                ]
            ];

            $this->redis->rPush('queue:default', json_encode($jobData));

            // Отдаем фронтенду 200/202 статус успеха
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Задача на удаление поставлена в очередь',
                'job_id'  => $jobId
            ]);

        } catch (\Throwable $e) {
            // АВТО-ОТКАТ: Если Редис чихнул на rPush, возвращаем статус в active
            $rollback = $this->db->prepare("UPDATE sites SET status = 'active' WHERE id = ?");
            $rollback->execute([$siteId]);

            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error'   => "Ошибка очереди: " . $e->getMessage()
            ]);
        }
    }

    public function update(): void
    {
        $this->requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $siteId = (int)($input['id'] ?? 0);
        $forceHttps = isset($input['force_https']) ? (int)$input['force_https'] : 1;

        $stmt = $this->db->prepare("SELECT * FROM sites WHERE id = ?");
        $stmt->execute([$siteId]);
        $site = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$site) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Сайт не найден']);
            return;
        }

        // Just update database for now, and maybe run a job to update Nginx
        // We will just do a quick DB update and trigger a Nginx rewrite via Job later if needed
        $update = $this->db->prepare("UPDATE sites SET force_https = ? WHERE id = ?");
        $update->execute([$forceHttps, $siteId]);

        // Trigger job to recreate config if needed (we can reuse CreateSiteJob or a new RebuildSiteJob)
        // For simplicity, we just trigger CreateSiteJob again to rewrite the config
        $jobId = bin2hex(random_bytes(16));
        $jobData = [
            'id'      => $jobId,
            'job'     => \App\Jobs\RebuildSiteConfigJob::class,
            'payload' => [
                'site_id'   => $site->id,
                'domain'    => $site->domain,
                'runtime'   => [
                    'type'    => $site->runtime_type,
                    'version' => $site->runtime_version
                ],
                'cms'       => $site->cms,
                'ssl'       => $site->ssl_status === 'active' ? 'letsencrypt' : 'none',
                'root_path' => $site->root_path
            ]
        ];
        $this->redis->rPush('queue:default', json_encode($jobData));

        echo json_encode(['success' => true, 'message' => 'Настройки обновлены. Конфигурация пересоздается.']);
    }

    public function toggle(): void
    {
        $this->requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $siteId = (int)($input['id'] ?? 0);

        $stmt = $this->db->prepare("SELECT * FROM sites WHERE id = ?");
        $stmt->execute([$siteId]);
        $site = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$site) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Сайт не найден']);
            return;
        }

        $newStatus = $site->is_active ? 0 : 1;
        $this->db->prepare("UPDATE sites SET is_active = ? WHERE id = ?")->execute([$newStatus, $siteId]);

        // Rebuild config via queue
        $jobId = bin2hex(random_bytes(16));
        $jobData = [
            'id'      => $jobId,
            'job'     => \App\Jobs\RebuildSiteConfigJob::class,
            'payload' => [
                'site_id'   => $site->id,
                'domain'    => $site->domain,
                'runtime'   => [
                    'type'    => $site->runtime_type,
                    'version' => $site->runtime_version
                ],
                'cms'       => $site->cms,
                'ssl'       => $site->ssl_status === 'active' ? 'letsencrypt' : 'none',
                'root_path' => $site->root_path
            ]
        ];
        $this->redis->rPush('queue:default', json_encode($jobData));

        echo json_encode(['success' => true, 'message' => $newStatus ? 'Сайт включен' : 'Сайт отключен']);
    }

    public function getConfig(): void
    {
        $this->requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        
        $stmt = $this->db->prepare("SELECT domain FROM sites WHERE id = ?");
        $stmt->execute([$id]);
        $domain = $stmt->fetchColumn();

        if (!$domain) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Сайт не найден']);
            return;
        }

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $storagePath = realpath(__DIR__ . '/../../storage');
        $configPath = $isWindows 
            ? "{$storagePath}/nginx/{$domain}.conf" 
            : "/etc/nginx/sites-available/{$domain}.conf";
        
        // Use root helper to read
        $helperPath = realpath(__DIR__ . '/../../bin/root_helper.php');
        $cmd = $isWindows 
            ? "php " . escapeshellarg($helperPath)
            : "sudo php " . escapeshellarg($helperPath);
        $inputData = json_encode(['action' => 'read', 'path' => $configPath]);
        
        $process = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ], $pipes);
        
        if (is_resource($process)) {
            fwrite($pipes[0], $inputData);
            fclose($pipes[0]);
            
            $outRead = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            
            $errRead = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            
            proc_close($process);
            
            $res = json_decode($outRead, true);
            if (!$res || empty($res['success'])) {
                $errorMsg = $res['error'] ?? 'Unknown error. STDOUT: ' . substr($outRead, 0, 200) . ' STDERR: ' . substr($errRead, 0, 200);
                echo json_encode(['success' => false, 'error' => 'Не удалось прочитать конфигурацию: ' . $errorMsg]);
                return;
            }
            
            echo json_encode(['success' => true, 'content' => base64_decode($res['content'])]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to execute root helper']);
        }
    }

    public function saveConfig(): void
    {
        $this->requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $siteId = (int)($input['id'] ?? 0);
        $content = $input['content'] ?? '';

        $stmt = $this->db->prepare("SELECT domain FROM sites WHERE id = ?");
        $stmt->execute([$siteId]);
        $domain = $stmt->fetchColumn();

        if (!$domain) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Сайт не найден']);
            return;
        }

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $storagePath = realpath(__DIR__ . '/../../storage');
        $configPath = $isWindows 
            ? "{$storagePath}/nginx/{$domain}.conf" 
            : "/etc/nginx/sites-available/{$domain}.conf";
            
        $helperPath = realpath(__DIR__ . '/../../bin/root_helper.php');
        
        $cmd = $isWindows 
            ? "php " . escapeshellarg($helperPath)
            : "sudo php " . escapeshellarg($helperPath);
            
        // 1. Читаем старый конфиг
        $processRead = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $oldContentBase64 = null;
        if (is_resource($processRead)) {
            fwrite($pipes[0], json_encode(['action' => 'read', 'path' => $configPath]));
            fclose($pipes[0]);
            $outRead = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $errRead = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            proc_close($processRead);
            $resRead = json_decode($outRead, true);
            if ($resRead && isset($resRead['content'])) {
                $oldContentBase64 = $resRead['content'];
            }
        }
        
        // 2. Сохраняем новый конфиг
        $inputData = json_encode(['action' => 'save', 'path' => $configPath, 'content' => base64_encode($content)]);
        $process = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        
        if (is_resource($process)) {
            fwrite($pipes[0], $inputData);
            fclose($pipes[0]);
            $saveOutput = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $saveErr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            proc_close($process);
            
            $resSave = json_decode($saveOutput, true);
            if (!$resSave || empty($resSave['success'])) {
                $errorMsg = $resSave['error'] ?? 'Unknown error. STDOUT: ' . substr($saveOutput, 0, 200) . ' STDERR: ' . substr($saveErr, 0, 200);
                echo json_encode(['success' => false, 'error' => $errorMsg]);
                return;
            }
            
            // 3. Проверяем валидность Nginx
            $processCheck = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $checkOutput = '';
            if (is_resource($processCheck)) {
                fwrite($pipes[0], json_encode(['action' => 'check_nginx']));
                fclose($pipes[0]);
                $checkOutput = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($processCheck);
            }
            
            $resCheck = json_decode($checkOutput, true);
            if (!$resCheck || !isset($resCheck['success']) || !$resCheck['success']) {
                // Восстанавливаем старый конфиг
                if ($oldContentBase64 !== null) {
                    $processRestore = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                    if (is_resource($processRestore)) {
                        fwrite($pipes[0], json_encode(['action' => 'save', 'path' => $configPath, 'content' => $oldContentBase64]));
                        fclose($pipes[0]);
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        proc_close($processRestore);
                    }
                }
                
                $errorMessage = $resCheck['error'] ?? 'Неизвестная ошибка при проверке Nginx конфига';
                echo json_encode(['success' => false, 'error' => $errorMessage]);
                return;
            }
            
            // 4. Перезагружаем Nginx
            exec("sudo systemctl reload nginx");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to execute root helper']);
        }
    }
}