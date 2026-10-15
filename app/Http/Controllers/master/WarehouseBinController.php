<?php

namespace App\Http\Controllers\master;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseBinController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/bcki/whouse/bin",
     *     tags={"Master Warehouse Bin"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="office_id", type="string", example="ABC"),
     *             @OA\Property(property="wh_id", type="string", example="ABC"),
     *             @OA\Property(property="bin_id", type="string", example="ABC"),
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

        if($request->bin_id != null)
        {
            $bin_id = $request->bin_id;
        }
        else
        {
            $bin_id = '';
        }

        
        if (!empty($office_id))
        {
            $where = $where . "and office_id = ". "'" . $office_id . "'";
        }

        if (!empty($wh_id))
        {
            $where = $where . "and wh_id = ". "'" . $wh_id . "'";
        }

        if (!empty($bin_id))
        {
            $where = $where . "and bin_id = ". "'" . $bin_id . "'";
        }

        $result = DB::connection('sqlSrvBCKI')
                ->table('wh_bin')
                ->selectRaw('office_id, 
                TRIM(wh_id) as wh_id, 
                TRIM(bin_id) as bin_id, 
                TRIM(descr) as descr,active_flag')
                ->whereRaw($where)
                ->where('active_flag', 'Y')
                ->orderBy('wh_id', 'asc')
                ->get();

        return $this->sendResponse($result, 'Successfully get Warehouse Bin');
        
    }
}
