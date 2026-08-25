<div id="activityLogPane">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Activity Log</h5>
    <button class="btn btn-outline-secondary btn-sm" id="btnRefreshLog"><i class="fa-solid fa-rotate me-1"></i>Refresh</button>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr><th>Timestamp</th><th>Actor</th><th>Action</th><th>Details</th></tr></thead>
      <tbody id="al_body"></tbody>
    </table>
  </div>
</div>

<script src="/firebase_to_php/assets/js/activity-log.js"></script>
