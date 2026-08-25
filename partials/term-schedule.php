<div id="termSchedulePane">
  <h5 class="mb-3">Term Schedule Management</h5>

  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
      <label class="form-label small">Class</label>
      <select class="form-select form-select-sm" id="tsc_class"><option value="">Select Class</option></select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small">Term</label>
      <select class="form-select form-select-sm" id="tsc_term"><option value="">Select Term</option></select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small">Report</label>
      <select class="form-select form-select-sm" id="tsc_report">
        <option value="1">Report 1</option>
        <option value="2">Report 2</option>
      </select>
    </div>
    <div class="col-6 col-md-4">
      <label class="form-label small">Exam</label>
      <select class="form-select form-select-sm" id="tsc_exam"><option value="">Select Exam</option></select>
    </div>
  </div>

  <div class="table-responsive d-none" id="tsc_tableWrap">
    <table class="table table-sm table-hover align-middle">
      <thead><tr><th>Subject</th><th>Max Marks</th></tr></thead>
      <tbody id="tsc_tableBody"></tbody>
    </table>
    <button class="btn btn-primary btn-sm" id="btnSaveTermSchedule">Save Schedule</button>
  </div>

  <hr>
  <h6>Copy Term Schedule to Other Classes</h6>
  <p class="text-muted small">Copies the source class's whole schedule for the selected term/report, replacing any existing schedule in the target classes for that term/report.</p>
  <div class="row g-2 align-items-end mb-2">
    <div class="col-12 col-md-4">
      <label class="form-label small">Target Classes</label>
      <select class="form-select form-select-sm" id="tsc_targetClasses" multiple size="6"></select>
    </div>
    <div class="col-12 col-md-4">
      <button class="btn btn-outline-primary btn-sm" id="btnCopySchedule">Copy from Current Selection</button>
    </div>
  </div>
</div>

<script src="/newapp/assets/js/term-schedule.js"></script>
