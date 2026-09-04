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

    global $db, $auth, $selGame, $g_options;

    // PHP 8 Fix: Null coalescing check
    if (($auth->userdata["acclevel"] ?? 0) < 80) {
        die ("Access denied!");
    }

    // Helper function to sanitize text input
    function clean_data($data)
    {
        return trim(mystripslashes((string)$data));
    }

    $server_ip = '';
    $server_port = '27015';
    $server_name = '';
    $server_rcon = '';
    $server_public_address = '';
    $selected_mod = 'PLEASESELECT';

    if (!empty($_POST)) {
        $s_address = isset($_POST['server_address']) ? clean_data($_POST['server_address']) : '';
        $s_port    = isset($_POST['server_port']) ? clean_data($_POST['server_port']) : '';
        $s_name    = isset($_POST['server_name']) ? clean_data($_POST['server_name']) : '';
        $p_address = isset($_POST['public_address']) ? clean_data($_POST['public_address']) : '';
        $s_rcon    = isset($_POST['server_rcon']) ? mystripslashes($_POST['server_rcon']) : '';
        $g_mod     = isset($_POST['game_mod']) ? mystripslashes($_POST['game_mod']) : '';

        // Retain entered values to repopulate the form in case of an error
        $server_ip = $s_address;
        $server_port = $s_port;
        $server_name = $s_name;
        $server_rcon = $s_rcon;
        $server_public_address = $p_address;
        $selected_mod = $g_mod;

        // 1. Server-side validation
        $error_msg = '';
        if ($s_address === '' || !filter_var($s_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $error_msg = "Server address must be a valid IPv4 address.";
        } elseif ($s_port === '' || !ctype_digit($s_port) || (int)$s_port < 1 || (int)$s_port > 65535) {
            $error_msg = "Server port must be an integer between 1 and 65535.";
        } elseif ($s_name === '') {
            $error_msg = "Server name cannot be empty.";
        } elseif ($g_mod === 'PLEASESELECT') {
            $error_msg = "You must make a selection for Admin Mod.";
        }

        if (!empty($error_msg)) {
            message("warning", $error_msg);
        } else {
            // 2. Check for duplicate address + port in database
            $s_port_int = (int)$s_port;
            $db->query("SELECT `name` FROM `hlstats_Servers` WHERE `address` = '" . $db->escape($s_address) . "' AND `port` = $s_port_int LIMIT 1");

            if ($row = $db->fetch_array()) {
                message("warning", "A server with address " . htmlspecialchars($s_address) . ":" . $s_port_int . " already exists: " . htmlspecialchars($row['name']) . ".");
            } else {
                // 3. Verify game configuration
                $db->query("SELECT `realgame` FROM `hlstats_Games` WHERE `code` = '" . $db->escape($selGame) . "' LIMIT 1");
                $row = $db->fetch_row();

                if (!$row) {
                    message("warning", "Selected game configuration is invalid.");
                } else {
                    $game = $row[0];

                    // Auto-generate Public Address if left blank
                    if ($p_address === '') {
                        $p_address = $s_address . ':' . $s_port_int;
                    }

                    $script_path = (isset($_SERVER['SSL']) || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == "https")) ? 'https://' : 'http://';
                    $script_path .= $_SERVER['HTTP_HOST'];
                    $script_path .= str_replace("\\", "/", dirname($_SERVER["PHP_SELF"]));

                    // Insert server record
                    $db->query(sprintf(
                        "INSERT INTO `hlstats_Servers` (`address`, `port`, `name`, `game`, `publicaddress`, `rcon_password`) VALUES ('%s', %d, '%s', '%s', '%s', '%s')",
                        $db->escape($s_address),
                        $s_port_int,
                        $db->escape($s_name),
                        $db->escape($selGame),
                        $db->escape($p_address),
                        $db->escape($s_rcon)
                    ));

                    $insert_id = (int)$db->insert_id();

                    // Insert mod defaults
                    $db->query("INSERT INTO `hlstats_Servers_Config` (`serverId`, `parameter`, `value`)
                                SELECT $insert_id, `parameter`, `value`
                                FROM `hlstats_Mods_Defaults` WHERE `code` = '" . $db->escape($g_mod) . "'
                                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);");

                    // Insert Mod setting
                    $db->query("INSERT INTO `hlstats_Servers_Config` (`serverId`, `parameter`, `value`) VALUES
                                ($insert_id, 'Mod', '" . $db->escape($g_mod) . "')
                                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);");

                    // Insert game defaults
                    $db->query("INSERT INTO `hlstats_Servers_Config` (`serverId`, `parameter`, `value`)
                                SELECT $insert_id, `parameter`, `value`
                                FROM `hlstats_Games_Defaults` WHERE `code` = '" . $db->escape($game) . "'
                                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);");

                    // Update HLStatsURL
                    $db->query("UPDATE `hlstats_Servers_Config`
                                SET `value` = '" . $db->escape($script_path) . "'
                                WHERE `serverId` = $insert_id AND `parameter` = 'HLStatsURL'");

                    $_POST = array();

                    echo "<script type=\"text/javascript\"> window.location.href=\"" . $g_options['scripturl'] . "?mode=admin&game=" . urlencode($selGame) . "&task=serversettings&key=$insert_id#startsettings\"; </script>";
                    exit;
                }
            }
        }
    }
?>
Enter the address of a server that you want to accept data from.<br /><br />
The "Public Address" should be the address you want shown to users. If left blank, it will be generated from the IP Address and Port. If you are using any kind of log relaying utility (i.e. hlstats.pl will not be receiving data directly from the game servers), you will want to set the IP Address and Port to the address of the log relay program, and set the Public Address to the real address of the game server. You will need a separate log relay for each game server. You can specify a hostname (or anything at all) in the Public Address.<p>

<script type="text/javascript">
<script type="text/javascript">
function checkMod() {
    var form = document.forms['newserverform'];
    if (!form) return true;

    var ipVal = form.server_address.value.trim();
    var ipPattern = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;

    if (!ipPattern.test(ipVal)) {
        alert('Server address must be a valid IPv4 address.');
        form.server_address.focus();
        return false;
    }
    if (form.game_mod.value === 'PLEASESELECT' || form.game_mod.value === '') {
        alert('You must make a selection for Admin Mod');
        form.game_mod.focus();
        return false;
    }
    form.submit();
}
</script>

<table class="data-table" style="width:75%;margin:10px auto;">
    <tr class="head data-table-head">
        <th class="fSmall" align="left" style="width:45%;">Details</th>
        <th class="fSmall" align="left" style="width:55%;">Value</th>
    </tr>
    <tr class="bg1" style="vertical-align:middle;">
        <td class="fSmall" align="left" style="width:45%;">Server IP Address:</td>
        <td style="width:55%;"><input type="text" name="server_address" maxlength="15" size="15" value="<?php echo htmlspecialchars($server_ip);?>" class="textbox" /></td>
    </tr>
    <tr class="bg2" style="vertical-align:middle;">
        <td class="fSmall" align="left" style="width:45%;">Server Port:</td>
        <td style="width:55%;"><input type="text" name="server_port" maxlength="5" size="5" value="<?php echo htmlspecialchars($server_port);?>" class="textbox" /></td>
    </tr>
    <tr class="bg1" style="vertical-align:middle;">
        <td class="fSmall" align="left" style="width:45%;">Server Name:</td>
        <td style="width:55%;"><input type="text" name="server_name" maxlength="255" size="35" value="<?php echo htmlspecialchars($server_name);?>" class="textbox" /></td>
    </tr>
    <tr class="bg2" style="vertical-align:middle;">
        <td class="fSmall" align="left" style="width:45%;">Rcon Password:</td>
        <td style="width:55%;">
            <span style="white-space: nowrap;">
                <input id="pwd_server_rcon" type="password" name="server_rcon" maxlength="128" size="15" value="<?php echo htmlspecialchars($server_rcon);?>" class="textbox" />
                <button type="button" style="background:none;border:none;cursor:pointer;font-size:14px;padding:0 2px;vertical-align:middle;user-select:none;"
                    onmousedown="document.getElementById('pwd_server_rcon').type='text';"
                    onmouseup="document.getElementById('pwd_server_rcon').type='password';"
                    onmouseleave="document.getElementById('pwd_server_rcon').type='password';"
                    ontouchstart="document.getElementById('pwd_server_rcon').type='text';"
                    ontouchend="document.getElementById('pwd_server_rcon').type='password';"
                    title="Hold to reveal password">👁</button>
            </span>
        </td>
    </tr>
    <tr class="bg1" style="vertical-align:middle;">
        <td class="fSmall" align="left" style="width:45%;">Public Address:</td>
        <td style="width:55%;"><input type="text" name="public_address" maxlength="128" size="15" value="<?php echo htmlspecialchars($server_public_address);?>" class="textbox" /></td>
    </tr>
    <tr class="bg2" style="vertical-align:middle;">
        <td class="fSmall" align="left" style="width:45%;">Admin Mod:</td>
        <td style="width:55%;">
            <select name="game_mod">
            <option value="PLEASESELECT">PLEASE SELECT</option>
            <?php
                $db->query("SELECT code, name FROM `hlstats_Mods_Supported`");

                while ($row = $db->fetch_array()) {
                    $selected = ($selected_mod === $row['code']) ? ' selected="selected"' : '';
                    echo '<option value="' . htmlspecialchars($row['code']) . '"' . $selected . '>' . htmlspecialchars($row['name']) . '</option>';
                }
            ?>
            </select>
        </td>
    </tr>
</table><br />

<table width="75%" border="0" cellspacing="0" cellpadding="0" style="margin:15px auto;">
    <tr>
        <td align="center"><input type="submit" value="  Add Server  " class="submit" onclick="checkMod();return false;" /></td>
    </tr>
</table>