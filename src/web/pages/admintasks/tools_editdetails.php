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

    global $auth, $task, $g_options, $selTask;

    // PHP 8 Fix: Null coalescing check
    if (($auth->userdata["acclevel"] ?? 0) < 80) {
        die ("Access denied!");
    }

?>

&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" class="imageformat" alt="" /><b>&nbsp;<?php echo htmlspecialchars($task->title ?? '', ENT_QUOTES, 'UTF-8'); ?></b><br /><br />

<span style="padding-left:35px;">You can enter a player or clan ID number directly, or you can search for a player or clan.</span><br /><br />

<div class="block">
    <?php printSectionTitle('Jump Direct'); ?>
    <div class="subblock">
        <form method="get" action="<?php echo htmlspecialchars($g_options['scripturl'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="mode" value="admin" />
            <table class="data-table border" style="width:30%;" cellspacing="1" cellpadding="4">
                <tr style="vertical-align:middle;" class="bg1">
                    <td style="white-space:nowrap;width:30%;">Type:</td>
                    <td style="width:70%;">
                        <?php
                            echo getSelect("task",
                                array(
                                    "tools_editdetails_player"=>"Player",
                                    "tools_editdetails_clan"=>"Clan"
                                )
                            );
                        ?>
                    </td>
                </tr>
                <tr style="vertical-align:middle;" class="bg1">
                    <td style="white-space:nowrap;width:30%;">ID Number:</td>
                    <td style="width:70%;">
                        <input type="text" name="id" size="15" maxlength="12" class="textbox" />
                    </td>
                </tr>
                <tr class="bg1">
                    <td colspan="2" style="text-align:center;">
                        <input type="submit" value=" Edit &gt;&gt; " class="submit" />
                    </td>
                </tr>
            </table>
        </form>
    </div>
</div><br /><br />

<?php
    require(PAGE_PATH . "/search-class.php");

    // PHP 8 Fix: Ensure string type and existence
    $sr_query = trim((string)($_GET["q"] ?? ""));

    if ($sr_query !== '') {
        $search_pattern  = array("/script/i", "/;/", "/%/");
        $replace_pattern = array("", "", "");
        $sr_query = preg_replace($search_pattern, $replace_pattern, $sr_query);
    }

    // PHP 8 Fix: Handle input keys safely
    $st_input = $_GET["st"] ?? "";
    $sr_type = valid_request($st_input, false);
    if (!$sr_type) {
        $sr_type = "player";
    }

    $game_input = $_GET["game"] ?? "";
    $sr_game = valid_request($game_input, false);

    $search = new Search($sr_query, $sr_type, $sr_game);

    $search->drawForm(array(
        "mode"=>"admin",
        "task"=>$selTask ?? ($_GET['task'] ?? 'tools_editdetails')
    ));

    if ($sr_query !== '')
    {
        $search->drawResults(
            "mode=admin&task=tools_editdetails_player&id=%k",
            "mode=admin&task=tools_editdetails_clan&id=%k"
        );
    }
?>