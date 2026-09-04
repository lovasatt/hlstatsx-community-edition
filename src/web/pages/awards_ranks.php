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
    // Security: Escape game variable
    $game_esc = $db->escape($game);
    $game_url = urlencode($game);

    $res_counts = $db->query("
        SELECT
            hlstats_Ranks.rankName,
            hlstats_Ranks.minKills,
            hlstats_Ranks.rankId,
            COUNT(hlstats_Players.playerId) AS obj_count
        FROM
            hlstats_Ranks
        INNER JOIN
            hlstats_Players
        ON (
            hlstats_Ranks.game = hlstats_Players.game
            )
        WHERE
            hlstats_Players.kills >= hlstats_Ranks.minKills
            AND hlstats_Players.kills <= hlstats_Ranks.maxKills
            AND hlstats_Ranks.game = '$game_esc'
            AND hlstats_Players.hideranking = 0
        GROUP BY
            hlstats_Ranks.rankId,
            hlstats_Ranks.rankName,
            hlstats_Ranks.minKills
    ");

    $ranks = array();
    while ($r = $db->fetch_array($res_counts))
    {
        $ranks[$r['rankId']] = (int)$r['obj_count'];
    }
    $db->free_result($res_counts);

    // select the available ranks
    $res_ranks = $db->query("
        SELECT
            rankName,
            minKills,
            maxKills,
            rankId,
            image
        FROM
            hlstats_Ranks
        WHERE
            hlstats_Ranks.game='$game_esc'
        ORDER BY
            minKills ASC
    ");
?>

<div class="block">
    <?php printSectionTitle('Ranks'); ?>
    <div class="subblock">
        <table class="data-table">
<?php
    // draw the rank info table (5 columns)
    $i = 0;

    // PHP 8 Fix: Ensure integer and key existence
    $cols = isset($g_options['awardrankscols']) ? (int)$g_options['awardrankscols'] : 5;
    if ($cols < 1 || $cols > 10) {
        $cols = 5;
    }

    $colwidth = round(100 / $cols);

    while ($r = $db->fetch_array($res_ranks))
    {
        if ($i == $cols)
        {
            echo "</tr>";
            $i = 0;
        }
        if ($i == 0)
        {
            echo "<tr class='bg1'>";
        }

        $rank_id = (int)$r['rankId'];
        $link = '<a href="hlstats.php?mode=rankinfo&amp;rank=' . $rank_id . "&amp;game=$game_url\">";
        $r_image = (string)($r['image'] ?? '');
        $image = getImage('/ranks/' . $r_image . '_small');

        if ($image && !empty($image['url']))
        {
            $imagestring = '<img src="' . htmlspecialchars((string)$image['url'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($r_image, ENT_QUOTES, 'UTF-8') . '" />';
        }
        else
        {
            $imagestring = 'Player List';
        }

        $achvd = '';
        // PHP 8 Fix: Check if key exists using Null Coalescing
        $player_count = (int)($ranks[$r['rankId']] ?? 0);

        if ($player_count > 0)
        {
            $imagestring = "$link$imagestring</a>";
            $achvd = 'Achieved by ' . number_format($player_count) . ' Players';
        }

        echo "<td style=\"text-align:center;vertical-align:top;width:$colwidth%;\">"
            .'<strong>'.htmlspecialchars((string)($r['rankName'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</strong><br />'
            .'<span class="fSmall">('.(int)($r['minKills'] ?? 0).'-'.(int)($r['maxKills'] ?? 0).'&nbsp;kills)'.'<br />'
            ."$achvd<br /></span>"
            .$imagestring.'
            </td>';
        $i++;
    }
    if ($i != 0)
    {
        for (; $i < $cols; $i++)
        {
            echo '<td class="bg1">&nbsp;</td>';
        }
        echo '</tr>';
    }
    $db->free_result($res_ranks);
?>
        </table>
    </div>
</div>