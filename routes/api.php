<?php

use App\Http\Controllers\Api\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\AuthController as AppAuthController;
use App\Http\Controllers\Api\Admin\CommonController as AdminCommonController;
use App\Http\Controllers\Api\Admin\ConfigController;
use App\Http\Controllers\Api\Admin\FinancialController as AdminFinancialController;
use App\Http\Controllers\Api\Admin\FundsController;
use App\Http\Controllers\Api\Admin\IeoController;
use App\Http\Controllers\Api\Admin\MarketController as AppMarketController;
use App\Http\Controllers\Api\Admin\MenuController;
use App\Http\Controllers\Api\Admin\OtcController;
use App\Http\Controllers\Api\App\OtcController as AppOtcController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\UserController as AppUserController;
use App\Http\Controllers\Api\App\ActivityController;
use App\Http\Controllers\Api\App\AuthController;
use App\Http\Controllers\Api\App\CommonController;
use App\Http\Controllers\Api\App\FinancialController;
use App\Http\Controllers\Api\App\FuturesController;
use App\Http\Controllers\Api\App\IeoController as AppIeoController;
use App\Http\Controllers\Api\App\MarketController;
use App\Http\Controllers\Api\App\PledgeController;
use App\Http\Controllers\Api\Admin\PledgeController as AdminPledgeController;
use App\Http\Controllers\Api\App\SpotController;
use App\Http\Controllers\Api\App\ThirdpartyController;
use App\Http\Controllers\Api\App\UserController;
use App\Http\Controllers\Api\App\WalletController;
use App\Http\Middleware\Rbac;
use Illuminate\Support\Facades\Route;

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


Route::any('/thrid_party/wallet/callback', [ThirdpartyController::class, 'udunCallback']);
Route::any('/third_party/engine/callback', [CommonController::class, 'engineCallback']);

Route::get('/app/account/area_code', [CommonController::class, 'phoneCode']);
// Route::post('/app/signin',[AuthController::class,'signin'])->middleware('validate.turnstile');
// Route::post('/app/signup',[AuthController::class,'signup'])->middleware('validate.turnstile');
// Route::post('/app/account/forget_password',[AuthController::class,'forgetSignInPassword'])->middleware('validate.turnstile');

Route::post('/app/signin', [AuthController::class, 'signin']);
Route::post('/app/signup', [AuthController::class, 'signup']);
Route::post('/app/account/forget_password', [AuthController::class, 'forgetSignInPassword']);
Route::post('/app/captcha', [CommonController::class, 'getCatpchaUnSignin']);
Route::get('/app/images/url', [CommonController::class, 'imagesUrl']);
Route::get('/app/country', [CommonController::class, 'countryList']);
Route::post('/app/update', [CommonController::class, 'updateCheck']);
Route::get('/app/protocol/aboutme', [CommonController::class, 'docAboutMe']);
Route::get('/app/protocol/terms', [CommonController::class, 'docTermsAndConditions']);
Route::get('/app/protocol/privacy', [CommonController::class, 'docPrivacyPolicy']);
Route::get('/config', [CommonController::class, 'single']);


