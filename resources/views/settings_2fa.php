<div class="content">


    <div class="card" style="max-width: 600px; margin: 32px;">
        
        <div id="setupAlert" style="display: none; padding: 16px; border-radius: 8px; margin-bottom: 24px; font-weight: 500;"></div>

        <?php if ($isEnabled): ?>
            <div style="text-align: center; padding: 24px 0;">
                <i class="fa-solid fa-shield-check" style="font-size: 48px; color: var(--success); margin-bottom: 16px;"></i>
                <h2>2FA включена</h2>
                <p style="color: var(--text-muted); margin-bottom: 24px;">Ваш аккаунт защищен двухэтапной аутентификацией.</p>
                
                <form id="disable2faForm" style="max-width: 300px; margin: 0 auto; text-align: left;">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 13px; color: var(--text-muted); margin-bottom: 8px;">Текущий пароль для отключения</label>
                        <input type="password" name="password" class="auth-input" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-main); color: var(--text-main);" required>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; background: var(--danger); border-color: var(--danger); padding: 10px 24px;">Отключить 2FA</button>
                </form>
            </div>
        <?php else: ?>
            <div style="text-align: center;">
                <p style="color: var(--text-muted); margin-bottom: 24px;">Отсканируйте этот QR-код в приложении Google Authenticator или Authy.</p>
                
                <div style="background: white; padding: 16px; border-radius: 12px; display: inline-block; margin-bottom: 16px;">
                    <img src="<?= $qrCode ?>" alt="QR Code" style="width: 200px; height: 200px;">
                </div>
                
                <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 32px;">
                    Секретный ключ (если не работает камера):<br>
                    <strong style="font-family: monospace; font-size: 16px; color: var(--text-main); letter-spacing: 2px; user-select: all;"><?= htmlspecialchars($secret) ?></strong>
                </p>
                
                <form id="verify2faForm" style="text-align: left;">
                    <input type="hidden" name="secret" value="<?= htmlspecialchars($secret) ?>">
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 13px; color: var(--text-muted); margin-bottom: 8px;">Код из приложения (6 цифр)</label>
                        <input type="text" name="code" class="auth-input" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-main); color: var(--text-main); font-family: monospace; font-size: 18px; letter-spacing: 4px; text-align: center;" maxlength="6" autocomplete="off" required>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 24px;">
                        <label style="display: block; font-size: 13px; color: var(--text-muted); margin-bottom: 8px;">Ваш текущий пароль от панели</label>
                        <input type="password" name="password" class="auth-input" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-main); color: var(--text-main);" required>
                    </div>
                    
                    <button type="submit" class="btn-primary" style="padding: 10px 24px;">Включить 2FA</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    const setupAlert = document.getElementById('setupAlert');
    
    function showMsg(isSuccess, text) {
        if (!setupAlert) return;
        setupAlert.style.display = 'block';
        if (isSuccess) {
            setupAlert.style.background = 'rgba(34, 197, 94, 0.1)';
            setupAlert.style.border = '1px solid rgba(34, 197, 94, 0.3)';
            setupAlert.style.color = '#22c55e';
        } else {
            setupAlert.style.background = 'rgba(239, 68, 68, 0.1)';
            setupAlert.style.border = '1px solid rgba(239, 68, 68, 0.3)';
            setupAlert.style.color = '#ef4444';
        }
        setupAlert.innerText = text;
    }

    const verifyForm = document.getElementById('verify2faForm');
    if (verifyForm) {
        verifyForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            if (btn) btn.disabled = true;
            
            try {
                const response = await fetch('/api/settings/2fa/verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Panel-Token': localStorage.getItem('panel_token') },
                    body: JSON.stringify({
                        secret: e.target.secret.value,
                        code: e.target.code.value,
                        password: e.target.password.value
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    showMsg(true, data.message);
                    setTimeout(() => { location.reload(); }, 1000);
                } else {
                    showMsg(false, data.error);
                    if (btn) btn.disabled = false;
                }
            } catch (err) {
                showMsg(false, 'Ошибка соединения');
                if (btn) btn.disabled = false;
            }
        });
    }
    
    const disableForm = document.getElementById('disable2faForm');
    if (disableForm) {
        disableForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            if (btn) btn.disabled = true;
            
            try {
                const response = await fetch('/api/settings/2fa/disable', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Panel-Token': localStorage.getItem('panel_token') },
                    body: JSON.stringify({
                        password: e.target.password.value
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    showMsg(true, data.message);
                    setTimeout(() => { location.reload(); }, 1000);
                } else {
                    showMsg(false, data.error);
                    if (btn) btn.disabled = false;
                }
            } catch (err) {
                showMsg(false, 'Ошибка соединения');
                if (btn) btn.disabled = false;
            }
        });
    }
})();
</script>
