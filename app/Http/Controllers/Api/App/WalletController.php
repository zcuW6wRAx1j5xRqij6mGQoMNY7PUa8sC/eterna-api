<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\CommonEnums;
use App\Enums\SpotWalletFlowEnums;
use App\Enums\TransferEnums;
use App\Enums\WalletFuturesFlowEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\PlatformWalletResource;
use App\Http\Resources\UserDepositCollection;
use App\Http\Resources\UserWalletAddressResource;
use App\Http\Resources\UserWithdrawCollection;
use App\Models\PlatformWallet;
use App\Models\UserDeposit;
use App\Models\UserWalletAddress;
use App\Models\UserWithdraw;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Internal\Order\Actions\FetchAvaiableChangeMoney;
use Internal\Wallet\Actions\DerivativeWallet;
use Internal\Wallet\Actions\DerivativeWalletFlow;
use Internal\Wallet\Actions\FuturesWallet;
use Internal\Wallet\Actions\SpotWallet;
use Internal\Wallet\Actions\SpotWalletFlow;
use Internal\Wallet\Actions\SubmitDeposit;
use Internal\Wallet\Actions\SubmitWithdraw;
use Internal\Wallet\Actions\Summary;
use Internal\Wallet\Actions\Transfer;
use Internal\Wallet\Actions\WalletFuturesFlow;
use Internal\Wallet\Actions\WalletSpotFlowList;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/** @package App\Http\Controllers\Api\App */
class WalletController extends ApiController {

    public function summary(Request $request, Summary $summary) {
        return $this->ok($summary($request->user()));
    }

    /**
     * 现货钱包
     * @param Request $request
     * @param SpotWallet $spotWallet
     * @return JsonResponse
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function spotWallet(Request $request, SpotWallet $spotWallet) {
        return $this->ok($spotWallet($request->user()));
    }

    /**
     * 现货钱包选择器
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function spotWalletSelector(Request $request)
    {
        return $this->ok((new SpotWallet())->selector($request->user()));
    }

    /**
     * 合约钱包
     * @param Request $request
     * @param DerivativeWallet $derivativeWallet
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function futuresWallet(Request $request,FuturesWallet $futuresWallet) {
        return $this->ok($futuresWallet($request->user()));
    }

    /**
     * 现货钱包流水
     * @param Request $request
     * @param SpotWalletFlow $spotWalletFlow
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function spotWalletFlow(Request $request, WalletSpotFlowList $walletSpotFlowList) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'flow_type'=>['string', Rule::in(SpotWalletFlowEnums::Maps)],
            'coin_id'=>'numeric',
        ]);
        return $this->ok($walletSpotFlowList($request, $request->user()));
    }

    /**
     * 合约钱包流水
     * @param Request $request
     * @param DerivativeWalletFlow $derivativeWalletFlow
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function futuresWalletFlow(Request $request, WalletFuturesFlow $walletFuturesFlow) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'flow_type'=>['string', Rule::in(WalletFuturesFlowEnums::Maps)],
        ]);
        return $this->ok($walletFuturesFlow($request, $request->user()));

    }


    /**
     * 获取准许划转到现货的可用余额
     * @param Request $request
     * @param FetchAvaiableChangeMoney $fetchAvaiableChangeMoney
     * @return JsonResponse
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function allowTransferSpotMoney(Request $request, FetchAvaiableChangeMoney $fetchAvaiableChangeMoney) {
        $money = $fetchAvaiableChangeMoney($request->user());
        return $this->ok($money);
    }

    /**
     * 划转
     * @param Request $request
     * @param Transfer $transfer
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function transfer(Request $request, Transfer $transfer) {
        $request->validate([
            'to'=>['required', Rule::in([TransferEnums::WalletDerivative,TransferEnums::WalletSpot])],
            'amount'=>'required|string',
        ]);

        $request->user()->checkFundsLock();

        $transfer($request, $request->user());
        return $this->ok(true);
    }

    /**
     * 支持入金的货币
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function supportDepositCoins(Request $request) {
        $coins = UserWalletAddress::with(['platform'])->where('uid', $request->user()->id)->get();
        if ($coins->isEmpty()) {
            return $this->ok([]);
        }
        $coins = $coins->filter(function($item){
            return $item->platform && $item->platform->spot_deposit == CommonEnums::Yes;
        });
        return $this->ok(UserWalletAddressResource::collection(
            $coins
        ));
    }

    /**
     * 支持提现的接口
     * @param Request $request
     * @return void
     * @throws BindingResolutionException
     */
    public function supportWithdrawCoins(Request $request) {
        $data = PlatformWalletResource::collection(PlatformWallet::with(['coin'])->where('spot_withdraw',CommonEnums::Yes)->get());
        return $this->ok($data);
    }

    /**
     * 入金
     * @param Request $request
     * @param SubmitDeposit $submitDeposit
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function deposit(Request $request, SubmitDeposit $submitDeposit) {
        $request->validate([
            'amount'=>'required|string',
            'coin_id'=>'required|numeric',
        ]);
        //$submitDeposit($request);
        return $this->ok(true);
    }

    /**
     * 充值列表
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function depositList(Request $request) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
        ]);
        $query = UserDeposit::where('uid', $request->user()->id);
        $data = $query->orderByDesc('created_at')->paginate($request->get('page_size',15));
        return $this->ok(new UserDepositCollection($data));
    }

    /*
     * 提现
     * @param Request $request
     * @param SubmitWithdraw $submitWithdraw
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function withdraw(Request $request, SubmitWithdraw $submitWithdraw) {
        $request->validate([
            'coin_id'=>'required|numeric',
            'amount'=>'required|string',
            'wallet_address'=>'required|string',
            'child_name'=>'required|string',
            'trade_pwd'=>'required|string',
        ]);
        $tradePwd = $request->get('trade_pwd');

        $request->user()->checkFundsLock();

        $user = $request->user();
        if ( ! $user->trade_password) {
            throw new LogicException(__('Please set the transaction password first'), LogicException::NoSetTradePassword);
        }
        if ( ! Hash::check($tradePwd, $user->trade_password)) {
            throw new LogicException(__('Incorrect transaction password'));
        }
        return $this->ok($submitWithdraw($request));
    }

    /**
     * 申请提现列表
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function withdrawList(Request $request) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
        ]);
        $query = UserWithdraw::where('uid', $request->user()->id);
        return $this->ok(new UserWithdrawCollection($query->orderByDesc('created_at')->paginate($request->get('page_size',15))));
    }
}
