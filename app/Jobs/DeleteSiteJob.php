<?php

declare(strict_types=1);

namespace App\Jobs;

use PDO;
use App\Services\NginxManager;
use RuntimeException;

class DeleteSiteJob
{
    private PDO $db;
    private NginxManager $nginx;
    private \App\Services\NotificationService $notification;

    public function __construct(PDO $db, NginxManager $nginx, \App\Services\NotificationService $notification)
    {
        $this->db = $db;
        $this->nginx = $nginx;
        $this->notification = $notification;
    }

    public function handle(array $data): void
    {
        $siteId = $data['site_id'] ?? null;
        $domain = $data['domain'] ?? null;

        if (!$siteId || !$domain) {
            throw new \InvalidArgumentException("Недостаточно данных для удаления сайта.");
        }

        // Стартуем чистую транзакцию в воркере
        $this->db->beginTransaction();

        try {
            // Получаем свежие данные сайта (он сейчас в статусе deleting)
            $stmt = $this->db->prepare("SELECT id, domain, root_path, status FROM sites WHERE id = ? FOR UPDATE");
            $stmt->execute([$siteId]);
            $site = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$site) {
                echo "[⚠️] [DeleteJob] Сайт с ID {$siteId} не найден. Возможен повторный вызов джобы. Пропускаем.\n";
                $this->db->rollBack();
                return;
            }

            // ЖЕСТКАЯ ПРОВЕРКА БЕЗОПАСНОСТИ ПУТИ
            $allowedPrefix = '/var/www/workspaces/';
            if (empty($site->root_path) || !str_starts_with($site->root_path, $allowedPrefix)) {
                throw new RuntimeException("Критическая ошибка безопасности: Недопустимый путь: '{$site->root_path}'");
            }
            if (rtrim($site->root_path, '/') === rtrim($allowedPrefix, '/')) {
                throw new RuntimeException("Попытка удалить общую корневую папку workspaces!");
            }

            // ЭТАП 1: Удаление конфигурации Nginx
            echo "[*] [DeleteJob] Удаление конфигурации Nginx для домена: {$domain}\n";
            $this->nginx->removeSite($domain);

            // ЭТАП 2: Удаление папки сайта на Linux-сервере
            echo "[*] [DeleteJob] Удаление директории сайта: {$site->root_path}\n";
            if (is_dir($site->root_path)) {
                $escapedPath = escapeshellarg($site->root_path);
                // Выполняем системное удаление папки
                shell_exec("rm -rf {$escapedPath} 2>&1");
                echo "[✓] Папка сайта {$site->root_path} успешно удалена.\n";
            } else {
                echo "[*] Папка сайта не найдена на диске, пропускаем этап очистки директории.\n";
            }

            // ЭТАП 3: Если всё прошло успешно — удаляем запись из базы данных
            $delete = $this->db->prepare("DELETE FROM sites WHERE id = ?");
            $delete->execute([$siteId]);

            // ФИКСИРУЕМ УСПЕХ: удаление из БД и коммит транзакции
            $this->db->commit();
            echo "[✓] Сайт {$domain} успешно и полностью удален из системы.\n";
            
            $this->notification->create(
                "Сайт удален",
                "Сайт {$domain} и все его файлы успешно удалены.",
                "warning"
            );

        } catch (\Throwable $e) {
            // ЕСЛИ ХОТЬ ОДИН ЭТАП СБОЙНУЛ — ОТКАТЫВАЕМ ВСЁ НАЗАД
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            echo "[❌] Ошибка выполнения DeleteSiteJob. Откат изменений в базе: {$e->getMessage()}\n";

            // Возвращаем сайту статус active в базе данных, чтобы юзер видел, что удаление сорвалось
            $rollback = $this->db->prepare("UPDATE sites SET status = 'active' WHERE id = ?");
            $rollback->execute([$siteId]);

            $this->notification->create(
                "Ошибка удаления",
                "Не удалось удалить сайт {$domain}: " . $e->getMessage(),
                "danger"
            );

            // Пробрасываем исключение дальше воркеру для логирования в queue:failed
            throw $e;
        }
    }
}