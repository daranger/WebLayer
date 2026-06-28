<?php

// Скрипт-помощник для выполнения привилегированных файловых операций (запускается через sudo)

if (php_sapi_name() !== 'cli') {
    die(json_encode(['success' => false, 'error' => 'Only CLI allowed']));
}

$inputRaw = file_get_contents('php://stdin');
$input = json_decode($inputRaw, true);

if (!$input || empty($input['action'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit(1);
}

$action = $input['action'];
$path = $input['path'] ?? '';

try {
    // Helper function for recursive copy
    function rcopy($src, $dst) {
        if (file_exists($dst)) rrmdir($dst);
        if (is_dir($src)) {
            mkdir($dst);
            $files = scandir($src);
            foreach ($files as $file) {
                if ($file != "." && $file != "..") {
                    rcopy("$src/$file", "$dst/$file");
                }
            }
        } else if (file_exists($src)) {
            copy($src, $dst);
        }
    }
    
    // Helper function for recursive delete
    function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object)) {
                        rrmdir($dir. DIRECTORY_SEPARATOR .$object);
                    } else {
                        unlink($dir. DIRECTORY_SEPARATOR .$object);
                    }
                }
            }
            rmdir($dir);
        } else if (file_exists($dir)) {
            unlink($dir);
        }
    }

    function getAllowedBasePath() {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), 'PANEL_BASE_DIR=')) {
                    $val = trim(substr(trim($line), 15));
                    $real = realpath($val);
                    if ($real) return $real;
                }
            }
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $path = realpath('d:/OSPanel/domains/SiteManager');
            if (!$path) {
                $path = realpath(__DIR__ . '/../../');
            }
            return $path ?: 'C:\\';
        }
        return '/';
    }

    function checkTraversal($path, $base) {
        // Полный доступ без ограничений
        return true;
    }

    function validatePath($p) {
        if (empty($p)) return;
        $base = rtrim(getAllowedBasePath(), '/\\');
        $real = realpath($p);
        if ($real !== false) {
            if (!checkTraversal($real, $base)) {
                echo json_encode(['success' => false, 'error' => 'Access denied: path traversal detected in root_helper (1)']);
                exit(1);
            }
        } else {
            $dir = dirname($p);
            $realDir = realpath($dir);
            if ($realDir !== false && !checkTraversal($realDir, $base)) {
                echo json_encode(['success' => false, 'error' => 'Access denied: path traversal detected in root_helper (2)']);
                exit(1);
            }
        }
    }

    if (!empty($path)) {
        validatePath($path);
    }
    
    if (!empty($input['files']) && is_array($input['files'])) {
        foreach ($input['files'] as $f) {
            validatePath($path . DIRECTORY_SEPARATOR . ltrim($f, '/\\'));
        }
    }

    switch ($action) {
        case 'list':
            if (!is_dir($path)) throw new Exception('Директория не существует: ' . $path);
            $items = scandir($path);
            $files = [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $fullPath = $path . DIRECTORY_SEPARATOR . $item;
                $isDir = is_dir($fullPath);
                
                $ownerName = fileowner($fullPath);
                if (function_exists('posix_getpwuid')) {
                    $ownerInfo = posix_getpwuid($ownerName);
                    if ($ownerInfo) $ownerName = $ownerInfo['name'];
                }
                
                $files[] = [
                    'name' => $item,
                    'is_dir' => $isDir,
                    'size' => $isDir ? 0 : filesize($fullPath),
                    'modified' => filemtime($fullPath),
                    'permissions' => substr(sprintf('%o', fileperms($fullPath)), -4),
                    'owner' => $ownerName
                ];
            }
            echo json_encode(['success' => true, 'files' => $files]);
            break;
            
        case 'list_dirs':
            if (!is_dir($path)) throw new Exception('Директория не существует: ' . $path);
            $items = scandir($path);
            $dirs = [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $fullPath = $path . DIRECTORY_SEPARATOR . $item;
                if (is_dir($fullPath)) {
                    $dirs[] = [
                        'name' => $item,
                        'path' => $fullPath
                    ];
                }
            }
            echo json_encode(['success' => true, 'dirs' => $dirs]);
            break;
            
        case 'copy':
        case 'move':
            $items = $input['items'] ?? [];
            $target = $input['target'] ?? '';
            $overwrite = $input['overwrite'] ?? false;
            
            if (empty($target) || !is_dir($target)) throw new Exception('Целевой каталог не существует');
            
            foreach ($items as $item) {
                if (!file_exists($item)) continue;
                $basename = basename($item);
                $dest = $target . DIRECTORY_SEPARATOR . $basename;
                
                if (file_exists($dest) && !$overwrite) {
                    continue; // Skip if no overwrite
                }
                
                if ($action === 'move') {
                    // Try rename first
                    if (!@rename($item, $dest)) {
                        rcopy($item, $dest);
                        rrmdir($item);
                    }
                } else {
                    rcopy($item, $dest);
                }
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'delete':
            if (!file_exists($path)) throw new Exception('Путь не существует: ' . $path);
            if (is_dir($path)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    if (!@$todo($fileinfo->getRealPath())) {
                        throw new Exception("Не удалось удалить вложенный элемент: " . $fileinfo->getFilename());
                    }
                }
                if (!@rmdir($path)) throw new Exception("Не удалось удалить директорию: " . basename($path));
            } else {
                if (!@unlink($path)) throw new Exception("Не удалось удалить файл: " . basename($path));
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'read':
            if (!is_file($path)) throw new Exception('Файл не найден: ' . $path);
            $content = file_get_contents($path);
            if ($content === false) throw new Exception('Не удалось прочитать файл');
            echo json_encode(['success' => true, 'content' => base64_encode($content)]);
            break;
            
        case 'save':
            if (!isset($input['content'])) throw new Exception('Пустой контент для сохранения');
            $content = base64_decode($input['content']);
            if (file_put_contents($path, $content) === false) {
                $err = error_get_last();
                throw new Exception('Не удалось сохранить файл (нет прав). Путь: ' . $path . '. Ошибка PHP: ' . ($err ? $err['message'] : 'неизвестно'));
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'mkdir':
            if (!@mkdir($path, 0755, true)) {
                throw new Exception('Не удалось создать директорию (нет прав)');
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'move_tmp':
            $tmpName = $input['tmp_name'] ?? '';
            if (!file_exists($tmpName)) throw new Exception('Временный файл загрузки не найден');
            
            // Если пытаемся переместить между разными mount points, rename может упасть, используем copy+unlink
            if (!@rename($tmpName, $path)) {
                if (!@copy($tmpName, $path)) {
                    throw new Exception('Не удалось скопировать загруженный файл в ' . $path);
                }
                @unlink($tmpName);
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'download_url':
            $url = $input['url'] ?? '';
            if (empty($url)) throw new Exception('URL не указан');
            $content = @file_get_contents($url);
            if ($content === false) throw new Exception('Не удалось скачать файл по URL');
            if (file_put_contents($path, $content) === false) {
                throw new Exception('Не удалось сохранить скачанный файл');
            }
            echo json_encode(['success' => true]);
            break;

        case 'service_control':
            $service = $input['service'] ?? '';
            $srvAction = $input['service_action'] ?? '';
            
            if (!preg_match('/^[a-zA-Z0-9\-_.]+$/', $service)) throw new Exception('Недопустимое имя службы');
            if (!in_array($srvAction, ['start', 'stop', 'restart', 'reload'])) throw new Exception('Недопустимое действие');
            
            $cmd = sprintf("systemctl %s %s 2>&1", $srvAction, escapeshellarg($service));
            exec($cmd, $output, $returnVar);
            
            if ($returnVar !== 0) {
                throw new Exception("Ошибка systemctl: " . implode("\n", $output));
            }
            echo json_encode(['success' => true]);
            break;

        case 'check_nginx':
            if (DIRECTORY_SEPARATOR === '\\') {
                echo json_encode(['success' => true, 'message' => 'Windows mock']);
                break;
            }
            exec("nginx -t 2>&1", $output, $returnVar);
            if ($returnVar !== 0) {
                echo json_encode(['success' => false, 'error' => implode("\n", $output)]);
            } else {
                echo json_encode(['success' => true]);
            }
            break;

        case 'attributes':
            if (!file_exists($path)) throw new Exception('Файл не существует');
            $perms = fileperms($path);
            $ownerId = fileowner($path);
            $groupId = filegroup($path);

            $owner = function_exists('posix_getpwuid') && $ownerId !== false ? posix_getpwuid($ownerId)['name'] : 'root';
            $group = function_exists('posix_getgrgid') && $groupId !== false ? posix_getgrgid($groupId)['name'] : 'root';
            
            echo json_encode([
                'success' => true,
                'perms' => substr(sprintf('%o', $perms), -4),
                'owner' => $owner,
                'group' => $group
            ]);
            break;
            
        case 'set_attributes':
            if (!file_exists($path)) throw new Exception('Файл не существует');
            $perms = $input['perms'] ?? '';
            $owner = $input['owner'] ?? '';
            $group = $input['group'] ?? '';
            
            if ($perms) @chmod($path, octdec($perms));
            if ($owner) @chown($path, $owner);
            if ($group) @chgrp($path, $group);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'rename':
            $newName = $input['new_name'] ?? '';
            if (empty($newName)) throw new Exception('Новое имя не указано');
            $newPath = dirname($path) . DIRECTORY_SEPARATOR . $newName;
            validatePath($path);
            validatePath($newPath);
            if (file_exists($newPath)) throw new Exception('Файл с таким именем уже существует');
            if (!@rename($path, $newPath)) throw new Exception('Не удалось переименовать файл');
            echo json_encode(['success' => true]);
            break;
            
        case 'compress':
            $files = $input['files'] ?? [];
            if (empty($files)) throw new Exception('Нет файлов для архивации');
            $archiveName = $input['archive_name'] ?? 'archive.zip';
            $archiveType = $input['archive_type'] ?? 'zip';
            $deleteFiles = !empty($input['delete_files']);
            $archivePath = $path . DIRECTORY_SEPARATOR . $archiveName;
            
            if ($archiveType === 'tar.gz') {
                $tarName = str_replace('.tar.gz', '.tar', $archiveName);
                $tarPath = $path . DIRECTORY_SEPARATOR . $tarName;
                if (file_exists($tarPath)) @unlink($tarPath);
                if (file_exists($archivePath)) @unlink($archivePath);
                $phar = new PharData($tarPath);
                foreach ($files as $f) {
                    $fPath = $path . DIRECTORY_SEPARATOR . $f;
                    if (!file_exists($fPath)) continue;
                    if (is_dir($fPath)) {
                        $dirItr = new RecursiveDirectoryIterator($fPath, RecursiveDirectoryIterator::SKIP_DOTS);
                        $itr = new RecursiveIteratorIterator($dirItr, RecursiveIteratorIterator::SELF_FIRST);
                        $phar->addEmptyDir($f);
                        foreach ($itr as $item) {
                            $localPath = $f . DIRECTORY_SEPARATOR . $itr->getSubPathName();
                            if ($item->isDir()) {
                                $phar->addEmptyDir($localPath);
                            } else {
                                $phar->addFile($item->getRealPath(), $localPath);
                            }
                        }
                    } else {
                        $phar->addFile($fPath, $f);
                    }
                }
                $phar->compress(Phar::GZ);
                @unlink($tarPath);
            } else {
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                        throw new Exception('Не удалось создать архив');
                    }
                    foreach ($files as $f) {
                        $fPath = $path . DIRECTORY_SEPARATOR . $f;
                        if (!file_exists($fPath)) continue;
                        if (is_dir($fPath)) {
                            $dirItr = new RecursiveDirectoryIterator($fPath, RecursiveDirectoryIterator::SKIP_DOTS);
                            $itr = new RecursiveIteratorIterator($dirItr, RecursiveIteratorIterator::SELF_FIRST);
                            $zip->addEmptyDir($f);
                            foreach ($itr as $item) {
                                $localPath = $f . DIRECTORY_SEPARATOR . $itr->getSubPathName();
                                if ($item->isDir()) {
                                    $zip->addEmptyDir($localPath);
                                } else {
                                    $zip->addFile($item->getRealPath(), $localPath);
                                }
                            }
                        } else {
                            $zip->addFile($fPath, $f);
                        }
                    }
                    $zip->close();
                } else {
                    // Fallback to shell zip command
                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                        $paths = array_map(function($f) use ($path) { 
                            return "'" . str_replace("'", "''", $path . DIRECTORY_SEPARATOR . $f) . "'"; 
                        }, $files);
                        $dest = "'" . str_replace("'", "''", $archivePath) . "'";
                        $psCmd = "powershell -NoProfile -Command \"Compress-Archive -Path " . implode(',', $paths) . " -DestinationPath " . $dest . " -Force\"";
                        exec($psCmd, $output, $returnVar);
                        if ($returnVar !== 0) {
                            throw new Exception('Не удалось создать ZIP архив через Powershell (код ' . $returnVar . ').');
                        }
                    } else {
                        $cmd = "cd " . escapeshellarg($path) . " && zip -r " . escapeshellarg($archiveName);
                        foreach ($files as $f) {
                            $cmd .= " " . escapeshellarg($f);
                        }
                        exec($cmd, $output, $returnVar);
                        if ($returnVar !== 0) {
                            throw new Exception('Не удалось создать ZIP архив через консоль (код ' . $returnVar . '). Утилита zip не установлена?');
                        }
                    }
                }
            }
            
            if ($deleteFiles) {
                foreach ($files as $f) {
                    $fPath = $path . DIRECTORY_SEPARATOR . $f;
                    if (is_dir($fPath)) {
                        rrmdir($fPath);
                    } else {
                        @unlink($fPath);
                    }
                }
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'extract':
            if (!file_exists($path)) throw new Exception('Архив не найден');
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $isTarGz = (str_ends_with(strtolower($path), '.tar.gz'));
            $extractTo = dirname($path);
            
            if ($isTarGz) {
                if (class_exists('PharData')) {
                    $phar = new PharData($path);
                    $phar->extractTo($extractTo, null, true);
                } else {
                    $cmd = 'tar -xzf ' . escapeshellarg($path) . ' -C ' . escapeshellarg($extractTo);
                    exec($cmd, $output, $returnVar);
                    if ($returnVar !== 0) {
                        throw new Exception('Не удалось распаковать tar.gz архив (код ' . $returnVar . ').');
                    }
                }
            } elseif ($ext === 'zip') {
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    if ($zip->open($path) === true) {
                        $zip->extractTo($extractTo);
                        $zip->close();
                    } else {
                        throw new Exception('Не удалось открыть ZIP архив');
                    }
                } else {
                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                        $src = "'" . str_replace("'", "''", $path) . "'";
                        $dest = "'" . str_replace("'", "''", $extractTo) . "'";
                        $psCmd = "powershell -NoProfile -Command \"Expand-Archive -Path " . $src . " -DestinationPath " . $dest . " -Force\"";
                        exec($psCmd, $output, $returnVar);
                        if ($returnVar !== 0) {
                            throw new Exception('Не удалось распаковать ZIP архив через Powershell (код ' . $returnVar . ').');
                        }
                    } else {
                        $cmd = 'unzip -o ' . escapeshellarg($path) . ' -d ' . escapeshellarg($extractTo);
                        exec($cmd, $output, $returnVar);
                        if ($returnVar !== 0) {
                            throw new Exception('Не удалось распаковать ZIP архив через консоль (код ' . $returnVar . '). Утилита unzip не установлена?');
                        }
                    }
                }
            } elseif ($ext === 'rar') {
                $cmd = 'unrar x -y -o+ ' . escapeshellarg($path) . ' ' . escapeshellarg($extractTo);
                exec($cmd, $output, $returnVar);
                if ($returnVar !== 0) {
                    throw new Exception('Не удалось распаковать RAR архив (код ' . $returnVar . '). Возможно, утилита unrar не установлена.');
                }
            } else {
                throw new Exception('Неподдерживаемый формат архива');
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'search':
            if (!is_dir($path)) throw new Exception('Директория не существует: ' . $path);
            
            $mask = $input['mask'] ?? '*';
            $recursive = !empty($input['recursive']);
            $contentText = $input['content_text'] ?? '';
            
            $results = [];
            $maxResults = 200; // Ограничение на количество результатов
            
            function searchDir($dir, $mask, $recursive, $contentText, &$results, $maxResults) {
                if (count($results) >= $maxResults) return;
                $files = @scandir($dir);
                if ($files === false) return;
                
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..') continue;
                    $fullPath = $dir . DIRECTORY_SEPARATOR . $f;
                    $isDir = is_dir($fullPath);
                    
                    $nameMatch = fnmatch($mask, $f);
                    
                    if ($nameMatch) {
                        $contentMatch = true;
                        if ($contentText !== '') {
                            if ($isDir) {
                                $contentMatch = false;
                            } else {
                                $contentMatch = false;
                                if (filesize($fullPath) < 5 * 1024 * 1024) { // До 5 МБ
                                    $content = @file_get_contents($fullPath);
                                    if ($content !== false && stripos($content, $contentText) !== false) {
                                        $contentMatch = true;
                                    }
                                }
                            }
                        }
                        
                        if ($contentMatch) {
                            $results[] = [
                                'name' => $f,
                                'path' => str_replace('\\', '/', $fullPath),
                                'is_dir' => $isDir
                            ];
                            if (count($results) >= $maxResults) return;
                        }
                    }
                    
                    if ($recursive && $isDir) {
                        searchDir($fullPath, $mask, $recursive, $contentText, $results, $maxResults);
                    }
                }
            }
            
            searchDir($path, $mask, $recursive, $contentText, $results, $maxResults);
            echo json_encode(['success' => true, 'files' => $results]);
            break;

        case 'kill_process':
            $pids = $input['pids'] ?? [];
            if (empty($pids) || !is_array($pids)) throw new Exception('Не указаны PID для завершения');
            
            $successCount = 0;
            foreach ($pids as $pid) {
                $pid = (int)$pid;
                if ($pid <= 5) continue; // Защита от системных процессов
                exec("kill -9 $pid 2>/dev/null", $output, $returnVar);
                if ($returnVar === 0) {
                    $successCount++;
                }
            }
            if ($successCount === 0 && count($pids) > 0) {
                throw new Exception('Не удалось завершить выбранные процессы');
            }
            echo json_encode(['success' => true]);
            break;

        case 'system_reboot':
            exec("reboot > /dev/null 2>&1 &");
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Неизвестное действие');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
