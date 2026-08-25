<?php
// Generic tab-content loader: GET ?slug=X returns the HTML fragment for that
// dashboard tab, gated by the same role->slug map the nav is built from.
// Slugs without a partial file yet (later phases) get a "coming soon" stand-in.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/nav.php';

require_login_page();
$user = current_user();
if ($user['type'] !== 'staff') {
    http_response_code(403);
    exit('Forbidden');
}

$slug = $_GET['slug'] ?? '';
$ttype = (int) $user['ttype'];

if (!preg_match('/^[a-z0-9\-]+$/', $slug) || !is_slug_allowed_for_role($slug, $ttype)) {
    http_response_code(403);
    exit('Forbidden');
}

$partialFile = __DIR__ . '/' . $slug . '.php';
if (is_file($partialFile)) {
    require $partialFile;
} else {
    ?>
    <div class="text-center text-muted py-5">
        <i class="fa-solid fa-hammer fa-2x mb-3"></i>
        <p>This module is coming in a later phase of the migration.</p>
    </div>
    <?php
}
