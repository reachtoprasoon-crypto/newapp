(function () {
    function populateSelects() {
        const classSel = $('#tsc_class').empty().append('<option value="">Select Class</option>');
        window.APP_DATA.classes.forEach(function (c) {
            classSel.append($('<option>').val(c).text('Class ' + c));
        });

        const targetSel = $('#tsc_targetClasses').empty();
        window.APP_DATA.classes.forEach(function (c) {
            targetSel.append($('<option>').val(c).text('Class ' + c));
        });

        const termSel = $('#tsc_term').empty().append('<option value="">Select Term</option>');
        (window.APP_DATA.terms || []).forEach(function (t) {
            termSel.append($('<option>').val(t.termid).text(t.termname));
        });

        loadExams();
    }

    function loadExams() {
        ajaxCall({ url: '/api/reference/exams.php', method: 'GET', silent: true }).then(function (exams) {
            const sel = $('#tsc_exam').empty().append('<option value="">Select Exam</option>');
            exams.forEach(function (e) {
                sel.append($('<option>').val(e.exid).text(e.examname));
            });
        });
    }

    function currentContext() {
        return {
            sclass: $('#tsc_class').val(),
            termid: $('#tsc_term').val(),
            report: $('#tsc_report').val(),
            exid: $('#tsc_exam').val(),
        };
    }

    function loadSchedule() {
        const ctx = currentContext();
        $('#tsc_tableWrap').addClass('d-none');
        if (!ctx.sclass || !ctx.termid || !ctx.report || !ctx.exid) return;

        ajaxCall({ url: '/api/term-schedule/for_form.php', method: 'GET', data: ctx, silent: true })
            .then(function (subjects) {
                const tbody = $('#tsc_tableBody').empty();
                subjects.forEach(function (s) {
                    const row = $('<tr>').attr('data-subid', s.subid);
                    row.append($('<td>').text(s.subname));
                    row.append($('<td>').html('<input type="number" class="form-control form-control-sm tsc-maxm" min="0" value="' + (s.maxm === null ? '' : s.maxm) + '">'));
                    tbody.append(row);
                });
                $('#tsc_tableWrap').removeClass('d-none');
            });
    }

    function saveSchedule() {
        const ctx = currentContext();
        const schedules = [];
        $('#tsc_tableBody tr').each(function () {
            const val = $(this).find('.tsc-maxm').val();
            if (val !== '') {
                schedules.push({ subid: parseInt($(this).data('subid'), 10), maxm: parseInt(val, 10) });
            }
        });
        ajaxCall({
            url: '/api/term-schedule/upsert.php',
            data: { sclass: ctx.sclass, termid: ctx.termid, report: ctx.report, exid: ctx.exid, schedules: JSON.stringify(schedules) },
            successMessage: 'Term schedule saved successfully.',
        });
    }

    function copySchedule() {
        const ctx = currentContext();
        const targets = $('#tsc_targetClasses').val() || [];
        if (!ctx.sclass || !ctx.termid || !ctx.report) {
            toastError('Select a source class, term and report first.');
            return;
        }
        if (!targets.length) {
            toastError('Select at least one target class.');
            return;
        }
        Swal.fire({
            title: 'Copy schedule?',
            text: 'This will replace the existing schedule for the selected term/report in ' + targets.length + ' class(es).',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Copy',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            ajaxCall({
                url: '/api/term-schedule/copy.php',
                data: { sourceSclass: ctx.sclass, sourceTermid: ctx.termid, sourceReport: ctx.report, targetSclasses: JSON.stringify(targets) },
                successMessage: 'Schedule copied successfully.',
            });
        });
    }

    $('#tsc_class, #tsc_term, #tsc_report, #tsc_exam').on('change', loadSchedule);
    $('#btnSaveTermSchedule').on('click', saveSchedule);
    $('#btnCopySchedule').on('click', copySchedule);

    populateSelects();
})();
