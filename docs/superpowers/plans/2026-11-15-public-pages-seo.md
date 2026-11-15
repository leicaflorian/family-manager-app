# Public pages: favicon and SEO Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The Family Manager favicon, and server-written SEO metadata with robots rules for every page.

**Architecture:** `App\Support\PageMeta::for(Request): PageHead` maps the route name to title, description and
indexability; a view composer passes it to `app.blade.php`, and the middleware `AddRobotsHeader` writes `X-Robots-Tag`.

**Tech Stack:** Laravel 13, Pest, Blade, spatie/laravel-data.

**Spec:** `docs/superpowers/specs/2026-11-15-public-pages-seo-design.md`

## Global Constraints

- English keys in code, Italian in `lang/it.json` (`TranslationsTest`).
- `declare(strict_types=1)`, `final`, PHPDoc on every class and method, Larastan level 9.
- Indexable route names: `login`, `register`. Everything else: `noindex, nofollow`.
- Image: `/family-manager-og-1200x630.png`, 1200×630.

## Review Focus

- A page with query parameters (`/login?x=1`): canonical and `og:url` without the query.
- A 404 or an error page rendered with `app.blade.php` without a route: falls back to the default, `noindex`.
- Inertia partial requests (JSON): `X-Robots-Tag` still set, no crash in the composer.
- Italian browser: description in Italian, `og:locale` `it_IT`.
- A page title with `&` or quotes: escaped by Blade (`{{ }}`).

---

### Task 1: Favicon

**Files:** Replace `public/favicon.svg` (copy of the vault's `family-manager-icon.svg`), `public/favicon.ico` (16, 32, 48
px from it, with `rsvg-convert` and Imagick in the container). Test: `tests/Feature/FaviconTest.php`.

- [x] Test `favicon is the Family Manager icon`: `favicon.svg` contains `#3399FF` and not `#FF2D20`; `favicon.ico` has three images (Imagick `getNumberImages() === 3`).
- [x] Run, see it fail; replace the files; run, see it pass.

### Task 2: PageMeta and the head

**Files:** Create `app/Support/PageHead.php` (a value object: it never reaches the pages), `app/Support/PageMeta.php`, `app/Http/Middleware/AddRobotsHeader.php`;
modify `resources/views/app.blade.php`, `app/Providers/AppServiceProvider.php` (composer for `app`), `bootstrap/app.php`
(global, so 404s and the calendar are covered; an answer with its own `X-Robots-Tag` keeps it), `lang/it.json`. Test: `tests/Feature/PageMetaTest.php`.

**Interfaces:** `PageMeta::for(Request $request): PageHead`; `PageHead(string $title, string $description, bool $indexable, ?string $canonical, string $image, string $locale)`.

- [x] Tests (GET as guest, `Accept-Language: it` where noted):
  - `login` is indexable: no `X-Robots-Tag`, `<link rel="canonical" href="{APP_URL}/login">`, `og:url` same, even with `?x=1`.
  - `register` is indexable.
  - `forgot-password`, `reset-password/abc`, `invitation/missing`, `connect`: `X-Robots-Tag: noindex, nofollow`, `<meta name="robots" content="noindex, nofollow">`, no canonical.
  - signed-in `dashboard`: `X-Robots-Tag: noindex, nofollow`.
  - Italian: `<title>Accedi - Family Manager</title>`, Italian description, `og:locale` `it_IT`; English: `en_US`.
  - `og:image` absolute with `og:image:width` 1200 and `og:image:height` 630, `twitter:card` `summary_large_image`.
- [x] Run, see them fail; implement; run, see them pass; Pint, PHPStan, Rector.
- [x] Commit both tasks with the docs and the learnings.
