<?php
// facture-cash.php
include '../connexion/connexion.php';

// Vérification de l'authentification BOUTIQUE
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'boutique') {
    header('Location: ../login.php');
    exit;
}

// Récupérer l'ID de la boutique connectée
$boutique_id = $_SESSION['boutique_id'] ?? null;
if (!$boutique_id) {
    header('Location: ../login.php');
    exit;
}

// Récupération de l'ID de la commande
$commande_id = $_GET['id'] ?? null;
if (!$commande_id) {
    $_SESSION['flash_message'] = [
        'text' => "Aucun ID de commande spécifié.",
        'type' => "error"
    ];
    header('Location: ventes_boutique.php');
    exit;
}

// Récupérer la commande avec vérification boutique
try {
    $query = $pdo->prepare("
        SELECT c.*, b.nom as boutique_nom
        FROM commandes c
        JOIN boutiques b ON c.boutique_id = b.id
        WHERE c.id = ? AND c.boutique_id = ? AND c.statut = 0
    ");
    $query->execute([$commande_id, $boutique_id]);
    $commande = $query->fetch(PDO::FETCH_ASSOC);

    if (!$commande) {
        $_SESSION['flash_message'] = [
            'text' => "Commande non trouvée ou vous n'avez pas les droits d'accès.",
            'type' => "error"
        ];
        header('Location: ventes_boutique.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur de base de données: " . $e->getMessage(),
        'type' => "error"
    ];
    header('Location: ventes_boutique.php');
    exit;
}

// Récupérer les produits de cette commande (CORRIGÉ avec umProduit)
try {
    $query = $pdo->prepare("
        SELECT 
            cp.quantite,
            cp.prix_unitaire,
            p.matricule,
            p.designation,
            p.umProduit,
            (cp.quantite * cp.prix_unitaire) as total_ligne
        FROM commande_produits cp
        JOIN stock s ON cp.stock_id = s.id
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE cp.commande_id = ? 
          AND cp.statut = 0
          AND s.statut = 0
          AND p.statut = 0
        ORDER BY cp.id DESC
    ");
    $query->execute([$commande_id]);
    $produits_commande = $query->fetchAll(PDO::FETCH_ASSOC);

    // Calculer le total de la commande
    $total_commande = 0;
    foreach ($produits_commande as $produit) {
        $total_commande += $produit['total_ligne'];
    }
} catch (PDOException $e) {
    $produits_commande = [];
    $total_commande = 0;
    error_log("Erreur récupération produits: " . $e->getMessage());
}

// Récupérer les paiements pour cette commande (CORRIGÉ avec commandes_id)
try {
    $query = $pdo->prepare("
        SELECT p.* 
        FROM paiements p
        WHERE p.commandes_id = ? AND p.statut = 0
        ORDER BY p.date DESC, p.id DESC
    ");
    $query->execute([$commande_id]);
    $paiements = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le total payé
    $total_paye = 0;
    foreach ($paiements as $paiement) {
        $total_paye += $paiement['montant'];
    }
    
    // Calculer le reste à payer
    $reste_a_payer = $total_commande - $total_paye;
    
} catch (PDOException $e) {
    $paiements = [];
    $total_paye = 0;
    $reste_a_payer = $total_commande;
    error_log("Erreur récupération paiements: " . $e->getMessage());
}

// INFORMATIONS STATIQUES DE LA BOUTIQUE
$infos_boutique = [
    'nom' => 'NGS Boutique',
    'adresse' => 'Avenue du Commerce, Kinshasa',
    'telephone' => '+243 81 234 5678',
    'email' => 'boutique@ngs.cd',
    'rc' => 'CD/KIN/2024-B-00123',
    'nif' => 'NIF-2024-00123-A',
    'telephone2' => '+243 89 876 5432'
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Facture Cash #<?= htmlspecialchars($commande['numero_facture']) ?> - NGS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    @media print {
      .no-print, .print-controls, button, aside, header, footer, .sidebar-menu { 
        display: none !important; 
      }
      .facture-container { 
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
      }
      body {
        background-color: white !important;
        font-size: 12px !important;
      }
      .ticket-print {
        width: 100% !important;
        max-width: 100% !important;
        font-family: 'Inter', sans-serif !important;
      }
      .print-only {
        display: block !important;
      }
    }
    
    @media screen {
      .print-only {
        display: none;
      }
    }
    
    body {
      font-family: 'Inter', sans-serif;
    }
    
    .ticket-print {
      font-family: 'Inter', sans-serif;
      max-width: 400px;
    }
    
    /* Badge pour les unités */
    .unit-badge {
      display: inline-block;
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 10px;
      font-weight: 500;
    }
    
    .unit-metres {
      background-color: #dbeafe;
      color: #1e40af;
      border: 1px solid #93c5fd;
    }
    
    .unit-pieces {
      background-color: #dcfce7;
      color: #166534;
      border: 1px solid #86efac;
    }
  </style>
</head>
<body class="bg-gray-50 min-h-screen">
  <div class="flex min-h-screen">
    <!-- Sidebar simplifiée -->
    <div class="no-print">
      <aside class="w-64 bg-gradient-to-b from-blue-900 to-blue-800 text-white flex flex-col sticky top-0 h-full">
        <div class="p-6 border-b border-white/10">
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center shadow-lg">
              <span class="font-bold text-white text-lg">NGS</span>
            </div>
            <div>
              <h1 class="text-xl font-bold">Boutique</h1>
              <p class="text-xs text-gray-300">Facture Cash</p>
            </div>
          </div>
        </div>

        <nav class="p-4 space-y-1">
          <a href="commande_details.php?id=<?= $commande_id ?>" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
            <i class="fas fa-arrow-left w-5 text-gray-300"></i>
            <span>Retour à la commande</span>
          </a>
          <a href="ventes_boutique.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
            <i class="fas fa-shopping-cart w-5 text-gray-300"></i>
            <span>Liste des ventes</span>
          </a>
          <a href="dashboard_boutique.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
            <i class="fas fa-chart-line w-5 text-gray-300"></i>
            <span>Tableau de bord</span>
          </a>
        </nav>

        <div class="p-4 border-t border-white/10 mt-auto">
          <a href="../models/logout.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-red-500/10 text-red-300 hover:text-red-200 transition-colors">
            <i class="fas fa-sign-out-alt w-5"></i>
            <span>Déconnexion</span>
          </a>
        </div>
      </aside>
    </div>

    <div class="flex-1 flex flex-col">
      <!-- Header avec boutons d'action -->
      <header class="bg-white border-b border-gray-200 p-4 no-print">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-xl font-bold text-gray-900">Facture Cash</h1>
            <p class="text-gray-600 text-sm">
              N° <?= htmlspecialchars($commande['numero_facture']) ?> | 
              Client: <?= $commande['client_nom'] ? htmlspecialchars($commande['client_nom']) : 'Non renseigné' ?>
            </p>
          </div>
          <div class="print-controls flex gap-3">
            <button onclick="window.print()" class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-2 rounded-lg hover:opacity-90 shadow-md flex items-center gap-2 transition-all">
              <i class="fas fa-print"></i> Imprimer
            </button>
            <a href="commande_details.php?id=<?= $commande_id ?>" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 shadow flex items-center gap-2 transition-all">
              <i class="fas fa-arrow-left"></i> Retour
            </a>
          </div>
        </div>
      </header>

      <!-- Contenu principal de la facture -->
      <main class="flex-1 flex items-center justify-center p-4 md:p-8">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-6 border border-gray-200 relative facture-container ticket-print">
          
          <!-- En-tête de la facture -->
          <div class="flex flex-col items-center mb-4">
            <div class="w-16 h-16 rounded-full bg-gradient-to-r from-blue-600 to-blue-800 flex items-center justify-center shadow-lg mb-3">
              <span class="font-bold text-white text-2xl">NGS</span>
            </div>
            <h2 class="text-xl font-bold text-gray-900 tracking-wide mb-1"><?= htmlspecialchars($infos_boutique['nom']) ?></h2>
            <h3 class="text-sm font-semibold text-blue-700">Boutique <?= htmlspecialchars($commande['boutique_nom']) ?></h3>
          </div>

          <!-- Informations boutique (STATIQUES) -->
          <div class="text-xs text-center mb-4 text-gray-600">
            <div class="font-medium mb-1"><?= htmlspecialchars($infos_boutique['adresse']) ?></div>
            <div>Tél: <?= htmlspecialchars($infos_boutique['telephone']) ?></div>
            <?php if (!empty($infos_boutique['telephone2'])): ?>
              <div>Tél: <?= htmlspecialchars($infos_boutique['telephone2']) ?></div>
            <?php endif; ?>
            <?php if (!empty($infos_boutique['email'])): ?>
              <div>Email: <?= htmlspecialchars($infos_boutique['email']) ?></div>
            <?php endif; ?>
            <?php if (!empty($infos_boutique['rc'])): ?>
              <div>RC: <?= htmlspecialchars($infos_boutique['rc']) ?></div>
            <?php endif; ?>
            <?php if (!empty($infos_boutique['nif'])): ?>
              <div>NIF: <?= htmlspecialchars($infos_boutique['nif']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Informations facture -->
          <div class="text-sm text-center text-gray-700 mb-6 bg-blue-50 rounded-lg p-4 border border-blue-100">
            <div class="grid grid-cols-2 gap-2 text-left">
              <div>
                <span class="font-semibold text-blue-700">Facture N°:</span><br>
                <span class="font-bold text-gray-900"><?= htmlspecialchars($commande['numero_facture']) ?></span>
              </div>
              <div class="text-right">
                <span class="font-semibold text-blue-700">Date:</span><br>
                <span class="font-bold text-gray-900"><?= date('d/m/Y', strtotime($commande['date_commande'])) ?></span>
              </div>
            </div>
            <div class="mt-3 text-left">
              <span class="font-semibold text-blue-700">Client:</span><br>
              <span class="font-bold text-gray-900">
                <?= $commande['client_nom'] ? htmlspecialchars($commande['client_nom']) : 'CLIENT CASH' ?>
              </span>
            </div>
            <?php if (!empty($paiements)): ?>
              <div class="mt-3 grid grid-cols-2 gap-2 text-left">
                <div>
                  <span class="font-semibold text-blue-700">Paiement:</span><br>
                  <span class="font-bold text-green-600">ESPÈCES</span>
                </div>
                <div class="text-right">
                  <span class="font-semibold text-blue-700">Dernier paiement:</span><br>
                  <span class="font-bold text-gray-900"><?= date('d/m/Y', strtotime($paiements[0]['date'])) ?></span>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- Tableau des produits -->
          <div class="mb-4">
            <table class="w-full text-sm mb-2 border border-gray-200 rounded-lg overflow-hidden">
              <thead>
                <tr class="bg-gradient-to-r from-blue-50 to-blue-100 text-blue-900">
                  <th class="font-semibold border-b border-gray-300 py-2 px-2 text-left">Qte</th>
                  <th class="font-semibold border-b border-gray-300 py-2 px-2 text-left">Désignation</th>
                  <th class="font-semibold border-b border-gray-300 py-2 px-2 text-right">P.U ($)</th>
                  <th class="font-semibold border-b border-gray-300 py-2 px-2 text-right">Total ($)</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($produits_commande)): ?>
                  <?php foreach($produits_commande as $produit): 
                    $uniteText = $produit['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                    $uniteClass = $produit['umProduit'] == 'metres' ? 'unit-metres' : 'unit-pieces';
                  ?>
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                      <td class="py-2 px-2 text-gray-800">
                        <?= number_format($produit['quantite'], 3) ?>
                        <span class="unit-badge <?= $uniteClass ?>"><?= $uniteText ?></span>
                      </td>
                      <td class="py-2 px-2 text-gray-800">
                        <?= htmlspecialchars($produit['designation']) ?>
                        <?php if (!empty($produit['matricule'])): ?>
                          <div class="text-xs text-gray-500">Ref: <?= htmlspecialchars($produit['matricule']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="py-2 px-2 text-gray-800 text-right">
                        <?= number_format($produit['prix_unitaire'], 2) ?>
                      </td>
                      <td class="py-2 px-2 text-gray-800 text-right font-medium">
                        <?= number_format($produit['total_ligne'], 2) ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="4" class="py-4 text-center text-gray-500">
                      Aucun produit dans cette commande
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Total et informations -->
          <div class="text-right text-lg font-bold text-gray-900 mt-2 mb-4">
            <div class="border-t border-gray-300 pt-3">
              <div class="flex justify-between items-center mb-1">
                <span class="text-gray-700 font-medium">Total commande:</span>
                <span class="text-blue-700"><?= number_format($total_commande, 2) ?> $</span>
              </div>
              
              <?php if ($total_paye > 0): ?>
                <div class="flex justify-between items-center mb-1">
                  <span class="text-gray-700 font-medium">Total payé:</span>
                  <span class="text-green-600"><?= number_format($total_paye, 2) ?> $</span>
                </div>
                <div class="flex justify-between items-center text-base mt-2 pt-2 border-t border-gray-300">
                  <span class="text-gray-700 font-bold">Reste à payer:</span>
                  <span class="font-bold <?= $reste_a_payer > 0 ? 'text-yellow-600' : 'text-green-700' ?>">
                    <?= number_format($reste_a_payer, 2) ?> $
                  </span>
                </div>
                
                <?php if ($reste_a_payer <= 0): ?>
                  <div class="mt-2 text-center">
                    <div class="inline-flex items-center bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                      <i class="fas fa-check-circle mr-1"></i>
                      FACTURE PAYÉE INTÉGRALEMENT
                    </div>
                  </div>
                <?php else: ?>
                  <div class="mt-2 text-center">
                    <div class="inline-flex items-center bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-medium">
                      <i class="fas fa-exclamation-circle mr-1"></i>
                      ACCOMPTE DE <?= number_format(($total_paye/$total_commande)*100, 0) ?>% PAYÉ
                    </div>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <div class="flex justify-between items-center text-base mt-2 pt-2 border-t border-gray-300">
                  <span class="text-gray-700 font-bold">Montant à payer:</span>
                  <span class="text-red-600 font-bold"><?= number_format($total_commande, 2) ?> $</span>
                </div>
                <div class="mt-2 text-center">
                  <div class="inline-flex items-center bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium">
                    <i class="fas fa-clock mr-1"></i>
                    EN ATTENTE DE PAIEMENT
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Informations paiement -->
          <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
            <div class="flex items-center mb-2">
              <i class="fas fa-money-bill-wave text-green-600 mr-2"></i>
              <span class="font-semibold text-green-800">PAIEMENT EN ESPÈCES</span>
            </div>
            <div class="text-sm text-green-700">
              <?php if ($total_paye > 0): ?>
                <div class="mb-1">Montant payé: <span class="font-bold"><?= number_format($total_paye, 2) ?> $</span></div>
                <div>Dernier paiement: <?= !empty($paiements) ? date('d/m/Y à H:i', strtotime($paiements[0]['date'])) : 'N/A' ?></div>
                <?php if (count($paiements) > 1): ?>
                  <div class="text-xs text-green-600 mt-1">
                    <i class="fas fa-info-circle mr-1"></i>
                    <?= count($paiements) ?> paiement(s) enregistré(s)
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <div>Aucun paiement enregistré</div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Pied de page -->
          <div class="text-center text-xs text-gray-500 mt-4 pt-4 border-t border-gray-200">
            <div class="mb-2">
              <em>Merci de votre confiance !</em>
            </div>
            <div class="text-gray-600">
              Facture générée le <?= date('d/m/Y à H:i') ?> via NGS Boutique
            </div>
            <div class="text-gray-400 text-xs mt-2">
              Conservez ce reçu pour vos archives
            </div>
          </div>

          <!-- Signature -->
          <div class="text-right text-xs text-gray-700 mt-6">
            <div class="inline-block border-t border-gray-400 w-32 mb-1"></div>
            <div class="font-semibold">Signature et cachet</div>
          </div>

          <!-- Message pour l'impression -->
          <div class="print-only text-center mt-6 pt-4 border-t border-gray-300">
            <div class="text-xs text-gray-500">
              *** Document généré automatiquement - Valide sans signature ***
            </div>
          </div>
        </div>
      </main>

      <!-- Footer avec informations supplémentaires -->
      <footer class="bg-white border-t border-gray-200 p-4 text-center text-sm text-gray-600 no-print">
        <div class="max-w-4xl mx-auto">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <i class="fas fa-store text-blue-600 mr-2"></i>
              <span class="font-medium">Boutique:</span> <?= htmlspecialchars($commande['boutique_nom']) ?>
            </div>
            <div>
              <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>
              <span class="font-medium">Date commande:</span> <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?>
            </div>
            <div>
              <i class="fas fa-file-invoice-dollar text-blue-600 mr-2"></i>
              <span class="font-medium">Facture:</span> <?= htmlspecialchars($commande['numero_facture']) ?>
            </div>
          </div>
        </div>
      </footer>
    </div>
  </div>

  <script>
    // Auto-impression optionnelle
    document.addEventListener('DOMContentLoaded', function() {
      // Optionnel: déclencher l'impression automatiquement après 1 seconde
      // setTimeout(() => {
      //   window.print();
      // }, 1000);
      
      // Raccourci clavier pour l'impression
      document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'p') {
          e.preventDefault();
          window.print();
        }
      });
    });

    // Message après impression
    window.onafterprint = function() {
      // Optionnel: afficher un message ou rediriger
      console.log("Facture imprimée avec succès !");
    };
  </script>
</body>
</html>