# *Report a problem*: screenshot and technical details Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** a report of *Something does not work* carries a screenshot of the page, the errors and failed requests of the browser, the last pages, the facts of the device and of the app, and the server errors of its account; every request has an id in the log.

**Architecture:** on the server a global middleware gives every request an id (`Context` + `X-Request-Id`), a listener adds the account to the `Context`, and the report callback of `withExceptions` keeps every reported error as a short row in `server_errors` (pruned after 30 days). `SubmitFeedback` saves the screenshot next to the file, the normalized details in `diagnostics` and a copy of the matching error rows in `server_errors` of the report. In the browser `lib/diagnostics.ts` collects from the start in `app.ts`; `AppLayout` takes the screenshot (`lib/screenshot.ts`, skipping `[data-no-screenshot]`) when it opens the panel and passes it to `FmFeedbackSheet`, which shows it with *What we send*. Filament shows it all on the page of the report; the email attaches the screenshot and counts the errors.

**Tech Stack:** Laravel 12 (`Context`, `MassPrunable`), Pest, Filament 5, Inertia + Vue 3, FormKit Pro, Vitest + Testing Library, a DOM-to-image library (Task 6).

**Spec:** `docs/superpowers/specs/2026-11-15-feedback-diagnostics-design.md`

## Global Constraints

- Only for kind `problem`: for `idea` the server drops `screenshot` and `diagnostics` and copies no server errors.
- Never send or store form values, page props, cookies, `localStorage`, page HTML, request or response bodies.
- Limits (browser and again on the server): 50 page errors, 20 failed requests, 10 pages, 500 characters per message, 2048 per path; server error message 500 characters; server errors copied: same account, 30 minutes before the report, plus those whose `request_id` is in the failed requests sent.
- `server_errors` rows pruned after 30 days; they are read only through a report (no Filament page listing them).
- Request id: always a new UUID, an incoming `X-Request-Id` is ignored.
- A failure of the capture, the buffer or the error row never blocks the report or hides the error.
- Everything in English with Italian in `lang/it.json` (new keys added by hand before the final `}`, never with a JSON parser; `git diff --stat lang/it.json` shows only added lines).
- TDD; `declare(strict_types=1)`, `final`, PHPDoc/TSDoc on everything (`@group` on components), PHPStan 9, no `any`, classes in code through `cn()`.
- Work in the worktree `.claude/worktrees/feedback-diagnostics` (other sessions have open changes on `dev`). Pest one at a time with its own database: `docker exec -u sail -e DB_DATABASE=testing_fdiag -w /var/www/html/.claude/worktrees/feedback-diagnostics family-manager-app-laravel.test-1 php artisan test --filter=…`; Vitest with `… npx vitest run <files>`. Full suites only before the last commit.
- Boards: `project/Segnala.dc.html` and `project/Segnala-pc.dc.html`, updated in Task 0 and saved in `.superpowers/sdd/feedback-diagnostics/boards/project/`; Task 7 reads them from there.

## Review Focus

- A report sent with the screenshot removed, or with a capture that failed, arrives without screenshot and nothing breaks → test in Task 3 (no `screenshot` field) and Task 7 (capture resolves `null`).
- `diagnostics` that is not JSON, is too big (> 64 KB) or has lists far over the limits → refused or cut, never a 500 → tests in Task 3.
- An error of another account or of another family in the same 30 minutes is never copied into the report → test in Task 3.
- Switching to *I have an idea* after the screenshot was taken, then sending: nothing technical leaves; back to *Something does not work* the screenshot is there again → test in Task 7.
- An error thrown before sign-in (guest page) still gets a row, with no user and no family → test in Task 2.

---

### Task 0: The boards of the panel (coordinator, with a fork)

