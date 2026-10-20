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
    die("Access denied!");
}
?>

&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" class="imageformat" alt="" /><strong>&nbsp;<?php echo htmlspecialchars($task->title ?? '', ENT_QUOTES, 'UTF-8'); ?></strong><br /><br />

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
    $result_games = $db->query("SELECT code FROM hlstats_Games WHERE hidden = '0'");
    while ($row = $db->fetch_row($result_games)) {
        if (isset($row[0])) $active_games[(string)$row[0]] = true;
    }

    $active_servers = array();
    $result_servers = $db->query("SELECT serverId FROM hlstats_Servers WHERE game IN (SELECT code FROM hlstats_Games WHERE hidden = '0')");
    while ($row = $db->fetch_row($result_servers)) {
        if (isset($row[0])) $active_servers[(string)$row[0]] = true;
    }

    $active_players = array();
    $result_players = $db->query("SELECT playerId FROM hlstats_Players");
    while ($row = $db->fetch_row($result_players)) {
        if (isset($row[0])) $active_players[(string)$row[0]] = true;
    }
    if ($result_games) { $db->free_result($result_games); }
    if ($result_servers) { $db->free_result($result_servers); }
    if ($result_players) { $db->free_result($result_players); }

    // Gather configured heatmap maps per game code (mapping realgame to code for directory alignment)
    $configured_heatmaps = array();
    $result_hm = $db->query("SELECT g.code, hc.map FROM hlstats_Heatmap_Config AS hc INNER JOIN hlstats_Games AS g ON g.realgame = hc.game");
    while ($row = $db->fetch_row($result_hm)) {
        if (isset($row[0], $row[1])) {
            $configured_heatmaps[(string)$row[0]][(string)$row[1]] = true;
        }
    }
    if ($result_hm) { $db->free_result($result_hm); }

    // 2. DATABASE PURGE
    echo "<li>Cleaning Table: hlstats_Trend ... ";
    if (!empty($active_games)) {
        $code_list = "'" . implode("','", array_map(array($db, 'escape'), array_keys($active_games))) . "'";
        $db->query("DELETE FROM hlstats_Trend WHERE game NOT IN ($code_list)");
    } else {
        $db->query("TRUNCATE TABLE hlstats_Trend");
    }
    echo "OK</li>\n";

    // Purge orphaned map statistics for hidden/disabled games
    echo "<li>Cleaning Table: hlstats_Maps_Counts ... ";
    if (!empty($active_games)) {
        $db->query("DELETE FROM hlstats_Maps_Counts WHERE game NOT IN ($code_list)");
    } else {
        $db->query("TRUNCATE TABLE hlstats_Maps_Counts");
    }
    echo "OK</li>\n";

    echo "<li>Cleaning Table: hlstats_server_load ... ";
    if (!empty($active_servers)) {
        $serv_list = implode(",", array_map('intval', array_keys($active_servers)));
        $db->query("DELETE FROM hlstats_server_load WHERE server_id NOT IN ($serv_list)");
        $db->query("DELETE FROM hlstats_Livestats WHERE server_id NOT IN ($serv_list)");
    } else {
        $db->query("TRUNCATE TABLE hlstats_server_load");
        $db->query("TRUNCATE TABLE hlstats_Livestats");
    }
    echo "OK</li>\n";

    // FIX: Only purge Heatmap Configs for games that do NOT exist in hlstats_Games at all.
    // Query checks realgame column since Heatmap_Config stores realgame identifiers.
    echo "<li>Cleaning Table: hlstats_Heatmap_Config (orphaned games only) ... ";
    $db->query("DELETE FROM hlstats_Heatmap_Config WHERE game NOT IN (SELECT realgame FROM hlstats_Games)");
    echo "OK</li>\n";

    // Clean MySQL Player Steam Cache (removes stale player profiles older than 30 days)
    echo "<li>Cleaning Table: hlstats_SteamCache ... ";
    $chk_tbl = $db->query("SHOW TABLES LIKE 'hlstats_SteamCache'");
    if ($chk_tbl && $db->num_rows($chk_tbl) > 0) {
        $expire_cutoff = time() - 2592000; // 30 days
        $db->query("DELETE FROM `hlstats_SteamCache` WHERE `updated` < $expire_cutoff");
        echo "OK</li>\n";
    } else {
        echo "Skipped (Table does not exist)</li>\n";
    }
    if ($chk_tbl) { $db->free_result($chk_tbl); }

    // 3. FILESYSTEM PURGE (Stream-based with Path Fallback)
    echo "<li>Streaming progress folder for cleanup ... ";
    $del_count = 0;

    // DEFENSIVE PATH RESOLUTION
    $base_rel_path = IMAGE_PATH . '/progress';
    $progress_dir = is_dir($base_rel_path) ? $base_rel_path : realpath($base_rel_path);

    if ($progress_dir && is_dir($progress_dir)) {
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
                    $pid = str_replace('.png', '', $parts[1]);
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
        echo "<span style='color:orange;'>ERROR (Directory not found: " . htmlspecialchars((string)$progress_dir, ENT_QUOTES, 'UTF-8') . ")</span></li>";
    }

    // 4. VOICECOMM & STEAM GROUP CACHE PURGE
    echo "<li>Cleaning Voice and Steam Group cache files ... ";
    $base_temp = defined('TEMP_PATH') ? TEMP_PATH : sys_get_temp_dir();
    $clean_base = rtrim(str_replace('\\', '/', $base_temp), '/');
    $del_count_cache = 0;
    $now = time();

    // Clean expired voice and community group cache files (Discord, TS, Ventrilo, Steam Group)
    $voice_patterns = [
        $clean_base . '/discord_widget_*.json',
        $clean_base . '/ts_query_*.json',
        $clean_base . '/ve_query_*.json',
        $clean_base . '/steam_group_*.json'
    ];

    foreach ($voice_patterns as $pattern) {
        $v_files = @glob($pattern);
        if (!empty($v_files) && is_array($v_files)) {
            foreach ($v_files as $v_file) {
                if (is_file($v_file)) {
                    if (@unlink($v_file)) $del_count_cache++;
                }
            }
        }
    }
    echo "OK ($del_count_cache expired files removed)</li>\n";

    // Helper: Execute an action only while holding an exclusive non-blocking lock on the target map
    $withMapLock = static function (string $dir, string $map, callable $action): bool {
        if (!preg_match('/\A[A-Za-z0-9_-]+\z/D', $map)) {
            return false;
        }

        $path = $dir . '/.' . $map . '-kill.lock';

        // Create lock file if missing, exactly as the generator does.
        $fp = @fopen($path, 'c');
        if (!$fp) {
            return false;
        }

        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return false;
        }

        try {
            return (bool)$action();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    };

    // 5. HEATMAP FILESYSTEM PURGE (Manifest-Verified Whitelist with In-Flight Lock Holding)
    echo "<li>Cleaning Heatmap images for hidden games and orphaned maps ... ";
    $del_count_hm = 0;
    $base_games_path = IMAGE_PATH . '/games';
    $games_base_dir  = is_dir($base_games_path) ? $base_games_path : realpath($base_games_path);

    if ($games_base_dir && is_dir($games_base_dir) && ($ghandle = opendir($games_base_dir))) {
        $now = time();
        while (false !== ($gdir = readdir($ghandle))) {
            if ($gdir == "." || $gdir == "..") continue;

            $hm_dir = $games_base_dir . '/' . $gdir . '/heatmaps';
            if (!is_dir($hm_dir)) continue;

            $is_game_active = isset($active_games[$gdir]);

            // Build precise whitelist of authorized prefixes using database maps and verified section manifests
            $allowed_file_prefixes = [];
            $configured_map_keys   = ($is_game_active && isset($configured_heatmaps[$gdir])) ? array_keys($configured_heatmaps[$gdir]) : [];

            if ($is_game_active) {
                foreach ($configured_map_keys as $active_map) {
                    $allowed_file_prefixes[$active_map . '-kill'] = $active_map;

                    // Read verified sections manifest if present to whitelist genuine sub-tiers
                    $m_file = $hm_dir . '/' . $active_map . '_sections.json';
                    if (is_file($m_file)) {
                        $raw_m = @file_get_contents($m_file);
                        if ($raw_m) {
                            $decoded_m = json_decode($raw_m, true);
                            $s_data = is_array($decoded_m) ? $decoded_m : [];
                            foreach ($s_data as $sRow) {
                                if (!is_array($sRow)) {
                                    continue;
                                }

                                $sSuf = $sRow['suffix'] ?? '';

                                if (!is_string($sSuf) || !preg_match('/\A_[A-Za-z0-9_-]+\z/D', $sSuf)) {
                                    continue;
                                }
                                if ($sSuf !== '') {
                                    $allowed_file_prefixes[$active_map . $sSuf . '-kill'] = $active_map;
                                }
                            }
                        }
                    }
                }
            }

            if ($hm_handle = opendir($hm_dir)) {
                while (false !== ($hm_file = readdir($hm_handle))) {
                    if ($hm_file == "." || $hm_file == "..") continue;
                    $full_hm_path = $hm_dir . '/' . $hm_file;

                    // A: Clean stranded temporary files older than 1h under exclusive lock
                    if (str_ends_with($hm_file, '.tmp')) {
                        $mtime = @filemtime($full_hm_path);
                        if ($mtime !== false && $mtime < ($now - 3600)) {
                            if (preg_match('/^(.+)_sections\.json\.[a-f0-9]{16}\.tmp$/', $hm_file, $m)) {
                                $base_map = $m[1];
                            } elseif (preg_match('/^(.+)-kill(?:-thumb)?\.jpg\.[a-f0-9]{16}\.tmp$/', $hm_file, $m)) {
                                $base_map = preg_replace('/_(lower|higher\d*|upper|basement)$/', '', $m[1]);
                            } else {
                                continue;
                            }
                            $base_map = preg_replace('/_(lower|higher\d*|upper|basement)$/', '', $base_map);

                            $deleted = $withMapLock($hm_dir, $base_map, static function () use ($full_hm_path) {
                                return @unlink($full_hm_path);
                            });
                            if ($deleted) $del_count_hm++;
                        }
                    }
                    // B: Preserve advisory lock files to prevent breaking coordination with running generators
                    elseif (str_ends_with($hm_file, '.lock')) {
                        // Preserved intentionally
                    }
                    // C: Clean published sections manifest safely under lock if orphaned
                    elseif (str_ends_with($hm_file, '_sections.json')) {
                        $manifest_map = substr($hm_file, 0, -strlen('_sections.json'));
                        if (!$is_game_active || !in_array($manifest_map, $configured_map_keys, true)) {
                            $deleted = $withMapLock($hm_dir, $manifest_map, static function () use ($full_hm_path) {
                                return @unlink($full_hm_path);
                            });
                            if ($deleted) $del_count_hm++;
                        }
                    }
                    // D: Clean JPG heatmaps and thumbnails using strict manifest prefix matching under lock
                    elseif (str_ends_with($hm_file, '.jpg')) {
                        $base_prefix = str_replace(array('-thumb.jpg', '.jpg'), '', $hm_file);
                        $is_authorized = ($is_game_active && isset($allowed_file_prefixes[$base_prefix]));

                        if (!$is_authorized) {
                            $map_lock_target = preg_replace('/-kill$/', '', $base_prefix);
                            $map_lock_target = preg_replace('/_(lower|higher\d*|upper|basement)$/', '', $map_lock_target);

                            $deleted = $withMapLock($hm_dir, $map_lock_target, static function () use ($full_hm_path) {
                                return @unlink($full_hm_path);
                            });
                            if ($deleted) $del_count_hm++;
                        }
                    }
                }
                closedir($hm_handle);
            }
        }
        closedir($ghandle);
        echo "OK ($del_count_hm files removed)</li>\n";
    } else {
        echo "Skipped (Games directory not found)</li>\n";
    }
    echo "</ul>\n";

    echo "Done.<br /><br />";
}
else
{
?>
<form name="resetform" method="post">
<table width="600" border="0" cellspacing="0" cellpadding="0" class="border" style="margin:15px auto;">
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
                    <li>Purge stale player Steam profile caches older than 30 days from MySQL.</li>
                    <li>Clean up expired Voice Server and Steam Group viewer cache files.</li>
                    <li>Purge generated heatmap images for games set to <strong>Hidden</strong> and orphaned maps.</li>
                </ul>
                <br /><br />
                <strong>Note:</strong> This process is irreversible. Due to the high volume of database and filesystem records, the cleanup may take up to 1 minute to complete. Please do not navigate away until the 'Done' message appears.<br /><br />
                <input type="hidden" name="confirm" value="1" />
                <input type="submit" value="  Click here to confirm Purge  " class="submit" />
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