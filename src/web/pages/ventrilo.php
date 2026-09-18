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

require_once(PAGE_PATH . '/ventrilostatus.php');

$veId = isset($_GET['veId']) ? (int)$_GET['veId'] : 0;

if ($veId <= 0) {
    pageHeader(array('Voice Server', 'Ventrilo Viewer'), array('Voice Server' => '', 'Ventrilo Viewer' => ''));
    echo "<div class=\"warning\"><span id=\"warning-header\"><strong>Error:</strong> Invalid Ventrilo Server ID.</span></div><br />\n";
    pageFooter();
    die();
}

$result = $db->query("SELECT addr, queryPort, UDPPort, name, password, descr FROM hlstats_Servers_VoiceComm WHERE serverId=$veId LIMIT 1");
$s = false;
if ($result) {
    $s = $db->fetch_array($result);
    $db->free_result($result);
}

if (!$s) {
    pageHeader(array('Voice Server', 'Ventrilo Viewer'), array('Voice Server' => '', 'Ventrilo Viewer' => ''));
    echo "<div class=\"warning\"><span id=\"warning-header\"><strong>Error:</strong> Server not found.</span></div><br />\n";
    pageFooter();
    die();
}

pageHeader(
    array('Voice Server', 'Ventrilo Viewer'),
    array('Voice Server' => '', 'Ventrilo Viewer' => '')
);

$uip   = trim((string)$s['addr']);
$qPort = (int)$s['queryPort'];
$uPort = (int)$s['UDPPort'];
$port  = ($qPort > 0) ? $qPort : (($uPort > 0) ? $uPort : 3784);
$raw_pass = trim((string)($s['password'] ?? ''));

// Initialize Ventrilo status query with 90s cache
$stat = new CVentriloStatus;
$stat->m_cmdcode = 2; // Detailed status
$stat->m_cmdhost = $uip;
$stat->m_cmdport = $port;
$stat->m_cmdpass = $raw_pass;
$stat->RequestCached(90);

$is_online = (empty($stat->m_error) && !empty($stat->m_name));

// --- VISUAL TEST MODE (Set to true to preview design without a running Ventrilo server) ---
$test_mode = false;

