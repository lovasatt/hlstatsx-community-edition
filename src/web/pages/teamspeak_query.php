<?php
/*
  © by iklas Hĺkansson <niklas.hk@telia.com>
  
  modified by CodeKing for DZCP 11-29-2006 (mm-dd-yyyy)
*/
function setUserStatus($img)
{
    global $g_options;
    $img = (string)$img;
    switch ($img) {
	case '1' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/ccommander.gif" class="tsicon" alt="" />'; 
	break;
	
	case '3' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/ccommander.gif" class="tsicon" alt="" />'; 
	break;	
	
	case '5' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/ccommander.gif" class="tsicon" alt="" />'; 
	break;
	
	case '7' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/ccommander.gif" class="tsicon" alt="" />'; 
	break;		
	
	case '8' :
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '9' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '10' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '11' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '12' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '13' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '14' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '15' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;			
	
	case '16' :
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/muted.gif" class="tsicon" alt="" />';
	break;
	
	case '17' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/muted.gif" class="tsicon" alt="" />';
	break;
	
	case '18' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/muted.gif" class="tsicon" alt="" />';
	break;
	
	case '19' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/muted.gif" class="tsicon" alt="" />';
	break;
	
	case '20' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/muted.gif" class="tsicon" alt="" />';
	break;
	
	case '21' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/muted.gif" class="tsicon" alt="" />';
	break;
	
	case '22' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/muted.gif" class="tsicon" alt="" />';
	break;
	
	case '23' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/muted.gif" class="tsicon" alt="" />';
	break;		
	
	case '24' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '25' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '26' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '27' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '28' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '29' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '30' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '31' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;		
	
	case '32' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '33' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '34' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '35' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '36' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '37' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '38' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '39' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;		
	
	case '40' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '41' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '42' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '43' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '44' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '45' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '46' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '47' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;		
	
	case '48' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '49' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '50' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '51' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '52' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '53' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '54' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '55' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '56' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;
	
	case '57' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/smuted.gif" class="tsicon" alt="" />';
	break;		
	
	case '58' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '59' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;		
	
	case '60' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;
	
	case '61' : 
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/away.gif" class="tsicon" alt="" />';
	break;		
	
	default :
	$img = '<img src="'.IMAGE_PATH.'/teamspeak/user.gif" class="tsicon" alt="" />';
	break;		
	
    }			 
    return $img;		
}

