<?php
/**
 * Fiche thèmes constellations — formulaire public [cf_fiche_intake].
 * Remplace le PDF "Personnel et confidentiel, à renvoyer par mail" par un
 * envoi direct : mêmes protections anti-spam que le module Pleine Vie
 * (nonce, pot de miel, anti-flood par IP).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFI_Public {

	public static function form_shortcode( $atts ) {
		$atts = shortcode_atts( [
			'titre'  => 'Fiche thèmes constellations',
			'bouton' => 'Envoyer ma fiche',
		], $atts, 'cf_fiche_intake' );

		ob_start();
		if ( isset( $_GET['cfi_ok'] ) ) {
			echo '<div class="cfi-notice" style="background:#f0fdf4;border:1px solid #86efac;padding:16px 18px;border-radius:8px;max-width:640px;">'
				. 'Merci ! Ta fiche est bien enregistrée, en toute confidentialité. Un accusé de réception vient de t\'être envoyé par email. 🌿</div>';
			return ob_get_clean();
		}
		$sections = CFI_Fiches::sections();
		?>
		<form method="post" class="cfi-form" style="max-width:680px;">
			<?php wp_nonce_field( 'cfi_submit', 'cfi_nonce' ); ?>
			<h3 style="margin-top:0;"><?php echo esc_html( $atts['titre'] ); ?></h3>
			<p style="color:#888;font-size:13px;">Personnel et confidentiel — ces informations m'aident à préparer notre premier temps ensemble et ne sont partagées avec personne.</p>

			<div class="cfi-section cfi-section--identity">
				<div style="display:flex;gap:10px;flex-wrap:wrap;">
					<p style="flex:1;min-width:180px;"><label>Prénom *<br><input type="text" name="cfi_prenom" required style="width:100%;padding:10px;"></label></p>
					<p style="flex:1;min-width:180px;"><label>Nom *<br><input type="text" name="cfi_nom" required style="width:100%;padding:10px;"></label></p>
				</div>
				<div style="display:flex;gap:10px;flex-wrap:wrap;">
					<p style="flex:1;min-width:180px;"><label>Email *<br><input type="email" name="cfi_email" required style="width:100%;padding:10px;"></label></p>
					<p style="flex:1;min-width:180px;"><label>Téléphone<br><input type="tel" name="cfi_telephone" style="width:100%;padding:10px;"></label></p>
				</div>
			</div>

			<fieldset class="cfi-section cfi-section--activites" style="border:none;padding:16px 18px;margin:0 0 16px;">
				<legend style="font-weight:600;color:#5a3e6b;padding:0 4px;">Quelle activité te concerne ?</legend>
				<p style="color:#888;font-size:13px;margin:2px 0 10px;">Choisis-la : les questions correspondantes s'affichent juste en dessous.</p>
				<div style="display:flex;flex-wrap:wrap;gap:10px 20px;">
					<?php foreach ( CFI_Fiches::ACTIVITES as $slug => $label ) : ?>
						<label style="display:flex;align-items:center;gap:6px;font-weight:400;">
							<input type="radio" name="cfi_activite" value="<?php echo esc_attr( $slug ); ?>" class="cfi-activite-rb">
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
			<p class="cfi-activite-lock" style="display:none;color:#5a3e6b;font-size:14px;margin:0 0 16px;">
				Fiche pour : <strong class="cfi-activite-lock-label"></strong>
				— <button type="button" class="cfi-activite-lock-change" style="background:none;border:none;padding:0;color:#5a3e6b;text-decoration:underline;cursor:pointer;font:inherit;">ce n'est pas la bonne activité ?</button>
			</p>

			<?php foreach ( $sections as $section ) :
				$activites_attr = implode( ',', $section['activites'] ?? [] );
			?>
				<div class="cfi-section" data-activites="<?php echo esc_attr( $activites_attr ); ?>">
					<h4 class="cfi-section-heading"><?php echo esc_html( $section['heading'] ); ?></h4>
					<?php foreach ( $section['fields'] as $field ) : ?>
						<p class="cfi-field">
							<label>
								<?php echo esc_html( $field['label'] ); ?><?php echo 'checkbox' === $field['type'] ? '' : '<br>'; ?>
								<?php if ( 'textarea' === $field['type'] ) : ?>
									<textarea name="cfi_<?php echo esc_attr( $field['key'] ); ?>" rows="3" style="width:100%;padding:10px;"></textarea>
								<?php elseif ( 'checkbox' === $field['type'] ) : ?>
									<input type="checkbox" name="cfi_<?php echo esc_attr( $field['key'] ); ?>" value="1" style="margin-left:6px;">
								<?php else : ?>
									<input type="text" name="cfi_<?php echo esc_attr( $field['key'] ); ?>" style="width:100%;padding:10px;">
								<?php endif; ?>
							</label>
							<?php if ( ! empty( $field['hint'] ) ) : ?>
								<span class="cfi-hint"><?php echo esc_html( $field['hint'] ); ?></span>
							<?php endif; ?>
						</p>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>

			<?php /* Pot de miel anti-spam : masqué aux humains, rempli par les bots */ ?>
			<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;" tabindex="-1">
				<label>Ne pas remplir<input type="text" name="cfi_website" tabindex="-1" autocomplete="off"></label>
			</div>
			<input type="hidden" name="cfi_ts" value="<?php echo (int) time(); ?>">

			<button type="submit" style="background:#5a3e6b;color:#fff;border:none;padding:13px 28px;border-radius:6px;cursor:pointer;margin-top:10px;"><?php echo esc_html( $atts['bouton'] ); ?></button>
		</form>

		<style>
		.cfi-form .cfi-section {
			background:#faf8fb; border:1px solid #e8e0ee; border-radius:10px;
			padding:16px 20px; margin:0 0 16px;
		}
		.cfi-form .cfi-section--identity { background:#fff; border-color:#e5e5e5; }
		.cfi-form .cfi-section-heading {
			margin:0 0 12px; color:#5a3e6b; font-size:15px; font-weight:700;
			padding-bottom:8px; border-bottom:1px solid #e8e0ee;
		}
		.cfi-form .cfi-field { margin:0 0 14px; }
		.cfi-form .cfi-field:last-child { margin-bottom:0; }
		.cfi-form .cfi-hint { display:block; color:#918a9c; font-size:12px; margin-top:4px; }
		</style>
		<script>
		(function () {
			var form = document.currentScript.closest('form') || document.querySelector('.cfi-form');
			if ( ! form ) return;
			var picker    = form.querySelector('.cfi-section--activites');
			var radios    = form.querySelectorAll('.cfi-activite-rb');
			var sections  = form.querySelectorAll('.cfi-section[data-activites]');
			var lock      = form.querySelector('.cfi-activite-lock');
			var lockLabel = form.querySelector('.cfi-activite-lock-label');
			var lockBtn   = form.querySelector('.cfi-activite-lock-change');

			function selected() {
				var checked = form.querySelector('.cfi-activite-rb:checked');
				return checked ? checked.value : '';
			}

			function apply() {
				var current = selected();
				sections.forEach(function (sec) {
					var tags = (sec.dataset.activites || '').split(',').filter(Boolean);
					var show = tags.length === 0 || tags.indexOf(current) !== -1;
					sec.style.display = show ? '' : 'none';
				});
			}

			function showPicker() {
				picker.style.display = '';
				lock.style.display   = 'none';
			}

			// Pré-sélection par ancre d'URL (ex. lien "#genogramme" depuis une
			// autre page) : la question ne se pose pas, on verrouille le choix
			// et on masque le sélecteur — l'usager n'a en pratique jamais qu'une
			// seule activité à cocher, autant lui éviter l'étape quand elle est
			// déjà connue. Un lien permet de revenir en arrière si besoin.
			function lockTo( radio ) {
				radio.checked = true;
				picker.style.display  = 'none';
				lockLabel.textContent = radio.parentNode.textContent.trim();
				lock.style.display    = '';
				apply();
			}

			var hash  = ( window.location.hash || '' ).replace( '#', '' );
			var match = hash && form.querySelector('.cfi-activite-rb[value="' + hash + '"]');
			if ( match ) {
				lockTo( match );
			} else {
				apply();
			}

			radios.forEach(function (rb) { rb.addEventListener('change', apply); });
			if ( lockBtn ) lockBtn.addEventListener('click', showPicker);
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	public static function handle_form() {
		if ( empty( $_POST['cfi_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfi_nonce'] ) ), 'cfi_submit' ) ) {
			return;
		}

		// Anti-spam : pot de miel rempli, ou formulaire soumis en < 2 s → bot.
		if ( ! empty( $_POST['cfi_website'] ) ) {
			return;
		}
		$ts = isset( $_POST['cfi_ts'] ) ? absint( $_POST['cfi_ts'] ) : 0;
		if ( $ts && ( time() - $ts ) < 2 ) {
			return;
		}

		// Anti-flood : une soumission par IP toutes les 30 s max.
		$ip_key = 'cfi_throttle_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		if ( get_transient( $ip_key ) ) {
			wp_safe_redirect( add_query_arg( 'cfi_ok', '1', wp_get_referer() ?: home_url( '/' ) ) );
			exit;
		}
		set_transient( $ip_key, 1, 30 );

		// Une seule activité par fiche en pratique (voir CFI_Public::form_shortcode())
		// — stockée dans un tableau à un élément pour rester compatible avec le
		// reste du module (CFI_Fiches::add(), affichage admin), qui manipulent
		// tous _activites comme une liste.
		$activite = sanitize_key( wp_unslash( $_POST['cfi_activite'] ?? '' ) );
		$data     = [
			'prenom'     => wp_unslash( $_POST['cfi_prenom']    ?? '' ),
			'nom'        => wp_unslash( $_POST['cfi_nom']       ?? '' ),
			'email'      => wp_unslash( $_POST['cfi_email']     ?? '' ),
			'telephone'  => wp_unslash( $_POST['cfi_telephone'] ?? '' ),
			'_activites' => $activite ? [ $activite ] : [],
		];

		foreach ( CFI_Fiches::sections() as $section ) {
			// Une section liée à une/des activité(s) non cochées est masquée
			// côté client (CSS) mais ses champs restent dans le DOM — sans ce
			// filtre, des réponses saisies puis abandonnées (case décochée
			// avant l'envoi) seraient quand même enregistrées et transmises
			// au génogramme/à l'IA. Les sections communes (activites vide)
			// restent toujours prises en compte.
			$section_activites = $section['activites'] ?? [];
			if ( $section_activites && ! array_intersect( $section_activites, $data['_activites'] ) ) {
				continue;
			}
			foreach ( $section['fields'] as $field ) {
				$post_key = 'cfi_' . $field['key'];
				if ( 'checkbox' === $field['type'] ) {
					$data[ $field['key'] ] = ! empty( $_POST[ $post_key ] ) ? 1 : 0;
				} elseif ( 'textarea' === $field['type'] ) {
					$data[ $field['key'] ] = sanitize_textarea_field( wp_unslash( $_POST[ $post_key ] ?? '' ) );
				} else {
					$data[ $field['key'] ] = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ?? '' ) );
				}
			}
		}
		$data['prenom']    = sanitize_text_field( $data['prenom'] );
		$data['nom']       = sanitize_text_field( $data['nom'] );
		$data['email']     = sanitize_email( $data['email'] );
		$data['telephone'] = sanitize_text_field( $data['telephone'] );

		$id = CFI_Fiches::add( $data );
		if ( is_wp_error( $id ) || ! $id ) {
			return; // email invalide : pas de redirection, le champ required rejouera
		}

		$row = CFI_Fiches::get( $id );
		if ( $row ) {
			CFI_Emails::notify_admin( $row );
			CFI_Emails::send_ack( $row );
		}

		wp_safe_redirect( add_query_arg( 'cfi_ok', '1', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}
}
