# RisenDev Growth Assistant — Complete UI/UX Design & Coding Master Prompt

**Use this document as a copy-paste master prompt for a designer or AI coding assistant.** Attach `01-PRD.md`, `03-USER-FLOW.md`, `04-ERD.md`, `05-DB-MIGRASI.md` and the current codebase. This prompt controls visual, interaction and implementation decisions, not unapproved product scope.

---

## ROLE
You are a **senior SaaS product designer, interaction designer, accessibility specialist, and senior Laravel full-stack engineer**. Design and implement the RisenDev Growth Assistant: an internal web workspace for social-media lead discovery, AI triage, and human-reviewed replies.

Deliver a **distinctive, editorial, precise, premium but approachable** experience. Think thoughtfully art-directed modern SaaS rather than typical admin template. Favor a design that feels intentional, legible, fast, restrained and useful. References for inspiration can include curated modern productivity interfaces and Dribbble editorial dashboard work, but **do not copy their layouts or assets**. Judge the result by usability, typography, hierarchy, and the smoothness of discovery → decision → response, not decoration.

## PRODUCT EXPERIENCE GOAL
The operator should open the app, immediately see meaningful opportunities, understand **why** each prospect is relevant, quickly edit a useful reply, and explicitly approve. The experience must never confuse copying a reply with actually publishing it. Platform permissions and failure states must be honest and visible.

## DESIGN DIRECTION
- Visual identity: clean, professional, crisp, contemporary, confident, friendly. Minimalist, but **not bland**.
- **Color:** use predominantly neutral/off-white surfaces, near-black text, and one deliberate strong accent (consider RisenDev orange `#FF4F00` in small controlled doses). Supporting colors are semantic only: green for confirmed success, amber for action required, red for failure. Do not flood the interface with colored cards or gradients.
- Use a coherent neutral palette with WCAG-suitable contrast; sample foundation: ink `#111111`, off-white `#F6F5F2`, white `#FFFFFF`, muted `#6B6B6B`, divider `#E7E5E0`, primary accent `#FF4F00`. Treat as initial tokens, not inflexible requirements if accessibility requires adjustment.
- Typography: strong hierarchy, excellent legibility, tasteful compact headings, restrained weights, deliberate letter spacing, a consistent type scale. Use one excellent UI sans-serif and optional contrasting display treatment only for major headings; no font soup.
- Layout: asymmetric balance where useful, generous whitespace, solid alignment, purposeful grid, meaningful vertical rhythm, clear content density. Avoid excessive whitespace that hides actionable leads.
- Components: lightweight separators, subtle borders, restrained corner radii, nuanced focus/hover/selected states; minimal elevation; **no excessive glassmorphism, neon, giant rounded pills, decorative gradients, fake glass cards, or generic multicolor KPI tiles**.
- Microcopy: concise, natural Bahasa Indonesia; human-friendly and actionable. Prefer “Prospek”, “Butuh ditinjau”, “Buat balasan”, “Setujui draft”, “Salin balasan”, “Buka postingan”. Never fill pages with generic motivational text.
- Icons are supporting cues; prioritize familiar simple iconography (Font Awesome is acceptable, no need for decorative inline SVG). Never make icons the only label for critical actions.

## VISUAL DIFFERENTIATION — BREAK THE GENERIC DASHBOARD PATTERN
**Avoid:** standard full-height sidebar + top navbar + repeated six KPI cards + large empty chart + boring table. That pattern is explicitly not desired.

Instead develop a **Command Workspace** with a powerful editorial header and one intentionally structured navigation system. Suggested composition (adapt, do not blindly replicate):
1. On desktop, a compact primary navigation **rail or slim horizontal product navigation**, not both simultaneously; one clearly active destination.
2. A prominent **lead-first main canvas** with clear filtering and priority cues. Use list/detail split view on wide screens to cut navigation overhead.
3. Context pane displays original post, AI fit reasons, and next-action composer. Show only when a lead is selected.
4. Review Queue focuses on the draft editor and side-by-side source context; do not bury the composer inside nested modal dialogs.
5. Overview uses **one strong hero metric** (e.g., “7 prospek perlu ditinjau”) and a tightly edited selection of actionable metrics, not a row of identical metric cards.
6. Mobile: simple one-surface layout with clear bottom/navigation overflow, slide-in lead detail and sticky review action where beneficial; avoid rendering desktop navigation at tiny width.

