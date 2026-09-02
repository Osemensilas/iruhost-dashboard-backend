<?php

namespace App\Controllers\API;

use PDO;
use App\Core\DB;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;

class SupportChatController{

    protected $pdo;
    protected $resend;
    protected $adminId;
    protected $smtpPassword;
    protected $smtpUsername;
    protected $smtpHost;
    protected $smtpPort;
    protected $smtpEncryption;

    public function __construct()
    {
        $this->pdo = DB::connection();
        $this->adminId = $_SESSION['admin']['user_id'];

        $this->smtpHost = $_ENV['SMTP_HOST'] ?? null;
        $this->smtpPort = $_ENV['SMTP_PORT'] ?? null;
        $this->smtpUsername = $_ENV['SMTP_USERNAME'] ?? null;
        $this->smtpPassword = $_ENV['SMTP_PASSWORD'] ?? null;
        $this->smtpEncryption = $_ENV['SMTP_ENCRYPTION'] ?? null;
    }

    public function GetChats(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT c.*
            FROM chats c
            INNER JOIN (
                SELECT user_id, MAX(id) AS last_id
                FROM chats
                WHERE reciever_id = 'admin'
                GROUP BY user_id
            ) latest ON c.id = latest.last_id
            ORDER BY c.id DESC");
        $stmt->execute();
        $rows = '';

        if ($stmt->rowCount() > 0){
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach($rows as $row){
 
                $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->execute([$row['user_id']]);

                if ($stmt->rowCount() > 0){
                    $user = $stmt->fetch();

                    $userChats[] = [
                        'chat' => $row,
                        'name' => $user['firstname'] . " " . $user['lastname']
                    ]; 
                }
            }
            echo json_encode([
                'status' => 'success',
                'result' => $userChats
            ]);
        }
    }

    public function UpdateChats() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        $user = $data["user"];

        echo $user;

        $stmt = $this->pdo->prepare("UPDATE chats SET status = ? WHERE user_id = ?");
        $result = $stmt->execute(['', $user]);

