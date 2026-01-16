<?php
session_start();

if (!isset($_SESSION['user_id'])) {
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

// Store untuk global use
$GLOBALS['permission_manager'] = $permission_manager;

// Check permission untuk action ini
if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

// Ambil filter dari GET
$ranting_id = isset($_GET['ranting_id']) ? (int)$_GET['ranting_id'] : 0;
$filter_pengprov = isset($_GET['filter_pengprov']) ? (int)$_GET['filter_pengprov'] : 0;
$filter_pengkot = isset($_GET['filter_pengkot']) ? (int)$_GET['filter_pengkot'] : 0;
$error = '';
$success = '';

// Proses tambah/edit jadwal
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'add';
    $hari = $_POST['hari'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    
    if (empty($ranting_id)) {
        $error = "Pilih unit/ranting terlebih dahulu!";
    } else {
        if ($action == 'add') {
            $sql = "INSERT INTO jadwal_latihan (ranting_id, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $ranting_id, $hari, $jam_mulai, $jam_selesai);
            
            if ($stmt->execute()) {
                $success = "Jadwal latihan berhasil ditambahkan!";
            } else {
                $error = "Error: " . $stmt->error;
            }
        } elseif ($action == 'delete') {
            $jadwal_id = (int)$_POST['jadwal_id'];
            $conn->query("DELETE FROM jadwal_latihan WHERE id = $jadwal_id");
            $success = "Jadwal latihan berhasil dihapus!";
        }
    }
}

// Ambil daftar pengurus provinsi
$pengprov_result = $conn->query("SELECT id, nama_pengurus FROM pengurus WHERE jenis_pengurus = 'provinsi' ORDER BY nama_pengurus");

// Ambil daftar pengurus kota berdasarkan filter pengprov
$pengkot_result = null;
$ranting_result = null;

if ($filter_pengprov > 0) {
    // Query pengkot yang berada di bawah pengprov yang dipilih
    $pengkot_result = $conn->query("SELECT id, nama_pengurus FROM pengurus WHERE jenis_pengurus = 'kota' AND pengurus_induk_id = $filter_pengprov ORDER BY nama_pengurus");
    
    // Query ranting berdasarkan filter pengkot
    if ($filter_pengkot > 0) {
        $ranting_result = $conn->query("SELECT id, nama_ranting FROM ranting WHERE pengurus_kota_id = $filter_pengkot ORDER BY nama_ranting");
    }
} else {
    // Jika tidak ada pengprov yang dipilih, tampilkan semua pengkot
    $pengkot_result = $conn->query("SELECT id, nama_pengurus FROM pengurus WHERE jenis_pengurus = 'kota' ORDER BY nama_pengurus");
}

// Jika tidak ada ranting result, buat query kosong
if (!$ranting_result) {
    $ranting_result = new stdClass();
    $ranting_result->num_rows = 0;
}

// Ambil jadwal untuk ranting yang dipilih
$jadwal_result = null;
if ($ranting_id > 0) {
    $jadwal_result = $conn->query("SELECT * FROM jadwal_latihan WHERE ranting_id = $ranting_id ORDER BY FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')");
}

$hari_options = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$is_readonly = $_SESSION['role'] == 'tamu';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Latihan - Sistem Beladiri</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background-color: #f5f5f5; }
        
        .container { max-width: 1100px; margin: 20px auto; padding: 0 20px; }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        h1 { color: #333; margin-bottom: 10px; }
        h3 { color: #333; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #667eea; }
        
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        select, input { 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
        }
        
        select:focus, input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        select:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            align-items: end;
        }
        
        .btn { 
            padding: 10px 15px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-weight: 600; 
            font-size: 13px; 
            transition: all 0.3s;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-reset { background: #6c757d; color: white; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f8f9fa; padding: 12px; text-align: left; border: 1px solid #ddd; font-weight: 600; }
        td { padding: 12px; border: 1px solid #ddd; }
        tr:hover { background: #f9f9f9; }
        
        .no-data { text-align: center; padding: 40px; color: #999; }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
        
        .info-text {
            background: #f0f7ff;
            border-left: 3px solid #667eea;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #333;
        }
        
        .info-text strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <?php renderNavbar('⏰ Jadwal Latihan'); ?>
    
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✓ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Filter Section -->
        <div class="card">
            <h3>🔍 Filter Unit/Ranting (Cascade)</h3>
            
            <div class="info-text">
                <strong>ℹ️ Cara Menggunakan:</strong> Pilih Pengurus Provinsi terlebih dahulu, lalu Pengurus Kota/Kabupaten akan menampilkan list yang ada di bawahnya, kemudian pilih Unit/Ranting.
            </div>
            
            <form method="GET" style="margin-bottom: 20px;">
                <div class="filter-section">
                    <div class="filter-row">
                        <!-- Filter 1: Pengurus Provinsi -->
                        <div class="form-group">
                            <label for="filter_pengprov">📍 Pengurus Provinsi</label>
                            <select name="filter_pengprov" id="filter_pengprov" onchange="updatePengKot()">
                                <option value="">-- Pilih Pengurus Provinsi --</option>
                                <?php 
                                $pengprov_result->data_seek(0);
                                while ($row = $pengprov_result->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $row['id']; ?>" <?php echo $filter_pengprov == $row['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($row['nama_pengurus']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Filter 2: Pengurus Kota -->
                        <div class="form-group">
                            <label for="filter_pengkot">🏛️ Pengurus Kota / Kabupaten</label>
                            <select name="filter_pengkot" id="filter_pengkot" onchange="updateRanting()" <?php echo $filter_pengprov == 0 ? 'disabled' : ''; ?>>
                                <option value="">-- Pilih Pengurus Kota --</option>
                                <?php 
                                if ($pengkot_result) {
                                    $pengkot_result->data_seek(0);
                                    while ($row = $pengkot_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $row['id']; ?>" <?php echo $filter_pengkot == $row['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($row['nama_pengurus']); ?>
                                        </option>
                                    <?php endwhile;
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Filter 3: Unit/Ranting -->
                        <div class="form-group">
                            <label for="ranting_id">🢂 Unit/Ranting</label>
                            <select name="ranting_id" id="ranting_id" onchange="this.form.submit()" <?php echo $filter_pengkot == 0 ? 'disabled' : ''; ?>>
                                <option value="">-- Pilih Unit/Ranting --</option>
                                <?php 
                                if ($ranting_result && $ranting_result->num_rows > 0) {
                                    $ranting_result->data_seek(0);
                                    while ($row = $ranting_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $row['id']; ?>" <?php echo ($ranting_id == $row['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($row['nama_ranting']); ?>
                                        </option>
                                    <?php endwhile;
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top: 15px;">
                        <a href="jadwal_latihan.php" class="btn btn-reset">🔄 Reset Filter</a>
                    </div>
                </div>
            </form>
        </div>
                
        <!-- Daftar Jadwal -->
        <?php if ($ranting_id > 0): ?>
        <div class="card">
            <h3>📋 Jadwal Latihan</h3>
            
            <?php if ($jadwal_result && $jadwal_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Hari</th>
                        <th>Jam Mulai</th>
                        <th>Jam Selesai</th>
                        <th style="width: 100px;">Durasi</th>
                        <?php if (!$is_readonly): ?><th style="width: 80px;">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $jadwal_result->fetch_assoc()): 
                        $mulai = strtotime($row['jam_mulai']);
                        $selesai = strtotime($row['jam_selesai']);
                        $durasi = round(($selesai - $mulai) / 3600);
                    ?>
                    <tr>
                        <td><strong><?php echo $row['hari']; ?></strong></td>
                        <td><?php echo date('H:i', $mulai); ?></td>
                        <td><?php echo date('H:i', $selesai); ?></td>
                        <td><?php echo $durasi; ?> jam</td>
                        <?php if (!$is_readonly): ?>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="jadwal_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Hapus jadwal ini?')">Hapus</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <p>🔭 Belum ada jadwal latihan untuk unit/ranting yang dipilih</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Form Input Jadwal -->
        <?php if ($ranting_id > 0 && !$is_readonly): ?>
        <div class="card">
            <h3>➕ Tambah Jadwal Baru</h3>
            
            <form method="POST" style="margin-bottom: 25px;">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="hari">Hari</label>
                        <select name="hari" id="hari" required>
                            <option value="">-- Pilih Hari --</option>
                            <?php foreach ($hari_options as $h): ?>
                                <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="jam_mulai">Jam Mulai</label>
                        <input type="time" name="jam_mulai" id="jam_mulai" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="jam_selesai">Jam Selesai</label>
                        <input type="time" name="jam_selesai" id="jam_selesai" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="margin-top: 10px;">➕ Tambah Jadwal</button>
            </form>
        </div>
        <?php endif; ?>        
    </div>

    <script>
        // Function untuk update dropdown PengKot via AJAX
        function updatePengKot() {
            const pengprovSelect = document.getElementById('filter_pengprov');
            const pengkotSelect = document.getElementById('filter_pengkot');
            const rantingSelect = document.getElementById('ranting_id');
            
            const pengprovId = pengprovSelect.value;
            
            if (pengprovId === '') {
                // Jika tidak ada pengprov yang dipilih, disable pengkot dan ranting
                pengkotSelect.disabled = true;
                rantingSelect.disabled = true;
                pengkotSelect.innerHTML = '<option value="">-- Pilih Pengurus Kota --</option>';
                rantingSelect.innerHTML = '<option value="">-- Pilih Unit/Ranting --</option>';
                return;
            }
            
            pengkotSelect.disabled = false;
            
            // Fetch pengkot yang ada di bawah pengprov ini
            fetch('../../api/get_pengkot.php?pengprov_id=' + pengprovId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<option value="">-- Pilih Pengurus Kota --</option>';
                        data.data.forEach(pengkot => {
                            html += '<option value="' + pengkot.id + '">' + pengkot.nama_pengurus + '</option>';
                        });
                        pengkotSelect.innerHTML = html;
                        
                        // Reset ranting dropdown
                        rantingSelect.innerHTML = '<option value="">-- Pilih Unit/Ranting --</option>';
                        rantingSelect.disabled = true;
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        // Function untuk update dropdown Ranting via AJAX
        function updateRanting() {
            const pengkotSelect = document.getElementById('filter_pengkot');
            const rantingSelect = document.getElementById('ranting_id');
            
            const pengkotId = pengkotSelect.value;
            
            if (pengkotId === '') {
                // Jika tidak ada pengkot yang dipilih, disable ranting
                rantingSelect.disabled = true;
                rantingSelect.innerHTML = '<option value="">-- Pilih Unit/Ranting --</option>';
                return;
            }
            
            rantingSelect.disabled = false;
            
            // Fetch ranting yang ada di bawah pengkot ini
            fetch('../../api/get_ranting.php?pengkot_id=' + pengkotId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<option value="">-- Pilih Unit/Ranting --</option>';
                        data.data.forEach(ranting => {
                            html += '<option value="' + ranting.id + '">' + ranting.nama_ranting + '</option>';
                        });
                        rantingSelect.innerHTML = html;
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>