Not code: a fork of the coordinator updates `Segnala` and `Segnala-pc` on the canvas (`https://claude.ai/artifact/XwCgtJdtBEgWrGDbLAmRrr`) with, for *Something does not work*: the thumbnail row *Photo of this page* with *Remove* (above the dashed *Add a file (optional)*), the closed row *What we send (technical details)* and one opened state, and the new note «Along with the message we send a photo of this page and some technical details, which you can read above. We answer you by email.». *I have an idea* stays as today. Colours with `var(--…)`, existing components (`FmRow`-like rows, `FmButton` text). Saves copies in the plan folder.

- [ ] Fork draws, publishes, saves `.superpowers/sdd/feedback-diagnostics/boards/project/Segnala.dc.html` and `Segnala-pc.dc.html`.
- [ ] Coordinator checks the boards against the spec (no field the panel does not have).

### Task 1: Request id and account in the log context

**Files:**
- Create: `app/Http/Middleware/AssignRequestId.php`, `app/Listeners/AddAccountToLogContext.php`
- Modify: `bootstrap/app.php` (`$middleware->prepend(AssignRequestId::class)`, global)
- Test: `tests/Feature/RequestIdTest.php`

**Interfaces:**
- Produces: `Context` keys `request_id` (UUID string), `user_id` (int), `family_id` (int); response header `X-Request-Id`. `AssignRequestId::HEADER = 'X-Request-Id'`.

- [ ] **Write the failing tests** in `RequestIdTest`:
  - `every response carries a new request id`: two `GET /login` → each `X-Request-Id` is a UUID (`Str::isUuid`) and they differ.
  - `an incoming request id is ignored`: `withHeader('X-Request-Id', 'chosen')` → response header ≠ `'chosen'`.
  - `the request id is in the context of the request`: a test route (`Route::get('/_ctx', fn () => Context::get('request_id'))` registered in the test with `web`) → body equals the header.
  - `a signed-in account is in the context`: same idea with `user_id`/`family_id` for a Bianchi member; a guest has neither (`Context::has` false).
- [ ] Run, see them fail (`--filter=RequestIdTest`).
- [ ] Implement `AssignRequestId::handle(Request, Closure): Response` and `AddAccountToLogContext::handle(Authenticated $event): void` (only for a `User`; an `Owner` adds nothing). Listeners in `app/Listeners` are discovered: no `Event::listen`.
- [ ] Green; commit `Request id in every log line and in X-Request-Id`.

### Task 2: Server errors kept as short rows

**Files:**
- Create: migration `create_server_errors_table`, `app/Models/ServerError.php`, `database/factories/ServerErrorFactory.php`, `app/Actions/Diagnostics/RecordServerError.php`
- Modify: `bootstrap/app.php` (`withExceptions`: `$exceptions->report(fn (Throwable $e) => app(RecordServerError::class)->handle($e));` with no `->stop()`), `AGENTS.md` (exception list of the family rule: `ServerError::forReport()` and `ServerError::prunable()` read rows without a family), model PHPDoc
- Test: `tests/Feature/Diagnostics/ServerErrorTest.php`

**Interfaces:**
- Consumes: `Context` keys of Task 1.
- Produces: table `server_errors` (`id`, `request_id` string(36) nullable index, `user_id` nullable index, `family_id` nullable, `method` string(10) nullable, `path` string(2048) nullable, `class`, `message` string(500), `file` string(500) nullable, `line` unsigned int nullable, `created_at`). `ServerError` is **not** a family model (like `MailInboxCall`), `MassPrunable` after 30 days.
- Produces: `ServerError::forReport(int $userId, CarbonInterface $sentAt, list<string> $requestIds): Collection<int, ServerError>` (account rows of the 30 minutes up to `$sentAt`, plus rows whose `request_id` is in the list **and** whose `user_id` is that account; oldest first).
- Produces: `ServerError::toReportArray(): array{at: string, request_id: ?string, method: ?string, path: ?string, class: string, message: string, file: ?string, line: ?int}` (`at` ISO 8601).

