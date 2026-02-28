<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// GET - Récupérer toutes les alertes
if ($method === 'GET') {
    checkAuth();
    
    $status = $_GET['status'] ?? '';
    
    $sql = "SELECT * FROM alerts WHERE 1=1";
    $params = [];
    
    if (!empty($status)) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $alerts = $stmt->fetchAll();
    
    sendResponse([
        'success' => true,
        'data' => [
            'alerts' => $alerts
        ]
    ]);
}

// POST - Créer une alerte manuellement
if ($method === 'POST') {
    checkAuth();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $commune = $data['commune'] ?? '';
    
    if (empty($commune)) {
        sendResponse([
            'success' => false,
            'message' => 'Commune requise'
        ], 400);
    }
    
    // Compter les signalements pour cette commune
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) as count FROM reports 
         WHERE commune = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    $countStmt->execute([$commune]);
    $count = $countStmt->fetch()['count'];
    
    // Récupérer le seuil
    $thresholdStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'alert_threshold'");
    $thresholdStmt->execute();
    $threshold = (int)$thresholdStmt->fetch()['setting_value'];
    
    // Créer l'alerte
    $stmt = $pdo->prepare(
        "INSERT INTO alerts (commune, reports_count, threshold, status) VALUES (?, ?, ?, 'active')"
    );
    $stmt->execute([$commune, $count, $threshold]);
    
    sendResponse([
        'success' => true,
        'message' => 'Alerte créée',
        'data' => [
            'alert_id' => $pdo->lastInsertId()
        ]
    ], 201);
}

// PUT - Résoudre une alerte
if ($method === 'PUT') {
    checkAuth();
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    
    if (empty($id)) {
        sendResponse([
            'success' => false,
            'message' => 'ID requis'
        ], 400);
    }
    
    $stmt = $pdo->prepare("UPDATE alerts SET status = 'resolved', resolved_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    
    sendResponse([
        'success' => true,
        'message' => 'Alerte résolue'
    ]);
}

// DELETE - Supprimer une alerte
if ($method === 'DELETE') {
    checkAuth();
    
    $id = $_GET['id'] ?? 0;
    
    if (empty($id)) {
        sendResponse([
            'success' => false,
            'message' => 'ID requis'
        ], 400);
    }
    
    $stmt = $pdo->prepare("DELETE FROM alerts WHERE id = ?");
    $stmt->execute([$id]);
    
    sendResponse([
        'success' => true,
        'message' => 'Alerte supprimée'
    ]);
}
?>
