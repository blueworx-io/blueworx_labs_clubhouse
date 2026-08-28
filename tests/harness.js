// Which harness this run is actually pointed at.
//
// The `wordpress` Playwright project does NOT mean the run targets WordPress.
// When no WordPress URL is set, playwright.config.js gives that project the
// preview's baseURL so `npm test` alone still covers the whole suite — so the
// project is named `wordpress` while the server answering is the preview.
//
// A spec that picks its addresses from `test.info().project.name` therefore
// asks for WordPress-shaped addresses ('/membership/') from the preview, which
// serves none of them, and every assertion after the navigation fails with the
// element simply not found. That is what happened to the hero heading and demo
// accent specs (issue #288).
//
// The honest question is the one playwright.config.js and global-setup.js both
// already ask: is a WordPress URL set? This is that question, in one place, so
// the next spec that needs it cannot get it wrong a third time.

const targetingWordPress = () => !!(process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL);

module.exports = { targetingWordPress };
