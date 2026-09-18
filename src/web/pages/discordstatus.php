<?php
/*
   HLstatsX Modernized Edition - Native Discord Widget API Integration
   Version: 1.0
   Project: https://github.com/lovasatt/hlstatsx-community-edition
   Author:  lovasatt (2026)
*/

if (!defined('IN_HLSTATS')) {
    die('Do not access this file directly.');
}

#[\AllowDynamicProperties]
class CDiscordStatus
{
    public $m_name = '';
    public $m_online = 0;
    public $m_channels = 0;
    public $m_invite = '';
    public $m_error = '';
    public $m_voice_tree = [];
    public $m_directory = [];

    private function sanitizeInviteUrl($url)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }

        // Extracts the invite code from any Discord URL format (with or without trailing slash, http/https, discord.gg or discord.com)
        if (preg_match('~(?:discord\.gg/|discord\.com/invite/)([A-Za-z0-9_.-]+)~i', $url, $m)) {
            return 'https://discord.gg/' . rtrim($m[1], '/');
        }

        // If the user pasted just the raw invite code (e.g. 'AbCdEf12' or 'clan-lounge')
        if (preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $url)) {
            return 'https://discord.gg/' . $url;
        }

        return '';
    }

    public function Request($guild_id, $fallback_invite = '')
    {
        $this->m_name = '';
        $this->m_online = 0;
        $this->m_channels = 0;
        $this->m_invite = $this->sanitizeInviteUrl($fallback_invite);
        $this->m_error = '';
        $this->m_voice_tree = [];
        $this->m_directory = [];

        $guild_id = trim((string)$guild_id);

        // Validate Discord snowflake ID format (17 to 25 digits)
        if (!preg_match('/^[0-9]{17,25}$/', $guild_id)) {
            $this->m_error = 'Invalid Discord Server ID (Guild ID).';
            return false;
        }

        // Cache engine: 90s for success, 15s for error responses to prevent API rate limits (HTTP 429)
        $cache_dir = defined('TEMP_PATH') ? TEMP_PATH : sys_get_temp_dir();
        $cache_file = rtrim($cache_dir, '/\\') . '/discord_widget_' . $guild_id . '.json';
        $json = null;

        if (file_exists($cache_file)) {
            $age = time() - filemtime($cache_file);
            $cached_raw = @file_get_contents($cache_file);
            if ($cached_raw) {
                $test = json_decode($cached_raw, true);
                if (is_array($test)) {
                    $is_cached_err = !empty($test['__hlx_error']);
                    if (($is_cached_err && $age < 15) || (!$is_cached_err && $age < 90)) {
                        if ($is_cached_err) {
                            $this->m_error = (string)$test['__hlx_error'];
                            return false;
                        }
                        $json = $cached_raw;
                    }
                }
            }
        }

        if (!$json) {
            $url = "https://discord.com/api/guilds/" . $guild_id . "/widget.json";

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 4,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => 'HLstatsX-CE-DiscordEngine/2.0',
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $json = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_err = curl_error($ch);
            $curl_errno = curl_errno($ch);
            curl_close($ch);

            if ($curl_errno !== 0) {
                $this->m_error = 'cURL Connection Error (' . $curl_errno . '): ' . $curl_err;
                @file_put_contents($cache_file, json_encode(['__hlx_error' => $this->m_error]), LOCK_EX);
                return false;
            }

            if ($code === 200 && !empty($json)) {
                $test_decode = json_decode($json, true);
                if (is_array($test_decode) && json_last_error() === JSON_ERROR_NONE) {
                    @file_put_contents($cache_file, $json, LOCK_EX);
                } else {
                    $this->m_error = 'Invalid data format received from Discord API.';
                    @file_put_contents($cache_file, json_encode(['__hlx_error' => $this->m_error]), LOCK_EX);
                    return false;
                }
            } else {
                if ($code === 429) {
                    $this->m_error = 'Discord API rate limit reached (HTTP 429). Please try again shortly.';
                } elseif ($code === 403 || $code === 404) {
                    $this->m_error = 'Discord widget is disabled in Discord Server Settings, or the Server ID is incorrect (HTTP ' . $code . ').';
                } else {
                    $this->m_error = 'Discord API returned unexpected HTTP status code: ' . $code;
                }
                @file_put_contents($cache_file, json_encode(['__hlx_error' => $this->m_error]), LOCK_EX);
                return false;
            }
        }

        $data = json_decode($json, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            @unlink($cache_file);
            $this->m_error = 'Failed to parse JSON response from Discord API.';
            return false;
        }

        $this->m_name   = (string)($data['name'] ?? 'Discord Community');
        $this->m_online = (int)($data['presence_count'] ?? count($data['members'] ?? []));

        $raw_channels = (array)($data['channels'] ?? []);
        $raw_members  = (array)($data['members'] ?? []);

        $this->m_channels = count($raw_channels);

        // Priority Invite Link Resolution:
        // 1. If admin specified an invite in HLstatsX password field, ALWAYS honor it.
        // 2. If left empty, automatically use the instant_invite provided by the Discord API widget.
        $custom_invite = $this->sanitizeInviteUrl($fallback_invite);
        $api_invite    = $this->sanitizeInviteUrl($data['instant_invite'] ?? '');

        if (!empty($custom_invite)) {
            $this->m_invite = $custom_invite;
        } elseif (!empty($api_invite)) {
            $this->m_invite = $api_invite;
        } else {
            $this->m_invite = '';
        }

        // Build channel index sorted by position
        $channel_map = [];
        $voice_channels_count = 0;

        foreach ($raw_channels as $ch) {
            $ch_type = isset($ch['type']) ? (int)$ch['type'] : 2; 

            if ($ch_type === 2) {
                $cid = (string)($ch['id'] ?? '');
                if ($cid !== '') {
                    $voice_channels_count++;
                    $channel_map[$cid] = [
                        'id'       => $cid,
                        'name'     => (string)($ch['name'] ?? 'Channel'),
                        'position' => (int)($ch['position'] ?? 0),
                        'users'    => []
                    ];
                }
            }
        }

      $this->m_channels = $voice_channels_count;

        // Separate members in voice channels from lobby/directory members
        foreach ($raw_members as $m) {
            $display_name = (string)($m['nick'] ?? $m['global_name'] ?? $m['username'] ?? 'User');

            $raw_status = strtolower((string)($m['status'] ?? 'online'));
            if ($raw_status === '') {
                $raw_status = 'online';
            }

            $user_entry = [
                'id'       => (string)($m['id'] ?? ''),
                'username' => $display_name,
                'status'   => $raw_status,
                'avatar'   => (string)($m['avatar_url'] ?? $m['avatar'] ?? ''),
                'activity' => (string)($m['game']['name'] ?? $m['activity']['name'] ?? $m['activities'][0]['name'] ?? ''),
                'is_muted' => (!empty($m['mute']) || !empty($m['self_mute'])),
                'is_deaf'  => (!empty($m['deaf']) || !empty($m['self_deaf'])),
                'is_bot'   => !empty($m['bot'])
            ];

            $cid = (string)($m['channel_id'] ?? '');
            if ($cid !== '' && isset($channel_map[$cid])) {
                $channel_map[$cid]['users'][] = $user_entry;
            } else {
                $this->m_directory[] = $user_entry;
            }
        }

        // Sort channels by position hierarchy
        usort($channel_map, function($a, $b) {
            return $a['position'] <=> $b['position'];
        });

        $this->m_voice_tree = $channel_map;

        return true;
    }
}
?>