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
assets/js/events.js   THE EVENTS LIST - edit this to update What's on
assets/js/main.js     Menu, 18+ check, event tickets, WhatsApp join form
assets/brand/         Supplied Gesond logo (scaled only) and icons
assets/fonts/         Anton + Archivo (self-hosted, SIL OFL licences included)
assets/js/gallery.js  THE PHOTO LIST - add real venue photos here
assets/img/gallery/   Put real photos here
assets/img/og-image.jpg  Social sharing image
```

## Hosting

- Live at https://www.gesondlifestyle.co.za (Hostinger, account u549082618, static site).
- Mailboxes to create: info@, orders@ and admin@gesondlifestyle.co.za.
- `.htaccess` forces HTTPS and www once SSL is active.

## Adding an event

Open `assets/js/events.js`, copy the example block, change the details and save. Upcoming events appear as tickets in the hero and under What's on; once the date passes they move to Recent sessions automatically. Publish only confirmed dates and acts.

## Adding photos

Copy photos into `assets/img/gallery/` and list them in `assets/js/gallery.js`. The "Inside Gesond" section shows only once a photo is listed.

## Editing content

All text is in `index.html`. Website copy for review is in `WEBSITE_CONTENT.md`.

## Brand

- Logo: supplied Gesond_Restaurant_Logo.png only, never redrawn
- Website colours: Night `#14110D`, Dusk `#221B14`, Gold `#E6B23E`, Brass `#B07810`, Bone `#F2EADB`, Ember `#E2552B` (only for "Next up")
- Website fonts: Anton (poster headlines), Archivo (text)
- Tagline: One Love · One Vibe · One Soul
- Legal notice on all liquor content: "Not for sale to persons under the age of 18."
