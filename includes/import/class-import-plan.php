<?php
// includes/import/class-import-plan.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The validated, sanitised result of parsing an import file: what to write to
 * Content_Store, which collection items to reconcile, which images to fetch,
 * and what was dropped along the way. Pure and serialisable — the controller
 * stores to_array() in a transient between the preview and the apply step, so
 * the plan the owner approved is the exact plan that runs.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Import_Plan {

	/** @var array<string,array<string,array<string,mixed>>> page => section => field => value */
	private array $fields = array();

	/** @var array<string,array<string,array<int,array<string,mixed>>>> page => section => items */
	private array $items = array();

	/** @var array<int,array{page:string,section:string,field:string,url:string,alt:string,label:string,index:int}> */
	private array $images = array();

	/** @var array<string,array<int,array{title:string,meta:array<string,string>,images:array<string,array{url:string,alt:string}>}>> */
	private array $collections = array();

	/** @var array<int,string> */
	private array $warnings = array();

	public function add_field( string $page, string $section, string $field, mixed $value ): void {
		$this->fields[ $page ][ $section ][ $field ] = $value;
	}

	/** @param array<int,array<string,mixed>> $items */
	public function add_items( string $page, string $section, array $items ): void {
		$this->items[ $page ][ $section ] = array_values( $items );
	}

	/**
	 * Queue an image to fetch. $index is the loop-item position when the image
	 * belongs to a repeatable section's item (News articles carry one each), or
	 * -1 for a plain section field. The applier needs it to know whether to
	 * write the resulting attachment ID to a section field or into an item.
	 */
	public function add_image( string $page, string $section, string $field, string $url, string $alt, string $label, int $index = -1 ): void {
		$this->images[] = array(
			'page'    => $page,
			'section' => $section,
			'field'   => $field,
			'url'     => $url,
			'alt'     => $alt,
			'label'   => $label,
			'index'   => $index,
		);
	}

	/** @param array<int,array{title:string,meta:array<string,string>,images:array<string,array{url:string,alt:string}>}> $items */
	public function add_collection( string $type, array $items ): void {
		$this->collections[ $type ] = array_values( $items );
	}

	public function warn( string $message ): void {
		$this->warnings[] = $message;
	}

	/** @return array<string,array<string,array<string,mixed>>> */
	public function fields(): array {
		return $this->fields;
	}

	/** @return array<string,array<string,array<int,array<string,mixed>>>> */
	public function items(): array {
		return $this->items;
	}

	/** @return array<int,array{page:string,section:string,field:string,url:string,alt:string,label:string,index:int}> */
	public function images(): array {
		return $this->images;
	}

	/** @return array<string,array<int,array{title:string,meta:array<string,string>,images:array<string,array{url:string,alt:string}>}>> */
	public function collections(): array {
		return $this->collections;
	}

	/** @return array<int,string> */
	public function warnings(): array {
		return $this->warnings;
	}

	/**
	 * True when there is nothing to apply. Warnings alone do not count — a file
	 * that produced only warnings must be reported as "nothing to import"
	 * rather than offering an Apply button that would write nothing.
	 */
	public function is_empty(): bool {
		return array() === $this->fields
			&& array() === $this->items
			&& array() === $this->images
			&& array() === $this->collections;
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'fields'      => $this->fields,
			'items'       => $this->items,
			'images'      => $this->images,
			'collections' => $this->collections,
			'warnings'    => $this->warnings,
		);
	}

	/**
	 * Rehydrate a plan from to_array(). Defensive: a transient can be corrupted
	 * or hand-edited, and a malformed slot must degrade to empty rather than
	 * fatal on a later foreach.
	 *
	 * @param array<string,mixed> $a
	 */
	public static function from_array( array $a ): self {
		$plan              = new self();
		$plan->fields      = is_array( $a['fields'] ?? null ) ? $a['fields'] : array();
		$plan->items       = is_array( $a['items'] ?? null ) ? $a['items'] : array();
		$plan->images      = is_array( $a['images'] ?? null ) ? array_values( $a['images'] ) : array();
		$plan->collections = is_array( $a['collections'] ?? null ) ? $a['collections'] : array();
		$plan->warnings    = is_array( $a['warnings'] ?? null ) ? array_values( $a['warnings'] ) : array();
		return $plan;
	}
}
