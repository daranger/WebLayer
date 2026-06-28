
<style>
    /* Grid layout for 3 columns on wide monitors */
    @media (min-width: 1200px) {
        .dashboard-grid {
            grid-template-columns: repeat(3, 1fr) !important;
        }
        .card.full-width {
            grid-column: span 3 !important;
        }
    }

    /* Chart Legend alignment */
    .chart-legend-container {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin-top: 16px;
        font-size: 12px;
        justify-content: center;
    }
    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text-muted);
    }
    .legend-color {
        width: 12px;
        height: 12px;
        border-radius: 3px;
    }

    .info-table th {
        text-align: left;
        color: var(--text-muted);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border-color);
    }
</style>

<div class="dashboard-grid">

<div class="card full-width">
    <div class="card-header">Быстрые действия</div>
    <div class="quick-actions">
        <button class="action-btn" onclick="if(typeof App !== 'undefined') App.navigate('/sites/create', 'Новый сайт')"><i class="fa-solid fa-plus"></i> Создать сайт</button>
        <button class="action-btn" onclick="if(typeof App !== 'undefined') App.navigate('/databases/create', 'Создать базу данных')"><i class="fa-solid fa-database"></i> Новая БД</button>
        <button class="action-btn" onclick="window.open('<?= env('PANEL_PMA_PATH', '/phpmyadmin') ?>', '_blank')"><i class="fa-solid fa-server"></i> phpMyAdmin</button>
        <button class="action-btn" onclick="window.rebootServer()"><i class="fa-solid fa-power-off"></i> Перезагрузка сервера</button>
    </div>
</div>

<div class="card">
    <div class="card-header">Ресурсы сервера</div>

    <div style="height: 140px; width: 100%; position: relative;">
        <canvas id="serverResourcesChart"></canvas>
    </div>

    <div class="chart-legend-container">
        <div class="legend-item">
            <div class="legend-color" style="background-color: var(--primary-color);"></div>
            <span>Диск, %</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background-color: #eab308;"></div>
            <span>RAM, %</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background-color: var(--success);"></div>
            <span>CPU, %</span>
        </div>
    </div>

    <div class="resources-table">

        <div class="resources-row">
            <div class="resources-cell cell-label">Процессор (CPU)</div>
            <div class="resources-cell cell-current" id="cpu-val-current">0%</div>
            <div class="resources-cell cell-progress">
                <div class="progress-bar">
                    <div class="progress" id="cpu-progress" style="width: 0%"></div>
                </div>
            </div>
            <div class="resources-cell cell-total" id="cpu-val-total">LA: 0.0</div>
        </div>

        <div class="resources-row">
            <div class="resources-cell cell-label">Оперативная память</div>
            <div class="resources-cell cell-current" id="ram-val-current">0 GB (0%)</div>
            <div class="resources-cell cell-progress">
                <div class="progress-bar">
                    <div class="progress" id="ram-progress" style="width: 0%"></div>
                </div>
            </div>
            <div class="resources-cell cell-total" id="ram-val-total">0 GB</div>
        </div>

        <div class="resources-row">
            <div class="resources-cell cell-label">Дисковое пространство</div>
            <div class="resources-cell cell-current" id="disk-val-current">0 GB (0%)</div>
            <div class="resources-cell cell-progress">
                <div class="progress-bar">
                    <div class="progress" id="disk-progress" style="width: 0%"></div>
                </div>
            </div>
            <div class="resources-cell cell-total" id="disk-val-total">0 GB</div>
        </div>

    </div>
</div>

<div class="card">
    <div class="card-header">Программное обеспечение сервера</div>
    <table class="info-table">
        <tr><td>OS</td><td id="sys-os-name">Загрузка...</td></tr>
        <tr><td>Nginx</td><td id="soft-nginx-version">Загрузка...</td></tr>
        <tr><td>MySQL / MariaDB</td><td id="soft-mysql-version">Загрузка...</td></tr>
        <tr><td>Redis Server</td><td id="soft-redis-version">Загрузка...</td></tr>
        <tr><td>PHP</td><td id="soft-php-version">Загрузка...</td></tr>
        <tr><td>Kernel</td><td id="soft-kernel" class="text-monospace">Загрузка...</td></tr>
    </table>
</div>

<div class="card">
    <div class="card-header">Информация о системе</div>
    <table class="info-table">
        <tr><td>Процессор</td><td id="sys-processor">Загрузка...</td></tr>
        <tr><td>Оперативная память</td><td id="sys-ram-summary">0 MB</td></tr>
        <tr><td>Размер диска</td><td id="sys-disk-summary">0.00 GB</td></tr>
        <tr><td>Время работы</td><td id="sys-uptime">0 hours</td></tr>
        <tr id="row-processes" style="cursor: pointer;" title="Открыть список процессов"><td>Процессы <i class="fa-solid fa-link" style="font-size: 10px; color: var(--text-muted); margin-left: 4px;"></i></td><td style="font-weight: 600; color: var(--primary-color);" id="sys-processes">0</td></tr>
    </table>
</div>

<div class="card">
    <div class="card-header">Фоновые задания</div>
    <table class="info-table" id="dashboard-jobs-table">
        <thead>
        <tr>
            <th>Имя скрипта</th>
            <th style="text-align: right;">Статус</th>
        </tr>
        </thead>
        <tbody>
        <tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 14px 0;">Загрузка задач...</td></tr>
        </tbody>
    </table>
</div>

<div class="card" style="grid-column: span 2;">
    <div class="card-header">Журнал посещений</div>
    <table class="info-table" id="dashboard-logs-table">
        <thead>
        <tr>
            <th>Время</th>
            <th style="text-align: center;">Пользователь</th>
            <th style="text-align: right;">Удалённый IP-адрес</th>
        </tr>
        </thead>
        <tbody>
        <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 14px 0;">Загрузка логов...</td></tr>
        </tbody>
    </table>
</div>

</div>
