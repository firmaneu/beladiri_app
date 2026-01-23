<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';
include '../../auth/PermissionManager.php';
include '../../helpers/navbar.php';

// Initialize permission manager
$permission_manager = new PermissionManager(
    $conn,
    $_SESSION['user_id'],
    $_SESSION['role'],
    $_SESSION['pengurus_id'] ?? null,
    $_SESSION['ranting_id'] ?? null
);

$GLOBALS['permission_manager'] = $permission_manager;

if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

$error = '';
$success = '';
$import_log = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_ext != 'csv') {
        $error = "Hanya file CSV yang didukung!";
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        
        // Baca header
        $header = fgetcsv($handle);
        
        if ($header === false || count($header) < 6) {
            $error = "Format CSV tidak valid! Harus memiliki minimal 6 kolom";
            fclose($handle);
        } else {
            // Sanitasi header
            $header = array_map(function($h) {
                return strtolower(trim($h));
            }, $header);
            
            // Cari index kolom
            $jenis_col = null;
            $nama_col = null;
            $ketua_col = null;
            $sk_col = null;
            $mulai_col = null;
            $akhir_col = null;
            $alamat_col = null;
            $induk_col = null;
            
            foreach ($header as $idx => $col) {
                if (strpos($col, 'jenis') !== false) $jenis_col = $idx;
                if (strpos($col, 'nama pengurus') !== false) $nama_col = $idx;
                if (strpos($col, 'ketua') !== false) $ketua_col = $idx;
                if (strpos($col, 'sk') !== false) $sk_col = $idx;
                if (strpos($col, 'mulai') !== false) $mulai_col = $idx;
                if (strpos($col, 'akhir') !== false) $akhir_col = $idx;
                if (strpos($col, 'alamat') !== false) $alamat_col = $idx;
                if (strpos($col, 'induk') !== false) $induk_col = $idx;
            }
            
            if ($jenis_col === null || $nama_col === null || $ketua_col === null || 
                $sk_col === null || $mulai_col === null || $akhir_col === null) {
                $error = "CSV harus memiliki kolom: Jenis, Nama Pengurus, Ketua, SK, Periode Mulai, Periode Akhir";
                fclose($handle);
            } else {
                $row_num = 1;
                $imported = 0;
                $skipped = 0;
                
                while ($row = fgetcsv($handle)) {
                    $row_num++;
                    
                    if (empty($row[0])) {
                        continue;
                    }
                    
                    // Ambil data dari CSV
                    $jenis = strtolower(trim($row[$jenis_col] ?? ''));
                    $nama = trim($row[$nama_col] ?? '');
                    $ketua = trim($row[$ketua_col] ?? '');
                    $sk = trim($row[$sk_col] ?? '');
                    $mulai = trim($row[$mulai_col] ?? '');
                    $akhir = trim($row[$akhir_col] ?? '');
                    $alamat = isset($alamat_col) ? trim($row[$alamat_col] ?? '') : '';
                    $induk_nama = isset($induk_col) ? trim($row[$induk_col] ?? '') : '';
                    
                    // Validasi
                    if (empty($jenis) || empty($nama) || empty($ketua) || empty($sk) || empty($mulai) || empty($akhir)) {
                        $import_log[] = "Baris $row_num: ⚠️ Data tidak lengkap - di-skip";
                        $skipped++;
                        continue;
                    }
                    
                    if (!in_array($jenis, ['pusat', 'provinsi', 'kota'])) {
                        $import_log[] = "Baris $row_num: ❌ Jenis invalid (gunakan: pusat, provinsi, kota)";
                        $skipped++;
                        continue;
                    }
                    
                    // Parse tanggal
                    $mulai_parsed = null;
                    $akhir_parsed = null;
                    
                    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $mulai, $m)) {
                        $mulai_parsed = $m[3] . '-' . $m[2] . '-' . $m[1];
                    } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $mulai)) {
                        $mulai_parsed = $mulai;
                    }
                    
                    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $akhir, $m)) {
                        $akhir_parsed = $m[3] . '-' . $m[2] . '-' . $m[1];
                    } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $akhir)) {
                        $akhir_parsed = $akhir;
                    }
                    
                    if (!$mulai_parsed || !$akhir_parsed) {
                        $import_log[] = "Baris $row_num: ❌ Format tanggal invalid (gunakan DD-MM-YYYY)";
                        $skipped++;
                        continue;
                    }
                    
                    // Cari pengurus induk jika ada
                    $induk_id = null;
                    if (!empty($induk_nama)) {
                        $induk_stmt = $conn->prepare("SELECT id FROM pengurus WHERE nama_pengurus = ? LIMIT 1");
                        $induk_stmt->bind_param("s", $induk_nama);
                        $induk_stmt->execute();
                        $induk_result = $induk_stmt->get_result();
                        
                        if ($induk_result->num_rows > 0) {
                            $induk_data = $induk_result->fetch_assoc();
                            $induk_id = $induk_data['id'];
                        } else {
                            $import_log[] = "Baris $row_num: ⚠️ Pengurus induk '$induk_nama' tidak ditemukan - di-skip";
                            $skipped++;
                            continue;
                        }
                    }
                    
                    // Insert pengurus
                    $insert_sql = "INSERT INTO pengurus (jenis_pengurus, nama_pengurus, ketua_nama, sk_kepengurusan, 
                                  periode_mulai, periode_akhir, alamat_sekretariat, pengurus_induk_id) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("sssssssi", $jenis, $nama, $ketua, $sk, $mulai_parsed, $akhir_parsed, $alamat, $induk_id);
                    
                    if (!$insert_stmt->execute()) {
                        $import_log[] = "Baris $row_num: ❌ Error insert - " . $insert_stmt->error;
                        $skipped++;
                        continue;
                    }
                    
                    $import_log[] = "Baris $row_num: ✅ '$nama' berhasil ditambahkan";
                    $imported++;
                    $insert_stmt->close();
                }
                
                fclose($handle);
                $success = "Import selesai! $imported data berhasil disimpan, $skipped data di-skip.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Pengurus - Sistem Beladiri</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background-color: #f5f5f5; }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
        }
        
        .container { max-width: 900px; margin: 20px auto; padding: 0 20px; }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        h1 { margin-bottom: 10px; color: #333; }
        
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        input[type="file"] {
            padding: 10px;
            border: 2px dashed #667eea;
            border-radius: 5px;
            width: 100%;
        }
        
        .info-box {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .template-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 12px;
        }
        
        .template-table th, .template-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .template-table th {
            background: #f0f7ff;
            font-weight: 600;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .button-group { display: flex; gap: 15px; margin-top: 30px; }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
        
        .log-box {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
            font-size: 12px;
            font-family: 'Courier New', monospace;
        }
        
        .log-item {
            margin-bottom: 6px;
            color: #333;
        }
    </style>
</head>
<body>
    <?php renderNavbar('📥 Import Pengurus'); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Import Pengurus dari CSV</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
                <?php if (count($import_log) > 0): ?>
                <div class="log-box">
                    <strong>📋 Detail Import:</strong><br>
                    <?php foreach ($import_log as $log): ?>
                        <div class="log-item"><?php echo htmlspecialchars($log); ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="info-box">
                <h4 style="color: #667eea; margin-bottom: 10px;">📋 Format File CSV</h4>
                <p><strong>CSV harus memiliki kolom:</strong></p>
                <ol style="margin-left: 20px; margin-top: 8px;">
                    <li><strong>Jenis</strong> - pusat, provinsi, atau kota</li>
                    <li><strong>Nama Pengurus</strong> - Nama lengkap pengurus</li>
                    <li><strong>Ketua</strong> - Nama ketua pengurus</li>
                    <li><strong>SK</strong> - Nomor SK kepengurusan</li>
                    <li><strong>Periode Mulai</strong> - DD-MM-YYYY atau YYYY-MM-DD</li>
                    <li><strong>Periode Akhir</strong> - DD-MM-YYYY atau YYYY-MM-DD</li>
                    <li><strong>Alamat</strong> - Alamat sekretariat (opsional)</li>
                    <li><strong>Pengurus Induk</strong> - Nama pengurus yang menaungi (opsional)</li>
                </ol>
                
                <p style="margin-top: 15px; font-weight: 600;">✅ Contoh Format CSV:</p>
                <table class="template-table">
                    <thead>
                        <tr>
                            <th>Jenis</th>
                            <th>Nama Pengurus</th>
                            <th>Ketua</th>
                            <th>SK</th>
                            <th>Periode Mulai</th>
                            <th>Periode Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>provinsi</td>
                            <td>Jatim</td>
                            <td>Bapak Sukardi</td>
                            <td>789/SK/JTM/2024</td>
                            <td>01-01-2024</td>
                            <td>31-12-2027</td>
                        </tr>
                        <tr>
                            <td>kota</td>
                            <td>PengKot Surabaya</td>
                            <td>Ibu Ratna</td>
                            <td>456/SK/SBY/2024</td>
                            <td>15-02-2024</td>
                            <td>14-02-2027</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="csv_file">Pilih File CSV <span style="color: #dc3545;">*</span></label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">📥 Upload & Import</button>
                    <a href="pengurus.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>