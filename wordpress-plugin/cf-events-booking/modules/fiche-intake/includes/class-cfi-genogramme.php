<?php
/**
 * Fiche thèmes → génogramme : transforme les réponses d'une fiche (champs
 * de la section « Composition familiale ») en preset exploitable par l'app
 * Génogramme familial (format {members:[{key,...}], relationships:[{from,to,type}]}
 * — voir Genogram.applyPresetData() dans genogramme-familial/assets/js/genogramme.js).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFI_Genogramme {

	/**
	 * @param object $row Ligne de fiche (voir CFI_Fiches::get()), avec ->donnees_a.
	 * @return array{members: array, relationships: array}
	 */
	public static function build_preset( $row ): array {
		$d = is_object( $row ) ? ( $row->donnees_a ?? [] ) : [];

		$members       = [];
		$relationships = [];

		$ego_name = trim( ( $row->prenom ?? '' ) . ' ' . ( $row->nom ?? '' ) );
		$ego      = [
			'key'          => 'ego',
			'name'         => $ego_name ?: 'Consultant·e',
			'gender'       => 'unknown',
			'generation'   => 4,
			'branch'       => 'ego',
			'kinshipLabel' => 'ego',
			'subsystem'    => 'sibling',
			'isIndex'      => true,
		];
		if ( ! empty( $d['profession'] ) ) {
			$ego['occupation'] = sanitize_text_field( $d['profession'] );
		}
		if ( ! empty( $d['nom_complet'] ) ) {
			$ego['name'] = sanitize_text_field( $d['nom_complet'] );
		}
		if ( ! empty( $d['surnoms'] ) ) {
			$ego['nickname'] = sanitize_text_field( $d['surnoms'] );
		}
		/*
		 * Champs qualitatifs (texte libre) — pas de parsing automatique en
		 * marqueurs/relations pour l'instant (regex insuffisant sur du texte
		 * libre) : posés en notes sur ego pour lecture immédiate à
		 * l'ouverture, à traduire à la main dans l'outil.
		 */
		$notes = [];
		if ( ! empty( $d['qualite_relations'] ) ) {
			$notes[] = 'Qualité des relations : ' . sanitize_textarea_field( $d['qualite_relations'] );
		}
		if ( ! empty( $d['evenements_significatifs'] ) ) {
			$notes[] = 'Événements marquants : ' . sanitize_textarea_field( $d['evenements_significatifs'] );
		}
		if ( $notes ) {
			$ego['notes'] = implode( "\n\n", $notes );
		}
		$members[] = $ego;

		if ( ! empty( $d['prenom_pere'] ) ) {
			$members[] = array_merge( [
				'key'          => 'pere',
				'name'         => sanitize_text_field( $d['prenom_pere'] ),
				'gender'       => 'male',
				'generation'   => 3,
				'branch'       => 'paternal',
				'kinshipLabel' => 'père',
				'subsystem'    => 'parental',
			], self::parse_dates( $d['dates_pere'] ?? '' ) );
			$relationships[] = [ 'from' => 'pere', 'to' => 'ego', 'type' => 'parent_child' ];
		}

		if ( ! empty( $d['prenom_mere'] ) ) {
			$members[] = array_merge( [
				'key'          => 'mere',
				'name'         => sanitize_text_field( $d['prenom_mere'] ),
				'gender'       => 'female',
				'generation'   => 3,
				'branch'       => 'maternal',
				'kinshipLabel' => 'mère',
				'subsystem'    => 'parental',
			], self::parse_dates( $d['dates_mere'] ?? '' ) );
			$relationships[] = [ 'from' => 'mere', 'to' => 'ego', 'type' => 'parent_child' ];
		}

		$grandparents = [
			'gp_paternel'   => [ 'prenom_gp_paternel',   'dates_gp_paternel',   'male',   'paternal', 'pere', 'grand-père paternel' ],
			'gm_paternelle' => [ 'prenom_gm_paternelle', 'dates_gm_paternelle', 'female', 'paternal', 'pere', 'grand-mère paternelle' ],
			'gp_maternel'   => [ 'prenom_gp_maternel',   'dates_gp_maternel',   'male',   'maternal', 'mere', 'grand-père maternel' ],
			'gm_maternelle' => [ 'prenom_gm_maternelle', 'dates_gm_maternelle', 'female', 'maternal', 'mere', 'grand-mère maternelle' ],
		];
		foreach ( $grandparents as $key => [ $field, $dates_field, $gender, $branch, $parent_key, $kinship ] ) {
			if ( empty( $d[ $field ] ) ) {
				continue;
			}
			$members[] = array_merge( [
				'key'          => $key,
				'name'         => sanitize_text_field( $d[ $field ] ),
				'gender'       => $gender,
				'generation'   => 2,
				'branch'       => $branch,
				'kinshipLabel' => $kinship,
				'subsystem'    => 'intergenerational',
			], self::parse_dates( $d[ $dates_field ] ?? '' ) );
			// Relié au parent seulement si celui-ci est lui-même connu — sinon
			// le grand-parent reste un membre isolé, rattachable à la main.
			if ( ! empty( $d[ 'prenom_' . $parent_key ] ) ) {
				$relationships[] = [ 'from' => $key, 'to' => $parent_key, 'type' => 'parent_child' ];
			}
		}

		foreach ( self::parse_liste( $d['fratrie_liste'] ?? '' ) as $i => $person ) {
			$key       = 'fratrie_' . $i;
			$members[] = array_merge( [
				'key'          => $key,
				'gender'       => 'unknown',
				'generation'   => 4,
				'branch'       => 'ego',
				'kinshipLabel' => 'fratrie',
				'subsystem'    => 'sibling',
			], $person );
		}

		if ( ! empty( $d['prenom_partenaire'] ) ) {
			$members[] = [
				'key'          => 'partenaire',
				'name'         => sanitize_text_field( $d['prenom_partenaire'] ),
				'gender'       => 'unknown',
				'generation'   => 4,
				'branch'       => 'allied',
				'kinshipLabel' => 'partenaire',
				'subsystem'    => 'conjugal',
			];
			$relationships[] = [ 'from' => 'ego', 'to' => 'partenaire', 'type' => 'married' ];
		}

		foreach ( self::parse_liste( $d['enfants_liste'] ?? '' ) as $i => $person ) {
			$key       = 'enfant_' . $i;
			$members[] = array_merge( [
				'key'          => $key,
				'gender'       => 'unknown',
				'generation'   => 5,
				'branch'       => 'descendant',
				'kinshipLabel' => 'enfant',
				'subsystem'    => 'sibling',
			], $person );
			$relationships[] = [ 'from' => 'ego', 'to' => $key, 'type' => 'parent_child' ];
		}

		return [
			'members'       => $members,
			'relationships' => $relationships,
			'events'        => [],
			'client'        => [ 'email' => $row->email ?? '', 'nom' => $ego_name ?: '' ],
		];
	}

	/**
	 * Parse un champ « un par ligne : Prénom, Année » en liste de
	 * ['name' => ..., 'birthYear' => int|null], ignore les lignes vides.
	 */
	private static function parse_liste( string $raw ): array {
		$out = [];
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$name = $line;
			$year = null;
			if ( preg_match( '/^(.*?),\s*(\d{4})\s*$/', $line, $m ) ) {
				$name = trim( $m[1] );
				$year = (int) $m[2];
			}
			if ( '' === $name ) {
				continue;
			}
			$entry = [ 'name' => sanitize_text_field( $name ) ];
			if ( $year ) {
				$entry['birthYear'] = $year;
			}
			$out[] = $entry;
		}
		return $out;
	}

	/**
	 * Parse un champ « Année » ou « Année-Année » (naissance-décès).
	 * Formats acceptés (rien d'autre) : « 1950 », « 1950-2015 », « 1950 - 2015 ».
	 * Renvoie [] si le format ne correspond pas, ['birthYear'] seul si
	 * naissance uniquement, ou ['birthYear','deathYear','deceased'] si les
	 * deux années sont données — jamais de clé à null, absente sinon.
	 */
	private static function parse_dates( string $raw ): array {
		$raw = trim( $raw );
		if ( '' === $raw || ! preg_match( '/^(\d{4})(?:\s*-\s*(\d{4}))?$/', $raw, $m ) ) {
			return [];
		}
		$out = [ 'birthYear' => (int) $m[1] ];
		if ( ! empty( $m[2] ) ) {
			$out['deathYear'] = (int) $m[2];
			$out['deceased']  = true;
		}
		return $out;
	}
}
