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

    global $db, $game, $clan, $realkills, $realheadshots;

    // Ensure safe types
    $clan = isset($clan) ? (int)$clan : 0;
    $game = isset($game) ? (string)$game : '';
    $game_esc = $db->escape($game);
    $game_url = urlencode($game);

    // Prevent division by zero in SQL
    $div_realkills = ((int)$realkills > 0) ? (int)$realkills : 1;
    $div_realheadshots = ((int)$realheadshots > 0) ? (int)$realheadshots : 1;

    flush();

    $tblMaps = new Table(
        array(
            new TableColumn(
                'map',
                'Map Name',
                'width=15&align=left&link=' . urlencode("mode=mapinfo&map=%k&game=$game_url")
            ),
            new TableColumn(
                'kills',
                'Kills',
                'width=6&align=right'
            ),
            new TableColumn(
                'kpercent',
                'Percentage of Kills',
                'width=15&sort=no&type=bargraph'
            ),
            new TableColumn(
                'kpercent',
                '%',
                'width=5&sort=no&align=right&append=' . urlencode('%')
            ),
            new TableColumn(
                'deaths',
                'Deaths',
                'width=6&align=right'
            ),
            new TableColumn(
                'kpd',
                'Kills per Death',
                'width=13&align=right'
            ),
            new TableColumn(
                'headshots',
                'Headshots',
                'width=9&align=right'
            ),
            new TableColumn(
                'hpercent',
                'Percentage of Headshots',
                'width=16&sort=no&type=bargraph'
            ),
            new TableColumn(
                'hpercent',
                '%',
                'width=5&sort=no&align=right&append=' . urlencode('%')
            ),
            new TableColumn(
                'hpk',
                'Hpk',
                'width=5&align=right'
            )

        ),
        'map',
        'kills',
        'kills',
        true,
        9999,
        'maps_page',
        'maps_sort',
        'maps_sortorder',
        'tabmaps',
        'desc',
        true
    );

    $db->query("DROP TEMPORARY TABLE IF EXISTS tmp_clan_kills");
    $db->query("
        CREATE TEMPORARY TABLE tmp_clan_kills
            SELECT
                IF(map='', '(Unaccounted)', map) AS map,
                COUNT(*) AS kills,
                IFNULL(SUM(headshot=1), 0) AS headshots
            FROM
                hlstats_Events_Frags
            INNER JOIN
                hlstats_Players
                ON hlstats_Players.playerId = hlstats_Events_Frags.killerId
            WHERE
                hlstats_Players.clan = $clan
                AND hlstats_Players.game = '$game_esc'
            GROUP BY
                map;
    ");

    $db->query("DROP TEMPORARY TABLE IF EXISTS tmp_clan_deaths");
    $db->query("
        CREATE TEMPORARY TABLE tmp_clan_deaths
            SELECT
                IF(map='', '(Unaccounted)', map) AS map,
                COUNT(*) AS deaths
            FROM
                hlstats_Events_Frags
            INNER JOIN
                hlstats_Players
                ON hlstats_Players.playerId = hlstats_Events_Frags.victimId
            WHERE
                hlstats_Players.clan = $clan
                AND hlstats_Players.game = '$game_esc'
            GROUP BY
                map;
    ");

    $result = $db->query("
        SELECT
            tmp_clan_kills.map,
            tmp_clan_kills.kills,
            tmp_clan_kills.headshots,
            IFNULL(tmp_clan_deaths.deaths, 0) AS deaths,
            ROUND(tmp_clan_kills.kills / IF(IFNULL(tmp_clan_deaths.deaths, 0) = 0, 1, tmp_clan_deaths.deaths), 2) AS kpd,
            ROUND(tmp_clan_kills.headshots / IF(tmp_clan_kills.kills = 0, 1, tmp_clan_kills.kills), 2) AS hpk,
            ROUND(tmp_clan_kills.kills / $div_realkills * 100, 2) AS kpercent,
            ROUND(tmp_clan_kills.headshots / $div_realheadshots * 100, 2) AS hpercent
        FROM
            tmp_clan_kills
        LEFT JOIN
            tmp_clan_deaths
            ON tmp_clan_kills.map = tmp_clan_deaths.map
        ORDER BY
            $tblMaps->sort $tblMaps->sortorder,
            $tblMaps->sort2 $tblMaps->sortorder
    ");

    $numitems = $db->num_rows($result);
    if ($numitems > 0)
    {
?>
    <div style="clear:both;padding-top:20px;"></div>
<?php
    printSectionTitle('Map Performance *');
    $tblMaps->draw($result, $numitems, 95);
?>
<br /><br />
<?php
    }
?>