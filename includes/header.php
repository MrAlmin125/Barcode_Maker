<?php
if (!isset($activePage)) $activePage = '';
if (!isset($pageTitle))  $pageTitle  = 'الضيافة - نظام طباعة الباركود';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($pageTitle); ?></title>
<link rel="icon" type="image/x-icon" href="image/logo.png">
<meta name="theme-color" content="#FFA500">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="الضيافة">
<link rel="manifest" href="./manifest.php" id="pwa-manifest-link">
<!-- PERF: open the connection to these CDNs immediately instead of
     waiting until the CSS <link> tags below are parsed - saves a DNS
     lookup + TLS handshake round trip on the critical path. -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;500;600;700&family=Tajawal:wght@400;500;700&family=JetBrains+Mono:wght@400;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=3">


</head>
<body class="rtl <?php echo $isAdmin ? 'admin-user' : ''; ?>" data-admin="<?php echo $isAdmin ? 'true' : 'false'; ?>" data-active-page="<?php echo h($activePage); ?>" data-username="<?php echo h($loggedInUser); ?>" data-user-id="<?php echo (int)$loggedInId; ?>">

<!-- ===== LOADING POPUP ===== -->
<div id="loading-popup" class="loading-popup-overlay">
  <div class="loading-popup-card">
    <button class="loading-close-btn" onclick="closeLoadingPopupManually()"><i class="fas fa-times"></i></button>
    <div class="loading-popup-icon"><i class="fas fa-spinner"></i></div>
    <div class="loading-popup-title" id="loading-title"><?php echo $isAdmin ? '<i class="fa-brands fa-amilia" style="color: rgb(255, 199, 0);"></i> ' : ''; ?>Loading...</div>
    <div class="loading-popup-message" id="loading-message">Please wait...</div>
    <div class="loading-progress-bar"><div class="loading-progress-fill" id="loading-progress"></div></div>
    <div class="loading-dots"><span></span><span></span><span></span></div>
    <div class="loading-timer"><i class="fas fa-hourglass-half"></i> Closing in <span id="timer-seconds-loading">5</span>s</div>
    <div class="loading-buttons">
      <button class="loading-btn loading-btn-wait" onclick="closeLoadingPopupAndWait()"><i class="fas fa-clock"></i> Wait</button>
      <button class="loading-btn loading-btn-close" onclick="closeLoadingPopupManually()"><i class="fas fa-times"></i> Close</button>
    </div>
  </div>
</div>

<!-- ===== HEADER ===== -->
<header>
  <div class="header-inner">
    <div class="header-left">
      <div class="lang-toggle">
        <button class="lang-btn active" id="btn-ar" onclick="setLanguage('ar')">AR</button>
        <button class="lang-btn" id="btn-en" onclick="setLanguage('en')">EN</button>
      </div>
    </div>

    <div class="logo">
      <a class="logo-link" href="barcode.php">
        <img id="header-logo" src="image/logo.png" alt="Logo" class="logo-img">
        <div>
          <div class="logo-text" id="header-shop-name">الضيافة</div>
          <div class="logo-sub" id="header-subtitle">نظام طباعة الباركود</div>
        </div>
      </a>
    </div>

    <div class="header-right">
      <button class="theme-toggle-btn" id="theme-toggle-btn" onclick="toggleTheme()" aria-label="Toggle theme">
        <span class="icon-moon">🌙</span>
        <span class="toggle-knob"></span>
        <span class="icon-sun">☀️</span>
      </button>
      <button id="pwa-install-btn" onclick="triggerPWAInstall()" style="display:none;background:linear-gradient(135deg,var(--accent),#8b85ff);color:white;border:none;padding:7px 13px;border-radius:8px;font-size:13px;cursor:pointer;font-weight:600;white-space:nowrap;align-items:center;gap:6px;">
        <img id="pwa-install-icon" src="image/logo.png" style="width:18px;height:18px;border-radius:4px;object-fit:cover;">
        <span id="pwa-install-btn-text">تثبيت</span>
      </button>
      <button class="hamburger" id="hamburger-btn" onclick="toggleMobileNav()" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<!-- ===== PC SIDEBAR ===== -->
