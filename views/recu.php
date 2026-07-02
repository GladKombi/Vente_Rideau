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
            b.nom as boutique_nom,
            b.email as boutique_email
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

// Récupérer les détails des paiements
try {
  $queryPayDetails = $pdo->prepare("SELECT * FROM paiements WHERE commandes_id = ? AND statut = 0 ORDER BY date DESC");
  $queryPayDetails->execute([$paiement['commande_id']]);
  $paiements_details = $queryPayDetails->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $paiements_details = [];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <title>REÇU DE PAIEMENT NGS <?= str_pad($paiement_id, 6, '0', STR_PAD_LEFT) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @media print {
      @page {
        margin: 0;
        padding: 0;
        size: 80mm auto;
      }

      body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        font-size: 12px !important;
        color: #000000 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      .no-print {
        display: none !important;
      }

      .ticket {
        width: 76mm !important;
        min-width: 76mm !important;
        max-width: 76mm !important;
        margin: 0 auto !important;
        padding: 2mm 3mm !important;
        box-shadow: none !important;
        background: white !important;
        color: #000000 !important;
      }

      /* Zone de sécurité pour la coupe */
      .cut-space {
        height: 20mm !important;
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
      }

      /* Forcer les couleurs en noir pur */
      .force-black,
      .force-black * {
        color: #000000 !important;
        border-color: #000000 !important;
      }

      .text-dark {
        color: #000000 !important;
      }

      .border-dark {
        border-color: #000000 !important;
      }

      * {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      a {
        text-decoration: none;
        color: #000000 !important;
      }
    }

    /* Styles écran */
    body {
      font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .ticket {
      width: 80mm;
      background: white;
      padding: 8mm;
      margin: 0 auto;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      border-radius: 12px;
      position: relative;
      overflow: hidden;
    }

    .ticket::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #10B981 0%, #059669 100%);
    }

    .line {
      border-top: 1px solid #000000;
      margin: 10px 0;
      opacity: 0.8;
    }

    .dotted-line {
      border-top: 1px dashed #000000;
      margin: 10px 0;
      opacity: 0.6;
    }

    .text-super-dark {
      color: #000000 !important;
    }

    .font-heavy {
      font-weight: 900 !important;
    }

    /* Badge PAYÉ */
    .paid-badge {
      background: linear-gradient(135deg, #10B981 0%, #059669 100%);
      color: white;
      font-weight: bold;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 10px;
      display: inline-block;
      margin-left: 8px;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .animate-fade-in {
      animation: fadeIn 0.5s ease-out;
    }

    .success-note {
      background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
      border: 1px solid #10B981;
      border-radius: 8px;
      padding: 8px;
      margin: 10px 0;
      text-align: center;
    }

    .montant-paye {
      color: #059669;
      font-weight: bold;
      font-size: 1.2em;
    }
  </style>
</head>

<body class="text-gray-900">

  <div class="no-print animate-fade-in mb-8 w-full max-w-md">
    <div class="bg-white rounded-xl shadow-2xl p-6 mb-4">
      <h2 class="text-xl font-bold text-center mb-4 text-gray-800">REÇU DE PAIEMENT</h2>
      <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="text-center">
          <div class="w-16 h-16 mx-auto bg-gradient-to-r from-green-400 to-emerald-600 rounded-full flex items-center justify-center mb-2">
            <i class="fas fa-receipt text-white text-2xl"></i>
          </div>
          <span class="text-sm font-medium text-gray-700">Reçu N°</span>
          <p class="font-bold text-gray-900">REC-<?= str_pad($paiement_id, 6, '0', STR_PAD_LEFT) ?></p>
        </div>
        <div class="text-center">
          <div class="w-16 h-16 mx-auto bg-gradient-to-r from-blue-400 to-blue-600 rounded-full flex items-center justify-center mb-2">
            <i class="fas fa-calendar-alt text-white text-2xl"></i>
          </div>
          <span class="text-sm font-medium text-gray-700">Date</span>
          <p class="font-bold text-gray-900"><?= date('d/m/Y') ?></p>
        </div>
      </div>
      <button onclick="window.print()" class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white font-bold py-4 px-6 rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all duration-300 shadow-lg hover:shadow-xl flex items-center justify-center space-x-3">
        <i class="fas fa-print text-xl"></i>
        <span>IMPRIMER LE REÇU</span>
      </button>
      <a href="paiements.php" class="w-full bg-slate-700 hover:bg-slate-800 text-white font-bold py-3 rounded-lg shadow transition-all flex items-center justify-center gap-3 mt-1">
        <i class="fas fa-arrow-left"></i>
        <span>RETOUR AUX PAIEMENTS</span>
      </a>
    </div>

    <div class="bg-white rounded-xl shadow-2xl p-6">
      <h3 class="font-bold text-lg mb-4 text-gray-800">Résumé du paiement</h3>
      <div class="space-y-3">
        <div class="flex justify-between">
          <span class="text-gray-600">Client:</span>
          <span class="font-bold"><?= htmlspecialchars($paiement['client_nom'] ?? 'CLIENT') ?></span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-600">Commande:</span>
          <span class="font-bold">#<?= $paiement['commande_id'] ?></span>
        </div>
        <div class="flex justify-between border-t border-gray-200 pt-3">
          <span class="text-gray-600">Montant payé:</span>
          <span class="font-bold text-emerald-600 text-xl"><?= number_format($paiement['montant'], 3) ?> $</span>
        </div>
        <?php if ($reste_a_payer > 0): ?>
          <div class="flex justify-between">
            <span class="text-gray-600">Reste à payer:</span>
            <span class="font-bold text-red-600"><?= number_format($reste_a_payer, 3) ?> $</span>
          </div>
        <?php else: ?>
          <div class="text-center mt-2">
            <span class="inline-flex items-center bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium">
              <i class="fas fa-check-circle mr-1"></i> COMMANDE PAYÉE INTÉGRALEMENT
            </span>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- REÇU DE PAIEMENT À IMPRIMER -->
  <div class="ticket force-black text-super-dark">
    <!-- En-tête NGS -->
    <div class="text-center mb-1">
      <div class="flex justify-center items-center mb-2">
        <div class="w-12 h-12 bg-gradient-to-r from-green-600 to-emerald-500 rounded-full flex items-center justify-center mr-3">
          <span class="text-white font-bold text-lg">NGS</span>
        </div>
        <h1 class="text-xl font-black tracking-tight">NEW GRACE SERVICE</h1>
      </div>
      <p class="text-[9px] mt-1 opacity-90"><?= htmlspecialchars($paiement['boutique_nom']) ?> | RDC</p>
      <p class="text-[9px] mt-1 opacity-80">RCCM : 20-A557</p>
      <p class="text-[9px] mt-1 opacity-80">+243 977 421 421</p>
    </div>

    <div class="line border-dark"></div>

    <!-- Informations reçu -->
    <div class="mb-4">
      <div class="flex justify-between items-start mb-2">
        <div class="text-[10px]">
          <p class="font-bold text-[11px]">REÇU DE PAIEMENT</p>
          <p><span class="font-semibold">N°:</span> REC-<?= str_pad($paiement_id, 6, '0', STR_PAD_LEFT) ?></p>
        </div>
        <div class="text-right text-[10px]">
          <p class="font-semibold"><?= date('d/m/Y H:i', strtotime($paiement['date'])) ?></p>
          <p class="text-[9px]">Paiement #<?= $paiement_id ?></p>
        </div>
      </div>

      <div class="bg-gray-100 p-2 rounded text-[10px] mt-2">
        <p class="font-semibold">CLIENT</p>
        <p><?= htmlspecialchars($paiement['client_nom'] ?: 'CLIENT') ?></p>
        <p class="text-[9px] opacity-80">Commande #<?= $paiement['commande_id'] ?></p>
        <?php if ($paiement['boutique_email']): ?>
          <p class="text-[9px] opacity-80"><?= htmlspecialchars($paiement['boutique_email']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Note de paiement -->
    <div class="success-note text-[9px] mb-3">
      <i class="fas fa-check-circle mr-1"></i>
      <span class="font-semibold">PAIEMENT CONFIRMÉ</span>
    </div>

    <!-- Détails des articles -->
    <table class="w-full text-[10px] mb-1">
      <thead>
        <tr class="border-b-2 border-black">
          <th class="text-left pb-1 font-black">ARTICLE</th>
          <th class="text-center pb-1 font-black">P.U. /$</th>
          <th class="text-center pb-1 font-black">QTÉ</th>
          <th class="text-right pb-1 font-black">TOTAL</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($produits_commande as $index => $art):
          $uniteText = $art['umProduit'] == 'metres' ? 'm' : 'pce';
        ?>
          <tr class="<?= $index % 2 === 0 ? 'bg-gray-50' : '' ?>">
            <td class="py-1.5 pr-1">
              <span class="font-medium"><?= htmlspecialchars($art['designation']) ?></span>
              <div class="text-[7px] opacity-90"><?= $uniteText ?></div>
            </td>
            <td class="text-center py-1.5">
              <?= number_format($art['prix_unitaire'], 3) ?>
            </td>
            <td class="text-center py-1.5">
              <span class="font-bold"><?= (float)$art['quantite'] ?></span>
            </td>
            <td class="text-right py-1.5 font-bold">
              <?= number_format($art['total_ligne'], 3) ?> $
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="line border-dark"></div>

    <!-- Totaux -->
    <div class="text-right mb-4">
      <div class="text-[12px] font-black">
        <p class="mb-1">TOTAL COMMANDE: <?= number_format($total_commande, 3) ?> $</p>
      </div>

      <div class="text-[10px] mt-2 space-y-1">
        <div class="flex justify-between">
          <span>Montant payé:</span>
          <span class="font-bold text-emerald-600"><?= number_format($paiement['montant'], 3) ?> $</span>
        </div>
        <div class="flex justify-between">
          <span>Total payé:</span>
          <span class="font-bold text-emerald-600"><?= number_format($total_paye, 3) ?> $</span>
        </div>
        <?php if ($reste_a_payer > 0): ?>
          <div class="flex justify-between">
            <span>Reste à payer:</span>
            <span class="font-bold text-red-600"><?= number_format($reste_a_payer, 3) ?> $</span>
          </div>
        <?php endif; ?>
      </div>

      <!-- Détails des paiements -->
      <?php if (!empty($paiements_details)): ?>
        <div class="mt-3 pt-2 border-t border-black text-[8px]">
          <p class="font-semibold mb-1">HISTORIQUE DES PAIEMENTS:</p>
          <?php foreach ($paiements_details as $paiement_item): ?>
            <div class="flex justify-between">
              <span><?= date('d/m/Y', strtotime($paiement_item['date'])) ?>:</span>
              <span><?= number_format($paiement_item['montant'], 3) ?> $</span>
            </div>
          <?php endforeach; ?>
          <div class="flex justify-between font-bold mt-1">
            <span>TOTAL PAYÉ:</span>
            <span><?= number_format($total_paye, 3) ?> $</span>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Pied de page -->
    <div class="text-center mt-1 pt-2">
      <p class="text-[9px] font-bold mb-1">MERCI POUR VOTRE PAIEMENT</p>
      <p class="text-[7px] opacity-80">Ce reçu atteste du paiement effectué</p>
      <p class="text-[6px] opacity-60 mt-1">Reçu généré le <?= date('d/m/Y à H:i') ?></p>
    </div>

    <!-- Zone de sécurité pour la coupe -->
    <div class="cut-space bg-white force-black">
      <div class="text-center text-[6px] opacity-40 pt-4">
        <p>--- COUPER ICI ---</p>
      </div>
    </div>
  </div>

  <script>
    // Optimisation pour l'impression
    document.addEventListener('DOMContentLoaded', function() {
      window.addEventListener('beforeprint', function() {
        document.body.classList.add('printing');
      });

      window.addEventListener('afterprint', function() {
        document.body.classList.remove('printing');

        const confirmation = document.createElement('div');
        confirmation.className = 'fixed top-4 right-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-3 rounded-lg shadow-lg no-print animate-fade-in';
        confirmation.innerHTML = `
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-check-circle text-xl"></i>
                        <div>
                            <p class="font-bold">Reçu imprimé</p>
                            <p class="text-sm opacity-90">N° REC-${'<?= str_pad($paiement_id, 6, '0', STR_PAD_LEFT) ?>'}</p>
                            <p class="text-xs opacity-80 mt-1">Montant: ${'<?= number_format($paiement['montant'], 3) ?>'} $</p>
                        </div>
                    </div>
                `;
        document.body.appendChild(confirmation);

        setTimeout(() => {
          confirmation.remove();
        }, 3000);
      });
    });

    // Raccourci clavier pour l'impression
    document.addEventListener('keydown', function(e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        window.print();
      }
    });

    // Gestion des popups
    window.addEventListener('load', function() {
      if (window.opener) {
        const popupInfo = document.createElement('div');
        popupInfo.className = 'fixed bottom-4 right-4 bg-blue-500 text-white px-4 py-3 rounded-lg shadow-lg no-print animate-fade-in';
        popupInfo.innerHTML = `
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-info-circle text-xl"></i>
                        <div>
                            <p class="text-sm">Fermeture automatique dans 10 secondes après impression</p>
                        </div>
                    </div>
                `;
        document.body.appendChild(popupInfo);

        setTimeout(() => {
          if (!document.body.classList.contains('printing')) {
            popupInfo.remove();
            setTimeout(() => {
              window.close();
            }, 1000);
          }
        }, 10000);

        window.addEventListener('afterprint', function() {
          setTimeout(() => {
            popupInfo.remove();
            window.close();
          }, 2000);
        });
      }
    });
  </script>

</body>

</html>