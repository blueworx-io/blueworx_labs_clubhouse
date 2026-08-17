<?php
// includes/admin/class-blocks-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress glue for Content → Blocks: the club's library of blocks, and the
 * form that edits one. Menu mounting, capability and nonce checks, sanitising
 * and persistence live here; Blocks_Screen only draws.
 *
 * Editing a block edits every page showing it. That is the point of the
 * library, and the reason two things here are not conveniences: the form says
 * which pages it is about to change before an owner types, and deleting a block
 * that is in use asks first and names them.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Blocks_Controller {

	public const CAPABILITY = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP;
	public const PAGE_SLUG  = 'clubhouse-blocks';
	public const NONCE      = 'clubhouse_blocks_save';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function add_menu(): void {
		add_submenu_page(
			Blueworx_Clubhouse_Pages_Controller::PAGE_SLUG,
			'Blocks',
			'Blocks',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);
	}

	public static function enqueue( string $hook ): void {
		if ( ! str_contains( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		// The media library, for a block with an image field — the same picker
		// Club Pages uses, driven by the same script.
		wp_enqueue_media();
		wp_enqueue_style( 'clubhouse-admin-setup', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/css/admin-setup.css', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION );
		wp_enqueue_script( 'clubhouse-admin-setup', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/admin-setup.js', array( 'jquery' ), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$storage = new Blueworx_Clubhouse_Options_Storage();
		$result  = array( 'notices' => array(), 'block' => '', 'confirm' => null );

		if ( isset( $_POST['clubhouse_blocks_submit'] ) ) {
			check_admin_referer( self::NONCE );
			$result = self::handle_post( wp_unslash( $_POST ), $storage ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- handle_post sanitises every value against the block's own field list.
		}

		$block = '' !== (string) $result['block']
			? (string) $result['block']
			: ( isset( $_GET['block'] ) ? sanitize_key( (string) wp_unslash( $_GET['block'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing which block to look at changes nothing.

		$model = self::build_model(
			$storage,
			$result['notices'],
			wp_nonce_field( self::NONCE, '_wpnonce', true, false ),
			admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
			$block,
			$result['confirm']
		);
		$model['pages_url'] = admin_url( 'admin.php?page=' . Blueworx_Clubhouse_Pages_Controller::PAGE_SLUG );
		$model['role_tags'] = Blueworx_Clubhouse_Access_Controller::role_tags_for( self::PAGE_SLUG );

		echo Blueworx_Clubhouse_Blocks_Screen::render( $model ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within Blocks_Screen.
	}

	// -- Deciding -------------------------------------------------------------

	/**
	 * Apply one POST from this screen.
	 *
	 * @param array<string,mixed> $post
	 * @return array{notices:array<int,array{type:string,text:string}>,block:string,confirm:?array{id:string,name:string,used_on:array<int,string>}}
	 */
	public static function handle_post( array $post, Blueworx_Clubhouse_Storage $storage ): array {
		$library     = new Blueworx_Clubhouse_Block_Library( $storage );
		$composition = new Blueworx_Clubhouse_Page_Composition( $storage );

		$new = (string) ( $post['clubhouse_blocks_new'] ?? '' );
		if ( '' !== $new ) {
			return self::make( $new, $library );
		}

		$id    = (string) ( $post['clubhouse_blocks_block'] ?? '' );
		$block = $library->get( $id );
		if ( null === $block ) {
			return self::done( array(), '' );
		}

		if ( isset( $post['clubhouse_blocks_delete'] ) ) {
			return self::delete( $block, '' !== (string) ( $post['clubhouse_blocks_confirm'] ?? '' ), $library, $composition );
		}
		if ( isset( $post['clubhouse_blocks_duplicate'] ) ) {
			$copy = $library->duplicate( $id, self::copy_name( (string) $block['name'], $library ) );
			return self::done(
				array( self::notice( sprintf( '"%s" is a copy of "%s", with the same words. Change either one and the other stays as it is.', $library->get( $copy )['name'], (string) $block['name'] ) ) ),
				$copy
			);
		}

		$notices = array();

		$name = trim( (string) ( $post['clubhouse_blocks_name'] ?? '' ) );
		if ( '' !== $name && $name !== (string) $block['name'] ) {
			$library->rename( $id, $name );
			$notices[] = self::notice( sprintf( 'Renamed to "%s". The pages using it are unchanged.', $name ) );
		}

		$content = self::content_from( $post, $block );

		// Adding or removing a repeated item is a change to the form rather than a
		// save the owner asked for, so it is applied to what they have typed and
		// handed straight back — nothing is lost by pressing "Add question".
		if ( isset( $post['clubhouse_blocks_add_item'] ) ) {
			$content['items'][] = array();
		}
		$remove = (string) ( $post['clubhouse_blocks_remove_item'] ?? '' );
		if ( '' !== $remove && isset( $content['items'][ (int) $remove ] ) ) {
			unset( $content['items'][ (int) $remove ] );
			$content['items'] = array_values( $content['items'] );
		}

		$library->set_content( $id, $content );

		if ( array() === $notices ) {
			$used      = $composition->uses( $id );
			$notices[] = self::notice(
				count( $used ) > 1
					? sprintf( 'Saved. This block shows on %s, and all of them have changed.', self::sentence( self::labels( $used ) ) )
					: 'Saved.'
			);
		}

		return self::done( $notices, $id );
	}

	/**
	 * @return array{notices:array<int,array{type:string,text:string}>,block:string,confirm:?array{id:string,name:string,used_on:array<int,string>}}
	 */
	private static function make( string $type, Blueworx_Clubhouse_Block_Library $library ): array {
		$definition = Blueworx_Clubhouse_Block_Types::get( $type );
		if ( null === $definition || $definition['singleton'] ) {
			return self::done( array(), '' );
		}
		$id = $library->add( $type, 'New ' . strtolower( $definition['label'] ) );
		return self::done(
			array( self::notice( 'Made. Give it a name and its words, then put it on a page under Content → Pages.' ) ),
			$id
		);
	}

	/**
	 * Delete a block, asking first when a page is using it.
	 *
	 * @param array<string,mixed> $block
	 * @return array{notices:array<int,array{type:string,text:string}>,block:string,confirm:?array{id:string,name:string,used_on:array<int,string>}}
	 */
	private static function delete(
		array $block,
		bool $confirmed,
		Blueworx_Clubhouse_Block_Library $library,
		Blueworx_Clubhouse_Page_Composition $composition
	): array {
		$id   = (string) $block['id'];
		$used = self::labels( $composition->uses( $id ) );

		if ( array() !== $used && ! $confirmed ) {
			return array(
				'notices' => array(),
				'block'   => $id,
				'confirm' => array( 'id' => $id, 'name' => (string) $block['name'], 'used_on' => $used ),
			);
		}

		foreach ( $composition->uses( $id ) as $page ) {
			$composition->remove( $page, $id );
		}
		$library->delete( $id );

		return self::done(
			array( self::notice( sprintf( '"%s" is deleted%s.', (string) $block['name'], array() === $used ? '' : ', and off ' . self::sentence( $used ) ) ) ),
			''
		);
	}

	/**
	 * The block's content as this POST leaves it, sanitised against its own
	 * field list — an unknown key never reaches storage, whatever the form said.
	 *
	 * @param array<string,mixed> $post
	 * @param array<string,mixed> $block
	 * @return array<string,mixed>
	 */
	private static function content_from( array $post, array $block ): array {
		$shape   = Blueworx_Clubhouse_Block_Fields::for_block( $block );
		$raw     = is_array( $post['field'] ?? null ) ? (array) $post['field'] : array();
		$content = (array) $block['content'];

		foreach ( $shape['fields'] as $field ) {
			$key = (string) $field['key'];
			$content[ $key ] = Blueworx_Clubhouse_Content_Sanitiser::field(
				$field,
				$raw[ $key ] ?? null,
				array_key_exists( $key, $raw )
			);
		}

		if ( null !== $shape['loop'] ) {
			$items            = is_array( $post['item'] ?? null ) ? array_values( (array) $post['item'] ) : array();
			$content['items'] = Blueworx_Clubhouse_Content_Sanitiser::items( $shape['loop']['fields'], $items );
		}

		return $content;
	}

	/** A copy's name: "Home hero copy", then "Home hero copy 2" and so on. */
	private static function copy_name( string $name, Blueworx_Clubhouse_Block_Library $library ): string {
		$taken = array();
		foreach ( $library->all() as $block ) {
			$taken[] = (string) $block['name'];
		}
		$candidate = $name . ' copy';
		$n         = 1;
		while ( in_array( $candidate, $taken, true ) ) {
			++$n;
			$candidate = $name . ' copy ' . $n;
		}
		return $candidate;
	}

	/** @return array{notices:array<int,array{type:string,text:string}>,block:string,confirm:null} */
	private static function done( array $notices, string $block ): array {
		return array( 'notices' => $notices, 'block' => $block, 'confirm' => null );
	}

	/** @return array{type:string,text:string} */
	private static function notice( string $text ): array {
		return array( 'type' => 'success', 'text' => $text );
	}

	// -- The model ------------------------------------------------------------

	/**
	 * @param array<int,array{type:string,text:string}>                     $notices
	 * @param array{id:string,name:string,used_on:array<int,string>}|null   $confirm
	 * @return array<string,mixed>
	 */
	public static function build_model(
		Blueworx_Clubhouse_Storage $storage,
		array $notices,
		string $nonce_field,
		string $action_url,
		string $block_id = '',
		?array $confirm = null
	): array {
		$library     = new Blueworx_Clubhouse_Block_Library( $storage );
		$composition = new Blueworx_Clubhouse_Page_Composition( $storage );

		$registry   = Blueworx_Clubhouse_Frontend::registry( $storage );
		$branding   = new Blueworx_Clubhouse_Branding( $storage );
		$look       = $registry->active();
		$plugin_url = defined( 'BLUEWORX_LABS_CLUBHOUSE_URL' ) ? BLUEWORX_LABS_CLUBHOUSE_URL : '';

		$blocks = $library->all();
		if ( '' === $block_id || ! isset( $blocks[ $block_id ] ) ) {
			$block_id = '';
		}

		return array(
			'groups'         => self::groups( $blocks, $composition, $block_id, $action_url ),
			'current'        => '' === $block_id ? null : self::current( $blocks[ $block_id ], $composition ),
			'new_types'      => self::new_types(),
			'confirm'        => $confirm,
			'notices'        => $notices,
			'nonce_field'    => $nonce_field,
			'action_url'     => $action_url,
			'pages_url'      => '',
			'menu_targets'   => Blueworx_Clubhouse_Link_Catalogue::targets( new Blueworx_Clubhouse_WP_Collections() ),
			'tokens'         => Blueworx_Clubhouse_Theme_Css::compose( $look, $branding ),
			'font_face_css'  => Blueworx_Clubhouse_Page_Renderer::font_face_css( $look, $plugin_url ),
			'role_tags'      => '',
		);
	}

	/**
	 * The library, grouped by kind and in the order the types are declared, so
	 * the list reads down a page the way a page reads down a screen.
	 *
	 * @param array<string,array<string,mixed>> $blocks
	 * @return array<int,array{type:string,label:string,blocks:array<int,array{id:string,name:string,used_on:array<int,string>,singleton:bool,current:bool,url:string}>}>
	 */
	private static function groups(
		array $blocks,
		Blueworx_Clubhouse_Page_Composition $composition,
		string $current,
		string $action_url
	): array {
		$groups = array();
		foreach ( Blueworx_Clubhouse_Block_Types::all() as $key => $type ) {
			$rows = array();
			foreach ( $blocks as $id => $block ) {
				if ( (string) $block['type'] !== (string) $key ) {
					continue;
				}
				$rows[] = array(
					'id'        => (string) $id,
					'name'      => (string) $block['name'],
					'used_on'   => self::labels( $composition->uses( (string) $id ) ),
					'singleton' => (bool) $type['singleton'],
					'current'   => (string) $id === $current,
					'url'       => $action_url . '&block=' . rawurlencode( (string) $id ),
				);
			}
			if ( array() === $rows ) {
				continue;
			}
			$groups[] = array( 'type' => (string) $key, 'label' => (string) $type['label'], 'blocks' => $rows );
		}
		return $groups;
	}

	/**
	 * The block being edited: what it is, what it says, and which pages this
	 * form is about to change.
	 *
	 * @param array<string,mixed> $block
	 * @return array<string,mixed>
	 */
	private static function current( array $block, Blueworx_Clubhouse_Page_Composition $composition ): array {
		$type  = Blueworx_Clubhouse_Block_Types::get( (string) $block['type'] );
		$shape = Blueworx_Clubhouse_Block_Fields::for_block( $block );

		return array(
			'id'         => (string) $block['id'],
			'name'       => (string) $block['name'],
			'type'       => (string) $block['type'],
			'type_label' => null === $type ? (string) $block['type'] : (string) $type['label'],
			'singleton'  => null !== $type && $type['singleton'],
			'used_on'    => self::labels( $composition->uses( (string) $block['id'] ) ),
			'fields'     => $shape['fields'],
			'loop'       => $shape['loop'],
			'content'    => (array) $block['content'],
		);
	}

	/**
	 * The kinds of block an owner can make from here.
	 *
	 * @return array<int,array{type:string,label:string}>
	 */
	private static function new_types(): array {
		$types = array();
		foreach ( Blueworx_Clubhouse_Block_Types::all() as $key => $type ) {
			if ( $type['singleton'] ) {
				continue;
			}
			if ( '' !== $type['requires'] && ! Blueworx_Clubhouse_Integrations::provides( $type['requires'] ) ) {
				continue;
			}
			$types[] = array( 'type' => (string) $key, 'label' => (string) $type['label'] );
		}
		return $types;
	}

	/**
	 * Page keys as a club reads them.
	 *
	 * @param array<int,string> $pages
	 * @return array<int,string>
	 */
	private static function labels( array $pages ): array {
		$labels = array();
		foreach ( Blueworx_Clubhouse_Page_Map::available() as $entry ) {
			$key = '' === (string) $entry['slug'] ? 'home' : (string) $entry['slug'];
			if ( in_array( $key, $pages, true ) ) {
				$labels[] = (string) $entry['label'];
			}
		}
		return $labels;
	}

	/** "Home, About and Membership" — a list a person can read aloud. */
	private static function sentence( array $labels ): string {
		if ( count( $labels ) < 2 ) {
			return implode( '', $labels );
		}
		$last = array_pop( $labels );
		return implode( ', ', $labels ) . ' and ' . $last;
	}
}
