<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'SiteManager' ?></title>
    <link id="favicon" rel="icon" href="/assets/favicon.png" type="image/png">
    <script>
        document.documentElement.setAttribute('data-theme', localStorage.getItem('panel_theme') || 'light');
    </script>
    <link rel="stylesheet" href="/assets/css/panel.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.js"></script>
</head>
<body>

<?php if (isset($view) && $view === 'login'): ?>

    <?= $content ?>

<?php else: ?>

    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <i class="fa-solid fa-server brand-icon"></i>
                <span><a style="color: inherit; text-decoration: none;" href="/">SiteManager</a></span>
            </div>

            <nav class="sidebar-menu">
                <a href="/" class="menu-item active" data-link>
                    <i class="fa-solid fa-chart-pie"></i> Дашборд
                </a>
                <a href="/sites" class="menu-item" data-link>
                    <i class="fa-solid fa-globe"></i> Сайты
                </a>
                <a href="/databases" class="menu-item" data-link>
                    <i class="fa-solid fa-database"></i> Базы данных
                </a>
                <a href="/cron" class="menu-item" data-link>
                    <i class="fa-solid fa-clock"></i> CRON задачи
                </a>
                <a href="/ssl" class="menu-item" data-link>
                    <i class="fa-solid fa-lock"></i> SSL-сертификаты
                </a>
                <a href="/services" class="menu-item" data-link>
                    <i class="fa-solid fa-cogs"></i> Службы
                </a>
                <a href="/manager" class="menu-item" data-link>
                    <i class="fa-regular fa-folder-open"></i> Менеджер файлов
                </a>
                <div class="menu-divider"></div>
                <a href="#" class="menu-item logout logout-action">
                    <i class="fa-solid fa-right-from-bracket"></i> Выйти
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <div class="header-title" id="page-title"><?= $title ?? 'Дашборд' ?></div>
                <div style="display: flex; align-items: center; gap: 16px;">
    <style>
        /* Toast Notifications Container */
        #toast-container {
            position: fixed;
            bottom: 60px;
            right: 30px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            align-items: flex-end;
            pointer-events: none; /* Let clicks pass through empty space */
        }
        
        .toast-item {
            background-color: var(--bg-card, #ffffff);
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08), 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color, #eaeaea);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            max-width: 400px;
            pointer-events: auto;
            color: var(--text-main, #333);
            font-size: 14px;
            font-weight: 500;
            animation: slideInRight 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            transition: opacity 0.3s, transform 0.3s;
        }

        .toast-item.toast-leaving {
            opacity: 0;
            transform: translateX(30px);
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .toast-icon {
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .toast-icon.success {
            color: #10B981;
            background: rgba(16, 185, 129, 0.1);
        }

        .toast-icon.error {
            color: #EF4444;
            background: rgba(239, 68, 68, 0.1);
        }
        
        .toast-content {
            flex-grow: 1;
            word-break: break-word;
        }

        .toast-close {
            background: none;
            border: none;
            color: var(--text-muted, #999);
            cursor: pointer;
            font-size: 16px;
            padding: 4px;
            margin-left: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }

        .toast-close:hover {
            color: var(--text-main, #333);
        }

        #toast-close-all {
            position: fixed;
            bottom: 15px;
            right: 30px;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.5);
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.2s, opacity 0.2s;
            opacity: 0;
            pointer-events: none;
        }

        #toast-close-all.show {
            opacity: 1;
            pointer-events: auto;
        }

        #toast-close-all:hover {
            background: rgba(0, 0, 0, 0.7);
        }
    </style>
                    <button id="themeToggleBtn" style="background: var(--profile-bg); border: 1px solid var(--border-color); cursor: pointer; padding: 6px 12px; border-radius: 20px; font-size: 13px; color: var(--text-dark); display: flex; align-items: center; gap: 6px; transition: all 0.2s;" title="Переключить тему">
                        🌓 <span>Тема</span>
                    </button>
                    <div class="notifications-wrapper" style="position: relative;">
                        <button id="notificationBtn" class="icon-btn" title="Уведомления">
                            <i class="fa-solid fa-bell"></i>
                            <span class="notification-badge hidden" id="notificationBadge">0</span>
                        </button>
                        <div class="header-dropdown" id="notificationDropdown">
                            <div class="dropdown-header">
                                <span>Уведомления</span>
                                <button id="markAllReadBtn" class="text-btn">Прочитано</button>
                            </div>
                            <div class="dropdown-body" id="notificationList">
                                <!-- Notifications will be loaded here -->
                                <div class="empty-state">Нет новых уведомлений</div>
                            </div>
                        </div>
                    </div>

                    <div class="user-profile-wrapper" style="position: relative;">
                        <div class="user-profile" id="userProfileBtn">
                            <i class="fa-solid fa-circle-user"></i>
                            <span><?= htmlspecialchars(env('PANEL_USER', 'admin')) ?></span>
                            <i class="fa-solid fa-chevron-down" style="font-size: 10px; margin-left: 4px;"></i>
                        </div>
                        <div class="header-dropdown user-dropdown" id="userDropdown">
                            <a href="/settings" class="dropdown-item">
                                <i class="fa-solid fa-gear"></i> Настройки
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item text-danger logout-action">
                                <i class="fa-solid fa-right-from-bracket"></i> Выйти
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Уведомления (Toast Container) -->
            <div id="toast-container"></div>
            <button id="toast-close-all" onclick="closeAllToasts()">Закрыть все</button>

            <!-- Контент страницы -->
            <div id="pjax-container" style="flex: 1; display: flex; flex-direction: column;">
                <?= $content ?>
            </div>
        </main>
    </div>

<?php endif; ?>
<script>
    // 🛑 КЛИЕНТСКИЙ ФАЙРВОЛ: Без токена сюда нельзя!


    const API_URL = window.location.origin + '/api';

    function showAlert(text, isError = true) {
        const container = document.getElementById('toast-container');
        if (!container) return;

        // Создаем новый toast
        const toast = document.createElement('div');
        toast.className = 'toast-item';
        
        const iconHtml = isError 
            ? '<div class="toast-icon error"><i class="fa-solid fa-xmark"></i></div>'
            : '<div class="toast-icon success"><i class="fa-solid fa-check"></i></div>';

        toast.innerHTML = `
            ${iconHtml}
            <div class="toast-content">${text}</div>
            <button class="toast-close"><i class="fa-solid fa-xmark"></i></button>
        `;

        // Кнопка закрытия
        toast.querySelector('.toast-close').addEventListener('click', () => {
            closeToast(toast);
        });

        // Добавляем в контейнер
        container.appendChild(toast);

        // Ограничиваем количество тостов до 3
        const toasts = container.querySelectorAll('.toast-item');
        if (toasts.length > 3) {
            closeToast(toasts[0]); // удаляем самый старый
        }

        // Обновляем видимость кнопки "Закрыть все"
        updateCloseAllBtn();

        // Автозакрытие через 4 секунды
        setTimeout(() => {
            if (toast.parentElement) {
                closeToast(toast);
            }
        }, 4000);
    }

    function closeToast(toast) {
        toast.classList.add('toast-leaving');
        setTimeout(() => {
            if (toast.parentElement) toast.remove();
            updateCloseAllBtn();
        }, 300); // ждем окончания CSS-анимации
    }

    function closeAllToasts() {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toasts = container.querySelectorAll('.toast-item');
        toasts.forEach(t => t.classList.add('toast-leaving'));
        
        setTimeout(() => {
            container.innerHTML = '';
            updateCloseAllBtn();
        }, 300);
    }

    function updateCloseAllBtn() {
        const container = document.getElementById('toast-container');
        const btn = document.getElementById('toast-close-all');
        if (!container || !btn) return;
        
        const count = container.querySelectorAll('.toast-item:not(.toast-leaving)').length;
        if (count > 1) {
            btn.classList.add('show');
        } else {
            btn.classList.remove('show');
        }
    }



    // 🚪 ЛОГАУТ
    document.querySelectorAll('.logout-action').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const token = localStorage.getItem('panel_token');
            try {
                await fetch(`${API_URL}/auth/logout`, {
                    method: 'POST',
                    headers: { 'X-Panel-Token': token }
                });
            } catch (e) {}
            localStorage.removeItem('panel_token');
            window.location.href = '/login';
        });
    });

    // 🌓 ПЕРЕКЛЮЧЕНИЕ ТЕМЫ
    const toggleBtn = document.getElementById('themeToggleBtn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('panel_theme', newTheme);
        });
    }
</script>
<script type="module" src="/assets/js/main.js"></script>
</body>
</html>