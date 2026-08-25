<div id="studentsPane">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
    <h5 class="mb-0">Student Management</h5>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm d-none" id="btnBulkPhoto"><i class="fa-solid fa-images me-1"></i>Bulk Photo Upload</button>
      <button class="btn btn-primary btn-sm d-none" id="btnAddStudent"><i class="fa-solid fa-user-plus me-1"></i>Add New Student</button>
    </div>
  </div>

  <div class="row g-2 mb-3 align-items-center">
    <div class="col-12 col-md-4" id="classSelectWrap">
      <select class="form-select form-select-sm" id="studentClassSelect">
        <option value="">Select a class to view students</option>
      </select>
    </div>
    <div class="col-12 col-md-5">
      <div class="input-group input-group-sm">
        <input type="text" class="form-control" id="studentSearchInput" placeholder="Search by name, parent, or scholar no...">
        <button class="btn btn-outline-secondary" id="btnStudentSearch"><i class="fa-solid fa-magnifying-glass"></i></button>
      </div>
    </div>
    <div class="col-12 col-md-3 text-md-end">
      <button class="btn btn-outline-secondary btn-sm d-none" id="btnEditRollNumbers"><i class="fa-solid fa-list-ol me-1"></i>Edit Roll Numbers</button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle" id="studentsTable">
      <thead>
        <tr>
          <th>Photo</th>
          <th>Roll</th>
          <th>Student Name</th>
          <th>Parent's Name</th>
          <th>Phone</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
  <p class="text-muted text-center d-none" id="studentsEmptyMessage">Select a class or search for a student.</p>
</div>

<!-- Add/Edit Student Modal -->
<div class="modal fade" id="studentFormModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="studentFormTitle">Add New Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="studentForm">
          <input type="hidden" id="sf_sid">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Student Name</label>
              <input type="text" class="form-control" id="sf_sname" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Roll No.</label>
              <input type="number" class="form-control" id="sf_roll" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Scholar No.</label>
              <input type="number" class="form-control" id="sf_schno" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Parent's Name</label>
              <input type="text" class="form-control" id="sf_pname">
            </div>
            <div class="col-md-6">
              <label class="form-label">Mother's Name</label>
              <input type="text" class="form-control" id="sf_mname">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" class="form-control" id="sf_phone">
            </div>
            <div class="col-md-6">
              <label class="form-label">Date of Birth (dd-mm-yyyy)</label>
              <input type="text" class="form-control" id="sf_dob" placeholder="dd-mm-yyyy" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Class</label>
              <select class="form-select" id="sf_sclass" required></select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Branch</label>
              <input type="text" class="form-control" id="sf_branch">
            </div>
            <div class="col-md-4">
              <label class="form-label">House</label>
              <select class="form-select" id="sf_hid"><option value="0">Select a house</option></select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Height (cm)</label>
              <input type="number" class="form-control" id="sf_ht" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Weight (kg)</label>
              <input type="number" class="form-control" id="sf_wt" required>
            </div>
            <div class="col-md-6">
              <label class="form-label d-block">Photo</label>
              <img id="sf_photo_preview" src="" class="rounded-circle mb-2 d-none" width="64" height="64" style="object-fit:cover;">
              <input type="file" class="form-control form-control-sm" id="sf_photo_file" accept="image/*">
              <input type="hidden" id="sf_photo">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btnSaveStudent">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<!-- Roll Number Editor Modal -->
<div class="modal fade" id="rollNumberModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Roll Numbers</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">Update the roll numbers for the students in this class.</p>
        <div style="max-height:60vh; overflow-y:auto;">
          <table class="table table-sm">
            <thead><tr><th>Sch No</th><th>Student Name</th><th>Roll No.</th></tr></thead>
            <tbody id="rollNumberRows"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btnSaveRollNumbers">Save Roll Numbers</button>
      </div>
    </div>
  </div>
</div>

<!-- Bulk Photo Upload Modal -->
<div class="modal fade" id="bulkPhotoModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Bulk Photo Upload</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">File names must be the Scholar Number (e.g. 1234.jpg). Select a class first.</p>
        <input type="file" class="form-control" id="bulkPhotoFiles" accept="image/*" multiple>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btnApplyBulkPhoto">Upload</button>
      </div>
    </div>
  </div>
</div>

<!-- Student Notes Modal -->
<div class="modal fade" id="studentNotesModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Notes: <span id="notesStudentName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="notesList" style="max-height:300px; overflow-y:auto;"></div>
        <hr>
        <label class="form-label small">Add a note</label>
        <textarea class="form-control form-control-sm mb-2" id="newNoteContent" rows="2"></textarea>
        <button class="btn btn-primary btn-sm" id="btnAddNote">Add Note</button>
      </div>
    </div>
  </div>
</div>

<script src="/firebase_to_php/assets/js/students.js"></script>
