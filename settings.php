<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// GET - Récupérer le seuil actuel
if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'alert_threshold'");
    $stmt->execute();
    $threshold = $stmt->fetch()['setting_value'];
    
    sendResponse([
        'success' => true,
        'data' => [
            'threshold' => (int)$threshold
        ]
    ]);
}

// PUT - Mettre à jour le seuil
if ($method === 'PUT') {
    checkAuth();
    
    $data = json_decode(file_get_contents('php://input'), true);
    $newThreshold = $data['threshold'] ?? 0;
    
    if ($newThreshold < 1) {
        sendResponse([
            'success' => false,
            'message' => 'Le seuil doit être supérieur à 0'
        ], 400);
    }
    
    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'alert_threshold'");
    $stmt->execute([$newThreshold]);
    
    // Réévaluer les alertes avec le nouveau seuil
    // Résoudre les alertes qui ne dépassent plus le seuil
    $stmt = $pdo->prepare(
        "UPDATE alerts a
         LEFT JOIN (
             SELECT commune, COUNT(*) as count 
             FROM reports 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY commune
         ) r ON a.commune = r.commune
         SET a.status = 'resolved', a.resolved_at = NOW()
         WHERE a.status = 'active' AND (r.count IS NULL OR r.count < ?)"
    );
    $stmt->execute([$newThreshold]);
    
    // Créer de nouvelles alertes pour les communes qui dépassent le nouveau seuil
    $stmt = $pdo->prepare(
        "SELECT commune, COUNT(*) as count 
         FROM reports 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY commune
         HAVING count >= ?"
    );
    $stmt->execute([$newThreshold]);
    $communes = $stmt->fetchAll();
    
    foreach ($communes as $commune) {
        // Vérifier si une alerte active existe déjà
        $checkStmt = $pdo->prepare("SELECT id FROM alerts WHERE commune = ? AND status = 'active'");
        $checkStmt->execute([$commune['commune']]);
        
        if (!$checkStmt->fetch()) {
            // Créer une nouvelle alerte
            $insertStmt = $pdo->prepare(
                "INSERT INTO alerts (commune, reports_count, threshold, status) VALUES (?, ?, ?, 'active')"
            );
            $insertStmt->execute([$commune['commune'], $commune['count'], $newThreshold]);
        }
    }
    
    sendResponse([
        'success' => true,
        'message' => 'Seuil mis à jour avec succès',
        'data' => [
            'threshold' => (int)$newThreshold
        ]
    ]);
}
?>
