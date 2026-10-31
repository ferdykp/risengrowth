# RisenGrowth V2 — audit dan rencana implementasi

Audit source 31 Oktober 2026. Lingkup: routes, controllers, services, models, middleware, jobs, scheduler, migration/index, Blade/JS/CSS, worker Python, dan test existing. Tidak membaca nilai .env atau memanggil provider berbayar/live. Database schema diperiksa read-only melalui Boost. Ini audit source, bukan penetration test atau validasi izin platform.

## Existing architecture dan alur

Laravel 13.36 / PHP 8.5, MySQL, database queue, Blade, Tailwind 4 terpasang; shell memakai Tailwind CDN runtime. Python memakai httpx, BeautifulSoup, dan Playwright opsional. Login internal → overview → keyword/discovery → hasil/qualification → lead → draft → review/approval. Tidak ditemukan endpoint publikasi otomatis. Data discovery/lead merupakan workspace internal bersama; keyword, draft, dan preferensi dimiliki pengguna.

Provider berbeda kemampuan: Threads official menjalankan keyword_search memakai OAuth; izin/ketersediaan aktual hanya dapat dibuktikan melalui request resmi. Apify memakai actor/run/dataset dengan batas biaya, polling, dan resume ID. Python adalah adapter HTML/RSS/browser yang dikonfigurasi admin serta official HN API untuk recent-items filtering, bukan pencarian Threads. Alur Apify/Python sudah mempunyai rule evidence, normalisasi, history, dan unique index. Status OAuth tidak membuktikan public search tersedia.

## Inventaris/reuse

Dipertahankan: authentication/password reset, admin/role, OAuth/enkripsi token, keyword soft-delete, discovery history/live polling, Apify charge guard, normalizer, rule engine, advisory local AI, Indonesian preference, inbox/detail, draft revision/hash/approval, activity, WIB clock, shared shell, queue/doctor commands, dan 213 test Laravel. Baseline: 213 test/949 assertion lulus; 11 test JS dan 12 test Python virtualenv lulus. Python global tidak memiliki httpx; gunakan scraper/.venv. Tidak perlu framework/package baru.

## Temuan dan prioritas

| Severity | Bukti | Temuan | Prioritas/perbaikan |
|---|---|---|---|
| High | Verified code | Overview, generator, dan lead detail membaca draft milik pengguna lain, sedangkan review queue bersifat pribadi | Batasi seluruh pembacaan draft ke author_id |
| High | Verified code | Threads official membuat lead tanpa qualification/analysis, berbeda dari Python/Apify | Simpan penilaian konsisten dan attribution tanpa menghapus history lead existing |
| High | Verified code | Rule buying intent tidak mempertimbangkan negasi seperti “tidak butuh website” | Negasi eksplisit harus meminta review, bukan buyer pasti |
| Medium | Verified code | Dashboard menyatakan Threads belum dikonfigurasi secara hard-coded | Hapus klaim statis; status provider tampil pada Discover |
| Medium | Verified code | Komunitas/tutorial/framework dipandang hanya sebagai negative buyer; belum ada dimensi networking | Tambah category, intent, confidence, rule version sebagai metadata yang dapat ditinjau |
| Medium | Verified code | Belum ada relationship/follow-up/content planning | Tambah data pribadi dengan index, activity, dan explicit manual confirmation |
| Medium | Verified code | Python memuat seluruh response sebelum memeriksa batas ukuran; DNS validation terpisah dari koneksi | Batasi egress di deployment; streaming limit dan DNS pinning perlu hardening lanjutan |
| Medium | Verified code | CDN Tailwind runtime pada shell meskipun Tailwind 4 terpasang | Pindahkan shell ke asset build lokal |
| Medium | Potential | API/provider permissions dan actor terms mungkin berbeda tiap akun | Validasi admin sebelum enable; test menggunakan fake |
| Medium | Potential | Schema live comment_suggestions berbeda dari migration terbaru (purpose) | Jalankan migrate:status dan backup sebelum rollout; jangan menganggap database sudah termigrasi |
| Low | Verified code | Banyak menu/subworkflow dan copy empty-state menganggap Threads wajib | Empat modul utama; secondary links tetap tersedia |
| Low | Potential | Query dashboard/inbox perlu profiling pada volume produksi | Eager loading sudah ada; ukur EXPLAIN dan latency sebelum index tambahan |

