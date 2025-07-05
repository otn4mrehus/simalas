<?php
// Pastikan tidak ada output sebelum session_start()
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inisialisasi variabel $page dan $action dengan nilai default
$page = isset($_GET['page']) ? $_GET['page'] : 'login';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Koneksi ke database
$host = "localhost";
$user = "root";
$pass = "";
$db = "presensi_siswa";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Periksa apakah tabel presensi memiliki kolom lokasi
$check_columns = $conn->query("SHOW COLUMNS FROM presensi LIKE 'lokasi_masuk'");
if ($check_columns->num_rows == 0) {
    // Tambahkan kolom jika belum ada
    $conn->query("ALTER TABLE presensi ADD COLUMN lokasi_masuk VARCHAR(50) DEFAULT NULL AFTER status_masuk");
    $conn->query("ALTER TABLE presensi ADD COLUMN lokasi_pulang VARCHAR(50) DEFAULT NULL AFTER status_pulang");
}

// Buat tabel pengaturan jika belum ada
$conn->query("CREATE TABLE IF NOT EXISTS pengaturan (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    radius INT(11) NOT NULL COMMENT 'dalam meter'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_nopad_ci");

// Buat tabel terlambat jika belum ada
$conn->query("CREATE TABLE IF NOT EXISTS terlambat (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(20) NOT NULL,
    tanggal DATE NOT NULL,
    keterangan TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_nopad_ci");

// Buat tabel absensi_izin jika belum ada
$conn->query("CREATE TABLE IF NOT EXISTS absensi_izin (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(20) NOT NULL,
    tanggal DATE NOT NULL,
    jenis ENUM('sakit', 'ijin') NOT NULL,
    keterangan TEXT,
    lampiran VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'diterima', 'ditolak') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_nopad_ci");

// Cek apakah ada data pengaturan
$sql_pengaturan = "SELECT * FROM pengaturan ORDER BY id DESC LIMIT 1";
$result_pengaturan = $conn->query($sql_pengaturan);

if ($result_pengaturan->num_rows > 0) {
    $pengaturan = $result_pengaturan->fetch_assoc();
    $latSekolah = $pengaturan['latitude'];
    $lngSekolah = $pengaturan['longitude'];
    $radiusSekolah = $pengaturan['radius'];
} else {
    // Default jika tidak ada pengaturan
    $latSekolah = -6.4105;
    $lngSekolah = 106.8440;
    $radiusSekolah = 100;
    
    // Insert data default
    $conn->query("INSERT INTO pengaturan (latitude, longitude, radius) VALUES ($latSekolah, $lngSekolah, $radiusSekolah)");
}

// Fungsi untuk kompres gambar
function compressImage($source, $destination, $quality) {
    if (!file_exists($source)) {
        return false;
    }
    
    $info = getimagesize($source);
    
    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
        imagejpeg($image, $destination, $quality);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
        imagepng($image, $destination, round(9 * $quality / 100));
    } else {
        return false;
    }
    
    return true;
}

// Fungsi untuk menghitung jarak
function hitungJarak($lat1, $lon1, $lat2, $lon2) {
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    return ($miles * 1609.344); // Meter
}

// Proses login siswa
if (isset($_POST['login'])) {
    $nis = $_POST['nis'];
    $password = $_POST['password'];
    
    $sql = "SELECT * FROM siswa WHERE nis = '$nis'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['nis'] = $nis;
            $_SESSION['nama'] = $row['nama'];
            header('Location: index.php?page=menu');
            exit();
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "NIS tidak ditemukan!";
    }
}

// Proses login admin
if (isset($_POST['admin_login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Contoh admin: username = admin, password = admin123
    if ($username == 'admin' && $password == 'admin123') {
        $_SESSION['admin'] = true;
        header('Location: index.php?page=admin');
        exit();
    } else {
        $error_admin = "Username atau password admin salah!";
    }
}

// Proses CRUD Siswa
$nis_edit = isset($_GET['nis']) ? $_GET['nis'] : '';

if ($action == 'edit_siswa' && $nis_edit != '') {
    $sql = "SELECT * FROM siswa WHERE nis = '$nis_edit'";
    $result = $conn->query($sql);
    $siswa_edit = $result->fetch_assoc();
}

if (isset($_POST['save_siswa'])) {
    $nis = $_POST['nis'];
    $nama = $_POST['nama'];
    $password = $_POST['password'];
    
    if (!empty($password)) {
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE siswa SET nama = '$nama', password = '$password_hashed' WHERE nis = '$nis'";
    } else {
        $sql = "UPDATE siswa SET nama = '$nama' WHERE nis = '$nis'";
    }
    
    if ($conn->query($sql) === TRUE) {
        $success_siswa = "Data siswa berhasil diperbarui!";
        // Redirect untuk menghindari form resubmission
        header('Location: index.php?page=admin#siswa');
        exit();
    } else {
        $error_siswa = "Error: " . $conn->error;
    }
}

if (isset($_POST['delete_siswa'])) {
    $nis = $_POST['nis'];
    $sql = "DELETE FROM siswa WHERE nis = '$nis'";
    
    if ($conn->query($sql) === TRUE) {
        $success_siswa = "Siswa berhasil dihapus!";
        // Redirect untuk menghindari form resubmission
        header('Location: index.php?page=admin#siswa');
        exit();
    } else {
        $error_siswa = "Error: " . $conn->error;
    }
}

if (isset($_POST['add_siswa'])) {
    $nis = $_POST['nis'];
    $nama = $_POST['nama'];
    $password = $_POST['password'];
    
    $password_hashed = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO siswa (nis, nama, password) VALUES ('$nis', '$nama', '$password_hashed')";
    
    if ($conn->query($sql) === TRUE) {
        $success_siswa = "Siswa berhasil ditambahkan!";
        // Redirect untuk menghindari form resubmission
        header('Location: index.php?page=admin#siswa');
        exit();
    } else {
        $error_siswa = "Error: " . $conn->error;
    }
}

// Proses simpan pengaturan
if (isset($_POST['save_pengaturan'])) {
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    $radius = $_POST['radius'];
    
    // Update atau insert
    $check = $conn->query("SELECT id FROM pengaturan ORDER BY id ASC LIMIT 1");
    if ($check->num_rows > 0) {
        $row = $check->fetch_assoc();
        $id = $row['id'];
        $sql = "UPDATE pengaturan SET latitude='$latitude', longitude='$longitude', radius='$radius' WHERE id=$id";
    } else {
        $sql = "INSERT INTO pengaturan (latitude, longitude, radius) VALUES ('$latitude', '$longitude', '$radius')";
    }
    
    if ($conn->query($sql) === TRUE) {
        $success_pengaturan = "Pengaturan berhasil disimpan!";
        header('Location: index.php?page=admin#pengaturan');
        exit();
    } else {
        $error_pengaturan = "Error: " . $conn->error;
    }
}

// Proses simpan keterangan terlambat
if (isset($_POST['save_keterangan_terlambat'])) {
    $nis = $_SESSION['nis'];
    $tanggal = date('Y-m-d');
    $keterangan = $_POST['keterangan'];
    
    $sql = "INSERT INTO terlambat (nis, tanggal, keterangan) VALUES ('$nis', '$tanggal', '$keterangan')";
    
    if ($conn->query($sql) === TRUE) {
        $_SESSION['show_terlambat_modal'] = false;
        header('Location: index.php?page=presensi');
        exit();
    } else {
        $error = "Error menyimpan keterangan: " . $conn->error;
    }
}

// Proses pengajuan izin
if (isset($_POST['ajukan_izin'])) {
    $nis = $_SESSION['nis'];
    $tanggal = $_POST['tanggal'];
    $jenis = $_POST['jenis'];
    $keterangan = $_POST['keterangan'];
    $lampiran = '';
    
    // Proses lampiran jika ada
    if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['lampiran'];
        $namaFile = "izin-$nis-" . date('YmdHis') . "-" . basename($file['name']);
        
        $baseDir = __DIR__;
        $lampiranDir = $baseDir . '/uploads/lampiran';
        
        // Buat direktori jika belum ada
        if (!file_exists($lampiranDir)) {
            if (!mkdir($lampiranDir, 0777, true)) {
                $error_izin = "Gagal membuat folder untuk menyimpan lampiran!";
            }
        }
        
        $targetFile = $lampiranDir . '/' . $namaFile;
        
        // Pindahkan file upload
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            $lampiran = $namaFile;
        }
    }
    
    $sql = "INSERT INTO absensi_izin (nis, tanggal, jenis, keterangan, lampiran) 
            VALUES ('$nis', '$tanggal', '$jenis', '$keterangan', '$lampiran')";
    
    if ($conn->query($sql) === TRUE) {
        $success_izin = "Pengajuan izin berhasil dikirim!";
    } else {
        $error_izin = "Error: " . $conn->error;
    }
}

