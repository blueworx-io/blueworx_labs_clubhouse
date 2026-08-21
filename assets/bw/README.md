# BlueWorx Admin Design System (vendored)

`bw.css` is copied from the Claude Design project
`0b906da5-c173-4d93-b806-f559a4baf924`, directory
`_ds/labs-wordpress-backend-components-75753ef2-9fb7-4c3a-ba0e-a7a0b967565d/`.

Do not hand-edit it. To take a CSS update, re-fetch every file listed in the
header comment of `bw.css` (via the `DesignSync` tool, `method: "get_file"`,
against that project and directory), concatenate in that order, and rewrite
the font urls to `./fonts/`.

## Fonts are sourced differently — read this before re-syncing

`fonts/` is **not** re-fetched from Claude Design. The six `.woff2` files are
downloaded directly from Fontsource, which is what the design system itself
ships:

```
https://cdn.jsdelivr.net/npm/@fontsource/inter@5/files/inter-latin-400-normal.woff2   (23664 bytes) -> fonts/inter-400.woff2
https://cdn.jsdelivr.net/npm/@fontsource/inter@5/files/inter-latin-500-normal.woff2   (24272 bytes) -> fonts/inter-500.woff2
https://cdn.jsdelivr.net/npm/@fontsource/inter@5/files/inter-latin-600-normal.woff2   (24452 bytes) -> fonts/inter-600.woff2
https://cdn.jsdelivr.net/npm/@fontsource/sora@5/files/sora-latin-400-normal.woff2     (14724 bytes) -> fonts/sora-400.woff2
https://cdn.jsdelivr.net/npm/@fontsource/sora@5/files/sora-latin-600-normal.woff2     (15000 bytes) -> fonts/sora-600.woff2
https://cdn.jsdelivr.net/npm/@fontsource/sora@5/files/sora-latin-700-normal.woff2     (15128 bytes) -> fonts/sora-700.woff2
```

**Never fetch a font through a tool that returns it as base64 into a model's
context.** That channel silently corrupts binary payloads — the bytes change
but the length usually doesn't, so a plain file-size check does not catch it,
and a corrupted `.woff2` still passes a casual look (it still starts with the
`wOF2` signature and still opens). Always download fonts with a direct binary
fetch (e.g. `curl`), never through a base64 relay.

The byte sizes above are the check: after downloading, confirm each file is
exactly that many bytes, and confirm its own WOFF2 header (bytes 8-11,
big-endian) declares the same length as the file on disk — that second check
is what `DashboardAssetsTest::test_every_font_is_a_whole_file` asserts on
every commit.

## Test coverage note

`DashboardAssetsTest::test_nothing_in_it_can_reach_markup_that_has_not_opted_in`
treats every rule as one level of nesting. `@keyframes` rules nest one level
deeper (percentage/`from`/`to` stops inside the keyframes body), which the
flat selector check can't see past without extra handling — its `50%` stop
was briefly flagged as an unscoped selector. Keyframe stops are inert outside
an `@keyframes` block and can never restyle arbitrary markup, so the test was
fixed to drop whole `@keyframes` blocks before checking, rather than scoping
or weakening the underlying check. No rule in `bw.css` needed rescoping — the
allowed prefixes remain `:root`, `.bw-`, `.clubhouse-member`.

This is the member area's look only. The club's public site is styled by
`assets/looks/`, and the two never meet: `bw.css` is enqueued on the member
area, checkout and order confirmation and nowhere else, and every rule in it
is scoped to `.bw-admin` or a `.bw-*` class.
