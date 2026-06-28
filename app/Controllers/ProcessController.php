<?php

namespace App\Controllers;

use PDO;

class ProcessController
{
    public function index(): void
    {
        echo view('processes.index');
    }

    public function list(): void
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $processes = [];

        if ($isWindows) {
            // Фолбэк для Windows (заглушка для отладки)
            $out = shell_exec('tasklist /FO CSV /NH 2>nul');
            if ($out) {
                $lines = explode("\n", trim($out));
                foreach ($lines as $line) {
                    $row = str_getcsv($line);
                    if (count($row) >= 5) {
                        $processes[] = [
                            'pid' => (int)$row[1],
                            'user' => 'system',
                            'cpu' => '0.00',
                            'mem' => round((int)str_replace(['K', ' '], '', $row[4]) / 1024, 2),
                            'time' => '0:00',
                            'command' => $row[0]
                        ];
                    }
                }
            }
        } else {
            // Выполняем ps aux, пропускаем заголовок
            // Формат: USER PID %CPU %MEM VSZ RSS TTY STAT START TIME COMMAND
            $out = shell_exec('ps aux 2>/dev/null');
            if ($out) {
                $lines = explode("\n", trim($out));
                array_shift($lines); // Удаляем заголовок
                
                foreach ($lines as $line) {
                    $parts = preg_split('/\s+/', trim($line), 11);
                    if (count($parts) >= 11) {
                        $pid = (int)$parts[1];
                        // Пропускаем важные системные процессы, чтобы их не убили случайно,
                        // а также процесс самого ps.
                        if ($pid <= 5 || str_contains($parts[10], 'ps aux')) {
                            continue;
                        }

                        $processes[] = [
                            'user' => $parts[0],
                            'pid' => $pid,
                            'cpu' => $parts[2],
                            // RSS в KB -> переводим в MB
                            'mem' => round((float)$parts[5] / 1024, 2),
                            'time' => $parts[9],
                            'command' => $parts[10]
                        ];
                    }
                }
            }
        }

        echo json_encode(['success' => true, 'processes' => $processes]);
    }

    public function kill(): void
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $inputRaw = file_get_contents('php://input');
        $input = json_decode($inputRaw, true);
        
        $pids = $input['pids'] ?? [];
        if (empty($pids) || !is_array($pids)) {
            echo json_encode(['success' => false, 'error' => 'Нет PID для завершения']);
            return;
        }

        $helperPath = realpath(__DIR__ . '/../../bin/root_helper.php');
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        if ($isWindows) {
            // Заглушка для Windows
            foreach ($pids as $pid) {
                shell_exec("taskkill /F /PID " . (int)$pid . " 2>nul");
            }
            echo json_encode(['success' => true]);
            return;
        }

        $payload = json_encode([
            'action' => 'kill_process',
            'pids' => array_map('intval', $pids)
        ]);

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
            
            $result = json_decode($output, true);
            if (isset($result['success']) && $result['success']) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Неизвестная ошибка']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Не удалось запустить root_helper']);
        }
    }
}
