<?php
/*
   HLstatsX Modernized Edition - Steam Group Community Viewer
   Version: 1.0 (Maximized Visuals, Announcements Pagination & Game Badges)
   Author:  lovasatt (2026)
*/

if (!defined('IN_HLSTATS')) {
    die('Do not access this file directly.');
}

global $db, $game;

require_once(PAGE_PATH . '/steamstatus.php');

$stId = isset($_GET['stId']) ? (int)$_GET['stId'] : 0;

if ($stId <= 0) {
    pageHeader(array('Community', 'Steam Group Viewer'), array('Community' => '', 'Steam Group Viewer' => ''));
    echo "<div class=\"warning\"><span id=\"warning-header\"><strong>Error:</strong> Invalid Steam Group ID.</span></div><br />\n";
    pageFooter();
    die();
}

$result = $db->query("SELECT name, addr, descr FROM hlstats_Servers_VoiceComm WHERE serverId=$stId AND serverType=3 LIMIT 1");
$s = false;
if ($result) {
    $s = $db->fetch_array($result);
    $db->free_result($result);
}

if (!$s) {
    pageHeader(array('Community', 'Steam Group Viewer'), array('Community' => '', 'Steam Group Viewer' => ''));
    echo "<div class=\"warning\"><span id=\"warning-header\"><strong>Error:</strong> Steam Group not found in database.</span></div><br />\n";
    pageFooter();
    die();
}

pageHeader(
    array('Community', 'Steam Group Viewer'),
    array('Community' => '', 'Steam Group Viewer' => '')
);

$group_ident = (string)$s['addr'];
$sg = new CSteamGroupStatus;
$request_success = $sg->Request($group_ident);

// Fetch supported games from HLstatsX for automatic icon resolution in announcements
$hlx_games = [];
$g_res = $db->query("SELECT code, name FROM hlstats_Games");
if ($g_res) {
    while ($g_row = $db->fetch_array($g_res)) {
        $hlx_games[strtolower(trim($g_row['name']))] = strtolower(trim($g_row['code']));
    }
    $db->free_result($g_res);
}

// Function to render local HLstatsX game icon badge helper
if (!function_exists('renderSteamGameBadgeImg')) {
    function renderSteamGameBadgeImg($code) {
        if (function_exists('getImage')) {
            $img_info = getImage("/games/{$code}/game");
            if (!empty($img_info['url'])) {
                $safe_code = htmlspecialchars(strtoupper((string)$code), ENT_QUOTES, 'UTF-8');
                return '<img src="' . htmlspecialchars((string)$img_info['url'], ENT_QUOTES, 'UTF-8') . '" alt="' . $safe_code . '" title="' . $safe_code . '" width="16" height="16" loading="lazy" style="vertical-align:middle; margin-right:5px; cursor:help;" />';
            }
        }
        return '';
    }
}

