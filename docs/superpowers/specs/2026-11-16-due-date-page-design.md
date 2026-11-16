# The page of a due date, told apart from its planned expense

Approved by the owner on 16/11/2026 (small change).

## Intent

*A due date* (… › Affitto › Affitto) and *A planned expense* (Spese fisse › Affitto) looked alike: same title, the
same list of months written two ways, and the due date page never said which month it was. The owner chose three
changes.

## Decisions

1. **The date in the title**: the heading of *A due date* is «Affitto · 14/05/2026» (the date it falls on, moved by an
   exception if any), the tab title too, and the last piece of the trail is the date alone («… › Affitto › 14/05/2026»).
2. **The same rows in both lists**: the list *Due dates* of *A due date* writes each row as *A planned expense* does —
   the date as the title, where it stands below (paid, late, different amount or date), no area dot and no repeated
   title. `FmDeadlineRow` gets the variant `dated`; the standing moves from `PlannedItem.vue` to
   `deadlineStanding()` in `lib/budget.ts`, used by both.
3. **A link to the planned item**: under the heading, «Go to the planned expense» («Vai alla spesa fissa»; for an
   income «Go to the planned income», «Vai all'entrata fissa»), to `showPlanned`.

Canvas first: `Scadenza`, `Scadenza-pc` and the `FmDeadlineRow` card of `Righe`.

## Approaches

One sensible way: a variant of `FmDeadlineRow` keeps one row component for due dates; a new component or a copy of the
`FmRow` markup of `PlannedItem.vue` would duplicate it. The plan is this note: three steps, each with its tests
(`lib/budget` for `deadlineStanding`, `FmDeadlineRow` for `dated`, `Deadline.test.ts` for title, trail, link and rows).
