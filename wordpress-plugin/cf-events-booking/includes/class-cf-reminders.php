<?php
/**
 * Rappels et suivis automatiques par email (cron WP).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_Reminders {

	public static function init() {
		add_filter( 'cron_schedules', [ __CLASS__, 'add_schedule' ] );
		add_action( 'cfeb_send_reminders', [ __CLASS__, 'send_due_reminders' ] );
		add_action( 'cfeb_send_followups', [ __CLASS__, 'send_followups' ] );
	}

	public static function add_schedule( $schedules ) {
		$schedules['cfeb_hourly'] = [
			'interval' => 3600,
			'display'  => 'Toutes les heures (CF Events)',
		];
		return $schedules;
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( 'cfeb_send_reminders' ) ) {
			wp_schedule_event( time(), 'hourly', 'cfeb_send_reminders' );
		}
		if ( ! wp_next_scheduled( 'cfeb_send_followups' ) ) {
			wp_schedule_event( time(), 'daily', 'cfeb_send_followups' );
		}
	}

	public static function unschedule() {
		$ts = wp_next_scheduled( 'cfeb_send_reminders' );
		if ( $ts ) {
			wp_unschedule_event( $ts, 'cfeb_send_reminders' );
		}
		$ts = wp_next_scheduled( 'cfeb_send_followups' );
		if ( $ts ) {
			wp_unschedule_event( $ts, 'cfeb_send_followups' );
		}
	}

	public static function send_due_reminders() {
		$opts           = CF_Admin::get_options();
		$reminder_hours = (int) ( $opts['reminder_hours'] ?? 24 );
		if ( $reminder_hours <= 0 ) {
			return;
		}

		$now          = time();
		$target       = $now + ( $reminder_hours * 3600 );
		$window_start = gmdate( 'Y-m-d H:i:s', $target - 1800 );
		$window_end   = gmdate( 'Y-m-d H:i:s', $target + 1800 );

		global $wpdb;
		$posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			 WHERE p.post_type = %s AND p.post_status = 'publish'
			 AND pm.meta_key = '_cfeb_date_debut'
			 AND pm.meta_value >= %s AND pm.meta_value <= %s",
			CFEB_SLUG,
			$window_start,
			$window_end
		) );

		if ( empty( $posts ) ) {
			return;
		}

		$table = $wpdb->prefix . CFEB_TABLE;
		foreach ( $posts as $row ) {
			$event = get_post( $row->ID );
			if ( ! $event ) {
				continue;
			}

			$bookings = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE event_id = %d AND statut = 'confirme' AND rappel_envoye = 0",
				$row->ID
			) );

			foreach ( $bookings as $booking ) {
				self::send_reminder( (array) $booking, $event );
				$wpdb->update( $table, [ 'rappel_envoye' => 1 ], [ 'id' => $booking->id ], [ '%d' ], [ '%d' ] );
			}
		}
	}

	public static function send_reminder( $booking, $event_post ) {
		$opts = CF_Admin::get_options();
		$meta = CF_CPT::get_meta( $event_post->ID );
		$date = $meta['date_debut'] ? date_i18n( 'l j F Y à H\hi', strtotime( $meta['date_debut'] ) ) : '';
		$lieu = $meta['lieu'] ?: ( $meta['lien_visio'] ? 'En ligne' : '' );

		$subject = '⏰ Rappel — ' . $event_post->post_title . ' c\'est bientôt !';

		// Message de rappel personnalisé (CF Réservations → Paramètres →
		// Emails) s'il est renseigné, sinon repli sur le texte par défaut —
		// jusqu'ici ce réglage n'était jamais réellement utilisé à l'envoi.
		$custom = trim( (string) ( $opts['rappel_msg'] ?? '' ) );
		if ( $custom ) {
			$vars   = [
				'{prenom}'    => $booking['prenom'],
				'{nom}'       => $booking['nom'] ?? '',
				'{evenement}' => $event_post->post_title,
				'{date}'      => $date,
				'{lieu}'      => $lieu,
			];
			$detail = '<div>' . wpautop( wp_kses_post( str_replace( array_keys( $vars ), array_values( $vars ), $custom ) ) ) . '</div>'
				. ( $meta['lien_visio'] ? '<p>🔗 <a href="' . esc_url( $meta['lien_visio'] ) . '">Rejoindre en ligne</a></p>' : '' )
				. '<p><a href="' . esc_url( get_permalink( $event_post->ID ) ) . '">Voir les détails de l\'événement</a></p>';
		} else {
			$detail = '<p>Bonjour ' . esc_html( $booking['prenom'] ) . ',</p>'
				. '<p>Nous vous rappelons votre réservation pour <strong>' . esc_html( $event_post->post_title ) . '</strong>.</p>'
				. ( $date ? '<p>📅 <strong>' . esc_html( $date ) . '</strong></p>' : '' )
				. ( $lieu ? '<p>📍 ' . esc_html( $lieu ) . '</p>' : '' )
				. ( $meta['lien_visio'] ? '<p>🔗 <a href="' . esc_url( $meta['lien_visio'] ) . '">Rejoindre en ligne</a></p>' : '' )
				. '<p><a href="' . esc_url( get_permalink( $event_post->ID ) ) . '">Voir les détails de l\'événement</a></p>';
		}

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $opts['from_name'] . ' <' . $opts['from_email'] . '>',
		];

		$body = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head><body style="font-family:\'Segoe UI\',Helvetica,Arial,sans-serif;padding:32px;background:#f3f4f6;">'
			. '<table width="600" style="max-width:600px;background:#fff;border-radius:12px;padding:32px;margin:0 auto;">'
			. '<tr><td><h2 style="color:#1a3c5e;">' . esc_html( $subject ) . '</h2>' . $detail . '</td></tr>'
			. '</table></body></html>';

		wp_mail( $booking['email'], $subject, $body, $headers );
	}

	public static function send_followups() {
		$opts           = CF_Admin::get_options();
		$followup_hours = (int) ( $opts['followup_hours'] ?? 0 );
		if ( $followup_hours <= 0 ) {
			return;
		}

		$now          = time();
		$target_start = gmdate( 'Y-m-d H:i:s', $now - ( $followup_hours * 3600 ) - 1800 );
		$target_end   = gmdate( 'Y-m-d H:i:s', $now - ( $followup_hours * 3600 ) + 1800 );

		global $wpdb;
		$posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			 WHERE p.post_type = %s AND p.post_status = 'publish'
			 AND pm.meta_key = '_cfeb_date_fin'
			 AND pm.meta_value >= %s AND pm.meta_value <= %s",
			CFEB_SLUG,
			$target_start,
			$target_end
		) );

		if ( empty( $posts ) ) {
			return;
		}

		$table        = $wpdb->prefix . CFEB_TABLE;
		$followup_msg = get_option( 'cfeb_followup_message', '' );

		foreach ( $posts as $row ) {
			$event = get_post( $row->ID );
			if ( ! $event ) {
				continue;
			}

			$bookings = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE event_id = %d AND statut IN ('present', 'confirme') AND followup_envoye = 0",
				$row->ID
			) );

			$event_meta = CF_CPT::get_meta( $event->ID );
			foreach ( $bookings as $booking ) {
				$opts_mail = CF_Admin::get_options();
				$subject   = '💌 Merci pour votre participation — ' . $event->post_title;
				$vars      = [
					'{prenom}'    => $booking->prenom,
					'{nom}'       => $booking->nom,
					'{email}'     => $booking->email,
					'{evenement}' => $event->post_title,
					'{date}'      => $event_meta['date_debut'] ? date_i18n( 'l j F Y', strtotime( $event_meta['date_debut'] ) ) : '',
				];
				$msg       = $followup_msg
					? str_replace( array_keys( $vars ), array_values( $vars ), $followup_msg )
					: 'Merci d\'avoir participé à notre événement. Nous espérons vous revoir bientôt !';
				$detail    = '<p>Bonjour ' . esc_html( $booking->prenom ) . ',</p>'
					. '<div>' . wpautop( wp_kses_post( $msg ) ) . '</div>'
					. '<p><a href="' . esc_url( home_url() ) . '">Voir nos prochains événements</a></p>';
				$headers   = [
					'Content-Type: text/html; charset=UTF-8',
					'From: ' . $opts_mail['from_name'] . ' <' . $opts_mail['from_email'] . '>',
				];
				$body    = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head><body style="font-family:\'Segoe UI\',Helvetica,Arial,sans-serif;padding:32px;background:#f3f4f6;">'
					. '<table width="600" style="max-width:600px;background:#fff;border-radius:12px;padding:32px;margin:0 auto;">'
					. '<tr><td><h2 style="color:#1a3c5e;">' . esc_html( $subject ) . '</h2>' . $detail . '</td></tr>'
					. '</table></body></html>';
				wp_mail( $booking->email, $subject, $body, $headers );
				$wpdb->update( $table, [ 'followup_envoye' => 1 ], [ 'id' => $booking->id ], [ '%d' ], [ '%d' ] );
			}
		}
	}
}
