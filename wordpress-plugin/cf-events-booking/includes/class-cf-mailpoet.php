<?php
/**
 * Intégration MailPoet — inscription optionnelle à la newsletter
 * depuis le formulaire de réservation (case à cocher, consentement explicite).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CF_MailPoet {

	/* ── MailPoet est-il installé et actif ? ─────────────────────── */
	public static function is_available() {
		return class_exists( '\MailPoet\API\API' );
	}

	public static function is_configured() {
		return self::is_available() && (bool) get_option( 'cfeb_mailpoet_enabled', 0 ) && get_option( 'cfeb_mailpoet_list_id', '' ) !== '';
	}

	/* ── Instance de l'API MailPoet (ou null) ─────────────────────── */
	private static function api() {
		if ( ! self::is_available() ) {
			return null;
		}
		try {
			return \MailPoet\API\API::MP( 'v1' );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/* ── Toutes les listes MailPoet avec nombre d'abonnés ─────────── */
	public static function get_lists_with_counts() {
		$api = self::api();
		if ( ! $api ) {
			return [];
		}
		try {
			$lists = $api->getLists();
		} catch ( \Exception $e ) {
			return [];
		}
		if ( ! is_array( $lists ) ) {
			return [];
		}
		$out = [];
		foreach ( $lists as $list ) {
			// MailPoet renvoie un tableau associatif par liste
			$id   = isset( $list['id'] ) ? (int) $list['id'] : 0;
			$name = isset( $list['name'] ) ? (string) $list['name'] : '';
			if ( ! $id ) {
				continue;
			}
			$out[] = [
				'id'          => $id,
				'name'        => $name,
				'description' => isset( $list['description'] ) ? (string) $list['description'] : '',
				'subscribers' => self::count_subscribers( $id ),
			];
		}
		return $out;
	}

	/* ── Nombre d'abonnés confirmés+souscrits d'une liste ─────────── */
	private static function count_subscribers( int $list_id ) {
		global $wpdb;
		// Table MailPoet : wp_mailpoet_subscriber_segment (relation) filtrée sur status subscribed
		$rel  = $wpdb->prefix . 'mailpoet_subscriber_segment';
		$subs = $wpdb->prefix . 'mailpoet_subscribers';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rel ) ) !== $rel ) {
			return null; // MailPoet absent ou schéma différent : on n'affiche pas de compteur
		}
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$rel} r
			 INNER JOIN {$subs} s ON s.id = r.subscriber_id
			 WHERE r.segment_id = %d AND r.status = 'subscribed' AND s.status = 'subscribed'",
			$list_id
		) );
		return null === $count ? null : (int) $count;
	}

	/* ── Inscrit un email à la liste configurée (double opt-in MailPoet conservé) ── */
	public static function subscribe( string $email, string $prenom, string $nom ) {
		if ( ! self::is_configured() ) {
			return;
		}

		$list_id = absint( get_option( 'cfeb_mailpoet_list_id', 0 ) );
		if ( ! $list_id ) {
			return;
		}

		try {
			$mailpoet = \MailPoet\API\API::MP( 'v1' );
			$mailpoet->addSubscriber(
				[
					'email'      => sanitize_email( $email ),
					'first_name' => sanitize_text_field( $prenom ),
					'last_name'  => sanitize_text_field( $nom ),
				],
				[ $list_id ],
				[
					// MailPoet applique son propre double opt-in si activé sur le site : rien n'est envoyé sans confirmation du destinataire.
					'send_confirmation_email' => true,
				]
			);
		} catch ( \Exception $e ) {
			// Ex. email déjà inscrit à cette liste : on ignore, la réservation ne doit jamais échouer pour ça.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CF_MailPoet::subscribe — ' . $e->getMessage() );
			}
		}
	}

	/* ── Lit la case à cocher du formulaire et inscrit si demandé ──
	   Priorité au système Newsletter maison (CFNL) ; repli sur MailPoet
	   si le module maison est absent mais MailPoet configuré. */
	public static function maybe_subscribe_from_request( string $email, string $prenom, string $nom ) {
		if ( empty( $_POST['cfeb_newsletter_optin'] ) ) {
			return;
		}
		if ( class_exists( 'CFNL_Subscribers' ) ) {
			$res = CFNL_Subscribers::add( $email, $prenom, $nom, 'mensuel', 'reservation' );
			// Envoi de la confirmation double opt-in si le système l'exige
			if ( ! is_wp_error( $res ) && class_exists( 'CFNL_Public' )
				&& in_array( $res['statut'] ?? '', [ 'pending' ], true ) && ! empty( $res['token'] ) ) {
				CFNL_Public::send_confirmation( sanitize_email( $email ), $prenom, $res['token'] );
			}
			return;
		}
		self::subscribe( $email, $prenom, $nom );
	}

	/* ── La case à cocher doit s'afficher si MailPoet OU le module maison est prêt ── */
	private static function optin_available() {
		if ( class_exists( 'CFNL_Subscribers' ) ) {
			return true; // le système maison est toujours prêt
		}
		return self::is_configured();
	}

	/* ── Rendu de la section paramètres (onglet Newsletter) ──────── */
	public static function render_settings_section() {
		$enabled  = (bool) get_option( 'cfeb_mailpoet_enabled', 0 );
		$list_id  = get_option( 'cfeb_mailpoet_list_id', '' );
		$label    = get_option( 'cfeb_mailpoet_checkbox_label', 'Je souhaite aussi recevoir la newsletter (un email de temps en temps, désinscription facile).' );
		$available = self::is_available();
		?>
		<h2 style="margin-top:0;">📰 MailPoet — Poste de pilotage</h2>
		<?php if ( ! $available ) : ?>
			<div class="notice notice-warning inline"><p>MailPoet ne semble pas actif sur ce site. Installez et activez l'extension MailPoet pour utiliser cette fonctionnalité.</p></div>
		<?php else : ?>
			<?php $lists = self::get_lists_with_counts(); ?>
			<?php if ( $lists ) : ?>
				<h3 style="margin-bottom:8px;">Vos listes &amp; abonnés</h3>
				<div class="cfeb-mp-lists" style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:8px;">
					<?php foreach ( $lists as $l ) : ?>
						<div style="flex:1;min-width:180px;background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:14px 18px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
							<div style="font-size:26px;font-weight:700;color:#1a3c5e;line-height:1.1;"><?php echo null === $l['subscribers'] ? '—' : (int) $l['subscribers']; ?></div>
							<div style="font-size:13px;color:#1d2327;font-weight:600;margin-top:4px;"><?php echo esc_html( $l['name'] ); ?></div>
							<div style="font-size:11px;color:#646970;margin-top:2px;">Liste #<?php echo (int) $l['id']; ?><?php echo $l['description'] ? ' · ' . esc_html( wp_trim_words( $l['description'], 8 ) ) : ''; ?></div>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="description" style="margin-bottom:20px;">
					Copiez l'<strong>ID</strong> de la liste cible (« Mensuel — l'essentiel » de préférence) dans le champ plus bas.
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mailpoet-newsletters' ) ); ?>">Composer une newsletter dans MailPoet →</a>
				</p>
			<?php else : ?>
				<div class="notice notice-info inline"><p>Aucune liste MailPoet détectée pour l'instant. Créez vos listes « Quinzaine » et « Mensuel — l'essentiel » dans MailPoet, puis revenez ici.</p></div>
			<?php endif; ?>
		<?php endif; ?>

		<hr style="margin:20px 0;">
		<h3>Inscription automatique à la réservation</h3>
		<p>Une case à cocher facultative apparaît sur le formulaire de réservation. Si le client la coche, son email est proposé à la liste MailPoet ci-dessous — <strong>MailPoet applique ensuite son propre double opt-in</strong> si celui-ci est activé sur votre site : aucun email n'est envoyé sans confirmation du destinataire.</p>

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="cfeb_mailpoet_enabled">Activer la case à cocher</label></th>
				<td><label><input type="checkbox" name="cfeb_mailpoet_enabled" id="cfeb_mailpoet_enabled" value="1" <?php checked( $enabled ); ?> <?php disabled( ! $available ); ?>> Proposer l'inscription à la newsletter lors de la réservation</label></td>
			</tr>
			<tr>
				<th><label for="cfeb_mailpoet_list_id">ID de la liste MailPoet</label></th>
				<td>
					<input type="number" name="cfeb_mailpoet_list_id" id="cfeb_mailpoet_list_id" value="<?php echo esc_attr( $list_id ); ?>" class="small-text" min="1">
					<p class="description">Dans MailPoet → Listes, ouvrez la liste cible (ex. « Mensuel — l'essentiel ») : l'ID apparaît dans l'URL (<code>...&amp;id=<strong>3</strong></code>).</p>
				</td>
			</tr>
			<tr>
				<th><label for="cfeb_mailpoet_checkbox_label">Texte affiché sous la case</label></th>
				<td><input type="text" name="cfeb_mailpoet_checkbox_label" id="cfeb_mailpoet_checkbox_label" value="<?php echo esc_attr( $label ); ?>" class="large-text"></td>
			</tr>
		</table>
		<?php
	}

	/* ── Sauvegarde des paramètres ────────────────────────────────── */
	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 403 );
		}
		update_option( 'cfeb_mailpoet_enabled',        ! empty( $_POST['cfeb_mailpoet_enabled'] ) ? 1 : 0 );
		update_option( 'cfeb_mailpoet_list_id',        absint( $_POST['cfeb_mailpoet_list_id'] ?? 0 ) ?: '' );
		update_option( 'cfeb_mailpoet_checkbox_label', sanitize_text_field( wp_unslash( $_POST['cfeb_mailpoet_checkbox_label'] ?? '' ) ) );
	}

	/* ── Bloc HTML de la case à cocher (inséré dans les 2 formulaires) ── */
	public static function render_checkbox_field() {
		if ( ! self::optin_available() ) {
			return;
		}
		$label = get_option( 'cfeb_mailpoet_checkbox_label', 'Je souhaite aussi recevoir la newsletter (un email de temps en temps, désinscription facile).' );
		// width:auto + flex:none neutralisent la règle globale .cfeb-field
		// input{width:100%} (pensée pour les champs texte), qui étirait
		// sinon la case à cocher sur toute la largeur de la ligne et
		// repoussait le texte, cassant l'alignement avec le libellé.
		echo '<p class="cfeb-field cfeb-field-newsletter"><label style="display:flex;align-items:center;gap:8px;font-weight:normal;cursor:pointer;">'
			. '<input type="checkbox" name="cfeb_newsletter_optin" value="1" style="width:auto;flex:0 0 auto;margin:0;">'
			. '<span>' . esc_html( $label ) . '</span>'
			. '</label></p>';
	}
}
