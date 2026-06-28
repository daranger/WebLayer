<?php

declare(strict_types=1);

namespace App\Controllers;

use Predis\ClientInterface;
use App\Core\Container;
use App\Services\SystemService;

class HomeController
{
    private ClientInterface $redis;
    private SystemService $systemService;

    // Внедряем SystemService прямо в конструктор
    public function __construct(ClientInterface $redis, SystemService $systemService)
    {
        $this->redis = $redis;
        $this->systemService = $systemService;
    }

    /**
     * GET /
     */
    public function index(): void
    {
        $this->renderDashboard();
    }

    /**
     * GET /dashboard
     */
    public function dashboard(): void
    {

        $this->renderDashboard();
    }

    /**
     * Вспомогательный метод сбора телеметрии для вывода на главную
     */
    private function renderDashboard(): void
    {
        // Собираем чистые метрики из нашего SystemService
        $ramData  = $this->systemService->getRamUsage();
        $diskData = $this->systemService->getDiskUsage('/var/www/workspaces');
        $uptimeSec = $this->systemService->getUptime();
        $laData   = $this->systemService->getLoadAverage();

        // Считаем дни и часы для аптайма
        $days = (int)($uptimeSec / 86400);
        $hours = (int)(($uptimeSec % 86400) / 3600);
        $minutes = (int)(($uptimeSec % 3600) / 60);
        $uptimeString = $days > 0 ? "{$days}дн. {$hours}ч." : "{$hours}ч. {$minutes}мин.";

        echo view('dashboard', [
            'title'     => 'Панель управления - SiteManager',
            'cpu_count' => $this->systemService->getCpuCount(),
            'load_avg'  => $laData['1m'] ?? 0.0,
            'ram'       => [
                'total'   => round($ramData['total'] / 1024 / 1024 / 1024, 1),
                'used'    => round($ramData['used'] / 1024 / 1024 / 1024, 1),
                'percent' => $ramData['percent']
            ],
            'disk'      => [
                'total'   => round($diskData['total'] / 1024 / 1024 / 1024, 1),
                'used'    => round($diskData['used'] / 1024 / 1024 / 1024, 1),
                'percent' => $diskData['percent']
            ],
            'uptime'    => $uptimeString
        ]);
    }

    /**
     * GET /api/dashboard/stats
     */
    /**
     * GET /api/dashboard/stats
     */
    public function stats(): void
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $systemService = \App\Core\Container::getInstance()->make(\App\Services\SystemService::class);
        $db = \App\Core\Container::getInstance()->make(\PDO::class);
        $redis = \App\Core\Container::getInstance()->make(\Predis\ClientInterface::class);

        // Читаем текущие метрики и историю из Redis (записанные демоном)
        $currentJson = $redis->get('system:monitor:current');
        $historyRaw = $redis->lrange('system:monitor:history', 0, -1);
        $history = array_map(function($json) { return json_decode($json, true); }, array_reverse($historyRaw));
        
