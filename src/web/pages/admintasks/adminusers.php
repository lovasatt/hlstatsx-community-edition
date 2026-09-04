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

    // Extract logged-in admin details for validation
    $current_admin_level = (int)($auth->userdata["acclevel"] ?? 0);
    $current_admin_user  = trim((string)($auth->userdata["username"] ?? ''));

    if ($current_admin_level < 100) {
        die ("Access denied!");
    }

    $edlist = new EditList("username", "hlstats_Users", "user", false);
    $edlist->columns[] = new EditListColumn("username", "Username", 15, true, "text", "", 32);
    $edlist->columns[] = new EditListColumn("password", "Password", 15, true, "password", "", 128);
    $edlist->columns[] = new EditListColumn("acclevel", "Access Level", 25, true, "select", "0/No Access;80/Restricted;100/Administrator");

    if (!empty($_POST))
    {
        $validation_error = "";

        // Collect all currently registered usernames in lowercase
        $existing_users = [];
        $res = $db->query("SELECT LOWER(username) AS uname FROM hlstats_Users");
        while ($r = $db->fetch_array($res)) {
            $existing_users[] = strtolower(trim($r['uname']));
        }

        // 1. Prevent duplicate username when adding a NEW user
        $new_username = trim((string)($_POST['new_username'] ?? ''));
        if ($new_username !== '') {
            if (in_array(strtolower($new_username), $existing_users, true)) {
                $validation_error = "Error: The username " . htmlspecialchars($new_username) . " already exists! Choose another name.";
            }
        }

        // 2. Prevent renaming an EXISTING user to an already existing username
        if (!$validation_error && isset($_POST['rows']) && is_array($_POST['rows'])) {
            foreach ($_POST['rows'] as $row_user) {
                // If this row is not deleted, check if its username was altered
                if (empty($_POST[$row_user . '_delete'])) {
                    $edited_uname = trim((string)($_POST[$row_user . '_username'] ?? ''));
                    // If the username field exists and was changed to another existing username
                    if ($edited_uname !== '' && strtolower($edited_uname) !== strtolower($row_user)) {
                        if (in_array(strtolower($edited_uname), $existing_users, true)) {
                            $validation_error = "Error: Cannot rename '" . htmlspecialchars($row_user) . "' to '" . htmlspecialchars($edited_uname) . "' because that username is already taken!";
                            break;
                        }
                    }
                }
            }
        }

        // 3. Prevent self-deletion
        if (!$validation_error && !empty($_POST[$current_admin_user . '_delete'])) {
            $validation_error = "Error: You cannot delete your own administrator account!";
        }

        // 4. Prevent demoting own access level below Administrator (100)
        if (!$validation_error && isset($_POST[$current_admin_user . '_acclevel'])) {
            $my_new_level = (int)$_POST[$current_admin_user . '_acclevel'];
            if ($my_new_level < 100) {
                $validation_error = "Error: You cannot demote your own access level below Administrator (100)!";
            }
        }

        // 5. Ensure at least one Administrator (level >= 100) remains active
        if (!$validation_error) {
            $active_admin_count = 0;

            // Check existing admins from database
            $res = $db->query("SELECT username FROM hlstats_Users WHERE acclevel >= 100");
            while ($r = $db->fetch_array($res)) {
                $u = $r['username'];

                // If marked for deletion, skip
                if (!empty($_POST[$u . '_delete'])) {
                    continue;
                }

                // If level is being changed via dropdown to below 100, skip
                if (isset($_POST[$u . '_acclevel']) && (int)$_POST[$u . '_acclevel'] < 100) {
                    continue;
                }

                $active_admin_count++;
            }

            // If a new user is being added with level >= 100
            if ($new_username !== '') {
                $new_level = (int)($_POST['new_acclevel'] ?? 0);
                if ($new_level >= 100) {
                    $active_admin_count++;
                }
            }

            if ($active_admin_count < 1) {
                $validation_error = "Security error: There must always be at least one Administrator (level 100) remaining!";
            }
        }

        // Show friendly warning message instead of crashing with SQL Duplicate entry
        if (!empty($validation_error)) {
            message("warning", $validation_error);
        } else {
            if ($edlist->update())
                message("success", "Operation successful.");
            else
                message("warning", $edlist->error());
        }
    }
?>

<p>Usernames and passwords can be set up for access to this HLstats Admin area. For most sites you will only want one admin user - yourself. Some sites may however need to give administration access to several people.</p>

<p><b>Note:</b> Passwords are encrypted in the database and so cannot be viewed. However, you can change a user's password by entering a new plain text value in the Password field.</p>

<b>Access Levels</b><br /><br />

&bull; <i>Restricted</i> users only have access to the Host Groups, Clan Tag Patterns, Weapons, Teams, Awards and Actions configuration areas. This means these users cannot set Options or add new Games, Servers or Admin Users to HLstats, or use any of the admin Tools.<br /><br />
&bull; <i>Administrator</i> users have full, unrestricted access.<br /><br />

<?php

    $result = $db->query("
        SELECT
            username,
            IF(password='','','(encrypted)') AS password,
            acclevel
        FROM
            hlstats_Users
        ORDER BY
            username
    ");

    $edlist->draw($result);
?>

<table width="75%" border="0" cellspacing="0" cellpadding="0" style="margin:15px auto;">
<tr>
    <td align="center"><input type="submit" value="  Apply  " class="submit" /></td>
</tr>
</table>