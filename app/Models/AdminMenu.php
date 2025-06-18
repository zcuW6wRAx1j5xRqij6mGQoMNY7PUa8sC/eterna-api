<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdminMenu extends Model
{
    use HasFactory;

    protected $table = 'admin_menu';

    protected $guarded = [];


    public function fetchAll($withPermission=true)
    {
        return $this->withQuery(function ($query){
            return $query->with('roles');
        })->treeAllNodes();
    }

    public static function getAllVisibleMenuByRole($roleId)
    {
        if($roleId == 1){
            return AdminMenu::select('id', 'parent_id', 'show_name','icon','url','position','open_link')
                ->where('visible', 1)
                ->orderBy('parent_id')
                ->orderBy('position')
                ->get();
        }
        return AdminMenu::select('admin_menu.id', 'admin_menu.parent_id', 'show_name','icon','url','position','open_link')
            ->join('admin_relation', 'admin_relation.menu_id', '=', 'admin_menu.id')
            ->where('admin_relation.role_id', $roleId)
            ->where('admin_menu.visible', 1)
            ->orderBy('parent_id')
            ->orderBy('position')
            ->get();
    }
}
