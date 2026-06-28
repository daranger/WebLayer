<?php

namespace App\Services;

class NginxManager
{
    private string $templatePath;
    private string $storagePath;

    // Whitelist разрешенных типов конфигураций
    private array $allowedTemplates = [
        'default',
        'ssl',
        'laravel',
        'wordpress',
        'proxy',
        'static'
    ];

    public function __construct(string $templatePath, string $storagePath)
    {
        $this->templatePath = rtrim($templatePath, '/');
        $this->storagePath  = rtrim($storagePath, '/');
    }

    /**
     * Проверка, запущена ли панель на Windows
     */
    private function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Генерирует конфигурационный файл сайта.
     * Возвращает полный путь к файлу в случае успеха или null при ошибке.
     */
    public function generateConfig(array $data): ?string
    {
        $domain = $data['domain'] ?? '';

        // 1. Проверка домена с поддержкой IDN (punycode xn--...) и хостнеймов
        if (empty($domain) || !filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return null;
        }

        // 2. Выбор шаблона из вайтлиста (Защита от Directory Traversal)
        $template = $data['template'] ?? 'default';
        if (!in_array($template, $this->allowedTemplates, true)) {
            return null;
        }

        // В bin/queue_worker мы передали путь до app. Шаблоны ищем в app/Views/nginx/
        $templateFile = "{$this->templatePath}/Views/nginx/{$template}.php";

        // 🔥 ХАК ДЛЯ WINDOWS: Если файла шаблона локально нет, создадим заглушку автоматически
        if (!is_readable($templateFile)) {
            if ($this->isWindows()) {
                @mkdir(dirname($templateFile), 0755, true);
                file_put_contents($templateFile, "# Mock Nginx config for <?php echo \$templateData['domain']; ?>\n");
            } else {
                return null;
            }
        }

        // 3. Изолированный рендеринг
        $templateData = $data;
        ob_start();
        require $templateFile;
        $configContent = ob_get_clean();

        if ($configContent === false) {
            return null;
        }

        // 4. Запись в песочницу storage
        $targetDir = "{$this->storagePath}/nginx";
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            return null;
        }

        $configFile = "{$targetDir}/{$domain}.conf";
        if (file_put_contents($configFile, $configContent) === false) {
            return null;
        }

        return $configFile;
    }

    public function installSite(string $domain): ?string
    {
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return null;
        }

        // 🔥 ХАК ДЛЯ ЛОКАЛЬНОЙ РАЗРАБОТКИ НА WINDOWS
        if ($this->isWindows()) {
            echo "[NginxManager] [Windows Mock] Имитация активации vhost для {$domain}\n";
            return "{$this->storagePath}/nginx/{$domain}.conf";
        }

        // НАСТОЯЩИЙ ПРОДАКШЕН (Ubuntu Linux)
        $configPath = "{$this->storagePath}/nginx/{$domain}.conf";
        if (!is_file($configPath)) {
            return null;
        }

        $available = "/etc/nginx/sites-available/{$domain}.conf";
        $enabled = "/etc/nginx/sites-enabled/{$domain}.conf";

        if (!copy($configPath, $available)) {
            return null;
        }

        if (!file_exists($enabled)) {
            symlink($available, $enabled);
        }

        // Перезагрузка nginx вынесена в SiteService или делается напрямую
        exec("systemctl reload nginx");

        return $enabled;
    }

    public function removeSite(string $domain): bool
    {
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) return false;

        // Очищаем локальную песочницу панели
        $localConfig = "{$this->storagePath}/nginx/{$domain}.conf";
        if (is_file($localConfig)) {
            unlink($localConfig);
        }

        // 🔥 ХАК ДЛЯ WINDOWS
        if ($this->isWindows()) {
            echo "[NginxManager] [Windows Mock] Имитация удаления сайта {$domain} из системы\n";
            return true;
        }

        // Удаляем напрямую системные конфиги vhost
        @unlink("/etc/nginx/sites-available/{$domain}.conf");
        @unlink("/etc/nginx/sites-enabled/{$domain}.conf");
        
        exec("systemctl reload nginx");

        return true;
    }

    /**
     * Проверка синтаксиса: временно линкуем конфиг и вызываем `nginx -t`
     */
    public function testConfig(string $domain, string &$errorMessage = ''): bool
    {
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $errorMessage = 'Некорректное имя домена';
            return false;
        }

        $configPath = "{$this->storagePath}/nginx/{$domain}.conf";
        if (!is_file($configPath)) {
            $errorMessage = 'Файл конфигурации не найден в хранилище панели';
            return false;
        }

        // 🔥 ХАК ДЛЯ WINDOWS
        if ($this->isWindows()) {
            echo "[NginxManager] [Windows Mock] Проверка синтаксиса конфига для {$domain} пройдена\n";
            return true;
        }

        $available = "/etc/nginx/sites-available/{$domain}.conf";
        $enabled = "/etc/nginx/sites-enabled/{$domain}.conf";

        // Временно создаем конфиг в системе для проверки
        copy($configPath, $available);
        $symlinkCreated = false;
        if (!file_exists($enabled)) {
            symlink($available, $enabled);
            $symlinkCreated = true;
        }

        $output = [];
        $returnVar = 1;
        exec("nginx -t 2>&1", $output, $returnVar);

        if ($returnVar !== 0) {
            $errorMessage = implode("\n", $output);
            
            // Откат изменений при ошибке
            @unlink($available);
            if ($symlinkCreated) {
                @unlink($enabled);
            }
            return false;
        }

        // Конфиг валидный, оставляем его, installSite просто подтвердит его
        return true;
    }
}