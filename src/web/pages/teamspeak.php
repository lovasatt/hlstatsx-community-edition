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

global $db;

require_once(PAGE_PATH . '/teamspeak_class.php');

$tsId = isset($_GET['tsId']) ? (int)$_GET['tsId'] : 0;

if ($tsId <= 0) {
    pageHeader(array('Voice Server', 'TeamSpeak Viewer'), array('Voice Server' => '', 'TeamSpeak Viewer' => ''));
    echo "<div class=\"warning\"><span id=\"warning-header\"><strong>Error:</strong> Invalid TeamSpeak Server ID.</span></div><br />\n";
    pageFooter();
    die();
}

$result = $db->query("SELECT addr, queryPort, UDPPort, name, password, descr FROM hlstats_Servers_VoiceComm WHERE serverId=$tsId LIMIT 1");
$s = false;
if ($result) {
    $s = $db->fetch_array($result);
    $db->free_result($result);
}

if (!$s) {
    pageHeader(array('Voice Server', 'TeamSpeak Viewer'), array('Voice Server' => '', 'TeamSpeak Viewer' => ''));
    echo "<div class=\"warning\"><span id=\"warning-header\"><strong>Error:</strong> Server not found.</span></div><br />\n";
    pageFooter();
    die();
}

pageHeader(
    array('Voice Server', 'TeamSpeak Viewer'),
    array('Voice Server' => '', 'TeamSpeak Viewer' => '')
);

$teamspeakDisplay = new teamspeakDisplayClass;

$uip   = trim((string)$s['addr']);
$tPort = ((int)$s['queryPort'] > 0) ? (int)$s['queryPort'] : 10011;
$port  = ((int)$s['UDPPort'] > 0)   ? (int)$s['UDPPort']   : 9987;
$raw_pass = (string)($s['password'] ?? '');

// Separate client connect password from TS6 WebQuery API key
$ts_api_key = '';
$ts_connect_pass = $raw_pass;
if (strpos($raw_pass, '|') !== false) {
    list($ts_connect_pass, $ts_api_key) = explode('|', $raw_pass, 2);
} elseif (strpos($raw_pass, 'apikey:') === 0) {
    $ts_api_key = substr($raw_pass, 7);
    $ts_connect_pass = '';
} elseif ($tPort === 10080 || strlen($raw_pass) >= 30) {
    $ts_api_key = $raw_pass;
    $ts_connect_pass = '';
}

$settings = $teamspeakDisplay->getDefaultSettings();
$settings['serveraddress']   = $uip;
$settings['serverqueryport'] = $tPort > 0 ? $tPort : 10011;
$settings['serverudpport']   = $port > 0 ? $port : 9987;
$settings['apikey']          = trim($ts_api_key);
$ts_info = $teamspeakDisplay->queryTeamspeakServerEx($settings);
$is_online = (isset($ts_info['queryerror']) && (int)$ts_info['queryerror'] === 0);

// --- VISUAL TEST MODE (Set to true to preview all icons, ranks and badges) ---
$test_mode = false;

