# PRD — Website Quiz Game Interaktif

**Versi:** 1.1
**Tech Stack:** PHP 8+ native (tanpa framework), MySQL (PDO), HTML + CSS, JavaScript vanilla
**Status:** Draft

---

## 1. Ringkasan

Website kuis pilihan ganda bergaya game. Pengunjung memasukkan **Nama** dan **Kelas**, menjawab soal satu per satu dengan **countdown timer**, dan mendapat skor berdasarkan **ketepatan dan kecepatan**. Skor tertinggi tampil di **Leaderboard Top 10** pada halaman utama. Admin/tim mengelola soal lewat dashboard khusus.

## 2. Tujuan

- Membuat kuis terasa seperti game: seru, kompetitif, dan interaktif.
- Mendorong pengunjung bermain ulang untuk mengalahkan skor teman.
- Memudahkan admin mengelola soal tanpa menyentuh kode.
- Nyaman dipakai di semua perangkat (HP, tablet, desktop).
- Mudah dikembangkan dan di-hosting: cukup PHP + MySQL, tanpa framework.

## 3. Target Pengguna

| Peran | Deskripsi | Autentikasi |
|---|---|---|
| **Pengunjung (Player)** | Siswa/peserta yang bermain kuis | Tidak perlu login, cukup isi Nama + Kelas |
| **Admin** | Tim pengelola soal dan hasil | Login username/email + password |

## 4. Ruang Lingkup

**Termasuk (MVP):**
- Modul manajemen soal (CRUD) oleh admin
- Sesi bermain dengan timer per soal
- Perhitungan skor otomatis (benar + kecepatan)
- Leaderboard Top 10 di halaman utama
- Desain responsif bergaya game

**Tidak termasuk (di luar MVP):**
- Mode multiplayer real-time (room/PIN)
- Registrasi/login pengunjung
- Kategori/mata pelajaran ganda
- Aplikasi mobile native

---

## 5. Kebutuhan Fungsional

### 5.1 Modul Manajemen Soal (Admin)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-A1 | Admin login dan logout | Must |
| FR-A2 | Menambah soal: teks soal, opsi A, B, C, D, kunci jawaban | Must |
| FR-A3 | Mengedit soal yang sudah ada | Must |
| FR-A4 | Menghapus soal (dengan konfirmasi) | Must |
| FR-A5 | Daftar soal dengan pencarian dan pagination | Should |
| FR-A6 | Kolom pembahasan jawaban (opsional) | Should |
| FR-A7 | Mengaktifkan/menonaktifkan soal tanpa menghapus | Should |
| FR-A8 | Melihat daftar hasil bermain (nama, kelas, skor, waktu) | Must |
| FR-A9 | Menghapus entri hasil / reset leaderboard | Should |
| FR-A10 | Pengaturan kuis: durasi per soal, jumlah soal per sesi | Should |
| FR-A11 | Import soal dari CSV | Could |

**Validasi soal:** teks soal wajib diisi, keempat opsi wajib diisi, kunci jawaban wajib salah satu dari A/B/C/D. Validasi dilakukan di server (PHP), bukan hanya di browser.

### 5.2 Sesi Bermain (Pengunjung)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-P1 | Halaman awal meminta **Nama** (wajib) dan **Kelas** (wajib) sebelum mulai | Must |
| FR-P2 | Soal tampil **satu per satu** | Must |
| FR-P3 | **Countdown timer** per soal (default 15 detik, dapat diatur admin) | Must |
| FR-P4 | Waktu habis tanpa jawaban dihitung salah dan otomatis lanjut | Must |
| FR-P5 | Pilihan jawaban A-D berupa kartu besar yang mudah ditekan | Must |
| FR-P6 | Feedback instan setelah menjawab (benar/salah + kunci jawaban) | Must |
| FR-P7 | Skor berjalan tampil selama bermain | Should |
| FR-P8 | Urutan soal diacak setiap sesi | Should |
| FR-P9 | Halaman hasil akhir: skor, jumlah benar, peringkat, tombol Main Lagi | Must |
| FR-P10 | Efek suara dan animasi, dengan tombol mute | Could |

