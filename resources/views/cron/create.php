<div class="page-card" style="max-width: 600px; padding: 32px;">
    <h2 style="margin-top: 0; font-size: 20px; display: flex; align-items: center; gap: 10px; font-weight: 600;">
        <i class="fa-solid fa-clock text-primary"></i> Новое задание
    </h2>
    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0 24px 0;">

    <form id="createCronForm" class="panel-form">
        <div class="form-group-row">
            <label>
                Адрес e-mail
                <i class="fa-regular fa-circle-question" title="Email для получения вывода команды"></i>
            </label>
            <input type="email" name="email" placeholder="admin@example.com" disabled>
            
            <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                <input type="checkbox" name="no_email" id="noEmailCheck" style="width: auto;" checked>
                <label for="noEmailCheck" style="font-size: 13px; font-weight: normal; cursor: pointer;">
                    Не отправлять отчет по e-mail
                </label>
            </div>
        </div>

        <div class="form-group-row">
            <label>Дата и время сервера</label>
            <input type="text" value="<?= date('d.m.Y, H:i:s') ?>" disabled style="opacity: 0.7; cursor: not-allowed; background: rgba(0,0,0,0.02);">
        </div>

        <div class="form-group-row">
            <label>
                Команда <span style="color: var(--danger);">*</span>
                <i class="fa-regular fa-circle-question" title="Исполняемая команда"></i>
            </label>
            <input type="text" name="command" placeholder="/usr/bin/php /var/www/.../script.php" required>
        </div>

        <div class="form-group-row">
            <label>
                Описание
                <i class="fa-regular fa-circle-question"></i>
            </label>
            <input type="text" name="description" placeholder="Моя задача...">
        </div>

        <div style="display: flex; align-items: center; gap: 10px; margin: 10px 0;">
            <input type="checkbox" name="is_active" id="isActiveCheck" checked style="width: auto;">
            <label for="isActiveCheck" style="font-weight: 500; font-size: 14px; cursor: pointer;">
                Включено
            </label>
        </div>

        <div style="margin-top: 20px;">
            <h3 style="font-size: 16px; margin-bottom: 16px; font-weight: 600;">Расписание</h3>
            
            <div style="display: flex; gap: 24px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="radio" name="schedule_mode" id="modeBasic" value="basic" checked style="width: auto; cursor: pointer;">
                    <label for="modeBasic" style="font-size: 14px; cursor: pointer;">базовый режим</label>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="radio" name="schedule_mode" id="modeExpert" value="expert" style="width: auto; cursor: pointer;">
                    <label for="modeExpert" style="font-size: 14px; cursor: pointer;">экспертный режим</label>
                </div>
            </div>

            <!-- Базовый режим -->
            <div id="basicScheduleWrapper" style="background: rgba(0,0,0,0.02); padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 16px;">
                <div class="form-group-row">
                    <label>Выполнять</label>
                    <select name="run_type" id="runTypeSelect">
                        <option value="minutely">каждую минуту</option>
                        <option value="hourly">ежечасно</option>
                        <option value="daily" selected>ежедневно</option>
                        <option value="weekly">еженедельно</option>
                        <option value="monthly">ежемесячно</option>
                        <option value="yearly">ежегодно</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 16px;" id="timeFieldsRow">
                    <div class="form-group-row hour-col" style="flex: 1;">
                        <label>Час</label>
                        <input type="number" name="hour" min="0" max="23" value="0">
                    </div>
                    <div class="form-group-row min-col" style="flex: 1;">
                        <label>Минута</label>
                        <input type="number" name="minute" min="0" max="59" value="0">
                    </div>
                </div>
            </div>

            <!-- Экспертный режим -->
            <div id="expertScheduleWrapper" style="display: none; background: rgba(0,0,0,0.02); padding: 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                <div class="form-group-row">
                    <label>CRON выражение</label>
                    <input type="text" name="schedule_expert" placeholder="0 * * * *" value="* * * * *">
                    <span style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Формат: мин час день месяц день_недели</span>
                </div>
            </div>
        </div>

        <div class="form-actions" style="margin-top: 24px;">
            <button type="submit" class="btn btn-primary">Создать</button>
            <button type="button" class="btn btn-secondary" onclick="App.navigate('/cron')">Отмена</button>
        </div>
    </form>
</div>

<script>
(() => {
// Переключение no_email
document.getElementById('noEmailCheck').addEventListener('change', function() {
    const emailInput = document.querySelector('input[name="email"]');
    emailInput.disabled = this.checked;
    if (this.checked) emailInput.value = '';
});

// Переключение режимов
document.querySelectorAll('input[name="schedule_mode"]').forEach(radio => {
    radio.addEventListener('change', function() {
        if (this.value === 'basic') {
            document.getElementById('basicScheduleWrapper').style.display = 'block';
            document.getElementById('expertScheduleWrapper').style.display = 'none';
        } else {
            document.getElementById('basicScheduleWrapper').style.display = 'none';
            document.getElementById('expertScheduleWrapper').style.display = 'block';
        }
    });
});

// Скрытие часов/минут в зависимости от типа
document.getElementById('runTypeSelect').addEventListener('change', function() {
    const val = this.value;
    const hourCol = document.querySelector('.hour-col');
    const minCol = document.querySelector('.min-col');
    
    hourCol.style.display = 'block';
    minCol.style.display = 'block';
    
    if (val === 'minutely') {
        hourCol.style.display = 'none';
        minCol.style.display = 'none';
    } else if (val === 'hourly') {
        hourCol.style.display = 'none';
    }
});

// Обработка формы
document.getElementById('createCronForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Создание...';

    try {
        const res = await fetch('/api/cron', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if (data.success) {
            App.navigate('/cron');
        } else {
            alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Создать';
        }
    } catch (err) {
        alert('Сетевая ошибка');
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Создать';
    }
});
})();
</script>
