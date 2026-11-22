# Every document row: the ⋯ menu

Date: 22/11/2026. Short design (a small change), asked by the owner after
*Persona - documenti in primo piano*.

## Why

From a list of documents the person wants the frequent actions without
opening the document: pin it at the top (the «favourites»), download it, open
it in a new tab, edit it.

## Goals

- `FmDocumentRow` gets an opt-in ⋯ menu on the right, beside the row link
  (never inside it), with, in this order:
  - *Pin at the top* / *Unpin* — only for a personal document (with a person,
    linked to no record) and for who may edit it;
  - *Download*;
  - *Open in a new tab*;
  - *Edit* — for who may edit it.
- The menu is on the rows that lead to a document: *A person* (the others and
  *Other documents*), *An organization*, *Documents* (the Archive). Not in the
  sheets where a row picks a document to link. The Home has no document rows.
- The pages of a card already have their ⋮ menu per document
  (`FmCardDocumentActions`): no second menu, it gains *Download*. *Pin* is not
  there: a document linked to a card is not personal.
- After pin / unpin the page reloads and keeps its scroll.
- The canvas first: the case with the menu in the card of `FmDocumentRow`
  (board *Righe*), then the screens that show it (person, organization,
  Archive, card page), phone and wide.

## Non-goals

- No favourites outside *Pin at the top*; no delete in the row menu.

## Approaches

One sensible way: the server sends per row what the menu needs and the row
draws `FmMenu`. Rejected: a per-page menu written in each page (cloned code),
and loading the document detail when the menu opens (a request per tap for
data the list can carry).

## Decided

- `DocumentData` gains `download_url`, `pinned`, `can_edit`
  (`Gate::allows('update', $document)`) and `can_pin` (`can_edit` and
  personal). «Personal» is computed for the whole list in the query (an
  `exists` on `documentables`), never with a query per row.
- The pin visit lives in one place (a composable), shared by
  `FmDocumentActions` and the row.

## Left to the builder

- Names of the prop, the composable and the query scope; how the icon button
  sits in the row (sizes from the board).
