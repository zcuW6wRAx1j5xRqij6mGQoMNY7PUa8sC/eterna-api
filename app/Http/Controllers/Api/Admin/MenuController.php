<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CommonEnums;
use App\Exceptions\InternalException;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Models\AdminMenu as Menu;
use App\Models\AdminRelation;
use App\Models\AdminRole as Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MenuController extends ApiController
{
    //个人可见菜单
    public function index(Request $request)
    {
        $roleId = $request->user()->role_id;
        $menus  = Menu::getAllVisibleMenuByRole($roleId);
        $menus  = buildNestedArray($menus);
        return $this->ok(['items'=>$menus]);
    }

    public function selector()
    {
        $menus = Menu::select('url', 'show_name', 'id', 'parent_id')->get();
        $menus  = buildNestedArray($menus);
        return $this->ok(['items'=>$menus]);
    }

    //列表
    public function show(Request $request)
    {
        $page       = $request->get('page',1);
        $name       = $request->get('name','');
        $category   = $request->get('category',1);
        $pageSize   = $request->get('page_size',CommonEnums::Paginate);
        $query      = Menu::query();
        if($name){
            $query->where('show_name', 'like', "%{$name}%");
        }
        if($category){
            $query->where('category', $category);
        }
        $data       = $query->orderBy('parent_id')
            ->orderBy('category')
            ->orderBy('position')
            ->orderBy('id')
            ->paginate($pageSize,['*'],null,$page);
        $data = listResp($data);

        $showNames  = Menu::pluck('show_name', 'id')->toArray();
        foreach ($data['items'] as $inx=>$item) {
            $data['items'][$inx]['parent_name'] = $item['parent_id']>0?$showNames[$item['parent_id']]:'';
        }
        return $this->ok($data);
    }

    //新增
    public function store(Request $request)
    {
        $rules      = [
            'parent_id'  => 'numeric',
            'show_name'  => 'required|string',
            'icon'       => 'nullable|string',
            'url'        => 'required|string',
            'open_link'  => 'numeric',
            'position'   => 'numeric',
            'desc'       => 'nullable|string',
            'status'     => 'numeric',
            'visible'    => 'numeric',
            'category'   => 'numeric',//1菜单、2按钮
        ];
        $input      = $request->only(...array_keys($rules));
        $validator  = Validator::make($input, $rules);
        if ($validator->fails()) {
            throw new LogicException($validator->errors()->first());
        }

        $menu = Menu::create($input);
        return $this->ok($menu);
    }

    //删除
    public function destroy(Request $request, $id)
    {
        $deleted = Menu::destroy($id);
        if (!$deleted){
            throw new InternalException(__("drop failure"));
        }
        // 移除已关联的菜单属性
        AdminRelation::where('menu_id', $id)->delete();
        Menu::where('parent_id', $id)->delete();
        return $this->ok('success');
    }

    //修改
    public function update(Request $request, $id)
    {
        $rules      = [
            'parent_id'  => 'numeric',
            'show_name'  => 'required|string',
            'icon'       => 'string',
            'url'        => 'required|string',
            'open_link'  => 'numeric',
            'position'   => 'numeric',
            'desc'       => 'string',
            'status'     => 'string',
            'visible'    => 'numeric',
            'category'   => 'numeric',
        ];
        $input      = $request->only(...array_keys($rules));
        $validator  = Validator::make($input, $rules);
        if ($validator->fails()) {
            throw new LogicException($validator->errors()->first());
        }

        $menu       = Menu::find($id);
        if(!$menu){
            throw new LogicException('Menu not exists.');
        }

        $data       = [];
        foreach ($input as $field=>$val) {
            if($val && $val != $menu->$field){
                $data[$field] = $val;
            }
        }
        $ok = $menu->update($data);

        return $this->ok(['status'=>$ok, 'new'=>$menu]);
    }

    //明细
    public function detail(Request $request, $id)
    {
        $menu = Menu::find($id);
        return $this->ok($menu);
    }

    //排序
    public function sort(Request $request, $id)
    {
        $rules      = [
            'id'        => 'required|numeric',
            'position'  => 'numeric',
        ];
        $input      = $request->only(...array_keys($rules));
        $validator  = Validator::make($input, $rules);
        if ($validator->fails()) {
            throw new LogicException($validator->errors()->first());
        }

        $menu       = Menu::find($id);
        if(!$menu){
            throw new LogicException('Menu not exists.');
        }

        $data       = [];
        foreach ($input as $field=>$val) {
            if($val && $val != $menu->$field){
                $data[$field] = $val;
            }
        }

        $ok = $menu->update($data);
        return $this->ok(['status'=>$ok, 'new'=>$menu]);
    }

}
