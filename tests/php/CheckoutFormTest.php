<?php

use PHPUnit\Framework\TestCase;

/**
 * The checkout form this plugin hands SureCart to seed.
 *
 * Every block name asserted here is read from SureCart 4.6.4's own
 * packages/blocks/Blocks — see docs/integrations/surecart-notes.md. A block
 * SureCart does not have renders as nothing, so these assertions are the only
 * thing standing between a rename and a silently missing field.
 */
final class CheckoutFormTest extends TestCase {

	public function test_the_form_carries_every_field_a_purchase_needs(): void {
		$content = Blueworx_Clubhouse_Checkout_Form::content();
		foreach ( array(
			'wp:surecart/checkout-errors',
			'wp:surecart/email',
			'wp:surecart/name',
			'wp:surecart/payment',
			'wp:surecart/submit',
			'wp:surecart/totals',
			'wp:surecart/line-items',
			'wp:surecart/coupon',
			'wp:surecart/subtotal',
			'wp:surecart/total',
		) as $block ) {
			$this->assertStringContainsString( $block, $content, $block . ' is missing from the form' );
		}
	}

	public function test_the_form_is_two_columns(): void {
		// The approved design is fields on the left, the order summary on the
		// right. Those are SureCart's own column blocks rather than anything
		// the page frame draws — the frame gets the content as one string.
		$content = Blueworx_Clubhouse_Checkout_Form::content();
		$this->assertStringContainsString( 'wp:surecart/columns', $content );
		$this->assertSame( 2, substr_count( $content, '<!-- wp:surecart/column ' ) );
	}

	public function test_the_address_only_appears_when_something_ships(): void {
		// A membership is not posted anywhere. Asking a member for their
		// address to buy one is a question with no purpose.
		$content = Blueworx_Clubhouse_Checkout_Form::content();
		$this->assertStringContainsString( 'wp:surecart/conditional-form', $content );
		$this->assertStringContainsString( 'wp:surecart/address', $content );
	}

	public function test_the_form_wears_the_member_areas_classes(): void {
		$this->assertStringContainsString( 'bw-card', Blueworx_Clubhouse_Checkout_Form::content() );
	}

	public function test_the_filter_supplies_our_content_for_the_checkout_form(): void {
		$out = Blueworx_Clubhouse_Checkout_Form::filter_forms(
			array(
				'checkout' => array(
					'name'      => 'checkout',
					'title'     => 'Checkout',
					'content'   => 'SURECART DEFAULT',
					'post_type' => 'sc_form',
				),
			)
		);
		$this->assertIsArray( $out );
		$this->assertStringContainsString( 'wp:surecart/email', (string) $out['checkout']['content'] );
		$this->assertStringNotContainsString( 'SURECART DEFAULT', (string) $out['checkout']['content'] );
	}

	public function test_the_filter_leaves_surecarts_other_keys_alone(): void {
		// SureCart wraps our content in its own form block and keys the post
		// off name, title and post_type. Rewriting any of them would produce a
		// form SureCart cannot find again.
		$out = Blueworx_Clubhouse_Checkout_Form::filter_forms(
			array(
				'checkout' => array(
					'name'      => 'checkout',
					'title'     => 'Checkout',
					'content'   => 'SURECART DEFAULT',
					'post_type' => 'sc_form',
				),
				'other'    => array( 'name' => 'other', 'content' => 'LEAVE ME' ),
			)
		);
		$this->assertSame( 'checkout', $out['checkout']['name'] );
		$this->assertSame( 'Checkout', $out['checkout']['title'] );
		$this->assertSame( 'sc_form', $out['checkout']['post_type'] );
		$this->assertSame( 'LEAVE ME', $out['other']['content'] );
	}

	public function test_an_unrecognised_shape_is_handed_straight_back(): void {
		// SureCart applies this filter inside its own seeder. If a future
		// version passes something else, returning it untouched leaves the
		// club with SureCart's default form rather than no checkout at all.
		$this->assertSame( 'not an array', Blueworx_Clubhouse_Checkout_Form::filter_forms( 'not an array' ) );
		$this->assertSame( array(), Blueworx_Clubhouse_Checkout_Form::filter_forms( array() ) );
		$this->assertSame(
			array( 'checkout' => 'not an array either' ),
			Blueworx_Clubhouse_Checkout_Form::filter_forms( array( 'checkout' => 'not an array either' ) )
		);
	}

	public function test_register_attaches_the_filter(): void {
		// A filter that is never added is the exact failure mode that made the
		// whole SureCart integration unreachable once before — see the note on
		// SureCart_Products::is_active(). Assert the wiring, not just the value.
		wp_stub_reset();
		Blueworx_Clubhouse_Checkout_Form::register();
		$this->assertTrue( has_filter( 'surecart/create_forms' ) );
	}
}