<div class="sidebar" id="pc-sidebar">
  <div class="sidebar-user-card">
    <div class="sidebar-user-avatar"><?php echo $isAdmin ? '<i class="fa-brands fa-amilia fa-beat-fade" style="color: rgb(255, 199, 0);"></i>' : '<i class="fa-solid fa-user-tie fa-beat-fade" style="color: rgb(255, 199, 0);"></i>'; ?></div>
    <div class="sidebar-user-info">
      <div class="sidebar-user-name"><?php echo h($loggedInUser); ?></div>
      <div class="sidebar-user-role" id="sb-user-role-text"><?php echo $isAdmin ? 'مدير النظام' : 'مستخدم نشط'; ?></div>
    </div>
  </div>
  <div class="sidebar-label" id="sb-label-menu">القائمة</div>
  <a class="sidebar-nav-btn <?php echo $activePage==='generator'?'active':''; ?>" id="nav-generator" href="generator.php">
    <span class="nav-icon"><i class="fas fa-barcode"></i></span> <span id="sb-txt-generator">المولد</span>
  </a>
  <a class="sidebar-nav-btn <?php echo $activePage==='library'?'active':''; ?>" id="nav-library" href="barcode.php">
    <span class="nav-icon"><i class="fas fa-database"></i></span> <span id="sb-txt-library">باركود</span>
  </a>
  <a class="sidebar-nav-btn <?php echo $activePage==='print'?'active':''; ?>" id="nav-print" href="print.php">
    <span class="nav-icon"><i class="fas fa-print"></i></span> <span id="sb-txt-print">الطباعة</span>
  </a>
  <?php if($isAdmin): ?>
  <a class="sidebar-nav-btn <?php echo $activePage==='users'?'active':''; ?>" id="nav-users" href="users.php">
    <span class="nav-icon"><i class="fas fa-users"></i></span> <span id="sb-txt-users">المستخدمون</span>
  </a>
  <?php endif; ?>
  <a class="sidebar-nav-btn <?php echo $activePage==='qr'?'active':''; ?>" id="nav-qrcode" href="qrcode.php">
    <span class="nav-icon"><i class="fas fa-qrcode"></i></span> <span id="sb-txt-qrcode">QR Code</span>
  </a>
  <a class="sidebar-nav-btn <?php echo $activePage==='settings'?'active':''; ?>" id="nav-settings" href="settings.php">
    <span class="nav-icon"><i class="fas fa-cog"></i></span> <span id="sb-txt-settings">الإعدادات</span>
  </a>
  
  <div style="flex:1;"></div>
  <div class="sidebar-divider"></div>
    
  <!-- Logout -->
    
  <a href="logout.php" class="sidebar-logout-btn">

    <span class="nav-icon logout-circle">

      <?php echo $isAdmin
        ? '<i class="fa-brands fa-amilia"></i>'
        : '<i class="fa-solid fa-user-tie"></i>';
      ?>

    </span>

    <span id="sb-txt-logout">
      تسجيل خروج
    </span>

    <span class="sidebar-logout-user">
      (<?php echo h($loggedInUser); ?>)
    </span>

  </a>
    
  <div class="sidebar-copyright">
    <span class="copy-by">Developed By</span>
    <a href="https://amin.free.nf/" target="_blank" class="copy-name">MR.AMIN</a>
  </div>
</div>

<!-- ===== MOBILE NAV ===== -->
<div class="mobile-nav-overlay" id="mobile-nav-overlay" onclick="closeMobileNav()"></div>
<div class="mobile-nav-drawer" id="mobile-nav-drawer">
  <div class="drawer-logo-area">
    <img id="drawer-logo-img" src="image/logo.png" alt="Logo">
    <div>
      <div class="drawer-shop-name" id="drawer-shop-name-text">كيف الضيافة</div>
      <div class="drawer-sub" id="drawer-sub-text">نظام طباعة الباركود</div>
    </div>
  </div>
  <div class="drawer-hi-user">
    <?php echo $isAdmin ? '<i class="fa-brands fa-amilia" style="color: rgb(255, 199, 0);"></i>' : '<i class="fa-solid fa-user-tie" style="color: rgb(255, 199, 0);"></i>'; ?>
    <span><?php echo h($loggedInUser); ?></span>
    <?php if($isAdmin): ?><span class="badge" style="background:var(--accent);color:white;font-size:9px;padding:2px 6px;">Admin</span><?php endif; ?>
  </div>
  <a class="drawer-nav-btn <?php echo $activePage==='generator'?'active':''; ?>" id="d-nav-generator" href="generator.php">
    <i class="fas fa-barcode"></i> <span id="d-txt-generator">المولد</span>
  </a>
  <a class="drawer-nav-btn <?php echo $activePage==='library'?'active':''; ?>" id="d-nav-library" href="barcode.php">
    <i class="fas fa-database"></i> <span id="d-txt-library">باركود</span>
  </a>
  <a class="drawer-nav-btn <?php echo $activePage==='print'?'active':''; ?>" id="d-nav-print" href="print.php">
    <i class="fas fa-print"></i> <span id="d-txt-print">الطباعة</span>
  </a>
  <?php if($isAdmin): ?>
  <a class="drawer-nav-btn <?php echo $activePage==='users'?'active':''; ?>" id="d-nav-users" href="users.php">
    <i class="fas fa-users"></i> <span id="d-txt-users">المستخدمون</span>
  </a>
  <?php endif; ?>
  <a class="drawer-nav-btn <?php echo $activePage==='qr'?'active':''; ?>" id="d-nav-qrcode" href="qrcode.php">
    <i class="fas fa-qrcode"></i> <span id="d-txt-qrcode">QR Code</span>
  </a>
  <a class="drawer-nav-btn <?php echo $activePage==='settings'?'active':''; ?>" id="d-nav-settings" href="settings.php">
    <i class="fas fa-cog"></i> <span id="d-txt-settings">الإعدادات</span>
  </a>
  <div class="drawer-theme-row">
    <span class="drawer-theme-label" id="d-txt-theme">🌙 الوضع الليلي</span>
    <button class="theme-toggle-btn" onclick="toggleTheme()">
      <span class="icon-moon">🌙</span>
      <span class="toggle-knob"></span>
      <span class="icon-sun">☀️</span>
    </button>
  </div>
  <a href="logout.php" class="drawer-logout"><i class="fas fa-sign-out-alt"></i> <span id="d-txt-logout">تسجيل خروج</span></a>
</div>

<!-- ===== MAIN APP ===== -->
<div class="app-wrapper">
  <div class="content-area">
    <div class="main">
