# HLstatsX : Community Edition

![PHP Version](https://img.shields.io/badge/PHP-8.4-777bb4.svg?style=flat-square&logo=php)
![Docker Build](https://github.com/lovasatt/hlstatsx-community-edition/actions/workflows/ci.yml/badge.svg)
[![github-release](https://img.shields.io/github/v/release/lovasatt/hlstatsx-community-edition?style=flat-square)](https://github.com/lovasatt/hlstatsx-community-edition/releases/)
![Game](https://img.shields.io/badge/Games-CS2%20Support-orange.svg?style=flat-square)
![GitHub repo size](https://img.shields.io/github/repo-size/lovasatt/hlstatsx-community-edition?style=flat-square&label=Web%20Size&color=blue)

HLstatsX Community Edition is an open-source project licensed
under GNU General Public License v2 and is a real-time stats
and ranking for Source engine based games. HLstatsX Community
Edition uses a Perl daemon to parse the log streamed from the
game server. The data is stored in a MySQL Database and has
a PHP frontend.

Counter-Strike 2 is natively supported: the updated hlstats.pl daemon handles both UDP and HTTP log streaming directly alongside the CS2 SuperLogs plugin (legacy [`source-udp-forwarder`](https://github.com/startersclan/source-udp-forwarder) remains available as an optional profile).

## :loudspeaker: Important changes

| Date | Description / Feature | Support Status / Additional Information |
| :--- | :--- | :--- |
| 2026-09-08 | **Native Discord Integration & Voice Server Modernization** | **feat(discord): Widget API, live rooms & game badges; feat(ts3): ServerQuery & ts3server://; fix(admin): Strict validation, ports 0 & deletion** |
| 2026-09-04 | Database Overhaul, Web Security & CS2 Meta Completion (v1.12.5) | feat(db): Full InnoDB/UTF8MB4, Y2038 & Update 97; feat(sec): Argon2id, CSRF & Leaflet; feat(cs2): Neck hitgroups & daemon hardening |
| 2026-08-13 | Avatar Fixes, Docker MariaDB 11.8 LTS & Lock Prevention | fix(web): SteamID3 avatars, cURL gzip & HTML syntax; perf(db): MariaDB 11.8 & temporary table locks |
| 2026-08-08 | Daemon Stability & In-Game UI Refinement | fix(daemon): Banid lookup, GeoIP safety & typos; feat(plugin): inline K/D ratio display |
| 2026-07-28 | PHP 8.x compatibility, and Dockerfiles | fix(web): PHP 8.x compatibility, GD graphics fixes, security, UI alignment and Dockerfiles |
| 2026-07-22 | Direct Dual-Protocol Log Streaming & SuperLogs v2.4 | Native HTTP & UDP listening on port 27500. Standalone UDP Forwarder made optional via Docker profile. |
| 2026-07-15 | CounterStrikeSharp 1.0.371 & .NET 10 Compatibility Update | Plugin fixes, stability improvements, and responsive UI updates. |
| 2026-05-16 | CS2 Knife fix (T/CT differentiation) | Correctly logs basic knife as `knife_t` for Terrorists and `knife` for CTs; resolves inaccurate kill stats in SuperLogs |
| 2026-04-23 | v1.12.3 - AG2 & Hitgroup Fix | Critical update for CS2 AnimGraph 2 engine changes. Restores precise hitgroup logging and stability. |
| 2026-03-14 | Modern Docker Stack | PHP 8.4, Debian 13, Automated DB init & CI verification. 🚀 |
| 2026-02-15 | Modern In-Game Interface | Integrates HLStatsX with Counter-Strike 2 using rich HTML dashboards for rankings, weapon stats, and player management. The in-game menus can be accessed with !mm (EloRank) and !hlx (HLStatsX). |
| 2026-01-28 | Unified Weapon System | Full-Stack Hitgroup & Loadout Refactor for Source 2 🎯 |
| | Generic Hitgroup | Implemented 'Body' (Generic 0) support across C#, Perl, and SQL |
| | Database Update | Update #94: Automated SQL schema migration for 8th hitgroup |
| 2026-01-12 | Core Refactoring | Modular CSS Platform: Adaptive Modes (Normal/Dark) and Mobile UX 📱 |
| | Visual Design | High-contrast Light & Dark modes with brightness-corrected assets |
| 2026-01-02 | Modernized Build | PHP 8.4 and Counter-Strike 2 Support 🚀 |
| | PHP Version | Full PHP 8.4 compatibility (Zero deprecated warnings) |
| | Security | Silent Migration (Legacy MD5 auto-upgrade to `password_hash`) |
| | CS2 Support | Updated Daemon (Correct CT/T fire/inferno differentiation) |
| | Calculations | EloRank System & SuperLogs plugin integration |
| | Architecture | Modernized `/src` directory structure |
| 07.01.2020 | #45 GeoIP2 Update | Linux script updated, GeoLite2 MaxMind database (GDPR) [Ref](https://blog.maxmind.com/2019/12/18/significant-changes-to-accessing-and-using-geolite2-databases/) |

---
### Standalone Installation Guide

All required files are located within the **`/src`** directory.

### 1. Database Setup
1. Create a MySQL/MariaDB database.
2. Import the initial schema: `./src/sql/install.sql`
3. *Note: For upgrades, the system handles the silent password migration automatically.*

### 2. Web Frontend (PHP 8.4)
1. **Upload:** Transfer `/src/web/` contents to your web server's directory.
2. **Document Root:** Point your webserver's Document Root specifically to the `/web` folder.
3. **Configuration:** Edit database credentials in: `./src/web/config.php`
4. **Linux Permissions:** Ensure the web server user (e.g., `www-data`) has proper ownership:
   ```bash
   # Example for Ubuntu/Debian:
   chown -R www-data:www-data /path/to/src/web
   chmod -R 755 /path/to/src/web
   ```
5. **Log Streaming:** To ensure game servers stream logs correctly to your daemon (port 27500):
    **Counter-Strike 2 (Source 2) Native HTTP:** Add the following parameter to your CS2 server launch options:
     ```bash
     +log on +logaddress_add_http "http://HLSTATSX_SERVER_IP:HLSTATSX_PORT/GAMESERVER_PORT"
     ```
     *Example:*
     ```bash
     +log on +logaddress_add_http "http://192.168.1.1:27500/27015"
     ```
    **Source 1 Games (CS:S, TF2, CS:GO, DoD:S) server.cfg:**
     ```cfg
     log on
     logaddress_add YOUR_HLSTATSX_SERVER_IP:HLSTATSX_SERVER_PORT
     ```
     *Example:*
     ```bash
     log on
     logaddress_add 192.168.1.1:27500
     ```

6. **CS2 Dedicated Server Integration:**
To enable real-time tracking for Counter-Strike 2:<br>
    Deploy Plugins: Copy the pre-compiled plugins from `./src/counterstrikesharp/plugins/` to your server's directory:<br>
    `game/csgo/addons/counterstrikesharp/plugins/`<br>
    Configuration: Update the .json configuration files for plugins with your database credentials.<br>
    Warmup Control: The included Warmup plugin automatically disables logging during warmup periods to prevent erroneous data collection and ensure statistical integrity.<br>
    
Detailed installation instructions below on the wikipedia page
---

## :book: Documentation

- https://github.com/NomisCZ/hlstatsx-community-edition/wiki 🚧 Wiki - work in progress 🚧

## :speech_balloon: Help

- https://forums.alliedmods.net/forumdisplay.php?f=156

---

## Usage (docker)

The modernized way to deploy HLStatsX:CE. Featuring automated setup, this version runs a **Debian 13 (Trixie)** and **PHP 8.4** stack inside the container, ensuring seamless compatibility across **Linux, Windows, and macOS**.

```bash
# 0. Clone the repository (if not done yet) (linux example)
git clone https://github.com/lovasatt/hlstatsx-community-edition.git hlstatsx
cd hlstatsx

# 1. Prepare your environment file
cp .env.example .env

# 2. Setup GeoIP (Optional, but recommended)
# To enable GeoIP functionality, obtain the latest GeoLite2-City.mmdb from MaxMind and copy it into `/src/scripts/GeoLiteCity`. On Linux, ensure the file is readable.

# 3. Configure credentials
# Open .env and adjust the minimum required settings:
# - DB_ROOT_PASS & DB_PASS: Set your secret database passwords.
# - PROXY_KEY: Set your secret key (must match your CS2 / game server config).

# 4. Build and launch the stack
docker compose up -d

# 5. Access the Web Interface
# Open in browser: http://your-server-ip/ (or http://localhost/)
# Default Admin: admin
# Default Password: 123456
```
Ensure no other service is using the same ports (default: web 80, mariadb 3306, daemon 27500).

### Docker Compose

The stack is managed via a single `docker-compose.yml` file. Database initialization and configuration are fully automated.

| Service | Internal Port | External Port | Protocol | Description |
| :--- | :--- | :--- | :--- | :--- |
| **Web** | 80 | **80** | TCP | Web Interface (Admin: `admin` / `123456`) |
| **Forwarder (Optional)**| 26999 | **26999** | TCP/UDP | Legacy UDP proxying (Enable via COMPOSE_PROFILES=forwarder) |
| **Daemon** | 27500 | **27500** | TCP/UDP | Direct log processor (+logaddress_add_http or direct UDP) |
| **Database** | 3306 | **3306** | TCP | MariaDB server |

- **Direct Logging:** Direct daemon logging (port 27500) is natively supported for all games including Counter-Strike 2. The Legacy UDP Forwarder is disabled by default to save resources.
- **Enabling Legacy Forwarder:** If you run legacy UDP setups targeting port 26999, uncomment COMPOSE_PROFILES=forwarder in your .env or run docker compose --profile forwarder up -d.
- **Automated Init:** The `install.sql` is automatically imported into the MariaDB container on the first run. 
- **Dynamic Setup:** The `PROXY_KEY` from your `.env` file is automatically injected into the database and configuration files during startup.
- **Port Conflict:** If port 80 or 3306 etc is already in use on your host, change the mapping in your `.env` (e.g., `WEB_PORT=81`).

Note: The UDP Forwarder is optional and disabled by default.
When adding your game server via the web interface, set the Daemon IP/Hostname to `hlx-daemon` (not `localhost`) so that HLX:CE can properly communicate with the daemon. After making configuration changes, reload or restart the daemon for them to take effect.

### Upgrading (docker)

1. **Update code:** Pull the latest changes from the repository.
   ```bash
   git pull origin master
   ```
2. **Rebuild containers:** Restart the stack and rebuild images to apply changes.
   ```bash
   docker compose up -d --build
   ```
3. **Database Migration:** Login to the **Web Admin Panel.** If a schema update is required, a notice will appear. Click the HLX:CE Database Updater button to finish the upgrade.

## Development

Modern deployment uses the **Docker Compose V2** plugin. Use `docker compose` instead of the legacy `docker-compose`.

### 📋 Useful Development Commands

**Accessing containers (Shell):**
```bash
# Web container shell
docker compose exec web bash

# Daemon container shell
docker compose exec daemon bash

# Database container shell
docker compose exec db bash
```
**Manual script execution (inside the daemon):**
```bash
# Generate daily awards
docker compose exec daemon su hlstats -c "perl /home/hlstats/scripts/hlstats-awards.pl"

# Resolve player countries via GeoIP
docker compose exec daemon su hlstats -c "perl /home/hlstats/scripts/hlstats-resolve.pl"
```
**Database Backup & Restore:**
Credentials are read from the .env file automatically if it exists; if not, default values are used.
```bash
# Create a backup (dump)
docker compose exec -T db mariadb-dump -u DB_USER -p"DB_PASS" DB_NAME > backup.sql

# Restore a backup
docker compose exec -T db mariadb -u DB_USER -p"DB_PASS" DB_NAME < backup.sql
```
**External Database Access (for CS2 Plugins)**
If you use external plugins (like CounterStrikeSharp, EloRank, or MatchZy) that require their own database or need to connect to HLStatsX, follow these steps.

```bash
#Create a new database:
docker compose exec -T db mariadb -u root -p"DB_ROOT_PASS" -e "CREATE DATABASE IF NOT EXISTS other_plugin_db;"

#Grant access to the existing user:
docker compose exec -T db mariadb \
  -u root \
  -p"DB_ROOT_PASS" \
  -e "CREATE USER IF NOT EXISTS 'DB_USER'@'%' IDENTIFIED BY 'DB_PASS';
      GRANT ALL PRIVILEGES ON DB_NAME.* TO 'DB_USER'@'%';
      FLUSH PRIVILEGES;"

#Populate the new database from an SQL file:
docker compose exec -T db mariadb -u root -p"DB_ROOT_PASS" other_plugin_db < your_plugin_data.sql

#Delete (Drop) the database:
docker compose exec -T db mariadb -u root -p"DB_ROOT_PASS" -e "DROP DATABASE IF EXISTS other_plugin_db;"
```
#### Plugin Connection Details
Use these settings in your plugin's configuration file (e.g., `config.json`). 
Do not use variables here; type the actual values you defined in your `.env` file.

| Setting | Value (Local Host) | Value (Remote Server) |
| :--- | :--- | :--- |
| **Database Host** | `127.0.0.1` | `Your_Server_Public_IP` |
| **Database Port** | `3306` (or your `DB_PORT`) | `3306` |
| **Database User** | `hlstatsx` (or your `DB_USER`) | `hlstatsx` |
| **Database Password** | `Your secret DB_PASS` | `Your secret DB_PASS` |
| **Database Name** | `other_plugin_db` | `other_plugin_db` |


**Live Troubleshooting (Logs):**
```bash
# Follow all logs
docker compose logs -f

# Web interface logs
docker compose logs -f web

# Daemon (Perl log parser) logs
docker compose logs -f daemon

# UDP Forwarder logs (only if using legacy profile)
docker compose --profile forwarder logs -f forwarder

# MariaDB container logs
docker compose logs -f db
```
**System Management:**
```bash
# Restart services individually
docker compose restart daemon web db

# Stop and remove containers (keeps volumes/data)
docker compose down

# FULL RESET: Remove everything (containers, networks, and ALL database data)
docker compose down -v --remove-orphans

# Clean up unused Docker resources
docker system prune -a -f
docker volume prune -f
docker network prune -f
```

## Troubleshooting & FAQ

### Q: I see "Waiting for DB..." in the daemon logs for a long time.
A: This is normal during the first launch. The MariaDB container needs time to initialize the database and import `install.sql`. The daemon will automatically start as soon as the database is ready.

### Q: My game server logs are not appearing in the stats.
A: Check the following:
1. **Proxy key:** Ensure the `PROXY_KEY` in your `.env` matches the key in **Web Admin Panel > HLStatsX Settings** and in your `HLStatsX_SuperLogs.json`.
2. **Port connectivity:** Ensure port `27500` (both UDP and TCP) is open in your server host firewall (e.g. UFW or Cloud Security Groups).
3. **CS2 Launch Option / Plugin:** Ensure your CS2 server uses `+logaddress_add_http "http://your_server_ip:27500/27015"` or the `HLStatsX_SuperLogs` plugin configured with port `27500`.
4. **Daemon IP/Hostname:** When adding a game server in the web interface, set **Daemon IP/Hostname** to `hlx-daemon` (not `localhost`) to ensure the daemon reloads properly.

### Q: How do I change the web port from 80 to something else?
A: Set the `WEB_PORT` variable in your `.env` file (e.g., `WEB_PORT=81`) and then restart the stack:
```bash
docker compose up -d
```