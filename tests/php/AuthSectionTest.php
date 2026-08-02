<?php
// tests/php/AuthSectionTest.php

use PHPUnit\Framework\TestCase;

/**
 * The account card renders the right screen, and the sign-in form posts real
 * WordPress credentials rather than the dead demo form it replaced.
 */
final class AuthSectionTest extends TestCase {

	/** @param array<string,mixed> $overrides */
	private function card( array $state = array() ): string {
		return Blueworx_Clubhouse_Sections::auth(
			array(
				'eyebrow'        => 'Members',
				'heading'        => 'Log in to your account',
				'lede'           => 'Access your membership.',
				'email_label'    => 'Email or username',
				'password_label' => 'Password',
				'remember_label' => 'Remember me',
				'forgot_label'   => 'Forgot password?',
				'forgot_href'    => '?page=login&clubhouse_auth=forgot',
				'signin_href'    => '?page=login',
				'submit_label'   => 'Log in',
				'join_prompt'    => 'Not a member yet?',
				'join_label'     => 'Join the club',
				'join_href'      => '?page=membership',
				'state'          => array_merge(
					array(
						'view'        => 'signin',
						'error'       => '',
						'notice'      => '',
						'form_action' => '/login/',
						'hidden'      => '<input type="hidden" name="_wpnonce" value="abc">',
						'redirect_to' => '',
						'logged_in'   => '',
						'logout_url'  => '',
					),
					$state
				),
			)
		);
	}

	public function test_sign_in_form_posts_wordpress_credential_fields(): void {
		$html = $this->card();
		$this->assertStringContainsString( 'method="post"', $html );
		$this->assertStringContainsString( 'action="/login/"', $html );
		$this->assertStringContainsString( 'name="user_login"', $html );
		$this->assertStringContainsString( 'name="user_password"', $html );
		$this->assertStringContainsString( 'name="remember"', $html );
		$this->assertStringContainsString( 'value="signin"', $html );
		$this->assertStringContainsString( 'name="_wpnonce"', $html );
	}

	/**
	 * A WordPress account can be signed into with its username, which type="email"
	 * would have the browser refuse before the form was ever submitted.
	 */
	public function test_sign_in_identifier_is_not_constrained_to_an_email(): void {
		$this->assertStringNotContainsString( 'type="email"', $this->card() );
	}

	public function test_forgot_view_asks_only_for_the_account(): void {
		$html = $this->card( array( 'view' => 'forgot' ) );
		$this->assertStringContainsString( 'Forgotten your password?', $html );
		$this->assertStringContainsString( 'value="forgot"', $html );
		$this->assertStringContainsString( 'name="user_login"', $html );
		$this->assertStringNotContainsString( 'name="user_password"', $html );
	}

	public function test_reset_view_asks_for_the_new_password_twice(): void {
		$html = $this->card( array( 'view' => 'reset' ) );
		$this->assertStringContainsString( 'name="pass1"', $html );
		$this->assertStringContainsString( 'name="pass2"', $html );
		$this->assertStringContainsString( 'value="reset"', $html );
	}

	public function test_confirmation_views_have_no_form_and_a_way_back(): void {
		foreach ( array( 'sent', 'resetok', 'signedout' ) as $view ) {
			$html = $this->card( array( 'view' => $view ) );
			$this->assertStringNotContainsString( '<form', $html, $view . ' should not render a form' );
			$this->assertStringContainsString( 'Back to sign in', $html, $view . ' should link back' );
		}
	}

	public function test_error_is_announced_and_escaped(): void {
		$html = $this->card( array( 'error' => 'Unknown username <b>x</b>.' ) );
		$this->assertStringContainsString( 'role="alert"', $html );
		$this->assertStringContainsString( 'Unknown username &lt;b&gt;x&lt;/b&gt;.', $html );
		$this->assertStringNotContainsString( '<b>x</b>', $html );
	}

	public function test_requested_destination_is_carried_through_the_form(): void {
		$html = $this->card( array( 'redirect_to' => '/members/' ) );
		$this->assertStringContainsString( '<input type="hidden" name="redirect_to" value="/members/">', $html );
	}
}