        if ($result){
            echo json_encode([
                "status" => "Updated"
            ]);
        }
    }

    public function sendChat() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        $recieverId = $data['reciever'];
        $message = $data["msg"];

        $getReciver = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $getReciver->execute([$recieverId]);

        if ($getReciver->rowCount() < 1){
            echo json_encode(['status' => 'error', 'message' => 'Error fetching reciever']);
            return;
        }

        $recieverRow = $getReciver->fetch(PDO::FETCH_ASSOC);

        $recieverEmail = $recieverRow['email'];

        $stmt = $this->pdo->prepare("INSERT INTO `chats`(`user_id`, `reciever_id`, `message`, `status`, `image`) VALUES (?,?,?,?,?)");
        $result = $stmt->execute(['admin', $recieverId, $message, 'new', '']);

        //$this->sendMailToReciever($recieverEmail, $message);

        if ($result){
            echo json_encode([
                'status' => 'success',
                'messgae' => 'message sent'
            ]);
        }
    }

    public function GetVisitorChats(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT c.*
            FROM chats c
            INNER JOIN (
                SELECT user_id, MAX(id) AS last_id
                FROM chats
                WHERE reciever_id = 'admin'
                GROUP BY user_id
            ) latest ON c.id = latest.last_id
            ORDER BY c.id DESC");
        $stmt->execute();
        $rows = '';

        if ($stmt->rowCount() > 0){
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach($rows as $row){
 
                $stmt = $this->pdo->prepare("SELECT * FROM chats_reg WHERE user_id = ?");
                $stmt->execute([$row['user_id']]);

                if ($stmt->rowCount() > 0){
                    $user = $stmt->fetch();

                    $userChats[] = [
                        'chat' => $row,
                        'name' => $user['fullname']
                    ]; 
                }
            }
            echo json_encode([
                'status' => 'success',
                'result' => $userChats
            ]);
        }
    }

    public function GetSupportTickets(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $getUnresolvedTickets = $this->pdo->prepare("SELECT * FROM `support` WHERE status = ?");
        $getUnresolvedTickets->execute(['unresolved']);

        if ($getUnresolvedTickets->rowCount() === 0){
            echo json_encode([
                'status' => 'error',
                'message' => 'No unresolved ticket'
            ]);
            return;
        }

        $tickets = $getUnresolvedTickets->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tickets as &$ticket) {

            // default
            $ticket['new_message'] = false;

            $stmt = $this->pdo->prepare(
                "SELECT 1 
                FROM support_chats 
                WHERE ticket_id = ? 
                AND status = ? 
                LIMIT 1"
            );
            $stmt->execute([$ticket['ticket_id'], 'not opened']);

            if ($stmt->rowCount() > 0) {
                $ticket['new_message'] = true;
            }
        }
        unset($ticket);

        echo json_encode([
            'status' => 'success',
            'result' => $tickets
        ]);
    }

    public function GetChat(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $clientId = $_GET['user'] ?? null;

        $stmt = $this->pdo->prepare("SELECT * FROM `chats` WHERE user_id = ? OR reciever_id = ? ORDER BY id");
        $stmt->execute([$clientId, $clientId]);

        if ($stmt->rowCount() > 0){
            $userChat = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($userChat);
        }
    }

    public function GetCommentTickets(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM `general_comments` WHERE comment_reply = ?");
        $stmt->execute(['']);

        if ($stmt->rowCount() > 0){
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'result' => $tickets
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'No unresolved ticket'
            ]);
        }
    }

    public function unresolvedTickets(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM `support` WHERE status = ? AND ticket_id = ?");
        $stmt->execute(['unresolved', $_GET['ticket_id']]);

        if ($stmt->rowCount() > 0){
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'message' => $ticket
            ]);
        }
    }

    public function supportChats(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $ticketId = $_GET['ticket_id'] ?? null;

        $stmt = $this->pdo->prepare("SELECT * FROM `support_chats` WHERE ticket_id = ? ORDER BY id");
        $result = $stmt->execute([$ticketId]);

        if (!$result){
            echo json_encode([
                'status' => 'error',
                'message' => 'no message found'
            ]);
            return;
        }

        $rows = [];

        if ($stmt->rowCount() > 0){
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'message' => $rows
            ]);
        }
    }

    public function PostSupportChats(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $ticketId = htmlspecialchars($_POST['ticket_id'] ?? '', ENT_QUOTES, 'UTF-8');;
        $message = htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8');
        $image = '';

        if (empty($ticketId) || empty($message)){
            echo json_encode([
                'status' => 'error',
                'message' => 'All field required'
            ]);
            return;
        }

        if (isset($_FILES['image']) && !empty($_FILES['image']['name'])) {
            $uploadDir = __DIR__ . "../../../../public/uploads/";

            $filename   = time() . "_" . basename($_FILES['image']['name']);
            $targetFile = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $image = $filename;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Image upload failed']);
                return;
            }
        }

        $userStmt = $this->pdo->prepare("SELECT * FROM `admin_users` WHERE user_id = ? AND role = ?");
        $userStmt->execute([$this->adminId, 'admin']);

        if ($userStmt->rowCount() === 0){
            echo json_encode([
                'status' => 'error',
                'message' => 'User not found'
            ]);
            return;
        }

        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
        $name = $userRow['name'] ?? 'User';
        $email = $userRow['email'] ?? '';

        $parts = preg_split('/\s+/', trim($name));

        $avatar = strtoupper(
            substr($parts[0], 0, 1) . 
            (isset($parts[1]) ? substr($parts[1], 0, 1) : '')
        );

        $getUserStmt = $this->pdo->prepare("SELECT * FROM `support` WHERE ticket_id = ?");
        $getUserStmt->execute([$ticketId]);

        if ($getUserStmt->rowCount() === 0){
            echo json_encode([
                'status' => 'error',
                'message' => 'Ticket not found'
            ]);
            return;
        }

        $ticketRow = $getUserStmt->fetch(PDO::FETCH_ASSOC);
        $userId = $ticketRow['user_id'];

        $stmtTickets = $this->pdo->prepare("INSERT INTO `support_chats`(`ticket_id`, `sender_id`, `sender`, `reciever_id`, `message`, `status`, `image`, `avatar`) VALUES (?,?,?,?,?,?,?,?)");
        $result = $stmtTickets->execute([$ticketId, $this->adminId, 'admin', $userId, $message, 'not opened', $image, $avatar]);

        if (!$result){
            echo json_encode([
                'status' => 'error',
                'message' => 'no message found'
            ]);
            return;
        }


        //$this->sendEmail($message, $email, $name);

        echo json_encode([
            'status' => 'success',
            'message' => 'message sent'
        ]);
    }

    public function updateChatsStatus(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $ticketId = $_GET['ticket_id'];

        $checkStmt = $this->pdo->prepare("SELECT * FROM support_chats WHERE ticket_id = ? AND status = ?");
        $checkStmt->execute([$ticketId, 'not opened']);

        if ($checkStmt->rowCount() < 1){
            json_encode([
                'status' => 'no new message'
            ]);
            return;
        }

        $rows = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach($rows as $row){
            if ($row['status'] === 'not opened'){
                $stmtUpdate = $this->pdo->prepare("UPDATE `support_chats` SET `status`= ? WHERE ticket_id = ?");
                $stmtUpdate->execute(['opened', $ticketId]);

                if ($stmtUpdate->rowCount() < 1){
                    return;
                }

                return;
            }
        }
    }

    public function CloseSupportChat(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        $ticketId = $data['ticket_id'];

        $stmtUpdate = $this->pdo->prepare("UPDATE `support` SET `status`= ? WHERE ticket_id = ?");
        $result = $stmtUpdate->execute(['resolved', $ticketId]);

        if (!$result){
            echo json_encode([
                'status' => 'error',
                'message' => 'status could not be updated'
            ]);
            return;
        }

        echo json_encode([
            'status' => 'status',
            'message' => 'status updated successfully'
        ]);
    }

    public function GetUserComments(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $commentId = $_GET['comment_id'] ?? null;

        $stmt = $this->pdo->prepare("SELECT * FROM `general_comments` WHERE comment_id = ?");
        $stmt->execute([$commentId]);

        if ($stmt->rowCount() > 0){
            $comments = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'result' => $comments
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'No comment found'
            ]);
        }
    }

    public function ReplyUserComments(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        if (!isset($_SESSION['admin'])){
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission']);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        $commentId = $data['comment_id'];
        $reply = htmlspecialchars($data['reply']);

        if (empty($reply)){
            echo json_encode([
                'status' => 'error',
                'message' => 'Reply field is required'
            ]);
            return;
        }

        $getAdmin = $this->pdo->prepare("SELECT * FROM admin_users WHERE user_id = ? AND role = ?");
        $getAdmin->execute([$this->adminId, 'admin']);
        
        if ($getAdmin->rowCount() === 0){
            echo json_encode([
                'status' => 'error',
                'message' => 'User not found'
            ]);
            return;
        }

        $adminRow = $getAdmin->fetch(PDO::FETCH_ASSOC);
        $adminName = $adminRow['firstname'] . " " . $adminRow['lastname'] ?? 'Admin';

        $stmt = $this->pdo->prepare("UPDATE `general_comments` SET comment_reply = ?, reply_by = ? WHERE comment_id = ?");
        $result = $stmt->execute([$reply, $adminName, $commentId]);

        if ($result){
            echo json_encode([
                'status' => 'success',
                'message' => 'Reply sent successfully'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to send reply'
            ]);
        }

        $message = $this->pdo->prepare("SELECT * FROM general_comments WHERE comment_id = ?");
        $message->execute([$commentId]);

        if ($message->rowCount() > 0){
            $messageRow = $message->fetch(PDO::FETCH_ASSOC);
            $userId = $messageRow['user_id'];

            $getUser = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $getUser->execute([$userId]);

            if ($getUser->rowCount() > 0){
                $userRow = $getUser->fetch(PDO::FETCH_ASSOC);
                $email = $userRow['email'];
                $name = $userRow['firstname'] . " " . $userRow['lastname'];
            }
        }

        //$this->sendCommentEmail($reply, $email, $name);
    }

    private function sendMailToReciever($recieverEmail, $message){
        try {
            $this->resend->emails->send([
                'from' => 'IruHost <contact@iruhost.com>',
                'to' => [$recieverEmail],
                'subject' => 'New Message from IruHost',
                'html' => "
                <div style='font-family: Arial, sans-serif; background-color: #f6f8fb; padding: 30px;'>
                    <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 30px;'>
                
                        <p style='color: #333; line-height: 1.6;'>
                        {$message}
                        </p>

                        <p style='text-align:center; color:#777; font-size:13px; margin-top:30px;'>
                        Thank you for contacting <strong>IruHost</strong>.<br>
                        Need help? Contact us at <a href='mailto:contact@iruhost.com' style='color:#007bff;'>contact@iruhost.com</a>
                        </p>
                    </div>
                </div>
                "
            ]);

        } catch (\Exception $e) {
            
        }
    }

    private function sendCommentEmail($message, $email, $name){
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
            $mail->Subject = "Reply to Your Comment on IruHost";
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; background-color: #f6f8fb; padding: 30px;'>
                    <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 30px;'>
                        
                        <p style='color: #333; line-height: 1.6;'>Hello {$name},</p>
                        <p style='color: #333; line-height: 1.6;'>{$message}</p>
                        
                        <div style='text-align:center; color:#777; font-size:13px; margin-top:30px;'>
                        Thank you for being a valued member of the <strong>IruHost</strong> community.<br>
                        Need help? Contact us at <a href='mailto:support@iruhost.com'>support@iruhost.com</a>
                        <div class='logo' style='margin-top: 20px; height: max-content; width: 100%; display: flex; justify-content: center; align-items: center;'>
                            <img src='https://iruhost.com/logo.png' alt='IruHost Logo' style='display: block; margin: 20px auto; width: 60px; height: 60px; object-fit: contain;'>
                        </div>
                    </div>
                </div>
            ";

            if ($mail->send()){
                
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