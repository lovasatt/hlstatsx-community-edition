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

    // Awards Info Page

    // Initialize variables
    $game = isset($game) ? (string)$game : '';
    // Security: Escape game variable
    $game_esc = $db->escape($game);
    $game_url = urlencode($game);

    $res_game = $db->query("SELECT name FROM hlstats_Games WHERE code = '$game_esc'");
    if ($db->num_rows($res_game) < 1) {
        error("No such game '" . htmlspecialchars($game, ENT_QUOTES, 'UTF-8') . "'.");
    }

    // PHP 8 Fix: Replace list() to prevent fatal error on empty result
    $row = $db->fetch_row($res_game);
    $gamename = ($row) ? (string)$row[0] : ucfirst($game);
    $db->free_result($res_game);

    // PHP 8 Fix: Null coalescing and type casting
    $type_in = isset($_GET['type']) ? (string)$_GET['type'] : '';
    $type = valid_request($type_in, false);

    $tab_in = isset($_GET['tab']) ? (string)$_GET['tab'] : '';
    $tab = valid_request($tab_in, false);

    if ($type == 'ajax' )
    {
        $allowed_tabs = array('daily', 'global', 'ranks', 'ribbons');
        // PHP 8 Fix: Correct regex syntax delimiters and allow pipe separator
        $tabs = explode('|', preg_replace('/[^a-z|]/', '', strtolower((string)$tab)));

        foreach ( $tabs as $t )
        {
            // Security: Ensure filename contains only safe characters
            $t = preg_replace('/[^a-z]/', '', (string)$t);
            if (empty($t) || !in_array($t, $allowed_tabs, true)) continue;

            if ( file_exists(PAGE_PATH . '/awards_' . $t . '.php') )
            {
                @include(PAGE_PATH . '/awards_' . $t . '.php');
            }
        }
        exit;
    }

    pageHeader(
        array($gamename, 'Awards Info'),
        array($gamename => "%s?game=$game_url", 'Awards Info' => '')
    );
?>

<?php
// PHP 8 Fix: Ensure array key exists
if (isset($g_options['playerinfo_tabs']) && (string)$g_options['playerinfo_tabs'] === '1') {
?>

<div id="main">
    <ul class="subsection_tabs" id="tabs_submenu">
        <li><a href="#" id="tab_daily">Daily&nbsp;Awards</a></li>
        <li><a href="#" id="tab_global">Global&nbsp;Awards</a></li>
        <li><a href="#" id="tab_ranks">Ranks</a></li>
        <li><a href="#" id="tab_ribbons">Ribbons</a></li>
    </ul>
<br />
<div id="main_content"></div>
<?php
if ($tab)
{
    $defaulttab = $tab;
}
else
{
    $defaulttab = 'daily';
}
// Security: Escape variables for JavaScript literal context
$game_js = addslashes($game);
$defaulttab_js = addslashes((string)$defaulttab);

echo "<script type=\"text/javascript\">
    new Tabs($('main_content'), $$('#main ul.subsection_tabs a'), {
        'mode': 'awards',
        'game': '$game_js',
        'loadingImage': '" . IMAGE_PATH . "/ajax.gif',
        'defaultTab': '$defaulttab_js'
    });
</script>";
?>

</div>


<?php } else {

    echo "\n<div id=\"daily\">\n";
    include PAGE_PATH.'/awards_daily.php';
    echo "\n</div>\n";

    echo "\n<div id=\"global\">\n";
    include PAGE_PATH.'/awards_global.php';
    echo "\n</div>\n";

    echo "\n<div id=\"ranks\">\n";
    include PAGE_PATH.'/awards_ranks.php';
    echo "\n</div>\n";

    echo "\n<div id=\"ribbons\">\n";
    include PAGE_PATH.'/awards_ribbons.php';
    echo "\n</div>\n";

}
?>