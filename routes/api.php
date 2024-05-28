<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserDocumentsController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\PasswordResetTokensController;
use App\Http\Controllers\TrunkController;
use App\Http\Controllers\MainPriceController;
use App\Http\Controllers\OutboundCallRateController;
use App\Http\Controllers\ExtensionController;

use App\Http\Controllers\TariffController;
use App\Http\Controllers\TfnController;
use App\Http\Controllers\TfnGroupController;
use App\Http\Controllers\MainPlansController;
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

Route::post('/registration', [UserController::class, 'registration']);
Route::post('/login', [UserController::class, 'login'])->name('login');
Route::post('/verifyEmail', [UserController::class, 'verifyEmailIdByOTP'])->name('verifyEmailIdByOTP');
//Route::get('/user/{id?}', [UserController::class, 'getUser']);
Route::post('/resend-otp', [UserController::class, 'resendOtp'])->name('resendOtp');
Route::get('/countries', [CountryController::class, 'getCountries']);
Route::get('/states/{country_id?}', [StateController::class, 'getStates']);

Route::post('/forgot-password-otp', [PasswordResetTokensController::class, 'sendForgotPasswordOTP']);
Route::post('/password-reset/{otp}', [PasswordResetTokensController::class, 'reset']);


Route::middleware('auth:sanctum')->group(function () {

    # Company Management
	Route::group(['prefix' => 'company'], function () {
		//Route::post ('/', [UserController::class, 'addCompany'])->middleware('role:super-admin,add-company');
		//Route::post('/', [UserController::class, 'addCompany']);
		Route::get('/active', [CompanyController::class, 'getAllActiveCompany']);
		Route::get('/{id?}', [CompanyController::class, 'getAllCompany']);
		Route::patch('/changeStatus/{id}', [CompanyController::class, 'changeStatus']);
		Route::put('/billing-address/{id}', [CompanyController::class, 'updateCompany']);

	});

    Route::group(['prefix' => 'user'], function () {
		Route::get('/balance', [CompanyController::class, 'getBalance']);
		Route::post('/', [UserController::class, 'createUser']);
		Route::get('/active', [UserController::class, 'getAllActiveUsers']);
		Route::get('/{id?}', [UserController::class, 'getUser']);
		Route::patch('/changeStatus/{id}', [UserController::class, 'changeStatus']);
		Route::put('/{id}', [UserController::class, 'updateUser']);
	});

	Route::group(['prefix' => 'user-documents'], function () {		
		Route::post('/', [UserDocumentsController::class, 'addUserDocuments']);
		Route::get('/{userId?}', [UserDocumentsController::class, 'getUserDocuments']);
		Route::patch('/changeStatus/{id}', [UserDocumentsController::class, 'changeDocumentStatus']);
		Route::put('/{id}', [UserDocumentsController::class, 'updateUserDocument']);
	});


	# Inbound Trunks
	Route::group(['prefix' => 'trunk'], function () {
		Route::post('/', [TrunkController::class, 'addTrunk']);
		Route::get('/active', [TrunkController::class, 'getAllActiveTrunks']);
		Route::get('/type/{type}', [TrunkController::class, 'getTrunksByType']);
		Route::get('/{id?}', [TrunkController::class, 'getAllTrunk']);		
		Route::patch('/changeStatus/{id}', [TrunkController::class, 'changeTrunkStatus']);
		Route::put('/{id}', [TrunkController::class, 'updateTrunk']);
		Route::delete('/{id}', [TrunkController::class, 'deleteTrunk']);
	});


	# Main Price
	Route::group(['prefix' => 'price'], function () {
		Route::post('/', [MainPriceController::class, 'addSuperAdminPrice']);		
		Route::patch('/changeStatus/{id}', [MainPriceController::class, 'changeMainPriceStatus']);		
		Route::get('/{id?}', [MainPriceController::class, 'getPriceList']);
		Route::patch('/{id}', [MainPriceController::class, 'updatePrice']);		
		Route::delete('/{id}', [MainPriceController::class, 'deletePrice']);
	});
	# Reseller commission Rate
	Route::group(['prefix' => 'reseller-price'], function () {
		Route::post('/', [MainPriceController::class, 'addResellerPrice']);
		Route::get('/{id?}', [MainPriceController::class, 'getResellerPriceList']);
		Route::patch('/changeStatus/{id}', [MainPriceController::class, 'changeResellerPriceStatus']);
		Route::put('/{id}', [MainPriceController::class, 'updateResellerPrice']);
		Route::delete('/{id}', [MainPriceController::class, 'deleteResellerPrice']);
	});

	# Outbound Call Rates
	Route::group(['prefix' => 'outbound-call-rates'], function () {
		Route::post('/', [OutboundCallRateController::class, 'addOutboundCallRate']);	
		Route::get('/active', [OutboundCallRateController::class, 'getAllActiveOutboundCallRate']);
		Route::get('/{id?}', [OutboundCallRateController::class, 'getAllOutboundCallRate']);				
		Route::patch('/changeStatus/{id}', [OutboundCallRateController::class, 'changeOutboundCallRateStatus']);				
		Route::put('/{id}', [OutboundCallRateController::class, 'updateOutboundCallRate']);
		Route::delete('/{id}', [OutboundCallRateController::class, 'deleteOutboundCallRate']);
	});

	# Manage Extensions
	Route::group(['prefix' => 'extensions'], function () {
		Route::post('/', [ExtensionController::class, 'addExtensions']);	
		Route::post('/generate', [ExtensionController::class, 'generateExtensions']);
		Route::get('/generatePassword', [ExtensionController::class, 'generateStrongPassword']);
		/*
		Route::patch('/changeStatus/{id}', [ExtensionController::class, 'changeOutboundCallRateStatus']);				
		Route::put('/{id}', [ExtensionController::class, 'updateOutboundCallRate']);
		Route::delete('/{id}', [ExtensionController::class, 'deleteOutboundCallRate']);*/
	});
		# Tariff Plan
		Route::group(['prefix' => 'tariff'], function () {
			Route::get('/active', [TariffController::class, 'getAllActiveTariff']);
			Route::post('/', [TariffController::class, 'createTariff']);
			Route::get('/{id?}', [TariffController::class, 'getAllTariff']);
			Route::put('/{id}', [TariffController::class, 'updateTariff']);
			Route::patch('/changeStatus/{id}', [TariffController::class, 'changeTariffStatus']);
		});
	
		# MainPlans
		Route::group(['prefix' => 'plan'], function () {
			Route::get('/active', [MainPlansController::class, 'getAllActivePlans']);
			Route::get('/{id?}', [MainPlansController::class, 'getAllPlans']);
		});
	
		# TfnGroup
		Route::group(['prefix' => 'tfngroup'], function () {
			Route::get('/active', [TfnGroupController::class, 'getAllActiveTfngroup']);
			Route::get('/{id?}', [TfnGroupController::class, 'getAllTfngroup']);
	
		});
	
		# Tfn Number 
		Route::group(['prefix' => 'tfn'], function () {
			Route::get('/active', [TfnController::class, 'getAllActiveTfns']);
			Route::post('/', [TfnController::class, 'addAdminTfns']);
			Route::put('/{id}', [TfnController::class, 'updateTfns']);
			Route::patch('/changeStatus/{id}', [TfnController::class, 'changeTfnsStatus']);
			Route::delete('/{id}', [TfnController::class, 'deleteTfn']);
			Route::get('/{id?}', [TfnController::class, 'getAllTfn']);
		});
});
/*
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
*/

