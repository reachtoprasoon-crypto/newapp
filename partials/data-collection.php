<div id="dataCollectionPane">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Data Collection Forms</h5>
    <button class="btn btn-primary btn-sm" id="btnNewForm"><i class="fa-solid fa-plus me-1"></i>New Form</button>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead><tr><th>Title</th><th>By</th><th>Active</th><th>Created</th><th></th></tr></thead>
      <tbody id="dc_formsBody"></tbody>
    </table>
  </div>

  <div id="dc_responsesArea" class="d-none mt-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="mb-0">Responses: <span id="dc_responsesTitle"></span></h6>
      <button class="btn btn-outline-secondary btn-sm" id="btnCloseResponses">Close</button>
    </div>
    <div class="table-responsive">
      <table class="table table-sm" id="dc_responsesTable">
        <thead><tr id="dc_responsesHead"></tr></thead>
        <tbody id="dc_responsesBody"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Form Builder Modal -->
<div class="modal fade" id="formBuilderModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="fb_title">New Form</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="fb_id">
        <div class="mb-3">
          <label class="form-label">Form Title</label>
          <input type="text" class="form-control" id="fb_formTitle" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="fb_description" rows="2"></textarea>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="fb_isActive" checked>
          <label class="form-check-label" for="fb_isActive">Active (visible on the public link)</label>
        </div>

        <label class="form-label fw-bold">Fields</label>
        <div id="fb_fields"></div>
        <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="btnAddField"><i class="fa-solid fa-plus me-1"></i>Add Field</button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btnSaveForm">Save Form</button>
      </div>
    </div>
  </div>
</div>

<script src="/newapp/assets/js/data-collection.js"></script>
