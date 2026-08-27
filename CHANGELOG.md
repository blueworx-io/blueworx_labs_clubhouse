# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.92.0

- **You can now see what every update changed** — a What's new screen under Clubhouse, listing every release in plain English with the version you are running marked. The recent ones are laid out in full and the rest of the history folds away underneath. Releases that only changed how the plugin is built say so rather than pretending otherwise.

## 0.91.2

- Development tooling only — nothing changes on your site. The local test setup can now run a real shop alongside the plugin, so the parts that only exist next to SureCart can be checked properly before they are released.

## 0.91.1

- **The "your shop is not ready to take payments" warning no longer appears at all** — the order confirmation page behind it is now made as soon as Clubhouse and your shop are both installed, whichever order you added them in. Nobody has to spot the warning and press a button. A club that has deliberately removed its own confirmation page still keeps that decision.

## 0.91.0

- **Members can now change their own name, email and password** — the member area showed the details the club keeps for billing and nothing about the member themselves, so there was no way in. Their own details sit at the top of Account now, with an Update link to change them.

## 0.90.0

- **Members can change things in the member area again** — Update on billing details, adding or removing a card, payment history, opening an order or an invoice, and changing or cancelling a plan all led back to the same read-only screen and did nothing. Every one of them now opens the screen it says it will.

## 0.89.0

- **Emails now come from your club, not from "WordPress"** — a password reset used to arrive from WordPress at a wordpress@ address, identical on every site we run. It now carries your club's name and an address on your own domain. If your club has a real mailbox members should be able to reply to, put it under Setup → Members.

## 0.88.2

- **The "your shop is not ready to take payments" warning can now be cleared** — the order confirmation page it complained about is created for you, like the other shop pages already were. Nobody has to walk through SureCart's setup to make the warning go away.

## 0.88.1

- **The savings badge no longer invents a saving** — it used to multiply whatever price sat on the Monthly side by twelve, whatever that price was actually charged per. A tier priced yearly on both sides claimed a saving of eleven more years of it. There is now only a badge where the two prices really are one a month and one a year.

## 0.88.0

- **Each team can now link to its own page elsewhere** — a league table, a governing-body squad page, wherever the section already keeps things up to date. Add the address to the team and a button appears on its card on the Teams page and on the team's own page. Leave it empty and no button shows at all.

## 0.87.3

- **The news ticker now runs without a jump.** It used to scroll partway along the headlines and snap back to the start; it now loops round continuously.

## 0.87.2

- Internal notes only — nothing changes on your site. The list of what to work on next was rewritten to match what is actually open.

## 0.87.1

- Test suite only — nothing changes on your site. Tests that open a WordPress admin screen are given the time that actually takes, so a passing test stops being reported as a failure and a real problem is easier to spot.

## 0.87.0

- **Your pages are now found the way every other WordPress page is found.** The plugin's own private routing is gone, which is where the maintenance saving from this milestone lands. Nothing about your site changes.
- **Old addresses still work.** Anything pointing at the previous form of an address is sent to the page itself, so bookmarks, newsletters and links from elsewhere keep working.
- **A single sport or team now has a slightly different address** — `/sports/?clubhouse_item=rugby` rather than `/sports/rugby/`. The old address forwards to it. Those were never real pages on your site, and this is the one visible change.

## 0.86.1

- Checked that a site built on the previous version upgrades cleanly, and wrote down what happens — including what stays behind if you roll back. No change to the plugin itself.

## 0.86.0

- **Your club's pages are now real WordPress pages.** They behave like any other page on your site, which is what lets everything below work.
- **Switching a page off in Setup now unpublishes it**, so it disappears from your menu, your sitemap and search — not just from the site's own navigation. Switching it back on republishes it.
- **A club page's status can no longer be changed from the Pages list**, by Quick Edit or Bulk Edit. Setup is the one place that decides whether a page is on, so the two can never disagree.
- **Links to your pages now come from WordPress itself**, so they stay correct if you change your permalink settings or move a page.

## 0.85.1

- **The checkout and thank-you pages no longer carry your site's footer as well as their own.** Both now fill the page on their own, the way they were designed to, instead of sitting inside the rest of your site with a second header above and a second footer below.

## 0.85.0

- **Your checkout is now your club's own.** It carries your club's name and crest, your terms and privacy links, and the same look as the rest of the member area, instead of a page that looked like a different product.
- **You never have to build a checkout form.** One is set up for you, ready to take payments, and you can still change it if you want to.
- Card details are still handled entirely by Stripe. The club never sees them.

## 0.84.2

- **A club with no shop no longer sees empty Billing and Account screens** in the member area. They only appear once the shop is set up, so members are not offered menu items that lead nowhere.

## 0.84.1

- **Space between the cards on each member area screen**, which used to sit flush against each other, and a tighter gap between the shortcut tiles.

## 0.84.0

- **The Billing page on a phone now shows your membership plan** above your orders and invoices, so everything you pay for is on one screen.
- **The extra Orders, Invoices and Plans links are gone from Billing** — the page carries all three itself now.

## 0.83.6

- **Orders, Invoices and Plans now sit under Billing on a phone, and only there.** They used to appear at the foot of every screen, which read as a stray menu following you around.

## 0.83.5

- **"Back home" now looks like the rest of the side menu**, arrow and all, instead of a small grey line under it. It still sits at the foot of the column, and on a phone it stays in the bottom bar.

## 0.83.4

- **No more underlines on the menus, tabs and shortcut cards** in the member area — only real links in your writing are underlined now.
- **The heading at the top of each member area screen is smaller**, so it sits better against the rest of the page.

## 0.83.3

- **The top bar reads as one block.** The heading and the line under it sit closer together, and the buttons on the right now line up with the middle of them instead of the bottom.
- **Buttons no longer look like links.** The underline that had crept onto every button drawn as a link has gone.

## 0.83.2

- **Your favicon now shows in the corner of the member area.** If you have set one, it is used as the mark next to your club name — it is square, so it fits the small box far better than a wide logo. Your logo is still used when there is no favicon.
- **Removed a gap above the panel on every member area screen**, so each section starts where it should.
- **The booking plugin's own "Logout" link and "Welcome ..." heading are hidden** on the bookings screen — the member area already has both, so members no longer see them twice.

## 0.83.1

- **A member's own name and picture no longer take up room at the top of a phone screen.** The row there now carries the section they are looking at, its description and the way out, and nothing else. On desktop the side menu still shows who is signed in.

## 0.83.0

- **The member area on a phone now uses the top of the screen for what a member is actually looking at.** The section's name and description sit across the top where your site name used to, sign out is in the top right, and the separate heading below it has gone — so a member sees their bookings or their bill sooner, without scrolling past a repeat of the title. "Back to the club site" now reads "Back home", which fits the tab it lives in. On desktop the side menu stays put as the page scrolls, and scrolls within itself if a club ever has more sections than fit.

## 0.82.0

- **The row of tabs on a phone is now a fixed set: your dashboard, bookings, billing, account, and the way back to your club site.** Billing and account are always there, even before you have added a shop — a member who taps one is told plainly that part is not set up yet, rather than finding the tab missing. Bookings appears once you have the booking plugin. The way back to your site has moved into that row, so it is where a thumb can reach it instead of at the top of the screen. Your desktop side menu is unchanged, with orders and invoices still listed separately.

## 0.81.1

- **The row of tabs along the bottom now shows on a phone even before you have added a shop or bookings.** It used to appear only once there was more than one section to move between, so a new club saw a different member area from an established one.

## 0.81.0

- **The member area now looks the way it was designed, and moves like an app.** Your club's name and badge sit at the top of a proper side menu, with the member's own name at the foot of it, and the page title tells them where they are. Moving between bookings, orders, invoices and the rest no longer reloads the page — it switches instantly, and the back button still works. On a phone the side menu becomes a row of tabs along the bottom, thumb height, so a member can get around one-handed.

## 0.80.0

- **Your members get their own page on your site, and you get one place to manage it.** The member area now lives at /member-dashboard/ on your own address, listed with your other pages and switchable off under Setup like any of them. Signed in, the Log in button in your header becomes Member area, so a member is always one click from their bookings, orders and membership. Anyone with the old link is taken to the new page automatically. WordPress's own Pages screen is gone from your menu — everything you edit lives under Club Pages and Setup now. Nothing has been deleted: the pages your shop and booking plugin rely on are all still there, doing their job quietly.

## 0.79.0

- **Your members now have one account page instead of three plugins in a stack.** Signing in takes a member to a proper member area: a menu down the side for their bookings, orders, invoices, membership and account details, with each one still run by the plugin that owns it. Your welcome pack greets them at the top. If you have no shop, or no bookings, those menu items simply are not there — nothing to set up and nothing to switch on. Your checkout and thank-you pages now match it, and every address on your site stays exactly as it was. One thing to know: if you had typed anything onto the account page itself, it is no longer shown there — the new member area takes the whole page over. Nothing has been deleted, and anything you want members to read there belongs in your welcome pack, which greets them at the top of the same page.

## 0.78.0

- **Members can now be shown monthly or annual prices.** Give a tier an annual price as well as a monthly one, under Club Pages → Membership → Tiers, and your Membership and Home pages get a Monthly / Annual switch above the tiers. If paying annually works out cheaper, the card says how much a member saves. A tier priced only one way simply shows that price and says so, and nothing you have already entered changes — your current prices are the monthly ones.

## 0.77.0

- **Your Facebook or Instagram posts can now sit on your Home page.** Paste the link to each post you want shown under Club Pages → Home → Social feed, then switch the section on under Setup → Visibility. It arrives switched off, so nothing on your site changes until you turn it on, and it stays off the page until you have pasted at least one post. Connecting your account directly, so posts arrive on their own, comes later.

## 0.76.2

- **The welcome pack now greets a member at the top of their account page.** It used to sit right at the bottom, under everything else, where a new member would only find it by scrolling. It is now a banner at the very top, in your club's colour, with the welcome pack link as a button rather than a line of underlined text. The wording is unchanged and still edited in the same place.

## 0.76.1

- **The Stories page no longer has a hole in it.** The category buttons sat so far below the featured story that the page looked broken. They now sit under it, on both a computer and a phone. Nothing else on the page has moved.

## 0.76.0

- **Club Pages is back, and it is where you edit your site again.** The Pages and Blocks screens have gone: building a page out of blocks did not turn out to be a better way to run a club site. Everything you have written is exactly where it was, your site looks the same, and the Visibility tab is back under Setup for taking a section off a page.
- Everything else from the last few releases stays: a page switched off still returns a proper "not found", one half-written item still leaves a gap rather than taking the page down, and the privacy and terms pages still carry example wording clearly labelled as an example.

## 0.75.0

- **Fixed: every page of a live site went down.** The last release broke the front end on real WordPress — every address returned an error. Caught before it reached anyone; the browser tests now cover it.
- **Fixed: the welcome pack was missing on existing sites.** A club that had already updated once never got the new welcome block, so its wording was unreachable. Updating now puts it back, wording and all.
- **A page switched off now properly does not exist.** Its address used to answer as if all were well and show whatever WordPress had lying around. It now returns a proper "not found", for visitors and for search engines alike.
- **One bad entry no longer takes a page down.** A half-written item — from an import, or hand-edited — used to white-screen the whole page. It now renders with the missing part blank and everything else intact.
- **The privacy and terms pages read as finished pages.** Where only a club can answer, they carry example wording clearly labelled as an example. Replace every one before you take real sign-ups: they are a starting point, not a policy.

