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

    global $db, $auth, $g_options, $selTask;

    // PHP 8 Fix: Null coalescing check
    if (($auth->userdata["acclevel"] ?? 0) < 80) {
        die ("Access denied!");
    }

    $id = 0;
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $id = (int)$_GET['id'];
    }

    if ($id <= 0) {
        die("Invalid Player ID!");
    }
?>

&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" class="imageformat" alt="" /><b>&nbsp;<a href="<?php echo htmlspecialchars($g_options['scripturl'] ?? '', ENT_QUOTES, 'UTF-8'); ?>?mode=admin&amp;task=tools_editdetails">Edit Player or Clan Details</a></b><br />

<img src="<?php echo IMAGE_PATH; ?>/spacer.gif" width="1" height="8" alt="" /><br />
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" class="imageformat" alt="" /><b>&nbsp;<?php echo "Edit Player #" . (int)$id; ?></b><br /><br />

<form method="post" action="<?php echo htmlspecialchars($g_options['scripturl'] ?? '', ENT_QUOTES, 'UTF-8') . "?mode=admin&amp;task=" . urlencode((string)($selTask ?? 'tools_editdetails_player')) . "&amp;id=" . (int)$id . (defined('SID') && SID ? '&amp;' . strip_tags(SID) : ''); ?>">
<?php

    // get available country flag files
    // PHP 8 Fix: Initialize variable
    $flagselect = "";
    $res_flags = $db->query("SELECT `flag`, `name` FROM hlstats_Countries ORDER BY `name`");
    while ($rowdata = $db->fetch_row($res_flags))
    {
        $flagselect .= ";" . (string)$rowdata[0] . "/" . (string)$rowdata[1];
    }
    $flagselect .= ";";

    $proppage = new PropertyPage("hlstats_Players", "playerId", $id, array(
        new PropertyPage_Group("Profile", array(
            new PropertyPage_Property("fullName", "Real Name", "text"),
            new PropertyPage_Property("email", "E-mail Address", "text"),
            new PropertyPage_Property("homepage", "Homepage URL", "text"),
            new PropertyPage_Property("mmrank", "MM Rank", "select", "0/;1/Silver I;2/Silver II;3/Silver III;4/Silver IV;5/Silver Elite;6/Silver Elite Master;7/Gold Nova I;8/Gold Nova II;9/Gold Nova III;10/Gold Nova Master;11/Master Guardian I;12/Master Guardian II;13/Master Guardian Elite;14/Distinguished Master Guardian;15/Legendary Eagle;16/Legendary Eagle Master;17/Supreme Master First Class;18/The Global Elite"),
            new PropertyPage_Property("flag", "Country Flag", "select", $flagselect),
            new PropertyPage_Property("skill", "Points", "text"),
            new PropertyPage_Property("kills", "Kills", "text"),
            new PropertyPage_Property("deaths", "Deaths", "text"),
            new PropertyPage_Property("headshots", "Headshots", "text"),
            new PropertyPage_Property("suicides", "Suicides", "text"),
            new PropertyPage_Property("hideranking", "Hide Ranking", "select", "0/No (Visible);1/Yes (Hidden);2/Flag as Banned;3/Inactive (Automatic)"),
            new PropertyPage_Property("blockavatar", "Force Default Avatar Image (note that this overrides images in hlstatsimg/avatars)", "select", "0/No (Visible);1/Yes (Hidden)"),
        ))
    ));

    if (isset($_POST['fullName']))
    {
        if (!empty($_POST['flag']))
        {
            $flag_esc = $db->escape($_POST['flag']);
            $res_c = $db->query("SELECT `name` FROM hlstats_Countries WHERE `flag` = '$flag_esc' LIMIT 1");
            if ($res_c && $row_c = $db->fetch_row($res_c))
            {
                $_POST['country'] = $row_c[0];
            }
        }

        $proppage->update();
        message("success", "Profile updated successfully.");
    }
    $playerId = (int)$id;
    $result = $db->query("
        SELECT
            *
        FROM
            hlstats_Players
        WHERE
            playerId='$playerId'
    ");
    if ($db->num_rows($result) < 1) {
        die("No player exists with ID #$id");
    }

    $data = $db->fetch_array($result);

    echo '<span class="fTitle">';
    // PHP 8 Fix: XSS Protection
    echo htmlspecialchars((string)($data['lastName'] ?? ''), ENT_QUOTES, 'UTF-8');
    echo '</span>';

    echo '<span class="fNormal">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'
        . '<a href="' . htmlspecialchars($g_options['scripturl'] ?? '', ENT_QUOTES, 'UTF-8') . "?mode=playerinfo&amp;player=" . (int)$id . (defined('SID') && SID ? '&amp;' . strip_tags(SID) : '') . '">'
        . '(View Player Details)</a></span>';
?><br /><br />

<table width="60%" border="0" cellspacing="0" cellpadding="0" style="margin:15px auto;">
<tr>
    <td class="fNormal"><?php
        $proppage->draw($data);
?>
    <div style="text-align:center;margin-top:12px;"><input type="submit" value="  Apply  " class="submit" /></div></td>
</tr>
</table>
</form>

<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    var flagSelect = document.querySelector('select[name="flag"]');
    var countryInput = document.querySelector('input[name="country"]');

    if (flagSelect && countryInput) {
        flagSelect.addEventListener('change', function() {
            var selectedText = flagSelect.options[flagSelect.selectedIndex].text;
            if (selectedText) {
                countryInput.value = selectedText;
            }
        });
    }
});
</script>

<?php
    $tblIps = new Table
    (
        array
        (
            new TableColumn
            (
                'ipAddress',
                'IP Address',
                'width=40'
            ),
            new TableColumn
            (
                'eventTime',
                'Last Used',
                'width=60'
            )
        ),
        'ipAddress',
        'eventTime',
        'eventTime'
    );
    $resultIps = $db->query
    ("
        SELECT
            ipAddress,
            MAX(eventTime) AS eventTime
        FROM
            hlstats_Events_Connects
        WHERE
            playerId = '$playerId'
        GROUP BY
            ipAddress
        ORDER BY
            eventTime DESC
    ");
?>
<div class="block">
<?php
    printSectionTitle('Player IP Addresses');
    $tblIps->draw($resultIps, 50, 50);
?>
</div><br /><br />