# Family name in the settings

Short design, 23/11/2026, approved by the owner in the conversation («va bene l'1, procedi»).

## Why

The name of a family is set by the owner when the family is created from the panel, and nobody could change it after:
an admin of the family must be able to fix or change it.

## What

- A new page **Settings → Family name** (`GET settings/family`, `PUT settings/family`, names `settings.family.edit` and
  `settings.family.update`), with one text field (*Family name*, required, at most 255 characters, trimmed) and *Save*.
- A row at the top of the *The family* card of the settings index (title *Family name*, subtitle the name), and the
  same section first in the *The family* group of `FmSettingsPage` (wide layout).
- Everyone of the family sees the page; only an `admin` saves. The check is the policy (`update` of `FamilySetting`,
  as *Address for the bills*); a `member` sees the field read-only and no *Save*.
- The name does not need to be unique (families are told apart by id).
- What changes with the name: the shared prop `family` (menu, subtitles), the emails sent after, the name of the
  subscribed calendar. What does not: the address for the bills, made from the name only once.

## Approaches

1. A page of its own, like the other settings (picked: works in the wide layout, where the index is not shown).
2. A bottom sheet opened from the row of the index: quicker on the phone, but the wide layout has no index to open it
   from.

## Builder's calls

- Unsaved changes guard (`useUnsavedChanges`) and the save shortcut, as the other settings pages.
- Boards first in the canvas: `Imp-famiglia.dc.html` and `Imp-famiglia-pc.dc.html`, and the new row in
  `Impostazioni.dc.html` / `Impostazioni-pc.dc.html`.
