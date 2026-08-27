<?php
/**
 * Page Statistiques — CF Événements & Réservations.
 *
 * Affiche un tableau de bord analytique :
 * – cartes résumé (totaux, CA estimé)
 * – graphique mensuel des réservations (CSS pur, 6 derniers mois)
 * – top 5 événements par nombre de réservations confirmées
 * – taux de remplissage des prochains événements
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_Stats {

	/* ── Enregistrement de la sous-page ─────────────────────────── */
	public static function register_page() {
		add_submenu_page(
			'edit.php?post_type=' . CFEB_SLUG,
			'Statistiques',
			'📊 Statistiques',
			'edit_posts',
			'cfeb-stats',
			[ __CLASS__, 'render' ]
		);
	}

	/* ── Rendu de la page ────────────────────────────────────────── */
	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Accès refusé.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . CFEB_TABLE;
		$now   = current_time( 'mysql' );

		/* ── Cartes résumé ──────────────────────────────────────── */
		$total_all       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_confirme  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(nb_places),0) FROM {$table} WHERE statut = %s", 'confirme' ) );
		$total_annule    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE statut = %s", 'annule' ) );
		$total_attente   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE statut = %s", 'liste_attente' ) );
		$total_present   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE statut = %s", 'present' ) );

		// CA estimé : SUM(prix * nb_places) pour les réservations confirmées
		$ca_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.event_id, b.nb_places FROM {$table} b WHERE b.statut = %s",
				'confirme'
			)
		);
		$ca_estime = 0.0;
		foreach ( $ca_rows as $row ) {
			$prix = (float) get_post_meta( $row->event_id, '_cfeb_prix', true );
			$ca_estime += $prix * (int) $row->nb_places;
		}

		// Ce mois-ci
		$month_start     = current_time( 'Y-m' ) . '-01 00:00:00';
		$total_ce_mois   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE statut != %s AND cree_le >= %s", 'annule', $month_start ) );
		$total_last_mois = self::bookings_last_month( $table );

		/* ── Graphique mensuel (6 derniers mois) ────────────────── */
		$months_data = self::get_monthly_data( $table, 6 );

		/* ── Top 5 événements ───────────────────────────────────── */
		$top_events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.event_id, SUM(b.nb_places) AS total_places, COUNT(*) AS nb_reservations, p.post_title
				 FROM {$table} b
				 LEFT JOIN {$wpdb->posts} p ON p.ID = b.event_id
				 WHERE b.statut = %s
				 GROUP BY b.event_id
				 ORDER BY total_places DESC
				 LIMIT 5",
				'confirme'
			)
		);

		/* ── Taux de remplissage (prochains événements) ─────────── */
		$upcoming = get_posts( [
			'post_type'      => CFEB_SLUG,
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'meta_key'       => '_cfeb_date_debut',
			'meta_value'     => $now,
			'meta_compare'   => '>=',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
		] );

		$max_monthly = max( array_column( $months_data, 'count' ) ?: [ 1 ] );
		?>
		<div class="wrap cfeb-admin-wrap cfeb-stats-wrap">
			<h1 class="wp-heading-inline">📊 Statistiques</h1>
			<hr class="wp-header-end">

			<!-- ══ Cartes résumé ══════════════════════════════════ -->
			<div class="cfeb-stats-cards">
				<?php
				self::stat_card( '📋', 'Total réservations', $total_all, '' );
				self::stat_card( '✅', 'Places confirmées', $total_confirme, '' );
				self::stat_card( '📅', 'Ce mois-ci', $total_ce_mois, $total_last_mois ? self::variation( $total_ce_mois, $total_last_mois ) : '' );
				self::stat_card( '⏳', 'Liste d\'attente', $total_attente, '' );
				self::stat_card( '❌', 'Annulées', $total_annule, '' );
				self::stat_card( '✔️', 'Présents', $total_present, '' );
				if ( $ca_estime > 0 ) {
					self::stat_card( '💶', 'CA estimé', number_format( $ca_estime, 2, ',', ' ' ) . ' €', '' );
				}
				?>
			</div>

			<div class="cfeb-stats-columns">

				<!-- ══ Graphique mensuel ══════════════════════════ -->
				<div class="cfeb-stats-section">
					<h2>Réservations — 6 derniers mois</h2>
					<div class="cfeb-bar-chart">
						<?php foreach ( $months_data as $md ) : ?>
							<?php $pct = $max_monthly > 0 ? round( $md['count'] * 100 / $max_monthly ) : 0; ?>
							<div class="cfeb-bar-item">
								<div class="cfeb-bar-wrap">
									<div class="cfeb-bar" style="height:<?php echo (int) $pct; ?>%" title="<?php echo (int) $md['count']; ?> réservation(s)">
										<span class="cfeb-bar-val"><?php echo (int) $md['count']; ?></span>
									</div>
								</div>
								<div class="cfeb-bar-label"><?php echo esc_html( $md['label'] ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- ══ Top événements ════════════════════════════ -->
				<div class="cfeb-stats-section">
					<h2>Top 5 événements (places confirmées)</h2>
					<?php if ( $top_events ) : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>Événement</th>
									<th style="width:100px;text-align:right;">Places</th>
									<th style="width:120px;text-align:right;">Réservations</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $top_events as $ev ) : ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( add_query_arg( [ 'post_type' => CFEB_SLUG, 'page' => 'cfeb-reservations', 'event_id' => $ev->event_id ], admin_url( 'edit.php' ) ) ); ?>">
												<?php echo esc_html( $ev->post_title ?: '#' . $ev->event_id ); ?>
											</a>
										</td>
										<td style="text-align:right;font-weight:700;"><?php echo (int) $ev->total_places; ?></td>
										<td style="text-align:right;"><?php echo (int) $ev->nb_reservations; ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p style="color:#646970;">Aucune réservation confirmée pour l'instant.</p>
					<?php endif; ?>
				</div>

			</div>

			<!-- ══ Taux de remplissage ════════════════════════════ -->
			<?php if ( $upcoming ) : ?>
			<div class="cfeb-stats-section" style="margin-top:24px;">
				<h2>Taux de remplissage — prochains événements</h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Événement</th>
							<th style="width:130px;">Date</th>
							<th style="width:90px;text-align:right;">Inscrits</th>
							<th style="width:90px;text-align:right;">Capacité</th>
							<th style="width:160px;">Remplissage</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $upcoming as $ev ) :
							$meta     = CF_CPT::get_meta( $ev->ID );
							$max      = (int) $meta['max_places'];
							$inscrits = CF_Booking::count_for_event( $ev->ID, 'confirme' );
							$pct      = $max > 0 ? min( 100, round( $inscrits * 100 / $max ) ) : 0;
							$color    = $pct >= 80 ? '#ef4444' : ( $pct >= 50 ? '#f59e0b' : '#22c55e' );
						?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( $ev->ID ) ); ?>"><?php echo esc_html( $ev->post_title ); ?></a></td>
							<td><?php echo $meta['date_debut'] ? esc_html( date_i18n( 'd/m/Y', strtotime( $meta['date_debut'] ) ) ) : '—'; ?></td>
							<td style="text-align:right;font-weight:700;"><?php echo (int) $inscrits; ?></td>
							<td style="text-align:right;"><?php echo $max > 0 ? (int) $max : '∞'; ?></td>
							<td>
								<?php if ( $max > 0 ) : ?>
									<div class="cfeb-progress-bar">
										<div class="cfeb-progress-fill" style="width:<?php echo (int) $pct; ?>%;background:<?php echo esc_attr( $color ); ?>;"></div>
									</div>
									<span style="font-size:12px;color:#646970;"><?php echo (int) $pct; ?>%</span>
								<?php else : ?>
									<span style="color:#646970;">Illimité</span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

		</div>

		<style>
		.cfeb-stats-cards   { display:flex; flex-wrap:wrap; gap:16px; margin:20px 0; }
		.cfeb-stat-card     { background:#fff; border:1px solid #e0e0e0; border-radius:10px; padding:16px 20px; min-width:140px; flex:1; display:flex; flex-direction:column; align-items:center; text-align:center; box-shadow:0 1px 4px rgba(0,0,0,.05); }
		.cfeb-stat-icon     { font-size:22px; margin-bottom:6px; }
		.cfeb-stat-val      { font-size:26px; font-weight:700; color:#1a3c5e; line-height:1.1; }
		.cfeb-stat-label    { font-size:12px; color:#646970; margin-top:4px; }
		.cfeb-stat-variation{ font-size:11px; margin-top:4px; }
		.cfeb-stat-variation.up   { color:#16a34a; }
		.cfeb-stat-variation.down { color:#dc2626; }
		.cfeb-stat-variation.flat { color:#6b7280; }

		.cfeb-stats-columns { display:flex; gap:24px; flex-wrap:wrap; margin-top:24px; }
		.cfeb-stats-columns .cfeb-stats-section { flex:1; min-width:280px; }
		.cfeb-stats-section { background:#fff; border:1px solid #e0e0e0; border-radius:10px; padding:20px 24px; }
		.cfeb-stats-section h2 { margin:0 0 16px; font-size:15px; color:#1a3c5e; }

		.cfeb-bar-chart { display:flex; align-items:flex-end; gap:12px; height:140px; padding-bottom:32px; position:relative; }
		.cfeb-bar-item  { flex:1; display:flex; flex-direction:column; align-items:center; height:100%; }
		.cfeb-bar-wrap  { flex:1; width:100%; display:flex; align-items:flex-end; justify-content:center; }
		.cfeb-bar       { width:70%; background:#2271b1; border-radius:4px 4px 0 0; min-height:4px; position:relative; transition:background .2s; cursor:default; }
		.cfeb-bar:hover { background:#1a5a99; }
		.cfeb-bar-val   { position:absolute; top:-20px; left:0; right:0; text-align:center; font-size:11px; font-weight:700; color:#374151; }
		.cfeb-bar-label { font-size:11px; color:#646970; margin-top:6px; text-align:center; white-space:nowrap; }

		.cfeb-progress-bar  { width:120px; height:8px; background:#e5e7eb; border-radius:4px; display:inline-block; vertical-align:middle; margin-right:6px; }
		.cfeb-progress-fill { height:100%; border-radius:4px; }
		</style>
		<?php
	}

	/* ── Carte résumé (helper) ───────────────────────────────────── */
	private static function stat_card( $icon, $label, $value, $variation ) {
		$var_class = '';
		$var_html  = '';
		if ( $variation ) {
			preg_match( '/([+-]?\d+)%/', $variation, $m );
			$n = isset( $m[1] ) ? (int) $m[1] : 0;
			if ( $n > 0 )      { $var_class = 'up';   $var_html = '▲ ' . $variation; }
			elseif ( $n < 0 )  { $var_class = 'down'; $var_html = '▼ ' . $variation; }
			else               { $var_class = 'flat';  $var_html = '= ' . $variation; }
		}
		?>
		<div class="cfeb-stat-card">
			<div class="cfeb-stat-icon"><?php echo esc_html( $icon ); ?></div>
			<div class="cfeb-stat-val"><?php echo is_int( $value ) ? (int) $value : esc_html( $value ); ?></div>
			<div class="cfeb-stat-label"><?php echo esc_html( $label ); ?></div>
			<?php if ( $var_html ) : ?>
				<div class="cfeb-stat-variation <?php echo esc_attr( $var_class ); ?>"><?php echo esc_html( $var_html ); ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ── Données mensuelles (6 derniers mois) ────────────────────── */
	private static function get_monthly_data( $table, $nb_months = 6 ) {
		global $wpdb;
		$data = [];
		for ( $i = $nb_months - 1; $i >= 0; $i-- ) {
			$ts    = strtotime( '-' . $i . ' month', strtotime( current_time( 'Y-m' ) . '-01' ) );
			$ym    = gmdate( 'Y-m', $ts );
			$label = date_i18n( 'M', $ts );
			$count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE statut != %s AND DATE_FORMAT(cree_le, '%%Y-%%m') = %s",
				'annule',
				$ym
			) );
			$data[] = [ 'ym' => $ym, 'label' => $label, 'count' => $count ];
		}
		return $data;
	}

	/* ── Réservations mois précédent ─────────────────────────────── */
	private static function bookings_last_month( $table ) {
		global $wpdb;
		$ts         = strtotime( '-1 month', strtotime( current_time( 'Y-m' ) . '-01' ) );
		$ym         = gmdate( 'Y-m', $ts );
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE statut != %s AND DATE_FORMAT(cree_le, '%%Y-%%m') = %s",
			'annule',
			$ym
		) );
	}

	/* ── Calcul de variation ─────────────────────────────────────── */
	private static function variation( $current, $previous ) {
		if ( $previous <= 0 ) {
			return '+100%';
		}
		$pct = round( ( $current - $previous ) * 100 / $previous );
		return ( $pct >= 0 ? '+' : '' ) . $pct . '%';
	}
}
