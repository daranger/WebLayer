<div class="form-page-container">
    <div class="page-card" style="padding: 32px; margin: 0;">
    <div class="form-page-header">
        <h2 style="margin: 0; font-size: 20px; display: flex; align-items: center; gap: 10px; font-weight: 600;">
            Редактирование сайта: <?= htmlspecialchars($site->domain) ?>
        </h2>
    </div>

    <div style="border-bottom: 1px solid var(--border-color); display: flex; gap: 20px; margin-bottom: 24px;">
        <div class="tab-item active" onclick="switchTab('settings')" id="tab-settings" style="padding: 16px 0; font-weight: 500; cursor: pointer; border-bottom: 2px solid var(--primary-color); color: var(--primary-color);">
            <i class="fa-solid fa-sliders"></i> Настройки
        </div>
        <div class="tab-item" onclick="switchTab('config')" id="tab-config" style="padding: 16px 0; font-weight: 500; cursor: pointer; color: var(--text-muted);">
            <i class="fa-brands fa-linux"></i> Nginx Конфиг
        </div>
    </div>

    <!-- Вкладка настроек -->
    <div id="content-settings">
        <form id="editSiteForm" class="panel-form" onsubmit="submitEditSite(event)">
            <input type="hidden" id="siteId" value="<?= $site->id ?>">

            <div class="form-group-row">
                <label>Домен (только для чтения)</label>
                <input type="text" value="<?= htmlspecialchars($site->domain) ?>" disabled style="background: rgba(0,0,0,0.02); cursor: not-allowed;">
            </div>

            <div class="form-group-row">
                <label>Среда выполнения (Runtime)</label>
                <select id="runtimeType" disabled style="background: rgba(0,0,0,0.02); cursor: not-allowed;">
                    <option value="php" <?= strtolower($site->runtime_type) === 'php' ? 'selected' : '' ?>>PHP (FastCGI)</option>
                    <option value="nodejs" <?= strtolower($site->runtime_type) === 'nodejs' ? 'selected' : '' ?>>Node.js</option>
                    <option value="static" <?= strtolower($site->runtime_type) === 'static' ? 'selected' : '' ?>>Статика (HTML/JS/CSS)</option>
                </select>
                <small style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">Среду выполнения пока нельзя изменить после создания</small>
            </div>

            <div class="form-group-row">
                <label>Версия</label>
                <select id="runtimeVersion">
                    <?php if(strtolower($site->runtime_type) === 'php'): ?>
                        <option value="<?= $phpVer ?>" <?= $site->runtime_version === $phpVer ? 'selected' : '' ?>>PHP <?= $phpVer ?> (System Default)</option>
                    <?php else: ?>
                        <option value="<?= htmlspecialchars($site->runtime_version) ?>" selected><?= htmlspecialchars($site->runtime_version) ?></option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group-row">
                <label></label>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" id="forceHttps" <?= (isset($site->force_https) && $site->force_https) ? 'checked' : '' ?> style="width: auto;">
                    <label for="forceHttps" style="font-weight: 500; font-size: 14px; cursor: pointer; margin: 0;">Перенаправлять HTTP запросы в HTTPS</label>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                <button type="submit" class="btn btn-primary" id="btnUpdateSite">Сохранить изменения</button>
                <button type="button" class="btn btn-secondary" onclick="App.navigate('/sites', 'Сайты')">Отмена</button>
            </div>
        </form>
    </div>

    <!-- Вкладка конфига -->
    <div id="content-config" style="display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <p style="margin: 0; font-size: 14px; color: var(--text-muted);">
                <i class="fa-solid fa-triangle-exclamation" style="color: #eab308;"></i> Будьте осторожны! Синтаксическая ошибка в конфигурации может привести к остановке веб-сервера.
            </p>
            <button type="button" class="btn btn-secondary btn-sm" onclick="loadNginxConfig()">
                <i class="fa-solid fa-rotate-right"></i> Обновить из файла
            </button>
        </div>
        
        <div style="position: relative;">
            <textarea id="nginxConfigEditor" style="width: 100%; height: 500px; font-family: monospace; font-size: 13px; line-height: 1.5; padding: 16px; background: #1e1e1e; color: #d4d4d4; border: 1px solid var(--border-color); border-radius: 8px; resize: vertical;" spellcheck="false">Загрузка...</textarea>
        </div>
        
        <div id="nginxErrorBox" style="display: none; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-left: 4px solid #ef4444; padding: 12px 16px; margin-top: 16px; border-radius: 4px; color: #ef4444; font-family: monospace; font-size: 13px; white-space: pre-wrap; word-break: break-all;"></div>
        
        <div class="form-actions" style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color);">
            <button type="button" class="btn btn-primary" onclick="saveNginxConfig()" id="btnSaveConfig">Сохранить и перезагрузить Nginx</button>
        </div>
    </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-item').forEach(el => {
        el.style.color = 'var(--text-muted)';
        el.style.borderBottom = 'none';
    });
    
    document.getElementById('tab-' + tab).style.color = 'var(--primary-color)';
    document.getElementById('tab-' + tab).style.borderBottom = '2px solid var(--primary-color)';
    
    document.getElementById('content-settings').style.display = tab === 'settings' ? 'block' : 'none';
    document.getElementById('content-config').style.display = tab === 'config' ? 'block' : 'none';

    if (tab === 'config' && document.getElementById('nginxConfigEditor').value === 'Загрузка...') {
        loadNginxConfig();
    }
}

async function submitEditSite(e) {
    e.preventDefault();
    const btn = document.getElementById('btnUpdateSite');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Сохранение...';

    const payload = {
        id: document.getElementById('siteId').value,
        force_https: document.getElementById('forceHttps').checked ? 1 : 0
    };

    try {
        const res = await fetch('/api/sites/update', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.success) {
            showAlert(data.message || 'Изменения сохранены', false);
            setTimeout(() => {
                App.navigate('/sites', 'Сайты');
            }, 500);
        } else {
            showAlert(data.error || 'Ошибка', true);
        }
    } catch (e) {
        showAlert('Ошибка сети', true);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Сохранить изменения';
    }
}

async function loadNginxConfig() {
    const id = document.getElementById('siteId').value;
    const editor = document.getElementById('nginxConfigEditor');
    editor.value = 'Загрузка конфигурации...';
    
    try {
        const res = await fetch('/api/sites/config?id=' + id);
        const data = await res.json();
        if (data.success) {
            editor.value = data.content;
        } else {
            editor.value = 'Ошибка загрузки: ' + (data.error || 'Неизвестная ошибка');
            showAlert(data.error, true);
        }
    } catch (e) {
        editor.value = 'Ошибка сети';
        showAlert('Ошибка сети', true);
    }
}

async function saveNginxConfig() {
    const id = document.getElementById('siteId').value;
    const content = document.getElementById('nginxConfigEditor').value;
    const btn = document.getElementById('btnSaveConfig');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Применение...';
    
    try {
        const res = await fetch('/api/sites/config', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id, content})
        });
        const data = await res.json();
        const errBox = document.getElementById('nginxErrorBox');
        
        if (data.success) {
            errBox.style.display = 'none';
            showAlert(data.message || 'Конфигурация Nginx применена', false);
        } else {
            errBox.style.display = 'block';
            errBox.textContent = data.error || 'Ошибка сохранения';
            showAlert('Ошибка синтаксиса Nginx', true);
        }
    } catch (e) {
        showAlert('Ошибка сети', true);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Сохранить и перезагрузить Nginx';
    }
}
</script>
