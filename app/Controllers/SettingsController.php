<?php

namespace App\Controllers;

use Exception;

class SettingsController
{
    public function index(): void
    {
        $currentUser = env('PANEL_USER', 'admin');
        $currentPath = env('PANEL_LOGIN_PATH', 'login');
        $allowedIps = env('PANEL_ALLOWED_IPS', '0.0.0.0/0');
        $twoFactorEnabled = env('PANEL_2FA_ENABLED', false) ? 'true' : 'false';

        echo view('settings', [
            'title' => 'Настройки пользователя - ' . htmlspecialchars($currentUser),
            'username' => $currentUser,
            'login_path' => $currentPath,
            'allowed_ips' => $allowedIps,
            'two_factor_enabled' => $twoFactorEnabled
        ]);
    }

    public function update(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception("Неверный формат данных");
            }

            $username = trim($input['username'] ?? '');
            $currentPassword = $input['current_password'] ?? '';
            $newPassword = $input['new_password'] ?? '';
            $confirmPassword = $input['confirm_password'] ?? '';
            $allowedIps = trim($input['allowed_ips'] ?? '0.0.0.0/0');
            $loginPath = trim($input['login_path'] ?? 'login');

            if (empty($username)) {
                throw new Exception("Имя пользователя не может быть пустым");
            }

            if (empty($loginPath)) {
                $loginPath = 'login'; // Fallback
            }

            // Проверяем текущий пароль для любых изменений
            $expectedUser = env('PANEL_USER', 'admin');
            $hashedPassword = env('PANEL_PASSWORD_HASH', '');
            
            // Если пытаемся изменить данные, нужен пароль!
            // Wait, to enforce security, we ALWAYS require the current password to save settings.
            if (empty($currentPassword)) {
                throw new Exception("Введите текущий пароль для подтверждения изменений");
            }

            if (!password_verify($currentPassword, (string)$hashedPassword)) {
                throw new Exception("Неверный текущий пароль");
            }

            $envUpdates = [
                'PANEL_USER' => $username,
                'PANEL_ALLOWED_IPS' => $allowedIps,
                'PANEL_BIND_IP_SESSION' => ($input['bind_session'] ?? false) ? 'true' : 'false'
            ];

            // Меняем пароль, если запрошено
            if (!empty($newPassword)) {
                if ($newPassword !== $confirmPassword) {
                    throw new Exception("Новые пароли не совпадают");
                }
                if (strlen($newPassword) < 6) {
                    throw new Exception("Новый пароль слишком короткий (минимум 6 символов)");
                }
                $envUpdates['PANEL_PASSWORD_HASH'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }

            $this->updateEnv($envUpdates);

            echo json_encode(['success' => true, 'message' => 'Настройки успешно сохранены']);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function setup2fa(): void
    {
        try {
            $qrCodeProvider = new \RobThree\Auth\Providers\Qr\ImageChartsQRCodeProvider();
            $tfa = new \RobThree\Auth\TwoFactorAuth($qrCodeProvider, 'SiteManager (' . env('PANEL_USER', 'admin') . ')');
            
            // Generate new secret
            $secret = $tfa->createSecret();
            
            $qrtext = $tfa->getQRText('SiteManager (' . env('PANEL_USER', 'admin') . ')', $secret);
            $qrCodeData = (new \chillerlan\QRCode\QRCode)->render($qrtext);
            
            echo view('settings_2fa', [
                'title' => 'Настройка 2FA',
                'secret' => $secret,
                'qrCode' => $qrCodeData,
                'isEnabled' => env('PANEL_2FA_ENABLED', false)
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Internal Server Error',
                'details' => $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function verify2fa(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $secret = $input['secret'] ?? '';
            $code = $input['code'] ?? '';
            $password = $input['password'] ?? '';

            if (empty($secret) || empty($code) || empty($password)) {
                throw new Exception("Заполните все поля");
            }

            // Verify password
            $hashedPassword = env('PANEL_PASSWORD_HASH', '');
            if (!password_verify($password, (string)$hashedPassword)) {
                throw new Exception("Неверный текущий пароль");
            }

            $qrCodeProvider = new \RobThree\Auth\Providers\Qr\ImageChartsQRCodeProvider();
            $tfa = new \RobThree\Auth\TwoFactorAuth($qrCodeProvider);
            $result = $tfa->verifyCode($secret, $code, 2); // 2 intervals leeway

            if ($result) {
                $this->updateEnv([
                    'PANEL_2FA_ENABLED' => 'true',
                    'PANEL_2FA_SECRET' => $secret
                ]);
                echo json_encode(['success' => true, 'message' => '2FA успешно включена']);
            } else {
                throw new Exception("Неверный код из приложения");
            }

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function disable2fa(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $password = $input['password'] ?? '';

            if (empty($password)) {
                throw new Exception("Введите текущий пароль");
            }

            $hashedPassword = env('PANEL_PASSWORD_HASH', '');
            if (!password_verify($password, (string)$hashedPassword)) {
                throw new Exception("Неверный текущий пароль");
            }

            $this->updateEnv([
                'PANEL_2FA_ENABLED' => 'false',
                'PANEL_2FA_SECRET' => ''
            ]);
            echo json_encode(['success' => true, 'message' => '2FA успешно отключена']);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function updateEnv(array $data): void
    {
        $envFile = __DIR__ . '/../../.env';
        if (!file_exists($envFile)) {
            throw new Exception("Файл .env не найден");
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES);
        $newLines = [];
        $updatedKeys = [];

        foreach ($lines as $line) {
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                if (array_key_exists($key, $data)) {
                    $newLines[] = $key . '=' . $data[$key];
                    $updatedKeys[] = $key;
                    continue;
                }
            }
            $newLines[] = $line;
        }

        // Add any missing keys
        foreach ($data as $key => $value) {
            if (!in_array($key, $updatedKeys)) {
                $newLines[] = $key . '=' . $value;
            }
        }

        file_put_contents($envFile, implode(PHP_EOL, $newLines) . PHP_EOL);
    }
}
