<?php
// =====================================================================
// PUANTAJ GİRİŞ SİSTEMİ - ÇIKIŞ İŞLEMİ (logout.php)
// =====================================================================

session_start();

// Tüm session değişkenlerini sil
$_SESSION = [];

// Çerezleri (cookie) temizle
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Oturumu yok et
session_destroy();

// Giriş sayfasına yönlendir
header("Location: index.php");
exit;
?>
