<?php

namespace App\Services;

class CloudflareManager
{
    private string $apiToken;

    public function __construct(string $apiToken)
    {
        $this->apiToken = $apiToken;
    }

    /**
     * Универсальный метод для отправки запросов к API Cloudflare
     */
    private function request(string $endpoint, string $method = 'GET', array $data = []): array
    {
        $url = "https://api.cloudflare.com/client/v4/" . $endpoint;
        $ch = curl_init();

        $headers = [
            "Authorization: Bearer {$this->apiToken}",
            "Content-Type: application/json"
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }

    /**
     * Создать DNS-запись (A)
     */
    public function createARecord(string $zoneId, string $name, string $ip, bool $proxied = true): bool
    {
        $endpoint = "zones/{$zoneId}/dns_records";
        $payload = [
            'type'    => 'A',
            'name'    => $name,    // например, 'mysite.com' или '@'
            'content' => $ip,      // твой белый IP
            'ttl'     => 1,       // 1 = Auto
            'proxied' => $proxied  // Проксирование (оранжевое облако)
        ];

        $result = $this->request($endpoint, 'POST', $payload);
        return reset($result['success']);
    }

    /**
     * Очистить кэш домена (Purge Everything)
     */
    public function purgeCache(string $zoneId): bool
    {
        $endpoint = "zones/{$zoneId}/purge_cache";
        $payload = ['purge_everything' => true];

        $result = $this->request($endpoint, 'POST', $payload);
        return (bool)($result['success'] ?? false);
    }
}