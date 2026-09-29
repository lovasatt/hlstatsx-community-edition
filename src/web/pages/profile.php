<?php

if (!defined('IN_HLSTATS')) {
    die('Do not access this file directly.');
}

global $auth, $db;

if (($auth->userdata['acclevel'] ?? 0) < 80) {
    die('Access denied!');
}

if (isset($_REQUEST['reset']) && (string)$_REQUEST['reset'] === '1') {
      $token = (string)($_REQUEST['csrf_token'] ?? '');
      if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
          die('Access denied: Invalid or expired security token.');
      }
      $db->query("DELETE FROM hlstats_sql_web_profile");
      $db->query("DELETE FROM hlstats_sql_daemon_profile");
      die("Performance stats successfully reset.");
}

if (!function_exists('renderProfileTable')) {
    function renderProfileTable($db, string $tableName, string $orderBy, string $title): void
    {
        $allowedTables = ['hlstats_sql_web_profile', 'hlstats_sql_daemon_profile'];
        $allowedOrders = ['run_count', 'run_time', 'avg_rt'];

        if (!in_array($tableName, $allowedTables, true) || !in_array($orderBy, $allowedOrders, true)) {
            return;
        }

        echo "<p><strong>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</strong></p>";
        echo "<table class=\"data-table\" style=\"width:100%; margin-bottom: 15px;\">";
        echo "<tr class=\"data-table-head\"><th>Origin</th><th>Count</th><th>Total Time (s)</th><th>Avg Time (s)</th></tr>";

        // Check if table exists before querying to avoid fatal error on unmigrated DBs
        $chk = $db->query("SHOW TABLES LIKE '{$tableName}'");
        if (!$chk || $db->num_rows($chk) === 0) {
            if ($chk) {
                $db->free_result($chk);
            }
            echo "<tr class=\"bg1\"><td colspan=\"4\" style=\"text-align:center;\">Profiler table not installed or inactive.</td></tr></table>";
            return;
        }
        $db->free_result($chk);

        $result = $db->query("
            SELECT
                source,
                run_count,
                run_time,
                (run_time / IF(run_count = 0, 1, run_count)) AS avg_rt
            FROM
                {$tableName}
            ORDER BY
                {$orderBy} DESC
            LIMIT 20
        ");

        if (!$result || $db->num_rows($result) === 0) {
            echo "<tr class=\"bg1\"><td colspan=\"4\" style=\"text-align:center;\">No data available.</td></tr>";
        } else {
            $i = 0;
            while ($rowdata = $db->fetch_array($result)) {
                $class = ($i % 2 === 0) ? 'bg1' : 'bg2';
                $source = htmlspecialchars((string)($rowdata['source'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $count = (int)($rowdata['run_count'] ?? 0);
                $total_time = sprintf("%.4f", (float)($rowdata['run_time'] ?? 0));
                $avg_time = sprintf("%.6f", (float)($rowdata['avg_rt'] ?? 0));

                echo "<tr class=\"{$class}\">";
                echo "<td>{$source}</td>";
                echo "<td style=\"text-align:right;\">{$count}</td>";
                echo "<td style=\"text-align:right;\">{$total_time}</td>";
                echo "<td style=\"text-align:right;\">{$avg_time}</td>";
                echo "</tr>";
                $i++;
            }
        }
        echo "</table>";

        // Free query result memory
        if ($result) {
            $db->free_result($result);
        }
    }
}

echo '<div class="block">';
  printSectionTitle('SQL Performance Profiler');
  echo '<div class="subblock">';

  echo '<div style="float:right; margin-bottom:10px;">';
  $csrf_token = function_exists('hlstats_csrf_token') ? hlstats_csrf_token() : ($_SESSION['csrf_token'] ?? '');
  echo '<a href="?mode=profile&amp;reset=1&amp;csrf_token=' . htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') . '" class="smallsubmit" onclick="return confirm(\'Are you sure you want to reset all performance stats?\');">Reset Statistics</a>';
  echo '</div>';
  echo '<div style="clear:both;"></div>';

  echo '<h3>Web Performance</h3>';
  renderProfileTable($db, 'hlstats_sql_web_profile', 'run_count', 'Top Queries by Number of Executions');
  renderProfileTable($db, 'hlstats_sql_web_profile', 'run_time', 'Top Queries by Total Time Taken');
  renderProfileTable($db, 'hlstats_sql_web_profile', 'avg_rt', 'Top Queries by Average Runtime');

  echo '<hr style="margin: 20px 0;" />';

  echo '<h3>Daemon Performance</h3>';
  renderProfileTable($db, 'hlstats_sql_daemon_profile', 'run_count', 'Top Queries by Number of Executions');
  renderProfileTable($db, 'hlstats_sql_daemon_profile', 'run_time', 'Top Queries by Total Time Taken');
  renderProfileTable($db, 'hlstats_sql_daemon_profile', 'avg_rt', 'Top Queries by Average Runtime');

  echo '</div>';
  echo '</div>';
?>