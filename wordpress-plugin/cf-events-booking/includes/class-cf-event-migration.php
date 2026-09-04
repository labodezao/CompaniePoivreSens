<?php
/**
 * class-cf-event-migration.php
 *
 * Bascule du module d'événements du thème (CPT « evenement », champs
 * _evt_*) vers ce plugin (CPT « cf_event », champs _cfeb_*).
 *
 * Déplacé depuis le thème poivre-sens (inc/event-migration.php) le
 * 2026-09-04, en même temps que class-cf-event-editor.php — voir son
 * en-tête pour la dépendance aux fonctions fournies par le thème actif
 * (ps_evt_plugin_actif(), ps_format_date()…).
 *
 * La migration est volontairement **non destructive** : chaque événement
 * d'origine est conservé et simplement marqué comme migré. Rien n'est
 * supprimé — vous pourrez faire le ménage vous-même une fois le résultat
 * vérifié. Relancer la migration ne crée pas de doublons.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CF_Event_Migration {

	/** Le plugin est-il actif ? (toujours vrai ici, mais garde le nom d'origine.) */
	public static function is_active() {
		return ps_evt_plugin_actif();
	}

	/**
	 * Traduit les champs de l'ancien module vers ceux du plugin.
	 *
	 * Fonction pure (aucun accès base de données) afin d'être vérifiable :
	 * $legacy est un tableau de clés _evt_*, le retour un tableau de clés
	 * _cfeb_* prêtes à être enregistrées.
	 */
	public static function map_to_cfeb( array $legacy ) {
		$date    = trim( (string) ( $legacy['_evt_date'] ?? '' ) );
		$heure   = trim( (string) ( $legacy['_evt_heure'] ?? '' ) );
		$fin     = trim( (string) ( $legacy['_evt_heure_fin'] ?? '' ) );
		$prix    = trim( (string) ( $legacy['_evt_prix'] ?? '' ) );
		$complet = (string) ( $legacy['_evt_complet'] ?? '' ) === '1';

		$out = [];

		// Le plugin attend le format des champs datetime-local : 2026-09-12T20:30
		if ( $date !== '' ) {
			$out['_cfeb_date_debut'] = $date . 'T' . ( $heure !== '' ? $heure : '00:00' );

			if ( $fin !== '' ) {
				// Une fin antérieure au début signifie un passage après minuit.
				$jour_fin = ( $heure !== '' && $fin < $heure )
					? date( 'Y-m-d', strtotime( $date . ' +1 day' ) )
					: $date;
				$out['_cfeb_date_fin'] = $jour_fin . 'T' . $fin;
			}
		}

		$out['_cfeb_lieu']  = (string) ( $legacy['_evt_lieu'] ?? '' );
		$out['_cfeb_ville'] = (string) ( $legacy['_evt_ville'] ?? '' );

		if ( ! empty( $legacy['_evt_adresse'] ) ) {
			$out['_cfeb_infos_pratiques'] = (string) $legacy['_evt_adresse'];
		}

		// Le plugin raisonne en montant numérique ; le thème affiche un texte
		// libre (« prix libre », « sur réservation »…). On conserve les deux
		// pour ne rien perdre.
		$montant = function_exists( 'ps_seo_event_price' ) ? ps_seo_event_price( $prix ) : null;
		$out['_cfeb_prix']     = $montant !== null ? (float) $montant : 0.0;
		$out['_ps_prix_texte'] = $prix;

		if ( ! empty( $legacy['_evt_billetterie'] ) ) {
			$out['_cfeb_event_url'] = (string) $legacy['_evt_billetterie'];
		}

		$out['_cfeb_statut'] = $complet ? 'complet' : 'ouvert';

		return $out;
	}

	/**
	 * Migre un événement du thème vers le plugin.
	 * Retourne l'ID créé, ou 0 si déjà migré / migration impossible.
	 */
	public static function migrate_one( $post_id ) {
		if ( ! self::is_active() ) return 0;

		$deja = (int) get_post_meta( $post_id, '_ps_migre_vers', true );
		if ( $deja && get_post( $deja ) ) return 0; // idempotent

		$source = get_post( $post_id );
		if ( ! $source ) return 0;

		$nouveau = wp_insert_post( [
			'post_type'    => CFEB_SLUG,
			'post_title'   => $source->post_title,
			'post_content' => $source->post_content,
			'post_excerpt' => $source->post_excerpt,
			'post_status'  => $source->post_status,
			'post_date'    => $source->post_date,
			'post_name'    => $source->post_name,
		], true );

		if ( is_wp_error( $nouveau ) || ! $nouveau ) return 0;

		// Champs
		$legacy = [];
		foreach ( [ '_evt_date', '_evt_heure', '_evt_heure_fin', '_evt_lieu', '_evt_adresse',
		            '_evt_ville', '_evt_type', '_evt_prix', '_evt_billetterie', '_evt_complet' ] as $k ) {
			$legacy[ $k ] = get_post_meta( $post_id, $k, true );
		}
		foreach ( self::map_to_cfeb( $legacy ) as $cle => $valeur ) {
			update_post_meta( $nouveau, $cle, $valeur );
		}

		// Type d'événement → catégorie du plugin
		$type = (string) $legacy['_evt_type'];
		if ( $type !== '' && defined( 'CFEB_TAX' ) && taxonomy_exists( CFEB_TAX ) ) {
			$libelles = CF_Event_Editor::types();
			$nom      = $libelles[ $type ] ?? ucfirst( $type );
			$terme    = term_exists( $type, CFEB_TAX ) ?: wp_insert_term( $nom, CFEB_TAX, [ 'slug' => $type ] );
			if ( ! is_wp_error( $terme ) ) {
				wp_set_object_terms( $nouveau, (int) $terme['term_id'], CFEB_TAX );
			}
		}

		// Image à la une
		$vignette = get_post_thumbnail_id( $post_id );
		if ( $vignette ) set_post_thumbnail( $nouveau, $vignette );

		// Traçabilité dans les deux sens
		update_post_meta( $post_id, '_ps_migre_vers',   $nouveau );
		update_post_meta( $nouveau, '_ps_migre_depuis', $post_id );

		return $nouveau;
	}

	/** Événements de l'ancien module du thème restant à migrer. */
	public static function pending() {
		return get_posts( [
			'post_type'      => 'evenement',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				'relation' => 'OR',
				[ 'key' => '_ps_migre_vers', 'compare' => 'NOT EXISTS' ],
				[ 'key' => '_ps_migre_vers', 'value' => '0' ],
			],
		] );
	}

	/* ── Page d'outil dans l'administration ───────────────────── */
	public static function register_page() {
		add_submenu_page(
			'tools.php',
			__( 'Migration des événements', 'cf-events' ),
			__( 'Migration événements', 'cf-events' ),
			'manage_options',
			'ps-evt-migration',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function render_page() {
		$notice = '';

		if ( isset( $_POST['ps_migrer'] ) && check_admin_referer( 'ps_evt_migration' ) ) {
			$faits = 0;
			foreach ( self::pending() as $id ) {
				if ( self::migrate_one( $id ) ) $faits++;
			}
			$notice = $faits
				? '<div class="notice notice-success"><p>' . sprintf( esc_html__( '%d événement(s) migré(s).', 'cf-events' ), $faits ) . '</p></div>'
				: '<div class="notice notice-info"><p>' . esc_html__( 'Rien à migrer.', 'cf-events' ) . '</p></div>';
		}

		$restants = self::pending();
		$actif    = self::is_active();
		?>
		<div class="wrap">
		  <h1><?= esc_html__( 'Migration des événements vers le plugin CF', 'cf-events' ) ?></h1>
		  <?= $notice ?>

		  <?php if ( ! $actif ): ?>
		  <div class="notice notice-error"><p>
		    <?= esc_html__( 'Le plugin « CF Événements & Réservations » n\'est pas actif. Activez-le dans Extensions avant de migrer.', 'cf-events' ) ?>
		  </p></div>
		  <?php endif; ?>

		  <p style="max-width:46em">
		    <?= esc_html__( 'Cette opération recopie chaque événement du thème vers le plugin : titre, contenu, image, date et heures, lieu, ville, tarif, billetterie et état « complet ». Le type devient une catégorie du plugin.', 'cf-events' ) ?>
		  </p>
		  <p style="max-width:46em">
		    <strong><?= esc_html__( 'Rien n\'est supprimé', 'cf-events' ) ?></strong> —
		    <?= esc_html__( 'les événements d\'origine sont conservés et marqués comme migrés. Relancer l\'opération ne crée pas de doublons.', 'cf-events' ) ?>
		  </p>

		  <p><?= sprintf( esc_html__( 'Événements restant à migrer : %d', 'cf-events' ), count( $restants ) ) ?></p>

		  <form method="post">
		    <?php wp_nonce_field( 'ps_evt_migration' ); ?>
		    <button type="submit" name="ps_migrer" class="button button-primary" <?= ( ! $actif || ! $restants ) ? 'disabled' : '' ?>>
		      <?= esc_html__( 'Lancer la migration', 'cf-events' ) ?>
		    </button>
		  </form>
		</div>
		<?php
	}
}
