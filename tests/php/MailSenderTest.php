<?php

use PHPUnit\Framework\TestCase;

final class MailSenderTest extends TestCase {

	public function test_the_sender_name_is_the_club_not_wordpress(): void {
		$this->assertSame( 'Crewe Vagrants Squash', Blueworx_Clubhouse_Mail::from_name( 'Crewe Vagrants Squash', '' ) );
	}

	public function test_an_owner_can_say_who_it_comes_from(): void {
		$this->assertSame( 'Crewe Vagrants Membership', Blueworx_Clubhouse_Mail::from_name( 'Crewe Vagrants Squash', 'Crewe Vagrants Membership' ) );
	}

	public function test_a_club_with_no_name_is_left_alone(): void {
		// Nothing to improve on, so core's own default stands rather than an
		// empty From name that some mail clients render as the address twice.
		$this->assertSame( '', Blueworx_Clubhouse_Mail::from_name( '   ', '' ) );
	}

	public function test_the_address_is_noreply_at_the_sites_own_domain(): void {
		$this->assertSame( 'noreply@crewevagrantssquash.co.uk', Blueworx_Clubhouse_Mail::from_address( 'https://crewevagrantssquash.co.uk/', '' ) );
	}

	public function test_www_is_not_part_of_the_address(): void {
		// www.club.co.uk and club.co.uk are the same club, and a mailbox at the
		// bare domain is the one that exists.
		$this->assertSame( 'noreply@club.co.uk', Blueworx_Clubhouse_Mail::from_address( 'https://www.club.co.uk/', '' ) );
	}

	public function test_a_port_is_not_part_of_the_address(): void {
		$this->assertSame( 'noreply@localhost', Blueworx_Clubhouse_Mail::from_address( 'http://localhost:8705/wp/', '' ) );
	}

	public function test_an_owner_can_give_their_own_address(): void {
		$this->assertSame( 'hello@club.co.uk', Blueworx_Clubhouse_Mail::from_address( 'https://club.co.uk/', ' hello@club.co.uk ' ) );
	}

	public function test_a_typed_address_that_is_not_an_address_is_ignored(): void {
		// Better the derived address than a From header no server will accept.
		$this->assertSame( 'noreply@club.co.uk', Blueworx_Clubhouse_Mail::from_address( 'https://club.co.uk/', 'not an address' ) );
	}

	public function test_no_address_when_there_is_no_domain_to_build_one_from(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Mail::from_address( '', '' ) );
	}

	public function test_an_owners_own_address_is_kept_across_a_save(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Setup_Controller::handle_save(
			array( 'clubhouse_mail_from_name' => 'Crewe Vagrants Membership', 'clubhouse_mail_from_address' => 'hello@club.co.uk' ),
			$storage
		);
		$mail = new Blueworx_Clubhouse_Mail_Settings( $storage );
		$this->assertSame( 'Crewe Vagrants Membership', $mail->get_from_name() );
		$this->assertSame( 'hello@club.co.uk', $mail->get_from_address() );
	}

	public function test_an_address_that_is_not_one_is_refused_with_a_notice(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$notices = Blueworx_Clubhouse_Setup_Controller::handle_save(
			array( 'clubhouse_mail_from_address' => 'not an address' ),
			$storage
		);
		$this->assertSame( '', ( new Blueworx_Clubhouse_Mail_Settings( $storage ) )->get_from_address() );
		$this->assertNotEmpty( array_filter( $notices, static fn( $n ) => 'error' === $n['type'] ) );
	}

	public function test_clearing_the_address_puts_the_club_back_on_its_own_domain(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		Blueworx_Clubhouse_Setup_Controller::handle_save( array( 'clubhouse_mail_from_address' => 'hello@club.co.uk' ), $storage );
		Blueworx_Clubhouse_Setup_Controller::handle_save( array( 'clubhouse_mail_from_address' => '' ), $storage );
		$this->assertSame( '', ( new Blueworx_Clubhouse_Mail_Settings( $storage ) )->get_from_address() );
	}
}
