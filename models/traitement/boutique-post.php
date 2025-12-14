<?php
# Se connecter à la BD
include '../../connexion/connexion.php';

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- NOTE SÉCURITÉ IMPORTANTE : Ajout de la protection CSRF recommandé ici ---
    
    try {
        // AJOUTER une boutique
        if (isset($_POST['ajouter_boutique'])) {
            $nom = htmlspecialchars(trim($_POST['nom']));
            $email = htmlspecialchars(trim($_POST['email']));
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $actif = isset($_POST['actif']) ? 1 : 0;

            $stmt = $pdo->prepare("INSERT INTO boutiques (nom, email, password, actif) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nom, $email, $password, $actif]);
            
            $_SESSION['flash_message'] = [
                'text' => "Boutique ajoutée avec succès !",
                'type' => "success"
            ];
            header('Location: ../../views/boutiques.php');
            exit;
        }

        // MODIFIER une boutique
        if (isset($_POST['modifier_boutique'])) {
            $id = $_POST['id'];
            $nom = htmlspecialchars(trim($_POST['nom']));
            $email = htmlspecialchars(trim($_POST['email']));
            $actif = isset($_POST['actif']) ? 1 : 0;

            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE boutiques SET nom = ?, email = ?, password = ?, actif = ? WHERE id = ?");
                $stmt->execute([$nom, $email, $password, $actif, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE boutiques SET nom = ?, email = ?, actif = ? WHERE id = ?");
                $stmt->execute([$nom, $email, $actif, $id]);
            }

            $_SESSION['flash_message'] = [
                'text' => "Boutique modifiée avec succès !",
                'type' => "success"
            ];
            header('Location: ../../views/boutiques.php');
            exit;
        }

        // DÉSACTIVER/ACTIVER une boutique
        if (isset($_POST['toggle_actif'])) {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT actif FROM boutiques WHERE id = ?");
            $stmt->execute([$id]);
            $boutique = $stmt->fetch();
            $new_actif = $boutique['actif'] ? 0 : 1;

            $stmt = $pdo->prepare("UPDATE boutiques SET actif = ? WHERE id = ?");
            $stmt->execute([$new_actif, $id]);
            
            $_SESSION['flash_message'] = [
                'text' => "Statut de la boutique mis à jour !",
                'type' => "info"
            ];
            header('Location: ../../views/boutiques.php');
            exit;
        }

        // SUPPRIMER une boutique (soft delete)
        if (isset($_POST['supprimer_boutique'])) {
            $id = $_POST['id'];

            // Ici, nous faisons un soft delete (statut = 1)
            $stmt = $pdo->prepare("UPDATE boutiques SET statut = 1 WHERE id = ?"); 
            $stmt->execute([$id]);
            
            $_SESSION['flash_message'] = [
                'text' => "Boutique supprimée (archivée) !",
                'type' => "warning"
            ];
            header('Location: ../../views/boutiques.php');
            exit;
        }
    } catch (PDOException $e) {
        // En cas d'erreur PDO, rediriger avec un message d'erreur
        error_log("DB Error: " . $e->getMessage()); // Log l'erreur
        
        $_SESSION['flash_message'] = [
            'text' => "Une erreur est survenue lors de l'opération.",
            'type' => "error"
        ];
        header('Location: ../../views/boutiques.php');
        exit;
    }
}