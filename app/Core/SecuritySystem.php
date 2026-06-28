<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\CloudflareManager;
use Predis\ClientInterface;

class SecuritySystem
{
    private ClientInterface $redis;
    private CloudflareManager $cloudflare;

    public function __construct(ClientInterface $redis, CloudflareManager $cloudflare)
    {
        $this->redis = $redis;
        $this->cloudflare = $cloudflare;
    }

    public function banIp(string $ip, string $reason = 'Bruteforce/Spam'): void
    {
        // 1. Всегда пишем в Redis бан на 24 часа
        $this->redis->setex("banned:ip:{$ip}", 86400, $reason);

        // 2. Fail2Ban через UFW (включается только если разрешено в .env)
        if (env('FIREWALL_UFW_ENABLED', false)) {
            $command = "sudo ufw deny from " . escapeshellarg($ip) . " to any comment " . escapeshellarg($reason);
            exec($command . " > /dev/null 2>&1 &");
        }

        // 3. Блокировка на уровне Cloudflare WAF
        if (env('CLOUDFLARE_ENABLED', false)) {
            try {
                $this->cloudflare->blockIp($ip, "Panel Fail2Ban: " . $reason);
            } catch (\Throwable $e) {
                // Если токен невалидный, не роняем приложение
            }
        }
    }

    public function isBanned(string $ip): bool
    {
        return (bool) $this->redis->exists("banned:ip:{$ip}");
    }
}