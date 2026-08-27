<?php
/**
 * Programme Pleine Vie — séquence d'accompagnement automatique.
 * Envoie les étapes (éditables) aux inscrits confirmés, au fil du mois,
 * via un cron quotidien. Ancrage : date de validation, ou date de démarrage.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFPV_Sequence {

	const NB_STEPS = 6;

	/* ── Étapes activées, triées par jour ─────────────────────────── */
	public static function steps() {
		$s     = wp_parse_args( get_option( 'cfpv_settings', [] ), CFPV_Install::default_settings() );
		$steps = [];
		for ( $i = 1; $i <= self::NB_STEPS; $i++ ) {
			if ( empty( $s[ "seq_{$i}_enabled" ] ) ) {
				continue;
			}
			$steps[] = [
				'key'   => $i,
				'day'   => (int) ( $s[ "seq_{$i}_day" ] ?? 0 ),
				'objet' => (string) ( $s[ "seq_{$i}_objet" ] ?? '' ),
				'corps' => (string) ( $s[ "seq_{$i}_corps" ] ?? '' ),
			];
		}
		usort( $steps, fn( $a, $b ) => $a['day'] <=> $b['day'] );
		return $steps;
	}

	/* ── Timestamp d'ancrage pour un inscrit ──────────────────────── */
	private static function anchor_ts( $row ) {
		$s = wp_parse_args( get_option( 'cfpv_settings', [] ), CFPV_Install::default_settings() );
		if ( 'demarrage' === ( $s['seq_anchor'] ?? 'confirmation' ) && ! empty( $s['demarrage'] ) ) {
			$ts = strtotime( $s['demarrage'] );
			if ( $ts ) {
				return $ts;
			}
		}
		// Défaut : date de validation de l'inscription
		return $row->confirme_le ? strtotime( $row->confirme_le ) : strtotime( $row->cree_le );
	}

	/* ── Cron quotidien : envoie les étapes dues ──────────────────── */
	public static function run_daily() {
		global $wpdb;
		$steps = self::steps();
		if ( ! $steps ) {
			return;
		}
		$now  = current_time( 'timestamp' );
		$rows = CFPV_Registrations::all( 'confirme', 500 );

		foreach ( $rows as $row ) {
			$anchor = self::anchor_ts( $row );
			if ( ! $anchor ) {
				continue;
			}
			$sent = json_decode( $row->seq_sent ?: '[]', true );
			$sent = is_array( $sent ) ? array_map( 'intval', $sent ) : [];
			$changed = false;

			foreach ( $steps as $step ) {
				if ( in_array( $step['key'], $sent, true ) ) {
					continue;
				}
				$due_ts = $anchor + ( $step['day'] * DAY_IN_SECONDS );
				if ( $now < $due_ts ) {
					continue; // pas encore l'heure
				}
				if ( CFPV_Emails::send_sequence_step( $row, $step['objet'], $step['corps'] ) ) {
					$sent[]  = $step['key'];
					$changed = true;
				}
			}

			if ( $changed ) {
				$wpdb->update( CFPV_Registrations::table(), [ 'seq_sent' => wp_json_encode( array_values( $sent ) ) ], [ 'id' => (int) $row->id ] );
			}
		}
	}

	/* ── Planification du cron ────────────────────────────────────── */
	public static function schedule() {
		if ( ! wp_next_scheduled( 'cfpv_daily_cron' ) ) {
			// Chaque jour à 8h (heure du site)
			$next = strtotime( 'tomorrow 08:00', current_time( 'timestamp' ) );
			wp_schedule_event( $next, 'daily', 'cfpv_daily_cron' );
		}
	}

	public static function unschedule() {
		$ts = wp_next_scheduled( 'cfpv_daily_cron' );
		if ( $ts ) {
			wp_unschedule_event( $ts, 'cfpv_daily_cron' );
		}
	}
}
