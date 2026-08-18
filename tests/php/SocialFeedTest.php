<?php

use PHPUnit\Framework\TestCase;

/** A source whose answer the test decides, including "the fetch failed" (null). */
final class FakeFeedSource implements Blueworx_Clubhouse_Feed_Source {

	/** @var array<int,mixed>|null */
	private $answer;

	public int $calls = 0;

	/** @param array<int,mixed>|null $answer */
	public function __construct( ?array $answer ) {
		$this->answer = $answer;
	}

	public function posts(): ?array {
		++$this->calls;
		return $this->answer;
	}
}

final class SocialFeedTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	protected function tearDown(): void {
		wp_stub_reset();
	}

	/** @return array<int,array{id:string,image:string,caption:string,date:string,permalink:string}> */
	private function twoPosts(): array {
		return array(
			array( 'id' => 'a', 'image' => '', 'caption' => 'one', 'date' => '', 'permalink' => 'https://x.test/1' ),
			array( 'id' => 'b', 'image' => '', 'caption' => 'two', 'date' => '', 'permalink' => 'https://x.test/2' ),
		);
	}

	public function test_a_good_fetch_is_served_and_cached(): void {
		$source = new FakeFeedSource( $this->twoPosts() );
		$feed   = new Blueworx_Clubhouse_Social_Feed( $source );
		$this->assertCount( 2, $feed->posts() );
		$this->assertSame( Blueworx_Clubhouse_Social_Feed::OK, $feed->status() );

		// A second instance reads the transient rather than the source.
		$again = new Blueworx_Clubhouse_Social_Feed( $source );
		$this->assertCount( 2, $again->posts() );
		$this->assertSame( 1, $source->calls, 'the cache did not spare the source a second call' );
	}

	public function test_nothing_to_show_reads_as_not_connected(): void {
		$feed = new Blueworx_Clubhouse_Social_Feed( new FakeFeedSource( array() ) );
		$this->assertSame( array(), $feed->posts() );
		$this->assertSame( Blueworx_Clubhouse_Social_Feed::NOT_CONNECTED, $feed->status() );
	}

	public function test_a_failed_fetch_keeps_the_last_good_posts_up(): void {
		( new Blueworx_Clubhouse_Social_Feed( new FakeFeedSource( $this->twoPosts() ) ) )->posts();
		wp_stub_clear_transients();

		$feed = new Blueworx_Clubhouse_Social_Feed( new FakeFeedSource( null ) );
		$this->assertCount( 2, $feed->posts(), 'a blip lost the club its feed' );
		$this->assertSame( Blueworx_Clubhouse_Social_Feed::OK, $feed->status() );
	}

	public function test_a_failed_fetch_with_no_history_is_an_error_not_an_empty_feed(): void {
		$feed = new Blueworx_Clubhouse_Social_Feed( new FakeFeedSource( null ) );
		$this->assertSame( array(), $feed->posts() );
		$this->assertSame( Blueworx_Clubhouse_Social_Feed::ERROR, $feed->status() );
	}

	public function test_a_failure_is_not_retried_on_every_page_load(): void {
		$source = new FakeFeedSource( null );
		( new Blueworx_Clubhouse_Social_Feed( $source ) )->posts();
		( new Blueworx_Clubhouse_Social_Feed( $source ) )->posts();
		$this->assertSame( 1, $source->calls, 'an outage was re-fetched on the next request' );
	}

	public function test_a_record_missing_its_link_never_reaches_the_renderer(): void {
		$feed = new Blueworx_Clubhouse_Social_Feed( new FakeFeedSource( array(
			array( 'id' => 'a', 'image' => '', 'caption' => 'kept', 'date' => '', 'permalink' => 'https://x.test/1' ),
			array( 'id' => '', 'image' => '', 'caption' => 'no id', 'date' => '', 'permalink' => 'https://x.test/2' ),
			array( 'id' => 'c', 'image' => '', 'caption' => 'no link', 'date' => '', 'permalink' => '' ),
			'not even an array',
		) ) );
		$posts = $feed->posts();
		$this->assertCount( 1, $posts );
		$this->assertSame( 'kept', $posts[0]['caption'] );
	}

	public function test_a_corrupted_last_good_option_is_treated_as_never_having_been_there(): void {
		update_option( 'blueworx_clubhouse_social_feed_last_good', 'not an array', false );
		$feed = new Blueworx_Clubhouse_Social_Feed( new FakeFeedSource( null ) );
		$this->assertSame( array(), $feed->posts() );
		$this->assertSame( Blueworx_Clubhouse_Social_Feed::ERROR, $feed->status() );
	}
}
