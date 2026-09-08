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
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

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
        return false;
    }

    function _closeConnection($socket) {
        if ($socket) {
            @fputs($socket, "quit\n");
            @fclose($socket);
        }
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
        $max_lines = 1000;
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
    private function _queryTS3Server($socket, $udpPort) {
        // Select virtual server by UDP port
        fputs($socket, "use port=" . (int)$udpPort . "\n");
        $line = fgets($socket, 4096);
        if ($line === false || strpos(trim($line), 'error id=0') !== 0) {
            return false;
        }

        // 1. Server info
        fputs($socket, "serverinfo\n");
        $sinfo_raw = $this->_readTS3Response($socket);
        $sinfo = $this->_parseTS3KeyValues($sinfo_raw);

        // 2. Channels
        fputs($socket, "channellist\n");
        $chan_raw = $this->_readTS3Response($socket);
        $raw_channels = $this->_parseTS3List($chan_raw);

        // 3. Players (filter out ServerQuery bots)
        fputs($socket, "clientlist\n");
        $client_raw = $this->_readTS3Response($socket);
        $raw_clients = $this->_parseTS3List($client_raw);

        $mapped_channels = [];
        foreach ($raw_channels as $ch) {
            $cid = $ch['cid'] ?? 0;
            $mapped_channels[$cid] = [
                'channelid'   => $cid,
                'channelname' => $ch['channel_name'] ?? 'Channel',
                'parent'      => (int)($ch['pid'] ?? 0) === 0 ? -1 : (int)($ch['pid'] ?? 0)
            ];
        }

        $mapped_players = [];
        foreach ($raw_clients as $cl) {
            // client_type: 0 = human player, 1 = ServerQuery bot
            if (isset($cl['client_type']) && (int)$cl['client_type'] === 0) {
                $pid = $cl['clid'] ?? 0;
                $mapped_players[$pid] = [
                    'playerid'   => $pid,
                    'channelid'  => $cl['cid'] ?? 0,
                    'playername' => $cl['client_nickname'] ?? 'Player'
                ];
            }
        }

        return [
            'queryerror'  => 0,
            'serverinfo'  => [
                'server_name'     => $sinfo['virtualserver_name'] ?? 'TeamSpeak 3 Server',
                'server_maxusers' => (int)($sinfo['virtualserver_maxclients'] ?? 32),
                'server_uptime'   => (int)($sinfo['virtualserver_uptime'] ?? 0)
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

    // MAIN QUERY DISPATCHER (TS2 + TS3)
    function queryTeamspeakServerEx($settings) {
        $result = array();
        $socket = null;

        // Try connect (2 sec timeout)
        if (! $this->_openConnection($socket, $settings["serveraddress"], $settings["serverqueryport"], 2.0)) {
            $result["queryerror"] = 1;
            return $result;
        }

        // Branch by detected protocol
        if ($this->is_ts3) {
            $ts3_result = $this->_queryTS3Server($socket, $settings["serverudpport"]);
            $this->_closeConnection($socket);
            if ($ts3_result === false) {
                $result["queryerror"] = 2;
                return $result;
            }
            return $ts3_result;
        } else {
            // Legacy TS2
            if (! $this->_selectServerTS2($socket, $settings["serverudpport"])) {
                $result["queryerror"] = 2;
                $this->_closeConnection($socket);
            } else {
                $result["queryerror"] = 0;
                $result["serverinfo"] = $this->_getServerInfoTS2($socket);
                $result["channellist"] = $this->_getChannelListTS2($socket);
                $result["playerlist"] = $this->_getPlayerListTS2($socket);
                $this->_closeConnection($socket);
            }
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
            "serveraddress"   => "",
            "serverudpport"   => 9987,
            "serverqueryport" => 10011,
            "limitchannel"    => ""
        ];
    }
}

// Global instance
$teamspeakDisplay = new teamspeakDisplayClass;