### 5.3 Perhitungan Skor

```
Jika jawaban salah / waktu habis : skor = 0
Jika jawaban benar               : skor = 500 + round((sisa_detik / durasi_soal) × 500)
```

- Rentang skor per soal: 500 sampai 1000 (jika benar).
- Total skor = jumlah skor seluruh soal dalam satu sesi.
- **Waktu dihitung di server**: PHP mencatat `question_sent_at` saat soal dikirim dan menghitung selisih saat jawaban diterima. Waktu dari browser tidak dipercaya.
- Jawaban yang diterima setelah batas waktu (+ toleransi jaringan ±1 detik) dianggap salah.
- Tie-breaker leaderboard: skor sama diurutkan berdasarkan total waktu menjawab tercepat, lalu waktu selesai paling awal.

### 5.4 Live Leaderboard

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-L1 | Halaman utama menampilkan **Top 10** skor tertinggi | Must |
| FR-L2 | Kolom: peringkat, nama, kelas, skor | Must |
| FR-L3 | Pembaruan otomatis lewat polling `fetch()` tiap 5-10 detik tanpa reload halaman | Should |
| FR-L4 | Top 3 ditampilkan dengan highlight khusus (podium/medali) | Should |
| FR-L5 | Satu pemain (nama + kelas) hanya muncul sekali dengan **skor terbaiknya** | Must |
| FR-L6 | Filter leaderboard per kelas | Could |

> **Asumsi:** Satu nama boleh bermain berkali-kali. Semua percobaan tercatat di database, tetapi leaderboard hanya menampilkan skor terbaik per pemain. Ini bisa diubah sesuai keputusan tim.

---

## 6. Alur Pengguna

### Pengunjung
1. Membuka website dan melihat halaman utama dengan leaderboard dan form Nama + Kelas.
2. Mengisi Nama dan Kelas, lalu menekan **Mulai**.
3. Hitung mundur 3-2-1, lalu soal pertama muncul.
4. Menjawab sebelum timer habis dan melihat feedback.
5. Mengulang sampai soal terakhir.
6. Melihat halaman hasil: skor, peringkat, dan tombol **Main Lagi** atau **Lihat Leaderboard**.

### Admin
1. Membuka `/admin/login.php` dan masuk.
2. Dashboard menampilkan ringkasan: jumlah soal, jumlah pemain, rata-rata skor.
3. Mengelola soal (tambah/edit/hapus), melihat hasil, dan mengatur kuis.

---

## 7. Desain dan UX

**Gaya visual:**
- Tema game: warna cerah dan kontras, sudut membulat, tipografi tebal.
- Empat kartu jawaban dengan warna berbeda (merah, biru, kuning, hijau) dan label A-D.
- Bar atau lingkaran timer yang berubah merah di 5 detik terakhir.
- Animasi (CSS + JavaScript): transisi antar soal, konfeti/getar halus untuk benar, goyang untuk salah, angka skor naik bertahap.
- Podium untuk Top 3 di leaderboard.

**Responsif (mobile-first):**
- Breakpoint: HP (< 640px), tablet (640-1024px), desktop (> 1024px).
- Di HP, kartu jawaban tersusun 1 kolom; di tablet/desktop 2x2 (CSS Grid/Flexbox).
- Area sentuh minimal 48 px.
- Teks soal tetap terbaca tanpa scroll horizontal.

**Aksesibilitas:**
- Kontras warna memadai dan label huruf A-D pada setiap kartu (tidak hanya mengandalkan warna).
- Dukungan keyboard (tombol 1-4 atau A-D untuk menjawab di desktop).
- Opsi `prefers-reduced-motion` untuk mengurangi animasi.

---

## 8. Kebutuhan Non-Fungsional

