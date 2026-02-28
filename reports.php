<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// GET - Récupérer tous les signalements avec filtres
if ($method === 'GET') {
    $commune = $_GET['commune'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $limit = $_GET['limit'] ?? 100;
    $offset = $_GET['offset'] ?? 0;
    
    $sql = "SELECT r.*, u.name as user_name, u.email as user_email 
            FROM reports r 
            JOIN users u ON r.user_id = u.id 
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($commune)) {
        $sql .= " AND r.commune = ?";
        $params[] = $commune;
    }
    
    if (!empty($status)) {
        $sql .= " AND r.status = ?";
        $params[] = $status;
    }
    
    if (!empty($search)) {
        $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR r.commune LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
    $params[] = (int)$limit;
    $params[] = (int)$offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
    
    // Décoder les symptômes JSON
    foreach ($reports as &$report) {
        $report['symptoms'] = json_decode($report['symptoms'], true);
    }
    
    // Compter le total
    $countSql = "SELECT COUNT(*) as total FROM reports r JOIN users u ON r.user_id = u.id WHERE 1=1";
    $countParams = [];
    
    if (!empty($commune)) {
        $countSql .= " AND r.commune = ?";
        $countParams[] = $commune;
    }
    
    if (!empty($status)) {
        $countSql .= " AND r.status = ?";
        $countParams[] = $status;
    }
    
    if (!empty($search)) {
        $countSql .= " AND (u.name LIKE ? OR u.email LIKE ? OR r.commune LIKE ?)";
        $searchParam = "%$search%";
        $countParams[] = $searchParam;
        $countParams[] = $searchParam;
        $countParams[] = $searchParam;
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetch()['total'];
    
    sendResponse([
        'success' => true,
        'data' => [
            'reports' => $reports,
            'total' => $total
        ]
    ]);
}

// POST - Créer un nouveau signalement
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $commune = $data['commune'] ?? '';
    $symptoms = $data['symptoms'] ?? [];
    $otherSymptoms = $data['otherSymptoms'] ?? '';
    $date = $data['date'] ?? date('Y-m-d');
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    
    // Validation
    if (empty($name) || empty($email) || empty($commune) || empty($symptoms)) {
        sendResponse([
            'success' => false,
            'message' => 'Données incomplètes'
        ], 400);
    }
    
    // Vérifier/créer l'utilisateur
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
        $stmt->execute([$name, $email, password_hash(uniqid(), PASSWORD_DEFAULT)]);
        $userId = $pdo->lastInsertId();
    } else {
        $userId = $user['id'];
    }
    
    // Créer le signalement
    $symptomsJson = json_encode($symptoms);
    $stmt = $pdo->prepare(
        "INSERT INTO reports (user_id, commune, symptoms, other_symptoms, latitude, longitude, report_date, status) 
         VALUES (?, ?, ?, ?, ?, ?, ?, 'normal')"
    );
    $stmt->execute([$userId, $commune, $symptomsJson, $otherSymptoms, $latitude, $longitude, $date]);
    
    $reportId = $pdo->lastInsertId();
    
    // Vérifier si on dépasse le seuil d'alerte
    $thresholdStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'alert_threshold'");
    $thresholdStmt->execute();
    $threshold = (int)$thresholdStmt->fetch()['setting_value'];
    
    // Compter les signalements pour cette commune (derniers 7 jours)
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) as count FROM reports 
         WHERE commune = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    $countStmt->execute([$commune]);
    $count = $countStmt->fetch()['count'];
    
    // Mettre à jour le statut si seuil atteint
    if ($count >= $threshold) {
        $updateStmt = $pdo->prepare(
            "UPDATE reports SET status = 'alerte' 
             WHERE commune = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $updateStmt->execute([$commune]);
        
        // Créer une alerte si elle n'existe pas déjà
        $alertStmt = $pdo->prepare(
            "SELECT id FROM alerts WHERE commune = ? AND status = 'active'"
        );
        $alertStmt->execute([$commune]);
        
        if (!$alertStmt->fetch()) {
            $createAlertStmt = $pdo->prepare(
                "INSERT INTO alerts (commune, reports_count, threshold, status) VALUES (?, ?, ?, 'active')"
            );
            $createAlertStmt->execute([$commune, $count, $threshold]);
        }
    }
    
    sendResponse([
        'success' => true,
        'message' => 'Signalement enregistré avec succès',
        'data' => [
            'report_id' => $reportId
        ]
    ], 201);
}

// PUT - Mettre à jour un signalement (admin uniquement)
if ($method === 'PUT') {
    checkAuth();
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    $status = $data['status'] ?? '';
    
    if (empty($id) || empty($status)) {
        sendResponse([
            'success' => false,
            'message' => 'Données incomplètes'
        ], 400);
    }
    
    $stmt = $pdo->prepare("UPDATE reports SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    
    sendResponse([
        'success' => true,
        'message' => 'Signalement mis à jour'
    ]);
}

// DELETE - Supprimer un signalement (admin uniquement)
if ($method === 'DELETE') {
    checkAuth();
    
    $id = $_GET['id'] ?? 0;
    
    if (empty($id)) {
        sendResponse([
            'success' => false,
            'message' => 'ID requis'
        ], 400);
    }
    
    $stmt = $pdo->prepare("DELETE FROM reports WHERE id = ?");
    $stmt->execute([$id]);
    
    sendResponse([
        'success' => true,
        'message' => 'Signalement supprimé'
    ]);
}
?>
