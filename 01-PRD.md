# RisenDev Growth Assistant — Product Requirements Document (PRD)

**Version:** 1.0 · **Status:** Proposed MVP · **Updated:** 14 October 2026  
**Product type:** Internal social lead discovery and human-reviewed engagement workspace  
**Primary language:** Bahasa Indonesia · **Initial platform:** Threads · **Later:** Instagram (subject to API feasibility)

## 1. Executive summary
RisenDev Growth Assistant membantu RisenDev menemukan percakapan publik yang relevan dengan jasa website, aplikasi, dan sistem bisnis; mengklasifikasikan potensi kebutuhan; menyusun draft respons dengan AI; kemudian meminta persetujuan pengguna sebelum interaksi. Produk memprioritaskan *quality over quantity*, kepatuhan platform, keamanan data, dan workflow yang bisa diaudit.

**Product promise:** “Temukan peluang yang relevan. Tinjau balasan dalam satu tempat. Bangun relasi tanpa spam.”

## 2. Problem & opportunity
- Pencarian manual di Threads/Instagram menyita waktu dan sulit dikonsistenkan.
- Keyword saja menghasilkan noise: developer belajar HTML ≠ calon pembeli website.
- Balasan massal generik berisiko merusak brand dan melanggar kebijakan platform.
- Tidak ada sumber data terpusat untuk postingan, draft, persetujuan, dan tindak lanjut.
- Integrasi eksternal memiliki batas akses dan rate limit yang tidak dapat dikontrol aplikasi.

## 3. Goals, non-goals, guardrails
### Goals (MVP)
1. Temukan postingan publik melalui akses Threads API yang *secara aktual telah diotorisasi*.
2. Kurangi duplikasi, spam, dan konten tidak relevan.
3. Klasifikasikan postingan menjadi direct intent, latent need, community, atau irrelevant; hasil scoring dapat dipahami.
4. Hasilkan 2–3 draft komentar relevan dengan tone berbeda dan **tidak mengarang kemampuan, harga, atau pengalaman**.
5. Wajibkan human approval terhadap **teks dan target postingan yang persis**.
6. Simpan rekam jejak tindakan, status, serta hasil publish/manual engagement.
7. Tetap dapat dipakai ketika integrasi publish tidak mendapat izin: manual copy/open permalink.

### Non-goals (MVP)
- Auto-follow, auto-like massal, auto-DM cold outreach, auto-comment tanpa approval.
- Scraping halaman login / bypass rate limit / bot yang meniru manusia untuk menghindari kebijakan.
- Instagram public-post discovery secara penuh tanpa validasi API terpisah.
- CRM omnichannel, billing, subscription, tenant isolation, training model AI, rekomendasi konten otomatis.
- Menjamin jumlah lead atau penjualan tertentu.

### Product safety constraints
- **Never publish without explicit approval**; approval menjadi tidak valid bila teks, target, atau akun sumber berubah.
- Publication only via official authorized integration and permissions. Otherwise use copy/manual posting with user confirmation after posting.
- Rate-limit locally; respect platform terms; minimize personal data and provide retention/deletion.
- Treat retrieved post content as **untrusted input**, not instructions for AI/app.

## 4. Target persona & jobs to be done
**Owner RisenDev (MVP):** satu operator internal, bekerja dari desktop dan mobile.  
**JTBD:** “Saat ingin mengembangkan audience dan menemukan proyek, saya ingin mendapatkan shortlist postingan yang layak ditanggapi beserta komentar berkualitas, tanpa mencari satu per satu.”

