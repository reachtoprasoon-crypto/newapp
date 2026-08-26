<?php
// Site-wide Light/Dark theme + report-card watermark, both stored as rows in
// the `controls` table (ctype='theme', deliberately excluded from the
// generic Controls screen — see lib/controls.php). Ports the relevant half
// of theme-management.tsx; its separate HSL color-palette editor (writes to
// a different config entirely, not the `controls` table) is not ported.

require_once __DIR__ . '/controls.php';

// {defaultTheme: control row|null, reportWatermark: control row|null}
function get_theme_controls($mysqli) {
    $all = get_all_controls($mysqli);
    $result = ['defaultTheme' => null, 'reportWatermark' => null];
    foreach ($all as $c) {
        if ($c['control'] === 'Default Theme') {
            $result['defaultTheme'] = $c;
        } elseif ($c['control'] === 'Report Watermark') {
            $result['reportWatermark'] = $c;
        }
    }
    return $result;
}

// Server-side theme lookup for setting <html data-bs-theme> before render,
// avoiding a flash of the wrong theme. Defaults to light if the control row
// is missing for any reason.
function is_dark_theme($mysqli) {
    $themes = get_theme_controls($mysqli);
    return $themes['defaultTheme'] !== null && (int) $themes['defaultTheme']['cval'] === 1;
}
