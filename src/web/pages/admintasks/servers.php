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

    global $db, $auth, $gamecode;

    // PHP 8 Fix: Null coalescing check
    if (($auth->userdata["acclevel"] ?? 0) < 80) {
        die ("Access denied!");
    }

    function delete_server($server)
    {
        global $db;
        $server_esc = $db->escape($server);
        $db->query("DELETE FROM `hlstats_Servers_Config` WHERE `serverId` = '$server_esc'");
        $db->query("DELETE FROM `hlstats_server_load` WHERE `server_id`  = '$server_esc'");
    }

    // Prepare variables for EditList
    $gamecode_safe = $gamecode ?? '';
    $gamecode_esc = $db->escape($gamecode_safe);
    $realgame = getRealGame($gamecode_safe);
    $realgame_esc = $db->escape($realgame);

    $edlist = new EditList("serverId", "hlstats_Servers", "server", true, true, "serversettings", 'delete_server');
    $edlist->columns[] = new EditListColumn("address", "IP Address", 15, true, "ipaddress", "", 15);
    $edlist->columns[] = new EditListColumn("port", "Port", 5, true, "text", "27015", 5);
    $edlist->columns[] = new EditListColumn("name", "Server Name", 35, true, "text", "", 255);
    $edlist->columns[] = new EditListColumn("rcon_password", "Rcon Password", 10, false, "password", "", 128);
    $edlist->columns[] = new EditListColumn("publicaddress", "Public Address", 20, false, "text", "", 128);
    $edlist->columns[] = new EditListColumn("game", "Game", 20, true, "select", "hlstats_Games.name/code/realgame='$realgame_esc'");
    $edlist->columns[] = new EditListColumn("sortorder", "Sort Order", 2, true, "text", "", 255);

    if (!empty($_POST))
    {
        $validation_error = '';

        // 1. Sanitize and validate numeric fields across submitted server rows
        if (isset($_POST['rows']) && is_array($_POST['rows'])) {
            foreach ($_POST['rows'] as $s_id) {
                $s_id = (int)$s_id;

                // Skip deleted servers
                if (!empty($_POST[$s_id . '_delete'])) {
                    continue;
                }

                $port_key      = $s_id . '_port';
                $sortorder_key = $s_id . '_sortorder';

                $port_val      = trim((string)($_POST[$port_key] ?? ''));
                $sortorder_val = trim((string)($_POST[$sortorder_key] ?? ''));

                // Validate Port
                if ($port_val === '' || !ctype_digit($port_val) || (int)$port_val < 1 || (int)$port_val > 65535) {
                    $validation_error = "Port must be an integer between 1 and 65535.";
                    break;
                }

                // Validate Sort Order
                if ($sortorder_val === '') {
                    $_POST[$sortorder_key] = '0';
                } elseif (!is_numeric($sortorder_val)) {
                    $validation_error = "Sort order must be a valid number.";
                    break;
                }
            }
        }

        // 2. Validate duplicate IP + Port entries across existing servers
        if (empty($validation_error) && isset($_POST['rows']) && is_array($_POST['rows'])) {
            $seen_pairs = array();

            foreach ($_POST['rows'] as $s_id) {
                $s_id = (int)$s_id;

                if (!empty($_POST[$s_id . '_delete'])) {
                    continue;
                }

                $addr = trim((string)($_POST[$s_id . '_address'] ?? ''));
                $port = (int)($_POST[$s_id . '_port'] ?? 0);

                if ($addr !== '' && $port > 0) {
                    $pair_key = $addr . ':' . $port;

                    if (in_array($pair_key, $seen_pairs, true)) {
                        $validation_error = "Duplicate server address detected in the form: {$pair_key}.";
                        break;
                    }
                    $seen_pairs[] = $pair_key;

                    $addr_esc = $db->escape($addr);
                    $check = $db->query("
                        SELECT `name`
                        FROM `hlstats_Servers`
                        WHERE `address` = '$addr_esc'
                          AND `port` = $port
                          AND `serverId` != $s_id
                        LIMIT 1
                    ");

                    if ($db->num_rows($check) > 0) {
                        $existing = $db->fetch_array($check);
                        $validation_error = "The address {$pair_key} is already assigned to another server: " . htmlspecialchars($existing['name']) . ".";
                        break;
                    }
                }
            }
        }

        if (!empty($validation_error)) {
            message("warning", $validation_error);
        } else {
            if ($edlist->update()) {
                message("success", "Operation successful.");
            } else {
                message("warning", $edlist->error());
            }
        }
    }

?>
<br /><br />

<?php

    $result = $db->query("
        SELECT
            serverId,
            address,
            port,
            name,
            sortorder,
            publicaddress,
            game,
            rcon_password
        FROM
            hlstats_Servers
        WHERE
            game='$gamecode_esc'
        ORDER BY
            address ASC,
            port ASC
    ");

    $edlist->draw($result, false);

?>

<table width="75%" border="0" cellspacing="0" cellpadding="0">
<tr>
    <td align="center"><input type="submit" value="  Apply  " class="submit"></td>
</tr>
</table>