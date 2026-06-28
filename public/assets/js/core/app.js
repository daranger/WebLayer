import { Dom } from './dom.js';
import { Api } from './api.js';

class AppEngine {
    constructor() {
        this.contentArea = Dom.one('#pjax-container');
        this.isLoading = false;
        this.activeController = null;
        // 🔥 Пульт управления отменой запросов статистики
        this.statsAbortController = null;
    }

    init() {
        Dom.on(document.body, 'click', 'a[data-module], .sidebar-menu a:not(.logout)', (e, target) => {
            e.preventDefault();
            const url = target.getAttribute('href');
            if (!url || url === '#') return;

            const title = target.getAttribute('data-title') || target.innerText;

            document.querySelectorAll('.sidebar-menu .menu-item').forEach(item => item.classList.remove('active'));
            target.classList.add('active');

            this.navigate(url, title);
        });

        window.addEventListener('popstate', (e) => {
            this.loadUrl(window.location.pathname + window.location.search, false);
        });

        this.initGlobalEvents();

        if (window.location.pathname !== '/login') {
            this.syncGlobalCounters();
        }
    }

    navigate(url, title) {
        this.loadUrl(url, true, title);
    }

    async loadUrl(url, updateHistory = true, title = '') {
        if (this.isLoading) return;
        this.isLoading = true;

        // Мгновенно убиваем фоновый пуллинг статистики при любом переходе по сайту!
        if (this.statsAbortController) {
            this.statsAbortController.abort();
            this.statsAbortController = null;
        }

        if (this.activeController) {
            this.activeController.abort();
        }
        this.activeController = new AbortController();
        const signal = this.activeController.signal;

        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Panel-Token': localStorage.getItem('panel_token') || ''
                },
                signal: signal
            });

            if (response.status === 401) {
                localStorage.removeItem('panel_token');
                window.location.href = '/login';
                return;
            }

            if (!response.ok) {
                throw new Error(`Статус ответа: ${response.status}`);
            }

            const htmlContent = await response.text();

            if (this.contentArea) {
                // Очищаем "осиротевшие" выпадающие меню, которые были перенесены в body
                document.querySelectorAll('body > .context-dropdown').forEach(d => d.remove());
                this.contentArea.innerHTML = htmlContent;
                this.executeScripts(this.contentArea);
            } else {
                this.contentArea = Dom.one('#pjax-container');
                if (this.contentArea) {
                    this.contentArea.innerHTML = htmlContent;
                    this.executeScripts(this.contentArea);
                }
            }

            const headerTitle = Dom.one('#page-title');
            if (headerTitle && title) {
                headerTitle.innerText = title.replace(/ - SiteManager/g, '');
            }

            if (title) {
                document.title = title.includes('SiteManager') ? title : `${title} | SiteManager`;
            }

            if (updateHistory) {
                window.history.pushState({ url }, '', url);
            }

            // Перезапуск фоновых счетчиков для нового контента
            if (window.location.pathname !== '/login') {
                this.syncGlobalCounters();
            }

        } catch (error) {
            if (error.name === 'AbortError') return;
            if (this.contentArea) {
                this.contentArea.innerHTML = `<div class="card full-width" style="color: var(--danger); border-left: 4px solid var(--danger);">Ошибка загрузки: ${error.message}</div>`;
            }
        } finally {
            this.isLoading = false;
        }
    }

    executeScripts(element) {
        const scripts = element.querySelectorAll('script');
        scripts.forEach(oldScript => {
            const newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
            newScript.appendChild(document.createTextNode(oldScript.innerHTML));
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }

    initGlobalEvents() {
        // --- ДИНАМИЧЕСКИЙ ХЕНДЛЕР ИЗМЕНЕНИЯ ВЕРСИЙ НА ФОРМЕ ---
        Dom.on(document.body, 'change', '#handler', (e, target) => {
            const handler = target.value;
            const versionSelect = Dom.one('#version');
            const versionGroup = Dom.one('#version-group');

            if (!versionSelect || !versionGroup) return;
            versionSelect.innerHTML = '';

            if (handler === 'PHP') {
                versionGroup.style.display = 'flex';
                versionSelect.innerHTML = `
                    <option value="8.3">8.3 (alt)</option>
                    <option value="8.2">8.2 (alt)</option>
                    <option value="8.0">8.0.24 (alt)</option>
                `;
            } else if (handler === 'NodeJS') {
                versionGroup.style.display = 'flex';
                versionSelect.innerHTML = `
                    <option value="20">20.x LTS</option>
                    <option value="18">18.x LTS</option>
                `;
            } else if (handler === 'Static') {
                versionGroup.style.display = 'none';
                versionSelect.innerHTML = `<option value="none">none</option>`;
            }
        });

        // --- УПРАВЛЕНИЕ КНОПКАМИ ТУЛБАРА ПРИ ВЫБОРЕ ЧЕКБОКСОВ ---
        const updateToolbarState = () => {
            const checkedBoxes = document.querySelectorAll('.site-checkbox:checked');
            const toolbarButtons = document.querySelectorAll('.toolbar-edit-btn, .toolbar-delete-btn, .toolbar-config-btn, .toolbar-folder-btn, .toolbar-cms-btn');

            if (checkedBoxes.length === 1) {
                toolbarButtons.forEach(btn => {
                    btn.classList.remove('disabled');
                    btn.removeAttribute('disabled');
                });
            } else if (checkedBoxes.length > 1) {
                toolbarButtons.forEach(btn => {
                    if (btn.classList.contains('toolbar-delete-btn')) {
                        btn.classList.remove('disabled');
                        btn.removeAttribute('disabled');
                    } else {
                        btn.classList.add('disabled');
                        btn.setAttribute('disabled', 'true');
                    }
                });
            } else {
                toolbarButtons.forEach(btn => {
                    btn.classList.add('disabled');
                    btn.setAttribute('disabled', 'true');
                });
            }
        };

        Dom.on(document.body, 'change', '.site-checkbox', () => {
            updateToolbarState();
        });

        Dom.on(document.body, 'change', '#selectAllSites', (e, target) => {
            const checkboxes = document.querySelectorAll('.site-checkbox');
            checkboxes.forEach(cb => cb.checked = target.checked);
            updateToolbarState();
        });

        // --- УДАЛЕНИЕ САЙТА ---
        const executeDelete = async (siteId, domainName) => {
            if (!confirm(`Вы уверены, что хотите безопасно удалить сайт ${domainName}? Это действие инициирует удаление папок и конфигураций.`)) {
                return;
            }

            try {
                await Api.request('/api/sites', {
                    method: 'DELETE',
                    body: JSON.stringify({ id: siteId }),
                    headers: { 'X-Panel-Token': localStorage.getItem('panel_token') || '' }
                });

                this.navigate('/sites', 'Сайты');

            } catch (err) {
                alert(err.message || 'Ошибка при отправке запроса на удаление');
            }
        };

        Dom.on(document.body, 'click', '.delete-site-btn', (e, target) => {
            e.preventDefault();
            
            let row = target.closest('tr');
            let siteId, domainName;
            
            // Если кнопку нажали в контекстном меню, меню уже в body, tr не найдет
            if (!row) {
                const dropdown = target.closest('.context-dropdown');
                if (dropdown) {
                    siteId = dropdown.id.replace('dropdown-', '');
                    row = document.querySelector(`tr[data-site-id="${siteId}"]`);
                }
            }
            
            if (!row) return;

            siteId = siteId || row.getAttribute('data-site-id');
            domainName = row.querySelector('.domain-name a').innerText.trim();
            
            executeDelete(siteId, domainName);
            
            // Закрываем меню после клика
            const dropdown = target.closest('.context-dropdown');
            if (dropdown) dropdown.classList.remove('show');
        });

        Dom.on(document.body, 'click', '.toolbar-delete-btn', (e) => {
            e.preventDefault();
            const checkedBoxes = document.querySelectorAll('.site-checkbox:checked');

            checkedBoxes.forEach(cb => {
                const row = cb.closest('tr');
                const siteId = row.getAttribute('data-site-id');
                const domainName = row.querySelector('.domain-name a').innerText.trim();
                executeDelete(siteId, domainName);
            });
        });

        // --- ПЕРЕХОД К РЕДАКТИРОВАНИЮ САЙТА ---
        Dom.on(document.body, 'click', '.toolbar-edit-btn', (e) => {
            e.preventDefault();
            const checkedBoxes = document.querySelectorAll('.site-checkbox:checked');
            if (checkedBoxes.length === 1) {
                const row = checkedBoxes[0].closest('tr');
                const siteId = row.getAttribute('data-site-id');
                if (siteId) {
                    this.navigate('/sites/edit?id=' + siteId, 'Настройки сайта');
                }
            }
        });

        // --- ПЕРЕХОД К ФАЙЛАМ САЙТА ---
        Dom.on(document.body, 'click', '.toolbar-folder-btn', (e) => {
            e.preventDefault();
            const checkedBoxes = document.querySelectorAll('.site-checkbox:checked');
            if (checkedBoxes.length === 1) {
                const row = checkedBoxes[0].closest('tr');
                const rootPath = row.getAttribute('data-root-path');
                if (rootPath) {
                    this.navigate('/manager?path=' + encodeURIComponent(rootPath), 'Файлы сайта');
                }
            }
        });

        // --- ПЕРЕХОД НА СТРАНИЦУ СОЗДАНИЯ САЙТА ---
        Dom.on(document.body, 'click', '.open-modal-btn', (e) => {
            e.preventDefault();
            this.navigate('/sites/create', 'Новый сайт');
        });

        // --- ФИКСИРОВАННАЯ АСИНХРОННАЯ ОТПРАВКА ФОРМЫ СОЗДАНИЯ ---
        Dom.on(document.body, 'submit', '#createSiteForm', async (e, form) => {
            e.preventDefault();

            const errorBlock = Dom.one('#formError');
            const successBlock = Dom.one('#formSuccess');
            const submitBtn = form.querySelector('button[type="submit"]');

            if (errorBlock) errorBlock.classList.add('hidden');
            if (successBlock) successBlock.classList.add('hidden');

            const formData = new FormData(form);
            // 🔥 ФИКС: Забираем домен корректно, чистим от пробелов
            const domain = formData.get('domain').trim().toLowerCase();

            if (!domain) return;

            // 🔥 ФИКС: Собираем чистый payload по феншую без костылей путей и ID
            const payload = {
                domain: domain,
                cms: formData.get('cms') || 'none',
                ssl: formData.get('ssl') || 'none',
                runtime: {
                    type: formData.get('handler').toLowerCase(),
                    version: formData.get('version')
                }
            };

            try {
                submitBtn.disabled = true;
                submitBtn.innerText = 'Создание...';

                const result = await Api.post('/api/sites', payload, {
                    headers: { 'X-Panel-Token': localStorage.getItem('panel_token') || '' }
                });

                if (successBlock) {
                    successBlock.innerText = result.message || 'Задача успешно добавлена в очередь!';
                    successBlock.classList.remove('hidden');
                }

                // Сбрасываем инпуты формы
                form.reset();
                const versionGroup = Dom.one('#version-group');
                if (versionGroup) versionGroup.style.display = 'flex';

                setTimeout(() => {
                    this.navigate('/sites', 'Сайты');
                }, 1500);

            } catch (err) {
                if (errorBlock) {
                    errorBlock.innerText = err.message || 'Произошла ошибка при создании';
                    errorBlock.classList.remove('hidden');
                }
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Создать';
            }
        });

        // --- ВХОД В ПАНЕЛЬ ---
        Dom.on(document.body, 'submit', '#loginForm', async (e, form) => {
            e.preventDefault();
            const errorBlock = Dom.one('#loginError');
            if (errorBlock) errorBlock.classList.add('hidden');

            const payload = Object.fromEntries(new FormData(form).entries());

            try {
                const result = await Api.post('/api/auth/login', payload);
                if (result.token) {
                    localStorage.setItem('panel_token', result.token);
                    window.location.href = '/';
                }
            } catch (err) {
                if (errorBlock) {
                    errorBlock.innerText = err.message || 'Неверный логин или пароль';
                    errorBlock.classList.remove('hidden');
                }
            }
        });

        // --- ВЫХОД ИЗ ПАНЕЛИ ---
        Dom.on(document.body, 'click', '#logoutBtn', async (e) => {
            e.preventDefault();
            const token = localStorage.getItem('panel_token');
            try {
                await Api.post('/api/auth/logout', {}, { headers: { 'X-Panel-Token': token } });
            } catch (err) {}

            localStorage.removeItem('panel_token');
            window.location.href = '/login';
        });

        // --- КОНТЕКСТНОЕ МЕНЮ ТАБЛИЦЫ ---
        Dom.on(document.body, 'click', '.context-trigger-btn', (e, target) => {
            e.stopPropagation();
            
            const dropdownId = target.getAttribute('data-dropdown');
            let dropdown = null;

            if (dropdownId) {
                dropdown = document.getElementById(dropdownId);
            } else {
                const row = target.closest('tr');
                const siteId = row ? row.getAttribute('data-site-id') : null;
                if (siteId) dropdown = Dom.one(`#dropdown-${siteId}`);
            }

            const isShowing = dropdown ? dropdown.classList.contains('show') : false;

            document.querySelectorAll('.context-dropdown').forEach(d => {
                d.classList.remove('show');
            });

            if (dropdown && !isShowing) {
                // Переносим меню в body, чтобы оно не обрезалось overflow и не ломалось от backdrop-filter
                if (dropdown.parentNode !== document.body) {
                    document.body.appendChild(dropdown);
                }

                dropdown.classList.add('show');
                
                const rect = target.getBoundingClientRect();
                dropdown.style.position = 'absolute';
                dropdown.style.right = 'auto';
                
                const dropWidth = dropdown.offsetWidth || 220;
                const dropHeight = dropdown.offsetHeight || 150;
                
                let top = rect.bottom + window.scrollY + 5;
                let left;
                
                // Smart alignment
                if (rect.left < window.innerWidth / 2) {
                    left = rect.left + window.scrollX;
                } else {
                    left = rect.right + window.scrollX - dropWidth;
                }
                
                if (rect.bottom + dropHeight > window.innerHeight) {
                    top = rect.top + window.scrollY - dropHeight - 5;
                }
                
                if (left + dropWidth > window.innerWidth + window.scrollX) left = window.innerWidth + window.scrollX - dropWidth - 10;
                if (left < window.scrollX + 10) left = window.scrollX + 10;
                
                dropdown.style.top = top + 'px';
                dropdown.style.left = left + 'px';
            }
        });

        // Закрываем меню при клике в любом месте
        document.addEventListener('click', () => {
            document.querySelectorAll('.context-dropdown').forEach(dropdown => dropdown.classList.remove('show'));
        });

        // Закрываем меню при любом скролле (очень важно для таблиц)
        document.addEventListener('scroll', (e) => {
            if (e.target && e.target.closest && !e.target.closest('.context-dropdown')) {
                document.querySelectorAll('.context-dropdown.show').forEach(d => d.classList.remove('show'));
            }
        }, true);

        // --- УПРАВЛЕНИЕ СЛУЖБАМИ ---
        Dom.on(document.body, 'click', '.control-service-btn', async (e, target) => {
            e.preventDefault();
            const service = target.getAttribute('data-service');
            const action = target.getAttribute('data-action');
            
            document.querySelectorAll('.context-dropdown.show').forEach(m => m.classList.remove('show'));
            if (window.showAlert) window.showAlert(`Команда "${action}" для ${service} отправлена...`, false);
            
            try {
                const res = await fetch('/api/services/control', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-Panel-Token': localStorage.getItem('panel_token') || ''
                    },
                    body: JSON.stringify({ service, action })
                });
                const data = await res.json();
                if (data.success) {
                    if (window.showAlert) window.showAlert('Команда успешно выполнена.', false);
                    setTimeout(() => this.navigate('/services', 'Службы'), 1500);
                } else {
                    if (window.showAlert) window.showAlert('Ошибка: ' + (data.error || 'Неизвестная ошибка'), true);
                }
            } catch (err) {
                if (window.showAlert) window.showAlert('Ошибка соединения', true);
            }
        });

        const updateServiceToolbar = () => {
            const checked = document.querySelectorAll('.service-checkbox:checked');
            const mainBtn = document.getElementById('mainToolbarBtn');
            const startBtn = document.getElementById('startToolbarBtn');
            const stopBtn = document.getElementById('stopToolbarBtn');
            
            if (mainBtn) {
                if (checked.length > 0) {
                    mainBtn.title = "Перезапустить выбранные";
                    mainBtn.classList.remove('disabled');
                    mainBtn.removeAttribute('disabled');
                    
                    if (startBtn) {
                        startBtn.classList.remove('disabled');
                        startBtn.removeAttribute('disabled');
                    }
                    if (stopBtn) {
                        stopBtn.classList.remove('disabled');
                        stopBtn.removeAttribute('disabled');
                    }
                } else {
                    mainBtn.title = "Обновить статусы (выберите службы)";
                    mainBtn.classList.add('disabled');
                    mainBtn.setAttribute('disabled', 'disabled');
                    
                    if (startBtn) {
                        startBtn.classList.add('disabled');
                        startBtn.setAttribute('disabled', 'disabled');
                    }
                    if (stopBtn) {
                        stopBtn.classList.add('disabled');
                        stopBtn.setAttribute('disabled', 'disabled');
                    }
                }
            }
        };

        Dom.on(document.body, 'change', '#selectAllServices', (e, target) => {
            document.querySelectorAll('.service-checkbox').forEach(cb => cb.checked = target.checked);
            updateServiceToolbar();
        });

        Dom.on(document.body, 'change', '.service-checkbox', () => {
            updateServiceToolbar();
        });

        Dom.on(document.body, 'click', '.mass-control-services-btn', async (e, target) => {
            e.preventDefault();
            
            if (target.hasAttribute('disabled') || target.classList.contains('disabled')) return;
            
            const action = target.getAttribute('data-action') || 'restart';
            const checked = document.querySelectorAll('.service-checkbox:checked');
            
            if (checked.length === 0) {
                if (action === 'restart') {
                    // Просто обновляем страницу, если ничего не выбрано (поведение старой кнопки)
                    this.navigate('/services', 'Службы');
                }
                return;
            }
            
            let allSuccess = true;
            
            const actionNames = {
                'start': 'запуск',
                'stop': 'остановка',
                'restart': 'перезапуск'
            };
            const actionName = actionNames[action] || action;
            
            if (window.showAlert) window.showAlert(`Массовый ${actionName} служб...`, false);
            
            for (let i = 0; i < checked.length; i++) {
                const row = checked[i].closest('tr');
                const service = row.getAttribute('data-service');
                try {
                    const res = await fetch('/api/services/control', {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'X-Panel-Token': localStorage.getItem('panel_token') || ''
                        },
                        body: JSON.stringify({ service: service, action: action })
                    });
                    
                    // Если это перезапуск Nginx или PHP, может быть 502 ошибка, так как обрывается соединение
                    if (!res.ok) {
                        if (res.status === 502 && (service.includes('php') || service.includes('nginx')) && (action === 'restart' || action === 'stop')) {
                            // Игнорируем ошибку обрыва соединения при рестарте веб-сервера
                            continue;
                        }
                        allSuccess = false;
                        continue;
                    }
                    
                    const data = await res.json();
                    if (!data.success) allSuccess = false;
                } catch(e) {
                    // Также прощаем Network Error (fetch failed), если рубим сук на котором сидим
                    if ((service.includes('php') || service.includes('nginx')) && (action === 'restart' || action === 'stop')) {
                        continue;
                    }
                    allSuccess = false;
                }
            }
            
            if (allSuccess) {
                if (window.showAlert) window.showAlert('Все службы успешно обработаны.', false);
            } else {
                if (window.showAlert) window.showAlert(`Возникли ошибки (${actionName} некоторых служб).`, true);
            }
            setTimeout(() => this.navigate('/services', 'Службы'), 2000);
        });

        // --- ЖИВОЙ ПОИСК ---
        Dom.on(document.body, 'keyup', '#siteSearch', (e, target) => {
            const filter = target.value.toLowerCase();
            const rows = document.querySelectorAll('#sitesTable tbody tr');

            rows.forEach(row => {
                const domainCell = row.querySelector('.domain-name');
                if (domainCell) {
                    const txtValue = domainCell.textContent || domainCell.innerText;
                    row.style.display = txtValue.toLowerCase().indexOf(filter) > -1 ? "" : "none";
                }
            });
        });

        // Клик по строке "Процессы" на главной
        Dom.on(document.body, 'click', '#row-processes', (e) => {
            this.navigate('/processes', 'Процессы');
        });
    }

    // --- Менеджер процессов ---
    async loadProcesses() {
        if (location.pathname !== '/processes') return;
        
        try {
            const res = await fetch('/api/processes', {
                headers: { 'X-Panel-Token': localStorage.getItem('panel_token') || '' }
            });
            const data = await res.json();
            
            if (data.success) {
                window.allProcesses = data.processes || [];
                window.renderProcesses();
            } else {
                document.getElementById('processesList').innerHTML = `<tr><td colspan="8" style="text-align: center; color: var(--danger);">Ошибка: ${data.error}</td></tr>`;
            }
        } catch (e) {
            document.getElementById('processesList').innerHTML = `<tr><td colspan="8" style="text-align: center; color: var(--danger);">Ошибка сети: ${e.message}</td></tr>`;
        }
    }

    async syncGlobalCounters() {
        const isDashboard = window.location.pathname === '/' || window.location.pathname === '/dashboard';
        if (!isDashboard) return;

        let resourcesChart = null;
        const canvas = document.getElementById('serverResourcesChart');

        if (canvas && typeof Chart !== 'undefined') {
            const ctx = canvas.getContext('2d');
            const timeLabels = ['150s', '140s', '130s', '120s', '110s', '100s', '90s', '80s', '70s', '60s', '50s', '40s', '30s', '20s', '10s'];

            resourcesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: timeLabels,
                    datasets: [
                        {
                            data: [],
                            label: 'Диск',
                            borderColor: '#6366f1',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            pointHitRadius: 15,
                            tension: 0.2
                        },
                        {
                            data: [],
                            label: 'Оперативная память',
                            borderColor: '#f59e0b',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            pointHitRadius: 15,
                            tension: 0.2
                        },
                        {
                            data: [],
                            label: 'Процессор (CPU)',
                            borderColor: '#10b981',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            pointHitRadius: 15,
                            tension: 0.2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'nearest',
                        intersect: false
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            padding: 10,
                            cornerRadius: 6,
                            bodyFont: { size: 12 },
                            titleFont: { size: 11, weight: 'bold' }
                        }
                    },
                    scales: {
                        y: {
                            min: 0,
                            max: 100,
                            grid: { color: 'rgba(255, 255, 255, 0.06)' },
                            ticks: {
                                font: { size: 10 },
                                color: '#64748b',
                                stepSize: 25,
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 10 },
                                color: '#64748b'
                            }
                        }
                    }
                }
            });
        }

        // 🔥 ФИКС: Пересобираем рекурсивный опрос с поддержкой AbortController на уровне класса
        const fetchStats = async () => {
            const cpuBar = document.getElementById('cpu-progress');

            // ГЛАВНЫЙ СТОПОР: Если ушли с дашборда — выходим и тушим старый контроллер
            if (!cpuBar) {
                if (this.statsAbortController) {
                    this.statsAbortController.abort();
                    this.statsAbortController = null;
                }
                return;
            }

            // Создаем контроллер, если он еще не поднят
            if (!this.statsAbortController) {
                this.statsAbortController = new AbortController();
            }

            try {
                const response = await fetch('/api/dashboard/stats', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Panel-Token': localStorage.getItem('panel_token') || ''
                    },
                    signal: this.statsAbortController.signal // Привязали актуальный сигнал отмены
                });

                if (response.status === 401) {
                    localStorage.removeItem('panel_token');
                    return;
                }

                const data = await response.json();
                if (!data || !data.success) return;

                const m = data.metrics;

                // Процессор
                cpuBar.style.width = m.cpu.percent + '%';
                document.getElementById('cpu-val-current').innerText = m.cpu.percent + '%';
                document.getElementById('cpu-val-total').innerText = 'LA: ' + m.cpu.la;
                cpuBar.classList.remove('warning', 'danger');
                if (m.cpu.percent >= 90) cpuBar.classList.add('danger'); else if (m.cpu.percent >= 60) cpuBar.classList.add('warning');

                // Оперативка
                const ramBar = document.getElementById('ram-progress');
                if (ramBar) {
                    ramBar.style.width = m.ram.percent + '%';
                    document.getElementById('ram-val-current').innerText = m.ram.used + ' GB (' + m.ram.percent + '%)';
                    document.getElementById('ram-val-total').innerText = m.ram.total + ' GB';
                    ramBar.classList.remove('warning', 'danger');
                    if (m.ram.percent >= 90) ramBar.classList.add('danger'); else if (m.ram.percent >= 60) ramBar.classList.add('warning');
                }

                // Диск
                const diskBar = document.getElementById('disk-progress');
                if (diskBar) {
                    diskBar.style.width = m.disk.percent + '%';
                    document.getElementById('disk-val-current').innerText = m.disk.used + ' GB (' + m.disk.percent + '%)';
                    document.getElementById('disk-val-total').innerText = m.disk.total + ' GB';
                    diskBar.classList.remove('warning', 'danger');
                    if (m.disk.percent >= 90) diskBar.classList.add('danger'); else if (m.disk.percent >= 60) diskBar.classList.add('warning');
                }

                // Текстовая инфа
                const softPhp = document.getElementById('soft-php-version');
                if (softPhp) {
                    softPhp.innerText = m.php_version;
                    document.getElementById('soft-redis-version').innerText = m.redis_version;
                    document.getElementById('soft-kernel').innerText = m.kernel;
                    
                    if (m.os_name) {
                        const sysOs = document.getElementById('sys-os-name');
                        if (sysOs) sysOs.innerText = m.os_name;
                    }
                    if (m.nginx_version) {
                        document.getElementById('soft-nginx-version').innerText = m.nginx_version;
                    }
                    if (m.mysql_version) {
                        document.getElementById('soft-mysql-version').innerText = m.mysql_version;
                    }
                    if (m.processor) {
                        document.getElementById('sys-processor').innerText = m.processor;
                    }
                    
                    document.getElementById('sys-ram-summary').innerText = m.ram.total + ' GB';
                    document.getElementById('sys-disk-summary').innerText = m.disk.total + ' GB';
                    document.getElementById('sys-uptime').innerText = m.uptime;
                    
                    if (m.process_count !== undefined) {
                        const sysProc = document.getElementById('sys-processes');
                        if (sysProc) sysProc.innerText = m.process_count;
                    }
                }

                // Двигаем график
                if (resourcesChart) {
                    const staticLabels = ['150s', '140s', '130s', '120s', '110s', '100s', '90s', '80s', '70s', '60s', '50s', '40s', '30s', '20s', '10s'];
                    
                    if (data.history && data.history.length > 0) {
                        let paddedDisk = Array(15).fill(0);
                        let paddedRam = Array(15).fill(0);
                        let paddedCpu = Array(15).fill(0);
                        
                        const startIdx = Math.max(0, 15 - data.history.length);
                        for (let i = 0; i < data.history.length; i++) {
                            if (startIdx + i < 15) {
                                paddedDisk[startIdx + i] = data.history[i].disk.percent;
                                paddedRam[startIdx + i] = data.history[i].ram.percent;
                                paddedCpu[startIdx + i] = data.history[i].cpu.percent;
                            }
                        }
                        
                        resourcesChart.data.labels = staticLabels;
                        resourcesChart.data.datasets[0].data = paddedDisk;
                        resourcesChart.data.datasets[1].data = paddedRam;
                        resourcesChart.data.datasets[2].data = paddedCpu;
                    } else {
                        if (resourcesChart.data.labels.length !== 15) resourcesChart.data.labels = staticLabels;
                        if (resourcesChart.data.datasets[0].data.length !== 15) resourcesChart.data.datasets[0].data = Array(15).fill(0);
                        if (resourcesChart.data.datasets[1].data.length !== 15) resourcesChart.data.datasets[1].data = Array(15).fill(0);
                        if (resourcesChart.data.datasets[2].data.length !== 15) resourcesChart.data.datasets[2].data = Array(15).fill(0);
                        
                        resourcesChart.data.datasets[0].data.push(m.disk.percent); resourcesChart.data.datasets[0].data.shift();
                        resourcesChart.data.datasets[1].data.push(m.ram.percent); resourcesChart.data.datasets[1].data.shift();
                        resourcesChart.data.datasets[2].data.push(m.cpu.percent); resourcesChart.data.datasets[2].data.shift();
                    }
                    resourcesChart.update('none');
                }

                // Таблица задач
                const jobsBody = document.querySelector('#dashboard-jobs-table tbody');
                if (jobsBody) {
                    jobsBody.innerHTML = data.jobs.map(j => `
                        <tr>
                            <td style="padding: 10px 0;">${j.name}</td>
                            <td style="text-align: right; padding: 10px 0;">
                                <i class="fa-solid ${j.status === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}" 
                                   style="color: ${j.status === 'success' ? 'var(--success)' : 'var(--danger)'}; font-size: 15px;"></i>
                            </td>
                        </tr>
                    `).join('');
                }

                // Таблица логов
                const logsBody = document.querySelector('#dashboard-logs-table tbody');
                if (logsBody) {
                    logsBody.innerHTML = data.logs.map(l => `
                        <tr>
                            <td style="padding: 10px 0;">${l.created_at}</td>
                            <td style="text-align: center; font-weight: 600; padding: 10px 0;">${l.username}</td>
                            <td style="text-align: right; padding: 10px 0;" class="text-monospace">${l.ip_address}</td>
                        </tr>
                    `).join('');
                }

                // Если дашборд всё ещё открыт — планируем новый запуск через 10 сек
                if (document.getElementById('cpu-progress')) {
                    setTimeout(() => fetchStats(), 10000);
                }

            } catch (err) {
                if (err.name === 'AbortError') {
                    console.log("✈️ Пуллинг stats успешно аннулирован контроллером класса.");
                    return;
                }
                console.error("Ошибка пула телеметрии:", err);

                if (document.getElementById('cpu-progress')) {
                    setTimeout(() => fetchStats(), 10000);
                }
            }
        };

        // Запускаем пуллинг
        fetchStats();
    }
}

