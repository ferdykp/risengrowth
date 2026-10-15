# RisenGrowth — Workspace Layout Refactor

## Yang diperbaiki
- 11 halaman authenticated memakai satu Blade layout: `resources/views/layouts/workspace.blade.php`.
- Sidebar/menu aktif, header, identitas user, logout, dan menu admin dari satu tempat.
- Mobile hamburger, overlay, Escape.
- Angka badge dari basis data, tidak lagi hardcoded.
- Semua isi halaman CRUD dan controller lama dipertahankan.
- Tidak ada pengiriman otomatis ke Threads. OAuth dan discovery API masih belum tersedia dan memerlukan kredensial resmi.

## Langkah pemasangan
Backup code dan `.env` dulu. Salin source ke proyek, lalu jalankan:

```bash
composer install
php artisan optimize:clear
php artisan view:cache
php artisan test
php artisan serve
```

## Arsitektur selanjutnya
- Migrasi aset CDN/inline scripts ke Vite + Tailwind CSS v4 dan `resources/js/app.js` untuk CSP ketat.
- Audit seluruh query per `user_id` atau workspace/team id sebelum membuka akses multi-user.
- OAuth Threads/Instagram, sinkronisasi data, queue/scheduler dan idempotency memerlukan tahap integrasi terpisah.
- Browser regression visual & full PHP test tidak dijalankan di build environment ini.

## Batas yang belum selesai
- Threads OAuth, Instagram OAuth, scheduler discovery, actual social search, publikasi komentar, dan lead scoring tidak diaktifkan. Tidak ada kredensial/izin API yang diberikan dan tidak boleh di-simulasikan sebagai integrasi sungguhan.
- Tidak ada perubahan migration/database pada refactor UI ini.
- Beberapa komponen visual lama masih menggunakan class dan script inline/ Tailwind CDN. Tahap berikutnya: migrasi terukur ke Vite dan komponen Blade, lalu audit semua Blade dengan browser visual tests.
- Seluruh route otorisasi yang ada dipertahankan, namun isolasi lead antar-user belum diaudit.
