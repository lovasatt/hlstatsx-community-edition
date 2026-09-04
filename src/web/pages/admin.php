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

// Enterprise HTTP Security Headers
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), camera=(), microphone=()");
}

// PHP 8 Fix: Initialize variables
  $game = (string)($game ?? '');
  if ( empty($game) )
  {
    $resultGames = $db->query("
        SELECT
            code,
            name
        FROM
            hlstats_Games
        WHERE
            hidden='0'
        ORDER BY
            name ASC 
        LIMIT 0,1

    ");
    // PHP 8 Fix: Replace list()
    $row = $db->fetch_row($resultGames);
    $game = ($row) ? $row[0] : '';
}

function hlstats_password_algo()
{
    if (defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)) {
        return PASSWORD_ARGON2ID;
    }
    return PASSWORD_DEFAULT;
}

function hlstats_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function hlstats_get_client_ip(): string
{
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ip_list[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = trim($_SERVER['HTTP_X_REAL_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function hlstats_ip_rate_limit(bool $record_failure = false, bool $reset = false): bool
{
    $ip = hlstats_get_client_ip();
    $file = sys_get_temp_dir() . '/hlx_lock_' . md5($ip) . '.json';

    if ($reset) {
        if (file_exists($file)) { @unlink($file); }
        return true;
    }

    $data = ['attempts' => 0, 'time' => time()];
    if (file_exists($file)) {
        $decoded = json_decode((string)@file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    if (time() - $data['time'] > 900) {
        $data['attempts'] = 0;
    }

    if ($record_failure) {
        $data['attempts']++;
        $data['time'] = time();
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }

    return ($data['attempts'] < 5);
}

function hlstats_verify_csrf(): bool
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
    }
    return true;
}

#[AllowDynamicProperties]
class Auth
{
    public $ok = false;
    public $error = false;

    public $username;
    public $password;
    public $savepass;
    public $sessionStart;
    public $session;

    public $userdata = array();

    function __construct()
    {
        global $db;

        if (session_status() === PHP_SESSION_NONE) {
            $is_https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
            ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $is_https,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }

        if (isset($_POST['authusername']) && valid_request($_POST['authusername'], false))
        {
            if (!hlstats_ip_rate_limit(false)) {
                http_response_code(429);
                $this->ok = false;
                $this->error = 'Too many failed login attempts from your IP. Access blocked for 15 minutes.';
                $this->printAuth();
                return;
            }

            $this->username = valid_request($_POST['authusername'], false);
            $this->password = (string)($_POST['authpassword'] ?? '');
            $this->savepass = valid_request($_POST['authsavepass'] ?? 0, false);
            $this->sessionStart = 0;
            $this->session = false;

            if ($this->checkPass() === true)
            {
                hlstats_ip_rate_limit(false, true);

                session_regenerate_id(true);
                unset($_SESSION['login_failed_attempts']);
                $_SESSION['username'] = $this->username;
                $_SESSION['authsessionStart'] = time();
                $_SESSION['authsessionAbsolute'] = time();
                $_SESSION['acclevel'] = isset($this->userdata['acclevel']) ? (int)$this->userdata['acclevel'] : 0;
                $_SESSION['loggedin'] = 1;
            }
            else
            {
                hlstats_ip_rate_limit(true);
            }
        }

        elseif (!empty($_SESSION['loggedin']))
        {
            $this->username = $_SESSION['username'] ?? '';
            $this->sessionStart = $_SESSION['authsessionStart'] ?? 0;
            $absoluteStart = $_SESSION['authsessionAbsolute'] ?? 0;

            if ($this->sessionStart > (time() - 3600) && $absoluteStart > (time() - 43200)) {
                $user_found = false;
                if (!empty($this->username) && isset($db)) {
                    $username_esc = $db->escape($this->username);
                    $res = $db->query("SELECT * FROM hlstats_Users WHERE username='$username_esc' LIMIT 1");
                    if ($res && $db->num_rows() == 1) {
                        $this->userdata = $db->fetch_array();
                        $db->free_result();
                        $_SESSION['acclevel'] = (int)($this->userdata['acclevel'] ?? 0);
                        $user_found = true;
                    }
                }

                if ($user_found) {
                    $this->ok = true;
                    $this->error = false;
                    $this->session = true;
                    $_SESSION['authsessionStart'] = time();
                } else {
                    unset($_SESSION['loggedin'], $_SESSION['username'], $_SESSION['authsessionStart'], $_SESSION['authsessionAbsolute'], $_SESSION['acclevel']);
                    $this->ok = false;
                    $this->error = 'Your account is no longer active. Please log in again.';
                    $this->printAuth();
                }
            } else {
                unset($_SESSION['loggedin'], $_SESSION['username'], $_SESSION['authsessionStart'], $_SESSION['authsessionAbsolute'], $_SESSION['acclevel']);
                $this->ok = false;
                $this->error = 'Your session has expired. Please try again.';
                $this->printAuth();
            }
        }
        else
        {
            $this->ok = false;
            $this->error = false;
            $this->session = false;
            $this->printAuth();
        }
    }

    function checkPass()
    {
        global $db;

        $target_algo = hlstats_password_algo();
        $username_esc = $db->escape($this->username);

        $db->query("
            SELECT
                *
            FROM
                hlstats_Users
            WHERE
                username='$username_esc'
            LIMIT 1
        ");

        if ($db->num_rows() == 1)
        {
            $this->userdata = $db->fetch_array();
            $db->free_result();

            // --- MODERN PASSWORD HANDLING START ---
            $input_pwd = (string)$this->password;
            $stored_hash = (string)$this->userdata["password"];
            $login_success = false;
            $rehash_needed = false;

            if (password_verify($input_pwd, $stored_hash)) {
                $login_success = true;
                if (password_needs_rehash($stored_hash, $target_algo)) {
                    $rehash_needed = true;
                }
            }
            elseif (preg_match('/^[a-f0-9]{32}$/i', $stored_hash) && hash_equals(strtolower($stored_hash), md5($input_pwd))) {
                $login_success = true;
                $rehash_needed = true;
            }

            if ($login_success)
            {
                if ($rehash_needed) {
                    // Check if the database column is too short for Argon2id/Bcrypt (< 255 chars) and self-heal it
                    $check_sql = $db->query("SHOW COLUMNS FROM `hlstats_Users` LIKE 'password'");
                    $col_info = $db->fetch_array($check_sql);
                    $col_type = (string)($col_info['Type'] ?? $col_info['type'] ?? '');
                    if ($col_info && preg_match('/varchar\((\d+)\)/i', $col_type, $matches) && (int)$matches[1] < 255) {
                        $db->query("ALTER TABLE `hlstats_Users` MODIFY `password` varchar(255) NOT NULL default ''");
                    }

                    try {
                        $new_hash = password_hash($input_pwd, $target_algo);
                        $new_hash_esc = $db->escape($new_hash);
                        $db->query("UPDATE hlstats_Users SET password='$new_hash_esc' WHERE username='$username_esc'");
                    } catch (\Throwable $e) {}
                }
                // --- MODERN PASSWORD HANDLING END ---

                $this->ok = true;
                $this->error = false;
                $_SESSION['loggedin'] = 1;
                $this->doCookies();
                return true;
            }
            else
            {
                // Wrong password for existing user (already processed by password_verify)
                $this->ok = false;
                $this->error = 'Invalid username or password.';
                $this->password = '';
                $this->printAuth();
                return false;
            }
        }
        else
        {
            // Equalize response latency against timing attacks when username does not exist
            password_verify((string)$this->password, '$argon2id$v=19$m=65536,t=4,p=1$dummyhashdummyhash$dummyhashdummyhashdummyhashdummyhashdummyhash');
            $this->ok = false;
            $this->error = 'Invalid username or password.';
            $this->printAuth();
            return false;
        }
    }

    function doCookies()
    {
        $is_https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
                    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
                    ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

        $cookie_options = array(
            'expires'  => time() + 31536000,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        );
        setcookie('authusername', $this->username, $cookie_options);
        $save_options = $cookie_options;
        $save_options['expires'] = $this->savepass ? (time() + 31536000) : 0;
        setcookie('authsavepass', $this->savepass, $save_options);
        setcookie('authsessionStart', time(), $save_options);
        setcookie('authpassword', '', time() - 3600, '/');
    }

    function printAuth()
    {
    global $g_options;

    include (PAGE_PATH . '/adminauth.php');
    }
}

#[AllowDynamicProperties]
class AdminTask
{
    public $title = '';
    public $acclevel = 0;
    public $type = '';
    public $description = '';
    public $group = '';

    function __construct($title, $acclevel, $type = 'general', $description = '', $group = '')
    {
    $this->title = $title;
    $this->acclevel = $acclevel;
    $this->type = $type;
    $this->description = $description;
    $this->group = $group;
    }
}

#[AllowDynamicProperties]
class EditList
{
    public $columns;
    public $keycol;
    public $table;
    public $deleteCallback;
    public $icon;
    public $showid;
    public $drawDetailsLink;
    public $DetailsLink;

    public $errors;
    public $newerror;

    public $helpTexts;
    public $helpKey;
    public $helpDIV;

    function __construct($keycol, $table, $icon, $showid = true, $drawDetailsLink = false, $DetailsLink = '', $deleteCallback = null)
    {
    $this->keycol = $keycol;
    $this->table = $table;
    $this->icon = $icon;
    $this->showid = $showid;
    $this->drawDetailsLink = $drawDetailsLink;
    $this->DetailsLink = $DetailsLink;
    $this->helpKey = '';
    $this->deleteCallback = $deleteCallback;
    $this->errors = array();
    $this->newerror = false;
    }

    function setHelp($div, $key, $texts)
    {
        $this->helpDIV = $div;
        $this->helpKey = $key;
        $this->helpTexts = $texts;

        if ($this->helpKey == '' || empty($this->helpTexts)) {
            return '';
        }

        $json_texts = json_encode($this->helpTexts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        $div_id_safe = htmlspecialchars($this->helpDIV, ENT_QUOTES, 'UTF-8');
        $clean_div = preg_replace('/[^a-zA-Z0-9_]/', '', $this->helpDIV);

        $json_div_id = json_encode((string)$this->helpDIV, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $returnstr  = "<script type='text/javascript'>\n";
        $returnstr .= "var texts_" . $clean_div . " = " . $json_texts . ";\n";
        $returnstr .= "function showHelp(keyname) {\n";
        $returnstr .= "    var elem = document.getElementById(" . $json_div_id . ");\n";
        $returnstr .= "    var dict = texts_" . $clean_div . ";\n";
        $returnstr .= "    if (elem && dict && dict[keyname]) {\n";
        $returnstr .= "        elem.textContent = dict[keyname];\n";
        $returnstr .= "        elem.style.visibility = 'visible';\n";
        $returnstr .= "    }\n";
        $returnstr .= "}\n";
        $returnstr .= "function hideHelp() {\n";
        $returnstr .= "    var elem = document.getElementById(" . $json_div_id . ");\n";
        $returnstr .= "    if (elem) elem.style.visibility = 'hidden';\n";
        $returnstr .= "}\n";
        $returnstr .= "</script>\n";
        $returnstr .= '<div class="helpwindow" id="' . $div_id_safe . '">No help text available</div>';

        return $returnstr;
    }

    function update()
    {
    global $db;

    $okcols = 0;
        // Initialize variables
        $qcols = '';
        $qvals = '';

        // Columns that must be integers
        $int_columns = array(
            'reward_player', 'reward_team', 'awardCount', 'minKills', 
            'maxKills', 'weight', 'acclevel', 'kills', 'headshots', 'count'
        );

    foreach ($this->columns as $col) {
        $post_key = "new_$col->name";
        // PHP 8 Fix: Correctly handle '0' string. !empty() returns false for '0'.
        $raw_value = $_POST[$post_key] ?? null;
        $value = ($raw_value !== null && $raw_value !== '') ? trim($raw_value) : '';

        // MySQL Strict Mode Fix
        if ($value === '' && in_array($col->name, $int_columns)) {
            $value = '0';
        }

        if ($value != '')
        {
    if ($col->type == 'ipaddress' && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false)
    {
        $this->errors[] = "Column '$col->title' requires a valid IP address for new row";
        $this->newerror = true;
        $okcols++;
    }
    else
    {
        if ($qcols)
        {
        $qcols .= ', ';
        }
        $qcols .= $col->name;

        if ($qvals)
        {
        $qvals .= ', ';
        }
        if ($col->type == 'password' && $this->table == 'hlstats_Users')
        {
            $value = password_hash(
                (string)$value,
                hlstats_password_algo()
            );
        }
        $qvals .= "'" . $db->escape($value) . "'";

        if ($col->type != 'select' && $col->type != 'hidden' && $value != $col->datasource)
        {
        $okcols++;
        }
    }
        }
        elseif ($col->required)
        {
    $this->errors[] = "Required column '$col->title' must have a value for new row";
    $this->newerror = true;
        }
    }

    if ($okcols > 0 && !$this->errors)
    {
        $db->query("
        INSERT INTO
        $this->table
        (
            $qcols
        )
        VALUES
        (
        $qvals
        )");
    }
    elseif ($okcols == 0)
    {
        $this->errors = array();
        $this->newerror = false;
    }

    if (!isset($_POST['rows']) || !is_array($_POST['rows']))
    {
        return true;
    }

    foreach ($_POST['rows'] as $row)
    {
        if (!empty($_POST[$row . '_delete'])) {
    if (!empty($this->deleteCallback) && is_callable($this->deleteCallback)) {
        call_user_func($this->deleteCallback, $row);
    }

    $db->query("
        DELETE FROM
        $this->table
        WHERE
        $this->keycol='" . $db->escape($row) . "'
    ");
        }
        else
        {
    $rowerror = false;

    $query = "UPDATE $this->table SET ";
    $i = 0;
    foreach ($this->columns as $col)
    {
        if ($col->type == 'readonly')
        {
        continue;
        }

            $post_key = $row . "_" . $col->name;
            $raw_value = $_POST[$post_key] ?? null;

            if ($raw_value !== null && $raw_value !== '') {
                $value = $raw_value;
            } else {
                $value = null;
            }

        if ($col->type == 'checkbox' && $value === null)
        {
            $value = '0';
        }

        if ($col->type == 'password')
        {
            if ($this->table == 'hlstats_Users' && ($value === null || $value === '' || $value === '(encrypted)'))
            {
                continue;
            }
            elseif ($value === '(encrypted)')
            {
                continue;
            }
        }

        // MySQL Strict Mode Fix
        if (($value === null || $value === '') && in_array($col->name, $int_columns)) {
            $value = '0';
        }

        if (($value === null || $value === '') && $col->required)
        {
        $this->errors[] = "Required column '$col->title' must have a value for row '$row'";
        $rowerror = true;
        }
        elseif ($col->type == "ipaddress" && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false)
        {
        $this->errors[] = "Column '$col->title' requires a valid IP address for row '$row'";
        $rowerror = true;
        }

        if ($i > 0)
        {
        $query .= ', ';
        }

        if ($col->type == 'password' && $this->table == 'hlstats_Users')
        {
            // Modern password hashing: use the configured password algorithm
            $hashed_val = password_hash((string)$value, hlstats_password_algo());
            $query .= $col->name . "='" . $db->escape($hashed_val) . "'";
        }
        else
        {
            $query .= $col->name . "='" . $db->escape($value) . "'";
        }
        $i++;
    }
    $query .= " WHERE $this->keycol='" . $db->escape($row) . "'";

    if (!$rowerror && $i > 0)
    {
        $db->query($query);
    }
        }
    }

    if ($this->error()) {
        return false;
    }

        return true;
    }

    function draw($result, $draw_new = true)
    {
    global $g_options, $db;


?>
<table class="data-table" style="width:100%;margin:auto;">

    <tr class="head data-table-head" style="vertical-align:bottom;">
<?php
    echo '<th style="width:24px;text-align:center;"></th>';

    if ($this->showid)
    {
?>
        <th align="right" class="fSmall">ID</th>
<?php
    }

    foreach ($this->columns as $col)
    {
        if ($col->type == 'hidden')
        {
            continue;
        }
        echo '<th class="fSmall" align="left">' . $col->title . "</th>\n";
    }

    if ($this->drawDetailsLink)
    {
?>
        <th align="right" class="fSmall"></th>
<?php
    }
?>
        <th align="center" class="fSmall" style="width:50px;">Delete</th>
    </tr>
<?php
    $row_idx = 0;
    while ($rowdata = $db->fetch_array($result))
    {
        $row_class = ($row_idx % 2 == 0) ? 'bg1' : 'bg2';
        echo "\n<tr class=\"$row_class\" style=\"vertical-align:middle;\">\n";
        echo '<td align="center">';
        if (file_exists(IMAGE_PATH . "/$this->icon.gif"))
        {
            echo '<img src="' . IMAGE_PATH . "/$this->icon.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"\" />";
        }
        else
        {
            echo '<img src="' . IMAGE_PATH . "/server.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"\" />";
        }
        echo "</td>\n";

        if ($this->showid)
        {
            echo '<td align="right" class="fSmall">' . $rowdata[$this->keycol] . "</td>\n";
        }

        $this->drawfields($rowdata, false, false);

        if ($this->drawDetailsLink)
        {
            global $gamecode;
?>
        <td align="center" class="fSmall"><?php
            echo "<a href='" . $g_options["scripturl"] . "?mode=admin&amp;game=$gamecode&amp;task=" . $this->DetailsLink . "&amp;key=" . $rowdata[$this->keycol] . "'><b>CONFIGURE</b></a>";
        ?></td>
<?php
        }

?>
<td align="center"><input type="checkbox" name="<?php echo $rowdata[$this->keycol]; ?>_delete" value="1" /></td>
<?php echo "</tr>\n\n";
        $row_idx++;
    }

    if ( $draw_new )
    {
        $new_class = ($row_idx % 2 == 0) ? 'bg1' : 'bg2';
        echo "<tr class=\"$new_class\" style=\"vertical-align:middle;\">\n";
        echo "<td class=\"fSmall\" align=\"center\"><b>new</b></td>\n";

        if ($this->showid)
            echo "<td class=\"fSmall\" align=\"right\">&nbsp;</td>\n";

        if ($this->newerror)
        {
            $this->drawfields($_POST, true, true);
        }
        else
        {
            $this->drawfields(array(), true);
        }

        echo "<td></td>\n";
        echo "</tr>\n";
    }
?>
</table><br /><br />
<?php
    }

    function drawfields($rowdata = array(), $new = false, $stripslashes = false)
    {
    global $g_options, $db;

    $i = 0;
    foreach ($this->columns as $col)
    {
        if ($new)
        {
    $keyval = 'new';
    $rowdata[$col->name] = isset($rowdata["new_$col->name"]) ? $rowdata["new_$col->name"] : '';
    if ($stripslashes)
        $rowdata[$col->name] = mystripslashes($rowdata[$col->name]);
        }
        else
        {
    $keyval = isset($rowdata[$this->keycol]) ? $rowdata[$this->keycol] : '';
    if ($stripslashes)
        $keyval = mystripslashes($keyval);

        }

        if ($col->type != 'hidden')
        {
    echo '<td>';
        }

        if ($i == 0 && !$new)
        {
    echo '<input type="hidden" name="rows[]" value="' . htmlspecialchars((string)$keyval) . '" />';
        }

        if ($col->maxlength < 1)
        {
    $col->maxlength = '';
        }

        switch ($col->type)
        {
    case 'select':
        unset($coldata);
        $coldata = array();

        foreach (explode(';', (string)$col->datasource) as $v)
        {
            $sections = preg_match_all('/\//', $v, $dsaljfdsaf);
            if ($sections == 2)
            {
                $parts = explode('.', $v, 2);
                $col_table = $parts[0] ?? '';
                $col_rest  = $parts[1] ?? '';
                $col_parts = explode('/', $col_rest, 3);
                $col_col   = $col_parts[0] ?? '';
                $col_key   = $col_parts[1] ?? '';
                $col_where = $col_parts[2] ?? '';

                if ($col_where)
                {
                    $col_where = "WHERE $col_where";
                }
                $col_result = $db->query("SELECT $col_key, $col_col FROM $col_table $col_where ORDER BY $col_col");
                while ($row_res = $db->fetch_row($col_result))
                {
                    $coldata[$row_res[0]] = $row_res[1];
                }
            }
            else if ($sections > 0)
            {
                $parts = explode('/', $v, 2);
                $coldata[$parts[0]] = $parts[1] ?? '';
            }
            else
            {
                $coldata[$v] = $v;
            }
        }

        if ($col->width)
        {
        $width = ' style="width:' . $col->width * 5 . 'px"';
        }
        else
        {
        $width = '';
        }

        echo "<select name=\"" . $keyval . "_$col->name\"$width>\n";

        if (!$col->required)
        {
        echo "<option value=\"\"></option>\n";
        }

        $gotcval = false;

        foreach ($coldata as $k => $v)
        {
            $val_str = (string)($rowdata[$col->name] ?? '');
            $k_str = (string)$k;

        if (isset($rowdata[$col->name]) && $val_str === $k_str)
        {
            $selected = ' selected="selected"';
            $gotcval = true;
        }
        else
        {
            $selected = '';
        }
        echo '<option value="' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . "</option>\n";
        }

        if (!$gotcval && isset($rowdata[$col->name]))
        {
            $safe_val = htmlspecialchars((string)$rowdata[$col->name], ENT_QUOTES, 'UTF-8');
            echo '<option value="' . $safe_val . '" selected="selected">' . $safe_val . "</option>\n";
        }

        echo '</select>';
        break;

    case 'checkbox':
        $selectedval = '1';
        $value = isset($rowdata[$col->name]) ? $rowdata[$col->name] : '';

        if ($value == $selectedval)
        {
        $selected = ' checked="checked"';
        }
        else
        {
        $selected = '';
        }

        echo '<center><input type="checkbox" name="' . $keyval . "_$col->name\" value=\"$selectedval\"$selected /></center>";
        break;

    case 'hidden':
        echo '<input type="hidden" name="' . $keyval . "_$col->name\" value=\"" . htmlspecialchars((string)$col->datasource) . '" />';
        break;

    case 'readonly':
        if (!$new)
        {
        echo htmlspecialchars((string)($rowdata[$col->name] ?? ''), ENT_QUOTES, 'UTF-8');
        break;
        }

    default:
        $onclick = '';
        if ($col->type == 'password') {
        $onclick = " onclick=\"if (this.value == '(encrypted)') this.value='';\"";
        }

        if ($col->datasource != '' && !isset($rowdata[$col->name]))
        {
        $value = $col->datasource;
        }
        else
        {
        $value = isset($rowdata[$col->name]) ? $rowdata[$col->name] : '';
        }
        if ($col->type == 'password' && !$new && !empty($value) && $this->table == 'hlstats_Users')
        {
            $value = '(encrypted)';
        }
        $onClick = '';
        if (!empty($this->helpKey) && !empty($rowdata[$this->helpKey])) {
        $onClick = "onmouseover=\"javascript:showHelp('" . strtolower($rowdata[$this->helpKey]) . "')\" onmouseout=\"javascript:hideHelp()\"";
        }

        $input_value = (!empty($value) || $value === '0') ? htmlspecialchars(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') : "";

        if ($col->type == 'password')
        {
            $field_id = 'pwd_' . $keyval . '_' . $col->name;
            echo "<span style=\"white-space: nowrap;\">";
            echo "<input id=\"$field_id\" $onClick type=\"password\" name=\"" . $keyval . "_$col->name\" size=\"$col->width\" value=\"" . $input_value . "\" class=\"textbox\" maxlength=\"$col->maxlength\"$onclick /> ";
            echo "<button type=\"button\" style=\"background:none;border:none;cursor:pointer;font-size:14px;padding:0 2px;vertical-align:middle;user-select:none;\" "
                . "onmousedown=\"document.getElementById('$field_id').type='text';\" "
                . "onmouseup=\"document.getElementById('$field_id').type='password';\" "
                . "onmouseleave=\"document.getElementById('$field_id').type='password';\" "
                . "ontouchstart=\"document.getElementById('$field_id').type='text';\" "
                . "ontouchend=\"document.getElementById('$field_id').type='password';\" "
                . "title=\"Hold to reveal password\">👁</button>";
            echo "</span>";
        }
        else
        {
            echo "<input $onClick type=\"text\" name=\"" . $keyval . "_$col->name\" size=\"$col->width\" value=\"" . $input_value . "\" class=\"textbox\" maxlength=\"$col->maxlength\"$onclick />";
        }
        }
        if ($col->type != 'hidden')
        {
    echo "</td>\n";
        }

        $i++;
        }
    }

    function error()
    {
    if (is_array($this->errors))
    {
        return implode("<br /><br />\n\n", $this->errors);
    }
    else
    {
        return false;
    }
    }
}

#[AllowDynamicProperties]
class EditListColumn
{
    public $name;
    public $title;
    public $width;
    public $required;
    public $type;
    public $datasource;
    public $maxlength;

    function __construct($name, $title, $width = 20, $required = false, $type = 'text', $datasource = '', $maxlength = 0)
    {
    $this->name = $name;
    $this->title = $title;
    $this->width = $width;
    $this->required = $required;
    $this->type = $type;
    $this->datasource = $datasource;
    $this->maxlength = intval($maxlength);
    }
}

#[AllowDynamicProperties]
class PropertyPage
{
    public $table;
    public $keycol;
    public $keyval;
    public $propertygroups = array();

    function __construct($table, $keycol, $keyval, $groups)
    {
    $this->table = $table;
    $this->keycol = $keycol;
    $this->keyval = $keyval;
    $this->propertygroups = $groups;
    }

    function draw($data)
    {
    foreach ($this->propertygroups as $group)
    {
        $group->draw($data);
    }
    }

    function update()
    {
        global $db;

        $setstrings = array();
        foreach ($this->propertygroups as $group)
        {
            foreach ($group->properties as $prop)
            {
                if ($prop->type == 'password')
                {
                    $raw_pwd = (string)($_POST[$prop->name] ?? '');
                    if ($raw_pwd === '') {
                        continue;
                    }
                    $hashed_pwd = password_hash($raw_pwd, hlstats_password_algo());
                    $setstrings[] = $prop->name . "='" . $db->escape($hashed_pwd) . "'";
                }
                elseif ($prop->name == 'name')
                {
                    $value = isset($_POST[$prop->name]) ? $_POST[$prop->name] : '';
                    $setstrings[] = $prop->name . "='" . $db->escape($value) . "'";
                }
                else
                {
                    $val = isset($_POST[$prop->name]) ? $_POST[$prop->name] : '';
                    $setstrings[] = $prop->name . "='" . $db->escape(valid_request($val, 0)) . "'";
                }
            }
        }

        if (!empty($setstrings)) {
            $db->query("
            UPDATE
                " . $this->table . "
            SET
                " . implode(",\n", $setstrings) . "
            WHERE
                " . $this->keycol . "='" . $db->escape($this->keyval) . "'
            ");
        }
    }
}

#[AllowDynamicProperties]
class PropertyPage_Group
{
    public $title = '';
    public $properties = array();

    function __construct($title, $properties)
    {
    $this->title = $title;
    $this->properties = $properties;
    }

    function draw($data)
    {
        global $g_options;
?>
<p><strong><?php echo htmlspecialchars($this->title, ENT_QUOTES, 'UTF-8'); ?></strong></p>
<table class="data-table" style="width:100%;margin:auto;">
<?php
        foreach ($this->properties as $prop)
        {
            $val = isset($data[$prop->name]) ? $data[$prop->name] : '';
            $prop->draw($val);
        }
?>
</table><br /><br />
<?php
    }
}

#[AllowDynamicProperties]
class PropertyPage_Property
{
    public $name;
    public $title;
    public $type;
    public $datasource;

    function __construct($name, $title, $type, $datasource = '')
    {
    $this->name = $name;
    $this->title = $title;
    $this->type = $type;
    $this->datasource = $datasource;
    }

    function draw($value)
    {
    global $g_options;
?>
<tr style="vertical-align:middle;">
    <td class="bg1" style="width:45%;"><?php
    echo $this->title . ':';
?></td>
    <td class="bg1" style="width:55%;"><?php
    switch ($this->type)
    {
        case 'textarea':
    echo "<textarea name=\"$this->name\" cols=35 rows=4 wrap=\"virtual\">" . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</textarea>';
    break;

    case 'select':
          $coldata = array();
          foreach (explode(';', (string)$this->datasource) as $v)
          {
              if (strpos($v, '/') !== false)
              {
                  $parts = explode('/', $v, 2);
                  $coldata[$parts[0]] = $parts[1] ?? $parts[0];
              }
              else
              {
                  $coldata[$v] = $v;
              }
          }

    echo getSelect($this->name, $coldata, $value);
    break;

        case 'password':
            $field_id = 'prop_' . htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8');
            echo "<span style=\"white-space: nowrap;\">";
            echo "<input id=\"$field_id\" type=\"password\" name=\"$this->name\" size=\"35\" value=\"\" placeholder=\"(leave blank to keep unchanged)\" class=\"textbox\" /> ";
            echo "<button type=\"button\" style=\"background:none;border:none;cursor:pointer;font-size:14px;padding:0 2px;vertical-align:middle;user-select:none;\" "
                . "onmousedown=\"document.getElementById('$field_id').type='text';\" "
                . "onmouseup=\"document.getElementById('$field_id').type='password';\" "
                . "onmouseleave=\"document.getElementById('$field_id').type='password';\" "
                . "ontouchstart=\"document.getElementById('$field_id').type='text';\" "
                . "ontouchend=\"document.getElementById('$field_id').type='password';\" "
                . "title=\"Hold to reveal password\">👁</button>";
            echo "</span>";
            break;
        default:
    echo "<input type=\"text\" name=\"$this->name\" size=35 value=\"" . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . "\" class=\"textbox\" />";
    break;
    }
?>
</td>
</tr>
<?php
    }
}

function message($icon, $msg)
{
    global $g_options;
?>
    <table width="60%" border="0" cellspacing="0" cellpadding="0">

    <tr valign="top">
        <td width="40"><img src="<?php echo IMAGE_PATH . "/" . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>.gif" width="16" height="16" border="0" hspace="5" alt="" /></td>
        <td width="100%"><?php
    echo "<b>" . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</b>";
?></td>
    </tr>

    </table><br /><br />
<?php
}

$auth = new Auth;
if($auth->ok===false)
{
    return;
}

pageHeader(array('Admin'), array('Admin' => ''));

$selTask = isset($_GET['task']) ? valid_request($_GET['task'], false) : false;
$selGame = isset($_GET['game']) ? valid_request($_GET['game'], false) : false;
$mode = isset($_GET['mode']) ? valid_request($_GET['mode'], false) : '';
?>

<table width="100%" align="center" border="0" cellspacing="0" cellpadding="0">

<tr valign="top">
    <td><?php

    $version_out = isset($g_options['version']) ? $g_options['version'] : 'Unknown';
    $dbversion_out = isset($g_options['dbversion']) ? $g_options['dbversion'] : 0;

$updateDbHtml = "<div>
        Current Version: <span style=\"color: #C40000;font-weight:bold\">{$version_out}</span><br />
        Current DB version: <span style=\"color: #C40000;font-weight:bold\">{$dbversion_out}</span><br />";
if (file_exists('./updater') && $mode != 'updater') {
    if (file_exists("./updater/" . ($dbversion_out + 1) . ".php")) {
    $updateDbHtml .= "
        <div class=\"block\">
        <div class=\"warning\">
        <span class=\"fHeading\"><img src=\"" . IMAGE_PATH . "/warning.gif\" alt=\"Warning\"> Warning:</span> Your database needs an upgrade. To perform a Database Update, please go to the Updater page.
            <div style=\"text-align: center;\"><strong><a class=\"fMediumLarge\" href=\"{$g_options['scripturl']}?mode=updater&amp;task=tools_updater\"><span>HLX:CE Database Updater</span></a></strong></div>
        </div>
        </div>";
    } else {
    $updateDbHtml .= "Great. Your database is the latest version.";
    }
}
$updateDbHtml .= "</div>";

// General Settings
$admintasks['options'] = new AdminTask('HLstatsX:CE Settings', 80);
$admintasks['adminusers'] = new AdminTask('Admin Users', 100);
$admintasks['games'] = new AdminTask('Games', 80);
$admintasks['hostgroups'] = new AdminTask('Host Groups', 100);
$admintasks['clantags'] = new AdminTask('Clan Tag Patterns', 80);
$admintasks['voicecomm'] = new AdminTask('Manage Voice Servers', 80);
$admintasks['map_regions'] = new AdminTask('Map Regions', 80);

// Game Settings
$admintasks['newserver'] = new AdminTask('Add Server', 80, 'game');
$admintasks['servers'] = new AdminTask('Edit Servers', 80, 'game');
$admintasks['serversettings'] = new AdminTask('&nbsp;&nbsp;&nbsp;&gt;&gt;&nbsp;Server Details', 80, 'game');
$admintasks['actions'] = new AdminTask('Actions', 80, 'game');
$admintasks['teams'] = new AdminTask('Teams', 80, 'game');
$admintasks['roles'] = new AdminTask('Roles', 80, 'game');
$admintasks['weapons'] = new AdminTask('Weapons', 80, 'game');
$admintasks['awards_weapons'] = new AdminTask('Weapon Awards', 80, 'game');
$admintasks['awards_plyractions'] = new AdminTask('Plyr Action Awards', 80, 'game');
$admintasks['awards_plyrplyractions'] = new AdminTask('PlyrPlyr Action Awards', 80, 'game');
$admintasks['awards_plyrplyractions_victim'] = new AdminTask('PlyrPlyr Action Awards (Victim)', 80, 'game');
$admintasks['ranks'] = new AdminTask('Ranks (triggered by Kills)', 80, 'game');
$admintasks['ribbons'] = new AdminTask('Ribbons (triggered by Awards)', 80, 'game');

// Tools
$admintasks['tools_updater'] = new AdminTask('DB Updater', 100, 'tool', $updateDbHtml);
$admintasks['tools_perlcontrol'] = new AdminTask('HLstatsX: CE Daemon Control', 80, 'tool', 'Reload or stop your HLX: CE Daemons');
$admintasks['tools_editdetails'] = new AdminTask('Edit Player or Clan Details', 80, 'tool', 'Edit a player or clan\'s profile information.');
$admintasks['tools_adminevents'] = new AdminTask('Admin-Event History', 80, 'tool', 'View event history of logged Rcon commands and Admin Mod messages.');
$admintasks['tools_ipstats'] = new AdminTask('Host Statistics', 80, 'tool', 'See which ISPs your players are using.');
$admintasks['tools_optimize'] = new AdminTask('Optimize Database', 100, 'tool', 'This operation tells the MySQL server to clean up the database tables, optimizing them for better performance. It is recommended that you run this at least once a month.');
$admintasks['tools_resetdbcollations'] = new AdminTask('Reset All DB Collations to UTF8', 100, 'tool', 'Reset DB Collations to UTF-8 if you receive collation errors after an upgrade from another HLstats(X)-based system.');

// Sub-Tools
$admintasks['tools_editdetails_player'] = new AdminTask('Edit Player Details', 80, 'subtool', 'Edit a player\'s profile information.');
$admintasks['tools_editdetails_clan'] = new AdminTask('Edit Clan Details', 80, 'subtool', 'Edit a clan\'s profile information.');

// Reset Tools
$admintasks['tools_reset'] = new AdminTask('Full or Partial Reset', 100, 'tool', 'Resets chosen data globally or for selected game', 'reset');
$admintasks['tools_reset_2'] = new AdminTask('Clean up Statistics', 100, 'tool', 'Delete all inactive players, clans and corresponding events from the database.', 'reset');
$admintasks['tools_purge_orphans'] = new AdminTask('Purge Orphaned Data & Images', 100, 'tool', 'Cleans up statistics and graph images for games and servers that have been deleted.', 'reset');

// Game Settings Tools
$admintasks['tools_settings_copy'] = new AdminTask('Duplicate Game settings', 80, 'tool', 'Duplicate a whole game settings tree to split servers of same gametype', 'settingstool');

// --- CENTRALIZED ACCESS CONTROL AND CSRF PROTECTION ---
$user_acclevel = (int)($auth->userdata['acclevel'] ?? $_SESSION['acclevel'] ?? 0);

// Global CSRF verification for all incoming POST requests (except login)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['authusername']) && !hlstats_verify_csrf()) {
    http_response_code(403);
    message('warning', 'Security token expired or invalid (CSRF detected). Please refresh the page and try again.');
    return;
}

// Task-level authorization check
if (!empty($selTask)) {
    if (!isset($admintasks[$selTask])) {
        message('warning', 'The requested administration task does not exist.');
        return;
    }

    $current_task = $admintasks[$selTask];

    if ($user_acclevel < $current_task->acclevel) {
        http_response_code(403);
        message('warning', 'Access Denied: You do not have permission to access this area.');
        return;
    }
}

// Show Tool
if (!empty($selTask) && !empty($admintasks[$selTask]) && ($admintasks[$selTask]->type == 'tool' || $admintasks[$selTask]->type == 'subtool'))
{
    $task = $admintasks[$selTask];
    $code = $selTask;
?>
&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" alt="" /><b>&nbsp;<a href="<?php echo $g_options['scripturl']; ?>?mode=admin">Tools</a></b><br />
<img src="<?php echo IMAGE_PATH; ?>/spacer.gif" width="1" height="8" border="0" alt="" /><br />

<?php
    // Automatically inject CSRF token into daemon control and other tool forms
    ob_start();
    include (PAGE_PATH . "/admintasks/$code.php");
    $tool_html = ob_get_clean();

    $csrf_input = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(hlstats_csrf_token(), ENT_QUOTES, 'UTF-8') . '" />';
    $tool_html = preg_replace('/(<form\b[^>]*>)/i', '$1' . "\n" . $csrf_input, $tool_html);

    echo $tool_html;
}
else
{
    // General Settings

?>
&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" alt="" /><b>&nbsp;General Settings</b><br /><br />
<?php
    foreach ($admintasks as $code => $task)
    {
    if ((isset($auth->userdata['acclevel']) ? $auth->userdata['acclevel'] : 0) >= $task->acclevel && $task->type == 'general')
    {
        if ($selTask == $code)
        {
?>
&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" alt="" /><b>&nbsp;<a href="<?php echo $g_options['scripturl']; ?>?mode=admin" name="<?php echo $code; ?>"><?php echo $task->title; ?></a></b><br /><br />

<form method="post" action="<?php echo $g_options['scripturl']; ?>?mode=admin&amp;task=<?php echo $code; ?>#<?php echo $code; ?>">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(hlstats_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>" />

<table width="100%" border="0" cellspacing="0" cellpadding="0">

<tr>
    <td width="2%">&nbsp;</td>
    <td width="98%"><?php
    include (PAGE_PATH . "/admintasks/$code.php");
?></td>
</tr>

</table><br /><br />
</form>
<?php
        }
        else
        {
?>
&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/rightarrow.gif" width="6" height="9" alt="" /><b>&nbsp;<a href="<?php echo $g_options['scripturl']; ?>?mode=admin&amp;task=<?php echo $code; ?>#<?php echo $code;
?>"><?php echo $task->title; ?></a></b><br /><br /> <?php
        }
    }
    }
?>

&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" alt="" /><b>&nbsp;Game Settings</b><br /><br />
<?php
    $gamesresult = $db->query("
        SELECT
    name,
    code
        FROM
    hlstats_Games
        WHERE
    hidden = '0'
        ORDER BY
    name ASC
        ;
    ");

    while ($gamedata = $db->fetch_array($gamesresult))
    {
    $gamename = $gamedata['name'];
    $gamecode = $gamedata['code'];

    if ($gamecode == $selGame)
    {
?>
&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" alt="" /><b>&nbsp;<a href="<?php echo htmlspecialchars($g_options['scripturl'], ENT_QUOTES, 'UTF-8'); ?>?mode=admin" name="game_<?php echo htmlspecialchars((string)$gamecode, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($gamename, ENT_QUOTES, 'UTF-8'); ?></a></b> (<?php echo htmlspecialchars((string)$gamecode, ENT_QUOTES, 'UTF-8'); ?>)<br /><br /> <?php
        foreach ($admintasks as $code => $task)
        {
    if ((isset($auth->userdata['acclevel']) ? $auth->userdata['acclevel'] : 0) >= $task->acclevel && $task->type == 'game')
    {
        if ($selTask == $code)
        {
?>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" alt="" /><b>&nbsp;<a href="<?php echo $g_options['scripturl']; ?>?mode=admin&amp;game=<?php echo $gamecode; ?>" name="<?php echo $code; ?>"><?php echo $task->title; ?></a></b><br /><br />

<form method="post" name="<?php echo $code; ?>form" action="<?php echo $g_options['scripturl']; ?>?mode=admin&amp;game=<?php echo $gamecode; ?>&amp;task=<?php echo $code; ?>#<?php echo $code; ?>">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(hlstats_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>" />

<table width="100%" border="0" cellspacing="0" cellpadding="0">

<tr>
    <td width="10%">&nbsp;</td>
    <td width="90%"><?php
        include (PAGE_PATH . "/admintasks/$code.php");
?></td>
</tr>

</table><br /><br />
</form>
<?php
        }
        elseif ($code != 'serversettings')
        {
    ?>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/rightarrow.gif" width="6" height="9" alt="" /><b>&nbsp;<a href="<?php echo $g_options['scripturl']; ?>?mode=admin&amp;game=<?php echo $gamecode; ?>&amp;task=<?php echo $code; ?>#<?php echo $code; ?>"><?php echo $task->title; ?></a></b><br /><br /> <?php
        }
    }
        }
    }
    else
    {
?>
&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/rightarrow.gif" width="6" height="9" alt="" /><b>&nbsp;<a href="<?php echo $g_options['scripturl']; ?>?mode=admin&amp;game=<?php echo $gamecode; ?>#game_<?php echo $gamecode; ?>"><?php echo $gamename; ?></a></b> (<?php echo $gamecode; ?>)<br /><br /> <?php
    }
    }
}
echo "</td>\n";

if (!$selTask || !isset($admintasks[$selTask]))
{
    echo '<td width="50%">';
?>
&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" alt="" /><b>&nbsp;Tools</b>

<ul>
<?php
    foreach ($admintasks as $code => $task)
    {
        $acclevel = isset($auth->userdata['acclevel']) ? $auth->userdata['acclevel'] : 0;
    if ($acclevel >= $task->acclevel && $task->type == 'tool')
    {
?>
<li><b><a href="<?php echo htmlspecialchars($g_options['scripturl'], ENT_QUOTES, 'UTF-8'); ?>?mode=admin&amp;task=<?php echo htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8'); ?></a></b><br />
    <?php echo $task->description; ?><br /><br />
</li>
<?php
    }
    }
?>
</ul>
<?php
    echo '</td>';
}
?>
</tr>

</table>

<?php
if (isset($footerscript))
{
    echo $footerscript;
}
