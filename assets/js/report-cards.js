(function () {
    let availableReports = [];
    let students = [];
    let selectedStudentIds = new Set();

    function populateClassSelect() {
        const classSel = $('#rc_class');
        window.APP_DATA.classes.forEach(function (c) {
            classSel.append($('<option>').val(c).text('Class ' + c));
        });
    }

    function escapeHtml(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function onClassChange() {
        const sclass = $('#rc_class').val();
        $('#rc_term').html('<option value="">Select class first</option>').prop('disabled', true);
        $('#rc_report').html('<option value="">Select term first</option>').prop('disabled', true);
        $('#rc_studentPickerWrap').addClass('d-none');
        students = [];
        selectedStudentIds = new Set();
        if (!sclass) return;

        ajaxCall({ url: '/api/report-card/available_reports.php', method: 'GET', data: { sclass: sclass }, silent: true })
            .then(function (reports) {
                availableReports = reports;
                const seen = {};
                const termSel = $('#rc_term').empty().append('<option value="">Select Term</option>').prop('disabled', false);
                reports.forEach(function (r) {
                    if (!seen[r.termid]) {
                        seen[r.termid] = true;
                        termSel.append($('<option>').val(r.termid).text(r.termname));
                    }
                });
            });

        ajaxCall({ url: '/api/students/list.php', method: 'GET', data: { sclass: sclass }, silent: true })
            .then(function (data) {
                students = data;
                selectedStudentIds = new Set(students.map(function (s) { return s.sid; }));
                renderStudentPicker();
                $('#rc_studentPickerWrap').toggleClass('d-none', students.length === 0);
            });
    }

    function onTermChange() {
        const termid = $('#rc_term').val();
        const reportSel = $('#rc_report').empty().append('<option value="">Select Report</option>');
        if (!termid) {
            reportSel.prop('disabled', true);
            return;
        }
        reportSel.prop('disabled', false);
        availableReports.filter(function (r) { return String(r.termid) === String(termid); })
            .forEach(function (r) { reportSel.append($('<option>').val(r.report).text('Report ' + r.report)); });
    }

    function renderStudentPicker(filter) {
        const container = $('#rc_studentPicker').empty();
        const lower = (filter || '').toLowerCase();
        const visible = students.filter(function (s) {
            if (!lower) return true;
            return s.sname.toLowerCase().indexOf(lower) !== -1 || String(s.roll).indexOf(lower) !== -1 || String(s.schno).indexOf(lower) !== -1;
        });
        visible.forEach(function (s) {
            const checked = selectedStudentIds.has(s.sid);
            const col = $('<div class="col-6 col-md-4 col-lg-3">');
            const label = $('<label class="d-flex align-items-center gap-1 border rounded p-1 small' + (checked ? ' bg-primary-subtle' : '') + '" style="cursor:pointer;">');
            label.append('<input type="checkbox" class="form-check-input rc-student-check" data-sid="' + s.sid + '"' + (checked ? ' checked' : '') + '>');
            label.append('<span>' + escapeHtml(s.sname) + '<br><span class="text-muted">Roll ' + s.roll + ' | Sch ' + s.schno + '</span></span>');
            col.append(label);
            container.append(col);
        });
        $('#rc_studentCount').text('Select Students (' + selectedStudentIds.size + '/' + students.length + ')');
    }

    function currentConfig() {
        return {
            sclass: $('#rc_class').val(),
            termid: $('#rc_term').val(),
            report: $('#rc_report').val(),
            label: $('#rc_customLabel').val().trim(),
            includeSchool: $('#rc_includeSchool').is(':checked') ? '1' : '0',
            includeBranch: $('#rc_includeBranch').is(':checked') ? '1' : '0',
            includeWatermark: $('#rc_includeWatermark').is(':checked') ? '1' : '0',
            includeSignatures: $('#rc_includeSignatures').is(':checked') ? '1' : '0',
        };
    }

    function buildQuery(params) {
        return Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    }

    function exportExcel() {
        const cfg = currentConfig();
        if (!cfg.sclass || !cfg.termid || !cfg.report) {
            toastError('Select class, term and report first.');
            return;
        }
        if (selectedStudentIds.size === 0) {
            toastError('Select at least one student.');
            return;
        }
        cfg.sids = Array.from(selectedStudentIds).join(',');
        triggerDownload(BASE_URL + '/api/report-card/export_excel.php?' + buildQuery(cfg));
    }

    function printReports() {
        const cfg = currentConfig();
        if (!cfg.sclass || !cfg.termid || !cfg.report) {
            toastError('Select class, term and report first.');
            return;
        }
        if (selectedStudentIds.size === 0) {
            toastError('Select at least one student.');
            return;
        }
        cfg.sids = Array.from(selectedStudentIds).join(',');
        delete cfg.includeWatermark;
        delete cfg.includeSignatures;
        window.open(BASE_URL + '/print_report_cards.php?' + buildQuery(cfg), '_blank');
    }

    function viewClassReport() {
        const sclass = $('#rc_class').val();
        const termid = $('#rc_term').val();
        const report = $('#rc_report').val();
        if (!sclass || !termid || !report) {
            toastError('Select class, term and report first.');
            return;
        }
        window.open(BASE_URL + '/report_card.php?sclass=' + encodeURIComponent(sclass) + '&termid=' + termid + '&report=' + report, '_blank');
    }

    function lookupSearch() {
        const q = $('#rc_lookupSearch').val().trim();
        if (!q) return;
        ajaxCall({ url: '/api/students/search.php', method: 'GET', data: { q: q }, silent: true })
            .then(function (results) {
                const tbody = $('#rc_lookupResults tbody').empty();
                results.forEach(function (s) {
                    const row = $('<tr>');
                    row.append($('<td>').text(s.sname));
                    row.append($('<td>').text('Class ' + s.sclass));
                    row.append($('<td>').html('<button class="btn btn-sm btn-outline-primary btn-lookup-view" data-sid="' + s.sid + '">View</button>'));
                    tbody.append(row);
                });
            });
    }

    $('#rc_class').on('change', onClassChange);
    $('#rc_term').on('change', onTermChange);
    $('#rc_studentSearch').on('input', function () { renderStudentPicker($(this).val()); });
    $('#rc_studentPicker').on('change', '.rc-student-check', function () {
        const sid = parseInt($(this).data('sid'), 10);
        if ($(this).is(':checked')) {
            selectedStudentIds.add(sid);
        } else {
            selectedStudentIds.delete(sid);
        }
        $('#rc_studentCount').text('Select Students (' + selectedStudentIds.size + '/' + students.length + ')');
    });
    $('#btnToggleSelectAll').on('click', function () {
        if (selectedStudentIds.size === students.length) {
            selectedStudentIds = new Set();
        } else {
            selectedStudentIds = new Set(students.map(function (s) { return s.sid; }));
        }
        renderStudentPicker($('#rc_studentSearch').val());
    });
    $('#btnExportExcel').on('click', exportExcel);
    $('#btnPrintReports').on('click', printReports);
    $('#btnViewClassReport').on('click', viewClassReport);
    $('#btnLookupSearch').on('click', lookupSearch);
    $('#rc_lookupSearch').on('keydown', function (e) { if (e.key === 'Enter') lookupSearch(); });
    $('#rc_lookupResults').on('click', '.btn-lookup-view', function () {
        const sid = $(this).data('sid');
        const termid = $('#rc_term').val();
        const report = $('#rc_report').val();
        if (!termid || !report) {
            toastError('Select a term and report first.');
            return;
        }
        window.open(BASE_URL + '/report_card.php?sid=' + sid + '&termid=' + termid + '&report=' + report, '_blank');
    });

    populateClassSelect();
})();
