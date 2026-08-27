<?php
/**
 * Bibliothèques de newsletters prêtes à l'emploi.
 *
 * Deux collections :
 *  - « annuel »   : les 12 éditions mensuelles (dossier library/), utilisées
 *                   aussi par « Programmer toute l'année ».
 *  - « articles » : un brouillon par article de blog (dossier
 *                   library-articles/), à piocher au fil de l'eau.
 *
 * Chaque édition vient d'un fichier HTML du dépôt (valeur par défaut), mais
 * reste MODIFIABLE depuis l'admin : les textes retouchés sont enregistrés
 * dans l'option `cfnl_library_custom` et prennent le pas sur le fichier.
 * Le bouton « Rétablir » supprime la version modifiée et redonne celle du
 * fichier — rien n'est jamais perdu.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFNL_Library {

	/* Textes de fichier retouchés depuis l'admin (le fichier reste la base) */
	const OPTION = 'cfnl_library_custom';

	/* Textes entièrement créés depuis l'admin : ils n'ont pas de fichier
	   derrière eux, ils vivent uniquement ici. C'est leur apparition qui
	   déclenche l'automatisation « nouveau cadeau » (voir CFNL_Automations). */
	const OPTION_ADDED = 'cfnl_library_added';

	/* ── Les collections disponibles ──────────────────────────────── */
	public static function collections() {
		return [
			'annuel'   => [ 'dir' => 'library',          'label' => 'Modèles annuels (12 mois)' ],
			'articles' => [ 'dir' => 'library-articles', 'label' => 'Un mail par article' ],
		];
	}

	public static function is_collection( $col ) {
		return array_key_exists( (string) $col, self::collections() );
	}

	/* ── Textes modifiés depuis l'admin ───────────────────────────── */
	private static function overrides( $col = null ) {
		$all = get_option( self::OPTION, [] );
		if ( ! is_array( $all ) ) {
			$all = [];
		}
		if ( null === $col ) {
			return $all;
		}
		return isset( $all[ $col ] ) && is_array( $all[ $col ] ) ? $all[ $col ] : [];
	}

	/* ── Textes créés depuis l'admin ──────────────────────────────── */
	private static function added( $col = null ) {
		$all = get_option( self::OPTION_ADDED, [] );
		if ( ! is_array( $all ) ) {
			$all = [];
		}
		if ( null === $col ) {
			return $all;
		}
		return isset( $all[ $col ] ) && is_array( $all[ $col ] ) ? $all[ $col ] : [];
	}

	/* ── Liste des éditions d'une collection ──────────────────────────
	   Lues depuis les fichiers, puis écrasées par la version modifiée
	   dans l'admin si elle existe. La clé (`key`) est le nom du fichier
	   sans extension : elle reste stable même si le titre change. */
	public static function editions( $col = 'annuel' ) {
		if ( ! self::is_collection( $col ) ) {
			$col = 'annuel';
		}
		$cols  = self::collections();
		$dir   = trailingslashit( CFNL_DIR ) . $cols[ $col ]['dir'];
		$files = glob( $dir . '/*.html' );
		if ( ! $files ) {
			$files = [];
		}
		sort( $files ); // ordre 01-…, 02-…, …
		$custom = self::overrides( $col );

		$out = [];
		foreach ( $files as $file ) {
			$key = basename( $file, '.html' );
			$raw = file_get_contents( $file );
			if ( false === $raw ) {
				continue;
			}
			$meta = [ 'titre' => ucfirst( preg_replace( '/^\d+-/', '', $key ) ), 'objet' => '' ];
			$body = $raw;
			if ( preg_match( '/<!--meta\s*(\{.*?\})\s*meta-->\s*(.*)$/s', $raw, $m ) ) {
				$parsed = json_decode( $m[1], true );
				if ( is_array( $parsed ) ) {
					// Les fichiers annuels utilisent « mois », ceux par article « titre »
					if ( isset( $parsed['mois'] ) && ! isset( $parsed['titre'] ) ) {
						$parsed['titre'] = $parsed['mois'];
					}
					$meta = array_merge( $meta, $parsed );
				}
				$body = $m[2];
			}

			$ed = [
				'key'      => $key,
				'titre'    => (string) ( $meta['titre'] ?? '' ),
				'objet'    => (string) ( $meta['objet'] ?? '' ),
				'corps'    => trim( $body ),
				'cadeau'   => (string) ( $meta['cadeau'] ?? '' ),
				'article'  => (string) ( $meta['article'] ?? '' ),
				// Slug WordPress réel de l'article (souvent encodé quand le
				// titre contenait un emoji : %f0%9f%8c%99-cortisol-…)
				'post_slug' => (string) ( $meta['post_slug'] ?? '' ),
				'modifie'  => false,
			];

			// Version retouchée dans l'admin : elle gagne
			if ( isset( $custom[ $key ] ) && is_array( $custom[ $key ] ) ) {
				$c = $custom[ $key ];
				foreach ( [ 'titre', 'objet', 'corps' ] as $f ) {
					if ( isset( $c[ $f ] ) && '' !== trim( (string) $c[ $f ] ) ) {
						$ed[ $f ] = (string) $c[ $f ];
					}
				}
				$ed['modifie'] = true;
			}

			// Compat : l'ancien code lisait « mois »
			$ed['mois'] = $ed['titre'];
			$out[] = $ed;
		}

		// Textes créés directement dans l'admin : pas de fichier derrière,
		// donc rien à fusionner — ils sont édités et supprimés tels quels.
		foreach ( self::added( $col ) as $key => $a ) {
			if ( ! is_array( $a ) ) {
				continue;
			}
			$out[] = [
				'key'       => (string) $key,
				'titre'     => (string) ( $a['titre'] ?? '' ),
				'objet'     => (string) ( $a['objet'] ?? '' ),
				'corps'     => (string) ( $a['corps'] ?? '' ),
				'cadeau'    => (string) ( $a['cadeau'] ?? '' ),
				'article'   => '',
				'post_slug' => '',
				'modifie'   => false,
				'ajoute'    => true,
				'cree_le'   => (string) ( $a['cree_le'] ?? '' ),
				'mois'      => (string) ( $a['titre'] ?? '' ),
			];
		}
		return $out;
	}

	/* ── Créer un texte depuis l'admin ─────────────────────────────────
	   Renvoie la clé créée. Cette clé n'existant nulle part avant, c'est
	   elle que l'automatisation « nouveau cadeau » verra apparaître. */
	public static function create_entry( $col, $titre, $objet, $corps, $cadeau = '' ) {
		if ( ! self::is_collection( $col ) ) {
			return new WP_Error( 'bad_collection', 'Collection inconnue.' );
		}
		$titre = trim( wp_strip_all_tags( (string) $titre ) );
		if ( '' === $titre ) {
			return new WP_Error( 'no_title', 'Il faut au moins un titre.' );
		}

		// Clé stable et unique, y compris face aux fichiers existants
		$base = sanitize_title( $titre );
		if ( '' === $base ) {
			$base = 'texte';
		}
		$existing = wp_list_pluck( self::editions( $col ), 'key' );
		$key      = $base;
		$i        = 2;
		while ( in_array( $key, $existing, true ) ) {
			$key = $base . '-' . $i;
			$i++;
		}

		$all = self::added();
		if ( ! isset( $all[ $col ] ) || ! is_array( $all[ $col ] ) ) {
			$all[ $col ] = [];
		}
		$all[ $col ][ $key ] = [
			'titre'   => sanitize_text_field( $titre ),
			'objet'   => sanitize_text_field( (string) $objet ),
			'corps'   => wp_kses_post( (string) $corps ),
			'cadeau'  => sanitize_text_field( (string) $cadeau ),
			'cree_le' => current_time( 'mysql' ),
		];
		update_option( self::OPTION_ADDED, $all );
		return $key;
	}

	/* ── Supprimer un texte créé depuis l'admin ────────────────────────
	   Ne touche jamais aux textes qui viennent d'un fichier. */
	public static function delete_entry( $col, $key ) {
		$all = self::added();
		if ( ! isset( $all[ $col ][ $key ] ) ) {
			return new WP_Error( 'not_added', 'Ce texte vient d\'un fichier : il ne peut pas être supprimé ici.' );
		}
		unset( $all[ $col ][ $key ] );
		update_option( self::OPTION_ADDED, $all );
		return true;
	}

	public static function get( $col, $key ) {
		foreach ( self::editions( $col ) as $ed ) {
			if ( $ed['key'] === $key ) {
				return $ed;
			}
		}
		return null;
	}

	/* ── MailPoet est-il disponible ? ─────────────────────────────── */
	public static function mailpoet_ready() {
		global $wpdb;
		$t = $wpdb->prefix . 'mailpoet_newsletters';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t;
	}

	/* ── Corps JSON minimal accepté par l'éditeur MailPoet ────────── */
	private static function mailpoet_body( $html ) {
		$body = [
			'content' => [
				'type'        => 'container',
				'orientation' => 'vertical',
				'styles'      => [ 'block' => [ 'backgroundColor' => 'transparent' ] ],
				'blocks'      => [
					[
						'type'        => 'container',
						'orientation' => 'horizontal',
						'styles'      => [ 'block' => [ 'backgroundColor' => 'transparent' ] ],
						'blocks'      => [
							[
								'type'        => 'container',
								'orientation' => 'vertical',
								'styles'      => [ 'block' => [ 'backgroundColor' => 'transparent' ] ],
								'blocks'      => [
									[ 'type' => 'text', 'text' => $html ],
								],
							],
						],
					],
				],
			],
		];
		return wp_json_encode( $body );
	}

	/* ── Crée un brouillon dans MailPoet (insertion directe) ──────── */
	public static function create_mailpoet_draft( $key, $col = 'annuel' ) {
		global $wpdb;
		$ed = self::get( $col, $key );
		if ( ! $ed ) {
			return new WP_Error( 'bad_key', 'Édition inconnue.' );
		}
		if ( ! self::mailpoet_ready() ) {
			return new WP_Error( 'no_mailpoet', 'MailPoet n\'est pas installé sur ce site.' );
		}
		$table = $wpdb->prefix . 'mailpoet_newsletters';
		$now   = gmdate( 'Y-m-d H:i:s' );

		$data = [
			'type'       => 'standard',
			'subject'    => $ed['objet'],
			'status'     => 'draft',
			'hash'       => substr( str_replace( [ '+', '/', '=' ], '', base64_encode( wp_generate_password( 24, true, true ) ) ), 0, 20 ),
			'body'       => self::mailpoet_body( $ed['corps'] ),
			'preheader'  => '',
			'created_at' => $now,
			'updated_at' => $now,
		];

		$ok = $wpdb->insert( $table, $data );
		if ( ! $ok ) {
			return new WP_Error( 'insert_failed', 'Insertion refusée par MailPoet (format ou schéma différent). Utilisez plutôt « Copier le contenu ».' );
		}
		$new_id   = (int) $wpdb->insert_id;
		$edit_url = admin_url( 'admin.php?page=mailpoet-newsletter-editor&id=' . $new_id );
		return [ 'id' => $new_id, 'edit_url' => $edit_url ];
	}

	/* ── Programme les 12 éditions au 1er de chaque mois, 9 h ───────
	   Mois déjà passé cette année → programmé pour l'an prochain.
	   Chaque campagne reste annulable/modifiable avant sa date. */
	public static function schedule_full_year() {
		$editions = self::editions( 'annuel' );
		$now_ts   = current_time( 'timestamp' );
		$year     = (int) date_i18n( 'Y', $now_ts );
		$created  = 0;

		foreach ( $editions as $i => $ed ) {
			$month = $i + 1; // janvier = index 0
			$when  = sprintf( '%d-%02d-01 09:00:00', $year, $month );
			if ( strtotime( $when ) <= $now_ts ) {
				$when = sprintf( '%d-%02d-01 09:00:00', $year + 1, $month );
			}
			$id = CFNL_Campaigns::save( [
				'titre' => $ed['titre'],
				'objet' => $ed['objet'],
				'corps' => $ed['corps'],
				'cible' => 'both',
			] );
			if ( $id && ! is_wp_error( CFNL_Campaigns::schedule( $id, $when ) ) ) {
				$created++;
			}
		}
		return $created;
	}

	/* ── Crée une campagne dans le module Newsletter maison ───────── */
	public static function create_native_draft( $key, $col = 'annuel' ) {
		$ed = self::get( $col, $key );
		if ( ! $ed || ! class_exists( 'CFNL_Campaigns' ) ) {
			return new WP_Error( 'bad', 'Impossible.' );
		}
		$id = CFNL_Campaigns::save( [
			'titre' => $ed['titre'],
			'objet' => $ed['objet'],
			'corps' => $ed['corps'],
			'cible' => 'both',
		] );
		return $id ? [ 'id' => $id ] : new WP_Error( 'save_failed', 'Création impossible.' );
	}
}
