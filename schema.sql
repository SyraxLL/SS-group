-- =====================================================================
-- PUANTAJ GİRİŞ SİSTEMİ - VERİTABANI ŞEMASI (schema.sql)
-- =====================================================================
-- Bu dosyayı MySQL veritabanınızda (örn. phpMyAdmin veya MySQL CLI)
-- içe aktararak tabloları ve örnek verileri otomatik olarak oluşturabilirsiniz.

CREATE DATABASE IF NOT EXISTS `ss_group` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ss_group`;

-- ÖNCEKİ TABLOLARI SİL (Eğer varsa hata vermemesi için sıralı silinmeli)
DROP TABLE IF EXISTS `salary_history`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `puantaj_records`;
DROP TABLE IF EXISTS `users`;

-- 1. KULLANICILAR TABLOSU (users)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) UNIQUE DEFAULT NULL,
  `department` VARCHAR(100) DEFAULT NULL,
  `company` VARCHAR(100) DEFAULT NULL,
  `avatar_url` VARCHAR(255) DEFAULT NULL,
  -- Yeni Eklenen Sütunlar
  `iban` VARCHAR(34) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `salary` DECIMAL(10,2) DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. PUANTAJ KAYITLARI TABLOSU (puantaj_records)
CREATE TABLE IF NOT EXISTS `puantaj_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `record_date` DATE NOT NULL,
  `check_in` TIME DEFAULT NULL,
  `check_out` TIME DEFAULT NULL,
  `status` ENUM('Normal', 'Izinli', 'Devamsiz') DEFAULT 'Normal',
  `leave_type` VARCHAR(50) DEFAULT NULL, -- Yıllık İzin, Raporlu, Ücretsiz İzin vb.
  `description` TEXT DEFAULT NULL, -- Açıklama detayları
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_date` (`user_id`, `record_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. DUYURULAR TABLOSU (announcements)
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. MAAŞ VE BORDRO GEÇMİŞİ TABLOSU (salary_history)
CREATE TABLE IF NOT EXISTS `salary_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `period` VARCHAR(7) NOT NULL, -- 'YYYY-MM' formatı (örn: '2026-05')
  `net_salary` DECIMAL(10,2) NOT NULL,
  `overtime_pay` DECIMAL(10,2) DEFAULT 0.00,
  `bonus` DECIMAL(10,2) DEFAULT 0.00,
  `payslip_available` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_period` (`user_id`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- ÖRNEK VERİLERİN EKLEMESİ
-- =====================================================================

-- NOT: Şifreler bcrypt algoritması ile şifrelenmiştir. (Şifreler: ahmet123 ve mehmet456)
INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `department`, `company`, `avatar_url`, `iban`, `phone`, `address`, `salary`) VALUES
(1, 'ahmet.yilmaz', '$2y$10$e0myzW.r5M3E.yLpB5.O5OfqVj.oPZqFw/mXyEwD9P3Qv.1QyZqZy', 'Ahmet Yılmaz', NULL, 'İnsan Kaynakları', 'SS Group', 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150', 'TR560006200000012345678901', '+90 (555) 123 45 67', 'Atatürk Mah. Vatan Cad. No:12 D:4 Ümraniye / İstanbul', 45000.00),
(2, 'mehmet.demir', '$2y$10$vWd2Gq.hY3gPqF5.t5.O5O2wGj.oPZqFw/mXyEwD9P3Qv.1QyZqZy', 'Mehmet Demir', NULL, 'Yazılım Geliştirme', 'SS Group', 'https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?w=150', 'TR560006200000098765432109', '+90 (532) 987 65 43', 'Barbaros Mah. Dereboyu Cad. Ağaoğlu Blokları A A2, Ataşehir / İstanbul', 55000.00);

-- Ahmet Yılmaz için Örnek Puantaj Verileri (Haziran 2026)
INSERT INTO `puantaj_records` (`user_id`, `record_date`, `check_in`, `check_out`, `status`, `leave_type`, `description`) VALUES
(1, '2026-06-01', '08:30:00', '17:30:00', 'Normal', NULL, 'Olağan çalışma günü.'),
(1, '2026-06-02', '08:25:00', '17:45:00', 'Normal', NULL, 'Olağan çalışma günü.'),
(1, '2026-06-03', '08:30:00', '19:30:00', 'Normal', NULL, 'Proje teslimi için 2 saat fazla mesai yapıldı.'),
(1, '2026-06-04', '08:40:00', '17:35:00', 'Normal', NULL, 'Sabah 10 dk gecikmeli giriş.'),
(1, '2026-06-05', '08:30:00', '17:30:00', 'Normal', NULL, 'Haftalık kapanış yapıldı.'),
(1, '2026-06-08', '08:30:00', '17:30:00', 'Normal', NULL, 'Olağan çalışma günü.'),
(1, '2026-06-09', NULL, NULL, 'Izinli', 'Yıllık İzin', 'Yıllık ücretli izin kullanıldı.'),
(1, '2026-06-10', NULL, NULL, 'Izinli', 'Yıllık İzin', 'Yıllık ücretli izin kullanıldı.'),
(1, '2026-06-11', '08:30:00', '17:30:00', 'Normal', NULL, 'İzin dönüşü ilk iş günü.'),
(1, '2026-06-12', '08:30:00', '20:30:00', 'Normal', NULL, 'Ay sonu hazırlıkları için 3 saat fazla mesai yapıldı.'),
(1, '2026-06-15', NULL, NULL, 'Devamsiz', NULL, 'Habersiz işe gelmeme, tutanak tutuldu.'),
(1, '2026-06-16', '08:30:00', '17:30:00', 'Normal', NULL, 'Olağan çalışma günü.');

-- Mehmet Demir için Örnek Puantaj Verileri (Haziran 2026)
INSERT INTO `puantaj_records` (`user_id`, `record_date`, `check_in`, `check_out`, `status`, `leave_type`, `description`) VALUES
(2, '2026-06-01', '09:00:00', '18:00:00', 'Normal', NULL, 'Backend API entegrasyonu.'),
(2, '2026-06-02', '09:00:00', '21:00:00', 'Normal', NULL, 'Sunucu göçü çalışması nedeniyle 3 saat fazla mesai.'),
(2, '2026-06-03', '08:55:00', '18:00:00', 'Normal', NULL, 'Olağan yazılım geliştirme çalışması.'),
(2, '2026-06-04', NULL, NULL, 'Izinli', 'Raporlu', 'Sağlık raporu ibraz edildi (Diş tedavisi).'),
(2, '2026-06-05', '09:00:00', '18:00:00', 'Normal', NULL, 'Haftalık sprint planlaması.'),
(2, '2026-06-08', '09:00:00', '18:00:00', 'Normal', NULL, 'Olağan yazılım geliştirme çalışması.'),
(2, '2026-06-09', '09:00:00', '18:00:00', 'Normal', NULL, 'Kod inceleme (Code Review) seansı.'),
(2, '2026-06-10', '09:00:00', '20:00:00', 'Normal', NULL, 'Bug fix çalışmaları için 2 saat fazla mesai.'),
(2, '2026-06-11', '09:05:00', '18:00:00', 'Normal', NULL, 'Olağan yazılım geliştirme çalışması.'),
(2, '2026-06-12', '09:00:00', '18:00:00', 'Normal', NULL, 'Haftalık sprint değerlendirmesi.');

-- Örnek Duyurular
INSERT INTO `announcements` (`id`, `title`, `content`) VALUES
(1, 'Haziran Ayı Puantaj Onayları Hakkında', 'Değerli çalışma arkadaşlarımız, Haziran ayı puantaj kayıtlarınızı kontrol etmeniz ve varsa eksiklikleri en geç 25 Haziran mesai bitimine kadar İnsan Kaynakları departmanına bildirmeniz rica olunur.'),
(2, 'Yeni Sağlık Sigortası Anlaşması', 'Şirketimiz, çalışanlarımızın özel sağlık sigortası kapsamını SS Group ayrıcalığıyla genişletmiştir. Detaylar ve poliçe örneği İK klasöründe paylaşılmıştır.'),
(3, 'Ofis İçi Hijyen ve Çalışma Düzeni', 'Ofis ortak alanlarında sosyal düzenin ve çalışma verimliliğinin korunması amacıyla belirlenen çalışma kurallarına gösterdiğiniz hassasiyet için teşekkür ederiz.');

-- Örnek Maaş Geçmişleri
INSERT INTO `salary_history` (`user_id`, `period`, `net_salary`, `overtime_pay`, `bonus`, `payslip_available`) VALUES
(1, '2026-05', 45000.00, 3200.00, 5000.00, 1),
(1, '2026-04', 45000.00, 1200.00, 0.00, 1),
(1, '2026-03', 42000.00, 2400.00, 1500.00, 1),
(2, '2026-05', 55000.00, 4800.00, 8000.00, 1),
(2, '2026-04', 55000.00, 1800.00, 0.00, 1),
(2, '2026-03', 50000.00, 3100.00, 2000.00, 1);
