# *Report a problem or an idea* Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** an item in the account menu (sidebar) and in *More* (phone) opens a panel that saves a report, emails it to the owner and lists it in `/admin`.

**Architecture:** `POST feedback` → `StoreFeedbackRequest` → `SubmitFeedback` (file on the private disk, row in `feedback_reports`, queued `FeedbackReportMail`). The owner reads the rows in a read-only Filament resource whose query lives in `app/Admin/FeedbackReports.php`. In the pages one `FmFeedbackSheet` lives in `AppLayout`; `FmSidebar` and `FmBottomBar` emit `report`.

**Tech Stack:** Laravel 12, Pest, Filament, Inertia + Vue 3, FormKit Pro, reka-ui, Vitest + Testing Library.

**Spec:** `docs/superpowers/specs/2026-11-15-feedback-report-design.md`

## Global Constraints

- Everything in English, Italian in `lang/it.json` (`TranslationsTest`); enum labels in `lang/{en,it}/enums.php`.
- TDD; `declare(strict_types=1)`, `final`, PHPDoc/TSDoc on everything, PHPStan 9, no `any`.
- Tests with `DB_DATABASE=testing_feedback` (worktree), never `migrate` on `laravel`.
- Boards: `project/Segnala.dc.html`, `project/Segnala-pc.dc.html` (downloaded in the scratchpad `boards/project/`).

## Review Focus

- A linked phone posting to `feedback` gets 403 (route not in `LimitLinkedDevice::ALLOWED`) → test in Task 1.
- A file that is not an image or PDF, or over 10 MB, is refused with a message, nothing saved, no mail → Task 1.
- The 11th report in an hour is refused (429) → Task 1.
- Opening the sheet from *More* on the phone closes *More* first (two dialogs never stacked) → Task 4.
- Cancel / closing resets the form, so the next opening starts empty with *Something does not work* → Task 3.

---

### Task 1: Saving and sending the report (server)

**Files:**
- Create: `database/migrations/2026_11_15_000000_create_feedback_reports_table.php`, `app/Models/FeedbackReport.php`, `database/factories/FeedbackReportFactory.php`, `app/Enums/FeedbackKind.php`, `app/Http/Requests/StoreFeedbackRequest.php`, `app/Actions/SubmitFeedback.php`, `app/Http/Controllers/FeedbackController.php`, `app/Mail/FeedbackReportMail.php`, `resources/views/mail/feedback-report.blade.php`, `app/Policies/FeedbackReportPolicy.php` (only if the guessed `FamilyPolicy` does not let a `member` create)
- Modify: `routes/web.php` (inside `auth`,`verified`, `RedirectToWelcome` group), `lang/it.json`, `lang/{en,it}/enums.php`, `tests/Feature/Schema/AllModelsIsolationTest.php`, `resources/js/generated/generated.d.ts` (enum), model PHPDoc via ide-helper
- Test: `tests/Feature/FeedbackTest.php`, `tests/Feature/LinkedDeviceLimitTest.php`

**Interfaces:**
- Produces: route `feedback.store` (`POST /feedback`), fields `kind` (`problem|idea`), `message` (string ≤5000, required), `page` (string ≤2048, required), `layout` (`phone|tablet|computer`, nullable), `file` (nullable, `mimes:jpg,jpeg,png,webp,heic,heif,gif,pdf`, `max:10240`). Flash `toast` `{type: 'success', message: 'Thanks, we will answer you by email.'}`, `back()`.
- Produces: `FeedbackReport` (columns of the spec, `kind` cast to `FeedbackKind`, `user()` BelongsTo), `FeedbackKind::Problem|Idea` with `HasLabel`.
- Produces: `FeedbackReportMail(FeedbackReport $report)`, subject `__('New report: :kind', ['kind' => label])`, `replyTo` the sender, file attached from the `local` disk.

