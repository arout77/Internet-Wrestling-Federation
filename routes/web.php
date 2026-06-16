<?php

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\BookerController;
use App\Controllers\CareerController;
use App\Controllers\ChallengeController;
use App\Controllers\DocsController;
use App\Controllers\ManagerController;
use App\Controllers\NotificationController;
use App\Controllers\PageController;
use App\Controllers\SimulatorController;
use App\Controllers\StoreController;
use App\Controllers\TournamentController;
use App\Controllers\TrainController;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use Rhapsody\Core\Routing\Router;

// Define your application routes using the static Router methods.

// These routes should only be accessible to guests.
Router::get('/login', [AuthController::class, 'showLoginForm'])->middleware('guest');
Router::post('/login', [AuthController::class, 'login'])->middleware('guest');
Router::get('/register', [AuthController::class, 'showRegisterForm'])->middleware('guest');
Router::post('/register', [AuthController::class, 'register'])->middleware('guest');

// --- PROTECTED ROUTES ---
// This route should only be accessible to authenticated users.
Router::get('/dashboard', [PageController::class, 'dashboard'])->middleware('auth');
Router::get('/simulator', [SimulatorController::class, 'index'])->middleware('auth');

// --- ADMIN
Router::get('/admin', [AdminController::class, 'index'])->middleware('auth', 'admin');
Router::get('/admin/wrestlers', [AdminController::class, 'listWrestlers'])->middleware('auth', 'admin');
Router::get('/admin/wrestler/create', [AdminController::class, 'createWrestlerForm'])->middleware('auth', 'admin');
Router::post('/admin/wrestler/store', [AdminController::class, 'storeWrestler'])->middleware('auth', 'admin');
Router::get('/admin/wrestler/edit/{id}', [AdminController::class, 'editWrestler'])->middleware('auth', 'admin');
Router::get('/admin/wrestler/update/{id}', [AdminController::class, 'updateWrestler'])->middleware('auth', 'admin');
Router::post('/admin/wrestler/update/{id}', [AdminController::class, 'updateWrestler'])->middleware('auth', 'admin');

// --- BOOKER GAME MODE
Router::get('/booker', [BookerController::class, 'index'])->middleware('auth');
Router::get('/booker/enter-name', [BookerController::class, 'enterName'])->middleware('auth');
Router::post('/booker/post-enter-name', [BookerController::class, 'postEnterName'])->middleware('auth');
Router::get('/booker/initial-tournaments', [BookerController::class, 'showInitialTournaments'])->middleware('auth');
Router::post('/booker/run-initial-tournaments', [BookerController::class, 'runInitialTournaments'])->middleware('auth');
Router::get('/booker/new-event', [BookerController::class, 'newEvent'])->middleware('auth');
Router::post('/booker/venue', [BookerController::class, 'postVenue'])->middleware('auth');
Router::get('/booker/advertising', [BookerController::class, 'advertising'])->middleware('auth');
Router::post('/booker/advertising', [BookerController::class, 'postAdvertising'])->middleware('auth');
Router::get('/booker/hire', [BookerController::class, 'hireWrestlers'])->middleware('auth');
Router::post('/booker/hire', [BookerController::class, 'postHireWrestlers'])->middleware('auth');
Router::get('/booker/matches', [BookerController::class, 'bookMatches'])->middleware('auth');
Router::post('/booker/matches', [BookerController::class, 'postBookMatches'])->middleware('auth');
Router::get('/booker/simulate', [BookerController::class, 'simulateEvent'])->middleware('auth');
Router::post('/booker/retire', [BookerController::class, 'retire'])->middleware('auth');

// --- CAREER / PROSPECT ROUTES ---
Router::get('/career', [CareerController::class, 'index'])->middleware('auth');
Router::get('/career/create', [CareerController::class, 'showCreateForm'])->middleware('auth');
Router::post('/career/create', [CareerController::class, 'handleCreateForm'])->middleware('auth');
Router::get('/career/find_match', [CareerController::class, 'findMatch'])->middleware('auth');
Router::get('/career/find-match', [CareerController::class, 'findMatch'])->middleware('auth');
Router::post('/career/match', [CareerController::class, 'runMatch'])->middleware('auth');
Router::get('/career/match-result', [CareerController::class, 'showMatchResult'])->middleware('auth');
Router::post('/career/select_archetype', [CareerController::class, 'selectArchetype'])->middleware('auth');
// --- END CAREER ROUTES ---

// --- CHALLENGES
Router::get('/challenge', [ChallengeController::class, 'index'])->middleware('auth');
Router::get('/challenge/manage', [ChallengeController::class, 'manage'])->middleware('auth');
Router::get('/challenge/ajax_get_challenge_details/{pid}', [ChallengeController::class, 'ajaxGetChallengeDetails'])->middleware('auth');
Router::post('/challenge/accept', [ChallengeController::class, 'accept'])->middleware('auth');
Router::post('/challenge/decline', [ChallengeController::class, 'decline'])->middleware('auth');