Tidak ada temuan Critical yang dibuktikan. .env tidak tracked pada working tree; pemeriksaan history untuk .env/.env.backup/.env.production/auth.json menemukan 0 commit. Tidak ditemukan nilai sensitif non-placeholder pada .env.example. Pemeriksaan ini bukan bukti menyeluruh bahwa secret tidak pernah terpapar di file/lokasi lain. Audit dependency online gagal karena DNS Packagist; jangan mengklaim dependency bebas vulnerability. Rotasi APP_KEY/THREADS_APP_SECRET/APIFY_TOKEN/GROQ_API_KEY hanya bila ada bukti paparan; rotasi APP_KEY harus mempertimbangkan data token terenkripsi.

## Target architecture dan revised requirements

Empat entry point: Today (tugas nyata, inbox aktif, follow-up), Discover (provider/run/results/keyword/manual capture), Relationships (catatan pribadi dan tahap hubungan), Content Studio (reply assistant existing + ide/draft/history). Discovery tetap provider-specific pada fetch; normalisasi/qualification metadata menjadi kontrak bersama. Kategori business/community/not relevant tidak memakai kuota 70/30. Skor rule evidence bukan probabilitas/fakta AI. Review manual tetap otoritatif.

Flow: capture/discover → review reason/category → save relationship → prepare editable reply → copy/open original → konfirmasi sendiri interaksi → jadwalkan follow-up → catat opportunity/won/lost bila relevan. Networking tidak wajib masuk sales. Content hanya dicatat published setelah konfirmasi manusia; tidak ada auto-post atau mass DM.

## Database change plan

Migration additive: relationships (user + lead unique, category/stage/follow-up/notes), content_drafts (user, topic/pillar/platform/body/status/published date/manual metrics). Reuse activity_logs untuk interaction history yang diberi owner dan metadata; reuse lead/post/draft/analysis. FK restrict mencegah kehilangan history. Tidak mengubah/hapus kolom existing. Rollback schema menghapus data fitur baru: ekspor terlebih dahulu; rollback aplikasi dengan tabel tetap ada lebih aman.

## Tahapan dan acceptance

0 audit + baseline selesai sebelum implementasi. 1: isolasi draft, rule evidence/negation, attribution, Python payload validation. 2: empat navigasi, Today actionable dan asset lokal. 3: relationship/follow-up, manual capture, content drafts/history, manual engagement confirmation. 4: full regression, build, dependency audit bila network tersedia, backup/rollback review. Setiap fase diverifikasi melalui affected tests; hasil akhir dan changed files dicatat di bawah.

## Deployment dan security checklist

- Backup database dan APP_KEY/secret store secara aman; uji restore pada salinan terisolasi. Jangan cetak secret.
- Tinjau git diff dan migration SQL (`php artisan migrate --pretend --no-interaction`), `php artisan migrate:status --no-interaction`; jalankan migration hanya pada environment target yang sudah dibackup.
- `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`; dependency tidak diubah oleh refactor ini.
- Gunakan Python virtualenv existing dengan requirements; periksa versi/resolusi dependency dan audit di CI online.
- `php artisan test --compact` pada database test terisolasi; test Python dan JS, pint, build.
- Atur HTTPS, secure cookies, APP_DEBUG=false, trusted proxy, least-privilege DB, server egress restrictions, secret store, log retention; inline JS masih membatasi CSP ketat.
- Pertahankan scheduler dan worker database; timeout worker harus lebih kecil daripada retry_after. Restart worker setelah rilis. Tidak menambah Redis atau broker.
- Verifikasi discovery doctor, failed jobs, empty/auth/rate-limit UI, akses member/admin, mobile/keyboard, follow-up WIB dan draft privacy. Fake-provider test tidak membuktikan integrasi live berhasil.
- Rollback kode dan asset ke release sebelumnya; jangan rollback migration additive sebelum ekspor data baru. Tidak menjalankan migrate:fresh/db:wipe atau perubahan produksi pada pekerjaan ini.

## Implementasi dan dampak per file

