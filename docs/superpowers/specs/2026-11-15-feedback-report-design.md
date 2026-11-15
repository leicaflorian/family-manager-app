# *Report a problem or an idea*

## Why

The owner wants the people of the families to tell him when something does not
work or when they have an idea, from inside the app, without looking for an
address. The panel is already drawn on the canvas (`Segnala` for the phone,
`Segnala-pc` for the computer); the owner asked, in a comment on the canvas, to
bring it into the code with an item in the account menu.

## Goals

- An item *Report a problem or an idea* (Lucide `MessageSquareWarning`):
  - computer and tablet: in the *My account* menu of `FmSidebar`, after
    *Settings*, before the separator and *Log out*;
  - phone: the last row of *More* (`FmBottomBar`), after *Settings*, before
    *Log out*, as the `Segnala` board draws it.
- The item opens the panel, as the boards draw it: `FmBottomSheet` (sheet from
  below on the phone, window in the middle on the computer), title *Report a
  problem or an idea*:
  - *What is it about*: two tiles, *Something does not work* (`Bug`, chosen at
    the opening) and *I have an idea* (`Lightbulb`);
  - *Tell us what happened* (required, up to 5000 characters);
  - *Add a screenshot or a file (optional)*: one file, image or PDF, up to
    10 MB; once chosen it shows its name with a way to remove it;
  - the note «Along with the message we send the page you were on, the device
    you use and the time. We answer you by email.»;
  - *Send* and *Cancel* (phone: one under the other; computer: right-aligned,
    *Cancel* first).
- *Send* saves the report and emails it to the owner; the panel closes and the
  usual toast says «Thanks, we will answer you by email.».
- Everyone in the family can send one, `admin` and `member`.

## Non-goals

- The linked phone (*Documents from the phone*) does not see the item and the
  route stays outside the list of `LimitLinkedDevice` (403).
- No answer from the app: the owner answers by email, outside the app.
- No list of the reports inside the app for the family.

## Owner decisions (15/11/2026)

- Saved in a table **and** emailed to the owner accounts.
- The file is kept apart, on the private disk under `feedback/`, not among the
  files of the family: it does not appear in *Documents* or *To sort*. It is
  also attached to the email.
- Everyone, `member` included; not the linked phone.
- After *Send*: the panel closes and a toast confirms.

## Approaches

- **Chosen: one sheet owned by the layout.** `AppLayout` holds one
  `FmFeedbackSheet`; `FmSidebar` and `FmBottomBar` emit `report` from their
  item (the phone closes *More* first). One copy of the form for every layout.
- Discarded: a page `/feedback`. The boards draw a panel over the page, and the
  page you were on is part of the report.
- Discarded: the sheet inside `FmSidebar` and `FmBottomBar`. Two copies, and the
  tablet sidebar lives in a panel that closes on a visit.

## Design

- **Data** — table `feedback_reports`: `family_id` (required, foreign key,
  index; model `FeedbackReport` with `BelongsToFamily`), `user_id` (who sent it,
  null on delete), `kind` (`App\Enums\FeedbackKind`: `problem`, `idea`, with
  labels in `lang/{en,it}/enums.php`), `message`, `page` (the path and query of
  the page, up to 2048), `user_agent` (from the request, up to 512), `layout`
  (`phone`, `tablet`, `computer`, from `useLayoutMode()`), `file_path`,
  `file_name`, `file_mime`, `file_size` (all null without a file), timestamps.
  It joins `AllModelsIsolationTest`.
- **Sending** — `POST feedback` (`feedback.store`), signed in, `throttle` 10 per
  hour per account. `StoreFeedbackRequest` validates; `FeedbackReportPolicy`
  `create` is true for everyone in the family. The action `SubmitFeedback`
  stores the file (`Storage::disk('local')`, `feedback/<family_id>/<uuid>.<ext>`),
  creates the row and queues `FeedbackReportMail` to the email of every
  `Owner`, with `replyTo` the account of who sent it and the file attached.
  The controller flashes the toast and goes `back()`.
- **Owner panel** — a resource *Reports* in `app/Filament/` (list and detail,
  read only: kind, message, page, device, layout, time, family, who, and a
  download of the file). This is the one place where the panel reads content of
  a family, because it is a message the person sent to the owner: the queries
  go through `app/Admin/FeedbackReports.php` (the exception of `AGENTS.md`,
  which gets a line), and `app/Filament/` keeps never calling
  `withoutGlobalScope`.
- **Front end** — `FmFeedbackSheet.vue` (`@group forms`, `pages` in
  `boards.json` → `Segnala`), FormKit; the page comes from `window.location`
  and the layout from `useLayoutMode()` when it is sent. `navigation.ts` gets a
  `feedback` item (label and icon, no address). Texts in English with
  `lang/it.json`.
- **Boards** — the *Navigation* board shows the account menu and *More* with
  the new item before the commit.

## Left to the builder

- The tiles of the choice (FormKit `radio` restyled with `$reset`, or an
  existing component if one fits), close to the boards.
- The email layout (Markdown mail like `InvitationMail`), in Italian.
- The exact Filament columns and filters.
