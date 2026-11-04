<?php
/*
   HLstatsX Community Edition - Discord Avatar Card Generator (hardened)
   Version: 1.0
   Project: https://github.com/lovasatt/hlstatsx-community-edition
   Author:  lovasatt (2026)

   Rewrite (mod_rewrite) example in .htaccess:
   RewriteRule ^card-([0-9]+)\.png$ card.php?player_id=$1 [L,QSA]
*/

define('IN_HLSTATS', true);

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
@set_time_limit(15);

if (ob_get_level() === 0) {
    ob_start();
}

$hlxav_method = (isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']))
    ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
if ($hlxav_method !== 'GET' && $hlxav_method !== 'HEAD') {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
    header('Referrer-Policy: no-referrer');
    header('Cross-Origin-Resource-Policy: cross-origin');
    header('Access-Control-Allow-Origin: *');
    header('Vary: Accept-Encoding');
    header('X-Robots-Tag: noindex');
}

$hlxav_raw_ims = (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && is_string($_SERVER['HTTP_IF_MODIFIED_SINCE']))
    ? substr($_SERVER['HTTP_IF_MODIFIED_SINCE'], 0, 64) : '';

foreach ($_SERVER as $key => $entry) {
    if ($key !== 'HTTP_COOKIE' && is_string($entry)) {
        $search_pattern  = array('/<script>/i', '/<\/script>/i', '/[^A-Za-z0-9.\-\/=:;_?#&~%]/');
        $replace_pattern = array('', '', '');
        $entry = preg_replace($search_pattern, $replace_pattern, $entry);
        if ($entry === null) {
            $entry = '';
        }

        if ($key === 'PHP_SELF') {
            $last_segment = strrchr($entry, '/');
            if ($last_segment !== false) {
                if (($last_segment !== '/hlstats.php') &&
                    ($last_segment !== '/card.php') &&
                    (!preg_match('/^\/card-[0-9]+\.png$/i', $last_segment)) &&
                    ($last_segment !== '/show_graph.php') &&
                    ($last_segment !== '/sig.php') &&
                    ($last_segment !== '/sig2.php') &&
                    (!preg_match('/^\/sig-[0-9]+(-[a-z0-9]+)?\.png$/i', $last_segment)) &&
                    ($last_segment !== '/index.php') &&
                    ($last_segment !== '/status.php') &&
                    ($last_segment !== '/top10.php') &&
                    ($last_segment !== '/config.php') &&
                    ($last_segment !== '/') &&
                    ($entry !== '')) {
                    $raw_host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', trim(explode(',', (string)$raw_host)[0]));
                    if ($host === '') {
                        $host = 'localhost';
                    }
                    $f_proto = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? strtolower(trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0])) : '';
                    $is_ssl = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
                        || ($f_proto === 'https')
                        || (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos((string)$_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false);
                    $proto = $is_ssl ? 'https' : 'http';
                    $base_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '')), '/');
                    header('Location: ' . $proto . '://' . $host . $base_dir . '/hlstats.php');
                    exit;
                }
            }
        }
        $_SERVER[$key] = $entry;
    }
}

require('config.php');
require(INCLUDE_PATH . '/class_db.php');
require(INCLUDE_PATH . '/functions.php');

$db_classname = 'DB_' . DB_TYPE;
if (class_exists($db_classname)) {
    $db = new $db_classname(DB_ADDR, DB_USER, DB_PASS, DB_NAME, DB_PCONNECT);
} else {
    http_response_code(500);
    exit;
}

$g_options = getOptions();

function hlxav_clean_buffers(): void
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
}

function hlxav_fail(int $code): void
{
    hlxav_clean_buffers();
    http_response_code($code);
    if ($code === 503) {
        header('Retry-After: 2');
    }
    exit;
}

