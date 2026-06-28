<div class="page-card">
    <div class="toolbar-container">
        <div class="toolbar-left" style="gap: 4px;">
            <a href="/cron/create" class="btn btn-primary btn-sm ajax-link">
                <i class="fa-solid fa-plus"></i> Создать задание <i class="fa-solid fa-chevron-down" style="font-size: 10px; margin-left: 4px;"></i>
            </a>
            <button id="toolbarBtnEdit" class="btn btn-secondary btn-sm disabled" disabled title="Изменить">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button id="toolbarBtnDelete" class="btn btn-secondary btn-sm disabled" disabled title="Удалить">
                <i class="fa-solid fa-trash-can"></i>
            </button>
            <button id="toolbarBtnRun" class="btn btn-secondary btn-sm disabled" disabled title="Выполнить">
                <i class="fa-solid fa-play"></i> Выполнить
            </button>
        </div>
        <div class="toolbar-right">
            <div class="search-box">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="searchCron" placeholder="Поиск задания...">
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
            <tr>
                <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllCron"></th>
                <th>Команда <i class="fa-solid fa-filter" style="color: var(--text-muted); margin-left: 4px; font-size: 10px;"></i></th>
                <th>Расписание</th>
                <th>Описание</th>
                <th style="text-align: center;">Состояние</th>
                <th style="width: 50px;"></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($jobs)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 40px;">
                        Заданий пока нет. Нажмите "Создать задание".
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($jobs as $job): ?>
                    <?php 
                        $scheduleText = $job['schedule'];
                        if ($scheduleText === '* * * * *') $scheduleText = 'каждую минуту';
                        elseif (preg_match('/^(\d+) \* \* \* \*$/', $scheduleText)) $scheduleText = 'ежечасно';
                        elseif (preg_match('/^(\d+) (\d+) \* \* \*$/', $scheduleText)) $scheduleText = 'ежедневно';
                        else $scheduleText = 'пользовательское';
                    ?>
                    <tr data-id="<?= $job['id'] ?>">
                        <td style="text-align: center;"><input type="checkbox" class="cron-checkbox"></td>
                        <td style="font-family: monospace; font-size: 13px; color: #444; max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($job['command']) ?>">
                            <?= htmlspecialchars($job['command']) ?>
                        </td>
                        <td><?= $scheduleText ?></td>
                        <td><?= htmlspecialchars($job['description'] ?: '—') ?></td>
                        <td style="text-align: center;">
                            <label class="switch">
                                <input type="checkbox" class="toggle-active" data-id="<?= $job['id'] ?>" <?= $job['is_active'] ? 'checked' : '' ?>>
                                <span class="slider round"></span>
                            </label>
                        </td>
                        <td class="actions-cell">
                            <button class="context-trigger-btn" data-dropdown="dropdown-cron-<?= $job['id'] ?>">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <div class="context-dropdown" id="dropdown-cron-<?= $job['id'] ?>">
                                <a href="#" class="dropdown-item context-run-btn">Выполнить</a>
                                <a href="#" class="dropdown-item context-edit-btn">Изменить</a>
                                <div class="dropdown-divider"></div>
                                <a href="#" class="dropdown-item text-danger delete-cron" data-id="<?= $job['id'] ?>">Удалить</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div style="padding: 16px 32px; border-top: 1px solid var(--border-color); color: var(--text-muted); font-size: 13px; display: flex; gap: 16px; background: rgba(0,0,0,0.01);">
        <span>Всего: <?= count($jobs) ?></span>
    </div>
</div>

<style>
/* Стили для тумблера */
.switch {
  position: relative;
  display: inline-block;
  width: 34px;
  height: 20px;
}
.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}
.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  transition: .4s;
}
.slider:before {
  position: absolute;
  content: "";
  height: 14px;
  width: 14px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: .4s;
}
input:checked + .slider {
  background-color: #0d6efd;
}
input:checked + .slider:before {
  transform: translateX(14px);
}
.slider.round {
  border-radius: 20px;
}
.slider.round:before {
  border-radius: 50%;
}
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

<script>
(() => {
document.querySelectorAll('.toggle-active').forEach(toggle => {
    toggle.addEventListener('change', async (e) => {
        const id = e.target.dataset.id;
        const isActive = e.target.checked ? 1 : 0;
        
        try {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('is_active', isActive);

            const res = await fetch('/api/cron/toggle', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (!data.success) {
                alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                e.target.checked = !e.target.checked; // откат UI
            }
        } catch (err) {
            alert('Ошибка сети');
            e.target.checked = !e.target.checked;
        }
    });
});

document.querySelectorAll('.delete-cron').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        e.preventDefault();
        if (!confirm('Удалить это задание?')) return;
        
        const id = e.target.dataset.id;
        
        try {
            const res = await fetch('/api/cron', {
                method: 'DELETE',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            });
            const data = await res.json();
            if (data.success) {
                App.navigate('/cron');
            } else {
                alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
            }
        } catch (err) {
            alert('Ошибка сети');
        }
    });
});

