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

        $planName = strtolower($data['planName']) ?? null;
        $planPrice  = $data['planPrice'] ?? null;
        $operation = $data['operation'] ?? null;
        $plans = $data['plans'] ?? null;
        $category = $data['product'];

        if (!$planName || !$planPrice || !$category){
            echo json_encode([
                "status" => "error",
                "message" => "Plan Name and Plan Price required"
            ]);
            return;
        }

        if (!preg_match('/^[a-zA-Z|| ]+$/', $planName)){
            echo json_encode([
                "status" => "error",
                "message" => "Invalid plan name"
            ]);
            return;
        }

        if (!preg_match('/^[0-9||.]+$/', $planPrice)){
            echo json_encode([
                "status" => "error",
                "message" => "Invalid plan price"
            ]);
            return;
        }

        if ($operation === "add hosting"){

            $check = $this->pdo->prepare("SELECT * FROM hosting_list WHERE hosting_name = ?");
            $check->execute([$planName]);

            if ($check->rowCount() > 0){
                echo json_encode([
                    "status" => "error",
                    "message" => "Plan already exist"
                ]);
                return;
            }

            $hostingId = uniqid('HOSTING_');

            $insert = $this->pdo->prepare("INSERT INTO `hosting_list`(`hosting_id`, `hosting_name`, `hosting_price`, `category`) VALUES (?,?,?,?)");
            $insert->execute([$hostingId, $planName, $planPrice, $category]);

            echo json_encode([
                "status" => "success",
                "data" => $data,
                "message" => "success"
            ]);
            return;
        }

        if ($operation === "update hosting"){

            if (!$plans){
                echo json_encode([
                    "status" => "error",
                    "message" => "Please select a plan"
                ]);
            }

            $check = $this->pdo->prepare("SELECT * FROM hosting_list WHERE hosting_name = ?");
            $check->execute([$planName]);

            if ($check->rowCount() < 1){
                echo json_encode([
                    "status" => "error",
                    "message" => "Plan do not exist"
                ]);
                return;
            }

            $update = $this->pdo->prepare("UPDATE `hosting_list` SET `hosting_name`=?,`hosting_price`=? WHERE hosting_name = ?");
            $update->execute([$planName, $planPrice, $planName]);

            echo json_encode([
                "status" => "success",
                "data" => $data,
                "message" => "success"
            ]);
            return;
        }
    }

    public function FetchHostingPlans(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $getSharedHosting = $this->pdo->prepare("SELECT * FROM `hosting_list`");
        $getSharedHosting->execute([]);

        if ($getSharedHosting->rowCount() > 0){
            $rows = $getSharedHosting->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "status" => "success",
                "hosting" => $rows
            ]);
            return;
        }

        echo json_encode([
            "status" => "success",
            "hosting" => []
        ]);
    }
}