// 100% Precision Game Badge Resolver (TeamSpeak principle: Start, End, or Delimited tags)
if (!function_exists('getSteamNewsBadgeHtml')) {
    function getSteamNewsBadgeHtml($title, $hlx_games) {
        $t = trim((string)$title);
        if ($t === '') return '';

        // 1. High-priority explicit aliases: strictly at the START (^), at the END ($), or DELIMITED ([], (), -, |, :)
        $priority_aliases = [
            'cs2'  => '/(?:^|[\[\(\s\-_|])(cs2|cs-2|counter-strike\s*2)(?:$|[\]\)\s\-_:])/i',
            'tf'   => '/(?:^|[\[\(\s\-_|])(tf2|team\s*fortress\s*2)(?:$|[\]\)\s\-_:])/i',
            'css'  => '/(?:^|[\[\(\s\-_|])(css|counter-strike:\s*source)(?:$|[\]\)\s\-_:])/i',
            'l4d2' => '/(?:^|[\[\(\s\-_|])(l4d2|left\s*4\s*dead\s*2)(?:$|[\]\)\s\-_:])/i',
            'dods' => '/(?:^|[\[\(\s\-_|])(dods|day\s*of\s*defeat:\s*source)(?:$|[\]\)\s\-_:])/i'
        ];

        foreach ($priority_aliases as $code => $regex) {
            if (preg_match($regex, $t)) {
                return renderSteamGameBadgeImg($code);
            }
        }

        // 2. Sort all 29+ games by name length descending
        uksort($hlx_games, function($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        // 3. Match 29+ game titles/codes strictly at Start, End, or Delimited
        foreach ($hlx_games as $g_name => $g_code) {
            if ($g_name === '' || $g_code === '') continue;

            $name_pattern = '/(?:^|[\[\(\s\-_|])' . preg_quote($g_name, '/') . '(?:$|[\]\)\s\-_:])/i';
            if (preg_match($name_pattern, $t)) {
                return renderSteamGameBadgeImg($g_code);
            }

            $code_pattern = '/(?:^|[\[\(\s\-_|])' . preg_quote($g_code, '/') . '(?:$|[\]\)\s\-_:])/i';
            if (preg_match($code_pattern, $t)) {
                return renderSteamGameBadgeImg($g_code);
            }
        }

        return '';
    }
}

// Humanized relative time formatting ("2 hours ago", "Yesterday", etc.)
if (!function_exists('format_steam_time_ago')) {
    function format_steam_time_ago($time_str) {
        $ts = strtotime((string)$time_str);
        if (!$ts || $ts <= 0) return 'Recent';
        $diff = time() - $ts;

        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 172800) return 'Yesterday';
        if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
        return date('M d, Y', $ts);
    }
}

// --- VISUAL TEST MODE (Set to true to preview design with comprehensive mock data) ---
$test_mode = false;

if ($test_mode) {
    $request_success = true;
    $sg->m_error            = '';
    $sg->m_name             = 'Royal Multi Gamers | HLstatsX Master Hub';
    $sg->m_group_id64       = '103582791430602394';
    $sg->m_group_url        = 'clan-rmg';
    $sg->m_headline         = 'Official CS2, TF2, Classic Source & Tactical Network';
    $sg->m_summary          = 'Welcome to the premier multi-gaming community! Daily competitive matches, automated statistics tracking, and weekly tournaments across all Source engine titles.';
    $sg->m_avatar_full      = 'https://avatars.steamstatic.com/fef49e7fa7e1997310d705b2a6158ff8dc1cdfeb_full.jpg';
    $sg->m_avatar_icon      = 'https://avatars.steamstatic.com/fef49e7fa7e1997310d705b2a6158ff8dc1cdfeb.jpg';
    $sg->m_members_count    = 6840;
    $sg->m_members_in_game  = 612;  // ~8.9% (Triggers "🔥 Highly Active Community" badge)
    $sg->m_members_online   = 1850; // ~27.0%
    $sg->m_members_chatting = 54;   // Triggers "💬 54 in Chat" badge
    $sg->m_announcements    = [
        // 1. CS2 at start of title
        [
            'title'   => '[CS2] Friday Night 5v5 Championship & Double Stats Weekend',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 45), // "Just now"
            'excerpt' => 'Join our weekly 5v5 competitive tournament tonight! Double skill points for MVP players and top fraggers. Sign up now on our forums.'
        ],
        // 2. TF2 at end of title
        [
            'title'   => 'Payload Marathon & Exclusive Item Drops [TF2]',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 900), // "15m ago"
            'excerpt' => 'Special community event this weekend across all Payload servers! Increased drop rates and custom community map rotations.'
        ],
        // 3. Counter-Strike: Source
        [
            'title'   => '[CSS] Dust2 Classic 24/7 Community Cup Registrations Open',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 7200), // "2h ago"
            'excerpt' => 'Old-school tournament registration is now officially open for all verified clan members. 128-tick rates and classic physics enabled.'
        ],
        // 4. Day of Defeat: Source (Must match DOD:S, not classic DOD)
        [
            'title'   => '[DODS] Day of Defeat: Source - Normandy Invasion Event',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 86400), // "Yesterday"
            'excerpt' => 'Historical battle reenactment this Saturday on custom avalanche and anzio maps. Rifle and machine gun restrictions lifted.'
        ],
        // 5. Left 4 Dead 2 (Must match L4D2, not L4D1)
        [
            'title'   => '[L4D2] Realism Expert Survival Weekend Challenge',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 172800), // "2d ago"
            'excerpt' => 'Can your squad survive Dark Carnival on Expert Realism? Special commemorative community ribbons for all completing teams.'
        ],
        // 6. Tactical Insurgency mod
        [
            'title'   => '[Insurgency] Tactical Cooperative Operations & Map Rotation',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 259200), // "3d ago"
            'excerpt' => 'New hardcore coop maps added to rotation. Slower movement speeds and realistic weapon lethality deployed to test servers.'
        ],
        // 7. Fistful of Frags
        [
            'title'   => '[FOF] Fistful of Frags High Noon Shootout & Whiskey Tourney',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 432000), // "5d ago"
            'excerpt' => 'Pass the whiskey! Dual Colt Peacemakers only for this wild west shootout. Highest notoriety takes the weekly crown.'
        ],
        // 8. Zombie Panic! Source
        [
            'title'   => '[ZPS] Zombie Panic! Source Infection Night 🎃',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 604800), // "7d ago"
            'excerpt' => 'Halloween warmup event! Don\'t get bitten, conserve your ammo, and stick with your carrier. Custom survivor skins unlocked.'
        ],
        // 9. Pirates, Vikings, and Knights II
        [
            'title'   => '[PVKII] Pirates, Vikings, and Knights II Melee Brawl ⚔️',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 864000), // "10d ago"
            'excerpt' => 'Three-way medieval carnage! Territory control and chest capture modes running all day. Choose your faction wisely.'
        ],
        // 10. Half-Life 2 Deathmatch
        [
            'title'   => '[HL2MP] Gravity Gun & Crossbow Deathmatch Madness',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 1209600), // "14d ago"
            'excerpt' => 'Physics-based deathmatch on killbox and lockdown. Catching toilet bowls and throwing sawblades has never been this fun.'
        ],
        // 11. FALSE POSITIVE TEST: Contains "staff" -> Must NOT match Fortress Forever (ff)
        [
            'title'   => '📢 Monthly Community Staff Meeting & Ban Appeals Review',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 1555200), // "18d ago"
            'excerpt' => 'Open community voice meeting this Sunday across all TeamSpeak and Discord lounges. Review of server moderation and rule updates.'
        ],
        // 12. FALSE POSITIVE TEST: Contains "platform" -> Must NOT match Team Fortress (tf)
        [
            'title'   => '⚙️ Platform Infrastructure Upgrade & Network Migration',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 1814400), // "21d ago"
            'excerpt' => 'We successfully migrated our core database and web infrastructure. Routing has been optimized to reduce ping across Europe.'
        ],
        // 13. Counter-Strike 1.6 Classic
        [
            'title'   => '[CStrike] Counter-Strike 1.6 Retro LAN Party Nostalgia',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 2160000), // "25d ago"
            'excerpt' => 'Original 1.6 goldSrc server live for one night only! De_cpl_mill, de_cbble and cs_assault in rotation.'
        ],
        // 14. General Announcement without game tag
        [
            'title'   => '🛡️ Community Guidelines & Voice Etiquette Reminder',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 2592000), // "1m ago"
            'excerpt' => 'Please treat fellow players with respect. Toxicity and mic spam in public voice channels will result in temporary timeouts.'
        ],
        // 15. General Recruitment
        [
            'title'   => '👥 Server Administrator & Moderator Recruitment Drive',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 3000000),
            'excerpt' => 'We are expanding our moderation team for competitive matchmaking servers. Active community members are welcome to apply.'
        ],
        // 16. Awards & Ribbons Announcement
        [
            'title'   => '🏆 Autumn Global Leaderboard Reset & VIP Rewards',
            'link'    => 'https://steamcommunity.com',
            'pubDate' => date('r', time() - 3600000),
            'excerpt' => 'Congratulations to our top three monthly MVPs! Exclusive badges and forum ribbons have been awarded to their profiles.'
        ]
    ];
}
// --- VISUAL TEST MODE END ---

