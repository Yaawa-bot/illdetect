<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

if ($method === 'GET') {
    checkAuth();
    
    // Statistiques globales
    $stats = [];
    
    // Total des signalements
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM reports");
    $stats['total_reports'] = $stmt->fetch()['total'];
    
    // Utilisateurs actifs (ayant fait au moins un signalement)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as total FROM reports");
    $stats['active_users'] = $stmt->fetch()['total'];
    
    // Zones en alerte
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM alerts WHERE status = 'active'");
    $stats['alert_zones'] = $stmt->fetch()['total'];
    
    // Communes suivies (communes avec au moins un signalement)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT commune) as total FROM reports");
    $stats['communes_tracked'] = $stmt->fetch()['total'];
    
    // Signalements dernières 24h
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stats['reports_last_24h'] = $stmt->fetch()['total'];
    
    // Signalements par commune (top 5)
    $stmt = $pdo->query(
        "SELECT commune, COUNT(*) as count, 
         CASE 
            WHEN COUNT(*) >= (SELECT setting_value FROM system_settings WHERE setting_key = 'alert_threshold') THEN 'alerte'
            WHEN COUNT(*) >= 5 THEN 'attention'
            ELSE 'normal'
         END as status
         FROM reports 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY commune 
         ORDER BY count DESC 
         LIMIT 10"
    );
    $stats['top_communes'] = $stmt->fetchAll();
    
    // Signalements récents (derniers 10)
    $stmt = $pdo->query(
        "SELECT r.*, u.name as user_name, u.email as user_email 
         FROM reports r 
         JOIN users u ON r.user_id = u.id 
         ORDER BY r.created_at DESC 
         LIMIT 10"
    );
    $recentReports = $stmt->fetchAll();
    
    // Décoder les symptômes JSON
    foreach ($recentReports as &$report) {
        $report['symptoms'] = json_decode($report['symptoms'], true);
    }
    
    $stats['recent_reports'] = $recentReports;
    
    // Évolution des signalements (7 derniers jours)
    $stmt = $pdo->query(
        "SELECT DATE(created_at) as date, COUNT(*) as count 
         FROM reports 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY DATE(created_at) 
         ORDER BY date ASC"
    );
    $stats['reports_evolution'] = $stmt->fetchAll();
    
    // Symptômes les plus fréquents
    $stmt = $pdo->query("SELECT symptoms FROM reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $allSymptoms = [];
    
    while ($row = $stmt->fetch()) {
        $symptoms = json_decode($row['symptoms'], true);
        if (is_array($symptoms)) {
            foreach ($symptoms as $symptom) {
                if (!isset($allSymptoms[$symptom])) {
                    $allSymptoms[$symptom] = 0;
                }
                $allSymptoms[$symptom]++;
            }
        }
    }
    
    arsort($allSymptoms);
    $stats['top_symptoms'] = array_slice($allSymptoms, 0, 10, true);
    
    sendResponse([
        'success' => true,
        'data' => $stats
    ]);
}
?>
