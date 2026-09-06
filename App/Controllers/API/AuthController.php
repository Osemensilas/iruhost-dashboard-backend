<?php

namespace App\Controllers\API;

use App\Core\DB;
use PDO;
use PDOException;
use Dotenv\Dotenv;
use Exception;

class AuthController{

    protected $pdo;
    protected $emailHost;
    protected $emailPassword;
    protected $emailEnct;
    protected $emailPort;
    protected $emailUsername;
    protected $resend;
    protected $resendApiCode;
    protected $encryptionKey;
    protected $encryptionIV;
    protected $smtpPassword;
    protected $smtpUsername;
    protected $smtpHost;
    protected $smtpPort;
    protected $smtpEncryption;

    public function __construct() {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
        $dotenv->load();
        $this->pdo = DB::connection();
        $this->emailHost = $_ENV['MAIL_HOST'] ?? null;
        $this->emailUsername = $_ENV['MAIL_USERNAME'] ?? null;
        $this->emailPassword = $_ENV['MAIL_PASSWORD'] ?? null;
        $this->resendApiCode = $_ENV['RESEND_API_KEY'] ?? null;
        $this->encryptionKey = hash('sha256', $_ENV['ENCRYPTION_KEY']);
        $this->encryptionIV = substr(hash('sha256', $_ENV['ENCRYPTION_IV']), 0, 16);

        //$this->resend = Resend::client($this->resendApiCode);

        $this->smtpHost = $_ENV['SMTP_HOST'] ?? null;
        $this->smtpPort = $_ENV['SMTP_PORT'] ?? null;
        $this->smtpUsername = $_ENV['SMTP_USERNAME'] ?? null;
        $this->smtpPassword = $_ENV['SMTP_PASSWORD'] ?? null;
        $this->smtpEncryption = $_ENV['SMTP_ENCRYPTION'] ?? null;
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

    public function Login(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }
        
        $data = json_decode(file_get_contents("php://input"), true);

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        echo json_encode([
            "email" => $email,
            "password" => $password
        ]);

        if (empty($email) || empty($password)) {
            //http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'All field required'
            ]);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)){
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid email address'
            ]);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM admin_users WHERE email = ? AND role = ?");
        $stmt->execute([$email, 'admin']);

        print_r($stmt->rowCount());

        if ($stmt->rowCount() < 1){
            echo json_encode([
                'status' => 'error',
                'message' => 'You do not have permission'
            ]);
            return;
        }

        $row = $stmt->fetch();

        print_r($row);

        if (!password_verify($password, $row['password'])){
            echo json_encode([
                'status' => 'error',
                'message' => 'Incorrect password'
            ]);
            return;
        }

        $_SESSION['admin'] = [
            'user_id' => $row['user_id'],
            'name' => $row['firstname'] . " " . $row['lastname'],
            'email' => $email,
        ];

        echo json_encode([
            'status' => 'success',
            'message' => 'valid admin'
        ]);
    }
}