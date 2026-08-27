<?php
/**
 * Export iCal / ICS + liens calendrier.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_Ical {

	public static function init() {
		add_action( 'init', [ __CLASS__, 'handle_feed' ] );
		add_action( 'init', [ __CLASS__, 'handle_single_export' ] );
	}

	public static function handle_feed() {
		if ( empty( $_GET['cfeb_ical'] ) ) {
			return;
		}
		$events = get_posts( [
			'post_type'      => CFEB_SLUG,
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'meta_key'       => '_cfeb_date_debut',
			'meta_value'     => gmdate( 'Y-m-d' ),
			'meta_compare'   => '>=',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
		] );
		$cal_name = get_option( 'cfeb_ical_calendar_name', get_bloginfo( 'name' ) );
		$ics = self::generate_ics( $events, $cal_name );
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="evenements.ics"' );
		echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function handle_single_export() {
		if ( empty( $_GET['cfeb_export_ical'] ) ) {
			return;
		}
		$post_id = absint( $_GET['cfeb_export_ical'] );
		$post    = get_post( $post_id );
		if ( ! $post || CFEB_SLUG !== $post->post_type ) {
			return;
		}
		$ics      = self::event_to_ics( $post );
		$filename = sanitize_file_name( $post->post_title ) . '.ics';
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function generate_ics( array $events, $cal_name = '' ): string {
		if ( ! $cal_name ) {
			$cal_name = get_bloginfo( 'name' );
		}
		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//CF Events Booking//FR',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . self::escape_ical( $cal_name ),
			'X-WR-TIMEZONE:Europe/Paris',
		];
		$lines[] = self::vtimezone();
		foreach ( $events as $post ) {
			$lines[] = self::event_to_vevent( $post );
		}
		$lines[] = 'END:VCALENDAR';
		return implode( "\r\n", $lines ) . "\r\n";
	}

	public static function event_to_ics( $post ): string {
		$cal_name = get_option( 'cfeb_ical_calendar_name', get_bloginfo( 'name' ) );
		$lines    = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//CF Events Booking//FR',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . self::escape_ical( $cal_name ),
			'X-WR-TIMEZONE:Europe/Paris',
		];
		$lines[] = self::vtimezone();
		$lines[] = self::event_to_vevent( $post );
		$lines[] = 'END:VCALENDAR';
		return implode( "\r\n", $lines ) . "\r\n";
	}

	private static function event_to_vevent( $post ): string {
		$meta    = CF_CPT::get_meta( $post->ID );
		$all_day = (bool) $meta['all_day'];
		$uid     = 'cfeb-' . $post->ID . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
		$dtstamp = gmdate( 'Ymd\THis\Z' );

		$location = $meta['lieu'] ?: '';
		if ( ! $location && $meta['lien_visio'] ) {
			$location = $meta['lien_visio'];
		}

		$desc = wp_strip_all_tags( $post->post_content );
		$desc = preg_replace( '/\s+/', ' ', $desc );
		if ( $meta['infos_pratiques'] ) {
			$desc .= ' ' . wp_strip_all_tags( $meta['infos_pratiques'] );
		}

		$vevent   = [ 'BEGIN:VEVENT' ];
		$vevent[] = 'UID:' . $uid;
		$vevent[] = 'DTSTAMP:' . $dtstamp;
		$vevent[] = 'SUMMARY:' . self::escape_ical( $post->post_title );

		if ( $meta['date_debut'] ) {
			if ( $all_day ) {
				$vevent[] = 'DTSTART;VALUE=DATE:' . gmdate( 'Ymd', strtotime( $meta['date_debut'] ) );
				if ( $meta['date_fin'] ) {
					$vevent[] = 'DTEND;VALUE=DATE:' . gmdate( 'Ymd', strtotime( $meta['date_fin'] ) + 86400 );
				} else {
					$vevent[] = 'DTEND;VALUE=DATE:' . gmdate( 'Ymd', strtotime( $meta['date_debut'] ) + 86400 );
				}
			} else {
				$vevent[] = 'DTSTART;TZID=Europe/Paris:' . gmdate( 'Ymd\THis', strtotime( $meta['date_debut'] ) );
				if ( $meta['date_fin'] ) {
					$vevent[] = 'DTEND;TZID=Europe/Paris:' . gmdate( 'Ymd\THis', strtotime( $meta['date_fin'] ) );
				} else {
					$vevent[] = 'DTEND;TZID=Europe/Paris:' . gmdate( 'Ymd\THis', strtotime( $meta['date_debut'] ) + 7200 );
				}
			}
		}

		if ( $location ) {
			$vevent[] = 'LOCATION:' . self::escape_ical( $location );
		}
		if ( $desc ) {
			$vevent[] = self::fold_line( 'DESCRIPTION:' . self::escape_ical( trim( $desc ) ) );
		}
		$url = get_permalink( $post->ID );
		if ( $url ) {
			$vevent[] = 'URL:' . $url;
		}

		$statut_event = $meta['statut_event'] ?? 'publie';
		if ( 'annule' === $statut_event ) {
			$vevent[] = 'STATUS:CANCELLED';
		} else {
			$vevent[] = 'STATUS:CONFIRMED';
		}

		$vevent[] = 'END:VEVENT';
		return implode( "\r\n", $vevent );
	}

	public static function google_calendar_url( $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$meta = CF_CPT::get_meta( $post_id );
		if ( ! $meta['date_debut'] ) {
			return '';
		}
		$start = gmdate( 'Ymd\THis\Z', strtotime( $meta['date_debut'] ) );
		$end   = $meta['date_fin']
			? gmdate( 'Ymd\THis\Z', strtotime( $meta['date_fin'] ) )
			: gmdate( 'Ymd\THis\Z', strtotime( $meta['date_debut'] ) + 7200 );
		$params = [
			'action'   => 'TEMPLATE',
			'text'     => $post->post_title,
			'dates'    => $start . '/' . $end,
			'details'  => wp_strip_all_tags( $post->post_content ),
			'location' => $meta['lieu'] ?: '',
			'sprop'    => 'website:' . home_url(),
		];
		return 'https://calendar.google.com/calendar/render?' . http_build_query( $params );
	}

	public static function outlook_url( $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$meta = CF_CPT::get_meta( $post_id );
		if ( ! $meta['date_debut'] ) {
			return '';
		}
		$start = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $meta['date_debut'] ) );
		$end   = $meta['date_fin']
			? gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $meta['date_fin'] ) )
			: gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $meta['date_debut'] ) + 7200 );
		$params = [
			'path'     => '/calendar/action/compose',
			'rru'      => 'addevent',
			'subject'  => $post->post_title,
			'startdt'  => $start,
			'enddt'    => $end,
			'body'     => wp_strip_all_tags( $post->post_content ),
			'location' => $meta['lieu'] ?: '',
		];
		return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query( $params );
	}

	public static function ical_attachment( $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		return self::event_to_ics( $post );
	}

	private static function vtimezone(): string {
		return "BEGIN:VTIMEZONE\r\nTZID:Europe/Paris\r\nBEGIN:STANDARD\r\nTZOFFSETFROM:+0200\r\nTZOFFSETTO:+0100\r\nTZNAME:CET\r\nDTSTART:19701025T030000\r\nRRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10\r\nEND:STANDARD\r\nBEGIN:DAYLIGHT\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0200\r\nTZNAME:CEST\r\nDTSTART:19700329T020000\r\nRRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3\r\nEND:DAYLIGHT\r\nEND:VTIMEZONE";
	}

	private static function escape_ical( $str ): string {
		$str = str_replace( [ '\\', ';', ',', "\n", "\r" ], [ '\\\\', '\\;', '\\,', '\\n', '' ], $str );
		return $str;
	}

	private static function fold_line( $line ): string {
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}
		$folded = '';
		while ( strlen( $line ) > 75 ) {
			$folded .= substr( $line, 0, 75 ) . "\r\n ";
			$line    = substr( $line, 75 );
		}
		$folded .= $line;
		return $folded;
	}
}
