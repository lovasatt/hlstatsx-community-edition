<?php
/*
HLstatsX Community Edition - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
http://www.hlxcommunity.com

HLstatsX Community Edition is a continuation of
ELstatsNEO - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Malte Bayer (steam@neo-soft.org)
http://ovrsized.neo-soft.org/

ELstatsNEO is an very improved & enhanced - so called Ultra-Humongus Edition of HLstatsX
HLstatsX - Real-time player and clan rankings and statistics for Half-Life 2
http://www.hlstatsx.com/
Copyright (C) 2005-2007 Tobias Oetzel (Tobi@hlstatsx.com)

HLstatsX is an enhanced version of HLstats made by Simon Garner
HLstats - Real-time player and clan rankings and statistics for Half-Life
http://sourceforge.net/projects/hlstats/
Copyright (C) 2001  Simon Garner

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

For support and installation notes visit http://www.hlxcommunity.com
*/

    if (!defined('IN_HLSTATS')) {
        die('Do not access this file directly.');
    }

    global $db, $auth;

    // PHP 8 Fix: Null coalescing check
    if (($auth->userdata['acclevel'] ?? 0) < 80) {
        die ('Access denied!');
    }

    if (!defined('PAGE_PATH')) define('PAGE_PATH', './pages');
    require_once(PAGE_PATH . '/discordstatus.php');

    // Fetch Discord server name via widget API if name is left blank
    if (!function_exists('hlx_fetch_discord_name')) {
        function hlx_fetch_discord_name($guild_id, $invite = '') {
            $guild_id = trim((string)$guild_id);
            if ($guild_id === '') {
                return 'Discord Server';
            }

            if (class_exists('CDiscordStatus')) {
                $dc = new CDiscordStatus();
                if ($dc->Request($guild_id, $invite) && !empty($dc->m_name)) {
                    return trim($dc->m_name);
                }
            }
            return 'Discord Server';
        }
    }

    $edlist = new EditList('serverId', 'hlstats_Servers_VoiceComm', '', true);
    $edlist->columns[] = new EditListColumn('name', 'Server Name', 25, false, 'text', '', 128);
    $edlist->columns[] = new EditListColumn('addr', 'IP / Hostname (or Discord Guild ID)', 22, true, 'text', '', 128);
    $edlist->columns[] = new EditListColumn('password', 'Password (Discord Invite URL / API Key)', 20, false, 'text', '', 128);
    $edlist->columns[] = new EditListColumn('UDPPort', 'UDP Port (TS)', 6, false, 'text', '', 5);
    $edlist->columns[] = new EditListColumn('queryPort', 'Query Port (TS/Vent)', 6, false, 'text', '', 5);
    $edlist->columns[] = new EditListColumn('descr', 'Notes', 20, false, 'text', '', 255);
    $edlist->columns[] = new EditListColumn('serverType', 'Server Type', 16, true, 'select', '' . '/-- select --;0/TeamSpeak;1/Ventrilo;2/Discord');

