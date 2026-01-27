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
    die("âŒ Akses ditolak!");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_ranting = $conn->real_escape_string($_POST['nama_ranting']);
    $jenis = $_POST['jenis'];
    $tanggal_sk = $_POST['tanggal_sk'];
    $no_sk_pembentukan = $conn->real_escape_string($_POST['no_sk_pembentukan']);
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $ketua_nama = $conn->real_escape_string($_POST['ketua_nama']);
    $penanggung_jawab = $conn->real_escape_string($_POST['penanggung_jawab']);
    $no_kontak = $_POST['no_kontak'];
    $pengurus_kota_id = (int)$_POST['pengurus_kota_id'];
    
    // Validasi No SK jika diisi
    if (!empty($no_sk_pembentukan)) {
        $check_sk = $conn->query("SELECT id FROM ranting WHERE no_sk_pembentukan = '$no_sk_pembentukan'");
        if ($check_sk->num_rows > 0) {
            $error = "No SK ini sudah digunakan!";
        }
    }
    
    if (!$error) {
        // Handle SK file upload
        if (isset($_FILES['sk_pembentukan']) && $_FILES['sk_pembentukan']['size'] > 0) {
            $file = $_FILES['sk_pembentukan'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if ($file_ext != 'pdf') {
                $error = "Hanya file PDF yang diperbolehkan!";
            } elseif ($file['size'] > 5242880) {
                $error = "Ukuran file maksimal 5MB!";
            } else {
                $upload_dir = '../../uploads/sk_pembentukan/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $nama_clean = preg_replace("/[^a-z0-9 -]/i", "_", $nama_ranting);
                $nama_clean = str_replace(" ", "_", $nama_clean);
                $file_name = 'SK-' . $nama_clean . '-' . $pengurus_kota_id . '-01.pdf';
                $file_path = $upload_dir . $file_name;
                
                if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                    $error = "Gagal upload file SK!";
                }
            }
        }
        
        if (!$error) {
            $sql = "INSERT INTO ranting (nama_ranting, jenis, tanggal_sk_pembentukan, no_sk_pembentukan,
                    alamat, ketua_nama, penanggung_jawab_teknik, no_kontak, pengurus_kota_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssi",
                $nama_ranting, $jenis, $tanggal_sk, $no_sk_pembentukan,
                $alamat, $ketua_nama, $penanggung_jawab,
                $no_kontak, $pengurus_kota_id
            );
            
            if ($stmt->execute()) {
                $ranting_id_baru = $stmt->insert_id;
                $success = "Unit/Ranting berhasil ditambahkan!";
                
                // Tambah jadwal jika ada
                if (isset($_POST['jadwal_hari']) && is_array($_POST['jadwal_hari'])) {
                    $jadwal_added = 0;
                    foreach ($_POST['jadwal_hari'] as $idx => $hari) {
                        if (!empty($hari) && !empty($_POST['jadwal_jam_mulai'][$idx]) && !empty($_POST['jadwal_jam_selesai'][$idx])) {
                            $jam_mulai = $_POST['jadwal_jam_mulai'][$idx];
                            $jam_selesai = $_POST['jadwal_jam_selesai'][$idx];
                            
                            $jadwal_sql = "INSERT INTO jadwal_latihan (ranting_id, hari, jam_mulai, jam_selesai)
                                         VALUES (?, ?, ?, ?)";
                            $jadwal_stmt = $conn->prepare($jadwal_sql);
                            $jadwal_stmt->bind_param("isss", $ranting_id_baru, $hari, $jam_mulai, $jam_selesai);
                            
                            if ($jadwal_stmt->execute()) {
                                $jadwal_added++;
                            }
                        }
                    }
                    if ($jadwal_added > 0) {
                        $success .= " ($jadwal_added jadwal ditambahkan)";
                    }
                }
                
                header("refresh:2;url=ranting_detail.php?id=$ranting_id_baru");
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
    }
}

