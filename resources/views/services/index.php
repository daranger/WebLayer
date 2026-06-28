<div class="page-card" style="margin: 32px; padding-bottom: 24px;">
    <div class="toolbar-container" style="border-bottom: 1px solid var(--border-color); margin-bottom: 0; padding-bottom: 16px;">
        <div class="toolbar-left">
            <h2 style="margin: 0; font-size: 20px; display: flex; align-items: center; gap: 10px; font-weight: 600;">
                <i class="fa-solid fa-cogs text-primary"></i> Службы
            </h2>
            <div style="display: flex; gap: 8px;">
                <button id="mainToolbarBtn" class="btn btn-secondary btn-sm mass-control-services-btn disabled" data-action="restart" title="Обновить статусы (выберите службы для перезапуска)" disabled style="color: var(--primary, #3B82F6);">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
                <button id="startToolbarBtn" class="btn btn-secondary btn-sm mass-control-services-btn disabled" data-action="start" title="Запустить выбранные" disabled style="color: var(--success, #10B981);">
                    <i class="fa-solid fa-play"></i>
                </button>
                <button id="stopToolbarBtn" class="btn btn-secondary btn-sm mass-control-services-btn disabled" data-action="stop" title="Остановить выбранные" disabled style="color: var(--danger, #EF4444);">
                    <i class="fa-solid fa-stop"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="table-responsive" style="padding-top: 16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 40px;"><input type="checkbox" id="selectAllServices"></th>
                    <th>Название</th>
                    <th>Состояние</th>
                    <th style="text-align: right; width: 60px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $srv): ?>
                    <tr data-service="<?= htmlspecialchars($srv['key']) ?>">
                        <td><input type="checkbox" class="service-checkbox" style="cursor: pointer;"></td>
                        <td>
                            <div style="font-weight: 500; display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-cube text-muted"></i>
                                <?= htmlspecialchars($srv['name']) ?> 
                                <span style="font-size: 11px; color: var(--text-muted);">(<?= htmlspecialchars($srv['key']) ?>)</span>
                            </div>
                        </td>
                        <td>
                            <?php if ($srv['is_active']): ?>
                                <span class="badge-status status-active">
                                    <i class="fa-solid fa-check"></i> Работает
                                </span>
                            <?php else: ?>
                                <span class="badge-status status-error">
                                    <i class="fa-solid fa-xmark"></i> Остановлена
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <button class="context-trigger-btn" data-dropdown="dropdown-srv-<?= htmlspecialchars($srv['key']) ?>">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <div class="context-dropdown" id="dropdown-srv-<?= htmlspecialchars($srv['key']) ?>">
                                <?php if (!$srv['is_active']): ?>
                                    <a href="#" class="dropdown-item control-service-btn" data-service="<?= htmlspecialchars($srv['key']) ?>" data-action="start">
                                        <i class="fa-solid fa-play"></i> Запустить
                                    </a>
                                <?php else: ?>
                                    <a href="#" class="dropdown-item control-service-btn" data-service="<?= htmlspecialchars($srv['key']) ?>" data-action="restart">
                                        <i class="fa-solid fa-rotate-right"></i> Перезапуск
                                    </a>
                                    <a href="#" class="dropdown-item text-danger control-service-btn" data-service="<?= htmlspecialchars($srv['key']) ?>" data-action="stop">
                                        <i class="fa-solid fa-stop"></i> Остановить
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($services)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 40px 0; color: var(--text-muted);">
                            Службы не найдены.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Скрипты перенесены в app.js, так как innerHTML не выполняет теги <script> -->