function setCPriv($str)
{
    $str = (string)$str;
    switch ($str) {		 	
	case '1' : //Channel Admin
	$str = '&nbsp;CA';  
        break;
	
	case '2' : //Channel Ops
	$str = '&nbsp;O';  
        break;
	
	case '3' : //Channel Admin & Ops 
	$str = '&nbsp;CA&nbsp;O';  
        break;
	
	case '4' : //Voice
	$str = '&nbsp;V';  
        break;
	
	case '5' : //Channel Admin & Voice
	$str = '&nbsp;CA&nbsp;V';  
        break;
	
	case '6' : //Ops & Voice
	$str = '&nbsp;O&nbsp;V';  
        break;
	
	case '7' : //Channel Admin & Ops & Voiced 
	$str = '&nbsp;CA&nbsp;O&nbsp;V';  
        break;
	
	case '8' : //Auto Ops 
	$str = '&nbsp;AO';  
        break;
	
	case '9' : //Channel Admin & Auto Ops 
	$str = '&nbsp;CA&nbsp;AO';  
        break;
	
	case '10' : //Channel Admin & Auto Ops 
	$str = '&nbsp;AO&nbsp;O';  
        break;
	
	case '11' : //Channel Admin & Auto Ops & Ops
	$str = '&nbsp;CA&nbsp;AO&nbsp;O';  
        break;
	
	case '12' : //Auto Ops & Voiced
	$str = '&nbsp;AO&nbsp;V';  
        break;
	
	case '13' : //Channel Admin & Auto Ops & Voiced
	$str = '&nbsp;CA&nbsp;AO&nbsp;V';  
        break;	
	
	case '14' : //Auto Ops & Ops & Voiced
	$str = '&nbsp;AO&nbsp;O&nbsp;V';  
        break;
	
	case '15' : //Channel Admin & Auto Ops & Ops & Voiced
	$str = '&nbsp;CA&nbsp;AO&nbsp;O&nbsp;V';  
        break;
	
	case '16' : //Auto Voice
	$str = '&nbsp;AV';  
        break;
	
	case '17' : //Channel Admin & Auto Voice
	$str = '&nbsp;CA&nbsp;AV';  
        break;
	
	case '18' : //Auto Voice & Ops
	$str = '&nbsp;AV&nbsp;O';  
        break;
	
	case '19' : //Channel Admin & Auto Voice & Ops
	$str = '&nbsp;CA&nbsp;AV&nbsp;O';  
        break;
	
	case '20' : //Auto Voice & Voice 
	$str = '&nbsp;AV&nbsp;V';  
        break;
	
	case '21' : //Channel Admin & Auto Voice & Voice 
	$str = '&nbsp;CA&nbsp;AV&nbsp;V';  
        break;
	
	case '22' : //Auto Voice & Ops & Voice 
	$str = '&nbsp;AV&nbsp;O&nbsp;V';  
        break;
	
	case '23' : //Channel Admin & Auto Voice & Ops & Voice 
	$str = '&nbsp;CA&nbsp;AV&nbsp;O&nbsp;V';  
        break;
	
	case '24' : //Auto Ops & Auto Voice
	$str = '&nbsp;AO&nbsp;AV';  
        break;
	
	case '25' : //Channel Admin & Auto Ops & Auto Voice 
	$str = '&nbsp;CA&nbsp;AO&nbsp;AV';  
        break;
	
	case '26' : //Auto Ops & Auto Voice & Ops 
	$str = '&nbsp;AO&nbsp;AV&nbsp;O';  
        break;
	
	case '27' : //Channel Admin & Auto Ops & Auto Voice & Ops 
	$str = '&nbsp;CA&nbsp;AO&nbsp;AV&nbsp;O';  
        break;
	
	case '28' : //Auto Ops & Auto Voice & Voice 
	$str = '&nbsp;AO&nbsp;AV&nbsp;V';  
        break;
	
	case '29' : //Channel Admin & Auto Ops & Auto Voice & Voice 
	$str = '&nbsp;CA&nbsp;AO&nbsp;AV&nbsp;V';  
        break;
	
	case '30' : //Auto Ops & Auto Voice & Ops & Voiced
	$str = '&nbsp;AO&nbsp;AV&nbsp;O&nbsp;V';  
        break;
	
	case '31' : //Channel Admin & Auto Ops & Auto Voice & Ops & Voiced
	$str = '&nbsp;CA&nbsp;AO&nbsp;AV&nbsp;O&nbsp;V';  
        break;
	
	default :
        $str = '';
        break;	
    }
    
    return $str;
}

function removeChar($str)
{
    $str = str_replace('"', '', (string)$str);
    return $str;
}

function time_convert($time)
{ 
    $time = (int)$time;
    $hours = floor($time/3600);
    $minutes = floor(($time%3600)/60);
    $seconds = floor(($time%3600)%60);
    
    if($hours>0) $time_str = $hours."h $minutes"."m $seconds".'s';
    else if($minutes>0) $time_str = $minutes."m $seconds".'s';
    else $time_str = $seconds.'s';
     
    return $time_str;
} 

