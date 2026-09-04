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

// Global Server Chat History
    $showserver = 0;
    if (isset($_GET['server_id'])) {
        // PHP 8 Fix: Explicit string cast
        $showserver = max(0, (int)$_GET['server_id']);
    }

    // Security: Escape game variable
    $game = isset($game) ? (string)$game : '';
    $game_esc = $db->escape($game);
    $game_url = urlencode($game);
    $scripturl = htmlspecialchars((string)($g_options['scripturl'] ?? ''), ENT_QUOTES, 'UTF-8');
    $deletedays = (int)($g_options['DeleteDays'] ?? 28);
    $showserver_esc = (int)$showserver;

    if ($showserver == 0) {
        $whereclause = "hlstats_Servers.game='$game_esc'";
    } else {
        $whereclause = "hlstats_Servers.game='$game_esc' AND hlstats_Events_Chat.serverId=$showserver_esc";
    }

    $res_game = $db->query("
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
    $gamename = ($row) ? (string)$row[0] : ucfirst($game);

    $db->free_result($res_game);

    pageHeader(
        array ($gamename, 'Server Chat Statistics'),
        array ($gamename => "%s?game=$game_url", 'Server Chat Statistics' => '')
    );

    flush();

    $servername = "(All Servers)";

    if ($showserver != 0)
    {
        $res_srv = $db->query
            ("
                SELECT
                    hlstats_Servers.name
                FROM
                    hlstats_Servers
                WHERE
                    hlstats_Servers.serverId = $showserver_esc
            ");

        // PHP 8 Fix: Fetch array safely
        $server_info = $db->fetch_array($res_srv);
        if ($server_info) {
            $servername = "(" . htmlspecialchars((string)$server_info['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ")";
        }
        $db->free_result($res_srv);
    }
?>

<div class="block">
    <?php printSectionTitle("$gamename $servername Server Chat Log (Last " . $deletedays . ' Days)'); ?>
    <div class="subblock">
        <div style="float:left;">
            <span>
            <form method="get" action="<?php echo $scripturl; ?>" style="margin:0px;padding:0px;">
                <input type="hidden" name="mode" value="chat" />
                <input type="hidden" name="game" value="<?php echo htmlspecialchars($game, ENT_QUOTES, 'UTF-8'); ?>" />
                <strong>&#8226;</strong> Show Chat from
                <?php

                    $res_srvlist = $db->query
                    ("
                        SELECT
                            hlstats_Servers.serverId,
                            hlstats_Servers.name
                        FROM
                            hlstats_Servers
                        WHERE
                            hlstats_Servers.game='$game_esc'
                        ORDER BY
                            hlstats_Servers.sortorder,
                            hlstats_Servers.name,
                            hlstats_Servers.serverId ASC
                        LIMIT
                            0,
                            50
                    ");

                    echo '<select name="server_id"><option value="0">All Servers</option>';
                    $dates = array();
                    $serverids = array();
                    while ($rowdata = $db->fetch_array($res_srvlist))
                    {
                        $serverids[] = $rowdata['serverId'];
                        $dates[] = $rowdata;
                        if ($showserver == $rowdata['serverId'])
                            echo '<option value="' . (int)$rowdata['serverId'] . '" selected="selected">' . htmlspecialchars((string)$rowdata['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
                        else
                            echo '<option value="' . (int)$rowdata['serverId'] . '">' . htmlspecialchars((string)$rowdata['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
                    }
                    echo '</select>';
                    $db->free_result($res_srvlist);
                    // PHP 8 Fix: Null coalescing
                    $filter = (isset($_REQUEST['filter']) && !is_array($_REQUEST['filter'])) ? trim((string)$_REQUEST['filter']) : "";
                ?>
                Filter: <input type="text" name="filter" value="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="submit" value="View" class="smallsubmit" />
            </form>
            </span>
        </div>
    </div>
    <div style="clear:both;padding-top:20px;"></div>
        <?php
            if ($showserver == 0)
            {
                $table = new Table(
                    array
                    (
                        new TableColumn
                        (
                            'eventTime',
                            'Date',
                            'width=16'
                        ),
                        new TableColumn
                        (
                            'lastName',
                            'Player',
                            'width=17&sort=no&flag=1&link=' . urlencode('mode=playerinfo&player=%k')
                        ),
                        new TableColumn
                        (
                            'message',
                            'Message',
                            'width=34&sort=no&embedlink=yes'
                        ),
                        new TableColumn
                        (
                            'serverName',
                            'Server',
                            'width=23&sort=no'
                        ),
                        new TableColumn
                        (
                            'map',
                            'Map',
                            'width=10&sort=no'
                        )
                    ),
                    'playerId',
                    'eventTime',
                    'lastName',
                    false,
                    50,
                    "page",
                    "sort",
                    "sortorder"
                );
            }
            else
            {
                $table = new Table(
                    array
                    (
                        new TableColumn
                        (
                            'eventTime',
                            'Date',
                            'width=16'
                        ),
                        new TableColumn
                        (
                            'lastName',
                            'Player',
                            'width=24&sort=no&flag=1&link=' . urlencode('mode=playerinfo&player=%k')
                        ),
                        new TableColumn
                        (
                            'message',
                            'Message',
                            'width=44&sort=no&embedlink=yes'
                        ),
                        new TableColumn
                        (
                            'map',
                            'Map',
                            'width=16&sort=no'
                        )
                    ),
                    'playerId',
                    'eventTime',
                    'lastName',
                    false,
                    50,
                    "page",
                    "sort",
                    "sortorder"
                );
            }
            $whereclause2='';
            if(!empty($filter))
            {
                $whereclause2="AND MATCH (hlstats_Events_Chat.message) AGAINST ('" . $db->escape($filter) . "' in BOOLEAN MODE)";
            }
            $surl = $scripturl;

            $result = $db->query
            ("
                SELECT SQL_NO_CACHE
                    hlstats_Events_Chat.eventTime,
                    unhex(replace(hex(hlstats_Players.lastName), 'E280AE', '')) as lastName,
                    IF(hlstats_Events_Chat.message_mode=2, CONCAT('(Team) ', hlstats_Events_Chat.message), IF(hlstats_Events_Chat.message_mode=3, CONCAT('(Squad) ', hlstats_Events_Chat.message), hlstats_Events_Chat.message)) AS message,
                    hlstats_Servers.name AS serverName,
                    hlstats_Events_Chat.playerId,
                    hlstats_Players.flag,
                    hlstats_Events_Chat.map
                FROM
                    hlstats_Events_Chat
                INNER JOIN
                    hlstats_Players
                ON
                    hlstats_Players.playerId = hlstats_Events_Chat.playerId
                INNER JOIN
                    hlstats_Servers
                ON
                    hlstats_Servers.serverId = hlstats_Events_Chat.serverId
                WHERE
                    $whereclause $whereclause2
                ORDER BY
                    hlstats_Events_Chat.id $table->sortorder
                LIMIT
                    $table->startitem,
                    $table->numperpage;
            ", true, false);

            $res_count = $db->query
            ("
                SELECT
                    COUNT(*)
                FROM
                    hlstats_Events_Chat
                INNER JOIN
                    hlstats_Players
                ON
                    hlstats_Players.playerId = hlstats_Events_Chat.playerId
                INNER JOIN
                    hlstats_Servers
                ON
                    hlstats_Servers.serverId = hlstats_Events_Chat.serverId
                WHERE
                    $whereclause $whereclause2
            ");
            if ($db->num_rows($res_count) < 1) {
                $numitems = 0;
            } else {
                // PHP 8 Fix: Replace list()
                $row = $db->fetch_row($res_count);
                $numitems = ($row) ? (int)$row[0] : 0;
            }
            $db->free_result($res_count);

            $table->draw($result, $numitems, 95);
        ?><br /><br />
    <div class="subblock">
        <div style="float:right;">
            Go to: <a href="<?php echo $scripturl . '?game=' . $game_url; ?>"><?php echo htmlspecialchars($gamename, ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
        <div style="clear:both;"></div>
    </div>
</div>