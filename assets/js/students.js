(function () {
    const ttype = window.APP_DATA.ttype;
    const canEdit = ttype === 10 || ttype === 5;
    const canDelete = ttype === 10;
    let currentStudents = [];
    let currentMode = null; // 'class' | 'search'

    function escapeHtml(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function populateSelects() {
        const classSelect = $('#studentClassSelect');
        window.APP_DATA.classes.forEach(function (c) {
            classSelect.append($('<option>').val(c).text('Class ' + c));
        });
        const formClassSelect = $('#sf_sclass');
        window.APP_DATA.classes.forEach(function (c) {
            formClassSelect.append($('<option>').val(c).text('Class ' + c));
        });
        const houseSelect = $('#sf_hid');
        window.APP_DATA.houses.forEach(function (h) {
            houseSelect.append($('<option>').val(h.hid).text(h.house));
        });

        if (ttype === 1) {
            // Class Teacher: lock to their own class, no browsing other classes.
            $('#classSelectWrap').hide();
            $('#studentClassSelect').val(window.APP_DATA.sclass);
            loadClass(window.APP_DATA.sclass);
        }
        if (canEdit) {
            $('#btnAddStudent, #btnEditRollNumbers, #btnBulkPhoto').removeClass('d-none');
        }
    }

    function renderStudents(students) {
        currentStudents = students;
        const tbody = $('#studentsTable tbody').empty();
        $('#studentsEmptyMessage').toggleClass('d-none', students.length > 0);

        students.forEach(function (s) {
            const photo = s.photo || '';
            const avatar = photo
                ? '<img src="' + photo + '" class="rounded-circle" width="36" height="36" style="object-fit:cover;">'
                : '<span class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;">' + escapeHtml(s.sname.charAt(0)) + '</span>';

            let actions = '';
            actions += '<button class="btn btn-sm btn-outline-secondary btn-student-notes me-1" data-sid="' + s.sid + '" data-name="' + escapeHtml(s.sname) + '" title="Notes"><i class="fa-solid fa-note-sticky"></i></button>';
            if (canEdit) {
                actions += '<button class="btn btn-sm btn-outline-secondary btn-edit-student me-1" data-sid="' + s.sid + '"><i class="fa-solid fa-pen"></i></button>';
            }
            if (canDelete) {
                actions += '<button class="btn btn-sm btn-outline-danger btn-delete-student" data-sid="' + s.sid + '" data-name="' + escapeHtml(s.sname) + '"><i class="fa-solid fa-box-archive"></i></button>';
            }

            const row = $('<tr>');
            row.append($('<td>').html(avatar));
            row.append($('<td>').text(s.roll));
            row.append($('<td>').text(s.sname));
            row.append($('<td>').text(s.pname || ''));
            row.append($('<td>').text(s.phone || ''));
            row.append($('<td>').html(actions));
            tbody.append(row);
        });
    }

    function loadClass(sclass) {
        if (!sclass) {
            renderStudents([]);
            return;
        }
        currentMode = 'class';
        ajaxCall({ url: '/api/students/list.php', method: 'GET', data: { sclass: sclass }, silent: true })
            .then(renderStudents);
    }

    function doSearch() {
        const q = $('#studentSearchInput').val().trim();
        if (!q) {
            renderStudents([]);
            return;
        }
        currentMode = 'search';
        $('#studentClassSelect').val('');
        ajaxCall({ url: '/api/students/search.php', method: 'GET', data: { q: q }, silent: true })
            .then(renderStudents);
    }

    function refreshCurrentView() {
        if (currentMode === 'search') {
            doSearch();
        } else {
            loadClass($('#studentClassSelect').val());
        }
    }

    function openStudentForm(student) {
        $('#studentForm')[0].reset();
        $('#sf_photo_preview').addClass('d-none').attr('src', '');
        $('#sf_photo').val('');

        if (student) {
            $('#studentFormTitle').text('Edit ' + student.sname);
            $('#sf_sid').val(student.sid);
            $('#sf_sname').val(student.sname);
            $('#sf_roll').val(student.roll);
            $('#sf_schno').val(student.schno);
            $('#sf_pname').val(student.pname);
            $('#sf_mname').val(student.mname);
            $('#sf_phone').val(student.phone);
            $('#sf_dob').val(student.dob);
            $('#sf_sclass').val(student.sclass);
            $('#sf_branch').val(student.branch);
            $('#sf_hid').val(student.hid);
            $('#sf_ht').val(student.ht);
            $('#sf_wt').val(student.wt);
            if (student.photo) {
                $('#sf_photo').val(student.photo);
                $('#sf_photo_preview').removeClass('d-none').attr('src', student.photo);
            }
        } else {
            $('#studentFormTitle').text('Add New Student');
            $('#sf_sid').val('');
            if (ttype === 1) {
                $('#sf_sclass').val(window.APP_DATA.sclass);
            }
        }
        new bootstrap.Modal('#studentFormModal').show();
    }

    function saveStudent() {
        const payload = {
            sid: $('#sf_sid').val(),
            sname: $('#sf_sname').val().trim(),
            roll: $('#sf_roll').val(),
            schno: $('#sf_schno').val(),
            pname: $('#sf_pname').val().trim(),
            mname: $('#sf_mname').val().trim(),
            phone: $('#sf_phone').val().trim(),
            dob: $('#sf_dob').val().trim(),
            sclass: $('#sf_sclass').val(),
            branch: $('#sf_branch').val().trim(),
            hid: $('#sf_hid').val(),
            ht: $('#sf_ht').val(),
            wt: $('#sf_wt').val(),
            photo: $('#sf_photo').val(),
        };

        const isNew = !payload.sid;
        const url = isNew ? '/api/students/add.php' : '/api/students/update.php';

        ajaxCall({ url: url, data: payload, successMessage: 'Student ' + (isNew ? 'added' : 'updated') + ' successfully.' })
            .then(function () {
                bootstrap.Modal.getInstance(document.getElementById('studentFormModal')).hide();
                refreshCurrentView();
            });
    }

    function deleteStudent(sid, sname) {
        confirmDelete('This will move ' + sname + ' to class "13Z" for archival purposes.').then(function (confirmed) {
            if (!confirmed) return;
            ajaxCall({ url: '/api/students/delete.php', data: { sid: sid }, successMessage: sname + ' has been archived.' })
                .then(refreshCurrentView);
        });
    }

    function openRollNumberEditor() {
        const tbody = $('#rollNumberRows').empty();
        currentStudents.forEach(function (s) {
            const row = $('<tr>').attr('data-sid', s.sid);
            row.append($('<td>').addClass('text-muted small').text(s.schno));
            row.append($('<td>').text(s.sname));
            row.append($('<td>').html('<input type="number" class="form-control form-control-sm roll-input" value="' + s.roll + '">'));
            tbody.append(row);
        });
        new bootstrap.Modal('#rollNumberModal').show();
    }

    function saveRollNumbers() {
        const students = [];
        $('#rollNumberRows tr').each(function () {
            students.push({ sid: parseInt($(this).data('sid'), 10), roll: parseInt($(this).find('.roll-input').val(), 10) });
        });
        ajaxCall({ url: '/api/students/roll_numbers.php', data: { students: JSON.stringify(students) }, successMessage: 'Roll numbers updated successfully.' })
            .then(function () {
                bootstrap.Modal.getInstance(document.getElementById('rollNumberModal')).hide();
                refreshCurrentView();
            });
    }

    function applyBulkPhotos() {
        const files = $('#bulkPhotoFiles')[0].files;
        if (!files.length) {
            toastError('Please select at least one photo.');
            return;
        }
        const formData = new FormData();
        for (let i = 0; i < files.length; i++) {
            formData.append('photos[]', files[i]);
        }
        ajaxCall({ url: '/api/students/bulk_photo.php', data: formData, isFormData: true, successMessage: 'Photos updated.' })
            .then(function () {
                bootstrap.Modal.getInstance(document.getElementById('bulkPhotoModal')).hide();
                refreshCurrentView();
            });
    }

    $('#studentClassSelect').on('change', function () {
        $('#studentSearchInput').val('');
        loadClass($(this).val());
    });
    $('#btnStudentSearch').on('click', doSearch);
    $('#studentSearchInput').on('keydown', function (e) {
        if (e.key === 'Enter') doSearch();
    });
    $('#btnAddStudent').on('click', function () { openStudentForm(null); });
    $('#studentsTable').on('click', '.btn-edit-student', function () {
        const sid = parseInt($(this).data('sid'), 10);
        const student = currentStudents.find(function (s) { return s.sid === sid; });
        if (student) openStudentForm(student);
    });
    $('#studentsTable').on('click', '.btn-delete-student', function () {
        deleteStudent(parseInt($(this).data('sid'), 10), $(this).data('name'));
    });
    $('#btnSaveStudent').on('click', saveStudent);
    $('#sf_photo_file').on('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onloadend = function () {
            $('#sf_photo').val(reader.result);
            $('#sf_photo_preview').removeClass('d-none').attr('src', reader.result);
        };
        reader.readAsDataURL(file);
    });
    $('#btnEditRollNumbers').on('click', openRollNumberEditor);
    $('#btnSaveRollNumbers').on('click', saveRollNumbers);
    $('#btnBulkPhoto').on('click', function () { new bootstrap.Modal('#bulkPhotoModal').show(); });
    $('#btnApplyBulkPhoto').on('click', applyBulkPhotos);

    let notesCurrentSid = null;

    function loadNotes(sid) {
        ajaxCall({ url: '/api/student-notes/list.php', method: 'GET', data: { sid: sid }, silent: true }).then(renderNotes);
    }

    function renderNotes(notes) {
        const list = $('#notesList').empty();
        if (!notes.length) {
            list.append('<p class="text-muted small">No notes yet.</p>');
        }
        notes.forEach(function (n) {
            const item = $('<div class="border rounded p-2 mb-2">');
            item.append('<div class="small text-muted d-flex justify-content-between"><span>' + escapeHtml(n.teacher_name) + '</span><span>' + n.updated_at + '</span></div>');
            item.append('<div>' + escapeHtml(n.note_content) + '</div>');
            if (n.tid === window.APP_DATA.tid) {
                item.append('<button class="btn btn-sm btn-link p-0 text-danger btn-delete-note" data-note-id="' + n.note_id + '">Delete</button>');
            }
            list.append(item);
        });
    }

    $('#studentsTable').on('click', '.btn-student-notes', function () {
        notesCurrentSid = parseInt($(this).data('sid'), 10);
        $('#notesStudentName').text($(this).data('name'));
        $('#newNoteContent').val('');
        loadNotes(notesCurrentSid);
        new bootstrap.Modal('#studentNotesModal').show();
    });
    $('#btnAddNote').on('click', function () {
        const content = $('#newNoteContent').val().trim();
        if (!content) return;
        ajaxCall({ url: '/api/student-notes/upsert.php', data: { sid: notesCurrentSid, note_content: content }, successMessage: 'Note added.' })
            .then(function () { $('#newNoteContent').val(''); loadNotes(notesCurrentSid); });
    });
    $('#notesList').on('click', '.btn-delete-note', function () {
        const noteId = $(this).data('note-id');
        confirmDelete('This note will be permanently deleted.').then(function (confirmed) {
            if (!confirmed) return;
            ajaxCall({ url: '/api/student-notes/delete.php', data: { note_id: noteId }, successMessage: 'Note deleted.' })
                .then(function () { loadNotes(notesCurrentSid); });
        });
    });

    populateSelects();
    renderStudents([]);
})();
