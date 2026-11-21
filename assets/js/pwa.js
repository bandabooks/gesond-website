/* Gesond app: register the service worker and offer "Install the Gesond app". */
(function () {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
  }
  var KEY = 'gesond_install_hidden_until';
  function hiddenNow() { try { return Date.now() < Number(localStorage.getItem(KEY) || 0); } catch (e) { return false; } }
  function hideFor(days) { try { localStorage.setItem(KEY, String(Date.now() + days * 864e5)); } catch (e) {} }
  var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  if (standalone || hiddenNow()) return;

  function bar(html, onInstall) {
    var b = document.createElement('div');
    b.className = 'appbar';
    b.setAttribute('role', 'region');
    b.setAttribute('aria-label', 'Install the Gesond app');
    b.innerHTML = '<img src="/assets/brand/icon-192.png" alt="" width="40" height="40"><div class="appbar__txt">' + html + '</div>' +
      (onInstall ? '<button type="button" class="btn btn--gold btn--sm appbar__go">Install</button>' : '') +
      '<button type="button" class="appbar__x" aria-label="Not now">&times;</button>';
    document.body.appendChild(b);
    document.body.classList.add('has-appbar');
    b.querySelector('.appbar__x').addEventListener('click', function () { hideFor(14); b.remove(); document.body.classList.remove('has-appbar'); });
    if (onInstall) b.querySelector('.appbar__go').addEventListener('click', onInstall);
    return b;
  }

  // Android / Chrome / Edge: native install prompt
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    var deferred = e;
    var b = bar('<b>Get the Gesond app</b><span>What\'s on, your rewards card and updates, one tap away.</span>', function () {
      deferred.prompt();
      deferred.userChoice.then(function (c) { if (c.outcome !== 'accepted') hideFor(14); b.remove(); document.body.classList.remove('has-appbar'); });
    });
  });
  window.addEventListener('appinstalled', function () { hideFor(3650); });

  // iPhone / iPad Safari: no prompt exists, so show how to add it
  var ua = navigator.userAgent;
  var iOS = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var safari = /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS/.test(ua);
  if (iOS && safari) {
    setTimeout(function () {
      bar('<b>Add Gesond to your Home Screen</b><span>Tap the Share button, then "Add to Home Screen".</span>', null);
    }, 2500);
  }
})();
