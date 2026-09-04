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

    // Initialize variables
    $game = isset($game) ? (string)$game : '';
    $game_esc = $db->escape($game);
    $game_url = urlencode($game);
    $scripturl = htmlspecialchars((string)($g_options['scripturl'] ?? ''), ENT_QUOTES, 'UTF-8');

    // Role Statistics
    $db->query
    ("
        SELECT
            hlstats_Games.name
        FROM
            hlstats_Games
        WHERE
            hlstats_Games.code = '$game_esc'
    ");
    if ($db->num_rows() < 1) {
        error("No such game '" . htmlspecialchars($game, ENT_QUOTES, 'UTF-8') . "'.");
    }

    $row = $db->fetch_row();
    $gamename = ($row) ? (string)$row[0] : '';
    $db->free_result();

    pageHeader
    (
        array ($gamename, 'Role Statistics'),
        array ($gamename => "%s?game=$game_url", 'Role Statistics' => '')
    );

    $result = $db->query
    ("
	SELECT
	    hlstats_Roles.code,
	    hlstats_Roles.name
	FROM
	    hlstats_Roles
	WHERE
	    hlstats_Roles.game='$game_esc'
    ");

    $fname = array();
    while ($rowdata = $db->fetch_row($result))
    {
        $code = $rowdata[0];
        $fname[strtolower((string)$code)] = htmlspecialchars((string)$rowdata[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $tblRoles = new Table
    (
        array
        (
            new TableColumn
            (
                'code',
                'Role',
                'width=24&type=roleimg&align=left&link=' . urlencode("mode=rolesinfo&amp;role=%k&amp;game=$game_url"),
                $fname
            ),
	    new TableColumn
	    (
		'picked',
		'Picked',
		'width=9&align=right&append=+times'
	    ),
	    new TableColumn
	    (
		'ppercent',
		'%',
		'width=6&align=right&append=' . urlencode('%')
	    ),
	    new TableColumn
	    (
		'ppercent',
		'Ratio',
		'width=9&sort=no&type=bargraph'
	    ),
	    new TableColumn
	    (
		'kills',
		'Kills',
		'width=6&align=right'
	    ),
	    new TableColumn
	    (
		'kpercent',
		'%',
		'width=6&align=right&append=' . urlencode('%')
	    ),
	    new TableColumn
	    (
		'kpercent',
		'Ratio',
		'width=9&sort=no&type=bargraph'
	    ),
	    new TableColumn
	    (
		'deaths',
		'Deaths',
		'width=6&align=right'
	    ),
	    new TableColumn
	    (
		'dpercent',
		'%',
		'width=6&sort=no&align=right&append=' . urlencode('%')
	    ),
	    new TableColumn
	    (
		'dpercent',
		'Ratio',
		'width=9&sort=no&type=bargraph'
	    ),
	    new TableColumn
	    (
		'kpd',
		'K:D',
		'width=5&align=right'
	    )
	),
	'code',
	'kills',
	'name',
	true,
	9999,
	'role_page',
	'role_sort',
	'role_sortorder'
    );
    $db->query
    ("
        SELECT
            IFNULL(SUM(hlstats_Roles.kills), 0),
            IFNULL(SUM(hlstats_Roles.deaths), 0),
            IFNULL(SUM(hlstats_Roles.picked), 0)
        FROM
            hlstats_Roles
        WHERE
            hlstats_Roles.game = '$game_esc'
            AND hlstats_Roles.hidden = '0'
    ");

    $row = $db->fetch_row();
    $totalkills  = ($row) ? (int)$row[0] : 0;
    $totaldeaths = ($row) ? (int)$row[1] : 0;
    $totalpicked = ($row) ? (int)$row[2] : 0;

    $div_realkills  = ($totalkills > 0) ? $totalkills : 1;
    $div_realdeaths = ($totaldeaths > 0) ? $totaldeaths : 1;
    $div_realpicked = ($totalpicked > 0) ? $totalpicked : 1;

    $result = $db->query
    ("
        SELECT
            hlstats_Roles.code,
            hlstats_Roles.name,
            hlstats_Roles.picked,
            ROUND(hlstats_Roles.picked / $div_realpicked * 100, 2) AS ppercent,
            hlstats_Roles.kills,
            ROUND(hlstats_Roles.kills / $div_realkills * 100, 2) AS kpercent,
            hlstats_Roles.deaths,
            ROUND(hlstats_Roles.deaths / $div_realdeaths * 100, 2) AS dpercent,
            ROUND(hlstats_Roles.kills / IF(hlstats_Roles.deaths = 0, 1, hlstats_Roles.deaths), 2) AS kpd
        FROM
            hlstats_Roles
        WHERE
            hlstats_Roles.game = '$game_esc'
            AND (hlstats_Roles.kills > 0 OR hlstats_Roles.deaths > 0 OR hlstats_Roles.picked > 0)
            AND hlstats_Roles.hidden = '0'
        GROUP BY
            hlstats_Roles.roleId
        ORDER BY
            $tblRoles->sort $tblRoles->sortorder,
            $tblRoles->sort2 $tblRoles->sortorder
    ");
?>

<div class="block">
    <?php printSectionTitle('Role Statistics'); ?>
    <div class="subblock">
        From a total of <strong><?php echo number_format($totalkills); ?></strong> kills with <strong><?php echo number_format($totaldeaths); ?></strong> deaths
    </div>
    <br /><br />
    <?php $tblRoles->draw($result, $db->num_rows($result), 95); ?><br /><br />
    <div class="subblock">
        <div style="float:right;">
            Go to: <a href="<?php echo $scripturl . '?game=' . $game_url; ?>"><?php echo htmlspecialchars($gamename, ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
        <div style="clear:both;"></div>
    </div>
</div>