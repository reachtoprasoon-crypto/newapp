// Shared authoring helpers for Question Papers & Subjective Papers: the
// bold/italic/underline/sup/sub + math-symbol formatting toolbar, a live
// KaTeX preview of the $...$/$$...$$ + **/*/__/^^/,, shorthand, and webcam
// capture — all copy-pasted 3x in the source app, built once here.
// Server-side rendering for DOCX export is the PHP twin in lib/paper_shorthand.php.

const PAPER_MATH_SYMBOLS = [
    { s: '+', l: '+' }, { s: '-', l: '-' }, { s: '\\times', l: '\\times' }, { s: '\\div', l: '\\div' }, { s: '\\pm', l: '\\pm' },
    { s: '=', l: '=' }, { s: '\\neq', l: '\\neq' }, { s: '\\approx', l: '\\approx' }, { s: '\\leq', l: '\\leq' }, { s: '\\geq', l: '\\geq' },
    { s: '\\frac{x}{y}', l: '\\frac{x}{y}' }, { s: '\\sqrt{x}', l: '\\sqrt{x}' }, { s: 'x^{n}', l: 'x^{n}' },
    { s: '\\int', l: '\\int' }, { s: '\\sum', l: '\\sum' }, { s: '\\pi', l: '\\pi' }, { s: '\\theta', l: '\\theta' }, { s: '^{\\circ}', l: '^{\\circ}' },
    { s: '(x)', l: '(x)' }, { s: '[x]', l: '[x]' }, { s: '\\{x\\}', l: '\\{x\\}' }, { s: '|x|', l: '|x|' },
    { s: '\\bar{x}', l: '\\bar{x}' }, { s: '\\vec{x}', l: '\\vec{x}' },
];

// Wraps the current textarea selection with prefix/suffix, jQuery port of
// the source's insertSnippet(): a `\`-prefixed math snippet auto-wraps in
// $...$ unless the cursor is already inside an odd number of $.
function paperInsertSnippet(textareaId, prefix, suffix) {
    suffix = suffix || '';
    const textarea = document.getElementById(textareaId);
    if (!textarea) return;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value || '';
    const selection = text.substring(start, end);
    const textBefore = text.substring(0, start);
    let finalSnippet = prefix + selection + suffix;
    if (prefix.charAt(0) === '\\') {
        const isInsideMath = ((textBefore.match(/\$/g) || []).length % 2) !== 0;
        if (!isInsideMath) finalSnippet = '$' + finalSnippet + '$';
    }
    const newText = textBefore + finalSnippet + text.substring(end);
    $(textarea).val(newText).trigger('input');
    setTimeout(function () {
        textarea.focus();
        const pos = start + finalSnippet.length;
        textarea.setSelectionRange(pos, pos);
    }, 0);
}

// Builds the toolbar HTML and wires its buttons to a given textarea id.
// $mount: a jQuery container to append the toolbar into, right above the textarea.
function paperAttachToolbar($mount, textareaId) {
    const $bar = $('<div class="paper-toolbar border rounded-top bg-light p-1">');
    const $formatRow = $('<div class="d-flex flex-wrap gap-1 mb-1">');
    const buttons = [
        { icon: 'fa-bold', title: 'Bold', prefix: '**', suffix: '**' },
        { icon: 'fa-italic', title: 'Italic', prefix: '*', suffix: '*' },
        { icon: 'fa-underline', title: 'Underline', prefix: '__', suffix: '__' },
        { icon: 'fa-superscript', title: 'Superscript', prefix: '^^', suffix: '^^' },
        { icon: 'fa-subscript', title: 'Subscript', prefix: ',,', suffix: ',,' },
        { icon: 'fa-list-ul', title: 'Bullet List', prefix: '\n- ', suffix: '' },
        { icon: 'fa-list-ol', title: 'Numbered List', prefix: '\n1. ', suffix: '' },
    ];
    buttons.forEach(function (b) {
        const $btn = $('<button type="button" class="btn btn-sm btn-outline-secondary" title="' + b.title + '"><i class="fa-solid ' + b.icon + '"></i></button>');
        $btn.on('click', function () { paperInsertSnippet(textareaId, b.prefix, b.suffix); });
        $formatRow.append($btn);
    });
    $bar.append($formatRow);

    const $mathRow = $('<div class="d-flex flex-wrap gap-1">');
    PAPER_MATH_SYMBOLS.forEach(function (item) {
        const $btn = $('<button type="button" class="btn btn-sm btn-outline-primary paper-math-symbol-btn" data-latex="' + $('<div>').text(item.s).html() + '"></button>');
        $btn.on('click', function () { paperInsertSnippet(textareaId, item.s, ''); });
        $mathRow.append($btn);
    });
    $bar.append($mathRow);
    $mount.append($bar);

    $mathRow.find('.paper-math-symbol-btn').each(function () {
        try {
            katex.render($(this).data('latex'), this, { throwOnError: false });
        } catch (e) {
            $(this).text($(this).data('latex'));
        }
    });
}

