(function () {
  var CFG = window.GESOND_CONFIG || {};
  var WA = CFG.whatsappNumber || "27826439779";
  var yr = document.getElementById("yr");
  if (yr) yr.textContent = new Date().getFullYear();

  /* Mobile menu */
  var toggle = document.querySelector(".nav__toggle");
  var menu = document.getElementById("menu");
  if (toggle && menu) {
    toggle.addEventListener("click", function () {
      var open = menu.classList.toggle("open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      toggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
    });
    menu.addEventListener("click", function (e) {
      if (e.target.tagName === "A") { menu.classList.remove("open"); toggle.setAttribute("aria-expanded", "false"); toggle.setAttribute("aria-label", "Open menu"); }
    });
  }

  /* 18+ confirmation, remembered where the browser allows */
  var gate = document.getElementById("agegate");
  var KEY = "gesond_age_ok";
  var ok = false;
  try { ok = localStorage.getItem(KEY) === "1"; } catch (e) {}
  if (gate && !ok) {
    gate.hidden = false;
    document.body.style.overflow = "hidden";
    document.getElementById("ag-yes").focus();
    document.getElementById("ag-yes").addEventListener("click", function () {
      try { localStorage.setItem(KEY, "1"); } catch (e) {}
      gate.hidden = true;
      document.body.style.overflow = "";
    });
    document.getElementById("ag-no").addEventListener("click", function () {
      document.getElementById("ag-msg").hidden = false;
    });
  }

  /* Events: split into upcoming and recent using South African date */
  var MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
  var DAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
  function parts(iso) { var p = iso.split("-").map(Number); var d = new Date(Date.UTC(p[0], p[1] - 1, p[2])); return { y: p[0], m: MONTHS[p[1] - 1], d: p[2], wd: DAYS[d.getUTCDay()] }; }
  function todaySA() { var n = new Date(Date.now() + 2 * 3600 * 1000); return n.toISOString().slice(0, 10); }

  function ticket(ev, isNext, hero) {
    var p = parts(ev.date);
    var status = ev.date === today ? "Tonight" : "Next up";
    var facts = [];
    if (ev.start) facts.push("From " + ev.start.replace(":", "h"));
    if (ev.entry) facts.push(ev.entry);
    if (ev.cooler) facts.push(ev.cooler);
    (ev.genres || []).forEach(function (g) { facts.push(g); });
    var lineup = (ev.lineup || []).join(", ");
    return '<article class="ticket' + (hero ? " ticket--hero" : "") + '">' +
      '<div class="ticket__stub"><span class="ticket__wd">' + p.wd.slice(0, 3) + '</span><span class="ticket__day">' + p.d + '</span><span class="ticket__mon">' + p.m + '</span><span class="ticket__yr">' + p.y + '</span></div>' +
      '<div class="ticket__body">' +
      (isNext ? '<span class="ticket__status">' + status + '</span>' : "") +
      (ev.series && ev.title.toLowerCase().indexOf(ev.series.toLowerCase()) !== 0 ? '<p class="ticket__series">' + esc(ev.series) + '</p>' : "") +
      '<h3 class="ticket__title">' + (ev.id ? '<a href="events.html#' + esc(ev.id) + '">' + esc(ev.title) + '</a>' : esc(ev.title)) + '</h3>' +
      (ev.headline ? '<p class="ticket__headline">' + esc(ev.headline) + '</p>' : "") +
      (lineup ? '<p class="ticket__lineup">' + esc(lineup) + (ev.mc ? ". MC: " + esc(ev.mc) : "") + '</p>' : "") +
      (facts.length ? '<div class="ticket__facts">' + facts.map(function (f) { return "<span>" + esc(f) + "</span>"; }).join("") + "</div>" : "") +
      "</div></article>";
  }
  function emptyTicket(hero) {
    return '<article class="ticket ticket--empty' + (hero ? " ticket--hero" : "") + '">' +
      '<div class="ticket__stub"><span class="ticket__day">?</span><span class="ticket__mon">Soon</span></div>' +
      '<div class="ticket__body"><h3 class="ticket__title">Next date coming soon</h3>' +
      '<p class="ticket__lineup">We announce new sessions on WhatsApp first. Join the list to hear the date and line-up as soon as they\'re confirmed.</p>' +
      '<a class="btn btn--gold" href="#join">Join the WhatsApp list</a></div></article>';
  }

  var events = (window.GESOND_EVENTS || []).filter(function (e) { return e && e.date && e.title; });
  var today = todaySA();
  var up = events.filter(function (e) { return e.date >= today; }).sort(function (a, b) { return a.date < b.date ? -1 : 1; });
  var past = events.filter(function (e) { return e.date < today; }).sort(function (a, b) { return a.date > b.date ? -1 : 1; }).slice(0, 6);

  var nextEl = document.getElementById("next-ticket");
  if (nextEl) nextEl.innerHTML = up.length ? ticket(up[0], true, true) : emptyTicket(true);
  var upEl = document.getElementById("upcoming");
  if (upEl) upEl.innerHTML = up.length ? up.map(function (e, i) { return ticket(e, i === 0, false); }).join("") : '<p class="upcoming__empty">No new dates are confirmed yet. <a href="#join">Join the WhatsApp list</a> to hear about the next session first.</p>';
  var pastEl = document.getElementById("past");
  if (pastEl) {
    pastEl.innerHTML = past.length ? past.map(function (e) {
      var p = parts(e.date);
      return '<div class="past__row"><span class="past__date">' + p.wd.slice(0, 3) + " " + p.d + " " + p.m + " " + p.y + '</span><span><span class="past__title">' + esc(e.title) + '</span><br><span class="past__lineup">' + esc((e.lineup || []).join(", ")) + '</span></span><span class="past__lineup">' + esc(e.entry || "") + "</span></div>";
    }).join("") : '<p class="past__empty">Past sessions will show here.</p>';
  }

  /* Full events page: posters, details and share links */
  var full = document.getElementById("events-full");
  if (full) {
    var base = location.origin + location.pathname.replace(/[^/]*$/, "") + "events.html";
    full.innerHTML = up.length ? up.map(function (ev, i) {
      var p = parts(ev.date);
      var url = base + "#" + (ev.id || "");
      var shareText = ev.title + ", " + p.wd + " " + p.d + " " + p.m + " " + p.y + " at Gesond Liquor Restaurant, Mapela. " + url;
      var posters = (ev.posters || []).map(function (src) {
        return '<a class="evcard__poster" href="' + esc(src) + '" target="_blank" rel="noopener"><img src="' + esc(src) + '" alt="Poster for ' + esc(ev.title) + '" loading="' + (i ? "lazy" : "eager") + '"></a>';
      }).join("");
      return '<article class="evcard" id="' + esc(ev.id || "") + '">' +
        (posters ? '<div class="evcard__posters">' + posters + "</div>" : "") +
        '<div class="evcard__info">' + ticket(ev, i === 0, false) +
        '<div class="evcard__share"><span>Share with your crew</span>' +
        '<a class="btn btn--sm btn--gold" href="https://wa.me/?text=' + encodeURIComponent(shareText) + '" target="_blank" rel="noopener">Share on WhatsApp</a>' +
        '<a class="btn btn--sm btn--line" href="https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url) + '" target="_blank" rel="noopener">Share on Facebook</a>' +
        '<button class="btn btn--sm btn--line" type="button" data-copy="' + esc(url) + '">Copy link</button></div>' +
        "</div></article>";
    }).join("") : '<p class="upcoming__empty">No new dates are confirmed yet. <a href="index.html#join">Join the WhatsApp list</a> to hear about the next session first.</p>';
    full.addEventListener("click", function (e) {
      var b = e.target.closest("[data-copy]");
      if (!b) return;
      var done = function () { b.textContent = "Link copied"; setTimeout(function () { b.textContent = "Copy link"; }, 2000); };
      if (navigator.clipboard) navigator.clipboard.writeText(b.getAttribute("data-copy")).then(done, function () { b.textContent = "Copy failed"; });
    });
    if (location.hash) { var t = document.getElementById(location.hash.slice(1)); if (t) setTimeout(function () { t.scrollIntoView(); }, 50); }
  }

  /* Updates page: posts from the staff Posts page (API) plus the static list */
  var ul = document.getElementById("updates-list");
  if (ul) {
    var render = function (extra) {
      var ups = extra.concat(window.GESOND_UPDATES || []).sort(function (a, b) { return a.date < b.date ? 1 : a.date > b.date ? -1 : 0; });
      ul.innerHTML = ups.length ? ups.map(function (u) {
        var p = parts(u.date);
        return '<article class="update"><p class="update__date">' + p.wd + " " + p.d + " " + p.m + " " + p.y + '</p><h2 class="update__title">' + esc(u.title) + "</h2><p>" + esc(u.text).replace(/\n/g, "<br>") + "</p>" +
          (u.link ? '<a class="link" href="' + esc(u.link) + '">Read more</a>' : "") + "</article>";
      }).join("") : '<p class="upcoming__empty">No updates yet. Join the WhatsApp list to hear from us first.</p>';
    };
    render([]);
    if (window.fetch) fetch("/members/api/posts.php", { cache: "no-store" }).then(function (r) { return r.ok ? r.json() : { posts: [] }; })
      .then(function (d) { if (d.posts && d.posts.length) render(d.posts); }).catch(function () {});
  }

  /* Social links: only accounts filled in config.js are shown */
  var SOC = [["facebook", "Facebook"], ["instagram", "Instagram"], ["tiktok", "TikTok"], ["linkedin", "LinkedIn"], ["youtube", "YouTube"], ["whatsappChannel", "WhatsApp Channel"]];
  var socHtml = SOC.filter(function (s) { return CFG[s[0]]; }).map(function (s) { return '<a href="' + esc(CFG[s[0]]) + '" target="_blank" rel="noopener">' + s[1] + "</a>"; }).join("");
  Array.prototype.forEach.call(document.querySelectorAll("[data-social]"), function (el) {
    if (socHtml) el.innerHTML = '<span>Follow Gesond</span>' + socHtml; else el.hidden = true;
  });
  if (CFG.whatsappChannel && document.getElementById("channel-item")) {
    document.getElementById("channel-link").href = CFG.whatsappChannel;
    document.getElementById("channel-item").hidden = false;
  }

  /* Photos: section stays hidden until photos are listed in gallery.js */
  var photos = (window.GESOND_PHOTOS || []).filter(function (p) { return p && p.src; });
  var gal = document.getElementById("gallery");
  if (gal && photos.length) {
    gal.innerHTML = photos.map(function (p) {
      return '<figure><img src="' + esc(p.src) + '" alt="' + esc(p.alt || "Gesond Liquor Restaurant") + '" loading="lazy" width="800" height="600">' + (p.caption ? "<figcaption>" + esc(p.caption) + "</figcaption>" : "") + "</figure>";
    }).join("");
    document.getElementById("photos").hidden = false;
  }

  /* Join form -> WhatsApp message */
  var form = document.getElementById("join-form");
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var err = document.getElementById("join-err");
      var name = form.elements.name.value.trim();
      var age = form.querySelector('input[name="age"]:checked');
      if (!name || !age) {
        err.textContent = !name ? "Enter your name so we know who's joining." : "Choose your age group.";
        err.hidden = false;
        (!name ? form.elements.name : form.querySelector('input[name="age"]')).focus();
        return;
      }
      err.hidden = true;
      var into = Array.prototype.map.call(form.querySelectorAll('input[name="into"]:checked'), function (i) { return i.value; });
      var msg = "Hi Gesond, please add me to the events list.\nName: " + name + "\nAge group: " + age.value + (into.length ? "\nInto: " + into.join(", ") : "");
      window.open("https://wa.me/" + WA + "?text=" + encodeURIComponent(msg), "_blank", "noopener");
    });
  }
})();
