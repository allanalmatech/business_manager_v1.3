<?php
// modules/products/products.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_permission('products.view');

$BASE_URL = $GLOBALS['BASE_URL'] ?? '';

$page_title = "Products";
$page_subtitle = "Manage products, units, and stock";

$extra_js = ["assets/js/app.js"]; // optional

require_once __DIR__ . '/../../templates/layout/header.php';
?>
<div class="app-shell">
  <?php require_once __DIR__ . '/../../templates/layout/sidebar.php'; ?>

  <div class="app-content">
    <?php require_once __DIR__ . '/../../templates/layout/topbar.php'; ?>

    <main class="page-wrap">
      <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div class="d-flex gap-2">
          <input id="q" class="form-control form-control-sm" style="max-width:320px" placeholder="Search name or SKU...">
          <button class="btn btn-sm btn-outline-secondary" id="btnSearch">Search</button>
        </div>

        <?php if (user_has_permission('products.create')): ?>
          <button class="btn btn-sm btn-primary" id="btnNew">+ New Product</button>
        <?php endif; ?>
      </div>

      <div class="card shadow-sm rounded-4">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm align-middle" id="tbl">
              <thead>
                <tr>
                  <th>Image</th>
                  <th>SKU</th>
                  <th>Name</th>
                  <th>Unit</th>
                  <th>Brand</th>
                  <th class="text-end">Cost</th>
                  <th class="text-end">Wholesale</th>
                  <th class="text-end">Retail</th>
                  <th>Stock</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
          <div class="text-muted small" id="hint">Loading…</div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Product Modal -->
<div class="modal fade" id="mdlProduct" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="mdlTitle">Product</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="id">
        <input type="hidden" id="current_images_count" value="0">

        <div class="row g-2">
          <div class="col-md-8">
            <label class="form-label">Product Name *</label>
            <input class="form-control" id="name">
          </div>
          <div class="col-md-4">
            <label class="form-label">SKU / Barcode *</label>
            <input class="form-control" id="sku">
          </div>

          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="description" rows="2"></textarea>
          </div>

          <div class="col-md-4">
            <label class="form-label">Source / Supplier</label>
            <input class="form-control" id="source" placeholder="e.g. ABC Supplies">
          </div>

          <div class="col-md-4">
            <label class="form-label">Unit Type *</label>
            <select class="form-select" id="unit_type">
              <option value="boxes">Boxes / Cartons</option>
              <option value="dozens">Dozens</option>
              <option value="pairs">Pairs</option>
              <option value="pieces" selected>Pieces</option>
              <option value="units">Units (kg, liters…)</option>
            </select>
          </div>

          <div class="col-md-4" id="wrap_unit_name" style="display:none;">
            <label class="form-label">Unit Name (e.g. kg) *</label>
            <input class="form-control" id="unit_name" placeholder="kg">
          </div>

          <div class="col-md-4" id="wrap_ppb" style="display:none;">
            <label class="form-label">Pieces per Box *</label>
            <input class="form-control" id="pieces_per_box" type="number" min="1" step="1" placeholder="e.g. 24">
          </div>

          <div class="col-md-4">
            <label class="form-label">Default Location</label>
            <select class="form-select" id="default_location_id">
              <option value="">— None —</option>
            </select>
          </div>

          <!-- ✅ BRAND -->
          <div class="col-md-4">
            <label class="form-label">Brand</label>
            <select class="form-select" id="brand_id">
              <option value="">— None —</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Cost Price</label>
            <input class="form-control" id="cost_price" type="number" min="0" step="0.01">
          </div>
          <div class="col-md-4">
            <label class="form-label">Wholesale Price (Floor)</label>
            <input class="form-control" id="wholesale_price" type="number" min="0" step="0.01">
          </div>
          <div class="col-md-4">
            <label class="form-label">Retail Price</label>
            <input class="form-control" id="retail_price" type="number" min="0" step="0.01">
          </div>

          <div class="col-md-6">
            <label class="form-label">Stock (Base)</label>
            <div class="form-text">
              If Boxes/Dozens/Pairs: enter total pieces in stock. If Units(kg): enter kg quantity.
            </div>
            <input class="form-control" id="qty_base" type="number" min="0" step="0.01">
          </div>

          <div class="col-md-6">
            <label class="form-label">Low Level (Base)</label>
            <div class="form-text">
              If Boxes/Dozens/Pairs: low stock pieces. If Units: low stock in that unit.
            </div>
            <input class="form-control" id="low_level_base" type="number" min="0" step="0.01">
          </div>

          <div class="col-md-4">
            <label class="form-label">Active</label>
            <select class="form-select" id="is_active">
              <option value="1" selected>Yes</option>
              <option value="0">No</option>
            </select>
          </div>

        </div>

        <!-- Images -->
        <div class="mt-3">
          <label class="form-label">Product Images <span id="imageCount">(0/5)</span></label>
          <div class="d-flex flex-wrap gap-2 mb-2" id="imageGallery"></div>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary" id="btnUploadImage">Upload File</button>
            <button class="btn btn-sm btn-outline-secondary" id="btnImportUrl">Import from URL</button>
            <button class="btn btn-sm btn-outline-info" id="btnBulkImport">Bulk URL Import</button>
            <button class="btn btn-sm btn-outline-warning" id="btnQrCapture">QR Capture</button>
            <input type="file" id="fileInput" accept="image/*" multiple style="display:none;">
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>

        <?php if (user_has_permission('products.delete')): ?>
          <button class="btn btn-outline-danger btn-sm" id="btnDelete" style="display:none;">Delete</button>
        <?php endif; ?>

        <?php if (user_has_permission('products.create') || user_has_permission('products.update')): ?>
          <button class="btn btn-primary btn-sm" id="btnSave">Save</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Bulk Image Import Modal -->
