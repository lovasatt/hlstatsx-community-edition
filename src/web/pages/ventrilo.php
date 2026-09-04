<?php

    if (!defined('IN_HLSTATS')) {
        die('Do not access this file directly.');
    }

    pageHeader(
        array('Ventrilo viewer'),
        array('Ventrilo viewer' => '')
    );

    require_once(PAGE_PATH . '/ventrilostatus.php');
    include_once(PAGE_PATH . '/voicecomm_serverlist.php');

    // PHP 8 Fix: Null coalescing and casting
    $veId_in = isset($_GET['veId']) ? $_GET['veId'] : 0;
    $veId = valid_request((int)$veId_in, true);

if (!function_exists('time_convert')) {
    function time_convert($time)
    {
        $time = (int)$time;
        $hours   = floor($time / 3600);
        $minutes = floor(($time % 3600) / 60);
        $seconds = floor(($time % 3600) % 60);

        if ($hours > 0) $time_str = $hours . "h " . $minutes . "m " . $seconds . "s";
        else if ($minutes > 0) $time_str = $minutes . "m " . $seconds . "s";
        else $time_str = $seconds . "s";

        return $time_str;
    }
}

if (!function_exists('VentriloDisplayEX1')) {
    function VentriloDisplayEX1( &$stat, $name, $cid, $bgidx )
    {
        $disp_out = "";

        if (!$stat || !is_object($stat)) {
            return "";
        }

        $chan = $stat->ChannelFind( $cid );

        // Safety check if channel exists
        if (!$chan) return "";

        $bg = "#000000";
        $fg = "#FE7200";
        $img = "pub_min"; // Default

        if ( $chan->m_prot == "0" )
        {
            if ( $bgidx %2 ){
                $img = "pub_min";
            }
            else
            {
                $img = "pub_exp";
            }
        }
        else if ( $chan->m_prot == "1" )
        {
            if ( $bgidx %2 ){
                $img = "pass_min";
            }
            else
            {
                $img = "pass_exp";
            }
        }
        else if ( $chan->m_prot == "2" )
        {
            if ( $bgidx %2 ){
                $img = "auth_min";
            }
            else
            {
                $img = "auth_exp";
            }
        }

        $disp_out .= "  <tr>\n";
        if($name != 'nil232143241432432131')
        {
            $disp_out .= "  <td style=\"padding-left:20px;border:0;\"><img src=\"".IMAGE_PATH."/ventrilo/".$img.".gif\" alt=\"\" class=\"tsicon\"/>&nbsp;<span style=\"color:$fg;font-weight:bold;\">";
            $disp_out .= htmlspecialchars((string)$name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); // XSS protection
            $disp_out .= "</span>\n";
        }
        else
        {
            $disp_out .= '<td style="padding-left:20px;">';
        }
        $clientcount = count( $stat->m_clientlist );
        $chancount = count($stat->m_channellist);

        // Display Client for this channel.
        $found = 1;
        for ( $i = 0; $i < $clientcount; $i++ )
        {
            $client = $stat->m_clientlist[ $i ];

            if ( $client->m_cid != $cid )
                continue;
            if ($found == 1)
                $disp_out .= "      <table>\n";
            $found++;
            $disp_out .= "      <tr>\n";
            $disp_out .= "        <td style=\"border:0;\"><img src=\"".IMAGE_PATH."/ventrilo/user.gif\" alt=\"\" class=\"tsicon\"/>&nbsp;";

            $flags = "";

            if ( $client->m_admin )
                $flags .= "A";

            if ( $client->m_phan )
                $flags .= "P";

            if ( strlen( $flags ) )
                $disp_out .= "\"$flags\" ";

            $disp_out .= htmlspecialchars((string)$client->m_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ( $client->m_comm )
                $disp_out .= " (" . htmlspecialchars((string)$client->m_comm, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ")";

            $disp_out .= "  </td>\n";
            $disp_out .= "      </tr>\n";
        }
        // Display sub-channels for this channel.
        for ( $i = 0; $i < $chancount; $i++ )
        {
            if ( $stat->m_channellist[ $i ]->m_pid == $cid )
            {
                $cn = (string)$stat->m_channellist[ $i ]->m_name;
                $comm = trim((string)($stat->m_channellist[ $i ]->m_comm ?? ''));
                if ($comm !== '')
                {
                    $cn .= " (" . $comm . ")";
                }
                $disp_out .= VentriloDisplayEX1( $stat, $cn, $stat->m_channellist[ $i ]->m_cid, $bgidx + 1 );
            }
        }
        if ($found > 1)
            $disp_out .= "      </table>\n";

        $disp_out .= "    </td>\n";
        $disp_out .= "  </tr>\n";

        return $disp_out;
    }
}

if (!function_exists('show_ventrilo_tpl')) {
    function show_ventrilo_tpl($tpl, $array)
    {
        $template = PAGE_PATH . "/templates/ventrilo/" . ltrim($tpl, '/') . ".html";
        $tpl_content = "";

        if (file_exists($template) && is_readable($template)) {
            $tpl_content = (string)@file_get_contents($template);
        } else {
            return "Template " . htmlspecialchars($tpl, ENT_QUOTES, 'UTF-8') . " not found.";
        }

        foreach ($array as $value => $code) {
            $tpl_content = str_replace("[" . $value . "]", (string)$code, $tpl_content);
        }
        return $tpl_content;
    }
}

    // Security: veId is cast to int at start, safe for SQL
    $db->query("SELECT addr, queryPort, password FROM hlstats_Servers_VoiceComm WHERE serverId=$veId");
    $s = $db->fetch_array();

    if ($s) {
        $uip      = (string)$s['addr'];
        $port     = (int)$s['queryPort'];
        $password = (string)$s['password'];
    } else {
        $uip = ''; $port = 0; $password = '';
    }

    $stat = new CVentriloStatus;
    $stat->m_cmdcode = 2; // Detail mode.
    $stat->m_cmdhost = $uip; // Assume ventrilo server on same machine.
    $stat->m_cmdport = $port; // Port to be statused.
    $stat->m_cmdpass = $password; // Status password if necessary.

    $stat->Request();

    // Initialize variables to avoid undefined warnings
    $name         = "Unknown";
    $os           = "Unknown";
    $uptime       = 0;
    $cAmount      = 0;
    $user         = 0;
    $max          = 0;
    $chan         = "";
    $subchan      = "";
    $info         = "";
    $channelstats = "";
    $userstats    = "";

    if (!empty($stat->m_error) || empty($stat->m_name) || empty($uip))
    {
        $err_text = !empty($stat->m_error) ? htmlspecialchars((string)$stat->m_error, ENT_QUOTES, 'UTF-8') : 'Server is offline or unreachable.';
        echo "<div class=\"warning\"><span id=\"warning-header\"><strong>Ventrilo Error:</strong> " . $err_text . "</span></div><br /><br />\n";
    }
    else
    {
        $name     = (string)$stat->m_name;
        $os       = (string)$stat->m_platform;
        $uptime   = (int)$stat->m_uptime;
        $cAmount  = (int)$stat->m_channelcount;
        $user     = (int)$stat->m_clientcount;
        $max      = (int)$stat->m_maxclients;
        $channels = VentriloDisplayEX1( $stat, 'nil232143241432432131', 0, 0 );
        $chan    .= show_ventrilo_tpl("channel", array("channel" => $channels, "subchannels" => $subchan));
    }

    $outp_str = show_ventrilo_tpl("ventrilo", array(
        "name" => $name,
        "os" => $os,
        "uptime" => time_convert($uptime),
        "user" => $user,
        "t_name" => "Server name",
        "t_os" => "Operating system",
        "uchannels" => $chan,
        "info" => $info,
        "t_uptime" => "Uptime",
        "t_channels" => "Channels",
        "t_user" => "Users",
        "head" => "Ventrilo Overview",
        "users_head" => "User Information",
        "player" => "User",
        "channel" => "Channel",
        "channel_head" => "Channel Information",
        "max" => $max,
        "channels" => $cAmount,
        "logintime" => "Login time",
        "idletime" => "Idle time",
        "channelstats" => $channelstats,
        "userstats" => $userstats
    ));

    echo $outp_str;

?>