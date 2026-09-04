<?php
    if (!defined('IN_HLSTATS')) {
        die('Do not access this file directly.');
    }

    // VOICECOMM MODULE
    global $db, $g_options, $game, $teamspeakDisplay;

    if (!defined('TS')) define('TS', 0);
    if (!defined('VENT')) define('VENT', 1);

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

    if ($db->num_rows($result) >= 1) {
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

        while ($row = $db->fetch_array($result)) {
            $row_type = (int)($row['serverType'] ?? 0);
            if ($row_type == TS) {
                $ts_servers[] = $row;
            } else if ($row_type == VENT) {
                $vent_servers[] = $row;
            }
        }

        if (!empty($ts_servers))
        {
            require_once(PAGE_PATH . '/teamspeak_class.php');
            // Ensure global object is available
            global $teamspeakDisplay;
            if (!isset($teamspeakDisplay)) {
                $teamspeakDisplay = new teamspeakDisplayClass;
            }

            foreach($ts_servers as $ts_server)
            {
                $settings = $teamspeakDisplay->getDefaultSettings();
                $settings['serveraddress']   = (string)$ts_server['addr'];
                $settings['serverqueryport'] = (int)$ts_server['queryPort'];
                $settings['serverudpport']   = (int)$ts_server['UDPPort'];
                $ts_info = $teamspeakDisplay->queryTeamspeakServerEx($settings);

                if (isset($ts_info['queryerror']) && (int)$ts_info['queryerror'] != 0) {
                    $ts_channels = 'err';
                    $ts_slots = 'Error (' . (int)$ts_info['queryerror'] . ')';
                } else {
                    $chan_cnt   = (isset($ts_info['channellist']) && is_array($ts_info['channellist'])) ? count($ts_info['channellist']) : 0;
                    $player_cnt = (isset($ts_info['playerlist']) && is_array($ts_info['playerlist'])) ? count($ts_info['playerlist']) : 0;
                    $max_users  = (string)($ts_info['serverinfo']['server_maxusers'] ?? '?');
                    $ts_channels = $chan_cnt;
                    $ts_slots    = $player_cnt . '/' . $max_users;
                }

                // Security: Sanitization
                $safe_name    = htmlspecialchars(trim((string)$ts_server['name']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safe_addr    = htmlspecialchars((string)$ts_server['addr'], ENT_QUOTES, 'UTF-8');
                $safe_pass    = htmlspecialchars((string)$ts_server['password'], ENT_QUOTES, 'UTF-8');
                $safe_descr   = htmlspecialchars((string)$ts_server['descr'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $ts_server_id = (int)$ts_server['serverId'];
                $ts_udp_port  = (int)$ts_server['UDPPort'];
?>
        <tr class="bg1">
            <td class="fHeading">
                <img src="<?php echo IMAGE_PATH; ?>/teamspeak/teamspeak.gif" alt="tsicon" />
                &nbsp;<a href="<?php echo $scripturl; ?>?mode=teamspeak&amp;game=<?php echo $url_game; ?>&amp;tsId=<?php echo $ts_server_id; ?>"><?php echo $safe_name; ?></a>
            </td>
            <td>
                <a href="teamspeak://<?php echo $safe_addr . ':' . $ts_udp_port; ?>/?channel=?password=<?php echo $safe_pass; ?>"><?php echo $safe_addr . ':' . $ts_udp_port; ?></a>
            </td>
            <td>
                <?php echo $safe_pass; ?>
            </td>
            <td style="text-align:right;">
                <?php echo $ts_channels; ?>
            </td>
            <td style="text-align:right;">
                <?php echo $ts_slots; ?>
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
                $ve_info->m_cmdcode = 2;                                    // Detail mode.
                $ve_info->m_cmdhost = (string)$vent_server['addr'];
                $ve_info->m_cmdport = (int)$vent_server['queryPort'];
                /////////
                $ve_info->Request();

                if (!empty($ve_info->m_error) || empty($ve_info->m_name)) {
                    // Request failed
                    $ve_channels = 'N/A';
                    $ve_slots = 'Offline';
                    $ve_server_name = '';
                } else {
                    $ve_channels = (int)($ve_info->m_channelcount ?? 0);
                    $cli_count   = (int)($ve_info->m_clientcount ?? 0);
                    $max_cli     = (int)($ve_info->m_maxclients ?? 0);
                    $ve_slots    = $cli_count . '/' . $max_cli;
                    $ve_server_name = (string)$ve_info->m_name;
                }

                // Security: Sanitization
                $safe_name     = htmlspecialchars(trim((string)$vent_server['name']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safe_addr     = htmlspecialchars((string)$vent_server['addr'], ENT_QUOTES, 'UTF-8');
                $safe_pass     = htmlspecialchars((string)$vent_server['password'], ENT_QUOTES, 'UTF-8');
                $safe_descr    = htmlspecialchars((string)$vent_server['descr'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safe_ve_name  = htmlspecialchars((string)$ve_server_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $ve_server_id  = (int)$vent_server['serverId'];
                $ve_query_port = (int)$vent_server['queryPort'];
        ?>
            <tr class="bg1">
                <td class="fHeading">
                    <img src="<?php echo IMAGE_PATH; ?>/ventrilo/ventrilo.png" alt="venticon" />
                    &nbsp;<a href="<?php echo $scripturl; ?>?mode=ventrilo&amp;game=<?php echo $url_game; ?>&amp;veId=<?php echo $ve_server_id; ?>"><?php echo $safe_name; ?></a>
                </td>
                <td>
                    <a href="ventrilo://<?php echo $safe_addr . ':' . $ve_query_port; ?>/servername=<?php echo $safe_ve_name; ?>">
                    <?php echo $safe_addr . ':' . $ve_query_port; ?>
                    </a>
                </td>
                <td>
                    <?php echo $safe_pass; ?>
                </td>
                <td style="text-align:right;">
                    <?php echo $ve_channels; ?>
                </td>
                <td style="text-align:right;">
                    <?php echo $ve_slots; ?>
                </td>
                <td>
                    <?php echo $safe_descr; ?>
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