<div class="modal fade" id="mdlBulkImport" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Bulk Image Import</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-info">
          <i class="bi bi-info-circle me-2"></i>
          <strong>Instructions:</strong><br>
          • Enter one image URL per line<br>
          • Supported formats: JPG, PNG, GIF, WebP<br>
          • Images will be downloaded and stored locally<br>
          • Maximum 5 images per product (current: <span id="currentCount">0</span>/5)
        </div>

        <div class="mb-3">
          <label class="form-label">Image URLs (one per line)</label>
          <textarea class="form-control" id="bulkImageUrls" rows="8" placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.png&#10;https://example.com/image3.gif"></textarea>
        </div>

        <div class="mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="replaceExisting" >
            <label class="form-check-label" for="replaceExisting">
              Replace existing images (uncheck to add to current images)
            </label>
          </div>
        </div>

        <div id="bulkImportProgress" style="display:none;">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span>Importing images...</span>
            <span id="progressText">0/0</span>
          </div>
          <div class="progress">
            <div class="progress-bar" id="progressBar" role="progressbar" style="width: 0%"></div>
          </div>
          <div id="importResults" class="mt-2"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" id="btnStartBulkImport">Start Import</button>
      </div>
    </div>
  </div>
</div>

<!-- QR Capture Modal -->
<div class="modal fade" id="mdlQRCapture" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">QR Capture</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-info py-2 small">
          <b>Tip:</b> Use your phone to scan a QR code containing an image URL.
        </div>

        <img id="qrPreview" class="preview mb-3" alt="Preview" style="width:100%; max-height:40vh; object-fit:contain; background:#fff; border:1px dashed #cfd4da; border-radius:12px;">

        <!-- SCAN FROM PHONE MODE -->
        <div class="text-center">
          <div class="alert alert-info">
            <h6><i class="bi bi-phone"></i> Scan from Phone</h6>
            <p class="mb-2">Scan QR code to open camera/gallery on your phone</p>
          </div>
          
          <!-- IP CONFIGURATION -->
          <div class="mb-3">
            <div class="card">
              <div class="card-body">
                <h6 class="card-title">Server IP Configuration</h6>
                <div class="row g-2 align-items-end">
                  <div class="col-8">
                    <label for="serverIP" class="form-label">Server IP Address:</label>
                    <input type="text" class="form-control" id="serverIP" placeholder="localhost or 192.168.1.100">
                    <div class="form-text">Enter the IP address of this server. QR code will update automatically.</div>
                  </div>
                  <div class="col-4">
                    <button class="btn btn-primary w-100" id="btnUpdateIP">Update</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- QR Code for Phone to Scan -->
          <div class="mb-3">
            <div class="card">
              <div class="card-body">
                <h6 class="card-title">Step 1: Scan this QR Code</h6>
                <p class="card-text small text-muted">Use your phone camera to scan this code</p>
                <div id="phoneQRCode" style="width:200px; height:200px; margin:0 auto; background:#fff; border:2px solid #dee2e6; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                  <div class="text-center">
                    <div class="spinner-border text-primary mb-2"></div>
                    <div class="small">Generating QR...</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Status -->
          <div class="mb-3">
            <div class="card">
              <div class="card-body">
                <h6 class="card-title">Step 2: Upload Images</h6>
                <div id="phoneConnectionStatus" class="alert alert-info">
                  <i class="bi bi-phone"></i> Scan QR code with phone to open camera/gallery
                </div>
              </div>
            </div>
          </div>
          
          <!-- Instructions -->
          <div class="alert alert-light">
            <h6><i class="bi bi-info-circle"></i> How it works:</h6>
            <ol class="small text-start mb-0">
              <li>Configure server IP address above</li>
              <li>Scan QR code with your phone</li>
              <li>Your phone will open a mobile-friendly page</li>
              <li>Use camera capture or gallery to select images</li>
              <li>Images will be uploaded to this product automatically</li>
            </ol>
          </div>
        </div>

        <!-- UPLOAD BUTTON -->
        <div class="d-grid gap-2 mt-3">
          <button class="btn btn-success" id="btnQRUpload" disabled>
            Upload to Product
          </button>

          <div id="qrMsg" class="small mt-2 muted"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../templates/layout/footer.php'; ?>

<script>
  window.APP = {
    BASE_URL: <?= json_encode($BASE_URL) ?>,
    CSRF: <?= json_encode($_SESSION['csrf'] ?? '') ?>,
  };
</script>

<script>
const BASE_URL = <?= json_encode($BASE_URL) ?>;
const canUpdate = <?= json_encode(user_has_permission('products.update')) ?>;
const canDelete = <?= json_encode(user_has_permission('products.delete')) ?>;
const canCreate = <?= json_encode(user_has_permission('products.create')) ?>;