| File | Sebelum → sesudah; alasan/dampak | Verifikasi |
|---|---|---|
| `app/Http/Controllers/OverviewController.php`, `resources/views/overview.blade.php` | Statistik total + klaim koneksi statis → Today berisi inbox aktif, action review/save/dismiss/reply, networking dan follow-up WIB. Draft/activity pribadi; score lalu freshness sebagai urutan sederhana, bukan probabilitas AI. | OverviewTest, GrowthWorkspaceTest |
| `app/Http/Controllers/CommentGeneratorController.php`, `app/Http/Controllers/LeadDetailController.php` | Membaca draft lintas akun → author scope di seluruh jalur; detail memuat relasi pribadi dan history interaksi. Lead/discovery tetap shared. | CommentGeneratorTest, LeadDetailTest, privacy regression |
| `app/Services/Discovery/RecordOpportunityAssessment.php` | Duplikasi pencatatan Python/Apify dan jalur Threads berbeda → satu kontrak assessment, attribution/run/evidence/version dan pelestarian human override. | Python/Apify/Threads discovery suites; override regression |
| `app/Services/Discovery/RunPythonDiscovery.php`, `RunApifyDiscovery.php`, `RunThreadsDiscovery.php` | Memanggil service assessment bersama; Threads menambah penilaian tetapi mempertahankan penyimpanan kandidat existing, termasuk yang belum qualified. Duplicate item tidak dihitung qualified dua kali. Provider-specific fetch/queue/resume tetap dipertahankan. | Existing provider failure/dedup/rollback tests |
| `app/Services/Discovery/QualifyDiscoveredPost.php` | rules-v1 → rules-v2: category, intent classification, confidence band, rule/review version, negation, ERP/HRIS/booking/SaaS. Rubrik score tetap keyword 20 + service 25 + buyer evidence 55 − negative 60; minimum 0. Confidence low untuk uncertain dan medium untuk hasil rule lain, bukan probabilitas terkalibrasi. | QualificationTest + negation tests |
| `app/Models/DiscoveredPost.php` | Urutan analisis dengan timestamp saja → tie-break id untuk review terbaru yang deterministik. | Manual review/reprocessing tests |
| `app/Http/Controllers/DiscoveredPostReviewController.php` | Review qualification saja → kategori manual, review status dan intent networking; manual decision tidak tertimpa reprocessing. | DiscoveryReviewTest + community filter tests |
| `app/Http/Controllers/DiscoveryController.php`, `resources/views/discovery/index.blade.php`, `resources/views/discovery/partials/post-reviews.blade.php` | Review terpisah dari sumber/status kemampuan → category filter, metadata confidence/version, capture manual dan status OAuth terpisah dari hasil run search terakhir. | Discovery/live transport tests + new module/filter tests |
| `app/Http/Controllers/ManualCaptureController.php`, `app/Http/Requests/CapturePostRequest.php` | Belum ada capture opportunity → simpan teks dan referensi HTTPS, idempotent berdasarkan URL/platform, query identitas dipertahankan, UTM dibuang. Tidak mengambil URL. | Capture duplicate/unsafe URL/query tests; Http::preventStrayRequests |
| `app/Models/Relationship.php`, `app/Http/Controllers/RelationshipController.php`, `app/Http/Requests/UpdateRelationshipRequest.php` | Belum ada CRM ringan → user+lead unique, category/stage/notes, follow-up date, private interaction activity, explicit confirmation untuk engaged/connected/opportunity/won/lost. Tahap dapat dipilih manusia; bukan forced sales pipeline. | Private notes, idempotence, validation, manual confirmation, WIB tests |
| `resources/views/leads/relationships.blade.php`, `resources/views/leads/relationship-form.blade.php`, `resources/views/leads/show.blade.php` | Detail hanya status lead → profil sumber, catatan relasi, follow-up, filter kontak/tahap/jatuh tempo dan riwayat interaksi. Contact masih per source lead; belum ada penggabungan identitas penulis lintas postingan. | Render, filters, XSS and interaction-history tests |
| `app/Models/ContentDraft.php`, `app/Http/Controllers/ContentStudioController.php`, `app/Http/Requests/SaveContentDraftRequest.php` | Belum ada content planning → draft/ready/published/archived, pillar, platform, body, tanggal publikasi dan metrics manual; owner check dan konfirmasi published. Tidak memanggil publishing API. | Lifecycle, privacy, validation/date/metric tests |
| `resources/views/comments/studio.blade.php`, `resources/views/comments/content-fields.blade.php` | Belum ada content workspace → enam ide editorial, create/edit/filter/history; input dipertahankan ketika validasi gagal. Ide editorial dilabeli sebagai ide, bukan hasil discovery nyata. | Render/lifecycle/XSS tests |
| `app/Http/Controllers/CommentSuggestionController.php`, `resources/views/comments/partials/quick-generator.blade.php` | Copy/edit saja → tone professional tambahan, mark replied dengan konfirmasi pengguna, timestamp idempotent; edit menghapus konfirmasi lama. Copy tetap tidak mengubah status. | Confirmation/ownership/edit regression + existing suggestion suites |
| `database/migrations/2026_10_31_141723_add_growth_workspace_tables.php` | Additive relationships/content_drafts dan nullable replied_at di comment_suggestions. Unique/FK/index disediakan. Data existing tidak dihapus; down menghapus data fitur baru dan perlu ekspor sebelum dipakai. | Migration pada SQLite test suite; belum diterapkan ke MySQL terhubung |
| `routes/web.php` | Routes existing dipertahankan; tambah authenticated capture/relationships/studio/replied dengan throttle mutation. | Guest/access/validation tests; route:list |
| `resources/views/layouts/workspace.blade.php`, `resources/css/app.css`, `public/workspace.css`, `resources/js/app.js` | Tujuh menu utama/CDN Tailwind → empat modul, secondary tools, Tailwind 4 build lokal, orange/black/cream accents, skip link, mobile inert/focus loop/Escape, busy feedback saat form disubmit. Inline page scripts tetap digunakan sesuai fondasi existing. | WorkspaceLayoutTest, render regression, npm build; visual/browser QA manual masih diperlukan |
| `scraper/main.py`, `scraper/tests/test_sources.py` | Pengecekan ukuran setelah buffering → streaming bounded HTTP/RSS; malformed URL/port/config ditolak; sumber gagal tetap ditelusuri. HN/browser adapters dipertahankan. | 15 Python unittest; HTTP mock dan timeout/error fixtures |
| `tests/Feature/GrowthWorkspaceTest.php` | Tambah regression end-to-end HTTP untuk fitur dan batas keamanan V2. Data test terisolasi; tidak seed demo ke database aplikasi. | 27 test/171 assertion pada file ini pada pemeriksaan akhir; lihat hasil full suite |