- [ ] **Write the failing tests**:
  - `a reported error leaves a row`: a test route that throws `RuntimeException(str_repeat('x', 900))`, called by a Bianchi member → one row with request id = response header, user, family, `GET`, path, class, message of 500 characters, file and line of the throw.
  - `an error before sign-in has no account`: guest calls the throwing route → row with `user_id` and `family_id` null.
  - `404, 403 and 422 leave no row`.
  - `a row that cannot be written does not hide the error`: `ServerError::creating(fn () => throw new RuntimeException('db'))` → the route still answers 500 and `Log` received the original error (`Log::spy()` / `Log::shouldReceive('error')` on the original message).
  - `rows older than 30 days are pruned`: `$this->travel(31)->days()`, `artisan model:prune --model=…` → gone; a 29-day row stays.
  - `forReport takes only the account's rows of the 30 minutes before, and its failed requests`: rows of another account and family in the same minutes, one of the account 31 minutes before, one of the account 40 minutes before whose `request_id` is listed, one of another account whose `request_id` is listed → only the account's in-window row and the listed own row.
- [ ] Run, fail.
- [ ] Implement; `RecordServerError::handle(Throwable $e): void` reads `Context` and `request()` only when the app has a request (console: method and path null), and swallows any `Throwable` of its own.
- [ ] Green; commit `Server errors kept for 30 days to read with a report`.

### Task 3: The report saves screenshot, details and server errors

**Files:**
- Create: migration `add_diagnostics_to_feedback_reports_table` (`screenshot_path` nullable, `screenshot_size` unsigned int nullable, `diagnostics` json nullable, `server_errors` json nullable), `app/Support/FeedbackDiagnostics.php`
- Modify: `app/Http/Requests/StoreFeedbackRequest.php`, `app/Http/Controllers/FeedbackController.php`, `app/Actions/Feedback/SubmitFeedback.php`, `app/Models/FeedbackReport.php` (casts `array`, PHPDoc), `config/app.php` (`'version' => env('SOURCE_COMMIT', 'dev')`), `database/factories/FeedbackReportFactory.php`
- Test: `tests/Feature/FeedbackTest.php`, `tests/Unit/Support/FeedbackDiagnosticsTest.php`

**Interfaces:**
- Consumes: `ServerError::forReport()`, `toReportArray()` (Task 2).
- Produces: request fields `screenshot` (`exclude_unless:kind,problem`, nullable, `image`, `mimes:webp,jpeg,png`, `max:` `MAX_FILE_KB`) and `diagnostics` (`exclude_unless:kind,problem`, nullable, string, `max:65536`, `json`).
- Produces: `FeedbackDiagnostics::normalize(array<mixed> $input, string $serverVersion): array` returning the shape below, every list cut to its limit (keeping the **last** entries) and every string cut; unknown keys dropped; wrong types become null/empty.

```
client_build: ?string, server_version: string,
viewport: {width: ?int, height: ?int, dpr: ?float, orientation: ?string},
phone: ?bool, ios: ?bool, standalone: ?bool, theme: ?string, locale: ?string,
online: ?bool, connection: ?string, page_age_seconds: ?int, component: ?string,
pages: list<{at: string, path: string, component: ?string}>,          // ≤10
console: list<{at: string, level: 'error'|'warn', message: string, component: ?string}>,  // ≤50, message ≤500
requests: list<{at: string, method: string, path: string, status: ?int, request_id: ?string}>  // ≤20, path ≤2048
```

- Produces: `SubmitFeedback::handle(User $sender, array $data, ?UploadedFile $file, ?UploadedFile $screenshot = null, ?array $diagnostics = null): FeedbackReport` — stores the screenshot in `FileStore::feedbackFolder()`, saves `diagnostics` normalized and `server_errors` from `ServerError::forReport($sender->id, now(), request ids of diagnostics.requests)` mapped with `toReportArray()`; for `idea` all four columns stay null.

