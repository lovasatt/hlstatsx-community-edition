<?php
    // VOICECOMM MODULE
    global $db, $g_options, $game, $teamspeakDisplay;
    
    if (!defined('TS')) define('TS', 0);
    if (!defined('VENT')) define('VENT', 1);
    
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
        
	while ($row = $db->fetch_array()) {
            $row_type = (int)$row['serverType'];
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
		$settings['serveraddress'] = $ts_server['addr'];
		$settings['serverqueryport'] = $ts_server['queryPort'];
		$settings['serverudpport'] = $ts_server['UDPPort'];
		$ts_info = $teamspeakDisplay->queryTeamspeakServerEx($settings);
		
                if (isset($ts_info['queryerror']) && $ts_info['queryerror'] != 0) {
		    $ts_channels = 'err';
		    $ts_slots = $ts_info['queryerror'];
		} else {
		    $ts_channels = count($ts_info['channellist']);
                    $max_users = isset($ts_info['serverinfo']['server_maxusers']) ? $ts_info['serverinfo']['server_maxusers'] : '?';
		    $ts_slots = count($ts_info['playerlist']).'/'.$max_users;
		}
                
                // Security: Sanitization
                $safe_name = htmlspecialchars(trim((string)$ts_server['name']));
                $safe_addr = htmlspecialchars((string)$ts_server['addr']);
                $safe_pass = htmlspecialchars((string)$ts_server['password']);
                $safe_descr = htmlspecialchars((string)$ts_server['descr']);
                $url_game = urlencode($game);
?>
        <tr class="bg1">
	    <td class="fHeading">
		<img src="<?php echo IMAGE_PATH; ?>/teamspeak/teamspeak.gif" alt="tsicon" />
		&nbsp;<a href="<?php echo htmlspecialchars($g_options['scripturl']); ?>?mode=teamspeak&amp;game=<?php echo $url_game; ?>&amp;tsId=<?php echo $ts_server['serverId']; ?>"><?php echo $safe_name; ?></a>
	    </td>
	    <td>
		<a href="teamspeak://<?php echo $safe_addr.':'.$ts_server['UDPPort'] ?>/?channel=?password=<?php echo $safe_pass; ?>"><?php echo $safe_addr.':'.$ts_server['UDPPort']; ?></a>
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
		$ve_info->m_cmdcode	= 2;					// Detail mode.
		$ve_info->m_cmdhost = $vent_server['addr'];
		$ve_info->m_cmdport = (int)$vent_server['queryPort'];
		/////////
		$rc = $ve_info->Request();
                
		if ($rc) {
		    // Request failed
		    $ve_channels = 'N/A';
		    $ve_slots = 'Offline';
                    $ve_server_name = ''; 
		} else {
		    $ve_channels = $ve_info->m_channelcount;
		    $ve_slots = $ve_info->m_clientcount.'/'.$ve_info->m_maxclients;
                    $ve_server_name = $ve_info->m_name;
		}
                
                // Security: Sanitization
                $safe_name = htmlspecialchars(trim((string)$vent_server['name']));
                $safe_addr = htmlspecialchars((string)$vent_server['addr']);
                $safe_pass = htmlspecialchars((string)$vent_server['password']);
                $safe_descr = htmlspecialchars((string)$vent_server['descr']);
                $safe_ve_name = htmlspecialchars((string)$ve_server_name);
                $url_game = urlencode($game);
	?>  
	    <tr class="bg1">
		<td class="fHeading">
		    <img src="<?php echo IMAGE_PATH; ?>/ventrilo/ventrilo.png" alt="venticon" />
		    &nbsp;<a href="<?php echo htmlspecialchars($g_options['scripturl']); ?>?mode=ventrilo&amp;game=<?php echo $url_game; ?>&amp;veId=<?php echo $vent_server['serverId']; ?>"><?php echo $safe_name; ?></a>
		</td>
		<td>
		    <a href="ventrilo://<?php echo $safe_addr.':'.$vent_server['queryPort'] ?>/servername=<?php echo $safe_ve_name; ?>">
		    <?php echo $safe_addr.':'.$vent_server['queryPort']; ?>
		    </a></td>
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