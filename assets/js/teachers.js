(function () {
    let currentTeachers = [];

    function escapeHtml(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function populateSelects() {
        const classSelect = $('#actClass').empty();
        window.APP_DATA.classes.forEach(function (c) {
            classSelect.append($('<option>').val(c).text('Class ' + c));
        });
        renderTeacherOptions(window.APP_DATA.teachers);
    }

    function renderTeacherOptions(teachers) {
        const teacherSelect = $('#actTeacher').empty();
        teachers.forEach(function (t) {
            teacherSelect.append($('<option>').val(t.tid).text(t.tname));
        });
    }

    function renderTeachers(teachers) {
        currentTeachers = teachers;
        const tbody = $('#teachersTableBody').empty();
        $('#teachersEmptyMessage').toggleClass('d-none', teachers.length > 0);

        teachers.forEach(function (t) {
            const subjects = t.subjectsTaught.map(function (s) { return s.subname + ' (' + s.sclass + ')'; }).join(', ');
            const classTeacherBadge = t.isClassTeacherOf
                ? '<span class="badge text-bg-primary">' + escapeHtml(t.isClassTeacherOf) + '</span>'
                : '<span class="text-muted small">&mdash;</span>';

            const row = $('<tr>');
            row.append($('<td>').text(t.tname));
            row.append($('<td>').text(t.tuser));
            row.append($('<td>').html(classTeacherBadge));
            row.append($('<td>').addClass('small text-muted').text(subjects || '—'));
            row.append($('<td>').html('<button class="btn btn-sm btn-outline-secondary btn-edit-teacher" data-tid="' + t.tid + '"><i class="fa-solid fa-pen"></i></button>'));
            tbody.append(row);
        });
    }

    function doSearch() {
        const q = $('#teacherSearchInput').val().trim();
        ajaxCall({ url: '/api/reference/search_teachers.php', method: 'GET', data: { q: q }, silent: true })
            .then(renderTeachers);
    }

    function openTeacherForm(teacher) {
        $('#teacherForm')[0].reset();
        if (teacher) {
            $('#teacherFormTitle').text('Edit ' + teacher.tname);
            $('#tf_tid').val(teacher.tid);
            $('#tf_tname').val(teacher.tname);
            $('#tf_tuser').val(teacher.tuser);
            $('#tf_tpass').val('');
            $('#tf_tpass_hint').show();
            $('#tf_phone').val(teacher.phone);
            $('#tf_dob').val(teacher.dob || '');
        } else {
            $('#teacherFormTitle').text('Add New Teacher');
            $('#tf_tid').val('');
            $('#tf_tpass_hint').hide();
        }
        new bootstrap.Modal('#teacherFormModal').show();
    }

    function saveTeacher() {
        const tid = $('#tf_tid').val();
        const isNew = !tid;
        const tpass = $('#tf_tpass').val();

        if (isNew) {
            const payload = {
                tname: $('#tf_tname').val().trim(),
                tuser: $('#tf_tuser').val().trim(),
                tpass: tpass,
                dob: $('#tf_dob').val().trim(),
            };
            ajaxCall({ url: '/api/teachers/add.php', data: payload, successMessage: 'Teacher added successfully.' })
                .then(function () {
                    bootstrap.Modal.getInstance(document.getElementById('teacherFormModal')).hide();
                    doSearch();
                });
        } else {
            const payload = {
                tid: tid,
                tname: $('#tf_tname').val().trim(),
                tuser: $('#tf_tuser').val().trim(),
                phone: $('#tf_phone').val().trim(),
                dob: $('#tf_dob').val().trim(),
            };
            if (tpass) {
                payload.tpass = tpass;
            }
            ajaxCall({ url: '/api/teachers/update.php', data: payload, successMessage: 'Teacher updated successfully.' })
                .then(function () {
                    bootstrap.Modal.getInstance(document.getElementById('teacherFormModal')).hide();
                    doSearch();
                });
        }
    }

    function assignClassTeacher() {
        const sclass = $('#actClass').val();
        const tid = $('#actTeacher').val();
        if (!sclass || !tid) return;

        ajaxCall({ url: '/api/teachers/assign_class_teacher.php', data: { sclass: sclass, tid: tid }, successMessage: 'Class teacher assigned.' })
            .then(function () {
                doSearch();
            });
    }

    $('#btnAddTeacher').on('click', function () { openTeacherForm(null); });
    $('#btnTeacherSearch').on('click', doSearch);
    $('#teacherSearchInput').on('keydown', function (e) {
        if (e.key === 'Enter') doSearch();
    });
    $('#teachersTableBody').on('click', '.btn-edit-teacher', function () {
        const tid = parseInt($(this).data('tid'), 10);
        const teacher = currentTeachers.find(function (t) { return t.tid === tid; });
        if (teacher) openTeacherForm(teacher);
    });
    $('#btnSaveTeacher').on('click', saveTeacher);
    $('#btnAssignClassTeacher').on('click', assignClassTeacher);

    populateSelects();
    renderTeachers(window.APP_DATA.allTeacherDetails || []);
})();
