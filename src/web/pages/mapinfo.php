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

    // Map Details
    $map_in = isset($_GET['map']) ? (string)$_GET['map'] : '';
    $map = valid_request($map_in, false);

    if (empty($map)) {
        error('No map specified.');
    }

    // Initialize variables
    $game = isset($game) ? (string)$game : '';
    $game_esc = $db->escape($game);
    $game_url = urlencode($game);
    $map_esc  = $db->escape((string)$map);
    $map_url  = urlencode((string)$map);
    $scripturl = htmlspecialchars((string)($g_options['scripturl'] ?? ''), ENT_QUOTES, 'UTF-8');
    $deletedays = (int)($g_options['DeleteDays'] ?? 28);

    // Fetch Name and Realgame (needed for image logic)
    $db->query("SELECT name, realgame FROM hlstats_Games WHERE code = '$game_esc'");
    if ($db->num_rows() != 1) {
        error("No such game '" . htmlspecialchars($game, ENT_QUOTES, 'UTF-8') . "'.");
    }

    $row = $db->fetch_row();
    $gamename = ($row) ? (string)$row[0] : ucfirst($game);
    $realgame = ($row) ? (string)$row[1] : $game;
    $db->free_result();

    pageHeader(
        array($gamename, 'Map Details', $map),
        array(
            $gamename => $scripturl . "?game=$game_url",
            'Map Statistics' => $scripturl . "?mode=maps&amp;game=$game_url",
            'Map Details' => ''
        ),
        $map
    );

    $table = new Table(
        array(
            new TableColumn(
                'killerName',
                'Player',
                'width=50&align=left&flag=1&link=' . urlencode('mode=playerinfo&amp;player=%k')
            ),
            new TableColumn(
                'frags',
                "Kills on " . htmlspecialchars($map, ENT_QUOTES, 'UTF-8'),
                'width=25&align=right'
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
            )
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
            COUNT(hlstats_Events_Frags.id) AS frags,
            IFNULL(SUM(hlstats_Events_Frags.headshot = 1), 0) AS headshots,
            ROUND(IFNULL(SUM(hlstats_Events_Frags.headshot = 1) / COUNT(hlstats_Events_Frags.id), 0), 2) AS hpk
        FROM
            hlstats_Events_Frags
        INNER JOIN
            hlstats_Players
            ON hlstats_Players.playerId = hlstats_Events_Frags.killerId
        WHERE
            hlstats_Events_Frags.map = '$map_esc'
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
            COUNT(hlstats_Events_Frags.id)
        FROM
            hlstats_Events_Frags
        INNER JOIN
            hlstats_Servers
            ON hlstats_Servers.serverId = hlstats_Events_Frags.serverId
        WHERE
            hlstats_Events_Frags.map = '$map_esc'
            AND hlstats_Servers.game = '$game_esc'
    ");

    $row = $db->fetch_row($resultCount);
    $numitems   = ($row) ? (int)$row[0] : 0;
    $totalkills = ($row) ? (int)$row[1] : 0;
?>

<div class="block">
    <?php printSectionTitle('Map Details'); ?>
    <div class="subblock">
        <div style="float:left;">
            <strong><?php echo htmlspecialchars($map, ENT_QUOTES, 'UTF-8'); ?></strong>: From a total of <strong><?php echo number_format($totalkills); ?></strong> kills (Last <?php echo $deletedays; ?> Days)
        </div>
        <div style="float:right;">
            Back to <a href="<?php echo $scripturl . '?mode=maps&amp;game=' . $game_url; ?>">Map Statistics</a>
        </div>
        <div style="clear:both;"></div>
    </div>
</div>
<br /><br />
<div class="block">
<?php
    $mapimg = '';
    if ($img_data = getImage("/games/$game_url/maps/$map")) {
        $mapimg = (string)$img_data['url'];
    } elseif (!empty($realgame) && ($img_data = getImage("/games/" . urlencode($realgame) . "/maps/$map"))) {
        $mapimg = (string)$img_data['url'];
    } else {
        $mapimg = IMAGE_PATH . "/nomap.png";
    }

    $heatmap = getImage("/games/$game_url/heatmaps/$map-kill");
    $heatmapthumb = getImage("/games/$game_url/heatmaps/$map-kill-thumb");
    $map_dlurl_setting = (string)($g_options['map_dlurl'] ?? '');

    if ($mapimg || $map_dlurl_setting !== '' || $heatmap)
    {
?>
    <div class="subblock">
        <div style="float:left;width:74%;">
            <?php $table->draw($result, $numitems, 100, 'center'); ?>
        </div>
        <div style="float:right;width:24%;text-align:center;">
<?php
            if (!empty($mapimg))
            {
                echo "<img src=\"" . htmlspecialchars($mapimg, ENT_QUOTES, 'UTF-8') . "\" alt=\"" . htmlspecialchars($map, ENT_QUOTES, 'UTF-8') . "\" style=\"max-width:100%; height:auto;\" />";
            }

            if ($map_dlurl_setting !== '')
            {
                $map_dlurl = str_replace(array("%MAP%", "%GAME%"), array($map, $game), $map_dlurl_setting);
                $mapdlheader = @get_headers($map_dlurl);

                if ($mapdlheader && isset($mapdlheader[0]) && preg_match("|200|", $mapdlheader[0])) {
                    echo "<p style=\"margin-top:10px;\"><a href=\"" . htmlspecialchars($map_dlurl, ENT_QUOTES, 'UTF-8') . "\">Download this map...</a></p>";
                }
            }

            if ($heatmap && !empty($heatmap['url']) && $heatmapthumb && !empty($heatmapthumb['url']))
            {
                echo "<a href=\"" . htmlspecialchars((string)$heatmap['url'], ENT_QUOTES, 'UTF-8') . "\" rel=\"boxed\" title=\"Heatmap: " . htmlspecialchars($map, ENT_QUOTES, 'UTF-8') . "\"><br /><img src=\"" . htmlspecialchars((string)$heatmapthumb['url'], ENT_QUOTES, 'UTF-8') . "\" alt=\"" . htmlspecialchars($map, ENT_QUOTES, 'UTF-8') . "\" style=\"max-width:100%; height:auto;\" /></a>";
            }
?>
        </div>
        <div style="clear:both;"></div>
    </div>
<?php
    }
    else
    {
        $table->draw($result, $numitems, 95, 'center');
    }
?>
</div>