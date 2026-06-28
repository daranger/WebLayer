<?php

declare(strict_types=1);

namespace App\Controllers;

use Predis\ClientInterface;
use App\Core\RateLimiter;

class AuthController
{
    private ClientInterface $redis;
    private RateLimiter $rateLimiter;

    public function __construct(ClientInterface $redis, RateLimiter $rateLimiter)
    {
        $this->redis = $redis;
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * POST /api/auth/login
     */
    public function login(): void
    {
        // 1. 🔒 Защита от брутфорса (теперь Redis активен, лимитер отработает мгновенно)
        $this->rateLimiter->attempt(5, 60);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        $expectedUser = env('PANEL_USER', 'admin');
        $hashedPassword = env('PANEL_PASSWORD_HASH', '');

        if ($username !== $expectedUser || !password_verify($password, $hashedPassword)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Неверный логин или пароль']);
            return;
        }

        // 2FA Verification
        if (env('PANEL_2FA_ENABLED', false)) {
            $code = trim($input['code'] ?? '');
            if (empty($code)) {
                echo json_encode(['success' => false, 'requires_2fa' => true]);
                return;
            }
            
            $secret = env('PANEL_2FA_SECRET', '');
            $qrCodeProvider = new \RobThree\Auth\Providers\Qr\ImageChartsQRCodeProvider();
            $tfa = new \RobThree\Auth\TwoFactorAuth($qrCodeProvider);
            if (!$tfa->verifyCode($secret, $code, 2)) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Неверный код 2FA']);
                return;
            }
        }

        // 3. Сохраняем состояние авторизации для бэкенд-редиректов (F5)
        // Сессия уже запущена Роутером на запись, так как это POST-запрос
        $_SESSION['user_logged'] = true;
        
        // Логируем вход в БД
        try {
            $db = \App\Core\Container::getInstance()->make(\PDO::class);
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $stmt = $db->prepare("INSERT INTO user_logs (username, ip_address) VALUES (?, ?)");
            $stmt->execute([$username, $ip]);
        } catch (\Throwable $e) {
            // Игнорируем ошибки логирования, чтобы не прерывать вход
        }

        // 4. Генерируем токен для твоего JS-подгрузчика (будет жить в localStorage)
        $token = bin2hex(random_bytes(32));

        // Пишем сессию в Redis (теперь без задержек!)
        $this->redis->setex("session:{$token}", 1800, json_encode([
            'user' => $username,
            'logged_at' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]));

        // 5. Отдаем чистый JSON на фронтенд
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'token' => $token,
            'message' => 'Авторизация успешна'
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(): void
    {
        $token = $_SERVER['HTTP_X_PANEL_TOKEN'] ?? '';
        if (!empty($token)) {
            $this->redis->del("session:{$token}");
        }

        // Полностью зачищаем сессию для корректных F5-редиректов
        if (isset($_SESSION['user_logged'])) {
            unset($_SESSION['user_logged']);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Сессия закрыта'], JSON_UNESCAPED_UNICODE);
    }
}