## 5. Scope and priorities
| ID | Requirement | Priority | Acceptance criterion |
|---|---|---|---|
| FR-01 | Login internal | P0 | Hanya operator terautentikasi dapat mengakses workspace |
| FR-02 | Threads OAuth & connection status | P0 | Status, scope dan expiry terlihat; token terenkripsi; disconnect berfungsi |
| FR-03 | API capability checks | P0 | Hasil uji keyword search/permissions dan alasan kegagalan dapat ditampilkan |
| FR-04 | Keyword management | P0 | CRUD, enable/disable, kategori, interval opsional, last run |
| FR-05 | Discovery run | P0 | Manual run dan job terjadwal mengambil hanya data yang diizinkan |
| FR-06 | Dedup + exclusion | P0 | Post unik per platform + external ID; akun sendiri dan konten kosong disaring |
| FR-07 | Lead classification | P0 | Label, score 0–100, rationale singkat, confidence, version |
| FR-08 | Lead Inbox | P0 | Filter prioritas, intent, status; sort; pagination; buka permalink |
| FR-09 | Draft generation | P0 | 2–3 varian tone, bahasa Indonesia, setiap draft tersimpan dan bisa diedit |
| FR-10 | Human approval | P0 | Approve/reject selalu menyimpan actor, timestamp, target, exact approved text/hash |
| FR-11 | Manual engagement fallback | P0 | Copy, open post, mark manually posted; tidak mengklaim publikasi otomatis |
| FR-12 | Publish integration | P1 conditional | Hanya aktif bila endpoint & scope telah lolos uji; idempotent/reconcilable |
| FR-13 | Activity/audit trail | P0 | Trace discovery, AI, approval, dispatch, result, errors tanpa token sensitif |
| FR-14 | Operational dashboard | P1 | KPI posts discovered, qualified leads, pending review, responded; date filter |
| FR-15 | AI provider fallback & budgets | P1 | Retry bounded, provider errors visible, max requests/tokens configurable |
| FR-16 | Instagram discovery | Future | Discovery & actions baru dirancang setelah API-specific research |

## 6. Lead scoring specification (initial, configurable)
- **Service fit (0–40):** match dengan website/company profile, catalog, inventory, POS, apps.
- **Purchase/problem intent (0–35):** eksplisit mencari vendor vs mengeluhkan proses vs diskusi umum.
- **Specificity (0–15):** requirement, business context, actionable request.
- **Recency (0–10):** waktu posting relatif terhadap waktu discovery.
- Threshold awal: `high >= 80`, `medium 55–79`, `low < 55`; label `irrelevant` dapat menimpa prioritas bila tidak ada konteks relevan.
- Score adalah **heuristik prioritisasi**, bukan probabilitas membeli. Semua output AI divalidasi berdasarkan schema, log reasoning ringkas, dan diperbolehkan koreksi manual.
- KPI evaluasi kualitas: precision@20 yang dinilai operator; bukan hanya volume penemuan.

## 7. Functional workflows
### A. Discovery
Connected account → active keywords → enqueue runs → request official API → process pages conservatively → normalize safe fields → deduplicate → persist post → queue analysis. API error menandai run failed/partial; tidak menghapus data lama.

### B. Analysis
Queued post → deterministic prefilter → AI classification with strict JSON schema → validation → score & rationale → lead upsert → Inbox. Invalid output = failed/retry bounded; never automatically qualified.

### C. Draft & approval
Qualified post → Generate draft variants → operator selects/edits → request approval → approved snapshot + content hash + reviewer/time → optional publish or copy. Any edit/target change creates new revision and resets approval. Reject requires optional reason.

### D. Engagement
**Manual V1:** Copy exact approved text → open original post → operator posts through Threads UI → operator optionally marks as published manually with evidence/notes.  
**API conditional:** Approved snapshot → capability check → publication attempt with dispatch key → confirm receipt/post ID → published. Ambiguous timeout = `verification_required`, never blind retry.

## 8. Permission & capability gates
- Official Meta Threads developer documentation is authoritative. Validate current permission names, review requirements, allowed reply targets, rate limits, policies, and endpoint behavior **in the actual app**.
- Initial research suggests `threads_basic` and `threads_keyword_search` for search; publication permission(s) and target restrictions must be revalidated before implementation.
- An endpoint existing in documentation does not guarantee arbitrary replies to every public post or production access.
- Feature flag `threads_reply_publish_enabled=false` until approved and tested.
- For Instagram: separate discovery feasibility gate; no assumption that Threads capabilities transfer.

