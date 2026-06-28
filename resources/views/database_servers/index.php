<div class="page-card">

<div class="toolbar-container">
    <div class="toolbar-left" style="gap: 4px;">
        <a href="javascript:void(0)" class="btn btn-primary btn-sm" onclick="openCreateDbServerModal()">
            <i class="fa-solid fa-plus"></i> Создать сервер
        </a>
        <button class="btn btn-secondary btn-sm disabled" id="toolbarEditBtn" onclick="editSelectedServer()" disabled>
            <i class="fa-solid fa-pen"></i>
        </button>
        <button class="btn btn-secondary btn-sm disabled" id="toolbarDeleteBtn" onclick="deleteSelectedServer()" disabled title="Удалить">
            <i class="fa-solid fa-trash-can"></i>
        </button>
    </div>
    <div class="toolbar-right">
        <div class="search-box">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="searchDbServer" placeholder="Поиск сервера...">
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllDbServers"></th>
                <th>Имя</th>
                <th>Тип</th>
                <th>Адрес сервера</th>
                <th>Версия</th>
                <th style="width: 50px;"></th>
            </tr>
        </thead>
        <tbody id="dbServersTableBody">
            <?php if (empty($servers)): ?>
                <tr>
                    <td colspan="6" class="text-center" style="padding: 40px; color: var(--text-muted);">
                        <div style="font-size: 40px; margin-bottom: 15px; opacity: 0.3;"><i class="fa-solid fa-server"></i></div>
                        Серверы баз данных не найдены.<br>Создайте первый сервер для размещения баз данных.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($servers as $server): ?>
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="db-server-checkbox" data-id="<?= $server['id'] ?>" onchange="updateToolbar()"></td>
                        <td style="font-weight: 500;">
                            <i class="fa-solid <?= $server['type'] === 'postgres' ? 'fa-elephant' : 'fa-database' ?> text-primary" style="margin-right: 8px;"></i>
                            <a href="javascript:void(0)" onclick="openEditDbServerModal(<?= $server['id'] ?>)" style="color: inherit; text-decoration: none; border-bottom: 1px dashed #ccc; padding-bottom: 1px;"><?= htmlspecialchars($server['name']) ?></a>
                        </td>
                        <td><?= htmlspecialchars(ucfirst($server['type'])) ?></td>
                        <td><?= htmlspecialchars($server['host']) ?><?= $server['port'] != 3306 && $server['port'] != 5432 ? ':' . $server['port'] : '' ?></td>
                        <td>
                            <!-- Версию можно было бы тянуть через API (testConnection), пока заглушка -->
                            <span class="badge badge-secondary" style="font-size: 12px;"><i class="fa-solid fa-spinner fa-spin"></i></span>
                        </td>
                        <td>
                            <button class="btn btn-icon btn-sm" onclick="openEditDbServerModal(<?= $server['id'] ?>)">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</div>

