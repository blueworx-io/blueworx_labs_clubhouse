<?php
// tests/php/LookCoverageTest.php

use PHPUnit\Framework\TestCase;

/**
 * Guards the invariant that every Base Look is built from the same building
 * blocks. Six components were once styled by court-side.css alone, so sports,
 * teams, events and calendar rendered unstyled under the other two looks for as
 * long as nothing checked.
 *
 * The assertion is PARITY, not absolute coverage. A handful of emitted classes
 * carry no rule in any look — markup hooks such as ch-tiles__label that inherit
 * and render correctly. Demanding a rule for each would mean styling hooks that
 * need none, or maintaining an ever-growing exemption list.
 *
 * Known limit: `emitted_classes()` only sees classes written literally in a
 * `class="…"` attribute. A class built by string concatenation — e.g.
 * `'ch-badge--' . $mod` in class-sections.php — is invisible to the scraper.
 * `test_no_classes_render_outside_scraped_files()` keeps that blind spot
 * honest by asserting no other rendering file has moved markup out from under
 * this check; it says nothing about concatenated classes within the files it
 * does scrape.
 */
final class LookCoverageTest extends TestCase {

	private const LOOKS = array( 'court-side', 'floodlight', 'members-house' );

	/**
	 * Emitted classes that carry no rule in ANY look, and correctly so. Pinned
	 * so that all three looks drifting *together* is still caught. Growing this
	 * list is a deliberate review decision, not a way to silence a failure.
	 */
	private const KNOWN_UNSTYLED = array(
		'ch-contact__line',
		'ch-faq-wrap',
		'ch-footer__brand-col',
		'ch-footer__col',
		'ch-footer__nl',
		'ch-info__col',
		'ch-info__line',
		'ch-milestone__body',
		'ch-policy',
		'ch-social__label',
		'ch-social__text',
		'ch-split',
		'ch-tabs',
		'ch-tiles__label',
	);

	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Classes the renderer actually emits.
	 *
	 * Only whole, literal class tokens count. `class="ch-badge--<?php echo $mod; ?>"`
	 * would otherwise yield the non-class `ch-badge--`, so any token ending in a
	 * separator is a truncated interpolation and is dropped.
	 */
	private function emitted_classes(): array {
		$out   = array();
		$files = glob( $this->root() . '/includes/render/*.php' );
		sort( $files );
		foreach ( $files as $path ) {
			$php = (string) file_get_contents( $path );
			preg_match_all( '/class="([^"]*)"/', $php, $attrs );
			foreach ( $attrs[1] as $attr ) {
				preg_match_all( '/\bch-[a-z0-9_-]+/', $attr, $classes );
				foreach ( $classes[0] as $class ) {
					if ( str_ends_with( $class, '-' ) || str_ends_with( $class, '_' ) ) {
						continue; // Truncated by a PHP interpolation — not a real class.
					}
					$out[ $class ] = true;
				}
			}
		}
		return array_keys( $out );
	}

