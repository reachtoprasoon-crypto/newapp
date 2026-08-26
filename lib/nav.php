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
            ['slug' => 'data-collection', 'label' => 'Data Collection', 'icon' => 'fa-clipboard-list'],
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
        ['slug' => 'data-collection', 'label' => 'Data Collection', 'icon' => 'fa-clipboard-list'],
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
