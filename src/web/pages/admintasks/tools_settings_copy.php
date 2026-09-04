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

    global $auth, $task;

    // PHP 8 Fix: Null coalescing check
    if (($auth->userdata['acclevel'] ?? 0) < 80) {
        die ('Access denied!');
    }
?>

&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" class="imageformat" alt="" /><b>&nbsp;<?php echo htmlspecialchars($task->title ?? '', ENT_QUOTES, 'UTF-8'); ?></b><br /><br />

<?php

function check_writable() {

    $ok = '';
    $f = IMAGE_PATH . "/games/";
    if (!is_writable($f))
        $ok .= "<li>I have no permission to write to '$f'";

    if ($ok != '') {
        echo 'FATAL:<br /><ul>';
        echo $ok;
        echo '</ul><br />Correct this before continuing';
        die();
    }
    return true;
}

function getTableFields($table, $auto_increment) {
   // get a field array of specified table
   global $db;

   $table_esc = $db->escape($table);
   $res_cols = $db->query("SHOW COLUMNS FROM `$table_esc`;");
   $res = array();
   while ($r = $db->fetch_array($res_cols))
   {
      if ((!$auto_increment) && ($r['Extra'] == 'auto_increment'))
      {
         continue;
      }
      else
      {
         $res[] = $r['Field'];
      }
   }
   return $res;
}

function copySettings($table, $game1, $game2) {
    global $db;

    $game1_esc = $db->escape($game1);
    $game2_esc = $db->escape($game2);

    $res_chk = $db->query("SELECT game FROM `$table` WHERE game='$game2_esc' LIMIT 1;");
    if ($db->num_rows($res_chk) != 0) {
        $ret = 'Target gametype exists, nothing done!';
    } else {
        $res_cnt = $db->query("SELECT COUNT(game) AS cnt FROM `$table` WHERE game='$game1_esc';");
        $r = $db->fetch_array($res_cnt);
        $cnt = (int)($r['cnt'] ?? 0);
        if ($cnt == 0) {
            $ret = 'No data existent for source gametype.';
        } else {
            $ret = $cnt . ' entries copied!';
            $fields = '';
            $ignoreFields = array('game','id','d_winner_id','d_winner_count','g_winner_id','g_winner_count','count','picked','kills','deaths','headshots');
            foreach (getTableFields($table, 0) as $field) {
                if (!in_array($field, $ignoreFields)) {
                    if ($fields != '') {
                        $fields .= ', ';
                    }
                    $fields .= "`$field`";
                }
            }
            $SQL = "INSERT INTO `$table` ($fields, `game`) SELECT $fields, '$game2_esc' FROM `$table` WHERE game='$game1_esc';";
            $db->query($SQL);
        }
    }
    return $ret . "</li>\n";
}

function mkdir_recursive($pathname) {
    is_dir(dirname($pathname)) || mkdir_recursive(dirname($pathname));
    return is_dir($pathname) || @mkdir($pathname);
}

function copyFile($source, $dest) {
    if ($source != '') {
        $source_path = IMAGE_PATH . "/games/$source";
        $dest_path = IMAGE_PATH . "/games/$dest";

        if (!is_file($source_path)) {
            $ret = "File not found $source_path (dest: $dest_path)";
        } else {
            mkdir_recursive(dirname($dest_path));
            if (!@copy($source_path, $dest_path)) {
                $ret = 'FAILED';
            } else {
                $ret = 'OK';
            }
        }
        return "Copying '$source_path' to '$dest_path': $ret</li>\n";
    }
    return '';
}

