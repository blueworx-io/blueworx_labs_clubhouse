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
 * Pure HTML from a model, built only from the design system's own classes.
 * Works without JavaScript: the table is a form posted to admin-post;
 * select-all and the live count are progressive.
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
		$out .= self::settings_card( $model );
		$out .= self::members_card( $model );
		return $out . Blueworx_Clubhouse_Admin_Shell::close();
	}

	private static function notice( string $type, string $text ): string {
		$type = in_array( $type, array( 'success', 'danger', 'warning', 'info' ), true ) ? $type : 'info';
		$icon = match ( $type ) {
			'success' => 'circle-check',
			'danger', 'warning' => 'triangle-alert',
			default => 'info',
		};
		$role = 'success' === $type ? 'status' : 'alert';
		return '<div class="bw-notice bw-notice--' . $type . '" role="' . $role . '">'
			. '<i class="bw-icon bw-icon--18 bw-notice__icon" data-lucide="' . $icon . '"></i>'
			. '<div class="bw-notice__body"><p class="bw-notice__text">' . self::e( $text ) . '</p></div></div>';
	}

	/** The card's head, the same shape Admin_Shell::card() draws, left open so a footer can follow the body. */
	private static function head( string $eyebrow, string $title, string $note ): string {
		return '<div class="bw-card__head"><div class="bw-card__titles">'
			. '<p class="bw-card__eyebrow">' . self::e( $eyebrow ) . '</p>'
			. '<h2 class="bw-card__title">' . self::e( $title ) . '</h2>'
			. '<p class="bw-card__note">' . self::e( $note ) . '</p>'
			. '</div></div>';
	}

	/** One settings row: a label on the left, the control and its help on the right. */
	private static function row( string $label, string $control, string $help = '', string $for = '' ): string {
		$tag  = '' === $for ? 'div' : 'label';
		$attr = '' === $for ? '' : ' for="' . self::e( $for ) . '"';
		return '<div class="bw-formrow"><' . $tag . ' class="bw-formrow__label"' . $attr . '>' . self::e( $label ) . '</' . $tag . '>'
			. '<div class="bw-formrow__control">' . $control
			. ( '' === $help ? '' : '<p class="bw-formrow__help">' . self::e( $help ) . '</p>' )
			. '</div></div>';
	}

	/* ------------------------------------------------------------------ */

	/** @param array<string,mixed> $model */
	private static function settings_card( array $model ): string {
		$s   = $model['settings'];
		$f   = self::FIELD;
		$out = '<form method="post" action="' . self::e( (string) $model['action_url'] ) . '" class="clubhouse-playtomic__settings">'
			. (string) $model['nonce_field']
			. '<input type="hidden" name="action" value="' . self::e( (string) $model['actions']['save'] ) . '">'
			. '<div class="bw-card">'
			. self::head( 'Step 1', 'Connection and settings', 'Your Playtomic developer credentials, who counts as a member, and one row per venue.' )
			. '<div class="bw-card__body">';

		$out .= self::row(
			'Client ID',
			'<input class="bw-input bw-input--mono" type="text" id="playtomic-client_id" name="' . $f . '[client_id]" value="' . self::e( (string) $s['client_id'] ) . '" autocomplete="off">',
			'From Playtomic Manager → Settings → Developer tools.',
			'playtomic-client_id'
		);

		$secret_help = ! empty( $s['has_secret'] ) ? 'Saved. Enter a new one only to replace it.' : 'Sent to you by Playtomic with the Client ID.';
		if ( empty( $model['can_encrypt'] ) ) {
			$secret_help .= ' This server cannot encrypt it, so it is stored as plain text.';
		}
		$out .= self::row(
			'Secret',
			'<input class="bw-input bw-input--mono" type="password" id="playtomic-secret" name="' . $f . '[secret]" value="" autocomplete="new-password">',
			$secret_help,
			'playtomic-secret'
		);

		$out .= self::row( 'Connection', self::connection_status( $s ), 'Press Save and test connection to check the credentials and read your venues from Playtomic.' );
		$out .= self::row( 'Who counts as a member', self::who( $model ) );
		$out .= self::row( 'Venues', self::venues( $s['venues'], (string) ( $model['today'] ?? '' ) ) );

		$out .= '</div>'
			. '<div class="bw-card__foot">'
			. '<button type="submit" class="bw-btn bw-btn--secondary" name="action" value="' . self::e( (string) $model['actions']['test'] ) . '">Save and test connection</button>'
			. '<button type="submit" class="bw-btn bw-btn--primary">Save settings</button>'
			. '</div></div></form>';
		return $out;
	}

	/** @param array<string,mixed> $s */
	private static function connection_status( array $s ): string {
		$last = (string) ( $s['last_test'] ?? '' );
		$err  = (string) ( $s['last_error'] ?? '' );
		// Divs, not paragraphs: a p carries the browser's own margins, and the
		// row's control column already spaces its children.
		$out = '<div class="bw-fieldnote"><i class="bw-icon bw-icon--14" data-lucide="' . ( '' === $last ? 'info' : 'circle-check' ) . '"></i><span>'
			. ( '' === $last ? 'Not tested yet.' : self::e( $last ) ) . '</span></div>';
		if ( '' !== $err ) {
			$out .= '<div class="bw-fieldnote clubhouse-playtomic__error"><i class="bw-icon bw-icon--14" data-lucide="triangle-alert"></i><span>Last problem: ' . self::e( $err ) . '</span></div>';
		}
		return $out;
	}

	/** @param array<string,mixed> $model */
	private static function who( array $model ): string {
		$s    = $model['settings'];
		$f    = self::FIELD;
		$shop = ! empty( $model['shop'] );
		// The stored rule, even with the shop off, so a Save does not quietly change it.
		$all = Blueworx_Clubhouse_Playtomic_Settings::WHO_SUBSCRIPTIONS !== (string) $s['who'];
		$out = '<fieldset class="bw-fields bw-fields--single clubhouse-playtomic__who"><legend class="bw-field__label">Rule</legend>';
		if ( ! $shop && ! $all ) {
			// A disabled radio is not sent, and the price boxes are not drawn,
			// so carry the stored rule through. Ticking "Everyone" comes later
			// in the form and wins.
			$out .= '<input type="hidden" name="' . $f . '[who]" value="subscriptions">';
			foreach ( (array) $s['price_ids'] as $id ) {
				$out .= '<input type="hidden" name="' . $f . '[price_ids][]" value="' . self::e( (string) $id ) . '">';
			}
		}
		$out .= '<label class="bw-check"><input type="radio" name="' . $f . '[who]" value="all"' . ( $all ? ' checked' : '' ) . '><span class="bw-check__text">Everyone with a member account</span></label>';
		$out .= '<label class="bw-check"' . ( $shop ? '' : ' data-disabled="true"' ) . '><input type="radio" name="' . $f . '[who]" value="subscriptions"' . ( $all ? '' : ' checked' ) . ( $shop ? '' : ' disabled' ) . '>'
			. '<span class="bw-check__text">Only members with one of these subscriptions'
			. ( $shop ? '' : '<span class="bw-check__help">This needs a shop. Install SureCart and set up your membership prices first.</span>' )
			. '</span></label>';
		if ( $shop ) {
			$prices = (array) $model['prices'];
			$out   .= '<div class="bw-card bw-card--sunken clubhouse-playtomic__prices"><div class="bw-card__body bw-fields bw-fields--single">';
			foreach ( $prices as $price ) {
				$checked = in_array( (string) $price['id'], (array) $s['price_ids'], true ) ? ' checked' : '';
				$out    .= '<label class="bw-check"><input type="checkbox" name="' . $f . '[price_ids][]" value="' . self::e( (string) $price['id'] ) . '"' . $checked . '><span class="bw-check__text">' . self::e( (string) $price['label'] ) . '</span></label>';
			}
			if ( array() === $prices ) {
				$out .= '<p class="bw-field__help">No prices found in the shop yet.</p>';
			}
			$out .= '</div></div>';
		}
		return $out . '</fieldset>';
	}

	/**
	 * One repeater row per venue, plus a blank one for adding the next.
	 *
	 * @param array<int,array<string,mixed>> $venues
	 * @param string                         $today  Y-m-d, to flag a fixed expiry already past.
	 */
	private static function venues( array $venues, string $today ): string {
		$saved  = count( $venues );
		$rows   = array_values( $venues );
		$rows[] = Blueworx_Clubhouse_Playtomic_Settings::venue_defaults();
		$out    = '<div class="bw-repeater">';
		foreach ( $rows as $i => $v ) {
			$n     = self::FIELD . '[venues][' . $i . ']';
			$is_new = $i >= $saved;
			$badges = '';
			// The blank row at the end is for adding a venue, so it is never flagged.
			if ( ! $is_new ) {
				if ( '' === trim( (string) $v['club'] ) ) {
					$badges .= '<span class="bw-badge bw-badge--warning">Needs a club name</span>';
				}
				$date = (string) $v['expiry_date'];
				if ( Blueworx_Clubhouse_Playtomic_Settings::EXPIRY_DATE === (string) $v['expiry_mode'] && '' !== $date && '' !== $today && $date < $today ) {
					$badges .= '<span class="bw-badge bw-badge--warning">Expiry date is in the past</span>';
				}
			}
			$title = '' !== trim( (string) $v['name'] ) ? (string) $v['name'] : 'Venue ' . ( $i + 1 );
			if ( $is_new ) {
				// The blank row folds away behind a native disclosure, so a club
				// with its venues in place is not shown an empty form every
				// visit. Open when there is nothing saved yet, because then it
				// is the form.
				$out .= '<details class="bw-accordion"' . ( 0 === $saved ? ' open' : '' ) . '>'
					. '<summary class="bw-accordion__head"><span class="bw-accordion__title">Add a venue'
					. '<span class="bw-accordion__sub">Fill this in and save. Save and test connection adds any venue Playtomic knows about by itself.</span></span>'
					. '<i class="bw-icon bw-icon--18 bw-accordion__chev" data-lucide="chevron-down"></i></summary>'
					. '<div class="bw-accordion__body"><div class="bw-fields">';
			} else {
				$out .= '<div class="bw-repeater__row">'
					. '<div class="bw-repeater__bar"><p class="bw-card__eyebrow">' . self::e( $title ) . '</p><span class="bw-toolbar__spacer"></span>' . $badges . '</div>'
					. '<div class="bw-fields">';
			}
			$out .= self::field( $n . '[id]', 'venue-' . $i . '-id', 'Venue id', (string) $v['id'], 'text', $is_new ? 'Filled in by Save and test connection, or paste it from Playtomic.' : '', 'bw-input--mono' )
				. self::field( $n . '[name]', 'venue-' . $i . '-name', 'Name', (string) $v['name'], 'text', $is_new ? 'For your own reference.' : '' )
				. self::field( $n . '[club]', 'venue-' . $i . '-club', 'Club name in headers', (string) $v['club'], 'text', 'Part of the file\'s column headers, so it must match what Playtomic Manager calls the venue.' )
				. self::field( $n . '[category]', 'venue-' . $i . '-category', 'Category', (string) $v['category'], 'text', 'Must match the membership category set up in Playtomic Manager.' )
				. '<div class="bw-field"><label class="bw-field__label" for="venue-' . $i . '-expiry_mode">Expiry rule</label>'
					. self::select( $n . '[expiry_mode]', 'Expiry rule', array( 'months' => 'Months from export', 'date' => 'On a date' ), (string) $v['expiry_mode'], 'venue-' . $i . '-expiry_mode' ) . '</div>'
				. self::field( $n . '[expiry_months]', 'venue-' . $i . '-expiry_months', 'Months from export', (string) (int) $v['expiry_months'], 'number', 'Used by the first rule.' )
				. self::field( $n . '[expiry_date]', 'venue-' . $i . '-expiry_date', 'Expiry date', (string) $v['expiry_date'], 'date', 'Used by the second rule.' )
				. self::field( $n . '[price_id]', 'venue-' . $i . '-price_id', 'Price id', (string) $v['price_id'], 'text', 'Optional. The membership price in Playtomic, if one is used.', 'bw-input--mono' )
				. '<div class="bw-field"><label class="bw-field__label" for="venue-' . $i . '-due_mode">Next payment rule</label>'
					. self::select( $n . '[due_mode]', 'Next payment rule', array( 'none' => 'Leave blank', 'expiry' => 'Same as expiry', 'months' => 'Months from export' ), (string) $v['due_mode'], 'venue-' . $i . '-due_mode' ) . '</div>'
				. self::field( $n . '[due_months]', 'venue-' . $i . '-due_months', 'Months until next payment', (string) (int) $v['due_months'], 'number', 'Used only when the rule above is Months from export.' )
				. ( $is_new ? '</div></div></details>' : '</div></div>' );
		}
		return $out . '</div>';
	}

	private static function field( string $name, string $id, string $label, string $value, string $type, string $help = '', string $extra = '' ): string {
		$range = 'number' === $type ? ' min="1" max="120"' : '';
		$out   = '<div class="bw-field"><label class="bw-field__label" for="' . self::e( $id ) . '">' . self::e( $label ) . '</label>'
			. '<input class="bw-input' . ( '' === $extra ? '' : ' ' . $extra ) . '" type="' . $type . '" id="' . self::e( $id ) . '" name="' . self::e( $name ) . '" value="' . self::e( $value ) . '"' . $range . '>';
		if ( '' !== $help ) {
			$out .= '<p class="bw-field__help">' . self::e( $help ) . '</p>';
		}
		return $out . '</div>';
	}

	/** @param array<string,string> $options */
	private static function select( string $name, string $label, array $options, string $current, string $id = '' ): string {
		$out = '<span class="bw-select"><select class="bw-select__el" name="' . $name . '" aria-label="' . self::e( $label ) . '"' . ( '' === $id ? '' : ' id="' . self::e( $id ) . '"' ) . '>';
		foreach ( $options as $value => $text ) {
			$out .= '<option value="' . self::e( (string) $value ) . '"' . ( $current === (string) $value ? ' selected' : '' ) . '>' . self::e( $text ) . '</option>';
		}
		return $out . '</select><i class="bw-icon bw-icon--14 bw-select__arrow" data-lucide="chevron-down"></i></span>';
	}

	/* ------------------------------------------------------------------ */

	/** @param array<string,mixed> $model */
	private static function members_card( array $model ): string {
		$members = (array) $model['members'];
		$empty   = array() === $members;

		// Filters are a toolbar; with a table below it sits flush against the
		// card, with the empty state below it the body keeps its padding.
		$filters = '<form method="get" action="' . self::e( strtok( (string) $model['page_url'], '?' ) ) . '" class="bw-toolbar' . ( $empty ? ' bw-toolbar--card' : '' ) . ' clubhouse-playtomic__filters">'
			. '<input type="hidden" name="page" value="clubhouse-playtomic">'
			. '<div class="bw-toolbar__group"><label class="bw-field__label" for="playtomic-filter">Show</label>'
			. self::select( self::FILTER, 'Show', array( 'new' => 'Not yet exported', 'all' => 'All members' ), (string) $model['filter'], 'playtomic-filter' ) . '</div>'
			. '<div class="bw-toolbar__group"><label class="bw-field__label" for="playtomic-from">Joined from</label><input class="bw-input bw-bulk__select" type="date" id="playtomic-from" name="' . self::FROM . '" value="' . self::e( (string) $model['from'] ) . '"></div>'
			. '<div class="bw-toolbar__group"><label class="bw-field__label" for="playtomic-to">To</label><input class="bw-input bw-bulk__select" type="date" id="playtomic-to" name="' . self::TO . '" value="' . self::e( (string) $model['to'] ) . '"></div>'
			. '<button type="submit" class="bw-btn bw-btn--secondary">Apply</button>'
			. '</form>';

		$out = '<div class="bw-card' . ( $empty ? '' : ' bw-card--flush' ) . '">'
			. self::head( 'Step 2', 'Review and export', 'Tick who goes. Nobody is exported without a tick, and everyone exported is marked with the date.' )
			. '<div class="bw-card__body">'
			. $filters
			. '<form method="post" action="' . self::e( (string) $model['action_url'] ) . '" class="clubhouse-playtomic__export">'
			. (string) $model['nonce_field']
			. '<input type="hidden" name="action" value="' . self::e( (string) $model['actions']['download'] ) . '">'
			. '<input type="hidden" name="' . self::FROM . '" value="' . self::e( (string) $model['from'] ) . '">'
			. '<input type="hidden" name="' . self::TO . '" value="' . self::e( (string) $model['to'] ) . '">';

		if ( $empty ) {
			$out .= '<div class="bw-empty"><i class="bw-icon bw-icon--28 bw-empty__icon" data-lucide="users"></i>'
				. '<p class="bw-empty__title">Nobody to export with this filter.</p>'
				. '<p class="bw-empty__text">Switch to All members to see everyone, including those already sent.</p></div>';
		} else {
			$out .= self::table( $members, (array) $model['clear_urls'] );
		}

		$ticked = count( array_filter( $members, static fn( array $m ): bool => ! empty( $m['complete'] ) && '' === (string) $m['exported'] ) );
		$out   .= '<div class="' . ( $empty ? 'bw-card__actions' : 'bw-tablefoot' ) . '">'
			. '<span class="bw-bulk__count" data-playtomic-count>' . $ticked . ' of ' . count( $members ) . ' selected</span>'
			. ( $empty ? '<span class="bw-toolbar__spacer"></span>' : '' )
			. '<button type="submit" class="bw-btn bw-btn--primary" name="' . self::e( (string) $model['actions']['download'] ) . '" value="1"' . ( $empty ? ' disabled' : '' ) . '><i class="bw-icon bw-icon--18" data-lucide="download"></i>Download Playtomic CSV</button>'
			. '</div>';
		return $out . '</form></div></div>';
	}

	/**
	 * @param array<int,array<string,mixed>> $members
	 * @param array<int,string>              $clear_urls
	 */
	private static function table( array $members, array $clear_urls ): string {
		$out = '<div class="bw-tablescroll"><table class="bw-table clubhouse-playtomic__members"><thead><tr>'
			. '<th class="bw-table__check"><input type="checkbox" data-playtomic-all aria-label="Tick everyone listed"></th>'
			. '<th>Member</th><th>Phone</th><th>Joined</th><th>Exported</th></tr></thead><tbody>';
		foreach ( $members as $m ) {
			$id       = (int) $m['id'];
			$name     = (string) $m['name'];
			$email    = (string) $m['email'];
			$complete = ! empty( $m['complete'] );
			$ticked   = $complete && '' === (string) $m['exported'];
			$exported = (string) $m['exported'];
			$out     .= '<tr>'
				. '<td class="bw-table__check"><input type="checkbox" name="' . self::IDS . '[]" value="' . $id . '" aria-label="Export ' . self::e( '' !== $name ? $name : $email ) . '"' . ( $ticked ? ' checked' : '' ) . ( $complete ? '' : ' disabled' ) . '></td>'
				. '<td><span class="bw-table__primary">' . self::e( '' !== $name ? $name : '—' ) . '</span>'
				. '<span class="bw-table__sub">' . self::e( '' !== $email ? $email : '—' ) . '</span>'
				. ( $complete ? '' : '<span class="bw-table__sub"><span class="bw-badge bw-badge--warning">' . self::e( (string) $m['reason'] ) . '</span></span>' ) . '</td>'
				. '<td>' . ( '' === (string) $m['phone'] ? '—' : self::e( (string) $m['phone'] ) ) . '</td>'
				. '<td>' . self::e( (string) $m['registered'] ) . '</td>'
				. '<td>' . ( '' === $exported
					? '<span class="bw-badge bw-badge--neutral">Not yet</span>'
					: '<span class="bw-badge bw-badge--success">' . self::e( $exported ) . '</span> <a class="bw-btn bw-btn--link bw-btn--sm" href="' . self::e( (string) ( $clear_urls[ $id ] ?? '' ) ) . '">Clear export mark</a>' )
				. '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}
}