- [ ] **Write the failing tests** in `FeedbackTest` (with the existing `reportFields()`):
  - `a problem saves the screenshot, the details and the server errors of the account`: WebP `UploadedFile::fake()->image('page.webp')`, `diagnostics` JSON with one console entry and one request with `request_id` of an own error row of 40 minutes ago; one own row 5 minutes ago; one row of another family → screenshot on `local` under `feedback/<family>/`, `diagnostics.console[0].message` kept, `diagnostics.server_version` = `config('app.version')`, `server_errors` has exactly the two own rows.
  - `an idea keeps nothing technical`: same fields with `kind=idea` → four columns null, no screenshot file on disk.
  - `a problem without screenshot or details is still saved`.
  - `details that are not JSON, or over 64 KB, are refused with a message, nothing saved` (dataset).
  - `a screenshot that is not an image is refused`.
- [ ] **Write the failing unit tests** `FeedbackDiagnosticsTest`: 80 console entries → last 50; message of 900 → 500; 30 requests → last 20; 15 pages → last 10; unknown key `cookies` dropped; `viewport.width: "wide"` → null; `level: 'info'` entry dropped.
- [ ] Run, fail.
- [ ] Implement; the controller decodes `diagnostics` (`json_decode(…, true)`, array or null) and passes `screenshot`.
- [ ] Green; commit `Report of a problem: screenshot, details and server errors saved`.

### Task 4: Owner panel and email