// Логика выделения чекбоксов и активации кнопок тулбара
const selectAllCb = document.getElementById('selectAllCron');
const rowCbs = document.querySelectorAll('.cron-checkbox');
const tbEdit = document.getElementById('toolbarBtnEdit');
const tbDelete = document.getElementById('toolbarBtnDelete');
const tbInfo = document.getElementById('toolbarBtnInfo');
const tbRun = document.getElementById('toolbarBtnRun');

function updateToolbar() {
    const selected = document.querySelectorAll('.cron-checkbox:checked');
    const count = selected.length;
    
    const toggleBtn = (btn, condition) => {
        if (!btn) return;
        if (condition) {
            btn.classList.remove('disabled');
            btn.removeAttribute('disabled');
        } else {
            btn.classList.add('disabled');
            btn.setAttribute('disabled', 'disabled');
        }
    };
    
    // Удалить, Выполнить можно несколько
    toggleBtn(tbDelete, count > 0);
    toggleBtn(tbRun, count > 0);
    
    // Изменить, Свойства - только если выбран ровно 1
    toggleBtn(tbEdit, count === 1);
    toggleBtn(tbInfo, count === 1);
}

if (selectAllCb) {
    selectAllCb.addEventListener('change', (e) => {
        rowCbs.forEach(cb => cb.checked = e.target.checked);
        updateToolbar();
    });
}

rowCbs.forEach(cb => {
    cb.addEventListener('change', () => {
        if (!cb.checked && selectAllCb) selectAllCb.checked = false;
        updateToolbar();
    });
});

// Удаление выделенных
if (tbDelete) {
    tbDelete.addEventListener('click', async () => {
        const selected = document.querySelectorAll('.cron-checkbox:checked');
        if (selected.length === 0) return;
        
        if (!confirm(`Удалить выбранные задания (${selected.length} шт)?`)) return;
        
        let hasError = false;
        
        for (let cb of selected) {
            const id = cb.closest('tr').dataset.id;
            try {
                const res = await fetch('/api/cron', {
                    method: 'DELETE',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: id})
                });
                const data = await res.json();
                if (!data.success) hasError = true;
            } catch (e) {
                hasError = true;
            }
        }
        
        if (hasError) alert('Некоторые задания не удалось удалить');
        App.navigate('/cron');
    });
}

// Запуск выделенных
if (tbRun) {
    tbRun.addEventListener('click', async () => {
        const selected = document.querySelectorAll('.cron-checkbox:checked');
        if (selected.length === 0) return;
        
        const oldText = tbRun.innerHTML;
        tbRun.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        tbRun.disabled = true;
        
        let results = [];
        for (let cb of selected) {
            const id = cb.closest('tr').dataset.id;
            try {
                const formData = new FormData();
                formData.append('id', id);
                const res = await fetch('/api/cron/run', { method: 'POST', body: formData });
                const data = await res.json();
                results.push(data);
            } catch (e) {
                results.push({success: false, error: 'Сетевая ошибка'});
            }
        }
        
        tbRun.innerHTML = oldText;
        tbRun.disabled = false;
        
        const hasError = results.some(r => !r.success);
        if (hasError) {
            alert('Некоторые задания завершились с ошибкой.');
        } else {
            alert('Задания успешно выполнены!');
        }
        
        // Show output of the last executed job if we ran only 1
        if (selected.length === 1 && results[0].output) {
            alert("Вывод команды:\n" + results[0].output);
        }
    });
}

// Редактирование (тулбар)
if (tbEdit) {
    tbEdit.addEventListener('click', () => {
        const selected = document.querySelector('.cron-checkbox:checked');
        if (!selected) return;
        const id = selected.closest('tr').dataset.id;
        App.navigate('/cron/edit?id=' + id);
    });
}

// Редактирование (контекстное меню)
document.querySelectorAll('.context-edit-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const dropdown = this.closest('.context-dropdown');
        if (!dropdown) return;
        const id = dropdown.id.replace('dropdown-cron-', '');
        dropdown.classList.remove('show');
        App.navigate('/cron/edit?id=' + id);
    });
});

// Запуск (контекстное меню)
document.querySelectorAll('.context-run-btn').forEach(btn => {
    btn.addEventListener('click', async function(e) {
        e.preventDefault();
        const dropdown = this.closest('.context-dropdown');
        if (!dropdown) return;
        const id = dropdown.id.replace('dropdown-cron-', '');
        dropdown.classList.remove('show');
        
        try {
            const formData = new FormData();
            formData.append('id', id);
            const res = await fetch('/api/cron/run', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                alert("Задание выполнено!\nВывод:\n" + (data.output || 'нет вывода'));
            } else {
                alert("Ошибка выполнения:\n" + (data.error || 'Неизвестная ошибка'));
            }
        } catch (err) {
            alert('Сетевая ошибка');
        }
    });
});
})();
</script>
