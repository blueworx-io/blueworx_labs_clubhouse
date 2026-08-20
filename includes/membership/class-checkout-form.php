<?php
// includes/membership/class-checkout-form.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The checkout form a club gets without ever opening SureCart.
 *
 * SureCart seeds a checkout form on activation, and its PageSeeder runs
 * everything it is about to write through the surecart/create_forms filter
 * first. This answers that filter with a form of our own, so the club's
 * checkout comes into being through SureCart's own machinery — the post type,
 * the wrapping form block, the option that records the id — while looking like
 * the rest of the member area.
 *
 * Two things this deliberately does not do:
 *
 * It never writes a post. SureCart's createPosts() skips anything that already
 * exists, so a club that has edited its own checkout keeps it, and nothing here
 * can destroy a club's work.
 *
 * It never collects a card. surecart/payment mounts Stripe's own element, which
 * draws the card fields inside an iframe. Placing and colouring it is ours;
 * drawing it is not, and that is the line that keeps this a plugin rather than
 * a payment processor.
 *
 * Every block name below exists in SureCart 4.6.4 (packages/blocks/Blocks) —
 * see docs/integrations/surecart-notes.md. A block SureCart no longer has
 * renders as nothing, so the tests assert each one by name.
 *
 * The address is shown only when something ships, but not through a
 * surecart/conditional-form wrapper — that block's only attribute is
 * rule_groups (packages/blocks/Blocks/ConditionalForm/block.json), and its
 * rule conditions cover totals, prices, products, coupons, country and
 * processors, never "shipping enabled". surecart/address has no "shipping"
 * attribute either (packages/blocks/Blocks/Address/block.json). The real
 * mechanism is on the address element itself: sc-order-shipping-address
 * renders only when fullShippingAddressRequired() || full || requireName ||
 * showName is true, and the block's "full" attribute defaults to true. Setting
 * "full":false here removes the only reason it would always render, leaving
 * the decision to SureCart's own fullShippingAddressRequired().
 *
 * sc-shipping-choices needs no such guard: its own render() returns a hidden
 * host whenever the checkout does not require a shipping choice, so it is
 * already silent on a membership purchase.
 *
 * sc-order-submit is deliberately given no bw-btn classes: SureCart 4.6.4's
 * compiled component metadata marks it encapsulation: none, so it renders its
 * own <button> straight into the light DOM rather than a shadow root. Button
 * classes on the host would paint a second, button-shaped background behind
 * the real button. Its colour is already handled — Task 1 mapped
 * --sc-color-primary-500 to --bw-brand, and that reaches it without help here.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Checkout_Form {

	/** The key SureCart's seeder uses for the checkout form. */
	private const KEY = 'checkout';

	public static function register(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_filter( 'surecart/create_forms', array( self::class, 'filter_forms' ) );
	}

	/**
	 * Swap our content in for SureCart's default, and change nothing else.
	 *
	 * Anything unexpected is handed straight back. This filter fires inside
	 * SureCart's seeder at the moment a club's checkout is being created, and
	 * the worst outcome here is not an ugly form — it is no form at all.
	 *
	 * @param mixed $forms
	 * @return mixed
	 */
	public static function filter_forms( $forms ) {
		if ( ! is_array( $forms ) || ! isset( $forms[ self::KEY ] ) || ! is_array( $forms[ self::KEY ] ) ) {
			return $forms;
		}
		$forms[ self::KEY ]['content'] = self::content();
		return $forms;
	}

	/**
	 * The form, as block markup.
	 *
	 * Both the block comment and the rendered element are written out, the way
	 * SureCart's own template does it, so the form renders correctly before
	 * anyone opens it in the editor.
	 *
	 * The order is the approved design: what went wrong, who you are, where it
	 * goes, how you are paying, and the button — with the summary beside it on
	 * a desktop and stacked underneath on a phone.
	 */
	public static function content(): string {
		return '<!-- wp:surecart/columns {"isStackedOnMobile":true,"isReversedOnMobile":true} -->'
			. '<sc-columns is-stacked-on-mobile="1" is-reversed-on-mobile="1" class="wp-block-surecart-columns clubhouse-checkout__cols">'

			// The fields.
			. '<!-- wp:surecart/column {"layout":{"type":"constrained","contentSize":"640px"}} -->'
			. '<sc-column class="wp-block-surecart-column clubhouse-checkout__main">'

			. '<!-- wp:surecart/checkout-errors --><sc-checkout-form-errors></sc-checkout-form-errors><!-- /wp:surecart/checkout-errors -->'

			. '<!-- wp:surecart/email {"label":"Email","placeholder":"you@example.com"} /-->'

			. '<!-- wp:surecart/name {"required":true,"label":"Name","placeholder":"Your full name"} -->'
			. '<sc-customer-name label="Name" placeholder="Your full name" required class="wp-block-surecart-name"></sc-customer-name>'
			. '<!-- /wp:surecart/name -->'

			. '<!-- wp:surecart/phone {"label":"Mobile","required":false} /-->'

			// No conditional wrapper: surecart/address has no "shipping" attribute
			// and surecart/conditional-form has no "shipping enabled" condition to
			// give it — see the class docblock. "full":false hands the decision to
			// SureCart's own fullShippingAddressRequired(), so the address only
			// renders when something in the cart actually ships. shipping-choices
			// self-hides the same way (sc-shipping-choices.js checks
			// selected_shipping_choice_required before rendering anything), so a
			// membership purchase shows neither.
			. '<!-- wp:surecart/address {"label":"Where should we send it?","full":false} /-->'
			. '<!-- wp:surecart/shipping-choices /-->'

			. '<!-- wp:surecart/payment {"secure_notice":"Your card is handled by Stripe. The club never sees it."} -->'
			. '<sc-payment label="Payment" secure-notice="Your card is handled by Stripe. The club never sees it." class="wp-block-surecart-payment"></sc-payment>'
			. '<!-- /wp:surecart/payment -->'

			. '<!-- wp:surecart/submit {"show_total":true,"full":true} -->'
			. '<sc-order-submit type="primary" full="true" size="large" show-total="true" class="wp-block-surecart-submit">Pay now</sc-order-submit>'
			. '<!-- /wp:surecart/submit -->'

			. '</sc-column><!-- /wp:surecart/column -->'

			// The summary.
			. '<!-- wp:surecart/column {"sticky":true,"layout":{"type":"constrained","contentSize":"400px"}} -->'
			. '<sc-column class="wp-block-surecart-column is-sticky clubhouse-checkout__rail bw-card">'

			. '<!-- wp:surecart/totals {"collapsible":true,"collapsedOnMobile":true,"closed_text":"Show order summary","open_text":"Hide order summary"} -->'
			. '<sc-order-summary collapsible="1" collapsed-on-mobile="1" closed-text="Show order summary" open-text="Hide order summary" class="wp-block-surecart-totals">'
			. '<!-- wp:surecart/line-items --><sc-line-items editable="1" class="wp-block-surecart-line-items"></sc-line-items><!-- /wp:surecart/line-items -->'
			. '<!-- wp:surecart/divider --><sc-divider></sc-divider><!-- /wp:surecart/divider -->'
			. '<!-- wp:surecart/subtotal --><sc-line-item-total total="subtotal" class="wp-block-surecart-subtotal"><span slot="description">Subtotal</span></sc-line-item-total><!-- /wp:surecart/subtotal -->'
			. '<!-- wp:surecart/coupon {"text":"Got a code?","button_text":"Apply"} --><sc-order-coupon-form></sc-order-coupon-form><!-- /wp:surecart/coupon -->'
			. '<!-- wp:surecart/tax-line-item --><sc-line-item-tax class="wp-block-surecart-tax-line-item"></sc-line-item-tax><!-- /wp:surecart/tax-line-item -->'
			. '<!-- wp:surecart/trial-line-item /-->'
			. '<!-- wp:surecart/divider --><sc-divider></sc-divider><!-- /wp:surecart/divider -->'
			. '<!-- wp:surecart/total --><sc-line-item-total total="total" size="large" show-currency="1" class="wp-block-surecart-total"><span slot="title">Due today</span><span slot="subscription-title">Due today</span></sc-line-item-total><!-- /wp:surecart/total -->'
			. '</sc-order-summary>'
			. '<!-- /wp:surecart/totals -->'

			. '</sc-column><!-- /wp:surecart/column -->'

			. '</sc-columns>'
			. '<!-- /wp:surecart/columns -->';
	}
}
