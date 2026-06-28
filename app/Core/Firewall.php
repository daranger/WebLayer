<?php


namespace App\Core;


class Firewall

{

    public static bool $pjax = false;

    public static function handle(): void
    {
        $request = new Request();

        self::$pjax = $request->get('pjax') === 'true';

        self::redirects($request);
        self::blockBadBots($request);
        self::seoHeaders($request);
    }

    public static function redirect(string $url, int $code = 301): never
    {
        header('Location: ' . $url, true, $code);
        exit;
    }

    private static function redirects($request): void
    {


        $uri = $request->uri();

        if ($uri === '/index.php') {
            self::redirect('/');
        }


        if (str_contains($uri, '?fbclid=')) {
            self::redirect(explode('?fbclid=', $uri)[0]);
        }

        if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
            self::redirect('https://' . $request->host() . $uri);
        }
    }

    private static function blockBadBots(Request $request): void

    {

        $ua = $request->useragent();


        if (!$ua && !admin()) {

            self::deny();

        }


        if (preg_match(
            '/SLEEP\(|^curl\/|SeekportBot|PetalBot|GynxBot|MauiBot|Konturbot|BLEXBot/i',
            $ua
        )) {
            self::deny();
        }

    }


    private static function seoHeaders($request): void

    {

        $request->isPost()

        && header('X-Robots-Tag: noindex, nofollow');

    }


    private static function deny(): never

    {
        http_response_code(403);
        require __DIR__ . '/../../resources/views/errors/403.html';
        exit;
    }

}