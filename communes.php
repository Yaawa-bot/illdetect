<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// GET - Récupérer les données des communes
if ($method === 'GET') {
    // Liste des communes d'Abidjan avec coordonnées GPS approximatives
    $communesList = [
        ['name' => 'Abobo', 'latitude' => '5.4278', 'longitude' => '-3.9975'],
        ['name' => 'Adjamé', 'latitude' => '5.3570', 'longitude' => '-4.0235'],
        ['name' => 'Attécoubé', 'latitude' => '5.3305', 'longitude' => '-4.0530'],
        ['name' => 'Cocody', 'latitude' => '5.3484', 'longitude' => '-3.9872'],
        ['name' => 'Koumassi', 'latitude' => '5.3152', 'longitude' => '-3.9533'],
        ['name' => 'Marcory', 'latitude' => '5.2969', 'longitude' => '-3.9833'],
        ['name' => 'Plateau', 'latitude' => '5.3250', 'longitude' => '-4.0150'],
        ['name' => 'Port-Bouët', 'latitude' => '5.2648', 'longitude' => '-3.9149'],
        ['name' => 'Treichville', 'latitude' => '5.3050', 'longitude' => '-4.0086'],
        ['name' => 'Yopougon', 'latitude' => '5.3433', 'longitude' => '-4.0591'],
        ['name' => 'Bingerville', 'latitude' => '5.3550', 'longitude' => '-3.8900'],
        ['name' => 'Songon', 'latitude' => '5.3300', 'longitude' => '-4.2500'],
        ['name' => 'Anyama', 'latitude' => '5.4950', 'longitude' => '-4.0517']
    ];
    
    // Récupérer le seuil d'alerte
    $thresholdStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'alert_threshold'");
    $thresholdStmt->execute();
    $threshold = (int)$thresholdStmt->fetch()['setting_value'];
    
    // Compter les signalements par commune
    $stmt = $pdo->query(
        "SELECT commune, COUNT(*) as count 
         FROM reports 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY commune"
    );
    $reportsCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Combiner les données
    $communesData = [];
    foreach ($communesList as $commune) {
        $count = $reportsCounts[$commune['name']] ?? 0;
        
        // Déterminer le statut
        if ($count >= $threshold) {
            $status = 'alerte';
        } elseif ($count >= 5) {
            $status = 'attention';
        } else {
            $status = 'normal';
        }
        
        $communesData[] = [
            'name' => $commune['name'],
            'latitude' => $commune['latitude'],
            'longitude' => $commune['longitude'],
            'reports' => $count,
            'status' => $status
        ];
    }
    
    sendResponse([
        'success' => true,
        'data' => [
            'communes' => $communesData,
            'threshold' => $threshold
        ]
    ]);
}
?>
