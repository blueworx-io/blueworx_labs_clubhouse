<?php
// includes/collections/class-collection-meta-boxes.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress glue that puts the six collections' editable fields on their native
 * post-edit screens: a "Details" meta-box per CPT (rendered and saved through the
 * pure Collection_Meta), plus admin list columns. Nonce- and capability-checked on
 * save; every value sanitised through Collection_Meta and escaped on output.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Collection_Meta_Boxes {

	private const NONCE  = 'clubhouse_meta_save';
	private const PREFIX = 'clubhouse_';

	public static function register(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add' ) );
		add_action( 'save_post', array( self::class, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", static function ( $cols ) use ( $type ) {
				return self::merge_columns( $type, is_array( $cols ) ? $cols : array() );
			} );
			add_action( "manage_{$type}_posts_custom_column", static function ( $col, $post_id ) use ( $type ) {
				echo self::column_value( $type, (string) $col, (int) $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in column_value.
			}, 10, 2 );
		}
	}

	public static function add(): void {
		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			add_meta_box( 'clubhouse_meta_' . $type, 'Details', array( self::class, 'render' ), $type, 'normal', 'high' );
		}
	}

	public static function render( $post ): void {
		$type    = is_object( $post ) ? (string) $post->post_type : '';
		$post_id = is_object( $post ) ? (int) $post->ID : 0;
		echo self::box_html( $type, $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within box_html.
	}

	public static function box_html( string $type, int $post_id ): string {
		$html = wp_nonce_field( self::NONCE, '_clubhouse_meta_nonce', true, false );
		foreach ( Blueworx_Clubhouse_Collection_Meta::fields( $type ) as $field ) {
			$value = (string) get_post_meta( $post_id, $field['key'], true );
			$html .= self::field_html( $field, $value );
		}
		return '<div class="clubhouse-meta">' . $html . '</div>';
	}

	/** @param array{key:string,label:string,type:string,options?:array<int,string>} $field */
	private static function field_html( array $field, string $value ): string {
		$id    = 'clubhouse_meta_' . $field['key'];
		$name  = 'clubhouse_meta[' . $field['key'] . ']';
		$label = '<label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $field['label'] ) . '</strong></label>';

		switch ( $field['type'] ) {
			case 'textarea':
				$control = '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3" class="widefat">' . esc_textarea( $value ) . '</textarea>';
				break;
			case 'select':
				$options = '';
				foreach ( ( $field['options'] ?? array() ) as $opt ) {
					$options .= '<option value="' . esc_attr( $opt ) . '"' . selected( $value, $opt, false ) . '>' . esc_html( '' === $opt ? '—' : $opt ) . '</option>';
				}
				$control = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . $options . '</select>';
				break;
			case 'media':
				$preview = ( '' !== $value && ctype_digit( $value ) ) ? (string) wp_get_attachment_image_url( (int) $value, 'thumbnail' ) : '';
				$hidden  = '' === $preview ? ' style="display:none"' : '';
				$control = '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">'
					. '<img class="clubhouse-meta__preview" src="' . esc_url( $preview ) . '" alt=""' . $hidden . '>'
					. '<button type="button" class="button clubhouse-meta__pick" data-target="' . esc_attr( $id ) . '">Choose image</button> '
					. '<button type="button" class="button clubhouse-meta__clear" data-target="' . esc_attr( $id ) . '">Remove</button>';
				break;
			case 'date':
			case 'time':
			case 'email':
				$control = '<input type="' . esc_attr( $field['type'] ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="widefat">';
				break;
			case 'url':
				$control = '<input type="url" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="widefat">';
				break;
			case 'text':
			case 'href':
			default:
				$control = '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="widefat">';
		}
		return '<p class="clubhouse-meta__row">' . $label . '<br>' . $control . '</p>';
	}

	public static function save( int $post_id, $post ): void {
		if ( ! isset( $_POST['_clubhouse_meta_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_clubhouse_meta_nonce'] ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		$type = is_object( $post ) ? (string) $post->post_type : '';
		if ( ! in_array( $type, Blueworx_Clubhouse_Collection_Meta::types(), true ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw = ( isset( $_POST['clubhouse_meta'] ) && is_array( $_POST['clubhouse_meta'] ) ) ? wp_unslash( $_POST['clubhouse_meta'] ) : array();
		foreach ( Blueworx_Clubhouse_Collection_Meta::fields( $type ) as $field ) {
			$value = isset( $raw[ $field['key'] ] ) ? (string) $raw[ $field['key'] ] : '';
			update_post_meta( $post_id, $field['key'], Blueworx_Clubhouse_Collection_Meta::sanitise( $type, $field['key'], $value ) );
		}
	}

	/** @param array<string,string> $cols @return array<string,string> */
	public static function merge_columns( string $type, array $cols ): array {
		$out = array();
		if ( isset( $cols['cb'] ) ) {
			$out['cb'] = $cols['cb'];
		}
		if ( isset( $cols['title'] ) ) {
			$out['title'] = $cols['title'];
		}
		foreach ( Blueworx_Clubhouse_Collection_Meta::columns( $type ) as $key => $col_label ) {
			$out[ self::PREFIX . $key ] = $col_label;
		}
		if ( isset( $cols['date'] ) ) {
			$out['date'] = $cols['date'];
		}
		return $out;
	}

	public static function column_value( string $type, string $col, int $post_id ): string {
		if ( 0 !== strpos( $col, self::PREFIX ) ) {
			return '';
		}
		$key = substr( $col, strlen( self::PREFIX ) );
		if ( 'clubhouse_fixture' === $type && 'matchup' === $key ) {
			return self::e( (string) get_post_meta( $post_id, 'home_team', true ) . ' v ' . (string) get_post_meta( $post_id, 'away_team', true ) );
		}
		if ( 'clubhouse_fixture' === $type && 'result' === $key ) {
			$score   = (string) get_post_meta( $post_id, 'score', true );
			$outcome = (string) get_post_meta( $post_id, 'outcome', true );
			return self::e( trim( $score . ( '' !== $outcome ? ' (' . $outcome . ')' : '' ) ) );
		}
		return self::e( (string) get_post_meta( $post_id, $key, true ) );
	}

	public static function enqueue( string $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'clubhouse-admin-collections', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/admin-collections.js', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
	}

	private static function e( string $s ): string {
		return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' );
	}
}
