<?php
/**
 * Intégration SumUp — génération de liens de paiement (acompte).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CF_SumUp {

	const API_BASE = 'https://api.sumup.com/v0.1';

	/* ── Configuration ─────────────────────────────────────────── */
	public static function is_configured() {
		return ! empty( get_option( 'cfeb_sumup_token' ) )
			&& ! empty( get_option( 'cfeb_sumup_merchant_email' ) );
	}

	/* ── Types de RDV soumis à l'acompte ──────────────────────── */
	public static function selected_slugs() {
		$slugs = get_option( 'cfeb_sumup_appt_slugs', null );
		if ( is_array( $slugs ) ) {
			return array_values( array_filter( array_map( 'sanitize_title', $slugs ) ) );
		}
		// Rétro-compatibilité : ancienne option mono-slug
		$old = get_option( 'cfeb_sumup_appt_slug', 'constellation-individuelle' );
		return $old ? [ sanitize_title( $old ) ] : [];
	}

	public static function applies_to( $slug ) {
		return in_array( sanitize_title( $slug ), self::selected_slugs(), true );
	}

	/* ── Crée un checkout et retourne l'URL de paiement ───────────
	   $amount null = acompte configuré ; sinon montant libre (bons cadeaux…) */
	public static function create_checkout( int $booking_id, string $prenom, string $appt_label, $amount = null, $ref_prefix = 'acompte' ) {
		$token          = get_option( 'cfeb_sumup_token', '' );
		$merchant_email = get_option( 'cfeb_sumup_merchant_email', '' );
		$amount         = null !== $amount ? (float) $amount : (float) get_option( 'cfeb_sumup_acompte', 30 );

		if ( ! $token || ! $merchant_email ) {
			return new WP_Error( 'sumup_not_configured', 'SumUp non configuré.' );
		}

		$payload = [
			'checkout_reference' => $ref_prefix . '-' . $booking_id . '-' . time(),
			'amount'             => $amount,
			'currency'           => 'EUR',
			'pay_to_email'       => $merchant_email,
			'description'        => sprintf( '%s %.0f€ — %s — %s', ucfirst( $ref_prefix ), $amount, $appt_label, $prenom ),
		];

		$response = wp_remote_post( self::API_BASE . '/checkouts', [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( $payload ),
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 && $code !== 201 ) {
			$msg = $body['message'] ?? ( 'Erreur SumUp HTTP ' . $code );
			return new WP_Error( 'sumup_api_error', $msg );
		}

		$checkout_id = $body['id'] ?? '';
		if ( ! $checkout_id ) {
			return new WP_Error( 'sumup_no_id', 'Réponse SumUp invalide (pas de checkout ID).' );
		}

		return 'https://pay.sumup.com/b2c/' . $checkout_id;
	}

	/* ── Statut d'un checkout ('PAID', 'PENDING', 'FAILED'…) ─────── */
	public static function get_checkout_status( string $checkout_id ) {
		$token = get_option( 'cfeb_sumup_token', '' );
		if ( ! $token || ! $checkout_id ) {
			return new WP_Error( 'sumup_not_configured', 'SumUp non configuré.' );
		}
		$response = wp_remote_get( self::API_BASE . '/checkouts/' . rawurlencode( $checkout_id ), [
			'headers' => [ 'Authorization' => 'Bearer ' . $token ],
			'timeout' => 15,
		] );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $body['status'] ) ? (string) $body['status'] : new WP_Error( 'sumup_bad_response', 'Réponse SumUp invalide.' );
	}

	/* ── Rendu de la section paramètres (onglet Paiement) ────────── */
	public static function render_settings_section() {
		$token    = get_option( 'cfeb_sumup_token', '' );
		$email    = get_option( 'cfeb_sumup_merchant_email', '' );
		$amount   = get_option( 'cfeb_sumup_acompte', '30' );
		$selected = self::selected_slugs();
		$enabled  = (bool) get_option( 'cfeb_sumup_enabled', 0 );
		$types    = get_posts( [
			'post_type'   => defined( 'CFEB_APPT_SLUG' ) ? CFEB_APPT_SLUG : 'cf_appt_type',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		] );
		?>
		<h2 style="margin-top:0;">💳 SumUp — Acompte en ligne</h2>
		<p>Quand un client réserve un des types de RDV cochés ci-dessous, il est redirigé vers la page de paiement SumUp juste après sa réservation pour régler l'acompte. Le lien de paiement est aussi inclus dans son email de confirmation.</p>
		<p>Obtenez votre <strong>Personal Access Token</strong> dans votre compte SumUp → <em>Intégrations → Clés API</em>.</p>

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="cfeb_sumup_enabled">Activer l'acompte SumUp</label></th>
				<td>
					<label><input type="checkbox" name="cfeb_sumup_enabled" id="cfeb_sumup_enabled" value="1" <?php checked( $enabled ); ?>> Demander un acompte à la réservation</label>
					<p class="description">Décoché : les réservations fonctionnent normalement, sans aucun paiement.</p>
				</td>
			</tr>
			<tr>
				<th><label for="cfeb_sumup_token">Personal Access Token</label></th>
				<td>
					<input type="password" name="cfeb_sumup_token" id="cfeb_sumup_token" value="<?php echo esc_attr( $token ); ?>" class="large-text" autocomplete="new-password">
					<p class="description">Ne partagez jamais ce token. Il est stocké dans la base de données WordPress : protégez l'accès à votre hébergement et à vos sauvegardes.</p>
				</td>
			</tr>
			<tr>
				<th><label for="cfeb_sumup_merchant_email">Email de votre compte SumUp</label></th>
				<td><input type="email" name="cfeb_sumup_merchant_email" id="cfeb_sumup_merchant_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="cfeb_sumup_acompte">Montant de l'acompte (€)</label></th>
				<td>
					<input type="number" name="cfeb_sumup_acompte" id="cfeb_sumup_acompte" value="<?php echo esc_attr( $amount ); ?>" class="small-text" min="1" max="500" step="1">
					<span class="description"> € — sera déduit du montant total réglé en séance</span>
				</td>
			</tr>
			<tr>
				<th>Types de RDV soumis à l'acompte</th>
				<td>
					<?php if ( $types ) : ?>
						<?php foreach ( $types as $t ) : ?>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="cfeb_sumup_appt_slugs[]" value="<?php echo esc_attr( $t->post_name ); ?>" <?php checked( in_array( $t->post_name, $selected, true ) ); ?>>
								<?php echo esc_html( $t->post_title ); ?> <code style="font-size:11px;color:#888;"><?php echo esc_html( $t->post_name ); ?></code>
							</label>
						<?php endforeach; ?>
						<p class="description">Cochez tous les types de rendez-vous pour lesquels un acompte est demandé à la réservation.</p>
					<?php else : ?>
						<p class="description">Aucun type de rendez-vous trouvé. Créez d'abord vos types de RDV dans le plugin de réservation.</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/* ── Sauvegarde des paramètres (appelé depuis CF_Admin) ──────── */
	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 403 );
		}
		update_option( 'cfeb_sumup_enabled',         ! empty( $_POST['cfeb_sumup_enabled'] ) ? 1 : 0 );
		update_option( 'cfeb_sumup_merchant_email',  sanitize_email( wp_unslash( $_POST['cfeb_sumup_merchant_email'] ?? '' ) ) );
		update_option( 'cfeb_sumup_acompte', max( 1, absint( $_POST['cfeb_sumup_acompte'] ?? 30 ) ) );
		$raw_slugs = array_map( 'sanitize_title', (array) wp_unslash( $_POST['cfeb_sumup_appt_slugs'] ?? [] ) );
		update_option( 'cfeb_sumup_appt_slugs', array_values( array_filter( $raw_slugs ) ) );

		// Mettre à jour le token seulement s'il est renseigné (sinon conserver l'ancien)
		$raw_token = sanitize_text_field( wp_unslash( $_POST['cfeb_sumup_token'] ?? '' ) );
		if ( $raw_token ) {
			update_option( 'cfeb_sumup_token', $raw_token );
		}
	}
}
