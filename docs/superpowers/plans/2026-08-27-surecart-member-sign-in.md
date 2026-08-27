# Plan — the shop's sign-in, and no login page without a shop

Issue [#261](../../../../issues/261). Written 27 August 2026 against main at
v0.91.1, after putting SureCart 4.7.0 into the local harness and reading a
running copy.

## What this does

Members sign in through SureCart's form on the club's own login page. Our
parallel sign-in, forgot-password and reset journeys go, along with the reset
email rewrite that pointed at them. A site with no SureCart has no login page,
no member area, and no "Log in" anywhere.

Decided 26 August 2026: **the member area is SureCart-only.** A club running
LatePoint but not SureCart has no member sign-in. That is the accepted cost.

## What was checked first, and what it changed

Run `npm run wp:up` then `npm run wp:shop` to get the same environment.

| Checked | Answer | What it means here |
| --- | --- | --- |
| Does SureCart's sign-in need a connected store? | No. Its login route is `wp_authenticate` + `wp_validate_redirect`. | The whole plan is viable. A club mid-onboarding can still sign its members in. The passwordless code path does need the API; we do not depend on it. |
| Is there a login block? | No. It is a view — `\SureCart::block()->render( 'web/login' )` — emitting `<sc-login-form>`. | `Plugin_Slot::block()` cannot reach it. See step 2. |
| Can the club's "after signing in" survive? | Yes, twice over: the `sc_login_redirect_url` filter, and `?redirect_to` on the address. | Setup keeps working. Use the filter. |
| Will the form come alive? | Only where `surecart-components` is enqueued. | The login page needs what the member area already does. |

## Steps

Each step is its own commit and leaves the suite green.

### 1. A login page only where there is a shop

`Page_Map::pages()` gains `requires_shop` on `login` and `member-dashboard`,
and `available()` filters on `SureCart_Products::is_active()`.

This is the wide step, not the interesting one. It reaches the nav, the
visibility toggles, the content editor, the SEO report and the render gate,
and about nine existing tests assume the login page is always there. Do it
first and on its own, so the churn is not tangled up with the form swap.

Watch for: `Auth::logout_url()` and the header's account link, which must go on
working on a shop-less site — signing out is not signing in.

### 2. The shop's form on the club's page

Decide between:

- **a. Render SureCart's view.** A new seam beside `Plugin_Slot::block()` that
  calls `\SureCart::block()->render( 'web/login' )`. Vendor owns the markup and
  the wording, including the "Sign in to your account" title.
- **b. Emit `<sc-login-form>` ourselves,** with the club's own heading in its
  title slot. Three lines of markup, and the content editor's login heading and
  lede keep meaning something.

**Recommend b.** The custom element is the contract, not the view template, and
it is the option that keeps what a club typed. Take a only if we would rather
own none of SureCart's markup at all.

Either way: enqueue `surecart-components` on the login page, the way
`Member_Dashboard::enqueue_shop_assets()` already does for the member area.

### 3. The club's own destination

Hook `sc_login_redirect_url` and return `Auth_View::safe_target()` of the
configured post-login setting. The guard stays ours — SureCart validates the
redirect, but the setting and its meaning are Setup's.

### 4. Take the old journey out

Remove from `Auth`: the login-page branch of `handle()`, `dispatch()`,
`sign_in()`, `forgot()`, `reset_password()`, `reset_fields()`, the
`retrieve_password_message` filter and `lostpassword_url`.

Keep: `logout()`, `logout_url()`, and the `publish()` of who is signed in,
which the header on every page reads.

Trim `Auth_View` to `safe_target()` and whatever step 3 needs. Delete
`Sections::auth()`'s view machinery — `auth_copy`, `auth_message`, `auth_form`,
`auth_alt` — and the tests that cover them.

### 5. Prove it

In the harness with the shop switched on:

- A member with a password signs in from the club's login page and lands where
  Setup says.
- Signing out still works, and still works on a site with no shop.
- With SureCart deactivated: no login page, no member area, no "Log in" link,
  and nothing 500s.

The first of those is the one this was stopped for last time. It is now
testable, so it gets a Playwright spec rather than a promise.

## What this does not do

Password reset wording, SMTP, and SureCart's passwordless sign-in codes — that
last one needs a connected store and is not something a local run can prove.
