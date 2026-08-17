<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The things a page works out once and several of its blocks then read.
 *
 * The old page methods computed these at the top of the method and let the
 * sections below close over them: the membership tiers and whether any of them
 * can actually take money, the filter pills and the rows they narrow, the news
 * query and which post was lifted out as the lead story. A render loop has no
 * such shared scope, so it goes here instead — computed once per page, from the
 * blocks that page is composed of, and handed to every block that needs it.
 *
 * Lazy throughout: a page with no tier grid never asks the shop for a price,
 * and a page with no news block never runs a post query.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_State {

	/** @var array<int,array<string,mixed>> The page's blocks, in render order. */
	private array $blocks;

	private string $page;
	private Blueworx_Clubhouse_Branding $branding;
	private Blueworx_Clubhouse_Visibility $visibility;
	private Blueworx_Clubhouse_Collections $collections;
	private Blueworx_Clubhouse_Block_Library $library;
	private string $requested_filter;

	/** @var array<string,mixed> Memoised answers, one entry per question asked. */
	private array $memo = array();

	/**
	 * @param array<int,array<string,mixed>> $blocks Resolved blocks, in render order.
	 */
	public function __construct(
		string $page,
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		Blueworx_Clubhouse_Block_Library $library,
		array $blocks,
		string $filter = ''
	) {
		$this->page             = $page;
		$this->branding         = $branding;
		$this->visibility       = $visibility;
		$this->collections      = $collections;
		$this->library          = $library;
		$this->blocks           = $blocks;
		$this->requested_filter = $filter;
	}

	public function page(): string {
		return $this->page;
	}

	public function club(): string {
		return $this->branding->get_club_name();
	}

	public function branding(): Blueworx_Clubhouse_Branding {
		return $this->branding;
	}

	public function visibility(): Blueworx_Clubhouse_Visibility {
		return $this->visibility;
	}

	public function collections(): Blueworx_Clubhouse_Collections {
		return $this->collections;
	}

	/** True when this page shows a block of the given type. */
	public function has_type( string $type ): bool {
		foreach ( $this->blocks as $block ) {
			if ( (string) ( $block['type'] ?? '' ) === $type ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The first block on this page of the given type, or null.
	 *
	 * @return array<string,mixed>|null
	 */
	public function block_of_type( string $type ): ?array {
		foreach ( $this->blocks as $block ) {
			if ( (string) ( $block['type'] ?? '' ) === $type ) {
				return $block;
			}
		}
		return null;
	}

	/** @param callable():mixed $compute */
	private function once( string $key, callable $compute ): mixed {
		if ( ! array_key_exists( $key, $this->memo ) ) {
			$this->memo[ $key ] = $compute();
		}
		return $this->memo[ $key ];
	}

	// -- Membership tiers -----------------------------------------------------

	/**
	 * The club's membership tiers, priced by the shop wherever a tier names a
	 * real price. One source for both the Membership page and the Home teaser,
	 * which is what makes editing the tiers once change both.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function tiers(): array {
		/** @var array<int,array<string,mixed>> */
		return $this->once(
			'tiers',
			function (): array {
				$block = $this->tier_source();
				$items = null === $block
					? Blueworx_Clubhouse_Block_Defaults::tier_items()
					: Blueworx_Clubhouse_Block_Content::items(
						(array) ( $block['content'] ?? array() ),
						Blueworx_Clubhouse_Block_Defaults::for_key( (string) ( $block['defaults_key'] ?? '' ), $this )
					);
				return array_map(
					static fn( array $tier ): array => Blueworx_Clubhouse_Block_Defaults::price_tier( $tier ),
					$items
				);
			}
		);
	}

	/**
	 * The block whose content the tiers come from. A tier grid may mirror
	 * another block — Home's grid shows the Membership page's tiers rather than
	 * a copy of them — so the mirror is followed here, in one place.
	 *
	 * @return array<string,mixed>|null
	 */
	private function tier_source(): ?array {
		$block = $this->block_of_type( 'tier_grid' );
		if ( null === $block ) {
			return null;
		}
		$mirror = (string) ( ( $block['settings'] ?? array() )['mirror'] ?? '' );
		if ( '' === $mirror ) {
			return $block;
		}
		return $this->library->get( $mirror ) ?? $block;
	}

	/** Whether this page can actually take money — at least one tier reaching checkout. */
	public function tiers_sell(): bool {
		/** @var bool */
		return $this->once(
			'sells',
			fn(): bool => Blueworx_Clubhouse_Page_Renderer::tiers_sell( $this->tiers(), Blueworx_Clubhouse_Checkout::base_url() )
		);
	}

	/** The in-page link to the tier grid, used by the Membership page's own buttons. */
	public function tiers_anchor(): string {
		return '#' . Blueworx_Clubhouse_Link_Catalogue::anchor_id( 'membership', 'tiers' );
	}

	// -- Filters --------------------------------------------------------------

	/**
	 * The rows this page's pills are built from, and the field each pill reads.
	 *
	 * @return array{rows:array<int,array<string,mixed>>,pick:callable(array<string,mixed>):string}|null
	 */
	private function filter_source(): ?array {
		switch ( $this->page ) {
			case 'sports':
				return array(
					'rows' => $this->collections->sports(),
					'pick' => static fn( array $s ): string => (string) $s['title'],
				);
			case 'teams':
				return array(
					'rows' => $this->collections->teams(),
					'pick' => static fn( array $t ): string => (string) $t['sport'],
				);
			case 'events':
				return array(
					'rows' => $this->collections->events(),
					'pick' => static fn( array $e ): string => (string) $e['tag'],
				);
			case 'calendar':
				// A fixture names its sport as "Rugby · 1st XV"; the pill filters on
				// the part before the separator.
				return array(
					'rows' => $this->collections->fixtures(),
					'pick' => static fn( array $f ): string => trim( explode( '·', (string) $f['sport'] )[0] ),
				);
		}
		return null;
	}

	/**
	 * Distinct, non-empty labels in first-seen order.
	 *
	 * @return array<int,string>
	 */
	public function filter_labels(): array {
		/** @var array<int,string> */
		return $this->once(
			'labels',
			function (): array {
				$source = $this->filter_source();
				if ( null === $source ) {
					return array();
				}
				$out = array();
				foreach ( $source['rows'] as $row ) {
					$value = trim( ( $source['pick'] )( $row ) );
					if ( '' !== $value && ! in_array( $value, $out, true ) ) {
						$out[] = $value;
					}
				}
				return $out;
			}
		);
	}

	/**
	 * The filter actually in force: the requested one when it names a label this
	 * page has, otherwise "All". Guards a stale or hand-typed parameter from
	 * showing an empty page.
	 */
	public function filter(): string {
		/** @var string */
		return $this->once(
			'filter',
			function (): string {
				if ( '' === $this->requested_filter ) {
					return '';
				}
				foreach ( $this->filter_labels() as $label ) {
					if ( Blueworx_Clubhouse_Page_Renderer::slugify( $label ) === $this->requested_filter ) {
						return $this->requested_filter;
					}
				}
				return '';
			}
		);
	}

	/**
	 * "All" plus one pill per label, each linking to this page with its slug and
	 * the one in force marked active.
	 *
	 * @return array<int,array{label:string,href:string,active:bool}>
	 */
	public function filter_pills(): array {
		$current = $this->filter();
		$pills   = array(
			array(
				'label'  => 'All',
				'href'   => Blueworx_Clubhouse_Links::url( $this->page ),
				'active' => '' === $current,
			),
		);
		foreach ( $this->filter_labels() as $label ) {
			$slug    = Blueworx_Clubhouse_Page_Renderer::slugify( $label );
			$pills[] = array(
				'label'  => $label,
				'href'   => Blueworx_Clubhouse_Links::filtered_url( $this->page, $slug ),
				'active' => $slug === $current,
			);
		}
		return $pills;
	}

	/**
	 * This page's rows, narrowed to the filter in force.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function filtered_rows(): array {
		/** @var array<int,array<string,mixed>> */
		return $this->once(
			'rows',
			function (): array {
				$source = $this->filter_source();
				if ( null === $source ) {
					return array();
				}
				$current = $this->filter();
				if ( '' === $current ) {
					return $source['rows'];
				}
				return array_values(
					array_filter(
						$source['rows'],
						static fn( array $row ): bool => Blueworx_Clubhouse_Page_Renderer::slugify( ( $source['pick'] )( $row ) ) === $current
					)
				);
			}
		);
	}

	// -- News -----------------------------------------------------------------

	/**
	 * Everything the news index needs, worked out in one pass so the lead story
	 * and the grid below it cannot disagree about which posts they hold.
	 *
	 * The lead story is only lifted out on the unfiltered first page, and only
	 * when the page actually shows a featured block — on page two, or inside a
	 * category, "featured" would just mean "whichever post happens to be first"
	 * and the same story would appear twice.
	 *
	 * @return array{categories:array<int,array{label:string,slug:string}>,filter:string,total:int,paging:array{page:int,pages:int,offset:int},posts:array<int,array<string,mixed>>,featured:array<string,mixed>|null}
	 */
	public function news(): array {
		/** @var array{categories:array<int,array{label:string,slug:string}>,filter:string,total:int,paging:array{page:int,pages:int,offset:int},posts:array<int,array<string,mixed>>,featured:array<string,mixed>|null} */
		return $this->once(
			'news',
			function (): array {
				$source     = Blueworx_Clubhouse_News::source();
				$categories = null !== $source ? $source->categories() : array();
				// An unknown category is not an error — it is a stale bookmark or a
				// renamed category. Fall back to everything.
				$known  = array_column( $categories, 'slug' );
				$filter = in_array( $this->requested_filter, $known, true ) ? $this->requested_filter : '';

				$total  = null !== $source ? $source->count( $filter ) : 0;
				$paging = Blueworx_Clubhouse_News::paging( $total, Blueworx_Clubhouse_News::requested_page() );
				$posts  = null !== $source
					? $source->recent( Blueworx_Clubhouse_News::PER_PAGE, $paging['offset'], $filter )
					: array();

				$featured = null;
				if ( '' === $filter && 1 === $paging['page'] && array() !== $posts && $this->has_type( 'news_featured' ) ) {
					$featured = array_shift( $posts );
				}

				return array(
					'categories' => $categories,
					'filter'     => $filter,
					'total'      => $total,
					'paging'     => $paging,
					'posts'      => $posts,
					'featured'   => $featured,
				);
			}
		);
	}
}
