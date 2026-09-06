<?php

namespace App\Controllers\API;

use App\Core\DB;

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
        
        print_r($_SESSION['admin']);
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