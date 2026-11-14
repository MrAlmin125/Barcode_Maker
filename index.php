<?php
/* =========================
SESSION & DATABASE
========================= */
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true, 'samesite' => 'Lax'
]);
session_start();

require_once __DIR__ . '/api/db_credentials.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) die("DB ERROR: " . $conn->connect_error);

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: barcode.php");
    exit;
}

/* =========================
LOGIN PROCESS
========================= */
$error = "";
if (isset($_GET['timeout']) && $_GET['timeout'] === '1') {
    $error = "Session expired. Please log in again.";
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, password, is_admin FROM users WHERE username = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $hash, $is_admin);
            $stmt->fetch();
            if (password_verify($password, $hash)) {
                session_regenerate_id(true);
                $_SESSION['loggedin'] = true;
                $_SESSION['username'] = $username;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['is_admin'] = (bool)$is_admin;
                // Fixed point-in-time the 30-minute session lifetime counts
                // from (see api/config.php) - deliberately NOT refreshed on
                // every request, so it's a true absolute expiry.
                $_SESSION['login_time'] = time();
                
                // Get user permissions
                $perm_stmt = $conn->prepare("SELECT * FROM user_permissions WHERE user_id = ?");
                if ($perm_stmt) {
                    $perm_stmt->bind_param("i", $user_id);
                    $perm_stmt->execute();
                    $perms = $perm_stmt->get_result()->fetch_assoc();

                    if ($perms) {
                        $_SESSION['perm_settings']  = (bool)$perms['perm_settings'];
                        $_SESSION['perm_shop_name'] = (bool)$perms['perm_shop_name'];
                        $_SESSION['perm_logo']      = (bool)$perms['perm_logo'];
                        $_SESSION['perm_data_mang'] = (bool)($perms['perm_data_mang'] ?? false);
                    } else {
                        $_SESSION['perm_settings']  = false;
                        $_SESSION['perm_shop_name'] = false;
                        $_SESSION['perm_logo']      = false;
                        $_SESSION['perm_data_mang'] = false;
                    }
                    $perm_stmt->close();
                } else {
                    // If permissions table doesn't exist, set defaults
                    $_SESSION['perm_settings']  = true;
                    $_SESSION['perm_shop_name'] = true;
                    $_SESSION['perm_logo']      = true;
                    $_SESSION['perm_data_mang'] = true;
                }
                
                header("Location: barcode.php");
                exit;
            } else {
                $error = "Invalid Password!";
            }
        } else {
            $error = "Username not found!";
        }
        $stmt->close();
    }
}

/* =========================
SITE META CONFIG
========================= */
$site_url = "https://barcode.free.nf";
$og_title = "الضيافة — نظام طباعة الباركود";
$og_desc = "نظام متكامل لإنشاء وطباعة باركود EAN-13 للمنتجات";
$og_image = $site_url . "/image/logo.png";
?>
<!DOCTYPE html>
<html lang="ar" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($og_title); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($og_desc); ?>">

<!-- FAVICON -->
<link rel="icon" type="image/png" sizes="32x32" href="image/logo.png">
<link rel="icon" type="image/png" sizes="16x16" href="image/logo.png">
<link rel="shortcut icon" type="image/x-icon" href="image/logo.png">
<link rel="apple-touch-icon" sizes="180x180" href="image/logo.png">
<link rel="apple-touch-icon-precomposed" href="image/logo.png">
<link rel="manifest" href="./manifest.json">

<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<!-- OPEN GRAPH -->
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo htmlspecialchars($site_url); ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($og_title); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($og_desc); ?>">
<meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
<meta property="og:image:width" content="400">
<meta property="og:image:height" content="400">
<meta property="og:locale" content="ar_SA">
<meta property="og:site_name" content="الضيافة">

<!-- TWITTER CARD -->
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?php echo htmlspecialchars($og_title); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($og_desc); ?>">
<meta name="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>">

