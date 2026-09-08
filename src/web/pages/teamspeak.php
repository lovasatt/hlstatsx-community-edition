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

$game = isset($game) ? (string)$game : '';
$game_url = urlencode($game);

pageHeader(
    array('Teamspeak viewer'),
    array('Teamspeak viewer' => '')
);

include_once(PAGE_PATH . '/voicecomm_serverlist.php');
include_once(PAGE_PATH . '/teamspeak_query.php');

$tsId = isset($_GET['tsId']) ? (int)$_GET['tsId'] : 0;

if (!function_exists('show')) {
    function show($tpl, $array)
    {
        $template_file = PAGE_PATH . "/templates/teamspeak/{$tpl}.html";
        $tpl_content = '';

        if (file_exists($template_file) && is_readable($template_file)) {
            $tpl_content = (string)@file_get_contents($template_file);
        }

        if ($tpl_content !== '') {
            foreach ($array as $value => $code) {
                $tpl_content = str_replace("[" . $value . "]", (string)$code, $tpl_content);
            }
        }
        return $tpl_content;
    }
}

if ($tsId <= 0) {
    echo "<div class=\"warning\"><span id=\"warning-header\"><strong>Error:</strong> Invalid Teamspeak Server ID.</span></div><br />\n";
    pageFooter();
    die();
}

$result = $db->query("SELECT addr, queryPort, UDPPort, name FROM hlstats_Servers_VoiceComm WHERE serverId=$tsId LIMIT 1");
$s = false;
if ($result) {
    $s = $db->fetch_array($result);
    $db->free_result($result);
}

if (!$s) {
    echo "<div class=\"warning\"><span id=\"warning-header\"><strong>Error:</strong> Server not found.</span></div><br />\n";
    pageFooter();
    die();
}

global $teamspeakDisplay;
if (!isset($teamspeakDisplay)) {
    require_once(PAGE_PATH . '/teamspeak_class.php');
    $teamspeakDisplay = new teamspeakDisplayClass;
}

$uip   = (string)$s['addr'];
$tPort = (int)$s['queryPort'];
$port  = (int)$s['UDPPort'];

// Query server via universal dual-engine
$settings = $teamspeakDisplay->getDefaultSettings();
$settings['serveraddress']   = $uip;
$settings['serverqueryport'] = $tPort;
$settings['serverudpport']   = $port;
$ts_info = $teamspeakDisplay->queryTeamspeakServerEx($settings);

