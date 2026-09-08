<?php
/*
   HLstatsX Modernized Edition - Discord Detailed Server Viewer
   Version: 1.0
   Project: https://github.com/lovasatt/hlstatsx-community-edition
   Author:  lovasatt (2026)
*/

if (!defined('IN_HLSTATS')) {
    die('Do not access this file directly.');
}

global $db;

require_once(PAGE_PATH . '/discordstatus.php');

$dcId = isset($_GET['dcId']) ? (int)$_GET['dcId'] : 0;

if ($dcId <= 0) {
    pageHeader(array('Voice Server', 'Discord Viewer'), array('Voice Server' => '', 'Discord Viewer' => ''));
    echo "<div class=\"warning\"><span id=\"warning-header\"><strong>Error:</strong> Invalid Discord Server ID.</span></div><br />\n";
    pageFooter();
    die();
}

$result = $db->query("SELECT name, addr, password, descr FROM hlstats_Servers_VoiceComm WHERE serverId=$dcId LIMIT 1");
$s = false;
if ($result) {
    $s = $db->fetch_array($result);
    $db->free_result($result);
}

if (!$s) {
    pageHeader(array('Voice Server', 'Discord Viewer'), array('Voice Server' => '', 'Discord Viewer' => ''));
    echo "<div class=\"warning\"><span id=\"warning-header\"><strong>Error:</strong> Server not found.</span></div><br />\n";
    pageFooter();
    die();
}

pageHeader(
    array('Voice Server', 'Discord Viewer'),
    array('Voice Server' => '', 'Discord Viewer' => '')
);

$guild_id = (string)$s['addr'];
$fallback_invite = (string)$s['password'];

$dc = new CDiscordStatus;
$request_success = $dc->Request($guild_id, $fallback_invite);

