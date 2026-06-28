import { App } from './core/app.js';

document.addEventListener('DOMContentLoaded', () => {
    window.App = App;
    App.init();
    console.log('[SiteManager] Фронтенд успешно запущен и готов к работе.');

    initDropdowns();
    initNotifications();
});

function initDropdowns() {
    const notifBtn = document.getElementById('notificationBtn');
    const notifDropdown = document.getElementById('notificationDropdown');
    const userBtn = document.getElementById('userProfileBtn');
    const userDropdown = document.getElementById('userDropdown');

    function closeAll() {
        if(notifDropdown) notifDropdown.classList.remove('show');
        if(userDropdown) userDropdown.classList.remove('show');
    }

    if(notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isShowing = notifDropdown.classList.contains('show');
            closeAll();
            if(!isShowing) notifDropdown.classList.add('show');
        });
    }

    if(userBtn && userDropdown) {
        userBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isShowing = userDropdown.classList.contains('show');
            closeAll();
            if(!isShowing) userDropdown.classList.add('show');
        });
    }

    document.addEventListener('click', (e) => {
        if(notifDropdown && !notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
            notifDropdown.classList.remove('show');
        }
        if(userDropdown && !userDropdown.contains(e.target) && !userBtn.contains(e.target)) {
            userDropdown.classList.remove('show');
        }
    });
}

async function initNotifications() {
    const badge = document.getElementById('notificationBadge');
    const list = document.getElementById('notificationList');
    const markAllBtn = document.getElementById('markAllReadBtn');
    if(!badge || !list) return;

    const token = localStorage.getItem('panel_token');

    async function loadNotifs() {
        try {
            const res = await fetch('/api/notifications', {
                headers: { 'X-Panel-Token': token }
            });
            const data = await res.json();
            if(data.success) {
                renderNotifs(data.data);
            }
        } catch(e) {
            console.error('Failed to load notifications', e);
        }
    }

    function renderNotifs(items) {
        list.innerHTML = '';
        if(items.length === 0) {
            badge.classList.add('hidden');
            list.innerHTML = '<div class="empty-state">Нет новых уведомлений</div>';
            return;
        }

        badge.classList.remove('hidden');
        badge.innerText = items.length > 9 ? '9+' : items.length;

        items.forEach(item => {
            const el = document.createElement('div');
            el.className = 'notification-item unread';
            el.innerHTML = `
                <div class="notif-title">${item.title}</div>
                <div class="notif-msg">${item.message}</div>
                <div class="notif-time">${new Date(item.created_at).toLocaleString()}</div>
            `;
            list.appendChild(el);
        });
    }

    if(markAllBtn) {
        markAllBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            try {
                await fetch('/api/notifications/read', {
                    method: 'POST',
                    headers: { 'X-Panel-Token': token, 'Content-Type': 'application/json' },
                    body: JSON.stringify({})
                });
                loadNotifs();
            } catch(e) {}
        });
    }

    loadNotifs();
    // Poll every 30s
    setInterval(loadNotifs, 30000);
}

// Глобальные функции для формы БД
window.togglePassword = function(id) {
    const input = document.getElementById(id);
    if (!input) return;
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
};

