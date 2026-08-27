// Is a shop installed on the site under test?
//
// The member area belongs to SureCart (issue #261): with no shop there is no
// membership to sign in to, so this plugin serves neither the member area nor
// the login page. CI provisions WordPress with this plugin and nothing else, so
// every member-area spec has to say what it needs rather than assume it.
//
// Locally: `npm run wp:up && npm run wp:shop` gives you a shop, and these run.
//
// This is a coverage gap worth closing rather than living with — the member
// area is the screen most worth testing and CI cannot reach it. The fix is to
// install SureCart in the shared CI harness; see the note on issue #261.

/** True when this site serves a member area, i.e. a shop is installed. */
async function hasShop(page) {
  const res = await page.goto('/member-dashboard/', { waitUntil: 'domcontentloaded' });
  return res !== null && res.status() !== 404;
}

module.exports = { hasShop };
