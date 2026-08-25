<?php
// Public data-collection kiosk — no login required. Linked from the admin
// Data Collection panel's "copy link" button. Ports /collect/[formId]/page.tsx.

require_once __DIR__ . '/config.php';

$formId = isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Collection Portal - Dr. Virendra Swarup Education Centre, Avadhpuri</title>
<?php include __DIR__ . '/partials/_assets_head.php'; ?>
</head>
<body>

<div id="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>

<div class="d-flex align-items-center justify-content-center" style="min-height:100vh;">
  <div class="card shadow-lg" style="max-width:560px; width:100%;">
    <div class="card-body p-4">

      <div id="dcp_loading" class="text-center py-4"><div class="spinner-border text-primary"></div></div>
      <div id="dcp_error" class="alert alert-danger d-none"></div>

      <div id="dcp_content" class="d-none">
        <div class="text-center mb-4">
          <i class="fa-solid fa-clipboard-list fa-2x text-primary mb-2"></i>
          <h4 class="fw-bold mb-1" id="dcp_title"></h4>
          <p class="text-muted small" id="dcp_description"></p>
        </div>

        <div id="dcp_verifyStep">
          <div id="dcp_verifyError" class="alert alert-danger d-none small"></div>
          <div class="mb-3">
            <label class="form-label small fw-bold text-uppercase">Scholar Number</label>
            <input type="number" class="form-control" id="dcp_schno">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold text-uppercase">Date of Birth</label>
            <input type="text" class="form-control" id="dcp_dob" placeholder="DD-MM-YYYY">
            <div class="form-text">Example: 25-12-2010</div>
          </div>
          <button class="btn btn-primary w-100" id="btnVerifyStudent"><i class="fa-solid fa-shield-halved me-2"></i>Verify &amp; Open Form</button>
        </div>

        <div id="dcp_alreadySubmitted" class="d-none text-center py-4">
          <i class="fa-solid fa-circle-check fa-3x text-success mb-3"></i>
          <h5 class="fw-bold">Already Submitted</h5>
          <p class="text-muted small">Our records show you have already submitted a response for this form. Only one submission per student is allowed.</p>
        </div>

        <div id="dcp_submitStep" class="d-none">
          <div class="text-center mb-3">
            <span class="badge text-bg-success mb-2"><i class="fa-solid fa-shield-check me-1"></i>Verified: <span id="dcp_studentName"></span></span>
          </div>
          <div id="dcp_fields"></div>
          <button class="btn btn-primary w-100 mt-2" id="btnSubmitForm"><i class="fa-solid fa-floppy-disk me-2"></i>Submit Information</button>
        </div>

        <div id="dcp_success" class="d-none text-center py-4">
          <i class="fa-solid fa-circle-check fa-3x text-success mb-3"></i>
          <h5 class="fw-bold text-success">Thank You!</h5>
          <p class="text-muted small">Your response has been recorded.</p>
        </div>
      </div>

    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/_assets_scripts.php'; ?>
<script>
const FORM_ID = <?= json_encode($formId) ?>;
let formConfig = null;
let verifiedStudent = null;

function escapeHtml(s) { return $('<div>').text(s == null ? '' : s).html(); }

function loadForm() {
    ajaxCall({ url: '/api/data-collection/get_form.php', method: 'GET', data: { id: FORM_ID }, silent: true, quiet: true })
        .then(function (form) {
            formConfig = form;
            $('#dcp_title').text(form.title);
            $('#dcp_description').text(form.description || '');
            $('#dcp_loading').addClass('d-none');
            $('#dcp_content').removeClass('d-none');
        }, function (xhr) {
            $('#dcp_loading').addClass('d-none');
            $('#dcp_error').removeClass('d-none').text((xhr.responseJSON && xhr.responseJSON.error) || 'This form is unavailable.');
        });
}

