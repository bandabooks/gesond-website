# gesond-website

Official website of **Gesond Liquor Restaurant** (Gesond Enterprise (Pty) Ltd, Reg. 2021/658589/07), Danisane Village, Mapela, Limpopo.

Static site: plain HTML, CSS and JavaScript. No build step and no server-side code.

## Structure

```
index.html            One-page site (What's on, Series, Your night, Join, Artists, Food & drinks, House rules, Vision, Visit)
404.html              Not-found page
favicon.ico           Icon cut from the supplied logo's gold O
robots.txt, sitemap.xml
.htaccess             HTTPS + www redirect, 404 page, caching (Apache / Hostinger)
assets/css/style.css  Styles
assets/js/events.js   THE EVENTS LIST - edit this to update What's on (events.html + home)
assets/js/updates.js  News shown on updates.html
assets/js/config.js   WhatsApp number and social media links
assets/img/events/    Event posters
assets/js/main.js     Menu, 18+ check, event tickets, WhatsApp join form
assets/brand/         Supplied Gesond logo (scaled only) and icons
assets/fonts/         Anton + Archivo (self-hosted, SIL OFL licences included)
assets/js/gallery.js  THE PHOTO LIST - add real venue photos here
assets/img/gallery/   Put real photos here
assets/img/og-image.jpg  Social sharing image
```

## Gesond Rewards, Talk to us and Posts (members/)

PHP 8 + MySQL app in `members/`, running on the same Hostinger site.

- `members/join.php`, `login.php`, `index.php` (member card with QR, points, vouchers), `pin.php`, `rules.php`, `privacy.php`
- `members/contact.php` - questions, bookings, complaints and ideas (reference GQ-000123)
- `members/staff/` - staff log-in: Till (find member, add points by bill + slip number, accept vouchers, reset PIN), Inbox, Posts (schedule website updates, optional auto-post to the Facebook Page), Staff (admin), Settings (admin: connect Facebook)
- AI writing (Posts page): staff type notes, Anthropic (Claude) or OpenAI drafts the post; staff review before saving. Keys are set by an admin in Settings and stored only in the database. Every draft ends with the under-18 notice.
- `members/api/posts.php` - published posts for the website's Updates page
- `members/cron.php` - run every 5 minutes by the hosting cron job to send scheduled posts to Facebook
- Database credentials and rewards rules live in `gesond-members-config.php` **outside** `public_html`, written once by `members/setup.php`. Never commit it.
- Points are an append-only ledger: the balance is always the sum of entries; corrections are reversal entries, never edits.

### First-time setup
1. hPanel: Websites, Manage, Databases: create a MySQL database and user (note the names and password).
2. Open https://www.gesondlifestyle.co.za/members/setup.php, enter those details and create the first admin log-in. The page then switches itself off.

## App (PWA)

`manifest.webmanifest`, `sw.js` and `assets/js/pwa.js` make the site installable ("Install" bar on Android/Chrome, Add to Home Screen tip on iPhone). Pages are fetched fresh when online; member and staff pages are never cached. Bump `VERSION` in `sw.js` together with the `?v=` numbers.

## Hosting

- Live at https://www.gesondlifestyle.co.za (Hostinger, account u549082618, static site).
- Mailboxes to create: info@, orders@ and admin@gesondlifestyle.co.za.
- `.htaccess` forces HTTPS and www once SSL is active.

## Adding an event

Open `assets/js/events.js`, copy the example block, change the details and save. Upcoming events appear as tickets in the hero and under What's on; once the date passes they move to Recent sessions automatically. Publish only confirmed dates and acts.

## Adding photos

Copy photos into `assets/img/gallery/` and list them in `assets/js/gallery.js`. The "Inside Gesond" section shows only once a photo is listed.

## After editing CSS or JS

Bump the `?v=` number on the `<script>` and `<link>` tags in every .html page so visitors get the new files instead of a cached copy.

## Editing content

All text is in `index.html`. Website copy for review is in `WEBSITE_CONTENT.md`.

## Brand

- Logo: supplied Gesond_Restaurant_Logo.png only, never redrawn
- Website colours: Night `#14110D`, Dusk `#221B14`, Gold `#E6B23E`, Brass `#B07810`, Bone `#F2EADB`, Ember `#E2552B` (only for "Next up")
- Website fonts: Anton (poster headlines), Archivo (text)
- Tagline: One Love · One Vibe · One Soul
- Legal notice on all liquor content: "Not for sale to persons under the age of 18."
