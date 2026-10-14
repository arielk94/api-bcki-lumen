<?php

namespace App\Http\Controllers\finance;

use App\Services\AccountingService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class JurnalbyAccountController extends Controller
{
    protected $serviceAcc;

    public function __construct(AccountingService $serviceAcc)
    {
        $this->serviceAcc = $serviceAcc;
    }

    public function index(Request $request){
        $sqlWhere = "1=1";
        $start_date = $request->start_date; // contoh: '20250101'
        $end_date = $request->end_date; // contoh: '20250531'
        $ac_num = $request->ac_num;

        if ($start_date) {
            if ($end_date) {
                $sqlWhere .= " AND tr_date >= '{$start_date}' 
                            AND tr_date <= '{$end_date}'";
            } else {
                $sqlWhere .= " AND tr_date >= '{$start_date} 00:00:00.001' 
                            AND tr_date <= '{$start_date} 23:59:59.000'";
            }
        }
        
        if ($ac_num) {
            $sqlWhere .= " and (ac_num_dr = '$ac_num' or ac_num_cr = '$ac_num')";
        }

        $data = DB::connection('sqlSrvFPI')
            ->table('v_journal_by_ac_num')
            ->selectRaw("*")
            ->whereRaw($sqlWhere)
            ->orderBy('tr_date', 'asc')
            ->get();

        return $this->sendResponse($data, 'Successfully retrieved data');
    }

    public function info(Request $request){
        $sqlWhere = "1=1";
        $start_date = $request->start_date; // contoh: '20250101'
        $end_date = $request->end_date; // contoh: '20250531'
        $ac_num = $request->ac_num;

        if ($start_date) {
            if ($end_date) {
                $sqlWhere .= " AND tr_date >= '{$start_date}' 
                            AND tr_date <= '{$end_date}'";
            } else {
                $sqlWhere .= " AND tr_date >= '{$start_date} 00:00:00.001' 
                            AND tr_date <= '{$start_date} 23:59:59.000'";
            }
        }
        
        if ($ac_num) {
            $sqlWhere .= " and (ac_num_dr = '$ac_num' or ac_num_cr = '$ac_num')";
        }

        $data = DB::connection('sqlSrvFPI')
            ->table('v_journal_by_ac_num')
            ->whereRaw($sqlWhere)
            ->orderBy('tr_date')
            ->orderBy('source')
            ->orderBy('source_num')
            ->orderByRaw("
                CASE
                    WHEN amt_dr > 0 THEN 1
                    ELSE 2
                END
            ")
            ->orderBy('ac_num')
            ->get();

        return $this->sendResponse($data, 'Successfully retrieved data');
    }

    
}
