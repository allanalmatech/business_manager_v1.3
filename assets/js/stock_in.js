// assets/js/stock_in.js
(() => {
  const api = (action) => `${window.APP.BASE_URL}/api/stock.php?action=${action}`;

  const el = (id) => document.getElementById(id);

  const form = el("stockInForm");
  const locationId = el("locationId");
  const productId = el("productId");
  const unitType = el("unitType");
  const qtyChange = el("qtyChange");
  const unitHint = el("unitHint");
  const unitPrice = el("unitPrice");
  const note = el("note");
  const btnSave = el("btnSave");
  const formError = el("formError");
  const formSuccess = el("formSuccess");
  const currentStock = el("currentStock");

  let products = [];
  let locations = [];

  function showElError(id, msg) {
    const box = el(id);
    if (!box) return;
    box.textContent = msg || "";
    box.classList.toggle("d-none", !msg);
  }

  async function loadLocations() {
    try {
      const res = await fetch(`${window.APP.BASE_URL}/api/stock.php?action=locations`);
      const j = await res.json();
      if (!j.ok) {
        console.error('Failed to load locations:', j);
        showElError("formError", "Failed to load locations: " + (j.error || "Unknown error"));
        return;
      }
      
      locations = j.data || [];
      locationId.innerHTML = '<option value="">-- Select Location --</option>';
      locations.forEach((loc) => {
        const opt = document.createElement("option");
        opt.value = loc.id;
        opt.textContent = loc.name;
        locationId.appendChild(opt);
      });
      
      console.log('DEBUG: Locations loaded:', locations.length, 'locations');
    } catch (error) {
      console.error('Failed to load locations:', error);
      showElError("formError", "Failed to load locations: " + error.message);
    }
  }

  async function loadProducts() {
    try {
      console.log('DEBUG: Loading products from API...');
      const apiUrl = `${window.APP.BASE_URL}/api/products.php?action=list&limit=500`;
      console.log('DEBUG: API URL:', apiUrl);
      
      const res = await fetch(apiUrl);
      console.log('DEBUG: Response status:', res.status);
      
      const j = await res.json();
      console.log('DEBUG: API response:', j);
      
      if (!j.ok) {
        console.error('DEBUG: API returned not ok:', j);
        showElError("formError", j.error || "Failed to load products");
        return;
      }
      
      products = j.data || []; // API returns products directly in data array
      console.log('DEBUG: Products loaded:', products.length, 'items');

      productId.innerHTML = '<option value="">-- Select Product --</option>';
      products.forEach((p) => {
        const opt = document.createElement("option");
        opt.value = p.id;
        opt.textContent = `${p.sku || ""} ${p.name || ""}`.trim();
        productId.appendChild(opt);
      });
      
      console.log('DEBUG: Dropdown populated with', products.length, 'options');
    } catch (error) {
      console.error('DEBUG: Failed to load products:', error);
      showElError("formError", "Failed to load products: " + error.message);
    }
  }

  function updateUnitHint() {
    const pid = productId.value;
    const selectedUnitType = unitType.value;
    const product = products.find(p => String(p.id) === pid);
    
    if (!product) {
      unitHint.textContent = "Select a product first";
      return;
    }

    let hint = "";
    if (selectedUnitType === 'boxes') {
      const pcsPerBox = product.pieces_per_box || 0;
      hint = `1 box = ${pcsPerBox} pieces`;
    } else if (selectedUnitType === 'pieces') {
      const pcsPerBox = product.pieces_per_box || 0;
      if (pcsPerBox > 0) {
        hint = `${pcsPerBox} pieces = 1 box`;
      } else {
        hint = "Individual pieces";
      }
    } else {
      hint = "Standard units";
    }
    
    unitHint.textContent = hint;
  }

  async function updateCurrentStock() {
    const pid = productId.value;
    
    if (!pid) {
      currentStock.textContent = "Select a product to see current quantity.";
      return;
    }
    
    try {
      // Fetch stock for all locations for this product
      const res = await fetch(`${window.APP.BASE_URL}/api/stock.php?action=stock_locations&product_id=${pid}`);
      const j = await res.json();
      
      if (!j.ok) {
        console.error('Failed to fetch stock:', j);
        currentStock.textContent = "Failed to fetch stock information.";
        return;
      }
      
      const stockData = j.data || [];
      const product = products.find(p => String(p.id) === pid);
      
      if (product && stockData.length > 0) {
        const pcsPerBox = product.pieces_per_box || 0;
        const unitType = product.unit_type || 'units';
        
        let stockDisplay = `<strong>${product.name}</strong><br>`;
        let totalStock = 0;
        
        stockData.forEach(location => {
          const locationQty = Number(location.qty_base || 0);
          totalStock += locationQty;
          
          let locationStockDisplay = '';
          if (unitType === 'boxes' && pcsPerBox > 0) {
            const fullBoxes = Math.floor(locationQty / pcsPerBox);
            const remainingPieces = locationQty % pcsPerBox;
            locationStockDisplay = `${fullBoxes} boxes`;
            if (remainingPieces > 0) {
              locationStockDisplay += ` + ${remainingPieces} pieces`;
            }
          } else if (unitType === 'pieces' && pcsPerBox > 0) {
            const fullBoxes = Math.floor(locationQty / pcsPerBox);
            const remainingPieces = locationQty % pcsPerBox;
            locationStockDisplay = `${locationQty.toLocaleString()} pieces`;
            if (fullBoxes > 0) {
              locationStockDisplay += ` (${fullBoxes} boxes`;
              if (remainingPieces > 0) {
                locationStockDisplay += ` + ${remainingPieces} pieces`;
              }
              locationStockDisplay += ')';
            }
          } else {
            locationStockDisplay = `${locationQty.toLocaleString()} ${unitType}`;
          }
          
          const stockClass = locationQty > 0 ? 'text-success' : 'text-warning';
          stockDisplay += `<div class="mb-1"><span class="${stockClass}">${location.location_name}:</span> ${locationStockDisplay}</div>`;
        });
        
        // Add total summary
        if (unitType === 'boxes' && pcsPerBox > 0) {
          const totalBoxes = Math.floor(totalStock / pcsPerBox);
          const remainingPieces = totalStock % pcsPerBox;
          stockDisplay += `<hr><strong>Total: ${totalBoxes} boxes`;
          if (remainingPieces > 0) {
            stockDisplay += ` + ${remainingPieces} pieces`;
          }
          stockDisplay += ` (${totalStock.toLocaleString()} total pieces)</strong>`;
        } else {
          stockDisplay += `<hr><strong>Total: ${totalStock.toLocaleString()} ${unitType}</strong>`;
        }
        
        currentStock.innerHTML = stockDisplay;
      } else if (product) {
        currentStock.innerHTML = `<strong>${product.name}</strong><br><span class="text-warning">No stock found in any location</span>`;
      } else {
        currentStock.textContent = "Product not found.";
      }
    } catch (error) {
      console.error('Failed to fetch stock:', error);
      currentStock.textContent = "Error fetching stock information.";
    }
  }

  async function submit(e) {
    e.preventDefault();

    const locId = locationId.value;
    const pid = productId.value;
    const selectedUnitType = unitType.value;
    const qty = Number(qtyChange.value || 0);
    const price = Number(unitPrice.value || 0);
    const n = (note.value || "").trim();

    if (!locId) {
      showElError("formError", "Select a location");
      return;
    }
    if (!pid) {
      showElError("formError", "Select a product");
      return;
    }
    if (qty <= 0) {
      showElError("formError", "Quantity must be greater than 0");
      return;
    }

    const fd = new FormData();
    fd.append("location_id", locId);
    fd.append("product_id", pid);
    fd.append("qty_change", String(qty));
    fd.append("unit_type", selectedUnitType);
    fd.append("unit_price", String(price));
    fd.append("note", n);
    fd.append("csrf", window.APP.CSRF || "");

    btnSave.disabled = true;
    btnSave.textContent = "Saving...";

    const res = await fetch(api("stock_in"), { 
      method: "POST", 
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        product_id: pid,
        to_location_id: locId,
        qty_base: qty,
        note: n
      })
    });
    const j = await res.json();

    btnSave.disabled = false;
    btnSave.textContent = "Add Stock";

    if (!j.ok) {
      showElError("formError", j.error || "Failed to record stock in");
      showElError("formSuccess", "");
    } else {
      showElError("formError", "");
      showElError("formSuccess", j.message || "Stock added successfully");
      form.reset();
      updateCurrentStock();
      updateUnitHint();
      setTimeout(() => showElError("formSuccess", ""), 5000);
    }
  }

  if (form) form.addEventListener("submit", submit);
  if (locationId) {
    locationId.addEventListener("change", updateCurrentStock);
  }
  if (productId) {
    productId.addEventListener("change", () => {
      updateCurrentStock();
      updateUnitHint();
    });
  }
  if (unitType) {
    unitType.addEventListener("change", updateUnitHint);
  }

  loadLocations();
  loadProducts();
})();