function getCodec($codec)
{
    $codec = (string)$codec;
    switch ($codec) {		 	
	case '0' : 
	$codec = 'CELP 5.2 Kbit';  
        break;
	
	case '1' : 
	$codec = 'CELP 6.3 Kbit';  
        break;
	
	case '2' : 
	$codec = 'GSM 14.8 Kbit';  
        break;
	
	case '3' : 
	$codec = 'GSM 16.4 Kbit';  
        break;
	
	case '4' : 
	$codec = 'Windows CELP 5.2 Kbit';  
        break;			
	
	case '5' : 
	$codec = 'Speex 3.4 Kbit';  
        break;
	
	case '6' : 
	$codec = 'Speex 5.2 Kbit';  
        break;
	
	case '7' : 
	$codec = 'Speex 7.2 Kbit';  
        break;		
	
	case '8' : 
	$codec = 'Speex 9.3 Kbit';  
        break;		
	
	case '9' : 
	$codec = 'Speex 12.3 Kbit';  
        break;
	
	case '10' : 
	$codec = 'Speex 16.3 Kbit';  
        break;
	
	case '11' : 
	$codec = 'Speex 19.5 Kbit';  
        break;	
	
	case '12' : 
	$codec = 'Speex 25.9 Kbit';  
        break;			
	
	default :
        $codec = '';
        break;
    }	
	
    return $codec;
}

function setPPriv($str)
{
    $str = (string)$str;
    switch ($str) {	
        case '5' : //Server Admin
	$str = 'R&nbsp;SA';
        break;
    
        case '4' : //Registered
        $str = 'R';
        break;
	
        default :
        $str = 'U';
        break;   
    }
   
    return $str;
}

function setPPrivText($str)
{
    $str = (string)$str;
    switch ($str) {	
        case '5' : //Server Admin
	$str = 'Server Administrator<br />Registered';
        break;
    
        case '4' : //Registered
        $str = 'Registered';
        break;
	
        default :
        $str = 'None';
        break;   
    }
   
    return $str;
}

