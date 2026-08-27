<?php
/**
 * Schema.org JSON-LD pour les événements.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_JsonLd {

	public static function init() {
		add_action( 'wp_head', [ __CLASS__, 'output' ] );
	}

	public static function output() {
		if ( ! is_singular( CFEB_SLUG ) ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post ) {
			return;
		}
		$meta = CF_CPT::get_meta( $post->ID );

		$statut_event  = $meta['statut_event'] ?? 'publie';
		$schema_status = 'https://schema.org/EventScheduled';
		if ( 'annule' === $statut_event ) {
			$schema_status = 'https://schema.org/EventCancelled';
		} elseif ( 'reporte' === $statut_event ) {
			$schema_status = 'https://schema.org/EventPostponed';
		}

		$attendance_mode = $meta['lien_visio']
			? 'https://schema.org/OnlineEventAttendanceMode'
			: 'https://schema.org/OfflineEventAttendanceMode';

		$data = [
			'@context'            => 'https://schema.org',
			'@type'               => 'Event',
			'name'                => $post->post_title,
			'url'                 => get_permalink( $post->ID ),
			'eventStatus'         => $schema_status,
			'eventAttendanceMode' => $attendance_mode,
		];

		if ( $meta['date_debut'] ) {
			$data['startDate'] = gmdate( 'c', strtotime( $meta['date_debut'] ) );
		}
		if ( $meta['date_fin'] ) {
			$data['endDate'] = gmdate( 'c', strtotime( $meta['date_fin'] ) );
		}

		if ( $meta['lieu'] ) {
			$data['location'] = [
				'@type'   => 'Place',
				'name'    => $meta['lieu'],
				'address' => $meta['lieu'],
			];
		} elseif ( $meta['lien_visio'] ) {
			$data['location'] = [
				'@type' => 'VirtualLocation',
				'url'   => $meta['lien_visio'],
			];
		}

		if ( $meta['animateur'] ) {
			$data['organizer'] = [
				'@type' => 'Organization',
				'name'  => $meta['animateur'],
				'url'   => home_url(),
			];
		}

		$thumb = get_the_post_thumbnail_url( $post->ID, 'large' );
		if ( $thumb ) {
			$data['image'] = $thumb;
		}

		$prix         = (float) $meta['prix'];
		$data['offers'] = [
			'@type'         => 'Offer',
			'price'         => $prix > 0 ? $prix : 0,
			'priceCurrency' => 'EUR',
			'availability'  => CF_CPT::get_dispo( $post->ID ) > 0
				? 'https://schema.org/InStock'
				: 'https://schema.org/SoldOut',
			'url'           => get_permalink( $post->ID ),
		];

		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
