# HLstatsX : Community Edition

![PHP Version](https://img.shields.io/badge/PHP-8.4-777bb4.svg?style=flat-square&logo=php)
[![github-release](https://img.shields.io/github/v/release/lovasatt/hlstatsx-community-edition?style=flat-square)](https://github.com/lovasatt/hlstatsx-community-edition/releases/)
![Game](https://img.shields.io/badge/Games-CS2%20Support-orange.svg?style=flat-square)
![GitHub repo size](https://img.shields.io/github/repo-size/lovasatt/hlstatsx-community-edition?style=flat-square&label=Web%20Size&color=blue)

HLstatsX Community Edition is an open-source project licensed
under GNU General Public License v2 and is a real-time stats
and ranking for Source engine based games. HLstatsX Community
Edition uses a Perl daemon to parse the log streamed from the
game server. The data is stored in a MySQL Database and has
a PHP frontend.

Counter-Strike 2 is supported, via [`source-udp-forwarder`](https://github.com/startersclan/source-udp-forwarder).

## :loudspeaker: Important changes

| Date | Description / Feature | Support Status / Additional Information |
| :--- | :--- | :--- |
| **2026-01-28** | **Unified Weapon System** | **Full-Stack Hitgroup & Loadout Refactor for Source 2 🎯** |
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
5. **Log Streaming:** To ensure the game server streams logs correctly to your daemon, add the following parameters to your CS2 server startup script (launch options):
   ```bash
   +log on +logaddress_add_http "http://your_ip:26999" (to source-udp-forwarder)
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

`web` image (See [./src/web/config.php](./src/web/config.php) for supported environment variables):

```sh
docker run --rm -it -e DB_ADDR=db -e DB_NAME=hlstatsxce -e DB_USER=hlstatsxce -e DB_PASS=hlstatsxce -p 80:80 startersclan/hlstatsx-community-edition:1.11.4-web
```

`daemon` image:

```sh
# Use --help for usage
docker run --rm -it -p 27500:27500/udp startersclan/hlstatsx-community-edition:1.11.4-daemon --db-host=db:3306 --db-name=hlstatsxce --db-username=hlstatsxce --db-password=hlstatsxce #--help
```

### Docker Compose

To deploy using Docker Compose:

```sh
docker-compose -f docker-compose.example.yml up
# `web` is available at http://localhost:8081 or https://web.example.com
# `phpmyadmin` is available at http://localhost:8083 or https://phpmyadmin.example.com

# You may need to add these DNS records in the hosts file
echo '127.0.0.1 web.example.com' | sudo tee -a /etc/hosts
echo '127.0.0.1 phpmyadmin.example.com' | sudo tee -a /etc/hosts
```

- [install.sql](./src/sql/install.sql) is mounted in `mysql` container which automatically installs the DB only on the first time. If you prefer not to mount `install.sql`, you may manually install the DB by logging into PHPMyAdmin and importing the `install.sql` there.
- `traefik` serves HTTPS with a self-signed cert. All HTTP requests are redirected to HTTPS.

### Upgrading (docker)

1. To be safe, stop the `daemon`.
1. Upgrade `web`:
    1. Bump the docker image to the latest tag
    1. Login to the `web` Admin Panel, there should be a notice to upgrade the DB. Click the `HLX:CE Database Updater` button to begin upgrading. The upgrade may take a while.
    1. After a few moments, you should see messages that the DB was successfully upgraded
1. Upgrade `daemon`:
    1. Simply bump the docker image to the latest tag

## Development

To use Docker Compose v2, use `docker compose` instead of `docker-compose`.

```sh
# 1. Start Counter-strike 1.6 server, source-udp-forwarder, HLStatsX:CE stack
docker-compose up --build
# HLStatsX:CE web frontend available at http://localhost:8081/. Admin Panel username: admin, password 123456
# phpmyadmin available at http://localhost:8083. Root username: root, root password: root. Username: hlstatsxce, password: hlstatsxce

# 2. Once setup, login to Admin Panel at http://localhost:8081/?mode=admin. Click HLstatsX:CE Settings > Proxy Settings, change the daemon's proxy key to 'somedaemonsecret'
# This enables gameserver logs forwarded via source-udp-forwarder to be accepted by the daemon.
# Then, restart the daemon.
docker-compose restart daemon

# 3. Finally, add a Counter-Strike 1.6 server. click Games > and unhide 'cstrike' game.
# Then, click Game Settings > Counter-Strike (cstrike) > Add Server.
#   IP: 10.5.0.100
#   Port: 27015
#   Name: My Counter-Strike 1.6 server
#   Rcon Password: password
#   Public Address: example.com:27015
#   Admin Mod: AMX Mod X
# On the next page, click Apply.

# 4. Reload the daemon via Tools > HLstatsX: CE Daemon Control, using Daemon IP: daemon, port: 27500. You should see the daemon reloaded in the logs.
# The stats of the gameserver is now recorded :)

# 5. To verify stats recording works, restart the gameserver. You should see the daemon recording the gameserver logs. All the best :)
docker-compose restart cstrike

# Development - Install vscode extensions
# Once installed, set breakpoints in code, and press F5 to start debugging.
code --install-extension bmewburn.vscode-intelephense-client # PHP intellisense
code --install-extension xdebug.php-debug # PHP remote debugging via xdebug
# If xdebug is not working, iptables INPUT chain may be set to DROP on the docker bridge.
# Execute this to allow php to reach the host machine via the docker0 bridge
sudo iptables -A INPUT -i br+ -j ACCEPT

# CS 1.6 server - Restart server
docker-compose restart cstrike
# CS 1.6 server - Attach to the CS 1.6 server console. Press CTRL+P and then CTRL+Q to detach
docker attach $( docker-compose ps -q cstrike )
# CS 1.6 server - Exec into container
docker exec -it $( docker-compose ps -q cstrike) bash

# daemon - Exec into container
docker exec -it $( docker-compose ps -q daemon ) sh
# web - Exec into container
docker exec -it $( docker-compose ps -q web ) sh
# Run awards
docker exec -it $( docker-compose ps -q awards) sh -c /awards.sh
# Generate heatmaps
docker exec -it $( docker-compose ps -q heatmaps) php /heatmaps/generate.php #--disable-cache=true
# db - Exec into container
docker exec -it $( docker-compose ps -q db ) sh

# Test
./test/test.sh dev 1

# Test production builds locally
./test/test.sh prod 1

# Dump the DB
docker exec $( docker-compose ps -q db ) mysqldump -uroot -proot hlstatsxce | gzip > hlstatsxce.sql.gz

# Restore the DB
zcat hlstatsxce.sql.gz | docker exec -i $( docker-compose ps -q db ) mysql -uroot -proot hlstatsxce

# Stop Counter-strike 1.6 server, source-udp-forwarder, HLStatsX:CE stack
docker-compose down

# Cleanup
docker-compose down
docker volume rm hlstatsx-community-edition_db-volume
```

## Release

```sh
./release.sh "1.2.3"
git add .
git commit -m "Chore: Release 1.2.3"
```

## FAQ

### Q: `Xdebug: [Step Debug] Could not connect to debugging client. Tried: host.docker.internal:9000 (through xdebug.client_host/xdebug.client_port)` appears in PHP logs on `docker-compose up`

A: If you are seeing this in development, the PHP debugger is not running. Press `F5` in `vscode` to start the PHP debugger. If you don't need debugging, set `XDEBUG_MODE=off` in `docker-compose.yml` to disable XDebug.


