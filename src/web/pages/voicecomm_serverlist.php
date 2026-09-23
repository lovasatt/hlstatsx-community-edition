<?php
    if (!defined('IN_HLSTATS')) {
        die('Do not access this file directly.');
    }

    // VOICECOMM MODULE
    global $db, $g_options, $game, $teamspeakDisplay;

    if (!defined('TS')) define('TS', 0);
    if (!defined('VENT')) define('VENT', 1);
    if (!defined('DISCORD')) define('DISCORD', 2);
    if (!defined('STEAMGROUP')) define('STEAMGROUP', 3);

    $game = isset($game) ? (string)$game : '';
    $url_game = urlencode($game);
    $scripturl = htmlspecialchars((string)($g_options['scripturl'] ?? ''), ENT_QUOTES, 'UTF-8');

    $result = $db->query("
        SELECT
            serverId,
            name,
            addr,
            password,
            descr,
            queryPort,
            UDPPort,
            serverType
        FROM
            hlstats_Servers_VoiceComm
    ");

    if ($result && $db->num_rows($result) >= 1) {
        printSectionTitle('Voice Server');
?>
    <div class="subblock">
        <table class="data-table">
            <tr class="data-table-head">
                <td class="fSmall">Server Name</td>
                <td class="fSmall">Server Address</td>
                <td class="fSmall">Password</td>
                <td class="fSmall" style="text-align:right;">Channels</td>
                <td class="fSmall" style="text-align:right;">Slots&nbsp;used</td>
                <td class="fSmall">Notes</td>
            </tr>
<?php
        $ts_servers = array();
        $vent_servers = array();
        $discord_servers = array();
        $steam_servers = array();

        while ($row = $db->fetch_array($result)) {
            $row_type = (int)($row['serverType'] ?? 0);
            if ($row_type == TS) {
                $ts_servers[] = $row;
            } else if ($row_type == VENT) {
                $vent_servers[] = $row;
            } else if ($row_type == DISCORD) {
                $discord_servers[] = $row;
            } else if ($row_type == STEAMGROUP) {
                $steam_servers[] = $row;
            }
        }
        $db->free_result($result);

        $row_idx = 0;

        if (!empty($ts_servers))
        {
            require_once(PAGE_PATH . '/teamspeak_class.php');
            global $teamspeakDisplay;
            if (!isset($teamspeakDisplay)) {
                $teamspeakDisplay = new teamspeakDisplayClass;
            }

            foreach($ts_servers as $ts_server)
            {
                $raw_pass = (string)($ts_server['password'] ?? '');
                $ts_api_key = '';
                $ts_connect_pass = $raw_pass;

                if (strpos($raw_pass, '|') !== false) {
                    list($ts_connect_pass, $ts_api_key) = explode('|', $raw_pass, 2);
                } elseif (strpos($raw_pass, 'apikey:') === 0) {
                    $ts_api_key = substr($raw_pass, 7);
                    $ts_connect_pass = '';
                } elseif ((int)$ts_server['queryPort'] === 10080 || strlen($raw_pass) >= 30) {
                    $ts_api_key = $raw_pass;
                    $ts_connect_pass = '';
                }

                $settings = $teamspeakDisplay->getDefaultSettings();
                $settings['serveraddress']   = (string)$ts_server['addr'];
                $settings['serverqueryport'] = ((int)$ts_server['queryPort'] > 0) ? (int)$ts_server['queryPort'] : 10011;
                $settings['serverudpport']   = ((int)$ts_server['UDPPort'] > 0) ? (int)$ts_server['UDPPort'] : 9987;
                $settings['apikey']          = trim($ts_api_key);
                $ts_info = $teamspeakDisplay->queryTeamspeakServerEx($settings);

                $is_ts_online = (!isset($ts_info['queryerror']) || (int)$ts_info['queryerror'] === 0);

                if (!$is_ts_online) {
                    $ts_channels = '<span style="color:#888;">&mdash;</span>';
                    $ts_slots_html = '<span style="color:#e74c3c; font-weight:bold;">🔴 Offline</span>';
                } else {
                    $chan_cnt   = (isset($ts_info['channellist']) && is_array($ts_info['channellist'])) ? count($ts_info['channellist']) : 0;
                    $player_cnt = (isset($ts_info['playerlist']) && is_array($ts_info['playerlist'])) ? count($ts_info['playerlist']) : 0;
                    $max_users  = (int)($ts_info['serverinfo']['server_maxusers'] ?? 32);

                    $ts_channels   = $chan_cnt;
                    $ts_slots_html = '<span style="color:#2ecc71; font-weight:bold;">🟢 ' . $player_cnt . ' / ' . $max_users . ' online</span>';
                }

                $raw_db_name  = trim((string)$ts_server['name']);
                $display_name = ($raw_db_name !== '') ? $raw_db_name : (!empty($ts_info['serverinfo']['server_name']) ? $ts_info['serverinfo']['server_name'] : 'TeamSpeak Server');
                $safe_name    = htmlspecialchars($display_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safe_addr    = htmlspecialchars((string)$ts_server['addr'], ENT_QUOTES, 'UTF-8');
                $safe_descr   = htmlspecialchars((string)$ts_server['descr'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $ts_server_id = (int)$ts_server['serverId'];
                $ts_udp_port  = ((int)$ts_server['UDPPort'] > 0) ? (int)$ts_server['UDPPort'] : 9987;
                $row_class    = ($row_idx++ % 2 === 0) ? 'bg1' : 'bg2';
?>
        <tr class="<?php echo $row_class; ?>">
            <td class="fHeading">
                <img src="<?php echo IMAGE_PATH; ?>/teamspeak/teamspeak.png" alt="tsicon" width="16" height="16" style="vertical-align:middle;" />
                &nbsp;<a href="<?php echo $scripturl; ?>?mode=teamspeak&amp;game=<?php echo $url_game; ?>&amp;tsId=<?php echo $ts_server_id; ?>"><?php echo $safe_name; ?></a>
            </td>
            <td>
                <a href="ts3server://<?php echo $safe_addr; ?>?port=<?php echo $ts_udp_port; ?>">
                    <?php echo $safe_addr . ':' . $ts_udp_port; ?>
                </a>
            </td>
            <td>
                <?php if (!empty($ts_connect_pass)): ?>
                    <span style="color:#f39c12; font-weight:bold;">Protected</span>
                <?php else: ?>
                    <span style="color:#888;">None</span>
                <?php endif; ?>
            </td>
            <td style="text-align:right;">
                <?php echo $ts_channels; ?>
            </td>
            <td style="text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums;">
                <?php echo $ts_slots_html; ?>
            </td>
            <td>
                <?php echo $safe_descr; ?>
            </td>
        </tr>
<?php
            }
        }

        if (!empty($vent_servers))
        {
            require_once(PAGE_PATH . '/ventrilostatus.php');
            foreach($vent_servers as $vent_server)
            {
                $ve_info = new CVentriloStatus;
                $ve_info->m_cmdcode = 2;
                $ve_info->m_cmdhost = (string)$vent_server['addr'];
                $ve_info->m_cmdport = ((int)$vent_server['queryPort'] > 0) ? (int)$vent_server['queryPort'] : 3784;
                $ve_info->RequestCached(90);

                $is_ve_online = (empty($ve_info->m_error) && !empty($ve_info->m_name));

                if (!$is_ve_online) {
                    $ve_channels   = '<span style="color:#888;">&mdash;</span>';
                    $ve_slots_html = '<span style="color:#e74c3c; font-weight:bold;">🔴 Offline</span>';
                    $ve_server_name = '';
                } else {
                    $ve_channels   = (int)($ve_info->m_channelcount ?? 0);
                    $cli_count     = (int)($ve_info->m_clientcount ?? 0);
                    $max_cli       = (int)($ve_info->m_maxclients ?? 32);
                    $ve_slots_html = '<span style="color:#2ecc71; font-weight:bold;">🟢 ' . $cli_count . ' / ' . $max_cli . ' online</span>';
                    $ve_server_name = (string)$ve_info->m_name;
                }

                $raw_db_name  = trim((string)$vent_server['name']);
                $display_name = ($raw_db_name !== '') ? $raw_db_name : (!empty($ve_server_name) ? $ve_server_name : 'Ventrilo Server');
                $safe_name    = htmlspecialchars($display_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safe_addr     = htmlspecialchars((string)$vent_server['addr'], ENT_QUOTES, 'UTF-8');
                $safe_pass     = htmlspecialchars((string)$vent_server['password'], ENT_QUOTES, 'UTF-8');
                $safe_descr    = htmlspecialchars((string)$vent_server['descr'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safe_ve_name  = htmlspecialchars((string)$ve_server_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $ve_server_id  = (int)$vent_server['serverId'];
                $ve_port       = ((int)$vent_server['queryPort'] > 0) ? (int)$vent_server['queryPort'] : 3784;
                $row_class     = ($row_idx++ % 2 === 0) ? 'bg1' : 'bg2';
?>
            <tr class="<?php echo $row_class; ?>">
                <td class="fHeading">
                    <img src="<?php echo IMAGE_PATH; ?>/ventrilo/ventrilo.png" alt="venticon" width="16" height="16" style="vertical-align:middle;" />
                    &nbsp;<a href="<?php echo $scripturl; ?>?mode=ventrilo&amp;game=<?php echo $url_game; ?>&amp;veId=<?php echo $ve_server_id; ?>"><?php echo $safe_name; ?></a>
                </td>
                <td>
                    <a href="ventrilo://<?php echo $safe_addr . ':' . $ve_port; ?>/servername=<?php echo rawurlencode($display_name); ?>">
                    <?php echo $safe_addr . ':' . $ve_port; ?>
                    </a>
                </td>
                <td>
                    <?php if (!empty($safe_pass)): ?>
                        <span style="color:#f39c12; font-weight:bold;">Protected</span>
                    <?php else: ?>
                        <span style="color:#888;">None</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;">
                    <?php echo $ve_channels; ?>
                </td>
                <td style="text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums;">
                    <?php echo $ve_slots_html; ?>
                </td>
                <td>
                    <?php echo $safe_descr; ?>
                </td>
            </tr>
<?php
            }
        }

        if (!empty($discord_servers))
        {
            require_once(PAGE_PATH . '/discordstatus.php');
            foreach($discord_servers as $dc_server)
            {
                $dc_info = new CDiscordStatus;
                $dc_guild_id = (string)$dc_server['addr'];
                $dc_invite = (string)$dc_server['password'];
                $dc_info->Request($dc_guild_id, $dc_invite);

                $raw_db_name  = trim((string)$dc_server['name']);
                $display_name = ($raw_db_name !== '') ? $raw_db_name : (!empty($dc_info->m_name) ? $dc_info->m_name : 'Discord Server');
                $safe_name    = htmlspecialchars($display_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safe_descr   = htmlspecialchars((string)$dc_server['descr'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safe_invite  = htmlspecialchars($dc_info->m_invite, ENT_QUOTES, 'UTF-8');
                $dc_server_id = (int)$dc_server['serverId'];

                $is_dc_online = empty($dc_info->m_error) && !empty($dc_info->m_name);
                $dc_channels  = ($is_dc_online && $dc_info->m_channels > 0) ? (int)$dc_info->m_channels : '<span style="color:#888;">&mdash;</span>';

                if ($is_dc_online) {
                    $dc_slots_html = '<span style="color:#2ecc71; font-weight:bold;">🟢 ' . (int)$dc_info->m_online . ' online</span>';
                } else {
                    $dc_slots_html = '<span style="color:#e74c3c; font-weight:bold;">🔴 Offline</span>';
                }

                $row_class = ($row_idx++ % 2 === 0) ? 'bg1' : 'bg2';
?>
            <tr class="<?php echo $row_class; ?>">
                <td class="fHeading">
                    <?php if (!empty($dc_info->m_icon)): ?>
                        <img src="<?php echo htmlspecialchars($dc_info->m_icon, ENT_QUOTES, 'UTF-8'); ?>" alt="dcicon" width="16" height="16" style="vertical-align:middle; border-radius:3px; object-fit:cover;" />
                    <?php else: ?>
                        <img src="<?php echo IMAGE_PATH; ?>/discord/discord.png" alt="discord" width="16" height="16" style="vertical-align:middle;" />
                    <?php endif; ?>
                    &nbsp;<a href="<?php echo $scripturl; ?>?mode=discord&amp;game=<?php echo $url_game; ?>&amp;dcId=<?php echo $dc_server_id; ?>"><?php echo $safe_name; ?></a>
                </td>
                <td>
                    <?php if (!empty($safe_invite)): ?>
                        <a href="<?php echo $safe_invite; ?>" target="_blank" rel="noopener noreferrer" style="color:#5865F2; font-weight:bold; text-decoration:none;">
                          🔗 Join Server
                        </a>
                    <?php else: ?>
                        <span style="color:#888;">ID: <?php echo htmlspecialchars($dc_guild_id, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </td>
                <td><span style="color:#888;">None</span></td>
                <td style="text-align:right;">
                    <?php echo $dc_channels; ?>
                </td>
                <td style="text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums;">
                    <?php echo $dc_slots_html; ?>
                </td>
                <td>
                    <?php echo $safe_descr; ?>
                </td>
            </tr>
<?php
            }
        }

        if (!empty($steam_servers))
        {
            require_once(PAGE_PATH . '/steamstatus.php');
            foreach($steam_servers as $sg_server)
            {
                $sg_info = new CSteamGroupStatus;
                $sg_ident = (string)$sg_server['addr'];
                $sg_ok = $sg_info->Request($sg_ident);

                $raw_db_name  = trim((string)$sg_server['name']);
                $display_name = ($raw_db_name !== '') ? $raw_db_name : (!empty($sg_info->m_name) ? $sg_info->m_name : 'Steam Group');
                $safe_name    = htmlspecialchars($display_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safe_descr   = htmlspecialchars((string)$sg_server['descr'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $sg_server_id = (int)$sg_server['serverId'];
                $sg_url_ident = urlencode($sg_info->m_group_url ?: $sg_ident);
                $join_url     = "https://steamcommunity.com/groups/{$sg_url_ident}";

                if ($sg_ok && empty($sg_info->m_error)) {
                    $sg_slots_html = '<span style="color:#2ecc71; font-weight:bold;">🟢 ' . number_format($sg_info->m_members_in_game) . ' in-game / ' . number_format($sg_info->m_members_count) . ' members</span>';
                } else {
                    $sg_slots_html = '<span style="color:#e74c3c; font-weight:bold;">🔴 Offline</span>';
                }

                $row_class = ($row_idx++ % 2 === 0) ? 'bg1' : 'bg2';
?>
            <tr class="<?php echo $row_class; ?>">
                <td class="fHeading">
                    <?php if (!empty($sg_info->m_avatar_icon)): ?>
                        <img src="<?php echo htmlspecialchars($sg_info->m_avatar_icon, ENT_QUOTES, 'UTF-8'); ?>" alt="steamicon" width="16" height="16" style="vertical-align:middle; border-radius:3px; object-fit:cover;" />
                    <?php else: ?>
                        <img src="<?php echo IMAGE_PATH; ?>/steamgroup/steam.png" alt="steam" width="16" height="16" style="vertical-align:middle;" />
                    <?php endif; ?>
                    &nbsp;<a href="<?php echo $scripturl; ?>?mode=steamgroup&amp;game=<?php echo $url_game; ?>&amp;stId=<?php echo $sg_server_id; ?>"><?php echo $safe_name; ?></a>
                </td>
                <td>
                    <a href="<?php echo $join_url; ?>" target="_blank" rel="noopener noreferrer" style="color:#2575fc; font-weight:bold; text-decoration:none;">
                      🔗 View Group
                    </a>
                </td>
                <td><span style="color:#888;">None</span></td>
                <td style="text-align:right;">
                    <span style="color:#888;">&mdash;</span>
                </td>
                <td style="text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums;">
                    <?php echo $sg_slots_html; ?>
                </td>
                <td>
                    <?php echo !empty($safe_descr) ? $safe_descr : '<span style="color:#888;">&mdash;</span>'; ?>
                </td>
            </tr>
<?php
            }
        }

?>
        </table>
    </div>
<br /><br />
<?php
    }
    // VOICECOMM MODULE END
?>