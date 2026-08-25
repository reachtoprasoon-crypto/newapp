<?php
// Small mysqli prepared-statement helpers used throughout api/*.php.
// $types is the mysqli bind_param type string, e.g. "si" for (string, int).

function db_query($mysqli, $sql, $types = '', $params = []) {
    $stmt = mysqli_prepare($mysqli, $sql);
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . mysqli_error($mysqli));
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception('Query execution failed: ' . $error);
    }
    return $stmt;
}

function db_fetch_all($mysqli, $sql, $types = '', $params = []) {
    $stmt = db_query($mysqli, $sql, $types, $params);
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
}

function db_fetch_one($mysqli, $sql, $types = '', $params = []) {
    $rows = db_fetch_all($mysqli, $sql, $types, $params);
    return $rows[0] ?? null;
}

function db_execute($mysqli, $sql, $types = '', $params = []) {
    $stmt = db_query($mysqli, $sql, $types, $params);
    $affected = mysqli_stmt_affected_rows($stmt);
    $insertId = mysqli_stmt_insert_id($stmt);
    mysqli_stmt_close($stmt);
    return ['affected' => $affected, 'insert_id' => $insertId];
}

function db_table_exists($mysqli, $table) {
    // SHOW TABLES LIKE ? cannot be prepared in MySQL; query information_schema instead.
    $row = db_fetch_one(
        $mysqli,
        "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
        's',
        [$table]
    );
    return $row !== null;
}
