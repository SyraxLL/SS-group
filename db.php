<?php
// =====================================================================
// PUANTAJ GİRİŞ SİSTEMİ - VERİTABANI BAĞLANTI DOSYASI (db.php)
// =====================================================================

$db_host = '127.0.0.1';
$db_name = 'ss_group';
$db_user = 'root';
$db_pass = ''; // XAMPP/Laragon varsayılanı boş şifredir. Gerektiğinde güncelleyin.
$db_charset = 'utf8mb4';

try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
    // =====================================================================
    // OTOMATİK VERİTABANI GÖÇÜ (Self-Healing Migrations)
    // =====================================================================
    
    // 1. users tablosuna yeni sütunların eklenmesi
    $cols_stmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = $cols_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('iban', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN iban VARCHAR(34) DEFAULT NULL");
    }
    if (!in_array('phone', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
    }
    if (!in_array('address', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN address TEXT DEFAULT NULL");
    }
    if (!in_array('salary', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN salary DECIMAL(10,2) DEFAULT 0.00");
    }
    
    // Örnek kullanıcıların yeni bilgilerle güncellenmesi (Eğer boşlarsa)
    $pdo->exec("UPDATE users SET 
        iban = 'TR560006200000012345678901', 
        phone = '+90 (555) 123 45 67', 
        address = 'Atatürk Mah. Vatan Cad. No:12 D:4 Ümraniye / İstanbul', 
        salary = 45000.00 
        WHERE id = 1 AND (iban IS NULL OR iban = '')");
        
    $pdo->exec("UPDATE users SET 
        iban = 'TR560006200000098765432109', 
        phone = '+90 (532) 987 65 43', 
        address = 'Barbaros Mah. Dereboyu Cad. Ağaoğlu Blokları A A2, Ataşehir / İstanbul', 
        salary = 55000.00 
        WHERE id = 2 AND (iban IS NULL OR iban = '')");
        
    // 2. announcements tablosunun oluşturulması ve beslenmesi
    $table_check_stmt = $pdo->query("SHOW TABLES LIKE 'announcements'");
    if ($table_check_stmt->rowCount() == 0) {
        $pdo->exec("CREATE TABLE announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $pdo->exec("INSERT INTO announcements (id, title, content) VALUES
            (1, 'Haziran Ayı Puantaj Onayları Hakkında', 'Değerli çalışma arkadaşlarımız, Haziran ayı puantaj kayıtlarınızı kontrol etmeniz ve varsa eksiklikleri en geç 25 Haziran mesai bitimine kadar İnsan Kaynakları departmanına bildirmeniz rica olunur.'),
            (2, 'Yeni Sağlık Sigortası Anlaşması', 'Şirketimiz, çalışanlarımızın özel sağlık sigortası kapsamını SS Group ayrıcalığıyla genişletmiştir. Detaylar ve poliçe örneği İK klasöründe paylaşılmıştır.'),
            (3, 'Ofis İçi Hijyen ve Çalışma Düzeni', 'Ofis ortak alanlarında sosyal düzenin ve çalışma verimliliğinin korunması amacıyla belirlenen çalışma kurallarına gösterdiğiniz hassasiyet için teşekkür ederiz.')");
    }
    
    // 3. salary_history tablosunun oluşturulması ve beslenmesi
    $table_check_stmt2 = $pdo->query("SHOW TABLES LIKE 'salary_history'");
    if ($table_check_stmt2->rowCount() == 0) {
        $pdo->exec("CREATE TABLE salary_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            period VARCHAR(7) NOT NULL,
            net_salary DECIMAL(10,2) NOT NULL,
            overtime_pay DECIMAL(10,2) DEFAULT 0.00,
            bonus DECIMAL(10,2) DEFAULT 0.00,
            payslip_available TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_period (user_id, period)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $pdo->exec("INSERT INTO salary_history (user_id, period, net_salary, overtime_pay, bonus) VALUES
            (1, '2026-05', 45000.00, 3200.00, 5000.00),
            (1, '2026-04', 45000.00, 1200.00, 0.00),
            (1, '2026-03', 42000.00, 2400.00, 1500.00),
            (2, '2026-05', 55000.00, 4800.00, 8000.00),
            (2, '2026-04', 55000.00, 1800.00, 0.00),
            (2, '2026-03', 50000.00, 3100.00, 2000.00)");
    }
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
                    <li>Veritabanı yönetim panelinize (phpMyAdmin) girerek <code>hr_puantaj</code> adında yeni bir veritabanı oluşturun.</li>
                    <li>Proje klasöründeki <code>schema.sql</code> dosyasını oluşturduğunuz bu veritabanına aktarın (import edin).</li>
                    <li>Veritabanı kullanıcı adı veya şifreniz varsayılandan farklı ise <code>db.php</code> dosyasındaki ayarları güncelleyin:
                        <code class="code-box">$db_user = 'kullanici_adiniz';<br>$db_pass = 'sifreniz';</code>
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
