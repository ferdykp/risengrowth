# RisenGrowth — Fix User Factory & Regressions

## Penyebab

Middleware `EnsureAccountIsActive` mengalihkan akun `User::factory()->create()` ke login karena atribut `is_active` tidak eksplisit di factory. Banyak test lama mengasumsikan akun factory langsung aktif.

## Perbaikan

- `database/factories/UserFactory.php`: nilai default `role=member`, `is_active=true`, `must_change_password=false` serta state `inactive()`/`admin()` untuk skenario tertentu.
- `tests/Feature/UserManagementTest.php`: test state default dan penggunaan `inactive()`.
- Semua aturan middleware tetap aktif; tidak ada bypass.

## Penggunaan

Cadangkan source dan `.env`, timpa file kode yang diperbarui dari ZIP, lalu jalankan:

```sh
php artisan optimize:clear
php artisan test --filter=UserManagementTest
php artisan test
```

Tidak memerlukan migration baru. Perubahan factory hanya memengaruhi akun yang dibuat oleh test/seeder melalui factory, tidak mengaktifkan ulang pengguna yang sudah dinonaktifkan di database.

## Verifikasi

`php -l` dan validasi ZIP lulus. Laravel PHPUnit belum dapat dijalankan di environment ini karena folder `vendor` tidak tersedia.