## 0.74.0

- **One place to edit your site.** The old Club Pages screen has gone. Everything it did now lives under Content — Pages for what each page is built from, Blocks for the words and pictures in each block. Your site looks exactly as it did and nothing you have written is lost.
- **The Visibility tab has gone too.** Taking a section off a page is now removing its block on the Pages screen, rather than a separate list of switches somewhere else.
- **Content editors can still edit content.** Club Pages was the only screen that role could open, so the two new screens open to it as well.
- **The welcome pack is a block like any other**, with its own on/off, edited under Content → Blocks.
- Header and footer are now edited once for the whole site. The per-page header and footer switches are gone.

## 0.73.2

- Internal only: the repo's own priority list now reflects what has shipped, and the two new screens' browser tests no longer depend on the order they run in. Nothing changes on a site.

## 0.73.1

- **Words edited under Blocks now stick.** Saving Club Pages or Setup used to put the old wording back over anything typed on the new Blocks screen, because both screens were still writing to the same site from different ends. They now keep each other in step, so whichever one you reach for, your words survive.
- **The user guide points at the new screens.** Its content chapter now explains blocks and shared blocks, and its links go to Content → Pages and Content → Blocks rather than the old editor.

## 0.73.0

- **A screen for the words themselves.** Content → Blocks lists every block your site is built from, grouped by kind, each saying which pages use it. Open one and edit its words the same way you always have — and if it is on more than one page, it says so before you start, because changing it changes all of them. Make a new block, rename one, or duplicate one when a shared block has to differ on a single page. Deleting a block that is in use asks first and names the pages.

## 0.72.0

- **A screen for arranging a page.** Content → Pages lists your pages down the side and shows what the chosen one is built from, top to bottom. Take a block off a page without deleting it, put an existing one on so both pages show the same words, or make a new one of any kind. The page's own on/off switch lives here too. The header and footer are pinned top and bottom, because they are on every page.

## 0.71.0

- **Every page is now built from a library of blocks.** Until now each page was a fixed recipe written into the plugin and all you could do was switch a section off. Your pages are now assembled from named blocks instead, which is what makes editing something once and reusing it possible. Your site is unchanged — every page renders exactly as it did, your words and your switched-off sections carried over on update, and Club Pages still edits everything. The screens for arranging pages and managing the block library come next.

## 0.70.3

- **Saving Club Pages no longer switches the cookie notice and announcement bar off.** Both are on until a club turns them off, but the editing screen drew their switches as off on a site that had never touched them — so one save wrote that back and both disappeared, without anyone choosing it. The switches now show what the site is really doing, and saving changes nothing you did not change.

## 0.70.2

- **Give new members a welcome pack on their account dashboard.** Write it once under Club Pages → Global → Welcome pack — the gate code, where to park, who to ask — and anyone who reaches the dashboard sees it. Leave it empty and nothing is shown, and it has its own on/off switch like every other section. It uses the dashboard's own plain styling rather than the club look, because that page belongs to the shop.

## 0.70.1

- **Share a news story from the story itself.** Facebook, WhatsApp, email and copy link at the foot of every post, so a match report can go straight to a team group chat instead of being copied out of the address bar. These are ordinary links rather than the networks' own buttons, so nothing about your readers is sent anywhere until they choose to share.

## 0.70.0

- **Move straight from one news story to the next.** The foot of every post now offers the story either side of it, named rather than a bare arrow, so a reader can work along the club's news without going back to the index each time. The newest and oldest stories show only the half that leads somewhere.

## 0.69.6

- **News cards now look like cards, and sit level with each other.** The lead story was a proper card while the ones below it sat bare on the background, and a single long summary stretched every card beside it. Summaries are now cut to three lines, so a row lines up whatever the club has written.

## 0.69.5

- **Pages no longer scroll sideways on a normal laptop window.** Between roughly 900px and 1230px wide the menu was too big for the window but still tried to sit across the top, dragging every page off to the right. The menu now folds into the button it already uses on smaller screens while there is still room for it, in all three looks.

## 0.69.4

- **Search & sharing and the User guide now say who can reach them.** Every other Clubhouse screen shows an administrator which roles can open the page they are on; those two were silent. Nobody's access changed — the two screens simply now report it like the rest.

## 0.69.3

- **No more stray blank tag under every news post.** A small empty pill sat below the body of every post on the site. An untagged post was reporting one nameless tag instead of none, and the page dutifully drew a chip for it.

## 0.69.2

- **The News page header has room to breathe again.** Its heading sat jammed against the menu bar with the second line touching the bottom edge of the dark band, on every club site and in every look. The band had asked for a spacing step that was never added to the scale, which quietly threw away the padding at both ends at once.

## 0.69.1

- **Import and the User guide moved to the Clubhouse menu.** They sit with the menu builder and the rest of the club tooling instead of being tucked under Club Pages. Who can open each one is unchanged.

## 0.69.0

- **The menu builder moved to the Clubhouse screen.** Arranging the header menu now sits with the rest of the site-wide settings, as its own tab, instead of being tucked inside Club Pages. Content Editors can still reach it — the screen shows them the Menu tab and nothing else.

## 0.68.9

- **The Calendar page says when a club has no fixtures.** Before, the fixtures list simply vanished, leaving a page titled "Fixtures & results" showing nothing but the court-booking grid — which read as if the bookings were the fixtures. It now says there are none yet, and the sport filters stay hidden until there is more than one sport to choose between.

## 0.68.8

- **Committee and directory people can have a photo.** Add one from the media library on each person; anyone without one keeps the initials block, so a club with only some headshots still looks tidy and the cards stay aligned.

## 0.68.7

- **No more empty grey picture box at the top of a page.** About, Membership and Book a court each showed a large blank frame with a broken-image glyph where a hero image would go. The page now only draws that frame once an image is actually chosen, so a club that never sets one sees clean pages.

## 0.68.6

- **The sponsors band disappears when there are no sponsors.** A club with none used to get the heading and a "Become a sponsor" button over an empty strip, which advertised the absence. Sponsors you add in the admin still show as before.

## 0.68.5

- **Save always sits in the same place.** On the Clubhouse admin screens the Save button slid to the left whenever the "unsaved changes" note was hidden, so it moved about depending on whether you had touched anything. It is now pinned to the right on every screen, and the menu builder’s Save matches the rest instead of looking like a stray WordPress button.

## 0.68.4

- **The sport filters now sit with the fixtures they filter.** On the Calendar page they were up in the header, above the court-booking calendar they have no effect on. They now sit directly above the fixtures & results list, and stay there when the booking section is switched off.

## 0.68.3

- **The calendar's dates are readable again.** The booking calendar printed a large faded month name across the first week's date squares, clipped mid-word. That decoration is now hidden; the calendar itself is untouched.

## 0.68.2

- **Every upcoming event now offers a way to take part.** One of the three shipped without a button, so it looked broken next to the other two. Past events still carry none, which now reads as deliberate because they all agree.

## 0.68.1

- **The About page's buttons now go where they say.** "Meet the committee" jumps to the committee further down the page instead of the contact form, and "Book a visit" — which led to a contact form that takes no bookings — now reads "Arrange a visit".

## 0.68.0

- **Booking now reads as one journey instead of two unrelated pages.** Book a court lists what you can book, where and with whom; the calendar shows when it is free. Each now says what the other is for and links to it, and the Bookings page no longer offers to pick a time it cannot show you.

## 0.67.1

- Recorded what the team is working on next, in the repo rather than in someone's head. No change to the plugin itself.

## 0.67.0

- **Your site now has a privacy policy and terms**, linked from the footer of every page. Both ship with starter wording that describes what this site actually collects and where it goes. Anywhere it says ADD, only your club can answer — those lines need writing before you take real sign-ups. It is a starting point, not legal advice.
- **A cookie notice appears once for each visitor** and says, in plain words, what cookies this site uses. It does not pretend to block anything, because the shop and its payment provider set theirs the moment those pages load — if you need real consent gating, switch this off under Club Pages → Global and use a consent plugin.
- **Fixed empty buttons appearing under a heading** when a page had no call to action set. They rendered as two blank boxes that reloaded the page.

## 0.66.0

- **The plugin now recognises that SureCart is installed.** It was looking for the wrong thing, so on a real site it decided there was no shop — which quietly disabled everything to do with selling, including tier prices and Join buttons. This is why membership tiers never sold on a live site despite working in testing.
- **The plugin now tells you when your shop is missing pages it needs** — the checkout page, the order confirmation page, the customer dashboard or the shop page — and says what each one costs you. A missing checkout page is why Join buttons have been sending people to the contact page instead of taking payment. One button puts back what SureCart can, and it asks SureCart to build its own pages rather than making copies of them. Nothing happens without you pressing it.
- **A deleted checkout page no longer produces Join buttons that lead to a "page not found."** They fall back to your contact page, as they should.
- **Clubs with a shop now get a Shop link in their navigation**, and can point any menu item or button at the shop or at a member's account. Products used to be findable from Google while nothing on the site linked to them. Clubs without a shop see no change.
- **The Membership page no longer contradicts itself.** It promised "join in five minutes" above steps that said register your interest, no payment yet, and we'll be in touch in a few days. It now says whichever is actually true: a club whose tiers reach a real checkout talks about joining and paying, and one whose tiers don't talks about registering interest. Any wording you have written yourself is untouched.
- **Members with a shop connected now land on their account dashboard after signing in**, instead of the front page, so they can see and manage what they have paid for. You can still send them anywhere you like on the Members screen — this only applies when you have left that box empty.
- **Signing out now returns members to your front page**, which is what the Members screen has always said it does. It used to leave them on a sign-in form they no longer needed.
- **Price changes in the shop now show on the site straight away** rather than up to five minutes later.

## 0.65.0

- **Membership tiers can now sell.** Connect a tier to a product in your shop and the card shows what that product charges, with its button going straight to checkout with the right membership in the basket. Change a price in the shop and the page follows. Tiers you don't connect are unchanged — they show the price you type and point at the contact page.
- **Fixed a Join button that went nowhere.** Custom membership tiers had no way to set where their Join button linked to, so the button could end up pointing at nothing. It now falls back to your contact page.

## 0.64.0

- Groundwork for the new page editor: blocks can now be stored as a reusable library, and each page can record which of them it shows. Nothing changes on the site yet.

## 0.63.3

- **The plugin zip is now named after the version it contains**, and building a new one removes the old, so the folder can never hold two builds that look alike and the wrong one cannot reach a club site by mistake. The folder inside the archive is unchanged, so WordPress still treats every release as the same plugin.

## 0.63.2

- **The demo mode switcher no longer sits on top of the home page's buttons.** It was a bar across the bottom of the screen, covering two of the hero's call-to-action tiles at every screen size. It is now a small "Demo" button in the corner, which opens when you click it.

