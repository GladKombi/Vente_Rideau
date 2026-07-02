-- ============================================
-- TABLE: realisations
-- ============================================
CREATE TABLE `realisations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boutique_id` int(11) NOT NULL,
  `titre` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `image_principale` varchar(500) NOT NULL,
  `categorie` enum('rideaux','voilages','stores','installation','sur_mesure','autre') DEFAULT 'autre',
  `date_realisation` date DEFAULT NULL,
  `client_nom` varchar(100) DEFAULT NULL,
  `client_ville` varchar(100) DEFAULT NULL,
  `prix_indicatif` decimal(10,2) DEFAULT NULL,
  `est_publie` tinyint(1) DEFAULT 1,
  `statut` int(11) DEFAULT 0,
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: realisation_likes
-- ============================================
CREATE TABLE `realisation_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `realisation_id` int(11) NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `date_like` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_like` (`realisation_id`,`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: realisation_images
-- ============================================
CREATE TABLE `realisation_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `realisation_id` int(11) NOT NULL,
  `image_url` varchar(500) NOT NULL,
  `ordre` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: demandes_service (optionnelle, pour suivi interne)
-- ============================================
CREATE TABLE `demandes_service` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `realisation_id` int(11) NOT NULL,
  `boutique_id` int(11) NOT NULL,
  `client_nom` varchar(100) NOT NULL,
  `client_email` varchar(100) DEFAULT NULL,
  `client_telephone` varchar(20) NOT NULL,
  `client_ville` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `type_produit` varchar(200) DEFAULT NULL,
  `budget_estime` decimal(10,2) DEFAULT NULL,
  `statut` enum('nouvelle','contactee','en_cours','terminee','annulee') DEFAULT 'nouvelle',
  `notes_admin` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_traitement` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- NOUVELLE TABLE : numeros_rideaux
-- Stocke les numéros de rideaux par boutique
-- ============================================
CREATE TABLE `numeros_rideaux` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `boutique_id` int(11) NOT NULL,
  `numero_rideau` varchar(50) NOT NULL COMMENT 'Numéro unique du rideau (ex: R001, R002, CASH-001)',
  `est_utilise` tinyint(1) DEFAULT 0 COMMENT '0 = disponible, 1 = déjà attribué à un stock',
  `date_creation` datetime DEFAULT current_timestamp(),
  `actif` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_numero_boutique` (`boutique_id`, `numero_rideau`),
  KEY `idx_boutique` (`boutique_id`),
  KEY `idx_disponible` (`boutique_id`, `est_utilise`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- MODIFICATION TABLE : stock
-- Ajouter une colonne pour lier un stock à un numéro de rideau
-- ============================================
ALTER TABLE `stock` 
ADD COLUMN `numero_rideau_id` int(11) DEFAULT NULL COMMENT 'ID du numéro de rideau attribué à ce stock' AFTER `produit_matricule`,
ADD KEY `idx_numero_rideau` (`numero_rideau_id`);

ALTER TABLE `realisations` DROP COLUMN `prix_indicatif`;