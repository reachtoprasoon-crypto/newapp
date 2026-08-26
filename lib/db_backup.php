<?php
// Full SQL dump of the live database, streamed directly to the browser.
// Ports db-backup-reset-flow.ts::backupDatabase (the backup half only —
// resetDatabase is deliberately not ported, see README). Unlike the source,
// which builds the entire dump as one in-memory string (risky for a ~90MB+
// database on a memory-capped shared host), this streams table-by-table.

require_once __DIR__ . '/db.php';

function stream_sql_backup($mysqli, $dbName) {
    set_time_limit(300);

    $filename = 'backup_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $dbName) . '_' . date('Y-m-d_His') . '.sql';
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    echo "-- SQL backup for {$dbName}\n-- Generated " . date('c') . "\n\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();

    $tables = db_fetch_all($mysqli, "SHOW TABLES");
    foreach ($tables as $row) {
        $tableName = array_values($row)[0];

        $createRow = db_fetch_one($mysqli, "SHOW CREATE TABLE `{$tableName}`");
        echo "DROP TABLE IF EXISTS `{$tableName}`;\n";
        echo $createRow['Create Table'] . ";\n\n";

        $result = mysqli_query($mysqli, "SELECT * FROM `{$tableName}`");
        if ($result && mysqli_num_rows($result) > 0) {
            $columns = [];
            while ($field = mysqli_fetch_field($result)) {
                $columns[] = $field->name;
            }
            $columnList = '`' . implode('`, `', $columns) . '`';

            while ($dataRow = mysqli_fetch_assoc($result)) {
                $values = array_map(function ($v) use ($mysqli) {
                    return $v === null ? 'NULL' : "'" . mysqli_real_escape_string($mysqli, $v) . "'";
                }, array_values($dataRow));
                echo "INSERT INTO `{$tableName}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
            }
        }
        if ($result) {
            mysqli_free_result($result);
        }
        echo "\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
}
