# SiwajarV3
Lebih Simpel dan langsung instalasi Database nya
### Kekurangannya:
```
- Belum ada Rule Pengajuan Ijin yang lengkap
- Perbaikan belum disempatkan lagi 
```
# siwajarv1.0
Aplikasi Siswa Belajar berbasis Website ini adalah aplikasi dengan project sekolah yang memungkinkan Presensi menggunankan perangkat HP Android yang dapat dilakukan secara langsung cepat tepat dan akurat di lingkungan sekolah secara mudah, murah dan meriah. Silakan kembangkan dengan ide menarik lainnya ke perangkat mobile. 
Semoga manfaat..
### Fitur Presensi : 
#### Siswa
```
- Login Per Siswa
- Pilihan Kehadiran (Presensi) - Ketidakhadiran (Ijin)
- Dilengkapi Alert Message saat otentikasi pengisian presensi
- Absensi Masuk dan Pulang (1x)
- Presensi berlangsung di Lingkungan Sekolah
- Dilampirkan Foto Wajah Siswa
- Presensi terlambat "> 7.30" , Pulang Awal "< 15.45"
```
#### Admin
```
- Login admin
- Menentukan titik GPS dan radius lingkungan presensi
- Data Pengajuan Ijin Siswa
- Data Keterangan Terlambat Siswa
- Data Rekap Kehadiran dan Ketidkhadiran dengan Filter Waktu
- Prosentase Kehadiran dan Ketidkhadiran dengan Filter Waktu
- Ranking 10 Besar untuk Kehadiran dan Ketidkhadiran 

```

## Persiapan
### 1. XAMPP 7.4 (windows/linux)
### 2. Struktur File - Direktori
```
+ presensi
  + uploads/
    + foto/
      - masuk/   # direktori foto awal hadir sekolah  
      - pulang/  # direktori foto akhir pulang sekolah  
  - index.php
```
### 3. Skema Database
```
scheme_db.sql
```
### 4. Jalankan
```
http://localhost/presensi

LOGIN SISWA: nis/1
LOGIN ADMIN: admin/admin123 

