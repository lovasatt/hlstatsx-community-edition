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

    global $db, $game, $g_options, $clan, $realkills, $realheadshots;

    flush();

    // Security: Escape & Typecast
    $clan = isset($clan) ? (int)$clan : 0;
    $game = isset($game) ? (string)$game : '';
    $game_esc = $db->escape($game);
    $game_url = urlencode($game);
    
    // Prevent division by zero
    $div_realkills = ($realkills > 0) ? (int)$realkills : 1;
    $div_realheadshots = ($realheadshots > 0) ? (int)$realheadshots : 1;
    
    $realgame = getRealGame($game);
    
    $result  = $db->query("SELECT `code`,`name` FROM hlstats_Weapons WHERE game='$game_esc'");
    
    $fname = array();
    while ($rowdata = $db->fetch_row($result)) {
        $code = $rowdata[0];
        $fname[strtolower((string)$code)] = htmlspecialchars((string)$rowdata[1], ENT_QUOTES, 'UTF-8');
    }
    $db->free_result();
    
    $tblWeapons = new Table(
	array(
	    new TableColumn(
		'weapon',
		'Weapon',
		'width=15&type=weaponimg&align=center&link=' . urlencode("mode=weaponinfo&weapon=%k&game=$game_url"),
		$fname
	    ),
	    new TableColumn(
		'modifier',
		'Modifier',
		'width=10&align=right'
	    ),
	    new TableColumn(
		'kills',
		'Kills',
		'width=11&align=right'
	    ),
	    new TableColumn(
		'kpercent',
		'%',
		'width=5&sort=no&align=right&append=' . urlencode('%')
	    ),
	    new TableColumn(
		'kpercent',
		'Ratio',
		'width=18&sort=no&type=bargraph'
	    ),
	    new TableColumn(
		'headshots',
		'Headshots',
		'width=8&align=right'
	    ),
	    new TableColumn(
		'hpercent',
		'%',
		'width=5&sort=no&align=right&append=' . urlencode('%')
	    ),
	    new TableColumn(
		'hpercent',
		'Ratio',
		'width=18&sort=no&type=bargraph'
	    ),
	    new TableColumn(
		'hpk',
		'HS:K',
		'width=5&align=right'
	    )
	),
	'weapon',
	'kills',
	'weapon',
	true,
	9999,
	'weap_page',
	'weap_sort',
	'weap_sortorder',
	'tabweapons',
	'desc',
	true
    );
    
    $result = $db->query("
	SELECT
	    hlstats_Events_Frags.weapon,
	    IFNULL(hlstats_Weapons.modifier, 1.00) AS modifier,
	    COUNT(hlstats_Events_Frags.weapon) AS kills,
	    ROUND(COUNT(hlstats_Events_Frags.weapon) / $div_realkills * 100, 2) AS kpercent,
	    SUM(hlstats_Events_Frags.headshot=1) as headshots,
	    ROUND(SUM(hlstats_Events_Frags.headshot=1) / IF(COUNT(hlstats_Events_Frags.weapon) = 0, 1, COUNT(hlstats_Events_Frags.weapon)), 2) AS hpk,
	    ROUND(SUM(hlstats_Events_Frags.headshot=1) / $div_realheadshots * 100, 2) AS hpercent
	FROM
	    hlstats_Events_Frags
	LEFT JOIN hlstats_Weapons ON
	    hlstats_Weapons.code = hlstats_Events_Frags.weapon
	LEFT JOIN hlstats_Players ON
	    hlstats_Players.playerId=hlstats_Events_Frags.killerId
	WHERE
	    hlstats_Players.clan = $clan
        AND
        (
            hlstats_Weapons.game = '$game_esc'
            OR hlstats_Weapons.weaponId IS NULL
        )
	GROUP BY
	    hlstats_Events_Frags.weapon,
	    hlstats_Weapons.modifier
	ORDER BY
	    $tblWeapons->sort $tblWeapons->sortorder,
	    $tblWeapons->sort2 $tblWeapons->sortorder
    ");

    printSectionTitle('Weapon Usage *');
    $tblWeapons->draw($result, $db->num_rows($result), 95);
?>
    <br /><br />
<!-- Begin StatsMe Addon 1.0 by JustinHoMi@aol.com -->
<?php

    flush();

    $tblWeaponstats = new Table(
        array(
            new TableColumn(
                'smweapon',
                'Weapon',
                'width=15&type=weaponimg&align=center&link=' . urlencode("mode=weaponinfo&weapon=%k&game=$game_url"),
                $fname
            ),
            new TableColumn(
                'smshots',
                'Shots',
                'width=8&align=right'
            ),
            new TableColumn(
                'smhits',
                'Hits',
                'width=8&align=right'
            ),
            new TableColumn(
                'smdamage',
                'Damage',
                'width=8&align=right'
            ),
            new TableColumn(
                'smheadshots',
                'Headshots',
                'width=8&align=right'
            ),
            new TableColumn(
                'smkills',
                'Kills',
                'width=8&align=right'
            ),
            new TableColumn(
                'smkdr',
                'K:D',
                'width=10&align=right'
            ),
            new TableColumn(
                'smaccuracy',
                'Accuracy',
                'width=10&align=right&append=' . urlencode('%')
            ),
            new TableColumn(
                'smdhr',
                'Damage per Hit',
                'width=10&align=right'
            ),
            new TableColumn(
                'smspk',
                'Shots per Kill',
                'width=10&align=right'
            )
        ),
        'smweapon',
        'smkills',
        'smweapon',
        true,
        9999,
        'weap_page',
        'weap_sort',
        'weap_sortorder',
        'tabweapons',
        'desc',
        true
    );

    $result = $db->query("
        SELECT
            hlstats_Events_Statsme.weapon AS smweapon,
            SUM(hlstats_Events_Statsme.kills) AS smkills,
            SUM(hlstats_Events_Statsme.hits) AS smhits,
            SUM(hlstats_Events_Statsme.shots) AS smshots,
            SUM(hlstats_Events_Statsme.headshots) AS smheadshots,
            SUM(hlstats_Events_Statsme.deaths) AS smdeaths,
            SUM(hlstats_Events_Statsme.damage) AS smdamage,
            ROUND((SUM(hlstats_Events_Statsme.damage) / (IF( SUM(hlstats_Events_Statsme.hits)=0, 1, SUM(hlstats_Events_Statsme.hits) ))), 1) as smdhr,
            SUM(hlstats_Events_Statsme.kills) / IF((SUM(hlstats_Events_Statsme.deaths)=0), 1, (SUM(hlstats_Events_Statsme.deaths))) as smkdr,
            ROUND((SUM(hlstats_Events_Statsme.hits) / SUM(hlstats_Events_Statsme.shots) * 100), 1) as smaccuracy,
            ROUND(( (IF(SUM(hlstats_Events_Statsme.kills)=0, 0, SUM(hlstats_Events_Statsme.shots))) / (IF( SUM(hlstats_Events_Statsme.kills)=0, 1, SUM(hlstats_Events_Statsme.kills) ))), 1) as smspk
        FROM
            hlstats_Events_Statsme
        INNER JOIN hlstats_Players ON
            hlstats_Players.playerId=hlstats_Events_Statsme.playerId
        WHERE
            hlstats_Players.clan = $clan
        GROUP BY
            hlstats_Events_Statsme.weapon
        HAVING
          SUM(hlstats_Events_Statsme.shots)>0
        ORDER BY
            $tblWeaponstats->sort $tblWeaponstats->sortorder,
            $tblWeaponstats->sort2 $tblWeaponstats->sortorder
    ");

if ($db->num_rows($result) != 0)
{
    printSectionTitle('Weapon Stats *');
    $tblWeaponstats->draw($result, $db->num_rows($result), 95);
?>
    <br /><br />
<!-- End StatsMe Addon 1.0 by JustinHoMi@aol.com -->
<?php
}

    flush();
    // Defensive schema check: Ensure compatibility with un-migrated legacy databases
    static $has_neck_col = null;
    static $has_generic_col = null;
    if ($has_neck_col === null) {
        $res = $db->query("SHOW COLUMNS FROM `hlstats_Events_Statsme2` LIKE 'neck'");
        $has_neck_col = ($res && $db->num_rows($res) > 0);
    }
    if ($has_generic_col === null) {
        $res = $db->query("SHOW COLUMNS FROM `hlstats_Events_Statsme2` LIKE 'generic'");
        $has_generic_col = ($res && $db->num_rows($res) > 0);
    }

    // Dynamic field replacement: Use column if exists, fallback to 0 if missing in DB
    $neck_sel    = $has_neck_col ? "SUM(hlstats_Events_Statsme2.neck)" : "0";
    $generic_sel = $has_generic_col ? "SUM(hlstats_Events_Statsme2.generic)" : "0";

    $raw_sort    = $_GET['weaponstats2_sort'] ?? $_GET['weap_sort'] ?? 'smhits';
    $raw_order   = $_GET['weaponstats2_sortorder'] ?? $_GET['weap_sortorder'] ?? 'desc';

    $allowed_sorts = array('smweapon', 'smhits', 'smhead', 'smneck', 'smchest', 'smstomach', 'smleftarm', 'smrightarm', 'smleftleg', 'smrightleg', 'smgeneric', 'smleft', 'smright', 'smmiddle');
    $sort_field    = in_array($raw_sort, $allowed_sorts, true) ? $raw_sort : 'smhits';
    $sort_order    = (strtolower((string)$raw_order) === 'asc') ? 'ASC' : 'DESC';

    // Query weapon targets with self-healing column fallbacks
    $query = "
        SELECT
            hlstats_Events_Statsme2.weapon AS smweapon,
            SUM(hlstats_Events_Statsme2.head) AS smhead,
            {$neck_sel} AS smneck,
            SUM(hlstats_Events_Statsme2.chest) AS smchest,
            SUM(hlstats_Events_Statsme2.stomach) AS smstomach,
            SUM(hlstats_Events_Statsme2.leftarm) AS smleftarm,
            SUM(hlstats_Events_Statsme2.rightarm) AS smrightarm,
            SUM(hlstats_Events_Statsme2.leftleg) AS smleftleg,
            SUM(hlstats_Events_Statsme2.rightleg) AS smrightleg,
            {$generic_sel} AS smgeneric,
            SUM(hlstats_Events_Statsme2.head)
                + {$neck_sel}
                + SUM(hlstats_Events_Statsme2.chest)
                + SUM(hlstats_Events_Statsme2.stomach)
                + SUM(hlstats_Events_Statsme2.leftarm)
                + SUM(hlstats_Events_Statsme2.rightarm)
                + SUM(hlstats_Events_Statsme2.leftleg)
                + SUM(hlstats_Events_Statsme2.rightleg)
                + {$generic_sel} AS smhits,
            IFNULL(ROUND((SUM(hlstats_Events_Statsme2.leftarm) + SUM(hlstats_Events_Statsme2.leftleg)) / (SUM(hlstats_Events_Statsme2.head) + {$neck_sel} + SUM(hlstats_Events_Statsme2.chest) + SUM(hlstats_Events_Statsme2.stomach) + SUM(hlstats_Events_Statsme2.leftarm) + SUM(hlstats_Events_Statsme2.rightarm) + SUM(hlstats_Events_Statsme2.leftleg) + SUM(hlstats_Events_Statsme2.rightleg) + {$generic_sel}) * 100, 1), 0.0) AS smleft,
            IFNULL(ROUND((SUM(hlstats_Events_Statsme2.rightarm) + SUM(hlstats_Events_Statsme2.rightleg)) / (SUM(hlstats_Events_Statsme2.head) + {$neck_sel} + SUM(hlstats_Events_Statsme2.chest) + SUM(hlstats_Events_Statsme2.stomach) + SUM(hlstats_Events_Statsme2.leftarm) + SUM(hlstats_Events_Statsme2.rightarm) + SUM(hlstats_Events_Statsme2.leftleg) + SUM(hlstats_Events_Statsme2.rightleg) + {$generic_sel}) * 100, 1), 0.0) AS smright,
            IFNULL(ROUND((SUM(hlstats_Events_Statsme2.head) + {$neck_sel} + SUM(hlstats_Events_Statsme2.chest) + SUM(hlstats_Events_Statsme2.stomach) + {$generic_sel}) / (SUM(hlstats_Events_Statsme2.head) + {$neck_sel} + SUM(hlstats_Events_Statsme2.chest) + SUM(hlstats_Events_Statsme2.stomach) + SUM(hlstats_Events_Statsme2.leftarm) + SUM(hlstats_Events_Statsme2.rightarm) + SUM(hlstats_Events_Statsme2.leftleg) + SUM(hlstats_Events_Statsme2.rightleg) + {$generic_sel}) * 100, 1), 0.0) AS smmiddle
        FROM
            hlstats_Events_Statsme2
        INNER JOIN
            hlstats_Players
        ON
            hlstats_Players.playerId = hlstats_Events_Statsme2.playerId
        WHERE
            hlstats_Players.clan = $clan
        GROUP BY
            hlstats_Events_Statsme2.weapon
        HAVING
            smhits > 0
        ORDER BY
            {$sort_field} {$sort_order}, smhits DESC
    ";

    $result = $db->query($query);

    if ($db->num_rows($result) != 0)
    {
        printSectionTitle('Weapon Targets *');

        if (isset($g_options['show_weapon_target_flash']) && $g_options['show_weapon_target_flash'] == 1)
        {
            $tblWeaponstats2 = new Table(
                array(
                    new TableColumn(
                        'smweapon',
                        'Weapon',
                        'width=35&type=weaponimg&align=center&link='.urlencode("javascript:switch_weapon('%k');"),
                        $fname
                    ),
                    new TableColumn(
                        'smhits',
                        'Hits',
                        'width=15&align=right'
                    ),
                    new TableColumn(
                        'smleft',
                        'Left',
                        'width=15&align=right&append=' . urlencode('%')
                    ),
                    new TableColumn(
                        'smmiddle',
                        'Middle',
                        'width=15&align=right&append=' . urlencode('%')
                    ),
                    new TableColumn(
                        'smright',
                        'Right',
                        'width=15&align=right&append=' . urlencode('%')
                    )
                ),
                'smweapon',
                'smhits',
                'smweapon',
                true,
                9999,
                'weap_page',
                'weap_sort',
                'weap_sortorder',
                'tabweapons',
                'desc',
                true
            );
?>
    <div class="subblock">
        <div style="float:left;vertical-align:top;width:52%;">
            <script type="text/javascript">
            /* <![CDATA[ */
            <?php
            $weapon_data = array();
            $weapon_data['total'] = array(
                'head' => 0, 'leftarm' => 0, 'rightarm' => 0, 'chest' => 0,
                'stomach' => 0, 'leftleg' => 0, 'rightleg' => 0,
                'generic' => 0,
                'model' => ''
            );

            $css_models = array('ct', 'ct2', 'ct3', 'ct4', 'ts', 'ts2', 'ts3', 'ts4');
            $css_ct_weapons = array('usp', 'tmp', 'm4a1', 'aug', 'famas', 'sig550', 'm4a1_silencer', 'usp_silencer', 'mp9', 'mag7', 'scar20', 'fiveseven');
            $css_ts_weapons = array('glock', 'elite', 'mac10', 'ak47', 'sg552', 'galil', 'galilar', 'g3sg1', 'sg556', 'tec9', 'sawedoff');
            $css_random_weapons = array('knife', 'deagle', 'p228', 'm3', 'xm1014', 'mp5navy', 'p90', 'scout', 'awp', 'm249', 'hegrenade', 'flashbang', 'ump45', 'smokegrenade_projectile', 'ssg08', 'bizon', 'nova', 'p250', 'revolver', 'mp5sd', 'negev', 'taser', 'cz75a');
            $dods_models = array('allies', 'axis');
            $dods_allies_weapons = array('thompson', 'colt', 'spring', 'garand', 'riflegren_us', 'm1carbine', 'bar', 'amerknife', '30cal', 'bazooka', 'frag_us', 'smoke_us');
            $dods_axis_weapons = array('spade', 'riflegren_ger', 'k98', 'mp40', 'p38', 'frag_ger', 'smoke_ger', 'mp44', 'k98_scoped', 'mg42', 'pschreck', 'c96');
            $l4d_models = array('zombie1', 'zombie2', 'zombie3');
            $insmod_models = array('insmod1', 'insmod2');
            $fof_models = array('fof1', 'fof2');
            $ges_models = array('ges-bond', 'ges-boris');
            $dinodday_models = array('ddd_allies', 'ddd_axis');
            $dinodday_allies_weapons = array('garand', 'greasegun', 'thompson', 'shotgun', 'sten', 'carbine', 'bar', 'mosin', 'p38', 'piat', 'nagant', 'flechette', 'pistol', 'trigger');
            $dinodday_axis_weapons = array('mp40', 'k98', 'mp44', 'k98sniper', 'luger', 'stygimoloch', 'mg42', 'trex');

            while ($rowdata = $db->fetch_array($result)) {
                $weapon_data['total']['head'] += (int)$rowdata['smhead'];
                $weapon_data['total']['leftarm'] += (int)$rowdata['smleftarm'];
                $weapon_data['total']['rightarm'] += (int)$rowdata['smrightarm'];
                $weapon_data['total']['chest'] += ((int)$rowdata['smchest'] + (int)($rowdata['smneck'] ?? 0));
                $weapon_data['total']['stomach'] += (int)$rowdata['smstomach'];
                $weapon_data['total']['leftleg'] += (int)$rowdata['smleftleg'];
                $weapon_data['total']['rightleg'] += (int)$rowdata['smrightleg'];
                $weapon_data['total']['generic'] += (int)$rowdata['smgeneric'];
                $weapon_data[$rowdata['smweapon']]['head'] = (int)$rowdata['smhead'];
                $weapon_data[$rowdata['smweapon']]['leftarm'] = (int)$rowdata['smleftarm'];
                $weapon_data[$rowdata['smweapon']]['rightarm'] = (int)$rowdata['smrightarm'];
                $weapon_data[$rowdata['smweapon']]['chest'] = ((int)$rowdata['smchest'] + (int)($rowdata['smneck'] ?? 0));
                $weapon_data[$rowdata['smweapon']]['stomach'] = (int)$rowdata['smstomach'];
                $weapon_data[$rowdata['smweapon']]['leftleg'] = (int)$rowdata['smleftleg'];
                $weapon_data[$rowdata['smweapon']]['rightleg'] = (int)$rowdata['smrightleg'];
                $weapon_data[$rowdata['smweapon']]['generic'] = (int)$rowdata['smgeneric'];

                switch ($realgame) {
                    case 'dods':
                        $weapon_data[$rowdata['smweapon']]['model'] = 'allies';
                        break;
                    case 'l4d':
                    case 'l4d2':
                        $weapon_data[$rowdata['smweapon']]['model'] = 'zombie1';
                        break;
                    case 'hl2mp':
                        $weapon_data[$rowdata["smweapon"]]['model'] = 'alyx';
                        break;
                    case 'insmod':
                        $weapon_data[$rowdata['smweapon']]['model'] = 'insmod1';
                        break;
                    case 'zps':
                        $weapon_data[$rowdata["smweapon"]]['model'] = 'zps1';
                        break;
                    case 'ges':
                        $weapon_data[$rowdata["smweapon"]]['model'] = 'ges-bond';
                        break;
                    case 'tfc':
                        $weapon_data[$rowdata["smweapon"]]['model'] = 'pyro';
                        break;
                    case 'fof':
                        $weapon_data[$rowdata['smweapon']]['model'] = 'fof1';
                        break;
                    case 'dinodday':
                        $weapon_data[$rowdata['smweapon']]['model'] = 'ddd_allies';
                        break;
                    default:
                        $weapon_data[$rowdata['smweapon']]['model'] = 'ct';
                }

                if ($realgame == 'css' || $realgame == 'cstrike' || $realgame == 'csgo' || $realgame == 'cs2' || $realgame == 'csp') {
                    if (in_array($rowdata['smweapon'], $css_random_weapons)) {
                        $weapon_data[$rowdata['smweapon']]['model'] = $css_models[array_rand($css_models)];
                    } elseif (in_array($rowdata['smweapon'], $css_ct_weapons)) {
                        $weapon_data[$rowdata['smweapon']]['model'] = $css_models[rand(0, 2) + 3];
                    } elseif (in_array($rowdata['smweapon'], $css_ts_weapons)) {
                        $weapon_data[$rowdata['smweapon']]['model'] = $css_models[rand(0, 2)];
                    }
                } elseif ($realgame == 'dods') {
                    if (in_array($rowdata['smweapon'], $dods_allies_weapons)) {
                        $weapon_data[$rowdata['smweapon']]['model'] = $dods_models[1];
                    } elseif (in_array($rowdata['smweapon'], $dods_axis_weapons)) {
                        $weapon_data[$rowdata['smweapon']]['model'] = $dods_models[0];
                    }
                } elseif ($realgame == 'dinodday') {
                    if (in_array($rowdata['smweapon'], $dinodday_allies_weapons)) {
                        $weapon_data[$rowdata['smweapon']]['model'] = $dinodday_models[1];
                    } elseif (in_array($rowdata['smweapon'], $dinodday_axis_weapons)) {
                        $weapon_data[$rowdata['smweapon']]['model'] = $dinodday_models[0];
                    }
                }
            }

            switch ($realgame) {
                case 'dods':
                    $start_model = $dods_models[array_rand($dods_models)];
                    break;
                case 'l4d':
                case 'l4d2':
                    $start_model = $l4d_models[array_rand($l4d_models)];
                    break;
                case 'hl2mp':
                    $start_model = 'alyx';
                    break;
                case 'insmod':
                    $start_model = $insmod_models[array_rand($insmod_models)];
                    break;
                case 'zps':
                    $start_model = 'zps1';
                    break;
                case 'ges':
                    $start_model = $ges_models[array_rand($ges_models)];
                    break;
                case 'tfc':
                    $start_model = 'pyro';
                    break;
                case 'fof':
                    $start_model = $fof_models[array_rand($fof_models)];
                    break;
                case 'dinodday':
                    $start_model = $dinodday_models[array_rand($dinodday_models)];
                    break;
                default:
                    $start_model = $css_models[array_rand($css_models)];
            }
            $weapon_data['total']['model'] = $start_model;

            echo "var data_array = {};\n";
            $graphtxt_load = htmlspecialchars((string)($g_options['graphtxt_load'] ?? 'FFFFFF'), ENT_QUOTES, 'UTF-8');
            $graphbg_load  = htmlspecialchars((string)($g_options['graphbg_load'] ?? '282828'), ENT_QUOTES, 'UTF-8');
            $image_path_safe = htmlspecialchars((string)IMAGE_PATH, ENT_QUOTES, 'UTF-8');

            foreach ($weapon_data as $key => $entry) {
                $display_name = ($key === 'total') ? 'All Weapons' : ucfirst((string)$key);
                $js_key       = ($key === 'total') ? 'All Weapons' : (string)$key;

                $head     = (int)($entry['head'] ?? 0);
                $leftarm  = (int)($entry['leftarm'] ?? 0);
                $rightarm = (int)($entry['rightarm'] ?? 0);
                $chest    = (int)($entry['chest'] ?? 0);
                $stomach  = (int)($entry['stomach'] ?? 0);
                $leftleg  = (int)($entry['leftleg'] ?? 0);
                $rightleg = (int)($entry['rightleg'] ?? 0);
                $generic  = (int)($entry['generic'] ?? 0);
                $model    = (string)($entry['model'] ?? '');

                $jsData = json_encode([
                    $display_name, $head, $leftarm, $rightarm, $chest,
                    $stomach, $leftleg, $rightleg, $generic, $model
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

                echo "data_array[" . json_encode($js_key) . "] = " . $jsData . ";\n";
            }

            // Reset pointer for table draw
            if ($db->num_rows($result) > 0) {
                $db->data_seek(0, $result);
            }
?>
            function switch_weapon(weapon) {
                if (document.embeds && document.embeds.hitbox) {
                    if (document.embeds.hitbox.LoadMovie) {
                        document.embeds.hitbox.LoadMovie(0, '<?php echo $image_path_safe; ?>/hitbox.swf?wname='+data_array[weapon][0]
                            +'&head='+data_array[weapon][1]
                            +'&rightarm='+data_array[weapon][3]
                            +'&leftarm='+data_array[weapon][2]
                            +'&chest='+data_array[weapon][4]
                            +'&stomach='+data_array[weapon][5]
                            +'&leftleg='+data_array[weapon][6]
                            +'&rightleg='+data_array[weapon][7]
                            +'&generic='+data_array[weapon][8]
                            +'&model='+data_array[weapon][9]
                            +'&numcolor_num=#<?php echo $graphtxt_load; ?>&numcolor_pct=#<?php echo $graphtxt_load; ?>&linecolor=#<?php echo $graphtxt_load; ?>&barcolor=#FFFFFF&barbackground=#000000&textcolor=#FFFFFF&captioncolor=#FFFFFF&textcolor_total=#FFFFFF');
                    }
                } else if (document.getElementById) {
                    var obj = document.getElementById('hitbox');
                    if (typeof obj.LoadMovie != 'undefined') {
                        obj.LoadMovie(0, '<?php echo $image_path_safe; ?>/hitbox.swf?wname='+data_array[weapon][0]
                            +'&head='+data_array[weapon][1]
                            +'&rightarm='+data_array[weapon][3]
                            +'&leftarm='+data_array[weapon][2]
                            +'&chest='+data_array[weapon][4]
                            +'&stomach='+data_array[weapon][5]
                            +'&leftleg='+data_array[weapon][6]
                            +'&rightleg='+data_array[weapon][7]
                            +'&generic='+data_array[weapon][8]
                            +'&model='+data_array[weapon][9]
                            +'&numcolor_num=#<?php echo $graphtxt_load; ?>&numcolor_pct=#<?php echo $graphtxt_load; ?>&linecolor=#<?php echo $graphtxt_load; ?>&barcolor=#FFFFFF&barbackground=#000000&textcolor=#FFFFFF&captioncolor=#FFFFFF&textcolor_total=#FFFFFF');
                    }
                }
            }
        </script>
<?php
        $tblWeaponstats2->draw($result, $db->num_rows($result), 100);
        $flashlink = IMAGE_PATH.'/hitbox.swf?wname=All+Weapons&amp;head='.$weapon_data['total']['head'].'&amp;rightarm='.$weapon_data['total']['rightarm'].'&amp;leftarm='.$weapon_data['total']['leftarm'].'&amp;chest='.$weapon_data['total']['chest'].'&amp;stomach='.$weapon_data['total']['stomach'].'&amp;rightleg='.$weapon_data['total']['rightleg'].'&amp;leftleg='.$weapon_data['total']['leftleg'].'&amp;generic='.$weapon_data['total']['generic'].'&amp;model='.$start_model.'&amp;numcolor_num=#'.$graphtxt_load.'&amp;numcolor_pct=#'.$graphtxt_load.'&amp;linecolor=#'.$graphtxt_load.'&amp;barcolor=#FFFFFF&amp;barbackground=#000000&amp;textcolor=#FFFFFF&amp;captioncolor=#FFFFFF&amp;textcolor_total=#FFFFFF';
?>
        </div>
        <div style="float:right;vertical-align:top;width:480px;">
            <table class="data-table">
                <tr class="data-table-head">
                    <td style="text-align:center;">Targets</td>
                </tr>
                <tr class="bg1">
                    <td style="text-align:center;">
                        <object width="470" height="360" align="middle" id="hitbox" data="<?php echo $flashlink; ?>" type="application/x-shockwave-flash">
                            <param name="movie" value="<?php echo $flashlink; ?>" />
                            <param name="quality" value="high" />
                            <param name="wmode" value="opaque" />
                            <param name="bgcolor" value="#<?php echo $graphbg_load; ?>" />
                            The hitbox display requires <a href="http://www.adobe.com" target="_blank">Adobe Flash Player</a> to view.
                        </object>
                    </td>
                </tr>
                <tr class="bg2">
                    <td style="text-align:center;">
                        <a href="javascript:switch_weapon('All Weapons');">Show total target statistics</a>
                    </td>
                </tr>
            </table>
        </div>
        <div style="clear:both;"></div>
    </div>
<?php
    }
    else
    {
        // Inspect rows for active neck and generic hits
        $has_neck_data    = false;
        $has_generic_data = false;

        while ($row = $db->fetch_array($result))
        {
            if (!empty($row['smneck']) && (int)$row['smneck'] > 0)
            {
                $has_neck_data = true;
            }
            if (!empty($row['smgeneric']) && (int)$row['smgeneric'] > 0)
            {
                $has_generic_data = true;
            }
        }
        $db->data_seek(0, $result);

        // Calculate active zone count and balanced column widths
        $zone_count = 7 + ($has_neck_data ? 1 : 0) + ($has_generic_data ? 1 : 0);

        if ($zone_count == 9) {
            $zone_w = 5; $l_w = 9; $m_w = 10; $r_w = 9;
        } elseif ($zone_count == 8) {
            $zone_w = 6; $l_w = 8; $m_w = 9;  $r_w = 8;
        } else {
            $zone_w = 7; $l_w = 8; $m_w = 8;  $r_w = 8;
        }

        // Build dynamic column list in anatomical order
        $body_cols = array();
        $body_cols[] = new TableColumn('smweapon', 'Weapon', 'width=15&type=weaponimg&align=center&link=' . urlencode("mode=weaponinfo&weapon=%k&game=$game_url"), $fname);
        $body_cols[] = new TableColumn('smhits', 'Hits', 'width=7&align=right');

        // Head
        $body_cols[] = new TableColumn('smhead', 'Head', "width={$zone_w}&align=right");

        // Neck (only if data exists)
        if ($has_neck_data) {
            $body_cols[] = new TableColumn('smneck', 'Neck', "width={$zone_w}&align=right");
        }

        // Torso & Limbs
        $body_cols[] = new TableColumn('smchest', 'Chest', "width={$zone_w}&align=right");
        $body_cols[] = new TableColumn('smstomach', 'Stomach', "width={$zone_w}&align=right");
        $body_cols[] = new TableColumn('smleftarm', 'Left Arm', "width={$zone_w}&align=right");
        $body_cols[] = new TableColumn('smrightarm', 'Right Arm', "width={$zone_w}&align=right");
        $body_cols[] = new TableColumn('smleftleg', 'Left Leg', "width={$zone_w}&align=right");
        $body_cols[] = new TableColumn('smrightleg', 'Right Leg', "width={$zone_w}&align=right");

        // Body / Generic (only if data exists)
        if ($has_generic_data) {
            $body_cols[] = new TableColumn('smgeneric', 'Body', "width={$zone_w}&align=right");
        }

        // Directions
        $body_cols[] = new TableColumn('smleft', 'Left', "width={$l_w}&align=right&append=" . urlencode('%'));
        $body_cols[] = new TableColumn('smmiddle', 'Middle', "width={$m_w}&align=right&append=" . urlencode('%'));
        $body_cols[] = new TableColumn('smright', 'Right', "width={$r_w}&align=right&append=" . urlencode('%'));

        $tblWeaponstats2 = new Table(
            $body_cols,
            'smweapon',
            'smhits',
            'smweapon',
            true,
            9999,
            'weap_page',
            'weap_sort',
            'weap_sortorder',
            'weaponstats2',
            'desc',
            true
        );

        $tblWeaponstats2->draw($result, $db->num_rows($result), 95);
    }
?>
    <br /><br />
<div style="text-align: center; width: 95%;">
    <p class="note"><b>Note:</b> The Weapon Targets table automatically displays the `Neck` and `Body` (Generic) hitgroups when hits are recorded. These hitgroups are supported by Source 2 games, such as Counter-Strike 2</p>
</div>
<?php
    }
?>