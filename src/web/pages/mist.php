<?php
/*
   HLstatsX Modernized Edition - MistServer Live Stream Engine
   Version: 1.0
   Project: https://github.com/lovasatt/hlstatsx-community-edition
   Author:  lovasatt (2026)
*/

if (!defined('IN_HLSTATS')) {
    die('Do not access this file directly.');
}

global $db, $g_options, $game;

require_once(PAGE_PATH . '/miststatus.php');

$stId = isset($_GET['stId']) ? (int)$_GET['stId'] : 0;

if ($stId <= 0) {
    pageHeader(['Live Stream', 'Broadcast Viewer'], ['Live Stream' => '', 'Broadcast Viewer' => '']);
    echo "<div class=\"warning\"><span id=\"warning-header\"><strong>Error:</strong> Invalid Stream Server ID.</span></div><br />\n";
    pageFooter();
    die();
}

$result = $db->query("SELECT serverId, name, addr, password, descr, queryPort, UDPPort FROM hlstats_Servers_VoiceComm WHERE serverId=$stId AND serverType=4 LIMIT 1");
$s = false;
if ($result) {
    $s = $db->fetch_array($result);
    $db->free_result($result);
}

if (!$s) {
    pageHeader(['Live Stream', 'Broadcast Viewer'], ['Live Stream' => '', 'Broadcast Viewer' => '']);
    echo "<div class=\"warning\"><span id=\"warning-header\"><strong>Error:</strong> Stream not found in database.</span></div><br />\n";
    pageFooter();
    die();
}

pageHeader(
    ['Live Stream', 'Broadcast Viewer'],
    ['Live Stream' => '', 'Broadcast Viewer' => '']
);

$host        = CMistStatus::sanitizeHost((string)$s['addr']);
$http_port   = ((int)$s['queryPort'] > 0) ? (int)$s['queryPort'] : 8080;
$stream_key  = trim((string)$s['password']);

if ($host === '' || $stream_key === '') {
    echo "<div class=\"warning\"><span id=\"warning-header\"><strong>Error:</strong> Invalid MistServer host or stream name in the Voice Server settings.</span></div><br />\n";
    pageFooter();
    die();
}

