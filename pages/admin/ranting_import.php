<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';
include '../../auth/PermissionManager.php';
include '../../helpers/navbar.php';

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

// Helper function untuk mencatat log import
function log_import($row_num, $message, $type = 'info') {
    $icon = $type === 'success' ? '✅' : ($type === 'error' ? '❌' : '⚠️');
    $GLOBALS['import_log'][] = "Baris $row_num: $icon $message";
    return $type;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_ext != 'csv') {
        $error = "Hanya format CSV yang didukung!";
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle);
        
        if ($header === false) {
            $error = "File CSV kosong!";
            fclose($handle);
        } else {
            $row_num = 1;
            $imported = 0;
            $skipped = 0;
            
            // Prepared statements untuk check duplikat
            $check_nama_stmt = $conn->prepare("SELECT id FROM ranting WHERE nama_ranting = ?");
            $check_sk_stmt = $conn->prepare("SELECT id FROM ranting WHERE no_sk_pembentukan = ?");
            
            // Prepared statement untuk insert
            $insert_stmt = $conn->prepare("INSERT INTO ranting (nama_ranting, jenis, tanggal_sk_pembentukan, no_sk_pembentukan, 
                            alamat, ketua_nama, penanggung_jawab_teknik, no_kontak, pengurus_kota_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            while ($row = fgetcsv($handle)) {
                $row_num++;
                
                if (empty($row[0])) continue;
                
                // Format: nama_ranting, jenis, tanggal_sk, no_sk_pembentukan, alamat, ketua, pj_teknik, kontak, pengurus_kota_id
                $nama_ranting = trim($row[0] ?? '');
                $jenis = trim($row[1] ?? '');
                $tanggal_sk = trim($row[2] ?? '');
                $no_sk_pembentukan = trim($row[3] ?? '');
                $alamat = trim($row[4] ?? '');
                $ketua = trim($row[5] ?? '');
                $pj_teknik = trim($row[6] ?? '');
                $kontak = trim($row[7] ?? '');
                $pengurus_kota_id = isset($row[8]) ? (int)trim($row[8]) : 0;
                
                // Validasi data tidak lengkap
                if (empty($nama_ranting) || empty($jenis) || empty($tanggal_sk) || !$pengurus_kota_id) {
                    log_import($row_num, "Data tidak lengkap - dilewati", 'warning');
                    $skipped++;
                    continue;
                }
                
                // Check duplikat nama
                $check_nama_stmt->bind_param("s", $nama_ranting);
                $check_nama_stmt->execute();
                $check_nama_result = $check_nama_stmt->get_result();
                if ($check_nama_result->num_rows > 0) {
                    log_import($row_num, "Nama ranting '$nama_ranting' sudah ada - dilewati", 'warning');
                    $skipped++;
                    continue;
                }
                
                // Check SK jika diisi
                if (!empty($no_sk_pembentukan)) {
                    $check_sk_stmt->bind_param("s", $no_sk_pembentukan);
                    $check_sk_stmt->execute();
                    $check_sk_result = $check_sk_stmt->get_result();
                    if ($check_sk_result->num_rows > 0) {
                        log_import($row_num, "No SK '$no_sk_pembentukan' sudah digunakan - dilewati", 'warning');
                        $skipped++;
                        continue;
                    }
                }
                
                // Insert data
                $insert_stmt->bind_param("ssssssssi",
                    $nama_ranting, $jenis, $tanggal_sk, $no_sk_pembentukan,
                    $alamat, $ketua, $pj_teknik, $kontak, $pengurus_kota_id
                );
                
                if ($insert_stmt->execute()) {
                    log_import($row_num, "'$nama_ranting' berhasil ditambahkan", 'success');
                    $imported++;
                } else {
                    log_import($row_num, "Error insert - " . $insert_stmt->error, 'error');
                    $skipped++;
                }
            }
            
            fclose($handle);
            $check_nama_stmt->close();
            $check_sk_stmt->close();
            $insert_stmt->close();
            
            $success = "Import selesai! $imported ranting berhasil ditambahkan, $skipped dilewati.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Unit/Ranting - Sistem Beladiri</title>
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
        .container { max-width: 1000px; margin: 20px auto; padding: 0 20px; }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        h1 { margin-bottom: 10px; color: #333; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #333; font-weight: 600; }
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
        .info-box h4 { color: #667eea; margin-bottom: 10px; }
        .info-box p { font-size: 13px; color: #333; margin-bottom: 8px; font-family: monospace; overflow-wrap: anywhere; word-break: break-word; white-space: normal; }

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
            font-family: monospace;
        }

        .template-table th { 
            background: #f0f7ff; 
            font-weight: 600; 
            font-family: monospace;
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
        .log-item { margin-bottom: 6px; color: #333; }
    </style>
</head>
<body>
    <?php renderNavbar('⬆️ Import Unit/Ranting'); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Import Unit/Ranting dari CSV</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
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
                <h4>📋 Format File CSV</h4>
                <p><strong>Header:</strong></p>
                <p>nama_ranting, jenis, tanggal_sk, no_sk_pembentukan, alamat, ketua_nama, pj_teknik, no_kontak, pengurus_kota_id</p>
                
                <p style="margin-top: 15px; font-weight: 600;">Contoh Data:</p>
                <table class="template-table">
                    <thead>
                        <tr>
                            <th>nama_ranting</th>
                            <th>jenis</th>
                            <th>tanggal_sk</th>
                            <th>no_sk_pembentukan</th>
                            <th>alamat</th>
                            <th>ketua_nama</th>
                            <th>pj_teknik</th>
                            <th>no_kontak</th>
                            <th>pengurus_kota_id</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>SMP 1</td>
                            <td>unit</td>
                            <td>1990-06-15</td>
                            <td>001/SK/KOTA/2024</td>
                            <td>Jl. Pacar Surabaya</td>
                            <td>Widodo</td>
                            <td>Gatot</td>
                            <td>08xxxxxxxxxx</td>
                            <td>3</td>
                        </tr>
                        <tr>
                            <td>Gubeng</td>
                            <td>ranting</td>
                            <td>2015-06-15</td>
                            <td>002/SK/KOTA/2024</td>
                            <td>Jl. Gubeng Kertajaya</td>
                            <td>Firman</td>
                            <td>Firman</td>
                            <td>089654789632</td>
                            <td>3</td>
                        </tr>
                    </tbody>
                </table>
                
                <p style="margin-top: 15px; color: #666; font-size: 12px;">
                    📝 <strong>Catatan:</strong><br>
                    • jenis: ukm, ranting, atau unit<br>
                    • tanggal_sk: format YYYY-MM-DD<br>
                    • no_sk_pembentukan: harus unik (tidak boleh duplikat)<br>
                    • pengurus_kota_id: ID pengurus kota yang menaungi (harus ada di database)
                </p>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="csv_file">Pilih File CSV <span style="color: #dc3545;">*</span></label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">⬆️ Upload & Import</button>
                    <a href="ranting.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>