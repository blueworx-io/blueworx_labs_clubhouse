// Turns the member area's markup into something that can be compared across
// the ClubHouse → Labs switch: Labs renamed the CSS classes, and nonces,
// post ids and the plugin's asset version differ per site. What is left is
// the structure and the words, which is what a member actually sees.
const RENAMES = [
  [/data-clubhouse-member/g, 'data-blueworx-store'],
  [/clubhouse-member-navtab-/g, 'blueworx-store-navtab-'],
  [/clubhouse-member-tab-/g, 'blueworx-store-tab-'],
  [/clubhouse-member-view/g, 'blueworx-store-view'],
  [/clubhouse-member__/g, 'blueworx-store__'],
  [/clubhouse-checkout__/g, 'blueworx-checkout__'],
  [/\bclubhouse-member\b/g, 'blueworx-store'],
  [/\bclubhouse-checkout\b/g, 'blueworx-checkout'],
];

function normalise(html) {
  let out = html;
  for (const [from, to] of RENAMES) out = out.replace(from, to);
  return out
    // Labs draws icons as the design system's <i data-lucide> element where
    // ClubHouse inlined the SVG; both render the same glyph, so icons are
    // reduced to a marker on both sides.
    .replace(/<svg\b[\s\S]*?<\/svg>/g, '<ICON>')
    .replace(/<i class="bw-icon" data-lucide="[^"]*" aria-hidden="true"><\/i>/g, '<ICON>')
    // Two phrases Labs made site-agnostic (a ruling in its plan's ledger):
    .replace(/The club never sees it./g, 'The site never sees it.')
    .replace(/Back to the club site/g, 'Back to the site')
    .replace(/_wpnonce=[a-f0-9]+/g, '_wpnonce=NONCE')
    .replace(/nonce=[a-f0-9]+/g, 'nonce=NONCE')
    // A hidden nonce field's value sits in its own attribute, not a query
    // string — mask it whichever way round the attributes render, and
    // whether the field is picked out by its name or its id.
    .replace(/(name="[^"]*nonce"[^>]*value=")[a-f0-9]+(")/g, '$1NONCE$2')
    .replace(/(value=")[a-f0-9]{10}("[^>]*name="[^"]*nonce")/g, '$1NONCE$2')
    .replace(/(id="[^"]*nonce"[^>]*value=")[a-f0-9]+(")/g, '$1NONCE$2')
    .replace(/(value=")[a-f0-9]{10}("[^>]*id="[^"]*nonce")/g, '$1NONCE$2')
    .replace(/\?ver=[^"&]+/g, '?ver=VER')
    .replace(/page_id=\d+/g, 'page_id=ID')
    .replace(/\s+/g, ' ')
    .replace(/> </g, '><')
    .trim();
}

module.exports = { normalise };