$pengurus_result = $conn->query("SELECT id, nama_pengurus FROM pengurus WHERE jenis_pengurus = 'kota' ORDER BY nama_pengurus");
$hari_options = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Unit/Ranting - Sistem Beladiri</title>
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
            padding: 35px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        h1 { color: #333; margin-bottom: 30px; }
        .form-group { margin-bottom: 22px; }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        input[type="text"], input[type="date"], input[type="file"], input[type="tel"], input[type="time"],
        select, textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        textarea { resize: vertical; min-height: 100px; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        .form-row.full { grid-template-columns: 1fr; }
        .required { color: #dc3545; }
        .form-hint { font-size: 12px; color: #999; margin-top: 6px; }
        hr { margin: 40px 0; border: none; border-top: 2px solid #f0f0f0; }
        h3 { color: #333; margin-bottom: 25px; font-size: 16px; padding-bottom: 12px; border-bottom: 2px solid #667eea; }
        .btn {
            padding: 12px 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-small { padding: 8px 12px; font-size: 12px; }

        .button-group { display: flex; gap: 15px; margin-top: 35px; }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid;
        }
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }

        .info-box {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 25px;
        }

        .info-box strong { color: #667eea; }

        .jadwal-item {
            background: white;
            padding: 15px;
            border-radius: 5px;
            border-left: 3px solid #667eea;
            margin-bottom: 10px;
        }

        .jadwal-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 50px;
            gap: 15px;
            margin-bottom: 15px;
            align-items: end;
        }

        .jadwal-remove {
            background: #dc3545;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }

        .jadwal-remove:hover {
            background: #c82333;
        }
        
        #jadwal-list {
            margin-bottom: 15px;
        }
        
        .jadwal-item {
            background: white;
            padding: 15px;
            border-radius: 5px;
            border-left: 3px solid #667eea;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php renderNavbar('➕ Tambah Unit/Ranting'); ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Formulir Tambah Unit/Ranting Baru</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">âš ï¸ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">âœ" <?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <h3>📋 Informasi Dasar</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Unit/Ranting <span class="required">*</span></label>
                        <input type="text" name="nama_ranting" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Jenis <span class="required">*</span></label>
                        <select name="jenis" required>
                            <option value="">-- Pilih Jenis --</option>
                            <option value="ukm">UKM Perguruan Tinggi</option>
                            <option value="ranting">Ranting</option>
                            <option value="unit">Unit</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tanggal SK <span class="required">*</span></label>
                        <input type="date" name="tanggal_sk" required>
                    </div>

                    <div class="form-group">
                        <label>No SK Pembentukan</label>
                        <input type="text" name="no_sk_pembentukan" placeholder="Contoh: 001/SK/KOTA/2024">
                        <div class="form-hint">Nomor Surat Keputusan pembentukan (harus unik)</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Upload SK File (PDF)</label>
                        <input type="file" name="sk_pembentukan" accept=".pdf">
                        <div class="form-hint">Ukuran maksimal 5MB</div>
                    </div>
                </div>
                
                <div class="form-row full">
                    <div class="form-group">
                        <label>Alamat <span class="required">*</span></label>
                        <textarea name="alamat" required></textarea>
                    </div>
                </div>
                
                <hr>
                
                <h3>👤 Struktur Organisasi</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Ketua <span class="required">*</span></label>
                        <input type="text" name="ketua_nama" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Penanggung Jawab Teknik</label>
                        <input type="text" name="penanggung_jawab">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>No Kontak <span class="required">*</span></label>
                        <input type="tel" name="no_kontak" required placeholder="08xxxxxxxxxx">
                    </div>
                    
                    <div class="form-group">
                        <label>Pengurus Kota <span class="required">*</span></label>
                        <select name="pengurus_kota_id" required>
                            <option value="">-- Pilih Pengurus Kota --</option>
                            <?php while ($row = $pengurus_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_pengurus']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <hr>
                
                <h3>⏰ Jadwal Latihan (Opsional)</h3>
                
                <div class="info-box">
                    <strong>ℹ️ Catatan:</strong> Anda dapat menambahkan jadwal latihan di sini. Jadwal bisa ditambah/diubah nanti di menu Jadwal Latihan atau Detail Unit.
                </div>
                
                <div id="jadwal-list"></div>
                
                <button type="button" class="btn btn-primary btn-small" onclick="tambahJadwal()">+ Tambah Jadwal</button>
                
                <div class="button-group" style="margin-top: 40px;">
                    <button type="submit" class="btn btn-primary">💾 Simpan Unit/Ranting</button>
                    <a href="ranting.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let jadwalIndex = 0;
        
        function tambahJadwal() {
            const container = document.getElementById('jadwal-list');
            
            const jadwalDiv = document.createElement('div');
            jadwalDiv.className = 'jadwal-item';
            jadwalDiv.id = 'jadwal-' + jadwalIndex;
            
            const hariOptions = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
            let optionsHtml = '<option value="">-- Pilih Hari --</option>';
            hariOptions.forEach(h => {
                optionsHtml += '<option value="' + h + '">' + h + '</option>';
            });
            
            jadwalDiv.innerHTML = `
                <div class="jadwal-row">
                    <div class="form-group">
                        <label>Hari</label>
                        <select name="jadwal_hari[]" required>
                            ${optionsHtml}
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Jam Mulai</label>
                        <input type="time" name="jadwal_jam_mulai[]" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Jam Selesai</label>
                        <input type="time" name="jadwal_jam_selesai[]" required>
                    </div>
                    
                    <div><button type="button" class="jadwal-remove" onclick="hapusJadwal('jadwal-${jadwalIndex}')">Hapus</button></div>
                </div>
            `;
            
            container.appendChild(jadwalDiv);
            jadwalIndex++;
        }
                
        function hapusJadwal(id) {
            const element = document.getElementById(id);
            if (element) {
                element.remove();
            }
        }
    </script>
</body>
</html>