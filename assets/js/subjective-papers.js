(function () {
    let isPrivileged = false;
    let myTid = Number(window.APP_DATA.tid);
    let library = { parts: [], sections: [], instructions: [] };
    let uid = 0;

    function classesForSelect() {
        return isPrivileged ? window.APP_DATA.classes : window.APP_DATA.teacherClasses;
    }

    function populateClassSelect() {
        const $sel = $('#sp_sclass');
        $sel.find('option:not(:first)').remove();
        classesForSelect().forEach(function (c) { $sel.append($('<option>').val(c).text('Class ' + c)); });
    }

    function populateSubjectSelect(sclass) {
        const $sel = $('#sp_subid');
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

    function loadLibrary() {
        return ajaxCall({ url: '/api/subjective-papers/library.php', method: 'GET', silent: true }).then(function (lib) {
            library = lib;
            $('#sp_partList').empty();
            lib.parts.forEach(function (p) { $('#sp_partList').append($('<option>').val(p)); });
            $('#sp_sectionList').empty();
            lib.sections.forEach(function (s) { $('#sp_sectionList').append($('<option>').val(s)); });
            $('#sp_instructionList').empty();
            lib.instructions.forEach(function (i) { $('#sp_instructionList').append($('<option>').val(i)); });
        });
    }

    function moveCard($card, dir) {
        if (dir < 0) {
            const $prev = $card.prev('.sp-element-card');
            if ($prev.length) $card.insertBefore($prev);
        } else {
            const $next = $card.next('.sp-element-card');
            if ($next.length) $card.insertAfter($next);
        }
    }

    function partSectionCardHtml(type, el) {
        el = el || { text: type === 'Part' ? 'PART-I' : 'SECTION-A', instruction: '' };
        const listId = type === 'Part' ? 'sp_partList' : 'sp_sectionList';
        const $card = $('<div class="card mb-2 border-start border-4 border-secondary sp-element-card" data-type="' + type + '">');
        const $body = $('<div class="card-body p-2 d-flex gap-2 align-items-start">');
        $card.append($body);
        $body.append(
            '<div class="d-flex flex-column gap-1">' +
            '<button type="button" class="btn btn-sm btn-outline-secondary sp-move-up" title="Move up"><i class="fa-solid fa-chevron-up"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary sp-move-down" title="Move down"><i class="fa-solid fa-chevron-down"></i></button>' +
            '</div>'
        );
        const $fields = $('<div class="flex-grow-1 row g-2">');
        $fields.append(
            '<div class="col-md-5"><label class="small fw-bold text-primary">' + type + ' Label</label>' +
            '<input type="text" class="form-control form-control-sm sp-el-text" list="' + listId + '" value="' + $('<div>').text(el.text).html() + '"></div>'
        );
        $fields.append(
            '<div class="col-md-7"><label class="small fw-bold text-primary">Instructions</label>' +
            '<input type="text" class="form-control form-control-sm sp-el-instruction" list="sp_instructionList" value="' + $('<div>').text(el.instruction || '').html() + '"></div>'
        );
        $body.append($fields);
        $body.append('<button type="button" class="btn btn-sm btn-outline-danger sp-remove-el"><i class="fa-solid fa-trash"></i></button>');
        return $card;
    }

    function subpartRowHtml(sp, spIdx, letter) {
        sp = sp || { text: '', marks: 0 };
        const id = 'sp_subpart_' + (uid++);
        const $row = $('<div class="d-flex gap-2 align-items-start bg-light border rounded p-2 mb-1 sp-subpart-row">');
        $row.append('<span class="fw-bold small text-muted pt-1">(' + letter + ')</span>');
        const $col = $('<div class="flex-grow-1">');
        const $toolbarMount = $('<div>');
        $col.append($toolbarMount);
        $col.append('<textarea id="' + id + '" class="form-control form-control-sm rounded-top-0" rows="1">' + $('<div>').text(sp.text).html() + '</textarea>');
        const $preview = $('<div class="border rounded-bottom p-1 small fst-italic bg-white" style="min-height:18px">');
        $col.append($preview);
        $row.append($col);
        $row.append('<button type="button" class="btn btn-sm btn-outline-danger sp-remove-subpart"><i class="fa-solid fa-xmark"></i></button>');

        $row.data('wire', function () {
            paperAttachToolbar($toolbarMount, id);
            $('#' + id).on('input', function () { paperRenderPreview($(this).val(), $preview); });
            paperRenderPreview(sp.text, $preview);
        });
        $row.data('getMarks', function () { return parseFloat(sp.marks) || 0; });
        return $row;
    }

    function relabelSubparts($subpartsWrap) {
        $subpartsWrap.find('.sp-subpart-row').each(function (i) {
            $(this).find('> span').text('(' + String.fromCharCode(97 + i) + ')');
        });
    }

    function refreshSubpartMarks($card) {
        const marks = parseFloat($card.find('.sp-el-marks').val()) || 0;
        const $rows = $card.find('.sp-subpart-row');
        const per = $rows.length > 0 ? (marks / $rows.length) : 0;
        $card.find('.sp-subpart-marks-label').text('[' + per.toFixed(1) + ']');
    }

    function questionCardHtml(el, defaultQno) {
        const q = (el && el.question) || { qno: defaultQno, text: '', marks: 0, image: '', subparts: [] };
        const textId = 'sp_q_text_' + (uid++);

        const $card = $('<div class="card mb-2 border-start border-4 border-primary sp-element-card" data-type="Question">');
        const $body = $('<div class="card-body p-2 d-flex gap-2 align-items-start">');
        $card.append($body);
        $body.append(
            '<div class="d-flex flex-column gap-1">' +
            '<button type="button" class="btn btn-sm btn-outline-secondary sp-move-up" title="Move up"><i class="fa-solid fa-chevron-up"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary sp-move-down" title="Move down"><i class="fa-solid fa-chevron-down"></i></button>' +
            '</div>'
        );

        const $main = $('<div class="flex-grow-1">');
        $body.append($main);
        $body.append('<button type="button" class="btn btn-sm btn-outline-danger sp-remove-el"><i class="fa-solid fa-trash"></i></button>');

        const $top = $('<div class="d-flex gap-2">');
        $top.append('<div style="width:60px"><label class="small fw-bold text-primary">No</label><input type="number" class="form-control form-control-sm sp-el-qno" value="' + q.qno + '"></div>');
        const $textCol = $('<div class="flex-grow-1">');
        $textCol.append('<label class="small fw-bold text-primary">Question Content</label>');
        const $toolbarMount = $('<div>');
        $textCol.append($toolbarMount);
        $textCol.append('<textarea id="' + textId + '" class="form-control form-control-sm rounded-top-0 sp-el-text-main" rows="2">' + $('<div>').text(q.text).html() + '</textarea>');
        const $preview = $('<div class="border rounded-bottom p-1 small fst-italic bg-light">');
        $textCol.append($preview);
        $top.append($textCol);
        $top.append('<div style="width:70px"><label class="small fw-bold text-primary">Marks</label><input type="number" step="0.5" class="form-control form-control-sm sp-el-marks" value="' + (q.marks || 0) + '"></div>');
        $main.append($top);

        const imageRow = (function () {
            const $row = $(
                '<div class="d-flex align-items-center gap-2 mt-2 sp-image-row" data-field="image">' +
                '<button type="button" class="btn btn-sm btn-outline-secondary sp-camera-btn"><i class="fa-solid fa-camera me-1"></i>Camera</button>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary sp-upload-btn"><i class="fa-solid fa-upload me-1"></i>Upload</button>' +
                '<input type="file" accept="image/*" class="d-none sp-file-input">' +
                '<input type="hidden" class="sp-hidden-image" value="' + $('<div>').text(q.image || '').html() + '">' +
                '<img class="sp-image-preview rounded border ' + (q.image ? '' : 'd-none') + '" style="height:32px" src="' + (q.image || '') + '">' +
                '<button type="button" class="btn btn-sm btn-outline-danger sp-clear-image ' + (q.image ? '' : 'd-none') + '"><i class="fa-solid fa-xmark"></i></button>' +
                '</div>'
            );
            return $row;
        })();
        $main.append(imageRow);

        const $subpartsWrap = $('<div class="ps-4 mt-2 border-start sp-subparts-wrap">');
        $main.append($subpartsWrap);
        (q.subparts || []).forEach(function (sp, i) {
            const $row = subpartRowHtml(sp, i, String.fromCharCode(97 + i));
            $subpartsWrap.append($row);
        });

        const $addSubpartBtn = $('<button type="button" class="btn btn-outline-secondary btn-sm mt-1 sp-add-subpart"><i class="fa-solid fa-list me-1"></i>Add Sub-part</button>');
        $main.append($addSubpartBtn);

        $card.data('wire', function () {
            paperAttachToolbar($toolbarMount, textId);
            $('#' + textId).on('input', function () { paperRenderPreview($(this).val(), $preview); });
            paperRenderPreview(q.text, $preview);
            paperAttachImageCapture(imageRow, {
                hiddenSelector: '.sp-hidden-image',
                previewSelector: '.sp-image-preview',
                cameraBtnSelector: '.sp-camera-btn',
                fileInputSelector: '.sp-file-input',
                clearBtnSelector: '.sp-clear-image',
            });
            $subpartsWrap.find('.sp-subpart-row').each(function () { $(this).data('wire')(); });
            refreshSubpartMarks($card);
        });

        $card.on('input', '.sp-el-marks', function () { refreshSubpartMarks($card); });
        $addSubpartBtn.on('click', function () {
            const letter = String.fromCharCode(97 + $subpartsWrap.find('.sp-subpart-row').length);
            const $row = subpartRowHtml(null, $subpartsWrap.find('.sp-subpart-row').length, letter);
            $subpartsWrap.append($row);
            $row.data('wire')();
            refreshSubpartMarks($card);
        });
        $subpartsWrap.on('click', '.sp-remove-subpart', function () {
            $(this).closest('.sp-subpart-row').remove();
            relabelSubparts($subpartsWrap);
            refreshSubpartMarks($card);
        });

        return $card;
    }

    function nextQuestionNo() {
        return $('#sp_elements .sp-element-card[data-type="Question"]').length + 1;
    }

    $(document).on('click', '.sp-move-up', function () { moveCard($(this).closest('.sp-element-card'), -1); });
    $(document).on('click', '.sp-move-down', function () { moveCard($(this).closest('.sp-element-card'), 1); });
    $(document).on('click', '.sp-remove-el', function () { $(this).closest('.sp-element-card').remove(); });

    $('#spBtnAddPart').on('click', function () { const $c = partSectionCardHtml('Part'); $('#sp_elements').append($c); });
    $('#spBtnAddSection').on('click', function () { const $c = partSectionCardHtml('Section'); $('#sp_elements').append($c); });
    $('#spBtnAddQuestion').on('click', function () {
        const $c = questionCardHtml(null, nextQuestionNo());
        $('#sp_elements').append($c);
        $c.data('wire')();
    });

    $('#sp_sclass').on('change', function () { populateSubjectSelect($(this).val()); });

    function openEditor(paper) {
        $('#sp_elements').empty();
        $('#sp_spid').val(paper ? paper.spid : '');
        populateClassSelect();
        $('#sp_sclass').val(paper ? paper.sclass : '');
        populateSubjectSelect(paper ? paper.sclass : '').then(function () {
            $('#sp_subid').val(paper ? paper.subid : '');
        });
        $('#sp_title').val(paper ? paper.title : '');
        $('#sp_max_marks').val(paper ? paper.max_marks : 100);
        $('#sp_time_duration').val(paper ? paper.time_duration : '3 Hours');
        $('#sp_instruction').val(paper ? (paper.instruction || '') : 'Candidates are allowed additional 15 minutes for only reading the paper.');

        loadLibrary().then(function () {
            const elements = (paper && paper.elements && paper.elements.length) ? paper.elements : [];
            elements.forEach(function (el) {
                let $card;
                if (el.type === 'Question') {
                    $card = questionCardHtml(el, el.question.qno);
                } else {
                    $card = partSectionCardHtml(el.type, el);
                }
                $('#sp_elements').append($card);
                if ($card.data('wire')) $card.data('wire')();
            });
        });

        $('#spListView').addClass('d-none');
        $('#spEditorView').removeClass('d-none');
    }

    function closeEditor() {
        $('#spEditorView').addClass('d-none');
        $('#spListView').removeClass('d-none');
        loadPapers();
    }

    $('#spBtnNew').on('click', function () { openEditor(null); });
    $('#spBtnBack').on('click', closeEditor);
    $('#spBtnCancel').on('click', closeEditor);

    function loadPapers() {
        ajaxCall({ url: '/api/subjective-papers/list.php', method: 'GET', silent: true }).then(function (data) {
            isPrivileged = data.isPrivileged;
            $('#spBtnNew').toggleClass('d-none', isPrivileged);
            $('#spAuthorHead').toggleClass('d-none', !isPrivileged);
            renderPapers(data.papers);
        });
    }

    function renderPapers(papers) {
        const tbody = $('#spBody').empty();
        papers.forEach(function (p) {
            const row = $('<tr>');
            row.append($('<td>').addClass('small').text(p.created_at));
            row.append($('<td>').text(p.title));
            row.append($('<td>').text(p.sclass));
            row.append($('<td>').text(p.subname));
            row.append($('<td>').text(p.max_marks));
            row.append($('<td>').addClass('small text-uppercase text-muted' + (isPrivileged ? '' : ' d-none')).text(p.tname));
            const actions = $('<td>').addClass('d-flex gap-1 justify-content-end');
            if (p.tid === myTid) {
                actions.append('<button class="btn btn-sm btn-outline-secondary btn-sp-edit" data-id="' + p.spid + '"><i class="fa-solid fa-pen"></i></button>');
                actions.append('<button class="btn btn-sm btn-outline-danger btn-sp-delete" data-id="' + p.spid + '"><i class="fa-solid fa-trash"></i></button>');
            }
            actions.append('<button class="btn btn-sm btn-outline-primary btn-sp-download" data-id="' + p.spid + '"><i class="fa-solid fa-file-word"></i></button>');
            row.append(actions);
            $('#spBody').append(row);
        });
    }

    $('#spBody').on('click', '.btn-sp-edit', function () {
        ajaxCall({ url: '/api/subjective-papers/get.php', method: 'GET', data: { spid: $(this).data('id') }, silent: true }).then(openEditor);
    });
    $('#spBody').on('click', '.btn-sp-delete', function () {
        const id = $(this).data('id');
        confirmDelete('This paper will be permanently deleted.').then(function (ok) {
            if (!ok) return;
            ajaxCall({ url: '/api/subjective-papers/delete.php', data: { spid: id }, successMessage: 'Paper deleted.' }).then(loadPapers);
        });
    });
    $('#spBody').on('click', '.btn-sp-download', function () {
        window.location.href = BASE_URL + '/api/subjective-papers/docx.php?spid=' + $(this).data('id');
    });

    $('#spBtnSave').on('click', function () {
        const elements = [];
        $('#sp_elements .sp-element-card').each(function () {
            const type = $(this).data('type');
            if (type === 'Question') {
                const subparts = [];
                $(this).find('.sp-subpart-row').each(function () {
                    const $ta = $(this).find('textarea');
                    subparts.push({ text: $ta.val(), marks: 0 });
                });
                const marks = parseFloat($(this).find('.sp-el-marks').val()) || 0;
                if (subparts.length > 0) {
                    const per = marks / subparts.length;
                    subparts.forEach(function (sp) { sp.marks = per; });
                }
                elements.push({
                    type: 'Question',
                    question: {
                        qno: parseInt($(this).find('.sp-el-qno').val(), 10) || 0,
                        text: $(this).find('.sp-el-text-main').val(),
                        image: $(this).find('.sp-hidden-image').val() || '',
                        marks: marks,
                        subparts: subparts,
                    },
                });
            } else {
                elements.push({
                    type: type,
                    text: $(this).find('.sp-el-text').val(),
                    instruction: $(this).find('.sp-el-instruction').val(),
                });
            }
        });

        if (!$('#sp_sclass').val() || !$('#sp_subid').val() || !$('#sp_title').val().trim() || !$('#sp_time_duration').val().trim() || elements.length === 0) {
            toastError('Class, subject, title, time duration and at least one paper element are required.');
            return;
        }

        ajaxCall({
            url: '/api/subjective-papers/upsert.php',
            data: {
                spid: $('#sp_spid').val(),
                sclass: $('#sp_sclass').val(),
                subid: $('#sp_subid').val(),
                title: $('#sp_title').val().trim(),
                instruction: $('#sp_instruction').val(),
                max_marks: $('#sp_max_marks').val(),
                time_duration: $('#sp_time_duration').val().trim(),
                elements: JSON.stringify(elements),
            },
            successMessage: 'Paper saved.',
        }).then(closeEditor);
    });

    loadPapers();
})();
