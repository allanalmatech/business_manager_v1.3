<?php
// modules/products/phone_scan.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_login();
require_permission('products.update');

$BASE_URL = $GLOBALS['BASE_URL'] ?? '';
$db = $GLOBALS['db'] ?? null;

$session = (string)($_GET['session'] ?? '');
$productId = (int)($_GET['product_id'] ?? 0);

if (empty($session)) die('Invalid session');
if (!($db instanceof mysqli)) die('DB unavailable');

// Store session data
$stmt = $db->prepare("INSERT INTO phone_scan_sessions (session_id, product_id, status, created_at) VALUES (?, ?, 'created', NOW()) ON DUPLICATE KEY UPDATE status = 'created', updated_at = NOW()");
$stmt->bind_param("si", $session, $productId);
$stmt->execute();
$stmt->close();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Phone QR Scanner</title>
  <link href="<?= h($BASE_URL) ?>/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f6f7fb;}
    .card{border:0;border-radius:18px;}
    .preview{
      width:100%; max-height:40vh; object-fit:contain;
      background:#fff; border:1px dashed #cfd4da; border-radius:12px;
    }
    .muted{color:#6c757d;}
  </style>
</head>
<body class="py-3">
<div class="container" style="max-width:720px;">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h5 class="mb-1">Phone QR Scanner</h5>
      <div class="muted mb-3">Session: <?= h($session) ?></div>

      <div class="alert alert-info py-2 small">
        <b>Instructions:</b> Scan a QR code containing an image URL using your phone camera.
      </div>

      <img id="preview" class="preview mb-3" alt="Preview">

      <!-- QR SCANNER MODE -->
      <div class="text-center mb-3">
        <div id="qrReader" style="width:100%; height:250px; background:#000; border-radius:12px; position:relative;">
          <video id="qrVideo" style="width:100%; height:100%; object-fit:cover; border-radius:12px;"></video>
          <div id="qrOverlay" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); text-align:center; color:white;">
            <div class="spinner-border text-light mb-2"></div>
            <div>Initializing QR scanner...</div>
          </div>
        </div>
        <div class="mt-2">
          <button class="btn btn-secondary btn-sm" id="btnStopQR">Stop Scanner</button>
          <button class="btn btn-primary btn-sm ms-2" id="btnStartQR">Start Scanner</button>
        </div>
      </div>
      <div id="qrResult" class="alert alert-success" style="display:none;">
        <strong>QR Code Detected!</strong><br>
        <span id="qrUrl"></span><br>
        <button class="btn btn-sm btn-primary mt-2" id="btnUseQRImage">Use This Image</button>
      </div>

      <!-- UPLOAD BUTTON -->
      <div class="d-grid gap-2 mt-3">
        <button class="btn btn-success" id="btnUpload" disabled>
          Send to PC
        </button>

        <div id="msg" class="small mt-2 muted"></div>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL = <?= json_encode($BASE_URL) ?>;
const SESSION_ID = <?= json_encode($session) ?>;
const PRODUCT_ID = <?= (int)$productId ?>;
const CSRF = <?= json_encode($_SESSION['csrf'] ?? '') ?>;

let pickedFile = null;
let qrScannerActive = false;
let qrStream = null;

const preview = document.getElementById('preview');
const btnUpload = document.getElementById('btnUpload');
const msg = document.getElementById('msg');

function setMessage(t, isError=false){
  msg.textContent = t || '';
  msg.className = 'small mt-2 ' + (isError ? 'text-danger' : 'muted');
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
  preview.src = URL.createObjectURL(file);
  btnUpload.disabled = false;
  setMessage('Ready to send: ' + file.name);
}

// QR Scanner Functions
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
    
    // Update status
    updateStatus('scanning');
    
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
  // Placeholder for QR detection
  return new Promise((resolve) => {
    setTimeout(() => {
      const simulatedQRs = [
        'https://picsum.photos/seed/phone1/400/400.jpg',
        'https://picsum.photos/seed/phone2/400/400.jpg',
        'https://picsum.photos/seed/phone3/400/400.jpg'
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
  
  loadImageFromURL(code);
}

async function loadImageFromURL(url) {
  try {
    setMessage('Loading image from QR code...');
    updateStatus('found');
    
    const response = await fetch(url);
    if (!response.ok) throw new Error('Failed to fetch image');
    
    const blob = await response.blob();
    if (!blob.type.startsWith('image/')) throw new Error('URL does not contain an image');
    
    const filename = 'qr_image_' + Date.now() + '.jpg';
    const file = new File([blob], filename, { type: blob.type });
    
    handlePick(file);
    setMessage('Image loaded from QR code successfully!');
    
  } catch (error) {
    setMessage('Failed to load image from QR code: ' + error.message, true);
  }
}

async function uploadToPC() {
  if (!pickedFile) return;
  
  btnUpload.disabled = true;
  setMessage('Sending to PC...');
  
  const formData = new FormData();
  formData.append('session', SESSION_ID);
  formData.append('file', pickedFile);
  
  try {
    const response = await fetch(BASE_URL + '/api/phone_scan.php?action=upload', {
      method: 'POST',
      body: formData
    });
    
    const text = await response.text();
    let json;
    try { 
      json = JSON.parse(text); 
    } catch(e) {
      console.error('Non-JSON:', text);
      setMessage('Server returned non-JSON. Check console.', true);
      btnUpload.disabled = false;
      return;
    }
    
    if (!json.ok) {
      setMessage(json.error || 'Upload failed', true);
      btnUpload.disabled = false;
      return;
    }
    
    setMessage('Image sent to PC successfully! ✅');
    
    // Update status
    updateStatus('uploaded');
    
    // Reset after success
    setTimeout(() => {
      pickedFile = null;
      preview.src = '';
      btnUpload.disabled = true;
      setMessage('Ready to scan next QR code...');
    }, 2000);
    
  } catch (err) {
    console.error(err);
    setMessage('Network error sending image.', true);
    btnUpload.disabled = false;
  }
}

function updateStatus(status) {
  fetch(BASE_URL + '/api/phone_scan.php?action=update_status', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ session: SESSION_ID, status: status })
  }).catch(console.error);
}

// Event Listeners
document.getElementById('btnStartQR')?.addEventListener('click', startQRScanner);
document.getElementById('btnStopQR')?.addEventListener('click', stopQRScanner);
document.getElementById('btnUseQRImage')?.addEventListener('click', () => {
  // Switch to show loaded image
  document.getElementById('qrResult').style.display = 'none';
});
document.getElementById('btnUpload')?.addEventListener('click', uploadToPC);

// Initialize
updateStatus('connected');
</script>
</body>
</html>
