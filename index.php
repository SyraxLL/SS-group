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
    <meta name="description" content="Puantaj Giriş Sistemi Kullanıcı Paneli Giriş Ekranı.">
    <title>Giriş Yap | SS Group</title>
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <!-- FontAwesome for Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* =====================================================================
           PREMIUM STATIC SLATE GRAY DESIGN - CLEAN & SIMPLE (NO NEON)
           ===================================================================== */
        
        /* 1. Reset and Core Layout */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 24px 16px;
        }

        /* 4. Elegant Card Wrapper */
        .card-border-wrap {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 420px;
            margin: auto;
            border-radius: 20px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.06);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            animation: scaleUp 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .card-border-wrap:hover {
            transform: translateY(-4px);
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.1);
        }

        /* 5. Clean Solid Grayish Card Container */
        .login-card {
            width: 100%;
            background: #f8fafc;
            border-radius: 20px;
            padding: 44px 38px;
            position: relative;
        }

        @keyframes scaleUp {
            from {
                opacity: 0;
                transform: scale(0.97) translateY(8px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* 6. Header Components */
        .login-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .login-logo-wrapper {
            position: relative;
            display: inline-block;
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .login-logo-wrapper:hover {
            transform: scale(1.03);
        }

        .login-logo-wrapper img {
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.05));
            transition: all 0.3s ease;
        }

        .login-subtitle {
            color: #475569;
            font-size: 13.5px;
            font-weight: 550;
            margin-top: 12px;
            letter-spacing: -0.010em;
        }

        /* 7. Input Fields & Focus Styling */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 7px;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            transition: all 0.2s ease;
        }

        .input-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: #94a3b8;
            font-size: 15px;
            pointer-events: none;
            transition: all 0.2s ease;
            z-index: 5;
        }

        .form-input {
            width: 100%;
            height: 48px;
            padding: 0 16px 0 46px;
            background: #e2e8f0;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            color: #0f172a;
            transition: all 0.2s ease;
        }

        #password {
            padding-right: 46px;
        }

        .form-input:hover {
            border-color: #94a3b8;
            background: #f1f5f9;
        }

        .form-input:focus {
            outline: none;
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .form-input:focus + .input-icon {
            color: #2563eb;
        }

        .form-group:focus-within .form-label {
            color: #2563eb;
        }

        /* 8. Interactive Password Visibility Toggle */
        .password-toggle-btn {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            border-radius: 8px;
            transition: all 0.2s;
            z-index: 5;
        }

        .password-toggle-btn:hover {
            color: #475569;
            background: #f1f5f9;
        }

        /* 9. Sleek Action Button */
        .login-btn {
            position: relative;
            width: 100%;
            height: 48px;
            background: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-weight: 600;
            font-size: 14.5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .login-btn:hover {
            background: #1d4ed8;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
        }

        .login-btn:active {
            transform: translateY(0.5px);
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
        }

        /* 10. Alerts Box */
        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.04);
            border: 1px solid rgba(239, 68, 68, 0.15);
        }

        .alert-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        /* 11. Info Box Credentials (Grayish Accent) */
        .info-box {
            margin-top: 26px;
            padding: 16px;
            border-radius: 10px;
            background: #cbd5e1;
            border: 1px solid #94a3b8;
            transition: all 0.2s ease;
        }

        .info-box:hover {
            background: #cbd5e1;
            border-color: #64748b;
        }

        .info-title {
            font-size: 11.5px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-text {
            font-size: 12px;
            color: #334155;
            line-height: 1.5;
            transition: all 0.2s ease;
            padding: 6px 8px;
            border-radius: 6px;
        }

        .info-text:hover {
            background: rgba(15, 23, 42, 0.05);
            padding-left: 12px;
        }

        .info-text code {
            font-family: monospace;
            font-weight: 600;
            background: #f1f5f9;
            padding: 1px 5px;
            border-radius: 4px;
            margin-left: 2px;
        }

        /* 12. Media Queries for Mobile Responsiveness */
        @media (max-width: 480px) {
            body {
                padding: 16px;
            }

            .card-border-wrap {
                border-radius: 16px;
            }

            .login-card {
                padding: 36px 24px;
                border-radius: 16px;
            }

            .login-logo-wrapper img {
                height: 60px !important;
            }
        }
    </style>
</head>

<body>


    <!-- Elevating Card Wrapper -->
    <div class="card-border-wrap">
        <!-- Glassmorphic Login Card -->
        <div class="login-card">

            <div class="login-header">
                <div class="login-logo-wrapper">
                    <img src="sslogo.png" alt="SS Group Logo"
                        style="height: 72px; max-width: 220px; object-fit: contain; display: block; margin: 0 auto;">
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
                    <span>Giriş yapmanız gerekmektedir.</span>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form action="login_action.php" method="POST" autocomplete="off">

                <div class="form-group">
                    <label for="username" class="form-label">Kullanıcı Adı</label>
                    <div class="input-container">
                        <input type="text" id="username" name="username" class="form-input" placeholder="kullanici.adi"
                            required>
                        <i class="fa-solid fa-user input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Şifre</label>
                    <div class="input-container">
                        <input type="password" id="password" name="password" class="form-input" placeholder="••••••••"
                            required autocomplete="current-password">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <button type="button" class="password-toggle-btn" onclick="togglePassword()"
                            title="Şifreyi Göster/Gizle">
                            <i class="fa-solid fa-eye" id="password-toggle-icon"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <button type="submit" class="login-btn" id="login-btn-submit">
                        Giriş Yap <i class="fa-solid fa-right-to-bracket"></i>
                    </button>
                </div>
            </form>

            <!-- Test Credentials Guide (Autofill Active) -->
            <div class="info-box">
                <div class="info-title"><i class="fa-solid fa-circle-info"></i> Örnek Giriş Bilgileri</div>
                <div class="info-text" style="cursor: pointer;" onclick="fillCredentials('ahmet.yilmaz', 'ahmet123')"
                    title="Tıkla ve Doldur">
                    <strong>Ahmet Yılmaz:</strong><br>
                    Kullanıcı Adı: <code style="color: #2563eb;">ahmet.yilmaz</code><br>
                    Şifre: <code style="color: #2563eb;">ahmet123</code>
                </div>
                <div class="info-text" style="cursor: pointer;" onclick="fillCredentials('mehmet.demir', 'mehmet456')"
                    title="Tıkla ve Doldur">
                    <strong>Mehmet Demir:</strong><br>
                    Kullanıcı Adı: <code style="color: #2563eb;">mehmet.demir</code><br>
                    Şifre: <code style="color: #2563eb;">mehmet456</code>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Password Visibility Toggle Handler
        function togglePassword() {
            const passInput = document.getElementById('password');
            const toggleIcon = document.getElementById('password-toggle-icon');

            if (passInput.type === 'password') {
                passInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Autofill credentials when clicking helper chips
        function fillCredentials(user, pass) {
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');

            // Set values
            usernameInput.value = user;
            passwordInput.value = pass;

            // Trigger focus styling temporarily
            usernameInput.classList.add('autofilled');
            passwordInput.classList.add('autofilled');

            // Visual click feedback on the submit button
            const submitBtn = document.getElementById('login-btn-submit');
            submitBtn.style.transform = 'scale(0.95) translateY(1px)';
            setTimeout(() => {
                submitBtn.style.transform = 'none';
                usernameInput.classList.remove('autofilled');
                passwordInput.classList.remove('autofilled');
            }, 250);
        }
    </script>
</body>

</html>