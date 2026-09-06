<?php

namespace App\Controllers\API;

use App\Core\DB;
use PDO;

class SessionController{
    private $pdo;

    public function __construct() {
        $this->pdo = DB::connection();
    }

    public function userSession(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        header("Content-Type: application/json");

        if (isset($_SESSION['admin'])){
            echo json_encode([
                'success' => true,
                'user' => $_SESSION['admin']
            ]);
        }else{
            echo json_encode([
                "success" => false,
                "message" => "No active session"
            ]);
        }
    }

    public function GetUser(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }
        
        if (!$_SESSION['admin']['user_id']){
            echo json_encode([
                'status' => 'error', 
                'message' => 'No active session'
            ]);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM admin_users WHERE user_id = ?");
        $stmt->execute([$_SESSION['admin']['user_id']]);

        if ($stmt->rowCount() < 1){
            echo json_encode([
                'status' => 'error', 
                'message' => 'User do not exist'
            ]);
            return;
        }

        $rows = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success', 
            'message' => 'User retrieved successfully',
            'user' => $rows
        ]);
    }

    public function Logout(){
        if (isset($_SESSION['user'])){
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
                return;
            }
            session_unset();
            session_destroy();
            echo json_encode(['status' => 'success', 'message' => 'Logged out']);
        }
    }
}