-- Migration : détection automatique de connexion du médecin au dashboard
-- À exécuter une fois sur la base existante.

ALTER TABLE `medecins`
  ADD COLUMN `derniere_connexion_dashboard` datetime DEFAULT NULL
    COMMENT 'Horodatage du dernier accès au dashboard médecin - sert à détecter automatiquement un retard/une indisponibilité imprévue'
    AFTER `langue`;

ALTER TABLE `medecins`
  ADD KEY `idx_medecin_derniere_connexion` (`derniere_connexion_dashboard`);
