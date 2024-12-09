<?php


use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NowPaymentsController;
use App\Http\Controllers\RechargeHistoryController;
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
use App\Http\Controllers\IvrOptionController;
use App\Http\Controllers\CdrController;
use App\Http\Controllers\ResellerCommissionController;
use App\Http\Controllers\OneGoUserController;
use App\Http\Controllers\ResellerCallCommissionController;
use App\Http\Controllers\VoiceMailController;
use App\Http\Controllers\TimeGroupController;
use App\Http\Controllers\TimeConditionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ErrorMessageController;
use App\Http\Controllers\ResellerPaymentHistoryController;

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
//Route::post('/sipReload', [UserController::class, 'sipReload']);
Route::post('/sendTestSMSOtp', [UserController::class, 'sendTestSMSOtp']);
Route::post('/registration', [UserController::class, 'registration']);

Route::middleware(['throttle:5,1', 'log.request.response'])->group(function () {
	Route::post('/login', [UserController::class, 'login'])->name('login');
	/********  Webphone API   *********/
	Route::post('/extension_login', [ExtensionController::class, 'extensionLogin']);
	Route::get('/getContactList', [ExtensionController::class, 'extensionContactList']);
	Route::get('/UnregisterSip/{extension_number}', [ExtensionController::class, 'extensionUnregisterFromOpenSips']);
	/********  Webphone API   *********/
});
Route::post('/verifyEmail', [UserController::class, 'verifyEmailIdByOTP'])->name('verifyEmailIdByOTP');
Route::post('/verifyMobile', [UserController::class, 'verifyMobileByOTP']);
//Route::get('/user/{id?}', [UserController::class, 'getUser']);
Route::post('/resend-otp', [UserController::class, 'resendOtp'])->name('resendOtp');
Route::post('/resend-sms-otp', [UserController::class, 'resendSMSOtp']);
Route::get('/countries', [CountryController::class, 'getCountries']);
Route::get('/states/{country_id?}', [StateController::class, 'getStates']);
Route::get('/getTimeZone/{country_id}', [CountryController::class, 'getCountriesTimeZones']);
Route::post('/forgot-password-otp', [PasswordResetTokensController::class, 'sendForgotPasswordOTP']);
Route::post('/password-reset/{otp}', [PasswordResetTokensController::class, 'reset']);

# MainPlans
Route::group(['prefix' => 'plan'], function () {
	Route::get('/active', [MainPlansController::class, 'getAllActivePlans']);
	Route::get('/{id?}', [MainPlansController::class, 'getAllPlans']);
});

