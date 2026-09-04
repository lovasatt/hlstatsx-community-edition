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

// Action Statistics
// Addon Created by Rufus (rufus@nonstuff.de)

    $game = (string)($game ?? '');
    // Security: Escape game variable
    $game_esc = $db->escape($game);

    $res_game = $db->query
    ("
        SELECT
            hlstats_Games.name
        FROM
            hlstats_Games
        WHERE
            hlstats_Games.code = '$game_esc'
    ");

    if ($db->num_rows($res_game) < 1) {
        error("No such game '" . htmlspecialchars($game, ENT_QUOTES, 'UTF-8') . "'.");
    }

    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row($res_game);
    $gamename = ($row) ? (string)$row[0] : '';
    $db->free_result($res_game);

    pageHeader
    (
        array ($gamename, 'Action Statistics'),
        array ($gamename=>"%s?game=$game", 'Action Statistics'=>'')
    );

    $tblPlayerActions = new Table
    (
        array
        (
            new TableColumn
            (
                'description',
                'Action',
                'width=45&link=' . urlencode('mode=actioninfo&action=%k&game=' . urlencode($game))
            ),
            new TableColumn
            (
                'obj_count',
                'Earned',
                'width=25&align=right&append=+times'
            ),
            new TableColumn
            (
                'obj_bonus',
                'Reward',
                'width=25&align=right'
            )
        ),
        'code',
        'obj_count',
        'description',
        true,
        9999,
        'obj_page',
        'obj_sort',
        'obj_sortorder'
    );

    // Fetch total actions count before rendering HTML
    $res_total = $db->query
    ("
        SELECT
            SUM(count)
        FROM
            hlstats_Actions
        WHERE
            hlstats_Actions.game = '$game_esc'
    ");
    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row($res_total);
    $totalactions = ($row) ? (int)$row[0] : 0;
    $db->free_result($res_total);

    $result = $db->query("
        SELECT
            hlstats_Actions.code,
            hlstats_Actions.description,
            hlstats_Actions.count AS obj_count,
            hlstats_Actions.reward_player AS obj_bonus
        FROM
            hlstats_Actions
        WHERE
            hlstats_Actions.game = '$game_esc'
            AND hlstats_Actions.count > 0
        GROUP BY
            hlstats_Actions.id
        ORDER BY
            $tblPlayerActions->sort $tblPlayerActions->sortorder,
            $tblPlayerActions->sort2 $tblPlayerActions->sortorder
    ");
?>
<div class="block">
    <?php printSectionTitle('Action Statistics'); ?>
    <div class="subblock">
        From a total of <strong><?php echo number_format($totalactions); ?></strong> earned actions
    </div><br /><br />
    <?php
        $tblPlayerActions->draw($result, $db->num_rows($result), 95);
    ?><br /><br />
    <div class="subblock">
        <div style="float:right;">
            Go to: <a href="<?php echo htmlspecialchars($g_options['scripturl'] ?? '', ENT_QUOTES, 'UTF-8'); ?>?game=<?php echo htmlspecialchars($game, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($gamename, ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
        <div style="clear:both;"></div>
    </div>
</div>