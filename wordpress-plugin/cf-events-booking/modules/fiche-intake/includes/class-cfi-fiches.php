<?php
/**
 * Fiche thèmes constellations — définition des champs (source unique
 * utilisée pour générer le formulaire ET l'email récapitulatif) + CRUD.
 * Champs repris tels quels du PDF « Fiche thèmes constellations ».
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFI_Fiches {

	const OPTION = 'cfi_field_definitions';

	/**
	 * Activités proposées par le cabinet — sert à filtrer les sections du
	 * formulaire public selon l'activité choisie en haut de la fiche (une
	 * seule en pratique, voir CFI_Public::form_shortcode()). Une section sans
	 * activité (tableau vide) est dite « commune » : toujours affichée, quelle
	 * que soit la sélection.
	 *
	 * Pré-sélection depuis un lien externe : ajouter l'ancre correspondant à
	 * la clé à l'URL de la page contenant [cf_fiche_intake], ex.
	 * https://…/fiches-themes/#genogramme — le sélecteur d'activité est alors
	 * verrouillé automatiquement, l'usager n'a pas à le choisir lui-même.
	 */
	const ACTIVITES = [
		'pleine_vie'                   => 'Pleine Vie',
		'constellations_individuelles' => 'Constellations individuelles',
		'constellations_groupe'        => 'Constellations de groupe',
		'genogramme'                   => 'Génogramme',
		'massages'                     => 'Massages',
		'mouvement'                    => 'Ateliers en mouvement',
	];

	/**
	 * Sections et champs du formulaire — modifiables depuis Fiches thèmes →
	 * Paramètres (stockées dans l'option ci-dessus). Repli sur les champs du
	 * PDF original tant qu'aucune personnalisation n'a été enregistrée.
	 * type : 'text' | 'textarea' | 'checkbox'. 'hint' (facultatif) : courte
	 * explication affichée sous le libellé. 'activites' (facultatif) : sous-
	 * ensemble des clés de self::ACTIVITES — vide ou absent = section commune,
	 * toujours affichée.
	 */
	public static function sections(): array {
		$stored = get_option( self::OPTION );
		if ( is_array( $stored ) && ! empty( $stored ) ) {
			return $stored;
		}
		return self::default_sections();
	}

	public static function default_sections(): array {
		return [
			[
				'heading' => 'Vos coordonnées',
				'fields'  => [
					[ 'key' => 'date_naissance',      'label' => 'Date de naissance',                         'type' => 'text' ],
					[ 'key' => 'adresse',              'label' => 'Adresse',                                   'type' => 'text' ],
					[ 'key' => 'cp_ville',             'label' => 'Code postal et ville',                      'type' => 'text' ],
					[ 'key' => 'lieu_naissance',       'label' => 'Lieu de naissance',                         'type' => 'text' ],
					[ 'key' => 'fraterie',             'label' => 'Fratrie',                                   'type' => 'text' ],
					[ 'key' => 'profession',           'label' => 'Profession ou études',                      'type' => 'text' ],
					[ 'key' => 'sedentaire',           'label' => 'Sédentaire',                                'type' => 'checkbox' ],
					[ 'key' => 'situation_maritale',   'label' => 'Situation maritale',                        'type' => 'text' ],
					[ 'key' => 'enfants',              'label' => 'Enfants ?',                                 'type' => 'text' ],
					[ 'key' => 'ivg_fc',               'label' => 'IVG / FC ?',                                'type' => 'text' ],
					[ 'key' => 'relationnel_pere',     'label' => 'Relationnel côté père',                     'type' => 'textarea' ],
					[ 'key' => 'relationnel_mere',     'label' => 'Relationnel côté mère',                     'type' => 'textarea' ],
				],
			],
			[
				'heading' => 'Vos motifs',
				'fields'  => [
					[ 'key' => 'motif_principal',      'label' => 'Quel est votre principal motif de consultation ?', 'type' => 'textarea' ],
					[ 'key' => 'motifs_secondaires',   'label' => 'Y a-t-il d’autres motifs secondaires ?',           'type' => 'textarea' ],
				],
			],
			[
				'heading' => 'Votre histoire au passé',
				'fields'  => [
					[ 'key' => 'gestation',    'label' => 'Antécédents (choc émotionnel, accidents, maladies, situation complexe) durant la gestation / naissance', 'type' => 'textarea' ],
					[ 'key' => 'enfance',      'label' => 'Antécédents dans l’enfance (0-7 ans)',                'type' => 'textarea' ],
					[ 'key' => 'preado',       'label' => 'Antécédents en pré-adolescence (7-14 ans)',           'type' => 'textarea' ],
					[ 'key' => 'adolescence',  'label' => 'Événements traumatiques dans l’adolescence (14-21 ans)', 'type' => 'textarea' ],
				],
			],
			[
				'heading' => 'Votre famille',
				'fields'  => [
					[ 'key' => 'afp', 'label' => 'Antécédents familiaux côté paternel',   'type' => 'textarea' ],
					[ 'key' => 'afm', 'label' => 'Antécédents familiaux côté maternel',   'type' => 'textarea' ],
					[ 'key' => 'aff', 'label' => 'Antécédents familiaux dans la fratrie', 'type' => 'textarea' ],
				],
			],
			[
				'heading' => 'Composition familiale (pour le génogramme)',
				'fields'  => [
					[ 'key' => 'prenom_pere',            'label' => 'Prénom du père',                                                              'type' => 'text' ],
					[ 'key' => 'dates_pere',              'label' => 'Père — année de naissance (et de décès si applicable)',                       'type' => 'text', 'hint' => 'Ex : 1950, ou 1950-2015 si décédé(e).' ],
					[ 'key' => 'prenom_mere',             'label' => 'Prénom de la mère',                                                            'type' => 'text' ],
					[ 'key' => 'dates_mere',               'label' => 'Mère — année de naissance (et de décès si applicable)',                       'type' => 'text', 'hint' => 'Ex : 1950, ou 1950-2015 si décédé(e).' ],
					[ 'key' => 'prenom_gp_paternel',      'label' => 'Prénom du grand-père paternel',                                                'type' => 'text' ],
					[ 'key' => 'dates_gp_paternel',        'label' => 'Grand-père paternel — année de naissance (et de décès si applicable)',         'type' => 'text', 'hint' => 'Ex : 1925, ou 1925-1998 si décédé(e).' ],
					[ 'key' => 'prenom_gm_paternelle',    'label' => 'Prénom de la grand-mère paternelle',                                           'type' => 'text' ],
					[ 'key' => 'dates_gm_paternelle',      'label' => 'Grand-mère paternelle — année de naissance (et de décès si applicable)',       'type' => 'text', 'hint' => 'Ex : 1925, ou 1925-1998 si décédé(e).' ],
					[ 'key' => 'prenom_gp_maternel',      'label' => 'Prénom du grand-père maternel',                                                'type' => 'text' ],
					[ 'key' => 'dates_gp_maternel',        'label' => 'Grand-père maternel — année de naissance (et de décès si applicable)',         'type' => 'text', 'hint' => 'Ex : 1925, ou 1925-1998 si décédé(e).' ],
					[ 'key' => 'prenom_gm_maternelle',    'label' => 'Prénom de la grand-mère maternelle',                                           'type' => 'text' ],
					[ 'key' => 'dates_gm_maternelle',      'label' => 'Grand-mère maternelle — année de naissance (et de décès si applicable)',       'type' => 'text', 'hint' => 'Ex : 1925, ou 1925-1998 si décédé(e).' ],
					[ 'key' => 'fratrie_liste',           'label' => 'Frères et sœurs, un par ligne : prénom, année de naissance si connue (ex. Marie, 1988)', 'type' => 'textarea' ],
					[ 'key' => 'prenom_partenaire',       'label' => 'Prénom du/de la partenaire, si applicable',                                   'type' => 'text' ],
					[ 'key' => 'enfants_liste',           'label' => 'Enfants, un par ligne : prénom, année de naissance si connue',                'type' => 'textarea' ],
					[ 'key' => 'qualite_relations',       'label' => 'Qualité des relations dans la famille (proches, distantes, conflictuelles, coupures...) et avec qui', 'type' => 'textarea' ],
					[ 'key' => 'evenements_significatifs','label' => 'Événements marquants (deuils, séparations, maladies graves, addictions, violences...) et qui ils concernent', 'type' => 'textarea' ],
				],
			],
			[
				'heading' => 'Votre histoire au présent — Santé',
				'fields'  => [
					[ 'key' => 'activites_physiques',  'label' => 'Activités physiques',                'type' => 'text' ],
					[ 'key' => 'loisirs',              'label' => 'Loisirs',                             'type' => 'text' ],
					[ 'key' => 'vacances_jours',       'label' => 'Nombre de jours de vacances par an',  'type' => 'text' ],
					[ 'key' => 'satisfaction',         'label' => 'Satisfaction (1-10)',                 'type' => 'text' ],
					[ 'key' => 'traitements_cours',    'label' => 'Traitements en cours',                'type' => 'text' ],
					[ 'key' => 'traitements_passes',   'label' => 'Traitements passés',                  'type' => 'text' ],
					[ 'key' => 'intolerances',         'label' => 'Intolérances',                        'type' => 'text' ],
					[ 'key' => 'allergies',            'label' => 'Allergies',                           'type' => 'text' ],
					[ 'key' => 'drogues',              'label' => 'Consommation de drogues',             'type' => 'text' ],
					[ 'key' => 'alcool',               'label' => 'Consommation d’alcool',               'type' => 'text' ],
					[ 'key' => 'addictions',           'label' => 'Addictions',                          'type' => 'text' ],
					[ 'key' => 'nutrition',            'label' => 'Qualité de la nutrition (1-10)',      'type' => 'text' ],
				],
			],
			[
				'heading'   => 'Identité complète (pour préparer le génogramme)',
				'activites' => [ 'genogramme' ],
				'fields'    => [
					[ 'key' => 'nom_complet',          'label' => 'Nom complet, y compris deuxième(s) prénom(s)', 'type' => 'text', 'hint' => 'Connaître le nom complet aide à situer précisément chacun dans l’arbre.' ],
					[ 'key' => 'surnoms',              'label' => 'Avez-vous, ou vos proches ont-ils, des surnoms ?', 'type' => 'text', 'hint' => 'Certaines personnes sont connues dans la famille sous un autre nom que leur nom officiel.' ],
					[ 'key' => 'origines_familiales',  'label' => 'Quelles sont vos origines, d’où vient votre famille ?', 'type' => 'textarea' ],
				],
			],
			[
				'heading'   => 'Famille immédiate — précisions',
				'activites' => [ 'genogramme' ],
				'fields'    => [
					[ 'key' => 'naissance_parents',    'label' => 'Où et quand vos parents sont-ils nés ?', 'type' => 'textarea' ],
					[ 'key' => 'mariage_parents',      'label' => 'Date de mariage de vos parents',        'type' => 'text' ],
					[ 'key' => 'photos_familiales',    'label' => 'Avez-vous des photos de famille anciennes ?', 'type' => 'text', 'hint' => 'Pas besoin de les apporter tout de suite : juste savoir si elles existent.' ],
				],
			],
			[
				'heading'   => 'Dates et événements clés',
				'activites' => [ 'genogramme' ],
				'fields'    => [
					[ 'key' => 'mariage_client',           'label' => 'Quand et où avez-vous été marié(e) (si applicable) ?', 'type' => 'text' ],
					[ 'key' => 'depart_lieu_naissance',     'label' => 'Quand avez-vous quitté votre lieu de naissance ?',     'type' => 'text' ],
					[ 'key' => 'amis_voisins_famille',      'label' => 'Amis ou voisins proches de la famille',                'type' => 'textarea', 'hint' => 'Ils font parfois partie de l’histoire familiale sans en être.' ],
					[ 'key' => 'souvenirs_historiques',     'label' => 'Souvenirs d’événements historiques marquants pour la famille', 'type' => 'textarea' ],
				],
			],
			[
				'heading'   => 'Jeunesse',
				'activites' => [ 'genogramme' ],
				'fields'    => [
					[ 'key' => 'passetemps_jeunesse',  'label' => 'Passe-temps et intérêts dans votre jeunesse', 'type' => 'text' ],
					[ 'key' => 'ecole_etudes',         'label' => 'Où avez-vous été à l’école, où avez-vous étudié ?', 'type' => 'text' ],
					[ 'key' => 'anecdotes_jeunesse',   'label' => 'Anecdotes amusantes ou marquantes de votre jeunesse', 'type' => 'textarea' ],
					[ 'key' => 'documents_conserves',  'label' => 'Avez-vous conservé des lettres, journaux intimes ou documents personnels de famille ?', 'type' => 'text' ],
					[ 'key' => 'amis_enfance',         'label' => 'Prénoms de vos amis d’enfance',              'type' => 'text' ],
				],
			],
			[
				'heading'   => 'Climat familial et non-dits',
				'activites' => [ 'genogramme' ],
				'fields'    => [
					[ 'key' => 'metiers_famille',          'label' => 'Métiers exercés dans la famille (vous et vos proches)', 'type' => 'textarea' ],
					[ 'key' => 'exils_guerres_accidents',  'label' => 'Exils, guerres, accidents ou faillites marquants dans la famille', 'type' => 'textarea' ],
					[ 'key' => 'secrets_famille',          'label' => 'Secrets connus ou supposés dans la famille', 'type' => 'textarea', 'hint' => 'Reste strictement confidentiel — utile seulement pour comprendre ce qui circule (ou pas) dans le système familial.' ],
					[ 'key' => 'exclusions_famille',       'label' => 'Personnes exclues ou mises à l’écart dans la famille', 'type' => 'textarea' ],
					[ 'key' => 'liens_ruptures',           'label' => 'Liens particulièrement forts, ruptures ou absences marquantes', 'type' => 'textarea' ],
				],
			],
			[
				'heading'   => 'Massages — préparation',
				'activites' => [ 'massages' ],
				'fields'    => [
					[ 'key' => 'antecedents_massage',  'label' => 'Antécédents médicaux, douleurs ou zones sensibles à connaître avant la séance', 'type' => 'textarea' ],
					[ 'key' => 'contre_indications',   'label' => 'Contre-indications (grossesse, chirurgie récente, problèmes de peau, allergies aux huiles...)', 'type' => 'textarea' ],
					[ 'key' => 'zones_eviter',          'label' => 'Zones du corps à éviter ou à privilégier',   'type' => 'text' ],
					[ 'key' => 'rapport_toucher',       'label' => 'Rapport au toucher : préférences ou appréhensions', 'type' => 'textarea' ],
					[ 'key' => 'attentes_massage',      'label' => 'Qu’attendez-vous de cette séance ?',          'type' => 'textarea' ],
				],
			],
			[
				'heading'   => 'Ateliers en mouvement — préparation',
				'activites' => [ 'mouvement' ],
				'fields'    => [
					[ 'key' => 'limitations_physiques', 'label' => 'Limitations physiques, douleurs ou blessures à prendre en compte', 'type' => 'textarea' ],
					[ 'key' => 'pratique_corporelle',   'label' => 'Pratiquez-vous déjà une activité corporelle régulière (yoga, danse, sport) ?', 'type' => 'text' ],
					[ 'key' => 'rapport_au_corps',      'label' => 'Comment décririez-vous votre rapport à votre corps aujourd’hui ?', 'type' => 'textarea' ],
					[ 'key' => 'intention_atelier',     'label' => 'Qu’aimeriez-vous explorer ou faire bouger à travers cet atelier ?', 'type' => 'textarea' ],
				],
			],
		];
	}

	/**
	 * Sanitize + enregistre une structure de sections soumise depuis la page
	 * Paramètres (tableau brut issu de $_POST). Ignore les sections sans
	 * titre et les champs sans libellé. Une clé de champ manquante (nouveau
	 * champ ajouté côté client) est générée depuis le libellé ; une clé déjà
	 * connue est conservée telle quelle pour ne jamais perdre le lien avec
	 * les données déjà enregistrées sous cette clé.
	 */
	public static function save_sections( array $raw ): array {
		$used_keys = [];
		$clean     = [];

		foreach ( $raw as $section ) {
			$heading = sanitize_text_field( $section['heading'] ?? '' );
			if ( '' === $heading ) {
				continue;
			}
			$fields = [];
			foreach ( (array) ( $section['fields'] ?? [] ) as $field ) {
				$label = sanitize_text_field( $field['label'] ?? '' );
				if ( '' === $label ) {
					continue;
				}
				$type = in_array( $field['type'] ?? '', [ 'text', 'textarea', 'checkbox' ], true ) ? $field['type'] : 'text';
				$key  = sanitize_key( $field['key'] ?? '' );
				if ( '' === $key ) {
					$key = sanitize_key( sanitize_title( $label ) );
				}
				// Évite les collisions (champ renommé vers une clé déjà prise,
				// ou deux nouveaux champs au même libellé).
				$base = $key;
				$i    = 2;
				while ( '' === $key || isset( $used_keys[ $key ] ) ) {
					$key = $base . '_' . $i++;
				}
				$used_keys[ $key ] = true;

				$hint = sanitize_text_field( $field['hint'] ?? '' );

				$fields[] = [ 'key' => $key, 'label' => $label, 'type' => $type, 'hint' => $hint ];
			}
			if ( empty( $fields ) ) {
				continue;
			}
			$activites = array_values( array_intersect( (array) ( $section['activites'] ?? [] ), array_keys( self::ACTIVITES ) ) );
			$clean[] = [ 'heading' => $heading, 'activites' => $activites, 'fields' => $fields ];
		}

		update_option( self::OPTION, $clean );
		return $clean;
	}

	public static function reset_sections() {
		delete_option( self::OPTION );
	}

	/** Liste à plat de toutes les clés de champs valides (hors identité). */
	public static function field_keys(): array {
		$keys = [];
		foreach ( self::sections() as $section ) {
			foreach ( $section['fields'] as $field ) {
				$keys[] = $field['key'];
			}
		}
		return $keys;
	}

	public static function table() {
		return CFI_Install::table();
	}

	/**
	 * Enregistre une fiche. $data doit contenir prenom/nom/email/telephone
	 * + les clés de field_keys() (valeurs déjà sanitizées par l'appelant).
	 */
	public static function add( array $data ) {
		global $wpdb;
		$email = sanitize_email( $data['email'] ?? '' );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', 'Adresse email invalide.' );
		}

		$donnees = [];
		foreach ( self::field_keys() as $key ) {
			$donnees[ $key ] = $data[ $key ] ?? '';
		}
		// Activités cochées par le client en haut du formulaire (voir
		// CFI_Public) — pas un champ de section, stocké à part dans le même
		// blob pour savoir quelles sections lui montrer si la fiche est
		// rouverte/modifiée, et donner du contexte à l'IA le cas échéant.
		$donnees['_activites'] = array_values( array_intersect( (array) ( $data['_activites'] ?? [] ), array_keys( self::ACTIVITES ) ) );

		$ok = $wpdb->insert( self::table(), [
			'prenom'    => sanitize_text_field( $data['prenom'] ?? '' ),
			'nom'       => sanitize_text_field( $data['nom'] ?? '' ),
			'email'     => $email,
			'telephone' => sanitize_text_field( $data['telephone'] ?? '' ),
			'donnees'   => wp_json_encode( $donnees ),
			'vu'        => 0,
			'cree_le'   => current_time( 'mysql' ),
		] );

		if ( false === $ok ) {
			return new WP_Error( 'db_insert', 'Enregistrement impossible pour le moment.' );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Met à jour une fiche existante (édition admin — ex. compléter des notes
	 * après un échange). Même forme de $data que add().
	 */
	public static function update( int $id, array $data ) {
		global $wpdb;
		$existing = self::get( $id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', 'Fiche introuvable.' );
		}

		$donnees = $existing->donnees_a ?? [];
		foreach ( self::field_keys() as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$donnees[ $key ] = $data[ $key ];
			}
		}

		$ok = $wpdb->update( self::table(), [
			'prenom'    => sanitize_text_field( $data['prenom']    ?? $existing->prenom ),
			'nom'       => sanitize_text_field( $data['nom']       ?? $existing->nom ),
			'email'     => isset( $data['email'] ) && is_email( $data['email'] ) ? sanitize_email( $data['email'] ) : $existing->email,
			'telephone' => sanitize_text_field( $data['telephone'] ?? $existing->telephone ),
			'donnees'   => wp_json_encode( $donnees ),
		], [ 'id' => $id ] );

		return false !== $ok;
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', absint( $id ) ) );
		if ( $row ) {
			$decoded        = json_decode( $row->donnees, true );
			$row->donnees_a = is_array( $decoded ) ? $decoded : [];
		}
		return $row;
	}

	public static function all( $limit = 200 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d', $limit
		) );
	}

	public static function mark_vu( $id ) {
		global $wpdb;
		return (bool) $wpdb->update( self::table(), [ 'vu' => 1 ], [ 'id' => absint( $id ) ] );
	}

	/** Toutes les fiches d'un email, plus récentes d'abord — pour l'espace Clients. */
	public static function for_email( string $email ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE email = %s ORDER BY cree_le DESC', sanitize_email( $email )
		) );
	}
}
