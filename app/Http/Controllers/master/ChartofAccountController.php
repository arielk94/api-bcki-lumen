<?php

namespace App\Http\Controllers\master;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use DB;

class ChartofAccountController extends Controller
{
    public function index(Request $request)
    {
        $sqlWhere = "1=1";
        $ac_type = $request->ac_type;
        $drcr = $request->drcr;
        $curr_id = $request->curr_id;
        $active_flag = $request->active_flag;

        if ($ac_type) {
            $sqlWhere .= " and ac_type = '$ac_type'";
        }
        if ($drcr) {
            $sqlWhere .= " and drcr = '$drcr'";
        }
        if ($curr_id) {
            $sqlWhere .= " and curr_id = '$curr_id'";
        }
        if ($active_flag) {
            $sqlWhere .= " and active_flag = '$active_flag'";
        }

        $datas = DB::connection('sqlSrvFPI')->table('g_ledger')
            ->selectraw("
                TRIM(ac_num) as ac_num, 
                TRIM(ac_num) + '-' + TRIM(ac_name) as ac_description, 
                TRIM(ac_par) as ac_par,
                TRIM(ac_type) as ac_type, 
                TRIM(ac_name) as ac_name, 
                TRIM(tax) as tax, 
                TRIM(drcr) as drcr, 
                TRIM(curr_id) as curr_id,  
                TRIM(active_flag) as active_flag
            ")
            ->whereRaw($sqlWhere)
            ->where('ac_num', '<>', ' ')
            ->orderBy('ac_num', 'ASC')
            ->get();

        return $this->sendResponse($datas, 'Successfully get cust');
    }

    public function store(Request $request)
    {
        try {    

            $save = DB::connection('sqlSrvFPI')->table('g_ledger')
                    ->insert([
                        'ac_num' => $request->ac_num ? $request->ac_num : " ",
                        'ac_par' => $request->ac_par ? $request->ac_par : " ",
                        'ac_type' => $request->ac_type ? $request->ac_type : " ",
                        'ac_name' => $request->ac_name ? $request->ac_name : " ",
                        'tax' => $request->tax ? $request->tax : " ",
                        'drcr' => $request->drcr ? $request->drcr : " ",
                        'curr_id' => $request->curr_id ? $request->curr_id : " ",
                        'active_flag' => $request->status
                    ]);
            
            return $this->sendResponse('Successfully Created', 200);

        } catch (\Exception $e) {
            return $this->sendError('Internal Error', $e->getMessage(), 500);
        }
    }

    public function updateStatus(Request $request)
    {
        try {    

            $data = DB::connection('sqlSrvFPI')->table('g_ledger')
                    ->selectRaw('*')
                    ->where('ac_num', $request->ac_num)
                    ->first();

            if ($data) {

                $save = DB::connection('sqlSrvFPI')->table('g_ledger')
                        ->where('ac_num', $request->ac_num)
                        ->update([
                            'active_flag' => $request->status
                        ]);

                return $this->sendResponse('Successfully Updated', 200);
                
            } else {
                return $this->sendError('Data not Found', 'Error', 500);
            }

        } catch (\Exception $e) {
            return $this->sendError('Internal Error', $e->getMessage(), 500);
        }
    }
}
