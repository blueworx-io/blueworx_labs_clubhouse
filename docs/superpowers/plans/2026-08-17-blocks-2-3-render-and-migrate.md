# Blocks, plans 2 and 3: the render path and the migration

Shipped in v0.71.0. This is the record of what was built and, more usefully,
the decisions taken along the way that the design spec did not settle.

Spec: `docs/superpowers/specs/2026-08-13-page-composition-and-block-library-design.md`.

## What now happens when a page renders

`Page_Composer::page()` reads the page's block ids, resolves each against the
library, drops the ones it cannot render, sorts by each block's position with
the stored order breaking ties, and renders each between the header and footer.
`Page_Map::render()` calls it whenever the site has been composed and falls back
to the old page methods when it has not.

Four new pieces sit behind that:

| File | Does |
| --- | --- |
| `blocks/class-block-content.php` | Reads a field off a block, falling back to its default — the old `cget()`/`citems()` rule that empty means unset. |
| `blocks/class-page-state.php` | What a page works out once and several blocks then read: the tiers and whether they sell, the filter pills and filtered rows, the news query and its lead story. |
| `blocks/class-block-defaults.php` | The ~55 sets of default copy, lifted out of the page methods. Computed, not frozen — the Home lede still counts the club's sports. |
| `blocks/class-block-renderers.php` | One renderer per block type: content in, `Sections` markup out. |
| `render/class-page-composer.php` | The loop, plus the header and footer. |
| `blocks/class-block-seeder.php` | Seeds a fresh site, migrates an existing one, and syncs one that is still edited by the old screens. |

## Decisions the spec left open

**A shared block cannot always share a position.** The Membership tiers are
second on their page and seventh on Home, and a block carries one position. So
Home gets a block of its own with a `mirror` setting naming the Membership
block, and reads that block's content. Editing the tiers once still changes both
pages, which is the behaviour that mattered.

**Anchors follow the address, not the type.** The spec wanted `page + type key`;
parity wanted today's ids. A seeded or migrated block carries the address it came
from, so it takes that address's anchor and every link an owner has already made
keeps working. A block created fresh has no address and takes its type's name.

**The Home closing band is one block with two switches.** `home/social` and
`home/info` were always one rendered band with a toggle each. They become one
block with `show_social` and `show_columns` settings, migrated from the two
toggles. The block leaves the page only when both halves are off.

**The cookie notice lives on the footer block**, under `cookie_*` keys. It has
never been a section anyone could place; it renders inside the footer.

**Integration gating is by address, not by type.** Only `calendar/booking` has
ever needed LatePoint present — the Bookings page's own slots are unconditional,
because that page is not served at all without it. A block with no address falls
back to its type's requirement.

## The parity check

`tests/php/BlockParityTest.php` renders all thirteen pages both ways and compares
whole strings, across five scenarios: a fresh site, a club with saved content, a
club with sections and a page hidden, the closing band half-off, and every
filtered list view including a filter that matches nothing. A difference fails
the build.

This is why the eleven page methods are still in `Page_Renderer`: they are the
thing parity is measured against. They go in step 5, with the test.

## Still interim

- The old Club Pages and Setup screens remain the editor. Every save is projected
  onto the blocks by `Block_Seeder::sync()`, called from the content controller,
  the setup controller and the import applier. This scaffolding comes out when
  the new screens land.
- `Blocks_Installer` composes a site once, on activation or the first admin
  request after a version change, and never touches a site that is already
  composed — so an owner's page edits survive an update.
- Not runnable in the DB-free preview: the migration against a real club's saved
  options. A manual WordPress smoke test is still required before release.
