<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class MenuController extends Controller
{
    public function getEntityUser(Request $request)
    {
        try {

            $user = getAuth($request)->user_id2;
            $entity_id = $request->entity_id ? $request->entity_id : '';
            $appl_id = $request->appl_id ?? 'MOBILE';
            $arrayDatas = [];

            $datas = DB::select(DB::raw("SELECT DISTINCT
                        sec_right.entity_id, 
                        sec_group.user_id2, 
	                    sec_entity.route_web,
                        sec_entity.description,
                        sec_entity.url_image,
                        sec_entity.color,
                        sec_entity.class
                    FROM
                        sec_right
                        INNER JOIN
                        sec_group
                        ON 
                            sec_right.appl_id = sec_group.appl_id AND
                            sec_right.group_id = sec_group.group_id AND 
                            sec_right.entity_id = sec_group.entity_id
                        INNER JOIN
                        sec_entity
                        ON 
                            sec_right.entity_id = sec_entity.entity_id
                        INNER JOIN
                        sec_appl
                        ON 
                            sec_right.appl_id = sec_appl.appl_id AND
                            sec_right.entity_id = sec_appl.entity_id
                    WHERE
                        sec_entity.entity_id LIKE '%$entity_id%' AND
                        sec_right.appl_id LIKE '%$appl_id%' AND
                        sec_entity.status = 1 AND
                        sec_group.active_flag = 'Y' AND
                        sec_entity.entity_id <> 'BCKI' AND
                        sec_group.user_id2 = '$user'
                    ORDER BY
                        sec_right.entity_id ASC")); 
            
            foreach ($datas as $data) {
                if ($data->route_web == null || $data->route_web == "") {
                    array_push($arrayDatas);
                } else {
                    array_push($arrayDatas, [
                        'entity_id' => $data->entity_id,
                        'user_id2' => $data->user_id2,
                        'route_web' => $data->route_web,
                        'description' => $data->description,
                        'url_image' => $data->url_image,
                        'color' => $data->color,
                        'class' => $data->class
                    ]);
                }
            }

            return $this->sendResponse($arrayDatas, 'Successfully get entity user');
            
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), '', 404);
        } 

    }

    public function getApplUser(Request $request)
    {
        try {

            $user = getAuth($request)->user_id2;
            $entity_id = $request->entity_id;

            $datas = DB::select(DB::raw("SELECT DISTINCT
                        sec_right.entity_id, 
                        sec_right.appl_id, 
                        sec_group.user_id2, 
                        sec_appl.note5,
                        sec_appl.link_web,
                        sec_appl.icon,
                        sec_appl.url_image,
                        sec_appl.note4
                    FROM
                        sec_right
                        INNER JOIN
                        sec_group
                        ON 
                            sec_right.appl_id = sec_group.appl_id AND
                            sec_right.group_id = sec_group.group_id AND 
                            sec_right.entity_id = sec_group.entity_id AND
                            sec_group.user_id2  = '$user'
                        INNER JOIN
                        dbo.sec_entity
                        ON sec_right.entity_id = sec_entity.entity_id
                        INNER JOIN
                        dbo.sec_appl
                        ON 
                            sec_right.appl_id = sec_appl.appl_id AND
                            sec_right.entity_id = sec_appl.entity_id
                    WHERE
                        sec_right.entity_id = '$entity_id' AND
                        sec_group.entity_id = '$entity_id' AND
                        sec_appl.entity_id = '$entity_id' AND
                        sec_entity.entity_id = '$entity_id' AND
                        sec_entity.status = 1 AND
                        sec_group.active_flag = 'Y' AND
                        sec_right.active_flag = 'Y' AND
                        sec_group.user_id2 = '$user' AND
                        sec_appl.critical_flag = 'V'
                    ORDER BY
                        sec_appl.note5 ASC")); 

            return $this->sendResponse($datas, 'Successfully get appl user');
            
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), '', 500);
        } 

    }

    public function getMenu(Request $request)
    {
        $user_id = getAuth($request)->user_id2;
        $appl_id = $request->appl_id ?? 'MOBILE';
        $entity_id = $request->entity_id;
        $search = $request->search;
        // dd($user_id);
        $datas = [];

        $sqlQuery = "SELECT DISTINCT
            sec_menu_header.entity_id,
            sec_right.appl_id,
            sec_right.group_id,
            --sec_right.menu_id,
            sec_menu_header.menu_header_id,
            sec_menu_header.menu_caption menu_header_caption,
            sec_menu_header.route_path menu_header_path,
            sec_menu_header.icon,
            sec_menu_header.seq_id
        FROM
            sec_right
            INNER JOIN sec_group ON (sec_right.appl_id= sec_group.appl_id AND sec_right.group_id= sec_group.group_id AND sec_right.entity_id= sec_group.entity_id AND sec_group.user_id2 = '$user_id')
            INNER JOIN sec_menu_header ON (sec_right.appl_id= sec_menu_header.appl_id AND sec_right.entity_id= sec_menu_header.entity_id AND sec_right.menu_header_id= sec_menu_header.menu_header_id)
            INNER JOIN
            sec_appl
            ON 
            sec_right.appl_id = sec_appl.appl_id AND
            sec_right.entity_id = sec_appl.entity_id
        WHERE
            sec_menu_header.active_flag = 'Y'
            AND sec_group.active_flag = 'Y'
	        AND sec_right.active_flag = 'Y'
            AND sec_right.appl_id = '$appl_id' 
            AND sec_right.entity_id = '$entity_id' 
            AND sec_group.user_id2 = '$user_id'
        ORDER BY sec_menu_header.seq_id ASC";

        $menus = DB::connection("sqlsrv")
        ->select($sqlQuery);
        // dd($menus);
        foreach ($menus as $menu) {

            if ($menu->icon == null || $menu->icon == "" ) {
                array_push($datas);
            } else {
                array_push($datas,[
                    'entity_id' => $menu->entity_id,
                    'appl_id' => $menu->appl_id,
                    'group_id' => $menu->group_id,
                    'menu_header_caption' => $menu->menu_header_caption,
                    'menu_header_path' => $menu->menu_header_path,
                    'menu_header_id' => $menu->menu_header_id,
                    'icon' => $menu->icon,
                    'submenus' => $this->secMenu($menu, $user_id, $search)
                ]);
            }
            
        }

        return $this->sendResponse($datas, 'Successfully get menu user');
    }

    private function secMenu($menu, $user_id, $search)
    {
        $datas = [];
        $sqlQuery = "SELECT DISTINCT
                        sec_menu.entity_id,
                        sec_menu.appl_id,
                        sec_menu.menu_id,
                        sec_menu.seq_id,
                        sec_menu.menu_caption,
                        sec_menu.route_path,
                        sec_menu.icon
                    FROM
                        sec_right
                        INNER JOIN sec_group ON ( sec_right.entity_id= sec_group.entity_id AND sec_right.appl_id= sec_group.appl_id AND sec_right.group_id= sec_group.group_id AND sec_group.user_id2 = '$user_id')
                        INNER JOIN sec_menu_header ON (sec_right.appl_id= sec_menu_header.appl_id AND sec_right.entity_id= sec_menu_header.entity_id AND sec_right.menu_header_id= sec_menu_header.menu_header_id)
                        INNER JOIN sec_menu ON ( sec_right.appl_id= sec_menu.appl_id AND sec_right.entity_id= sec_menu.entity_id AND sec_right.menu_id= sec_menu.menu_id )
                        INNER JOIN
                        sec_appl
                        ON 
                        sec_right.appl_id = sec_appl.appl_id AND
                        sec_right.entity_id = sec_appl.entity_id
                    WHERE
                        sec_menu_header.active_flag = 'Y'
                        AND sec_right.entity_id = '$menu->entity_id'
                        AND sec_right.appl_id = '$menu->appl_id' 
                        AND sec_right.group_id = '$menu->group_id' 
                        AND sec_right.menu_header_id = '$menu->menu_header_id' 
                        AND sec_menu.active_flag = 'Y'  
                        AND sec_menu.entity_id = '$menu->entity_id'
                        AND sec_menu.appl_id = '$menu->appl_id' 
                        AND sec_menu.menu_header_id = '$menu->menu_header_id' 
                        AND sec_group.user_id2= '$user_id' 
                        AND sec_menu_header.menu_header_id = '$menu->menu_header_id' 
                        AND sec_menu_header.menu_caption = '$menu->menu_header_caption'
                        AND sec_menu.menu_caption LIKE '%$search%'
                        -- AND sec_right.menu_id IN (
                        -- SELECT DISTINCT
                        --     sec_right.menu_id 
                        -- FROM
                        --     sec_right
                        --     INNER JOIN sec_group ON ( sec_right.appl_id= sec_group.appl_id AND sec_right.group_id= sec_group.group_id )
                        --     INNER JOIN sec_menu ON ( sec_right.appl_id= sec_menu.appl_id AND sec_right.menu_id= sec_menu.menu_id )
                        --     INNER JOIN sec_menu_header ON ( sec_menu.menu_header_id= sec_menu_header.menu_header_id AND sec_menu.appl_id= sec_menu_header.appl_id ) 
                        -- WHERE
                        --     sec_menu.menu_caption LIKE '%$search%'
                        --     AND sec_menu_header.active_flag = 'Y' 
                        --     AND sec_menu.entity_id = '$menu->entity_id'
                        --     AND sec_right.active_flag = 'Y'
                        --     AND sec_right.appl_id = '$menu->appl_id' 
                        --     AND sec_group.user_id2= '$user_id' 
                        --     AND sec_menu_header.menu_caption = '$menu->menu_header_caption'
                        -- )
                    ORDER BY sec_menu.seq_id ASC";

        $subMenus = DB::connection("sqlsrv")
        ->select($sqlQuery);

        foreach ($subMenus as $data) {
            if ($data->route_path == null || $data->route_path == "" ) {
                array_push($datas);
            } else {
                array_push($datas,$data);
            }
        }

        return $datas;

    }

    public function getEnvConf(Request $request)
    {
        try {

            $groupid = $request->groupid ? $request->groupid : '';
            $appl_id = $request->appl_id ?? 'MOBILE';
            $var_id = $request->var_id;
            
            if (!empty($var_id)){
                $getRight = DB::connection("sqlsrv")->table('sec_env_conf')
                                    ->selectRaw('TRIM(var_value) as var_value')
                                    ->where('entity_id','=', $request->entity_id)
                                    ->where('appl_id','=', $appl_id)
                                    ->where('var_id','=', $request->var_id)
                                    ->Where('user_id2', '=', getAuth($request)->user_id2)
                                    ->where('active_flag','=', 'Y')
                                    ->Value('var_value');
            }else{
                $getRight = DB::connection("sqlsrv")->table('sec_env_conf')
                            ->selectRaw("
                                        entity_id,
                                        appl_id,
                                        user_id2,
                                        var_id,
                                        trim(var_value) as var_value,
                                        note1
                                        ")
                            ->where('entity_id', $request->entity_id)
                            ->where('appl_id','=', $appl_id)
                            ->Where('user_id2', '=', getAuth($request)->user_id2)
                            ->where('active_flag','=', 'Y')
                            ->get();
            }

            return $this->sendResponse($getRight, 'Successfully get user previlege');            
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Internal Error', 500);
        }
    }
}
