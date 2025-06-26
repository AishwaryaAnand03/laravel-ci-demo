<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\BusinesRegistrationController;
use App\Http\Controllers\API\PartnerDashboardController;
use App\Http\Controllers\API\PartnerProfileController;
use App\Http\Controllers\API\JobController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\GooglePlacesController;
use App\Http\Controllers\API\WebsiteContentController;
// use Tests\Feature\BusinessRegistrationControllerTest;
use App\Http\Controllers\API\AccountsController;
use App\Http\Controllers\API\StripeSubscriptionController;
use App\Http\Controllers\API\PartnerQuotesController;
use App\Http\Controllers\API\CustomerQuotesController;
use App\Http\Controllers\API\CustomerReviewController;
use App\Http\Controllers\API\PartnerJobmatchesController;
use App\Http\Controllers\API\StripeInvoiceController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\StripeBillingController;
use App\Http\Controllers\API\BookingsController;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::get('/', function () {
    return response()->json(['message' => 'Welcome'], 200);
});






Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

    Route::get('/{userId}/draft-job-data', [JobController::class, 'getJobReviewData']);
    Route::post('/get-job-details-by-id', [JobController::class, 'getJobDetailsById']);
    Route::post('/save-job-details', [JobController::class, 'storeJobDetails']);
    Route::post('/get-move-date', [JobController::class, 'getMoveDate']);
    Route::post('/save-move-date', [JobController::class, 'saveMoveDate']);
    Route::post('/get-inventory-photos', [JobController::class, 'getJobPhotos']);
    Route::get('/jobs/{userId}', [JobController::class, 'getAllJobsForUser']);
    Route::post('/jobs/{jobId}/upload-inventory-photos', [JobController::class, 'uploadPhotos']);
    Route::post('/get-services', [JobController::class, 'getServicesAndAccessInfo']);
    Route::post('/save-services-access', [JobController::class, 'saveServicesAndAccessInfo']);
    Route::post('/save-contact-details', [JobController::class, 'saveContactDetails']);
    Route::post('/submit-private-job', [JobController::class, 'submitPrivateJob']);
    Route::post('/submit-final', [JobController::class, 'submitFinalQuote']);
    Route::post('/public-jobs/submit', [JobController::class, 'submitPublicJob']);
    Route::post('/accept-quote', [JobController::class, 'acceptQuote']);
    Route::get('/place-details', [GooglePlacesController::class, 'getPlaceDetails']);
    Route::get('/travel-distance', [GooglePlacesController::class, 'getTravelDistance']);
    Route::get('/users/{userId}/details', [UserController::class, 'getUserDetails']);
    Route::get('/get-removalists', [WebsiteContentController::class, 'getRemovalists']);
    Route::post('/get-city-data', [WebsiteContentController::class, 'getCityData']);
    Route::post('/get-suburb-data', [WebsiteContentController::class, 'getSuburbData']);
    Route::post('/get-town-data', [WebsiteContentController::class, 'getTownData']);
    Route::post('/get-interstatetown-data', [WebsiteContentController::class, 'getInterstateTownData']);


    //Partner Dashboard
    Route::post('/get-partner-dashboard' , [PartnerDashboardController::class, 'getData']);




    Route::middleware('api')->group(function () {


   Route::get('/get-user', [BusinesRegistrationController::class, 'getUserById']);
        Route::post('/business-register', [BusinesRegistrationController::class, 'register']);
    Route::post('/login', [UserController::class, 'login']);
    Route::post('/confirm-user', [UserController::class, 'confirmUser']);
    Route::post('/resend-otp', [UserController::class, 'resendOTP']);
    Route::post('/forgot-password', [UserController::class, 'forgotPassword']);
    Route::post('/verify-forgot-password-code', [UserController::class, 'verifyForgotPasswordCode']);
    Route::post('/logout', [UserController::class, 'logout']);
    Route::post('/change-password', [UserController::class, 'changePassword']);
    Route::get('/subscription-plans', [StripeSubscriptionController::class, 'getSubscriptionPlans']);
    Route::post('stripe/webhook', [StripeSubscriptionController::class, 'handleStripeWebhook']);
    Route::post('/get-joblist', [PartnerJobmatchesController::class, 'getjoblist']);
    Route::post('/job-details/{id}', [PartnerJobmatchesController::class, 'jobDetails']);
    Route::post('/submit-quote', [PartnerJobmatchesController::class, 'submitQuote']);
  
   


});




Route::middleware('auth:api')->group(function () {
    Route::prefix('partner')->group(function () {
        Route::post('/quotes', [PartnerQuotesController::class, 'getQuotesList']);
        Route::post('/save-notes', [PartnerQuotesController::class, 'saveNotes']);
        Route::get('/quotes/{uuid}', [PartnerQuotesController::class, 'getQuoteDetails']);
        Route::post('/update-quote-status', [PartnerQuotesController::class, 'updateQuoteStatus']);
        Route::post('/get-reviews', [ReviewController::class, 'getReviews']);
        Route::post('/update-review-status', [ReviewController::class, 'updateReviewStatus']);
        Route::post('/send-review-reminder', [ReviewController::class, 'sendReviewReminder']);
        Route::post('/get-profile', [PartnerProfileController::class, 'getProfile']);
        Route::post('/update-profile', [PartnerProfileController::class, 'updateProfile']);
        Route::get('/get-credit-plans', [StripeSubscriptionController::class, 'getCreditPlans']);
        Route::post('/purchase-credits', [StripeSubscriptionController::class, 'purchaseCredits']);    Route::get('/get-invoices', [StripeInvoiceController::class, 'getInvoices']);

    }); 
    Route::post('/account/update', [AccountsController::class, 'update']);
    Route::post('/update-password', [UserController::class, 'changePassword']);
    Route::get('/user-details', [AccountsController::class, 'getUserDetails']);
    Route::get('/subscription-plans', [StripeSubscriptionController::class, 'getSubscriptionPlans']);
    Route::post('/generate-checkout-link', [StripeSubscriptionController::class, 'generateCheckoutLink']);
    Route::post('/manage-subscription', [StripeSubscriptionController::class, 'manageSubscription']);
    Route::post('/get-quote-for-job', [CustomerQuotesController::class, 'getQuotesForJob']);
    Route::post('/customer-jobs-review', [CustomerReviewController::class, 'submitReview']);    Route::post('/customer-details-update', [AccountsController::class, 'customerDetailsUpdate']);
    Route::post('/get-partner-profile', [AccountsController::class, 'getPartnerProfile']);
    Route::post('/delete-business-logo', [AccountsController::class, 'deleteBusinessLogo']);
    Route::get('/get-payments', [StripeBillingController::class, 'getPayments']);

    Route::post('/get-booking-list', [BookingsController::class, 'index']);
    Route::get('/bookings/{job_id}', [BookingsController::class, 'show']);
    /*Route::post('/bookings/{job_id}/complete', [BookingsController::class, 'complete']);
    Route::post('/bookings/{job_id}/upload-doc', [BookingsController::class, 'uploadDocument']);*/
});