const mdl = new bootstrap.Modal(document.getElementById('mdlProduct'));
const tbody = document.querySelector('#tbl tbody');
const hint = document.getElementById('hint');

let currentProductId = null;
let productImages = [];

// Modals
const bulkImportModal = new bootstrap.Modal(document.getElementById('mdlBulkImport'));
const qrCaptureModal = new bootstrap.Modal(document.getElementById('mdlQRCapture'));

// QR Scanner variables
let qrScannerActive = false;
let qrStream = null;
let qrPickedFile = null;

function showUnitFields(){
  const t = document.getElementById('unit_type').value;
  document.getElementById('wrap_ppb').style.display = (t === 'boxes') ? '' : 'none';
  document.getElementById('wrap_unit_name').style.display = (t === 'units') ? '' : 'none';
}

// Image handling functions
function displayProductImages() {
  const gallery = document.getElementById('imageGallery');
  const imageCount = document.getElementById('imageCount');
  
  if (!gallery) return;
  
  gallery.innerHTML = '';
  
  if (productImages && productImages.length > 0) {
    productImages.forEach((img, index) => {
      const div = document.createElement('div');
      div.className = 'position-relative d-inline-block me-2 mb-2';
      const imageSrc = img.startsWith('http') ? img : (BASE_URL + '/' + img);
      div.innerHTML = `
        <img src="${imageSrc}" alt="Product image ${index + 1}" style="width: 80px; height: 80px; object-fit: cover;" class="rounded border">
        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" onclick="removeImage(${index})" style="padding: 2px 6px;">
          <i class="bi bi-x"></i>
        </button>
      `;
      gallery.appendChild(div);
    });
    
    if (imageCount) {
      imageCount.textContent = `(${productImages.length}/5)`;
    }
  } else {
    if (imageCount) {
      imageCount.textContent = '(0/5)';
    }
  }
}

function removeImage(index) {
  productImages.splice(index, 1);
  displayProductImages();
}

function addImageToGallery(imageUrl) {
  if (productImages.length >= 5) {
    alert('Maximum 5 images allowed');
    return false;
  }
  productImages.push(imageUrl);
  displayProductImages();
  return true;
}

// Bulk import functions
function openBulkImport() {
  const currentCount = productImages.length;
  document.getElementById('currentCount').textContent = currentCount;
  document.getElementById('bulkImageUrls').value = '';
  document.getElementById('replaceExisting').checked = false;
  document.getElementById('bulkImportProgress').style.display = 'none';
  document.getElementById('importResults').innerHTML = '';
  
  bulkImportModal.show();
}

async function startBulkImport() {
  const urls = document.getElementById('bulkImageUrls').value
    .split('\n')
    .map(url => url.trim())
    .filter(url => url.length > 0);
  
  if (urls.length === 0) {
    alert('Please enter at least one image URL');
    return;
  }
  
  const replaceExisting = document.getElementById('replaceExisting').checked;
  const maxImages = 5;
  const currentCount = productImages.length;
  const availableSlots = replaceExisting ? maxImages : (maxImages - currentCount);
  
  if (urls.length > availableSlots) {
    alert(`You can only import ${availableSlots} more images (maximum ${maxImages} per product)`);
    return;
  }
  
  // Show progress
  document.getElementById('bulkImportProgress').style.display = 'block';
  document.getElementById('progressText').textContent = `0/${urls.length}`;
  document.getElementById('progressBar').style.width = '0%';
  document.getElementById('importResults').innerHTML = '';
  
  const results = [];
  let successCount = 0;
  
  if (replaceExisting) {
    productImages = [];
  }
  
  for (let i = 0; i < urls.length; i++) {
    const url = urls[i];
    const progress = ((i + 1) / urls.length) * 100;
    
    document.getElementById('progressText').textContent = `${i + 1}/${urls.length}`;
    document.getElementById('progressBar').style.width = `${progress}%`;
    
    try {
      const result = await scrapeAndStoreImage(url);
      results.push({ url, success: true, message: 'Success', filename: result.filename });
      productImages.push(result.url);
      successCount++;
    } catch (error) {
      results.push({ url, success: false, message: error.message });
    }
    
    // Small delay to show progress
    await new Promise(resolve => setTimeout(resolve, 300));
  }
  
  // Show results
  const resultsHtml = results.map(r => `
    <div class="alert ${r.success ? 'alert-success' : 'alert-danger'} py-2">
      <small>
        <strong>${r.success ? '✓' : '✗'}</strong> ${r.url.substring(0, 50)}...<br>
        ${r.message}
      </small>
    </div>
  `).join('');
  
  document.getElementById('importResults').innerHTML = resultsHtml;
  
  // Update gallery
  displayProductImages();
  
  // Show completion message
  setTimeout(() => {
    if (successCount > 0) {
      bulkImportModal.hide();
      alert(`Successfully imported ${successCount} out of ${urls.length} images`);
    } else {
      alert('Failed to import any images. Please check the URLs and try again.');
    }
  }, 2000);
}

