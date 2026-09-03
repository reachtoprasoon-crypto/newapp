(function () {
    let sub = 'roster';

    function populateSelects() {
        const classSel = $('#fr_class').empty();
        window.APP_DATA.classes.forEach(function (c) {
            classSel.append($('<option>').val(c).text('Class ' + c));
        });
        const termSel = $('#fr_term').empty();
        (window.APP_DATA.terms || []).forEach(function (t) {
            termSel.append($('<option>').val(t.termid).text(t.termname));
        });
    }

    function switchSub(newSub) {
        sub = newSub;
        $('#frSubNav a').removeClass('active');
        $('#frSubNav a[data-fr-sub="' + sub + '"]').addClass('active');
        $('#fr_roster, #fr_promotion, #fr_reportcards').hide();
        $('#fr_' + sub).show();
        loadCurrent();
    }

    function loadCurrent() {
        const sclass = $('#fr_class').val();
        $('#btnExportFinalRoster').addClass('d-none');
        if (!sclass) return;
        if (sub === 'promotion') loadPromotion(sclass);
        if (sub === 'reportcards') loadStudentsForReportCards(sclass);
        // roster is manual (click "Generate/Refresh") since it recomputes+persists.
    }

    function loadRoster() {
        const sclass = $('#fr_class').val();
        if (!sclass) return;
        ajaxCall({ url: '/api/final-results/roster.php', method: 'GET', data: { sclass: sclass } })
            .then(function (data) {
                renderRoster(data);
                $('#btnExportFinalRoster').removeClass('d-none');
            });
    }

    function exportFinalRoster() {
        const sclass = $('#fr_class').val();
        if (!sclass) return;
        triggerDownload(BASE_URL + '/api/final-results/roster_export.php?sclass=' + encodeURIComponent(sclass));
    }

    function exportPromotion() {
        const sclass = $('#fr_class').val();
        if (!sclass) return;
        triggerDownload(BASE_URL + '/api/promotion/export.php?sclass=' + encodeURIComponent(sclass));
    }

    function renderRoster(data) {
        const headRow = $('#fr_rosterHead').empty();
        headRow.append('<th>Roll</th><th>Name</th>');
        data.header.forEach(function (h) {
            h.subHeaders.forEach(function (sh) {
                headRow.append('<th>' + (h.label !== 'Grand Total' && h.label !== 'Percentage' && h.label !== 'Rank' ? h.label + ' ' : '') + sh.label + '</th>');
            });
        });

        const tbody = $('#fr_rosterBody').empty();
        data.studentData.forEach(function (s) {
            const row = $('<tr>');
            row.append($('<td>').text(s.roll));
            row.append($('<td>').text(s.sname));
            data.header.forEach(function (h) {
                h.subHeaders.forEach(function (sh) {
                    const val = s[sh.key];
                    row.append($('<td>').text(val === null || val === undefined ? '-' : val));
                });
            });
            tbody.append(row);
        });
    }

    function loadPromotion(sclass) {
        ajaxCall({ url: '/api/promotion/get.php', method: 'GET', data: { sclass: sclass }, silent: true })
            .then(renderPromotion);
    }

    function renderPromotion(students) {
        const options = ['GRANTED', 'CONDITIONAL', 'GRANTED AS PER RTE'];
        const tbody = $('#fr_promotionBody').empty();
        students.forEach(function (s) {
            const row = $('<tr>').attr('data-sid', s.sid);
            row.append($('<td>').text(s.roll));
            row.append($('<td>').text(s.schno));
            row.append($('<td>').text(s.sname));
            let selectHtml = '<select class="form-select form-select-sm fr-promo-status"><option value="">-- Not Set --</option>';
            options.forEach(function (o) {
                selectHtml += '<option value="' + o + '"' + (o === s.status ? ' selected' : '') + '>' + o + '</option>';
            });
            selectHtml += '</select>';
            row.append($('<td>').html(selectHtml));
            tbody.append(row);
        });
    }

    function savePromotion() {
        const sclass = $('#fr_class').val();
        const promotions = [];
        $('#fr_promotionBody tr').each(function () {
            const status = $(this).find('.fr-promo-status').val();
            if (status) {
                promotions.push({ sid: parseInt($(this).data('sid'), 10), status: status });
            }
        });
        ajaxCall({
            url: '/api/promotion/upsert.php',
            data: { sclass: sclass, promotions: JSON.stringify(promotions) },
            successMessage: 'Promotion status saved.',
        });
    }

    function loadStudentsForReportCards(sclass) {
        ajaxCall({ url: '/api/students/list.php', method: 'GET', data: { sclass: sclass }, silent: true })
            .then(function (students) {
                const tbody = $('#fr_studentsBody').empty();
                $('#fr_selectAllStudents').prop('checked', true);
                students.forEach(function (s) {
                    const row = $('<tr>');
                    row.append($('<td>').html('<input type="checkbox" class="fr-student-check" data-sid="' + s.sid + '" checked>'));
                    row.append($('<td>').text(s.roll));
                    row.append($('<td>').text(s.sname));
                    row.append($('<td>').html('<button class="btn btn-sm btn-outline-primary btn-final-report-card" data-sid="' + s.sid + '">View Final Report Card</button>'));
                    tbody.append(row);
                });
            });
    }

    function exportFinalReportCards() {
        const sclass = $('#fr_class').val();
        const termid = $('#fr_term').val();
        const report = $('#fr_report').val();
        if (!sclass || !termid || !report) {
            toastError('Select class, term and report first.');
            return;
        }
        const sids = $('.fr-student-check:checked').map(function () { return $(this).data('sid'); }).get();
        if (sids.length === 0) {
            toastError('Select at least one student.');
            return;
        }
        const params = {
            sclass: sclass,
            termid: termid,
            report: report,
            sids: sids.join(','),
            includeSchool: $('#fr_includeSchool').is(':checked') ? '1' : '0',
            includeBranch: $('#fr_includeBranch').is(':checked') ? '1' : '0',
            includeWatermark: $('#fr_includeWatermark').is(':checked') ? '1' : '0',
            includeSignatures: $('#fr_includeSignatures').is(':checked') ? '1' : '0',
        };
        const query = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
        triggerDownload(BASE_URL + '/api/final-results/export_excel.php?' + query);
    }

    $('#frSubNav').on('click', 'a', function (e) {
        e.preventDefault();
        switchSub($(this).data('fr-sub'));
    });
    $('#fr_class').on('change', loadCurrent);
    $('#btnLoadRoster').on('click', loadRoster);
    $('#btnExportFinalRoster').on('click', exportFinalRoster);
    $('#btnSavePromotion').on('click', savePromotion);
    $('#btnExportPromotion').on('click', exportPromotion);
    $('#fr_term, #fr_report').on('change', function () {
        if (sub === 'reportcards') loadStudentsForReportCards($('#fr_class').val());
    });
    $('#fr_studentsBody').on('click', '.btn-final-report-card', function () {
        const sid = $(this).data('sid');
        const termid = $('#fr_term').val();
        const report = $('#fr_report').val();
        window.open(BASE_URL + '/final_report_card.php?sid=' + sid + '&termid=' + termid + '&report=' + report, '_blank');
    });
    $('#fr_selectAllStudents').on('change', function () {
        $('.fr-student-check').prop('checked', $(this).is(':checked'));
    });
    $('#fr_studentsBody').on('change', '.fr-student-check', function () {
        if (!$(this).is(':checked')) $('#fr_selectAllStudents').prop('checked', false);
        else if ($('.fr-student-check:not(:checked)').length === 0) $('#fr_selectAllStudents').prop('checked', true);
    });
    $('#btnExportFinalReportCards').on('click', exportFinalReportCards);

    populateSelects();
})();
