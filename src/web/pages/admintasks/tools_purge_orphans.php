<?php
/*
   HLstatsX Modernized Edition -  Maintenance Utility
   Engineering Audit: High-Performance Cleanup with Hidden-status and Server-ID validation.
   Version: 1.0
   Project: https://github.com/lovasatt/hlstatsx-community-edition
   Author:  lovasatt (2026)
*/

if (!defined('IN_HLSTATS')) {
    die('Do not access this file directly.');
}

global $db, $auth, $task, $g_options;

if (($auth->userdata['acclevel'] ?? 0) < 100) {
    echo "Access denied!";
    return;
}
?>

&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" class="imageformat" alt="" /><strong>&nbsp;<?php echo htmlspecialchars($task->title); ?></strong><br /><br />

<?php
if (isset($_POST['confirm']))
{
    // --- SERVER & RESOURCE OPTIMIZATION ---
    @set_time_limit(600);
    @ini_set('memory_limit', '256M');
    @ignore_user_abort(true);

    echo "<ul>\n";

    // 1. DATA GATHERING (Associative Arrays for O(1) Lookup)
    $active_games = array();
    $result = $db->query("SELECT code FROM hlstats_Games WHERE hidden = '0'");
    while ($row = $db->fetch_row($result)) {
        if (isset($row[0])) $active_games[(string)$row[0]] = true;
    }

    $active_servers = array();
    $result = $db->query("SELECT serverId FROM hlstats_Servers WHERE game IN (SELECT code FROM hlstats_Games WHERE hidden = '0')");
    while ($row = $db->fetch_row($result)) {
        if (isset($row[0])) $active_servers[(string)$row[0]] = true;
    }

    $active_players = array();
    $result = $db->query("SELECT playerId FROM hlstats_Players");
    while ($row = $db->fetch_row($result)) {
        if (isset($row[0])) $active_players[(string)$row[0]] = true;
    }

    // 2. DATABASE PURGE
    echo "<li>Cleaning Table: hlstats_Trend ... ";
    if (!empty($active_games)) {
        $code_list = "'" . implode("','", array_map(array($db, 'escape'), array_keys($active_games))) . "'";
        $db->query("DELETE FROM hlstats_Trend WHERE game NOT IN ($code_list)");
    } else {
        $db->query("TRUNCATE TABLE hlstats_Trend");
    }
    echo "OK</li>\n";

    echo "<li>Cleaning Table: hlstats_server_load ... ";
    if (!empty($active_servers)) {
        $serv_list = implode(",", array_map('intval', array_keys($active_servers)));
        $db->query("DELETE FROM hlstats_server_load WHERE server_id NOT IN ($serv_list)");
    } else {
        $db->query("TRUNCATE TABLE hlstats_server_load");
    }
    echo "OK</li>\n";

    // 3. FILESYSTEM PURGE (Stream-based with Path Fallback)
    echo "<li>Streaming progress folder for cleanup ... ";
    $del_count = 0;

    // DEFENSIVE PATH RESOLUTION
    $base_rel_path = IMAGE_PATH . '/progress';
    $progress_dir = realpath($base_rel_path) ?: $base_rel_path;

    if (is_dir($progress_dir)) {
        if ($handle = opendir($progress_dir)) {
            $expire_time = time() - 86400;

            while (false !== ($filename = readdir($handle))) {
                if ($filename == "." || $filename == "..") continue;

                // Modern PHP 8 string check
                if (!str_ends_with($filename, '.png')) continue;

                $parts = explode('_', $filename);
                $should_delete = false;
                $full_file_path = $progress_dir . '/' . $filename;

                // A: server_*.png
                if ($parts[0] == 'server' && isset($parts[4])) {
                    $g_code = (string)$parts[4];
                    $s_type = $parts[3] ?? '0';
                    $s_id   = isset($parts[5]) ? str_replace('.png', '', $parts[5]) : '';

                    if (!isset($active_games[$g_code])) { 
                        $should_delete = true; 
                    } elseif ($s_type == '0' && $s_id !== '' && !isset($active_servers[$s_id])) {
                        $should_delete = true;
                    }
                }
                // B: sig_*.png
                elseif ($parts[0] == 'sig' && isset($parts[1])) {
                    $pid = str_replace('.png', '', $parts[1]);
                    if (!isset($active_players[$pid])) $should_delete = true;
                }
                // C: trend_*.png
                elseif ($parts[0] == 'trend' && isset($parts[1])) {
                    $pid = (string)$parts[1];
                    if (!isset($active_players[$pid]) || (@filemtime($full_file_path) < $expire_time)) {
                        $should_delete = true;
                    }
                }

                if ($should_delete) {
                    if (@unlink($full_file_path)) {
                        $del_count++;
                    }
                }
            }
            closedir($handle);
            echo "OK ($del_count files removed)</li>\n";
        } else {
            echo "<span style='color:orange;'>ERROR (Could not open directory stream)</span></li>";
        }
    } else {
        echo "<span style='color:orange;'>ERROR (Directory not found: $progress_dir)</span></li>";
    }

    echo "</ul>\n";
    echo "Done.<br /><br />";
}
else
{
?>
<form name="resetform" method="post">
<table width="600" align="center" border="0" cellspacing="0" cellpadding="0" class="border">
<tr>
    <td>
        <table width="100%" border="0" cellspacing="1" cellpadding="10">
        <tr class="bg1">
            <td class="fNormal" align="center">
                <p>This tool performs a cleanup based on the <strong>Hidden</strong> status of games:</p>
                <ul style="text-align:left; display:inline-block; line-height: 1.5em;">
                    <li>Purge database stats for all games set to <strong>Hidden</strong>.</li>
                    <li>Delete graph images for all games set to <strong>Hidden</strong>.</li>
                    <li>Remove images for servers no longer in the active database.</li>
                    <li>Clean up player signatures and expired cache files.</li>
                </ul>
                <br /><br />
                <strong>Note:</strong> This process is irreversible. Due to the high number of players (29k+), the cleanup may take up to 1 minute to complete. Please do not navigate away until the 'Done' message appears.<br /><br />
                <input type="hidden" name="confirm" value="1" />
                <input type="submit" value="  Click here to confirm Purge  " />
            </td>
        </tr>
        </table>
    </td>
</tr>
</table>
</form>
<?php
}
?>