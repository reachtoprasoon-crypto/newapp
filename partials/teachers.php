<div id="teachersPane">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
    <h5 class="mb-0">Teacher Management</h5>
    <button class="btn btn-primary btn-sm" id="btnAddTeacher"><i class="fa-solid fa-user-plus me-1"></i>Add New Teacher</button>
  </div>

  <div class="row g-2 mb-3">
    <div class="col-12 col-md-6">
      <div class="input-group input-group-sm">
        <input type="text" class="form-control" id="teacherSearchInput" placeholder="Search by name or username...">
        <button class="btn btn-outline-secondary" id="btnTeacherSearch"><i class="fa-solid fa-magnifying-glass"></i></button>
      </div>
    </div>
  </div>

  <div class="table-responsive mb-4">
    <table class="table table-sm table-hover align-middle">
      <thead>
        <tr>
          <th>Name</th>
          <th>Username</th>
          <th>Class Teacher Of</th>
          <th>Subjects Taught</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="teachersTableBody"></tbody>
    </table>
  </div>
  <p class="text-muted text-center d-none" id="teachersEmptyMessage">Search for a teacher by name or username.</p>

  <hr>
  <h6>Assign Class Teacher</h6>
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-4">
      <label class="form-label small">Class</label>
      <select class="form-select form-select-sm" id="actClass"></select>
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label small">Teacher</label>
      <select class="form-select form-select-sm" id="actTeacher"></select>
    </div>
    <div class="col-12 col-md-4">
      <button class="btn btn-outline-primary btn-sm" id="btnAssignClassTeacher">Assign</button>
    </div>
  </div>
</div>

<!-- Add/Edit Teacher Modal -->
<div class="modal fade" id="teacherFormModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="teacherFormTitle">Add New Teacher</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="teacherForm">
          <input type="hidden" id="tf_tid">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" id="tf_tname" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" id="tf_tuser" required>
          </div>
          <div class="mb-3">
            <label class="form-label" id="tf_tpass_label">Password</label>
            <input type="text" class="form-control" id="tf_tpass">
            <div class="form-text" id="tf_tpass_hint" style="display:none;">Leave blank to keep the current password.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" class="form-control" id="tf_phone">
          </div>
          <div class="mb-3">
            <label class="form-label">Date of Birth (dd/mm/yyyy)</label>
            <input type="text" class="form-control" id="tf_dob" placeholder="dd/mm/yyyy">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btnSaveTeacher">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<script src="/newapp/assets/js/teachers.js"></script>
