<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'SiteManager Panel' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script>
        document.documentElement.setAttribute('data-theme', localStorage.getItem('panel_theme') || 'light');
    </script>
    <style>
        :root {
            --bg-main: #f3f6f9;
            --bg-card: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --accent: #4f46e5;
            --accent-hover: #4338ca;
            --input-focus: #4f46e5;
            --error-bg: rgba(244, 63, 94, 0.1);
            --error-border: rgba(244, 63, 94, 0.2);
            --error-text: #e11d48;
            --body-bg: radial-gradient(circle at top right, #f3f6f9, #e2e8f0);
            --input-bg: #f8fafc;
            --logo-color: #1e293b;
            --footer-bg: rgba(255, 255, 255, 0.3);
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --btn-sec-bg: rgba(0, 0, 0, 0.05);
            --btn-sec-hover: rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] {
            --bg-main: #070a13;
            --bg-card: rgba(17, 24, 39, 0.75);
            --text-main: #f8fafc;
            --text-muted: #64748b;
            --border-color: rgba(255, 255, 255, 0.08);
            --accent: #6366f1;
            --accent-hover: #4f46e5;
            --input-focus: #6366f1;
            --error-bg: rgba(244, 63, 94, 0.1);
            --error-border: rgba(244, 63, 94, 0.2);
            --error-text: #fda4af;
            --body-bg: radial-gradient(circle at top, #111827 0%, #030712 100%);
            --input-bg: rgba(15, 23, 42, 0.5);
            --logo-color: #ffffff;
            --footer-bg: rgba(3, 7, 18, 0.3);
            --card-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            --btn-sec-bg: rgba(255, 255, 255, 0.05);
            --btn-sec-hover: rgba(255, 255, 255, 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--body-bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background-color 0.3s, color 0.3s;
        }

        .header-nav {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-placeholder {
            font-weight: 700;
            font-size: 20px;
            letter-spacing: -0.5px;
            color: var(--logo-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-links {
            display: flex;
            gap: 28px;
            align-items: center;
            list-style: none;
        }

        .nav-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover, .nav-links a.active {
            color: var(--logo-color);
        }

        .nav-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn-secondary {
            padding: 8px 18px;
            background: var(--btn-sec-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background: var(--btn-sec-hover);
        }

        .theme-toggle, .lang-toggle {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: var(--text-muted);
            transition: color 0.2s;
        }

        .theme-toggle:hover, .lang-toggle:hover {
            color: var(--logo-color);
        }

        /* Основной контейнер */
        main {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        /* Подвал */
        footer {
            border-top: 1px solid var(--border-color);
            background-color: var(--footer-bg);
            padding: 40px 0;
            margin-top: auto;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
    </style>
</head>
<body>

<header class="header-nav">
    <div class="logo-placeholder">🌐 WebLayer</div>
    <div class="nav-actions">
        <button class="theme-toggle" id="loginThemeToggleBtn" title="Сменить тему">🌓</button>
    </div>
</header>

<main id="app-content">
    <style>
        .auth-wrapper {
            width: 100%;
            max-width: 460px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* Карточка с инпутами */
        .auth-card {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 40px;
            box-shadow: var(--card-shadow);
        }

        .auth-card h1 {
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 24px;
            letter-spacing: -0.5px;
            color: var(--logo-color);
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .auth-input {
            width: 100%;
            padding: 12px 40px 12px 16px;
            font-size: 14px;
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-main);
            outline: none;
            transition: all 0.2s;
        }

        .auth-input:focus {
            border-color: var(--input-focus);
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.25);
        }

        /* Красная звездочка */
        .required-star {
            position: absolute;
            right: 16px;
            color: #f43f5e;
            font-size: 14px;
            pointer-events: none;
        }

        /* Иконка глаза для пароля */
        .toggle-password {
            position: absolute;
            right: 32px;
            cursor: pointer;
            color: var(--text-muted);
            user-select: none;
            display: flex;
            align-items: center;
        }

        .forgot-link {
            display: inline-block;
            margin-top: 8px;
            font-size: 13px;
            color: var(--accent);
            text-decoration: none;
            transition: color 0.2s;
        }

        .forgot-link:hover {
            color: #818cf8;
            text-decoration: underline;
        }

        /* Вынесенная плоская широкая кнопка из дизайна */
        .auth-submit-btn {
            width: 100%;
            background-color: var(--btn-sec-bg);
            border: 1px solid var(--border-color);
            border-radius: 30px;
            padding: 14px 24px;
            font-size: 15px;
            font-weight: 500;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            transition: all 0.2s;
        }

        /* Кнопка становится активной, если инпуты заполнены */
        .auth-submit-btn.ready {
            color: #ffffff;
            background-color: var(--accent);
            border-color: var(--accent);
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.3);
        }

        .auth-submit-btn.ready:hover {
            background-color: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 24px rgba(99, 102, 241, 0.4);
        }

        .auth-submit-btn:hover:not(.ready) {
            background-color: var(--btn-sec-hover);
        }

        /* Красивый блок ошибок авторизации */
        .error-toast {
            background-color: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 14px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>

    <div class="auth-wrapper">
        <div id="authErrorBlock" class="error-toast">
            ⚠️ <span id="authErrorMessage">Неверный логин или пароль</span>
        </div>

        <div class="auth-card">
            <h1>Авторизация</h1>
            <form id="spaLoginForm">
                <div class="form-group">
                    <label for="loginUser">Login</label>
                    <div class="input-wrapper">
                        <input type="text" id="loginUser" name="username" class="auth-input" placeholder="Admin" required autocomplete="username">
                        <span class="required-star" id="starUser">*</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="loginPass">Пароль</label>
                    <div class="input-wrapper">
                        <input type="password" id="loginPass" name="password" class="auth-input" placeholder="Пароль" required autocomplete="current-password">
                        <span class="toggle-password" id="eyeIcon">👁️</span>
                        <span class="required-star" id="starPass" style="right: 16px;">*</span>
                    </div>
                </div>

                <div class="form-group" id="tfaGroup" style="display: none;">
                    <label for="loginCode" style="color: #3b82f6;"><i class="fa-solid fa-shield-check"></i> Код 2FA</label>
                    <div class="input-wrapper">
                        <input type="text" id="loginCode" name="code" class="auth-input" placeholder="123456" maxlength="6" autocomplete="off" style="font-family: monospace; letter-spacing: 4px; font-size: 16px;">
                    </div>
                </div>
            </form>
        </div>

        <button type="submit" form="spaLoginForm" id="actionLoginBtn" class="auth-submit-btn">
            <span>Войти</span>
            <span>→</span>
        </button>
    </div>
</main>

<footer>
    <div class="footer-container" style="justify-content: center;">
        <p style="font-size: 13px; color: var(--text-muted);">&copy; 2026 WebLayer. All rights reserved.</p>
    </div>
</footer>

<script type="module" src="/assets/js/main.js"></script>
</body>
</html>


<script>
    const userInp = document.getElementById('loginUser');
    const passInp = document.getElementById('loginPass');
    const codeInp = document.getElementById('loginCode');
    const tfaGroup = document.getElementById('tfaGroup');
    const starUser = document.getElementById('starUser');
    const starPass = document.getElementById('starPass');
    const eyeIcon = document.getElementById('eyeIcon');
    const submitBtn = document.getElementById('actionLoginBtn');

    // Прячем звездочки, когда поля заполняются, и подсвечиваем нижнюю кнопку
    function checkInputs() {
        starUser.style.display = userInp.value ? 'none' : 'block';

        // Корректируем положение глаза, если звезда пропала
        if (passInp.value) {
            starPass.style.display = 'none';
            eyeIcon.style.right = '16px';
        } else {
            starPass.style.display = 'block';
            eyeIcon.style.right = '32px';
        }

        if (userInp.value && passInp.value) {
            submitBtn.classList.add('ready');
        } else {
            submitBtn.classList.remove('ready');
        }
    }

    userInp.addEventListener('input', checkInputs);
    passInp.addEventListener('input', checkInputs);

    // Переключение видимости пароля
    eyeIcon.addEventListener('click', () => {
        if (passInp.type === 'password') {
            passInp.type = 'text';
            eyeIcon.innerText = '🙈';
        } else {
            passInp.type = 'password';
            eyeIcon.innerText = '👁️';
        }
    });
    document.getElementById('spaLoginForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const authErrorBlock = document.getElementById('authErrorBlock');
        const authErrorMessage = document.getElementById('authErrorMessage');

        authErrorBlock.style.display = 'none';

        try {

            const response = await fetch('/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    username: userInp.value,
                    password: passInp.value,
                    code: codeInp.value
                })
            });

            const data = await response.json();

            if (data.requires_2fa) {
                tfaGroup.style.display = 'block';
                codeInp.focus();
                return;
            }

            if (!data.success) {
                authErrorMessage.textContent = data.error;
                authErrorBlock.style.display = 'flex';
                return;
            }

            localStorage.setItem('panel_token', data.token);

            location.href = '/';

        } catch (err) {

            authErrorMessage.textContent = 'Ошибка соединения';
            authErrorBlock.style.display = 'flex';

        }
    });

    const loginThemeToggleBtn = document.getElementById('loginThemeToggleBtn');
    if (loginThemeToggleBtn) {
        const updateIcon = (theme) => {
            loginThemeToggleBtn.innerText = theme === 'dark' ? '☀️' : '🌙';
        };
        const initialTheme = document.documentElement.getAttribute('data-theme') || 'light';
        updateIcon(initialTheme);
        
        loginThemeToggleBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('panel_theme', newTheme);
            updateIcon(newTheme);
        });
    }
</script>