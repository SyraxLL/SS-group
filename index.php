<?php
// =====================================================================
// PUANTAJ GİRİŞ SİSTEMİ - GİRİŞ SAYFASI (index.php)
// =====================================================================

session_start();

// Kullanıcı oturumu zaten aktifse doğrudan panel sayfasına yönlendir
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}

// Hata mesajını al
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Puantaj Giriş Sistemi Kullanıcı Paneli Giriş Ekranı. İnsan kaynakları personeli için giriş sistemi.">
    <title>Giriş Yap | SS Group</title>
    <!-- FontAwesome for Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    
    <!-- Decorative Background Blobs -->
    <div class="bg-blobs">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <!-- Login Area -->
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div style="margin-bottom: 20px; text-align: center;">
                    <img src="sslogo.png" alt="SS Group Logo" style="height: 70px; max-width: 200px; object-fit: contain;">
                </div>
                <p class="login-subtitle">Devam Kontrol ve Takip Paneli</p>
            </div>

            <!-- Error Alerts -->
            <?php if ($error === 'empty'): ?>
                <div class="alert alert-danger" id="alert-empty">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Lütfen tüm alanları doldurun.</span>
                </div>
            <?php elseif ($error === 'invalid'): ?>
                <div class="alert alert-danger" id="alert-invalid">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <span>Kullanıcı adı veya şifre hatalı.</span>
                </div>
            <?php elseif ($error === 'auth'): ?>
                <div class="alert alert-danger" id="alert-auth">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Bu sayfayı görüntülemek için giriş yapmalısınız.</span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="login_action.php" method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="username" class="form-label">Kullanıcı Adı</label>
                    <div class="input-container">
                        <input type="text" id="username" name="username" class="form-input" placeholder="kullanici.adi" required autocomplete="off">
                        <i class="fa-solid fa-user input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Şifre</label>
                    <div class="input-container">
                        <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required autocomplete="current-password">
                        <i class="fa-solid fa-lock input-icon"></i>
                    </div>
                </div>

                <button type="submit" class="login-btn" id="login-btn-submit">
                    Giriş Yap <i class="fa-solid fa-right-to-bracket" style="margin-left: 6px;"></i>
                </button>
            </form>

            <!-- Test Credentials Guide -->
            <div class="info-box">
                <div class="info-title"><i class="fa-solid fa-circle-info"></i> Örnek Giriş Bilgileri</div>
                <div class="info-text" style="margin-top: 5px;">
                    <strong>Ahmet Yılmaz:</strong><br>
                    Kullanıcı Adı: <code style="color: var(--primary);">ahmet.yilmaz</code><br>
                    Şifre: <code style="color: var(--primary);">ahmet123</code>
                </div>
                <div class="info-text" style="margin-top: 10px;">
                    <strong>Mehmet Demir:</strong><br>
                    Kullanıcı Adı: <code style="color: var(--primary);">mehmet.demir</code><br>
                    Şifre: <code style="color: var(--primary);">mehmet456</code>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
