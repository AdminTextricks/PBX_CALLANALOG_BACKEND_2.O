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
use App\Http\Controllers\BlockNumberController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\TariffController;
use App\Http\Controllers\TfnController;
use App\Http\Controllers\PurchaseTfnNumberController;
use App\Http\Controllers\TfnGroupController;
use App\Http\Controllers\MainPlansController;
use App\Http\Controllers\ConfTemplateController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RingGroupController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\ConferenceController;
use App\Http\Controllers\IvrMediaController;
use App\Http\Controllers\IvrController;
use App\Http\Controllers\CdrController;
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

# MainPlans
Route::group(['prefix' => 'plan'], function () {
	Route::get('/active', [MainPlansController::class, 'getAllActivePlans']);
	Route::get('/{id?}', [MainPlansController::class, 'getAllPlans']);
});

Route::middleware(['auth:sanctum', 'log.request.response'])->group(function () {

	# Company Management
	Route::group(['prefix' => 'company'], function () {
		//Route::post ('/', [UserController::class, 'addCompany'])->middleware('role:super-admin,add-company');
		Route::post('/', [CompanyController::class, 'registrationByAdminOrReseller']);
		Route::get('/get-balance/{id}', [CompanyController::class, 'getBalance']);
		Route::get('/active', [CompanyController::class, 'getAllActiveCompany']);
		Route::get('/{id?}', [CompanyController::class, 'getAllCompany']);
		Route::patch('/changeStatus/{id}', [CompanyController::class, 'changeStatus']);
		Route::put('/billing-address/{id}', [CompanyController::class, 'updateCompany']);

		Route::get('/activeReseller/{reseller_id}', [CompanyController::class, 'getAllActiveCompanyOfReseller']);
	});

	Route::group(['prefix' => 'user'], function () {		
		Route::get('reseller/active', [UserController::class, 'getActiveResellerUsers']);
		Route::get('reseller/{id?}', [UserController::class, 'getAllResellerUsers']);		
		//Route::get('/balance', [CompanyController::class, 'getBalance']);
		Route::post('/', [UserController::class, 'createUser']);
		Route::get('/active', [UserController::class, 'getAllActiveUsers']);
		Route::get('/{id?}', [UserController::class, 'getUser']);
		Route::patch('/changeStatus/{id}', [UserController::class, 'changeStatus']);
		Route::put('/{id}', [UserController::class, 'updateUser']);
	});

	Route::group(['prefix' => 'user-documents'], function () {
		Route::post('/changeMultipleStatus', [UserDocumentsController::class, 'changeMultipleDocumentStatus']);
		Route::post('/', [UserDocumentsController::class, 'addUserDocuments']);
		Route::get('/{userId?}', [UserDocumentsController::class, 'getUserDocuments']);
		Route::patch('/changeStatus/{id}', [UserDocumentsController::class, 'changeDocumentStatus']);
		Route::put('/{id}', [UserDocumentsController::class, 'updateUserDocument']);

	});


	# Manage Trunks
	Route::group(['prefix' => 'trunk'], function () {
		Route::post('/', [TrunkController::class, 'addTrunk']);
		Route::get('/active', [TrunkController::class, 'getAllActiveTrunks']);
		Route::get('/{id?}', [TrunkController::class, 'getAllTrunk']);
		Route::patch('/changeStatus/{id}', [TrunkController::class, 'changeTrunkStatus']);
		Route::put('/{id}', [TrunkController::class, 'updateTrunk']);
		Route::delete('/{id}', [TrunkController::class, 'deleteTrunk']);
		Route::get('/type/{type}', [TrunkController::class, 'getTrunksByType']);
	});


	# Main Price
	Route::group(['prefix' => 'price'], function () {
		Route::post('/', [MainPriceController::class, 'addSuperAdminPrice']);
		Route::patch('/changeStatus/{id}', [MainPriceController::class, 'changeMainPriceStatus']);
		Route::get('/{id?}', [MainPriceController::class, 'getPriceList']);
		Route::patch('/{id}', [MainPriceController::class, 'updatePrice']);
		Route::delete('/{id}', [MainPriceController::class, 'deletePrice']);
		//Route::delete('/reseller/{id}', [MainPriceController::class, 'deleteResellerPrice']);
		//Route::post('/reseller', [MainPriceController::class, 'addResellerPrice']);
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

	# Tariff Plan
	Route::group(['prefix' => 'tariff'], function () {
		Route::get('/active', [TariffController::class, 'getAllActiveTariff']);
		Route::post('/', [TariffController::class, 'createTariff']);
		Route::get('/{id?}', [TariffController::class, 'getAllTariff']);
		Route::put('/{id}', [TariffController::class, 'updateTariff']);
		Route::patch('/changeStatus/{id}', [TariffController::class, 'changeTariffStatus']);
	});

	# TfnGroup
	Route::group(['prefix' => 'tfngroup'], function () {
		Route::get('/active', [TfnGroupController::class, 'getAllActiveTfngroup']);
		Route::get('/{id?}', [TfnGroupController::class, 'getAllTfngroup']);
	});

	# Add to Cart
	Route::group(['prefix' => 'cart'], function () {
		Route::post('/', [PurchaseTfnNumberController::class, 'addtocart']);
		Route::delete('/{id}', [PurchaseTfnNumberController::class, 'removeFromCart']);
		Route::get('/all', [PurchaseTfnNumberController::class, 'allCartList']);
	});

	# Tfn Number 
	Route::group(['prefix' => 'tfn'], function () {
		Route::get('/destination-type', [TfnController::class, 'destinationType']);
		Route::post('/assign-tfn-number', [TfnController::class, 'assignTfnMain']);
		Route::post('/assign-destination', [TfnController::class, 'assignDestinationType']);
		Route::post('/upload-csv', [TfnController::class, 'uploadCSVfile']);
		Route::post('/search', [PurchaseTfnNumberController::class, 'searchTfn']);
		Route::get('/active', [TfnController::class, 'getAllActiveTfns']);
		Route::post('/', [TfnController::class, 'addAdminTfns']);
		Route::get('/{id?}', [TfnController::class, 'getAllTfn']);
		Route::put('/{id}', [TfnController::class, 'updateTfns']);
		Route::patch('/changeStatus/{id}', [TfnController::class, 'changeTfnsStatus']);
		Route::delete('/{id}', [TfnController::class, 'deleteTfn']);
		Route::post('/{id}', [TfnController::class, 'removeTfnfromTable']);
		Route::get('/getByCountryAndCompany/{country_id}/{company_id}', [TfnController::class, 'getAllActiveTFNByCompanyAndCountry']);
		
	});

	#Block number
	Route::group(['prefix' => 'block-number'], function () {
		Route::post('/', [BlockNumberController::class, 'addBlockNumber']);
		Route::put('/{id}', [BlockNumberController::class, 'updateBlockNumber']);
		Route::patch('/changeStatus/{id}', [BlockNumberController::class, 'changeBlockNumberStatus']);
		Route::get('/active', [BlockNumberController::class, 'getAllActiveBlockNumbers']);
		Route::get('/{id?}', [BlockNumberController::class, 'getAllBlockNumber']);
		Route::get('/getByCompany/{comapny_id}', [BlockNumberController::class, 'getBlockNumbersByCompany']);
		Route::delete('/{id}', [BlockNumberController::class, 'deleteBlockNumber']);
	});

	#Server Number Management
	Route::group(['prefix' => 'server'], function () {
		Route::post('/', [ServerController::class, 'addServer']);
		Route::put('/{id}', [ServerController::class, 'updateServer']);
		Route::patch('/changeStatus/{id}', [ServerController::class, 'changeServerStatus']);
		Route::get('/active', [ServerController::class, 'getAllActiveServers']);
		Route::get('/{id?}', [ServerController::class, 'getAllServers']);
		Route::delete('/{id}', [ServerController::class, 'deleteServer']);
	});

	# Manage Extensions
	Route::group(['prefix' => 'extensions'], function () {
		Route::get('/getSipRegistrationList', [ExtensionController::class, 'getSipRegistrationList'])->name('getSipRegistrationList');
		
		Route::post('/', [ExtensionController::class, 'createExtensions']);
		//Route::post('/', [ExtensionController::class, 'addExtensions']);
		Route::post('/generate', [ExtensionController::class, 'generateExtensions']);
		Route::get('/generatePassword', [ExtensionController::class, 'generateStrongPassword']);
		Route::get('/{id?}', [ExtensionController::class, 'getAllExtensions']);
		Route::put('/{id}', [ExtensionController::class, 'updateExtension']);	
		Route::get('/getByCountryAndCompany/{country_id}/{company_id}', [ExtensionController::class, 'getExtensionsByCountryIdAndCompanyId']);		
		
	});

	#Conf Template Manage
	Route::group(['prefix' => 'conf-template'], function () {
		Route::post('/', [ConfTemplateController::class, 'addConfTemplate']);
		Route::put('/{id}', [ConfTemplateController::class, 'updateConfTemplate']);
		Route::get('/{id?}', [ConfTemplateController::class, 'getAllConfTemplate']);
		Route::delete('/{id}', [ConfTemplateController::class, 'deleteConfTemplate']);
	});

	# Role management
	Route::group(['prefix' => 'role'], function () {
		Route::post('/', [RoleController::class, 'addRole']);
		Route::get('/active', [RoleController::class, 'getAllActiveRole']);
		Route::get('/{id?}', [RoleController::class, 'getRoles']);
		Route::put('/{id}', [RoleController::class, 'updateRole']);
		Route::patch('/changeStatus/{id}', [RoleController::class, 'changeStatus']);
	});

	#Permission Management
	Route::group(['prefix' => 'permission'], function () {
		Route::get('/userAndRolePermission', [PermissionController::class, 'getUserAndRolePermission']);
		Route::get('/rolesPermission/{slug?}', [PermissionController::class, 'getRolePermissions']);
		Route::get('/userPermission/{id?}', [PermissionController::class, 'getUserPermissions']);
		Route::get('/all/{slug}', [PermissionController::class, 'getAllPermissionByRole']);
		Route::get('/permission-by-group/{slug}', [PermissionController::class, 'getAllPermissionByGroup']);
		Route::put('/role-permission', [PermissionController::class, 'updateRolePermissions']);
		Route::put('/user-permission', [PermissionController::class, 'updateUserPermissions']);
	});

	# Ring Group Manage
	Route::group(['prefix' => 'ring-group'], function () {
		Route::post('/', [RingGroupController::class, 'addRingGroup']);
		Route::get('/active', [RingGroupController::class, 'getAllActiveRingGroup']);
		Route::get('/getByCountryAndCompany/{country_id}/{company_id}', [RingGroupController::class, 'getAllActiveByCompanyAndCountry']);
		Route::get('/{id?}', [RingGroupController::class, 'getAllRingGroup']);
		Route::put('/{id}', [RingGroupController::class, 'updateRingGroup']);
		Route::patch('/changeStatus/{id}', [RingGroupController::class, 'changeRingGroupStatus']);
		Route::delete('/{id}', [RingGroupController::class, 'deleteRingGroup']);
	});
	
	# Manage Ring Member
	Route::group(['prefix' => 'ring-member'], function () {
		Route::post('/', [RingGroupController::class, 'addRingMember']);
		Route::get('/{ring_id}', [RingGroupController::class, 'getRingMemberByRingId']);
		Route::delete('/', [RingGroupController::class, 'removeRingMember']);
	});

	# Invoices
	Route::group(['prefix' => 'invoice'], function () {
		Route::get('/getall', [InvoiceController::class, 'getAllInvoiceData']);
		Route::get('/{id}', [InvoiceController::class, 'getInvoiceData']);
		Route::post('/', [InvoiceController::class, 'createInvoice']);
	});

	# Payments Manage.
	Route::group(['prefix' => 'payment'], function () {
		Route::post('/pay', [PaymentController::class, 'PayNow']);
		Route::post('/Striperefund', [PaymentController::class, 'RefundStripePayment']);
		Route::post('/paywithwallet', [PaymentController::class, 'PaywithWallet']);
		Route::post('/addbalance', [PaymentController::class, 'addToWallet']);
	});	


	# Queue Manage
	Route::group(['prefix' => 'queue'], function () {
		Route::post('/', [QueueController::class, 'addQueue']);
		Route::get('/active', [QueueController::class, 'getAllActiveQueue']);
		Route::get('/getByCountryAndCompany/{country_id}/{company_id}', [QueueController::class, 'getAllActiveByCompanyAndCountry']);
		Route::get('/{id?}', [QueueController::class, 'getAllQueue']);
		Route::put('/{id}', [QueueController::class, 'updateQueue']);
		Route::patch('/changeStatus/{id}', [QueueController::class, 'changeQueueStatus']);
		Route::delete('/{id}', [QueueController::class, 'deleteQueue']);
	});

	# Manage Queue Member
	Route::group(['prefix' => 'queue-member'], function () {
		Route::post('/', [QueueController::class, 'addQueueMember']);
		Route::get('/{queue_id}', [QueueController::class, 'getQueueMemberByQueueId']);
		Route::delete('/', [QueueController::class, 'removeQueueMember']);
	});

	# Conferences Manage test
	Route::group(['prefix' => 'conference'], function () {
		Route::post('/', [ConferenceController::class, 'addConference']);
		Route::get('/active', [ConferenceController::class, 'getAllActiveConference']);
		Route::get('/getByCountryAndCompany/{country_id}/{company_id}', [ConferenceController::class, 'getAllActiveByCompanyAndCountry']);
		Route::get('/{id?}', [ConferenceController::class, 'getAllConference']);
		Route::put('/{id}', [ConferenceController::class, 'updateConference']);
		Route::patch('/changeStatus/{id}', [ConferenceController::class, 'changeConferenceStatus']);
		Route::delete('/{id}', [ConferenceController::class, 'deleteConference']);
	});

	Route::group(['prefix'=> 'chnage-password'], function () {
		Route::post('/', [UserController::class,'changePasswordBySuperadmin']);
		Route::post('/self', [UserController::class,'passwordChange']);
	});
	
	#IVR Media Manage
	Route::group(['prefix'=> 'ivr-media'], function () {
		Route::post('/', [IvrMediaController::class,'addIvrMedia']);
		Route::patch('/changeStatus/{id}', [IvrMediaController::class,'changeIVRMediaStatus']);
		Route::put('/{id}', [IvrMediaController::class,'updateIvrMedia']);
		Route::get('/active/{company_id}', [IvrMediaController::class, 'getAllActiveIvrMediaList']);
		Route::get('/{id?}', [IvrMediaController::class, 'getAllIvrMedia']);
		Route::get('/getByCompany/{company_id}', [IvrMediaController::class, 'getAllIvrMediaByCompany']);	
		Route::delete('/{id}', [IvrMediaController::class, 'deleteIvrMedia']);
	});	

	#IVR Manage
	Route::group(['prefix'=> 'ivr'], function () {
		Route::post('/', [IvrController::class,'addIvr']);
		Route::patch('/changeStatus/{id}', [IvrController::class,'changeIVRStatus']);
		Route::put('/{id}', [IvrController::class,'updateIvr']);
		Route::get('/active', [IvrController::class, 'getAllActiveIvrList']);
		Route::get('/{id?}', [IvrController::class, 'getAllIvrList']);
		Route::get('/getByCountryAndCompany/{country_id}/{company_id}', [IvrController::class, 'getIvrListByCompanyAndCountry']);
		Route::delete('/{id}', [IvrController::class,'deleteIvr']);
	});

	#CDR 
	Route::group(['prefix'=> 'cdr-report'], function () {
		Route::get('/inboundCdr/{company_id?}', [CdrController::class, 'getAllCdrList']);
		Route::get('/outboundCdr/{company_id?}', [CdrController::class, 'getAllCallList']);
		//Route::delete('/{id}', [IvrController::class,'deleteIvr']);
	});

});
/*
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
*/