Route::middleware(['auth:sanctum', 'token.expiry', 'throttle:60,1', 'log.request.response'])->group(function () {

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
		Route::post('/add-to-wallet', [CompanyController::class, 'AddbalanceForCompanyBySuperAdmin']);
		Route::get('/resellersCompany/{reseller_id}/{plan_id}', [CompanyController::class, 'getAllActiveCompanyOfResellersByPlanId']);
		Route::post('/reseller-add-to-wallet', [RechargeHistoryController::class, 'AddbalanceForResellerBySuperAdmin']);
		Route::post('/changeServer', [CompanyController::class, 'changeCompanyRegisteredServer']);
	});

	Route::group(['prefix' => 'user'], function () {
		Route::get('/getallreseller', [UserController::class, 'getAllResellerlist']);
		Route::get('reseller/active', [UserController::class, 'getActiveResellerUsers']);
		Route::get('reseller/{id?}', [UserController::class, 'getAllResellerUsers']);
		//Route::get('/balance', [CompanyController::class, 'getBalance']);
		Route::post('/', [UserController::class, 'createUser']);
		Route::get('/active', [UserController::class, 'getAllActiveUsers']);
		Route::get('/{id?}', [UserController::class, 'getUser']);
		Route::patch('/changeStatus/{id}', [UserController::class, 'changeStatus']);
		Route::put('/{id}', [UserController::class, 'updateUser']);
		Route::get('getCompanyUserslist', [UserController::class, 'getCompanyUserslist']);
		Route::post('/liveCallHangUp', [UserController::class, 'liveCallHangUp']);
	});

	Route::post('/logout', [UserController::class, 'logout']);

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
		Route::get('/{price_for}', [MainPriceController::class, 'getPriceList']);
		Route::get('/{id?}', [MainPriceController::class, 'getAllPriceList']);
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
		Route::post('/tfn-date-change', [TfnController::class, 'tfnexpDateUpdate']);
		Route::get('/getAllTfnOrByCompany', [TfnController::class, 'getAllTfnOrByCompany']);
		Route::get('/csv-list', [TfnController::class, 'getALLCsvUploadedList']);
		Route::post('/replace-tfn', [TfnController::class, 'ReplaceTfnNumber']);
		Route::get('/get-tfn-by-company/{company_id?}', [TfnController::class, 'getALLTfnNumberofCompany']);
		Route::get('/get-tfn-by-country/{country_id?}', [TfnController::class, 'getAllTfnNumberFreebyCountry']);
		Route::post('/call-screen-action', [TfnController::class, 'callScreenAction']);
		Route::get('/removed-tfn', [TfnController::class, 'getALLRemovedTfn']);
		Route::post('/renew-tfn-number', [TfnController::class, 'assignTfnMainRenew']);
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
		Route::post('/extensionexpDateUpdate', [ExtensionController::class, 'extensionexpDateUpdate']);
		Route::get('/getextensionfordownload', [ExtensionController::class, 'getAllExtensionForsuperadmintodownloadincsv']);
		Route::post('/multiEdit', [ExtensionController::class, 'updateExtensionsDetails']);
		Route::get('/getSipRegistrationList', [ExtensionController::class, 'getSipRegistrationList'])->name('getSipRegistrationList');
		Route::get('/quickView/{company_id}', [ExtensionController::class, 'getExtensionsNumberPassword']);
		Route::post('/', [ExtensionController::class, 'createExtensions']);
		Route::patch('/changeStatus/{id}', [ExtensionController::class, 'changeExtensionStatus']);
		Route::post('/generate', [ExtensionController::class, 'generateExtensions']);
		Route::get('/generatePassword', [ExtensionController::class, 'generateStrongPassword']);
		Route::get('/{id?}', [ExtensionController::class, 'getAllExtensions']);
		Route::put('/{id}', [ExtensionController::class, 'updateExtension']);
		Route::get('/getByCountryAndCompany/{country_id}/{company_id}', [ExtensionController::class, 'getExtensionsByCountryIdAndCompanyId']);
		//Route::post('/adToCart', [ExtensionController::class, 'extensionAddToCArt']);	
		Route::delete('/{id}', [ExtensionController::class, 'deleteExtension']);
		Route::delete('/', [ExtensionController::class, 'multipleDeleteExtension']);
		Route::get('/getByCompany/{company_id}', [ExtensionController::class, 'getExtensionsByCompany']);
		Route::get('/getForBarging/{company_id?}', [ExtensionController::class, 'getExtensionsForBarging']);
		Route::post('/renewExtensions', [ExtensionController::class, 'renewExtensions']);
		Route::get('/extensionlog', [ExtensionController::class, 'getAllExtensionsLog']);
		Route::get('/extensionUnregisterFromOpenSips/{extension_number}', [ExtensionController::class, 'extensionUnregisterFromOpenSips']);
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
		Route::get('/getCompanyPermission', [PermissionController::class, 'getCompanyPermissionWithGroup']);
	});

	# Ring Group Manage
	Route::group(['prefix' => 'ring-group'], function () {
		Route::post('/', [RingGroupController::class, 'addRingGroup']);
		Route::get('/active', [RingGroupController::class, 'getAllActiveRingGroup']);
		Route::get('/getAllOrByCompany', [RingGroupController::class, 'getAllOrByCompany']);
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
		Route::get('/reseller-recharge-history', [RechargeHistoryController::class, 'getALLresellerRechargeHistory']);
		Route::get('/recharge-history', [InvoiceController::class, 'getRechargehistoryInvoiceData']);
		Route::get('/getall', [InvoiceController::class, 'getAllInvoiceData']);
		Route::get('/{id}', [InvoiceController::class, 'getInvoiceData']);
		Route::post('/', [InvoiceController::class, 'createInvoice']);
	});

	# Payments Manage.
	Route::group(['prefix' => 'payment'], function () {
		Route::post('/pay', [PaymentController::class, 'PayNow']);
		Route::post('/Striperefund', [PaymentController::class, 'RefundStripePayment']);
		Route::post('/paywithwallet', [PaymentController::class, 'PaywithWallet']);
		Route::post('/reselleraddToWallet', [PaymentController::class, 'ResellerAddToWallet']);
		Route::post('/addbalance', [PaymentController::class, 'addToWallet']);
		Route::post('/nowpayment/create', [NowPaymentsController::class, 'createPayment']);
		Route::get('/nowpayment/status/{paymentId}', [NowPaymentsController::class, 'checkPaymentStatus']);
		Route::post('/nowpayment/add-to-wallet', [NowPaymentsController::class, 'nowPaymentsAddToWallet']);
		Route::get('/nowpayment/add-to-wallet/status/{paymentId}', [NowPaymentsController::class, 'nowPaymentsWalletcheckPaymentsStatus']);
		Route::post('/nowpayment/reseller-add-to-wallet', [NowPaymentsController::class, 'ResellernowPaymentsaddToWallet']);
		Route::get('/nowpayment/reseller-add-to-wallet/status/{paymentId}', [NowPaymentsController::class, 'ReselletnowPaymentsWalletcheckPaymentsStatus']);
	});


	# Queue Manage
	Route::group(['prefix' => 'queue'], function () {
		Route::post('/', [QueueController::class, 'addQueue']);
		Route::get('/active', [QueueController::class, 'getAllActiveQueue']);
		Route::get('/getAllOrByCompany', [QueueController::class, 'getAllOrByCompany']);
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
		Route::get('/getAllOrByCompany', [ConferenceController::class, 'getAllOrByCompany']);
		Route::get('/getByCountryAndCompany/{country_id}/{company_id}', [ConferenceController::class, 'getAllActiveByCompanyAndCountry']);
		Route::get('/{id?}', [ConferenceController::class, 'getAllConference']);
		Route::put('/{id}', [ConferenceController::class, 'updateConference']);
		Route::patch('/changeStatus/{id}', [ConferenceController::class, 'changeConferenceStatus']);
		Route::delete('/{id}', [ConferenceController::class, 'deleteConference']);
	});

	Route::group(['prefix' => 'chnage-password'], function () {
		Route::post('/', [UserController::class, 'changePasswordBySuperadmin']);
		Route::post('/self', [UserController::class, 'passwordChange']);
	});

	#IVR Media Manage
	Route::group(['prefix' => 'ivr-media'], function () {
		Route::post('/', [IvrMediaController::class, 'addIvrMedia']);
		Route::patch('/changeStatus/{id}', [IvrMediaController::class, 'changeIVRMediaStatus']);
		Route::put('/{id}', [IvrMediaController::class, 'updateIvrMedia']);
		Route::get('/active/{company_id}', [IvrMediaController::class, 'getAllActiveIvrMediaList']);
		Route::get('/{id?}', [IvrMediaController::class, 'getAllIvrMedia']);
		Route::get('/getByCompany/{company_id}', [IvrMediaController::class, 'getAllIvrMediaByCompany']);
		Route::delete('/{id}', [IvrMediaController::class, 'deleteIvrMedia']);
	});

	#IVR Manage
	Route::group(['prefix' => 'ivr'], function () {
		Route::post('/', [IvrController::class, 'addIvr']);
		Route::patch('/changeStatus/{id}', [IvrController::class, 'changeIVRStatus']);
		Route::put('/{id}', [IvrController::class, 'updateIvr']);
		Route::get('/active', [IvrController::class, 'getAllActiveIvrList']);
		//Route::get('/dd', [IvrController::class, 'getDirectDestination']);
		Route::get('/{id?}', [IvrController::class, 'getAllIvrList']);
		Route::get('/getByCountryAndCompany/{country_id}/{company_id}', [IvrController::class, 'getIvrListByCompanyAndCountry']);
		Route::delete('/{id}', [IvrController::class, 'deleteIvr']);
	});

	#IVR Options Manage
	Route::group(['prefix' => 'ivr-options'], function () {
		Route::post('/', [IvrOptionController::class, 'addIvrOption']);
		Route::put('/{id}', [IvrOptionController::class, 'editIvrOption']);
		Route::delete('/{id}', [IvrOptionController::class, 'removeIvrOption']);
		Route::get('/{ivr_id}', [IvrOptionController::class, 'getIvrOptions']);
	});

	#CDR 
	Route::group(['prefix' => 'cdr-report'], function () {
		Route::get('/getCdrFilterList', [CdrController::class, 'getCdrFilterList']);
		Route::get('/{company_id?}', [CdrController::class, 'getAllCdrList']);

		//Route::get('/inboundCdr/{company_id?}', [CdrController::class, 'getAllCdrList']);
		Route::get('/outboundCdr/{company_id?}', [CdrController::class, 'getAllCallList']);
	});

	#One Go User
	Route::group(['prefix' => 'one-go-user'], function () {
		Route::post('/', [CompanyController::class, 'registrationByAdminOrReseller']);
		Route::post('/reserveTFN', [OneGoUserController::class, 'reserveTFN']);
		Route::post('/createExtensions', [OneGoUserController::class, 'createExtensions']);
		Route::post('/addRingGroup', [OneGoUserController::class, 'addRingGroup']);
		Route::get('/', [OneGoUserController::class, 'getOneGoUser']);
		Route::post('/createInvoice', [OneGoUserController::class, 'createInvoice']);
		Route::post('/oneGoPayment', [OneGoUserController::class, 'createOneGoPayment']);
		Route::delete('/{id}', [OneGoUserController::class, 'deleteOneGoUser']);
	});

	Route::group(['prefix' => 'reseller-commission'], function () {
		Route::get('/get-invoice-items/{id?}', [ResellerCommissionController::class, 'numberofItemsforResellerCommission']);
		Route::get('/commission', [ResellerCommissionController::class, 'getCommissionExtensionOrTfnForReseller']);
		Route::get('/ofcalls', [ResellerCommissionController::class, 'getCommissionOfCallsForReseller']);
	});

	# Reseller Call  Commission
	Route::group(['prefix' => 'reseller-call-commission'], function () {
		Route::post('/', [ResellerCallCommissionController::class, 'addResellerCallCommission']);
		Route::get('/{id?}', [ResellerCallCommissionController::class, 'getAllResellerCallCommission']);
		Route::patch('/changeStatus/{id}', [ResellerCallCommissionController::class, 'changeResellerCallCommissionStatus']);
		Route::put('/{id}', [ResellerCallCommissionController::class, 'updateResellerCallCommission']);
		Route::delete('/{id}', [ResellerCallCommissionController::class, 'deleteResellerCallCommission']);
	});

	# Reseller Payment History
	Route::group(['prefix' => 'reseller-payment-history'], function () {
		Route::get('/', [ResellerPaymentHistoryController::class, 'getAllResellerPaymentHistory']);
	});

	# Voice Mail Manage 
	Route::group(['prefix' => 'voice-mail'], function () {
		Route::post('/', [VoiceMailController::class, 'addVoiceMail']);
		Route::get('/getAllOrByCompany', [VoiceMailController::class, 'getAllOrByCompany']);
		Route::get('/getByCompany/{company_id}', [VoiceMailController::class, 'getAllVoiceMailByCompany']);
		Route::get('/get', [VoiceMailController::class, 'getVoiceMail']);
		Route::get('/{id?}', [VoiceMailController::class, 'getAllVoiceMail']);
		Route::put('/{id}', [VoiceMailController::class, 'updateVoiceMail']);
		Route::delete('/{id}', [VoiceMailController::class, 'deleteVoiceMail']);
	});

	# TFN authentication Manage 
	Route::group(['prefix' => 'tfn-auth'], function () {
		Route::post('/', [TfnController::class, 'setTfnAuthentication']);
		Route::get('/{tfn_id}', [TfnController::class, 'getTfnAuthentication']);
	});

	# Time Group Manage 
	Route::group(['prefix' => 'time-group'], function () {
		Route::post('/', [TimeGroupController::class, 'addTimeGroup']);
		Route::get('/getByCompany/{company_id}', [TimeGroupController::class, 'getTimeGroupByCompany']);
		Route::get('/{id?}', [TimeGroupController::class, 'getAllTimeGroup']);
		Route::put('/{id}', [TimeGroupController::class, 'updateTimeGroup']);
		Route::delete('/{id}', [TimeGroupController::class, 'deleteTimeGroup']);
	});

	# Time Conditions Manage 
	Route::group(['prefix' => 'time-condition'], function () {
		Route::post('/', [TimeConditionController::class, 'addTimeCondition']);
		Route::get('/active', [TimeConditionController::class, 'getAllActiveTimeCondition']);
		//Route::get('/getByCompany/{company_id}/{country_id}', [TimeConditionController::class, 'getTimeConditionByCompanyAndCountry']);
		Route::get('/{id?}', [TimeConditionController::class, 'getAllTimeCondition']);
		Route::patch('/changeStatus/{id}', [TimeConditionController::class, 'changeTimeConditionStatus']);
		Route::put('/{id}', [TimeConditionController::class, 'updateTimeCondition']);
		Route::delete('/{id}', [TimeConditionController::class, 'deleteTimeCondition']);
	});

	# Notifications 
	Route::group(['prefix' => 'notifications'], function () {
		Route::get('/', [NotificationController::class, 'getAllNotifications']);
		Route::put('/{id}', [NotificationController::class, 'updateMarkAsRead']);
		Route::post('/multiMarkAsRead', [NotificationController::class, 'updateMultipleMarkAsRead']);
	});
	# Error Messages 
	Route::group(['prefix' => 'error-message'], function () {
		Route::get('/hangup-cause', [ErrorMessageController::class, 'getAllHangupCauseList']);
	});

	# Dashboard Manage
	Route::group(['prefix' => 'dashboard'], function () {
		Route::get('/getResellerandMaincompany', [DashboardController::class, 'getAllcompanyListforSuperAdminDashboard']);
		Route::get('/getResellerandUser', [DashboardController::class, 'getAllResellerUserCountforSuperAdminDashboard']);
		Route::get('/getTfnCount', [DashboardController::class, 'getAllTfnforSuperAdminDashboard']);
		Route::get('/getExtensionCount', [DashboardController::class, 'getAllExtensionforSuperAdminDashboard']);
		Route::get('/get-invoice-tfn-extension-price', [DashboardController::class, 'getALLTfnExtensionPriceforlastSevendays']);
		Route::get('/cdrReports/{dayCount}', [DashboardController::class, 'getCdrReports']);
		Route::get('/companyUserCount', [DashboardController::class, 'getCompanyUserCount']);

		/// Reseller Dashboard Section 
		Route::get('/get-reseller-company', [DashboardController::class, 'getAllcompanyListforResellerDashboard']);
		Route::get('/get-reseller-calls-commissions', [DashboardController::class, 'getAllcompanyCallCommissionListforResellerDashboard']);
		Route::get('/get-reseller-items-commissions', [DashboardController::class, 'getAllcompanyItemsCommissionListforResellerDashboard']);
		Route::get('/get-reseller-graph-commissions/{options}', [DashboardController::class, 'getResellerGraphCommissionDashboard']);
	});
});


Route::get('/route-cache', function () {
	$exitCode = Artisan::call('route:cache');
	$exitCode = Artisan::call('route:clear');
	return 'Routes cache cleared';
});
Route::get('/config-cache', function () {
	$exitCode = Artisan::call('config:cache');
	return 'Config cache cleared';
});
Route::get('/clear-cache', function () {
	$exitCode = Artisan::call('cache:clear');
	return 'Application cache cleared';
});
Route::get('/optimize', function () {
	$exitCode = Artisan::call('optimize');
	return 'Application optimize';
});
/*
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
*/
