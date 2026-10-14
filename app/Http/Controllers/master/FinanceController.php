<?php

namespace App\Http\Controllers\master;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/fpi/master/getFinanceReport",
     *     tags={"Master Finance"},
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
     *         description="Returns get all finance report",
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
        $sqlWhere = "1=1";
        $entity_id = $request->entity_id;
        $group_id = $request->group_id;
        $ac_num = $request->ac_num;
        $report_name = $request->report_name;

        if ($entity_id) {
            $sqlWhere.= " and glf.entity_id = '$entity_id'";
        }

        if ($group_id) {
            $sqlWhere.= " and glf.group_id = '$group_id'";
        }
 
        if ($ac_num) {
            $sqlWhere.= " and glf.ac_num = '$ac_num'";
        }
 
        if ($report_name) {
            $sqlWhere.= " and glf.report_name = '$report_name'";
        }

        $data =  DB::connection('sqlSrvFPI')
            ->table('g_ledger_finance as glf')
            ->selectRaw("TRIM(glf.report_name) as report_name,
                        TRIM(glf.entity_id) as entity_id,
                        TRIM(glf.group_id) as group_id,
                        TRIM(glf.ac_num) as ac_num,
                        TRIM(gl.ac_name) as ac_name,
                        TRIM(glf.ac_par) as ac_par,
                        glf.drcr,
                        glf.ppn")
            ->leftJoin('g_ledger as gl', 'gl.ac_num', '=', 'glf.ac_num')
            ->orderBy('glf.report_name', 'asc')
            ->orderBy('glf.entity_id', 'asc')
            ->orderBy('glf.group_id', 'asc')
            ->orderBy('glf.ac_num', 'asc')
            ->whereRaw($sqlWhere)
            ->get();

        return $this->sendResponse($data, 'Successfully get Finance Report List');
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/master/getReportName",
     *     tags={"Master Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all report names",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getReportName(Request $request)
    {
        $data =  DB::connection('sqlSrvFPI')
            ->table('g_ledger_finance as glf')
            ->selectRaw("DISTINCT(TRIM(glf.report_name)) as report_name")
            ->get();

        return $this->sendResponse($data, 'Successfully get Report Name List');
    }
    
    /**
     * @OA\Post(
     *     path="/api/fpi/master/getEntity",
     *     tags={"Master Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="report_name", type="string", example="ABC"),
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all report",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getEntity(Request $request)
    {
        $sqlWhere = "1=1";
        $report_name = $request->report_name;

        if ($report_name) {
            $sqlWhere .= " and glf.report_name = '$report_name'";
        }

        $data =  DB::connection('sqlSrvFPI')
            ->table('g_ledger_finance as glf')
            ->selectRaw("DISTINCT(TRIM(glf.entity_id)) as entity_id,
                        TRIM(glf.report_name) as report_name")
            ->whereRaw($sqlWhere)
            ->get();
        
        return $this->sendResponse($data, 'Successfully get Entity List');
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/master/getGroup",
     *     tags={"Master Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="report_name", type="string", example="ABC"),
     *             @OA\Property(property="entity_id", type="string", example="ABC"),
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get group report",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getGroup(Request $request)
    {
        $sqlWhere = "1=1";
        $report_name = $request->report_name;
        $entity_id = $request->entity_id;

        if ($report_name) {
            $sqlWhere .= " and glf.report_name = '$report_name'";
        }

         // Add condition for entity_id if provided
        if ($entity_id) {
            $sqlWhere .= " and glf.entity_id = '$entity_id'";
        }

        $data =  DB::connection('sqlSrvFPI')
            ->table('g_ledger_finance as glf')
            ->selectRaw("DISTINCT(TRIM(glf.group_id)) as group_id, glf.entity_id, glf.report_name")
            ->whereRaw($sqlWhere)
            ->get();

        return $this->sendResponse($data, 'Successfully get Group List');
    }

        /**
     * @OA\Post(
     *     path="/api/fpi/master/getAcNum",
     *     tags={"Master Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="ac_type", type="string", example="ABC"),
     *             @OA\Property(property="ac_num", type="string", example="ABC"),
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get ac num",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getAcNum(Request $request)
    {
        $sqlWhere = "1=1";
        $ac_type = $request->ac_type;
        $ac_num = $request->ac_num;
        if ($ac_num) {
            $sqlWhere .= " and TRIM(gl.ac_num) = '$ac_num'";
        }
        if($ac_type) {
            $sqlWhere .= " and TRIM(gl.ac_type) = '$ac_type'";
        }
        $data =  DB::connection('sqlSrvFPI')
            ->table('g_ledger as gl')
            ->selectRaw("TRIM(gl.ac_num) as ac_num, TRIM(gl.ac_name) as ac_name")
            ->whereRaw($sqlWhere)
            ->get();

        return $this->sendResponse($data, 'Successfully get Ac Num List');
    }

    // public function saveOffice(Request $request)
    // {
    //     try
    //     {
    //             $datas = DB::connection('sqlSrvFPI') 
    //                 ->table('office')
    //                 ->selectRaw("MAX(office.office_id)+1 as office_id")
    //                 ->first(); 

    //             $insert = DB::connection('sqlSrvFPI')
    //                 ->table('office')
    //                 ->insert([
    //                     'office_id' => $datas->office_id,
    //                     'office_name' => $request->office_name ? $request->office_name : ' ',
    //                     'region' => $request->region ? $request->region : ' ',
    //                     'address' => $request->address ? $request->address : ' ',
    //                     'status' => $request->status ? $request->status : 'Y',
    //                     'created_date' => now(),
    //                     'created_by' => getAuth($request)->user_id2,
    //                     'modified_date' => now(),
    //                     'modified_by' => getAuth($request)->user_id2,
    //                 ]);
    //             return $this->sendResponse('Success Insert office', 'Successfully insert Office');  
    //     }
        
    //     catch (\Exception $e) {
    //         return $this->sendError($e->getMessage(), 'Error', 500);
    //     }
    // }

    // public function updateOffice(Request $request)
    // {
    //     try
    //     {
    //         $data = $this->checkOffice($request)->first();
                    
    //         if($data){

    //             $insert = DB::connection('sqlSrvFPI')
    //             ->table('office')
    //             ->where('office_id', $request->office_id)
    //             ->update([
    //                 'office_name' => $request->office_name,
    //                 'region' => $request->region ? $request->region : ' ',
    //                 'address' => $request->address,
    //                 'status' => $request->status,
    //                 'modified_date' => now(),
    //                 'modified_by' => getAuth($request)->user_id2,
    //             ]);
    //             return $this->sendResponse('Success Update', 'Successfully Update Office');  

    //         } else {
    //             return $this->sendError('Upss Sorry tidak bisa update office karena belum dibuat', 'Error', 500);
    //         }
    //     }
        
    //     catch (\Exception $e) {
    //         return $this->sendError($e->getMessage(), 'Error', 500);
    //     }
    // }

    public function insertAcNum(Request $request)
    {
        try {
            // $data = DB::connection('sqlSrvFPI')
            //     ->table('g_ledger_finance')
            //     ->where('ac_num', $request->ac_num)
            //     ->first();

            // if ($data) {
            //     // Update existing record
            //     DB::connection('sqlSrvFPI')
            //         ->table('g_ledger_finance')
            //         ->where('ac_num', $request->ac_num)
            //         ->update([
            //             'report_name' => $request->report_name,
            //             'entity_id' => $request->entity_id,
            //             'group_id' => $request->group_id,
            //             'ac_par' => $request->ac_par,
            //             'drcr' => $request->drcr,
            //             'ppn' => $request->ppn,
            //             'modified_date' => now(),
            //             'modified_by' => getAuth($request)->user_id2,

            //             report_name
            //             office_id
            //             entity_id
            //             group_id
            //             ac_num
            //             ac_par
            //             drcr
            //             user_id
            //             ppn
            //             dt_modified

            //         ]);
            // } else {
                // Insert new record
                $data = DB::connection('sqlSrvFPI')
                    ->table('g_ledger_finance')
                    ->insert([
                        'office_id' => 1, // Assuming office_id is always 1, adjust as necessary
                        'report_name' => !empty($request->report_name) ? $request->report_name : '',
                        'entity_id' => !empty($request->entity_id) ? $request->entity_id : '',
                        'group_id' => !empty($request->group_id) ? $request->group_id : '',
                        'ac_num' => !empty($request->ac_num) ? $request->ac_num : '',
                        'ac_par' => !empty($request->ac_par) ? $request->ac_par : '',
                        'drcr' => !empty($request->drcr) ? $request->drcr : '',
                        'ppn' => !empty($request->ppn) ? $request->ppn : 0,
                        'dt_modified' => now(),
                        'user_id' => getAuth($request)->user_id2
                    ]);
            // }

            return $this->sendResponse($data, 'Successfully Insert Ac Num');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function deleteAcNum(Request $request)
    {
        try {
            $data = DB::connection('sqlSrvFPI')
                ->table('g_ledger_finance')                
                ->where('report_name', $request->report_name)
                ->where('entity_id', $request->entity_id)
                ->where('group_id', $request->group_id)
                ->where('ac_num', $request->ac_num)
                ->delete();

            return $this->sendResponse($data, 'Successfully Delete Ac Num');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
}
