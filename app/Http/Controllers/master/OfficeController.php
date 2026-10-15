<?php

namespace App\Http\Controllers\master;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfficeController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/bcki/office",
     *     tags={"Master Office"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="office_id", type="string", example="ABC"),
     *             @OA\Property(property="region_id", type="string", example="ABC"),
     *             @OA\Property(property="status", type="string", example="ABC"),
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
        $office_id = $request->office_id;
        $region_id = $request->region_id;
        $status = $request->status;

        if ($office_id) {
            $sqlWhere.= " and office.office_id = '$office_id'";
        }

        if ($region_id) {
            $sqlWhere.= " and office.region = '$region_id'";
        }
 
        if ($status) {
            $sqlWhere.= " and office.status = '$status'";
        }

        $datas = DB::connection('sqlSrvBCKI') 
                    ->table('branch_office as bo')
                    ->selectRaw("bo.office_id, 
                                bo.office, 
                                bo.address,
                                bo.region,
                                bo.active_flag")
                    ->leftJoin('region', function ($join) {
                        $join->on('region.region_id', '=', 'bo.region');
                    })
                    ->whereRaw($sqlWhere)
                    ->get(); 

        return $this->sendResponse($datas, 'Successfully get Office List');
    }

    
    private function checkOffice(Request $request)
    {
            return DB::connection('sqlSrvBCKI')
                ->table('office')
                ->where('office_id', $request->office_id);
    }

    public function saveOffice(Request $request)
    {
        try
        {
                $datas = DB::connection('sqlSrvBCKI') 
                    ->table('office')
                    ->selectRaw("MAX(office.office_id)+1 as office_id")
                    ->first(); 

                $insert = DB::connection('sqlSrvBCKI')
                    ->table('office')
                    ->insert([
                        'office_id' => $datas->office_id,
                        'office_name' => $request->office_name ? $request->office_name : ' ',
                        'region' => $request->region ? $request->region : ' ',
                        'address' => $request->address ? $request->address : ' ',
                        'status' => $request->status ? $request->status : 'Y',
                        'created_date' => now(),
                        'created_by' => getAuth($request)->user_id2,
                        'modified_date' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);
                return $this->sendResponse('Success Insert office', 'Successfully insert Office');  
        }
        
        catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function updateOffice(Request $request)
    {
        try
        {
            $data = $this->checkOffice($request)->first();
                    
            if($data){

                $insert = DB::connection('sqlSrvBCKI')
                ->table('office')
                ->where('office_id', $request->office_id)
                ->update([
                    'office_name' => $request->office_name,
                    'region' => $request->region ? $request->region : ' ',
                    'address' => $request->address,
                    'status' => $request->status,
                    'modified_date' => now(),
                    'modified_by' => getAuth($request)->user_id2,
                ]);
                return $this->sendResponse('Success Update', 'Successfully Update Office');  

            } else {
                return $this->sendError('Upss Sorry tidak bisa update office karena belum dibuat', 'Error', 500);
            }
        }
        
        catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
}