| Aspek | Kebutuhan |
|---|---|
| **Performa** | Halaman utama termuat < 2 detik pada koneksi 4G; transisi antar soal < 300 ms |
| **Keamanan** | Lihat bagian 9 (checklist keamanan PHP native) |
| **Anti-curang** | Timer dan skor dihitung di server; soal dikirim satu per satu; urutan soal acak; kunci jawaban tidak dikirim ke browser sebelum dijawab |
| **Skalabilitas** | Mampu melayani minimal 100 pemain bersamaan (kelas/acara sekolah) |
| **Kompatibilitas** | Chrome, Safari, Firefox, Edge versi terbaru; Android dan iOS |
| **Ketersediaan** | Berjalan di shared hosting/VPS standar (PHP 8.0+, MySQL 5.7+/MariaDB); bisa dikembangkan lokal dengan XAMPP/Laragon |

---

## 9. Arsitektur dan Tech Stack

| Bagian | Teknologi | Peran |
|---|---|---|
| **Backend** | PHP 8+ native | Logika server: sesi, validasi, pengacakan soal, pencatatan waktu, penilaian, autentikasi admin |
| **Database** | MySQL + **PDO** (prepared statement) | Penyimpanan soal, pemain, sesi, jawaban, pengaturan |
| **Frontend** | HTML5 + CSS3 (opsional Tailwind via CDN) | Struktur dan tampilan responsif bergaya game |
| **Interaktivitas** | JavaScript vanilla (ES6+) | Timer countdown, animasi, tampilan feedback, polling leaderboard |
| **Komunikasi** | `fetch()` + JSON | Browser memanggil endpoint di folder `api/` |
| **Sesi login** | PHP `$_SESSION` | Menjaga status login admin dan sesi permainan |

**Pembagian tugas:**
- **PHP (server)** adalah pemegang keputusan: menyimpan data, mencatat waktu, memeriksa kunci jawaban, menghitung skor.
- **JavaScript (browser)** hanya menampilkan: timer di layar, animasi, dan memperbarui leaderboard. Timer di layar bersifat kosmetik.

### Struktur Folder

```
quiz/
├── index.php              ← halaman utama: leaderboard + form nama
├── play.php               ← halaman game
├── result.php             ← hasil akhir
├── config/
│   └── database.php       ← koneksi PDO (simpan di luar public_html bila memungkinkan)
├── api/
│   ├── start.php          ← buat pemain + sesi, acak urutan soal
│   ├── question.php       ← kirim soal aktif (tanpa kunci jawaban)
│   ├── answer.php         ← terima jawaban, hitung skor di server
│   └── leaderboard.php    ← JSON top 10
├── admin/
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── questions.php      ← daftar soal
│   ├── question_form.php  ← tambah/edit soal
│   ├── question_delete.php
│   ├── results.php
│   └── settings.php
├── includes/
│   ├── header.php
│   ├── footer.php
│   ├── auth.php           ← cek login admin (di-include di semua halaman admin)
│   ├── csrf.php           ← pembuat/pemeriksa token CSRF
│   └── functions.php      ← helper: sanitasi, hitung skor, dll.
├── assets/
│   ├── css/style.css
│   ├── js/game.js         ← timer, animasi, fetch soal/jawaban
│   ├── js/leaderboard.js  ← polling tiap 5-10 detik
│   ├── img/
│   └── sounds/
└── database.sql           ← struktur tabel + data contoh
```

### Daftar Halaman dan Endpoint

| Method | File | Fungsi |
|---|---|---|
| GET | `index.php` | Halaman utama + leaderboard + form nama |
| POST | `api/start.php` | Membuat player dan sesi, mengacak soal |
| GET | `api/question.php` | Mengambil soal aktif (tanpa kunci jawaban) |
| POST | `api/answer.php` | Mengirim jawaban, server menghitung skor |
| GET | `result.php?session=...` | Halaman hasil akhir |
| GET | `api/leaderboard.php` | Data Top 10 dalam JSON (untuk polling) |
| GET/POST | `admin/login.php` | Login admin |
| GET/POST | `admin/questions.php`, `question_form.php`, `question_delete.php` | CRUD soal |
| GET | `admin/results.php` | Daftar hasil bermain |
| GET/POST | `admin/settings.php` | Pengaturan kuis |

