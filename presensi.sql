SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+07:00";
CREATE DATABASE IF NOT EXISTS presensi_siswa DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
USE presensi_siswa;

CREATE TABLE absensi_izin (
  id int(11) NOT NULL,
  nis varchar(20) NOT NULL,
  tanggal date NOT NULL,
  jenis enum('sakit','ijin') NOT NULL,
  keterangan text DEFAULT NULL,
  lampiran varchar(255) DEFAULT NULL,
  status enum('pending','diterima','ditolak') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE kelas (
  id int(11) NOT NULL,
  nama_kelas varchar(10) NOT NULL,
  wali_kelas varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE pengaturan (
  id int(11) NOT NULL,
  latitude decimal(10,8) NOT NULL,
  longitude decimal(11,8) NOT NULL,
  radius int(11) NOT NULL COMMENT 'dalam meter'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE presensi (
  id int(11) NOT NULL,
  nis varchar(20) NOT NULL,
  tanggal date NOT NULL,
  jam_masuk time DEFAULT NULL,
  jam_pulang time DEFAULT NULL,
  foto_masuk varchar(255) DEFAULT NULL,
  foto_pulang varchar(255) DEFAULT NULL,
  status_masuk enum('tepat waktu','terlambat') DEFAULT 'tepat waktu',
  lokasi_masuk varchar(50) DEFAULT NULL,
  status_pulang enum('tepat waktu','cepat') DEFAULT 'tepat waktu',
  lokasi_pulang varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE siswa (
  nis varchar(20) NOT NULL,
  nama varchar(100) NOT NULL,
  password varchar(255) NOT NULL,
  kelas_id int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE terlambat (
  id int(11) NOT NULL,
  nis varchar(20) NOT NULL,
  tanggal date NOT NULL,
  keterangan text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


ALTER TABLE absensi_izin
  ADD PRIMARY KEY (id);

ALTER TABLE kelas
  ADD PRIMARY KEY (id);

ALTER TABLE pengaturan
  ADD PRIMARY KEY (id);

ALTER TABLE presensi
  ADD PRIMARY KEY (id),
  ADD KEY nis (nis);

ALTER TABLE siswa
  ADD PRIMARY KEY (nis);

ALTER TABLE terlambat
  ADD PRIMARY KEY (id);


ALTER TABLE absensi_izin
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE kelas
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE pengaturan
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE presensi
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE terlambat
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE presensi
  ADD CONSTRAINT presensi_ibfk_1 FOREIGN KEY (nis) REFERENCES siswa (nis);
