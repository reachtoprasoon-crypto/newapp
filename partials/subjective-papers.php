<link href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>

<div id="spListView">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Subjective Papers</h5>
    <button class="btn btn-primary btn-sm d-none" id="spBtnNew"><i class="fa-solid fa-plus me-1"></i>Create Paper</button>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead><tr><th>Date</th><th>Title</th><th>Class</th><th>Subject</th><th>Marks</th><th id="spAuthorHead" class="d-none">By</th><th></th></tr></thead>
      <tbody id="spBody"></tbody>
    </table>
  </div>
</div>

<div id="spEditorView" class="d-none">
  <button class="btn btn-sm btn-outline-secondary mb-3" id="spBtnBack"><i class="fa-solid fa-arrow-left me-1"></i>Back</button>
  <input type="hidden" id="sp_spid">

  <div class="card mb-3">
    <div class="card-header py-2"><strong>Header Details</strong></div>
    <div class="card-body">
      <div class="row g-2 mb-2">
        <div class="col-md-3">
          <label class="form-label small fw-bold">Class</label>
          <select class="form-select form-select-sm" id="sp_sclass"><option value="">Select Class</option></select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">Subject</label>
          <select class="form-select form-select-sm" id="sp_subid"><option value="">Select Subject</option></select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">Exam Title</label>
          <input type="text" class="form-control form-control-sm" id="sp_title">
        </div>
        <div class="col-md-1">
          <label class="form-label small fw-bold">Max Marks</label>
          <input type="number" class="form-control form-control-sm" id="sp_max_marks" value="100">
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">Time Duration</label>
          <input type="text" class="form-control form-control-sm" id="sp_time_duration" value="3 Hours">
        </div>
      </div>
      <label class="form-label small fw-bold">General Paper Instructions</label>
      <input type="text" class="form-control form-control-sm" id="sp_instruction" list="sp_instructionList" value="Candidates are allowed additional 15 minutes for only reading the paper.">
      <datalist id="sp_instructionList"></datalist>
    </div>
  </div>

  <div id="sp_elements"></div>

  <div class="d-flex justify-content-center gap-2 py-2 border-top border-dashed mb-3">
    <button type="button" class="btn btn-outline-secondary btn-sm" id="spBtnAddPart">+ Part</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="spBtnAddSection">+ Section</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="spBtnAddQuestion">+ Question</button>
  </div>
  <div class="d-flex justify-content-end gap-2">
    <button type="button" class="btn btn-outline-secondary btn-sm" id="spBtnCancel">Cancel</button>
    <button type="button" class="btn btn-primary btn-sm" id="spBtnSave"><i class="fa-solid fa-floppy-disk me-1"></i>Save Paper</button>
  </div>

  <datalist id="sp_partList"></datalist>
  <datalist id="sp_sectionList"></datalist>
</div>

<script src="/newapp/assets/js/paper-editor.js?v=<?= filemtime(__DIR__ . '/../assets/js/paper-editor.js') ?>"></script>
<script src="/newapp/assets/js/subjective-papers.js?v=<?= filemtime(__DIR__ . '/../assets/js/subjective-papers.js') ?>"></script>
