<?php
/** HLstatsX:CE heatmap generator - deterministic full-window rebuild with multi-level Z dispatching (PHP 8+) */
class Env {
    private static array $data = [];
    public static function set(string $key, $value): void { self::$data[$key] = $value; }
    public static function get(string $key) { return self::$data[$key] ?? null; }
}

class Heatmap {
    public string $version = '1.1';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_ERROR = 'error';

    public static function init(): void {
        if (!extension_loaded('gd') || !extension_loaded('mysqli')) {
            throw new RuntimeException('Required PHP extensions: gd and mysqli');
        }
        global $argv;
        DB::connect();
        self::mapinfo();
        self::parseArguments($argv ?? []);
    }

    public static function mapinfo(): void {
        $sql = 'SELECT g.code, hc.* FROM `' . DB::table('Games') . '` AS g '
             . 'INNER JOIN `' . DB::table('Heatmap_Config') . '` AS hc ON hc.game = g.realgame '
             . 'ORDER BY g.code, hc.game, hc.map';
        $result = DB::doQuery($sql);
        if ($result === false) { throw new RuntimeException('Cannot load heatmap configuration'); }
        $maps = [];
        while ($row = $result->fetch_assoc()) { $maps[$row['code']][$row['map']] = $row; }
        $result->free();
        Env::set('mapinfo', $maps);
    }

    private static function validName(string $name): bool {
        return (bool)preg_match('/\A[a-zA-Z0-9_\-]+\z/D', $name);
    }

    private static function queryParts(string $code, string $map, string $start, string $end): array {
        $m = DB::escape($map);
        $g = DB::escape($code);
        $start = DB::escape($start);
        $end = DB::escape($end);
        $filter = Env::get('ignore_infected') ? " AND f.victimRole <> 'infected'" : '';

        $frags = "SELECT f.id, 'frag' AS killtype, f.eventTime, f.pos_x, f.pos_y, f.pos_z "
               . 'FROM `' . DB::table('Events_Frags') . '` f '
               . 'INNER JOIN `' . DB::table('Servers') . '` s ON s.serverId = f.serverId '
               . "WHERE s.game = '$g' AND f.map = '$m' AND f.pos_x IS NOT NULL AND f.pos_y IS NOT NULL "
               . "AND f.eventTime >= '$start' AND f.eventTime <= '$end'" . $filter;

        $teamkills = "SELECT t.id, 'teamkill' AS killtype, t.eventTime, t.pos_x, t.pos_y, t.pos_z "
                   . 'FROM `' . DB::table('Events_Teamkills') . '` t '
                   . 'INNER JOIN `' . DB::table('Servers') . '` s ON s.serverId = t.serverId '
                   . "WHERE s.game = '$g' AND t.map = '$m' AND t.pos_x IS NOT NULL AND t.pos_y IS NOT NULL "
                   . "AND t.eventTime >= '$start' AND t.eventTime <= '$end'";

        return [$frags, $teamkills];
    }

    public static function buildQuery(string $code, string $map, string $start, string $end,
                                      ?array $cursor = null): string {
        $effectiveStart = ($cursor !== null) ? $cursor['time'] : $start;
        [$frags, $teamkills] = self::queryParts($code, $map, $effectiveStart, $end);
        $where = '';
        if ($cursor !== null) {
            $t = DB::escape($cursor['time']);
            $kind = DB::escape($cursor['type']);
            $id = (int)$cursor['id'];
            $where = "WHERE (e.eventTime > '$t' OR (e.eventTime = '$t' AND e.killtype > '$kind') "
                   . "OR (e.eventTime = '$t' AND e.killtype = '$kind' AND e.id > $id))";
        }
        return "SELECT e.id, e.killtype, e.eventTime, e.pos_x, e.pos_y, e.pos_z FROM ($frags UNION ALL $teamkills) e "
             . "$where ORDER BY e.eventTime ASC, e.killtype ASC, e.id ASC LIMIT " . max(1, (int)KILL_LIMIT);
    }