## 0.63.1

- **Search engines now see one page per sport and team, not one "Sports" page repeated.** Each section page carries its own name and its own address, so it can be found and shared. Without this the new pages would have been treated as copies of the list and dropped.
- **Filtering a list no longer creates a near-duplicate page.** Views like Sports filtered to Hockey are no longer offered to search engines as pages in their own right, so they stop competing with the full list.

## 0.63.0

- **Every sport and every team now has its own page.** The cards on Sports, Teams and the home page were a name and a strapline that went nowhere — which is the main thing most people come to a club site for. Each one now opens a page with the section's own words, when it trains, who to ask, and its fixtures.
- **Two new things to fill in per section:** training times and a contact name and email, under Collections → Sports or Teams. Anything you leave empty is simply not shown.
- Pages live at addresses like /sports/rugby/ and /teams/1st-xv/, so they can be linked and shared. Rename a section and its address follows the new name.
- A link to a section that no longer exists lands on the full list rather than an empty page.

## 0.62.0

- **The page no longer contradicts itself.** Headlines counted "nine sports" and "twenty-four teams" on a club that listed six and four. The numbers are now counted from your own sports and teams, so the claim and the list beneath it always agree.
- **Events retire themselves.** Add the date an event ends and it moves to "Recently at the club" on its own the day after. Previously the club open day sat under "Upcoming" nine days after it happened, until someone changed it by hand. You can still mark something past early; nothing will drag a finished event back.
- **No more buttons that go nowhere.** An event with a button label but no link showed a button that just reloaded the page. It now shows one only when there is somewhere to go.
- Picture descriptions name your club rather than saying "ClubHouse" on every site.

## 0.61.1

- **Shop pages are left entirely alone now.** The customer dashboard, checkout, order pages and products no longer pick up the club's header, footer, fonts or colours — Clubhouse was fighting SureCart's own design instead of sitting alongside it. They stand on their own.
- The "page not found" screen and category listings still carry the club's design, as before.

## 0.61.0

- **The contact form and newsletter signup now work — or aren't shown.** Both looked real and did nothing: someone could type out an enquiry, press send, be told nothing, and the club would never hear from them.
- **To take enquiries, paste a SureForms shortcode** into Club Pages → Contact → Contact form. Do the same under Global → Footer for newsletter signups.
- Until you do, the contact page offers your club email instead, and the footer signup box is hidden. Nothing on the site now asks for details it will not act on.

## 0.60.3

- **A wrong web address now looks like your club, not a broken page.** The "not found" page and category listings used to come out as plain text on white — no header, no footer, no way back. They now carry the club's design like every other page.
- This matters most for someone arriving from a Google result, which is exactly where those addresses turn up.

## 0.60.2

- **The footer now signs off properly.** Your club's name runs across it at poster scale, and there is a copyright line underneath — it picks up the year on its own, so it will still be right next January.
- Each look renders the big name in its own typeface, so it reads as part of the design rather than bolted on.

## 0.60.1

- **Links and buttons are big enough to tap.** Menu items, footer links, email addresses, the map link and the filter pills were as small as 20px on a phone, so they were easy to miss. They are all at least 44px now, on every look, and nothing has moved on desktop.
- **The page can no longer end up blank.** Sections fade in as you scroll, and if that ever broke halfway the rest of the page stayed invisible. It now hands the content back within a couple of seconds if anything goes wrong.
- Pages now declare UK English, so screen readers use a British voice and browsers stop offering to translate.

## 0.60.0

- **Plugins are called what they are called.** SureCart, LatePoint, SureForms and the rest now show their own names everywhere in the admin, instead of substitutes like "eCommerce" and "Bookings". Looking up help for one no longer means working out which plugin it really is.
- **"Club Content" is now "Club Pages"** in the menu, the page heading and the browser tab. Nothing has moved and existing bookmarks still work.
- **The booking page is "Bookings" in the menu**, not "Book a court", which wrapped onto three lines. The page itself still says "Book a court". A club that has renamed the item keeps its own wording.

## 0.59.0

- **The news on your home page is now your actual news.** The "From the clubhouse" band shows your three most recent posts — headline, category, date and picture — and each one opens the story. It used to be three headlines typed in by hand that went nowhere.
- **Nothing to set up.** Publish a post and it appears. Until you have published anything, the band keeps showing whatever is written under Club Content → Home, so a new club never sees an empty space.

## 0.58.3

- **News is reachable at last.** The News page shipped with nothing linking to it, so it could only be found by typing the address. It now sits in the footer alongside the rest of the club, and the news section on the home page ends with a link through to it.
- Clubs that have already customised their header menu can add News to it themselves under Club Content; the change only affects menus nobody has edited.

## 0.58.2

- **The menu no longer wraps onto three lines.** "Book a court" was breaking across three lines in the header on ordinary laptop screens, leaving the menu looking broken on every page.
- **The headline on a phone no longer breaks in the middle of a word** — "One communi / ty." now reads as it should.
- **Demo mode gets out of the way.** The look switcher was sitting on top of the open menu on a phone, so "Log in" could not be tapped at all. It now hides while the menu is open, and tucks into the corner on small screens instead of covering the middle of the page.

## 0.58.1

- **Apostrophes and ampersands read properly again.** Headlines and summaries taken from your posts were showing raw code where a punctuation mark should be — "Mental &#038; Physical Challenges" instead of "Mental & Physical Challenges".
- **Buttons and icons that go somewhere are now announced as links.** The quick links under the home page headline, and the social icons, were being read out as list items, so anyone using a screen reader was not told they could be followed.
- **The Fixtures/Events and Sports/Teams switches now work properly with a keyboard and a screen reader**, including the arrow keys, and say which of the two is showing.
- **Each page now sends one title to Google, not two.** Every page was sending both its own title and the site's, leaving search engines to pick.
- **Headings no longer skip levels**, so the outline a screen reader reads out matches the page.

## 0.58.0

- **A User guide screen, under Club Content.** Plain-English explanations of everything ClubHouse does — what each screen is for, what each page is made of, how to change the words and pictures, what the lists of teams and fixtures are, and how the look and colours work.
- **It describes your site, not a generic one, and it keeps itself right.** It is built from the site as it stands each time you open it, so a page you switch off is reported as switched off, a section you hide is named as hidden, an empty list says it is empty, and anything added to ClubHouse later appears in the guide on its own. Nothing to keep up to date by hand.
- It only covers ClubHouse. Other plugins are theirs to explain.

## 0.57.0

- **Your pages now describe themselves properly to Google and to anything they get shared into.** Each page carries a description, its own web address, and a preview card with your club name and logo, so a link posted to Facebook or WhatsApp shows the club rather than a bare URL.
- **A new Search & sharing screen, under Clubhouse, tells you how each page reads.** Titles that are too long to fit in a search result, descriptions that are too short to be useful, images with no description, more than one main heading — in plain sentences, with nothing to score or chase.
- **If you already run Yoast, Rank Math or similar, ClubHouse stays out of the way** and adds no tags of its own, so nothing is written twice. The report still works either way.

## 0.56.0

- **The member login page is a real login now.** Signing in goes through WordPress itself, so anything else the site has installed still applies — two-factor, login limiters, security plugins — and when one of them refuses a sign-in, its own explanation is what the member reads.
- **Members who forget their password can sort it out without leaving the club site.** Asking for a reset link, and setting the new password, both happen on the club's own login page in the club's own look, instead of dropping the member onto the bare WordPress login screen halfway through.
- **You can choose where members land after signing in and after signing out** — Setup → Members. Leave either blank to send them to your front page. A member who was heading somewhere else when they were asked to sign in is returned there instead, and an address on another website is ignored, so the login page cannot be used to bounce people off your site.
- Signed-in members get a working "Log out" in the header where "Log in" used to be.

## 0.55.0

- **The club has a news section.** A News page listing everything the club has published — newest story given the top spot, the rest in a grid, filtered by category and paged when there is enough to page.
- **Stories themselves now look like part of the site.** A match report reads as a proper article: headline, standfirst, byline, full-width photo, pull quotes, tags, an author note and three more stories to read at the end. Before this, a post fell through to the bare theme with no header, no menu and no footer.
- **You write them as ordinary WordPress posts** — nothing new to learn, and everything the club has already written keeps working.
- The article sets its own spacing rather than inheriting the gaps between the bands of a landing page, so a byline sits with the paragraph it belongs to instead of a screen away from it.
- Both screens use your chosen look, your colours and your typefaces, with the same header and footer as every other page. The page head wording is yours to edit under Club Content → News, and the whole section can be switched off under Visibility like any other.

## 0.51.3

- **CI no longer builds and verifies the zip here; the shared guardrails do it for every plugin project.** This repo carried its own `package` job because nothing shared checked what reached the deployment artifact — the risk being `preview/index.php`, which defines its own ABSPATH and would render a full page, unauthenticated, on a live club site. The foundation now stages the tree every release would zip and fails the pull request if anything that must never ship survives, so the rule is enforced for every plugin rather than for this one.
- `bin/build-zip.sh` stays for building a zip by hand locally, but its allowlist is now a convenience rather than a second source of truth. Where the two disagree, the foundation's list is the one that decides.
- Nothing about what the plugin ships has changed.

## 0.51.2

- **CI now pins the shared foundation workflow to `@v1` instead of tracking its
  `main` branch.** Any change to the shared workflow used to land in this
  project's CI the moment it merged upstream, with no way to stage it. `v1` is a
  moving major tag that follows backward-compatible releases, so fixes still
  arrive on their own; a breaking change goes to `v2` and waits for a deliberate
  move here. `foundation_ref` is set to match — it defaults to `main`, so pinning
  only the `uses:` ref would run the v1 workflow against today's scripts.
  Nothing about the plugin itself changes.

## 0.51.1

- **Fixed: the ClubHouse - Content Editor could not edit content.** Club Content was locked with the one permission that separates the two Clubhouse roles, so the page the role exists to use was missing from its menu entirely. Content editors now get Club Content — the words, images and header menu on every page — as they were always meant to.
- **Nothing else changed for either role.** Club Content now has a key of its own, held by content editors, owners and administrators alike, so "may set the site up" and "may edit its words" are finally separate questions. The two roles still differ by exactly that one permission, and every other limit is untouched.
- **Import stays with owners and administrators.** It replaces every page's content in one go and can switch sections off — a setup change, not an edit — so it is deliberately not part of this.
- Existing sites pick the change up on update; there is nothing to do.
- Also fixed: five tests that check how another plugin's pages get the club header and footer were being run against the wrong test setup, so they failed on every local run. They now run only where they can work.

## 0.51.0