$raw_name     = trim((string)$s['name']);
$display_name = ($raw_name !== '') ? $raw_name : "Live Stream ({$stream_key})";
$safe_name    = htmlspecialchars($display_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safe_descr   = htmlspecialchars((string)($s['descr'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$ms = new CMistStatus();
$ms->Request($host, $http_port, $stream_key);

$forwarded_proto = '';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO']);
    $forwarded_proto = strtolower(trim($parts[0]));
}

$forwarded_rfc = false;
if (!empty($_SERVER['HTTP_FORWARDED']) && preg_match('/proto=("?)https\1/i', $_SERVER['HTTP_FORWARDED'])) {
    $forwarded_rfc = true;
}

$is_cf_https = false;
if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
    $cf_visitor = json_decode($_SERVER['HTTP_CF_VISITOR'], true);
    $is_cf_https = (is_array($cf_visitor) && isset($cf_visitor['scheme']) && strtolower($cf_visitor['scheme']) === 'https');
}

$is_https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || ($forwarded_proto === 'https')
    || $forwarded_rfc
    || $is_cf_https
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
    || (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower((string)$_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off');

$proto = ($is_https || CMistStatus::isTlsPort($http_port)) ? 'https' : 'http';

$base_http_url    = CMistStatus::buildBaseUrl($proto, $host, $http_port);
$stream_path      = CMistStatus::encodeStream($stream_key);
$player_embed_url = "{$base_http_url}/{$stream_path}.html";
$direct_m3u8_url  = "{$base_http_url}/hls/{$stream_path}/index.m3u8";

$ratio = 16 / 9;
if ($ms->m_width > 0 && $ms->m_height > 0) {
    $r = $ms->m_width / $ms->m_height;
    if ($r >= 0.4 && $r <= 3.5) {
        $ratio = $r;
    }
}
$pad_pct   = number_format(100 / $ratio, 4, '.', '');
$max_w_vh  = number_format(78 * $ratio, 2, '.', '');
$popup_w   = 1280;
$popup_h   = (int)max(300, min(1000, round($popup_w / $ratio)));

$safe_embed = htmlspecialchars($player_embed_url, ENT_QUOTES, 'UTF-8');
$safe_m3u8  = htmlspecialchars($direct_m3u8_url, ENT_QUOTES, 'UTF-8');
$row_idx    = 0;
?>

<!-- Section: Stream Details & Information -->
<div class="block">
    <?php printSectionTitle('Live Broadcast &amp; Match Information'); ?>
    <div class="subblock">
        <table class="data-table" style="width:100%;">
            <tr class="<?php echo ($row_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="width:20%; font-weight:bold; vertical-align:middle;">Broadcast:</td>
                <td style="vertical-align:middle;">
                    <div style="display:inline-flex; align-items:center; vertical-align:middle; gap:8px;">
                        <img src="<?php echo htmlspecialchars(IMAGE_PATH . '/mist/mist.png', ENT_QUOTES, 'UTF-8'); ?>" alt="mist" width="28" height="28" style="width:28px; height:28px; border-radius:4px; vertical-align:middle; border:1px solid rgba(0,0,0,0.15); box-shadow:0 1px 3px rgba(0,0,0,0.25); object-fit:contain; background:rgba(0,0,0,0.05);" />
                        <strong style="font-size:14px; line-height:1;"><?php echo $safe_name; ?></strong>
                        <?php if ($ms->m_is_online): ?>
                            <span style="background:#2ecc71; color:#fff; font-size:10px; font-weight:bold; padding:3px 8px; border-radius:3px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; line-height:1;">🟢 Online</span>
                        <?php else: ?>
                            <span style="background:#e74c3c; color:#fff; font-size:10px; font-weight:bold; padding:3px 8px; border-radius:3px; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; line-height:1;">🔴 Offline</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php if (!empty($safe_descr)): ?>
            <tr class="<?php echo ($row_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Match / Caster Notes:</td>
                <td><?php echo $safe_descr; ?></td>
            </tr>
            <?php endif; ?>
            <tr class="<?php echo ($row_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Stream Status:</td>
                <td>
                    <?php if ($ms->m_is_online): ?>
                        <span style="color:#2ecc71; font-weight:bold;">🟢 Active Transmission</span>
                        <?php if ($ms->m_width > 0): ?>
                            &mdash; <strong><?php echo (int)$ms->m_width . 'x' . (int)$ms->m_height; ?></strong>
                            <?php if ($ms->m_fps > 0) echo ' @ ' . (int)$ms->m_fps . 'fps'; ?>
                            <?php if ($ms->m_bitrate > 0) echo ' (' . number_format($ms->m_bitrate) . ' kbps)'; ?>
                            <?php
                                if (!empty($ms->m_video_codec)) {
                                    $codecs = $ms->m_video_codec;
                                    if (!empty($ms->m_audio_codec)) {
                                        $codecs .= ' + ' . $ms->m_audio_codec;
                                    }
                                    echo ' [' . htmlspecialchars($codecs, ENT_QUOTES, 'UTF-8') . ']';
                                }
                            ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:#f39c12; font-weight:bold;">⏳ <?php echo htmlspecialchars($ms->m_status_text, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr class="<?php echo ($row_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Actions &amp; Popout:</td>
                <td>
                    <button type="button" class="mist-popout-btn"
                            data-url="<?php echo $safe_embed; ?>"
                            onclick="window.open(this.getAttribute('data-url'), 'MistStreamPopout', 'width=<?php echo $popup_w; ?>,height=<?php echo $popup_h; ?>,toolbar=no,menubar=no,scrollbars=no,resizable=yes,noopener');"
                            style="cursor:pointer; font-size:11px; font-weight:bold; padding:4px 10px; border-radius:3px; background:#2575fc; color:#fff; border:none; margin-right:12px;">
                        📺 Theater Mode (Popout Window)
                    </button>
                    <a href="<?php echo $safe_embed; ?>" target="_blank" rel="noopener noreferrer" style="font-weight:bold; color:#2575fc; text-decoration:none; margin-right:15px; font-size:11px;">
                        🌐 Direct Web Player &raquo;
                    </a>
                    <a href="<?php echo $safe_m3u8; ?>" style="font-weight:bold; color:#f39c12; text-decoration:none; margin-right:15px; font-size:11px;" title="Open in VLC Media Player">
                        📥 HLS Stream (VLC)
                    </a>
                </td>
            </tr>
        </table>
    </div>
</div>

<br /><br />

<!-- Section: Live Cinema Player -->
<div class="block">
    <?php printSectionTitle('Live Video Transmission'); ?>
    <div class="subblock mist-player" style="padding:0; background:#000; overflow:hidden; border-radius:4px; box-shadow:0 4px 15px rgba(0,0,0,0.5);">

        <!-- Ratio box: height follows the stream aspect ratio, capped at 78vh, centered -->
        <div class="mist-ratio" style="position:relative; width:100%; max-width:<?php echo $max_w_vh; ?>vh; margin:0 auto; padding-bottom:<?php echo $pad_pct; ?>%; background:#000;">
            <iframe
                src="<?php echo $safe_embed; ?>"
                title="<?php echo $safe_name; ?>"
                style="position:absolute; top:0; left:0; width:100%; height:100%; border:0; display:block;"
                sandbox="allow-scripts allow-same-origin allow-popups allow-presentation"
                allow="autoplay *; fullscreen *; picture-in-picture *"
                loading="lazy"
                allowfullscreen>
            </iframe>
        </div>

    </div>
</div>