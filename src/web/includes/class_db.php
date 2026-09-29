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


/* Profile support:

To see SQL run counts and run times, set the $profile variable below to something
that evaluates as true.

Add the following table to your database:
CREATE TABLE IF NOT EXISTS `hlstats_sql_web_profile` (
`queryid` int(11) NOT NULL AUTO_INCREMENT,
`source` tinytext NOT NULL,
`run_count` int(11) NOT NULL,
`run_time` float NOT NULL,
PRIMARY KEY (`queryid`),
UNIQUE KEY `source` (`source`(64))
) ENGINE=MyISAM;
*/

if (!defined('IN_HLSTATS')) {
    die('Do not access this file directly.');
}

#[AllowDynamicProperties]
class DB_mysql
{
    public $db_addr;
    public $db_user;
    public $db_pass;
    public $db_name;

    public $link = null;
    public $last_result = null;
    public $last_query = '';
    public $last_insert_id = 0;
    public $profile = 0;
    public $querycount = 0;
    public $last_calc_rows = 0;

    function __construct($db_addr, $db_user, $db_pass, $db_name, $use_pconnect = false)
    {
	$this->db_addr = $db_addr;
	$this->db_user = $db_user;
	$this->db_pass = $db_pass;

	$this->querycount = 0;

        // PHP 8+ compatible exception handling
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $host = (string)$db_addr;
            $port = 3306;
            $socket = null;

            if (strpos($host, ':/') !== false)
            {
                // Unix socket notation, e.g. "localhost:/tmp/mysql.sock" or ":/tmp/mysql.sock"
                list($host, $socket) = explode(':', $host, 2);
                if ($host === '') {
                    $host = 'localhost';
                }
            }
            elseif (preg_match('/^([^:]+):(\d+)$/', $host, $m))
            {
                // "host:port" notation
                $host = $m[1];
                $port = (int)$m[2];
            }

        if ( $use_pconnect )
        {
                $host = "p:" . $host;
        }
            
            $this->link = mysqli_connect($host, $db_user, $db_pass, null, $port, $socket);
            
        } catch (\Throwable $e) {
            $this->link = false;
        }