// Renders shorthand text (math + bold/italic/underline/sup/sub + lists) to
// safe HTML for a live preview pane, mirroring the source's MathRenderer.
function paperRenderPreview(text, $target) {
    $target.empty();
    if (!text) return;
    const lines = String(text).split(/\r?\n/);
    lines.forEach(function (line) {
        let prefix = '';
        let processedLine = line;
        if (line.startsWith('- ')) {
            prefix = '• ';
            processedLine = line.substring(2);
        } else {
            const numMatch = line.match(/^(\d+)\.\s/);
            if (numMatch) {
                prefix = numMatch[0];
                processedLine = line.substring(numMatch[0].length);
            }
        }

        const $line = $('<div class="d-flex align-items-start gap-1">');
        if (prefix) $line.append($('<span class="fw-bold text-primary">').text(prefix));
        const $body = $('<span>');

        const mathParts = processedLine.split(/(\$\$[\s\S]*?\$\$|\$[\s\S]*?\$)/g);
        mathParts.forEach(function (part) {
            if (!part) return;
            if (part.startsWith('$$') && part.endsWith('$$')) {
                const $span = $('<span>');
                try { katex.render(part.slice(2, -2).trim(), $span[0], { throwOnError: false, displayMode: true }); } catch (e) { $span.text(part); }
                $body.append($span);
                return;
            }
            if (part.startsWith('$') && part.endsWith('$')) {
                const $span = $('<span>');
                try { katex.render(part.slice(1, -1).trim(), $span[0], { throwOnError: false }); } catch (e) { $span.text(part); }
                $body.append($span);
                return;
            }
            const subParts = part.split(/(\*\*.*?\*\*|\*.*?\*|__.*?__|\^\^.*?\^\^|,,.*?,,)/g);
            subParts.forEach(function (sub) {
                if (!sub) return;
                if (sub.startsWith('**') && sub.endsWith('**')) $body.append($('<strong>').text(sub.slice(2, -2)));
                else if (sub.startsWith('__') && sub.endsWith('__')) $body.append($('<u>').text(sub.slice(2, -2)));
                else if (sub.startsWith('^^') && sub.endsWith('^^')) $body.append($('<sup>').text(sub.slice(2, -2)));
                else if (sub.startsWith(',,') && sub.endsWith(',,')) $body.append($('<sub>').text(sub.slice(2, -2)));
                else if (sub.startsWith('*') && sub.endsWith('*')) $body.append($('<em>').text(sub.slice(1, -1)));
                else $body.append(document.createTextNode(sub));
            });
        });
        $line.append($body);
        $target.append($line);
    });
}

// Opens a Bootstrap modal wrapping getUserMedia, calling back with a base64
// PNG data URL on capture (matches the students.js single-image-field
// convention — no upload plumbing needed beyond the hidden input it fills).
function paperOpenWebcamCapture(onCapture) {
    const modalHtml =
        '<div class="modal fade" id="paperWebcamModal" tabindex="-1">' +
        '<div class="modal-dialog"><div class="modal-content">' +
        '<div class="modal-header"><h5 class="modal-title">Take Photo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>' +
        '<div class="modal-body"><div class="ratio ratio-16x9 bg-dark"><video id="paperWebcamVideo" autoplay muted></video></div><canvas id="paperWebcamCanvas" class="d-none"></canvas></div>' +
        '<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="paperWebcamCaptureBtn">Capture</button></div>' +
        '</div></div></div>';
    $('#paperWebcamModal').remove();
    $('body').append(modalHtml);

    const video = document.getElementById('paperWebcamVideo');
    const canvas = document.getElementById('paperWebcamCanvas');
    let stream = null;

    const modalEl = document.getElementById('paperWebcamModal');
    const modal = new bootstrap.Modal(modalEl);

    navigator.mediaDevices.getUserMedia({ video: true }).then(function (s) {
        stream = s;
        video.srcObject = s;
    }).catch(function () {
        toastError('Could not access camera.');
        modal.hide();
    });

    $('#paperWebcamCaptureBtn').on('click', function () {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0, video.videoWidth, video.videoHeight);
        const dataUrl = canvas.toDataURL('image/png');
        modal.hide();
        onCapture(dataUrl);
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
        $(modalEl).remove();
    });

    modal.show();
}

// Wires a "Camera"/"Upload" button pair + hidden field + preview thumbnail
// for one image-capable field. $scope limits lookups to one row/card.
function paperAttachImageCapture($scope, opts) {
    const $hidden = $scope.find(opts.hiddenSelector);
    const $preview = $scope.find(opts.previewSelector);

    function setImage(dataUrl) {
        $hidden.val(dataUrl || '');
        if (dataUrl) {
            $preview.attr('src', dataUrl).removeClass('d-none');
        } else {
            $preview.addClass('d-none').attr('src', '');
        }
    }

    $scope.find(opts.cameraBtnSelector).on('click', function () {
        paperOpenWebcamCapture(setImage);
    });
    $scope.find(opts.fileInputSelector).on('change', function (e) {
        const file = e.target.files && e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onloadend = function () { setImage(reader.result); };
        reader.readAsDataURL(file);
    });
    $scope.find(opts.clearBtnSelector).on('click', function () { setImage(''); });

    if ($hidden.val()) setImage($hidden.val());
    return { setImage: setImage };
}
