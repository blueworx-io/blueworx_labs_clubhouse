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

	/**
	 * Join a token map into a raw CSS declaration string for embedding inside a
	 * <style> block: "--k:v;--k2:v2;". Unlike inline_tokens() this does NOT run
	 * values through esc() — a <style> element is raw text, so HTML character
	 * references (e.g. &#039;) are not decoded by the browser and would corrupt
	 * values such as font-family names. Values here are fully server-controlled
	 * (hardcoded look tokens plus a sanitize_hex_color-validated accent), so raw
	 * emission is safe; as defense-in-depth, strip characters that could break
	 * out of the declaration block.
	 */
	private static function css_tokens( array $tokens ): string {
		$out = '';
		foreach ( $tokens as $name => $value ) {
			$safe_name  = str_replace( array( '<', '}' ), '', (string) $name );
			$safe_value = str_replace( array( '<', '}' ), '', (string) $value );
			$out       .= $safe_name . ':' . $safe_value . ';';
		}
		return $out;
	}

	/**
	 * The Menu tab, if this screen is showing one. It carries its own form —
	 * it saves through the content plumbing, not the setup one — so it is
	 * rendered as a sibling of the setup form rather than nested inside it.
	 *
	 * @param array<string,mixed> $menu
	 */
	private static function menu_panel( array $menu ): string {
		return Blueworx_Clubhouse_Menu_Panel::render( array_merge(
			$menu,
			array( 'panel_class' => 'clubhouse-panel', 'panel_attr' => 'data-panel="menu"' )
		) );
	}

	/**
	 * A Content Editor can edit the menu but nothing else on this screen, so
	 * they get the Menu tab on its own: the screen they are sent to is the same
	 * screen, with only the part they are allowed to touch on it.
	 *
	 * @param array<string,mixed> $model
	 */
	private static function menu_only( array $model ): string {
		$out  = '<div class="wrap clubhouse-wrap">';
		$out .= '<div class="clubhouse-setup">';
		$out .= self::header( $model['progress'], (string) ( $model['role_tags'] ?? '' ) );
		$out .= self::notices( $model['notices'] );
		$out .= '<div class="clubhouse-tabs" role="tablist">'
			. '<button type="button" class="clubhouse-tab is-active" data-tab="menu" role="tab" aria-selected="true">Menu</button></div>';
		$out .= self::menu_panel( (array) $model['menu'] );
		return $out . '</div></div>';
	}

	/** @param array<string,mixed> $model */
	public static function render( array $model ): string {
		$menu = is_array( $model['menu'] ?? null ) ? $model['menu'] : null;
		if ( false === ( $model['can_setup'] ?? true ) ) {
			return null !== $menu ? self::menu_only( $model ) : '';
		}
		$active_tokens = $model['look_tokens'][ $model['active_slug'] ] ?? array();

		$out  = '<div class="wrap clubhouse-wrap">';
		$out .= '<style>' . $model['font_face_css']
			. '.clubhouse-setup{' . self::css_tokens( $active_tokens ) . '}</style>';
		$out .= '<div class="clubhouse-setup">';
		$out .= self::header( $model['progress'], (string) ( $model['role_tags'] ?? '' ) );
		$out .= self::notices( $model['notices'] );
		$out .= '<form method="post" action="' . self::esc( (string) $model['action_url'] ) . '" class="clubhouse-form">';
		$out .= $model['nonce_field'];

		// Tab nav. Demo mode is an admin-only control (manage_options) — shown only
		// to admins, and never counted in setup progress.
		$can_demo = (bool) ( $model['can_demo'] ?? false );
		$out .= '<div class="clubhouse-tabs" role="tablist">';
		$out .= '<button type="button" class="clubhouse-tab is-active" data-tab="look" role="tab" aria-selected="true">Base Look &amp; Branding</button>';
		$out .= '<button type="button" class="clubhouse-tab" data-tab="visibility" role="tab" aria-selected="false">Visibility</button>';
		$out .= '<button type="button" class="clubhouse-tab" data-tab="members" role="tab" aria-selected="false">Members</button>';
		if ( null !== $menu ) {
			$out .= '<button type="button" class="clubhouse-tab" data-tab="menu" role="tab" aria-selected="false">Menu</button>';
		}
		if ( $can_demo ) {
			$out .= '<button type="button" class="clubhouse-tab" data-tab="demo" role="tab" aria-selected="false">Demo Mode</button>';
		}
		$out .= '</div>';

		$out .= '<section class="clubhouse-panel is-active" data-panel="look" role="tabpanel">'
			. self::look_area( $model['looks'], $model['look_tokens'] )
			. self::branding_area( $model['branding'] ) . '</section>';
		$out .= '<section class="clubhouse-panel" data-panel="visibility" role="tabpanel">'
			. self::visibility_area( $model['inventory'], $model['visibility'] ) . '</section>';
		$out .= '<section class="clubhouse-panel" data-panel="members" role="tabpanel">'
			. self::members_area( $model['members'] ?? array() )
			// The club's own questions about a member sit with the other member
			// settings, not in a tab of their own: an owner looking for what the
			// member area does looks here.
			. self::profile_fields_area( (array) ( $model['profile_fields'] ?? array() ) )
			// Beside it rather than in a tab of its own: the email a club sends is
			// almost entirely password resets, which is a member journey.
			. self::emails_area( $model['mail'] ?? array() ) . '</section>';
		if ( $can_demo ) {
			$out .= '<section class="clubhouse-panel" data-panel="demo" role="tabpanel">'
				. self::demo_area( (bool) ( $model['demo_active'] ?? false ) ) . '</section>';
		}

		$out .= self::save_bar( $model['progress'] );
		$out .= '</form>';

		if ( null !== $menu ) {
			$out .= self::menu_panel( $menu );
		}

		// JSON island for the live re-skin.
		$json = json_encode( $model['look_tokens'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		$out .= '<script type="application/json" id="clubhouse-look-tokens">' . $json . '</script>';

		// A second island for the colour pickers: the preset swatches Iris offers,
		// and the active look's background and ink so the live contrast check can
		// judge a colour against the surfaces it will actually sit on. Same
		// mechanism as the token island above rather than wp_localize_script, so
		// this class stays WordPress-free and the whole screen remains one string.
		$picker = json_encode(
			array(
				'palette' => $model['color_palette'] ?? array(),
				'shell'   => array(
					'bg'  => $active_tokens['--color-bg'] ?? '#ffffff',
					'ink' => $active_tokens['--color-ink'] ?? '#000000',
				),
			),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		$out .= '<script type="application/json" id="clubhouse-color-picker">' . $picker . '</script>';

		$out .= '</div></div>';
		return $out;
	}

	/**
	 * $role_tags is prebuilt markup from Access_Screen, empty for anyone but an
	 * administrator. Passed in rather than decided here so this class stays free
	 * of WordPress — the controller owns the "is this an admin" question.
	 *
	 * @param array{completed:int,total:int} $p
	 */
	private static function header( array $p, string $role_tags = '' ): string {
		$pct = 0 === $p['total'] ? 0 : (int) round( 100 * $p['completed'] / $p['total'] );
		return '<header class="clubhouse-head">'
			. '<div class="clubhouse-head__titles"><p class="clubhouse-eyebrow">Clubhouse · Site setup</p>'
			. '<h1 class="clubhouse-head__h1">Clubhouse Setup</h1>' . $role_tags . '</div>'
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
		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Foundation</p><h2 class="clubhouse-step__h">Base Look</h2>';
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
		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Branding</p><h2 class="clubhouse-step__h">Make it yours</h2>';
		$out .= '<div class="clubhouse-fields">';
		$out .= self::color_field(
			'clubhouse_accent',
			'Primary colour',
			(string) $b['accent'],
			(string) ( $b['accent_default'] ?? '' ),
			'Used for buttons, links and highlights. Must be legible on the chosen look.'
		);
		$out .= self::color_field(
			'clubhouse_secondary',
			'Secondary colour',
			(string) ( $b['secondary'] ?? '' ),
			(string) ( $b['secondary_default'] ?? '' ),
			'Used for secondary buttons, section markers and hover states. Clear it to go back to a shade derived from your primary.',
			(string) ( $b['secondary_effective'] ?? '' )
		);
		$out .= self::text_field( 'clubhouse_club_name', 'Club name', (string) $b['club_name'] );
		$out .= self::media_field( 'clubhouse_logo', 'Logo', (string) $b['logo'], (string) $b['logo_preview'], 'No logo set — SVG or PNG, up to 2 MB' );
		$out .= self::media_field( 'clubhouse_favicon', 'Favicon', (string) $b['favicon'], (string) $b['favicon_preview'], 'No favicon set — square PNG, ICO or SVG' );
		$out .= self::text_field( 'clubhouse_facebook', 'Facebook URL', (string) $b['facebook'], 'url' );
		$out .= self::text_field( 'clubhouse_instagram', 'Instagram URL', (string) $b['instagram'], 'url' );
		$out .= self::text_field( 'clubhouse_linkedin', 'LinkedIn URL', (string) $b['linkedin'], 'url' );
		$out .= self::text_field( 'clubhouse_x', 'X (Twitter) URL', (string) $b['x'], 'url' );
		$out .= '</div></div>';
		return $out;
	}

	/**
	 * A colour setting. The markup is a plain text input carrying the current hex
	 * — which is what posts, and what somebody who knows their brand hex can type
	 * straight in — plus the data WordPress's own colour picker (Iris, shipped as
	 * wp-color-picker) needs to upgrade it in place: a default to reset to, and a
	 * palette of on-brand presets.
	 *
	 * Progressive enhancement, deliberately: with JavaScript off or the picker
	 * unavailable, this is still a working, labelled, editable field rather than a
	 * dead swatch. The static swatch beside it is the no-JS preview and is
	 * replaced by the picker's own button when Iris initialises.
	 *
	 * $effective is shown only when it differs from $value — i.e. when the field
	 * is empty and a colour is being derived — so an owner can see what "unset"
	 * currently resolves to instead of guessing.
	 */
	private static function color_field(
		string $name,
		string $label,
		string $value,
		string $default,
		string $help,
		string $effective = ''
	): string {
		$swatch = '' !== $value ? $value : $effective;
		$out    = '<div class="clubhouse-field clubhouse-field--color">'
			. '<label class="clubhouse-label" for="' . self::esc( $name ) . '">' . self::esc( $label ) . '</label>'
			. '<div class="clubhouse-accent">'
			. '<span class="clubhouse-accent__swatch" data-color-swatch="' . self::esc( $name ) . '" style="background:' . self::esc( $swatch ) . '"></span>'
			. '<input type="text" id="' . self::esc( $name ) . '" name="' . self::esc( $name ) . '"'
			. ' value="' . self::esc( $value ) . '" class="clubhouse-input clubhouse-color"'
			. ' data-default-color="' . self::esc( $default ) . '"'
			. ' data-token="' . self::esc( self::token_for( $name ) ) . '"'
			. ' autocomplete="off" spellcheck="false"></div>';

		if ( '' === $value && '' !== $effective ) {
			$out .= '<p class="clubhouse-help">Not set — currently using <code>' . self::esc( $effective )
				. '</code>, derived from your primary colour.</p>';
		}

		// Filled by the client-side contrast check; empty and hidden until there is
		// something to say. role="status" so a screen reader is told when a chosen
		// pair stops clearing AA, rather than only sighted users seeing it.
		$out .= '<p class="clubhouse-contrast" data-contrast-for="' . self::esc( $name ) . '" role="status" hidden></p>';
		$out .= '<p class="clubhouse-help">' . self::esc( $help ) . '</p></div>';
		return $out;
	}

	/**
	 * The CSS custom property a colour field drives, so the live preview can
	 * repaint the panel as the picker moves. Mapped here rather than in the
	 * JavaScript: the field names and the token names are both this class's
	 * business, and a mismatch would silently preview nothing.
	 */
	private static function token_for( string $name ): string {
		return 'clubhouse_secondary' === $name ? '--color-secondary' : '--color-accent';
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
		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Visibility</p><h2 class="clubhouse-step__h">What visitors see</h2>';
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
			$total    = count( $page['sections'] );
			$cls      = $first ? ' is-active' : '';
			$selected = $first ? 'true' : 'false';
			// A page with no sections at all (the member area) has nothing to count —
			// "0/0" reads as broken, not as "everything is hidden".
			$count    = $total > 0 ? ' <span class="clubhouse-vistab__count">' . $shown . '/' . $total . '</span>' : '';
			$out     .= '<button type="button" class="clubhouse-vistab' . $cls . '" data-vistab="' . self::esc( $page['page'] ) . '" role="tab" aria-selected="' . $selected . '">'
				. self::esc( $page['label'] ) . $count . '</button>';
			$first = false;
		}
		$out .= '</div>';

		// Sub-panels.
		$first = true;
		foreach ( $inventory as $page ) {
			$page_on   = ( $visibility['pages'][ $page['page'] ] ?? true );
			$has_sections = array() !== $page['sections'];
			$cls       = $first ? ' is-active' : '';
			$out .= '<div class="clubhouse-vispanel' . $cls . '" data-vispanel="' . self::esc( $page['page'] ) . '" role="tabpanel">';
			// "… sections" only makes sense once there is a grid of them below it —
			// a page with none (the member area) just needs the page-level toggle.
			$title = $has_sections ? self::esc( $page['label'] ) . ' sections' : self::esc( $page['label'] );
			$out  .= '<div class="clubhouse-vispanel__head"><span class="clubhouse-vispanel__title">' . $title . '</span>';
			$out  .= self::toggle( 'clubhouse_page[' . $page['page'] . ']', 'Page shown', $page_on ) . '</div>';
			if ( $has_sections ) {
				$out .= '<div class="clubhouse-toggle-grid">';
				foreach ( $page['sections'] as $section ) {
					$skey = $page['page'] . '.' . $section['key'];
					$on   = ( $visibility['sections'][ $skey ] ?? true );
					$out .= self::toggle( 'clubhouse_section[' . $skey . ']', $section['label'], $on );
				}
				$out .= '</div>';
			}
			$out .= '</div>';
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

	/**
	 * Where members land after signing in and out.
	 *
	 * Both fields are optional and both mean "the front page" when empty, which is
	 * the right answer for most clubs — so this is not a setup step and is not
	 * counted in progress. An off-site address is refused when the redirect
	 * happens rather than when it is typed, so the help text says so plainly.
	 *
	 * @param array<string,string> $m
	 */
	private static function members_area( array $m ): string {
		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Members</p>'
			. '<h2 class="clubhouse-step__h">After signing in</h2>';
		// The dashboard default is worth saying out loud: a blank field that
		// quietly does two different things depending on whether a shop is
		// connected is the kind of behaviour an owner should read, not discover.
		$signed_in = '' !== (string) ( $m['dashboard_url'] ?? '' )
			? 'Leave "after signing in" blank and members go to their account dashboard, where they manage what they have paid for. '
			: 'Leave "after signing in" blank to send them to your front page. ';
		$out .= '<p class="clubhouse-step__lede">Where a member goes once they have signed in, and once they have signed out. '
			. self::esc( $signed_in )
			. 'Leave "after signing out" blank to send them to your front page. If a member was heading somewhere else when they were '
			. 'asked to sign in, they are returned there instead. Addresses on other websites are ignored.</p>';
		$out .= '<div class="clubhouse-fields">';
		$out .= self::text_field( 'clubhouse_post_login', 'After signing in', (string) ( $m['post_login'] ?? '' ) );
		$out .= self::text_field( 'clubhouse_post_logout', 'After signing out', (string) ( $m['post_logout'] ?? '' ) );
		$out .= '</div>';
		$out .= '<p class="clubhouse-help">For example <code>/membership/</code> for a page on this site.</p>';
		return $out . '</div>';
	}

	/**
	 * The custom member fields a club has invented, and the row for the next one.
	 *
	 * Server-rendered add and remove, like the Club Pages content loop: submit
	 * buttons rather than JavaScript, so the builder works on first load, with
	 * JS off, and never loses a half-typed row to a script that failed to load.
	 *
	 * The key rides along in a hidden input on every existing row. It is what
	 * lets an owner rewrite a label without detaching every member's answer —
	 * so it must survive the round trip, and it is never editable.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 */
	public static function profile_fields_area( array $fields ): string {
		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Members</p>'
			. '<h2 class="clubhouse-step__h">What you keep about a member</h2>';
		$out .= '<p class="clubhouse-step__lede">Add anything your club needs to know — shirt size, emergency contact, squad number. '
			. 'Members see and fill in their own on their Profile page. You can also keep things only the club sees.</p>';

		$out .= '<div class="clubhouse-loop">';
		foreach ( $fields as $idx => $field ) {
			$out .= self::profile_field_row( (array) $field, (int) $idx );
		}

		if ( count( $fields ) < Blueworx_Clubhouse_Profile_Fields::MAX_FIELDS ) {
			// Always one blank row past the end, so adding a field is typing
			// rather than clicking and then typing.
			$out .= self::profile_field_row( array(), count( $fields ) );
			$out .= '<button type="submit" name="clubhouse_profile_field_add" value="1" class="clubhouse-btn clubhouse-btn--sm">Add another field</button>';
		} else {
			$out .= '<p class="clubhouse-help">That is ' . (int) Blueworx_Clubhouse_Profile_Fields::MAX_FIELDS
				. ' fields — as many as one page can sensibly ask anybody for. Remove one to add another.</p>';
		}
		$out .= '</div>';
		return $out . '</div>';
	}

	/**
	 * One field's row. An empty $field is the blank row at the end, which
	 * carries no key and offers nothing to remove.
	 *
	 * @param array<string,mixed> $field
	 */
	private static function profile_field_row( array $field, int $idx ): string {
		$name  = 'clubhouse_profile_field[' . $idx . ']';
		$key   = (string) ( $field['key'] ?? '' );
		$type  = (string) ( $field['type'] ?? Blueworx_Clubhouse_Profile_Fields::DEFAULT_TYPE );
		$who   = (string) ( $field['who'] ?? Blueworx_Clubhouse_Profile_Fields::DEFAULT_WHO );
		$blank = '' === $key;

		$out = '<div class="clubhouse-loop__item">';
		if ( ! $blank ) {
			$out .= '<input type="hidden" name="' . self::esc( $name ) . '[key]" value="' . self::esc( $key ) . '">';
		}
		$out .= '<div class="clubhouse-fields">';
		$out .= self::text_field( $name . '[label]', $blank ? 'Add a field' : 'What it is called', (string) ( $field['label'] ?? '' ) );
		$out .= self::select_field( $name . '[type]', 'Kind of answer', Blueworx_Clubhouse_Profile_Fields::TYPES, $type );
		$out .= self::select_field( $name . '[who]', 'Who fills it in', Blueworx_Clubhouse_Profile_Fields::WHO, $who );
		$out .= '</div>';

		$choices_name = $name . '[choices]';
		$out         .= '<div class="clubhouse-field"><label class="clubhouse-label" for="' . self::esc( $choices_name ) . '">Choices, one per line</label>'
			. '<textarea id="' . self::esc( $choices_name ) . '" name="' . self::esc( $choices_name ) . '" rows="3" class="clubhouse-input">'
			. self::esc( implode( "\n", array_map( 'strval', (array) ( $field['choices'] ?? array() ) ) ) )
			. '</textarea>'
			. '<p class="clubhouse-help">Only used by the two dropdown kinds. Ignored otherwise.</p></div>';

		$out .= '<div class="clubhouse-fields">';
		$out .= self::text_field( $name . '[help]', 'A note under the box (optional)', (string) ( $field['help'] ?? '' ) );
		$out .= '</div>';
		$out .= self::toggle( $name . '[required]', 'A member must fill this in before they can save', ! empty( $field['required'] ) );

		if ( ! $blank ) {
			// Two ways out, because they are not the same thing. Remove takes the
			// question off every screen and keeps the answers, so a mistake costs
			// nothing. Clearing them is separate, confirmed, and final.
			$out .= '<div class="clubhouse-loop__actions">'
				. '<button type="submit" name="clubhouse_profile_field_remove" value="' . (int) $idx . '" class="clubhouse-btn-link">Remove this field</button>'
				. '<button type="submit" name="clubhouse_profile_field_forget" value="' . self::esc( $key ) . '" class="clubhouse-btn-link clubhouse-btn-link--danger" '
				. 'onclick="return confirm(&#039;This clears every member&#039;s answer to this field, for good. Are you sure?&#039;)">Remove and clear every answer</button>'
				. '</div>';
		}
		return $out . '</div>';
	}

	/**
	 * A labelled dropdown.
	 *
	 * @param array<string,string> $options value => label
	 */
	private static function select_field( string $name, string $label, array $options, string $value ): string {
		$out = '<div class="clubhouse-field"><label class="clubhouse-label" for="' . self::esc( $name ) . '">' . self::esc( $label ) . '</label>'
			. '<select id="' . self::esc( $name ) . '" name="' . self::esc( $name ) . '" class="clubhouse-input">';
		foreach ( $options as $opt_value => $opt_label ) {
			$selected = ( (string) $opt_value === $value ) ? ' selected' : '';
			$out     .= '<option value="' . self::esc( (string) $opt_value ) . '"' . $selected . '>' . self::esc( $opt_label ) . '</option>';
		}
		return $out . '</select></div>';
	}

	/**
	 * Who the site's email comes from.
	 *
	 * Both fields empty is the normal case, so the step leads with what the club
	 * already gets rather than with two blanks. The address is deliberately shown
	 * rather than described: a club needs to know members will see noreply@ before
	 * they can decide they would rather have something else.
	 *
	 * @param array<string,mixed> $m
	 */
	private static function emails_area( array $m ): string {
		$name    = (string) ( $m['name_default'] ?? '' );
		$address = (string) ( $m['address_default'] ?? '' );

		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Emails</p>'
			. '<h2 class="clubhouse-step__h">Who your email comes from</h2>';
		$out .= '<p class="clubhouse-step__lede">Password resets and everything else this site sends. '
			. ( '' !== $address
				? 'Leave both empty and members see ' . self::esc( '' !== $name ? $name . ' <' . $address . '>' : $address ) . '. '
				: 'Leave both empty and members see your club\'s name. ' )
			. 'Fill them in only if your club has a real mailbox you would rather members could reply to.</p>';
		$out .= '<div class="clubhouse-fields">';
		$out .= self::text_field( 'clubhouse_mail_from_name', 'Sender name', (string) ( $m['from_name'] ?? '' ) );
		$out .= self::text_field( 'clubhouse_mail_from_address', 'Sender address', (string) ( $m['from_address'] ?? '' ), 'email' );
		$out .= '</div>';
		$out .= '<p class="clubhouse-help">Nobody can reply to the ' . self::esc( '' !== $address ? $address : 'noreply' ) . ' address — that is what it is for.</p>';
		return $out . '</div>';
	}

	/** Admin-only demo-mode panel. Not a setup step and not counted in progress. */
	private static function demo_area( bool $active ): string {
		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Admins only</p><h2 class="clubhouse-step__h">Demo mode</h2>';
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
