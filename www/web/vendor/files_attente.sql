-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : jeu. 25 juin 2026 à 12:08
-- Version du serveur : 8.0.31
-- Version de PHP : 8.0.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `files_attente`
--

DELIMITER $$
--
-- Procédures
--
DROP PROCEDURE IF EXISTS `sp_recalcul_duree_estimee_veille`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_recalcul_duree_estimee_veille` ()   BEGIN
    DECLARE v_ss_id        INT UNSIGNED;
    DECLARE v_nouvelle     INT UNSIGNED;
    DECLARE v_ancienne     INT UNSIGNED;
    DECLARE v_nb_obs       INT UNSIGNED;
    DECLARE done           INT DEFAULT FALSE;

    DECLARE cur CURSOR FOR
        SELECT id FROM sous_services WHERE statut = 'actif';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;
    boucle: LOOP
        FETCH cur INTO v_ss_id;
        IF done THEN LEAVE boucle; END IF;

        -- Moyenne des durées réelles d'HIER uniquement, valeurs
        -- aberrantes exclues (< 60s ou > 10800s, cf. trg_historique_aberrant)
        SELECT
            COUNT(*),
            ROUND(AVG(duree_reelle))
        INTO v_nb_obs, v_nouvelle
        FROM historique_durees
        WHERE sous_service_id = v_ss_id
          AND est_aberrant     = 0
          AND CAST(heure_debut AS DATE) = CURDATE() - INTERVAL 1 DAY;

        -- Au moins 1 consultation valable hier pour mettre à jour ;
        -- sinon on conserve la durée actuelle (pas de remise à zéro).
        IF v_nb_obs >= 1 AND v_nouvelle IS NOT NULL THEN
            SELECT duree_estimee INTO v_ancienne
            FROM sous_services WHERE id = v_ss_id;

            UPDATE sous_services
               SET duree_estimee = v_nouvelle
             WHERE id = v_ss_id;

            INSERT INTO logs_estimation
                (sous_service_id, date_calcul, nb_observations, ancienne_duree, nouvelle_duree)
            VALUES
                (v_ss_id, CURDATE(), v_nb_obs, v_ancienne, v_nouvelle);
        END IF;

    END LOOP;
    CLOSE cur;
END$$

DROP PROCEDURE IF EXISTS `sp_recalcul_estimation_nuit`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_recalcul_estimation_nuit` ()   BEGIN
    DECLARE v_ss_id        INT UNSIGNED;
    DECLARE v_nouvelle     INT UNSIGNED;
    DECLARE v_ancienne     INT UNSIGNED;
    DECLARE v_nb_obs       INT UNSIGNED;
    DECLARE done           INT DEFAULT FALSE;

    DECLARE cur CURSOR FOR
        SELECT id FROM sous_services WHERE statut = 'actif';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;
    boucle: LOOP
        FETCH cur INTO v_ss_id;
        IF done THEN LEAVE boucle; END IF;

        -- Moyenne pondérée sur les 100 dernières durées non aberrantes
        SELECT
            COUNT(*),
            ROUND(
                SUM(duree_reelle / rang_obs) /
                NULLIF(SUM(1.0   / rang_obs), 0)
            )
        INTO v_nb_obs, v_nouvelle
        FROM (
            SELECT
                duree_reelle,
                ROW_NUMBER() OVER (
                    PARTITION BY sous_service_id
                    ORDER BY created_at DESC
                ) AS rang_obs
            FROM historique_durees
            WHERE sous_service_id = v_ss_id
              AND est_aberrant     = 0
            LIMIT 100
        ) ranked;

        -- Minimum 5 observations requises pour mettre à jour
        IF v_nb_obs >= 5 AND v_nouvelle IS NOT NULL THEN
            SELECT duree_estimee INTO v_ancienne
            FROM sous_services WHERE id = v_ss_id;

            UPDATE sous_services
               SET duree_estimee = v_nouvelle
             WHERE id = v_ss_id;

            INSERT INTO logs_estimation
                (sous_service_id, date_calcul, nb_observations, ancienne_duree, nouvelle_duree)
            VALUES
                (v_ss_id, CURDATE(), v_nb_obs, v_ancienne, v_nouvelle);
        END IF;

    END LOOP;
    CLOSE cur;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `configuration_hopital`
--

DROP TABLE IF EXISTS `configuration_hopital`;
CREATE TABLE IF NOT EXISTS `configuration_hopital` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom_hopital` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setup_completed` tinyint(1) DEFAULT '0',
  `date_installation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_mise_a_jour` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `configuration_hopital`
--

INSERT INTO `configuration_hopital` (`id`, `nom_hopital`, `adresse`, `telephone`, `email`, `logo_path`, `setup_completed`, `date_installation`, `date_mise_a_jour`) VALUES
(1, 'CMA de Tyo', 'Entrée domicile Tankou', '694319623', 'cmatyo@gmail.com', 'public/uploads/hopital/logo_1780515899.jpg', 1, '2026-06-03 14:50:03', '2026-06-03 20:44:59');

-- --------------------------------------------------------

--
-- Structure de la table `conges_gestionnaires`
--

DROP TABLE IF EXISTS `conges_gestionnaires`;
CREATE TABLE IF NOT EXISTS `conges_gestionnaires` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `gestionnaire_id` int UNSIGNED NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `motif` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gest_conge` (`gestionnaire_id`,`date_debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `conges_medecins`
--

DROP TABLE IF EXISTS `conges_medecins`;
CREATE TABLE IF NOT EXISTS `conges_medecins` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `medecin_id` int UNSIGNED NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `motif` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_medecin_conge` (`medecin_id`,`date_debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `consultations`
--