async function scrapeAndStoreImage(imageUrl) {
  // Validate URL format
  if (!imageUrl.match(/^https?:\/\/.+\.(jpg|jpeg|png|gif|webp)$/i)) {
    throw new Error('Invalid image URL format');
  }
  
  // Test if image is accessible
  const testResponse = await fetch(imageUrl, { method: 'HEAD' });
  if (!testResponse.ok) {
    throw new Error('Image not accessible');
  }
  
  const contentType = testResponse.headers.get('content-type');
  if (!contentType || !contentType.startsWith('image/')) {
    throw new Error('URL does not point to an image');
  }
  
  // Download image via server
  const formData = new FormData();
  formData.append('url', imageUrl);
  formData.append('product_id', currentProductId || 0);
  formData.append('csrf', window.APP.CSRF);
  
  const response = await fetch(BASE_URL + '/api/products.php?action=scrape_image', {
    method: 'POST',
    body: formData
  });
  
  const text = await response.text();
  let json;
  try {
    json = JSON.parse(text);
  } catch (e) {
    console.error('Scrape response:', text);
    throw new Error('Invalid server response');
  }
  
  if (!json.ok) {
    throw new Error(json.error || 'Failed to scrape image');
  }
  
  return json.data;
}

// File upload handler
function handleFileUpload(files) {
  const file = files[0];
  if (!file) return;
  
  if (!file.type.startsWith('image/')) {
    alert('Please select an image file');
    return;
  }
  
  if (file.size > 5 * 1024 * 1024) { // 5MB limit
    alert('Image size must be less than 5MB');
    return;
  }
  
  const formData = new FormData();
  formData.append('file', file);
  formData.append('product_id', currentProductId || 0);
  formData.append('csrf', window.APP.CSRF);
  
  fetch(BASE_URL + '/api/products.php?action=upload_image', {
    method: 'POST',
    body: formData
  })
  .then(res => res.text())
  .then(text => {
    try {
      const json = JSON.parse(text);
      if (json.ok) {
        addImageToGallery(json.data.url);
      } else {
        alert(json.error || 'Upload failed');
      }
    } catch (e) {
      console.error('Upload response:', text);
      alert('Upload failed - invalid response');
    }
  })
  .catch(err => {
    console.error('Upload error:', err);
    alert('Upload failed');
  });
}

// URL import handler
function importFromUrl() {
  const url = prompt('Enter image URL:');
  if (!url) return;
  
  if (!url.match(/^https?:\/\/.+\.(jpg|jpeg|png|gif|webp)$/i)) {
    alert('Please enter a valid image URL');
    return;
  }
  
  // Create a temporary image to test if URL loads
  const img = new Image();
  img.onload = function() {
    addImageToGallery(url);
  };
  img.onerror = function() {
    alert('Failed to load image from URL');
  };
  img.src = url;
}

function openQRCapture() {
  // Reset QR capture modal
  resetQRCaptureModal();
  
  // Show the QR capture modal
  qrCaptureModal.show();
  
  // Initialize phone scanning immediately (only mode available)
  initPhoneScanning();
}

function resetQRCaptureModal() {
  // Reset file and preview
  qrPickedFile = null;
  document.getElementById('qrPreview').src = '';
  document.getElementById('qrPreview').style.display = 'none';
  
  // Reset messages
  setQRMessage('');
  
  // Reset upload button
  document.getElementById('btnQRUpload').disabled = true;
  
  // Stop phone scanning
  stopPhoneScanning();
}

function switchQRMode(mode) {
  // Hide all modes
  document.querySelectorAll('.qr-capture-mode').forEach(el => el.style.display = 'none');
  
  // Show selected mode
  switch(mode) {
    case 'camera':
      document.getElementById('qrCameraMode').style.display = 'block';
      stopQRScanner();
      break;
    case 'qr':
      document.getElementById('qrQRMode').style.display = 'block';
      break;
    case 'phone':
      document.getElementById('qrPhoneMode').style.display = 'block';
      stopQRScanner();
      initPhoneScanning();
      break;
    case 'gallery':
      document.getElementById('qrGalleryMode').style.display = 'block';
      stopQRScanner();
      break;
  }
}

// Phone Scanning Functions
let phoneSessionId = null;
let customServerIP = '';

function initPhoneScanning() {
  // Load saved IP from localStorage
  customServerIP = localStorage.getItem('customServerIP') || '';
  
  if (customServerIP) {
    document.getElementById('serverIP').value = customServerIP;
  }
  
  // Generate QR code for mobile scanning
  generatePhoneQRCode();
  
  // Update status to show simple workflow
  const statusEl = document.getElementById('phoneConnectionStatus');
  statusEl.className = 'alert alert-info';
  statusEl.innerHTML = '<i class="bi bi-phone"></i> Scan QR code with phone to open camera/gallery';
}

