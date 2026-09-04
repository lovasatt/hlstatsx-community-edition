<?php
/*
HLstatsX Community Edition - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
http://www.hlxcommunity.com

HLstatsX Community Edition is a continuation of 
ELstatsNEO - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Malte Bayer (steam@neo-soft.org)
http://ovrsized.neo-soft.org/

ELstatsNEO is an very improved & enhanced - so called Ultra-Humongus Edition of HLstatsX
HLstatsX - Real-time player and clan rankings and statistics for Half-Life 2
http://www.hlstatsx.com/
Copyright (C) 2005-2007 Tobias Oetzel (Tobi@hlstatsx.com)

HLstatsX is an enhanced version of HLstats made by Simon Garner
HLstats - Real-time player and clan rankings and statistics for Half-Life
http://sourceforge.net/projects/hlstats/
Copyright (C) 2001  Simon Garner

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

For support and installation notes visit http://www.hlxcommunity.com
*/

function printMap($type = 'main')
{
    global $db, $game, $g_options, $clandata, $clan;

    $game = isset($game) ? (string)$game : '';
    $image_path = htmlspecialchars((string)IMAGE_PATH, ENT_QUOTES, 'UTF-8');
?> 
    <style type="text/css">
        .leaflet-popup-content-wrapper { border-radius: 4px; box-shadow: 0 3px 14px rgba(0,0,0,0.4); }
        .leaflet-popup-content { color: #000; font-size: 11px; margin: 8px 12px; line-height: 1.4; }
        .gmapstab { width: 100%; border-collapse: collapse; }
        .gmapstab td { padding: 2px 4px; }
        .gmapstabtitle { font-weight: bold; border-bottom: 1px solid #333; margin-bottom: 4px; text-align: left; }
    </style>

    <script type="text/javascript">
    /* <![CDATA[ */
    (function() {
        function initLeafletMap() {
            var mapElement = document.getElementById("map");
            if (!mapElement || typeof L === "undefined") return;

            if (mapElement._leaflet_id) {
                mapElement._leaflet_id = null;
            }

            // Add the preloads here...so that they don't get loaded after the graphs load
            function preloadImages() {
                var d = document;
                if (d.images) {
                    if (!d.p) d.p = [];
                    var i, j = d.p.length, a = preloadImages.arguments;
                    for (i = 0; i < a.length; i++) {
                        if (a[i].indexOf("#") !== 0) {
                            d.p[j] = new Image;
                            d.p[j++].src = a[i];
                        }
                    }
                }
            }
            <?php echo "preloadImages('" . IMAGE_PATH . "/mm_20_blue.png', " . (($type == 'main') ? "'" . IMAGE_PATH . "/mm_20_red.png', " : '') . "'" . IMAGE_PATH . "/mm_20_shadow.png');\n"; ?>

            // Custom Leaflet icons using HLstatsX marker images
            var LeafIcon = L.Icon.extend({
                options: {
                    shadowUrl: '<?php echo $image_path; ?>/mm_20_shadow.png',
                    iconSize:     [12, 20],
                    shadowSize:   [22, 20],
                    iconAnchor:   [6, 20],
                    shadowAnchor: [6, 20],
                    popupAnchor:  [0, -20]
                }
            });

            var point_icon = new LeafIcon({iconUrl: '<?php echo $image_path; ?>/mm_20_blue.png'});
            var point_icon_red = new LeafIcon({iconUrl: '<?php echo $image_path; ?>/mm_20_red.png'});

<?php
            // this creates mapLatLng and mapZoom
            printMapCenter(($type == 'clan' && !empty($clandata['mapregion'])) ? $clandata['mapregion'] : ($g_options['google_map_region'] ?? 'NORTH AMERICA'));

            // this creates mapType based on options.php
            printMapType($g_options['google_map_type'] ?? 'HYBRID');
?>
            // Tile Layer definitions (Free / Open source tile providers)
            // 1. Normal (OpenStreetMap)
            var layerMap = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">OpenStreetMap</a> contributors'
            });

            // 2. Satellite (Esri World Imagery)
            var layerSatellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19,
                attribution: 'Tiles &copy; <a href="https://www.esri.com" target="_blank" rel="noreferrer">Esri</a>'
            });

            // 3. Physical (OpenTopoMap + OSM adatok)
            var layerTerrain = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
                maxZoom: 17,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">OpenStreetMap</a>, <a href="https://opentopomap.org" target="_blank" rel="noreferrer">OpenTopoMap</a>'
            });

            // 4. Hybrid (Esri műhold + Határok és OpenStreetMap feliratok)
            var layerHybrid = L.layerGroup([
                L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    maxZoom: 19,
                    attribution: 'Tiles &copy; <a href="https://www.esri.com" target="_blank" rel="noreferrer">Esri</a>, <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">OpenStreetMap</a>'
                }),
                L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
                    maxZoom: 19
                })
            ]);

            // Set initial active base layer configured in options.php
            var activeBaseLayer = layerHybrid;
            if (typeof mapType !== 'undefined') {
                if (mapType === 'MAP') {
                    activeBaseLayer = layerMap;
                } else if (mapType === 'SATELLITE') {
                    activeBaseLayer = layerSatellite;
                } else if (mapType === 'PHYSICAL') {
                    activeBaseLayer = layerTerrain;
                } else if (mapType === 'HYBRID') {
                    activeBaseLayer = layerHybrid;
                }
            }

            // Initialize map
            var map = L.map('map', {
                center: mapLatLng,
                zoom: mapZoom,
                scrollWheelZoom: false,
                layers: [activeBaseLayer]
            });

            // Map type dropdown controller matching Google Maps choices
            var baseMaps = {
                "Hybrid": layerHybrid,
                "Normal": layerMap,
                "Satellite": layerSatellite,
                "Physical": layerTerrain
            };
            L.control.layers(baseMaps, null, { position: 'topright', collapsed: true }).addTo(map);

            setTimeout(function() { map.invalidateSize(); }, 250);

            function createMarker(point, city, country, player_info) {
                var html_text = '<table class="gmapstab"><tr><td colspan="2" class="gmapstabtitle" style="border-bottom:1px solid black;">' + city + ', ' + country + '</td></tr>';
                for (var i = 0; i < player_info.length; i++) {
                    html_text += '<tr><td><a href="hlstats.php?mode=playerinfo&amp;player=' + player_info[i][0] + '">' + player_info[i][1] + '</a></td></tr>';
                    html_text += '<tr><td>Kills/Deaths</td><td>' + player_info[i][2] + ':' + player_info[i][3] + '</td></tr>';
<?php
                    if ($type == 'main') {
                        echo "html_text += '<tr><td>Time</td><td>' + player_info[i][4] + '</td></tr>';";
                    } 
?>
                }
                html_text += '</table>';
                L.marker(point, {icon: point_icon}).addTo(map).bindPopup(html_text);
            }

