<?php

namespace App\Services;

use App\Repositories\SiteRepository;
use Exception;

class WebsiteManager
{
    private SiteRepository $repository;
    private NginxManager $nginx;
    private FileManager $fileManager;

    public function __construct(
        SiteRepository $repository,
        NginxManager $nginx,
        FileManager $fileManager
    ) {
        $this->repository = $repository;
        $this->nginx = $nginx;
        $this->fileManager = $fileManager;
    }
    /**
     * Включение SSL для существующего сайта
     */
    public function enableSSL(string $domain, string $email = 'admin@localhost'): bool
    {
        // 1. Получаем данные сайта из репозитория
        $site = $this->repository->getByDomain($domain);
        if (!$site) return false;

        // 2. Пытаемся выпустить сертификат через Certbot
        $sslResult = $this->sslManager->issue($domain, $site['root_dir'], $email);
        $sslLog = $sslResult['output'] ?? '';
        
        if (!$sslResult['success']) {
            // Если Certbot упал, обновляем статус в БД на 'failed' и сохраняем лог, не выбрасываем исключение.
            // Но мы не можем включить SSL в Nginx, поэтому возвращаем false.
            try {
                $stmt = $this->repository->getPdo()->prepare("UPDATE sites SET ssl_status = 'failed', ssl_log = ? WHERE domain = ?");
                $stmt->execute([$sslLog, $domain]);
            } catch (\Exception $e) {}
            return false;
        }

        try {
            $this->repository->beginTransaction();

            // 3. Обновляем статус SSL в базе данных
            // Предположим, мы расширили репозиторий методом updateSslStatus
            $this->repository->updateSslStatus($domain, true);

            // 4. Перегенерируем конфиг Nginx, подставляя шаблон 'ssl'
            $configPath = $this->nginx->generateConfig([
                'domain'     => $domain,
                'ip'         => '*', // берем из настроек или базы
                'rootDir'    => $site['root_dir'],
                'phpVersion' => $site['php_version'],
                'template'   => 'ssl' // Переключаемся на HTTPS шаблон!
            ]);

            if ($configPath === null) {
                throw new \Exception("Не удалось сгенерировать SSL-конфиг Nginx.");
            }

            // 5. Тестируем и устанавливаем новый конфиг в систему
            $finalSystemPath = $this->nginx->installSite($domain);
            if ($finalSystemPath === null) {
                throw new \Exception("Системный тест Nginx для SSL провален.");
            }

            // Обновляем ssl_log на успех
            $stmt = $this->repository->getPdo()->prepare("UPDATE sites SET ssl_log = ? WHERE domain = ?");
            $stmt->execute([$sslLog, $domain]);

            $this->repository->commit();
            return true;

        } catch (\Exception $e) {
            $this->repository->rollBack();

            // Откат: если Nginx не принял новый SSL конфиг,
            // откатываем конфиг обратно на дефолтный HTTP, чтобы сайт не умер
            $this->nginx->generateConfig([
                'domain'     => $domain,
                'ip'         => '*',
                'rootDir'    => $site['root_dir'],
                'phpVersion' => $site['php_version'],
                'template'   => $site['template'] // возвращаем старый шаблон (например, default)
            ]);
            $this->nginx->installSite($domain);

            return false;
        }
    }
    public function create(array $data): bool
    {
        $domain = $data['domain'] ?? '';
        $rootDir = $data['root_dir'] ?? '';
        $ip = $data['ip'] ?? '*';

        if (empty($domain) || empty($rootDir)) return false;
        if ($this->repository->exists($domain)) return false;

        // Инициализируем флаги состояния для точечного отката
        $dirCreated = false;
        $siteInstalled = false;

        try {
            // Шаг 1: Создаем директорию
            if (!$this->fileManager->makeDirectory($rootDir)) {
                throw new Exception("Не удалось создать директорию сайта.");
            }
            $dirCreated = true;

            // Шаг 2: Транзакция БД
            $this->repository->beginTransaction();

            $this->repository->insert([
                'domain'      => $domain,
                'root_dir'    => $rootDir,
                'php_version' => $data['php_version'] ?? '8.3',
                'template'    => $data['template'] ?? 'default'
            ]);

            // Шаг 3: Генерация конфига локально
            $configPath = $this->nginx->generateConfig([
                'domain'     => $domain,
                'ip'         => $ip,
                'rootDir'    => $rootDir,
                'phpVersion' => $data['php_version'] ?? '8.3',
                'template'   => $data['template'] ?? 'default'
            ]);

            if ($configPath === null) {
                throw new Exception("Генерация локального конфига провалена.");
            }

            // Шаг 4: Установка в систему Nginx
            $finalSystemPath = $this->nginx->installSite($domain);
            if ($finalSystemPath === null) {
                throw new Exception("Скрипт-установщик Nginx вернул ошибку.");
            }
            $siteInstalled = true; // Фиксируем успех установки vhost

            // Идеальное место для логирования
            // logger()->info("Site vhost successfully installed", ['config' => $finalSystemPath]);

            $this->repository->commit();
            return true;

        } catch (Exception $e) {
            $this->repository->rollBack();

            // Чистим ТОЛЬКО то, что реально успело создаться
            if ($dirCreated) {
                $this->fileManager->removeDirectory($rootDir);
            }

            if ($siteInstalled) {
                $this->nginx->removeSite($domain);
            }

            return false;
        }
    }
}