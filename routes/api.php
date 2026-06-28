<?php

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\SiteController;
use App\Controllers\SystemController;
use App\Controllers\NotificationController;
use App\Controllers\DatabaseController;

// Auth (registered in web.php)

// System Stats
Router::get('/api/system/stats', [SystemController::class, 'stats']);

// Sites Management
Router::get('/api/sites/list', [SiteController::class, 'index']);
Router::post('/api/sites/create', [SiteController::class, 'store']);
Router::post('/api/sites/delete', [SiteController::class, 'destroy']);

// Databases Management
Router::post('/api/databases/create', [DatabaseController::class, 'store']);
Router::post('/api/databases/delete', [DatabaseController::class, 'destroy']);

// Notifications
Router::get('/api/notifications', [NotificationController::class, 'index']);
Router::post('/api/notifications/read', [NotificationController::class, 'read']);