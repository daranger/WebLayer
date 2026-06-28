<?php

namespace App\Core;

class Logger
{
    protected static function write(string $path='',$extra = ''): void
    {
        $request = new Request();
        $path = !empty($path) ? $path . '/' : '';


        $data =
            'time: ' . date('d.m.Y H:i:s') .
            "\npage: " . urldecode($request->uri()) .
            ' ' . serialize($_REQUEST) .
            "\nUser-Agent: " . $request->useragent() .
            ' | ip: ' . $request->ip() .
            ' | country: ' . $request->country() .
            $extra .
            "\n\n";

        file_put_contents(
            __DIR__ . '/../../storage/logs/' .$path . date('d.m.y') . '.log',
            $data,
            FILE_APPEND
        );
    }


    public static function info(): void
    {
        $executionTime = (hrtime(true) - $GLOBALS['start']) / 1_000_000_000;
        $extra = "\nExecutionDuration: ".round($executionTime,5)."s";
        ($executionTime > env("SLOW_REQUEST_TIME")) ? self::write('slow-queries', $extra) : self::write();
    }

    public static function dbError(): void
    {
        self::write('db-error');
    }

    public static function rateLimit(): void
    {
        self::write('rate-limits');
    }


    public static function redirect(): void
    {
        self::write('redirects');
    }

    public static function googleUseragent(): void
    {
        self::write('google-useragent');
    }
}