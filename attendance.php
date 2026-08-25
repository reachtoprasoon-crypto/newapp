<?php
// Public daily-attendance kiosk — no login required. Linked from a QR code
// per class (generated in the admin Daily Self-Attendance panel).

require_once __DIR__ . '/config.php';

$sclass = trim($_GET['sclass'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Attendance Portal - Dr. Virendra Swarup Education Centre, Avadhpuri</title>
<?php include __DIR__ . '/partials/_assets_head.php'; ?>
</head>
<body>

<div id="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>

<div class="d-flex align-items-center justify-content-center" style="min-height:100vh;">
  <div class="card shadow-lg" style="max-width:420px; width:100%;">
    <div class="card-body p-4 text-center">
      <i class="fa-solid fa-user-check fa-2x text-primary mb-2"></i>
      <h4 class="fw-bold mb-1">Attendance Portal</h4>
      <p class="text-muted small mb-4">Verifying Presence for <strong>Class <?= htmlspecialchars($sclass) ?></strong></p>

      <div id="portalForm">
        <div id="portalError" class="alert alert-danger d-none text-start small"></div>
        <div class="mb-3 text-start">
          <label class="form-label small fw-bold text-uppercase">Scholar Number</label>
          <input type="number" class="form-control form-control-lg" id="pf_schno" placeholder="Enter your scholar number">
        </div>
        <div class="mb-2 text-start">
          <label class="form-label small fw-bold text-uppercase">Date of Birth</label>
          <input type="text" class="form-control form-control-lg" id="pf_dob" placeholder="DD-MM-YYYY">
          <div class="form-text">Format: 15-08-2010</div>
        </div>
        <button class="btn btn-primary btn-lg w-100 mt-3" id="btnVerify"><i class="fa-solid fa-shield-halved me-2"></i>Verify &amp; Mark Present</button>
      </div>

      <div id="portalSuccess" class="d-none py-4">
        <i class="fa-solid fa-circle-check fa-3x text-success mb-3"></i>
        <h5 class="fw-bold text-success">Attendance Recorded!</h5>
        <p class="text-muted small">You have been marked present for today.</p>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/_assets_scripts.php'; ?>
<script>
  const KIOSK_SCLASS = <?= json_encode($sclass) ?>;
  $('#btnVerify').on('click', function () {
    $('#portalError').addClass('d-none');
    const schno = $('#pf_schno').val();
    const dob = $('#pf_dob').val().trim();
    if (!schno || !dob) {
      $('#portalError').removeClass('d-none').text('Please fill in both fields.');
      return;
    }
    ajaxCall({ url: '/api/daily-attendance/self_mark.php', data: { sclass: KIOSK_SCLASS, schno: schno, dob: dob }, quiet: true })
      .then(function () {
        $('#portalForm').addClass('d-none');
        $('#portalSuccess').removeClass('d-none');
      }, function (xhr) {
        $('#portalError').removeClass('d-none').text((xhr.responseJSON && xhr.responseJSON.error) || 'Verification failed.');
      });
  });
</script>
</body>
</html>
