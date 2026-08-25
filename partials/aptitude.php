<div id="aptitudePane">
  <h5 class="mb-3">Aptitude Management</h5>

  <div class="row g-2 mb-3 align-items-end">
    <div class="col-6 col-md-3">
      <label class="form-label small">Class</label>
      <select class="form-select form-select-sm" id="apt_class"><option value="">Select Class</option></select>
    </div>
    <div class="col-6 col-md-3">
      <button class="btn btn-outline-secondary btn-sm" id="btnLoadLogsheet"><i class="fa-solid fa-file-excel me-1"></i>Generate &amp; Export Logsheet</button>
    </div>
  </div>

  <div class="table-responsive d-none" id="apt_tableWrap">
    <table class="table table-sm table-hover align-middle">
      <thead><tr><th>Roll</th><th>Sch No</th><th>Name</th><th>Aptitude Marks</th></tr></thead>
      <tbody id="apt_tableBody"></tbody>
    </table>
    <button class="btn btn-primary btn-sm" id="btnSaveAptitude">Save Marks</button>
  </div>
</div>

<script src="/newapp/assets/js/aptitude.js"></script>
