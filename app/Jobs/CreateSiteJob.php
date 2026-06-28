<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SiteService;

class CreateSiteJob
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
            $this->siteService->create($payload);
        } catch (\Throwable $e) {
            var_dump($e->getMessage());
            throw $e;
        }
    }
}