    private static function countEvents(string $code, string $map, string $start, string $end) {
        [$frags, $teamkills] = self::queryParts($code, $map, $start, $end);
        $sql = "SELECT (SELECT COUNT(*) FROM ($frags) f1) + (SELECT COUNT(*) FROM ($teamkills) t1) AS total";
        $res = DB::doQuery($sql);
        if ($res === false) { return false; }
        $row = $res->fetch_assoc();
        $res->free();
        return (int)($row['total'] ?? 0);
    }

    private static function prepareBrush($brush, int $opacity): void {
        if (!imageistruecolor($brush)) {
            imagepalettetotruecolor($brush);
        }
        imagealphablending($brush, false);
        imagesavealpha($brush, true);
        $w = imagesx($brush); $h = imagesy($brush); $min = 127;
        for ($y=0; $y<$h; $y++) for ($x=0; $x<$w; $x++) {
            $min = min($min, (imagecolorat($brush, $x, $y) >> 24) & 127);
        }
        $pct = $opacity / 100;
        for ($y=0; $y<$h; $y++) for ($x=0; $x<$w; $x++) {
            $c = imagecolorat($brush, $x, $y);
            $a = ($c >> 24) & 127;
            $newA = $min !== 127 ? 127 + 127*$pct*($a-127)/(127-$min) : $a+127*$pct;
            $color = imagecolorallocatealpha($brush, ($c>>16)&255, ($c>>8)&255, $c&255,
                                             max(0, min(127, (int)$newA)));
            imagesetpixel($brush, $x, $y, $color);
        }
        imagealphablending($brush, true);
    }

    private static function colorize($img, $overlay): void {
        $w = min(imagesx($img), imagesx($overlay));
        $h = min(imagesy($img), imagesy($overlay));
        for ($y=0; $y<$h; $y++) for ($x=0; $x<$w; $x++) {
            $rgb = imagecolorat($overlay, $x, $y);
            $red = ($rgb >> 16) & 255;
            if ($red === 0) continue;
            if ($red < 128) {
                $r=0; $g=(int)round($red/127*255); $b=(int)round((127-$red)/127*255);
            } else {
                $r=(int)round(($red-128)/127*255);
                $g=(int)round((255-$red)/127*255); $b=0;
            }
            $color = imagecolorallocatealpha($img, max(0,min(255,$r)), max(0,min(255,$g)),
                                            max(0,min(255,$b)), max(0,min(127,127-(int)($red/2))));
            imagesetpixel($img, $x, $y, $color);
        }
    }

    /**
     * Resolves an available TrueType font path across local and system directories.
     */
    private static function resolveFont(): ?string {
        // 1. Primary bundled font in project root
        $bundled = __DIR__ . '/DejaVuSans.ttf';
        if (is_file($bundled)) {
            return $bundled;
        }

        // 2. Standard system sans-serif locations (Debian, Ubuntu, RHEL, CentOS, Alpine, macOS)
        $systemFonts = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/liberation-sans/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
            '/Library/Fonts/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf'
        ];

        // 3. Standard Windows font locations
        $winDir = getenv('WINDIR') ?: 'C:\\Windows';
        $systemFonts[] = $winDir . '\\Fonts\\arial.ttf';
        $systemFonts[] = $winDir . '\\Fonts\\segoeui.ttf';
        $systemFonts[] = $winDir . '\\Fonts\\tahoma.ttf';

        foreach ($systemFonts as $fontPath) {
            if (is_file($fontPath)) {
                return $fontPath;
            }
        }

