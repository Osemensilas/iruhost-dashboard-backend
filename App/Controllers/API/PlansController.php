<?php

namespace App\Controllers\API;

use App\Core\DB;
use PDO;
use Exception;

class PlansController{
    protected $pdo;

    public function __construct(){
        $this->pdo = DB::connection();
    }

    public function AddHostingPlan(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        echo json_encode([
            "status" => "success",
            "data" => $data
        ]);
    }
}