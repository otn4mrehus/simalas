# Troubleshooting on Armbian hosting
## Kendala I
```
Fatal error: Uncaught Error: Class 'mysqli' not found in /var/www/html/presensi/index.php:16 Stack trace: #0 {main} thrown in /var/www/html/presensi/index.php on line 16
```
*Fatal error: Uncaught Error: Class 'mysqli' not found*

## Solusi I
#### Jalankan perintah ini di dalam Dockerfile atau container terminal
```
docker-php-ext-install mysqli && docker-php-ext-enable mysqli
```
## Solusi II
#### a. Masuk ke container
```
docker exec -it <nama_container_php> bash
```
#### b. Jalankan perintah ini di dalam container terminal
```
docker-php-ext-install mysqli && \
docker-php-ext-enable mysqli
```
#### c. Keluar container
```
exit 
```
#### d. Restart container PHP/Server
```
docker restart nama_container_php
```
#### e. Host Database Container
###### Jika menggunakan Docker, host-nya BUKAN localhost atau 127.0.0.1, tapi harus sesuai nama service/container database.
```
$host = "db"; // <----- atau sesuai nama container MySQL di docker-compose.yml
$user = "root";
$pass = "password";
$db   = "presensi";
```


## Kendala II
*Error Graphic Draw di browser muncul pada saat "Kirim Presensi". Ekstensi ini digunakan untuk memproses gambar secara dinamis.dengan pustaka GD (Graphics Draw)*

## Solusi II
#### a.Jalankan perintah ini di dalam Dockerfile atau container terminal
```
apt update -y && \
apt install -y libpng-dev libjpeg-dev libfreetype6-dev && \
docker-php-ext-configure gd --with-freetype --with-jpeg && \
docker-php-ext-install gd
```
#### b. Restart container PHP/Server
```
docker restart nama_container_php
```
