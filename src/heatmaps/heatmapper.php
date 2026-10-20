<?php
/**
 * HLstatsX:CE - CS2 Heatmap Image Converter & Multi-Level Radar Pipeline
 *
 * Ingests Valve KeyValues overview definitions (.txt) using a robust recursive tokenizer.
 * Detects single and multi-level map radars, converts textures to standardized 1024x1024 JPEGs,
 * exports dynamic sidecar sections JSON files, and updates hlstats_Heatmap_Config.
 */

require_once __DIR__ . '/config.inc.php';
ini_set('memory_limit', '512M');

// =========================================================================
// DIRECTORY CONFIGURATION
// =========================================================================

$txt_dir    = __DIR__ . '/import';
$png_dir    = __DIR__ . '/import';
$output_dir = __DIR__ . '/src/cs2';
$sql_file   = __DIR__ . '/update_heatmaps.sql';
$game_code  = 'cs2';

echo "=======================================================\n";
echo " HLstatsX:CE - CS2 Heatmap Converter & Level Pipeline\n";
echo "=======================================================\n\n";

if (!is_dir($txt_dir)) {
    die("[FATAL ERROR] Overview definitions directory does not exist: $txt_dir\n");
}

if (!is_dir($png_dir)) {
    die("[FATAL ERROR] Radar textures directory does not exist: $png_dir\n");
}

if (!is_dir($output_dir) && !@mkdir($output_dir, 0775, true)) {
    die("[FATAL ERROR] Failed to create output directory: $output_dir\n");
}

if (!is_writable($output_dir)) {
    die("[FATAL ERROR] Output directory is not writable: $output_dir\n");
}

$clean_txt_dir = rtrim($txt_dir, '/\\');
$clean_png_dir = rtrim($png_dir, '/\\');
$clean_out_dir = rtrim($output_dir, '/\\');

$txt_files = glob("$clean_txt_dir/*.txt");
if (empty($txt_files)) {
    die("[FATAL ERROR] No .txt overview files found in: $clean_txt_dir\n");
}

/**
 * Token-based Valve KeyValues (VDF) recursive parser
 * Handles nested blocks, single-line C++ comments, unquoted strings, and arbitrary whitespace.
 */
function parseKeyValues(string $text): array {
    // Strip single-line comments
    $text = preg_replace('!//.*?$!m', '', $text);

    // Match quoted tokens, structural braces, or unquoted barewords
    $pattern = '/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|([{}]|[^\s"{}]+)/';
    preg_match_all($pattern, $text, $matches);
    $tokens = [];
    foreach ($matches[0] as $i => $raw) {
        if ($matches[1][$i] !== '') {
            $tokens[] = stripcslashes($matches[1][$i]);
        } else {
            $tokens[] = $raw;
        }
    }

    $index = 0;
    $parseBlock = function() use (&$tokens, &$index, &$parseBlock): array {
        $data = [];
        $key = null;
        while ($index < count($tokens)) {
            $token = $tokens[$index++];
            if ($token === '}') {
                return $data;
            } elseif ($token === '{') {
                if ($key !== null) {
                    $data[$key] = $parseBlock();
                    $key = null;
                }
            } else {
                if ($key === null) {
                    $key = $token;
                } else {
                    $data[$key] = $token;
                    $key = null;
                }
            }
        }
        return $data;
    };

    return $parseBlock();
}

/**
 * Helper to resample and save a 1024x1024 JPEG with solid black background
 */
function convertRadarToJpeg(string $source_png, string $dest_jpg): bool {
    $png = @imagecreatefrompng($source_png);
    if (!$png) return false;

    $final_img = imagecreatetruecolor(1024, 1024);
    $bg_color  = imagecolorallocate($final_img, 0, 0, 0);
    imagefill($final_img, 0, 0, $bg_color);
    imagecopyresampled($final_img, $png, 0, 0, 0, 0, 1024, 1024, imagesx($png), imagesy($png));

    $temp_jpg = $dest_jpg . '.' . bin2hex(random_bytes(8)) . '.tmp';
    $saved = imagejpeg($final_img, $temp_jpg, 95);

    if ($saved) {
        $saved = @rename($temp_jpg, $dest_jpg);
    }
    if (!$saved && is_file($temp_jpg)) {
        @unlink($temp_jpg);
    }

    imagedestroy($png);
    imagedestroy($final_img);
    return $saved;
}