**Files:**
- Create: `app/Http/Controllers/Admin/FeedbackFileController.php`, `resources/views/filament/feedback/diagnostics.blade.php` (or Filament entries: builder's call)
- Modify: `routes/web.php` (`GET admin-files/feedback/{report}/{part}` with `auth:owner`, `part` in `screenshot|file`, name `owner.feedback.file`), `app/Filament/Resources/FeedbackReports/FeedbackReportResource.php` (infolist), `app/Filament/Resources/FeedbackReports/Pages/ViewFeedbackReport.php` (keep or replace *Download the file*: builder's call), `app/Mail/FeedbackReportMail.php`, `resources/views/mail/feedback-report.blade.php`, `lang/it.json`
- Test: `tests/Feature/Admin/FeedbackReportsTest.php`, `tests/Feature/FeedbackTest.php` (mail)

**Interfaces:**
- Consumes: columns of Task 3; `FeedbackReports::query()` (scope lifted, existing) to find the report by id.
- Produces: `route('owner.feedback.file', [$report, 'screenshot'])` streams the image inline (`Content-Disposition: inline`), `'file'` streams the file; 404 when the part is missing.

- [ ] **Write the failing tests**:
  - `the page of a problem shows the screenshot, device, path, page errors, failed requests and server errors`: report from the factory with all four columns → `assertSee` the screenshot URL, the viewport (`390 × 844`), `standalone` as «Installed app» / «Browser», a path, a console message, `POST /documents/42 · 500`, the request id, an error class; the client build and server version, with «Different from the server» when they differ.
  - `an idea shows no technical sections`.
  - `the owner opens the screenshot and the file; a family account and a guest cannot` (owner 200 inline image; `User` → redirect/403; guest → redirect), `a missing part is 404`.
  - Mail (`FeedbackTest`): `the email of a problem attaches the screenshot and counts the errors` → two attachments when there are file and screenshot; body has «2 page errors, 1 failed request, 1 server error — details in the panel» (`transChoice` per count, keys with `:count`); an idea has no count line.
- [ ] Run, fail.
- [ ] Implement; Italian texts in `lang/it.json`.
- [ ] Green; commit `Owner panel and email show the screenshot and the technical details`.

### Task 5: The diagnostics buffer in the browser

**Files:**
- Create: `resources/js/lib/diagnostics.ts`, `resources/js/lib/diagnostics.test.ts`
- Modify: `resources/js/app.ts` (`startDiagnostics()` before `createInertiaApp`; in `withApp` `app.config.errorHandler = reportVueError`), `vite.config.ts` (`define: { __APP_BUILD__: JSON.stringify(process.env.SOURCE_COMMIT ?? 'dev') }`), `resources/js/env.d.ts` or the existing globals file (`declare const __APP_BUILD__: string`)

**Interfaces:**
- Produces (types in `lib/diagnostics.ts`, same shape as `FeedbackDiagnostics` of Task 3):
  - `startDiagnostics(target?: { console: Console; window: Window; fetch?: typeof fetch }): () => void` — installs the wrappers and listeners (idempotent), returns a stop function for tests.
  - `reportVueError(error: unknown, instance: unknown, info: string): void` — records and still writes to `console.error`.
  - `recordRequest(entry: { method: string; path: string; status: number | null; requestId: string | null }): void` — used by the Inertia hook (`router.on('httpException')`, `router.on('networkError')`) and the `fetch` wrapper (status ≥ 400 or rejected).
  - `collectDiagnostics(): FeedbackDiagnostics` — buffer plus facts of the moment (`isPhone`, `isIos`, `isStandalone` from `lib/devices.ts`, theme from `<html class="dark">`, `document.documentElement.lang`, `navigator.onLine`, `navigator.connection?.effectiveType`, `performance.now()`, `__APP_BUILD__`, component from `usePage()`).
  - Limits as constants: `MAX_CONSOLE = 50`, `MAX_REQUESTS = 20`, `MAX_PAGES = 10`, `MAX_MESSAGE = 500`.

- [ ] **Write the failing tests**:
  - `console.error and console.warn are kept and still printed` (fake console object passed to `startDiagnostics`).
  - `an uncaught error and an unhandled rejection are kept` (dispatch `ErrorEvent` / `PromiseRejectionEvent`-like event on the window).
  - `only the last 50 entries are kept, each cut to 500 characters`.
  - `a failed fetch keeps method, path, status and the X-Request-Id of the response; a fetch that succeeds is not kept`.
  - `the last 10 pages are kept` (emit Inertia `navigate` events as Inertia sends them).
  - `the buffer never throws`: `console.error(circularObject)` and a `Symbol` argument are recorded as text.
  - `collectDiagnostics has the facts of the device and the build`.
- [ ] Run, fail; implement; green.
- [ ] Commit `Browser diagnostics collected from the start of the app`.

### Task 6: The screenshot of the page

**Files:**
- Create: `resources/js/lib/screenshot.ts`, `resources/js/lib/screenshot.test.ts`
- Modify: `package.json` (+ lock; restore `"name": "html"` if the worktree rewrites it), `resources/js/components/fm/FmBottomSheet.vue` (`data-no-screenshot` on overlay and content), the tablet menu dialog in `AppLayout.vue`, the `Toaster` wrapper (`data-no-screenshot`), `resources/js/layouts/AppLayout.vue` (`openFeedback` starts `captureScreenshot()` and passes the promise's result to the sheet as prop `screenshot`)
- Test: `resources/js/layouts/AppLayout.test.ts`

**Interfaces:**
- Produces: `captureScreenshot(options?: { timeoutMs?: number }): Promise<File | null>` — imports the library with `import()`, renders `document.body` at the viewport size, skips nodes for which `skipNode(node)` is true, writes WebP (JPEG if `toBlob` gives no WebP), scaled so the longer side is at most 1600 px, resolves `null` on any error or after `timeoutMs` (default 4000); `File` named `page.webp` / `page.jpg`.
- Produces: `skipNode(node: Node): boolean` — true for an element with `data-no-screenshot`, `role="dialog"`, `role="menu"` or inside one of them.
- Produces: `FmFeedbackSheet` prop `screenshot?: File | null` (Task 7 consumes).
- Library: try `modern-screenshot` and `html-to-image` on the Home, a document with its PDF preview and *More* open on the phone; pick one, write the reason in *Decisions taken* of the plan (end of this file).

- [ ] **Write the failing tests**: `skipNode` cases (dialog, menu, attribute, child of a dialog, plain `main`); `captureScreenshot resolves null when the library throws` and `…after the time limit` (the library import is the boundary: `vi.mock` of the library module only here); `AppLayout`: choosing *Report a problem or an idea* opens the panel at once and then shows the screenshot thumbnail (with `@/lib/screenshot` replaced by one resolving a fixed `File`).
- [ ] Run, fail; implement; green.
- [ ] Commit `Screenshot of the page when the report panel opens`.

### Task 7: The panel: thumbnail and *What we send*

**Files:**
- Modify: `resources/js/components/fm/FmFeedbackSheet.vue`, `resources/js/components/fm/FmFeedbackSheet.test.ts`, `lang/it.json`
- Read: `.superpowers/sdd/feedback-diagnostics/boards/project/Segnala.dc.html`, `Segnala-pc.dc.html`

**Interfaces:**
- Consumes: prop `screenshot` (Task 6), `collectDiagnostics()` (Task 5).
- Produces: POST fields `screenshot` and `diagnostics` (JSON string) only for `problem`.

- [ ] **Write the failing tests** (spying `router.post`, as the existing ones):
  - `a problem shows the photo of this page with Remove and sends it with the details`: thumbnail `img` with name «Photo of this page»; *Send* → `screenshot` is the file, `diagnostics` parses to an object with `console`.
  - `Remove takes the photo away and it is not sent`; the file picker still works and both a file and the photo are sent.
  - `What we send opens and lists the page, the last pages, the errors and the device` (texts as the board).
  - `I have an idea hides the photo and What we send and sends neither; back to Something does not work they are there again`.
  - `without a screenshot (capture failed) the panel has no thumbnail and sends without it`.
  - `the note changes with the kind` (problem: new text; idea: today's).
  - A screenshot arriving while the panel is open appears without resetting what was written.
- [ ] Run, fail.
- [ ] Implement following the boards (thumbnail with `URL.createObjectURL`, revoked on change/unmount; `FmButton` text *Remove*; *What we send* as a disclosure button with `aria-expanded`).
- [ ] Green, then the tests of `AppLayout` again; commit `Report panel: photo of the page and what we send`.

### Task 8: Docs and checks

**Files:**
- Modify: `AGENTS.md` (*Pubblicazione*: `SOURCE_COMMIT` must reach build and runtime on Coolify, check at the first deploy that `config('app.version')` and the client build match), `.claude/learnings.md` (what was found), this plan's *Decisions taken*

- [ ] Full checks in the worktree (`npm run build` first): Pest `--parallel --processes=6` and Vitest together, PHPStan, Rector `--dry-run`, Pint, `npm run types`, `npm run lint`, `vp check --fix`, `typescript:transform` unchanged or committed.
- [ ] Try it in a headless Chromium on the worktree (port 8091, own cookies): a report from the Home on phone and computer, a report from *More*; the screenshot shows the page without the panels; the owner page shows it all.
- [ ] Docs in the vault with `project-docs`; commit; finish the branch.

## Decisions taken

- (Task 6) modern-screenshot 4.7.0: maintained fork of html-to-image with a node filter and WebP/JPEG via the canvas; loaded only on the click. To be confirmed on real pages in Task 8.
- (Task 7) The panel shows paths, not page titles, under the thumbnail and in *Last pages*, and the Device line has no browser name (size and theme only), because the diagnostics keep only paths and no user agent (the server stores the user agent separately). The remove button is icon-only, as on the board.
- (Task 8) modern-screenshot confirmed in headless Chromium on the Agenda (phone, from *More*, scrolled), the Agenda (computer, from the account menu, scrolled) and a document with its PDF preview: the photo matches the screen once the clone root gets `margin: 0` and `overflow: visible` (an open panel puts `overflow: hidden` on the body, and the photo was empty) and the clone of a sticky element is `relative` (the ruling of Task 6: the phone bottom bar now sits at the bottom edge). html-to-image was not tried: the same clone-and-foreignObject approach, and the fixes are options of the root clone either way.
- (Task 8) A server error of a queued job (no method, no path) is shown in the owner panel as time · request id, without empty parts.
