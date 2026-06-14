<?php
// =====================================================================
// PUANTAJ GİRİŞ SİSTEMİ - VERİTABANI BAĞLANTI DOSYASI (db.php)
// =====================================================================

// =====================================================================
// .ENV DOSYASI YÜKLEME YARDIMCISI (Dotenv Parser)
// =====================================================================
if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Yorum satırlarını ve boşlukları atla
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                // Tırnak işaretlerini temizle
                $value = trim($value, "\"'");
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Projenin kök dizinindeki .env dosyasını yükle
loadEnv(__DIR__ . '/.env');

$db_host    = $_ENV['DB_HOST'] ?? '127.0.0.1';
$db_name    = $_ENV['DB_NAME'] ?? 'ss_group';
$db_user    = $_ENV['DB_USER'] ?? 'root';
$db_pass    = $_ENV['DB_PASS'] ?? '';
$db_charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
} catch (\PDOException $e) {
    // Veritabanı bağlantı hatası durumunda gösterilecek şık hata arayüzü
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Veritabanı Bağlantı Hatası | SS Group</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            :root {
                --bg-color: #0b0f19;
                --card-bg: rgba(255, 255, 255, 0.03);
                --card-border: rgba(255, 255, 255, 0.08);
                --text-main: #f3f4f6;
                --text-muted: #9ca3af;
                --accent-red: #ef4444;
                --accent-blue: #3b82f6;
                --font-sans: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }

            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
                background: radial-gradient(circle at 50% 50%, #111827 0%, #0b0f19 100%);
                color: var(--text-main);
                font-family: var(--font-sans);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .container {
                max-width: 600px;
                width: 100%;
                background: var(--card-bg);
                border: 1px solid var(--card-border);
                border-radius: 24px;
                padding: 40px;
                backdrop-filter: blur(20px);
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
                text-align: center;
                animation: fadeIn 0.6s ease-out;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .error-icon {
                font-size: 64px;
                color: var(--accent-red);
                margin-bottom: 24px;
                display: inline-block;
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }

            h1 {
                font-size: 24px;
                font-weight: 700;
                margin-bottom: 16px;
                color: var(--text-main);
                letter-spacing: -0.5px;
            }

            p {
                color: var(--text-muted);
                font-size: 15px;
                line-height: 1.6;
                margin-bottom: 24px;
            }

            .instructions {
                background: rgba(0, 0, 0, 0.2);
                border-radius: 16px;
                padding: 20px;
                text-align: left;
                margin-bottom: 30px;
                border: 1px solid rgba(255, 255, 255, 0.04);
            }

            .instructions h2 {
                font-size: 16px;
                font-weight: 600;
                margin-bottom: 12px;
                color: var(--accent-blue);
            }

            .instructions ol {
                padding-left: 20px;
                color: var(--text-muted);
                font-size: 14px;
            }

            .instructions li {
                margin-bottom: 8px;
                line-height: 1.5;
            }

            .code-box {
                background: #000;
                padding: 10px;
                border-radius: 8px;
                font-family: monospace;
                font-size: 13px;
                color: #34d399;
                margin-top: 8px;
                display: block;
                overflow-x: auto;
            }

            .retry-btn {
                background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                color: white;
                border: none;
                padding: 12px 30px;
                font-size: 15px;
                font-weight: 600;
                border-radius: 12px;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-block;
                box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
            }

            .retry-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
            }

            .technical-details {
                margin-top: 30px;
                font-size: 12px;
                color: rgba(255, 255, 255, 0.2);
                border-top: 1px solid rgba(255, 255, 255, 0.05);
                padding-top: 15px;
                text-align: left;
                word-break: break-all;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <span class="error-icon">⚠️</span>
            <h1>Veritabanına Bağlanılamadı</h1>
            <p>Sistem şu anda MySQL veritabanı sunucusu ile iletişim kuramıyor. Lütfen veritabanı kurulum adımlarını kontrol edin.</p>
            
            <div class="instructions">
                <h2>Kurulum Adımları:</h2>
                <ol>
                    <li>MySQL veritabanı sunucunuzun (XAMPP, WampServer, Laragon vb.) açık olduğundan emin olun.</li>
                    <li>Veritabanı yönetim panelinize (phpMyAdmin) girerek <code>ss_group</code> adında yeni bir veritabanı oluşturun.</li>
                    <li>Proje klasöründeki <code>database.sql</code> dosyasını oluşturduğunuz bu veritabanına aktarın (import edin).</li>
                    <li>Veritabanı kullanıcı adı veya şifreniz varsayılandan farklı ise <code>.env</code> dosyasındaki ayarları güncelleyin:
                        <code class="code-box">DB_USER=kullanici_adiniz<br>DB_PASS=sifreniz</code>
                    </li>
                </ol>
            </div>

            <a href="" class="retry-btn">Tekrar Dene</a>

            <div class="technical-details">
                <strong>Hata Detayı:</strong> <?php echo htmlspecialchars($e->getMessage()); ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
