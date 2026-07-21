# Testing

Two harnesses, one suite. Every spec belongs to exactly one of them, and
`playwright.config.js` routes it there via a project — so a single run covers
both and nothing is silently skipped.

| Project | Harness | Specs |
|---|---|---|
| `wordpress` | Real WordPress (PHP + SQLite), provisioned per run | everything not tagged `@preview` |
| `preview` | The DB-free PHP preview (`preview/index.php`) | specs tagged `@preview` |

## Day to day

```bash
npm test          # everything against the preview — fast, no database
npm run wp:up     # provision + start a real local WordPress
npm run test:wp   # wordpress specs → WordPress, @preview specs → the preview
npm run wp:down   # stop it
```

`npm test` is the quick loop. Run `npm run test:wp` before opening a PR — it is
what CI runs, and it exercises WordPress's own routing, template loading and
stored settings, none of which the preview can reach.

Requires PHP on PATH with `pdo_sqlite`, and the foundation repo
(`bluegroup_core_foundation`) cloned alongside this one — `wp:up` calls its
`scripts/wp-test-env.mjs`. `.wp-test/` is the throwaway WordPress tree; delete it
for a clean slate.

## Writing specs that work on both

Navigate with `?clubhouse_page=<slug>`. That is WordPress's real query var
(`Frontend::QUERY_VAR`), and `preview/index.php` accepts it too, so one URL form
works everywhere. Do **not** use `?page=` — it is the preview's own parameter and
against WordPress it silently renders Home, which makes a spec pass for the wrong
reason or fail for a confusing one.

Tag a spec `@preview` only when it genuinely cannot run against WordPress —
currently those that drive the preview's injected `.ch-switcher`, or its `?look=`
and `?demo=1` params. WordPress persists the look as a setting instead, so there
is nothing there for them to drive. Prefer making a spec portable over tagging it.

Site-wide state the specs cannot set themselves is seeded by
[`tests/global-setup.js`](../tests/global-setup.js) — currently demo mode, which
WordPress reads from an option while the preview takes `?demo=1`. Add to it
rather than making specs tolerate missing state; a spec that skips when the state
is absent is a spec that can quietly stop testing anything.

## Running several plugins at once

Both harnesses bind a port, and the foundation's defaults are the same for every
plugin — so two plugin repos in flight at once would collide, the second `up`
attaching to the first one's server and testing the wrong plugin.
[`bin/dev-ports.js`](../bin/dev-ports.js) derives this plugin's ports from its
slug so that cannot happen:

```bash
npm run ports     # slug=blueworx-labs-clubhouse preview=8405 wordpress=8705
```

`.wp-test/` is already per-repo, so the databases were never shared. Override
with `CLUBHOUSE_PREVIEW_PORT` / `CLUBHOUSE_WP_PORT` if you need a specific port —
or if two slugs ever happen to hash to the same offset.

## Known rough edge

On Windows, the foundation's `wp-test-env.mjs down` kills the PID it recorded but
not the process tree, so a stale `php -S` can keep holding the port. If routes
start 404ing or a page renders without the plugin, check for a leftover listener
before debugging anything else:

```bash
netstat -ano | grep ":8705.*LISTENING"
```
