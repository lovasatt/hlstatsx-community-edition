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

    global $db, $auth;

    // PHP 8 Fix: Null coalescing check
    if (($auth->userdata["acclevel"] ?? 0) < 80) {
        die ("Access denied!");
    }

    $edlist = new EditList("id", "hlstats_ClanTags", "clan", false);
    $edlist->columns[] = new EditListColumn("pattern", "Pattern", 40, true, "text", "", 64);
    $edlist->columns[] = new EditListColumn("position", "Match Position", 0, true, "select", "EITHER/EITHER;START/START only;END/END only");

    if (!empty($_POST))
    {
        if ($edlist->update())
            message("success", "Operation successful.");
        else
            message("warning", $edlist->error());
    }

?>

<p>Here you can define the patterns used to determine what clan a player is in. These patterns are applied to player&apos;s names when they connect or change name.</p>

<p>Special characters in the pattern:</p>

<table class="data-table" style="width:75%;margin:10px auto;">
<tr class="head data-table-head">
    <th class="fSmall" align="left" style="width:25%;">Character</th>
    <th class="fSmall" align="left" style="width:75%;">Description</th>
</tr>
<tr class="bg1" style="vertical-align:middle;">
    <td class="fSmall">A</td>
    <td class="fSmall">Matches one character (i.e. a character is required)</td>
</tr>
<tr class="bg2" style="vertical-align:middle;">
    <td class="fSmall">X</td>
    <td class="fSmall">Matches zero or one characters (i.e. a character is optional)</td>
</tr>
<tr class="bg1" style="vertical-align:middle;">
    <td class="fSmall">a</td>
    <td class="fSmall">Matches literal A or a</td>
</tr>
<tr class="bg2" style="vertical-align:middle;">
    <td class="fSmall">x></td>
    <td class="fSmall">Matches literal X or x</td>
</tr>
</table><br />

<p>Example patterns:</p>

<table class="data-table" style="width:75%;margin:10px auto;">
<tr class="head data-table-head">
    <th class="fSmall" align="left" style="width:15%;">Pattern</th>
    <th class="fSmall" align="left" style="width:55%;">Description</th>
    <th class="fSmall" align="left" style="width:30%;">Example</th>
</tr>
<tr class="bg1" style="vertical-align:middle;">
    <td class="fSmall">[AXXXXX]</td>
    <td class="fSmall">Matches 1 to 6 characters inside square braces</td>
    <td class="fSmall">[ZOOM]Player</td>
</tr>
<tr class="bg2" style="vertical-align:middle;">
    <td class="fSmall">{AAXX}</tt></td>
    <td class="fSmall">Matches 2 to 4 characters inside curly braces</td>
    <td class="fSmall">{S3G}Player</tt></td>
</tr>
<tr class="bg1" style="vertical-align:middle;">
    <td class="fSmall">rex>></tt></td>
    <td class="fSmall">Matches the string "rex>>", "REX>>", etc.</td>
    <td class="fSmall">REX>>Tyranno</td>
</tr>
</table><br />

<p>Avoid adding patterns to the database that are too generic. Always ensure you have at least one literal (non-special) character in the pattern -- for example if you were to add the pattern "AXXA", it would match any player with 2 or more letters in their name!</p>

<p>The Match Position field sets which end of the player&apos;s name the clan tag is allowed to appear.</p>

<?php

    $result = $db->query("
        SELECT
            id,
            pattern,
            position
        FROM
            hlstats_ClanTags
        ORDER BY
            id
    ");

    $edlist->draw($result);
?>

<table width="75%" border="0" cellspacing="0" cellpadding="0" style="margin:15px auto;">
<tr>
    <td align="center"><input type="submit" value="  Apply  " class="submit" /></td>
</tr>
</table>