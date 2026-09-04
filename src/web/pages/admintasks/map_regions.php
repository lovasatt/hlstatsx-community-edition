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

    if (!defined('IN_HLSTATS')) {
        die('Do not access this file directly.');
    }

    global $db, $auth;

    // PHP 8 Fix: Null coalescing check
    if (($auth->userdata["acclevel"] ?? 0) < 80) {
        die ("Access denied!");
    }

    // --- SELF-HEALING: Auto-create and populate table if missing ---
    $check_table = $db->query("SHOW TABLES LIKE 'hlstats_Map_Regions'");
    if ($db->num_rows($check_table) == 0)
    {
        $db->query("
            CREATE TABLE IF NOT EXISTS `hlstats_Map_Regions` (
              `region_id` int(10) unsigned NOT NULL auto_increment,
              `code` varchar(64) NOT NULL,
              `name` varchar(128) NOT NULL,
              `lat` decimal(8,4) NOT NULL default '0.0000',
              `lng` decimal(8,4) NOT NULL default '0.0000',
              `zoom` tinyint(2) unsigned NOT NULL default '4',
              PRIMARY KEY (`region_id`),
              UNIQUE KEY `code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $db->query("
            INSERT INTO `hlstats_Map_Regions` (`code`, `name`, `lat`, `lng`, `zoom`) VALUES
            ('EUROPE', 'Europe', 48.8000, 8.5000, 3),
            ('NORTH AMERICA', 'North America', 45.0000, -97.0000, 3),
            ('SOUTH AMERICA', 'South America', -14.8000, -61.2000, 3),
            ('NORTH AFRICA', 'North Africa', 25.4000, 8.4000, 4),
            ('SOUTH AFRICA', 'South Africa', -29.0000, 23.7000, 5),
            ('NORTH EUROPE', 'North Europe', 62.6000, 15.4000, 4),
            ('EAST EUROPE', 'East Europe', 51.9000, 31.8000, 4),
            ('CANADA', 'Canada', 60.0000, -97.0000, 3),
            ('GERMANY', 'Germany', 51.1000, 10.1000, 5),
            ('FRANCE', 'France', 47.2000, 2.4000, 5),
            ('SPAIN', 'Spain', 40.3000, -4.0000, 5),
            ('UNITED KINGDOM', 'United Kingdom', 54.0000, -4.3000, 5),
            ('DENMARK', 'Denmark', 56.1000, 9.2000, 6),
            ('SWEDEN', 'Sweden', 63.2000, 16.3000, 4),
            ('NORWAY', 'Norway', 65.6000, 13.1000, 4),
            ('FINLAND', 'Finland', 65.1000, 26.6000, 4),
            ('NETHERLANDS', 'Netherlands', 52.3000, 5.4000, 7),
            ('BELGIUM', 'Belgium', 50.7000, 4.5000, 7),
            ('POLAND', 'Poland', 52.1000, 19.3000, 6),
            ('SUISSE', 'Suisse', 46.8000, 8.2000, 7),
            ('AUSTRIA', 'Austria', 47.7000, 14.1000, 7),
            ('ITALY', 'Italy', 42.6000, 12.7000, 5),
            ('TURKEY', 'Turkey', 39.0000, 34.9000, 6),
            ('ROMANIA', 'Romania', 45.9400, 24.9600, 6),
            ('HUNGARY', 'Hungary', 47.1600, 19.5000, 7),
            ('BRAZIL', 'Brazil', -12.0000, -53.1000, 4),
            ('ARGENTINA', 'Argentina', -34.3000, -65.7000, 3),
            ('RUSSIA', 'Russia', 65.7000, 98.8000, 3),
            ('ASIA', 'Asia', 20.4000, 95.6000, 3),
            ('CHINA', 'China', 36.2000, 104.0000, 4),
            ('JAPAN', 'Japan', 36.2000, 136.8000, 5),
            ('SOUTH KOREA', 'South Korea', 36.6000, 127.8000, 6),
            ('TAIWAN', 'Taiwan', 23.6000, 121.0000, 7),
            ('AUSTRALIA', 'Australia', -26.1000, 134.8000, 4),
            ('WORLD', 'World', 25.0000, 8.5000, 2);
        ");

        message('warning', 'Notice: Database table `hlstats_Map_Regions` was missing and has been automatically created with standard default regions.');
    }
    // --- END SELF-HEALING ---

    $edlist = new EditList("region_id", "hlstats_Map_Regions", "server", false);
    $edlist->columns[] = new EditListColumn("code", "Region Code", 25, true, "text", "", 64);
    $edlist->columns[] = new EditListColumn("name", "Display Name", 25, true, "text", "", 128);
    $edlist->columns[] = new EditListColumn("lat", "Latitude", 15, true, "text", "", 15);
    $edlist->columns[] = new EditListColumn("lng", "Longitude", 15, true, "text", "", 15);
    $edlist->columns[] = new EditListColumn("zoom", "Zoom Level", 10, true, "text", "", 2);

    if (!empty($_POST))
    {
        $validation_error = '';
        $seen_codes       = array();

        // 1. Validate NEW region row
        $new_code = trim((string)($_POST['new_code'] ?? ''));
        $new_name = trim((string)($_POST['new_name'] ?? ''));
        $new_lat  = trim((string)($_POST['new_lat'] ?? ''));
        $new_lng  = trim((string)($_POST['new_lng'] ?? ''));
        $new_zoom = trim((string)($_POST['new_zoom'] ?? ''));

        // If any field of the new row was filled in, require all fields to be valid
        if ($new_code !== '' || $new_name !== '' || $new_lat !== '' || $new_lng !== '') {
            if ($new_code === '') {
                $validation_error = "Region Code is required when adding a new region.";
            } elseif ($new_name === '') {
                $validation_error = "Display Name is required when adding a new region.";
            } elseif ($new_lat === '' || !is_numeric($new_lat) || (float)$new_lat < -90 || (float)$new_lat > 90) {
                $validation_error = "Latitude must be a valid number between -90 and +90 degrees.";
            } elseif ($new_lng === '' || !is_numeric($new_lng) || (float)$new_lng < -180 || (float)$new_lng > 180) {
                $validation_error = "Longitude must be a valid number between -180 and +180 degrees.";
            } elseif ($new_zoom === '' || !ctype_digit($new_zoom) || (int)$new_zoom < 0 || (int)$new_zoom > 20) {
                $validation_error = "Zoom Level must be an integer between 0 and 20.";
            } else {
                $seen_codes[] = strtoupper($new_code);

                // Check duplicate in database
                $c_esc = $db->escape($new_code);
                $check = $db->query("SELECT 1 FROM `hlstats_Map_Regions` WHERE UPPER(`code`) = UPPER('$c_esc') LIMIT 1");
                if ($db->num_rows($check) > 0) {
                    $validation_error = "The Region Code '<b>" . htmlspecialchars($new_code) . "</b>' already exists!";
                }
            }
        }

        // 2. Validate EXISTING region rows
        if (!$validation_error && isset($_POST['rows']) && is_array($_POST['rows'])) {
            foreach ($_POST['rows'] as $r_id) {
                $r_id_int = (int)$r_id;

                // Skip deleted regions
                if (!empty($_POST[$r_id . '_delete'])) {
                    continue;
                }

                $code = trim((string)($_POST[$r_id . '_code'] ?? ''));
                $name = trim((string)($_POST[$r_id . '_name'] ?? ''));
                $lat  = trim((string)($_POST[$r_id . '_lat'] ?? ''));
                $lng  = trim((string)($_POST[$r_id . '_lng'] ?? ''));
                $zoom = trim((string)($_POST[$r_id . '_zoom'] ?? ''));

                if ($code === '') {
                    $validation_error = "Region Code cannot be empty.";
                    break;
                }
                if ($name === '') {
                    $validation_error = "Display Name cannot be empty.";
                    break;
                }

                if ($lat === '' || !is_numeric($lat) || (float)$lat < -90 || (float)$lat > 90) {
                    $validation_error = "Latitude must be a valid number between -90 and +90 degrees.";
                    break;
                }

                if ($lng === '' || !is_numeric($lng) || (float)$lng < -180 || (float)$lng > 180) {
                    $validation_error = "Longitude must be a valid number between -180 and +180 degrees.";
                    break;
                }

                if ($zoom === '' || !ctype_digit($zoom) || (int)$zoom < 0 || (int)$zoom > 20) {
                    $validation_error = "Zoom Level must be an integer between 0 and 20.";
                    break;
                }

                $upper_code = strtoupper($code);
                if (in_array($upper_code, $seen_codes, true)) {
                    $validation_error = "Duplicate Region Code detected in form: '<b>" . htmlspecialchars($code) . "</b>'.";
                    break;
                }
                $seen_codes[] = $upper_code;

                // Check collision in DB with other regions
                $code_esc = $db->escape($code);
                $check = $db->query("SELECT 1 FROM `hlstats_Map_Regions` WHERE UPPER(`code`) = UPPER('$code_esc') AND `region_id` != $r_id_int LIMIT 1");
                if ($db->num_rows($check) > 0) {
                    $validation_error = "The Region Code '<b>" . htmlspecialchars($code) . "</b>' is already assigned to another region.";
                    break;
                }
            }
        }

        // 3. Save updates and synchronize options choices
        if (!empty($validation_error)) {
            message("warning", $validation_error);
        } else {
            if ($edlist->update()) {
                // Get currently selected active region from hlstats_Options to maintain it
                $current_opt_res = $db->query("SELECT `value` FROM `hlstats_Options` WHERE `keyname` = 'google_map_region' LIMIT 1");
                $current_active_code = '';
                if ($r_opt = $db->fetch_row($current_opt_res)) {
                    $current_active_code = $r_opt[0];
                }

                // Synchronize dropdown choices for options.php cleanly
                $db->query("DELETE FROM `hlstats_Options_Choices` WHERE `keyname` = 'google_map_region'");
                $res = $db->query("SELECT `code`, `name` FROM `hlstats_Map_Regions` ORDER BY `name` ASC");
                while ($r = $db->fetch_array($res)) {
                    $c_esc = $db->escape((string)$r['code']);
                    $n_esc = $db->escape((string)$r['name']);
                    $is_def = ($r['code'] === $current_active_code) ? 1 : 0;
                    $db->query("INSERT INTO `hlstats_Options_Choices` (`keyname`, `value`, `text`, `isDefault`) VALUES ('google_map_region', '$c_esc', '$n_esc', $is_def)");
                }
                message("success", "Operation successful.");
            } else {
                message("warning", $edlist->error());
            }
        }
    }
?>

<p>Here you can define geographical map regions and countries. These centers are used when rendering the world map on the front page and clan details page based on your site options.</p>

<p>Field descriptions:</p>

<table class="data-table" style="width:75%;margin:10px auto;">
<tr class="head data-table-head">
    <th class="fSmall" align="left" style="width:25%;">Field</th>
    <th class="fSmall" align="left" style="width:75%;">Description</th>
</tr>
<tr class="bg1" style="vertical-align:middle;">
    <td class="fSmall">Region Code</td>
    <td class="fSmall">Unique uppercase identifier (e.g. HUNGARY, EUROPE, GERMANY)</td>
</tr>
<tr class="bg2" style="vertical-align:middle;">
    <td class="fSmall">Display Name</td>
    <td class="fSmall">Name shown in the Admin Options dropdown menu (e.g. Hungary, Europe)</td>
</tr>
<tr class="bg1" style="vertical-align:middle;">
    <td class="fSmall">Latitude</td>
    <td class="fSmall">Geographical latitude of the map center in decimal degrees (e.g. 47.1600)</td>
</tr>
<tr class="bg2" style="vertical-align:middle;">
    <td class="fSmall">Longitude</td>
    <td class="fSmall">Geographical longitude of the map center in decimal degrees (e.g. 19.5000)</td>
</tr>
<tr class="bg1" style="vertical-align:middle;">
    <td class="fSmall">Zoom Level</td>
    <td class="fSmall">Default magnification level (2 for World/Continents, 4-6 for large countries, 7-8 for small countries)</td>
</tr>
</table><br />

<p>Example configurations:</p>

<table class="data-table" style="width:75%;margin:10px auto;">
<tr class="head data-table-head">
    <th class="fSmall" align="left" style="width:25%;">Region Code</th>
    <th class="fSmall" align="left" style="width:30%;">Display Name</th>
    <th class="fSmall" align="left" style="width:25%;">Coordinates (Lat / Lng)</th>
    <th class="fSmall" align="left" style="width:20%;">Zoom</th>
</tr>
<tr class="bg1" style="vertical-align:middle;">
    <td class="fSmall">EUROPE</td>
    <td class="fSmall">Europe</td>
    <td class="fSmall">48.8000 / 8.5000</td>
    <td class="fSmall">3</td>
</tr>
<tr class="bg2" style="vertical-align:middle;">
    <td class="fSmall">HUNGARY</td>
    <td class="fSmall">Hungary</td>
    <td class="fSmall">47.1600 / 19.5000</td>
    <td class="fSmall">7</td>
</tr>
<tr class="bg1" style="vertical-align:middle;">
    <td class="fSmall">NORTH AMERICA</td>
    <td class="fSmall">North America</td>
    <td class="fSmall">45.0000 / -97.0000</td>
    <td class="fSmall">3</td>
</tr>
</table><br />

<p>To add a new region, fill in the fields in the <strong>new</strong> row at the bottom of the table and click <strong>Apply</strong>. To delete an existing region, check the <strong>Delete</strong> box on the right and click <strong>Apply</strong>.</p>

<?php

    $result = $db->query("
        SELECT
            region_id,
            code,
            name,
            lat,
            lng,
            zoom
        FROM
            hlstats_Map_Regions
        ORDER BY
            name ASC
    ");

    $edlist->draw($result);
?>

<table width="75%" border="0" cellspacing="0" cellpadding="0" style="margin:15px auto;">
<tr>
    <td align="center"><input type="submit" value="  Apply  " class="submit" /></td>
</tr>
</table>