function setCPrivText($str)
{
    $str = (string)$str;
    switch ($str) {		 	
	case '1' : //Channel Admin
	$str = 'Channel Admin';
        break;
	
	case '2' : //Channel Ops
	$str = 'Channel Ops';
        break;
	
	case '3' : //Channel Admin & Ops 
	$str = 'Channel Admin<br />Ops';
        break;
	
	case '4' : //Voice
	$str = 'Voice';
        break;
	
	case '5' : //Channel Admin & Voice
	$str = 'Channel Admin<br />Voice';
        break;
	
	case '6' : //Ops & Voice
	$str = 'Ops<br />Voice';  
        break;
	
	case '7' : //Channel Admin & Ops & Voiced 
	$str = 'Channel Admin<br />Ops<br />Voiced';  
        break;
	
	case '8' : //Auto Ops 
	$str = 'Auto Ops';  
        break;
	
	case '9' : //Channel Admin & Auto Ops 
	$str = 'Channel Admin<br />Auto Ops';  
        break;
	
	case '10' : //Auto Ops & Auto Ops 
	$str = 'Auto Ops<br />Ops';  
        break;
	
	case '11' : //Channel Admin & Auto Ops & Operator
	$str = 'Channel Admin<br />Auto Ops<br />Ops';  
        break;
	
	case '12' : //Auto Ops & Voiced
	$str = 'Auto Ops<br />Voiced';  
        break;
	
	case '13' : //Channel Admin & Auto Ops & Voiced
	$str = 'Channel Admin<br />Auto Ops<br />Voiced';  
        break;	
	
	case '14' : //Auto Ops & Ops & Voiced
	$str = 'Auto Ops<br />Ops<br />Voiced';  
        break;
	
	case '15' : //Channel Admin & Auto Ops & Ops & Voiced
	$str = 'Channel Admin<br />Auto Ops<br />Ops<br />Voiced';  
        break;
	
	case '16' : //Auto Voice
	$str = 'Auto Voice';  
        break;
	
	case '17' : //Channel Admin & Auto Voice
	$str = 'Channel Admin<br />Auto Voice';  
        break;
	
	case '18' : //Auto Voice & Ops
	$str = 'Auto Voice<br />Ops';  
        break;
	
	case '19' : //Channel Admin & Auto Voice & Ops
	$str = 'Channel Admin<br />Auto Voice<br />Ops';  
        break;
	
	case '20' : //Auto Voice & Voice 
	$str = 'Auto Voice<br />Voice';  
        break;
	
	case '21' : //Channel Admin & Auto Voice & Voice 
	$str = 'Channel Admin<br />Auto Voice<br />Voice';  
        break;
	
	case '22' : //Auto Voice & Ops & Voice 
	$str = 'Auto Voice<br />Ops<br />Voice';  
        break;
	
	case '23' : //Channel Admin & Auto Voice & Ops & Voice 
	$str = 'Channel Admin<br />Auto Voice<br />Ops<br />Voice';  
        break;
	
	case '24' : //Auto Ops & Auto Voice
	$str = 'Auto Ops<br />Auto Voice';  
        break;
	
	case '25' : //Channel Admin & Auto Ops & Auto Voice 
	$str = 'Channel Admin<br />Auto Ops<br />Auto Voice';  
        break;
	
	case '26' : //Auto Ops & Auto Voice & Ops 
	$str = 'Auto Ops<br />Auto Voice<br />Ops';  
        break;
	
	case '27' : //Channel Admin & Auto Ops & Auto Voice & Ops 
	$str = 'Channel Admin<br />Auto Ops<br />Auto Voice<br />Ops';  
        break;
	
	case '28' : //Auto Ops & Auto Voice & Voice 
	$str = 'Auto Ops<br />Auto Voice<br />Voice';  
        break;
	
	case '29' : //Channel Admin & Auto Ops & Auto Voice & Voice 
	$str = 'Channel Admin<br />Auto Ops<br />Auto Voice<br />Voice';  
        break;
	
	case '30' : //Auto Ops & Auto Voice & Ops & Voiced
	$str = 'Auto Ops<br />Auto Voice<br />Ops<br />Voiced';  
        break;
	
	case '31' : //Channel Admin & Auto Ops & Auto Voice & Ops & Voiced
	$str = 'Channel Admin<br />Auto Ops<br />Auto Voice<br />Ops<br />Voiced';  
        break;
	
	default :
        $str = 'None';
        break;	
    }
    
    return $str;
}

function indexOf($str,$strChar)
{
    $str = (string)$str;
    $strChar = (string)$strChar;
    if(strlen(strchr($str,$strChar))>0) {
	$position_num = strpos($str,$strChar) + strlen($strChar);		
	return $position_num;
    } else {
	return -1;
    }
}
function getChannelName($cid,$ip,$port,$tPort)
{		
    $name = 'Unknown';
    $cArray = getChannels($ip,$port,$tPort);
    
    if (is_array($cArray)) {
        for($i=0;$i<count($cArray);$i++)
        {
	    $innerArray=$cArray[$i];		
	    if(isset($innerArray[0]) && $innerArray[0]==$cid && isset($innerArray[5]))
	        $name = removeChar($innerArray[5]);	
        }
    }
    return $name;
}

