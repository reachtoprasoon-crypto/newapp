(function () {
    let currentResponsesFormId = null;

    function escapeHtml(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function fieldRowHtml(field) {
        field = field || { label: '', type: 'Text', options: '', required: true };
        const row = $('<div class="row g-2 align-items-center mb-2 fb-field-row">');
        row.append('<div class="col-4"><input type="text" class="form-control form-control-sm fb-field-label" placeholder="Field label" value="' + escapeHtml(field.label) + '"></div>');

        const typeSel = $('<select class="form-select form-select-sm fb-field-type">');
        ['Text', 'Number', 'Date', 'Select', 'Radio', 'Checkbox'].forEach(function (t) {
            typeSel.append($('<option>').val(t).text(t).prop('selected', t === field.type));
        });
        const typeCol = $('<div class="col-3">').append(typeSel);

        const optionsCol = $('<div class="col-3">').html('<input type="text" class="form-control form-control-sm fb-field-options" placeholder="Comma-separated options" value="' + escapeHtml(field.options || '') + '">');
        const reqCol = $('<div class="col-1 form-check">').html('<input class="form-check-input fb-field-required" type="checkbox"' + (field.required ? ' checked' : '') + '>');
        const rmCol = $('<div class="col-1">').html('<button type="button" class="btn btn-sm btn-outline-danger btn-remove-field"><i class="fa-solid fa-xmark"></i></button>');

        row.append(typeCol).append(optionsCol).append(reqCol).append(rmCol);
        return row;
    }

    function loadForms() {
        ajaxCall({ url: '/api/data-collection/list_forms.php', method: 'GET', silent: true }).then(renderForms);
    }

    function renderForms(forms) {
        const tbody = $('#dc_formsBody').empty();
        forms.forEach(function (f) {
            const row = $('<tr>');
            row.append($('<td>').text(f.title));
            row.append($('<td>').addClass('small').text(f.teacher_name));
            row.append($('<td>').html(f.is_active ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>'));
            row.append($('<td>').addClass('small').text(f.created_at));
            const actions = $('<td>').addClass('d-flex gap-1');
            actions.append('<button class="btn btn-sm btn-outline-secondary btn-copy-link" data-id="' + f.id + '" title="Copy public link"><i class="fa-solid fa-link"></i></button>');
            actions.append('<button class="btn btn-sm btn-outline-primary btn-view-responses" data-id="' + f.id + '" data-title="' + escapeHtml(f.title) + '"><i class="fa-solid fa-list"></i></button>');
            actions.append('<button class="btn btn-sm btn-outline-secondary btn-edit-form" data-form=\'' + JSON.stringify(f).replace(/'/g, '&#39;') + '\'><i class="fa-solid fa-pen"></i></button>');
            actions.append('<button class="btn btn-sm btn-outline-danger btn-delete-form" data-id="' + f.id + '"><i class="fa-solid fa-trash"></i></button>');
            row.append(actions);
            tbody.append(row);
        });
    }

    function openBuilder(form) {
        $('#fb_id').val(form ? form.id : '');
        $('#fb_title').text(form ? 'Edit Form' : 'New Form');
        $('#fb_formTitle').val(form ? form.title : '');
        $('#fb_description').val(form ? form.description : '');
        $('#fb_isActive').prop('checked', form ? !!form.is_active : true);
        const fieldsContainer = $('#fb_fields').empty();
        const fields = (form && form.fields && form.fields.length) ? form.fields : [{ label: '', type: 'Text', options: '', required: true }];
        fields.forEach(function (f) { fieldsContainer.append(fieldRowHtml(f)); });
        new bootstrap.Modal('#formBuilderModal').show();
    }

    function saveForm() {
        const id = $('#fb_id').val();
        const title = $('#fb_formTitle').val().trim();
        const description = $('#fb_description').val().trim();
        const isActive = $('#fb_isActive').is(':checked');
        const fields = [];
        $('.fb-field-row').each(function () {
            const label = $(this).find('.fb-field-label').val().trim();
            if (!label) return;
            fields.push({
                label: label,
                type: $(this).find('.fb-field-type').val(),
                options: $(this).find('.fb-field-options').val().trim(),
                required: $(this).find('.fb-field-required').is(':checked'),
            });
        });
        if (!title || fields.length === 0) {
            toastError('Form title and at least one field are required.');
            return;
        }
        ajaxCall({
            url: '/api/data-collection/upsert_form.php',
            data: { id: id, title: title, description: description, fields: JSON.stringify(fields), is_active: isActive ? '1' : '0' },
            successMessage: 'Form saved.',
        }).then(function () {
            bootstrap.Modal.getInstance(document.getElementById('formBuilderModal')).hide();
            loadForms();
        });
    }

    function viewResponses(formId, title) {
        currentResponsesFormId = formId;
        ajaxCall({ url: '/api/data-collection/list_responses.php', method: 'GET', data: { form_id: formId }, silent: true })
            .then(function (responses) {
                $('#dc_responsesTitle').text(title);
                const labels = {};
                responses.forEach(function (r) { Object.keys(r.responses || {}).forEach(function (k) { labels[k] = true; }); });
                const labelList = Object.keys(labels);

                const headRow = $('#dc_responsesHead').empty();
                headRow.append('<th>Roll</th><th>Name</th><th>Class</th>');
                labelList.forEach(function (l) { headRow.append('<th>' + escapeHtml(l) + '</th>'); });
                headRow.append('<th></th>');

                const tbody = $('#dc_responsesBody').empty();
                responses.forEach(function (r) {
                    const row = $('<tr>');
                    row.append($('<td>').text(r.roll));
                    row.append($('<td>').text(r.sname));
                    row.append($('<td>').text(r.sclass));
                    labelList.forEach(function (l) {
                        row.append($('<td>').text((r.responses && r.responses[l]) || ''));
                    });
                    row.append($('<td>').html('<button class="btn btn-sm btn-outline-danger btn-delete-response" data-id="' + r.id + '"><i class="fa-solid fa-trash"></i></button>'));
                    tbody.append(row);
                });

                $('#dc_responsesArea').removeClass('d-none');
            });
    }

    $('#btnNewForm').on('click', function () { openBuilder(null); });
    $('#btnAddField').on('click', function () { $('#fb_fields').append(fieldRowHtml(null)); });
    $('#fb_fields').on('click', '.btn-remove-field', function () { $(this).closest('.fb-field-row').remove(); });
    $('#btnSaveForm').on('click', saveForm);

    $('#dc_formsBody').on('click', '.btn-edit-form', function () {
        openBuilder(JSON.parse($(this).attr('data-form')));
    });
    $('#dc_formsBody').on('click', '.btn-delete-form', function () {
        const id = $(this).data('id');
        confirmDelete('This form and all its responses will be permanently deleted.').then(function (confirmed) {
            if (!confirmed) return;
            ajaxCall({ url: '/api/data-collection/delete_form.php', data: { id: id }, successMessage: 'Form deleted.' }).then(loadForms);
        });
    });
    $('#dc_formsBody').on('click', '.btn-view-responses', function () {
        viewResponses($(this).data('id'), $(this).data('title'));
    });
    $('#dc_formsBody').on('click', '.btn-copy-link', function () {
        const link = window.location.origin + BASE_URL + '/collect.php?form_id=' + $(this).data('id');
        navigator.clipboard.writeText(link).then(function () { toastSuccess('Link copied.'); });
    });
    $('#btnCloseResponses').on('click', function () { $('#dc_responsesArea').addClass('d-none'); });
    $('#btnExportResponses').on('click', function () {
        if (!currentResponsesFormId) return;
        triggerDownload(BASE_URL + '/api/data-collection/export_responses.php?form_id=' + currentResponsesFormId);
    });
    $('#dc_responsesBody').on('click', '.btn-delete-response', function () {
        const id = $(this).data('id');
        const $row = $(this).closest('tr');
        confirmDelete('This response will be permanently deleted.').then(function (confirmed) {
            if (!confirmed) return;
            ajaxCall({ url: '/api/data-collection/delete_response.php', data: { id: id }, successMessage: 'Response deleted.' })
                .then(function () { $row.remove(); });
        });
    });

    loadForms();
})();
