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

  // Rank Details
  $rank = isset($_GET['rank']) ? (int)$_GET['rank'] : 0;

  if ($rank <= 0) {
      error('No rank ID specified.');
  }

  // Security: Escape game
  $game = isset($game) ? (string)$game : '';
  $game_esc = $db->escape($game);
  $game_url = urlencode($game);
  $scripturl = htmlspecialchars((string)($g_options['scripturl'] ?? ''), ENT_QUOTES, 'UTF-8');

  $db->query("
      SELECT
          rankName,
          image,
          minKills,
          maxKills
      FROM
          hlstats_Ranks
      WHERE
          rankId = $rank
  ");

  if ($db->num_rows() != 1) {
      error("No such rank '$rank'.");
  }

  $rankrow = $db->fetch_array();
  $act_name = (string)($rankrow['rankName'] ?? '');

  $db->query("SELECT name FROM hlstats_Games WHERE code = '$game_esc'");
  if ($db->num_rows() != 1) {
      error('Invalid or no game specified.');
  }

  $row = $db->fetch_row();
  $gamename = ($row) ? (string)$row[0] : '';
    pageHeader(
      array($gamename, 'Rank Details', $act_name),
      array(
          $gamename => $scripturl . "?game=$game_url",
          'Ranks' => $scripturl . "?mode=awards&amp;game=$game_url&amp;tab=ranks",
          'Rank Details' => ''
      ),
      $act_name
  );
    
    $table = new Table(
	array(
	    new TableColumn(
		'playerName',
		'Player',
		'width=45&align=left&flag=1&link=' . urlencode('mode=playerinfo&amp;player=%k') 
	    ),
	    new TableColumn(
		'kills',
		'Kills',
		'width=25&align=right'
	    ),
	    new TableColumn(
		'skill',
		'Skill',
		'width=25&align=right'
	    )
	),
	'playerId',
	'skill',
	'playerName',
	true,
	50
    );

  $minKills = (int)($rankrow['minKills'] ?? 0);
  $maxKills = (int)($rankrow['maxKills'] ?? 0);

  $result = $db->query("
      SELECT
          skill,
          kills,
          flag,
          unhex(replace(hex(lastName), 'E280AE', '')) AS playerName,
          playerId
      FROM
          hlstats_Players
      WHERE
          game = '$game_esc'
          AND hideranking = 0
          AND kills >= $minKills
          AND kills <= $maxKills
      ORDER BY
          $table->sort $table->sortorder,
          $table->sort2 $table->sortorder
      LIMIT $table->startitem, $table->numperpage
  ");

  $resultCount = $db->query("
      SELECT
          COUNT(playerId)
      FROM
          hlstats_Players
      WHERE
          game = '$game_esc'
          AND hideranking = 0
          AND kills >= $minKills
          AND kills <= $maxKills
  ");

    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row($resultCount);
    $numitems = ($row) ? (int)$row[0] : 0;
?>

<div class="block">
    <?php printSectionTitle('Rank Details'); ?>
    <div class="subblock">
        <div style="float:right;">
            Back to <a href="<?php echo $scripturl . '?mode=awards&amp;game=' . $game_url . '&amp;tab=ranks'; ?>">Ranks</a>
        </div>
        <div style="clear:both;"></div>
    </div>
    <br /><br />
    <div style="text-align:center; margin-bottom: 15px;">
<?php
    if (!empty($rankrow['image'])) {
        $image = getImage('/ranks/' . (string)$rankrow['image']);
        if ($image && !empty($image['url'])) {
            echo '<img src="' . htmlspecialchars((string)$image['url'], ENT_QUOTES, 'UTF-8') . '" alt="" style="vertical-align:middle; margin-right:6px;" />';
        }
    }
    echo '<strong style="font-size:14px; vertical-align:middle;">' . htmlspecialchars($act_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>';
?>
    </div>
<?php
    $table->draw($result, $numitems, 95, 'center');
?>
</div>
