<?php
require_once __DIR__ . '/api/config.php';
$activePage = 'qr';
$pageTitle  = 'QR Code Generator - Al-Diyafah';
include __DIR__ . '/includes/header.php';
?>


<div class="qr-generator-wrapper">
  <!-- ===== PREVIEW SECTION ===== -->
  <div class="qr-preview-card">
    <div class="qr-preview-header">
      <h3>📱 QR Code Preview</h3>
      <div class="qr-preview-actions">
        <button onclick="downloadQR()" title="Download">⬇️</button>
        <button onclick="copyQR()" title="Copy">📋</button>
        <button onclick="printQR()" title="Print">🖨️</button>
        <button onclick="exportPDF()" title="PDF">📄</button>
      </div>
    </div>

    <div id="qr-display-container">
      <div id="qr-title-display"></div>
      <img id="qr-image" src="" alt="QR Code" style="display:none;">
      <div id="qr-subtitle-display"></div>
    </div>

    <div id="qr-status" class="qr-status-badge">Enter data to generate QR Code</div>
    
    <div class="qr-info-bar">
      <span class="qr-info-item">
        <span class="dot" style="background:var(--accent);"></span>
        <span id="qr-version">Version: Auto</span>
      </span>
      <span class="qr-info-item">
        <span class="dot" style="background:#51cf66;"></span>
        <span id="qr-level">Level: M</span>
      </span>
      <span class="qr-info-item">
        <span class="dot" style="background:#ffd43b;"></span>
        <span id="qr-size-display">Size: 300px</span>
      </span>
      <span class="qr-info-item">
        <span class="dot" style="background:#74c0fc;"></span>
        <span id="qr-corner-display">Corner: Standard</span>
      </span>
    </div>

    <div class="logo-badge" id="logo-badge">
      <img id="logo-badge-img" src="" alt="Logo">
      <span>✅ Logo added</span>
    </div>
  </div>

  <!-- ===== CONTROLS SECTION ===== -->
  <div>
    <div class="qr-controls-card">
      <div class="qr-controls-header">
        <div class="icon">⚙️</div>
        <h3>QR Code Settings</h3>
      </div>

      <!-- Data Input -->
      <div class="control-section">
        <div class="control-label">
          <span class="emoji">📝</span> Data
        </div>
        <textarea id="qr-data" class="control-input" rows="2" placeholder="Enter text, URL, or any data...">https://example.com</textarea>
        <div class="template-buttons">
          <button class="template-btn" onclick="setQRData('https://')">🔗 URL</button>
          <button class="template-btn" onclick="setQRData('tel:+966')">📞 Phone</button>
          <button class="template-btn" onclick="setQRData('mailto:')">✉️ Email</button>
          <button class="template-btn" onclick="setQRData('sms:')">💬 SMS</button>
          <button class="template-btn" onclick="setQRData('WIFI:T:WPA;S:MyWiFi;P:password;;')">📶 WiFi</button>
          <button class="template-btn" onclick="setQRData('BEGIN:VCARD\nFN:John Doe\nTEL:+123456789\nEMAIL:john@example.com\nEND:VCARD')">👤 vCard</button>
        </div>
      </div>

      <!-- Title & Subtitle with Font Size -->
      <div class="control-section">
        <div class="control-grid-2">
          <div>
            <div class="control-label" style="font-size:12px;font-weight:500;">
              <span class="emoji">📌</span> Title
            </div>
            <input type="text" id="qr-title" class="control-input" placeholder="Enter title..." oninput="updateTitle()">
          </div>
          <div>
            <div class="control-label" style="font-size:12px;font-weight:500;">
              <span class="emoji">📎</span> Subtitle
            </div>
            <input type="text" id="qr-subtitle" class="control-input" placeholder="Enter subtitle..." oninput="updateTitle()">
          </div>
        </div>
        <div style="margin-top:10px;">
          <div class="control-label" style="font-size:12px;font-weight:500;">
            <span class="emoji">📏</span> Font Size
          </div>
          <div class="font-size-controls">
            <span style="font-size:12px;color:var(--text3);">Title:</span>
            <input type="range" id="title-font-size" min="14" max="48" value="24" oninput="updateTitleFontSize()">
            <span class="size-value" id="title-font-display">24px</span>
          </div>
          <div class="font-size-controls" style="margin-top:6px;">
            <span style="font-size:12px;color:var(--text3);">Subtitle:</span>
            <input type="range" id="subtitle-font-size" min="10" max="28" value="16" oninput="updateSubtitleFontSize()">
            <span class="size-value" id="subtitle-font-display">16px</span>
          </div>
        </div>
      </div>

      <!-- Colors -->
      <div class="control-section">
        <div class="control-label">
          <span class="emoji">🎨</span> Colors
        </div>
        <div class="control-grid-2">
          <div class="color-picker-group">
            <input type="color" id="qr-fg-color" value="#000000" onchange="updateQR()">
            <input type="text" id="qr-fg-color-text" value="#000000" onchange="syncColor('fg', this.value)">
            <span class="color-picker-label">Foreground</span>
          </div>
          <div class="color-picker-group">
            <input type="color" id="qr-bg-color" value="#ffffff" onchange="updateQR()">
            <input type="text" id="qr-bg-color-text" value="#ffffff" onchange="syncColor('bg', this.value)">
            <span class="color-picker-label">Background</span>
          </div>
        </div>
      </div>

      <!-- Size & Level -->
      <div class="control-section">
        <div class="control-grid-2">
          <div>
            <div class="control-label" style="font-size:12px;font-weight:500;">
              <span class="emoji">📏</span> Size
            </div>
            <select id="qr-size" class="control-select" onchange="updateQR()">
              <option value="200">Small (200px)</option>
              <option value="300" selected>Medium (300px)</option>
              <option value="400">Large (400px)</option>
              <option value="600">X-Large (600px)</option>
              <option value="800">XX-Large (800px)</option>
            </select>
          </div>
          <div>
            <div class="control-label" style="font-size:12px;font-weight:500;">
              <span class="emoji">🔲</span> Error Correction
            </div>
            <select id="qr-error-level" class="control-select" onchange="updateQR()">
              <option value="L">Low (7%)</option>
              <option value="M" selected>Medium (15%)</option>
              <option value="Q">Quartile (25%)</option>
              <option value="H">High (30%)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Corner Style Presets -->
      <div class="control-section">
        <div class="control-label">
          <span class="emoji">🔷</span> Corner Style Presets
        </div>
        <div class="corner-style-presets">
          <button class="corner-preset active" data-corner="standard" onclick="setCornerStyle('standard')">
            <span class="corner-icon">▣</span>
            <span class="corner-name">Standard</span>
          </button>
          <button class="corner-preset" data-corner="rounded" onclick="setCornerStyle('rounded')">
            <span class="corner-icon">⬤</span>
            <span class="corner-name">Rounded</span>
          </button>
          <button class="corner-preset" data-corner="circle" onclick="setCornerStyle('circle')">
            <span class="corner-icon">●</span>
            <span class="corner-name">Circle</span>
          </button>
        </div>
      </div>

      <!-- Logo Upload -->
      <div class="control-section">
        <div class="control-label">
          <span class="emoji">🖼️</span> Logo (Optional)
        </div>
        <div class="file-upload-wrapper">
          <input type="file" id="qr-logo-upload" accept="image/*" onchange="uploadLogo(event)">
          <button class="btn-remove-logo" onclick="removeLogo()" id="remove-logo-btn">🗑️</button>
        </div>
        <div style="margin-top:6px;font-size:11px;color:var(--text3);">
          Supports PNG, JPG, SVG - Max 2MB
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="control-section" style="margin-bottom:0;">
        <div class="action-buttons">
          <button class="action-btn action-btn-primary" onclick="generateQR()">
            <span>🔄</span> Generate
          </button>
          <button class="action-btn action-btn-secondary" onclick="saveQR()">
            <span>💾</span> Save
          </button>
          <button class="action-btn action-btn-success" onclick="printQR()">
            <span>🖨️</span> Print
          </button>
          <button class="action-btn action-btn-secondary" onclick="exportPDF()">
            <span>📄</span> PDF
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===== HISTORY SECTION ===== -->
<div class="history-card">
  <div class="history-header">
    <h3>
      📜 QR Code History
      <span class="count" id="qr-history-count">0</span>
    </h3>
    <button class="action-btn action-btn-danger" onclick="clearQRHistory()" style="padding:6px 16px;font-size:12px;">
      🗑️ Clear All
    </button>
  </div>
  <div id="qr-history-list" class="history-grid"></div>
</div>

<!-- QRCode.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<!-- jsPDF for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>


<?php include __DIR__ . '/includes/footer.php'; ?>