<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create New Admin User';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $user = AdminUser::where('username','root')->first();
        if ($user) {
            $user->password = Hash::make('123123123');
            $user->save();
        }
        $user = new AdminUser();
        $user->username = 'root';
        $user->nickname = 'root';
        $user->password = Hash::make('123123123');
        $user->save();

        return $this->info('done : default password : 123123123');
    }
}
