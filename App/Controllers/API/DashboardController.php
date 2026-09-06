<?php

namespace App\Controllers\API;

use App\Core\DB;
use PDO;
use PDOException;
use Dotenv\Dotenv;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class DashboardController{
    protected $pdo;
    protected $smtpPassword;
    protected $smtpUsername;
    protected $smtpHost;
    protected $smtpPort;
    protected $smtpEncryption;

    public function __construct(){
        $this->pdo = DB::connection();

        $this->smtpHost = $_ENV['SMTP_HOST'] ?? null;
        $this->smtpPort = $_ENV['SMTP_PORT'] ?? null;
        $this->smtpUsername = $_ENV['SMTP_USERNAME'] ?? null;
        $this->smtpPassword = $_ENV['SMTP_PASSWORD'] ?? null;
        $this->smtpEncryption = $_ENV['SMTP_ENCRYPTION'] ?? null;
    }

    public function SignUps(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT created_at FROM users WHERE role = ?");
        $stmt->execute(['user']);

        if ($stmt->rowCount() > 0){
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Prepare array for past 8 months (including current month)
            $months = [];
            for ($i = 0; $i < 8; $i++) {
                $timestamp = strtotime("-$i months");
                $key = date("Y-m", $timestamp);
                $label = date("M", $timestamp);
                $months[$key] = ['label' => $label, 'count' => 0];
            }

            // Count signups
            foreach ($rows as $row) {
                $dateReg = $row['created_at'];
                $key = date("Y-m", strtotime($dateReg));
                if (isset($months[$key])) {
                    $months[$key]['count']++;
                }
            }

            // Keep order: most recent first
            $months = array_values($months);

            echo json_encode([
                'status' => 'success',
                'data' => $months,
                'sign_ups' => $stmt->rowCount()
            ]);
        }
    }

    public function ActiveUsers(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT DISTINCT user_id FROM products");
        $stmt->execute();

        $activeUsers = $stmt->rowCount();

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE role = ?");
        $stmt->execute(['user']);

        $totalUsers = $stmt->rowCount() - $activeUsers;

        echo json_encode([
            'status' => 'success',
            'active_user' => $activeUsers,
            'total_user' => $totalUsers
        ]);
    }

    public function GetTotalSalesNum(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE product = ?");
        $stmt->execute(['hosting']);

        $hosting = $stmt->rowCount();

        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE product = ?");
        $stmt->execute(['domain']);

        $domain = $stmt->rowCount();

        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE product = ?");
        $stmt->execute(['SSL']);

        $ssl = $stmt->rowCount();

        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE product = ?");
        $stmt->execute(['email']);

        $email = $stmt->rowCount();

        echo json_encode([
            'domain' => $domain,
            'email' => $email,
            'hosting' => $hosting,
            'ssl' => $ssl,
            'status' => 'success'
        ]);
    }

    public function GetTotalWebSalesNum(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE product = ?");
        $stmt->execute(['web app']);

        $website = $stmt->rowCount();

        $stmt = $this->pdo->prepare("SELECT * FROM products");
        $stmt->execute();

        $product = $stmt->rowCount() - $website;

        echo json_encode([
            'website' => $website,
            'others' => $product,
            'status' => 'success'
        ]);
    }

    public function ExpiringProducts(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $getProducts = $this->pdo->prepare("SELECT * FROM products");
        $getProducts->execute();

        $expiring = [];

        if ($getProducts->rowCount() > 0){
            $products = $getProducts->fetchAll(PDO::FETCH_ASSOC);

            $todayDate = date('Y-m-d');

            foreach($products as $product){

                $expiryDate = $product['expiry_date'];

                $twoWeeksBefore = date('Y-m-d', strtotime('-2 weeks', strtotime($expiryDate)));
                $threeWeeksBefore = date('Y-m-d', strtotime('-3 weeks', strtotime($expiryDate)));
                $oneMonthBefore = date('Y-m-d', strtotime('-4 weeks', strtotime($expiryDate)));

                if ($product['billing'] === "year"){
                    if ($todayDate >= $oneMonthBefore){
                        $expiring[] = $product;
                    }
                }

                if ($product['billing'] === "quarter"){
                    if ($todayDate >= $threeWeeksBefore){
                        $expiring[] = $product;
                    }
                }

                if ($product['billing'] === "month"){
                    if ($todayDate >= $twoWeeksBefore){
                        $expiring[] = $product;
                    }
                }
            }

            echo json_encode([
                'status' => 'success',
                'products' => $expiring
            ]);
        }
    }

/**
 * Manually triggered by an admin clicking a button in the dashboard.
 * Requires an active admin session — this is NOT called by cron.
 */
    public function ExpiringMessage(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $getProducts = $this->pdo->prepare("SELECT * FROM products");
        $getProducts->execute();

        $expiring = [];

        if ($getProducts->rowCount() > 0){
            $products = $getProducts->fetchAll(PDO::FETCH_ASSOC);

            $todayDate = date('Y-m-d');

            foreach($products as $product){

                $expiryDate = $product['expiry_date'];

                $twoWeeksBefore   = date('Y-m-d', strtotime('-2 weeks', strtotime($expiryDate)));
                $threeWeeksBefore = date('Y-m-d', strtotime('-3 weeks', strtotime($expiryDate)));
                $oneMonthBefore   = date('Y-m-d', strtotime('-4 weeks', strtotime($expiryDate)));

                if ($product['billing'] === "year" && $todayDate >= $oneMonthBefore){
                    $expiring[] = $product;
                }

                if ($product['billing'] === "quarter" && $todayDate >= $threeWeeksBefore){
                    $expiring[] = $product;
                }

                if ($product['billing'] === "month" && $todayDate >= $twoWeeksBefore){
                    $expiring[] = $product;
                }
            }

            $this->manualExpiringMessaging($expiring);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'No products found']);
        }
    }

    private function manualExpiringMessaging($expiring){
        if (empty($expiring)) {
            echo json_encode(['status' => 'success', 'message' => 'No product expiring']);
            return;
        }

        $results = [];

        foreach($expiring as $ex){
            $getUsers = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $getUsers->execute([$ex['user_id']]);

            if ($getUsers->rowCount() < 1){
                // No matching user for this product — skip it, don't try to read $rows[0]
                $results[] = ['product_id' => $ex['id'] ?? null, 'status' => 'skipped', 'reason' => 'no user found'];
                continue;
            }

            $rows = $getUsers->fetchAll(PDO::FETCH_ASSOC);

            $email = $rows[0]['email'];
            $name  = $rows[0]['firstname'] . " " . $rows[0]['lastname'];

            // Build the message based on how close to expiry the product is
            $expiryDate   = $ex['expiry_date'];
            $daysLeft     = (int) floor((strtotime($expiryDate) - strtotime(date('Y-m-d'))) / 86400);
            $productName  = $ex['name'] ?? $ex['product_name'] ?? 'your service';
            $prettyExpiry = date('F j, Y', strtotime($expiryDate));

            if ($daysLeft < 0) {
                $composedMessage = "Your service <strong>{$productName}</strong> expired on <strong>{$prettyExpiry}</strong>. Please renew as soon as possible to restore access.";
            } elseif ($daysLeft === 0) {
                $composedMessage = "Your service <strong>{$productName}</strong> expires <strong>today</strong> ({$prettyExpiry}). Please renew now to avoid any interruption.";
            } else {
                $composedMessage = "Your service <strong>{$productName}</strong> is set to expire in <strong>{$daysLeft} day" . ($daysLeft === 1 ? '' : 's') . "</strong>, on <strong>{$prettyExpiry}</strong>.";
            }

            $mail = new PHPMailer(true);
            //$mail->SMTPDebug = 2;

            try {
                $mail->isSMTP();
                $mail->Host = $this->smtpHost;
                $mail->SMTPAuth = true;
                $mail->Username = $this->smtpUsername;
                $mail->Password = $this->smtpPassword;
                $mail->SMTPSecure = $this->smtpEncryption;
                $mail->Port = $this->smtpPort;

                $mail->setFrom('noreply@iruhost.com', 'IruHost');
                $mail->addAddress("osemensilas@gmail.com", $name);

                $mail->isHTML(true);
                $mail->Subject = "Service Expiration Notice";
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; background-color: #f6f8fb; padding: 30px;'>
                        <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 30px;'>

                            <h2 style='color: #1a1a1a; text-align: center; margin-bottom: 20px;'>Service Expiration Reminder</h2>

                            <p style='color: #333; line-height: 1.6;'>Hello {$name},</p>

                            <p style='color: #333; line-height: 1.6;'>
                                {$composedMessage}
                            </p>

                            <p style='color: #333; line-height: 1.6;'>
                                To avoid any interruption, please renew your service before it expires.
                            </p>

                            <p style='color: #333; line-height: 1.6;'>
                                You can log in to your client portal to manage your services and complete the renewal:
                                <br>
                                <a href='https://iruhost.com/' style='color:#2b6cb0;'>https://iruhost.com/</a>
                            </p>

                            <p style='color: #333; line-height: 1.6;'>
                                If you have already renewed, you can ignore this email.
                            </p>

                            <p style='color: #333; line-height: 1.6;'>
                                Best regards,<br>
                                Osemen Silas Oseobonoite<br>
                                CEO, IruHost
                            </p>

                            <div style='text-align:center; color:#777; font-size:13px; margin-top:30px;'>
                                <img src='https://iruhost.com/logo.png' alt='IruHost Logo' style='display:block; margin:20px auto; width:60px; height:60px; object-fit:contain;'>
                            </div>

                        </div>
                    </div>
                    ";

                $sent = $mail->send();
                $results[] = [
                    'product_id' => $ex['id'] ?? null,
                    'email'      => $email,
                    'status'     => $sent ? 'sent' : 'failed'
                ];

            } catch (Exception $e) {
                $results[] = [
                    'product_id' => $ex['id'] ?? null,
                    'email'      => $email,
                    'status'     => 'error',
                    'message'    => $mail->ErrorInfo
                ];
            }
        }

        echo json_encode(['status' => 'success', 'results' => $results]);
    }
}