$processed_data = [];
$success_count  = 0;
$missing_images = [];
$syntax_errors  = [];
$layer_warnings = [];

// =========================================================================
// MAIN PROCESSING LOOP
// =========================================================================
foreach ($txt_files as $file) {
    $map = basename($file, '.txt');

    if (!preg_match('/\A[a-zA-Z0-9_-]+\z/D', $map)) {
        echo "[-] [INVALID MAP] Invalid map filename: $map\n";
        $syntax_errors[] = $map;
        continue;
    }

    $raw_content = @file_get_contents($file);
    if ($raw_content === false) {
        echo "[-] [READ ERROR] Cannot read overview file: " . basename($file) . "\n";
        $syntax_errors[] = $map;
        continue;
    }

    $kv = parseKeyValues($raw_content);
    if (empty($kv)) {
        echo "[-] [PARSE ERROR] KeyValues parser produced empty tree: " . basename($file) . "\n";
        $syntax_errors[] = $map;
        continue;
    }

    // Retrieve root map node
    $root = $kv[$map] ?? reset($kv);
    if (!is_array($root)) {
        echo "[-] [PARSE ERROR] Missing root definition block in: " . basename($file) . "\n";
        $syntax_errors[] = $map;
        continue;
    }

    // Normalize keys to lowercase for reliable access
    $clean_root = array_change_key_case($root, CASE_LOWER);

    $pos_x = isset($clean_root['pos_x']) ? (float)$clean_root['pos_x'] : null;
    $pos_y = isset($clean_root['pos_y']) ? (float)$clean_root['pos_y'] : null;
    $scale = isset($clean_root['scale']) ? (float)$clean_root['scale'] : null;

    if ($pos_x === null || $pos_y === null || $scale === null || $scale <= 0.0) {
        echo "[-] [PARSE ERROR] Missing or invalid (scale <= 0) coordinates in: " . basename($file) . "\n";
        $syntax_errors[] = $map;
        continue;
    }

    $xoffset = -1 * $pos_x;
    $yoffset = $pos_y;
    $flipx   = (stripos($map, 'mirror') !== false) ? 1 : 0;
    $flipy   = 1;
    $rotate  = (isset($clean_root['rotate']) && (int)$clean_root['rotate'] === 1) ? 1 : 0;

    // 1. Convert Primary / Default Radar Texture
    $candidates = [
        "$clean_png_dir/{$map}.png",
        "$clean_png_dir/{$map}_radar_psd.png",
        "$clean_png_dir/{$map}_radar.png",
        "$clean_png_dir/{$map}_radar_tga.png",
    ];

    $source_img = null;
    foreach ($candidates as $cand) {
        if (file_exists($cand)) {
            $source_img = $cand;
            break;
        }
    }

    if (!$source_img) {
        $wildcards = glob("$clean_png_dir/{$map}_radar_*.png");
        if (!empty($wildcards) && file_exists($wildcards[0])) {
            $source_img = $wildcards[0];
        }
    }

    if (!$source_img) {
        echo "[-] [NO IMAGE] Missing primary radar image for map: $map\n";
        $missing_images[] = $map;
        continue;
    }

    $dest_jpg = "$clean_out_dir/{$map}.jpg";
    if (!convertRadarToJpeg($source_img, $dest_jpg)) {
        echo "[-] [WRITE ERROR] Failed to convert primary JPEG: $dest_jpg\n";
        continue;
    }

    echo "[+] [OK] $map (Primary -> {$map}.jpg)\n";
    $success_count++;

    // 2. Discover and Validate Multi-Level Vertical Sections
    $verified_sections = [];
    $raw_sections = [];

    if (isset($clean_root['verticalsections']) && is_array($clean_root['verticalsections'])) {
        $raw_sections = array_change_key_case($clean_root['verticalsections'], CASE_LOWER);
    }

    // Only maps with 2 or more distinct sections qualify for multi-level processing
    if (count($raw_sections) > 1) {
        foreach ($raw_sections as $secName => $secProps) {
            if (!is_array($secProps)) continue;
            $clean_props = array_change_key_case($secProps, CASE_LOWER);

            $minZ = isset($clean_props['altitudemin']) ? (float)$clean_props['altitudemin'] : null;
            $maxZ = isset($clean_props['altitudemax']) ? (float)$clean_props['altitudemax'] : null;

            if ($minZ === null || $maxZ === null) continue;

            $sec_min = min($minZ, $maxZ);
            $sec_max = max($minZ, $maxZ);

            // Primary / Default layer is already verified
            if ($secName === 'default') {
                $verified_sections[] = [
                    'section' => 'default',
                    'suffix'  => '',
                    'title'   => 'Default Level',
                    'min_z'   => $sec_min,
                    'max_z'   => $sec_max
                ];
                continue;
            }

            // Secondary level: find and verify corresponding radar texture
            $sub_candidates = [
                "$clean_png_dir/{$map}_{$secName}_radar_psd.png",
                "$clean_png_dir/{$map}_{$secName}.png",
                "$clean_png_dir/{$map}_{$secName}_radar.png",
                "$clean_png_dir/{$map}_{$secName}_radar_tga.png"
            ];

            $sub_source = null;
            foreach ($sub_candidates as $scand) {
                if (file_exists($scand)) {
                    $sub_source = $scand;
                    break;
                }
            }

            if (!$sub_source) {
                $wild = glob("$clean_png_dir/{$map}_{$secName}_radar_*.png");
                if (!empty($wild) && file_exists($wild[0])) {
                    $sub_source = $wild[0];
                }
            }

            if ($sub_source) {
                $sub_dest = "$clean_out_dir/{$map}_{$secName}.jpg";
                if (convertRadarToJpeg($sub_source, $sub_dest)) {
                    echo "    [+] [LAYER OK] {$map}_{$secName} (from " . basename($sub_source) . ")\n";
                    $verified_sections[] = [
                        'section' => $secName,
                        'suffix'  => "_{$secName}",
                        'title'   => ucfirst($secName) . ' Level',
                        'min_z'   => $sec_min,
                        'max_z'   => $sec_max
                    ];
                } else {
                    echo "    [-] [LAYER ERROR] Failed to convert secondary JPEG: $sub_dest\n";
                }
            } else {
                echo "    [-] [LAYER WARNING] Radar texture missing for section '$secName' on map: $map\n";
                $layer_warnings[] = "$map ($secName)";
            }
        }
    }

    // Dynamically assign human-readable titles based on physical vertical altitude ordering (Z-bounds)
    if (count($verified_sections) > 1) {
        $sorted_by_z = $verified_sections;
        usort($sorted_by_z, function($a, $b) {
            return $a['min_z'] <=> $b['min_z'];
        });

        $sec_count = count($sorted_by_z);
        $titles_by_section = [];

        if ($sec_count === 2) {
            $titles_by_section[$sorted_by_z[0]['section']] = 'Lower Level';
            $titles_by_section[$sorted_by_z[1]['section']] = 'Upper Level';
        } elseif ($sec_count === 3) {
            $titles_by_section[$sorted_by_z[0]['section']] = 'Lower Level';
            $titles_by_section[$sorted_by_z[1]['section']] = 'Middle Level';
            $titles_by_section[$sorted_by_z[2]['section']] = 'Upper Level';
        } else {
            foreach ($sorted_by_z as $idx => $s) {
                $tier = ($idx === 0) ? 'Lowest' : (($idx === $sec_count - 1) ? 'Highest' : 'Mid');
                $titles_by_section[$s['section']] = 'Level ' . ($idx + 1) . " ($tier)";
            }
        }

        // Apply resolved titles back while preserving physical file suffixes and default mappings
        foreach ($verified_sections as &$vSec) {
            if (isset($titles_by_section[$vSec['section']])) {
                $vSec['title'] = $titles_by_section[$vSec['section']];
            }
        }
        unset($vSec);

        // Keep default layer always first in array so web galleries open upper level first
        usort($verified_sections, function($a, $b) {
            if ($a['section'] === 'default') return -1;
            if ($b['section'] === 'default') return 1;
            return $b['min_z'] <=> $a['min_z']; // Remaining layers sorted highest to lowest
        });
    }

    // Save dynamic sections JSON if and only if at least 2 verified levels exist
    $sections_file = "$clean_out_dir/{$map}_sections.json";
    if (count($verified_sections) > 1) {
        $temp_json = $sections_file . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (@file_put_contents($temp_json, json_encode($verified_sections, JSON_PRETTY_PRINT)) !== false) {
            @rename($temp_json, $sections_file);
            echo "    [+] [SECTIONS SAVED] Dynamic descriptor created: {$map}_sections.json\n";
        }
        if (is_file($temp_json)) @unlink($temp_json);
    } else {
        // Prune stale descriptor if map fell back to single-level
        if (is_file($sections_file)) @unlink($sections_file);
    }

    $processed_data[] = [
        'map'     => $map,
        'game'    => $game_code,
        'xoffset' => (float)$xoffset,
        'yoffset' => (float)$yoffset,
        'flipx'   => (int)$flipx,
        'flipy'   => (int)$flipy,
        'rotate'  => (int)$rotate,
        'scale'   => (float)$scale,
    ];
}

