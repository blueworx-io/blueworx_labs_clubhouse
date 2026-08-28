# Profile Builder — Design

**Status:** Approved (design phase)
**Date:** 2026-08-27
**Issue:** to be raised before implementation
**Repo:** `blueworx_labs_clubhouse`
**Version at design:** v0.93.1

## Summary

A club invents its own member fields — shirt size, squad number, emergency
contact, dietary needs — and Clubhouse stores them against the WordPress user.

Three surfaces. The owner builds the list on the **Members** tab of Clubhouse
Setup. The member sees and fills in their own on a new **Profile** page in the
member area. Club staff see every field, including the ones members never see,
on WordPress's own user profile screen.

The member area's existing **Account** view splits in the same pass: name,
sign-in email and password move to Profile, and Account keeps billing details
and payment methods.

## Goals

- A club can record anything it needs about a member without a developer.
- The member's own details and the club's records about them are one page, not
  two systems.
- Answers live in WordPress's user records, so nothing is trapped in Clubhouse.
- A club that defines no fields sees no change beyond the Account/Profile split.
- A member cannot change a field the club reserved to itself.

## Non-Goals

- **File upload fields.** Decided against for now: uploads bring private storage,
  per-file permissions, deletion on account removal and a GDPR surface, and no
  club has asked. Its own piece of work when one does.
- **Reading answers across members** — columns on the Users screen, or a
  spreadsheet export. Decided against: one member at a time is the smallest
  thing that works, and the storage choice below keeps the export cheap to add.
- **Asking for these fields at signup or checkout.** The checkout is SureCart's
  form; adding to it is a separate job.
- **Showing custom fields on the club's public site** — a member directory, a
  squad list. Nothing here renders outside the member area and wp-admin.

## Key Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Where answers live | WordPress user meta | The club's data survives Clubhouse being removed, any other plugin or export tool can read it, and the parked spreadsheet export becomes trivial. A table of our own buys nothing and costs a migration. |
| Where definitions live | The Clubhouse options store, via `Storage` | Same as every other Clubhouse setting; keeps the definition classes WP-free and unit-testable, like `Content_Store`. |
| Builder location | A section on the existing Setup → **Members** tab | Decided by Luke, revised from "a new tab": Members already holds the member-area settings and member emails. Same screen, same save bar, found where the other member settings are. |
| Field identity | A permanent generated key, set once at creation | The owner can rewrite a label as often as they like without detaching every member's answer from it. Labels are display only. |
| Deleting a field | Removes it from every screen; answers are kept | An accidental delete is recoverable by re-adding the field. Clearing answers for good is a separate, explicitly confirmed action. |
| Who fills a field in | Three settings per field | Decided by Luke. Members fill some in, the club fills others in, and a third kind is club-private. Two settings cannot express a private note about a member. |
| Required | Enforced on the member's own Profile form only | Club staff routinely change one thing about a member; blocking their save on an unrelated required field would make wp-admin unusable. |
| Account view | Splits into Profile and Account | Decided by Luke. "Who I am" and "how I pay" are different errands. Existing `?view=account` addresses keep working. |

## The field types

Seven. Each is a definition setting, a rendered control on two surfaces, a
validation rule, and one stored value.

| Type | Stored as | Validation |
|---|---|---|
| Short text | string | Trimmed, plain text only, length capped |
| Long text | string | Trimmed, line breaks kept, no HTML |
| Number | string holding a number | Must parse as a number, or be empty |
| Date | `YYYY-MM-DD` string | Must be a real calendar date, or be empty |
| Dropdown | string | Must be one of the club's choices, or be empty |
| Multi-select | array of strings | Every entry must be one of the club's choices |
| Yes/no | `'1'` or `''` | Nothing to validate |

## What a field carries

- **Label** — what a member and staff see. Editable at any time.
- **Key** — generated from the label at creation, then permanent and hidden.
- **Type** — one of the seven above, chosen at creation.
- **Choices** — dropdown and multi-select only; one per line.
- **Help text** — optional, shown under the control.
- **Required** — see the enforcement rule above.
- **Who fills it in** — one of:
  - *Member* — a form control on the member's Profile page, and on wp-admin.
  - *Club* — read-only text on the member's Profile page, editable on wp-admin.
  - *Club, private* — absent from the member's Profile page entirely; editable
    on wp-admin.

Fields render in the order the owner arranges them, on both surfaces.

A club may define at most 30 fields. Beyond that the Profile page stops being a
page anyone reads, and the limit is a cheaper conversation than the page.

## The three surfaces

### Setup → Members → Profile fields

A repeating list of field rows with add, remove and reorder, following the
existing repeatable-section pattern in the Club Pages content screen
(`Content_Screen::loop_area`). Saved by the Setup screen's existing form and
save bar — no new form, no new nonce path.

