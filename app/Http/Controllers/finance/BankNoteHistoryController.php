<?php

namespace App\Http\Controllers\finance;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankNoteHistoryController extends Controller
{

    public function index(Request $request)
    {



    }

    public function searchData(Request $request)
    {

        $tr_date_from = $request->start_date;
        $tr_date_to = $request->end_date;
        $bank_id = $request->bank_id;
        $account_id = $request->account_id;
        $transfer_type = $request->transfer_type;
        $posting_id = $request->posting_id;
        $sqlWhere = " 1=1 ";

        if ($bank_id) {
            $sqlWhere =  $sqlWhere . " and v.bank_name  = '" . $bank_id . "'";
        }

        if ($account_id) {
            $sqlWhere =  $sqlWhere . " and v.bank_acc  = '" . $account_id . "'";
        }

        if ($transfer_type) {
            $sqlWhere =  $sqlWhere . " and v.tr_type  = '" . $transfer_type . "'";
        }

        if ($tr_date_from) {
            $sqlWhere =  $sqlWhere . " and v.dt_trans  >= CONVERT(DATE, '" . $tr_date_from . "')";
        }
        if ($tr_date_to) {
            $sqlWhere = $sqlWhere . " and v.dt_trans  <= CONVERT(DATE, '" . $tr_date_to . "')";
        }

        if (!empty($posting_id)) {
            if (strtoupper($posting_id) == 'Y') {
                $sqlWhere = $sqlWhere . " and v.voucher >0";
            } else {
                $sqlWhere = $sqlWhere . " and v.voucher = 0";
            }
        }

        $result = DB::connection('sqlSrvBCKI')
                ->table('v_bank_split as v')
                ->selectRaw("
                    v.*, 
                    (
                        SELECT COUNT(*) 
                        FROM v_bank_split c 
                        WHERE c.mother_child = 'CHILD' 
                        AND c.mother_id = v.mother_id
                    ) AS total_child_count
                ")
                ->where('v.mother_child', 'MOTHER')
                ->whereRaw($sqlWhere)
                ->orderBy('v.dt_trans', 'asc')
                ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $result
        ], 200);

    }

    public function getDetails(Request $request) 
    {
        
        $mother_id = $request->mother_id;

        $result = DB::connection('sqlSrvBCKI')
                ->table('v_bank_split')
                ->where('mother_id', $mother_id)
                ->where('mother_child', 'CHILD')
                ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Details retrieved successfully',
            'data' => $result
        ], 200);

    }

}