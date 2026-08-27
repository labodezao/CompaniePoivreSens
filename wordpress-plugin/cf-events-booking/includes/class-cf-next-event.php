<?php
/**
 * Widget « Prochaines dates » — affiche automatiquement les prochains
 * créneaux à venir des types de RDV. Utilisable partout via
 * [cf_prochain_atelier]. Remplace le contenu jadis codé en dur dans la
 * barre latérale des articles (qui pointait vers l'ancien système
 * d'événements, remplacé depuis par les types de RDV à créneaux).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CF_Next_Event {

	/**
	 * IDs des types de RDV à considérer : ceux portant la catégorie réglée
	 * dans CF Réservations → Paramètres → Général (« Catégorie Groupes »),
	 * ou tous les types publiés si aucune catégorie n'est configurée.
	 */
	private static function get_type_ids() {
		if ( ! defined( 'CFEB_APPT_SLUG' ) ) {
			return [];
		}
		$slug = class_exists( 'CF_Admin' ) ? ( CF_Admin::get_options()['groupes_categorie'] ?? '' ) : '';

		$args = [
			'post_type'      => CFEB_APPT_SLUG,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
		];
		if ( $slug ) {
			$args['meta_query'] = [
				[ 'key' => '_cfeb_appt_categorie', 'value' => $slug ],
			];
		}
		$q = new WP_Query( $args );
		return $q->posts;
	}

	/* ── Shortcode [cf_prochain_atelier] ──────────────────────────── */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( [
			'titre'   => '📅 Prochaines dates',
			'lien'    => home_url( '/prendre-rdv/' ),
			'bouton'  => 'Voir les dates',
			'nombre'  => 3,
		], $atts, 'cf_prochain_atelier' );

		$occurrences = [];
		if ( class_exists( 'CF_ApptType' ) ) {
			$type_ids    = self::get_type_ids();
			$occurrences = $type_ids ? CF_ApptType::get_next_occurrences( $type_ids, max( 1, (int) $atts['nombre'] ) ) : [];
		}

		ob_start();
		?>
		<div class="cf-next-event" style="background:var(--wp--preset--color--light-bg,#f0ece4);border-radius:12px;padding:1.5rem;">
			<h3 style="font-size:1rem;font-weight:700;margin:0 0 .5rem;"><?php echo esc_html( $atts['titre'] ); ?></h3>
			<?php if ( $occurrences ) : ?>
				<ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.85rem;">
					<?php foreach ( $occurrences as $occ ) :
						$type_post = get_post( $occ['type_id'] );
						if ( ! $type_post ) {
							continue;
						}
						$m        = CF_ApptType::get_meta( $occ['type_id'] );
						$ts       = strtotime( str_replace( 'T', ' ', $occ['debut_dt'] ) );
						$ligne    = $ts ? date_i18n( 'l j F', $ts ) . ( date_i18n( 'Hi', $ts ) !== '0000' ? date_i18n( ' à H\hi', $ts ) : '' ) : '';
						// « URL de la page de réservation » du type si renseignée ET
						// distincte de l'accueil (repli explicite) ; sinon l'ancre
						// stable vers ce type sur la page de réservation — même
						// schéma que les widgets [cf_rdv_widget] — pour retomber
						// directement sur le bon type au clic plutôt que sur
						// l'accueil, qui ne mène nulle part en particulier.
						$page_url  = untrailingslashit( (string) ( $m['page_url'] ?? '' ) );
						$item_lien = ( $page_url && $page_url !== untrailingslashit( home_url( '/' ) ) )
							? $m['page_url']
							: rtrim( $atts['lien'], '/' ) . '/#cf-rdv-' . sanitize_title( $type_post->post_name );
					?>
					<li style="font-size:.9rem;line-height:1.5;">
						<?php if ( $item_lien ) : ?><a href="<?php echo esc_url( $item_lien ); ?>" style="color:inherit;text-decoration:none;display:block;"><?php endif; ?>
						<strong><?php echo esc_html( $type_post->post_title ); ?></strong><br>
						<span style="color:var(--wp--preset--color--text-muted,#6b6577);"><?php echo esc_html( ucfirst( $ligne ) ); ?></span>
						<?php if ( $item_lien ) : ?></a><?php endif; ?>
					</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p style="font-size:.9rem;line-height:1.5;margin:0 0 1rem;color:var(--wp--preset--color--text-muted,#6b6577);">Prochaines dates bientôt annoncées — écris-moi pour être prévenu·e.</p>
				<a href="<?php echo esc_url( $atts['lien'] ); ?>" class="wp-element-button" style="display:inline-block;background:var(--wp--preset--color--primary,#3f2a4d);color:#fff;font-size:.85rem;font-weight:600;padding:.6rem 1.25rem;border-radius:8px;text-decoration:none;"><?php echo esc_html( $atts['bouton'] ); ?></a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
