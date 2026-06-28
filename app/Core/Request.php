<?php

namespace App\Core;

class Request
{
    private bool $pjax;


    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    public function host(): string
    {
        return $_SERVER['HTTP_HOST'] ?? '';
    }

    public function deviceType(): string
    {
        $ua = strtolower($this->userAgent());

        return preg_match('/mobile|android|iphone/i', $ua)
            ? 'mobile'
            : 'computer';
    }

    public function path(): string
    {
        $path = parse_url(
            $_SERVER['REQUEST_URI'] ?? '/',
            PHP_URL_PATH
        );

        // Заменяет //+ на один /
        return preg_replace('#/+#', '/', $path);
    }
    public function apiPath(): string
    {
        return basename(trim(
            substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), 5),
            '/'
        ));
    }

    public function uri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    public function url(): string
    {
        $scheme = isset($_SERVER['HTTPS'])
            ? 'https'
            : 'http';

        return $scheme . '://' .
            ($_SERVER['HTTP_HOST'] ?? 'localhost') .
            $this->path();
    }

    public function country()
    {
        global $_SESSION;
        return $_SESSION['country'] ?? 'US';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key]
            ?? $_GET[$key]
            ?? $default;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($_POST[$key])
            || isset($_GET[$key]);
    }

    public function referer(): string
    {
        return !empty($_SERVER['HTTP_REFERER'])
            ? urldecode($_SERVER['HTTP_REFERER'])
            : '';
    }

    public function useragent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }
    function json(string $key, $default = null)
    {
        static $data = null;

        if ($data === null) {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
        }

        return $data[$key] ?? $default;
    }
    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }
}