window.generatePassword = function() {
    const length = 16;
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+~`|}{[]:;?><,./-=";
    let retVal = "";
    for (let i = 0, n = charset.length; i < length; ++i) {
        retVal += charset.charAt(Math.floor(Math.random() * n));
    }
    const pass1 = document.getElementById('password');
    const pass2 = document.getElementById('password_confirm');
    if (pass1) { pass1.value = retVal; pass1.setAttribute('type', 'text'); }
    if (pass2) { pass2.value = retVal; pass2.setAttribute('type', 'text'); }
};

document.addEventListener('submit', async (e) => {
    if (e.target && e.target.id === 'createDatabaseForm') {
        e.preventDefault();
        
        const formError = document.getElementById('formError');
        const formSuccess = document.getElementById('formSuccess');
        const submitBtn = document.getElementById('submitBtn');
        
        if (formError) formError.classList.add('hidden');
        if (formSuccess) formSuccess.classList.add('hidden');
        
        const pass1 = document.getElementById('password').value;
        const pass2 = document.getElementById('password_confirm').value;

        if (pass1 !== pass2) {
            if (formError) {
                formError.innerText = 'Пароли не совпадают!';
                formError.classList.remove('hidden');
            }
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Создание...';
        }

        const formData = new FormData(e.target);

        try {
            const res = await fetch('/api/databases/create', {
                method: 'POST',
                headers: {
                    'X-Panel-Token': localStorage.getItem('panel_token')
                },
                body: formData
            });

            const data = await res.json();
            
            if (data.success) {
                if (formSuccess) {
                    formSuccess.innerText = data.message;
                    formSuccess.classList.remove('hidden');
                }
                e.target.reset();
                setTimeout(() => App.navigate('/databases', 'Базы данных'), 1000);
            } else {
                if (formError) {
                    formError.innerText = data.message;
                    formError.classList.remove('hidden');
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerText = 'Создать';
                }
            }
        } catch (err) {
            if (formError) {
                formError.innerText = 'Ошибка соединения с сервером';
                formError.classList.remove('hidden');
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Создать';
            }
        }
    }
});

// Глобальная функция для удаления БД
window.deleteDatabase = async function(id, name) {
    if (!confirm(`Вы действительно хотите удалить базу данных ${name} и всех ее пользователей? Это действие необратимо!`)) return;

    try {
        const res = await fetch('/api/databases/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Panel-Token': localStorage.getItem('panel_token')
            },
            body: JSON.stringify({ id })
        });

        const data = await res.json();
        // Временно используем alert, если showAlert не определен в глобальной области
        const notify = window.showAlert ? window.showAlert : (msg, isErr) => alert(msg);
        
        if (data.success) {
            notify(data.message, false);
            setTimeout(() => {
                // Если App импортирован, но не доступен глобально
                // Заметим, что App здесь берется из замыкания модуля main.js
                // Так как это внутри main.js, переменная App доступна!
                App.navigate('/databases', 'Базы данных');
            }, 1000);
        } else {
            notify(data.message, true);
        }
    } catch (e) {
        if (window.showAlert) window.showAlert('Ошибка соединения с сервером', true);
        else alert('Ошибка соединения с сервером');
    }
};

// Делегирование для поиска БД
document.addEventListener('input', (e) => {
    if (e.target && e.target.id === 'searchDatabase') {
        const text = e.target.value.toLowerCase();
        document.querySelectorAll('.data-table tbody tr').forEach(row => {
            if (row.children.length === 1) return; // empty row
            const name = row.children[1].textContent.toLowerCase();
            const user = row.children[2].textContent.toLowerCase();
});
    }
});

// --- Глобальные функции Файлового Менеджера ---

window.getCurrentManagerPath = function() {
    const params = new URLSearchParams(window.location.search);
    return params.get('path') || '/';
};

window.showCreateModal = async function(type) {
    document.querySelectorAll('.context-dropdown').forEach(d => d.classList.remove('show'));
    const name = prompt(type === 'folder' ? 'Введите имя новой папки:' : 'Введите имя нового файла:');
    if (!name) return;

    try {
        const res = await fetch('/api/manager/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Panel-Token': localStorage.getItem('panel_token') || ''
            },
            body: JSON.stringify({ path: window.getCurrentManagerPath(), name, type })
        });
        const data = await res.json();
        if (data.success) {
            if(typeof App !== 'undefined') App.navigate(window.location.pathname + window.location.search);
            else location.reload();
        } else alert(data.message);
    } catch (e) {
        alert('Ошибка при создании');
    }
};

window.openAttrModal = async function(name) {
    document.querySelectorAll('.context-dropdown').forEach(d => d.classList.remove('show'));
    
    try {
        const res = await fetch(`/api/manager/attributes?path=${encodeURIComponent(window.getCurrentManagerPath())}&name=${encodeURIComponent(name)}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Panel-Token': localStorage.getItem('panel_token') || ''
            }
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.message);
            return;
        }
        
        const attr = data.data;
        document.getElementById('attrModalTitle').innerText = `Атрибуты - ${attr.name}`;
        document.getElementById('attrOldName').value = attr.name;
        document.getElementById('attrName').value = attr.name;
        
        const ownerSelect = document.getElementById('attrOwner');
        ownerSelect.innerHTML = attr.users.map(u => `<option value="${u}" ${u === attr.owner ? 'selected' : ''}>${u}</option>`).join('');
        
        const groupSelect = document.getElementById('attrGroup');
        groupSelect.innerHTML = attr.groups.map(g => `<option value="${g}" ${g === attr.group ? 'selected' : ''}>${g}</option>`).join('');
        
        document.getElementById('attrRecursiveGroup').style.display = attr.is_dir ? 'block' : 'none';
        
        document.getElementById('attrPerms').value = attr.perms.padStart(4, '0');
        updatePermCheckboxesFromOctal(attr.perms.padStart(4, '0'));
        
        document.getElementById('attrModal').style.display = 'flex';
    } catch (e) {
        alert('Ошибка при загрузке файла');
    }
}

