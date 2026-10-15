# Playtomic Connector — Design

**Status:** Approved (design phase)
**Date:** 2026-10-15
**Repo:** `blueworx_labs_clubhouse`
**Version at design:** v0.106.2
**Brief:** PadlX – Playtomic Integration Brief (pasted by Luke, 15 October 2026)

## Summary

A club that runs its courts on Playtomic gets two things. First, a members
export: an admin screen that lists the club's members, builds the CSV that
Playtomic Manager's chain import expects, and remembers who has already been
sent. Second, a read-only Playtomic API client the plugin can build features
on later, with a Test connection button that proves the credentials work.

Playtomic's API cannot create members, so the CSV is the only road in. Luke
downloads it and uploads it in Playtomic Manager by hand.

Built generally: venues, categories and who qualifies are all settings, so any
Clubhouse club on Playtomic can switch it on. PadlX is the first.

## Goals

- An administrator reviews a table of members, ticks who goes, and downloads a
  Playtomic-ready CSV of exactly those people.
- Members already sent are not sent twice, and a failed import can be redone.
- Phone, gender and date of birth are recorded against every member as
  standard fields, so the CSV can carry them.
- The club chooses who counts: every member, or only holders of chosen
  SureCart subscriptions.
- One client class owns everything about talking to Playtomic: auth, token
  cache, paging, errors. Future features call it and never touch HTTP.
- Credentials never appear in code, the repo, logs or the screen after saving.

## Non-Goals

- Showing bookings, players or payments anywhere on the site.
- Scheduled syncs or background jobs.
- Writing anything to Playtomic (the API cannot).
- Matching the official chain sample CSV exactly. Decided by Luke: build from
  the brief's headers now, check against the sample before the first real
  import, adjust then. Headers live in one place so that change is cheap.
- Asking for phone, gender or birthdate at checkout. SureCart owns that form.

## Key Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Where it lives | A **Playtomic** screen under the Clubhouse Setup menu, administrators only | Same place as Search & sharing and Import. Admin-only because it holds credentials and exports personal data. |
| Who qualifies | A setting: *everyone with a member account* or *only members holding chosen SureCart subscriptions* | Decided by Luke: clubs differ, so both, configurable. |
| Phone, gender, birthdate | New **standard** profile fields on every club | Decided by Luke. Custom fields would mean every club mapping them by hand; standard fields make the export the same everywhere. |
| Export marker | User meta holding the export date | Same home as the profile answers: survives the plugin, readable by any export tool. |
| When a member is marked | When they are ticked and downloaded | Decided by Luke: the review table is the final step, and only ticked rows leave the site. The *All* filter plus a per-member *Clear* covers a failed import. |
| CSV headers | Constants in one class, in the brief's order | One file to edit when the sample arrives. |
| Venues | Read from the token's `tenant_ids` on Test connection, kept as settings with an admin override | The brief asks for exactly this. Nothing hard-coded. |
| Secret storage | Encrypted at rest with a key derived from the site's own salts | WordPress has no secret store. Sodium ships with PHP 7.2+, and the salts are already secret per site. Never echoed back after saving. |
| HTTP | An injectable transport over `wp_remote_request` | Lets every client path be unit-tested with a fake, the same seam `Products` gives SureCart. |
| Token cache | A transient, refreshed five minutes before expiry or on a 401 | Tokens last an hour; one refresh per hour per site is the whole cost. |

## Part 1 — Standard member fields

Three fields that every club has, drawn above the club's custom fields on the
member's Profile page and on wp-admin's user profile screen. They cannot be
deleted or reordered from the Members tab; the club can hide any of them with
a switch on that tab if it does not want to ask.

| Field | Meta key | Stored as | Validation |
|---|---|---|---|
| Phone | `clubhouse_profile_phone` | string, as typed | Digits, spaces, `+`, `(`, `)` and `-` only; cleaned on export, not on save |
| Gender | `clubhouse_profile_gender` | string | One of the choices below, or empty |
| Date of birth | `clubhouse_profile_birthdate` | `YYYY-MM-DD` | Real calendar date, not in the future, or empty |

Gender choices: Male, Female, Other, Prefer not to say — stored as the lowercase
word (`male`, `female`, `other`, `undisclosed`). Playtomic's own accepted values
are not documented in the brief; the CSV writes the stored word and this is on
the pre-import checklist.

Standard fields are members' own to fill in (`who = member`). They reuse the
existing type machinery (`text`, `select`, `date`) through a new
`Profile_Fields::standard()` list that `Profile_Store::fields()` prepends to the
club's custom ones, so every surface that already draws custom fields draws these
with no new rendering code. A custom field can never take one of these keys.

## Part 2 — The export

### The screen

`Clubhouse → Playtomic`, slug `clubhouse-playtomic`, capability: administrator
only (a new `Owner_Capabilities::PLAYTOMIC_CAP` granted to administrators and no
Clubhouse role). Registered in `Admin_Pages::all()` so the Access screen reports
it. Two cards, settings first:

**Connection and settings card** — a form that saves to the Clubhouse options
store under one `playtomic` option:

