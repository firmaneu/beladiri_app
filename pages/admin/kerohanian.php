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

$search = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT k.*, a.nama_lengkap, a.no_anggota, a.tingkat_id, t.nama_tingkat, r.nama_ranting
        FROM kerohanian k
        JOIN anggota a ON k.anggota_id = a.id
        LEFT JOIN tingkatan t ON a.tingkat_id = t.id
        LEFT JOIN ranting r ON k.ranting_id = r.id
        WHERE 1=1";

if ($search) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND (a.nama_lengkap LIKE '%$search%' OR a.no_anggota LIKE '%$search%')";
}

$sql .= " ORDER BY k.tanggal_pembukaan DESC";

$result = $conn->query($sql);
$total_kerohanian = $result->num_rows;

$is_readonly = $_SESSION['role'] == 'user';

function formatTanggal($date) {
    if (empty($date)) return '-';
    return date('d-m-Y', strtotime($date));
}

function singkatTingkat($nama_tingkat) {
    $singkat = [
        'Dasar I' => 'DI',
        'Dasar II' => 'DII',
        'Calon Keluarga' => 'Cakel',
        'Putih' => 'P',
        'Putih Hijau' => 'PH',
        'Hijau' => 'H',
        'Hijau Biru' => 'HB',
        'Biru' => 'B',
        'Biru Merah' => 'BM',
        'Merah' => 'M',
        'Merah Kuning' => 'MK',
        'Kuning' => 'K/PM',
        'Pendekar' => 'PKE'
    ];
    return isset($singkat[$nama_tingkat]) ? $singkat[$nama_tingkat] : $nama_tingkat;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kerohanian - Sistem Beladiri</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            color: #333;
        }
        
        .header-right {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-print {
            background: #6c757d;
            color: white;
        }

        .btn-print:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .search-filter {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-filter input {
            flex: 1;
            min-width: 200px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .search-filter input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ddd;
            font-size: 12px;
        }
        
        td {
            padding: 11px 12px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .action-icons {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        
        .icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s;
            color: white;
        }
        
        .icon-view {
            background: #3498db;
        }
        
        .icon-view:hover {
            background: #2980b9;
        }
        
        .icon-edit {
            background: #f39c12;
        }
        
        .icon-edit:hover {
            background: #d68910;
        }
        
        .icon-delete {
            background: #e74c3c;
        }
        
        .icon-delete:hover {
            background: #c0392b;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <?php renderNavbar('🙏 Manajemen Kerohanian'); ?>
    
    <div class="container">
        <div class="header">
            <div>
                <h1>Daftar Kerohanian</h1>
                <p style="color: #666; margin-top: 5px;">Total: <strong><?php echo $total_kerohanian; ?> pembukaan</strong></p>
            </div>
            <div class="header-right">
                <button onclick="window.print()" class="btn btn-print" title="Cetak Daftar">
                    🖨️ Print
                </button>
                <a href="kerohanian_import.php" class="btn btn-success" title="Import dari CSV">
                    📥 Import CSV
                </a>
                <?php if (!$is_readonly): ?>
                <a href="kerohanian_tambah.php" class="btn btn-primary" title="Tambah Kerohanian Baru">
                    ➕ Tambah
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="search-filter">
            <form method="GET" style="display: flex; gap: 10px; flex: 1; flex-wrap: wrap; align-items: center;">
                <input type="text" name="search" placeholder="Cari nama atau no anggota..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">🔍 Cari</button>
                <a href="kerohanian.php" class="btn" style="background: #6c757d; color: white;">Reset</a>
            </form>
        </div>
        
        <div class="table-container">
            <?php if ($total_kerohanian > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>No Anggota</th>
                        <th>Nama Anggota</th>
                        <th>Tingkat</th>
                        <th>Tanggal Pembukaan</th>
                        <th>Lokasi</th>
                        <th>Penyelenggara</th>
                        <th>Unit/Ranting</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['no_anggota']; ?></td>
                        <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                        <td>
                            <span class="badge">
                                <?php echo singkatTingkat($row['nama_tingkat'] ?? '-'); ?>
                            </span>
                        </td>
                        <td><?php echo formatTanggal($row['tanggal_pembukaan']); ?></td>
                        <td><?php echo htmlspecialchars($row['lokasi'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['pembuka_nama'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_ranting'] ?? '-'); ?></td>
                        <td>
                            <div class="action-icons">
                                <a href="kerohanian_detail.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-view" title="Lihat">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (!$is_readonly): ?>
                                <a href="kerohanian_edit.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="kerohanian_hapus.php?id=<?php echo $row['id']; ?>" class="icon-btn icon-delete" title="Hapus" onclick="return confirm('Yakin hapus data ini?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <p>🔍 Tidak ada data kerohanian</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>