export const App = new AppEngine();

// --- СЕРВЕРЫ БАЗ ДАННЫХ ---
window.openCreateDbServerModal = function() {
    const modal = document.getElementById('createDbServerModal');
    if (modal.parentElement !== document.body) document.body.appendChild(modal);
    document.getElementById('createDbServerForm').reset();
    modal.style.display = 'flex';
};

window.closeCreateDbServerModal = function() {
    document.getElementById('createDbServerModal').style.display = 'none';
};

window.submitCreateDbServer = async function() {
    const form = document.getElementById('createDbServerForm');
    if (!form.reportValidity()) return;
    
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    
    const btn = document.getElementById('btnCreateDbServer');
    const oldText = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Создание...';
    
    try {
        const res = await fetch('/api/database-servers', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Panel-Token': localStorage.getItem('panel_token') || ''
            },
            body: JSON.stringify(payload)
        });
        
        const data = await res.json();
        if (data.success) {
            closeCreateDbServerModal();
            if (window.showAlert) window.showAlert('Сервер успешно добавлен', false);
            setTimeout(() => App.navigate('/databases/servers', 'Серверы баз данных'), 1000);
        } else {
            alert(data.error || 'Ошибка при создании сервера');
        }
    } catch (err) {
        alert('Ошибка сети: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerText = oldText;
    }
};

