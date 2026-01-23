<?php
/**
 * Function untuk render navbar dengan tombol kembali ke parent page
 * @param string $page_title Judul halaman saat ini
 * @param string $back_url URL untuk tombol kembali (auto-detect jika kosong)
 */
function renderNavbar($page_title, $back_url = null) {
    // Auto-detect back URL berdasarkan halaman saat ini
    if ($back_url === null) {
        $current_file = basename($_SERVER['PHP_SELF']);
        
        // Mapping file ke parent page
        $back_map = [
            'anggota_detail.php' => 'anggota.php',
            'anggota_edit.php' => 'anggota.php',
            'anggota_tambah.php' => 'anggota.php',
            'anggota_import.php' => 'anggota.php',
            'anggota_hapus.php' => 'anggota.php',
            
            'ukt_detail.php' => 'ukt.php',
            'ukt_buat.php' => 'ukt.php',
            'ukt_input_nilai.php' => 'ukt.php',
            'ukt_tambah_peserta.php' => 'ukt.php',
            'ukt_import_nilai.php' => 'ukt.php',
            'ukt_hapus_peserta.php' => 'ukt.php',
            
            'kerohanian_detail.php' => 'kerohanian.php',
            'kerohanian_edit.php' => 'kerohanian.php',
            'kerohanian_tambah.php' => 'kerohanian.php',
            'kerohanian_import.php' => 'kerohanian.php',
            'kerohanian_hapus.php' => 'kerohanian.php',
            
            'pengurus_detail.php' => 'pengurus.php',
            'pengurus_edit.php' => 'pengurus.php',
            'pengurus_tambah.php' => 'pengurus.php',
            'pengurus_list.php' => 'pengurus.php',
            'pengurus_import.php' => 'pengurus.php',
            'pengurus_hapus.php' => 'pengurus.php',
            
            'ranting_detail.php' => 'ranting.php',
            'ranting_edit.php' => 'ranting.php',
            'ranting_tambah.php' => 'ranting.php',
            'ranting_import.php' => 'ranting.php',
            'ranting_hapus.php' => 'ranting.php',
            
            'jadwal_latihan.php' => '../../index.php',
            'settings.php' => '../../index.php',
            'user_management.php' => '../../index.php',
        ];
        
        if (isset($back_map[$current_file])) {
            $back_url = $back_map[$current_file];
        } else {
            $back_url = '../../index.php';
        }
    }
    
    $username = htmlspecialchars($_SESSION['nama'] ?? 'User');
    $role_label = isset($GLOBALS['permission_manager']) 
        ? $GLOBALS['permission_manager']->getRoleName()
        : ($_SESSION['role'] ?? 'User');
    
    // Mapping untuk role label yang lebih readable
    $role_map = [
        'admin' => 'Administrator',
        'pengprov' => 'Pengurus Provinsi',
        'pengkot' => 'Pengurus Kota',
        'unit' => 'Unit / Ranting',
        'tamu' => 'Tamu (Read Only)'
    ];
    
    $role_display = $role_map[$_SESSION['role'] ?? ''] ?? $role_label;
    
    ?>
    <div class="navbar">
        <div class="navbar-left">
            <h2><?php echo $page_title; ?></h2>
        </div>
        <div class="navbar-right">
            <div class="navbar-user-info">
                <span class="user-name"><?php echo $username; ?></span>
                <span class="user-role"><?php echo $role_display; ?></span>
            </div>
            <div class="navbar-buttons">                
                <a href="../../index.php" class="btn-navbar" title="Home">
                    🏠 Home
                </a>
                <a href="<?php echo htmlspecialchars($back_url); ?>" 
                   class="btn-navbar" title="Kembali ke halaman sebelumnya">
                    ← Kembali
                </a>
                <a href="../../logout.php" class="btn-navbar btn-danger" title="Logout">
                    🚪 Logout
                </a>
            </div>
        </div>
    </div>
    
    <style>
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .navbar-left h2 {
            margin: 0;
            font-size: 22px;
        }
        
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }
        
        .navbar-user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            font-size: 13px;
            min-width: 150px;
        }
        
        .user-name {
            font-weight: 600;
        }
        
        .user-role {
            opacity: 0.9;
            font-size: 11px;
        }
        
        .navbar-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-navbar {
            padding: 8px 14px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.3);
            white-space: nowrap;
        }
        
        .btn-navbar:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
        }
        
        .btn-navbar.btn-danger:hover {
            background: rgba(220,53,69,0.8);
            border-color: #dc3545;
        }
        
        @media print {
            .navbar { display: none; }
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                padding: 15px 20px;
            }
            
            .navbar-left h2 {
                font-size: 18px;
            }
            
            .navbar-right {
                width: 100%;
                justify-content: space-between;
            }
            
            .navbar-buttons {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
    <?php
}
?>