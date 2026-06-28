<?php

use App\Controllers\AuthController;
use App\Controllers\SiteController;
use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\DatabaseController;
use App\Controllers\DatabaseServerController;
use App\Controllers\SslController;
use App\Controllers\FileManagerController;
use App\Controllers\ServiceController;
use App\Controllers\CronController;
use App\Controllers\SettingsController;
use App\Controllers\ProcessController;

// --- АВТОРИЗАЦИЯ ---
$loginPath = '/' . ltrim(env('PANEL_LOGIN_PATH', 'login'), '/');
Router::get($loginPath, [HomeController::class, 'login'], false);
Router::post('/api/auth/login', [AuthController::class, 'login'], false);
Router::post('/api/auth/logout', [AuthController::class, 'logout'], false);


Router::get('/', [HomeController::class, 'dashboard']);
Router::get('/api/dashboard/stats', [HomeController::class, 'stats']);

Router::get('/sites', [SiteController::class, 'index']);
Router::post('/api/sites', [SiteController::class, 'store']);
Router::get('/sites/create', [SiteController::class, 'create']);
Router::get('/sites/edit', [SiteController::class, 'edit']);
Router::post('/api/sites/update', [SiteController::class, 'update']);
Router::post('/api/sites/toggle', [SiteController::class, 'toggle']);
Router::get('/api/sites/config', [SiteController::class, 'getConfig']);
Router::post('/api/sites/config', [SiteController::class, 'saveConfig']);
Router::delete('/api/sites', [SiteController::class, 'destroy']);

Router::get('/databases', [DatabaseController::class, 'index']);
Router::get('/databases/create', [DatabaseController::class, 'create']);
Router::get('/databases/servers', [DatabaseServerController::class, 'index']);
Router::post('/api/database-servers', [DatabaseServerController::class, 'create']);
Router::post('/api/database-servers/update', [DatabaseServerController::class, 'update']);
Router::delete('/api/database-servers/delete', [DatabaseServerController::class, 'delete']);
Router::get('/api/database-servers/get', [DatabaseServerController::class, 'get']);
Router::get('/api/database-servers/migrate', [DatabaseServerController::class, 'migrate'], false);
Router::post('/api/database-servers/test', [DatabaseServerController::class, 'testConnection']);


Router::get('/ssl', [SslController::class, 'index']);

Router::get('/services', [ServiceController::class, 'index']);
Router::post('/api/services/control', [ServiceController::class, 'apiControl']);
Router::post('/api/system/reboot', [ServiceController::class, 'reboot']);

Router::get('/processes', [ProcessController::class, 'index']);
Router::get('/api/processes', [ProcessController::class, 'list']);
Router::post('/api/processes/kill', [ProcessController::class, 'kill']);

Router::get('/cron', [\App\Controllers\CronController::class, 'index']);
Router::get('/cron/create', [\App\Controllers\CronController::class, 'create']);
Router::post('/api/cron', [\App\Controllers\CronController::class, 'store']);
Router::post('/api/cron/toggle', [\App\Controllers\CronController::class, 'toggle']);
Router::post('/api/cron/run', [\App\Controllers\CronController::class, 'run']);
Router::post('/api/cron/update', [\App\Controllers\CronController::class, 'update']);
Router::delete('/api/cron', [\App\Controllers\CronController::class, 'destroy']);
Router::get('/cron/edit', [\App\Controllers\CronController::class, 'edit']);

Router::get('/manager', [FileManagerController::class, 'index']);
Router::post('/api/manager/create', [FileManagerController::class, 'create']);
Router::post('/api/manager/delete', [FileManagerController::class, 'delete']);
Router::post('/api/manager/upload', [FileManagerController::class, 'upload']);
Router::get('/api/manager/attributes', [FileManagerController::class, 'getAttributes']);
Router::post('/api/manager/attributes', [FileManagerController::class, 'updateAttributes']);
Router::get('/api/manager/download', [FileManagerController::class, 'download']);
Router::get('/api/manager/read', [FileManagerController::class, 'read']);
Router::post('/api/manager/write', [FileManagerController::class, 'write']);
Router::get('/api/manager/tree', [FileManagerController::class, 'tree']);
Router::post('/api/manager/search', [FileManagerController::class, 'search']);
Router::post('/api/manager/compress', [FileManagerController::class, 'compress']);
Router::post('/api/manager/extract', [FileManagerController::class, 'extract']);
Router::post('/api/manager/copy', [FileManagerController::class, 'copy']);
Router::post('/api/manager/move', [FileManagerController::class, 'move']);

Router::get('/settings', [SettingsController::class, 'index']);
Router::post('/api/settings/update', [SettingsController::class, 'update']);

Router::get('/settings/2fa', [SettingsController::class, 'setup2fa']);
Router::post('/api/settings/2fa/verify', [SettingsController::class, 'verify2fa']);
Router::post('/api/settings/2fa/disable', [SettingsController::class, 'disable2fa']);