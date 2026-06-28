<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SiteService;

class RebuildSiteConfigJob
{
    private SiteService $siteService;

    /**
     * DI-контейнер автоматически внедрит сюда наш бронебойный сервис
     */
    public function __construct(SiteService $siteService)
    {
        $this->siteService = $siteService;
    }

    /**
     * Точка входа для воркера
     */
    public function handle(array $payload): void
    {
        try {
            $this->siteService->rebuildConfig($payload);
        } catch (\Throwable $e) {
            var_dump($e->getMessage());
            throw $e;
        }
    }
}