window.openEditDbServerModal = async function(id) {
    try {
        const res = await fetch('/api/database-servers/get?id=' + id, {
            headers: { 'X-Panel-Token': localStorage.getItem('panel_token') || '' }
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.error || 'Не удалось загрузить данные сервера');
            return;
        }
        
        const server = data.data;
        document.getElementById('editDbServerId').value = server.id;
        document.getElementById('editDbServerName').value = server.name;
        document.getElementById('editDbServerType').value = server.type;
        document.getElementById('editDbServerHost').value = server.host;
        document.getElementById('editDbServerPort').value = server.port;
        document.getElementById('editDbServerUsername').value = server.username;
        document.getElementById('editDbServerPassword').value = server.password;
        document.getElementById('editDbServerRemoteAccess').checked = server.remote_access == 1;
        
        document.getElementById('changePasswordCheckbox').checked = false;
        document.getElementById('editPasswordDiv').style.display = 'none';
        
        document.getElementById('editDbServerTitle').innerHTML = `<i class="fa-solid fa-server text-primary" style="margin-right: 10px;"></i> Сервер баз данных - ${server.type === 'mysql' ? 'MySQL' : 'PostgreSQL'}`;
        
        document.getElementById('editDbServerModal').style.display = 'flex';
    } catch (err) {
        alert('Ошибка сети: ' + err.message);
    }
};