	/**
	 * Classes some selector in the given stylesheets targets.
	 *
	 * Matches whole class tokens anywhere in a selector, so descendant and child
	 * combinators count: `.ch-split > *` covers `ch-split`. Matching on the whole
	 * token also stops `.ch-contact__lines` from satisfying `ch-contact__line`.
	 */
	private function styled_classes( string ...$paths ): array {
		$out = array();
		foreach ( $paths as $path ) {
			$css = (string) file_get_contents( $path );
			$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css ); // Comments name classes too.
			// Blank out declaration bodies so a class named in a property value
			// (a content: string, say) is never mistaken for a selector. Single
			// pass, non-recursive: `[^{}]*` cannot match a nested `{ }`, so this
			// assumes today's CSS never nests at-rules more than one level deep
			// (e.g. one `@media { .sel { ... } }`, not `@media { @supports { ... } }`).
			// A second level of nesting would leave the outer block's own
			// declarations unblanked, and a class named in one of them would be
			// misread as a selector.
			$selectors = preg_replace( '/\{[^{}]*\}/', '|', $css );
			preg_match_all( '/\.(ch-[a-z0-9_-]+)/', (string) $selectors, $found );
			foreach ( $found[1] as $class ) {
				$out[ $class ] = true;
			}
		}
		return array_keys( $out );
	}

	/** @return array<int,string> emitted classes no rule in base.css or this look targets */
	private function unstyled_for( string $look ): array {
		$styled = $this->styled_classes(
			$this->root() . '/assets/looks/base.css',
			$this->root() . '/assets/looks/' . $look . '.css'
		);
		$gap = array_values( array_diff( $this->emitted_classes(), $styled ) );
		sort( $gap );
		return $gap;
	}

	public function test_every_look_leaves_the_same_classes_unstyled(): void {
		$gaps = array();
		foreach ( self::LOOKS as $look ) {
			$gaps[ $look ] = $this->unstyled_for( $look );
		}
		$reference = $gaps[ self::LOOKS[0] ];
		foreach ( self::LOOKS as $look ) {
			$this->assertSame(
				$reference,
				$gaps[ $look ],
				sprintf(
					'%s does not use the same building blocks as %s. Only in %s: %s',
					$look,
					self::LOOKS[0],
					$look,
					implode( ', ', array_diff( $gaps[ $look ], $reference ) ) ?: '(none)'
				)
			);
		}
	}

	public function test_the_shared_unstyled_set_has_not_grown(): void {
		$expected = self::KNOWN_UNSTYLED;
		sort( $expected );
		$this->assertSame(
			$expected,
			$this->unstyled_for( 'court-side' ),
			'The set of unstyled classes changed. If a new component is genuinely '
				. 'style-free, add it to KNOWN_UNSTYLED with a reason; otherwise style it.'
		);
	}

	/**
	 * Every `.php` file under includes/ and templates/, excluding includes/render/
	 * itself (that's the set emitted_classes() already scrapes).
	 *
	 * @return array<int,string> absolute paths
	 */
	private function files_outside_render(): array {
		$out = array();
		foreach ( array( 'includes', 'templates' ) as $dir ) {
			$base = $this->root() . '/' . $dir;
			if ( ! is_dir( $base ) ) {
				continue;
			}
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				$path = str_replace( '\\', '/', $file->getPathname() );
				if ( 'php' !== $file->getExtension() ) {
					continue;
				}
				if ( str_contains( $path, '/includes/render/' ) ) {
					continue; // Already scraped by emitted_classes().
				}
				$out[] = $path;
			}
		}
		sort( $out );
		return $out;
	}

	/**
	 * The glob in emitted_classes() only scrapes includes/render/. That is only a
	 * trustworthy stand-in for "all rendered markup" if no ch-* class attribute
	 * lives anywhere else. This asserts that boundary rather than assuming it: if
	 * markup ever moves (or is added) outside includes/render/, this fails and
	 * names the offending files instead of the coverage check silently narrowing.
	 *
	 * A failure here is not something to weaken — it means real markup is now
	 * invisible to test_every_look_leaves_the_same_classes_unstyled() and
	 * test_the_shared_unstyled_set_has_not_grown().
	 */
	public function test_no_classes_render_outside_scraped_files(): void {
		$offenders = array();
		foreach ( $this->files_outside_render() as $path ) {
			$php = (string) file_get_contents( $path );
			preg_match_all( '/class="([^"]*)"/', $php, $attrs );
			foreach ( $attrs[1] as $attr ) {
				if ( preg_match( '/\bch-[a-z0-9_-]+/', $attr ) ) {
					$offenders[] = $path;
					break;
				}
			}
		}
		$this->assertSame(
			array(),
			$offenders,
			'ch-* classes found in class="…" attributes outside includes/render/: '
				. implode( ', ', $offenders )
				. '. The static coverage check only scrapes includes/render/, so markup '
				. 'here is invisible to it — move it under includes/render/ or widen the scrape.'
		);
	}
}
