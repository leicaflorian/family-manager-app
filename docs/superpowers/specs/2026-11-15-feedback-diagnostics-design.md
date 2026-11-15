# *Report a problem*: screenshot and technical details

Follows `2026-11-15-feedback-report-design.md` (the panel *Report a problem or an
idea*, already in `dev`).

## Why

A report of *Something does not work* today arrives with the message, the page,
the layout and the user agent. To understand what went wrong the owner has to
write back and ask. The owner wants a report of a problem to carry, by itself,
what is needed to find the cause: what the page looked like, the errors of the
page in the browser, and the errors of the server at that moment.

Success: opening a report in the owner panel, the owner sees the page as the
person saw it, the errors of the browser and of the server around that moment,
and can find the same request in the server log.

## Goals

1. **Technical details** sent with every report of a problem: the errors of the
   page (console), the failed requests, the last pages visited and the facts of
   the device and of the app.
2. **Automatic screenshot** of the page the person was on, already attached when
   the panel opens.
3. **Server errors** linked to the report: every request has an id written in
   every line of the log, and every error the server reports is also kept as a
   short row in a table; the report keeps the errors of its account from the
   30 minutes before.

## Non-goals

- Nothing changes for *I have an idea*: no screenshot, no technical details.
- No page in the owner panel listing all the server errors: they are seen only
  inside a report (the panel reads family content only when the person sends it
  on purpose, and an error message can contain family data).
- No collection of browser errors outside a report: nothing is sent to the
  server until the person presses *Send*.
- No external error service (Sentry, Nightwatch, Flare).
- The linked phone keeps not seeing the item (unchanged).

## Anti-goals

- Never send form values, page props, cookies, `localStorage`, the HTML of the
  page or the bodies of requests and responses.
- The screenshot never shows the panel of the report, *More* or a menu: it is
  the page of the problem.
- Collecting the details must never break the app: a failure of the capture or
  of the buffer leaves the report sendable without them.
- Saving an error row must never hide the error: if it fails, the log still has
  the error.

## Owner decisions (15/11/2026)

- **Consent: everything is sent by default, visible and removable, only for
  *Something does not work*.** The screenshot is attached already, with its
  thumbnail and *Remove*; the technical details always go and can be read under
  *What we send*. Switching to *I have an idea* hides both; switching back shows
  them again.
- **Server errors: inside the report**, through a table of short error rows
  (cleaned after 30 days), **and** the request id in every line of the log.

## Approaches

Consent:
- **Chosen: A, all by default, visible and removable, problems only.** Most
  information for the owner; one click for whoever wants to remove the screenshot.
- Discarded: B, screenshot only on request. Fewer screenshots arrive.
- Discarded: C, two checkboxes. Longer panel for the same result.

Server errors:
- **Chosen: 2, error rows in a table plus the id in the log.** The owner sees
  them in the panel without opening the server terminal.
- Discarded: 1, only the id in the log. Cheaper, but every report needs a search
  in the Coolify terminal.

Screenshot: a DOM-to-canvas library loaded only on the click (`import()`), with a
node filter that skips open sheets, dialogs and menus. A real screen capture
(`getDisplayMedia`) asks for a permission and does not exist on iPhone.

## Design

### 1. In the browser: the diagnostics buffer (`lib/diagnostics.ts`)

Started in `app.ts` before `createInertiaApp`, so it sees the errors from the
first moment. It keeps, in memory only:

- **Page errors**, the last 50: `console.error` and `console.warn` (wrapped, the
  original still runs), the `window` events `error` and `unhandledrejection`, and
  Vue errors (`app.config.errorHandler`, which still writes to `console.error` as
  Vue does without a handler). Each entry: time, level, message (with the first
  lines of the stack for errors), the Inertia component of the page at that
  moment; message cut to 500 characters.
- **Failed requests**, the last 20: method, path (no query values beyond the
  path and query string the app itself builds), status, time and the
  `X-Request-Id` of the response. From Inertia visits that end in an error
  response or a network failure, and from `fetch` calls of the app (wrapped).
- **Last pages**, from `lib/visitHistory.ts` or the same `navigate` events: the
  last 10 paths with their Inertia component.

At *Send* (problem only) the panel adds the facts of the moment:
viewport width and height, `devicePixelRatio`, orientation, installed app or
browser (`isStandalone()`, `isIos()`, `isPhone()` from `lib/devices.ts`), theme,
locale, `navigator.onLine`, connection type where the browser has it, time since
the page was loaded, and the **build id** of the client (written at build time).

### 2. In the browser: the screenshot

- Taken when the person chooses *Report a problem or an idea* in the account menu
  of `FmSidebar` or in *More* of `FmBottomBar`, before the panel opens, skipping
  the nodes of open sheets, dialogs, menus and toasts (the filter of the
  library), so *More* closing does not appear.
- The viewport only (what the person sees), as WebP (JPEG where the browser
  cannot write WebP), scaled so the file stays well under the limit of the
  request.
- The panel shows it as a thumbnail with *Photo of this page* and *Remove*; the
  existing *Add a file (optional)* stays, so a report can carry both.
