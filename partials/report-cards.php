<div id="reportCardsPane">
  <h5 class="mb-3">Report Cards</h5>

  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
      <label class="form-label small">Class</label>
      <select class="form-select form-select-sm" id="rc_class"><option value="">Select Class</option></select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small">Term</label>
      <select class="form-select form-select-sm" id="rc_term" disabled><option value="">Select class first</option></select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small">Report</label>
      <select class="form-select form-select-sm" id="rc_report" disabled><option value="">Select term first</option></select>
    </div>
  </div>

  <div class="mb-3">
    <label class="form-label small fw-bold text-uppercase">Custom Term Label</label>
    <input type="text" class="form-control form-control-sm" id="rc_customLabel" placeholder="e.g. HALF YEARLY 2025-26">
  </div>

  <div id="rc_studentPickerWrap" class="d-none border rounded p-2 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <strong class="small" id="rc_studentCount">Select Students (0/0)</strong>
      <div class="d-flex gap-2">
        <input type="text" class="form-control form-control-sm" id="rc_studentSearch" placeholder="Search..." style="max-width:180px;">
        <button class="btn btn-outline-secondary btn-sm" id="btnToggleSelectAll">Select / Deselect All</button>
      </div>
    </div>
    <div class="row g-1" id="rc_studentPicker" style="max-height:260px; overflow-y:auto;"></div>
  </div>

  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" id="rc_includeSchool" checked><label class="form-check-label small" for="rc_includeSchool">Include School Name</label></div></div>
    <div class="col-6 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" id="rc_includeBranch" checked><label class="form-check-label small" for="rc_includeBranch">Include Branch</label></div></div>
    <div class="col-6 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" id="rc_includeWatermark" checked><label class="form-check-label small" for="rc_includeWatermark">Include Watermark</label></div></div>
    <div class="col-6 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" id="rc_includeSignatures" checked><label class="form-check-label small" for="rc_includeSignatures">Include Signatures</label></div></div>
  </div>

  <div class="d-flex gap-2 mb-4">
    <button class="btn btn-secondary btn-sm" id="btnExportExcel"><i class="fa-solid fa-file-excel me-1"></i>Export Excel (one sheet per student)</button>
    <button class="btn btn-primary btn-sm" id="btnPrintReports"><i class="fa-solid fa-print me-1"></i>Print Reports</button>
  </div>

  <hr>
  <h6>Class Logsheet (all students, one row each)</h6>
  <div class="d-flex gap-2 mb-2">
    <button class="btn btn-outline-primary btn-sm" id="btnViewClassReport"><i class="fa-solid fa-file-lines me-1"></i>View Class Logsheet</button>
  </div>

  <hr>
  <h6>Single Student Lookup</h6>
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-6">
      <label class="form-label small">Search Student</label>
      <div class="input-group input-group-sm">
        <input type="text" class="form-control" id="rc_lookupSearch" placeholder="Name, parent, or scholar no...">
        <button class="btn btn-outline-secondary" id="btnLookupSearch"><i class="fa-solid fa-magnifying-glass"></i></button>
      </div>
    </div>
  </div>
  <div class="table-responsive mt-2">
    <table class="table table-sm table-hover" id="rc_lookupResults"><tbody></tbody></table>
  </div>
</div>

<script src="/firebase_to_php/assets/js/report-cards.js"></script>
