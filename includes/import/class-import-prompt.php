<?php
// includes/import/class-import-prompt.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Markdown prompt a club owner downloads and pastes into an AI
 * chat. Every field description is generated from Content_Catalogue and
 * Collection_Meta, so adding a section to ClubHouse updates the prompt on the
 * next download — there is no hand-maintained copy to drift. A lockstep test
 * asserts the coverage.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Import_Prompt {

	/** Filename offered to the browser on download. */
	public const FILENAME = 'clubhouse-import-prompt.md';

	public static function markdown( string $version ): string {
		$out  = self::preamble();
		$out .= self::content_inventory();
		$out .= self::collection_inventory();
		$out .= self::output_contract( $version );
		return $out;
	}

	private static function preamble(): string {
		return <<<'MD'
# ClubHouse website content interview

You are helping a sports club write the content for their ClubHouse website.
The club owner is not a copywriter and does not know the website's structure —
you do, because it is described below. Interview them, draft the copy, and at
the end produce a single JSON file they can upload back into their site.

## How to run the interview

1. Start by asking what the club is: its name, sports, where it plays, and
   roughly how old it is. Use that to inform every later draft.
2. Then work through the sections below **in the order they appear**, a few at
   a time. Do not dump the whole list at them.
3. For each section, explain in one sentence what it is for, then ask what they
   want it to say. Offer a draft they can accept or correct — do not make them
   write from scratch.
4. Keep each field to the length its description implies. Short text is a few
   words, not a paragraph. A paragraph is two or three sentences.
5. **Never invent facts.** Prices, dates, member counts, league names, results,
   people's names and email addresses must all come from the club. If they do
   not know one, leave the field out rather than guessing.
6. After each page, tell them what is left and ask whether to carry on now or
   generate the file so far. Both are fine — the file can be uploaded as many
   times as they like, and each upload only changes what it contains.
7. When they ask for the file, produce it exactly as described at the end.

## Images

Some fields are images. ClubHouse cannot receive an image through this chat, so
for each one ask for a **public image URL** — a link to the picture on their old
website, or a public link from Drive, Dropbox, or similar. If they do not have
one, leave the field out; the site will show a placeholder and they can upload
the picture in the admin later. Never invent an image URL.


MD;
	}

	private static function content_inventory(): string {
		$out = "## The pages\n\n";
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages() as $page ) {
			$out .= '### ' . $page['label'] . "\n\n";
			foreach ( $page['sections'] as $section ) {
				$address = (string) $section['store_page'] . '.' . (string) $section['key'];
				$out    .= '#### ' . $section['label'] . ' — `content.' . $address . "`\n\n";

				$note = self::section_note( $section );
				if ( '' !== $note ) {
					$out .= $note . "\n\n";
				}

				$fields = is_array( $section['fields'] ?? null ) ? $section['fields'] : array();
				$loop   = is_array( $section['loop'] ?? null ) ? $section['loop'] : array();

				if ( array() === $fields && array() === $loop ) {
					// A purely descriptive section (an auto/linkout with nothing of
					// its own to ask for) must say so explicitly. Otherwise the note
					// alone still reads as an invitation, and the assistant may ask
					// the club about it anyway and produce fields that only trigger
					// "Ignored unknown field" noise on upload.
					$out .= "This section takes no content from you — do not ask about it or include it in the file.\n\n";
					continue;
				}

				foreach ( $fields as $field ) {
					$out .= self::field_line( $field );
				}
				if ( array() !== $fields ) {
					$out .= "\n";
				}

				if ( array() !== $loop ) {
					$out .= 'Then a repeatable list of **' . $loop['name'] . '** entries, under `items`. Each entry has:' . "\n";
					foreach ( $loop['fields'] as $field ) {
						$out .= self::field_line( $field );
					}
					$out .= "\n";
				}
			}
		}
		return $out;
	}

	/** The catalogue's own explanatory note for a section, if it has one. */
	private static function section_note( array $section ): string {
		$parts = array();
		if ( ! empty( $section['note'] ) ) {
			$parts[] = (string) $section['note'];
		}
		if ( ! empty( $section['link']['text'] ) ) {
			$parts[] = (string) $section['link']['text'];
		}
		if ( ! empty( $section['auto']['text'] ) ) {
			$parts[] = (string) $section['auto']['text'];
		}
		return '' === implode( '', $parts ) ? '' : '_' . implode( ' ', $parts ) . '_';
	}

	private static function field_line( array $field ): string {
		$line = '- `' . $field['key'] . '` — ' . $field['label'] . ' (' . self::type_hint( $field ) . ')';
		if ( ! empty( $field['placeholder'] ) ) {
			// Some catalogue placeholders already read "e.g. …" themselves; don't double it.
			$placeholder = (string) $field['placeholder'];
			$line       .= ' ' . ( 0 === stripos( $placeholder, 'e.g.' ) ? $placeholder : 'e.g. ' . $placeholder );
		}
		return $line . "\n";
	}

	/** A plain-English description of what a field accepts. */
	private static function type_hint( array $field ): string {
		switch ( $field['type'] ) {
			case 'textarea':
				return 'a short paragraph';
			case 'url':
			case 'href':
				return 'a link';
			case 'image':
			case 'media':
				return 'a public image URL';
			case 'toggle':
				return 'true or false';
			case 'date':
				return 'a date, YYYY-MM-DD';
			case 'time':
				return 'a 24-hour time, HH:MM';
			case 'email':
				return 'an email address';
			case 'select':
				return 'one of: ' . self::option_list( $field );
			case 'text':
			default:
				return 'short text';
		}
	}

	/**
	 * Select options come in two shapes: the content catalogue keys them
	 * value => label, while Collection_Meta lists bare values. Handle both, and
	 * name the empty value rather than printing nothing.
	 */
	private static function option_list( array $field ): string {
		$options = is_array( $field['options'] ?? null ) ? $field['options'] : array();
		$values  = array_is_list( $options ) ? $options : array_keys( $options );
		$shown   = array();
		foreach ( $values as $value ) {
			$shown[] = '' === (string) $value ? '(leave out)' : (string) $value;
		}
		return implode( ', ', $shown );
	}

	private static function collection_inventory(): string {
		$out  = "## The collections\n\n";
		$out .= "These are lists the site builds pages from. Ask for as many entries as the club\n";
		$out .= "actually has — do not pad them out. Every entry needs a `title`.\n\n";

		foreach ( Blueworx_Clubhouse_Collection_Meta::types() as $type ) {
			$out .= '### ' . Blueworx_Clubhouse_Collection_Meta::label( $type ) . ' — `collections.' . $type . "`\n\n";
			$out .= '- `title` — the name shown on the site (short text)' . "\n";
			foreach ( Blueworx_Clubhouse_Collection_Meta::fields( $type ) as $field ) {
				$out .= self::field_line( $field );
			}
			$out .= "\n";
		}
		return $out;
	}

	private static function output_contract( string $version ): string {
		$example = <<<'JSON'
```json
{
  "clubhouse_import": 1,
  "generated_for": "VERSION",
  "content": {
    "home": {
      "hero": {
        "eyebrow": "Est. 1974 · Marlow",
        "title_lead": "One club, ",
        "title_highlight": "every sport",
        "image": { "url": "https://example.org/pavilion.jpg", "alt": "The pavilion" }
      }
    },
    "global": { "header": { "join": "Join the club" } }
  },
  "collections": {
    "clubhouse_sport": [
      { "title": "Tennis", "subtitle": "Four courts, all year",
        "image": { "url": "https://example.org/tennis.jpg" } }
    ]
  }
}
```
JSON;
		$example = str_replace( 'VERSION', $version, $example );

		return <<<MD
## The file you produce

When the club asks for the file, output **one JSON code block and nothing else
inside it**, and tell them to save it as `clubhouse-import.json` and upload it
at Club Content → Import in their ClubHouse admin.

Rules for the file:

- `"clubhouse_import": 1` and `"generated_for": "{$version}"` must both be present.
- Use the exact `content.<page>.<section>` addresses and the exact field keys
  listed above. A key that is not listed will be ignored on upload.
- **Leave out anything you did not discuss.** Uploading only changes what the
  file contains; absent sections keep whatever is already on the site. A blank
  string is not the same as leaving a field out — a blank string clears it.
- **Cover every section of a page you write about.** On upload the club is
  offered a tick box that switches off the sections a page's own file has no
  content for, so their site shows only what they gave you rather than leftover
  demo content. Pages the file says nothing about are never touched — so it is
  safe to do one page at a time, but half a page is not.
- Repeatable sections take their entries as a list under `items`.
- Images are `{ "url": "https://…", "alt": "…" }`. The `alt` is optional.
- Collections are lists of entries, each with a `title`.

{$example}

MD;
	}
}
