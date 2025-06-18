<?php

namespace Internal\Order\Actions;

use App\Http\Resources\UserOrderSpotCollection;
use App\Http\Resources\UserOrderSpotResource;
use App\Models\UserOrderSpot;
use Illuminate\Http\Request;

class SpotOrders {

    public function __invoke(Request $request)
    {
        $side = $request->get('side','');
        $spotId = $request->get('spot_id','');
        $status = $request->get('status','');
        $tradeType = $request->get('trade_type','');
        $query = UserOrderSpot::query()->with(['symbol'])->where('uid', $request->user()->id);
        if ($side) {
            $query->where('side', $side);
        }
        if ($spotId) {
            $query->where('spot_id', $spotId);
        }
        if ($tradeType) {
            $query->where('trade_type', $tradeType);
        }
        if ($status) {
            $query->where('trade_status', $status);
        }

        $data = $query->orderByDesc('created_at')->get();
        return UserOrderSpotResource::collection($data);
    }

}
