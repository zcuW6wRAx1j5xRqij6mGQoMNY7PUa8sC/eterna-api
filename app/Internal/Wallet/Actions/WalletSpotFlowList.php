<?php

namespace Internal\Wallet\Actions;

use App\Http\Resources\UserWalletSpotFlowCollection;
use App\Models\User;
use App\Models\UserWalletSpotFlow;
use Illuminate\Http\Request;

class WalletSpotFlowList {

    public function __invoke(Request $request, User $user)
    {
        $pageSize = $request->get('page_size',15);
        $flowType = $request->get('flow_type','');
        $coinId = $request->get('coin_id',null);

        $flow = UserWalletSpotFlow::with(['coin'])->where('uid', $user->id);
        if ($flowType) {
            $flow->where('flow_type', $flowType);
        }
        if ($coinId !== null) {
            $flow->where('coin_id', $coinId);
        }
        return new UserWalletSpotFlowCollection($flow->orderByDesc('created_at')->paginate($pageSize));
    }
}