// =========================================================================
// WRITE OUTPUT FILE & DATABASE UPDATE
// =========================================================================
if (!empty($processed_data)) {
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'hlstats';

    // Build raw SQL backup updates with real escaping
    $sql_values = [];
    foreach ($processed_data as $row) {
        $sql_values[] = sprintf(
            "('%s', '%s', %F, %F, %d, %d, %d, 30, 'small', %F, 10, 0.170312, 0.170312, 0, 0, 0, 0)",
            addslashes($row['map']),
            addslashes($row['game']),
            $row['xoffset'],
            $row['yoffset'],
            $row['flipx'],
            $row['flipy'],
            $row['rotate'],
            $row['scale']
        );
    }

    // Automated Database Update via Transactional Prepared Statements
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $db_success = true;
    $error_msg  = "";

    if ($db->connect_error) {
        $db_success = false;
        $error_msg = $db->connect_error;
    } else {
        if (!$db->set_charset("utf8mb4")) {
            $db_success = false;
            $error_msg = "Failed to set charset utf8mb4: " . $db->error;
        } else {

            $sql = "INSERT INTO `{$prefix}_Heatmap_Config`
                    (`map`, `game`, `xoffset`, `yoffset`, `flipx`, `flipy`, `rotate`, `days`, `brush`, `scale`, `font`, `thumbw`, `thumbh`, `cropx1`, `cropy1`, `cropx2`, `cropy2`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 30, 'small', ?, 10, 0.170312, 0.170312, 0, 0, 0, 0)
                    ON DUPLICATE KEY UPDATE
                    `xoffset` = VALUES(`xoffset`),
                    `yoffset` = VALUES(`yoffset`),
                    `scale` = VALUES(`scale`),
                    `flipx` = VALUES(`flipx`),
                    `flipy` = VALUES(`flipy`),
                    `rotate` = VALUES(`rotate`)";

            $stmt = $db->prepare($sql);
            if (!$stmt) {
                $db_success = false;
                $error_msg = "SQL prepare failed: " . $db->error;
            } else {
                if (!$db->begin_transaction()) {
                    $db_success = false;
                    $error_msg = "Transaction start failed: " . $db->error;
                } else {
                    foreach ($processed_data as $row) {
                        if (!$stmt->bind_param(
                            "ssddiiid",
                            $row['map'],
                            $row['game'],
                            $row['xoffset'],
                            $row['yoffset'],
                            $row['flipx'],
                            $row['flipy'],
                            $row['rotate'],
                            $row['scale']
                        )) {
                            $db_success = false;
                            $error_msg = "Bind failed: " . $stmt->error;
                            break;
                        }

                        if (!$stmt->execute()) {
                            $db_success = false;
                            $error_msg = "Execute failed: " . $stmt->error;
                            break;
                        }
                    }

                    if ($db_success) {
                        if (!$db->commit()) {
                            $db_success = false;
                            $error_msg = "Commit failed: " . $db->error;
                            $db->rollback();
                        }
                    } else {
                        $db->rollback();
                    }
                }
                $stmt->close();
            }
        }
        $db->close();
    }

    // Save SQL backup file atomically
    $file_written = false;
    if (!empty($sql_values)) {
        $sql_content  = "-- HLstatsX:CE Heatmap Updates\n";
        $sql_content .= "INSERT INTO `{$prefix}_Heatmap_Config` (`map`, `game`, `xoffset`, `yoffset`, `flipx`, `flipy`, `rotate`, `days`, `brush`, `scale`, `font`, `thumbw`, `thumbh`, `cropx1`, `cropy1`, `cropx2`, `cropy2`) VALUES\n";
        $sql_content .= implode(",\n", $sql_values) . "\n";
        $sql_content .= "ON DUPLICATE KEY UPDATE\n";
        $sql_content .= "  `xoffset` = VALUES(`xoffset`),\n";
        $sql_content .= "  `yoffset` = VALUES(`yoffset`),\n";
        $sql_content .= "  `scale`   = VALUES(`scale`),\n";
        $sql_content .= "  `flipx`   = VALUES(`flipx`),\n";
        $sql_content .= "  `flipy`   = VALUES(`flipy`),\n";
        $sql_content .= "  `rotate`  = VALUES(`rotate`);\n";

        $temp_sql = $sql_file . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (@file_put_contents($temp_sql, $sql_content, LOCK_EX) !== false) {
            $file_written = @rename($temp_sql, $sql_file);
        }
        if (is_file($temp_sql)) @unlink($temp_sql);
    }

    // Calculate total aggregated warning count
    $total_warnings = count($syntax_errors) + count($missing_images) + count($layer_warnings);

    echo "\n=======================================================\n";
    echo "[SUCCESS] Total maps processed and converted: $success_count\n";

    if ($file_written === true) {
        echo "[SUCCESS] SQL backup written to: $sql_file\n";
    } else {
        echo "[-] [WRITE ERROR] Failed to write SQL file: $sql_file\n";
    }

    // ANSI color codes for terminal highlighting
    $c_green  = "\033[1;32m";
    $c_yellow = "\033[1;33m";
    $c_reset  = "\033[0m";

    if ($db_success) {
        echo "{$c_green}[+] [DB UPDATE] Database successfully updated!{$c_reset}\n";
    } else {
        echo "{$c_yellow}[-] [DB UPDATE] FAILED: $error_msg{$c_reset}\n";
    }

    echo "[WARNINGS] Total warnings: $total_warnings\n";
    echo "=======================================================\n";

    if (!empty($syntax_errors)) {
        echo "[WARNING] Syntax/read errors in overview files (" . count($syntax_errors) . "):\n";
        echo "          " . implode(', ', $syntax_errors) . "\n";
    }

    if (!empty($missing_images)) {
        echo "[WARNING] Missing primary radar images (" . count($missing_images) . "):\n";
        echo "          " . implode(', ', $missing_images) . "\n";
    }

    if (!empty($layer_warnings)) {
        echo "[WARNING] Missing secondary radar textures (" . count($layer_warnings) . "):\n";
        echo "          " . implode(', ', $layer_warnings) . "\n";
    }
} else {
    echo "\n[FAILED] No maps were successfully processed.\n";
}