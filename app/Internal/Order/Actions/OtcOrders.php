<?php

namespace App\Internal\Order\Actions;

use App\Enums\CommonEnums;
use App\Enums\OrderEnums;
use App\Models\OtcOrder;
use App\Models\Scopes\SalesmanScope;
use Illuminate\Http\Request;

class OtcOrders {

    public function __invoke(Request $request)
    {
        $uid    = $request->user()->id;
        $status = $request->get('status','');
        $query  = OtcOrder::select('id','uid','product_id','quantity','amount','price','status','created_at','payment_method', 'trade_type')
            ->with(['product'])->where('uid', $uid);
        if ($status) {
            $query->where('status', $status);
        }
        $data   = $query->orderByDesc('created_at')->paginate($request->get('page_size',CommonEnums::Paginate));
        return listResp($data);
    }

    public function auditList(Request $request)
    {
        $uid        = $request->get('uid','');
        $status     = $request->get('status','');
        $productId  = $request->get('product_id',0);
        $tradeType  = $request->get('trade_type',0);
        $query      = OtcOrder::query()->with(['product','user']);

        if ($uid) {
            $query->where('uid', $uid);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($productId) {
            $query->where('product_id', $productId);
        }
        if ($tradeType) {
            $query->where('trade_type', $tradeType);
        }
        $query->withGlobalScope('salesman_scope', new SalesmanScope());
        $data = $query->orderByDesc('created_at')->paginate($request->get('page_size',15));

        return listResp($data);
    }
}
