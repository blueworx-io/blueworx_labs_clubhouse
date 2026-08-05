(function () {
  if (!('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion:reduce)').matches) {
    return;
  }
  var els = document.querySelectorAll('.ch-main > *:not(.ch-hero):not(.ch-home-hero)');
  if (!els.length) {
    return;
  }

  // Nothing may stay hidden because the reveal failed. Hiding is only ever
  // applied by this script, so a script that never loads leaves the page
  // visible — but a script that hides everything and THEN throws, or whose
  // observer never fires, would leave nine blank sections with no way back.
  // show() is the single undo, called on any error and on a watchdog timer.
  var shown = false;
  function show() {
    if (shown) {
      return;
    }
    shown = true;
    els.forEach(function (el) {
      el.classList.remove('ch-reveal');
      el.classList.remove('is-in');
    });
  }

  try {
    els.forEach(function (el) {
      el.classList.add('ch-reveal');
    });
    // Liveness, not progress: an observer reports on every element the moment
    // it is observed, intersecting or not. So "it has called us back" is true
    // within a frame of a working observer, while a visitor who simply has not
    // scrolled yet never trips it and still gets the animation.
    var fired = false;
    var io = new IntersectionObserver(function (entries) {
      fired = true;
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-in');
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.08 });
    els.forEach(function (el) {
      io.observe(el);
    });

    setTimeout(function () {
      if (!fired) {
        show();
      }
    }, 2000);
  } catch (e) {
    show();
  }
})();
