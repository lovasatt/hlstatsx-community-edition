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

    // Action Details

    // Addon created by Rufus (rufus@nonstuff.de)
    
    // PHP 8 Fix: Null coalescing and treat action code as string (false parameter)
    $action_in = isset($_GET['action']) ? $_GET['action'] : '';
    $action = valid_request((string)$action_in, false);
    
    if (!$action) {
        error('No action code specified.');
    }
    
    // Security: Escape variables
    $action_esc = $db->escape($action);
    $game_esc = $db->escape($game);

    $db->query("
	SELECT
	    description
	FROM
	    hlstats_Actions
	WHERE
	    code='$action_esc'
	    AND game='$game_esc'
    ");
    
    if ($db->num_rows() != 1) {
	$act_name = ucfirst($action);
    } else {
	$actiondata = $db->fetch_array();
	$db->free_result();
	$act_name = $actiondata['description'];
    }
    
    $db->query("SELECT name FROM hlstats_Games WHERE code='$game_esc'");
    if ($db->num_rows() != 1) {
	error('Invalid or no game specified.');
    } else {
        // PHP 8 Fix: Replace list()
        $row = $db->fetch_row();
	$gamename = ($row) ? $row[0] : '';
    }

    $table = new Table(
	array(
	    new TableColumn(
		'playerName',
		'Player',
		'width=45&align=left&flag=1&link=' . urlencode("mode=playerinfo&amp;player=%k") 
	    ),
	    new TableColumn(
		'obj_count',
		'Achieved',
		'width=25&align=right'
	    ),
	    new TableColumn(
		'obj_bonus',
		'Skill Bonus Total',
		'width=25&align=right&sort=no'
	    )
	),
	'playerId',
	'obj_count',
	'playerName',
	true,
	50
    );
    
    // Optimization: Check counts first to decide which query to run
    // This avoids running a heavy SELECT if we need to switch tables
    $resultCount = $db->query("
	SELECT
	    COUNT(DISTINCT hlstats_Events_PlayerActions.playerId),
	    COUNT(hlstats_Events_PlayerActions.Id)
	FROM
	    hlstats_Events_PlayerActions, hlstats_Players, hlstats_Actions
	WHERE
	    hlstats_Actions.code = '$action_esc' AND
	    hlstats_Players.game = '$game_esc' AND
	    hlstats_Players.playerId = hlstats_Events_PlayerActions.playerId AND
	    hlstats_Events_PlayerActions.actionId = hlstats_Actions.id
    ");
    
    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row($resultCount);
    $numitems = ($row) ? (int)$row[0] : 0;
    $totalact = ($row) ? (int)$row[1] : 0;
    
    // Header must be printed before content
    pageHeader(
	array($gamename, 'Action Details', htmlspecialchars($act_name)),
	array(
	    $gamename => $g_options['scripturl']."?game=$game",
            'Actions' => $g_options['scripturl']."?mode=actions&game=$game",
	    'Action Details' => ''
	),
	$act_name
    );
  
    if ($totalact > 0) {
        // Query PlayerActions
	$result = $db->query("
	    SELECT
		hlstats_Events_PlayerActions.playerId,
		hlstats_Players.lastName AS playerName,
		hlstats_Players.flag as flag,
		COUNT(hlstats_Events_PlayerActions.id) AS obj_count,
		COUNT(hlstats_Events_PlayerActions.id) * hlstats_Actions.reward_player AS obj_bonus
	    FROM
		hlstats_Events_PlayerActions, hlstats_Players, hlstats_Actions
	    WHERE
		hlstats_Actions.code = '$action_esc' AND
		hlstats_Players.game = '$game_esc' AND
		hlstats_Players.playerId = hlstats_Events_PlayerActions.playerId AND
		hlstats_Events_PlayerActions.actionId = hlstats_Actions.id AND
		hlstats_Players.hideranking<>'1'
	    GROUP BY
		hlstats_Events_PlayerActions.playerId,
		hlstats_Actions.reward_player
	    ORDER BY
		$table->sort $table->sortorder,
		$table->sort2 $table->sortorder
	    LIMIT $table->startitem,$table->numperpage
	");
    } else {
        // Query TeamBonuses if no PlayerActions found
        $resultCount = $db->query("
            SELECT
                COUNT(DISTINCT hlstats_Events_TeamBonuses.playerId),
                COUNT(hlstats_Events_TeamBonuses.Id)
            FROM
                hlstats_Events_TeamBonuses, hlstats_Players, hlstats_Actions
            WHERE
                hlstats_Actions.code = '$action_esc' AND
                hlstats_Players.game = '$game_esc' AND
                hlstats_Players.playerId = hlstats_Events_TeamBonuses.playerId AND
                hlstats_Events_TeamBonuses.actionId = hlstats_Actions.id
        ");
        
        $row = $db->fetch_row($resultCount);
        $numitems = ($row) ? (int)$row[0] : 0;
        
        $result = $db->query("
            SELECT
                hlstats_Events_TeamBonuses.playerId,
                hlstats_Players.lastName AS playerName,
                hlstats_Players.flag as flag,
                COUNT(hlstats_Events_TeamBonuses.id) AS obj_count,
                COUNT(hlstats_Events_TeamBonuses.id) * hlstats_Actions.reward_player AS obj_bonus
            FROM
                hlstats_Events_TeamBonuses, hlstats_Players, hlstats_Actions
            WHERE
                hlstats_Actions.code = '$action_esc' AND
                hlstats_Players.game = '$game_esc' AND
                hlstats_Players.playerId = hlstats_Events_TeamBonuses.playerId AND
                hlstats_Events_TeamBonuses.actionId = hlstats_Actions.id AND
                hlstats_Players.hideranking<>'1'
            GROUP BY
                hlstats_Events_TeamBonuses.playerId,
		hlstats_Actions.reward_player
            ORDER BY
                $table->sort $table->sortorder,
                $table->sort2 $table->sortorder
            LIMIT $table->startitem,$table->numperpage
        ");
    }    
    
    $table->draw($result, $numitems, 100, 'center');
?>