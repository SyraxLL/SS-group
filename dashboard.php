<?php
// =====================================================================
// SS GROUP PUANTAJ SİSTEMİ - KULLANICI PANELİ (dashboard.php)
// =====================================================================

session_start();
require_once 'db.php'; // Veritabanı bağlantısı (PDO)

// Oturum kontrolü
if (!isset($_SESSION['user'])) {
    header("Location: index.php?error=auth");
    exit;
}

$sessionUser = $_SESSION['user'];

// Kullanıcı bilgilerini veritabanından tazeleyelim (iban, phone vb. güncel veriler için)
try {
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $user_stmt->execute(['id' => $sessionUser['id']]);
    $currentUser = $user_stmt->fetch();
    
    if (!$currentUser) {
        // Kullanıcı silindiyse oturumu kapat
        header("Location: logout.php");
        exit;
    }
} catch (\PDOException $e) {
    $currentUser = $sessionUser;
}

// Kullanıcı adının baş harflerini hesaplayalım (Profil avatarı için)
$nameWords = explode(' ', $currentUser['full_name']);
$userInitials = '';
if (!empty($nameWords)) {
    $userInitials .= mb_substr($nameWords[0], 0, 1, 'UTF-8');
    if (count($nameWords) > 1) {
        $userInitials .= mb_substr($nameWords[count($nameWords) - 1], 0, 1, 'UTF-8');
    }
}
$userInitials = mb_strtoupper($userInitials, 'UTF-8');

// Zaman hesaplama yardımcı fonksiyonu
function calculatePuantaj($check_in, $check_out, $status) {
    if ($status !== 'Normal' || empty($check_in) || empty($check_out)) {
        return ['worked' => 0, 'overtime' => 0];
    }
    
    $start = strtotime($check_in);
    $end = strtotime($check_out);
    
    if ($end <= $start) {
        return ['worked' => 0, 'overtime' => 0];
    }
    
    $total_hours = ($end - $start) / 3600;
    
    // Standart mesai: 9 saat (8 saat çalışma + 1 saat yemek arası)
    if ($total_hours > 9) {
        $worked = 8;
        $overtime = $total_hours - 9;
    } else {
        $worked = max(0, $total_hours - 1);
        $overtime = 0;
    }
    
    return [
        'worked' => round($worked, 1),
        'overtime' => round($overtime, 1)
    ];
}

// ----------------------------------------------------
// VERİ ÇEKME - MAAŞ GEÇMİŞİ
// ----------------------------------------------------
$salaryHistory = [];
try {
    $sal_stmt = $pdo->prepare("SELECT * FROM salary_history WHERE user_id = :user_id ORDER BY period DESC");
    $sal_stmt->execute(['user_id' => $currentUser['id']]);
    $salaryHistory = $sal_stmt->fetchAll();
} catch (\PDOException $e) {
    // Boş maaş geçmişi
}

$latestSalary = !empty($salaryHistory) ? $salaryHistory[0] : null;

// ----------------------------------------------------
// VERİ ÇEKME - PUANTAJ VE İSTATİSTİKLER
// ----------------------------------------------------
$filter_month = isset($_GET['filter_month']) ? trim($_GET['filter_month']) : '';

// Filtreli Puantaj Sorgusu
$sql = "SELECT p.*, u.full_name FROM puantaj_records p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.user_id = :user_id AND p.status = 'Normal'";
$params = ['user_id' => $currentUser['id']];

if (!empty($filter_month)) {
    $sql .= " AND DATE_FORMAT(p.record_date, '%Y-%m') = :filter_month";
    $params['filter_month'] = $filter_month;
}
$sql .= " ORDER BY p.record_date DESC";

try {
    $p_stmt = $pdo->prepare($sql);
    $p_stmt->execute($params);
    $records = $p_stmt->fetchAll();
} catch (\PDOException $e) {
    $records = [];
}

// İSTATİSTİK HESAPLAMALARI (Seçili Filtreye Göre)
$total_worked_hours = 0;
$total_overtime_hours = 0;
$total_leave_days = 0;
$total_absent_days = 0;
$total_worked_days = 0;

foreach ($records as $row) {
    if ($row['status'] === 'Normal') {
        $calc = calculatePuantaj($row['check_in'], $row['check_out'], $row['status']);
        $total_worked_hours += $calc['worked'];
        $total_overtime_hours += $calc['overtime'];
        $total_worked_days++;
    } elseif ($row['status'] === 'Izinli') {
        $total_leave_days++;
    } elseif ($row['status'] === 'Devamsiz') {
        $total_absent_days++;
    }
}

// Devamlılık Oranı hesaplama: Çalışılan gün / (Çalışılan Gün + Devamsız Gün)
$total_active_days = $total_worked_days + $total_absent_days;
$attendance_rate = $total_active_days > 0 ? round(($total_worked_days / $total_active_days) * 100) : 100;