if ($test_mode) {
    $is_online = true;
    $ts_info = [
        'queryerror' => 0,
        'serverinfo' => [
            'server_name'     => 'HLstatsX TeamSpeak Master Suite',
            'server_platform' => 'Linux x64',
            'server_maxusers' => 64,
            'server_uptime'   => 259200, // 3 days
            'server_version'  => '3.13.8'
        ],
        'channellist' => [
            1 => ['channelid' => 1, 'channelname' => 'Lobby / Welcome Area', 'topic' => 'Public entrance, feel free to chat!', 'parent' => -1, 'order' => 1],
            2 => ['channelid' => 2, 'channelname' => 'Counter-Strike 2 Ranked #1', 'topic' => 'Competitive 5v5 only - Mic required', 'parent' => -1, 'order' => 2],
            3 => ['channelid' => 3, 'channelname' => 'Team Fortress 2 Lounge', 'topic' => 'Casual payload and payload race', 'parent' => -1, 'order' => 3],
            4 => ['channelid' => 4, 'channelname' => 'Counter-Strike: Source Classic', 'topic' => 'Dust2 24/7 community games', 'parent' => -1, 'order' => 4],
            5 => ['channelid' => 5, 'channelname' => 'AFK / Quiet Lounge', 'topic' => 'Muted room for sleeping or eating', 'parent' => -1, 'order' => 5]
        ],
        'playerlist' => [
            1  => ['playerid' => 1,  'channelid' => 1, 'playername' => '[TEST]AdminTom [CS2]', 'is_away' => false, 'away_message' => '', 'is_muted' => false, 'is_deaf' => false, 'is_cc' => true],
            2  => ['playerid' => 2,  'channelid' => 2, 'playername' => 'ProGamer_CS2', 'is_away' => false, 'away_message' => '', 'is_muted' => false, 'is_deaf' => false, 'is_cc' => false],
            3  => ['playerid' => 3,  'channelid' => 2, 'playername' => 'Silent_Sniper | CSS', 'is_away' => false, 'away_message' => '', 'is_muted' => true,  'is_deaf' => false, 'is_cc' => false],
            4  => ['playerid' => 4,  'channelid' => 2, 'playername' => 'DeafPlayer_CS2', 'is_away' => false, 'away_message' => '', 'is_muted' => true,  'is_deaf' => true,  'is_cc' => false],
            5  => ['playerid' => 5,  'channelid' => 3, 'playername' => 'HeavyGuy (TF2)', 'is_away' => false, 'away_message' => '', 'is_muted' => false, 'is_deaf' => false, 'is_cc' => true],
            6  => ['playerid' => 6,  'channelid' => 3, 'playername' => 'Medic_TF2', 'is_away' => false, 'away_message' => '', 'is_muted' => false, 'is_deaf' => false, 'is_cc' => false],
            7  => ['playerid' => 7,  'channelid' => 4, 'playername' => 'SourceVeteran - CSS', 'is_away' => false, 'away_message' => '', 'is_muted' => false, 'is_deaf' => false, 'is_cc' => false],
            8  => ['playerid' => 8,  'channelid' => 5, 'playername' => 'SleepingNinja', 'is_away' => true,  'away_message' => 'Back in 30m', 'is_muted' => true,  'is_deaf' => true,  'is_cc' => false],
            9  => ['playerid' => 9,  'channelid' => 1, 'playername' => 'RandomGuest', 'is_away' => false, 'away_message' => '', 'is_muted' => false, 'is_deaf' => false, 'is_cc' => false],
            10 => ['playerid' => 10, 'channelid' => 1, 'playername' => 'Gamer_Girl [CS2]', 'is_away' => false, 'away_message' => '', 'is_muted' => false, 'is_deaf' => false, 'is_cc' => false],
            11 => ['playerid' => 11, 'channelid' => 2, 'playername' => 'RifleMaster_CS2', 'is_away' => false, 'away_message' => '', 'is_muted' => false, 'is_deaf' => false, 'is_cc' => false],
            12 => ['playerid' => 12, 'channelid' => 3, 'playername' => 'PyroManiac (TF2)', 'is_away' => false, 'away_message' => '', 'is_muted' => false, 'is_deaf' => false, 'is_cc' => false]
        ]
    ];
}
// --- VISUAL TEST MODE END ---
$db_name      = trim((string)($s['name'] ?? ''));
$live_ts_name = trim((string)($ts_info['serverinfo']['server_name'] ?? ''));