<!-- Модальное окно: Создать сервер -->
<div id="createDbServerModal" class="modal-overlay" style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1100;">
    <div class="modal-container" style="background: var(--bg-card, #fff); border-radius: 12px; width: 100%; max-width: 550px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--border-color, #eaeaea); border-radius: 12px 12px 0 0; background: transparent;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-server text-primary"></i> Новый сервер баз данных
            </h3>
            <button type="button" class="btn-close" onclick="closeCreateDbServerModal()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #999;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <form id="createDbServerForm" class="panel-form">
                <div class="form-group">
                    <label>Имя*</label>
                    <input type="text" name="name" class="form-control" required placeholder="Например: Основной MySQL">
                </div>
                <div class="form-group">
                    <label>Тип</label>
                    <select name="type" class="form-control">
                        <option value="mysql">MySQL</option>
                        <option value="postgres">PostgreSQL</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Действие</label>
                    <select name="action_type" class="form-control">
                        <option value="connect">Подключить существующий сервер</option>
                        <option value="install" disabled>Установить новый локально (скоро)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Адрес сервера*</label>
                    <input type="text" name="host" class="form-control" value="localhost" required>
                </div>
                <div class="form-group">
                    <label>Порт</label>
                    <input type="number" name="port" class="form-control" value="3306" required>
                </div>
                <div class="form-group">
                    <label>Имя пользователя* (с правами root)</label>
                    <input type="text" name="username" class="form-control" required placeholder="root">
                </div>
                <div class="form-group">
                    <label>Пароль*</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="password" name="password" id="createDbServerPassword" class="form-control" required>
                        <button type="button" class="btn btn-secondary" onclick="togglePasswordVisibility('createDbServerPassword')" title="Показать/скрыть">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 20px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="remote_access" value="1" style="width: 16px; height: 16px; margin: 0;">
                        <span>Удаленный доступ</span>
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-start; gap: 12px; padding: 16px 24px; background: var(--bg-body, #f8fafc); border-top: 1px solid var(--border-color, #eaeaea); border-radius: 0 0 12px 12px;">
            <button class="btn btn-primary" onclick="submitCreateDbServer()" id="btnCreateDbServer">Создать</button>
            <button class="btn btn-secondary" onclick="closeCreateDbServerModal()">Закрыть</button>
            <button class="btn btn-secondary" style="margin-left: auto;" onclick="testDbServerConnection('createDbServerForm')" type="button">Проверить соединение</button>
        </div>
    </div>
</div>

<!-- Модальное окно: Редактировать сервер -->
<div id="editDbServerModal" class="modal-overlay" style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1100;">
    <div class="modal-container" style="background: var(--bg-card, #fff); border-radius: 12px; width: 100%; max-width: 550px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--border-color, #eaeaea); border-radius: 12px 12px 0 0; background: transparent;">
            <h3 id="editDbServerTitle" style="margin: 0; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-server text-primary"></i> Сервер баз данных
            </h3>
            <button type="button" class="btn-close" onclick="closeEditDbServerModal()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #999;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <form id="editDbServerForm" class="panel-form">
                <input type="hidden" name="id" id="editDbServerId">
                <div class="form-group">
                    <label>Имя*</label>
                    <input type="text" name="name" id="editDbServerName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Тип</label>
                    <select name="type" id="editDbServerType" class="form-control" disabled>
                        <option value="mysql">MySQL</option>
                        <option value="postgres">PostgreSQL</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Адрес сервера*</label>
                    <input type="text" name="host" id="editDbServerHost" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Порт</label>
                    <input type="number" name="port" id="editDbServerPort" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Имя пользователя*</label>
                    <input type="text" name="username" id="editDbServerUsername" class="form-control" required>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="changePasswordCheckbox" style="width: 16px; height: 16px; margin: 0;" onchange="document.getElementById('editPasswordDiv').style.display = this.checked ? 'block' : 'none'">
                        <span>Установить новый пароль</span>
                    </label>
                </div>
                
                <div class="form-group" id="editPasswordDiv" style="display: none;">
                    <label>Пароль*</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="password" name="password" id="editDbServerPassword" class="form-control">
                        <button type="button" class="btn btn-secondary" onclick="togglePasswordVisibility('editDbServerPassword')" title="Показать/скрыть">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="remote_access" id="editDbServerRemoteAccess" value="1" style="width: 16px; height: 16px; margin: 0;">
                        <span>Удаленный доступ</span>
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-start; gap: 12px; padding: 16px 24px; background: var(--bg-body, #f8fafc); border-top: 1px solid var(--border-color, #eaeaea); border-radius: 0 0 12px 12px;">
            <button class="btn btn-primary" onclick="submitEditDbServer()" id="btnEditDbServer">Сохранить</button>
            <button class="btn btn-secondary" onclick="closeEditDbServerModal()">Закрыть</button>
            <button class="btn btn-secondary" style="margin-left: auto;" onclick="testDbServerConnection('editDbServerForm')" type="button">Проверить соединение</button>
            <button class="btn btn-danger" style="margin-left: 8px;" onclick="deleteDbServer()" type="button"><i class="fa-solid fa-trash"></i></button>
        </div>
    </div>
</div>

<!-- Модальное окно: Изменить пароль root -->

<script>
    function updateToolbar() {
        const checkboxes = document.querySelectorAll('.db-server-checkbox:checked');
        const editBtn = document.getElementById('toolbarEditBtn');
        const deleteBtn = document.getElementById('toolbarDeleteBtn');
        
        if (checkboxes.length === 1) {
            editBtn.classList.remove('disabled');
            editBtn.removeAttribute('disabled');
            deleteBtn.classList.remove('disabled');
            deleteBtn.removeAttribute('disabled');
        } else if (checkboxes.length > 1) {
            editBtn.classList.add('disabled');
            editBtn.setAttribute('disabled', 'true');
            deleteBtn.classList.remove('disabled');
            deleteBtn.removeAttribute('disabled');
        } else {
            editBtn.classList.add('disabled');
            editBtn.setAttribute('disabled', 'true');
            deleteBtn.classList.add('disabled');
            deleteBtn.setAttribute('disabled', 'true');
        }
    }

    document.getElementById('selectAllDbServers')?.addEventListener('change', function(e) {
        document.querySelectorAll('.db-server-checkbox').forEach(cb => {
            cb.checked = e.target.checked;
        });
        updateToolbar();
    });

    function editSelectedServer() {
        const selected = document.querySelector('.db-server-checkbox:checked');
        if (selected) {
            openEditDbServerModal(selected.dataset.id);
        }
    }

    async function deleteSelectedServer() {
        const selected = document.querySelectorAll('.db-server-checkbox:checked');
        if (selected.length > 0) {
            if (confirm('Вы уверены, что хотите удалить выбранные серверы?')) {
                const ids = Array.from(selected).map(cb => cb.dataset.id);
                // Execute delete requests sequentially
                for (const id of ids) {
                    try {
                        const response = await fetch('/api/database-servers/delete', {
                            method: 'DELETE',
                            body: JSON.stringify({ id: id }),
                            headers: { 
                                'Content-Type': 'application/json',
                                'X-Panel-Token': localStorage.getItem('panel_token') || '' 
                            }
                        });
                        const res = await response.json();
                        if (!res.success) throw new Error(res.error || 'Unknown error');
                    } catch (e) {
                        alert('Ошибка при удалении сервера с ID ' + id + ': ' + e.message);
                    }
                }
                // Reload page to reflect changes
                window.location.reload();
            }
        }
    }

</script>
