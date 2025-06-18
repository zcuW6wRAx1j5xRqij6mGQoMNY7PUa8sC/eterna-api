<?php

namespace Internal\Common\Actions;

use App\Enums\CommonEnums;
use App\Models\PlatformNotice;
use Illuminate\Http\Request;

class Notices {

    public function __invoke(Request $request)
    {
        return PlatformNotice::select(['id','subject','created_at'])->where('status',CommonEnums::Yes)->get();
    }

    public function detail(Request $request) {
        $data = PlatformNotice::find($request->get('id',0));
        if (!$data) {
            return [];
        }
        return $data;
    }

}