DROP TABLE IF EXISTS `consultations`;
CREATE TABLE IF NOT EXISTS `consultations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` int UNSIGNED NOT NULL,
  `sous_service_id` int UNSIGNED NOT NULL,
  `medecin_id` int UNSIGNED DEFAULT NULL COMMENT 'Médecin qui prend en charge',
  `emploi_temps_id` int UNSIGNED DEFAULT NULL COMMENT 'Créneau de l''emploi du temps réservé',
  `qr_code_id` int UNSIGNED DEFAULT NULL COMMENT 'QR code utilisé pour la prise de rendez-vous',
  `statut` enum('en_attente','confirme','en_cours','en_pause','traite','annule','absent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `rang` int UNSIGNED DEFAULT NULL COMMENT 'Position dans la file d''attente du jour',
  `mode_prise` enum('LIGNE','PLACE','MANUEL','QR_CODE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PLACE' COMMENT 'LIGNE=domicile, PLACE=QR Code scanné, MANUEL=Saisie manuelle, QR_CODE=Généré par QR',
  `heure_emission` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Heure de création du ticket',
  `heure_passage_estimee` datetime DEFAULT NULL COMMENT 'Heure de passage calculée (duree_estimee × rang)',
  `heure_debut_reelle` datetime DEFAULT NULL COMMENT 'Heure réelle de début de consultation',
  `heure_fin_reelle` datetime DEFAULT NULL COMMENT 'Heure réelle de fin de consultation',
  `heure_pause` datetime DEFAULT NULL COMMENT 'Heure de mise en pause (départ pour examen externe)',
  `motif_pause` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Motif de la pause (ex: Radio, Analyse sanguine…)',
  `duree_pause_cumulee` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Durée totale cumulée des pauses en secondes',
  `priorite_retour` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Priorité absolue au retour d''examen externe',
  `duree_estimee` int UNSIGNED DEFAULT NULL COMMENT 'Durée estimée en secondes au moment de la réservation',
  `motif` text COLLATE utf8mb4_unicode_ci COMMENT 'Motif de la consultation (optionnel)',
  `prochain_rdv_id` int UNSIGNED DEFAULT NULL COMMENT 'ID de la consultation de suivi planifiée par le médecin',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_consult_patient` (`patient_id`),
  KEY `idx_consult_ss` (`sous_service_id`),
  KEY `idx_consult_medecin` (`medecin_id`),
  KEY `idx_consult_edt` (`emploi_temps_id`),
  KEY `idx_consult_statut` (`statut`),
  KEY `idx_consult_emission` (`heure_emission`),
  KEY `idx_consult_rang` (`sous_service_id`,`rang`),
  KEY `idx_consult_qrcode` (`qr_code_id`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Consultations des patients (remplace rendez_vous)';

--
-- Déchargement des données de la table `consultations`
--

INSERT INTO `consultations` (`id`, `patient_id`, `sous_service_id`, `medecin_id`, `emploi_temps_id`, `qr_code_id`, `statut`, `rang`, `mode_prise`, `heure_emission`, `heure_passage_estimee`, `heure_debut_reelle`, `heure_fin_reelle`, `heure_pause`, `motif_pause`, `duree_pause_cumulee`, `priorite_retour`, `duree_estimee`, `motif`, `prochain_rdv_id`, `created_at`) VALUES
(1, 1, 3, 1, NULL, NULL, 'traite', 1, 'PLACE', '2026-06-04 21:13:00', '2026-06-04 21:13:00', '2026-06-04 21:13:47', '2026-06-04 21:25:08', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-04 21:13:00'),
(2, 2, 3, 1, NULL, NULL, 'traite', 2, 'PLACE', '2026-06-04 21:23:13', '2026-06-04 21:23:13', '2026-06-04 21:25:12', '2026-06-04 21:43:20', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-04 21:23:13'),
(3, 3, 3, 1, NULL, NULL, 'en_cours', 1, 'PLACE', '2026-06-05 12:56:38', '2026-06-05 12:56:38', '2026-06-05 13:24:20', NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-05 12:56:38'),
(4, 1, 3, 1, NULL, NULL, 'en_attente', 1, 'PLACE', '2026-06-06 20:29:05', '2026-06-06 20:29:05', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-06 20:29:05'),
(5, 1, 3, 1, NULL, NULL, 'en_attente', 1, 'PLACE', '2026-06-07 20:31:22', '2026-06-08 20:31:22', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-07 20:31:22'),
(6, 4, 3, 1, NULL, NULL, 'en_cours', 2, 'PLACE', '2026-06-07 20:52:51', '2026-06-07 20:52:51', '2026-06-07 21:32:55', NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-07 20:52:51'),
(7, 1, 3, 1, NULL, NULL, 'en_attente', 1, 'PLACE', '2026-06-11 20:00:17', '2026-06-11 20:00:17', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-11 20:00:17'),
(8, 2, 3, 1, NULL, NULL, 'traite', 1, 'PLACE', '2026-06-12 09:41:39', '2026-06-12 09:41:39', '2026-06-12 10:21:44', '2026-06-12 10:22:01', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-12 09:41:39'),
(9, 1, 3, 1, NULL, NULL, 'traite', 2, 'PLACE', '2026-06-12 10:21:28', '2026-06-12 10:21:28', '2026-06-12 10:21:42', '2026-06-12 10:22:04', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-12 10:21:28'),
(10, 3, 3, 1, NULL, NULL, 'absent', 3, 'PLACE', '2026-06-12 10:23:18', '2026-06-12 10:23:18', '2026-06-12 10:26:08', NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-12 10:23:18'),
(11, 5, 3, 1, NULL, NULL, 'absent', 4, 'PLACE', '2026-06-12 10:24:09', '2026-06-12 10:24:09', '2026-06-12 10:26:06', NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-12 10:24:09'),
(12, 6, 3, 1, NULL, NULL, 'traite', 5, 'PLACE', '2026-06-12 10:25:26', '2026-06-12 10:25:26', '2026-06-12 10:26:04', '2026-06-12 10:26:29', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-12 10:25:26'),
(13, 7, 3, 1, NULL, NULL, 'traite', 6, 'PLACE', '2026-06-12 10:27:25', '2026-06-12 10:27:25', '2026-06-12 10:27:41', '2026-06-12 22:23:33', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-12 10:27:25'),
(14, 2, 3, 1, NULL, NULL, 'en_attente', 1, 'PLACE', '2026-06-13 07:33:36', '2026-06-14 07:33:36', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-13 07:33:36'),
(15, 3, 3, 1, NULL, NULL, 'en_attente', 2, 'PLACE', '2026-06-13 07:34:19', '2026-06-14 08:03:36', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-13 07:34:19'),
(16, 1, 3, 1, NULL, NULL, 'en_attente', 3, 'PLACE', '2026-06-13 07:55:07', '2026-06-14 08:33:36', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-13 07:55:07'),
(17, 1, 3, 1, NULL, NULL, 'absent', 1, 'PLACE', '2026-06-17 11:07:53', '2026-06-18 08:00:00', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-17 11:07:53'),
(18, 3, 3, 1, NULL, NULL, 'absent', 2, 'PLACE', '2026-06-17 11:08:22', '2026-06-18 08:30:00', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-17 11:08:22'),
(19, 2, 3, 1, NULL, NULL, 'absent', 3, 'PLACE', '2026-06-17 11:25:06', '2026-06-18 09:00:00', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-17 11:25:06'),
(20, 8, 3, 1, NULL, NULL, 'traite', 4, 'PLACE', '2026-06-17 11:41:29', '2026-06-17 11:41:29', '2026-06-17 11:51:08', '2026-06-17 12:10:51', NULL, NULL, 529, 0, 1800, NULL, NULL, '2026-06-17 11:41:29'),
(21, 9, 3, 1, NULL, NULL, 'traite', 5, 'PLACE', '2026-06-17 11:56:18', '2026-06-17 12:11:29', '2026-06-17 11:56:58', '2026-06-17 12:05:27', NULL, NULL, 0, 0, 1800, NULL, 23, '2026-06-17 11:56:18'),
(22, 10, 3, 1, NULL, NULL, 'traite', 6, 'PLACE', '2026-06-17 12:02:08', '2026-06-17 12:41:29', '2026-06-17 12:11:03', '2026-06-17 12:21:08', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-17 12:02:08'),
(23, 9, 3, 1, NULL, NULL, 'absent', 4, 'MANUEL', '2026-06-17 12:02:44', '2026-06-18 09:30:00', NULL, NULL, NULL, NULL, 0, 0, 1800, 'Suivi', NULL, '2026-06-17 12:02:44'),
(24, 11, 3, 1, NULL, NULL, 'traite', 7, 'PLACE', '2026-06-17 12:13:54', '2026-06-17 13:11:29', '2026-06-17 12:21:14', '2026-06-17 12:36:08', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-17 12:13:54'),
(25, 12, 3, 1, NULL, NULL, 'traite', 8, 'PLACE', '2026-06-17 12:37:59', '2026-06-17 12:51:14', '2026-06-17 12:38:36', '2026-06-17 13:21:09', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-17 12:37:59'),
(26, 13, 3, 1, NULL, NULL, 'absent', 9, 'PLACE', '2026-06-17 12:39:24', '2026-06-17 12:51:14', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-17 12:39:24'),
(27, 3, 3, 1, NULL, NULL, 'traite', 1, 'PLACE', '2026-06-22 21:42:29', '2026-06-22 21:42:29', '2026-06-22 22:09:15', '2026-06-22 22:21:44', NULL, NULL, 0, 0, 1800, NULL, 30, '2026-06-22 21:42:29'),
(28, 3, 3, 1, NULL, NULL, 'absent', 1, 'MANUEL', '2026-06-22 22:09:36', '2026-06-23 08:00:00', NULL, NULL, NULL, NULL, 0, 0, 1800, 'Suivi', NULL, '2026-06-22 22:09:36'),
(29, 2, 3, 1, NULL, NULL, 'absent', 2, 'PLACE', '2026-06-22 22:20:35', '2026-06-22 22:39:15', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-22 22:20:35'),
(30, 3, 3, 1, NULL, NULL, 'absent', 1, 'MANUEL', '2026-06-22 22:21:39', '2026-06-24 12:00:00', NULL, NULL, NULL, NULL, 0, 0, 1800, 'Suivi', NULL, '2026-06-22 22:21:39'),
(31, 1, 3, 1, NULL, NULL, 'absent', 3, 'PLACE', '2026-06-22 22:57:13', '2026-06-22 22:39:15', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-22 22:57:13'),
(32, 1, 3, 1, NULL, NULL, 'absent', 4, 'PLACE', '2026-06-22 22:58:16', '2026-06-22 22:39:15', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-22 22:58:16'),
(33, 11, 3, 1, NULL, NULL, 'traite', 1, 'PLACE', '2026-06-23 18:36:06', '2026-06-23 18:36:06', '2026-06-23 19:32:31', '2026-06-23 19:34:04', NULL, NULL, 0, 0, 1800, NULL, 36, '2026-06-23 18:36:06'),
(34, 2, 3, 1, NULL, NULL, 'absent', 2, 'PLACE', '2026-06-23 19:13:49', '2026-06-23 19:06:06', '2026-06-23 19:34:15', NULL, '2026-06-23 19:38:07', 'radio', 0, 1, 1800, NULL, NULL, '2026-06-23 19:13:49'),
(35, 3, 3, 1, NULL, NULL, 'traite', 3, 'PLACE', '2026-06-23 19:14:24', '2026-06-23 19:36:06', '2026-06-23 19:38:13', '2026-06-23 19:45:44', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-23 19:14:24'),
(36, 11, 3, 1, NULL, NULL, 'absent', 1, 'MANUEL', '2026-06-23 19:33:51', '2026-06-25 09:00:00', NULL, NULL, NULL, NULL, 0, 0, 1800, 'Suivi', NULL, '2026-06-23 19:33:51'),
(37, 14, 3, 1, NULL, NULL, 'traite', 1, 'PLACE', '2026-06-24 07:17:31', '2026-06-24 08:00:00', '2026-06-24 07:28:12', '2026-06-24 07:52:28', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 07:17:31'),
(38, 6, 3, 3, NULL, NULL, 'traite', 2, 'PLACE', '2026-06-24 07:18:13', '2026-06-24 08:30:00', '2026-06-24 07:28:27', '2026-06-24 07:54:03', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 07:18:13'),
(39, 8, 3, 1, NULL, NULL, 'traite', 3, 'PLACE', '2026-06-24 07:51:20', '2026-06-24 07:58:27', '2026-06-24 07:52:33', '2026-06-24 08:12:01', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 07:51:20'),
(40, 1, 3, 1, NULL, NULL, 'absent', 4, 'PLACE', '2026-06-24 11:23:26', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 11:23:26'),
(41, 11, 3, 1, NULL, NULL, 'absent', 5, 'PLACE', '2026-06-24 12:23:54', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 12:23:54'),
(42, 11, 3, 1, NULL, NULL, 'absent', 6, 'PLACE', '2026-06-24 13:11:01', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 13:11:01'),
(43, 11, 3, 1, NULL, NULL, 'absent', 7, 'PLACE', '2026-06-24 13:14:13', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 13:14:13'),
(44, 11, 3, 1, NULL, NULL, 'absent', 8, 'PLACE', '2026-06-24 13:22:01', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 13:22:01'),
(45, 11, 3, 1, NULL, NULL, 'absent', 9, 'PLACE', '2026-06-24 13:22:41', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 13:22:41'),
(46, 11, 3, 1, NULL, NULL, 'absent', 10, 'PLACE', '2026-06-24 13:25:38', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 13:25:38'),
(47, 11, 3, 1, NULL, NULL, 'absent', 11, 'PLACE', '2026-06-24 13:30:09', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 13:30:09'),
(48, 11, 3, 1, NULL, NULL, 'traite', 12, 'PLACE', '2026-06-24 13:34:10', '2026-06-24 07:58:27', '2026-06-24 13:34:36', '2026-06-24 13:39:48', NULL, NULL, 144, 0, 1800, NULL, NULL, '2026-06-24 13:34:10'),
(49, 15, 3, 3, NULL, NULL, 'absent', 13, 'PLACE', '2026-06-24 13:35:18', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 13:35:18'),
(50, 16, 3, 3, NULL, NULL, 'absent', 14, 'PLACE', '2026-06-24 13:36:01', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 13:36:01'),
(51, 15, 3, 1, NULL, NULL, 'absent', 15, 'PLACE', '2026-06-24 13:37:37', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 13:37:37'),
(52, 3, 3, 1, NULL, NULL, 'absent', 16, 'PLACE', '2026-06-24 13:38:05', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 13:38:05'),
(53, 15, 3, 1, NULL, NULL, 'traite', 17, 'PLACE', '2026-06-24 13:43:04', '2026-06-24 07:58:27', '2026-06-24 13:44:27', '2026-06-24 14:18:17', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 13:43:04'),
(54, 16, 3, 3, NULL, NULL, 'absent', 18, 'PLACE', '2026-06-24 13:43:50', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 13:43:50'),
(55, 7, 3, 3, NULL, NULL, 'absent', 19, 'PLACE', '2026-06-24 13:44:08', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 13:44:08'),
(56, 7, 3, 3, NULL, NULL, 'absent', 20, 'PLACE', '2026-06-24 14:09:41', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 14:09:41'),
(57, 7, 3, 3, NULL, NULL, 'traite', 21, 'PLACE', '2026-06-24 14:10:50', '2026-06-24 07:58:27', '2026-06-24 14:11:01', '2026-06-24 14:18:24', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 14:10:50'),
(58, 7, 3, 1, NULL, NULL, 'absent', 22, 'PLACE', '2026-06-24 20:09:27', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 20:09:27'),
(59, 16, 3, 1, NULL, NULL, 'absent', 23, 'PLACE', '2026-06-24 20:23:33', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 20:23:33'),
(60, 16, 3, 1, NULL, NULL, 'traite', 24, 'PLACE', '2026-06-24 20:35:15', '2026-06-24 07:58:27', '2026-06-24 20:35:37', '2026-06-24 20:46:33', NULL, NULL, 20, 0, 1800, NULL, NULL, '2026-06-24 20:35:15'),
(61, 17, 3, 1, NULL, NULL, 'traite', 25, 'PLACE', '2026-06-24 20:47:24', '2026-06-24 07:58:27', '2026-06-24 20:47:48', '2026-06-24 21:17:04', NULL, NULL, 40, 0, 1800, NULL, 62, '2026-06-24 20:47:24'),
(62, 17, 3, 1, NULL, NULL, 'absent', 1, 'MANUEL', '2026-06-24 20:48:49', '2026-06-26 08:00:00', NULL, NULL, NULL, NULL, 0, 0, 1800, 'Suivi', NULL, '2026-06-24 20:48:49'),
(63, 18, 3, 1, NULL, NULL, 'traite', 26, 'PLACE', '2026-06-24 21:18:26', '2026-06-24 07:58:27', '2026-06-24 21:19:45', '2026-06-24 21:27:52', NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 21:18:26'),
(64, 19, 3, 3, NULL, NULL, 'absent', 27, 'PLACE', '2026-06-24 21:19:10', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 21:19:10'),
(65, 20, 3, 1, NULL, NULL, 'absent', 28, 'PLACE', '2026-06-24 21:42:34', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 21:42:34'),
(66, 20, 3, 1, NULL, NULL, 'annule', 29, 'PLACE', '2026-06-24 21:42:55', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 21:42:55'),
(67, 20, 3, 1, NULL, NULL, 'absent', 30, 'PLACE', '2026-06-24 21:51:25', '2026-06-24 07:58:27', NULL, NULL, NULL, NULL, 0, 0, 1800, NULL, NULL, '2026-06-24 21:51:25');

--
-- Déclencheurs `consultations`
--
DROP TRIGGER IF EXISTS `trg_consult_enregistrer_duree`;
DELIMITER $$
CREATE TRIGGER `trg_consult_enregistrer_duree` AFTER UPDATE ON `consultations` FOR EACH ROW BEGIN
    IF NEW.statut = 'traite'
       AND OLD.statut != 'traite'
       AND NEW.heure_debut_reelle IS NOT NULL
       AND NEW.heure_fin_reelle   IS NOT NULL THEN

        INSERT INTO historique_durees
            (sous_service_id, consultation_id, medecin_id,
             duree_reelle, heure_debut,
             jour_semaine, tranche_horaire)
        VALUES (
            NEW.sous_service_id,
            NEW.id,
            NEW.medecin_id,
            TIMESTAMPDIFF(SECOND, NEW.heure_debut_reelle, NEW.heure_fin_reelle),
            NEW.heure_debut_reelle,
            DAYOFWEEK(NEW.heure_debut_reelle),   -- 1=Dim … 7=Sam (MySQL)
            HOUR(NEW.heure_debut_reelle)
        );

        -- Incrémenter le compteur de la session active
        UPDATE session_service
           SET nb_traites = nb_traites + 1
         WHERE gestionnaire_id IN (
             SELECT id FROM gestionnaires WHERE sous_service_id = NEW.sous_service_id
         )
           AND heure_fin IS NULL
        LIMIT 1;

    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_consult_increment_creneaux`;
DELIMITER $$
CREATE TRIGGER `trg_consult_increment_creneaux` AFTER INSERT ON `consultations` FOR EACH ROW BEGIN
    IF NEW.emploi_temps_id IS NOT NULL
       AND NEW.statut IN ('en_attente', 'confirme') THEN
        UPDATE emplois_du_temps
           SET nb_creneaux = nb_creneaux + 1
         WHERE id = NEW.emploi_temps_id;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_consult_liberer_creneau`;
DELIMITER $$
CREATE TRIGGER `trg_consult_liberer_creneau` AFTER UPDATE ON `consultations` FOR EACH ROW BEGIN
    IF NEW.statut IN ('annule', 'absent')
       AND OLD.statut NOT IN ('annule', 'absent')
       AND NEW.emploi_temps_id IS NOT NULL THEN
        UPDATE emplois_du_temps
           SET nb_creneaux = GREATEST(nb_creneaux - 1, 0)
         WHERE id = NEW.emploi_temps_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `emplois_du_temps`
--

DROP TABLE IF EXISTS `emplois_du_temps`;
CREATE TABLE IF NOT EXISTS `emplois_du_temps` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `sous_service_id` int UNSIGNED NOT NULL,
  `medecin_id` int UNSIGNED DEFAULT NULL COMMENT 'Médecin responsable du créneau',
  `jour` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `nb_creneaux` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Nombre de créneaux réservés sur ce planning',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_edt_ss` (`sous_service_id`),
  KEY `idx_edt_medecin` (`medecin_id`),
  KEY `idx_edt_jour` (`jour`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Planning journalier des sous-services par médecin';

--
-- Déchargement des données de la table `emplois_du_temps`
--

INSERT INTO `emplois_du_temps` (`id`, `sous_service_id`, `medecin_id`, `jour`, `heure_debut`, `heure_fin`, `nb_creneaux`, `created_at`) VALUES
(1, 3, 1, '2026-06-14', '08:00:00', '17:00:00', 0, '2026-06-14 12:45:32'),
(2, 3, 1, '2026-06-18', '09:00:00', '10:00:00', 1, '2026-06-17 12:02:44'),
(3, 3, 1, '2026-06-23', '08:00:00', '09:00:00', 1, '2026-06-22 22:09:36'),
(4, 3, 1, '2026-06-24', '12:00:00', '13:00:00', 1, '2026-06-22 22:21:39'),
(5, 3, 1, '2026-06-25', '09:00:00', '10:00:00', 1, '2026-06-23 19:33:51'),
(6, 3, 1, '2026-06-26', '08:00:00', '09:00:00', 1, '2026-06-24 20:48:49');

-- --------------------------------------------------------

--
-- Structure de la table `gestionnaires`
--

DROP TABLE IF EXISTS `gestionnaires`;
CREATE TABLE IF NOT EXISTS `gestionnaires` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `sous_service_id` int UNSIGNED NOT NULL COMMENT 'Sous-service auquel le gestionnaire est affecté',
  `nom` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Haché avec bcrypt (coût 12)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gestionnaires_email` (`email`),
  UNIQUE KEY `uq_gestionnaires_telephone` (`telephone`),
  KEY `idx_gestionnaires_ss` (`sous_service_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Agents de gestion — chacun affecté à un sous-service';

--
-- Déchargement des données de la table `gestionnaires`
--

INSERT INTO `gestionnaires` (`id`, `sous_service_id`, `nom`, `telephone`, `email`, `password`, `created_at`) VALUES
(1, 3, 'ange zutchi', '696945237', 'angezutchi@gmail.com', '$2y$12$VxDIOeSgSHPd181cFArQf.BB1cJnDKHAM0SUZlBOXstVkgyvvzvNK', '2026-06-04 12:25:18');

-- --------------------------------------------------------

--
-- Structure de la table `gestionnaire_jours_travail`
--

DROP TABLE IF EXISTS `gestionnaire_jours_travail`;
CREATE TABLE IF NOT EXISTS `gestionnaire_jours_travail` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `gestionnaire_id` int UNSIGNED NOT NULL,
  `jour_semaine` tinyint UNSIGNED NOT NULL COMMENT '1=Lundi à 7=Dimanche',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gest_jour` (`gestionnaire_id`,`jour_semaine`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `gestionnaire_jours_travail`
--

INSERT INTO `gestionnaire_jours_travail` (`id`, `gestionnaire_id`, `jour_semaine`, `actif`, `created_at`) VALUES
(1, 1, 1, 1, '2026-06-05 11:36:31'),
(2, 1, 2, 1, '2026-06-05 11:36:31'),
(3, 1, 3, 1, '2026-06-05 11:36:31'),
(4, 1, 4, 1, '2026-06-05 11:36:31'),
(5, 1, 5, 1, '2026-06-05 11:36:31');

-- --------------------------------------------------------

--
-- Structure de la table `historique_durees`
--

DROP TABLE IF EXISTS `historique_durees`;
CREATE TABLE IF NOT EXISTS `historique_durees` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `sous_service_id` int UNSIGNED NOT NULL,
  `consultation_id` int UNSIGNED DEFAULT NULL,
  `medecin_id` int UNSIGNED DEFAULT NULL,
  `duree_reelle` int UNSIGNED NOT NULL COMMENT 'Durée réelle en secondes',
  `heure_debut` datetime NOT NULL,
  `jour_semaine` tinyint UNSIGNED NOT NULL COMMENT '1=Lundi … 7=Dimanche',
  `tranche_horaire` tinyint UNSIGNED NOT NULL COMMENT 'Heure de début 0-23',
  `est_aberrant` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = exclue du calcul (< 60s ou > 10800s)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hist_ss` (`sous_service_id`),
  KEY `idx_hist_consultation` (`consultation_id`),
  KEY `idx_hist_medecin` (`medecin_id`),
  KEY `idx_hist_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique des durées réelles — base du recalcul nocturne';

--
-- Déchargement des données de la table `historique_durees`
--

INSERT INTO `historique_durees` (`id`, `sous_service_id`, `consultation_id`, `medecin_id`, `duree_reelle`, `heure_debut`, `jour_semaine`, `tranche_horaire`, `est_aberrant`, `created_at`) VALUES
(10, 3, 1, 1, 681, '2026-06-04 21:13:47', 5, 21, 0, '2026-06-04 21:25:08'),
(11, 3, 2, 1, 1088, '2026-06-04 21:25:12', 5, 21, 0, '2026-06-04 21:43:20'),
(12, 3, 8, 1, 17, '2026-06-12 10:21:44', 6, 10, 1, '2026-06-12 10:22:01'),
(13, 3, 9, 1, 22, '2026-06-12 10:21:42', 6, 10, 1, '2026-06-12 10:22:04'),
(14, 3, 12, 1, 25, '2026-06-12 10:26:04', 6, 10, 1, '2026-06-12 10:26:29'),
(15, 3, 13, 1, 42952, '2026-06-12 10:27:41', 6, 10, 1, '2026-06-12 22:23:33'),
(16, 3, 21, 1, 509, '2026-06-17 11:56:58', 4, 11, 0, '2026-06-17 12:05:27'),
(17, 3, 20, 1, 1183, '2026-06-17 11:51:08', 4, 11, 0, '2026-06-17 12:10:51'),
(18, 3, 22, 1, 605, '2026-06-17 12:11:03', 4, 12, 0, '2026-06-17 12:21:08'),
(19, 3, 24, 1, 894, '2026-06-17 12:21:14', 4, 12, 0, '2026-06-17 12:36:08'),
(20, 3, 25, 1, 2553, '2026-06-17 12:38:36', 4, 12, 0, '2026-06-17 13:21:09'),
(21, 3, 27, 1, 749, '2026-06-22 22:09:15', 2, 22, 0, '2026-06-22 22:21:44'),
(22, 3, 33, 1, 93, '2026-06-23 19:32:31', 3, 19, 0, '2026-06-23 19:34:04'),
(23, 3, 35, 1, 451, '2026-06-23 19:38:13', 3, 19, 0, '2026-06-23 19:45:44'),
(24, 3, 37, 1, 1456, '2026-06-24 07:28:12', 4, 7, 0, '2026-06-24 07:52:28'),
(25, 3, 38, 3, 1536, '2026-06-24 07:28:27', 4, 7, 0, '2026-06-24 07:54:03'),
(26, 3, 39, 1, 1168, '2026-06-24 07:52:33', 4, 7, 0, '2026-06-24 08:12:01'),
(27, 3, 48, 1, 312, '2026-06-24 13:34:36', 4, 13, 0, '2026-06-24 13:39:48'),
(28, 3, 53, 1, 2030, '2026-06-24 13:44:27', 4, 13, 0, '2026-06-24 14:18:17'),
(29, 3, 57, 3, 443, '2026-06-24 14:11:01', 4, 14, 0, '2026-06-24 14:18:24'),
(30, 3, 60, 1, 656, '2026-06-24 20:35:37', 4, 20, 0, '2026-06-24 20:46:33'),
(31, 3, 61, 1, 1756, '2026-06-24 20:47:48', 4, 20, 0, '2026-06-24 21:17:04'),
(32, 3, 63, 1, 487, '2026-06-24 21:19:45', 4, 21, 0, '2026-06-24 21:27:52');

--
-- Déclencheurs `historique_durees`
--
DROP TRIGGER IF EXISTS `trg_historique_aberrant`;
DELIMITER $$
CREATE TRIGGER `trg_historique_aberrant` BEFORE INSERT ON `historique_durees` FOR EACH ROW BEGIN
    IF NEW.duree_reelle < 60 OR NEW.duree_reelle > 10800 THEN
        SET NEW.est_aberrant = 1;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `horaires`
--

DROP TABLE IF EXISTS `horaires`;
CREATE TABLE IF NOT EXISTS `horaires` (
  `id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `jour` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci DEFAULT 'actif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `jours_travail`
--

DROP TABLE IF EXISTS `jours_travail`;
CREATE TABLE IF NOT EXISTS `jours_travail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `medecin_id` int NOT NULL,
  `jour` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `medecin_id` (`medecin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `logs_estimation`
--

DROP TABLE IF EXISTS `logs_estimation`;
CREATE TABLE IF NOT EXISTS `logs_estimation` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `sous_service_id` int UNSIGNED NOT NULL,
  `date_calcul` date NOT NULL,
  `nb_observations` int UNSIGNED NOT NULL DEFAULT '0',
  `ancienne_duree` int UNSIGNED NOT NULL COMMENT 'Durée avant recalcul (secondes)',
  `nouvelle_duree` int UNSIGNED NOT NULL COMMENT 'Durée après recalcul (secondes)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_ss` (`sous_service_id`),
  KEY `idx_logs_date` (`date_calcul`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Traçabilité des recalculs nocturnes de la moyenne pondérée';

--
-- Déchargement des données de la table `logs_estimation`
--

INSERT INTO `logs_estimation` (`id`, `sous_service_id`, `date_calcul`, `nb_observations`, `ancienne_duree`, `nouvelle_duree`, `created_at`) VALUES
(1, 3, '2026-06-25', 9, 1800, 1094, '2026-06-25 12:07:30');

-- --------------------------------------------------------

--
-- Structure de la table `medecins`
--

DROP TABLE IF EXISTS `medecins`;
CREATE TABLE IF NOT EXISTS `medecins` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialite` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('disponible','indisponible','conge') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disponible',
  `photo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Chemin vers la photo du médecin',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medecins_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Médecins intervenant dans les sous-services';

--
-- Déchargement des données de la table `medecins`
--

INSERT INTO `medecins` (`id`, `nom`, `prenom`, `specialite`, `telephone`, `email`, `password`, `statut`, `photo`, `created_at`) VALUES
(1, 'MATHILDE', 'MOUMI', 'Cardiologie', '0677453688', 'mathildemoumi@gmail.com', '$2y$12$Q4LNostbNffHMSb0NRfleO8IgloC5aeFowzz6D52P1k/LHhsK6Bx6', 'disponible', 'uploads/medecins/med_6a39a46268986.jpg', '2026-06-04 11:56:31'),
(2, 'MAGLOIRE', 'Ebele', 'Hématologie', '677889900', 'ebelemagloire@gmail.com', '$2y$10$oKdZTF726kgSu2G2fFlvSOASyyrAOcefyhGzS8SH3xTC610TzzNPS', 'disponible', NULL, '2026-06-16 19:34:26'),
(3, 'MOUMI', 'NEILL', 'Cardiologie', '695406634', 'neillmoumi@gmail.com', 'Ne1llmoum!', 'disponible', 'uploads/medecins/med_6a3b7997af253.jpg', '2026-06-24 07:16:01');

-- --------------------------------------------------------

--
-- Structure de la table `medecin_jours_travail`
--

DROP TABLE IF EXISTS `medecin_jours_travail`;
CREATE TABLE IF NOT EXISTS `medecin_jours_travail` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `medecin_id` int UNSIGNED NOT NULL,
  `jour_semaine` tinyint UNSIGNED NOT NULL COMMENT '1=Lundi à 7=Dimanche',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medecin_jour` (`medecin_id`,`jour_semaine`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Jours de travail des médecins';

--
-- Déchargement des données de la table `medecin_jours_travail`
--

INSERT INTO `medecin_jours_travail` (`id`, `medecin_id`, `jour_semaine`, `actif`, `created_at`) VALUES
(1, 1, 1, 1, '2026-06-05 11:33:32'),
(2, 1, 2, 1, '2026-06-05 11:33:32'),
(3, 1, 3, 1, '2026-06-05 11:33:32'),
(4, 1, 4, 1, '2026-06-05 11:33:32'),
(5, 1, 5, 1, '2026-06-05 11:33:32'),
(6, 1, 6, 1, '2026-06-05 11:33:32'),
(7, 2, 1, 1, '2026-06-16 19:34:26'),
(8, 2, 2, 1, '2026-06-16 19:34:26'),
(9, 2, 3, 1, '2026-06-16 19:34:26'),
(10, 2, 4, 1, '2026-06-16 19:34:26'),
(11, 2, 5, 1, '2026-06-16 19:34:26');

-- --------------------------------------------------------

--
-- Structure de la table `medecin_sous_service`
--

DROP TABLE IF EXISTS `medecin_sous_service`;
CREATE TABLE IF NOT EXISTS `medecin_sous_service` (
  `medecin_id` int UNSIGNED NOT NULL,
  `sous_service_id` int UNSIGNED NOT NULL,
  `date_affectation` date DEFAULT NULL,
  PRIMARY KEY (`medecin_id`,`sous_service_id`),
  KEY `idx_mss_ss` (`sous_service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Affectation médecin ↔ sous-service (est_chef = médecin chef)';

--
-- Déchargement des données de la table `medecin_sous_service`
--

INSERT INTO `medecin_sous_service` (`medecin_id`, `sous_service_id`, `date_affectation`) VALUES
(1, 3, '2026-06-04'),
(2, 4, '2026-06-16'),
(3, 3, '2026-06-24');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` int UNSIGNED NOT NULL,
  `consultation_id` int UNSIGNED DEFAULT NULL,
  `type` enum('CONFIRMATION','RAPPEL_J1','RAPPEL_15MIN','APPEL_IMMEDIAT','AVANCEMENT','DECALAGE','ANNULATION','CLOTURE_ABSENT','MAJ_HEURE','URGENCE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenu` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `canal` enum('FCM','SMS') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FCM',
  `statut` enum('en_attente','envoye','echec','lu') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_patient` (`patient_id`),
  KEY `idx_notif_consultation` (`consultation_id`),
  KEY `idx_notif_statut` (`statut`),
  KEY `idx_notif_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notifications envoyées aux patients (FCM ou SMS)';

-- --------------------------------------------------------

--
-- Structure de la table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expire_at` datetime NOT NULL,
  `utilise` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_expire_at` (`expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Codes de vérification pour la réinitialisation du mot de passe';

-- --------------------------------------------------------

--
-- Structure de la table `patients`
--

DROP TABLE IF EXISTS `patients`;
CREATE TABLE IF NOT EXISTS `patients` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NULL si inscription via QR Code uniquement (anonyme)',
  `token_fcm` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Token Firebase Cloud Messaging pour les push',
  `statut` enum('actif','suspendu','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_inscription` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_patients_email` (`email`),
  UNIQUE KEY `uq_patients_telephone` (`telephone`),
  KEY `idx_patients_statut` (`statut`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Patients / usagers du système';

--
-- Déchargement des données de la table `patients`
--

INSERT INTO `patients` (`id`, `nom`, `prenom`, `telephone`, `email`, `password`, `token_fcm`, `statut`, `date_inscription`) VALUES
(1, 'BOULA', 'AICHA', '65195688', 'boulaaicha@gmail.com', NULL, NULL, 'actif', '2026-06-04 21:13:00'),
(2, 'KAMENI', 'LOIC', '690870424', 'kameniloic@gmail.com', NULL, NULL, 'actif', '2026-06-04 21:23:13'),
(3, 'papao', 'andre', '677488969', 'andrepapao@gmail.com', NULL, NULL, 'actif', '2026-06-05 12:56:38'),
(4, 'KONDRE', 'GASPARD', '657788990', 'kondregaspard@gmail.com', NULL, NULL, 'actif', '2026-06-07 20:52:51'),
(5, 'Lao', 'minga', '655443322', 'laominga@gmail.com', NULL, NULL, 'actif', '2026-06-12 10:24:08'),
(6, 'ENDER', 'THOMAS', '677889900', 'enderthomas@gmail.com', NULL, NULL, 'actif', '2026-06-12 10:25:26'),
(7, 'Bopda', 'jean', '611223344', 'bopdajean@gmail.com', NULL, NULL, 'actif', '2026-06-12 10:27:24'),
(8, 'MOUMI', 'NEILL', '677401159', 'neillmoumi@gmail.com', NULL, NULL, 'actif', '2026-06-17 11:41:29'),
(9, 'ABENA', 'AMOS', '655321467', 'amosabena@gmail.com', NULL, NULL, 'actif', '2026-06-17 11:56:17'),
(10, 'Olomo', 'ondoa', '690987076', 'ondoaolomo@gmail.com', NULL, NULL, 'actif', '2026-06-17 12:02:08'),
(11, 'KENNE', 'AUDE', '650607080', 'kenneaude@gmail.com', NULL, NULL, 'actif', '2026-06-17 12:13:54'),
(12, 'CHAMBERLAIN', 'EMMA', '699887766', 'emmachamberlain@gmail.com', NULL, NULL, 'actif', '2026-06-17 12:37:59'),
(13, 'SITUATION', 'LENA', '650403020', 'lenasituation@gmail.com', NULL, NULL, 'actif', '2026-06-17 12:39:24'),
(14, 'BILOA', 'JEAN', '670605040', 'jeanbiloa@gmail.com', NULL, NULL, 'actif', '2026-06-24 07:17:31'),
(15, 'Ngono', 'Remi', '660504030', 'remingono@gmail.com', NULL, NULL, 'actif', '2026-06-24 13:35:17'),
(16, 'BONTO', 'PAUL', '666554433', 'paulbonto@gmail.com', NULL, NULL, 'actif', '2026-06-24 13:36:00'),
(17, 'WOULOU', 'GISCARD', '600998877', 'woulougiscard@gmail.com', NULL, NULL, 'actif', '2026-06-24 20:47:24'),
(18, 'BATACK', 'SAWA', '667788990', 'sawabatack@gmail.com', NULL, NULL, 'actif', '2026-06-24 21:18:26'),
(19, 'NGON', 'BULU', '665544332', 'bulungon@gmail.com', NULL, NULL, 'actif', '2026-06-24 21:19:09'),
(20, 'TSANG', 'BAMI', '657483920', 'bamitsang@gmail.com', NULL, NULL, 'actif', '2026-06-24 21:42:34');

-- --------------------------------------------------------

--
-- Structure de la table `patient_api_tokens`
--

DROP TABLE IF EXISTS `patient_api_tokens`;
CREATE TABLE IF NOT EXISTS `patient_api_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `token` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `patient_id` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `qr_codes`
--

DROP TABLE IF EXISTS `qr_codes`;
CREATE TABLE IF NOT EXISTS `qr_codes` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `sous_service_id` int UNSIGNED NOT NULL COMMENT 'Sous-service associé au QR code',
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Token unique pour l''URL du QR code',
  `qr_code_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Chemin vers l''image du QR code',
  `expire_at` datetime NOT NULL COMMENT 'Date d''expiration du QR code',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Contenu encodé dans le QR code (URL)',
  `scan_count` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Nombre de fois que le QR code a été scanné',
  `statut` enum('actif','inactif','expire') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif' COMMENT 'Statut du QR code',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création',
  `created_by` int UNSIGNED DEFAULT NULL COMMENT 'ID du gestionnaire qui a généré le QR code',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qrcodes_token` (`token`),
  KEY `idx_qrcodes_ss` (`sous_service_id`),
  KEY `idx_qrcodes_token_idx` (`token`),
  KEY `idx_qrcodes_statut` (`statut`),
  KEY `idx_qrcodes_expire_at` (`expire_at`),
  KEY `idx_qrcodes_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='QR codes générés pour les sous-services';

--
-- Déchargement des données de la table `qr_codes`
--

INSERT INTO `qr_codes` (`id`, `sous_service_id`, `token`, `qr_code_path`, `expire_at`, `content`, `scan_count`, `statut`, `created_at`, `created_by`) VALUES
(2, 3, '6456d16d1681ec1825b56a7cacafec491f65e9c1c80bc1547b613336bae4d4f6', 'public/qrcodes/qrcode_3_1780654355.png', '2026-06-05 11:32:35', 'http://localhost/file-attente/scan_ticket.php?token=6456d16d1681ec1825b56a7cacafec491f65e9c1c80bc1547b613336bae4d4f6', 0, 'expire', '2026-06-05 11:12:36', 1),
(3, 3, 'cb372c4cfdd95df96ca3257dbf9b3d5176c13f0edf947f844ce5d06d4885ce94', 'public/qrcodes/qrcode_3_1780659573.png', '2026-06-05 12:59:33', 'http://localhost/file-attente/scan_ticket.php?token=cb372c4cfdd95df96ca3257dbf9b3d5176c13f0edf947f844ce5d06d4885ce94', 0, 'expire', '2026-06-05 12:39:33', 1),
(4, 3, '50450c623a78091cbe4a90f9c1d057c3840ce99802ea71aa5922705730b9b8d1', 'public/qrcodes/qrcode_3_1780774121.png', '2026-06-06 20:48:41', 'http://localhost/fil-attente2/scan_ticket.php?token=50450c623a78091cbe4a90f9c1d057c3840ce99802ea71aa5922705730b9b8d1', 0, 'expire', '2026-06-06 20:28:43', 1),
(5, 3, 'fa49a3c424b9146ad6e6c73d0e27ad855a1c8e1cad2374bd7a3462cbf63abcbe', 'public/qrcodes/qrcode_3_1780895995.png', '2026-06-08 06:39:55', 'http://localhost/fil-attente2/scan_ticket.php?token=fa49a3c424b9146ad6e6c73d0e27ad855a1c8e1cad2374bd7a3462cbf63abcbe', 0, 'expire', '2026-06-08 06:19:55', 1),
(6, 3, 'b659ad0c6865c79eaa70dabc5454bd22aa09a4a62d610993cff4779cf17f6ab4', 'public/qrcodes/qrcode_3_1781690292.png', '2026-06-17 11:18:12', 'http://localhost/file-attente/scan_ticket.php?token=b659ad0c6865c79eaa70dabc5454bd22aa09a4a62d610993cff4779cf17f6ab4', 0, 'expire', '2026-06-17 10:58:13', 1),
(7, 3, 'c51aeb45b31cd1cea8a3e5cf46c92619abffa6c3427a90355e947fc37ee0b38d', 'public/qrcodes/qrcode_3_1781692850.png', '2026-06-17 12:00:50', 'http://localhost/file-attente/scan_ticket.php?token=c51aeb45b31cd1cea8a3e5cf46c92619abffa6c3427a90355e947fc37ee0b38d', 0, 'expire', '2026-06-17 11:40:51', 1),
(8, 3, '12cea81a6cbc95422b636d732e781646b646662c18a0361d69c21dd43971f3c8', 'public/qrcodes/qrcode_3_1781697080.png', '2026-06-17 13:11:20', 'http://localhost/file-attente/scan_ticket.php?token=12cea81a6cbc95422b636d732e781646b646662c18a0361d69c21dd43971f3c8', 0, 'expire', '2026-06-17 12:51:21', 1),
(9, 3, '066c96d092639ed503db474dd6fef9642fc23da7667b311ca3a72ecf8d9ff90f', 'public/qrcodes/qrcode_3_1782160936.png', '2026-06-22 22:02:16', 'http://localhost/fil-attente3/scan_ticket.php?token=066c96d092639ed503db474dd6fef9642fc23da7667b311ca3a72ecf8d9ff90f', 0, 'expire', '2026-06-22 21:42:17', 1),
(10, 3, '531ddf80a10db66bc28d3be88c3899dfaf044fb47efe2b299f18334eb610bbdf', 'public/qrcodes/qrcode_3_1782283860.png', '2026-06-24 08:11:00', 'http://localhost/fil-attente3/scan_ticket.php?token=531ddf80a10db66bc28d3be88c3899dfaf044fb47efe2b299f18334eb610bbdf', 0, 'expire', '2026-06-24 07:51:01', 1),
(11, 3, '8231a3c73fc72092c594e373e2e15faa80a9015de6f3bcf157a0b6cb2d5b9b40', 'public/qrcodes/qrcode_3_1782296561.png', '2026-06-24 11:42:41', 'http://localhost/fil-attente3/scan_ticket.php?token=8231a3c73fc72092c594e373e2e15faa80a9015de6f3bcf157a0b6cb2d5b9b40', 0, 'expire', '2026-06-24 11:22:42', 1),
(12, 3, '278f208052e13036fed2af755921817ef923cdeebc5912a4357a4fed3914434b', 'public/qrcodes/qrcode_3_1782332853.png', '2026-06-24 21:47:33', 'http://localhost/fil-attente2/scan_ticket.php?token=278f208052e13036fed2af755921817ef923cdeebc5912a4357a4fed3914434b', 0, 'expire', '2026-06-24 21:27:34', 1);

-- --------------------------------------------------------

--
-- Structure de la table `services`
--

DROP TABLE IF EXISTS `services`;
CREATE TABLE IF NOT EXISTS `services` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom de l''hôpital / établissement',
  `description` text COLLATE utf8mb4_unicode_ci,
  `adresse` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `horaires_ouverture` time DEFAULT '08:00:00',
  `horaires_fermeture` time DEFAULT '18:00:00',
  `pause_debut` time DEFAULT NULL,
  `pause_fin` time DEFAULT NULL,
  `jours_fermeture` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `horaires` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ex : Lun-Ven 07h00-17h00',
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_services_statut` (`statut`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hôpitaux et établissements de santé';

--
-- Déchargement des données de la table `services`
--

INSERT INTO `services` (`id`, `nom`, `description`, `adresse`, `horaires_ouverture`, `horaires_fermeture`, `pause_debut`, `pause_fin`, `jours_fermeture`, `horaires`, `statut`, `created_at`) VALUES
(1, 'CMA Tyo de Baleng', 'Centre Médical d\'Arrondissement', 'PMI, entrée école normale', '08:00:00', '18:00:00', NULL, NULL, '', NULL, 'actif', '2026-06-04 11:54:36');

-- --------------------------------------------------------

--
-- Structure de la table `session_service`
--

DROP TABLE IF EXISTS `session_service`;
CREATE TABLE IF NOT EXISTS `session_service` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `gestionnaire_id` int UNSIGNED NOT NULL,
  `sous_service_id` int UNSIGNED NOT NULL,
  `heure_debut` datetime NOT NULL,
  `heure_fin` datetime DEFAULT NULL,
  `nb_traites` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Nombre de consultations traitées durant cette session',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sess_gestionnaire` (`gestionnaire_id`),
  KEY `idx_sess_ss` (`sous_service_id`),
  KEY `idx_sess_date` (`heure_debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sessions de travail des gestionnaires';

-- --------------------------------------------------------

--
-- Structure de la table `sous_services`
--

DROP TABLE IF EXISTS `sous_services`;
CREATE TABLE IF NOT EXISTS `sous_services` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` int UNSIGNED NOT NULL COMMENT 'Hôpital auquel appartient ce département',
  `nom` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ex : Hématologie, Oncologie',
  `description` text COLLATE utf8mb4_unicode_ci,
  `duree_rdv_defaut` int UNSIGNED NOT NULL DEFAULT '1800' COMMENT 'Durée par défaut en secondes (30 min)',
  `duree_estimee` int UNSIGNED NOT NULL DEFAULT '1800' COMMENT 'Durée estimée recalculée chaque nuit par moyenne pondérée (secondes)',
  `capacite_horaire` int UNSIGNED NOT NULL DEFAULT '10' COMMENT 'Nombre max de consultations par heure',
  `qr_code` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Contenu / token du QR Code dynamique propre à ce sous-service',
  `qr_expire_at` datetime DEFAULT NULL COMMENT 'Date d''expiration du QR Code (régénéré périodiquement)',
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ss_service` (`service_id`),
  KEY `idx_ss_statut` (`statut`),
  KEY `idx_ss_qrcode` (`qr_code`(191))
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Départements médicaux (Hématologie, Oncologie…) — niveau QR Code';

--
-- Déchargement des données de la table `sous_services`
--

INSERT INTO `sous_services` (`id`, `service_id`, `nom`, `description`, `duree_rdv_defaut`, `duree_estimee`, `capacite_horaire`, `qr_code`, `qr_expire_at`, `statut`, `created_at`) VALUES
(3, 1, 'Cardiologie', 'Pour tout problème lié au coeur, consultez le service de cardiologie', 1800, 1094, 10, NULL, NULL, 'actif', '2026-06-04 11:54:36'),
(4, 1, 'Hématologie', 'Pour les problèmes liés au sang', 1800, 1800, 2, NULL, NULL, 'actif', '2026-06-16 19:27:25');

-- --------------------------------------------------------

--
-- Structure de la table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` int UNSIGNED NOT NULL,
  `qr_code_id` int UNSIGNED NOT NULL,
  `consultation_id` int UNSIGNED DEFAULT NULL,
  `rang` int UNSIGNED NOT NULL,
  `heure_creation` datetime NOT NULL,
  `heure_debut_estimee` datetime DEFAULT NULL,
  `heure_fin_estimee` datetime DEFAULT NULL,
  `temps_attente_minutes` int UNSIGNED DEFAULT NULL,
  `statut` enum('en_attente','en_cours','termine','absent','annule') COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `qr_code_id` (`qr_code_id`),
  KEY `consultation_id` (`consultation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `urgences`
--

DROP TABLE IF EXISTS `urgences`;
CREATE TABLE IF NOT EXISTS `urgences` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` int UNSIGNED NOT NULL COMMENT 'Hôpital qui déclare l''urgence',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `priorite` tinyint UNSIGNED NOT NULL DEFAULT '1' COMMENT '1=haute 2=moyenne 3=basse',
  `statut` enum('ouverte','en_cours','cloturee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouverte',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_urgences_service` (`service_id`),
  KEY `idx_urgences_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Urgences déclarées par les hôpitaux';

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','gestionnaire','medecin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `medecin_id` int DEFAULT NULL,
  `gestionnaire_id` int DEFAULT NULL,
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci DEFAULT 'actif',
  `derniere_connexion` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `medecin_id` (`medecin_id`),
  KEY `gestionnaire_id` (`gestionnaire_id`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `email`, `nom`, `mot_de_passe`, `role`, `medecin_id`, `gestionnaire_id`, `statut`, `derniere_connexion`, `created_at`, `created_by`) VALUES
(1, 'ebelemagloire@gmail.com', 'Dr Ebele Magloire', '$2y$10$81j7JUKeiB4rzCNlyy5J5ubwvbuwQR3gRf9fNP73P.5S0apOk3Nfa', 'admin', 2, NULL, 'actif', '2026-06-22 21:41:01', '2026-06-03 13:50:03', NULL),
(2, 'mathildemoumi@gmail.com', 'MOUMI MATHILDE', '$2y$10$.2Mi3klTmw5xVzFvxU4H8.3a/r9OZeb4VmsKAY0qkhwXHCxiwHesG', 'medecin', 1, NULL, 'actif', '2026-06-24 21:16:56', '2026-06-04 10:56:31', NULL),
(3, 'angezutchi@gmail.com', 'ange zutchi', '$2y$10$gm7ASaNRH2VuHd9MPXsN..6IrXz6j8slmMZ2dwUPr6AWK0W1vK2Au', 'gestionnaire', NULL, 1, 'actif', '2026-06-25 12:38:59', '2026-06-04 11:25:18', NULL),
(4, 'neillmoumi@gmail.com', 'NEILL MOUMI', '$2y$10$w9q6PnVR08/ZBRIttd60FuW4n.oidPlzLbmivb3FgIKEyJIxLkL6W', 'medecin', 3, NULL, 'actif', '2026-06-24 21:16:48', '2026-06-24 06:16:01', NULL);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_file_attente`
-- (Voir ci-dessous la vue réelle)
--
DROP VIEW IF EXISTS `v_file_attente`;
CREATE TABLE IF NOT EXISTS `v_file_attente` (
`consultation_id` int unsigned
,`duree_estimee_sec` int unsigned
,`heure_passage_estimee` datetime
,`heure_pause` datetime
,`medecin_nom` varchar(201)
,`mode_prise` enum('LIGNE','PLACE','MANUEL','QR_CODE')
,`motif` text
,`motif_pause` varchar(255)
,`patient_nom` varchar(100)
,`patient_prenom` varchar(100)
,`patient_telephone` varchar(20)
,`rang` int unsigned
,`service_nom` varchar(200)
,`sous_service_nom` varchar(200)
,`statut` enum('en_attente','confirme','en_cours','en_pause','traite','annule','absent')
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_qrcodes_actifs`
-- (Voir ci-dessous la vue réelle)
--
DROP VIEW IF EXISTS `v_qrcodes_actifs`;
CREATE TABLE IF NOT EXISTS `v_qrcodes_actifs` (
`content` text
,`created_at` datetime
,`created_by` int unsigned
,`expire_at` datetime
,`gestionnaire_nom` varchar(150)
,`id` int unsigned
,`qr_code_path` varchar(500)
,`scan_count` int unsigned
,`service_nom` varchar(200)
,`sous_service_id` int unsigned
,`sous_service_nom` varchar(200)
,`statut` enum('actif','inactif','expire')
,`token` varchar(255)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_sous_services_complet`
-- (Voir ci-dessous la vue réelle)
--
DROP VIEW IF EXISTS `v_sous_services_complet`;
CREATE TABLE IF NOT EXISTS `v_sous_services_complet` (
`capacite_horaire` int unsigned
,`duree_estimee` int unsigned
,`gestionnaire_id` int unsigned
,`gestionnaire_nom` varchar(150)
,`gestionnaire_telephone` varchar(20)
,`qr_code` varchar(500)
,`service_adresse` varchar(300)
,`service_id` int unsigned
,`service_nom` varchar(200)
,`ss_id` int unsigned
,`ss_nom` varchar(200)
,`ss_statut` enum('actif','inactif')
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_stats_jour`
-- (Voir ci-dessous la vue réelle)
--
DROP VIEW IF EXISTS `v_stats_jour`;
CREATE TABLE IF NOT EXISTS `v_stats_jour` (
`absentes` decimal(23,0)
,`annulees` decimal(23,0)
,`duree_reelle_moy_sec` decimal(21,0)
,`en_attente` decimal(23,0)
,`jour` date
,`prises_en_ligne` decimal(23,0)
,`prises_sur_place` decimal(23,0)
,`service_id` int unsigned
,`service_nom` varchar(200)
,`sous_service_id` int unsigned
,`sous_service_nom` varchar(200)
,`total_consultations` bigint
,`traitees` decimal(23,0)
);

-- --------------------------------------------------------

--
-- Structure de la vue `v_file_attente`
--
DROP TABLE IF EXISTS `v_file_attente`;

DROP VIEW IF EXISTS `v_file_attente`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_file_attente`  AS SELECT `c`.`id` AS `consultation_id`, `c`.`rang` AS `rang`, `c`.`statut` AS `statut`, `c`.`mode_prise` AS `mode_prise`, `c`.`heure_passage_estimee` AS `heure_passage_estimee`, `c`.`heure_pause` AS `heure_pause`, `c`.`motif_pause` AS `motif_pause`, `c`.`motif` AS `motif`, `p`.`nom` AS `patient_nom`, `p`.`prenom` AS `patient_prenom`, `p`.`telephone` AS `patient_telephone`, `ss`.`nom` AS `sous_service_nom`, `ss`.`duree_estimee` AS `duree_estimee_sec`, `s`.`nom` AS `service_nom`, concat(`m`.`prenom`,' ',`m`.`nom`) AS `medecin_nom` FROM ((((`consultations` `c` join `patients` `p` on((`p`.`id` = `c`.`patient_id`))) join `sous_services` `ss` on((`ss`.`id` = `c`.`sous_service_id`))) join `services` `s` on((`s`.`id` = `ss`.`service_id`))) left join `medecins` `m` on((`m`.`id` = `c`.`medecin_id`))) WHERE ((`c`.`statut` in ('en_attente','confirme','en_cours','en_pause')) AND (cast(`c`.`heure_emission` as date) = curdate())) ORDER BY `ss`.`id` ASC, `c`.`rang` ASC  ;

-- --------------------------------------------------------

--
-- Structure de la vue `v_qrcodes_actifs`
--
DROP TABLE IF EXISTS `v_qrcodes_actifs`;

DROP VIEW IF EXISTS `v_qrcodes_actifs`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_qrcodes_actifs`  AS SELECT `qc`.`id` AS `id`, `qc`.`sous_service_id` AS `sous_service_id`, `qc`.`token` AS `token`, `qc`.`qr_code_path` AS `qr_code_path`, `qc`.`expire_at` AS `expire_at`, `qc`.`content` AS `content`, `qc`.`scan_count` AS `scan_count`, `qc`.`statut` AS `statut`, `qc`.`created_at` AS `created_at`, `qc`.`created_by` AS `created_by`, `ss`.`nom` AS `sous_service_nom`, `s`.`nom` AS `service_nom`, `g`.`nom` AS `gestionnaire_nom` FROM (((`qr_codes` `qc` join `sous_services` `ss` on((`ss`.`id` = `qc`.`sous_service_id`))) join `services` `s` on((`s`.`id` = `ss`.`service_id`))) left join `gestionnaires` `g` on((`g`.`id` = `qc`.`created_by`))) WHERE ((`qc`.`statut` = 'actif') AND (`qc`.`expire_at` > now())) ORDER BY `qc`.`created_at` AS `DESCdesc` ASC  ;

-- --------------------------------------------------------

--
-- Structure de la vue `v_sous_services_complet`
--
DROP TABLE IF EXISTS `v_sous_services_complet`;

DROP VIEW IF EXISTS `v_sous_services_complet`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_sous_services_complet`  AS SELECT `ss`.`id` AS `ss_id`, `ss`.`nom` AS `ss_nom`, `ss`.`duree_estimee` AS `duree_estimee`, `ss`.`capacite_horaire` AS `capacite_horaire`, `ss`.`qr_code` AS `qr_code`, `ss`.`statut` AS `ss_statut`, `s`.`id` AS `service_id`, `s`.`nom` AS `service_nom`, `s`.`adresse` AS `service_adresse`, `g`.`id` AS `gestionnaire_id`, `g`.`nom` AS `gestionnaire_nom`, `g`.`telephone` AS `gestionnaire_telephone` FROM ((`sous_services` `ss` join `services` `s` on((`s`.`id` = `ss`.`service_id`))) left join `gestionnaires` `g` on((`g`.`sous_service_id` = `ss`.`id`)))  ;

-- --------------------------------------------------------

--
-- Structure de la vue `v_stats_jour`
--
DROP TABLE IF EXISTS `v_stats_jour`;

DROP VIEW IF EXISTS `v_stats_jour`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_stats_jour`  AS SELECT `ss`.`id` AS `sous_service_id`, `ss`.`nom` AS `sous_service_nom`, `s`.`id` AS `service_id`, `s`.`nom` AS `service_nom`, cast(`c`.`heure_emission` as date) AS `jour`, count(`c`.`id`) AS `total_consultations`, sum((`c`.`statut` = 'traite')) AS `traitees`, sum((`c`.`statut` in ('en_attente','confirme'))) AS `en_attente`, sum((`c`.`statut` = 'absent')) AS `absentes`, sum((`c`.`statut` = 'annule')) AS `annulees`, sum((`c`.`mode_prise` = 'LIGNE')) AS `prises_en_ligne`, sum((`c`.`mode_prise` = 'PLACE')) AS `prises_sur_place`, round(avg((case when ((`c`.`heure_debut_reelle` is not null) and (`c`.`heure_fin_reelle` is not null)) then timestampdiff(SECOND,`c`.`heure_debut_reelle`,`c`.`heure_fin_reelle`) end)),0) AS `duree_reelle_moy_sec` FROM ((`consultations` `c` join `sous_services` `ss` on((`ss`.`id` = `c`.`sous_service_id`))) join `services` `s` on((`s`.`id` = `ss`.`service_id`))) GROUP BY `ss`.`id`, `ss`.`nom`, `s`.`id`, `s`.`nom`, cast(`c`.`heure_emission` as date)  ;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `fk_consult_edt` FOREIGN KEY (`emploi_temps_id`) REFERENCES `emplois_du_temps` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consult_medecin` FOREIGN KEY (`medecin_id`) REFERENCES `medecins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consult_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consult_qrcode` FOREIGN KEY (`qr_code_id`) REFERENCES `qr_codes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consult_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `emplois_du_temps`
--
ALTER TABLE `emplois_du_temps`
  ADD CONSTRAINT `fk_edt_medecin` FOREIGN KEY (`medecin_id`) REFERENCES `medecins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_edt_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `gestionnaires`
--
ALTER TABLE `gestionnaires`
  ADD CONSTRAINT `fk_gestionnaires_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `historique_durees`
--
ALTER TABLE `historique_durees`
  ADD CONSTRAINT `fk_hist_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hist_medecin` FOREIGN KEY (`medecin_id`) REFERENCES `medecins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hist_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `logs_estimation`
--
ALTER TABLE `logs_estimation`
  ADD CONSTRAINT `fk_logs_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `medecin_jours_travail`
--
ALTER TABLE `medecin_jours_travail`
  ADD CONSTRAINT `medecin_jours_travail_ibfk_1` FOREIGN KEY (`medecin_id`) REFERENCES `medecins` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `medecin_sous_service`
--
ALTER TABLE `medecin_sous_service`
  ADD CONSTRAINT `fk_mss_medecin` FOREIGN KEY (`medecin_id`) REFERENCES `medecins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mss_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notif_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD CONSTRAINT `fk_qrcodes_created_by` FOREIGN KEY (`created_by`) REFERENCES `gestionnaires` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_qrcodes_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `session_service`
--
ALTER TABLE `session_service`
  ADD CONSTRAINT `fk_sess_gestionnaire` FOREIGN KEY (`gestionnaire_id`) REFERENCES `gestionnaires` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sess_ss` FOREIGN KEY (`sous_service_id`) REFERENCES `sous_services` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `sous_services`
--
ALTER TABLE `sous_services`
  ADD CONSTRAINT `fk_ss_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`qr_code_id`) REFERENCES `qr_codes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `urgences`
--
ALTER TABLE `urgences`
  ADD CONSTRAINT `fk_urgences_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

DELIMITER $$
--
-- Évènements
--
DROP EVENT IF EXISTS `event_expire_qrcodes`$$
CREATE DEFINER=`root`@`localhost` EVENT `event_expire_qrcodes` ON SCHEDULE EVERY 1 HOUR STARTS '2026-05-27 16:24:23' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    UPDATE qr_codes 
    SET statut = 'expire' 
    WHERE expire_at <= NOW() 
      AND statut = 'actif';
END$$

DROP EVENT IF EXISTS `event_absence_auto`$$
CREATE DEFINER=`root`@`localhost` EVENT `event_absence_auto` ON SCHEDULE EVERY 1 MINUTE STARTS '2026-06-08 11:05:50' ON COMPLETION PRESERVE ENABLE DO BEGIN

  -- Cas 1 : patient en_attente ou confirme non démarré
  --         10 minutes après la fin de la consultation précédente → absent
  UPDATE consultations c
  JOIN (
    SELECT sous_service_id, medecin_id, MAX(heure_fin_reelle) AS derniere_fin
    FROM consultations
    WHERE statut = 'traite'
      AND CAST(heure_emission AS DATE) = CURDATE()
      AND heure_fin_reelle IS NOT NULL
    GROUP BY sous_service_id, medecin_id
  ) fin
    ON  fin.sous_service_id = c.sous_service_id
    AND fin.medecin_id      = c.medecin_id
  SET c.statut = 'absent'
  WHERE c.statut IN ('en_attente', 'confirme')
    AND CAST(c.heure_emission AS DATE) = CURDATE()
    AND c.heure_debut_reelle IS NULL
    AND fin.derniere_fin IS NOT NULL
    AND TIMESTAMPDIFF(MINUTE, fin.derniere_fin, NOW()) >= 10;

  -- Cas 2 (retiré) : un patient en pause pour un examen externe peut
  -- légitimement revenir après plus de 30 minutes ; il n'est donc plus
  -- jamais marqué "absent" automatiquement pendant sa pause.

END$$

DROP EVENT IF EXISTS `event_recalcul_duree_estimee`$$
CREATE DEFINER=`root`@`localhost` EVENT `event_recalcul_duree_estimee` ON SCHEDULE EVERY 1 DAY STARTS '2026-06-25 00:30:00' ON COMPLETION PRESERVE ENABLE COMMENT 'Recalcule chaque nuit duree_estimee de chaque sous-service à partir de la moyenne réelle des consultations terminées la veille.' DO CALL sp_recalcul_duree_estimee_veille()$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
