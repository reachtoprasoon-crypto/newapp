<div id="classRosterPane">
  <h5 class="mb-3">Class Roster</h5>

  <div class="row g-2 mb-3 align-items-end">
    <div class="col-6 col-md-3" id="cr_classWrap">
      <label class="form-label small">Class</label>
      <select class="form-select form-select-sm" id="cr_class"></select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small">Term</label>
      <select class="form-select form-select-sm" id="cr_term"></select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small">Report</label>
      <select class="form-select form-select-sm" id="cr_report">
        <option value="1">Report 1</option>
        <option value="2">Report 2</option>
      </select>
    </div>
    <div class="col-6 col-md-4 d-flex gap-2">
      <button class="btn btn-primary btn-sm" id="btnFetchRoster">Fetch Roster</button>
      <button class="btn btn-outline-secondary btn-sm d-none" id="btnExportRoster"><i class="fa-solid fa-file-excel me-1"></i>Export Excel</button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm" id="cr_table">
      <thead><tr id="cr_head"></tr></thead>
      <tbody id="cr_body"></tbody>
    </table>
  </div>
</div>

<script src="/firebase_to_php/assets/js/class-roster.js"></script>