function generatePhoneQRCode() {
  const qrContainer = document.getElementById('phoneQRCode');

  if (!currentProductId) {
    qrContainer.innerHTML = `<div class="text-danger small">Save/Edit a product first.</div>`;
    return;
  }

  // Get server IP from input or default
  const serverIP = customServerIP || window.location.hostname || 'localhost';
  
  // Generate mobile URL without CSRF (will be generated server-side)
  const mobileUrl = `http://${serverIP}/business_manager_v1.2/modules/products/capture.php?pid=${currentProductId}`;
  
  // Generate QR code using QR Server API (more reliable than Google Charts)
  const qrApiUrl = `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(mobileUrl)}`;
  
  qrContainer.innerHTML = `
    <div class="text-center p-3">
      <div class="mb-2">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading QR code...</span>
        </div>
      </div>
      <img id="qrCodeImage" src="${qrApiUrl}" width="240" height="240" alt="QR Code" 
           class="img-fluid border border-secondary" 
           style="display:none;" 
           onload="this.style.display='block'; this.previousElementSibling.style.display='none';"
           onerror="this.style.display='none'; document.getElementById('qrError').style.display='block';">
      <div id="qrError" class="text-danger small" style="display:none;">
        Failed to load QR code. <button class="btn btn-sm btn-outline-primary" onclick="generatePhoneQRCode()">Retry</button>
      </div>
      <div class="small text-muted mt-2">QR Code Generated</div>
      <div class="small text-muted mt-2">
        <strong>URL:</strong><br>
        <code style="font-size: 11px; word-break: break-all;">${mobileUrl}</code>
      </div>
    </div>
  `;
  
  setQRMessage('Scan QR code with your phone camera.');
}

function updateServerIP() {
  const ipInput = document.getElementById('serverIP');
  const newIP = ipInput.value.trim();
  
  if (!newIP) {
    setQRMessage('Please enter a valid IP address', true);
    return;
  }
  
  // Save to localStorage
  localStorage.setItem('customServerIP', newIP);
  customServerIP = newIP;
  
  // Regenerate QR code with new IP
  generatePhoneQRCode();
  
  setQRMessage('Server IP updated and QR code regenerated!');
}

function stopPhoneScanning() {
  // No polling interval to clean up in simplified version
  phoneSessionId = null;
}

function setQRMessage(text, isError = false) {
  const msg = document.getElementById('qrMsg');
  msg.textContent = text || '';
  msg.className = 'small mt-2 ' + (isError ? 'text-danger' : 'muted');
}

function handleQRFilePick(file) {
  if (!file) return;
  if (!file.type.startsWith('image/')) {
    setQRMessage('Please select an image file.', true);
    return;
  }
  if (file.size > 6 * 1024 * 1024) {
    setQRMessage('Image too large. Max 6MB.', true);
    return;
  }
  
  qrPickedFile = file;
  document.getElementById('qrPreview').src = URL.createObjectURL(file);
  document.getElementById('qrPreview').style.display = 'block';
  document.getElementById('btnQRUpload').disabled = false;
  setQRMessage('Ready to upload: ' + file.name);
}

// QR Scanner Functions (adapted from capture.php)
async function startQRScanner() {
  try {
    qrStream = await navigator.mediaDevices.getUserMedia({ 
      video: { facingMode: 'environment' } 
    });
    
    const qrVideo = document.getElementById('qrVideo');
    const qrOverlay = document.getElementById('qrOverlay');
    
    qrVideo.srcObject = qrStream;
    qrVideo.style.display = 'block';
    qrOverlay.style.display = 'none';
    qrScannerActive = true;
    
    // Start QR code scanning
    scanQRCode();
    
  } catch (error) {
    console.error('Camera error:', error);
    const qrOverlay = document.getElementById('qrOverlay');
    qrOverlay.innerHTML = '<div class="text-danger">Camera access denied</div>';
    qrOverlay.style.display = 'block';
  }
}

function stopQRScanner() {
  if (qrStream) {
    qrStream.getTracks().forEach(track => track.stop());
    qrStream = null;
  }
  
  const qrVideo = document.getElementById('qrVideo');
  const qrOverlay = document.getElementById('qrOverlay');
  
  qrVideo.style.display = 'none';
  qrOverlay.style.display = 'block';
  qrOverlay.innerHTML = '<div class="spinner-border text-light mb-2"></div><div>Initializing QR scanner...</div>';
  qrScannerActive = false;
  
  const qrResult = document.getElementById('qrResult');
  qrResult.style.display = 'none';
}

async function scanQRCode() {
  if (!qrScannerActive) return;
  
  try {
    const qrVideo = document.getElementById('qrVideo');
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    
    canvas.width = qrVideo.videoWidth;
    canvas.height = qrVideo.videoHeight;
    context.drawImage(qrVideo, 0, 0, canvas.width, canvas.height);
    
    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
    const code = await detectQRCode(imageData);
    
    if (code) {
      handleQRCodeDetected(code);
    } else {
      requestAnimationFrame(() => scanQRCode());
    }
    
  } catch (error) {
    console.error('QR scan error:', error);
    setTimeout(() => scanQRCode(), 1000);
  }
}

async function detectQRCode(imageData) {
  // Placeholder for QR detection (same as capture.php)
  return new Promise((resolve) => {
    setTimeout(() => {
      const simulatedQRs = [
        'https://picsum.photos/seed/product1/400/400.jpg',
        'https://picsum.photos/seed/product2/400/400.jpg',
        'https://picsum.photos/seed/product3/400/400.jpg'
      ];
      
      if (Math.random() > 0.7) {
        resolve(simulatedQRs[Math.floor(Math.random() * simulatedQRs.length)]);
      } else {
        resolve(null);
      }
    }, 2000);
  });
}

