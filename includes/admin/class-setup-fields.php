<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Setup screen as data, in the page editor library's vocabulary.
 *
 * Pure — no hooks, no WordPress, no storage — so SetupFieldsTest can hold the
 * whole screen against Schema::validate() and a mistake is a red test rather
 * than a live screen telling an owner it is not ready.
 *
 * Tabs a user may not touch are left out here rather than filtered afterwards.
 * Capabilities::reduce() empties a panel it may not show but keeps the panel
 * itself, so a Content Editor filtered that way would land on four empty tabs
 * and one with the menu in it.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Setup_Fields {

	/** A page's switch is this prefix plus the page key visibility is stored under. */
	public const PAGE_FIELD_PREFIX = 'page_visible_';

	private static function cap(): string {
		return Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP;
	}

	/**
	 * @param array{setup:bool,menu:bool,demo:bool} $can
	 * @param array<string,array{name:string,description:string}> $looks
	 * @param array<int,array{page:string,label:string}> $pages
	 * @return array<int,array<string,mixed>>
	 */
	public static function tabs( array $can, array $looks, array $pages ): array {
		$tabs = array();
		if ( ! empty( $can['setup'] ) ) {
			$tabs[] = self::look_tab( $looks );
			$tabs[] = self::visibility_tab( $pages );
		}
		if ( ! empty( $can['menu'] ) ) {
			$tabs[] = self::menu_tab();
		}
		if ( ! empty( $can['setup'] ) ) {
			$tabs[] = self::members_tab();
			$tabs[] = self::settings_tab();
		}
		if ( ! empty( $can['setup'] ) && ! empty( $can['demo'] ) ) {
			$tabs[] = self::demo_tab();
		}
		return $tabs;
	}

	/**
	 * Base Look, and the branding that rides on it.
	 *
	 * A radio rather than the old preview cards: the club's own look no longer
	 * skins wp-admin (issue #282), so there is nothing left for a card to
	 * preview. Each look's own description carries the difference instead.
	 *
	 * @param array<string,array{name:string,description:string}> $looks
	 * @return array<string,mixed>
	 */
	private static function look_tab( array $looks ): array {
		$options = array();
		$help    = array();
		foreach ( $looks as $slug => $look ) {
			$options[] = array( 'value' => (string) $slug, 'label' => $look['name'] );
			$help[]    = $look['name'] . ' — ' . $look['description'];
		}

		return array(
			'id'     => 'look',
			'label'  => 'Base Look & Branding',
			'panels' => array(
				array(
					'id'     => 'base_look',
					'title'  => 'Base Look',
					'note'   => 'The visual foundation for your site. Everything else adapts to it.',
					'fields' => array(
						array(
							'id'         => 'look',
							'kind'       => 'radio',
							'label'      => 'Base Look',
							'options'    => $options,
							'help'       => implode( ' · ', $help ),
							'capability' => self::cap(),
						),
					),
				),
				array(
					'id'     => 'branding',
					'title'  => 'Branding',
					'note'   => 'Your club name, colours, logo and social links.',
					'fields' => array(
						array(
							'id'         => 'club_name',
							'kind'       => 'text',
							'label'      => 'Club name',
							'capability' => self::cap(),
						),
						array(
							'id'         => 'accent',
							'kind'       => 'colour',
							'label'      => 'Main colour',
							'help'       => 'Used for buttons and links. It has to stay readable on your chosen look, so a very pale or very grey colour is refused.',
							'capability' => self::cap(),
						),
						array(
							'id'         => 'secondary',
							'kind'       => 'colour',
							'label'      => 'Second colour',
							// This is where the old "saved, but low contrast"
							// warning went. The library has field errors and no
							// warning channel, and a club that insists on its
							// real brand colour here should be told rather than
							// overruled — so it is said once, in advance.
							'help'       => 'Optional. Leave it empty to have one worked out from your main colour. A low-contrast colour is allowed here, but text on it may be hard to read.',
							'capability' => self::cap(),
						),
						array(
							'id'         => 'logo',
							'kind'       => 'media',
							'label'      => 'Logo',
							'capability' => self::cap(),
						),
						array(
							'id'         => 'favicon',
							'kind'       => 'media',
							'label'      => 'Browser tab icon',
							'capability' => self::cap(),
						),
						array(
							'id'         => 'facebook',
							'kind'       => 'text',
							'format'     => 'url',
							'label'      => 'Facebook',
							'capability' => self::cap(),
						),
						array(
							'id'         => 'instagram',
							'kind'       => 'text',
							'format'     => 'url',
							'label'      => 'Instagram',
							'capability' => self::cap(),
						),
						array(
							'id'         => 'linkedin',
							'kind'       => 'text',
							'format'     => 'url',
							'label'      => 'LinkedIn',
							'capability' => self::cap(),
						),
						array(
							'id'         => 'x',
							'kind'       => 'text',
							'format'     => 'url',
							'label'      => 'X',
							'capability' => self::cap(),
						),
					),
				),
			),
		);
	}

	/**
	 * One switch per page this site can actually serve. A section's own on/off
	 * is not here — it is on that section's panel, on the page it belongs to,
	 * which is where somebody looking for it would look (phase 3).
	 *
	 * @param array<int,array{page:string,label:string}> $pages
	 * @return array<string,mixed>
	 */
	private static function visibility_tab( array $pages ): array {
		$fields = array();
		foreach ( $pages as $page ) {
			$fields[] = array(
				'id'         => self::PAGE_FIELD_PREFIX . $page['page'],
				'kind'       => 'toggle',
				'label'      => $page['label'],
				'default'    => true,
				'capability' => self::cap(),
			);
		}

		return array(
			'id'     => 'visibility',
			'label'  => 'Visibility',
			'panels' => array(
				array(
					'id'     => 'pages',
					'title'  => 'Pages',
					'note'   => 'Switch a page off and it stops being published — visitors and search engines both get a proper "not found". Sections are switched off on the page itself.',
					'fields' => $fields,
				),
			),
		);
	}

	/**
	 * The nav, as a repeater. Indent is a flag on the row rather than a drag
	 * handle: the library owns reordering, and a child is simply the row after
	 * its parent with the switch on.
	 *
	 * These fields carry no capability, which is what lets a Content Editor
	 * write them while the rest of the screen stays behind SETUP_CAP.
	 *
	 * @return array<string,mixed>
	 */
	private static function menu_tab(): array {
		return array(
			'id'     => 'menu',
			'label'  => 'Menu',
			'panels' => array(
				array(
					'id'     => 'menu',
					'title'  => 'Menu',
					'note'   => 'The navigation across the top of your site. Drag a row to move it.',
					'fields' => array(
						array(
							'id'     => 'menu',
							'kind'   => 'repeater',
							'label'  => 'Menu items',
							'fields' => array(
								array(
									'id'       => 'label',
									'kind'     => 'text',
									'label'    => 'Label',
									'required' => true,
								),
								array(
									'id'     => 'target',
									'kind'   => 'text',
									'format' => 'url',
									'label'  => 'Links to',
								),
								array(
									'id'    => 'nested',
									'kind'  => 'toggle',
									'label' => 'Show under the item above',
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * The club's own questions about a member.
	 *
	 * Removing a row takes the question off the form and leaves every answer
	 * already given alone. Erasing answers is a separate, deliberate act — a
	 * row that can be removed by accident must never be able to wipe member
	 * data (Profile_Store::forget()).
	 *
	 * @return array<string,mixed>
	 */
	private static function members_tab(): array {
		$types = array();
		foreach ( Blueworx_Clubhouse_Profile_Fields::TYPES as $value => $label ) {
			$types[] = array( 'value' => (string) $value, 'label' => (string) $label );
		}
		$who = array();
		foreach ( Blueworx_Clubhouse_Profile_Fields::WHO as $value => $label ) {
			$who[] = array( 'value' => (string) $value, 'label' => (string) $label );
		}

		return array(
			'id'     => 'members',
			'label'  => 'Members',
			'panels' => array(
				array(
					'id'     => 'profile_fields',
					'title'  => 'What you ask your members',
					'note'   => 'Your club\'s own questions, on top of name and email. Removing one takes it off the form and leaves answers already given alone.',
					'fields' => array(
						array(
							'id'         => 'profile_fields',
							'kind'       => 'repeater',
							'label'      => 'Member fields',
							'capability' => self::cap(),
							// The cells are Profile_Fields' own definition, read
							// from it rather than restated: the two drifting
							// apart is a club losing a field's type or who
							// fills it in, silently, on the next save.
							'fields'     => array(
								// The key is generated from the label once and
								// then never changes — it is what keeps a
								// renamed question attached to every answer
								// already given.
								//
								// The old screen carried it as a hidden input.
								// The library has no hidden cell, and its
								// server-side sanitiser rebuilds every row from
								// the declared cells alone, so a key that is not
								// a cell does not survive a save at all. Shown,
								// then — because the alternative is re-attaching
								// keys by row position, and the repeater can
								// reorder rows, which would hand one member's
								// answers to another field.
								array(
									'id'    => 'key',
									'kind'  => 'text',
									'label' => 'Reference',
									'help'  => 'Set once from the question, and used to store every answer to it. Leave it alone — changing it starts a new, empty field.',
								),
								array(
									'id'       => 'label',
									'kind'     => 'text',
									'label'    => 'Question',
									'required' => true,
								),
								array(
									'id'      => 'type',
									'kind'    => 'select',
									'label'   => 'Answer',
									'options' => $types,
								),
								array(
									'id'    => 'choices',
									'kind'  => 'textarea',
									'label' => 'Choices, one per line',
								),
								array(
									'id'      => 'who',
									'kind'    => 'select',
									'label'   => 'Who fills it in',
									'options' => $who,
								),
								array(
									'id'    => 'help',
									'kind'  => 'text',
									'label' => 'Hint under the question',
								),
								array(
									'id'    => 'required',
									'kind'  => 'toggle',
									'label' => 'Must be answered',
								),
								array(
									'id'    => 'column',
									'kind'  => 'toggle',
									'label' => 'Show the answers as a column',
								),
							),
						),
					),
				),
			),
		);
	}

	/** @return array<string,mixed> */
	private static function settings_tab(): array {
		return array(
			'id'     => 'settings',
			'label'  => 'Settings',
			'panels' => array(
				array(
					'id'     => 'after_sign_in',
					'title'  => 'After signing in',
					'note'   => 'Where a member lands when they sign in, and when they sign out. Leave either empty for the member area.',
					'fields' => array(
						array(
							'id'         => 'post_login',
							'kind'       => 'text',
							'label'      => 'After signing in',
							'capability' => self::cap(),
						),
						array(
							'id'         => 'post_logout',
							'kind'       => 'text',
							'label'      => 'After signing out',
							'capability' => self::cap(),
						),
					),
				),
				array(
					'id'     => 'emails',
					'title'  => 'Emails',
					'note'   => 'Who your site\'s email comes from. Leave both empty and it comes from your club\'s name, at your own domain.',
					'fields' => array(
						array(
							'id'         => 'mail_from_name',
							'kind'       => 'text',
							'label'      => 'From name',
							'capability' => self::cap(),
						),
						array(
							'id'         => 'mail_from_address',
							'kind'       => 'text',
							'format'     => 'email',
							'label'      => 'Reply-to address',
							'capability' => self::cap(),
						),
					),
				),
			),
		);
	}

	/** @return array<string,mixed> */
	private static function demo_tab(): array {
		return array(
			'id'     => 'demo',
			'label'  => 'Demo mode',
			'panels' => array(
				array(
					'id'     => 'demo',
					'title'  => 'Demo mode',
					'note'   => 'Fills the site with example content so it can be shown before a club has written anything of its own.',
					'fields' => array(
						array(
							'id'         => 'demo_active',
							'kind'       => 'toggle',
							'label'      => 'Demo mode on',
							'capability' => 'manage_options',
						),
					),
				),
			),
		);
	}
}