function getChannels($ip,$port,$tPort)
{
    $cArray 	= array();
    $out		= "";
    $j			= 0; 
    $k			= 0;
    
    $errno = 0; $errstr = '';
    $fp = @fsockopen($ip, $tPort, $errno, $errstr, 30);
    
    if($fp) {
	fputs($fp, "cl ".$port."\n");		
	fputs($fp, "quit\n");
	while(!feof($fp)) {
	    $out .= fgets($fp, 1024);
	}
	$out   = str_replace('[TS]', '', $out);
	$out   = str_replace("\n", "\t", $out);			
	$data 	= explode("\t", $out);
	$num 	= count($data);				
	
	for($i=0;$i<count($data);$i++) {
	    if($i>=10) {
                // Initialize inner array key
		$innerArray[$j] = convertCharset($data[$i]);
		if($j>=8)
		{
		    $cArray[$k]=$innerArray;
		    $j = 0;
		    $k = $k+1;
                    $innerArray = array(); // Reset inner array
		} else {
		    $j++;
		}
	    }			
	}			
	fclose($fp);	
    } 	

    return $cArray;
}
function getTSChannelUsers($ip,$port,$tPort)
{
    $uArray 	= array();
    $innerArray = array();
    $out		= "";
    $j			= 0; 
    $k			= 0;
    
    $errno = 0; $errstr = '';
    $fp = @fsockopen($ip, $tPort, $errno, $errstr, 30);
    
    if($fp) {
	fputs($fp, "pl ".$port."\n");		
	fputs($fp, "quit\n");
	while(!feof($fp)) {
	    $out .= fgets($fp, 1024);
	}
        $out = (string)$out;
	$out   = str_replace("[TS]", "", $out);
	$out   = str_replace("loginname", "loginname\t", $out);		
	$data 	= explode("\t", $out);
	$num 	= count($data);				
	
	for($i=0;$i<count($data);$i++) {
	    $innerArray[$j] = convertCharset($data[$i]);
	    if($j>=15)
	    {
		$uArray[$k]=$innerArray;
		$j = 0;
		$k = $k+1;
                $innerArray = array();
	    } else {
		$j++;
	    }			
	}			
	fclose($fp);	
    } 	
     return $uArray;		
}

function usedID($usedArray,$cid)
{		
    $ok = true;
    for($i=0;$i<count($usedArray);$i++)
    {	
	if($usedArray[$i]==$cid) {
	    $ok = false;			
	}		
    }
    return $ok;
}

function defaultInfo($ip,$tPort,$port)
{
    $out = '';
    $html = '';	
    
    $errno = 0; $errstr = '';
    $fp = @fsockopen($ip, $tPort, $errno, $errstr, 30);
    
    if($fp) {
	fputs($fp, "sel ".$port."\n");
	fputs($fp, "si\n");
	fputs($fp, "quit\n");
	while(!feof($fp)) {
	    $out .= fgets($fp, 1024);
	}
	
        $out = (string)$out;
	$out   	= str_replace('[TS]', '', $out);
	$out   	= str_replace('OK', '', $out);
	$out 	= trim($out);
	
	$name=substr($out,indexOf($out,"server_name="),strlen($out));
	$name=convertCharset(substr($name,0,indexOf($name,"server_platform=")-strlen("server_platform=")));
	
	$os=substr($out,indexOf($out,"server_platform="),strlen($out));
	$os=convertCharset(substr($os,0,indexOf($os,"server_welcomemessage=")-strlen("server_welcomemessage=")));
	
	$tsType=substr($out,indexOf($out,"server_clan_server="),strlen($out));
	$tsType=substr($tsType,0,indexOf($tsType,"server_udpport=")-strlen("server_udpport="));			
	
	$welcomeMsg=substr($out,indexOf($out,"server_welcomemessage="),strlen($out));
	$welcomeMsg=convertCharset(substr($welcomeMsg,0,indexOf($welcomeMsg,"server_webpost_linkurl=")-strlen("server_webpost_linkurl=")));
		
	
	if(isset($tsType[0]) && $tsType[0]==1) $tsTypeText = "Freeware Clan Server";
	else $tsTypeText = "Freeware Public Server";		

	$html = "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\" class=\"fHeading\">Server:</td></tr>\n";
	$html .= "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\">$name<br /><br /></td></tr>\n";
	$html .= "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\" class=\"fHeading\">Server IP:</td></tr>\n";
	$html .= "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\">$ip:$port<br /><br /></td></tr>\n";
	$html .= "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\" class=\"fHeading\">Version:</td></tr>\n";
	$html .= "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\">".getTSVersion($ip,$tPort,$port)."<br /><br /></td></tr>\n";
	$html .= "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\" class=\"fHeading\">Type:</td></tr>\n";
	$html .= "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\">$tsTypeText<br /><br /></td></tr>\n";
	$html .= "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\" class=\"fHeading\">Welcome Message:</td></tr>\n";
	$html .= "<tr class=\"bg1\"><td id=\"contentMainFirst\" style=\"border:0\">$welcomeMsg<br /><br /></td></tr>";
	
	fclose($fp);
    }
    return $html;
}

