<link href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>

<div id="qpPane">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Question Papers (MCQ)</h5>
    <button class="btn btn-primary btn-sm d-none" id="qpBtnNew"><i class="fa-solid fa-plus me-1"></i>New Paper</button>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead><tr><th>Title</th><th>Class</th><th>Subject</th><th>Qns</th><th id="qpAuthorHead" class="d-none">By</th><th></th></tr></thead>
      <tbody id="qpBody"></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="qpFormModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="qpFormTitle">New MCQ Paper</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="qp_qpid">
        <div class="row g-2 mb-3">
          <div class="col-md-4">
            <label class="form-label small fw-bold">Class</label>
            <select class="form-select form-select-sm" id="qp_sclass"><option value="">Select Class</option></select>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Subject</label>
            <select class="form-select form-select-sm" id="qp_subid"><option value="">Select Subject</option></select>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Title</label>
            <input type="text" class="form-control form-control-sm" id="qp_title" placeholder="e.g. Unit Test 1">
          </div>
        </div>
        <div id="qp_questions"></div>
        <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-2" id="qpBtnAddQuestion"><i class="fa-solid fa-plus me-1"></i>Add Question</button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="qpBtnSave">Save Paper</button>
      </div>
    </div>
  </div>
</div>

<script src="/newapp/assets/js/paper-editor.js"></script>
<script src="/newapp/assets/js/question-papers.js"></script>