### Checklist Keamanan (wajib karena tanpa framework)

1. **Prepared statement PDO** di semua query (cegah SQL injection); nonaktifkan emulate prepares.
2. **`password_hash()` / `password_verify()`** untuk password admin.
3. **`session_start()`** dengan cookie `HttpOnly` dan `SameSite`; panggil `session_regenerate_id(true)` setelah login.
4. **Token CSRF** pada semua form admin dan request POST yang mengubah data.
5. **`htmlspecialchars()`** setiap kali menampilkan nama, kelas, atau teks soal (cegah XSS).
6. **`includes/auth.php`** dipanggil di setiap halaman admin untuk memastikan hanya admin login yang bisa mengakses.
7. **Pembatasan percobaan login** (mis. maks. 5 kali gagal per beberapa menit per IP).
8. **Rate limiting sederhana** pada `api/answer.php` dan `api/start.php`.
9. **Validasi input di server**: panjang nama/kelas, nilai jawaban hanya A/B/C/D.
10. **File konfigurasi database** tidak boleh bisa diakses langsung dari browser; jangan simpan kredensial di repository publik.
11. **Matikan `display_errors`** di production; catat error ke log.

---

## 10. Struktur Database

### `admins`
| Kolom | Tipe |
|---|---|
| id | INT, PK, AUTO_INCREMENT |
| username | VARCHAR(50), UNIQUE |
| password_hash | VARCHAR(255) |
| created_at | TIMESTAMP |

### `questions`
| Kolom | Tipe |
|---|---|
| id | INT, PK, AUTO_INCREMENT |
| question | TEXT |
| option_a, option_b, option_c, option_d | VARCHAR(255) |
| correct_answer | ENUM('A','B','C','D') |
| explanation | TEXT, NULL |
| is_active | TINYINT(1), default 1 |
| created_at, updated_at | TIMESTAMP |

### `players`
| Kolom | Tipe |
|---|---|
| id | INT, PK, AUTO_INCREMENT |
| name | VARCHAR(50) |
| kelas | VARCHAR(30) |
| created_at | TIMESTAMP |

### `quiz_sessions`
| Kolom | Tipe |
|---|---|
| id | INT, PK, AUTO_INCREMENT |
| player_id | INT, FK → players |
| token | VARCHAR(64), UNIQUE (identitas sesi yang sulit ditebak) |
| question_order | JSON / TEXT (urutan id soal) |
| current_index | INT, default 0 |
| total_score | INT, default 0 |
| correct_count | INT, default 0 |
| total_time_ms | INT, default 0 |
| question_sent_at | DATETIME(3), NULL |
| finished_at | DATETIME, NULL |
| created_at | TIMESTAMP |

### `session_answers`
| Kolom | Tipe |
|---|---|
| id | INT, PK, AUTO_INCREMENT |
| quiz_session_id | INT, FK → quiz_sessions |
| question_id | INT, FK → questions |
| answer | ENUM('A','B','C','D'), NULL |
| is_correct | TINYINT(1) |
| time_taken_ms | INT |
| score | INT |
| created_at | TIMESTAMP |

### `settings`
| Kolom | Tipe |
|---|---|
| setting_key | VARCHAR(50), PK (mis. `question_duration`, `questions_per_session`) |
| setting_value | VARCHAR(255) |

**Indeks yang disarankan:** `quiz_sessions(total_score DESC, total_time_ms ASC)` untuk query leaderboard, `session_answers(quiz_session_id)`, dan `quiz_sessions(token)`.

---

## 11. Aturan Bisnis

