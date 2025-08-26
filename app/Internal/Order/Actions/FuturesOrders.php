<?php

namespace Internal\Order\Actions;

use App\Http\Resources\UserOrderFuturesCollection;
use App\Http\Resources\UserOrderFuturesResource;
use App\Models\UserOrderFutures;
use Illuminate\Http\Request;

class FuturesOrders {

    public function __invoke(Request $request)
    {
        $symbol = $request->get('symbol','');
        $side = $request->get('side','');
        $futuresId = $request->get('futures_id','');
        $status = $request->get('status','');
        $tradeType = $request->get('trade_type','');
        $query = UserOrderFutures::query()->with(['symbol'])->where('uid', $request->user()->id);
        if ($side) {
            $query->where('side', $side);
        }
        if ($symbol) {
            $query->whereHas('symbol', function ($query) use ($symbol) {
                $query->where('symbol','like','%'.$symbol.'%');
            });
        }
        if ($futuresId) {
            $query->where('futures_id', $futuresId);
        }
        if ($tradeType) {
            $query->where('trade_type', $tradeType);
        }
        if ($status && $status != 'all') {
            $query->where('trade_status', $status);
        }
        $data = $query->orderByDesc('created_at')->get();
        return UserOrderFuturesResource::collection($data);
        // return new UserOrderFuturesCollection($query->orderByDesc('created_at')->paginate($request->get('page_size',15)));
    }
}
