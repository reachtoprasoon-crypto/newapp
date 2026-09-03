(function () {
    const ttype = window.APP_DATA.ttype;
    const isClassTeacher = ttype === 1;

    function populateSelects() {
        const classSel = $('#cr_class').empty();
        window.APP_DATA.classes.forEach(function (c) {
            classSel.append($('<option>').val(c).text('Class ' + c));
        });
        const termSel = $('#cr_term').empty();
        (window.APP_DATA.terms || []).forEach(function (t) {
            termSel.append($('<option>').val(t.termid).text(t.termname));
        });

        if (isClassTeacher) {
            $('#cr_class').val(window.APP_DATA.sclass);
            $('#cr_classWrap').hide();
        }

        const activeTerm = (window.APP_DATA.controls || []).find(function (c) { return c.ctype === 'term' && c.allowed; });
        const activeReport = (window.APP_DATA.controls || []).find(function (c) { return c.ctype === 'report' && c.allowed; });
        if (activeTerm) $('#cr_term').val(activeTerm.cval);
        if (activeReport) $('#cr_report').val(activeReport.cval);
    }

    function escapeHtml(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function fetchRoster() {
        const sclass = $('#cr_class').val();
        const termid = $('#cr_term').val();
        const report = $('#cr_report').val();
        if (!sclass || !termid || !report) {
            toastError('Select class, term and report first.');
            return;
        }
        ajaxCall({ url: '/api/reporting/class_roster.php', method: 'GET', data: { sclass: sclass, termid: termid, report: report } })
            .then(function (roster) {
                render(roster);
                $('#btnExportRoster').removeClass('d-none');
            });
    }

    function render(roster) {
        const headRow = $('#cr_head').empty();
        headRow.append('<th>Roll</th><th>Name</th>');
        const flatCols = [];
        roster.header.forEach(function (h) {
            const markCount = h.subHeaders.filter(function (sh) { return sh.key.indexOf('mark_') === 0; }).length;
            const finalSubs = markCount > 1 ? h.subHeaders : h.subHeaders.filter(function (sh) { return sh.key.indexOf('total_') !== 0; });
            finalSubs.forEach(function (sh) {
                const skipPrefix = h.label === 'Grand Total' || h.label === 'Percentage' || h.label === 'Rank';
                headRow.append('<th>' + (skipPrefix ? '' : escapeHtml(h.subshort || h.label) + ' ') + escapeHtml(sh.label) + '</th>');
                flatCols.push({ key: sh.key, maxm: sh.maxm });
            });
        });
        roster.gradeSubjects.forEach(function (gs) {
            headRow.append('<th>' + escapeHtml(gs.subshort || gs.subname) + '</th>');
        });
        headRow.append('<th>Attendance</th><th>Comment ID</th>');

        const tbody = $('#cr_body').empty();
        roster.studentData.forEach(function (s) {
            const row = $('<tr>');
            row.append('<td>' + s.roll + '</td>');
            row.append('<td>' + escapeHtml(s.sname) + '</td>');
            const grandTotalFailing = typeof s.percentage === 'number' && s.percentage < 40;
            flatCols.forEach(function (col) {
                const key = col.key;
                const v = s[key];
                const isTotal = key.indexOf('total_') === 0 || key === 'grandTotal';
                const isPercentage = key === 'percentage';
                const isRank = key === 'rank';
                let isFailing = false;
                if (key === 'grandTotal' || key === 'percentage') {
                    isFailing = grandTotalFailing;
                } else if (!isRank && typeof v === 'number' && col.maxm) {
                    isFailing = v < col.maxm * 0.4;
                }
                const classes = [];
                if (isTotal || isPercentage || isRank) classes.push('fw-bold');
                if (isFailing) classes.push('text-danger', 'text-decoration-underline');
                const cls = classes.length ? ' class="' + classes.join(' ') + '"' : '';
                row.append('<td' + cls + '>' + (v === null || v === undefined ? '-' : escapeHtml(v)) + '</td>');
            });
            roster.gradeSubjects.forEach(function (gs) {
                const grade = (roster.studentGrades[s.sid] || {})[gs.subid];
                row.append('<td>' + (grade || 'N/A') + '</td>');
            });
            const att = roster.studentAttendance[s.sid];
            row.append('<td>' + (att ? (att.attendance ?? 'N/A') + '/' + (att.totalattendance ?? 'N/A') : 'N/A') + '</td>');
            row.append('<td>' + (att && att.comid !== null ? att.comid : 'N/A') + '</td>');
            tbody.append(row);
        });
    }

    function exportRoster() {
        const sclass = $('#cr_class').val();
        const termid = $('#cr_term').val();
        const report = $('#cr_report').val();
        triggerDownload(BASE_URL + '/api/reporting/class_roster_export.php?sclass=' + encodeURIComponent(sclass) + '&termid=' + termid + '&report=' + report);
    }

    $('#btnFetchRoster').on('click', fetchRoster);
    $('#btnExportRoster').on('click', exportRoster);

    populateSelects();
})();
