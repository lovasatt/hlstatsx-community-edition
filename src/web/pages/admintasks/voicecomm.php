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
    $edlist->columns[] = new EditListColumn('password', 'Password (or Discord Invite URL)', 20, false, 'text', '', 128);
    $edlist->columns[] = new EditListColumn('UDPPort', 'UDP Port (TS)', 6, false, 'text', '', 5);
    $edlist->columns[] = new EditListColumn('queryPort', 'Query Port (TS/Vent)', 6, false, 'text', '', 5);
    $edlist->columns[] = new EditListColumn('descr', 'Notes', 20, false, 'text', '', 255);
    $edlist->columns[] = new EditListColumn('serverType', 'Server Type', 16, true, 'select', '' . '/-- select --;0/Teamspeak;1/Ventrilo;2/Discord');

if (!empty($_POST)) {
    $custom_error = false;

    // Collect row IDs marked for deletion so they will not trigger duplicate errors
    $deleted_ids = [];
    foreach ($_POST as $k => $v) {
        if (strpos($k, 'd_') === 0 && !empty($v)) {
            $deleted_ids[] = (int)substr($k, 2);
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

        $sql = "SELECT serverId FROM hlstats_Servers_VoiceComm
                WHERE (
                    (addr = '$addr_esc' AND UDPPort = $udp AND queryPort = $query)
                    OR (serverType = 2 AND addr = '$addr_esc')
                )";

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

    // --- 1. VALIDATE NEW SERVER SUBMISSION ---
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

            // Discord check: must be a 17-25 numeric Guild ID (prevents pasting invite links into IP/ID)
            if ($stype_int === 2) {
                if (!preg_match('/^[0-9]{17,25}$/', $new_addr)) {
                    message('warning', 'Error: Discord Server ID must be a numeric ID (17-25 digits). Do not paste invite links into IP / Hostname!');
                    $custom_error = true;
                }
                $new_udp = 0;
                $new_query = 0;
            } else {
                $raw_udp   = trim((string)($_POST['new_UDPPort'] ?? ''));
                $raw_query = trim((string)($_POST['new_queryPort'] ?? ''));

                if ($raw_udp !== '' && (!preg_match('/^[0-9]+$/', $raw_udp) || (int)$raw_udp < 1 || (int)$raw_udp > 65535)) {
                    message('warning', 'Error: UDP Port must be between 1 and 65535.');
                    $custom_error = true;
                }
                if ($raw_query !== '' && (!preg_match('/^[0-9]+$/', $raw_query) || (int)$raw_query < 1 || (int)$raw_query > 65535)) {
                    message('warning', 'Error: Query Port must be between 1 and 65535.');
                    $custom_error = true;
                }

                $new_udp   = (int)$raw_udp;
                $new_query = (int)$raw_query;
            }

            // Duplicate Check: Prevent duplicate new server
            if (!$custom_error && $check_duplicate($stype_int, $new_addr, $new_udp, $new_query)) {
                $type_name = ($stype_int === 2) ? 'Discord' : (($stype_int === 1) ? 'Ventrilo' : 'Teamspeak');
                message('warning', "Error: Duplicate server! A {$type_name} server with this address / ID already exists.");
                $custom_error = true;
            }
        }
    }

    // --- 2. PREPARE NEW SERVER DEFAULTS IF VALID ---
    if (!$custom_error && $new_addr !== '' && $new_stype !== '') {
        $stype_int = (int)$new_stype;

        if ($stype_int === 2) {
            $_POST['new_UDPPort']   = 0;
            $_POST['new_queryPort'] = 0;

            // Auto-prepend https:// to Discord invite if omitted
            if (!empty($_POST['new_password'])) {
                $pass_trimmed = trim((string)$_POST['new_password']);
                if (!preg_match('~^https?://~i', $pass_trimmed)) {
                    $_POST['new_password'] = 'https://' . $pass_trimmed;
                }
            }
        } else {
            $_POST['new_UDPPort']   = (int)($_POST['new_UDPPort'] ?? 0);
            $_POST['new_queryPort'] = (int)($_POST['new_queryPort'] ?? 0);
        }

        // Auto-resolve empty server name via Discord API or fallback
        if (empty(trim((string)($_POST['new_name'] ?? '')))) {
            if ($stype_int === 2) {
                $_POST['new_name'] = hlx_fetch_discord_name($new_addr, $_POST['new_password'] ?? '');
            } else {
                $_POST['new_name'] = $new_addr;
            }
        }
    }

    // --- 3. HANDLE EXISTING SERVER UPDATES (Catches duplicates before MariaDB SQL error) ---
    if (!$custom_error) {
        // Collect all existing row IDs from POST (supports both addr_1 and addr[1])
        $existing_ids = [];
        foreach ($_POST as $k => $v) {
            if (preg_match('/^addr_([0-9]+)$/', $k, $m)) {
                $existing_ids[] = (int)$m[1];
            }
        }
        if (isset($_POST['addr']) && is_array($_POST['addr'])) {
            foreach (array_keys($_POST['addr']) as $aid) {
                $existing_ids[] = (int)$aid;
            }
        }
        $existing_ids = array_unique($existing_ids);

        foreach ($existing_ids as $id) {
            if (in_array($id, $deleted_ids, true)) continue; // Skip rows being deleted

            $addr_val  = trim((string)($_POST['addr_' . $id] ?? $_POST['addr'][$id] ?? ''));
            $stype_int = (int)($_POST['serverType_' . $id] ?? $_POST['serverType'][$id] ?? 0);
            $udp       = ($stype_int === 2) ? 0 : (int)($_POST['UDPPort_' . $id] ?? $_POST['UDPPort'][$id] ?? 0);
            $query     = ($stype_int === 2) ? 0 : (int)($_POST['queryPort_' . $id] ?? $_POST['queryPort'][$id] ?? 0);

            // Normalize integer ports in $_POST so EditList saves clean values
            $_POST['UDPPort_' . $id]   = $udp;
            $_POST['queryPort_' . $id] = $query;

            // Validation: Discord ID must be numeric snowflake (17-25 digits)
            if ($stype_int === 2 && !preg_match('/^[0-9]{17,25}$/', $addr_val)) {
                message('warning', "Error on row #{$id}: Discord Server ID must be a numeric ID (17-25 digits). Do not paste invite links into IP / Hostname!");
                $custom_error = true;
                break;
            }

            // Duplicate Check: Catch duplicates in PHP before MariaDB throws a database error
            if ($check_duplicate($stype_int, $addr_val, $udp, $query, $id)) {
                $type_name = ($stype_int === 2) ? 'Discord' : (($stype_int === 1) ? 'Ventrilo' : 'Teamspeak');
                message('warning', "Error on row #{$id}: Duplicate server! A {$type_name} server with address / ID '" . htmlspecialchars($addr_val, ENT_QUOTES, 'UTF-8') . "' already exists.");
                $custom_error = true;
                break;
            }

            // Fallback name resolution if an existing server name was cleared
            $current_name = trim((string)($_POST['name_' . $id] ?? $_POST['name'][$id] ?? ''));
            if (empty($current_name)) {
                $inv = (string)($_POST['password_' . $id] ?? $_POST['password'][$id] ?? '');
                if ($stype_int === 2) {
                    $_POST['name_' . $id] = hlx_fetch_discord_name($addr_val, $inv);
                } else {
                    $_POST['name_' . $id] = $addr_val;
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
<b>Note:</b> When adding a Discord server:<br /><br />
1. In Discord app settings: Open <em>Server Settings &rarr; Activity (Engagement)</em>, turn on <em>Enable Server Widget</em>, and select a welcome/landing channel.<br />
2. In HLX: Paste the numeric Server ID (17-25 digits) into <em>IP / Hostname</em>, copy the invite link to that landing channel into <em>Password</em>, and leave port fields empty (all voice rooms sync automatically).<br /><br />
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
