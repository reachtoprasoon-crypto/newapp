(function () {
    let isPrivileged = false;
    let myTid = Number(window.APP_DATA.tid);
    let qIndex = 0;

    function classesForSelect() {
        return isPrivileged ? window.APP_DATA.classes : window.APP_DATA.teacherClasses;
    }

    function populateClassSelect() {
        const $sel = $('#qp_sclass');
        $sel.find('option:not(:first)').remove();
        classesForSelect().forEach(function (c) { $sel.append($('<option>').val(c).text('Class ' + c)); });
    }

    // Returns a promise that resolves once #qp_subid's options are populated.
    function populateSubjectSelect(sclass) {
        const $sel = $('#qp_subid');
        $sel.find('option:not(:first)').remove();
        if (!sclass) return $.Deferred().resolve().promise();
        if (isPrivileged) {
            (window.APP_DATA.subjects || []).forEach(function (s) { $sel.append($('<option>').val(s.subid).text(s.subname)); });
            return $.Deferred().resolve().promise();
        }
        return ajaxCall({ url: '/api/marks/teacher_subjects.php', method: 'GET', data: { tid: myTid, sclass: sclass }, silent: true })
            .then(function (subjects) {
                subjects.forEach(function (s) { $sel.append($('<option>').val(s.subid).text(s.subname)); });
            });
    }

    // One self-contained camera/upload/preview/clear control group for a
    // single image field. Every piece paperAttachImageCapture needs lives
    // inside this one element, so it can always be used as the $scope.
    function imageCaptureRowHtml(field, value, previewHeight) {
        return $(
            '<div class="d-flex align-items-center gap-2 mt-1 qp-image-row" data-field="' + field + '">' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qp-camera-btn"><i class="fa-solid fa-camera"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qp-upload-btn"><i class="fa-solid fa-upload"></i></button>' +
            '<input type="file" accept="image/*" class="d-none qp-file-input">' +
            '<input type="hidden" class="qp-hidden-image" value="' + $('<div>').text(value || '').html() + '">' +
            '<img class="qp-image-preview rounded border ' + (value ? '' : 'd-none') + '" style="height:' + previewHeight + 'px" src="' + (value || '') + '">' +
            '<button type="button" class="btn btn-sm btn-outline-danger qp-clear-image ' + (value ? '' : 'd-none') + '"><i class="fa-solid fa-xmark"></i></button>' +
            '</div>'
        );
    }

    function wireImageRow($row) {
        paperAttachImageCapture($row, {
            hiddenSelector: '.qp-hidden-image',
            previewSelector: '.qp-image-preview',
            cameraBtnSelector: '.qp-camera-btn',
            fileInputSelector: '.qp-file-input',
            clearBtnSelector: '.qp-clear-image',
        });
    }

    // Wires a textarea/input to its formatting toolbar + live preview pane.
    function wireTextEditor(id, $mount, $preview, initialValue) {
        paperAttachToolbar($mount, id);
        $('#' + id).on('input', function () { paperRenderPreview($(this).val(), $preview); });
        paperRenderPreview(initialValue, $preview);
    }

    function questionCardHtml(q, idx) {
        q = q || { question_text: '', question_image: '', option_a: '', option_a_image: '', option_b: '', option_b_image: '', option_c: '', option_c_image: '', option_d: '', option_d_image: '', correct_option: 'A', marks: 0.5 };
        const textareaId = 'qp_q_text_' + idx;

        const $card = $('<div class="card mb-3 border-start border-4 border-primary qp-question-card" data-idx="' + idx + '">');
        const $body = $('<div class="card-body p-2">');
        $card.append($body);

        $body.append(
            '<div class="d-flex justify-content-between align-items-center mb-1">' +
            '<label class="fw-bold small text-uppercase text-primary">Question</label>' +
            '<div class="d-flex align-items-center gap-2">' +
            '<label class="small text-muted mb-0">Marks</label>' +
            '<input type="number" step="0.25" min="0" class="form-control form-control-sm qp-marks-input" style="width:70px" value="' + (q.marks || 0.5) + '">' +
            '<button type="button" class="btn btn-sm btn-outline-danger qp-remove-question"><i class="fa-solid fa-trash"></i></button>' +
            '</div>' +
            '</div>'
        );
        const $toolbarMount = $('<div>');
        $body.append($toolbarMount);
        $body.append('<textarea id="' + textareaId + '" class="form-control form-control-sm rounded-top-0" rows="2">' + $('<div>').text(q.question_text).html() + '</textarea>');
        const $preview = $('<div class="border rounded-bottom p-1 small fst-italic bg-light">');
        $body.append($preview);

        const $qImageRow = imageCaptureRowHtml('question_image', q.question_image, 32);
        $body.append($('<div class="mt-2">').append($qImageRow));

        const $optionsWrap = $('<div class="row g-2 mt-2">');
        const optionRows = [];
        ['a', 'b', 'c', 'd'].forEach(function (opt) {
            const optId = 'qp_opt_' + opt + '_' + idx;
            const $col = $('<div class="col-md-6 border rounded p-2">');
            $col.append(
                '<div class="form-check mb-1"><input class="form-check-input qp-correct-radio" type="radio" name="qp_correct_' + idx + '" value="' + opt.toUpperCase() + '" ' + (q.correct_option === opt.toUpperCase() ? 'checked' : '') + '><label class="form-check-label fw-bold small">Option ' + opt.toUpperCase() + '</label></div>'
            );
            const $optToolbarMount = $('<div>');
            $col.append($optToolbarMount);
            $col.append('<input type="text" id="' + optId + '" class="form-control form-control-sm rounded-top-0" value="' + $('<div>').text(q['option_' + opt]).html() + '">');
            const $optPreview = $('<div class="border rounded-bottom p-1 small fst-italic bg-light" style="min-height:20px">');
            $col.append($optPreview);
            const $optImageRow = imageCaptureRowHtml('option_' + opt + '_image', q['option_' + opt + '_image'], 28);
            $col.append($('<div class="mt-1">').append($optImageRow));
            $optionsWrap.append($col);

            optionRows.push({ id: optId, $toolbarMount: $optToolbarMount, $preview: $optPreview, initial: q['option_' + opt], $imageRow: $optImageRow });
        });
        $body.append($optionsWrap);

        // Defer wiring until the card is in the DOM.
        $card.data('wire', function () {
            wireTextEditor(textareaId, $toolbarMount, $preview, q.question_text);
            wireImageRow($qImageRow);
            optionRows.forEach(function (o) {
                wireTextEditor(o.id, o.$toolbarMount, o.$preview, o.initial);
                wireImageRow(o.$imageRow);
            });
        });

        return $card;
    }

    function addQuestionCard(q) {
        const idx = qIndex++;
        const $card = questionCardHtml(q, idx);
        $('#qp_questions').append($card);
        $card.data('wire')();
    }

    $(document).on('click', '.qp-remove-question', function () {
        $(this).closest('.qp-question-card').remove();
    });

    function openForm(paper) {
        $('#qp_questions').empty();
        qIndex = 0;
        $('#qp_qpid').val(paper ? paper.qpid : '');
        $('#qpFormTitle').text(paper ? 'Edit MCQ Paper' : 'New MCQ Paper');
        populateClassSelect();
        $('#qp_sclass').val(paper ? paper.sclass : '');
        populateSubjectSelect(paper ? paper.sclass : '').then(function () {
            $('#qp_subid').val(paper ? paper.subid : '');
        });
        $('#qp_title').val(paper ? paper.title : '');

        const questions = (paper && paper.questions && paper.questions.length) ? paper.questions : [null];
        questions.forEach(function (q) { addQuestionCard(q); });

        new bootstrap.Modal('#qpFormModal').show();
    }

    $('#qp_sclass').on('change', function () { populateSubjectSelect($(this).val()); });
    $('#qpBtnNew').on('click', function () { openForm(null); });
    $('#qpBtnAddQuestion').on('click', function () { addQuestionCard(null); });

    function loadPapers() {
        ajaxCall({ url: '/api/question-papers/list.php', method: 'GET', silent: true }).then(function (data) {
            isPrivileged = data.isPrivileged;
            $('#qpBtnNew').toggleClass('d-none', !data.canCreate);
            $('#qpAuthorHead').toggleClass('d-none', !isPrivileged);
            renderPapers(data.papers);
        });
    }

    function renderPapers(papers) {
        const tbody = $('#qpBody').empty();
        papers.forEach(function (p) {
            const row = $('<tr>');
            row.append($('<td>').text(p.title));
            row.append($('<td>').text(p.sclass));
            row.append($('<td>').text(p.subname));
            row.append($('<td>').html('<span class="badge text-bg-secondary">' + p.question_count + '</span>'));
            const $authorCell = $('<td>').addClass('small text-uppercase text-muted' + (isPrivileged ? '' : ' d-none')).text(p.tname);
            row.append($authorCell);
            const actions = $('<td>').addClass('d-flex gap-1 justify-content-end');
            const canEdit = isPrivileged || p.tid === myTid;
            if (canEdit) {
                actions.append('<button class="btn btn-sm btn-outline-secondary btn-qp-edit" data-id="' + p.qpid + '"><i class="fa-solid fa-pen"></i></button>');
                actions.append('<button class="btn btn-sm btn-outline-danger btn-qp-delete" data-id="' + p.qpid + '"><i class="fa-solid fa-trash"></i></button>');
            }
            actions.append('<button class="btn btn-sm btn-outline-primary btn-qp-download" data-id="' + p.qpid + '"><i class="fa-solid fa-file-word"></i></button>');
            if (isPrivileged) {
                actions.append('<button class="btn btn-sm btn-outline-success btn-qp-download-zip" data-id="' + p.qpid + '" title="Download Images (ZIP)"><i class="fa-solid fa-file-zipper"></i></button>');
                actions.append('<button class="btn btn-sm btn-outline-dark btn-qp-download-csv" data-id="' + p.qpid + '" title="Download Answers (CSV)"><i class="fa-solid fa-file-csv"></i></button>');
            }
            row.append(actions);
            $('#qpBody').append(row);
        });
    }

    $('#qpBody').on('click', '.btn-qp-edit', function () {
        ajaxCall({ url: '/api/question-papers/get.php', method: 'GET', data: { qpid: $(this).data('id') }, silent: true }).then(openForm);
    });
    $('#qpBody').on('click', '.btn-qp-delete', function () {
        const id = $(this).data('id');
        confirmDelete('This MCQ paper and all its questions will be permanently deleted.').then(function (ok) {
            if (!ok) return;
            ajaxCall({ url: '/api/question-papers/delete.php', data: { qpid: id }, successMessage: 'Paper deleted.' }).then(loadPapers);
        });
    });
    $('#qpBody').on('click', '.btn-qp-download', function () {
        window.location.href = BASE_URL + '/api/question-papers/docx.php?qpid=' + $(this).data('id');
    });
    $('#qpBody').on('click', '.btn-qp-download-zip', function () {
        window.location.href = BASE_URL + '/api/question-papers/zip.php?qpid=' + $(this).data('id');
    });
    $('#qpBody').on('click', '.btn-qp-download-csv', function () {
        window.location.href = BASE_URL + '/api/question-papers/answers_csv.php?qpid=' + $(this).data('id');
    });

    $('#qpBtnSave').on('click', function () {
        const questions = [];
        $('.qp-question-card').each(function () {
            const idx = $(this).data('idx');
            const q = { correct_option: $(this).find('.qp-correct-radio:checked').val() || 'A' };
            q.question_text = $('#qp_q_text_' + idx).val();
            q.marks = parseFloat($(this).find('.qp-marks-input').val()) || 0.5;
            ['a', 'b', 'c', 'd'].forEach(function (opt) { q['option_' + opt] = $('#qp_opt_' + opt + '_' + idx).val(); });
            $(this).find('.qp-image-row').each(function () {
                const field = $(this).data('field');
                q[field] = $(this).find('.qp-hidden-image').val();
            });
            questions.push(q);
        });
        if (!$('#qp_sclass').val() || !$('#qp_subid').val() || !$('#qp_title').val().trim() || questions.length === 0) {
            toastError('Class, subject, title and at least one question are required.');
            return;
        }
        ajaxCall({
            url: '/api/question-papers/upsert.php',
            data: {
                qpid: $('#qp_qpid').val(),
                sclass: $('#qp_sclass').val(),
                subid: $('#qp_subid').val(),
                title: $('#qp_title').val().trim(),
                questions: JSON.stringify(questions),
            },
            successMessage: 'Paper saved.',
        }).then(function () {
            bootstrap.Modal.getInstance(document.getElementById('qpFormModal')).hide();
            loadPapers();
        });
    });

    loadPapers();
})();
