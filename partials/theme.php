<div id="themePane">
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-1">Appearance</h5>
      <p class="text-muted small mb-0">Applies for everyone — students, teachers, and admins.</p>
    </div>
    <div class="card-body d-flex gap-3">
      <button type="button" class="btn btn-lg flex-fill" id="themeBtnLight"><i class="fa-solid fa-sun me-2"></i>Light</button>
      <button type="button" class="btn btn-lg flex-fill" id="themeBtnDark"><i class="fa-solid fa-moon me-2"></i>Dark</button>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="mb-1">Report Card Watermark</h5>
      <p class="text-muted small mb-0">The faint background image used on printed/exported report cards.</p>
    </div>
    <div class="card-body">
      <div class="row g-4">
        <div class="col-md-6">
          <label class="form-label">Watermark Image</label>
          <input type="file" class="form-control mb-3" id="wm_file" accept="image/*">

          <label class="form-label">Opacity: <span id="wm_opacityLabel">30%</span></label>
          <input type="range" class="form-range mb-3" id="wm_opacity" min="1" max="100" value="30">

          <label class="form-label">Size: <span id="wm_sizeLabel">350px</span></label>
          <input type="range" class="form-range mb-3" id="wm_size" min="100" max="800" step="10" value="350">

          <button type="button" class="btn btn-primary" id="wm_apply"><i class="fa-solid fa-floppy-disk me-1"></i>Apply Branding Update</button>
        </div>
        <div class="col-md-6">
          <label class="form-label">Preview</label>
          <div class="border rounded p-3 bg-body-secondary text-center" style="min-height: 220px;">
            <canvas id="wm_previewCanvas" style="max-width: 100%; max-height: 220px;"></canvas>
            <div id="wm_noImage" class="text-muted small py-5">No watermark set.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="/newapp/assets/js/theme.js"></script>
