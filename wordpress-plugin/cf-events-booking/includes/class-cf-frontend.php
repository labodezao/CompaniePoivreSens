<?php
/**
 * Frontend : shortcodes, gabarits, assets.
 *
 * Shortcodes :
 *  [cf_events]                           liste à venir
 *  [cf_events nombre="6"]                nb d'événements
 *  [cf_events categorie="constellation"] filtré
 *  [cf_events vue="calendrier"]          vue mensuelle
 *  [cf_calendrier]                       alias calendrier
 *  [cf_mes_reservations]                 réservations perso (token ou email)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_Frontend {

	/* ── Enqueue (conditionnel) ──────────────────────────────────── */
	public static function enqueue() {
		// Toujours charger sur les pages event CPT
		$is_event_page = is_singular( CFEB_SLUG ) || is_post_type_archive( CFEB_SLUG ) || is_tax( CFEB_TAX );

		// Détecter les shortcodes dans le contenu de la page courante
		$has_sc = false;
		if ( is_singular() ) {
			global $post;
			if ( $post instanceof WP_Post ) {
				foreach ( [ 'cf_events', 'cf_calendrier', 'cf_mes_reservations', 'cf_filtres', 'cf_lieux', 'cf_rdv' ] as $sc ) {
					if ( has_shortcode( $post->post_content, $sc ) ) {
						$has_sc = true;
						break;
					}
				}
			}
		}

		if ( ! $is_event_page && ! $has_sc ) {
			return;
		}

		wp_enqueue_style( 'cfeb-frontend', CFEB_URL . 'assets/css/frontend.css', [], CFEB_VERSION );
		wp_enqueue_script( 'cfeb-frontend', CFEB_URL . 'assets/js/frontend.js', [], CFEB_VERSION, true );
		wp_localize_script( 'cfeb-frontend', 'cfeb', [
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'cfeb_front' ),
			'tel_requis' => (int) CF_Admin::get_options()['tel_obligatoire'],
			'l10n'       => [
				'reserver'      => 'Réserver',
				'confirmer'     => 'Confirmer ma réservation →',
				'en_cours'      => 'Traitement…',
				'places_dispo'  => 'place(s) disponible(s)',
				'complet'       => 'Complet',
				'confirme'      => '✅ Réservation confirmée !',
				'email_envoye'  => 'Un email de confirmation a été envoyé à',
				'erreur'        => 'Une erreur est survenue. Veuillez réessayer.',
				'deja_reserve'  => 'Vous avez déjà réservé cet événement.',
				'liste_attente' => '⏳ Vous avez été ajouté·e à la liste d\'attente.',
				'ferme'         => 'Inscriptions fermées',
				'gratuit'       => 'Gratuit',
			],
		] );

		// Template single event
		if ( is_singular( CFEB_SLUG ) ) {
			add_filter( 'single_template', [ __CLASS__, 'single_template' ] );
		}
		if ( is_post_type_archive( CFEB_SLUG ) ) {
			add_filter( 'archive_template', [ __CLASS__, 'archive_template' ] );
		}

		// Gabarit single lieu
		if ( is_singular( 'cf_venue' ) ) {
			add_filter( 'single_template', function ( $t ) {
				$c  = CFEB_DIR . 'templates/single-cf_venue.php';
				$th = locate_template( 'single-cf_venue.php' );
				return $th ?: ( file_exists( $c ) ? $c : $t );
			} );
		}
	}

	/* ── Gabarits ────────────────────────────────────────────────── */
	public static function single_template( $tpl ) {
		$custom = CFEB_DIR . 'templates/single-cf-event.php';
		// Theme override possible : mettre single-cf_event.php dans le thème
		$theme_tpl = locate_template( 'single-cf_event.php' );
		return $theme_tpl ? $theme_tpl : ( file_exists( $custom ) ? $custom : $tpl );
	}

	public static function archive_template( $tpl ) {
		$custom    = CFEB_DIR . 'templates/archive-cf-event.php';
		$theme_tpl = locate_template( 'archive-cf_event.php' );
		return $theme_tpl ? $theme_tpl : ( file_exists( $custom ) ? $custom : $tpl );
	}

	/* ── Enregistrement shortcodes ───────────────────────────────── */
	public static function init_shortcodes() {
		add_shortcode( 'cf_events',           [ __CLASS__, 'sc_events' ] );
		add_shortcode( 'cf_calendrier',       [ __CLASS__, 'sc_calendrier' ] );
		add_shortcode( 'cf_mes_reservations', [ __CLASS__, 'sc_mes_reservations' ] );
		add_shortcode( 'cf_filtres',          [ __CLASS__, 'sc_filtres' ] );
		add_shortcode( 'cf_lieux',            [ __CLASS__, 'sc_lieux' ] );
	}

	/* ══════════════════════════════════════════════════════════════
	   SHORTCODE [cf_events]
	══════════════════════════════════════════════════════════════ */
	public static function sc_events( $atts ) {
		$atts = shortcode_atts( [
			'nombre'       => 12,
			'categorie'    => '',
			'tag'          => '',
			'vue'          => 'liste',  // liste | calendrier | jour | semaine
			'mois'         => '',       // YYYY-MM pour forcer le mois calendrier
			'statut_event' => '',       // ouvert | complet | ferme | annule
			'featured'     => '',       // 1 pour ne montrer que les événements mis en avant
			'passe'        => '',       // 1 pour inclure les événements passés
		], $atts, 'cf_events' );

		if ( 'calendrier' === $atts['vue'] ) {
			return self::render_calendrier( $atts );
		}
		if ( 'jour' === $atts['vue'] ) {
			return self::render_jour( $atts );
		}
		if ( 'semaine' === $atts['vue'] ) {
			return self::render_semaine( $atts );
		}
		return self::render_liste( $atts );
	}

	public static function sc_calendrier( $atts ) {
		$atts          = shortcode_atts( [ 'mois' => '', 'categorie' => '' ], $atts, 'cf_calendrier' );
		$atts['vue']    = 'calendrier';
		$atts['nombre'] = 100;
		return self::render_calendrier( $atts );
	}

	/* ── Vue liste ───────────────────────────────────────────────── */
	private static function render_liste( $atts ) {
		// Chargement des assets si pas encore fait
		if ( ! wp_script_is( 'cfeb-frontend', 'enqueued' ) ) {
			wp_enqueue_style(  'cfeb-frontend', CFEB_URL . 'assets/css/frontend.css', [], CFEB_VERSION );
			wp_enqueue_script( 'cfeb-frontend', CFEB_URL . 'assets/js/frontend.js', [], CFEB_VERSION, true );
		}

		$events = self::get_events( $atts );

		ob_start();
		echo '<div class="cfeb-events-list">';

		if ( empty( $events ) ) {
			echo '<p class="cfeb-no-events">Aucun événement à venir pour le moment. Revenez bientôt !</p>';
		} else {
			foreach ( $events as $post ) {
				echo self::render_event_card( $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		echo '</div>';
		echo self::modal_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return ob_get_clean();
	}

	/* ── Carte événement ─────────────────────────────────────────── */
	public static function render_event_card( $post ) {
		$m      = CF_CPT::get_meta( $post->ID );
		$dispo  = CF_CPT::get_dispo( $post->ID );
		$max    = (int) $m['max_places'];
		$opts   = CF_Admin::get_options();
		$cats   = get_the_terms( $post->ID, CFEB_TAX );

		$date_debut = $m['date_debut'] ? strtotime( $m['date_debut'] ) : 0;
		$date_fin   = $m['date_fin']   ? strtotime( $m['date_fin'] )   : 0;
		$statut     = CF_CPT::compute_statut( $post->ID );
		$prix       = (float) $m['prix'];
		$thumb      = get_the_post_thumbnail( $post->ID, 'medium', [ 'class' => 'cfeb-card-img' ] );

		// Données pour le JS
		$data = htmlspecialchars( wp_json_encode( [
			'id'      => $post->ID,
			'titre'   => $post->post_title,
			'date'    => $date_debut ? date_i18n( 'l j F Y à H\hi', $date_debut ) : '',
			'lieu'    => $m['lieu'],
			'prix'      => $prix > 0 ? number_format( $prix, 2, ',', ' ' ) . ' €' : 'Gratuit',
			'prix_brut' => $prix,
			'dispo'   => PHP_INT_MAX === $dispo ? -1 : $dispo,
			'max'     => $max,
			'statut'  => $statut,
			'visio'   => (bool) $m['lien_visio'],
		] ), ENT_QUOTES, 'UTF-8' );

		ob_start();
		?>
		<article class="cfeb-card cfeb-statut-<?php echo esc_attr( $statut ); ?>">
			<?php if ( $thumb ) : ?>
				<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="cfeb-card-thumb"><?php echo $thumb; // phpcs:ignore ?></a>
			<?php endif; ?>
			<div class="cfeb-card-body">
				<?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
					<div class="cfeb-card-cats">
						<?php foreach ( $cats as $cat ) : ?>
							<a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" class="cfeb-cat"><?php echo esc_html( $cat->name ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<h3 class="cfeb-card-title">
					<a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( $post->post_title ); ?></a>
				</h3>

				<ul class="cfeb-card-meta">
					<?php if ( $date_debut ) : ?>
						<li class="cfeb-meta-date">
							📅 <?php echo esc_html( date_i18n( 'l j F Y', $date_debut ) ); ?>
							&nbsp;à&nbsp;<?php echo esc_html( date_i18n( 'H\hi', $date_debut ) ); ?>
							<?php if ( $date_fin ) : ?>
								&nbsp;–&nbsp;<?php echo esc_html( date_i18n( 'H\hi', $date_fin ) ); ?>
							<?php endif; ?>
						</li>
					<?php endif; ?>
					<?php if ( $m['lieu'] ) : ?>
						<li class="cfeb-meta-lieu">📍 <?php echo esc_html( $m['lieu'] ); ?></li>
					<?php elseif ( $m['lien_visio'] ) : ?>
						<li class="cfeb-meta-lieu">💻 En ligne (Zoom / visio)</li>
					<?php endif; ?>
					<li class="cfeb-meta-prix">
						💶 <?php echo $prix > 0 ? esc_html( number_format( $prix, 2, ',', ' ' ) . ' €' ) : 'Gratuit'; ?>
					</li>
					<?php if ( $max > 0 ) : ?>
						<li class="cfeb-meta-places">
							<?php if ( $dispo > 0 && 'ouvert' === $statut ) : ?>
								👥 <?php echo (int) $dispo; ?> place(s) disponible(s)
							<?php elseif ( 'complet' === $statut ) : ?>
								🔴 Complet<?php if ( $opts['liste_attente'] ) : ?> – liste d'attente ouverte<?php endif; ?>
							<?php endif; ?>
						</li>
					<?php endif; ?>
					<?php if ( $m['animateur'] ) : ?>
						<li class="cfeb-meta-anim">🧘 <?php echo esc_html( $m['animateur'] ); ?></li>
					<?php endif; ?>
				</ul>

				<?php if ( $post->post_excerpt ) : ?>
					<p class="cfeb-card-excerpt"><?php echo esc_html( $post->post_excerpt ); ?></p>
				<?php endif; ?>

				<div class="cfeb-card-footer">
					<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="cfeb-btn cfeb-btn-secondary">En savoir plus</a>

					<?php if ( 'ouvert' === $statut || ( 'complet' === $statut && $opts['liste_attente'] ) ) : ?>
						<button
							class="cfeb-btn cfeb-btn-primary cfeb-open-modal"
							data-event='<?php echo $data; // phpcs:ignore ?>'
						>
							<?php echo 'complet' === $statut ? '⏳ Liste d\'attente' : '✅ Réserver'; ?>
						</button>
					<?php elseif ( 'ferme' === $statut ) : ?>
						<span class="cfeb-badge cfeb-badge-ferme">🔒 Inscriptions fermées</span>
					<?php else : ?>
						<span class="cfeb-badge cfeb-badge-complet">🔴 Complet</span>
					<?php endif; ?>

					<?php echo self::calendar_buttons( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	/* ── Vue calendrier ──────────────────────────────────────────── */
	private static function render_calendrier( $atts ) {
		if ( ! wp_script_is( 'cfeb-frontend', 'enqueued' ) ) {
			wp_enqueue_style(  'cfeb-frontend', CFEB_URL . 'assets/css/frontend.css', [], CFEB_VERSION );
			wp_enqueue_script( 'cfeb-frontend', CFEB_URL . 'assets/js/frontend.js', [], CFEB_VERSION, true );
		}

		// Mois courant
		$now       = current_time( 'timestamp' );
		$mois_str  = $atts['mois'] ?: gmdate( 'Y-m', $now );
		$ts_mois   = strtotime( $mois_str . '-01' );
		$mois_prev = gmdate( 'Y-m', strtotime( '-1 month', $ts_mois ) );
		$mois_next = gmdate( 'Y-m', strtotime( '+1 month', $ts_mois ) );
		$nb_jours  = (int) gmdate( 't', $ts_mois );
		$premier_jour_semaine = (int) gmdate( 'N', $ts_mois ); // 1=lun … 7=dim

		// Événements du mois
		$events = self::get_events( array_merge( $atts, [ 'mois_cal' => $mois_str, 'nombre' => 200 ] ) );

		// Indexer par jour
		$by_day = [];
		foreach ( $events as $e ) {
			$d = get_post_meta( $e->ID, '_cfeb_date_debut', true );
			if ( $d && substr( $d, 0, 7 ) === $mois_str ) {
				$jour = (int) substr( $d, 8, 2 );
				$by_day[ $jour ][] = $e;
			}
		}

		$jours_sem = [ 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim' ];

		ob_start();
		?>
		<div class="cfeb-calendrier" data-mois="<?php echo esc_attr( $mois_str ); ?>">
			<div class="cfeb-cal-nav">
				<a href="?mois=<?php echo esc_attr( $mois_prev ); ?>" class="cfeb-cal-prev">◀ <?php echo esc_html( date_i18n( 'F Y', strtotime( $mois_prev . '-01' ) ) ); ?></a>
				<strong class="cfeb-cal-titre"><?php echo esc_html( date_i18n( 'F Y', $ts_mois ) ); ?></strong>
				<a href="?mois=<?php echo esc_attr( $mois_next ); ?>" class="cfeb-cal-next"><?php echo esc_html( date_i18n( 'F Y', strtotime( $mois_next . '-01' ) ) ); ?> ▶</a>
			</div>

			<div class="cfeb-cal-grid">
				<?php foreach ( $jours_sem as $js ) : ?>
					<div class="cfeb-cal-header"><?php echo esc_html( $js ); ?></div>
				<?php endforeach; ?>

				<?php
				// Cellules vides avant le 1er
				for ( $i = 1; $i < $premier_jour_semaine; $i++ ) {
					echo '<div class="cfeb-cal-cell cfeb-cal-empty"></div>';
				}

				// Jours du mois
				$today      = (int) gmdate( 'j', $now );
				$this_month = gmdate( 'Y-m', $now ) === $mois_str;

				for ( $jour = 1; $jour <= $nb_jours; $jour++ ) :
					$has  = ! empty( $by_day[ $jour ] );
					$past = $this_month && $jour < $today;
					$cls  = 'cfeb-cal-cell';
					if ( $has ) $cls .= ' cfeb-has-event';
					if ( $past ) $cls .= ' cfeb-past';
					if ( $this_month && $jour === $today ) $cls .= ' cfeb-today';
					?>
					<div class="<?php echo esc_attr( $cls ); ?>">
						<span class="cfeb-cal-num"><?php echo (int) $jour; ?></span>
						<?php if ( $has ) : ?>
							<div class="cfeb-cal-events">
								<?php foreach ( $by_day[ $jour ] as $ev ) : ?>
									<?php
									$heure = get_post_meta( $ev->ID, '_cfeb_date_debut', true );
									$heure = $heure ? date_i18n( 'H\hi', strtotime( $heure ) ) : '';
									?>
									<a href="<?php echo esc_url( get_permalink( $ev ) ); ?>" class="cfeb-cal-event-link">
										<?php if ( $heure ) : ?><span class="cfeb-cal-heure"><?php echo esc_html( $heure ); ?></span><?php endif; ?>
										<?php echo esc_html( $ev->post_title ); ?>
									</a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
					<?php
				endfor;

				// Cellules vides après le dernier
				$dernier_jour_sem = (int) gmdate( 'N', strtotime( $mois_str . '-' . $nb_jours ) );
				for ( $i = $dernier_jour_sem; $i < 7; $i++ ) {
					echo '<div class="cfeb-cal-cell cfeb-cal-empty"></div>';
				}
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ── Shortcode mes réservations ──────────────────────────────── */
	public static function sc_mes_reservations( $atts ) {
		if ( ! wp_script_is( 'cfeb-frontend', 'enqueued' ) ) {
			wp_enqueue_style(  'cfeb-frontend', CFEB_URL . 'assets/css/frontend.css', [], CFEB_VERSION );
			wp_enqueue_script( 'cfeb-frontend', CFEB_URL . 'assets/js/frontend.js', [], CFEB_VERSION, true );
		}

		ob_start();

		// Annulation par token (lien email)
		if ( isset( $_GET['cfeb_annuler'], $_GET['cfeb_token'] ) ) {
			$token  = sanitize_text_field( wp_unslash( $_GET['cfeb_token'] ) );
			$result = CF_Booking::cancel_by_token( $token );
			if ( is_wp_error( $result ) ) {
				echo '<div class="cfeb-alert cfeb-alert-error">❌ ' . esc_html( $result->get_error_message() ) . '</div>';
			} else {
				CF_Email::annulation_user( (array) $result, get_post( $result->event_id ) );
				echo '<div class="cfeb-alert cfeb-alert-success">✅ Votre réservation a bien été annulée.</div>';
			}
		}

		// Reprogrammation cliente par token
		if ( isset( $_GET['cfeb_reschedule'], $_GET['cfeb_token'] ) ) {
			$token   = sanitize_text_field( wp_unslash( $_GET['cfeb_token'] ) );
			$booking = CF_Booking::get_by_token( $token );
			if ( $booking && 'confirme' === $booking->statut ) {
				$orig_meta = CF_CPT::get_meta( $booking->event_id );
				$title_search = $booking->event_title ?: get_the_title( $booking->event_id );
				// Cherche d'autres événements avec le même titre, à venir, reschedule_allowed
				$alts = new WP_Query( [
					'post_type'      => CFEB_SLUG,
					'post_status'    => 'publish',
					'posts_per_page' => 20,
					'post__not_in'   => [ (int) $booking->event_id ],
					'title'          => $title_search,
					'orderby'        => 'meta_value',
					'meta_key'       => '_cfeb_date_debut',
					'order'          => 'ASC',
					'no_found_rows'  => true,
					'meta_query'     => [
						'relation' => 'AND',
						[
							'key'     => '_cfeb_date_debut',
							'value'   => current_time( 'mysql' ),
							'compare' => '>=',
							'type'    => 'DATETIME',
						],
						[
							'key'     => '_cfeb_reschedule_allowed',
							'value'   => '1',
							'compare' => '=',
						],
					],
				] );
				$alt_events = $alts->posts;

				echo '<div class="cfeb-reschedule-form" style="background:#f9fafb;padding:20px;border-radius:8px;margin-bottom:20px;">';
				echo '<h3>📅 Reporter ma réservation</h3>';
				echo '<p>Vous souhaitez reporter votre réservation pour <strong>' . esc_html( $title_search ) . '</strong>.</p>';
				if ( ! empty( $alt_events ) ) {
					echo '<form id="cfeb-reschedule-form" data-nonce="' . esc_attr( wp_create_nonce( 'cfeb_front' ) ) . '" data-token="' . esc_attr( $token ) . '">';
					echo '<label for="cfeb-reschedule-event" style="display:block;margin-bottom:8px;font-weight:600;">Choisir une nouvelle date :</label>';
					echo '<select id="cfeb-reschedule-event" name="new_event_id" style="margin-bottom:12px;width:100%;max-width:400px;padding:8px;">';
					echo '<option value="">— Sélectionner une date —</option>';
					foreach ( $alt_events as $alt ) {
						$am   = CF_CPT::get_meta( $alt->ID );
						$ads  = $am['date_debut'] ? date_i18n( 'l j F Y à H\hi', strtotime( $am['date_debut'] ) ) : '';
						echo '<option value="' . esc_attr( $alt->ID ) . '">' . esc_html( $ads ?: $alt->post_title ) . '</option>';
					}
					echo '</select>';
					echo '<div class="cfeb-reschedule-alert" style="display:none;margin-bottom:8px;"></div>';
					echo '<button type="submit" class="cfeb-btn cfeb-btn-primary">✅ Confirmer le report</button>';
					echo ' <a href="' . esc_url( remove_query_arg( [ 'cfeb_reschedule', 'cfeb_token' ] ) ) . '" class="cfeb-btn cfeb-btn-secondary">Annuler</a>';
					echo '</form>';
					echo '<script>
					(function(){
						var frm = document.getElementById("cfeb-reschedule-form");
						if(!frm) return;
						frm.addEventListener("submit",function(e){
							e.preventDefault();
							var sel = frm.querySelector("[name=new_event_id]");
							var alert = frm.querySelector(".cfeb-reschedule-alert");
							if(!sel||!sel.value){alert.textContent="Veuillez sélectionner une date.";alert.style.display="";return;}
							var btn=frm.querySelector("[type=submit]");
							btn.disabled=true;btn.textContent="Traitement…";
							var fd=new FormData();
							fd.append("action","cfeb_customer_reschedule");
							fd.append("nonce",frm.dataset.nonce);
							fd.append("token",frm.dataset.token);
							fd.append("new_event_id",sel.value);
							fetch("' . esc_js( admin_url( 'admin-ajax.php' ) ) . '",{method:"POST",body:fd,headers:{"X-Requested-With":"XMLHttpRequest"}})
							.then(function(r){return r.json();})
							.then(function(res){
								if(res.success){
									frm.innerHTML="<p class=\'cfeb-alert cfeb-alert-success\'>✅ "+res.data.message+"</p>";
								} else {
									alert.textContent=(res.data&&res.data.message)?res.data.message:"Une erreur est survenue.";
									alert.style.display="";
									btn.disabled=false;btn.textContent="✅ Confirmer le report";
								}
							}).catch(function(){
								alert.textContent="Erreur réseau. Veuillez réessayer.";alert.style.display="";
								btn.disabled=false;btn.textContent="✅ Confirmer le report";
							});
						});
					})();
					</script>';
				} else {
					echo '<p>Aucune autre date disponible pour cet événement actuellement.</p>';
					echo '<a href="' . esc_url( remove_query_arg( [ 'cfeb_reschedule', 'cfeb_token' ] ) ) . '" class="cfeb-btn cfeb-btn-secondary">Retour</a>';
				}
				echo '</div>';
			}
		}

		// Récupération des réservations
		$reservations = [];
		$email_search = '';

		if ( is_user_logged_in() ) {
			// Utilisateur connecté : affichage automatique
			$reservations = CF_Booking::get_by_user_id( get_current_user_id() );
			if ( empty( $reservations ) ) {
				// Fallback : chercher par email si l'utilisateur n'a pas de user_id dans ses réservations
				$user = wp_get_current_user();
				if ( $user->user_email ) {
					global $wpdb;
					$t = CF_Booking::table();
					$reservations = $wpdb->get_results( $wpdb->prepare(
						"SELECT b.*, p.post_title AS event_title FROM {$t} b LEFT JOIN {$wpdb->posts} p ON p.ID = b.event_id WHERE b.email = %s ORDER BY b.cree_le DESC",
						$user->user_email
					) );
				}
			}
		} elseif ( isset( $_POST['cfeb_email_search'] ) && isset( $_POST['cfeb_nonce_search'] ) ) {
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfeb_nonce_search'] ) ), 'cfeb_search' ) ) {
				$email_search = sanitize_email( wp_unslash( $_POST['cfeb_email_search'] ) );
				if ( $email_search ) {
					global $wpdb;
					$t = CF_Booking::table();
					$reservations = $wpdb->get_results( $wpdb->prepare(
						"SELECT b.*, p.post_title AS event_title FROM {$t} b LEFT JOIN {$wpdb->posts} p ON p.ID = b.event_id WHERE b.email = %s ORDER BY b.cree_le DESC",
						$email_search
					) );
				}
			}
		}
		?>
		<div class="cfeb-mes-reservations">
			<h3>🗓️ Mes réservations</h3>

			<?php if ( ! is_user_logged_in() ) : ?>
				<form method="post" class="cfeb-search-form">
					<?php wp_nonce_field( 'cfeb_search', 'cfeb_nonce_search' ); ?>
					<div class="cfeb-field-row">
						<input type="email" name="cfeb_email_search" value="<?php echo esc_attr( $email_search ); ?>" placeholder="Votre adresse email" required />
						<button type="submit" class="cfeb-btn cfeb-btn-primary">Rechercher mes réservations</button>
					</div>
				</form>
			<?php endif; ?>

			<?php if ( ( $email_search || is_user_logged_in() ) && empty( $reservations ) ) : ?>
				<p class="cfeb-no-events">Aucune réservation trouvée.</p>
			<?php endif; ?>

			<?php if ( $reservations ) : ?>
				<table class="cfeb-resa-table">
					<thead>
						<tr>
							<th>Événement</th>
							<th>Date réservée</th>
							<th>Places</th>
							<th>Statut</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $reservations as $r ) : ?>
						<?php
						$r_meta            = $r->event_id ? CF_CPT::get_meta( $r->event_id ) : [];
						$reschedule_ok     = 'confirme' === $r->statut && ! empty( $r_meta['reschedule_allowed'] );
						?>
						<tr>
							<td>
								<?php if ( $r->event_id ) : ?>
									<a href="<?php echo esc_url( get_permalink( $r->event_id ) ); ?>"><?php echo esc_html( $r->event_title ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $r->event_title ?: '—' ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $r->cree_le ) ) ); ?></td>
							<td><?php echo (int) $r->nb_places; ?></td>
							<td>
								<?php
								$statuts_labels = [
									'confirme'      => '✅ Confirmé',
									'annule'        => '❌ Annulé',
									'liste_attente' => '⏳ Liste d\'attente',
									'present'       => '✔️ Présent',
									'absent'        => '🚫 Absent',
								];
								echo esc_html( $statuts_labels[ $r->statut ] ?? $r->statut );
								?>
							</td>
							<td>
								<?php if ( 'confirme' === $r->statut || 'liste_attente' === $r->statut ) : ?>
									<a href="<?php echo esc_url( add_query_arg( [ 'cfeb_annuler' => 1, 'cfeb_token' => $r->token ] ) ); ?>"
									   class="cfeb-btn cfeb-btn-danger cfeb-btn-sm"
									   onclick="return confirm('Confirmer l\'annulation ?')">
										Annuler
									</a>
								<?php endif; ?>
								<?php if ( $reschedule_ok ) : ?>
									<a href="<?php echo esc_url( add_query_arg( [ 'cfeb_reschedule' => 1, 'cfeb_token' => $r->token ] ) ); ?>"
									   class="cfeb-btn cfeb-btn-secondary cfeb-btn-sm"
									   style="margin-left:4px;">
										📅 Reporter
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ── Modal réservation (injecté une fois par page) ───────────── */
	public static function modal_html() {
		$opts = CF_Admin::get_options();
		ob_start();
		?>
		<div id="cfeb-modal" class="cfeb-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cfeb-modal-title" style="display:none;">
			<div class="cfeb-modal-inner">
				<button class="cfeb-modal-close" aria-label="Fermer">✕</button>

				<div id="cfeb-modal-form-wrap">
					<h3 id="cfeb-modal-title" class="cfeb-modal-event-titre"></h3>
					<p class="cfeb-modal-event-meta"></p>

					<form id="cfeb-form" novalidate>
						<input type="hidden" name="action" value="cfeb_reserver" />
						<input type="hidden" name="nonce"  value="" />
						<input type="hidden" name="event_id" value="" />

						<div class="cfeb-field-row">
							<div class="cfeb-field">
								<label for="cfeb-prenom">Prénom <span class="cfeb-req">*</span></label>
								<input type="text" id="cfeb-prenom" name="prenom" autocomplete="given-name" required />
							</div>
							<div class="cfeb-field">
								<label for="cfeb-nom">Nom <span class="cfeb-req">*</span></label>
								<input type="text" id="cfeb-nom" name="nom" autocomplete="family-name" required />
							</div>
						</div>

						<div class="cfeb-field">
							<label for="cfeb-email">Email <span class="cfeb-req">*</span></label>
							<input type="email" id="cfeb-email" name="email" autocomplete="email" required />
						</div>

						<div class="cfeb-field cfeb-field-tel">
							<label for="cfeb-tel">Téléphone<?php echo $opts['tel_obligatoire'] ? ' <span class="cfeb-req">*</span>' : ' <span class="cfeb-opt">(optionnel)</span>'; ?></label>
							<input type="tel" id="cfeb-tel" name="telephone" autocomplete="tel" <?php echo $opts['tel_obligatoire'] ? 'required' : ''; ?> />
						</div>

						<div class="cfeb-field cfeb-field-places">
							<label for="cfeb-places">Nombre de places</label>
							<input type="number" id="cfeb-places" name="nb_places" value="1" min="1" max="10" />
						</div>

						<div class="cfeb-field">
							<label for="cfeb-notes">Message <span class="cfeb-opt">(optionnel)</span></label>
							<textarea id="cfeb-notes" name="notes" rows="2" placeholder="Questions, besoins particuliers…"></textarea>
						</div>

						<?php if ( class_exists( 'CF_Vouchers' ) ) CF_Vouchers::render_field(); ?>
						<?php if ( class_exists( 'CF_MailPoet' ) ) CF_MailPoet::render_checkbox_field(); ?>

						<?php if ( $opts['mentions_rgpd'] ) : ?>
							<div class="cfeb-rgpd"><?php echo wp_kses_post( $opts['mentions_rgpd'] ); ?></div>
						<?php endif; ?>

						<div class="cfeb-form-alert" style="display:none;"></div>

						<button type="submit" id="cfeb-submit" class="cfeb-btn cfeb-btn-primary cfeb-btn-full">
							✅ Confirmer ma réservation →
						</button>
					</form>
				</div>

				<div id="cfeb-modal-success" style="display:none;">
					<div class="cfeb-success-icon">🎉</div>
					<h3>Réservation confirmée !</h3>
					<p id="cfeb-success-msg"></p>
					<button class="cfeb-btn cfeb-btn-secondary cfeb-modal-close">Fermer</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ── Boutons calendrier (Google, Outlook, iCal) ───────────────── */
	public static function calendar_buttons( int $event_id ): string {
		if ( ! class_exists( 'CF_Ical' ) ) {
			return '';
		}
		$google  = CF_Ical::google_calendar_url( $event_id );
		$outlook = CF_Ical::outlook_url( $event_id );
		$ical    = CF_Ical::single_export_url( $event_id );

		if ( ! $google && ! $outlook && ! $ical ) {
			return '';
		}

		$out = '<div class="cfeb-cal-buttons">';
		if ( $google ) {
			$out .= '<a href="' . esc_url( $google ) . '" target="_blank" rel="noopener noreferrer" class="cfeb-cal-btn cfeb-cal-google" title="Ajouter à Google Agenda">📅 Google</a>';
		}
		if ( $outlook ) {
			$out .= '<a href="' . esc_url( $outlook ) . '" target="_blank" rel="noopener noreferrer" class="cfeb-cal-btn cfeb-cal-outlook" title="Ajouter à Outlook">📅 Outlook</a>';
		}
		if ( $ical ) {
			$out .= '<a href="' . esc_url( $ical ) . '" class="cfeb-cal-btn cfeb-cal-ical" title="Télécharger le fichier iCal">📥 iCal</a>';
		}
		$out .= '</div>';

		return $out;
	}

	/* ── Vue jour ────────────────────────────────────────────────── */
	private static function render_jour( $atts ) {
		if ( ! wp_script_is( 'cfeb-frontend', 'enqueued' ) ) {
			wp_enqueue_style(  'cfeb-frontend', CFEB_URL . 'assets/css/frontend.css', [], CFEB_VERSION );
			wp_enqueue_script( 'cfeb-frontend', CFEB_URL . 'assets/js/frontend.js', [], CFEB_VERSION, true );
		}

		$date_str = isset( $_GET['cfeb_date'] ) ? sanitize_text_field( wp_unslash( $_GET['cfeb_date'] ) ) : gmdate( 'Y-m-d' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_str ) ) {
			$date_str = gmdate( 'Y-m-d' );
		}

		$prev_day = gmdate( 'Y-m-d', strtotime( $date_str . ' -1 day' ) );
		$next_day = gmdate( 'Y-m-d', strtotime( $date_str . ' +1 day' ) );
		$today    = gmdate( 'Y-m-d' );

		$day_atts            = $atts;
		$day_atts['date_debut'] = $date_str . ' 00:00:00';
		$day_atts['date_fin']   = $date_str . ' 23:59:59';
		$day_atts['passe']      = '1';
		$day_atts['nombre']     = 50;

		$events = self::get_events( $day_atts );

		$base_url = get_permalink() ?: home_url( '/' );

		ob_start();
		?>
		<div class="cfeb-jour-wrap">
			<div class="cfeb-jour-nav">
				<a href="<?php echo esc_url( add_query_arg( 'cfeb_date', $prev_day, $base_url ) ); ?>" class="cfeb-btn cfeb-btn-secondary">◀ <?php echo esc_html( date_i18n( 'j F', strtotime( $prev_day ) ) ); ?></a>
				<strong class="cfeb-jour-titre"><?php echo esc_html( date_i18n( 'l j F Y', strtotime( $date_str ) ) ); ?></strong>
				<a href="<?php echo esc_url( add_query_arg( 'cfeb_date', $next_day, $base_url ) ); ?>" class="cfeb-btn cfeb-btn-secondary"><?php echo esc_html( date_i18n( 'j F', strtotime( $next_day ) ) ); ?> ▶</a>
				<?php if ( $date_str !== $today ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'cfeb_date', $today, $base_url ) ); ?>" class="cfeb-btn cfeb-btn-primary">Aujourd'hui</a>
				<?php endif; ?>
			</div>

			<div class="cfeb-events-list">
			<?php if ( empty( $events ) ) : ?>
				<p class="cfeb-no-events">Aucun événement ce jour.</p>
			<?php else : ?>
				<?php foreach ( $events as $post ) : ?>
					<?php echo self::render_event_card( $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			<?php endif; ?>
			</div>
		</div>
		<?php echo self::modal_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php
		return ob_get_clean();
	}

	/* ── Shortcode [cf_filtres] ──────────────────────────────────── */
	public static function sc_filtres( $atts ) {
		$atts = shortcode_atts( [
			'target'    => '',  // ID du conteneur à mettre à jour via AJAX
			'categorie' => '1', // afficher le filtre catégorie
			'tag'       => '1',
			'date'      => '1',
			'statut'    => '0',
		], $atts, 'cf_filtres' );

		if ( ! wp_script_is( 'cfeb-frontend', 'enqueued' ) ) {
			wp_enqueue_style(  'cfeb-frontend', CFEB_URL . 'assets/css/frontend.css', [], CFEB_VERSION );
			wp_enqueue_script( 'cfeb-frontend', CFEB_URL . 'assets/js/frontend.js', [], CFEB_VERSION, true );
		}

		$categories = get_terms( [ 'taxonomy' => CFEB_TAX, 'hide_empty' => true ] );
		$tags        = get_terms( [ 'taxonomy' => 'cf_event_tag', 'hide_empty' => true ] );

		ob_start();
		?>
		<form class="cfeb-filtres" data-target="<?php echo esc_attr( $atts['target'] ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cfeb_front' ) ); ?>">
			<?php if ( $atts['categorie'] && ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
				<div class="cfeb-filtre-group">
					<label for="cfeb-filtre-cat">Catégorie</label>
					<select id="cfeb-filtre-cat" name="categorie">
						<option value="">Toutes</option>
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat->slug ); ?>"><?php echo esc_html( $cat->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<?php if ( $atts['tag'] && ! empty( $tags ) && ! is_wp_error( $tags ) ) : ?>
				<div class="cfeb-filtre-group">
					<label for="cfeb-filtre-tag">Étiquette</label>
					<select id="cfeb-filtre-tag" name="tag">
						<option value="">Toutes</option>
						<?php foreach ( $tags as $tag ) : ?>
							<option value="<?php echo esc_attr( $tag->slug ); ?>"><?php echo esc_html( $tag->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<?php if ( $atts['date'] ) : ?>
				<div class="cfeb-filtre-group">
					<label for="cfeb-filtre-date-debut">Du</label>
					<input type="date" id="cfeb-filtre-date-debut" name="date_debut" value="">
				</div>
				<div class="cfeb-filtre-group">
					<label for="cfeb-filtre-date-fin">Au</label>
					<input type="date" id="cfeb-filtre-date-fin" name="date_fin" value="">
				</div>
			<?php endif; ?>

			<?php if ( $atts['statut'] ) : ?>
				<div class="cfeb-filtre-group">
					<label for="cfeb-filtre-statut">Disponibilité</label>
					<select id="cfeb-filtre-statut" name="statut_event">
						<option value="">Tous</option>
						<option value="ouvert">Places disponibles</option>
						<option value="complet">Complet</option>
						<option value="ferme">Inscriptions fermées</option>
					</select>
				</div>
			<?php endif; ?>

			<div class="cfeb-filtre-group">
				<label for="cfeb-filtre-q">Recherche</label>
				<input type="search" id="cfeb-filtre-q" name="q" placeholder="Mot-clé…" value="">
			</div>

			<button type="submit" class="cfeb-btn cfeb-btn-primary">Filtrer</button>
			<button type="reset" class="cfeb-btn cfeb-btn-secondary">Réinitialiser</button>
		</form>
		<?php
		return ob_get_clean();
	}

	/* ── Récupérer les événements (public pour AJAX) ─────────────── */
	public static function get_events( $atts ) {
		$now       = current_time( 'mysql' );
		$passe     = ! empty( $atts['passe'] ) && '1' === (string) $atts['passe'];

		$query_args = [
			'post_type'      => CFEB_SLUG,
			'post_status'    => 'publish',
			'posts_per_page' => min( (int) ( $atts['nombre'] ?? 12 ), 100 ),
			'orderby'        => 'meta_value',
			'meta_key'       => '_cfeb_date_debut',
			'order'          => $passe ? 'DESC' : 'ASC',
			'no_found_rows'  => true,
			'update_post_term_cache' => true,
		];

		// Recherche par mot-clé
		if ( ! empty( $atts['q'] ) ) {
			$query_args['s'] = sanitize_text_field( $atts['q'] );
		}

		// Plage de dates
		$date_debut = ! empty( $atts['date_debut'] ) ? sanitize_text_field( $atts['date_debut'] ) : '';
		$date_fin   = ! empty( $atts['date_fin'] )   ? sanitize_text_field( $atts['date_fin'] )   : '';

		if ( ! empty( $atts['mois_cal'] ) ) {
			$mois       = $atts['mois_cal'];
			$ts         = strtotime( $mois . '-01' );
			$nb_jours   = (int) gmdate( 't', $ts );
			$date_debut = $mois . '-01 00:00:00';
			$date_fin   = $mois . '-' . $nb_jours . ' 23:59:59';
		}

		if ( $date_debut && $date_fin ) {
			$query_args['meta_query'] = [
				[
					'key'     => '_cfeb_date_debut',
					'value'   => [ $date_debut, $date_fin ],
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				],
			];
		} elseif ( $passe ) {
			$query_args['meta_query'] = [
				[
					'key'     => '_cfeb_date_debut',
					'value'   => $now,
					'compare' => '<',
					'type'    => 'DATETIME',
				],
			];
		} else {
			$query_args['meta_query'] = [
				[
					'key'     => '_cfeb_date_debut',
					'value'   => $now,
					'compare' => '>=',
					'type'    => 'DATETIME',
				],
			];
		}

		// Filtres taxonomiques
		$tax_query = [];

		if ( ! empty( $atts['categorie'] ) ) {
			$tax_query[] = [
				'taxonomy' => CFEB_TAX,
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_key', explode( ',', $atts['categorie'] ) ),
			];
		}

		if ( ! empty( $atts['tag'] ) ) {
			$tax_query[] = [
				'taxonomy' => 'cf_event_tag',
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_key', explode( ',', $atts['tag'] ) ),
			];
		}

		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		if ( ! empty( $tax_query ) ) {
			$query_args['tax_query'] = $tax_query;
		}

		// Événements mis en avant
		if ( ! empty( $atts['featured'] ) && '1' === (string) $atts['featured'] ) {
			$query_args['meta_query'][] = [
				'key'   => '_cfeb_featured',
				'value' => '1',
			];
			$query_args['meta_query']['relation'] = 'AND';
		}

		$q      = new WP_Query( $query_args );
		$events = $q->posts;

		// Filtrer par statut calculé si demandé
		if ( ! empty( $atts['statut_event'] ) ) {
			$s = sanitize_key( $atts['statut_event'] );
			$events = array_filter( $events, function( $post ) use ( $s ) {
				return CF_CPT::compute_statut( $post->ID ) === $s;
			} );
			$events = array_values( $events );
		}

		return $events;
	}

	/* ══════════════════════════════════════════════════════════════
	   SHORTCODE [cf_lieux]
	══════════════════════════════════════════════════════════════ */
	public static function sc_lieux( $atts ) {
		$atts = shortcode_atts( [
			'nombre'  => 12,
			'colonne' => 3,
		], $atts, 'cf_lieux' );

		if ( ! wp_script_is( 'cfeb-frontend', 'enqueued' ) ) {
			wp_enqueue_style( 'cfeb-frontend', CFEB_URL . 'assets/css/frontend.css', [], CFEB_VERSION );
		}

		$nombre  = min( (int) $atts['nombre'], 100 );
		$colonne = max( 1, min( 6, (int) $atts['colonne'] ) );
		$now     = current_time( 'mysql' );

		$q = new WP_Query( [
			'post_type'      => 'cf_venue',
			'post_status'    => 'publish',
			'posts_per_page' => $nombre,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		] );

		if ( ! $q->have_posts() ) {
			return '<p class="cfeb-no-events">Aucun lieu trouvé.</p>';
		}

		ob_start();
		echo '<div class="cfeb-lieux-grid" style="display:grid;grid-template-columns:repeat(' . esc_attr( $colonne ) . ',1fr);gap:20px;">';

		while ( $q->have_posts() ) {
			$q->the_post();
			$venue_id = get_the_ID();
			$ville    = get_post_meta( $venue_id, '_cfeb_ville', true );

			// Compter les prochains événements dans ce lieu
			$nb_events = ( new WP_Query( [
				'post_type'      => CFEB_SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
				'meta_query'     => [
					'relation' => 'AND',
					[ 'key' => '_cfeb_venue_id', 'value' => $venue_id, 'compare' => '=', 'type' => 'NUMERIC' ],
					[ 'key' => '_cfeb_date_debut', 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME' ],
				],
			] ) )->found_posts;

			echo '<div class="cfeb-venue-card" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);">';
			if ( has_post_thumbnail() ) {
				echo '<a href="' . esc_url( get_permalink() ) . '" style="display:block;aspect-ratio:16/9;overflow:hidden;">';
				the_post_thumbnail( 'medium', [ 'style' => 'width:100%;height:100%;object-fit:cover;', 'alt' => esc_attr( get_the_title() ) ] );
				echo '</a>';
			}
			echo '<div style="padding:16px;">';
			echo '<h3 style="margin:0 0 6px;font-size:16px;"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
			if ( $ville ) {
				echo '<p style="margin:0 0 6px;color:#6b7280;font-size:13px;">📍 ' . esc_html( $ville ) . '</p>';
			}
			if ( $nb_events > 0 ) {
				echo '<p style="margin:0;font-size:12px;color:#2271b1;">' . sprintf( '%d événement(s) à venir', $nb_events ) . '</p>';
			}
			echo '</div>';
			echo '</div>';
		}

		wp_reset_postdata();
		echo '</div>';
		return ob_get_clean();
	}

	/* ══════════════════════════════════════════════════════════════
	   VUE SEMAINE
	══════════════════════════════════════════════════════════════ */
	private static function render_semaine( $atts ) {
		if ( ! wp_script_is( 'cfeb-frontend', 'enqueued' ) ) {
			wp_enqueue_style(  'cfeb-frontend', CFEB_URL . 'assets/css/frontend.css', [], CFEB_VERSION );
			wp_enqueue_script( 'cfeb-frontend', CFEB_URL . 'assets/js/frontend.js', [], CFEB_VERSION, true );
		}

		// Déterminer la semaine courante ou celle demandée
		$semaine_param = isset( $_GET['cfeb_semaine'] ) ? sanitize_text_field( wp_unslash( $_GET['cfeb_semaine'] ) ) : '';
		if ( $semaine_param && preg_match( '/^(\d{4})-(\d{2})$/', $semaine_param, $m ) ) {
			$annee  = (int) $m[1];
			$semaine = (int) $m[2];
		} else {
			$annee   = (int) gmdate( 'Y' );
			$semaine = (int) gmdate( 'W' );
		}

		// Calculer lundi et dimanche de la semaine
		$ts_lundi    = strtotime( $annee . 'W' . str_pad( $semaine, 2, '0', STR_PAD_LEFT ) );
		$ts_dimanche = $ts_lundi + 6 * DAY_IN_SECONDS;

		$date_debut = gmdate( 'Y-m-d 00:00:00', $ts_lundi );
		$date_fin   = gmdate( 'Y-m-d 23:59:59', $ts_dimanche );

		// Semaines précédente et suivante
		$ts_prev      = $ts_lundi - 7 * DAY_IN_SECONDS;
		$ts_next      = $ts_lundi + 7 * DAY_IN_SECONDS;
		$sem_prev     = gmdate( 'Y-W', $ts_prev );
		$sem_next     = gmdate( 'Y-W', $ts_next );
		$sem_courante = gmdate( 'Y-W' );

		// Récupérer les événements de la semaine
		$events = self::get_events( array_merge( $atts, [
			'nombre'     => 200,
			'date_debut' => $date_debut,
			'date_fin'   => $date_fin,
		] ) );

		// Indexer par jour
		$by_day = [];
		foreach ( $events as $ev ) {
			$m_ev  = CF_CPT::get_meta( $ev->ID );
			$jour  = $m_ev['date_debut'] ? gmdate( 'Y-m-d', strtotime( $m_ev['date_debut'] ) ) : '';
			if ( $jour ) {
				$by_day[ $jour ][] = [ 'post' => $ev, 'meta' => $m_ev ];
			}
		}

		$current_url = remove_query_arg( 'cfeb_semaine' );
		$jours_noms  = [ 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim' ];

		ob_start();
		?>
		<div class="cfeb-vue-semaine">
			<!-- Navigation -->
			<div class="cfeb-semaine-nav" style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
				<a href="<?php echo esc_url( add_query_arg( 'cfeb_semaine', $sem_prev, $current_url ) ); ?>" class="cfeb-btn cfeb-btn-secondary">◀ Semaine précédente</a>
				<strong style="font-size:16px;">
					<?php echo esc_html( date_i18n( 'j F', $ts_lundi ) ); ?> – <?php echo esc_html( date_i18n( 'j F Y', $ts_dimanche ) ); ?>
				</strong>
				<a href="<?php echo esc_url( add_query_arg( 'cfeb_semaine', $sem_next, $current_url ) ); ?>" class="cfeb-btn cfeb-btn-secondary">Semaine suivante ▶</a>
				<?php if ( $semaine_param && $semaine_param !== $sem_courante ) : ?>
					<a href="<?php echo esc_url( remove_query_arg( 'cfeb_semaine', $current_url ) ); ?>" class="cfeb-btn cfeb-btn-primary">Aujourd'hui</a>
				<?php endif; ?>
			</div>

			<!-- Grille desktop (cachée sur mobile) -->
			<div class="cfeb-semaine-grid cfeb-semaine-desktop" style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px;">
				<?php for ( $i = 0; $i < 7; $i++ ) : ?>
					<?php
					$ts_jour  = $ts_lundi + $i * DAY_IN_SECONDS;
					$date_str = gmdate( 'Y-m-d', $ts_jour );
					$is_today = $date_str === gmdate( 'Y-m-d' );
					?>
					<div class="cfeb-semaine-col <?php echo $is_today ? 'cfeb-today' : ''; ?>" style="min-height:120px;">
						<div class="cfeb-semaine-header" style="font-weight:600;padding:6px 8px;background:<?php echo $is_today ? '#2271b1' : '#f3f4f6'; ?>;color:<?php echo $is_today ? '#fff' : '#374151'; ?>;border-radius:6px 6px 0 0;font-size:13px;text-align:center;">
							<?php echo esc_html( $jours_noms[ $i ] ); ?><br>
							<span style="font-size:18px;font-weight:700;"><?php echo esc_html( date_i18n( 'j', $ts_jour ) ); ?></span>
						</div>
						<div class="cfeb-semaine-events" style="padding:4px;">
							<?php if ( ! empty( $by_day[ $date_str ] ) ) : ?>
								<?php foreach ( $by_day[ $date_str ] as $item ) : ?>
									<?php
									$ev_post   = $item['post'];
									$ev_meta   = $item['meta'];
									$heure     = $ev_meta['date_debut'] ? date_i18n( 'H\hi', strtotime( $ev_meta['date_debut'] ) ) : '';
									$ev_statut = CF_CPT::compute_statut( $ev_post->ID );
									$color     = 'complet' === $ev_statut ? '#dc2626' : ( 'ferme' === $ev_statut ? '#6b7280' : '#2271b1' );
									$cats      = get_the_terms( $ev_post->ID, CFEB_TAX );
									if ( $cats && ! is_wp_error( $cats ) ) {
										$cat_color = get_term_meta( $cats[0]->term_id, 'color', true );
										if ( $cat_color ) {
											$color = $cat_color;
										}
									}
									?>
									<a href="<?php echo esc_url( get_permalink( $ev_post->ID ) ); ?>"
									   style="display:block;background:<?php echo esc_attr( $color ); ?>;color:#fff;padding:4px 6px;border-radius:4px;font-size:11px;margin-bottom:4px;text-decoration:none;line-height:1.3;"
									   title="<?php echo esc_attr( $ev_post->post_title ); ?>">
										<?php if ( $heure ) echo esc_html( $heure ) . ' '; ?>
										<?php echo esc_html( wp_trim_words( $ev_post->post_title, 5, '…' ) ); ?>
									</a>
								<?php endforeach; ?>
							<?php else : ?>
								<span style="display:block;text-align:center;color:#d1d5db;padding:12px 0;font-size:11px;">—</span>
							<?php endif; ?>
						</div>
					</div>
				<?php endfor; ?>
			</div>

			<!-- Liste mobile -->
			<div class="cfeb-semaine-mobile" style="display:none;">
				<?php for ( $i = 0; $i < 7; $i++ ) : ?>
					<?php
					$ts_jour  = $ts_lundi + $i * DAY_IN_SECONDS;
					$date_str = gmdate( 'Y-m-d', $ts_jour );
					$is_today = $date_str === gmdate( 'Y-m-d' );
					?>
					<div class="cfeb-semaine-day" style="margin-bottom:12px;">
						<div style="font-weight:700;padding:8px 12px;background:<?php echo $is_today ? '#2271b1' : '#f3f4f6'; ?>;color:<?php echo $is_today ? '#fff' : '#374151'; ?>;border-radius:6px;font-size:14px;">
							<?php echo esc_html( date_i18n( 'l j F', $ts_jour ) ); ?>
						</div>
						<?php if ( ! empty( $by_day[ $date_str ] ) ) : ?>
							<?php foreach ( $by_day[ $date_str ] as $item ) : ?>
								<div style="padding:8px 12px;border-bottom:1px solid #f3f4f6;">
									<a href="<?php echo esc_url( get_permalink( $item['post']->ID ) ); ?>" style="font-weight:600;"><?php echo esc_html( $item['post']->post_title ); ?></a>
									<?php $heure = $item['meta']['date_debut'] ? date_i18n( 'H\hi', strtotime( $item['meta']['date_debut'] ) ) : ''; ?>
									<?php if ( $heure ) : ?><span style="color:#6b7280;margin-left:8px;font-size:13px;">⏰ <?php echo esc_html( $heure ); ?></span><?php endif; ?>
								</div>
							<?php endforeach; ?>
						<?php else : ?>
							<p style="padding:8px 12px;color:#9ca3af;margin:0;font-size:13px;">Aucun événement</p>
						<?php endif; ?>
					</div>
				<?php endfor; ?>
			</div>

			<style>
				@media (max-width: 768px) {
					.cfeb-semaine-desktop { display:none !important; }
					.cfeb-semaine-mobile  { display:block !important; }
				}
			</style>
		</div>

		<?php echo self::modal_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php
		return ob_get_clean();
	}
}
