<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure HTML builder for the Clubhouse Setup admin page. Emits the form; the
 * controller supplies the model and the WP-produced nonce/action strings, and
 * processes the POST. No WordPress calls, no persistence here.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Setup_Screen {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/** @param array<string,mixed> $model */
	public static function render( array $model ): string {
		$out  = '<div class="wrap clubhouse-setup">';
		$out .= '<h1>Clubhouse Setup</h1>';
		$out .= self::notices( $model['notices'] );
		$out .= self::progress( $model['progress'] );
		$out .= '<form method="post" action="' . self::esc( (string) $model['action_url'] ) . '">';
		$out .= $model['nonce_field'];
		$out .= self::look_area( $model['looks'] );
		$out .= self::branding_area( $model['branding'] );
		$out .= self::visibility_area( $model['inventory'], $model['visibility'] );
		$out .= self::demo_area( (bool) ( $model['demo_active'] ?? false ) );
		$out .= '<p class="submit"><button type="submit" class="button button-primary">Save changes</button></p>';
		$out .= '</form></div>';
		return $out;
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

	/** @param array{items:array<string,bool>,completed:int,total:int} $p */
	private static function progress( array $p ): string {
		$pct  = 0 === $p['total'] ? 0 : (int) round( 100 * $p['completed'] / $p['total'] );
		$out  = '<div class="clubhouse-progress">';
		$out .= '<p class="clubhouse-progress__label">Setup: ' . (int) $p['completed'] . ' of ' . (int) $p['total'] . ' complete</p>';
		$out .= '<div class="clubhouse-progress__track"><div class="clubhouse-progress__bar" style="width:' . $pct . '%"></div></div>';
		$out .= '</div>';
		return $out;
	}

	/** @param array<int,array{slug:string,name:string,description:string,active:bool}> $looks */
	private static function look_area( array $looks ): string {
		$out = '<h2>Base Look</h2><div class="clubhouse-looks" role="radiogroup" aria-label="Base Look">';
		foreach ( $looks as $look ) {
			$checked = $look['active'] ? ' checked' : '';
			$out .= '<label class="clubhouse-look-card">';
			$out .= '<input type="radio" name="clubhouse_look" value="' . self::esc( $look['slug'] ) . '"' . $checked . '>';
			$out .= '<span class="clubhouse-look-card__name">' . self::esc( $look['name'] ) . '</span>';
			$out .= '<span class="clubhouse-look-card__desc">' . self::esc( $look['description'] ) . '</span>';
			$out .= '</label>';
		}
		$out .= '</div>';
		return $out;
	}

	/** @param array<string,string> $b */
	private static function branding_area( array $b ): string {
		$out  = '<h2>Branding</h2><table class="form-table" role="presentation"><tbody>';
		$out .= self::text_row( 'clubhouse_accent', 'Accent colour', (string) $b['accent'], 'text', 'A hex colour, e.g. #c6f24e. Must be legible on the chosen look.' );
		$out .= self::text_row( 'clubhouse_club_name', 'Club name', (string) $b['club_name'] );
		$preview = '' !== $b['logo_preview']
			? '<img class="clubhouse-logo-preview" src="' . self::esc( (string) $b['logo_preview'] ) . '" alt="Current logo" style="max-height:60px">'
			: '<span class="clubhouse-logo-preview clubhouse-logo-preview--empty">No logo set</span>';
		$out .= '<tr><th scope="row"><label>Logo</label></th><td>';
		$out .= '<input type="hidden" name="clubhouse_logo" id="clubhouse_logo" value="' . self::esc( (string) $b['logo'] ) . '">';
		$out .= $preview;
		$out .= ' <button type="button" class="button" id="clubhouse-logo-pick">Choose logo</button>';
		$out .= ' <button type="button" class="button-link" id="clubhouse-logo-clear">Remove</button>';
		$out .= '</td></tr>';
		$out .= self::text_row( 'clubhouse_facebook', 'Facebook URL', (string) $b['facebook'], 'url' );
		$out .= self::text_row( 'clubhouse_instagram', 'Instagram URL', (string) $b['instagram'], 'url' );
		$out .= '</tbody></table>';
		return $out;
	}

	private static function text_row( string $name, string $label, string $value, string $type = 'text', string $help = '' ): string {
		$out  = '<tr><th scope="row"><label for="' . self::esc( $name ) . '">' . self::esc( $label ) . '</label></th><td>';
		$out .= '<input type="' . self::esc( $type ) . '" class="regular-text" id="' . self::esc( $name ) . '" name="' . self::esc( $name ) . '" value="' . self::esc( $value ) . '">';
		if ( '' !== $help ) {
			$out .= '<p class="description">' . self::esc( $help ) . '</p>';
		}
		$out .= '</td></tr>';
		return $out;
	}

	/**
	 * @param array<int,array{page:string,label:string,sections:array<int,array{key:string,label:string}>}> $inventory
	 * @param array{pages:array<string,bool>,sections:array<string,bool>} $visibility
	 */
	private static function visibility_area( array $inventory, array $visibility ): string {
		$out = '<h2>Visibility</h2><p class="description">Untick to hide a page or a section. Pages and sections are shown by default.</p>';
		foreach ( $inventory as $page ) {
			$page_checked = ( $visibility['pages'][ $page['page'] ] ?? true ) ? ' checked' : '';
			$out .= '<fieldset class="clubhouse-vis-page"><legend>';
			$out .= '<label><input type="checkbox" name="clubhouse_page[' . self::esc( $page['page'] ) . ']" value="1"' . $page_checked . '> ' . self::esc( $page['label'] ) . '</label>';
			$out .= '</legend><div class="clubhouse-vis-sections">';
			foreach ( $page['sections'] as $section ) {
				$skey            = $page['page'] . '.' . $section['key'];
				$section_checked = ( $visibility['sections'][ $skey ] ?? true ) ? ' checked' : '';
				$out .= '<label class="clubhouse-vis-section"><input type="checkbox" name="clubhouse_section[' . self::esc( $skey ) . ']" value="1"' . $section_checked . '> ' . self::esc( $section['label'] ) . '</label>';
			}
			$out .= '</div></fieldset>';
		}
		return $out;
	}

	private static function demo_area( bool $active ): string {
		$checked = $active ? ' checked' : '';
		$out  = '<h2>Demo mode</h2>';
		$out .= '<p class="description">When on, every visitor sees a floating switcher to preview the Base Looks, and the site renders in a demo look. Your saved look is not changed. Only administrators can turn this on or off.</p>';
		$out .= '<label><input type="checkbox" name="clubhouse_demo_active" value="1"' . $checked . '> Enable demo mode for all visitors</label>';
		return $out;
	}
}
