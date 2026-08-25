<div id="communicationsPane">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Communications</h5>
    <button class="btn btn-primary btn-sm" id="btnNewCommunication"><i class="fa-solid fa-bullhorn me-1"></i>New Communication</button>
  </div>

  <ul class="nav nav-pills mb-3" id="commSubNav">
    <li class="nav-item"><a class="nav-link active" href="#" data-comm-sub="mine">My Communications</a></li>
    <li class="nav-item"><a class="nav-link" href="#" data-comm-sub="class">By Class</a></li>
  </ul>

  <div class="row g-2 mb-3 d-none" id="comm_classWrap">
    <div class="col-6 col-md-3">
      <select class="form-select form-select-sm" id="comm_classFilter"></select>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead><tr><th>Date</th><th>Type</th><th>Title</th><th>Classes</th><th>By</th><th>Attachment</th><th></th></tr></thead>
      <tbody id="comm_body"></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="commFormModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">New Communication</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="commForm">
          <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" class="form-control" id="cf_title" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Type</label>
            <select class="form-select" id="cf_type">
              <option value="Notice">Notice</option>
              <option value="Homework">Homework</option>
              <option value="Worksheet">Worksheet</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Content</label>
            <textarea class="form-control" id="cf_content" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Recipient Classes</label>
            <select class="form-select" id="cf_classes" multiple size="6"></select>
          </div>
          <div class="mb-3">
            <label class="form-label">Attachment (optional)</label>
            <input type="file" class="form-control" id="cf_attachment">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btnSendCommunication">Send</button>
      </div>
    </div>
  </div>
</div>

<script src="/firebase_to_php/assets/js/communications.js"></script>
