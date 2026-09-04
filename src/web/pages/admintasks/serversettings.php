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

    global $db, $auth, $g_options, $gamecode;

    // PHP 8 Fix: Null coalescing check
    if (($auth->userdata['acclevel'] ?? 0) < 80) {
        die ('Access denied!');
    }

    function setdefaults($key)
    {
        global $db;
        $key = (int)$key;
        $db->query("DELETE FROM `hlstats_Servers_Config` WHERE `serverId` = $key");
        $db->query("INSERT INTO `hlstats_Servers_Config` (`serverId`, `parameter`, `value`)
                    SELECT $key, `parameter`, `value` FROM `hlstats_Servers_Config_Default`");
    }

    // PHP 8 Fix: Simplified input handling
    $key_input = $_GET['key'] ?? ($_POST['key'] ?? 0);
    $key = valid_request((int)$key_input, true);

    if ($key <= 0) {
        die('Server ID not set!');
    }

    $sourceId_input = $_POST['sourceId'] ?? 0;
    $sourceId = valid_request((int)$sourceId_input, true);

    // Initialize EditList once
    $edlist = new EditList('serverConfigId', 'hlstats_Servers_Config', '', false);
    $edlist->columns[] = new EditListColumn('parameter', 'Server parameter name', 30, true, 'readonly', '', 50);
    $edlist->columns[] = new EditListColumn('value', 'Parameter value', 60, false, 'text', '', 128);

    // Process POST submissions
    if (!empty($_POST)) {
        if (isset($_POST['setdefaults']) && $_POST['setdefaults'] === 'defaults') {
            setdefaults($key);
            message('success', 'All server parameters have been successfully reset to defaults.');
        } elseif ($sourceId > 0) {
            $db->query("DELETE FROM `hlstats_Servers_Config` WHERE `serverId` = $key");
            $db->query("INSERT INTO `hlstats_Servers_Config` (`serverId`, `parameter`, `value`)
                        SELECT $key, `parameter`, `value` FROM `hlstats_Servers_Config` WHERE `serverId` = $sourceId");
            message('success', 'Server configuration successfully copied from source server.');
        } else {
            if ($edlist->update()) {
                message('success', 'Operation successful.');
            } else {
                message('warning', $edlist->error());
            }
        }
    }

    // Build URL for Daemon Control preserving game context
    $game_param = !empty($gamecode) ? '&amp;game=' . urlencode($gamecode) : '';
    $daemon_url = htmlspecialchars($g_options['scripturl'] ?? '', ENT_QUOTES, 'UTF-8') . "?mode=admin{$game_param}&amp;task=tools_perlcontrol";

?>

<style type="text/css">
    /* Only hide delete column in the inner parameters table without breaking page layout */
    form[name="serversettingsform"] .data-table tr > th:last-child,
    form[name="serversettingsform"] .data-table tr > td:last-child {
        display: none !important;
    }
</style>

    <table id="startsettings" class="border" style="width:60%;margin:auto;" cellspacing="1" cellpadding="10">
        <tr style="background-color:#FF0000;">
            <td class="fNormal" style="color: #FFF; font-weight: bold; font-size: medium;" align="center">
                Note: For changes on this page to take effect, you <strong>must</strong> <a href="<?php echo htmlspecialchars($g_options['scripturl'] ?? '', ENT_QUOTES, 'UTF-8') . "?mode=admin&amp;task=tools_perlcontrol"; ?>" style="color:#FFF;text-decoration:underline;">reload</a> or restart the HLX:CE daemon.
            </td>
        </tr>
    </table>

<br />

<?php
    // Get available help texts
    $res_help = $db->query("SELECT parameter, description FROM hlstats_Servers_Config_Default");
    $helptexts = array();
    while ($r = $db->fetch_array($res_help)) {
        $helptexts[strtolower((string)$r['parameter'])] = $r['description'];
    }

    $footerscript = $edlist->setHelp('helpdiv', 'parameter', $helptexts);
?>

These are the actual server parameters used by the hlstats.pl script.<br /><br />

<?php
    $result = $db->query("
        SELECT
            serverConfigId,
            parameter,
            value
        FROM
            hlstats_Servers_Config
        WHERE
            serverId = $key
        ORDER BY
            parameter ASC
    ");

    if ($db->num_rows($result) == 0) {
        setdefaults($key);
        $result = $db->query("
            SELECT
                serverConfigId,
                parameter,
                value
            FROM
                hlstats_Servers_Config
            WHERE
                serverId = $key
            ORDER BY
                parameter ASC
        ");
    }

    $edlist->draw($result, false);

    // Get all other server IDs for copying configuration
    $sourceIds = '';
    $res_srv = $db->query("SELECT CONCAT(name,' (',address,':',port,')') AS name, serverId FROM hlstats_Servers WHERE serverId <> $key ORDER BY name, address, port");
    while ($r = $db->fetch_array($res_srv)) {
        $sourceIds .= '<option value="' . (int)$r['serverId'] . '">' . htmlspecialchars((string)$r['name'], ENT_QUOTES, 'UTF-8') . '</option>';
    }

?>

<input type="hidden" name="key" value="<?php echo (int)$key; ?>" />

<table width="75%" border="0" cellspacing="0" cellpadding="4" style="margin:15px auto;">
<tr>
    <td align="center" style="padding:4px;">
        <label style="cursor:pointer;">
            <input type="checkbox" name="setdefaults" value="defaults" /> Reset all settings to default!
        </label>
    </td>
</tr>
<tr>
    <td align="center" style="padding:4px;">
        Set all options like existing server configuration:
        <select name="sourceId" style="margin-left:5px;">
            <option value="0">Select a server</option>
            <?php echo $sourceIds; ?>
        </select>
    </td>
</tr>
<tr>
    <td align="center" style="padding:12px 0 0 0;">
        <input type="submit" value="  Apply  " class="submit" />
    </td>
</tr>
</table>