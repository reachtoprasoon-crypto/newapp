(function () {
    function populateClassSelect() {
        const sel = $('#apt_class').empty().append('<option value="">Select Class</option>');
        window.APP_DATA.classes.forEach(function (c) {
            sel.append($('<option>').val(c).text('Class ' + c));
        });
    }

    function loadMarks() {
        const sclass = $('#apt_class').val();
        $('#apt_tableWrap').addClass('d-none');
        if (!sclass) return;
        ajaxCall({ url: '/api/aptitude/get.php', method: 'GET', data: { sclass: sclass }, silent: true })
            .then(renderMarks);
    }

    function renderMarks(students) {
        const tbody = $('#apt_tableBody').empty();
        students.forEach(function (s) {
            const row = $('<tr>').attr('data-sid', s.sid);
            row.append($('<td>').text(s.roll));
            row.append($('<td>').text(s.schno));
            row.append($('<td>').text(s.sname));
            row.append($('<td>').html('<input type="number" class="form-control form-control-sm apt-marks" min="0" value="' + (s.marks === null ? '' : s.marks) + '">'));
            tbody.append(row);
        });
        $('#apt_tableWrap').removeClass('d-none');
    }

    function saveMarks() {
        const marks = [];
        $('#apt_tableBody tr').each(function () {
            const val = $(this).find('.apt-marks').val();
            if (val !== '') {
                marks.push({ sid: parseInt($(this).data('sid'), 10), marks: parseInt(val, 10) });
            }
        });
        ajaxCall({ url: '/api/aptitude/upsert.php', data: { marks: JSON.stringify(marks) }, successMessage: 'Aptitude marks saved.' })
            .then(loadMarks);
    }

    function exportLogsheet() {
        const sclass = $('#apt_class').val();
        if (!sclass) {
            toastError('Select a class first.');
            return;
        }
        window.location.href = BASE_URL + '/api/aptitude/export_excel.php?sclass=' + encodeURIComponent(sclass);
    }

    $('#apt_class').on('change', loadMarks);
    $('#btnSaveAptitude').on('click', saveMarks);
    $('#btnLoadLogsheet').on('click', exportLogsheet);

    populateClassSelect();
})();
