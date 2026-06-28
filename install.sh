#!/bin/bash

# SiteManager Installation Script
# Target OS: Ubuntu 22.04 / 24.04
# Run as root

# Перезапуск через bash, если запустили через sh
if [ -z "$BASH_VERSION" ]; then
    echo "Скрипт требует bash. Перезапускаем..."
    exec bash "$0" "$@"
fi

if [ "$(id -u)" != "0" ]; then
  echo "Пожалуйста, запустите скрипт от имени root (sudo bash ./install.sh)"
  exit 1
fi

# Защита от двойного запуска
exec 9>"/var/lock/sitemanager.install.lock"
if ! flock -n 9; then
    echo "Ошибка: Другая копия install.sh уже запущена."
    exit 1
fi

# Настройка логирования
LOG_FILE="install_$(date +%Y_%m_%d-%H_%M_%S).log"
exec > >(tee -a ${LOG_FILE}) 2>&1
echo "Лог установки сохраняется в файл: ${LOG_FILE}"

echo "========================================"
echo "    Установка SiteManager Panel        "
echo "========================================"

# Проверки системы
echo "=> Проверка системных требований..."

# 1. Архитектура
ARCH=$(uname -m)
if [ "$ARCH" = "i686" ]; then
    echo "Ошибка: 32-битная архитектура не поддерживается."
    exit 1
fi

# 2. ОС
if [ -f /etc/os-release ]; then
    . /etc/os-release
    if [[ "$ID" != "ubuntu" && "$ID" != "debian" ]]; then
        echo "Ошибка: Установка поддерживается только на Ubuntu или Debian."
        exit 1
    fi
else
    echo "Ошибка: Не удалось определить ОС."
    exit 1
fi

# 3. RAM
MEM_TOTAL=$(free -m | awk 'NR==2{print $2}')
if [ "$MEM_TOTAL" -lt 1024 ]; then
    echo "Ошибка: Недостаточно оперативной памяти (нужно минимум 1024 МБ, доступно $MEM_TOTAL МБ)."
    exit 1
fi

# 4. Disk space
DISK_FREE=$(df -P -m / | tail -1 | awk '{print $4}')
if [ "$DISK_FREE" -lt 5120 ]; then
    echo "Ошибка: Недостаточно места на корневом разделе (нужно минимум 5 ГБ, доступно ${DISK_FREE} МБ)."
    exit 1
fi


# Настройки по умолчанию
INSTALL_DIR="/opt/sitemanager"
PANEL_PORT="2026"
DB_NAME="panel_db"
DB_USER="panel_user"

# 1. Запрос пароля от панели
if [ -f /root/.sitemanager_install.conf ]; then
    echo "=> Найден файл конфигурации от предыдущего запуска. Загружаем пароли..."
    source /root/.sitemanager_install.conf
else
    read -t 10 -p "Придумайте логин для панели (по умолчанию: admin) [автопропуск через 10 сек]: " PANEL_USER
    echo
    PANEL_USER=${PANEL_USER:-admin}
    
    read -t 10 -s -p "Придумайте пароль для входа в панель (Enter для автогенерации) [автопропуск через 10 сек]: " PANEL_PASS
    echo
    if [ -z "$PANEL_PASS" ]; then
        PANEL_PASS=$(tr -dc A-Za-z0-9 </dev/urandom | head -c 12)
        echo "Пароль не введен. Сгенерирован случайный пароль для панели: $PANEL_PASS"
    fi

    # Генерация сложных случайных паролей для БД
    DB_PASS=$(tr -dc A-Za-z0-9 </dev/urandom | head -c 16)
    MYSQL_ROOT_PASS=$(tr -dc A-Za-z0-9 </dev/urandom | head -c 16)
    
    # Сохраняем для повторных запусков
    echo "PANEL_USER=\"$PANEL_USER\"" > /root/.sitemanager_install.conf
    echo "PANEL_PASS=\"$PANEL_PASS\"" >> /root/.sitemanager_install.conf
    echo "DB_PASS=\"$DB_PASS\"" >> /root/.sitemanager_install.conf
    echo "MYSQL_ROOT_PASS=\"$MYSQL_ROOT_PASS\"" >> /root/.sitemanager_install.conf
