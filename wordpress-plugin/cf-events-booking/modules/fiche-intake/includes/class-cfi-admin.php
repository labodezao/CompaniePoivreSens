<?php
/**
 * Fiche thèmes constellations — administration (consultation + édition des fiches reçues).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFI_Admin {

	private static function base_url( $args = [] ) {
		return admin_url( 'edit.php?post_type=' . CFEB_SLUG . '&page=cfi-fiches' . ( $args ? '&' . http_build_query( $args ) : '' ) );
	}

	private static function settings_url( $args = [] ) {
		return admin_url( 'edit.php?post_type=' . CFEB_SLUG . '&page=cfi-fiches-settings' . ( $args ? '&' . http_build_query( $args ) : '' ) );
	}

	public static function register_menu() {
		add_submenu_page( 'edit.php?post_type=' . CFEB_SLUG, 'Fiches thèmes', 'Fiches thèmes', 'manage_options', 'cfi-fiches', [ __CLASS__, 'page' ] );
		add_submenu_page( 'edit.php?post_type=' . CFEB_SLUG, 'Fiches thèmes — Paramètres', 'Fiches thèmes — Paramètres', 'manage_options', 'cfi-fiches-settings', [ __CLASS__, 'page_settings' ] );
	}

	public static function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['cfi_action'] ) && 'open_genogramme' === $_GET['cfi_action'] ) {
			$fiche_id = absint( $_GET['fiche_id'] ?? 0 );
			check_admin_referer( 'cfi_open_genogramme_' . $fiche_id );

			$row = $fiche_id ? CFI_Fiches::get( $fiche_id ) : null;
			if ( ! $row ) {
				wp_die( 'Fiche introuvable.' );
			}

			/*
			 * Sur un plugin génogramme à jour : ouvre l'enregistrement
			 * persistant associé à l'email de la fiche (le même que celui
			 * du lien envoyé au client dans l'accusé de réception), plutôt
			 * qu'un pont éphémère isolé — pré-rempli une première fois,
			 * jamais écrasé si déjà entamé.
			 */
			if ( class_exists( 'Geno_Client_Saves' ) && is_email( $row->email ) ) {
				$save   = Geno_Client_Saves::get_or_create( $row->email, trim( $row->prenom . ' ' . $row->nom ) );
				$preset = CFI_Genogramme::build_preset( $row );
				if ( class_exists( 'CFI_AI' ) ) {
					$preset = CFI_AI::enrich_preset( $preset, $row->donnees_a ?? [] );
				}
				Geno_Client_Saves::seed_preset_if_empty( (int) $save['id'], $preset );
				wp_redirect( Geno_Client_Saves::link( $save['token'] ) );
				exit;
			}

			// Repli (plugin génogramme pas encore mis à jour côté serveur) :
			// pont éphémère à usage unique, comme avant.
			$preset = CFI_Genogramme::build_preset( $row );
			if ( class_exists( 'CFI_AI' ) ) {
				$preset = CFI_AI::enrich_preset( $preset, $row->donnees_a ?? [] );
			}
			$token  = bin2hex( random_bytes( 20 ) ); // hex minuscule : survit à sanitize_key() côté lecture.
			set_transient( 'cfi_geno_import_' . $token, $preset, 10 * MINUTE_IN_SECONDS );

			wp_redirect( add_query_arg( [ 'genogramme_app' => 1, 'cfi_import' => $token ], home_url( '/' ) ) );
			exit;
		}

		if ( ! empty( $_POST['cfi_admin_save'] ) ) {
			check_admin_referer( 'cfi_admin_save' );

			$id = absint( $_POST['cfi_id'] ?? 0 );
			if ( ! $id ) {
				return;
			}

			$data = [
				'prenom'    => wp_unslash( $_POST['cfi_prenom']    ?? '' ),
				'nom'       => wp_unslash( $_POST['cfi_nom']       ?? '' ),
				'email'     => wp_unslash( $_POST['cfi_email']     ?? '' ),
				'telephone' => wp_unslash( $_POST['cfi_telephone'] ?? '' ),
			];
			foreach ( CFI_Fiches::sections() as $section ) {
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

			CFI_Fiches::update( $id, $data );
			wp_safe_redirect( self::base_url( [ 'fiche_id' => $id, 'cfi_saved' => 1 ] ) );
			exit;
		}

		if ( ! empty( $_POST['cfi_settings_save'] ) ) {
			check_admin_referer( 'cfi_settings_save' );
			CFI_Fiches::save_sections( wp_unslash( $_POST['sections'] ?? [] ) );
			wp_safe_redirect( self::settings_url( [ 'saved' => 1 ] ) );
			exit;
		}

		if ( ! empty( $_POST['cfi_settings_reset'] ) ) {
			check_admin_referer( 'cfi_settings_save' );
			CFI_Fiches::reset_sections();
			wp_safe_redirect( self::settings_url( [ 'reset' => 1 ] ) );
			exit;
		}
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$fiche_id = absint( $_GET['fiche_id'] ?? 0 );
		if ( $fiche_id ) {
			self::page_detail( $fiche_id );
			return;
		}

		$rows = CFI_Fiches::all();
		?>
		<div class="wrap">
			<h1>📋 Fiches thèmes constellations</h1>
			<p>Reçues via le formulaire <code>[cf_fiche_intake]</code>, en remplacement du PDF envoyé par email — même contenu, personnel et confidentiel. Chaque fiche reste modifiable (ex. pour y ajouter des notes prises pendant un échange).
			<a href="<?php echo esc_url( self::settings_url() ); ?>">Modifier les champs du formulaire →</a></p>
			<table class="wp-list-table widefat striped cfeb-table" style="margin-top:12px;">
				<thead>
					<tr>
						<th>Nom</th>
						<th>Email</th>
						<th>Téléphone</th>
						<th>Reçue le</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5" style="text-align:center;padding:20px;color:#888;">Aucune fiche pour l'instant.</td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><strong><?php echo esc_html( trim( $r->prenom . ' ' . $r->nom ) ); ?></strong><?php echo ! $r->vu ? ' <span style="background:#f59e0b;color:#fff;border-radius:10px;padding:1px 8px;font-size:11px;">nouveau</span>' : ''; ?></td>
						<td><?php echo esc_html( $r->email ); ?></td>
						<td><?php echo esc_html( $r->telephone ?: '—' ); ?></td>
						<td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $r->cree_le ) ) ); ?></td>
						<td><a class="button button-small" href="<?php echo esc_url( self::base_url( [ 'fiche_id' => $r->id ] ) ); ?>">Voir / modifier</a></td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function page_detail( $fiche_id ) {
		$row = CFI_Fiches::get( $fiche_id );
		if ( ! $row ) {
			echo '<div class="wrap"><h1>Fiche introuvable</h1></div>';
			return;
		}
		if ( ! $row->vu ) {
			CFI_Fiches::mark_vu( $fiche_id );
		}
		$d = $row->donnees_a ?? [];
		?>
		<div class="wrap">
			<p><a href="<?php echo esc_url( self::base_url() ); ?>">← Retour à la liste</a></p>
			<h1><?php echo esc_html( trim( $row->prenom . ' ' . $row->nom ) ); ?></h1>
			<p style="color:#888;">
				Reçue le <?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $row->cree_le ) ) ); ?>
				<?php if ( current_user_can( 'manage_options' ) ) :
					$geno_url = wp_nonce_url( self::base_url( [ 'cfi_action' => 'open_genogramme', 'fiche_id' => $row->id ] ), 'cfi_open_genogramme_' . $row->id );
				?>
					· <a class="button button-small" href="<?php echo esc_url( $geno_url ); ?>" target="_blank" rel="noopener">🌳 Ouvrir dans le génogramme</a>
				<?php endif; ?>
			</p>

			<?php $activites_choisies = array_intersect_key( CFI_Fiches::ACTIVITES, array_flip( (array) ( $d['_activites'] ?? [] ) ) ); ?>
			<?php if ( $activites_choisies ) : ?>
				<p><strong>Activités cochées :</strong> <?php echo esc_html( implode( ', ', $activites_choisies ) ); ?></p>
			<?php endif; ?>

			<?php if ( isset( $_GET['cfi_saved'] ) ) : ?>
				<div class="notice notice-success"><p>Fiche enregistrée.</p></div>
			<?php endif; ?>

			<form method="post" style="max-width:900px;">
				<?php wp_nonce_field( 'cfi_admin_save' ); ?>
				<input type="hidden" name="cfi_admin_save" value="1">
				<input type="hidden" name="cfi_id" value="<?php echo (int) $row->id; ?>">

				<table class="wp-list-table widefat striped cfeb-table" style="margin-top:12px;">
					<tbody>
					<tr><td colspan="2" style="background:#f6f3f9;font-weight:700;color:#5a3e6b;">📌 Coordonnées</td></tr>
					<tr><td style="width:320px;color:#666;">Prénom</td><td><input type="text" name="cfi_prenom" value="<?php echo esc_attr( $row->prenom ); ?>" class="regular-text"></td></tr>
					<tr><td style="color:#666;">Nom</td><td><input type="text" name="cfi_nom" value="<?php echo esc_attr( $row->nom ); ?>" class="regular-text"></td></tr>
					<tr><td style="color:#666;">Email</td><td><input type="email" name="cfi_email" value="<?php echo esc_attr( $row->email ); ?>" class="regular-text"></td></tr>
					<tr><td style="color:#666;">Téléphone</td><td><input type="text" name="cfi_telephone" value="<?php echo esc_attr( $row->telephone ); ?>" class="regular-text"></td></tr>

					<?php foreach ( CFI_Fiches::sections() as $section ) : ?>
						<tr><td colspan="2" style="background:#f6f3f9;font-weight:700;color:#5a3e6b;">📌 <?php echo esc_html( $section['heading'] ); ?></td></tr>
						<?php foreach ( $section['fields'] as $field ) :
							$val      = $d[ $field['key'] ] ?? '';
							$post_key = 'cfi_' . $field['key'];
						?>
						<tr>
							<td style="width:320px;color:#666;vertical-align:top;padding-top:12px;">
								<?php echo esc_html( $field['label'] ); ?>
								<?php if ( ! empty( $field['hint'] ) ) : ?>
									<br><span style="font-size:11px;color:#999;font-weight:400;"><?php echo esc_html( $field['hint'] ); ?></span>
								<?php endif; ?>
							</td>
							<td style="padding-top:8px;">
								<?php if ( 'textarea' === $field['type'] ) : ?>
									<textarea name="<?php echo esc_attr( $post_key ); ?>" rows="3" style="width:100%;max-width:520px;"><?php echo esc_textarea( $val ); ?></textarea>
								<?php elseif ( 'checkbox' === $field['type'] ) : ?>
									<input type="checkbox" name="<?php echo esc_attr( $post_key ); ?>" value="1" <?php checked( ! empty( $val ) ); ?>>
								<?php else : ?>
									<input type="text" name="<?php echo esc_attr( $post_key ); ?>" value="<?php echo esc_attr( $val ); ?>" style="width:100%;max-width:520px;">
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
					</tbody>
				</table>

				<p><button type="submit" class="button button-primary">Enregistrer les modifications</button></p>
			</form>
		</div>
		<?php
	}

	public static function page_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$sections = CFI_Fiches::sections();
		?>
		<div class="wrap">
			<h1>⚙️ Fiches thèmes — Paramètres des champs</h1>
			<p><a href="<?php echo esc_url( self::base_url() ); ?>">← Retour à la liste des fiches</a></p>

			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success"><p>Champs enregistrés.</p></div>
			<?php elseif ( isset( $_GET['reset'] ) ) : ?>
				<div class="notice notice-success"><p>Champs réinitialisés aux valeurs par défaut (celles du PDF original).</p></div>
			<?php endif; ?>

			<p style="max-width:720px;color:#555;">Ajoute, renomme ou retire les champs du formulaire <code>[cf_fiche_intake]</code>. Retirer un champ ne supprime pas les réponses déjà reçues pour ce champ, il n'apparaît juste plus dans le formulaire ni dans les nouvelles fiches. Renommer le libellé d'un champ existant n'affecte pas les données déjà enregistrées sous ce champ.</p>

			<style>
			.cfi-section          { border:1px solid #dcdcde; border-radius:8px; padding:16px 18px; margin-bottom:18px; background:#fff; max-width:820px; }
			.cfi-section-heading  { font-size:14px; font-weight:600; width:100%; max-width:420px; padding:8px; margin-bottom:10px; }
			.cfi-section-activites { display:flex; flex-wrap:wrap; gap:4px 14px; margin:0 0 14px; padding:8px 10px; background:#f6f3f9; border-radius:6px; }
			.cfi-section-activites label { font-size:12px; font-weight:400; display:flex; align-items:center; gap:4px; }
			.cfi-field-row        { display:flex; gap:8px; align-items:center; margin-bottom:4px; }
			.cfi-field-row input[type=text] { flex:2; padding:6px; }
			.cfi-field-row select { flex:1; }
			.cfi-field-hint       { width:100%; box-sizing:border-box; padding:5px 6px; margin:0 0 10px; font-size:12px; color:#666; }
			.cfi-remove-field, .cfi-remove-section { color:#b32d2e; }
			</style>

			<form method="post" id="cfi-settings-form">
				<?php wp_nonce_field( 'cfi_settings_save' ); ?>
				<div id="cfi-sections-wrap">
					<?php foreach ( $sections as $si => $section ) : ?>
					<div class="cfi-section" data-section data-s-idx="<?php echo (int) $si; ?>">
						<input type="text" class="cfi-section-heading" name="sections[<?php echo (int) $si; ?>][heading]" value="<?php echo esc_attr( $section['heading'] ); ?>" placeholder="Titre de la section">
						<div class="cfi-section-activites">
							<?php foreach ( CFI_Fiches::ACTIVITES as $act_slug => $act_label ) : ?>
								<label>
									<input type="checkbox" name="sections[<?php echo (int) $si; ?>][activites][]" value="<?php echo esc_attr( $act_slug ); ?>" <?php checked( in_array( $act_slug, $section['activites'] ?? [], true ) ); ?>>
									<?php echo esc_html( $act_label ); ?>
								</label>
							<?php endforeach; ?>
							<span style="font-size:12px;color:#888;">(aucune case = section toujours affichée)</span>
						</div>
						<div class="cfi-fields-wrap">
							<?php foreach ( $section['fields'] as $fi => $field ) : ?>
							<div data-field>
								<div class="cfi-field-row">
									<input type="hidden" name="sections[<?php echo (int) $si; ?>][fields][<?php echo (int) $fi; ?>][key]" value="<?php echo esc_attr( $field['key'] ); ?>">
									<input type="text" name="sections[<?php echo (int) $si; ?>][fields][<?php echo (int) $fi; ?>][label]" value="<?php echo esc_attr( $field['label'] ); ?>" placeholder="Libellé du champ">
									<select name="sections[<?php echo (int) $si; ?>][fields][<?php echo (int) $fi; ?>][type]">
										<option value="text"     <?php selected( $field['type'], 'text' );     ?>>Texte court</option>
										<option value="textarea" <?php selected( $field['type'], 'textarea' ); ?>>Texte long</option>
										<option value="checkbox" <?php selected( $field['type'], 'checkbox' ); ?>>Case à cocher</option>
									</select>
									<button type="button" class="button-link cfi-remove-field" title="Retirer ce champ">✕</button>
								</div>
								<input type="text" class="cfi-field-hint" name="sections[<?php echo (int) $si; ?>][fields][<?php echo (int) $fi; ?>][hint]" value="<?php echo esc_attr( $field['hint'] ?? '' ); ?>" placeholder="Indice affiché sous la question (facultatif)">
							</div>
							<?php endforeach; ?>
						</div>
						<p>
							<button type="button" class="button button-small cfi-add-field">+ Ajouter un champ</button>
							<button type="button" class="button-link cfi-remove-section" style="margin-left:12px;">Supprimer cette section</button>
						</p>
					</div>
					<?php endforeach; ?>
				</div>

				<p><button type="button" class="button" id="cfi-add-section">+ Ajouter une section</button></p>

				<p>
					<button type="submit" name="cfi_settings_save" value="1" class="button button-primary">Enregistrer les champs</button>
					<button type="submit" name="cfi_settings_reset" value="1" class="button" style="margin-left:8px;" onclick="return confirm('Réinitialiser tous les champs aux valeurs par défaut du PDF original ? Les personnalisations en cours seront perdues.');">Réinitialiser aux champs par défaut</button>
				</p>
			</form>

			<template id="cfi-field-template">
				<div data-field>
					<div class="cfi-field-row">
						<input type="hidden" name="">
						<input type="text" name="" placeholder="Libellé du champ">
						<select name="">
							<option value="text">Texte court</option>
							<option value="textarea">Texte long</option>
							<option value="checkbox">Case à cocher</option>
						</select>
						<button type="button" class="button-link cfi-remove-field" title="Retirer ce champ">✕</button>
					</div>
					<input type="text" class="cfi-field-hint" name="" placeholder="Indice affiché sous la question (facultatif)">
				</div>
			</template>
			<template id="cfi-section-template">
				<div class="cfi-section" data-section>
					<input type="text" class="cfi-section-heading" name="" placeholder="Titre de la section">
					<div class="cfi-section-activites">
						<?php foreach ( CFI_Fiches::ACTIVITES as $act_slug => $act_label ) : ?>
							<label>
								<input type="checkbox" name="" value="<?php echo esc_attr( $act_slug ); ?>">
								<?php echo esc_html( $act_label ); ?>
							</label>
						<?php endforeach; ?>
						<span style="font-size:12px;color:#888;">(aucune case = section toujours affichée)</span>
					</div>
					<div class="cfi-fields-wrap"></div>
					<p>
						<button type="button" class="button button-small cfi-add-field">+ Ajouter un champ</button>
						<button type="button" class="button-link cfi-remove-section" style="margin-left:12px;">Supprimer cette section</button>
					</p>
				</div>
			</template>

			<script>
			(function () {
				var wrap = document.getElementById('cfi-sections-wrap');
				var sectionCounter = <?php echo (int) count( $sections ); ?>;

				function bindRemoveField(btn) {
					btn.addEventListener('click', function () {
						btn.closest('[data-field]').remove();
					});
				}

				function addField(sectionEl) {
					var tpl    = document.getElementById('cfi-field-template').content.cloneNode(true);
					var sIdx   = sectionEl.dataset.sIdx;
					var fIdx   = sectionEl.querySelectorAll('[data-field]').length;
					var inputs = tpl.querySelectorAll('input, select');
					inputs[0].name = 'sections[' + sIdx + '][fields][' + fIdx + '][key]';
					inputs[1].name = 'sections[' + sIdx + '][fields][' + fIdx + '][label]';
					inputs[2].name = 'sections[' + sIdx + '][fields][' + fIdx + '][type]';
					inputs[3].name = 'sections[' + sIdx + '][fields][' + fIdx + '][hint]';
					bindRemoveField(tpl.querySelector('.cfi-remove-field'));
					sectionEl.querySelector('.cfi-fields-wrap').appendChild(tpl);
				}

				function bindSection(sectionEl) {
					var addFieldBtn      = sectionEl.querySelector('.cfi-add-field');
					var removeSectionBtn = sectionEl.querySelector('.cfi-remove-section');
					if (addFieldBtn) {
						addFieldBtn.addEventListener('click', function () { addField(sectionEl); });
					}
					if (removeSectionBtn) {
						removeSectionBtn.addEventListener('click', function () {
							if (confirm('Supprimer cette section et tous ses champs du formulaire ?')) {
								sectionEl.remove();
							}
						});
					}
					sectionEl.querySelectorAll('.cfi-remove-field').forEach(bindRemoveField);
				}

				document.querySelectorAll('[data-section]').forEach(function (sectionEl, i) {
					sectionEl.dataset.sIdx = i;
					bindSection(sectionEl);
				});

				document.getElementById('cfi-add-section').addEventListener('click', function () {
					var tpl       = document.getElementById('cfi-section-template').content.cloneNode(true);
					var sectionEl = tpl.querySelector('[data-section]');
					var sIdx      = sectionCounter++;
					sectionEl.dataset.sIdx = sIdx;
					sectionEl.querySelector('.cfi-section-heading').name = 'sections[' + sIdx + '][heading]';
					sectionEl.querySelectorAll('.cfi-section-activites input[type=checkbox]').forEach(function (cb) {
						cb.name = 'sections[' + sIdx + '][activites][]';
					});
					wrap.appendChild(tpl);
					bindSection(wrap.lastElementChild);
				});
			})();
			</script>
		</div>
		<?php
	}
}
