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

    global $db, $game, $g_options;

    // Map Details

    // PHP 8 Fix: Null coalescing and type casting
    $map_in = $_GET['map'] ?? '';
    $map = valid_request((string)$map_in, false);

    if (!$map) {
        error('No map specified.');
    }

    $game = (string)($game ?? '');

    // Security: Escape variables
    $game_esc = $db->escape($game);
    $map_esc = $db->escape($map);

    $db->query("SELECT name FROM hlstats_Games WHERE code='$game_esc'");
    if ($db->num_rows() != 1) {
        error("No such game '$game'.");
    }

    // PHP 8 Fix: Replace list() which fails on empty result
    $row = $db->fetch_row();
    $gamename = ($row) ? (string)$row[0] : '';
    $db->free_result();

    pageHeader(
        array($gamename, 'Map Details', $map),
        array(
            $gamename => ($g_options['scripturl'] ?? 'hlstats.php') . "?game=" . urlencode($game),
            'Maps' => ($g_options['scripturl'] ?? 'hlstats.php') . "?mode=maps&game=" . urlencode($game),
            'Map Details' => ''
        ),
        $map
    );

    $table = new Table(
        array(
            new TableColumn(
                'killerName',
                'Player',
                'width=60&align=left&flag=1&link=' . urlencode('mode=playerinfo&player=%k')
            ),
            new TableColumn(
                'frags',
                'Kills on ' . htmlspecialchars($map, ENT_QUOTES, 'UTF-8'),
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
            hlstats_Players.flag as flag,
            COUNT(hlstats_Events_Frags.map) AS frags,
            SUM(hlstats_Events_Frags.headshot=1) as headshots,
            IFNULL(ROUND(SUM(hlstats_Events_Frags.headshot=1) / NULLIF(COUNT(hlstats_Events_Frags.map), 0), 2), '-') AS hpk
        FROM
            hlstats_Events_Frags,
            hlstats_Players
        WHERE
            hlstats_Players.playerId = hlstats_Events_Frags.killerId
            AND hlstats_Events_Frags.map='$map_esc'
            AND hlstats_Players.game='$game_esc'
            AND hlstats_Players.hideranking<>'1'
        GROUP BY
            hlstats_Events_Frags.killerId,
            hlstats_Players.lastName,
            hlstats_Players.flag
        ORDER BY
            $table->sort $table->sortorder,
            $table->sort2 $table->sortorder
        LIMIT $table->startitem,$table->numperpage
    ");

    $resultCount = $db->query("
        SELECT
            COUNT(DISTINCT hlstats_Events_Frags.killerId),
            COUNT(hlstats_Events_Frags.id)
        FROM
            hlstats_Events_Frags,
            hlstats_Players
        WHERE
            hlstats_Players.playerId = hlstats_Events_Frags.killerId
            AND hlstats_Events_Frags.map='$map_esc'
            AND hlstats_Players.game='$game_esc'
            AND hlstats_Players.hideranking<>'1'
    ");

    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row($resultCount);
    $numitems = ($row) ? (int)($row[0] ?? 0) : 0;
    $totalkills = ($row) ? (int)($row[1] ?? 0) : 0;
    if ($resultCount) { $db->free_result($resultCount); }
?>

<div class="block">
    <?php printSectionTitle('Map Details: ' . htmlspecialchars((string)$map, ENT_QUOTES, 'UTF-8')); ?>
    <div class="subblock">
        <div style="float:left;">
            From a total of <strong><?php echo number_format((int)$totalkills); ?></strong> kills on <strong><?php echo htmlspecialchars((string)$map, ENT_QUOTES, 'UTF-8'); ?></strong>.
        </div>
        <div style="clear:both;"></div>
    </div>
    <br /><br />
    <?php
        $table->draw($result, $numitems, 100, 'center');
        if ($result) {
            $db->free_result($result);
        }
    ?>
</div>