<?php
/**
 * Impression des bons d'entrée/sortie de caisse
 * Format Ticket 80mm - Style NGS
 * Dev Senior - Evotech Africa
 */

require_once '../connexion/connexion.php';

// Vérification authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Vérification permissions
$userRoles = $_SESSION['roles'] ?? [];
if (!in_array('DG', $userRoles) && !in_array('comptable', $userRoles)) {
    die("Accès non autorisé");
}

$mouvement_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';
$beneficiaire = isset($_GET['beneficiaire']) ? trim($_GET['beneficiaire']) : '';

if (!$mouvement_id || !in_array($type, ['entree', 'sortie'])) {
    die("Paramètres invalides");
}

// Récupérer les informations du mouvement
try {
    $query = "
        SELECT 
            m.*,
            c.nom as compte_nom,
            c.type as compte_type,
            c.devise,
            p.reference as projet_reference,
            p.titre as projet_titre,
            cc.reference as contrat_reference,
            cc.type_contrat as contrat_type,
            u.nom as cree_par_nom,
            u.prenom as cree_par_prenom
        FROM MOUVEMENT_COMPTE m
        LEFT JOIN COMPTES c ON m.compte_id = c.compte_id
        LEFT JOIN PROJETS p ON m.projet_id = p.projet_id
        LEFT JOIN CONTRATS_COM cc ON m.contrat_id = cc.contrat_id
        LEFT JOIN UTILISATEURS u ON m.cree_par = u.utilisateur_id
        WHERE m.mouvement_id = ? AND m.statut = 0
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$mouvement_id]);
    $mouvement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mouvement) {
        die("Mouvement introuvable");
    }
    
    // Générer un numéro de bon unique
    $prefixe = ($type === 'entree') ? 'BEC' : 'BSC';
    $annee = date('Y');
    $numero_bon = $prefixe . '-' . $annee . '-' . str_pad((int) $mouvement_id, 6, '0', STR_PAD_LEFT);
    
    // Fonction de conversion montant en lettres
    function nombreEnLettres($nombre) {
        $unites = [
            0 => 'ZERO', 1 => 'UN', 2 => 'DEUX', 3 => 'TROIS', 4 => 'QUATRE',
            5 => 'CINQ', 6 => 'SIX', 7 => 'SEPT', 8 => 'HUIT', 9 => 'NEUF',
            10 => 'DIX', 11 => 'ONZE', 12 => 'DOUZE', 13 => 'TREIZE', 14 => 'QUATORZE',
            15 => 'QUINZE', 16 => 'SEIZE', 17 => 'DIX-SEPT', 18 => 'DIX-HUIT', 19 => 'DIX-NEUF'
        ];
        
        $dizaines = [
            2 => 'VINGT', 3 => 'TRENTE', 4 => 'QUARANTE', 5 => 'CINQUANTE',
            6 => 'SOIXANTE', 7 => 'SOIXANTE-DIX', 8 => 'QUATRE-VINGTS', 9 => 'QUATRE-VINGT-DIX'
        ];
        
        if ($nombre < 20) {
            return $unites[$nombre];
        } elseif ($nombre < 100) {
            $dizaine = floor($nombre / 10);
            $unite = $nombre % 10;
            
            if ($dizaine == 7 || $dizaine == 9) {
                $dizaine--;
                $unite += 10;
            }
            
            $texte = $dizaines[$dizaine];
            
            if ($unite == 1 && $dizaine < 8) {
                $texte .= ' ET ' . $unites[$unite];
            } elseif ($unite > 0) {
                $texte .= '-' . $unites[$unite];
            } elseif ($dizaine == 8) {
                $texte = 'QUATRE-VINGTS';
            }
            
            return $texte;
        } elseif ($nombre < 1000) {
            $centaines = floor($nombre / 100);
            $reste = $nombre % 100;
            $texte = '';
            
            if ($centaines == 1) {
                $texte .= 'CENT';
            } else {
                $texte .= $unites[$centaines] . ' CENT';
            }
            
            if ($reste > 0) {
                $texte .= ' ' . nombreEnLettres($reste);
            } elseif ($centaines > 1) {
                $texte .= 'S';
            }
            
            return $texte;
        } elseif ($nombre < 1000000) {
            $milliers = floor($nombre / 1000);
            $reste = $nombre % 1000;
            $texte = '';
            
            if ($milliers == 1) {
                $texte .= 'MILLE';
            } else {
                $texte .= nombreEnLettres($milliers) . ' MILLE';
            }
            
            if ($reste > 0) {
                $texte .= ' ' . nombreEnLettres($reste);
            }
            
            return $texte;
        } elseif ($nombre < 1000000000) {
            $millions = floor($nombre / 1000000);
            $reste = $nombre % 1000000;
            $texte = '';
            
            if ($millions == 1) {
                $texte .= 'UN MILLION';
            } else {
                $texte .= nombreEnLettres($millions) . ' MILLIONS';
            }
            
            if ($reste > 0) {
                $texte .= ' ' . nombreEnLettres($reste);
            }
            
            return $texte;
        }
        
        return (string)$nombre;
    }
    
    function montantEnLettres($montant, $devise = 'FRANCS CFA') {
        $entier = floor($montant);
        $centimes = round(($montant - $entier) * 100);
        
        $lettres = nombreEnLettres($entier) . ' ' . $devise;
        
        if ($centimes > 0) {
            $lettres .= ' ET ' . nombreEnLettres($centimes) . ' CENTIMES';
        }
        
        return $lettres;
    }
    
    $montant_lettres = montantEnLettres($mouvement['montant']);
    
    // Déterminer le bénéficiaire
    if (empty($beneficiaire)) {
        $beneficiaire = ($type === 'entree') ? 'APPORTEUR' : 'BENEFICIAIRE';
    }
    
    // Formater les dates
    $date_mouvement = date('d/m/Y H:i', strtotime($mouvement['date_mouvement']));
    $date_creation = date('d/m/Y H:i', strtotime($mouvement['date_creation']));
    
    // Informations entreprise
    $entreprise = [
        'nom' => 'EVOTECH AFRICA',
        'slogan' => 'Innovation & Technologies',
        'siege' => 'Dakar, Sénégal',
        'contact' => '+221 33 123 45 67',
        'email' => 'contact@evotech.africa',
        'web' => 'www.evotech.africa'
    ];
    
} catch (PDOException $e) {
    error_log("Erreur: " . $e->getMessage());
    die("Erreur lors de la récupération des données");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BON <?= $type === 'entree' ? "D'ENTREE" : "DE SORTIE" ?> <?= $numero_bon ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* STYLES IMPRESSION TICKET THERMIQUE 80mm */
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
                font-family: 'Courier New', 'Lucida Console', monospace !important;
            }
            .cut-space {
                height: 15mm !important; 
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
            }
            .force-black, .force-black * {
                color: #000000 !important;
                border-color: #000000 !important;
            }
            .text-dark { color: #000000 !important; }
            .border-dark { border-color: #000000 !important; }
            .bg-dark { background-color: #000000 !important; }
            
            /* Forcer les polices monospace pour aspect ticket */
            .ticket, .ticket * {
                font-family: 'Courier New', 'Lucida Console', monospace !important;
            }
            
            /* Améliorer lisibilité */
            .font-bold { font-weight: 800 !important; }
            .text-[8px] { font-size: 8px !important; }
            .text-[9px] { font-size: 9px !important; }
            .text-[10px] { font-size: 10px !important; }
            .text-[11px] { font-size: 11px !important; }
            
            /* Supprimer backgrounds inutiles */
            .bg-gray-100, .bg-yellow-50, .bg-green-50 {
                background: white !important;
                border: 1px solid #000 !important;
            }
        }

        /* STYLES ECRAN */
        body { 
            font-family: 'Courier New', 'Lucida Console', 'Segoe UI', monospace; 
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
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
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border-radius: <?= $type === 'entree' ? '12px 12px 12px 12px' : '12px 12px 12px 12px' ?>;
            position: relative;
            overflow: hidden;
            border-left: 4px solid <?= $type === 'entree' ? '#059669' : '#b91c1c' ?>;
        }
        
        .line { 
            border-top: 1px solid #000000; 
            margin: 8px 0; 
            opacity: 0.8;
        }
        
        .dotted-line {
            border-top: 1px dashed #000000;
            margin: 8px 0;
            opacity: 0.6;
        }
        
        .bon-badge {
            background: <?= $type === 'entree' ? '#059669' : '#b91c1c' ?>;
            color: white;
            font-weight: bold;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            display: inline-block;
            letter-spacing: 1px;
        }
        
        .montant-paye {
            color: <?= $type === 'entree' ? '#059669' : '#b91c1c' ?> !important;
            font-weight: bold;
        }
        
        /* Animation écran */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        .btn-entree {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        }
        
        .btn-sortie {
            background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
        }
        
        .btn-entree:hover {
            background: linear-gradient(135deg, #047857 0%, #059669 100%);
        }
        
        .btn-sortie:hover {
            background: linear-gradient(135deg, #991b1b 0%, #b91c1c 100%);
        }
        
        /* Style monospace pour ticket */
        .ticket, .ticket * {
            font-family: 'Courier New', 'Lucida Console', 'Segoe UI', monospace;
        }
    </style>
</head>
<body class="text-gray-900">

    <!-- INTERFACE AVANT IMPRESSION -->
    <div class="no-print animate-fade-in mb-8 w-full max-w-md">
        <div class="bg-white rounded-xl shadow-2xl p-6 mb-4">
            <h2 class="text-xl font-bold text-center mb-4 text-gray-800">
                BON DE <?= $type === 'entree' ? "D'ENTRÉE" : "DE SORTIE" ?>
            </h2>
            
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto <?= $type === 'entree' ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-red-500 to-red-600' ?> rounded-full flex items-center justify-center mb-2">
                        <i class="fas <?= $type === 'entree' ? 'fa-arrow-down' : 'fa-arrow-up' ?> text-white text-2xl"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">N° Bon</span>
                    <p class="font-bold text-gray-900"><?= $numero_bon ?></p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center mb-2">
                        <i class="fas fa-calendar-alt text-white text-2xl"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Date</span>
                    <p class="font-bold text-gray-900"><?= date('d/m/Y') ?></p>
                </div>
            </div>
            
            <button onclick="window.print()" class="w-full <?= $type === 'entree' ? 'btn-entree' : 'btn-sortie' ?> text-white font-bold py-4 px-6 rounded-lg hover:opacity-90 transition-all duration-300 shadow-lg hover:shadow-xl flex items-center justify-center space-x-3">
                <i class="fas fa-print text-xl"></i>
                <span>IMPRIMER LE BON</span>
            </button>
            
            <a href="mouvements.php" class="w-full bg-slate-700 hover:bg-slate-800 text-white font-bold py-3 rounded-lg shadow transition-all flex items-center justify-center gap-3 mt-3">
                <i class="fas fa-arrow-left"></i>
                <span>RETOUR AUX MOUVEMENTS</span>
            </a>
        </div>
        
        <!-- Résumé -->
        <div class="bg-white rounded-xl shadow-2xl p-6">
            <h3 class="font-bold text-lg mb-4 text-gray-800">Résumé de l'opération</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Type:</span>
                    <span class="font-bold <?= $type === 'entree' ? 'text-green-600' : 'text-red-600' ?> uppercase">
                        <?= $type === 'entree' ? 'ENTRÉE' : 'SORTIE' ?>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Bénéficiaire:</span>
                    <span class="font-bold"><?= htmlspecialchars($beneficiaire) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Compte:</span>
                    <span class="font-bold"><?= htmlspecialchars($mouvement['compte_nom']) ?></span>
                </div>
                <div class="flex justify-between border-t border-gray-200 pt-3">
                    <span class="text-gray-800 font-semibold">MONTANT:</span>
                    <span class="font-bold <?= $type === 'entree' ? 'text-green-600' : 'text-red-600' ?> text-xl">
                        <?= $mouvement['devise'] ?? 'CFA' ?> <?= number_format($mouvement['montant'], 0, ',', ' ') ?>
                    </span>
                </div>
                <div class="mt-2 p-3 rounded-lg bg-gray-50 border border-gray-200">
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        <?= htmlspecialchars($mouvement['motif']) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- TICKET À IMPRIMER - FORMAT 80mm -->
    <div class="ticket force-black text-super-dark">
        
        <!-- EN-TÊTE EVOTECH -->
        <div class="text-center mb-2">
            <div class="flex justify-center items-center mb-1">
                <div class="w-10 h-10 <?= $type === 'entree' ? 'bg-green-600' : 'bg-red-600' ?> rounded-full flex items-center justify-center mr-2">
                    <span class="text-white font-bold text-lg">EA</span>
                </div>
                <h1 class="text-xl font-black tracking-tight">EVOTECH</h1>
            </div>
            <p class="text-[9px] mt-1 uppercase font-bold"><?= $type === 'entree' ? "BON D'ENTRÉE" : "BON DE SORTIE" ?></p>
            <p class="text-[8px] opacity-80">AFRICA | GESTION INTÉGRÉE</p>
        </div>

        <div class="line border-dark"></div>

        <!-- NUMÉRO ET DATE -->
        <div class="flex justify-between items-start mb-3">
            <div class="text-[10px]">
                <span class="bon-badge">N° <?= $numero_bon ?></span>
            </div>
            <div class="text-right text-[9px]">
                <p class="font-semibold"><?= $date_mouvement ?></p>
                <p class="text-[8px]">OP: #<?= str_pad((int) $mouvement_id, 5, '0', STR_PAD_LEFT) ?></p>
            </div>
        </div>

        <!-- INFORMATIONS BÉNÉFICIAIRE -->
        <div class="bg-gray-100 p-2 text-[9px] mb-3">
            <div class="flex justify-between">
                <div>
                    <p class="font-black text-[10px]">BÉNÉFICIAIRE</p>
                    <p class="uppercase font-bold"><?= htmlspecialchars($beneficiaire) ?></p>
                </div>
                <div class="text-right">
                    <p class="font-black text-[10px]">COMPTE</p>
                    <p><?= htmlspecialchars($mouvement['compte_nom']) ?></p>
                    <p class="text-[8px]"><?= $mouvement['compte_type'] ?? 'CAISSE' ?></p>
                </div>
            </div>
        </div>

        <!-- MOTIF -->
        <div class="mb-3">
            <p class="font-black text-[10px] mb-1">MOTIF</p>
            <p class="text-[9px] border border-black p-1.5">
                <?= htmlspecialchars($mouvement['motif']) ?>
            </p>
        </div>

        <!-- DÉTAILS FINANCIERS -->
        <div class="mb-3">
            <table class="w-full text-[9px]">
                <thead>
                    <tr class="border-b-2 border-black">
                        <th class="text-left pb-1 font-black">DÉSIGNATION</th>
                        <th class="text-right pb-1 font-black">MONTANT</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="py-1 pr-2">
                            <span class="font-medium">RÉFÉRENCE PIÈCE</span>
                            <div class="text-[8px]"><?= !empty($mouvement['reference_piece']) ? htmlspecialchars($mouvement['reference_piece']) : 'N/A' ?></div>
                        </td>
                        <td class="text-right py-1 align-middle" rowspan="2">
                            <span class="font-bold text-[14px] <?= $type === 'entree' ? 'text-green-700' : 'text-red-700' ?>">
                                <?= $mouvement['devise'] ?? 'CFA' ?><?= number_format($mouvement['montant'], 0, ',', ' ') ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-1 pr-2">
                            <span class="font-medium">OPÉRATION</span>
                            <div class="text-[8px]"><?= $type === 'entree' ? 'ENCAISSEMENT' : 'DÉCAISSEMENT' ?></div>
                        </td>
                    </tr>
                    
                    <?php if (!empty($mouvement['projet_reference'])): ?>
                    <tr class="border-t border-black">
                        <td class="py-1 pr-2" colspan="2">
                            <span class="font-medium">PROJET:</span>
                            <span class="text-[8px]"><?= htmlspecialchars($mouvement['projet_reference']) ?> - <?= htmlspecialchars($mouvement['projet_titre']) ?></span>
                        </td>
                    </tr>
                    <?php elseif (!empty($mouvement['contrat_reference'])): ?>
                    <tr class="border-t border-black">
                        <td class="py-1 pr-2" colspan="2">
                            <span class="font-medium">CONTRAT:</span>
                            <span class="text-[8px]"><?= htmlspecialchars($mouvement['contrat_reference']) ?> - <?= htmlspecialchars($mouvement['contrat_type']) ?></span>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- MONTANT EN LETTRES -->
        <div class="border-t-2 border-black pt-2 mt-2">
            <p class="font-black text-[8px] mb-1">ARRÊTÉ À LA SOMME DE :</p>
            <p class="text-[8px] leading-tight uppercase font-bold">
                <?= $montant_lettres ?>
            </p>
        </div>

        <!-- SIGNATURES -->
        <div class="grid grid-cols-3 gap-1 mt-4 text-[8px]">
            <div class="text-center">
                <p class="font-black">CAISSIER</p>
                <div class="h-8"></div>
                <div class="border-t border-black pt-1">
                    <?= htmlspecialchars($mouvement['cree_par_prenom'] ?? '') ?> <?= htmlspecialchars($mouvement['cree_par_nom'] ?? '') ?>
                </div>
            </div>
            <div class="text-center">
                <p class="font-black">BÉNÉFICIAIRE</p>
                <div class="h-8"></div>
                <div class="border-t border-black pt-1 uppercase">
                    <?= htmlspecialchars($beneficiaire) ?>
                </div>
            </div>
            <div class="text-center">
                <p class="font-black">DIRECTION</p>
                <div class="h-8"></div>
                <div class="border-t border-black pt-1">
                    DG EVOTECH
                </div>
            </div>
        </div>

        <!-- TRACABILITÉ -->
        <div class="text-[7px] mt-3 pt-2 border-t border-dashed border-black">
            <div class="flex justify-between">
                <span>IMPRESSION: <?= date('d/m/Y H:i:s') ?></span>
                <span>PAR: <?= htmlspecialchars($_SESSION['prenom'] ?? '') ?> <?= htmlspecialchars($_SESSION['nom'] ?? '') ?></span>
            </div>
        </div>

        <!-- PIED DE PAGE -->
        <div class="text-center mt-3 pt-1">
            <p class="text-[9px] font-bold uppercase">MERCI POUR VOTRE CONFIANCE</p>
            <p class="text-[7px] opacity-80"><?= $entreprise['web'] ?> | <?= $entreprise['contact'] ?></p>
            <p class="text-[6px] opacity-70 mt-1">Ce document fait office de pièce justificative</p>
        </div>

        <!-- ZONE DE COUPE -->
        <div class="cut-space bg-white force-black">
            <div class="text-center text-[6px] opacity-40 pt-3">
                <p>- - - - - - - - - - - - COUPER ICI - - - - - - - - - - - -</p>
            </div>
        </div>
    </div>

    <script>
        // OPTIMISATIONS IMPRESSION
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-print si demandé
            <?php if (isset($_GET['auto_print']) && $_GET['auto_print'] == 1): ?>
            setTimeout(function() {
                window.print();
            }, 300);
            <?php endif; ?>
            
            // Masquer interface avant impression
            window.addEventListener('beforeprint', function() {
                document.body.classList.add('printing');
            });
            
            window.addEventListener('afterprint', function() {
                document.body.classList.remove('printing');
                
                // Notification de succès
                const notification = document.createElement('div');
                notification.className = 'no-print fixed top-4 right-4 <?= $type === 'entree' ? 'bg-green-600' : 'bg-red-600' ?> text-white px-4 py-3 rounded-lg shadow-lg animate-fade-in';
                notification.innerHTML = `
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-check-circle text-xl"></i>
                        <div>
                            <p class="font-bold">Bon imprimé</p>
                            <p class="text-sm opacity-90">N° ${'<?= $numero_bon ?>'}</p>
                        </div>
                    </div>
                `;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            });
            
            // Fermeture auto si popup
            if (window.opener) {
                window.addEventListener('afterprint', function() {
                    setTimeout(function() {
                        window.close();
                    }, 1000);
                });
            }
        });
        
        // Raccourci clavier Ctrl+P
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>