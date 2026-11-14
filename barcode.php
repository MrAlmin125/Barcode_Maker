<?php
require_once __DIR__ . '/api/config.php';
$activePage = 'library';
$pageTitle  = 'باركود - الضيافة';
include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-title">
    <span class="dot"></span>
    <span id="library-title">مكتبة الباركود</span>
    <span class="badge-count" id="library-count-badge">0</span>
  </div>
<div class="lib-sticky-zone">
    <div class="lib-search-sticky form-group">
      <div style="position:relative;">
        <i class="fa-solid fa-magnifying-glass" style="position:absolute; top:50%; transform:translateY(-50%); inset-inline-start:14px; color: rgb(255, 199, 0); font-size:14px; pointer-events:none; z-index:1;"></i>
        <input type="text" id="lib-search" placeholder="بحث بالاسم أو رقم الباركود..." oninput="handleLibSearchInput()" style="width:100%;padding-inline-start:36px;padding-inline-end:38px;" />
        <button type="button" id="lib-search-clear" class="lib-search-clear-btn" onclick="clearLibSearch()">&times;</button>
      </div>
    </div>
    <div class="category-filter-bar" id="lib-category-filter"></div>
  </div>
  <div style="height:12px;"></div>
  <div class="barcode-grid" id="barcode-grid"></div>
  <div class="click-hint-text" id="click-hint-text">💡 اضغط على أي باركود للذهاب إلى صفحة الطباعة</div>
</div>
<!-- EDIT BARCODE MODAL -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal">
    <div class="modal-title" id="edit-modal-title">✏️ تعديل الباركود</div>
    <div class="form-group"><label id="edit-product-label">اسم المنتج</label><input type="text" id="edit-title" /></div>
    <div class="form-group">
      <label id="edit-category-label">التصنيف</label>
      <select id="edit-category" style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:11px 14px;color:var(--text);font-family:inherit;font-size:14px;outline:none;">
        <option value="">-- بدون تصنيف --</option>
      </select>
    </div>
    <div class="row">
      <div class="form-group"><label id="edit-price-label">السعر</label><input type="number" id="edit-price" /></div>
      <div class="form-group"><label id="edit-weight-label">الوزن</label><input type="number" id="edit-weight-value" step="any" oninput="combineEditWeight()" /></div>
      <div class="form-group weight-unit-group">
        <label id="edit-weight-unit-label">الوحدة</label>
        <select id="edit-weight-unit" onchange="combineEditWeight()" style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:11px 8px;color:var(--text);font-family:inherit;font-size:14px;outline:none;">
          <option value="g">g</option>
          <option value="kg">kg</option>
          <option value="ml">ml</option>
          <option value="l">liter</option>
        </select>
      </div>
    </div>
    <!-- Hidden field: keeps original combined format e.g. "500g" for save.php/update.php compatibility -->
    <input type="hidden" id="edit-weight" />
    <div class="form-group">
      <label id="edit-expdate-label">تاريخ الانتهاء <span style="font-size:11px;color:var(--text2);">(اختياري)</span></label>
      <input type="date" id="edit-expdate-picker" oninput="convertEditExpDate()" style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:11px 14px;color:var(--text);font-family:inherit;font-size:15px;outline:none;" />
      <!-- Hidden field: keeps original DD/MM/YYYY format for save.php/update.php compatibility -->
      <input type="hidden" id="edit-expdate" />
    </div>
    <div class="form-group">
      <label id="edit-barcode-label">رقم الباركود</label>
      <div style="display:flex;gap:8px;align-items:center;">
        <input type="text" id="edit-barcode" class="mono" maxlength="13" style="flex:1;" />
        <button class="btn btn-warning btn-sm" onclick="genRandomBarcodeForEdit()">🎲</button>
      </div>
      <div id="dup-warning-edit" style="display:none;margin-top:8px;"></div>
    </div>
    <div class="toggle-row" style="margin-top:14px;">
      <span class="toggle-label" id="edit-status-label">حالة الباركود</span>
      <div style="display:flex;align-items:center;gap:8px;">
        <span class="toggle-status off" id="toggle-status-text">معطّل</span>
        <label class="toggle-switch">
          <input type="checkbox" id="edit-status-toggle" onchange="updateToggleLabel()">
          <span class="toggle-slider"></span>
        </label>
      </div>
    </div>
    <div class="row" style="margin-top:20px;">
      <button class="btn btn-primary" id="save-edit-btn" onclick="saveEdit()">💾 حفظ التغييرات</button>
      <button class="btn btn-danger" id="cancel-edit-btn" onclick="closeEditModal()">إلغاء</button>
    </div>
  </div>
</div>

<!-- BARCODE DELETE POPUP -->
<div id="delete-popup-overlay" class="delete-popup-overlay" style="display:none;" onclick="handleDeletePopupOverlayClick(event)">
  <div class="delete-popup-card">
    <span class="delete-popup-icon">🗑️</span>
    <div class="delete-popup-title" id="del-popup-title">تأكيد الحذف</div>
    <div class="delete-popup-msg" id="del-popup-msg">هل تريد حذف هذا الباركود؟</div>
    <span class="delete-popup-name" id="del-popup-name" onclick="toggleDeletePopupBarcode()" style="cursor:pointer;user-select:none;"></span>
    <div id="del-popup-barcode-preview" style="display:none;margin-bottom:16px;background:#fff;border-radius:10px;padding:10px;text-align:center;"></div>
    <div class="delete-popup-btns">
      <button class="btn btn-danger" id="del-popup-confirm-btn" onclick="confirmDeleteBarcode()" style="flex:none;min-width:130px;">🗑️ <span id="del-popup-confirm-text">نعم، احذف</span></button>
      <button class="btn btn-secondary" id="del-popup-cancel-btn" onclick="closeDeletePopup()" style="flex:none;min-width:110px;">❌ <span id="del-popup-cancel-text">إلغاء</span></button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>