# WebLayer — Lightweight Server Management Panel (CE)

A self-hosted web panel for managing Nginx sites, databases, SSL certificates, cron jobs, and system services on Ubuntu/Debian servers. Built on a custom PHP micro-framework with no heavy dependencies.

![Dashboard](https://raw.githubusercontent.com/daranger/WebLayer/main/public/assets/sc/1.png)

![Sites](https://raw.githubusercontent.com/daranger/WebLayer/main/public/assets/sc/2.png)

![File Manager](https://raw.githubusercontent.com/daranger/WebLayer/main/public/assets/sc/3.png)

![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue) ![License](https://img.shields.io/badge/license-MIT-green) ![OS](https://img.shields.io/badge/OS-Ubuntu%2022.04%20%2F%2024.04-orange) ![Tested](https://img.shields.io/badge/Tested%20on-Ubuntu%2026.04-success)


---

## Features

- **Site management** — create, edit, enable/disable Nginx virtual hosts
- **SSL** — automatic Let's Encrypt certificate issuance and renewal via Certbot
- **Database management** — create MySQL/PostgreSQL databases and users; connect remote DB servers
- **File Manager** — browse, edit, upload, download, compress/extract files on the server
- **Cron jobs** — create and manage scheduled tasks with a visual interface
- **Services** — start/stop/restart system services (Nginx, PHP-FPM, MariaDB, Redis)
- **Process monitor** — view running processes, kill by PID
- **System dashboard** — CPU, RAM, disk usage in real time
- **Auth** — bcrypt passwords, Redis-backed sessions with TTL, optional TOTP 2FA, IP binding, brute-force rate limiting
- **phpMyAdmin** — bundled at a randomized secret URL

---

## Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.1+ |
| Web server | Nginx + PHP-FPM |
| Database | MariaDB (MySQL) / PostgreSQL |
| Cache / Sessions | Redis (Predis) |
| Background workers | PHP CLI + systemd |
| SSL | Certbot / Let's Encrypt |
| Firewall | UFW |
| 2FA | TOTP (robthree/twofactorauth) |
| Framework | Custom micro-framework (DI container, Router, Queue) |

---

## Requirements

- Ubuntu 22.04 / 24.04 or Debian 11/12
- 1 GB RAM minimum
- 5 GB free disk space
- Root access

---

## Installation

```bash
wget https://raw.githubusercontent.com/daranger/WebLayer/main/install.sh
sh install.sh
```

The script will:
1. Install Nginx, MariaDB, Redis, PHP 8.3, Certbot, phpMyAdmin
2. Create a dedicated database and user for the panel
3. Generate secure random passwords for everything
4. Set up two systemd services (queue worker + system monitor)
5. Configure Nginx on port `2026`
6. Open ports 80, 443, 2026 in UFW

At the end of installation you will see:

```
Panel URL:      http://YOUR_IP:2026
Login:          admin
Password:       <generated>
phpMyAdmin:     http://YOUR_IP:2026/phpmyadmin_xxxxxxxx
MySQL root pw:  <generated>
```

---

## Configuration

All configuration lives in `.env` (created from `.env.example` during install):

```env
PANEL_ENV=production

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=panel_db
DB_USER=panel_user
DB_PASS=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

PANEL_USER=admin
PANEL_PASSWORD_HASH=        # BCrypt hash

PANEL_LOGIN_PATH=login      # Customize the login URL path
PANEL_BIND_IP_SESSION=false # Bind token to client IP

FIREWALL_UFW_ENABLED=true
CLOUDFLARE_ENABLED=false
CF_API_TOKEN=

SERVER_IP=
```

To change the password, generate a new BCrypt hash and update `PANEL_PASSWORD_HASH`:

```bash
php -r "echo password_hash('your_new_password', PASSWORD_BCRYPT);"
```

---

## Project Structure

```
├── app/
│   ├── Controllers/     # HTTP controllers
│   ├── Core/            # Framework core (Application, Router, Container, Session...)
│   ├── Services/        # Business logic (SiteService, FileManager, NginxManager...)
│   ├── Repositories/    # DB access layer
│   ├── Jobs/            # Background jobs (CreateSite, DeleteSite, RebuildConfig...)
│   └── Exceptions/      # Error handler
├── bin/
│   ├── queue_worker.php   # Job queue processor (runs as systemd service)
│   ├── monitor_worker.php # System monitor (runs as systemd service)
│   └── root_helper.php    # Privileged operations proxy (called via sudo)
├── config/              # Service bindings
├── database/            # SQL schema + SQLite placeholder
├── public/              # Web root (index.php, assets)
├── resources/views/     # PHP templates
├── routes/              # web.php + api.php
└── storage/             # Logs, Nginx configs, tmp files
```

---

## Security notes

- The panel is intended for **single-user/admin use only** — there is no multi-user role system
- It is strongly recommended to restrict port `2026` to your IP via UFW or a firewall after setup
- Enable 2FA in Settings after first login
- The `root_helper.php` script is the only entry point for privileged system operations; it validates all input before executing shell commands

---

## License

MIT — see [LICENSE](LICENSE)