window.closeEditDbServerModal = function() {
    document.getElementById('editDbServerModal').style.display = 'none';
};

window.submitEditDbServer = async function() {
    const form = document.getElementById('editDbServerForm');
    if (!form.reportValidity()) return;
    
    const id = document.getElementById('editDbServerId').value;
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    
    if (!document.getElementById('changePasswordCheckbox').checked) {
        delete payload.password;
    }
    
    const btn = document.getElementById('btnEditDbServer');
    const oldText = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Сохранение...';
    
    try {
        const res = await fetch('/api/database-servers/update', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Panel-Token': localStorage.getItem('panel_token') || ''
            },
            body: JSON.stringify(payload)
        });
        
        const data = await res.json();
        if (data.success) {
            closeEditDbServerModal();
            if (window.showAlert) window.showAlert('Сервер успешно обновлен', false);
            setTimeout(() => App.navigate('/databases/servers', 'Серверы баз данных'), 1000);
        } else {
            alert(data.error || 'Ошибка при сохранении сервера');
        }
    } catch (err) {
        alert('Ошибка сети: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerText = oldText;
    }
};

window.deleteDbServer = async function() {
    const id = document.getElementById('editDbServerId').value;
    if (!confirm('Вы уверены, что хотите удалить этот сервер? Это действие нельзя отменить.')) return;
    
    try {
        const res = await fetch('/api/database-servers/delete', {
            method: 'DELETE',
            headers: { 
                'Content-Type': 'application/json',
                'X-Panel-Token': localStorage.getItem('panel_token') || '' 
            },
            body: JSON.stringify({ id: id })
        });
        
        const data = await res.json();
        if (data.success) {
            closeEditDbServerModal();
            if (window.showAlert) window.showAlert('Сервер удален', false);
            setTimeout(() => App.navigate('/databases/servers', 'Серверы баз данных'), 1000);
        } else {
            alert(data.error || 'Ошибка при удалении сервера');
        }
    } catch (err) {
        alert('Ошибка сети: ' + err.message);
    }
};

