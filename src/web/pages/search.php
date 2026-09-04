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

    global $game;
    // Search
    require_once(PAGE_PATH . '/search-class.php');

    pageHeader
    (
        array ('Search'),
        array ('Search' => '')
    );

    $sr_query = (isset($_GET['q']) && !is_array($_GET['q'])) ? trim((string)$_GET['q']) : '';

    $st_input = (isset($_GET['st']) && !is_array($_GET['st'])) ? trim((string)$_GET['st']) : 'player';
    $sr_type = valid_request($st_input, false);
    if (!in_array($sr_type, array('player', 'uniqueid', 'ip', 'clan'), true)) {
        $sr_type = 'player';
    }

    $game_default = isset($game) ? (string)$game : '';
    $game_input   = (isset($_GET['game']) && !is_array($_GET['game'])) ? (string)$_GET['game'] : $game_default;
    $sr_game      = valid_request($game_input, false);

    $search = new Search($sr_query, (string)$sr_type, (string)$sr_game);
    $search->drawForm(array('mode' => 'search'));

    if ($sr_query !== '') {
        $search->drawResults();
    }
?>