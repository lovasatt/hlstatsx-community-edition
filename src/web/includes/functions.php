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

/**
 * hlx_h()
 * Escapes a value for safe HTML output (text and attribute context).
 *
 * @param mixed $str
 * @return string
 */
function hlx_h($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/**
 * valid_game()
 * Game codes are used in file paths and SQL, so only allow a strict charset.
 *
 * @param mixed $str
 * @return string
 */
function valid_game($str)
{
    if (is_array($str)) {
        return '';
    }
    return substr(preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$str), 0, 32);
}

/**
 * getOptions()
 * 
 * @return Array All the options from the options/perlconfig table
 */
function getOptions()
{
    global $db;
    $options = array(); // PHP 8 Fix: Initialize array
    $result = $db->query("SELECT `keyname`,`value` FROM hlstats_Options WHERE opttype >= 1");
    while ($rowdata = $db->fetch_row($result))
    {
        $options[$rowdata[0]] = $rowdata[1];
    }
    $db->free_result($result);
    if ( !count($options) )
    {
	error('Warning: Could not find any options in table <b>hlstats_Options</b>, database <b>' .
	    DB_NAME . '</b>. Check HLstats configuration.');
    }
    // PHP 8 Fix: Ensure numeric operation
    $minAct = isset($options['MinActivity']) ? (int)$options['MinActivity'] : 28;
    $options['MinActivity'] = $minAct * 86400;
    return $options;
}

// Test if flags exists
/**
 * getFlag()
 * 
 * @param string $flag
 * @param string $type
 * @return string Either the flag or default flag if none exists
 */
function getFlag($flag, $type='url')
{
    // PHP 8 Fix: Cast to string for strtolower
    $image = getImage('/flags/'.strtolower((string)$flag));
    if ($image)
	return $image[$type];
    else
	return IMAGE_PATH.'/flags/0.gif';
}

/**
 * valid_request()
 * 
 * @param string $str
 * @param boolean $numeric
 * @return mixed request
 */