window.testDbServerConnection = async function(formId) {
    const form = document.getElementById(formId);
    if (!form.reportValidity()) return;
    
    const formData = new FormData(form);
    let payload = Object.fromEntries(formData.entries());
    
    if (formId === 'editDbServerForm') {
        payload.type = document.getElementById('editDbServerType').value;
    }
    
    const btn = form.querySelector('button[onclick^="testDbServerConnection"]');
    const oldText = btn.innerText;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Проверка...';
    
    try {
        const res = await fetch('/api/database-servers/test', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Panel-Token': localStorage.getItem('panel_token') || ''
            },
            body: JSON.stringify(payload)
        });
        
        const data = await res.json();
        if (data.success) {
            alert('Успешно подключено!\nВерсия сервера: ' + data.version);
        } else {
            alert('Ошибка подключения:\n' + data.error);
        }
    } catch (err) {
        alert('Ошибка запроса: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerText = oldText;
    }
};

window.togglePasswordVisibility = function(inputId) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
};

window.rebootServer = async function() {
    if (!confirm('ВНИМАНИЕ! Вы действительно хотите перезагрузить сервер? Панель управления и все сайты будут недоступны во время перезагрузки (обычно 1-2 минуты).')) return;
    
    try {
        const res = await fetch('/api/system/reboot', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Panel-Token': localStorage.getItem('panel_token') || ''
            }
        });
        
        const data = await res.json();
        if (data.success) {
            document.body.innerHTML = `
                <div style="display:flex; justify-content:center; align-items:center; height:100vh; background:var(--bg-color); color:var(--text-color); flex-direction:column;">
                    <i class="fa-solid fa-power-off" style="font-size: 64px; color: var(--danger); margin-bottom: 24px;"></i>
                    <h1 style="font-size: 28px; margin-bottom: 12px;">Сервер перезагружается...</h1>
                    <p style="color: var(--text-muted); font-size: 16px;">Пожалуйста, подождите несколько минут и обновите страницу (Ctrl + F5).</p>
                </div>
            `;
        } else {
            alert('Ошибка перезагрузки: ' + (data.error || 'Неизвестная ошибка'));
        }
    } catch (e) {
        alert('Ошибка запроса: ' + e.message);
    }
};

