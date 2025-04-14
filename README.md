# IndoTicket: Sistem Pelacakan Tiket Helpdesk Standalone

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/)

[!Screenshoot](https://blog.classy.id/upload/gambar_berita/cc176c11bde3533ce1b97274efedd4c9_20250414110520.png)

IndoTicket adalah sistem pelacakan tiket helpdesk sederhana namun kuat yang diimplementasikan dalam satu file PHP, menjadikannya sangat mudah untuk di-deploy. Sistem ini dirancang untuk memudahkan pengguna melacak status permintaan dukungan teknis mereka tanpa perlu login.

## ✨ Fitur

- **Single-file Application**: Seluruh aplikasi terdapat dalam satu file PHP untuk kemudahan deployment
- **Real-time Status Tracking**: Lacak status tiket support secara real-time
- **Timeline Activity**: Lihat seluruh riwayat aktivitas tiket
- **File Attachment Display**: Melihat daftar file lampiran yang terkait dengan tiket
- **Responsive Design**: Tampilan yang responsif menggunakan TailwindCSS
- **No External APIs**: Tidak memerlukan API eksternal, semua diproses pada server yang sama

## 📋 Persyaratan

- PHP 7.4 atau lebih tinggi
- MySQL/MariaDB
- Web server (Apache, Nginx, dll)

## 🚀 Instalasi

1. Clone repository ini:
   ```bash
   git clone https://github.com/classyid/indoticket.git
   ```

2. Salin file `index.php` ke direktori web server Anda.

3. Edit bagian konfigurasi database di bagian atas file:
   ```php
   // Koneksi database
   $host = 'your_database_host';
   $username = 'your_database_username';
   $password = 'your_database_password'; 
   $database = 'your_database_name';
   ```

4. Pastikan struktur database Anda kompatibel (lihat bagian Struktur Database di bawah).

5. Akses melalui browser, contoh: `http://your-server.com/ticket-tracking.php`

## 🗄️ Struktur Database

Aplikasi menggunakan tabel-tabel berikut:

- `users`: Menyimpan data pengguna dan pelapor tiket
- `ticket_replies`: Menyimpan tiket utama dan balasan tiket
- `ticket_history`: Menyimpan riwayat perubahan status tiket
- `ticket_files`: Menyimpan informasi file yang dilampirkan pada tiket
- `ticket_categories`: Menyimpan kategori tiket
- `data_product`: Menyimpan produk terkait tiket
- `custom_statuses`: Menyimpan status-status kustom tiket

## 📊 Cara Penggunaan

1. Buka halaman aplikasi di browser
2. Masukkan ID Tiket pada form pencarian
3. Sistem akan menampilkan detail tiket dan riwayat aktivitasnya:
   - Status terkini
   - Informasi pelapor dan operator
   - Timeline aktivitas
   - Detail tiket
   - File lampiran (jika ada)

## 🔒 Keamanan

Script ini mengimplementasikan:
- Prepared statements untuk mencegah SQL injection
- Sanitasi output untuk mencegah XSS

## 🛠️ Kustomisasi

Anda dapat dengan mudah mengkustomisasi aplikasi dengan:
- Mengubah warna dan tema di bagian Tailwind config
- Menyesuaikan tampilan dengan mengedit bagian HTML
- Menyesuaikan logika bisnis dengan mengedit fungsi PHP

## 📝 Lisensi

Dilisensikan di bawah [MIT License](LICENSE)

## 👤 Kontributor

- [Andri Wiratmono] - Pengembang Utama

## 🔗 Link Terkait

- [Dokumentasi Lebih Lanjut](https://github.com/classyid/indoticket/wiki)
- [Laporkan Bug](https://github.com/classyid/indoticket/issues)
- [Request Fitur](https://github.com/classyid/indoticket/issues)