Route::prefix('app')->middleware('auth:sanctum')->group(function () {
    Route::post('/images/upload', [CommonController::class, 'uploadImagePath']);
    Route::get('banners', [CommonController::class, 'banners']);
    Route::get('notices', [CommonController::class, 'notices']);
    Route::get('notice/detail', [CommonController::class, 'noticeDetail']);
    Route::get('leverages', [CommonController::class, 'leverages']);
    Route::get('/config', [CommonController::class, 'single']);

    Route::get('/announcement', [CommonController::class, 'announcement']);
    Route::post('/announcement/read', [CommonController::class, 'readTagAnnouncement']);

    // Route::get('/protocol/aboutme',[CommonController::class,'docAboutMe']);
    // Route::get('/protocol/terms',[CommonController::class,'docTermsAndConditions']);
    // Route::get('/protocol/privacy',[CommonController::class,'docPrivacyPolicy']);

    Route::get('/news', [CommonController::class, 'news']);
    Route::get('/news/detail', [CommonController::class, 'newsDetail']);

    Route::post('/account/captcha', [CommonController::class, 'getCatpcha']);

    Route::prefix('financial')->controller(FinancialController::class)->group(function () {
        Route::get('/products', 'products');
        Route::get('/product/detail', 'productDetail');
        Route::get('/orders', 'orders');
        Route::post('/order/buy', 'buy');
        Route::post('/order/redeem', 'redeem');
    });

    Route::prefix('ieo')->controller(AppIeoController::class)->group(function () {
        Route::get('/', 'list');
        Route::get('/orders', 'orders');
        Route::post('/order/buy', 'joinIn');
    });

    Route::prefix('active')->controller(ActivityController::class)->group(function () {
        Route::post('/support', 'submitSupport');
        Route::post('/dividend', 'getDividend');
        Route::get('/dividend/history', 'hasDividend');

        Route::get('/mentos', 'mentos');
        Route::post('/mento', 'mentoVote');

    });

    Route::prefix('account')->controller(UserController::class)->group(function () {
        Route::get('/profile', 'profile');
        Route::post('/punch', 'punch');
        Route::post('/setting/profile', 'setting');
        Route::post('/setting/password', 'changePassword');
        Route::post('/setting/trade_password', 'changeTradePassword');
        Route::post('/setting/email', 'changeEmail');
        Route::post('/setting/phone', 'changePhone');

        Route::post('/setting/identity', 'submitIdentity');
        Route::get('/setting/identity/status', 'showIdentityProcess');

        Route::get('inbox', 'inbox');
        Route::get('inbox/detail', 'msgDetail');
    });

    Route::prefix('market')->controller(MarketController::class)->group(function () {
        Route::get('coins', 'coins');
        Route::get('recommend', 'recommend');
        Route::get('spot', 'spot');
        Route::get('futures', 'futures');
        Route::get('/collections', 'myCollection');
        Route::post('/collection', 'collection');

        Route::post('/symbol/line', 'allSymbolSimpleLine');
        Route::get('/symbol/kline', 'klineHistory');
        Route::get('/symbol', 'symbol');

    });

    Route::prefix('wallet')->controller(WalletController::class)->group(function () {
        Route::get('spot', 'spotWallet');
        Route::get('spot/flow', 'spotWalletFlow');
        Route::get('spot/selector', 'spotWalletSelector');
        Route::get('futures', 'futuresWallet');
        Route::get('futures/flow', 'futuresWalletFlow');
        Route::post('transfer', 'transfer');
        Route::get('deposit/coins', 'supportDepositCoins');
        Route::get('withdraw/coins', 'supportWithdrawCoins');
        Route::post('deposit', 'deposit');
        Route::get('deposit/history', 'depositList');
        Route::post('withdraw', 'withdraw');
        Route::get('withdraw/history', 'withdrawList');
        Route::get('summary', 'summary');
        Route::get('/transfer/avaiable', 'allowTransferSpotMoney');
    });

    Route::prefix('order/spot')->controller(SpotController::class)->group(function () {
        Route::get('/', 'orders');
        Route::post('/', 'create');
        Route::post('/cancel', 'cancel');
        Route::post('/instant/exchange', 'instant');   //闪兑交易
        Route::get('/instant/exchange', 'instantLogs');//闪兑交易记录
    });

    Route::prefix('order/futures')->controller(FuturesController::class)->group(function () {
        Route::get('/', 'orders');
        Route::post('/', 'create');
        Route::post('/close', 'close');
        Route::post('/cancel', 'cancel');

        Route::post('/modify/position', 'averageDown');
        Route::post('/modify/sltp', 'modifySLTP');
    });

    Route::prefix('order/pledge')->controller(PledgeController::class)->group(function () {
        Route::get('/', 'config');
        Route::post('/apply', 'apply');
        Route::get('/orders', 'orders');
    });

    Route::prefix('otc')->controller(AppOtcController::class)->group(function () {
        Route::get('/products', 'products');
        Route::post('/trade', 'trade');
        Route::get('/orders', 'orders');
    });

});

