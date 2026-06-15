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
-- DONNÉES DE TEST (optionnel - pour avoir des réalisations à afficher)
-- ============================================
INSERT INTO `realisations` (`boutique_id`, `titre`, `description`, `image_principale`, `categorie`, `date_realisation`, `client_nom`, `client_ville`, `prix_indicatif`) VALUES
(1, 'Rideaux en velours pour salon luxueux', 'Magnifique installation de rideaux en velours bleu nuit pour un salon de 40m². Tissu premium avec doublure thermique pour une isolation parfaite.', 'https://images.unsplash.com/photo-1618220179428-22790b461013?w=800', 'rideaux', '2026-05-15', 'Mme Kabuo', 'Butembo', 450.00),
(1, 'Voilages aériens pour chambre parentale', 'Pose de voilages en organza de soie naturelle dans une chambre parentale. Effet vaporeux et lumineux garanti pour un réveil tout en douceur.', 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800', 'voilages', '2026-04-20', 'Dr Mulumba', 'Butembo', 280.00),
(2, 'Stores japonais motorisés pour bureau', 'Installation de stores japonais motorisés avec télécommande dans un bureau professionnel. Design épuré et contrôle précis de la lumière naturelle.', 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=800', 'stores', '2026-03-10', 'Cabinet Juridique Mbayahi', 'Beni', 720.00),
(1, 'Rideaux occultants pour cinéma maison', 'Création sur mesure de rideaux occultants en tissu triple épaisseur pour une salle de projection privée. Noir total garanti même en plein jour.', 'https://images.unsplash.com/photo-1616046229478-9901c5536a45?w=800', 'rideaux', '2026-02-28', 'M. Kambale', 'Butembo', 580.00),
(1, 'Voilages anti-UV pour véranda', 'Installation de voilages techniques anti-UV protégeant votre mobilier tout en laissant passer la lumière. Solution idéale pour les vérandas exposées.', 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=800', 'voilages', '2026-05-01', 'Famille Paluku', 'Butembo', 350.00),
(3, 'Rideaux traditionnels africains', 'Réalisation de rideaux en wax africain authentique pour une décoration chaleureuse et colorée. Chaque pièce est unique.', 'https://images.unsplash.com/photo-1616046229478-9901c5536a45?w=800', 'rideaux', '2026-04-10', 'Mme Kahindo', 'Bunia', 390.00);