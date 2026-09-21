<?php
// Teamspeak Display Preview Release 3
// Copyright (C) 2005  Guido van Biemen (aka MrGuide@NL)
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

if (!defined('IN_HLSTATS')) {
    die('Do not access this file directly.');
}

#[\AllowDynamicProperties]
class teamspeakDisplayClass
{
    public $is_ts3 = false;
    public $is_ts6 = false;
    public $error = '';

    function _stripEOL($evalString) {
        if ($evalString === false || $evalString === null) {
            return '';
        }
        $evalString = (string)$evalString;
        $newLen = strlen($evalString);
        while ($newLen > 0 && ((substr($evalString, $newLen - 1, 1) == "\r") || (substr($evalString, $newLen - 1, 1) == "\n"))) {
            $newLen--;
        }
        return substr($evalString, 0, $newLen);
    }

    // Connects and auto-detects TS2 vs TS3 protocol
    function _openConnection(&$socket, $host, $port, $timeout) {
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, (int)$port, $errno, $errstr, $timeout);

        if ($socket) {
            stream_set_timeout($socket, 2);
            $line = fgets($socket, 4096);
            if ($line !== false) {
                $trimmed = $this->_stripEOL($line);
                // TeamSpeak 3 handshake
                if (strpos($trimmed, 'TS3') === 0) {
                    $this->is_ts3 = true;
                    // Read welcome message line
                    fgets($socket, 4096);
                    return true;
                }
                // TeamSpeak 2 handshake
                if ($trimmed == "[TS]") {
                    $this->is_ts3 = false;
                    return true;
                }
            }
        }
        $this->error = "Connection timed out or host unreachable ($host:$port).";
        return false;
    }

    function _closeConnection($socket) {
        if ($socket) {
            @fputs($socket, "quit\n");
            @fclose($socket);
        }
    }

    // ==========================================
    // --- TS6 HTTP REST WEBQUERY ENGINE (JSON) ---
    // ==========================================
    private function _queryTS6Http($host, $queryPort, $udpPort, $apiKey) {
        $base_url = "http://{$host}:{$queryPort}/1";

        $headers = ['Accept: application/json'];
        if (!empty($apiKey)) {
            $headers[] = 'x-api-key: ' . trim($apiKey);
        }

        $fetch_json = function($endpoint) use ($base_url, $headers) {
            $ch = curl_init($base_url . $endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_ENCODING       => "",
                CURLOPT_HTTPHEADER     => $headers
            ]);
            $res = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200 && !empty($res)) {
                $data = json_decode($res, true);
                if (is_array($data) && isset($data['status']['code']) && (int)$data['status']['code'] === 0) {
                    return $data['body'] ?? [];
                }
            }
            return false;
        };

        // 1. Fetch virtual server info
        $serverinfo_body = $fetch_json('/serverinfo');
        if ($serverinfo_body === false || empty($serverinfo_body[0])) {
            $this->error = "TS6 WebQuery authentication failed or invalid response from port {$queryPort}.";
            return false;
        }
        $sdata = $serverinfo_body[0];

        // 2. Fetch channel list with topics
        $chan_body = $fetch_json('/channellist?-topic');
        $raw_channels = is_array($chan_body) ? $chan_body : [];

        // 3. Fetch client list with flags (away, mute, deaf, times, uid)
        $client_body = $fetch_json('/clientlist?-away&-voice&-times&-uid');
        $raw_clients = is_array($client_body) ? $client_body : [];

        $mapped_channels = [];
        foreach ($raw_channels as $ch) {
            $cid = (int)($ch['cid'] ?? 0);
            $mapped_channels[$cid] = [
                'channelid'   => $cid,
                'channelname' => (string)($ch['channel_name'] ?? 'Channel'),
                'topic'       => (string)($ch['channel_topic'] ?? ''),
                'parent'      => ((int)($ch['pid'] ?? 0) === 0) ? -1 : (int)($ch['pid'] ?? 0),
                'order'       => (int)($ch['channel_order'] ?? 0)
            ];
        }

        $mapped_players = [];
        foreach ($raw_clients as $cl) {
            // Filter out ServerQuery bots: client_type 1 (keep human players only)
            if (isset($cl['client_type']) && (int)$cl['client_type'] === 0) {
                $pid = (int)($cl['clid'] ?? 0);
                $is_away = !empty($cl['client_away']);
                $is_muted = (!empty($cl['client_input_muted']) || !empty($cl['client_input_hardware_deactivated']));
                $is_deaf = (!empty($cl['client_output_muted']) || !empty($cl['client_output_hardware_deactivated']));
                $idle_time = (int)($cl['client_idle_time'] ?? 0);

                $mapped_players[$pid] = [
                    'playerid'     => $pid,
                    'channelid'    => (int)($cl['cid'] ?? 0),
                    'playername'   => (string)($cl['client_nickname'] ?? 'Player'),
                    'is_away'      => $is_away,
                    'away_message' => $is_away ? (string)($cl['client_away_message'] ?? 'Away') : '',
                    'is_muted'     => $is_muted,
                    'is_deaf'      => $is_deaf,
                    'is_cc'        => !empty($cl['client_is_channel_commander']),
                    'idle_time'    => $idle_time > 0 ? (int)round($idle_time / 1000) : 0,
                    'unique_id'    => (string)($cl['client_unique_identifier'] ?? '')
                ];
            }
        }

        $this->is_ts6 = true;

        return [
            'queryerror'  => 0,
            'serverinfo'  => [
                'server_name'     => (string)($sdata['virtualserver_name'] ?? 'TeamSpeak Server'),
                'server_platform' => (string)($sdata['virtualserver_platform'] ?? 'Linux'),
                'server_maxusers' => (int)($sdata['virtualserver_maxclients'] ?? 32),
                'server_uptime'   => (int)($sdata['virtualserver_uptime'] ?? 0),
                'server_version'  => (string)($sdata['virtualserver_version'] ?? '6.x')
            ],
            'channellist' => $mapped_channels,
            'playerlist'  => $mapped_players
        ];
    }

    // --- TS3 SPECIFIC PROTOCOL HANDLERS ---
    private function _unescapeTS3($str) {
        $map = [
            '\\s' => ' ',
            '\\p' => '|',
            '\\/' => '/',
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\a' => '',
            '\\b' => '',
            '\\v' => '',
            '\\\\' => '\\'
        ];
        return strtr((string)$str, $map);
    }

    private function _parseTS3KeyValues($str) {
        $result = [];
        $parts = explode(' ', trim((string)$str));
        foreach ($parts as $part) {
            $pos = strpos($part, '=');
            if ($pos !== false) {
                $key = substr($part, 0, $pos);
                $val = substr($part, $pos + 1);
                $result[$key] = $this->_unescapeTS3($val);
            } else {
                $result[$part] = true;
            }
        }
        return $result;
    }

    private function _parseTS3List($str) {
        $items = [];
        if (trim((string)$str) === '') return $items;
        $entries = explode('|', $str);
        foreach ($entries as $entry) {
            $items[] = $this->_parseTS3KeyValues($entry);
        }
        return $items;
    }

    private function _readTS3Response($socket) {
        $data = '';
        $max_lines = 2000;
        $count = 0;
        while (!feof($socket) && $count++ < $max_lines) {
            $line = fgets($socket, 8192);
            if ($line === false) break;
            $trimmed = trim($line);
            if (strpos($trimmed, 'error id=') === 0) {
                break;
            }
            if ($trimmed !== '') {
                $data .= ($data !== '' ? ' ' : '') . $trimmed;
            }
        }
        return $data;
    }

    // Query TeamSpeak 3 Virtual Server
    private function _queryTS3Server($socket, $udpPort, $password = '') {
        // If password is provided, login as serveradmin for full access
        if (!empty($password)) {
            $user = 'serveradmin';
            $pass = $password;
            if (strpos($password, ':') !== false) {
                list($user, $pass) = explode(':', $password, 2);
            }
            fputs($socket, "login " . $user . " " . $this->_unescapeTS3($pass) . "\n");
            fgets($socket, 4096); // Read login response
        }

        // Select virtual server by UDP port
        fputs($socket, "use port=" . (int)$udpPort . "\n");
        $line = fgets($socket, 4096);
        if ($line === false || strpos(trim($line), 'error id=0') !== 0) {
            $this->error = 'Virtual server with UDP port ' . $udpPort . ' could not be selected.';
            return false;
        }

        // 1. Server info
        fputs($socket, "serverinfo\n");
        $sinfo_raw = $this->_readTS3Response($socket);
        $sinfo = $this->_parseTS3KeyValues($sinfo_raw);

        // 2. Channels with topics
        fputs($socket, "channellist -topic\n");
        $chan_raw = $this->_readTS3Response($socket);
        $raw_channels = $this->_parseTS3List($chan_raw);

        // 3. Players (with flags to retrieve Mute, Deaf, Away and times)
        fputs($socket, "clientlist -away -voice -times -uid\n");
        $client_raw = $this->_readTS3Response($socket);
        $raw_clients = $this->_parseTS3List($client_raw);

        $mapped_channels = [];
        foreach ($raw_channels as $ch) {
            $cid = (int)($ch['cid'] ?? 0);
            $mapped_channels[$cid] = [
                'channelid'   => $cid,
                'channelname' => (string)($ch['channel_name'] ?? 'Channel'),
                'topic'       => (string)($ch['channel_topic'] ?? ''),
                'parent'      => ((int)($ch['pid'] ?? 0) === 0) ? -1 : (int)($ch['pid'] ?? 0),
                'order'       => (int)($ch['channel_order'] ?? 0)
            ];
        }

        $mapped_players = [];
        foreach ($raw_clients as $cl) {
            // client_type: 0 = human player, 1 = ServerQuery bot
            if (isset($cl['client_type']) && (int)$cl['client_type'] === 0) {
                $pid = (int)($cl['clid'] ?? 0);
                $is_away = !empty($cl['client_away']);
                $is_muted = (!empty($cl['client_input_muted']) || !empty($cl['client_input_hardware_deactivated']));
                $is_deaf = (!empty($cl['client_output_muted']) || !empty($cl['client_output_hardware_deactivated']));
                $idle_time = (int)($cl['client_idle_time'] ?? 0);

                $mapped_players[$pid] = [
                    'playerid'     => $pid,
                    'channelid'    => (int)($cl['cid'] ?? 0),
                    'playername'   => (string)($cl['client_nickname'] ?? 'Player'),
                    'is_away'      => $is_away,
                    'away_message' => $is_away ? (string)($cl['client_away_message'] ?? 'Away') : '',
                    'is_muted'     => $is_muted,
                    'is_deaf'      => $is_deaf,
                    'is_cc'        => !empty($cl['client_is_channel_commander']),
                    'idle_time'    => $idle_time > 0 ? (int)round($idle_time / 1000) : 0,
                    'unique_id'    => (string)($cl['client_unique_identifier'] ?? '')
                ];
            }
        }

        return [
            'queryerror'  => 0,
            'serverinfo'  => [
                'server_name'     => (string)($sinfo['virtualserver_name'] ?? 'TeamSpeak Server'),
                'server_platform' => (string)($sinfo['virtualserver_platform'] ?? 'Linux'),
                'server_maxusers' => (int)($sinfo['virtualserver_maxclients'] ?? 32),
                'server_uptime'   => (int)($sinfo['virtualserver_uptime'] ?? 0),
                'server_version'  => (string)($sinfo['virtualserver_version'] ?? '3.x')
            ],
            'channellist' => $mapped_channels,
            'playerlist'  => $mapped_players
        ];
    }

    // --- LEGACY TS2 PROTOCOL METHODS ---
    function _stripPartFromString(&$evalString) {
        $evalString = (string)$evalString;
        $pos = strpos($evalString, "\t");
        if($pos !== false) {
            $result = substr($evalString, 0, $pos);
            $evalString = substr($evalString, $pos + 1);
        } else {
            $result = $evalString;
            $evalString = "";
        }
        return $result;
    }

    function _stripQuotes($evalString) {
        $evalString = (string)$evalString;
        $len = strlen($evalString);
        if ($len == 0) return $evalString;
        if(strpos($evalString, '"') === 0) $evalString = substr($evalString, 1, $len - 1);
        $len = strlen($evalString);
        if($len > 0 && strrpos($evalString, '"') === $len - 1) $evalString = substr($evalString, 0, $len - 1);
        return $evalString;
    }

    function _getServerInfoTS2($socket) {
        fputs($socket, "si\n");
        $result = array();
        $max_loops = 100;
        $i = 0;
        do {
            $line = fgets($socket, 4096);
            if ($line === false) break;
            $buffer = $this->_stripEOL($line);
            $is_ok = ($buffer === "OK");
            $is_error = (strtoupper(substr($buffer, 0, 5)) === "ERROR");
            if (!$is_ok && !$is_error) {
                $pos = strpos($buffer, '=');
                if ($pos !== False) {
                    $result[substr($buffer, 0, $pos)] = substr($buffer, $pos + 1);
                }
            }
            $i++;
        } while (!$is_ok && !$is_error && !feof($socket) && $i < $max_loops);
        return $result;
    }

    function _getPlayerListTS2($socket) {
        fputs($socket, "pl\n");
        $first_line = fgets($socket, 4096);
        $buffer = $this->_stripEOL($first_line !== false ? $first_line : "");
        $result = array();
        if (strtoupper(substr($buffer, 0, 5)) == "ERROR") { return $result; }
        $max_loops = 5000;
        $i = 0;
        do {
            $line = fgets($socket, 4096);
            if ($line === false) break;
            $buffer = $this->_stripEOL($line);
            $is_ok = ($buffer === "OK");
            $is_error = (strtoupper(substr($buffer, 0, 5)) === "ERROR");
            if (!$is_ok && !$is_error) {
                $playerid = $this->_stripPartFromString($buffer);
                $result[$playerid] = array(
                    "playerid" => $playerid,
                    "channelid" => $this->_stripPartFromString($buffer),
                    "playername" => $this->_stripQuotes($this->_stripPartFromString($buffer))
                );
            }
            $i++;
        } while (!$is_ok && !$is_error && !feof($socket) && $i < $max_loops);
        return $result;
    }

    function _getChannelListTS2($socket) {
        fputs($socket, "cl\n");
        $first_line = fgets($socket, 4096);
        $buffer = $this->_stripEOL($first_line !== false ? $first_line : "");
        $result = array();
        if (strtoupper(substr($buffer, 0, 5)) == "ERROR") { return $result; }
        $max_loops = 5000;
        $i = 0;
        do {
            $line = fgets($socket, 4096);
            if ($line === false) break;
            $buffer = $this->_stripEOL($line);
            $is_ok = ($buffer === "OK");
            $is_error = (strtoupper(substr($buffer, 0, 5)) === "ERROR");
            if (!$is_ok && !$is_error) {
                $channelid = $this->_stripPartFromString($buffer);
                $result[$channelid] = array(
                    "channelid" => $channelid,
                    "channelname" => $this->_stripQuotes($this->_stripPartFromString($buffer))
                );
            }
            $i++;
        } while (!$is_ok && !$is_error && !feof($socket) && $i < $max_loops);
        return $result;
    }

    function _selectServerTS2($socket, $port) {
        fputs($socket, "sel ".$port . "\n");
        $line = fgets($socket, 4096);
        return ($this->_stripEOL($line !== false ? $line : "") == "OK");
    }

    // ==========================================
    // --- MAIN DISPATCHER: TS6 -> TS3 -> TS2 ---
    // ==========================================
    function queryTeamspeakServerEx($settings) {
        $raw_host = trim((string)($settings["serveraddress"] ?? '127.0.0.1'));
        // Sanitize hostname: strip protocol prefixes (ts3server://, http://, ://) and trailing slashes
        $host = preg_replace('~^([a-z0-9_]+://|://)~i', '', $raw_host);
        $host = rtrim($host, '/');
        if (strpos($host, ':') !== false) {
            $parts = explode(':', $host, 2);
            $host = $parts[0];
        }

        $qport = (int)($settings["serverqueryport"] ?? 10011);
        $uport = (int)($settings["serverudpport"] ?? 9987);

        // Smart API key detection from password settings
        $raw_pass = trim((string)($settings["apikey"] ?? $settings["password"] ?? $settings["serverpassword"] ?? ''));
        $api_key = '';
        if (strpos($raw_pass, '|') !== false) {
            list(, $api_key) = explode('|', $raw_pass, 2);
        } elseif (strpos($raw_pass, 'apikey:') === 0) {
            $api_key = substr($raw_pass, 7);
        } elseif ($qport === 10080 || strlen($raw_pass) >= 30) {
            $api_key = $raw_pass;
        }
        $api_key = trim($api_key);

        // Cache: 90s for success, only 10s for errors (faster recovery while testing)
        $cache_dir = defined('TEMP_PATH') ? TEMP_PATH : sys_get_temp_dir();
        $cache_file = rtrim($cache_dir, '/\\') . '/ts_query_' . md5($host . '_' . $qport . '_' . $uport) . '.json';

        if (file_exists($cache_file)) {
            $age = time() - filemtime($cache_file);
            $cached_content = @file_get_contents($cache_file);
            if ($cached_content) {
                $decoded = json_decode($cached_content, true);
                if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                    $is_cached_err = isset($decoded['queryerror']) && (int)$decoded['queryerror'] !== 0;
                    if (($is_cached_err && $age < 10) || (!$is_cached_err && $age < 90)) {
                        return $decoded;
                    }
                }
            }
        }

        // 1. TRY TS6 HTTP REST WEBQUERY (if port 10080 or API key provided)
        if ($qport === 10080 || !empty($api_key)) {
            $ts6_data = $this->_queryTS6Http($host, $qport, $uport, $api_key);
            if ($ts6_data !== false) {
                @file_put_contents($cache_file, json_encode($ts6_data), LOCK_EX);
                return $ts6_data;
            }
        }

        $result = array();
        $socket = null;

        // Fast 1.5-second socket timeout
        if (! $this->_openConnection($socket, $host, $qport, 1.5)) {
            $result = ["queryerror" => 1, "error_msg" => $this->error];
            @file_put_contents($cache_file, json_encode($result), LOCK_EX);
            return $result;
        }

        // Branch by detected protocol
        if ($this->is_ts3) {
            $ts3_result = $this->_queryTS3Server($socket, $uport, $raw_pass);
            $this->_closeConnection($socket);
            if ($ts3_result === false) {
                $result = ["queryerror" => 2, "error_msg" => $this->error];
                @file_put_contents($cache_file, json_encode($result), LOCK_EX);
                return $result;
            }
            @file_put_contents($cache_file, json_encode($ts3_result), LOCK_EX);
            return $ts3_result;
        } else {
            // Legacy TS2
            if (! $this->_selectServerTS2($socket, $uport)) {
                $result["queryerror"] = 2;
                $this->_closeConnection($socket);
            } else {
                $result["queryerror"] = 0;
                $result["serverinfo"] = $this->_getServerInfoTS2($socket);
                $result["channellist"] = $this->_getChannelListTS2($socket);
                $result["playerlist"] = $this->_getPlayerListTS2($socket);
                $this->_closeConnection($socket);
            }
            @file_put_contents($cache_file, json_encode($result), LOCK_EX);
            return $result;
        }
    }

    function queryTeamspeakServer($serverAddress, $serverUDPPort, $serverQueryPort) {
        $settings = $this->getDefaultSettings();
        $settings["serveraddress"] = $serverAddress;
        $settings["serverudpport"] = $serverUDPPort;
        $settings["serverqueryport"] = $serverQueryPort;
        return $this->queryTeamspeakServerEx($settings);
    }

    function getDefaultSettings() {
        return [
            "serveraddress"   => "127.0.0.1",
            "serverudpport"   => 9987,
            "serverqueryport" => 10011,
            "apikey"          => "",
            "limitchannel"    => ""
        ];
    }
}

// Global instance
$teamspeakDisplay = new teamspeakDisplayClass;