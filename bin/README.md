# bin/

## build-zip.sh — the deployment artifact

```bash
npm run build:zip            # writes ../blueworx-labs-clubhouse.zip
bash bin/build-zip.sh DIR    # or choose the output directory
```

Builds the plugin zip from the allowlist declared at the top of the script, then
verifies the artifact it just produced. The zip is the deployment artifact — never
copy individual files to a host.

### What it guards

`preview/index.php` **defines its own `ABSPATH`**, so it renders a full page
without WordPress bootstrapping it. It exists to run the design engine on
localhost with no database, which is exactly why it must never ship: on a live
club site it would be reachable unauthenticated. It cannot be made safe with an
`ABSPATH` guard, because running without WordPress *is the point* of it.

The zip has never actually shipped `preview/` — this was a latent process risk,
not an exposure. But the protection was that whoever built the zip remembered the
allowlist, and the obvious way to build one (zip up the repo) gets it wrong. CI
now runs this script on every PR, so the rule is enforced instead.

### Why the allowlist is an allowlist

A new development directory is excluded by default, rather than shipped because
nobody remembered to add it to a denylist. The script fails if an allowlisted
path is missing, so a rename cannot silently drop a shipped directory either.

### Why not Compress-Archive

PowerShell's `Compress-Archive` writes **backslash** entry paths on Windows.
WordPress hosts are Linux, so those mis-extract and WordPress reports *"Plugin
file does not exist."* on activate. It is banned for this artifact. GNU `tar`
cannot write zip format at all, so on Linux the script uses `zip(1)`; on Windows
and macOS it uses bsdtar (on Windows that means System32's `tar.exe`
specifically, not whatever `tar` resolves to first). Whichever tool is used, the
script asserts the entries came out with forward slashes.

### The checks

Every one runs against the built zip, not against the intent:

- every entry uses forward slashes, nested under `blueworx-labs-clubhouse/`
- `blueworx-labs-clubhouse/blueworx-labs-clubhouse.php` sits directly inside it
- no development directory ships (`preview`, `tests`, `docs`, `vendor`,
  `node_modules`, `.github`, `.superpowers`, `.git`)
- no development file ships (`*.spec.js`, phpunit/phpcs/composer/playwright
  config, `CLAUDE.md`)

The directory and file checks are belt and braces: the allowlist already excludes
all of them, so a hit means one is **nested inside a shipped directory** — the
case a human reviewer misses.
