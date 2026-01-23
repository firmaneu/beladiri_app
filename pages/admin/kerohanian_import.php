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
        
        if ($header === false || count($header) < 5) {
            $error = "Format CSV tidak valid! Harus memiliki minimal 5 kolom (No Anggota, Tanggal, Lokasi, Pembuka, Ranting)";
            fclose($handle);
        } else {
            // Sanitasi header
            $header = array_map(function($h) {
                return strtolower(trim($h));
            }, $header);
            
            // Cari index kolom
            $no_anggota_col = null;
            $tanggal_col = null;
            $lokasi_col = null;
            $pembuka_col = null;
            $ranting_col = null;
            
            foreach ($header as $idx => $col) {
                if (strpos($col, 'no') !== false && strpos($col, 'anggota') !== false) {
                    $no_anggota_col = $idx;
                }
                if (strpos($col, 'tanggal') !== false) {
                    $tanggal_col = $idx;
                }
                if (strpos($col, 'lokasi') !== false) {
                    $lokasi_col = $idx;
                }
                if (strpos($col, 'pembuka') !== false) {
                    $pembuka_col = $idx;
                }
                if (strpos($col, 'ranting') !== false) {
                    $ranting_col = $idx;
                }
            }
            
            if ($no_anggota_col === null || $tanggal_col === null || $lokasi_col === null || $pembuka_col === null) {
                $error = "CSV harus memiliki kolom: No Anggota, Tanggal, Lokasi, Pembuka Nama";
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
                    $no_anggota = trim($row[$no_anggota_col] ?? '');
                    $tanggal = trim($row[$tanggal_col] ?? '');
                    $lokasi = trim($row[$lokasi_col] ?? '');
                    $pembuka = trim($row[$pembuka_col] ?? '');
                    $ranting_name = isset($ranting_col) ? trim($row[$ranting_col] ?? '') : '';
                    
                    if (empty($no_anggota) || empty($tanggal) || empty($lokasi) || empty($pembuka)) {
                        $import_log[] = "Baris $row_num: ⚠️ Data tidak lengkap - di-skip";
                        $skipped++;
                        continue;
                    }
                    
                    // Parse tanggal (format bisa DD-MM-YYYY atau YYYY-MM-DD)
                    $parsed_date = null;
                    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $tanggal, $matches)) {
                        $parsed_date = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
                    } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tanggal)) {
                        $parsed_date = $tanggal;
                    }
                    
                    if (!$parsed_date) {
                        $import_log[] = "Baris $row_num: ❌ Format tanggal invalid (gunakan DD-MM-YYYY atau YYYY-MM-DD)";
                        $skipped++;
                        continue;
                    }
                    
                    // Cari anggota
                    $peserta_stmt = $conn->prepare("SELECT id FROM anggota WHERE no_anggota = ?");
                    $peserta_stmt->bind_param("s", $no_anggota);
                    $peserta_stmt->execute();
                    $peserta_result = $peserta_stmt->get_result();
                    
                    if ($peserta_result->num_rows == 0) {
                        $import_log[] = "Baris $row_num: ❌ Anggota '$no_anggota' tidak ditemukan";
                        $skipped++;
                        continue;
                    }
                    
                    $peserta_data = $peserta_result->fetch_assoc();
                    $anggota_id = $peserta_data['id'];
                    
                    // Cek apakah sudah pernah pembukaan kerohanian
                    $check = $conn->query("SELECT id FROM kerohanian WHERE anggota_id = $anggota_id");
                    if ($check->num_rows > 0) {
                        $import_log[] = "Baris $row_num: ⚠️ Anggota '$no_anggota' sudah memiliki pembukaan - di-skip";
                        $skipped++;
                        continue;
                    }
                    
                    // Cari ranting jika ada
                    $ranting_id = null;
                    if (!empty($ranting_name)) {
                        $ranting_stmt = $conn->prepare("SELECT id FROM ranting WHERE nama_ranting = ? LIMIT 1");
                        $ranting_stmt->bind_param("s", $ranting_name);
                        $ranting_stmt->execute();
                        $ranting_result = $ranting_stmt->get_result();
                        
                        if ($ranting_result->num_rows > 0) {
                            $ranting_data = $ranting_result->fetch_assoc();
                            $ranting_id = $ranting_data['id'];
                        }
                    }
                    
                    // Insert kerohanian
                    $insert_sql = "INSERT INTO kerohanian (anggota_id, ranting_id, tanggal_pembukaan, lokasi, pembuka_nama) 
                                  VALUES (?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("iisss", $anggota_id, $ranting_id, $parsed_date, $lokasi, $pembuka);
                    
                    if (!$insert_stmt->execute()) {
                        $import_log[] = "Baris $row_num: ❌ Error insert - " . $insert_stmt->error;
                        $skipped++;
                        continue;
                    }
                    
                    // Update status kerohanian di anggota
                    $conn->query("UPDATE anggota SET status_kerohanian = 'sudah', tanggal_pembukaan_kerohanian = '$parsed_date' WHERE id = $anggota_id");
                    
                    $import_log[] = "Baris $row_num: ✅ Anggota '$no_anggota' berhasil ditambahkan";
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
    <title>Import Kerohanian - Sistem Beladiri</title>
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
    <?php renderNavbar('📥 Import Kerohanian'); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Import Kerohanian dari CSV</h1>
            
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
                <p>1. <strong>No Anggota</strong> - Nomor identitas anggota</p>
                <p>2. <strong>Tanggal</strong> - Format DD-MM-YYYY atau YYYY-MM-DD</p>
                <p>3. <strong>Lokasi</strong> - Tempat pembukaan kerohanian</p>
                <p>4. <strong>Pembuka Nama</strong> - Nama pembuka kerohanian</p>
                <p>5. <strong>Ranting</strong> - Nama unit/ranting (opsional)</p>
                
                <p style="margin-top: 15px; font-weight: 600;">✅ Contoh Format CSV:</p>
                <table class="template-table">
                    <thead>
                        <tr>
                            <th>No Anggota</th>
                            <th>Tanggal</th>
                            <th>Lokasi</th>
                            <th>Pembuka Nama</th>
                            <th>Ranting</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>12345</td>
                            <td>15-08-2024</td>
                            <td>Gedung Olahraga</td>
                            <td>Bapak Heri</td>
                            <td>TL</td>
                        </tr>
                        <tr>
                            <td>12346</td>
                            <td>20-08-2024</td>
                            <td>Lapangan Terbuka</td>
                            <td>Ibu Siti</td>
                            <td>SMP 1</td>
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
                    <a href="kerohanian.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>