# Search and filters in Movements, Planned expenses and Documents

Decided by the owner in the canvas comments (thread on `Movimenti-pc`, 15–16/11/2026). Boards:
`.superpowers/sdd/bilancio-filtri/boards/` (copies of the canvas).

## Intent

*Movements* and *Planned expenses* get a search by title and filters by amount and date, as *Documents* has. On the
computer the filters are a column always open on the left; when something is opened from the list, its detail takes
the place of that column, so there is only one side column and the list keeps its width and place.

## Decisions

- **Where the column is**: on the computer layout (`useComputerLayout()`, from 1024 px) the filter column is always
  open on the left, in all three pages. Below (tablet and phone) the filters are in the panel from below
  (`FmBottomSheet`), opened by a *Filters* button that shows how many filters are on; a row opens its page.
- **Detail in place of the filters** (computer): *Movements* and *Documents* open the chosen row in the left column,
  same width, with «← Back to the filters» and the × at the top; both close the detail (the address loses
  `movement=`/`document=`) and the filters come back. The list does not move or change width; the open row is marked
  (`current`), and after going back no row is marked. The *New expense or income* panel opens in the same place.
  *Documents* loses its third column (from 1440 px) and the floating `FmSidePanel` (1024–1439 px).
- **Planned expenses**: a row keeps opening the page of the planned item (owner, 16/11): no side detail there.
- **Movements filters**: search (the title, as typed: a part of it, any case), type (Everything / Expenses / Income), account, amount from / to, date from / to.
  - The *search mode* starts when there is a search text, an amount or a date: the list then covers every month
    (narrowed by the dates if given), grouped by month with the total in each header, and instead of the month switch
    and the three figures there is one line «6 movements found, 310,10 € in all» and the pills of the filters on, each
    with its ×, plus «Remove the filters».
  - Type and account narrow both the month and the search mode, count in the *Filters* badge and show as pills when set.
  - On the computer the type buttons and the account menu beside the month switch go away: type and account are only in
    the column. On the phone they stay on the page (owner has not decided yet) and are also in the panel.
  - Amounts compare the absolute amount of the movement, inclusive bounds; dates inclusive, `aaaa-mm-gg`.
- **Planned expenses filters**: search (title), amount from / to, next due date from / to. Rows that do not pass are
  hidden; a section with no rows left is hidden; the badge counts amount and dates (search shows as a pill).
  Ended items are filtered too (by their amount; the date filter hides them, they have no next date).
- **Documents**: same filters as today; only the layout changes.
- **No new list or detail components**: `FmMovementPanel`, `FmDocumentDetail compact`, `FmRow`, `FmActiveFilter`,
  `FmSearchField`, `FmBottomSheet` are reused. New: `FmFilterAside` (the left column that shows the filters or the
  detail, with «Back to the filters») because `FmSidePanel` floats over the list and is the opposite behaviour, and
  `FmMovementFilters` / `FmPlannedFilters` as `FmDocumentFilters` is for documents. The *Filters* button with the count
  becomes `FmFilterButton`, used by all three pages.

## Non-goals

No change to the phone list of *Documents*, to the Agenda, to the global search.
