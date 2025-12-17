<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SchemeController;
use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\PayinOnboardedBankController;
use App\Http\Controllers\Api\PayoutOnboardedBankController;
use App\Http\Controllers\Api\TicketHelpDeskController;
use App\Http\Controllers\Api\BeneficiaryDetailController;
use App\Http\Controllers\Api\Auth\ManagePasswordController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
//callback Api
use App\Http\Controllers\Api\Callback\PayinCallback\PayinCallbackController;
use App\Http\Controllers\Api\Callback\PayoutCallback\PayoutCallbackController;

//payin 
use App\Http\Controllers\Api\Payin\AllPayinController;

//PayIN dashboard
use App\Http\Controllers\Api\Payin\Dashboard_PayinController;


//PayOUT dashboard
use App\Http\Controllers\Api\Payout\Dashboard_PayoutController;
//PayIN
use App\Http\Controllers\Api\Payin\PayhaltController;
//PayOut
use App\Http\Controllers\Api\Payout\BusyBoxController;
use App\Http\Controllers\Api\Payout\CommanPayoutController;
use App\Http\Controllers\Api\Payout\CashfreepayoutController;

//PayIn/PayOut Glide Crypto
use App\Http\Controllers\Api\Payin\Crypto\Glide\GlidePaymentGatewayController;
use App\Http\Controllers\Api\Callback\PayinCallback\Crypto\GlideWebhookController;


use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;


Route::post('/upload', function (Request $request) {
    // Validate the file
    $request->validate([
        'file' => 'required|file|max:10240', // max 10 MB
    ]);

    // Store the file in storage/app/uploads
    $path = $request->file('file')->store('uploads');

    return response()->json([
        'message' => 'File uploaded successfully',
        'path' => $path,
    ]);
});
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// --------------------
// Protected Routes
// --------------------
// Route::get('credentials',[UserController::class,'showCredentials']);
Route::middleware(['auth:sanctum'])->group(function () {
    //User/Merchant
    Route::post('delete-merchant/{id}', [UserController::class, 'deleteMerchant']);
    Route::post('onboard-merchant', [UserController::class, 'onboardMerchant']);
    Route::get('show-merchant/{id?}', [UserController::class, 'showMerchant']);
    Route::post('update-merchant/{id?}', [UserController::class, 'updateMerchant']);
    Route::put('update-user-statuses', [UserController::class, 'updateUserStatuses']);
    Route::get('get-merchants', [UserController::class, 'getMerchants']);
    Route::post('payin-settlement', [UserController::class, 'managePayinWallet']);
    Route::post('payout-load-wallet', [UserController::class, 'managePayoutWallet']);
    Route::post('payout-take-back', [UserController::class, 'takeBackFromPayoutWallet']);
    Route::put('payin-payout-statuses', [UserController::class, 'payinPayoutStatuses']);
    //Beneficiary Details
    Route::post('store-beneficiary-detail', [BeneficiaryDetailController::class, 'storeBeneficiaryDetails']);
    Route::get('beneficiary-List', [BeneficiaryDetailController::class, 'BeneficiaryDetailsList']);
    Route::post('/delete-Beneficiary/{id?}', [BeneficiaryDetailController::class, 'deleteBeneficiaryDetails']);
    
    //Mid credentials
    Route::get('credentials',[UserController::class,'showCredentials']);
    Route::post('update-credential', [UserController::class, 'updateMerchantCredential']);
    Route::post('add-credential', [UserController::class, 'addCredential']);
    Route::post('delete-credential/{id?}', [UserController::class, 'deleteCredential']);


    
    //Scheme
    Route::get('get-scheme', [SchemeController::class, 'getScheme']);
    Route::post('create-scheme', [SchemeController::class, 'createScheme']);
    Route::get('show-scheme/{id}', [SchemeController::class, 'showScheme']);
    Route::post('update-scheme/{id}', [SchemeController::class, 'updateScheme']);
    Route::post('delete-scheme/{id}', [SchemeController::class, 'deleteScheme']);
    Route::post('update-scheme-status', [SchemeController::class, 'updateSchemeStatus']);
    //Auth Api Token
    Route::get('get-tokens', [AuthTokenController::class, 'getTokens']);
    Route::post('generate-token', [AuthTokenController::class, 'generateAuthToken']);
    Route::post('delete-token/{id}', [AuthTokenController::class, 'deleteAuthToken']);
    //Onboard PayIn Bank
    Route::post('/onboard-payinbank', [PayinOnboardedBankController::class, 'OnboardPayInBank']);
    Route::post('/update-payin-bank-status', [PayinOnboardedBankController::class, 'updatePayinBankStatus']);
    Route::post('/update-bank-status/', [PayinOnboardedBankController::class, 'updateOnboardedBankStatus']);
    Route::post('/delete-payinbank/{id}', [PayinOnboardedBankController::class, 'DestroyPayInBank']); // Delete
    Route::get('/payinbanks-List', [PayinOnboardedBankController::class, 'ListPayInBanks']); //bank list payin

    //Onboard PayOut Bank
    Route::post('/onboard-payoutbank', [PayoutOnboardedBankController::class, 'OnboardPayOutBank']);
    Route::post('/update-payout-bank-status', [PayoutOnboardedBankController::class, 'updatePayoutBankStatus']);
    Route::post('/delete-payoutbank/{id}', [PayoutOnboardedBankController::class, 'DestroyPayOutBank']); // Delete
    Route::get('/payoutbanks-List', [PayoutOnboardedBankController::class, 'ListPayOutBanks']); //bank list payouy
    
    //Authentication
    Route::post('/change-password', [ManagePasswordController::class, 'changePassword'])->name('change.password');
    
    // Reports API's
    Route::post('/create-report', [ReportController::class, 'createReport']);
    Route::get('/reportrecords-List', [ReportController::class, 'ReportRecordsList']);
    Route::get('/crypto-reportrecords-list', [ReportController::class, 'ReportRecordsList']);
    
    Route::any('/collection-record', [ReportController::class, 'CollectionRecord']);
       Route::any('/Merchant-Collection', [ReportController::class, 'MerchantCollection']);
    Route::any('/Merchant-Records', [ReportController::class, 'MerchantRecords']);   


    //TicketHelpDesk
    Route::get('/get-tickets', [TicketHelpDeskController::class, 'getTickets']);
    Route::post('/store-ticket', [TicketHelpDeskController::class, 'storeTicket']);
    Route::get('/show-ticket/{id}', [TicketHelpDeskController::class, 'showTicket']);
    Route::post('/update-ticket/{id}', [TicketHelpDeskController::class, 'updateTicket']);
    Route::post('/delete-ticket/{id}', [TicketHelpDeskController::class, 'deleteTicket']);
    Route::post('/manage-status-and-priority', [TicketHelpDeskController::class, 'manageStatusAndPriority']);
    
    //Dashboard payIN API
    Route::post('/Airpay/request', [Dashboard_PayinController::class, 'generate_Airpay_UPIQR']);
    


    
    //Dashboard payOUT API 
    Route::post('/dashboard-payou/request', [Dashboard_PayoutController::class, 'Dashboard_payoutRequest']);
    
});


