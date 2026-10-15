<?php
// includes/playtomic/class-playtomic-screen.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Clubhouse → Playtomic screen: connection and settings first, then the
 * review table that is the last step before anyone is exported.
 *
 * Pure HTML from a model. Works without JavaScript: the table is a form
 * posted to admin-post; select-all and the live count are progressive.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Screen {

	public const FIELD  = 'clubhouse_playtomic';
	public const IDS    = 'clubhouse_playtomic_ids';
	public const FILTER = 'playtomic_filter';
	public const FROM   = 'playtomic_from';
	public const TO     = 'playtomic_to';

	private static function e( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/** @param array<string,mixed> $model */
	public static function render( array $model ): string {
		$out = Blueworx_Clubhouse_Admin_Shell::open(
			'Clubhouse · Playtomic',
			'Playtomic',
			'Send your members to Playtomic Manager as a file it can import, and keep track of who has already gone.',
			(string) ( $model['role_tags'] ?? '' )
		);
		if ( is_array( $model['notice'] ?? null ) ) {
			$out .= self::notice( (string) $model['notice']['type'], (string) $model['notice']['text'] );
		}
		$out .= Blueworx_Clubhouse_Admin_Shell::card( 'Step 1', 'Connection and settings', 'Your Playtomic developer credentials, who counts as a member, and one row per venue.', self::settings_form( $model ) );
		$out .= Blueworx_Clubhouse_Admin_Shell::card( 'Step 2', 'Review and export', 'Tick who goes. Nobody is exported without a tick, and everyone exported is marked with the date.', self::members_form( $model ) );
		return $out . Blueworx_Clubhouse_Admin_Shell::close();
	}

	private static function notice( string $type, string $text ): string {
		$type = in_array( $type, array( 'success', 'danger', 'warning', 'info' ), true ) ? $type : 'info';
		return '<div class="bw-notice bw-notice--' . $type . '" role="status"><div class="bw-notice__body"><p class="bw-notice__text">' . self::e( $text ) . '</p></div></div>';
	}

	/* ------------------------------------------------------------------ */

	/** @param array<string,mixed> $model */
	private static function settings_form( array $model ): string {
		$s   = $model['settings'];
		$f   = self::FIELD;
		$out = '<form method="post" action="' . self::e( (string) $model['action_url'] ) . '" class="clubhouse-playtomic__settings">'
			. (string) $model['nonce_field']
			. '<input type="hidden" name="action" value="' . self::e( (string) $model['actions']['save'] ) . '">';

		$out .= '<div class="bw-fields">';
		$out .= self::text( $f . '[client_id]', 'client_id', 'Client ID', (string) $s['client_id'], 'From Playtomic Manager → Settings → Developer tools.' );
		$secret_help = ! empty( $s['has_secret'] ) ? 'Saved. Enter a new one only to replace it.' : 'Sent to you by Playtomic with the Client ID.';
		if ( empty( $model['can_encrypt'] ) ) {
			$secret_help .= ' This server cannot encrypt it, so it is stored as plain text.';
		}
		$out .= '<div class="bw-field"><label class="bw-field__label" for="playtomic-secret">Secret</label>'
			. '<input class="bw-input" type="password" id="playtomic-secret" name="' . $f . '[secret]" value="" autocomplete="new-password">'
			. '<p class="bw-field__help">' . self::e( $secret_help ) . '</p></div>';
		$out .= '</div>';

		$last = (string) ( $s['last_test'] ?? '' );
		$out .= '<p class="bw-fieldnote">' . ( '' !== $last ? self::e( $last ) : 'Not tested yet.' ) . '</p>';
		$err = (string) ( $s['last_error'] ?? '' );
		if ( '' !== $err ) {
			$out .= '<p class="bw-fieldnote clubhouse-playtomic__error">Last problem: ' . self::e( $err ) . '</p>';
		}

		$out .= self::who( $model );
		$out .= self::venues( $s['venues'], (string) ( $model['today'] ?? '' ) );

		$out .= '<div class="bw-card__actions clubhouse-playtomic__buttons">'
			. '<button type="submit" class="bw-btn bw-btn--primary">Save settings</button> '
			. '<button type="submit" class="bw-btn bw-btn--secondary" name="action" value="' . self::e( (string) $model['actions']['test'] ) . '">Save and test connection</button>'
			. '</div>';
		return $out . '</form>';
	}

	private static function text( string $name, string $id, string $label, string $value, string $help = '' ): string {
		$out = '<div class="bw-field"><label class="bw-field__label" for="playtomic-' . self::e( $id ) . '">' . self::e( $label ) . '</label>'
			. '<input class="bw-input" type="text" id="playtomic-' . self::e( $id ) . '" name="' . self::e( $name ) . '" value="' . self::e( $value ) . '">';
		if ( '' !== $help ) {
			$out .= '<p class="bw-field__help">' . self::e( $help ) . '</p>';
		}
		return $out . '</div>';
	}

	/** @param array<string,mixed> $model */
	private static function who( array $model ): string {
		$s      = $model['settings'];
		$f      = self::FIELD;
		$shop   = ! empty( $model['shop'] );
		// The stored rule, even with the shop off, so a Save does not quietly change it.
		$all    = Blueworx_Clubhouse_Playtomic_Settings::WHO_SUBSCRIPTIONS !== (string) $s['who'];
		$out    = '<fieldset class="bw-field clubhouse-playtomic__who"><legend class="bw-field__label">Who counts as a member</legend>';
		if ( ! $shop && ! $all ) {
			// A disabled radio is not sent, and the price boxes are not drawn,
			// so carry the stored rule through. Ticking "Everyone" comes later
			// in the form and wins.
			$out .= '<input type="hidden" name="' . $f . '[who]" value="subscriptions">';
			foreach ( (array) $s['price_ids'] as $id ) {
				$out .= '<input type="hidden" name="' . $f . '[price_ids][]" value="' . self::e( (string) $id ) . '">';
			}
		}
		$out   .= '<label class="bw-check"><input type="radio" name="' . $f . '[who]" value="all"' . ( $all ? ' checked' : '' ) . '> <span class="bw-check__text">Everyone with a member account</span></label>';
		$out   .= '<label class="bw-check"><input type="radio" name="' . $f . '[who]" value="subscriptions"' . ( $all ? '' : ' checked' ) . ( $shop ? '' : ' disabled' ) . '> <span class="bw-check__text">Only members with one of these subscriptions</span>'
			. ( $shop ? '' : '<span class="bw-check__help">This needs a shop. Install SureCart and set up your membership prices first.</span>' ) . '</label>';
		if ( $shop ) {
			$out .= '<div class="clubhouse-playtomic__prices">';
			foreach ( (array) $model['prices'] as $price ) {
				$checked = in_array( (string) $price['id'], (array) $s['price_ids'], true ) ? ' checked' : '';
				$out    .= '<label class="bw-check"><input type="checkbox" name="' . $f . '[price_ids][]" value="' . self::e( (string) $price['id'] ) . '"' . $checked . '> <span class="bw-check__text">' . self::e( (string) $price['label'] ) . '</span></label>';
			}
			if ( array() === (array) $model['prices'] ) {
				$out .= '<p class="bw-field__help">No prices found in the shop yet.</p>';
			}
			$out .= '</div>';
		}
		return $out . '</fieldset>';
	}

	/**
	 * @param array<int,array<string,mixed>> $venues
	 * @param string                         $today  Y-m-d, to flag a fixed expiry already past.
	 */
	private static function venues( array $venues, string $today ): string {
		$saved  = count( $venues );
		$rows   = array_values( $venues );
		$rows[] = Blueworx_Clubhouse_Playtomic_Settings::venue_defaults();
		$out    = '<h3 class="bw-card__title">Venues</h3><p class="bw-card__note">One row per venue. Press Save and test connection to fill the ids in from Playtomic. The club name becomes part of the file\'s column headers, so it must match what Playtomic Manager calls the venue.</p>';
		$out .= '<div class="bw-tablescroll"><table class="bw-table clubhouse-playtomic__venues"><thead><tr>'
			. '<th>Venue id</th><th>Name</th><th>Club name in headers</th><th>Category</th><th>Expires</th><th>Price id</th><th>Next payment</th></tr></thead><tbody>';
		foreach ( $rows as $i => $v ) {
			$n           = self::FIELD . '[venues][' . $i . ']';
			$club_note   = '';
			$expiry_note = '';
			// The blank row at the end is for adding a venue, so it is never flagged.
			if ( $i < $saved ) {
				if ( '' === trim( (string) $v['club'] ) ) {
					$club_note = ' <span class="bw-badge bw-badge--warning">Needs a club name before it can go in the file</span>';
				}
				$date = (string) $v['expiry_date'];
				if ( Blueworx_Clubhouse_Playtomic_Settings::EXPIRY_DATE === (string) $v['expiry_mode'] && '' !== $date && '' !== $today && $date < $today ) {
					$expiry_note = ' <span class="bw-badge bw-badge--warning">Expiry date is in the past</span>';
				}
			}
			$out .= '<tr>'
				. '<td><input class="bw-input" type="text" name="' . $n . '[id]" value="' . self::e( (string) $v['id'] ) . '" aria-label="Venue id"></td>'
				. '<td><input class="bw-input" type="text" name="' . $n . '[name]" value="' . self::e( (string) $v['name'] ) . '" aria-label="Venue name"></td>'
				. '<td><input class="bw-input" type="text" name="' . $n . '[club]" value="' . self::e( (string) $v['club'] ) . '" aria-label="Club name in headers">' . $club_note . '</td>'
				. '<td><input class="bw-input" type="text" name="' . $n . '[category]" value="' . self::e( (string) $v['category'] ) . '" aria-label="Category name"></td>'
				. '<td>' . self::select( $n . '[expiry_mode]', 'Expiry rule', array( 'months' => 'Months from export', 'date' => 'On a date' ), (string) $v['expiry_mode'] )
					. ' <input class="bw-input" type="number" min="1" max="120" name="' . $n . '[expiry_months]" value="' . (int) $v['expiry_months'] . '" aria-label="Months until expiry">'
					. ' <input class="bw-input" type="date" name="' . $n . '[expiry_date]" value="' . self::e( (string) $v['expiry_date'] ) . '" aria-label="Expiry date">' . $expiry_note . '</td>'
				. '<td><input class="bw-input" type="text" name="' . $n . '[price_id]" value="' . self::e( (string) $v['price_id'] ) . '" aria-label="Benefit price id"></td>'
				. '<td>' . self::select( $n . '[due_mode]', 'Next payment rule', array( 'none' => 'Leave blank', 'expiry' => 'Same as expiry', 'months' => 'Months from export' ), (string) $v['due_mode'] )
					. ' <input class="bw-input" type="number" min="1" max="120" name="' . $n . '[due_months]" value="' . (int) $v['due_months'] . '" aria-label="Months until next payment"></td>'
				. '</tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/** @param array<string,string> $options */
	private static function select( string $name, string $label, array $options, string $current, string $id = '' ): string {
		$out = '<select class="bw-select__el" name="' . $name . '" aria-label="' . self::e( $label ) . '"' . ( '' === $id ? '' : ' id="' . self::e( $id ) . '"' ) . '>';
		foreach ( $options as $value => $text ) {
			$out .= '<option value="' . self::e( (string) $value ) . '"' . ( $current === (string) $value ? ' selected' : '' ) . '>' . self::e( $text ) . '</option>';
		}
		return $out . '</select>';
	}

	/* ------------------------------------------------------------------ */

	/** @param array<string,mixed> $model */
	private static function members_form( array $model ): string {
		$out  = '<form method="get" action="' . self::e( strtok( (string) $model['page_url'], '?' ) ) . '" class="clubhouse-playtomic__filters bw-fields">'
			. '<input type="hidden" name="page" value="clubhouse-playtomic">'
			. '<div class="bw-field"><label class="bw-field__label" for="playtomic-filter">Show</label>'
			. self::select( self::FILTER, 'Show', array( 'new' => 'Not yet exported', 'all' => 'All members' ), (string) $model['filter'], 'playtomic-filter' ) . '</div>'
			. '<div class="bw-field"><label class="bw-field__label" for="playtomic-from">Joined from</label><input class="bw-input" type="date" id="playtomic-from" name="' . self::FROM . '" value="' . self::e( (string) $model['from'] ) . '"></div>'
			. '<div class="bw-field"><label class="bw-field__label" for="playtomic-to">Joined to</label><input class="bw-input" type="date" id="playtomic-to" name="' . self::TO . '" value="' . self::e( (string) $model['to'] ) . '"></div>'
			. '<div class="bw-field"><button type="submit" class="bw-btn bw-btn--secondary">Apply</button></div>'
			. '</form>';

		$members = (array) $model['members'];
		$out    .= '<form method="post" action="' . self::e( (string) $model['action_url'] ) . '" class="clubhouse-playtomic__export">'
			. (string) $model['nonce_field']
			. '<input type="hidden" name="action" value="' . self::e( (string) $model['actions']['download'] ) . '">'
			. '<input type="hidden" name="' . self::FROM . '" value="' . self::e( (string) $model['from'] ) . '">'
			. '<input type="hidden" name="' . self::TO . '" value="' . self::e( (string) $model['to'] ) . '">';

		if ( array() === $members ) {
			$out .= '<p class="bw-fieldnote">Nobody to export with this filter.</p>';
		} else {
			$out .= '<div class="bw-tablescroll"><table class="bw-table clubhouse-playtomic__members"><thead><tr>'
				. '<th class="bw-table__check"><input type="checkbox" data-playtomic-all aria-label="Tick everyone listed"></th>'
				. '<th>Name</th><th>Email</th><th>Phone</th><th>Joined</th><th>Exported</th></tr></thead><tbody>';
			foreach ( $members as $m ) {
				$id       = (int) $m['id'];
				$complete = ! empty( $m['complete'] );
				$ticked   = $complete && '' === (string) $m['exported'];
				$out     .= '<tr>'
					. '<td class="bw-table__check"><input type="checkbox" name="' . self::IDS . '[]" value="' . $id . '" aria-label="Export ' . self::e( (string) $m['name'] ) . '"' . ( $ticked ? ' checked' : '' ) . ( $complete ? '' : ' disabled' ) . '></td>'
					. '<td><span class="bw-table__primary">' . self::e( (string) $m['name'] ) . '</span>'
					. ( $complete ? '' : '<span class="bw-badge bw-badge--warning">' . self::e( (string) $m['reason'] ) . '</span>' ) . '</td>'
					. '<td>' . self::e( (string) $m['email'] ) . '</td>'
					. '<td>' . self::e( (string) $m['phone'] ) . '</td>'
					. '<td>' . self::e( (string) $m['registered'] ) . '</td>'
					. '<td>' . ( '' === (string) $m['exported'] ? '—' : self::e( (string) $m['exported'] ) . ' <a class="bw-btn bw-btn--link bw-btn--sm" href="' . self::e( (string) ( $model['clear_urls'][ $id ] ?? '' ) ) . '">Clear export mark</a>' ) . '</td>'
					. '</tr>';
			}
			$out .= '</tbody></table></div>';
		}

		$out .= '<div class="bw-card__actions clubhouse-playtomic__buttons">'
			. '<span class="bw-fieldnote" data-playtomic-count>' . count( array_filter( $members, static fn( array $m ): bool => ! empty( $m['complete'] ) && '' === (string) $m['exported'] ) ) . ' of ' . count( $members ) . ' selected</span> '
			. '<button type="submit" class="bw-btn bw-btn--primary" name="' . self::e( (string) $model['actions']['download'] ) . '" value="1"' . ( array() === $members ? ' disabled' : '' ) . '>Download Playtomic CSV</button>'
			. '</div>';
		return $out . '</form>';
	}
}
