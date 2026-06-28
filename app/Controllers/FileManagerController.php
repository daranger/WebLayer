<?php

namespace App\Controllers;

use App\Services\FileManager;
use App\Core\Container;
use Exception;

class FileManagerController
{
    private FileManager $fileManager;

    public function __construct()
    {
        $container = Container::getInstance();
        $this->fileManager = $container->make(FileManager::class);
    }

    public function index(): void
    {
        $path = $_GET['path'] ?? '/';

        try {
            $data = $this->fileManager->getList($path);
            $path = $data['target_dir']; // Update path to real absolute path
        } catch (Exception $e) {
            $data = ['files' => [], 'total_items' => 0, 'total_size' => '0 B', 'target_dir' => $path, 'error' => $e->getMessage()];
        }

        echo view('manager', [
            'title' => 'Менеджер файлов - SiteManager',
            'files' => $data['files'],
            'current_path' => $path,
            'total_items' => $data['total_items'],
            'total_size' => $data['total_size'],
            'error' => $data['error'] ?? null
        ]);
    }

    public function create(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $path = $input['path'] ?? '/';
            $name = $input['name'] ?? '';
            $type = $input['type'] ?? 'file';

            if (empty($name)) throw new Exception('Имя не может быть пустым');

            if ($type === 'folder') {
                $this->fileManager->createFolder($path, $name);
            } else {
                $this->fileManager->createFile($path, $name);
            }

            echo json_encode(['success' => true, 'message' => 'Успешно создано']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function delete(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $path = $input['path'] ?? '/';
            $name = $input['name'] ?? '';

            if (empty($name)) throw new Exception('Имя не указано');

            $this->fileManager->delete($path, $name);
            echo json_encode(['success' => true, 'message' => 'Успешно удалено']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function upload(): void
    {
        try {
            $path = $_POST['path'] ?? '/';
            $url = $_POST['url'] ?? '';

            if (!empty($_FILES['file'])) {
                if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    $errCode = $_FILES['file']['error'];
                    throw new Exception("Ошибка загрузки файла (Код: $errCode). Возможно, файл слишком велик.");
                }
                $this->fileManager->uploadLocal($path, $_FILES['file']);
            } elseif (!empty($url)) {
                $this->fileManager->uploadUrl($path, $url);
            } else {
                throw new Exception('Файл или URL не указан');
            }

            echo json_encode(['success' => true, 'message' => 'Файл успешно загружен']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getAttributes(): void
    {
        try {
            $path = $_GET['path'] ?? '/';
            $name = $_GET['name'] ?? '';
            if (empty($name)) throw new Exception('Имя файла не указано');
            
            $data = $this->fileManager->getAttributes($path, $name);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function updateAttributes(): void
    {
        try {
            $path = $_POST['path'] ?? '/';
            $oldName = $_POST['old_name'] ?? '';
            
            if (empty($oldName)) throw new Exception('Имя файла не указано');
            
            $this->fileManager->updateAttributes($path, $oldName, $_POST);
            echo json_encode(['success' => true, 'message' => 'Атрибуты успешно обновлены']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function download(): void
    {
        try {
            $path = $_GET['path'] ?? '/';
            $name = $_GET['name'] ?? '';
            if (empty($name)) throw new Exception('Имя файла не указано');
            
            $content = $this->fileManager->readLocal($path, $name);
            
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($name).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . strlen($content));
            echo $content;
            exit;
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function read(): void
    {
        try {
            $path = $_GET['path'] ?? '/';
            $name = $_GET['name'] ?? '';
            if (empty($name)) throw new Exception('Имя файла не указано');
            
            $content = $this->fileManager->readLocal($path, $name);
            echo json_encode(['success' => true, 'content' => $content]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function write(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $path = $input['path'] ?? '/';
            $name = $input['name'] ?? '';
            $content = $input['content'] ?? '';
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Имя файла не указано']);
                return;
            }
            
            $this->fileManager->writeLocal($path, $name, $content);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function search(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $path = $input['path'] ?? '/';
            $mask = $input['mask'] ?? '*';
            $recursive = !empty($input['recursive']);
            $contentText = $input['contentText'] ?? '';
            
            $files = $this->fileManager->searchLocal($path, $mask, $recursive, $contentText);
            echo json_encode(['success' => true, 'files' => $files]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function compress(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $path = $input['path'] ?? '/';
            $files = $input['files'] ?? [];
            $archiveName = $input['archive_name'] ?? 'archive.zip';
            $archiveType = $input['archive_type'] ?? 'zip';
            $deleteFiles = !empty($input['delete_files']);
            
            $this->fileManager->compressLocal($path, $files, $archiveName, $archiveType, $deleteFiles);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function extract(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $path = $input['path'] ?? '/';
            $file = $input['file'] ?? '';
            
            $this->fileManager->extractLocal($path, $file);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function tree(): void
    {
        try {
            $path = $_GET['path'] ?? '/';
            $result = $this->fileManager->listDirsLocal($path);
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function copy(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $items = $input['items'] ?? [];
            $target = $input['target'] ?? '/';
            $overwrite = !empty($input['overwrite']);
            
            $this->fileManager->copyLocal($items, $target, $overwrite);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function move(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $items = $input['items'] ?? [];
            $target = $input['target'] ?? '/';
            $overwrite = !empty($input['overwrite']);
            
            $this->fileManager->moveLocal($items, $target, $overwrite);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