Choosing a type is a creation-time decision. Changing the type of a field that
already holds answers is not offered: the answers would not survive it, and the
owner can add a new field and clear the old one.

### Member area → Profile

A new view in `Dashboard_Views::all()`, keyed `profile`, sitting where Account
sits today and carrying the same icon treatment as the rest.

It holds, in order:

1. SureCart's `surecart/wordpress-account` block — name, sign-in email, password.
2. A Clubhouse-rendered card of the club's custom fields.

The custom-fields card is ours, not a plugin block. `Dashboard_Views` currently
declares only a list of block names per view, so a view gains a way to declare a
native panel alongside its blocks, and `Member_Dashboard::view_body()` renders
it into a card like any other.

The card holds every *Member* field as an editable control and every *Club*
field as read-only text. *Club, private* fields are not rendered and not sent to
the browser. It posts to a Clubhouse `admin-post` action with a nonce.

A club with no fields defined gets no card, and the Profile page is the
WordPress account block alone — which is still a better home for it than the
billing screen.

Profile requires SureCart today, because the WordPress account block is
SureCart's. If a shop-less club ever needs a Profile page, the custom-fields
card can stand alone; that is not built now.

**Account** keeps `surecart/customer-billing-details` and
`surecart/customer-payment-methods`, and keeps its key, so `?view=account`
bookmarks still resolve.

### WordPress → Users → the user's profile

Every field, in order, in its own section on `profile.php` and `user-edit.php`,
via `show_user_profile` / `edit_user_profile` and saved on
`personal_options_update` / `edit_user_profile_update`.

Every field is editable here, including *Club, private*. Nothing is rendered or
saved unless the current user can edit that user.

## Security

- The member's form saves only fields whose setting is *Member*. A tampered
  form carrying a squad number is discarded, not saved.
- *Club, private* fields never reach a member's browser — not as a hidden input,
  not as read-only text.
- A member's form only ever writes to their own user record.
- Nonce on the member form; capability check on both wp-admin hooks.
- Every value is validated against its field's type and choices on the way in,
  and escaped on the way out.

## Components

Pure classes first, WordPress at the edges — the pattern the rest of the plugin
follows.

| File | Does | Depends on |
|---|---|---|
| `includes/profile/class-profile-fields.php` | The type catalogue, and sanitising a submitted field definition into a valid one | Nothing. Pure. |
| `includes/profile/class-profile-values.php` | Validating and normalising a member's answers against a definition list; deciding which fields a given viewer may see and write | `Profile_Fields`. Pure. |
| `includes/profile/class-profile-store.php` | Reading and writing definitions (options) and answers (user meta) | WordPress |
| `includes/profile/class-profile-panel.php` | The member-facing card's HTML | `Profile_Fields`. Pure. |
| `includes/profile/class-profile-form.php` | Handling the member's post | `Profile_Values`, `Profile_Store` |
| `includes/profile/class-profile-user-screen.php` | The two wp-admin hooks | `Profile_Values`, `Profile_Store` |

Changed: `class-dashboard-views.php` (the Profile view, the Account split, a
native-panel declaration), `class-member-dashboard.php` (render a native panel),
`class-setup-screen.php` and `class-setup-controller.php` (the builder section
and its save), `bootstrap.php` (wire the new classes).

## Testing

**Unit (`tests/php`)** — the pure classes, where the rules actually live:

- Each of the seven types accepts what it should and rejects what it should not.
- A dropdown answer outside the club's choices is rejected.
- A field's key survives a label rewrite.
- Deleting a definition leaves the stored answers untouched.
- A member's submission carrying a *Club* or *Club, private* field is stripped
  of it before saving.
- A *Club, private* field is absent from the member panel's HTML.

**Playwright** — the journeys:

- An owner adds a field on Setup → Members and it appears on the member's
  Profile page.
- A member fills it in, saves, and the value shows on their WordPress profile.
- A required field left blank blocks the member's save with a message.
- A *Club* field shows the member a value with no control to change it.
- Account still resolves and still shows billing and payment methods.

## Upgrade and rollback

Existing sites gain a Profile nav item and lose the account block from Account.
Nothing is migrated: no site has custom fields yet, and the blocks that move are
declared, not stored. `?view=account` keeps working.

Rolling back to v0.93.x leaves the definitions in the options row and the
answers in user meta, both unread and both harmless. Re-installing picks them
straight back up.

## Delivery

One issue, one branch, one pull request, under the repo's standing guardrails:
lint and build pass, a minor version bump, a changelog entry written for a club
owner, and Playwright coverage for the new journeys.

Follow-on work, each its own issue: file upload fields; columns and a
spreadsheet export on the members list; asking for fields at signup.
