SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

CREATE TABLE absensi_izin (
  id int(11) NOT NULL,
  nis varchar(20) NOT NULL,
  tanggal date NOT NULL,
  jenis enum('sakit','ijin') NOT NULL,
  keterangan text DEFAULT NULL,
  lampiran varchar(255) DEFAULT NULL,
  status enum('pending','diterima','ditolak') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_nopad_ci;

INSERT INTO absensi_izin (id, nis, tanggal, jenis, keterangan, lampiran, status) VALUES
(1, '12345', '2025-07-05', 'sakit', 'Berobat', 'izin-12345-20250705212018-INFO_KELULUSAN.jpeg', 'diterima');

CREATE TABLE kelas (
  id int(11) NOT NULL,
  nama_kelas varchar(10) NOT NULL,
  wali_kelas varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_nopad_ci;

CREATE TABLE pengaturan (
  id int(11) NOT NULL,
  latitude decimal(10,8) NOT NULL,
  longitude decimal(11,8) NOT NULL,
  radius int(11) NOT NULL COMMENT 'dalam meter'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_nopad_ci;

INSERT INTO pengaturan (id, latitude, longitude, radius) VALUES
(1, '-6.41050000', '106.84400000', 100);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_nopad_ci;

INSERT INTO presensi (id, nis, tanggal, jam_masuk, jam_pulang, foto_masuk, foto_pulang, status_masuk, lokasi_masuk, status_pulang, lokasi_pulang) VALUES
(5, '54321', '2025-07-05', '21:27:46', NULL, 'foto-54321-21.27-05.07.2025.jpg', NULL, 'terlambat', '-6.4107407,106.8439112', 'tepat waktu', NULL);

CREATE TABLE siswa (
  nis varchar(20) NOT NULL,
  nama varchar(100) NOT NULL,
  password varchar(255) NOT NULL,
  kelas_id int(11) DEFAULT NULL,
  kelas varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_nopad_ci;

INSERT INTO siswa (nis, nama, password, kelas_id, kelas) VALUES
('12345', 'Budi Santoso', '$2y$10$SKwXFa5vWWAejQqM2qMsQ.NFEnEux9tt6JhyJiOdVguhaB7BGrnIq', NULL, NULL),
('54321', 'Ani Wijaya', '$2y$10$SKwXFa5vWWAejQqM2qMsQ.NFEnEux9tt6JhyJiOdVguhaB7BGrnIq', NULL, NULL);

CREATE TABLE terlambat (
  id int(11) NOT NULL,
  nis varchar(20) NOT NULL,
  tanggal date NOT NULL,
  keterangan text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_nopad_ci;

INSERT INTO terlambat (id, nis, tanggal, keterangan) VALUES
(1, '54321', '2025-07-05', 'Telat');


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
  MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE kelas
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE pengaturan
  MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE presensi
  MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE terlambat
  MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;


ALTER TABLE presensi
  ADD CONSTRAINT presensi_ibfk_1 FOREIGN KEY (nis) REFERENCES siswa (nis);
COMMIT;
