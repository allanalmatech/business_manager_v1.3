<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_login();
require_permission('products.update');

$BASE_URL = $GLOBALS['BASE_URL'] ?? '';
$db = $GLOBALS['db'] ?? null;

$pid  = (int)($_GET['pid'] ?? 0);
if ($pid <= 0) die('Invalid product.');

if (!($db instanceof mysqli)) die('DB unavailable.');

$stmt = $db->prepare("SELECT id, name, sku FROM products WHERE id=? LIMIT 1");
$stmt->bind_param("i", $pid);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$product) die('Product not found.');

// CSRF token for this capture session
$csrf = bin2hex(random_bytes(16));
$_SESSION['mobile_csrf'] = $csrf;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Capture Product Image</title>
  <link href="<?= h($BASE_URL) ?>/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <style>
:root{
  --primary:#0d6efd;
  --bg:#f6f7fb;
  --card:#ffffff;
  --border:#dbe2ee;
  --muted:#6c757d;
  --text:#111827;
}

*{ box-sizing:border-box; }

body{
  margin:0;
  background:var(--bg);
  font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial;
  color:var(--text);
}

.page-wrap{
  min-height:100vh;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:16px;
}

.capture-card{
  width:100%;
  max-width:560px;
  background:var(--card);
  border-radius:22px;
  box-shadow:0 10px 30px rgba(17,24,39,.08);
  overflow:hidden;
}

.capture-body{
  padding:22px;
}

.title{
  font-weight:700;
  margin:0;
  font-size:1.1rem;
}

.sub{
  margin:6px 0 14px 0;
  color:var(--muted);
  font-size:.95rem;
}

.tip{
  background:#eef6ff;
  border:1px solid #d6e9ff;
  border-radius:14px;
  padding:10px 12px;
  font-size:.92rem;
  margin-bottom:14px;
}

.preview-wrap{
  width:100%;
  height:280px;
  border:2px dashed var(--border);
  border-radius:16px;
  background:#fff;
  display:flex;
  align-items:center;
  justify-content:center;
  overflow:hidden;
  margin-bottom:16px;
}

.preview-wrap img{
  width:100%;
  height:100%;
  object-fit:contain;
}

.preview-placeholder{
  color:#94a3b8;
  font-size:.95rem;
}

.actions{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:12px;
  margin-bottom:12px;
}

.btn-action{
  display:flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  padding:14px 14px;
  border-radius:16px;
  font-weight:600;
  border:1px solid var(--border);
  background:#fff;
  color:var(--text);
  cursor:pointer;
  user-select:none;
  text-decoration:none;
}

.btn-action.primary{
  background:var(--primary);
  border-color:var(--primary);
  color:#fff;
}

.btn-action:active{
  transform:translateY(1px);
}

.btn-upload{
  width:100%;
  padding:14px 16px;
  border-radius:16px;
  font-weight:700;
  border:0;
  background:#16a34a;
  color:#fff;
  cursor:pointer;
}

.btn-upload[disabled]{
  opacity:.55;
  cursor:not-allowed;
}

.note{
  margin-top:12px;
  color:var(--muted);
  font-size:.82rem;
  text-align:center;
}

#msg{
  margin-top:10px;
  text-align:center;
  font-size:.9rem;
  color:var(--muted);
}

@media (max-width:480px){
  .preview-wrap{ height:240px; }
  .actions{ grid-template-columns:1fr; }
}
</style>

</head>
<body>
  <div class="page-wrap">
    <div class="capture-card">
      <div class="capture-body">

        <h5 class="title">Add Product Image</h5>
        <div class="sub">
          <?= h($product['name']) ?> (<?= h($product['sku']) ?>)
        </div>

        <div class="tip">
          <b>Tip:</b> Tap <b>Open Camera</b>. If camera doesn't open, use <b>Choose from Gallery</b>.
        </div>

        <div class="preview-wrap" id="previewWrap">
          <div class="preview-placeholder">No image selected</div>
        </div>

        <!-- Buttons (NO OVERLAP) -->
        <div class="actions">
          <label class="btn-action primary">
            Open Camera
            <input id="cameraInput" type="file" accept="image/*" capture="environment" hidden>
          </label>

          <label class="btn-action">
            Choose from Gallery
            <input id="galleryInput" type="file" accept="image/*" hidden>
          </label>
        </div>

        <button class="btn-upload" id="btnUpload" disabled>Upload</button>
        <div id="msg"></div>

        <div class="note">
          If you're on iPhone: camera capture may open inside Safari only after allowing camera permission.
        </div>

      </div>
    </div>
  </div>
</body>

<!-- Bootstrap Icons (optional; remove if you already load icons globally) -->
<link rel="stylesheet" href="<?= h($BASE_URL) ?>/assets/vendor/bootstrap-icons/bootstrap-icons.css">

<script>
const BASE_URL = <?= json_encode($BASE_URL) ?>;
const PRODUCT_ID = <?= (int)$pid ?>;
const CSRF = <?= json_encode($csrf) ?>;

let pickedFile = null;

const previewWrap = document.getElementById('previewWrap');
const cameraInput = document.getElementById('cameraInput');
const galleryInput = document.getElementById('galleryInput');
const btnUpload = document.getElementById('btnUpload');
const msg = document.getElementById('msg');

function setMessage(t, isError=false){
  msg.textContent = t || '';
  msg.className = 'text-center font-size:.9rem color:var(--muted) margin-top:10px';
}

function handlePick(file){
  if(!file) return;

  if(!file.type.startsWith('image/')){
    setMessage('Please select an image file.', true);
    return;
  }

  if(file.size > 6 * 1024 * 1024){
    setMessage('Image too large. Max 6MB.', true);
    return;
  }

  pickedFile = file;

  previewWrap.innerHTML = '';
  const img = document.createElement('img');
  img.src = URL.createObjectURL(file);
  img.className = 'preview-wrap img';
  previewWrap.appendChild(img);

  btnUpload.disabled = false;
  setMessage('Ready: ' + file.name);
}

// If camera fails to open, user taps gallery button (manual fallback)
cameraInput.addEventListener('change', e => handlePick(e.target.files[0]));
galleryInput.addEventListener('change', e => handlePick(e.target.files[0]));

// Upload
btnUpload.addEventListener('click', async () => {
  if(!pickedFile) return;

  btnUpload.disabled = true;
  setMessage('Uploading...');

  const fd = new FormData();
  fd.append('product_id', PRODUCT_ID);
  fd.append('csrf', CSRF);
  fd.append('file', pickedFile);

  try{
    const res = await fetch(BASE_URL + '/api/product_images.php?action=upload', {
      method: 'POST',
      body: fd
    });

    const txt = await res.text();
    let j;
    try { j = JSON.parse(txt); }
    catch(e){
      console.error('Non-JSON:', txt);
      setMessage('Server returned non-JSON. Check console.', true);
      btnUpload.disabled = false;
      return;
    }

    if(!j.ok){
      setMessage(j.error || 'Upload failed', true);
      btnUpload.disabled = false;
      return;
    }

    setMessage('Upload successful ✅');

    // Notify opener (desktop product modal)
    try{
      window.opener?.postMessage({
        type:'productImageUploaded', 
        product_id: PRODUCT_ID,
        image_url: j.data?.url || null
      }, '*');
    }catch(e){}

    // Reset for next capture
    pickedFile = null;
    cameraInput.value = '';
    galleryInput.value = '';
    btnUpload.disabled = true;

  }catch(err){
    console.error(err);
    setMessage('Network error uploading image.', true);
    btnUpload.disabled = false;
  }
});
</script>
</body>
</html>