fi

echo "Сгенерирован пароль базы данных панели: $DB_PASS"
echo "Сгенерирован root-пароль MySQL: $MYSQL_ROOT_PASS"

# 2. Обновление системы и добавление репозиториев
echo "=> Подготовка системы и обновление пакетов..."
apt-get update
apt-get install -y software-properties-common curl wget git zip unzip unrar debconf-utils

# Получаем ID и VERSION_ID из /etc/os-release
if [ -f /etc/os-release ]; then
    . /etc/os-release
fi
VER_MAJOR=$(echo "$VERSION_ID" | cut -d. -f1)

if [[ "$ID" == "ubuntu" && "$VER_MAJOR" -ge 26 ]]; then
    echo "=> Ubuntu 26.04+ обнаружена. PPA ondrej/php пропускается, используем системные пакеты."
    PHP_VER_PKG="php"
else
    echo "=> Добавление PPA ondrej/php..."
    add-apt-repository ppa:ondrej/php -y || true
    apt-get update
    PHP_VER_PKG="php8.3"
fi

apt-get install -y mariadb-server

# Настройка MariaDB перед установкой phpMyAdmin
if mysql -uroot -e "SELECT 1;" &> /dev/null; then
    mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASS}';" 2>/dev/null || true
    mysql -uroot -p"${MYSQL_ROOT_PASS}" -e "CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '${MYSQL_ROOT_PASS}';" 2>/dev/null || true
    mysql -uroot -p"${MYSQL_ROOT_PASS}" -e "ALTER USER 'root'@'127.0.0.1' IDENTIFIED BY '${MYSQL_ROOT_PASS}';" 2>/dev/null || true
    mysql -uroot -p"${MYSQL_ROOT_PASS}" -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;" 2>/dev/null || true
    mysql -uroot -p"${MYSQL_ROOT_PASS}" -e "FLUSH PRIVILEGES;"
fi

echo "phpmyadmin phpmyadmin/dbconfig-install boolean true" | debconf-set-selections
echo "phpmyadmin phpmyadmin/app-password-confirm password $MYSQL_ROOT_PASS" | debconf-set-selections
echo "phpmyadmin phpmyadmin/mysql/admin-pass password $MYSQL_ROOT_PASS" | debconf-set-selections
echo "phpmyadmin phpmyadmin/mysql/app-pass password $MYSQL_ROOT_PASS" | debconf-set-selections
echo "phpmyadmin phpmyadmin/reconfigure-webserver multiselect none" | debconf-set-selections

apt-get install -y nginx redis-server certbot \
    ${PHP_VER_PKG}-fpm ${PHP_VER_PKG}-cli ${PHP_VER_PKG}-mysql ${PHP_VER_PKG}-redis ${PHP_VER_PKG}-mbstring \
    ${PHP_VER_PKG}-xml ${PHP_VER_PKG}-curl ${PHP_VER_PKG}-zip ${PHP_VER_PKG}-gd ${PHP_VER_PKG}-intl phpmyadmin

