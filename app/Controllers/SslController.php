<?php

namespace App\Controllers;

use App\Core\Container;
use PDO;

class SslController
{
    public function index(): void
    {
        $container = Container::getInstance();
        $db = $container->make(PDO::class);
        $stmt = $db->query("SELECT id, domain, workspace_id, ssl_status, ssl_log FROM sites");
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $certificates = [];
        $idCounter = 1;

        foreach ($sites as $site) {
            $domain = $site['domain'];
            $foundInNginx = false;
            
            // Пробуем получить сертификат от локального Nginx
            $streamContext = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'SNI_enabled' => true,
                    'peer_name' => $domain
                ]
            ]);

            // Таймаут 1 секунда, чтобы не вешать панель
            $client = @stream_socket_client("ssl://127.0.0.1:443", $errno, $errstr, 1, STREAM_CLIENT_CONNECT, $streamContext);
            if ($client) {
                $params = stream_context_get_params($client);
                $certResource = $params['options']['ssl']['peer_certificate'] ?? null;
                
                if ($certResource) {
                    $certInfo = openssl_x509_parse($certResource);
                    if ($certInfo) {
                        // Проверяем, что сертификат действительно для этого домена, а не дефолтный fall-back
                        $cn = $certInfo['subject']['CN'] ?? '';
                        $san = $certInfo['extensions']['subjectAltName'] ?? '';
                        
                        if ($cn === $domain || str_contains($san, $domain)) {
                            $issuer = $certInfo['issuer']['O'] ?? 'Unknown';
                            $isLetsEncrypt = str_contains($issuer, "Let's Encrypt") || str_contains($issuer, "ISRG");
                            
                            $type = $isLetsEncrypt ? "Let's Encrypt" : "Самоподписанный";
                            $type_icon = $isLetsEncrypt ? 'fa-shield-halved' : 'fa-certificate';
                            $type_color = $isLetsEncrypt ? 'text-success' : 'text-danger';

                            $certificates[] = [
                                'id' => $idCounter++,
                                'name' => $domain,
                                'owner' => $site['workspace_id'],
                                'valid_until' => date('Y-m-d', $certInfo['validTo_time_t']),
                                'type' => $type,
                                'type_icon' => $type_icon,
                                'type_color' => $type_color,
                                'in_use' => true,
                                'log' => $site['ssl_log'] ?? '',
                                'db_status' => $site['ssl_status']
                            ];
                            $foundInNginx = true;
                        }
                    }
                }
                fclose($client);
            }
            
            // Если сертификата в Nginx нет, но в базе статус failed/issuing, добавляем заглушку
            if (!$foundInNginx && in_array($site['ssl_status'], ['failed', 'issuing'])) {
                $type = $site['ssl_status'] === 'failed' ? 'Ошибка выпуска' : 'В процессе';
                $type_icon = $site['ssl_status'] === 'failed' ? 'fa-triangle-exclamation' : 'fa-spinner fa-spin';
                $type_color = $site['ssl_status'] === 'failed' ? 'text-danger' : 'text-warning';
                
                $certificates[] = [
                    'id' => $idCounter++,
                    'name' => $domain,
                    'owner' => $site['workspace_id'],
                    'valid_until' => '—',
                    'type' => $type,
                    'type_icon' => $type_icon,
                    'type_color' => $type_color,
                    'in_use' => false,
                    'log' => $site['ssl_log'] ?? 'Лог отсутствует',
                    'db_status' => $site['ssl_status']
                ];
            }
        }

        echo view('ssl', [
            'title' => 'SSL-сертификаты - SiteManager',
            'certificates' => $certificates
        ]);
    }
}
