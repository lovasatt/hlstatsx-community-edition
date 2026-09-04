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

    global $db, $game, $clan;

    // Security: Type cast
    $clan = isset($clan) ? (int)$clan : 0;
    $game = isset($game) ? (string)$game : '';
    $game_esc = $db->escape($game);
    $game_url = urlencode($game);

    flush();

    $tblTeams = new Table(
        array(
            new TableColumn(
                'name',
                'Team',
                'width=35'
            ),
            new TableColumn(
                'teamcount',
                'Joined',
                'width=10&align=right&append=+times'
            ),
            new TableColumn(
                'percent',
                'Percentage of Times',
                'width=40&sort=no&type=bargraph'
            ),
            new TableColumn(
                'percent',
                '%',
                'width=10&sort=no&align=right&append=' . urlencode('%')
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

    $db->query("
        SELECT
            COUNT(hlstats_Events_ChangeTeam.id)
        FROM
            hlstats_Events_ChangeTeam
        INNER JOIN hlstats_Teams ON
            hlstats_Events_ChangeTeam.team = hlstats_Teams.code
            AND hlstats_Teams.game = '$game_esc'
            AND (
                hlstats_Teams.hidden <> '1'
                OR hlstats_Teams.hidden IS NULL
            )
        INNER JOIN hlstats_Players ON
            hlstats_Players.playerId = hlstats_Events_ChangeTeam.playerId
        WHERE
            hlstats_Players.clan = $clan
            AND hlstats_Players.game = '$game_esc'
    ");

    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row();
    $numteamjoins = ($row) ? (int)$row[0] : 0;

    // Prevent division by zero
    $numteamjoins_sql = ($numteamjoins == 0) ? 1 : $numteamjoins;

    $result  = $db->query("SELECT `code`,`name` FROM hlstats_Roles WHERE game='$game_esc'");

    $fname = array();
    while ($rowdata = $db->fetch_row($result))
    {
        $code = preg_replace("/[ \r\n\t]+/", '', (string)$rowdata[0]);
        $fname[strtolower($code)] = htmlspecialchars((string)$rowdata[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    $db->free_result($result);

    $result = $db->query("
        SELECT
            hlstats_Teams.name AS name,
            COUNT(hlstats_Events_ChangeTeam.id) AS teamcount,
            ROUND(COUNT(hlstats_Events_ChangeTeam.id) / $numteamjoins_sql * 100, 2) AS percent
        FROM
            hlstats_Events_ChangeTeam
        INNER JOIN hlstats_Teams ON
            hlstats_Events_ChangeTeam.team = hlstats_Teams.code
            AND hlstats_Teams.game = '$game_esc'
            AND (
                hlstats_Teams.hidden <> '1'
                OR hlstats_Teams.hidden IS NULL
            )
        INNER JOIN hlstats_Players ON
            hlstats_Players.playerId = hlstats_Events_ChangeTeam.playerId
        WHERE
            hlstats_Players.clan = $clan
            AND hlstats_Players.game = '$game_esc'
        GROUP BY
            hlstats_Events_ChangeTeam.team,
            hlstats_Teams.name
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

        $tblRoles = new Table(
        array
        (
            new TableColumn
            (
                'code',
                'Role',
                'width=25&type=roleimg&align=left&link=' . urlencode("mode=rolesinfo&role=%k&game=$game_url"),
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

    $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
    $collate = defined('DB_COLLATE') ? DB_COLLATE : 'utf8mb4_unicode_ci';
    $charset_clause = "DEFAULT CHARSET={$charset} COLLATE={$collate}";

    $db->query("DROP TEMPORARY TABLE IF EXISTS hlstats_Frags_as");

    $sql_create_temp_table = "
        CREATE TEMPORARY TABLE hlstats_Frags_as
        (
            playerId INT(10),
            kills INT(10),
            deaths INT(10),
            role varchar(128) NOT NULL default ''
        ) " . $charset_clause . ";
    ";

    $db->query($sql_create_temp_table);

    $db->query("
        INSERT INTO
        hlstats_Frags_as
        (
            playerId,
            kills,
            role
        )
        SELECT
            victimId,
            killerId,
            killerRole
        FROM
            hlstats_Events_Frags
        LEFT JOIN hlstats_Servers ON
            hlstats_Servers.serverId=hlstats_Events_Frags.serverId LEFT JOIN hlstats_Players ON
            hlstats_Players.playerId = hlstats_Events_Frags.killerId
        WHERE
            hlstats_Servers.game='$game_esc' AND clan = $clan
        ");

    $db->query("
        INSERT INTO
            hlstats_Frags_as
        (
            playerId,
            deaths,
            role
        )
        SELECT
            killerId,
            victimId,
            victimRole
        FROM
            hlstats_Events_Frags
        LEFT JOIN
            hlstats_Servers
        ON
            hlstats_Servers.serverId = hlstats_Events_Frags.serverId
        LEFT JOIN
            hlstats_Players
        ON
            hlstats_Players.playerId = hlstats_Events_Frags.victimId
        WHERE
            hlstats_Servers.game='$game_esc' AND clan = $clan
        ");

    $db->query("DROP TEMPORARY TABLE IF EXISTS hlstats_Frags_as_res");

    $sql_create_temp_table = "
        CREATE TEMPORARY TABLE hlstats_Frags_as_res
        (
            killsTotal INT(10),
            deathsTotal INT(10),
            role varchar(128) NOT NULL default ''
        ) " . $charset_clause . ";
    ";

    $db->query($sql_create_temp_table);

    $db->query("
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
        role
        from hlstats_Frags_as GROUP by role
    ");

    $db->query("
        SELECT
            COUNT(*)
        FROM
            hlstats_Events_ChangeRole
        LEFT JOIN hlstats_Players ON
            hlstats_Players.playerId=hlstats_Events_ChangeRole.playerId
        WHERE
            clan=$clan
    ");

    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row();
    $numrolejoins = ($row) ? (int)$row[0] : 0;
    $numrolejoins_sql = ($numrolejoins == 0) ? 1 : $numrolejoins;

    $result = $db->query("
        SELECT
            IFNULL(hlstats_Roles.name, hlstats_Events_ChangeRole.role) AS name,
            IFNULL(hlstats_Roles.code, hlstats_Events_ChangeRole.role) AS code,
            COUNT(hlstats_Events_ChangeRole.id) AS rolecount,
            ROUND(COUNT(hlstats_Events_ChangeRole.id) / $numrolejoins_sql * 100, 2) AS percent,
            IFNULL(killsTotal, 0) AS killsTotal,
            IFNULL(deathsTotal, 0) AS deathsTotal,
            ROUND(IFNULL(killsTotal, 0) / IF(IFNULL(deathsTotal, 0) = 0, 1, deathsTotal), 2) AS kpd
        FROM
            hlstats_Events_ChangeRole
        LEFT JOIN
            hlstats_Roles
        ON
            (hlstats_Events_ChangeRole.role = hlstats_Roles.code AND hlstats_Roles.game = '$game_esc')
        LEFT JOIN
            hlstats_Servers
        ON
            hlstats_Servers.serverId = hlstats_Events_ChangeRole.serverId
        LEFT JOIN
            hlstats_Frags_as_res
        ON
            hlstats_Frags_as_res.role = hlstats_Events_ChangeRole.role
        LEFT JOIN
            hlstats_Players
        ON
            hlstats_Players.playerId = hlstats_Events_ChangeRole.playerId
        WHERE
            hlstats_Servers.game = '$game_esc'
            AND hlstats_Players.clan = $clan
            AND (hlstats_Roles.hidden <> '1' OR hlstats_Roles.hidden IS NULL)
        GROUP BY
            hlstats_Events_ChangeRole.role,
            hlstats_Roles.name,
            hlstats_Roles.code,
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