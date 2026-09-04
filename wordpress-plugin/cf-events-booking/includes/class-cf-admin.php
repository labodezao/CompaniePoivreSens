<?php
/**
 * Interface d'administration : liste réservations, stats, paramètres, export CSV.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_Admin {

	/* ══════════════════════════════════════════════════════════════
	   NAVIGATION À ONGLETS — présente sur toutes les pages
	══════════════════════════════════════════════════════════════ */
	public static function render_tab_nav( $current_page = '' ) {
		$base      = admin_url( 'edit.php?post_type=' . CFEB_SLUG . '&page=' );
		$appt_url  = admin_url( 'edit.php?post_type=' . CFEB_APPT_SLUG );
		$appt_new  = admin_url( 'post-new.php?post_type=' . CFEB_APPT_SLUG );
		$on_appt   = ( isset( $_GET['post_type'] ) && CFEB_APPT_SLUG === $_GET['post_type'] )
			|| ( isset( $_GET['post'] ) && CFEB_APPT_SLUG === get_post_type( (int) $_GET['post'] ) );

		$tabs = [
			'cfeb-reservations'   => [ 'icon' => '📋', 'label' => 'Réservations',  'url' => $base . 'cfeb-reservations' ],
			'cfeb-appt-types'     => [ 'icon' => '📅', 'label' => 'Types RDV',     'url' => $appt_url, 'active' => $on_appt ],
			'cfeb-calendrier'     => [ 'icon' => '🗓️', 'label' => 'Calendrier',    'url' => $base . 'cfeb-calendrier' ],
			'cfeb-stats'          => [ 'icon' => '📊', 'label' => 'Statistiques',  'url' => $base . 'cfeb-stats' ],
			'cfeb-client-history' => [ 'icon' => '👤', 'label' => 'Historique',    'url' => $base . 'cfeb-client-history' ],
			'cfeb-settings'       => [ 'icon' => '⚙️', 'label' => 'Paramètres',   'url' => $base . 'cfeb-settings' ],
		];
		echo '<nav class="cfeb-tab-nav">';
		foreach ( $tabs as $page => $info ) {
			$active = ( ! empty( $info['active'] ) || $current_page === $page ) ? ' cfeb-tab-active' : '';
			printf(
				'<a href="%s" class="cfeb-tab%s">%s %s</a>',
				esc_url( $info['url'] ),
				esc_attr( $active ),
				$info['icon'],
				esc_html( $info['label'] )
			);
		}
		echo '</nav>';
	}

	/* ── Onglets sur les écrans natifs des types de RDV ──────────────
	   La liste (edit.php?post_type=cf_appt_type) et l'éditeur sont des
	   écrans WordPress natifs : ils n'appellent pas render_tab_nav().
	   On l'injecte ici pour garder la navigation visible. */
	public static function maybe_render_cpt_tab_nav() {
		if ( ! function_exists( 'get_current_screen' ) ) return;
		$screen = get_current_screen();
		if ( $screen && CFEB_APPT_SLUG === $screen->post_type ) {
			self::render_tab_nav( 'cfeb-appt-types' );
		}
	}

	/* ── Sous-menus natifs de cf_event ───────────────────────────────
	   Ils étaient retirés ici : dans la conception d'origine, un
	   « événement » n'était qu'un créneau de réservation, engendré
	   uniquement par le générateur de planning et administré depuis les
	   écrans du plugin.

	   Sur ce site, un événement est une vraie page publiée (spectacle,
	   jam, atelier) : on doit pouvoir en créer un à la date voulue et
	   rouvrir n'importe lequel pour en corriger la date ou le lieu.
	   « Tous les événements » et « Ajouter » restent donc en place. */

	/* ══════════════════════════════════════════════════════════════
	   ENREGISTREMENT AJAX DÉTAIL RÉSERVATION
	══════════════════════════════════════════════════════════════ */
	public static function ajax_booking_detail() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( null, 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cfeb_admin' ) ) {
			wp_send_json_error( null, 403 );
		}
		$id = absint( $_POST['id'] ?? 0 );
		$b  = CF_Booking::get( $id );
		if ( ! $b ) {
			wp_send_json_error( null, 404 );
		}

		$event      = $b->event_id ? get_post( $b->event_id ) : null;
		$type_post  = ( ! $event && $b->appt_type_id ) ? get_post( $b->appt_type_id ) : null;
		$meta       = $event ? CF_CPT::get_meta( $b->event_id ) : [];
		$appt_meta  = ( $type_post && class_exists( 'CF_ApptType' ) ) ? CF_ApptType::get_meta( $b->appt_type_id ) : [];
		$ev_title   = $event ? $event->post_title : ( $type_post ? $type_post->post_title : '—' );
		$ev_date    = $event
			? ( ! empty( $meta['date_debut'] ) ? date_i18n( 'l j F Y \xc0 H\hi', strtotime( $meta['date_debut'] ) ) : '—' )
			: ( ! empty( $b->slot_debut ) ? date_i18n( 'l j F Y \xc0 H\hi', strtotime( $b->slot_debut ) ) : '—' );
		$lieu       = $event
			? ( ! empty( $meta['lieu'] ) ? $meta['lieu'] : ( ! empty( $meta['lien_visio'] ) ? 'En ligne' : '—' ) )
			: ( ! empty( $appt_meta['lieu'] ) ? $appt_meta['lieu'] : '—' );

		$champs_perso = [];
		if ( ! empty( $b->champs_perso ) ) {
			$decoded = json_decode( $b->champs_perso, true );
			if ( is_array( $decoded ) ) {
				$champs_perso = $decoded;
			}
		}

		$html = '<div class="cfeb-detail-panel">';
		$html .= '<div class="cfeb-detail-grid">';

		$html .= '<div class="cfeb-detail-section">';
		$html .= '<h4>Participant</h4>';
		$html .= '<table class="cfeb-detail-table">';
		$html .= '<tr><td>Nom</td><td><strong>' . esc_html( $b->prenom . ' ' . $b->nom ) . '</strong></td></tr>';
		$html .= '<tr><td>Email</td><td><a href="mailto:' . esc_attr( $b->email ) . '">' . esc_html( $b->email ) . '</a></td></tr>';
		$html .= '<tr><td>Téléphone</td><td>' . esc_html( $b->telephone ?: '—' ) . '</td></tr>';
		$html .= '<tr><td>Places</td><td>' . (int) $b->nb_places . '</td></tr>';
		$html .= '<tr><td>Statut</td><td>' . esc_html( $b->statut ) . '</td></tr>';
		if ( property_exists( $b, 'paye' ) ) {
			$html .= '<tr><td>Paiement</td><td>' . ( $b->paye ? '✅ Payé' : '☐ Non payé' );
			if ( ! empty( $b->mode_paiement ) ) {
				$html .= ' <em style="color:#6b7280;">(' . esc_html( $b->mode_paiement ) . ')</em>';
			}
			$html .= '</td></tr>';
		}
		$html .= '<tr><td>Réservé le</td><td>' . esc_html( date_i18n( 'd/m/Y H:i', strtotime( $b->cree_le ) ) ) . '</td></tr>';
		$html .= '</table>';
		$html .= '</div>';

		$html .= '<div class="cfeb-detail-section">';
		$html .= '<h4>' . ( $type_post ? 'Type de RDV' : 'Événement' ) . '</h4>';
		$html .= '<table class="cfeb-detail-table">';
		$html .= '<tr><td>Titre</td><td>' . esc_html( $ev_title ) . '</td></tr>';
		$html .= '<tr><td>Date</td><td>' . esc_html( $ev_date ) . '</td></tr>';
		$html .= '<tr><td>Lieu</td><td>' . esc_html( $lieu ) . '</td></tr>';
		if ( $type_post && ! empty( $b->slot_fin ) ) {
			$html .= '<tr><td>Fin</td><td>' . esc_html( date_i18n( 'H\hi', strtotime( $b->slot_fin ) ) ) . '</td></tr>';
		}
		$html .= '</table>';

		if ( $b->notes ) {
			$html .= '<h4 style="margin-top:14px;">Notes</h4>';
			$html .= '<div class="cfeb-detail-notes">' . esc_html( $b->notes ) . '</div>';
		}
		if ( ! empty( $b->motif_annulation ) ) {
			$html .= '<h4 style="margin-top:14px;color:#b91c1c;">Motif d\'annulation</h4>';
			$html .= '<div class="cfeb-detail-notes cfeb-detail-notes--danger">' . esc_html( $b->motif_annulation ) . '</div>';
		}
		if ( $champs_perso ) {
			$html .= '<h4 style="margin-top:14px;">Champs personnalisés</h4>';
			$html .= '<table class="cfeb-detail-table">';
			foreach ( $champs_perso as $k => $v ) {
				$html .= '<tr><td>' . esc_html( $k ) . '</td><td>' . esc_html( is_array( $v ) ? implode( ', ', $v ) : $v ) . '</td></tr>';
			}
			$html .= '</table>';
		}
		$html .= '</div>';

		$html .= '</div>';

		$resched_url = add_query_arg( [
			'post_type' => CFEB_SLUG,
			'page'      => 'cfeb-reservations',
			'cfeb_add'  => '1',
			'cfeb_dup'  => $id,
		], admin_url( 'edit.php' ) );
		$history_url = add_query_arg( [
			'post_type' => CFEB_SLUG,
			'page'      => 'cfeb-client-history',
			'email'     => rawurlencode( $b->email ),
		], admin_url( 'edit.php' ) );

		$html .= '<div class="cfeb-detail-actions">';
		$html .= '<a href="' . esc_url( $resched_url ) . '" class="button">📋 Réinscrire</a> ';
		$html .= '<a href="' . esc_url( $history_url ) . '" class="button">👤 Historique</a> ';
		$html .= '<a href="mailto:' . esc_attr( $b->email ) . '" class="button">✉️ Email</a>';
		$html .= '</div>';

		$html .= '</div>';

		wp_send_json_success( [ 'html' => $html ] );
	}

	public static function init() {
		add_submenu_page(
			'edit.php?post_type=' . CFEB_SLUG,
			'Réservations',
			'Réservations',
			'edit_posts',
			'cfeb-reservations',
			[ __CLASS__, 'page_reservations' ]
		);
		add_submenu_page(
			'edit.php?post_type=' . CFEB_SLUG,
			'Calendrier',
			'Calendrier',
			'edit_posts',
			'cfeb-calendrier',
			[ __CLASS__, 'page_calendar' ]
		);
		add_submenu_page(
			'edit.php?post_type=' . CFEB_SLUG,
			'Paramètres',
			'Paramètres',
			'manage_options',
			'cfeb-settings',
			[ __CLASS__, 'page_settings' ]
		);

		add_submenu_page(
			'edit.php?post_type=' . CFEB_SLUG,
			'Historique client',
			'👤 Historique',
			'edit_posts',
			'cfeb-client-history',
			[ __CLASS__, 'page_client_history' ]
		);

		// Traitement export CSV
		add_action( 'admin_init', [ __CLASS__, 'handle_export' ] );
		// Sauvegarde paramètres
		add_action( 'admin_init', [ __CLASS__, 'save_settings' ] );
		// Actions inline et en masse sur réservations
		add_action( 'admin_init', [ __CLASS__, 'handle_booking_action' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_bulk_action' ] );
		// Ajout manuel de réservation
		add_action( 'admin_init', [ __CLASS__, 'handle_add_booking' ] );

		// AJAX mise à jour paiement
		add_action( 'wp_ajax_cfeb_payment_update',   [ __CLASS__, 'ajax_payment_update' ] );
		// AJAX détail réservation
		add_action( 'wp_ajax_cfeb_booking_detail',   [ __CLASS__, 'ajax_booking_detail' ] );
	}

	/* ══════════════════════════════════════════════════════════════
	   WIDGET TABLEAU DE BORD
	══════════════════════════════════════════════════════════════ */
	public static function register_dashboard_widget() {
		wp_add_dashboard_widget(
			'cfeb_dashboard_widget',
			'📅 CF Événements — Vue d\'ensemble',
			[ __CLASS__, 'render_dashboard_widget' ]
		);
	}

	public static function render_dashboard_widget() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . CFEB_TABLE;
		$now   = current_time( 'mysql' );

		$upcoming = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_cfeb_date_debut'
			 WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_value >= %s",
			CFEB_SLUG,
			$now
		) );

		$today_start = current_time( 'Y-m-d' ) . ' 00:00:00';
		$today_end   = current_time( 'Y-m-d' ) . ' 23:59:59';
		$today_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_cfeb_date_debut'
			 WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_value BETWEEN %s AND %s",
			CFEB_SLUG,
			$today_start,
			$today_end
		) );

		$week_start  = gmdate( 'Y-m-d', strtotime( 'monday this week' ) ) . ' 00:00:00';
		$week_res    = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE statut != %s AND cree_le >= %s",
			'annule',
			$week_start
		) );

		$total_confirme = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE statut = %s",
			'confirme'
		) );

		$next_events = get_posts( [
			'post_type'      => CFEB_SLUG,
			'post_status'    => 'publish',
			'posts_per_page' => 4,
			'meta_key'       => '_cfeb_date_debut',
			'meta_value'     => $now,
			'meta_compare'   => '>=',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
		] );
		?>
		<div class="cfeb-widget">
			<div class="cfeb-widget-stats">
				<div class="cfeb-widget-stat">
					<span class="cfeb-widget-num"><?php echo (int) $upcoming; ?></span>
					<span class="cfeb-widget-label">Événements à venir</span>
				</div>
				<div class="cfeb-widget-stat">
					<span class="cfeb-widget-num"><?php echo (int) $today_count; ?></span>
					<span class="cfeb-widget-label">Aujourd'hui</span>
				</div>
				<div class="cfeb-widget-stat">
					<span class="cfeb-widget-num"><?php echo (int) $week_res; ?></span>
					<span class="cfeb-widget-label">Réservations cette semaine</span>
				</div>
				<div class="cfeb-widget-stat">
					<span class="cfeb-widget-num"><?php echo (int) $total_confirme; ?></span>
					<span class="cfeb-widget-label">Confirmées (total)</span>
				</div>
			</div>

			<?php if ( $next_events ) : ?>
				<h4 style="margin:12px 0 6px;color:#1a3c5e;font-size:13px;">Prochains événements</h4>
				<ul style="margin:0;padding:0;list-style:none;">
					<?php foreach ( $next_events as $ev ) :
						$date_debut = get_post_meta( $ev->ID, '_cfeb_date_debut', true );
						$nb         = CF_Booking::count_for_event( $ev->ID, 'confirme' );
					?>
					<li style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f0f0f0;">
						<a href="<?php echo esc_url( get_edit_post_link( $ev->ID ) ); ?>" style="color:#1a3c5e;font-weight:500;font-size:13px;text-decoration:none;">
							<?php echo esc_html( $ev->post_title ); ?>
						</a>
						<span style="font-size:12px;color:#646970;white-space:nowrap;margin-left:8px;">
							<?php echo $date_debut ? esc_html( date_i18n( 'd/m/Y', strtotime( $date_debut ) ) ) : ''; ?>
							<?php if ( $nb > 0 ) : ?><span style="background:#22c55e;color:#fff;border-radius:10px;padding:1px 7px;margin-left:4px;font-size:11px;"><?php echo (int) $nb; ?></span><?php endif; ?>
						</span>
					</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<p style="margin:12px 0 0;display:flex;gap:8px;flex-wrap:wrap;">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CFEB_SLUG . '&page=cfeb-reservations' ) ); ?>" class="button button-primary">Réservations</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CFEB_SLUG . '&page=cfeb-stats' ) ); ?>" class="button">Statistiques</a>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . CFEB_SLUG ) ); ?>" class="button">+ Événement</a>
			</p>
		</div>
		<style>
		.cfeb-widget-stats { display:flex; gap:10px; margin:0 0 10px; }
		.cfeb-widget-stat  { flex:1; text-align:center; background:#f9fafb; border-radius:8px; padding:10px 6px; }
		.cfeb-widget-num   { display:block; font-size:22px; font-weight:700; color:#1a3c5e; line-height:1.1; }
		.cfeb-widget-label { display:block; font-size:10px; color:#646970; margin-top:2px; line-height:1.3; }
		</style>
		<?php
	}

	/* ══════════════════════════════════════════════════════════════
	   PAGE CALENDRIER ADMIN
	══════════════════════════════════════════════════════════════ */
	public static function page_calendar() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Accès refusé.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . CFEB_TABLE;

		$week_param = isset( $_GET['semaine'] ) ? sanitize_text_field( wp_unslash( $_GET['semaine'] ) ) : '';
		$week_base  = ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $week_param ) && strtotime( $week_param ) )
			? strtotime( $week_param )
			: current_time( 'timestamp' );
		$week_start_ts = strtotime( 'monday this week', $week_base );
		$week_end_ts   = strtotime( '+6 days', $week_start_ts );
		$week_prev     = gmdate( 'Y-m-d', strtotime( '-7 days', $week_start_ts ) );
		$week_next     = gmdate( 'Y-m-d', strtotime( '+7 days', $week_start_ts ) );
		$week_cur      = gmdate( 'Y-m-d', strtotime( 'monday this week', current_time( 'timestamp' ) ) );
		$week_start_dt = gmdate( 'Y-m-d', $week_start_ts ) . ' 00:00:00';
		$week_end_dt   = gmdate( 'Y-m-d', $week_end_ts ) . ' 23:59:59';

		$bookings = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, event_id, appt_type_id, slot_debut, slot_fin, prenom, nom, email, statut
			 FROM {$table}
			 WHERE statut != %s
			   AND (
			     (slot_debut IS NOT NULL AND slot_debut != '' AND slot_debut BETWEEN %s AND %s)
			     OR ((slot_debut IS NULL OR slot_debut = '') AND event_id > 0)
			   )
			 ORDER BY slot_debut ASC, cree_le ASC",
			'annule',
			$week_start_dt,
			$week_end_dt
		) );

		$by_day = [];
		foreach ( (array) $bookings as $b ) {
			$display_ts = ! empty( $b->slot_debut ) ? strtotime( $b->slot_debut ) : 0;
			if ( ! $display_ts && ! empty( $b->event_id ) ) {
				$event_start = get_post_meta( (int) $b->event_id, '_cfeb_date_debut', true );
				if ( $event_start ) {
					$display_ts = strtotime( $event_start );
				}
			}
			if ( ! $display_ts || $display_ts < $week_start_ts || $display_ts > ( $week_end_ts + DAY_IN_SECONDS - 1 ) ) {
				continue;
			}

			$day_key       = gmdate( 'Y-m-d', $display_ts );
			$client_name   = trim( (string) $b->prenom . ' ' . (string) $b->nom );
			$by_day[ $day_key ][] = [
				'id'      => (int) $b->id,
				'name'    => $client_name ?: (string) $b->email,
				'time'    => date_i18n( 'H\hi', $display_ts ),
				'status'  => sanitize_key( (string) $b->statut ),
				'tooltip' => $client_name ?: (string) $b->email,
			];
		}

		$jours_sem = [ 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim' ];
		$today_str = gmdate( 'Y-m-d' );
		?>
		<div class="wrap cfeb-admin-wrap">
			<div class="cfeb-page-header">
				<div class="cfeb-page-header-left"><h1>CF Événements &amp; Réservations</h1></div>
				<div class="cfeb-page-header-actions">
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . CFEB_SLUG ) ); ?>" class="cfeb-btn cfeb-btn-primary">➕ Ajouter</a>
				</div>
			</div>
			<?php self::render_tab_nav( 'cfeb-calendrier' ); ?>

			<div class="cfeb-admin-cal-nav" style="display:flex;align-items:center;gap:10px;margin:16px 0;flex-wrap:wrap;">
				<a href="<?php echo esc_url( add_query_arg( [ 'post_type' => CFEB_SLUG, 'page' => 'cfeb-calendrier', 'semaine' => $week_prev ], admin_url( 'edit.php' ) ) ); ?>" class="button">◀ Semaine préc.</a>
				<strong style="font-size:18px;"><?php echo esc_html( date_i18n( 'j F', $week_start_ts ) . ' → ' . date_i18n( 'j F Y', $week_end_ts ) ); ?></strong>
				<a href="<?php echo esc_url( add_query_arg( [ 'post_type' => CFEB_SLUG, 'page' => 'cfeb-calendrier', 'semaine' => $week_next ], admin_url( 'edit.php' ) ) ); ?>" class="button">Semaine suiv. ▶</a>
				<a href="<?php echo esc_url( add_query_arg( [ 'post_type' => CFEB_SLUG, 'page' => 'cfeb-calendrier', 'semaine' => $week_cur ], admin_url( 'edit.php' ) ) ); ?>" class="button button-primary">Cette semaine</a>
			</div>

			<style>
			.cfeb-admin-week-wrap { display:grid; grid-template-columns: minmax(720px,1fr) 320px; gap:16px; align-items:start; }
			.cfeb-admin-week-grid { display:grid; grid-template-columns:repeat(7,minmax(140px,1fr)); border-left:1px solid #ddd; border-top:1px solid #ddd; overflow-x:auto; }
			.cfeb-admin-week-day { border-right:1px solid #ddd; border-bottom:1px solid #ddd; min-height:280px; background:#fff; display:flex; flex-direction:column; }
			.cfeb-admin-week-day.is-today { background:#eff6ff; }
			.cfeb-admin-week-head { border-bottom:1px solid #e5e7eb; padding:8px; text-align:center; }
			.cfeb-admin-week-name { display:block; font-weight:600; font-size:13px; }
			.cfeb-admin-week-date { display:block; color:#6b7280; font-size:12px; }
			.cfeb-admin-week-list { padding:8px; display:flex; flex-direction:column; gap:6px; }
			.cfeb-admin-week-empty { color:#9ca3af; font-size:12px; }
			.cfeb-week-booking { text-align:left; border:1px solid #dbeafe; background:#f8fbff; border-radius:6px; padding:7px; cursor:pointer; }
			.cfeb-week-booking:hover { border-color:#93c5fd; background:#eff6ff; }
			.cfeb-week-booking.is-active { border-color:#2563eb; box-shadow:0 0 0 2px rgba(37,99,235,.18); }
			.cfeb-week-booking-time { display:block; font-weight:600; font-size:12px; color:#1d4ed8; margin-bottom:2px; }
			.cfeb-week-booking-name { display:block; font-size:12px; color:#111827; }
			.cfeb-week-booking.cfeb-status-liste_attente { border-color:#fde68a; background:#fff8e1; }
			.cfeb-week-booking.cfeb-status-present { border-color:#86efac; background:#f0fdf4; }
			.cfeb-week-booking.cfeb-status-absent { border-color:#fecaca; background:#fff1f2; }
			.cfeb-week-detail { border:1px solid #ddd; border-radius:8px; background:#fff; min-height:280px; }
			.cfeb-week-detail h3 { margin:0; padding:12px; border-bottom:1px solid #eee; font-size:14px; color:#1d2327; }
			.cfeb-week-detail-body { padding:12px; color:#4b5563; font-size:13px; }
			@media (max-width: 1400px) {
				.cfeb-admin-week-wrap { grid-template-columns: 1fr; }
				.cfeb-week-detail { min-height:0; }
			}
			</style>

			<div class="cfeb-admin-week-wrap">
				<div class="cfeb-admin-week-grid">
					<?php for ( $i = 0; $i < 7; $i++ ) : ?>
						<?php
						$day_ts   = strtotime( "+{$i} days", $week_start_ts );
						$day_key  = gmdate( 'Y-m-d', $day_ts );
						$items    = $by_day[ $day_key ] ?? [];
						$is_today = ( $day_key === $today_str );
						?>
						<div class="cfeb-admin-week-day<?php echo $is_today ? ' is-today' : ''; ?>">
							<div class="cfeb-admin-week-head">
								<span class="cfeb-admin-week-name"><?php echo esc_html( $jours_sem[ $i ] ); ?></span>
								<span class="cfeb-admin-week-date"><?php echo esc_html( date_i18n( 'd/m', $day_ts ) ); ?></span>
							</div>
							<div class="cfeb-admin-week-list">
								<?php if ( empty( $items ) ) : ?>
									<span class="cfeb-admin-week-empty">Aucune réservation</span>
								<?php else : ?>
									<?php foreach ( $items as $item ) : ?>
										<button type="button" class="cfeb-week-booking cfeb-status-<?php echo esc_attr( $item['status'] ); ?>" data-booking-id="<?php echo (int) $item['id']; ?>" title="<?php echo esc_attr( $item['tooltip'] ); ?>">
											<span class="cfeb-week-booking-time"><?php echo esc_html( $item['time'] ); ?></span>
											<span class="cfeb-week-booking-name"><?php echo esc_html( $item['name'] ); ?></span>
										</button>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
					<?php endfor; ?>
				</div>
				<div class="cfeb-week-detail">
					<h3>Détail réservation</h3>
					<div class="cfeb-week-detail-body">Cliquez sur une réservation pour afficher le détail.</div>
				</div>
			</div>
		</div>
		<?php
	}

	/* ── CSS / JS admin ─────────────────────────────────────────── */
	public static function enqueue( $hook ) {
		// Toutes les pages du plugin (cfeb-*) et du module Post-Séance (cfps-*)
		$is_plugin_page = ( false !== strpos( $hook, 'cfeb-' ) || false !== strpos( $hook, 'cfps-' ) );
		// Liste native des types de RDV (edit.php?post_type=cf_appt_type)
		$is_appt_list = ( 'edit.php' === $hook && CFEB_APPT_SLUG === ( $_GET['post_type'] ?? '' ) );
		if ( ! $is_plugin_page && ! $is_appt_list && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		wp_enqueue_style(  'cfeb-admin', CFEB_URL . 'assets/css/admin.css',  [], CFEB_VERSION );
		wp_enqueue_script( 'cfeb-admin', CFEB_URL . 'assets/js/admin.js', [], CFEB_VERSION, true );
		wp_localize_script( 'cfeb-admin', 'cfebAdmin', [
			'nonce'   => wp_create_nonce( 'cfeb_admin' ),
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'l10n'    => [
				'confirm_delete'      => 'Supprimer cette réservation ?',
				'confirm_bulk_delete' => 'Supprimer les réservations sélectionnées ?',
				'select_action'       => 'Veuillez choisir une action.',
				'select_items'        => 'Veuillez sélectionner au moins une réservation.',
				'motif_annulation'    => "Motif d'annulation (optionnel) :",
				'mode_paiement'       => "Mode de paiement :\n0 - Non renseigné\n1 - Virement\n2 - Chèque\n3 - Espèces\n4 - CB en ligne\n5 - CB sur place\n6 - Autre",
			],
		] );
	}

	/* ══════════════════════════════════════════════════════════════
	   PAGE RÉSERVATIONS
	══════════════════════════════════════════════════════════════ */
	public static function page_reservations() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Accès refusé.' );
		}

		$event_id      = isset( $_GET['event_id'] )  ? absint( $_GET['event_id'] )                                 : 0;
		$statut_filter = isset( $_GET['statut'] )    ? sanitize_key( $_GET['statut'] )                              : '';
		$search        = isset( $_GET['s'] )         ? sanitize_text_field( wp_unslash( $_GET['s'] ) )              : '';
		$date_from     = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) )      : '';
		$date_to       = isset( $_GET['date_to'] )   ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) )        : '';
		$page_num      = isset( $_GET['paged'] )     ? max( 1, absint( $_GET['paged'] ) )                           : 1;
		$per_page      = 20;
		$show_form     = isset( $_GET['cfeb_add'] );

		$query_args = [
			'event_id'  => $event_id,
			'statut'    => $statut_filter,
			'search'    => $search,
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'per_page'  => $per_page,
			'page'      => $page_num,
		];

		$bookings = CF_Booking::get_all( $query_args );
		$total    = CF_Booking::count_all( [ 'event_id' => $event_id, 'statut' => $statut_filter, 'search' => $search, 'date_from' => $date_from, 'date_to' => $date_to ] );
		$pages    = ceil( $total / $per_page );

		// Liste des événements pour le filtre et le formulaire d'ajout
		$events = get_posts( [
			'post_type'      => CFEB_SLUG,
			'posts_per_page' => 200,
			'post_status'    => 'publish',
			'orderby'        => 'meta_value',
			'meta_key'       => '_cfeb_date_debut',
			'order'          => 'DESC',
		] );

		$statuts = [
			''              => 'Tous les statuts',
			'confirme'      => '✅ Confirmé',
			'annule'        => '❌ Annulé',
			'liste_attente' => '⏳ Liste d\'attente',
			'present'       => '✔️ Présent',
			'absent'        => '🚫 Absent',
		];

		$statuts_form = array_filter(
			$statuts,
			static function ( $k ) { return '' !== $k; },
			ARRAY_FILTER_USE_KEY
		);

		$export_url = add_query_arg( [
			'action'    => 'cfeb_export_csv',
			'event_id'  => $event_id,
			'statut'    => $statut_filter,
			's'         => $search,
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'_wpnonce'  => wp_create_nonce( 'cfeb_export' ),
		], admin_url( 'admin-ajax.php' ) );

		$add_url = add_query_arg( [ 'post_type' => CFEB_SLUG, 'page' => 'cfeb-reservations', 'cfeb_add' => '1' ], admin_url( 'edit.php' ) );
		?>
		<div class="wrap cfeb-admin-wrap">
			<div class="cfeb-page-header">
				<div class="cfeb-page-header-left">
					<h1>CF Événements &amp; Réservations</h1>
				</div>
				<div class="cfeb-page-header-actions">
					<a href="<?php echo esc_url( $add_url ); ?>" class="cfeb-btn cfeb-btn-primary">➕ Ajouter</a>
					<a href="<?php echo esc_url( $export_url ); ?>" class="cfeb-btn">⬇️ CSV</a>
				</div>
			</div>

			<?php self::render_tab_nav( 'cfeb-reservations' ); ?>

			<?php self::show_notice(); ?>

			<?php if ( $show_form ) :
				$dup_id       = isset( $_GET['cfeb_dup'] ) ? absint( $_GET['cfeb_dup'] ) : 0;
				$dup          = $dup_id ? CF_Booking::get( $dup_id ) : null;
				$pf_prenom    = $dup ? $dup->prenom    : '';
				$pf_nom       = $dup ? $dup->nom       : '';
				$pf_email     = $dup ? $dup->email     : '';
				$pf_telephone = $dup ? $dup->telephone : '';
				$pf_places    = $dup ? $dup->nb_places : 1;
				$pf_notes     = $dup ? $dup->notes     : '';
			?>
			<!-- ══ Formulaire ajout manuel ═════════════════════════ -->
			<div class="cfeb-add-booking-form">
				<h2><?php echo $dup ? '📋 Réinscrire — ' . esc_html( $pf_prenom . ' ' . $pf_nom ) : 'Ajouter une réservation manuellement'; ?></h2>
				<?php if ( $dup ) : ?>
					<p style="color:#646970;font-size:13px;">Données pré-remplies. Choisissez le nouvel événement.</p>
				<?php endif; ?>
				<form method="post" action="">
					<?php wp_nonce_field( 'cfeb_add_booking', 'cfeb_add_booking_nonce' ); ?>
					<input type="hidden" name="cfeb_add_booking" value="1" />
					<div class="cfeb-form-grid">
						<div class="cfeb-form-field">
							<label for="cfeb_f_event_id">Événement <span class="required">*</span></label>
							<select id="cfeb_f_event_id" name="cfeb_f_event_id" required>
								<option value="">— Choisir un événement —</option>
								<?php foreach ( $events as $ev ) :
									$meta_d = get_post_meta( $ev->ID, '_cfeb_date_debut', true );
									$label  = $ev->post_title . ( $meta_d ? ' (' . date_i18n( 'd/m/Y', strtotime( $meta_d ) ) . ')' : '' );
								?>
									<option value="<?php echo esc_attr( $ev->ID ); ?>" <?php selected( $event_id, $ev->ID ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="cfeb-form-field">
							<label for="cfeb_f_prenom">Prénom <span class="required">*</span></label>
							<input type="text" id="cfeb_f_prenom" name="cfeb_f_prenom" value="<?php echo esc_attr( $pf_prenom ); ?>" required class="regular-text" />
						</div>
						<div class="cfeb-form-field">
							<label for="cfeb_f_nom">Nom <span class="required">*</span></label>
							<input type="text" id="cfeb_f_nom" name="cfeb_f_nom" value="<?php echo esc_attr( $pf_nom ); ?>" required class="regular-text" />
						</div>
						<div class="cfeb-form-field">
							<label for="cfeb_f_email">Email <span class="required">*</span></label>
							<input type="email" id="cfeb_f_email" name="cfeb_f_email" value="<?php echo esc_attr( $pf_email ); ?>" required class="regular-text" />
						</div>
						<div class="cfeb-form-field">
							<label for="cfeb_f_telephone">Téléphone</label>
							<input type="tel" id="cfeb_f_telephone" name="cfeb_f_telephone" value="<?php echo esc_attr( $pf_telephone ); ?>" class="regular-text" />
						</div>
						<div class="cfeb-form-field">
							<label for="cfeb_f_nb_places">Nombre de places</label>
							<input type="number" id="cfeb_f_nb_places" name="cfeb_f_nb_places" value="<?php echo (int) $pf_places; ?>" min="1" max="10" class="small-text" />
						</div>
						<div class="cfeb-form-field">
							<label for="cfeb_f_statut">Statut</label>
							<select id="cfeb_f_statut" name="cfeb_f_statut">
								<?php foreach ( $statuts_form as $k => $label ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $k, 'confirme' ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="cfeb-form-field">
							<label for="cfeb_f_mode_paiement">Mode de paiement</label>
							<select id="cfeb_f_mode_paiement" name="cfeb_f_mode_paiement">
								<option value="">— Non renseigné —</option>
								<option value="Virement">Virement</option>
								<option value="Chèque">Chèque</option>
								<option value="Espèces">Espèces</option>
								<option value="CB en ligne">CB en ligne</option>
								<option value="CB sur place">CB sur place</option>
								<option value="Autre">Autre</option>
							</select>
						</div>
						<div class="cfeb-form-field cfeb-form-field--full">
							<label for="cfeb_f_notes">Notes</label>
							<textarea id="cfeb_f_notes" name="cfeb_f_notes" rows="3" class="large-text"><?php echo esc_textarea( $pf_notes ); ?></textarea>
						</div>
						<div class="cfeb-form-field cfeb-form-field--full">
							<label>
								<input type="checkbox" name="cfeb_f_send_email" value="1" checked />
								Envoyer l'email de confirmation au participant
							</label>
						</div>
					</div>
					<p>
						<button type="submit" class="button button-primary">Enregistrer la réservation</button>
						<a href="<?php echo esc_url( remove_query_arg( [ 'cfeb_add', 'cfeb_dup' ] ) ); ?>" class="button">Annuler</a>
					</p>
				</form>
			</div>
			<?php endif; ?>

			<!-- ══ Filtres ════════════════════════════════════════ -->
			<form method="get" class="cfeb-filters" id="cfeb-filter-form">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( CFEB_SLUG ); ?>" />
				<input type="hidden" name="page" value="cfeb-reservations" />
				<div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:6px;">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher nom / email / tél…" style="min-width:220px;" />
					<select name="event_id">
						<option value="0">— Tous les événements —</option>
						<?php foreach ( $events as $ev ) : ?>
							<option value="<?php echo esc_attr( $ev->ID ); ?>" <?php selected( $event_id, $ev->ID ); ?>>
								<?php echo esc_html( $ev->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<select name="statut">
						<?php foreach ( $statuts as $k => $label ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $statut_filter, $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<label style="font-size:13px;color:#646970;white-space:nowrap;">Période :
						<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" style="width:140px;" title="Événement à partir du" />
						→
						<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" style="width:140px;" title="Événement jusqu'au" />
					</label>
					<button type="submit" class="button button-primary">Filtrer</button>
					<?php if ( $search || $date_from || $date_to || $event_id || $statut_filter ) : ?>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CFEB_SLUG . '&page=cfeb-reservations' ) ); ?>" class="button">Réinitialiser</a>
					<?php endif; ?>
					<span style="margin-left:auto;color:#646970;font-size:13px;"><?php echo (int) $total; ?> réservation(s)</span>
				</div>
			</form>

			<!-- ══ Formulaire tableau + actions en masse ══════════ -->
			<form method="post" id="cfeb-bulk-form">
				<?php wp_nonce_field( 'cfeb_bulk_action', 'cfeb_bulk_nonce' ); ?>
				<input type="hidden" name="cfeb_event_id_filter" value="<?php echo esc_attr( $event_id ); ?>" />
				<input type="hidden" name="cfeb_statut_filter"   value="<?php echo esc_attr( $statut_filter ); ?>" />
				<input type="hidden" name="cfeb_paged_filter"    value="<?php echo esc_attr( $page_num ); ?>" />

				<!-- Actions en masse (haut) -->
				<div class="tablenav top cfeb-bulk-bar">
					<div class="alignleft actions bulkactions">
						<select name="cfeb_bulk_action" id="cfeb-bulk-action">
							<option value="">— Actions groupées —</option>
							<option value="confirme">✅ Marquer Confirmé</option>
							<option value="present">✔️ Marquer Présent</option>
							<option value="absent">🚫 Marquer Absent</option>
							<option value="annule">❌ Marquer Annulé</option>
							<option value="liste_attente">⏳ Liste d'attente</option>
							<option value="delete">🗑️ Supprimer</option>
						</select>
						<button type="submit" id="cfeb-bulk-apply" class="button action">Appliquer</button>
					</div>
				</div>

				<!-- Tableau -->
				<table class="wp-list-table widefat fixed striped cfeb-table">
					<thead>
						<tr>
							<th style="width:28px"><input type="checkbox" id="cfeb-cb-all" /></th>
							<th style="width:14%">Nom / Prénom</th>
							<th style="width:16%">Email</th>
							<th style="width:8%">Tél.</th>
							<th style="width:14%">Événement</th>
							<th style="width:4%">Pl.</th>
							<th style="width:14%">Statut</th>
							<th style="width:7%">Payé</th>
							<th style="width:10%">Date résa</th>
							<th>Notes / Motif</th>
							<th style="width:70px">Actions</th>
						</tr>
					</thead>
					<tbody>
					<?php if ( $bookings ) : ?>
						<?php foreach ( $bookings as $b ) :
							$motif   = property_exists( $b, 'motif_annulation' ) ? $b->motif_annulation : '';
							$paye    = property_exists( $b, 'paye' )             ? (bool) $b->paye      : false;
							$mode    = property_exists( $b, 'mode_paiement' )    ? $b->mode_paiement    : '';
							$history_url  = admin_url( 'edit.php?post_type=' . CFEB_SLUG . '&page=cfeb-client-history&email=' . rawurlencode( $b->email ) );
							$dup_url = add_query_arg( [
								'post_type'  => CFEB_SLUG,
								'page'       => 'cfeb-reservations',
								'cfeb_add'   => '1',
								'cfeb_dup'   => $b->id,
							], admin_url( 'edit.php' ) );
						?>
						<tr id="cfeb-row-<?php echo (int) $b->id; ?>" class="cfeb-booking-row" data-id="<?php echo (int) $b->id; ?>">
							<td class="cfeb-cb-cell" onclick="event.stopPropagation()"><input type="checkbox" class="cfeb-cb" name="cfeb_bulk_ids[]" value="<?php echo (int) $b->id; ?>" /></td>
							<td>
								<strong class="cfeb-row-name"><?php echo esc_html( $b->prenom . ' ' . $b->nom ); ?></strong>
								<div class="row-actions" style="margin-top:2px;">
									<span><a href="<?php echo esc_url( $history_url ); ?>" onclick="event.stopPropagation()">👤 Historique</a></span> |
									<span><a href="<?php echo esc_url( $dup_url ); ?>" onclick="event.stopPropagation()">📋 Réinscrire</a></span>
								</div>
							</td>
							<td data-label="Email"><a href="mailto:<?php echo esc_attr( $b->email ); ?>" style="word-break:break-all;font-size:12px;"><?php echo esc_html( $b->email ); ?></a></td>
							<td data-label="Tél." style="font-size:12px;"><?php echo esc_html( $b->telephone ?: '—' ); ?></td>
							<td data-label="Événement">
								<?php if ( $b->event_id ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $b->event_id ) ); ?>" style="font-size:12px;"><?php echo esc_html( $b->event_title ?: '#' . $b->event_id ); ?></a>
								<?php elseif ( $b->appt_type_id ) : ?>
									<span style="font-size:12px;"><?php echo esc_html( get_the_title( $b->appt_type_id ) ); ?></span>
									<?php if ( $b->slot_debut ) : ?>
										<br><small style="color:#646970;"><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $b->slot_debut ) ) ); ?></small>
									<?php endif; ?>
								<?php else : ?>—<?php endif; ?>
							</td>
							<td data-label="Places"><?php echo (int) $b->nb_places; ?></td>
							<td data-label="Statut">
								<select class="cfeb-statut-select" data-id="<?php echo (int) $b->id; ?>">
									<?php foreach ( $statuts as $k => $label ) : ?>
										<?php if ( $k ) : ?>
											<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $b->statut, $k ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</td>
							<td data-label="Payé">
								<button type="button"
									class="cfeb-paye-toggle button button-small<?php echo $paye ? ' cfeb-paye-oui' : ''; ?>"
									data-id="<?php echo (int) $b->id; ?>"
									data-paye="<?php echo $paye ? '1' : '0'; ?>"
									title="<?php echo $mode ? esc_attr( $mode ) : ''; ?>"
									style="min-width:44px;">
									<?php echo $paye ? '✅' : '☐'; ?>
								</button>
							</td>
							<td data-label="Réservé le" style="font-size:12px;"><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $b->cree_le ) ) ); ?></td>
							<td data-label="Notes" style="font-size:12px;color:#555;max-width:160px;">
								<?php if ( $b->notes ) : ?>
									<span title="<?php echo esc_attr( $b->notes ); ?>" style="cursor:help;">📝 <?php echo esc_html( mb_strimwidth( $b->notes, 0, 50, '…' ) ); ?></span>
								<?php endif; ?>
								<?php if ( $motif ) : ?>
									<span style="color:#b91c1c;" title="Motif : <?php echo esc_attr( $motif ); ?>">🚫 <?php echo esc_html( mb_strimwidth( $motif, 0, 50, '…' ) ); ?></span>
								<?php endif; ?>
								<?php if ( ! $b->notes && ! $motif ) : echo '—'; endif; ?>
							</td>
							<td style="white-space:nowrap;" onclick="event.stopPropagation()">
								<a href="<?php echo esc_url( self::action_url( 'delete', $b->id ) ); ?>" class="cfeb-delete" title="Supprimer" data-confirm="confirm_delete">🗑️</a>
							</td>
						</tr>
						<tr id="cfeb-detail-<?php echo (int) $b->id; ?>" class="cfeb-detail-row" style="display:none;">
							<td colspan="11" style="padding:0;">
								<div class="cfeb-detail-loading" style="padding:20px;text-align:center;color:#646970;">Chargement…</div>
							</td>
						</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="11" style="text-align:center;padding:20px;">Aucune réservation trouvée.</td></tr>
					<?php endif; ?>
					</tbody>
				</table>

				<!-- Actions en masse (bas) + pagination -->
				<div class="tablenav bottom cfeb-bulk-bar">
					<div class="alignleft actions bulkactions">
						<select name="cfeb_bulk_action2">
							<option value="">— Actions groupées —</option>
							<option value="confirme">✅ Marquer Confirmé</option>
							<option value="present">✔️ Marquer Présent</option>
							<option value="absent">🚫 Marquer Absent</option>
							<option value="annule">❌ Marquer Annulé</option>
							<option value="liste_attente">⏳ Liste d'attente</option>
							<option value="delete">🗑️ Supprimer</option>
						</select>
						<button type="submit" id="cfeb-bulk-apply2" class="button action">Appliquer</button>
					</div>
					<?php if ( $pages > 1 ) : ?>
						<div class="tablenav-pages">
							<?php
							echo paginate_links( [
								'base'    => add_query_arg( 'paged', '%#%' ),
								'format'  => '',
								'current' => $page_num,
								'total'   => $pages,
							] );
							?>
						</div>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<?php
	}

	/* ══════════════════════════════════════════════════════════════
	   AJOUT MANUEL DE RÉSERVATION
	══════════════════════════════════════════════════════════════ */
	public static function handle_add_booking() {
		if ( ! isset( $_POST['cfeb_add_booking'] ) ) {
			return;
		}
		if (
			! isset( $_POST['cfeb_add_booking_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfeb_add_booking_nonce'] ) ), 'cfeb_add_booking' ) ||
			! current_user_can( 'edit_posts' )
		) {
			wp_die( 'Erreur de sécurité.' );
		}

		$event_id      = absint( $_POST['cfeb_f_event_id']       ?? 0 );
		$prenom        = sanitize_text_field( wp_unslash( $_POST['cfeb_f_prenom']       ?? '' ) );
		$nom           = sanitize_text_field( wp_unslash( $_POST['cfeb_f_nom']          ?? '' ) );
		$email         = sanitize_email( wp_unslash( $_POST['cfeb_f_email']             ?? '' ) );
		$telephone     = sanitize_text_field( wp_unslash( $_POST['cfeb_f_telephone']    ?? '' ) );
		$nb_places     = min( absint( $_POST['cfeb_f_nb_places'] ?? 1 ), 10 );
		$statut        = sanitize_key( $_POST['cfeb_f_statut']    ?? 'confirme' );
		$notes         = sanitize_textarea_field( wp_unslash( $_POST['cfeb_f_notes']    ?? '' ) );
		$mode_paiement = sanitize_text_field( wp_unslash( $_POST['cfeb_f_mode_paiement'] ?? '' ) );
		$send_email    = ! empty( $_POST['cfeb_f_send_email'] );

		if ( ! $event_id || ! $prenom || ! $nom || ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( [
				'post_type' => CFEB_SLUG,
				'page'      => 'cfeb-reservations',
				'cfeb_add'  => '1',
				'cfeb_err'  => '1',
			], admin_url( 'edit.php' ) ) );
			exit;
		}

		$event = get_post( $event_id );
		if ( ! $event ) {
			wp_safe_redirect( add_query_arg( [
				'post_type' => CFEB_SLUG,
				'page'      => 'cfeb-reservations',
				'cfeb_add'  => '1',
				'cfeb_err'  => '1',
			], admin_url( 'edit.php' ) ) );
			exit;
		}

		$booking_data = [
			'event_id'      => $event_id,
			'user_id'       => 0,
			'prenom'        => $prenom,
			'nom'           => $nom,
			'email'         => $email,
			'telephone'     => $telephone,
			'nb_places'     => $nb_places,
			'statut'        => $statut,
			'notes'         => $notes,
			'mode_paiement' => $mode_paiement,
		];

		$booking = CF_Booking::add( $booking_data );
		if ( ! is_wp_error( $booking ) && $send_email && class_exists( 'CF_Email' ) ) {
			CF_Email::confirmation_user( $booking, $event );
			CF_Email::notification_admin( $booking, $event );
		}

		wp_safe_redirect( add_query_arg( [
			'post_type'  => CFEB_SLUG,
			'page'       => 'cfeb-reservations',
			'cfeb_saved' => '1',
		], admin_url( 'edit.php' ) ) );
		exit;
	}

	/* ══════════════════════════════════════════════════════════════
	   ACTIONS EN MASSE
	══════════════════════════════════════════════════════════════ */
	public static function handle_bulk_action() {
		if ( ! isset( $_POST['cfeb_bulk_nonce'] ) ) {
			return;
		}
		if (
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfeb_bulk_nonce'] ) ), 'cfeb_bulk_action' ) ||
			! current_user_can( 'edit_posts' )
		) {
			return;
		}

		// L'action peut venir du select haut ou du select bas
		$action = sanitize_key( $_POST['cfeb_bulk_action'] ?? '' );
		if ( ! $action ) {
			$action = sanitize_key( $_POST['cfeb_bulk_action2'] ?? '' );
		}
		if ( ! $action ) {
			return;
		}

		$ids = isset( $_POST['cfeb_bulk_ids'] ) ? array_map( 'absint', (array) $_POST['cfeb_bulk_ids'] ) : [];
		if ( empty( $ids ) ) {
			return;
		}

		$allowed_statuts = [ 'confirme', 'annule', 'liste_attente', 'present', 'absent' ];

		if ( 'delete' === $action ) {
			foreach ( $ids as $id ) {
				CF_Booking::delete( $id );
			}
		} elseif ( in_array( $action, $allowed_statuts, true ) ) {
			foreach ( $ids as $id ) {
				CF_Booking::update_statut( $id, $action );
				if ( 'annule' === $action && class_exists( 'CF_Ajax' ) ) {
					$b = CF_Booking::get( $id );
					if ( $b ) {
						CF_Ajax::promote_waitlist( $b->event_id );
					}
				}
			}
		}

		// Reconstruire l'URL avec les filtres actifs
		$redirect_args = [
			'post_type'  => CFEB_SLUG,
			'page'       => 'cfeb-reservations',
			'cfeb_saved' => '1',
		];
		if ( ! empty( $_POST['cfeb_event_id_filter'] ) ) {
			$redirect_args['event_id'] = absint( $_POST['cfeb_event_id_filter'] );
		}
		if ( ! empty( $_POST['cfeb_statut_filter'] ) ) {
			$redirect_args['statut'] = sanitize_key( $_POST['cfeb_statut_filter'] );
		}
		if ( ! empty( $_POST['cfeb_paged_filter'] ) ) {
			$redirect_args['paged'] = absint( $_POST['cfeb_paged_filter'] );
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php' ) ) );
		exit;
	}

	/* ══════════════════════════════════════════════════════════════
	   PAGE PARAMÈTRES
	══════════════════════════════════════════════════════════════ */
	public static function page_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		$opts = self::get_options();

		// Onglet actif
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		$tab_url = admin_url( 'edit.php?post_type=' . CFEB_SLUG . '&page=cfeb-settings' );
		?>
		<div class="wrap">
			<h1>⚙️ Paramètres — CF Événements & Réservations</h1>
			<?php self::show_notice(); ?>

			<nav class="nav-tab-wrapper" style="margin-bottom:20px;">
				<a href="<?php echo esc_url( $tab_url . '&tab=general' ); ?>" class="nav-tab<?php echo 'general' === $tab ? ' nav-tab-active' : ''; ?>">Général</a>
				<a href="<?php echo esc_url( $tab_url . '&tab=emails' ); ?>"  class="nav-tab<?php echo 'emails'  === $tab ? ' nav-tab-active' : ''; ?>">📧 Emails</a>
				<a href="<?php echo esc_url( $tab_url . '&tab=lieux' ); ?>"   class="nav-tab<?php echo 'lieux'   === $tab ? ' nav-tab-active' : ''; ?>">📍 Lieux</a>
				<a href="<?php echo esc_url( $tab_url . '&tab=gcal#gcal' ); ?>"   class="nav-tab<?php echo 'gcal'    === $tab ? ' nav-tab-active' : ''; ?>">📅 Google Agenda</a>
				<a href="<?php echo esc_url( $tab_url . '&tab=paiement' ); ?>" class="nav-tab<?php echo 'paiement' === $tab ? ' nav-tab-active' : ''; ?>">💳 Paiement</a>
			</nav>

			<form method="post">
				<?php wp_nonce_field( 'cfeb_settings_save', 'cfeb_settings_nonce' ); ?>
				<input type="hidden" name="cfeb_settings_tab" value="<?php echo esc_attr( $tab ); ?>" />

				<?php if ( 'general' === $tab ) : ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cfeb_admin_email">Email admin (notifications)</label></th>
						<td><input type="email" id="cfeb_admin_email" name="cfeb_admin_email" value="<?php echo esc_attr( $opts['admin_email'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cfeb_admin_emails_extra">Emails admin supplémentaires</label></th>
						<td>
							<input type="text" id="cfeb_admin_emails_extra" name="cfeb_admin_emails_extra" value="<?php echo esc_attr( $opts['admin_emails_extra'] ); ?>" class="large-text" />
							<p class="description">Adresses email séparées par des virgules. Chacune recevra aussi les notifications de nouvelle réservation.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cfeb_ical_calendar_name">Nom du calendrier iCal</label></th>
						<td><input type="text" id="cfeb_ical_calendar_name" name="cfeb_ical_calendar_name" value="<?php echo esc_attr( $opts['ical_calendar_name'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row">Options</th>
						<td>
							<label><input type="checkbox" name="cfeb_tel_obligatoire" value="1" <?php checked( $opts['tel_obligatoire'] ); ?> /> Téléphone obligatoire <span style="color:#646970;font-weight:normal;">(widget événements historique uniquement — pour les types de rendez-vous, réglez « Téléphone obligatoire » sur la fiche de chaque type)</span></label><br>
							<label><input type="checkbox" name="cfeb_double_booking" value="1" <?php checked( $opts['double_booking'] ); ?> /> Autoriser plusieurs réservations par email</label><br>
							<label><input type="checkbox" name="cfeb_liste_attente" value="1" <?php checked( $opts['liste_attente'] ); ?> /> Activer la liste d&rsquo;attente quand l&rsquo;événement est complet</label><br>
							<label><input type="checkbox" name="cfeb_show_past_events_link" value="1" <?php checked( $opts['show_past_events_link'] ); ?> /> Afficher le lien « événements passés » sur la page publique</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cfeb_mentions_rgpd">Mention RGPD (sous le formulaire)</label></th>
						<td>
							<?php wp_editor( $opts['mentions_rgpd'], 'cfebrgpdeditor', [
								'textarea_name' => 'cfeb_mentions_rgpd',
								'textarea_rows' => 3,
								'teeny'         => true,
								'media_buttons' => false,
							] ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cfeb_confirmation_redirect">URL de redirection après réservation</label></th>
						<td>
							<input type="url" id="cfeb_confirmation_redirect" name="cfeb_confirmation_redirect" value="<?php echo esc_attr( $opts['confirmation_redirect'] ); ?>" class="large-text" placeholder="https://votresite.fr/merci/" />
							<p class="description">Si renseignée, l'utilisateur sera redirigé vers cette URL 1,5 seconde après la confirmation de sa réservation.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cfeb_groupes_categorie">Catégorie « Groupes » (widget Prochaines dates)</label></th>
						<td>
							<input type="text" id="cfeb_groupes_categorie" name="cfeb_groupes_categorie" value="<?php echo esc_attr( $opts['groupes_categorie'] ); ?>" class="regular-text" list="cfeb-categories-datalist" placeholder="ex : groupes" />
							<datalist id="cfeb-categories-datalist">
								<?php foreach ( self::get_distinct_appt_categories() as $cf_cat ) : ?>
									<option value="<?php echo esc_attr( $cf_cat ); ?>"></option>
								<?php endforeach; ?>
							</datalist>
							<p class="description">Texte libre : doit correspondre exactement au texte saisi dans le champ « Catégorie » d'un type de RDV (onglet Général de ce type) pour qu'il apparaisse dans le widget « Prochaines dates » de la barre latérale des articles (shortcode <code>[cf_prochain_atelier]</code>). Laissez vide pour ne pas filtrer (tous les types de RDV).</p>
						</td>
					</tr>
				</table>
				<?php elseif ( 'emails' === $tab ) : ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cfeb_from_name">Nom de l'expéditeur</label></th>
						<td><input type="text" id="cfeb_from_name" name="cfeb_from_name" value="<?php echo esc_attr( $opts['from_name'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cfeb_from_email">Email de l'expéditeur</label></th>
						<td><input type="email" id="cfeb_from_email" name="cfeb_from_email" value="<?php echo esc_attr( $opts['from_email'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cfeb_confirmation_msg">Message de confirmation (email utilisateur)</label></th>
						<td>
							<?php wp_editor( $opts['confirmation_msg'], 'cfebconfirmeditor', [
								'textarea_name' => 'cfeb_confirmation_msg',
								'textarea_rows' => 8,
								'teeny'         => true,
							] ); ?>
							<p class="description">Variables disponibles : {prenom} {nom} {email} {evenement} {date} {lieu} {adresse} (vide si distanciel) {lien_visio} (lien de la réunion en ligne, vide si le RDV est en présentiel) {code_visio} (code d'accès à la visio, s'il est renseigné sur le type de RDV)</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cfeb_reminder_hours">Rappel avant événement (heures)</label></th>
						<td>
							<input type="number" id="cfeb_reminder_hours" name="cfeb_reminder_hours" value="<?php echo esc_attr( $opts['reminder_hours'] ); ?>" class="small-text" min="0" />
							<p class="description">Nombre d'heures avant le début de l'événement pour envoyer le rappel (0 = désactivé).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cfeb_followup_hours">Suivi après événement (heures)</label></th>
						<td>
							<input type="number" id="cfeb_followup_hours" name="cfeb_followup_hours" value="<?php echo esc_attr( $opts['followup_hours'] ); ?>" class="small-text" min="0" />
							<p class="description">Nombre d'heures après la fin de l'événement pour envoyer le message de suivi (0 = désactivé).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cfeb_followup_message">Message de suivi</label></th>
						<td>
							<?php wp_editor( $opts['followup_message'], 'cfebfollowupeditor', [
								'textarea_name' => 'cfeb_followup_message',
								'textarea_rows' => 6,
								'teeny'         => true,
							] ); ?>
							<p class="description">Variables disponibles : {prenom} {nom} {email} {evenement} {date}</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cfeb_rappel_msg">Message de rappel</label></th>
						<td>
							<?php wp_editor( $opts['rappel_msg'], 'cfebrappeleditor', [
								'textarea_name' => 'cfeb_rappel_msg',
								'textarea_rows' => 6,
								'teeny'         => true,
							] ); ?>
							<p class="description">Envoyé X heures avant l'événement. Variables : {prenom} {nom} {evenement} {date} {lieu}</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cfeb_annulation_msg">Message d'annulation (email client)</label></th>
						<td>
							<?php wp_editor( $opts['annulation_msg'], 'cfebannulationeditor', [
								'textarea_name' => 'cfeb_annulation_msg',
								'textarea_rows' => 6,
								'teeny'         => true,
							] ); ?>
							<p class="description">Envoyé quand l'admin annule une réservation. Variables : {prenom} {nom} {evenement} {date}</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cfeb_liste_attente_msg">Message promotion liste d'attente</label></th>
						<td>
							<?php wp_editor( $opts['liste_attente_msg'], 'cfeblisteattenteeditor', [
								'textarea_name' => 'cfeb_liste_attente_msg',
								'textarea_rows' => 6,
								'teeny'         => true,
							] ); ?>
							<p class="description">Envoyé quand une place se libère. Variables : {prenom} {evenement} {date} {lieu}</p>
						</td>
					</tr>
				</table>
				<?php elseif ( 'lieux' === $tab ) :
					$locations = self::get_locations();
				?>
				<table class="form-table" role="presentation" style="max-width:700px;">
					<tr>
						<th scope="row"><label for="cfeb_default_lieu">Adresse par défaut</label></th>
						<td>
							<input type="text" id="cfeb_default_lieu" name="cfeb_default_lieu" value="<?php echo esc_attr( $opts['default_lieu'] ); ?>" class="regular-text" placeholder="Salle, adresse..." />
							<p class="description">Utilisée pour tous les créneaux présentiels de tous les types de RDV, sauf ceux qui ont leur propre « Lieu par défaut » (onglet Général du type) ou un lieu spécifique choisi sur la plage horaire (ci-dessous).</p>
						</td>
					</tr>
				</table>
				<p style="color:#646970;font-size:13px;max-width:700px;">Vos autres lieux de consultation, si vous recevez à plusieurs endroits — partagés par tous les types de RDV. Sur chaque type (onglet Disponibilité), vous pourrez choisir sur telle ou telle plage horaire lequel s'applique.</p>
				<div id="cfeb-locations-list" style="display:flex;flex-direction:column;gap:6px;margin:14px 0;max-width:700px;">
					<?php foreach ( $locations as $loc ) : ?>
					<div class="cfeb-location-row" data-id="<?php echo esc_attr( $loc['id'] ?? '' ); ?>" style="display:flex;align-items:center;gap:8px;">
						<input type="text" class="cfeb-loc-nom" style="max-width:180px;" value="<?php echo esc_attr( $loc['nom'] ?? '' ); ?>" placeholder="Nom court (ex : Salle Gambetta gauche)" />
						<input type="text" class="cfeb-loc-adresse regular-text" value="<?php echo esc_attr( $loc['adresse'] ?? '' ); ?>" placeholder="Adresse" />
						<input type="text" class="cfeb-loc-ville" style="max-width:160px;" value="<?php echo esc_attr( $loc['ville'] ?? '' ); ?>" placeholder="Ville" />
						<button type="button" class="button-link cfeb-loc-rm" style="color:#b32d2e;" title="Supprimer ce lieu"><span class="dashicons dashicons-no-alt"></span></button>
					</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button button-small" id="cfeb-loc-add">+ Ajouter un lieu</button>
				<input type="hidden" name="cfeb_locations" id="cfeb-locations-hidden" value="<?php echo esc_attr( wp_json_encode( $locations ) ); ?>" />
				<script>
				(function(){
					var list   = document.getElementById('cfeb-locations-list');
					var hidden = document.getElementById('cfeb-locations-hidden');
					var addBtn = document.getElementById('cfeb-loc-add');
					if ( ! list || ! hidden ) return;

					function serialize(){
						var rows = list.querySelectorAll('.cfeb-location-row');
						var data = [];
						rows.forEach(function(row){
							var id      = row.getAttribute('data-id') || '';
							var nom     = row.querySelector('.cfeb-loc-nom').value.trim();
							var adresse = row.querySelector('.cfeb-loc-adresse').value.trim();
							var ville   = row.querySelector('.cfeb-loc-ville').value.trim();
							if ( nom || adresse || ville ) data.push({ id: id, nom: nom, adresse: adresse, ville: ville });
						});
						hidden.value = JSON.stringify(data);
					}

					function genId(){
						return 'loc' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
					}

					function wireRow(row){
						row.querySelector('.cfeb-loc-nom').addEventListener('input', serialize);
						row.querySelector('.cfeb-loc-adresse').addEventListener('input', serialize);
						row.querySelector('.cfeb-loc-ville').addEventListener('input', serialize);
						row.querySelector('.cfeb-loc-rm').addEventListener('click', function(){
							row.remove();
							serialize();
						});
					}

					list.querySelectorAll('.cfeb-location-row').forEach(wireRow);

					if ( addBtn ) {
						addBtn.addEventListener('click', function(){
							var row = document.createElement('div');
							row.className = 'cfeb-location-row';
							row.setAttribute('data-id', genId());
							row.style.cssText = 'display:flex;align-items:center;gap:8px;';
							row.innerHTML = '<input type="text" class="cfeb-loc-nom" style="max-width:180px;" placeholder="Nom court (ex : Salle Gambetta gauche)" />'
								+ '<input type="text" class="cfeb-loc-adresse regular-text" placeholder="Adresse" />'
								+ '<input type="text" class="cfeb-loc-ville" style="max-width:160px;" placeholder="Ville" />'
								+ '<button type="button" class="button-link cfeb-loc-rm" style="color:#b32d2e;" title="Supprimer ce lieu"><span class="dashicons dashicons-no-alt"></span></button>';
							list.appendChild(row);
							wireRow(row);
							serialize();
						});
					}

					serialize();
				})();
				</script>
				<?php elseif ( 'gcal' === $tab ) : ?>
				<?php CF_GoogleCalendar::render_settings_section(); ?>
				<?php elseif ( 'paiement' === $tab ) : ?>
				<?php if ( class_exists( 'CF_SumUp' ) ) CF_SumUp::render_settings_section(); ?>
				<?php endif; ?>

				<?php submit_button( 'Enregistrer les paramètres' ); ?>
			</form>
		</div>
		<?php
	}
	public static function save_settings() {
		if (
			! isset( $_POST['cfeb_settings_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfeb_settings_nonce'] ) ), 'cfeb_settings_save' ) ||
			! current_user_can( 'manage_options' )
		) {
			return;
		}

		$tab = sanitize_key( $_POST['cfeb_settings_tab'] ?? 'general' );

		if ( 'general' === $tab ) {
			update_option( 'cfeb_admin_email',           sanitize_email( wp_unslash( $_POST['cfeb_admin_email'] ?? '' ) ) );
			$raw_extra = sanitize_textarea_field( wp_unslash( $_POST['cfeb_admin_emails_extra'] ?? '' ) );
			$valid_extras = array_filter( array_map( 'trim', explode( ',', $raw_extra ) ), 'is_email' );
			update_option( 'cfeb_admin_emails_extra', implode( ',', $valid_extras ) );
			update_option( 'cfeb_mentions_rgpd',         wp_kses_post( wp_unslash( $_POST['cfeb_mentions_rgpd'] ?? '' ) ) );
			update_option( 'cfeb_tel_obligatoire',       ! empty( $_POST['cfeb_tel_obligatoire'] ) ? 1 : 0 );
			update_option( 'cfeb_double_booking',        ! empty( $_POST['cfeb_double_booking'] )  ? 1 : 0 );
			update_option( 'cfeb_liste_attente',         ! empty( $_POST['cfeb_liste_attente'] )   ? 1 : 0 );
			update_option( 'cfeb_ical_calendar_name',    sanitize_text_field( wp_unslash( $_POST['cfeb_ical_calendar_name'] ?? '' ) ) );
			update_option( 'cfeb_show_past_events_link', ! empty( $_POST['cfeb_show_past_events_link'] ) ? 1 : 0 );
			update_option( 'cfeb_confirmation_redirect', esc_url_raw( wp_unslash( $_POST['cfeb_confirmation_redirect'] ?? '' ) ) );
			update_option( 'cfeb_groupes_categorie',     sanitize_text_field( wp_unslash( $_POST['cfeb_groupes_categorie'] ?? '' ) ) );
		} elseif ( 'emails' === $tab ) {
			update_option( 'cfeb_from_name',             sanitize_text_field( wp_unslash( $_POST['cfeb_from_name'] ?? '' ) ) );
			update_option( 'cfeb_from_email',            sanitize_email( wp_unslash( $_POST['cfeb_from_email'] ?? '' ) ) );
			update_option( 'cfeb_confirmation_msg',      wp_kses_post( wp_unslash( $_POST['cfeb_confirmation_msg'] ?? '' ) ) );
			update_option( 'cfeb_reminder_hours',        absint( $_POST['cfeb_reminder_hours'] ?? 24 ) );
			update_option( 'cfeb_followup_hours',        absint( $_POST['cfeb_followup_hours'] ?? 0 ) );
			update_option( 'cfeb_followup_message',      wp_kses_post( wp_unslash( $_POST['cfeb_followup_message'] ?? '' ) ) );
			update_option( 'cfeb_rappel_msg',            wp_kses_post( wp_unslash( $_POST['cfeb_rappel_msg'] ?? '' ) ) );
			update_option( 'cfeb_annulation_msg',        wp_kses_post( wp_unslash( $_POST['cfeb_annulation_msg'] ?? '' ) ) );
			update_option( 'cfeb_liste_attente_msg',     wp_kses_post( wp_unslash( $_POST['cfeb_liste_attente_msg'] ?? '' ) ) );
		} elseif ( 'lieux' === $tab ) {
			update_option( 'cfeb_default_lieu', sanitize_text_field( wp_unslash( $_POST['cfeb_default_lieu'] ?? '' ) ) );
			$raw   = wp_unslash( $_POST['cfeb_locations'] ?? '' );
			$input = $raw ? json_decode( $raw, true ) : [];
			$clean = [];
			if ( is_array( $input ) ) {
				foreach ( $input as $loc ) {
					$id = sanitize_key( $loc['id'] ?? '' );
					if ( ! $id ) {
						continue;
					}
					$clean[] = [
						'id'      => $id,
						'nom'     => sanitize_text_field( $loc['nom'] ?? '' ),
						'adresse' => sanitize_text_field( $loc['adresse'] ?? '' ),
						'ville'   => sanitize_text_field( $loc['ville'] ?? '' ),
					];
				}
			}
			update_option( 'cfeb_locations', wp_json_encode( $clean ) );
		} elseif ( 'gcal' === $tab ) {
			CF_GoogleCalendar::save_settings( [
				'client_id'          => $_POST['cfeb_gcal_client_id']          ?? '',
				'client_secret'      => $_POST['cfeb_gcal_client_secret']      ?? '',
				'calendar_id'        => $_POST['cfeb_gcal_calendar_id']        ?? 'primary',
				'extra_calendar_ids' => $_POST['cfeb_gcal_extra_calendar_ids'] ?? '',
			] );
			update_option( 'cfeb_gcal_sync_all', ! empty( $_POST['cfeb_gcal_sync_all'] ) ? 1 : 0 );
		} elseif ( 'paiement' === $tab && class_exists( 'CF_SumUp' ) ) {
			CF_SumUp::save_settings();
		}

		wp_safe_redirect( add_query_arg( [ 'post_type' => CFEB_SLUG, 'page' => 'cfeb-settings', 'tab' => $tab, 'cfeb_saved' => '1' ], admin_url( 'edit.php' ) ) );
		exit;
	}

	/* ── Lire les options (avec valeurs par défaut) ───────────────── */
	public static function get_options() {
		return [
			'admin_email'           => get_option( 'cfeb_admin_email',           get_option( 'admin_email' ) ),
			'admin_emails_extra'    => get_option( 'cfeb_admin_emails_extra',    '' ),
			'from_name'             => get_option( 'cfeb_from_name',             get_bloginfo( 'name' ) ),
			'from_email'            => get_option( 'cfeb_from_email',            get_option( 'admin_email' ) ),
			'confirmation_msg'      => get_option( 'cfeb_confirmation_msg',      "Bonjour {prenom},\n\nVotre réservation pour « {evenement} » le {date} est bien confirmée.\n\nÀ bientôt !" ),
			'mentions_rgpd'         => get_option( 'cfeb_mentions_rgpd',         'Vos données sont utilisées uniquement pour la gestion de vos réservations, conformément au RGPD.' ),
			'tel_obligatoire'       => (bool) get_option( 'cfeb_tel_obligatoire', 0 ),
			'double_booking'        => (bool) get_option( 'cfeb_double_booking',  0 ),
			'liste_attente'         => (bool) get_option( 'cfeb_liste_attente',   1 ),
			'reminder_hours'        => (int)  get_option( 'cfeb_reminder_hours',  24 ),
			'followup_hours'        => (int)  get_option( 'cfeb_followup_hours',  0 ),
			'followup_message'      => get_option( 'cfeb_followup_message',      '' ),
			'ical_calendar_name'    => get_option( 'cfeb_ical_calendar_name',    get_bloginfo( 'name' ) ),
			'show_past_events_link' => (bool) get_option( 'cfeb_show_past_events_link', 1 ),
			'confirmation_redirect' => get_option( 'cfeb_confirmation_redirect', '' ),
			'gcal_sync_all'         => (int)  get_option( 'cfeb_gcal_sync_all',   0 ),
			'rappel_msg'            => get_option( 'cfeb_rappel_msg',        "Bonjour {prenom},\n\nRappel : votre réservation pour \xab {evenement} \xbb a lieu le {date}.\n\n{lieu}\n\n\xc0 bient\xf4t !" ),
			'annulation_msg'        => get_option( 'cfeb_annulation_msg',    "Bonjour {prenom},\n\nVotre r\xe9servation pour \xab {evenement} \xbb du {date} a \xe9t\xe9 annul\xe9e.\n\nN'h\xe9sitez pas \xe0 vous r\xe9inscrire." ),
			'liste_attente_msg'     => get_option( 'cfeb_liste_attente_msg', "Bonjour {prenom},\n\nBonne nouvelle ! Une place s'est lib\xe9r\xe9e pour \xab {evenement} \xbb le {date}.\n\nVotre r\xe9servation est maintenant confirm\xe9e." ),
			'default_lieu'          => get_option( 'cfeb_default_lieu',     '' ),
			'groupes_categorie'     => get_option( 'cfeb_groupes_categorie', '' ),
		];
	}

	/**
	 * Lieux de consultation, partagés entre tous les types de RDV (utile pour
	 * les praticiens qui reçoivent à plusieurs endroits) — gérés depuis
	 * Paramètres → 📍 Lieux, sélectionnés créneau par créneau dans chaque
	 * type de RDV (voir CF_ApptType::resolve_location()).
	 *
	 * @return array [ ['id'=>string,'nom'=>string,'adresse'=>string,'ville'=>string], ... ]
	 */
	public static function get_locations() {
		$raw = get_option( 'cfeb_locations', '' );
		if ( ! $raw ) {
			return [];
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Valeurs distinctes déjà saisies dans le champ « Catégorie » des types
	 * de RDV — simple aide à la saisie (datalist) pour le réglage « Catégorie
	 * Groupes », qui doit correspondre exactement à l'une d'elles.
	 *
	 * @return string[]
	 */
	private static function get_distinct_appt_categories() {
		global $wpdb;
		if ( ! defined( 'CFEB_APPT_SLUG' ) ) {
			return [];
		}
		$values = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_cfeb_appt_categorie' AND pm.meta_value != '' AND p.post_type = %s
			 ORDER BY pm.meta_value ASC",
			CFEB_APPT_SLUG
		) );
		return is_array( $values ) ? $values : [];
	}

	/* ── Actions sur réservations ────────────────────────────────── */
	public static function handle_booking_action() {
		if ( ! isset( $_GET['cfeb_action'], $_GET['cfeb_id'] ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$action = sanitize_key( $_GET['cfeb_action'] );
		$id     = absint( $_GET['cfeb_id'] );

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cfeb_action_' . $id ) ) {
			return;
		}

		if ( 'delete' === $action ) {
			CF_Booking::delete( $id );
		} elseif ( 'annule' === $action ) {
			CF_Booking::update_statut( $id, 'annule' );
			if ( class_exists( 'CF_Ajax' ) ) {
				CF_Ajax::promote_waitlist( CF_Booking::get( $id )->event_id ?? 0 );
			}
		}

		wp_safe_redirect( remove_query_arg( [ 'cfeb_action', 'cfeb_id', '_wpnonce' ] ) );
		exit;
	}

	/* ── Export CSV ─────────────────────────────────────────────── */
	public static function handle_export() {
		if ( ! isset( $_GET['action'] ) || 'cfeb_export_csv' !== $_GET['action'] ) {
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cfeb_export' ) ) {
			return;
		}

		$event_id  = isset( $_GET['event_id'] )  ? absint( $_GET['event_id'] )                                : 0;
		$statut    = isset( $_GET['statut'] )    ? sanitize_key( $_GET['statut'] )                            : '';
		$search    = isset( $_GET['s'] )         ? sanitize_text_field( wp_unslash( $_GET['s'] ) )            : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) )   : '';
		$date_to   = isset( $_GET['date_to'] )   ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) )     : '';

		$rows = CF_Booking::get_all( [
			'event_id'  => $event_id,
			'statut'    => $statut,
			'search'    => $search,
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'per_page'  => 9999,
		] );

		$filename = 'reservations-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );

		$out = fopen( 'php://output', 'w' );
		fputs( $out, "\xEF\xBB\xBF" ); // BOM UTF-8 pour Excel
		fputcsv( $out, [
			'ID', 'Événement', 'Date événement',
			'Prénom', 'Nom', 'Email', 'Téléphone',
			'Places', 'Statut', 'Payé', 'Mode paiement',
			'Notes', 'Motif annulation', 'Champs perso', 'Date réservation',
		], ';' );
		foreach ( $rows as $r ) {
			$ev_date   = get_post_meta( $r->event_id, '_cfeb_date_debut', true );
			$champs    = $r->champs_perso ? wp_strip_all_tags( implode( ' | ', (array) json_decode( $r->champs_perso, true ) ) ) : '';
			fputcsv( $out, [
				$r->id,
				$r->event_title,
				$ev_date ? date_i18n( 'd/m/Y H:i', strtotime( $ev_date ) ) : '',
				$r->prenom,
				$r->nom,
				$r->email,
				$r->telephone,
				$r->nb_places,
				$r->statut,
				( property_exists( $r, 'paye' ) && $r->paye ) ? 'Oui' : 'Non',
				property_exists( $r, 'mode_paiement' ) ? $r->mode_paiement : '',
				$r->notes,
				property_exists( $r, 'motif_annulation' ) ? $r->motif_annulation : '',
				$champs,
				date_i18n( 'd/m/Y H:i', strtotime( $r->cree_le ) ),
			], ';' );
		}
		fclose( $out );
		exit;
	}

	/* ── AJAX mise à jour paiement ───────────────────────────────── */
	public static function ajax_payment_update() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( null, 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cfeb_admin' ) ) {
			wp_send_json_error( null, 403 );
		}
		$id            = absint( $_POST['id']            ?? 0 );
		$paye          = (int) (bool) ( $_POST['paye']   ?? 0 );
		$mode_paiement = sanitize_text_field( wp_unslash( $_POST['mode_paiement'] ?? '' ) );

		if ( ! $id ) {
			wp_send_json_error( null, 422 );
		}
		CF_Booking::update_payment( $id, $paye, $mode_paiement );
		wp_send_json_success( [ 'paye' => $paye ] );
	}

	/* ══════════════════════════════════════════════════════════════
	   PAGE HISTORIQUE CLIENT
	══════════════════════════════════════════════════════════════ */
	public static function page_client_history() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Accès refusé.' );
		}

		$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';

		$bookings = $email ? CF_Booking::get_history_by_email( $email ) : [];

		$statuts_labels = [
			'confirme'      => '✅ Confirmé',
			'annule'        => '❌ Annulé',
			'liste_attente' => '⏳ Attente',
			'present'       => '✔️ Présent',
			'absent'        => '🚫 Absent',
		];

		$total_confirme = 0;
		$total_paye     = 0;
		$total_presents = 0;
		foreach ( $bookings as $b ) {
			if ( in_array( $b->statut, [ 'confirme', 'present', 'absent' ], true ) ) {
				$total_confirme++;
			}
			if ( property_exists( $b, 'paye' ) && $b->paye ) {
				$total_paye++;
			}
			if ( 'present' === $b->statut ) {
				$total_presents++;
			}
		}
		?>
		<div class="wrap cfeb-admin-wrap">
			<div class="cfeb-page-header">
				<div class="cfeb-page-header-left"><h1>CF Événements &amp; Réservations</h1></div>
			</div>
			<?php self::render_tab_nav( 'cfeb-client-history' ); ?>

			<!-- Formulaire de recherche -->
			<form method="get" style="display:flex;align-items:center;gap:10px;margin:16px 0;">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( CFEB_SLUG ); ?>" />
				<input type="hidden" name="page" value="cfeb-client-history" />
				<input type="email" name="email" value="<?php echo esc_attr( $email ); ?>"
					placeholder="Adresse email du client…" style="min-width:300px;" required />
				<button type="submit" class="button button-primary">Rechercher</button>
				<?php if ( $email ) : ?>
					<a href="mailto:<?php echo esc_attr( $email ); ?>" class="button">✉️ Envoyer un email</a>
					<a href="<?php echo esc_url( add_query_arg( [
						'action'    => 'cfeb_export_csv',
						's'         => $email,
						'_wpnonce'  => wp_create_nonce( 'cfeb_export' ),
					], admin_url( 'admin-ajax.php' ) ) ); ?>" class="button">⬇️ Export CSV</a>
				<?php endif; ?>
			</form>

			<?php if ( $email && empty( $bookings ) ) : ?>
				<p style="color:#646970;">Aucune réservation trouvée pour <strong><?php echo esc_html( $email ); ?></strong>.</p>
			<?php elseif ( $bookings ) : ?>

				<!-- Résumé -->
				<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px;">
					<?php
					$stats = [
						'Total réservations'  => count( $bookings ),
						'Confirmées'          => $total_confirme,
						'Présences'           => $total_presents,
						'Paiements reçus'     => $total_paye,
					];
					foreach ( $stats as $label => $val ) : ?>
					<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:14px 20px;min-width:130px;text-align:center;">
						<div style="font-size:26px;font-weight:700;color:#1a3c5e;"><?php echo (int) $val; ?></div>
						<div style="font-size:12px;color:#6b7280;margin-top:3px;"><?php echo esc_html( $label ); ?></div>
					</div>
					<?php endforeach; ?>
				</div>

				<table class="wp-list-table widefat fixed striped cfeb-table">
					<thead>
						<tr>
							<th>Événement</th>
							<th style="width:140px;">Date événement</th>
							<th style="width:80px;">Places</th>
							<th style="width:120px;">Statut</th>
							<th style="width:90px;">Payé</th>
							<th style="width:120px;">Mode</th>
							<th>Notes / Motif</th>
							<th style="width:130px;">Réservé le</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $bookings as $b ) : ?>
						<tr>
							<td>
								<?php if ( $b->event_id ) : ?>
									<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CFEB_SLUG . '&page=cfeb-reservations&event_id=' . $b->event_id ) ); ?>">
										<?php echo esc_html( $b->event_title ?: '#' . $b->event_id ); ?>
									</a>
								<?php else : ?>—<?php endif; ?>
							</td>
							<td><?php echo $b->event_date ? esc_html( date_i18n( 'd/m/Y H\hi', strtotime( $b->event_date ) ) ) : '—'; ?></td>
							<td><?php echo (int) $b->nb_places; ?></td>
							<td><?php echo esc_html( $statuts_labels[ $b->statut ] ?? $b->statut ); ?></td>
							<td>
								<?php if ( property_exists( $b, 'paye' ) && $b->paye ) : ?>
									<span style="color:#166534;font-weight:600;">✅ Oui</span>
								<?php else : ?>
									<span style="color:#6b7280;">☐ Non</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ( property_exists( $b, 'mode_paiement' ) ? $b->mode_paiement : '' ) ?: '—' ); ?></td>
							<td style="font-size:12px;color:#555;">
								<?php if ( $b->notes ) : ?>📝 <?php echo esc_html( mb_strimwidth( $b->notes, 0, 80, '…' ) ); ?><?php endif; ?>
								<?php if ( property_exists( $b, 'motif_annulation' ) && $b->motif_annulation ) : ?>
									<span style="color:#b91c1c;">🚫 <?php echo esc_html( mb_strimwidth( $b->motif_annulation, 0, 80, '…' ) ); ?></span>
								<?php endif; ?>
								<?php if ( ! $b->notes && ! ( property_exists( $b, 'motif_annulation' ) && $b->motif_annulation ) ) : echo '—'; endif; ?>
							</td>
							<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $b->cree_le ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ── Helpers ─────────────────────────────────────────────────── */
	private static function action_url( $action, $id ) {
		return add_query_arg( [
			'cfeb_action' => $action,
			'cfeb_id'     => $id,
			'_wpnonce'    => wp_create_nonce( 'cfeb_action_' . $id ),
		] );
	}

	private static function show_notice() {
		if ( isset( $_GET['cfeb_saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>✅ Opération effectuée avec succès.</p></div>';
		}
		if ( isset( $_GET['cfeb_err'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>❌ Erreur : veuillez vérifier les champs obligatoires.</p></div>';
		}
	}
}
