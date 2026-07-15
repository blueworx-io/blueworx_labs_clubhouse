# Accent-tinted backbone blocks — design spec

**Date:** 2026-07-15
**Branch:** `full-bleed-home-hero`
**Status:** Approved for planning

## Goal

Stop near-black ink being the de-facto second brand colour on light Base Looks.
The largest structural blocks — the top banner, the Home hero fill, and the news
ticker — currently render as the look's near-black ink, so every club's site
shares the same black backbone and the club's own accent is reduced to a
highlight.

Instead, fill those blocks with the look's ink **pulled 30% toward the club's
accent**, so each club's site reads as tinted with its own brand while keeping
the weight and grounding the near-black currently provides.

The tint is derived by the colour engine from each look's own tokens. It is a
system-wide rule, not per-look configuration: any future Base Look inherits the
behaviour with no settings, and none can drift out of it.

## Background — why ink carries so much weight today

The colour engine derives exactly four tokens (`--color-accent`,
`--color-accent-ink`, `--color-accent-deep`, `--color-accent-wash`), all from a
single club-supplied accent. There is no secondary hue anywhere in the system, so
when a design needs a second heavy fill, ink is the only value available that is
guaranteed legible whatever accent a club picks.

Ink is also *polarity-independent*: the `background: var(--color-ink); color:
var(--color-bg)` pairing yields a dark block on a light look and a light block on
a dark look automatically. That is precisely why it scales — and why the tinted
replacement must also be derived from each look's own ink rather than from a
fixed colour.

Measured on `court-side.css`: ink is a background fill **15** times against the
accent's **17**. The observation that black behaves like a secondary colour is
quantitatively correct.

## The rule

Add one derived token, `--color-accent-block`, to `Color_Engine::derive()`:

1. **Start** at `mix( $accent, $shell_ink, 0.30 )` — the look's own ink pulled
   30% toward the club's accent.
2. **Guard:** if that fails `contrast( accent-block, $shell_bg ) >= 4.5`, step the
   blend back toward ink until it clears, mirroring the descending
   integer-stepping idiom `derive()` already uses for `accent-deep`:

   ```php
   $block = self::normalize_hex( $shell_ink ); // floor: always legible
   for ( $i = 6; $i >= 0; $i-- ) {             // 6/20 = 0.30 max tint
       $candidate = self::mix( $accent, self::normalize_hex( $shell_ink ), $i / 20 );
       if ( self::contrast_ratio( $candidate, $shell_bg ) >= 4.5 ) {
           $block = $candidate;
           break;
       }
   }
   ```

   Stepping in twentieths matches `accent-deep`'s existing grid, and breaking on
   the first pass keeps the strongest tint legibility allows. Termination is
   guaranteed: `i = 0` yields plain ink, which every look already guarantees is
   legible against its own shell.

> **Implementer's note.** `mix( string $a, string $b, float $weight_a )` weights
> toward its **first** argument, so `mix( $accent, $shell_ink, 0.30 )` is 30%
> accent / 70% ink — not the reverse. Swapping the arguments produces a
> 70%-accent block (`#93b23e` for Court Side + lime) at 2.28 contrast, which
> fails the guard at *every* step, so the loop falls through to its floor and
> returns plain ink. The failure mode is therefore **the tint silently
> disappearing** — the page looks exactly like it does today rather than throwing.
> The `#4f5c28` anchor test below is what catches it. `normalize_hex()` is
> `protected`, so it is callable from inside the engine but not from tests.

### Why one constraint is sufficient

These blocks are painted `background: var(--color-accent-block); color:
var(--color-bg)`. The single requirement `contrast(accent-block, shell_bg) >= 4.5`
therefore delivers two guarantees at once:

- the block separates from the page it sits on, **and**
- the `--color-bg` text on the block is legible,

because both are the same colour pair. No second token (a "text on block" ink) is
required, and none should be added.

### Measured headroom at 30%

