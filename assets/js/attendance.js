(function () {
    const ttype = window.APP_DATA.ttype;
    const controls = window.APP_DATA.controls;
    const isAdminOrOffice = ttype === 10 || ttype === 5;
    const classesToShow = isAdminOrOffice ? window.APP_DATA.classes : [window.APP_DATA.sclass];
    const comments = window.APP_DATA.comments || [];
    const maxComId = comments.length ? Math.max.apply(null, comments.map(function (c) { return c.comid; })) : 0;

    let mode = 'term'; // 'term' | 'htwt'
    let currentStudents = [];

    function activeTermId() {
        const c = controls.find(function (c) { return c.ctype === 'term' && c.allowed; });
        return c ? c.cval : null;
    }
    function activeReportVal() {
        const c = controls.find(function (c) { return c.ctype === 'report' && c.allowed; });
        return c ? c.cval : null;
    }

    function romanToArabic(roman) {
        const map = { I: 1, V: 5, X: 10, L: 50 };
        let result = 0;
        for (let i = 0; i < roman.length; i++) {
            const current = map[roman[i]];
            const next = map[roman[i + 1]];
            if (next && current < next) result -= current; else result += current;
        }
        return result;
    }

    function isFeedingAllowedForClass(sclassValue) {
        if (isAdminOrOffice) return true;
        if (!sclassValue) return true;
        const sclass = sclassValue.split('-')[0].toUpperCase();
        const numericPart = sclass.match(/\d+/);
        const finalClass = numericPart ? parseInt(numericPart[0], 10) : romanToArabic(sclass.replace(/[^IVX]/g, ''));
        let controlName = '';
        if (finalClass >= 1 && finalClass <= 8) controlName = 'Jr_School_Marks_Feeding';
        else if (finalClass >= 9 && finalClass <= 10) controlName = '9_10_Marksfeeding';
        else if (finalClass >= 11 && finalClass <= 12) controlName = '11_12_Marksfeeding';
        if (!controlName) return true;
        const control = controls.find(function (c) { return c.control === controlName; });
        return control ? !!control.allowed : false;
    }

    function populateClassSelect() {
        const sel = $('#att_class').empty().append('<option value="">Select Class</option>');
        classesToShow.forEach(function (c) {
            if (c) sel.append($('<option>').val(c).text('Class ' + c));
        });
        if (!isAdminOrOffice && classesToShow[0]) {
            sel.val(classesToShow[0]);
            $('#att_classWrap').hide();
        }
    }

    function switchMode(newMode) {
        mode = newMode;
        $('#attSubNav a').removeClass('active');
        $('#attSubNav a[data-sub="' + mode + '"]').addClass('active');

        if (mode === 'daily') {
            $('#gridArea').hide();
            $('#dailyArea').show();
            initDaily();
            return;
        }
        $('#dailyArea').hide();
        $('#gridArea').show();
        $('#att_totalWrap').toggle(mode === 'term');
        $('#att_tableWrap').addClass('d-none');
        load();
    }

    function load() {
        const sclass = $('#att_class').val();
        $('#att_tableWrap').addClass('d-none');
        $('#att_notAllowed, #att_noActiveTerm').addClass('d-none');
        if (!sclass) return;

        if (!isFeedingAllowedForClass(sclass)) {
            $('#att_notAllowed').removeClass('d-none');
            return;
        }
        const termid = activeTermId();
        const report = activeReportVal();
        if (termid === null || report === null) {
            $('#att_noActiveTerm').removeClass('d-none');
            return;
        }

        if (mode === 'term') {
            ajaxCall({ url: '/api/attendance/get.php', method: 'GET', data: { sclass: sclass, termid: termid, report: report }, silent: true })
                .then(renderTermAttendance);
        } else {
            ajaxCall({ url: '/api/height_weight/get.php', method: 'GET', data: { sclass: sclass, termid: termid, report: report }, silent: true })
                .then(renderHtWt);
        }
    }

    function renderTermAttendance(students) {
        currentStudents = students;
        const firstWithTotal = students.find(function (s) { return s.totalattendance !== null; });
        $('#att_total').val(firstWithTotal ? firstWithTotal.totalattendance : '');

        let commentOptions = '<option value="">-</option>';
        comments.forEach(function (c) {
            commentOptions += '<option value="' + c.comid + '">' + c.comid + ' - ' + $('<div>').text(c.comment).html() + '</option>';
        });

        $('#att_tableHead').html('<tr><th>Roll</th><th>Name</th><th>Present Days</th><th>Comment</th></tr>');
        const tbody = $('#att_tableBody').empty();
        students.forEach(function (s) {
            const row = $('<tr>').attr('data-sid', s.sid);
            row.append($('<td>').text(s.roll));
            row.append($('<td>').text(s.sname));
            row.append($('<td>').html('<input type="number" class="form-control form-control-sm att-value" min="0" value="' + (s.attendance === null ? '' : s.attendance) + '">'));
            const sel = $('<select class="form-select form-select-sm att-comid">').html(commentOptions);
            sel.val(s.comid === null ? '' : s.comid);
            row.append($('<td>').append(sel));
            tbody.append(row);
        });
        $('#att_tableWrap').removeClass('d-none');
    }

    function renderHtWt(students) {
        currentStudents = students;
        $('#att_tableHead').html('<tr><th>Roll</th><th>Name</th><th>Height (cm)</th><th>Weight (kg)</th></tr>');
        const tbody = $('#att_tableBody').empty();
        // Height/weight response has no roll/sname — join against APP_DATA if needed is unnecessary since sid is enough for saving.
        students.forEach(function (s) {
            const row = $('<tr>').attr('data-sid', s.sid);
            row.append($('<td>').text(''));
            row.append($('<td>').text('SID ' + s.sid));
            row.append($('<td>').html('<input type="number" class="form-control form-control-sm ht-value" min="0" value="' + (s.ht === null ? '' : s.ht) + '">'));
            row.append($('<td>').html('<input type="number" class="form-control form-control-sm wt-value" min="0" value="' + (s.wt === null ? '' : s.wt) + '">'));
            tbody.append(row);
        });
        $('#att_tableWrap').removeClass('d-none');
    }

    function save() {
        const sclass = $('#att_class').val();
        const termid = activeTermId();
        const report = activeReportVal();

        if (mode === 'term') {
            const totalAttendance = parseInt($('#att_total').val(), 10) || 0;
            const students = [];
            $('#att_tableBody tr').each(function () {
                const attVal = $(this).find('.att-value').val();
                const comVal = $(this).find('.att-comid').val();
                if (comVal !== '' && maxComId && parseInt(comVal, 10) > maxComId) {
                    toastError('Max comment ID is ' + maxComId);
                    return;
                }
                students.push({
                    sid: parseInt($(this).data('sid'), 10),
                    attendance: attVal === '' ? '' : parseInt(attVal, 10),
                    comid: comVal === '' ? '' : parseInt(comVal, 10),
                });
            });
            confirmSave(function () {
                ajaxCall({
                    url: '/api/attendance/upsert.php',
                    data: { sclass: sclass, termid: termid, report: report, totalAttendance: totalAttendance, students: JSON.stringify(students) },
                    successMessage: 'Attendance and comments saved successfully.',
                });
            });
        } else {
            const students = [];
            $('#att_tableBody tr').each(function () {
                students.push({
                    sid: parseInt($(this).data('sid'), 10),
                    ht: $(this).find('.ht-value').val() || 0,
                    wt: $(this).find('.wt-value').val() || 0,
                });
            });
            confirmSave(function () {
                ajaxCall({
                    url: '/api/height_weight/upsert.php',
                    data: { sclass: sclass, termid: termid, report: report, students: JSON.stringify(students) },
                    successMessage: 'Height/weight saved successfully.',
                });
            });
        }
    }

    function confirmSave(onConfirm) {
        Swal.fire({
            title: 'Are you sure?',
            text: 'Click Confirm to save this data for the class.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Confirm & Save',
        }).then(function (result) {
            if (result.isConfirmed) onConfirm();
        });
    }

    // --- Daily self-attendance kiosk management ---
    let dailySub = 'live';
    let dailyInitialized = false;

    function populateDailyClassSelect() {
        const sel = $('#da_class').empty().append('<option value="">Select Class</option>');
        classesToShow.forEach(function (c) {
            if (c) sel.append($('<option>').val(c).text('Class ' + c));
        });
        if (!isAdminOrOffice && classesToShow[0]) {
            sel.val(classesToShow[0]);
            $('#da_class').prop('disabled', true);
        }

        const monthSel = $('#da_month').empty();
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const now = new Date();
        monthNames.forEach(function (name, i) {
            monthSel.append($('<option>').val(i + 1).text(name));
        });
        monthSel.val(now.getMonth() + 1);

        const yearSel = $('#da_year').empty();
        const currentYear = now.getFullYear();
        for (let y = currentYear - 2; y <= currentYear + 1; y++) {
            yearSel.append($('<option>').val(y).text(y));
        }
        yearSel.val(currentYear);

        const holidayClassSel = $('#da_holidayClasses').empty();
        classesToShow.forEach(function (c) {
            if (c) holidayClassSel.append($('<option>').val(c).text('Class ' + c));
        });
    }

    function updateKioskLink() {
        const sclass = $('#da_class').val();
        $('#da_link').val(sclass ? window.location.origin + BASE_URL + '/attendance.php?sclass=' + encodeURIComponent(sclass) : '');
    }

    function loadDailyStatus() {
        const sclass = $('#da_class').val();
        updateKioskLink();
        if (!sclass) return;
        ajaxCall({ url: '/api/daily-attendance/get_status.php', method: 'GET', data: { sclass: sclass }, silent: true })
            .then(function (data) {
                $('#da_toggleLink').prop('checked', data.isActive);
                renderDailyLive(data.attendance);
            });
    }

    function renderDailyLive(attendance) {
        const tbody = $('#da_liveBody').empty();
        attendance.forEach(function (s) {
            const row = $('<tr>').attr('data-sid', s.sid);
            row.append($('<td>').text(s.roll));
            row.append($('<td>').text(s.schno));
            row.append($('<td>').text(s.sname));
            row.append($('<td>').html(s.isPresent
                ? '<span class="badge text-bg-success">Present' + (s.markedAt ? ' (' + s.markedAt + ')' : '') + '</span>'
                : '<span class="badge text-bg-secondary">Absent</span>'));
            row.append($('<td>').html('<button class="btn btn-sm btn-outline-primary btn-toggle-daily" data-present="' + (s.isPresent ? '0' : '1') + '">' + (s.isPresent ? 'Mark Absent' : 'Mark Present') + '</button>'));
            tbody.append(row);
        });
    }

    function toggleDailyStudent(sid, makePresent) {
        ajaxCall({ url: '/api/daily-attendance/mark.php', data: { sid: sid, isPresent: makePresent ? '1' : '0' }, silent: true })
            .then(loadDailyStatus);
    }

    function loadMonthlyRegistry() {
        const sclass = $('#da_class').val();
        const month = $('#da_month').val();
        const year = $('#da_year').val();
        if (!sclass) return;
        ajaxCall({ url: '/api/daily-attendance/monthly_report.php', method: 'GET', data: { sclass: sclass, month: month, year: year }, silent: true })
            .then(renderMonthlyRegistry);
    }

    function renderMonthlyRegistry(data) {
        const headRow = $('#da_monthlyHead').empty();
        headRow.append('<th>Roll</th><th>Name</th>');
        data.days.forEach(function (d) {
            const cls = d.isSunday ? 'table-secondary' : (d.holiday ? 'table-warning' : '');
            headRow.append('<th class="text-center ' + cls + '" title="' + (d.holiday || '') + '">' + d.day + '</th>');
        });
        headRow.append('<th>Present / Working Days</th>');

        const tbody = $('#da_monthlyBody').empty();
        data.students.forEach(function (s) {
            const row = $('<tr>');
            row.append($('<td>').text(s.roll));
            row.append($('<td>').text(s.sname));
            s.daily.forEach(function (present, i) {
                const d = data.days[i];
                const cls = d.isSunday ? 'table-secondary' : (d.holiday ? 'table-warning' : (present ? 'table-success' : ''));
                row.append('<td class="text-center ' + cls + '">' + (present ? '&#10003;' : '') + '</td>');
            });
            row.append($('<td>').text(s.monthPresent + ' / ' + s.monthTotal));
            tbody.append(row);
        });
    }

    function saveHoliday(isHoliday) {
        const isRange = $('#da_isRange').is(':checked');
        const allClasses = $('#da_allClasses').is(':checked');
        const description = $('#da_holidayDesc').val().trim();
        const sclasses = allClasses ? [null] : ($('#da_holidayClasses').val() || []);

        if (!sclasses.length) {
            toastError('Select at least one class, or check "All Classes".');
            return;
        }

        if (isRange) {
            const startDate = $('#da_holidayDate').val();
            const endDate = $('#da_holidayEndDate').val();
            if (!startDate || !endDate) { toastError('Select a start and end date.'); return; }
            ajaxCall({
                url: '/api/daily-attendance/set_holiday_range.php',
                data: { startDate: startDate, endDate: endDate, sclasses: JSON.stringify(sclasses), description: description, isHoliday: isHoliday ? '1' : '0' },
                successMessage: isHoliday ? 'Holiday range set.' : 'Holiday range removed.',
            });
        } else {
            const date = $('#da_holidayDate').val();
            if (!date) { toastError('Select a date.'); return; }
            ajaxCall({
                url: '/api/daily-attendance/set_holiday.php',
                data: { date: date, sclasses: JSON.stringify(sclasses), description: description, isHoliday: isHoliday ? '1' : '0' },
                successMessage: isHoliday ? 'Holiday set.' : 'Holiday removed.',
            });
        }
    }

    function switchDailySub(sub) {
        dailySub = sub;
        $('#dailySubNav a').removeClass('active');
        $('#dailySubNav a[data-daily-sub="' + sub + '"]').addClass('active');
        $('#da_live, #da_monthly, #da_holidays').hide();
        $('#da_' + sub).show();
        if (sub === 'live') loadDailyStatus();
        if (sub === 'monthly') loadMonthlyRegistry();
    }

    function initDaily() {
        if (!dailyInitialized) {
            populateDailyClassSelect();
            dailyInitialized = true;
        }
        switchDailySub(dailySub);
    }

    $('#da_class').on('change', function () {
        updateKioskLink();
        if (dailySub === 'live') loadDailyStatus();
        if (dailySub === 'monthly') loadMonthlyRegistry();
    });
    $('#da_toggleLink').on('change', function () {
        const sclass = $('#da_class').val();
        if (!sclass) { $(this).prop('checked', false); return; }
        ajaxCall({ url: '/api/daily-attendance/toggle_link.php', data: { sclass: sclass, isActive: $(this).is(':checked') ? '1' : '0' }, successMessage: 'Attendance link updated.' });
    });
    $('#da_copyLink').on('click', function () {
        const link = $('#da_link').val();
        if (!link) return;
        navigator.clipboard.writeText(link).then(function () { toastSuccess('Link copied.'); });
    });
    $('#da_liveBody').on('click', '.btn-toggle-daily', function () {
        const sid = $(this).closest('tr').data('sid');
        toggleDailyStudent(sid, $(this).data('present') === 1 || $(this).data('present') === '1');
    });
    $('#dailySubNav').on('click', 'a', function (e) {
        e.preventDefault();
        switchDailySub($(this).data('daily-sub'));
    });
    $('#btnLoadMonthly').on('click', loadMonthlyRegistry);
    $('#da_isRange').on('change', function () { $('#da_endDateWrap').toggle($(this).is(':checked')); });
    $('#da_allClasses').on('change', function () { $('#da_holidayClasses').toggleClass('d-none', $(this).is(':checked')); });
    $('#btnSetHoliday').on('click', function () { saveHoliday(true); });
    $('#btnUnsetHoliday').on('click', function () { saveHoliday(false); });

    $('#attSubNav').on('click', 'a', function (e) {
        e.preventDefault();
        switchMode($(this).data('sub'));
    });
    $('#att_class').on('change', load);
    $('#btnSaveAttendance').on('click', save);

    populateClassSelect();
    if (activeTermId() === null || activeReportVal() === null) {
        $('#att_noActiveTerm').removeClass('d-none');
    }
    if (classesToShow.length && classesToShow[0]) {
        load();
    }
})();