function scanCopyFiles($source, $dest) {
    global $files;
    if (!isset($files) || !is_array($files)) {
        $files = array();
    }

    $path = IMAGE_PATH . '/games/' . $source;
    if (is_dir($path)) {
        $d = dir($path);
        if ($d !== false) {
            while (($entry = $d->read()) !== false) {
                if ($entry == '.' || $entry == '..') {
                    continue;
                }
                if (is_file($path . '/' . $entry)) {
                    $files[] = array($source . '/' . $entry, $dest . '/' . $entry);
                } elseif (is_dir($path . '/' . $entry)) {
                    scanCopyFiles($source . '/' . $entry, $dest . '/' . $entry);
                }
            }
            $d->close();
        }
    }
}

    if (isset($_POST['confirm'])) {
        // PHP 8 Fix: Null coalescing
        $game1 = isset($_POST['game1']) ? $_POST['game1'] : '';
        $game2 = isset($_POST['game2']) ? $_POST['game2'] : '';
        $game2name = isset($_POST['game2name']) ? $_POST['game2name'] : '';

        echo "<ul>\n";
        check_writable();
        // PHP 8 Fix: Ensure string type
        $game2 = valid_request((string)$game2, false);
        $game2name = valid_request((string)$game2name, false);

        // Security: Escape inputs
        $game1_esc = $db->escape($game1);
        $game2_esc = $db->escape($game2);
        $game2name_esc = $db->escape($game2name);

        echo '<li>hlstats_Games ... ';
        $res_game = $db->query("SELECT code FROM hlstats_Games WHERE code='$game2_esc' LIMIT 1;");
        if ($db->num_rows($res_game) != 0) {
            echo 'FAILED (Target game code exists)</li></ul><br /><br />';
            echo '<b>Target gametype exists, nothing done!</b><br /><br />';
        } else {
            $db->query("INSERT INTO hlstats_Games (code,name,hidden,realgame) SELECT '$game2_esc', '$game2name_esc', '0', realgame FROM hlstats_Games WHERE code='$game1_esc'");
            echo "OK</li>\n";

            echo '<li>hlstats_Games_Defaults ... ';
            $db->query("INSERT INTO hlstats_Games_Defaults (code, parameter, value) SELECT '$game2_esc', parameter, value FROM hlstats_Games_Defaults WHERE code='$game1_esc'");
            echo "OK</li>\n";

            $dbtables = array(
                'hlstats_Actions',
                'hlstats_Awards',
                'hlstats_Ribbons',
                'hlstats_Ranks',
                'hlstats_Roles',
                'hlstats_Teams',
                'hlstats_Weapons',
                'hlstats_Heatmap_Config'
            );

            foreach ($dbtables as $dbt) {
                echo "<li>$dbt ... ";
                echo copySettings($dbt, $game1, $game2);
            }

            echo "</ul><br /><br />\n";
            echo "<ul>\n";

            $files = array(); // Initialize as empty array before scan

            scanCopyFiles("$game1/", "$game2/");

            foreach ($files as $f) {
                echo '<li>';
                echo copyFile($f[0], $f[1]);
            }
            echo "</ul><br /><br />\n";
            echo 'Done.<br /><br />';
        }
    } else {
        $result = $db->query("SELECT code, name FROM hlstats_Games ORDER BY code;");
        $games = array();
        $games[] = '<option value="" selected="selected">Please select</option>';

        // PHP 8 Fix: Replace list()
        while ($rowdata = $db->fetch_row($result))
        {
            $code = (string)$rowdata[0];
            $name = (string)$rowdata[1];
            $games[] = '<option value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . ' - ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</option>';
        }

?>

<form method="post">
<table width="60%" border="0" cellspacing="0" cellpadding="0" class="border" style="margin:15px auto;">

<tr>
    <td>
        <table width="100%" border="0" cellspacing="1" cellpadding="10">

        <tr class="bg1">
            <td class="fNormal" style="text-align:center;">

Are you sure to copy all settings from the selected gametype to the new gametype name?<br />
All existing images will be copied also to the new gametype!<br /><br />

<input type="hidden" name="confirm" value="1" />
 Existing gametype:
 <select name="game1">
 <?php foreach ($games as $g) echo $g; ?>
 </select><br /><br />
 New gametype code:
 <input type="text" size="10" value="newcode" name="game2" class="textbox" /><br /><br />
 New gametype name:
 <input type="text" size="26" value="New Game" name="game2name" class="textbox" /><br /><br />
 <input type="submit" value="  Copy selected gametype to the new name  " class="submit" />
</td>
        </tr>
        </table></td>
</tr>

</table>
</form>
<?php
    }
?>