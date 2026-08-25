<div id="tcPane">
  <h5 class="mb-3">Issue Transfer Certificate</h5>

  <div class="alert alert-warning small">
    <i class="fa-solid fa-triangle-exclamation me-1"></i>
    Issuing a TC permanently removes the student from active records (archived, not soft-deleted). This cannot be undone.
  </div>

  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
      <label class="form-label small">1. Select Class</label>
      <select class="form-select form-select-sm" id="tc_class"><option value="">Select Class</option></select>
    </div>
    <div class="col-6 col-md-4">
      <label class="form-label small">2. Select Student</label>
      <select class="form-select form-select-sm" id="tc_student" disabled><option value="">Select class first</option></select>
    </div>
  </div>

  <div id="tc_formWrap" class="d-none border rounded p-3 mb-4">
    <div class="row g-3">
      <div class="col-6 col-md-3">
        <label class="form-label small">TCR No.</label>
        <input type="text" class="form-control form-control-sm" id="tc_tcr_no">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">Sl. No.</label>
        <input type="number" class="form-control form-control-sm" id="tc_sl_no">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">Issue Date</label>
        <input type="text" class="form-control form-control-sm" id="tc_issue_date" placeholder="dd-mm-yyyy">
      </div>

      <div class="col-12 border-top pt-3"><h6 class="text-primary">Admission &amp; Background</h6></div>
      <div class="col-6 col-md-3">
        <label class="form-label small">Admitted on (Date)</label>
        <input type="text" class="form-control form-control-sm" id="tc_admitted_on" placeholder="dd-mm-yyyy">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">In Class</label>
        <input type="text" class="form-control form-control-sm" id="tc_admitted_class">
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label small">Previous School / TC From</label>
        <input type="text" class="form-control form-control-sm" id="tc_prev_school" value="Dr. V. S. E. C., Sharda Nagar, Kanpur Nagar">
      </div>

      <div class="col-12 border-top pt-3"><h6 class="text-primary">Departure Status</h6></div>
      <div class="col-6 col-md-3">
        <label class="form-label small">Left on (Date)</label>
        <input type="text" class="form-control form-control-sm" id="tc_left_on" placeholder="dd-mm-yyyy">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">Character</label>
        <input type="text" class="form-control form-control-sm" id="tc_character_cert" value="Good">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">Studying in (Class)</label>
        <input type="text" class="form-control form-control-sm" id="tc_studying_class">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">Promotion Status</label>
        <input type="text" class="form-control form-control-sm" id="tc_promotion_status" value="Granted">
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label small">Board / Stream</label>
        <input type="text" class="form-control form-control-sm" id="tc_board_stream" value="C.I.S.C.E., New Delhi">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">School Year (From)</label>
        <input type="text" class="form-control form-control-sm" id="tc_year_from" placeholder="dd-mm-yyyy">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">School Year (To)</label>
        <input type="text" class="form-control form-control-sm" id="tc_year_to" placeholder="dd-mm-yyyy">
      </div>

      <div class="col-12 border-top pt-3"><h6 class="text-primary">Registry Verification</h6></div>
      <div class="col-12">
        <label class="form-label small">DOB in Words</label>
        <textarea class="form-control form-control-sm" id="tc_dob_words" rows="2"></textarea>
        <div class="form-text">Matched against: <span id="tc_dobDisplay"></span></div>
      </div>
    </div>

    <div class="d-flex justify-content-end mt-3">
      <button class="btn btn-primary" id="btnIssueTC"><i class="fa-solid fa-paper-plane me-1"></i>Issue &amp; Archive</button>
    </div>
  </div>

  <hr>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="mb-0">Issued TC Records</h6>
    <button class="btn btn-outline-secondary btn-sm" id="btnRefreshTCHistory"><i class="fa-solid fa-rotate"></i></button>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead><tr><th>Sl. No.</th><th>Sch No.</th><th>Student Name</th><th>Class</th><th>Issue Date</th><th></th></tr></thead>
      <tbody id="tc_historyBody"></tbody>
    </table>
  </div>
</div>

<script src="/newapp/assets/js/tc.js"></script>