// File Manager specific
document.addEventListener('change', (e) => {
    if (e.target.id === 'selectAllFiles') {
        const checked = e.target.checked;
        document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = checked);
    }
    
    if (e.target.classList.contains('file-checkbox') || e.target.id === 'selectAllFiles') {
        const checkedBoxes = document.querySelectorAll('.file-checkbox:checked');
        const toolbarButtons = document.querySelectorAll('.toolbar-container .btn.disabled');
        const attrBtn = document.querySelector('.toolbar-attr-btn');
        const delBtn = document.querySelector('.toolbar-del-btn');
        
        if (checkedBoxes.length > 0) {
            // Enable basic buttons
        } else {
            // Disable buttons
        }
        
        if (attrBtn) {
            if (checkedBoxes.length === 1) {
                attrBtn.classList.remove('disabled');
                attrBtn.removeAttribute('disabled');
                
                const oldClone = attrBtn.cloneNode(true);
                attrBtn.parentNode.replaceChild(oldClone, attrBtn);
                oldClone.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    const row = checkedBoxes[0].closest('tr');
                    const fileName = row.querySelector('td:nth-child(2)').textContent.trim();
                    window.openAttrModal(fileName);
                });
            } else {
                attrBtn.classList.add('disabled');
                attrBtn.setAttribute('disabled', 'disabled');
                const oldClone = attrBtn.cloneNode(true);
                attrBtn.parentNode.replaceChild(oldClone, attrBtn);
            }
        }

        if (delBtn) {
            if (checkedBoxes.length > 0) {
                delBtn.classList.remove('disabled');
                delBtn.removeAttribute('disabled');
                
                const oldClone = delBtn.cloneNode(true);
                delBtn.parentNode.replaceChild(oldClone, delBtn);
                oldClone.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    if (!confirm(`Удалить выбранные элементы (${checkedBoxes.length} шт.)? Это действие необратимо!`)) return;

                    const deletePromises = Array.from(checkedBoxes).map(async (cb) => {
                        const row = cb.closest('tr');
                        const fileName = row.querySelector('td:nth-child(2)').textContent.trim();
                        try {
                            const res = await fetch('/api/manager/delete', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ path: window.getCurrentManagerPath(), name: fileName })
                            });
                            const data = await res.json();
                            if (!data.success) {
                                console.error('Ошибка удаления', fileName, data.message);
                            }
                        } catch (e) {
                            console.error('Ошибка сети при удалении', fileName);
                        }
                    });

                    Promise.all(deletePromises).then(() => {
                        if(typeof App !== 'undefined') App.navigate(window.location.pathname + window.location.search);
                        else location.reload();
                    });
                });
            } else {
                delBtn.classList.add('disabled');
                delBtn.setAttribute('disabled', 'disabled');
                const oldClone = delBtn.cloneNode(true);
                delBtn.parentNode.replaceChild(oldClone, delBtn);
            }
        }
    }
});;

window.closeAttrModal = function() {
    document.getElementById('attrModal').style.display = 'none';
};

