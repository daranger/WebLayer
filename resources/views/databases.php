<div class="page-card">
<div class="toolbar-container">
    <div class="toolbar-left" style="gap: 4px;">
        <a href="/databases/create" class="btn btn-primary btn-sm" data-module>
            <i class="fa-solid fa-plus"></i> Создать базу данных
        </a>
        <button class="btn btn-secondary btn-sm toolbar-delete-btn disabled" disabled>
            <i class="fa-solid fa-trash-can"></i>
        </button>
        <button id="toolbar-pma-btn" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-desktop"></i> Web интерфейс БД
        </button>
        <button class="btn btn-secondary btn-sm" onclick="App.navigate('/databases/servers', 'Серверы баз данных')">
            <i class="fa-solid fa-server"></i> Серверы БД
        </button>
    </div>
    <div class="toolbar-right">
        <div class="search-box">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="searchDatabase" placeholder="Поиск базы данных...">
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="data-table">
        <thead>
        <tr>
            <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllDbs"></th>
            <th>Имя</th>
            <th>Владелец</th>
            <th>Адрес сервера</th>
            <th>Пользователь БД</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($databases)): ?>
            <tr>
                <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 40px;">
                    Базы данных пока не добавлены
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($databases as $db): ?>
                <tr data-db-id="<?= $db['id'] ?>" data-db-user="<?= htmlspecialchars($db['db_user']) ?>" data-db-pass="<?= htmlspecialchars($db['db_pass'] ?? '') ?>" data-db-name="<?= htmlspecialchars($db['db_name']) ?>">
                    <td style="text-align: center;"><input type="checkbox" class="db-checkbox"></td>
                    <td class="domain-name">
                        <i class="fa-solid fa-database" style="color: var(--primary-color); margin-right: 8px;"></i>
                        <?= htmlspecialchars($db['db_name']) ?>
                    </td>
                    <td>
                        <?php if ($db['site_domain']): ?>
                            <span class="badge-owner"><?= htmlspecialchars($db['site_domain']) ?></span>
                        <?php else: ?>
                            <?= htmlspecialchars($db['db_user']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge-owner" style="background: var(--bg-body, #f0f0f0); color: var(--text-muted);">
                            <i class="fa-solid fa-server"></i> <?= htmlspecialchars($db['server_name'] ?? 'Локальный') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($db['db_user']) ?></td>
                    <td class="actions-cell">
                        <button class="context-trigger-btn" data-dropdown="dropdown-db-<?= $db['id'] ?>">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <div class="context-dropdown" id="dropdown-db-<?= $db['id'] ?>">
                            <a href="#" class="dropdown-item context-pma-btn">Web интерфейс БД</a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item text-danger" onclick="deleteDatabase(<?= $db['id'] ?>, '<?= htmlspecialchars($db['db_name']) ?>'); return false;">Удалить</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
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
</style>

<?php $pmaBase = rtrim(env('PANEL_PMA_PATH', '/phpmyadmin'), '/'); ?>
<form id="pmaLoginForm" action="<?= $pmaBase ?>/index.php" method="POST" target="_blank" style="display: none;">
    <input type="hidden" name="pma_username" id="pmaUser">
    <input type="hidden" name="pma_password" id="pmaPass">
</form>

<script>
(function() {
    const selectAllCheckbox = document.getElementById('selectAllDbs');
    const checkboxes = document.querySelectorAll('.db-checkbox');
    const deleteBtn = document.querySelector('.toolbar-delete-btn');
    const pmaBtn = document.getElementById('toolbar-pma-btn');

    function updateToolbar() {
        const checkboxes = document.querySelectorAll('.db-checkbox:checked');
        
        if (checkboxes.length === 1) {
            deleteBtn.classList.remove('disabled');
            deleteBtn.removeAttribute('disabled');
        } else if (checkboxes.length > 1) {
            deleteBtn.classList.remove('disabled');
            deleteBtn.removeAttribute('disabled');
        } else {
            deleteBtn.classList.add('disabled');
            deleteBtn.setAttribute('disabled', 'true');
        }
    }
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateToolbar();
        });
    }
    
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateToolbar);
    });
    
    function loginToPma(user, pass) {
        // Чтобы phpMyAdmin пустил нас, нам нужен его CSRF токен (token и set_session)
        // Получаем страницу логина, вытаскиваем токены и отправляем форму
        const pmaUrl = '<?= $pmaBase ?>';
        fetch(pmaUrl + '/index.php')
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const token = doc.querySelector('input[name="token"]')?.value;
                const setSession = doc.querySelector('input[name="set_session"]')?.value;
                const server = doc.querySelector('input[name="server"]')?.value || '1';
                
                const form = document.getElementById('pmaLoginForm');
                document.getElementById('pmaUser').value = user;
                document.getElementById('pmaPass').value = pass;
                
                // Добавляем токен
                if (token) {
                    let tInput = form.querySelector('input[name="token"]');
                    if (!tInput) {
                        tInput = document.createElement('input');
                        tInput.type = 'hidden';
                        tInput.name = 'token';
                        form.appendChild(tInput);
                    }
                    tInput.value = token;
                }
                
                // Добавляем сессию
                if (setSession) {
                    let sInput = form.querySelector('input[name="set_session"]');
                    if (!sInput) {
                        sInput = document.createElement('input');
                        sInput.type = 'hidden';
                        sInput.name = 'set_session';
                        form.appendChild(sInput);
                    }
                    sInput.value = setSession;
                }
                
                // Добавляем сервер
                let srvInput = form.querySelector('input[name="server"]');
                if (!srvInput) {
                    srvInput = document.createElement('input');
                    srvInput.type = 'hidden';
                    srvInput.name = 'server';
                    form.appendChild(srvInput);
                }
                srvInput.value = server;
                
                form.submit();
            })
            .catch(err => {
                console.error('Ошибка получения токена phpMyAdmin:', err);
                // Запасной вариант - просто открываем
                window.open(pmaUrl + '/', '_blank');
            });
    }

    if (pmaBtn) {
        pmaBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const selected = document.querySelectorAll('.db-checkbox:checked');
            if (selected.length === 1) {
                const row = selected[0].closest('tr');
                const user = row.dataset.dbUser;
                const pass = row.dataset.dbPass;
                loginToPma(user, pass);
            } else {
                // Если база не выбрана, открываем phpMyAdmin как обычно (потребует ввода логина)
                window.open('<?= $pmaBase ?>/', '_blank');
            }
        });
    }
    
    const contextPmaBtns = document.querySelectorAll('.context-pma-btn');
    if (contextPmaBtns) {
        contextPmaBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const dropdown = this.closest('.context-dropdown');
                if (!dropdown) return;
                
                const dbId = dropdown.id.replace('dropdown-db-', '');
                const row = document.querySelector('tr[data-db-id="' + dbId + '"]');
                
                if (row) {
                    const user = row.dataset.dbUser;
                    const pass = row.dataset.dbPass;
                    loginToPma(user, pass);
                }
                
                // Закрываем меню
                dropdown.classList.remove('show');
            });
        });
    }
    
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function() {
            const selected = document.querySelectorAll('.db-checkbox:checked');
            if (selected.length === 0) return;
            
            if (confirm('Вы уверены, что хотите удалить выбранные базы данных? (ВНИМАНИЕ: Безвозвратно!)')) {
                // Пока реализуем удаление только одной первой выбранной БД для простоты
                const row = selected[0].closest('tr');
                const dbId = row.dataset.dbId;
                const dbName = row.dataset.dbName;
                deleteDatabase(dbId, dbName);
            }
        });
    }
})();
</script>
