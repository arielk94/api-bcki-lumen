<?php

namespace App\Http\Controllers\master;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Str;

class CommodityController extends Controller
{
     /**
     * @OA\Post(
     *     path="/api/fpi/master/commodity",
     *     tags={"Master Commodity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all commodity",
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
        $datas = DB::connection('sqlSrvFPI')
            ->table('qc_item_commodity')
            ->selectRaw("com_id, TRIM(com_desc) as com_desc, active_flag")
            ->where('active_flag', 'Y')
            ->orderBy('com_id', 'asc')
            ->get();

        return $this->sendResponse($datas, 'Successfully get qc_item_commodity');
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/master/commodity/show",
     *     tags={"Master Commodity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="qc_trans_id", type="string", example="ABC"),
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all commodity",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function show(Request $request)
    {
        $qc_trans_id = trim($request->qc_trans_id);
        $commodities = DB::connection('sqlSrvFPI')->select(DB::raw("
                                SELECT
                                    DISTINCT commodity.com_desc,
                                    commodity.com_id, commodity.no_urut, commodity.[percent]
                                FROM
                                    [dbo].[qc_trans_dtl] AS qtl
                                    INNER JOIN qc_trans_hdr AS qhdr ON qhdr.qc_trans_id = qtl.qc_trans_id
                                    INNER JOIN qc_item AS qc_item ON qc_item.id_qc_item = qtl.id_qc_item
                                    INNER JOIN qc_item_commodity AS commodity ON qc_item.com_id = commodity.com_id
                                    INNER JOIN house_unit AS house_unit ON qhdr.id_house = house_unit.id_house
                                    INNER JOIN site_cluster AS site_cluster ON house_unit.id_cluster = site_cluster.id_cluster
                                    INNER JOIN site AS site ON house_unit.id_site = site.id_site
                                    INNER JOIN spk_hdr AS spk_hdr ON spk_hdr.id_spk = qhdr.doc_id
                                    LEFT OUTER JOIN vendor AS vendor ON vendor.vendor_id = spk_hdr.pihak_2 
                                WHERE
                                    qhdr.doc_type = 'SPK' AND qhdr.qc_trans_id = '$qc_trans_id'
                                ORDER BY commodity.no_urut
                            "));

        return $this->sendResponse($commodities, 'Successfully get qc_item_commodity');

    }
}