function valid_request($str, $numeric = false)
{
    if (is_array($str)) {
        return $numeric ? -1 : '';
    }

    // Allow Unicode letters (including Hungarian), numbers, spaces and safe punctuation
    $str = preg_replace('/[^\p{L}\p{N}\[\]*.,=()!"$%&^`\':;?#+~_\-|<>\/\\\\@{ }]/u', '', (string)$str);

    if (!$numeric) {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    if (is_numeric($str)) {
        return intval($str);
    }

    return -1;
}

/**
 * timestamp_to_str()
 * 
 * @param integer $timestamp
 * @return string Formatted Timestamp
 */
function timestamp_to_str($seconds)
{
    // We allow passing an empty parameter, for output in html
    if (empty($seconds)) {
	return '---';
    }

    // If something other than int or float is passed here, then return 'Undefined'
    if (!is_numeric($seconds)) {
	return "Undefined";
    }

    // DateTime class doesn't work with float type,
    // doesn't matter we don't need microsecond precision :D
    $seconds = round((float)$seconds);

    $dtF = new \DateTime('@0');
    $dtT = new \DateTime("@$seconds");

    return $dtF->diff($dtT)->format('%ad&nbsp;%H:%I:%Sh');
}

/**
 * error()
 * Formats and outputs the given error message. Optionally terminates script
 * processing.
 * 
 * @param mixed $message
 * @param bool $exit
 * @return void
 */
function error($message, $exit = true)
{
    global $g_options;
?>
<table border="1" cellspacing="0" cellpadding="5">
<tr>
<td class="errorhead">ERROR</td>
</tr>
<tr>
<td class="errortext"><?php echo $message; ?></td>
</tr>
</table>
<?php if ($exit)
	exit;
}


//
// string makeQueryString (string key, string value, [array notkeys])
//
// Generates an HTTP GET query string from the current HTTP GET variables,
// plus the given 'key' and 'value' pair. Any current HTTP GET variables
// whose keys appear in the 'notkeys' array, or are the same as 'key', will
// be excluded from the returned query string.
//

/**
 * makeQueryString()
 * 
 * @param mixed $key
 * @param mixed $value
 * @param mixed $notkeys
 * @return
 */
function makeQueryString($key, $value, $notkeys = array())
{
    if (!is_array($notkeys)) {
        $notkeys = array();
    }

    $querystring = '';
    foreach ($_GET as $k => $v) {
        if (is_array($v)) {
            continue;
        }

        if ($k && $k != $key && !in_array($k, $notkeys)) {
            $querystring .= urlencode((string)$k) . '=' . rawurlencode((string)$v) . '&amp;';
        }
    }

    $querystring .= urlencode((string)$key) . '=' . urlencode((string)$value);

    return $querystring;
}

//
// void pageHeader (array title, array location)
//
// Prints the page heading.
//

/**
 * pageHeader()
 * 
 * @param mixed $title
 * @param mixed $location
 * @return
 */
function pageHeader($title = '', $location = '')
{
    global $db, $g_options;
    if ( defined('PAGE') && PAGE == 'HLSTATS' )
	include (PAGE_PATH . '/header.php');
    elseif ( defined('PAGE') && PAGE == 'INGAME' )
	include (PAGE_PATH . '/ingame/header.php');
}


//
// void pageFooter (void)
//
// Prints the page footer.
//

/**
 * pageFooter()
 * 
 * @return
 */
function pageFooter()
{
    global $g_options;
    if ( defined('PAGE') && PAGE == 'HLSTATS' )
	include (PAGE_PATH . '/footer.php');
    elseif ( defined('PAGE') && PAGE == 'INGAME' )
	include (PAGE_PATH . '/ingame/footer.php');
}

/**
 * getSortArrow()
 * 
 * @param mixed $sort
 * @param mixed $sortorder
 * @param mixed $name
 * @param mixed $longname
 * @param string $var_sort
 * @param string $var_sortorder
 * @param string $sorthash
 * @return string Returns the code for a sort arrow <IMG> tag.
 */
function getSortArrow($sort, $sortorder, $name, $longname, $var_sort = 'sort', $var_sortorder =
    'sortorder', $sorthash = '', $ajax = false)
{
    global $g_options;

    if ($sortorder == 'asc')
    {
	$sortimg = 'sort-ascending.gif';
	$othersortorder = 'desc';
    }
    else
    {
	$sortimg = 'sort-descending.gif';
	$othersortorder = 'asc';
    }
    
    $arrowstring = '<a href="' . $g_options['scripturl'] . '?' . makeQueryString($var_sort, $name,
	array($var_sortorder));

    if ($sort == $name)
    {
	$arrowstring .= "&amp;$var_sortorder=$othersortorder";
	$jsarrow = "'" . $var_sortorder . "': '" . $othersortorder . "'";
    }
    else
    {
	$arrowstring .= "&amp;$var_sortorder=$sortorder";
	$jsarrow = "'" . $var_sortorder . "': '" . $sortorder . "'";
    }

    if ($sorthash)
    {
	$arrowstring .= "#$sorthash";
    }

    $arrowstring .= '" class="head"';
    
    if ( $ajax )
    {
	$arrowstring .= " onclick=\"Tabs.refreshTab({'$var_sort': '$name', $jsarrow}); return false;\"";
    }
    
    $arrowstring .= ' title="Change sorting order">' . "$longname</a>";

    if ($sort == $name)
    {
	$arrowstring .= '&nbsp;<img src="' . IMAGE_PATH . "/$sortimg\"" .
	    " style=\"padding-left:4px;padding-right:4px;\" alt=\"$sortimg\" />";
    }


    return $arrowstring;
}

/**
 * getSelect()
 * Returns the HTML for a SELECT box, generated using the 'values' array.
 * Each key in the array should be a OPTION VALUE, while each value in the
 * array should be a corresponding descriptive name for the OPTION.
 * 
 * @param mixed $name
 * @param mixed $values
 * @param string $currentvalue
 * @return The 'currentvalue' will be given the SELECTED attribute.
 */
function getSelect($name, $values, $currentvalue = '')
{
    $select = "<select name=\"" . hlx_h($name) . "\" style=\"width:300px;\">\n";

    $gotcval = false;

    foreach ($values as $k => $v)
    {
	$select .= "\t<option value=\"" . hlx_h($k) . "\"";

	if ($k == $currentvalue)
	{
	    $select .= ' selected="selected"';
	    $gotcval = true;
	}

	$select .= ">" . hlx_h($v) . "</option>\n";
    }

    if ($currentvalue && !$gotcval)
    {
	$select .= "\t<option value=\"" . hlx_h($currentvalue) . "\" selected=\"selected\">" . hlx_h($currentvalue) . "</option>\n";
    }

    $select .= '</select>';

    return $select;
}

/**
 * getLink()
 * 
 * @param mixed $url
 * @param integer $maxlength
 * @param string $type
 * @param string $target
 * @return
 */

function getLink($url, $type = 'http://', $target = '_blank')
{
    $url = trim((string)$url);
    $urld = parse_url($url);

    if ($urld === false) {
        return 'Invalid Url :(';
    }

    // Bare host without scheme ("example.com/path?x=1"): retry with the default scheme
    if (!isset($urld['scheme']) && !isset($urld['host']) && isset($urld['path'])) {
        $urld = parse_url(rtrim((string)$type, ':/') . '://' . $url);
        if ($urld === false) {
            return 'Invalid Url :(';
        }
    }

    $scheme = isset($urld['scheme']) ? strtolower($urld['scheme']) : '';
    if (($scheme !== 'http' && $scheme !== 'https') || empty($urld['host'])) {
        return 'Invalid Url :(';
    }

    $host = $urld['host'];
    if (!preg_match('/^[\p{L}\p{N}.\-]+$|^\[[0-9A-Fa-f:.]+\]$/u', $host)) {
        return 'Invalid Url :(';
    }

    $port     = isset($urld['port']) ? ':' . (int)$urld['port'] : '';
    $path     = isset($urld['path']) ? str_replace(' ', '%20', $urld['path']) : '';
    $query    = (isset($urld['query']) && $urld['query'] !== '') ? '?' . $urld['query'] : '';
    $fragment = (isset($urld['fragment']) && $urld['fragment'] !== '') ? '#' . $urld['fragment'] : '';

    $host_uri = $host . $port . $path . $query . $fragment;

    // Attribute values are always escaped; rel prevents window.opener abuse
    return sprintf('<a href="%s://%s" target="%s" rel="noopener noreferrer">%s</a>',
        hlx_h($scheme), hlx_h($host_uri), hlx_h($target), hlx_h($host_uri));
}

/**
 * getEmailLink()
 * 
 * @param string $email
 * @param integer $maxlength
 * @return string Formatted email tag
 */
function getEmailLink($email, $maxlength = 40)
{
    $email = trim((string)$email);

    if (filter_var($email, FILTER_VALIDATE_EMAIL))
    {
    if (mb_strlen($email, 'UTF-8') > $maxlength)
    {
        $email_title = mb_substr($email, 0, $maxlength - 3, 'UTF-8') . '...';
    }
    else
    {
        $email_title = $email;
    }

    return '<a href="mailto:' . hlx_h($email) . '">' . hlx_h($email_title) . '</a>';
    }
    else
    {
    return '';
    }
}

/**
 * getImage()
 * 
 * @param string $filename
 * @return mixed Either the image if exists, or false otherwise
 */
function getImage($filename)
{
    static $cache = array();

    $filename = (string)$filename;

    // Block path traversal and null bytes (weapon/role codes come from game server logs)
    if (strpos($filename, '..') !== false || strpos($filename, "\0") !== false) {
    return false;
    }

    if (array_key_exists($filename, $cache)) {
    return $cache[$filename];
    }

    $result = false;

    if (preg_match('/^(.*\/)(.+)$/', $filename, $matches)) {
    $relpath = $matches[1];
    $realfilename = $matches[2];

    $path = IMAGE_PATH . $filename;
    $url = IMAGE_PATH . $relpath . rawurlencode($realfilename);

    // check if image exists
    if (file_exists($path . '.png'))
    {
        $ext = 'png';
    } elseif (file_exists($path . '.gif'))
    {
        $ext = 'gif';
    } elseif (file_exists($path . '.jpg'))
    {
        $ext = 'jpg';
    }
    else
    {
        $ext = '';
    }

    if ($ext)
    {
        $size = @getimagesize("$path.$ext");
            if ($size) {
            $result = array('url' => "$url.$ext", 'path' => "$path.$ext", 'width' => $size[0], 'height' => $size[1], 'size' => $size[3]);
            }
    }
    }

    // Keep the cache bounded on very long-running requests
    if (count($cache) > 2000) {
    $cache = array();
    }
    $cache[$filename] = $result;

    return $result;
}

function mystripslashes($text)
{
    // Deprecated, throws an warning in php 7.4 and above
    // return get_magic_quotes_gpc() ? stripslashes($text) : $text;
    return $text;
}

function getRealGame($game)
{
    global $db;
    $game_esc = $db->escape((string)$game);
    $result = $db->query("SELECT realgame from hlstats_Games WHERE code='$game_esc'");
    // PHP 8 Fix: Replace list() which fails on empty result
    $row = $db->fetch_row($result);
    $db->free_result($result);
    $realgame = ($row) ? $row[0] : '';
    return $realgame;
}

function printSectionTitle($title)
{
    echo '<span class="fHeading">&nbsp;<img src="'.IMAGE_PATH."/downarrow.gif\" alt=\"\" />&nbsp;$title</span><br /><br />\n";
}

function getStyleText($style)
{
    return "\t<link rel=\"stylesheet\" type=\"text/css\" href=\"./css/$style.css\" />\n";
}

function getJSText($js)
{
    return "\t<script type=\"text/javascript\" src=\"".INCLUDE_PATH."/js/$js.js\"></script> \n";
}

function get_player_rank($playerdata) {
    global $db, $g_options;

    $rank = 0;
    $tempdeaths = (int)($playerdata['deaths'] ?? 0);
    if ($tempdeaths == 0)
        $tempdeaths = 1;

    $game_esc = $db->escape((string)($playerdata['game'] ?? ''));
    $rankingtype = (string)($g_options['rankingtype'] ?? 'skill');
    // Ensure rankingtype is safe (usually kills or skill)
    if ($rankingtype !== 'kills' && $rankingtype !== 'skill') $rankingtype = 'skill';

    $player_rank_val = $db->escape((string)($playerdata[$rankingtype] ?? 0));
    $player_kills = (float)($playerdata['kills'] ?? 0);
    $query = "
	SELECT
	    COUNT(*)
	FROM
	    hlstats_Players
	WHERE
	    game='$game_esc'
	    AND hideranking = 0
	    AND kills >= 1
	    AND (
		    (".$rankingtype." > '".$player_rank_val."') OR (
			(".$rankingtype." = '".$player_rank_val."') AND (kills/IF(deaths=0,1,deaths) > ".($player_kills/$tempdeaths).")
		    )
	    )
    ";
    $result = $db->query($query);
    $row = $db->fetch_row($result);
    $db->free_result($result);
    $rank = ($row) ? (int)$row[0] : 0;
    $rank++;

    return $rank;
}

/**
 * Convert colors Usage:  color::hex2rgb("FFFFFF")
 * 
 * @author      Tim Johannessen <root@it.dk>
 * @version    1.0.1
 */
function hex2rgb($hexVal = '')
{
    $hexVal = preg_replace('/[^a-fA-F0-9]/', '', (string)$hexVal);
    if (strlen($hexVal) == 3)
    {
        $hexVal = $hexVal[0] . $hexVal[0] . $hexVal[1] . $hexVal[1] . $hexVal[2] . $hexVal[2];
    }
    if (strlen($hexVal) != 6)
    {
        return array('red' => 0, 'green' => 0, 'blue' => 0);
    }
    $arrTmp = array_map('hexdec', str_split($hexVal, 2));
    return array('red' => $arrTmp[0] ?? 0, 'green' => $arrTmp[1] ?? 0, 'blue' => $arrTmp[2] ?? 0);
}