// Ortak Maaş Parametreleri
$daily_wage = round($currentUser['salary'] / 30, 2);
$hourly_rate = $daily_wage / 8;
$extra_pay = 200.00;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <script>
        // Prevents flash of light theme
        if (localStorage.getItem('ss_theme') === 'dark') {
            document.documentElement.classList.add('dark-theme');
        }
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SS Group Çalışan Paneli ve Puantaj Takip Sistemi">
    <title>Puantaj Takip Paneli | SS Group</title>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <script>
        if (localStorage.getItem('ss_theme') === 'dark') {
            document.body.classList.add('dark-theme');
        }
    </script>

    <!-- Top Navigation Bar -->
    <nav class="top-nav">
        <!-- Logo and Brand -->
        <div class="nav-brand">
            <img src="sslogo.png" alt="SS Group Logo" class="nav-logo">
        </div>

        <!-- Real-Time Clock & Dynamic Greeting Widget -->
        <div class="header-clock-widget" id="header-clock-widget">
            <i class="fa-solid fa-cloud-sun" id="greeting-icon"></i>
            <span id="dynamic-greeting" class="dynamic-greeting">Merhaba</span>,
            <span id="header-realtime-clock" class="realtime-clock">00:00:00</span>
            <span id="header-realtime-date" class="realtime-date">14 Haziran 2026</span>
        </div>

        <!-- Right Utilities (Theme, User) -->
        <div class="nav-user">
            <!-- Theme Toggle -->
            <button type="button" id="theme-toggle" class="theme-toggle-btn" title="Tema Değiştir">
                <i class="fa-solid fa-moon"></i>
            </button>

            <!-- Enhanced User profile dropdown -->
            <div class="user-profile-menu-container" id="user-profile-menu-container">
                <div class="user-profile" id="user-profile-trigger">
                    <div class="profile-avatar-wrapper">
                        <div class="profile-initials-badge"><?php echo $userInitials; ?></div>
                        <span class="profile-online-dot"></span>
                    </div>
                    <div class="user-meta">
                        <span class="user-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
                        <span class="user-dept"><?php echo htmlspecialchars($currentUser['department'] . ' @ ' . $currentUser['company']); ?></span>
                    </div>
                    <i class="fa-solid fa-chevron-down profile-arrow"></i>
                </div>
                <!-- User Profile Dropdown Menu -->
                <div class="user-dropdown-menu" id="user-dropdown-menu">
                    <div class="user-dropdown-header">
                        <div class="user-dropdown-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></div>
                    </div>
                    <ul class="user-dropdown-list">
                        <li class="user-dropdown-item" data-target="profile">
                            <i class="fa-solid fa-id-card"></i> Profil Bilgilerim
                        </li>
                        <li class="user-dropdown-item" data-target="salary">
                            <i class="fa-solid fa-receipt"></i> Maaş Detaylarım
                        </li>
                        <li class="user-dropdown-divider"></li>
                        <li class="user-dropdown-item logout" id="logout-dropdown-btn">
                            <i class="fa-solid fa-right-from-bracket"></i> Güvenli Çıkış
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Layout Wrapper -->
    <div class="app-layout">
        
        <!-- Left Sidebar Navigation -->
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li class="sidebar-category">Ana Menü</li>
                <li class="menu-item active" data-target="home">
                    <button type="button"><i class="fa-solid fa-house"></i> <span>Ana Sayfa</span></button>
                </li>
                <li class="menu-item" data-target="puantaj">
                    <button type="button"><i class="fa-solid fa-calendar-days"></i> <span>Puantajlarım</span></button>
                </li>
                <li class="menu-item" data-target="salary">
                    <button type="button"><i class="fa-solid fa-wallet"></i> <span>Maaş Geçmişim</span></button>
                </li>
                <li class="sidebar-category" style="margin-top: 16px;">Ayarlar</li>
                <li class="menu-item" data-target="profile">
                    <button type="button"><i class="fa-solid fa-user-gear"></i> <span>Profilim</span></button>
                </li>
            </ul>

            <!-- Sleek Interactive User Widget Card -->
            <div class="sidebar-widget">
                <span class="widget-title">Aktif Kullanıcı</span>
                <div class="widget-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></div>
                <div class="widget-status">
                    <span class="widget-status-dot"></span>
                    <span>Sistem Aktif</span>
                </div>
            </div>

            <div class="sidebar-footer">
                <span style="font-size: 11px; color: var(--text-sidebar-muted);">SS Group © 2026 v1.0.0</span>
            </div>
        </aside>

        <!-- Right Content Panels -->
        <main class="content-area">
            
            <!-- Alert Messages (Dynamic redirection results) -->
            <?php 
            $tab_success = isset($_GET['success']) ? trim($_GET['success']) : '';
            $tab_error = isset($_GET['error']) ? trim($_GET['error']) : '';
            ?>
            <?php if ($tab_success === 'info'): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Kişisel bilgileriniz başarıyla güncellendi.</span>
                </div>
            <?php elseif ($tab_success === 'password'): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-key"></i>
                    <span>Şifreniz başarıyla güncellendi.</span>
                </div>
            <?php elseif ($tab_error === 'empty_fields'): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Lütfen e-posta alanını boş bırakmayın.</span>
                </div>
            <?php elseif ($tab_error === 'pass_empty'): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Lütfen tüm şifre alanlarını doldurun.</span>
                </div>
            <?php elseif ($tab_error === 'pass_mismatch'): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <span>Girdiğiniz yeni şifreler birbiriyle uyuşmuyor.</span>
                </div>
            <?php elseif ($tab_error === 'pass_wrong'): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <span>Mevcut şifreniz hatalı.</span>
                </div>
            <?php endif; ?>

            <!-- -----------------------------------------------------------
                 SEKME 1: ANA SAYFA PANELİ
                 ----------------------------------------------------------- -->
            <section id="panel-home" class="tab-panel active">
                <div class="section-header">
                    <h1 class="section-title">Hoş Geldiniz, <?php echo htmlspecialchars(explode(' ', $currentUser['full_name'])[0]); ?></h1>
                    <p class="section-subtitle">Bu ayki çalışma durumu özetinizi ve son girilen puantaj kayıtlarınızı aşağıdan görebilirsiniz.</p>
                </div>

                <!-- Top Stats Counters -->
                <div class="stats-grid" style="margin-top: 25px;">
                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="stat-label">Bu Ay Çalışılan Gün</span>
                            <span class="stat-value"><?php echo $total_worked_days; ?> <span style="font-size:14px; font-weight:500; color:var(--text-secondary);">Gün</span></span>
                        </div>
                        <div class="stat-icon icon-blue">
                            <i class="fa-solid fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="stat-label">Toplam Çalışma Saati</span>
                            <span class="stat-value"><?php echo $total_worked_hours; ?> <span style="font-size:14px; font-weight:500; color:var(--text-secondary);">Saat</span></span>
                        </div>
                        <div class="stat-icon icon-green">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="stat-label">Bu Ay Fazla Mesai</span>
                            <span class="stat-value"><?php echo $total_overtime_hours > 0 ? '+' . $total_overtime_hours : '0'; ?> <span style="font-size:14px; font-weight:500; color:var(--text-secondary);">Saat</span></span>
                        </div>
                        <div class="stat-icon icon-warning">
                            <i class="fa-solid fa-fire"></i>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- Son Giriş-Çıkış & İzin Bilgileri Kolonu -->
                    <div style="display:flex; flex-direction:column; gap:20px;">
                        
                        <!-- Son Girilen Puantajlarım -->
                        <div class="dashboard-card">
                            <h2 class="card-title"><i class="fa-solid fa-calendar-check"></i> Son Girilen Puantajlarım</h2>
                            <div class="announcement-list">
                                <?php 
                                // Son 3 kaydı göster
                                $recent_records = array_slice($records, 0, 3);
                                if (empty($recent_records)): 
                                ?>
                                    <p style="color: var(--text-secondary); text-align: center; padding: 20px;">Son günlere ait kayıt bulunamadı.</p>
                                <?php else: ?>
                                    <?php foreach ($recent_records as $rec): 
                                        $calc = calculatePuantaj($rec['check_in'], $rec['check_out'], $rec['status']);
                                        
                                        // Vardiya zaman hesaplamaları
                                        $rec_start_ts = strtotime($rec['record_date'] . ' ' . $rec['check_in']);
                                        if (strtotime($rec['check_out']) < strtotime($rec['check_in'])) {
                                            $rec_end_ts = strtotime($rec['record_date'] . ' ' . $rec['check_out'] . ' +1 day');
                                        } else {
                                            $rec_end_ts = strtotime($rec['record_date'] . ' ' . $rec['check_out']);
                                        }
                                        $rec_elapsed_hours = round(($rec_end_ts - $rec_start_ts) / 3600, 1);
                                        $rec_work_hours = min(8, $rec_elapsed_hours);
                                        $rec_overtime_hours = max(0, $rec_elapsed_hours - 8);
                                        
                                        $badgeClass = 'badge-success';
                                        $statusLabel = 'Normal Çalışma';
                                        if ($rec['status'] === 'Izinli') {
                                            $badgeClass = 'badge-warning';
                                            $statusLabel = $rec['leave_type'] ?? 'İzinli';
                                        } elseif ($rec['status'] === 'Devamsiz') {
                                            $badgeClass = 'badge-danger';
                                            $statusLabel = 'Devamsız';
                                        }
                                    ?>
                                        <div class="announcement-item clickable-shift-row" 
                                             data-date="<?php echo date('d.m.Y', strtotime($rec['record_date'])); ?>"
                                             data-person="<?php echo htmlspecialchars($rec['full_name']); ?>"
                                             data-start="<?php echo date('H:i', strtotime($rec['check_in'])); ?>"
                                             data-end="<?php echo date('H:i', strtotime($rec['check_out'])); ?>"
                                             data-work-hours="<?php echo $rec_work_hours; ?>"
                                             data-overtime-hours="<?php echo $rec_overtime_hours; ?>"
                                             data-daily-wage="<?php echo $daily_wage; ?>"
                                             data-extra-pay="<?php echo $extra_pay; ?>"
                                             style="display:flex; justify-content:space-between; align-items:center; border-left:3px solid <?php echo $rec['status'] === 'Normal' ? 'var(--success)' : ($rec['status'] === 'Izinli' ? 'var(--warning)' : 'var(--danger)'); ?>">
                                            <div style="display:flex; flex-direction:column; gap:4px;">
                                                <span style="font-weight:700; font-size:13.5px; color:var(--text-primary);">
                                                    <?php echo date('d.m.Y', strtotime($rec['record_date'])); ?>
                                                </span>
                                                <span style="font-size:12px; color:var(--text-secondary);">
                                                    <?php if ($rec['status'] === 'Normal'): ?>
                                                        Mesai: <strong><?php echo $calc['worked']; ?> Saat</strong>
                                                        <?php if ($calc['overtime'] > 0): ?>
                                                            | Fazla Mesai: <strong style="color: var(--warning);">+<?php echo $calc['overtime']; ?> Saat</strong>
                                                        <?php endif; ?>
                                                        <br>
                                                        <span style="font-size:11px; color:var(--text-muted);">
                                                            (Giriş: <?php echo date('H:i', strtotime($rec['check_in'])); ?> - Çıkış: <?php echo date('H:i', strtotime($rec['check_out'])); ?>)
                                                        </span>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($rec['description'] ?? '-'); ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div>
                                                <span class="badge <?php echo $badgeClass; ?>">
                                                    <span class="status-dot"></span>
                                                    <?php echo $statusLabel; ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Last Salary Summary Column -->
                    <div class="dashboard-card">
                        <h2 class="card-title"><i class="fa-solid fa-wallet"></i> Son Maaş Özeti</h2>
                        <?php if (!$latestSalary): ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fa-solid fa-wallet"></i></div>
                                <div class="empty-title">Kayıt Bulunamadı</div>
                                <p style="font-size: 13px; color: var(--text-secondary);">Maaş kaydı bulunmamaktadır.</p>
                            </div>
                        <?php else: 
                            $total_earnings = $latestSalary['net_salary'] + $latestSalary['overtime_pay'] + $latestSalary['bonus'];
                            $period_parts = explode('-', $latestSalary['period']);
                            $months_turkish = [
                                '01'=>'Ocak', '02'=>'Şubat', '03'=>'Mart', '04'=>'Nisan', '05'=>'Mayıs', '06'=>'Haziran',
                                '07'=>'Temmuz', '08'=>'Ağustos', '09'=>'Eylül', '10'=>'Ekim', '11'=>'Kasım', '12'=>'Aralık'
                            ];
                            $period_label = $months_turkish[$period_parts[1]] . ' ' . $period_parts[0];
                        ?>
                            <div class="salary-summary-box">
                                <span class="salary-label"><?php echo $period_label; ?> Dönemi Net Hak Ediş</span>
                                <div class="salary-amount"><?php echo number_format($total_earnings, 2, ',', '.'); ?> ₺</div>
                                
                                <div class="salary-breakdown">
                                    <div class="breakdown-row">
                                        <span>Taban Net Maaş:</span>
                                        <span><?php echo number_format($latestSalary['net_salary'], 2, ',', '.'); ?> ₺</span>
                                    </div>
                                    <div class="breakdown-row">
                                        <span>Fazla Mesai Ücreti:</span>
                                        <span>+<?php echo number_format($latestSalary['overtime_pay'], 2, ',', '.'); ?> ₺</span>
                                    </div>
                                    <div class="breakdown-row">
                                        <span>Prim & Ek Ödemeler:</span>
                                        <span>+<?php echo number_format($latestSalary['bonus'], 2, ',', '.'); ?> ₺</span>
                                    </div>
                                    <div class="breakdown-row total">
                                        <span>Toplam Net Yatacak:</span>
                                        <span style="color: var(--success);"><?php echo number_format($total_earnings, 2, ',', '.'); ?> ₺</span>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 20px; text-align: left;">
                                    <span class="salary-label" style="font-size: 10px;">Ödenecek Hesap (IBAN)</span>
                                    <div class="iban-box" style="margin-top: 6px;">
                                        <div class="iban-code"><?php echo htmlspecialchars($currentUser['iban'] ?? 'IBAN Girilmemiş'); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- -----------------------------------------------------------
                 SEKME 2: PUANTAJLARIM PANELİ
                 ----------------------------------------------------------- -->
            <section id="panel-puantaj" class="tab-panel">
                <div class="section-header">
                    <h1 class="section-title">Puantaj Günlük Kayıtları</h1>
                    <p class="section-subtitle">Giriş-çıkış saatlerini, mesaileri ve izin durumlarınızı inceleyebilirsiniz.</p>
                </div>

                <!-- Filters -->
                <div class="filter-card">
                    <form method="GET" action="dashboard.php" class="filter-form" id="tab-puantaj-filter">
                        <input type="hidden" name="tab" value="puantaj">
                        
                        <div class="filter-group">
                            <label for="filter_month" class="filter-label">Ay Seçimi</label>
                            <select id="filter_month" name="filter_month" class="filter-input">
                                <option value="">Tüm Dönemler</option>
                                <option value="2026-06" <?php echo $filter_month === '2026-06' ? 'selected' : ''; ?>>Haziran 2026</option>
                                <option value="2026-05" <?php echo $filter_month === '2026-05' ? 'selected' : ''; ?>>Mayıs 2026</option>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn-filter">
                                <i class="fa-solid fa-filter"></i> Filtrele
                            </button>
                            <a href="dashboard.php?tab=puantaj" class="btn-reset">
                                <i class="fa-solid fa-rotate-left"></i> Sıfırla
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Puantaj Tables Grouped by Month -->
                <?php
                // Group records by month-year
                $grouped_records = [];
                foreach ($records as $row) {
                    $month_key = date('Y-m', strtotime($row['record_date']));
                    $grouped_records[$month_key][] = $row;
                }
                ?>
                <?php if (empty($grouped_records)): ?>
                    <div class="table-card" style="margin-top: 25px;">
                        <div class="table-responsive">
                            <table class="puantaj-table">
                                <tbody>
                                    <tr>
                                        <td style="text-align: center; border-bottom: none;">
                                            <div class="empty-state">
                                                <div class="empty-icon"><i class="fa-solid fa-calendar-day"></i></div>
                                                <div class="empty-title">Kayıt Bulunamadı</div>
                                                <div class="empty-text">Filtre kriterlerinize uyan kayıt bulunmuyor.</div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <?php 
                    $months_turkish = [
                        '01'=>'Ocak', '02'=>'Şubat', '03'=>'Mart', '04'=>'Nisan', '05'=>'Mayıs', '06'=>'Haziran',
                        '07'=>'Temmuz', '08'=>'Ağustos', '09'=>'Eylül', '10'=>'Ekim', '11'=>'Kasım', '12'=>'Aralık'
                    ];
                    foreach ($grouped_records as $month_key => $month_records): 
                        $parts = explode('-', $month_key);
                        $year = $parts[0];
                        $month_num = $parts[1];
                        $month_name = $months_turkish[$month_num] . ' ' . $year;

                        // Aylık İstatistik Hesaplama
                        $m_worked_days = 0;
                        $m_worked_hours = 0;
                        $m_overtime = 0;
                        $m_leaves = 0;
                        $m_absents = 0;

                        foreach ($month_records as $r) {
                            if ($r['status'] === 'Normal') {
                                $m_calc = calculatePuantaj($r['check_in'], $r['check_out'], $r['status']);
                                $m_worked_hours += $m_calc['worked'];
                                $m_overtime += $m_calc['overtime'];
                                $m_worked_days++;
                            } elseif ($r['status'] === 'Izinli') {
                                $m_leaves++;
                            } elseif ($r['status'] === 'Devamsiz') {
                                $m_absents++;
                            }
                        }
                    ?>
                        <div style="margin-top: 25px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                            <div style="width: 4px; height: 18px; background-color: var(--primary); border-radius: 2px;"></div>
                            <h3 style="font-size: 15px; font-weight: 700; color: var(--text-primary); margin: 0;"><?php echo $month_name; ?></h3>
                        </div>
                        <div class="table-card" style="margin-top: 10px;">
                            <div class="table-responsive">
                                <table class="puantaj-table">
                                    <thead>
                                        <tr>
                                            <th>Personel</th>
                                            <th>Başlama</th>
                                            <th>Çıkış</th>
                                            <th>Gün</th>
                                            <th>Çalışma Saati</th>
                                            <th>Mesai Saati</th>
                                            <th>Günlük Ücret</th>
                                            <th>Mesai Hakediş</th>
                                            <th>Ekstra Ücret</th>
                                            <th>Toplam Tutar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $month_days_sum = 0;
                                        $month_work_hours_sum = 0;
                                        $month_overtime_hours_sum = 0;
                                        $month_overtime_pay_sum = 0;
                                        $month_extra_pay_sum = 0;
                                        $month_total_pay_sum = 0;
                                        $month_worked_shifts_count = 0;

                                        $daily_wage = round($currentUser['salary'] / 30, 2);
                                        $hourly_rate = $daily_wage / 8;
                                        $overtime_rate = $hourly_rate * 1.5;
                                        $extra_pay = 200.00;

                                        foreach ($month_records as $row): 
                                            $start_ts = strtotime($row['record_date'] . ' ' . $row['check_in']);
                                            if (strtotime($row['check_out']) < strtotime($row['check_in'])) {
                                                $end_ts = strtotime($row['record_date'] . ' ' . $row['check_out'] . ' +1 day');
                                                $day_count = 2;
                                            } else {
                                                $end_ts = strtotime($row['record_date'] . ' ' . $row['check_out']);
                                                $day_count = 1;
                                            }

                                            $start_formatted = date('d.m.Y H:i', $start_ts);
                                            $end_formatted = date('d.m.Y H:i', $end_ts);
                                            
                                            $elapsed_hours = round(($end_ts - $start_ts) / 3600, 1);
                                            $work_hours = min(8, $elapsed_hours);
                                            $overtime_hours = max(0, $elapsed_hours - 8);
                                            
                                            $overtime_pay = round($overtime_hours * $overtime_rate, 2);
                                            $total_pay = $daily_wage + $overtime_pay + $extra_pay;
                                            
                                            $month_days_sum += $day_count;
                                            $month_work_hours_sum += $work_hours;
                                            $month_overtime_hours_sum += $overtime_hours;
                                            $month_overtime_pay_sum += $overtime_pay;
                                            $month_extra_pay_sum += $extra_pay;
                                            $month_total_pay_sum += $total_pay;
                                            $month_worked_shifts_count++;
                                        ?>
                                            <tr class="clickable-shift-row"
                                                data-date="<?php echo date('d.m.Y', strtotime($row['record_date'])); ?>"
                                                data-person="<?php echo htmlspecialchars($row['full_name']); ?>"
                                                data-start="<?php echo date('H:i', strtotime($row['check_in'])); ?>"
                                                data-end="<?php echo date('H:i', strtotime($row['check_out'])); ?>"
                                                data-work-hours="<?php echo $work_hours; ?>"
                                                data-overtime-hours="<?php echo $overtime_hours; ?>"
                                                data-daily-wage="<?php echo $daily_wage; ?>"
                                                data-extra-pay="<?php echo $extra_pay; ?>">
                                                <td data-label="Personel" class="hide-on-mobile">
                                                    <span style="font-weight:600;"><?php echo htmlspecialchars($row['full_name']); ?></span>
                                                </td>
                                                <td data-label="Başlama" class="cell-date">
                                                    <?php echo $start_formatted; ?>
                                                </td>
                                                <td data-label="Çıkış" class="cell-time">
                                                    <?php echo $end_formatted; ?>
                                                </td>
                                                <td data-label="Gün" class="hide-on-mobile">
                                                    <?php echo $day_count; ?>
                                                </td>
                                                <td data-label="Çalışma Saati" class="hide-on-mobile">
                                                    <?php echo $work_hours; ?>
                                                </td>
                                                <td data-label="Mesai Saati">
                                                    <?php echo $overtime_hours; ?>
                                                </td>
                                                <td data-label="Günlük Ücret" class="hide-on-mobile">
                                                    ₺<?php echo number_format($daily_wage, 2, ',', '.'); ?>
                                                </td>
                                                <td data-label="Mesai Hakediş">
                                                    ₺<?php echo number_format($overtime_pay, 2, ',', '.'); ?>
                                                </td>
                                                <td data-label="Ekstra Ücret" class="hide-on-mobile">
                                                    ₺<?php echo number_format($extra_pay, 2, ',', '.'); ?>
                                                </td>
                                                <td data-label="Toplam Tutar" style="color: var(--success); font-weight: 700;">
                                                    ₺<?php echo number_format($total_pay, 2, ',', '.'); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="summary-row" style="background-color: var(--bg-surface-hover); font-weight: 700; border-top: 2px solid var(--border-color); border-bottom: 2px solid var(--border-color);">
                                            <td colspan="3" style="text-align: right; padding: 12px 16px;">Dönem Özeti</td>
                                            <td data-label="Toplam Gün" style="padding: 12px 16px;"><?php echo $month_days_sum; ?></td>
                                            <td data-label="Toplam Çalışma" style="padding: 12px 16px;"><?php echo $month_work_hours_sum; ?></td>
                                            <td data-label="Toplam Mesai" style="padding: 12px 16px;"><?php echo $month_overtime_hours_sum; ?></td>
                                            <td style="padding: 12px 16px;">-</td>
                                            <td data-label="Toplam Mesai Hakediş" style="padding: 12px 16px;">₺<?php echo number_format($month_overtime_pay_sum, 2, ',', '.'); ?></td>
                                            <td data-label="Toplam Ekstra" style="padding: 12px 16px;">₺<?php echo number_format($month_extra_pay_sum, 2, ',', '.'); ?></td>
                                            <td data-label="Genel Toplam" style="padding: 12px 16px; color: var(--success); font-weight: 800;">₺<?php echo number_format($month_total_pay_sum, 2, ',', '.'); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        
                        <?php 
                        $month_normal_hakedis_sum = $month_worked_shifts_count * $daily_wage;
                        ?>
                        <div class="summary-pill-container" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px; flex-wrap: wrap; margin-bottom: 30px;">
                            <div class="summary-pill-item" style="background: var(--bg-surface); border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 12px; font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; display: flex; align-items: center; gap: 8px;">
                                ÇALIŞMA GÜNLERİ <span style="color: var(--primary); font-size: 14px; font-weight: 700;"><?php echo $month_days_sum; ?></span>
                            </div>
                            <div class="summary-pill-item" style="background: var(--bg-surface); border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 12px; font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; display: flex; align-items: center; gap: 8px;">
                                NORMAL HAKEDİŞ <span style="color: var(--success); font-size: 14px; font-weight: 700;">₺<?php echo number_format($month_normal_hakedis_sum, 2, ',', '.'); ?></span>
                            </div>
                            <div class="summary-pill-item" style="background: var(--bg-surface); border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 12px; font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; display: flex; align-items: center; gap: 8px;">
                                MESAİ ÜCRETİ <span style="color: var(--warning); font-size: 14px; font-weight: 700;">₺<?php echo number_format($month_overtime_pay_sum, 2, ',', '.'); ?></span>
                            </div>
                            <div class="summary-pill-item" style="background: var(--bg-surface); border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 12px; font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; display: flex; align-items: center; gap: 8px;">
                                EKSTRA <span style="color: var(--info); font-size: 14px; font-weight: 700;">₺<?php echo number_format($month_extra_pay_sum, 2, ',', '.'); ?></span>
                            </div>
                            <div class="summary-pill-item" style="background: var(--bg-surface); border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 12px; font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; display: flex; align-items: center; gap: 8px;">
                                GENEL TOPLAM <span style="color: var(--success); font-size: 14px; font-weight: 800;">₺<?php echo number_format($month_total_pay_sum, 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <!-- -----------------------------------------------------------
                 SEKME 3: MAAŞ & BORDROLAR PANELİ
                 ----------------------------------------------------------- -->
            <section id="panel-salary" class="tab-panel">
                <div class="section-header">
                    <h1 class="section-title">Maaş Geçmişi</h1>
                    <p class="section-subtitle">Geçmiş dönem net hak edişlerinizi, mesai ve ek ödeme detaylarınızı inceleyebilirsiniz.</p>
                </div>

                <div class="table-card" style="margin-top: 25px;">
                    <div class="table-responsive">
                        <table class="puantaj-table salary-history-table">
                            <thead>
                                <tr>
                                    <th>Dönem</th>
                                    <th>Net Taban Maaş</th>
                                    <th>Fazla Mesai Ücreti</th>
                                    <th>Prim & Ek Ödeme</th>
                                    <th>Toplam Ödenen Net Maaş</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($salaryHistory)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">Maaş geçmişi bulunamadı.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($salaryHistory as $sal): 
                                        $total = $sal['net_salary'] + $sal['overtime_pay'] + $sal['bonus'];
                                        $period_parts = explode('-', $sal['period']);
                                        $months_turkish = [
                                            '01'=>'Ocak', '02'=>'Şubat', '03'=>'Mart', '04'=>'Nisan', '05'=>'Mayıs', '06'=>'Haziran',
                                            '07'=>'Temmuz', '08'=>'Ağustos', '09'=>'Eylül', '10'=>'Ekim', '11'=>'Kasım', '12'=>'Aralık'
                                        ];
                                        $period_name = $months_turkish[$period_parts[1]] . ' ' . $period_parts[0];
                                    ?>
                                        <tr>
                                            <td data-label="Dönem" style="font-weight: 700; font-family: var(--font-heading);">
                                                <?php echo $period_name; ?>
                                            </td>
                                            <td data-label="Net Taban Maaş">
                                                <?php echo number_format($sal['net_salary'], 2, ',', '.'); ?> ₺
                                            </td>
                                            <td data-label="Fazla Mesai Ücreti" style="color: var(--warning); font-weight: 500;">
                                                +<?php echo number_format($sal['overtime_pay'], 2, ',', '.'); ?> ₺
                                            </td>
                                            <td data-label="Prim & Ek Ödeme" style="color: var(--info); font-weight: 500;">
                                                +<?php echo number_format($sal['bonus'], 2, ',', '.'); ?> ₺
                                            </td>
                                            <td data-label="Toplam Ödenen Net Maaş" style="font-weight: 700; color: var(--success);">
                                                <?php echo number_format($total, 2, ',', '.'); ?> ₺
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ===========================================================
                 SEKME 4: PROFİLİM
                 =========================================================== -->
            <section id="panel-profile" class="tab-panel">
                <div class="section-header">
                    <h1 class="section-title">Çalışan Profil Portalı & Ayarlar</h1>
                    <p class="section-subtitle">Kişisel bilgilerinizi inceleyebilir, iletişim, IBAN ve güvenlik tercihlerinizi kolayca yönetebilirsiniz.</p>
                </div>

                <div class="profile-layout">
                    <!-- Sol Sütun: Çalışan Kimlik Kartı -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        
                        <!-- Profil Kimlik Kartı (Yenilikçi Tasarım) -->
                        <div class="dashboard-card" style="text-align: center; position: relative; overflow: hidden; padding: 35px 20px; border-radius: 20px; background: linear-gradient(135deg, rgba(30, 41, 59, 0.7) 0%, rgba(15, 23, 42, 0.9) 100%); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);">
                            <!-- Mesh Gradient Background Effect -->
                            <div style="position: absolute; top: -50px; left: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(59, 130, 246, 0) 70%); pointer-events: none; z-index: 1;"></div>
                            <div style="position: absolute; bottom: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(16, 185, 129, 0.12) 0%, rgba(16, 185, 129, 0) 70%); pointer-events: none; z-index: 1;"></div>
                            
                            <div style="position: relative; z-index: 2;">
                                <!-- Avatar Wrapper with Gradient Ring -->
                                <div style="width: 108px; height: 108px; margin: 0 auto 20px auto; position: relative; padding: 4px; background: linear-gradient(135deg, var(--primary) 0%, #10b981 100%); border-radius: 50%; box-shadow: 0 8px 24px rgba(37, 99, 235, 0.25);">
                                    <div style="font-size: 34px; font-weight: 800; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--bg-surface); color: var(--primary); border-radius: 50%;"><?php echo $userInitials; ?></div>
                                    <span class="profile-online-dot" style="width: 16px; height: 16px; border: 3px solid var(--bg-surface); right: 2px; bottom: 2px; box-shadow: 0 0 8px var(--success);"></span>
                                </div>

                                <h2 style="font-size: 20px; font-weight: 800; color: #ffffff; margin-bottom: 8px; letter-spacing: -0.02em;"><?php echo htmlspecialchars($currentUser['full_name']); ?></h2>
                                
                                <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); color: var(--text-sidebar-secondary); font-size: 11px; font-weight: 700; padding: 5px 16px; border-radius: 99px; margin-top: 5px; box-shadow: var(--shadow-sm);">
                                    <i class="fa-solid fa-building" style="font-size: 10px; color: var(--primary);"></i>
                                    <span><?php echo htmlspecialchars($currentUser['company']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sağ Sütun: Tüm Ayarlar Kartları (Hep Beraber Görünür) -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        
                        <!-- Yeni Bölüm: Çalışan Bilgilerim (Kartı) -->
                        <div class="dashboard-card" style="margin-bottom: 0;">
                            <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 20px;">
                                <div style="width: 42px; height: 42px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 18px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);">
                                    <i class="fa-solid fa-address-card"></i>
                                </div>
                                <div>
                                    <h3 style="font-size: 16px; font-weight: 750; color: var(--text-primary); margin: 0; border: none; padding: 0;">Çalışan Bilgilerim</h3>
                                    <p style="font-size: 12px; color: var(--text-muted); margin: 3px 0 0 0;">Günlük kazanç, mesai tutarı ve çalışma saati bilgileriniz aşağıdadır.</p>
                                </div>
                            </div>
                            
                            <div class="profile-stats-grid">
                                <!-- Kart 1: Günlük Ücret -->
                                <div style="text-align: center; border: 1px solid var(--border-color); background: rgba(255,255,255,0.01); border-radius: 12px; padding: 16px; transition: var(--transition);">
                                    <div style="width: 44px; height: 44px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 18px; margin: 0 auto 10px auto; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);">
                                        <i class="fa-solid fa-money-bill-wave"></i>
                                    </div>
                                    <div style="font-size: 12px; font-weight: 650; color: var(--text-muted); margin-bottom: 4px;">Günlük Ücret</div>
                                    <div style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">₺600,00</div>
                                    <div style="font-size: 11px; color: var(--text-muted);">Günlük kazancınız</div>
                                </div>
                                
                                <!-- Kart 2: Mesai Ücreti -->
                                <div style="text-align: center; border: 1px solid var(--border-color); background: rgba(255,255,255,0.01); border-radius: 12px; padding: 16px; transition: var(--transition);">
                                    <div style="width: 44px; height: 44px; background: #3b82f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 18px; margin: 0 auto 10px auto; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);">
                                        <i class="fa-solid fa-clock"></i>
                                    </div>
                                    <div style="font-size: 12px; font-weight: 650; color: var(--text-muted); margin-bottom: 4px;">Mesai Ücreti</div>
                                    <div style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">₺100,00</div>
                                    <div style="font-size: 11px; color: var(--text-muted);">Saatlik fazla mesai kazancı</div>
                                </div>

                                <!-- Kart 3: Günlük Çalışma Saati -->
                                <div style="text-align: center; border: 1px solid var(--border-color); background: rgba(255,255,255,0.01); border-radius: 12px; padding: 16px; transition: var(--transition);">
                                    <div style="width: 44px; height: 44px; background: #f97316; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 18px; margin: 0 auto 10px auto; box-shadow: 0 4px 10px rgba(249, 115, 22, 0.25);">
                                        <i class="fa-solid fa-hourglass-half"></i>
                                    </div>
                                    <div style="font-size: 12px; font-weight: 650; color: var(--text-muted); margin-bottom: 4px;">Günlük Çalışma Saati</div>
                                    <div style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">4 h</div>
                                    <div style="font-size: 11px; color: var(--text-muted);">Normal çalışma süresi</div>
                                </div>
                            </div>
                        </div>

                        <!-- Kart 1: Temel Çalışan Bilgileri -->
                        <div class="dashboard-card" style="margin-bottom: 0;">
                            <h3 class="card-title" style="margin-bottom: 15px;"><i class="fa-solid fa-user-tag" style="color: var(--primary);"></i> Temel Çalışan Bilgileri</h3>
                            <form action="update_profile.php" method="POST" class="grid-form profile-basic-form">
                                <input type="hidden" name="action" value="update_info">
                                <input type="hidden" name="iban" value="<?php echo htmlspecialchars($currentUser['iban'] ?? ''); ?>">
                                <input type="hidden" name="address" value="<?php echo htmlspecialchars($currentUser['address'] ?? ''); ?>">

                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-size: 11px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; display: block;">Ad Soyad</label>
                                    <input type="text" class="form-input" style="padding-left:14px; padding-top: 9px; padding-bottom: 9px; background: rgba(255,255,255,0.02); color: var(--text-muted); cursor: not-allowed;" value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" disabled>
                                </div>

                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-size: 11px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; display: block;">Firma / İşveren</label>
                                    <input type="text" class="form-input" style="padding-left:14px; padding-top: 9px; padding-bottom: 9px; background: rgba(255,255,255,0.02); color: var(--text-muted); cursor: not-allowed;" value="<?php echo htmlspecialchars($currentUser['company']); ?>" disabled>
                                </div>

                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="profile_phone" class="form-label" style="font-size: 11px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; display: block;">Telefon Numarası</label>
                                    <div style="position: relative;">
                                        <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 12px;"><i class="fa-solid fa-phone"></i></span>
                                        <input type="text" id="profile_phone" name="phone" class="form-input" style="padding-left:36px; padding-top: 9px; padding-bottom: 9px;" placeholder="+90 (555) 000 00 00" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div style="grid-column: span 3; text-align: right; margin-top: 5px;">
                                    <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 13px;">
                                        <i class="fa-solid fa-floppy-disk"></i> Bilgileri Güncelle
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- İki sütunlu alt sıra -->
                        <div class="profile-bottom-grid">
                            <!-- Kart 2: Banka & IBAN Ayarları -->
                            <div class="dashboard-card" style="margin-bottom: 0; height: 100%;">
                                <h3 class="card-title" style="margin-bottom: 15px;"><i class="fa-solid fa-building-columns" style="color: var(--primary);"></i> Banka Bilgileri</h3>
                                <form action="update_profile.php" method="POST" class="grid-form" style="display: flex; flex-direction: column; gap: 12px;">
                                    <input type="hidden" name="action" value="update_info">
                                    <input type="hidden" name="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                                    <input type="hidden" name="address" value="<?php echo htmlspecialchars($currentUser['address'] ?? ''); ?>">

                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label for="profile_iban" class="form-label" style="font-size: 11px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; display: block;">IBAN Numarası</label>
                                        <div class="iban-box" style="display: flex; align-items: center; border: 1px solid var(--border-color); border-radius: 12px; background: var(--bg-surface-hover); padding-right: 6px; overflow: hidden; transition: all 0.3s ease;">
                                            <span style="padding-left: 12px; color: var(--text-muted); font-weight: 600; font-size: 13px;">TR</span>
                                            <input type="text" id="profile_iban" name="iban" class="form-input" style="border:none; background:none; flex: 1; padding-left: 6px; font-family: monospace; font-size: 13px; letter-spacing: 0.5px; padding-top: 9px; padding-bottom: 9px;" placeholder="24 haneli IBAN girin..." value="<?php echo htmlspecialchars(substr($currentUser['iban'] ?? '', 2)); ?>">
                                            <button type="button" class="iban-btn-copy" onclick="copyIban()" title="Panoya Kopyala" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 6px; transition: color 0.2s ease;">
                                                <i class="fa-solid fa-copy" style="font-size: 12px;"></i>
                                            </button>
                                        </div>
                                        <span id="bank-detector-text" style="display: inline-block; font-size: 10px; margin-top: 4px; font-weight: 600; color: var(--success); min-height: 14px;"></span>
                                    </div>

                                    <div style="background: rgba(37, 99, 235, 0.03); border: 1px solid rgba(37, 99, 235, 0.1); border-radius: 12px; padding: 10px; display: flex; gap: 8px; align-items: flex-start;">
                                        <i class="fa-solid fa-circle-info" style="color: var(--primary); font-size: 13px; margin-top: 1px;"></i>
                                        <div style="font-size: 11px; color: var(--text-secondary); line-height: 1.4;">
                                            IBAN hesabınızın adınıza kayıtlı olduğundan emin olun. Maaş ödemeleriniz bu hesaba aktarılacaktır.
                                        </div>
                                    </div>

                                    <div style="text-align: right; margin-top: 5px;">
                                        <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 13px;">
                                            <i class="fa-solid fa-floppy-disk"></i> IBAN Kaydet
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Kart 3: Şifre & Güvenlik -->
                            <div class="dashboard-card" style="margin-bottom: 0; height: 100%;">
                                <h3 class="card-title" style="margin-bottom: 15px;"><i class="fa-solid fa-key" style="color: var(--primary);"></i> Şifre Yenileme</h3>
                                <form action="update_profile.php" method="POST" class="grid-form" style="display: flex; flex-direction: column; gap: 12px;">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label for="current_password" class="form-label" style="font-size: 11px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; display: block;">Mevcut Şifre</label>
                                        <div style="position: relative;">
                                            <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 12px;"><i class="fa-solid fa-lock"></i></span>
                                            <input type="password" id="current_password" name="current_password" class="form-input" style="padding-left: 36px; padding-top: 9px; padding-bottom: 9px;" required autocomplete="current-password" placeholder="Mevcut şifreniz">
                                        </div>
                                    </div>

                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label for="new_password" class="form-label" style="font-size: 11px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; display: block;">Yeni Şifre</label>
                                        <div style="position: relative;">
                                            <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 12px;"><i class="fa-solid fa-key"></i></span>
                                            <input type="password" id="new_password" name="new_password" class="form-input" style="padding-left: 36px; padding-top: 9px; padding-bottom: 9px;" required autocomplete="new-password" placeholder="En az 6 karakter" onkeyup="checkPasswordStrength(this.value)">
                                        </div>
                                        <div style="margin-top: 5px;">
                                            <div style="display: flex; height: 3px; gap: 3px; background: rgba(255,255,255,0.05); border-radius: 2px; overflow: hidden;">
                                                <div id="strength-bar-1" style="flex: 1; height: 100%; transition: background-color 0.3s ease;"></div>
                                                <div id="strength-bar-2" style="flex: 1; height: 100%; transition: background-color 0.3s ease;"></div>
                                                <div id="strength-bar-3" style="flex: 1; height: 100%; transition: background-color 0.3s ease;"></div>
                                            </div>
                                            <span id="strength-text" style="font-size: 9px; font-weight: 600; margin-top: 3px; display: block; color: var(--text-muted); min-height: 11px;">Şifre gücü analizi...</span>
                                        </div>
                                    </div>

                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label for="new_password_confirm" class="form-label" style="font-size: 11px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; display: block;">Yeni Şifre Tekrar</label>
                                        <div style="position: relative;">
                                            <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 12px;"><i class="fa-solid fa-key"></i></span>
                                            <input type="password" id="new_password_confirm" name="new_password_confirm" class="form-input" style="padding-left: 36px; padding-top: 9px; padding-bottom: 9px;" required autocomplete="new-password" placeholder="Onaylayın">
                                        </div>
                                    </div>

                                    <div style="text-align: right; margin-top: 5px;">
                                        <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 13px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);">
                                            <i class="fa-solid fa-shield-check"></i> Şifre Güncelle
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        </main>
    </div>

    <!-- Interactive Client Scripts -->
    <script>
        // SPA Sekme Yönetimi (Tab controller)
        document.addEventListener('DOMContentLoaded', () => {
            const menuItems = document.querySelectorAll('.sidebar-menu .menu-item');
            const tabPanels = document.querySelectorAll('.tab-panel');

            // URL Parametresine Göre Aktif Sekmeyi Belirle
            const getQueryParam = (name) => {
                const urlParams = new URLSearchParams(window.location.search);
                return urlParams.get(name);
            };

            const activateTab = (tabId) => {
                if (!tabId) return;
                
                // Menü öğesini güncelle
                menuItems.forEach(item => {
                    if (item.getAttribute('data-target') === tabId) {
                        item.classList.add('active');
                    } else {
                        item.classList.remove('active');
                    }
                });

                // Panelleri güncelle
                tabPanels.forEach(panel => {
                    if (panel.id === `panel-${tabId}`) {
                        panel.classList.add('active');
                    } else {
                        panel.classList.remove('active');
                    }
                });
            };

            // Sayfa ilk yüklendiğinde aktif sekmeyi seç
            let activeTab = getQueryParam('tab') || 'home';
            activateTab(activeTab);

            // Tıklamalarda tab değiştir
            menuItems.forEach(item => {
                item.addEventListener('click', () => {
                    const targetTab = item.getAttribute('data-target');
                    
                    // URL parametresini güncelle (Sayfa yenilenmeden)
                    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + targetTab;
                    window.history.pushState({ path: newUrl }, '', newUrl);
                    
                    activateTab(targetTab);
                });
            });

            // ----------------------------------------------------
            // PREMIUM DYNAMIC REAL-TIME CLOCK & TURKISH GREETING
            // ----------------------------------------------------
            const clockEl = document.getElementById('header-realtime-clock');
            const dateEl = document.getElementById('header-realtime-date');
            const greetingEl = document.getElementById('dynamic-greeting');
            const greetingIcon = document.getElementById('greeting-icon');

            const months = ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];
            const days = ["Pazar", "Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi"];

            function updateTime() {
                const now = new Date();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');
                
                // Clock element with blinking colon
                if (clockEl) {
                    clockEl.innerHTML = `${hours}<span class="colon-blink">:</span>${minutes}<span class="colon-blink">:</span>${seconds}`;
                }

                // Date element
                if (dateEl) {
                    const day = now.getDate();
                    const month = months[now.getMonth()];
                    const year = now.getFullYear();
                    const dayName = days[now.getDay()];
                    dateEl.innerText = `${day} ${month} ${year}, ${dayName}`;
                }

                // Greeting logic based on Turkish standards
                const hour = now.getHours();
                let greeting = "Merhaba";
                let iconClass = "fa-cloud-sun";

                if (hour >= 5 && hour < 12) {
                    greeting = "Günaydın";
                    iconClass = "fa-cloud-sun";
                } else if (hour >= 12 && hour < 18) {
                    greeting = "İyi Günler";
                    iconClass = "fa-sun";
                } else if (hour >= 18 && hour < 22) {
                    greeting = "İyi Akşamlar";
                    iconClass = "fa-cloud-moon";
                } else {
                    greeting = "İyi Geceler";
                    iconClass = "fa-star-and-crescent";
                }

                if (greetingEl) greetingEl.innerText = greeting;
                if (greetingIcon) {
                    greetingIcon.className = `fa-solid ${iconClass}`;
                }
            }

            // Start clock
            updateTime();
            setInterval(updateTime, 1000);

            // ----------------------------------------------------
            // USER DROPDOWN CONTROLLER
            // ----------------------------------------------------
            const userTrigger = document.getElementById('user-profile-trigger');
            const userMenuContainer = document.getElementById('user-profile-menu-container');
            const userMenu = document.getElementById('user-dropdown-menu');

            if (userTrigger && userMenu) {
                userTrigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userMenu.classList.toggle('open');
                    userMenuContainer.classList.toggle('active');
                });
            }

            // Dropdown items navigation
            document.querySelectorAll('.user-dropdown-item').forEach(item => {
                item.addEventListener('click', () => {
                    const target = item.getAttribute('data-target');
                    if (target) {
                        activateTab(target);
                        const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + target;
                        window.history.pushState({ path: newUrl }, '', newUrl);
                    }
                    if (userMenu) {
                        userMenu.classList.remove('open');
                        userMenuContainer.classList.remove('active');
                    }
                });
            });

            // Logout click
            const logoutDropdownBtn = document.getElementById('logout-dropdown-btn');
            if (logoutDropdownBtn) {
                logoutDropdownBtn.addEventListener('click', () => {
                    window.location.href = 'logout.php';
                });
            }

            // Click outside helper to close dropdowns
            document.addEventListener('click', (e) => {
                if (userMenu && !userMenuContainer.contains(e.target)) {
                    userMenu.classList.remove('open');
                    userMenuContainer.classList.remove('active');
                }
            });
        });



        // IBAN Kopyalama Fonksiyonu (TR ön eki eklenerek)
        function copyIban() {
            const ibanInput = document.getElementById('profile_iban');
            const cleanVal = ibanInput.value.replace(/\s+/g, '');
            const fullIban = cleanVal.substring(0, 2) === 'TR' ? cleanVal : 'TR' + cleanVal;
            
            navigator.clipboard.writeText(fullIban).then(() => {
                const copyBtn = document.querySelector('.iban-btn-copy');
                const originalIcon = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#10b981;"></i>';
                
                setTimeout(() => {
                    copyBtn.innerHTML = originalIcon;
                }, 1500);
            });
        }

        // ----------------------------------------------------
        // PROFILE SUBTAB & BANK DETECTOR & PASSWORD STRENGTH
        // ----------------------------------------------------
        document.addEventListener('DOMContentLoaded', () => {
            const subtabBtns = document.querySelectorAll('.subtab-btn');
            const subPanels = document.querySelectorAll('.sub-panel');
            
            subtabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const targetSub = btn.getAttribute('data-sub');
                    
                    // Switch active button
                    subtabBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    
                    // Switch active panel
                    subPanels.forEach(p => {
                        if (p.id === `subpanel-${targetSub}`) {
                            p.classList.add('active');
                        } else {
                            p.classList.remove('active');
                        }
                    });
                });
            });

            // IBAN bank detector initial and event listener
            const ibanInput = document.getElementById('profile_iban');
            if (ibanInput) {
                const triggerDetection = () => {
                    const val = ibanInput.value;
                    const textEl = document.getElementById('bank-detector-text');
                    if (!textEl) return;
                    
                    const cleanVal = val.replace(/\s+/g, '').toUpperCase();
                    if (cleanVal.length < 4) {
                        textEl.innerText = "";
                        return;
                    }
                    
                    // Add TR check
                    let checkVal = cleanVal;
                    if (checkVal.substring(0, 2) === "TR") {
                        checkVal = checkVal.substring(2);
                    }
                    
                    const bankCode = checkVal.substring(2, 7);
                    let bankName = "";
                    if (bankCode === "00010") bankName = "T.C. Ziraat Bankası";
                    else if (bankCode === "00012") bankName = "Türkiye Halk Bankası";
                    else if (bankCode === "00015") bankName = "Türkiye Vakıflar Bankası (Vakıfbank)";
                    else if (bankCode === "00062") bankName = "Türkiye Garanti Bankası (Garanti BBVA)";
                    else if (bankCode === "00064") bankName = "Türkiye İş Bankası";
                    else if (bankCode === "00046") bankName = "Akbank";
                    else if (bankCode === "00111") bankName = "QNB Finansbank";
                    else if (bankCode === "00032") bankName = "Türk Ekonomi Bankası (TEB)";
                    else if (bankCode === "00067") bankName = "Yapı ve Kredi Bankası";
                    else if (bankCode === "00205") bankName = "Kuveyt Türk Katılım Bankası";
                    else if (bankCode === "00206") bankName = "Türkiye Finans Katılım Bankası";
                    else if (bankCode === "00092") bankName = "Denizbank";
                    else if (bankCode === "00123") bankName = "HSBC Bank";
                    else if (bankCode === "00146") bankName = "Odeabank";
                    
                    if (bankName) {
                        textEl.innerHTML = `<i class="fa-solid fa-building-columns"></i> Tespit Edilen Banka: <strong>${bankName}</strong>`;
                        textEl.style.color = "#10b981"; // Success green
                    } else {
                        if (checkVal.length >= 22) {
                            textEl.innerHTML = `<i class="fa-solid fa-circle-question"></i> Bilinmeyen / Diğer Banka`;
                            textEl.style.color = "var(--text-secondary)";
                        } else {
                            textEl.innerText = "";
                        }
                    }
                };

                ibanInput.addEventListener('keyup', triggerDetection);
                ibanInput.addEventListener('change', triggerDetection);
                triggerDetection(); // Run on load
            }
        });

        // Şifre Gücü Analizi
        function checkPasswordStrength(password) {
            const bar1 = document.getElementById('strength-bar-1');
            const bar2 = document.getElementById('strength-bar-2');
            const bar3 = document.getElementById('strength-bar-3');
            const text = document.getElementById('strength-text');
            
            if (!bar1 || !bar2 || !bar3 || !text) return;
            
            // Sıfırla
            bar1.style.backgroundColor = "";
            bar2.style.backgroundColor = "";
            bar3.style.backgroundColor = "";
            
            if (password.length === 0) {
                text.innerText = "Şifre gücü analizi bekleniyor...";
                text.style.color = "var(--text-muted)";
                return;
            }
            
            let score = 0;
            if (password.length >= 6) score++;
            if (/[A-Z]/.test(password) && /[a-z]/.test(password)) score++;
            if (/[0-9]/.test(password) || /[^A-Za-z0-9]/.test(password)) score++;
            
            if (score === 1) {
                bar1.style.backgroundColor = "#ef4444"; // Zayıf (Kırmızı)
                text.innerText = "Zayıf Şifre - Büyük/küçük harf veya sayı ekleyin.";
                text.style.color = "#ef4444";
            } else if (score === 2) {
                bar1.style.backgroundColor = "#fbbf24"; // Orta (Sarı)
                bar2.style.backgroundColor = "#fbbf24";
                text.innerText = "Orta Şifre - Özel karakter ekleyerek güçlendirin.";
                text.style.color = "#fbbf24";
            } else if (score === 3) {
                bar1.style.backgroundColor = "#10b981"; // Güçlü (Yeşil)
                bar2.style.backgroundColor = "#10b981";
                bar3.style.backgroundColor = "#10b981";
                text.innerText = "Güçlü Şifre - Hesabınız güvende.";
                text.style.color = "#10b981";
            }
        }

        // ----------------------------------------------------
        // THEME SWITCHER CONTROLLER (Karanlık / Aydınlık Mod)
        // ----------------------------------------------------
        const themeToggle = document.getElementById('theme-toggle');
        
        // Update Theme Button Icon
        const updateThemeIcon = (isDark) => {
            const icon = themeToggle.querySelector('i');
            if (isDark) {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
        };

        // Initialize theme UI state
        if (document.body.classList.contains('dark-theme')) {
            updateThemeIcon(true);
        }

        themeToggle.addEventListener('click', () => {
            const isDark = document.body.classList.toggle('dark-theme');
            localStorage.setItem('ss_theme', isDark ? 'dark' : 'light');
            updateThemeIcon(isDark);
        });

        // ----------------------------------------------------
        // SLIDE DRAWER & OVERTIME CALCULATOR CONTROLLER
        // ----------------------------------------------------
        const drawer = document.getElementById('shift-details-drawer');
        const overlay = document.getElementById('shift-drawer-overlay');
        const closeBtn = document.getElementById('drawer-close-btn');
        const slider = document.getElementById('drawer-multiplier-slider');

        // Drawer elements
        const titleDate = document.getElementById('drawer-title-date');
        const subtitlePerson = document.getElementById('drawer-subtitle-person');
        const barWork = document.getElementById('drawer-bar-work');
        const barOvertime = document.getElementById('drawer-bar-overtime');
        const lblWork = document.getElementById('drawer-lbl-work');
        const lblOvertime = document.getElementById('drawer-lbl-overtime');
        const lblMultiplier = document.getElementById('drawer-lbl-multiplier');
        const calcDaily = document.getElementById('drawer-calc-daily');
        const calcHourly = document.getElementById('drawer-calc-hourly');
        const calcOvertime = document.getElementById('drawer-calc-overtime');
        const calcExtra = document.getElementById('drawer-calc-extra');
        const calcTotal = document.getElementById('drawer-calc-total');

        // Current active row data cache
        let activeShiftData = null;

        const openDrawer = (data) => {
            activeShiftData = data;
            
            // Populate fields
            titleDate.innerText = `Vardiya Detayı (${data.date})`;
            subtitlePerson.innerText = `Çalışan: ${data.person} (Mesai: ${data.start} - ${data.end})`;
            
            lblWork.innerText = data.workHours;
            lblOvertime.innerText = data.overtimeHours;
            
            // Visual timeline widths
            const totalHours = parseFloat(data.workHours) + parseFloat(data.overtimeHours);
            if (totalHours > 0) {
                const workPct = (parseFloat(data.workHours) / totalHours) * 100;
                const otPct = (parseFloat(data.overtimeHours) / totalHours) * 100;
                barWork.style.width = `${workPct}%`;
                barOvertime.style.width = `${otPct}%`;
            } else {
                barWork.style.width = '0%';
                barOvertime.style.width = '0%';
            }

            // Reset slider to default 1.5
            slider.value = 1.5;
            
            // Calculate calculations
            recalculateOvertimeEarnings(1.5);

            // Open drawer and overlay
            drawer.classList.add('open');
            overlay.classList.add('open');
        };

        const closeDrawer = () => {
            drawer.classList.remove('open');
            overlay.classList.remove('open');
            activeShiftData = null;
        };

        const formatCurrency = (val) => {
            return '₺' + parseFloat(val).toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
        };

        const recalculateOvertimeEarnings = (multiplier) => {
            if (!activeShiftData) return;
            
            lblMultiplier.innerText = `${multiplier.toFixed(1)}x`;
            
            const dailyWage = parseFloat(activeShiftData.dailyWage);
            const extraPay = parseFloat(activeShiftData.extraPay);
            const workHours = parseFloat(activeShiftData.workHours);
            const overtimeHours = parseFloat(activeShiftData.overtimeHours);
            
            // Calculations
            const hourlyRate = dailyWage / 8;
            const overtimeHourlyRate = hourlyRate * multiplier;
            const overtimeEarnings = overtimeHours * overtimeHourlyRate;
            const totalEarnings = dailyWage + overtimeEarnings + extraPay;

            // Render
            calcDaily.innerText = formatCurrency(dailyWage);
            calcHourly.innerText = formatCurrency(overtimeHourlyRate);
            calcOvertime.innerText = formatCurrency(overtimeEarnings);
            calcExtra.innerText = formatCurrency(extraPay);
            calcTotal.innerText = formatCurrency(totalEarnings);
        };

        // Slider input event listener
        slider.addEventListener('input', (e) => {
            recalculateOvertimeEarnings(parseFloat(e.target.value));
        });

        // Add event listeners to all shift rows
        document.querySelectorAll('.clickable-shift-row').forEach(row => {
            row.addEventListener('click', () => {
                const dataset = row.dataset;
                openDrawer({
                    date: dataset.date,
                    person: dataset.person,
                    start: dataset.start,
                    end: dataset.end,
                    workHours: dataset.workHours,
                    overtimeHours: dataset.overtimeHours,
                    dailyWage: dataset.dailyWage,
                    extraPay: dataset.extraPay
                });
            });
        });

        closeBtn.addEventListener('click', closeDrawer);
        overlay.addEventListener('click', closeDrawer);

        // Escape key to close drawer
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeDrawer();
            }
        });

        // ----------------------------------------------------
        // COUNT-UP ANIMATION FOR STATS
        // ----------------------------------------------------
        const animateCount = (el, start, end, duration, decimals = 0) => {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                // Quadratic Easing Out
                const easeProgress = progress * (2 - progress);
                const currentVal = easeProgress * (end - start) + start;
                
                if (decimals > 0) {
                    el.innerText = currentVal.toFixed(decimals);
                } else {
                    el.innerText = Math.floor(currentVal);
                }
                
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    if (decimals > 0) {
                        el.innerText = end.toFixed(decimals);
                    } else {
                        el.innerText = end;
                    }
                }
            };
            window.requestAnimationFrame(step);
        };

        // Trigger stats animation
        const statsValues = document.querySelectorAll('.stat-value');
        statsValues.forEach(valEl => {
            // Extract the original number and string
            const originalText = valEl.innerHTML.trim();
            // Match number (integer or decimal)
            const match = originalText.match(/^([0-9.,]+)/);
            if (match) {
                const numericStr = match[1].replace(',', '.');
                const targetValue = parseFloat(numericStr);
                
                // Determine decimals
                const decimals = (numericStr.includes('.')) ? numericStr.split('.')[1].length : 0;
                
                // Keep the original suffix (e.g. " Gün" or " Saat")
                const suffix = originalText.substring(match[1].length);
                
                // Set initial to 0
                valEl.innerHTML = `0${suffix}`;
                
                // Create a sub-span for counting so suffix is preserved
                valEl.innerHTML = `<span class="counter-num">0</span>${suffix}`;
                const numSpan = valEl.querySelector('.counter-num');
                
                // Animate
                setTimeout(() => {
                    animateCount(numSpan, 0, targetValue, 1200, decimals);
                }, 200);
            }
        });

    </script>

    <!-- Slide Drawer Panel Overlay Backdrop -->
    <div class="drawer-overlay" id="shift-drawer-overlay"></div>

    <!-- Slide-over Details Drawer Panel -->
    <div class="slide-drawer" id="shift-details-drawer">
        <div class="drawer-header">
            <div class="drawer-title-area">
                <h3 class="drawer-title" id="drawer-title-date">Vardiya Detayı</h3>
                <span class="drawer-subtitle" id="drawer-subtitle-person">Çalışan: -</span>
            </div>
            <button type="button" class="drawer-close-btn" id="drawer-close-btn" aria-label="Kapat">&times;</button>
        </div>
        <div class="drawer-body">
            <!-- Timeline Chart Section -->
            <div>
                <div class="drawer-section-title">Çalışma Süresi Grafiği</div>
                <div class="timeline-visual-card">
                    <div class="timeline-visual-bar">
                        <div class="timeline-bar-work" id="drawer-bar-work" style="width: 0%;"></div>
                        <div class="timeline-bar-overtime" id="drawer-bar-overtime" style="width: 0%;"></div>
                    </div>
                    <div class="timeline-legend">
                        <span class="legend-item"><span class="legend-dot work"></span>Normal Çalışma (<span id="drawer-lbl-work">0</span> saat)</span>
                        <span class="legend-item"><span class="legend-dot overtime"></span>Fazla Mesai (<span id="drawer-lbl-overtime">0</span> saat)</span>
                    </div>
                </div>
            </div>

            <!-- Interactive Overtime Calculator Section -->
            <div>
                <div class="drawer-section-title">Mesai Ücreti Hesaplayıcı</div>
                <div class="calculator-card">
                    <div class="slider-header">
                        <span>Mesai Katsayısı</span>
                        <span class="multiplier-display" id="drawer-lbl-multiplier">1.5x</span>
                    </div>
                    <div class="slider-container">
                        <input type="range" class="drawer-slider" id="drawer-multiplier-slider" min="1.0" max="2.5" step="0.1" value="1.5">
                    </div>
                    <div class="calc-breakdown">
                        <div class="calc-row">
                            <span>Günlük Net Ücret:</span>
                            <span id="drawer-calc-daily">₺0,00</span>
                        </div>
                        <div class="calc-row">
                            <span>Mesai Saatlik Ücreti:</span>
                            <span id="drawer-calc-hourly">₺0,00</span>
                        </div>
                        <div class="calc-row">
                            <span>Fazla Mesai Kazancı:</span>
                            <span id="drawer-calc-overtime">₺0,00</span>
                        </div>
                        <div class="calc-row">
                            <span>Ekstra Ek Ödeme:</span>
                            <span id="drawer-calc-extra">₺0,00</span>
                        </div>
                        <div class="calc-row highlight">
                            <span>Toplam Tutar:</span>
                            <span id="drawer-calc-total">₺0,00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</body>
</html>