<?php
            if ($type == 'main') {
?>
            function createMarkerS(point, servers, city, country, kills) {
                var html_text = '<table class="gmapstab"><tr><td colspan="2" class="gmapstabtitle" style="border-bottom:1px solid black;">' + city + ', ' + country + '</td></tr>';
                for (var i = 0; i < servers.length; i++) {
                    html_text += '<tr><td><a href="hlstats.php?mode=servers&amp;server_id=' + servers[i][0] + '&amp;game=<?php echo htmlspecialchars($game, ENT_QUOTES, 'UTF-8'); ?>">' + servers[i][2] + '</a></td></tr>';
                    html_text += '<tr><td>' + servers[i][1] + ' (<a href="steam://connect/' + servers[i][1] + '">connect</a>)</td></tr>';
                }
                html_text += '<tr><td>' + kills + ' kills</td></tr></table>';
                L.marker(point, {icon: point_icon_red}).addTo(map).bindPopup(html_text);
            }
<?php
                $game_esc = $db->escape($game);
                $db->query("SELECT serverId, IF(publicaddress != '', publicaddress, CONCAT(address, ':', port)) AS addr, name, kills, lat, lng, city, country FROM hlstats_Servers WHERE game='$game_esc' AND lat IS NOT NULL AND lng IS NOT NULL");

                $servers = array();
                while ($row = $db->fetch_array()) {
                    // Skip this part, if we already have the location info (should be the same)
                    $key = $row['lat'] . ',' . $row['lng'];
                    if (!isset($servers[$key])) {
                        $servers[$key] = array('lat' => $row['lat'], 'lng' => $row['lng'], 'addr' => $row['addr'], 'city' => $row['city'], 'country' => $row['country'], 'servers' => array());
                    }
                    $servers[$key]['servers'][] = array('serverId' => $row['serverId'], 'addr' => $row['addr'], 'name' => $row['name'], 'kills' => $row['kills']);
                }

                foreach ($servers as $map_location) {
                    $kills = 0;
                    $servers_js = array();
                    foreach ($map_location['servers'] as $server) {
                        $search_pattern = array("/[^A-Za-z0-9\[\]*.,=()!\"$%&^`ґ':;ЯІі#+~_\-|<>\/@{}дцьДЦЬ ]/");
                        $replace_pattern = array("");
                        $server['name'] = preg_replace($search_pattern, $replace_pattern, (string)$server['name']);
                        $temp = "[" . (int)$server['serverId'] . ",";
                        $temp .= "'" . htmlspecialchars(urldecode(preg_replace($search_pattern, $replace_pattern, (string)$server['addr'])), ENT_QUOTES, 'UTF-8') . "',";
                        $temp .= "'" . htmlspecialchars(urldecode((string)$server['name']), ENT_QUOTES, 'UTF-8') . "']";
                        $servers_js[] = $temp;
                        $kills += (int)$server['kills'];
                    }
                    echo 'createMarkerS([' . (float)$map_location['lat'] . ', ' . (float)$map_location['lng'] . '], [' . implode(',', $servers_js) . '], "' . htmlspecialchars(urldecode((string)$map_location['city']), ENT_QUOTES, 'UTF-8') . '", "' . htmlspecialchars(urldecode((string)$map_location['country']), ENT_QUOTES, 'UTF-8') . '", ' . $kills . ");\n";
                }

                $db->query("SELECT 
                            hlstats_Livestats.* 
                        FROM 
                            hlstats_Livestats
                        INNER JOIN
                            hlstats_Servers 
                            ON (hlstats_Servers.serverId=hlstats_Livestats.server_id)
                        WHERE 
                            hlstats_Livestats.cli_lat IS NOT NULL 
                            AND hlstats_Livestats.cli_lng IS NOT NULL
                            AND hlstats_Servers.game='$game_esc'");

                $players = array();
                while ($row = $db->fetch_array()) {
                    // Skip this part, if we already have the location info (should be the same)
                    $key = $row['cli_lat'] . ',' . $row['cli_lng'];
                    if (!isset($players[$key])) {
                        $players[$key] = array('cli_lat' => $row['cli_lat'], 'cli_lng' => $row['cli_lng'], 'cli_city' => $row['cli_city'], 'cli_country' => $row['cli_country'], 'players' => array());
                    }
                    $search_pattern = array("/[^A-Za-z0-9\[\]*.,=()!\"$%&^`ґ':;ЯІі#+~_\-|<>\/@{}дцьДЦЬ ]/");
                    $replace_pattern = array("");
                    $row['name'] = preg_replace($search_pattern, $replace_pattern, (string)$row['name']);

                    $players[$key]['players'][] = array('playerId' => $row['player_id'], 'name' => $row['name'], 'kills' => $row['kills'], 'deaths' => $row['deaths'], 'connected' => $row['connected']);
                }

                foreach ($players as $map_location) {
                    $players_js = array();
                    foreach ($map_location['players'] as $player) {
                        $connected_ts = (int)($player['connected'] ?? 0);
                        $stamp = max(0, time() - $connected_ts);
                        $hours = sprintf("%02d", floor($stamp / 3600));
                        $min = sprintf("%02d", floor(($stamp % 3600) / 60));
                        $sec = sprintf("%02d", floor($stamp % 60));
                        $time_str = $hours . ":" . $min . ":" . $sec;

                        $search_pattern = array("/[^A-Za-z0-9\[\]*.,=()!\"$%&^`ґ':;ЯІі#+~_\-|<>\/@{}дцьДЦЬ ]/");
                        $replace_pattern = array("");

                        $temp = "[" . (int)$player['playerId'] . ",";
                        $temp .= "'" . htmlspecialchars(urldecode(preg_replace($search_pattern, $replace_pattern, (string)$player['name'])), ENT_QUOTES, 'UTF-8') . "',";
                        $temp .= (int)$player['kills'] . ",";
                        $temp .= (int)$player['deaths'] . ",";
                        $temp .= "'" . $time_str . "']";
                        $players_js[] = $temp;
                    }

                    echo "createMarker([" . (float)$map_location['cli_lat'] . ", " . (float)$map_location['cli_lng'] . '], "' . htmlspecialchars(urldecode((string)$map_location['cli_city']), ENT_QUOTES, 'UTF-8') . '", "' . htmlspecialchars(urldecode((string)$map_location['cli_country']), ENT_QUOTES, 'UTF-8') . '", [' . implode(',', $players_js) . "]);\n";
                }
            } else if ($type == 'clan') {
                $clan_id = (int)$clan;
                $db->query("
                    SELECT
                        playerId,
                        lastName,
                        country,
                        skill,
                        kills,
                        deaths,
                        lat,
                        lng,
                        city
                    FROM
                        hlstats_Players
                    WHERE
                        clan=$clan_id
                        AND hlstats_Players.hideranking = 0
                        AND lat IS NOT NULL
                        AND lng IS NOT NULL
                    GROUP BY
                        hlstats_Players.playerId,
                        hlstats_Players.lastName,
                        hlstats_Players.country,
                        hlstats_Players.skill,
                        hlstats_Players.kills,
                        hlstats_Players.deaths,
                        hlstats_Players.lat,
                        hlstats_Players.lng,
                        hlstats_Players.city
                ");

                $players = array();
                while ($row = $db->fetch_array()) {
                    // Skip this part, if we already have the location info (should be the same)
                    $key = $row['lat'] . ',' . $row['lng'];
                    if (!isset($players[$key])) {
                        $players[$key] = array('lat' => $row['lat'], 'lng' => $row['lng'], 'city' => $row['city'], 'country' => $row['country'], 'players' => array());
                    }
                    $search_pattern = array("/[^A-Za-z0-9\[\]*.,=()!\"$%&^`ґ':;ЯІі#+~_\-|<>\/@{}дцьДЦЬ ]/");
                    $replace_pattern = array("");
                    $row['lastName'] = preg_replace($search_pattern, $replace_pattern, (string)$row['lastName']);

                    $players[$key]['players'][] = array(
                        'playerId' => $row['playerId'],
                        'name' => $row['lastName'],
                        'kills' => $row['kills'],
                        'deaths' => $row['deaths']
                    );
                }

                foreach ($players as $location) {
                    $players_js = array();
                    foreach ($location['players'] as $player) {
                        $search_pattern = array("/[^A-Za-z0-9\[\]*.,=()!\"$%&^`ґ':;ЯІі#+~_\-|<>\/@{}дцьДЦЬ ]/");
                        $replace_pattern = array("");
                        $temp = "[" . (int)$player['playerId'] . ",";
                        $temp .= "'" . htmlspecialchars(urldecode(preg_replace($search_pattern, $replace_pattern, (string)$player['name'])), ENT_QUOTES, 'UTF-8') . "',";
                        $temp .= (int)$player['kills'] . ",";
                        $temp .= (int)$player['deaths'] . "]";
                        $players_js[] = $temp;
                    }

                    echo "createMarker([" . (float)$location['lat'] . ", " . (float)$location['lng'] . '], "' . htmlspecialchars(urldecode((string)$location['city']), ENT_QUOTES, 'UTF-8') . '", "' . htmlspecialchars(urldecode((string)$location['country']), ENT_QUOTES, 'UTF-8') . '", [' . implode(',', $players_js) . "]);\n";
                }
            }
?>
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", initLeafletMap);
        } else {
            initLeafletMap();
        }
    })();
    /* ]]> */
    </script>
