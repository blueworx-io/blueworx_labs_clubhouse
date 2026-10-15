// assets/js/playtomic-export.js
// Select-all and the live "N of M selected" count on the Playtomic review
// table. The form works without this file; it only saves clicks.
(function () {
  var form = document.querySelector('.clubhouse-playtomic__export');
  if (!form) return;
  var all = form.querySelector('[data-playtomic-all]');
  var count = form.querySelector('[data-playtomic-count]');
  var button = form.querySelector('button[type="submit"]');
  var boxes = function () {
    return Array.prototype.slice.call(form.querySelectorAll('input[name="clubhouse_playtomic_ids[]"]:not([disabled])'));
  };
  var update = function () {
    var list = boxes();
    var ticked = list.filter(function (b) { return b.checked; }).length;
    if (count) count.textContent = ticked + ' of ' + form.querySelectorAll('input[name="clubhouse_playtomic_ids[]"]').length + ' selected';
    if (button) button.disabled = ticked === 0;
    if (all) {
      all.checked = list.length > 0 && ticked === list.length;
      all.indeterminate = ticked > 0 && ticked < list.length;
    }
  };
  if (all) {
    all.addEventListener('change', function () {
      boxes().forEach(function (b) { b.checked = all.checked; });
      update();
    });
  }
  form.addEventListener('change', update);
  update();
})();
