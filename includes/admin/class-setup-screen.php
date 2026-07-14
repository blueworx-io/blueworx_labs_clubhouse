<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure HTML builder for the Clubhouse Setup admin page: a bespoke, tabbed,
 * look-inheriting form. The controller supplies the model (incl. each look's
 * composed design tokens and combined @font-face CSS) and the WP nonce/action;
 * this class makes no WordPress calls and no persistence.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Setup_Screen {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/** Join a token map into an inline custom-property string: "--k:v;--k2:v2;". */
	private static function inline_tokens( array $tokens ): string {
		$out = '';
		foreach ( $tokens as $name => $value ) {
			$out .= self::esc( (string) $name ) . ':' . self::esc( (string) $value ) . ';';
		}
		return $out;
	}

	/** @param array<string,mixed> $model */
	public static function render( array $model ): string {
		$active_tokens = $model['look_tokens'][ $model['active_slug'] ] ?? array();

		$out  = '<div class="wrap">';
		$out .= '<style>' . $model['font_face_css']
			. '.clubhouse-setup{' . self::inline_tokens( $active_tokens ) . '}</style>';
		$out .= '<div class="clubhouse-setup">';
		$out .= self::header( $model['progress'] );
		$out .= self::notices( $model['notices'] );
		$out .= '<form method="post" action="' . self::esc( (string) $model['action_url'] ) . '" class="clubhouse-form">';
		$out .= $model['nonce_field'];

		// Tab nav.
		$out .= '<div class="clubhouse-tabs" role="tablist">';
		$out .= '<button type="button" class="clubhouse-tab is-active" data-tab="look">Base Look &amp; Branding</button>';
		$out .= '<button type="button" class="clubhouse-tab" data-tab="visibility">Visibility</button>';
		$out .= '<button type="button" class="clubhouse-tab" data-tab="demo">Demo Mode</button>';
		$out .= '</div>';

		$out .= '<section class="clubhouse-panel is-active" data-panel="look">'
			. self::look_area( $model['looks'], $model['look_tokens'] )
			. self::branding_area( $model['branding'] ) . '</section>';
		$out .= '<section class="clubhouse-panel" data-panel="visibility">'
			. self::visibility_area( $model['inventory'], $model['visibility'] ) . '</section>';
		$out .= '<section class="clubhouse-panel" data-panel="demo">'
			. self::demo_area( (bool) ( $model['demo_active'] ?? false ) ) . '</section>';

		$out .= self::save_bar( $model['progress'] );
		$out .= '</form>';

		// JSON island for the live re-skin.
		$json = json_encode( $model['look_tokens'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		$out .= '<script type="application/json" id="clubhouse-look-tokens">' . $json . '</script>';

		$out .= '</div></div>';
		return $out;
	}

	/** @param array{completed:int,total:int} $p */
	private static function header( array $p ): string {
		$pct = 0 === $p['total'] ? 0 : (int) round( 100 * $p['completed'] / $p['total'] );
		return '<header class="clubhouse-head">'
			. '<div class="clubhouse-head__titles"><p class="clubhouse-eyebrow">Clubhouse · Site setup</p>'
			. '<h1 class="clubhouse-head__h1">Clubhouse Setup</h1></div>'
			. '<div class="clubhouse-head__progress"><p class="clubhouse-pct">' . $pct . '%</p>'
			. '<p class="clubhouse-progress__label">' . (int) $p['completed'] . ' of ' . (int) $p['total'] . ' complete</p>'
			. '<div class="clubhouse-progress__track"><div class="clubhouse-progress__bar" style="width:' . $pct . '%"></div></div>'
			. '</div></header>';
	}

	/** @param array<int,array{type:string,text:string}> $notices */
	private static function notices( array $notices ): string {
		$out = '';
		foreach ( $notices as $n ) {
			$type = in_array( $n['type'], array( 'error', 'warning', 'success' ), true ) ? $n['type'] : 'info';
			$out .= '<div class="notice notice-' . self::esc( $type ) . '"><p>' . self::esc( $n['text'] ) . '</p></div>';
		}
		return $out;
	}

	/**
	 * @param array<int,array{slug:string,name:string,description:string,active:bool}> $looks
	 * @param array<string,array<string,string>> $look_tokens
	 */
	private static function look_area( array $looks, array $look_tokens ): string {
		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Step 1 · Foundation</p><h2 class="clubhouse-step__h">Base Look</h2>';
		$out .= '<p class="clubhouse-step__lede">Pick the visual foundation for your club site. Everything else adapts to it.</p>';
		$out .= '<div class="clubhouse-looks" role="radiogroup" aria-label="Base Look">';
		foreach ( $looks as $look ) {
			$checked = $look['active'] ? ' checked' : '';
			$style   = self::inline_tokens( $look_tokens[ $look['slug'] ] ?? array() );
			$out .= '<label class="clubhouse-look-card">';
			$out .= '<span class="clubhouse-look-card__preview" style="' . $style . '">'
				. '<span class="clubhouse-look-card__bar"></span><span class="clubhouse-look-card__accent"></span>'
				. '<span class="clubhouse-look-card__line"></span><span class="clubhouse-look-card__line"></span></span>';
			$out .= '<input type="radio" name="clubhouse_look" value="' . self::esc( $look['slug'] ) . '"' . $checked . '>';
			$out .= '<span class="clubhouse-look-card__name">' . self::esc( $look['name'] ) . '</span>';
			$out .= '<span class="clubhouse-look-card__desc">' . self::esc( $look['description'] ) . '</span>';
			$out .= '</label>';
		}
		$out .= '</div></div>';
		return $out;
	}

	/** @param array<string,string> $b */
	private static function branding_area( array $b ): string {
		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Step 2 · Branding</p><h2 class="clubhouse-step__h">Make it yours</h2>';
		$out .= '<div class="clubhouse-fields">';
		$out .= '<div class="clubhouse-field"><label class="clubhouse-label" for="clubhouse_accent">Accent colour</label>'
			. '<div class="clubhouse-accent"><span class="clubhouse-accent__swatch" id="clubhouse-accent-swatch" style="background:' . self::esc( (string) $b['accent'] ) . '"></span>'
			. '<input type="text" id="clubhouse_accent" name="clubhouse_accent" value="' . self::esc( (string) $b['accent'] ) . '" class="clubhouse-input"></div>'
			. '<p class="clubhouse-help">Used for buttons, links and highlights. Must be legible on the chosen look.</p></div>';
		$out .= self::text_field( 'clubhouse_club_name', 'Club name', (string) $b['club_name'] );
		$out .= self::media_field( 'clubhouse_logo', 'Logo', (string) $b['logo'], (string) $b['logo_preview'], 'No logo set — SVG or PNG, up to 2 MB' );
		$out .= self::media_field( 'clubhouse_favicon', 'Favicon', (string) $b['favicon'], (string) $b['favicon_preview'], 'No favicon set — square PNG, ICO or SVG' );
		$out .= self::text_field( 'clubhouse_facebook', 'Facebook URL', (string) $b['facebook'], 'url' );
		$out .= self::text_field( 'clubhouse_instagram', 'Instagram URL', (string) $b['instagram'], 'url' );
		$out .= self::text_field( 'clubhouse_linkedin', 'LinkedIn URL', (string) $b['linkedin'], 'url' );
		$out .= '</div></div>';
		return $out;
	}

	private static function text_field( string $name, string $label, string $value, string $type = 'text' ): string {
		return '<div class="clubhouse-field"><label class="clubhouse-label" for="' . self::esc( $name ) . '">' . self::esc( $label ) . '</label>'
			. '<input type="' . self::esc( $type ) . '" id="' . self::esc( $name ) . '" name="' . self::esc( $name ) . '" value="' . self::esc( $value ) . '" class="clubhouse-input"></div>';
	}

	private static function media_field( string $name, string $label, string $value, string $preview, string $empty ): string {
		$prev = '' !== $preview
			? '<img class="clubhouse-media__img" src="' . self::esc( $preview ) . '" alt="Current ' . self::esc( strtolower( $label ) ) . '">'
			: '<span class="clubhouse-media__empty" aria-hidden="true"></span>';
		return '<div class="clubhouse-field"><span class="clubhouse-label">' . self::esc( $label ) . '</span>'
			. '<div class="clubhouse-media" data-media="' . self::esc( $name ) . '">'
			. '<input type="hidden" id="' . self::esc( $name ) . '" name="' . self::esc( $name ) . '" value="' . self::esc( $value ) . '">'
			. '<span class="clubhouse-media__preview">' . $prev . '</span>'
			. '<span class="clubhouse-media__meta"><span class="clubhouse-media__hint">' . self::esc( $empty ) . '</span>'
			. '<span class="clubhouse-media__actions"><button type="button" class="clubhouse-btn clubhouse-btn--sm" data-media-pick>Choose ' . self::esc( strtolower( $label ) ) . '</button>'
			. '<button type="button" class="clubhouse-btn-link" data-media-clear>Remove</button></span></span>'
			. '</div></div>';
	}

	/**
	 * @param array<int,array{page:string,label:string,sections:array<int,array{key:string,label:string}>}> $inventory
	 * @param array{pages:array<string,bool>,sections:array<string,bool>} $visibility
	 */
	private static function visibility_area( array $inventory, array $visibility ): string {
		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Step 3 · Visibility</p><h2 class="clubhouse-step__h">What visitors see</h2>';
		$out .= '<p class="clubhouse-step__lede">Everything is shown by default. Switch off any page or the sections within it.</p>';

		// Sub-tab nav — one per page, counts from live state.
		$out .= '<div class="clubhouse-vistabs" role="tablist">';
		$first = true;
		foreach ( $inventory as $page ) {
			$shown = 0;
			foreach ( $page['sections'] as $section ) {
				if ( $visibility['sections'][ $page['page'] . '.' . $section['key'] ] ?? true ) {
					$shown++;
				}
			}
			$total = count( $page['sections'] );
			$cls   = $first ? ' is-active' : '';
			$out  .= '<button type="button" class="clubhouse-vistab' . $cls . '" data-vistab="' . self::esc( $page['page'] ) . '">'
				. self::esc( $page['label'] ) . ' <span class="clubhouse-vistab__count">' . $shown . '/' . $total . '</span></button>';
			$first = false;
		}
		$out .= '</div>';

		// Sub-panels.
		$first = true;
		foreach ( $inventory as $page ) {
			$page_on = ( $visibility['pages'][ $page['page'] ] ?? true );
			$cls     = $first ? ' is-active' : '';
			$out .= '<div class="clubhouse-vispanel' . $cls . '" data-vispanel="' . self::esc( $page['page'] ) . '">';
			$out .= '<div class="clubhouse-vispanel__head"><span class="clubhouse-vispanel__title">' . self::esc( $page['label'] ) . ' sections</span>';
			$out .= self::toggle( 'clubhouse_page[' . $page['page'] . ']', 'Page shown', $page_on ) . '</div>';
			$out .= '<div class="clubhouse-toggle-grid">';
			foreach ( $page['sections'] as $section ) {
				$skey = $page['page'] . '.' . $section['key'];
				$on   = ( $visibility['sections'][ $skey ] ?? true );
				$out .= self::toggle( 'clubhouse_section[' . $skey . ']', $section['label'], $on );
			}
			$out .= '</div></div>';
			$first = false;
		}
		$out .= '</div>';
		return $out;
	}

	private static function toggle( string $name, string $label, bool $on ): string {
		$checked = $on ? ' checked' : '';
		return '<label class="clubhouse-toggle"><input type="checkbox" name="' . self::esc( $name ) . '" value="1"' . $checked . '>'
			. '<span class="clubhouse-toggle__track"><span class="clubhouse-toggle__thumb"></span></span>'
			. '<span class="clubhouse-toggle__label">' . self::esc( $label ) . '</span></label>';
	}

	private static function demo_area( bool $active ): string {
		$checked = $active ? ' checked' : '';
		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Step 4 · Demo mode</p><h2 class="clubhouse-step__h">Preview for everyone</h2>';
		$out .= '<p class="clubhouse-step__lede">When on, every visitor sees a floating switcher to preview the base looks, and the site renders in a demo look. Your saved look isn\'t changed — only administrators can turn this on or off.</p>';
		$out .= '<div class="clubhouse-demo-card">'
			. self::toggle( 'clubhouse_demo_active', 'Enable demo mode for all visitors', $active )
			. '</div></div>';
		return $out;
	}

	/** @param array{completed:int,total:int} $p */
	private static function save_bar( array $p ): string {
		$done = $p['completed'] >= $p['total'];
		$hint = $done
			? 'Everything set — save your changes.'
			: (int) $p['completed'] . ' of ' . (int) $p['total'] . ' sections done — save now and finish later.';
		return '<div class="clubhouse-bar"><p class="clubhouse-bar__hint">' . self::esc( $hint ) . '</p>'
			. '<button type="submit" name="clubhouse_setup_submit" value="1" class="clubhouse-btn clubhouse-btn--primary">Save changes</button></div>';
	}
}
