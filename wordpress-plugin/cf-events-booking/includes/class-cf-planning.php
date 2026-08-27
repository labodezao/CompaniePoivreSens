<?php
/**
 * Gestion des créneaux de disponibilité (modèles + génération) et des périodes de fermeture.
 *
 * Deux sous-pages dans le menu Événements :
 *   - 🕐 Créneaux  → cfeb-creneaux
 *   - 🚫 Fermetures → cfeb-closures
 *
 * Stockage :
 *   - cfeb_slot_templates (wp_options) — tableau de modèles de planning
 *   - cfeb_closures       (wp_options) — tableau de périodes de fermeture
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_Planning {

	/* ══════════════════════════════════════════════════════════════
	   ENREGISTREMENT DES PAGES
	══════════════════════════════════════════════════════════════ */
	public static function register_pages() {

		// Les deux pages ci-dessous existaient déjà (rendu, traitement des
		// formulaires) mais n'apparaissaient nulle part dans le menu :
		// add_submenu_page() n'était jamais appelée. Inaccessibles depuis
		// l'administration, sauf à en connaître l'URL par cœur.
		add_submenu_page(
			'edit.php?post_type=' . CFEB_SLUG,
			'Créneaux & Planning',
			'🕐 Créneaux',
			'edit_posts',
			'cfeb-creneaux',
			[ __CLASS__, 'page_creneaux' ]
		);
		add_submenu_page(
			'edit.php?post_type=' . CFEB_SLUG,
			'Périodes de fermeture',
			'🚫 Fermetures',
			'edit_posts',
			'cfeb-closures',
			[ __CLASS__, 'page_closures' ]
		);

		add_action( 'admin_init', [ __CLASS__, 'handle_save_template' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_delete_template' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_generate_events' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_save_closure' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_delete_closure' ] );
	}

	/* ══════════════════════════════════════════════════════════════
	   FERMETURES — CRUD
	══════════════════════════════════════════════════════════════ */

	/** Retourne toutes les fermetures, triées par date de début. */
	public static function get_closures() {
		$closures = get_option( 'cfeb_closures', [] );
		usort( $closures, static function ( $a, $b ) {
			return strcmp( $a['debut'], $b['debut'] );
		} );
		return $closures;
	}

	/**
	 * Vérifie si une date/heure tombe dans une période de fermeture.
	 *
	 * @param string $date_str  Date (YYYY-MM-DD ou datetime local format)
	 * @return string|false     Libellé de la fermeture, ou false
	 */
	public static function is_closed( $date_str ) {
		if ( ! $date_str ) {
			return false;
		}
		$ts = strtotime( $date_str );
		if ( ! $ts ) {
			return false;
		}
		foreach ( self::get_closures() as $c ) {
			$ts_debut = strtotime( $c['debut'] . ' 00:00:00' );
			$ts_fin   = strtotime( $c['fin']   . ' 23:59:59' );
			if ( $ts_debut && $ts_fin && $ts >= $ts_debut && $ts <= $ts_fin ) {
				return $c['label'] ?: 'Fermeture';
			}
		}
		return false;
	}

	/** Sauvegarde d'une nouvelle fermeture. */
	public static function handle_save_closure() {
		if ( ! isset( $_POST['cfeb_save_closure'] ) ) {
			return;
		}
		if (
			! isset( $_POST['cfeb_closure_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfeb_closure_nonce'] ) ), 'cfeb_save_closure' ) ||
			! current_user_can( 'edit_posts' )
		) {
			wp_die( 'Sécurité.' );
		}

		$label = sanitize_text_field( wp_unslash( $_POST['cfeb_closure_label'] ?? '' ) );
		$debut = sanitize_text_field( wp_unslash( $_POST['cfeb_closure_debut'] ?? '' ) );
		$fin   = sanitize_text_field( wp_unslash( $_POST['cfeb_closure_fin']   ?? '' ) );
		$note  = sanitize_text_field( wp_unslash( $_POST['cfeb_closure_note']  ?? '' ) );

		if ( ! $debut || ! $fin || strtotime( $fin ) < strtotime( $debut ) ) {
			wp_safe_redirect( add_query_arg( [
				'post_type' => CFEB_SLUG,
				'page'      => 'cfeb-closures',
				'cfeb_err'  => '1',
			], admin_url( 'edit.php' ) ) );
			exit;
		}

		$closures   = get_option( 'cfeb_closures', [] );
		$closures[] = [
			'id'    => wp_generate_password( 10, false ),
			'label' => $label ?: 'Fermeture',
			'debut' => $debut,
			'fin'   => $fin,
			'note'  => $note,
		];
		update_option( 'cfeb_closures', $closures );

		wp_safe_redirect( add_query_arg( [
			'post_type'  => CFEB_SLUG,
			'page'       => 'cfeb-closures',
			'cfeb_saved' => '1',
		], admin_url( 'edit.php' ) ) );
		exit;
	}

	/** Suppression d'une fermeture par ID. */
	public static function handle_delete_closure() {
		if ( ! isset( $_GET['cfeb_del_closure'], $_GET['_wpnonce'] ) ) {
			return;
		}
		$id = sanitize_text_field( wp_unslash( $_GET['cfeb_del_closure'] ) );
		if (
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cfeb_del_closure_' . $id ) ||
			! current_user_can( 'edit_posts' )
		) {
			return;
		}

		$closures = array_values( array_filter(
			get_option( 'cfeb_closures', [] ),
			static function ( $c ) use ( $id ) { return $c['id'] !== $id; }
		) );
		update_option( 'cfeb_closures', $closures );

		wp_safe_redirect( add_query_arg( [
			'post_type'  => CFEB_SLUG,
			'page'       => 'cfeb-closures',
			'cfeb_saved' => '1',
		], admin_url( 'edit.php' ) ) );
		exit;
	}

	/* ══════════════════════════════════════════════════════════════
	   MODÈLES DE CRÉNEAUX — CRUD
	══════════════════════════════════════════════════════════════ */

	public static function get_templates() {
		return get_option( 'cfeb_slot_templates', [] );
	}

	/** Sauvegarde (création ou modification) d'un modèle. */
	public static function handle_save_template() {
		if ( ! isset( $_POST['cfeb_save_template'] ) ) {
			return;
		}
		if (
			! isset( $_POST['cfeb_template_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfeb_template_nonce'] ) ), 'cfeb_save_template' ) ||
			! current_user_can( 'edit_posts' )
		) {
			wp_die( 'Sécurité.' );
		}

		$edit_id     = sanitize_text_field( wp_unslash( $_POST['cfeb_tpl_edit_id']   ?? '' ) );
		$nom         = sanitize_text_field( wp_unslash( $_POST['cfeb_tpl_nom']        ?? '' ) );
		$titre_event = sanitize_text_field( wp_unslash( $_POST['cfeb_tpl_titre']      ?? '' ) );
		$animateur   = sanitize_text_field( wp_unslash( $_POST['cfeb_tpl_animateur']  ?? '' ) );
		$categorie   = sanitize_text_field( wp_unslash( $_POST['cfeb_tpl_categorie']  ?? '' ) );
		$lieu        = sanitize_text_field( wp_unslash( $_POST['cfeb_tpl_lieu']        ?? '' ) );
		$prix        = (float) ( $_POST['cfeb_tpl_prix']       ?? 0 );
		$max_places  = absint( $_POST['cfeb_tpl_max_places']   ?? 0 );

		$jours_raw = isset( $_POST['cfeb_tpl_jours'] ) ? (array) $_POST['cfeb_tpl_jours'] : [];
		$jours     = array_values( array_filter( array_map( static function ( $j ) {
			$n = (int) $j;
			return ( $n >= 1 && $n <= 7 ) ? $n : 0;
		}, $jours_raw ) ) );

		// Créneaux horaires (tableaux parallèles)
		$debut_arr = isset( $_POST['cfeb_tpl_slot_debut'] ) ? (array) $_POST['cfeb_tpl_slot_debut'] : [];
		$fin_arr   = isset( $_POST['cfeb_tpl_slot_fin']   ) ? (array) $_POST['cfeb_tpl_slot_fin']   : [];
		$creneaux  = [];
		foreach ( $debut_arr as $i => $deb ) {
			$deb = sanitize_text_field( $deb );
			$fin = sanitize_text_field( $fin_arr[ $i ] ?? '' );
			if ( $deb && $fin && $fin > $deb ) {
				$creneaux[] = [ 'debut' => $deb, 'fin' => $fin ];
			}
		}

		if ( ! $nom || empty( $jours ) || empty( $creneaux ) ) {
			wp_safe_redirect( add_query_arg( [
				'post_type' => CFEB_SLUG,
				'page'      => 'cfeb-creneaux',
				'cfeb_err'  => '1',
			], admin_url( 'edit.php' ) ) );
			exit;
		}

		$tpl = [
			'id'          => $edit_id ?: wp_generate_password( 10, false ),
			'nom'         => $nom,
			'titre_event' => $titre_event ?: $nom,
			'animateur'   => $animateur,
			'categorie'   => $categorie,
			'lieu'        => $lieu,
			'prix'        => $prix,
			'max_places'  => $max_places,
			'jours'       => $jours,
			'creneaux'    => $creneaux,
		];

		$templates = self::get_templates();
		if ( $edit_id ) {
			$found = false;
			foreach ( $templates as $idx => $t ) {
				if ( $t['id'] === $edit_id ) {
					$templates[ $idx ] = $tpl;
					$found             = true;
					break;
				}
			}
			if ( ! $found ) {
				$templates[] = $tpl;
			}
		} else {
			$templates[] = $tpl;
		}
		update_option( 'cfeb_slot_templates', $templates );

		wp_safe_redirect( add_query_arg( [
			'post_type'  => CFEB_SLUG,
			'page'       => 'cfeb-creneaux',
			'cfeb_saved' => '1',
		], admin_url( 'edit.php' ) ) );
		exit;
	}

	/** Suppression d'un modèle par ID. */
	public static function handle_delete_template() {
		if ( ! isset( $_GET['cfeb_del_template'], $_GET['_wpnonce'] ) ) {
			return;
		}
		$id = sanitize_text_field( wp_unslash( $_GET['cfeb_del_template'] ) );
		if (
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cfeb_del_tpl_' . $id ) ||
			! current_user_can( 'edit_posts' )
		) {
			return;
		}

		$templates = array_values( array_filter(
			self::get_templates(),
			static function ( $t ) use ( $id ) { return $t['id'] !== $id; }
		) );
		update_option( 'cfeb_slot_templates', $templates );

		wp_safe_redirect( add_query_arg( [
			'post_type'  => CFEB_SLUG,
			'page'       => 'cfeb-creneaux',
			'cfeb_saved' => '1',
		], admin_url( 'edit.php' ) ) );
		exit;
	}

	/* ══════════════════════════════════════════════════════════════
	   GÉNÉRATION D'ÉVÉNEMENTS DEPUIS UN MODÈLE
	══════════════════════════════════════════════════════════════ */
	public static function handle_generate_events() {
		if ( ! isset( $_POST['cfeb_generate_events'] ) ) {
			return;
		}
		if (
			! isset( $_POST['cfeb_generate_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfeb_generate_nonce'] ) ), 'cfeb_generate_events' ) ||
			! current_user_can( 'edit_posts' )
		) {
			wp_die( 'Sécurité.' );
		}

		$tpl_id      = sanitize_text_field( wp_unslash( $_POST['cfeb_gen_template']   ?? '' ) );
		$date_debut  = sanitize_text_field( wp_unslash( $_POST['cfeb_gen_debut']       ?? '' ) );
		$date_fin    = sanitize_text_field( wp_unslash( $_POST['cfeb_gen_fin']         ?? '' ) );
		$skip_closed = ! empty( $_POST['cfeb_gen_skip_closed'] );

		if ( ! $tpl_id || ! $date_debut || ! $date_fin ) {
			wp_safe_redirect( add_query_arg( [
				'post_type' => CFEB_SLUG,
				'page'      => 'cfeb-creneaux',
				'cfeb_err'  => '2',
			], admin_url( 'edit.php' ) ) );
			exit;
		}

		// Trouver le modèle
		$tpl = null;
		foreach ( self::get_templates() as $t ) {
			if ( $t['id'] === $tpl_id ) {
				$tpl = $t;
				break;
			}
		}
		if ( ! $tpl ) {
			wp_safe_redirect( add_query_arg( [
				'post_type' => CFEB_SLUG,
				'page'      => 'cfeb-creneaux',
				'cfeb_err'  => '2',
			], admin_url( 'edit.php' ) ) );
			exit;
		}

		$ts_start = strtotime( $date_debut );
		$ts_end   = strtotime( $date_fin );
		if ( ! $ts_start || ! $ts_end || $ts_end < $ts_start ) {
			wp_safe_redirect( add_query_arg( [
				'post_type' => CFEB_SLUG,
				'page'      => 'cfeb-creneaux',
				'cfeb_err'  => '2',
			], admin_url( 'edit.php' ) ) );
			exit;
		}

		// Résoudre l'ID de la catégorie
		$cat_term_id = 0;
		if ( ! empty( $tpl['categorie'] ) ) {
			$term = get_term_by( 'slug', $tpl['categorie'], defined( 'CFEB_TAX' ) ? CFEB_TAX : 'cf_event_cat' );
			if ( $term ) {
				$cat_term_id = $term->term_id;
			}
		}

		$created = 0;
		$skipped = 0;
		$current = $ts_start;

		while ( $current <= $ts_end ) {
			$day_of_week = (int) gmdate( 'N', $current ); // 1=Lun, 7=Dim
			$date_str    = gmdate( 'Y-m-d', $current );

			if ( in_array( $day_of_week, (array) $tpl['jours'], true ) ) {
				// Vérifier fermetures
				if ( $skip_closed && self::is_closed( $date_str ) ) {
					$skipped++;
				} else {
					foreach ( (array) $tpl['creneaux'] as $creneau ) {
						$debut_dt = $date_str . 'T' . $creneau['debut'];
						$fin_dt   = $date_str . 'T' . $creneau['fin'];

						// Anti-doublon : même titre + même date de début
						$existing = get_posts( [
							'post_type'      => CFEB_SLUG,
							'post_status'    => 'any',
							'posts_per_page' => 1,
							'fields'         => 'ids',
							'meta_query'     => [ [
								'key'   => '_cfeb_date_debut',
								'value' => $debut_dt,
							] ],
							'title'          => $tpl['titre_event'],
						] );

						if ( $existing ) {
							$skipped++;
							continue;
						}

						$post_id = wp_insert_post( [
							'post_title'  => $tpl['titre_event'],
							'post_status' => 'publish',
							'post_type'   => CFEB_SLUG,
							'post_author' => get_current_user_id(),
						] );

						if ( $post_id && ! is_wp_error( $post_id ) ) {
							update_post_meta( $post_id, '_cfeb_date_debut',   $debut_dt );
							update_post_meta( $post_id, '_cfeb_date_fin',     $fin_dt );
							update_post_meta( $post_id, '_cfeb_animateur',    $tpl['animateur'] ?? '' );
							update_post_meta( $post_id, '_cfeb_lieu',         $tpl['lieu']       ?? '' );
							update_post_meta( $post_id, '_cfeb_prix',         (float) ( $tpl['prix']       ?? 0 ) );
							update_post_meta( $post_id, '_cfeb_max_places',   (int)   ( $tpl['max_places'] ?? 0 ) );
							update_post_meta( $post_id, '_cfeb_statut',       'ouvert' );
							update_post_meta( $post_id, '_cfeb_statut_event', 'publie' );
							if ( $cat_term_id ) {
								wp_set_object_terms( $post_id, [ $cat_term_id ], defined( 'CFEB_TAX' ) ? CFEB_TAX : 'cf_event_cat' );
							}
							$created++;
						}
					}
				}
			}

			$current = strtotime( '+1 day', $current );
		}

		delete_transient( 'cfeb_events_upcoming' );

		wp_safe_redirect( add_query_arg( [
			'post_type'      => CFEB_SLUG,
			'page'           => 'cfeb-creneaux',
			'cfeb_generated' => $created,
			'cfeb_skipped'   => $skipped,
		], admin_url( 'edit.php' ) ) );
		exit;
	}

	/* ══════════════════════════════════════════════════════════════
	   PAGE — CRÉNEAUX
	══════════════════════════════════════════════════════════════ */
	public static function page_creneaux() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Accès refusé.' );
		}

		$templates = self::get_templates();

		// Mode édition ?
		$edit_id  = isset( $_GET['cfeb_edit_tpl'] ) ? sanitize_text_field( wp_unslash( $_GET['cfeb_edit_tpl'] ) ) : '';
		$edit_tpl = null;
		if ( $edit_id ) {
			foreach ( $templates as $t ) {
				if ( $t['id'] === $edit_id ) {
					$edit_tpl = $t;
					break;
				}
			}
		}

		$categories   = get_terms( [
			'taxonomy'   => defined( 'CFEB_TAX' ) ? CFEB_TAX : 'cf_event_cat',
			'hide_empty' => false,
		] );
		$jours_labels = [ 1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim' ];
		?>
		<div class="wrap cfeb-admin-wrap cfeb-planning-wrap">
			<h1 class="wp-heading-inline">🕐 Créneaux & Planning</h1>
			<hr class="wp-header-end" />

			<?php if ( isset( $_GET['cfeb_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>✅ Enregistré avec succès.</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['cfeb_err'] ) ) : ?>
				<?php $errs = [ '1' => 'Nom, jours et au moins un créneau horaire sont obligatoires.', '2' => 'Sélectionnez un modèle et une plage de dates valide.' ]; ?>
				<div class="notice notice-error is-dismissible"><p>❌ <?php echo esc_html( $errs[ $_GET['cfeb_err'] ] ?? 'Erreur.' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['cfeb_generated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>✅ <strong><?php echo (int) $_GET['cfeb_generated']; ?></strong> événement(s) créé(s)
					<?php if ( isset( $_GET['cfeb_skipped'] ) && (int) $_GET['cfeb_skipped'] > 0 ) : ?>
						— <strong><?php echo (int) $_GET['cfeb_skipped']; ?></strong> ignoré(s) (doublon ou fermeture).
					<?php else : ?>.</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="cfeb-planning-columns">

				<!-- ══ Formulaire modèle ══════════════════════════ -->
				<div class="cfeb-planning-form">
					<h2><?php echo $edit_tpl ? '✏️ Modifier le modèle' : '➕ Nouveau modèle de créneaux'; ?></h2>
					<form method="post" id="cfeb-tpl-form">
						<?php wp_nonce_field( 'cfeb_save_template', 'cfeb_template_nonce' ); ?>
						<input type="hidden" name="cfeb_save_template" value="1" />
						<input type="hidden" name="cfeb_tpl_edit_id" value="<?php echo esc_attr( $edit_tpl['id'] ?? '' ); ?>" />
						<table class="form-table">
							<tr>
								<th><label>Nom du modèle <span class="cfeb-req">*</span></label></th>
								<td><input type="text" name="cfeb_tpl_nom" value="<?php echo esc_attr( $edit_tpl['nom'] ?? '' ); ?>" class="regular-text" required /></td>
							</tr>
							<tr>
								<th><label>Titre des événements générés</label></th>
								<td>
									<input type="text" name="cfeb_tpl_titre" value="<?php echo esc_attr( $edit_tpl['titre_event'] ?? '' ); ?>" class="regular-text" placeholder="Laissez vide = nom du modèle" />
								</td>
							</tr>
							<tr>
								<th><label>Animateur</label></th>
								<td><input type="text" name="cfeb_tpl_animateur" value="<?php echo esc_attr( $edit_tpl['animateur'] ?? '' ); ?>" class="regular-text" /></td>
							</tr>
							<tr>
								<th><label>Lieu</label></th>
								<td><input type="text" name="cfeb_tpl_lieu" value="<?php echo esc_attr( $edit_tpl['lieu'] ?? '' ); ?>" class="regular-text" placeholder="Adresse ou salle" /></td>
							</tr>
							<tr>
								<th><label>Catégorie</label></th>
								<td>
									<select name="cfeb_tpl_categorie">
										<option value="">— Aucune —</option>
										<?php if ( ! is_wp_error( $categories ) ) : ?>
										<?php foreach ( $categories as $cat ) : ?>
											<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( ( $edit_tpl['categorie'] ?? '' ), $cat->slug ); ?>><?php echo esc_html( $cat->name ); ?></option>
										<?php endforeach; ?>
										<?php endif; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th><label>Prix (€)</label></th>
								<td><input type="number" name="cfeb_tpl_prix" value="<?php echo esc_attr( $edit_tpl['prix'] ?? 0 ); ?>" min="0" step="0.01" class="small-text" /> <span class="description">0 = gratuit</span></td>
							</tr>
							<tr>
								<th><label>Places max</label></th>
								<td><input type="number" name="cfeb_tpl_max_places" value="<?php echo esc_attr( $edit_tpl['max_places'] ?? 0 ); ?>" min="0" class="small-text" /> <span class="description">0 = illimité</span></td>
							</tr>
							<tr>
								<th><label>Jours <span class="cfeb-req">*</span></label></th>
								<td class="cfeb-jours-row">
									<?php foreach ( $jours_labels as $num => $nom ) : ?>
										<label class="cfeb-jour-label">
											<input type="checkbox" name="cfeb_tpl_jours[]" value="<?php echo (int) $num; ?>"
												<?php checked( in_array( $num, (array) ( $edit_tpl['jours'] ?? [] ), true ) ); ?> />
											<?php echo esc_html( $nom ); ?>
										</label>
									<?php endforeach; ?>
								</td>
							</tr>
							<tr>
								<th><label>Créneaux horaires <span class="cfeb-req">*</span></label></th>
								<td>
									<div id="cfeb-slots-list">
										<?php $init_creneaux = $edit_tpl['creneaux'] ?? [ [ 'debut' => '09:00', 'fin' => '10:00' ] ]; ?>
										<?php foreach ( $init_creneaux as $cr ) : ?>
										<div class="cfeb-slot-row">
											<input type="time" name="cfeb_tpl_slot_debut[]" value="<?php echo esc_attr( $cr['debut'] ); ?>" required />
											<span class="cfeb-slot-arrow">→</span>
											<input type="time" name="cfeb_tpl_slot_fin[]"   value="<?php echo esc_attr( $cr['fin'] ); ?>"   required />
											<button type="button" class="button button-small cfeb-rm-slot" title="Retirer ce créneau">✕</button>
										</div>
										<?php endforeach; ?>
									</div>
									<button type="button" id="cfeb-add-slot" class="button button-small" style="margin-top:6px;">+ Ajouter un créneau</button>
									<p class="description">Chaque créneau correspond à un événement distinct (ex : 09h–10h et 14h–15h = 2 événements par jour)</p>
								</td>
							</tr>
						</table>
						<p>
							<button type="submit" class="button button-primary">
								<?php echo $edit_tpl ? 'Enregistrer les modifications' : 'Créer le modèle'; ?>
							</button>
							<?php if ( $edit_tpl ) : ?>
								<a href="<?php echo esc_url( remove_query_arg( 'cfeb_edit_tpl' ) ); ?>" class="button">Annuler</a>
							<?php endif; ?>
						</p>
					</form>
				</div>

				<!-- ══ Liste des modèles ══════════════════════════ -->
				<div class="cfeb-planning-list">
					<h2>Modèles enregistrés</h2>
					<?php if ( $templates ) : ?>
						<?php foreach ( $templates as $tpl ) : ?>
						<div class="cfeb-template-card<?php echo ( $edit_id === $tpl['id'] ) ? ' cfeb-template-card--active' : ''; ?>">
							<div class="cfeb-template-header">
								<strong><?php echo esc_html( $tpl['nom'] ); ?></strong>
								<div class="cfeb-template-actions">
									<a href="<?php echo esc_url( add_query_arg( [
										'post_type'     => CFEB_SLUG,
										'page'          => 'cfeb-creneaux',
										'cfeb_edit_tpl' => $tpl['id'],
									], admin_url( 'edit.php' ) ) ); ?>" class="button button-small">✏️ Modifier</a>
									<a href="<?php echo esc_url( wp_nonce_url(
										add_query_arg( [
											'post_type'        => CFEB_SLUG,
											'page'             => 'cfeb-creneaux',
											'cfeb_del_template'=> $tpl['id'],
										], admin_url( 'edit.php' ) ),
										'cfeb_del_tpl_' . $tpl['id']
									) ); ?>" class="button button-small cfeb-delete" onclick="return confirm('Supprimer ce modèle ?')">🗑️ Supprimer</a>
								</div>
							</div>
							<div class="cfeb-template-meta">
								<?php if ( $tpl['titre_event'] && $tpl['titre_event'] !== $tpl['nom'] ) : ?>
									<span>📋 <?php echo esc_html( $tpl['titre_event'] ); ?></span>
								<?php endif; ?>
								<?php if ( $tpl['animateur'] ) : ?>
									<span>👤 <?php echo esc_html( $tpl['animateur'] ); ?></span>
								<?php endif; ?>
								<span>📅 <?php echo esc_html( implode( ', ', array_map( static function ( $j ) use ( $jours_labels ) { return $jours_labels[ $j ] ?? $j; }, (array) $tpl['jours'] ) ) ); ?></span>
								<span>⏰ <?php echo count( (array) $tpl['creneaux'] ); ?> créneau(x)</span>
								<?php if ( (int) $tpl['max_places'] > 0 ) : ?>
									<span>👥 <?php echo (int) $tpl['max_places']; ?> places</span>
								<?php endif; ?>
								<?php if ( (float) ( $tpl['prix'] ?? 0 ) > 0 ) : ?>
									<span>💶 <?php echo esc_html( number_format( (float) $tpl['prix'], 2, ',', ' ' ) . ' €' ); ?></span>
								<?php endif; ?>
								<?php if ( $tpl['lieu'] ) : ?>
									<span>📍 <?php echo esc_html( $tpl['lieu'] ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p style="color:#646970;">Aucun modèle créé. Commencez par en créer un à gauche.</p>
					<?php endif; ?>
				</div>

			</div>

			<!-- ══ Section génération ════════════════════════════ -->
			<?php if ( $templates ) : ?>
			<div class="cfeb-generate-section">
				<h2>🚀 Générer des événements depuis un modèle</h2>
				<p style="color:#646970;margin-top:0;">Sélectionnez un modèle et une plage de dates pour créer automatiquement tous les événements correspondants. Les doublons (même titre + même heure) sont automatiquement ignorés.</p>
				<form method="post">
					<?php wp_nonce_field( 'cfeb_generate_events', 'cfeb_generate_nonce' ); ?>
					<input type="hidden" name="cfeb_generate_events" value="1" />
					<div class="cfeb-generate-form">
						<div>
							<label>Modèle <span class="cfeb-req">*</span></label>
							<select name="cfeb_gen_template" required>
								<option value="">— Choisir un modèle —</option>
								<?php foreach ( $templates as $tpl ) : ?>
									<option value="<?php echo esc_attr( $tpl['id'] ); ?>"><?php echo esc_html( $tpl['nom'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div>
							<label>Du <span class="cfeb-req">*</span></label>
							<input type="date" name="cfeb_gen_debut" required value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" />
						</div>
						<div>
							<label>Au <span class="cfeb-req">*</span></label>
							<input type="date" name="cfeb_gen_fin" required value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+3 months' ) ) ); ?>" />
						</div>
						<div class="cfeb-generate-option">
							<label>
								<input type="checkbox" name="cfeb_gen_skip_closed" value="1" checked />
								Ignorer les périodes de fermeture
							</label>
						</div>
						<div>
							<button type="submit" class="button button-primary button-hero">Générer les événements →</button>
						</div>
					</div>
				</form>
			</div>
			<?php endif; ?>

		</div>
		<?php
	}

	/* ══════════════════════════════════════════════════════════════
	   PAGE — FERMETURES
	══════════════════════════════════════════════════════════════ */
	public static function page_closures() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Accès refusé.' );
		}

		$closures = self::get_closures();
		$now      = current_time( 'timestamp' );
		?>
		<div class="wrap cfeb-admin-wrap cfeb-planning-wrap">
			<h1 class="wp-heading-inline">🚫 Périodes de fermeture</h1>
			<hr class="wp-header-end" />

			<?php if ( isset( $_GET['cfeb_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>✅ Enregistré.</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['cfeb_err'] ) ) : ?>
				<div class="notice notice-error is-dismissible"><p>❌ Veuillez renseigner une date de début et une date de fin valides (fin ≥ début).</p></div>
			<?php endif; ?>

			<div class="cfeb-planning-columns">

				<!-- ══ Formulaire ajout ═══════════════════════════ -->
				<div class="cfeb-planning-form" style="max-width:360px;flex:none;">
					<h2>➕ Ajouter une fermeture</h2>
					<p style="color:#646970;font-size:13px;margin-top:0;">Pendant cette période, toute nouvelle réservation sera refusée pour les événements dont la date tombe dans cette plage.</p>
					<form method="post">
						<?php wp_nonce_field( 'cfeb_save_closure', 'cfeb_closure_nonce' ); ?>
						<input type="hidden" name="cfeb_save_closure" value="1" />
						<table class="form-table">
							<tr>
								<th><label>Libellé</label></th>
								<td><input type="text" name="cfeb_closure_label" class="regular-text" placeholder="Vacances d'été 2025" /></td>
							</tr>
							<tr>
								<th><label>Début <span class="cfeb-req">*</span></label></th>
								<td><input type="date" name="cfeb_closure_debut" required /></td>
							</tr>
							<tr>
								<th><label>Fin <span class="cfeb-req">*</span></label></th>
								<td><input type="date" name="cfeb_closure_fin" required /></td>
							</tr>
							<tr>
								<th><label>Note (interne)</label></th>
								<td><input type="text" name="cfeb_closure_note" class="regular-text" /></td>
							</tr>
						</table>
						<p><button type="submit" class="button button-primary">Ajouter la fermeture</button></p>
					</form>
				</div>

				<!-- ══ Liste des fermetures ═══════════════════════ -->
				<div class="cfeb-planning-list" style="flex:2;">
					<h2>Fermetures enregistrées</h2>
					<?php if ( $closures ) : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>Libellé</th>
									<th style="width:110px;">Début</th>
									<th style="width:110px;">Fin</th>
									<th style="width:70px;">Durée</th>
									<th>Note</th>
									<th style="width:50px;"></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $closures as $c ) :
									$ts_d   = $c['debut'] ? strtotime( $c['debut'] ) : 0;
									$ts_f   = $c['fin']   ? strtotime( $c['fin']   ) : 0;
									$jours  = ( $ts_d && $ts_f ) ? (int) round( ( $ts_f - $ts_d ) / DAY_IN_SECONDS ) + 1 : 0;
									$is_now = $ts_d && $ts_f && $now >= $ts_d && $now <= ( $ts_f + DAY_IN_SECONDS );
									$past   = $ts_f && $now > ( $ts_f + DAY_IN_SECONDS );
								?>
								<tr style="<?php echo $past ? 'opacity:.55;' : ( $is_now ? 'background:#fef3c7;' : '' ); ?>">
									<td>
										<strong><?php echo esc_html( $c['label'] ); ?></strong>
										<?php if ( $is_now ) : ?>
											<span class="cfeb-badge cfeb-badge--warning">EN COURS</span>
										<?php elseif ( $past ) : ?>
											<span class="cfeb-badge cfeb-badge--muted">PASSÉE</span>
										<?php else : ?>
											<span class="cfeb-badge cfeb-badge--ok">À VENIR</span>
										<?php endif; ?>
									</td>
									<td><?php echo $ts_d ? esc_html( date_i18n( 'd/m/Y', $ts_d ) ) : '—'; ?></td>
									<td><?php echo $ts_f ? esc_html( date_i18n( 'd/m/Y', $ts_f ) ) : '—'; ?></td>
									<td><?php echo $jours ? (int) $jours . ' j.' : '—'; ?></td>
									<td style="color:#646970;font-size:12px;"><?php echo esc_html( $c['note'] ); ?></td>
									<td>
										<a href="<?php echo esc_url( wp_nonce_url(
											add_query_arg( [
												'post_type'        => CFEB_SLUG,
												'page'             => 'cfeb-closures',
												'cfeb_del_closure' => $c['id'],
											], admin_url( 'edit.php' ) ),
											'cfeb_del_closure_' . $c['id']
										) ); ?>" class="cfeb-delete" title="Supprimer" onclick="return confirm('Supprimer cette période de fermeture ?')">🗑️</a>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:30px 20px;text-align:center;color:#646970;">
							<p style="font-size:32px;margin:0 0 8px;">🏖️</p>
							<p>Aucune période de fermeture définie.<br>Utilisez le formulaire ci-contre pour en ajouter une (vacances, congés, travaux…)</p>
						</div>
					<?php endif; ?>

					<div class="cfeb-closure-tip" style="margin-top:16px;padding:12px 16px;background:#eff6ff;border-left:3px solid #2271b1;border-radius:4px;font-size:13px;color:#1a3c5e;">
						<strong>💡 Comment ça fonctionne ?</strong><br>
						Quand un visiteur tente de réserver un événement dont la date tombe dans une période de fermeture, la réservation est bloquée avec un message explicatif. Les réservations déjà enregistrées ne sont pas affectées.
					</div>
				</div>

			</div>
		</div>
		<?php
	}
}