// Proses ubah status izin (admin)
if (isset($_POST['update_status_izin'])) {
    $id = $_POST['id'];
    $status = $_POST['status'];
    
    $sql = "UPDATE absensi_izin SET status = '$status' WHERE id = $id";
    
    if ($conn->query($sql) === TRUE) {
        $success_admin = "Status izin berhasil diperbarui!";
    } else {
        $error_admin = "Error: " . $conn->error;
    }
}

// Proses presensi
if (isset($_POST['presensi'])) {
    $nis = $_SESSION['nis'];
    $jenis = $_POST['jenis_presensi'];
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    $tanggal = date('Y-m-d');
    
    // Gunakan pengaturan dari database
    $jarak = hitungJarak($latSekolah, $lngSekolah, $latitude, $longitude);
    
    // Radius dari database
    if ($jarak > $radiusSekolah) {
        $error = "Anda berada di luar area sekolah! (".round($jarak)." m dari pusat)";
    } else {
        // Cek apakah sudah ada presensi hari ini
        $cek_sql = "SELECT * FROM presensi WHERE nis = '$nis' AND tanggal = '$tanggal'";
        $cek_result = $conn->query($cek_sql);
        $row_presensi = $cek_result->fetch_assoc();
        
        // Jika presensi masuk
        if ($jenis == 'masuk') {
            // Jika sudah ada presensi masuk hari ini
            if ($row_presensi && $row_presensi['jam_masuk']) {
                $error = "Anda sudah melakukan presensi masuk hari ini!";
            } else {
                // Proses foto
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
                    $foto = $_FILES['foto'];
                    $namaFile = "foto-$nis-" . date('H.i-d.m.Y') . ".jpg";
                    
                    // Gunakan path absolute
                    $baseDir = __DIR__;
                    $fotoDir = $baseDir . '/uploads/foto';
                    
                    // Buat direktori jika belum ada
                    if (!file_exists($fotoDir)) {
                        if (!mkdir($fotoDir, 0777, true)) {
                            $error = "Gagal membuat folder untuk menyimpan foto!";
                        }
                    }
                    
                    $targetFile = $fotoDir . '/' . $namaFile;
                    
                    // Pindahkan file upload
                    if (move_uploaded_file($foto['tmp_name'], $targetFile)) {
                        // Kompres gambar ke 500KB
                        if (compressImage($targetFile, $targetFile, 60)) {
                            // Simpan data presensi
                            $waktu = date('H:i:s');
                            
                            // Tentukan status kehadiran
                            $status_masuk = 'tepat waktu';
                            if (strtotime($waktu) > strtotime('07:30:00')) {
                                $status_masuk = 'terlambat';
                                $_SESSION['show_terlambat_modal'] = true;
                            }
                            
                            // Simpan lokasi presensi
                            $lokasi = "$latitude,$longitude";
                            
                            if ($row_presensi) {
                                // Update jika sudah ada (mungkin hanya ada pulang sebelumnya, tapi seharusnya tidak)
                                $update_sql = "UPDATE presensi SET 
                                    jam_masuk = '$waktu', 
                                    foto_masuk = '$namaFile', 
                                    status_masuk = '$status_masuk',
                                    lokasi_masuk = '$lokasi' 
                                    WHERE nis = '$nis' AND tanggal = '$tanggal'";
                                    
                                if ($conn->query($update_sql) === TRUE) {
                                    $success = "Presensi masuk berhasil dicatat!";
                                } else {
                                    $error = "Error update: " . $conn->error;
                                }
                            } else {
                                // Insert baru
                                $insert_sql = "INSERT INTO presensi (nis, tanggal, jam_masuk, foto_masuk, status_masuk, lokasi_masuk) 
                                        VALUES ('$nis', '$tanggal', '$waktu', '$namaFile', '$status_masuk', '$lokasi')";
                                        
                                if ($conn->query($insert_sql) === TRUE) {
                                    $success = "Presensi masuk berhasil dicatat!";
                                } else {
                                    $error = "Error insert: " . $conn->error;
                                }
                            }
                        } else {
                            $error = "Gagal mengkompres foto!";
                        }
                    } else {
                        $error = "Gagal menyimpan foto! Pastikan folder 'uploads/foto' memiliki izin tulis.";
                    }
                } else {
                    $error = "Foto tidak terupload! Error: " . $_FILES['foto']['error'];
                }
            }
        } else { // presensi pulang
            // Cek apakah sudah ada presensi masuk hari ini
            if (!$row_presensi || !$row_presensi['jam_masuk']) {
                $error = "Anda belum melakukan presensi masuk hari ini!";
            } else if ($row_presensi['jam_pulang']) {
                $error = "Anda sudah melakukan presensi pulang hari ini!";
            } else {
                // Proses foto
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
                    $foto = $_FILES['foto'];
                    $namaFile = "foto-$nis-" . date('H.i-d.m.Y') . ".jpg";
                    
                    // Gunakan path absolute
                    $baseDir = __DIR__;
                    $fotoDir = $baseDir . '/uploads/foto';
                    
                    // Buat direktori jika belum ada
                    if (!file_exists($fotoDir)) {
                        if (!mkdir($fotoDir, 0777, true)) {
                            $error = "Gagal membuat folder untuk menyimpan foto!";
                        }
                    }
                    
                    $targetFile = $fotoDir . '/' . $namaFile;
                    
                    // Pindahkan file upload
                    if (move_uploaded_file($foto['tmp_name'], $targetFile)) {
                        // Kompres gambar ke 500KB
                        if (compressImage($targetFile, $targetFile, 60)) {
                            // Simpan data presensi
                            $waktu = date('H:i:s');
                            
                            $status_pulang = 'tepat waktu';
                            if (strtotime($waktu) < strtotime('15:30:00')) {
                                $status_pulang = 'cepat';
                            }
                            
                            // Simpan lokasi presensi
                            $lokasi = "$latitude,$longitude";
                            
                            $update_sql = "UPDATE presensi SET 
                                jam_pulang = '$waktu', 
                                foto_pulang = '$namaFile', 
                                status_pulang = '$status_pulang',
                                lokasi_pulang = '$lokasi' 
                                WHERE nis = '$nis' AND tanggal = '$tanggal'";
                            
                            if ($conn->query($update_sql) === TRUE) {
                                $success = "Presensi pulang berhasil dicatat!";
                            } else {
                                $error = "Error: " . $conn->error;
                            }
                        } else {
                            $error = "Gagal mengkompres foto!";
                        }
                    } else {
                        $error = "Gagal menyimpan foto! Pastikan folder 'uploads/foto' memiliki izin tulis.";
                    }
                } else {
                    $error = "Foto tidak terupload! Error: " . $_FILES['foto']['error'];
                }
            }
        }
    }
}

