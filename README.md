# Quiz Dies Nat — Website Kuis Interaktif

Website kuis pilihan ganda bergaya game: pemain mengisi Nama + Kelas, menjawab soal satu per satu
dengan countdown, dan mendapat skor berdasarkan ketepatan dan kecepatan. Skor tertinggi tampil
di leaderboard Top 10. Admin mengelola soal, hasil, dan pengaturan lewat dashboard.

Dibangun dengan **PHP 8+ native (tanpa framework)**, **MySQL/MariaDB via PDO**, **HTML + CSS**,
dan **JavaScript vanilla**.

---

## 1. Kebutuhan

- PHP 8.0+ dengan ekstensi `pdo_mysql` (dan `mbstring` disarankan)
- MySQL 5.7+ / MySQL 8.x / MariaDB 10.3+
- Apache/Nginx (XAMPP, Laragon, atau shared hosting)

## 2. Instalasi

1. Letakkan folder ini di web root, mis. `C:\xampp\htdocs\project_quiz_diesnat`.
2. Import database:

   ```bash
   mysql -u root < database.sql
   ```

   Atau lewat phpMyAdmin: **Import** → pilih `database.sql`.

3. Sesuaikan koneksi di `config/database.php` bila perlu. Nilai default:

   | Variabel | Default |
   |---|---|
   | `QUIZ_DB_HOST` | `127.0.0.1` |
   | `QUIZ_DB_PORT` | `3306` |
   | `QUIZ_DB_NAME` | `quiz_diesnat` |
   | `QUIZ_DB_USER` | `root` |
   | `QUIZ_DB_PASS` | *(kosong)* |

   Kredensial bisa ditimpa lewat environment variable tanpa mengubah file.

4. Buka `http://localhost/project_quiz_diesnat/`.

5. Login admin di `/admin/login.php`.

   | Username | Password |
   |---|---|
   | `admin` | `admin123` |

   **Ganti password segera** lewat menu **Pengaturan → Keamanan Akun**.

## 3. Struktur Folder

```
project_quiz_diesnat/
├── index.php              halaman utama: leaderboard + form nama
├── cafeteria.php          lobby eksplorasi (karakter WASD, arcade, NPC)
├── leaderboard.php        halaman papan peringkat lengkap
├── play.php               halaman kuis
├── result.php             halaman hasil akhir
├── database.sql           struktur tabel + data contoh
├── config/
│   └── database.php       koneksi PDO
├── api/
│   ├── start.php          buat pemain + sesi, acak soal
│   ├── question.php       kirim soal aktif (tanpa kunci jawaban)
│   ├── answer.php         terima jawaban, hitung skor di server
│   └── leaderboard.php    JSON Top 10
├── admin/
│   ├── login.php, logout.php
│   ├── dashboard.php
│   ├── questions.php, question_form.php, question_delete.php
│   ├── results.php
│   ├── settings.php
│   └── includes/          layout admin
├── includes/
│   ├── header.php, footer.php
│   ├── pixel-decor.php    sprite pixel-art & lapisan dekorasi
│   ├── auth.php           proteksi halaman admin
│   ├── csrf.php           token CSRF
│   ├── functions.php      helper + bootstrap
│   └── game.php           logika permainan & skor
├── assets/
│   ├── css/
│   │   ├── style.css          gaya utama (arcade pixel)
│   │   ├── pixel-decor.css    dekorasi pixel & animasi sprite
│   │   ├── fx.css             animasi interaktif (ripple, reveal, ticker, dll.)
│   │   ├── cafeteria.css      scene Cafeteria (canvas + HUD)
│   │   └── leaderboard.css    halaman Hall of Fame
│   └── js/
│       ├── app.js             helper bersama (toast, confetti, fetch)
│       ├── fx.js              efek interaktif global
│       ├── cafeteria.js       engine eksplorasi Cafeteria
│       ├── leaderboard-page.js animasi halaman leaderboard
│       ├── home.js, game.js, leaderboard.js, result.js
├── logs/                  log error PHP (diblokir dari web)
└── storage/               rate limit file (diblokir dari web)
```

## 4. Aturan Main

- Jumlah soal per sesi = pengaturan (default 10), atau seluruh soal aktif bila lebih sedikit.
- Urutan soal diacak tiap sesi; satu soal hanya dijawab sekali per sesi.
- Timer per soal default 15 detik (dapat diatur 3–120 detik).
- Skor jawaban benar: `500 + round((sisa_detik / durasi) × 500)` → 500–1000.
- Jawaban salah / waktu habis: 0.
- Waktu dihitung **di server** dari `question_sent_at`; toleransi jaringan ±1 detik.
- Leaderboard: satu pemain (nama + kelas) tampil sekali dengan skor terbaiknya.
- Tie-breaker: total waktu lebih cepat, lalu selesai lebih awal.

## 5. Keamanan yang Diterapkan

- Prepared statement PDO di semua query, `ATTR_EMULATE_PREPARES = false`.
- `password_hash()` / `password_verify()` untuk password admin.
- Cookie sesi `HttpOnly` + `SameSite=Lax` (+ `Secure` saat HTTPS), `session_regenerate_id(true)` setelah login.
- Token CSRF pada semua form admin dan request POST API.
- `htmlspecialchars()` pada setiap output (`e()`).
- `includes/auth.php` di semua halaman admin.
- Rate limit login (maks. 5 gagal / 5 menit / IP) dan rate limit API.
- Validasi input di server; nilai jawaban hanya A/B/C/D.
- Folder `config/`, `includes/`, `logs/`, `storage/` diblokir lewat `.htaccess`.
- `display_errors` mati di production; error dicatat ke `logs/php-error.log`.
- Kunci jawaban tidak pernah dikirim sebelum pemain menjawab.

## 6. Uji Cepat

1. Beranda menampilkan form dan leaderboard.
2. Isi Nama + Kelas → Mulai → hitung mundur → soal muncul.
3. Jawab dengan cepat; feedback menampilkan benar/salah + pembahasan.
4. Selesai → halaman hasil berisi skor, peringkat, ringkasan jawaban.
5. Buka beranda di tab lain; leaderboard berubah tanpa reload (polling ~7 detik).
6. Login admin → tambah/edit/nonaktifkan soal, atur durasi, lihat hasil.

## 7. Deployment Produksi

- Matikan `QUIZ_DEV` (jangan set env `QUIZ_DEV=1`).
- Ganti password admin dan kredensial database.
- Pastikan `logs/` dan `storage/` writable oleh PHP.
- Aktifkan HTTPS agar cookie `Secure` ikut aktif.
- Cadangkan database secara berkala.