function hlxav_send_png(string $file, int $max_age, string $raw_ims): void
{
    $mtime = (int)@filemtime($file);
    $size  = (int)@filesize($file);
    if ($mtime <= 0 || $size <= 0) {
        return;
    }
    $etag = '"' . dechex($mtime) . '-' . dechex($size) . '"';
    $inm  = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string)$_SERVER['HTTP_IF_NONE_MATCH']) : '';
    $ims  = ($raw_ims !== '') ? (int)@strtotime($raw_ims) : 0;

    hlxav_clean_buffers();
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=' . max(1, $max_age));

    if (($inm !== '' && strpos($inm, $etag) !== false) || ($ims > 0 && $ims >= $mtime)) {
        http_response_code(304);
        exit;
    }
    header('Content-Type: image/png');
    header('Content-Length: ' . $size);
    readfile($file);
    exit;
}

function hlxav_serve_cached(string $file, int $ttl, string $raw_ims, bool $allow_stale): void
{
    if (!is_file($file)) {
        return;
    }
    $age = time() - (int)@filemtime($file);
    if ($age < $ttl) {
        hlxav_send_png($file, $ttl - $age, $raw_ims);
    } elseif ($allow_stale) {
        hlxav_send_png($file, 30, $raw_ims);
    }
}

function hlxav_atomic_write(string $file, string $data): bool
{
    $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (@file_put_contents($tmp, $data, LOCK_EX) === false) {
        @unlink($tmp);
        return false;
    }
    @chmod($tmp, 0644);
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function hlxav_gc(string $cache_dir): void
{
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }
    $now = time();
    $files = array();
    foreach (array('card_player_*', 'card_img_*', '*.lock') as $pattern) {
        $g = @glob($cache_dir . '/' . $pattern);
        if (is_array($g)) {
            $files = array_merge($files, array_slice($g, 0, 2000));
        }
    }
    foreach ($files as $f) {
        $mt = (int)@filemtime($f);
        $cutoff = (substr($f, -4) === '.tmp') ? 3600 : 604800;
        if ($mt > 0 && ($now - $mt) > $cutoff) {
            @unlink($f);
        }
    }
}

function hlxav_trusted_cdn_url(string $url): bool
{
    if ($url === '' || strlen($url) > 255 || preg_match('/[\x00-\x20\x7F]/', $url)) {
        return false;
    }
    $p = @parse_url($url);
    if (!is_array($p) || ($p['scheme'] ?? '') !== 'https') {
        return false;
    }
    if (isset($p['user']) || isset($p['pass']) || isset($p['port'])) {
        return false;
    }
    $host = strtolower($p['host'] ?? '');
    if ($host === '' || !preg_match('/^[a-z0-9.\-]+$/', $host)) {
        return false;
    }
    return (bool)preg_match('/(^|\.)(steamstatic\.com|steampowered\.com|steamcommunity\.com)$/', $host);
}

function hlxav_http_get(string $url, int $max_bytes, int $connect_timeout = 2, int $timeout = 3): array
{
    $body = '';
    $too_big = false;

    $ch = curl_init();
    $opts = array(
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; HLstatsX-SteamEngine/2.0)',
        CURLOPT_CONNECTTIMEOUT => $connect_timeout,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS      => 0,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_MAXFILESIZE    => $max_bytes,
        CURLOPT_WRITEFUNCTION  => function ($c, $data) use (&$body, &$too_big, $max_bytes) {
            if (strlen($body) + strlen($data) > $max_bytes) {
                $too_big = true;
                return 0;
            }
            $body .= $data;
            return strlen($data);
        },
    );
    if (defined('CURLOPT_PROTOCOLS_STR')) {
        $opts[CURLOPT_PROTOCOLS_STR] = 'https';
    } else {
        $opts[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
    }
    curl_setopt_array($ch, $opts);
    $ok = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($ok === false || $too_big) {
        return array($code, null);
    }
    return array($code, $body);
}