$raw_server_name = trim((string)$s['name']);
$display_server_name = ($raw_server_name !== '') ? $raw_server_name : (!empty($dc->m_name) ? $dc->m_name : 'Discord Server');
$safe_name   = htmlspecialchars($display_server_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safe_descr  = htmlspecialchars($s['descr'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safe_invite = htmlspecialchars($dc->m_invite, ENT_QUOTES, 'UTF-8');

$is_online = ($request_success === true && empty($dc->m_error));

// Fetch supported games from HLstatsX for automatic icon resolution
$hlx_games = [];
$g_res = $db->query("SELECT code, name FROM hlstats_Games");
if ($g_res) {
    while ($g_row = $db->fetch_array($g_res)) {
        $hlx_games[strtolower(trim($g_row['name']))] = strtolower(trim($g_row['code']));
    }
    $db->free_result($g_res);
}

// Render game badge: local HLstatsX game icon if matched, otherwise fallback controller emoji
if (!function_exists('getGameBadgeHtml')) {
    function getGameBadgeHtml($activity_name, $hlx_games) {
        $act = trim((string)$activity_name);
        if ($act === '') {
            return '<span style="color:#888;">&mdash;</span>';
        }

        $act_safe = htmlspecialchars($act, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $act_lower = strtolower($act);
        $matched_code = null;

        foreach ($hlx_games as $g_name => $g_code) {
            if ($g_name !== '' && (strpos($act_lower, $g_name) !== false || strpos($g_name, $act_lower) !== false || $act_lower === $g_code)) {
                $matched_code = $g_code;
                break;
            }
        }

        // Common fallback aliases
        if (!$matched_code) {
            if (strpos($act_lower, 'counter-strike 2') !== false || $act_lower === 'cs2') {
                $matched_code = 'cs2';
            } elseif (strpos($act_lower, 'team fortress 2') !== false || $act_lower === 'tf2') {
                $matched_code = 'tf';
            } elseif (strpos($act_lower, 'counter-strike: source') !== false || $act_lower === 'css') {
                $matched_code = 'css';
            }
        }

        // Resolve local HLstatsX icon
        $icon_url = '';
        if ($matched_code && function_exists('getImage')) {
            $img_info = getImage("/games/{$matched_code}/game");
            if (!empty($img_info['url'])) {
                $icon_url = (string)$img_info['url'];
            }
        }

        $text_span = '<span style="max-width:140px; display:inline-block; vertical-align:middle; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="' . $act_safe . '">' . $act_safe . '</span>';

        // If game icon exists, display ONLY the icon with a hover tooltip (saves space)
        if (!empty($icon_url)) {
            return '<img src="' . htmlspecialchars($icon_url, ENT_QUOTES, 'UTF-8') . '" alt="' . $act_safe . '" title="' . $act_safe . '" width="18" height="18" loading="lazy" style="vertical-align:middle; margin-left:3px; cursor:help;" />';
        }
        // Fallback for unknown games without an icon: show controller + game name
        return '<span style="vertical-align:middle;">🎮</span> ' . $text_span;
    }
}
?>

<!-- Section: Server Information -->
<div class="block">
    <?php printSectionTitle('Discord Server Information'); ?>
    <div class="subblock">
        <table class="data-table" style="width:100%;">
          <tr class="bg1">
              <td style="width:20%; font-weight:bold;">Community:</td>
              <td><?php echo $safe_name; ?> <?php if (!empty($dc->m_name) && strcasecmp($safe_name, $dc->m_name) !== 0): ?>&mdash; <em><?php echo htmlspecialchars($dc->m_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></em><?php endif; ?></td>
          </tr>
          <?php if (!empty($safe_descr)): ?>
          <tr class="bg2">
              <td style="font-weight:bold;">Description:</td>
              <td><?php echo $safe_descr; ?></td>
          </tr>
          <?php endif; ?>
          <tr class="<?php echo !empty($safe_descr) ? 'bg1' : 'bg2'; ?>">
              <td style="font-weight:bold;">Active Presence:</td>
                <td>
                    <?php if ($is_online): ?>
                        <span style="color:#2ecc71; font-weight:bold;">🟢 <?php echo (int)$dc->m_online; ?> Users Online</span>
                    <?php else: ?>
                        <span style="color:#e74c3c; font-weight:bold;">🔴 Offline / Unavailable</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr class="bg1">
                <td style="font-weight:bold;">Voice Rooms:</td>
                <td><?php echo (int)$dc->m_channels; ?> registered voice channels</td>
            </tr>
            <tr class="bg2">
                <td style="font-weight:bold;">Join Server:</td>
                <td>
                    <?php if (!empty($safe_invite)): ?>
                        <a href="<?php echo $safe_invite; ?>" target="_blank" rel="noopener noreferrer" style="font-weight:bold; color:#5865F2; text-decoration:none;">
                            🚀 Launch Discord &amp; Connect
                        </a>
                    <?php else: ?>
                        <span style="color:#888; font-style:italic;">🔒 Private Community &mdash; Invitation required</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<br /><br />

<?php if (!$request_success): ?>
    <br />
    <div class="block">
        <?php printSectionTitle('Discord Server Status'); ?>
        <div class="subblock">
            <p style="color:#e74c3c; padding:15px; font-weight:bold; text-align:center; margin:0;">
                ⚠️ <?php echo htmlspecialchars($dc->m_error ?: 'Failed to communicate with Discord API.', ENT_QUOTES, 'UTF-8'); ?>
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
            if (!empty($dc->m_voice_tree)) {
                $ch_idx = 0;
                foreach ($dc->m_voice_tree as $channel) {
                    $row_class = ($ch_idx % 2 == 0) ? 'bg1' : 'bg2';
                    $ch_name = htmlspecialchars($channel['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $users = $channel['users'];
                    $user_count = count($users);
            ?>
                <tr class="<?php echo $row_class; ?>">
                    <td class="fHeading" style="vertical-align:middle;">
                        <?php $ch_icon = ($user_count > 0) ? 'channel_active.png' : 'channel.png'; ?>
                        <img src="<?php echo IMAGE_PATH; ?>/discord/<?php echo $ch_icon; ?>" alt="" width="16" height="16" style="vertical-align:middle; margin-right:5px;" />
                        <?php echo $ch_name; ?>
                    </td>
                    <td style="text-align:center; vertical-align:middle; font-weight:bold;">
                        <?php echo $user_count > 0 ? "<span style='color:#2ecc71;'>$user_count in room</span>" : "<span style='color:#888;'>Empty</span>"; ?>
                    </td>
                    <td style="vertical-align:middle;">
                        <?php if ($user_count > 0): ?>
                            <?php foreach ($users as $u):
                                $uname = htmlspecialchars($u['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $u_avatar = htmlspecialchars($u['avatar'], ENT_QUOTES, 'UTF-8');
                                $u_act = $u['activity'];
                            ?>
                                <span style="display:inline-block; background:rgba(0,0,0,0.12); border-radius:12px; padding:3px 8px; margin:2px 4px 2px 0; vertical-align:middle;">
                                    <?php if (!empty($u_avatar) && preg_match('/^https?:\/\//i', $u_avatar)): ?>
                                        <img src="<?php echo $u_avatar; ?>" alt="" width="16" height="16" loading="lazy" style="border-radius:50%; vertical-align:middle; margin-right:4px;" />
                                    <?php else: ?>
                                        <img src="<?php echo IMAGE_PATH; ?>/discord/discord.png" alt="" width="16" height="16" loading="lazy" style="border-radius:50%; vertical-align:middle; margin-right:4px;" />
                                    <?php endif; ?>
                                    <strong style="max-width:130px; display:inline-block; vertical-align:middle; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo $uname; ?>"><?php echo $uname; ?></strong>
                                    <?php if (!empty($u['is_bot'])): ?>
                                        <span style="background:#5865F2; color:#ffffff; font-size:9px; font-weight:bold; padding:1px 4px; border-radius:3px; margin-left:3px; vertical-align:middle;">BOT</span>
                                    <?php endif; ?>
                                    <?php if (!empty($u_act)): ?>
                                        <span style="font-size:11px; color:#3498db; margin-left:4px; font-weight:bold; vertical-align:middle;"><?php echo getGameBadgeHtml($u_act, $hlx_games); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($u['is_muted'])): ?>
                                        <img src="<?php echo IMAGE_PATH; ?>/discord/muted.png" alt="Muted" title="Microphone Muted" width="14" height="14" style="vertical-align:middle; margin-left:4px;" />
                                    <?php endif; ?>
                                    <?php if (!empty($u['is_deaf'])): ?>
                                        <img src="<?php echo IMAGE_PATH; ?>/discord/deafened.png" alt="Deafened" title="Deafened (Audio &amp; Mic Muted)" width="14" height="14" style="vertical-align:middle; margin-left:4px;" />
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
                echo '<tr class="bg1"><td colspan="3" style="text-align:center; padding:15px; color:#888;">No voice channels found or Discord widget is disabled.</td></tr>';
            }
            ?>
        </table>
    </div>
</div>

<br /><br />

<!-- Section: Online Directory -->
<a id="directory"></a>
<div class="block">
    <?php
        $total_dir_members = count($dc->m_directory);
        printSectionTitle('Online Members Directory' . ($total_dir_members > 0 ? " ($total_dir_members)" : ''));
    ?>
    <div class="subblock">
        <?php
            // Pagination & Per-page logic
            $allowed_per_page = [10, 20, 50, 100];
            $per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $allowed_per_page, true)
                        ? (int)$_GET['per_page']
                        : 20;

            $total_pages = max(1, (int)ceil($total_dir_members / $per_page));
            $current_page = isset($_GET['dpage']) ? max(1, min($total_pages, (int)$_GET['dpage'])) : 1;
            $offset = ($current_page - 1) * $per_page;
            $paged_members = array_slice($dc->m_directory, $offset, $per_page);

            global $game;
            $url_game = urlencode((string)($game ?? ''));
            $game_param = ($url_game !== '') ? "&amp;game={$url_game}" : '';

            $page_base_url = "hlstats.php?mode=discord{$game_param}&amp;dcId={$dcId}&amp;per_page={$per_page}";
        ?>
            <!-- Per-page selector dropdown -->
            <?php if ($total_dir_members > 10): ?>
            <div style="margin-bottom: 10px; text-align: right; font-size: 11px;">
                <form method="get" action="hlstats.php" style="display:inline; margin:0;">
                  <input type="hidden" name="mode" value="discord" />
                  <?php if (!empty($game)): ?>
                  <input type="hidden" name="game" value="<?php echo htmlspecialchars($game, ENT_QUOTES, 'UTF-8'); ?>" />
                  <?php endif; ?>
                  <input type="hidden" name="dcId" value="<?php echo $dcId; ?>" />
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
                    <td class="fSmall" style="width:30%;">Username</td>
                    <td class="fSmall" style="width:20%;">Status</td>
                    <td class="fSmall">Current Activity</td>
                </tr>
                <?php
                if (!empty($paged_members)) {
                    $m_idx = 0;
                    foreach ($paged_members as $member) {
                        $row_class = ($m_idx % 2 == 0) ? 'bg1' : 'bg2';
                        $m_name = htmlspecialchars($member['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $m_status = $member['status'];
                        $m_avatar = htmlspecialchars($member['avatar'], ENT_QUOTES, 'UTF-8');
                        $m_act = (string)$member['activity'];

                        $badge = 'Online';
                        $dot = '#2ecc71';
                        if ($m_status === 'idle') {
                            $badge = 'Away / Idle';
                            $dot = '#f39c12';
                        } elseif ($m_status === 'dnd') {
                            $badge = 'Do Not Disturb';
                            $dot = '#e74c3c';
                        } elseif ($m_status === 'offline' || $m_status === 'invisible') {
                            $badge = 'Offline';
                            $dot = '#747f8d';
                        }
                ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td style="text-align:center; vertical-align:middle;">
                            <?php if (!empty($m_avatar) && preg_match('/^https?:\/\//i', $m_avatar)): ?>
                                <img src="<?php echo $m_avatar; ?>" alt="" width="22" height="22" loading="lazy" style="border-radius:50%; vertical-align:middle;" />
                            <?php else: ?>
                                <img src="<?php echo IMAGE_PATH; ?>/discord/discord.png" alt="" width="22" height="22" loading="lazy" style="border-radius:50%; vertical-align:middle;" />
                            <?php endif; ?>
                        </td>
                        <td class="fHeading" style="vertical-align:middle;">
                            <?php echo $m_name; ?>
                            <?php if (!empty($member['is_bot'])): ?>
                                <span style="background:#5865F2; color:#ffffff; font-size:9px; font-weight:bold; padding:1px 4px; border-radius:3px; margin-left:4px; vertical-align:middle;">BOT</span>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align:middle;">
                            <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:<?php echo $dot; ?>; margin-right:5px;"></span>
                            <?php echo $badge; ?>
                        </td>
                        <td style="vertical-align:middle;">
                            <?php if (!empty($m_act)): ?>
                                <span style="color:#3498db; font-weight:bold;"><?php echo getGameBadgeHtml($m_act, $hlx_games); ?></span>
                            <?php else: ?>
                                <span style="color:#888;">&mdash;</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php
                        $m_idx++;
                    }
                } else {
                    echo '<tr class="bg1"><td colspan="4" style="text-align:center; padding:15px; color:#888;">No members currently visible via widget.</td></tr>';
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

        <?php endif; ?>
    </div>
</div>