- **A second club colour.** Clubhouse theming now takes a secondary colour alongside the primary, and it reaches the whole site: secondary buttons, the markers inside call-to-action bands, filter hover states, and the Clubhouse admin screens themselves. The primary keeps every job it already had, so the two read as a hierarchy rather than competing.
- **You only pick the colour; everything that follows from it is worked out.** Hover, pressed, disabled, a subtle background tint and a readable text colour to sit on it are all derived — and derived against the look you are using, so "darker on hover" comes out lighter on the dark look instead of disappearing.
- **Leave it blank and you still get one.** An unset secondary is derived from your primary — a turn around the colour wheel at your own intensity, nudged only as far as it must go to stay readable. The setup screen shows you exactly which colour that is rather than leaving you guessing.
- **Real colour pickers, everywhere a colour is set.** Both colour settings now open WordPress's own picker: a swatch, a full spectrum, ten on-brand presets, a live preview as you drag, and a clear button. The hex box is still there and still editable for anyone who knows their brand code, and the fields keep working with JavaScript switched off.
- **You are warned about contrast as you choose, not after you save.** The setup screen checks your colour against the surfaces it will actually sit on and says so, live. A low-contrast secondary is saved with a warning rather than refused — it is spent on second actions, and a club that insists on its real brand colour should be told, not overruled. A low-contrast primary is still refused, as before.
- Every preset swatch offered is now guaranteed to be one the screen accepts. One of the first choices was a green that no text colour reads on, which would have been offered and then rejected.
- Fixed as part of the same audit: one hardcoded white in the Court Side band gradient now follows the look.

## 0.50.0

- **A new Settings → ClubHouse access page, for administrators only.** It lists everyone on the site holding a ClubHouse role, the role each of them holds, and every part of the Clubhouse they can actually open — plus the same read out per role and per section, so a question about access can be answered without logging in as somebody.
- **Read-only, deliberately.** Nothing on the page can change anybody's access; roles are still set on the Users screen. A page that both reported access and edited it would be a way to grant it by accident.
- **Every ClubHouse admin page now shows, in its top bar, which roles can open it.** Administrators only — an owner never sees the access map, on their own screens or anywhere else.
- Access is judged on both of the things that actually decide it: the permission the page is registered with **and** whether the page survives that role's menu. A page a role has permission for but which is not on its menu is not a page that role can reach, and is now reported that way rather than being overstated.
- The page reports what the site grants rather than a second copy of the rules, so it cannot drift from what is enforced.

## 0.49.0

- **The bundled plugins now read as what they do.** SureCart is **eCommerce**, SureForms is **Form Builder**, LatePoint is **Bookings**, SureRank is **SEO Rank**, SureDonation is **Donations**, SureContact is **CRM Management** and SureMail is **Mail Reports** — in the plugins list, down the admin menu, in submenu items and page titles, and anywhere a Clubhouse screen names one of them.
- **The name is all that changes.** Nothing about any plugin's own workings, screens, branding or licensing links has been touched, and no vendor file has been edited. Updates, licences and activation all work exactly as before, and the new names survive a plugin update — they are reapplied every time the screen is drawn rather than written into anything.
- The list is held in one place, so adding a plugin or changing a name is a one-line change.

## 0.48.1

- **Fixed: the highlighted phrase in a heading now always starts on the second line.** It used to break wherever the words happened to run out — "A club for every age and / ability" — which split the underline into two disconnected pieces and left one line running the full width and the next barely started. The highlight now gets a line of its own, so its underline or fill reads as one continuous mark.
- This is a rule of the heading itself, not a fix to one page's wording: it applies to every hero on the site, at every screen size, and holds however long or short the club's own heading is. Where a highlight is long enough to need two lines of its own, the words are now shared evenly between them rather than dropping a single word onto the last line.

## 0.48.0

- Added a Menu tab to Club Content: reorder, rename and nest header nav items, and point each one at a page, a section of a page, a filtered sport/team/event view, or a custom URL.
- Every editable section now carries a stable anchor id, so a menu item can link straight to it.
- The suggestion list behind every URL field in Club Content now offers section anchors and filtered collection views, not just the top-level pages.

## 0.47.2

- **Removed the attempt to give club owners LatePoint automatically.** Two releases tried to make an owner look like an administrator to LatePoint, and neither worked: LatePoint settles the question earlier than anything this plugin can reach. While it was in place it gave owners a stand-in administrator permission that bought nothing, so it has been taken out entirely. Owners are ordinary owners again, exactly as in 0.46.0.
- **To give owners LatePoint, add them in LatePoint itself:** LatePoint → Settings → Roles → Create Custom Role, tied to the ClubHouse - Owner role. That is LatePoint's own supported way of doing it, it takes a moment, and it survives LatePoint updates. The Clubhouse side is already in place and correct — the LatePoint menu appears for owners as soon as that role exists.
- Nothing else changed. The owner and content editor roles, their menus, SureCart access and every limit on both are exactly as they were.

## 0.47.1

- **Fixed: LatePoint still did not appear for club owners in 0.47.0.** The previous release told WordPress the owner answered to the administrator name, but not in the one place LatePoint actually looks. WordPress keeps a second copy of a user's role names alongside their permissions, and that copy is what a plugin reads when it asks "is this an administrator?" — it was left untouched, so LatePoint carried on saying no. Both are now kept in step. Proved on a live site: an account holding every administrator permission but a different role name was refused entry, and a real administrator was not, which is what pinned down the check being made.
- The recognition also now lasts for the whole of a LatePoint page load rather than only while the menu is built, because WordPress re-checks whether you may view a page after the menu has finished being assembled.

## 0.47.0

- **Club owners now get LatePoint, with nothing to set up.** LatePoint never used WordPress permissions: its Role Manager matches on role *names*, and its stock entry names the administrator role, so an owner was invisible to it however much access they were given. Owners are now presented to LatePoint under the name it already recognises, so the booking diary is fully theirs — no custom LatePoint role to create, on this site or any other.
- **Only the name, never the powers.** WordPress works out what an account can do when it loads, so a name added afterwards unlocks nothing by itself. Every limit is unchanged: an owner still cannot reach settings, plugins, themes, updates, tools, page editing or user management. The name is also only in place while the admin menu is built and while LatePoint itself is being used — never on the public site, and never anywhere else in wp-admin.

## 0.46.0

- **A second Clubhouse role: ClubHouse - Content Editor.** For the person who runs the club's words and its members, but not its money. They get Club Content, Collections, Posts, Media and Users — and nothing else. No Clubhouse Setup, no SureCart, no LatePoint, no plugins, themes, settings, tools or import/export.
- **The Owner is now everything the Content Editor is, plus the shop and the diary.** Both roles are built from the same capability map; the Owner simply also holds the key to Clubhouse Setup, SureCart and LatePoint. There is no longer any job the junior role can do that the senior cannot.
- **Both roles can manage members' accounts, and neither can touch a senior one.** They can view and edit ordinary users, and they can never create, delete, remove or promote anybody. Any attempt to edit an administrator, an Owner, or a BlueWorx role is refused outright — so no one can reset an administrator's password and take over the site. The refusal is enforced at the capability layer, which means it holds on every screen, every REST route, and inside any other plugin that asks.
- **Both roles are now cloned from the site's live editor role** rather than a hard-coded list, so a club that has adjusted its editor keeps that adjustment. Anything dangerous is then stripped back off, so an over-powered editor cannot leak into a Clubhouse role.
- **Fixed: LatePoint never appeared for the Owner.** LatePoint locks its menu with a capability of its own rather than the usual WordPress one, so no amount of general permission reached it. The Owner is now given whatever LatePoint and SureCart grant an administrator, read from the site at the moment the role is built — so it keeps working when either plugin renames something.
- Both roles are relabelled to read **ClubHouse - Owner** and **ClubHouse - Content Editor** in the roles list. Existing owners keep their role and their access; only the label changed.
- Comments are no longer a Clubhouse surface for either role, and neither is the raw Pages editor — pages are served by Clubhouse's own routing and edited under Club Content.

## 0.45.0

- **Club owners now get the shop and the diary.** SureCart and LatePoint have joined the owner's menu, and both are theirs to run in full — products, orders and shop settings on one side, bookings, agents and availability on the other. Neither plugin was reachable before: their menus are locked to capabilities the owner did not hold, so they simply were not there.
- **The Clubhouse menu is back on the menu.** Site setup was only reachable as a dashboard widget for owners; it is now a top-level Clubhouse item as well, sitting between the Dashboard and Club Content. The dashboard widget stays exactly as it was.
- The owner's menu now reads: Dashboard, Clubhouse, Club Content, Collections, SureCart, LatePoint, Posts, Media, Users, and their own profile.
- **Comments have gone from the owner's menu**, along with the comment-moderation capability behind it.
- **Nothing about the ceiling has changed.** The owner still cannot reach WordPress settings, plugins, themes, updates, tools, page editing, or any user management — the two plugins are unlocked, the screens that lock the site down are not, and the extra reach lasts only for the length of a request rather than being written onto the role.

## 0.44.0

- **SureCart's customer dashboard now looks like the rest of the site.** Pages another plugin owns — SureCart's account and customer dashboard — were being rendered by the bare theme underneath the Clubhouse, so they arrived with no header, no menu, no footer, no page width and none of the club's fonts or colours. They now open inside the same header and footer as every other page, and in the club's own type and colour. Nothing to switch on.
- **SureCart still styles SureCart.** The Clubhouse only frames the page: it adds the chrome around the outside and the club's fonts and colours, and applies nothing at all to SureCart's own buttons, forms and panels. The scroll animation used on Clubhouse pages is deliberately left off here, so nothing of SureCart's is ever hidden waiting to animate in.
- Pages that carry no clue they belong to another plugin — SureCart's checkout, for one — can be brought in with the `blueworx_clubhouse_dress_external_page` filter.

## 0.43.0

- **The booking calendar has moved to the Calendar page.** LatePoint's availability calendar now sits on Calendar, above the fixtures and results — a member looking at that page is working out when to play, and booking is what follows. Book a court keeps the three lists that tell you what you are booking: sessions and services, courts and locations, coaches and staff. Both the new section and its editable shortcode appear under Club content → Calendar → Book a court, and it has its own switch under Site setup → Visibility.
- Like the Book a court page, the new Calendar section vanishes completely without LatePoint — no section on the page, no toggle, no content fields. The Calendar page itself is unaffected: it has fixtures to show either way.

## 0.42.1

- **Fixed: a new page's web address 404s until permalinks are re-saved.** v0.42.0 added the Book a court page, and `/booking/` returned "Page not found" even though the page itself worked — the plugin told WordPress about the new address, but WordPress keeps a cached list of addresses and only rebuilds it when the plugin is activated. Updating by uploading a new zip over a running plugin does not count as activating it, so the cached list stayed as it was. The plugin now rebuilds that list once by itself after any update that changes its pages, so a new page works as soon as it is uploaded. Nothing to do by hand.

## 0.42.0

- **A "Book a court" page, built from LatePoint.** A new page carrying the whole booking journey in the order a member thinks about it: what you can book, where you play, who you book with, and when. Each of the four is a LatePoint shortcode, pre-filled and editable under Club content → Book a court, so a club can change the calendar view or the number of columns without waiting for a release.
- **The page only exists if LatePoint does.** Without LatePoint the page is gone completely — no nav link, no footer link, no entry in Club content, no switches under Site setup → Visibility, and the URL itself is not served. Nobody is left configuring a page that renders nothing. Install LatePoint and it reappears, with any settings you had made still in place; remove LatePoint and nothing is lost, it simply hides again.
- **Detection is by shortcode, not by plugin file.** The question that matters is whether the shortcode will actually render, so the check asks WordPress whether LatePoint's shortcode is registered. A plugin that is installed but not activated registers nothing, and would otherwise have produced a page of empty boxes.
- Each of the four sections can be switched off individually under Site setup → Visibility, like any other section. Clearing a shortcode field restores its default rather than hiding the section — an empty field means "use the standard one" everywhere else in the plugin, and this is no different.

