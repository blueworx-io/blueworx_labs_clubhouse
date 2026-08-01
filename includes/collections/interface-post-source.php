<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read model for club news — native WordPress posts, projected into the shape
 * the news templates draw.
 *
 * Native posts rather than a custom type, deliberately: a club that already
 * writes news in WordPress keeps everything it has written, everything that
 * already understands posts (the editor, the RSS feed, search, an app that
 * pulls the site's feed) keeps working, and nobody has to be taught a second
 * place to write.
 *
 * The pure Demo implementation serves the preview and the tests; the WordPress
 * implementation reads real posts.
 *
 * @package BlueworxLabsClubhouse
 */
interface Blueworx_Clubhouse_Post_Source {

	/**
	 * A page of posts, newest first.
	 *
	 * @param string $category Category slug to narrow to, '' for all.
	 * @return array<int,array{id:int,title:string,href:string,excerpt:string,category:string,category_slug:string,date:string,read:string,image:string,image_alt:string}>
	 */
	public function recent( int $limit, int $offset = 0, string $category = '' ): array;

	/** How many posts match, ignoring paging — what the pager and the count label need. */
	public function count( string $category = '' ): int;

	/**
	 * The categories that actually carry a post. A category nobody has written in
	 * is not offered as a filter, because filtering to it would show an empty page.
	 *
	 * @return array<int,array{label:string,slug:string}>
	 */
	public function categories(): array;

	/**
	 * The post being viewed, or null when this is not a single post.
	 *
	 * @return array{id:int,title:string,href:string,standfirst:string,html:string,category:string,category_slug:string,date:string,read:string,image:string,image_alt:string,image_caption:string,tags:array<int,string>,author:array{name:string,role:string,initials:string,bio:string}}|null
	 */
	public function current(): ?array;

	/**
	 * Posts to read next — same category first, then anything recent, never the
	 * post being read.
	 *
	 * @return array<int,array{id:int,title:string,href:string,excerpt:string,category:string,category_slug:string,date:string,read:string,image:string,image_alt:string}>
	 */
	public function related( int $limit ): array;
}
