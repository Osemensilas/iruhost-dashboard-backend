<?php

use App\Controllers\API\AuthController;
use App\Controllers\API\DashboardController;
use App\Controllers\API\MigrationController;
use App\Controllers\API\SupportChatController;

/*Migrations Route*/
$router->get('/api/run-migrations', [MigrationController::class, 'UpdateMigrations']);

/*Authentication Routes*/
$router->post('/api/admin-login', [AuthController::class, 'Login']);
$router->post('/api/create-main-admin', [AuthController::class, 'CreateMainAdministrator']);

/** Dashboard Routes */
$router->get('/api/get-signups', [DashboardController::class, 'SignUps']);
$router->get('/api/get-total-sales', [DashboardController::class, 'GetTotalSalesNum']);
$router->get('/api/get-web-sales', [DashboardController::class, 'GetTotalWebSalesNum']);
$router->get('/api/get-active-users', [DashboardController::class, 'ActiveUsers']);
$router->get('/api/get-web-sales', [DashboardController::class, 'GetTotalWebSalesNum']);

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