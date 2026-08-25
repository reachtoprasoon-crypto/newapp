<div id="studentsTotalPane">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Students Total (School-wide)</h5>
    <button class="btn btn-outline-secondary btn-sm" id="btnExportStudentsTotal"><i class="fa-solid fa-file-excel me-1"></i>Export Excel</button>
  </div>
  <div class="mb-3">
    <input type="text" class="form-control form-control-sm" id="st_search" placeholder="Search by name, scholar no, or class...">
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr><th>Sch No</th><th>Roll</th><th>Name</th><th>Class</th><th>Grand Total</th></tr></thead>
      <tbody id="st_body"></tbody>
    </table>
  </div>
</div>

<script src="/newapp/assets/js/students-total.js"></script>
