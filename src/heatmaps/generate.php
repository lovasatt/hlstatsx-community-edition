#!/usr/bin/php
<?php
error_reporting(E_ALL);

require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/heatmap.class.php';

Heatmap::init();

$mapinfo = Env::get('mapinfo') ?? [];
$success = 0;
$skipped = 0;
$failed = 0;

foreach ($mapinfo as $game => $gameconf) {
    foreach ($gameconf as $map => $data) {
        $res = Heatmap::generate($game, $map, "kill");
        if ($res === Heatmap::STATUS_SUCCESS) {
            $success++;
        } elseif ($res === Heatmap::STATUS_SKIPPED) {
            $skipped++;
        } else {
            $failed++;
        }
    }
}

show::Event("SUMMARY", "Done! Generated: $success, Skipped/Empty: $skipped, Errors: $failed.", 1);

exit($failed > 0 ? 1 : 0);
