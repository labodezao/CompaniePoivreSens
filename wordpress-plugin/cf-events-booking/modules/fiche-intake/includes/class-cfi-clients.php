<?php
/**
 * Espace « Clients » — vue unifiée par email : fiches thèmes reçues et
 * génogramme associé (si le plugin génogramme-familial est actif),
 * regroupés au même endroit pour ne plus avoir à recouper manuellement.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFI_Clients {

	private static function base_url( $args = [] ) {
		return admin_url( 'edit.php?post_type=' . CFEB_SLUG . '&page=cfi-clients' . ( $args ? '&' . http_build_query( $args ) : '' ) );
	}

	public static function register_menu() {
		add_submenu_page( 'edit.php?post_type=' . CFEB_SLUG, 'Clients', 'Clients', 'manage_options', 'cfi-clients', [ __CLASS__, 'page' ] );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$bulk_result = null;
		if ( ! empty( $_POST['cfi_clients_bulk_create'] ) && check_admin_referer( 'cfi_clients_bulk_create' ) ) {
			$bulk_result = self::handle_bulk_create();
		}
		$email = sanitize_email( wp_unslash( $_GET['email'] ?? '' ) );
		if ( $email ) {
			self::page_detail( $email );
			return;
		}
		self::page_list( $bulk_result );
	}

	/**
	 * Crée (ou retrouve) un lien génogramme personnel pour chaque ligne
	 * saisie, sans passer par une fiche — pour les cas où le génogramme
	 * démarre à vide : un atelier, une formation, un accompagnement qui n'a
	 * pas commencé par la fiche thèmes.
	 *
	 * Une ligne par personne, au format « Nom, email » (le nom est
	 * facultatif : un email seul suffit). S'appuie sur
	 * Geno_Client_Saves::get_or_create(), qui ne crée jamais deux fois la
	 * même adresse — relancer l'opération sur une liste déjà traitée
	 * retrouve simplement les mêmes liens, ne les régénère pas.
	 *
	 * @return array{crees: array, erreurs: array}
	 */
	private static function handle_bulk_create(): array {
		$crees   = [];
		$erreurs = [];

		if ( ! class_exists( 'Geno_Client_Saves' ) || ! Geno_Client_Saves::table_exists() ) {
			$erreurs[] = 'Le plugin génogramme n\'est pas actif sur ce site.';
			return [ 'crees' => $crees, 'erreurs' => $erreurs ];
		}

		$brut  = (string) wp_unslash( $_POST['cfi_clients_bulk_input'] ?? '' );
		$lignes = preg_split( '/\r\n|\r|\n/', $brut );

		foreach ( $lignes as $ligne ) {
			$ligne = trim( $ligne );
			if ( '' === $ligne ) {
				continue;
			}
			if ( str_contains( $ligne, ',' ) ) {
				[ $nom, $email ] = array_map( 'trim', explode( ',', $ligne, 2 ) );
			} else {
				$nom   = '';
				$email = $ligne;
			}
			$email = sanitize_email( $email );
			if ( ! is_email( $email ) ) {
				$erreurs[] = $ligne;
				continue;
			}
			$save    = Geno_Client_Saves::get_or_create( $email, sanitize_text_field( $nom ) );
			$crees[] = [
				'nom'   => $save['nom'] ?: $email,
				'email' => $email,
				'lien'  => Geno_Client_Saves::link( $save['token'] ),
			];
		}

		return [ 'crees' => $crees, 'erreurs' => $erreurs ];
	}

	/**
	 * Fusionne les emails connus des fiches et (si le plugin génogramme est
	 * actif) des génogrammes sauvegardés, en une liste triée par activité
	 * la plus récente. Un même email ne compte qu'une fois.
	 */
	private static function list_clients(): array {
		$clients = []; // email normalisé => données

		foreach ( CFI_Fiches::all( 1000 ) as $fiche ) {
			$key = strtolower( trim( $fiche->email ) );
			if ( '' === $key ) {
				continue;
			}
			if ( ! isset( $clients[ $key ] ) ) {
				$clients[ $key ] = [
					'email'        => $fiche->email,
					'nom'          => trim( $fiche->prenom . ' ' . $fiche->nom ),
					'nb_fiches'    => 0,
					'a_genogramme' => false,
					'derniere'     => $fiche->cree_le,
				];
			}
			$clients[ $key ]['nb_fiches']++;
			if ( strtotime( $fiche->cree_le ) > strtotime( $clients[ $key ]['derniere'] ) ) {
				$clients[ $key ]['derniere'] = $fiche->cree_le;
			}
		}

		if ( class_exists( 'Geno_Client_Saves' ) && Geno_Client_Saves::table_exists() ) {
			foreach ( Geno_Client_Saves::all() as $save ) {
				$key = strtolower( trim( $save['email'] ) );
				if ( '' === $key ) {
					continue;
				}
				if ( ! isset( $clients[ $key ] ) ) {
					$clients[ $key ] = [
						'email'        => $save['email'],
						'nom'          => $save['nom'],
						'nb_fiches'    => 0,
						'a_genogramme' => false,
						'derniere'     => $save['maj_le'],
					];
				}
				$clients[ $key ]['a_genogramme'] = ! empty( $save['data'] );
				if ( strtotime( $save['maj_le'] ) > strtotime( $clients[ $key ]['derniere'] ) ) {
					$clients[ $key ]['derniere'] = $save['maj_le'];
				}
			}
		}

		usort( $clients, function ( $a, $b ) {
			return strtotime( $b['derniere'] ) <=> strtotime( $a['derniere'] );
		} );

		return array_values( $clients );
	}

	private static function page_list( $bulk_result = null ) {
		$clients = self::list_clients();
		?>
		<div class="wrap">
			<h1>👤 Clients</h1>
			<p>Fiches thèmes et génogrammes regroupés par email, dans l'ordre de la dernière activité.</p>

			<h2 style="margin-top:2rem;">Créer des liens génogramme</h2>
			<p style="max-width:60em;">
				Pour un génogramme qui démarre à vide — un atelier, une formation — sans passer par la fiche
				thèmes. Un lien personnel par personne : chacun y construit son génogramme (sans compte, sans
				mot de passe), et il apparaît ici dès la première sauvegarde. Relancer sur une liste déjà
				traitée retrouve les mêmes liens, n'en recrée pas de nouveaux.
			</p>
			<form method="post">
				<?php wp_nonce_field( 'cfi_clients_bulk_create' ); ?>
				<p>
					<textarea name="cfi_clients_bulk_input" rows="5" class="large-text code"
						placeholder="Un par ligne — Nom, email (le nom est facultatif : un email seul suffit)&#10;Marie Dupont, marie@exemple.fr&#10;jean@exemple.fr"
						style="max-width:36em;"></textarea>
				</p>
				<p>
					<button type="submit" name="cfi_clients_bulk_create" value="1" class="button button-primary">Créer les liens</button>
				</p>
			</form>

			<?php if ( $bulk_result && $bulk_result['erreurs'] ) : ?>
				<div class="notice notice-error"><p>
					Adresse email illisible, ignorée·s : <?php echo esc_html( implode( ' · ', $bulk_result['erreurs'] ) ); ?>
				</p></div>
			<?php endif; ?>

			<?php if ( $bulk_result && $bulk_result['crees'] ) : ?>
				<table class="wp-list-table widefat striped cfeb-table" style="margin-top:12px;max-width:60em;">
					<thead><tr><th>Nom</th><th>Email</th><th>Lien à transmettre</th></tr></thead>
					<tbody>
					<?php foreach ( $bulk_result['crees'] as $c ) : ?>
						<tr>
							<td><?php echo esc_html( $c['nom'] ); ?></td>
							<td><?php echo esc_html( $c['email'] ); ?></td>
							<td><input type="text" readonly value="<?php echo esc_attr( $c['lien'] ); ?>" class="large-text code" onclick="this.select();" style="width:100%;"></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2 style="margin-top:2.5rem;">Tous les clients</h2>
			<table class="wp-list-table widefat striped cfeb-table" style="margin-top:12px;">
				<thead>
					<tr>
						<th>Nom</th>
						<th>Email</th>
						<th>Fiches</th>
						<th>Génogramme</th>
						<th>Dernière activité</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $clients ) : ?>
					<tr><td colspan="6" style="text-align:center;padding:20px;color:#888;">Aucun client pour l'instant.</td></tr>
				<?php else : ?>
					<?php foreach ( $clients as $c ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $c['nom'] ?: '—' ); ?></strong></td>
						<td><?php echo esc_html( $c['email'] ); ?></td>
						<td><?php echo (int) $c['nb_fiches']; ?></td>
						<td><?php echo $c['a_genogramme'] ? '🌳 Oui' : '—'; ?></td>
						<td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $c['derniere'] ) ) ); ?></td>
						<td><a class="button button-small" href="<?php echo esc_url( self::base_url( [ 'email' => $c['email'] ] ) ); ?>">Voir</a></td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function page_detail( string $email ) {
		$fiches = CFI_Fiches::for_email( $email );
		$save   = ( class_exists( 'Geno_Client_Saves' ) && Geno_Client_Saves::table_exists() )
			? ( Geno_Client_Saves::for_email( $email )[0] ?? null )
			: null;
		$nom    = $fiches ? trim( $fiches[0]->prenom . ' ' . $fiches[0]->nom ) : ( $save['nom'] ?? '' );
		?>
		<div class="wrap">
			<p><a href="<?php echo esc_url( self::base_url() ); ?>">← Retour à la liste des clients</a></p>
			<h1><?php echo esc_html( $nom ?: $email ); ?></h1>
			<p style="color:#888;"><?php echo esc_html( $email ); ?></p>

			<h2 style="margin-top:2rem;">📋 Fiches thèmes</h2>
			<?php if ( ! $fiches ) : ?>
				<p style="color:#888;">Aucune fiche reçue de cet email.</p>
			<?php else : ?>
				<table class="wp-list-table widefat striped cfeb-table">
					<thead><tr><th>Reçue le</th><th>Téléphone</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $fiches as $f ) : ?>
						<tr>
							<td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $f->cree_le ) ) ); ?></td>
							<td><?php echo esc_html( $f->telephone ?: '—' ); ?></td>
							<td><a class="button button-small" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CFEB_SLUG . '&page=cfi-fiches&fiche_id=' . $f->id ) ); ?>">Voir / modifier</a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2 style="margin-top:2rem;">🌳 Génogramme</h2>
			<?php if ( ! class_exists( 'Geno_Client_Saves' ) ) : ?>
				<p style="color:#888;">Plugin génogramme non actif sur ce site.</p>
			<?php elseif ( ! $save ) : ?>
				<p style="color:#888;">Aucun génogramme pour cet email pour l'instant — il apparaîtra ici dès qu'une fiche sera reçue de cet email, ou qu'un génogramme y sera explicitement associé depuis l'outil.</p>
			<?php else : ?>
				<p>
					Dernière mise à jour : <?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $save['maj_le'] ) ) ); ?>
					— <?php echo empty( $save['data'] ) ? 'pas encore commencé' : 'en cours'; ?>
					&nbsp;·&nbsp;
					<a class="button button-small" href="<?php echo esc_url( Geno_Client_Saves::link( $save['token'] ) ); ?>" target="_blank" rel="noopener">🌳 Ouvrir</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