## 9. UX / navigation
- Overview: actionable KPIs and “Needs Review” not vanity chart clutter.
- Discover: keyword groups, run status, search history, connection health.
- Lead Inbox: list-detail pattern with priority, context, notes, archive/snooze.
- Review Queue: post context, AI rationale, tone selector, editable reply, approve/reject.
- Activity: audit timeline, manual vs API publishing distinguishable.
- Settings: social accounts, AI controls, schedule, retention, data removal.

## 10. Data and privacy
Store minimal post snapshot: external post ID, platform, author handle, text excerpt/full text only where permitted, permalink, published time, discovery time. Protect tokens using encryption at rest; never log tokens. Add explicit retention policy (initial suggestion: purge raw unqualified snapshots after 90 days; retain redacted audit metadata for limited business need; configure final intervals after policy review). Support manual delete/purge, disconnect account, account scope controls. No user private messages or private profiles.

## 11. Nonfunctional requirements
| Area | Requirement |
|---|---|
| Security | auth, policy authorization, CSRF, encrypted credentials, secret rotation, least-privilege scopes |
| Reliability | idempotent ingestion, transaction boundaries, bounded retry, exponential backoff + jitter, explicit unknown states |
| Performance | paginated Inbox, indexed filters; web request must not wait on AI/discovery batch |
| Scalability | Redis queues by workload, horizontally scalable workers, DB constraints, provider throttles |
| Observability | job ID, run ID, correlation ID, latency, error classes, permission status, backlog, structured logs |
| Accessibility | keyboard access, 4.5:1 contrast body text, labels, focus states, no color-only meaning |
| Responsive | usable desktop and mobile without hidden essential actions |
| Testability | mock external HTTP/AI, feature tests, state-machine tests, race-condition tests |

## 12. Success metrics (30 days after launch)
- Search success rate and cause breakdown.
- Qualified posts reviewed/week (baseline first, no invented target).
- Precision@20 for AI qualification, operator-reviewed.
- Draft acceptance/edit rate, median time from discovery to approval.
- Publish outcome distribution: manually posted, API verified, rejected, failed, verification required.
- Zero unapproved sends and zero duplicate verified sends (hard quality goals).
- API failure rate and AI usage cost/limits.

## 13. Risks and mitigations
| Risk | Mitigation |
|---|---|
| API scopes denied / restricted | Early feasibility spike; manual fallback; postpone unapproved feature |
| Spam/brand damage | Mandatory approval; context-aware drafts; suggested daily cap, no mass automation |
| AI hallucination or prompt injection | Strict system prompt, JSON validation, untrusted source boundary, human review |
| Duplicate submissions on timeout | Unique dispatch key, `verification_required`, reconciliation before any retry |
| Token leak | Encryption, secret masking, short scope, logout/disconnect, access control |
| Provider outage | queue retries bounded, visible errors, manual workflow |
| Data retention / ToS changes | privacy review, data minimization, configurable purge, policy re-check |

## 14. Releases & dependencies
**Gate 0:** Threads API discovery feasibility passed.  
**MVP:** manual-response workflow end to end.  
**MVP+:** queue, monitoring, score tuning, dashboard.  
**Conditional:** verified API-based reply.  
**Future:** Instagram, multiple operator roles, multi-tenant SaaS only if demand proven.

## 15. Definition of Done
- All P0 requirements working with mock API and permitted live test.
- Authentication and approval enforced server-side.
- Unit/feature/integration tests pass; migration `up/down` and fresh migration tested.
- No unsanctioned scraping, access token exposure, duplicate sending, or publish without approval.
- User can finish real lead discovery → AI draft → review → manual reply workflow.

## 16. References / verification
- Meta official Threads Postman collection: https://www.postman.com/meta/threads/documentation/dht3nzz/threads-api
- Meta Threads docs/changelog: https://developers.facebook.com/docs/threads/
- Laravel 13 queues: https://laravel.com/docs/13.x/queues
- Laravel Horizon: https://laravel.com/docs/13.x/horizon

> Documentation is a design baseline, not a claim that your Meta app has been granted any given permission. Validate against current Meta docs and actual tokens before enabling live actions.
