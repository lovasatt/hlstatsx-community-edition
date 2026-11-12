<?php
/*
   HLstatsX Modernized Edition - MistServer Live Stream Engine
   Version: 1.0
   Project: https://github.com/lovasatt/hlstatsx-community-edition
   Author:  lovasatt (2026)
*/

if (!defined('IN_HLSTATS')) {
    die('Do not access this file directly.');
}

#[\AllowDynamicProperties]
class CMistStatus
{
    public $m_name = '';
    public $m_stream_name = '';
    public $m_host = '';
    public $m_port = 8080;
    public $m_scheme = '';
    public $m_is_online = false;
    public $m_status_text = 'Offline';
    public $m_viewers = 0;
    public $m_width = 0;
    public $m_height = 0;
    public $m_fps = 0;
    public $m_bitrate = 0;
    public $m_video_codec = '';
    public $m_audio_codec = '';
    public $m_error = '';

    public static function sanitizeHost($host)
    {
        $host = trim((string)$host);
        $host = preg_replace('~^[a-z][a-z0-9+.\-]*://~i', '', $host);
        $host = trim(explode('/', $host, 2)[0]);
        if ($host === '') {
            return '';
        }

        if ($host[0] === '[') {
            $end = strpos($host, ']');
            if ($end === false) {
                return '';
            }
            $inner = substr($host, 1, $end - 1);
            return filter_var($inner, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[' . $inner . ']' : '';
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return '[' . $host . ']';
        }

        if (strpos($host, ':') !== false) {
            $host = explode(':', $host, 2)[0];
        }

        return preg_match('/^[A-Za-z0-9]([A-Za-z0-9.\-]*[A-Za-z0-9])?$/', $host) ? $host : '';
    }

    public static function encodeStream($name)
    {
        return str_replace('%2B', '+', rawurlencode((string)$name));
    }

    public static function isTlsPort($port)
    {
        return in_array((int)$port, array(443, 8443, 2053, 2083, 2087, 2096), true);
    }

    public static function buildBaseUrl($scheme, $host, $port)
    {
        $scheme  = ($scheme === 'https') ? 'https' : 'http';
        $default = ($scheme === 'https') ? 443 : 80;
        $suffix  = ((int)$port === $default) ? '' : ':' . (int)$port;
        return $scheme . '://' . $host . $suffix;
    }

    private function fetchUrl($url, &$errno = 0, &$code = 0)
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 2,
            CURLOPT_ENCODING       => "",
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; HLstatsX-MistEngine/2.0)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ];

        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $opts[CURLOPT_PROTOCOLS_STR]       = 'http,https';
            $opts[CURLOPT_REDIR_PROTOCOLS_STR] = 'http,https';
        } else {
            $opts[CURLOPT_PROTOCOLS]       = CURLPROTO_HTTP | CURLPROTO_HTTPS;
            $opts[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }

        curl_setopt_array($ch, $opts);
        $res   = curl_exec($ch);
        $errno = curl_errno($ch);
        $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && is_string($res) && $res !== '') {
            return $res;
        }
        return false;
    }

    public function Request($host, $port, $stream_name)
    {
        $this->m_host        = self::sanitizeHost($host);
        $this->m_port        = ((int)$port > 0 && (int)$port <= 65535) ? (int)$port : 8080;
        $this->m_stream_name = trim((string)$stream_name);

        if ($this->m_host === '' || $this->m_stream_name === '') {
            $this->m_is_online   = false;
            $this->m_status_text = 'Misconfigured';
            $this->m_error       = 'Host or Stream Key is empty or invalid.';
            return false;
        }

        if (!function_exists('curl_init')) {
            $this->m_is_online   = false;
            $this->m_status_text = 'Unavailable';
            $this->m_error       = 'PHP cURL extension is not installed.';
            return false;
        }

        $cache_dir  = (defined('TEMP_PATH') && is_dir(TEMP_PATH) && is_writable(TEMP_PATH)) ? TEMP_PATH : sys_get_temp_dir();
        $cache_key  = md5("{$this->m_host}_{$this->m_port}_{$this->m_stream_name}");
        $cache_file = rtrim($cache_dir, '/\\') . '/mist_stream_' . $cache_key . '.json';

        $stale = null;
        if (is_file($cache_file)) {
            $raw = @file_get_contents($cache_file);
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $ttl = isset($decoded['ttl']) ? max(1, (int)$decoded['ttl']) : 8;
                    if ((time() - (int)@filemtime($cache_file)) < $ttl) {
                        $this->applyData($decoded);
                        return true;
                    }
                    $stale = $decoded;
                }
            }
        }

        $lock = @fopen($cache_file . '.lock', 'c');
        if ($lock && !@flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            $lock = null;
            if ($stale !== null) {
                $this->applyData($stale);
                return true;
            }
        }

        $first   = self::isTlsPort($this->m_port) ? 'https' : 'http';
        $second  = ($first === 'https') ? 'http' : 'https';
        $enc     = self::encodeStream($this->m_stream_name);

        $raw_response = false;
        foreach (array($first, $second) as $scheme) {
            $base  = self::buildBaseUrl($scheme, $this->m_host, $this->m_port);
            $errno = 0;
            $code  = 0;

            $raw_response = $this->fetchUrl("{$base}/json_{$enc}.js", $errno, $code);
            if ($raw_response === false && $errno === 0 && $code !== 400) {
                $raw_response = $this->fetchUrl("{$base}/info_{$enc}.js", $errno, $code);
            }

            if ($raw_response !== false) {
                $this->m_scheme = $scheme;
                break;
            }

            if (!($errno !== 0 || $code === 400 || $code === 0)) {
                break;
            }
        }

        if ($raw_response === false) {
            $this->m_is_online   = false;
            $this->m_status_text = 'Offline / Unreachable';
            $this->m_error       = 'Cannot connect to MistServer HTTP port.';
            $this->cacheCurrentState($cache_file, 20);
            return false;
        }

        $clean_json = trim($raw_response);
        $s_pos = strpos($clean_json, '{');
        $e_pos = strrpos($clean_json, '}');
        if ($s_pos !== false && $e_pos !== false && $e_pos > $s_pos) {
            $clean_json = substr($clean_json, $s_pos, ($e_pos - $s_pos + 1));
        }

        $data = json_decode($clean_json, true);
        if (!is_array($data)) {
            $this->m_is_online   = false;
            $this->m_status_text = 'Standby';
            $this->cacheCurrentState($cache_file, 8);
            return false;
        }

        $tracks = array();
        if (!empty($data['meta']['tracks']) && is_array($data['meta']['tracks'])) {
            $tracks = $data['meta']['tracks'];
        } elseif (!empty($data['tracks']) && is_array($data['tracks'])) {
            $tracks = $data['tracks'];
        }

        $has_av      = false;
        $best_area   = -1;
        $total_bps   = 0;
        $width = 0; $height = 0; $fps = 0; $vcodec = ''; $acodec = '';

        foreach ($tracks as $track) {
            if (!is_array($track)) {
                continue;
            }
            $ttype = (string)($track['type'] ?? '');
            if ($ttype === 'video') {
                $has_av = true;
                $tw   = (int)($track['width'] ?? 0);
                $th   = (int)($track['height'] ?? 0);
                $area = $tw * $th;
                $total_bps += (int)($track['bps'] ?? 0);
                if ($area > $best_area) {
                    $best_area = $area;
                    $width  = $tw;
                    $height = $th;
                    $fps    = !empty($track['fpks']) ? (int)round((int)$track['fpks'] / 1000) : 0;
                    $vcodec = !empty($track['codec']) ? strtoupper((string)$track['codec']) : '';
                }
            } elseif ($ttype === 'audio') {
                $has_av = true;
                $total_bps += (int)($track['bps'] ?? 0);
                if ($acodec === '' && !empty($track['codec'])) {
                    $acodec = strtoupper((string)$track['codec']);
                }
            }
        }

        if ($width <= 0 && !empty($data['width']))   $width  = (int)$data['width'];
        if ($height <= 0 && !empty($data['height'])) $height = (int)$data['height'];

        $has_error  = !empty($data['error']);
        $error_text = (isset($data['error']) && is_scalar($data['error'])) ? trim((string)$data['error']) : 'Stream error';
        $has_media  = $has_av || (empty($tracks) && !empty($data['source']));

        if (!$has_error && $has_media) {
            $this->m_is_online   = true;
            $this->m_status_text = 'Live';
        } else {
            $this->m_is_online   = false;
            $this->m_status_text = $has_error ? $error_text : 'Standby / Waiting for source';
        }

        $this->m_width       = $width;
        $this->m_height      = $height;
        $this->m_fps         = $fps;
        $this->m_bitrate     = (int)round($total_bps * 8 / 1024);
        $this->m_video_codec = $vcodec;
        $this->m_audio_codec = $acodec;

        $this->cacheCurrentState($cache_file, 8);
        return true;
    }

    private function cacheCurrentState($cache_file, $ttl = 8)
    {
        $to_cache = [
            'is_online'    => $this->m_is_online,
            'status_text'  => $this->m_status_text,
            'viewers'      => $this->m_viewers,
            'width'        => $this->m_width,
            'height'       => $this->m_height,
            'fps'          => $this->m_fps,
            'bitrate'      => $this->m_bitrate,
            'video_codec'  => $this->m_video_codec,
            'audio_codec'  => $this->m_audio_codec,
            'error'        => $this->m_error,
            'scheme'       => $this->m_scheme,
            'ttl'          => (int)$ttl
        ];
        $tmp = $cache_file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, json_encode($to_cache), LOCK_EX) !== false) {
            @rename($tmp, $cache_file);
        }
    }

    private function applyData(array $d)
    {
        $this->m_is_online   = !empty($d['is_online']);
        $this->m_status_text = (string)($d['status_text'] ?? 'Offline');
        $this->m_viewers     = (int)($d['viewers'] ?? 0);
        $this->m_width       = (int)($d['width'] ?? 0);
        $this->m_height      = (int)($d['height'] ?? 0);
        $this->m_fps         = (int)($d['fps'] ?? 0);
        $this->m_bitrate     = (int)($d['bitrate'] ?? 0);
        $this->m_video_codec = (string)($d['video_codec'] ?? '');
        $this->m_audio_codec = (string)($d['audio_codec'] ?? '');
        $this->m_error       = (string)($d['error'] ?? '');
        $this->m_scheme      = (string)($d['scheme'] ?? '');
    }
}