// --- Managers
Router::get('/career/hire_manager', [ManagerController::class, 'index'])->middleware('auth');
Router::post('/manager/purchase/{id}', [ManagerController::class, 'purchase'])->middleware('auth');
Router::post('/manager/fire', [ManagerController::class, 'fire'])->middleware('auth');

// --- NOTIFICATIONS
Router::get('/notifications', [NotificationController::class, 'index'])->middleware('auth');

Router::get('/store', [StoreController::class, 'index'])->middleware('auth');

// --- TOURNAMENT ROUTES ---
Router::get('/tournament', [TournamentController::class, 'index'])->middleware('auth');
Router::get('/tournament/{type}', [TournamentController::class, 'index'])->middleware('auth');
Router::post('/tournament/start', [TournamentController::class, 'start'])->middleware('auth');
Router::post('/tournament/get_odds', [TournamentController::class, 'get_odds'])->middleware('auth');
Router::post('/tournament/matchup_odds', [TournamentController::class, 'get_matchup_odds'])->middleware('auth');
Router::post('/tournament/simulate', [TournamentController::class, 'simulate'])->middleware('auth');
Router::post('/tournament/payToContinue', [TournamentController::class, 'payToContinue'])->middleware('auth');

// --- TRAINING ROUTES
// Page to purchase new moves
Router::get('/career/train', [TrainController::class, 'index'])->middleware('auth');
Router::get('/train', [TrainController::class, 'index'])->middleware('auth');

// --- The routes below can be viewed by visitors and logged in users
// --- DOCUMENTATION ROUTES ---
Router::get('/docs', [DocsController::class, 'index']);
Router::get('/docs/caching', [DocsController::class, 'performance']);
Router::get('/docs/cli', [DocsController::class, 'cli']);
Router::get('/docs/controllers', [DocsController::class, 'controllers']);
Router::get('/docs/doctrine', [DocsController::class, 'doctrine']);
Router::get('/docs/events', [DocsController::class, 'events']);
Router::get('/docs/file-uploader', [DocsController::class, 'fileUploader']);
Router::get('/docs/image-processing', [DocsController::class, 'imageProcessing']);
Router::get('/docs/installation', [DocsController::class, 'installation']);
Router::get('/docs/logging', [DocsController::class, 'logging']);
Router::get('/docs/mailer', [DocsController::class, 'mailer']);
Router::get('/docs/middleware', [DocsController::class, 'middleware']);
Router::get('/docs/models', [DocsController::class, 'models']);
Router::get('/docs/pagination', [DocsController::class, 'pagination']);
Router::get('/docs/request', [DocsController::class, 'request']);
Router::get('/docs/response', [DocsController::class, 'response']);
Router::get('/docs/routing', [DocsController::class, 'routing']);
Router::get('/docs/security', [DocsController::class, 'security']);
Router::get('/docs/seo', [DocsController::class, 'seo']);
Router::get('/docs/updating', [DocsController::class, 'updating']);
Router::get('/docs/validation', [DocsController::class, 'validation']);
Router::get('/docs/views', [DocsController::class, 'views']);

Router::get('/docs/gamemodes', [DocsController::class, 'gamemodes']);
Router::get('/docs/gamemodes/booking', [DocsController::class, 'booking']);
Router::get('/docs/faq', [DocsController::class, 'faq']);
Router::get('/docs/career_guide', [DocsController::class, 'career']);
Router::get('/docs/features_rewards', [DocsController::class, 'features']);
Router::get('/docs/simulation-engine', [DocsController::class, 'simengine']);
Router::get('/docs/traits_guide', [DocsController::class, 'traits']);
Router::get('/docs/index', [DocsController::class, 'index']);

Router::get('/', [PageController::class, 'index']);
Router::get('/about', [PageController::class, 'about']);
Router::get('/contact', [App\Controllers\PageController::class, 'contact']);
Router::post('/contact', [App\Controllers\PageController::class, 'handleContact']);

Router::get('/sitemap.xml', [SitemapController::class, 'generate']);

Router::get('/logout', [AuthController::class, 'logout']);

// This will match URLs like /posts/hello-world or /posts/123
Router::get('/posts/{slug}', [PageController::class, 'showPost']);

// --- PROTECTED ROUTES ---
// This route should only be accessible to authenticated users.
Router::get('/dashboard', [PageController::class, 'dashboard'])->middleware('auth');
Router::get('/upload', [App\Controllers\PageController::class, 'showUploadForm'])->middleware('auth');
Router::post('/upload', [App\Controllers\PageController::class, 'handleUpload'])->middleware('auth');
Router::get('/users', [PageController::class, 'showUsers']);
Router::get('/users/{user_id}', [PageController::class, 'viewUser']);
