<?php

use App\Controllers\ApiController;
use App\Controllers\ChallengeController;
use App\Controllers\NotificationController;
use App\Controllers\SimulatorController;
use Rhapsody\Core\Routing\Router;

// API routes can be prefixed for versioning, e.g., /api/v1
Router::get('/api/users', [ApiController::class, 'getUsers']);
Router::get('/api/users/{id}', [ApiController::class, 'getUser']);

// --- NOTIFICATION ROUTES ---
Router::get('/api/notification/check', [NotificationController::class, 'getUnread']);
Router::post('/api/notifications/mark-read', [NotificationController::class, 'markAsRead']);

// --- CAREER/PROSPECT API ROUTE ---
Router::post('/api/career/create_prospect', [\App\Controllers\ProspectController::class, 'handleCreateForm']);
Router::post('/api/career/upgrade_attribute/{attribute}', [\App\Controllers\ProspectController::class, 'upgradeAttribute']);

// --- CHALLENGE ROUTES ---
Router::post('/api/challenges/create', [ChallengeController::class, 'create']);
Router::post('/api/challenges/resolve', [ChallengeController::class, 'resolve']);

// --- SIMULATOR ROUTES ---
// Router::post( '/api/simulator/run', [SimulatorController::class, 'runSimulation'] );
// Router::post( '/api/simulator/run-single', [SimulatorController::class, 'runSingleMatch'] );
// *** FIX: This route is for getting odds/bulk sims, so it must point to 'runMatch' ***
Router::post('/api/simulator/run', [SimulatorController::class, 'runMatch']);
// *** FIX: This route is for a single match/bet, so it must point to 'runSimulation' ***
Router::post('/api/simulator/run-single', [SimulatorController::class, 'runSimulation']);
