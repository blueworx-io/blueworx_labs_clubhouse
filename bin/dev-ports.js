// Local dev/test ports, derived from the plugin slug.
//
// Both local harnesses (the DB-free PHP preview, and the real WordPress that
// the foundation's wp-test-env.mjs provisions) bind a port. The foundation's
// defaults are the same for every plugin, so two plugin repos worked on at the
// same time collide: the second `up` silently attaches to the first one's
// server, and you end up testing the wrong plugin. That is not hypothetical —
// it is how this file came to exist.
//
// Deriving the port from the slug gives every repo its own stable pair with no
// per-machine config and nothing to remember. Stable matters: the port has to
// survive a reboot so a bookmark and a running session still agree.
//
// Override either with an env var when you need a specific port.

const { name } = require('../package.json');

const PREVIEW_BASE = 8300;
const WORDPRESS_BASE = 8600;
const SPAN = 200; // 8300-8499 preview, 8600-8799 WordPress.

/** FNV-1a. Small, dependency-free, and stable across Node versions — Node's
 *  string hashing is not guaranteed to be, and this value is baked into URLs. */
function hash(slug) {
  let h = 0x811c9dc5;
  for (let i = 0; i < slug.length; i += 1) {
    h ^= slug.charCodeAt(i);
    h = Math.imul(h, 0x01000193) >>> 0;
  }
  return h;
}

/** Same offset for both harnesses, so one repo's ports share an index. */
const offset = hash(name) % SPAN;

function previewPort() {
  return Number(process.env.CLUBHOUSE_PREVIEW_PORT) || PREVIEW_BASE + offset;
}

function wordpressPort() {
  return Number(process.env.CLUBHOUSE_WP_PORT) || WORDPRESS_BASE + offset;
}

module.exports = { previewPort, wordpressPort, slug: name };

// `node bin/dev-ports.js` prints them, so shell scripts and npm scripts can ask
// rather than duplicate the arithmetic.
if (require.main === module) {
  const which = process.argv[2];
  if (which === 'preview') console.log(previewPort());
  else if (which === 'wordpress') console.log(wordpressPort());
  else console.log(`slug=${module.exports.slug} preview=${previewPort()} wordpress=${wordpressPort()}`);
}