// --- Глобальные функции менеджера процессов ---
window.currentProcessSort = { column: 'mem', dir: 'desc' };
window.allProcesses = [];

window.renderProcesses = function() {
    const filter = (document.getElementById('procSearch')?.value || '').toLowerCase();
    
    // Сортировка
    let procs = [...window.allProcesses];
    procs.sort((a, b) => {
        let valA = a[window.currentProcessSort.column];
        let valB = b[window.currentProcessSort.column];
        
        if (typeof valA === 'string') valA = valA.toLowerCase();
        if (typeof valB === 'string') valB = valB.toLowerCase();
        
        // Для mem и cpu и pid
        if (['mem', 'cpu', 'pid'].includes(window.currentProcessSort.column)) {
            valA = parseFloat(valA) || 0;
            valB = parseFloat(valB) || 0;
        }

        if (valA < valB) return window.currentProcessSort.dir === 'asc' ? -1 : 1;
        if (valA > valB) return window.currentProcessSort.dir === 'asc' ? 1 : -1;
        return 0;
    });

    // Фильтрация
    if (filter) {
        procs = procs.filter(p => 
            String(p.pid).includes(filter) || 
            p.user.toLowerCase().includes(filter) || 
            p.command.toLowerCase().includes(filter)
        );
    }

    const tbody = document.getElementById('processesList');
    if (!tbody) return;
    
    if (procs.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 40px 0; color: var(--text-muted);">Ничего не найдено</td></tr>`;
    } else {
        tbody.innerHTML = procs.map(p => `
            <tr data-pid="${p.pid}">
                <td><input type="checkbox" class="proc-checkbox" value="${p.pid}" style="cursor: pointer;"></td>
                <td style="font-family: monospace;">${p.pid}</td>
                <td>${p.user}</td>
                <td>${p.cpu}</td>
                <td>${p.mem}</td>
                <td>${p.time}</td>
                <td style="max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${p.command}">${p.command}</td>
                <td class="actions-cell">
                    <button class="context-trigger-btn" data-dropdown="dropdown-proc-${p.pid}">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <div class="context-dropdown" id="dropdown-proc-${p.pid}">
                        <a href="#" class="dropdown-item text-danger" onclick="window.killProcesses([${p.pid}]); return false;">
                            <i class="fa-solid fa-xmark"></i> Завершить
                        </a>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    window.updateProcStats();
};

window.updateProcStats = function() {
    const checks = document.querySelectorAll('.proc-checkbox:checked');
    const totalSelected = checks.length;
    let selectedMem = 0;
    let selectedCpu = 0;
    
    checks.forEach(c => {
        const p = window.allProcesses.find(x => x.pid == c.value);
        if (p) {
            selectedMem += parseFloat(p.mem) || 0;
            selectedCpu += parseFloat(p.cpu) || 0;
        }
    });

    const killBtn = document.getElementById('killToolbarBtn');
    if (killBtn) {
        if (totalSelected > 0) {
            killBtn.disabled = false;
            killBtn.classList.remove('disabled');
        } else {
            killBtn.disabled = true;
            killBtn.classList.add('disabled');
        }
    }

    const sCount = document.getElementById('procCountDisplay');
    const sSel = document.getElementById('procSelectedDisplay');
    const sCpu = document.getElementById('procCpuDisplay');
    const sMem = document.getElementById('procMemDisplay');
    
    if (sCount) sCount.innerText = 'Всего: ' + window.allProcesses.length;
    if (sSel) sSel.innerText = 'Выделено: ' + totalSelected;
    if (sCpu) sCpu.innerText = 'Процессор %: ' + selectedCpu.toFixed(2);
    if (sMem) sMem.innerText = 'Память (MB): ' + selectedMem.toFixed(2);
};

window.killProcesses = async function(pids) {
    if (!pids || pids.length === 0) return;
    if (!confirm(`Вы уверены, что хотите завершить ${pids.length} процесс(ов)?`)) return;
    
    try {
        const res = await fetch('/api/processes/kill', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Panel-Token': localStorage.getItem('panel_token') || ''
            },
            body: JSON.stringify({ pids: pids })
        });
        const data = await res.json();
        if (data.success) {
            if (window.showAlert) window.showAlert('Процессы успешно завершены', false);
            if (window.app && typeof window.app.loadProcesses === 'function') {
                window.app.loadProcesses();
            }
        } else {
            alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
        }
    } catch (e) {
        alert('Ошибка сети: ' + e.message);
    }
};

// Привязки событий для процессов
Dom.on(document.body, 'keyup', '#procSearch', () => window.renderProcesses());

Dom.on(document.body, 'click', '#processesTable th[data-sort]', (e, th) => {
    const col = th.dataset.sort;
    if (window.currentProcessSort.column === col) {
        window.currentProcessSort.dir = window.currentProcessSort.dir === 'asc' ? 'desc' : 'asc';
    } else {
        window.currentProcessSort.column = col;
        window.currentProcessSort.dir = 'desc'; // По умолчанию по убыванию
    }
    
    // Обновляем иконки сортировки
    document.querySelectorAll('#processesTable th[data-sort] i').forEach(i => i.className = 'fa-solid fa-sort');
    th.querySelector('i').className = window.currentProcessSort.dir === 'asc' ? 'fa-solid fa-sort-up' : 'fa-solid fa-sort-down';
    
    window.renderProcesses();
});

Dom.on(document.body, 'change', '#selectAllProcs', (e, target) => {
    document.querySelectorAll('.proc-checkbox').forEach(cb => {
        cb.checked = target.checked;
        const row = cb.closest('tr');
        if (target.checked) row.classList.add('selected');
        else row.classList.remove('selected');
    });
    window.updateProcStats();
});

Dom.on(document.body, 'change', '.proc-checkbox', (e, target) => {
    const row = target.closest('tr');
    if (target.checked) row.classList.add('selected');
    else row.classList.remove('selected');
    
    const allCount = document.querySelectorAll('.proc-checkbox').length;
    const checkedCount = document.querySelectorAll('.proc-checkbox:checked').length;
    const selectAll = document.getElementById('selectAllProcs');
    if (selectAll) {
        selectAll.checked = (allCount === checkedCount && allCount > 0);
        selectAll.indeterminate = (checkedCount > 0 && checkedCount < allCount);
    }
    window.updateProcStats();
});

Dom.on(document.body, 'click', '#killToolbarBtn', () => {
    const pids = Array.from(document.querySelectorAll('.proc-checkbox:checked')).map(cb => parseInt(cb.value));
    window.killProcesses(pids);
});

// Перехват окончания навигации для загрузки процессов
const originalLoadUrl = AppEngine.prototype.loadUrl;
AppEngine.prototype.loadUrl = async function(url, updateHistory = true, title = '') {
    await originalLoadUrl.call(this, url, updateHistory, title);
    if (url === '/processes') {
        this.loadProcesses();
        // Можно обновлять периодически
        if (this.procTimer) clearInterval(this.procTimer);
        this.procTimer = setInterval(() => {
            if (location.pathname === '/processes') this.loadProcesses();
            else clearInterval(this.procTimer);
        }, 5000);
    }
};