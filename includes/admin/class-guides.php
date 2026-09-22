<?php
// includes/admin/class-guides.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything a club can do with ClubHouse, as guides.
 *
 * These are shown on the Guides page of the BlueWorx WordPress Enhancements
 * plugin, in a ClubHouse section of their own, beside that plugin's own guides
 * and its WordPress, SureCart and LatePoint ones. ClubHouse used to draw a
 * guide screen of its own; one place for every guide on the site is easier
 * to find than one per plugin, so that screen has gone and the guides live
 * here instead, handed across by Blueworx_Clubhouse_Guides_Registrar.
 *
 * Each guide follows the shape every other guide on that page has: where to
 * go, an optional framing sentence, the numbered steps, and what the reader
 * should now see. Button and tab names sit in *asterisks*, which the page
 * shows as emphasis.
 *
 * Pure: the site's facts (whether a shop or bookings are running) are handed
 * in, and nothing here touches WordPress.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Guides {

	public const PRODUCT = 'clubhouse';
	public const LABEL   = 'ClubHouse';

	/** The topics, in the order they sit along the top of the section. @return array<string,string> */
	public static function tabs(): array {
		return array(
			'ch-start'       => 'Getting started',
			'ch-look'        => 'Look & branding',
			'ch-pages'       => 'Pages & menu',
			'ch-collections' => 'Collections',
			'ch-news'        => 'News',
			'ch-members'     => 'Members',
			'ch-bookings'    => 'Bookings',
			'ch-search'      => 'Search & sharing',
			'ch-import'      => 'Import',
			'ch-admin'       => 'Admin',
		);
	}

	/**
	 * Every guide this site should show.
	 *
	 * A guide about the shop is only worth reading on a site with a shop, and
	 * one about bookings only where LatePoint is running — the same rule the
	 * Guides page applies to its own SureCart and LatePoint sections.
	 *
	 * @param array{shop?:bool,bookings?:bool} $site
	 * @return array<int,array{id:string,title:string,tab:string,product:string,capability:string,parts:array<string,mixed>}>
	 */
	public static function catalogue( array $site ): array {
		$shop     = (bool) ( $site['shop'] ?? false );
		$bookings = (bool) ( $site['bookings'] ?? false );

		$guides = array_merge(
			self::getting_started(),
			self::look(),
			self::pages(),
			self::collections(),
			self::news(),
			self::members( $shop ),
			$bookings ? self::bookings() : array(),
			self::search(),
			self::import(),
			self::admin()
		);

		return array_map(
			static fn( array $g ): array => array(
				'id'         => 'clubhouse-' . $g['id'],
				'title'      => $g['title'],
				'tab'        => $g['tab'],
				'product'    => self::PRODUCT,
				'capability' => $g['cap'],
				'parts'      => $g['parts'],
			),
			$guides
		);
	}

	/**
	 * A guide body from its parts, in the Guides page's own markup.
	 *
	 * The Enhancements plugin builds this for every guide on the page, and its
	 * helper is used when it is there so ours read identically. The fallback is
	 * the same markup, for the tests and for anything that asks before that
	 * plugin has loaded.
	 *
	 * @param array{where?:string,intro?:string,steps?:array<int,string>,then?:string} $parts
	 */
	public static function body( array $parts ): string {
		if ( function_exists( 'blueworx_guide_body' ) ) {
			return (string) blueworx_guide_body( $parts );
		}

		$html = '';
		if ( '' !== (string) ( $parts['where'] ?? '' ) ) {
			$html .= '<p class="bw-guide__where"><strong>Where:</strong> ' . self::text( (string) $parts['where'] ) . '</p>';
		}
		if ( '' !== (string) ( $parts['intro'] ?? '' ) ) {
			$html .= '<p>' . self::text( (string) $parts['intro'] ) . '</p>';
		}
		if ( array() !== (array) ( $parts['steps'] ?? array() ) ) {
			$html .= '<ol class="bw-guide__steps">';
			foreach ( (array) $parts['steps'] as $step ) {
				$html .= '<li>' . self::text( (string) $step ) . '</li>';
			}
			$html .= '</ol>';
		}
		if ( '' !== (string) ( $parts['then'] ?? '' ) ) {
			$html .= '<p class="bw-guide__then">' . self::text( (string) $parts['then'] ) . '</p>';
		}
		return $html;
	}

	/** Escaped, with *a button name* shown as emphasis. */
	private static function text( string $text ): string {
		return (string) preg_replace( '/\*([^*\s][^*]*?)\*/', '<em>$1</em>', esc_html( $text ) );
	}

	/**
	 * @param array<int,string> $steps
	 * @return array<string,mixed>
	 */
	private static function guide( string $id, string $tab, string $cap, string $title, string $where, string $intro, array $steps, string $then ): array {
		return array(
			'id'    => $id,
			'tab'   => $tab,
			'cap'   => $cap,
			'title' => $title,
			'parts' => array(
				'where' => $where,
				'intro' => $intro,
				'steps' => $steps,
				'then'  => $then,
			),
		);
	}

	/* ------------------------------------------------------------------ */

	/** @return array<int,array<string,mixed>> */
	private static function getting_started(): array {
		$content = Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP;
		$setup   = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP;

		return array(
			self::guide(
				'start-where',
				'ch-start',
				$content,
				'Where everything lives',
				'The menu down the left of this screen',
				'ClubHouse adds three places to WordPress, and everything else you need is WordPress itself.',
				array(
					'*Clubhouse* holds the site\'s settings: *Setup* for the look, branding, which pages are on and the top menu; *Global content* for the header, footer and cookie notice; then *Import*, *Search & sharing* and *What\'s new*.',
					'*Collections* holds the club\'s lists — sports, teams, fixtures, events, sponsors and people. Add something to a list once and every page that shows it updates by itself.',
					'*Pages* is where you change the words and pictures on any page of the site. Each club page is a normal WordPress page with its own editor.',
					'*Posts* is the club\'s news, *Media* holds your pictures, and *Users* is your members.',
				),
				'Anything not in those places belongs to WordPress or to another plugin, and has its own section on this Guides page.'
			),
			self::guide(
				'start-roles',
				'ch-start',
				$content,
				'The two ClubHouse roles',
				'Users → All Users',
				'Every person who works on the site is either a ClubHouse Owner or a ClubHouse Content Editor. The pills at the bottom of each guide here show which of them can do the thing it describes.',
				array(
					'An *Owner* can do everything on this page: change the look, switch pages on and off, set up membership, import a site and edit any content.',
					'A *Content Editor* changes what the site says — pages, collections, news, the top menu — but cannot change the look, the settings or who can sign in.',
					'A site administrator can do everything both roles can, and also see the *Settings → ClubHouse access* report.',
					'To change someone\'s role, open *Users*, press *Edit* under their name and choose the role, then press *Update User*.',
				),
				'The person\'s menu changes the next time they load a page: they see only the screens their role can use.'
			),
			self::guide(
				'start-order',
				'ch-start',
				$setup,
				'Setting a new site up, in order',
				'Clubhouse → Setup',
				'Each step builds on the one before it, so a new site comes together fastest in this order.',
				array(
					'Choose a look and put in your club name, colours and logo (*Base Look & Branding*).',
					'If the club has an old website, bring its words across with *Clubhouse → Import* before writing anything by hand.',
					'Fill the lists under *Collections* — sports and teams first, then fixtures, events, sponsors and people.',
					'Go through each page under *Pages* and check its words and pictures, switching off any section you do not need.',
					'Arrange the top menu on the *Menu* tab of Setup and switch off any page the club will not use on *Visibility*.',
					'Set up membership on the *Members* tab, and the sign-in and email settings on *Settings*.',
					'Open *Clubhouse → Search & sharing* to check how each page will read in Google and when it is shared.',
				),
				'Turn *Demo mode* off on the last tab of Setup once you are happy, and the site is live.'
			),
		);
	}

	/** @return array<int,array<string,mixed>> */
	private static function look(): array {
		$setup = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP;

		return array(
			self::guide(
				'look-choose',
				'ch-look',
				$setup,
				'Choosing a look',
				'Clubhouse → Setup → Base Look & Branding',
				'A look is the whole design of the site — its typefaces, spacing and shapes. Changing it changes nothing you have written; the same pages and words are simply dressed differently.',
				array(
					'Open *Clubhouse → Setup*. The first tab is *Base Look & Branding*.',
					'Under *Base Look*, pick a look from the list. Each one has a line describing its character.',
					'Press *Save changes* at the bottom.',
					'Open the site in a new tab to see it. You can switch back at any time.',
				),
				'Every page now uses the new look, with your colours and logo carried across.'
			),
			self::guide(
				'look-branding',
				'ch-look',
				$setup,
				'Club name and colours',
				'Clubhouse → Setup → Base Look & Branding',
				'Your main colour is used for buttons, links and highlights across the whole site.',
				array(
					'Under *Branding*, type the club\'s name as you want it to read in the header and in browser tabs.',
					'Pick a *Main colour*. A colour that would leave text too faint to read is refused rather than saved, so nothing you choose can make the site unreadable.',
					'A *Second colour* is optional. Leave it empty and one is worked out from the first.',
					'Press *Save changes*.',
				),
				'Buttons, links and highlights change colour everywhere at once.'
			),
			self::guide(
				'look-logo',
				'ch-look',
				$setup,
				'Logo and browser tab icon',
				'Clubhouse → Setup → Base Look & Branding',
				'',
				array(
					'Under *Branding*, press *Choose an image* under *Logo* and pick a picture from your media library, or upload one. A picture with a transparent background sits best on the header.',
					'Do the same for *Browser tab icon* — a small square picture, ideally your crest on its own.',
					'Press *Save changes*.',
				),
				'The logo appears in the header and footer, and the icon in the browser tab and in bookmarks. With no logo set, the club name is shown in its place.'
			),
			self::guide(
				'look-social',
				'ch-look',
				$setup,
				'Linking your social accounts',
				'Clubhouse → Setup → Base Look & Branding',
				'',
				array(
					'Under *Branding*, paste the full web address of each account the club has: *Facebook*, *Instagram*, *LinkedIn* and *X*.',
					'Leave any the club does not use empty, and its icon is simply not shown.',
					'Press *Save changes*.',
				),
				'Icons for the accounts you filled in appear in the footer and in the social section of the contact page.'
			),
			self::guide(
				'look-demo',
				'ch-look',
				$setup,
				'Demo mode',
				'Clubhouse → Setup → Demo mode',
				'Demo mode shows a band across the top of the site saying it is a demonstration, so a site being set up is never mistaken for a live one.',
				array(
					'Open the *Demo mode* tab.',
					'Switch *Demo mode on* off when the site is ready, or on while it is still being built.',
					'Press *Save changes*.',
				),
				'The band appears or disappears on every page straight away.'
			),
		);
	}

	/** @return array<int,array<string,mixed>> */
	private static function pages(): array {
		$content = Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP;
		$setup   = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP;

		return array(
			self::guide(
				'pages-edit',
				'ch-pages',
				$content,
				'Changing the words and pictures on a page',
				'Pages → All Pages',
				'Each page holds its own words and pictures, and you edit them on the page itself.',
				array(
					'Open *Pages* and press *Edit* under the page you want — Home, About, Membership, Contact and the rest are all there.',
					'Pick the part of the page you want down the left. Each one is named after what it shows on the page: *Hero*, *Committee*, *FAQ* and so on.',
					'Change the wording, or press *Choose an image* to swap a picture for one from your media library.',
					'Press *Save changes*. The bar at the bottom says *Everything is saved* when it is done.',
				),
				'Open the page on the site to see the change. Every save is kept, so you can go back to what you had before from the page\'s *Revisions*.'
			),
			self::guide(
				'pages-sections',
				'ch-pages',
				$content,
				'Hiding a section of a page',
				'Pages → All Pages',
				'Most parts of a page can be switched off without deleting anything. The words and pictures stay saved, and come back exactly as they were when it is switched on again.',
				array(
					'Open the page under *Pages* and pick the part you want to hide down the left.',
					'Use the switch at the top of that part to turn it off. A part with no switch is one the page needs.',
					'Press *Save changes*.',
				),
				'The section disappears from the page. If you are looking for something that has gone from a page, this switch is the first place to check.'
			),
			self::guide(
				'pages-visibility',
				'ch-pages',
				$setup,
				'Switching a whole page on or off',
				'Clubhouse → Setup → Visibility',
				'A page that is switched off is not in the menu and cannot be reached by its address — a visitor gets a not-found page. Nothing on it is deleted.',
				array(
					'Open the *Visibility* tab.',
					'Under *Pages*, switch off any page the club does not use — a club with no bookings has no need of a Bookings page.',
					'Press *Save changes*.',
				),
				'The page drops out of the site. Switch it back on here whenever it is needed, and everything on it is still there.'
			),
			self::guide(
				'pages-menu',
				'ch-pages',
				$content,
				'Arranging the top menu',
				'Clubhouse → Setup → Menu',
				'The menu across the top of every page is yours to arrange: which pages appear, in what order, and what each is called.',
				array(
					'Open the *Menu* tab.',
					'Press *Add* to add an item. Give it a *Label* and choose what it *Links to* — start typing a page\'s name and pick it from the suggestions.',
					'Tick *Show under the item above* to tuck an item into a drop-down beneath the one before it.',
					'Drag items to reorder them, or press the bin to remove one.',
					'Press *Save changes*.',
				),
				'The menu changes on every page. A page you switch off on the Visibility tab is dropped from the menu for you.'
			),
			self::guide(
				'pages-global',
				'ch-pages',
				$content,
				'Header, footer and cookie notice',
				'Clubhouse → Global content',
				'Some words appear on every page rather than on one, and they are edited in one place.',
				array(
					'Open *Clubhouse → Global content*.',
					'Pick *Header* for the strapline and the button in the top bar, *Footer* for the address, opening lines and small print, or *Cookie notice* for the wording of the cookie banner.',
					'Change the wording and press *Save changes*.',
				),
				'Every page picks up the change straight away.'
			),
			self::guide(
				'pages-home',
				'ch-pages',
				$content,
				'The home page',
				'Pages → Home',
				'The home page is built from more parts than any other, and most of them can be switched off.',
				array(
					'Open *Pages* and edit *Home*.',
					'*Top of the page* holds the *Hero* — the big picture and headline — the *Quick tiles* beneath it and the *Ticker*.',
					'*The club* holds the *Sports grid*, the *Clubhouse band*, the *Membership tiers* and the *Activity tabs* of fixtures and events, which fill themselves from your collections.',
					'*News and community* holds the latest *News*, the *Social feed*, your *Find us details*, *Sponsors* and *Social* links.',
					'Change what you want, switch off what you do not need, and press *Save changes*.',
				),
				'A part that draws from a collection — sports, fixtures, sponsors — shows whatever is in that list, so add to the list rather than to the page.'
			),
			self::guide(
				'pages-social-feed',
				'ch-pages',
				$content,
				'Showing your latest social posts',
				'Pages → Home → Social feed',
				'The social feed on the home page shows posts you paste in. It cannot yet read your accounts directly, so it is refreshed by hand.',
				array(
					'On Facebook or Instagram, open the post and copy its web address.',
					'Open *Pages*, edit *Home* and pick *Social feed* down the left.',
					'Press *Add a row* and paste the address in, with a caption and a picture if you want one shown.',
					'Remove any old post you no longer want, then press *Save changes*.',
				),
				'The feed on the home page shows the posts in the order you listed them.'
			),
			self::guide(
				'pages-about',
				'ch-pages',
				$content,
				'The About page',
				'Pages → About',
				'',
				array(
					'Open *Pages* and edit *About*.',
					'Write the club\'s story in *History*, what it stands for in *Values*, and what it has in *Facilities*.',
					'*Committee* fills itself from the people under *Collections → People* who have a committee role, so add people there rather than here.',
					'*Get involved* and the *Call to action* are where you ask people to volunteer, coach or join.',
					'Press *Save changes*.',
				),
				''
			),
			self::guide(
				'pages-contact',
				'ch-pages',
				$content,
				'The Contact page and its form',
				'Pages → Contact',
				'',
				array(
					'Open *Pages* and edit *Contact*.',
					'*Contact form* holds the heading, the club\x27s address, email and phone, a map picture, and the *Form shortcode* from SureForms that puts the form itself on the page — the SureForms section of this Guides page covers making one.',
					'*Directory* fills itself from the people under *Collections → People* who have a directory role — the fixtures secretary, the safeguarding officer and so on.',
					'*Social* repeats the social links you set under Setup, with its own heading.',
					'Press *Save changes*.',
				),
				'Send yourself a message through the form once to be sure it arrives.'
			),
			self::guide(
				'pages-legal',
				'ch-pages',
				$content,
				'Privacy, terms and club rules',
				'Pages → Privacy, Terms and Club rules',
				'Three plain pages of text, each with a heading and a body.',
				array(
					'Open *Pages* and edit *Privacy*, *Terms* or *Club rules*.',
					'Pick *Hero* for the heading and *Policy*, *Terms* or *Rules* for the body, and write or paste the text.',
					'Press *Save changes*.',
				),
				'The pages are linked from the footer. Any of the three can be switched off on the Visibility tab of Setup if the club does not need it.'
			),
			self::guide(
				'pages-images-needed',
				'ch-pages',
				$content,
				'"Pictures still needed" after an import',
				'Pages → All Pages',
				'An import can name a picture it could not fetch. Until you add it, a notice on the page editors lists what is still missing.',
				array(
					'Open the page the notice names and pick the part it points to.',
					'Press *Choose an image* on the empty picture and pick or upload one.',
					'Press *Save changes*.',
				),
				'The picture drops off the notice, and the notice goes once nothing is left.'
			),
		);
	}

	/** @return array<int,array<string,mixed>> */
	private static function collections(): array {
		$content = Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP;

		return array(
			self::guide(
				'collections-how',
				'ch-collections',
				$content,
				'How collections work',
				'Collections',
				'A collection is a list rather than a page. You add an item once, and every part of the site that shows that kind of thing updates by itself — a fixture appears on the calendar, the events page and the home page without being added to any of them.',
				array(
					'Open *Collections* and pick a list: *Sports*, *Teams*, *Fixtures*, *Events*, *Sponsors* or *People*.',
					'Press *Add New* to add an item, or open one to change it.',
					'Give it a name at the top, fill in the details, and press *Publish* or *Update*.',
					'To take something off the site, open it and press *Move to Bin*.',
				),
				'An empty list shows nothing on the site, so a section that looks bare usually needs items here rather than words on the page.'
			),
			self::guide(
				'collections-sports',
				'ch-collections',
				$content,
				'Sports',
				'Collections → Sports',
				'One entry for each sport the club plays. They appear on the Sports page and in the sports grid on the home page.',
				array(
					'Open *Collections → Sports* and press *Add New*.',
					'Name it, then give it a *Short label* for tight spaces, a *Subtitle* and a *Description*.',
					'The two *Stat* pairs are a number and what it counts — "6 teams", "Est. 1921" — shown on the sport\'s card.',
					'Add *Training times*, a *Contact name* and *Contact email*, and an *Image*.',
					'Press *Publish*.',
				),
				'Teams are attached to a sport by name, so add the sports before the teams.'
			),
			self::guide(
				'collections-teams',
				'ch-collections',
				$content,
				'Teams',
				'Collections → Teams',
				'',
				array(
					'Open *Collections → Teams* and press *Add New*.',
					'Name the team, type its *Sport* exactly as the sport is named, and add a *Description*, *Match day* and *League*.',
					'Add *Training times*, a *Contact name* and *Contact email*, and an *Image*.',
					'If the team has a page of its own elsewhere, paste it into *Team page link*.',
					'Press *Publish*.',
				),
				'The team appears on the Teams page, grouped under its sport.'
			),
			self::guide(
				'collections-fixtures',
				'ch-collections',
				$content,
				'Fixtures and results',
				'Collections → Fixtures',
				'A fixture is added before the match and becomes a result afterwards, so one entry serves both.',
				array(
					'Open *Collections → Fixtures* and press *Add New*.',
					'Name it, then fill in the *Sport*, *Match date*, *Kick-off*, *Venue*, *Home team* and *Away team*.',
					'Press *Publish*. It appears on the calendar and in the fixtures tab on the home page.',
					'After the match, open it again: put the *Score* in, choose the *Outcome* — win, draw or loss — and add a *Result note* if there is a story to tell.',
					'Press *Update*.',
				),
				'Past fixtures move from the upcoming list to the results on their own, by date.'
			),
			self::guide(
				'collections-events',
				'ch-collections',
				$content,
				'Events',
				'Collections → Events',
				'Anything that is not a match: presentation nights, open days, junior camps.',
				array(
					'Open *Collections → Events* and press *Add New*.',
					'Name it, give it a *Tag* such as "Social" or "Juniors", a *Date label* as you want it to read, and the *Date it ends*.',
					'Write the *Detail*. If people should do something — book, buy a ticket — give the button a *Button label* and a *Button link*.',
					'Leave *Status* as upcoming; change it to past once it has happened, or let the end date do it.',
					'Press *Publish*.',
				),
				'It appears on the Events page, the calendar and the home page.'
			),
			self::guide(
				'collections-sponsors',
				'ch-collections',
				$content,
				'Sponsors',
				'Collections → Sponsors',
				'',
				array(
					'Open *Collections → Sponsors* and press *Add New*.',
					'Name the sponsor and set its logo as the *Featured image*, on the right.',
					'Paste the sponsor\'s site into *Website URL* so the logo links to it.',
					'Press *Publish*.',
				),
				'Sponsors appear in the band on the home page. Set the *Order* number on each to arrange them.'
			),
			self::guide(
				'collections-people',
				'ch-collections',
				$content,
				'People: the committee and the directory',
				'Collections → People',
				'One list of people feeds two places: the committee on the About page, and the directory on the Contact page. A person can be in both.',
				array(
					'Open *Collections → People* and press *Add New*.',
					'Name them and add a *Photo*.',
					'Give them a *Committee role* — Chair, Treasurer — to put them on the About page.',
					'Give them a *Directory role* — Fixtures secretary, Safeguarding — to put them in the contact directory, with their *Email* if it should be shown.',
					'Press *Publish*.',
				),
				'Leave a role empty and the person is left off that page.'
			),
		);
	}

	/** @return array<int,array<string,mixed>> */
	private static function news(): array {
		$content = Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP;

		return array(
			self::guide(
				'news-post',
				'ch-news',
				$content,
				'Publishing a news story',
				'Posts → Add New',
				'News is ordinary WordPress posts. The News page and the home page show the latest ones by themselves.',
				array(
					'Open *Posts* and press *Add New*.',
					'Give the story a title and write it. Set a *Featured image* on the right — it is the picture shown in the list.',
					'Press *Publish*.',
				),
				'It appears at the top of the News page and in the news section of the home page. The WordPress section of this Guides page covers drafts, scheduling and editing in more detail.'
			),
			self::guide(
				'news-page',
				'ch-news',
				$content,
				'The News page',
				'Pages → News',
				'',
				array(
					'Open *Pages* and edit *News*.',
					'*Page head* is the heading and introduction. *Featured story* chooses which post is shown large at the top, and *Stories* how the rest are listed.',
					'Press *Save changes*.',
				),
				''
			),
		);
	}

	/** @return array<int,array<string,mixed>> */
	private static function members( bool $shop ): array {
		$setup   = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP;
		$content = Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP;

		$guides = array(
			self::guide(
				'members-fields',
				'ch-members',
				$setup,
				'What you ask your members',
				'Clubhouse → Setup → Members',
				'The questions a member answers when they join — emergency contact, medical notes, a squad number — are yours to set. Some are answered by the member, some only by the club.',
				array(
					'Open the *Members* tab.',
					'Press *Add a row* under *Member fields*. Give the question a short *Reference* the club uses for it, the *Question* as the member reads it, and the kind of *Answer* — text, a date, a choice.',
					'For a choice, list the *Choices, one per line*.',
					'Choose *Who fills it in*: the member, or the club only. Add a *Hint under the question* if it needs one, and tick *Must be answered* if it does.',
					'Tick *Show the answers as a column* to see it on the Users list.',
					'Press *Save changes*.',
				),
				'Members see the questions when they join and on their profile in the member area. Staff see and edit every answer under Users.'
			),
			self::guide(
				'members-answers',
				'ch-members',
				$content,
				'Seeing and changing a member\'s answers',
				'Users → All Users',
				'',
				array(
					'Open *Users*. Any question you chose to show as a column is there, so you can see the answers at a glance.',
					'Press *Edit* under a member to see all their answers, including the ones only the club fills in.',
					'Change what you need and press *Update User*.',
				),
				'Nothing is required on this screen, so you can change one thing without filling in the rest.'
			),
			self::guide(
				'members-area',
				'ch-members',
				$setup,
				'The member area',
				'Pages → Member area',
				'A signed-in member has an area of their own with their profile, their plan, orders and invoices, and any bookings — one page, whichever plugin holds each part.',
				array(
					'Open *Pages* and edit *Member area* to change the words that frame it.',
					'The *Welcome pack* — how to get in, where to park, who to ask — is written once under *Clubhouse → Global content* and shown to every member here.',
					'Press *Save changes*.',
				),
				'A visitor who is not signed in is sent to the sign-in page instead.'
			),
			self::guide(
				'members-signin',
				'ch-members',
				$setup,
				'Where members go after signing in',
				'Clubhouse → Setup → Settings',
				'',
				array(
					'Open the *Settings* tab.',
					'Under *After signing in*, choose the page a member lands on — usually the member area.',
					'Under *After signing out*, choose where they go next — usually the home page.',
					'Press *Save changes*.',
				),
				''
			),
			self::guide(
				'members-emails',
				'ch-members',
				$setup,
				'The emails the site sends',
				'Clubhouse → Setup → Settings',
				'Emails about joining, renewing and resetting a password go out in the club\'s name.',
				array(
					'Open the *Settings* tab.',
					'Under *Emails*, set the *From name* members see — the club\'s name, usually — and the *Reply-to address* their replies go to.',
					'Press *Save changes*.',
				),
				'Every email the site sends from now on uses them.'
			),
		);

		if ( $shop ) {
			$guides[] = self::guide(
				'members-tiers',
				'ch-members',
				$setup,
				'Membership tiers and prices',
				'SureCart → Products',
				'Each tier a member can buy — adult, junior, family — is a product in the shop, and the Membership page lists them by itself.',
				array(
					'Open *SureCart → Products* and press *Add New*.',
					'Name the tier, set its price and how often it is paid, and press *Save*.',
					'Open *Pages*, edit *Membership* and pick *Tiers* to choose which products are shown and mark one as *Most popular*.',
					'Use *Included / excluded* on the same page to say what each tier gets.',
					'Press *Save changes*.',
				),
				'The tier appears on the Membership page and in the tiers band on the home page, with a button straight to the checkout.'
			);
			$guides[] = self::guide(
				'members-join',
				'ch-members',
				$setup,
				'How someone joins',
				'The Membership page on the site',
				'Knowing the path a new member takes makes it easier to help one who is stuck.',
				array(
					'They open *Membership* and press the button under a tier.',
					'They fill in their name, email and the questions you set on the Members tab, and pay.',
					'They arrive on a confirmation page, get an email, and can sign in to the member area from then on.',
				),
				'Their answers and plan are under *Users*; their payment is under *SureCart → Orders*. The SureCart section of this Guides page covers refunds and renewals.'
			);
			$guides[] = self::guide(
				'members-shop-pages',
				'ch-members',
				$setup,
				'The checkout and confirmation pages',
				'Pages → All Pages',
				'The shop needs a checkout page and a confirmation page, and BlueWorx keeps them present for you.',
				array(
					'If a BlueWorx notice in the admin says a shop page is missing, press the button on it and the page is made.',
					'The two pages are ordinary pages under *Pages*, drawn without the club\'s header and footer so the checkout stands on its own.',
				),
				'Nothing else on those pages needs editing.'
			);
		}

		return $guides;
	}

	/** @return array<int,array<string,mixed>> */
	private static function bookings(): array {
		$content = Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP;
		$setup   = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP;

		return array(
			self::guide(
				'bookings-page',
				'ch-bookings',
				$content,
				'The Bookings page',
				'Pages → Bookings',
				'Courts, sessions and coaching are booked through LatePoint, and the Bookings page is the club\'s front door to it.',
				array(
					'Open *Pages* and edit *Bookings*.',
					'Write the heading and introduction in *Hero*.',
					'*Sessions and services*, *Courts and locations* and *Coaches and staff* fill themselves from what is set up in LatePoint — the LatePoint section of this Guides page covers adding them.',
					'Press *Save changes*.',
				),
				'A member\'s own bookings are also listed in the member area.'
			),
			self::guide(
				'bookings-off',
				'ch-bookings',
				$setup,
				'Turning bookings off',
				'Clubhouse → Setup → Visibility',
				'',
				array(
					'Open the *Visibility* tab and switch off *Bookings*.',
					'Press *Save changes*.',
				),
				'The page and its menu item go. Everything in LatePoint is untouched, and switching the page back on brings it back.'
			),
		);
	}

	/** @return array<int,array<string,mixed>> */
	private static function search(): array {
		$setup = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP;

		return array(
			self::guide(
				'search-report',
				'ch-search',
				$setup,
				'How each page reads in Google',
				'Clubhouse → Search & sharing',
				'The report reads every page exactly as a search engine receives it — with sections switched off and the current look applied — and shows what it found.',
				array(
					'Open *Clubhouse → Search & sharing*.',
					'Each page is listed with its title, the description a search result would show, and the picture used when the page is shared.',
					'A page with a problem — no description, a heading missing — says so. Follow the link to the page and fix the words it names.',
					'Come back to the report to check it is clear.',
				),
				'The title and description come from the page\'s own words, so a better hero heading is a better search result.'
			),
			self::guide(
				'search-sharing',
				'ch-search',
				$setup,
				'What a shared link looks like',
				'Clubhouse → Search & sharing',
				'When someone pastes a page into a message or a post, the picture and text shown with it are the page\'s own.',
				array(
					'Open the report and find the page.',
					'The picture shown is the hero picture of that page. Change it under *Pages* if it is not the one you want.',
					'The text is the page\'s description. Change the hero wording to change it.',
				),
				'Some services keep an old copy for a while, so a change can take a day to show when the link is shared again.'
			),
		);
	}

	/** @return array<int,array<string,mixed>> */
	private static function import(): array {
		$setup = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP;

		return array(
			self::guide(
				'import-site',
				'ch-import',
				$setup,
				'Bringing in an existing website',
				'Clubhouse → Import',
				'If the club already has a website, Import reads what you give it and fills the pages and collections for you. It is for setting a site up, not for small changes later — it replaces the content on every page it recognises.',
				array(
					'Open *Clubhouse → Import*.',
					'Press *Download the prompt* and paste the file it gives you into an AI chat. The chat interviews you about the club and writes the content.',
					'The chat hands back a file called clubhouse-import.json. Upload it on the same screen.',
					'Read the preview: it shows exactly what will change, page by page, before anything is saved.',
					'Press *Apply this import*.',
				),
				'The pages and lists are filled. Go through each page under *Pages* to check the words, and add any picture the "Pictures still needed" notice lists.'
			),
			self::guide(
				'import-again',
				'ch-import',
				$setup,
				'Running an import again',
				'Clubhouse → Import',
				'',
				array(
					'Check what you have changed by hand since the first import — those pages will be overwritten.',
					'Upload the file as before and read the preview of what it will replace. Each upload only changes what that file contains.',
					'Confirm only if you are happy to lose the hand-made changes on those pages.',
				),
				'Every page keeps its revisions, so a page\'s earlier words can be brought back from *Revisions* if the import replaced something you wanted.'
			),
		);
	}

	/** @return array<int,array<string,mixed>> */
	private static function admin(): array {
		$setup = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP;

		return array(
			self::guide(
				'admin-access',
				'ch-admin',
				'manage_options',
				'Who can reach what',
				'Settings → ClubHouse access',
				'A report for administrators: every person with a ClubHouse role, and which ClubHouse screens each of them can open.',
				array(
					'Open *Settings → ClubHouse access*.',
					'Each person is listed with their role and the ClubHouse screens they can reach.',
					'To change what someone can do, change their role under *Users* — the report reads the roles, it does not set them.',
				),
				'The same answer is shown as tags in the top bar of every ClubHouse screen, so you can see who can reach the screen you are on.'
			),
			self::guide(
				'admin-whats-new',
				'ch-admin',
				$setup,
				'What has changed in ClubHouse',
				'Clubhouse → What\'s new',
				'',
				array(
					'Open *Clubhouse → What\'s new*.',
					'The newest version is at the top, with a plain line for each change.',
				),
				'If something on the site looks different after an update, this is where it is explained.'
			),
			self::guide(
				'admin-update',
				'ch-admin',
				'manage_options',
				'Updating ClubHouse',
				'Plugins → Installed Plugins',
				'',
				array(
					'Open *Plugins*. When a new version is available, *BlueWorx Labs | ClubHouse* shows an update line beneath it.',
					'Press *update now*.',
				),
				'Your words, pictures and settings are kept. Anything that needs moving to a new home moves itself on the first page load after the update.'
			),
		);
	}
}