if ($test_mode) {
    $is_online = true;
    $stat->m_error            = null;
    $stat->m_name             = 'HLstatsX Ventrilo Master Server';
    $stat->m_platform         = 'Linux x64';
    $stat->m_maxclients       = 32;
    $stat->m_uptime           = 172800; // 2 days
    $stat->m_version          = '3.0.8';
    $stat->m_voicecodec_desc  = 'Speex';
    $stat->m_voiceformat_desc = '32 KHz, 16 bit, 10 Q';
    $stat->m_channelcount     = 4;
    $stat->m_clientcount      = 12;

    $c1 = new CVentriloChannel; $c1->m_cid = 1; $c1->m_pid = 0; $c1->m_prot = '0'; $c1->m_name = 'Lobby / Welcome Room'; $c1->m_comm = 'Public chat lounge';
    $c2 = new CVentriloChannel; $c2->m_cid = 2; $c2->m_pid = 0; $c2->m_prot = '0'; $c2->m_name = 'Counter-Strike 2 Ranked'; $c2->m_comm = 'Competitive 5v5 team channel';
    $c3 = new CVentriloChannel; $c3->m_cid = 3; $c3->m_pid = 0; $c3->m_prot = '0'; $c3->m_name = 'Team Fortress 2 Casual'; $c3->m_comm = 'Payload and defense games';
    $c4 = new CVentriloChannel; $c4->m_cid = 4; $c4->m_pid = 0; $c4->m_prot = '1'; $c4->m_name = 'Staff / Private Meeting'; $c4->m_comm = 'Password protected channel';
    $stat->m_channellist = [$c1, $c2, $c3, $c4];

    $u1  = new CVentriloClient; $u1->m_uid  = 1;  $u1->m_cid  = 1; $u1->m_name  = '[TEST]AdminTom [CS2]'; $u1->m_admin = '1'; $u1->m_phan = '0'; $u1->m_ping = 15; $u1->m_sec = 7200;  $u1->m_comm = 'Server Admin';
    $u2  = new CVentriloClient; $u2->m_uid  = 2;  $u2->m_cid  = 2; $u2->m_name  = 'ProGamer_CS2'; $u2->m_admin = '0'; $u2->m_phan = '0'; $u2->m_ping = 22; $u2->m_sec = 3600;  $u2->m_comm = 'Entry Fragger';
    $u3  = new CVentriloClient; $u3->m_uid  = 3;  $u3->m_cid  = 2; $u3->m_name  = 'AwpSniper | CSS'; $u3->m_admin = '0'; $u3->m_phan = '0'; $u3->m_ping = 35; $u3->m_sec = 1800;  $u3->m_comm = 'AWP Main';
    $u4  = new CVentriloClient; $u4->m_uid  = 4;  $u4->m_cid  = 3; $u4->m_name  = 'HeavyGuy (TF2)'; $u4->m_admin = '0'; $u4->m_phan = '0'; $u4->m_ping = 40; $u4->m_sec = 5400;  $u4->m_comm = '';
    $u5  = new CVentriloClient; $u5->m_uid  = 5;  $u5->m_cid  = 4; $u5->m_name  = 'SilentWatcher'; $u5->m_admin = '0'; $u5->m_phan = '1'; $u5->m_ping = 18; $u5->m_sec = 10800; $u5->m_comm = 'Listening only';
    $u6  = new CVentriloClient; $u6->m_uid  = 6;  $u6->m_cid  = 1; $u6->m_name  = 'Medic_TF2'; $u6->m_admin = '0'; $u6->m_phan = '0'; $u6->m_ping = 28; $u6->m_sec = 900;   $u6->m_comm = '';
    $u7  = new CVentriloClient; $u7->m_uid  = 7;  $u7->m_cid  = 2; $u7->m_name  = 'ClutchKing [CS2]'; $u7->m_admin = '0'; $u7->m_phan = '0'; $u7->m_ping = 12; $u7->m_sec = 4200;  $u7->m_comm = 'IGL';
    $u8  = new CVentriloClient; $u8->m_uid  = 8;  $u8->m_cid  = 2; $u8->m_name  = '[TEST]SupportGuy_CS2'; $u8->m_admin = '0'; $u8->m_phan = '0'; $u8->m_ping = 25; $u8->m_sec = 1200;  $u8->m_comm = '';
    $u9  = new CVentriloClient; $u9->m_uid  = 9;  $u9->m_cid  = 3; $u9->m_name  = 'SpyMaster (TF2)'; $u9->m_admin = '0'; $u9->m_phan = '0'; $u9->m_ping = 31; $u9->m_sec = 2700;  $u9->m_comm = '';
    $u10 = new CVentriloClient; $u10->m_uid = 10; $u10->m_cid = 1; $u10->m_name = 'CasualGuest'; $u10->m_admin = '0'; $u10->m_phan = '0'; $u10->m_ping = 55; $u10->m_sec = 600;   $u10->m_comm = '';
    $u11 = new CVentriloClient; $u11->m_uid = 11; $u11->m_cid = 1; $u11->m_name = 'OldSchool - CSS'; $u11->m_admin = '0'; $u11->m_phan = '0'; $u11->m_ping = 19; $u11->m_sec = 3100;  $u11->m_comm = '';
    $u12 = new CVentriloClient; $u12->m_uid = 12; $u12->m_cid = 4; $u12->m_name = 'CoAdmin [CS2]'; $u12->m_admin = '1'; $u12->m_phan = '0'; $u12->m_ping = 14; $u12->m_sec = 8400;  $u12->m_comm = 'Staff Member';
    $stat->m_clientlist = [$u1, $u2, $u3, $u4, $u5, $u6, $u7, $u8, $u9, $u10, $u11, $u12];
}
// --- VISUAL TEST MODE END ---

