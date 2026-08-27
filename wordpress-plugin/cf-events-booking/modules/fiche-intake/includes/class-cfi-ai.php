<?php
/**
 * Extraction IA (Google Gemini, API gratuite) des relations/marqueurs/notes/
 * événements du génogramme depuis les champs qualitatifs de la fiche thème
 * (texte libre, pas exploitable par un simple regex). Uniquement appelée
 * depuis le pont admin (CFI_Admin::open_genogramme) — jamais sur le lien
 * envoyé au client dans l'accusé de réception, pour que tout ce qui est
 * proposé (marqueurs, notes, événements...) soit relu par Ewen dans l'outil
 * avant d'être vu par qui que ce soit d'autre.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFI_AI {

	const REL_TYPES = [
		'married', 'cohabiting', 'engaged', 'separated', 'divorced', 'affair',
		'parent_child', 'adopted', 'foster', 'twins_mono', 'twins_di',
		'close', 'strong', 'enmeshed', 'distant', 'strained', 'conflictual',
		'violence_abuse', 'overprotective', 'cutoff', 'fused_conflictual',
	];

	const MARKER_TYPES = [
		'violence', 'alcohol', 'drugs', 'mental', 'abuse', 'prison', 'suicide', 'illness',
		'resilience', 'resource', 'support', 'spiritual', 'creative',
	];

	/** Types d'événements de la frise chronologique du génogramme (EVENT_TYPES côté JS). */
	const EVENT_TYPES = [
		'birth', 'death', 'separation', 'migration', 'crisis', 'illness',
		'placement', 'recovery', 'other',
	];

	/**
	 * Champs qualitatifs de la fiche envoyés à l'IA comme contexte, groupés
	 * par section pour l'aider à raisonner — tout ce qui est narratif/
	 * systémique. Volontairement exclus : champs déjà exploités tels quels
	 * par CFI_Genogramme::build_preset() (prénoms, dates, listes fratrie/
	 * enfants, profession) et champs purement logistiques (adresse, code
	 * postal, sédentarité, notes chiffrées 1-10...) qui n'apportent rien à
	 * un génogramme.
	 */
	const CONTEXT_FIELDS = [
		'Coordonnées et situation'            => [
			'fraterie'           => 'Fratrie (description libre)',
			'situation_maritale' => 'Situation maritale',
			'enfants'            => 'Enfants',
			'ivg_fc'             => 'IVG / fausse(s) couche(s)',
			'relationnel_pere'   => 'Relationnel côté père',
			'relationnel_mere'   => 'Relationnel côté mère',
		],
		'Motifs de consultation'              => [
			'motif_principal'    => 'Motif principal',
			'motifs_secondaires' => 'Motifs secondaires',
		],
		'Histoire personnelle, par âge'       => [
			'gestation'   => 'Gestation / naissance',
			'enfance'     => 'Enfance (0-7 ans)',
			'preado'      => 'Pré-adolescence (7-14 ans)',
			'adolescence' => 'Adolescence (14-21 ans)',
		],
		'Antécédents familiaux'               => [
			'afp' => 'Côté paternel',
			'afm' => 'Côté maternel',
			'aff' => 'Dans la fratrie',
		],
		'Composition familiale et relations'  => [
			'qualite_relations'        => 'Qualité des relations dans la famille',
			'evenements_significatifs' => 'Événements marquants',
		],
		'Identité et origines'                => [
			'origines_familiales'   => 'Origines familiales',
			'naissance_parents'     => 'Naissance des parents (où/quand)',
			'mariage_parents'       => 'Mariage des parents',
			'mariage_client'        => 'Mariage du/de la client(e)',
			'depart_lieu_naissance' => 'Départ du lieu de naissance',
			'amis_voisins_famille'  => 'Amis/voisins proches de la famille',
			'souvenirs_historiques' => 'Souvenirs historiques marquants',
		],
		'Jeunesse'                             => [
			'passetemps_jeunesse' => 'Passe-temps de jeunesse',
			'ecole_etudes'        => 'École / études',
			'anecdotes_jeunesse'  => 'Anecdotes de jeunesse',
			'documents_conserves' => 'Documents de famille conservés',
			'amis_enfance'        => "Amis d'enfance",
		],
		'Climat familial et non-dits'         => [
			'metiers_famille'         => 'Métiers dans la famille',
			'exils_guerres_accidents' => 'Exils, guerres, accidents ou faillites',
			'secrets_famille'         => 'Secrets connus ou supposés',
			'exclusions_famille'      => 'Personnes exclues ou mises à l’écart',
			'liens_ruptures'          => 'Liens forts, ruptures ou absences',
		],
		'Santé (signal pour marqueurs)'        => [
			'drogues'    => 'Consommation de drogues',
			'alcool'     => "Consommation d'alcool",
			'addictions' => 'Addictions',
		],
	];

	public static function is_configured(): bool {
		return (bool) get_option( 'cfi_ai_enabled', 0 ) && '' !== trim( (string) get_option( 'cfi_ai_gemini_key', '' ) );
	}

	/* ── Rendu de la section paramètres (onglet IA) ──────────────── */
	public static function render_settings_section() {
		$enabled = (bool) get_option( 'cfi_ai_enabled', 0 );
		$key     = get_option( 'cfi_ai_gemini_key', '' );
		$model   = get_option( 'cfi_ai_gemini_model', 'gemini-flash-latest' );
		?>
		<h2 style="margin-top:0;">🤖 Extraction IA — Génogramme</h2>
		<p>Quand tu ouvres une fiche thème dans le génogramme (bouton « 🌳 Ouvrir dans le génogramme »), l'IA lit quasiment toutes les réponses qualitatives de la fiche pour proposer des relations, marqueurs (deuil, rupture, addiction...), notes par personne et repères chronologiques — <strong>toujours à vérifier/corriger dans l'outil</strong>, jamais appliqué automatiquement sur le lien envoyé au client.</p>
		<p>Clé API gratuite sur <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer">Google AI Studio</a> (compte Google, aucune carte bancaire requise).</p>

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="cfi_ai_enabled">Activer l'extraction IA</label></th>
				<td>
					<label><input type="checkbox" name="cfi_ai_enabled" id="cfi_ai_enabled" value="1" <?php checked( $enabled ); ?>> Proposer des relations/marqueurs/notes/événements à l'ouverture d'une fiche dans le génogramme</label>
					<p class="description">Décoché : le génogramme se pré-remplit normalement (parents, fratrie, enfants...), sans passer par l'IA.</p>
				</td>
			</tr>
			<tr>
				<th><label for="cfi_ai_gemini_key">Clé API Gemini</label></th>
				<td>
					<input type="password" name="cfi_ai_gemini_key" id="cfi_ai_gemini_key" value="<?php echo esc_attr( $key ); ?>" class="large-text" autocomplete="new-password">
					<p class="description">Stockée dans la base de données WordPress — ne la partage jamais, protège l'accès à ton hébergement.</p>
				</td>
			</tr>
			<tr>
				<th><label for="cfi_ai_gemini_model">Modèle</label></th>
				<td>
					<input type="text" name="cfi_ai_gemini_model" id="cfi_ai_gemini_model" value="<?php echo esc_attr( $model ); ?>" class="regular-text">
					<p class="description">Par défaut <code>gemini-flash-latest</code> (alias toujours à jour, gratuit). À changer seulement si Google recommande un autre nom de modèle.</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/* ── Sauvegarde (appelé depuis CF_Admin) ─────────────────────── */
	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 403 );
		}
		update_option( 'cfi_ai_enabled', ! empty( $_POST['cfi_ai_enabled'] ) ? 1 : 0 );
		update_option( 'cfi_ai_gemini_model', sanitize_text_field( wp_unslash( $_POST['cfi_ai_gemini_model'] ?? '' ) ) ?: 'gemini-flash-latest' );

		// Mettre à jour la clé seulement si renseignée (sinon conserver l'ancienne).
		$raw_key = sanitize_text_field( wp_unslash( $_POST['cfi_ai_gemini_key'] ?? '' ) );
		if ( $raw_key ) {
			update_option( 'cfi_ai_gemini_key', $raw_key );
		}
	}

	/**
	 * Construit le bloc de contexte texte envoyé à l'IA à partir de
	 * CONTEXT_FIELDS — une section par titre, une ligne par réponse non
	 * vide. Les sections entièrement vides sont omises.
	 */
	private static function build_context( array $donnees ): string {
		$blocks = [];
		foreach ( self::CONTEXT_FIELDS as $section => $fields ) {
			$lines = [];
			foreach ( $fields as $key => $label ) {
				$val = trim( (string) ( $donnees[ $key ] ?? '' ) );
				if ( '' !== $val ) {
					$lines[] = $label . ' : ' . sanitize_textarea_field( $val );
				}
			}
			if ( $lines ) {
				$blocks[] = "## {$section}\n" . implode( "\n", $lines );
			}
		}
		return implode( "\n\n", $blocks );
	}

	/**
	 * Enrichit un preset déjà construit (CFI_Genogramme::build_preset()) à
	 * partir de (quasi) tout le texte libre de la fiche : relations,
	 * marqueurs, notes par personne, détails structurés (métier, origine...)
	 * et événements de la frise chronologique. Ne modifie rien si l'IA
	 * n'est pas configurée, si le contexte est vide, ou en cas d'erreur
	 * d'appel — le preset structurel (parents, fratrie...) reste toujours
	 * utilisable seul.
	 */
	public static function enrich_preset( array $preset, array $donnees ): array {
		if ( ! self::is_configured() ) {
			return $preset;
		}
		$context = self::build_context( $donnees );
		if ( '' === $context ) {
			return $preset;
		}

		$result = self::call_gemini( $context, $preset['members'] ?? [] );
		if ( is_wp_error( $result ) ) {
			$preset['ai_error'] = $result->get_error_message();
			return $preset;
		}

		$valid_keys = [];
		foreach ( $preset['members'] ?? [] as $m ) {
			$valid_keys[ $m['key'] ] = true;
		}

		foreach ( (array) ( $result['relationships'] ?? [] ) as $r ) {
			$from = (string) ( $r['from'] ?? '' );
			$to   = (string) ( $r['to'] ?? '' );
			$type = (string) ( $r['type'] ?? '' );
			if ( isset( $valid_keys[ $from ], $valid_keys[ $to ] ) && in_array( $type, self::REL_TYPES, true ) ) {
				$preset['relationships'][] = [ 'from' => $from, 'to' => $to, 'type' => $type ];
			}
		}

		$markers_by_member = [];
		foreach ( (array) ( $result['markers'] ?? [] ) as $m ) {
			$key    = (string) ( $m['member_key'] ?? '' );
			$marker = (string) ( $m['marker'] ?? '' );
			if ( isset( $valid_keys[ $key ] ) && in_array( $marker, self::MARKER_TYPES, true ) ) {
				$markers_by_member[ $key ][ $marker ] = true;
			}
		}

		// notes/occupation/ethnicity/familyRole : indexés par membre pour un
		// seul passage sur preset['members'] plutôt qu'un par catégorie.
		$notes_by_member   = [];
		foreach ( (array) ( $result['member_notes'] ?? [] ) as $n ) {
			$key   = (string) ( $n['member_key'] ?? '' );
			$notes = trim( (string) ( $n['notes'] ?? '' ) );
			if ( isset( $valid_keys[ $key ] ) && '' !== $notes ) {
				$notes_by_member[ $key ] = sanitize_textarea_field( $notes );
			}
		}
		$details_by_member = [];
		foreach ( (array) ( $result['member_details'] ?? [] ) as $det ) {
			$key = (string) ( $det['member_key'] ?? '' );
			if ( ! isset( $valid_keys[ $key ] ) ) {
				continue;
			}
			foreach ( [ 'occupation', 'ethnicity', 'familyRole' ] as $field ) {
				$val = trim( (string) ( $det[ $field ] ?? '' ) );
				if ( '' !== $val ) {
					$details_by_member[ $key ][ $field ] = sanitize_text_field( $val );
				}
			}
		}

		if ( $markers_by_member || $notes_by_member || $details_by_member ) {
			foreach ( $preset['members'] as &$member ) {
				$key = $member['key'];
				if ( isset( $markers_by_member[ $key ] ) ) {
					$member['markers'] = array_merge( $member['markers'] ?? [], $markers_by_member[ $key ] );
				}
				if ( isset( $notes_by_member[ $key ] ) ) {
					// Remplace la note (plutôt que la préfixer/dupliquer) :
					// l'IA lit le même texte brut que le remplissage
					// déterministe (voir CFI_Genogramme::build_preset()) et
					// le répartit intelligemment par personne — sa version
					// est donc strictement plus utile que le paquet brut
					// posé par défaut sur ego seul.
					$member['notes'] = $notes_by_member[ $key ];
				}
				if ( isset( $details_by_member[ $key ] ) ) {
					foreach ( $details_by_member[ $key ] as $field => $val ) {
						if ( empty( $member[ $field ] ) ) {
							$member[ $field ] = $val;
						}
					}
				}
			}
			unset( $member );
		}

		$events = [];
		foreach ( (array) ( $result['events'] ?? [] ) as $ev ) {
			$type = (string) ( $ev['type'] ?? '' );
			if ( ! in_array( $type, self::EVENT_TYPES, true ) ) {
				continue;
			}
			$description = trim( (string) ( $ev['description'] ?? '' ) );
			if ( '' === $description ) {
				continue;
			}
			$member_keys = array_values( array_intersect( (array) ( $ev['member_keys'] ?? [] ), array_keys( $valid_keys ) ) );
			$events[]    = [
				'date'        => sanitize_text_field( (string) ( $ev['date'] ?? '' ) ),
				'type'        => $type,
				'description' => sanitize_textarea_field( $description ),
				'memberKeys'  => $member_keys,
			];
		}
		if ( $events ) {
			$preset['events'] = array_merge( $preset['events'] ?? [], $events );
		}

		$preset['ai_enriched'] = true;
		return $preset;
	}

	/**
	 * @param string $context Bloc de contexte texte construit par
	 *                        build_context() — toutes les réponses
	 *                        qualitatives non vides de la fiche, groupées
	 *                        par section.
	 * @param array  $members Membres déjà identifiés (build_preset()) — sert
	 *                        de vocabulaire fermé pour l'IA (jamais de
	 *                        nouveau nom inventé).
	 * @return array|WP_Error {relationships, markers, member_notes, member_details, events}
	 */
	private static function call_gemini( string $context, array $members ) {
		$api_key = trim( (string) get_option( 'cfi_ai_gemini_key', '' ) );
		$model   = trim( (string) get_option( 'cfi_ai_gemini_model', 'gemini-flash-latest' ) ) ?: 'gemini-flash-latest';

		$membres_txt = [];
		foreach ( $members as $m ) {
			$membres_txt[] = ( $m['key'] ?? '' ) . ' = ' . ( $m['name'] ?? '' );
		}

		$prompt = "Tu aides un thérapeute familial à préparer un génogramme (méthode McGoldrick) à partir d'une fiche thème remplie par son/sa client(e).\n\n"
			. "Membres déjà identifiés (clé = prénom), à utiliser TELS QUELS — n'invente jamais une nouvelle clé ou un nouveau membre :\n"
			. implode( "\n", $membres_txt ) . "\n\n"
			. "Réponses de la fiche, telles que décrites par la personne :\n\n" . $context . "\n\n"
			. "Objectif : que quasiment toutes les informations utiles de ce texte apparaissent sur le génogramme, réparties sur la bonne personne plutôt qu'entassées sur une seule. À partir de CE TEXTE UNIQUEMENT, propose :\n"
			. "1. relationships : des relations entre membres déjà identifiés ci-dessus (jamais un nouveau nom), type parmi : " . implode( ', ', self::REL_TYPES ) . ".\n"
			. "2. markers : des marqueurs à poser sur un membre déjà identifié, type parmi : " . implode( ', ', self::MARKER_TYPES ) . ".\n"
			. "3. member_notes : pour chaque membre identifié dont le texte dit quelque chose de spécifique (relationnel, métier, histoire, santé...), une courte synthèse (2-4 phrases maximum, à la 3e personne, factuelle) à afficher comme note sur sa fiche dans le génogramme. Répartis l'information sur la bonne personne — n'entasse pas tout sur ego si le texte parle clairement d'un parent, grand-parent, partenaire ou enfant précis.\n"
			. "4. member_details : pour un membre identifié, occupation/ethnicity/familyRole SEULEMENT si le texte l'indique clairement pour cette personne précise (ex. métier mentionné pour le père, origine mentionnée pour la famille).\n"
			. "5. events : les événements marquants datables ou situables dans le temps (deuils, séparations, maladies graves, migrations/exils, guerres, accidents, faillites, placements, réparations/résiliences...), avec date (année si connue, sinon une période courte comme « Enfance » ou « Adolescence », jamais inventée), type parmi : " . implode( ', ', self::EVENT_TYPES ) . ", une description courte, et les clés des membres concernés (peut être vide si l'événement concerne toute la famille).\n"
			. "Si le texte ne permet pas de déduire quoi que ce soit avec une confiance raisonnable dans une catégorie, renvoie une liste vide pour cette catégorie plutôt que de deviner. N'extrapole jamais au-delà de ce qui est écrit, et ne répète jamais la même information dans plusieurs catégories à la fois.";

		$schema = [
			'type'       => 'OBJECT',
			'properties' => [
				'relationships' => [
					'type'  => 'ARRAY',
					'items' => [
						'type'       => 'OBJECT',
						'properties' => [
							'from' => [ 'type' => 'STRING' ],
							'to'   => [ 'type' => 'STRING' ],
							'type' => [ 'type' => 'STRING', 'enum' => self::REL_TYPES ],
						],
						'required' => [ 'from', 'to', 'type' ],
					],
				],
				'markers' => [
					'type'  => 'ARRAY',
					'items' => [
						'type'       => 'OBJECT',
						'properties' => [
							'member_key' => [ 'type' => 'STRING' ],
							'marker'     => [ 'type' => 'STRING', 'enum' => self::MARKER_TYPES ],
						],
						'required' => [ 'member_key', 'marker' ],
					],
				],
				'member_notes' => [
					'type'  => 'ARRAY',
					'items' => [
						'type'       => 'OBJECT',
						'properties' => [
							'member_key' => [ 'type' => 'STRING' ],
							'notes'      => [ 'type' => 'STRING' ],
						],
						'required' => [ 'member_key', 'notes' ],
					],
				],
				'member_details' => [
					'type'  => 'ARRAY',
					'items' => [
						'type'       => 'OBJECT',
						'properties' => [
							'member_key' => [ 'type' => 'STRING' ],
							'occupation' => [ 'type' => 'STRING' ],
							'ethnicity'  => [ 'type' => 'STRING' ],
							'familyRole' => [ 'type' => 'STRING' ],
						],
						'required' => [ 'member_key' ],
					],
				],
				'events' => [
					'type'  => 'ARRAY',
					'items' => [
						'type'       => 'OBJECT',
						'properties' => [
							'date'        => [ 'type' => 'STRING' ],
							'type'        => [ 'type' => 'STRING', 'enum' => self::EVENT_TYPES ],
							'description' => [ 'type' => 'STRING' ],
							'member_keys' => [ 'type' => 'ARRAY', 'items' => [ 'type' => 'STRING' ] ],
						],
						'required' => [ 'type', 'description' ],
					],
				],
			],
			'required' => [ 'relationships', 'markers', 'member_notes', 'member_details', 'events' ],
		];

		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent',
			[
				'timeout' => 45,
				'headers' => [
					'Content-Type'   => 'application/json',
					'X-Goog-Api-Key' => $api_key,
				],
				'body' => wp_json_encode( [
					'contents'         => [ [ 'role' => 'user', 'parts' => [ [ 'text' => $prompt ] ] ] ],
					'generationConfig' => [
						'responseMimeType' => 'application/json',
						'responseSchema'   => $schema,
					],
				] ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $body ) ) {
			return new WP_Error( 'gemini_http', 'Erreur API Gemini (HTTP ' . $code . ').' );
		}

		$text    = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
		$decoded = json_decode( (string) $text, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'gemini_parse', 'Réponse IA illisible.' );
		}
		return $decoded;
	}
}
