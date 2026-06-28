<div class="page-card">
    <div class="toolbar-container">
        <div class="toolbar-left" style="gap: 4px;">
            <button class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> Добавить сертификат <i class="fa-solid fa-chevron-down" style="font-size: 10px; margin-left: 4px;"></i>
            </button>
            <button class="btn btn-secondary btn-sm disabled" disabled title="Изменить">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button class="btn btn-secondary btn-sm disabled" disabled title="Удалить">
                <i class="fa-solid fa-trash-can"></i>
            </button>
            <button class="btn btn-secondary btn-sm disabled" disabled title="Продлить">
                <i class="fa-solid fa-arrows-rotate"></i> Продлить
            </button>
            <button class="btn btn-secondary btn-sm disabled" disabled title="Заменить">
                <i class="fa-solid fa-right-left"></i> Заменить
            </button>
            <button class="btn btn-secondary btn-sm" title="Журнал">
                <i class="fa-solid fa-file-lines"></i> Журнал
            </button>
            <button class="btn btn-secondary btn-sm" title="CSR запросы">
                <i class="fa-solid fa-file-signature"></i> CSR запросы
            </button>
        </div>
        <div class="toolbar-right">
            <div class="search-box">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="searchSsl" placeholder="Поиск сертификата...">
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
            <tr>
                <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllSsl"></th>
                <th>Имя SSL-сертификата <i class="fa-solid fa-file-contract" style="color: var(--text-muted); margin-left: 4px;"></i></th>
                <th>Владелец</th>
                <th>Действителен до</th>
                <th>Тип</th>
                <th style="text-align: center;">Используется</th>
                <th style="width: 50px;"></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($certificates)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 40px;">
                        SSL-сертификаты пока не добавлены
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($certificates as $cert): ?>
                    <tr data-cert-id="<?= $cert['id'] ?>">
                        <td style="text-align: center;"><input type="checkbox" class="ssl-checkbox"></td>
                        <td class="domain-name">
                            <?= htmlspecialchars($cert['name']) ?>
                        </td>
                        <td><?= htmlspecialchars($cert['owner']) ?></td>
                        <td><?= htmlspecialchars($cert['valid_until']) ?></td>
                        <td>
                            <i class="fa-solid <?= $cert['type_icon'] ?> <?= $cert['type_color'] ?>" style="margin-right: 6px;"></i>
                            <?= htmlspecialchars($cert['type']) ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($cert['in_use']): ?>
                                <i class="fa-solid fa-check" style="color: var(--text-muted);"></i>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <button class="context-trigger-btn" data-dropdown="dropdown-ssl-<?= $cert['id'] ?>">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <div class="context-dropdown" id="dropdown-ssl-<?= $cert['id'] ?>">
                                <a href="#" class="dropdown-item">Данные сертификата</a>
                                <a href="#" class="dropdown-item">Продлить</a>
                                <?php if (isset($cert['db_status']) && $cert['db_status'] === 'failed'): ?>
                                    <a href="#" class="dropdown-item text-warning" onclick="showSslLog('<?= htmlspecialchars($cert['name']) ?>', <?= htmlspecialchars(json_encode($cert['log'] ?? '')) ?>); return false;">
                                        <i class="fa-solid fa-file-lines" style="margin-right: 6px;"></i> Лог ошибки
                                    </a>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <a href="#" class="dropdown-item text-danger">Удалить</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div style="padding: 16px 32px; border-top: 1px solid var(--border-color); color: var(--text-muted); font-size: 13px; display: flex; gap: 16px; background: rgba(0,0,0,0.01);">
        <span>Всего: <?= count($certificates) ?></span>
        <span><i class="fa-solid fa-shield-halved text-success"></i> Существующих: <?= count(array_filter($certificates, fn($c) => $c['type'] === 'Существующий')) ?></span>
        <span><i class="fa-solid fa-certificate text-danger"></i> Самоподписанных: <?= count(array_filter($certificates, fn($c) => $c['type'] === 'Самоподписанный')) ?></span>
    </div>
</div>

<style>
.dropdown-divider {
    height: 1px;
    background-color: var(--border-color);
    margin: 4px 0;
}
.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}
.btn-sm i {
    font-size: 14px;
}
</style>

<!-- Модальное окно для логов SSL -->
<div class="modal-overlay" id="sslLogModal" style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1100;">
    <div class="modal-container" style="background: var(--bg-card); border-radius: 12px; width: 100%; max-width: 900px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); display: flex; flex-direction: column;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--border-color);">
            <h3 style="margin: 0; font-size: 18px; font-weight: 500;">Лог ошибки SSL: <span id="sslLogDomainName" style="color: var(--text-muted);"></span></h3>
            <button class="close-modal" onclick="document.getElementById('sslLogModal').style.display='none'" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 16px;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <pre id="sslLogContent" style="background: #1e1e1e; color: #d4d4d4; padding: 16px; border-radius: 8px; font-family: 'Consolas', 'Courier New', monospace; font-size: 13px; max-height: 500px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; margin: 0; border: 1px solid #333;"></pre>
        </div>
        <div class="modal-footer" style="padding: 15px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; background: rgba(0,0,0,0.02); border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
            <button class="btn btn-secondary" onclick="document.getElementById('sslLogModal').style.display='none'">Закрыть</button>
        </div>
    </div>
</div>

<script>
function showSslLog(domain, logContent) {
    document.getElementById('sslLogDomainName').textContent = domain;
    document.getElementById('sslLogContent').textContent = logContent || 'Лог пуст или отсутствует.';
    document.getElementById('sslLogModal').style.display = 'flex';
}
</script>
