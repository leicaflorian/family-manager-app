# A person with many documents: the page in boxes, a few rows at a time

Date: 19/11/2026. Short design (a small change).

## Why

The page of a person (`family/Person.vue`) shows every list in full: with a
person who has dozens of documents (Grazia Fanelli: about 40 personal, many
more linked to records) the page becomes endless, and the open reminders are
loose notices on the page, without a box. The canvas already draws this case
in the boards *Persona-tanti* (phone) and *Persona-tanti-pc* (wide): the work
is bringing those boards into the code.

## Goals

- Follow *Persona-tanti* and *Persona-tanti-pc*:
  - **To do**: a box with the title and the number of open reminders, *Open in
    the Agenda* beside the title (`/reminders?person=`), the first 5 rows and
    under them *Show 5 more · N left*. The late reminder stays as the red
    notice above the box, as in the board.
  - **Personal documents**: title with the number, *Add* beside it (only for
    who can create documents), the most recent first, 5 at a time with *Show 5
    more · N left*.
  - **Links**: the tabs of today (`FmLinkedRecords`), with *Show 3 more · N
    left* under the rows of the open tab.
  - **Other documents** (side column): title with the number, *Open in the
    Archive* (`/documents?person=`), 10 at a time with *Show 10 more · N left*.
  - The block *Expiring* goes away, as in the boards: the renewals already
    appear in *To do*.
- Same order and boxes on the phone, one column.

## Non-goals

- **Pinned** (*In primo piano*, documents fixed at the top from their menu):
  left for a separate ToDo item (decision of the owner, 19/11/2026). The
  board keeps it; the code does not show it, and the difference goes in the
  *Decisions taken*.
- No change to *An organization*, which shares `FmLinkedRecords`: the *Show
  more* of the links applies there too, nothing else.

## Approaches

1. **Everything sent, revealed in the page**: simple, but a person can have
   150+ documents and the page would load them all every time.
2. **Documents a page at a time from the server, reminders and links revealed
   in the page** (chosen): the two document lists use `DocumentRows::page()`
   with `Inertia::merge` and a partial reload (`only`, `preserveUrl: true`, as
   in *Link a document*); the reminders and the records are few and already
   needed for the counts, so they are sent whole and revealed 5 / 3 at a time
   in the browser.
3. **Previous / Next** (`FmPager`): not what the boards draw.

## Left to the builder

- The *Show N more · N left* button (outline, full width, in the footer of the
  box) becomes one component or a variant of an existing one, after checking
  `components/fm/` and the boards *Basi* and *Navigazione*; a new component
  takes its row in `boards.json` and its card in the board.
- The number beside a section title: a prop of `FmCard` (with its test and its
  card in the board *Basi*), not a title written by hand.
- Names of the props and of the page parameters, the counts sent by the
  server (`total`) for *N left*.
