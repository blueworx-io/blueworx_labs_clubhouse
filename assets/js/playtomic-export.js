// assets/js/playtomic-export.js
// Two small helpers for the Playtomic screen. Both forms work without this
// file; it only saves clicks.
//
// 1. The venues repeater: "Add a venue" stamps a new row from the template,
//    "Remove" takes a row away, and a row's title follows its name.
// 2. Select-all and the live "N of M selected" count on the review table.
(function () {
  var venues = document.querySelector('[data-playtomic-venues]');
  var template = document.querySelector('[data-playtomic-venue-template]');
  var add = document.querySelector('[data-playtomic-add]');

  if (venues && template && add) {
    var next = venues.querySelectorAll('[data-playtomic-venue]').length;

    var wire = function (row) {
      var remove = row.querySelector('[data-playtomic-remove]');
      var name = row.querySelector('input[name$="[name]"]');
      var title = row.querySelector('[data-playtomic-venue-title]');
      if (remove) {
        remove.addEventListener('click', function () {
          row.remove();
          // A club with nothing left still needs a row to type into.
          if (!venues.querySelector('[data-playtomic-venue]')) addRow();
        });
      }
      if (name && title) {
        name.addEventListener('input', function () {
          title.textContent = name.value.trim() || 'Venue';
        });
      }
    };

    var addRow = function () {
      var html = template.innerHTML.split('__i__').join(String(next++));
      var holder = document.createElement('div');
      holder.innerHTML = html;
      var row = holder.firstElementChild;
      venues.appendChild(row);
      wire(row);
      var first = row.querySelector('input');
      if (first) first.focus();
      return row;
    };

    Array.prototype.forEach.call(venues.querySelectorAll('[data-playtomic-venue]'), wire);
    add.addEventListener('click', function () { addRow(); });
  }

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