function handleQRCodeDetected(code) {
  qrScannerActive = false;
  document.getElementById('qrUrl').textContent = code;
  document.getElementById('qrResult').style.display = 'block';
  
  loadImageFromQRURL(code);
}

async function loadImageFromQRURL(url) {
  try {
    setQRMessage('Loading image from QR code...');
    
    const response = await fetch(url);
    if (!response.ok) throw new Error('Failed to fetch image');
    
    const blob = await response.blob();
    if (!blob.type.startsWith('image/')) throw new Error('URL does not contain an image');
    
    const filename = 'qr_image_' + Date.now() + '.jpg';
    const file = new File([blob], filename, { type: blob.type });
    
    handleQRFilePick(file);
    setQRMessage('Image loaded from QR code successfully!');
    
  } catch (error) {
    setQRMessage('Failed to load image from QR code: ' + error.message, true);
  }
}

async function uploadQRImage() {
  if (!qrPickedFile) return;
  
  const btnUpload = document.getElementById('btnQRUpload');
  btnUpload.disabled = true;
  setQRMessage('Uploading...');
  
  const formData = new FormData();
  formData.append('product_id', currentProductId || 0);
  formData.append('csrf', window.APP.CSRF);
  formData.append('file', qrPickedFile);
  
  try {
    const response = await fetch(BASE_URL + '/api/product_images.php?action=upload', {
      method: 'POST',
      body: formData
    });
    
    const text = await response.text();
    let json;
    try { 
      json = JSON.parse(text); 
    } catch(e) {
      console.error('Non-JSON:', text);
      setQRMessage('Server returned non-JSON. Check console.', true);
      btnUpload.disabled = false;
      return;
    }
    
    if (!json.ok) {
      setQRMessage(json.error || 'Upload failed', true);
      btnUpload.disabled = false;
      return;
    }
    
    setQRMessage('Upload successful ');
    
    // Add image to product gallery
    addImageToGallery(json.data.url);
    
    // Close modal after a short delay
    setTimeout(() => {
      qrCaptureModal.hide();
    }, 1000);
    
  } catch (err) {
    console.error(err);
    setQRMessage('Network error uploading image.', true);
    btnUpload.disabled = false;
  }
}

async function loadProductImages(productId) {
  try {
    const res = await fetch(BASE_URL + "/api/products.php?action=get&id=" + productId);
    const txt = await res.text();
    let json;
    try { json = JSON.parse(txt); }
    catch(e) {
      console.error('Failed to load product images:', e);
      return;
    }
    
    if (json.ok && json.data.images) {
      try {
        const images = typeof json.data.images === 'string' ? JSON.parse(json.data.images) : json.data.images;
        if (Array.isArray(images)) {
          productImages = images.filter(img => img && img.trim() !== '');
          displayProductImages();
        }
      } catch (e) {
        console.error('Error parsing images:', e);
      }
    }
  } catch (e) {
    console.error('Error loading product images:', e);
  }
}

