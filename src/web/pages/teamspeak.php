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

    pageHeader(
	array('Teamspeak viewer'),
	array('Teamspeak viewer' => '')
    );
    include (PAGE_PATH.'/voicecomm_serverlist.php');
    include (PAGE_PATH.'/teamspeak_query.php');

    // PHP 8 Fix: Null coalescing and type casting
    $tsId_in = isset($_GET['tsId']) ? $_GET['tsId'] : 0;
    $tsId = valid_request((int)$tsId_in, true);

    function show($tpl, $array)
    {
	$template = PAGE_PATH."/templates/teamspeak/$tpl";
        
        $tpl_content = '';
	if($fp = @fopen($template.".html", "r")) {
	  $tpl_content = @fread($fp, filesize($template.".html"));
          fclose($fp);
        }

        if ($tpl_content) {
	    foreach($array as $value => $code)
	    {
	      $tpl_content = str_replace("[".$value."]", (string)$code, $tpl_content);
	    }
        }
        return $tpl_content;
    }

  if (function_exists('fsockopen'))
  {
    $db->query("SELECT addr, queryPort, UDPPort FROM hlstats_Servers_VoiceComm WHERE serverId=$tsId");
    $s = $db->fetch_array();

    if ($s) {
        $uip 	= $s['addr'];
        $tPort 	= $s['queryPort'];
        $port 	= $s['UDPPort'];
    
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($uip, $tPort, $errno, $errstr, 2);
    
	if(!$fp)
        {
	    $index = error("No teamspeak", 1);
        } else {
	    $out = "";
            // Re-open for clean communication or reuse $fp if logic permits (original code reopened)
            fclose($fp);
            
	    $fp = @fsockopen($uip, $tPort, $errno, $errstr, 2);
	    if($fp)
          {
	        fputs($fp, "sel $port\n");
	        fputs($fp, "si\n");
	        fputs($fp, "quit\n");
	        while(!feof($fp))
            {
		    $out .= fgets($fp, 1024);
	        }
                // PHP 8 Fix: ensure $out is string
                $out = (string)$out;
	        $out = str_replace('[TS]', '', $out);
	        $out = str_replace('OK', '', $out);
	        $out = trim($out);
    
                // Helper to prevent undefined function error if indexOf not in included files
                if (!function_exists('indexOf')) {
                    function indexOf($haystack, $needle) {
                        return strpos($haystack, $needle);
                    }
                }

    	$name=substr($out,indexOf($out,'server_name='),strlen($out));
	    $name=substr($name,0,indexOf($name,'server_platform=')-strlen('server_platform='));
	      $os=substr($out,indexOf($out,'server_platform='),strlen($out));
	      $os=substr($os,0,indexOf($os,'server_welcomemessage=')-strlen('server_welcomemessage='));
	      $uptime=substr($out,indexOf($out,'server_uptime='),strlen($out));
	      $uptime=substr($uptime,0,indexOf($uptime,'server_currrentusers=')-strlen('server_currrentusers='));
	      $cAmount=substr($out,indexOf($out,'server_currentchannels='),strlen($out));
	      $cAmount=substr($cAmount,0,indexOf($cAmount,'server_bwinlastsec=')-strlen('server_bwinlastsec='));
	      $user=substr($out,indexOf($out,'server_currentusers='),strlen($out));
	      $user=substr($user,0,indexOf($user,'server_currentchannels=')-strlen('server_currentchannels='));
	      $max=substr($out,indexOf($out,'server_maxusers='),strlen($out));
	      $max=substr($max,0,indexOf($max,'server_allow_codec_celp51=')-strlen('server_allow_codec_celp51='));
          fclose($fp);
        } else {
            // Handle secondary connection failure
            $name = $os = $uptime = $cAmount = $user = $max = 'N/A';
        }
    
        $uArray = array();
	  $innerArray = array();
	  $out = "";
	  $j = 0;
	  $k = 0;
    
        $fp = @fsockopen($uip, $tPort, $errno, $errstr, 30);
	  if($fp)
        {
	      fputs($fp, "pl ".$port."\n");
	      fputs($fp, "quit\n");
	      while(!feof($fp))
          {
		  $out .= fgets($fp, 1024);
	      }
              $out = (string)$out;
	      $out = str_replace('[TS]', '', $out);
	      $out = str_replace('loginname', "loginname\t", $out);
	      $data	= explode("\t", $out);
    
	      for($i=0;$i<count($data);$i++)
          {
		  $innerArray[$j] = $data[$i];
		  if($j>=15)
		  {
		      $uArray[$k]=$innerArray;
		      $j = 0;
		      $k = $k+1;
		  } else {
		      $j++;
		  }
	      }
	      fclose($fp);
	  }
	  $debug = false;
    
        // Initialize output variables
        $userstats = "";
        $channelstats = "";
        $chan = "";
        $color = 0;

        for($i=1;$i<count($uArray);$i++)
        {
	    $innerArray=$uArray[$i];
            
            // PHP 8 Fix: Ensure array keys exist before access
            $status = isset($innerArray[12]) ? setUserStatus($innerArray[12]) : '';
            $p_name = isset($innerArray[14]) ? removeChar($innerArray[14]) : '';
            $ppriv = isset($innerArray[11]) ? setPPriv($innerArray[11]) : '';
            $cpriv = isset($innerArray[10]) ? setCPriv($innerArray[10]) : '';
            
          $p = $status."&nbsp;<span style=\"font-weight:bold;\">".$p_name."</span>
               &nbsp;(".$ppriv."".$cpriv.")";
               
          $class = ($color % 2) ? "bg2" : "bg1"; $color++;
          
          $chan_id = isset($innerArray[1]) ? $innerArray[1] : 0;
          $misc1 = isset($innerArray[6]) ? $innerArray[6] : '';
          $misc2 = isset($innerArray[7]) ? $innerArray[7] : '';
          $misc3 = isset($innerArray[8]) ? $innerArray[8] : 0;
          $misc4 = isset($innerArray[9]) ? $innerArray[9] : 0;

          $userstats .= show("/userstats", array("player" => $p,
                                                      "channel" => getChannelName($chan_id,$uip,$port,$tPort),
                                                      "misc1" => $misc1,
                                                      "class" => $class,
                                                      "misc2" => $misc2,
                                                      "misc3" => time_convert($misc3),
                                                      "misc4" => time_convert($misc4)));
    
	  }
    
        $uArr = getTSChannelUsers($uip,$port,$tPort);
        if (!is_array($uArr)) $uArr = array();
        
	  $pcArr = Array();
	  $ccArr = Array();
	  $thisArr = Array();
	  $listArr = Array();
	  $usedArr = Array();
	  $cArr	= getChannels($uip,$port,$tPort);
          if (!is_array($cArr)) $cArr = array();
          
	  $z = 0;
	  $x = 0;
    
        for($i=0;$i<count($cArr);$i++)
	  {
	      $innerArr=$cArr[$i];
	      $listArr[$i]=$innerArr[3];
	  }
	  sort($listArr);
          
          // Function usedID helper if missing
          if (!function_exists('usedID')) {
              function usedID($arr, $id) {
                  return !in_array($id, $arr);
              }
          }

	  for($i=0;$i<count($listArr);$i++)
	  {
	      for($j=0;$j<count($cArr);$j++)
	      {
		  $innArr=$cArr[$j];
    
		  if($innArr[3]==$listArr[$i] && usedID($usedArr,$innArr[0]))
		  {
		      if($innArr[2]==-1)
		      {
			  $thisArr[0] = $innArr[0];
			  $thisArr[1] = $innArr[5];
			  $thisArr[2] = $innArr[2];
			  $pcArr[$z] = $thisArr;
			  $usedArr[count($usedArr)] = $innArr[0];
			  $z++;
		      } else {
			  $thisArr[0] = $innArr[0];
			  $thisArr[1] = $innArr[5];
			  $thisArr[2] = $innArr[2];
			  $ccArr[$x] = $thisArr;
			  $usedArr[count($usedArr)] = $innArr[0];
			  $x++;
		      }
		  }
	      }
	  }
    
	  for($i=0;$i<count($pcArr);$i++)
        {
	    $innerArr=$pcArr[$i];
    
          $subchan = "";
	    for($j=0;$j<count($ccArr);$j++)
          {
	      $innerCCArray=$ccArr[$j];
	      if($innerArr[0]==$innerCCArray[2])
            {
                $subusers = "";
                // Logic fix: loops were nested identically using $p
                for($p=1;$p<count($uArr);$p++)
                {
		        $innerUArray=$uArr[$p];
                        // Ensure array keys exist
		        if(isset($innerUArray[1]) && $innerCCArray[0]==$innerUArray[1])
		        {
                            $u_status = isset($innerUArray[12]) ? setUserStatus($innerUArray[12]) : '';
                            $u_name = isset($innerUArray[14]) ? removeChar($innerUArray[14]) : '';
                            $u_ppriv = isset($innerUArray[11]) ? setPPriv($innerUArray[11]) : '';
                            $u_cpriv = isset($innerUArray[10]) ? setCPriv($innerUArray[10]) : '';
                            
                            $subusers .= "&nbsp;&nbsp;&nbsp;&nbsp;<img src=\"".IMAGE_PATH."/teamspeak/trenner.gif\" alt=\"\" class=\"tsicon\" />".$u_status."&nbsp;<span style=\"font-weight:bold;\">".$u_name."</span>&nbsp;(".$u_ppriv."".$u_cpriv.")<br />";
	                }
	        }
                $clean_chan_name = removeChar($innerCCArray[1]);
                $subchannels = "<img src=\"".IMAGE_PATH."/teamspeak/trenner.gif\" alt=\"\" class=\"tsicon\" /><img src=\"".IMAGE_PATH."/teamspeak/channel.gif\" alt=\"\" class=\"tsicon\" /><a style=\"font-weight:normal\" href=\"hlstats.php?mode=teamspeak&amp;game=$game&amp;tsId=$tsId&amp;cID=".$innerCCArray[0]."&amp;type=1\">&nbsp;".$clean_chan_name."&nbsp;</a><br /> ".$subusers."";
                $subchan .= show("subchannels", array("subchannels" => $subchannels));
	      }
          }
          
          $users = "";
          for($k=1;$k<count($uArr);$k++)
          {
	        $innerUArray=$uArr[$k];
	        if(isset($innerUArray[1]) && $innerArr[0]==$innerUArray[1])
            {
                $u_status = isset($innerUArray[12]) ? setUserStatus($innerUArray[12]) : '';
                $u_name = isset($innerUArray[14]) ? removeChar($innerUArray[14]) : '';
                $u_ppriv = isset($innerUArray[11]) ? setPPriv($innerUArray[11]) : '';
                $u_cpriv = isset($innerUArray[10]) ? setCPriv($innerUArray[10]) : '';
              
              $users .= "<img src=\"".IMAGE_PATH."/teamspeak/trenner.gif\" alt=\"\" class=\"tsicon\" />".$u_status."<span style=\"font-weight:bold;\">".$u_name."</span>&nbsp;(".$u_ppriv."".$u_cpriv.")<br />";
	    }
	}
    
          $clean_parent_chan = removeChar($innerArr[1]);
          $channels = "<img src=\"".IMAGE_PATH."/teamspeak/channel.gif\" alt=\"\" class=\"tsicon\" />&nbsp;<a style=\"font-weight:bold\" href=\"hlstats.php?mode=teamspeak&amp;game=$game&amp;tsId=$tsId&amp;cID=".trim((string)$innerArr[0])."&amp;type=1\">".$clean_parent_chan."&nbsp;</a><br /> ".$users."";
    
          $chan .= show("channel", array("channel" => $channels,
                                               "subchannels" => $subchan));
    
        }
    
        if (isset($_GET['cID'])) {
	    $cID = (int)$_GET['cID'];
	    $type= (int)$_GET['type'];
        } else {
	    $cID = 0;
	    $type = 0;
        }
        
        $info = "";
        if ($type == 0) {
	    $info = defaultInfo($uip, $tPort, $port);
	} else if ($type == 1) {
	    $info = channelInfo($uip, $tPort, $port, $cID);
	}
    
        $outp_str = show("teamspeak", array("name" => $name,
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
                                               "head" => "Teamspeak Overview",
                                               "users_head" => "User Information",
                                               "player" => "User",
                                               "channel" => "Channel",
                                               "channel_head" => "Channel Information",
                                               "max" => $max,
                                               "channels" => $cAmount,
                                               "logintime" => "Login time",
                                               "idletime" => "Idle time",
                                               "channelstats" => $channelstats,
                                               "userstats" => $userstats));
			   
        echo $outp_str;				   
			   
        }
    }
  } else {
    echo "Error, function fsockopen not found";
  }

?>