function hlxav_clean_text($s, int $max = 32): string
{
    $s = (string)$s;
    if (!preg_match('//u', $s)) {
        $s = (string)mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
    $s = preg_replace('/[\x{0000}-\x{001F}\x{007F}-\x{009F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2069}\x{FEFF}]/u', '', $s) ?? '';
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    if (mb_strlen($s, 'UTF-8') > $max) {
        $s = mb_substr($s, 0, $max - 2, 'UTF-8') . '..';
    }
    return ($s === '') ? 'Unknown' : $s;
}

function hlxav_text($img, int $size, int $x, int $y, int $color, string $font, string $text, int $gdfont = 3): void
{
    if ($font !== '' && function_exists('imagettftext')) {
        $r = @imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
        if ($r !== false) {
            return;
        }
    }
    $ascii = preg_replace('/[^\x20-\x7E]/', '?', $text);
    imagestring($img, $gdfont, $x, max(0, $y - $size), (string)$ascii, $color);
}

function hlxav_box($img, int $x, int $y, int $w, int $h, int $fill, int $border): void
{
    imagefilledrectangle($img, $x, $y, $x + $w - 1, $y + $h - 1, $fill);
    imagerectangle($img, $x, $y, $x + $w - 1, $y + $h - 1, $border);
}

function hlxav_safe_image(string $path): string
{
    $real = @realpath($path);
    $base = @realpath(IMAGE_PATH);
    if ($real === false || $base === false) {
        return '';
    }
    if (strpos($real, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) {
        return '';
    }
    return $real;
}

function hlxav_load_image_file(string $path)
{
    $path = hlxav_safe_image($path);
    if ($path === '') {
        return null;
    }
    $info = @getimagesize($path);
    if (!$info) {
        return null;
    }
    switch ($info[2]) {
        case IMAGETYPE_PNG:  $im = @imagecreatefrompng($path);  break;
        case IMAGETYPE_GIF:  $im = @imagecreatefromgif($path);  break;
        case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($path); break;
        default: $im = false;
    }
    return $im ? $im : null;
}

function hlxav_draw_file($img, string $path, int $x, int $y, int $w, int $h): bool
{
    $im = hlxav_load_image_file($path);
    if (!$im) {
        return false;
    }
    imagecopyresampled($img, $im, $x, $y, 0, 0, $w, $h, imagesx($im), imagesy($im));
    imagedestroy($im);
    return true;
}

function hlxav_steam_community_id(string $uid): string
{
    if (PHP_INT_SIZE < 8) {
        return '';
    }
    $base = 76561197960265728;
    $uid = trim($uid);
    if (preg_match('/^7656119[0-9]{10}$/', $uid)) {
        return $uid;
    }
    if (preg_match('/^\[?U:1:([0-9]{1,10})\]?$/i', $uid, $m)) {
        return (string)($base + (int)$m[1]);
    }
    if (preg_match('/^(?:STEAM_[0-5]:)?([01]):([0-9]{1,10})$/i', $uid, $m)) {
        return (string)($base + ((int)$m[2] * 2) + (int)$m[1]);
    }
    return '';
}

function hlxav_ensure_steamcache($db, array &$g_options): void
{
    if (!empty($g_options['steamcache_installed'])) {
        return;
    }
    $chk = $db->query("SHOW TABLES LIKE 'hlstats_SteamCache'");
    if ($db->num_rows($chk) == 0) {
        $db->query("
            CREATE TABLE IF NOT EXISTS `hlstats_SteamCache` (
                `communityId` varchar(32) NOT NULL DEFAULT '',
                `status` varchar(64) NOT NULL DEFAULT 'Unknown',
                `avatar` varchar(255) NOT NULL DEFAULT '',
                `updated` int(10) unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`communityId`),
                KEY `idx_updated` (`updated`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
    if ($chk) {
        $db->free_result($chk);
    }
    $db->query("INSERT INTO `hlstats_Options` (`keyname`, `value`, `opttype`)
                VALUES ('steamcache_installed', '1', 2)
                ON DUPLICATE KEY UPDATE `value` = '1'");
    $g_options['steamcache_installed'] = '1';
}

function hlxav_steam_profile($db, string $coid, array &$g_options, string $ua, int $now): array
{
    hlxav_ensure_steamcache($db, $g_options);

    $coid_esc = $db->escape($coid);
    $avatar = '';
    $status = 'Unknown';
    $fresh  = false;

    $res = $db->query("SELECT `status`, `avatar`, `updated` FROM `hlstats_SteamCache` WHERE `communityId` = '$coid_esc' LIMIT 1");
    if ($res && ($row = $db->fetch_array($res))) {
        if (!empty($row['status'])) {
            $status = (string)$row['status'];
        }
        if (!empty($row['avatar']) && hlxav_trusted_cdn_url((string)$row['avatar'])) {
            $avatar = (string)$row['avatar'];
        }
        $fresh = (($now - (int)$row['updated']) < 900);
    }
    if ($res) {
        $db->free_result($res);
    }

    $is_bot = ($ua === '')
        || ((bool)preg_match('/bot|crawl|slurp|spider|mediapartners|semrush|ahrefs/i', $ua)
            && !preg_match('/discord/i', $ua));

    $cooldown_until = 0;
    $c_res = $db->query("SELECT `value` FROM `hlstats_Options` WHERE `keyname` = 'steam_api_cooldown' LIMIT 1");
    if ($c_res && ($c_row = $db->fetch_row($c_res))) {
        $cooldown_until = (int)$c_row[0];
    }
    if ($c_res) {
        $db->free_result($c_res);
    }

    if (!$fresh && !$is_bot && $now >= $cooldown_until) {
        list($code, $xml) = hlxav_http_get('https://steamcommunity.com/profiles/' . $coid . '?xml=1', 524288, 2, 3);

        if ($xml === null || $code >= 500 || $code === 429 || $code === 0) {
            $pause = $now + 180;
            $db->query("INSERT INTO `hlstats_Options` (`keyname`, `value`, `opttype`) VALUES ('steam_api_cooldown', '$pause', 0) ON DUPLICATE KEY UPDATE `value` = '$pause'");
            $db->query("INSERT INTO `hlstats_SteamCache` (`communityId`, `status`, `avatar`, `updated`) VALUES ('$coid_esc', 'Unknown', '', $now) ON DUPLICATE KEY UPDATE `updated` = $now");
        } elseif ($code === 200 && $xml !== '') {
            $xmlDoc = false;
            if (stripos($xml, '<!DOCTYPE') === false) {
                $xmlDoc = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            }
            if ($xmlDoc) {
                if (isset($xmlDoc->onlineState)) {
                    $st = preg_replace('/[^A-Za-z \-]/', '', trim((string)$xmlDoc->onlineState));
                    if ($st !== null && $st !== '') {
                        $status = substr(ucwords(strtolower($st)), 0, 64);
                    }
                }
                if (!empty($xmlDoc->avatarFull)) {
                    $raw_avatar = trim((string)$xmlDoc->avatarFull);
                    if (filter_var($raw_avatar, FILTER_VALIDATE_URL) && hlxav_trusted_cdn_url($raw_avatar)) {
                        $avatar = $raw_avatar;
                    }
                }
            }
            $status_esc = $db->escape($status);
            $avatar_esc = $db->escape($avatar);
            $db->query("
                INSERT INTO `hlstats_SteamCache` (`communityId`, `status`, `avatar`, `updated`)
                VALUES ('$coid_esc', '$status_esc', '$avatar_esc', $now)
                ON DUPLICATE KEY UPDATE `status` = '$status_esc', `avatar` = '$avatar_esc', `updated` = $now
            ");
        } else {
            $db->query("INSERT INTO `hlstats_SteamCache` (`communityId`, `status`, `avatar`, `updated`) VALUES ('$coid_esc', 'Unknown', '', $now) ON DUPLICATE KEY UPDATE `updated` = $now");
        }
    }

    if (!$is_bot && mt_rand(1, 100) === 1) {
        $expire_cutoff = $now - 2592000;
        $db->query("DELETE FROM `hlstats_SteamCache` WHERE `updated` < $expire_cutoff ORDER BY `updated` ASC LIMIT 200");
    }

    return array($avatar, $status);
}

function hlxav_avatar_image(string $url, string $cache_dir, bool $cache_ok, int $now)
{
    if (!hlxav_trusted_cdn_url($url)) {
        return null;
    }
    $file = $cache_dir . '/card_img_' . sha1($url) . '.png';

    if ($cache_ok && is_file($file) && ($now - (int)@filemtime($file)) < 259200) {
        $im = @imagecreatefrompng($file);
        if ($im) {
            return $im;
        }
    }

    list($code, $raw) = hlxav_http_get($url, 524288, 2, 3);
    if ($code !== 200 || !is_string($raw) || $raw === '') {
        return null;
    }
    $info = @getimagesizefromstring($raw);
    if (!$info
        || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF), true)
        || $info[0] < 16 || $info[1] < 16 || $info[0] > 1024 || $info[1] > 1024) {
        return null;
    }
    $src = @imagecreatefromstring($raw);
    if (!$src) {
        return null;
    }
    $thumb = imagecreatetruecolor(100, 100);
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    $trans_color = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
    imagefilledrectangle($thumb, 0, 0, 99, 99, $trans_color);
    imagealphablending($thumb, true);

    imagecopyresampled($thumb, $src, 0, 0, 0, 0, 100, 100, imagesx($src), imagesy($src));
    imagedestroy($src);

    if ($cache_ok) {
        ob_start();
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagepng($thumb, null, 6);
        $png = (string)ob_get_clean();
        if ($png !== '') {
            hlxav_atomic_write($file, $png);
        }
    }
    return $thumb;
}

function hlxav_placeholder_avatar(string $font)
{
    $im = hlxav_load_image_file(IMAGE_PATH . '/unknown.jpg');
    if ($im) {
        return $im;
    }
    $im = imagecreatetruecolor(100, 100);
    $bg = imagecolorallocate($im, 56, 58, 64);
    $fg = imagecolorallocate($im, 148, 155, 164);
    imagefilledrectangle($im, 0, 0, 100, 100, $bg);
    hlxav_text($im, 36, 36, 66, $fg, $font, '?', 5);
    return $im;
}

$player_id = 0;

if (isset($_GET['player_id']) && is_string($_GET['player_id'])) {
    $v = $_GET['player_id'];
    if (ctype_digit($v) && strlen($v) <= 10) {
        $player_id = (int)$v;
    }
} elseif (isset($_GET['player']) && is_string($_GET['player'])) {
    $v = $_GET['player'];
    if (ctype_digit($v) && strlen($v) <= 10) {
        $player_id = (int)$v;
    }
} elseif (isset($_GET['steam_id']) && is_string($_GET['steam_id'])) {
    $raw_steam = preg_replace('/[^A-Za-z0-9_:]/', '', trim($_GET['steam_id']));
    $raw_steam = substr((string)$raw_steam, 0, 64);
    $lookup = array();

    if (PHP_INT_SIZE >= 8 && preg_match('/^7656119\d{10}$/', $raw_steam)) {
        $steamId64 = (int)$raw_steam;
        $steamId32 = $steamId64 - 76561197960265728;
        $y = $steamId32 % 2;
        $z = intdiv($steamId32 - $y, 2);
        $lookup[] = (string)$steamId64;
        $lookup[] = "{$y}:{$z}";
        $lookup[] = "STEAM_1:{$y}:{$z}";
        $lookup[] = "STEAM_0:{$y}:{$z}";
    } elseif (preg_match('/^(?:STEAM_[01]:)?([01]):(\d{1,10})$/i', $raw_steam, $m)) {
        $y = (int)$m[1];
        $z = (int)$m[2];
        $lookup[] = (string)(76561197960265728 + ($z * 2) + $y);
        $lookup[] = "{$y}:{$z}";
        $lookup[] = "STEAM_1:{$y}:{$z}";
        $lookup[] = "STEAM_0:{$y}:{$z}";
    } elseif ($raw_steam !== '') {
        $lookup[] = $raw_steam;
    }

    if ($lookup) {
        $escaped_ids = implode("','", array_map(array($db, 'escape'), array_unique($lookup)));
        $game_cond = '';
        if (isset($_GET['game']) && is_string($_GET['game']) && preg_match('/^[A-Za-z0-9_\-]{1,32}$/', $_GET['game'])) {
            $game_cond = "AND u.game = '" . $db->escape($_GET['game']) . "'";
        }
        $resU = $db->query("
            SELECT u.playerId
            FROM hlstats_PlayerUniqueIds u
            JOIN hlstats_Players p ON p.playerId = u.playerId
            WHERE u.uniqueId IN ('$escaped_ids') $game_cond
            ORDER BY p.last_event DESC
            LIMIT 1
        ");
        if ($resU) {
            if ($rowU = $db->fetch_row($resU)) {
                $player_id = (int)$rowU[0];
            }
            $db->free_result($resU);
        }
    }
}

if ($player_id <= 0) {
    hlxav_fail(404);
}

$now = time();
$update_interval = 900;
$degraded_ttl = 300;

$cache_dir = IMAGE_PATH . '/progress';
if (!is_dir($cache_dir)) {
    @mkdir($cache_dir, 0755, true);
}
$cache_ok = is_dir($cache_dir) && is_writable($cache_dir);
$cache_file = $cache_dir . '/card_player_' . $player_id . '.png';

if ($cache_ok && mt_rand(1, 100) === 1) {
    register_shutdown_function('hlxav_gc', $cache_dir);
}

hlxav_serve_cached($cache_file, $update_interval, $hlxav_raw_ims, false);

$lock = null;
$have_lock = false;
if ($cache_ok) {
    $lock = @fopen($cache_file . '.lock', 'c');
    if ($lock) {
        $have_lock = @flock($lock, LOCK_EX | LOCK_NB);
        if (!$have_lock) {
            hlxav_serve_cached($cache_file, $update_interval, $hlxav_raw_ims, true);
            for ($i = 0; $i < 30 && !$have_lock; $i++) {
                usleep(100000);
                hlxav_serve_cached($cache_file, $update_interval, $hlxav_raw_ims, false);
                $have_lock = @flock($lock, LOCK_EX | LOCK_NB);
            }
            if (!$have_lock) {
                hlxav_fail(503);
            }
        }
        if (is_file($cache_file) && (time() - (int)@filemtime($cache_file)) < $update_interval) {
            @flock($lock, LOCK_UN);
            @fclose($lock);
            $lock = null;
            hlxav_send_png($cache_file, $update_interval - (time() - (int)@filemtime($cache_file)), $hlxav_raw_ims);
        }
    }
}

$resP = $db->query("
    SELECT
        playerId, game, lastName, flag, country, kills, deaths, skill, mmrank, headshots, blockavatar,
        IFNULL(ROUND(kills / IF(deaths = 0, 1, deaths), 2), '-') AS kpd,
        IFNULL(ROUND(headshots / NULLIF(kills, 0) * 100, 1), 0.0) AS hpk,
        activity, hideranking
    FROM hlstats_Players
    WHERE playerId = " . (int)$player_id . "
    LIMIT 1
");

if (!$resP || $db->num_rows($resP) !== 1) {
    hlxav_fail(404);
}
$playerdata = $db->fetch_array($resP);
$db->free_result($resP);

$game = (string)$playerdata['game'];
$game_esc = $db->escape($game);

$resTotal = $db->query("SELECT COUNT(*) FROM hlstats_Players WHERE game = '$game_esc' AND hideranking = 0");
$total_players = 0;
if ($resTotal && ($rT = $db->fetch_row($resTotal))) {
    $total_players = (int)$rT[0];
}
if ($resTotal) {
    $db->free_result($resTotal);
}

if (($playerdata['activity'] > 0) && ($playerdata['hideranking'] == 0)) {
    $rank_pos = get_player_rank($playerdata);
} else {
    $rank_pos = ($playerdata['hideranking'] == 1) ? 'Hidden' : (($playerdata['hideranking'] == 2) ? 'Banned' : 'Inactive');
}

$uqid = '';
$resSteamId = $db->query("SELECT uniqueId FROM hlstats_PlayerUniqueIds WHERE playerId = " . (int)$player_id . " LIMIT 1");
if ($resSteamId) {
    if ($rSId = $db->fetch_row($resSteamId)) {
        $uqid = trim((string)$rSId[0]);
    }
    $db->free_result($resSteamId);
}
$coid = hlxav_steam_community_id($uqid);

$is_game_bot = (bool)preg_match('/^(BOT|STEAM_ID_LAN|STEAM_ID_PENDING|UNKNOWN)/i', $uqid)
    || ($coid === '76561197960265728')
    || !preg_match('/^7656119[0-9]{10}$/', $coid);

$font_file = (string)@realpath(IMAGE_PATH . '/sig/font/DejaVuSans.ttf');
if ($font_file === '' || !is_file($font_file)) {
    $font_file = '';
}

$avatar_im = null;
$status    = 'Unknown';
$degraded  = false;

if (!$is_game_bot && empty($playerdata['blockavatar'])) {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
    list($avatar_url, $status) = hlxav_steam_profile($db, $coid, $g_options, $ua, $now);
    if ($avatar_url !== '') {
        $avatar_im = hlxav_avatar_image($avatar_url, $cache_dir, $cache_ok, $now);
    }
    if (!$avatar_im) {
        $degraded = true;
    }
}
if (!$avatar_im) {
    $avatar_im = hlxav_placeholder_avatar($font_file);
}

$mil_rank_name = 'Recruit';
$mil_rank_img  = '';
$resMilRank = $db->query("
    SELECT rankName, image
    FROM hlstats_Ranks
    WHERE minKills <= " . (int)$playerdata['kills'] . " AND game = '$game_esc'
    ORDER BY minKills DESC
    LIMIT 1
");
if ($resMilRank) {
    if ($rM = $db->fetch_array($resMilRank)) {
        $mil_rank_name = hlxav_clean_text($rM['rankName'], 30);
        $img_obj = getImage('/ranks/' . ($rM['image'] ?? ''));
        if (is_array($img_obj) && !empty($img_obj['path'])) {
            $mil_rank_img = (string)$img_obj['path'];
        }
    }
    $db->free_result($resMilRank);
}

$w = 512;
$h = 512;
$img = imagecreatetruecolor($w, $h);
imagealphablending($img, true);
imagesavealpha($img, true);

$col_bg     = imagecolorallocate($img, 30, 31, 34);
$col_card   = imagecolorallocate($img, 43, 45, 49);
$col_inner  = imagecolorallocate($img, 35, 36, 40);
$col_border = imagecolorallocate($img, 56, 58, 64);
$col_blurple = imagecolorallocate($img, 88, 101, 242);
$col_gold   = imagecolorallocate($img, 241, 196, 15);
$col_white  = imagecolorallocate($img, 255, 255, 255);
$col_gray   = imagecolorallocate($img, 148, 155, 164);
$col_online = imagecolorallocate($img, 35, 165, 90);
$col_offline = imagecolorallocate($img, 128, 132, 142);

imagefilledrectangle($img, 0, 0, $w, $h, $col_bg);
hlxav_box($img, 16, 16, $w - 32, $h - 32, $col_card, $col_border);
imagefilledrectangle($img, 17, 17, $w - 17, 22, $col_blurple);

$av_x = 36; $av_y = 42; $av_s = 100;
imagefilledrectangle($img, $av_x, $av_y, $av_x + $av_s - 1, $av_y + $av_s - 1, $col_inner);
imagecopyresampled($img, $avatar_im, $av_x, $av_y, 0, 0, $av_s, $av_s, imagesx($avatar_im), imagesy($avatar_im));
imagedestroy($avatar_im);
imagerectangle($img, $av_x - 1, $av_y - 1, $av_x + $av_s, $av_y + $av_s, $col_border);

$is_online = (stripos((string)$status, 'online') !== false || stripos((string)$status, 'in-game') !== false);
$status_color = $is_online ? $col_online : $col_offline;
imagefilledellipse($img, $av_x + $av_s - 8, $av_y + $av_s - 8, 16, 16, $col_card);
imagefilledellipse($img, $av_x + $av_s - 8, $av_y + $av_s - 8, 10, 10, $status_color);

$name_x = 152;
$name_y = 72;
$flag_code = (string)$playerdata['flag'];
if ($flag_code !== '' && preg_match('/^[A-Za-z0-9]{1,16}$/', $flag_code)) {
    $flag_file = getFlag($flag_code, 'path');
    if ($flag_file && hlxav_draw_file($img, (string)$flag_file, $name_x, $name_y - 14, 24, 16)) {
        $name_x += 32;
    }
}

$player_name = hlxav_clean_text($playerdata['lastName'], 18);
hlxav_text($img, 17, $name_x, $name_y, $col_white, $font_file, $player_name, 5);

if (is_numeric($rank_pos)) {
    $rank_line = 'Rank: #' . number_format((float)$rank_pos) . ' / ' . number_format($total_players);
} else {
    $rank_line = 'Rank: ' . $rank_pos;
}
hlxav_text($img, 11, 152, $name_y + 30, $col_gold, $font_file, $rank_line, 3);
hlxav_text($img, 11, 152, $name_y + 54, $col_gray, $font_file, 'Points: ' . number_format((int)$playerdata['skill']), 3);

$stats = array(
    array('KILLS',     number_format((int)$playerdata['kills'])),
    array('DEATHS',    number_format((int)$playerdata['deaths'])),
    array('K/D RATIO', (string)$playerdata['kpd']),
    array('HEADSHOTS', number_format((int)$playerdata['headshots']) . ' (' . $playerdata['hpk'] . '%)'),
);
$box_w = 214;
$box_h = 66;
for ($i = 0; $i < 4; $i++) {
    $bx = 36 + ($i % 2) * ($box_w + 12);
    $by = 168 + (int)floor($i / 2) * ($box_h + 10);
    hlxav_box($img, $bx, $by, $box_w, $box_h, $col_inner, $col_border);
    hlxav_text($img, 9,  $bx + 16, $by + 24, $col_gray,  $font_file, $stats[$i][0], 2);

    $val_color = $col_white;
    if ($i === 2 && is_numeric($playerdata['kpd']) && (float)$playerdata['kpd'] >= 1.0) {
        $val_color = $col_gold;
    }

    hlxav_text($img, 15, $bx + 16, $by + 52, $val_color, $font_file, $stats[$i][1], 4);
}

$badge_y = 330;
$badge_w = 214;
$badge_h = 110;

$lx = 36;
hlxav_box($img, $lx, $badge_y, $badge_w, $badge_h, $col_inner, $col_border);
hlxav_text($img, 9,  $lx + 14, $badge_y + 22, $col_gray,  $font_file, 'MILITARY RANK', 2);
hlxav_text($img, 10, $lx + 14, $badge_y + 96, $col_white, $font_file, $mil_rank_name, 2);
if ($mil_rank_img !== '') {
    hlxav_draw_file($img, $mil_rank_img, $lx + 75, $badge_y + 28, 64, 48);
}

$rx = 36 + $badge_w + 12;
hlxav_box($img, $rx, $badge_y, $badge_w, $badge_h, $col_inner, $col_border);
hlxav_text($img, 9, $rx + 14, $badge_y + 22, $col_gray, $font_file, 'COMPETITIVE RANK', 2);
$mmrank = (int)($playerdata['mmrank'] ?? 0);
$rank_drawn = hlxav_draw_file($img, IMAGE_PATH . '/mmranks/' . $mmrank . '.png', $rx + 35, $badge_y + 36, 144, 56);
if (!$rank_drawn) {
    hlxav_text($img, 11, $rx + 68, $badge_y + 65, $col_gray, $font_file, 'Unranked', 3);
}
$site_host = @parse_url((string)($g_options['siteurl'] ?? $g_options['scripturl'] ?? ''), PHP_URL_HOST);
$brand = $site_host ? hlxav_clean_text($site_host, 40) : 'HLstatsX:CE';
hlxav_text($img, 9, 36, $h - 26, $col_gray, $font_file, $brand, 2);

ob_start();
imagepng($img, null, 6);
$png = (string)ob_get_clean();
imagedestroy($img);

if ($png === '') {
    hlxav_fail(500);
}

$ttl = $degraded ? $degraded_ttl : $update_interval;

if ($cache_ok && hlxav_atomic_write($cache_file, $png)) {
    if ($degraded) {
        @touch($cache_file, $now - $update_interval + $degraded_ttl);
    }
}
if ($lock) {
    @flock($lock, LOCK_UN);
    @fclose($lock);
}

hlxav_clean_buffers();
header('Content-Type: image/png');
header('Content-Length: ' . strlen($png));
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $now) . ' GMT');
header('Cache-Control: public, max-age=' . $ttl);
echo $png;
exit;
