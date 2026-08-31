<?php
// includes/import/class-import-prompt.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Markdown prompt a club owner downloads and pastes into an AI
 * chat. Every field description is generated from Page_Fields and
 * Collection_Meta — the same two declarations the editing screens are built
 * from — so adding a section to ClubHouse updates the prompt on the next
 * download, and the prompt can never describe a field an owner cannot see.
 * There is no hand-maintained copy to drift; a lockstep test asserts the
 * coverage.
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
		$out  = "## The pages\n\n";
		$area = '';
		// Without the products adapter, the tier price_id select has only its
		// "Not connected" option, so the AI is told that is the only valid
		// value and a realistic import clears every tier's connection — the
		// editing screens and Import_Parser::sections() pass this same source
		// for exactly this reason; the prompt generator must match them.
		foreach ( Blueworx_Clubhouse_Page_Fields::sections( Blueworx_Clubhouse_Products_Source::get() ) as $address => $section ) {
			if ( $section['area'] !== $area ) {
				$area = $section['area'];
				$out .= '### ' . $section['area_label'] . "\n\n";
			}
			$out .= '#### ' . $section['section_label'] . ' — `content.' . str_replace( '/', '.', $address ) . "`\n\n";

			if ( '' !== $section['note'] ) {
				$out .= '_' . $section['note'] . "_\n\n";
			}

			$fields = $section['fields'];
			$items  = $section['items'];

			if ( array() === $fields && null === $items ) {
				// A purely descriptive section (one that shows a collection and
				// has nothing of its own to ask for) must say so explicitly.
				// Otherwise the note alone still reads as an invitation, and the
				// assistant may ask the club about it anyway and produce fields
				// that only trigger "Ignored unknown field" noise on upload.
				$out .= "This section takes no content from you — do not ask about it or include it in the file.\n\n";
				continue;
			}

			foreach ( $fields as $key => $field ) {
				$out .= self::page_field_line( (string) $key, $field );
			}
			if ( array() !== $fields ) {
				$out .= "\n";
			}

			if ( null !== $items ) {
				// "Then" only reads right after a list of the section's own
				// fields; a section that is nothing but a repeating list starts
				// with it.
				$lead = array() === $fields ? 'A' : 'Then a';
				$out .= $lead . ' repeatable list — **' . $items['label'] . '** — under `items`. Each entry has:' . "\n";
				foreach ( $items['fields'] as $cell ) {
					$out .= self::page_field_line( (string) $cell['id'], $cell );
				}
				$out .= "\n";
			}
		}
		return $out;
	}

	/**
	 * One line describing a page field. The key an import file uses is the bare
	 * one, not the screen-unique id the library needs, so it is passed in
	 * rather than read off the field.
	 *
	 * @param array<string,mixed> $field
	 */
	private static function page_field_line( string $key, array $field ): string {
		$line = '- `' . $key . '` — ' . $field['label'] . ' (' . self::kind_hint( $field ) . ')';
		if ( ! empty( $field['help'] ) ) {
			// Some help already reads "e.g. …" itself; don't double it.
			$help  = (string) $field['help'];
			$line .= ' ' . ( 0 === stripos( $help, 'e.g.' ) ? $help : 'e.g. ' . $help );
		}
		return $line . "\n";
	}

	/**
	 * A plain-English description of what a page field accepts, by the library
	 * kind it was declared with. A link and an email address are a 'text' with
	 * a format rather than kinds of their own — see Schema.
	 *
	 * @param array<string,mixed> $field
	 */
	private static function kind_hint( array $field ): string {
		switch ( (string) ( $field['kind'] ?? 'text' ) ) {
			case 'textarea':
				return 'a short paragraph';
			case 'media':
				return 'a public image URL';
			case 'toggle':
				return 'true or false';
			case 'date':
				return 'a date, YYYY-MM-DD';
			case 'number':
				return 'a whole number';
			case 'select':
				$values = array();
				foreach ( $field['options'] ?? array() as $option ) {
					$values[] = '' === (string) $option['value'] ? '(leave out)' : (string) $option['value'];
				}
				return 'one of: ' . implode( ', ', $values );
			case 'text':
			default:
				if ( 'url' === ( $field['format'] ?? '' ) ) {
					return 'a link';
				}
				if ( 'email' === ( $field['format'] ?? '' ) ) {
					return 'an email address';
				}
				return 'short text';
		}
	}

	private static function field_line( array $field ): string {
		$line = '- `' . $field['key'] . '` — ' . $field['label'] . ' (' . self::type_hint( $field ) . ')';
		if ( ! empty( $field['placeholder'] ) ) {
			// Some placeholders already read "e.g. …" themselves; don't double it.
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
	 * A collection field's select options. Collection_Meta lists bare values,
	 * but a keyed value => label map is read too, and the empty value is named
	 * rather than printed as nothing.
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
at Clubhouse → Import in their ClubHouse admin.

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