// Tangani logout
if ($page == 'logout') {
    session_destroy();
    header('Location: index.php?page=login');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presensi Siswa SMK</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        .container {
            max-width: 100%;
            padding: 15px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            padding: 20px;
            margin-bottom: 15px;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-3px);
        }
        
        .header {
            background: linear-gradient(135deg, #3498db, #1a5276);
            color: white;
            padding: 15px 0;
            text-align: center;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header h1 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 0.85rem;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
        }
        
        input, button, select, textarea {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ddd;
            font-size: 0.95rem;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        button {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        
        button:hover {
            background: linear-gradient(135deg, #2980b9, #1a5276);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #7f8c8d, #95a5a6);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #27ae60, #219653);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #e67e22, #d35400);
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.85rem;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.85rem;
        }
        
        .camera-container {
            position: relative;
            width: 100%;
            max-width: 100%;
            margin: 15px auto;
            border-radius: 10px;
            overflow: hidden;
            background: #000;
            aspect-ratio: 4/3;
        }
        
        #video {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }
        
        .camera-controls {
            position: absolute;
            bottom: 10px;
            left: 0;
            right: 0;
            text-align: center;
        }
        
        .btn-capture {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 3px solid #3498db;
            cursor: pointer;
        }
        
        .presensi-info {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            background: #e8f4fc;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            gap: 8px;
        }
        
        .info-item {
            text-align: center;
            flex: 1 1 calc(33.333% - 8px);
            min-width: 100px;
            padding: 8px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .info-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2980b9;
            margin-bottom: 4px;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #7f8c8d;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.85rem;
        }
        
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        tr:hover {
            background-color: #f5f7fa;
        }
        
        .foto-presensi {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
        }
        
        .status-tepat {
            color: #27ae60;
            font-weight: 600;
        }
        
        .status-telambat {
            color: #e74c3c;
            font-weight: 600;
        }
        
        .status-cepat {
            color: #f39c12;
            font-weight: 600;
        }
        
        .status-pending {
            color: #3498db;
            font-weight: 600;
        }
        
        .status-diterima {
            color: #27ae60;
            font-weight: 600;
        }
        
        .status-ditolak {
            color: #e74c3c;
            font-weight: 600;
        }
        
        .footer {
            text-align: center;
            padding: 15px;
            color: #7f8c8d;
            font-size: 0.8rem;
            margin-top: 15px;
        }
        
        .nav-tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 15px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .nav-tabs a {
            padding: 10px 15px;
            text-decoration: none;
            color: #2c3e50;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            white-space: nowrap;
            font-size: 0.9rem;
        }
        
        .nav-tabs a.active {
            border-bottom: 3px solid #3498db;
            color: #3498db;
        }
        
        .nav-tabs a:hover {
            color: #3498db;
        }
        
        .tabs-container {
            margin-bottom: 15px;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .presensi-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 15px 0;
        }
        
        @media (min-width: 480px) {
            .presensi-options {
                flex-direction: row;
            }
        }
        
        .presensi-option {
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            background: #f5f7fa;
            border: 2px solid #ddd;
            transition: all 0.3s;
            flex: 1;
        }
        
        .presensi-option.active {
            border-color: #3498db;
            background: #e8f4fc;
        }
        
        .presensi-option.masuk.active {
            border-color: #27ae60;
            background: #e8f6f0;
        }
        
        .presensi-option.pulang.active {
            border-color: #e74c3c;
            background: #fceae8;
        }
        
        .file-input-container {
            margin: 15px 0;
            text-align: center;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 10px 16px;
            background: #3498db;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 0.9rem;
        }
        
        .file-input-label:hover {
            background: #2980b9;
        }
        
        #foto-input {
            display: none;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 20px;
            color: #aaa;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        .modal-title {
            margin-bottom: 15px;
            text-align: center;
            color: #2c3e50;
            font-size: 1.2rem;
        }
        
        .delete-confirm {
            text-align: center;
            padding: 15px;
        }
        
        .delete-confirm p {
            margin-bottom: 15px;
            font-size: 1rem;
        }
        
        .btn-group {
            display: flex;
            gap: 8px;
            flex-direction: column;
        }
        
        @media (min-width: 480px) {
            .btn-group {
                flex-direction: row;
            }
        }
        
        .btn-group button {
            flex: 1;
        }
        
        /* Responsive table */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Responsive adjustments */
        @media (max-width: 767px) {
            .container {
                padding: 10px;
            }
            
            .card {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 1.3rem;
            }
            
            .header p {
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 8px 10px;
                font-size: 0.8rem;
            }
            
            .foto-presensi {
                width: 45px;
                height: 45px;
            }
            
            .info-item {
                flex: 1 1 calc(50% - 8px);
            }
            
            .info-value {
                font-size: 1rem;
            }
            
            .presensi-option {
                padding: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .info-item {
                flex: 1 1 100%;
            }
            
            .nav-tabs a {
                padding: 8px 12px;
                font-size: 0.85rem;
            }
            
            .file-input-label {
                padding: 8px 12px;
                font-size: 0.85rem;
            }
            
            .modal-content {
                padding: 15px;
            }
        }
        
        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #b8c2cc;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #3498db;
        }
        
        /* Action buttons in table */
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .action-buttons button {
            padding: 8px 10px;
            font-size: 0.8rem;
        }
        
        .lokasi-link {
            display: inline-block;
            padding: 5px 10px;
            background: #3498db;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.8rem;
            margin-top: 5px;
        }
        
        .lokasi-link:hover {
            background: #2980b9;
        }
        
        /* Ikon tombol kecil */
        .btn-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        
        .btn-icon-edit {
            background: #3498db;
            color: white;
            border: none;
        }
        
        .btn-icon-delete {
            background: #e74c3c;
            color: white;
            border: none;
            margin-left: 5px;
        }
        
        /* Form edit sederhana */
        .edit-form {
            padding: 20px;
        }
        
        .edit-form .form-group {
            margin-bottom: 15px;
        }
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .loading-spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .menu-options {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 20px;
        }
        
        .menu-option {
            display: flex;
            align-items: center;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
        }
        
        .menu-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }
        
        .menu-icon {
            font-size: 2rem;
            margin-right: 20px;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
        }
        
        .menu-kehadiran .menu-icon {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }
        
        .menu-ketidakhadiran .menu-icon {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        .menu-text {
            flex: 1;
        }
        
        .menu-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .menu-description {
            font-size: 0.9rem;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-user-check"></i> Presensi Siswa SMK</h1>
        <p>Sistem Presensi dengan Teknologi Geofencing</p>
    </div>
    
    <div class="container">
        <?php if ($page == 'login'): ?>
            <!-- Halaman Login -->
            <div class="card">
                <h2 style="text-align: center; margin-bottom: 15px; font-size: 1.3rem;">Login Siswa</h2>
                
                <?php if (isset($error)): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="nis"><i class="fas fa-id-card"></i> Nomor Induk Siswa (NIS)</label>
                        <input type="text" id="nis" name="nis" required placeholder="Masukkan NIS Anda">
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password</label>
                        <input type="password" id="password" name="password" required placeholder="Masukkan password">
                    </div>
                    
                    <button type="submit" name="login"><i class="fas fa-sign-in-alt"></i> Login</button>
                </form>
            </div>
            
            <div class="card">
                <h2 style="text-align: center; margin-bottom: 15px; font-size: 1.3rem;">Login Admin</h2>
                
                <?php if (isset($error_admin)): ?>
                    <div class="error"><?php echo $error_admin; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="username" name="username" required placeholder="Masukkan username admin">
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_password"><i class="fas fa-lock"></i> Password</label>
                        <input type="password" id="admin_password" name="password" required placeholder="Masukkan password admin">
                    </div>
                    
                    <button type="submit" name="admin_login"><i class="fas fa-sign-in-alt"></i> Login Admin</button>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if ($page == 'menu' && isset($_SESSION['nis'])): ?>
            <!-- Menu Utama Siswa -->
            <div class="presensi-info">
                <div class="info-item">
                    <div class="info-value"><?php echo date('H:i'); ?></div>
                    <div class="info-label"><i class="fas fa-clock"></i> Waktu Sekarang</div>
                </div>
                <div class="info-item">
                    <div class="info-value"><?php echo $_SESSION['nama']; ?></div>
                    <div class="info-label"><i class="fas fa-user"></i> Nama Siswa</div>
                </div>
                <div class="info-item">
                    <div class="info-value"><?php echo $_SESSION['nis']; ?></div>
                    <div class="info-label"><i class="fas fa-id-card"></i> NIS</div>
                </div>
            </div>
            
            <div class="card">
                <h2 style="text-align: center; margin-bottom: 20px; font-size: 1.3rem;">Menu Utama</h2>
                
                <div class="menu-options">
                    <a href="index.php?page=presensi" class="menu-option menu-kehadiran">
                        <div class="menu-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="menu-text">
                            <div class="menu-title">Kehadiran (Presensi)</div>
                            <div class="menu-description">Presensi masuk dan pulang dengan verifikasi lokasi dan foto</div>
                        </div>
                        <div>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                    
                    <a href="index.php?page=izin" class="menu-option menu-ketidakhadiran">
                        <div class="menu-icon">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="menu-text">
                            <div class="menu-title">Ketidakhadiran (Ijin)</div>
                            <div class="menu-description">Ajukan ijin tidak masuk karena sakit atau keperluan lainnya</div>
                        </div>
                        <div>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                </div>
                
                <div style="text-align: center; margin-top: 25px;">
                    <a href="?page=logout" style="color: #e74c3c; text-decoration: none; font-size: 0.9rem;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($page == 'presensi' && isset($_SESSION['nis'])): ?>
            <!-- Halaman Presensi -->
            <?php
            // Ambil rekap absen bulan ini untuk siswa ini
            $nis_siswa = $_SESSION['nis'];
            $bulan_ini = date('m');
            $tahun_ini = date('Y');

            $sql_rekap = "SELECT 
                COUNT(*) AS total_hadir,
                SUM(CASE WHEN status_masuk = 'tepat waktu' THEN 1 ELSE 0 END) AS tepat_waktu,
                SUM(CASE WHEN status_masuk = 'terlambat' THEN 1 ELSE 0 END) AS terlambat,
                SUM(CASE WHEN jam_pulang IS NOT NULL THEN 1 ELSE 0 END) AS pulang
                FROM presensi 
                WHERE nis = '$nis_siswa' 
                AND MONTH(tanggal) = '$bulan_ini' 
                AND YEAR(tanggal) = '$tahun_ini'";

            $result_rekap = $conn->query($sql_rekap);
            $rekap = $result_rekap->fetch_assoc();

            $total_hadir = $rekap['total_hadir'] ?? 0;
            $tepat_waktu = $rekap['tepat_waktu'] ?? 0;
            $terlambat = $rekap['terlambat'] ?? 0;
            $pulang = $rekap['pulang'] ?? 0;
            ?>
            
            <div class="presensi-info">
                <div class="info-item">
                    <div class="info-value"><?php echo date('H:i'); ?></div>
                    <div class="info-label"><i class="fas fa-clock"></i> Waktu Sekarang</div>
                </div>
                <div class="info-item">
                    <div class="info-value"><?php echo $_SESSION['nama']; ?></div>
                    <div class="info-label"><i class="fas fa-user"></i> Nama Siswa</div>
                </div>
                <div class="info-item">
                    <div class="info-value"><?php echo $_SESSION['nis']; ?></div>
                    <div class="info-label"><i class="fas fa-id-card"></i> NIS</div>
                </div>
            </div>
            
            <div class="card">
                <h3 style="text-align: center; margin-bottom: 15px; font-size: 1.1rem;"><i class="fas fa-chart-bar"></i> Rekap Absen Bulan Ini</h3>
                <div class="presensi-info">
                    <div class="info-item">
                        <div class="info-value"><?php echo $total_hadir; ?></div>
                        <div class="info-label">Total Hadir</div>
                    </div>
                    <div class="info-item">
                        <div class="info-value"><?php echo $tepat_waktu; ?></div>
                        <div class="info-label">Tepat Waktu</div>
                    </div>
                    <div class="info-item">
                        <div class="info-value"><?php echo $terlambat; ?></div>
                        <div class="info-label">Terlambat</div>
                    </div>
                    <div class="info-item">
                        <div class="info-value"><?php echo $pulang; ?></div>
                        <div class="info-label">Pulang</div>
                    </div>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error">
                    <strong>Error:</strong> <?php echo $error; ?>
                    <?php if (isset($foto) && is_array($foto)): ?>
                        <div style="margin-top: 8px; font-size: 11px;">
                            <div>Nama File: <?php echo $foto['name']; ?></div>
                            <div>Ukuran: <?php echo round($foto['size']/1024, 2); ?> KB</div>
                            <div>Tipe: <?php echo $foto['type']; ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h2 style="text-align: center; margin-bottom: 15px; font-size: 1.3rem;"><i class="fas fa-camera"></i> Presensi Siswa</h2>
                
                <form method="POST" enctype="multipart/form-data" id="presensi-form">
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                    <input type="hidden" name="jenis_presensi" id="jenis_presensi" value="masuk">
                    
                    <!-- Opsi Presensi -->
                    <div class="presensi-options">
                        <div class="presensi-option masuk active" data-jenis="masuk">
                            <i class="fas fa-sign-in-alt fa-lg"></i>
                            <div>Presensi Masuk</div>
                        </div>
                        <div class="presensi-option pulang" data-jenis="pulang">
                            <i class="fas fa-sign-out-alt fa-lg"></i>
                            <div>Presensi Pulang</div>
                        </div>
                    </div>
                    
                    <!-- Preview Kamera -->
                    <div class="camera-container">
                        <video id="video" autoplay playsinline></video>
                        <div class="camera-controls">
                            <button type="button" id="btn-capture" class="btn-capture">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                    </div>
                    <canvas id="canvas" style="display: none;"></canvas>
                    
                    <!-- Input File untuk Foto -->
                    <div class="file-input-container">
                        <label for="foto-input" class="file-input-label">
                            <i class="fas fa-camera"></i> Ambil Foto Wajah
                        </label>
                        <input type="file" name="foto" id="foto-input" accept="image/*" capture="user" required>
                    </div>
                    
                    <!-- Info Lokasi -->
                    <div id="lokasi-info" style="margin-top: 12px; padding: 10px; background: #f8f9fa; border-radius: 8px; text-align: center; font-size: 0.9rem;">
                        <i class="fas fa-sync fa-spin"></i> Mengambil lokasi...
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" name="presensi" id="btn-submit" disabled class="btn-success" style="width: 100%; margin-top: 10px;">
                        <i class="fas fa-paper-plane"></i> Kirim Presensi
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 15px;">
                    <a href="index.php?page=menu" style="color: #3498db; text-decoration: none; font-size: 0.9rem; margin-right: 15px;">
                        <i class="fas fa-arrow-left"></i> Kembali ke Menu
                    </a>
                    <a href="?page=logout" style="color: #e74c3c; text-decoration: none; font-size: 0.9rem;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <!-- Modal Keterangan Terlambat -->
            <?php if (isset($_SESSION['show_terlambat_modal']) && $_SESSION['show_terlambat_modal']): ?>
                <div id="terlambatModal" class="modal" style="display: block;">
                    <div class="modal-content">
                        <h3 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Keterangan Terlambat</h3>
                        <p style="margin-bottom: 15px; text-align: center;">
                            Anda terlambat melakukan presensi. Silakan berikan keterangan alasan keterlambatan Anda.
                        </p>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label for="keterangan">Alasan Keterlambatan</label>
                                <textarea id="keterangan" name="keterangan" rows="4" required placeholder="Mohon jelaskan alasan keterlambatan Anda..."></textarea>
                            </div>
                            
                            <button type="submit" name="save_keterangan_terlambat" class="btn-warning">
                                <i class="fas fa-paper-plane"></i> Kirim Keterangan
                            </button>
                        </form>
                    </div>
                </div>
                
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        document.getElementById('terlambatModal').style.display = 'block';
                    });
                </script>
            <?php endif; ?>
            
            <script>
                // Inisialisasi variabel
                const video = document.getElementById('video');
                const canvas = document.getElementById('canvas');
                const ctx = canvas.getContext('2d');
                const btnCapture = document.getElementById('btn-capture');
                const fotoInput = document.getElementById('foto-input');
                const lokasiInfo = document.getElementById('lokasi-info');
                const latitudeInput = document.getElementById('latitude');
                const longitudeInput = document.getElementById('longitude');
                const btnSubmit = document.getElementById('btn-submit');
                const jenisPresensiInput = document.getElementById('jenis_presensi');
                const presensiOptions = document.querySelectorAll('.presensi-option');
                
                // Set ukuran canvas sesuai video
                function setCanvasSize() {
                    if (video.videoWidth > 0 && video.videoHeight > 0) {
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                    }
                }
                
                // Mengakses kamera
                if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    navigator.mediaDevices.getUserMedia({ 
                        video: { facingMode: 'user' } // Gunakan kamera depan
                    })
                    .then(function(stream) {
                        video.srcObject = stream;
                        video.addEventListener('loadedmetadata', function() {
                            setCanvasSize();
                        });
                    })
                    .catch(function(error) {
                        lokasiInfo.innerHTML = "Tidak dapat mengakses kamera: " + error.name;
                        console.error("Camera error: ", error);
                    });
                } else {
                    lokasiInfo.innerHTML = "Browser Anda tidak mendukung akses kamera";
                }
                
                // Geolocation
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(showPosition, showError, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    });
                } else {
                    lokasiInfo.innerHTML = "Geolocation tidak didukung oleh browser ini.";
                }
                
                function showPosition(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    latitudeInput.value = lat;
                    longitudeInput.value = lng;
                    
                    // Koordinat sekolah diambil dari PHP
                    const latSekolah = <?php echo $latSekolah; ?>;
                    const lngSekolah = <?php echo $lngSekolah; ?>;
                    const radiusSekolah = <?php echo $radiusSekolah; ?>;
                    
                    // Hitung jarak dalam meter
                    const jarak = hitungJarak(latSekolah, lngSekolah, lat, lng);
                    
                    if (jarak <= radiusSekolah) {
                        lokasiInfo.innerHTML = `<i class="fas fa-check-circle"></i> Anda berada di dalam area sekolah (${jarak.toFixed(0)} m dari pusat).`;
                        btnSubmit.disabled = false;
                    } else {
                        lokasiInfo.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Anda di LUAR area sekolah (${jarak.toFixed(0)} m dari pusat). Hanya bisa presensi dalam radius ${radiusSekolah} m.`;
                        btnSubmit.disabled = true;
                    }
                }
                
                function showError(error) {
                    let message = '';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            message = "Izin lokasi ditolak. Aktifkan izin lokasi untuk presensi.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = "Informasi lokasi tidak tersedia.";
                            break;
                        case error.TIMEOUT:
                            message = "Permintaan lokasi timeout.";
                            break;
                        case error.UNKNOWN_ERROR:
                            message = "Terjadi kesalahan tidak diketahui.";
                            break;
                    }
                    lokasiInfo.innerHTML = `<i class='fas fa-exclamation-circle'></i> ${message}`;
                    btnSubmit.disabled = true;
                }
                
                // Fungsi untuk menghitung jarak
                function hitungJarak(lat1, lon1, lat2, lon2) {
                    const R = 6371e3; // Radius bumi dalam meter
                    const φ1 = lat1 * Math.PI/180;
                    const φ2 = lat2 * Math.PI/180;
                    const Δφ = (lat2-lat1) * Math.PI/180;
                    const Δλ = (lon2-lon1) * Math.PI/180;
                    
                    const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                              Math.cos(φ1) * Math.cos(φ2) *
                              Math.sin(Δλ/2) * Math.sin(Δλ/2);
                    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                    
                    const distance = R * c;
                    return distance;
                }
                
                // Tombol ambil foto
                btnCapture.addEventListener('click', function() {
                    // Ambil gambar dari video
                    setCanvasSize();
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    
                    // Konversi ke blob dan set ke input file
                    canvas.toBlob(function(blob) {
                        const file = new File([blob], "presensi.jpg", {type: 'image/jpeg'});
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        fotoInput.files = dataTransfer.files;
                        
                        // Tampilkan notifikasi
                        alert('Foto telah diambil! Silakan kirim presensi.');
                    }, 'image/jpeg', 0.9);
                });
                
                // Pilihan jenis presensi
                presensiOptions.forEach(option => {
                    option.addEventListener('click', function() {
                        // Hapus active class dari semua opsi
                        presensiOptions.forEach(opt => opt.classList.remove('active'));
                        
                        // Tambahkan active class ke opsi yang dipilih
                        this.classList.add('active');
                        
                        // Set nilai jenis presensi
                        jenisPresensiInput.value = this.dataset.jenis;
                    });
                });
                
                // Form submission handler
                document.getElementById('presensi-form').addEventListener('submit', function(e) {
                    if (!fotoInput.files.length) {
                        e.preventDefault();
                        alert('Silakan ambil foto terlebih dahulu!');
                    } else if (btnSubmit.disabled) {
                        e.preventDefault();
                        alert('Lokasi Anda di luar area sekolah atau tidak valid!');
                    } else {
                        // Tampilkan loading indicator
                        const overlay = document.createElement('div');
                        overlay.className = 'loading-overlay';
                        overlay.innerHTML = '<div class="loading-spinner"></div>';
                        document.body.appendChild(overlay);
                        overlay.style.display = 'flex';
                    }
                });
            </script>
        <?php endif; ?>
        
        <?php if ($page == 'izin' && isset($_SESSION['nis'])): ?>
            <!-- Halaman Pengajuan Izin -->
            <div class="presensi-info">
                <div class="info-item">
                    <div class="info-value"><?php echo date('d/m/Y'); ?></div>
                    <div class="info-label"><i class="fas fa-calendar"></i> Tanggal</div>
                </div>
                <div class="info-item">
                    <div class="info-value"><?php echo $_SESSION['nama']; ?></div>
                    <div class="info-label"><i class="fas fa-user"></i> Nama Siswa</div>
                </div>
                <div class="info-item">
                    <div class="info-value"><?php echo $_SESSION['nis']; ?></div>
                    <div class="info-label"><i class="fas fa-id-card"></i> NIS</div>
                </div>
            </div>
            
            <?php if (isset($error_izin)): ?>
                <div class="error"><?php echo $error_izin; ?></div>
            <?php endif; ?>
            
            <?php if (isset($success_izin)): ?>
                <div class="success"><?php echo $success_izin; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h2 style="text-align: center; margin-bottom: 15px; font-size: 1.3rem;"><i class="fas fa-user-times"></i> Pengajuan Izin</h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="tanggal"><i class="fas fa-calendar"></i> Tanggal Izin</label>
                        <input type="date" id="tanggal" name="tanggal" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="jenis"><i class="fas fa-info-circle"></i> Jenis Izin</label>
                        <select id="jenis" name="jenis" required>
                            <option value="">-- Pilih Jenis Izin --</option>
                            <option value="sakit">Sakit</option>
                            <option value="ijin">Ijin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="keterangan"><i class="fas fa-comment"></i> Keterangan</label>
                        <textarea id="keterangan" name="keterangan" rows="4" required placeholder="Berikan keterangan alasan ketidakhadiran Anda..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="lampiran"><i class="fas fa-paperclip"></i> Lampiran (Opsional)</label>
                        <input type="file" id="lampiran" name="lampiran" accept="image/*,application/pdf">
                        <small style="display: block; margin-top: 5px; color: #7f8c8d;">Format: JPG, PNG, PDF (maks. 2MB)</small>
                    </div>
                    
                    <button type="submit" name="ajukan_izin" class="btn-warning">
                        <i class="fas fa-paper-plane"></i> Ajukan Izin
                    </button>
                </form>
                
                <div style="margin-top: 30px;">
                    <h3 style="margin-bottom: 15px; font-size: 1.1rem;"><i class="fas fa-history"></i> Riwayat Pengajuan Izin</h3>
                    
                    <?php
                    $nis_siswa = $_SESSION['nis'];
                    $sql = "SELECT * FROM absensi_izin WHERE nis = '$nis_siswa' ORDER BY tanggal DESC";
                    $result = $conn->query($sql);
                    
                    if ($result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Jenis</th>
                                        <th>Keterangan</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                            <td><?php echo ucfirst($row['jenis']); ?></td>
                                            <td><?php echo substr($row['keterangan'], 0, 50) . (strlen($row['keterangan']) > 50 ? '...' : ''); ?></td>
                                            <td>
                                                <?php if ($row['status'] == 'pending'): ?>
                                                    <span class="status-pending">Menunggu</span>
                                                <?php elseif ($row['status'] == 'diterima'): ?>
                                                    <span class="status-diterima">Diterima</span>
                                                <?php else: ?>
                                                    <span class="status-ditolak">Ditolak</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; padding: 15px; color: #7f8c8d;">Belum ada riwayat pengajuan izin</p>
                    <?php endif; ?>
                </div>
                
                <div style="text-align: center; margin-top: 25px;">
                    <a href="index.php?page=menu" style="color: #3498db; text-decoration: none; font-size: 0.9rem; margin-right: 15px;">
                        <i class="fas fa-arrow-left"></i> Kembali ke Menu
                    </a>
                    <a href="?page=logout" style="color: #e74c3c; text-decoration: none; font-size: 0.9rem;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($page == 'admin' && isset($_SESSION['admin'])): ?>
            <!-- Admin Dashboard -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <h2 style="font-size: 1.3rem;"><i class="fas fa-tachometer-alt"></i> Dashboard Admin</h2>
                <a href="?page=logout" style="color: #e74c3c; text-decoration: none; font-size: 0.9rem;"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
            
            <?php if (isset($success_siswa)): ?>
                <div class="success"><?php echo $success_siswa; ?></div>
            <?php endif; ?>
            <?php if (isset($error_siswa)): ?>
                <div class="error"><?php echo $error_siswa; ?></div>
            <?php endif; ?>
            <?php if (isset($success_pengaturan)): ?>
                <div class="success"><?php echo $success_pengaturan; ?></div>
            <?php endif; ?>
            <?php if (isset($error_pengaturan)): ?>
                <div class="error"><?php echo $error_pengaturan; ?></div>
            <?php endif; ?>
            <?php if (isset($success_admin)): ?>
                <div class="success"><?php echo $success_admin; ?></div>
            <?php endif; ?>
            <?php if (isset($error_admin)): ?>
                <div class="error"><?php echo $error_admin; ?></div>
            <?php endif; ?>
            
            <div class="tabs-container">
                <div class="nav-tabs">
                    <a href="#presensi" class="active">Data Presensi</a>
                    <a href="#siswa">Data Siswa</a>
                    <a href="#terlambat">Data Terlambat</a>
                    <a href="#pengajuan">Pengajuan Izin</a>
                    <a href="#pengaturan">Pengaturan</a>
                    <a href="#rekap">Rekap Absen</a>
                </div>
                
                <div id="presensi" class="tab-content active">
                    <div class="card">
                        <h3 style="margin-bottom: 12px; font-size: 1.1rem;"><i class="fas fa-list"></i> Data Presensi Siswa</h3>
                        
                        <?php 
                        $sql = "SELECT p.*, s.nama FROM presensi p JOIN siswa s ON p.nis = s.nis ORDER BY p.tanggal DESC";
                        $result = $conn->query($sql);
                        
                        if ($result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>NIS</th>
                                            <th>Nama</th>
                                            <th>Masuk</th>
                                            <th>Pulang</th>
                                            <th>Foto Masuk</th>
                                            <th>Foto Pulang</th>
                                            <th>Status</th>
                                            <th>Lokasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['tanggal']; ?></td>
                                                <td><?php echo $row['nis']; ?></td>
                                                <td><?php echo $row['nama']; ?></td>
                                                <td><?php echo $row['jam_masuk']; ?></td>
                                                <td><?php echo $row['jam_pulang'] ? $row['jam_pulang'] : '-'; ?></td>
                                                <td>
                                                    <?php if ($row['foto_masuk']): ?>
                                                        <img src="uploads/foto/<?php echo $row['foto_masuk']; ?>" class="foto-presensi">
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($row['foto_pulang']): ?>
                                                        <img src="uploads/foto/<?php echo $row['foto_pulang']; ?>" class="foto-presensi">
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-<?php 
                                                        echo ($row['status_masuk'] == 'tepat waktu') ? 'tepat' : 'telambat'; 
                                                    ?>">
                                                        <?php echo $row['status_masuk']; ?>
                                                    </span>
                                                    <br>
                                                    <span class="status-<?php 
                                                        echo ($row['status_pulang'] == 'tepat waktu') ? 'tepat' : 'cepat'; 
                                                    ?>">
                                                        <?php echo $row['status_pulang'] ? $row['status_pulang'] : '-'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($row['lokasi_masuk'])): ?>
                                                        <a href="https://www.google.com/maps/search/?api=1&query=<?= $row['lokasi_masuk'] ?>" 
                                                           target="_blank" class="lokasi-link">
                                                            <i class="fas fa-map-marker-alt"></i> Masuk
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($row['lokasi_pulang'])): ?>
                                                        <br>
                                                        <a href="https://www.google.com/maps/search/?api=1&query=<?= $row['lokasi_pulang'] ?>" 
                                                           target="_blank" class="lokasi-link">
                                                            <i class="fas fa-map-marker-alt"></i> Pulang
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; padding: 15px; color: #7f8c8d;">Tidak ada data presensi</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="siswa" class="tab-content">
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                            <h3 style="margin: 0; font-size: 1.1rem;"><i class="fas fa-users"></i> Data Siswa</h3>
                            <button class="btn-success" onclick="openModal('add')" style="padding: 10px 15px; font-size: 0.9rem;">
                                <i class="fas fa-plus"></i> Tambah Siswa
                            </button>
                        </div>
                        
                        <?php 
                        $sql = "SELECT * FROM siswa";
                        $result = $conn->query($sql);
                        
                        if ($result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>NIS</th>
                                            <th>Nama</th>
                                            <th>Password</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['nis']; ?></td>
                                                <td><?php echo $row['nama']; ?></td>
                                                <td>••••••••</td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn-icon btn-icon-edit" onclick="openModal('edit', '<?php echo $row['nis']; ?>')" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn-icon btn-icon-delete" onclick="openModal('delete', '<?php echo $row['nis']; ?>', '<?php echo $row['nama']; ?>')" title="Hapus">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; padding: 15px; color: #7f8c8d;">Tidak ada data siswa</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="terlambat" class="tab-content">
                    <div class="card">
                        <h3 style="margin-bottom: 12px; font-size: 1.1rem;"><i class="fas fa-clock"></i> Data Keterlambatan</h3>
                        
                        <?php 
                        $sql = "SELECT t.*, s.nama FROM terlambat t JOIN siswa s ON t.nis = s.nis ORDER BY t.tanggal DESC";
                        $result = $conn->query($sql);
                        
                        if ($result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>NIS</th>
                                            <th>Nama</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['tanggal']; ?></td>
                                                <td><?php echo $row['nis']; ?></td>
                                                <td><?php echo $row['nama']; ?></td>
                                                <td><?php echo $row['keterangan']; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; padding: 15px; color: #7f8c8d;">Tidak ada data keterlambatan</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="pengajuan" class="tab-content">
                    <div class="card">
                        <h3 style="margin-bottom: 12px; font-size: 1.1rem;"><i class="fas fa-file-alt"></i> Pengajuan Izin Siswa</h3>
                        
                        <?php 
                        $sql = "SELECT a.*, s.nama FROM absensi_izin a JOIN siswa s ON a.nis = s.nis ORDER BY a.tanggal DESC";
                        $result = $conn->query($sql);
                        
                        if ($result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>NIS</th>
                                            <th>Nama</th>
                                            <th>Jenis</th>
                                            <th>Keterangan</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['tanggal']; ?></td>
                                                <td><?php echo $row['nis']; ?></td>
                                                <td><?php echo $row['nama']; ?></td>
                                                <td><?php echo ucfirst($row['jenis']); ?></td>
                                                <td><?php echo substr($row['keterangan'], 0, 50) . (strlen($row['keterangan']) > 50 ? '...' : ''); ?></td>
                                                <td>
                                                    <?php if ($row['status'] == 'pending'): ?>
                                                        <span class="status-pending">Menunggu</span>
                                                    <?php elseif ($row['status'] == 'diterima'): ?>
                                                        <span class="status-diterima">Diterima</span>
                                                    <?php else: ?>
                                                        <span class="status-ditolak">Ditolak</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: flex; gap: 5px;">
                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                        <select name="status" style="padding: 5px; border-radius: 5px; font-size: 0.8rem;">
                                                            <option value="pending" <?php echo $row['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="diterima" <?php echo $row['status'] == 'diterima' ? 'selected' : ''; ?>>Diterima</option>
                                                            <option value="ditolak" <?php echo $row['status'] == 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                                                        </select>
                                                        <button type="submit" name="update_status_izin" class="btn-icon btn-icon-edit" title="Update">
                                                            <i class="fas fa-sync"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; padding: 15px; color: #7f8c8d;">Tidak ada pengajuan izin</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="pengaturan" class="tab-content">
                    <div class="card">
                        <h3 style="margin-bottom: 12px; font-size: 1.1rem;"><i class="fas fa-cog"></i> Pengaturan Geolokasi</h3>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label for="latitude">Latitude Sekolah</label>
                                <input type="text" id="latitude" name="latitude" value="<?php echo $latSekolah; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="longitude">Longitude Sekolah</label>
                                <input type="text" id="longitude" name="longitude" value="<?php echo $lngSekolah; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="radius">Radius (meter)</label>
                                <input type="number" id="radius" name="radius" value="<?php echo $radiusSekolah; ?>" required min="10">
                            </div>
                            
                            <button type="submit" name="save_pengaturan" class="btn-success">Simpan Pengaturan</button>
                        </form>
                    </div>
                </div>
                
                <div id="rekap" class="tab-content">
                    <div class="card">
                        <h3 style="margin-bottom: 12px; font-size: 1.1rem;"><i class="fas fa-chart-bar"></i> Rekap Absen Siswa</h3>
                        
                        <?php
                        $sql_rekap = "SELECT 
                            s.nis,
                            s.nama,
                            COUNT(p.id) AS total_hadir,
                            SUM(CASE WHEN p.status_masuk = 'tepat waktu' THEN 1 ELSE 0 END) AS tepat_waktu,
                            SUM(CASE WHEN p.status_masuk = 'terlambat' THEN 1 ELSE 0 END) AS terlambat,
                            SUM(CASE WHEN p.jam_pulang IS NOT NULL THEN 1 ELSE 0 END) AS pulang
                            FROM siswa s
                            LEFT JOIN presensi p ON s.nis = p.nis
                            GROUP BY s.nis, s.nama
                            ORDER BY s.nama";
                            
                        $result_rekap = $conn->query($sql_rekap);
                        ?>
                        
                        <?php if ($result_rekap->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>NIS</th>
                                            <th>Nama</th>
                                            <th>Total Hadir</th>
                                            <th>Tepat Waktu</th>
                                            <th>Terlambat</th>
                                            <th>Pulang</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result_rekap->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['nis']; ?></td>
                                                <td><?php echo $row['nama']; ?></td>
                                                <td><?php echo $row['total_hadir']; ?></td>
                                                <td><?php echo $row['tepat_waktu']; ?></td>
                                                <td><?php echo $row['terlambat']; ?></td>
                                                <td><?php echo $row['pulang']; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; padding: 15px; color: #7f8c8d;">Tidak ada data rekap</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Modal untuk CRUD Siswa -->
            <div id="crudModal" class="modal">
                <div class="modal-content">
                    <span class="close-modal" onclick="closeModal()">&times;</span>
                    <div id="modalContent"></div>
                </div>
            </div>
            
            <script>
                // Tab switching
                const tabs = document.querySelectorAll('.nav-tabs a');
                const tabContents = document.querySelectorAll('.tab-content');
                
                tabs.forEach(tab => {
                    tab.addEventListener('click', (e) => {
                        e.preventDefault();
                        const target = tab.getAttribute('href').substring(1);
                        
                        // Remove active class from all tabs and contents
                        tabs.forEach(t => t.classList.remove('active'));
                        tabContents.forEach(tc => tc.classList.remove('active'));
                        
                        // Add active class to current tab and content
                        tab.classList.add('active');
                        document.getElementById(target).classList.add('active');
                        
                        // Update URL hash
                        window.location.hash = target;
                    });
                });
                
                // Check hash on page load
                if (window.location.hash) {
                    const targetTab = document.querySelector(`.nav-tabs a[href="${window.location.hash}"]`);
                    if (targetTab) {
                        tabs.forEach(t => t.classList.remove('active'));
                        tabContents.forEach(tc => tc.classList.remove('active'));
                        
                        targetTab.classList.add('active');
                        document.querySelector(targetTab.getAttribute('href')).classList.add('active');
                    }
                }
                
                // Modal functions
                function openModal(action, nis = '', nama = '') {
                    const modal = document.getElementById('crudModal');
                    const modalContent = document.getElementById('modalContent');
                    
                    if (action === 'add') {
                        modalContent.innerHTML = `
                            <h3 class="modal-title"><i class="fas fa-user-plus"></i> Tambah Siswa Baru</h3>
                            <form method="POST">
                                <div class="form-group">
                                    <label for="add_nis">NIS</label>
                                    <input type="text" id="add_nis" name="nis" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="add_nama">Nama</label>
                                    <input type="text" id="add_nama" name="nama" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="add_password">Password</label>
                                    <input type="password" id="add_password" name="password" required>
                                </div>
                                
                                <button type="submit" name="add_siswa" class="btn-success">Simpan</button>
                            </form>
                        `;
                    } else if (action === 'edit') {
                        // AJAX untuk ambil data siswa
                        fetch(`index.php?page=admin&action=edit_siswa&nis=${nis}`)
                            .then(response => response.text())
                            .then(data => {
                                modalContent.innerHTML = `
                                    <div class="edit-form">
                                        <h3 class="modal-title"><i class="fas fa-user-edit"></i> Edit Siswa</h3>
                                        ${data}
                                    </div>
                                `;
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                modalContent.innerHTML = '<div class="error">Terjadi kesalahan saat mengambil data siswa</div>';
                            });
                    } else if (action === 'delete') {
                        modalContent.innerHTML = `
                            <div class="delete-confirm">
                                <h3 class="modal-title"><i class="fas fa-trash"></i> Hapus Siswa</h3>
                                <p>Apakah Anda yakin ingin menghapus siswa: <strong>${nama} (${nis})</strong>?</p>
                                <form method="POST">
                                    <input type="hidden" name="nis" value="${nis}">
                                    <div class="btn-group">
                                        <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
                                        <button type="submit" name="delete_siswa" class="btn-danger">Hapus</button>
                                    </div>
                                </form>
                            </div>
                        `;
                    }
                    
                    modal.style.display = 'block';
                }
                
                function closeModal() {
                    document.getElementById('crudModal').style.display = 'none';
                }
                
                // Close modal when clicking outside
                window.onclick = function(event) {
                    const modal = document.getElementById('crudModal');
                    if (event.target == modal) {
                        closeModal();
                    }
                };
            </script>
        <?php endif; ?>
        
        <?php if ($page == 'admin' && $action == 'edit_siswa' && $nis_edit != ''): ?>
            <!-- Form Edit Siswa -->
            <div class="edit-form">
                <h3 class="modal-title"><i class="fas fa-user-edit"></i> Edit Siswa</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="edit_nis">NIS</label>
                        <input type="text" id="edit_nis" name="nis" value="<?php echo $siswa_edit['nis']; ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_nama">Nama</label>
                        <input type="text" id="edit_nama" name="nama" value="<?php echo $siswa_edit['nama']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_password">Password (Kosongkan jika tidak ingin mengubah)</label>
                        <input type="password" id="edit_password" name="password">
                    </div>
                    
                    <button type="submit" name="save_siswa" class="btn-success">Simpan Perubahan</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <p>© <?php echo date('Y'); ?> Sistem Presensi SMK - Dibangun dengan PHP Native</p>
    </div>
    
    <!-- Loading overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="loading-spinner"></div>
    </div>
</body>
</html>
