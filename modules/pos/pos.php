<?php
// modules/pos/pos.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_permission('pos.create');

$db = $GLOBALS['db'] ?? null;
$BASE_URL = rtrim((string)($GLOBALS['BASE_URL'] ?? ''), '/');

$page_title = "POS";
$page_subtitle = "Fast sales • Live search • Quick items • Payments • Print";

if (!$db instanceof mysqli) {
  http_response_code(500);
  die("DB not available");
}


// CSRF
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

// Locations
$locations = [];
$res = $db->query("SELECT id, name FROM locations WHERE is_active=1 ORDER BY name ASC");
if ($res) {
  while ($row = $res->fetch_assoc()) $locations[] = $row;
  $res->free();
}
$default_location_id = $locations[0]['id'] ?? '';
foreach ($locations as $loc) {
  if (mb_strtolower((string)$loc['name']) === 'counter') {
    $default_location_id = $loc['id'];
    break;
  }
}

// Light customers
$customers = [];
$customerLightLimit = 150;
$cq = $db->prepare("SELECT id, name, phone, category_id FROM customers WHERE is_active=1 ORDER BY name ASC LIMIT ?");
if ($cq) {
  $cq->bind_param('i', $customerLightLimit);
  $cq->execute();
  $cr = $cq->get_result();
  if ($cr) {
    while ($r = $cr->fetch_assoc()) $customers[] = $r;
    $cr->free();
  }
  $cq->close();
}

// Permissions
$can_discount  = user_has_permission('pos.apply_discount');
$can_editprice = user_has_permission('pos.edit_price');
$can_invoice   = user_has_permission('pos.invoice');
$can_dn        = user_has_permission('pos.delivery_note');
$can_debt      = user_has_permission('pos.allow_debt');

require_once __DIR__ . '/../../templates/layout/header.php';
?>

<div class="app-shell" id="posAppShell">
  <?php require_once __DIR__ . '/../../templates/layout/sidebar.php'; ?>

  <div class="app-content">
    <?php require_once __DIR__ . '/../../templates/layout/topbar.php'; ?>

    <main class="page-wrap">

<link rel="stylesheet" href="<?= h($BASE_URL) ?>/assets/css/pos.css?v=3">

