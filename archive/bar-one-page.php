<?php

/* =========================
SESSION
========================= */

ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

/* =========================
AUTO LOGOUT AFTER 30 MINUTES
========================= */

$inactive = 1800;

if (isset($_SESSION['last_activity'])) {
    $session_life = time() - $_SESSION['last_activity'];
    if ($session_life > $inactive) {
        session_unset();
        session_destroy();
        header("Location: /?timeout=1");
        exit;
    }
}

$_SESSION['last_activity'] = time();

/* =========================
LOGIN CHECK
========================= */

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /");
    exit;
}

/* =========================
LOGGED IN USERNAME & ADMIN STATUS
========================= */
$loggedInUser  = isset($_SESSION['username'])  ? $_SESSION['username']  : 'User';
$loggedInId    = isset($_SESSION['user_id'])   ? (int)$_SESSION['user_id'] : 0;
$isAdmin       = isset($_SESSION['is_admin'])  ? (bool)$_SESSION['is_admin'] : false;
$permSettings  = $isAdmin ? true : ($_SESSION['perm_settings']  ?? false);
$permShopName  = $isAdmin ? true : ($_SESSION['perm_shop_name'] ?? false);
$permLogo      = $isAdmin ? true : ($_SESSION['perm_logo']      ?? false);
$permDataMang  = $isAdmin ? true : ($_SESSION['perm_data_mang'] ?? false);

/* =========================
DB CONNECTION
========================= */
$conn = new mysqli(
    "sql110.infinityfree.com",
    "if0_40706792",
    "NewTree1",
    "if0_40706792_barcodes"
);