function channelInfo($ip,$tPort,$port,$cID)
{
    $cArray		= getChannels($ip,$port,$tPort);
    $uArray 	= getTSChannelUsers($ip,$port,$tPort);
    $html 		= '';
    $cUser		= 0;
    $ok 		= false;
    $codec = ''; $max = ''; $name = ''; $topic = '';
    
    for($i=0;$i<count($cArray);$i++)
    {
	$innArray = $cArray[$i];
	if($innArray[0]==$cID)
	{
	    $codec  = isset($innArray[1]) ? $innArray[1] : '';
	    $max	= isset($innArray[4]) ? $innArray[4] : '';
	    $name 	= isset($innArray[5]) ? $innArray[5] : '';				
	    $topic 	= isset($innArray[8]) ? $innArray[8] : '';
	    $ok = true; 
	}
    }
    
    for($i=0;$i<count($uArray);$i++)
    {
	$innArray = $uArray[$i];
	if(isset($innArray[1]) && $innArray[1]==$cID) $cUser++;		
    }	
    if($ok) 
    {
        // Safe encoding
        $topic_clean = convertCharset(removeChar($topic));
        
	$html = "<tr class=\"bg1\"><td>Channel:</td></tr>\n";
	$html .= "<tr class=\"bg1\"><td>".removeChar($name)."<br /><br /></td></tr>\n";
	$html .= "<tr class=\"bg1\"><td>Topic:</td></tr>\n";
	$html .= "<tr class=\"bg1\"><td>".$topic_clean."<br /><br /></td></tr>\n";
	$html .= "<tr class=\"bg1\"><td>User in channel:</td></tr>\n";
	$html .= "<tr class=\"bg1\"><td>".$cUser."/".removeChar($max)."<br /><br /></td></tr>\n";
	$html .= "<tr class=\"bg1\"><td>Codec:</td></tr>\n";
	$html .= "<tr class=\"bg1\"><td>".getCodec($codec)."<br /><br /></td></tr>\n";
	$name = str_replace("'","¶",$name);
//		$html .= "<tr><td><br /><input type=\"button\" id=\"submit\" onclick=\"javascript:w('login.php?cName=".removeChar($name)."', 'TS2', '420', '150');\" value=\"Join Channel\" class=\"submit\" /></td></tr>\n";
    } else {
	$html = "<tr class=\"bg1\"><td>Channel is deleted!</td></tr>\n";
    }
    
    return $html;	
}

function getTSVersion($ip,$tPort,$port)
{
    $out = "";
    $errno = 0; $errstr = '';
    $fp = @fsockopen($ip, $tPort, $errno, $errstr, 30);
    
    if($fp) {
	fputs($fp, "sel ".$port."\n");
	fputs($fp, "ver\n");
	fputs($fp, "quit\n");
	while(!feof($fp)) {
	    $out .= fgets($fp, 1024);
	}
        $out = (string)$out;
	$out   	= str_replace("[TS]", "", $out);
	$out   	= str_replace("OK", "", $out);
	$out   	= str_replace("\n", "", $out);		
	$data  	= explode(" ", $out);
	
	fclose($fp);
        return isset($data[0]) ? $data[0] : '';
    }
    return '';
}

function convertCharset($text)
{
    // utf8_encode is deprecated in PHP 8.2
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding((string)$text, 'UTF-8', 'Windows-1252');
    }
    // Fallback if not on PHP 8.2 yet or warnings suppressed
    return @utf8_encode((string)$text);
}
?>