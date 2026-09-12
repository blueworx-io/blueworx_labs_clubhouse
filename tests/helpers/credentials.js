// The admin account the WordPress specs sign in with. CI and bin/wp-test.mjs
// pass it in; the fallback is what the foundation's local harness creates,
// so a spec run straight against `wp:up` signs in too. One place, so the next
// time the harness changes its password it is one line here, not sixteen.
const ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.WP_ADMIN_PASS || 'admin';

module.exports = { ADMIN_USER, ADMIN_PASS };
