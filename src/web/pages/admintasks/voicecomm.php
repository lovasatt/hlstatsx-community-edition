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

    // Check if any deletion checkbox was checked
    $has_delete = false;
    foreach ($_POST as $k => $v) {
        if ((strpos($k, 'd_') === 0 || $k === 'delete' || $k === 'dead') && !empty($v)) {
            $has_delete = true;
            break;
        }
    }

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

    // --- 1. VALIDATE NEW SERVER SUBMISSION ---
    // Only validate the new row if the user actually typed into it, OR if the database is completely empty:
    if ($has_new_input || (!$has_existing_servers && !$has_delete)) {
        if ($new_addr === '') {
            message('warning', 'Error: IP / Hostname (or Discord Guild ID) is required.');
            $custom_error = true;
        }

        if ($new_stype === '') {
            message('warning', 'Error: Please select a Server Type.');
            $custom_error = true;
        }
    }

    // --- 2. PREPARE NEW SERVER DEFAULTS IF VALID ---
    if (!$custom_error && $new_addr !== '' && $new_stype !== '') {
        $_POST['new_UDPPort'] = (int)($_POST['new_UDPPort'] ?? 0);
        $_POST['new_queryPort'] = (int)($_POST['new_queryPort'] ?? 0);

        if (empty(trim((string)($_POST['new_name'] ?? '')))) {
            $stype = (int)$new_stype;
            if ($stype === 2) {
                $_POST['new_name'] = hlx_fetch_discord_name($new_addr, $_POST['new_password'] ?? '');
            } else {
                $_POST['new_name'] = $new_addr;
            }
        }
    }

    // --- 3. HANDLE EXISTING SERVER UPDATES (arrays) ---
    if (isset($_POST['addr']) && is_array($_POST['addr'])) {
        foreach ($_POST['addr'] as $id => $addr_val) {
            $_POST['UDPPort'][$id] = (int)($_POST['UDPPort'][$id] ?? 0);
            $_POST['queryPort'][$id] = (int)($_POST['queryPort'][$id] ?? 0);

            if (empty(trim((string)($_POST['name'][$id] ?? '')))) {
                $stype = (int)($_POST['serverType'][$id] ?? -1);
                if ($stype === 2) {
                    $inv = $_POST['password'][$id] ?? '';
                    $_POST['name'][$id] = hlx_fetch_discord_name($addr_val, $inv);
                } else {
                    $_POST['name'][$id] = trim((string)$addr_val);
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
2. In HLX: Paste the numeric Server ID (17-21 digits) into <em>IP / Hostname</em>, copy the invite link to that landing channel into <em>Password</em>, and leave port fields empty (all voice rooms sync automatically).<br /><br />
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