## 0.41.0

- **Clubhouse can now render other plugins' shortcodes.** SureCart, SureDash, LatePoint and SureForms all publish shortcodes, and until now pasting one into a Clubhouse field printed it as text — every content field is escaped on output, which is what keeps club-entered content from breaking a page. There is now a dedicated **shortcode** field type that runs the shortcode instead of escaping it, so a checkout, a booking form or a member dashboard can sit inside a Clubhouse page. It is a distinct field type rather than a change to the existing ones: ordinary text and intro fields are escaped exactly as before, so this cannot leak into the rest of the content. Where these slots appear on the site is the next piece of work — this release is the capability.
- **The preview shows shortcodes as text rather than running them.** The design preview has no WordPress behind it, so there is nothing to run a shortcode against. It shows the shortcode literally instead, which is honest about what will appear on the real site without pretending to render it.

## 0.40.1

- **WordPress's own "Pages" menu is back for administrators.** The plugin hid it from wp-admin entirely, on the grounds that Clubhouse serves every page through its own routing so the built-in editor was redundant. That reasoning only held for pages Clubhouse owns. It does not hold for pages other plugins add — a SureCart customer dashboard, a LatePoint booking page — which exist as ordinary WordPress pages and became unreachable in the admin, with no obvious cause. Administrators now see Pages in the sidebar and "+ New → Page" in the admin bar again. Club owners are unchanged: they still do not see Pages, and stay inside the Clubhouse screens as before. Nothing was ever deleted by the old behaviour — the pages were there the whole time, just hidden.

## 0.40.0

- **X (Twitter) joins Facebook, Instagram and LinkedIn as a club social link.** Add the club's profile under Site setup → Branding → "X (Twitter) URL" and it appears wherever the other three do: the footer, the "Follow the club" band and the Contact page. Unlike the other three it ships empty rather than pointing at a demo profile, so no existing site gains a link it did not ask for, and the setup progress bar counts it towards the social step.
- **The header shows the logo or the club name, never both.** A crest that already spells out the club's name sat next to the same name set in type, reading as a doubled title. When a logo is set it now stands alone; without one the club name stands alone, as before. The link still announces the club's name either way.
- **Filters no longer reload the page.** On Sports, Teams, Events and Calendar, clicking a filter pill swapped the whole page — the hero re-rendered and the scroll position jumped. The list below now changes in place while everything above holds still. The pills stay ordinary links, so each filtered view remains shareable and bookmarkable, and the page still works with JavaScript switched off.
- **Home's sports section now switches between Sports and Teams.** One section, two collections, chosen by the reader instead of by the page — each with its own "see them all" link. If the club has only one of the two, it renders as a plain grid with no switch.
- **Link fields suggest your Clubhouse pages as you type.** Every link box in Club content now offers the club's own pages by name, so filling one in no longer means knowing the `?clubhouse_page=…` form by heart. They remain free-text fields — external links and other plugins' pages still work.
- **"Global" and "Home" are now separate tabs in Club content.** The Global tab held the sitewide header and footer *and* every section of the Home page, so editing the Home hero looked like a sitewide change. Global now holds only the header and footer; everything Home-specific has moved to its own Home tab. Your saved content is untouched.
- **Save is always reachable.** The Save bar in Site setup and Club content sat at the foot of the form, so on long forms you had to scroll to the bottom to use it. It now stays pinned to the bottom of the screen with the form scrolling behind it.
- **Background images are easier to read over.** The wash over the Home hero photo was too light at the top to carry the heading on a bright image. It is now stronger overall and weighted towards the side the text sits on, in all three looks.
- **Removed: the Home stats strip.** The row of big numbers ("900+ Members", "9 Sports") has been withdrawn from the template, along with its section toggle and its content fields. No club can show it, and any numbers previously entered are simply no longer rendered.

## 0.39.0

- **Your club's address, email and phone are now yours to set.** The details beside the contact form were fixed text baked into the plugin — every club's Contact page published "12 Riverside Lane, Marlow, SL7 1AA", `hello@clubhouse.example` and `01628 000 000`, and there was no setting anywhere that changed them. They are now ordinary content, under Club content → Contact → Contact form, alongside a heading for the block and an optional photo or map image. The map tile is also a link to Google Maps for whatever address you enter, and it now describes itself to screen readers using your club's name rather than the word "ClubHouse".
- **Fixed: hero headings ran two words together.** On Sports, Teams, Events and Calendar the highlighted phrase sits on the same line as the words before it, and the gap between them was missing — "Represent" plus "Crewe Vagrants" printed as "RepresentCrewe Vagrants". It only affected headings you had saved yourself, because a trailing space is trimmed when the field is stored, so there was no way to fix it from the admin.
- **Fixed: a long word in a hero heading was split down the middle.** A single long word in the highlighted phrase — "membership" — outgrew the coloured box at the largest heading size and the browser broke it across two lines as "membershi / p". Large headings are now sized so a realistic word fits.
- **Fixed: long values in team and sport cards were cut off at the card edge.** The pair of details at the foot of each card is designed for short values, but a real club's are sentences — "Thursday evening" and "North West Counties" — and the second was clipped mid-word. The pair now wraps onto its own line and scales down when the text is long.
- **Empty sections no longer leave a heading over blank space.** A club with no committee entered saw a bare "The committee" on About, and one with nothing scheduled saw a bare "Upcoming events" on Events. Sections built from a list now render nothing at all when the list is empty. Where the list sits behind a filter, the section keeps its heading and says nothing matched, so a filter that returns nothing still looks deliberate.
- **Each page now has its own name in the browser tab.** Every page printed the club name alone, so tabs, bookmarks, search results and shared links were indistinguishable across the whole site. Pages now read "Membership — Your Club"; the home page keeps the club name on its own.

## 0.38.1

- **The home hero's quick links no longer turn black when you point at them.** Hovering one of the four tiles (Join the club, Take a tour, See fixtures, Get in touch) flipped the whole tile to near-black, which dropped a heavy dark slab into a row of pale tiles and looked more like the tile switching off than lighting up. Hovering now outlines the tile in your club colour, lifts it slightly and drops a soft shadow beneath it, leaving the tile's own colour alone. The outline uses the deeper shade of your club colour, so it stays clearly visible whether your colour is light or dark, and the tile never changes size — the tiles beside it stay put.

## 0.38.0

- **The page now ends on one band instead of two.** "Follow the club" and the find-us details (location, opening hours, contact, map link) were two stacked sections — a light band above a dark strip — which read as two endings and dropped a slab of dark between your content and the footer. They are now a single light section, in the same style as the section above it, sitting flush against the footer. Both halves keep their own section switch: turn either off and the band simply carries the other.
- **Footer social links are icons only.** Facebook, Instagram and LinkedIn now show as round icon buttons rather than name pills, so the footer's first column stays tight. Each button still announces its network name to screen readers and keeps a full-size touch target. The social links elsewhere on the site are unchanged.

## 0.36.2

- **Full-width bands no longer have rounded corners.** The dark clubhouse band, the news ticker and the contact strip run edge to edge, but on the Members House and Floodlight looks they were still drawn with rounded corners — so a sliver of page background cut into each corner and the band read as broken rather than styled. They are now square-cornered, matching Court Side, which was already correct. Panels that sit inside the page margins, like the membership band, keep their rounded corners as before.

## 0.36.1

- **Fixed: body text ignored the club's fonts and ran the lines together.** The plugin's stylesheet was loaded before the site's theme, so the theme's own reset overruled it and body copy fell back to the browser's default serif at single line spacing — navigation, intro paragraphs, membership points, news dates, ticker items and the footer were all affected. The club's fonts were downloading correctly the whole time; nothing was reaching them. Headings were unaffected, which is why the pages looked almost right. The stylesheet now loads after the theme, so both fonts and line spacing apply as designed.
- Buttons, inputs and dropdowns now use the club's font. Browsers give form controls their own font unless told otherwise, so they had been rendering in the system default while everything around them used the club's.
- The small uppercase label above a section heading is now legible on the membership band for every club colour. On darker club colours it fell just under the accessible contrast minimum.

## 0.36.0

- **An import can now switch off the sections it has no content for.** Previously, uploading a file filled in your own content but left every section the file said nothing about still showing its demo content. The preview now offers a tick box — on by default — that names those sections and switches them off, and switches on any section your file fills in. Only pages your file writes about are affected, so importing one page at a time is still safe; the header and footer are never touched. Untick the box to apply the content and leave every section switch exactly as it is.

## 0.35.1

- **Fixed: "Download the prompt" on Club Content → Import failed with "The link you followed has expired."** The link's security token was escaped twice, so it reached WordPress under the wrong name and the download was refused every time. The prompt now downloads on the first click.

## 0.35.0

- **AI-assisted content import.** Club Content → Import offers a downloadable prompt generated from the plugin's own content catalogue; paste it into any AI chat, answer its questions, and upload the JSON file it produces to populate page content and all six collections. Uploads are previewed before anything is written, partial files merge, and images are fetched from public URLs into the Media Library.
- On/off answers in an imported file (e.g. the announcement bar, a "Featured" stat, an "Included" membership point) are now read as what they actually say, not just whether the question was answered. Importing sports, teams or another collection only ever removes the seeded demo entries — a club's own post is never deleted just because it happens to share a demo title. The "images still needed" list now accumulates across multiple imports instead of being reset by the next one, and the Import screen has its proper styling.

## 0.34.0

- **WordPress's built-in "Pages" are now hidden.** The clubhouse plugin provides every page of your site, so the native Pages editor was redundant and only invited editing content that never appears. The "Pages" item in the admin sidebar and the "+ New → Page" shortcut are both gone. Nothing is deleted and blog Posts are untouched — this only tidies the admin so you edit your site in one place (Clubhouse → Club Content).

## 0.33.0

- **Internal:** unified the three page-hero components into one consistent family. They now share a single "head" (the eyebrow and highlighted title) and the filtered hero's CSS name was tidied from the cryptic `ch-hero-f` to `ch-hero-filter`. No visible change — every page's hero looks and behaves exactly as before on all three looks; this only makes the heroes consistent and easier to maintain.

## 0.32.0

- **The filter pills on Sports, Teams, Events and Calendar now actually filter.** Previously they were decorative — every pill reloaded the same full page. Click a sport (or an event type) and the list narrows to just those items, with the chosen pill highlighted; "All" brings everything back. It works without JavaScript (each pill is a real link) and the pills are built from your own content, so they always match the sports, team sports and event types you've actually added — nothing to configure.

## 0.31.0

- **A clearer About page.** The club's facilities now sit higher up, right after its values, and a new **"Get involved"** section highlights ways to help beyond playing — volunteering, coaching and sponsorship — separate from the join-the-club call to action that closes the page. The history timeline is now fully editable in Clubhouse → Club Content → About, so you can add, remove or reword your own milestones.
- **Membership pricing is now front and centre.** The tier cards move above the "Why join" benefits, so visitors see the options and prices immediately after the page intro rather than after scrolling.