# Определяем фактическую версию установленного PHP (например, 8.3, 8.4)
PHP_BIN=$(command -v php)
INSTALLED_PHP_VER=$($PHP_BIN -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
echo "=> Фактически установлена версия PHP: $INSTALLED_PHP_VER"

# Установка Composer
if ! command -v composer &> /dev/null; then
    echo "=> Установка Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# 4. Настройка MariaDB
echo "=> Настройка базы данных..."

    # Настройка root теперь выполняется до установки phpMyAdmin

mysql -uroot -p"${MYSQL_ROOT_PASS}" -e "DROP DATABASE IF EXISTS test;"
mysql -uroot -p"${MYSQL_ROOT_PASS}" -e "FLUSH PRIVILEGES;"

mysql -uroot -p"${MYSQL_ROOT_PASS}" -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -uroot -p"${MYSQL_ROOT_PASS}" -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -uroot -p"${MYSQL_ROOT_PASS}" -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -uroot -p"${MYSQL_ROOT_PASS}" -e "FLUSH PRIVILEGES;"

# 5. Установка файлов панели
echo "=> Загрузка файлов панели..."
mkdir -p /var/www/workspaces/www

# ССЫЛКА НА ИСХОДНИКИ (измените на свой репозиторий или ZIP-архив)
# Мы используем переданный GitHub PAT токен для доступа к приватному репозиторию
REPO_URL="https://github.com/daranger/WebLayer.git"
ZIP_URL=""

if [ -n "$REPO_URL" ]; then
    if [ -d "${INSTALL_DIR}/.git" ]; then
        echo "=> Обновление существующего репозитория Git..."
        cd ${INSTALL_DIR}
        git pull
    else
        echo "=> Клонирование из Git: $REPO_URL"
        rm -rf ${INSTALL_DIR}
        git clone $REPO_URL ${INSTALL_DIR}
    fi
elif [ -n "$ZIP_URL" ]; then
    echo "=> Скачивание ZIP-архива: $ZIP_URL"
    mkdir -p ${INSTALL_DIR}
    wget -O /tmp/panel.zip $ZIP_URL
    unzip -q /tmp/panel.zip -d ${INSTALL_DIR}/
    rm /tmp/panel.zip
else
    echo "=> Источник не указан. Пытаемся скопировать локальные файлы из текущей папки..."
    mkdir -p ${INSTALL_DIR}
    cp -r * ${INSTALL_DIR}/ 2>/dev/null || true
fi

if [ ! -f "${INSTALL_DIR}/composer.json" ]; then
    echo "Ошибка: Исходные файлы панели не найдены в ${INSTALL_DIR}."
    echo "Пожалуйста, укажите REPO_URL или ZIP_URL в install.sh"
    exit 1
fi

cp -r ${INSTALL_DIR}/.env.example ${INSTALL_DIR}/.env

cd ${INSTALL_DIR}

# 6. Инициализация базы данных
if [ -f "database/init.sql" ]; then
    echo "=> Импорт базовой структуры базы данных..."
    mysql -u${DB_USER} -p${DB_PASS} ${DB_NAME} < database/init.sql
    # Добавляем локальный сервер БД
    mysql -u${DB_USER} -p${DB_PASS} ${DB_NAME} -e "INSERT IGNORE INTO database_servers (id, type, name, host, port, username, password, remote_access) VALUES (1, 'mysql', 'Local MySQL', '127.0.0.1', 3306, 'root', '${MYSQL_ROOT_PASS}', 0);"
fi

# 7. Настройка .env и генерация хэша пароля
echo "=> Настройка конфигурации .env..."
# Используем флаг -4 для принудительного получения IPv4 адреса
SERVER_IP=$(curl -4 -s ifconfig.me || echo "127.0.0.1")
B_HASH=$(php -r "echo password_hash('${PANEL_PASS}', PASSWORD_BCRYPT);")

sed -i "s/REPLACE_DB_PASS/${DB_PASS}/g" .env
sed -i "s|REPLACE_PANEL_HASH|${B_HASH}|g" .env
sed -i "s/SERVER_IP=127.0.0.1/SERVER_IP=${SERVER_IP}/g" .env
sed -i "s/PANEL_USER=admin/PANEL_USER=${PANEL_USER}/g" .env
echo "" >> .env
echo "MYSQL_ROOT_PASS=\"${MYSQL_ROOT_PASS}\"" >> .env

PMA_HASH=$(tr -dc a-z0-9 </dev/urandom | head -c 8)
echo "PANEL_PMA_PATH=\"/phpmyadmin_${PMA_HASH}\"" >> .env

# 8. Composer install
echo "=> Установка PHP-зависимостей панели..."
composer install --no-dev --optimize-autoloader

# 9. Настройка прав
mkdir -p ${INSTALL_DIR}/storage
chown -R www-data:www-data ${INSTALL_DIR}
chmod -R 755 ${INSTALL_DIR}/storage

# Разрешаем root делать git pull, несмотря на то что папка принадлежит www-data
git config --global --add safe.directory ${INSTALL_DIR}

# Разрешаем www-data выполнять системные команды без пароля (для работы панели)
cat > /etc/sudoers.d/sitemanager <<EOF
www-data ALL=(ALL) NOPASSWD: /usr/bin/php ${INSTALL_DIR}/bin/root_helper.php, /usr/bin/certbot, /usr/sbin/useradd, /usr/sbin/userdel
EOF
chmod 0440 /etc/sudoers.d/sitemanager

# 10. Симлинк для phpMyAdmin (Секретный URL)
rm -f ${INSTALL_DIR}/public/phpmyadmin*
ln -sfn /usr/share/phpmyadmin ${INSTALL_DIR}/public/phpmyadmin_${PMA_HASH}

# 11. Настройка Nginx для панели
echo "=> Настройка Nginx для SiteManager (Порт: ${PANEL_PORT})..."
cat > /etc/nginx/sites-available/sitemanager.conf <<EOF
server {
    listen ${PANEL_PORT};
    server_name _;
    root ${INSTALL_DIR}/public;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${INSTALLED_PHP_VER}-fpm.sock;
    }
}
EOF

