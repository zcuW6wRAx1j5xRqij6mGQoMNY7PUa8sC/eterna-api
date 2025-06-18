<?php

namespace Internal\User\Actions;

use App\Events\UserNewMsg;
use App\Models\UserInbox;

class NewUserMsg {

    public function __invoke(int $uid, string $subject, string $content)
    {
        $box = new UserInbox();
        $box->uid = $uid;
        $box->subject = $subject;
        $box->content = $content;
        $box->save();

        UserNewMsg::dispatch($box);
        return true;
    }
}

