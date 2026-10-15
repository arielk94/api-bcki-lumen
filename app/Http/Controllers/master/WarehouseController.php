<?php

namespace App\Http\Controllers\master;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/bcki/whouse",
     *     tags={"Master Warehouse"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="office_id", type="string", example="ABC"),
     *             @OA\Property(property="wh_id", type="string", example="ABC"),
     *
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all warehouse bin",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function index(Request $request)
    {
        $where = "1=1";

        if($request->office_id != null)
        {
            $office_id = $request->office_id;
        }
        else
        {
            $office_id = '';
        }

        if($request->wh_id != null)
        {
            $wh_id = $request->wh_id;
        }
        else
        {
            $wh_id = '';
        }

        
        if (!empty($office_id))
        {
            $where = $where . " and office_id = ". "'" . $office_id . "'";
        }

        if (!empty($wh_id))
        {
            $where = $where . " and wh_id = ". "'" . $wh_id . "'";
        }

        $result = DB::connection('sqlSrvBCKI')
                ->table('whouse')
                ->selectRaw('office_id, 
                TRIM(wh_id) as wh_id, 
                TRIM(descr) as descr, 
                TRIM(dept_id) as dept_id, active_flag')
                ->whereRaw($where)
                ->where('active_flag', 'Y')
                ->orderBy('wh_id', 'asc')
                ->get();

        return $this->sendResponse($result, 'Successfully get Warehouse');
        
    }

    public function getCurrentPeriod(Request $request)
    {
        $result =  DB::connection("sqlSrvBCKI")
                        ->table('period')
                        ->where('dept_id', 'PRODWH')
                        ->where('office_id', $request->office_id)
                        ->first();

        return $this->sendResponse($result, 'Successfully get current period');

    }

}
