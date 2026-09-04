# HLstatsX : Community Edition

![PHP Version](https://img.shields.io/badge/PHP-8.4-777bb4.svg?style=flat-square&logo=php)
![Docker Build](https://github.com/lovasatt/hlstatsx-community-edition/actions/workflows/ci.yml/badge.svg)
[![github-release](https://img.shields.io/github/v/release/lovasatt/hlstatsx-community-edition?style=flat-square)](https://github.com/lovasatt/hlstatsx-community-edition/releases/)
![Game](https://img.shields.io/badge/Games-CS2%20Support-orange.svg?style=flat-square)
![GitHub repo size](https://img.shields.io/github/repo-size/lovasatt/hlstatsx-community-edition?style=flat-square&label=Web%20Size&color=blue)

HLstatsX Community Edition is an open-source project licensed under GNU General Public License v2 providing real-time statistics and ranking for Source, Source 2, and GoldSrc engine-based games. HLstatsX Community Edition uses a high-performance Perl daemon to parse logs streamed from game servers. Data is stored in a MySQL/MariaDB database and presented through a modern PHP web frontend.

Counter-Strike 2 is natively supported: the updated `hlstats.pl` daemon directly handles both UDP and HTTP log streaming (`logaddress_add_http`) alongside the CS2 CounterStrikeSharp SuperLogs plugin (legacy [`source-udp-forwarder`](https://github.com/startersclan/source-udp-forwarder) remains available as an optional profile).

## 🚀 Key Features

### 🌐 Hybrid Log Ingestion (UDP + HTTP)
- **Unified Multi-Protocol Listener**: Handles legacy `srcds` UDP logs and CS2’s native HTTP-based log stream (`logaddress_add_http`) simultaneously on the same daemon port.
- **Asynchronous Event Handling**: Powered by non-blocking `IO::Select` socket multiplexing for high-throughput packet processing.
- **Multi-Server Concurrency**: Real-time event streaming across multiple servers without dropped packets.
- **Automatic Log Normalization**: Native parser for CS2 SuperLogs event format and 9-zone anatomical hitgroups (including `neck`).

### 📡 Dual RCON Engine & Multi-Key Latency Tracking
- **Hybrid RCON Architecture**: Native UDP challenge-response RCON for GoldSrc (CS 1.6, HL1, TFC, DoD) and TCP Source RCON for Source 1 & CS2.
- **Multi-Key Ping Reconciliation**: Seamlessly maps player latency in CS2 (where SteamIDs are omitted from `status` output) using an intelligent fallback chain (`UniqueID -> UserID -> Name -> Address`).
- **Resilient Spectator Watchdog**: Eliminates 250s idle-disconnect drops while spectating via dynamic watchdog timestamp resets and Source 2 engine inspection.
- **Real-Time Latency Database**: Directly records latency events to `hlstats_Events_Latency` for both online players and local LAN zero-latency testing (`0 ms`).

### 🧠 True CS2 Zero-Slot (Slot 0) & Anti-Ghosting Support
- Native support for CS2's 0-indexed player slots (`UserID: 0`), preventing false idle-disconnects or missing stats for the first joined player.
- Built-in CS2 weapon translation layer (`inferno -> firebomb` for CTs, `usp_silencer`, `m4a1_silencer`).
- Accurate map name detection via CS2 `loaded spawngroup` and Workshop map lumplog parsing.
- Instant ghost cleanup on client console quit (`(CS2 QUIT)` guard).

### 🗺️ Modernized Interactive Mapping & Responsive UI
- **Zero-Dependency Leaflet Engine**: Replaced deprecated Google Maps API with local Leaflet 1.9.4 featuring 4 live tile layers (Hybrid, Normal, Satellite, Physical) and 35 customizable map regions.
- **Full Responsive Dark Mode**: Optimized with `modern-responsive.css` for mobile/desktop fluid scaling, non-destructive dark tile inversion (preserving marker and flag colors), and dark popup bubble styling.
- **Fluid Asset Scaling**: Prevents table overflows and horizontal layout shifts for server load graphs and signature banners (`max-width: 750px`).

### 🔒 Modern Web Frontend (PHP 8.4 Ready)
- Fully refactored for **PHP 8.2, 8.3, and 8.4** (zero deprecated notices, strict typing, null coalescing).
- **Metadata Lock Fix**: Scoped temporary table operations (`DROP TEMPORARY TABLE`) preventing MariaDB 10.11+ lock contention.
- **Full Steam Avatar Support**: Automatic SteamID3 / 64-bit conversion and GZIP cURL decompression.
- **Silent Password Migration**: Transparently upgrades legacy MD5 / SHA passwords to secure `password_hash()` (Argon2id / Bcrypt) upon user login.
- **Offline Local GeoIP2 Architecture**: Reads `GeoLite2-City.mmdb` binary directly from `/src/scripts/GeoLiteCity` without external API dependencies or license conflicts.

### 🔌 Multi-Engine Plugin Compatibility
- **Source 1**: Fully supports legacy `hlstatsx.smx` SourceMod plugin.
- **GoldSrc**: Fully supports `hlstatsx.amxx` (AMX Mod X).
- **CS2 / Source 2**: Fully compatible with CounterStrikeSharp & MetaMod plugins (emulating `hlx_sm_*` commands).

## 🎮 29 Supported Games & Mods

Run all your game servers on a single HLstatsX installation:
* **Counter-Strike Series**: Counter-Strike 2 (CS2), CS:GO, CS:Source, CS 1.6, CSPromod
* **Team Fortress**: Team Fortress 2 (TF2), Team Fortress Classic (TFC), Fortress Forever (FF)
* **Left 4 Dead**: Left 4 Dead, Left 4 Dead 2
* **Day of Defeat**: Day of Defeat: Source (DoD:S), Day of Defeat (DoD)
* **Half-Life**: Half-Life 2 Deathmatch (HL2:MP), Half-Life 2 CTF, Half-Life 1 (Valve)
* **Community Mods**: Insurgency, Age of Chivalry (AoC), Zombie Panic! Source (ZPS), The Hidden: Source, Fistful of Frags (FoF), GoldenEye: Source (GES), Battle Grounds 2 (BG2), NeoTokyo (NTS), Pirates, Vikings & Knights II (PVKII), Dystopia, Nuclear Dawn, Dino D-Day, Stargate: TLS, Natural Selection.
* **Source 2 Engine Extensibility**: Dynamic engine abstraction layer supporting future Source 2 community modifications (`s2_*`).

## 🐳 Quick Start with Docker (Recommended)
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

### Standalone Installation Guide

All required files are located within the **`/src`** directory.

### 1. Database Setup
1. Create a MySQL/MariaDB database.
2. Import the initial schema: `./src/sql/install.sql`
3. *Note: For existing installations, navigate to `/updater/97.php` to perform the database upgrade.*

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

## :book: Documentation

- https://github.com/NomisCZ/hlstatsx-community-edition/wiki 🚧 Wiki - work in progress 🚧