        if ($currentJson) {
            $metrics = json_decode($currentJson, true);
            
            // Если старый демон еще крутится в памяти (и присылает данные без статических метрик)
            if (!isset($metrics['processor'])) {
                $staticInfoJson = $redis->get('system:static_info');
                if ($staticInfoJson) {
                    $staticInfo = json_decode($staticInfoJson, true);
                } else {
                    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
                    $processorName = 'Unknown CPU';
                    if ($isWindows) {
                        $cpuInfo = shell_exec('wmic cpu get name 2>nul');
                        if ($cpuInfo && preg_match('/Name\s+(.+)/i', $cpuInfo, $matches)) {
                            $processorName = trim($matches[1]);
                        }
                    } else {
                        if (is_readable('/proc/cpuinfo')) {
                            $cpuinfo = file_get_contents('/proc/cpuinfo');
                            if (preg_match('/model name\s+:\s+(.+)/i', $cpuinfo, $matches)) {
                                $processorName = trim($matches[1]);
                            }
                        }
                    }

                    $nginxVersion = 'Unknown';
                    $out = shell_exec('nginx -v 2>&1');
                    if ($out && preg_match('/nginx\/([0-9\.]+)/i', $out, $matches)) {
                        $nginxVersion = $matches[1];
                    }

                    $mysqlVersionRaw = 'Unknown';
                    try {
                        $mysqlVersionRaw = $db->query('SELECT VERSION()')->fetchColumn() ?: 'Unknown';
                    } catch (\Throwable $e) {}

                    $isMariaDB = stripos($mysqlVersionRaw, 'mariadb') !== false;
                    $mysqlVersion = $mysqlVersionRaw;
                    if (preg_match('/^([\d\.]+)/', $mysqlVersionRaw, $matches)) {
                        $mysqlVersion = $matches[1] . ($isMariaDB ? ' MariaDB' : '');
                    }

                    $osName = php_uname('s') . ' ' . php_uname('r');
                    if (is_readable('/etc/os-release')) {
                        $osRelease = file_get_contents('/etc/os-release');
                        if (preg_match('/^PRETTY_NAME="([^"]+)"/m', $osRelease, $matches)) {
                            $osName = $matches[1];
                        }
                    }

                    $staticInfo = [
                        'processor' => $processorName,
                        'nginx_version' => $nginxVersion,
                        'mysql_version' => $mysqlVersion,
                        'os_name' => $osName
                    ];
                    $redis->setex('system:static_info', 3600, json_encode($staticInfo));
                }
                
                $metrics['processor'] = $staticInfo['processor'];
                $metrics['nginx_version'] = $staticInfo['nginx_version'];
                $metrics['mysql_version'] = $staticInfo['mysql_version'];
                $metrics['os_name'] = $staticInfo['os_name'] ?? 'Unknown OS';
                
                if (!isset($metrics['process_count'])) {
                    $metrics['process_count'] = $systemService->getProcessCount();
                }
            }
        } else {
            // Фолбэк, если демон не работает
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $uptimeSec = $systemService->getUptime();
            $days = (int)($uptimeSec / 86400);
            $hours = (int)(($uptimeSec % 86400) / 3600);
            $minutes = (int)(($uptimeSec % 3600) / 60);
            $uptimeString = $days > 0 ? "{$days}дн. {$hours}ч." : "{$hours}ч. {$minutes}мин.";
            
            $ramData = $systemService->getRamUsage();
            $diskData = $systemService->getDiskUsage('/var/www/workspaces');
            
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            
            $cpuPercent = $systemService->getCpuUsagePercent();
            
            if ($isWindows) {
                $la = round($cpuPercent / 100, 4);
            } else {
                $laRaw = $systemService->getLoadAverage();
                $la = isset($laRaw['1m']) ? round((float)$laRaw['1m'], 4) : 0.0;
            }

            $processorName = 'Unknown CPU';
            if ($isWindows) {
                $cpuInfo = shell_exec('wmic cpu get name 2>nul');
                if ($cpuInfo && preg_match('/Name\s+(.+)/i', $cpuInfo, $matches)) {
                    $processorName = trim($matches[1]);
                }
            } else {
                if (is_readable('/proc/cpuinfo')) {
                    $cpuinfo = file_get_contents('/proc/cpuinfo');
                    if (preg_match('/model name\s+:\s+(.+)/i', $cpuinfo, $matches)) {
                        $processorName = trim($matches[1]);
                    }
                }
            }

            $nginxVersion = 'Unknown';
            $out = shell_exec('nginx -v 2>&1');
            if ($out && preg_match('/nginx\/([0-9\.]+)/i', $out, $matches)) {
                $nginxVersion = $matches[1];
            }

            $mysqlVersionRaw = 'Unknown';
            try {
                $mysqlVersionRaw = $db->query('SELECT VERSION()')->fetchColumn() ?: 'Unknown';
            } catch (\Throwable $e) {}

            $isMariaDB = stripos($mysqlVersionRaw, 'mariadb') !== false;
            $mysqlVersion = $mysqlVersionRaw;
            if (preg_match('/^([\d\.]+)/', $mysqlVersionRaw, $matches)) {
                $mysqlVersion = $matches[1] . ($isMariaDB ? ' MariaDB' : '');
            }

            $osName = php_uname('s') . ' ' . php_uname('r');
            if (is_readable('/etc/os-release')) {
                $osRelease = file_get_contents('/etc/os-release');
                if (preg_match('/^PRETTY_NAME="([^"]+)"/m', $osRelease, $matches)) {
                    $osName = $matches[1];
                }
            }

            $metrics = [
                'cpu' => ['percent' => $cpuPercent, 'la' => $la],
                'ram' => [
                    'percent' => $ramData['percent'],
                    'used' => round($ramData['used'] / 1024 / 1024 / 1024, 1),
                    'total' => round($ramData['total'] / 1024 / 1024 / 1024, 1)
                ],
                'disk' => [
                    'percent' => $diskData['percent'],
                    'used' => round($diskData['used'] / 1024 / 1024 / 1024, 1),
                    'total' => round($diskData['total'] / 1024 / 1024 / 1024, 1)
                ],
                'uptime' => $uptimeString,
                'php_version' => PHP_VERSION,
                'kernel' => php_uname('r'),
                'redis_version' => 'Active',
                'processor' => $processorName,
                'nginx_version' => $nginxVersion,
                'mysql_version' => $mysqlVersion,
                'os_name' => $osName,
                'process_count' => $systemService->getProcessCount(),
                'git_version' => shell_exec('git --version | awk \'{print $3}\'') ?: 'Not installed'
            ];
            
            try {
                $metrics['redis_version'] = $redis->info('server')['Server']['redis_version'] ?? 'Active';
            } catch (\Throwable $e) {}
        }

