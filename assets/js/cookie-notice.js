// The cookie notice: show it once, remember that it was dismissed.
//
// The dismissal is kept in localStorage rather than a cookie. A notice that set
// a cookie to record that you had read a notice about cookies would be its own
// punchline, and localStorage is not sent to the server, so nothing about this
// reaches us.
//
// The notice ships hidden and is revealed here. That way a visitor who has
// already dismissed it never sees it flash up on the next page load, and a
// visitor with JavaScript off — who cannot dismiss it — is not left with a
// permanent bar across the bottom of every page they visit.
(function () {
  var KEY = 'clubhouse_cookie_notice_dismissed';
  var notice = document.getElementById('ch-cookie');
  var button = document.getElementById('ch-cookie-dismiss');
  if (!notice || !button) return;

  var stored = null;
  try {
    stored = window.localStorage.getItem(KEY);
  } catch (e) {
    // Private browsing, or storage disabled. Showing the notice every time is
    // the right failure: it is the honest state, and it is dismissible.
    stored = null;
  }
  if (stored === '1') return;

  notice.hidden = false;

  button.addEventListener('click', function () {
    notice.hidden = true;
    try {
      window.localStorage.setItem(KEY, '1');
    } catch (e) {
      // Nothing to do — it will show again next time, which is harmless.
    }
  });
})();