/* =========================
LOAD USERS LIST (admin only)
========================= */
$usersList = [];
if ($isAdmin && !$conn->connect_error) {
    $res = $conn->query(
        "SELECT id, username, is_admin FROM users
         WHERE id != {$loggedInId}
         ORDER BY is_admin DESC, id DESC"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $usersList[] = [
                'id'       => (int)$r['id'],
                'username' => $r['username'],
                'is_admin' => (bool)$r['is_admin'],
            ];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>الضيافة - نظام طباعة الباركود</title>
<link rel="icon" type="image/x-icon" href="../image/logo.png">
<meta name="theme-color" content="#FFA500">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="الضيافة">
<link rel="manifest" href="./manifest.php" id="pwa-manifest-link">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;500;600;700&family=Tajawal:wght@400;500;700&family=JetBrains+Mono:wght@400;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
  @font-face {
    font-family: 'SaudiRiyalSymbol';
    src: url('https://cdn.jsdelivr.net/npm/@abdulrysr/saudi-riyal-new-symbol-font@1.0.0/saudiriyal.woff2') format('woff2'),
         url('https://cdn.jsdelivr.net/npm/@abdulrysr/saudi-riyal-new-symbol-font@1.0.0/saudiriyal.woff') format('woff'),
         url('https://cdn.jsdelivr.net/npm/@abdulrysr/saudi-riyal-new-symbol-font@1.0.0/saudiriyal.otf') format('opentype');
  }

  /* ===== DARK MODE (default) ===== */
  :root {
    --bg: #0a0a0f;
    --bg2: #111118;
    --bg3: #1a1a24;
    --bg4: #22222f;
    --border: #2e2e42;
    --accent: #F1AC14;
    --accent2: #00d4aa;
    --accent3: #ff6b6b;
    --text: #e8e8f0;
    --text2: #9999b8;
    --card: #16161f;
    --shadow: 0 8px 32px rgba(108,99,255,0.15);
    --header-bg: linear-gradient(135deg, #0d0d1a 0%, #1a1030 50%, #0d1520 100%);
  }

  /* ===== LIGHT MODE ===== */
  body.light-mode {
    --bg: #f0f2f8;
    --bg2: #e8eaf2;
    --bg3: #dde0ed;
    --bg4: #d0d4e8;
    --border: #c5cade;
    --accent: #d4920a;
    --accent2: #00a882;
    --accent3: #e05555;
    --text: #1a1a2e;
    --text2: #5a5a8a;
    --card: #ffffff;
    --shadow: 0 8px 32px rgba(108,99,255,0.10);
    --header-bg: linear-gradient(135deg, #ffffff 0%, #f0f0ff 50%, #e8f4ff 100%);
  }

  /* ===== THEME TOGGLE — moon/sun style ===== */
  .theme-toggle-btn {
    position: relative;
    width: 56px; height: 28px;
    background: #1a1a2e;
    border: 2px solid var(--border);
    border-radius: 28px;
    cursor: pointer;
    flex-shrink: 0;
    outline: none;
    display: flex;
    align-items: center;
    transition: background 0.3s ease, border-color 0.3s ease;
    padding: 0;
  }
  body.light-mode .theme-toggle-btn {
    background: #f0e6c8;
    border-color: #d4920a;
  }
  .theme-toggle-btn .toggle-knob {
    position: absolute;
    top: 2px; left: 2px;
    width: 20px; height: 20px;
    background: #2e2e42;
    border-radius: 50%;
    transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
    box-shadow: 0 2px 6px rgba(0,0,0,0.4);
    z-index: 2;
  }
  body.light-mode .theme-toggle-btn .toggle-knob {
    transform: translateX(28px);
    background: #fff7e0;
  }
  .theme-toggle-btn .icon-moon {
    position: absolute;
    left: 4px;
    font-size: 13px;
    z-index: 3;
    pointer-events: none;
    line-height: 1;
    top: 50%;
    transform: translateY(-50%);
    transition: opacity 0.25s;
    opacity: 1;
  }
  .theme-toggle-btn .icon-sun {
    position: absolute;
    right: 4px;
    font-size: 13px;
    z-index: 3;
    pointer-events: none;
    line-height: 1;
    top: 50%;
    transform: translateY(-50%);
    transition: opacity 0.25s;
    opacity: 0.35;
  }
  body.light-mode .theme-toggle-btn .icon-moon { opacity: 0.35; }
  body.light-mode .theme-toggle-btn .icon-sun  { opacity: 1; }

  .riyal-symbol {
    font-family: 'SaudiRiyalSymbol', sans-serif;
    font-size: inherit; display: inline-block; color: inherit; font-weight: bold;
  }

  /* ===== LOADING POPUP ===== */
  .loading-popup-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.85);
    backdrop-filter: blur(5px); z-index: 10001;
    display: none; align-items: center; justify-content: center;
  }
  .loading-popup-card {
    background: linear-gradient(135deg, var(--card), var(--bg2));
    border-radius: 20px; padding: 35px 45px; text-align: center;
    min-width: 320px; max-width: 400px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    border: 1px solid var(--accent); animation: pulse 1.5s infinite;
    position: relative;
  }
  @keyframes pulse {
    0%,100%{ box-shadow: 0 0 0 0 rgba(241,172,20,0.4); }
    50%{ box-shadow: 0 0 0 15px rgba(241,172,20,0); }
  }
  .loading-close-btn {
    position: absolute; top: 15px; right: 15px;
    background: rgba(255,107,107,0.2); border: none;
    width: 32px; height: 32px; border-radius: 50%; cursor: pointer;
    color: var(--accent3); font-size: 16px; display: flex;
    align-items: center; justify-content: center; transition: all 0.3s ease; z-index: 10;
  }
  .loading-close-btn:hover { background: var(--accent3); color: white; transform: scale(1.1); }
  body.ltr .loading-close-btn { right: auto; left: 15px; }
  .loading-popup-icon { font-size: 48px; color: var(--accent); margin-bottom: 20px; animation: spin 1s linear infinite; }
  @keyframes spin { 0%{ transform: rotate(0deg); } 100%{ transform: rotate(360deg); } }
  .loading-popup-title { font-size: 22px; font-weight: 700; color: var(--accent); margin-bottom: 15px; }
  .loading-popup-message { font-size: 14px; color: var(--text2); margin-bottom: 20px; line-height: 1.5; }
  .loading-progress-bar { width: 100%; height: 4px; background: var(--bg3); border-radius: 4px; overflow: hidden; margin-top: 15px; }
  .loading-progress-fill { height: 100%; width: 0%; background: linear-gradient(90deg, var(--accent), var(--accent2)); border-radius: 4px; transition: width 0.3s ease; }
  .loading-dots { display: inline-flex; gap: 4px; margin-top: 10px; }
  .loading-dots span { width: 8px; height: 8px; background: var(--accent); border-radius: 50%; animation: dotBounce 1.4s infinite ease-in-out both; }
  .loading-dots span:nth-child(1){ animation-delay: -0.32s; }
  .loading-dots span:nth-child(2){ animation-delay: -0.16s; }
  @keyframes dotBounce { 0%,80%,100%{ transform: scale(0); opacity: 0.3; } 40%{ transform: scale(1); opacity: 1; } }
  .loading-timer { font-size: 12px; color: var(--text2); margin-top: 15px; font-family: monospace; }
  .loading-buttons { display: flex; gap: 10px; justify-content: center; margin-top: 20px; }
  .loading-btn { padding: 8px 20px; border-radius: 25px; border: none; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
  .loading-btn-close { background: rgba(255,107,107,0.15); color: var(--accent3); border: 1px solid rgba(255,107,107,0.3); }
  .loading-btn-close:hover { background: var(--accent3); color: white; transform: translateY(-2px); }
  .loading-btn-wait { background: rgba(241,172,20,0.15); color: var(--accent); border: 1px solid rgba(241,172,20,0.3); }
  .loading-btn-wait:hover { background: var(--accent); color: white; transform: translateY(-2px); }

  /* ===== HOVER ANIMATIONS ===== */
  .btn { position: relative; overflow: hidden; transition: all 0.25s cubic-bezier(0.4,0,0.2,1) !important; }
  .btn::after { content: ''; position: absolute; inset: 0; background: rgba(255,255,255,0.08); opacity: 0; transition: opacity 0.2s ease; border-radius: inherit; }
  .btn:hover::after { opacity: 1; }
  .btn:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
  .btn:active { transform: translateY(0) scale(0.98); box-shadow: none; }
  .btn-primary:hover  { box-shadow: 0 8px 25px rgba(108,99,255,0.45) !important; }
  .btn-success:hover  { box-shadow: 0 8px 25px rgba(0,212,170,0.45) !important; }
  .btn-danger:hover   { box-shadow: 0 8px 25px rgba(255,107,107,0.45) !important; }
  .btn-warning:hover  { box-shadow: 0 8px 25px rgba(240,165,0,0.45) !important; }
  .btn-secondary:hover{ box-shadow: 0 6px 18px rgba(0,0,0,0.25) !important; border-color: var(--accent) !important; }
  .btn .ripple { position: absolute; border-radius: 50%; background: rgba(255,255,255,0.25); transform: scale(0); animation: rippleAnim 0.5s linear; pointer-events: none; }
  @keyframes rippleAnim { to{ transform: scale(4); opacity: 0; } }

  .barcode-card { cursor: pointer; transition: all 0.3s cubic-bezier(0.4,0,0.2,1); }
  .barcode-card:hover { transform: translateY(-6px) scale(1.01); box-shadow: 0 16px 40px rgba(255,165,0,0.35); border-color: var(--accent); }
  .label-size-btn { transition: all 0.25s cubic-bezier(0.4,0,0.2,1) !important; }
  .label-size-btn:hover { border-color: var(--accent) !important; background: rgba(108,99,255,0.12) !important; transform: translateX(-3px); }
  .category-chip { transition: all 0.2s cubic-bezier(0.4,0,0.2,1) !important; }
  .category-chip:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(108,99,255,0.25); }

  /* ===== DELETE POPUP ===== */
  .delete-popup-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 2000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(6px); animation: fadeInOverlay 0.2s ease; }
  @keyframes fadeInOverlay { from{opacity:0} to{opacity:1} }
  .delete-popup-card { background: var(--card); border: 1px solid #ff6b6b55; border-radius: 20px; padding: 32px 28px; width: 90%; max-width: 360px; text-align: center; animation: popupSlideIn 0.3s cubic-bezier(0.34,1.56,0.64,1); box-shadow: 0 20px 60px rgba(255,107,107,0.2); position: relative; overflow: hidden; }
  .delete-popup-card::before { content:''; position: absolute; top:0; left:0; right:0; height:3px; background: linear-gradient(90deg,var(--accent3),#ff9a9a,var(--accent3)); background-size: 200% 100%; animation: shimmerLine 2s linear infinite; }
  @keyframes shimmerLine { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
  @keyframes popupSlideIn { from{transform:scale(0.7) translateY(30px);opacity:0} to{transform:scale(1) translateY(0);opacity:1} }
  .delete-popup-icon { font-size: 52px; display: block; margin-bottom: 14px; animation: iconShake 0.5s ease 0.2s both; }
  @keyframes iconShake { 0%,100%{transform:rotate(0)} 20%{transform:rotate(-10deg)} 40%{transform:rotate(10deg)} 60%{transform:rotate(-6deg)} 80%{transform:rotate(6deg)} }
  .delete-popup-title { font-size: 20px; font-weight: 700; color: var(--accent3); margin-bottom: 10px; }
  .delete-popup-msg { font-size: 14px; color: var(--text2); margin-bottom: 8px; line-height: 1.6; }
  .delete-popup-name { font-size: 15px; font-weight: 700; color: var(--text); background: var(--bg3); border-radius: 8px; padding: 8px 14px; margin-bottom: 24px; display: block; border: 1px solid var(--border); word-break: break-word; }
  .delete-popup-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

  /* ===== USER ALERTS ===== */
  .dup-warning, .user-alert-error {
    background: rgba(255,107,107,0.12); border: 1px solid rgba(255,107,107,0.4);
    border-radius: 10px; padding: 10px 14px; font-size: 13px;
    color: var(--accent3); display: flex; align-items: center; gap: 8px;
    margin-top: 8px; animation: dupSlideIn 0.3s ease;
  }
  .user-alert-success {
    background: rgba(0,212,170,0.12); border: 1px solid rgba(0,212,170,0.35);
    color: var(--accent2); border-radius: 10px; padding: 10px 14px;
    font-size: 13px; display: flex; align-items: center; gap: 8px;
    animation: dupSlideIn 0.3s ease;
  }
  .input-error { border-color: var(--accent3) !important; box-shadow: 0 0 0 2px rgba(255,107,107,0.2) !important; }
  @keyframes dupSlideIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
  @keyframes shakeInput { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-6px)} 40%{transform:translateX(6px)} 60%{transform:translateX(-4px)} 80%{transform:translateX(4px)} }
  .input-shake { animation: shakeInput 0.4s ease; }

  input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(100%) sepia(90%) saturate(500%) hue-rotate(10deg) brightness(200%);
    cursor: pointer; opacity: 1;
  }

  /* ===== SIDEBAR USER CARD ===== */
  .sidebar-user-card { display: flex; align-items: center; gap: 12px; padding: 16px 18px 14px; background: rgba(241,172,20,0.07); border-bottom: 1px solid var(--border); margin-bottom: 4px; }
  .sidebar-user-avatar { width: 38px; height: 38px; border-radius: 50%; background: rgba(241,172,20,0.18); border: 2px solid rgba(241,172,20,0.35); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
  .sidebar-user-info { overflow: hidden; }
  .sidebar-user-name { font-size: 14px; font-weight: 700; color: var(--accent); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .sidebar-user-role { font-size: 11px; color: var(--accent2); margin-top: -5px; }
  .sidebar-greeting { display: flex; align-items: center; gap: 7px; padding: 9px 18px; font-size: 13px; font-weight: 700; color: var(--accent2); background: rgba(0,212,170,0.05); border-bottom: 1px solid var(--border); flex-wrap: wrap; }
  .sidebar-greeting .sb-greeting-text { color: var(--text2); font-weight: 500; }
  .sidebar-greeting .sb-username { color: var(--accent); font-weight: 800; }
  .sidebar-greeting .sb-admin-badge { background: linear-gradient(135deg,#c0392b,#e74c3c); color: white; font-size: 9px; padding: 2px 7px; border-radius: 10px; font-weight: 700; }
  .sidebar-logout-btn { display: flex; align-items: center; gap: 10px; padding: 13px 20px; text-decoration: none; color: var(--accent3); font-size: 14px; font-weight: 700; border-inline-start: 3px solid transparent; transition: background 0.15s, border-color 0.15s; }
  .sidebar-logout-btn:hover { background: rgba(255,107,107,0.1); border-inline-start-color: var(--accent3); }
  .sidebar-logout-btn .nav-icon { font-size: 16px; flex-shrink: 0; }
  .sidebar-logout-user { font-size: 11px; opacity: 0.75; font-weight: 500; margin-inline-start: 2px; }
  .sidebar-copyright { text-align: center; padding: 8px 14px 12px; border-top: 1px solid var(--border); }
  .sidebar-copyright .copy-by { font-size: 9px; color: var(--text2); letter-spacing: 1.5px; text-transform: uppercase; display: block; margin-bottom: 2px; opacity: 0.7; }
  .sidebar-copyright .copy-name { font-size: 12px; font-weight: 800; letter-spacing: 3px; color: var(--accent); text-decoration: none; text-shadow: 0 0 8px rgba(241,172,20,0.5); animation: textShine 2.5s infinite ease-in-out; display: block; }
  @keyframes textShine { 0%,100%{color:#f1ac14;} 50%{color:#ffffff;} }

  #welcome-greeting { display: none !important; }
  #logout-btn-header { display: none !important; }

  .hi-user { display: inline-flex; align-items: center; gap: 6px; background: rgba(241,172,20,0.12); border: 1px solid rgba(241,172,20,0.3); color: var(--accent); border-radius: 20px; padding: 5px 14px; font-size: 13px; font-weight: 700; white-space: nowrap; flex-shrink: 0; }
  body.light-mode .hi-user { background: rgba(212,146,10,0.1); border-color: rgba(212,146,10,0.3); }

  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Noto Sans Arabic', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; transition: background 0.3s ease, color 0.3s ease; font-size: 15px; }
  body.light-mode .card { box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
  body.light-mode .barcode-card { box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
  body.light-mode .modal { box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
  body.light-mode .toast { box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
  body.rtl { direction: rtl; }
  body.ltr { direction: ltr; }

  /* ===== HEADER ===== */
  header { background: var(--header-bg); border-bottom: 1px solid var(--border); padding: 0 20px; position: fixed; top: 0; left: 0; right: 0; height: 60px; z-index: 300; transition: background 0.3s ease, border-color 0.3s ease; }
  body.light-mode header { box-shadow: 0 2px 12px rgba(0,0,0,0.10); }
  .header-inner { height: 60px; display: flex; align-items: center; justify-content: space-between; position: relative; }

  /* In RTL: header-left renders on the RIGHT side visually */
  /* So: lang-toggle goes in header-left → appears RIGHT */
  /* theme-toggle goes in header-right → appears LEFT */
  .header-left { display: flex; align-items: center; gap: 16px; flex-shrink: 0; z-index: 2; }
  .logo { position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%); z-index: 1; }
  .logo-img { width: 70px; height: 70px; border-radius: 10px; object-fit: contain; flex-shrink: 0; }
  .logo-text { font-size: 17px; font-weight: 700; white-space: nowrap; }
  .logo-sub { font-size: 10px; color: var(--text2); font-family: 'JetBrains Mono', monospace; white-space: nowrap; }
  .logo-link { display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; cursor: pointer; }
  .logo-link:hover .logo-text { color: var(--accent); }
  .header-right { display: flex; align-items: center; gap: 16px; flex-shrink: 0; z-index: 2; }

  .lang-toggle { background: var(--bg3); border: 1px solid var(--border); border-radius: 30px; padding: 4px; display: flex; gap: 4px; }
  .lang-btn { padding: 5px 13px; border: none; border-radius: 25px; cursor: pointer; font-family: 'Noto Sans Arabic', sans-serif; font-weight: 600; font-size: 13px; transition: all 0.2s; background: transparent; color: var(--text2); }
  .lang-btn:hover { background: var(--bg4); color: var(--text); }
  .lang-btn.active { background: var(--accent); color: white; }

  /* ===== SIDEBAR ===== */
  .sidebar { position: fixed; top: 60px; width: 220px; height: calc(100vh - 60px); background: var(--bg2); border-inline-end: 1px solid var(--border); display: flex; flex-direction: column; padding: 16px 0; z-index: 200; overflow-y: auto; transition: background 0.3s ease, border-color 0.3s ease; }
  body.rtl .sidebar { right: 0; left: auto; border-right: none; border-left: 1px solid var(--border); }
  body.ltr .sidebar { left: 0; right: auto; border-left: none; border-right: 1px solid var(--border); }
  body.light-mode .sidebar { box-shadow: -2px 0 12px rgba(0,0,0,0.06); }
  .sidebar-nav-btn { display: flex; align-items: center; gap: 10px; padding: 12px 20px; background: none; border: none; color: var(--text2); font-family: 'Noto Sans Arabic', sans-serif; font-size: 14px; font-weight: 500; cursor: pointer; width: 100%; text-align: start; border-radius: 0; transition: background 0.15s, color 0.15s, border-color 0.15s; border-inline-start: 3px solid transparent; }
  .sidebar-nav-btn:hover { background: var(--bg3); color: var(--text); }
  .sidebar-nav-btn.active { background: rgba(241,172,20,0.12); color: var(--accent); border-inline-start-color: var(--accent); font-weight: 700; }
  .sidebar-nav-btn .nav-icon { font-size: 16px; flex-shrink: 0; }
  .sidebar-label { font-size: 10px; font-weight: 700; letter-spacing: 1px; color: var(--text2); padding: 16px 20px 6px; text-transform: uppercase; opacity: 0.6; }
  .sidebar-divider { height: 1px; background: var(--border); margin: 8px 16px; }

  .app-wrapper { display: flex; flex-direction: column; min-height: 100vh; padding-top: 60px; }
  .content-area { flex: 1; }
  body.rtl .content-area { margin-right: 220px; margin-left: 0; }
  body.ltr .content-area { margin-left: 220px; margin-right: 0; }
  .main { padding: 28px 24px; }
  .page { display: none; }
  .page.active { display: block; }
  .gen-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
  @media (max-width: 1100px) { .gen-grid { grid-template-columns: 1fr; gap: 16px; } }

  /* ===== MOBILE ===== */
  @media (max-width: 768px) {
    header { padding: 0 12px; height: 52px; }
    .header-inner { height: 52px; }
    .logo-sub { display: none; }
    .logo-text { font-size: 14px; }
    .logo-img { width: 26px; height: 26px; }
    .lang-btn { padding: 4px 9px; font-size: 11px; }
    .hi-user { display: none !important; }
    #pwa-install-btn { display: none !important; }
    #logout-btn-header { display: none !important; }
    #theme-toggle-btn { display: none !important; }
    .sidebar { display: none !important; }
    body.rtl .content-area { margin-right: 0; }
    body.ltr .content-area { margin-left: 0; }
    .app-wrapper { padding-top: 52px; }
    .main { padding: 12px 10px; }
    .card { padding: 14px; border-radius: 12px; }
    .card-title { font-size: 14px; margin-bottom: 12px; padding-bottom: 10px; flex-wrap: wrap; }
    .barcode-grid { grid-template-columns: repeat(2,1fr); gap: 10px; }
    .form-group { margin-bottom: 12px; }
    .form-group label { font-size: 12px; margin-bottom: 5px; }
    .form-group input, .form-group select, .form-group textarea { padding: 9px 11px; font-size: 13px; }
    .row { flex-direction: column; gap: 0; }
    .btn { padding: 10px 14px; font-size: 13px; }
    .btn-sm { padding: 7px 10px; font-size: 12px; }
    .gen-action-row { flex-direction: row !important; gap: 8px !important; }
    .gen-action-row .btn { flex: 1; }
    .card-actions { gap: 4px; padding: 8px; flex-wrap: nowrap; }
    .card-actions .btn { flex: 1; min-width: 0; font-size: 10px; padding: 6px 2px; gap: 2px; }
    .label-size-btn { padding: 12px; }
    .modal { padding: 16px; border-radius: 14px; width: 95%; }
    .modal-title { font-size: 16px; }
    .print-queue { max-height: 260px; }
    .empty-state { padding: 28px 10px; }
    .category-chip { padding: 5px 9px; font-size: 11px; }
    .toast-container { bottom: 10px; right: 8px; left: 8px; }
    .toast { font-size: 12px; padding: 10px 12px; }
    .delete-popup-card { padding: 22px 16px; }
    .delete-popup-btns { flex-direction: column; align-items: stretch; }
    .delete-popup-btns .btn { width: 100%; min-width: unset; }
    .theme-toggle-btn { width: 48px; height: 24px; }
    .theme-toggle-btn .toggle-knob { width: 17px; height: 17px; }
    body.light-mode .theme-toggle-btn .toggle-knob { transform: translateX(24px); }
    .loading-popup-card { padding: 25px 20px; min-width: 280px; }
    .users-table-wrap th:nth-child(1), .users-table-wrap td:nth-child(1) { display: none; }
    .add-user-form-row { flex-direction: column !important; }
    .add-user-form-row .user-role-select-wrap { flex: 1 !important; }
  }
  @media (max-width: 480px) {
    .main { padding: 10px 8px; }
    .card { padding: 10px; border-radius: 10px; }
    .barcode-grid { grid-template-columns: repeat(2,1fr); gap: 7px; }
    .btn { font-size: 12px; padding: 9px 12px; }
    .btn-sm { font-size: 11px; padding: 6px 8px; }
  }

  /* ===== CARDS ===== */
  .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; }
  .card-title { font-size: 17px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border); padding-bottom: 14px; }
  .card-title .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--accent); }
  .form-group { margin-bottom: 18px; }
  .form-group label { display: block; font-size: 14px; color: var(--text2); margin-bottom: 7px; font-weight: 500; }
  .form-group input, .form-group select, .form-group textarea { width: 100%; background: var(--bg3); border: 1px solid var(--border); border-radius: 10px; padding: 11px 14px; color: var(--text); font-family: 'Noto Sans Arabic', sans-serif; font-size: 15px; outline: none; transition: border-color 0.2s ease, box-shadow 0.2s ease; }
  .form-group input:focus, .form-group select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(108,99,255,0.15); }
  .form-group input.mono { font-family: 'JetBrains Mono', monospace; letter-spacing: 2px; }
  .row { display: flex; gap: 12px; }
  .row .form-group { flex: 1; }
  .btn { padding: 12px 24px; border-radius: 10px; border: none; font-family: 'Noto Sans Arabic', sans-serif; font-weight: 600; font-size: 15px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
  .btn-primary  { background: linear-gradient(135deg, var(--accent), #8b85ff); color: white; }
  .btn-secondary{ background: var(--bg3); color: var(--text); border: 1px solid var(--border); }
  .btn-success  { background: var(--accent2); color: #0a0a0f; }
  .btn-danger   { background: var(--accent3); color: white; }
  .btn-warning  { background: #f0a500; color: #1a1a2e; }
  .btn-sm { padding: 8px 12px; font-size: 13px; border-radius: 8px; gap: 6px; }
  .badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
  .badge-green  { background: rgba(0,212,170,0.15); color: var(--accent2); }
  .badge-purple { background: rgba(108,99,255,0.15); color: var(--accent); }
  .badge-count { display: inline-flex; align-items: center; justify-content: center; background: rgba(108,99,255,0.25); color: var(--accent); border-radius: 20px; font-size: 12px; font-weight: 700; padding: 2px 10px; min-width: 28px; }
  .badge-count-red { display: inline-flex; align-items: center; justify-content: center; background: rgba(255,107,107,0.25); color: var(--accent3); border-radius: 20px; font-size: 12px; font-weight: 700; padding: 2px 10px; min-width: 28px; }
  .hint { font-size: 11px; color: var(--text2); margin-top: 5px; margin-left: 50%; }
  .barcode-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px,1fr)); gap: 16px; }
  .barcode-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; position: relative; text-align: center; }
  .card-actions { display: flex; gap: 8px; padding: 12px; border-top: 1px solid var(--border); background: var(--bg3); }
  .print-queue { background: var(--bg3); border-radius: 10px; border: 1px solid var(--border); max-height: 400px; overflow-y: auto; }
  .pq-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-bottom: 1px solid var(--border); font-size: 13px; }
  .label-size-btn { display: flex; align-items: center; justify-content: space-between; background: var(--bg3); border: 2px solid var(--border); border-radius: 12px; padding: 16px; margin-bottom: 12px; cursor: pointer; }
  .label-size-btn.selected { border-color: var(--accent); background: rgba(108,99,255,0.15); }
  .label-size-name { font-weight: 700; font-size: 16px; }
  .label-size-dim { font-size: 12px; color: var(--text2); font-family: monospace; }
  .toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 3000; display: flex; flex-direction: column; gap: 8px; }
  .toast { background: var(--bg4); border: 1px solid var(--border); border-radius: 10px; padding: 12px 20px; font-size: 13px; animation: slideIn 0.3s ease; }
  @keyframes slideIn { from{transform:translateX(100px);opacity:0} to{transform:translateX(0);opacity:1} }
  .empty-state { text-align: center; padding: 60px 20px; color: var(--text2); }
  .divider { height: 1px; background: var(--border); margin: 16px 0; }
  .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
  .modal-overlay.open { display: flex; }
  .modal { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 28px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
  .modal-title { font-size: 20px; font-weight: 700; margin-bottom: 20px; color: var(--accent2); }
  .label-container { background: white; border-radius: 4px; display: flex; flex-direction: column; justify-content: space-between; align-items: center; overflow: hidden; box-sizing: border-box; }
  .shop-name-text { font-family: 'Noto Sans Arabic', sans-serif; font-weight: 900; font-size: 14px; color: #1e3c72; text-align: center; letter-spacing: 0.5px; }
  .product-title-text { font-family: 'Tajawal','Noto Sans Arabic',sans-serif; font-weight: 500; text-align: center; color: #1a1a2e; overflow: hidden; text-overflow: ellipsis; white-space: normal; word-break: break-word; line-height: 1.2; }
  .barcode-wrapper { text-align: center; margin: 2px 0; }
  .barcode-wrapper svg { display: block; margin: 0 auto; max-width: 100%; height: auto; }
  .price-weight-row { display: flex; justify-content: space-between; align-items: center; width: 100%; margin-top: 2px; }
  .price-text { font-weight: bold; color: #2c7a2c; display: inline-flex; align-items: center; gap: 2px; }
  .weight-text { font-weight: bold; color: #a05e00; }
  .click-hint-text { font-size: 10px; color: var(--accent2); margin-top: 5px; text-align: center; opacity: 0.7; }

  /* ===== STICKY SEARCH ===== */
  .lib-sticky-zone { position: sticky; top: 60px; z-index: 99; background: var(--card); padding: 10px 0 2px; margin: 0 -28px; padding-left: 28px; padding-right: 28px; border-bottom: 1px solid var(--border); box-shadow: 0 4px 16px rgba(0,0,0,0.12); transition: background 0.3s ease; }
  .lib-search-sticky { margin-bottom: 5px !important; }
  .lib-search-sticky input { border: 1px solid #f1ac14 !important; box-shadow: 0 0 0 2px rgba(234,88,12,0.15); }
  .lib-search-sticky input:focus { border-color: #f1ac14 !important; box-shadow: 0 0 0 4px rgba(255,165,0,0.35) !important; }
  .category-filter-bar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 0 !important; padding-top: 4px; padding-bottom: 10px; }
  @media (max-width: 768px) { .lib-sticky-zone { top: 52px; margin: 0 -14px; padding-left: 14px; padding-right: 14px; } }
  @media (max-width: 480px) { .lib-sticky-zone { top: 52px; margin: 0 -10px; padding-left: 10px; padding-right: 10px; } }

  /* ===== FOOTER ===== */
  .app-footer { text-align: center; padding: 18px 16px; margin-top: 32px; border-top: 1px solid var(--border); font-size: 12px; color: var(--text2); }
  .app-footer .footer-links { display: inline-flex; align-items: center; gap: 14px; margin-top: 4px; flex-wrap: wrap; justify-content: center; }
  .app-footer a { text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; transition: opacity .2s; }
  .app-footer a:hover { opacity: 0.72; }
  .app-footer .lnk-ig { color: #e1306c; }
  .app-footer .lnk-fb { color: #1877f2; }

  /* ===== DOWNLOAD BTN ===== */
  .card-dl-btn { position: absolute; bottom: 120px; right: 10px; width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg,#F77F00,#FCBF49); color: #fff; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; box-shadow: 0 2px 8px rgba(108,99,255,0.45); transition: transform .15s, box-shadow .15s; z-index: 5; }
  .card-dl-btn:hover { transform: scale(1.12); box-shadow: 0 4px 14px rgba(108,99,255,0.6); }

  /* ===== HAMBURGER ===== */
  .hamburger { display: none; flex-direction: column; justify-content: center; gap: 5px; width: 36px; height: 36px; background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; cursor: pointer; padding: 6px; flex-shrink: 0; transition: background 0.2s; }
  .hamburger:hover { background: var(--bg4); }
  .hamburger span { display: block; height: 2px; background: var(--text); border-radius: 2px; transition: all 0.3s cubic-bezier(0.4,0,0.2,1); }
  .hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
  .hamburger.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
  .hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }
  @media (max-width: 768px) { .hamburger { display: flex; } }

  /* ===== MOBILE DRAWER ===== */
  .mobile-nav-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 500; backdrop-filter: blur(3px); }
  .mobile-nav-overlay.open { display: block; }
  .mobile-nav-drawer { position: fixed; top: 0; right: 0; width: 250px; height: 100%; background: var(--bg2); border-left: 1px solid var(--border); z-index: 501; padding: 0; display: flex; flex-direction: column; transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.4,0,0.2,1); box-shadow: -6px 0 28px rgba(0,0,0,0.35); }
  body.ltr .mobile-nav-drawer { right: auto; left: 0; border-left: none; border-right: 1px solid var(--border); transform: translateX(-100%); }
  .mobile-nav-drawer.open { transform: translateX(0) !important; }
  .drawer-logo-area { display: flex; align-items: center; gap: 10px; padding: 16px 18px 14px; border-bottom: 1px solid var(--border); background: var(--bg3); }
  .drawer-logo-area img { width: 32px; height: 32px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
  .drawer-logo-area .drawer-shop-name { font-size: 15px; font-weight: 700; color: var(--text); }
  .drawer-logo-area .drawer-sub { font-size: 10px; color: var(--text2); }
  .drawer-hi-user { padding: 10px 18px 8px; font-size: 13px; font-weight: 700; color: var(--accent); background: rgba(241,172,20,0.08); border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
  .drawer-nav-btn { display: flex; align-items: center; gap: 10px; padding: 13px 18px; background: none; border: none; color: var(--text2); font-family: 'Noto Sans Arabic', sans-serif; font-size: 15px; font-weight: 500; cursor: pointer; width: 100%; text-align: start; transition: background 0.15s, color 0.15s; border-inline-start: 3px solid transparent; }
  .drawer-nav-btn:hover { background: var(--bg3); color: var(--text); }
  .drawer-nav-btn.active { background: rgba(241,172,20,0.12); color: var(--accent); border-inline-start-color: var(--accent); font-weight: 700; }
  .drawer-theme-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; border-top: 1px solid var(--border); }
  .drawer-theme-label { font-size: 14px; font-weight: 500; color: var(--text2); }
  .drawer-logout { display: flex; align-items: center; gap: 10px; padding: 14px 18px; background: none; border: none; color: var(--accent3); font-family: 'Noto Sans Arabic', sans-serif; font-size: 15px; font-weight: 600; cursor: pointer; width: 100%; text-align: start; text-decoration: none; border-top: 1px solid var(--border); margin-top: auto; transition: background 0.15s; }
  .drawer-logout:hover { background: rgba(255,107,107,0.1); }

  /* ===== TOGGLE SWITCH ===== */
  .toggle-row { display: flex; align-items: center; justify-content: space-between; background: var(--bg3); border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; margin-bottom: 18px; }
  .toggle-label { font-size: 14px; color: var(--text2); font-weight: 500; }
  .toggle-switch { position: relative; width: 46px; height: 24px; flex-shrink: 0; }
  .toggle-switch input { display: none; }
  .toggle-slider { position: absolute; inset: 0; background: #444; border-radius: 24px; cursor: pointer; transition: background 0.25s; }
  .toggle-slider::before { content: ''; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px; background: #aaa; border-radius: 50%; transition: transform 0.25s, background 0.25s; }
  .toggle-switch input:checked + .toggle-slider { background: var(--accent2); }
  .toggle-switch input:checked + .toggle-slider::before { transform: translateX(22px); background: #fff; }
  .toggle-status { font-size: 12px; font-weight: 700; margin-inline-start: 10px; min-width: 52px; color: var(--text2); }
  .toggle-status.on { color: var(--accent2); }
  .toggle-status.off { color: #888; }

  /* ===== CATEGORY CHIPS ===== */
  .category-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; cursor: pointer; border: 2px solid transparent; background: var(--bg3); color: var(--text2); border-color: var(--border); }
  .category-chip:hover { border-color: var(--accent); color: var(--text); }
  .category-chip.active { background: rgba(108,99,255,0.2); border-color: var(--accent); color: var(--accent); }
  .category-chip.expired-chip { background: rgba(255,107,107,0.1); border-color: rgba(255,107,107,0.3); color: var(--accent3); }
  .category-chip.expired-chip.active { background: rgba(255,107,107,0.2); border-color: var(--accent3); color: var(--accent3); }
  .category-chip .chip-del { font-size: 10px; color: var(--accent3); font-weight: 700; margin-inline-start: 4px; }
  .cat-badge { font-size: 9px; padding: 1px 7px; border-radius: 10px; font-weight: 700; background: rgba(108,99,255,0.2); color: var(--accent); margin-top: 3px; display: inline-block; }
  .exp-date-text { font-weight: bold; color: #e07800; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .exp-date-urgent { color: #cc0000; }
  .barcode-card.expired-card { border-color: rgba(255,107,107,0.35); background: linear-gradient(135deg,var(--card),rgba(255,107,107,0.04)); }
  .barcode-card.expired-card:hover { border-color: var(--accent3); box-shadow: 0 16px 40px rgba(255,107,107,0.3); }
  .expired-badge-overlay { position: absolute; top: 8px; left: 8px; background: var(--accent3); color: white; font-size: 9px; font-weight: 700; padding: 3px 8px; border-radius: 20px; z-index: 2; }
  body.ltr .expired-badge-overlay { left: auto; right: 8px; }
  .barcode-card.disabled-card { filter: grayscale(100%) opacity(0.55); border-color: #444 !important; box-shadow: none !important; }
  .barcode-card.disabled-card:hover { transform: none !important; box-shadow: none !important; }
  .disabled-badge-overlay { position: absolute; top: 8px; right: 8px; background: #555; color: #ccc; font-size: 9px; font-weight: 700; padding: 3px 8px; border-radius: 20px; z-index: 2; }
  body.ltr .disabled-badge-overlay { right: auto; left: 8px; }
  .category-chip.disabled-chip { background: rgba(100,100,120,0.15); border-color: #444; color: #888; }
  .category-chip.disabled-chip.active { background: rgba(100,100,120,0.3); border-color: #777; color: #bbb; }
  .lib-product-title { font-size: 14px; font-weight: 700; color: #ea580c; margin-bottom: 5px; font-family: 'Inter','Noto Sans Arabic',Arial,sans-serif; }
  .lib-barcode-number { font-family: monospace; font-size: 11px; color: var(--text2); margin-top: 4px; font-weight: 600; }
  .lib-creator { font-size: 10px; color: var(--accent2); margin-top: 6px; padding-top: 2px; border-top: 1px dashed var(--border); display: inline-block; width: 100%; text-align: center; }

  /* ===== PASSWORD EYE TOGGLE ===== */
  .pw-wrap { position: relative; flex: 1; min-width: 150px; display: flex; align-items: center; }
  .pw-wrap input { width: 100%; padding-inline-end: 42px !important; }
  .eye-btn { position: absolute; inset-inline-end: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 16px; padding: 4px; line-height: 1; color: var(--text2); transition: color 0.2s; display: flex; align-items: center; justify-content: center; border-radius: 4px; }
  .eye-btn:hover { color: var(--accent); }
  .eye-btn.active { color: var(--accent); }

  /* ===== USERS PAGE ===== */
  .user-mgmt-page { max-width: 920px; width: 100%; }

  .add-user-form-row {
    display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 0;
  }
  .add-user-form-row input {
    flex: 1; min-width: 130px; background: var(--bg3); border: 1px solid var(--border);
    border-radius: 10px; padding: 11px 14px; color: var(--text);
    font-family: 'Noto Sans Arabic', sans-serif; font-size: 14px; outline: none;
    transition: border-color 0.2s;
  }
  .add-user-form-row input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(241,172,20,0.15); }

  .user-role-select-wrap { flex: 0 0 140px; }
  .user-role-select {
    width: 100%; background: var(--bg3); border: 1px solid var(--border);
    border-radius: 10px; padding: 11px 14px; color: var(--text);
    font-family: 'Noto Sans Arabic', sans-serif; font-size: 14px; outline: none;
    transition: border-color 0.2s, background 0.2s, color 0.2s; cursor: pointer;
  }
  .user-role-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(241,172,20,0.15); }
  .user-role-select.is-admin-role {
    border-color: rgba(255,107,107,0.55) !important;
    background: rgba(255,107,107,0.06) !important;
    color: #e05555 !important;
  }

  .users-table-wrap { overflow-x: auto; margin-top: 12px; border-radius: 12px; border: 1px solid var(--border); }
  .users-table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 420px; }
  .users-table thead tr { background: var(--bg3); border-bottom: 2px solid var(--accent); }
  .users-table thead th { padding: 13px 16px; text-align: start; font-weight: 700; font-size: 12px; color: var(--accent); white-space: nowrap; letter-spacing: 0.3px; }
  .users-table thead th:last-child { text-align: center; }
  .users-table thead th.th-role { text-align: center; }
  .users-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s ease; }
  .users-table tbody tr:last-child { border-bottom: none; }
  .users-table tbody tr:nth-child(even) { background: rgba(255,255,255,0.02); }
  .users-table tbody tr:hover { background: rgba(241,172,20,0.06); }
  .users-table tbody td { padding: 12px 16px; vertical-align: middle; }
  .users-table tbody td:last-child { text-align: center; }
  .users-table .user-id-cell { font-family: monospace; font-size: 12px; font-weight: 700; color: var(--accent); background: rgba(241,172,20,0.12); padding: 4px 10px; border-radius: 6px; display: inline-block; white-space: nowrap; }
  .users-table .user-name-cell { display: flex; align-items: center; gap: 10px; }
  .users-table .user-avatar-sm { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 16px; }
  .users-table .user-avatar-admin { background: rgba(255,107,107,0.14); border: 1.5px solid rgba(255,107,107,0.3); }
  .users-table .user-avatar-user  { background: rgba(241,172,20,0.14); border: 1.5px solid rgba(241,172,20,0.3); }
  .users-table .user-name-text { font-weight: 600; color: var(--text); font-size: 14px; }
  .users-table .user-sub-text { font-size: 10px; color: var(--text2); margin-top: 1px; }
  .users-table .action-btns { display: flex; gap: 8px; justify-content: center; align-items: center; }

  .role-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; white-space: nowrap;
  }
  .role-badge-admin {
    background: rgba(255,107,107,0.13); color: #d63031;
    border: 1px solid rgba(255,107,107,0.32);
  }
  .role-badge-user {
    background: rgba(241,172,20,0.13); color: #d4920a;
    border: 1px solid rgba(241,172,20,0.32);
  }
  body.light-mode .role-badge-admin { background: rgba(255,107,107,0.1); color: #c0392b; }
  body.light-mode .role-badge-user  { background: rgba(241,172,20,0.1); color: #b7800a; }

  .role-admin-warning {
    display: none; background: rgba(255,107,107,0.1);
    border: 1px solid rgba(255,107,107,0.3); border-radius: 8px;
    padding: 8px 12px; font-size: 12px; color: var(--accent3);
    margin-top: 6px;
  }
  .role-admin-warning.visible { display: flex; align-items: center; gap: 6px; }

  .section-micro-label {
    font-size: 11px; font-weight: 700; letter-spacing: 0.5px;
    color: var(--text2); text-transform: uppercase; margin-bottom: 10px;
    display: flex; align-items: center; gap: 8px;
  }
  .section-micro-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

  body.light-mode .users-table tbody tr:nth-child(even) { background: rgba(0,0,0,0.02); }
  body.light-mode .users-table thead tr { background: var(--bg3); }

  /* ===== PRINT ===== */
  @media print {
    body * { visibility: hidden; }
    .print-label-page, .print-label-page * { visibility: visible; }
    .print-label-page { position: absolute; top: 0; left: 0; width: 100%; height: 100%; margin: 0; padding: 0; }
    @page { margin: 0; size: auto; }
  }

  /* ===== LOGO PREVIEW ===== */
  .logo-preview { margin-top: 10px; padding: 10px; background: var(--bg3); border-radius: 8px; text-align: center; }
  .logo-preview img { max-width: 60px; max-height: 60px; border-radius: 8px; }

  /* ===== PRELOADER ===== */
  #preloader{position:fixed;inset:0;background:#ffffff;display:flex;justify-content:center;align-items:center;z-index:999999;}
  .loader-container{display:flex;flex-direction:column;align-items:center;gap:28px;}
  .logo-circle{position:relative;width:140px;height:140px;display:flex;justify-content:center;align-items:center;}
  .circle-svg{position:absolute;width:140px;height:140px;rotate:-90deg;}
  .circle-path{fill:none;stroke:#ff930f;stroke-width:4;stroke-linecap:round;stroke-dasharray:100 240;animation:circleMove 2s linear infinite;}
  .preloader-logo{width:100px;height:100px;border-radius:50%;object-fit:contain;animation:logoFloat 2s ease-in-out infinite;}
  .loading-text{direction:rtl;display:flex;gap:12px;font-size:34px;font-weight:800;font-family:Tahoma,sans-serif;}
  .loading-text span{color:#c8c8c8;position:relative;overflow:hidden;}
  .loading-text span::after{content:"";position:absolute;inset:0;color:#ff930f;width:0%;overflow:hidden;white-space:nowrap;animation:textFill 2s infinite;}
  .loading-text span:nth-child(1)::after{content:"كيف";animation-delay:0s;}
  .loading-text span:nth-child(2)::after{content:"الضيافة";animation-delay:1s;}
  @keyframes circleMove{100%{stroke-dashoffset:-340;}}
  @keyframes logoFloat{0%,100%{transform:translateY(0);}50%{transform:translateY(-8px);}}
  @keyframes textFill{0%{width:0%;}50%{width:100%;}100%{width:0%;}}
  @media(max-width:480px){ .logo-circle{width:110px;height:110px;} .circle-svg{width:110px;height:110px;} .preloader-logo{width:72px;height:72px;} .loading-text{font-size:24px;} }

  .developer-credit{display:flex !important;flex-direction:column !important;align-items:center !important;justify-content:center;margin-top:15px;text-align:center;gap:0 !important;}
  .developer-credit .dev-text{display:block !important;width:100%;font-size:14px;font-weight:600;letter-spacing:3px;text-transform:uppercase;color:#f1ac14 !important;margin-bottom:5px !important;line-height:1;}
  .developer-credit .dev-name{display:block !important;width:100%;font-size:16px;font-weight:700;letter-spacing:4px;line-height:1;color:#f1ac14 !important;margin:0 !important;text-shadow:0 0 3px rgba(241,172,20,.9),0 0 8px rgba(241,172,20,.7),0 0 15px rgba(241,172,20,.5);animation:textShine 2s infinite ease-in-out;}
  @keyframes textShine{0%,100%{color:#f1ac14;text-shadow:0 0 3px rgba(241,172,20,.9),0 0 8px rgba(241,172,20,.7),0 0 15px rgba(241,172,20,.5);}50%{color:#ffffff;text-shadow:0 0 5px #ffffff,0 0 10px #ffffff,0 0 20px #ffffff,0 0 30px #ffffff;}}
</style>
</head>
<body class="rtl <?php echo $isAdmin ? 'admin-user' : ''; ?>" data-admin="<?php echo $isAdmin ? 'true' : 'false'; ?>">

<!-- PRELOADER -->
<div id="preloader">
  <div class="loader-container">
    <div class="logo-circle">
      <img src="image/logo.png" alt="Logo" class="preloader-logo">
      <svg class="circle-svg" viewBox="0 0 120 120">
        <circle cx="60" cy="60" r="54" class="circle-path"/>
      </svg>
    </div>
    <div class="loading-text"><span>كيف</span><span>الضيافة</span></div>
    <div class="developer-credit">
      <div class="dev-text">Developed By</div>
      <div class="dev-name">MR.AMIN</div>
    </div>
  </div>
</div>

<!-- LOADING POPUP -->
<div id="loading-popup" class="loading-popup-overlay">
  <div class="loading-popup-card">
    <button class="loading-close-btn" onclick="closeLoadingPopupManually()"><i class="fas fa-times"></i></button>
    <div class="loading-popup-icon"><i class="fas fa-spinner"></i></div>
    <div class="loading-popup-title" id="loading-title"><?php echo $isAdmin ? '👑 ' : ''; ?>Loading...</div>
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

    <!-- In RTL layout: header-left renders on the RIGHT side visually -->
    <!-- Lang toggle here = RIGHT side of header -->
    <div class="header-left">
      <div class="lang-toggle">
        <button class="lang-btn active" id="btn-ar" onclick="setLanguage('ar')">AR</button>
        <button class="lang-btn" id="btn-en" onclick="setLanguage('en')">EN</button>
      </div>
    </div>

    <!-- Centered logo -->
    <div class="logo">
      <a class="logo-link" href="#" onclick="event.preventDefault();showPage('library');">
        <img id="header-logo" src="image/logo.png" alt="Logo" class="logo-img">
        <div>
          <div class="logo-text" id="header-shop-name">الضيافة</div>
          <div class="logo-sub" id="header-subtitle">نظام طباعة الباركود</div>
        </div>
      </a>
    </div>

    <!-- In RTL layout: header-right renders on the LEFT side visually -->
    <!-- Theme toggle here = LEFT side of header -->
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
    <div class="sidebar-user-avatar"><?php echo $isAdmin ? '👑' : '👤'; ?></div>
    <div class="sidebar-user-info">
      <div class="sidebar-user-name"><?php echo htmlspecialchars($loggedInUser); ?></div>
      <div class="sidebar-user-role" id="sb-user-role-text"><?php echo $isAdmin ? 'مدير النظام' : 'مستخدم نشط'; ?></div>
    </div>
  </div>
  <div class="sidebar-greeting">
    <i class="fas fa-hand-sparkles" style="font-size:13px;"></i>
    <span class="sb-greeting-text" id="sb-greeting-label">مرحباً,</span>
    <span class="sb-username"><?php echo htmlspecialchars($loggedInUser); ?></span>
    <?php if($isAdmin): ?><span class="sb-admin-badge">Super-Admin</span><?php endif; ?>
  </div>
  <div class="sidebar-label" id="sb-label-menu">القائمة</div>
  <button class="sidebar-nav-btn" id="nav-generator" onclick="showPage('generator')">
    <span class="nav-icon"><i class="fas fa-barcode"></i></span> <span id="sb-txt-generator">المولد</span>
  </button>
  <button class="sidebar-nav-btn active" id="nav-library" onclick="showPage('library')">
    <span class="nav-icon"><i class="fas fa-database"></i></span> <span id="sb-txt-library">باركود</span>
  </button>
  <button class="sidebar-nav-btn" id="nav-print" onclick="showPage('print')">
    <span class="nav-icon"><i class="fas fa-print"></i></span> <span id="sb-txt-print">الطباعة</span>
  </button>
  <?php if($isAdmin): ?>
  <button class="sidebar-nav-btn" id="nav-users" onclick="showPage('users')">
    <span class="nav-icon"><i class="fas fa-users"></i></span> <span id="sb-txt-users">المستخدمون</span>
  </button>
  <?php endif; ?>
  <button class="sidebar-nav-btn" id="nav-settings" onclick="showPage('settings')">
    <span class="nav-icon"><i class="fas fa-cog"></i></span> <span id="sb-txt-settings">الإعدادات</span>
  </button>
  <div style="flex:1;"></div>
  <div class="sidebar-divider"></div>
  <a href="logout.php" class="sidebar-logout-btn">
    <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
    <span id="sb-txt-logout">تسجيل خروج</span>
    <span class="sidebar-logout-user">(<?php echo htmlspecialchars($loggedInUser); ?>)</span>
  </a>
  <div class="sidebar-copyright">
    <span class="copy-by">Developed By</span>
    <a href="https://facebook.com/mr.amin4125" target="_blank" class="copy-name">MR.AMIN</a>
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
    <i class="fas fa-user-circle"></i>
    <span><?php echo htmlspecialchars($loggedInUser); ?></span>
    <?php if($isAdmin): ?><span class="badge" style="background:var(--accent);color:white;font-size:9px;padding:2px 6px;">Admin</span><?php endif; ?>
  </div>
  <button class="drawer-nav-btn" id="d-nav-generator" onclick="showPage('generator');closeMobileNav()">
    <i class="fas fa-barcode"></i> <span id="d-txt-generator">المولد</span>
  </button>
  <button class="drawer-nav-btn active" id="d-nav-library" onclick="showPage('library');closeMobileNav()">
    <i class="fas fa-database"></i> <span id="d-txt-library">باركود</span>
  </button>
  <button class="drawer-nav-btn" id="d-nav-print" onclick="showPage('print');closeMobileNav()">
    <i class="fas fa-print"></i> <span id="d-txt-print">الطباعة</span>
  </button>
  <?php if($isAdmin): ?>
  <button class="drawer-nav-btn" id="d-nav-users" onclick="showPage('users');closeMobileNav()">
    <i class="fas fa-users"></i> <span id="d-txt-users">المستخدمون</span>
  </button>
  <?php endif; ?>
  <button class="drawer-nav-btn" id="d-nav-settings" onclick="showPage('settings');closeMobileNav()">
    <i class="fas fa-cog"></i> <span id="d-txt-settings">الإعدادات</span>
  </button>
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

<!-- ===========================
     GENERATOR PAGE
=========================== -->
<div class="page" id="page-generator">
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
            <input type="text" id="m-weight" placeholder="500g" oninput="updatePreviewFromManual()" />
          </div>
        </div>
        <div class="form-group">
          <label id="label-expdate">تاريخ الانتهاء <span id="expdate-optional" style="font-size:11px;color:var(--text2);">(اختياري)</span></label>
          <input type="date" id="m-expdate" oninput="updatePreviewFromManual()" />
        </div>
        <div class="form-group">
          <label id="label-barcode">رقم الباركود EAN-13</label>
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="text" id="m-barcode" class="mono" maxlength="13" placeholder="880XXXXXXXXXX" oninput="onManualInput()" style="flex:1;" />
            <button class="btn btn-warning btn-sm" id="random-btn" onclick="genRandomBarcode()" style="flex-shrink:0;white-space:nowrap;">🎲</button>
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
        <button id="download-barcode-btn" class="btn btn-warning btn-sm" onclick="downloadBarcodeHighRes()" style="width:100%;margin-top:10px;display:none;"><span id="download-btn-text">تحميل PNG جودة عالية</span></button>
      </div>
    </div>
  </div>
</div>

<!-- ===========================
     LIBRARY PAGE
=========================== -->
<div class="page" id="page-library">
  <div class="card">
    <div class="card-title">
      <span class="dot"></span>
      <span id="library-title">مكتبة الباركود</span>
      <span class="badge-count" id="library-count-badge">0</span>
    </div>
    <div class="lib-sticky-zone">
      <div class="lib-search-sticky form-group">
        <input type="text" id="lib-search" placeholder=" بحث بالاسم أو رقم الباركود..." oninput="renderLibrary()" style="width:100%" />
      </div>
      <div class="category-filter-bar" id="lib-category-filter"></div>
    </div>
    <div style="height:12px;"></div>
    <div class="barcode-grid" id="barcode-grid"></div>
    <div class="click-hint-text" id="click-hint-text">💡 اضغط على أي باركود للذهاب إلى صفحة الطباعة</div>
  </div>
</div>

<!-- ===========================
     PRINT PAGE
=========================== -->
<div class="page" id="page-print">
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
</div>

<!-- ===========================
     USERS PAGE (Admin only)
=========================== -->
<?php if($isAdmin): ?>
<div class="page" id="page-users">
  <div class="user-mgmt-page">
    <div class="card">
      <div class="card-title">
        <span class="dot" style="background:var(--accent2)"></span>
        <span id="users-page-title">إدارة المستخدمين</span>
        <span class="badge-count" id="users-count-badge">0</span>
      </div>

      <div id="user-alert-box" style="display:none;margin-bottom:14px;"></div>

      <div class="section-micro-label" id="add-user-label">➕ إضافة مستخدم جديد</div>
      <div class="add-user-form-row">
        <input type="text" id="new-username-input" placeholder="اسم المستخدم" autocomplete="off" />
        <div class="pw-wrap">
          <input type="password" id="new-password-input" placeholder="كلمة المرور (6+ أرقام)" autocomplete="new-password" />
          <button type="button" class="eye-btn" onclick="toggleEye('new-password-input','eye-new')" tabindex="-1">
            <span id="eye-new"><i class="fa-regular fa-eye"></i></span>
          </button>
        </div>
        <div class="user-role-select-wrap">
          <select id="new-role-select" class="user-role-select" onchange="onRoleSelectChange(this)">
            <option value="0">👤 مستخدم</option>
            <option value="1">👑 مشرف</option>
          </select>
        </div>
        <button type="button" class="btn btn-warning btn-sm" onclick="ajaxAddUser()" style="flex-shrink:0;white-space:nowrap;align-self:center;">
          <i class="fas fa-plus"></i> <span id="add-user-btn-text">إضافة</span>
        </button>
      </div>
      <div style="font-size:11px;color:var(--text2);margin-top:6px;margin-bottom:4px;" id="pass-hint-text">
        كلمة المرور: أرقام فقط (6 أو أكثر) · المشرف يملك صلاحيات كاملة بما فيها إدارة المستخدمين
      </div>

      <div class="divider"></div>

      <div id="users-cards-container"></div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ===========================
     SETTINGS PAGE
=========================== -->
<div class="page" id="page-settings">
  <div style="max-width:600px;width:100%">

    <?php if($permSettings): ?>
    <div class="card">
      <div class="card-title"><span class="dot"></span> <span id="settings-header-title">إعدادات الرأس والمتجر</span></div>
      <?php if($permShopName): ?>
      <div class="form-group">
        <label id="shop-name-label">اسم المتجر</label>
        <input type="text" id="shop-name-setting" value="الضيافة" oninput="updateHeaderPreview()" />
      </div>
      <?php endif; ?>
      <?php if($permLogo): ?>
      <div class="form-group">
        <label id="logo-url-label">رابط الشعار</label>
        <input type="text" id="logo-url-setting" placeholder="https://example.com/logo.png" oninput="updateLogoPreview()" />
        <div class="hint" id="logo-hint">أدخل رابط الصورة المباشر</div>
      </div>
      <div class="logo-preview" id="logo-preview-area">
        <div style="font-size:11px;color:var(--text2);margin-bottom:5px;" id="logo-preview-text">معاينة الشعار:</div>
        <img id="logo-preview-img" src="image/logo.png" alt="معاينة" style="max-width:60px;max-height:60px;border-radius:8px;">
      </div>
      <?php endif; ?>
      <?php if($permShopName || $permLogo): ?>
      <button class="btn btn-primary" id="save-header-btn" onclick="saveHeaderSettings()" style="width:100%;">💾 حفظ إعدادات الرأس</button>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card" style="margin-top:16px">
      <div class="card-title"><span class="dot" style="background:var(--accent2)"></span> <span id="categories-settings-title">إدارة التصنيفات</span></div>
      <div style="display:flex;gap:8px;margin-bottom:10px;align-items:center;">
        <input type="text" id="new-category-input" placeholder="اسم التصنيف الجديد..." style="flex:1;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:10px 14px;color:var(--text);font-family:inherit;font-size:14px;outline:none;" onkeydown="if(event.key==='Enter')addCategory()" />
        <button id="add-category-btn" onclick="addCategory()" style="background:var(--accent);color:white;border:none;border-radius:10px;padding:10px 14px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0;">➕</button>
      </div>
      <div id="categories-list" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
    </div>

    <div class="card" style="margin-top:16px">
      <div class="card-title"><span class="dot" style="background:#f0a500"></span> <span id="currency-settings-title">إعداد العملة</span></div>
      <div class="form-group">
        <label id="currency-label">اختر العملة / الرمز الذي يظهر على الملصق</label>
        <select id="currency-select" onchange="saveCurrencySetting()" style="background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:11px 14px;color:var(--text);font-family:inherit;font-size:15px;width:100%;outline:none;">
          <option value="SAR">SAR</option>
          <option value="ريال" selected>ريال</option>
          <option value="riyal_new">ريال (رمز جديد ﷼)</option>
          <option value="BDT">BDT</option>
          <option value="BDT_symbol">৳ (BDT Symbol)</option>
        </select>
      </div>
      <div style="margin-top:10px;padding:10px;background:var(--bg3);border-radius:8px;font-size:13px;" id="currency-preview-box">
        <span id="currency-preview-label">معاينة:</span> <span id="currency-preview-text" style="font-size:16px;font-weight:700;color:var(--accent2)"></span>
      </div>
    </div>

    <?php if($permDataMang): ?>
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
    <?php endif; ?>

    <div class="card" style="margin-top:16px;display:none;" id="default-settings-card">
      <div class="card-title"><span class="dot" style="background:#f0a500"></span> <span id="default-settings-title">الإعدادات الافتراضية</span></div>
      <div class="form-group">
        <label id="default-prefix-label">البادئة الافتراضية</label>
        <input type="text" id="default-prefix" value="8801234" maxlength="12" class="mono" />
        <div class="hint" id="prefix-hint">تستخدم عند النقر على "إنشاء رقم عشوائي"</div>
      </div>
      <button class="btn btn-secondary" id="save-defaults-btn" onclick="saveBarcodeDefaults()" style="width:100%;">💾 حفظ الإعدادات الافتراضية</button>
    </div>

  </div>
</div>

    </div><!-- .main -->
  </div><!-- .content-area -->

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
        <div class="form-group"><label id="edit-weight-label">الوزن</label><input type="text" id="edit-weight" placeholder="500g" /></div>
      </div>
      <div class="form-group">
        <label id="edit-expdate-label">تاريخ الانتهاء <span style="font-size:11px;color:var(--text2);">(اختياري)</span></label>
        <input type="date" id="edit-expdate" style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:11px 14px;color:var(--text);font-family:inherit;font-size:15px;outline:none;" />
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

  <!-- USER EDIT MODAL -->
  <div class="modal-overlay" id="user-edit-modal">
    <div class="modal" style="max-width:420px;">
      <div class="modal-title" style="color:var(--accent);">✏️ <span id="user-edit-modal-title">تعديل المستخدم</span></div>
      <div id="user-edit-alert" style="display:none;margin-bottom:12px;"></div>
      <div class="form-group">
        <label id="edit-user-name-label">اسم المستخدم</label>
        <input type="text" id="edit-user-name-input" autocomplete="off" />
      </div>
      <div class="form-group">
        <label id="edit-user-pass-label">كلمة المرور الجديدة (6+ أرقام)</label>
        <div class="pw-wrap">
          <input type="password" id="edit-user-pass-input" autocomplete="new-password" />
          <button type="button" class="eye-btn" onclick="toggleEye('edit-user-pass-input','eye-edit')" tabindex="-1">
            <span id="eye-edit"><i class="fa-regular fa-eye"></i></span>
          </button>
        </div>
      </div>
      <div class="form-group">
        <label id="edit-user-role-label">الصلاحية / Role</label>
        <select id="edit-user-role-select" class="user-role-select" onchange="onRoleSelectChange(this)">
          <option value="0">👤 مستخدم (User) — صلاحيات محدودة</option>
          <option value="1">👑 مشرف (Admin) — صلاحيات كاملة</option>
        </select>
        <div class="role-admin-warning" id="role-admin-warning">
          <i class="fas fa-exclamation-triangle"></i>
          سيحصل هذا المستخدم على صلاحيات كاملة بما فيها إدارة المستخدمين!
        </div>
      </div>
      <div class="row" style="margin-top:18px;">
        <button type="button" class="btn btn-primary" onclick="ajaxEditUser()">💾 <span id="save-user-edit-text">حفظ التغييرات</span></button>
        <button type="button" class="btn btn-secondary" onclick="closeUserEditModal()"><span id="cancel-user-edit-text">إلغاء</span></button>
      </div>
    </div>
  </div>

  <!-- USER DELETE POPUP -->
  <div id="user-delete-popup" class="delete-popup-overlay" style="display:none;" onclick="if(event.target===this)closeUserDeletePopup()">
    <div class="delete-popup-card">
      <span class="delete-popup-icon">🗑️</span>
      <div class="delete-popup-title" id="udel-title">تأكيد الحذف</div>
      <div class="delete-popup-msg" id="udel-msg">هل تريد حذف هذا المستخدم؟</div>
      <span class="delete-popup-name" id="udel-name"></span>
      <div class="delete-popup-btns">
        <button class="btn btn-danger" onclick="confirmDeleteUser()" style="flex:none;min-width:130px;">🗑️ <span id="udel-confirm-text">نعم، احذف</span></button>
        <button class="btn btn-secondary" onclick="closeUserDeletePopup()" style="flex:none;min-width:110px;">❌ <span id="udel-cancel-text">إلغاء</span></button>
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

  <footer class="app-footer">
    <div>Developed By</div>
    <div class="footer-links">
      <a class="lnk-ig" href="https://instagram.com/al.amin125" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" style="width:16px;height:16px;vertical-align:middle;" fill="none"><rect x="2" y="2" width="20" height="20" rx="5" ry="5" stroke="#e1306c" stroke-width="2" fill="none"/><circle cx="12" cy="12" r="4.5" stroke="#e1306c" stroke-width="2" fill="none"/><circle cx="17.5" cy="6.5" r="1.2" fill="#e1306c"/></svg>
        Mr.Amin
      </a>
      <a class="lnk-fb" href="https://facebook.com/mr.amin4125" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" style="width:16px;height:16px;vertical-align:middle;" fill="#1877f2"><path d="M22 12c0-5.522-4.478-10-10-10S2 6.478 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987H7.898V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.891h-2.33v6.987C18.343 21.128 22 16.991 22 12z"/></svg>
        Mr.Amin
      </a>
    </div>
  </footer>

</div><!-- .app-wrapper -->

<div class="toast-container" id="toast-container"></div>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function(){
  var p = document.getElementById('preloader');
  p.style.transition = 'opacity 0.5s ease';
  p.style.opacity = '0';
  setTimeout(function(){ p.style.display = 'none'; }, 500);
});

// ============================================================
// LOADING POPUP
// ============================================================
let loadingTimerInterval = null;
let loadingAutoCloseTimeout = null;
let isLoadingDataComplete = false;

function showLoadingPopup(){
  var popup = document.getElementById('loading-popup');
  if(popup){ popup.style.display = 'flex'; }
  startLoadingTimer(5);
}
function updateLoadingMessage(msg, pct){
  var m = document.getElementById('loading-message');
  var p = document.getElementById('loading-progress');
  if(m) m.innerHTML = msg;
  if(p && pct !== undefined) p.style.width = pct + '%';
}
function startLoadingTimer(seconds){
  var left = seconds;
  var span = document.getElementById('timer-seconds-loading');
  if(loadingTimerInterval) clearInterval(loadingTimerInterval);
  if(loadingAutoCloseTimeout) clearTimeout(loadingAutoCloseTimeout);
  loadingTimerInterval = setInterval(function(){
    left--;
    if(span) span.textContent = left;
    if(left <= 0){ clearInterval(loadingTimerInterval); loadingTimerInterval = null; if(!isLoadingDataComplete) forceCloseLoadingPopup(); }
  }, 1000);
  loadingAutoCloseTimeout = setTimeout(function(){ if(!isLoadingDataComplete) forceCloseLoadingPopup(); }, seconds * 1000);
}
function forceCloseLoadingPopup(){
  var popup = document.getElementById('loading-popup');
  if(popup){ popup.style.opacity='0'; popup.style.transition='opacity 0.3s ease'; setTimeout(function(){ popup.style.display='none'; },300); }
  if(loadingTimerInterval) clearInterval(loadingTimerInterval);
  if(loadingAutoCloseTimeout) clearTimeout(loadingAutoCloseTimeout);
}
function closeLoadingPopupManually(){ if(loadingTimerInterval) clearInterval(loadingTimerInterval); if(loadingAutoCloseTimeout) clearTimeout(loadingAutoCloseTimeout); forceCloseLoadingPopup(); }
function closeLoadingPopupAndWait(){ if(loadingTimerInterval) clearInterval(loadingTimerInterval); if(loadingAutoCloseTimeout) clearTimeout(loadingAutoCloseTimeout); forceCloseLoadingPopup(); }
function closeLoadingPopup(){ isLoadingDataComplete = true; forceCloseLoadingPopup(); }

// ============================================================
// GLOBAL STATE
// ============================================================
const API_BASE = 'api/';
let USE_PHP = false;
let barcodes = [];
let printQueue = JSON.parse(localStorage.getItem('bc-queue') || '[]');
let shopName = 'الضيافة';
let logoUrl = 'image/logo.png';
let defaultPrefix = '8801234';
let selectedLabelSize = localStorage.getItem('selected_label_size') || '6x4';
let currentEditId = null;
let currentLanguage = localStorage.getItem('language') || 'ar';
let categories = [];
let selectedCategoryFilter = '';
let selectedCurrency = localStorage.getItem('bc-currency') || 'ريال';
let pendingDeleteId = null;
let currentPreviewCode = '';
let currentPreviewTitle = '';
let currentPreviewPrice = '';
let currentPreviewWeight = '';
let currentPreviewExpDate = '';
const isAdminUser = document.body.getAttribute('data-admin') === 'true';
const loggedInUsername = "<?php echo addslashes($loggedInUser); ?>";
const loggedInUserId   = <?php echo $loggedInId; ?>;

// ============================================================
// PHP API
// ============================================================
async function phpGet(ep){ const r=await fetch(API_BASE+ep,{cache:'no-store'}); if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); }
async function phpPost(ep,data){ const r=await fetch(API_BASE+ep,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}); if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); }

// ============================================================
// INDEXEDDB
// ============================================================
const IDB_NAME='BarcodeAppDB',IDB_VER=1;
let _idb=null;
function openIDB(){return new Promise((res,rej)=>{if(_idb){res(_idb);return;}const req=indexedDB.open(IDB_NAME,IDB_VER);req.onupgradeneeded=e=>{const db=e.target.result;if(!db.objectStoreNames.contains('barcodes'))db.createObjectStore('barcodes',{keyPath:'id'});if(!db.objectStoreNames.contains('categories'))db.createObjectStore('categories',{keyPath:'name'});if(!db.objectStoreNames.contains('settings'))db.createObjectStore('settings',{keyPath:'key'});};req.onsuccess=e=>{_idb=e.target.result;res(_idb);};req.onerror=e=>rej(e.target.error);});}
async function idbGetAll(store){const db=await openIDB();return new Promise((res,rej)=>{const r=db.transaction(store,'readonly').objectStore(store).getAll();r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error);});}
async function idbPut(store,obj){const db=await openIDB();return new Promise((res,rej)=>{const r=db.transaction(store,'readwrite').objectStore(store).put(obj);r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error);});}
async function idbDelete(store,key){const db=await openIDB();return new Promise((res,rej)=>{const r=db.transaction(store,'readwrite').objectStore(store).delete(key);r.onsuccess=()=>res();r.onerror=()=>rej(r.error);});}
async function idbClear(store){const db=await openIDB();return new Promise((res,rej)=>{const r=db.transaction(store,'readwrite').objectStore(store).clear();r.onsuccess=()=>res();r.onerror=()=>rej(r.error);});}
async function idbGetSetting(key,fallback){try{const db=await openIDB();return new Promise(res=>{const r=db.transaction('settings','readonly').objectStore('settings').get(key);r.onsuccess=()=>res(r.result?r.result.value:fallback);r.onerror=()=>res(fallback);});}catch(e){return fallback;}}
async function idbSetSetting(key,value){try{await idbPut('settings',{key,value});}catch(e){}localStorage.setItem(key,value);}

// ============================================================
// DB CRUD
// ============================================================
async function dbSaveBarcode(bc){if(USE_PHP)return phpPost('save.php',bc);return idbPut('barcodes',bc);}
async function dbUpdateBarcode(bc){if(USE_PHP)return phpPost('update.php',bc);return idbPut('barcodes',bc);}
async function dbDeleteBarcode(id){if(USE_PHP)return phpPost('delete.php',{id});return idbDelete('barcodes',id);}
async function dbSaveCategory(name){if(USE_PHP)return phpPost('categories.php',{action:'add',name});return idbPut('categories',{name});}
async function dbDeleteCategory(name){if(USE_PHP)return phpPost('categories.php',{action:'delete',name});return idbDelete('categories',name);}
async function dbSaveSetting(key,value){if(USE_PHP){phpPost('settings.php',{[key]:value}).catch(()=>{});}idbSetSetting(key,value);}
async function dbSaveSettings(obj){if(USE_PHP){phpPost('settings.php',obj).catch(()=>{});}for(const[k,v]of Object.entries(obj))idbSetSetting(k,v);}

// ============================================================
// RIPPLE
// ============================================================
document.addEventListener('click',function(e){const btn=e.target.closest('.btn');if(!btn)return;const ex=btn.querySelector('.ripple');if(ex)ex.remove();const rip=document.createElement('span');rip.className='ripple';const rect=btn.getBoundingClientRect();const size=Math.max(rect.width,rect.height);rip.style.cssText=`width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px;`;btn.appendChild(rip);setTimeout(()=>rip.remove(),600);});

// ============================================================
// EAN-13
// ============================================================
function calcCheckDigit(d12){let s=0;for(let i=0;i<12;i++)s+=parseInt(d12[i])*(i%2===0?1:3);return(10-(s%10))%10;}
function validateEAN13(code){if(!/^\d{13}$/.test(code))return false;return calcCheckDigit(code.slice(0,12))===parseInt(code[12]);}
function completeEAN13(code){code=code.replace(/\D/g,'');if(code.length===12)return code+calcCheckDigit(code);if(code.length===13&&validateEAN13(code))return code;return null;}
function generateRandomEAN13(){const prefix=defaultPrefix.slice(0,7);let base=prefix;while(base.length<12)base+=Math.floor(Math.random()*10);return completeEAN13(base);}
function generateEAN13SVG(code,opts={}){
  const{width=200,height=100,color='#000',bg='#fff',showText=true}=opts;
  const PATTERNS={L:['0001101','0011001','0010011','0111101','0100011','0110001','0101111','0111011','0110111','0001011'],G:['0100111','0110011','0011011','0100001','0011101','0111001','0000101','0010001','0001001','0010111'],R:['1110010','1100110','1101100','1000010','1011100','1001110','1010000','1000100','1001000','1110100']};
  const FIRST_DIGIT=['LLLLLL','LLGLGG','LLGGLG','LLGGGL','LGLLGG','LGGLLG','LGGGLL','LGLGLG','LGLGGL','LGGLGL'];
  const d=code.split('').map(Number);const pattern=FIRST_DIGIT[d[0]];
  let bars='101';for(let i=0;i<6;i++)bars+=PATTERNS[pattern[i]][d[i+1]];bars+='01010';for(let i=7;i<13;i++)bars+=PATTERNS.R[d[i]];bars+='101';
  const quietZone=7;const totalUnits=bars.length+quietZone*2;const unitW=(width-20)/totalUnits;const barHeight=height-(showText?22:6);const startX=10+quietZone*unitW;
  let rects='';let x=startX;for(let i=0;i<bars.length;i++){const isGuard=(i<3||(i>=45&&i<=49)||i>=92);const h=isGuard?barHeight+5:barHeight;if(bars[i]==='1')rects+=`<rect x="${x.toFixed(2)}" y="4" width="${unitW.toFixed(2)}" height="${h}" fill="${color}"/>`;x+=unitW;}
  let textEls='';if(showText){const ty=height-4;const fs=Math.max(6,Math.min(10,unitW*6));const cx=width/2;textEls+=`<text x="${cx}" y="${ty}" text-anchor="middle" font-family="'JetBrains Mono',monospace" font-size="${fs}" fill="${color}">${code}</text>`;}
  return`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}" width="${width}" height="${height}"><rect width="${width}" height="${height}" fill="${bg}"/>${rects}${textEls}</svg>`;
}
function formatExpDate(d){if(!d||d==='')return'';var dt=new Date(d+'T00:00:00');return String(dt.getDate()).padStart(2,'0')+'/'+String(dt.getMonth()+1).padStart(2,'0')+'/'+dt.getFullYear();}

// ============================================================
// DUPLICATE CHECK
// ============================================================
function isDuplicateBarcode(code,excludeId){return barcodes.some(b=>b.code===code&&String(b.id)!==String(excludeId));}
function getDuplicateItem(code,excludeId){return barcodes.find(b=>b.code===code&&String(b.id)!==String(excludeId));}
function showDupWarning(cid,code,excId){const el=document.getElementById(cid);if(!el)return;const dup=getDuplicateItem(code,excId);if(!dup){el.style.display='none';el.innerHTML='';return;}const msg=currentLanguage==='ar'?`⚠️ هذا الرقم مستخدم مسبقاً في: <strong style="color:orange;">${dup.title}</strong>`:`⚠️ Already exists: <strong style="color:orange;">${dup.title}</strong>`;el.innerHTML=`<div class="dup-warning">${msg}</div>`;el.style.display='block';}
function hideDupWarning(cid){const el=document.getElementById(cid);if(el){el.style.display='none';el.innerHTML='';}}
function showInputError(inp,isErr){if(isErr){inp.classList.add('input-error','input-shake');setTimeout(()=>inp.classList.remove('input-shake'),400);}else{inp.classList.remove('input-error');}}

// ============================================================
// EXPIRED HELPERS
// ============================================================
function isExpired(bc){if(!bc.expDate||bc.expDate==='')return false;var d=new Date(bc.expDate+'T00:00:00');var now=new Date();now.setHours(0,0,0,0);return d<=now;}
function isExpiringSoon(bc){if(!bc.expDate||bc.expDate==='')return false;var d=new Date(bc.expDate+'T00:00:00');var now=new Date();now.setHours(0,0,0,0);var dl=Math.floor((d-now)/(864e5));return dl>=0&&dl<=7;}
function isExpiredOrSoon(bc){return isExpired(bc)||isExpiringSoon(bc);}
function getExpiredCount(){return barcodes.filter(b=>isExpiredOrSoon(b)).length;}
const EXPIRED_FILTER='__EXPIRED__';
const DISABLED_FILTER='__DISABLED__';

// ============================================================
// LABEL SIZES
// ============================================================
let rawSizes = localStorage.getItem('label_sizes');
let labelSizes;
if (rawSizes) {
  try {
    labelSizes = JSON.parse(rawSizes);
    delete labelSizes['4x2.5'];
    delete labelSizes['2x3.8'];
    if (Object.keys(labelSizes).length === 0) {
      labelSizes = { '6x4': {width:6,height:4,barcodeWidth:195,barcodeHeight:65,titleFont:11,shopFont:13,priceFont:12,weightFont:8,expFont:8,padding:5,custom:false,nameAr:'ملصق كبير',nameEn:'Large Label'} };
    }
  } catch(e) {
    labelSizes = { '6x4': {width:6,height:4,barcodeWidth:195,barcodeHeight:65,titleFont:11,shopFont:13,priceFont:12,weightFont:8,expFont:8,padding:5,custom:false,nameAr:'ملصق كبير',nameEn:'Large Label'} };
  }
} else {
  labelSizes = { '6x4': {width:6,height:4,barcodeWidth:195,barcodeHeight:65,titleFont:11,shopFont:13,priceFont:12,weightFont:8,expFont:8,padding:5,custom:false,nameAr:'ملصق كبير',nameEn:'Large Label'} };
}
if (!labelSizes['6x4']) {
  labelSizes['6x4'] = {width:6,height:4,barcodeWidth:195,barcodeHeight:65,titleFont:11,shopFont:13,priceFont:12,weightFont:8,expFont:8,padding:5,custom:false,nameAr:'ملصق كبير',nameEn:'Large Label'};
} else {
  if (!labelSizes['6x4'].weightFont) labelSizes['6x4'].weightFont = 8;
  if (!labelSizes['6x4'].expFont) labelSizes['6x4'].expFont = 8;
  if (!labelSizes['6x4'].priceFont) labelSizes['6x4'].priceFont = 12;
  if (!labelSizes['6x4'].titleFont) labelSizes['6x4'].titleFont = 14;
  if (!labelSizes['6x4'].shopFont) labelSizes['6x4'].shopFont = 13;
}
if (selectedLabelSize === '4x2.5' || selectedLabelSize === '2x3.8' || !labelSizes[selectedLabelSize]) {
  selectedLabelSize = '6x4';
  localStorage.setItem('selected_label_size', selectedLabelSize);
}
localStorage.setItem('label_sizes', JSON.stringify(labelSizes));

// ============================================================
// LABEL HTML
// ============================================================
function generateLabelHTML(barcode,productTitle,price,weight,sizeKey,expDate){
  var size=labelSizes[sizeKey];if(!size)return'<div>Error</div>';
  function toAr(str){return String(str).replace(/\d/g,d=>'٠١٢٣٤٥٦٧٨٩'[d]);}
  var arFont='font-family:"Tajawal","Noto Sans Arabic",Arial,sans-serif;';
  var val=(price&&price!==''&&price!=='0')?price:'';
  var displayVal=(currentLanguage==='ar'&&val)?toAr(val):val;
  var priceStr='';
  if(val){switch(selectedCurrency){case'SAR':priceStr=(currentLanguage==='ar')?displayVal+' ﷼':'SAR '+val;break;case'ريال':priceStr=displayVal+' ريال';break;case'riyal_new':priceStr='<span class="riyal-symbol">﷼</span> '+displayVal;break;case'BDT':priceStr='BDT '+val;break;case'BDT_symbol':priceStr='৳ '+val;break;default:priceStr='SAR '+val;}}
  var weightStr='';
  if(weight&&weight!==''){if(currentLanguage==='ar'){var wMatch=String(weight).match(/^([\d.]+)\s*(.*)$/);if(wMatch){var wNum=wMatch[1];var wUnit=wMatch[2].toLowerCase().trim();var arNum=toAr(wNum);var unitMap={'kg':'كيلو','كيلو':'كيلو','g':'جم','gm':'جم','gram':'جم','جم':'جم','ml':'مل','مل':'مل','l':'لتر','لتر':'لتر','lb':'رطل','oz':'أوقية'};var arUnit=unitMap[wUnit]||wUnit;weightStr=arNum+' '+arUnit;}else{weightStr=toAr(weight);}}else{weightStr=weight;}}
  var expStr=expDate?formatExpDate(expDate):'';if(currentLanguage==='ar'&&expStr)expStr=toAr(expStr);
  var expLabel=(currentLanguage==='ar')?'انتهاء':'EXP';var expDisplay=expStr?(expLabel+': '+expStr):'';
  var expColor='#e07800';
  if(expDate&&expDate!==''){var expCheckD=new Date(expDate+'T00:00:00');var nowCheck=new Date();nowCheck.setHours(0,0,0,0);var daysLeftLabel=Math.floor((expCheckD-nowCheck)/(864e5));expColor=(daysLeftLabel<=7)?'#cc0000':'#e07800';}
  var expClass=(expColor==='#cc0000')?'exp-date-text exp-date-urgent':'exp-date-text';
  var displayTitle=(productTitle&&productTitle.trim()!=='')?productTitle:(currentLanguage==='ar'?'اسم المنتج':'Product Name');
  var shopFs = size.shopFont || 14;
  var titleFs = size.titleFont || 10;
  var priceFs = size.priceFont || 10;
  var weightFs = size.weightFont || 6;
  var expFs = size.expFont || 6;
  var labelClass = size.custom ? 'custom-label' : 'default-label';
  var svg = generateEAN13SVG(barcode, {width: size.barcodeWidth, height: size.barcodeHeight});
  var leftItem='<span class="price-text" style="font-size:'+priceFs+'px;font-weight:900;flex:1;text-align:left;'+arFont+'">'+(priceStr||'')+'</span>';
  var midItem=expDisplay?'<span class="'+expClass+'" style="font-size:'+expFs+'px;flex:1;text-align:center;white-space:nowrap;direction:ltr;unicode-bidi:plaintext;'+arFont+'">'+expDisplay+'</span>':'<span style="flex:1;"></span>';
  var rightItem='<span class="weight-text" style="font-size:'+weightFs+'px;font-weight:700;flex:1;text-align:right;'+arFont+'">'+weightStr+'</span>';
  var bottomRow='';if(priceStr||expDisplay||weightStr){bottomRow='<div class="price-weight-row" style="font-size:'+priceFs+'px;">'+leftItem+midItem+rightItem+'</div>';}
  return'<div class="label-container '+labelClass+'" style="width:'+size.width+'cm;height:'+size.height+'cm;padding: '+size.padding+'px; padding-top: '+(size.padding+4)+'px; overflow:hidden;">'
    +'<div class="shop-name-text" style="font-size:'+shopFs+'px;width:100%;text-align:center;'+arFont+'">'+shopName+'</div>'
    +'<div class="product-title-text" style="font-size:'+titleFs+'px;width:100%;text-align:center;'+arFont+'">'+displayTitle+'</div>'
    +'<div class="barcode-wrapper" style="width:100%;text-align:center;">'+svg+'</div>'
    +bottomRow+'</div>';
}

// ============================================================
// TRANSLATIONS
// ============================================================
const translations={
  ar:{currency:'﷼',shopName:'الضيافة',subtitle:'نظام طباعة الباركود',navGenerator:'المولد',navLibrary:'باركود',navPrint:'الطباعة',navUsers:'المستخدمون',navSettings:'الإعدادات',genTitle:'إنشاء باركود',productName:'اسم المنتج *',price:'السعر',weight:'الوزن',barcodeNumber:'رقم الباركود EAN-13',barcodeHint:'12 أو 13 رقم، يتم حساب الرقم الاختباري تلقائياً',addToQueue:'إضافة إلى المكتبة',addAndPrint:'إضافة وطباعة مباشرة',previewTitle:'معاينة مباشرة',libraryTitle:'مكتبة الباركود',searchPlaceholder:' بحث بالاسم أو رقم الباركود...',printSizeTitle:'حجم الملصق',recommended:'موصى به',forSmall:'للعناصر الصغيرة',customSizeTitle:'➕ إضافة حجم مخصص',customNamePlaceholder:'الاسم (مثال: ملصق خاص)',customWidthPlaceholder:'العرض (سم)',customHeightPlaceholder:'الارتفاع (سم)',addCustom:'➕ إضافة حجم جديد',printSettings:'ℹ️ إعدادات الطباعة',printInfo1:'• كل باركود يطبع على ملصق منفصل',printInfo2:'• اضبط الطابعة على حجم الملصق المحدد',printInfo3:'• استخدم "الحجم الفعلي" في مربع حوار الطباعة',printAll:'🖨️ طباعة الكل',clearQueue:'🗑️ تفريغ القائمة',queueTitle:'قائمة الطباعة',emptyQueue:'قائمة الطباعة فارغة',editLabel:'تعديل',addLabel:'طباعة',deleteLabel:'حذف',saveChanges:'💾 حفظ التغييرات',cancel:'إلغاء',editTitle:'✏️ تعديل الباركود',valid:'باركود صالح',invalid:'❌ باركود غير صالح',productPlaceholder:'مثال: زيت زيتون ممتاز',weightPlaceholder:'500g',barcodePlaceholder:'966XXXXXXXXXX',settingsHeader:'إعدادات الرأس والمتجر',shopNameLabel:'اسم المتجر',logoUrlLabel:'رابط الشعار',logoHint:'أدخل رابط الصورة المباشر (png, jpg, svg)',logoPreview:'معاينة الشعار:',saveHeader:'💾 حفظ إعدادات الرأس',defaultSettings:'الإعدادات الافتراضية',defaultPrefixLabel:'البادئة الافتراضية',prefixHint:'تستخدم عند النقر على "إنشاء رقم عشوائي"',saveDefaults:'💾 حفظ الإعدادات الافتراضية',dataManagement:'إدارة البيانات',exportData:'📤 تصدير البيانات',importData:'📥 استيراد البيانات',deleteAll:'🗑️ حذف جميع البيانات',editProduct:'اسم المنتج',editPrice:'السعر',editWeight:'الوزن',editBarcode:'رقم الباركود',saveEdit:'💾 حفظ التغييرات',cancelEdit:'إلغاء',noBarcodes:'لا توجد باركودات',alreadyInQueue:'الباركود موجود بالفعل في القائمة',addedToQueue:'تمت الإضافة إلى قائمة الطباعة',queueCleared:'تم تفريغ القائمة',barcodeSaved:'تم حفظ الباركود',invalidBarcode:'رقم باركود غير صالح',enterProductName:'أدخل اسم المنتج',randomGenerated:'تم إنشاء باركود عشوائي',dataExported:'تم تصدير البيانات',dataImported:'تم استيراد البيانات',invalidFile:'ملف غير صالح',allDataDeleted:'تم حذف جميع البيانات',headerSaved:'تم حفظ إعدادات الرأس',defaultsSaved:'تم حفظ الإعدادات الافتراضية',barcodeUpdated:'تم تحديث الباركود بنجاح',barcodeDeleted:'تم حذف الباركود',confirmDelete:'هل تريد حذف هذا الباركود؟',confirmClearQueue:'تفريغ قائمة الطباعة بالكامل؟',confirmDeleteAll:'حذف جميع الباركودات؟ لا يمكن التراجع!',customAdded:'تم إضافة الحجم المخصص',printingStarted:'جار فتح نافذة الطباعة...',clickHint:'💡 اضغط على أي باركود للذهاب إلى صفحة الطباعة',categoriesTitle:'إدارة التصنيفات',categoryInputPlaceholder:'اسم التصنيف الجديد...',addCategoryBtn:'➕ إضافة',noCategoriesYet:'لا توجد تصنيفات بعد',categoryExists:'التصنيف موجود مسبقاً',categoryAdded:'تم إضافة التصنيف',categoryLabel:'التصنيف',noCategoryOption:'-- بدون تصنيف --',allCategories:'📋 الكل',expiredCategory:'⏰ منتهية / قريباً',currencyTitle:'إعداد العملة',currencySelectLabel:'اختر العملة / الرمز الذي يظهر على الملصق',currencySaved:'تم حفظ إعداد العملة',currencyPreviewLabel:'معاينة:',printAllInline:'طباعة الكل',expdateLabel:'تاريخ الانتهاء',expdateOptional:'(اختياري)',downloadHighRes:'⬇️ تحميل PNG جودة عالية',duplicateBarcode:'هذا الرقم مستخدم مسبقاً!',deletePopupTitle:'تأكيد الحذف',deletePopupMsg:'هل تريد حذف هذا الباركود؟',deletePopupConfirmText:'نعم، احذف',deletePopupCancelText:'إلغاء',expiredLabel:'⏰ منتهي',logoutBtn:'تسجيل خروج',statusLabel:'حالة الباركود',statusActive:'مفعّل',statusDisabled:'معطّل',
    usersPageTitle:'إدارة المستخدمين',addUserLabel:'➕ إضافة مستخدم جديد',addUserBtn:'إضافة',passHint:'كلمة المرور: أرقام فقط (6 أو أكثر) · المشرف يملك صلاحيات كاملة',thId:'الرقم',thUsername:'المستخدم',thRole:'الصلاحية',thActions:'الإجراءات',editUserModalTitle:'تعديل المستخدم',editUserNameLabel:'اسم المستخدم',editUserPassLabel:'كلمة المرور الجديدة (6+ أرقام)',editUserRoleLabel:'الصلاحية / Role',saveUserEdit:'حفظ التغييرات',cancelUserEdit:'إلغاء',noUsersText:'لا يوجد مستخدمون بعد',errDuplicate:'اسم المستخدم مستخدم مسبقاً',errPass:'كلمة المرور يجب أن تكون 6 أرقام على الأقل',welcomeGreeting:'مرحباً',roleLabelAdmin:'👑 مشرف',roleLabelUser:'👤 مستخدم'
  },
  en:{currency:'SAR',shopName:'Al-Diyafa',subtitle:'Barcode Label System',navGenerator:'Generator',navLibrary:'Barcodes',navPrint:'Print',navUsers:'Users',navSettings:'Settings',genTitle:'Create Barcode',productName:'Product Name *',price:'Price',weight:'Weight',barcodeNumber:'EAN-13 Barcode Number',barcodeHint:'12 or 13 digits, check digit auto-calculated',addToQueue:'Add to Library',addAndPrint:'Add & Print Now',previewTitle:'Live Preview',libraryTitle:'Barcode Library',searchPlaceholder:'🔍 Search by name or barcode...',printSizeTitle:'Label Size',recommended:'Recommended',forSmall:'For small items',customSizeTitle:'➕ Add Custom Size',customNamePlaceholder:'Name (e.g., Custom Label)',customWidthPlaceholder:'Width (cm)',customHeightPlaceholder:'Height (cm)',addCustom:'➕ Add Custom Size',printSettings:'ℹ️ Print Settings',printInfo1:'• Each barcode prints on a separate label',printInfo2:'• Set printer to match selected label size',printInfo3:'• Use "Actual Size" in print dialog',printAll:'🖨️ Print All',clearQueue:'🗑️ Clear Queue',queueTitle:'Print Queue',emptyQueue:'Queue is empty',editLabel:'Edit',addLabel:'Print',deleteLabel:'Delete',saveChanges:'💾 Save Changes',cancel:'Cancel',editTitle:'✏️ Edit Barcode',valid:'Valid EAN-13',invalid:'❌ Invalid Barcode',productPlaceholder:'e.g., Premium Olive Oil',weightPlaceholder:'500g',barcodePlaceholder:'966XXXXXXXXXX',settingsHeader:'Header & Shop Settings',shopNameLabel:'Shop Name',logoUrlLabel:'Logo URL',logoHint:'Enter direct image URL (png, jpg, svg)',logoPreview:'Logo Preview:',saveHeader:'💾 Save Header Settings',defaultSettings:'Default Settings',defaultPrefixLabel:'Default Prefix',prefixHint:'Used when clicking Generate Random',saveDefaults:'💾 Save Default Settings',dataManagement:'Data Management',exportData:'📤 Export Data',importData:'📥 Import Data',deleteAll:'🗑️ Delete All Data',editProduct:'Product Name',editPrice:'Price',editWeight:'Weight',editBarcode:'Barcode Number',saveEdit:'💾 Save Changes',cancelEdit:'Cancel',noBarcodes:'No barcodes found',alreadyInQueue:'Already in queue',addedToQueue:'Added to print queue',queueCleared:'Queue cleared',barcodeSaved:'Barcode saved',invalidBarcode:'Invalid barcode number',enterProductName:'Enter product name',randomGenerated:'Random barcode generated',dataExported:'Data exported',dataImported:'Data imported',invalidFile:'Invalid file',allDataDeleted:'All data cleared',headerSaved:'Header settings saved',defaultsSaved:'Default settings saved',barcodeUpdated:'Barcode updated successfully',barcodeDeleted:'Barcode deleted',confirmDelete:'Delete this barcode?',confirmClearQueue:'Clear entire print queue?',confirmDeleteAll:'Delete ALL barcodes? This cannot be undone.',customAdded:'Custom size added',printingStarted:'Opening print dialog...',clickHint:'💡 Click any barcode to go to print page',categoriesTitle:'Manage Categories',categoryInputPlaceholder:'New category name...',addCategoryBtn:'➕ Add',noCategoriesYet:'No categories yet',categoryExists:'Category already exists',categoryAdded:'Category added',categoryLabel:'Category',noCategoryOption:'-- No Category --',allCategories:'📋 All',expiredCategory:'⏰ Expired / Soon',currencyTitle:'Currency Settings',currencySelectLabel:'Choose the currency / symbol shown on labels',currencySaved:'Currency saved',currencyPreviewLabel:'Preview:',printAllInline:'Print All',expdateLabel:'Expiry Date',expdateOptional:'(optional)',downloadHighRes:'⬇️ Download PNG (High-Res)',duplicateBarcode:'This barcode number already exists!',deletePopupTitle:'Confirm Delete',deletePopupMsg:'Are you sure you want to delete this barcode?',deletePopupConfirmText:'Yes, Delete',deletePopupCancelText:'Cancel',expiredLabel:'⏰ Expired',logoutBtn:'Logout',statusLabel:'Barcode Status',statusActive:'Active',statusDisabled:'Disabled',
    usersPageTitle:'User Management',addUserLabel:'➕ Add New User',addUserBtn:'Add',passHint:'Password: digits only (6+) · Admin has full access including user management',thId:'ID',thUsername:'User',thRole:'Role',thActions:'Actions',editUserModalTitle:'Edit User',editUserNameLabel:'Username',editUserPassLabel:'New Password (6+ digits)',editUserRoleLabel:'Role / Access',saveUserEdit:'Save Changes',cancelUserEdit:'Cancel',noUsersText:'No users yet',errDuplicate:'Username already taken',errPass:'Password must be 6+ digits',welcomeGreeting:'Hello',roleLabelAdmin:'👑 Admin',roleLabelUser:'👤 User'
  }
};
let currentTexts = translations.ar;

// ============================================================
// USER MANAGEMENT
// ============================================================
let usersData = <?php echo json_encode($usersList); ?>;
let pendingDeleteUserId = null;
let currentEditUserId = null;

function toggleEye(inputId, iconId){
  var inp=document.getElementById(inputId);
  var btn=inp?inp.parentElement.querySelector('.eye-btn'):null;
  if(!inp)return;
  if(inp.type==='password'){inp.type='text';document.getElementById(iconId).innerHTML='<i class="fas fa-eye-slash"></i>';if(btn)btn.classList.add('active');}
  else{inp.type='password';document.getElementById(iconId).innerHTML='<i class="fa-regular fa-eye"></i>';if(btn)btn.classList.remove('active');}
}

function onRoleSelectChange(sel){
  if(sel.value==='1'){
    sel.classList.add('is-admin-role');
    var warn=document.getElementById('role-admin-warning');
    if(warn) warn.classList.add('visible');
  } else {
    sel.classList.remove('is-admin-role');
    var warn=document.getElementById('role-admin-warning');
    if(warn) warn.classList.remove('visible');
  }
}

function showUserAlert(msg,type){
  var box=document.getElementById('user-alert-box');
  if(!box)return;
  box.innerHTML='<div class="user-alert-'+type+'">'+msg+'</div>';
  box.style.display='block';
  if(type==='success')setTimeout(function(){box.style.display='none';},3500);
}
function showEditAlert(msg,type){
  var box=document.getElementById('user-edit-alert');
  if(!box)return;
  box.innerHTML='<div class="user-alert-'+type+'">'+msg+'</div>';
  box.style.display='block';
  if(type==='success')setTimeout(function(){box.style.display='none';},2500);
}
function escHtml(str){return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

function renderUsersTable(){
  if(!isAdminUser)return;
  var container=document.getElementById('users-cards-container');
  var badge=document.getElementById('users-count-badge');
  if(badge)badge.textContent=usersData.length;
  if(!container)return;
  if(usersData.length===0){
    container.innerHTML='<div class="empty-state" style="padding:40px 20px;"><div style="font-size:48px;">👥</div><div style="margin-top:12px;font-size:14px;color:var(--text2);">'+(currentLanguage==='ar'?'لا يوجد مستخدمون بعد':'No users yet')+'</div></div>';
    return;
  }
  var t=currentTexts;
  var rows=usersData.map(function(u){
    var safeUser=escHtml(u.username).replace(/'/g,"\\'");
    var isAdm=u.is_admin;
    var avatarClass=isAdm?'user-avatar-admin':'user-avatar-user';
    var avatarIcon=isAdm?'👑':'👤';
    var subText=isAdm?(currentLanguage==='ar'?'صلاحيات كاملة':'full access'):(currentLanguage==='ar'?'مستخدم عادي':'standard user');
    var roleBadge=isAdm
      ?'<span class="role-badge role-badge-admin">👑 '+(currentLanguage==='ar'?'مشرف':'Admin')+'</span>'
      :'<span class="role-badge role-badge-user">👤 '+(currentLanguage==='ar'?'مستخدم':'User')+'</span>';
    return '<tr>'+
      '<td><span class="user-id-cell">#'+u.id+'</span></td>'+
      '<td><div class="user-name-cell">'+
        '<div class="user-avatar-sm '+avatarClass+'">'+avatarIcon+'</div>'+
        '<div><div class="user-name-text">'+escHtml(u.username)+'</div>'+
        '<div class="user-sub-text">'+subText+'</div></div>'+
      '</div></td>'+
      '<td style="text-align:center;">'+roleBadge+'</td>'+
      '<td><div class="action-btns">'+
        '<button class="btn btn-warning btn-sm" onclick="openUserEdit('+u.id+',\''+safeUser+'\','+( isAdm?'true':'false')+')">'+
          '<i class="fas fa-edit"></i> '+(currentLanguage==='ar'?'تعديل':'Edit')+
        '</button>'+
        '<button class="btn btn-danger btn-sm" onclick="askDeleteUser('+u.id+',\''+safeUser+'\')">'+
          '<i class="fas fa-trash"></i> '+(currentLanguage==='ar'?'حذف':'Delete')+
        '</button>'+
      '</div></td>'+
    '</tr>';
  }).join('');
  container.innerHTML=
    '<div class="users-table-wrap">'+
      '<table class="users-table">'+
        '<thead><tr>'+
          '<th style="width:80px;">'+t.thId+'</th>'+
          '<th>'+t.thUsername+'</th>'+
          '<th class="th-role" style="width:120px;">'+t.thRole+'</th>'+
          '<th style="width:190px;text-align:center;">'+t.thActions+'</th>'+
        '</tr></thead>'+
        '<tbody>'+rows+'</tbody>'+
      '</table>'+
    '</div>';
}

async function ajaxAddUser(){
  if(!isAdminUser)return;
  var usernameInput=document.getElementById('new-username-input');
  var passwordInput=document.getElementById('new-password-input');
  var roleSelect=document.getElementById('new-role-select');
  var username=usernameInput.value.trim();
  var password=passwordInput.value.trim();
  var isAdmin=roleSelect?roleSelect.value==='1':false;
  var lang=currentLanguage;
  showInputError(usernameInput,false);showInputError(passwordInput,false);
  if(!username){showInputError(usernameInput,true);showUserAlert('⚠️ '+(lang==='ar'?'أدخل اسم المستخدم':'Enter username'),'error');return;}
  if(!/^\d{6,}$/.test(password)){showInputError(passwordInput,true);showUserAlert('⚠️ '+(lang==='ar'?'كلمة المرور أرقام فقط (6+)':'Password must be digits only (6+)'),'error');return;}
  if(usersData.some(function(u){return u.username.toLowerCase()===username.toLowerCase();})){showInputError(usernameInput,true);showUserAlert('⚠️ '+(lang==='ar'?'اسم المستخدم مستخدم مسبقاً':'Username already taken'),'error');return;}
  try{
    var resp=await fetch('user_ajax.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'add',username:username,password:password,is_admin:isAdmin})});
    var data=await resp.json();
    if(data.success){
      usersData.unshift({id:data.id,username:username,is_admin:!!data.is_admin});
      renderUsersTable();
      usernameInput.value='';passwordInput.value='';passwordInput.type='password';
      document.getElementById('eye-new').innerHTML='<i class="fa-regular fa-eye"></i>';
      if(roleSelect){roleSelect.value='0';onRoleSelectChange(roleSelect);}
      showUserAlert('✅ '+(lang==='ar'?'تم إضافة المستخدم بنجاح':'User added successfully')+(isAdmin?(lang==='ar'?' (مشرف)':' (Admin)'):''),'success');
    } else {
      if(data.error==='duplicate'){showInputError(usernameInput,true);showUserAlert('⚠️ '+(lang==='ar'?'اسم المستخدم مستخدم مسبقاً':'Username already taken'),'error');}
      else if(data.error==='invalid_password'){showInputError(passwordInput,true);showUserAlert('⚠️ '+(lang==='ar'?'كلمة المرور 6 أرقام على الأقل':'Password must be 6 digits minimum'),'error');}
      else{showUserAlert('⚠️ '+(lang==='ar'?'خطأ في الحفظ':'Save error'),'error');}
    }
  }catch(e){showUserAlert('❌ '+(lang==='ar'?'خطأ في الاتصال':'Connection error'),'error');}
}

function openUserEdit(id,username,isAdminFlag){
  if(!isAdminUser)return;
  currentEditUserId=id;
  document.getElementById('edit-user-name-input').value=username;
  document.getElementById('edit-user-pass-input').value='';
  document.getElementById('edit-user-pass-input').type='password';
  document.getElementById('eye-edit').innerHTML='<i class="fa-regular fa-eye"></i>';
  var roleSelect=document.getElementById('edit-user-role-select');
  if(roleSelect){roleSelect.value=isAdminFlag?'1':'0';onRoleSelectChange(roleSelect);}
  showInputError(document.getElementById('edit-user-name-input'),false);
  showInputError(document.getElementById('edit-user-pass-input'),false);
  var box=document.getElementById('user-edit-alert');if(box)box.style.display='none';
  document.getElementById('user-edit-modal').classList.add('open');
}
function closeUserEditModal(){
  document.getElementById('user-edit-modal').classList.remove('open');
  currentEditUserId=null;
  var warn=document.getElementById('role-admin-warning');if(warn)warn.classList.remove('visible');
}

async function ajaxEditUser(){
  if(!isAdminUser)return;
  var usernameInput=document.getElementById('edit-user-name-input');
  var passwordInput=document.getElementById('edit-user-pass-input');
  var roleSelect=document.getElementById('edit-user-role-select');
  var username=usernameInput.value.trim();
  var password=passwordInput.value.trim();
  var isAdmin=roleSelect?roleSelect.value==='1':false;
  var lang=currentLanguage;
  showInputError(usernameInput,false);showInputError(passwordInput,false);
  if(!username){showInputError(usernameInput,true);showEditAlert('⚠️ '+(lang==='ar'?'أدخل اسم المستخدم':'Enter username'),'error');return;}
  if(!/^\d{6,}$/.test(password)){showInputError(passwordInput,true);showEditAlert('⚠️ '+(lang==='ar'?'كلمة المرور أرقام فقط (6+)':'Password digits only (6+)'),'error');return;}
  if(usersData.some(function(u){return u.id!==currentEditUserId&&u.username.toLowerCase()===username.toLowerCase();})){showInputError(usernameInput,true);showEditAlert('⚠️ '+(lang==='ar'?'اسم المستخدم مستخدم مسبقاً':'Username already taken'),'error');return;}
  try{
    var resp=await fetch('user_ajax.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'edit',id:currentEditUserId,username:username,password:password,is_admin:isAdmin})});
    var data=await resp.json();
    if(data.success){
      usersData=usersData.map(function(u){return u.id===currentEditUserId?{id:u.id,username:username,is_admin:isAdmin}:u;});
      renderUsersTable();
      showEditAlert('✅ '+(lang==='ar'?'تم التحديث بنجاح':'Updated successfully'),'success');
      setTimeout(closeUserEditModal,1200);
    } else {
      if(data.error==='duplicate'){showInputError(usernameInput,true);showEditAlert('⚠️ '+(lang==='ar'?'اسم المستخدم مستخدم مسبقاً':'Username already taken'),'error');}
      else if(data.error==='invalid_password'){showInputError(passwordInput,true);showEditAlert('⚠️ '+(lang==='ar'?'كلمة المرور 6 أرقام على الأقل':'Password must be 6 digits minimum'),'error');}
      else{showEditAlert('⚠️ '+(lang==='ar'?'خطأ في التحديث':'Update error'),'error');}
    }
  }catch(e){showEditAlert('❌ '+(lang==='ar'?'خطأ في الاتصال':'Connection error'),'error');}
}

function askDeleteUser(id,username){
  if(!isAdminUser)return;
  pendingDeleteUserId=id;
  var lang=currentLanguage;
  var user=usersData.find(function(u){return u.id===id;});
  var roleLabel=user&&user.is_admin?(lang==='ar'?'مشرف':'Admin'):(lang==='ar'?'مستخدم':'User');
  document.getElementById('udel-title').textContent=lang==='ar'?'تأكيد الحذف':'Confirm Delete';
  document.getElementById('udel-msg').textContent=lang==='ar'?'هل تريد حذف هذا المستخدم؟':'Delete this user?';
  document.getElementById('udel-name').textContent=(user&&user.is_admin?'👑 ':'👤 ')+username+' — '+roleLabel+' (ID: '+id+')';
  document.getElementById('udel-confirm-text').textContent=lang==='ar'?'نعم، احذف':'Yes, Delete';
  document.getElementById('udel-cancel-text').textContent=lang==='ar'?'إلغاء':'Cancel';
  document.getElementById('user-delete-popup').style.display='flex';
}
function closeUserDeletePopup(){document.getElementById('user-delete-popup').style.display='none';pendingDeleteUserId=null;}
async function confirmDeleteUser(){
  if(!isAdminUser||!pendingDeleteUserId)return;
  var lang=currentLanguage;
  try{
    var resp=await fetch('user_ajax.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',id:pendingDeleteUserId})});
    var data=await resp.json();
    if(data.success){
      usersData=usersData.filter(function(u){return u.id!==pendingDeleteUserId;});
      renderUsersTable();
      showUserAlert('✅ '+(lang==='ar'?'تم حذف المستخدم':'User deleted'),'success');
    } else {
      var errMsg=data.error==='cannot_delete_self'?(lang==='ar'?'لا يمكنك حذف حسابك الخاص':'Cannot delete your own account'):(lang==='ar'?'خطأ في الحذف':'Delete error');
      showUserAlert('❌ '+errMsg,'error');
    }
  }catch(e){showUserAlert('❌ '+(lang==='ar'?'خطأ في الاتصال':'Connection error'),'error');}
  closeUserDeletePopup();
}

// ============================================================
// CATEGORIES
// ============================================================
async function addCategory(){const input=document.getElementById('new-category-input');const name=input.value.trim();if(!name)return;if(categories.includes(name)){toast(currentTexts.categoryExists,'error');return;}try{await dbSaveCategory(name);categories.push(name);input.value='';renderCategoriesList();renderCategoryDropdowns();renderLibrary();toast(currentTexts.categoryAdded);}catch(e){toast('Error','error');}}
async function deleteCategory(name){try{await dbDeleteCategory(name);for(const bc of barcodes){if(bc.category===name){bc.category='';await dbUpdateBarcode(bc);}}}catch(e){}categories=categories.filter(c=>c!==name);barcodes=barcodes.map(b=>b.category===name?{...b,category:''}:b);if(selectedCategoryFilter===name)selectedCategoryFilter='';renderCategoriesList();renderCategoryDropdowns();renderLibrary();}
function renderCategoriesList(){const el=document.getElementById('categories-list');if(!el)return;if(categories.length===0){el.innerHTML=`<span style="color:var(--text2);font-size:12px">${currentTexts.noCategoriesYet}</span>`;return;}el.innerHTML=categories.map(c=>`<div class="category-chip" style="cursor:default">🏷️ ${c}<span class="chip-del" onclick="deleteCategory('${c.replace(/'/g,"\\'")}')">✕</span></div>`).join('');}
function renderCategoryDropdowns(){['m-category','edit-category'].forEach(id=>{const sel=document.getElementById(id);if(!sel)return;const cur=sel.value;sel.innerHTML=`<option value="">${currentTexts.noCategoryOption}</option>`;categories.forEach(c=>{const opt=document.createElement('option');opt.value=c;opt.textContent=c;sel.appendChild(opt);});sel.value=cur;});}
function renderLibraryCategoryFilter(){
  const bar=document.getElementById('lib-category-filter');if(!bar)return;
  var allCount=barcodes.length;var expCount=getExpiredCount();var disabledCount=barcodes.filter(b=>b.disabled===true).length;
  var html='<div class="category-chip'+(selectedCategoryFilter===''?' active':'')+'" onclick="filterByCategory(\'\')">'+(currentTexts.allCategories||'📋 الكل')+' <span class="badge-count" style="font-size:10px;padding:0 5px;">'+allCount+'</span></div>';
  html+='<div class="category-chip expired-chip'+(selectedCategoryFilter===EXPIRED_FILTER?' active':'')+'" onclick="filterByCategory(\''+EXPIRED_FILTER+'\')">'+(currentTexts.expiredCategory||'⏰ منتهية')+' <span class="badge-count-red" style="font-size:10px;padding:0 5px;">'+expCount+'</span></div>';
  html+='<div class="category-chip disabled-chip'+(selectedCategoryFilter===DISABLED_FILTER?' active':'')+'" onclick="filterByCategory(\''+DISABLED_FILTER+'\')">'+(currentLanguage==='ar'?'⛔ معطّلة':'⛔ Disabled')+' <span class="badge-count" style="font-size:10px;padding:0 5px;background:rgba(100,100,120,0.25);color:#888;">'+disabledCount+'</span></div>';
  categories.slice().sort((a,b)=>a.localeCompare(b,undefined,{sensitivity:'base'})).forEach(c=>{var count=barcodes.filter(b=>b.category===c).length;var safeC=c.replace(/\\/g,'\\\\').replace(/'/g,"\\'");html+='<div class="category-chip'+(selectedCategoryFilter===c?' active':'')+'" onclick="filterByCategory(\''+safeC+'\')">'+'🏷️ '+c+' <span class="badge-count" style="font-size:10px;padding:0 5px;">'+count+'</span></div>';});
  bar.innerHTML=html;
}
function filterByCategory(cat){selectedCategoryFilter=cat;renderLibraryCategoryFilter();renderLibrary();}

// ============================================================
// CURRENCY
// ============================================================
function formatCurrency(price,wrapBadge){const val=(price&&price!==''&&price!=='0')?price:null;if(!val)return'';let str='';switch(selectedCurrency){case'SAR':str=`SAR ${val}`;break;case'ريال':str=`${val} ريال`;break;case'riyal_new':str=`<span class="riyal-symbol">﷼</span> ${val}`;break;case'BDT':str=`BDT ${val}`;break;case'BDT_symbol':str=`৳ ${val}`;break;default:str=`SAR ${val}`;}return wrapBadge?`<span class="badge badge-green">💰 ${str}</span>`:str;}
async function saveCurrencySetting(){selectedCurrency=document.getElementById('currency-select').value;await dbSaveSetting('bc-currency',selectedCurrency);updateCurrencyPreview();updatePreviewFromManual();renderLibrary();renderPrintQueue();toast(currentTexts.currencySaved);}
function updateCurrencyPreview(){const el=document.getElementById('currency-preview-text');if(!el)return;const sel=document.getElementById('currency-select');if(sel)sel.value=selectedCurrency;el.innerHTML=formatCurrency('99.00',false)||'SAR 99.00';}

// ============================================================
// CUSTOM LABEL SIZES
// ============================================================
function addCustomLabelSize(){const nameAr=document.getElementById('custom-name')?.value.trim();const width=parseFloat(document.getElementById('custom-width')?.value);const height=parseFloat(document.getElementById('custom-height')?.value);if(!nameAr||isNaN(width)||isNaN(height)||width<=0||height<=0){toast(currentLanguage==='ar'?'الرجاء إدخال اسم وأبعاد صحيحة':'Please enter valid name and dimensions','error');return;}const id='custom_'+Date.now();
  labelSizes[id]={
    id,width,height,
    barcodeWidth: Math.min(180, Math.max(90, width*28)),
    barcodeHeight: Math.min(90, Math.max(30, height*16)),
    titleFont: Math.min(13, Math.max(7, height*3.5)),
    shopFont: Math.min(16, Math.max(8, height*4.0)),
    priceFont: Math.min(11, Math.max(7, height*3)),
    weightFont: Math.min(9, Math.max(4.5, height*2.2)),
    expFont: Math.min(9, Math.max(4.5, height*2.5)),
    padding: Math.min(8, Math.max(1.5, height*1.1)),
    custom:true,
    nameAr,nameEn:nameAr
  };
  localStorage.setItem('label_sizes',JSON.stringify(labelSizes));
  renderLabelSizes();
  toast(currentTexts.customAdded);
  document.getElementById('custom-name').value='';
  document.getElementById('custom-width').value='';
  document.getElementById('custom-height').value='';
}
function deleteCustomLabelSize(id){if(labelSizes[id]&&labelSizes[id].custom){delete labelSizes[id];localStorage.setItem('label_sizes',JSON.stringify(labelSizes));if(selectedLabelSize===id){selectedLabelSize='6x4';localStorage.setItem('selected_label_size',selectedLabelSize);}renderLabelSizes();renderPrintQueue();updatePreviewFromManual();toast(currentLanguage==='ar'?'تم حذف الحجم المخصص':'Custom size deleted');}}
function renderLabelSizes(){const container=document.getElementById('paper-size-list');if(!container)return;container.innerHTML='';for(const[id,size]of Object.entries(labelSizes)){const name=currentLanguage==='ar'?size.nameAr:(size.nameEn||size.nameAr);const isSelected=selectedLabelSize===id;const btn=document.createElement('div');btn.className='label-size-btn'+(isSelected?' selected':'');let rightEl='';if(id==='6x4'){rightEl=`<div class="badge badge-purple">${currentTexts.recommended||'موصى به'}</div>`;}else if(size.custom){rightEl=`<button class="btn btn-danger btn-sm" style="padding:4px 8px;font-size:11px;" onclick="event.stopPropagation();deleteCustomLabelSize('${id}')">✕</button>`;}btn.innerHTML=`<div><div class="label-size-name">${size.custom?'⭐ ':'📏 '}${name}</div><div class="label-size-dim">${size.width}cm × ${size.height}cm</div></div>${rightEl}`;btn.onclick=()=>selectLabelSize(id);container.appendChild(btn);}}

// ============================================================
// LANGUAGE
// ============================================================
function setLanguage(lang){
  currentLanguage=lang;localStorage.setItem('language',lang);dbSaveSetting('language',lang);currentTexts=translations[lang];
  if(lang==='ar'){document.body.classList.remove('ltr');document.body.classList.add('rtl');document.documentElement.setAttribute('dir','rtl');}
  else{document.body.classList.remove('rtl');document.body.classList.add('ltr');document.documentElement.setAttribute('dir','ltr');}
  document.getElementById('btn-ar').classList.toggle('active',lang==='ar');
  document.getElementById('btn-en').classList.toggle('active',lang==='en');
  _applyAllLangTexts(lang);
  renderCategoryDropdowns();renderCategoriesList();updateCurrencyPreview();renderLabelSizes();updatePreviewFromManual();renderLibrary();renderPrintQueue();updateThemeDrawerLabel();renderUsersTable();
}

// ============================================================
// THEME
// ============================================================
function toggleTheme(){var isLight=document.body.classList.toggle('light-mode');localStorage.setItem('theme',isLight?'light':'dark');updateThemeDrawerLabel();}
function applyTheme(){var saved=localStorage.getItem('theme')||'dark';if(saved==='light')document.body.classList.add('light-mode');else document.body.classList.remove('light-mode');updateThemeDrawerLabel();}
function updateThemeDrawerLabel(){var isLight=document.body.classList.contains('light-mode');var el=document.getElementById('d-txt-theme');if(!el)return;if(currentLanguage==='ar'){el.textContent=isLight?'☀️ الوضع النهاري':'🌙 الوضع الليلي';}else{el.textContent=isLight?'☀️ Light Mode':'🌙 Dark Mode';}}

// ============================================================
// NAVIGATION
// ============================================================
function showPage(page){
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.sidebar-nav-btn').forEach(b=>b.classList.remove('active'));
  const pageEl=document.getElementById('page-'+page);if(pageEl)pageEl.classList.add('active');
  const navBtn=document.getElementById('nav-'+page);if(navBtn)navBtn.classList.add('active');
  syncDrawerActive(page);
  if(page==='library'){setTimeout(function(){renderLibrary();},50);}
  if(page==='print'){renderLabelSizes();renderPrintQueue();}
  if(page==='users'&&isAdminUser){renderUsersTable();}
  if(page==='settings'){document.getElementById('shop-name-setting').value=shopName;document.getElementById('logo-url-setting').value=logoUrl;document.getElementById('default-prefix').value=defaultPrefix;document.getElementById('logo-preview-img').src=logoUrl;renderCategoriesList();updateCurrencyPreview();}
}
function syncDrawerActive(page){['generator','library','print','users','settings'].forEach(p=>{const el=document.getElementById('d-nav-'+p);if(el)el.classList.toggle('active',p===page);});}
function toggleMobileNav(){const btn=document.getElementById('hamburger-btn');const overlay=document.getElementById('mobile-nav-overlay');const drawer=document.getElementById('mobile-nav-drawer');const isOpen=drawer.classList.contains('open');if(isOpen){closeMobileNav();}else{btn.classList.add('open');overlay.classList.add('open');drawer.classList.add('open');}}
function closeMobileNav(){document.getElementById('hamburger-btn').classList.remove('open');document.getElementById('mobile-nav-overlay').classList.remove('open');document.getElementById('mobile-nav-drawer').classList.remove('open');}
function selectLabelSize(size){if(labelSizes[size]){selectedLabelSize=size;localStorage.setItem('selected_label_size',size);renderLabelSizes();renderPrintQueue();updatePreviewFromManual();}}
function toast(msg,type='success'){const t=document.createElement('div');t.className='toast';t.innerHTML=`<span>${type==='success'?'✅':'❌'}</span><span>${msg}</span>`;document.getElementById('toast-container').appendChild(t);setTimeout(()=>t.remove(),3000);}
function saveData(){localStorage.setItem('bc-queue',JSON.stringify(printQueue));}

// ============================================================
// HEADER SETTINGS
// ============================================================
function updateHeaderPreview(){const n=document.getElementById('shop-name-setting').value;document.getElementById('header-shop-name').textContent=n||(currentLanguage==='ar'?'الضيافة':'Al-Diyafa');}
function updateLogoPreview(){const n=document.getElementById('logo-url-setting').value;const img=document.getElementById('logo-preview-img');if(n){img.src=n;img.onerror=()=>{img.src='image/logo.png';};}}
async function saveHeaderSettings(){const newShopName=document.getElementById('shop-name-setting').value.trim();const newLogo=document.getElementById('logo-url-setting').value.trim();if(newShopName){shopName=newShopName;document.getElementById('header-shop-name').textContent=shopName;}if(newLogo){logoUrl=newLogo;document.getElementById('header-logo').src=logoUrl;}await dbSaveSettings({shop_name:shopName,logo_url:logoUrl});toast(currentTexts.headerSaved);updatePreviewFromManual();}
async function saveBarcodeDefaults(){const newPrefix=document.getElementById('default-prefix').value.trim();if(newPrefix&&newPrefix.length>=4)defaultPrefix=newPrefix;await dbSaveSetting('default_prefix',defaultPrefix);toast(currentTexts.defaultsSaved);}
function updateToggleLabel(){const chk=document.getElementById('edit-status-toggle');const txt=document.getElementById('toggle-status-text');if(!chk||!txt)return;if(chk.checked){txt.textContent=currentTexts.statusActive||'Active';txt.className='toggle-status on';}else{txt.textContent=currentTexts.statusDisabled||'Disabled';txt.className='toggle-status off';}}

// ============================================================
// EDIT BARCODE MODAL
// ============================================================
function openEditModal(id){const sid=String(id);const bc=barcodes.find(b=>String(b.id)===sid);if(!bc)return;currentEditId=sid;document.getElementById('edit-title').value=bc.title||'';document.getElementById('edit-price').value=bc.price||'';document.getElementById('edit-weight').value=bc.weight||'';document.getElementById('edit-barcode').value=bc.code||'';var eed=document.getElementById('edit-expdate');if(eed)eed.value=bc.expDate||'';renderCategoryDropdowns();document.getElementById('edit-category').value=bc.category||'';const toggle=document.getElementById('edit-status-toggle');if(toggle){toggle.checked=(bc.disabled!==true);updateToggleLabel();}hideDupWarning('dup-warning-edit');document.getElementById('edit-modal').classList.add('open');document.getElementById('edit-barcode').oninput=function(){const raw=this.value.replace(/\D/g,'');this.value=raw;if(raw.length>=12){const full=completeEAN13(raw);if(full&&isDuplicateBarcode(full,currentEditId)){showDupWarning('dup-warning-edit',full,currentEditId);}else{hideDupWarning('dup-warning-edit');}}else{hideDupWarning('dup-warning-edit');}};}
function closeEditModal(){document.getElementById('edit-modal').classList.remove('open');currentEditId=null;hideDupWarning('dup-warning-edit');}
async function saveEdit(){const newTitle=document.getElementById('edit-title').value.trim();if(!newTitle){toast(currentTexts.enterProductName,'error');return;}let newCode=document.getElementById('edit-barcode').value.trim();const fullCode=completeEAN13(newCode);if(!fullCode||!validateEAN13(fullCode)){toast(currentTexts.invalidBarcode,'error');return;}if(isDuplicateBarcode(fullCode,currentEditId)){const inp=document.getElementById('edit-barcode');inp.classList.add('input-shake');setTimeout(()=>inp.classList.remove('input-shake'),500);showDupWarning('dup-warning-edit',fullCode,currentEditId);toast(currentTexts.duplicateBarcode,'error');return;}const index=barcodes.findIndex(b=>String(b.id)===String(currentEditId));if(index!==-1){var eedSave=document.getElementById('edit-expdate');const toggleSave=document.getElementById('edit-status-toggle');const originalCreator=barcodes[index].created_by||loggedInUsername;const updated={...barcodes[index],title:newTitle,price:document.getElementById('edit-price').value,weight:document.getElementById('edit-weight').value,expDate:eedSave?eedSave.value:'',code:fullCode,category:document.getElementById('edit-category').value,disabled:toggleSave?!toggleSave.checked:false,date:new Date().toISOString(),created_by:originalCreator};try{await idbPut('barcodes',updated);let serverSaved=false;try{const ctrl=new AbortController();const timer=setTimeout(()=>ctrl.abort(),8000);const res=await fetch(API_BASE+'update.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(updated),signal:ctrl.signal,cache:'no-store'});clearTimeout(timer);if(res.ok){const j=await res.json();if(j&&j.success){serverSaved=true;USE_PHP=true;}}}catch(serverErr){}barcodes[index]=updated;renderLibrary();toast(serverSaved?currentTexts.barcodeUpdated:'💾 تم الحفظ محلياً فقط');closeEditModal();}catch(e){toast('❌ خطأ في الحفظ','error');}}}
function genRandomBarcodeForEdit(){const code=generateRandomEAN13();if(code){const input=document.getElementById('edit-barcode');input.value=code;if(isDuplicateBarcode(code,currentEditId)){showDupWarning('dup-warning-edit',code,currentEditId);}else{hideDupWarning('dup-warning-edit');}}}

// ============================================================
// DELETE BARCODE POPUP
// ============================================================
function deleteBarcode(id){const sid=String(id);const bc=barcodes.find(b=>String(b.id)===sid);if(!bc)return;pendingDeleteId=sid;document.getElementById('del-popup-title').textContent=currentTexts.deletePopupTitle;document.getElementById('del-popup-msg').textContent=currentTexts.deletePopupMsg;document.getElementById('del-popup-confirm-text').textContent=currentTexts.deletePopupConfirmText;document.getElementById('del-popup-cancel-text').textContent=currentTexts.deletePopupCancelText;const nameEl=document.getElementById('del-popup-name');nameEl.innerHTML='🔍 '+bc.title+' · <span style="font-family:monospace;font-size:12px;">'+bc.code+'</span><br><span style="font-size:10px;color:var(--text2);">'+(currentLanguage==='ar'?'اضغط لعرض الباركود':'Click to preview barcode')+'</span>';nameEl._bcCode=bc.code;const preview=document.getElementById('del-popup-barcode-preview');preview.style.display='none';preview.innerHTML='';document.getElementById('delete-popup-overlay').style.display='flex';}
function toggleDeletePopupBarcode(){var nameEl=document.getElementById('del-popup-name');var preview=document.getElementById('del-popup-barcode-preview');var code=nameEl._bcCode;if(!code)return;if(preview.style.display==='none'||preview.style.display===''){preview.innerHTML=generateEAN13SVG(code,{width:200,height:70,color:'#000',bg:'#fff',showText:true});preview.style.display='block';}else{preview.style.display='none';preview.innerHTML='';}}
async function confirmDeleteBarcode(){if(!pendingDeleteId)return;try{await dbDeleteBarcode(pendingDeleteId);barcodes=barcodes.filter(b=>String(b.id)!==String(pendingDeleteId));printQueue=printQueue.filter(q=>String(q.id)!==String(pendingDeleteId));saveData();renderLibrary();renderPrintQueue();toast(currentTexts.barcodeDeleted);}catch(e){toast('Error deleting','error');}closeDeletePopup();}
function closeDeletePopup(){document.getElementById('delete-popup-overlay').style.display='none';var preview=document.getElementById('del-popup-barcode-preview');if(preview){preview.style.display='none';preview.innerHTML='';}pendingDeleteId=null;}
function handleDeletePopupOverlayClick(e){if(e.target===document.getElementById('delete-popup-overlay'))closeDeletePopup();}

// ============================================================
// LIBRARY RENDER
// ============================================================
function renderLibrary(){
  if(!barcodes||barcodes.length===undefined){
    const fallback=localStorage.getItem('bc-barcodes-fallback');
    if(fallback){try{barcodes=JSON.parse(fallback);}catch(e){}}
    if(!barcodes||barcodes.length===0){const grid=document.getElementById('barcode-grid');if(grid)grid.innerHTML='<div class="empty-state">⏳ جاري تحميل الباركودات...</div>';return;}
  }
  const search=document.getElementById('lib-search')?.value.toLowerCase()||'';
  let filtered=barcodes.filter(b=>b.title.toLowerCase().includes(search)||b.code.includes(search));
  if(selectedCategoryFilter===EXPIRED_FILTER)filtered=filtered.filter(b=>isExpiredOrSoon(b));
  else if(selectedCategoryFilter===DISABLED_FILTER)filtered=filtered.filter(b=>b.disabled===true);
  else if(selectedCategoryFilter)filtered=filtered.filter(b=>b.category===selectedCategoryFilter);
  const grid=document.getElementById('barcode-grid');if(!grid)return;
  renderLibraryCategoryFilter();
  const countBadge=document.getElementById('library-count-badge');
  if(countBadge){if((search||selectedCategoryFilter)&&filtered.length!==barcodes.length){countBadge.textContent=`${filtered.length} / ${barcodes.length}`;}else{countBadge.textContent=barcodes.length;}}
  if(filtered.length===0){grid.innerHTML=`<div class="empty-state">${currentTexts.noBarcodes}</div>`;return;}
  function renderCard(bc){
    const val=(bc.price&&bc.price!==''&&bc.price!=='0')?bc.price:null;
    let priceHtml=val?formatCurrency(val,true):'';
    const catHtml=bc.category?`<div class="cat-badge">🏷️ ${bc.category}</div>`:'';
    var expBadge='';var expiredCard='';var expiredOverlay='';
    if(bc.expDate&&bc.expDate!==''){var expD=new Date(bc.expDate+'T00:00:00');var expFormatted=String(expD.getDate()).padStart(2,'0')+'/'+String(expD.getMonth()+1).padStart(2,'0')+'/'+expD.getFullYear();var now=new Date();now.setHours(0,0,0,0);var daysLeft=Math.floor((expD-now)/(864e5));var expColor=(daysLeft<=0)?'#cc0000':(daysLeft<=7?'#cc0000':'#e07800');var expBg=(daysLeft<=7)?'rgba(204,0,0,0.12)':'rgba(224,120,0,0.12)';expBadge='<span class="badge" style="background:'+expBg+';color:'+expColor+';">📅 EXP: '+expFormatted+'</span>';if(daysLeft<=0){expiredCard=' expired-card';expiredOverlay='<div class="expired-badge-overlay">'+(currentLanguage==='ar'?'⏰ منتهي':'⏰ Expired')+'</div>';}else if(daysLeft<=7){expiredCard=' expired-card';expiredOverlay='<div class="expired-badge-overlay" style="background:#e07800;">'+(currentLanguage==='ar'?'⚠️ ينتهي قريباً':'⚠️ Expiring Soon')+'</div>';}}
    const creatorName=bc.created_by||(currentLanguage==='ar'?'غير معروف':'Unknown');
    const creatorLabel=currentLanguage==='ar'?'بواسطة:':'By:';
    return'<div class="barcode-card'+expiredCard+(bc.disabled?' disabled-card':'')+'" onclick="goToPrintWithBarcode(\''+bc.id+'\')">'
      +expiredOverlay+(bc.disabled?'<div class="disabled-badge-overlay">'+(currentLanguage==='ar'?'⛔ معطّل':'⛔ Disabled')+'</div>':'')
      +'<div style="background:#fff;padding:12px;text-align:center;">'+generateEAN13SVG(bc.code,{width:140,height:50})+'</div>'
      +'<div style="padding:12px;padding-bottom:14px;"><div class="lib-product-title">'+bc.title+'</div>'+catHtml+'<div class="lib-barcode-number">'+bc.code+'</div>'
      +'<div class="lib-creator">👤 '+creatorLabel+' '+creatorName+'</div>'
      +'<div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;justify-content:center;">'+priceHtml+(bc.weight?'<span class="badge badge-purple">⚖️ '+bc.weight+'</span>':'')+expBadge+'</div></div>'
      +'<button class="card-dl-btn" onclick="event.stopPropagation();downloadBarcode(\''+bc.id+'\')" title="'+(currentLanguage==='ar'?'تحميل PNG':'Download PNG')+'">⬇️</button>'
      +'<div class="card-actions" onclick="event.stopPropagation()">'
      +'<button class="btn btn-warning btn-sm" onclick="openEditModal(\''+bc.id+'\')" style="flex:1;">✏️ '+currentTexts.editLabel+'</button>'
      +'<button class="btn btn-success btn-sm" onclick="addOneToQueueAndPrint(\''+bc.id+'\')" style="flex:1;">🖨️ '+currentTexts.addLabel+'</button>'
      +'<button class="btn btn-danger btn-sm" onclick="deleteBarcode(\''+bc.id+'\')" style="flex:1;">🗑️ '+currentTexts.deleteLabel+'</button>'
      +'</div></div>';
  }
  grid.innerHTML=filtered.map(renderCard).join('');
}

// ============================================================
// DOWNLOAD
// ============================================================
function downloadBarcode(id){const bc=barcodes.find(b=>String(b.id)===String(id));if(!bc)return;const SCALE=3;const W=400;const BW=360;const BH=120;const NH=52;const H=BH+NH;const svgStr=generateEAN13SVG(bc.code,{width:BW,height:BH,color:'#000000',bg:'#ffffff',showText:false});const canvas=document.createElement('canvas');canvas.width=W*SCALE;canvas.height=H*SCALE;const ctx=canvas.getContext('2d');ctx.scale(SCALE,SCALE);ctx.fillStyle='#ffffff';ctx.fillRect(0,0,W,H);const svgBlob=new Blob([svgStr],{type:'image/svg+xml;charset=utf-8'});const url=URL.createObjectURL(svgBlob);const img=new Image();img.onload=function(){URL.revokeObjectURL(url);ctx.drawImage(img,(W-BW)/2,0,BW,BH);ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillStyle='#000000';let fs=36;for(;fs>=10;fs--){ctx.font='bold '+fs+'px "Courier New",monospace';if(ctx.measureText(bc.code).width<=W-16)break;}ctx.font='bold '+fs+'px "Courier New",monospace';ctx.fillText(bc.code,W/2,BH+NH/2);canvas.toBlob(function(blob){const dlName=(bc.title&&bc.title.trim()!=='')?bc.title.trim().replace(/[\\/:*?"<>|]/g,'_').substring(0,80):bc.code;const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=dlName+'.png';document.body.appendChild(a);a.click();document.body.removeChild(a);setTimeout(()=>URL.revokeObjectURL(a.href),1000);toast(currentLanguage==='ar'?'✅ تم تحميل الباركود':'✅ Barcode downloaded');},'image/png',1.0);};img.src=url;}

function goToPrintWithBarcode(id){const bc=barcodes.find(b=>String(b.id)===String(id));if(bc){printQueue=[bc];saveData();renderPrintQueue();toast(currentTexts.addedToQueue);showPage('print');renderLabelSizes();renderPrintQueue();}}
function addOneToQueueAndPrint(id){const bc=barcodes.find(b=>String(b.id)===String(id));if(bc){printQueue=[bc];saveData();renderPrintQueue();toast(currentTexts.addedToQueue);setTimeout(()=>printAllInQueue(),300);}}

// ============================================================
// QUEUE & PRINT
// ============================================================
function resetGeneratorForm(){document.getElementById('m-title').value='';document.getElementById('m-price').value='';document.getElementById('m-weight').value='';document.getElementById('m-expdate').value='';document.getElementById('m-category').value='';hideDupWarning('dup-warning-gen');const nb=generateRandomEAN13();if(nb){document.getElementById('m-barcode').value=nb;currentPreviewCode=nb;}else{document.getElementById('m-barcode').value='';currentPreviewCode='';}currentPreviewTitle='';currentPreviewPrice='';currentPreviewWeight='';currentPreviewExpDate='';renderLivePreview();}
async function _buildAndSaveFromForm(){const titleInput=document.getElementById('m-title');const title=titleInput.value.trim();if(!title){toast(currentTexts.enterProductName,'error');showInputError(titleInput,true);return null;}showInputError(titleInput,false);if(!currentPreviewCode){toast(currentLanguage==='ar'?'أنشئ باركوداً أولاً':'Generate a barcode first','error');return null;}if(isDuplicateBarcode(currentPreviewCode,null)){const inp=document.getElementById('m-barcode');showInputError(inp,true);showDupWarning('dup-warning-gen',currentPreviewCode,null);toast(currentTexts.duplicateBarcode,'error');return null;}const edEl=document.getElementById('m-expdate');const newItem={id:Date.now(),code:currentPreviewCode,title,price:document.getElementById('m-price').value,weight:document.getElementById('m-weight').value,expDate:edEl?edEl.value:'',category:document.getElementById('m-category').value,date:new Date().toISOString(),created_by:loggedInUsername};try{await dbSaveBarcode(newItem);return newItem;}catch(e){toast('Error saving barcode','error');return null;}}
async function addToQueueOnly(){const newItem=await _buildAndSaveFromForm();if(!newItem)return;barcodes.unshift(newItem);printQueue.push(newItem);saveData();renderLibrary();renderPrintQueue();toast(currentTexts.barcodeSaved);resetGeneratorForm();}
async function addToQueueAndPrint(){const nb=await _buildAndSaveFromForm();if(!nb)return;barcodes.unshift(nb);printQueue=[nb];saveData();renderLibrary();renderPrintQueue();toast(currentTexts.addedToQueue);resetGeneratorForm();setTimeout(()=>printAllInQueue(),300);}
function removeFromQueue(id){printQueue=printQueue.filter(q=>String(q.id)!==String(id));saveData();renderPrintQueue();}
function clearQueue(){if(printQueue.length===0)return;if(!confirm(currentTexts.confirmClearQueue))return;printQueue=[];saveData();renderPrintQueue();toast(currentTexts.queueCleared);}
function renderPrintQueue(){const container=document.getElementById('print-queue-list');const count=printQueue.length;const pce=document.getElementById('queue-count-print');const rce=document.getElementById('queue-count-right');if(pce)pce.textContent=count;if(rce)rce.textContent=count;if(printQueue.length===0){container.innerHTML=`<div class="empty-state" style="padding:20px">${currentTexts.emptyQueue}</div>`;return;}container.innerHTML=printQueue.map((bc,i)=>{let priceDisplay=bc.price?`<span>💰 ${formatCurrency(bc.price,false)}</span>`:'';const catHtml=bc.category?`<span style="color:var(--accent);font-size:9px;">🏷️ ${bc.category}</span>`:'';const isDisabled=bc.disabled===true;const disabledStyle=isDisabled?'filter:grayscale(100%) opacity(0.5);':'';const disabledBadge=isDisabled?`<span style="font-size:9px;background:#555;color:#aaa;padding:1px 6px;border-radius:10px;margin-inline-start:4px;">${currentLanguage==='ar'?'معطّل':'Disabled'}</span>`:'';return`<div class="pq-item" style="${disabledStyle}"><span style="color:var(--text2);font-size:11px">${i+1}.</span><div style="flex:1;padding:0 10px"><div style="font-size:13px;font-weight:700;color:var(--accent2)">${bc.title}${disabledBadge}</div>${catHtml}<div style="font-size:10px;font-family:monospace">${bc.code}</div><div style="font-size:9px;display:flex;gap:10px;margin-top:2px">${priceDisplay}${bc.weight?`<span>⚖️ ${bc.weight}</span>`:''}</div></div><button class="btn btn-danger btn-sm" onclick="removeFromQueue('${bc.id}')">✕</button></div>`;}).join('');}

// ============================================================
// PRINT
// ============================================================
function printAllInQueue(){
  if(printQueue.length===0){toast(currentTexts.emptyQueue,'error');return;}
  const size=labelSizes[selectedLabelSize];if(!size){toast('Label size error','error');return;}
  toast(currentTexts.printingStarted);
  var pw_cm = size.width + 'cm';
  var ph_cm = size.height + 'cm';
  var labelsHtml = printQueue.map(function(bc){
    return generateLabelHTML(bc.code,bc.title||(currentLanguage==='ar'?'اسم المنتج':'Product Name'),bc.price,bc.weight,selectedLabelSize,bc.expDate||'');
  }).join('<div style="page-break-after:always;"></div>');
  var printCSS = [
    '*{margin:0;padding:0;box-sizing:border-box;}',
    'html,body{margin:0;padding:0;background:white;font-family:"Noto Sans Arabic",Arial,sans-serif;}',
    '.label-container{background:white;display:flex;flex-direction:column;justify-content:space-between;align-items:center;overflow:hidden;box-sizing:border-box;}',
    '.shop-name-text{font-family:"Noto Sans Arabic",Arial,sans-serif;font-weight:900;color:#1e3c72;text-align:center;width:100%;line-height:1.2;}',
    '.product-title-text{font-family:"Tajawal","Noto Sans Arabic",Arial,sans-serif;font-weight:500;text-align:center;color:#1a1a2e;line-height:1.2;width:100%;word-break:break-word;}',
    '.barcode-wrapper{text-align:center;width:100%;}',
    '.barcode-wrapper svg{display:block;margin:0 auto;max-width:100%;}',
    '.price-weight-row{display:flex;justify-content:space-between;align-items:center;width:100%;margin-top:2px;}',
    '.price-text{font-weight:900;color:#2c7a2c;-webkit-print-color-adjust:exact;print-color-adjust:exact;}',
    '.weight-text{font-weight:bold;color:#a05e00;-webkit-print-color-adjust:exact;print-color-adjust:exact;}',
    '.exp-date-text{font-weight:bold;color:#000;-webkit-print-color-adjust:exact;print-color-adjust:exact;}',
    '.exp-date-urgent{color:#cc0000 !important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}',
    '.riyal-symbol{font-family:"SaudiRiyalSymbol",sans-serif;}',
    '@font-face{font-family:"SaudiRiyalSymbol";src:url("https://cdn.jsdelivr.net/npm/@abdulrysr/saudi-riyal-new-symbol-font@1.0.0/saudiriyal.woff2") format("woff2");}',
    '.custom-label .shop-name-text{color:#1a3c6a !important;}',
    '.custom-label .product-title-text{color:#2c3e50 !important;font-weight:700 !important;}',
    '.custom-label .price-text{color:#27ae60 !important;}',
    '.custom-label .weight-text{color:#d35400 !important;}',
    '.default-label .shop-name-text{color:#1e3c72 !important;}',
    '.default-label .product-title-text{color:#1a1a2e !important;}',
    '@page{size:'+pw_cm+' '+ph_cm+';margin:0mm;}',
    '@media print{html,body{width:'+pw_cm+';height:'+ph_cm+';margin:0;padding:0;}.label-container{width:'+pw_cm+'!important;height:'+ph_cm+'!important;}*{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}'
  ].join('');
  var htmlContent = '<!DOCTYPE html><html dir="ltr" lang="'+currentLanguage+'"><head><meta charset="UTF-8"><title>'+shopName+'</title>'
    +'<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;700;900&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">'
    +'<style>'+printCSS+'</style></head><body>'+labelsHtml+'</body></html>';
  var oldFrame=document.getElementById('__print_iframe__');
  if(oldFrame&&oldFrame.parentNode)oldFrame.parentNode.removeChild(oldFrame);
  var iframe=document.createElement('iframe');
  iframe.id='__print_iframe__';
  iframe.setAttribute('aria-hidden','true');
  iframe.style.cssText='position:fixed;top:-9999px;left:-9999px;width:0;height:0;border:none;opacity:0;pointer-events:none;';
  document.body.appendChild(iframe);
  iframe.onload=function(){
    iframe.onload=null;
    setTimeout(function(){
      try{iframe.contentWindow.focus();iframe.contentWindow.print();}
      catch(e){var pw=window.open('','_blank','width=800,height=600');if(pw){pw.document.open();pw.document.write(htmlContent);pw.document.close();pw.focus();setTimeout(function(){pw.print();},500);}}
      setTimeout(function(){if(iframe&&iframe.parentNode)iframe.parentNode.removeChild(iframe);},3000);
    },400);
  };
  try{var doc=iframe.contentDocument||iframe.contentWindow.document;doc.open('text/html','replace');doc.write(htmlContent);doc.close();}
  catch(e){iframe.srcdoc=htmlContent;}
}

// ============================================================
// LIVE PREVIEW
// ============================================================
function updatePreviewFromManual(){currentPreviewTitle=document.getElementById('m-title').value.trim();currentPreviewPrice=document.getElementById('m-price').value;currentPreviewWeight=document.getElementById('m-weight').value;var edEl=document.getElementById('m-expdate');currentPreviewExpDate=edEl?edEl.value:'';if(currentPreviewCode)renderLivePreview();else renderLivePreviewPlaceholder();}
function renderLivePreview(){const container=document.getElementById('live-preview-container');if(!currentPreviewCode){renderLivePreviewPlaceholder();return;}const isValid=validateEAN13(currentPreviewCode);document.getElementById('preview-validity').innerHTML=isValid?`<span class="badge badge-green">✅ ${currentTexts.valid}</span>`:`<span class="badge" style="background:rgba(255,107,107,0.15);color:var(--accent3)">❌ ${currentTexts.invalid}</span>`;var displayTitle=currentPreviewTitle||(currentLanguage==='ar'?'اسم المنتج':'Product Name');container.innerHTML=generateLabelHTML(currentPreviewCode,displayTitle,currentPreviewPrice,currentPreviewWeight,selectedLabelSize,currentPreviewExpDate);var dlBtn=document.getElementById('download-barcode-btn');if(dlBtn)dlBtn.style.display=isValid?'flex':'none';}
function renderLivePreviewPlaceholder(){document.getElementById('live-preview-container').innerHTML='<div style="color:#999;font-size:12px;padding:40px;">'+(currentLanguage==='ar'?'أدخل بيانات المنتج لمعاينة الملصق':'Enter product details to see preview')+'</div>';document.getElementById('preview-validity').innerHTML='';var dlBtn=document.getElementById('download-barcode-btn');if(dlBtn)dlBtn.style.display='none';}
function onManualInput(){const raw=document.getElementById('m-barcode').value.replace(/\D/g,'');document.getElementById('m-barcode').value=raw;if(raw.length>=12){const full=completeEAN13(raw);if(full){currentPreviewCode=full;if(isDuplicateBarcode(full,null)){showDupWarning('dup-warning-gen',full,null);}else{hideDupWarning('dup-warning-gen');}renderLivePreview();}}else{currentPreviewCode='';hideDupWarning('dup-warning-gen');renderLivePreviewPlaceholder();}}
function genRandomBarcode(){let full=generateRandomEAN13();let attempts=0;while(isDuplicateBarcode(full,null)&&attempts<100){full=generateRandomEAN13();attempts++;}if(!full)return;currentPreviewCode=full;document.getElementById('m-barcode').value=full;hideDupWarning('dup-warning-gen');renderLivePreview();toast(currentTexts.randomGenerated);}

// ============================================================
// EXPORT / IMPORT / CLEAR
// ============================================================
function exportData(){const data={barcodes,shopName,logoUrl,defaultPrefix,labelSizes};const a=document.createElement('a');a.href='data:application/json,'+encodeURIComponent(JSON.stringify(data,null,2));a.download='barcode-export.json';a.click();toast(currentTexts.dataExported);}
function importData(input){const file=input.files[0];if(!file)return;input.value='';const reader=new FileReader();reader.onload=async e=>{let data;try{data=JSON.parse(e.target.result);}catch(e){toast(currentTexts.invalidFile,'error');return;}if(data.shopName)shopName=data.shopName;if(data.logoUrl)logoUrl=data.logoUrl;if(data.defaultPrefix)defaultPrefix=data.defaultPrefix;if(data.labelSizes){labelSizes=data.labelSizes;localStorage.setItem('label_sizes',JSON.stringify(labelSizes));}const incoming=data.barcodes||data.library||[];if(incoming.length===0){toast(currentLanguage==='ar'?'الملف لا يحتوي على باركودات':'No barcodes found','error');return;}let saved=0,skipped=0;for(let i=0;i<incoming.length;i++){const bc={...incoming[i]};bc.title=bc.title||bc.name||'';bc.code=bc.code||bc.barcode||'';bc.price=bc.price||'';bc.weight=bc.weight||'';bc.category=bc.category||'';bc.expDate=bc.expDate||'';bc.date=bc.date||new Date().toISOString();if(!bc.created_by)bc.created_by=currentLanguage==='ar'?'غير معروف':'Unknown';if(!bc.code||!bc.title){skipped++;continue;}if(barcodes.some(b=>b.code===bc.code)){skipped++;continue;}if(!bc.id||barcodes.some(b=>b.id===bc.id))bc.id=Date.now()+i+Math.floor(Math.random()*9999);try{await dbSaveBarcode(bc);barcodes.unshift(bc);saved++;}catch(e){skipped++;}}await dbSaveSettings({shop_name:shopName,logo_url:logoUrl,default_prefix:defaultPrefix});barcodes.sort((a,b)=>new Date(b.date||0)-new Date(a.date||0));document.getElementById('header-shop-name').textContent=shopName;document.getElementById('header-logo').src=logoUrl;toast(currentLanguage==='ar'?'تم استيراد '+saved+' باركود'+(skipped>0?' (تخطي '+skipped+')':''):'Imported '+saved+' barcodes'+(skipped>0?' ('+skipped+' skipped)':''));renderLibrary();renderLabelSizes();renderCategoryDropdowns();renderCategoriesList();};reader.readAsText(file);}
async function clearAllData(){if(!confirm(currentTexts.confirmDeleteAll))return;if(USE_PHP){for(const bc of[...barcodes])await phpPost('delete.php',{id:bc.id}).catch(()=>{});}else{await idbClear('barcodes');}barcodes=[];printQueue=[];saveData();renderLibrary();renderPrintQueue();toast(currentTexts.allDataDeleted);}

// ============================================================
// HIGH-RES DOWNLOAD
// ============================================================
function downloadBarcodeHighRes(){if(!currentPreviewCode||!validateEAN13(currentPreviewCode)){toast(currentLanguage==='ar'?'باركود غير صالح':'Invalid barcode','error');return;}const SCALE=3;const W=400;const BW=360;const BH=120;const NH=52;const H=BH+NH;var svgStr=generateEAN13SVG(currentPreviewCode,{width:BW,height:BH,color:'#000000',bg:'#ffffff',showText:false});var canvas=document.createElement('canvas');canvas.width=W*SCALE;canvas.height=H*SCALE;var ctx=canvas.getContext('2d');ctx.scale(SCALE,SCALE);ctx.fillStyle='#ffffff';ctx.fillRect(0,0,W,H);var svgBlob=new Blob([svgStr],{type:'image/svg+xml;charset=utf-8'});var url=URL.createObjectURL(svgBlob);var img=new Image();img.onload=function(){URL.revokeObjectURL(url);ctx.drawImage(img,(W-BW)/2,0,BW,BH);ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillStyle='#000000';var fs=36;for(;fs>=10;fs--){ctx.font='bold '+fs+'px "Courier New",monospace';if(ctx.measureText(currentPreviewCode).width<=W-16)break;}ctx.font='bold '+fs+'px "Courier New",monospace';ctx.fillText(currentPreviewCode,W/2,BH+NH/2);var title=(currentPreviewTitle&&currentPreviewTitle.trim()!=='')?currentPreviewTitle.trim().replace(/[\\/:*?"<>|]/g,'_').substring(0,80):currentPreviewCode;canvas.toBlob(function(blob){var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=title+'.png';document.body.appendChild(a);a.click();document.body.removeChild(a);setTimeout(function(){URL.revokeObjectURL(a.href);},1000);toast(currentLanguage==='ar'?'✅ تم تحميل PNG بجودة عالية':'✅ PNG downloaded (high-res)');},'image/png',1.0);};img.onerror=function(){URL.revokeObjectURL(url);var a=document.createElement('a');a.download=(currentPreviewTitle||currentPreviewCode)+'.svg';a.href='data:image/svg+xml;charset=utf-8,'+encodeURIComponent(svgStr);document.body.appendChild(a);a.click();document.body.removeChild(a);};img.src=url;}

// ============================================================
// PWA
// ============================================================
(function(){if('serviceWorker'in navigator)navigator.serviceWorker.register('./service-worker.js').catch(()=>{});let deferredPrompt=null;window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();deferredPrompt=e;const btn=document.getElementById('pwa-install-btn');if(btn){btn.style.display='inline-flex';}});window.addEventListener('appinstalled',function(){const btn=document.getElementById('pwa-install-btn');if(btn)btn.style.display='none';deferredPrompt=null;});window.triggerPWAInstall=function(){if(deferredPrompt){deferredPrompt.prompt();deferredPrompt.userChoice.then(function(c){if(c.outcome==='accepted'){deferredPrompt=null;const btn=document.getElementById('pwa-install-btn');if(btn)btn.style.display='none';}});}};})();

document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeDeletePopup();closeEditModal();closeUserEditModal();closeUserDeletePopup();}});
document.getElementById('edit-modal').addEventListener('click',function(e){if(e.target===this)closeEditModal();});
document.getElementById('user-edit-modal').addEventListener('click',function(e){if(e.target===this)closeUserEditModal();});

// ============================================================
// LOAD FROM DB
// ============================================================
async function loadFromDB(){
  await loadFromIDB();
  try{const ctrl=new AbortController();const timer=setTimeout(()=>ctrl.abort(),5000);const ping=await fetch(API_BASE+'load.php',{signal:ctrl.signal,cache:'no-store'});clearTimeout(timer);if(ping.ok){const j=await ping.json();if(j&&j.success!==undefined)USE_PHP=true;}}catch(e){USE_PHP=false;}
  if(USE_PHP){try{const[sRes,cRes,bRes]=await Promise.allSettled([phpGet('settings.php'),phpGet('categories.php'),phpGet('load.php')]);if(sRes.status==='fulfilled'&&cRes.status==='fulfilled'&&bRes.status==='fulfilled'){const s=sRes.value;const cR=cRes.value;const bR=bRes.value;if(s.success&&s.settings){const st=s.settings;if(st.shop_name)shopName=st.shop_name;if(st.logo_url)logoUrl=st.logo_url;if(st.default_prefix)defaultPrefix=st.default_prefix;if(st.currency)selectedCurrency=st.currency;if(st.language){currentLanguage=st.language;localStorage.setItem('language',st.language);}}if(cR.success)categories=cR.categories||[];if(bR.success){barcodes=bR.barcodes||[];barcodes.sort((a,b)=>new Date(b.date||0)-new Date(a.date||0));}}else{USE_PHP=false;}}catch(e){USE_PHP=false;}}
}
async function loadFromIDB(){
  try{shopName=await idbGetSetting('shop_name',shopName);logoUrl=await idbGetSetting('logo_url',logoUrl);defaultPrefix=await idbGetSetting('default_prefix',defaultPrefix);selectedCurrency=await idbGetSetting('bc-currency',selectedCurrency);const lang=await idbGetSetting('language',currentLanguage);currentLanguage=lang;localStorage.setItem('language',lang);categories=(await idbGetAll('categories')).map(c=>c.name);barcodes=await idbGetAll('barcodes');barcodes.sort((a,b)=>new Date(b.date||0)-new Date(a.date||0));try{localStorage.setItem('bc-barcodes-fallback',JSON.stringify(barcodes));}catch(e){}try{localStorage.setItem('bc-categories-fallback',JSON.stringify(categories));}catch(e){}}catch(e){barcodes=JSON.parse(localStorage.getItem('bc-barcodes-fallback')||'[]');categories=JSON.parse(localStorage.getItem('bc-categories-fallback')||'[]');}
}

// ============================================================
// APPLY ALL LANG TEXTS
// ============================================================
function _applyAllLangTexts(lang){
  const t=currentTexts;
  const $=id=>document.getElementById(id);
  const setText=(id,txt)=>{const el=$(id);if(el)el.textContent=txt;};
  const setHTML=(id,html)=>{const el=$(id);if(el)el.innerHTML=html;};
  const sbg=$('sb-greeting-label');if(sbg)sbg.textContent=lang==='ar'?'مرحباً,':'Hello,';
  const sbRole=$('sb-user-role-text');if(sbRole)sbRole.textContent=isAdminUser?(lang==='ar'?'مدير النظام':'System Admin'):(lang==='ar'?'مستخدم نشط':'Active User');
  ['generator','library','print','users','settings'].forEach(k=>{const v=t['nav'+k.charAt(0).toUpperCase()+k.slice(1)];if(v){setText('sb-txt-'+k,v);setText('d-txt-'+k,v);}});
  setText('sb-txt-logout',t.logoutBtn);setText('d-txt-logout',t.logoutBtn);
  const statusLbl=$('edit-status-label');if(statusLbl)statusLbl.textContent=t.statusLabel;
  updateToggleLabel();
  setText('gen-title',t.genTitle);setText('label-product-name',t.productName);setText('label-price',t.price);setText('label-weight',t.weight);setText('label-barcode',t.barcodeNumber);setText('barcode-hint',t.barcodeHint);
  setHTML('save-btn','➕ '+t.addToQueue);setHTML('add-and-print-btn','🖨️ '+t.addAndPrint);
  setText('preview-title',t.previewTitle);setText('library-title',t.libraryTitle);
  const ls=$('lib-search');if(ls)ls.placeholder=t.searchPlaceholder;
  setText('click-hint-text',t.clickHint);setText('print-size-title',t.printSizeTitle);setText('custom-size-title',t.customSizeTitle);
  $('custom-name')?.setAttribute('placeholder',t.customNamePlaceholder);
  $('custom-width')?.setAttribute('placeholder',t.customWidthPlaceholder);
  $('custom-height')?.setAttribute('placeholder',t.customHeightPlaceholder);
  setText('print-settings-title',t.printSettings);setText('print-info1',t.printInfo1);setText('print-info2',t.printInfo2);setText('print-info3',t.printInfo3);
  setHTML('print-all-btn',`${t.printAll} (<span id="queue-count-print">0</span>)`);
  setHTML('clear-queue-btn',t.clearQueue);
  setHTML('queue-title',`${t.queueTitle} (<span id="queue-count-right">0</span>)`);
  setText('settings-header-title',t.settingsHeader);setText('shop-name-label',t.shopNameLabel);setText('logo-url-label',t.logoUrlLabel);setText('logo-hint',t.logoHint);setText('logo-preview-text',t.logoPreview);
  setHTML('save-header-btn',t.saveHeader);setText('default-settings-title',t.defaultSettings);setText('default-prefix-label',t.defaultPrefixLabel);setText('prefix-hint',t.prefixHint);setHTML('save-defaults-btn',t.saveDefaults);
  setText('data-mgmt-title',t.dataManagement);setHTML('export-btn',t.exportData);setHTML('import-btn',t.importData);setHTML('delete-all-btn',t.deleteAll);
  setText('edit-modal-title',t.editTitle);setText('edit-product-label',t.editProduct);setText('edit-price-label',t.editPrice);setText('edit-weight-label',t.editWeight);setText('edit-barcode-label',t.editBarcode);setHTML('save-edit-btn',t.saveEdit);setHTML('cancel-edit-btn',t.cancelEdit);
  const mt=$('m-title');if(mt)mt.placeholder=t.productPlaceholder;const mw=$('m-weight');if(mw)mw.placeholder=t.weightPlaceholder;const mb=$('m-barcode');if(mb)mb.placeholder=t.barcodePlaceholder;
  const lc=$('label-category');if(lc)lc.textContent=t.categoryLabel;const ecl=$('edit-category-label');if(ecl)ecl.textContent=t.categoryLabel;
  setText('categories-settings-title',t.categoriesTitle);const nci=$('new-category-input');if(nci)nci.placeholder=t.categoryInputPlaceholder;
  setText('currency-settings-title',t.currencyTitle);setText('currency-label',t.currencySelectLabel);setText('currency-preview-label',t.currencyPreviewLabel);
  const pait=$('print-all-inline-text');if(pait)pait.textContent=t.printAllInline;
  const led=$('label-expdate');if(led&&led.childNodes[0])led.childNodes[0].nodeValue=t.expdateLabel+' ';
  const exo=$('expdate-optional');if(exo)exo.textContent=t.expdateOptional;
  const eel=$('edit-expdate-label');if(eel&&eel.childNodes[0])eel.childNodes[0].nodeValue=t.expdateLabel+' ';
  const dbt=$('download-btn-text');if(dbt)dbt.textContent=t.downloadHighRes;
  setText('del-popup-title',t.deletePopupTitle);setText('del-popup-msg',t.deletePopupMsg);setText('del-popup-confirm-text',t.deletePopupConfirmText);setText('del-popup-cancel-text',t.deletePopupCancelText);
  setText('users-page-title',t.usersPageTitle);setText('add-user-label',t.addUserLabel);
  const nui=$('new-username-input');if(nui)nui.placeholder=lang==='ar'?'اسم المستخدم':'Username';
  const npi=$('new-password-input');if(npi)npi.placeholder=lang==='ar'?'كلمة المرور (6+ أرقام)':'Password (6+ digits)';
  const nrs=$('new-role-select');if(nrs){nrs.options[0].text='👤 '+(lang==='ar'?'مستخدم':'User');nrs.options[1].text='👑 '+(lang==='ar'?'مشرف':'Admin');}
  const ers=$('edit-user-role-select');if(ers){ers.options[0].text='👤 '+(lang==='ar'?'مستخدم — صلاحيات محدودة':'User — limited access');ers.options[1].text='👑 '+(lang==='ar'?'مشرف — صلاحيات كاملة':'Admin — full access');}
  setText('add-user-btn-text',t.addUserBtn);setText('pass-hint-text',t.passHint);
  setText('user-edit-modal-title',t.editUserModalTitle);setText('edit-user-name-label',t.editUserNameLabel);setText('edit-user-pass-label',t.editUserPassLabel);setText('edit-user-role-label',t.editUserRoleLabel);
  setText('save-user-edit-text',t.saveUserEdit);setText('cancel-user-edit-text',t.cancelUserEdit);
  updateThemeDrawerLabel();
  renderPrintQueue();
}

// ============================================================
// INIT
// ============================================================
async function init(){
  showLoadingPopup();
  updateLoadingMessage('جاري تحميل الإعدادات...', 8);
  applyTheme();
  updateLoadingMessage('جاري تحميل البيانات...', 20);
  await loadFromDB();
  updateLoadingMessage('جاري تحميل اللغة...', 38);
  currentTexts=translations[currentLanguage];
  if(currentLanguage==='ar'){document.body.classList.remove('ltr');document.body.classList.add('rtl');document.documentElement.setAttribute('dir','rtl');}
  else{document.body.classList.remove('rtl');document.body.classList.add('ltr');document.documentElement.setAttribute('dir','ltr');}
  document.getElementById('btn-ar').classList.toggle('active',currentLanguage==='ar');
  document.getElementById('btn-en').classList.toggle('active',currentLanguage==='en');
  updateLoadingMessage('جاري تجهيز الواجهة...', 52);
  const logoEl=document.getElementById('header-logo');
  if(logoEl){logoEl.src=logoUrl;logoEl.onerror=()=>{logoEl.src='image/logo.png';};}
  const shopNameEl2=document.getElementById('header-shop-name');if(shopNameEl2)shopNameEl2.textContent=shopName||currentTexts.shopName;
  const subtitleEl=document.getElementById('header-subtitle');if(subtitleEl)subtitleEl.textContent=currentTexts.subtitle;
  updateLoadingMessage('جاري تحميل التصنيفات...', 65);
  renderCategoryDropdowns();renderCategoriesList();updateCurrencyPreview();renderLabelSizes();
  updateLoadingMessage('جاري إنشاء رقم الباركود...', 78);
  let initBarcode=generateRandomEAN13();let tryCount=0;
  while(initBarcode&&isDuplicateBarcode(initBarcode,null)&&tryCount<100){initBarcode=generateRandomEAN13();tryCount++;}
  if(initBarcode){currentPreviewCode=initBarcode;const bcInput=document.getElementById('m-barcode');if(bcInput)bcInput.value=initBarcode;renderLivePreview();}
  updateLoadingMessage('جاري الانتهاء...', 90);
  renderPrintQueue();
  if(isAdminUser)renderUsersTable();
  updateLoadingMessage('✅ تم التحميل بنجاح!', 100);
  setTimeout(function(){
    closeLoadingPopup();
    document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.sidebar-nav-btn').forEach(b=>b.classList.remove('active'));
    const libPage=document.getElementById('page-library');if(libPage)libPage.classList.add('active');
    const navLib=document.getElementById('nav-library');if(navLib)navLib.classList.add('active');
    ['generator','library','print','users','settings'].forEach(p=>{const el=document.getElementById('d-nav-'+p);if(el)el.classList.toggle('active',p==='library');});
    renderLibrary();
    _applyAllLangTexts(currentLanguage);
  },300);
}

init();
</script>
</body>
</html>