$raw_db_name = trim((string)$s['name']);
$display_name = ($raw_db_name !== '') ? $raw_db_name : (!empty($sg->m_name) ? $sg->m_name : 'Steam Group');
$safe_name  = htmlspecialchars($display_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safe_descr = htmlspecialchars((string)($s['descr'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$group_slug = $sg->m_group_url ?: $group_ident;
$web_join_url    = "https://steamcommunity.com/groups/" . urlencode($group_slug);
$client_join_url = !empty($sg->m_group_id64) ? "steam://url/GroupSteamIDPage/" . urlencode($sg->m_group_id64) : $web_join_url;
$client_chat_url = !empty($sg->m_group_id64) ? "steam://friends/joinchat/" . urlencode($sg->m_group_id64) : $web_join_url;

$default_avatar = IMAGE_PATH . '/steamgroup/steam_default.png';
$group_avatar   = !empty($sg->m_avatar_full) ? htmlspecialchars($sg->m_avatar_full, ENT_QUOTES, 'UTF-8') : $default_avatar;

// Activity percentage calculation protected against zero division
$total_members = max(1, $sg->m_members_count);
$in_game_pct   = min(100, round(($sg->m_members_in_game / $total_members) * 100, 1));
$online_pct    = min(100 - $in_game_pct, round(($sg->m_members_online / $total_members) * 100, 1));
$offline_pct   = max(0, round(100 - ($in_game_pct + $online_pct), 1));

// Community activity badge rating
$activity_badge = '<span style="background:#555d6b; color:#fff; font-size:10px; font-weight:bold; padding:2px 6px; border-radius:3px; margin-left:6px; vertical-align:middle;">Casual Community</span>';
if ($in_game_pct >= 8.0) {
    $activity_badge = '<span style="background:#e74c3c; color:#fff; font-size:10px; font-weight:bold; padding:2px 6px; border-radius:3px; margin-left:6px; vertical-align:middle;">🔥 Highly Active Community</span>';
} elseif ($in_game_pct >= 3.0) {
    $activity_badge = '<span style="background:#90ba3c; color:#fff; font-size:10px; font-weight:bold; padding:2px 6px; border-radius:3px; margin-left:6px; vertical-align:middle;">⚡ Active Community</span>';
}
?>

<!-- Section: Steam Group Information -->
<div class="block">
    <?php printSectionTitle('Steam Community Group Information'); ?>
    <div class="subblock">
        <table class="data-table" style="width:100%;">
            <?php $info_idx = 0; ?>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="width:22%; font-weight:bold;">Community:</td>
                <td>
                    <img src="<?php echo $group_avatar; ?>" onerror="this.src='<?php echo $default_avatar; ?>';" alt="" width="28" height="28" style="vertical-align:middle; margin-right:8px; border-radius:4px; border:1px solid rgba(255,255,255,0.15);" />
                    <strong style="font-size:13px; vertical-align:middle;"><?php echo $safe_name; ?></strong>
                    <?php echo $activity_badge; ?>
                </td>
            </tr>
            <?php if (!empty($safe_descr)): ?>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Notes:</td>
                <td><?php echo $safe_descr; ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($sg->m_headline)): ?>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Headline:</td>
                <td><em>"<?php echo htmlspecialchars($sg->m_headline, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"</em></td>
            </tr>
            <?php endif; ?>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Group ID (64-bit):</td>
                <td><code><?php echo htmlspecialchars($sg->m_group_id64 ?: $group_ident, ENT_QUOTES, 'UTF-8'); ?></code></td>
            </tr>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold; vertical-align:middle;">Community Presence:</td>
                <td>
                    <?php if ($request_success): ?>
                        <div style="margin-bottom:6px;">
                            <span style="color:#90ba3c; font-weight:bold; margin-right:15px;">🟢 <?php echo number_format($sg->m_members_in_game); ?> Playing (In-Game)</span>
                            <span style="color:#66c0f4; font-weight:bold; margin-right:15px;">🔵 <?php echo number_format($sg->m_members_online); ?> Online on Steam</span>
                            <?php if ($sg->m_members_chatting > 0): ?>
                                <span style="color:#f39c12; font-weight:bold; margin-right:15px;">💬 <?php echo number_format($sg->m_members_chatting); ?> in Chat</span>
                            <?php endif; ?>
                            <span style="color:#888; font-weight:bold;">👥 <?php echo number_format($sg->m_members_count); ?> Total Members</span>
                        </div>
                        <!-- Tri-color Activity Distribution Bar -->
                        <div style="width:100%; max-width:480px; height:8px; background:#3d4450; border-radius:4px; overflow:hidden; display:flex;" title="In-Game: <?php echo $in_game_pct; ?>% | Online: <?php echo $online_pct; ?>% | Offline: <?php echo $offline_pct; ?>%">
                            <div style="width:<?php echo $in_game_pct; ?>%; background:#90ba3c;"></div>
                            <div style="width:<?php echo $online_pct; ?>%; background:#66c0f4;"></div>
                            <div style="width:<?php echo $offline_pct; ?>%; background:#555d6b;"></div>
                        </div>
                    <?php else: ?>
                        <span style="color:#e74c3c; font-weight:bold;">🔴 Offline / Unreachable</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr class="<?php echo ($info_idx++ % 2 == 0) ? 'bg1' : 'bg2'; ?>">
                <td style="font-weight:bold;">Join Community:</td>
                <td>
                    <a href="<?php echo $client_join_url; ?>" style="font-weight:bold; color:#2575fc; text-decoration:none; margin-right:15px;" title="Open Profile in Steam Client">
                        🚀 Launch Steam Client
                    </a>
                    <a href="<?php echo $client_chat_url; ?>" style="font-weight:bold; color:#f39c12; text-decoration:none; margin-right:15px;" title="Open Steam Group Chat Room directly">
                        💬 Open Group Chat
                    </a>
                    <a href="<?php echo $web_join_url; ?>" style="font-weight:bold; color:#f39c12; text-decoration:none; margin-right:15px;" title="Open Web Profile directly">
                        🌐 Web Profile &raquo;
                    </a>
                </td>
            </tr>
        </table>
    </div>
</div>

<br /><br />

<?php if (!$request_success): ?>
    <div class="block">
        <?php printSectionTitle('Steam Group Status'); ?>
        <div class="subblock">
            <p style="color:#e74c3c; padding:15px; font-weight:bold; text-align:center; margin:0;">
                ⚠️ <?php echo htmlspecialchars($sg->m_error ?: 'Could not connect to Steam Community.', ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>
    </div>
<?php else: ?>

<!-- Section: Announcements & Group News with Pagination -->
<a id="announcements"></a>
<div class="block">
    <?php
        $total_news = count($sg->m_announcements);
        printSectionTitle('Latest Community Announcements &amp; News' . ($total_news > 0 ? " ($total_news)" : ''));
    ?>
    <div class="subblock">
        <?php
            // Pagination & Per-page logic (identical to Discord & TeamSpeak viewers)
            $allowed_per_page = [3, 5, 10];
            $per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $allowed_per_page, true)
                        ? (int)$_GET['per_page']
                        : 5;

            $total_pages = max(1, (int)ceil($total_news / $per_page));
            $current_page = isset($_GET['spage']) ? max(1, min($total_pages, (int)$_GET['spage'])) : 1;
            $offset = ($current_page - 1) * $per_page;
            $paged_news = array_slice($sg->m_announcements, $offset, $per_page);

            $url_game = urlencode((string)($game ?? ''));
            $game_param = ($url_game !== '') ? "&amp;game={$url_game}" : '';
            $page_base_url = "hlstats.php?mode=steamgroup{$game_param}&amp;stId={$stId}&amp;per_page={$per_page}";
        ?>

        <!-- Per-page selector dropdown -->
        <?php if ($total_news > 5): ?>
        <div style="margin-bottom: 10px; text-align: right; font-size: 11px;">
            <form method="get" action="hlstats.php" style="display:inline; margin:0;">
                <input type="hidden" name="mode" value="steamgroup" />
                <?php if (!empty($game)): ?>
                    <input type="hidden" name="game" value="<?php echo htmlspecialchars((string)$game, ENT_QUOTES, 'UTF-8'); ?>" />
                <?php endif; ?>
                <input type="hidden" name="stId" value="<?php echo $stId; ?>" />
                <label for="per_page_select" style="color:inherit; opacity:0.8; margin-right:4px;">News per page:</label>
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
                <td class="fSmall" style="width:120px;">Date</td>
                <td class="fSmall" style="width:34%;">Announcement Title</td>
                <td class="fSmall">Summary</td>
                <td class="fSmall" style="width:110px; text-align:center;">Action</td>
            </tr>
            <?php
            if (!empty($paged_news)) {
                $n_idx = 0;
                foreach ($paged_news as $news) {
                    $row_class  = ($n_idx++ % 2 == 0) ? 'bg1' : 'bg2';
                    $safe_title = htmlspecialchars($news['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $safe_link  = htmlspecialchars($news['link'], ENT_QUOTES, 'UTF-8');
                    $raw_date   = $news['pubDate'] ?: 'now';
                    $safe_date  = htmlspecialchars(date('M d, Y @ H:i', strtotime($raw_date)), ENT_QUOTES, 'UTF-8');
                    $time_ago   = format_steam_time_ago($raw_date);
                    $safe_ex    = htmlspecialchars($news['excerpt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $game_badge = getSteamNewsBadgeHtml($news['title'], $hlx_games);
            ?>
                <tr class="<?php echo $row_class; ?>">
                    <td style="vertical-align:middle; font-size:11px; color:inherit; white-space:nowrap;" title="<?php echo $safe_date; ?>">
                        📅 <?php echo $time_ago; ?>
                    </td>
                    <td class="fHeading" style="vertical-align:middle;">
                        <?php echo $game_badge; ?>
                        <span style="font-size:13px; margin-right:4px;">📢</span>
                        <a href="<?php echo $safe_link; ?>" target="_blank" rel="noopener noreferrer" style="font-weight:bold; color:inherit;">
                            <?php echo $safe_title; ?>
                        </a>
                    </td>
                    <td style="vertical-align:middle; font-size:11px; line-height:1.45; color:inherit; opacity:0.9;">
                        <?php echo $safe_ex; ?>
                    </td>
                    <td style="vertical-align:middle; text-align:center;">
                        <a href="<?php echo $safe_link; ?>" target="_blank" rel="noopener noreferrer" style="font-size:10px; font-weight:bold; background:rgba(128,128,128,0.2); color:inherit; border:1px solid rgba(128,128,128,0.4); padding:3px 8px; border-radius:3px; text-decoration:none; display:inline-block;">
                            Read More &raquo;
                        </a>
                    </td>
                </tr>
            <?php
                }
            } else {
                echo '<tr class="bg1"><td colspan="4" style="text-align:center; padding:15px; color:#888;">No recent announcements found in this Steam Group.</td></tr>';
            }
            ?>
        </table>

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div style="margin-top:12px; text-align:center; font-size:12px; font-weight:bold;">
                <?php if ($current_page > 1): ?>
                    <a href="<?php echo $page_base_url . '&amp;spage=' . ($current_page - 1); ?>#announcements" style="margin-right:8px;">&laquo; Previous</a>
                <?php endif; ?>

                <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>

                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo $page_base_url . '&amp;spage=' . ($current_page + 1); ?>#announcements" style="margin-left:8px;">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>