if (!empty($_POST)) {
    $custom_error = false;

    // Collect row IDs marked for deletion so they will not trigger duplicate errors
    $deleted_ids = [];
    if (isset($_POST['rows']) && is_array($_POST['rows'])) {
        foreach ($_POST['rows'] as $rid) {
            $rid_int = (int)$rid;
            if (!empty($_POST[$rid_int . '_delete'])) {
                $deleted_ids[] = $rid_int;
            }
        }
    }
    $has_delete = !empty($deleted_ids);

    $new_addr  = trim((string)($_POST['new_addr'] ?? ''));
    $new_stype = trim((string)($_POST['new_serverType'] ?? ''));
    $new_name  = trim((string)($_POST['new_name'] ?? ''));
    $new_pass  = trim((string)($_POST['new_password'] ?? ''));
    $new_descr = trim((string)($_POST['new_descr'] ?? ''));

    // Did the user touch the new server row at the bottom?
    $has_new_input = ($new_addr !== '' || $new_stype !== '' || $new_name !== '' || $new_pass !== '' || $new_descr !== '');

    // Check directly in database if any server already exists
    $has_existing_servers = false;
    $chk = $db->query("SELECT serverId FROM hlstats_Servers_VoiceComm LIMIT 1");
    if ($chk && $db->num_rows($chk) > 0) {
        $has_existing_servers = true;
        $db->free_result($chk);
    }

    // --- HELPER CLOSURE: DUPLICATE SERVER CHECK (Matches MariaDB KEY `address`) ---
    $check_duplicate = function($stype, $addr, $udp, $query, $exclude_id = 0) use ($db, $deleted_ids) {
        $stype = (int)$stype;
        $addr_esc = $db->escape(trim((string)$addr));
        $udp = (int)$udp;
        $query = (int)$query;
        $exclude_id = (int)$exclude_id;

        if ($addr_esc === '' || $stype < 0) {
            return false;
        }

        $sql = "SELECT serverId FROM hlstats_Servers_VoiceComm WHERE addr = '$addr_esc' AND UDPPort = $udp AND queryPort = $query";

        if ($exclude_id > 0) {
            $sql .= " AND serverId != $exclude_id";
        }

        $res = $db->query($sql);
        if ($res) {
            while ($row = $db->fetch_array($res)) {
                $found_id = (int)$row['serverId'];
                if (!in_array($found_id, $deleted_ids, true)) {
                    $db->free_result($res);
                    return true;
                }
            }
            $db->free_result($res);
        }
        return false;
    };

    // --- 1. VALIDATE AND PREPARE NEW SERVER SUBMISSION ---
    if ($has_new_input || (!$has_existing_servers && !$has_delete)) {
        if ($new_addr === '') {
            message('warning', 'Error: IP / Hostname (or Discord Guild ID) is required.');
            $custom_error = true;
        }

        if ($new_stype === '') {
            message('warning', 'Error: Please select a Server Type.');
            $custom_error = true;
        } else {
            $stype_int = (int)$new_stype;

            if ($stype_int === 2) {
                // Discord: 17-25 numeric Guild ID
                if (!preg_match('/^[0-9]{17,25}$/', $new_addr)) {
                    message('warning', 'Error: Discord Server ID must be a numeric ID (17-25 digits). Do not paste invite links into IP / Hostname!');
                    $custom_error = true;
                }
                $new_udp   = 0;
                $new_query = 0;
                $_POST['new_UDPPort']   = '0';
                $_POST['new_queryPort'] = '0';

                // Normalize Discord invite URL
                if (!empty($_POST['new_password'])) {
                    $pass_trimmed = trim((string)$_POST['new_password']);
                    if (!preg_match('~^https?://~i', $pass_trimmed)) {
                        $_POST['new_password'] = 'https://' . $pass_trimmed;
                    }
                }
            } else {
                // Clean TeamSpeak / Ventrilo address: strip protocol prefixes (ts3server://, http://, ://)
                $new_addr = preg_replace('~^([a-z0-9_]+://|://)~i', '', $new_addr);
                $new_addr = rtrim($new_addr, '/');

                // Split hostname and port if provided as host:port (e.g. voice.com:9987)
                if (strpos($new_addr, ':') !== false && !filter_var($new_addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $parts = explode(':', $new_addr, 2);
                    $new_addr = $parts[0];
                    if (empty($_POST['new_UDPPort']) && is_numeric($parts[1])) {
                        $_POST['new_UDPPort'] = (string)(int)$parts[1];
                    }
                }
                $_POST['new_addr'] = $new_addr;

                $raw_udp   = trim((string)($_POST['new_UDPPort'] ?? ''));
                $raw_query = trim((string)($_POST['new_queryPort'] ?? ''));

                // Set default ports before duplicate check
                if ($raw_udp === '')   $raw_udp   = ($stype_int === 0) ? '9987' : '3784';
                if ($raw_query === '') $raw_query = ($stype_int === 0) ? '10011' : '3784';

                if (!preg_match('/^[0-9]+$/', $raw_udp) || (int)$raw_udp < 1 || (int)$raw_udp > 65535) {
                    message('warning', 'Error: UDP Port must be between 1 and 65535.');
                    $custom_error = true;
                }
                if (!preg_match('/^[0-9]+$/', $raw_query) || (int)$raw_query < 1 || (int)$raw_query > 65535) {
                    message('warning', 'Error: Query Port must be between 1 and 65535.');
                    $custom_error = true;
                }

                $new_udp   = (int)$raw_udp;
                $new_query = (int)$raw_query;
                $_POST['new_UDPPort']   = (string)$new_udp;
                $_POST['new_queryPort'] = (string)$new_query;
            }

            // Duplicate check with final ports and cleaned address
            if (!$custom_error && $check_duplicate($stype_int, $new_addr, $new_udp, $new_query)) {
                $type_name = ($stype_int === 2) ? 'Discord' : (($stype_int === 1) ? 'Ventrilo' : 'Teamspeak');
                message('warning', "Error: Duplicate server! A {$type_name} server with address/ID '{$new_addr}' and ports ({$new_udp}/{$new_query}) already exists in the database.");
                $custom_error = true;
            }

            // Auto-resolve server name if left empty
            if (!$custom_error && empty(trim((string)($_POST['new_name'] ?? '')))) {
                if ($stype_int === 2) {
                    $_POST['new_name'] = hlx_fetch_discord_name($new_addr, $_POST['new_password'] ?? '');
                } else {
                    $_POST['new_name'] = $new_addr;
                }
            }
        }
    }

    // --- 3. HANDLE EXISTING SERVER UPDATES (Catches duplicates before MariaDB SQL error) ---
    if (!$custom_error && isset($_POST['rows']) && is_array($_POST['rows'])) {
        foreach ($_POST['rows'] as $rid) {
            $id = (int)$rid;
            if (in_array($id, $deleted_ids, true)) continue; // Skip rows being deleted

            $addr_val  = trim((string)($_POST[$id . '_addr'] ?? ''));
            $stype_int = (int)($_POST[$id . '_serverType'] ?? 0);

            if ($stype_int === 2) {
                $_POST[$id . '_UDPPort']   = '0';
                $_POST[$id . '_queryPort'] = '0';
                $udp   = 0;
                $query = 0;

                if (!preg_match('/^[0-9]{17,25}$/', $addr_val)) {
                    message('warning', "Error on row #{$id}: Discord Server ID must be a numeric ID (17-25 digits).");
                    $custom_error = true;
                    break;
                }

                // Normalize Discord invite URL
                if (!empty($_POST[$id . '_password'])) {
                    $pass_trimmed = trim((string)$_POST[$id . '_password']);
                    if (!preg_match('~^https?://~i', $pass_trimmed)) {
                        $_POST[$id . '_password'] = 'https://' . $pass_trimmed;
                    }
                }
            } else {
                $raw_udp   = trim((string)($_POST[$id . '_UDPPort'] ?? ''));
                $raw_query = trim((string)($_POST[$id . '_queryPort'] ?? ''));

                // Normalize default ports if left empty by user on existing row
                if ($raw_udp === '')   $raw_udp   = ($stype_int === 0) ? '9987' : '3784';
                if ($raw_query === '') $raw_query = ($stype_int === 0) ? '10011' : '3784';

                $udp   = (int)$raw_udp;
                $query = (int)$raw_query;
                $_POST[$id . '_UDPPort']   = (string)$udp;
                $_POST[$id . '_queryPort'] = (string)$query;
            }

            if ($check_duplicate($stype_int, $addr_val, $udp, $query, $id)) {
                $type_name = ($stype_int === 2) ? 'Discord' : (($stype_int === 1) ? 'Ventrilo' : 'Teamspeak');
                message('warning', "Error on row #{$id}: Duplicate server! A {$type_name} server with address / ID '" . htmlspecialchars($addr_val, ENT_QUOTES, 'UTF-8') . "' already exists.");
                $custom_error = true;
                break;
            }

            $current_name = trim((string)($_POST[$id . '_name'] ?? ''));
            if (empty($current_name)) {
                $inv = (string)($_POST[$id . '_password'] ?? '');
                if ($stype_int === 2) {
                    $_POST[$id . '_name'] = hlx_fetch_discord_name($addr_val, $inv);
                } else {
                    $_POST[$id . '_name'] = $addr_val;
                }
            }
        }
    }

    // Execute update only if validation passed
    if (!$custom_error) {
        if ($edlist->update()) {
            message('success', 'Operation successful.');
        } else {
            message('warning', $edlist->error());
        }
    }
}

echo '
<div style="margin-bottom: 18px;">
    <b>Voice Server Setup Guides (Click on a service to expand):</b><br /><br />

    <details style="margin-bottom: 8px; cursor: pointer;">
        <summary style="font-weight: bold; color: inherit;">Discord Server Widget</summary>
        <div style="padding: 8px 0 4px 15px; font-size: 11px; line-height: 1.6; cursor: default;">
            1. <strong>In Discord:</strong> Open <em>Server Settings &rarr; Widget</em> (under Activity / Engagement). Enable <strong>Enable Server Widget</strong> and select a default invite channel.<br />
            2. <strong>In HLX:</strong> Paste the 17&ndash;25 digit numeric <strong>Server ID</strong> into <em>IP / Hostname</em>.<br />
            3. <strong>Server Name (Optional):</strong> You can leave <em>Server Name</em> blank; HLstatsX will automatically query and save your Discord community\'s real name via the widget API.<br />
            4. <strong>Invite Link (Optional):</strong> If a landing channel was selected in Discord, the invite link is automatically retrieved. You can paste a custom/vanity invite link into <em>Password</em> to override it, or leave both empty to keep the server closed/private (status and channels only, without a join link).<br />
            5. Leave <em>UDP Port</em> and <em>Query Port</em> empty (or set to 0). Note: Discord widgets only display voice channels that are publicly visible to @everyone.
        </div>
    </details>

    <details style="margin-bottom: 8px; cursor: pointer;">
        <summary style="font-weight: bold; color: inherit;">TeamSpeak 2 (Legacy)</summary>
        <div style="padding: 8px 0 4px 15px; font-size: 11px; line-height: 1.6; cursor: default;">
            &bull; <strong>HLX Fields:</strong> Enter the server IP/hostname. Default UDP Port is <code>8767</code> (Voice) and Query Port is <code>51234</code> (TCP).<br />
            &bull; <strong>Authentication:</strong> Leave <em>Password</em> empty. TS2 ServerQuery operates anonymously by default; no extra permissions or accounts are required.<br />
            &bull; <strong>Firewall:</strong> Ensure UDP <code>8767</code> and TCP <code>51234</code> are open and reachable from your web server.
        </div>
    </details>

    <details style="margin-bottom: 8px; cursor: pointer;">
        <summary style="font-weight: bold; color: inherit;">TeamSpeak 3 (Telnet ServerQuery)</summary>
        <div style="padding: 8px 0 4px 15px; font-size: 11px; line-height: 1.6; cursor: default;">
            &bull; <strong>HLX Fields:</strong> Enter the server IP/hostname. Default UDP Port is <code>9987</code> (Voice) and Query Port is <code>10011</code> (TCP).<br />
            &bull; <strong>Password Field:</strong> Leave empty for open servers, or enter only the client voice connect password if your virtual server requires a password for players to join.<br />
            &bull; <strong>Flood Protection:</strong> Add your web server\'s IP to <code>query_ip_whitelist.txt</code> (or <code>query_ip_allowlist.txt</code>) in the TS3 root folder to prevent rate-limit bans.<br />
            &bull; <strong>One-time Guest Permission Setup:</strong> The viewer operates anonymously without storing admin credentials in the database. Connect via Telnet (e.g. <code>telnet 127.0.0.1 10011</code> or PuTTY) and grant the following 6 permissions to the <em>Guest Server Query</em> group (<code>sgid=1</code>):<br /><br />

            <code>login serveradmin &lt;password&gt;</code><br />
            <code>use sid=1 (or use port=9987)</code><br />
            <code>servergroupaddperm sgid=1 permsid=b_virtualserver_select permvalue=1 permnegated=0 permskip=0</code><br />
            <em>&rarr; Purpose: Allows selecting the virtual server by port or SID (required for `use port=9987`).</em><br /><br />

            <code>servergroupaddperm sgid=1 permsid=b_virtualserver_info_view permvalue=1 permnegated=0 permskip=0</code><br />
            <em>&rarr; Purpose: Retrieves general server properties: real server name, platform, version, and max slots (`serverinfo`).</em><br /><br />

            <code>servergroupaddperm sgid=1 permsid=b_virtualserver_connectioninfo_view permvalue=1 permnegated=0 permskip=0</code><br />
            <em>&rarr; Purpose: Retrieves connection metrics and real uptime. In TS 3.13+, missing this permission causes `serverinfo` to fail with `failed_permid=25`.</em><br /><br />

            <code>servergroupaddperm sgid=1 permsid=b_virtualserver_channel_list permvalue=1 permnegated=0 permskip=0</code><br />
            <em>&rarr; Purpose: Retrieves the channel tree, room names, and topics (`channellist -topic`).</em><br /><br />

            <code>servergroupaddperm sgid=1 permsid=b_virtualserver_client_list permvalue=1 permnegated=0 permskip=0</code><br />
            <em>&rarr; Purpose: Retrieves connected players, nicknames, channel commander, mute, and deaf flags (`clientlist`).</em><br /><br />

            <code>servergroupaddperm sgid=1 permsid=b_client_skip_channelgroup_permissions permvalue=1 permnegated=0 permskip=0</code><br />
            <em>&rarr; Purpose: Master bypass. When a query client binds to port 9987, it is placed into the default channel. This permission prevents default Channel Group restrictions from blocking server-level query commands.</em><br /><br />

            <code>quit</code><br />
            <em>(Alternatively via YaTQA GUI: Permissions &rarr; Server Query Groups &rarr; Guest Server Query &rarr; enable the 6 permissions above).</em><br />
            &bull; <strong>Firewall:</strong> Ensure UDP <code>9987</code> (voice) and TCP <code>10011</code> (query) are allowed through the firewall.
        </div>
    </details>

    <details style="margin-bottom: 8px; cursor: pointer;">
        <summary style="font-weight: bold; color: inherit;">TeamSpeak 6 (HTTP REST WebQuery)</summary>
        <div style="padding: 8px 0 4px 15px; font-size: 11px; line-height: 1.6; cursor: default;">
            &bull; <strong>Server Startup:</strong> Start your TS6 instance with HTTP query enabled: <code>--query-http-enable --query-http-port 10080</code>.<br />
            &bull; <strong>HLX Fields:</strong> Enter the server IP. Set UDP Port to <code>9987</code> (Voice), Query Port strictly to <strong><code>10080</code></strong> (HTTP REST WebQuery), and paste your <strong>WebQuery API key</strong> directly into the <em>Password</em> field.<br />
            &bull; <strong>Dual Password (Optional):</strong> If your server requires a client connect password for players AND you use an API key, enter them in the Password field as: <code>connect_password|your_api_key</code>.<br />
            &bull; <strong>API Key Generation:</strong> Connect via Telnet/ServerQuery and generate an API key for the viewer:<br />
            <code>login serveradmin &lt;password&gt;</code><br />
            <code>use sid=1</code><br />
            <code>apikeyadd scope=read</code> &rarr; <em>(Returns: apikey=&lt;your_32_char_key&gt;)</em><br />
            <code>quit</code><br /><br />
            &bull; <strong>Permissions:</strong> No Telnet Guest permissions are needed because the API key authenticates directly via the <code>x-api-key</code> HTTP header.<br />
            &bull; <strong>Firewall &amp; Whitelist:</strong> Ensure UDP <code>9987</code> and TCP <code>10080</code> are open in your firewall. Keep your web server\'s IP in the allowlist if flood protection is enabled.
        </div>
    </details>

    <details style="margin-bottom: 12px; cursor: pointer;">
        <summary style="font-weight: bold; color: inherit;">Ventrilo 3.x</summary>
        <div style="padding: 8px 0 4px 15px; font-size: 11px; line-height: 1.6; cursor: default;">
            &bull; <strong>HLX Fields:</strong> Enter the server IP/hostname. Default UDP Port and Query Port are both <code>3784</code>.<br />
            &bull; <strong>Password:</strong> Leave empty, or enter the server password if required for clients to connect.<br />
            &bull; <strong>Server Configuration:</strong> Ensure status queries are enabled in your <code>ventrilo_srv.ini</code> file (specifically set <code>IntStatus=1</code>), and both UDP and TCP port <code>3784</code> are open through the firewall.
        </div>
    </details>
</div>
';

    $result = $db->query("
        SELECT
            serverId,
            name,
            addr,
            password,
            UDPPort,
            queryPort,
            descr,
            serverType
        FROM
            hlstats_Servers_VoiceComm
        ORDER BY
            serverType,
            name
    ");

    $edlist->draw($result);
    $db->free_result($result);
?>
<table width="75%" border="0" cellspacing="0" cellpadding="0" style="margin:15px auto;">
<tr>
    <td align="center"><input type="submit" value="  Apply  " class="submit" /></td>
</tr>
</table>
