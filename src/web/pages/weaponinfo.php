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
    // Weapon Details

    $weapon_in = isset($_GET['weapon']) ? (string)$_GET['weapon'] : '';
    $weapon = valid_request($weapon_in, false);

    if (!$weapon) {
        error('No weapon ID specified.');
    }

    // Initialize variables
    $game = isset($game) ? (string)$game : '';
    $game_esc = $db->escape($game);
    $game_url = urlencode($game);
    $weapon_esc = $db->escape((string)$weapon);
    $scripturl = htmlspecialchars((string)($g_options['scripturl'] ?? ''), ENT_QUOTES, 'UTF-8');
    $deletedays = (int)($g_options['DeleteDays'] ?? 28);

    $db->query("
        SELECT
            name
        FROM
            hlstats_Weapons
        WHERE
            code = '$weapon_esc'
            AND game = '$game_esc'
    ");

    if ($db->num_rows() != 1)
    {
        $wep_name = ucfirst((string)$weapon);
    }
    else
    {
        $weapondata = $db->fetch_array();
        $db->free_result();
        $wep_name = (string)($weapondata['name'] ?? ucfirst((string)$weapon));
    }

    $db->query("SELECT name FROM hlstats_Games WHERE code = '$game_esc'");
    if ($db->num_rows() != 1)
    {
        error("No such game '" . htmlspecialchars($game, ENT_QUOTES, 'UTF-8') . "'.");
    }

    $row = $db->fetch_row();
      $gamename = ($row) ? (string)$row[0] : ucfirst($game);
    $db->free_result();

    pageHeader(
        array($gamename, 'Weapon Details', $wep_name),
        array(
            $gamename => $scripturl . "?game=$game_url",
            'Weapon Statistics' => $scripturl . "?mode=weapons&amp;game=$game_url",
            'Weapon Details' => ''
        ),
        $wep_name
    );
    $table = new Table(
	array(
	    new TableColumn(
		'killerName',
		'Player',
		'width=60&align=left&flag=1&link=' . urlencode('mode=playerinfo&amp;player=%k') 
	    ),
	    new TableColumn(
		'frags',
		ucfirst($weapon) . ' kills',
		'width=15&align=right'
	    ),
	    new TableColumn(
		'headshots',
		'Headshots',
		'width=15&align=right'
	    ),
	    new TableColumn(
		'hpk',
		'Hpk',
		'width=5&align=right'
	    ),
	),
	'killerId', // keycol
	'frags', // sort_default
	'killerName', // sort_default2
	true, // showranking
	50 // numperpage
    );

    $result = $db->query("
        SELECT
            hlstats_Events_Frags.killerId,
            unhex(replace(hex(hlstats_Players.lastName), 'E280AE', '')) AS killerName,
            hlstats_Players.flag AS flag,
            COUNT(hlstats_Events_Frags.id) AS frags,
            IFNULL(SUM(hlstats_Events_Frags.headshot = 1), 0) AS headshots,
            ROUND(IFNULL(SUM(hlstats_Events_Frags.headshot = 1) / COUNT(hlstats_Events_Frags.id), 0), 2) AS hpk
        FROM
            hlstats_Events_Frags
        INNER JOIN
            hlstats_Players
            ON hlstats_Players.playerId = hlstats_Events_Frags.killerId
        WHERE
            hlstats_Events_Frags.weapon = '$weapon_esc'
            AND hlstats_Players.game = '$game_esc'
            AND hlstats_Players.hideranking = 0
        GROUP BY
            hlstats_Events_Frags.killerId,
            hlstats_Players.lastName,
            hlstats_Players.flag
        ORDER BY
            $table->sort $table->sortorder,
            $table->sort2 $table->sortorder
        LIMIT
            $table->startitem, $table->numperpage
    ");

    $resultCount = $db->query("
        SELECT
            COUNT(DISTINCT hlstats_Events_Frags.killerId),
            COUNT(hlstats_Events_Frags.id),
            IFNULL(SUM(hlstats_Events_Frags.headshot = 1), 0)
        FROM
            hlstats_Events_Frags
        INNER JOIN
            hlstats_Servers
            ON hlstats_Servers.serverId = hlstats_Events_Frags.serverId
        WHERE
            hlstats_Events_Frags.weapon = '$weapon_esc'
            AND hlstats_Servers.game = '$game_esc'
    ");

    $row = $db->fetch_row($resultCount);
    if ($row) {
        $numitems       = (int)$row[0];
        $totalkills     = (int)$row[1];
        $totalheadshots = (int)$row[2];
    } else {
        $numitems = 0;
        $totalkills = 0;
        $totalheadshots = 0;
    }
?>

<div class="block">
    <?php printSectionTitle('Weapon Details'); ?>
    <div class="subblock">
    <?php
        $image = getImage("/games/$game_url/weapons/" . strtolower($weapon));
        if ($image && !empty($image['url']))
        {
            $wep_content = '<img src="' . htmlspecialchars(str_replace('#', '%23', (string)$image['url']), ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($wep_name, ENT_QUOTES, 'UTF-8') . '" style="vertical-align:middle; margin-right:4px;" />';
        }
        else
        {
            $wep_content = "<strong>" . htmlspecialchars($wep_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</strong>: ";
        }
?>
        <div style="float:left;">
            <?php echo $wep_content; ?>&nbsp;From a total of <b><?php echo number_format($totalkills); ?></b> kills with <b><?php echo number_format($totalheadshots); ?></b> headshots (Last <?php echo $deletedays; ?> Days)
        </div>
        <div style="float:right;">
            Back to <a href="<?php echo $scripturl . '?mode=weapons&amp;game=' . $game_url; ?>">Weapon Statistics</a>
        </div>
        <div style="clear:both;padding:2px;"></div>
    </div>
    <br /><br />
    <?php $table->draw($result, $numitems, 95, 'center'); ?>
</div>