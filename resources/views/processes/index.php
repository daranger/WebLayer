<div class="page-card" style="margin: 32px; padding-bottom: 24px;">
    <div class="toolbar-container" style="border-bottom: 1px solid var(--border-color); margin-bottom: 0; padding-bottom: 16px;">
        <div class="toolbar-left">
            <h2 style="margin: 0; font-size: 20px; display: flex; align-items: center; gap: 10px; font-weight: 600;">
                <button class="btn btn-secondary btn-sm" onclick="App.navigate('/', 'Дашборд')" title="Назад"><i class="fa-solid fa-arrow-left"></i></button>
                <i class="fa-solid fa-microchip text-primary"></i> Список процессов
            </h2>
            <div style="display: flex; gap: 8px;">
                <button id="killToolbarBtn" class="btn btn-secondary btn-sm disabled" title="Завершить выбранные" disabled style="color: var(--danger, #EF4444);">
                    <i class="fa-solid fa-xmark"></i> Завершить
                </button>
            </div>
        </div>
        <div class="toolbar-right">
            <div class="search-box">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="procSearch" placeholder="Ctrl + Shift + F" autocomplete="off">
            </div>
        </div>
    </div>

    <div class="table-responsive" style="padding-top: 16px;">
        <table class="data-table" id="processesTable">
            <thead>
                <tr>
                    <th style="width: 40px;"><input type="checkbox" id="selectAllProcs"></th>
                    <th data-sort="pid" style="cursor: pointer;">PID <i class="fa-solid fa-sort"></i></th>
                    <th data-sort="user" style="cursor: pointer;">Пользователь <i class="fa-solid fa-sort"></i></th>
                    <th data-sort="cpu" style="cursor: pointer;">Процессор % <i class="fa-solid fa-sort"></i></th>
                    <th data-sort="mem" style="cursor: pointer;">Память (MB) <i class="fa-solid fa-sort"></i></th>
                    <th data-sort="time" style="cursor: pointer;">Время работы <i class="fa-solid fa-sort"></i></th>
                    <th data-sort="command" style="cursor: pointer;">Команда <i class="fa-solid fa-sort"></i></th>
                    <th style="text-align: right; width: 60px;"></th>
                </tr>
            </thead>
            <tbody id="processesList">
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px 0; color: var(--text-muted);">
                        <i class="fa-solid fa-spinner fa-spin"></i> Загрузка процессов...
                    </td>
                </tr>
            </tbody>
        </table>
        <div id="procStatsBar" style="padding: 12px 16px; font-size: 13px; color: var(--text-muted); border-top: 1px solid var(--border-color); display: flex; gap: 16px;">
            <span id="procCountDisplay">Всего: 0</span>
            <span id="procSelectedDisplay">Выделено: 0</span>
            <span id="procCpuDisplay">Процессор %: 0.00</span>
            <span id="procMemDisplay">Память (MB): 0.00</span>
        </div>
    </div>
</div>
