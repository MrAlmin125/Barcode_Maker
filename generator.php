<?php
require_once __DIR__ . '/api/config.php';
$activePage = 'generator';
$pageTitle  = 'المولد - الضيافة';
include __DIR__ . '/includes/header.php';
?>

<div class="gen-grid">
  <div>
    <div class="card">
      <div class="card-title"><span class="dot"></span> <span id="gen-title">إنشاء باركود</span></div>
      <div class="form-group">
        <label id="label-product-name">اسم المنتج *</label>
        <input type="text" id="m-title" placeholder="مثال: زيت زيتون ممتاز" oninput="updatePreviewFromManual()" />
      </div>
      <div class="form-group">
        <label id="label-category">التصنيف</label>
        <select id="m-category" onchange="updatePreviewFromManual()" style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:11px 14px;color:var(--text);font-family:inherit;font-size:14px;outline:none;">
          <option value="">-- بدون تصنيف --</option>
        </select>
      </div>
      <div class="row">
        <div class="form-group">
          <label id="label-price">السعر</label>
          <input type="number" id="m-price" placeholder="0.00" oninput="updatePreviewFromManual()" />
        </div>
        <div class="form-group">
          <label id="label-weight">الوزن</label>
          <input type="number" id="m-weight-value" placeholder="500" step="any" oninput="combineWeight()" />
        </div>
        <div class="form-group weight-unit-group">
          <label id="label-weight-unit">الوحدة</label>
          <select id="m-weight-unit" onchange="combineWeight()" style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:11px 8px;color:var(--text);font-family:inherit;font-size:14px;outline:none;">
            <option value="g">g</option>
            <option value="kg">kg</option>
            <option value="ml">ml</option>
            <option value="l">liter</option>
          </select>
        </div>
      </div>
      <!-- Hidden field: keeps original combined format e.g. "500g" for DB/preview compatibility -->
      <input type="hidden" id="m-weight" />
      <div class="form-group">
        <label id="label-expdate">تاريخ الانتهاء <span id="expdate-optional" style="font-size:11px;color:var(--text2);">(اختياري)</span></label>
        <input type="date" id="m-expdate-picker" oninput="convertExpDate()" />
        <!-- Hidden field: keeps original DD/MM/YYYY format for save.php compatibility -->
        <input type="hidden" id="m-expdate" />
      </div>
      <div class="form-group">
        <label id="label-barcode">رقم الباركود EAN-13</label>
        <div style="display:flex;gap:8px;align-items:center;">
          <input type="text" id="m-barcode" class="mono" maxlength="13" placeholder="880XXXXXXXXXX" oninput="onManualInput()" style="flex:1;" />
          <button class="btn btn-warning btn-lg" id="random-btn" onclick="genRandomBarcode()" style="flex-shrink:0;white-space:nowrap; padding: 20px 16px;"><i class="fa-solid fa-dice-six fa-spin-pulse fa-xl" style="color: rgb(255, 255, 255);"></i></button>
        </div>
        <div class="hint" id="barcode-hint">12 أو 13 رقم، يتم حساب الرقم الاختباري تلقائياً</div>
        <div id="dup-warning-gen" style="display:none;"></div>
      </div>
      <div class="gen-action-row" style="display:flex;flex-direction:row;gap:10px;margin-top:4px;">
        <button class="btn btn-danger" id="save-btn" onclick="addToQueueOnly()" style="flex:1;">⌇⌇⌇ إضافة إلى المكتبة</button>
        <button class="btn btn-success" id="add-and-print-btn" onclick="addToQueueAndPrint()" style="flex:1;background:var(--accent2);color:#0a0a0f;">🖨️ إضافة وطباعة مباشرة</button>
      </div>
    </div>
  </div>
  <div>
    <div class="card">
      <div class="card-title"><span class="dot" style="background:var(--accent2)"></span> <span id="preview-title">معاينة مباشرة</span></div>
      <div style="background:#f5f5f5;border-radius:12px;padding:20px;text-align:center;min-height:350px;display:flex;align-items:center;justify-content:center;" id="live-preview-container">
        <div style="color:#999;font-size:12px;" id="preview-placeholder-text">أدخل بيانات المنتج لمعاينة الملصق</div>
      </div>
      <div id="preview-validity" style="margin-top:12px;text-align:center;"></div>
      <button id="download-barcode-btn" class="btn btn-warning btn-sm" onclick="downloadBarcodeHighRes()" style="width:100%;margin-top:10px;display:none;"><i class="fa-solid fa-download fa-lg" style="color: rgb(255, 255, 255); position: relative; top: -2px;"></i> <span id="download-btn-text">تحميل PNG جودة عالية</span></button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>