## Hasil dan batas verifikasi

Baseline Laravel 213 test/949 assertion, JS 11, Python 12. Setelah implementasi: Laravel 240 test/1120 assertion, JS 11, Python 15 lulus; Pint dan Vite build berhasil. Vite memberi warning optional fontaine untuk optimized font fallback; package baru tidak dipasang. Tidak ada dependency berubah. Tests Laravel memakai SQLite :memory:, bukan MySQL aplikasi. Migrasi baru belum dijalankan pada database terhubung. Boost read-only membuktikan migration `2026_10_28_141317_add_purpose_to_comment_suggestions_table` juga masih pending pada database tersebut; jalankan pending migrations lengkap setelah backup dan review.

Shell langsung tidak dapat mengakses MySQL karena sandbox (operation not permitted). Schema dan migration ledger diperiksa melalui Boost. Online composer audit gagal karena DNS; npm/Python advisory audit belum diverifikasi. Tidak ada live Threads/Apify search atau publish; izin platform/actor harus diverifikasi admin. Tidak ada perubahan produksi, backup produksi, atau klaim kesiapan deployment yang sudah terbukti.

Seluruh fitur baru memerlukan pending migrations dan asset build sebelum digunakan pada environment aplikasi.

Batas yang masih perlu ditutup sebelum rollout: visual QA di browser desktop/mobile dan keyboard nyata; profiling MySQL dengan volume representatif; dependency advisory audit online; smoke test provider resmi sesuai izin; streaming cap pada adapter browser dan DNS pinning/egress policy untuk sumber Python. Score berbasis pola masih bisa salah pada bahasa ambigu/sarkasme. Import manual URL Threads berbasis shortcode dan hasil provider berbasis numeric ID dapat perlu rekonsiliasi jika URL berbeda; unique index existing tetap mencegah duplikasi ID provider yang sama. Content metrics adalah input pengguna, bukan sinkronisasi analytics. Relationship saat ini per lead/post, belum consolidated contact lintas platform.