        return null;
    }

    private static function drawHud($img, string $mapTitle, array $cfg, int $count, string $start, string $end): void {
        $font = self::resolveFont();
        $fontSize = max(1, (int)$cfg['font']);
        $text = [
            strtoupper($mapTitle) . ' - HLX:CE HEATMAP - TOTAL KILLS: ' . $count,
            date('m/d/y', strtotime($start)) . ' - ' . date('m/d/y', strtotime($end)),
            'Generated: ' . date('Y-m-d H:i:s'),
            HUD_URL
        ];

        // Calculate dynamic HUD banner height based on rendering engine
        $lineHeight = ($font !== null) ? ($fontSize + 4) : 16;
        $h = min(imagesy($img), $lineHeight * (count($text) + 1) + 8);

        imagealphablending($img, true);
        $bannerBg = imagecolorallocatealpha($img, 0, 0, 0, 50);
        imagefilledrectangle($img, 0, 0, imagesx($img) - 1, $h, $bannerBg);

        $white = imagecolorallocate($img, 255, 255, 255);

        if ($font !== null && function_exists('imagettftext')) {
            // TrueType rendering using bundled or equivalent system sans-serif font
            foreach ($text as $i => $line) {
                imagettftext($img, $fontSize, 0, 10, ($fontSize + 4) * ($i + 1) + 8, $white, $font, $line);
            }
        } else {
            // Zero-dependency GD raster fallback if no system TTF exists anywhere
            foreach ($text as $i => $line) {
                imagestring($img, 4, 10, ($i * 16) + 8, $line, $white);
            }
        }
    }

    public static function generate(string $code, string $map, string $mode): string {
        $maps = Env::get('mapinfo') ?? [];
        if (!isset($maps[$code][$map]) || !self::validName($code) || !self::validName($map)
            || !self::validName($mode)) { show::Event('ERROR', "Invalid map: $code/$map", 1); return self::STATUS_ERROR; }
        $cfg = $maps[$code][$map];
        if ((int)KILL_LIMIT < 1) { show::Event('ERROR', 'KILL_LIMIT must be positive', 1); return self::STATUS_ERROR; }
        if (!self::validName((string)$cfg['game']) || !in_array($cfg['brush'], ['small','large'], true)
            || (float)$cfg['scale'] <= 0) { show::Event('ERROR', 'Invalid map configuration', 1); return self::STATUS_ERROR; }

        $outDir = rtrim(HLXCE_WEB, '/\\') . '/hlstatsimg/games/' . $code . '/heatmaps';
        if ((!is_dir($outDir) && !@mkdir($outDir, 0775, true)) || !is_writable($outDir)) {
            show::Event('ERROR', 'Output directory not writable: ' . $outDir, 1);
            return self::STATUS_ERROR;
        }

        // Advisory lock per map to prevent overlapping generation
        $lock = @fopen($outDir . '/.' . $map . '-' . $mode . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) fclose($lock);
            show::Event('ERROR', "Another generation is running for $map", 1);
            return self::STATUS_ERROR;
        }

        $temps = [];
        $layers = [];
        $brush = null;
        try {
            $days = max(1, (int)$cfg['days']);
            $bounds = DB::doQuery("SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL $days DAY), '%Y-%m-%d %H:%i:%s') AS start_time, "
                                . "DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s') AS end_time");
            if ($bounds === false) throw new RuntimeException('Cannot determine database time window');
            $window = $bounds->fetch_assoc(); $bounds->free();
            $start = $window['start_time']; $end = $window['end_time'];

            // Query total events once for the whole map
            $total = self::countEvents($code, $map, $start, $end);
            if ($total === false) throw new RuntimeException('Cannot count events');

            // Detect multi-level radar configuration
            $sections_file = __DIR__ . '/src/' . $cfg['game'] . '/' . $map . '_sections.json';
            $sections = [];

            if (is_file($sections_file)) {
                $raw_sec = @file_get_contents($sections_file);

                if ($raw_sec === false) {
                    throw new RuntimeException(
                        'Cannot read sections manifest: ' . $sections_file
                    );
                }

                try {
                    $sections = json_decode($raw_sec, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new RuntimeException(
                        'Invalid sections manifest: ' . $sections_file,
                        0,
                        $e
                    );
                }

                if (!is_array($sections) || empty($sections)) {
                    throw new RuntimeException(
                        'Empty or invalid sections manifest: ' . $sections_file
                    );
                }
            }

            // Fallback for single-level maps and legacy Source 1 games
            if (empty($sections)) {
                $sections = [
                    [
                        'section' => 'default',
                        'suffix'  => '',
                        'title'   => '',
                        'min_z'   => null,
                        'max_z'   => null
                    ]
                ];
            }

            // Cleanup stale published images if map has zero kills
            if ($total === 0) {
                foreach ($sections as $sec) {
                    $s_suffix = (string)($sec['suffix'] ?? '');
                    $s_base = $outDir . '/' . $map . $s_suffix . '-' . $mode;
                    @unlink($s_base . '.jpg');
                    @unlink($s_base . '-thumb.jpg');
                }
                // The web must not advertise levels without generated heatmaps.
                $publishedManifest = $outDir . '/' . $map . '_sections.json';

                if (is_file($publishedManifest) && !@unlink($publishedManifest)) {
                    throw new RuntimeException(
                        'Cannot remove empty-map manifest: ' . $publishedManifest
                    );
                }
                return self::STATUS_SKIPPED;
            }

            // Capture the previously published manifest (if any) so we can prune
            // image files for sections that no longer exist once this run succeeds.
            $previousManifestPath = $outDir . '/' . $map . '_sections.json';
            $previousSuffixes = [];
            if (is_file($previousManifestPath)) {
                $prevRaw = @file_get_contents($previousManifestPath);
                if ($prevRaw !== false) {
                    $prevDecoded = json_decode($prevRaw, true);
                    if (is_array($prevDecoded)) {
                        foreach ($prevDecoded as $prevSec) {
                            if (isset($prevSec['suffix'])) {
                                $previousSuffixes[] = (string)$prevSec['suffix'];
                            }
                        }
                    }
                }
            }

            // Initialize image layers for each verified section
            foreach ($sections as $sec) {
                $sec_name   = (string)$sec['section'];
                $sec_suffix = (string)($sec['suffix'] ?? '');
                $source     = __DIR__ . '/src/' . $cfg['game'] . '/' . $map . $sec_suffix . '.jpg';

                if (!is_file($source)) {
                    throw new RuntimeException(
                        'Required radar source missing: ' . $source
                    );
                }

                $img = @imagecreatefromjpeg($source);
                if (!$img) throw new RuntimeException('Cannot load source JPEG: ' . $source);
                imagealphablending($img, true);

                $overlay = imagecreatetruecolor(imagesx($img), imagesy($img));
                $black = imagecolorallocate($overlay, 0, 0, 0);
                imagefill($overlay, 0, 0, $black);
                imagecolortransparent($overlay, $black);
                imagealphablending($overlay, true);

                $layers[$sec_name] = [
                    'config'   => $sec,
                    'source'   => $source,
                    'img'      => $img,
                    'overlay'  => $overlay,
                    'rendered' => 0
                ];
            }

            if (empty($layers) || !isset($layers['default'])) {
                throw new RuntimeException('No valid radar layers available for rendering');
            }

            // Prepare brush once for the entire map
            $brush = @imagecreatefrompng(__DIR__ . '/src/brush_' . $cfg['brush'] . '.png');
            if (!$brush) throw new RuntimeException('Cannot load brush');

            $opacity = max(2, min(40, (int)(50000 / max(1, $total))));
            self::prepareBrush($brush, $opacity);
            $brushSize = $cfg['brush'] === 'large' ? 33 : 17;

            // Single query stream: dispatch events to appropriate floor in memory
            $cursor = null;
            $processed = 0;
            $is_multilevel = (count($layers) > 1);
            $missing_z_count = 0;
            $out_of_bounds_z_count = 0;

            do {
                $res = DB::doQuery(self::buildQuery($code, $map, $start, $end, $cursor));
                if ($res === false) throw new RuntimeException('Event query failed');
                $count = $res->num_rows;

                while ($row = $res->fetch_assoc()) {
                    $next = ['time' => $row['eventTime'], 'type' => $row['killtype'], 'id' => (int)$row['id']];
                    if ($cursor !== null && ($next['time'] < $cursor['time'] ||
                        ($next['time'] === $cursor['time'] && strcmp($next['type'], $cursor['type']) < 0) ||
                        ($next['time'] === $cursor['time'] && $next['type'] === $cursor['type'] && $next['id'] <= $cursor['id']))) {
                        throw new RuntimeException('Pagination cursor did not advance');
                    }
                    $cursor = $next;
                    $processed++;

                    $px = (float)$row['pos_x'] * (!empty($cfg['flipx']) ? -1 : 1);
                    $py = (float)$row['pos_y'] * (!empty($cfg['flipy']) ? -1 : 1);
                    $x = ($px + (float)$cfg['xoffset']) / (float)$cfg['scale'];
                    $y = ($py + (float)$cfg['yoffset']) / (float)$cfg['scale'];
                    $tx = (int)(!empty($cfg['rotate']) ? $y : $x);
                    $ty = (int)(!empty($cfg['rotate']) ? $x : $y);

                    // Determine target section based on Z coordinate
                    $targetSec = 'default';

                    if ($is_multilevel) {
                        if ($row['pos_z'] === null) {
                            $missing_z_count++;
                            continue;
                        }

                        $pz = (float)$row['pos_z'];
                        $matched = false;

                        foreach ($layers as $sName => $layerData) {
                            $sConf = $layerData['config'];

                            if (
                                $sConf['min_z'] !== null &&
                                $sConf['max_z'] !== null &&
                                $pz >= (float)$sConf['min_z'] &&
                                $pz < (float)$sConf['max_z']
                            ) {
                                $targetSec = $sName;
                                $matched = true;
                                break;
                            }
                        }

                        if (!$matched) {
                            $out_of_bounds_z_count++;
                            continue;
                        }
                    }

                    if (!isset($layers[$targetSec])) continue;

                    $targetOverlay = $layers[$targetSec]['overlay'];
                    if ($tx < 0 || $ty < 0 || $tx >= imagesx($targetOverlay) || $ty >= imagesy($targetOverlay)) {
                        continue;
                    }

                    // Count every valid event assigned to this radar layer.
                    $layers[$targetSec]['rendered']++;

                    $red = (imagecolorat($targetOverlay, $tx, $ty) >> 16) & 255;
                    if ($red <= 200) {
                        imagecopy($targetOverlay, $brush, (int)($tx - $brushSize / 2), (int)($ty - $brushSize / 2),
                                  0, 0, $brushSize, $brushSize);
                    }
                }
                $res->free();
            } while ($count >= max(1, (int)KILL_LIMIT));

            imagedestroy($brush);
            $brush = null;

            // Log Z coordinate anomalies if any occurred on multi-level maps
            if ($is_multilevel && ($missing_z_count > 0 || $out_of_bounds_z_count > 0)) {
                show::Event(
                    'INFO',
                    "Map: $map Z anomalies -> Missing Z: $missing_z_count, Out-of-bounds Z: $out_of_bounds_z_count (skipped)",
                    2
                );
            }

            // Finalize, crop, draw HUD, and atomically publish full-size heatmaps and thumbnails for all layers
            $total_rendered = 0;
            foreach ($layers as $sName => $lData) {
                $lImg     = $lData['img'];
                $lOverlay = $lData['overlay'];
                $lConf    = $lData['config'];
                $lSuffix  = (string)($lConf['suffix'] ?? '');
                $lTitle   = (string)($lConf['title'] ?? '');
                $lRend    = (int)$lData['rendered'];
                $total_rendered += $lRend;

                self::colorize($lImg, $lOverlay);
                imagedestroy($lOverlay);

                if ((int)$cfg['cropx2'] > 0 && (int)$cfg['cropy2'] > 0) {
                    $cw = (int)$cfg['cropx2']; $ch = (int)$cfg['cropy2'];
                    $cropped = imagecreatetruecolor($cw, $ch);
                    imagecopy($cropped, $lImg, 0, 0, (int)$cfg['cropx1'], (int)$cfg['cropy1'], $cw, $ch);
                    imagedestroy($lImg);
                    $lImg = $cropped;
                }

                $base = $outDir . '/' . $map . $lSuffix . '-' . $mode;
                $token = bin2hex(random_bytes(8));
                $jpgTmp = $base . '.jpg.' . $token . '.tmp';
                $temps[] = $jpgTmp;
                $thumbTmp = null;

                // Generate clean thumbnail for every layer
                if ((float)$cfg['thumbw'] > 0 && (float)$cfg['thumbh'] > 0) {
                    $tw = max(1, (int)(imagesx($lImg) * (float)$cfg['thumbw']));
                    $th = max(1, (int)(imagesy($lImg) * (float)$cfg['thumbh']));
                    $thumb = imagecreatetruecolor($tw, $th);
                    imagecopyresampled($thumb, $lImg, 0, 0, 0, 0, $tw, $th, imagesx($lImg), imagesy($lImg));
                    $thumbTmp = $base . '-thumb.jpg.' . $token . '.tmp';
                    $temps[] = $thumbTmp;
                    $ok = imagejpeg($thumb, $thumbTmp, 95);
                    imagedestroy($thumb);
                    if (!$ok) {
                        imagedestroy($lImg);
                        throw new RuntimeException('Cannot save thumbnail for ' . $base);
                    }
                }

                // Draw HUD header with layer title and floor-specific kill count
                $hud_title = !empty($lTitle) ? $map . ' (' . $lTitle . ')' : $map;
                self::drawHud($lImg, $hud_title, $cfg, $lRend, $start, $end);

                if (!imagejpeg($lImg, $jpgTmp, 95)) {
                    imagedestroy($lImg);
                    throw new RuntimeException('Cannot save JPEG for ' . $base);
                }
                imagedestroy($lImg);

                // Publish layer files atomically
                if ($thumbTmp !== null && !@rename($thumbTmp, $base . '-thumb.jpg')) {
                    throw new RuntimeException('Cannot publish thumbnail for ' . $base);
                }
                if (!@rename($jpgTmp, $base . '.jpg')) {
                    throw new RuntimeException('Cannot publish heatmap for ' . $base);
                }
            }

            // Publish validated multi-level manifest for the web interface.
            $publishedManifest = $outDir . '/' . $map . '_sections.json';

            if ($is_multilevel) {
                $manifestTmp = $publishedManifest . '.' .
                               bin2hex(random_bytes(8)) . '.tmp';

                $temps[] = $manifestTmp;

                $json = json_encode(
                    $sections,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );

                if (file_put_contents($manifestTmp, $json, LOCK_EX) === false) {
                    throw new RuntimeException(
                        'Cannot write sections manifest: ' . $manifestTmp
                    );
                }

                if (!@rename($manifestTmp, $publishedManifest)) {
                    throw new RuntimeException(
                        'Cannot publish sections manifest: ' . $publishedManifest
                    );
                }
            } elseif (is_file($publishedManifest)) {
                // A map that is now single-level must not retain an old manifest.
                if (!@unlink($publishedManifest)) {
                    throw new RuntimeException(
                        'Cannot remove obsolete sections manifest: ' . $publishedManifest
                    );
                }
            }

            // Prune image files for sections that existed in the previous run but are
            // no longer part of this map's configuration (e.g. a floor was removed).
            $currentSuffixes = array_map(static fn($s) => (string)($s['suffix'] ?? ''), $sections);
            foreach (array_diff($previousSuffixes, $currentSuffixes) as $staleSuffix) {
                $staleBase = $outDir . '/' . $map . $staleSuffix . '-' . $mode;
                @unlink($staleBase . '.jpg');
                @unlink($staleBase . '-thumb.jpg');
            }

            show::Event('CREATE', "Game: $code Map: $map Total Events: $processed Total Drawn: $total_rendered", 1);
            return self::STATUS_SUCCESS;
        } catch (Throwable $e) {
            show::Event('ERROR', "$code/$map: " . $e->getMessage(), 1);
            return self::STATUS_ERROR;
        } finally {
            foreach ($temps as $tmp) if (is_file($tmp)) @unlink($tmp);
            if ($brush instanceof GdImage) imagedestroy($brush);
            foreach ($layers as $lData) {
                if (isset($lData['img']) && $lData['img'] instanceof GdImage) imagedestroy($lData['img']);
                if (isset($lData['overlay']) && $lData['overlay'] instanceof GdImage) imagedestroy($lData['overlay']);
            }
            flock($lock, LOCK_UN); fclose($lock);
        }
    }

    private static function arguments(array $argv): array {
        $args = [];
        foreach ($argv as $arg) {
            if (preg_match('/^--([a-zA-Z0-9_-]+)(?:=(.*))?$/', $arg, $m)) {
                $args[str_replace('-', '', strtolower($m[1]))] = $m[2] ?? 'true';
            }
        }
        return $args;
    }

    public static function parseArguments(array $argv): void {
        $maps = Env::get('mapinfo') ?? [];
        $args = self::arguments($argv);
        if (isset($args['game'])) {
            $game = $args['game'];
            if (!isset($maps[$game])) throw new InvalidArgumentException('Unknown game: ' . $game);
            if (isset($args['map'])) {
                if (!isset($maps[$game][$args['map']])) throw new InvalidArgumentException('Unknown map');
                $maps = [$game => [$args['map'] => $maps[$game][$args['map']]]];
            } else { $maps = [$game => $maps[$game]]; }
        } else {
            $res = DB::doQuery('SELECT code FROM `' . DB::table('Games') . "` WHERE hidden='0'");
            if ($res === false) throw new RuntimeException('Cannot load visible games');
            $visible = [];
            while ($row = $res->fetch_assoc()) if (isset($maps[$row['code']])) $visible[$row['code']] = $maps[$row['code']];
            $res->free(); $maps = $visible;
        }
        Env::set('mapinfo', $maps);
        Env::set('ignore_infected', in_array(strtolower($args['ignoreinfected'] ?? 'false'), ['true','1'], true));
    }
}

