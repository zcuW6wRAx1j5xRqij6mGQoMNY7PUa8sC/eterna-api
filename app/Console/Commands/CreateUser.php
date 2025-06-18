<?php

namespace App\Console\Commands;

use App\Enums\CommonEnums;
use Illuminate\Console\Command;
use Internal\User\Actions\CreateUser as ActionsCreateUser;
use Internal\User\Payloads\CreateUserPayload;

class CreateUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create {email} {password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '创建新用户';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email      = $this->argument('email');
        $password   = $this->argument('password');

        $payload            = new CreateUserPayload;
        $payload->isAdmin   = true;
        $payload->salesman  = CommonEnums::CommandSalesman;
        $payload->email     = $email;
        $payload->password  = $password;
        $payload->ip        = '127.0.0.1';

        (new ActionsCreateUser)($payload);

        return $this->info('done');
    }
}
