# Playtomic — how the plugin talks to it

Written 15 October 2026 from the official docs at https://third-party.playtomic.io/
(Authentication, Conventions, Bookings, Players, Payments). Nothing here was
verified against a live account yet; the Test connection button is the first
real call.

## What exists

- **Clubhouse → Playtomic** (administrators and Clubhouse Owners): credentials, who counts as
  a member, one row per venue, and the review table that builds the CSV for
  Playtomic Manager's chain import (Customers → Imports → Import new customers).
- `Blueworx_Clubhouse_Playtomic_Client` — the only class that talks to the
  API. Everything else calls it.

## Using the client from a future feature

```php
$settings = Blueworx_Clubhouse_Playtomic_Controller::settings();
$client   = Blueworx_Clubhouse_Playtomic_Controller::client( $settings );

try {
	$venues   = $client->venues();                                        // tenant ids from the token
	$bookings = $client->bookings( $venues[0], '2026-10-01T00:00:00', '2026-10-31T23:59:59' );
	$players  = $client->players( $venues[0], array( 'BENEFITS' ) );
	$one      = $client->player( $venues[0], $players[0]['player_id'] );
	$payments = $client->payments( $venues[0], array( 'start_payment_date' => '2026-09-01T00:00:00Z' ) );
	$raw      = $client->get( '/api/v1/anything-new', array( 'tenant_id' => $venues[0] ) );
} catch ( Blueworx_Clubhouse_Playtomic_Exception $e ) {
	// $e->getMessage() is a plain sentence fit for a screen.
	// $e->status() is the HTTP status (0 for a network failure).
	// $e->detail() is the scrubbed response body, for the log only.
	Blueworx_Clubhouse_Playtomic_Client::log( $e->getMessage() . ' | ' . $e->detail() );
}
```

Things the client already does, so a feature does not have to:

- Gets a token (`POST /api/v1/oauth/token`, body `client_id` + `secret`), caches it in a transient, refreshes it five minutes before the hour is up, and once more if a call comes back 401.
- Follows every page: bookings by `page`/`size` (200) until a short page; players and payments by `cursor_id` until `has_more` is false.
- Tries `/api/v1/venues/{id}/players` first and `/api/v1/venue/{id}/players` if that 404s, and remembers which answered. The docs show both.
- Turns 400, 401/403, 429 (rate limit: 400 calls per 10 minutes), 202 (payments still being prepared) and network failures into one exception with a plain message.
- Scrubs the secret and the token out of anything it logs.

Things it does not do: retry on 429 or 202 (a feature decides whether to wait), schedule anything, or write to Playtomic (the API is read-only).

## Where the credentials live

In the `clubhouse_playtomic` option, with the secret encrypted using a key
derived from the site's own salts (`AUTH_KEY`, `SECURE_AUTH_KEY`,
`LOGGED_IN_KEY`). Changing the salts means re-entering the secret. The secret is
never shown on screen again and never written to the log.

## Before the first real import

- Compare the header row of a downloaded file with the official chain sample
  from Playtomic Manager. The headers live in one place:
  `Blueworx_Clubhouse_Playtomic_Csv`.
- Confirm the gender values Playtomic's import accepts. The file writes `MALE`
  and `FEMALE` (what the Players API returns) and leaves anything else blank.
- Phone numbers are written as `+61` followed by digits (`Playtomic_Csv::COUNTRY_CODE`).
- Import one or two dummy members and check the categories attach.
