<?php

namespace App\Internal\Order\Actions;

use App\Enums\CommonEnums;
use App\Http\Resources\UserOrderFuturesCollection;
use App\Http\Resources\UserOrderPledgeCollection;
use App\Http\Resources\UserOrderPledgeResource;
use App\Models\Scopes\SalesmanScope;
use App\Models\UserOrderPledge;
use Illuminate\Http\Request;

class PledgeOrders {

    public function __invoke(Request $request)
    {
        $uid    = $request->user()->id;
        $status = $request->get('status','');

        $query  = UserOrderPledge::query()->with(['symbolCoin', 'user'])
            ->where('uid', $uid);
        if ($status) {
            $query->where('status', $status);
        }
        $query->withGlobalScope('salesman_scope', new SalesmanScope());
        $data = $query->orderByDesc('created_at')->paginate($request->get('page_size',CommonEnums::Paginate));
        // return new UserOrderPledgeCollection($data);
        return UserOrderPledgeResource::collection($data);
    }

    public function auditList(Request $request)
    {
        $uid        = $request->get('uid','');
        $status     = $request->get('status','');
        $coinId     = $request->get('coin_id',0);
        $query      = UserOrderPledge::query()->with(['symbolCoin','user']);

        if ($uid) {
            $query->where('uid', $uid);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($coinId) {
            $query->where('coin_id', $coinId);
        }
        $query->withGlobalScope('salesman_scope', new SalesmanScope());
        $data = $query->orderByDesc('created_at')->paginate($request->get('page_size',15));

        return listResp($data, function ($items) {
            $items['items'] = collect($items['items'])->map(function ($item) {
                $item->created_at = $item->status == 'hold' ? $item->start_at : $item->created_at;
                return $item;
            });
            return $items;
        });

        // return UserOrderPledgeResource::collection($data);
//         return new UserOrderFuturesCollection($query->orderByDesc('created_at')->paginate($request->get('page_size',15))));
    }
}
