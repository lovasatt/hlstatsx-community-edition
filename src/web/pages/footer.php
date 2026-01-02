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

    // calculate the scripttime
    global $scripttime, $db, $g_options, $mode, $redirect_to_game;

    // PHP 8 Fix: Ensure numeric types for arithmetic
    $start_time = (isset($scripttime) && is_numeric($scripttime)) ? (float)$scripttime : microtime(true);
    $scripttime = round(microtime(true) - $start_time, 4);
?>
<div style="clear:both;"></div>
<br />
<br />
    <div id="footer">
        <!-- Security: Escape constants -->
	<a href="http://www.hlxce.com" target="_blank"><img src="<?php echo htmlspecialchars(IMAGE_PATH); ?>/footer-small.png" alt="HLstatsX Community Edition" border="0" /></a>
    </div>
<br />
<div class="fSmall" style="text-align:center;">
<?php
    if (isset($_SESSION['nojs']) && $_SESSION['nojs'] == 1) {
	echo 'You are currently viewing the basic version of this page, please enable JavaScript and reload the page to access full functionality.<br />';
    }

    // PHP 8 Fix: Safe version output
    $version_out = isset($g_options['version']) ? htmlspecialchars($g_options['version']) : 'Unknown';
    echo 'Generated in real-time by <a href="http://www.hlxce.com" target="_blank">HLstatsX Community Edition ' . $version_out . '</a>';

    if (isset($g_options['showqueries']) && $g_options['showqueries'] == 1) {
        $query_count = isset($db->querycount) ? $db->querycount : 0;
	echo '
	    <br />
	    Executed ' . $query_count . " queries, generated this page in $scripttime Seconds\n";
    }
?>
<br />
All images are copyrighted by their respective owners.

<?php
    // PHP 8 Fix: Escape URL to prevent XSS
    $script_url = isset($g_options['scripturl']) ? htmlspecialchars($g_options['scripturl']) : '';
    echo '<br /><br />[<a href="' . $script_url . "?mode=admin\">Admin</a>]";

    if (isset($_SESSION['loggedin'])) {

	echo '&nbsp;[<a href="hlstats.php?logout=1">Logout</a>]';

    }
?>
</div>
</div>
<?php
    // PHP 8 Fix: Check existence of all variables before comparison
    $show_map = (isset($g_options["show_google_map"]) && $g_options["show_google_map"] == 1);
    $is_content_mode = (isset($mode) && $mode == "contents");
    $has_redirect = (isset($redirect_to_game) && $redirect_to_game > 0);

    if ($show_map && $is_content_mode && $has_redirect)
    {
	include(INCLUDE_PATH . '/google_maps.php');
	printMap();
    }
?>
</body>
</html>