window.submitAttr = async function() {
    const formData = new FormData(document.getElementById('attrForm'));
    formData.append('path', window.getCurrentManagerPath());
    
    try {
        const res = await fetch('/api/manager/attributes', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Panel-Token': localStorage.getItem('panel_token') || ''
            },
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            window.closeAttrModal();
            if(typeof App !== 'undefined') App.navigate(window.location.pathname + window.location.search);
            else location.reload();
        } else {
            alert(data.message);
        }
    } catch (e) {
        alert('Ошибка сохранения атрибутов');
    }
};

function updatePermCheckboxesFromOctal(octalStr) {
    const perms = parseInt(octalStr, 8);
    document.querySelectorAll('.perm-cb').forEach(cb => {
        const bit = parseInt(cb.getAttribute('data-bit'), 10);
        cb.checked = (perms & bit) === bit;
    });
}

document.addEventListener('change', (e) => {
    if (e.target.classList.contains('perm-cb')) {
        let perms = 0;
        document.querySelectorAll('.perm-cb').forEach(cb => {
            if (cb.checked) {
                perms |= parseInt(cb.getAttribute('data-bit'), 10);
            }
        });
        const octalStr = perms.toString(8).padStart(4, '0');
        document.getElementById('attrPerms').value = octalStr;
    }
});

document.addEventListener('input', (e) => {
    if (e.target.id === 'attrPerms') {
        const val = e.target.value.padStart(4, '0');
        if (/^[0-7]{4}$/.test(val)) {
            updatePermCheckboxesFromOctal(val);
        }
    }
});

window.deleteFileManagerItem = async function(name) {
    if (!confirm(`Удалить "${name}"? Это действие необратимо!`)) return;

    try {
        const res = await fetch('/api/manager/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ path: window.getCurrentManagerPath(), name })
        });
        const data = await res.json();
        if (data.success) {
            if(typeof App !== 'undefined') App.navigate(window.location.pathname + window.location.search);
            else location.reload();
        } else alert(data.message);
    } catch (e) {
        alert('Ошибка сети');
    }
};

window.toggleUploadPanel = function(show) {
    const panel = document.getElementById('uploadOffcanvas');
    const backdrop = document.getElementById('offcanvasBackdrop');
    if(!panel || !backdrop) return;
    if (show) {
        panel.classList.add('show');
        backdrop.classList.add('show');
    } else {
        panel.classList.remove('show');
        backdrop.classList.remove('show');
        setTimeout(() => backdrop.style.display = 'none', 300);
    }
};

window.toggleUploadType = function(radio) {
    const local = document.getElementById('uploadLocalArea');
    const url = document.getElementById('uploadUrlArea');
    if(local) local.style.display = radio.value === 'local' ? 'block' : 'none';
    if(url) url.style.display = radio.value === 'url' ? 'block' : 'none';
};

// Global event delegation for fileInput
document.addEventListener('change', (e) => {
    if (e.target && e.target.id === 'fileInput') {
        const file = e.target.files[0];
        const label = document.getElementById('selectedFileName');
        if(label) label.innerText = file ? file.name : '';
    }
});

window.submitUpload = async function() {
    const typeRadio = document.querySelector('input[name="upload_type"]:checked');
    if(!typeRadio) return;
    const type = typeRadio.value;
    const formData = new FormData();
    formData.append('path', window.getCurrentManagerPath());

    if (type === 'local') {
        const fileInput = document.getElementById('fileInput');
        if(!fileInput) return;
        const file = fileInput.files[0];
        if (!file) return alert('Выберите файл');
        formData.append('file', file);
    } else {
        const fileUrlInput = document.getElementById('fileUrlInput');
        if(!fileUrlInput) return;
        const url = fileUrlInput.value;
        if (!url) return alert('Введите URL');
        formData.append('url', url);
    }

    try {
        const res = await fetch('/api/manager/upload', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            window.toggleUploadPanel(false);
            if(typeof App !== 'undefined') App.navigate(window.location.pathname + window.location.search);
            else location.reload();
        } else {
            alert(data.message);
        }
    } catch (e) {
        alert('Ошибка сети при загрузке');
    }
};