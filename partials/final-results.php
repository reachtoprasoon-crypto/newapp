<div id="finalResultsPane">
  <h5 class="mb-3">Final Results</h5>

  <ul class="nav nav-pills mb-3" id="frSubNav">
    <li class="nav-item"><a class="nav-link active" href="#" data-fr-sub="roster">Final Roster</a></li>
    <li class="nav-item"><a class="nav-link" href="#" data-fr-sub="promotion">Promotion</a></li>
    <li class="nav-item"><a class="nav-link" href="#" data-fr-sub="reportcards">Report Cards</a></li>
  </ul>

  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
      <label class="form-label small">Class</label>
      <select class="form-select form-select-sm" id="fr_class"></select>
    </div>
  </div>

  <!-- Final Roster -->
  <div id="fr_roster">
    <button class="btn btn-outline-primary btn-sm mb-2" id="btnLoadRoster"><i class="fa-solid fa-rotate me-1"></i>Generate / Refresh Final Roster</button>
    <button class="btn btn-outline-secondary btn-sm mb-2 d-none" id="btnExportFinalRoster"><i class="fa-solid fa-file-excel me-1"></i>Export Excel</button>
    <p class="text-muted small">Recomputes and persists rank, highest-in-class, and final totals for this class.</p>
    <div class="table-responsive">
      <table class="table table-sm" id="fr_rosterTable">
        <thead><tr id="fr_rosterHead"></tr></thead>
        <tbody id="fr_rosterBody"></tbody>
      </table>
    </div>
  </div>

  <!-- Promotion -->
  <div id="fr_promotion" style="display:none;">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr><th>Roll</th><th>Scholar No.</th><th>Name</th><th>Promotion Status</th></tr></thead>
        <tbody id="fr_promotionBody"></tbody>
      </table>
    </div>
    <button class="btn btn-primary btn-sm" id="btnSavePromotion">Save Promotion Status</button>
    <button class="btn btn-outline-secondary btn-sm" id="btnExportPromotion"><i class="fa-solid fa-file-excel me-1"></i>Export Excel</button>
  </div>

  <!-- Report Cards (per-student link generator + Excel export) -->
  <div id="fr_reportcards" style="display:none;">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-6 col-md-3">
        <label class="form-label small">Term</label>
        <select class="form-select form-select-sm" id="fr_term"></select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small">Report</label>
        <select class="form-select form-select-sm" id="fr_report">
          <option value="1">Report 1</option>
          <option value="2">Report 2</option>
        </select>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr><th><input type="checkbox" id="fr_selectAllStudents"></th><th>Roll</th><th>Name</th><th></th></tr></thead>
        <tbody id="fr_studentsBody"></tbody>
      </table>
    </div>

    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" id="fr_includeSchool" checked><label class="form-check-label small" for="fr_includeSchool">Include School Name</label></div></div>
      <div class="col-6 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" id="fr_includeBranch" checked><label class="form-check-label small" for="fr_includeBranch">Include Branch</label></div></div>
      <div class="col-6 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" id="fr_includeWatermark" checked><label class="form-check-label small" for="fr_includeWatermark">Include Watermark</label></div></div>
      <div class="col-6 col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" id="fr_includeSignatures" checked><label class="form-check-label small" for="fr_includeSignatures">Include Signatures</label></div></div>
    </div>
    <button class="btn btn-secondary btn-sm" id="btnExportFinalReportCards"><i class="fa-solid fa-file-excel me-1"></i>Export Excel (one sheet per student)</button>
  </div>
</div>

<script src="/newapp/assets/js/final-results.js"></script>
