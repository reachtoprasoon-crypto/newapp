(function () {
    const tid = window.APP_DATA.tid;
    let mode = 'mine';

    function escapeHtml(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function populateClassSelects() {
        const cf = $('#cf_classes').empty();
        const filter = $('#comm_classFilter').empty();
        window.APP_DATA.classes.forEach(function (c) {
            cf.append($('<option>').val(c).text('Class ' + c));
            filter.append($('<option>').val(c).text('Class ' + c));
        });
    }

    function switchMode(newMode) {
        mode = newMode;
        $('#commSubNav a').removeClass('active');
        $('#commSubNav a[data-comm-sub="' + mode + '"]').addClass('active');
        $('#comm_classWrap').toggleClass('d-none', mode !== 'class');
        load();
    }

    function load() {
        if (mode === 'mine') {
            ajaxCall({ url: '/api/communications/list.php', method: 'GET', data: { mine: 1 }, silent: true }).then(render);
        } else {
            const sclass = $('#comm_classFilter').val();
            if (!sclass) { $('#comm_body').empty(); return; }
            ajaxCall({ url: '/api/communications/list.php', method: 'GET', data: { sclass: sclass }, silent: true }).then(render);
        }
    }

    function render(items) {
        const tbody = $('#comm_body').empty();
        items.forEach(function (c) {
            const row = $('<tr>');
            row.append($('<td>').addClass('small text-nowrap').text(c.created_at));
            row.append($('<td>').html('<span class="badge text-bg-secondary">' + c.comm_type + '</span>'));
            row.append($('<td>').text(c.title));
            row.append($('<td>').addClass('small').text(c.sclass));
            row.append($('<td>').addClass('small').text(c.teacher_name));
            row.append($('<td>').html(c.attachment_file ? '<a href="' + c.attachment_file + '" download="' + escapeHtml(c.attachment_name) + '" class="small">' + escapeHtml(c.attachment_name) + '</a>' : ''));
            const actions = (mode === 'mine' && c.tid === tid)
                ? '<button class="btn btn-sm btn-outline-danger btn-delete-comm" data-commid="' + c.commid + '"><i class="fa-solid fa-trash"></i></button>'
                : '';
            row.append($('<td>').html(actions));
            tbody.append(row);
        });
    }

    function sendCommunication() {
        const title = $('#cf_title').val().trim();
        const commType = $('#cf_type').val();
        const content = $('#cf_content').val().trim();
        const sclasses = $('#cf_classes').val() || [];
        if (!title || sclasses.length === 0) {
            toastError('Title and at least one recipient class are required.');
            return;
        }

        const fileInput = $('#cf_attachment')[0];
        const file = fileInput.files[0];

        function submit(attachmentFile, attachmentName) {
            ajaxCall({
                url: '/api/communications/create.php',
                data: {
                    title: title, comm_type: commType, content: content,
                    sclasses: JSON.stringify(sclasses),
                    attachment_file: attachmentFile || '', attachment_name: attachmentName || '',
                },
                successMessage: 'Communication sent.',
            }).then(function () {
                bootstrap.Modal.getInstance(document.getElementById('commFormModal')).hide();
                $('#commForm')[0].reset();
                switchMode('mine');
            });
        }

        if (file) {
            const reader = new FileReader();
            reader.onloadend = function () { submit(reader.result, file.name); };
            reader.readAsDataURL(file);
        } else {
            submit(null, null);
        }
    }

    $('#btnNewCommunication').on('click', function () {
        $('#commForm')[0].reset();
        new bootstrap.Modal('#commFormModal').show();
    });
    $('#btnSendCommunication').on('click', sendCommunication);
    $('#commSubNav').on('click', 'a', function (e) {
        e.preventDefault();
        switchMode($(this).data('comm-sub'));
    });
    $('#comm_classFilter').on('change', load);
    $('#comm_body').on('click', '.btn-delete-comm', function () {
        const commid = $(this).data('commid');
        confirmDelete('This communication will be permanently deleted.').then(function (confirmed) {
            if (!confirmed) return;
            ajaxCall({ url: '/api/communications/delete.php', data: { commid: commid }, successMessage: 'Deleted.' }).then(load);
        });
    });

    populateClassSelects();
    load();
})();
