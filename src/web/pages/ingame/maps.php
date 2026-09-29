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

    // Player Details

    // PHP 8 Fix: Null coalescing and type casting
    $player_in = $_GET['player'] ?? 0;
    $player = valid_request((int)$player_in, true);

    $uniqueid_in = $_GET['uniqueid'] ?? '';
    $uniqueid = valid_request((string)$uniqueid_in, false);

    $game_in = $_GET['game'] ?? '';
    $game = valid_request((string)$game_in, false);

    // Security: Escape variables
    $game_esc = $db->escape($game);
    $uniqueid_esc = $db->escape($uniqueid);

    if (!$player && $uniqueid) {
        if (!$game) {
            $redirect_url = ($g_options['scripturl'] ?? 'hlstats.php') . "?mode=search&st=uniqueid&q=" . urlencode($uniqueid);
            header("Location: $redirect_url");
            exit;
        }

        $db->query("
            SELECT
                playerId
            FROM
                hlstats_PlayerUniqueIds
            WHERE
                uniqueId='$uniqueid_esc'
                AND game='$game_esc'
        ");

        if ($db->num_rows() > 1) {
            $redirect_url = ($g_options['scripturl'] ?? 'hlstats.php') . "?mode=search&st=uniqueid&q=" . urlencode($uniqueid) . "&game=" . urlencode($game);
            header("Location: $redirect_url");
            exit;
        } elseif ($db->num_rows() < 1) {
            error("No players found matching uniqueId '$uniqueid'");
        } else {
            // PHP 8 Fix: Replace list()
            $row = $db->fetch_row();
            $player = (int)($row[0] ?? 0);
            $db->free_result();
        }
    } elseif (!$player && !$uniqueid) {
        error('No player ID specified.');
    }

    $db->query("
        SELECT
            hlstats_Players.playerId,
            hlstats_Players.lastName,
            hlstats_Players.game
        FROM
            hlstats_Players
        WHERE
            playerId='$player'
    ");

    if ($db->num_rows() != 1) {
        error("No such player '$player'.");
    }

    $playerdata = $db->fetch_array();
    $db->free_result();

    // Multi-byte safe string truncation and UTF-8 escaping
    $raw_name = (string)($playerdata['lastName'] ?? '');

    if (mb_strlen($raw_name, 'UTF-8') > 10) {
        $pl_shortname = mb_substr($raw_name, 0, 8, 'UTF-8') . '...';
    } else {
        $pl_shortname = $raw_name;
    }

    $pl_name = htmlspecialchars($raw_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $pl_shortname = htmlspecialchars($pl_shortname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $pl_urlname = urlencode((string)($playerdata['lastName'] ?? ''));

    $game = (string)($playerdata['game'] ?? $game ?? '');
    $game_esc = $db->escape($game);

    $db->query("SELECT name FROM hlstats_Games WHERE code='$game_esc'");

    if ($db->num_rows() != 1) {
        $gamename = ucfirst($game);
        $db->free_result();
    } else {
        // PHP 8 Fix: Replace list()
        $row = $db->fetch_row();
        $gamename = ($row) ? (string)$row[0] : '';
        $db->free_result();
    }

    // Added: Page Header for proper layout
    pageHeader(
        array ($gamename, 'Map Performance', $pl_name),
        array ($gamename => ($g_options['scripturl'] ?? 'hlstats.php') . "?game=" . urlencode($game), 'Map Performance' => '')
    );

    $tblMaps = new Table(
        array(
            new TableColumn(
                'map',
                'Map Name',
                'width=18&align=left&link=' . urlencode("mode=mapinfo&map=%k&game=" . urlencode($game))
            ),
            new TableColumn(
                'kills',
                'Kills',
                'width=7&align=right'
            ),
            new TableColumn(
                'kpercent',
                'Perc. Kills',
                'width=10&sort=no&type=bargraph'
            ),
            new TableColumn(
                'kpercent',
                '%',
                'width=6&sort=no&align=right&append=' . urlencode('%')
            ),
            new TableColumn(
                'deaths',
                'Deaths',
                'width=7&align=right'
            ),
            new TableColumn(
                'kpd',
                'Kpd',
                'width=13&align=right'
            ),
            new TableColumn(
                'headshots',
                'Headshots',
                'width=10&align=right'
            ),
            new TableColumn(
                'hpercent',
                'Perc. Headshots',
                'width=12&sort=no&type=bargraph'
            ),
            new TableColumn(
                'hpercent',
                '%',
                'width=6&sort=no&align=right&append=' . urlencode('%')
            ),
            new TableColumn(
                'hpk',
                'Hpk',
                'width=6&align=right'
            )

        ),
        'map',
        'kpd',
        'kills',
        true,
        9999,
        'maps_page',
        'maps_sort',
        'maps_sortorder',
        'maps'
    );

    $db->query("
        SELECT
            COUNT(*)
        FROM
            hlstats_Events_Frags
        LEFT JOIN hlstats_Servers ON
            hlstats_Servers.serverId=hlstats_Events_Frags.serverId
        WHERE
            hlstats_Servers.game='$game_esc' AND killerId='$player'
    ");

    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row();
    $realkills = ($row) ? (int)$row[0] : 0;
    $db->free_result();

    $db->query("
        SELECT
            COUNT(*)
        FROM
            hlstats_Events_Frags
        LEFT JOIN hlstats_Servers ON
            hlstats_Servers.serverId=hlstats_Events_Frags.serverId
        WHERE
            hlstats_Servers.game='$game_esc' AND killerId='$player'
            AND headshot=1
    ");

    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row();
    $realheadshots = ($row) ? (int)$row[0] : 0;
    $db->free_result();

    // Prevent division by zero
    $realkills_sql = ($realkills > 0) ? $realkills : 1;
    $realheadshots_sql = ($realheadshots > 0) ? $realheadshots : 1;

    $result = $db->query("
        SELECT
            IF(map='', '(Unaccounted)', map) AS map,
            SUM(killerId=$player) AS kills,
            SUM(victimId=$player) AS deaths,
            IFNULL(ROUND(SUM(killerId=$player) / NULLIF(SUM(victimId=$player), 0), 2), '-') AS kpd,
            ROUND(SUM(killerId=$player) / $realkills_sql * 100, 2) AS kpercent,
            SUM(killerId=$player AND headshot=1) as headshots,
            IFNULL(ROUND(SUM(killerId=$player AND headshot=1) / NULLIF(SUM(killerId=$player), 0), 2), '-') AS hpk,
            ROUND(SUM(killerId=$player AND headshot=1) / $realheadshots_sql * 100, 2) AS hpercent
        FROM
            hlstats_Events_Frags
        LEFT JOIN hlstats_Servers ON
            hlstats_Servers.serverId=hlstats_Events_Frags.serverId
        WHERE
            hlstats_Servers.game='$game_esc' AND (killerId='$player'
            OR victimId='$player')
        GROUP BY
            map
        ORDER BY
            $tblMaps->sort $tblMaps->sortorder,
            $tblMaps->sort2 $tblMaps->sortorder
    ");

    $numitems = ($result) ? (int)$db->num_rows($result) : 0;
    $tblMaps->draw($result, $numitems, 100);
    if ($result) {
        $db->free_result($result);
    }
?>