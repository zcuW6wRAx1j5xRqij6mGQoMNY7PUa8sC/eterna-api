<?php

namespace Internal\Wallet\Actions;

use App\Http\Resources\UserWalletFuturesFlowCollection;
use App\Models\User;
use App\Models\UserWalletFuturesFlow;
use Illuminate\Http\Request;

class WalletFuturesFlow {

    public function __invoke(Request $request,User $user)
    {
        $pageSize = $request->get('page_size',15);
        $flowType = $request->get('flow_type','');

        $flow = UserWalletFuturesFlow::query()->where('uid', $user->id);
        if ($flowType) {
            $flow->where('flow_type', $flowType);
        }
        return new UserWalletFuturesFlowCollection($flow->orderByDesc('created_at')->paginate($pageSize));
    }
}

