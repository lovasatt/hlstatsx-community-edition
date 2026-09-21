<?php
/*
   HLstatsX Modernized Edition - Native Steam Community Group Engine
   Version: 1.0 (Hardened Cache, 30 RSS Items & Chat Support)
   Author:  lovasatt (2026)
*/

if (!defined('IN_HLSTATS')) {
    die('Do not access this file directly.');
}

#[\AllowDynamicProperties]
class CSteamGroupStatus
{
    public $m_name = '';
    public $m_group_id64 = '';
    public $m_group_url = '';
    public $m_headline = '';
    public $m_summary = '';
    public $m_avatar_icon = '';
    public $m_avatar_medium = '';
    public $m_avatar_full = '';
    public $m_members_count = 0;
    public $m_members_in_game = 0;
    public $m_members_online = 0;
    public $m_members_chatting = 0;
    public $m_announcements = [];
    public $m_error = '';

    private function fetchUrl($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_ENCODING       => "",
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; HLstatsX-SteamEngine/2.0)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($code === 200 && is_string($res) && $res !== '') {
            return $res;
        }
        return false;
    }

    private function sanitizeExcerpt($html, $max_len = 160)
    {
        // Remove BBCode style tags
        $clean = preg_replace('/\[[^\]]+\]/', '', (string)$html);
        // Strip HTML tags and entities
        $clean = strip_tags($clean);
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = preg_replace('/\s+/', ' ', $clean);
        $clean = trim($clean);

        if (mb_strlen($clean, 'UTF-8') > $max_len) {
            return mb_substr($clean, 0, $max_len, 'UTF-8') . '...';
        }
        return $clean;
    }

    public function Request($group_identifier)
    {
        $ident = trim((string)$group_identifier);
        if ($ident === '') {
            $this->m_error = 'Invalid Steam Group identifier.';
            return false;
        }

        // Determine base URL: numeric 64-bit ID or custom group name
        $is_gid64 = preg_match('/^[0-9]{17,20}$/', $ident);
        $base_url = $is_gid64 
            ? "https://steamcommunity.com/gid/{$ident}"
            : "https://steamcommunity.com/groups/" . urlencode($ident);

        // Cache engine: 15 mins (900s) for success, 120s for network errors
        $cache_dir = defined('TEMP_PATH') ? TEMP_PATH : sys_get_temp_dir();
        $cache_file = rtrim($cache_dir, '/\\') . '/steam_group_' . md5($ident) . '.json';
        $cached_data = null;

        if (file_exists($cache_file)) {
            $age = time() - filemtime($cache_file);
            $raw = @file_get_contents($cache_file);
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $cached_data = $decoded;
                    $is_err = !empty($decoded['__error']);
                    // If fresh cache, serve immediately
                    if ((!$is_err && $age < 900) || ($is_err && $age < 120)) {
                        $this->applyData($decoded);
                        return empty($this->m_error);
                    }
                }
            }
        }

        // Live queries: XML Details and RSS Announcements (p=1 limits member IDs to page 1, preventing multi-MB downloads on huge groups)
        $xml_url = $base_url . "/memberslistxml/?xml=1&p=1";
        $xml_raw = $this->fetchUrl($xml_url);

        $rss_url = $base_url . "/rss/";
        $rss_raw = $this->fetchUrl($rss_url);

        // Stale-While-Revalidate: If Steam is down, serve stale cache
        if (!$xml_raw && $cached_data && empty($cached_data['__error'])) {
            $this->applyData($cached_data);
            return true;
        }

        if (!$xml_raw) {
            $this->m_error = 'Steam Community is currently unreachable or group is private.';
            @file_put_contents($cache_file, json_encode(['__error' => $this->m_error]), LOCK_EX);
            return false;
        }

        // Safe XML parsing without memory leaks or PHP warnings
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_raw, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET | LIBXML_NOERROR);
        libxml_clear_errors();

        if (!$xml || !isset($xml->groupDetails)) {
            $this->m_error = 'Failed to parse Steam Group data.';
            @file_put_contents($cache_file, json_encode(['__error' => $this->m_error]), LOCK_EX);
            return false;
        }

        $gd = $xml->groupDetails;
        $this->m_name              = (string)($gd->groupName ?? 'Steam Community');
        $this->m_group_id64        = (string)($gd->groupID64 ?? $ident);
        $this->m_group_url         = (string)($gd->groupURL ?? $ident);
        $this->m_headline          = strip_tags((string)($gd->headline ?? ''));
        $this->m_summary           = strip_tags((string)($gd->summary ?? ''));
        $this->m_avatar_icon       = (string)($gd->avatarIcon ?? '');
        $this->m_avatar_medium     = (string)($gd->avatarMedium ?? '');
        $this->m_avatar_full       = (string)($gd->avatarFull ?? '');
        $this->m_members_count     = (int)($gd->memberCount ?? 0);
        $this->m_members_in_game   = (int)($gd->membersInGame ?? 0);
        $this->m_members_online    = (int)($gd->membersOnline ?? 0);
        $this->m_members_chatting  = (int)($gd->membersChatting ?? 0);

        // Parse RSS Announcements safely (fetch up to 30 items for pagination)
        $announcements = [];
        if ($rss_raw) {
            libxml_use_internal_errors(true);
            $rss = simplexml_load_string($rss_raw, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET | LIBXML_NOERROR);
            libxml_clear_errors();

            if ($rss && isset($rss->channel->item)) {
                $count = 0;
                foreach ($rss->channel->item as $item) {
                    if ($count++ >= 30) break;
                    $announcements[] = [
                        'title'   => (string)$item->title,
                        'link'    => (string)$item->link,
                        'pubDate' => (string)$item->pubDate,
                        'excerpt' => $this->sanitizeExcerpt((string)$item->description)
                    ];
                }
            }
        }
        $this->m_announcements = $announcements;

        // Save atomic cache
        $to_cache = [
            'name'             => $this->m_name,
            'group_id64'       => $this->m_group_id64,
            'group_url'        => $this->m_group_url,
            'headline'         => $this->m_headline,
            'summary'          => $this->m_summary,
            'avatar_icon'      => $this->m_avatar_icon,
            'avatar_medium'    => $this->m_avatar_medium,
            'avatar_full'      => $this->m_avatar_full,
            'members_count'    => $this->m_members_count,
            'members_in_game'  => $this->m_members_in_game,
            'members_online'   => $this->m_members_online,
            'members_chatting' => $this->m_members_chatting,
            'announcements'    => $this->m_announcements,
            '__error'          => ''
        ];
        @file_put_contents($cache_file, json_encode($to_cache), LOCK_EX);

        return true;
    }

    private function applyData(array $d)
    {
        $this->m_name             = $d['name'] ?? '';
        $this->m_group_id64       = $d['group_id64'] ?? '';
        $this->m_group_url        = $d['group_url'] ?? '';
        $this->m_headline         = $d['headline'] ?? '';
        $this->m_summary          = $d['summary'] ?? '';
        $this->m_avatar_icon      = $d['avatar_icon'] ?? '';
        $this->m_avatar_medium    = $d['avatar_medium'] ?? '';
        $this->m_avatar_full      = $d['avatar_full'] ?? '';
        $this->m_members_count    = (int)($d['members_count'] ?? 0);
        $this->m_members_in_game  = (int)($d['members_in_game'] ?? 0);
        $this->m_members_online   = (int)($d['members_online'] ?? 0);
        $this->m_members_chatting = (int)($d['members_chatting'] ?? 0);
        $this->m_announcements    = $d['announcements'] ?? [];
        $this->m_error            = $d['__error'] ?? '';
    }
}