## 0.30.0

- **Membership tiers are now managed in one place.** The tier cards on the Home page previously showed a fixed, separate set that ignored your Membership page edits and left out the Junior tier. Home now mirrors the tiers you set on the Membership page — edit them once and both pages update. On Home the cards link through to the Membership page (where the full detail and "register interest" live); the Membership page itself is unchanged.

## 0.29.0

- **The top announcement bar is now yours to edit or switch off.** In Clubhouse → Club Content → Global → Header you can change its message and link, or turn the whole bar off with a single toggle — your drafted wording is kept even while it's hidden. Until you change anything it reads exactly as before.

## 0.28.0

- **A tidier, better-flowing home page.** Contact and location details now sit at the foot of the page, nearest the footer, instead of interrupting the middle. The news ticker is bolder and easier to read, with its label filling the strip. The dark "clubhouse" band no longer shows a broken-image icon when you haven't set a photo, and its wording no longer echoes the footer. The big lime highlight in the hero has a little more breathing room so letters don't touch its edge, and the fixtures list's small print (e.g. "Rugby · 1st XV") is now larger and easier to read.
- **The home hero's two buttons are off by default**, because the four quick-link tiles just beneath them already cover the same actions. If you want the buttons back, set a primary button label in Clubhouse → Club Content and they return.

## 0.27.0

- **Cleaner, more consistent calls to action across the site.** Social links now use one style everywhere — the branded Facebook/Instagram/LinkedIn buttons — instead of a second row of plain letter icons that went nowhere. The "join" button now reads the same wherever it appears. "Become a sponsor" and "Open in Maps" now go to the right places, and links that had no destination yet (the footer legal links, the news cards) no longer look clickable when they aren't. The sponsors section now has the same small label above its heading as every other section.

## 0.26.5

- **Fixed: the Floodlight and Members House looks now render every page.** The calendar, teams, sports and events pages were missing their styling entirely under those two looks — filters, cards and fixture lists showed as unstyled text — because six components were only ever styled for Court Side. All three looks now share one set of building blocks, so switching look re-skins the whole site rather than part of it. Court Side is unchanged.

## 0.26.4

- **Changed: the test suite now runs against a real WordPress.** Nothing about the plugin's behaviour changes — this is a development and CI change. Previously every test ran against the DB-free PHP preview, which meant WordPress's own routing, template loading and stored settings were never exercised. Tests now run against a disposable WordPress the run provisions itself (PHP + SQLite, no Docker, no staging site), with the handful of preview-only tests still pointed at the preview. Bringing the suite up against real WordPress immediately showed that page routing had never been covered there.

- **Changed: local test ports are derived from the plugin slug**, so several plugin repos can be worked on at once without their local WordPress and preview servers landing on the same port and quietly serving the wrong plugin. `npm run ports` prints this plugin's pair.

## 0.26.3

- **Changed: the home hero quick-tile icons are now crisp, current Lucide line icons.** The Explore/tour and Contact/email glyphs were hand-simplified approximations; they (and the Join and Fixtures icons) now use the exact official Lucide vector geometry, so the compass needle and envelope flap render cleanly at every size. No content or settings change — existing tiles keep their icons.

- **Changed: clearer admin menu names.** The **Site Content** editor is now **Club Content**, and the **Content** menu the collections (sports, teams, events…) nest under is now **Collections** — so the two are no longer easily confused. Same screens, same links; only the labels changed.

- **Changed: the three Clubhouse admin menus now use crisp Lucide line icons.** Clubhouse → trophy, Club Content → page-panels, Collections → library, replacing the old megaphone/pencil/clipboard dashicons. The icons are tinted with the menu's own colour, so they brighten on hover and when active just like WordPress's built-in menu icons.

## 0.26.2

- **Changed: the placeholder "C" logo mark is gone.** Until you upload a logo, the header and footer now show your club name on its own instead of a generic "C" badge. Upload a logo in Clubhouse → Setup and it appears in the header exactly as before.

- **Fixed: your favicon now shows across the whole site.** Once you add a favicon in Clubhouse → Setup, it appears in the browser tab on every front-end page — including blog posts — not just the main clubhouse pages. Nothing shows until you add one.

## 0.26.1

- **Fixed: Demo mode look swaps now appear immediately for every visitor.** While Demo mode is on, clubhouse pages are no longer stored in the page cache, so when anyone picks a different Base Look in the switcher the page re-skins on reload instead of showing the previous look until a hard refresh. This affects installs fronted by a caching plugin (WP Rocket, W3 Total Cache, WP Super Cache), a host page cache, or a CDN. Normal caching is untouched when Demo mode is off.

## 0.26.0

- **Removed: the Results tab.** The "What's happening" section on the home page now has two tabs, Fixtures and Events, instead of three. The Results tab and its list of recent scores are gone.

  **Your fixture data is untouched.** Score, Outcome and Result note remain on the fixture editor exactly as before, and nothing you have recorded has been deleted. Outcome still does its other job: a fixture with no outcome set is treated as upcoming, which is how the Fixtures tab knows what to list.

  **The Calendar is unaffected** — it still shows every played match with its result note and W/D/L badge, as it always has.

## 0.25.3

- **Internal:** reverts the 0.25.2 documentation change. It edited this repo's copy of the shared contributor instructions; that file's source of truth is the `bluegroup_core_foundation` template, so the edit belongs there and would have been lost on the next sync. No change to the plugin.

## 0.25.2

- **Internal:** the project's contributor instructions now point at the zip build script added in 0.25.1, rather than describing a hand-built zip that would bypass its checks. Documentation only — no change to the plugin.

## 0.25.1

- **Internal:** the deployment zip is now built by a script from an explicit list of what ships, and every pull request builds and inspects the real artifact. Previously the zip was assembled by hand, and nothing stopped a future one from accidentally including the developer preview harness — a file that renders without WordPress and so must never reach a live site. No shipped zip has ever contained it; this closes the gap that made it possible. Nothing about the plugin itself changes.

## 0.25.0

- **The demo colour swatches now show which one you picked.** Clicking a swatch recoloured the site but left every swatch looking identical, so there was no confirmation of your choice — and for anyone using a screen reader, no feedback at all that the click had done anything. The chosen swatch now carries a ring and announces itself as selected, and the mark follows you across pages just as the colour does.

- **Change:** while demo mode is on, the demo bar now appears only on Clubhouse pages, instead of on every page of the site. On a blog post or shop page there is no Clubhouse design for it to restyle, so the bar did nothing useful there and its colour settings could clash with your theme's own. Turning demo mode on and off is unchanged — that control lives in the admin bar and is still available everywhere.

- **Fix:** a corrupted demo colour cookie could stop the saved colour being applied on page load until the cookie was cleared. It is now ignored safely and the site falls back to the club's own colour.

## 0.24.3

- **Demo mode now lets you try colours, not just looks.** While demo mode is on, the bar gains five brand colours — click one and the whole site recolours instantly, no page reload. Your choice follows you as you browse, and switching Base Look keeps your colour while re-fitting it to that look's style. It only ever changes what *you* see: the club's saved colour is untouched, and other visitors are unaffected.

## 0.24.2

- **Fix (Court Side):** the small label above the home hero headline ("Est. 1974 · Marlow, UK") was invisible — its text was the same colour as the pill behind it. It now reads correctly, and the label's text automatically switches between dark and light so it stays readable whatever colour your club picks.

- **Change:** the top banner and news ticker now carry your club's colour instead of near-black, and the home hero does too whenever no hero photo is set. Each is filled with the Base Look's own ink pulled 30% toward your accent, so the site reads as tinted with your brand while keeping the same weight and readability. Every club colour is handled automatically — no setting to configure, and contrast is guaranteed by the colour engine. Applies to Court Side and Members' House; Floodlight is unchanged, as its blocks were never near-black. Hero photographs are unaffected: the scrim over them stays neutral so your images keep their true colours.

## 0.24.1

- **Fix:** the news ticker now sits flush against the bottom of the full-bleed home hero, with no gap between them — the hero's dark background runs straight into the ticker as one continuous block.
- **Fix (Court Side):** the "Follow the club" social block has lost its rounded corners and now seats directly on top of the footer with no gap, closing the page off as a single square-edged band. Applies to the home and contact pages, which both end with the block; every other page keeps its usual footer spacing.
- **Change:** the home stat strip (Members / Sports / Teams / Founded) now ships hidden. It stays available — switch "Stats" to Shown on either the Setup or Site Content screen to bring it back, and any site that has already saved a visibility choice keeps whatever it chose. Every other section still ships visible.

## 0.24.0

- **Site Content editor** — a new "Site Content" screen under Clubhouse in the admin menu lets you edit the page copy that used to be hardcoded: heroes, the news ticker, stats, bands, news, info strips, FAQ, steps, tiers, values, the facilities image band and the call-to-action bands. Anything you haven't edited keeps rendering exactly as it does today. Sports, teams, events, sponsors, committee and directory content still live on their own dedicated screens, which the new screen links out to. It picks up your chosen Base Look automatically, and each section's Shown/Hidden switch is the same one used on the Setup screen.
- **Fix:** every field in the Site Content catalogue now genuinely changes the rendered site. Previously ~26 fields across About (history body/image, the Facilities loop), Contact (form intro/submissions email/success message), Log in (support email) and the Sports/Teams/Events/Calendar hero (CTAs, image) were editable but silently ignored by the renderer. Those sections have been reshaped to match what actually renders, or wired up where the renderer already supported the field — an owner's edit now always shows up on the site.

## 0.23.0

