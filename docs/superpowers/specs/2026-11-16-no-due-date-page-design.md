# No page for a single due date

Approved by the owner on 16/11/2026 (medium change).

## Intent

*A due date* (`budget/Deadline`) repeats what *A planned expense* shows, and since `1eef747` a due date is paid and
changed from the planned expense. The page goes; every way into it opens the planned expense with the right panel.

## Decisions

- **The old address stays** (`budget.deadlines.show`, `…/occurrences/<date>`), so links in search, the Agenda, e-mails
  and history keep working, but it only redirects:
  - a paid due date → the movement that paid it (`budget.movements.show`);
  - otherwise → `budget.planned.show` with `?deadline=<date>`, `pay=1` kept, and `from=<path>`: the page the visit
    came from (the `Referer`, only on the same host and not the planned expense itself).
- **The planned expense opens the panel on arrival** (props `opening: ?DeadlineData`, `pay: bool`, `back: ?string`):
  with `pay` and `can_pay` *I paid it*; else for an `admin` *Change this due date*; else, with `can_pay`, *I paid it*;
  else nothing. The query is removed at once with `router.replace` (as `Deadline.vue` did with `?pay=1`), so *Back*
  does not reopen it.
- **Back where you came from**: the panels opened on arrival send `?back=<from>`; payment and exceptions redirect
  there (a local path only: starts with `/`, not `//` or `/\`). Without it they go to the planned expense, never to
  the old address. `from_agenda` stays as it is.
- **Rows of *Next due dates***: open → the panel (*Change this due date* for an `admin`, *I paid it* for who can only
  pay), paid → its movement, otherwise plain. *Record payment* opens *I paid it* in place.
- **Removed**: `pages/budget/Deadline.vue` and its test, the list logic of `DeadlineController::show`
  (`before()`, `NEXT`, `BEFORE`, `NEXT_YEARS`), the `current` and `dated` props of `FmDeadlineRow`, the texts used only
  there; on the canvas the boards `Scadenza` and `Scadenza-pc`, links to them pointing to `Previsto` instead, and the
  boards of the payment panel drawn over the planned expense.

## Approaches

Redirect plus panel on arrival chosen over opening the payment panel directly on the Agenda/Home (the Agenda already
pays in place; elsewhere it would need accounts and the form on every page) and over deleting the route (old links
would 404).
