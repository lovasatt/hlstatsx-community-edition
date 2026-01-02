<?php

    if (!defined('IN_HLSTATS')) {
	die('Do not access this file directly.');
    }
    
    if (!file_exists("./updater")) {
	die('Updater directory is missing.');
    }
    
    define('IN_UPDATER', true);
    
    // PHP 8 Fix: Bring variables into scope
    global $gamename, $g_options;
    $gamename = isset($gamename) ? $gamename : 'Updater';

    pageHeader
    (
	array ($gamename, 'Updater')
    );
    echo "<div class=\"warning\">\n" .
    "<span id=\"warning-header\"><strong>HLX:CE Database Updater log</span></strong><br /><br />\n";
    
    // PHP 8 Fix: Ensure version key exists
    $current_version = isset($g_options['version']) ? $g_options['version'] : '0.0.0';

    // Check version since updater wasn't implemented until version 1.6.2
    $versioncomp = version_compare($current_version, '1.6.1');
    
    if ($versioncomp === -1)
    {
	// not yet at 1.6.1
	echo "You cannot upgrade from this version (".htmlspecialchars($current_version)."). You can only upgrade from 1.6.1.  Please manually apply the SQL updates found in the SQL folder through 1.6.1, then re-run this updater.\n";
    }
    else if ($versioncomp === 0)
    {
	// at 1.6.1, up to 1.6.2
	include ("./updater/update161-162.php");		
    }
    else
    {
        // PHP 8 Fix: Safe casting
        $db_version = isset($g_options['dbversion']) ? (int)$g_options['dbversion'] : 0;

	// at 1.6.2 or higher, can update normally
	echo "Currently on database version " . $db_version . "<br />\n";
	$i = $db_version + 1;
	
	while (file_exists ("./updater/$i.php"))
	{
	    echo "<br /><em>Running database update $i</em><br />\n";
	    include ("./updater/$i.php");
	    
	    echo "<em>Database update for DB Version $i complete.</em><br />";
	    $i++;
	    
	}
	
	if ($i == $db_version + 1)
	{
	    echo "<strong>Your database is already up to date (" . $db_version . ")</strong>\n";
	}
	else
	{
	    echo "<br /><strong>Successfully updated to database version ".($i-1)."!</strong>\n";
	}
    }
    
    // In docker, the updater folder will always be present, to allow
    // DB upgrades to be done using this updater. Hence, this code is
    // commented out to allow things to work correctly after the DB upgrade in docker.
    // echo "<br /><br /><img src=\"".IMAGE_PATH."/warning.gif\" alt=\"Warning\"> <span class=\"warning-header\">You <strong>must delete</strong> the \"updater\" folder from your web site before your site will be operational.</span>\n</div>\n";
?>