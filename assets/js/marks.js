(function () {
    const ttype = window.APP_DATA.ttype;
    const tid = window.APP_DATA.tid;
    const controls = window.APP_DATA.controls;
    const isAdminOrOffice = ttype === 10 || ttype === 5;
    const classesToShow = isAdminOrOffice ? window.APP_DATA.classes : window.APP_DATA.teacherClasses;

    let currentSubjects = [];
    let currentSchedules = [];
    let currentRows = [];
    let isGradedSelected = false;
    let maxMarks = 100;

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
        const sel = $('#mk_class');
        classesToShow.forEach(function (c) {
            sel.append($('<option>').val(c).text('Class ' + c));
        });
    }

    function resetDownstream() {
        $('#mk_subject').html('<option value="">Select Subject</option>').prop('disabled', true);
        $('#mk_scheduleWrap').hide();
        $('#mk_tableWrap').addClass('d-none');
        $('#mk_notAllowed').addClass('d-none');
        currentSubjects = [];
        currentRows = [];
    }

    function loadSubjects(sclass) {
        resetDownstream();
        if (!sclass) return;

        if (!isFeedingAllowedForClass(sclass)) {
            $('#mk_notAllowed').removeClass('d-none');
            return;
        }

        ajaxCall({ url: '/api/marks/class_subjects_schedule.php', method: 'GET', data: { sclass: sclass }, silent: true })
            .then(function (scheduled) {
                const scheduledSubjects = scheduled.filter(function (s) { return s.isScheduled; });

                if (isAdminOrOffice) {
                    finishSubjects(scheduledSubjects);
                    return;
                }

                const scheduledIds = {};
                scheduledSubjects.forEach(function (s) { scheduledIds[s.subid] = true; });

                ajaxCall({ url: '/api/marks/teacher_subjects.php', method: 'GET', data: { tid: tid, sclass: sclass }, silent: true })
                    .then(function (teacherAssigned) {
                        let finalSubjects = teacherAssigned.filter(function (s) { return scheduledIds[s.subid]; });

                        if (ttype === 1 && window.APP_DATA.sclass === sclass) {
                            ajaxCall({ url: '/api/marks/class_subjects_for_grading.php', method: 'GET', data: { sclass: sclass, isClassTeacher: 1 }, silent: true })
                                .then(function (special) {
                                    const filteredSpecial = special.filter(function (s) { return scheduledIds[s.subid]; });
                                    const combined = finalSubjects.concat(filteredSpecial);
                                    const uniq = {};
                                    combined.forEach(function (s) { uniq[s.subid] = s; });
                                    finishSubjects(Object.values(uniq));
                                });
                        } else {
                            finishSubjects(finalSubjects);
                        }
                    });
            });
    }

    function finishSubjects(subjects) {
        subjects.sort(function (a, b) { return a.subname.localeCompare(b.subname); });
        currentSubjects = subjects;
        const sel = $('#mk_subject').empty().append('<option value="">Select Subject</option>').prop('disabled', false);
        subjects.forEach(function (s) {
            sel.append($('<option>').val(s.subid).text(s.subname));
        });
    }

    function onSubjectChange() {
        const subid = $('#mk_subject').val();
        $('#mk_scheduleWrap').hide();
        $('#mk_tableWrap').addClass('d-none');
        currentRows = [];
        if (!subid) return;

        const subject = currentSubjects.find(function (s) { return String(s.subid) === subid; });
        if (!subject) return;
        isGradedSelected = subject.subtype === 0 || subject.subname === 'Moral Science' || subject.subname === 'SUPW';

        const sclass = $('#mk_class').val();
        const termid = activeTermId();
        const report = activeReportVal();
        if (termid === null || report === null) {
            $('#marksNoActiveTerm').removeClass('d-none');
            return;
        }
        $('#marksNoActiveTerm').addClass('d-none');

        if (isGradedSelected) {
            $('#mk_valueHeader').text('Grade');
            ajaxCall({ url: '/api/grades/get.php', method: 'GET', data: { sclass: sclass, subid: subid, termid: termid, report: report }, silent: true })
                .then(renderGradeRows);
        } else {
            $('#mk_scheduleWrap').show();
            ajaxCall({ url: '/api/marks/schedules.php', method: 'GET', data: { sclass: sclass, subid: subid, termid: termid, report: report }, silent: true })
                .then(function (schedules) {
                    currentSchedules = schedules;
                    const sel = $('#mk_schedule').empty().append('<option value="">Select Assessment</option>');
                    schedules.forEach(function (s) {
                        sel.append($('<option>').val(s.termschid).text(s.examname + ' (max ' + s.maxm + ')'));
                    });
                });
        }
    }

    function onScheduleChange() {
        const termschid = $('#mk_schedule').val();
        $('#mk_tableWrap').addClass('d-none');
        if (!termschid) return;

        const schedule = currentSchedules.find(function (s) { return String(s.termschid) === termschid; });
        maxMarks = schedule ? schedule.maxm : 100;
        $('#mk_valueHeader').text('Marks (max ' + maxMarks + ')');

        const sclass = $('#mk_class').val();
        ajaxCall({ url: '/api/marks/students_with_marks.php', method: 'GET', data: { sclass: sclass, termschid: termschid }, silent: true })
            .then(renderMarksRows);
    }

    function renderMarksRows(students) {
        currentRows = students;
        const tbody = $('#mk_tableBody').empty();
        students.forEach(function (s) {
            const row = $('<tr>').attr('data-sid', s.sid);
            row.append($('<td>').text(s.roll));
            row.append($('<td>').text(s.sname));
            row.append($('<td>').html('<input type="number" class="form-control form-control-sm mk-value" min="0" max="' + maxMarks + '" value="' + (s.marks === null ? '' : s.marks) + '">'));
            tbody.append(row);
        });
        $('#mk_tableWrap').removeClass('d-none');
    }

    function renderGradeRows(students) {
        currentRows = students;
        const options = ['N/A', 'A', 'B', 'C', 'D'];
        const tbody = $('#mk_tableBody').empty();
        students.forEach(function (s) {
            const row = $('<tr>').attr('data-sid', s.sid);
            row.append($('<td>').text(s.roll));
            row.append($('<td>').text(s.sname));
            const currentGrade = s.grade || 'N/A';
            let selectHtml = '<select class="form-select form-select-sm mk-value">';
            options.forEach(function (o) {
                selectHtml += '<option value="' + o + '"' + (o === currentGrade ? ' selected' : '') + '>' + o + '</option>';
            });
            selectHtml += '</select>';
            row.append($('<td>').html(selectHtml));
            tbody.append(row);
        });
        $('#mk_tableWrap').removeClass('d-none');
    }

    function save() {
        const sclass = $('#mk_class').val();
        const subid = $('#mk_subject').val();
        const termid = activeTermId();
        const report = activeReportVal();

        if (isGradedSelected) {
            const grades = [];
            $('#mk_tableBody tr').each(function () {
                grades.push({ sid: parseInt($(this).data('sid'), 10), grade: $(this).find('.mk-value').val() });
            });
            ajaxCall({
                url: '/api/grades/upsert.php',
                data: { sclass: sclass, subid: subid, termid: termid, report: report, grades: JSON.stringify(grades) },
                successMessage: 'Grades saved successfully.',
            });
        } else {
            const termschid = $('#mk_schedule').val();
            if (!termschid) return;
            const marks = [];
            $('#mk_tableBody tr').each(function () {
                const val = $(this).find('.mk-value').val();
                if (val !== '') {
                    marks.push({ sid: parseInt($(this).data('sid'), 10), marks: parseInt(val, 10) });
                }
            });
            ajaxCall({
                url: '/api/marks/upsert.php',
                data: { termschid: termschid, marks: JSON.stringify(marks) },
                successMessage: 'Marks saved successfully.',
            });
        }
    }

    $('#mk_class').on('change', function () { loadSubjects($(this).val()); });
    $('#mk_subject').on('change', onSubjectChange);
    $('#mk_schedule').on('change', onScheduleChange);
    $('#btnSaveMarks').on('click', save);

    populateClassSelect();
    if (activeTermId() === null || activeReportVal() === null) {
        $('#marksNoActiveTerm').removeClass('d-none');
    }
})();
