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

$id = (int)$_GET['id'];

// Ambil data UKT
$ukt_result = $conn->query("SELECT * FROM ukt WHERE id = $id");

if ($ukt_result->num_rows == 0) {
    die("UKT tidak ditemukan!");
}

$ukt = $ukt_result->fetch_assoc();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tanggal_pelaksanaan = $_POST['tanggal_pelaksanaan'];
    $lokasi = $conn->real_escape_string($_POST['lokasi']);
    $penyelenggara_id = !empty($_POST['penyelenggara_id']) ? (int)$_POST['penyelenggara_id'] : null;
    $catatan = $conn->real_escape_string($_POST['catatan'] ?? '');
    
    if (empty($tanggal_pelaksanaan) || empty($lokasi)) {
        $error = "Tanggal dan lokasi harus diisi!";
    } else {
        $sql = "UPDATE ukt SET 
                tanggal_pelaksanaan = ?, 
                lokasi = ?, 
                penyelenggara_id = ?, 
                catatan = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssisi", $tanggal_pelaksanaan, $lokasi, $penyelenggara_id, $catatan, $id);
        
        if ($stmt->execute()) {
            $success = "UKT berhasil diperbarui!";
            
            // Refresh data
            $ukt_result = $conn->query("SELECT * FROM ukt WHERE id = $id");
            $ukt = $ukt_result->fetch_assoc();
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit UKT - Sistem Beladiri</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background-color: #f5f5f5; }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container { max-width: 900px; margin: 20px auto; padding: 0 20px; }
        
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
            font-size: 13px;
        }
        
        input[type="text"], 
        input[type="date"], 
        select, 
        textarea {
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
        
        .button-group { 
            display: flex; 
            gap: 15px; 
            margin-top: 35px; 
        }
        
        .btn {
            padding: 12px 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        h3 {
            color: #333;
            margin: 30px 0 20px 0;
            font-size: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #667eea;
        }
        
        h3:first-child {
            margin-top: 0;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            color: #333;
            font-size: 13px;
        }
        
        select:disabled {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        
        .loading {
            display: none;
            font-size: 12px;
            color: #999;
            margin-top: 6px;
        }
    </style>
</head>
<body>
    <?php renderNavbar('✏️ Edit UKT'); ?>
    
    <div class="container">
        <div class="form-container">
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <h1>✏️ Edit Data UKT</h1>
            
            <div class="info-box">
                <strong>ℹ️ Catatan:</strong> Anda dapat mengubah informasi dasar UKT. Data peserta dan hasil UKT tidak dapat diubah di sini.
            </div>
            
            <form method="POST">
                <h3>📋 Informasi UKT</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tanggal Pelaksanaan <span class="required">*</span></label>
                        <input type="date" name="tanggal_pelaksanaan" value="<?php echo date('Y-m-d', strtotime($ukt['tanggal_pelaksanaan'])); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Lokasi <span class="required">*</span></label>
                        <input type="text" name="lokasi" value="<?php echo htmlspecialchars($ukt['lokasi']); ?>" required placeholder="Contoh: Gedung Olahraga">
                    </div>
                </div>
                
                <h3>🏛️ Penyelenggara</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Jenis Penyelenggara</label>
                        <select name="jenis_penyelenggara" id="jenisPenyelenggara" onchange="handleJenisPenyelenggaraChange()">
                            <option value="">-- Pilih Jenis Penyelenggara --</option>
                            <option value="pusat">Pusat (PP)</option>
                            <option value="provinsi">Provinsi (PengProv)</option>
                            <option value="kota">Kota / Kabupaten (PengKot)</option>
                        </select>
                        <div class="form-hint">Tingkat organisasi penyelenggara</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Penyelenggara</label>
                        <select name="penyelenggara_id" id="namaPenyelenggara" disabled>
                            <option value="">-- Pilih Penyelenggara --</option>
                        </select>
                        <div class="form-hint">Organisasi yang menyelenggarakan UKT</div>
                        <div class="loading" id="loadingPenyelenggara">Memuat data...</div>
                    </div>
                </div>
                
                <div class="form-row full">
                    <div class="form-group">
                        <label>Catatan / Keterangan</label>
                        <textarea name="catatan" placeholder="Catatan tambahan tentang UKT (opsional)"><?php echo htmlspecialchars($ukt['catatan'] ?? ''); ?></textarea>
                        <div class="form-hint">Informasi tambahan jika diperlukan</div>
                    </div>
                </div>
                
                <h3>📊 Statistik Peserta</h3>
                <div class="info-box">
                    <?php 
                    $stat_result = $conn->query("SELECT 
                        COUNT(id) as total,
                        SUM(CASE WHEN status = 'lulus' THEN 1 ELSE 0 END) as lulus,
                        SUM(CASE WHEN status = 'tidak_lulus' THEN 1 ELSE 0 END) as tidak_lulus
                        FROM ukt_peserta WHERE ukt_id = $id");
                    $stats = $stat_result->fetch_assoc();
                    ?>
                    <strong>Total Peserta:</strong> <?php echo $stats['total'] ?? 0; ?><br>
                    <strong>Lulus:</strong> <?php echo $stats['lulus'] ?? 0; ?><br>
                    <strong>Tidak Lulus:</strong> <?php echo $stats['tidak_lulus'] ?? 0; ?>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                    <a href="ukt_detail.php?id=<?php echo $id; ?>" class="btn btn-secondary">Kembali</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function handleJenisPenyelenggaraChange() {
            const jenisPenyelenggara = document.getElementById('jenisPenyelenggara').value;
            const namaPenyelenggaraSelect = document.getElementById('namaPenyelenggara');
            const loadingDiv = document.getElementById('loadingPenyelenggara');
            
            namaPenyelenggaraSelect.innerHTML = '<option value="">-- Pilih Penyelenggara --</option>';
            namaPenyelenggaraSelect.disabled = true;
            loadingDiv.style.display = 'none';
            
            if (!jenisPenyelenggara) return;
            
            loadingDiv.style.display = 'block';
            
            fetch('../../api/get_penyelenggara.php?jenis_pengurus=' + encodeURIComponent(jenisPenyelenggara))
                .then(response => response.json())
                .then(data => {
                    loadingDiv.style.display = 'none';
                    if (data.success && data.data.length > 0) {
                        namaPenyelenggaraSelect.innerHTML = '<option value="">-- Pilih Penyelenggara --</option>';
                        data.data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.id;
                            option.textContent = item.nama;
                            <?php if ($ukt['penyelenggara_id']): ?>
                                if (item.id == <?php echo $ukt['penyelenggara_id']; ?>) option.selected = true;
                            <?php endif; ?>
                            namaPenyelenggaraSelect.appendChild(option);
                        });
                        namaPenyelenggaraSelect.disabled = false;
                    } else {
                        namaPenyelenggaraSelect.innerHTML = '<option value="">-- Tidak ada data --</option>';
                    }
                })
                .catch(error => { loadingDiv.style.display = 'none'; console.error('Error:', error); });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($ukt['penyelenggara_id']): ?>
                // Fetch jenis penyelenggara dari database
                fetch('../../api/get_penyelenggara.php?id=<?php echo $ukt['penyelenggara_id']; ?>')
                    .then(r => r.json())
                    .then(data => {
                        if (data.jenis) {
                            document.getElementById('jenisPenyelenggara').value = data.jenis;
                            handleJenisPenyelenggaraChange();
                        }
                    });
            <?php endif; ?>
        });
    </script>
</body>
</html>