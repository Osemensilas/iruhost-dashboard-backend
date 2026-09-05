<?php
namespace App\Controllers\API;

use App\Core\DB;
use Exception;
use PDO;
use Dotenv\Dotenv;

class MigrationController{

    protected $pdo;
    protected $adminId;

    public function __construct(){
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
        $dotenv->load();

        $this->adminId = $_SESSION['admin']['user_id'];
        $this->pdo =  DB::connection();
    }

    public function UpdateMigrations(){
    // --- Security: Require a key from query string ---
        $key = $_GET['key'] ?? '';
        $envKey = $_ENV['APP_KEY'] ?? 'create1p'; // fallback if .env missing

        if ($key !== $envKey) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Unauthorized'
            ]);
            return;
        }

        // --- Path setup ---
        $phpPath = '/usr/local/bin/php'; // cPanel PHP CLI path (usually this works)
        $migrateScript = __DIR__ . '/../../../commands/migrate.php';
        $logDir = __DIR__ . '/../../../storage';

        if (!file_exists($migrateScript)) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Migration script not found'
            ]);
            return;
        }

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // --- Execute the migrations ---
        ob_start(); // start capturing output
        include $migrateScript;
        $output = ob_get_clean(); // get everything the script printed

        // --- Log the result ---
        $logFile = $logDir . '/migrate.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . "\n" . $output . "\n\n", FILE_APPEND);

        // --- Respond ---
        echo json_encode([
            'status' => 'success',
            'message' => 'Migrations executed successfully',
            'output' => trim($output)
        ]);
    }
}