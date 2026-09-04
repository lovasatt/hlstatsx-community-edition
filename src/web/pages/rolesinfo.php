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
    
    // Roles Details
    $role_in = isset($_GET['role']) ? (string)$_GET['role'] : '';
    $role = valid_request($role_in, false);

    if (!$role) {
        error('No role ID specified.');
    }

    // Security: Escape variables
    $game = isset($game) ? (string)$game : '';
    $game_esc = $db->escape($game);
    $game_url = urlencode($game);
    $role_esc = $db->escape((string)$role);
    $scripturl = htmlspecialchars((string)($g_options['scripturl'] ?? ''), ENT_QUOTES, 'UTF-8');
    $deletedays = (int)($g_options['DeleteDays'] ?? 28);

    $db->query("
        SELECT
            hlstats_Roles.name,
            hlstats_Roles.code
        FROM
            hlstats_Roles
        WHERE
            hlstats_Roles.code = '$role_esc'
            AND hlstats_Roles.game = '$game_esc'
    ");

    if ($db->num_rows() != 1) {
        $role_name = ucfirst((string)$role);
        $role_code = ucfirst((string)$role);
    } else {
        $roledata = $db->fetch_array();
        $db->free_result();
        $role_name = (string)($roledata['name'] ?? ucfirst((string)$role));
        $role_code = (string)($roledata['code'] ?? (string)$role);
    }

    $db->query("SELECT name FROM hlstats_Games WHERE code = '$game_esc'");
    if ($db->num_rows() != 1) {
        error("No such game '" . htmlspecialchars($game, ENT_QUOTES, 'UTF-8') . "'.");
    }

    $row = $db->fetch_row();
    $gamename = ($row) ? (string)$row[0] : ucfirst($game);
    $db->free_result();

    pageHeader(
        array($gamename, 'Role Details', $role_name),
        array(
            $gamename => $scripturl . "?game=$game_url",
            'Role Statistics' => $scripturl . "?mode=roles&amp;game=$game_url",
            'Role Details' => ''
        ),
        $role_name
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
		ucfirst($role_name) . ' kills',
		'width=35&align=right'
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
            COUNT(hlstats_Events_Frags.id) AS frags
        FROM
            hlstats_Events_Frags
        INNER JOIN
            hlstats_Players
            ON hlstats_Players.playerId = hlstats_Events_Frags.killerId
        WHERE
            hlstats_Events_Frags.killerRole = '$role_esc'
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
            hlstats_Events_Frags.killerRole = '$role_esc'
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
    <?php printSectionTitle('Role Details'); ?>
    <div class="subblock">
<?php
    $wep_content = "<strong>" . htmlspecialchars($role_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</strong>: ";
    $image = getImage("/games/$game_url/roles/" . strtolower($role_code));
    if ($image && !empty($image['url'])) {
        $wep_content .= '<img src="' . htmlspecialchars(str_replace('#', '%23', (string)$image['url']), ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($role_name, ENT_QUOTES, 'UTF-8') . '" style="vertical-align:middle;" />';
    }
?>
        <div style="float:left;">
            <?php echo $wep_content; ?>
            &nbsp;From a total of <b><?php echo number_format($totalkills); ?></b> kills as <?php
                echo htmlspecialchars($role_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                if ($totalheadshots > 0 || $role === 'sniper')
                {
                    echo ' with <b>' . number_format($totalheadshots) . '</b> headshots ';
                }
                ?> (Last <?php echo $deletedays; ?> Days)
        </div>
        <div style="float:right;">
            Back to <a href="<?php echo $scripturl . '?mode=roles&amp;game=' . $game_url; ?>">Role Statistics</a>
        </div>
        <div style="clear:both;padding:2px;"></div>
    </div>
    <br /><br />
<?php 
    $table->draw($result, $numitems, 95, 'center');
?>
</div>