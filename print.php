<?php
require_once __DIR__ . '/api/config.php';
$activePage = 'print';
$pageTitle  = 'الطباعة - الضيافة';
include __DIR__ . '/includes/header.php';
?>

<div class="gen-grid">
  <div>
    <div class="card">
      <div class="card-title"><span class="dot" style="background:var(--accent3)"></span> <span id="print-size-title">حجم الملصق</span></div>
      <div id="paper-size-list"></div>
      <div class="divider"></div>
      <div style="margin-bottom:16px;">
        <div style="font-size:13px;font-weight:600;margin-bottom:10px;" id="custom-size-title">➕ إضافة حجم مخصص</div>
        <div class="row">
          <div class="form-group"><input type="text" id="custom-name" placeholder="الاسم (مثال: ملصق خاص)" /></div>
        </div>
        <div class="row">
          <div class="form-group"><input type="number" id="custom-width" placeholder="العرض (سم)" step="0.1" /></div>
          <div class="form-group"><input type="number" id="custom-height" placeholder="الارتفاع (سم)" step="0.1" /></div>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="addCustomLabelSize()" style="width:100%">➕ إضافة حجم جديد</button>
      </div>
      <div class="divider"></div>
      <div style="background:var(--bg3);border-radius:10px;padding:12px;margin-bottom:16px;">
        <div style="font-size:12px;color:var(--text2);margin-bottom:8px;" id="print-settings-title">ℹ️ إعدادات الطباعة</div>
        <div style="font-size:11px;" id="print-info1">• كل باركود يطبع على ملصق منفصل</div>
        <div style="font-size:11px;" id="print-info2">• اضبط الطابعة على حجم الملصق المحدد</div>
        <div style="font-size:11px;" id="print-info3">• استخدم "الحجم الفعلي" في مربع حوار الطباعة</div>
      </div>
      <div style="display:flex;gap:10px;">
        <button class="btn" onclick="printAllInQueue()" style="width:100%;background:var(--accent);color:#0a0a0f;" id="print-all-btn">
          🖨️ طباعة الكل (<span id="queue-count-print">0</span>)
        </button>
        <button class="btn btn-danger" style="width:100%" id="clear-queue-btn" onclick="clearQueue()">🗑️ تفريغ القائمة</button>
      </div>
    </div>
  </div>
  <div>
    <div class="card">
      <div class="card-title">
        <span class="dot" style="background:var(--accent)"></span>
        <span id="queue-title">قائمة الطباعة</span> (<span id="queue-count-right">0</span>)
        <button class="btn btn-success btn-sm" id="print-all-inline-btn" onclick="printAllInQueue()" style="background:var(--accent);margin-inline-start:auto;padding:5px 14px;font-size:12px;">🖨️ <span id="print-all-inline-text">طباعة الكل</span></button>
      </div>
      <div class="print-queue" id="print-queue-list"></div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