Worst case across all three shipped looks and all five preview swatches is
**6.1:1** (Members' House + Volt Lime), comfortably clear of the 4.5 minimum, so
the guard is a safety net rather than a routine code path. For reference, the
resolved values on Court Side:

| Club accent | `--color-accent-block` | Contrast vs shell bg |
| --- | --- | --- |
| Volt Lime `#c6f24e` | `#4f5c28` dark olive | 6.8 |
| Signal Orange `#ff5b23` | `#602e1b` burnt umber | 10.4 |
| Court Teal `#12c3b0` | `#194d46` dark pine | 9.0 |
| Cobalt `#3b5bdb` | `#252e53` navy | 12.4 |
| Berry `#c2337a` | `#4e2235` dark plum | 12.3 |

40% was rejected: Volt Lime falls to 5.1 on Court Side and exactly 4.5 on
Members' House, so the guard would begin pulling individual hues back and
different clubs would visibly tint by different amounts. 20% was rejected as too
close to near-black to answer the original problem.

## Scope

**In scope**

- `--color-accent-block` added to `Color_Engine::derive()`, with the guard above.
- **Court Side** and **Members' House**: `.ch-banner`, `.ch-home-hero__bg`,
  `.ch-home-hero__bg--empty`, and `.ch-ticker` switch their fill from
  `--color-ink` to `--color-accent-block`.
- Preview switcher: carry the new token so the five swatches keep re-theming the
  page live through the real engine.
- Engine, stylesheet, and Playwright tests (below).
- Version bump + changelog.

**Out of scope (explicitly excluded)**

- **Floodlight.** It fills these blocks with `--color-paper` (`#1e1913`) and uses
  ink as their *text* colour — its banner/hero/ticker are slightly-raised dark
  panels separated by a border, not inverted ink blocks. It has no black-backbone
  problem, and a rule that replaces *ink-as-fill* does not reach it. This is an
  emergent property of the rule, **not** a per-look exception, and no Floodlight
  code changes.
- **`.ch-home-hero__scrim`.** Keeps neutral `--color-ink`. The scrim darkens a
  club's hero *photograph*; tinting it would put a duotone wash over club
  photography with results varying unpredictably by hue and image.
- The other 12 ink-filled surfaces on Court Side — the `Join the Club` CTA pill,
  all hover states (`.ch-btn--ghost:hover`, `.ch-home-hero__tile:hover`,
  `.ch-tiles__tile:hover`, `.ch-sponsors__tile:hover`), the fixtures/results
  lists, the contact info panel, the eyebrow band, the active tab, the skip link,
  and the hamburger bars. Near-black deliberately remains the anchor for
  controls, hovers, and utility affordances.
- Adding a genuine per-look secondary hue token (a larger change, considered and
  set aside).
- Any change to `--color-accent-deep`, which stays a *text* colour. It is
  explicitly not reused as a fill: on a dark shell it derives toward white, so
  filling with it would turn Floodlight's hero bright lime.

## Files touched

| File | Change |
| --- | --- |
| `includes/theme/class-color-engine.php` | Derive `--color-accent-block`; update `derive()` docblock + return shape. |
| `assets/looks/court-side.css` | 4 fills swap `--color-ink` → `--color-accent-block`. |
| `assets/looks/members-house.css` | Same 4 fills. |
| `preview/index.php` | Add `block` to the palette array + one `setProperty` line in the switcher JS. |
| `tests/php/ColorEngine*Test.php` | New coverage (below); existing `derive()` shape assertions gain the key. |
| `tests/php/CourtSideStylesheetTest.php`, `MembersHouseStylesheetTest.php`, `FloodlightStylesheetTest.php` | Assert the token is used / not used per look. |
| `tests/*.spec.js` | Home banner/hero/ticker resolve to the derived colour. |

`Theme_Css::compose()` needs **no change** — it `array_merge`s `derive()`'s
output, so the new token is emitted to `:root` automatically. This is worth
stating explicitly so the implementer does not go looking for a registration step
that does not exist.

## Testing

**Engine (PHPUnit)**

- `--color-accent-block` clears AA (>= 4.5) against the shell background across
  saturated hues on all three shipped shells — mirroring the existing
  `test_accent_ink_clears_AA_across_saturated_hues`. **Verified**: passes for all
  seven hues in the existing `hues()` provider on every real shell.
- **Regression anchor:** Court Side + Volt Lime resolves to exactly `#4f5c28`.
  This is the test that catches an inverted `mix()`.
- **The guard fires and still lands legible.** Note the guard **never fires on the
  shipped light shells at 30%** — verified across the full hue provider plus
  `#ffffff` and `#ffff00` — so this needs a synthetic shell: on
  `bg #faf8f3 / ink #444444`, the 30% lime tint measures 4.49 and fails, so the
  guard steps back one notch to `#657047` at 4.99 — still tinted, now legible.
- **The floor is ink.** On a shell whose ink cannot itself clear AA
  (`bg #808080 / ink #ffffff`, where ink measures 3.95), the token degrades to
  exactly the ink. The honest guarantee is therefore *"never worse than plain
  ink"* — anything ink-derived is bounded by ink's own legibility, and today's
  `background:var(--color-ink)` blocks have precisely the same property. AA on
  such a shell is not achievable and is not claimed.
- `derive()` returns the new key alongside the existing four. Note
  `ColorEngineDeriveTest::test_returns_expected_keys` asserts the exact key list
  via `assertSame` on `array_keys`, so it must be updated.

**Stylesheets (PHPUnit)**

- Court Side and Members' House reference `--color-accent-block` on all four
  fills and no longer use `--color-ink` for them.
- Court Side's `.ch-home-hero__scrim` still uses `--color-ink`.
- Floodlight references `--color-accent-block` nowhere.

**Browser (Playwright)**

- On the DB-free preview, the Home banner, hero fill, and ticker resolve to the
  derived tint rather than near-black.

## Risks and follow-ups

- **The `CLUB NEWS` ticker label.** It is full `--color-accent` on what becomes a
  dark-olive ticker rather than near-black, making it a tonal pairing rather than
  a contrasting one. Legibility is unaffected (its text is `--color-accent-ink`
  on `--color-accent`, an untouched pair), but it will pop less. Review visually
  once built; if it reads flat it needs its own treatment, tracked separately.
- **Live sites restyle on upgrade.** Every club on a light look sees its banner,
  hero, and ticker change colour, with no opt-out. Judged acceptable for a
  pre-1.0 template, but it is a visible change to live sites and belongs in the
  changelog in plain language.
- **Judging 30%.** Build behind the five preview swatches and review the real
  thing across all five hues on both light looks before treating 30% as final.
  The constant should be trivial to tune.
