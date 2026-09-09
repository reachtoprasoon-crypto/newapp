<?php
// Single source of truth for which dashboard tabs each role sees, used by
// both dashboard.php (to render the nav) and partials/load.php (to gate
// direct slug requests server-side rather than trusting the client).

function get_nav_tabs_for_role($ttype) {
    $ttype = (int) $ttype;

    if ($ttype === 10) { // Admin
        return [
            ['slug' => 'students', 'label' => 'Students', 'icon' => 'fa-user-graduate'],
            ['slug' => 'teachers', 'label' => 'Teachers', 'icon' => 'fa-chalkboard-user'],
            ['slug' => 'attendance', 'label' => 'Attendance', 'icon' => 'fa-calendar-check'],
            ['slug' => 'marks', 'label' => 'Marks / Grades', 'icon' => 'fa-pen'],
            ['slug' => 'term-schedule', 'label' => 'Term Schedule', 'icon' => 'fa-calendar-days'],
            ['slug' => 'report-cards', 'label' => 'Report Cards', 'icon' => 'fa-file-lines'],
            ['slug' => 'class-roster', 'label' => 'Class Roster', 'icon' => 'fa-table-list'],
            ['slug' => 'final-results', 'label' => 'Final Results', 'icon' => 'fa-trophy'],
            ['slug' => 'students-total', 'label' => 'Students Total', 'icon' => 'fa-ranking-star'],
            ['slug' => 'aptitude', 'label' => 'Aptitude', 'icon' => 'fa-brain'],
            ['slug' => 'communications', 'label' => 'Communications', 'icon' => 'fa-bullhorn'],
            ['slug' => 'data-collection', 'label' => 'Data Collection', 'icon' => 'fa-clipboard-list'],
            ['slug' => 'question-papers', 'label' => 'Question Papers', 'icon' => 'fa-list-check'],
            ['slug' => 'subjective-papers', 'label' => 'Subjective Papers', 'icon' => 'fa-file-pen'],
            ['slug' => 'tc', 'label' => 'Issue TC', 'icon' => 'fa-file-export'],
            ['slug' => 'activity-log', 'label' => 'Activity Log', 'icon' => 'fa-clock-rotate-left'],
            ['slug' => 'controls', 'label' => 'Controls', 'icon' => 'fa-sliders'],
            ['slug' => 'theme', 'label' => 'Theme', 'icon' => 'fa-palette'],
            ['slug' => 'database', 'label' => 'Database', 'icon' => 'fa-database'],
        ];
    }
    if ($ttype === 5) { // Office
        return [
            ['slug' => 'students', 'label' => 'Students', 'icon' => 'fa-user-graduate'],
            ['slug' => 'marks', 'label' => 'Marks / Grades', 'icon' => 'fa-pen'],
            ['slug' => 'attendance', 'label' => 'Attendance', 'icon' => 'fa-calendar-check'],
            ['slug' => 'class-roster', 'label' => 'Class Roster', 'icon' => 'fa-table-list'],
            ['slug' => 'final-results', 'label' => 'Final Results', 'icon' => 'fa-trophy'],
            ['slug' => 'students-total', 'label' => 'Students Total', 'icon' => 'fa-ranking-star'],
            ['slug' => 'communications', 'label' => 'Communications', 'icon' => 'fa-bullhorn'],
            ['slug' => 'subjective-papers', 'label' => 'Subjective Papers', 'icon' => 'fa-file-pen'],
            ['slug' => 'activity-log', 'label' => 'Activity Log', 'icon' => 'fa-clock-rotate-left'],
        ];
    }
    if ($ttype === 6) { // Principal
        return [
            ['slug' => 'students', 'label' => 'Students', 'icon' => 'fa-user-graduate'],
            ['slug' => 'attendance', 'label' => 'Attendance', 'icon' => 'fa-calendar-check'],
            ['slug' => 'class-roster', 'label' => 'Class Roster', 'icon' => 'fa-table-list'],
            ['slug' => 'communications', 'label' => 'Communications', 'icon' => 'fa-bullhorn'],
            ['slug' => 'question-papers', 'label' => 'Question Papers', 'icon' => 'fa-list-check'],
            ['slug' => 'subjective-papers', 'label' => 'Subjective Papers', 'icon' => 'fa-file-pen'],
        ];
    }

    // Teacher / Class Teacher (ttype 1 gets extra tabs scoped to their own class)
    $tabs = [
        ['slug' => 'marks', 'label' => 'Marks / Grades', 'icon' => 'fa-pen'],
        ['slug' => 'attendance', 'label' => 'Attendance', 'icon' => 'fa-calendar-check'],
        ['slug' => 'communications', 'label' => 'Communications', 'icon' => 'fa-bullhorn'],
        ['slug' => 'question-papers', 'label' => 'Question Papers', 'icon' => 'fa-list-check'],
        ['slug' => 'subjective-papers', 'label' => 'Subjective Papers', 'icon' => 'fa-file-pen'],
    ];
    if ($ttype === 1) {
        $tabs[] = ['slug' => 'students', 'label' => 'My Class', 'icon' => 'fa-user-graduate'];
        $tabs[] = ['slug' => 'class-roster', 'label' => 'Class Roster', 'icon' => 'fa-table-list'];
    }
    return $tabs;
}

function is_slug_allowed_for_role($slug, $ttype) {
    foreach (get_nav_tabs_for_role($ttype) as $tab) {
        if ($tab['slug'] === $slug) {
            return true;
        }
    }
    return false;
}

// Purely presentational grouping for the top nav's dropdown menus — the
// access-control list above (get_nav_tabs_for_role) stays the single source
// of truth for who can see what; this only decides how to arrange it.
const NAV_GROUP_ORDER = ['Academics', 'Management', 'Tools', 'Utils'];
const NAV_GROUP_MAP = [
    'marks' => 'Academics',
    'report-cards' => 'Academics',
    'class-roster' => 'Academics',
    'final-results' => 'Academics',

    'students' => 'Management',
    'teachers' => 'Management',
    'term-schedule' => 'Management',
    'tc' => 'Management',
    'controls' => 'Management',

    'attendance' => 'Tools',
    'students-total' => 'Tools',
    'aptitude' => 'Tools',
    'communications' => 'Tools',
    'data-collection' => 'Tools',
    'question-papers' => 'Tools',
    'subjective-papers' => 'Tools',

    'activity-log' => 'Utils',
    'theme' => 'Utils',
    'database' => 'Utils',
];

// Buckets a flat tab list (from get_nav_tabs_for_role) into
// [['group' => 'Academics', 'items' => [...]], ...], preserving
// NAV_GROUP_ORDER and dropping empty groups. Any tab not yet in
// NAV_GROUP_MAP (e.g. a newly added slug) falls back to a single
// ['group' => null, 'items' => [...]] bucket rendered as plain top-level
// links, so it stays reachable instead of silently disappearing.
function group_nav_tabs($tabs) {
    $byGroup = array_fill_keys(NAV_GROUP_ORDER, []);
    $ungrouped = [];
    foreach ($tabs as $tab) {
        $group = NAV_GROUP_MAP[$tab['slug']] ?? null;
        if ($group !== null && isset($byGroup[$group])) {
            $byGroup[$group][] = $tab;
        } else {
            $ungrouped[] = $tab;
        }
    }
    $result = [];
    foreach (NAV_GROUP_ORDER as $group) {
        if (!empty($byGroup[$group])) {
            $result[] = ['group' => $group, 'items' => $byGroup[$group]];
        }
    }
    if (!empty($ungrouped)) {
        $result[] = ['group' => null, 'items' => $ungrouped];
    }
    return $result;
}