$raw_server_name = trim((string)$s['name']);
$display_server_name = ($raw_server_name !== '') ? $raw_server_name : ($stat->m_name ?: 'Ventrilo Server');
$safe_name   = htmlspecialchars($display_server_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safe_descr  = htmlspecialchars((string)($s['descr'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// 1-Click Launch URL for Ventrilo Client (Clean: does not leak password)
$join_url = "ventrilo://" . urlencode($uip) . ":" . $port . "/servername=" . urlencode($display_server_name);

// Fetch supported games from HLstatsX for automatic icon resolution
$hlx_games = [];
$g_res = $db->query("SELECT code, name FROM hlstats_Games");
if ($g_res) {
    while ($g_row = $db->fetch_array($g_res)) {
        $hlx_games[strtolower(trim($g_row['name']))] = strtolower(trim($g_row['code']));
    }
    $db->free_result($g_res);
}

if (!function_exists('getVeGameBadgeHtml')) {
    function getVeGameBadgeHtml(string $activity_name, array $hlx_games): string {
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

if (!function_exists('format_ve_uptime')) {
    function format_ve_uptime(int $seconds): string {
        $d = floor($seconds / 86400);
        $h = floor(($seconds % 86400) / 3600);
        $m = floor(($seconds % 3600) / 60);
        if ($d > 0) return "{$d}d {$h}h {$m}m";
        if ($h > 0) return "{$h}h {$m}m";
        return "{$m}m";
    }
}

$channels = $stat->m_channellist ?? [];
$clients  = $stat->m_clientlist ?? [];
?>

<!-- Section: Server Information -->
<div class="block">
    <?php printSectionTitle('Ventrilo Server Information'); ?>
    <div class="subblock">
        <table class="data-table" style="width:100%;">
            <?php $info_idx = 0; ?>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="width:20%; font-weight:bold;">Community:</td>
                <td><?php echo $safe_name; ?> <?php if (!empty($stat->m_name) && strcasecmp($safe_name, $stat->m_name) !== 0): ?>&mdash; <em><?php echo htmlspecialchars($stat->m_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></em><?php endif; ?></td>
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
                <td style="font-weight:bold;">Password:</td>
                <td><?php echo !empty($raw_pass) ? '<span style="color:#f39c12; font-weight:bold;">Protected</span>' : '<span style="color:#888;">None</span>'; ?></td>
            </tr>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Active Presence:</td>
                <td>
                    <?php if ($is_online): ?>
                        <span style="color:#2ecc71; font-weight:bold;">🟢 <?php echo count($clients); ?> / <?php echo (int)($stat->m_maxclients ?? 32); ?> Users Online</span>
                        <span style="color:#888; margin-left:10px; font-size:11px;">(Uptime: <?php echo format_ve_uptime((int)($stat->m_uptime ?? 0)); ?>, Platform: <?php echo htmlspecialchars($stat->m_platform ?: 'Linux', ENT_QUOTES, 'UTF-8'); ?>, Version: <?php echo htmlspecialchars($stat->m_version ?: '3.x', ENT_QUOTES, 'UTF-8'); ?>)</span>
                    <?php else: ?>
                        <span style="color:#e74c3c; font-weight:bold;">🔴 Offline / Unavailable</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Voice Rooms:</td>
                <td><?php echo count($channels); ?> registered channels <?php if (!empty($stat->m_voicecodec_desc)): ?><span style="color:#888; font-size:11px;">(Codec: <?php echo htmlspecialchars($stat->m_voicecodec_desc, ENT_QUOTES, 'UTF-8'); ?>)</span><?php endif; ?></td>
            </tr>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Join Server:</td>
                <td>
                    <?php if ($is_online): ?>
                        <a href="<?php echo $join_url; ?>" style="font-weight:bold; color:#2575fc; text-decoration:none;">
                            🚀 Launch Ventrilo &amp; Connect
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
        <?php printSectionTitle('Ventrilo Server Status'); ?>
        <div class="subblock">
            <p style="color:#e74c3c; padding:15px; font-weight:bold; text-align:center; margin:0;">
                ⚠️ <?php echo htmlspecialchars($stat->m_error ?: 'Could not establish UDP connection to Ventrilo server.', ENT_QUOTES, 'UTF-8'); ?> (Port: <?php echo $port; ?>)
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
            if (!empty($channels)) {
                $ch_idx = 0;
                foreach ($channels as $ch) {
                    $row_class = ($ch_idx % 2 == 0) ? 'bg1' : 'bg2';
                    $cid = $ch->m_cid;
                    $ch_name = htmlspecialchars((string)$ch->m_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                    $channel_users = array_filter($clients, function($cl) use ($cid) {
                        return (int)$cl->m_cid === (int)$cid;
                    });
                    $user_count = count($channel_users);
            ?>
                <tr class="<?php echo $row_class; ?>">
                    <td class="fHeading" style="vertical-align:middle;">
                        <img src="<?php echo IMAGE_PATH; ?>/ventrilo/channel.png" alt="" width="16" height="16" style="vertical-align:middle; margin-right:5px;" />
                        <?php echo $ch_name; ?>
                        <?php if (!empty($ch->m_prot)): ?>
                            <span title="Password Protected Channel" style="font-size:11px; cursor:help; margin-left:3px;">🔒</span>
                        <?php endif; ?>
                        <?php if (!empty($ch->m_comm)): ?>
                            <div style="font-size:10px; color:#888; font-weight:normal; margin-left:21px;"><?php echo htmlspecialchars((string)$ch->m_comm, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center; vertical-align:middle; font-weight:bold;">
                        <?php echo $user_count > 0 ? "<span style='color:#2ecc71;'>$user_count in room</span>" : "<span style='color:#888;'>Empty</span>"; ?>
                    </td>
                    <td style="vertical-align:middle;">
                        <?php if ($user_count > 0): ?>
                            <?php foreach ($channel_users as $u):
                                $uname = htmlspecialchars((string)$u->m_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $u_game_badge = getVeGameBadgeHtml((string)$u->m_name, $hlx_games);
                            ?>
                                <span style="display:inline-block; background:rgba(0,0,0,0.12); border-radius:12px; padding:3px 8px; margin:2px 4px 2px 0; vertical-align:middle;">
                                    <img src="<?php echo IMAGE_PATH; ?>/ventrilo/user.png" alt="" width="14" height="14" style="vertical-align:middle; margin-right:4px;" />

                                    <strong style="max-width:130px; display:inline-block; vertical-align:middle; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo $uname; ?>"><?php echo $uname; ?></strong>

                                    <?php if (!empty($u->m_admin)): ?>
                                        <span style="background:#e74c3c; color:#ffffff; font-size:9px; font-weight:bold; padding:1px 4px; border-radius:3px; margin-left:3px; vertical-align:middle;" title="Server Admin">ADMIN</span>
                                    <?php endif; ?>

                                    <?php if (!empty($u->m_phan)): ?>
                                        <span style="background:#9b59b6; color:#ffffff; font-size:9px; font-weight:bold; padding:1px 4px; border-radius:3px; margin-left:3px; vertical-align:middle;" title="Phantom">PHAN</span>
                                    <?php endif; ?>

                                    <?php if (!empty($u_game_badge)): ?>
                                        <?php echo $u_game_badge; ?>
                                    <?php endif; ?>

                                    <?php if (!empty($u->m_comm)): ?>
                                        <span style="font-size:10px; color:#888; margin-left:4px;">(<?php echo htmlspecialchars((string)$u->m_comm, ENT_QUOTES, 'UTF-8'); ?>)</span>
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
        $total_dir_members = count($clients);
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
            $paged_members = array_slice($clients, $offset, $per_page);

            global $game;
            $url_game = urlencode((string)($game ?? ''));
            $game_param = ($url_game !== '') ? "&amp;game={$url_game}" : '';

            $page_base_url = "hlstats.php?mode=ventrilo{$game_param}&amp;veId={$veId}&amp;per_page={$per_page}";
        ?>
            <?php if ($total_dir_members > 10): ?>
            <div style="margin-bottom: 10px; text-align: right; font-size: 11px;">
                <form method="get" action="hlstats.php" style="display:inline; margin:0;">
                    <input type="hidden" name="mode" value="ventrilo" />
                    <?php if (!empty($game)): ?>
                    <input type="hidden" name="game" value="<?php echo htmlspecialchars((string)$game, ENT_QUOTES, 'UTF-8'); ?>" />
                    <?php endif; ?>
                    <input type="hidden" name="veId" value="<?php echo $veId; ?>" />
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
                    <td class="fSmall" style="width:15%;">Online Time</td>
                    <td class="fSmall">Status &amp; Flags</td>
                </tr>
                <?php
                if (!empty($paged_members)) {
                    $m_idx = 0;
                    foreach ($paged_members as $member) {
                        $row_class = ($m_idx % 2 == 0) ? 'bg1' : 'bg2';
                        $m_name = htmlspecialchars((string)$member->m_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $cid = (int)$member->m_cid;
                        $cobj = $stat->ChannelFind($cid);
                        $cname = ($cobj && !empty($cobj->m_name)) ? htmlspecialchars((string)$cobj->m_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'Lobby';
                ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td style="text-align:center; vertical-align:middle;">
                            <img src="<?php echo IMAGE_PATH; ?>/ventrilo/user.png" alt="" width="14" height="14" style="vertical-align:middle;" />
                        </td>
                        <td class="fHeading" style="vertical-align:middle;">
                            <?php echo $m_name; ?>
                            <?php if (!empty($member->m_admin)): ?>
                                <span style="background:#e74c3c; color:#ffffff; font-size:9px; font-weight:bold; padding:1px 4px; border-radius:3px; margin-left:3px; vertical-align:middle;" title="Server Admin">ADMIN</span>
                            <?php endif; ?>
                            <?php if (!empty($member->m_phan)): ?>
                                <span style="background:#9b59b6; color:#ffffff; font-size:9px; font-weight:bold; padding:1px 4px; border-radius:3px; margin-left:3px; vertical-align:middle;" title="Phantom">PHAN</span>
                            <?php endif; ?>
                            <?php echo getVeGameBadgeHtml((string)$member->m_name, $hlx_games); ?>
                        </td>
                        <td style="vertical-align:middle;">
                            <img src="<?php echo IMAGE_PATH; ?>/ventrilo/channel.png" alt="" width="14" height="14" style="vertical-align:middle; margin-right:3px;" />
                            <?php echo $cname; ?>
                        </td>
                        <td style="vertical-align:middle; font-size:11px;">
                            <?php echo format_ve_uptime((int)($member->m_sec ?? 0)); ?>
                        </td>
                        <td style="vertical-align:middle;">
                            <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#2ecc71; margin-right:5px;"></span>
                            <span style="color:#2ecc71; font-weight:bold;">Online</span>
                            <?php if (!empty($member->m_ping)): ?>
                                <span style="color:#888; font-size:10px; margin-left:6px;">(Ping: <?php echo (int)$member->m_ping; ?>ms)</span>
                            <?php endif; ?>
                            <?php if (!empty($member->m_comm)): ?>
                                <span style="font-size:11px; color:#888; margin-left:6px; font-style:italic;">"<?php echo htmlspecialchars((string)$member->m_comm, ENT_QUOTES, 'UTF-8'); ?>"</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php
                        $m_idx++;
                    }
                } else {
                    echo '<tr class="bg1"><td colspan="5" style="text-align:center; padding:15px; color:#888;">No members currently online.</td></tr>';
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