// If DB name is empty or identical to the server IP, use the live queried TS name
if ($db_name === '' || $db_name === $uip) {
    $display_server_name = ($live_ts_name !== '') ? $live_ts_name : 'TeamSpeak Server';
} else {
    $display_server_name = $db_name;
}
$safe_name   = htmlspecialchars($display_server_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safe_descr  = htmlspecialchars($s['descr'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
// 1-Click Launch URL for TS3 / TS5 / TS6 Clients (Secure: does not leak password or API key)
$join_url = "ts3server://" . urlencode($uip) . "?port=" . $port;

// Fetch supported games from HLstatsX for automatic icon resolution
$hlx_games = [];
$g_res = $db->query("SELECT code, name FROM hlstats_Games");
if ($g_res) {
    while ($g_row = $db->fetch_array($g_res)) {
        $hlx_games[strtolower(trim($g_row['name']))] = strtolower(trim($g_row['code']));
    }
    $db->free_result($g_res);
}

if (!function_exists('getTSGameBadgeHtml')) {
    function getTSGameBadgeHtml(string $activity_name, array $hlx_games): string {
        $act = trim($activity_name);
        if ($act === '') {
            return '';
        }

        $matched_code = null;
        $game_title   = '';

        // Match game tags STRICTLY AT THE END of nickname: [CS2], (CS2), _CS2, - CS2, | CS2, CS2
        if (preg_match('/(?:\[|\(|\s|_|-|\|)(cs2|cs-2|counter-strike\s*2)(?:\]|\))?\s*$/i', $act)) {
            $matched_code = 'cs2';
            $game_title   = 'Counter-Strike 2';
        } elseif (preg_match('/(?:\[|\(|\s|_|-|\|)(tf2|team\s*fortress\s*2)(?:\]|\))?\s*$/i', $act)) {
            $matched_code = 'tf';
            $game_title   = 'Team Fortress 2';
        } elseif (preg_match('/(?:\[|\(|\s|_|-|\|)(css|counter-strike:\s*source)(?:\]|\))?\s*$/i', $act)) {
            $matched_code = 'css';
            $game_title   = 'Counter-Strike: Source';
        }

        // Fallback: Check registered HLstatsX games strictly at the end of nickname
        if (!$matched_code) {
            foreach ($hlx_games as $g_name => $g_code) {
                if ($g_code === '' && $g_name === '') continue;
                $pattern = '/(?:\[|\(|\s|_|-|\|)(' . preg_quote($g_code, '/') . '|' . preg_quote($g_name, '/') . ')(?:\]|\))?\s*$/i';
                if (preg_match($pattern, $act)) {
                    $matched_code = $g_code;
                    $game_title   = ucfirst($g_name);
                    break;
                }
            }
        }

        // If no game tag matched at the end of nickname, do not display badge
        if (!$matched_code) {
            return '';
        }

        // Resolve registered game icon from HLstatsX
        $icon_url = '';
        if (function_exists('getImage')) {
            $img_info = getImage("/games/{$matched_code}/game");
            if (!empty($img_info['url'])) {
                $icon_url = (string)$img_info['url'];
            }
        }

        if (!empty($icon_url)) {
            $safe_title = htmlspecialchars($game_title ?: strtoupper($matched_code), ENT_QUOTES, 'UTF-8');
            return '<img src="' . htmlspecialchars($icon_url, ENT_QUOTES, 'UTF-8') . '" alt="' . $safe_title . '" title="' . $safe_title . '" width="16" height="16" loading="lazy" style="vertical-align:middle; margin-left:3px; cursor:help;" />';
        }

        return '';
    }
}

if (!function_exists('format_ts_uptime')) {
    function format_ts_uptime(int $seconds): string {
        $d = floor($seconds / 86400);
        $h = floor(($seconds % 86400) / 3600);
        $m = floor(($seconds % 3600) / 60);
        if ($d > 0) return "{$d}d {$h}h {$m}m";
        if ($h > 0) return "{$h}h {$m}m";
        return "{$m}m";
    }
}

?>

<!-- Section: Server Information -->
<div class="block">
    <?php printSectionTitle('TeamSpeak Server Information'); ?>
    <div class="subblock">
        <table class="data-table" style="width:100%;">
            <?php $info_idx = 0; ?>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="width:20%; font-weight:bold;">Community:</td>
                <td><?php echo $safe_name; ?> <?php if (!empty($live_ts_name) && strcasecmp($safe_name, $live_ts_name) !== 0): ?>&mdash; <em><?php echo htmlspecialchars($live_ts_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></em><?php endif; ?></td>
            </tr>
            <?php if (!empty($safe_descr)): ?>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Description:</td>
                <td><?php echo $safe_descr; ?></td>
            </tr>
            <?php endif; ?>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Server Address:</td>
                <td><code><?php echo htmlspecialchars($uip . ':' . $port, ENT_QUOTES, 'UTF-8'); ?></code></td>
            </tr>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Access &amp; Security:</td>
                <td>
                    <?php if (!empty($ts_connect_pass)): ?>
                        <span style="color:#f39c12; font-weight:bold;">🔒 Password Protected</span>
                    <?php else: ?>
                        <span style="color:#2ecc71; font-weight:bold;">🔓 Public / Free Access</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Active Presence:</td>
                <td>
                    <?php if ($is_online): ?>
                        <span style="color:#2ecc71; font-weight:bold;">🟢 <?php echo count($ts_info['playerlist'] ?? []); ?> / <?php echo (int)($ts_info['serverinfo']['server_maxusers'] ?? 32); ?> Users Online</span>
                        <span style="color:#888; margin-left:10px; font-size:11px;">(Uptime: <?php echo format_ts_uptime($ts_info['serverinfo']['server_uptime'] ?? 0); ?>, Platform: <?php echo htmlspecialchars($ts_info['serverinfo']['server_platform'] ?? 'Linux', ENT_QUOTES, 'UTF-8'); ?>)</span>
                    <?php else: ?>
                        <span style="color:#e74c3c; font-weight:bold;">🔴 Offline / Unavailable</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Voice Rooms:</td>
                <td><?php echo count($ts_info['channellist'] ?? []); ?> registered channels</td>
            </tr>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Join Server:</td>
                <td>
                    <?php if ($is_online): ?>
                        <a href="<?php echo $join_url; ?>" style="font-weight:bold; color:#2575fc; text-decoration:none;">
                            🚀 Launch TeamSpeak &amp; Connect
                        </a>
                    <?php else: ?>
                        <span style="color:#888; font-style:italic;">🔒 Server is currently unreachable</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<br /><br />

<?php if (!$is_online): ?>
    <div class="block">
        <?php printSectionTitle('TeamSpeak Server Status'); ?>
        <div class="subblock">
            <p style="color:#e74c3c; padding:15px; font-weight:bold; text-align:center; margin:0;">
                ⚠️ <?php echo htmlspecialchars($ts_info['error_msg'] ?? 'Could not establish connection to TeamSpeak ServerQuery.', ENT_QUOTES, 'UTF-8'); ?> (Port: <?php echo $tPort; ?>)
            </p>
        </div>
    </div>
<?php else: ?>

<!-- Section: Voice Channels Hierarchy -->
<div class="block">
    <?php printSectionTitle('Voice Lounges &amp; Active Channels'); ?>
    <div class="subblock">
        <table class="data-table" style="width:100%;">
            <tr class="data-table-head">
                <td class="fSmall" style="width:35%;">Room Name</td>
                <td class="fSmall" style="width:15%; text-align:center;">Occupancy</td>
                <td class="fSmall">Connected Members</td>
            </tr>
            <?php
            $channels = $ts_info['channellist'] ?? [];
            $players  = $ts_info['playerlist'] ?? [];

            if (!empty($channels)) {
                $ch_idx = 0;
                foreach ($channels as $cid => $ch) {
                    $row_class = ($ch_idx % 2 == 0) ? 'bg1' : 'bg2';
                    $ch_name = htmlspecialchars($ch['channelname'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                    $channel_users = array_filter($players, function($p) use ($cid) {
                        return (int)$p['channelid'] === (int)$cid;
                    });
                    $user_count = count($channel_users);
            ?>
                <tr class="<?php echo $row_class; ?>">
                    <td class="fHeading" style="vertical-align:middle;">
                        <img src="<?php echo IMAGE_PATH; ?>/teamspeak/channel.png" alt="" width="16" height="16" style="vertical-align:middle; margin-right:5px;" />
                        <?php echo $ch_name; ?>
                        <?php if (!empty($ch['topic'])): ?>
                            <div style="font-size:10px; color:#888; font-weight:normal; margin-left:21px;"><?php echo htmlspecialchars($ch['topic'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center; vertical-align:middle; font-weight:bold;">
                        <?php echo $user_count > 0 ? "<span style='color:#2ecc71;'>$user_count in room</span>" : "<span style='color:#888;'>Empty</span>"; ?>
                    </td>
                    <td style="vertical-align:middle;">
                        <?php if ($user_count > 0): ?>
                            <?php foreach ($channel_users as $u):
                                $uname = htmlspecialchars($u['playername'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $u_game_badge = getTSGameBadgeHtml($u['playername'], $hlx_games);
                            ?>
                                <span style="display:inline-block; background:rgba(0,0,0,0.12); border-radius:12px; padding:3px 8px; margin:2px 4px 2px 0; vertical-align:middle;">
                                    <?php if (!empty($u['is_cc'])): ?>
                                        <img src="<?php echo IMAGE_PATH; ?>/teamspeak/ccommander.png" alt="CC" title="Channel Commander" width="16" height="16" style="vertical-align:middle; margin-right:4px;" />
                                    <?php else: ?>
                                        <img src="<?php echo IMAGE_PATH; ?>/teamspeak/user.png" alt="" width="16" height="16" style="vertical-align:middle; margin-right:4px;" />
                                    <?php endif; ?>

                                    <strong style="max-width:130px; display:inline-block; vertical-align:middle; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo $uname; ?>"><?php echo $uname; ?></strong>

                                    <?php if (!empty($u['is_cc'])): ?>
                                        <span style="background:#f39c12; color:#ffffff; font-size:9px; font-weight:bold; padding:1px 4px; border-radius:3px; margin-left:3px; vertical-align:middle;">CC</span>
                                    <?php endif; ?>

                                    <?php if (!empty($u_game_badge)): ?>
                                        <?php echo $u_game_badge; ?>
                                    <?php endif; ?>

                                    <?php if (!empty($u['is_away'])): ?>
                                        <img src="<?php echo IMAGE_PATH; ?>/teamspeak/away.png" alt="Away" title="Away: <?php echo htmlspecialchars($u['away_message'], ENT_QUOTES, 'UTF-8'); ?>" width="14" height="14" style="vertical-align:middle; margin-left:4px;" />
                                    <?php endif; ?>

                                    <?php if (!empty($u['is_muted'])): ?>
                                        <img src="<?php echo IMAGE_PATH; ?>/teamspeak/muted.png" alt="Muted" title="Microphone Muted" width="14" height="14" style="vertical-align:middle; margin-left:4px;" />
                                    <?php endif; ?>

                                    <?php if (!empty($u['is_deaf'])): ?>
                                        <img src="<?php echo IMAGE_PATH; ?>/teamspeak/smuted.png" alt="Deafened" title="Speakers / Sound Deactivated" width="14" height="14" style="vertical-align:middle; margin-left:4px;" />
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span style="color:#888; font-style:italic;">No users connected to this room.</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php
                    $ch_idx++;
                }
            } else {
                echo '<tr class="bg1"><td colspan="3" style="text-align:center; padding:15px; color:#888;">No channels found on this server.</td></tr>';
            }
            ?>
        </table>
    </div>
</div>

<br /><br />

<!-- Section: Online Members Directory with Pagination -->
<a id="directory"></a>
<div class="block">
    <?php
        $total_dir_members = count($players);
        printSectionTitle('Online Members Directory' . ($total_dir_members > 0 ? " ($total_dir_members)" : ''));
    ?>
    <div class="subblock">
        <?php
            $allowed_per_page = [10, 20, 50, 100];
            $per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $allowed_per_page, true)
                        ? (int)$_GET['per_page']
                        : 20;

            $total_pages = max(1, (int)ceil($total_dir_members / $per_page));
            $current_page = isset($_GET['dpage']) ? max(1, min($total_pages, (int)$_GET['dpage'])) : 1;
            $offset = ($current_page - 1) * $per_page;
            $paged_members = array_slice($players, $offset, $per_page);

            global $game;
            $url_game = urlencode((string)($game ?? ''));
            $game_param = ($url_game !== '') ? "&amp;game={$url_game}" : '';

            $page_base_url = "hlstats.php?mode=teamspeak{$game_param}&amp;tsId={$tsId}&amp;per_page={$per_page}";
        ?>
            <?php if ($total_dir_members > 10): ?>
            <div style="margin-bottom: 10px; text-align: right; font-size: 11px;">
                <form method="get" action="hlstats.php" style="display:inline; margin:0;">
                    <input type="hidden" name="mode" value="teamspeak" />
                    <?php if (!empty($game)): ?>
                    <input type="hidden" name="game" value="<?php echo htmlspecialchars((string)$game, ENT_QUOTES, 'UTF-8'); ?>" />
                    <?php endif; ?>
                    <input type="hidden" name="tsId" value="<?php echo $tsId; ?>" />
                    <label for="per_page_select" style="color:inherit; opacity:0.8; margin-right:4px;">Members per page:</label>
                    <select id="per_page_select" name="per_page" onchange="this.form.submit()" style="padding:2px 4px; font-size:11px; border-radius:3px; background:inherit; color:inherit; border:1px solid rgba(128,128,128,0.4);">
                        <?php foreach ($allowed_per_page as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php echo ($per_page === $opt) ? 'selected="selected"' : ''; ?>><?php echo $opt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <?php endif; ?>

            <table class="data-table" style="width:100%;">
                <tr class="data-table-head">
                    <td class="fSmall" style="width:40px; text-align:center;">User</td>
                    <td class="fSmall" style="width:30%;">Nickname</td>
                    <td class="fSmall" style="width:25%;">Current Channel</td>
                    <td class="fSmall">Status &amp; Flags</td>
                </tr>
                <?php
                if (!empty($paged_members)) {
                    $m_idx = 0;
                    foreach ($paged_members as $member) {
                        $row_class = ($m_idx % 2 == 0) ? 'bg1' : 'bg2';
                        $m_name = htmlspecialchars($member['playername'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $cid = $member['channelid'];
                        $cname = isset($channels[$cid]['channelname']) ? htmlspecialchars($channels[$cid]['channelname'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'Unknown Channel';
                ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td style="text-align:center; vertical-align:middle;">
                            <?php if (!empty($member['is_cc'])): ?>
                                <img src="<?php echo IMAGE_PATH; ?>/teamspeak/ccommander.png" alt="CC" width="16" height="16" style="vertical-align:middle;" />
                            <?php else: ?>
                                <img src="<?php echo IMAGE_PATH; ?>/teamspeak/user.png" alt="" width="16" height="16" style="vertical-align:middle;" />
                            <?php endif; ?>
                        </td>
                        <td class="fHeading" style="vertical-align:middle;">
                            <?php echo $m_name; ?>
                            <?php if (!empty($member['is_cc'])): ?>
                                <span style="background:#f39c12; color:#ffffff; font-size:9px; font-weight:bold; padding:1px 4px; border-radius:3px; margin-left:3px; vertical-align:middle;">CC</span>
                            <?php endif; ?>
                            <?php echo getTSGameBadgeHtml($member['playername'], $hlx_games); ?>
                        </td>
                        <td style="vertical-align:middle;">
                            <img src="<?php echo IMAGE_PATH; ?>/teamspeak/channel.png" alt="" width="14" height="14" style="vertical-align:middle; margin-right:3px;" />
                            <?php echo $cname; ?>
                        </td>
                        <td style="vertical-align:middle;">
                            <?php if (!empty($member['is_away'])): ?>
                                <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#f39c12; margin-right:5px;"></span>
                                <span style="color:#e67e22; font-weight:bold;">Away <?php if (!empty($member['away_message'])): ?><em>(<?php echo htmlspecialchars($member['away_message'], ENT_QUOTES, 'UTF-8'); ?>)</em><?php endif; ?></span>
                            <?php else: ?>
                                <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#2ecc71; margin-right:5px;"></span>
                                <span style="color:#2ecc71; font-weight:bold;">Online</span>
                            <?php endif; ?>

                            <?php if (!empty($member['is_muted'])): ?>
                                <img src="<?php echo IMAGE_PATH; ?>/teamspeak/muted.png" alt="Muted" title="Microphone Muted" width="14" height="14" style="vertical-align:middle; margin-left:6px;" />
                            <?php endif; ?>

                            <?php if (!empty($member['is_deaf'])): ?>
                                <img src="<?php echo IMAGE_PATH; ?>/teamspeak/smuted.png" alt="Deafened" title="Sound Deactivated" width="14" height="14" style="vertical-align:middle; margin-left:4px;" />
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php
                        $m_idx++;
                    }
                } else {
                    echo '<tr class="bg1"><td colspan="4" style="text-align:center; padding:15px; color:#888;">No members currently online.</td></tr>';
                }
                ?>
            </table>

            <?php if ($total_pages > 1): ?>
                <div style="margin-top:12px; text-align:center; font-size:12px; font-weight:bold;">
                    <?php if ($current_page > 1): ?>
                        <a href="<?php echo $page_base_url . '&amp;dpage=' . ($current_page - 1); ?>#directory" style="margin-right:8px;">&laquo; Previous</a>
                    <?php endif; ?>

                    <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo $page_base_url . '&amp;dpage=' . ($current_page + 1); ?>#directory" style="margin-left:8px;">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
    </div>
</div>

<?php endif; ?>