ln -sfn /etc/nginx/sites-available/sitemanager.conf /etc/nginx/sites-enabled/sitemanager.conf
systemctl restart nginx

# 12. Настройка файрвола (UFW) если он активен
echo "=> Настройка брандмауэра (открытие портов 80, 443 и ${PANEL_PORT})..."
if command -v ufw &> /dev/null; then
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw allow ${PANEL_PORT}/tcp
    # Перезагружаем правила без перебивания существующих
    ufw reload &> /dev/null || true
fi

# 12. Создание системного демона для фоновых очередей (Воркер панели)
echo "=> Установка демона очереди (Worker)..."
cat > /etc/systemd/system/sitemanager-worker.service <<EOF
[Unit]
Description=SiteManager Queue Worker
After=network.target redis-server.service mariadb.service

[Service]
Type=simple
User=root
Group=root
Restart=always
RestartSec=5
WorkingDirectory=${INSTALL_DIR}
ExecStart=/usr/bin/php${INSTALLED_PHP_VER} ${INSTALL_DIR}/bin/queue_worker.php

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable sitemanager-worker
systemctl start sitemanager-worker

echo "=> Установка демона мониторинга (Monitor)..."
cat > /etc/systemd/system/sitemanager-monitor.service <<EOF
[Unit]
Description=SiteManager System Monitor
After=network.target redis-server.service mariadb.service

[Service]
Type=simple
User=root
Group=root
Restart=always
RestartSec=5
WorkingDirectory=${INSTALL_DIR}
ExecStart=/usr/bin/php${INSTALLED_PHP_VER} ${INSTALL_DIR}/bin/monitor_worker.php

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable sitemanager-monitor
systemctl start sitemanager-monitor

echo "================================================================"
echo " Установка успешно завершена! "
echo " Панель доступна по адресу: http://${SERVER_IP}:${PANEL_PORT}"
echo " Логин для входа: ${PANEL_USER}"
echo " Пароль для входа: ${PANEL_PASS}"
echo " Web-интерфейс БД: http://${SERVER_IP}:${PANEL_PORT}/phpmyadmin_${PMA_HASH}"
echo " Пароль root от БД: ${MYSQL_ROOT_PASS}"
echo "================================================================"