function num(x){
  if(x === null || x === undefined) return '0';
  return Number(x).toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2});
}
function escapeHtml(s){
  return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

/* ----------------------------
   ✅ LOAD BRANDS INTO DROPDOWN
----------------------------- */
async function loadBrandsInto(selectId){
  const sel = document.getElementById(selectId);
  if (!sel) return;

  sel.innerHTML = '<option value="">— None —</option>';

  try{
    const res = await fetch(BASE_URL + "/api/products.php?action=brands");
    const txt = await res.text();
    let json;
    try { json = JSON.parse(txt); } catch(e){
      console.error('[brands] non-json:', txt);
      return;
    }
    if(!json.ok) {
      console.warn('[brands] error:', json.error);
      return;
    }
    const brands = json.data?.brands || [];
    brands.forEach(b=>{
      const o = document.createElement('option');
      o.value = b.id;
      o.textContent = b.name;
      sel.appendChild(o);
    });
  } catch(e){
    console.error('[brands] failed:', e);
  }
}

/* ----------------------------
   TABLE LOADER
----------------------------- */
async function loadProducts(){
  hint.textContent = "Loading…";
  tbody.innerHTML = "";
  const q = document.getElementById('q').value.trim();
  const url = BASE_URL + "/api/products.php?action=list&q=" + encodeURIComponent(q);

  const res = await fetch(url);
  const txt = await res.text();

  let json;
  try { json = JSON.parse(txt); }
  catch(e){
    console.error('[products list] non-json:', txt);
    hint.textContent = "Server returned non-JSON (check console).";
    return;
  }

  if(!json.ok){ hint.textContent = json.error || "Failed"; return; }

  json.data.forEach(p => {
    const tr = document.createElement('tr');

    let images = [];
    if (p.images) {
      try { images = JSON.parse(p.images); } catch(e) { images = []; }
    }
    const firstImage = images.length > 0 ? images[0] : null;

    const imageCell = firstImage
      ? `<img src="${firstImage.startsWith('http') ? firstImage : (BASE_URL + '/' + firstImage)}" width="40" height="40" class="border rounded" style="object-fit:cover;">`
      : `<div class="text-muted small">—</div>`;

    tr.innerHTML = `
      <td>${imageCell}</td>
      <td>${escapeHtml(p.sku)}</td>
      <td>${escapeHtml(p.name)}</td>
      <td>${escapeHtml(p.unit_type)}${p.unit_type==='units' ? ' ('+escapeHtml(p.unit_name||'')+')' : ''}${p.unit_type==='boxes' ? ' • '+(p.pieces_per_box||0)+' pcs/box' : ''}</td>
      <td>${escapeHtml(p.brand_name || '')}</td>
      <td class="text-end">${num(p.cost_price)}</td>
      <td class="text-end">${num(p.wholesale_price)}</td>
      <td class="text-end">${num(p.retail_price)}</td>
      <td>${escapeHtml(p.stock_display || '')}</td>
      <td>${p.is_active==1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Disabled</span>'}</td>
      <td class="text-end">
        ${(canUpdate ? `<button class="btn btn-sm btn-outline-primary" data-id="${p.id}" data-act="edit">Edit</button>` : '')}
      </td>
    `;
    tbody.appendChild(tr);
  });

  hint.textContent = json.data.length ? "" : "No products yet.";
}

/* ----------------------------
   FORM HELPERS
----------------------------- */
function clearForm(){
  [
    'id','name','sku','description','source','unit_name','pieces_per_box',
    'cost_price','wholesale_price','retail_price','qty_base','low_level_base',
    'default_location_id','brand_id'
  ].forEach(i => {
    const el = document.getElementById(i);
    if (el) el.value = '';
  });

  document.getElementById('unit_type').value = 'pieces';
  document.getElementById('is_active').value = '1';
  showUnitFields();

  const delBtn = document.getElementById('btnDelete');
  if (delBtn) delBtn.style.display = 'none';

  currentProductId = null;
  productImages = [];
  displayProductImages();
}

async function openNew(){
  if(!canCreate) return;
  clearForm();
  document.getElementById('mdlTitle').textContent = "New Product";
  
  // Set a temporary ID for new products (will be replaced after save)
  currentProductId = 'new_' + Date.now();
  
  mdl.show();
}

async function openEdit(id){
  if(!canUpdate) return;

  const res = await fetch(BASE_URL + "/api/products.php?action=get&id=" + id);
  const txt = await res.text();

  let json;
  try { json = JSON.parse(txt); }
  catch(e){
    console.error('[products get] non-json:', txt);
    alert('Server returned non-JSON. See console.');
    return;
  }

  if(!json.ok){ alert(json.error || "Failed"); return; }

  const p = json.data;
  currentProductId = p.id;

  document.getElementById('mdlTitle').textContent = "Edit Product";
  document.getElementById('id').value = p.id;
  document.getElementById('name').value = p.name || '';
  document.getElementById('sku').value = p.sku || '';
  document.getElementById('description').value = p.description || '';
  document.getElementById('source').value = p.source || '';
  document.getElementById('unit_type').value = p.unit_type || 'pieces';
  document.getElementById('unit_name').value = p.unit_name || '';
  document.getElementById('pieces_per_box').value = p.pieces_per_box || '';
  document.getElementById('cost_price').value = p.cost_price || 0;
  document.getElementById('wholesale_price').value = p.wholesale_price || 0;
  document.getElementById('retail_price').value = p.retail_price || 0;
  document.getElementById('qty_base').value = p.qty_base || 0;
  document.getElementById('low_level_base').value = p.low_level_base || 0;
  document.getElementById('is_active').value = String(p.is_active ?? 1);
  document.getElementById('default_location_id').value = p.default_location_id || '';

  // ✅ BRAND
  document.getElementById('brand_id').value = p.brand_id ? String(p.brand_id) : '';

  // Load existing images
  productImages = [];
  if (p.images) {
    try {
      const images = typeof p.images === 'string' ? JSON.parse(p.images) : p.images;
      if (Array.isArray(images)) {
        productImages = images.filter(img => img && img.trim() !== '');
      }
    } catch (e) {
      console.error('Error parsing images:', e);
    }
  }
  displayProductImages();

  showUnitFields();

  const delBtn = document.getElementById('btnDelete');
  if(canDelete && delBtn) delBtn.style.display = '';

  mdl.show();
}

async function save(){
  const id = Number(document.getElementById('id').value || 0);
  const action = id ? 'update' : 'create';
  if(action==='create' && !canCreate) return;
  if(action==='update' && !canUpdate) return;

  const payload = {
    id,
    name: document.getElementById('name').value.trim(),
    sku: document.getElementById('sku').value.trim(),
    description: document.getElementById('description').value.trim(),
    source: document.getElementById('source').value.trim(),
    unit_type: document.getElementById('unit_type').value,
    unit_name: document.getElementById('unit_name').value.trim(),
    pieces_per_box: Number(document.getElementById('pieces_per_box').value || 0),
    cost_price: Number(document.getElementById('cost_price').value || 0),
    wholesale_price: Number(document.getElementById('wholesale_price').value || 0),
    retail_price: Number(document.getElementById('retail_price').value || 0),
    qty_base: Number(document.getElementById('qty_base').value || 0),
    low_level_base: Number(document.getElementById('low_level_base').value || 0),
    default_location_id: Number(document.getElementById('default_location_id').value) || 0,

    // ✅ BRAND (0 = none)
    brand_id: Number(document.getElementById('brand_id').value) || 0,

    is_active: Number(document.getElementById('is_active').value || 1),
    
    // Include images
    images: JSON.stringify(productImages)
  };

  const res = await fetch(BASE_URL + "/api/products.php?action=" + action, {
    method: "POST",
    headers: {"Content-Type":"application/json"},
    body: JSON.stringify(payload)
  });

  const txt = await res.text();
  let json;
  try { json = JSON.parse(txt); }
  catch(e){
    console.error('[products save] non-json:', txt);
    alert('Server returned non-JSON. See console.');
    return;
  }

  if(!json.ok){ alert(json.error || "Save failed"); return; }

  // Update currentProductId for new products
  if (action === 'create' && json.data?.id) {
    currentProductId = json.data.id;
  }

  mdl.hide();
  await loadProducts();
}

async function del(){
  const id = Number(document.getElementById('id').value || 0);
  if(!id || !canDelete) return;
  if(!confirm("Delete this product?")) return;

  const res = await fetch(BASE_URL + "/api/products.php?action=delete", {
    method: "POST",
    headers: {"Content-Type":"application/x-www-form-urlencoded"},
    body: "id=" + encodeURIComponent(id)
  });

  const txt = await res.text();
  let json;
  try { json = JSON.parse(txt); } catch(e){
    console.error('[products delete] non-json:', txt);
    alert('Server returned non-JSON. See console.');
    return;
  }
  if(!json.ok){ alert(json.error || "Delete failed"); return; }

  mdl.hide();
  await loadProducts();
}

document.getElementById('btnSearch').addEventListener('click', loadProducts);
document.getElementById('q').addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); loadProducts(); } });