<!-- PWA & BROWSER -->
<meta name="theme-color" content="#FFA500">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="الضيافة">
<meta name="application-name" content="الضيافة - باركود">
<meta name="msapplication-TileImage" content="image/logo.png">
<meta name="msapplication-TileColor" content="#f1ac14">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">

<div class="page-wrapper">

  <!-- PRELOADER WITH CIRCLE BORDER -->
  <div id="login-preloader">
    <img src="image/logo.png" class="preloader-logo" alt="Loading">
  </div>

  <!-- CLOCK -->
  <div class="top-clock">
    <div class="clock-time" id="clock-time">00<span class="colon">:</span>00</div>
    <div class="clock-ampm" id="clock-ampm">AM</div>
    <div class="clock-date" id="clock-date">--</div>
    <div class="clock-divider"></div>
  </div>

  <!-- LOGIN AREA -->
  <div class="login-area">
    <div class="login-wrapper">

      <!-- WELCOME - Dynamic time + "WELCOME BACK" text -->
      <div class="welcome-container">
        <div class="welcome-label" id="welcome-label">🌅 GOOD MORNING</div>
        <div class="welcome-greeting" id="welcome-greeting">WELCOME BACK, <span>Friend!</span></div>
        <div class="welcome-divider"></div>
      </div>

      <!-- LOGIN BOX -->
      <div class="login-box <?php echo !empty($error) ? 'shake' : ''; ?>">
        <img src="image/logo.png" class="logo-img" alt="Logo">
        <h2 class="barcode-headline">Barcode Login</h2>

        <?php if(!empty($error)): ?>
          <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" id="loginForm">
          <div class="input-group">
            <input type="text" name="username" id="username" required autocomplete="username" placeholder=" ">
            <i class="fas fa-user input-icon"></i>
            <label for="username">Username</label>
          </div>

          <div class="input-group password-box">
            <input type="password" name="password" id="password" required autocomplete="current-password" placeholder=" ">
            <i class="fas fa-lock input-icon"></i>
            <label for="password">Password</label>
            <span class="toggle-password" onclick="togglePassword()" id="toggleIcon">
              <i class="fa-regular fa-eye"></i>
            </span>
          </div>

          <div class="remember-row">
            <input type="checkbox" id="rememberMe" onchange="handleRemember()">
            <label for="rememberMe">Remember Me</label>
          </div>

          <button type="submit" id="loginBtn">Login</button>
        </form>
      </div>

    </div>
  </div>

  <!-- FOOTER -->
  <footer class="app-footer">
    <div>Developed By</div>
    <div class="footer-links">
      <a class="lnk-ig" href="https://instagram.com/al.amin125" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" style="width:16px;height:16px;vertical-align:middle;" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="2" y="2" width="20" height="20" rx="5" ry="5" stroke="#e1306c" stroke-width="2" fill="none"/>
          <circle cx="12" cy="12" r="4.5" stroke="#e1306c" stroke-width="2" fill="none"/>
          <circle cx="17.5" cy="6.5" r="1.2" fill="#e1306c"/>
        </svg>
        Mr.Amin
      </a>
      <a class="lnk-fb" href="https://facebook.com/mr.amin4125" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" style="width:16px;height:16px;vertical-align:middle;" fill="#1877f2" xmlns="http://www.w3.org/2000/svg">
          <path d="M22 12c0-5.522-4.478-10-10-10S2 6.478 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987H7.898V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.891h-2.33v6.987C18.343 21.128 22 16.991 22 12z"/>
        </svg>
        Mr.Amin
      </a>
       <a href="https://amin.free.nf/" target="_blank" rel="noopener" style="color:#FFC700; text-decoration:none;">
    <span style="
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:18px;
        height:18px;
        border-radius:50%;
        background:#FFC700;
        vertical-align:middle;
        ">
    <i class="fa-brands fa-edge-legacy" style="color:#fff; font-size:11px;"></i>
    </span>

    &nbsp;Mr.Amin
</a>     
    </div>
  </footer>

</div>

<script src="assets/js/common.js"></script>
</body>
</html>