- If the capture fails or takes too long, the panel opens without it, with no
  message.
- On *I have an idea* the thumbnail and *What we send* are hidden and not sent;
  back to *Something does not work*, they are there again (unless removed).

### 3. In the panel: *What we send*

- A closed row *What we send (technical details)* under the files; opened, it
  shows in plain words what goes: the page, the last pages, the number of page
  errors and failed requests (with their lines), the device and the version.
- The note at the bottom becomes, for a problem, «Along with the message we send
  a photo of this page and some technical details, which you can read above. We
  answer you by email.»; for an idea it stays as today.

The boards `Segnala` and `Segnala-pc` of the canvas are updated first, with the
thumbnail and *What we send*, and the panel follows them.

### 4. On the server: request id and context

- A middleware early in the stack gives every request a new id (a UUID; an
  incoming `X-Request-Id` is ignored, so nobody can choose it), puts it in
  Laravel's `Context` and writes it in the response header `X-Request-Id`. Once
  the person is signed in, the account id and the family id are added to the
  `Context` too, so every line of the log carries them.
- Queued jobs inherit the `Context` (Laravel does it), so a job's error carries
  the id of the request that queued it.

### 5. On the server: the error rows (`server_errors`)

- A table that is **not** a family model (like `mail_inbox_calls`): an error can
  happen before sign-in. Columns: request id, user id (nullable), family id
  (nullable), method, path, exception class, message cut to 500 characters, file
  and line of the throw, time.
- Written from the report callback of `withExceptions` in `bootstrap/app.php`,
  for every exception Laravel reports (so not 404, 403, 422, which Laravel does
  not report), without stopping the normal logging. A failure while writing the
  row is swallowed (the error is in the log anyway).
- Request id, user and family come from Laravel's `Context`, not from `auth()`,
  so an error of a queued job or a scheduled command keeps the request that
  started it; method and path stay empty when there is no request.
- Cleaned after 30 days with `model:prune` (`MassPrunable`).
- The query that reads them for a report is outside `BelongsToFamily`: it goes in
  the list of exceptions of `AGENTS.md`, like `ReminderNotice::prunable()`.

### 6. On the server: the report

- `StoreFeedbackRequest` accepts, for a problem only, `screenshot` (image, same
  size limit as `file`) and `diagnostics` (a JSON string with a size limit,
  checked for its shape; the server cuts every list and text again to the limits
  above). For an idea both are dropped.
- `SubmitFeedback` stores the screenshot next to the file in `feedback/<family>/`
  (new columns `screenshot_path`, `screenshot_size`), saves the details in a JSON
  column `diagnostics`, and **copies into the report** the error rows of the same
  account from the 30 minutes before, plus any error row whose request id is in
  the failed requests sent (JSON column `server_errors`). Copied at sending time,
  they stay with the report after the rows are cleaned.
- The server also writes its own version (from the deploy commit) in the details,
  so a client build id different from the server version shows a stale app. It is
  worked out like the build id, and the plan checks that the value is there while
  the app runs, not only during the build.

### 7. Owner panel and email

- The page of a report (`FeedbackReportResource::infolist`) gets, for a problem:
  - *Photo of the page*: the screenshot, opening large; the existing file can be
    opened too. Both are served by a route behind the `owner` guard, never from a
    public URL.
  - *Where it was sent from*: the facts of the device and the versions, next to
    page, layout and device.
  - *Path*: the last pages.
  - *Page errors*, *Failed requests*, *Server errors*: one line each, time first,
    with the request id where there is one.
- The existing *Download the file* action of `ViewFeedbackReport` may stay or be
  replaced by the new route: builder's call.
- The list of reports gets nothing new (maybe an icon when there are errors:
  builder's call).
- The email attaches the screenshot too, and says how many page errors, failed
  requests and server errors there are, «details in the panel».

## Constraints

- Project rules of `AGENTS.md`: TDD, English code with Italian translations in
  `lang/it.json` (added by hand, never rewritten with a JSON parser), PHPStan 9,
  `spatie/laravel-data` for data sent to pages, FormKit where it applies,
  components of `components/fm/`, `cn()` for classes in code.
- The new library goes in the *Decisions taken* of the plan with the reason.
- Nothing in the tests calls a real network or uses the real disk.
- The screenshot library cannot render in jsdom: the panel and the menus get the
  capture through a small module that the tests replace with one returning a
  fixed image (the test checks what the person sees, not the library).

## Left to the builder

- Which library (`modern-screenshot` or `html-to-image`) and its options, after
  trying both on the real pages (blur of the panels, PDF previews in iframes,
  images from the private disk).
- Exact scale and quality of the screenshot, and the time limit of the capture.
- The exact shape of `diagnostics` and its `App\Data` class, and how the build id
  is written at build time (`SOURCE_COMMIT` of Coolify, `git rev-parse` locally).
- How the Inertia events and `fetch` are hooked for the failed requests.
- The name of the table and of the columns.
- Whether the list in the panel marks reports with errors.