1. Nama (maks. 50 karakter) dan Kelas (maks. 30 karakter) wajib diisi; input disanitasi.
2. Sesi hanya bisa dimulai jika ada minimal 1 soal aktif.
3. Jumlah soal per sesi = nilai pengaturan, atau seluruh soal aktif jika lebih sedikit.
4. Satu soal hanya bisa dijawab sekali per sesi.
5. Sesi yang tidak selesai tidak masuk leaderboard.
6. Soal yang pernah dijawab tetap punya riwayat meski soal diedit/dinonaktifkan; gunakan `is_active` alih-alih menghapus permanen bila soal sudah punya jawaban.
7. Identitas sesi permainan memakai `token` acak (bukan id berurutan) agar tidak bisa ditebak pemain lain.

---

## 12. Kriteria Penerimaan (Acceptance Criteria)

- [ ] Admin dapat login dan melakukan tambah, edit, hapus soal dengan validasi yang benar.
- [ ] Halaman admin tidak bisa diakses tanpa login.
- [ ] Pengunjung tidak bisa memulai kuis tanpa mengisi Nama dan Kelas.
- [ ] Soal tampil satu per satu dengan timer 15 detik yang berjalan dan otomatis lanjut saat habis.
- [ ] Jawaban benar yang lebih cepat menghasilkan skor lebih tinggi daripada jawaban benar yang lambat.
- [ ] Skor tidak dapat dimanipulasi dari sisi browser (diuji dengan mengubah waktu/JS di client).
- [ ] Leaderboard menampilkan 10 besar, terurut benar, dan ter-update tanpa reload.
- [ ] Tampilan nyaman dan tidak rusak di HP (360px), tablet, dan desktop.
- [ ] Kunci jawaban tidak terlihat di response jaringan sebelum soal dijawab.
- [ ] Semua query memakai prepared statement dan semua output di-escape.

---

## 13. Rencana Pengembangan

| Fase | Cakupan | Estimasi |
|---|---|---|
| **1. Setup** | Struktur folder, `database.sql`, koneksi PDO, login admin | 1-2 hari |
| **2. Admin** | CRUD soal, pengaturan, daftar hasil | 2-3 hari |
| **3. Game Flow** | Form nama, sesi, timer, perhitungan skor di server | 3-4 hari |
| **4. Leaderboard** | Query Top 10, polling, podium | 1-2 hari |
| **5. UI/UX Game** | Styling, animasi, responsif, efek suara | 3-4 hari |
| **6. Testing & Deploy** | Uji lintas perangkat, uji keamanan dan anti-curang, deploy | 2 hari |

---

## 14. Risiko dan Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Pemain memanipulasi timer di browser | Skor tidak adil | Timer dan skor divalidasi di server |
| Celah keamanan karena tanpa framework (SQL injection, XSS, CSRF) | Data bocor/rusak | Ikuti checklist keamanan di bagian 9, lakukan review kode |
| Latensi jaringan membuat jawaban tepat waktu ditolak | Pemain merasa dirugikan | Toleransi ±1 detik di server |
| Lonjakan pemain bersamaan | Server lambat | Indeks database, query ringan, cache leaderboard singkat (file cache 5 detik) |
| Nama tidak pantas di leaderboard | Citra buruk | Admin dapat menghapus entri; filter kata kasar (opsional) |
| Kode makin berantakan saat fitur bertambah | Sulit dirawat | Pisahkan folder (`api/`, `admin/`, `includes/`), gunakan helper bersama |
| Soal terlalu sedikit | Kuis membosankan | Wajibkan minimal jumlah soal aktif sebelum kuis dibuka |

---

## 15. Pengembangan Lanjutan (Backlog)

- Mode multiplayer real-time dengan PIN room (butuh WebSocket; pertimbangkan migrasi ke framework)
- Kategori dan tingkat kesulitan soal
- Power-up (50:50, tambah waktu)
- Streak bonus untuk jawaban benar beruntun
- Gambar pada soal dan opsi
- Export hasil ke Excel/PDF
- Leaderboard mingguan/bulanan
