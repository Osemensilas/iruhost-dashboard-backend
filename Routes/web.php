<?php

use App\Controllers\API\AuthController;

/*Authentication Routes*/
$router->post('/api/admin-login', [AuthController::class, 'Login']);
$router->post('/api/create-main-admin', [AuthController::class, 'CreateMainAdministrator']);