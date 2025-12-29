<?php
// recu.php
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

// Récupération de l'ID du paiement
$paiement_id = $_GET['id'] ?? null;
if (!$paiement_id) {
    $_SESSION['flash_message'] = [
        'text' => "Aucun ID de paiement spécifié.",
        'type' => "error"
    ];
    header('Location: paiements.php');
    exit;
}

// Récupérer le paiement avec vérification boutique
try {
    $query = $pdo->prepare("
        SELECT 
            p.*,
            c.id as commande_id,
            c.numero_facture,
            c.client_nom,
            c.date_commande,
            c.etat as commande_etat,
            b.nom as boutique_nom
        FROM paiements p
        JOIN commandes c ON p.commandes_id = c.id
        JOIN boutiques b ON c.boutique_id = b.id
        WHERE p.id = ? AND c.boutique_id = ? AND p.statut = 0
    ");
    $query->execute([$paiement_id, $boutique_id]);
    $paiement = $query->fetch(PDO::FETCH_ASSOC);

    if (!$paiement) {
        $_SESSION['flash_message'] = [
            'text' => "Paiement non trouvé ou vous n'avez pas les droits d'accès.",
            'type' => "error"
        ];
        header('Location: paiements.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur de base de données: " . $e->getMessage(),
        'type' => "error"
    ];
    header('Location: paiements.php');
    exit;
}

// Récupérer les produits de la commande pour calculer le total
try {
    $query = $pdo->prepare("
        SELECT 
            cp.quantite,
            cp.prix_unitaire,
            p.designation,
            p.umProduit,
            (cp.quantite * cp.prix_unitaire) as total_ligne
        FROM commande_produits cp
        JOIN stock s ON cp.stock_id = s.id
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE cp.commande_id = ? AND cp.statut = 0
        ORDER BY cp.id DESC
    ");
    $query->execute([$paiement['commande_id']]);
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

// Récupérer tous les paiements pour cette commande
try {
    $query = $pdo->prepare("
        SELECT SUM(montant) as total_paye
        FROM paiements 
        WHERE commandes_id = ? AND statut = 0
    ");
    $query->execute([$paiement['commande_id']]);
    $result = $query->fetch(PDO::FETCH_ASSOC);
    $total_paye = $result['total_paye'] ?? 0;
    
    // Calculer le reste à payer
    $reste_a_payer = $total_commande - $total_paye;
    
} catch (PDOException $e) {
    $total_paye = $paiement['montant'];
    $reste_a_payer = $total_commande - $total_paye;
    error_log("Erreur récupération total payé: " . $e->getMessage());
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
  <title>Reçu de paiement #<?= $paiement_id ?> - NGS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    @media print {
      .no-print, .print-controls, button, aside, header, footer, .sidebar-menu { 
        display: none !important; 
      }
      .recu-container { 
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
      @page {
        margin: 10mm;
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
    
    /* Barre de séparation */
    .separator {
      height: 1px;
      background: repeating-linear-gradient(
        90deg,
        transparent,
        transparent 5px,
        #9ca3af 5px,
        #9ca3af 10px
      );
      margin: 15px 0;
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
              <p class="text-xs text-gray-300">Reçu de paiement</p>
            </div>
          </div>
        </div>

        <nav class="p-4 space-y-1">
          <a href="paiements.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
            <i class="fas fa-arrow-left w-5 text-gray-300"></i>
            <span>Retour aux paiements</span>
          </a>
          <a href="ventes_boutique.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
            <i class="fas fa-shopping-cart w-5 text-gray-300"></i>
            <span>Liste des ventes</span>
          </a>
          <a href="commande_details.php?id=<?= $paiement['commande_id'] ?>" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
            <i class="fas fa-file-invoice w-5 text-gray-300"></i>
            <span>Voir la commande</span>
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
            <h1 class="text-xl font-bold text-gray-900">Reçu de paiement</h1>
            <p class="text-gray-600 text-sm">
              N° <?= $paiement_id ?> | 
              Commande #<?= $paiement['commande_id'] ?> | 
              Client: <?= htmlspecialchars($paiement['client_nom']) ?>
            </p>
          </div>
          <div class="print-controls flex gap-3">
            <button onclick="imprimerRecu()" class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-2 rounded-lg hover:opacity-90 shadow-md flex items-center gap-2 transition-all">
              <i class="fas fa-print"></i> Imprimer
            </button>
            <a href="paiements.php" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 shadow flex items-center gap-2 transition-all">
              <i class="fas fa-arrow-left"></i> Retour
            </a>
          </div>
        </div>
      </header>

      <!-- Contenu principal du reçu -->
      <main class="flex-1 flex items-center justify-center p-4 md:p-8">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-6 border border-gray-200 recu-container ticket-print">
          
          <!-- En-tête du reçu -->
          <div class="flex flex-col items-center mb-4">
            <div class="w-16 h-16 rounded-full bg-gradient-to-r from-green-600 to-green-800 flex items-center justify-center shadow-lg mb-3">
              <span class="font-bold text-white text-2xl">
                <i class="fas fa-check-circle"></i>
              </span>
            </div>
            <h2 class="text-xl font-bold text-gray-900 tracking-wide mb-1">REÇU DE PAIEMENT</h2>
            <h3 class="text-sm font-semibold text-green-700">Boutique <?= htmlspecialchars($paiement['boutique_nom']) ?></h3>
          </div>

          <!-- Informations boutique -->
          <div class="text-xs text-center mb-4 text-gray-600">
            <div class="font-medium mb-1"><?= htmlspecialchars($infos_boutique['adresse']) ?></div>
            <div>Tél: <?= htmlspecialchars($infos_boutique['telephone']) ?></div>
            <?php if (!empty($infos_boutique['telephone2'])): ?>
              <div>Tél: <?= htmlspecialchars($infos_boutique['telephone2']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Barre de séparation -->
          <div class="separator"></div>

          <!-- Numéro du reçu -->
          <div class="text-center mb-4">
            <div class="text-sm font-semibold text-gray-700 mb-1">N° REÇU</div>
            <div class="text-2xl font-bold text-blue-700">REC-<?= str_pad($paiement_id, 6, '0', STR_PAD_LEFT) ?></div>
          </div>

          <!-- Informations principales -->
          <div class="text-sm text-gray-700 mb-6 bg-gray-50 rounded-lg p-4 border border-gray-200">
            <div class="grid grid-cols-2 gap-2 mb-3">
              <div>
                <span class="font-semibold text-gray-700">Date reçu:</span><br>
                <span class="font-bold text-gray-900"><?= date('d/m/Y', strtotime($paiement['date'])) ?></span>
              </div>
              <div class="text-right">
                <span class="font-semibold text-gray-700">Heure:</span><br>
                <span class="font-bold text-gray-900"><?= date('H:i') ?></span>
              </div>
            </div>
            
            <div class="mb-3">
              <span class="font-semibold text-gray-700">Commande:</span><br>
              <span class="font-bold text-gray-900">#<?= $paiement['commande_id'] ?></span>
              <span class="text-xs text-gray-500 ml-2">(Facture: <?= htmlspecialchars($paiement['numero_facture']) ?>)</span>
            </div>
            
            <div class="mb-3">
              <span class="font-semibold text-gray-700">Client:</span><br>
              <span class="font-bold text-gray-900">
                <?= htmlspecialchars($paiement['client_nom'] ?? 'CLIENT') ?>
              </span>
            </div>
            
            <div>
              <span class="font-semibold text-gray-700">Date commande:</span><br>
              <span class="font-bold text-gray-900"><?= date('d/m/Y', strtotime($paiement['date_commande'])) ?></span>
            </div>
          </div>

          <!-- Montant payé -->
          <div class="text-center mb-6">
            <div class="text-sm font-semibold text-gray-700 mb-2">MONTANT PAYÉ</div>
            <div class="text-4xl font-bold text-green-700">
              <?= number_format($paiement['montant'], 3) ?> $
            </div>
            <div class="text-xs text-gray-500 mt-1">
              <?= number_format($paiement['montant'] * 0, 3) ?> $ TVA incluse
            </div>
          </div>

          <!-- Barre de séparation -->
          <div class="separator"></div>

          <!-- Détails de la commande -->
          <div class="mb-4">
            <div class="text-sm font-semibold text-gray-700 mb-2">DÉTAILS DE LA COMMANDE</div>
            <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
              <div class="grid grid-cols-2 gap-2 text-sm mb-2">
                <div class="text-gray-600">Total commande:</div>
                <div class="text-right font-bold"><?= number_format($total_commande, 3) ?> $</div>
                
                <div class="text-gray-600">Total payé:</div>
                <div class="text-right font-bold text-green-600"><?= number_format($total_paye, 3) ?> $</div>
                
                <div class="text-gray-600">Reste à payer:</div>
                <div class="text-right font-bold <?= $reste_a_payer > 0 ? 'text-red-600' : 'text-green-600' ?>">
                  <?= number_format($reste_a_payer, 3) ?> $
                </div>
              </div>
              
              <?php if ($reste_a_payer <= 0): ?>
                <div class="text-center mt-2">
                  <span class="inline-flex items-center bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium">
                    <i class="fas fa-check-circle mr-1"></i> COMMANDE PAYÉE INTÉGRALEMENT
                  </span>
                </div>
              <?php else: ?>
                <div class="text-center mt-2">
                  <span class="inline-flex items-center bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-medium">
                    <i class="fas fa-exclamation-circle mr-1"></i> 
                    <?= number_format(($total_paye/$total_commande)*100, 0) ?>% PAYÉ
                  </span>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Liste des produits -->
          <div class="mb-4">
            <div class="text-sm font-semibold text-gray-700 mb-2">PRODUITS COMMANDÉS</div>
            <?php if (!empty($produits_commande)): ?>
              <div class="max-h-32 overflow-y-auto pr-2">
                <?php foreach($produits_commande as $produit): 
                  $uniteText = $produit['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                ?>
                  <div class="flex justify-between items-start text-sm py-1 border-b border-gray-100 last:border-0">
                    <div class="flex-1">
                      <div class="font-medium text-gray-800"><?= htmlspecialchars($produit['designation']) ?></div>
                      <div class="text-xs text-gray-500">
                        <?= number_format($produit['quantite'], 3) ?> <?= $uniteText ?>
                        × <?= number_format($produit['prix_unitaire'], 3) ?> $
                      </div>
                    </div>
                    <div class="font-bold text-gray-900">
                      <?= number_format($produit['total_ligne'], 3) ?> $
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="text-center text-gray-500 text-sm py-2">
                Aucun produit dans cette commande
              </div>
            <?php endif; ?>
          </div>

          <!-- Barre de séparation -->
          <div class="separator"></div>

          <!-- Informations paiement -->
          <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
            <div class="flex items-center mb-2">
              <i class="fas fa-money-bill-wave text-green-600 mr-2"></i>
              <span class="font-semibold text-green-800">DÉTAIL DU PAIEMENT</span>
            </div>
            <div class="text-sm text-green-700">
              <div class="mb-1">Montant: <span class="font-bold"><?= number_format($paiement['montant'], 3) ?> $</span></div>
              <div class="mb-1">Date: <?= date('d/m/Y', strtotime($paiement['date'])) ?></div>
              <div>Mode de paiement: <span class="font-bold">ESPÈCES</span></div>
            </div>
          </div>

          <!-- Code QR optionnel -->
          <div class="text-center mb-4">
            <div class="text-xs text-gray-500 mb-2">
              <i class="fas fa-qrcode mr-1"></i>
              Code de vérification
            </div>
            <div class="font-mono text-sm bg-gray-100 p-2 rounded border border-gray-300 inline-block">
              NGS-PAY-<?= strtoupper(dechex($paiement_id)) ?>-<?= date('Ymd', strtotime($paiement['date'])) ?>
            </div>
          </div>

          <!-- Pied de page -->
          <div class="text-center text-xs text-gray-500 mt-4 pt-4 border-t border-gray-200">
            <div class="mb-2">
              <em>Ce reçu atteste du paiement effectué</em>
            </div>
            <div class="text-gray-600">
              Reçu généré le <?= date('d/m/Y à H:i') ?> via NGS Boutique
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
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <i class="fas fa-file-invoice-dollar text-blue-600 mr-2"></i>
              <span class="font-medium">Reçu:</span> <?= $paiement_id ?>
            </div>
            <div>
              <i class="fas fa-shopping-cart text-blue-600 mr-2"></i>
              <span class="font-medium">Commande:</span> #<?= $paiement['commande_id'] ?>
            </div>
            <div>
              <i class="fas fa-user text-blue-600 mr-2"></i>
              <span class="font-medium">Client:</span> <?= htmlspecialchars($paiement['client_nom']) ?>
            </div>
            <div>
              <i class="fas fa-money-bill-wave text-blue-600 mr-2"></i>
              <span class="font-medium">Montant:</span> <?= number_format($paiement['montant'], 3) ?> $
            </div>
          </div>
        </div>
      </footer>
    </div>
  </div>

  <script>
    // Fonction d'impression améliorée
    function imprimerRecu() {
      const originalTitle = document.title;
      document.title = "Recu_" + <?= $paiement_id ?> + "_" + new Date().toISOString().slice(0, 10);
      
      setTimeout(() => {
        window.print();
        setTimeout(() => {
          document.title = originalTitle;
        }, 1000);
      }, 100);
    }
    
    // Auto-impression optionnelle
    document.addEventListener('DOMContentLoaded', function() {
      // Optionnel: déclencher l'impression automatiquement après 1 seconde
      // setTimeout(() => {
      //   imprimerRecu();
      // }, 1000);
      
      // Raccourci clavier pour l'impression
      document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'p') {
          e.preventDefault();
          imprimerRecu();
        }
      });
    });

    // Message après impression
    window.onafterprint = function() {
      console.log("Reçu imprimé avec succès !");
      
      // Optionnel: rediriger après impression
      // setTimeout(() => {
      //   window.location.href = 'paiements.php';
      // }, 500);
    };
  </script>
</body>
</html>