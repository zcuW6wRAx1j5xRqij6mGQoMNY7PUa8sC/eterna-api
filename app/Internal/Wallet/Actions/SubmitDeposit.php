<?php

namespace Internal\Wallet\Actions;

use App\Events\SubmitDeposit as EventsSubmitDeposit;
use App\Exceptions\LogicException;
use App\Models\PlatformWallet;
use App\Models\UserDeposit;
use App\Models\UserWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// 废弃不用
/** @package Internal\Wallet\Actions */
class SubmitDeposit {

    public function __invoke(Request $request)
    {
        return DB::transaction(function() use($request){

            $amount = $request->get('amount',0);
            $amount = abs(intval($amount));
            $coinId = $request->get('coin_id');

            $coin = PlatformWallet::find($coinId);
            if (!$coin) {
                Log::error('failed to submit deposit : no found platform wallet',[
                    'uid'=>$request->user()->id,
                    'coin_id'=>$coinId,
                ]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            $wallet = UserWallet::where('uid', $request->user()->id)->first();
            if (!$wallet) {
                Log::error('failed to submit deposit : no found platform wallet',[
                    'uid'=>$request->user()->id,
                    'coin_id'=>$coinId,
                ]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }

            $key = $coin->key_name;
            $deposit = new UserDeposit();
            $deposit->coin_id = $coinId;
            $deposit->uid = $request->user()->id;
            $deposit->wallet_address = $wallet->$key;
            $deposit->amount = $amount;
            $deposit->save();

            EventsSubmitDeposit::dispatch($deposit);
            return true;
        });
    }
}

