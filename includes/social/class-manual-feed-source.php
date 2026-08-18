<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stage one's source: the post links a club has pasted on Club Pages, turned
 * into the same normalised posts the Meta source will later return. No network
 * call, so it can never fail — it returns array() when nothing is pasted, which
 * the cache reads as "not connected yet".
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Manual_Feed_Source implements Blueworx_Clubhouse_Feed_Source {

	private Blueworx_Clubhouse_Content_Store $content;

	public function __construct( Blueworx_Clubhouse_Content_Store $content ) {
		$this->content = $content;
	}

	/** @return array<int,array{id:string,image:string,caption:string,date:string,permalink:string}> */
	public function posts(): ?array {
		$posts = array();
		foreach ( $this->content->get_items( 'home', 'social_feed' ) as $item ) {
			$post = self::normalise( is_array( $item ) ? $item : array() );
			if ( null !== $post ) {
				$posts[] = $post;
			}
		}
		return $posts;
	}

	/**
	 * One pasted row as a normalised post, or null when there is no usable link
	 * — a half-filled row is dropped rather than rendered as a card leading
	 * nowhere.
	 *
	 * Only http(s) links survive: the value reaches the renderer as an href, and
	 * a javascript: or data: link pasted into the admin must never become one.
	 * The id is derived from the link so the same post keeps the same render key
	 * across saves.
	 *
	 * @param array<string,mixed> $item
	 * @return array{id:string,image:string,caption:string,date:string,permalink:string}|null
	 */
	public static function normalise( array $item ): ?array {
		$permalink = trim( (string) ( $item['href'] ?? '' ) );
		if ( '' === $permalink || 1 !== preg_match( '#^https?://#i', $permalink ) ) {
			return null;
		}
		return array(
			'id'        => 'manual-' . md5( $permalink ),
			'image'     => '',
			'caption'   => trim( (string) ( $item['caption'] ?? '' ) ),
			'date'      => '',
			'permalink' => $permalink,
		);
	}
}
