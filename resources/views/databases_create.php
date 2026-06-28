<div class="form-page-container" style="margin: unset;">
    <div class="page-card" style="padding: 32px; margin: 0;">
        <div class="form-page-header">
            <h2>Новая база данных</h2>
        </div>

        <form id="createDatabaseForm" class="panel-form">
            <div class="form-group-row">
                <label for="db_name">Имя*</label>
                <input type="text" id="db_name" name="db_name" placeholder="my_database" required autocomplete="off" pattern="[a-zA-Z0-9_]+" title="Только латинские буквы, цифры и подчеркивание">
            </div>

            <div class="form-group-row">
                <label for="site_id">Связанный сайт</label>
                <select id="site_id" name="site_id">
                    <option value="">-- Без привязки --</option>
                    <?php foreach ($sites as $site): ?>
                        <option value="<?= $site['id'] ?>"><?= htmlspecialchars($site['domain']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (count($servers) > 1): ?>
                <div class="form-group-row">
                    <label for="db_server">Сервер баз данных*</label>
                    <select id="db_server" name="db_server" required>
                        <?php foreach ($servers as $server): ?>
                            <option value="<?= $server['id'] ?>"><?= htmlspecialchars($server['name']) ?> (<?= htmlspecialchars($server['type']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <?php $defaultServerId = !empty($servers) ? $servers[0]['id'] : ''; ?>
                <input type="hidden" id="db_server" name="db_server" value="<?= $defaultServerId ?>">
            <?php endif; ?>

            <div class="form-group-row">
                <label for="charset">Кодировка</label>
                <select id="charset" name="charset" disabled>
                    <option value="utf8mb4">utf8mb4</option>
                </select>
            </div>

            <div class="form-group-row">
                <label for="db_user">Имя пользователя*</label>
                <input type="text" id="db_user" name="db_user" placeholder="my_user" required autocomplete="off" pattern="[a-zA-Z0-9_]+" title="Только латинские буквы, цифры и подчеркивание">
            </div>

            <div class="form-group-row">
                <label for="password">Пароль*</label>
                <div style="display: flex; gap: 8px; width: 100%;">
                    <input type="password" id="password" name="password" required autocomplete="new-password" style="flex-grow: 1;">
                    <button type="button" class="btn btn-secondary" onclick="togglePassword('password')" title="Показать/скрыть"><i class="fa-solid fa-eye"></i></button>
                    <button type="button" class="btn btn-secondary" onclick="generatePassword()" title="Сгенерировать"><i class="fa-solid fa-dice"></i></button>
                </div>
            </div>

            <div class="form-group-row">
                <label for="password_confirm">Подтверждение*</label>
                <div style="display: flex; gap: 8px; width: 100%;">
                    <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password" style="flex-grow: 1;">
                    <button type="button" class="btn btn-secondary" onclick="togglePassword('password_confirm')" title="Показать/скрыть"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>

            <div id="formError" class="form-error-msg hidden"></div>
            <div id="formSuccess" class="form-success-msg hidden"></div>

            <div class="form-actions" style="margin-top: 32px;">
                <button type="submit" class="btn btn-primary" id="submitBtn">Создать</button>
                <a href="/databases" class="btn btn-secondary" data-module>Закрыть</a>
            </div>
        </form>
    </div>
</div>

<style>
    .panel-form .form-group-row {
        margin-bottom: 20px;
    }
    .panel-form label {
        display: block;
        font-weight: 500;
        margin-bottom: 8px;
        color: var(--text-dark);
        font-size: 14px;
    }
    .panel-form input[type="text"],
    .panel-form input[type="password"],
    .panel-form select {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background-color: var(--input-bg);
        color: var(--text-dark);
        font-size: 14px;
        transition: all 0.2s;
    }
    .panel-form input:focus,
    .panel-form select:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }
    .panel-form input:disabled,
    .panel-form select:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        background-color: var(--bg-main);
    }
    .form-error-msg {
        color: var(--danger);
        background-color: rgba(239, 68, 68, 0.1);
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .form-success-msg {
        color: var(--success);
        background-color: rgba(16, 185, 129, 0.1);
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .hidden { display: none; }
</style>