<div class="pos-shell">

  <!-- LEFT -->
  <section class="pos-left">

    <div class="pos-topbar">
      <div class="pos-tabs-modern" id="posCategories">
        <button type="button" class="pos-tab-modern active" data-cat="">
          <i class="bi bi-grid-3x3-gap"></i> All
        </button>
        <button type="button" class="pos-tab-modern" data-cat="popular">
          <i class="bi bi-star"></i> Popular
        </button>
        <?php
        // Load categories from database
        $categories_query = $db->query("SELECT id, name FROM product_categories WHERE is_active=1 ORDER BY name ASC LIMIT 8");
        if ($categories_query && $categories_query->num_rows > 0):
          while ($category = $categories_query->fetch_assoc()):
            // Get appropriate icon based on category name
            $icon = 'bi-box'; // default icon
            $categoryName = strtolower($category['name']);
            
            // Map category names to appropriate Bootstrap Icons
            if (strpos($categoryName, 'food') !== false || strpos($categoryName, 'drink') !== false || strpos($categoryName, 'beverage') !== false) {
              $icon = 'bi-cup-hot';
            } elseif (strpos($categoryName, 'cloth') !== false || strpos($categoryName, 'fashion') !== false || strpos($categoryName, 'wear') !== false) {
              $icon = 'bi-bag';
            } elseif (strpos($categoryName, 'electronic') !== false || strpos($categoryName, 'tech') !== false || strpos($categoryName, 'gadget') !== false) {
              $icon = 'bi-cpu';
            } elseif (strpos($categoryName, 'phone') !== false || strpos($categoryName, 'mobile') !== false) {
              $icon = 'bi-phone';
            } elseif (strpos($categoryName, 'book') !== false || strpos($categoryName, 'paper') !== false) {
              $icon = 'bi-book';
            } elseif (strpos($categoryName, 'home') !== false || strpos($categoryName, 'furniture') !== false) {
              $icon = 'bi-house';
            } elseif (strpos($categoryName, 'toy') !== false || strpos($categoryName, 'game') !== false) {
              $icon = 'bi-controller';
            } elseif (strpos($categoryName, 'health') !== false || strpos($categoryName, 'medicine') !== false) {
              $icon = 'bi-heart-pulse';
            } elseif (strpos($categoryName, 'sport') !== false || strpos($categoryName, 'fitness') !== false) {
              $icon = 'bi-trophy';
            } elseif (strpos($categoryName, 'beauty') !== false || strpos($categoryName, 'cosmetic') !== false) {
              $icon = 'bi-star';
            } elseif (strpos($categoryName, 'clean') !== false || strpos($categoryName, 'detergent') !== false) {
              $icon = 'bi-droplet';
            } elseif (strpos($categoryName, 'car') !== false || strpos($categoryName, 'auto') !== false) {
              $icon = 'bi-car-front';
            } elseif (strpos($categoryName, 'tool') !== false || strpos($categoryName, 'hardware') !== false) {
              $icon = 'bi-wrench';
            }
            
            // Check if category name is short enough for icon + text, otherwise use angle brackets
            $categoryLength = strlen($category['name']);
            $useAngleBrackets = $categoryLength > 8; // Use brackets for longer names
        ?>
          <button type="button" class="pos-tab-modern" data-cat="<?= htmlspecialchars($category['id']) ?>">
            <?php if ($useAngleBrackets): ?>
              <<?= htmlspecialchars($icon) ?>> <?= htmlspecialchars($category['name']) ?>
            <?php else: ?>
              <i class="bi <?= htmlspecialchars($icon) ?>"></i> <?= htmlspecialchars($category['name']) ?>
            <?php endif; ?>
          </button>
        <?php 
          endwhile; 
        endif; 
        ?>
      </div>

      <div class="pos-search">
        <i class="bi bi-search"></i>
        <input type="text" id="product_search" placeholder="Search SKU, name, barcode…" autocomplete="off">
      </div>

      <div class="pos-header-actions">
        <select class="form-select form-select-sm" id="selling_location_id" style="width: auto;">
          <?php foreach ($locations as $loc): ?>
            <option value="<?= h($loc['id']) ?>" <?= ((string)$loc['id'] === (string)$default_location_id) ? 'selected' : '' ?>>
              <?= h($loc['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        
        <button type="button" class="fullscreen-btn" id="btnToggleFullscreen" title="Toggle Fullscreen">
          <i class="bi bi-arrows-fullscreen"></i>
        </button>
      </div>
    </div>

    <div id="searchResultsWrap" class="pos-suggest d-none">
      <div class="pos-suggest-head">
        <div class="fw-semibold">Suggestions</div>
        <button class="btn btn-sm btn-light" type="button" id="btnHideResults">Hide</button>
      </div>
      <div id="searchResults" class="list-group"></div>
    </div>

    <div class="pos-products">
      <div class="pos-products-head">
        <div>
          <div class="pos-h1">Products</div>
          <div class="pos-sub">Tap items to add to cart</div>
        </div>

        <div class="pos-actions">
          <div class="btn-group btn-group-sm" role="group">
            <input type="radio" class="btn-check" name="pricing_mode" id="pm_retail" value="retail" checked>
            <label class="btn btn-outline-primary" for="pm_retail">Retail</label>

            <input type="radio" class="btn-check" name="pricing_mode" id="pm_wholesale" value="wholesale">
            <label class="btn btn-outline-primary" for="pm_wholesale">Wholesale</label>
          </div>

          <button class="btn btn-outline-secondary btn-sm" id="btnNewSale" type="button">
            <i class="bi bi-arrow-clockwise"></i> Clear
          </button>
        </div>
      </div>

      <div id="quickItems" class="pos-grid touch-grid"></div>
    </div>

  </section>

  <!-- RIGHT -->
  <aside class="pos-right">

    <div class="pos-cart-head">
      <div>
        <div class="pos-h2">Current Sale</div>
        <div id="cartCount" class="pos-sub">0 items</div>
      </div>

      <select class="form-select form-select-sm" id="doc_type" style="width: auto;">
        <option value="receipt">Receipt</option>
        <?php if ($can_invoice): ?><option value="invoice">Invoice</option><?php endif; ?>
        <?php if ($can_dn): ?><option value="delivery_note">Delivery Note</option><?php endif; ?>
      </select>
    </div>

    <div id="cartPanel" class="pos-cart-list">
      <div id="cartEmptyRow" class="pos-empty">
        <i class="bi bi-cart-x d-block mb-2" style="font-size: 2rem; opacity: 0.3;"></i>
        Cart is empty
      </div>
    </div>

    <div class="pos-summary">
      <div class="pos-row">
        <span class="muted">Subtotal</span>
        <span class="fw-semibold" id="t_subtotal">0</span>
      </div>
      <div class="pos-row">
        <span class="muted">Discount</span>
        <span class="fw-semibold" id="t_discount">0</span>
      </div>
      <div class="pos-divider"></div>
      <div class="pos-row">
        <span class="muted">Grand Total</span>
        <span class="pos-grand text-primary" id="t_grand">0</span>
      </div>

      <div class="pos-divider"></div>

      <div class="row g-2 mb-3">
        <div class="col-12">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
            <select class="form-select" id="customer_id">
              <option value="">Walk-in Customer</option>
              <?php foreach ($customers as $c): ?>
                <option value="<?= h($c['id']) ?>"><?= h($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="pos-paybox">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-bold">Payment</div>
          <div class="small text-primary fw-bold" id="t_balance_display">Balance: 0</div>
        </div>

        <div class="row g-2 mb-2">
          <div class="col-6">
            <select class="form-select form-select-sm" id="pay_method">
              <option value="cash">Cash</option>
              <option value="mobile_money">Mobile Money</option>
              <option value="bank">Bank Transfer</option>
            </select>
          </div>
          <div class="col-6">
            <input type="number" class="form-control form-control-sm fw-bold text-end" id="pay_amount" placeholder="0.00">
          </div>
        </div>

        <div class="payment-shortcuts">
          <button type="button" class="btn btn-outline-secondary btn-shortcut" data-amt="exact">EXACT</button>
          <button type="button" class="btn btn-outline-secondary btn-shortcut" data-amt="100">+100</button>
          <button type="button" class="btn btn-outline-secondary btn-shortcut" data-amt="500">+500</button>
          <button type="button" class="btn btn-outline-secondary btn-shortcut" data-amt="5000">+5,000</button>
          <button type="button" class="btn btn-outline-secondary btn-shortcut" data-amt="50000">+50,000</button>
          <button type="button" class="btn btn-primary btn-shortcut" id="btnAddPaymentRow">ADD</button>
        </div>

        <div class="pos-paytable mt-3" style="max-height: 100px;">
          <table class="table table-sm table-borderless mb-0">
            <tbody id="paymentsBody">
              <tr id="paymentsEmptyRow">
                <td colspan="3" class="text-center text-muted small py-2">No payments</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="pos-cta mt-3">
        <button type="button" class="btn btn-sm btn-outline-info w-100 mb-2 py-2 fw-bold" id="btnOpenB2B">
          <i class="bi bi-plus-circle me-1"></i> ADD B2B ITEM TO CART
        </button>
        
        <button class="btn btn-success btn-lg w-100 py-3 fw-bold shadow-sm" type="button" id="btnConfirm">
          <i class="bi bi-check2-circle me-2"></i> COMPLETE SALE
        </button>
      </div>

    </div>
  </aside>

</div>

<script>
  window.POS_CONFIG = {
    baseUrl: "<?= h($BASE_URL) ?>",
    apiUrl: "<?= h($BASE_URL) ?>/modules/pos/pos_api.php",
    csrf: "<?= h($csrf) ?>",
    perms: {
      discount: <?= $can_discount ? 'true' : 'false' ?>,
      editPrice: <?= $can_editprice ? 'true' : 'false' ?>,
      debt: <?= $can_debt ? 'true' : 'false' ?>
    }
  };
</script>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold">Checkout Summary</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body" id="previewModalBody">
        <div class="text-center py-4">
          <div class="spinner-border text-primary" role="status"></div>
          <div class="mt-2 text-muted">Preparing receipt...</div>
        </div>
      </div>

      <div class="modal-footer border-0">
        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Edit Sale</button>
        <button type="button" class="btn btn-success px-5 fw-bold" id="btnConfirmFromPreview">
          FINALIZE & PRINT
        </button>
      </div>
    </div>
  </div>
</div>

<!-- B2B Modal -->
<div class="modal fade" id="mdlB2B" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Add B2B / External Item</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="row g-2">
          <div class="col-md-8">
            <label class="form-label">Item Name *</label>
            <input class="form-control" id="b2b_name" placeholder="e.g. Samsung Charger 45W">
          </div>

          <div class="col-md-4">
            <label class="form-label">SKU (optional)</label>
            <input class="form-control" id="b2b_sku" placeholder="barcode/SKU">
          </div>

          <div class="col-md-4">
            <label class="form-label">Quantity *</label>
            <input class="form-control" id="b2b_qty" type="number" min="0" step="0.01" value="1">
          </div>

          <div class="col-md-4">
            <label class="form-label">Unit Type *</label>
            <select class="form-select" id="b2b_unit_type">
              <option value="pieces">Pieces</option>
              <option value="boxes">Boxes</option>
              <option value="dozens">Dozens</option>
              <option value="pairs">Pairs</option>
              <option value="units">Units (kg/litre/etc)</option>
            </select>
          </div>

          <div class="col-md-4" id="b2b_unit_name_wrap" style="display:none;">
            <label class="form-label">Unit Name</label>
            <input class="form-control" id="b2b_unit_name" placeholder="e.g. kg">
          </div>

          <div class="col-md-4">
            <label class="form-label">Cost Price *</label>
            <input class="form-control" id="b2b_cost" type="number" min="0" step="0.01" placeholder="e.g. 80000">
          </div>

          <div class="col-md-4">
            <label class="form-label">Sell Price *</label>
            <input class="form-control" id="b2b_sell" type="number" min="0" step="0.01" placeholder="e.g. 100000">
            <div class="form-text">Default rule: sell must be ≥ cost (we enforce in JS).</div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Currency</label>
            <select class="form-select" id="b2b_currency">
              <option value="UGX">UGX</option>
              <option value="USD">USD</option>
              <option value="CNY">Yuan (CNY)</option>
              <option value="KES">KES</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Exchange Rate → UGX</label>
            <input class="form-control" id="b2b_rate" type="number" min="0" step="0.000001" value="1">
            <div class="form-text">If UGX, leave 1.</div>
          </div>

          <div class="col-md-8">
            <label class="form-label">Supplier (optional)</label>
            <input class="form-control" id="b2b_supplier_text" placeholder="e.g. Kikuubo Shop A">
            <!-- Later we will replace this with a supplier dropdown from Contacts -->
          </div>

          <div class="col-12">
            <label class="form-label">Note (optional)</label>
            <input class="form-control" id="b2b_note" placeholder="e.g. bought from another shop for customer request">
          </div>

          <div class="col-12">
            <div class="small" id="b2b_msg"></div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" id="btnAddB2BLine">Add Item</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= h($BASE_URL) ?>/assets/js/pos.js?v=3"></script>

    </main>
  </div>
</div>

<?php require_once __DIR__ . '/../../templates/layout/footer.php'; ?>

<script>
/**
 * B2B lines live in memory until checkout.
 * On checkout, we will send window.b2bLines in the API payload.
 */
window.b2bLines = window.b2bLines || [];

// Wait for DOM to be ready and pos.js to be loaded
document.addEventListener('DOMContentLoaded', function() {
  // Initialize B2B modal after Bootstrap and pos.js are loaded
  setTimeout(function() {
    if (typeof bootstrap !== 'undefined') {
      const mdlB2B = new bootstrap.Modal(document.getElementById('mdlB2B'));
      
      document.getElementById('btnOpenB2B').addEventListener('click', ()=>{
        setB2BMsg('');
        document.getElementById('b2b_name').value = '';
        document.getElementById('b2b_sku').value = '';
        document.getElementById('b2b_qty').value = 1;
        document.getElementById('b2b_unit_type').value = 'pieces';
        document.getElementById('b2b_unit_name').value = '';
        document.getElementById('b2b_cost').value = '';
        document.getElementById('b2b_sell').value = '';
        document.getElementById('b2b_currency').value = 'UGX';
        document.getElementById('b2b_rate').value = 1;
        document.getElementById('b2b_supplier_text').value = '';
        document.getElementById('b2b_note').value = '';
        
        mdlB2B.show();
      });

      document.getElementById('b2b_unit_type').addEventListener('change', toggleUnitName);
      
      document.getElementById('btnAddB2BLine').addEventListener('click', ()=>{
        const name = document.getElementById('b2b_name').value.trim();
        const sku = document.getElementById('b2b_sku').value.trim() || null;
        const qty = Number(document.getElementById('b2b_qty').value || 0);
        const unit_type = document.getElementById('b2b_unit_type').value;
        const unit_name = (unit_type === 'units') ? (document.getElementById('b2b_unit_name').value.trim() || null) : null;

        const cost = Number(document.getElementById('b2b_cost').value || 0);
        const sell = Number(document.getElementById('b2b_sell').value || 0);

        const currency = document.getElementById('b2b_currency').value.trim() || 'UGX';
        const rate = Number(document.getElementById('b2b_rate').value || 1);

        const supplier_name = document.getElementById('b2b_supplier_text').value.trim() || null;
        const note = document.getElementById('b2b_note').value.trim() || null;

        // validation
        if(!name){ return setB2BMsg('Item name is required.', true); }
        if(qty <= 0){ return setB2BMsg('Quantity must be greater than 0.', true); }
        if(cost <= 0){ return setB2BMsg('Cost price must be greater than 0.', true); }
        if(sell <= 0){ return setB2BMsg('Sell price must be greater than 0.', true); }
        if(sell < cost){ return setB2BMsg('Sell price cannot be below cost (admin override comes later).', true); }
        if(!rate || rate <= 0){ return setB2BMsg('Exchange rate must be > 0.', true); }

        const line = {
          tmp_id: cryptoRandomId(),
          name, sku,
          qty,
          unit_type,
          unit_name,
          cost_price: cost,
          sell_price: sell,
          currency,
          exchange_rate: rate,
          supplier_id: null,
          supplier_name: supplier_name,
          note
        };

        // Add to main cart using global addToCart function
        if (typeof window.addToCart === 'function') {
          window.addToCart({
            is_b2b: true,
            tmp_id: line.tmp_id,
            name: line.name,
            sku: line.sku,
            qty: line.qty,
            unit_price: line.sell_price,
            b2b_data: line
          });
          //console.log('B2B item added to cart successfully'); // Debug line
        } else {
          // Fallback if addToCart not available
          window.b2bLines = window.b2bLines || [];
          window.b2bLines.push(line);
          renderB2BLines();
        }
        mdlB2B.hide();
      });
    }
  }, 100); // Small delay to ensure all scripts are loaded
});

function renderB2BLines(){
  // Fallback rendering for B2B items if addToCart is not available
  if (!window.b2bLines || window.b2bLines.length === 0) return;
  
  // Use the new POS_CART API if available
  if (window.POS_CART && typeof window.POS_CART.add === 'function') {
    window.b2bLines.forEach(function(b2bItem) {
      window.POS_CART.add({
        is_b2b: true,
        tmp_id: b2bItem.tmp_id,
        name: b2bItem.name,
        sku: b2bItem.sku || '',
        qty: b2bItem.qty,
        unit_price: b2bItem.sell_price,
        b2b_data: b2bItem
      });
    });
  //  console.log('B2B items added via POS_CART API');
  } else {
    // Fallback to direct cart manipulation
   // console.warn('POS_CART API not available, using fallback');
    window.b2bLines.forEach(function(b2bItem) {
      // Create a mock addToCart call
      if (typeof window.posCart === 'undefined') {
        window.posCart = window.posCart || [];
      }
      // Check if item already exists
      const existingIndex = window.posCart.findIndex(item => 
        item.is_b2b && item.tmp_id === b2bItem.tmp_id
      );
      
      if (existingIndex === -1) {
        window.posCart.push({
          _key: `b2b:${b2bItem.tmp_id}`,
          product_id: null,
          name: b2bItem.name,
          sku: b2bItem.sku || '',
          thumbnail: '',
          qty: b2bItem.qty,
          unit_price: b2bItem.sell_price,
          min_price: null,
          discount: 0,
          is_external: false,
          is_b2b: true,
          b2b_data: b2bItem,
          meta: {},
          stock_hint: ''
        });
      }
    });
    
    // Trigger cart render if the function exists
    if (typeof window.renderCart === 'function') {
      window.renderCart();
    }
  }
}

function toggleUnitName(){
  const t = document.getElementById('b2b_unit_type').value;
  document.getElementById('b2b_unit_name_wrap').style.display = (t === 'units') ? '' : 'none';
}



function setB2BMsg(msg, isErr=false){
  const el = document.getElementById('b2b_msg');
  el.textContent = msg || '';
  el.className = 'small ' + (isErr ? 'text-danger' : 'text-muted');
}

function fmt(n){
  const x = Number(n || 0);
  return x % 1 === 0 ? String(x) : x.toFixed(2);
}
function fmtMoney(n){
  const x = Number(n || 0);
  return numberWithCommas((x % 1 === 0) ? x.toFixed(0) : x.toFixed(2));
}
function numberWithCommas(x){
  return String(x).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
function escapeHtml(s){
  return String(s ?? '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}
function cryptoRandomId(){
  // simple unique id for UI operations
  return 'b2b_' + Math.random().toString(16).slice(2) + '_' + Date.now();
}

// Call this once on POS load if you want
renderB2BLines();
</script>