- [ ] Write `FeedbackTest`: a `member` posts a problem with a PNG (`UploadedFile::fake()->image`, `Storage::fake('local')`, `Mail::fake()`) → row with family, user, kind, message, page, user agent (`withHeader('User-Agent', 'TestPhone')`), layout, file fields; file exists on `local` under `feedback/<family_id>/`; `FeedbackReportMail` queued to every `Owner` email, has the attachment and replyTo the member; session flash toast. Cases: without file (file columns null, no attachment); `message` missing → error, nothing saved, no mail; `.exe`/`text/plain` file and 11 MB file → error; 11th post in an hour → 429; a guest → redirect to login.
- [ ] In `LinkedDeviceLimitTest`: `POST feedback` from a linked phone → 403.
- [ ] Run, see them fail (`docker exec -u sail -e DB_DATABASE=testing_feedback -w /var/www/html/.claude/worktrees/funny-ardinghelli-679db6 family-manager-app-laravel.test-1 php artisan test --filter=Feedback`).
- [ ] Implement; add the model to `AllModelsIsolationTest`; `ide-helper:models --write --reset` on the test db is not possible → write the model PHPDoc by hand like the others; `typescript:transform`.
- [ ] Tests green; commit.

### Task 2: *Reports* in the owner panel

**Files:**
- Create: `app/Admin/FeedbackReports.php`, `app/Filament/Resources/FeedbackReports/FeedbackReportResource.php`, `.../Pages/ListFeedbackReports.php`, `.../Pages/ViewFeedbackReport.php`
- Modify: `AGENTS.md` (the exception list in the family-data rule and the owner-panel rule), `lang/it.json`
- Test: `tests/Feature/Admin/FeedbackReportsTest.php`

**Interfaces:**
- Consumes: `FeedbackReport` from Task 1.
- Produces: `FeedbackReports::query(): Builder<FeedbackReport>` (scope lifted, `with(['family:id,name','user:id,name,email'])`, newest first); resource `getEloquentQuery()` returns it; `canCreate/canEdit/canDelete` false; a *Download file* action (`Storage::disk('local')->download`) visible only with a file.

- [ ] Test (Livewire, owner with authenticator like `MailPageTest`): list shows reports of two families newest first with family, who, kind, time `d/m/Y H:i`; view shows message, page, user agent, layout; download returns the file; a family account cannot open `/admin` resource (existing `OwnerAccessTest` covers the guard; just one assertion that `/admin/feedback-reports` redirects a `User`).
- [ ] Fail → implement → green → commit.

### Task 3: `FmFeedbackSheet`

**Files:**
- Create: `resources/js/components/fm/FmFeedbackSheet.vue`, `FmFeedbackSheet.test.ts`
- Modify: `resources/design/boards.json` (`"FmFeedbackSheet": "pages"`), `lang/it.json`

**Interfaces:**
- Consumes: `feedback.store` (Wayfinder `@/routes/feedback` `store`), `useLayoutMode()`, `FmBottomSheet`, `FmButton`, `FmIcon`.
- Produces: `<FmFeedbackSheet v-model:open="…" />`, `@group forms`.

- [ ] Test: open → title *Report a problem or an idea*, radio *Something does not work* checked, *I have an idea* not; the note text; *Send* without text shows the required message and posts nothing; with text + chosen file posts multipart (`router.post` spy / `useForm`) with `kind`, `message`, `page` = `location.pathname + location.search`, `layout`, `file`; the chosen file name shows with *Remove*; *Cancel* closes and the next opening is empty with *Something does not work*; on success the sheet closes.
- [ ] Fail → implement (FormKit form; tiles = FormKit `radio` with `$reset` classes as the board: 52 px, 2 px `border-action` + `bg-action-soft` when checked, `Bug`/`Lightbulb`; file through a hidden `<input type="file" accept="image/*,application/pdf">` behind the dashed button; buttons stacked on the phone, right-aligned in `useWideLayout()`) → green → commit.

### Task 4: The items in the sidebar and in *More*, the sheet in the layout

**Files:**
- Modify: `resources/js/components/fm/navigation.ts` (+test: `feedback: { label, icon: MessageSquareWarning }` in `NavigationItems`), `FmSidebar.vue` (+test: menu item after Settings emits `report`), `FmBottomBar.vue` (+test: row after Settings, closes *More* and emits `report`), `resources/js/layouts/AppLayout.vue` (+test: `report` from either opens `FmFeedbackSheet`), TSDoc of the three components.

- [ ] Tests → fail → implement → green → commit.

### Task 5: Board, checks, notes

- [ ] Update the *Navigazione* board (`project/Navigazione.dc.html`): account menu and *More* with the new item, `var(--…)` colours; publish to the canvas.
- [ ] `npm run build`, full Pest (`DB_DATABASE=testing_feedback`), Vitest, PHPStan (own `tmpDir`), Rector (throwaway container), Pint, types, lint, `vp check --fix`; restore `package-lock.json` name if changed.
- [ ] Learnings in `.claude/learnings.md` if any; commit.