class DB {
    public static mysqli $connect;
    public static function table(string $suffix): string {
        $name = DB_PREFIX . '_' . $suffix;
        if (!preg_match('/\A[A-Za-z0-9_]+\z/D', $name)) throw new InvalidArgumentException('Invalid DB prefix');
        return $name;
    }
    public static function connect(): void {
        mysqli_report(MYSQLI_REPORT_OFF);
        $db = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_errno) throw new RuntimeException('Database connection failed: ' . $db->connect_error);
        if (!$db->set_charset('utf8mb4')) throw new RuntimeException('Cannot set utf8mb4');
        self::$connect = $db;
    }
    public static function escape(string $str): string { return self::$connect->real_escape_string($str); }
    public static function doQuery(string $sql) {
        $res = self::$connect->query($sql);
        if ($res === false) show::Event('SQL ERROR', self::$connect->error, 1);
        return $res;
    }
    public static function getAssoc($result) { return $result instanceof mysqli_result ? $result->fetch_assoc() : false; }
    public static function numRows($result): int { return $result instanceof mysqli_result ? $result->num_rows : 0; }
}

class show {
    public static function Event(string $type, string $text, int $level): void {
        if ($level <= (int)DEBUG) echo date('Y-m-d H:i:s') . "\t$type: $text\n";
    }
}