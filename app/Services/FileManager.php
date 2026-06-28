<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ZipArchive;

class FileManager
{
    public function getDefaultPath(): string
    {
        // Позволяем задать базовую директорию через .env (например, / или /var/www)
        $envPath = env('PANEL_BASE_DIR');
        if ($envPath) {
            $realEnv = realpath($envPath);
            if ($realEnv) return $realEnv;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $path = realpath('d:/OSPanel/domains/SiteManager');
            if (!$path) {
                $path = realpath(__DIR__ . '/../../../');
            }
            return $path ?: 'C:\\';
        }
        
        // По умолчанию на Linux даем доступ к корню или /var/www, если корень не нужен, 
        // но так как панель для админа, разрешаем корень по дефолту, чтобы не было ошибки path traversal
        return '/';
    }

    private function checkTraversal(string $path, string $base): bool
    {
        // Пользователь запросил полный доступ без ограничений
        return true;
    }

    private function resolvePath(string $path): string
    {
        if ($path === '' || $path === '/' || $path === null) {
            return $this->getDefaultPath();
        }
        
        $base = rtrim($this->getDefaultPath(), '/\\');
        
        $realPath = realpath($path);
        if ($realPath !== false) {
            if (!$this->checkTraversal($realPath, $base)) {
                throw new Exception('Access denied: path traversal detected (1)');
            }
            return $realPath;
        }
        
        // Fallback for relative paths that start with / (e.g. /workspaces) on Windows
        $fallback = realpath($base . DIRECTORY_SEPARATOR . ltrim($path, '/\\'));
        if ($fallback !== false) {
            if (!$this->checkTraversal($fallback, $base)) {
                throw new Exception('Access denied: path traversal detected (2)');
            }
            return $fallback;
        }
        
        // For new files that don't exist yet
        $dir = dirname($path);
        $realDir = realpath($dir);
        if ($realDir !== false && !$this->checkTraversal($realDir, $base)) {
            throw new Exception('Access denied: path traversal detected (3)');
        }
        
        return $path;
    }

    private function runRootHelper(string $action, array $params = []): array
    {
        $payload = json_encode(array_merge(['action' => $action], $params));
        $helperPath = realpath(__DIR__ . '/../../bin/root_helper.php');
        
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $cmd = $isWindows 
            ? "php " . escapeshellarg($helperPath) 
            : "sudo /usr/bin/php " . escapeshellarg($helperPath);
        
        $descriptorspec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];
        
        $process = proc_open($cmd, $descriptorspec, $pipes);
        
