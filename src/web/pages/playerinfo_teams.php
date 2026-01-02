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
    $player = isset($player) ? (int)$player : 0;
    $game = isset($game) ? $game : '';
    $game_esc = $db->escape($game);
    $game_url = urlencode($game);

    flush();
    $tblTeams = new Table
    (
	array
	(
	    new TableColumn
	    (
		'name',
		'Team',
		'width=35'
	    ),
	    new TableColumn
	    (
		'teamcount',
		'Joined',
		'width=10&align=right&append=+times'
	    ),
	    new TableColumn
	    (
		'percent',
		'%',
		'width=10&sort=no&align=right&append=' . urlencode('%')
	    ),
	    new TableColumn
	    (
		'percent',
		'Ratio',
		'width=40&sort=no&type=bargraph'
	    )
	),
	'name',
	'teamcount',
	'name',
	true,
	9999,
	'teams_page',
	'teams_sort',
	'teams_sortorder',
	'tabteams',
	'desc',
	true
    );
    
    $db->query
    ("
	SELECT
	    COUNT(*)
	FROM
	    hlstats_Events_ChangeTeam
	WHERE
	    hlstats_Events_ChangeTeam.playerId = $player 
    ");
    
    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row();
    $numteamjoins = ($row) ? (int)$row[0] : 0;

    if($numteamjoins == 0) {
	$numteamjoins = 1;
    }

    $result = $db->query
    ("
	SELECT
	    IFNULL(hlstats_Teams.name, hlstats_Events_ChangeTeam.team) AS name,
	    COUNT(hlstats_Events_ChangeTeam.id) AS teamcount,
	    ROUND((COUNT(hlstats_Events_ChangeTeam.id) / $numteamjoins) * 100, 2) AS percent
	FROM
	    hlstats_Events_ChangeTeam
	LEFT JOIN
	    hlstats_Teams
	ON
	    hlstats_Events_ChangeTeam.team = hlstats_Teams.code
	WHERE
	    hlstats_Teams.game = '$game_esc'
	    AND hlstats_Events_ChangeTeam.playerId = $player
	    AND
	    (
		hidden <> '1'
		OR hidden IS NULL
	    )
	GROUP BY
	    hlstats_Events_ChangeTeam.team
	ORDER BY
	    $tblTeams->sort $tblTeams->sortorder,
	    $tblTeams->sort2 $tblTeams->sortorder
    ");
    $numitems = $db->num_rows($result);
    if ($numitems > 0)
    {
	printSectionTitle('Team Selection *');
	$tblTeams->draw($result, $numitems, 95);
?>
    <br /><br />
<?php
    }
    flush();
    $result = $db->query
    ("
	SELECT
	    hlstats_Roles.code,
	    hlstats_Roles.name
	FROM
	    hlstats_Roles
	WHERE
	    hlstats_Roles.game = '$game_esc'
    ");
    
    $fname = array();
    while ($rowdata = $db->fetch_row($result))
    {
	$code = preg_replace("/[ \r\n\t]+/", "", (string)$rowdata[0]);
	$fname[strtolower($code)] = htmlspecialchars((string)$rowdata[1]);
    }
    
    $tblRoles = new Table
    (
	array
	(
	    new TableColumn
	    (
		'code',
		'Role',
		'width=25&type=roleimg&align=left&link=' . urlencode("mode=rolesinfo&amp;role=%k&amp;game=$game_url"),
		$fname
	    ),
	    new TableColumn
	    (
		'rolecount',
		'Joined',
		'width=10&align=right&append=+times'
	    ),
	    new TableColumn
	    (
		'percent',
		'%',
		'width=10&sort=no&align=right&append=' . urlencode('%')
	    ),
	    new TableColumn
	    (
		'percent',
		'Ratio',
		'width=20&sort=no&type=bargraph'
	    ),
	    new TableColumn
	    (
		'killsTotal',
		'Kills',
		'width=10&align=right'
	    ),
	    new TableColumn
	    (
		'deathsTotal',
		'Deaths',
		'width=10&align=right'
	    ),
	    new TableColumn
	    (
		'kpd',
		'K:D',
		'width=10&align=right'
	    )
	),
	'code',
	'rolecount',
	'name',
	true,
	9999,
	'roles_page',
	'roles_sort',
	'roles_sortorder',
	'roles',
	'desc',
	true
    );

    $db->query("DROP TABLE IF EXISTS hlstats_Frags_as");

    $sql_create_temp_table = "
	CREATE TEMPORARY TABLE hlstats_Frags_as
	(
	    playerId INT(10),
	    kills INT(10),
	    deaths INT(10),
	    role varchar(128) NOT NULL default ''
	) DEFAULT CHARSET=" . DB_CHARSET . " DEFAULT COLLATE=" . DB_COLLATE . ";
    ";

    $db->query($sql_create_temp_table);

    $db->query
    ("
	INSERT INTO
	    hlstats_Frags_as
	    (
		playerId,
		kills,
		role
	    )
	SELECT
	    hlstats_Events_Frags.victimId,
	    hlstats_Events_Frags.killerId,
	    hlstats_Events_Frags.killerRole
	FROM
	    hlstats_Events_Frags
	WHERE 
	    hlstats_Events_Frags.killerId = $player
    ");
    $db->query
    ("
	INSERT INTO
	    hlstats_Frags_as
	    (
		playerId,
		deaths,
		role
	    )
	SELECT
	    hlstats_Events_Frags.killerId,
	    hlstats_Events_Frags.victimId,
	    hlstats_Events_Frags.victimRole
	FROM
	    hlstats_Events_Frags
	WHERE 
	    hlstats_Events_Frags.victimId = $player 
    ");

    $db->query("DROP TABLE IF EXISTS hlstats_Frags_as_res");

    $sql_create_temp_table = "
	CREATE TEMPORARY TABLE hlstats_Frags_as_res
	(
	    killsTotal INT(10),
	    deathsTotal INT(10),
	    role varchar(128) NOT NULL default ''
	) DEFAULT CHARSET=" . DB_CHARSET . " DEFAULT COLLATE=" . DB_COLLATE . ";
    ";

    $db->query($sql_create_temp_table);

    $db->query
    ("
	INSERT INTO
	    hlstats_Frags_as_res
	    (
		killsTotal,
		deathsTotal,
		role
	    )
	SELECT
	    COUNT(hlstats_Frags_as.kills) AS kills, 
	    COUNT(hlstats_Frags_as.deaths) AS deaths,
	    hlstats_Frags_as.role
	FROM
	    hlstats_Frags_as
	GROUP BY
	    hlstats_Frags_as.role
    ");
    $db->query
    ("
	SELECT
	    COUNT(*)
	FROM
	    hlstats_Events_ChangeRole
	WHERE
	    hlstats_Events_ChangeRole.playerId = $player
    ");
    
    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row();
    $numrolejoins = ($row) ? (int)$row[0] : 0;
    
    // Prevent division by zero
    $numrolejoins_sql = ($numrolejoins == 0) ? 1 : $numrolejoins;

    $result = $db->query
    ("
	SELECT
	    IFNULL(hlstats_Roles.name, hlstats_Events_ChangeRole.role) AS name,
	    IFNULL(hlstats_Roles.code, hlstats_Events_ChangeRole.role) AS code,
	    COUNT(hlstats_Events_ChangeRole.id) AS rolecount,
	    ROUND(COUNT(hlstats_Events_ChangeRole.id) / $numrolejoins_sql * 100, 2) AS percent,
	    hlstats_Frags_as_res.killsTotal,
	    hlstats_Frags_as_res.deathsTotal,
	    ROUND(hlstats_Frags_as_res.killsTotal / IF(hlstats_Frags_as_res.deathsTotal = 0, 1, hlstats_Frags_as_res.deathsTotal), 2) AS kpd
	FROM
	    hlstats_Events_ChangeRole
	LEFT JOIN
	    hlstats_Roles
	ON
	    hlstats_Events_ChangeRole.role = hlstats_Roles.code
	LEFT JOIN
	    hlstats_Frags_as_res
	ON
	    hlstats_Frags_as_res.role = hlstats_Events_ChangeRole.role
	WHERE
	    hlstats_Events_ChangeRole.playerId = $player
	    AND
	    (
		hidden <> '1'
		OR hidden IS NULL
	    )
	    AND hlstats_Roles.game = '$game_esc'
	GROUP BY
	    hlstats_Events_ChangeRole.role,
	    hlstats_Frags_as_res.killsTotal,
	    hlstats_Frags_as_res.deathsTotal
	ORDER BY
	    $tblRoles->sort $tblRoles->sortorder,
	    $tblRoles->sort2 $tblRoles->sortorder
    ");
    $numitems = $db->num_rows($result);
    if ($numitems > 0)
    {
	printSectionTitle('Role Selection *');
	$tblRoles->draw($result, $numitems, 95);
?>
    <br /><br />

<?php
    }
?>