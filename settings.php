<?php
require_once __DIR__ . '/api/config.php';
$activePage = 'settings';
$pageTitle  = 'الإعدادات - الضيافة';
include __DIR__ . '/includes/header.php';
?>

<div style="max-width:600px;width:100%">
  <?php if($isAdmin): ?>
  <div class="card">
    <div class="card-title"><span class="dot"></span> <span id="settings-header-title">إعدادات الرأس والمتجر</span></div>
    <div class="form-group">
      <label id="shop-name-label">اسم المتجر</label>
      <input type="text" id="shop-name-setting" value="الضيافة" oninput="updateHeaderPreview()" />
    </div>
    <div class="form-group">
      <label id="logo-url-label">رابط الشعار</label>
      <input type="text" id="logo-url-setting" placeholder="https://example.com/logo.png" oninput="updateLogoPreview()" />
      <div class="hint" id="logo-hint">أدخل رابط الصورة المباشر</div>
    </div>
    <div class="logo-preview" id="logo-preview-area">
      <div style="font-size:11px;color:var(--text2);margin-bottom:5px;" id="logo-preview-text">معاينة الشعار:</div>
      <img id="logo-preview-img" src="image/logo.png" alt="معاينة" style="max-width:60px;max-height:60px;border-radius:8px;">
    </div>
    <button class="btn btn-primary" id="save-header-btn" onclick="saveHeaderSettings()" style="width: 100%;">💾 حفظ إعدادات الرأس</button>
  </div>
  <?php endif; ?>

  <div class="card" style="margin-top:16px">
    <div class="card-title"><span class="dot" style="background:var(--accent2)"></span> <span id="categories-settings-title">إدارة التصنيفات</span></div>
    <div style="display:flex;gap:8px;margin-bottom:10px;align-items:center;">
      <input type="text" id="new-category-input" placeholder="اسم التصنيف الجديد..." style="flex:1;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:10px 14px;color:var(--text);font-family:inherit;font-size:14px;outline:none;" onkeydown="if(event.key==='Enter')addCategory()" />
      <button id="add-category-btn" onclick="addCategory()" style="background:var(--accent);color:white;border:none;border-radius:10px;padding:10px 14px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0;"><i class="fa-solid fa-folder-plus fa-beat-fade fa-xl" style="color: rgb(255, 255, 255);"></i></button>
    </div>
    <div id="categories-list" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
  </div>

  <div class="card" style="margin-top:16px">
    <div class="card-title"><span class="dot" style="background:#f0a500"></span> <span id="currency-settings-title">إعداد العملة</span></div>
    <div class="form-group">
      <label id="currency-label">اختر العملة / الرمز الذي يظهر على الملصق</label>
      <select id="currency-select" onchange="saveCurrencySetting()" style="background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:11px 14px;color:var(--text);font-family:inherit;font-size:15px;width:100%;outline:none;">
        <option value="SAR">SAR</option>
        <option value="riyal_new" selected> رمز جديد ﷼</option>
        <option value="ريال"> Riyal </option>
        <option value="BDT">BDT</option>
        <option value="BDT_symbol">৳</option>
      </select>
    </div>
    <div style="margin-top:10px;padding:10px;background:var(--bg3);border-radius:8px;font-size:13px;" id="currency-preview-box">
      <span id="currency-preview-label">معاينة:</span> <span id="currency-preview-text" style="font-size:16px;font-weight:700;color:var(--accent2)"></span>
    </div>
  </div>

  <div class="card" style="margin-top:16px">
    <div class="card-title"><span class="dot" style="background:#3498db"></span> <span id="print-label-settings-title">إعدادات طباعة الملصق</span></div>
    <div class="form-group">
      <label id="label-language-label">لغة تخطيط الملصق المطبوع</label>
      <select id="label-language-select" onchange="saveLabelLanguageSetting()" style="background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:11px 14px;color:var(--text);font-family:inherit;font-size:15px;width:100%;outline:none;">
        <option value="default">افتراضي (Default)</option>
        <option value="en">English</option>
        <option value="ar">العربية</option>
      </select>
    </div>
    <div class="form-group">
      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <div style="flex:1;min-width:150px;">
          <label id="price-numeral-label">نظام أرقام السعر</label>
          <select id="price-numeral-select" onchange="savePriceNumeralSetting()" style="background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:11px 14px;color:var(--text);font-family:inherit;font-size:15px;width:100%;outline:none;">
            <option value="ar">أرقام عربية (٠١٢٣٤٥٦٧٨٩)</option>
            <option value="en">أرقام إنجليزية (0123456789)</option>
          </select>
          <div class="hint" id="price-numeral-hint" style="margin-top:6px;">يتبع لغة الملصق تلقائياً، ويمكنك تغييره يدوياً هنا</div>
        </div>
        <div style="flex:1;min-width:150px;">
          <label id="weight-numeral-label">نظام أرقام الوزن</label>
          <select id="weight-numeral-select" onchange="saveWeightNumeralSetting()" style="background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:11px 14px;color:var(--text);font-family:inherit;font-size:15px;width:100%;outline:none;">
            <option value="ar">أرقام عربية (٠١٢٣٤٥٦٧٨٩)</option>
            <option value="en">أرقام إنجليزية (0123456789)</option>
          </select>
          <div class="hint" id="weight-numeral-hint" style="margin-top:6px;">يتبع لغة الملصق تلقائياً، ويمكنك تغييره يدوياً هنا</div>
        </div>
      </div>
    </div>
    <div style="margin-top:10px;padding:10px;background:var(--bg3);border-radius:8px;font-size:13px;" id="label-preview-box">
      <span id="label-preview-label">معاينة الملصق:</span>
      <div id="label-print-preview-text" style="font-size:15px;font-weight:700;color:var(--accent2);margin-top:4px;"></div>
    </div>
  </div>

 <?php if($isAdmin): ?>
 <div class="card" style="margin-top:16px;" id="default-settings-card">
    <div class="card-title"><span class="dot" style="background:#f0a500"></span> <span id="default-settings-title">الإعدادات الافتراضية</span></div>
    <div class="form-group">
      <label id="default-prefix-label">البادئة الافتراضية</label>
      <input type="text" id="default-prefix" value="96601234" maxlength="12" class="mono" />
      <div class="hint" id="prefix-hint">تستخدم عند النقر على "إنشاء رقم عشوائي"</div>
    </div>
    <button class="btn btn-primary" id="save-defaults-btn" onclick="saveBarcodeDefaults()" style="width:100%;">💾 حفظ الإعدادات الافتراضية</button>
  </div>
  <?php endif; ?>


  <?php if($isAdmin): ?>
  <div class="card" style="margin-top:16px">
    <div class="card-title"><span class="dot" style="background:var(--accent3)"></span> <span id="data-mgmt-title">إدارة البيانات</span></div>
    <div class="row">
      <button class="btn btn-secondary" style="flex:1" id="export-btn" onclick="exportData()">📤 تصدير البيانات</button>
      <button class="btn btn-secondary" style="flex:1" id="import-btn" onclick="document.getElementById('import-file').click()">📥 استيراد البيانات</button>
    </div>
    <input type="file" id="import-file" accept=".json" style="display:none" onchange="importData(this)">
    <div class="divider"></div>
    <button class="btn btn-danger" style="width:100%" id="delete-all-btn" onclick="clearAllData()">🗑️ حذف جميع البيانات</button>
  </div>
</div>
  <?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
