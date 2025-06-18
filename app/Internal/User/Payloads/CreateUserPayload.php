<?php

namespace Internal\User\Payloads;

use App\Enums\CommonEnums;
use App\Exceptions\LogicException;
use Illuminate\Http\Request;

class CreateUserPayload {

    public string $accountType = '';

    public string $email = '';
    public string $phoneCode = '';
    public string $phone = '';
    public string $password = '';
    public string $inviteCode = '';
    public int $salesman = 0;
    public string $captcha = '';
    public string $cfToken = '';

    public bool $isAdmin = false;
    public string $ip = '';
    public string $device = '';


    public function __construct()
    {
    }

    public function parseFromRequest(Request $request)  {
        if (! $this->accountType) {
            $this->accountType = $request->get('account_type','');
        }
        $email = $request->get('email');
        if (is_null($email)) {
            $email = '';
        }
        $phoneCode = $request->get('phone_code');
        if (is_null($phoneCode)) {
            $phoneCode = '';
        }
        $phone = $request->get('phone');
        if (is_null($phone)) {
            $phone = '';
        }

        $this->email = $email;
        $this->phoneCode = $phoneCode;
        $this->phone = $phone;
        $this->inviteCode = $request->get('invite_code','');
        $this->password = $request->get('password');
        $this->ip = $request->ip();
        $this->captcha = $request->get('captcha_code','');
        //$this->cfToken = $request->get('cf_token','');
        $this->device = '';

        $this->check();
        return $this;
    }

    public function check() {
        //if (!$this->isAdmin) {
            //if (!$this->cfToken) {
            //    throw new LogicException(__('Incorrect submitted data'));
            //}
        //}
        if (!$this->accountType) {
            throw new LogicException(__('Incorrect submitted data'));
        }
        if ($this->accountType == CommonEnums::AccountTypeEmail) {
            if (!$this->email) {
                throw new LogicException(__('Incorrect submitted data'));
            }
        } else {
            if (!$this->phone ||!$this->phoneCode) {
                throw new LogicException(__('Incorrect submitted data'));
            }
        }
        if (!$this->isAdmin) {
            if(!$this->captcha){
                throw new LogicException(__('Incorrect submitted data'));
            }
            if (!$this->inviteCode) {
                throw new LogicException(__('Incorrect invite code'));
            }
        }
        if (!$this->password) {
            throw new LogicException(__('Incorrect submitted data'));
        }
        return true;
    }
}