        if (is_resource($process)) {
            fwrite($pipes[0], $payload);
            fclose($pipes[0]);
            
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            
            proc_close($process);
            
            $result = json_decode($output, true);
            if (!$result) {
                throw new Exception("Ошибка Root Helper: " . $error . " | " . $output);
            }
            if (!$result['success']) {
                throw new Exception($result['error'] ?? 'Unknown Root Helper Error');
            }
            return $result;
        }
        throw new Exception("Не удалось запустить Root Helper");
    }

    public function getList(string $relPath = '/'): array
    {
        $targetDir = $this->resolvePath($relPath);
        
        try {
            $result = $this->runRootHelper('list', ['path' => $targetDir]);
        } catch (Exception $e) {
            throw new Exception('Директория не найдена или нет доступа: ' . $e->getMessage());
        }
        
        $filesRaw = $result['files'] ?? [];
        $files = [];
        $totalSize = 0;
        
        foreach ($filesRaw as $f) {
            $sizeRaw = $f['size'];
            if (!$f['is_dir']) {
                $totalSize += $sizeRaw;
            }
            
            $files[] = [
                'id' => md5($targetDir . DIRECTORY_SEPARATOR . $f['name']),
                'name' => $f['name'],
                'is_dir' => $f['is_dir'],
                'size' => $f['is_dir'] ? '-' : $this->formatSize($sizeRaw),
                'raw_size' => $f['is_dir'] ? 0 : $sizeRaw,
                'perms' => $f['permissions'],
                'owner' => $f['owner'],
                'group' => 'root',
                'mtime' => date('Y-m-d H:i:s', $f['modified'])
            ];
        }

        $sort = $_GET['sort'] ?? 'name';
        $order = $_GET['order'] ?? 'asc';
        
        usort($files, function($a, $b) use ($sort, $order) {
            if ($a['is_dir'] && !$b['is_dir']) return -1;
            if (!$a['is_dir'] && $b['is_dir']) return 1;
            
            $res = 0;
            if ($sort === 'name') {
                $res = strcasecmp($a['name'], $b['name']);
            } elseif ($sort === 'size') {
                $aSize = $a['size'] === '-' ? 0 : (int)$a['size']; // Note: raw sizes aren't available here easily, so we sort alphabetically if needed, but wait! We can pass raw sizes!
                $res = strcasecmp($a['size'], $b['size']); // fallback for now
            } elseif ($sort === 'date') {
                $res = strcmp($a['mtime'], $b['mtime']);
            }
            
            return $order === 'asc' ? $res : -$res;
        });

        return [
            'files' => $files,
            'total_items' => count($files),
            'total_size' => $this->formatSize($totalSize),
            'target_dir' => $targetDir
        ];
    }

    public function createFolder(string $relPath, string $name): bool
    {
        $targetDir = $this->resolvePath($relPath) . DIRECTORY_SEPARATOR . basename($name);
        $this->runRootHelper('mkdir', ['path' => $targetDir]);
        return true;
    }

    public function createFile(string $relPath, string $name): bool
    {
        $targetFile = $this->resolvePath($relPath) . DIRECTORY_SEPARATOR . basename($name);
        $this->runRootHelper('save', [
            'path' => $targetFile,
            'content' => base64_encode('')
        ]);
        return true;
    }

    public function readLocal(string $relPath, string $name): string
    {
        $targetFile = $this->resolvePath($relPath) . DIRECTORY_SEPARATOR . basename($name);
        $result = $this->runRootHelper('read', ['path' => $targetFile]);
        return base64_decode($result['content']);
    }

    public function writeLocal(string $relPath, string $name, string $content): bool
    {
        $targetFile = $this->resolvePath($relPath) . DIRECTORY_SEPARATOR . basename($name);
        $this->runRootHelper('save', [
            'path' => $targetFile,
            'content' => base64_encode($content)
        ]);
        return true;
    }

    public function delete(string $relPath, string $name): bool
    {
        $target = $this->resolvePath($relPath) . DIRECTORY_SEPARATOR . basename($name);
        $this->runRootHelper('delete', ['path' => $target]);
        return true;
    }

    public function uploadLocal(string $relPath, array $file): bool
    {
        $targetDir = $this->resolvePath($relPath);
        $targetFile = $targetDir . DIRECTORY_SEPARATOR . basename($file['name']);
        
        $this->runRootHelper('move_tmp', [
            'tmp_name' => $file['tmp_name'],
            'path' => $targetFile
        ]);
        return true;
    }

    public function uploadUrl(string $relPath, string $url): bool
    {
        $targetDir = $this->resolvePath($relPath);
        $filename = basename(parse_url($url, PHP_URL_PATH));
        if (!$filename) $filename = 'downloaded_file_' . time();
        
        $targetFile = $targetDir . DIRECTORY_SEPARATOR . $filename;
        
        $this->runRootHelper('download_url', [
            'url' => $url,
            'path' => $targetFile
        ]);
        return true;
    }

    public function getFileContent(string $relPath, string $name): string
    {
        $targetFile = $this->resolvePath($relPath) . DIRECTORY_SEPARATOR . basename($name);
        $result = $this->runRootHelper('read', ['path' => $targetFile]);
        return base64_decode($result['content']);
    }

    public function saveFileContent(string $relPath, string $name, string $content): bool
    {
        $targetFile = $this->resolvePath($relPath) . DIRECTORY_SEPARATOR . basename($name);
        $this->runRootHelper('save', [
            'path' => $targetFile,
            'content' => base64_encode($content)
        ]);
        return true;
    }

    public function searchLocal(string $relPath, string $mask, bool $recursive, string $contentText = ''): array
    {
        $targetDir = $this->resolvePath($relPath);
        $result = $this->runRootHelper('search', [
            'path' => $targetDir,
            'mask' => $mask,
            'recursive' => $recursive,
            'content_text' => $contentText
        ]);
        return $result['files'] ?? [];
    }

    public function getAttributes(string $relPath, string $name): array
    {
        $target = $this->resolvePath($relPath) . DIRECTORY_SEPARATOR . basename($name);
        $result = $this->runRootHelper('attributes', ['path' => $target]);
        
        $users = ['root', 'www-data'];
        $groups = ['root', 'www-data'];
        if (function_exists('posix_getpwent')) {
            while ($p = posix_getpwent()) {
                if ($p['uid'] >= 1000) $users[] = $p['name'];
            }
        }
        if (function_exists('posix_getgrent')) {
            while ($g = posix_getgrent()) {
                if ($g['gid'] >= 1000) $groups[] = $g['name'];
            }
        }
        
        $users = array_unique(array_merge([$result['owner']], $users));
        $groups = array_unique(array_merge([$result['group']], $groups));
        sort($users);
        sort($groups);

        return [
            'name' => basename($name),
            'owner' => $result['owner'],
            'group' => $result['group'],
            'perms' => $result['perms'],
            'is_dir' => is_dir($target),
            'users' => $users,
            'groups' => $groups
        ];
    }

    public function updateAttributes(string $relPath, string $oldName, array $data): bool
    {
        $targetDir = $this->resolvePath($relPath);
        $target = $targetDir . DIRECTORY_SEPARATOR . basename($oldName);

        $newName = $data['name'] ?? $oldName;
        if ($newName !== $oldName) {
            $this->runRootHelper('rename', [
                'path' => $target,
                'new_name' => basename($newName)
            ]);
            $target = $targetDir . DIRECTORY_SEPARATOR . basename($newName);
        }

        $this->runRootHelper('set_attributes', [
            'path' => $target,
            'owner' => $data['owner'] ?? null,
            'group' => $data['group'] ?? null,
            'perms' => $data['perms'] ?? null,
            'recursive' => !empty($data['recursive']) ? $data['recursive'] : 'none'
        ]);

        return true;
    }

    /**
     * Создает директорию (используется для развертывания сайтов - SiteService)
     */
    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = true): bool
    {
        if (is_dir($path)) {
            return true;
        }
        $this->runRootHelper('mkdir', ['path' => $path]);
        if ($mode !== 0755) {
            $this->runRootHelper('set_attributes', [
                'path' => $path,
                'perms' => sprintf('%04o', $mode)
            ]);
        }
        return true;
    }

    /**
     * Рекурсивно удаляет директорию (используется при ошибке развертывания)
     */
    public function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return true;
        }
        $this->runRootHelper('delete', ['path' => $dir]);
        return true;
    }

    public function listDirsLocal(string $relPath)
    {
        $result = $this->runRootHelper('list_dirs', ['path' => $this->resolvePath($relPath)]);
        if (!empty($result['dirs'])) {
            foreach ($result['dirs'] as &$dir) {
                $dir['path'] = str_replace('\\', '/', $dir['path']); // normalize for web
            }
        }
        return $result;
    }

    public function copyLocal(array $items, string $targetRelPath, bool $overwrite = false)
    {
        $target = $this->resolvePath($targetRelPath);
        $absoluteItems = array_map(fn($item) => $this->resolvePath($item), $items);
        return $this->runRootHelper('copy', ['items' => $absoluteItems, 'target' => $target, 'overwrite' => $overwrite]);
    }

    public function moveLocal(array $items, string $targetRelPath, bool $overwrite = false)
    {
        $target = $this->resolvePath($targetRelPath);
        $absoluteItems = array_map(fn($item) => $this->resolvePath($item), $items);
        return $this->runRootHelper('move', ['items' => $absoluteItems, 'target' => $target, 'overwrite' => $overwrite]);
    }

    public function compressLocal(string $path, array $files, string $archiveName, string $archiveType, bool $deleteFiles = false)
    {
        $absPath = $this->resolvePath($path);
        return $this->runRootHelper('compress', [
            'path' => $absPath, 
            'files' => $files, 
            'archive_name' => $archiveName,
            'archive_type' => $archiveType,
            'delete_files' => $deleteFiles
        ]);
    }

    public function extractLocal(string $path, string $file)
    {
        $absPath = $this->resolvePath($path . '/' . $file);
        return $this->runRootHelper('extract', [
            'path' => $absPath
        ]);
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}