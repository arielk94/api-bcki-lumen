<?php

namespace App\Http\Controllers\master;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use DB;

class SourceJournalController extends Controller
{
    public function index(Request $request)
    {
        try {

            $data = DB::connection('sqlSrvBCKI')->select(DB::raw("
                        SELECT DISTINCT TRIM(jv.source) AS source
                        FROM journal_voucher jv
                        INNER JOIN g_ledger as g_ledger ON jv.ac_num = g_ledger.ac_num
                    "));
            return $this->sendResponse($data, 200);
        } catch (\Exception $e) {
            return $this->sendError('Internal Error', $e->getMessage(), 500);
        }
    }
}
