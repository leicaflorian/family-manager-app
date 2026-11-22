# A person: documents pinned at the top of the personal documents

Date: 22/11/2026. Short design (a small change). ToDo item *Persona - documenti
in primo piano*.

## Why

On the page of a person the documents asked for most often (identity card,
driving licence, health card, an employment contract, the last 730) sink
among dozens of others, five at a time. The boards *Persona-tanti* and
*Persona-tanti-pc* already draw a part **Pinned** (*In primo piano*) at the
top of *Personal documents*; the work of 19/11/2026 left it out (spec
`2026-11-19-person-many-documents-design.md`, non-goals) for this item.

## Goals

- **Personal documents** of *A person* get two parts:
  - **Pinned**: every pinned document, always all visible, as tiles (one
    column on the phone, two in the wide layout, `useWideLayout()`), in the
    order they were pinned. Each tile: the icon of its category, the title,
    the line below (the expiry with its colour when it has one, as
    `FmDocumentRow` with `expiry`, otherwise what it is and the date), and the
    pin button on the right (pressed, *Unpin «…»*, title *Pinned by :name ·
    unpin*) that unpins it.
  - **The others, most recent first**: the rest, a page at a time with
    *Show 5 more · N left*, as today. The pinned ones are not repeated here.
  - The number beside the title counts all of them, pinned included.
  - In the wide board, beside *Pinned*, the hint *Pin a document at the top
    from its menu*.
- **The ⋯ menu of a document** (its page and the panel beside the Archive,
  `FmDocumentActions`) gets *Pin at the top* / *Unpin*, only for a personal
  document (with a person and linked to no record) and only for who can edit
  it (so a `member` too, `DocumentPolicy`).
- Pinned is a fact of the document, the same for the whole family; the
  document remembers who pinned it and when.

## Non-goals

- No documents pinned by themselves (identity documents by category): the
  owner chose pinning by hand only (22/11/2026). In the boards the first four
  tiles (identity card, licence, health card, tax code), drawn without a
  button and with the generic icon, take the pin button and their category
  icon like the others; the tax code shows its date like any document.
- No reordering of the pinned ones by dragging.
- No pinning of documents linked to a record, nor elsewhere than *A person*.

## Approaches

1. **Two columns on `documents`, `pinned_at` and `pinned_by`** (chosen): a
   document has a single `person_id`, so pinned is a property of the
   document. Nullable columns, so the migration suits the previous release.
2. **A table of pins** (person, document, user): pins per account or per
   person, which nobody asked for; more joins for the same page.
3. **Pinned per account** (*Pinned by you*): every person in the family would
   see a different top; rejected, the family shares one archive.

## Decided

- Unlinking a document from its person or linking it to a record takes it out
  of *Pinned* (it is no longer a personal document); the builder decides
  whether the columns are cleared or just ignored, with a test.
- Pin and unpin are a `PATCH`/`POST` that goes back to the page it came from
  (`back()`), with its own `throttle` prefix and the 404 for a document of
  another family.

## Left to the builder

- The tile: first check `components/fm/` and the boards *Righe* and *Basi*
  (`FmRow`, `FmDocumentRow`, `FmFileTile`); extend one with a prop or variant,
  or create one with its row in `boards.json`, its card in the board and the
  reason in the *Decisions taken*.
- How the server sends the pinned list (a prop of its own, whole, beside the
  pages of the others) and the names of props, route and action.
- The board update (the four tiles above) published before the commit of the
  code that changes the look.