// --------------------
// Public Authentication Routes
// --------------------
Route::post('/register', [RegisteredUserController::class, 'store'])->name('register');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');

// Email verification routes (protected, user must be logged in)
Route::middleware('auth:sanctum')->group(function () {
Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])->name('verification.send');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

// Verify email (signed + throttled)
Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

//Callback Apis
Route::group(['prefix'=> 'callback/update'], function() {
    // payin callback
    Route::any('prod/airpaycallbkp', [PayinCallbackController::class, 'airpaycallbkp']);
    
    // payout callback
    Route::any('prod/cashfreecallbkp', [PayoutCallbackController::class, 'cashfreeCallback']);
    
});

//PayHalt Api for PayIN
Route::group(['prefix' => 'ph/payin'], function(){
    Route::post('/request', [PayhaltController::class, 'Generate_request']);
});

//Glide CryptoPayment Integration
Route::group(['prefix' => 'GLIDE'], function(){
    Route::post('/create-glide-widget-url', [GlidePaymentGatewayController::class, 'generateGlidePgWidgetUrl']);
    Route::post('/review-glide-widget-payments', [GlidePaymentGatewayController::class, 'reviewGlideWidgetPayments']);
    Route::post('/payment-success', [GlidePaymentGatewayController::class, 'successGlideWidgetPayments']);
    Route::post('/payment-error', [GlidePaymentGatewayController::class, 'errorGlideWidgetPayments']);
    Route::post('/payment-cancel', [GlidePaymentGatewayController::class, 'cancelGlideWidgetPayments']);
    Route::post('/generate-random-ids', [GlidePaymentGatewayController::class, 'generateRandomIds']);
    Route::post('/webhook/transaction', [GlideWebhookController::class, 'handleGlideWebhook']);
    //Route::get('/webhook/view-all-transactions', [GlideWebhookController::class, 'listAllTransactions']);
    
});


//Airpay api for payin
Route::group(['prefix' => 'payin'], function(){  
    Route::any('/checkstatus', [AllPayinController::class, 'check_status']);
        //payin API
    Route::post('/request', [AllPayinController::class, 'generate_Airpay_UPIQR']);
});

//Busybox Api for Payout
Route::group(['prefix' => 'bb/payout'], function(){
    Route::any('/request', [BusyBoxController::class, 'payoutRequest']);
});

Route::group(['prefix' => 'comman/payout'], function(){
    Route::any('/request', [CommanPayoutController::class, 'payout_request']);
});


Route::group(['prefix' => 'bb/payout'], function(){
    Route::post('/upi/request', [BusyBoxController::class, 'upiRequest']);
});


//Cashfree Api for Payout
Route::group(['prefix' => 'CF/payout'], function(){
    Route::post('/payment/request', [CashfreepayoutController::class, 'payment_request']);
    Route::post('/upi/request', [CashfreepayoutController::class, 'upi_request']);
    Route::post('/status', [CashfreepayoutController::class, 'status']);
});