//Route::post('aaa', [\App\Http\Controllers\Api\Admin\MarketController::class, 'createNewBotTask']);
//Route::post('bbb', [\App\Http\Controllers\Api\Admin\MarketController::class, 'bbb']);
//Route::get('ccc', [\App\Http\Controllers\Api\Admin\MarketController::class, 'ccc']);
//Route::post('ddd', [\App\Http\Controllers\Api\Admin\MarketController::class, 'createKline']);

// Admin API Resource
Route::post('/admin/login', [AppAuthController::class, 'login']);
Route::get('admin/menu/selector', [MenuController::class, 'selector']);//菜单选择框
Route::get('admin/role/selector', [RoleController::class, 'selector']);//角色选择框


Route::prefix('admin')->middleware('auth:admin')->group(function () {
    Route::post('/images/upload', [AdminCommonController::class, 'uploadImagePath']);

    Route::prefix('ieo')->controller(IeoController::class)->group(function () {
        Route::get('/', 'list');
        Route::post('/add', 'add');
        Route::post('/edit', 'edit');
        Route::get('/orders', 'orders');
        Route::post('/order/edit', 'editOrders');
        Route::post('/order/subscibed', 'subscribed');
        Route::post('/order/public', 'publicResult');
        Route::post('/order/add', 'addOrder');
    });

    Route::prefix('financial')->controller(AdminFinancialController::class)->group(function () {
        Route::get('/products', 'products');
        Route::post('/create', 'create');
        Route::post('/edit', 'edit');
        Route::get('/orders', 'orders');
    });

    Route::prefix('account')->controller(AdminController::class)->group(function () {
        Route::get('/', 'list');
        Route::post('/create', 'create');
        Route::post('/logout', 'logout');
        Route::post('/setting', 'setting');

        Route::get('/roleOptions', 'roleOptions');
        Route::get('/salesmanOptions', 'salesmanOptions');
        Route::get('/salesmanLeaderOptions', 'salesmanLeaderOptions');
        Route::post('/bindParent', 'bindParent');
        Route::post('/cancelBindParent', 'cancelBindParent');

        Route::get('show', 'list');                        //列表
        Route::post('store', 'store');                     //新增
        Route::post('destroy/{id}', 'destroy');            //删除
        Route::post('update/{id}', 'update');              //修改
        Route::get('detail/{id}', 'detail');               //详细信息
        Route::post('resetPwd/{id}', 'resetPwd');          //重置密码
        Route::post('freeze/{id}', 'freeze');              //冻结账号
        Route::post('assignRole/{id}/{rid}', 'assignRole');//分配角色
    });

    Route::prefix('menu')->controller(MenuController::class)->group(function () {
        Route::get('index', 'index');          //个人展示菜单
        Route::get('show', 'show');            //管理列表
        Route::post('store', 'store');         //新增
        Route::post('destroy/{id}', 'destroy');//删除
        Route::post('update/{id}', 'update');  //修改
        Route::get('detail/{id}', 'detail');   //详细信息
    });

    Route::prefix('role')->controller(RoleController::class)->group(function () {
        Route::get('show', 'show');                    //列表
        Route::post('store', 'store');                 //新增
        Route::post('destroy/{id}', 'destroy');        //删除
        Route::post('update/{id}', 'update');          //修改
        Route::get('detail/{id}', 'detail');           //详细信息
        Route::post('assignMenus/{id}', 'assignMenus');//分配菜单
    });

    Route::prefix('config')->controller(ConfigController::class)->group(function () {
        Route::get('/banners', 'banners');
        Route::post('/banner', 'modifyBanners');
        Route::post('/banner/delete', 'deleteBanner');

        Route::post('/protocol', 'setProtocols');
        Route::get('/protocol', 'protocols');
        Route::get('/notices', 'notices');
        Route::post('/notice', 'modifyNotices');
        Route::post('/notice/delete', 'deleteNotice');

        Route::get('/announcements', 'announcements');
        Route::post('/announcement/modify', 'modifyAnnouncements');

        Route::get('/', [ConfigController::class, 'configs']);
        Route::post('/modify', [ConfigController::class, 'modifyConfig']);
    });

    Route::prefix('market')->controller(AppMarketController::class)->group(function () {
        Route::get('coins', 'coins');
        Route::get('symbols', 'symbols');
        Route::get('symbol/simple', 'simpleSymbols');

        Route::post('/symbol', 'modifySymbols');

        Route::get('/symbol/spot', 'spotSymbols');
        Route::post('/symbol/spot', 'modifySpotSymbol');

        Route::get('/symbol/derivative', 'DerivativeSymbols');
        Route::post('/symbol/derivative', 'modifyDervativeSymbol');

        Route::get('/symbol/derivative/simple', 'simpleFutures');

        Route::get('/symbol/price', 'fakePrice');
        Route::get('/symbol/price/detail', 'getAirCoinPrice');
        Route::post('/symbol/price', 'setFakePrice');
        Route::post('/symbol/price/cancel', 'cancelFakePrice');

        Route::get('/bot/task/list', 'BotTaskList');
        Route::post('/bot/task/preview', 'previewKline');
        Route::post('/bot/task/switch-type', 'changeKlineType');
        Route::post('/bot/task/add', 'NewBotTask');
        Route::post('/bot/task/edit', 'changeFloat');
        Route::post('/bot/task/delete', 'DeleteBotTask');
        Route::post('/bot/task/cancel', 'CancelBotTask');

    });

    Route::prefix('funds')->controller(FundsController::class)->group(function () {
        Route::get('/deposit/list', 'depositList');
        Route::get('/withdraw/list', 'withdrawList');
        Route::post('/withdraw/audit', 'auditWithdraw');
        Route::get('/deposit/summary/today', 'summaryTodayDeposit');
        Route::get('/withdraw/summary/today', 'summaryTodayWithdraw');
    });

    Route::post('/user/wallet/transfer', [FundsController::class, 'transfer']);
    Route::prefix('user')->controller(AppUserController::class)->group(function () {
        Route::get('/', 'list');
        Route::get('/level', 'userLevels');
        Route::post('/setting', 'setting');
        Route::post('/bind', 'bindSalesman');//绑定用户所属的业务员
        Route::get('/wallet/spot', 'userSpotWallet');
        Route::get('/wallet/derivative', 'userDerivativeWallet');
        Route::get('/wallet/spot/flow', 'spotWalletFlow');
        Route::get('/wallet/derivative/flow', 'derivativeWalletFlow');
        Route::post('/wallet/spot/modify', 'modifySpotWallet');
        Route::post('/wallet/derivative/modify', 'modifyDerivativeWallet');

        Route::post('/remark', 'editRemark');
        Route::get('/identity', 'userIdentityList');
        Route::post('/identity/submit', 'submitIdentity');
        Route::post('/identity/audit', 'auditIdentity');
        Route::post('/create', 'create');

        Route::post('/identity/remove', 'removeUserIdentity');
        Route::post('/inbox/send', 'sendUserMsg');
    });

    Route::prefix('order')->controller(OrderController::class)->group(function () {
        Route::get('/spot', 'spotOrders');
        Route::get('/futures', 'futuresOrders');
        Route::post('/futures/close', 'closeFuturesOrder');
    });

    Route::prefix('activity')->controller(AdminActivityController::class)->group(function () {
        Route::get('/mentos', 'mentos');
        Route::post('/mento', 'newMento');
        Route::post('/mento/edit', 'editMento');
    });

    Route::prefix('pledge')->controller(AdminPledgeController::class)->group(function () {
        Route::get('/coins', 'coins');           //可选币种集合
        Route::get('/config/coin', 'coinConfig');//列表
        Route::get('/config/duration', 'durationConfig');

        Route::post('/config/coin/add', 'addCoinConfig');//新增
        Route::post('/config/duration/add', 'addDurationConfig');

        Route::post('/config/coin/drop', 'dropCoinConfig');//删除
        Route::post('/config/duration/drop', 'dropDurationConfig');

        Route::get('/orders', 'orders');     //订单列表
        Route::post('/audit', 'audit');      //审核
        Route::post('/settle', 'settle');    //人工执行结算
        Route::post('/rollback', 'rollback');//回撤订单
    });

    Route::prefix('otc')->controller(OtcController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/option', 'otcProductOption');
        Route::post('/create', 'create');
        Route::post('/update', 'update');
        Route::post('/delete', 'delete');

        Route::get('/orders', 'orders');
        Route::post('/audit', 'audit');
    });

});
