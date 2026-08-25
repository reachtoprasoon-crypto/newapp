<div id="marksPane">
  <h5 class="mb-3">Marks &amp; Grade Feeding</h5>

  <div class="alert alert-warning d-none" id="marksNoActiveTerm">
    No active term/report is currently set by the administrator. Marks and grades cannot be entered right now.
  </div>

  <div id="marksFormArea">
    <div class="row g-2 mb-3">
      <div class="col-12 col-md-4">
        <label class="form-label small">Class</label>
        <select class="form-select form-select-sm" id="mk_class"><option value="">Select Class</option></select>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label small">Subject</label>
        <select class="form-select form-select-sm" id="mk_subject" disabled><option value="">Select Subject</option></select>
      </div>
      <div class="col-12 col-md-4" id="mk_scheduleWrap" style="display:none;">
        <label class="form-label small">Assessment</label>
        <select class="form-select form-select-sm" id="mk_schedule"><option value="">Select Assessment</option></select>
      </div>
    </div>

    <div class="alert alert-secondary d-none" id="mk_notAllowed">Marks/grade feeding is currently disabled for this class by the administrator.</div>

    <div class="table-responsive d-none" id="mk_tableWrap">
      <table class="table table-sm table-hover align-middle">
        <thead><tr><th>Roll</th><th>Name</th><th id="mk_valueHeader">Marks</th></tr></thead>
        <tbody id="mk_tableBody"></tbody>
      </table>
      <button class="btn btn-primary btn-sm" id="btnSaveMarks">Save</button>
    </div>
  </div>
</div>

<script src="/firebase_to_php/assets/js/marks.js"></script>
