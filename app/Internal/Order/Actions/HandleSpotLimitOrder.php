<?php

namespace Internal\Order\Actions;

use App\Enums\OrderEnums;
use App\Events\UpdateFuturesOrder;
use App\Exceptions\LogicException;
use App\Models\UserOrderFutures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HandleSpotLimitOrder {

    public function __invoke(array $orders)
    {

    }
}
