
<div class="content">

    <div class="card" style="max-width: 800px; margin: 32px;">


        <div id="settingsAlert" style="display: none; padding: 16px; border-radius: 8px; margin-bottom: 24px; font-weight: 500;"></div>

        <style>
            .floating-group {
                position: relative;
                margin-bottom: 24px;
                display: flex;
                align-items: center;
                gap: 16px;
            }
            .floating-input-wrapper {
                position: relative;
                flex: 1;
            }
            .floating-label {
                position: absolute;
                top: -8px;
                left: 12px;
                background: var(--bg-main);
                padding: 0 4px;
                font-size: 12px;
                color: var(--text-muted);
                z-index: 10;
            }
            .floating-input {
                width: 100%;
                padding: 14px 16px;
                border: 1px solid var(--border-color);
                border-radius: 8px;
                background: transparent;
                color: var(--text-main);
                font-size: 14px;
                outline: none;
                transition: border-color 0.2s;
            }
            .floating-input:focus {
                border-color: var(--logo-color);
            }
            select.floating-input {
                appearance: none;
            }
            .select-arrow {
                position: absolute;
                right: 16px;
                top: 50%;
                transform: translateY(-50%);
                color: var(--text-muted);
                pointer-events: none;
            }
            .help-icon {
                background: var(--profile-bg);
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 6px;
                color: var(--text-dark);
                font-weight: bold;
                font-size: 12px;
                cursor: help;
                flex-shrink: 0;
            }
            .checkbox-wrapper {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 24px;
                color: var(--text-main);
            }
            .checkbox-wrapper input[type="checkbox"] {
                width: 18px;
                height: 18px;
                border-radius: 4px;
                border: 1px solid var(--border-color);
                cursor: pointer;
            }
            
            textarea.floating-input {
                resize: vertical;
                min-height: 50px;
                font-family: monospace;
            }
            .resize-icon {
                position: absolute;
                right: 16px;
                top: 50%;
                transform: translateY(-50%);
                color: var(--text-muted);
                cursor: pointer;
                background: var(--bg-main);
                padding: 4px;
            }
        </style>

        <form id="userSettingsForm">
            <div class="floating-group">
                <div class="floating-input-wrapper">
                    <label class="floating-label">Имя пользователя</label>
                    <input type="text" name="username" class="floating-input" value="<?= htmlspecialchars($username) ?>" required>
                </div>
            </div>

            <div class="floating-group">
                <div class="floating-input-wrapper">
                    <label class="floating-label">Язык</label>
                    <select name="language" class="floating-input">
                        <option value="ru">Русский</option>
                        <option value="en">English</option>
                    </select>
                    <i class="fa-solid fa-chevron-down select-arrow"></i>
                </div>
            </div>

            <div class="floating-group">
                <div class="floating-input-wrapper">
                    <label class="floating-label">Текущий пароль</label>
                    <input type="password" name="current_password" class="floating-input" placeholder="Обязательно для сохранения изменений" required>
                    <i class="fa-solid fa-eye-slash select-arrow" style="cursor: pointer; pointer-events: auto;"></i>
                </div>
                <div class="help-icon" title="Введите ваш текущий пароль для подтверждения изменений.">?</div>
            </div>


            <div class="floating-group">
                <div class="floating-input-wrapper">
                    <label class="floating-label">Новый пароль</label>
                    <input type="password" name="new_password" class="floating-input" placeholder="Оставьте пустым, если не хотите менять">
                </div>
            </div>

            <div class="floating-group">
                <div class="floating-input-wrapper">
                    <label class="floating-label">Подтверждение</label>
                    <input type="password" name="confirm_password" class="floating-input" placeholder="Подтвердите новый пароль">
                    <i class="fa-solid fa-eye-slash select-arrow" style="cursor: pointer; pointer-events: auto;"></i>
                </div>
                <div class="help-icon" title="Повторите новый пароль.">?</div>
            </div>

            <?php
                $isIpRestricted = !empty($allowed_ips) && trim($allowed_ips) !== '0.0.0.0/0';
            ?>

            <div class="floating-group">
                <div class="floating-input-wrapper">
                    <label class="floating-label">Доступ к панели управления</label>
                    <select id="ipAccessSelect" class="floating-input">
                        <option value="any" <?= !$isIpRestricted ? 'selected' : '' ?>>С любого IP</option>
                        <option value="list" <?= $isIpRestricted ? 'selected' : '' ?>>Только с IP из списка</option>
                    </select>
                    <i class="fa-solid fa-chevron-down select-arrow"></i>
                </div>
                <div class="help-icon" title="Ограничение доступа к панели управления только для определенных IP адресов.">?</div>
            </div>

            <div class="floating-group" id="ipListGroup" style="display: <?= $isIpRestricted ? 'flex' : 'none' ?>;">
                <div class="floating-input-wrapper">
                    <label class="floating-label">Список IP-адресов</label>
                    <textarea name="allowed_ips" id="ipListInput" class="floating-input" style="height: 50px;" placeholder="192.168.1.1, 10.0.0.0/24"><?= $isIpRestricted ? htmlspecialchars($allowed_ips) : '' ?></textarea>
                    <div class="resize-icon" id="toggleIpSize" title="Изменить размер поля">
                        <i class="fa-solid fa-sort"></i>
                    </div>
                </div>
                <div class="help-icon" title="Укажите один или несколько IP адресов через запятую.">?</div>
            </div>


            <div class="card" style="background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2); box-shadow: none; margin-top: 24px;">
                <div style="font-weight: 600; margin-bottom: 12px;">Двухэтапная аутентификация</div>
                <div style="display: flex; gap: 12px; font-size: 14px; color: var(--text-muted); align-items: flex-start;">
                    <i class="fa-solid fa-circle-info" style="color: #3b82f6; margin-top: 3px;"></i>
                    <p style="margin: 0;">Для генерации одноразовых паролей используйте любое TOTP приложение, например Google Authenticator, установленное на мобильное устройство.</p>
                </div>
                
                <div style="margin-top: 16px;">
                    <?php if ($two_factor_enabled === 'true'): ?>
                        <span style="color: var(--success); font-weight: 600;"><i class="fa-solid fa-check"></i> 2FA Включена</span>
                        <a href="/settings/2fa" style="margin-left: 16px; color: var(--logo-color); text-decoration: underline;">Отключить / Настроить заново</a>
                    <?php else: ?>
                        <a href="/settings/2fa" style="color: var(--logo-color); text-decoration: underline;">Настроить двухэтапную аутентификацию</a>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-top: 32px; display: flex; gap: 16px;">
                <button type="submit" class="btn-primary" id="saveSettingsBtn" style="padding: 10px 24px;">Сохранить</button>
            </div>
        </form>

    </div>
