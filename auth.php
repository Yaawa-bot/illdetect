<?php
require_once '../config.php';

session_start();

$method = $_SERVER['REQUEST_METHOD'];

// Login
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        sendResponse([
            'success' => false,
            'message' => 'Email et mot de passe requis'
        ], 400);
    }
    
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        
        sendResponse([
            'success' => true,
            'message' => 'Connexion réussie',
            'data' => [
                'token' => 'admin_token_' . session_id(),
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ]
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Email ou mot de passe incorrect'
        ], 401);
    }
}

// Logout
if ($method === 'DELETE') {
    checkAuth();
    
    session_destroy();
    
    sendResponse([
        'success' => true,
        'message' => 'Déconnexion réussie'
    ]);
}

// Vérifier la session
if ($method === 'GET') {
    checkAuth();
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        sendResponse([
            'success' => true,
            'data' => [
                'user' => $user
            ]
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Session invalide'
        ], 401);
    }
}
?>
