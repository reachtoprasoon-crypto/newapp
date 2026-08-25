<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';

if (is_logged_in()) {
    header('Location: ' . (is_student() ? '/firebase_to_php/student_dashboard.php' : '/firebase_to_php/dashboard.php'));
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - Dr. Virendra Swarup Education Centre, Avadhpuri</title>
<?php include __DIR__ . '/partials/_assets_head.php'; ?>
</head>
<body>

<div id="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>

<div class="login-card card shadow-sm">
  <div class="card-body p-4">
    <div class="text-center mb-3">
      <img src="/firebase_to_php/assets/images/logo.gif" onerror="this.style.display='none'" alt="School Logo" width="48" height="48" class="rounded-circle mb-2">
      <h5 class="fw-bold mb-1">Dr. Virendra Swarup Education Centre, Avadhpuri</h5>
      <div class="text-muted small">Enter your credentials to access the Portal</div>
    </div>

    <form id="loginForm">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" class="form-control" name="username" id="username" placeholder="your-username" required autofocus>
      </div>
      <div class="mb-2">
        <label class="form-label">Password</label>
        <input type="password" class="form-control" name="password" id="password" placeholder="********" required>
      </div>
      <div class="mb-3 text-end">
        <a href="#" id="forgotPasswordLink" class="small">Forgot Password?</a>
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
  </div>
</div>

<!-- Forgot Password modal: 3 steps — username, then DOB + new password, then success -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reset Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <div id="fpStep1">
          <p class="text-muted small">Enter your username to begin.</p>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" id="fpUsername">
          </div>
          <div id="fpStep1Error" class="text-danger small mb-2"></div>
          <button type="button" class="btn btn-primary w-100" id="fpStep1Next">Next</button>
        </div>

        <div id="fpStep2" style="display:none;">
          <p class="text-muted small">Confirm your Date of Birth and choose a new password.</p>
          <div class="mb-3">
            <label class="form-label">Date of Birth (dd/mm/yyyy)</label>
            <input type="text" class="form-control" id="fpDob" placeholder="dd/mm/yyyy">
          </div>
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" class="form-control" id="fpNewPassword">
          </div>
          <div id="fpStep2Error" class="text-danger small mb-2"></div>
          <button type="button" class="btn btn-primary w-100" id="fpStep2Submit">Reset Password</button>
        </div>

        <div id="fpStep3" style="display:none;">
          <div class="text-success text-center py-3">
            <i class="fa-solid fa-circle-check fa-2x mb-2"></i>
            <p>Password reset successfully. You can now log in.</p>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/_assets_scripts.php'; ?>
<script src="/firebase_to_php/assets/js/login.js"></script>
</body>
</html>