const btnNew = document.getElementById('btnNew');
if(btnNew) btnNew.addEventListener('click', openNew);

document.getElementById('btnSave').addEventListener('click', save);

const btnDelete = document.getElementById('btnDelete');
if(btnDelete) btnDelete.addEventListener('click', del);

// Image upload handlers
document.getElementById('btnUploadImage')?.addEventListener('click', () => {
  document.getElementById('fileInput').click();
});

document.getElementById('fileInput')?.addEventListener('change', (e) => {
  handleFileUpload(e.target.files);
});

document.getElementById('btnImportUrl')?.addEventListener('click', importFromUrl);

document.getElementById('btnBulkImport')?.addEventListener('click', openBulkImport);

document.getElementById('btnStartBulkImport')?.addEventListener('click', startBulkImport);

document.getElementById('btnQrCapture')?.addEventListener('click', () => {
  openQRCapture();
});

// QR Capture Modal Event Listeners
document.getElementById('btnQRUpload')?.addEventListener('click', uploadQRImage);

document.getElementById('btnUpdateIP')?.addEventListener('click', updateServerIP);

tbody.addEventListener('click', (e)=>{
  const btn = e.target.closest('button[data-act]');
  if(!btn) return;
  if(btn.dataset.act === 'edit') openEdit(btn.dataset.id);
});

async function loadLocationsInto(selectId){
  const res = await fetch(BASE_URL + "/api/stock.php?action=locations");
  const txt = await res.text();
  let json;
  try { json = JSON.parse(txt); } catch(e){
    console.error('[locations] non-json:', txt);
    return;
  }
  if(!json.ok){ console.warn(json.error || "Failed to load locations"); return; }

  const sel = document.getElementById(selectId);
  sel.innerHTML = '<option value="">— None —</option>';
  json.data.forEach(l=>{
    const o = document.createElement('option');
    o.value = l.id;
    o.textContent = l.name;
    sel.appendChild(o);
  });
}

document.getElementById('unit_type').addEventListener('change', showUnitFields);

// Global message listener for mobile image uploads
window.addEventListener('message', function(event) {
  // Accept only same host (loose check)
  try {
    const allowedHost = new URL(BASE_URL).host;
    const incomingHost = new URL(event.origin).host;
    if (allowedHost !== incomingHost) return;
  } catch(e) {
    // If parsing fails, ignore
    return;
  }
  
  if (event.data?.type === 'productImageUploaded' && 
      (Number(event.data.product_id) === Number(currentProductId) || 
       (typeof currentProductId === 'string' && currentProductId.startsWith('new_') && event.data.product_id === 'new_product'))) {
    // Reload from server to get updated images JSON
    if (typeof currentProductId === 'string' && currentProductId.startsWith('new_')) {
      // For new products, just add to local gallery since server doesn't have the product yet
      if (event.data.image_url) {
        addImageToGallery(event.data.image_url);
      }
    } else {
      // For existing products, reload from server
      loadProductImages(currentProductId);
    }
    
    // Update status if QR modal is open
    const statusEl = document.getElementById('phoneConnectionStatus');
    if (statusEl) {
      statusEl.className = 'alert alert-success';
      statusEl.innerHTML = '<i class="bi bi-check-circle"></i> Image uploaded successfully!';
    }
    
    // Update QR message if QR modal is open
    const qrMsgEl = document.getElementById('qrMessage');
    if (qrMsgEl) {
      setQRMessage('Image added to product gallery! ✅');
    }
  }
});

// init
loadLocationsInto('default_location_id');
loadBrandsInto('brand_id');   // ✅ load brands
loadProducts();
</script>
