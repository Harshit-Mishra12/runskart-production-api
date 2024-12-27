<?php

use App\Http\Controllers\V1\Admin\EventsController;
use App\Http\Controllers\V1\Admin\DashboardController;
use App\Http\Controllers\V1\Admin\CricketExternalController;
use App\Http\Controllers\V1\User\UserCricketExternalController;
use App\Http\Controllers\V1\Admin\FAQController;
use App\Http\Controllers\V1\Admin\UserController;
use App\Http\Controllers\V1\Admin\TermsAndConditionController;
use App\Http\Controllers\V1\User\UserEventsController;
use App\Http\Controllers\V1\User\ProfileController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\User\WalletController;
use App\Http\Controllers\V1\RetailerApp\CategoryController as RetailerAppCategoryController;
use App\Http\Controllers\V1\RetailerApp\ProductController as RetailerAppProductController;
use App\Http\Controllers\V1\TeamExportController;
use App\Http\Controllers\V1\Admin\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::prefix('v1')->group(function () {

    Route::get('/config-clear', function () {
        Artisan::call('config:clear');
        return response()->json(['message' => 'Config cache cleared successfully.']);
    })->name('config.clear');
    Route::get('/optimize', function () {
        Artisan::call('optimize');
        return response()->json(['message' => 'Application optimized successfully.']);
    })->name('optimize');

    Route::post("/auth/login", [AuthController::class, 'login']);
    Route::post("/auth/register", [AuthController::class, 'register']);
    Route::post("/auth/register2", [AuthController::class, 'register2']);
    Route::post("/auth/register/verify", [AuthController::class, 'verifyUser']);
    Route::post("/auth/forget-password", [AuthController::class, 'forgetPassword']);
    Route::post("/auth/forget-password/verify", [AuthController::class, 'forgetPasswordVerifyUser']);
    Route::post("/auth/forget-password/change-password", [AuthController::class, 'forgetPasswordChangePassword']);
    Route::post("/auth/bank-account/verify", [AuthController::class, 'verifyBankAccount']);
    Route::post('/wallet/verify-payment', [WalletController::class, 'verifyPayment']);
    Route::get('/fetchTermsAndConditions', [TermsAndConditionController::class, 'fetchTermsAndConditions']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('admin')->group(function () {
            //events
            Route::get('/teams/export/{event_id}', [TeamExportController::class, 'exportToCsv']);
            Route::post("/events/add", [EventsController::class, 'createEvent']);
            Route::post("/events/update", [EventsController::class, 'updateEvent']);
            Route::get("/events/activate/{eventId}", [EventsController::class, 'activateEventStatus']);
            Route::post("/events/fetch", [EventsController::class, 'getEventList']);
            Route::get("/events/fetch/{eventId}", [EventsController::class, 'getEventDetails']);
            Route::get('/events/delete/{eventId}', [EventsController::class, 'deleteEvent']);
            Route::get("/events/matches/players/fetch/{matchId}", [EventsController::class, 'getPlayersList']);
            Route::post("/events/players/playingStatus", [EventsController::class, 'updatePlayingStatus']);

            Route::post("/events/team/create/randomUsers", [EventsController::class, 'createRandomUserTeam']);
            Route::post("/events/user/data", [EventsController::class, 'randomUserData']);
            Route::post("/events/user/data", [EventsController::class, 'randomUserData']);
            Route::get("/events/teams/prize-amount/{eventId}", [EventsController::class, 'getTeamsByEvent']);




            Route::post("/transactions/fetch", [TransactionController::class, 'getAllUserTransactions']);
            Route::post("/transactions/status/update", [TransactionController::class, 'updateTransactionStatus']);






            //cricket api
            Route::post('/events/fetch-matches/external', [CricketExternalController::class, 'fetchMatches']);
            //dashboard
            Route::get("/dashboard", [DashboardController::class, 'getDashboardData']);
            Route::post('/events/count', [DashboardController::class, 'getTotalEventsByType']);

            //faq
            Route::get("/faq/fetch", [FAQController::class, 'fetchFaqList']);
            Route::post("/faq/add", [FAQController::class, 'createFaq']);
            Route::post("/faq/update", [FAQController::class, 'updateFaq']);
            Route::get('/faq/delete/{faqId}', [FAQController::class, 'deleteFaq']);

            //users
            Route::post("/users/fetch", [UserController::class, 'fetchAllUsers']);
            Route::get('/users/fetch/{userId}', [UserController::class, 'fetchUserDetails']);
            Route::post('/users/changeverificationdocstatus', [UserController::class, 'changeVerificationStatus']);
            Route::post('/users/changestatus', [UserController::class, 'toggleUserStatus']);
            Route::post('/uploadTermsAndConditions', [TermsAndConditionController::class, 'uploadTermsAndConditions']);



        });

        Route::prefix('user')->group(function () {
            Route::post("/events/fetch", [UserEventsController::class, 'getEventsListByStatus']);
            Route::post("/account-verify/create-contact", [AuthController::class, 'createContact']);
            Route::post("/account-verify/create-fund-account", [AuthController::class, 'createFundAccount']);
            Route::post("/account-verify/validate-fund-account", [AuthController::class, 'validateFundAccount']);




            Route::post("/events/my/fetch", [UserEventsController::class, 'getMyEventsListByStatus']);

            Route::get("/matchessquad/fetch/{eventId}", [UserCricketExternalController::class, 'getEventSquad']);
            Route::get("/events/prizes/fetch/{eventId}", [UserEventsController::class, 'fetchEventPrizes']);
            Route::post("/events/matches/fetch", [UserEventsController::class, 'fetchMatchesListByEventId']);
            Route::post("/events/team/create", [UserEventsController::class, 'createTeam']);
            Route::post("/events/team/update", [UserEventsController::class, 'updateTeam']);
            Route::get("/events/team/{eventId}", [UserEventsController::class, 'getTeamsByEvent']);
            Route::get("/events/all/team/{eventId}", [UserEventsController::class, 'getAllTeamsByEvent']);
            Route::get("/events/all/user-team/{userId}", [UserEventsController::class, 'getAllTeamsByUser']);

            Route::get("/events/team/players/{teamId}", [UserEventsController::class, 'getPlayersByTeam']);
            Route::get("/events/fetch/{eventId}", [UserEventsController::class, 'getEventDetailsById']);

            Route::get("/events/fetch/playerpoints/{eventId}", [UserEventsController::class, 'getEventsMatchesPlayersPoints']);
            Route::get("/events/fetch/matchinfo/{eventId}", [UserEventsController::class, 'getEventMatchesScorecard']);

            Route::get("/events/fetch/teams/userTransactionDetails/{eventId}", [UserEventsController::class, 'getTeamsTransactionDetails']);




            //transaction

            Route::post("/wallet/transaction", [WalletController::class, 'addFunds']);
            Route::post("/wallet/withdraw-amount", [WalletController::class, 'withdrawAmount']);

            Route::get("/wallet/user-transactions", [WalletController::class, 'getUserTransactions']);

            Route::post("/events/team/transaction", [WalletController::class, 'participateInTeam']);

            Route::post('/wallet/create-order', [WalletController::class, 'createOrder']);

            //profile
            Route::get("/profile/fetch", [ProfileController::class, 'fetchUserProfileDetails']);
            Route::post("/profile/update", [ProfileController::class, 'updateProfile']);
            Route::get("/faq/fetch", [FAQController::class, 'fetchFaqList']);
            Route::post("/profile/bank-details/update", [ProfileController::class, 'updateBankDetails']);

        });

        Route::prefix('retailer')->group(function () {});
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });
});
