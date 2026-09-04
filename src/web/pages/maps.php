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

    // Initialize variables
    $game = isset($game) ? (string)$game : '';
    $game_esc = $db->escape($game);
    $game_url = urlencode($game);
    $scripturl = htmlspecialchars((string)($g_options['scripturl'] ?? ''), ENT_QUOTES, 'UTF-8');

    // Map Statistics
    $db->query("
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
    $gamename = ($row) ? (string)$row[0] : ucfirst($game);
    $db->free_result();

    pageHeader(
        array ($gamename, 'Map Statistics'),
        array ($gamename => "%s?game=$game_url", 'Map Statistics' => '')
    );

    $tblMaps = new Table
    (
        array
        (
            new TableColumn
            (
                'map',
                'Map',
                'width=20&align=left&link=' . urlencode("mode=mapinfo&amp;map=%k&amp;game=$game_url")
            ),
            new TableColumn
            (
                'kills',
                'Kills',
                'width=8&align=right'
            ),
            new TableColumn
            (
                'kpercent',
                '%',
                'width=7&sort=no&align=right&append=' . urlencode('%')
            ),
            new TableColumn
            (
                'kpercent',
                'Ratio',
                'width=16&sort=no&type=bargraph'
            ),
            new TableColumn
            (
                'headshots',
                'Headshots',
                'width=8&align=right'
            ),
            new TableColumn(
                'hpercent',
                '%',
                'width=7&sort=no&align=right&append=' . urlencode('%')
            ),
            new TableColumn(
                'hpercent',
                'Ratio',
                'width=16&sort=no&type=bargraph'
            ),
            new TableColumn(
                'hpk',
                'HS:K',
                'width=9&align=right'
            ),
            new TableColumn(
                'map',
                'HeatMap',
                'width=4&type=heatmap'
            )
        ),
        'map',
        'kills',
        'map',
        true,
        9999,
        'maps_page',
        'maps_sort',
        'maps_sortorder'
    );

    $db->query("
        SELECT
            IFNULL(SUM(hlstats_Maps_Counts.kills), 0),
            IFNULL(SUM(hlstats_Maps_Counts.headshots), 0)
        FROM
            hlstats_Maps_Counts
        WHERE
            hlstats_Maps_Counts.game = '$game_esc'
    ");

    $row = $db->fetch_row();
    $totalkills     = ($row) ? (int)$row[0] : 0;
    $totalheadshots = ($row) ? (int)$row[1] : 0;

    $div_realkills     = ($totalkills > 0) ? $totalkills : 1;
    $div_realheadshots = ($totalheadshots > 0) ? $totalheadshots : 1;

    $result = $db->query("
        SELECT
            IF(hlstats_Maps_Counts.map = '', '(Unaccounted)', hlstats_Maps_Counts.map) AS map,
            hlstats_Maps_Counts.kills,
            ROUND(hlstats_Maps_Counts.kills / $div_realkills * 100, 2) AS kpercent,
            hlstats_Maps_Counts.headshots,
            ROUND(hlstats_Maps_Counts.headshots / IF(hlstats_Maps_Counts.kills = 0, 1, hlstats_Maps_Counts.kills), 2) AS hpk,
            ROUND(hlstats_Maps_Counts.headshots / $div_realheadshots * 100, 2) AS hpercent
        FROM
            hlstats_Maps_Counts
        WHERE
            hlstats_Maps_Counts.game = '$game_esc'
        ORDER BY
            $tblMaps->sort $tblMaps->sortorder,
            $tblMaps->sort2 $tblMaps->sortorder
    ");
?>

<div class="block">
    <?php printSectionTitle('Map Statistics'); ?>
    <div class="subblock">
        <div style="float:left;">
            From a total of <strong><?php echo number_format($totalkills); ?></strong> kills with <strong><?php echo number_format($totalheadshots); ?></strong> headshots
        </div>
        <div style="clear:both;"></div>
    </div>
    <br /><br />
    <?php $tblMaps->draw($result, $db->num_rows($result), 95); ?><br /><br />
    <div class="subblock">
        <div style="float:right;">
            Go to: <a href="<?php echo $scripturl . '?game=' . $game_url; ?>"><?php echo htmlspecialchars($gamename, ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
        <div style="clear:both;"></div>
    </div>
</div>