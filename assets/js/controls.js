// Admin "Controls" tab: a flat toggle-switch list of the `controls` table's
// feature flags, grouped by ctype. Ports controls-management.tsx's grouping
// rules (marksfeeding conid override, Password Reset forced into a Security
// group) — theme rows (Default Theme / Report Watermark) are excluded
// server-side since they carry an image blob consumed elsewhere, not a
// simple on/off flag.
(function () {
    const MARKSFEEDING_CONIDS = [11, 12, 13];

    function groupKeyFor(control) {
        if (MARKSFEEDING_CONIDS.includes(control.conid)) return 'marksfeeding';
        if (control.control === 'Password Reset') return 'security';
        return (control.ctype || 'other').toLowerCase();
    }

    function groupLabelFor(key) {
        if (key === 'marksfeeding') return 'Marksfeeding Controls';
        if (key === 'security') return 'Security';
        const words = key.replace(/_/g, ' ').trim();
        const label = words.charAt(0).toUpperCase() + words.slice(1);
        return label + ' Controls';
    }

    function renderGroups(controls) {
        const groups = {};
        controls.forEach(function (c) {
            const key = groupKeyFor(c);
            if (!groups[key]) groups[key] = [];
            groups[key].push(c);
        });

        const $container = $('#ctrl_groups').empty();
        Object.keys(groups).sort().forEach(function (key) {
            const rows = groups[key].sort(function (a, b) { return a.control.localeCompare(b.control); });
            const $section = $('<div class="mb-4">');
            $section.append('<h6 class="text-uppercase text-muted small fw-bold mb-2">' + groupLabelFor(key) + '</h6>');
            const $list = $('<div class="d-flex flex-column gap-2">');
            rows.forEach(function (c) {
                const $row = $(
                    '<div class="d-flex align-items-center justify-content-between p-2 border rounded">' +
                    '<span class="fw-medium">' + $('<div>').text(c.control).html() + '</span>' +
                    '<div class="form-check form-switch mb-0">' +
                    '<input class="form-check-input ctrl-toggle" type="checkbox" role="switch" data-conid="' + c.conid + '" ' + (c.allowed ? 'checked' : '') + '>' +
                    '</div></div>'
                );
                $list.append($row);
            });
            $section.append($list);
            $container.append($section);
        });
    }

    function loadControls() {
        ajaxCall({ url: '/api/controls/list.php', method: 'GET', silent: true }).then(renderGroups);
    }

    $('#ctrl_groups').on('change', '.ctrl-toggle', function () {
        const $checkbox = $(this);
        const conid = $checkbox.data('conid');
        const newValue = $checkbox.is(':checked');
        ajaxCall({ url: '/api/controls/update.php', data: { conid: conid, allowed: newValue ? '1' : '0' }, silent: true })
            .then(function () {
                toastSuccess('Setting updated.');
            })
            .catch(function () {
                $checkbox.prop('checked', !newValue);
            });
    });

    loadControls();
})();