- Full-bleed Home hero: the home page hero is now a full-bleed background image (with a graceful toned fallback panel when no photo is set) with the heading, intro and calls-to-action overlaid, the quick-links folded in as an icon-card row, and the news ticker directly beneath. Applied across all three Base Looks (Court Side, Floodlight, Members' House), each in its own style. Existing copy is unchanged, and the shared hero used by the other pages is untouched.

## 0.22.1

- Setup screen full-bleed CSS tidy: the container now uses `height: 100vh` with `max-width: 100%` and `border-radius: 0`, the `.wrap.clubhouse-wrap` padding is dropped, and panels lose their bottom padding — all still scoped to the Setup page body class so no other admin screen is affected.

## 0.22.0

- Redesigned the Clubhouse Setup screen: a bespoke, tabbed layout (Base Look & Branding · Visibility) that inherits the selected Base Look's fonts, colours, radii and accent, and re-skins live as you pick a look. The screen fills the admin content area edge-to-edge with a full-width Save footer.
- Added Favicon and LinkedIn as brand inputs. The favicon renders in the browser tab; LinkedIn joins Facebook and Instagram in the site's social block and footer.
- Setup progress counts the main setup sections (base look, accent, club name, logo & favicon, social, visibility) — nothing is compulsory and Save is always available so you can return and finish later. Visibility counts as done once you save (keeping the defaults is a valid choice).
- Saving now shows a confirmation notice.
- Demo mode is an admin-only control: its tab shows only to administrators and is never counted in the setup progress.

## [0.21.0] - 2026-07-13

### Phase 5 — Font self-hosting

#### Changed

- **Fonts are now self-hosted.** Every Base Look's typefaces (Syne, Inter, Fraunces, Mulish, Bricolage Grotesque, Hanken Grotesk) are served from woff2 files bundled in the plugin instead of the Google Fonts CDN. No visible change — same families, weights, and `font-display: swap` — but the front end now makes **zero third-party font requests** (no `fonts.googleapis.com`/`fonts.gstatic.com`), which is faster and more private. Each family's SIL OFL 1.1 licence is bundled under `assets/fonts/licenses/`.

## [0.20.1] - 2026-07-13

### Docs

- Added a **manual WP smoke-test checklist** (`docs/manual-smoke-test.md`) covering the live-install-only behaviours across Admin Phases 1–4 and the site-wide Demo mode runtime — the surface that cannot be reached by unit tests (real WordPress, multiple browsers, capability/nonce probing).

## [0.20.0] - 2026-07-13

### Changed

- **Demo mode is now site-wide.** Instead of a private per-admin preview, an administrator turns Demo mode on or off for the whole site — from the ⚡ admin-bar toggle (which now works in the front end *and* in wp-admin) or from a new control on **Clubhouse → Setup**. While it is on, every visitor sees the floating look switcher and can click through the Base Looks themselves (their own choice, held in their browser); the club's saved look is never changed. Only administrators can turn it on or off.

## [0.19.0] — Admin Phase 4: Clubhouse Owner role, admin lockdown & Dashboard takeover

### Added
- A new **Clubhouse Owner** role: a curated back-end for non-technical club owners. Login lands directly on the Setup screen (the dashboard is replaced with it), and the admin menu is limited to Setup, Content, Media, Posts, Comments, Users, and Profile — everything else (Appearance, Plugins, Tools, Settings, Pages) is hidden and capability-denied.
- The six collection post types are now grouped under a single **Content** menu.
- Owners can view the Users list but cannot create, edit, or delete users; they can edit the collections and the blog, upload media, and moderate comments.

### Changed
- The Clubhouse Setup screen is now gated by a dedicated `manage_clubhouse` capability (granted to owners and administrators) instead of `manage_options`.

### Notes
- The role is created on activation and kept on deactivate; it is removed only when the plugin is uninstalled.

## [0.18.0] — Admin Phase 3: collection editing, projection robustness, header logo/nav

### Added
- Native custom meta-boxes for all six collection CPTs (fixtures, teams, people, sponsors, sports, events) with typed inputs (date/time/select/email/url) and a `wp.media` image picker, driven by a single pure `Collection_Meta` field definition; values sanitised server-side and escaped on output.
- Admin list columns for the high-signal fields of each collection (e.g. a fixture's date, teams, and result).
- Front-end logo rendering in the site header (attachment resolved to a URL in the WordPress layer; club-name text kept beside it) and omission of hidden pages from the header nav and footer link lists.

### Fixed
- `Fixture_Projection` now groups the calendar by year-and-month (`January 2026`) so fixtures in different years no longer merge, and guards empty/malformed match dates (which previously resolved to "now") — undated fixtures show as "Date TBC" on the calendar.
- The Clubhouse Setup admin menu is now registered on init (it was defined in v0.16.0 but never wired, so it never appeared on a real install).

## [0.17.0] - 2026-07-13

### Demo mode

An admin-only way to demo the Base Looks live on the real site, so a prospective club owner can pick one. (Superseded by the site-wide model in 0.19.0.)

#### Added

- **Demo mode toggle.** A **⚡ Demo mode** button in the front-end WordPress admin bar (for users who can manage the site). Turning it on reveals a floating switcher listing every installed Base Look; click a look and the whole live site re-skins to it on the spot.
- **Ephemeral and private.** Switching looks in Demo mode is per-admin and temporary (held in a browser cookie) — it never changes the club's saved look, accent, or content, and public visitors always see the saved look. "Exit demo" (or toggling it off) returns to the saved look.

#### Fixed

- **Front-end navigation now uses real permalinks.** Internal links were emitting the preview server's `?page=<slug>` form, which WordPress does not route — so every nav click landed on Home. Links now resolve to proper permalinks (e.g. `/about/`), falling back to a query-var URL when permalinks are set to Plain.

## [0.16.1] - 2026-07-13

### Changed

- **Plugin title** on the WordPress Plugins page is now **Blueworx Labs | Clubhouse** (was "Blueworx Labs Clubhouse").

## [0.16.0] - 2026-07-13

### Clubhouse Setup screen

The first owner-facing admin surface — a standard WordPress admin page under a new **Clubhouse** menu.

#### Added

- **Base Look picker.** Choose Court Side, Members' House, or Floodlight; the choice becomes the active look.
- **Branding controls.** Accent colour (rejected on save if it is too low-contrast for the chosen look — the check is look-aware: text-bearing looks need higher contrast than the glow-only dark look), club name, logo (via the media library), and Facebook / Instagram URLs.
- **Visibility controls.** Show or hide any page and any of its sections. A hidden sub-page now returns a 404 on the front end (a hidden home page falls back to WordPress's front-page setting).
- **Setup progress bar.** Tracks the six branding/look configuration items (page content is not counted).
- **Look-aware accent legibility.** Base Looks now declare whether they paint text on the accent fill, so accent acceptance matches how each look actually uses the colour.

#### Notes

- The screen is a standard admin page for now (capability `manage_options`); the locked-down Clubhouse Owner role and Dashboard takeover arrive in a later phase.
- The logo is stored here; rendering it in the site header (and omitting hidden pages from the nav) lands in the next phase.

## [0.15.0] - 2026-07-13

### Admin foundation & engine hardening

Groundwork for the admin experience — no user-facing UI yet.

#### Changed

- **All three Base Looks are now registered at runtime.** `Frontend` previously registered only Court Side, so the stored active look could never resolve to Members' House or Floodlight on a live site. A shared `Frontend::registry()` now registers all three (Court Side first as the fallback).
- **Theme-cache signature includes look tokens + plugin version.** The composed `:root` cache was keyed only on look slug + accent, so an upgrade that changed a look's shell tokens served stale CSS. The signature now also hashes the look's token contents and the plugin version.
- **`Frontend::context()` returns a named `Clubhouse_Context` DTO** instead of a positional array, and now carries the Base Look registry for the upcoming setup screen.

#### Added

- **`Color_Engine::accent_is_legible()`** — validates that a club accent clears WCAG AA both as ink on the accent fill and as accent-text on the shell. The admin setup screen will use it to reject low-contrast accents.

## [0.14.0] - 2026-07-12

### Social block

A global, skin-agnostic "Follow us" section linking out to the club's Facebook and Instagram — not a live/embedded feed.

#### New

- **`Branding` social URLs** — `facebook`/`instagram` fields alongside accent, club name and logo, so the club's social links are a single global source (admin editing arrives with the admin flow).
- **`Sections::social()`** — a `ch-social` renderer with self-hosted inline brand SVGs for Facebook and Instagram (`fill`/`stroke="currentColor"`, no icon font, no hex), each link carrying a descriptive `aria-label` and list semantics.
- **Placed on Home and Contact** — after Sponsors on Home, after the Directory on Contact, both behind their own `social` visibility toggle.
- **Court Side styling** — a heading + lede band with pill-shaped accent-wash links, hover lift respecting `prefers-reduced-motion`, and a responsive stack on narrow screens.

## [0.13.0] - 2026-07-12

### Collections / custom post types

Real data behind the unchanged renderers.

#### New

- **Six custom post types** — Sports, Teams, Fixtures, Events, Sponsors, People — registered with their meta fields. Fixtures is a single type carrying an outcome (empty = upcoming, `W`/`L`/`D` = result), feeding both the Home activity tabs and the Calendar.
- **`Collections` repository** — a pure `Demo_Collections` (preview + tests) and a thin `WP_Collections` (reads the CPT posts) behind one interface; `Page_Renderer` projects the canonical data to each renderer's exact shape, so no renderer changed.
- **Demo content is seeded** on activation from a single `Demo_Content` source, so a fresh install still shows a fully populated site. Per-field editing UI arrives with the admin flow.

#### Notes

- Home and Calendar fixtures are now derived from one consistent set (the previously-hardcoded demo diverged between the two views).
- The `sponsors` renderer takes only names, so a sponsor's URL is stored but unused for now; committee entries render without email (matching the demo), the directory shows emails.

## [0.12.0] - 2026-07-11

### WordPress integration

The plugin now serves its eight-page Court Side site inside WordPress — the first WordPress-runnable release.

#### New

- **Rewrite-rule routing.** The plugin owns the frontend: Home renders at the site root and each other page at `/{slug}` via rewrite rules, with the active theme left neutral. Flushed on activation/deactivation.
- **Canvas template + proper enqueue.** A `template_include` canvas template fires `wp_head()`/`wp_footer()`; the look stylesheet and fonts are enqueued and the derived `:root` design tokens are injected inline via `wp_add_inline_style`.
- **Cached `:root` tokens.** The composed token string is cached in an autoloaded option keyed by look + accent, so the colour math runs only when they change (`invalidate()` is exposed for the admin flow).
- **`Page_Map`** — a single slug→renderer dispatch used by both WordPress and the DB-free preview, so they render identical bodies. The scroll-reveal script is extracted to `assets/js/reveal.js` (enqueued in WP, inlined in the preview).

#### Notes

- Renderers still use hardcoded demo data; the Collections/CPT plan swaps the data source behind them.
- This branch also carries the CI-preview wiring (from PR #6) so its guardrails check passes ahead of that PR propagating through the stack.

## [0.11.0] - 2026-07-11

### Sports, Teams, Events & Calendar pages

The four remaining collection pages, completing the eight-page ClubHouse site under the Court Side look.

#### New

- **Four new pages** — Sports, Teams, Events and Calendar — composed under per-section `Visibility` with hardcoded ClubHouse demo data, routed in the preview via `?page=`.
- **Five new skin-agnostic section renderers** on `Blueworx_Clubhouse_Sections`: `hero_filter` (filter-pill hero), `stat_card_grid` (chip + stats cards for Sports/Teams), `event_grid` + `event_archive` (upcoming cards + past list), and `calendar_months` (month-grouped fixtures/results with W/D/L status badges). All emit only `ch-*` classes, escape interpolated text, and carry list semantics.
- **Court Side styling** for every new hook, consuming engine custom properties only.

#### Notes

- Demo data is hardcoded this round; the later Collections/CPT plan swaps the data source behind the unchanged renderers.
- Filter pills are presentational (unfiltered demo data), consistent with the progressive-enhancement / presentational-forms decisions.
- Members' House and Floodlight will need the same new `ch-*` hooks styled when they rebase onto this branch (re-skin contract).

## [0.10.0] - 2026-07-11

### Floodlight Base Look

A bold, dark, night-match re-skin covering every `ch-*` hook.

#### New

- **Floodlight — third Base Look.** A bold, dark, night-match re-skin (Bricolage Grotesque + Hanken Grotesk, warm-ink canvas, bold-modern 16/11/7 radii, accent spent as glow) covering every `ch-*` hook. Adds `includes/looks/class-floodlight.php` and `assets/looks/floodlight.css`, registers the look in the DB-free preview, and cycles looks via `?look=`. Pure re-skin: no changes to section renderers or the theme engine. On the dark shell all accent text/marks route through the engine's AA-guaranteed `--color-accent-deep`; the engine's `accent-ink`-on-dark limitation is sidestepped by the glow idiom, not triggered.

> Note: `0.9.0` is the Members' House Base Look, delivered on its own sibling branch/PR; Floodlight takes `0.10.0` so the two do not collide when both merge into `base-look-theming-design`.

## [0.9.0] - 2026-07-10

### Members' House — second Base Look

The first re-skin. A refined, editorial Base Look that reuses the engine and every
skin-agnostic section unchanged — swapping the active look changes only the tokens,
fonts, and stylesheet.

- **New Base Look `members-house`** (`Blueworx_Clubhouse_Members_House`): warm parchment
  shell, warm near-black ink, small crisp radii (10/7/4px), Fraunces (display) + Mulish
  (body). Accent stays engine-derived — the look defines no accent tokens.
- **Refined-editorial stylesheet** (`assets/looks/members-house.css`): every `ch-*` hook
  restyled in a restrained idiom — hairline rules, rectangular buttons, an accent
  underline on the hero highlight (no rotated block), quiet accent-wash bands, and fine
  accent marks. Accent is referenced only via `var(--color-accent*)`, so a club still
  re-themes by swapping one colour. All accessibility and motion behaviour (skip link,
  focus indicator, no-JS nav drawer, ticker pause, scroll-reveal, reduced-motion) is
  preserved through the shared section markup.
- **Preview look switch**: `preview/index.php` registers both looks and takes `?look=`
  (default Court Side), with a toggle to flip between them; the accent swatches derive
  from the active look's shell so they stay AA-correct per look.

## [0.8.1] - 2026-07-11

### CI preview wiring

Un-gates the merge train by running Playwright against the plugin's DB-free PHP preview instead of a not-yet-existent staging site.

#### Changed

- **Playwright now boots the preview itself.** `playwright.config.js` gains a `webServer` that starts `php -S` (docroot = plugin root) and points `baseURL` at `preview/`, so CI needs no deployed staging URL. The foundation `preview_url` input in `.github/workflows/ci.yml` is set to the same localhost preview URL.
- **Real smoke test.** The skipped placeholder (`tests/example.spec.js`) is replaced by `tests/smoke.spec.js`, which loads each built page (home, about, membership, contact, login) and asserts it renders (title + `<main>` landmark) and that `?page=` routing resolves to the right page rather than the Home fallback.

## [0.8.0] - 2026-07-10

### Login page, hover motion, and design polish

Second design-review pass on the Court Side site.

#### New

- **Member login page** (`?page=login`). A centred sign-in card — email/password with autocomplete, remember-me, forgot-password link, and a "not a member yet? Join the club" prompt — rendered by a new skin-agnostic `auth()` section. The header's "Log in" (bar and mobile drawer) now routes here instead of `#`.

#### Spacing

- **Fixed inline-title sections butting against their content.** Sections that render the eyebrow + title directly above a grid (benefits, news, directory, timeline, included/excluded, steps, FAQ, contact, activity tabs) had no gap between the heading and the grid below — the title's descenders touched the first row. Added the same 34px gap the header-variant sections already get from `.ch-sec__head`.
- **FAQ now shares the one 1200px content column** with every other section. It was capped at 820px and centred, which read as a misaligned island against the full-width sections above and below; answers keep their own 64ch cap for readability.

#### Layout

- **Directory (contact page) caps at three across** instead of packing six narrow columns, so names and emails keep room to breathe; steps to two, then one, as the viewport tightens.

#### Motion

- **Hover animations across the previously-inert surfaces.** Primary CTAs (accent/ink buttons) now lift and deepen on hover — before, only the ghost outline responded; the paper cards (benefits, tiers, steps) lift with an accent edge; directory people and their avatars, and FAQ questions, respond too. All hover transforms respect `prefers-reduced-motion`.
- **Entrance motion** on scroll — the hero rises in on load and each section reveals as it enters the viewport, via CSS plus a tiny IntersectionObserver (no runtime dependency; per the foundation guidance, GSAP is reserved for genuinely complex animation). Content stays fully visible without JS and when reduced motion is preferred.

#### Tooling

- **PHP linting is now real.** Added PHP_CodeSniffer with a curated, tab-aware ruleset (`phpcs.xml.dist`) tuned to the project's WordPress-flavoured style; run locally with `composer lint` and enforced by the shared CI PHPCS step. The previous `npm run lint` was a placeholder that always passed. Synced `package.json` to the plugin version so the CI version-sync check passes.

## [0.7.0] - 2026-07-10

### Design review fixes (responsiveness, navigation, spacing, accessibility)

Actioned a principal-designer UX/accessibility review of the Court Side site.

#### Navigation

- **Mobile & tablet navigation restored.** Below 900px the primary nav was previously hidden with no replacement, leaving those users unable to reach any page. Added a no-JS `<details>` disclosure (hamburger → drawer) carrying every nav link plus Log in; the persistent Join CTA stays in the bar on tablet and moves into the drawer on phones so the header always fits.

#### Responsiveness

- **Eliminated horizontal scroll on mobile** across all pages: hero and section/band titles now wrap long words (`overflow-wrap`), the hero highlight is width-capped, the accent band pads down on small screens, and every auto-fit grid uses `minmax(min(…,100%),1fr)` so a track can never force overflow. Verified clean from 320px to desktop.
- Hero type scaled down slightly so the primary CTA isn't pushed off-screen; buttons no longer wrap mid-label.

#### Spacing

- Introduced a shared spacing scale (`--space-*`) and a **flow-based rhythm**: one consistent gap between every top-level block (`--flow-lg`, 88px desktop / 52px mobile) and one tight gap inside the hero utility cluster and between a band and its cards (`--flow-sm`, 24px / 20px). Spacing now lives in a single margin rule instead of ad-hoc per-section paddings, so adjacent paddings can never double up and every section gap is identical. Background-bearing sections keep their own internal padding. Fixed the brand/nav gap.

#### Placeholder images & gradients

- **Every empty image slot now renders an intentional gradient placeholder** with a centred photo icon (hero media, sports cards, the clubhouse image band, news thumbnails and the contact map); people/committee avatars use a gradient behind their initials. Image bands get a dark gradient placeholder so their white heading stays legible. Placeholders are engine-derived, so they re-theme with the club accent, with subtle per-position variation so a grid doesn't read as identical.
- **Wider gradient usage** for depth: the membership accent band (radial highlight), the featured stat card, the dark info strip and ink CTA band, and a soft ambient wash behind the hero — all derived from the accent tokens.

#### Hierarchy & clarity

- The emphasised stat is now chosen by data (a `featured` flag) instead of DOM position.
- Home quick tiles reframed as task-oriented shortcuts (Join the club / Take a tour / See fixtures / Get in touch) rather than a second, mismatched copy of the nav.
- Photo-less people avatars render initials, and empty image slots show a subtle patterned placeholder instead of a flat-grey block; committee/directory names reserve two lines so a wrapped name doesn't break row alignment.

#### Accessibility

- Added a skip link and a `<main id="ch-main">` landmark to every page.
- One branded, guaranteed-contrast `:focus-visible` indicator for all interactive elements (previously only form inputs had a focus style).
- The news ticker gained an accessible, no-JS pause control (WCAG 2.2.2).
- Active nav link now signals the current page with full-contrast ink text and an accent underline (colour alone previously measured ~4.3:1).
- Footer links, legal links and social icons raised to ≥44px touch targets.

## [0.6.1] - 2026-07-10

### Accessibility & fixes

- **List semantics across all grids.** Every grid of repeated items — quick tiles, stats, sports cards, membership tiers (and their feature lists), fixtures/results/events, news, info columns, sponsors, benefits, committee/directory people, the history timeline, the included/excluded/policies split and the how-to-join steps — now carries `role="list"` / `role="listitem"`. This restores list semantics for screen readers, including on WebKit where `list-style:none` silently strips the implicit roles from `<ul>` grids.
- `list_split` column headers (previously the hard-coded English "Included / Not included / Good to know") are now passed in as data, so a non-English club can relabel them.
- The Contact info card's `tel:` link now strips whitespace from the number so it dials correctly (`tel:01628000000`); the visible number keeps its spacing.

## [0.6.0] - 2026-07-10

### Added

- **About**, **Membership** and **Contact** pages under the Court Side look: new skin-agnostic section renderers — benefit grid, people/committee grid, history timeline, included/excluded list split, how-to-join steps, a native-`<details>` FAQ (works with no JS), and a presentational contact form with an info card. Shared header/footer extracted into `shell_header`/`shell_footer` helpers; the hero renders its media block only when an image or caption is present. Preview routes `?page=about|membership|contact`.

## [0.5.0] - 2026-07-10

### Added

- Full Court Side **Home page**: upgraded header (promo banner, Login + Join CTAs, active nav) and 4-column footer (social pills + newsletter form + legal bar), plus quick-access tiles, a reduced-motion-safe news ticker, sports card grid, clubhouse image band, membership band + tier grid, tabbed club-activity (fixtures/results/events), news cards, dark info strip, and sponsors grid — all skin-agnostic `ch-*` section renderers styled by the Court Side pack.
- `?page=` routing in the DB-free localhost preview so the site is navigable.

## [0.4.0] - 2026-07-10

### Added

- Court Side base look pack (tokens, Syne+Inter, stylesheet); skin-agnostic section renderers (header/hero/stats/footer); page renderer (head + `:root` + Home body honouring visibility); DB-free localhost preview with live, engine-derived accent switcher.

## [0.3.0] - 2026-07-10

### Added

- Base Look theming framework: pluggable look registry, single-accent colour engine
  with derived legible tokens, branding store, :root CSS composition.

## [0.2.0] - 2026-07-09

### Added

- Engine core & content foundation: PHP unit test harness (PHPUnit, dev-only),
  runtime class loader, base `Registry`, `Storage` interface with an autoloaded
  WordPress options adapter, `Content_Store` for singular section content, and
  page/section `Visibility` — all dependency-injected and unit-tested.

## [0.1.1] - 2026-07-09

### Added

- Design spec for the Sports Club Template **engine**
  (`docs/superpowers/specs/2026-07-09-sports-club-template-engine-design.md`):
  declarative page/section/collection registries, options + CPT storage,
  page/section show/hide, branding token engine with 5 font style presets,
  Blog + Social Feeds feature toggles, a graceful third-party integration seam
  (SureCart, SureDash, SureForms, SureRank, SureDonation, SureCookie, LatePoint),
  and a performance-first frontend.

## [0.1.0] - 2026-07-09

### Added

- Initial project scaffold: main plugin file with WordPress header, shared CI
  guardrail caller workflow (`ci-wordpress.yml`), PR and issue templates, Claude
  Code settings, `CLAUDE.md`, `approved-deps.json`, and a basic Playwright config
  pointed at a placeholder staging/preview URL.
