<?php

namespace Internal\Common\Actions;

use App\Enums\CommonEnums;
use App\Models\PlatformBanner;
use Illuminate\Http\Request;

class Banners {

    public function __invoke(Request $request)
    {
        return PlatformBanner::where('status',CommonEnums::Yes)->get();
    }

}

