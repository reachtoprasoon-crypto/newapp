<?php
// Streams a full SQL dump of the live database. Page-context guard (plain
// GET, navigated to directly) rather than an AJAX JSON endpoint, matching
// api/tc/docx.php's convention.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db_backup.php';

require_staff_role_page([10]);

stream_sql_backup($mysqli, DB_NAME);
