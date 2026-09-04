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

    define('IN_HLSTATS', true);

    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

    if (ob_get_level() == 0) {
        ob_start();
    }

    // Load database classes
    require ('config.php');
    require (INCLUDE_PATH . '/class_db.php');
    require (INCLUDE_PATH . '/functions.php');
    require (INCLUDE_PATH . '/pChart/pData.class');
    require (INCLUDE_PATH . '/pChart/pChart.class');

    $db_classname = 'DB_' . DB_TYPE;
    if (class_exists($db_classname)) {
	$db = new $db_classname(DB_ADDR, DB_USER, DB_PASS, DB_NAME, DB_PCONNECT);
    } else {
	error('Database class does not exist.  Please check your config.php file for DB_TYPE');
    }

    $g_options = getOptions();
    
    $bg_color = array('red' => 90, 'green' => 90, 'blue' => 90);
    if (!empty($_GET['bgcolor']) && is_string($_GET['bgcolor'])) {
        $clean_bg = trim($_GET['bgcolor']);
        if (preg_match('/^[a-fA-F0-9]{3,6}$/', $clean_bg)) {
            $parsed_bg = hex2rgb($clean_bg);
            if (is_array($parsed_bg) && isset($parsed_bg['red'])) {
                $bg_color = $parsed_bg;
            }
        }
    }

    $color = array('red' => 213, 'green' => 217, 'blue' => 221);
    if (!empty($_GET['color']) && is_string($_GET['color'])) {
        $clean_color = trim($_GET['color']);
        if (preg_match('/^[a-fA-F0-9]{3,6}$/', $clean_color)) {
            $parsed_color = hex2rgb($clean_color);
            if (is_array($parsed_color) && isset($parsed_color['red'])) {
                $color = $parsed_color;
            }
        }
    }

    // PHP 8 Fix: Null coalescing and casting
    $player = isset($_GET['player']) ? (int)$_GET['player'] : 0;
    if ($player <= 0) {
	exit();
    }

    $res = $db->query("SELECT UNIX_TIMESTAMP(eventTime) AS ts, skill, skill_change FROM hlstats_Players_History WHERE playerId = {$player} ORDER BY eventTime DESC LIMIT 30");
    $skill = array();
    $skill_change = array();
    $date = array();
    $rowcnt = ($res) ? $db->num_rows($res) : 0;
    $last_time = 0;
    
    for ($i = 1; $i <= $rowcnt; $i++)
    {
	$row = $db->fetch_array($res);
        
        // PHP 8 Fix: Ensure values are numeric
        $skill_val = (isset($row['skill']) && $row['skill'] != 0) ? ($row['skill'] / 1000) : 0;
        $skill_change_val = isset($row['skill_change']) ? $row['skill_change'] : 0;
        $ts_val = isset($row['ts']) ? (int)$row['ts'] : 0;

	if ($i === 1) {
	    $last_time = $ts_val;
	}

	array_unshift($skill, $skill_val);
	array_unshift($skill_change, $skill_change_val);
	if ($i == 1 || $i == round($rowcnt/2) || $i == $rowcnt)
	{
	    array_unshift($date, date("M-j", $ts_val));
	}
	else
	{
	    array_unshift($date, '');
	}
    }
    
    $update_interval = defined('IMAGE_UPDATE_INTERVAL') ? IMAGE_UPDATE_INTERVAL : 3600;

    $cache_dir = IMAGE_PATH . "/progress";
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }

    $cache_image = IMAGE_PATH . "/progress/trend_{$player}_{$last_time}.png";

    if (file_exists($cache_image))
    {
        $file_timestamp = @filemtime($cache_image);
        if ($file_timestamp && ($file_timestamp + $update_interval > time())) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-type: image/png');
            header('Cache-Control: public, max-age=' . $update_interval);
            readfile($cache_image);
            exit();
        }
    }
    
    // Ensure pChart works
    if (!class_exists('pChart')) {
        // Fallback or error handling if pChart is missing
        exit('pChart library missing');
    }

    $font_path = IMAGE_PATH . '/sig/font/DejaVuSans.ttf';

    $Chart = new pChart(380, 200);
    $Chart->drawBackground($bg_color['red'], $bg_color['green'], $bg_color['blue']);
    
    $Chart->setGraphArea(50, 28, 339, 174);
    $Chart->drawGraphAreaGradient(40, 40, 40, -50);
    
    if (count($date) < 2)
    {
	if (file_exists($font_path)) {
	    $Chart->setFontProperties($font_path, 11);
	}
	$Chart->drawTextBox(50, 85, 339, 115, "Not Enough Session Data", 0, 0, 0, 0, ALIGN_CENTER, FALSE, 255, 255, 255, 0);
    }
    else
    {	
	$DataSet = new pData;
	$DataSet->AddPoint($skill, 'SerieSkill');
	$DataSet->AddPoint($skill_change, 'SerieSession');
	$DataSet->AddPoint($date, 'SerieDate');
	$DataSet->AddSerie('SerieSkill');
	$DataSet->SetAbsciseLabelSerie('SerieDate');
	$DataSet->SetSerieName('Skill', 'SerieSkill');
	$DataSet->SetSerieName('Session', 'SerieSession');

	if (file_exists($font_path)) {
	    $Chart->setFontProperties($font_path, 7);
	}
	$DataSet->SetYAxisName('Skill');
	$DataSet->SetYAxisUnit('K');
	$Chart->setColorPalette(0, 255, 255, 0);
	$Chart->drawRightScale($DataSet->GetData(), $DataSet->GetDataDescription(),
	    SCALE_NORMAL, $color['red'], $color['green'], $color['blue'], TRUE, 0, 0);
	$Chart->drawGrid(1, FALSE, 55, 55, 55, 100);
	$Chart->setShadowProperties(3, 3, 0, 0, 0, 30, 4);
	$Chart->drawCubicCurve($DataSet->GetData(), $DataSet->GetDataDescription());
	$Chart->clearShadow();
	$Chart->drawFilledCubicCurve($DataSet->GetData(), $DataSet->GetDataDescription(), 0.1, 30);
	$Chart->drawPlotGraph($DataSet->GetData(), $DataSet->GetDataDescription(), 1, 1, 255, 255, 255);
	
	$Chart->clearScale();

	$DataSet->RemoveSerie('SerieSkill');
	$DataSet->AddSerie('SerieSession');
	$DataSet->SetYAxisName('Session');
	$DataSet->SetYAxisUnit('');
	$Chart->setColorPalette(1, 255, 0,   0);
	$Chart->setColorPalette(2,   0, 0, 255);
	$Chart->drawScale($DataSet->GetData(), $DataSet->GetDataDescription(),
	    SCALE_NORMAL, $color['red'], $color['green'], $color['blue'], TRUE, 0, 0);
	$Chart->setShadowProperties(3, 3, 0, 0, 0, 30, 4);
	$Chart->drawCubicCurve($DataSet->GetData(), $DataSet->GetDataDescription());
	$Chart->clearShadow();
	$Chart->drawPlotGraph($DataSet->GetData(), $DataSet->GetDataDescription(), 1, 1, 255, 255, 255);
	
	if (file_exists($font_path)) {
	    $Chart->setFontProperties($font_path, 7);
	}
	$Chart->drawHorizontalLegend(235, -1, $DataSet->GetDataDescription(),
	    0, 0, 0, 0, 0, 0, $color['red'], $color['green'], $color['blue'], FALSE);
    }
    
    $Chart->Render($cache_image);

    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-type: image/png');
    header('Cache-Control: public, max-age=' . $update_interval);
    readfile($cache_image);
    exit();

?>