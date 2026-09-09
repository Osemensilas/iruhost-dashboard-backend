<?php

namespace App\Controllers\API;

use PDO;
use App\Core\DB;
use Dotenv\Dotenv;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AutomaticController{

    protected $pdo;
    protected $smtpPassword;
    protected $smtpUsername;
    protected $smtpHost;
    protected $smtpPort;
    protected $smtpEncryption;
    protected $whmUsername;
    protected $whmApiToken;
    protected $whmHostname;

    public function __construct() {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
        $dotenv->load();
        $this->pdo = DB::connection();

        //$this->resend = Resend::client($this->resendApiCode);

        $this->smtpHost = $_ENV['SMTP_HOST'] ?? null;
        $this->smtpPort = $_ENV['SMTP_PORT'] ?? null;
        $this->smtpUsername = $_ENV['SMTP_USERNAME'] ?? null;
        $this->smtpPassword = $_ENV['SMTP_PASSWORD'] ?? null;
        $this->smtpEncryption = $_ENV['SMTP_ENCRYPTION'] ?? null;
        $this->whmUsername = $_ENV['WHM_USERNAME'] ?? null;
        $this->whmApiToken = $_ENV['WHM_API_TOKEN'] ?? null;
        $this->whmHostname = $_ENV['WHM_HOST'] ?? null;
    }
    public function CreateMainAdministrator(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        $firstname = "osemen";
        $lastname = "osebonite";
        $password = password_hash("Onion$101", PASSWORD_BCRYPT);
        $role = "admin";
        $userId = uniqid("ADMIN_");
        $permission = "all";
        $email = "osemensilas@gmail.com";

        $stmt = $this->pdo->prepare("INSERT INTO `admin_users`(`user_id`, `role`, `permission`, `firstname`,`lastname`, `email`, `password`) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");

        try{

            $stmt->execute([$userId, $role, $permission, $firstname, $lastname, $email, $password]);

            $_SESSION['user'] = [
                'user_id' => $userId,
                'name' => $firstname . " " . $lastname,
                'email' => $email,
            ];

            session_regenerate_id(true);

            echo json_encode([
                'status' => 'success',
                'message' => 'successful'
            ]);

        }catch(Exception $err){

            echo json_encode([
                'status' => 'error',
                'message' => 'Database Error: ' . $err->getMessage()
            ]);

        }

    }

    public function autoExpiring(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM `products`");
        $stmt->execute();

        $expiring = [];

        if ($stmt->rowCount() > 0){
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach($rows as $row){
                $expiryDate = $row['expiry_date'];
                $twoWeeksBefore = date('Y-m-d', strtotime('-2 weeks', strtotime($expiryDate)));
                $expiryDatePlusOneDay   = date('Y-m-d H:i:s', strtotime($expiryDate . ' +1 day'));
                $expiryDatePlusTwoDays  = date('Y-m-d H:i:s', strtotime($expiryDate . ' +2 days'));
                $expiryDatePlusThreeDays = date('Y-m-d H:i:s', strtotime($expiryDate . ' +3 days'));
            
                $now = date('Y-m-d');

                if ($now > $twoWeeksBefore && $now < $expiryDate) {
                    $expiring[] = [
                        'product' => $row,
                        'period' => "two weeks"
                    ];
                    $this->expiringMessage($expiring);
                }

                if ($now === $expiryDate){
                    $expiring[] = [
                        'product' => $row,
                        'period' => "today"
                    ];
                    $this->expiringMessage($expiring);
                }

                if ($now === $expiryDatePlusOneDay){
                    $expiring[] = [
                        'product' => $row,
                        'period' => "one day"
                    ];
                    $this->expiringMessage($expiring);
                }

                if ($now === $expiryDatePlusTwoDays){
                    $expiring[] = [
                        'product' => $row,
                        'period' => "two days"
                    ];
                    $this->expiringMessage($expiring);
                }

                if ($now === $expiryDatePlusThreeDays){
                    $expiring[] = [
                        'product' => $row,
                        'period' => "three days"
                    ];
                    $this->expiringMessage($expiring);
                }

                if ($now > $expiryDatePlusThreeDays){
                    $expiring[] = [
                        'product' => $row,
                        'period' => "expired"
                    ];
                    $this->expiringMessage($expiring);
                }
            }
        }
    }

    private function expiringMessage($expiring){

        foreach($expiring as $ex){

            $userId = $ex['product']['user_id'];
            $period = $ex['period'];

            if ($period === "expired"){
                $this->suspendService($ex['product']['product_id']);
            }


            $getUser = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $getUser->execute([$userId]);

            $composedMessage = "";

            if ($getUser->rowCount() > 0){
                $user = $getUser->fetch(PDO::FETCH_ASSOC);

                $email = $user['email'];
                $name = $user['name'];
                $product = $ex['product']['product'];
                $productName = $ex['product']['product_name'];
            }

            if ($period === "two weeks"){
                $composedMessage = "We wanted to let you know that your <strong>{$product}</strong> ({$productName}) service with <strong>IruHost</strong> is approaching its expiration date.";
            }

            if ($period === "today"){
                $composedMessage = "We wanted to let you know that your <strong>{$product}</strong> ({$productName}) service with <strong>IruHost</strong> is expires today.";
            }

            if ($period === "one day"){
                $composedMessage = "We wanted to let you know that your <strong>{$product}</strong> ({$productName}) service with <strong>IruHost</strong> has expired. This is a first day grace period before service is suspended.";
            }

            if ($period === "two days"){
                $composedMessage = "We wanted to let you know that your <strong>{$product}</strong> ({$productName}) service with <strong>IruHost</strong> has expired. This is a second day grace period before service is suspended.";
            }

            if ($period === "three days"){
                $composedMessage = "We wanted to let you know that your <strong>{$product}</strong> ({$productName}) service with <strong>IruHost</strong> has expired. This is a third day grace period before service is suspended.";
            }

            if ($period === "expired"){
                $composedMessage = "We wanted to let you know that your <strong>{$product}</strong> ({$productName}) service with <strong>IruHost</strong> has expired. You service is suspended. To reactivate your service, intiate payment.";
            }

            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = $this->smtpHost; // your SMTP server
                $mail->SMTPAuth = true;
                $mail->Username = $this->smtpUsername; // SMTP username
                $mail->Password = $this->smtpPassword;   // SMTP password
                $mail->SMTPSecure = $this->smtpEncryption; // or ENCRYPTION_SMTPS
                $mail->Port = $this->smtpPort; // 465 for SSL

                $mail->setFrom('noreply@iruhost.com', 'IruHost');
                $mail->addAddress($email, $name);

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

                if ($mail->send()){
                    echo json_encode([
                        'status' => 'success', 
                        'message' => 'Message sent successfully'
                    ]);
                } else {
                    echo json_encode([
                        'status' => 'error', 
                        'message' => 'Failed to send message'
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'SMTP Mail Error to ' . $email . ': ' . $mail->ErrorInfo
                ]);
            }
        }
    }

    private function suspendService($productId){

        $getUsername = $this->pdo->prepare("SELECT * FROM products WHERE product_id = ?");
        $getUsername->execute([$productId]);

        if ($getUsername->rowCount() < 1){
            return;
        }

        $rows = $getUsername->fetch(PDO::FETCH_ASSOC);

        $username = $rows['url'];
        $reason = 'Suspended via billing system';

        // WHM server connection details — pull these from config/env, not hardcoded
        $whmHost   = $this->whmHostname;      // e.g. 'yourserver.com'
        $whmPort   = 2087;
        $whmUser   = $this->whmUsername;      // typically 'root' or a reseller with suspend privileges
        $whmToken  = $this->whmApiToken; // WHM API token (preferred over password auth)

        $query = http_build_query([
            'user'   => $username,
            'reason' => $reason,
        ]);

        $url = "https://{$whmHost}:{$whmPort}/json-api/suspendacct?api.version=1&{$query}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: whm {$whmUser}:{$whmToken}",
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            echo "cURL error while suspending {$username}: {$error}\n";
            return false;
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if (isset($data['metadata']['result']) && $data['metadata']['result'] == 1) {
            echo "Successfully suspended account '{$username}' (product ID {$productId}).\n";
            return true;
        }

        $reasonMsg = $data['metadata']['reason'] ?? 'Unknown error';
        echo "Failed to suspend '{$username}': {$reasonMsg}\n";
        return false;
    }
}