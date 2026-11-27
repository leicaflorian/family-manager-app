# Card form: composed title and «+» next to the organization — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** in the card form (`cards/EditCard`, the same page creates and edits) show a read-only *Title* with the name the card will have, and a «+» next to every organization menu that creates an organization in a panel and chooses it.

**Architecture:** the server describes how a type composes its name (`CardType::titleParts()`), the page joins the labels of the chosen options with ` - ` as `HasDocuments::joinLabel()` does. A new route creates an organization with only name and types, flashes the new id and goes back; the page reloads the fields (new option) and the form chooses it.

**Tech Stack:** Laravel 13, Inertia 3 (`Inertia::flash`), Vue 3 TS, FormKit Pro, Pest, Vitest.

**Short design (approved by the owner on 27/11/2026):**
- Title: only for the types whose name is composed: *Utility* (type - supplier, or type - residence when there is no supplier), *Employment* (person first name - employer), *Enrollment* (person first name - type - organization). The others have their own *Name*/*Title* field. Read-only text field at the top of the form, updated live; while nothing composes it, the placeholder «It is made from the fields below» (it: «Si compone dai campi qui sotto»).
- «+»: next to every `organization` field of every card type, in new and edit. Panel *New organization* with *Name* (required) and *Type* (the same choices as the organization form). Save closes the panel and the new organization is chosen in the menu. Phone, email, website and logo are added later from the organization page.
- Approaches considered: (a) compose the title in the page from parts described by the server — chosen, no logic written twice, no request per keystroke; (b) ask the server for the title on every change (partial reload) — a request per change for a string join; (c) write the rules again in TypeScript — duplicated code, forbidden by AGENTS.md. For the «+»: an Inertia POST with a flash (chosen, like every form of the app) vs a JSON endpoint with `fetch` (a second way of saving for one form).

## Global Constraints

- AGENTS.md and `.claude/learnings.md` of the worktree; `.superpowers/sdd/Piano/lane-rules.md` lane A (worktree `scadenze-veicolo`, DBs `testing_scadenze_veicolo` / `testing_scadenze_veicolo_dev`), branch `scheda-titolo-ente`.
- The preview equals `label()` of the saved card, exactly (same parts, same order, ` - ` separator, empty parts skipped, first name of the person).
- Creating an organization stays admin-only (`OrganizationPolicy`/`FamilyPolicy` `create`); the «+» shows only to who can create one.
- English code, Italian in `lang/it.json` appended as text before the final `}`.
- New texts are drafts to approve by the owner.

## Review Focus

- A Utility with no supplier and a residence: the preview shows type - residence; choosing a supplier switches to type - supplier.
- A person option label is the full name in the menu, but the title uses the first name only.
- Saving the panel with an empty name: the error shows in the panel, the card form keeps what was typed.
- After creating the organization, the card form keeps every value already typed (the page reload must not reset the form) and the unsaved-changes guard still works.
- A member never sees the «+»; a POST from a member answers 403.

---

### Task 1: the server describes the composed title

**Files:** `app/Cards/CardType.php`, `app/Cards/Types/UtilityCard.php`, `EmploymentCard.php`, `EnrollmentCard.php`, the data class the form page gets (`CardFieldData` / the props of `cards/EditCard` in `CardController`), `tests/Unit/Cards/CardTypesTest.php`, `tests/Feature/Cards/CardPagesTest.php`.

**Produces:** `CardType::titleParts(): list<list<string>>` (default `[]`): each part is a list of columns, the first one with a value wins. Utility `[['type'], ['organization_id', 'home_id']]`, Employment `[['person_id'], ['organization_id']]`, Enrollment `[['person_id'], ['type'], ['organization_id']]`. The page prop `titleParts` (array, empty for the other types). Each option of a `person` field also carries the text the title uses (the first name), e.g. an optional `title` on the option data, so the page needs no rule of its own.

- [ ] Test: the three types give those parts, the others `[]`; the EditCard page (new and edit) of a utility sends `titleParts`; a person option carries the first name.
- [ ] Test (Bianchi seeder): for every utility, employment and enrollment of the seeder, joining with ` - ` the option texts the page receives for the card values, part by part, gives `label()` of the card.
- [ ] Implement, PHPStan, Pint, Rector, typescript:transform; commit.

### Task 2: a quick organization from the card form

**Files:** a new route `POST family/organizations/quick` named `organizations.quick` (next to the resource in `routes/web.php`), its controller method, a form request reusing the rules of `Family/SaveOrganizationRequest` for `name` and `types` (no copy of the rules), tests in `tests/Feature/Family/`.

**Produces:** on success `Inertia::flash('organization', ['id' => int, 'name' => string])` and `back()`; validation errors go back as usual (error bag `organization`, so they don't mix with the card form errors). The EditCard page props tell whether the person can create an organization (`canCreateOrganization: bool`).

- [ ] Tests: admin creates (row in the family, flash with id and name, redirect back); member 403; empty name 422/errors in bag `organization`; the organization lands in the current family (isolation).
- [ ] Implement, wayfinder:generate, checks; commit.

### Task 3: the form shows the title and the «+»

**Files:** `resources/js/components/fm/FmCardForm.vue` (+ test), `resources/js/pages/cards/EditCard.vue` (+ test), a small util in `resources/js/lib/cards.ts` (`composeTitle(parts, fields, values): string`, + test), the panel built with the existing `components/fm/` panel components as the boards show (new component only if none fits, with its `boards.json` row), `lang/it.json`, `resources/js/test/fixtures.ts`.

**Boards:** read the files given in the dispatch (`.superpowers/sdd/titolo-ente/boards/`), they are the reference.

- [ ] Tests: `composeTitle` with the seeder-shaped fixture (utility with supplier, without supplier with residence, employment, enrollment, nothing chosen → empty); the form shows the read-only *Title* only when `titleParts` is not empty, updated when a menu changes, placeholder when empty; the «+» shows next to organization fields only with `canCreateOrganization`; saving the panel posts to `organizations.quick` keeping the page state and URL, closes the panel and chooses the flashed id; errors of the bag `organization` show in the panel; the values typed before stay.
- [ ] Implement, vp check, types, lint, Vitest; commit.
