(function () {
  if (!('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion:reduce)').matches) {
    return;
  }
  var els = document.querySelectorAll('.ch-main > *:not(.ch-hero)');
  if (!els.length) {
    return;
  }
  els.forEach(function (el) {
    el.classList.add('ch-reveal');
  });
  var io = new IntersectionObserver(function (entries) {
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
})();