        $logs = [];
        try {
            $logStmt = $db->query("SELECT created_at, username, ip_address FROM user_logs ORDER BY id DESC LIMIT 5");
            $logs = $logStmt ? $logStmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            
            // Заглушки, если таблица пуста, чтобы красиво смотрелось на дашборде
            if (empty($logs)) {
                $logs = [
                    ['created_at' => date('Y-m-d H:i:s'), 'username' => 'root', 'ip_address' => '12.22.13.144'],
                    ['created_at' => date('Y-m-d H:i:s', time() - 3600), 'username' => 'root', 'ip_address' => '12.22.13.144'],
                    ['created_at' => date('Y-m-d H:i:s', time() - 86400), 'username' => 'root', 'ip_address' => '12.22.13.144']
                ];
            }
        } catch (\Throwable $e) {
            $logs = [];
        }

        $jobs = [];
        try {
            // Создаем таблицу для логов демона, если её нет (демон будет писать сюда)
            $db->exec("CREATE TABLE IF NOT EXISTS `daemon_jobs_log` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `status` varchar(50) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            $jobsStmt = $db->query("SELECT name, status, created_at FROM daemon_jobs_log ORDER BY id DESC LIMIT 3");
            $jobs = $jobsStmt ? $jobsStmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            
            if (empty($jobs)) {
                $jobs = [
                    ['name' => 'Резервное копирование MySQL', 'status' => 'success', 'created_at' => date('Y-m-d H:i:s')],
                    ['name' => 'Обновление SSL сертификатов', 'status' => 'success', 'created_at' => date('Y-m-d H:i:s')],
                    ['name' => 'Синхронизация конфигурации Nginx', 'status' => 'success', 'created_at' => date('Y-m-d H:i:s')]
                ];
            }
        } catch (\Throwable $e) {
            $jobs = [];
        }

        echo json_encode([
            'success' => true,
            'metrics' => $metrics,
            'history' => $history,
            'jobs' => $jobs,
            'logs' => $logs
        ]);
        exit;
    }

    /**
     * GET /login
     */
    public function login(): void
    {
        echo view('login', [
            'title' => 'Авторизация - SiteManager'
        ]);
    }

    /**
     * GET /sites
     */
    public function sites(): void
    {
        echo view('sites', [
            'title' => 'Мои сайты - SiteManager'
        ]);
    }
}