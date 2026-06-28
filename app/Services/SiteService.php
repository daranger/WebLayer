<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Exception;

class SiteService
{
    private PDO $db;
    private SystemManager $system;
    private FileManager $fileManager;
    private NginxManager $nginxManager;
    private SSLManager $sslManager;
    private CloudflareManager $cfManager;
    private NotificationService $notificationService;

    public function __construct(
        PDO $db,
        SystemManager $system,
        FileManager $fileManager,
        NginxManager $nginxManager,
        SSLManager $sslManager,
        CloudflareManager $cfManager,
        NotificationService $notificationService
    ) {
        $this->db = $db;
        $this->system = $system;
        $this->fileManager = $fileManager;
        $this->nginxManager = $nginxManager;
        $this->sslManager = $sslManager;
        $this->cfManager = $cfManager;
        $this->notificationService = $notificationService;
    }

    public function getAllSites(): array
    {
        $stmt = $this->db->query("SELECT id, domain FROM sites ORDER BY domain ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): bool
    {
        // Вытаскиваем данные из нашего нового payload джоба
        $domain      = $data['domain'];
        $runtime     = $data['runtime']; // Массив ['type' => 'php', 'version' => '8.3']
        $cms         = $data['cms'] ?? 'none';
        $sslOption   = $data['ssl'] ?? 'none';

        // Временно хардкодим 'www' для системного пользователя Linux, пока панель сингл-юзер
        $workspaceId = 'www';

        // Базовый путь до корня проекта и изолированный Document Root
        $rootPath    = "/var/www/workspaces/{$workspaceId}/{$domain}";
        $publicPath  = "{$rootPath}/public";

        // Флаги строгого каскадного отката
        $flags = [
            'linux_user_created' => false,
            'directory_created'  => false,
            'nginx_installed'    => false,
        ];

        try {
            // Шаг 1: Создаем Linux-пользователя (оставляем твою логику)
            if (!$this->system->createUser($workspaceId)) {
                throw new Exception("Не удалось создать системного пользователя Linux: {$workspaceId}");
            }
            $flags['linux_user_created'] = true;

            // Шаг 2: Создаем корень сайта, папку public и общую папку logs для workspace
            $workspaceLogs = "/var/www/workspaces/{$workspaceId}/logs";
            if (!$this->fileManager->makeDirectory($rootPath) ||
                !$this->fileManager->makeDirectory($publicPath) ||
                !$this->fileManager->makeDirectory($workspaceLogs)) {
                throw new Exception("Не удалось создать структуру директорий сайта: {$rootPath}");
            }
            $flags['directory_created'] = true;

            // Накатываем права на корень проекта
            chown($rootPath, $workspaceId);
            chgrp($rootPath, 'www-data');
            chmod($rootPath, 0770);

            // Права на общую папку logs
            @chown($workspaceLogs, $workspaceId);
            @chgrp($workspaceLogs, 'www-data');
            @chmod($workspaceLogs, 0770);

            // Шаг 2.5: Базовый контент для сайта
            if ($runtime['type'] === 'php' && $cms === 'wordpress') {
                // Здесь будет твой вызов скачивания и распаковки WP в $publicPath
                // $this->fileManager->deployWordPress($publicPath);
            } else {
                $htmlTemplate = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$domain} - Успешно создан!</title>
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f4f4f9; color: #333; display: flex; align-items: center; justify-content: center; height: 100vh; text-align: center; }
        .card { background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); max-width: 500px; width: 90%; }
        h1 { margin: 0 0 10px 0; font-size: 24px; color: #111; }
        .domain { color: #007bff; font-weight: 700; font-size: 28px; display: block; margin-bottom: 20px; word-break: break-all; }
        p { color: #666; line-height: 1.6; margin: 0 0 20px 0; }
        .code { background: #f8f9fa; padding: 4px 8px; border-radius: 6px; font-family: monospace; font-size: 14px; color: #d63384; }
        .badge { display: inline-block; padding: 6px 14px; font-size: 13px; font-weight: 600; border-radius: 50px; background: #e0e7ff; color: #4338ca; }
        .footer { margin-top: 30px; font-size: 13px; color: #aaa; }
        .footer b { color: #888; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Сайт успешно работает! 🎉</h1>
        <span class="domain">{$domain}</span>
        <p>Эта страница сгенерирована автоматически. Удалите этот файл и загрузите файлы вашего проекта в папку <span class="code">public</span>.</p>
        <div class="badge">{{BADGE}}</div>
        <div class="footer">Powered by <b>WebLayer</b> Panel</div>
    </div>
</body>
</html>
HTML;

                if ($runtime['type'] === 'static') {
                    $content = str_replace('{{BADGE}}', 'Static HTML', $htmlTemplate);
                    file_put_contents("{$publicPath}/index.html", $content);
                } elseif ($runtime['type'] === 'php') {
                    // Используем PHP для вывода актуальной версии
                    $content = str_replace('{{BADGE}}', 'PHP <?php echo phpversion(); ?>', $htmlTemplate);
                    file_put_contents("{$publicPath}/index.php", $content);
                }
            }

            // Шаг 3: Генерируем конфиг Nginx
            // Fetch latest site settings from db
            $stmt = $this->db->prepare("SELECT is_active, force_https FROM sites WHERE domain = ?");
            $stmt->execute([$domain]);
            $siteSettings = $stmt->fetch(PDO::FETCH_ASSOC);
            $isActive = $siteSettings ? (bool)$siteSettings['is_active'] : true;
            $forceHttps = $siteSettings ? (bool)$siteSettings['force_https'] : true;

            $configPath = $this->nginxManager->generateConfig([
                'domain'      => $domain,
                'rootDir'     => $publicPath,
                'runtime'     => $runtime,
                'is_active'   => $isActive,
                'force_https' => $forceHttps,
                'workspaceId' => $workspaceId,
                'template'    => 'default'
            ]);

            if (!$configPath || !$this->nginxManager->installSite($domain)) {
                throw new Exception("Ошибка валидации базовой конфигурации Nginx");
            }
            $flags['nginx_installed'] = true;

            // Шаг 4: Направляем DNS в Cloudflare (твоя логика без изменений)
            $serverIp = env('SERVER_IP', '127.0.0.1');
            if (env('CF_ZONE_ID')) {
                $this->cfManager->createDnsRecord(env('CF_ZONE_ID'), [
                    'type'    => 'A',
                    'name'    => $domain,
                    'content' => $serverIp,
                    'proxied' => false
                ]);
            }

            // Шаг 5: Долгий сетевой выпуск SSL
            $finalSslStatus = 'none';
            $sslLog = null;
            
            if ($sslOption === 'letsencrypt') {
                $sslResult = $this->sslManager->issue($domain, $publicPath, "webmaster@{$domain}");
                $sslLog = $sslResult['output'] ?? '';
                
                if (!$sslResult['success']) {
                    // Мы не выкидываем Exception, чтобы сайт создался (доступен по HTTP)
                    $finalSslStatus = 'failed';
                } else {
                    $finalSslStatus = 'active';
                    
                    // Шаг 6: Переписываем vhost Nginx на SSL только при успехе
                    $this->nginxManager->generateConfig([
                        'domain'      => $domain,
                        'rootDir'     => $publicPath,
                        'runtime'     => $runtime,
                        'is_active'   => $isActive ?? true,
                        'force_https' => $forceHttps ?? true,
                        'workspaceId' => $workspaceId,
                        'template'    => 'ssl'
                    ]);
                    $this->nginxManager->installSite($domain);
                }
            }

            // Шаг 7: Сохраняем стейт в БД
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE sites
                                        SET status = 'active', ssl_status = ?, ssl_log = ?
                                        WHERE id = ?");
            $stmt->execute([$finalSslStatus, $sslLog, $data['site_id']]);

            $this->notificationService->create(
                "Сайт создан",
                "Сайт {$domain} успешно создан и настроен.",
                "success"
            );

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            // Бескомпромиссный каскадный откат в случае падения
            if ($flags['nginx_installed']) {
                $this->nginxManager->removeSite($domain);
            }
            if ($flags['directory_created']) {
                $this->fileManager->removeDirectory($rootPath);
            }
            if ($flags['linux_user_created']) {
                if (!$this->hasOtherSitesInWorkspace($workspaceId)) {
                    $this->system->deleteUser($workspaceId);
                }
            }

            // Обновляем статус сайта в базе на 'error' и записываем текст ошибки
            try {
                $stmt = $this->db->prepare("UPDATE sites SET status = 'error', error_message = ? WHERE id = ?");
                $stmt->execute([$e->getMessage(), $data['site_id']]);
                
                $this->notificationService->create(
                    "Ошибка создания сайта",
                    "Сайт {$data['domain']} не был создан: " . $e->getMessage(),
                    "danger"
                );
            } catch (Exception $dbEx) {
                // Игнорируем, если даже база отвалилась, чтобы выбросить основное исключение ОС
            }

            throw $e;
        }
    }

    private function hasOtherSitesInWorkspace(string $workspaceId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM sites WHERE workspace_id = ? AND status = 'active'");
        $stmt->execute([$workspaceId]);
        return $stmt->fetchColumn() > 0;
    }

    public function rebuildConfig(array $data): bool
    {
        $domain      = $data['domain'];
        $runtime     = $data['runtime'] ?? ['type' => 'php', 'version' => '8.3']; // Фолбэк если не передан
        $sslOption   = $data['ssl'] ?? 'none';
        $workspaceId = 'www'; // Хардкодим 'www'

        $rootPath    = "/var/www/workspaces/{$workspaceId}/{$domain}";
        $publicPath  = "{$rootPath}/public";

        $stmt = $this->db->prepare("SELECT is_active, force_https, runtime_type, runtime_version FROM sites WHERE domain = ?");
        $stmt->execute([$domain]);
        $siteSettings = $stmt->fetch(PDO::FETCH_ASSOC);
        $isActive = $siteSettings ? (bool)$siteSettings['is_active'] : true;
        $forceHttps = $siteSettings ? (bool)$siteSettings['force_https'] : true;
        
        if ($siteSettings && isset($siteSettings['runtime_type'])) {
             $runtime['type'] = $siteSettings['runtime_type'];
             $runtime['version'] = $siteSettings['runtime_version'];
        }

        if ($sslOption === 'letsencrypt') {
            $configPath = $this->nginxManager->generateConfig([
                'domain'      => $domain,
                'rootDir'     => $publicPath,
                'runtime'     => $runtime,
                'is_active'   => $isActive,
                'force_https' => $forceHttps,
                'workspaceId' => $workspaceId,
                'template'    => 'ssl'
            ]);
        } else {
            $configPath = $this->nginxManager->generateConfig([
                'domain'      => $domain,
                'rootDir'     => $publicPath,
                'runtime'     => $runtime,
                'is_active'   => $isActive,
                'force_https' => $forceHttps,
                'workspaceId' => $workspaceId,
                'template'    => 'default'
            ]);
        }

        if (!$configPath || !$this->nginxManager->installSite($domain)) {
            throw new Exception("Не удалось обновить конфигурацию Nginx для {$domain}");
        }

        if (!$this->system->restartService('nginx')) {
            throw new Exception("Не удалось перезапустить Nginx");
        }

        $this->notificationService->create(
            "Конфигурация сайта {$domain} успешно обновлена.",
            "Nginx конфиг перестроен",
            "success"
        );

        return true;
    }
}