<?php
/**
 * Programme Pleine Vie — installation (table + réglages par défaut).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CFPV_Install {

	public static function activate() {
		self::create_table();
		if ( ! get_option( 'cfpv_settings' ) ) {
			update_option( 'cfpv_settings', self::default_settings() );
		}
		if ( class_exists( 'CFPV_Sequence' ) ) {
			CFPV_Sequence::schedule();
		}
	}

	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = $wpdb->prefix . 'cfpv_inscriptions';
		dbDelta( "CREATE TABLE IF NOT EXISTS {$t} (
			id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
			prenom       VARCHAR(100) NOT NULL DEFAULT '',
			nom          VARCHAR(100) NOT NULL DEFAULT '',
			email        VARCHAR(200) NOT NULL DEFAULT '',
			telephone    VARCHAR(50)  NOT NULL DEFAULT '',
			message      TEXT         NOT NULL,
			session      VARCHAR(100) NOT NULL DEFAULT '',
			statut       VARCHAR(20)  NOT NULL DEFAULT 'attente',
			token        VARCHAR(64)  NOT NULL DEFAULT '',
			source       VARCHAR(50)  NOT NULL DEFAULT '',
			seq_sent     TEXT         NOT NULL,
			confirme_le  DATETIME     DEFAULT NULL,
			cree_le      DATETIME     NOT NULL,
			PRIMARY KEY (id),
			KEY statut (statut),
			KEY email (email(100))
		) " . $wpdb->get_charset_collate() . ';' );

		// Migration : colonne seq_sent sur une table existante
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$t}`" );
		if ( is_array( $cols ) && ! in_array( 'seq_sent', $cols, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$t}` ADD COLUMN `seq_sent` TEXT NOT NULL" );
		}
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cfpv_inscriptions';
	}

	public static function default_settings() {
		$sig = "\n\n— Ewen";
		return [
			'from_name'    => get_bloginfo( 'name' ),
			'from_email'   => get_option( 'admin_email' ),
			'notif_email'  => get_option( 'admin_email' ),
			'session'      => '', // libellé de la prochaine session (ex. « Session d'octobre »)

			// Paiement par virement — coordonnées éditables, insérées via {{virement}}
			'vir_titulaire'    => get_bloginfo( 'name' ),
			'vir_iban'         => '',
			'vir_bic'          => '',
			'vir_montant'      => '',
			'vir_instructions' => 'Merci d\'indiquer ton NOM en référence du virement. Ta place est réservée dès réception.',

			// Séquence d'accompagnement (ancrage + démarrage)
			'seq_anchor'   => 'confirmation', // 'confirmation' (date de validation) ou 'demarrage'
			'demarrage'    => '',             // date de démarrage du programme (AAAA-MM-JJ), si ancrage 'demarrage'

			// Accusé de réception envoyé au candidat dès l'inscription
			'ack_objet'    => 'J\'ai bien reçu ta demande — Programme Pleine Vie',
			'ack_corps'    => "Bonjour {{prenom}},\n\nMerci pour ta demande d'inscription au Programme Pleine Vie. Je l'ai bien reçue et je reviens vers toi très vite pour la suite (dates, modalités, et tout ce qu'il faut savoir).\n\nSi tu as une question d'ici là, réponds simplement à cet email." . $sig,

			// Email de validation, envoyé quand tu valides l'inscription
			'valid_objet'  => 'Bienvenue dans le Programme Pleine Vie 🌿',
			'valid_corps'  => "Bonjour {{prenom}},\n\nC'est officiel : ta place dans le Programme Pleine Vie est confirmée. Je suis heureux de t'accompagner pendant ce mois.\n\nPour finaliser ton inscription, le règlement se fait par virement :\n\n{{virement}}\n\nDès réception, tout est bon — je reviens vers toi avec les dates et les premiers pas.\n\nÀ très vite," . $sig,

			// Email d'annulation (optionnel)
			'cancel_objet' => 'À propos de ton inscription au Programme Pleine Vie',
			'cancel_corps' => "Bonjour {{prenom}},\n\nJe reviens vers toi au sujet de ta demande d'inscription au Programme Pleine Vie. Malheureusement, je ne peux pas la retenir pour cette session.\n\nN'hésite pas à me répondre si tu souhaites en discuter ou envisager une prochaine session." . $sig,
		] + self::default_sequence();
	}

	/* ── Étapes par défaut de la séquence d'accompagnement (1 mois) ──
	   Chaque étape : activée, jour (décalage depuis l'ancrage), objet, corps. */
	public static function default_sequence() {
		$sig = "\n\n— Ewen";
		return [
			'seq_1_enabled' => 1, 'seq_1_day' => 0,
			'seq_1_objet'   => 'On démarre — Programme Pleine Vie 🌿',
			'seq_1_corps'   => "Bonjour {{prenom}},\n\nNous y voilà : le programme commence. Pendant ce mois, tu vas avancer pas à pas, à ton rythme.\n\nPour aujourd'hui, une seule chose : prends cinq minutes au calme, et pose l'intention avec laquelle tu entres dans ce parcours. Note-la quelque part.\n\nJe t'écris dans quelques jours pour la suite." . $sig,

			'seq_2_enabled' => 1, 'seq_2_day' => 3,
			'seq_2_objet'   => 'Un premier ancrage',
			'seq_2_corps'   => "Bonjour {{prenom}},\n\nAs-tu remarqué ce qui a bougé depuis le début ? Parfois beaucoup, parfois rien d'apparent — les deux sont justes.\n\nCette semaine, observe simplement, sans rien forcer. Le mouvement a déjà commencé." . $sig,

			'seq_3_enabled' => 1, 'seq_3_day' => 7,
			'seq_3_objet'   => 'Première semaine — on fait le point',
			'seq_3_corps'   => "Bonjour {{prenom}},\n\nUne semaine déjà. C'est un bon moment pour relire l'intention que tu avais posée au départ.\n\nSi tu ressens le besoin d'échanger, réponds-moi : je lis tout." . $sig,

			'seq_4_enabled' => 1, 'seq_4_day' => 14,
			'seq_4_objet'   => 'À mi-parcours',
			'seq_4_corps'   => "Bonjour {{prenom}},\n\nTu es à la moitié du chemin. C'est souvent là que les choses se déposent vraiment.\n\nContinue avec douceur — tu n'as rien à prouver, juste à laisser faire." . $sig,

			'seq_5_enabled' => 1, 'seq_5_day' => 21,
			'seq_5_objet'   => 'Laisser intégrer',
			'seq_5_corps'   => "Bonjour {{prenom}},\n\nDans cette dernière ligne, l'important n'est plus de « faire » mais de laisser l'expérience s'installer.\n\nOffre-toi des temps de calme cette semaine." . $sig,

			'seq_6_enabled' => 1, 'seq_6_day' => 28,
			'seq_6_objet'   => 'La fin du parcours (et la suite)',
			'seq_6_corps'   => "Bonjour {{prenom}},\n\nLe programme touche à sa fin. Prends un moment pour mesurer le chemin parcouru depuis le premier jour.\n\nSi tu souhaites prolonger ce travail, écris-moi — on verra ensemble ce qui a du sens pour toi.\n\nMerci pour ta confiance tout au long de ce mois." . $sig,
		];
	}
}
