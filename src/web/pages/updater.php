<?php

    if (!defined('IN_HLSTATS')) {
        die('Do not access this file directly.');
    }

    if (!is_dir('./updater')) {
        die('Updater directory is missing.');
    }

    define('IN_UPDATER', true);

    // Initialize variables
    global $gamename, $g_options;
    $gamename = isset($gamename) ? (string)$gamename : 'Updater';

    pageHeader
    (
        array ($gamename, 'Updater')
    );
    echo "<div class=\"warning\">\n" .
    "<span id=\"warning-header\"><strong>HLX:CE Database Updater log</strong></span><br /><br />\n";

    $current_version = (string)($g_options['version'] ?? '0.0.0');

    // Check version since updater wasn't implemented until version 1.6.2
    $versioncomp = version_compare($current_version, '1.6.1');

    if ($versioncomp === -1)
    {
        // not yet at 1.6.1
        echo "You cannot upgrade from this version (" . htmlspecialchars($current_version, ENT_QUOTES, 'UTF-8') . "). You can only upgrade from 1.6.1. Please manually apply the SQL updates found in the SQL folder through 1.6.1, then re-run this updater.\n";
    }
    else if ($versioncomp === 0)
    {
        // at 1.6.1, up to 1.6.2
        include("./updater/update161-162.php");
    }
    else
    {
        $db_version = (int)($g_options['dbversion'] ?? 0);

        // at 1.6.2 or higher, can update normally
        echo "Currently on database version " . $db_version . "<br />\n";
        $i = $db_version + 1;

        while (file_exists("./updater/{$i}.php"))
        {
            echo "<br /><em>Running database update {$i}</em><br />\n";
            include("./updater/{$i}.php");

            echo "<em>Database update for DB Version {$i} complete.</em><br />";
            $i++;
        }

        if ($i === $db_version + 1)
        {
            echo "<strong>Your database is already up to date (" . $db_version . ")</strong>\n";
        }
        else
        {
            echo "<br /><strong>Successfully updated to database version " . ($i - 1) . "!</strong>\n";
        }
    }

    echo "</div>\n";
?>