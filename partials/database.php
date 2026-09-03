<div id="dbPane">
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-1">Database Configuration</h5>
      <p class="text-muted small mb-0">Set the connection details this app uses to reach its MySQL database.</p>
    </div>
    <div class="card-body">
      <div class="alert alert-warning small">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
Every page of this app depends on these settings. Saving only ever writes to a plain <code>.env</code> connection-values file (never any application code) — it tests the new connection first and backs up <code>.env</code> before writing — but a wrong host/user/database will still take the whole app offline until it's fixed. Have someone with SFTP access on standby.
      </div>
      <form id="dbConfigForm" class="row g-3" style="max-width: 640px;">
        <div class="col-md-6">
          <label class="form-label">Host</label>
          <input type="text" class="form-control" id="db_host" placeholder="e.g. localhost" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Database Name</label>
          <input type="text" class="form-control" id="db_database" placeholder="e.g. my_database" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">User</label>
          <input type="text" class="form-control" id="db_user" placeholder="e.g. root" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Password</label>
          <input type="password" class="form-control" id="db_password" placeholder="Leave blank to keep current password">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Save Configuration</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-danger-subtle">
    <div class="card-header">
      <h5 class="mb-1"><i class="fa-solid fa-triangle-exclamation text-danger me-1"></i>Backup</h5>
      <p class="text-muted small mb-0">Download a complete SQL dump of the current database structure and data.</p>
    </div>
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <h6 class="mb-1">Full SQL Backup</h6>
        <p class="text-muted small mb-0">Includes every table, all data. May take a while and produce a large file for a database this size.</p>
      </div>
      <a href="/newapp/api/database/backup.php" id="btnDownloadBackup" class="btn btn-outline-primary"><i class="fa-solid fa-download me-1"></i>Download SQL Backup</a>
    </div>
  </div>
</div>

<script src="/newapp/assets/js/database.js"></script>