    if ( $this->link )
    {
            try {
	if (defined('DB_CHARSET')) {
                    mysqli_set_charset($this->link, DB_CHARSET);
                } else {
                    mysqli_set_charset($this->link, 'utf8mb4');
                }

                if (defined('DB_COLLATE')) {
                    $query_str = "SET collation_connection = " . mysqli_real_escape_string($this->link, DB_COLLATE);
                    mysqli_query($this->link, $query_str);
                }

	if ( $db_name != '' )
	{
	    $this->db_name = $db_name;
	    mysqli_select_db($this->link, $db_name);
	}
            } catch (\Throwable $e) {
                if ($this->link) mysqli_close($this->link);
                $this->error("Database initialization failed: " . $e->getMessage());
            }

	    return $this->link;
	}
	else
	{
	    $this->error('Could not connect to database server. Check that the values of DB_ADDR, DB_USER and DB_PASS in config.php are set correctly.');
	}
    }

    function data_seek($row_number, $query_id = 0)
    {
	if ( !$query_id )
	{
	    $query_id = $this->last_result;
	}
	if ( $query_id instanceof mysqli_result )
	{
            if ($row_number < 0 || $row_number >= mysqli_num_rows($query_id)) {
                return false;
            }
	    return mysqli_data_seek($query_id, (int)$row_number);
	}
	return false;
    }

    function fetch_array($query_id = 0, $mode = MYSQLI_BOTH)
    {
	if ( !$query_id )
	{
	    $query_id = $this->last_result;
	}

	if ( $query_id instanceof mysqli_result )
	{
        return mysqli_fetch_array($query_id, $mode);
    }
    return false;
    }

    function fetch_row($query_id = 0)
    {
	if ( !$query_id )
	{
	    $query_id = $this->last_result;
	}

	if ( $query_id instanceof mysqli_result )
	{
	    return mysqli_fetch_row($query_id);
	}
	return false;
    }

    function fetch_row_set($query_id = 0)
    {
	if ( !$query_id )
	{
	    $query_id = $this->last_result;
	}

	if ( $query_id instanceof mysqli_result )
	{
	    $rowset = array();
	    while ( $row = $this->fetch_array($query_id) )
		$rowset[] = $row;

	    return $rowset;
	}
	return false;
    }

    function free_result($query_id = 0)
    {
	if ( !$query_id )
	{
	    $query_id = $this->last_result;
	}

	if ( $query_id instanceof mysqli_result )
	{
        try {
	mysqli_free_result($query_id);
        } catch (\Throwable $e) {
	return false;
        }
        if ($this->last_result === $query_id) {
            $this->last_result = null;
        }
            return true;
	}
	return false;
    }

    function insert_id()
    {
	return $this->last_insert_id;
    }

    function num_rows($query_id = 0)
    {
	if ( !$query_id )
	{
	    $query_id = $this->last_result;
	}

	if ( $query_id instanceof mysqli_result )
	{
	    return mysqli_num_rows($query_id);
	}
	return false;
    }

    function calc_rows()
    {
	return $this->last_calc_rows;
    }

    function query($query, $showerror=true, $calcrows=false)
    {
        if (!$this->link) {
            if ($showerror) $this->error("No database connection.");
            return false;
        }

	$this->last_query = $query;
	$starttime = microtime(true);
	if($calcrows == true)
	{
	    /* Add sql_calc_found_rows to this query */
	    $query = preg_replace('/^\s*select/i', 'SELECT SQL_CALC_FOUND_ROWS', $query, 1);
	}
        
        try {
            $this->last_result = mysqli_query($this->link, $query);
            } catch (\Throwable $e) {
                $this->last_result = false;
                $this->last_error_msg = $e->getMessage();
            }

        $endtime = microtime(true);

        $this->last_insert_id = mysqli_insert_id($this->link);

        if($calcrows == true)
        {
                try {
        	$calc_result = mysqli_query($this->link, "select found_rows() as rowcount");
        if($calc_result && $row = mysqli_fetch_assoc($calc_result))
        {
            $this->last_calc_rows = (int)$row['rowcount'];
        }
        if ($calc_result instanceof mysqli_result)
        {
            mysqli_free_result($calc_result);
        }
                } catch (\Throwable $e) {
                    $this->last_calc_rows = 0;
                }
        }

	$this->querycount++;

	if ( defined('DB_DEBUG') && DB_DEBUG == true )
	{
	    echo "<div style='background:#eee;padding:5px;border:1px solid #ccc;'><pre>" . htmlspecialchars($query) . "</pre></div>";
	}

	if ( $this->last_result )
	{
	    if($this->profile)
	    {
	$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
                $bt = isset($backtrace[1]) ? $backtrace[1] : (isset($backtrace[0]) ? $backtrace[0] : null);
                $file_info = ($bt && isset($bt['file'])) ? basename($bt['file']) . ':' . $bt['line'] : 'unknown';
                $escaped_source = mysqli_real_escape_string($this->link, $file_info);
                $duration = $endtime - $starttime;
                
		$profilequery = "insert into hlstats_sql_web_profile (source, run_count, run_time) values ".
		    "('$escaped_source',1,'$duration') ".
		    "ON DUPLICATE KEY UPDATE run_count = run_count+1, run_time=run_time+$duration";
		try {
	                    mysqli_query($this->link, $profilequery);
	                } catch (\Throwable $e) {}
	        }
	        return $this->last_result;
	    }
	    else
	    {
	        if ($showerror)
	        {
	                // Retrieve actual error from MySQLi exception handling logic
	                $error_msg = ($this->link instanceof mysqli && mysqli_error($this->link)) ? mysqli_error($this->link) : ($this->last_error_msg ?? 'Unknown query error');
		$this->error('Bad query. ' . $error_msg);
            }
            else
	    {
    	return false;
            }
        }
    }

    function result($row_idx, $field, $query_id = 0)
    {
	if ( !$query_id )
	{
	    $query_id = $this->last_result;
	}

	if ( $query_id instanceof mysqli_result )
	{
            // Fix: mysqli_result function does not exist in PHP 8/Standard MySQLi
            // Implemented seeking and fetching logic
            if ($row_idx < 0 || $row_idx >= mysqli_num_rows($query_id)) {
                return false;
            }
            if (mysqli_data_seek($query_id, (int)$row_idx)) {
                $row_data = mysqli_fetch_array($query_id);
                return (is_array($row_data) && array_key_exists($field, $row_data)) ? $row_data[$field] : false;
            }
	}
	return false;
    }

    function escape($string)
    {
	if ( $this->link )
	{
            // PHP 8 Fix: Cast to string, null is deprecated
	    return mysqli_real_escape_string($this->link, (string)$string);
	}
    
	return '';
    }
    function error($message, $exit=true)
    {
        // Always log the full details server-side
        error_log('HLstatsX DB error: ' . (string)$message . ' | last query: ' . substr((string)$this->last_query, 0, 500));

        $out = "<b>Database Error</b><br />\n<br />\n";

        if (defined('DB_DEBUG') && DB_DEBUG == true) {
            $out .= "<i>Server Address:</i> " . htmlspecialchars((string)$this->db_addr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br />\n" .
                "<i>Server Username:</i> " . htmlspecialchars((string)$this->db_user, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br /><br />\n" .
                "<i>Error Diagnostic:</i><br />\n" . htmlspecialchars((string)$message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br /><br />\n";

            if ($this->link instanceof mysqli) {
                $out .= "<i>Server Error:</i> (" . mysqli_errno($this->link) . ") " . htmlspecialchars(mysqli_error($this->link), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br /><br />\n";
            }
            $out .= "<i>Last SQL Query:</i><br />\n<pre style=\"font-size:10px;\">" . htmlspecialchars($this->last_query, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</pre>";
        } else {
            $out .= "The database is currently unavailable. Please try again later.<br /><br />\n";
        }

        if (function_exists('error')) {
            error($out, $exit);
        } else {
            echo $out;
            if ($exit) {
                die();
            }
        }
    }
}
