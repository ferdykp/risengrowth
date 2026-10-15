# Page 06 — AI Comment Generator

## Implemented
- `/comments` authenticated workspace based on Stitch visual language.
- Select a lead, view its original post, author and score.
- Create manual draft.
- Optional Groq `llama-3.1-8b-instant` generation when `GROQ_API_KEY` is configured.
- Server-side validation, per-user throttle, bounded post content and AI output, safe error handling.
- Saved drafts are immutable revisions with SHA-256 and `draft` status.
- Draft history and copy interaction.
- Lead Detail page CTA to generator.
- No automatic publishing or approval.

## Setup
Set `GROQ_API_KEY` and optionally `GROQ_MODEL` in `.env`; `php artisan config:clear`.

## Notes
The AI request is synchronous and bounded; consider queue-based generation later if volume or rate limits warrant it.
No social platform integration is activated. Review/approval is implemented on Page 07.