function verifyStudent() {
    $('#dcp_verifyError').addClass('d-none');
    const schno = $('#dcp_schno').val();
    const dob = $('#dcp_dob').val().trim();
    if (!schno || !dob) {
        $('#dcp_verifyError').removeClass('d-none').text('Please fill in both fields.');
        return;
    }
    ajaxCall({ url: '/api/data-collection/verify_student.php', data: { schno: schno, dob: dob }, silent: true, quiet: true })
        .then(function (student) {
            verifiedStudent = student;
            verifiedStudent.dob = dob;
            ajaxCall({ url: '/api/data-collection/check_response.php', method: 'GET', data: { form_id: FORM_ID, sid: student.sid }, silent: true, quiet: true })
                .then(function (exists) {
                    if (exists) {
                        $('#dcp_verifyStep').addClass('d-none');
                        $('#dcp_alreadySubmitted').removeClass('d-none');
                    } else {
                        showSubmitStep();
                    }
                });
        }, function (xhr) {
            $('#dcp_verifyError').removeClass('d-none').text((xhr.responseJSON && xhr.responseJSON.error) || 'Verification failed.');
        });
}

function showSubmitStep() {
    $('#dcp_verifyStep').addClass('d-none');
    $('#dcp_studentName').text(verifiedStudent.sname);
    const container = $('#dcp_fields').empty();

    formConfig.fields.forEach(function (f) {
        const options = f.options ? f.options.split(',').map(function (o) { return o.trim(); }).filter(Boolean) : [];
        const wrap = $('<div class="mb-3">');
        wrap.append('<label class="form-label small fw-bold text-uppercase">' + escapeHtml(f.label) + (f.required ? ' <span class="text-danger">*</span>' : '') + '</label>');

        if (f.type === 'Select') {
            const sel = $('<select class="form-select dcp-field" data-label="' + escapeHtml(f.label) + '" data-type="select">');
            sel.append('<option value="">-- Select --</option>');
            options.forEach(function (o) { sel.append($('<option>').val(o).text(o)); });
            wrap.append(sel);
        } else if (f.type === 'Radio') {
            const group = $('<div class="dcp-field" data-label="' + escapeHtml(f.label) + '" data-type="radio">');
            options.forEach(function (o, i) {
                group.append('<div class="form-check"><input class="form-check-input" type="radio" name="radio_' + f.label.replace(/\W/g, '_') + '" value="' + escapeHtml(o) + '" id="r_' + f.label.replace(/\W/g, '_') + '_' + i + '"><label class="form-check-label" for="r_' + f.label.replace(/\W/g, '_') + '_' + i + '">' + escapeHtml(o) + '</label></div>');
            });
            wrap.append(group);
        } else if (f.type === 'Checkbox') {
            const group = $('<div class="dcp-field" data-label="' + escapeHtml(f.label) + '" data-type="checkbox">');
            options.forEach(function (o, i) {
                group.append('<div class="form-check"><input class="form-check-input" type="checkbox" value="' + escapeHtml(o) + '" id="c_' + f.label.replace(/\W/g, '_') + '_' + i + '"><label class="form-check-label" for="c_' + f.label.replace(/\W/g, '_') + '_' + i + '">' + escapeHtml(o) + '</label></div>');
            });
            wrap.append(group);
        } else {
            const inputType = f.type === 'Number' ? 'number' : (f.type === 'Date' ? 'date' : 'text');
            wrap.append('<input type="' + inputType + '" class="form-control dcp-field" data-label="' + escapeHtml(f.label) + '" data-type="text">');
        }
        container.append(wrap);
    });

    $('#dcp_submitStep').removeClass('d-none');
}

function collectResponses() {
    const responses = {};
    $('.dcp-field').each(function () {
        const label = $(this).data('label');
        const type = $(this).data('type');
        if (type === 'radio') {
            responses[label] = $(this).find('input:checked').val() || '';
        } else if (type === 'checkbox') {
            const vals = [];
            $(this).find('input:checked').each(function () { vals.push($(this).val()); });
            responses[label] = vals.join(', ');
        } else {
            responses[label] = $(this).val();
        }
    });
    return responses;
}

function submitForm() {
    const responses = collectResponses();
    for (const f of formConfig.fields) {
        if (f.required && !responses[f.label]) {
            toastError(f.label + ' is mandatory.');
            return;
        }
    }
    ajaxCall({
        url: '/api/data-collection/submit_response.php',
        data: { form_id: FORM_ID, schno: verifiedStudent.schno, dob: verifiedStudent.dob, responses: JSON.stringify(responses) },
        quiet: true,
    }).then(function () {
        $('#dcp_submitStep').addClass('d-none');
        $('#dcp_success').removeClass('d-none');
    });
}

$('#btnVerifyStudent').on('click', verifyStudent);
$('#btnSubmitForm').on('click', submitForm);

loadForm();
</script>
</body>
</html>
