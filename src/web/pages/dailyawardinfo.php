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

    // Daily Award Statistics

    // PHP 8 Fix: Null coalescing and type casting
    $award_in = isset($_GET['award']) ? (int)$_GET['award'] : 0;
    $award = valid_request($award_in, true);

    if (!$award || $award <= 0) {
        error('No award ID specified.');
    }

    // Initialize variables
    $game = isset($game) ? (string)$game : '';
    $game_esc = $db->escape($game);
    $game_url = urlencode($game);
    $scripturl = htmlspecialchars((string)($g_options['scripturl'] ?? ''), ENT_QUOTES, 'UTF-8');

    $db->query("
        SELECT
            awardType,
            code,
            name,
            verb
        FROM
            hlstats_Awards
        WHERE
            hlstats_Awards.awardid=$award
    ");

    // PHP 8 Fix: Safe fetching
    $awarddata = $db->fetch_array();
    if (!$awarddata) {
        error('Invalid award specified.');
    }

    $db->free_result();

    // PHP 8 Fix: Ensure variables are defined
    $awardname = isset($awarddata['name']) ? (string)$awarddata['name'] : '';
    $awardverb = isset($awarddata['verb']) ? (string)$awarddata['verb'] : '';
    $awardtype = isset($awarddata['awardType']) ? (string)$awarddata['awardType'] : '';
    $awardcode = isset($awarddata['code']) ? (string)$awarddata['code'] : '';

    $db->query("SELECT name FROM hlstats_Games WHERE code = '$game_esc'");
    if ($db->num_rows() < 1) {
        error("No such game '" . htmlspecialchars($game, ENT_QUOTES, 'UTF-8') . "'.");
    }

    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row();
    $gamename = ($row) ? (string)$row[0] : ucfirst($game);
    $db->free_result();

    pageHeader(
        array($gamename, 'Award Details', $awardname),
        array(
            $gamename => $scripturl . "?game=$game_url",
            'Awards Statistics' => $scripturl . "?mode=awards&amp;game=$game_url",
            'Awards Details' => ''
        ),
        $awardname
    );

    $table = new Table(
        array(
            new TableColumn(
                'awardTime',
                'Day',
                'width=20&align=left'
            ),
            new TableColumn(
                'lastName',
                'Player',
                'width=40&align=left&flag=1&link=' . urlencode('mode=playerinfo&amp;player=%k')
            ),
            new TableColumn(
                'count',
                'Count for the Day',
                'width=35&align=right&append=' . urlencode(" $awardverb")
            )
        ),
        'playerId',
        'awardTime',
        'lastName',
        true,
        30
    );


    $result = $db->query("
        SELECT
            hlstats_Players_Awards.playerId,
            hlstats_Players_Awards.awardTime,
            unhex(replace(hex(hlstats_Players.lastName), 'E280AE', '')) AS lastName,
            hlstats_Players.flag,
            hlstats_Players_Awards.count
        FROM
            hlstats_Players_Awards
        LEFT JOIN
            hlstats_Players
        ON
            (hlstats_Players_Awards.playerId = hlstats_Players.playerId AND hlstats_Players_Awards.game = hlstats_Players.game)
        WHERE
            hlstats_Players_Awards.awardId = $award
        ORDER BY
            $table->sort $table->sortorder,
            $table->sort2 $table->sortorder
        LIMIT $table->startitem, $table->numperpage
    ");


    $resultCount = $db->query("
        SELECT
            COUNT(*)
        FROM
            hlstats_Players_Awards
        WHERE
            awardId = $award
    ");

    $row = $db->fetch_row($resultCount);
    $numitems = ($row) ? (int)$row[0] : 0;
    $db->free_result();

?>

<div class="block">
    <?php printSectionTitle('Daily Award Details'); ?>
    <div class="subblock">
        <div style="float:right;">
            Back to <a href="<?php echo $scripturl . '?mode=awards&amp;game=' . $game_url; ?>">Daily Awards</a>
        </div>
        <div style="clear:both;"></div>
    </div>
    <br /><br />
    <?php
    // PHP 8 Fix: Cast to string for strtolower
    $img_name = strtolower((string)$awardtype) . '_' . strtolower((string)$awardcode) . '.png';
    $img = IMAGE_PATH . "/games/$game_url/dawards/" . $img_name;

    if (!is_file($img))
    {
        $img = IMAGE_PATH . '/award.png';
    }

    // PHP 8 Fix: XSS Protection
    $safe_code = htmlspecialchars((string)$awardcode, ENT_QUOTES, 'UTF-8');
    $safe_name = htmlspecialchars((string)$awardname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo "<img src=\"$img\" alt=\"$safe_code\" style=\"vertical-align:middle; margin-right:4px;\" /> <strong>$safe_name</strong>";
    $table->draw($result, $numitems, 95, 'center');
?>
</div>