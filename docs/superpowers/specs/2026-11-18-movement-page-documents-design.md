# The page of a movement, with its documents beside the form

Approved by the owner on 18/11/2026.

## Intent

An expense with no document (a receipt that arrives later, a bill paid before it was filed) has no way to get one from
its own page: `FmMovementPanel` shows *Documents* only when the movement already has some, and a document is linked to
an expense only from the document's form (its links). The owner wants to link documents and upload new ones from the
expense itself, the way a card does it, and wants the expense to open on its own page on the computer too, as it
already does from the *Payments* of a card.

## Goals

- **Movements, computer**: a click on an expense or income of *Budget › Movements* opens its page
  (`budget.movements.show`), not the column beside the list. *New movement* opens `budget.movements.create` as well
  (owner's choice, 18/11/2026). The column beside the list shows the filters only; `?movement=` is no longer read.
  *Back* from the page goes back to the movements with the filters they had (`usePageTrail`, as today on the phone).
- **The page of a movement, computer**: two columns as *Edit a card* (`EditCard.vue`, board *Modifica-pc*): on the left
  the grey column of the documents, on the right the form. On the phone the documents are a section under the fields.
  Expenses and incomes, new and saved alike.
- **The documents column** is `FmCardFiles` made general (a prop for what it belongs to, card or movement), not a copy:
  *Link* beside the title opens `FmLinkDocumentSheet`; the drop zone keeps the chosen files until *Save*; the linked
  documents have their menu, with *Unlink*. Nothing is sent until *Save*, as with cards: the documents chosen are linked
  and those marked unlinked with the movement.
- **Uploaded files go through *Import*** (owner's choice A, 18/11/2026): after *Save*, with files, they are kept waiting
  for their document (`FileStore::store(..., toSort: true, link: …)`) with the movement as preset link, and *Import*
  opens with them (`ImportPage::open`), as `CardController::openWithFiles` does. Without files the save goes where it
  goes today.
- **The form fields in pairs**, on two columns on phone and computer: *Account | Category*, *Refers to | Holder*,
  *Planned expense | Due date* (the due date appears only with a planned item, as today). For an income the pairs
  follow its fields. *Title*, *Commission* and *Organization* stay full width.
- **Permissions**: a `member` links, unlinks and uploads, because they may update expenses and incomes and the link
  permission is on the document (`Gate::authorize('update', $document)`), as for cards.

## Non-goals

- No change to *Import* itself or to how a file is read.
- No new way to link from the document side: the links of the document form stay as they are.
- No change to the payments of a card (`Payments`), which already open the page.

## Constraints

- The boards come first: *Movimento*, *Movimento-pc*, *Nuova-spesa*, *Nuova-spesa-pc*, *Entrata*, *Movimenti-pc*,
  *Movimenti-filtri-dettaglio-pc*, copies in `.superpowers/sdd/2026-11-18-movement-page/boards/`.
- A form that sends files sends an update as `POST` with `_method` (`withMethod()`), and the file rules hold for new and
  saved movements alike.
- The *Save* with files must not lose the unsaved-changes guard (`useUnsavedChanges`).
- A document of another family answers 404 before anything is saved (looked up in the request, as
  `SaveCardRequest::documentsToLink()`).
- Expense and income are already linkables (`Linkables::WITH_DOCUMENTS`), so a preset link of type `expense`/`income`
  must be accepted by `PresetLink` and *Import*; if `PresetLink` only knows cards, it gains movements.

## Approaches

1. **Reuse the card pattern (chosen)**: generalize `FmCardFiles` and the server side of card documents (link/unlink with
   the save, files to *Import*) for movements. One component, one behaviour; the owner already knows it from cards.
2. A new movement-only component with immediate uploads: rejected, it would clone `FmCardFiles` under another name,
   which the project forbids.
3. Files becoming documents at once with the expense title (option B): rejected by the owner, the documents would stay
   without a category.

## Left to the builder

- The name of the generalized component (keep `FmCardFiles` with a prop, or rename it and update `boards.json` and the
  board *Pannelli*/*Cifre-e-file* card).
- Where the shared link/unlink and files-to-*Import* code lives on the server (an action or a request concern shared by
  `SaveCardRequest` and `SaveMovementRequest`).
- What happens to `Movements.vue`'s embedded panel code once the column no longer opens a movement (removed, with its
  tests).