- Client ID (text), Secret (password field; shows "Saved — enter a new one to
  replace it" once set, never the value).
- **Test connection** button: fetches a token, lists the venues the token names,
  and says in plain words whether it worked. On success, any venue not yet in
  the list below is added with its id and an empty name.
- Who qualifies: radio — *Everyone with a member account* / *Only members with
  one of these subscriptions*, then a checklist of SureCart prices from the
  existing `Products` adapter.
- Venues, a repeater: venue id, friendly name, column club name (the
  `[clubname]` in headers), category name, expiry (either a fixed date or a
  number of months from the export date), benefit price id (optional), next due
  date rule (none / same as expiry / months from export date).

**Members card** — filter bar, then a review table, then the download. The
table is the last step before anything leaves the site: nobody is exported
without an administrator having seen their row and ticked it.

- Filters: *Not yet exported* (default) / *All*; joined from / to dates.
- Columns: a checkbox, name, email, phone, joined, exported on. A header
  checkbox ticks or unticks every row shown; rows can also be ticked one by
  one. Rows already exported start unticked under *All*, so a re-export is a
  deliberate tick. Members missing a name or email are listed with a warning
  badge and cannot be ticked.
- A live count beside the button: "12 of 40 selected".
- **Download Playtomic CSV** is disabled until something is ticked. It sends
  only the ticked members and stamps each with today's date, so they drop out
  of *Not yet exported* and are never sent twice by accident. A per-row *Clear
  export mark* link undoes one if an import failed.
- Works without JavaScript: the checkboxes are a plain form posted to
  `admin-post`; the select-all header and live count are progressive.

### Who is a member

A member is a WordPress user with the `subscriber` role, which is what SureCart
gives a buyer. "Joined" is the user's registration date.

With the subscription rule on, a member qualifies only if SureCart reports an
active subscription on one of the ticked prices. Read through SureCart's REST
routes dispatched internally, the way `SureCart_Products` already does: the
user's customer ids from the `sc_customer_ids` user meta, then
`surecart/v1/subscriptions` filtered to those customers. **Verify both against
SureCart's source before building** — the integration notes record what went
wrong last time this was guessed. Without SureCart installed, the subscription
rule is offered greyed out with a sentence saying why.

### The file

UTF-8, comma-separated, CRLF line endings, quoted where needed, built by a
pure `Playtomic_Csv` class and streamed through `admin-post` with a nonce and
capability check. Filename `playtomic-members-YYYY-MM-DD.csv`.

Standard columns, in order: `name`, `email`, `phone_number`, `gender`,
`birthdate`. Then, for each venue in settings order:
`CAT_IMP-[club]-cat_name`, `CAT_IMP-[club]-exp_date`,
`CAT_IMP-[club]-benefit_price_id`, `CAT_IMP-[club]-next_due_date`.

Rules, each a pure function with its own tests:

- Name is first and last name joined; display name if both are empty; a member
  with no name at all or no email is skipped and flagged.
- Phone: strip everything but digits; drop a leading `0`; drop a leading `61`;
  write `+61` followed by the rest. Empty stays empty. The country code is a
  constant for now (`+61`, the brief's one club); a settings field if a second
  country ever needs it.
- Dates always `YYYY-MM-DD`. Expiry from a fixed date is written as is; from
  months, it is the export date plus that many months. A fixed date before the
  export date is written anyway and the screen warns beside the setting.
- Empty optional settings write empty cells; the column is still present.

## Part 3 — The API client

`includes/playtomic/`:

- `class-playtomic-settings.php` — reads and writes the option, encrypts and
  decrypts the secret. Pure beyond `Storage`.
- `interface-playtomic-transport.php` with `class-playtomic-wp-transport.php`
  (`wp_remote_request`) and a fake in tests.
- `class-playtomic-client.php` — the one class future features use:

```
token(): string                              // cached; refreshes early or on 401
venues(): array<int,string>                  // tenant_ids from the token
bookings( string $venue, string $from, string $to ): array   // every page
players( string $venue ): array              // every page
player( string $venue, string $id ): array
payments( ...per the Payments docs ): array
```

- Token: `POST /api/v1/oauth/token` with `client_id` and `secret`; cached in a
  transient for `expires_in` minus five minutes; a 401 on any call clears it and
  retries once.
- Paging: follows `page`/`size` (size 200) until a short page.
- Errors become `Playtomic_Exception` with a plain-English message: a 400 names
  the missing or misordered parameter, 401/403 says the credentials or token
  were refused, anything else says Playtomic could not be reached. The raw body
  is kept on the exception for the log, with any token or secret replaced.
- Logging: `error_log` through one `log()` method that scrubs secrets and tokens
  before writing; the last error is also stored in the option so the screen can
  show it next to Test connection.
- The Players path is `/api/v1/venues/{id}/players` per the brief; the docs'
  examples show `/venue/`. Test connection tries the plural and falls back to
  the singular once, and records which answered.

The official docs at https://third-party.playtomic.io/ are read in full before
the client is written.

## Guides, tests, docs, release

- A guide entry for the Playtomic screen, pushed through the existing guides
  filters (GuidesTest fails without one).
- PHPUnit: CSV building, phone cleaning, date rules, member filtering, the
  settings encryption round trip, the client's token cache, refresh-on-401,
  paging and each error class, all against the fake transport.
- Playwright (WordPress project): sign in as an administrator, save settings,
  see a seeded member listed, download the CSV and check its header row and the
  member's row, reload and see them under *All* but not *Not yet exported*.
- `docs/integrations/playtomic.md`: how to call the client, where the
  credentials live, the pre-import checklist from the brief.
- Version 0.107.0, changelog entry in the club's words, GitHub issue raised
  before the branch's first commit.

## Checklist for Luke before the first real import

- Compare the file's header row with the official chain sample; tell me any
  difference.
- Confirm Playtomic's accepted gender values.
- Import one or two dummy members and confirm the categories attach.
