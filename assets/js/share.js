// Copy link, on the share row at the foot of a news story.
//
// The button ships with the hidden attribute set and is revealed here, so a
// reader without script — or on a browser with no clipboard access, which
// includes any page not served over HTTPS — never sees a button that does
// nothing when pressed. The three share links beside it are plain anchors and
// work regardless.
(function () {
  var buttons = document.querySelectorAll('[data-clubhouse-copy]');
  if (!buttons.length) return;

  var canCopy = !!(navigator.clipboard && navigator.clipboard.writeText);
  if (!canCopy) return;

  Array.prototype.forEach.call(buttons, function (button) {
    button.hidden = false;

    button.addEventListener('click', function () {
      var url = button.getAttribute('data-clubhouse-copy') || '';
      navigator.clipboard.writeText(url).then(
        function () {
          var original = button.textContent;
          button.textContent = button.getAttribute('data-copied-label') || 'Link copied';
          // Announced as well as shown: the label change is the only feedback,
          // and a screen reader would otherwise get nothing at all.
          button.setAttribute('aria-live', 'polite');
          setTimeout(function () {
            button.textContent = original;
          }, 2000);
        },
        function () {
          // Permission refused at the moment of pressing. Say so rather than
          // leaving the reader believing the link is on their clipboard.
          button.textContent = 'Press Ctrl+C to copy';
        }
      );
    });
  });
})();