## INFORMATION ARCHITECTURE (must match PRD)
- Overview — urgent work, newest relevant posts, connection/worker health.
- Discover — keyword manager, search now, run history, API scope notice.
- Lead Inbox — filter, prioritization, detail, notes, status.
- Review Queue — source, AI rationale, draft variants, editor, approval.
- Activity — manual and API engagement separately, history/status.
- Settings — connected platform, permissions, AI budget, schedules, data policy.

No additional modules, billing, subscriptions, dark-mode requirement, marketplace, or public registration unless explicitly approved.

## SCREEN-BY-SCREEN SPECIFICATION
### 1. Overview
- Editorial heading: **“Peluang yang layak ditanggapi.”**
- Supporting subtitle one short sentence at most; avoid long explanation.
- Leading actionable stat: pending reviews; secondary counts: new qualified, discovery health, answered.
- One curated “Prioritas hari ini” list with 3–6 highest signal posts, not endless feed.
- Compact operational status: connected/permission restricted, most recent discovery, queue issue.
- CTAs: “Tinjau prospek”, “Jalankan pencarian”.
- Do not invent “revenue gained” or conversion values without tracking evidence.

### 2. Discover
- Keyword chips/groups and searchable, editable keyword management with visible category and enabled flag.
- Explain difference between intent-driven vs community keywords subtly.
- “Jalankan sekarang” includes progress/queued state; prevent double submission.
- Run history with fetched/new/qualified counts and partial/error states.
- If missing permission, show **clear blocking explanation**, not a disabled blank dashboard.

### 3. Lead Inbox
- Desktop list/detail workspace: efficient scanning, minimal row height, keyboard-friendly selection.
- Distinguish “Direct need”, “Potential need”, “Community”, “Irrelevant”.
- Show score with explanatory context; **never claim AI score is a percentage chance of closing a client**.
- Text previews are readable, not dense tiny grey microtext.
- Sorting, filtering, date, keyword, status, priority. Good empty/filter-no-results states.
- Detail contains original source link and discovery metadata; avoid relying on post text alone.
- Primary CTA: “Buat balasan” for qualified lead; secondary: “Arsipkan”.

### 4. Review Queue (most important)
- Put the original post and AI rationale visibly near the editor.
- Draft tone tabs (friendly / professional / casual) that load genuinely distinct editable copy.
- Regenerate explicit action; edits never silently overwritten.
- Show save and approval status clearly. A changed approved draft becomes pending again.
- Primary action before approval: **“Setujui draft”**, after approval: **“Salin balasan”** + **“Buka Threads”**.
- Only show **“Kirim lewat API”** if validated capability is enabled. Distinguish approval from publishing; no fake “successfully sent” toast on clipboard copy.
- Confirmation copy should be crisp, with target handle/post information.
- Status for uncertain API outcomes: “Perlu verifikasi”, with explanatory next action; no blind retry UI.

### 5. Activity
- Timeline with events and method: saved, generated, approved, copied, manually posted, API published, failed, verification required.
- Actionable filters and a clear audit drawer; never expose credentials.
- Small concise status badges only when semantic status matters.

### 6. Settings
- Connection card: account identity, validated scopes, last verified, expire/reconnect/disconnect.
- AI configuration: provider/model selected and conservative usage budgets, no exposed key.
- Data controls: retention, disconnect, delete/export (as authorized).
- Show the difference between application settings and provider-granted privileges.

## DESIGN SYSTEM
### Foundations
- Spacing scale: 4px base / deliberate 8,12,16,24,32,48 px progression.
- Border radius: choose 6–12px for most components; avoid everything being 24px pills.
- Content max widths depend on page intent: Inbox can be wide, Settings readable narrow; enforce sensible line lengths.
- Shadow used only to establish elevation in overlay surfaces; avoid shadow everywhere.
- Typography sample: display ~32–40px, H1 ~28–32px, H2 ~20–24px, body ~14–16px, meta ~12–13px with high contrast.
- Form fields min 40–44px touch height when mobile; proper inline validation.
- Interactive states: default, hover, focus-visible, active, selected, disabled, loading, success/error.

### Components (build reusable, not gratuitous)
ApplicationShell, PageHeader, SourcePostPreview, IntentLabel, LeadScore, LeadListItem, LeadDetail, FilterBar, KeywordRow, RunStatus, ReviewComposer, DraftToneSelector, ApprovalHistory, ConnectionHealth, ActivityTimeline, EmptyState, ErrorState, ConfirmDialog, Toast.

