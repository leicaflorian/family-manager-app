# Public pages: favicon and SEO

Approved by the owner on 15/11/2026.

## Intent

The public pages (sign in, sign up, forgotten and new password, invitation, linking the phone) still show the Laravel
favicon, and their `<head>` has no description, no Open Graph and no robots rules: a shared link shows only
«Family Manager», and search engines may index pages that carry a secret in the address.

## Goals

- The favicon is the Family Manager icon (the *negative*: white drawing on the `brand` square), from the vault
  (`03 - Branding/assets/family-manager-icon.svg`), as SVG and as ICO (16, 32, 48 px).
- The server writes, in `app.blade.php`, the title, the description in the language of the page, Open Graph
  (`og:title`, `og:description`, `og:image` with size and alt, `og:url`, `og:type`, `og:site_name`, `og:locale`) and
  the Twitter card (`summary_large_image`), so that link previews work without JavaScript.
- Only *Log in* and *Sign up* can be indexed (with `<link rel="canonical">` and `og:url` from `APP_URL`, no query).
  Every other page, public or not, has `noindex, nofollow`, both as `<meta name="robots">` and as `X-Robots-Tag`.

## Non-goals

- No sitemap, no structured data, no landing page.
- `robots.txt` stays open: a blocked page cannot be read, so its `noindex` would never be seen.

## Approach

One place decides the metadata of a page from its route name: `App\Support\PageMeta` returns an `App\Support\PageHead`
(title, description, indexable, canonical url, image). A view composer gives it to `app.blade.php`, and the middleware
`AddRobotsHeader` (global) writes `X-Robots-Tag` when the page is not indexable. Considered and dropped: passing the
metadata from each controller (Fortify renders its views in closures, and the same map would be scattered in nine
places); `robots.txt` rules (see non-goals).
