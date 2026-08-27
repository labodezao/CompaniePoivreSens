<?php
/**
 * Témoignages — collecte via formulaire public (lié à l'email 3 post-séance),
 * validation en admin (CPT), affichage via shortcode.
 * Shortcodes : [cf_temoignage_form]  [cf_temoignages]  [cf_temoignages_google]
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CF_Testimonials {

	const CPT = 'cf_temoignage';

	public static function init() {
		register_post_type( self::CPT, [
			'labels'       => [
				'name'          => 'Témoignages',
				'singular_name' => 'Témoignage',
				'menu_name'     => 'Témoignages',
				'add_new_item'  => 'Ajouter un témoignage',
				'edit_item'     => 'Modifier le témoignage',
			],
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'edit.php?post_type=' . CFEB_SLUG,
			'supports'     => [ 'title' ],
			'map_meta_cap' => true,
		] );

		add_action( 'add_meta_boxes',                 [ __CLASS__, 'register_meta_boxes' ] );
		add_action( 'save_post_' . self::CPT,         [ __CLASS__, 'save_meta' ], 10, 2 );
		add_filter( 'manage_' . self::CPT . '_posts_columns',       [ __CLASS__, 'admin_columns' ] );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', [ __CLASS__, 'admin_column_content' ], 10, 2 );
	}

	/* ══════════════════════════════════════════════════════════════
	   ADMIN — META BOXES (remplace l'éditeur par défaut)
	══════════════════════════════════════════════════════════════ */
	public static function register_meta_boxes() {
		add_meta_box( 'cft_contenu',      'Contenu',            [ __CLASS__, 'mb_contenu' ],      self::CPT, 'normal', 'high' );
		add_meta_box( 'cft_publication',  'Source & publication', [ __CLASS__, 'mb_publication' ], self::CPT, 'normal', 'default' );
	}

	private static function get_meta( $post_id ) {
		return [
			'texte'    => get_post( $post_id )->post_content ?? '',
			'prenom'   => get_post_meta( $post_id, '_cft_prenom',  true ),
			'anonyme'  => (int) get_post_meta( $post_id, '_cft_anonyme', true ),
			'consent'  => (int) get_post_meta( $post_id, '_cft_consent', true ),
			'source'   => get_post_meta( $post_id, '_cft_source', true ) ?: 'site',
			'note'     => (int) get_post_meta( $post_id, '_cft_note', true ),
			'ordre'    => (int) get_post_meta( $post_id, '_cft_ordre', true ),
		];
	}

	public static function mb_contenu( $post ) {
		wp_nonce_field( 'cft_save', 'cft_nonce' );
		$m = self::get_meta( $post->ID );
		?>
		<style>
		.cft-grid  { display:grid; grid-template-columns:1fr 1fr; gap:16px 24px; }
		.cft-full  { grid-column:1/-1; }
		.cft-label { display:block; font-weight:600; font-size:13px; margin-bottom:4px; color:#1d2327; }
		.cft-hint  { font-size:11px; color:#888; margin-top:3px; }
		</style>
		<div class="cft-grid" style="padding:4px 0;">
			<div class="cft-full">
				<label class="cft-label">Texte du témoignage *</label>
				<textarea name="cft_texte" rows="5" class="large-text" required><?php echo esc_textarea( $m['texte'] ); ?></textarea>
			</div>
			<div>
				<label class="cft-label">Prénom / Auteur</label>
				<input type="text" name="cft_prenom" value="<?php echo esc_attr( $m['prenom'] ); ?>" class="regular-text" />
			</div>
			<div>
				<label class="cft-label">Afficher de façon anonyme</label>
				<label style="display:flex;align-items:center;gap:6px;font-weight:400;margin-top:6px;">
					<input type="checkbox" name="cft_anonyme" value="1" <?php checked( $m['anonyme'] ); ?> /> « Anonyme » à la place du prénom
				</label>
			</div>
		</div>
		<?php
	}

	public static function mb_publication( $post ) {
		$m = self::get_meta( $post->ID );
		?>
		<div class="cft-grid" style="padding:4px 0;">
			<div>
				<label class="cft-label">Source</label>
				<select name="cft_source" id="cft-source-select">
					<option value="site"   <?php selected( $m['source'], 'site' );   ?>>Témoignage du site (formulaire)</option>
					<option value="google" <?php selected( $m['source'], 'google' ); ?>>Avis Google (recopié à la main)</option>
				</select>
				<p class="cft-hint">Détermine où ce témoignage peut apparaître : <code>[cf_temoignages]</code> (site) ou <code>[cf_temoignages_google]</code> (bandeau Google).</p>
			</div>
			<div id="cft-note-row" style="<?php echo 'google' === $m['source'] ? '' : 'display:none;'; ?>">
				<label class="cft-label">Note (si avis Google)</label>
				<select name="cft_note">
					<?php for ( $n = 5; $n >= 1; $n-- ) : ?>
						<option value="<?php echo (int) $n; ?>" <?php selected( $m['note'], $n ); ?>><?php echo str_repeat( '★', $n ); ?> (<?php echo (int) $n; ?>)</option>
					<?php endfor; ?>
				</select>
			</div>
			<div>
				<label class="cft-label">Ordre d'affichage dans le bandeau</label>
				<input type="number" name="cft_ordre" value="<?php echo (int) $m['ordre']; ?>" class="small-text" min="0" />
				<p class="cft-hint">0 = premier. À égalité, les plus récents d'abord.</p>
			</div>
			<div>
				<label class="cft-label">Publication</label>
				<label style="display:flex;align-items:center;gap:6px;font-weight:400;margin-top:6px;">
					<input type="checkbox" name="cft_consent" value="1" <?php checked( $m['consent'] ); ?> /> Autorisé à être affiché publiquement
				</label>
				<p class="cft-hint">Ne suffit pas seul : le statut de l'article doit aussi être « Publié ».</p>
			</div>
		</div>
		<script>
		(function(){
			var sel = document.getElementById('cft-source-select');
			var row = document.getElementById('cft-note-row');
			if (!sel || !row) return;
			sel.addEventListener('change', function(){
				row.style.display = ('google' === sel.value) ? '' : 'none';
			});
		})();
		</script>
		<?php
	}

	public static function save_meta( $post_id, $post ) {
		if (
			! isset( $_POST['cft_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cft_nonce'] ) ), 'cft_save' ) ||
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
			! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		$texte = sanitize_textarea_field( wp_unslash( $_POST['cft_texte'] ?? '' ) );
		// wp_update_post() redéclenche save_post_<cpt> — se retirer du hook le
		// temps de l'appel, sinon boucle infinie (ce même save_meta() se
		// rappelant lui-même) jusqu'à épuisement mémoire.
		remove_action( 'save_post_' . self::CPT, [ __CLASS__, 'save_meta' ], 10 );
		wp_update_post( [ 'ID' => $post_id, 'post_content' => $texte ] );
		add_action( 'save_post_' . self::CPT, [ __CLASS__, 'save_meta' ], 10, 2 );

		$prenom  = sanitize_text_field( wp_unslash( $_POST['cft_prenom'] ?? '' ) );
		$anonyme = ! empty( $_POST['cft_anonyme'] );
		$consent = ! empty( $_POST['cft_consent'] );
		$source  = in_array( $_POST['cft_source'] ?? '', [ 'site', 'google' ], true ) ? $_POST['cft_source'] : 'site';
		$note    = max( 0, min( 5, absint( $_POST['cft_note'] ?? 0 ) ) );
		$ordre   = absint( $_POST['cft_ordre'] ?? 0 );

		update_post_meta( $post_id, '_cft_prenom',  $prenom );
		update_post_meta( $post_id, '_cft_anonyme', $anonyme ? 1 : 0 );
		update_post_meta( $post_id, '_cft_consent', $consent ? 1 : 0 );
		update_post_meta( $post_id, '_cft_source',  $source );
		update_post_meta( $post_id, '_cft_note',    $note );
		update_post_meta( $post_id, '_cft_ordre',   $ordre );
	}

	/* ── Colonnes de la liste admin ───────────────────────────────── */
	public static function admin_columns( $columns ) {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['cft_source']  = 'Source';
				$new['cft_note']    = 'Note';
				$new['cft_consent'] = 'Publication';
				$new['cft_ordre']   = 'Ordre';
			}
		}
		return $new;
	}

	public static function admin_column_content( $column, $post_id ) {
		$m = self::get_meta( $post_id );
		switch ( $column ) {
			case 'cft_source':
				echo 'google' === $m['source'] ? '🌐 Google' : '💬 Site';
				break;
			case 'cft_note':
				echo $m['note'] ? esc_html( str_repeat( '★', $m['note'] ) ) : '—';
				break;
			case 'cft_consent':
				echo $m['consent'] ? '✅' : '❌';
				break;
			case 'cft_ordre':
				echo (int) $m['ordre'];
				break;
		}
	}

	/* ── Formulaire public [cf_temoignage_form] ────────────────────── */
	/*
	 * Trois portes, sans hiérarchie imposée dans le code (l'ordre visuel —
	 * Google d'abord — se décide dans le contenu de la page /temoignage/,
	 * pas ici) :
	 *   a) avis Google (lien externe, hors de ce formulaire)
	 *   b) message WhatsApp (lien externe, « pour dire, pas pour publier »)
	 *   c) ce formulaire écrit, dont la publication est un CHOIX SÉPARÉ.
	 *
	 * Le consentement de publication n'est plus bloquant : un message envoyé
	 * sans la case cochée arrive quand même (Ewen le lit), mais ne sera
	 * JAMAIS affiché sur le site — voir le double verrou dans
	 * display_shortcode() (statut ET meta de consentement).
	 */
	public static function form_shortcode() {
		ob_start();
		if ( isset( $_GET['cft_ok'] ) ) {
			echo '<p style="background:#f0fdf4;border:1px solid #86efac;padding:14px 18px;border-radius:8px;">Merci. 🙏 C\'est bien arrivé — et si vous avez coché la case de publication, ce sera relu avant toute mise en ligne.</p>';
			return ob_get_clean();
		}
		?>
		<p style="color:#666;font-size:0.95rem;margin-bottom:1.25rem;">Témoigner n'est pas attendu — s'exprimer suffit. Rien n'est jamais publié sans votre accord explicite, coché ci-dessous.</p>
		<form method="post" class="cft-form" style="max-width:480px;">
			<?php wp_nonce_field( 'cft_submit', 'cft_nonce' ); ?>
			<p><label>Votre prénom<br><input type="text" name="cft_prenom" style="width:100%;padding:10px;"></label></p>
			<p><label>Votre témoignage *<br><textarea name="cft_texte" rows="6" required style="width:100%;padding:10px;" placeholder="En deux ou trois phrases, ce que vous avez vécu…"></textarea></label></p>
			<p style="font-size:0.85rem;color:#888;margin:-0.5rem 0 1rem;">Ce message m'arrive dans tous les cas. Les deux cases ci-dessous ne concernent QUE sa publication éventuelle sur le site.</p>
			<p><label style="display:flex;gap:8px;align-items:flex-start;"><input type="checkbox" name="cft_consent" value="1" style="margin-top:3px;"> <span>J'autorise la publication de ce témoignage sur le site</span></label></p>
			<p><label style="display:flex;gap:8px;align-items:flex-start;"><input type="checkbox" name="cft_anonyme" value="1" style="margin-top:3px;"> <span>Si publié : de façon anonyme (sinon, avec mon prénom)</span></label></p>
			<button type="submit" style="background:#5a3e6b;color:#fff;border:none;padding:12px 26px;border-radius:6px;cursor:pointer;">Envoyer</button>
		</form>
		<?php
		return ob_get_clean();
	}

	/* ── Réception (hook init) ─────────────────────────────────────── */
	public static function handle_submit() {
		if ( empty( $_POST['cft_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cft_nonce'] ) ), 'cft_submit' ) ) {
			return;
		}
		// Le consentement N'EST PLUS une condition d'envoi (cf. commentaire
		// au-dessus de form_shortcode()) : seul un texte non vide est requis.
		$texte = sanitize_textarea_field( wp_unslash( $_POST['cft_texte'] ?? '' ) );
		if ( '' === trim( $texte ) ) {
			return;
		}
		$prenom  = sanitize_text_field( wp_unslash( $_POST['cft_prenom'] ?? '' ) );
		$anonyme = ! empty( $_POST['cft_anonyme'] );
		$consent = ! empty( $_POST['cft_consent'] );

		$id = wp_insert_post( [
			'post_type'    => self::CPT,
			'post_status'  => 'pending', // relu et publié par l'administratrice, jamais automatique
			'post_title'   => $anonyme || ! $prenom ? 'Témoignage anonyme' : 'Témoignage de ' . $prenom,
			'post_content' => $texte,
		] );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_cft_prenom', $prenom );
			update_post_meta( $id, '_cft_anonyme', $anonyme ? 1 : 0 );
			update_post_meta( $id, '_cft_consent', $consent ? 1 : 0 );
			update_post_meta( $id, '_cft_source', 'site' );
			// Prévenir l'administratrice — le sujet indique tout de suite si
			// la publication est autorisée, pour prioriser la relecture.
			$sujet = $consent
				? 'Nouveau témoignage à relire (publication autorisée)'
				: 'Nouveau message (témoignage) — publication NON autorisée';
			wp_mail(
				get_option( 'admin_email' ),
				$sujet,
				'Un nouveau message attend ta lecture : ' . admin_url( 'post.php?post=' . $id . '&action=edit' )
				. ( $consent ? '' : "\n\nCette personne n'a PAS coché la case de publication : à lire, jamais à publier telle quelle." )
			);
		}
		wp_safe_redirect( add_query_arg( 'cft_ok', '1', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	/* ── Affichage [cf_temoignages] — témoignages du site, en citations ── */
	/*
	 * Double verrou avant affichage : statut « publish » (décidé à la main
	 * par Ewen) ET consentement EXPLICITE ( _cft_consent = 1 ) déclaré par
	 * l'auteur. Repli fermé volontaire : une meta absente ne signifie jamais
	 * un consentement — seule une valeur '1' explicite autorise l'affichage.
	 */
	public static function display_shortcode( $atts ) {
		// -1 = tous (par défaut) : voir tous les témoignages plutôt qu'un
		// sous-ensemble figé, dans un ordre aléatoire à chaque affichage —
		// "nombre" reste réglable pour qui veut vraiment en limiter le nombre.
		$atts  = shortcode_atts( [ 'nombre' => -1 ], $atts, 'cf_temoignages' );
		$posts = get_posts( [
			'post_type'   => self::CPT,
			'post_status' => 'publish',
			'numberposts' => (int) $atts['nombre'],
			'orderby'     => 'rand',
			// "Source = site" inclut aussi les témoignages publiés avant l'ajout
			// de ce champ (meta absente) : seule une source explicitement
			// "google" les exclut de cet affichage, jamais un défaut fermé qui
			// ferait disparaître des témoignages déjà en ligne.
			'meta_query'  => [
				'relation' => 'AND',
				[ 'key' => '_cft_consent', 'value' => '1', 'compare' => '=' ],
				[
					'relation' => 'OR',
					[ 'key' => '_cft_source', 'value' => 'google', 'compare' => '!=' ],
					[ 'key' => '_cft_source', 'compare' => 'NOT EXISTS' ],
				],
			],
		] );
		if ( ! $posts ) return '';
		$out = '<div class="cft-list" style="display:grid;gap:18px;">';
		foreach ( $posts as $p ) {
			$prenom  = get_post_meta( $p->ID, '_cft_prenom', true );
			$anonyme = (bool) get_post_meta( $p->ID, '_cft_anonyme', true );
			$auteur  = ( $anonyme || ! $prenom ) ? 'Anonyme' : $prenom;
			$out .= '<blockquote style="background:#f6f3f9;border-left:3px solid #5a3e6b;border-radius:8px;padding:18px 22px;margin:0;font-style:italic;">'
				. wpautop( esc_html( $p->post_content ) )
				. '<footer style="font-style:normal;font-size:14px;color:#666;margin-top:8px;">— ' . esc_html( $auteur ) . '</footer>'
				. '</blockquote>';
		}
		return $out . '</div>';
	}

	/* ── Affichage [cf_temoignages_google] — bandeau défilant ─────────
	   Rend la même structure .ccf-marquee que le HTML autrefois codé en
	   dur dans la page /temoignages/ (voir thème constellation-cf,
	   assets/css/frontend.css .ccf-marquee* et assets/js/frontend.js pour
	   la duplication/le défilement) — zéro changement CSS/JS nécessaire,
	   seule la source du contenu change : géré depuis cet admin plutôt que
	   collé à la main dans l'éditeur Gutenberg. Même double verrou que
	   ci-dessus (statut publish + consentement explicite). */
	public static function display_google_shortcode( $atts ) {
		// -1 = tous (par défaut) : le marquee ne se limite plus aux 5 premiers
		// (champ "Ordre d'affichage" ignoré ici) — ordre aléatoire à chaque
		// affichage, pour que chacun repasse avant qu'un autre ne se répète.
		$atts  = shortcode_atts( [ 'nombre' => -1 ], $atts, 'cf_temoignages_google' );
		$posts = get_posts( [
			'post_type'   => self::CPT,
			'post_status' => 'publish',
			'numberposts' => (int) $atts['nombre'],
			'orderby'     => 'rand',
			'meta_query'  => [
				'relation' => 'AND',
				[ 'key' => '_cft_consent', 'value' => '1', 'compare' => '=' ],
				[ 'key' => '_cft_source',  'value' => 'google', 'compare' => '=' ],
			],
		] );
		if ( ! $posts ) return '';

		ob_start();
		?>
		<div class="ccf-marquee">
			<div class="ccf-marquee__track">
				<div class="ccf-marquee__set">
					<?php foreach ( $posts as $p ) :
						$prenom  = get_post_meta( $p->ID, '_cft_prenom', true );
						$anonyme = (bool) get_post_meta( $p->ID, '_cft_anonyme', true );
						$auteur  = ( $anonyme || ! $prenom ) ? '' : $prenom;
						$note    = max( 1, min( 5, (int) get_post_meta( $p->ID, '_cft_note', true ) ?: 5 ) );
					?>
					<div class="ccf-marquee-card">
						<p class="ccf-marquee-stars"><?php echo esc_html( str_repeat( '★', $note ) ); ?></p>
						<blockquote><?php echo esc_html( $p->post_content ); ?></blockquote>
						<?php if ( $auteur ) : ?>
							<cite>— <?php echo esc_html( $auteur ); ?>, avis Google</cite>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
		return trim( (string) ob_get_clean() );
	}
}
