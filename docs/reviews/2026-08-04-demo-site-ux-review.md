# UI/UX review — demo.305media.co.uk

Reviewed 4 Aug 2026. Every page in the nav, every filter view, both listings, a
blog post, a product page, the 404, and the full LatePoint booking flow, at
1440px and at 390px.

**Re-checked 4 Aug against v0.58.0 after the demo was updated.** Member login and
password reset now work, and club pages now carry a description, canonical and
social tags — those two are closed (#88, #119). Everything else below was
confirmed still present on v0.58.0. The update also surfaced three new problems
of its own: News stories open unstyled, News isn't linked from anywhere, and
escaped code shows throughout the News excerpts.

All findings are raised as issues #86–#138.

No bookings, orders or messages were submitted.

---

## Blockers — the site cannot do its job

**1. The contact form silently discards everything typed into it.**
`onsubmit="return false"`, no handler, no message, no error. This is where every
CTA on the site points: Join, Register interest, Ask a question, Volunteer,
Become a sponsor, Enquire about hire. A visitor who wants to join the club has
no working way to say so. Still stubbed in current code.
*Done when:* the form sends, the sender sees confirmation, and a failure says so.

**2. The newsletter signup does nothing.** Same stub, in the footer of every
page. No validation, no confirmation, no request. Still stubbed in current code.

**3. Member login does nothing.** The form submits, nothing happens — no
request, no error, no redirect. There is also no "forgot password" link.
*Verify after update.*

**4. On a phone, "Contact" and "Log in" cannot be tapped.** The demo look
switcher is pinned over the bottom of the open menu, so the last two items are
unreachable at 390×844. Confirmed by hit-testing, not just by eye.

**5. Anything outside the plugin's own pages has no design at all.** The 404
page, all six blog posts, category archives and every product page render as raw
unstyled HTML — blue links, serif body text, no header, no footer, no way back
to the club. These URLs are in the sitemap and indexable, so this is what a
search visitor can land on.

**6. Add to Cart fails and never recovers.** The checkout request returns 403;
the button spins forever and retries indefinitely with no message to the
customer. The 403 may be the read-only support window — re-test with it closed —
but the silent infinite retry is a real defect either way.

**7. Every product image is broken.** All product photos are hotlinked from
`app.surecart.com` and return 404.

## High — the main journeys dead-end

**8. There is no way to actually join.** All four membership tiers (£12–£45/mo)
have a "Join" button that goes to the contact page. SureCart is installed with
eleven products and takes no part in it.

**9. Sport and team cards are not links, and no sport or team pages exist.** Nine
sports and twenty-four teams are the reason people visit a club site; a visitor
can read a name and a squad count and go no further.

**10. News goes nowhere.** Homepage news cards and the ticker aren't links, and
there is no News page. Six real posts exist and are reachable only by URL.
*Verify after update* — a News page shipped in v0.55.

**11. The booking system still contains LatePoint's sample data.** "Coaching
Session 1/2/3" with stock boilerplate, "Location 1 – London", "Court 2 –
Manchester", "Court 3 – Liverpool" for a Marlow club, staff named Kathy Smith,
Peter Jones and Samantha Davies, and every price £0.00.

**12. The booking flow looks like a different website.** System fonts, green
buttons, default modal — none of the club's typeface, colour or button style.
Same for the calendar embed.

**13. Booking hours contradict the site.** The footer advertises Mon–Sun,
7:00am–10:00pm. Booking offers Mon/Thu/Fri/Sat/Sun only, 8:00am–4:00pm.

**14. The booking phone field defaults to the United States** (+1,
placeholder 201-555-0123) on a UK club site.

**15. Every plugin page has two `<title>` tags in its head** — the page title and
the site default. Search engines pick one. On v0.51.1 there is also no meta
description, canonical or social-share tag; those landed in v0.58.
*Verify the duplicate title is gone after update — I found no title filter in
current code.*

**16. Products, posts and archives are indexable but unreachable.** They are in
the sitemap with no link to them from anywhere on the site, and there is no shop.

## Medium — visible damage and accessibility

**17. The header nav wraps "Book a court" onto three lines** at 1440px and
below, on every page. It reads as broken.

**18. The hero headline breaks mid-word on mobile:** "One communi / ty."

**19. The demo look switcher covers content everywhere** — the hero CTAs on
mobile, cards mid-page on desktop, the footer. It needs to sit out of the way or
be collapsible.

**20. Every image on the club site is a grey placeholder** — sports, teams,
news, page heroes, the contact map, sponsor logos. There are 160 items in the
media library.

**21. The numbers contradict each other.** "Nine sports", six listed. "Twenty-four
teams", four listed. Squash appears in the About timeline and in the booking
system but not in Sports.

**22. Dates are stale or wrong.** Homepage fixtures are 12–19 Jul (past). The
Calendar's "Fixtures & results" shows only June and July — no upcoming fixture
on a page headed "Every game, all season". Events lists 26 Jul under "Upcoming".

**23. An HTML entity is showing as code:** "Summer BBQ &#038; Family Day" on the
Events page.

**24. Links are announced as list items to screen readers.** The hero quick
tiles and the social links carry `role="listitem"` on the `<a>`, which overrides
the link role — a screen reader user is told "list item", not "link".
`class-sections.php` lines 296, 414, 1225.

**25. The Sports/Teams and Fixtures/Events switchers have no tab semantics** — no
role, no aria-selected, no aria-controls. Four unlabelled buttons with no
indication of which is on.

**26. Heading order skips on every page** — h2 to h4 in the footer, h1 to h3 on
Membership, h1 to h4 on Login.

**27. The booking modal traps you.** Escape doesn't close it, the page scrolls
behind it, and focus isn't held inside.

**28. Without JavaScript the site is blank below the hero.** Everything is
`opacity: 0` until a script reveals it. Current code documents a no-JS fallback;
the deployed build doesn't have one. *Verify after update.*

**29. Three external links on the booking page use `target="_blank"` with no
`rel="noopener"`.**

**30. Booking is offered twice, two different ways** — a card list at /booking/
and a calendar at /calendar/ — with no link between them and no explanation of
the difference.

**31. No privacy policy, terms or cookie notice anywhere,** while forms collect
name, email, phone and free text. Plugin pages have no copyright line either.

**32. The site is branded "305Media" but the content is "ClubHouse"** — header,
footer and every browser tab.

**33. Smaller things:** the third event card has no CTA where the other two do;
"Meet the committee" and "Book a visit" on the About page both go to the contact
page; sponsor tiles read "Sponsor 01–06"; the booking flow says "Available
Coaches" then offers "Any Agent"; Kathy Smith's card shows her name twice; there
is no search; filtered list URLs are indexable duplicates sharing one title; the
plugin folder `latepoint-pro-features-old` is still active and loading assets on
every page.
