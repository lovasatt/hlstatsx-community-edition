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

    global $db, $auth, $task;

    // PHP 8 Fix: Null coalescing check
    if (($auth->userdata["acclevel"] ?? 0) < 80) {
        die ("Access denied!");
    }
?>

&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" class="imageformat" alt="" /><b>&nbsp;<?php echo htmlspecialchars($task->title ?? '', ENT_QUOTES, 'UTF-8'); ?></b><br /><br />

<?php

   $servers = array();
   $servers[0] = array(
       "name"   => "ELstatsNEO Masterserver",
       "host"   => "master.elstatsneo.de",
       "port"   => 27801,
       "packet" => chr(255).chr(255)."Z".chr(255)."1.00".chr(255).chr(255).chr(255)
   );

   $servers[1] = array(
       "name"   => "HLstatsX Masterserver (doesn't work anymore)",
       "host"   => "master.hlstatsx.com",
       "port"   => 27501,
       "packet" => chr(255).chr(255)."Z".chr(255)
   );

   function hide_cheaters($query)  {
     global $db;
     $result      = $db->query($query);
     $cheater     = array();
     $base_query  = "UPDATE hlstats_Players SET last_event = IF(hideranking <> 2, UNIX_TIMESTAMP(), last_event), hideranking = 2 WHERE playerId IN ";
     $insert_part = "";
     $first       = 0;

     // PHP 8 Fix: Replace list()
     while ($row = $db->fetch_row($result))  {
        $player_id = (int)$row[0];
        if ($first == 0)
          $insert_part = "(" . $player_id;
        else
          $insert_part .= "," . $player_id;
        $first++;
     }
     if ($first > 0) {
       echo "<li>Updating <b>$first</b> cheaters... ";
       $insert_part .= ")";
       $update_query = $base_query . $insert_part;
       $db->query($update_query);
       echo "<b>OK</b></li>\n";
     }
   }


    if (isset($_POST['confirm']))
    {
      echo "<ul>\n";
      $s_id = isset($_POST['masterserver']) ? (int)$_POST['masterserver'] : 0;

      if (!isset($servers[$s_id])) {
          echo "<li>Invalid masterserver selected.</li></ul>\n";
      } else {
          $host = $servers[$s_id]["host"];
          $port = (int)$servers[$s_id]["port"];

          echo "<li>Requesting cheaterlist from <b>" . htmlspecialchars($host, ENT_QUOTES, 'UTF-8') . ":$port</b>... ";

          if (!function_exists('socket_create')) {
              echo "<b>PHP Sockets extension is not enabled.</b></li></ul>\n";
              return;
          }

          $host_ip = gethostbyname($host);
          $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

          if ($socket === false) {
               echo "<b>Socket create failed</b></li></ul>\n";
          } else {
              $packet = $servers[$s_id]["packet"];
              $bytes_sent = socket_sendto($socket, $packet, strlen($packet), 0, $host_ip, $port);
              echo "<b>" . (int)$bytes_sent . "</b> bytes <b>OK</b></li>\n";

              echo "<li>Retrieving data from masterserver... ";
              $recv_bytes = 0;
              $buffer     = "";
              $timeout    = 30;
              $answer     = "";
              $packets    = 0;
              $write      = NULL;
              $except     = NULL;

              while (true) {
                $read = array($socket);
                // Force int casting for timeout
                $num_changed = socket_select($read, $write, $except, (int)$timeout);

                if ($num_changed > 0) {
                    $from_host = '';
                    $from_port = 0;
                    $recv_bytes += socket_recvfrom($socket, $buffer, 2000, 0, $from_host, $from_port);
                    if (strlen($buffer) > 8 && ($buffer[0] == chr(255)) && ($buffer[1] == chr(255)) && ($buffer[2] == "Z") && ($buffer[3] == chr(255)) &&
                        ($buffer[4] == "1") && ($buffer[5] == ".") && ($buffer[6] == "0") && ($buffer[7] == "0") && ($buffer[8] == chr(255))) {
                      $answer .= substr($buffer, 9);
                    }
                    $buffer     = "";
                    $timeout    = 1;
                    $packets++;
                } else {
                    break;
                }
              }

              $steam_ids = explode(chr(255), $answer);
              // Remove last empty element if exists
              if (end($steam_ids) === "") {
                  array_pop($steam_ids);
              }

              echo "receiving <b>$recv_bytes</b> bytes in <b>$packets</b> packets... <b>" . count($steam_ids) . "</b> cheaters... <b>OK</b></li>\n";

              $query       = "SELECT playerId FROM hlstats_PlayerUniqueIds WHERE uniqueId in ";
              $insert_part = "";
              $first       = 0;

              foreach ($steam_ids as $entry) {
                // PHP 8 Fix: Escape string
                $entry_esc = $db->escape($entry);

                if ($first == 0)
                  $insert_part = "('" . $entry_esc . "'";
                else
                  $insert_part .= ",'" . $entry_esc . "'";
                $first++;

                if ($first % 50 == 0)  {
                  $insert_part .= ")";
                  $select_query = $query . $insert_part;
                  hide_cheaters($select_query);
                  $insert_part = "";
                  $first       = 0;
                }
              }
              if ($first > 0) {
                $insert_part .= ")";
                $select_query = $query . $insert_part;
                hide_cheaters($select_query);
              }

              echo "<li>Closing connection to masterserver... ";

              socket_close($socket);
              echo "<b>OK</b></li>\n";
              echo "</ul>\n";
          }
      }
    } else {

?>

<form method="post">
<table width="60%" border="0" cellspacing="0" cellpadding="0" class="border" style="margin:15px auto;">

<tr>
    <td>
        <table width="100%" border="0" cellspacing="1" cellpadding="10">

        <tr class="bg1">
            <td class="fNormal" style="text-align:center;">

If you synchronize with one of the selected master servers, some players may be marked as cheater. You will see them on your VAC Cheater list!<br /><br />
Choose preferred masterserver:
<select name="masterserver">
<?php
  $i = 0;
  foreach ($servers as $server) {
      echo '<option value="' . $i . '">' . htmlspecialchars($server["name"], ENT_QUOTES, 'UTF-8') . '</option>';
      $i++;
  }
?>
</select><br /><br />

<input type="hidden" name="confirm" value="1" />
<div style="text-align:center;margin-top:15px;"><input type="submit" value="  Synchronize Stats  " class="submit" /></div>
</td>
        </tr>

        </table></td>
</tr>

</table>
</form>

<?php
    }
?>