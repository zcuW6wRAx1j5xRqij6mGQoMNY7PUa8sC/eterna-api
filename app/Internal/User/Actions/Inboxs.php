<?php

namespace Internal\User\Actions;

use App\Enums\CommonEnums;
use App\Models\UserInbox;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class Inboxs {

    public function __invoke(Request $request)
    {
        return UserInbox::select(['id','subject','is_read'])
            ->where('uid', $request->user()->id)
            ->orderBy('created_at')
            ->orderBy('is_read')
            ->paginate($request->get('page_size'),['*'],null, $request->get('page'));
    }

    /**
     * 信息详情
     * @param Request $request
     * @return mixed
     * @throws BadRequestException
     */
    public function detail(Request $request) {
        $id = $request->get('id');
        $msg = UserInbox::find($id);
        if (!$msg) {
            return [];
        }
        if ($msg->uid != $request->user()->id) {
            return [];
        }

        if ($msg->is_read == CommonEnums::No) {
            $msg->is_read = CommonEnums::Yes;
        }

        $msg->save();
        return $msg;
    }
}
