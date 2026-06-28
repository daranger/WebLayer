<?php
$isActive = $templateData['is_active'] ?? true;
$forceHttps = $templateData['force_https'] ?? true;
?>
server {
    listen 80;
    server_name <?php echo $templateData['domain']; ?> www.<?php echo $templateData['domain']; ?>;

<?php if ($forceHttps): ?>
    # Перенаправление всех HTTP запросов на HTTPS
    location / {
        return 301 https://$host$request_uri;
    }

    # Исключение для Certbot
    location ^~ /.well-known/acme-challenge/ {
        allow all;
        default_type "text/plain";
    }
<?php else: ?>
    root <?php echo $templateData['rootDir']; ?>;
    index index.php index.html index.htm;

<?php if (!$isActive): ?>
    location / {
        default_type text/html;
        return 200 '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Сайт отключен</title><style>body{background:#f4f4f5;color:#3f3f46;font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}</style></head><body><div style="text-align:center;background:#fff;padding:40px;border-radius:8px;box-shadow:0 4px 6px -1px rgba(0,0,0,.1);"><h2>Сайт временно отключен</h2><p style="color:#71717a">Доступ к сайту ограничен администратором (WebLayer).</p></div></body></html>';
    }
<?php else: ?>
<?php if (isset($templateData['runtime']) && strtolower($templateData['runtime']['type']) === 'php'): ?>
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php<?php echo $templateData['runtime']['version']; ?>-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
<?php elseif (isset($templateData['runtime']) && strtolower($templateData['runtime']['type']) === 'nodejs'): ?>
    location / {
        proxy_pass http://127.0.0.1:<?php echo $templateData['runtime']['port'] ?? 3000; ?>;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
<?php else: ?>
    location / {
        try_files $uri $uri/ =404;
    }
<?php endif; ?>
<?php endif; ?>

    # Исключение для Certbot
    location ^~ /.well-known/acme-challenge/ {
        allow all;
        default_type "text/plain";
    }
<?php endif; ?>
}

server {
    listen 443 ssl http2;
    server_name <?php echo $templateData['domain']; ?> www.<?php echo $templateData['domain']; ?>;

    root <?php echo $templateData['rootDir']; ?>;
    index index.php index.html index.htm;

    ssl_certificate /etc/letsencrypt/live/<?php echo $templateData['domain']; ?>/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/<?php echo $templateData['domain']; ?>/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers "ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384";

    access_log /var/log/nginx/<?php echo $templateData['domain']; ?>.access.log;
    error_log /var/log/nginx/<?php echo $templateData['domain']; ?>.error.log;

<?php if (!$isActive): ?>
    location / {
        default_type text/html;
        return 200 '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Сайт отключен</title><style>body{background:#f4f4f5;color:#3f3f46;font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}</style></head><body><div style="text-align:center;background:#fff;padding:40px;border-radius:8px;box-shadow:0 4px 6px -1px rgba(0,0,0,.1);"><h2>Сайт временно отключен</h2><p style="color:#71717a">Доступ к сайту ограничен администратором (WebLayer).</p></div></body></html>';
    }
<?php else: ?>
<?php if (isset($templateData['runtime']) && strtolower($templateData['runtime']['type']) === 'php'): ?>
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php<?php echo $templateData['runtime']['version']; ?>-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
<?php elseif (isset($templateData['runtime']) && strtolower($templateData['runtime']['type']) === 'nodejs'): ?>
    location / {
        proxy_pass http://127.0.0.1:<?php echo $templateData['runtime']['port'] ?? 3000; ?>;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
<?php else: ?>
    location / {
        try_files $uri $uri/ =404;
    }
<?php endif; ?>
<?php endif; ?>

    location ~ /\. {
        deny all;
    }
}
