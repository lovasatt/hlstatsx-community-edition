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

    // Ribbon Statistics
    $ribbon = isset($_GET['ribbon']) ? (int)$_GET['ribbon'] : 0;

    if ($ribbon <= 0) {
        error('No ribbon ID specified.');
    }

    $game = isset($game) ? (string)$game : '';
    $game_esc = $db->escape($game);
    $game_url = urlencode($game);
    $scripturl = htmlspecialchars((string)($g_options['scripturl'] ?? ''), ENT_QUOTES, 'UTF-8');

    $db->query("
        SELECT
            ribbonName,
            image,
            awardCode,
            awardCount,
            special
        FROM
            hlstats_Ribbons
        WHERE
            ribbonId = $ribbon
    ");

    if ($db->num_rows() != 1) {
        error("No such ribbon '$ribbon'.");
    }

      $actiondata = $db->fetch_array();
  $db->free_result();

    $act_name   = (string)($actiondata['ribbonName'] ?? '');
    $awardmin   = (int)($actiondata['awardCount'] ?? 0);
    $awardcode  = (string)($actiondata['awardCode'] ?? '');
    $image      = (string)($actiondata['image'] ?? '');
    $special    = (int)($actiondata['special'] ?? 0);
    $awardcode_esc = $db->escape($awardcode);

    $db->query("SELECT name FROM hlstats_Games WHERE code = '$game_esc'");
    if ($db->num_rows() < 1) {
        error("No such game '" . htmlspecialchars($game, ENT_QUOTES, 'UTF-8') . "'.");
    }

    $row = $db->fetch_row();
    $gamename = ($row) ? (string)$row[0] : ucfirst($game);
    $db->free_result();

    pageHeader(
        array($gamename, 'Ribbon Details', $act_name),
        array(
            $gamename => $scripturl . "?game=$game_url",
            'Ribbons' => $scripturl . "?mode=awards&amp;game=$game_url&amp;tab=ribbons",
            'Ribbon Details' => ''
        ),
        $act_name
    );

    $table = new Table(
	array(
	    new TableColumn
	    (
		'playerName',
		'Player',
		'width=45&align=left&flag=1&link=' . urlencode('mode=playerinfo&amp;player=%k')
	    ),
	    new TableColumn
	    (
		'numawards',
		'Daily awards',
		'width=10&align=right&append=' . urlencode(' times')
	    ),
	    new TableColumn
	    (
		'awardName',
		'',
		'width=40&align=left'
	    )
	),
	'playerId',
	'numawards',
	'playerName',
	true,
	50
    );

  if ($special === 1) {
      $whereClause = "
          FROM hlstats_Players
          WHERE game = '$game_esc'
            AND hideranking = 0
            AND headshots >= $awardmin
      ";
      $selectFields = "
          flag,
          unhex(replace(hex(lastName), 'E280AE', '')) AS playerName,
          playerId,
          'Headshots' AS awardName,
          headshots AS numawards
      ";
  } elseif ($special === 2) {
      $awardSeconds = $awardmin * 3600;
      $whereClause = "
          FROM hlstats_Players
          WHERE game = '$game_esc'
            AND hideranking = 0
            AND connection_time >= $awardSeconds
      ";
      $selectFields = "
          flag,
          unhex(replace(hex(lastName), 'E280AE', '')) AS playerName,
          playerId,
          'Connection Hours' AS awardName,
          ROUND(connection_time / 3600, 1) AS numawards
      ";
  } else {
      $whereClause = "
          FROM hlstats_Players
          INNER JOIN hlstats_Players_Awards
              ON (hlstats_Players_Awards.playerId = hlstats_Players.playerId AND hlstats_Players_Awards.game = hlstats_Players.game)
          INNER JOIN hlstats_Awards
              ON (hlstats_Players_Awards.awardId = hlstats_Awards.awardId AND hlstats_Players_Awards.game = hlstats_Awards.game)
          WHERE hlstats_Awards.code = '$awardcode_esc'
            AND hlstats_Players.game = '$game_esc'
            AND hlstats_Players.hideranking = 0
          GROUP BY hlstats_Players.playerId, flag, lastName, hlstats_Awards.name
          HAVING COUNT(hlstats_Awards.name) >= $awardmin
      ";
      $selectFields = "
          flag,
          unhex(replace(hex(lastName), 'E280AE', '')) AS playerName,
          hlstats_Players.playerId,
          hlstats_Awards.name AS awardName,
          COUNT(hlstats_Awards.name) AS numawards
      ";
  }

  $result = $db->query("
      SELECT
          {$selectFields}
          {$whereClause}
      ORDER BY
          $table->sort $table->sortorder,
          $table->sort2 $table->sortorder
      LIMIT $table->startitem, $table->numperpage
  ");

if ($special > 0) {
      $resultCount = $db->query("SELECT COUNT(playerId) {$whereClause}");
      $row = $db->fetch_row($resultCount);
      $numitems = ($row) ? (int)$row[0] : 0;
  } else {
      $resultCount = $db->query("SELECT hlstats_Players.playerId {$whereClause}");
      $numitems = $db->num_rows($resultCount);
  }
?>

<div class="block">
    <?php printSectionTitle('Ribbon Details'); ?>
    <div class="subblock">
        <div style="float:right;">
            Back to <a href="<?php echo $scripturl . '?mode=awards&amp;game=' . $game_url . '&amp;tab=ribbons'; ?>">Ribbons</a>
        </div>
        <div style="clear:both;"></div>
    </div>
    <br /><br />
    <div style="text-align:center; margin-bottom: 15px;">
<?php
    if (!empty($image)) {
        echo '<img src="' . IMAGE_PATH . '/games/' . $game_url . '/ribbons/' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" alt="" style="vertical-align:middle; margin-right:6px;" />';
    }
    echo '<strong style="font-size:14px; vertical-align:middle;">' . htmlspecialchars($act_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>';
?>
    </div>
<?php
    $table->draw($result, $numitems, 95, 'center');
?>
</div>