<?php
}

function printMapCenter($country)
{
    global $db;

    $country_code = strtoupper(trim((string)$country));
    if ($country_code === '') {
        $country_code = 'NORTH AMERICA';
    }

    $found = false;

    // --- LEVEL 1: Safe database check (SHOW TABLES never dies/errors on non-existent tables) ---
    if (isset($db)) {
        $check_table = $db->query("SHOW TABLES LIKE 'hlstats_Map_Regions'");
        if ($check_table && $db->num_rows($check_table) > 0) {
            $country_esc = $db->escape($country_code);
            $result = $db->query("SELECT lat, lng, zoom FROM `hlstats_Map_Regions` WHERE `code`='$country_esc' LIMIT 1");
            if ($result && $row = $db->fetch_array($result)) {
                echo "var mapLatLng = [" . (float)$row['lat'] . ", " . (float)$row['lng'] . "];\nvar mapZoom = " . (int)$row['zoom'] . ";";
                $found = true;
            }
        }
    }

    // --- LEVEL 2: Static fallback dictionary (matches the 35 install.sql regions) ---
    if (!$found) {
        $defaults = array(
            'EUROPE'         => array(48.8000, 8.5000, 3),
            'NORTH AMERICA'  => array(45.0000, -97.0000, 3),
            'SOUTH AMERICA'  => array(-14.8000, -61.2000, 3),
            'NORTH AFRICA'   => array(25.4000, 8.4000, 4),
            'SOUTH AFRICA'   => array(-29.0000, 23.7000, 5),
            'NORTH EUROPE'   => array(62.6000, 15.4000, 4),
            'EAST EUROPE'    => array(51.9000, 31.8000, 4),
            'CANADA'         => array(60.0000, -97.0000, 3),
            'GERMANY'        => array(51.1000, 10.1000, 5),
            'FRANCE'         => array(47.2000, 2.4000, 5),
            'SPAIN'          => array(40.3000, -4.0000, 5),
            'UNITED KINGDOM' => array(54.0000, -4.3000, 5),
            'DENMARK'        => array(56.1000, 9.2000, 6),
            'SWEDEN'         => array(63.2000, 16.3000, 4),
            'NORWAY'         => array(65.6000, 13.1000, 4),
            'FINLAND'        => array(65.1000, 26.6000, 4),
            'NETHERLANDS'    => array(52.3000, 5.4000, 7),
            'BELGIUM'        => array(50.7000, 4.5000, 7),
            'POLAND'         => array(52.1000, 19.3000, 6),
            'SUISSE'         => array(46.8000, 8.2000, 7),
            'AUSTRIA'        => array(47.7000, 14.1000, 7),
            'ITALY'          => array(42.6000, 12.7000, 5),
            'TURKEY'         => array(39.0000, 34.9000, 6),
            'ROMANIA'        => array(45.9400, 24.9600, 6),
            'HUNGARY'        => array(47.1600, 19.5000, 7),
            'BRAZIL'         => array(-12.0000, -53.1000, 4),
            'ARGENTINA'      => array(-34.3000, -65.7000, 3),
            'RUSSIA'         => array(65.7000, 98.8000, 3),
            'ASIA'           => array(20.4000, 95.6000, 3),
            'CHINA'          => array(36.2000, 104.0000, 4),
            'JAPAN'          => array(36.2000, 136.8000, 5),
            'SOUTH KOREA'    => array(36.6000, 127.8000, 6),
            'TAIWAN'         => array(23.6000, 121.0000, 7),
            'AUSTRALIA'      => array(-26.1000, 134.8000, 4),
            'WORLD'          => array(25.0000, 8.5000, 2)
        );

        if (isset($defaults[$country_code])) {
            $d = $defaults[$country_code];
            echo "var mapLatLng = [" . $d[0] . ", " . $d[1] . "];\nvar mapZoom = " . $d[2] . ";";
        } else {
            // --- LEVEL 3: Ultimate default fallback matching install.sql (NORTH AMERICA) ---
            echo "var mapLatLng = [45.0000, -97.0000];\nvar mapZoom = 3;";
        }
    }
    echo "\n";
}
function printMapType($maptype)
{
    $maptype = strtoupper((string)$maptype);
    switch ($maptype)
    {
        case 'SATELLITE':
            echo "var mapType = 'SATELLITE';";
            break;
        case 'MAP':
            echo "var mapType = 'MAP';";
            break;
        case 'PHYSICAL':
            echo "var mapType = 'PHYSICAL';";
            break;
        case 'HYBRID':
        default:
            echo "var mapType = 'HYBRID';";
            break;
    }
    echo "\n";
}
?>