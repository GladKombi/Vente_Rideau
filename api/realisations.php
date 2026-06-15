<?php
// api/realisations.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Activer l'affichage des erreurs PHP (uniquement pour déboguer, à commenter en production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Connexion à la base de données
$connexionPath = __DIR__ . '/../connexion/connexion.php';
if (!file_exists($connexionPath)) {
    echo json_encode(['success' => false, 'error' => 'Fichier de connexion introuvable']);
    exit;
}
require_once $connexionPath;

// Vérifier que $pdo existe
if (!isset($pdo)) {
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']);
    exit;
}

// Récupérer l'action
$action = $_GET['action'] ?? 'liste';

// Initialiser la session pour les likes (lecture seule)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fonction pour obtenir les catégories
function getCategories() {
    return ['rideaux' => 'Rideaux', 'voilages' => 'Voilages', 'stores' => 'Stores', 'installation' => 'Installation', 'sur_mesure' => 'Sur mesure', 'autre' => 'Autre'];
}

try {
    switch ($action) {
        case 'liste':
            // Paramètres
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(50, max(1, (int)($_GET['limit'] ?? 9))); // max 50 pour sécurité
            $offset = ($page - 1) * $limit;
            $categorie = $_GET['categorie'] ?? null;
            $sort = $_GET['sort'] ?? 'recent';

            // Construction de la requête
            $where = "WHERE r.est_publie = 1 AND r.statut = 0";
            $params = [];

            if ($categorie && $categorie !== 'tous') {
                $where .= " AND r.categorie = :categorie";
                $params[':categorie'] = $categorie;
            }

            $orderBy = "ORDER BY r.date_creation DESC";
            if ($sort === 'popular') {
                $orderBy = "ORDER BY likes_count DESC, r.date_creation DESC";
            }

            // Compter le total
            $countSql = "SELECT COUNT(*) FROM realisations r $where";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Récupérer les données
            $sql = "
                SELECT r.*, 
                       b.nom as boutique_nom,
                       (SELECT COUNT(*) FROM realisation_likes rl WHERE rl.realisation_id = r.id) as likes_count,
                       (SELECT COUNT(*) FROM realisation_images WHERE realisation_id = r.id) as images_count
                FROM realisations r
                JOIN boutiques b ON r.boutique_id = b.id
                $where
                $orderBy
                LIMIT $limit OFFSET $offset
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $realisations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $realisations,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);
            break;

        case 'detail':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID invalide']);
                exit;
            }

            $sql = "
                SELECT r.*, b.nom as boutique_nom,
                       (SELECT COUNT(*) FROM realisation_likes rl WHERE rl.realisation_id = r.id) as likes_count
                FROM realisations r
                JOIN boutiques b ON r.boutique_id = b.id
                WHERE r.id = :id AND r.est_publie = 1 AND r.statut = 0
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            $realisation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$realisation) {
                echo json_encode(['success' => false, 'error' => 'Réalisation non trouvée']);
                exit;
            }

            // Récupérer les images supplémentaires
            $imgStmt = $pdo->prepare("SELECT id, image_url, ordre FROM realisation_images WHERE realisation_id = :id ORDER BY ordre ASC");
            $imgStmt->execute([':id' => $id]);
            $realisation['galerie'] = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

            // Vérifier si l'utilisateur a liké (via session)
            $sessionId = $_GET['session_id'] ?? '';
            if ($sessionId) {
                $likeStmt = $pdo->prepare("SELECT id FROM realisation_likes WHERE realisation_id = :rid AND session_id = :sid");
                $likeStmt->execute([':rid' => $id, ':sid' => $sessionId]);
                $realisation['liked_by_user'] = $likeStmt->fetchColumn() ? true : false;
            } else {
                $realisation['liked_by_user'] = false;
            }

            echo json_encode(['success' => true, 'data' => $realisation]);
            break;

        case 'like':
            $input = json_decode(file_get_contents('php://input'), true);
            $realisationId = (int)($input['realisation_id'] ?? 0);
            $sessionId = $input['session_id'] ?? '';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';

            if ($realisationId <= 0 || empty($sessionId)) {
                echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
                exit;
            }

            // Vérifier si le like existe déjà
            $checkStmt = $pdo->prepare("SELECT id FROM realisation_likes WHERE realisation_id = :rid AND session_id = :sid");
            $checkStmt->execute([':rid' => $realisationId, ':sid' => $sessionId]);
            $existing = $checkStmt->fetchColumn();

            if ($existing) {
                // Supprimer le like
                $deleteStmt = $pdo->prepare("DELETE FROM realisation_likes WHERE id = :id");
                $deleteStmt->execute([':id' => $existing]);
                $liked = false;
            } else {
                // Ajouter le like
                $insertStmt = $pdo->prepare("INSERT INTO realisation_likes (realisation_id, session_id, ip_address) VALUES (:rid, :sid, :ip)");
                $insertStmt->execute([':rid' => $realisationId, ':sid' => $sessionId, ':ip' => $ip]);
                $liked = true;
            }

            // Nouveau compteur
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM realisation_likes WHERE realisation_id = :rid");
            $countStmt->execute([':rid' => $realisationId]);
            $likesCount = (int)$countStmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'liked' => $liked,
                'likes_count' => $likesCount
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur base de données : ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}