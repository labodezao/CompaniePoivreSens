<?php
/**
 * class-cf-event-bulk-create.php
 *
 * Créer une série d'événements identiques (même titre, même horaire,
 * même lieu…) qui ne diffèrent que par leur date — un atelier
 * hebdomadaire ou mensuel reconduit sur toute une saison, par exemple.
 * Sans cet outil, chaque occurrence demande de remplir la métaboîte
 * « Détails de l'événement » (class-cf-event-editor.php) à la main.
 *
 * Déplacé depuis le thème poivre-sens (inc/event-bulk-create.php) le
 * 2026-09-04, en même temps que class-cf-event-editor.php — voir son
 * en-tête pour la dépendance aux fonctions fournies par le thème actif.
 *
 * Chaque événement est créé en repassant exactement par le formulaire
 * que CF_Event_Editor sait déjà lire (read_form()) et par le hook
 * save_post qu'il enregistre (save()) : cet outil ne réécrit pas la
 * traduction vers les clés du plugin, il simule simplement une
 * soumission de ce formulaire pour chaque date.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CF_Event_Bulk_Create {

	/**
	 * Découpe la zone de texte « une date par ligne » en dates ISO
	 * (AAAA-MM-JJ), avec les lignes qui n'ont pas pu être comprises.
	 *
	 * Formats acceptés : JJ/MM/AAAA et AAAA-MM-JJ. Les lignes vides sont
	 * ignorées silencieusement ; toute autre ligne mal formée est signalée
	 * plutôt que devinée.
	 */
	public static function parse_dates( string $texte ): array {
		$iso = [];
		$invalides = [];

		foreach ( preg_split( '/\R/', $texte ) as $ligne ) {
			$ligne = trim( $ligne );
			if ( $ligne === '' ) continue;

			if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $ligne, $m ) ) {
				[ $tout, $a, $mo, $j ] = $m;
			} elseif ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $ligne, $m ) ) {
				[ $tout, $j, $mo, $a ] = $m;
			} else {
				$invalides[] = $ligne;
				continue;
			}

			if ( checkdate( (int) $mo, (int) $j, (int) $a ) ) {
				$iso[] = sprintf( '%04d-%02d-%02d', (int) $a, (int) $mo, (int) $j );
			} else {
				$invalides[] = $ligne;
			}
		}

		return [ $iso, $invalides ];
	}

	/**
	 * Un événement du même titre existe-t-il déjà à cette date ? Évite les
	 * doublons si l'outil est relancé (double clic, page rechargée…).
	 */
	public static function already_exists( string $titre, string $date_iso ): bool {
		$existants = get_posts( [
			'post_type'      => ps_evt_cpt(),
			'post_status'    => 'any',
			'title'          => $titre,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [ [ 'key' => ps_evt_cle_date(), 'value' => $date_iso, 'compare' => 'STARTS WITH' ] ],
		] );
		return ! empty( $existants );
	}

	/**
	 * Crée une occurrence. Réutilise le hook save_post déjà enregistré par
	 * CF_Event_Editor::save() en lui simulant sa propre soumission de
	 * formulaire — la même conversion vers les clés du plugin (ou de
	 * l'ancien module) qu'une saisie manuelle, sans code séparé à
	 * maintenir en double.
	 */
	public static function create_one( array $commun, string $date_iso ): int {
		// $_POST doit porter les valeurs de CETTE occurrence avant l'appel :
		// wp_insert_post() déclenche lui-même save_post, une seule fois, à la
		// fin de son propre traitement — c'est cette unique déclenche
		// naturelle que lit CF_Event_Editor::save(), exactement comme pour
		// une création manuelle depuis l'écran d'édition. Provoquer un
		// second déclenchement à la main redéclencherait aussi les hooks
		// save_post d'autres extensions (Yoast, etc.) en double.
		$post_sauvegarde = $_POST;
		$_POST = [
			'ps_evt_nonce'     => wp_create_nonce( 'ps_evt_save' ),
			'evt_date'         => $date_iso,
			'evt_heure'        => $commun['heure']       ?? '',
			'evt_heure_fin'    => $commun['heure_fin']   ?? '',
			'evt_lieu'         => $commun['lieu']        ?? '',
			'evt_adresse'      => $commun['adresse']     ?? '',
			'evt_ville'        => $commun['ville']       ?? '',
			'evt_type'         => $commun['type']        ?? '',
			'evt_prix'         => $commun['prix']        ?? '',
			'evt_billetterie'  => $commun['billetterie'] ?? '',
			'evt_max_places'   => $commun['max_places']  ?? '',
			'evt_animateur'    => $commun['animateur']   ?? '',
			'evt_statut'       => 'ouvert',
			'evt_statut_event' => 'publie',
		];

		$post_id = wp_insert_post( [
			'post_type'    => ps_evt_cpt(),
			'post_title'   => $commun['titre'],
			'post_content' => $commun['contenu'] ?? '',
			'post_status'  => 'publish',
		], true );

		$_POST = $post_sauvegarde;

		return ( is_wp_error( $post_id ) || ! $post_id ) ? 0 : $post_id;
	}

	/* ── Page d'outil dans l'administration ───────────────────── */
	public static function register_page() {
		add_submenu_page(
			'tools.php',
			__( 'Créer des événements en série', 'cf-events' ),
			__( 'Événements en série', 'cf-events' ),
			'publish_posts',
			'ps-evt-bulk-create',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'publish_posts' ) ) return;

		$notice = '';
		$valeurs = [
			'titre' => '', 'type' => '', 'heure' => '', 'heure_fin' => '',
			'lieu' => '', 'adresse' => '', 'ville' => '', 'prix' => '',
			'max_places' => '', 'animateur' => '', 'billetterie' => '',
			'contenu' => '', 'dates' => '',
		];

		if ( isset( $_POST['ps_evt_bulk_creer'] ) && check_admin_referer( 'ps_evt_bulk_create' ) ) {
			foreach ( $valeurs as $champ => $defaut ) {
				$valeurs[ $champ ] = isset( $_POST[ 'ps_evt_bulk_' . $champ ] )
					? wp_unslash( $_POST[ 'ps_evt_bulk_' . $champ ] )
					: $defaut;
			}

			$titre = trim( $valeurs['titre'] );
			[ $dates_iso, $invalides ] = self::parse_dates( $valeurs['dates'] );

			if ( $titre === '' ) {
				$notice = '<div class="notice notice-error"><p>' . esc_html__( 'Le titre est obligatoire.', 'cf-events' ) . '</p></div>';
			} elseif ( ! $dates_iso ) {
				$notice = '<div class="notice notice-error"><p>' . esc_html__( 'Aucune date valide n\'a été reconnue.', 'cf-events' ) . '</p></div>';
			} else {
				$crees = 0;
				$deja_la = 0;
				foreach ( $dates_iso as $date_iso ) {
					if ( self::already_exists( $titre, $date_iso ) ) {
						$deja_la++;
						continue;
					}
					if ( self::create_one( array_merge( $valeurs, [ 'titre' => $titre ] ), $date_iso ) ) {
						$crees++;
					}
				}

				$morceaux = [ sprintf( _n( '%d événement créé.', '%d événements créés.', $crees, 'cf-events' ), $crees ) ];
				if ( $deja_la ) {
					$morceaux[] = sprintf( _n( '%d existait déjà à cette date et a été ignoré.', '%d existaient déjà à cette date et ont été ignorés.', $deja_la, 'cf-events' ), $deja_la );
				}
				if ( $invalides ) {
					$morceaux[] = sprintf( esc_html__( 'Lignes non reconnues, ignorées : %s', 'cf-events' ), esc_html( implode( ', ', $invalides ) ) );
				}
				$notice = '<div class="notice notice-' . ( $crees ? 'success' : 'warning' ) . '"><p>' . implode( ' ', $morceaux ) . '</p></div>';

				if ( $crees ) {
					// Le formulaire repart vide : la série est faite, pas besoin
					// de la resoumettre par erreur en rechargeant la page.
					foreach ( $valeurs as $champ => $defaut ) { $valeurs[ $champ ] = $defaut; }
				}
			}
		}
		?>
		<div class="wrap">
		  <h1><?= esc_html__( 'Créer des événements en série', 'cf-events' ) ?></h1>
		  <?= $notice ?>
		  <p style="max-width:46em">
		    <?= esc_html__( 'Pour un atelier reconduit à plusieurs dates (même titre, même horaire, même lieu) : remplissez les champs communs une fois, listez les dates une par ligne, et chaque occurrence est créée comme un événement à part entière — modifiable ensuite individuellement.', 'cf-events' ) ?>
		  </p>

		  <form method="post">
		    <?php wp_nonce_field( 'ps_evt_bulk_create' ); ?>
		    <table class="form-table" role="presentation">
		      <tr>
		        <th><label for="ps_evt_bulk_titre"><?= esc_html__( 'Titre', 'cf-events' ) ?></label></th>
		        <td><input type="text" id="ps_evt_bulk_titre" name="ps_evt_bulk_titre" class="regular-text" required value="<?= esc_attr( $valeurs['titre'] ) ?>"></td>
		      </tr>
		      <tr>
		        <th><label for="ps_evt_bulk_type"><?= esc_html__( 'Type', 'cf-events' ) ?></label></th>
		        <td>
		          <select id="ps_evt_bulk_type" name="ps_evt_bulk_type">
		            <option value=""><?= esc_html__( '—', 'cf-events' ) ?></option>
		            <?php foreach ( CF_Event_Editor::types() as $cle => $libelle ): ?>
		            <option value="<?= esc_attr( $cle ) ?>" <?= selected( $valeurs['type'], $cle, false ) ?>><?= esc_html( $libelle ) ?></option>
		            <?php endforeach; ?>
		          </select>
		        </td>
		      </tr>
		      <tr>
		        <th><label for="ps_evt_bulk_heure"><?= esc_html__( 'Horaire', 'cf-events' ) ?></label></th>
		        <td>
		          <input type="time" id="ps_evt_bulk_heure" name="ps_evt_bulk_heure" value="<?= esc_attr( $valeurs['heure'] ) ?>">
		          –
		          <input type="time" name="ps_evt_bulk_heure_fin" value="<?= esc_attr( $valeurs['heure_fin'] ) ?>">
		        </td>
		      </tr>
		      <tr>
		        <th><label for="ps_evt_bulk_lieu"><?= esc_html__( 'Lieu', 'cf-events' ) ?></label></th>
		        <td><input type="text" id="ps_evt_bulk_lieu" name="ps_evt_bulk_lieu" class="regular-text" value="<?= esc_attr( $valeurs['lieu'] ) ?>"></td>
		      </tr>
		      <tr>
		        <th><label for="ps_evt_bulk_adresse"><?= esc_html__( 'Adresse', 'cf-events' ) ?></label></th>
		        <td><input type="text" id="ps_evt_bulk_adresse" name="ps_evt_bulk_adresse" class="regular-text" value="<?= esc_attr( $valeurs['adresse'] ) ?>"></td>
		      </tr>
		      <tr>
		        <th><label for="ps_evt_bulk_ville"><?= esc_html__( 'Ville', 'cf-events' ) ?></label></th>
		        <td><input type="text" id="ps_evt_bulk_ville" name="ps_evt_bulk_ville" class="regular-text" value="<?= esc_attr( $valeurs['ville'] ) ?>"></td>
		      </tr>
		      <tr>
		        <th><label for="ps_evt_bulk_prix"><?= esc_html__( 'Tarif', 'cf-events' ) ?></label></th>
		        <td><input type="text" id="ps_evt_bulk_prix" name="ps_evt_bulk_prix" class="regular-text" placeholder="<?= esc_attr__( 'ex. 12 € ou Prix libre', 'cf-events' ) ?>" value="<?= esc_attr( $valeurs['prix'] ) ?>"></td>
		      </tr>
		      <tr>
		        <th><label for="ps_evt_bulk_max_places"><?= esc_html__( 'Places maximum', 'cf-events' ) ?></label></th>
		        <td><input type="number" min="0" id="ps_evt_bulk_max_places" name="ps_evt_bulk_max_places" value="<?= esc_attr( $valeurs['max_places'] ) ?>"> <span class="description"><?= esc_html__( 'vide ou 0 = illimité', 'cf-events' ) ?></span></td>
		      </tr>
		      <tr>
		        <th><label for="ps_evt_bulk_animateur"><?= esc_html__( 'Animé par', 'cf-events' ) ?></label></th>
		        <td><input type="text" id="ps_evt_bulk_animateur" name="ps_evt_bulk_animateur" class="regular-text" value="<?= esc_attr( $valeurs['animateur'] ) ?>"></td>
		      </tr>
		      <tr>
		        <th><label for="ps_evt_bulk_billetterie"><?= esc_html__( 'Billetterie externe', 'cf-events' ) ?></label></th>
		        <td><input type="url" id="ps_evt_bulk_billetterie" name="ps_evt_bulk_billetterie" class="regular-text" placeholder="<?= esc_attr__( 'laisser vide pour la réservation en ligne du site', 'cf-events' ) ?>" value="<?= esc_attr( $valeurs['billetterie'] ) ?>"></td>
		      </tr>
		      <tr>
		        <th><label for="ps_evt_bulk_contenu"><?= esc_html__( 'Description', 'cf-events' ) ?></label></th>
		        <td><textarea id="ps_evt_bulk_contenu" name="ps_evt_bulk_contenu" rows="4" class="large-text"><?= esc_textarea( $valeurs['contenu'] ) ?></textarea></td>
		      </tr>
		      <tr>
		        <th><label for="ps_evt_bulk_dates"><?= esc_html__( 'Dates', 'cf-events' ) ?></label></th>
		        <td>
		          <textarea id="ps_evt_bulk_dates" name="ps_evt_bulk_dates" rows="10" class="large-text" placeholder="11/09/2026&#10;25/09/2026&#10;16/10/2026" required><?= esc_textarea( $valeurs['dates'] ) ?></textarea>
		          <p class="description"><?= esc_html__( 'Une date par ligne, au format JJ/MM/AAAA (ou AAAA-MM-JJ).', 'cf-events' ) ?></p>
		        </td>
		      </tr>
		    </table>
		    <button type="submit" name="ps_evt_bulk_creer" class="button button-primary">
		      <?= esc_html__( 'Créer ces événements', 'cf-events' ) ?>
		    </button>
		  </form>
		</div>
		<?php
	}
}
