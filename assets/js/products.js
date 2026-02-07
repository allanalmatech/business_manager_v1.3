// assets/js/products.js
(() => {
  const api = (action) => `${window.APP.BASE_URL}/api/products.php?action=${action}`;
  const el = (id) => document.getElementById(id);
  const $ = (s) => document.querySelector(s);

  const tbody = $("#tbl tbody");
  const hint = el("hint");
  const q = el("q");
  const btnSearch = el("btnSearch");
  const btnNew = el("btnNew");
  const mdlProduct = el("mdlProduct");
  const bsModal = mdlProduct ? new bootstrap.Modal(mdlProduct) : null;

  let products = [];

  function money(x) {
    return Number(x || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  async function loadCategories() {
    try {
      const res = await fetch(api("categories"), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const text = await res.text();
      const j = JSON.parse(text);
      
      if (j.ok && j.data.categories) {
        const select = el("category_id");
        if (select) {
          select.innerHTML = '<option value="">— Select Category —</option>';
          j.data.categories.forEach(cat => {
            const option = document.createElement("option");
            option.value = cat.id;
            option.textContent = cat.name;
            select.appendChild(option);
          });
        }
      }
    } catch (e) {
      console.error("Failed to load categories:", e);
    }
  }

  async function loadLocations() {
    try {
      const res = await fetch(api("locations"), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const text = await res.text();
      const j = JSON.parse(text);
      
      if (j.ok && j.data.locations) {
        const select = el("default_location_id");
        if (select) {
          select.innerHTML = '<option value="">— Select Location —</option>';
          j.data.locations.forEach(loc => {
            const option = document.createElement("option");
            option.value = loc.id;
            option.textContent = loc.name;
            select.appendChild(option);
          });
        }
      }
    } catch (e) {
      console.error("Failed to load locations:", e);
    }
  }

  // Basic image handling
  function displayProductImages(images) {
    const gallery = el("imageGallery");
    const imageCount = el("imageCount");
    
    if (!gallery) return;
    
    gallery.innerHTML = '';
    
    if (images && images.length > 0) {
      images.forEach((img, index) => {
        const div = document.createElement('div');
        div.className = 'position-relative d-inline-block me-2 mb-2';
        div.innerHTML = `
          <img src="${img}" alt="Product image ${index + 1}" style="width: 80px; height: 80px; object-fit: cover;" class="rounded border">
          <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" onclick="removeImage(${index})" style="padding: 2px 6px;">
            <i class="bi bi-x"></i>
          </button>
        `;
        gallery.appendChild(div);
      });
      
      if (imageCount) {
        imageCount.textContent = `(${images.length}/5)`;
      }
    } else {
      if (imageCount) {
        imageCount.textContent = '(0/5)';
      }
    }
  }

  function removeImage(index) {
    // This would need to be implemented to remove images from the product
    console.log('Remove image at index:', index);
  }

  async function loadProducts() {
    if (hint) hint.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div> Loading products...';
    
    try {
      const query = q?.value || "";
      const res = await fetch(`${api("list")}&q=${encodeURIComponent(query)}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      const text = await res.text();   // read raw first
      
      try {
        const j = JSON.parse(text);
        
        if (!j.ok) throw new Error(j.error || "Failed to load");
        
        products = j.data || [];  // API returns data directly, not as items
        renderTable();
      } catch (e) {
        console.error("NON-JSON RESPONSE:", text); // shows HTML/PHP warning
        throw e;
      }
    } catch (e) {
      if (tbody) tbody.innerHTML = `<tr><td colspan="9" class="text-center p-4 text-danger">${e.message}</td></tr>`;
    } finally {
      if (hint) hint.style.display = "none";
    }
  }

  function renderTable() {
    if (!tbody) return;
    tbody.innerHTML = "";
    
    if (!products.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center p-5 text-muted">No products found.</td></tr>';
      return;
    }

    products.forEach(p => {
      const tr = document.createElement("tr");
      const statusClass = Number(p.is_active) === 1 ? "bg-success" : "bg-secondary";
      const statusText = Number(p.is_active) === 1 ? "Active" : "Inactive";
      
      tr.innerHTML = `
        <td class="ps-4">
          <div class="bg-light rounded" style="width:48px;height:48px;overflow:hidden;">
            ${p.image ? `<img src="${window.APP.BASE_URL}/${p.image}" style="width:100%;height:100%;object-fit:cover;">` : '<i class="bi bi-box p-2 opacity-25" style="font-size:1.5rem"></i>'}
          </div>
        </td>
        <td>
          <div class="fw-bold text-dark">${esc(p.name)}</div>
          <div class="text-muted small">${esc(p.sku)} • ${esc(p.category_name || "No Category")}</div>
        </td>
        <td class="text-end text-muted">${money(p.cost_price)}</td>
        <td class="text-end fw-semibold text-warning">${money(p.wholesale_price)}</td>
        <td class="text-end fw-bold text-primary">${money(p.retail_price)}</td>
        <td class="text-center">
          <span class="badge ${Number(p.qty_on_hand) <= Number(p.low_level) ? 'bg-danger' : 'bg-light text-dark border'}">
            ${money(p.qty_on_hand)}
          </span>
        </td>
        <td>
          <span class="badge bg-light text-dark border">${esc(p.brand_name || "No Brand")}</span>
        </td>
        <td><span class="badge ${statusClass}">${statusText}</span></td>
        <td class="text-end pe-4">
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-primary" onclick="editProduct(${p.id})"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-outline-danger" onclick="deleteProduct(${p.id})"><i class="bi bi-trash"></i></button>
          </div>
        </td>
      `;
      tbody.appendChild(tr);
    });
  }

  function esc(s) {
    return String(s || "").replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  window.editProduct = (id) => {
    const p = products.find(x => x.id == id);
    if (!p) return;
    
    el("id").value = p.id;
    el("name").value = p.name || "";
    el("sku").value = p.sku || "";
    el("description").value = p.description || "";
    el("cost_price").value = p.cost_price || 0;
    el("wholesale_price").value = p.wholesale_price || 0;
    el("retail_price").value = p.retail_price || 0;
    el("qty_base").value = p.qty_on_hand || 0;
    el("low_level_base").value = p.low_level || 0;
    el("category_id").value = p.category_id || "";
    el("brand_id").value = p.brand_id || "";
    el("default_location_id").value = p.default_location_id || "";
    el("is_active_check").checked = Number(p.is_active) === 1;
    el("mdlTitle").textContent = "Edit Product";
    el("btnDelete").style.display = "";
    
    // Load images if available
    if (p.images) {
      displayProductImages(p.images);
    } else {
      displayProductImages([]);
    }
    
    bsModal.show();
  };

  window.deleteProduct = async (id) => {
    if (!confirm("Are you sure you want to delete this product?")) return;
    
    try {
      const fd = new FormData();
      fd.append("id", id);
      fd.append("csrf", window.APP.CSRF);
      
      const res = await fetch(api("delete"), { method: "POST", body: fd });
      const j = await res.json();
      if (j.ok) loadProducts();
      else alert(j.message || "Delete failed");
    } catch (e) {
      alert("Error deleting product");
    }
  };

  btnSearch?.addEventListener("click", loadProducts);
  q?.addEventListener("keypress", (e) => { if (e.key === "Enter") loadProducts(); });

  btnNew?.addEventListener("click", () => {
    el("id").value = "";
    el("name").value = "";
    el("sku").value = "";
    el("description").value = "";
    el("cost_price").value = "";
    el("wholesale_price").value = "";
    el("retail_price").value = "";
    el("qty_base").value = "0";
    el("low_level_base").value = "0";
    el("is_active_check").checked = true;
    el("mdlTitle").textContent = "New Product";
    el("btnDelete").style.display = "none";
    bsModal.show();
  });

  el("btnSave")?.addEventListener("click", async () => {
    const btn = el("btnSave");
    const id = el("id").value;
    const action = id ? "update" : "create";
    
    const data = {
      id: id,
      name: el("name").value,
      sku: el("sku").value,
      description: el("description").value,
      cost_price: parseFloat(el("cost_price").value) || 0,
      wholesale_price: parseFloat(el("wholesale_price").value) || 0,
      retail_price: parseFloat(el("retail_price").value) || 0,
      qty_on_hand: parseFloat(el("qty_base").value) || 0,
      low_level: parseFloat(el("low_level_base").value) || 0,
      category_id: el("category_id").value || 0,
      brand_id: el("brand_id").value || 0,
      is_active: el("is_active_check").checked ? 1 : 0
    };

    btn.disabled = true;
    btn.textContent = "Saving...";

    try {
      const res = await fetch(api(action), { 
        method: "POST", 
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
      });
      const text = await res.text();
      
      try {
        const j = JSON.parse(text);
        if (j.ok) {
          bsModal.hide();
          loadProducts();
        } else {
          alert(j.error || "Save failed");
        }
      } catch (e) {
        console.error("NON-JSON RESPONSE:", text);
        alert("Invalid server response");
      }
    } catch (e) {
      console.error("Error saving product:", e);
      alert("Error saving product");
    } finally {
      btn.disabled = false;
      btn.textContent = "Save Product";
    }
  });

  loadProducts();
  loadCategories();
  loadLocations();
})();
