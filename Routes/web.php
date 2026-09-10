<?php

use App\Controllers\API\AuthController;
use App\Controllers\API\AutomaticController;
use App\Controllers\API\DashboardController;
use App\Controllers\API\MigrationController;
use App\Controllers\API\PlansController;
use App\Controllers\API\SessionController;
use App\Controllers\API\SupportChatController;

/*Migrations Route*/
$router->get('/api/run-migrations', [MigrationController::class, 'UpdateMigrations']);

/*Session Rotes*/
$router->get('/api/get-user', [SessionController::class, 'GetUser']);
$router->get('/api/logout', [SessionController::class, 'Logout']);

/*Authentication Routes*/
$router->post('/api/admin-login', [AuthController::class, 'Login']);

/*Auto Controller*/
$router->post('/api/create-main-admin', [AutomaticController::class, 'CreateMainAdministrator']);
$router->get('/api/auto-expiring', [AutomaticController::class, 'AutoExpiring']);

/** Dashboard Routes */
$router->get('/api/get-signups', [DashboardController::class, 'SignUps']);
$router->get('/api/get-total-sales', [DashboardController::class, 'GetTotalSalesNum']);
$router->get('/api/get-web-sales', [DashboardController::class, 'GetTotalWebSalesNum']);
$router->get('/api/get-active-users', [DashboardController::class, 'ActiveUsers']);
$router->get('/api/get-web-sales', [DashboardController::class, 'GetTotalWebSalesNum']);
$router->get('/api/expiring-products', [DashboardController::class, 'ExpiringProducts']);
$router->post('/api/expiring-message', [DashboardController::class, 'ExpiringMessage']);

/**Support Chart Routes*/
$router->get('/api/admin-get-chats', [SupportChatController::class, 'GetChats']);
$router->get('/api/admin-get-visitor-chats', [SupportChatController::class, 'GetVisitorChats']);
$router->get('/api/get-admin-chat', [SupportChatController::class, 'GetChat']);
$router->post('/api/send-admin-chat', [SupportChatController::class, 'SendChat']);
$router->post('/api/update-admin-chat', [SupportChatController::class, 'UpdateChats']);
$router->get('/api/admin-get-support-tickets', [SupportChatController::class, 'GetSupportTickets']);
$router->get('/api/admin-get-comment-tickets', [SupportChatController::class, 'GetCommentTickets']);
$router->get('/api/admin-unresolved-tickets', [SupportChatController::class, 'UnresolvedTickets']);
$router->get('/api/admin-get-support-chats', [SupportChatController::class, 'SupportChats']);
$router->get('/api/admin-update-chat-status', [SupportChatController::class, 'UpdateChatsStatus']);
$router->post('/api/admin-post-support-message', [SupportChatController::class, 'PostSupportChats']);
$router->post('/api/close-support-chats', [SupportChatController::class, 'CloseSupportChat']);
$router->get('/api/get-user-comments', [SupportChatController::class, 'GetUserComments']);
$router->post('/api/reply-user-comments', [SupportChatController::class, 'ReplyUserComments']);

/*Plans Route*/
$router->post('/api/add-hosting-plan', [PlansController::class, 'AddHostingPlan']);
$router->post('/api/fetch-hosting-plans', [PlansController::class, 'FetchHostingPlans']);