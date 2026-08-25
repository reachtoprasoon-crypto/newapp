<div id="attendancePane">
  <h5 class="mb-3">Attendance &amp; Height/Weight</h5>

  <ul class="nav nav-pills mb-3" id="attSubNav">
    <li class="nav-item"><a class="nav-link active" href="#" data-sub="term">Term Attendance</a></li>
    <li class="nav-item"><a class="nav-link" href="#" data-sub="htwt">Height / Weight</a></li>
    <li class="nav-item"><a class="nav-link" href="#" data-sub="daily">Daily Self-Attendance</a></li>
  </ul>

  <div id="gridArea">
    <div class="row g-2 mb-3">
      <div class="col-12 col-md-4" id="att_classWrap">
        <label class="form-label small">Class</label>
        <select class="form-select form-select-sm" id="att_class"><option value="">Select Class</option></select>
      </div>
      <div class="col-12 col-md-3" id="att_totalWrap">
        <label class="form-label small">Total Attendance (days)</label>
        <input type="number" class="form-control form-control-sm" id="att_total" min="1">
      </div>
    </div>

    <div class="alert alert-warning d-none" id="att_noActiveTerm">No active term/report is currently set by the administrator.</div>
    <div class="alert alert-secondary d-none" id="att_notAllowed">Feeding is currently disabled for this class by the administrator.</div>

    <div class="table-responsive d-none" id="att_tableWrap">
      <table class="table table-sm table-hover align-middle">
        <thead id="att_tableHead"></thead>
        <tbody id="att_tableBody"></tbody>
      </table>
      <button class="btn btn-primary btn-sm" id="btnSaveAttendance">Save</button>
    </div>
  </div>

  <div id="dailyArea" style="display:none;">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-12 col-md-4">
        <label class="form-label small">Class</label>
        <select class="form-select form-select-sm" id="da_class"><option value="">Select Class</option></select>
      </div>
      <div class="col-12 col-md-8 d-flex align-items-center gap-2">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="da_toggleLink">
          <label class="form-check-label small" for="da_toggleLink">Attendance link active</label>
        </div>
        <div class="input-group input-group-sm" style="max-width:320px;">
          <input type="text" class="form-control" id="da_link" readonly>
          <button class="btn btn-outline-secondary" id="da_copyLink" type="button"><i class="fa-solid fa-copy"></i></button>
        </div>
      </div>
    </div>

    <ul class="nav nav-tabs mb-3" id="dailySubNav">
      <li class="nav-item"><a class="nav-link active" href="#" data-daily-sub="live">Today's Session</a></li>
      <li class="nav-item"><a class="nav-link" href="#" data-daily-sub="monthly">Monthly Registry</a></li>
      <li class="nav-item"><a class="nav-link" href="#" data-daily-sub="holidays">Calendar &amp; Holidays</a></li>
    </ul>

    <div id="da_live">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead><tr><th>Roll</th><th>Scholar No.</th><th>Name</th><th>Status</th><th></th></tr></thead>
          <tbody id="da_liveBody"></tbody>
        </table>
      </div>
    </div>

    <div id="da_monthly" style="display:none;">
      <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
          <select class="form-select form-select-sm" id="da_month"></select>
        </div>
        <div class="col-6 col-md-3">
          <select class="form-select form-select-sm" id="da_year"></select>
        </div>
        <div class="col-12 col-md-3">
          <button class="btn btn-outline-primary btn-sm" id="btnLoadMonthly">Load Registry</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm" id="da_monthlyTable">
          <thead><tr id="da_monthlyHead"></tr></thead>
          <tbody id="da_monthlyBody"></tbody>
        </table>
      </div>
    </div>

    <div id="da_holidays" style="display:none;">
      <div class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-2">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="da_isRange">
            <label class="form-check-label small" for="da_isRange">Date range</label>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small">Date</label>
          <input type="date" class="form-control form-control-sm" id="da_holidayDate">
        </div>
        <div class="col-6 col-md-2" id="da_endDateWrap" style="display:none;">
          <label class="form-label small">End Date</label>
          <input type="date" class="form-control form-control-sm" id="da_holidayEndDate">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label small">Description</label>
          <input type="text" class="form-control form-control-sm" id="da_holidayDesc" placeholder="e.g. Diwali Break">
        </div>
        <div class="col-12 col-md-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="da_allClasses" checked>
            <label class="form-check-label small" for="da_allClasses">All Classes</label>
          </div>
        </div>
      </div>
      <select class="form-select form-select-sm mb-3 d-none" id="da_holidayClasses" multiple size="6"></select>
      <button class="btn btn-primary btn-sm" id="btnSetHoliday">Mark as Holiday</button>
      <button class="btn btn-outline-danger btn-sm" id="btnUnsetHoliday">Remove Holiday</button>
    </div>
  </div>
</div>

<script src="/firebase_to_php/assets/js/attendance.js"></script>