</div>

<script>
(function() {
    const userSettingsForm = document.getElementById('userSettingsForm');
    if (!userSettingsForm) return;

    userSettingsForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = document.getElementById('saveSettingsBtn');
        const alertBox = document.getElementById('settingsAlert');
        
        submitBtn.disabled = true;
        submitBtn.innerText = 'Сохранение...';
        alertBox.style.display = 'none';

        const ipAccessSelect = document.getElementById('ipAccessSelect');
        const allowedIpsField = form.allowed_ips.value;
        let finalIps = '0.0.0.0/0';
        if (ipAccessSelect && ipAccessSelect.value === 'list') {
            finalIps = allowedIpsField.trim() === '' ? '0.0.0.0/0' : allowedIpsField;
        }

        const formData = {
            username: form.username.value,
            current_password: form.current_password.value,
            new_password: form.new_password.value,
            confirm_password: form.confirm_password.value,
            allowed_ips: finalIps
        };

        try {
            const response = await fetch('/api/settings/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Panel-Token': localStorage.getItem('panel_token')
                },
                body: JSON.stringify(formData)
            });

            const data = await response.json();

            alertBox.style.display = 'block';
            if (data.success) {
                alertBox.style.background = 'rgba(34, 197, 94, 0.1)';
                alertBox.style.border = '1px solid rgba(34, 197, 94, 0.3)';
                alertBox.style.color = '#22c55e';
                alertBox.innerText = data.message;
                
                // Clear password fields
                form.current_password.value = '';
                form.new_password.value = '';
                form.confirm_password.value = '';
                
                if (data.redirect) {
                    setTimeout(() => location.href = data.redirect, 2000);
                }
            } else {
                alertBox.style.background = 'rgba(239, 68, 68, 0.1)';
                alertBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
                alertBox.style.color = '#ef4444';
                alertBox.innerText = data.error || 'Ошибка';
            }
        } catch (err) {
            alertBox.style.display = 'block';
            alertBox.style.background = 'rgba(239, 68, 68, 0.1)';
            alertBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
            alertBox.style.color = '#ef4444';
            alertBox.innerText = 'Произошла ошибка при соединении с сервером';
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerText = 'Сохранить';
        }
    });

    // IP Selection Logic
    const ipAccessSelect = document.getElementById('ipAccessSelect');
    const ipListGroup = document.getElementById('ipListGroup');
    
    if (ipAccessSelect && ipListGroup) {
        ipAccessSelect.addEventListener('change', (e) => {
            if (e.target.value === 'list') {
                ipListGroup.style.display = 'flex';
            } else {
                ipListGroup.style.display = 'none';
            }
        });
    }

    // Resize IP Field
    const toggleIpSize = document.getElementById('toggleIpSize');
    const ipListInput = document.getElementById('ipListInput');
    
    if (toggleIpSize && ipListInput) {
        toggleIpSize.addEventListener('click', () => {
            if (ipListInput.style.height === '50px' || !ipListInput.style.height) {
                ipListInput.style.height = '120px';
            } else {
                ipListInput.style.height = '50px';
            }
        });
    }

    // Eye Icons for passwords
    document.querySelectorAll('.fa-eye-slash, .fa-eye').forEach(icon => {
        icon.addEventListener('click', function() {
            const input = this.previousElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            } else {
                input.type = 'password';
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            }
        });
    });
})();
</script>
