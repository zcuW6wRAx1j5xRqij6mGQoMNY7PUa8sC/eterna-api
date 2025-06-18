<?php

namespace Internal\Order\Actions;

use App\Models\UserDerivativelPosition;
use Illuminate\Http\Request;

class DerivativeOrders {

    public function __invoke(Request $request)
    {
        $symbolId = $request->get('symbol_id');
        $status = $request->get('status',null);


        $query = UserDerivativelPosition::query()->with(['symbol'])->where('uid', $request->user()->id);
        if ($status !== null) {
            $query->where('trade_status', $status);
        }
        if ($symbolId) {
            $query->where('symbol_id', $symbolId);
        }
        return $query->orderByDesc('created_at')->paginate($request->get('page_size',15),['*'],null, $request->get('page'));
    }
}

