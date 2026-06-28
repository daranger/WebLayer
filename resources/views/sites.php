<div class="page-card">
<div class="toolbar-container">
    <div class="toolbar-left">
        <button class="btn btn-primary open-modal-btn">
            <i class="fa-solid fa-plus"></i> Создать сайт
        </button>
        <div class="btn-group">
            <button class="btn btn-secondary toolbar-edit-btn disabled" title="Изменить" disabled><i class="fa-solid fa-pen"></i></button>
            <button class="btn btn-secondary btn-danger-hover toolbar-delete-btn disabled" title="Удалить" disabled><i class="fa-solid fa-trash"></i></button>
        </div>
        <div class="btn-group">
            <button class="btn btn-secondary toolbar-config-btn disabled" title="Конфиг. файлы" disabled><i class="fa-solid fa-gear"></i></button>
            <button class="btn btn-secondary toolbar-folder-btn disabled" title="Файлы сайта" disabled><i class="fa-solid fa-folder-open"></i></button>
            <button class="btn btn-secondary toolbar-cms-btn disabled" title="CMS" disabled><i class="fa-solid fa-puzzle-piece"></i></button>
        </div>
    </div>

    <div class="toolbar-right">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="siteSearch" placeholder="Поиск (Ctrl + Shift + F)...">
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    window.toggleSiteStatus = async function(id, enable) {
        if (!confirm(enable ? 'Включить сайт?' : 'Отключить сайт? Он будет перенаправлен на страницу-заглушку.')) return;
        try {
            const res = await fetch('/api/sites/toggle', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            });
            const data = await res.json();
            if (data.success) {
                showAlert(data.message, false);
                setTimeout(() => App.navigate('/sites', 'Сайты'), 1000);
            } else {
                showAlert(data.error || 'Ошибка', true);
            }
        } catch (e) {
            showAlert('Ошибка сети', true);
        }
    };
});
</script>

<div class="table-responsive">
    <table class="data-table" id="sitesTable">
        <thead>
        <tr>
            <th width="40"><input type="checkbox" id="selectAllSites"></th>
            <th>Имя</th>
            <th width="60">SSL</th>
            <th>Владелец</th>
            <th>Корневая директория</th>
            <th>IP-адрес</th>
            <th>Обработчик</th>
            <th>Статус</th>
            <th width="50"></th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($sites)): ?>
            <?php foreach ($sites as $site): ?>
                <tr data-site-id="<?= $site->id ?>" data-root-path="<?= htmlspecialchars($site->root_path) ?>">
                    <td><input type="checkbox" class="site-checkbox"></td>
                    <td class="domain-name">
                        <a href="http://<?= $site->domain ?>" target="_blank" rel="noopener">
                            <?= $site->domain ?>
                        </a>
                    </td>
                    <td>
                        <?php if ($site->ssl_status === 'active'): ?>
                            <span style="color: var(--success); font-weight: 500;"><i class="fa-solid fa-circle-check"></i></span>
                        <?php elseif ($site->ssl_status === 'error'): ?>
                            <span style="color: var(--danger); font-weight: 500;"><i class="fa-solid fa-circle-exclamation"></i> Ошибка выпуска</span>
                        <?php else: ?>
                            <span style="color: var(--text-muted);"><i class="fa-solid fa-unlock"></i></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge-owner">root</span></td>
                    <td class="text-muted text-monospace"><?= $site->root_path ?></td>
                    <td><?= htmlspecialchars($site->ip_address ?? 'IP не задан') ?></td>
                    <td>
                        <?php if (strtolower($site->runtime_type) === 'php'): ?>
                            <span class="badge-runtime badge-php"><i class="fa-brands fa-php"></i> PHP <?= $site->runtime_version ?></span>
                        <?php elseif (strtolower($site->runtime_type) === 'nodejs'): ?>
                            <span class="badge-runtime badge-node"><i class="fa-brands fa-node-js"></i> Node <?= $site->runtime_version ?></span>
                        <?php else: ?>
                            <span class="badge-runtime badge-static"><i class="fa-solid fa-code"></i> Static</span>
                        <?php endif; ?>

                        <?php if (!empty($site->cms) && $site->cms !== 'none'): ?>
                            <span class="badge-cms">(<?= htmlspecialchars($site->cms) ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($site->status === 'pending'): ?>
                            <span class="badge-status status-pending" title="Создаётся воркером..."><i class="fa-solid fa-spinner fa-spin"></i> В очереди</span>
                        <?php elseif ($site->status === 'deleting'): ?>
                            <span class="badge-status status-deleting" title="Удаляется воркером..."><i class="fa-solid fa-trash-can fa-bounce"></i> Удаляется</span>
                        <?php elseif ($site->status === 'error'): ?>
                            <span class="badge-status status-error" title="Косяк: <?= htmlspecialchars($site->error_message ?? 'Неизвестная ошибка конфигурации ОС') ?>">
                        <i class="fa-solid fa-circle-xmark"></i> Ошибка
                    </span>
                        <?php elseif (isset($site->is_active) && !$site->is_active): ?>
                            <span class="badge-status" style="background: rgba(255,255,255,0.1); color: var(--text-muted); border: 1px solid var(--border-color);"><i class="fa-solid fa-power-off"></i> Отключен</span>
                        <?php else: ?>
                            <span class="badge-status status-active">Активен</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions-cell">
                        <button class="context-trigger-btn" type="button">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>

                        <div class="context-dropdown" id="dropdown-<?= $site->id ?>">
                            <a href="http://<?= $site->domain ?>" target="_blank" class="dropdown-item"><i class="fa-solid fa-arrow-up-right-from-square"></i> Открыть сайт в браузере</a>
                            <div class="dropdown-divider"></div>
                            <a href="javascript:void(0)" onclick="App.navigate('/sites/edit?id=<?= $site->id ?>', 'Настройки сайта')" class="dropdown-item"><i class="fa-solid fa-pen"></i> Настройки сайта</a>
                            <a href="javascript:void(0)" onclick="toggleSiteStatus(<?= $site->id ?>, <?= (isset($site->is_active) && !$site->is_active) ? 'true' : 'false' ?>)" class="dropdown-item"><i class="fa-solid <?= (isset($site->is_active) && !$site->is_active) ? 'fa-play' : 'fa-pause' ?>"></i> <?= (isset($site->is_active) && !$site->is_active) ? 'Включить сайт' : 'Отключить сайт' ?></a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item text-danger delete-site-btn"><i class="fa-solid fa-trash"></i> Удалить</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="9" class="text-center text-muted">Сайты еще не добавлены.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</div>