if (isset($ts_info['queryerror']) && (int)$ts_info['queryerror'] !== 0) {
    echo "<div class=\"warning\"><span id=\"warning-header\"><strong>Error:</strong> Could not connect to Teamspeak Server (Error code: " . (int)$ts_info['queryerror'] . ").</span></div><br />\n";
} else {
    // Determine Protocol: TS3 vs TS2
    if (!empty($teamspeakDisplay->is_ts3)) {
        // --- TEAMSPEAK 3 RENDERING ---
        $name    = htmlspecialchars($ts_info['serverinfo']['server_name'] ?? $s['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $os      = htmlspecialchars($ts_info['serverinfo']['server_platform'] ?? 'Linux', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $uptime  = (int)($ts_info['serverinfo']['server_uptime'] ?? 0);
        $channels_arr = $ts_info['channellist'] ?? [];
        $players_arr  = $ts_info['playerlist'] ?? [];
        $cAmount = count($channels_arr);
        $user    = count($players_arr);
        $max     = (int)($ts_info['serverinfo']['server_maxusers'] ?? 32);

        // Build TS3 Channel & Player Tree
        $chan = "";
        if (!empty($channels_arr)) {
            foreach ($channels_arr as $ch) {
                $cid = $ch['channelid'];
                $cname = htmlspecialchars($ch['channelname'] ?? 'Channel', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $subusers = "";

                foreach ($players_arr as $pl) {
                    if ($pl['channelid'] == $cid) {
                        $pname = htmlspecialchars($pl['playername'] ?? 'Player', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $subusers .= "<img src=\"" . IMAGE_PATH . "/teamspeak/trenner.gif\" alt=\"\" class=\"tsicon\" />"
                                   . "<img src=\"" . IMAGE_PATH . "/teamspeak/user.gif\" alt=\"\" class=\"tsicon\" />&nbsp;"
                                   . "<span style=\"font-weight:bold;\">" . $pname . "</span><br />";
                    }
                }

                $chan .= "<img src=\"" . IMAGE_PATH . "/teamspeak/channel.gif\" alt=\"\" class=\"tsicon\" />&nbsp;"
                       . "<strong>" . $cname . "</strong><br />" . $subusers;
            }
        } else {
            $chan = "No channels found.";
        }

        $info = "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\" class=\"fHeading\">Server:</td></tr>\n"
              . "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\">" . $name . "<br /><br /></td></tr>\n"
              . "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\" class=\"fHeading\">Server Address:</td></tr>\n"
              . "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\">" . htmlspecialchars($uip . ':' . $port, ENT_QUOTES, 'UTF-8') . "<br /><br /></td></tr>\n"
              . "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\" class=\"fHeading\">Type:</td></tr>\n"
              . "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\">TeamSpeak 3 Server<br /><br /></td></tr>\n";

        $outp_str = show("teamspeak", array(
            "name"          => $name,
            "os"            => $os,
            "uptime"        => time_convert($uptime),
            "user"          => $user,
            "t_name"        => "Server name",
            "t_os"          => "Operating system",
            "uchannels"     => $chan,
            "info"          => $info,
            "t_uptime"      => "Uptime",
            "t_channels"    => "Channels",
            "t_user"        => "Users",
            "head"          => "Teamspeak Overview",
            "users_head"    => "User Information",
            "player"        => "User",
            "channel"       => "Channel",
            "channel_head"  => "Channel Information",
            "max"           => $max,
            "channels"      => $cAmount,
            "logintime"     => "Login time",
            "idletime"      => "Idle time",
            "channelstats"  => "",
            "userstats"     => ""
        ));

        echo $outp_str;

    } else {
        // --- LEGACY TEAMSPEAK 2 RENDERING ---
        $name    = htmlspecialchars($ts_info['serverinfo']['server_name'] ?? 'Unknown', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $os      = htmlspecialchars($ts_info['serverinfo']['server_platform'] ?? 'Unknown', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $uptime  = (int)($ts_info['serverinfo']['server_uptime'] ?? 0);
        $cAmount = count($ts_info['channellist'] ?? []);
        $user    = count($ts_info['playerlist'] ?? []);
        $max     = (int)($ts_info['serverinfo']['server_maxusers'] ?? 0);

        $cID  = isset($_GET['cID']) ? (int)$_GET['cID'] : 0;
        $type = isset($_GET['type']) ? (int)$_GET['type'] : 0;

        $info = ($type == 1) ? channelInfo($uip, $tPort, $port, $cID) : defaultInfo($uip, $tPort, $port);

        $outp_str = show("teamspeak", array(
            "name"          => $name,
            "os"            => $os,
            "uptime"        => time_convert($uptime),
            "user"          => $user,
            "t_name"        => "Server name",
            "t_os"          => "Operating system",
            "uchannels"     => "",
            "info"          => $info,
            "t_uptime"      => "Uptime",
            "t_channels"    => "Channels",
            "t_user"        => "Users",
            "head"          => "Teamspeak Overview",
            "users_head"    => "User Information",
            "player"        => "User",
            "channel"       => "Channel",
            "channel_head"  => "Channel Information",
            "max"           => $max,
            "channels"      => $cAmount,
            "logintime"     => "Login time",
            "idletime"      => "Idle time",
            "channelstats"  => "",
            "userstats"     => ""
        ));

        echo $outp_str;
    }
}
?>