# Release answer

You prepare the release of Fameja.app. The commits since the last release arrive on stdin, one per line. Read them; look at the diff with git only when a subject is not enough to understand a change.

Answer with one JSON object and nothing else (no code fence, no text around it):

```json
{
  "bump": "minor",
  "reason": "One sentence on why this number grows.",
  "changelog": "### Area\n- A change that matters",
  "notes": { "it": ["..."], "en": ["..."] }
}
```

## Fields

- `bump`: `minor` when something new that a person can use arrives, otherwise `patch`. Never `major`: the owner decides that.
- `reason`: one sentence.
- `changelog`: English, for the owner, in Markdown. Group by area with `### Area` headings and write one bullet per change that matters. Leave out merge commits and noise (formatting, renames, test-only changes).
- `notes`: what the family sees in *What's new*. Short sentences in simple words, in the second person singular ("you"), with no technical words (no "record", "field", "migration"). `it` in Italian and `en` in English, the same changes in both. Only changes a person of the family would notice, fixes included. At most 8 per language, at least 1.
