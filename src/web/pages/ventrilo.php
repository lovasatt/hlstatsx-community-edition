<?php

    if (!defined('IN_HLSTATS')) {
        die('Do not access this file directly.');
    }

    pageHeader(
	array('Ventrilo viewer'),
	array('Ventrilo viewer'=>'')
    );

    require_once(PAGE_PATH . '/ventrilostatus.php');
    include (PAGE_PATH . '/voicecomm_serverlist.php');

    // PHP 8 Fix: Null coalescing and casting
    $veId_in = isset($_GET['veId']) ? $_GET['veId'] : 0;
    $veId = valid_request((int)$veId_in, true);

function time_convert($time)
{ 
    $time = (int)$time;
    $hours = floor($time/3600);
    $minutes = floor(($time%3600)/60);
    $seconds = floor(($time%3600)%60);
    
    if($hours>0) $time_str = $hours."h ".$minutes."m ".$seconds."s";
    else if($minutes>0) $time_str = $minutes."m ".$seconds."s";
    else $time_str = $seconds."s";
     
    return $time_str;
}
  
function VentriloDisplayEX1( &$stat, $name, $cid, $bgidx )
{
    // PHP 8 Fix: Initialize variable
    $disp_out = "";
    
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
	$disp_out .= htmlspecialchars((string)$name); // XSS protection
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
	
	$disp_out .= htmlspecialchars((string)$client->m_name);
	if ( $client->m_comm )
	    $disp_out .= " (" . htmlspecialchars((string)$client->m_comm) . ")";
	
	$disp_out .= "  </td>\n";
	$disp_out .= "      </tr>\n";
    }
    // Display sub-channels for this channel.
    for ( $i = 0; $i < $chancount; $i++ )
    {
	if ( $stat->m_channellist[ $i ]->m_pid == $cid )
	{
	    $cn = $stat->m_channellist[ $i ]->m_name;
	    if ( strlen( (string)$stat->m_channellist[ $i ]->m_comm ) )
	    {
		$cn .= " (";
		$cn .= $stat->m_channellist[ $i ]->m_comm;
		$cn .= ")";
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

  
function show($tpl, $array)
{
    $template = PAGE_PATH."/templates/ventrilo/".$tpl.".html";
    $tpl_content = "";
  
    if(file_exists($template))
    {
	$tpl_content = file_get_contents($template);
    }
    else
    {
	// die('no template'); // Soft fail is better
        return "Template $tpl not found.";
    }
    
    foreach($array as $value => $code)
    {
	$tpl_content = str_replace("[".$value."]", (string)$code, $tpl_content);
    }
    return $tpl_content;
}

    // Security: veId is cast to int at start, safe for SQL
    $db->query("SELECT addr, queryPort, password FROM hlstats_Servers_VoiceComm WHERE serverId=$veId");
    $s = $db->fetch_array();

    if ($s) {
        $uip 	= $s['addr'];
        $port 	= $s['queryPort'];
	$password = $s['password'];
    } else {
        $uip = ''; $port = 0; $password = '';
    }

    // Fix: logic error in original code: strlen($password < 1) -> empty($password)
    if (empty($password)){
	$password = '';
    }

    $stat = new CVentriloStatus;
    $stat->m_cmdcode	= 2;					// Detail mode.
    $stat->m_cmdhost	= $uip;					// Assume ventrilo server on same machine.
    $stat->m_cmdport	= $port;				// Port to be statused.
    $stat->m_cmdpass	= $password;			// Status password if necessary.

    $rc = $stat->Request();
    
    // Initialize variables to avoid undefined warnings
    $name = "Unknown";
    $os = "Unknown";
    $uptime = 0;
    $cAmount = 0;
    $user = 0;
    $max = 0;
    $chan = "";
    $subchan = ""; // Was undefined in original
    $info = "";
    $channelstats = "";
    $userstats = "";
    
    if ( $rc )
    {
	error("No Ventrilo", 1);
	echo "CVentriloStatus->Request() failed. <strong>" . htmlspecialchars($stat->m_error) . "</strong><br><br>\n";
    }
    else
    {
	$name = $stat->m_name;
	$os = $stat->m_platform;
	$uptime = $stat->m_uptime;
	$cAmount = $stat->m_channelcount;
	$user = $stat->m_clientcount;
	$max = $stat->m_maxclients;
	$channels = VentriloDisplayEX1( $stat, 'nil232143241432432131', 0, 0 );
	$chan .= show("channel", array("channel" => $channels , "subchannels" => $subchan ));
    }

    $outp_str = show("ventrilo", array(
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