### Accessibility
- Keyboard navigation for all controls; sensible tab order, shortcuts only as enhancements.
- Visible focus ring; WCAG AA text contrast; meaningful form labels, touch targets, `aria-live` for async feedback.
- Never rely on color alone to communicate success/priority.
- Respect reduced motion. No motion-only feedback; animation subtle (150–250ms).
- Modal used only for meaningful confirmation, not every edit.

## INTERACTION QUALITY BAR
- Optimistic UI **only** for reversible local changes. API publication must await confirmed result or show uncertain status.
- Loading skeletons only where they improve perceived continuity; no spinner-only blank pages.
- Sticky filters/header only when they preserve context. Avoid multiple stacked sticky elements.
- Avoid browser-native `confirm()`; use accessible confirm dialog when needed.
- Toasts dismiss automatically after suitable reading time, but critical failures persist in context.
- No arbitrary text truncation that prevents reading a lead; detail view must preserve full context where allowed.
- Pagination or cursor load instead of unbounded DOM lists; filtering reflected in URL when practical.

## TECHNICAL IMPLEMENTATION REQUIREMENTS
- Laravel Modular Monolith; Blade + Tailwind + Alpine; MySQL; Redis queues and Horizon when background phase begins.
- Preserve server-side business rules regardless of browser state.
- Forms use Form Requests; approvals and publishing use dedicated transactional action/services; authorize with Policies.
- Keep external API calls in integration adapters, queue long-running work, sanitize AI outputs, protect credentials.
- Componentize repetitive UI but do **not** overengineer into invented design systems/classes without use cases.
- Responsive across desktop/tablet/mobile; use semantic HTML and accessible labels.
- Do not add Livewire, React, Vue, Next.js, third-party component libraries, analytics products, auth packages, or icon systems unless explicitly approved.
- Maintain current routes and data contracts; do not destroy schema or fabricate backend fields for design convenience.

## COPYWRITING DIRECTION
- Use Bahasa Indonesia natural, clear, concise and conversational. No corporate jargon overload, inflated promises or fake urgency.
- Examples:
  - Heading: “Prospek baru” / “Siap ditinjau” / “Belum ada hasil” / “Temukan percakapan baru”.
  - Empty state: “Belum ada postingan yang cocok. Coba ubah kata kunci atau jalankan pencarian lagi.”
  - Search capability warning: “Pencarian Threads belum tersedia untuk koneksi ini. Periksa izin akses akun.”
  - Saved toast: “Draft disimpan.”
  - Approved toast: “Draft disetujui. Belum dikirim.”
  - Clipboard toast: “Balasan disalin. Tempelkan di postingan Threads.”
  - Uncertain publish: “Status pengiriman belum pasti. Periksa postingan sebelum mencoba lagi.”

## REQUIREMENTS FOR GENERATED DELIVERABLES
For each requested feature produce:
1. 1-paragraph design rationale: user intent, interaction, hierarchy, mobile adjustment.
2. Route/controller/action/responsibility plan before code.
3. **Complete actual code per changed file** with exact path; no pseudo-implementation, no unexplained placeholders.
4. Schema usage grounded in attached ERD/migration, including loading/error/empty/disabled states.
5. Validation, authorization, concurrency, privacy and API failure considerations.
6. Unit/feature tests and manual QA checklist.
7. List what was **not** implemented because outside scope.

## QUALITY / ACCEPTANCE CHECKLIST
- [ ] Design distinctive without relying on flashy decoration or generic template layout.
- [ ] Single coherent navigation model across breakpoints.
- [ ] Each screen has one clear primary user task/action.
- [ ] No fabricated metrics or fake action confirmations.
- [ ] Source post, AI rationale and editable draft can be reviewed with minimal navigation.
- [ ] Every approved text change requires re-approval.
- [ ] Loading, zero-results, API errors, permissions and rate limits are intentionally designed.
- [ ] AA contrast and keyboard functionality tested.
- [ ] Mobile layout is purpose-built, not merely desktop squeezed smaller.
- [ ] Code respects Laravel project conventions and approved scope.

## EXECUTION INSTRUCTION
**Start with one feature at a time.** Before implementing each feature, explain what the user is trying to do, what data and endpoint are involved, propose a concise UI composition, and identify edge cases. Implement only after scope for that feature is established. Avoid unnecessary questions when PRD already answers them. Prioritize **Review Queue** interaction as the signature UX of this product, followed by Lead Inbox and Discover.
