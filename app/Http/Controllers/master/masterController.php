<?php

namespace App\Http\Controllers\master;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use PDO;

class masterController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/fpi/master/getProvince",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all province",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getProvince(Request $request)
    {
        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('province_list')
                ->selectRaw("
                                    province_list.province_code as id_province, 
                                    province_list.province_code as kemendagri_province_code, 
                                    province_list.province
                                ")
                ->get();

            return $this->sendResponse($datas, 'Successfully get Province');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/master/getCity",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="id_province", type="string", example="ABC"),
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all city",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getCity(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $id_province = $request->id_province;

            $sqlWhere = $sqlWhere . "and city_list.province_code = " . "'" . $id_province . "'";

            $datas = DB::connection("sqlSrvFPI")
                ->table('city_list')
                ->selectRaw("
                                        city_list.city_code as id_city,
                                        city_list.city_code as id_prov_city, 
                                        city_list.city as city_name, 
                                        city_list.province_code as id_province,
                                        city_list.province_code kemendagri_province_code
                                        ")
                ->whereRaw($sqlWhere)
                ->get();
            return $this->sendResponse($datas, 'Successfully get City');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/master/getDistrict",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="province_code", type="string", example="ABC"),
     *             @OA\Property(property="city_code", type="string", example="ABC"),
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all district",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getDistrict(Request $request)
    {
        $province_code = $request->province_code;
        $city_code = $request->city_code;
        $sqlWhere = "1=1";

        if (!empty($province_code)) {
            $sqlWhere = $sqlWhere . "and province_code = " . "'" . $province_code . "'";
        }

        if (!empty($city_code)) {
            $sqlWhere = $sqlWhere . "and city_code = " . "'" . $city_code . "'";
        }

        $result = DB::connection('sqlSrvFPI')
            ->table('district_list')
            ->selectRaw("TRIM(district_code) as district_code, district, city_code, province_code")
            ->whereRaw($sqlWhere)
            ->orderBy('district', 'asc')
            ->get();

        return $this->sendResponse($result, 'Successfully get district');
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/master/getVillage",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="province_code", type="string", example="ABC"),
     *             @OA\Property(property="city_code", type="string", example="ABC"),
     *             @OA\Property(property="district_code", type="string", example="ABC"),
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all village",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getVillage(Request $request)
    {
        $province_code = $request->province_code;
        $city_code = $request->city_code;
        $district_code = $request->district_code;

        $sqlWhere = "1=1";

        if (!empty($province_code)) {
            $sqlWhere = $sqlWhere . "and province_code = " . "'" . $province_code . "'";
        }

        if (!empty($city_code)) {
            $sqlWhere = $sqlWhere . "and city_code = " . "'" . $city_code . "'";
        }

        if (!empty($district_code)) {
            $sqlWhere = $sqlWhere . "and district_code = " . "'" . $district_code . "'";
        }

        $result = DB::connection('sqlSrvFPI')
            ->table('village_list')
            ->selectRaw("TRIM(village_code) as village_code, village, district_code, city_code, province_code")
            ->whereRaw($sqlWhere)
            ->orderBy('village', 'asc')
            ->get();

        return $this->sendResponse($result, 'Successfully get village');
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/master/getApplConstant",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="key_code", type="string", example="ABC"),
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all application constants",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getApplConstant(Request $request) //project atau retail
    {
        $sqlWhere = "1=1";

        $key_code = $request->key_code;

        if ($key_code) {
            $sqlWhere = $sqlWhere . "and key_code = '$key_code'";
        }

        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('appl_constant')
                ->selectRaw("*")
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Successfully get appl constant');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getApplConstantMasterCustomer(Request $request) //project atau retail
    {
        $sqlWhere = "1=1";

        $tipe = $request->tipe;

        if ($tipe) {
            $sqlWhere = $sqlWhere . "and str3 = '$tipe'";
        }

        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('appl_constant')
                ->selectRaw("*")
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Successfully get appl constant');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /*================================================================================*/
    /*===================================| REGION |===================================*/
    /*================================================================================*/

    public function getRegion(Request $request)
    {
        $sqlWhere = "1=1";

        $status = $request->status;

        if ($status == "Aktif") {
            $sqlWhere = $sqlWhere . "and active_flag = 'Y'";
        } else if ($status == "Non-aktif") {
            $sqlWhere = $sqlWhere . "and active_flag = 'N'";
        } else {
            // $sqlWhere = $sqlWhere . "and active_flag = 'Y'";
        }

        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('region')
                ->selectRaw("region_id, descr as region_name,
                                CASE 
                                    WHEN active_flag = 'Y' THEN 'Active'
                                    WHEN active_flag = 'N' THEN 'Non-Active'
                                    ELSE '-'
                                END AS active_flag_kata
                                ")
                ->whereRaw($sqlWhere)
                ->orderByRaw('CAST(SUBSTRING(region_id, 4, LEN(region_id) - 3) AS INT)')
                ->get();

            return $this->sendResponse($datas, 'Successfully get Region');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function updateRegion(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $region = DB::connection('sqlSrvFPI')
                ->table('region')
                ->where('region_id', $request->region_id)
                ->first();

            if ($region) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('region')
                    ->where('region_id', $request->region_id)
                    ->update([
                        'descr' =>  $request->descr ? $request->descr : '',
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,

                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Update', 'Successfully update Region');
            } else {
                return $this->sendError('Region tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function deleteRegion(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $region = DB::connection('sqlSrvFPI')
                ->table('region')
                ->where('region_id', $request->region_id)
                ->first();

            if ($region) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('region')
                    ->where('region_id', $request->region_id)
                    ->update([
                        'active_flag' => 'N',
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,

                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Update', 'Successfully Delete Region');
            } else {
                return $this->sendError('Region tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function activateRegion(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $region = DB::connection('sqlSrvFPI')
                ->table('region')
                ->where('region_id', $request->region_id)
                ->first();

            if ($region) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('region')
                    ->where('region_id', $request->region_id)
                    ->update([
                        'active_flag' => 'Y',
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,

                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Update', 'Successfully Activate Region');
            } else {
                return $this->sendError('Region tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function storeRegion(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {
            $region_id = $this->generateRegionID($request);
            // dd($region_id);

            $save = DB::connection('sqlSrvFPI')
                ->table('region')
                ->insert([
                    'region_id' => $region_id,
                    'descr' => $request->region_name,
                    'active_flag' => 'Y'
                ]);
            DB::connection('sqlSrvFPI')->commit();
            return $this->sendResponse($save, 'Successfully saved Region');
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function generateRegionID($request)
    {
        $gen_region_id = '';

        $getLastTrID =  DB::connection('sqlSrvFPI')
            ->table('region')
            ->max('region_id');

        if ($getLastTrID) {
            $seq = (int) substr(trim($getLastTrID), 3) + 1;
            // $gen_region_id = 'REG'.str_pad($seq, 3, '0', STR_PAD_LEFT);
            $gen_region_id = 'REG' . $seq;
        } else {
            $gen_region_id = 'REG' . '1';
        }

        return $gen_region_id;
    }

    /*================================================================================*/
    /*===================================| OFFICE |===================================*/
    /*================================================================================*/

    public function getOffice(Request $request)
    {
        $sqlWhere = "1=1";

        $status = $request->status;
        $region = $request->region;

        if ($status == "Y") {
            $sqlWhere = $sqlWhere . "and branch_office.active_flag = 'Y'";
        } else if ($status == "N") {
            $sqlWhere = $sqlWhere . "and branch_office.active_flag = 'N'";
        } else {
            // $sqlWhere = $sqlWhere . "and active_flag = 'Y'";
        }

        if ($region) {
            $sqlWhere = $sqlWhere . "and branch_office.region = '" . $region . "'";
        }

        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('branch_office')
                ->selectRaw("
                                            branch_office.office_id, 
                                            trim(branch_office.office) as office, 
                                            trim(branch_office.address) as address, 
                                            trim(branch_office.city) as city, 
                                            trim(branch_office.zip_code) as zip_code, 
                                            branch_office.region, 
                                            branch_office.active_flag, 
                                            branch_office.dt_modified, 
                                            branch_office.modified_by, 
                                            CASE 
                                                    WHEN branch_office.active_flag = 'Y' THEN 'Active'
                                                    WHEN branch_office.active_flag = 'N' THEN 'Non-Active'
                                                    ELSE '-'
                                            END AS active_flag_kata,
                                            region.descr
                                ")
                ->leftJoin('region', function ($join) {
                    $join->on('branch_office.region', '=', 'region.region_id');
                })
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Successfully get Office');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function updateOffice(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $region = DB::connection('sqlSrvFPI')
                ->table('branch_office')
                ->where('office_id', $request->office_id)
                ->first();

            if ($region) {
                $status = $request->status;
                $region = $request->region;

                // if ($status == "Aktif"){
                //     $status_ofc = 'Y';
                // }else if($status == "Non-aktif"){
                //     $status_ofc = 'N';
                // }else{
                //     $status_ofc = 'Y';
                // }

                $save = DB::connection('sqlSrvFPI')
                    ->table('branch_office')
                    ->where('office_id', $request->office_id)
                    ->update([
                        'office' =>  $request->office ? $request->office : '',
                        'address' =>  $request->address ? $request->address : '',
                        'city'   =>  $request->city   ? $request->city   : '',
                        'region' =>  $request->region ? $request->region : '',
                        'active_flag' =>  $request->status ? $request->status : '',
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,

                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Update', 'Successfully update Office');
            } else {
                return $this->sendError('Office tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function deleteOffice(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $office = DB::connection('sqlSrvFPI')
                ->table('branch_office')
                ->where('office_id', $request->office_id)
                ->first();

            if ($office) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('branch_office')
                    ->where('office_id', $request->office_id)
                    ->update([
                        'active_flag' => 'N',
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,

                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Update', 'Successfully Delete Office');
            } else {
                return $this->sendError('Office tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function activateOffice(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $office = DB::connection('sqlSrvFPI')
                ->table('branch_office')
                ->where('office_id', $request->office_id)
                ->first();

            if ($office) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('branch_office')
                    ->where('office_id', $request->office_id)
                    ->update([
                        'active_flag' => 'Y',
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,

                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Update', 'Successfully Activate Office');
            } else {
                return $this->sendError('Office tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function storeOffice(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();
        try {
            if ($request->office_id) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('branch_office')
                    ->where('office_id', $request->office_id)
                    ->update([
                        'office' =>  $request->office ? $request->office : '',
                        'address' =>  $request->address ? $request->address : '',
                        'city'   =>  $request->city   ? $request->city   : '',
                        'region' =>  $request->region_id ? $request->region_id : '',
                        'active_flag' =>  $request->status ? $request->status : 'Y',
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);
            } else {
                $office_id = $this->generateOfficeID($request);
                $save = DB::connection('sqlSrvFPI')
                    ->table('branch_office')
                    ->insert([
                        'office_id' => $office_id,
                        'office' => $request->office,
                        'address' => $request->address,
                        'city' => $request->city,
                        'region' => $request->region_id,
                        'active_flag' => 'Y',
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);
            }

            DB::connection('sqlSrvFPI')->commit();
            return $this->sendResponse($save, 'Successfully saved Office');
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function generateOfficeID($request)
    {
        $gen_region_id = '';

        $getLastTrID =  DB::connection('sqlSrvFPI')
            ->table('branch_office')
            ->max('office_id');

        if ($getLastTrID) {
            $seq = $getLastTrID;
            $gen_region_id = $seq + 1;
        } else {
            $gen_region_id = '1';
        }

        return $gen_region_id;
    }

    //  ========== HOUSE TYPE ===========
    /**
     * @OA\Post(
     *     path="/api/fpi/houseType/getHouseType",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="id_site", type="string", example="ABC"),
     *             @OA\Property(property="houses_category", type="string", example="Rumah"),
     *             @OA\Property(property="status", type="string", example="Aktif"),
     *             @OA\Property(property="id_prov", type="string", example="ABC"),
     *             @OA\Property(property="id_prov_city", type="string", example="ABC"),
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all house types",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getHouseType(Request $request)
    {
        $sqlWhere = "1=1";

        $id_site = $request->id_site;
        $houses_category = $request->houses_category;
        $status = $request->status;
        $id_prov = $request->id_prov;
        $id_prov_city = $request->id_prov_city;

        // if ($house_size == 'Small') {
        //     $sqlWhere = $sqlWhere . " and house_type.building_area < 90 ";
        // } elseif ($house_size == 'Medium') {
        //     $sqlWhere = $sqlWhere . " and house_type.building_area >=90 and house_type.building_area < 300 ";
        // } elseif ($house_size == 'Large') {
        //     $sqlWhere = $sqlWhere . " and house_type.building_area >= 300 ";
        // }

        if ($houses_category == 'Rumah') {
            $sqlWhere = $sqlWhere . " and house_type.category = 'Rumah' ";
        } elseif ($houses_category == 'Ruko') {
            $sqlWhere = $sqlWhere . " and house_type.category = 'Ruko' ";
        }

        if ($status == 'Non-aktif') {
            $sqlWhere = $sqlWhere . " and house_type.stat='N' ";
        }
        if ($status == 'Aktif') {
            $sqlWhere = $sqlWhere . " and house_type.stat='Y' ";
        }

        if (empty($status)) {
            $sqlWhere = $sqlWhere . " and house_type.stat='Y' ";
        }

        if (!empty($id_site)) {
            $sqlWhere = $sqlWhere . " and house_type.id_site = '$id_site' ";
        }

        if ($id_prov_city) {
            $sqlWhere = $sqlWhere . " and site.city='$id_prov_city' ";
        }

        if ($id_prov) {
            $sqlWhere = $sqlWhere . " and city_list.province_code = '$id_prov' ";
        }

        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('house_type')
                ->selectRaw("house_type.*, site.site_name")
                ->leftjoin('site', 'house_type.id_site', '=', 'site.id_site')
                ->leftjoin('city_list', 'site.city', '=', 'city_list.city_code')
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Successfully get House Type');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function storeHouseType(Request $request)
    {
        $house_qty = $request->house_qty ?? 0;
        $house_category = $request->houses_category;
        $id_site = $request->site_id;

        DB::connection('sqlSrvFPI')->beginTransaction();

        try {
            $house_type_id = $this->generateHouseTypeID($request);

            $save = DB::connection('sqlSrvFPI')
                ->table('house_type')
                ->insert([
                    'id_house_type' => $house_type_id,
                    'sbk_name' => $request->sbk_name,
                    'common_name' => $request->common_name,
                    'date_created' => now(),
                    'created_by' => getAuth($request)->user_id2,
                    'date_modified' => '',
                    'modified_by' => '',
                    'building_dimension' => $request->building_dimension ?? '',
                    'building_area' => $request->building_area ?? '',
                    'building_area_uom' => 'meter persegi',
                    'land_dimension' => $request->land_dimension ?? '',
                    'land_area' => $request->land_area ?? '',
                    'land_area_uom' => 'meter persegi',
                    'remark' => $request->remark ?? '',
                    'stat' => 'Y',
                    'category' => $house_category,
                    'id_site' => $id_site,
                    'house_qty' => $house_qty,
                    'map_color_name' => $request->map_color_name,
                    'map_color_rgb' => $request->map_color_rgb,
                ]);
            DB::connection('sqlSrvFPI')->commit();

            // --  insert house unit
            // $x = 1;
            // while ($x <= $house_qty) {
            //     $house_name = $house_category . ' ' . $x;
            //     $id_house = $this->generateHouseItemID($request);

            //     DB::connection('sqlSrvFPI')
            //         ->table('house_unit')
            //         ->insert([
            //             'id_house' => $id_house,
            //             'id_site' => $id_site,
            //             'id_house_type' => $house_type_id,
            //             'id_cluster' => '',
            //             'house_name' => $house_name,
            //             'stat' => 'G',
            //             'date_created' => now(),
            //             'created_by' => getAuth($request)->user_id2,
            //             'date_modified' => '',
            //             'modified_by' => '',
            //             'date_build' => '',
            //             'date_finish' => '',
            //             'date_sold' => '',
            //             'building_dimension' => $request->building_dimension ?? '',
            //             'building_area' => $request->building_area ?? '',
            //             'building_area_uom' => 'meter persegi',
            //             'land_dimension' => $request->land_dimension ?? '',
            //             'land_area' => $request->land_area ?? '',
            //             'land_area_uom' => 'meter persegi',
            //         ]);

            //     $x++;
            // }

            return $this->sendResponse($save, 'Successfully Saved House Type');
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function generateHouseTypeID($request)
    {
        // $dateYear = substr( date("Y"), 0);
        $dateYear = date("Y");
        // $dateMonth = date("m");
        // $dateDay = date("d");
        $gen_cluster_id = '';

        $getLastTrID = DB::connection('sqlSrvFPI')
            ->table('house_type')
            ->where('id_house_type', 'like', "%" . $dateYear . "%")
            ->max('id_house_type');

        if ($getLastTrID) {
            $seq = substr(trim($getLastTrID), -3) + 1;
            $gen_cluster_id = 'H' . $dateYear . str_pad($seq, 3, '0', STR_PAD_LEFT);
        } else {
            $gen_cluster_id = 'H' . $dateYear . '001';
        }

        return $gen_cluster_id;
    }


    public function getDetailHouseType(Request $request)
    {
        try {
            // dd($sqlWhere);
            $datas = DB::connection("sqlSrvFPI")
                ->table('house_type')
                ->selectRaw("
                                            *
                                            ")
                // ->leftjoin('commodity', function ($join) {
                //     $join->on('article.com_id', '=', 'commodity.com_id')
                //          ->on('category.cat_id', '=', 'commodity.cat_id');
                // })
                ->whereRaw("house_type.id_house_type = " . "'" . $request->id_house_type . "'")
                ->get();
            return $this->sendResponse($datas, 'Successfully get House Detail');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function updateHouseType(Request $request)
    {
        $id_site = $request->site_id;
        $house_type_id = $request->id_house_type;
        $house_category = $request->houses_category;

        try {
            $site = DB::connection('sqlSrvFPI')
                ->table('house_type')
                ->where('id_house_type', $house_type_id)
                ->first();

            if (!$site) {
                return $this->sendError('No Tipe Rumah tidak ditemukan', 'Error', 500);
            }

            // -- get jumlah house item before, jumlah status Y/N
            $getInfoHouse = DB::connection("sqlSrvFPI")
                ->table('house_unit')
                ->where('id_house_type', $house_type_id)
                ->orderBy("id_house", "desc")
                ->selectRaw("
                    COUNT(CASE WHEN active_flag = 'N' THEN 1 END) as jumlah_non_aktif,
                    COUNT(CASE WHEN active_flag = 'Y' AND stat != 'G' THEN 1 END) as jumlah_aktif,
                    MAX(id_house) as id_house,
                    COUNT(*) as jumlah_total
                ")
                ->first();

            // -- check jumlah pengurangan > jumlah aktif permanen
            // if ($request->house_qty < $getInfoHouse->jumlah_aktif) {
            //     return $this->sendError('Jumlah pengurangan harus lebih dari rumah yang sudah dibangun', 'Error', 500);
            // }

            DB::connection('sqlSrvFPI')->beginTransaction();
            DB::connection('sqlSrvFPI')
                ->table('house_type')
                ->where('id_house_type', $house_type_id)
                ->update([
                    'sbk_name' => $request->sbk_name ? $request->sbk_name : '',
                    'common_name' => $request->common_name ? $request->common_name : '',
                    'building_dimension' => $request->building_dimension ? $request->building_dimension : '',
                    'building_area' => $request->building_area ? $request->building_area : '',
                    'land_dimension' => $request->land_dimension ? $request->land_dimension : '',
                    'land_area' => $request->land_area ? $request->land_area : '',
                    'category' => $request->houses_category ? $request->houses_category : '',
                    'remark' => $request->remark ? $request->remark : '',
                    'date_modified' => now(),
                    'modified_by' => getAuth($request)->user_id2,
                    'id_site' => $request->site_id,
                    // 'house_qty' => $request->house_qty,
                    'map_color_rgb' => $request->map_color_rgb,
                    'map_color_name' => $request->map_color_name,
                ]);

            // -- perubahan house item
            // $houseItemCount = (int) $request->house_qty - (int) @$site->house_qty;

            // -- mengurangi house item
            // if ($houseItemCount < 0) {
            //     DB::connection("sqlSrvFPI")
            //         ->table('house_unit')
            //         ->whereIn('id_house', function ($query) use ($houseItemCount, $house_type_id) {
            //             $query->select('id_house')
            //                 ->from('house_unit')
            //                 ->where('id_house_type', $house_type_id)
            //                 ->where('active_flag', 'Y')
            //                 ->where('stat', 'G') // hanya stat=G yang dapat dinonaktifkan
            //                 ->orderBy('id_house', 'desc')
            //                 ->limit(abs($houseItemCount));
            //         })
            //         ->update([
            //             'active_flag' => 'N'
            //         ]);
            // }

            // -- menambah house item
            // if ($houseItemCount > 0) {
            //     DB::connection("sqlSrvFPI")
            //         ->table('house_unit')
            //         ->whereIn('id_house', function ($query) use ($houseItemCount, $house_type_id) {
            //             $query->select('id_house')
            //                 ->from('house_unit')
            //                 ->where('id_house_type', $house_type_id)
            //                 ->where('active_flag', 'N')
            //                 ->orderBy('id_house', 'asc')
            //                 ->limit(abs($houseItemCount));
            //         })
            //         ->update([
            //             'active_flag' => 'Y'
            //         ]);

            //     // -- jika voucher non aktif masih kurang
            //     $voucherAddCount = abs($houseItemCount) - $getInfoHouse->jumlah_non_aktif;

            //     for ($i = 1; $i <= $voucherAddCount; $i++) {
            //         $house_name = $house_category . ' ' . $i + $getInfoHouse->jumlah_total;
            //         $id_house = $this->generateHouseItemID($request);

            //         DB::connection('sqlSrvFPI')
            //             ->table('house_unit')
            //             ->insert([
            //                 'id_house' => $id_house,
            //                 'id_site' => $id_site,
            //                 'id_house_type' => $house_type_id,
            //                 'id_cluster' => '',
            //                 'house_name' => $house_name,
            //                 'stat' => 'G',
            //                 'date_created' => now(),
            //                 'created_by' => getAuth($request)->user_id2,
            //                 'date_modified' => '',
            //                 'modified_by' => '',
            //                 'date_build' => '',
            //                 'date_finish' => '',
            //                 'building_dimension' => $request->building_dimension ?? '',
            //                 'building_area' => $request->building_area ?? '',
            //                 'building_area_uom' => 'meter persegi',
            //                 'land_dimension' => $request->land_dimension ?? '',
            //                 'land_area' => $request->land_area ?? '',
            //             ]);
            //     }
            // }

            DB::connection('sqlSrvFPI')->commit();
            return $this->sendResponse('Success Update', 'Successfully update House Type');
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function deleteHouseType(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $site = DB::connection('sqlSrvFPI')
                ->table('house_type')
                ->where('id_house_type', $request->id_house_type)
                ->first();

            if ($site) {
                DB::connection('sqlSrvFPI')
                    ->table('house_type')
                    ->where('id_house_type', $request->id_house_type)
                    ->update([
                        'stat' => 'N',
                        'date_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);

                // -- house unit
                DB::connection('sqlSrvFPI')
                    ->table('house_unit')
                    ->where('id_house_type', $request->id_house_type)
                    ->update([
                        'active_flag' => 'N',
                        'date_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);


                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Delete', 'Successfully Delete');
            } else {
                return $this->sendError('No site tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function activateHouseType(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $site = DB::connection('sqlSrvFPI')
                ->table('house_type')
                ->where('id_house_type', $request->id_house_type)
                ->first();

            if ($site) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('house_type')
                    ->where('id_house_type', $request->id_house_type)
                    ->update([
                        'stat' => 'Y',
                        'date_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success activate', 'Successfully activate');
            } else {
                return $this->sendError('No cluster tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    //  ========== SITE ==========
    /**
     * @OA\Post(
     *     path="/api/fpi/site/getSite",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="id_province", type="string", example="ABC"),
     *             @OA\Property(property="id_prov_city", type="string", example="ABC"),
     *             @OA\Property(property="status", type="string", example="Aktif"),
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all site",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getSite(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $id_province = $request->id_province;
            $id_prov_city = $request->id_prov_city;
            $status = $request->status;

            if (!empty($id_province)) {
                $sqlWhere = $sqlWhere . "and province_list.province_code = " . "'" . $id_province . "'";
            }
            if (!empty($id_prov_city)) {
                $sqlWhere = $sqlWhere . "and city_list.city_code = " . "'" . $id_prov_city . "'";
            }
            if ($status == 'Non-aktif') {
                $sqlWhere = $sqlWhere . "and site.stat='N' ";
            }
            if ($status == 'Aktif') {
                $sqlWhere = $sqlWhere . "and site.stat='Y' ";
            }
            if (empty($status)) {
                $sqlWhere = $sqlWhere . "and site.stat='Y' ";
            }

            // dd($sqlWhere);
            $datas = DB::connection("sqlSrvFPI")
                ->table('site')
                ->selectRaw("
                                            site.*, 
                                            city_list.city as city_name, 
                                            province_list.province,
                                            city_list.city_code as id_prov_city, 
                                            province_list.province_code as id_province,
                                            district_list.district as district_name,
                                            village_list.village as village_name
                                ")
                ->leftjoin('city_list', 'site.city', '=', 'city_list.city_code')
                ->leftjoin('province_list', 'city_list.province_code', '=', 'province_list.province_code')
                ->leftjoin('district_list', 'site.kecamatan', '=', 'district_list.district_code')
                ->leftjoin('village_list', 'site.kelurahan_desa', '=', 'village_list.village_code')
                // ->leftjoin('commodity', function ($join) {
                //     $join->on('article.com_id', '=', 'commodity.com_id')
                //          ->on('category.cat_id', '=', 'commodity.cat_id');
                // })
                ->whereRaw($sqlWhere)
                ->get();
            return $this->sendResponse($datas, 'Successfully get Site');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function getAllSite(Request $request)
    {
        try {
            $sqlWhere = "1=1";
            $status = $request->status;


            if ($status == 'Aktif') {
                $sqlWhere = $sqlWhere . "and site.stat='Y' ";
            }

            // dd($sqlWhere);
            $datas = DB::connection("sqlSrvFPI")
                ->table('site')
                ->selectRaw("
                    site.*, 
                    city_list.city as city_name, 
                    province_list.province,
                    city_list.city_code as id_prov_city, 
                    province_list.province_code as id_province,
                    district_list.district as district_name,
                    village_list.village as village_name
                ")
                ->whereRaw($sqlWhere)
                ->get();
            return $this->sendResponse($datas, 'Successfully get Site');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getDetailSite(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $id_site = $request->id_site;

            if (!empty($id_site)) {
                $sqlWhere = $sqlWhere . "and site.id_site = " . "'" . $id_site . "'";
            }

            // dd($sqlWhere);
            $datas = DB::connection("sqlSrvFPI")
                ->table('site')
                ->selectRaw("
                                            site.*,
                                            province_list.province_code as id_province,
                                            city_list.city_code as id_prov_city,
                                            district_list.district as district_name,
                                            village_list.village as village_name
                                            ")
                ->leftjoin('city_list', 'site.city', '=', 'city_list.city_code')
                ->leftjoin('province_list', 'city_list.province_code', '=', 'province_list.province_code')
                ->leftjoin('district_list', 'site.kecamatan', '=', 'district_list.district_code')
                ->leftjoin('village_list', 'site.kelurahan_desa', '=', 'village_list.village_code')
                // ->leftjoin('commodity', function ($join) {
                //     $join->on('article.com_id', '=', 'commodity.com_id')
                //          ->on('category.cat_id', '=', 'commodity.cat_id');
                // })
                ->whereRaw($sqlWhere)
                ->get();
            return $this->sendResponse($datas, 'Successfully get Site');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function storeSite(Request $request)
    {

        DB::connection('sqlSrvFPI')->beginTransaction();

        try {
            $site_id = $this->generateSiteID($request);

            // dd($site_id);
            $save = DB::connection('sqlSrvFPI')
                ->table('site')
                ->insert([
                    'id_site' => $site_id,
                    'site_name' => $request->site_name,
                    'site_address' => $request->site_address,
                    'kelurahan_desa' => $request->kelurahan ? $request->kelurahan : '',
                    'kecamatan' => $request->kecamatan ? $request->kecamatan : '',
                    'city' => $request->id_prov_city,
                    'postal_code' => $request->postal_code ? $request->postal_code : '',
                    'phone1' => $request->phone1 ? $request->phone1 : '',
                    'phone2' => $request->phone2 ? $request->phone2 : '',
                    'date_created' => now(),
                    'date_modified' => '',
                    'created_by' => getAuth($request)->user_id2,
                    'modified_by' => '',
                    'remark' => $request->remark ? $request->remark : '',
                    'email' => $request->email ? $request->email : '',
                    'sku' => $request->sku ? $request->sku : '',
                    'ka_site' => '',
                    'stat' => 'Y',
                    'dpp_percentage' => $request->dpp_percentage ? $request->dpp_percentage : 0
                ]);
            DB::connection('sqlSrvFPI')->commit();


            return $this->sendResponse($save, 'Successfully saved Site');
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function generateSiteID($request)
    {
        // $dateYear = substr( date("Y"), 0);
        $dateYear = date("Y");
        $dateMonth = date("m");
        $dateDay = date("d");
        $gen_site_id = '';

        $getLastTrID = DB::connection('sqlSrvFPI')
            ->table('site')
            ->where('id_site', 'like', "%" . $dateYear . $dateMonth . $dateDay . "%")
            ->max('id_site');

        if ($getLastTrID) {
            $seq = substr(trim($getLastTrID), -3) + 1;
            $gen_site_id = $dateYear . $dateMonth . $dateDay . str_pad($seq, 3, '0', STR_PAD_LEFT);
        } else {
            $gen_site_id = $dateYear . $dateMonth . $dateDay . '001';
        }

        return $gen_site_id;
    }

    public function updateSite(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();


        try {

            $site = DB::connection('sqlSrvFPI')
                ->table('site')
                ->where('id_site', $request->id_site)
                ->first();

            if ($site) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('site')
                    ->where('id_site', $request->id_site)
                    ->update([
                        'site_name' => $request->site_name,
                        'site_address' => $request->site_address,
                        'kelurahan_desa' => $request->kelurahan ? $request->kelurahan : '',
                        'kecamatan' => $request->kecamatan ? $request->kecamatan : '',
                        'city' => $request->id_prov_city,
                        'postal_code' => $request->postal_code ? $request->postal_code : '',
                        'phone1' => $request->phone1 ? $request->phone1 : '',
                        'phone2' => $request->phone2 ? $request->phone2 : '',
                        'date_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                        'remark' => $request->remark ? $request->remark : '',
                        'email' => $request->email ? $request->email : '',
                        'sku' => $request->sku ? $request->sku : '',
                        'dpp_percentage' => $request->dpp_percentage ? $request->dpp_percentage : 0
                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Update', 'Successfully saved');
            } else {
                return $this->sendError('No site tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function deleteSite(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $site = DB::connection('sqlSrvFPI')
                ->table('site')
                ->where('id_site', $request->id_site)
                ->first();

            if ($site) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('site')
                    ->where('id_site', $request->id_site)
                    ->update([
                        'stat' => 'N',
                        'date_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Delete', 'Successfully Delete');
            } else {
                return $this->sendError('No site tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function activateSite(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $site = DB::connection('sqlSrvFPI')
                ->table('site')
                ->where('id_site', $request->id_site)
                ->first();

            if ($site) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('site')
                    ->where('id_site', $request->id_site)
                    ->update([
                        'stat' => 'Y',
                        'date_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success activate', 'Successfully activate');
            } else {
                return $this->sendError('No site tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    //  ========== CLUSTER ==========
    /**
     * @OA\Post(
     *     path="/api/fpi/cluster/getCluster",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="id_province", type="string", example="ABC"),
     *             @OA\Property(property="id_prov_city", type="string", example="ABC"),
     *             @OA\Property(property="id_site", type="string", example="ABC"),
     *             @OA\Property(property="category", type="string", example="Rumah"),
     *             @OA\Property(property="status", type="string", example="Aktif"),
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all site",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getCluster(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $id_province = $request->id_province;
            $id_prov_city = $request->id_prov_city;
            $id_site = $request->id_site;
            $category = $request->category;
            $status = $request->status;

            if (!empty($id_province)) {
                $sqlWhere = $sqlWhere . "and province_list.province_code = " . "'" . $id_province . "'";
            }
            if (!empty($id_prov_city)) {
                $sqlWhere = $sqlWhere . "and city_list.city_code = " . "'" . $id_prov_city . "'";
            }
            if (!empty($id_site)) {
                $sqlWhere = $sqlWhere . "and site.id_site = " . "'" . $id_site . "'";
            }
            if (!empty($category)) {
                $sqlWhere = $sqlWhere . "and house_type.category = " . "'" . $category . "'";
            }
            if ($status == 'Non-aktif') {
                $sqlWhere = $sqlWhere . "and site_cluster.stat='N' ";
            }
            if ($status == 'Aktif') {
                $sqlWhere = $sqlWhere . "and site_cluster.stat='Y' ";
            }
            if (empty($status)) {
                $sqlWhere = $sqlWhere . "and site_cluster.stat='Y' ";
            }
            // dd($sqlWhere);
            $datas = DB::connection("sqlSrvFPI")
                ->table('site_cluster')
                ->selectRaw("
                                            site_cluster.*, 
                                            site.site_name, 
                                            site.site_address, 
                                            site.kelurahan_desa, 
                                            site.kecamatan, 
                                            site.postal_code, 
                                            site.phone1, 
                                            site.phone2, 
                                            site.email, 
                                            site.ka_site, 
                                            site.city as id_prov_city, 
                                            city_list.city as city_name, 
                                            province_list.province_code as id_province,
                                            province_list.province as province_name, 
                                            CONCAT(house_type.sbk_name, ' / ' ,house_type.common_name ) as house_name,
                                            house_type.building_area, 
                                            house_type.land_area,
                                            house_type.category
                                            ")
                ->leftjoin('site', 'site_cluster.id_site', '=', 'site.id_site')
                ->leftjoin('city_list', 'site.city', '=', 'city_list.city_code')
                ->leftjoin('province_list', 'city_list.province_code', '=', 'province_list.province_code')
                ->leftjoin('house_type', 'site_cluster.house_type', '=', 'house_type.id_house_type')
                // ->leftjoin('commodity', function ($join) {
                //     $join->on('article.com_id', '=', 'commodity.com_id')
                //          ->on('category.cat_id', '=', 'commodity.cat_id');
                // })
                ->whereRaw($sqlWhere)
                ->get();
            return $this->sendResponse($datas, 'Successfully get Cluster');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function storeCluster(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {
            // $house_qty = $request->house_qty;
            $cluster_id = $this->generateClusterID($request);

            // Proses gambar
            $image = $request->file('img_cluster');
            if ($image) {
                $directory = 'fpis/'; // Direktori penyimpanan

                $filename = Str::random(40) . '.jpg'; // Nama file original (100% kualitas)
                $filename2 = Str::random(40) . '.jpg'; // Nama file hasil compress
                $path = $directory . $filename;
                $path2 = $directory . $filename2;

                // Gambar kualitas asli 100%
                $medium = Image::make($image)->encode('jpg', 100);

                // Kompres gambar
                $quality = 90;
                $maxSize = 300 * 1024;

                // Resize untuk efisiensi (opsional tapi direkomendasikan)
                $imageInstance = Image::make($image);
                $imageInstance->resize(1280, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

                do {
                    $medium2 = (clone $imageInstance)->encode('jpg', $quality);
                    $imageSize = strlen((string) $medium2);
                    $quality -= 5;
                } while ($imageSize > $maxSize && $quality > 10);

                // Simpan kedua gambar ke storage
                Storage::disk('public')->put($path, (string) $medium);   // Original
                Storage::disk('public')->put($path2, (string) $medium2); // Compressed
            } else {
                $path = '';
                $path2 = '';
            }


            $save = DB::connection('sqlSrvFPI')
                ->table('site_cluster')
                ->insert([
                    'id_cluster' => $cluster_id,
                    'id_site' => $request->id_site,
                    'cluster_name' => $request->cluster_name,
                    'date_created' => now(),
                    'created_by' => getAuth($request)->user_id2,
                    'date_modified' => '',
                    'modified_by' => '',
                    'house_type' => $request->id_house_type ? $request->id_house_type : '',
                    'house_qty' => $request->house_qty ? $request->house_qty : '',
                    'remark' => $request->remark ? $request->remark : '',
                    'sku' => $request->sku ? $request->sku : '',
                    'stat' => 'Y',
                    'img_cluster' => $path,
                    'img_cluster_tmb' => $path2
                ]);

            // --  dipindah di house type
            // $getCategory =  DB::connection('sqlSrvFPI')
            //     ->table('house_type')
            //     ->where('id_house_type', 'like', $request->id_house_type)
            //     ->max('category');

            // $x = 1;
            // while ($x <= $house_qty) {
            //     $house_name = $getCategory . ' ' . $x;

            //     $id_house = $this->generateHouseItemID($request);

            //     $saveHI = DB::connection('sqlSrvFPI')
            //         ->table('house_unit')
            //         ->insert([
            //             'id_house' => $id_house,
            //             'id_cluster' => $cluster_id,
            //             'house_name' => $house_name,
            //             'stat' => 'Ground',
            //             'date_created' => now(),
            //             'created_by' => getAuth($request)->user_id2,
            //             'date_modified' => '',
            //             'modified_by' => '',
            //             'date_build' => '',
            //             'date_finish' => '',
            //             'date_sold' => ''
            //         ]);
            //     $x++;
            // }
            // -- end dipindah di house type

            DB::connection('sqlSrvFPI')->commit();
            return $this->sendResponse('Success', 'Successfully saved Site');
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function generateClusterID($request)
    {
        // $dateYear = substr( date("Y"), 0);
        $dateYear = date("Y");
        $dateMonth = date("m");
        // $dateDay = date("d");
        $gen_cluster_id = '';

        $getLastTrID = DB::connection('sqlSrvFPI')
            ->table('site_cluster')
            ->where('id_cluster', 'like', "%" . $dateYear . "%")
            ->max('id_cluster');

        if ($getLastTrID) {
            $month = substr(trim($getLastTrID), 5, 2);

            // dd($dateMonth.'-'.$month);

            if ($month != $dateMonth) {
                $gen_cluster_id = 'C' . $dateYear . $dateMonth . '001';
            } else {
                $seq = substr(trim($getLastTrID), -3) + 1;
                $gen_cluster_id = 'C' . $dateYear . $dateMonth . str_pad($seq, 3, '0', STR_PAD_LEFT);
            }
        } else {
            $gen_cluster_id = 'C' . $dateYear . $dateMonth . '001';
        }
        // dd($gen_cluster_id);
        return $gen_cluster_id;
    }

    public function getDetailCluster(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $id_cluster = $request->id_cluster;

            if (!empty($id_cluster)) {
                $sqlWhere = $sqlWhere . "and site_cluster.id_cluster = " . "'" . $id_cluster . "'";

                // dd($sqlWhere);
                $datas = DB::connection("sqlSrvFPI")
                    ->table('site_cluster')
                    ->selectRaw("
                                            site_cluster.*, 
                                            site.site_name, 
                                            city_list.city, 
                                            province_list.province, 
                                            site.kelurahan_desa,
                                            village_list.village as village_name, 
                                            site.kecamatan, 
                                            district_list.district as district_name,
                                            site.site_address, 
                                            site.city,
                                            city_list.city as city_name,
                                            site.postal_code, 
                                            site.phone1, 
                                            site.phone2, 
                                            city_list.province_code,
                                            house_type.sbk_name, 
                                            house_type.common_name, 
                                            house_type.building_dimension, 
                                            house_type.building_area, 
                                            house_type.building_area_uom, 
                                            house_type.land_dimension, 
                                            house_type.land_area, 
                                            house_type.land_area_uom, 
                                            house_type.category
                                            ")
                    ->leftjoin('site', 'site_cluster.id_site', '=', 'site.id_site')
                    ->leftjoin('city_list', 'site.city', '=', 'city_list.city_code')
                    ->leftjoin('province_list', 'city_list.province_code', '=', 'province_list.province_code')
                    ->leftjoin('house_type', 'site_cluster.house_type', '=', 'house_type.id_house_type')
                    ->leftjoin('district_list', 'site.kecamatan', '=', 'district_list.district_code')
                    ->leftjoin('village_list', 'site.kelurahan_desa', '=', 'village_list.village_code')
                    // ->leftjoin('commodity', function ($join) {
                    //     $join->on('article.com_id', '=', 'commodity.com_id')
                    //          ->on('category.cat_id', '=', 'commodity.cat_id');
                    // })
                    ->whereRaw($sqlWhere)
                    ->get();

                return $this->sendResponse($datas, 'Successfully get Site');
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function updateCluster(Request $request)
    {
        try {
            $id_site = $request->id_site;
            $id_house_type = $request->id_house_type;
            $id_cluster = $request->id_cluster;

            $site = DB::connection('sqlSrvFPI')
                ->table('site_cluster')
                ->where('id_cluster', $id_cluster)
                ->first();

            // $getCategory =  DB::connection('sqlSrvFPI')
            //     ->table('house_type')
            //     ->where('id_house_type', 'like', $request->id_house_type)
            //     ->max('category');

            // -- dipindah pada house type
            //
            // // -- get jumlah house item before, jumlah status Y/N
            // $getInfoHouse = DB::connection("sqlSrvFPI")
            //     ->table('house_unit')
            //     ->where('id_cluster', $id_cluster)
            //     ->orderBy("id_house", "desc")
            //     ->selectRaw("
            //         COUNT(CASE WHEN active_flag = 'N' THEN 1 END) as jumlah_non_aktif,
            //         COUNT(CASE WHEN active_flag = 'Y' AND stat != 'Ground' THEN 1 END) as jumlah_aktif,
            //         MAX(id_house) as id_house,
            //         COUNT(*) as jumlah_total
            //     ")
            //     ->first();

            // // -- check jumlah pengurangan > jumlah aktif permanen
            // if ($request->house_qty < $getInfoHouse->jumlah_aktif) {
            //     return $this->sendError('Jumlah pengurangan harus lebih dari rumah yang sudah dibangun', 'Error', 500);
            // }
            //
            // -- end dipindah pada house type

            DB::connection('sqlSrvFPI')->beginTransaction();

            if ($site) {
                // Proses gambar
                $image = $request->file('img_cluster');
                if ($image) {
                    $directory = 'fpis/'; // Direktori penyimpanan
                    // Generate nama file
                    $filename = Str::random(40) . '.jpg'; // Generate nama file
                    $filename2 = Str::random(40) . '.jpg'; // Generate nama file
                    $path = $directory . $filename; // Path lengkap untuk penyimpanan
                    $path2 = $directory . $filename2; // Path lengkap untuk penyimpanan

                    // Gambar Asli 100%
                    $medium = Image::make($image)->encode('jpg', 100);

                    // Kompres gambar
                    $quality = 90;
                    $maxSize = 300 * 1024;

                    // Resize untuk efisiensi (opsional tapi direkomendasikan)
                    $imageInstance = Image::make($image);
                    $imageInstance->resize(1280, null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });

                    do {
                        $medium2 = (clone $imageInstance)->encode('jpg', $quality);
                        $imageSize = strlen((string) $medium2);
                        $quality -= 5;
                    } while ($imageSize > $maxSize && $quality > 10);

                    if ($site->img_cluster) {
                        Storage::disk('public')->delete($site->img_cluster);
                    }
                    if ($site->img_cluster_tmb) {
                        Storage::disk('public')->delete($site->img_cluster_tmb);
                    }
                    // Simpan gambar ke penyimpanan (storage/public/fpis/)
                    Storage::disk('public')->put($path, $medium->__toString());
                    Storage::disk('public')->put($path2, $medium2->__toString());
                } else {
                    $path = ''; // Jika tidak ada gambar, set null
                    $path2 = ''; // Jika tidak ada gambar, set null
                }

                if (!empty($id_site) && !empty($id_house_type)) {
                    DB::connection('sqlSrvFPI')
                        ->table('site_cluster')
                        ->where('id_cluster', $request->id_cluster)
                        ->update([
                            'id_site' => $request->id_site,
                            'cluster_name' => $request->cluster_name,
                            'house_qty' => $request->house_qty,
                            'house_type' => $request->id_house_type,
                            'remark' => $request->remark ? $request->remark : '',
                            'sku' => $request->sku ? $request->sku : '',
                            'date_modified' => now(),
                            'modified_by' => getAuth($request)->user_id2,
                            'img_cluster' => $path,
                            'img_cluster_tmb' => $path2
                        ]);
                } elseif (!empty($id_site) && empty($id_house_type)) {
                    DB::connection('sqlSrvFPI')
                        ->table('site_cluster')
                        ->where('id_cluster', $request->id_cluster)
                        ->update([
                            'id_site' => $request->id_site,
                            'cluster_name' => $request->cluster_name,
                            'house_qty' => $request->house_qty,
                            'remark' => $request->remark ? $request->remark : '',
                            'sku' => $request->sku ? $request->sku : '',
                            'date_modified' => now(),
                            'modified_by' => getAuth($request)->user_id2,
                            'img_cluster' => $path,
                            'img_cluster_tmb' => $path2
                        ]);
                } elseif (empty($id_site) && !empty($id_house_type)) {
                    DB::connection('sqlSrvFPI')
                        ->table('site_cluster')
                        ->where('id_cluster', $request->id_cluster)
                        ->update([
                            'cluster_name' => $request->cluster_name,
                            'house_qty' => $request->house_qty,
                            'house_type' => $request->id_house_type,
                            'remark' => $request->remark ? $request->remark : '',
                            'sku' => $request->sku ? $request->sku : '',
                            'date_modified' => now(),
                            'modified_by' => getAuth($request)->user_id2,
                            'img_cluster' => $path,
                            'img_cluster_tmb' => $path2
                        ]);
                } elseif (empty($id_site) && empty($id_house_type)) {
                    DB::connection('sqlSrvFPI')
                        ->table('site_cluster')
                        ->where('id_cluster', $request->id_cluster)
                        ->update([
                            'cluster_name' => $request->cluster_name,
                            'house_qty' => $request->house_qty,
                            'remark' => $request->remark ? $request->remark : '',
                            'sku' => $request->sku ? $request->sku : '',
                            'date_modified' => now(),
                            'modified_by' => getAuth($request)->user_id2,
                            'img_cluster' => $path,
                            'img_cluster_tmb' => $path2
                        ]);
                }

                // -- dipindah pada house type
                //
                // // -- perubahan house item
                // $houseItemCount = (int) $request->house_qty - (int) @$site->house_qty;

                // // -- mengurangi house item
                // if ($houseItemCount < 0) {
                //     DB::connection("sqlSrvFPI")
                //         ->table('house_unit')
                //         ->whereIn('id_house', function ($query) use ($houseItemCount, $id_cluster) {
                //             $query->select('id_house')
                //                 ->from('house_unit')
                //                 ->where('id_cluster', $id_cluster)
                //                 ->where('active_flag', 'Y')
                //                 ->where('stat', 'Ground') // hanya stat=Ground yang dapat dinonaktifkan
                //                 ->orderBy('id_house', 'desc')
                //                 ->limit(abs($houseItemCount));
                //         })
                //         ->update([
                //             'active_flag' => 'N'
                //         ]);
                // }

                // // -- menambah house item
                // if ($houseItemCount > 0) {
                //     DB::connection("sqlSrvFPI")
                //         ->table('house_unit')
                //         ->whereIn('id_house', function ($query) use ($houseItemCount, $id_cluster) {
                //             $query->select('id_house')
                //                 ->from('house_unit')
                //                 ->where('id_cluster', $id_cluster)
                //                 ->where('active_flag', 'N')
                //                 ->orderBy('id_house', 'asc')
                //                 ->limit(abs($houseItemCount));
                //         })
                //         ->update([
                //             'active_flag' => 'Y'
                //         ]);

                //     // -- jika voucher non aktif masih kurang
                //     $voucherAddCount = abs($houseItemCount) - $getInfoHouse->jumlah_non_aktif;

                //     for ($i = 1; $i <= $voucherAddCount; $i++) {
                //         $house_name = $getCategory . ' ' . $i + $getInfoHouse->jumlah_total;
                //         $id_house = $this->generateHouseItemID($request);

                //         DB::connection('sqlSrvFPI')
                //             ->table('house_unit')
                //             ->insert([
                //                 'id_house' => $id_house,
                //                 'id_cluster' => $id_cluster,
                //                 'house_name' => $house_name,
                //                 'stat' => 'Ground',
                //                 'date_created' => now(),
                //                 'created_by' => getAuth($request)->user_id2,
                //                 'date_modified' => '',
                //                 'modified_by' => '',
                //                 'date_build' => '',
                //                 'date_finish' => '',
                //                 'date_sold' => ''
                //             ]);
                //     }
                // }
                //
                // -- end dipindah pada house type

                DB::connection('sqlSrvFPI')->commit();
                return $this->sendResponse('Success Update', 'Successfully saved');
            } else {
                return $this->sendError('No site tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function deleteCluster(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $site = DB::connection('sqlSrvFPI')
                ->table('site_cluster')
                ->where('id_cluster', $request->id_cluster)
                ->first();

            if ($site) {
                DB::connection('sqlSrvFPI')
                    ->table('site_cluster')
                    ->where('id_cluster', $request->id_cluster)
                    ->update([
                        'stat' => 'N',
                        'date_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);

                DB::connection('sqlSrvFPI')
                    ->table('house_unit')
                    ->where('id_cluster', $request->id_cluster)
                    ->update([
                        'active_flag' => 'N',
                        'date_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);

                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Delete', 'Successfully Delete');
            } else {
                return $this->sendError('No site tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function activateCluster(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $site = DB::connection('sqlSrvFPI')
                ->table('site_cluster')
                ->where('id_cluster', $request->id_cluster)
                ->first();

            if ($site) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('site_cluster')
                    ->where('id_cluster', $request->id_cluster)
                    ->update([
                        'stat' => 'Y',
                        'date_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success activate', 'Successfully activate');
            } else {
                return $this->sendError('No cluster tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    //  ========== CONTRACTOR ==========
    /**
     * @OA\Post(
     *     path="/api/fpi/contractor/getContractor",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="id_province", type="string", example="ABC"),
     *             @OA\Property(property="id_prov_city", type="string", example="ABC"),
     *             @OA\Property(property="status", type="string", example="Aktif"),
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all contractor",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getContractor(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $id_province = $request->id_province;
            $id_prov_city = $request->id_prov_city;
            $status = $request->status;

            if (!empty($id_province)) {
                $sqlWhere = $sqlWhere . "and province_list.province_code = " . "'" . $id_province . "'";
            }
            if (!empty($id_prov_city)) {
                $sqlWhere = $sqlWhere . "and city_list.city_code = " . "'" . $id_prov_city . "'";
            }
            if ($status == 'Non-aktif') {
                $sqlWhere = $sqlWhere . "and contractor.stat='N' ";
            }
            if ($status == 'Aktif') {
                $sqlWhere = $sqlWhere . "and contractor.stat='Y' ";
            }
            if (empty($status)) {
                $sqlWhere = $sqlWhere . "and contractor.stat='Y' ";
            }
            // dd($sqlWhere);
            $datas = DB::connection("sqlSrvFPI")
                ->table('contractor')
                ->selectRaw("
                                            contractor.id_contractor, 
                                            contractor.contractor_name, 
                                            contractor.contractor_address, 
                                            contractor.phone1, 
                                            contractor.phone2, 
                                            contractor.city, 
                                            contractor.npwp, 
                                            contractor.nib, 
                                            contractor.email, 
                                            contractor.stat, 
                                            contractor.remark, 
                                            city_list.city, 
                                            province_list.province
                                            ")
                ->leftjoin('city_list', 'contractor.city', '=', 'city_list.city_code')
                ->leftjoin('province_list', 'city_list.province_code', '=', 'province_list.province_code')
                // ->leftjoin('commodity', function ($join) {
                //     $join->on('article.com_id', '=', 'commodity.com_id')
                //          ->on('category.cat_id', '=', 'commodity.cat_id');
                // })
                ->whereRaw($sqlWhere)
                ->get();
            return $this->sendResponse($datas, 'Successfully get Cluster');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function storeContractor(Request $request)
    {

        DB::connection('sqlSrvFPI')->beginTransaction();

        try {
            $gen_contractor_id = $this->generateContractorID($request);
            // dd($gen_contractor_id);

            $save = DB::connection('sqlSrvFPI')
                ->table('contractor')
                ->insert([
                    'id_contractor' => $gen_contractor_id,
                    'contractor_name' => $request->contractor_name ? $request->contractor_name : '',
                    'contractor_address' => $request->contractor_address ? $request->contractor_address : '',
                    'email' => $request->contractor_email ? $request->contractor_email : '',
                    'phone1' => $request->contractor_phone1 ? $request->contractor_phone1 : '',
                    'phone2' => $request->contractor_phone2 ? $request->contractor_phone2 : '',
                    'city' => $request->id_prov_city ? $request->id_prov_city : '',
                    'npwp' => $request->contractor_npwp ? $request->contractor_npwp : '',
                    'nib' => $request->contractor_nib ? $request->contractor_nib : '',
                    'date_created' => now(),
                    'created_by' => getAuth($request)->user_id2,
                    'date_modified' => '',
                    'modified_by' => '',
                    'stat' => 'Y',
                    'remark' => $request->remark ? $request->remark : ''

                ]);
            DB::connection('sqlSrvFPI')->commit();

            // dd($save);
            // return $this->sendResponse('Success', 'Successfully saved Site');
            return $this->sendResponse($save, 'Successfully saved Contractor');
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function generateContractorID($request)
    {
        // $dateYear = substr( date("Y"), 0);
        $dateYear = date("Y");
        $dateMonth = date("m");
        // $dateDay = date("d");
        $gen_contractor_id = '';

        $getLastTrID = DB::connection('sqlSrvFPI')
            ->table('contractor')
            ->where('id_contractor', 'like', "%" . $dateYear . "%")
            ->max('id_contractor');

        if ($getLastTrID) {
            $year = substr(trim($getLastTrID), 1, 4);

            // dd($dateYear.'-'.$year);

            if ($year != $dateYear) {
                $gen_contractor_id = 'C' . $dateYear . '001';
            } else {
                $seq = substr(trim($getLastTrID), -3) + 1;
                $gen_contractor_id = 'C' . $dateYear . str_pad($seq, 3, '0', STR_PAD_LEFT);
            }
        } else {
            $gen_contractor_id = 'C' . $dateYear . '001';
        }
        // dd($gen_cluster_id);
        return $gen_contractor_id;
    }

    public function getDetailContractor(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $id_contractor = $request->id_contractor;

            if (!empty($id_contractor)) {
                $sqlWhere = $sqlWhere . "and contractor.id_contractor = " . "'" . $id_contractor . "'";

                // dd($sqlWhere);
                $datas = DB::connection("sqlSrvFPI")
                    ->table('contractor')
                    ->selectRaw("
                                            contractor.id_contractor, 
                                            contractor.contractor_name, 
                                            contractor.contractor_address, 
                                            contractor.phone1, 
                                            contractor.phone2, 
                                            contractor.city, 
                                            contractor.npwp, 
                                            contractor.nib, 
                                            contractor.email, 
                                            contractor.stat, 
                                            contractor.remark, 
                                            city_list.city, 
                                            province_list.province
                                            ")
                    ->leftjoin('city_list', 'contractor.city', '=', 'city_list.city_code')
                    ->leftjoin('province_list', 'city_list.province_code', '=', 'province_list.province_code')
                    // ->leftjoin('commodity', function ($join) {
                    //     $join->on('article.com_id', '=', 'commodity.com_id')
                    //          ->on('category.cat_id', '=', 'commodity.cat_id');
                    // })
                    ->whereRaw($sqlWhere)
                    ->get();

                return $this->sendResponse($datas, 'Successfully get Detail Contractor');
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function updateContractor(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {
            $sqlUpdate = '';
            $id_contractor = $request->id_contractor;
            $id_prov_city = $request->id_prov_city;

            $contractor = DB::connection('sqlSrvFPI')
                ->table('contractor')
                ->where('id_contractor', $request->id_contractor)
                ->first();

            if ($contractor) {
                // dd($contractor);

                if (!empty($id_prov_city)) {
                    $save = DB::connection('sqlSrvFPI')
                        ->table('contractor')
                        ->where('id_contractor', $request->id_contractor)
                        ->update([
                            'contractor_name' => $request->contractor_name ? $request->contractor_name : '',
                            'contractor_address' => $request->contractor_address ? $request->contractor_address : '',
                            'city' => $request->id_prov_city ? $request->id_prov_city : '',
                            'email' => $request->contractor_email ? $request->contractor_email : '',
                            'phone1' => $request->contractor_phone1 ? $request->contractor_phone1 : '',
                            'phone2' => $request->contractor_phone2 ? $request->contractor_phone2 : '',
                            'npwp' => $request->npwp ? $request->npwp : '',
                            'nib' => $request->nib ? $request->nib : '',
                            'remark' => $request->remark ? $request->remark : '',
                            'date_modified' => now(),
                            'modified_by' => getAuth($request)->user_id2,
                        ]);
                    DB::connection('sqlSrvFPI')->commit();

                    return $this->sendResponse('Success Update', 'Successfully saved');
                } elseif (empty($id_prov_city)) {
                    $save = DB::connection('sqlSrvFPI')
                        ->table('contractor')
                        ->where('id_contractor', $request->id_contractor)
                        ->update([
                            'contractor_name' => $request->contractor_name ? $request->contractor_name : '',
                            'contractor_address' => $request->contractor_address ? $request->contractor_address : '',
                            'email' => $request->contractor_email ? $request->contractor_email : '',
                            'phone1' => $request->contractor_phone1 ? $request->contractor_phone1 : '',
                            'phone2' => $request->contractor_phone2 ? $request->contractor_phone2 : '',
                            'npwp' => $request->npwp ? $request->npwp : '',
                            'nib' => $request->nib ? $request->nib : '',
                            'remark' => $request->remark ? $request->remark : '',
                            'date_modified' => now(),
                            'modified_by' => getAuth($request)->user_id2,
                        ]);
                    DB::connection('sqlSrvFPI')->commit();

                    return $this->sendResponse('Success Update', 'Successfully saved');
                }
            } else {
                return $this->sendError('No site tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function deleteContractor(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $site = DB::connection('sqlSrvFPI')
                ->table('contractor')
                ->where('id_contractor', $request->id_contractor)
                ->first();

            if ($site) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('contractor')
                    ->where('id_contractor', $request->id_contractor)
                    ->update([
                        'stat' => 'N',
                        'date_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Delete', 'Successfully Delete');
            } else {
                return $this->sendError('No contractor tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function activateContractor(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $site = DB::connection('sqlSrvFPI')
                ->table('contractor')
                ->where('id_contractor', $request->id_contractor)
                ->first();

            if ($site) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('contractor')
                    ->where('id_contractor', $request->id_contractor)
                    ->update([
                        'stat' => 'Y',
                        'date_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success activate', 'Successfully activate');
            } else {
                return $this->sendError('No contractor tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    //  ========== HOUSE ITEM ==========
    /**
     * @OA\Post(
     *     path="/api/fpi/houseUnit/getHouseUnit",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="id_province", type="string", example="ABC"),
     *             @OA\Property(property="id_prov_city", type="string", example="ABC"),
     *             @OA\Property(property="id_site", type="string", example="ABC"),
     *             @OA\Property(property="category", type="string", example="ABC"),
     *            @OA\Property(property="id_house", type="string", example="ABC"),
     *            @OA\Property(property="id_cluster", type="string", example="ABC"),
     *            @OA\Property(property="id_house_type", type="string", example="ABC"),
     *            @OA\Property(property="status", type="string", example="Aktif"),
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all house unit",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getHouseUnit(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $id_province = $request->id_province;
            $id_prov_city = $request->id_prov_city;
            $id_site = $request->id_site;
            $category = $request->category;
            $id_house = $request->id_house;
            $id_cluster = $request->id_cluster;
            $id_house_type = $request->id_house_type;
            $status = $request->status;

            if (!empty($id_province)) {
                $sqlWhere = $sqlWhere . "and province_list.province_code = " . "'" . $id_province . "'";
            }
            if (!empty($id_prov_city)) {
                $sqlWhere = $sqlWhere . "and city_list.city_code = " . "'" . $id_prov_city . "'";
            }
            if (!empty($id_site)) {
                $sqlWhere = $sqlWhere . "and site.id_site = " . "'" . $id_site . "'";
            }
            if (!empty($category)) {
                $sqlWhere = $sqlWhere . "and house_type.category = " . "'" . $category . "'";
            }
            if (!empty($id_house)) {
                $sqlWhere = $sqlWhere . "and house_unit.id_house = " . "'" . $id_house . "'";
            }
            if (!empty($id_cluster)) {
                $sqlWhere = $sqlWhere . "and house_unit.id_cluster = " . "'" . $id_cluster . "'";
            }
            if (!empty($id_house_type)) {
                $sqlWhere = $sqlWhere . "and house_unit.id_house_type = " . "'" . $id_house_type . "'";
            }

            $datas = DB::connection("sqlSrvFPI")
                ->table('house_unit')
                ->selectRaw("
                            house_unit.*, 
                            site_cluster.cluster_name, 
                            site.site_name, 
                            site.site_address, 
                            site.kelurahan_desa, 
                            site.kecamatan, 
                            house_type.sbk_name, 
                            house_type.common_name,  
                            house_type.category, 
                            city_list.city, 
                            province_list.province,
                            district_list.district,
                            village_list.village,
                            house_type.map_color_rgb,
                            apl_const_sold.str2 as sold_stat_name,
                            apl_const_stat.str2 as stat_name
                        ")
                ->leftjoin('site_cluster', 'house_unit.id_cluster', '=', 'site_cluster.id_cluster')
                ->leftjoin('site', 'house_unit.id_site', '=', 'site.id_site')
                ->leftjoin('city_list', 'site.city', '=', 'city_list.city_code')
                ->leftjoin('province_list', 'city_list.province_code', '=', 'province_list.province_code')
                ->leftjoin('house_type', 'house_unit.id_house_type', '=', 'house_type.id_house_type')
                ->leftjoin('district_list', 'site.kecamatan', '=', 'district_list.district_code')
                ->leftjoin('village_list', 'site.kelurahan_desa', '=', 'village_list.village_code')
                ->leftJoin('appl_constant as apl_const_sold', function ($join) {
                    $join->on('house_unit.sold_stat', '=', 'apl_const_sold.str1')
                        ->where('apl_const_sold.key_code', '=', 'SOLD_STAT'); // Tambahkan kondisi key_code = 'SOLD_STAT'
                })
                ->leftJoin('appl_constant as apl_const_stat', function ($join) {
                    $join->on('house_unit.stat', '=', 'apl_const_stat.str1')
                        ->where('apl_const_stat.key_code', '=', 'BD_STATUS'); // Tambahkan kondisi key_code = 'SOLD_STAT'
                })
                // ->leftjoin('commodity', function ($join) {
                //     $join->on('article.com_id', '=', 'commodity.com_id')
                //          ->on('category.cat_id', '=', 'commodity.cat_id');
                // })
                ->whereRaw($sqlWhere)
                ->where('house_unit.active_flag', 'Y')
                ->orderBy('house_unit.house_name', 'asc')
                ->get()
                ->map(function ($e) {
                    $e->house_price = (int) $e->house_price;

                    return $e;
                });

            return $this->sendResponse($datas, 'Successfully get House Unit');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function updateHouseUnit(Request $request)
    {
        $houseUnitId = $request->id_house;

        if (!$houseUnitId) {
            return $this->sendError('Masukkan ID House yang akan diubah', 'Error', 500);
        }

        try {
            DB::connection('sqlSrvFPI')
                ->table('house_unit')
                ->where('id_house', $houseUnitId)
                ->update([
                    "id_cluster" => $request->id_cluster ?? '',
                    "id_house_type" => $request->id_house_type ?? '',
                    "house_name" => $request->house_name ?? '',
                    "building_area" => $request->building_area ?? '',
                    "land_area" => $request->land_area ?? '',
                    "building_dimension" => $request->building_dimension ?? '',
                    "land_dimension" => $request->land_dimension ?? '',
                    "sales" => $request->sales ?? '',
                    "buyer" => $request->buyer ?? '',
                    "stat" => $request->stat ?? '',
                    "sold_stat" => $request->sold_stat ?? '',
                    "date_build" => $request->date_build ?? '',
                    "date_finish" => $request->date_finish ?? '',
                    "date_sold" => $request->date_sold ?? '',
                    "house_price" => $request->house_price ?? 0,
                    'remark' => $request->remark ? $request->remark : '',
                    'date_modified' => now(),
                    'modified_by' => getAuth($request)->user_id2,
                ]);

            return $this->sendResponse('Success Update', 'Successfully get House Unit');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function createHouseUnit(Request $request)
    {
        try {
            $id_house = $this->generateHouseItemID($request);
            DB::connection('sqlSrvFPI')
                ->table('house_unit')
                ->insert([
                    "id_house" => $id_house ?? '',
                    "id_site" => $request->id_site ?? '',
                    "id_house_type" => $request->id_house_type ?? '',
                    "id_cluster" => $request->id_cluster ?? '',
                    "house_name" => $request->house_name ?? '',
                    "building_area" => $request->building_area ?? '',
                    "land_area" => $request->land_area ?? '',
                    "building_dimension" => $request->building_dimension ?? '',
                    "land_dimension" => $request->land_dimension ?? '',
                    "sales" => $request->sales ?? '',
                    "buyer" => $request->buyer ?? '',
                    "stat" => $request->stat ?? '',
                    "sold_stat" => $request->sold_stat ?? '',
                    "date_build" => $request->date_build ?? '',
                    "date_finish" => $request->date_finish ?? '',
                    "date_sold" => $request->date_sold ?? '',
                    'house_price' => $request->house_price ?? '',
                    'remark' => $request->remark ? $request->remark : '',
                    'date_modified' => now(),
                    'date_created' => now(),
                    'modified_by' => getAuth($request)->user_id2,
                ]);

            return $this->sendResponse('Success Update', 'Successfully get House Unit');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function setActiveHouseUnit(Request $request)
    {
        $id_house = $request->id_house ?? '';
        $active_flag = $request->active_flag ?? 'N';

        try {
            DB::connection('sqlSrvFPI')
                ->table('house_unit')
                ->where('id_house', $id_house)
                ->update([
                    'active_flag' => $active_flag,
                    'date_modified' => now(),
                    'modified_by' => getAuth($request)->user_id2,
                ]);

            return $this->sendResponse('Success Update', 'Successfully update House Unit');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function saveKoordinat(Request $request)
    {

        $houseUnitId = $request->id_house;

        if (!$houseUnitId) {
            return $this->sendError('Masukkan ID House yang akan diubah', 'Error', 500);
        }

        try {
            $save = DB::connection('sqlSrvFPI')
                ->table('house_unit')
                ->where('id_house', $houseUnitId)
                ->update([
                    'site_coordinate' => $request->kooridinat ? $request->kooridinat : '',
                    'date_modified' => now(),
                    'modified_by' => getAuth($request)->user_id2,
                ]);
            DB::connection('sqlSrvFPI')->commit();


            return $this->sendResponse($save, 'Successfully saved Koordinate');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/houseUnit/getKoordinat",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             
     *            @OA\Property(property="id_site", type="string", example="ABC"),
     *            @OA\Property(property="id_cluster", type="string", example="ABC"),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get koordinat",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getKoordinat(Request $request)
    {
        $id_site = $request->id_site;
        $id_cluster = $request->id_cluster;

        try {
            $response = DB::connection('sqlSrvFPI')
                ->table('house_unit')
                ->selectRaw("
                    house_unit.id_house,
                    house_unit.house_name as name,
                    house_type.remark as description,
                    site_cluster.cluster_name,
                    house_type.common_name,
                    house_unit.building_area,
                    house_unit.land_area,
                    apl_const_stat.str2 as stat_name,
                    house_unit.date_build,
                    house_unit.date_finish,
                    apl_const_sold.str2 as sold_stat_name,
                    house_unit.date_sold,
                    house_unit.site_coordinate as coordinates,
                    CASE 
                        WHEN house_unit.sold_stat = 'S' THEN apl_const_sold.str3
                        WHEN house_unit.sold_stat = 'P' THEN apl_const_sold.str3
                        WHEN house_unit.sold_stat = 'H' THEN apl_const_sold.str3
                        WHEN house_unit.sold_stat = 'B' THEN apl_const_sold.str3
                        ELSE house_type.map_color_rgb 
                    END as color,

                    CASE 
                        WHEN house_unit.sold_stat = 'S' THEN 'Merah'
                        WHEN house_unit.sold_stat = 'P' THEN 'Biru'
                        WHEN house_unit.sold_stat = 'H' THEN 'Hijau'
                        WHEN house_unit.sold_stat = 'B' THEN 'Kuning'
                        ELSE house_type.map_color_name 
                    END as color_name,
                    Sales.NamaSales,
                    Customer.NamaCustomer
                ") // Hapus koma terakhir
                ->leftjoin('house_type', 'house_type.id_house_type', '=', 'house_unit.id_house_type')
                ->leftjoin('site', 'site.id_site', '=', 'house_unit.id_site')
                ->leftjoin('site_cluster', 'house_unit.id_cluster', '=', 'site_cluster.id_cluster')
                ->leftjoin('appl_constant as apl_const_stat', function ($query) {
                    $query->on('house_unit.stat', '=', 'apl_const_stat.str1')
                        ->where('apl_const_stat.key_code', '=', 'BD_STATUS');
                })
                ->leftjoin('appl_constant as apl_const_sold', function ($query) {
                    $query->on('house_unit.sold_stat', '=', 'apl_const_sold.str1')
                        ->where('apl_const_sold.key_code', '=', 'SOLD_STAT');
                })
                ->leftjoin('Customer', 'house_unit.buyer', '=', 'Customer.CustomerId')
                ->leftjoin('Sales', 'house_unit.sales', '=', 'Sales.SalesId')
                ->where('site.id_site', $id_site)
                ->where('site_cluster.id_cluster', $id_cluster)
                ->where('house_unit.active_flag', 'Y')
                ->get();


            return $this->sendResponse($response, 'Successfully Get Site Coordinate');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/master/getCustomer",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             
     *            
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get customer",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getCustomer()
    {
        try {
            $customer = DB::connection("sqlSrvFPI")
                ->table('Customer')
                ->select("*")
                ->where('active_flag', 'Y')
                ->get();

            return $this->sendResponse($customer, 'Successfully get sales');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function updateHousePrice(Request $request)
    {
        $id_house = $request->id_house;
        $price = $request->price;

        try {
            $house = DB::connection("sqlSrvFPI")
                ->table('house_unit')
                ->where('id_house', $id_house)
                ->first();

            if (!$house) {
                return $this->sendError("Data not found", 'Error', 500);
            }

            // Update house price
            DB::connection("sqlSrvFPI")
                ->table('house_unit')
                ->where('id_house', $id_house)
                ->update([
                    'house_price' => (int) $price,
                    'date_modified' => now(),
                    'modified_by' => getAuth($request)->user_id2,
                ]);

            // -- save log price
            DB::connection("sqlSrvFPI")
                ->table('house_price_log')
                ->insert([
                    'id_house' => $id_house,
                    'price' => (int) $price,
                    'dt_modified' => now(),
                    'modified_by' => getAuth($request)->user_id2,
                ]);

            return $this->sendResponse($id_house, 'Successfully update price');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function generateHouseItemID($request)
    {
        // $dateYear = substr( date("Y"), 0);
        $dateYear = date("Y");
        $dateMonth = date("m");
        // $dateDay = date("d");
        $id_house = '';

        $getLastTrID = DB::connection('sqlSrvFPI')
            ->table('house_unit')
            ->where('id_house', 'like', "%" . $dateYear . $dateMonth . "%")
            ->max('id_house');

        if ($getLastTrID) {
            $month = substr(trim($getLastTrID), 5, 2);

            // dd($dateMonth.'-'.$month);

            if ($month != $dateMonth) {
                $id_house = 'H' . $dateYear . $dateMonth . '001';
            } else {
                $seq = substr(trim($getLastTrID), -3) + 1;
                $id_house = 'H' . $dateYear . $dateMonth . str_pad($seq, 3, '0', STR_PAD_LEFT);
            }
        } else {
            $id_house = 'H' . $dateYear . $dateMonth . '001';
        }

        return $id_house;
    }

    /*=================================================================================*/
    /*====================================| SALES |====================================*/
    /*=================================================================================*/

    /**
     * @OA\Post(
     *     path="/api/fpi/sales/getKaCab",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *            @OA\Property(property="region", type="string", example="ABC"),
     *            @OA\Property(property="office", type="string", example="ABC"),
     *            
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get customer",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getKaCab(Request $request)
    {
        $sqlWhere = "sales_type = 'K'";

        $region = $request->region;
        $office = $request->office;

        if ($region) {
            $sqlWhere = $sqlWhere . "and region = '" . $region . "'";
        }
        if ($office) {
            $sqlWhere = $sqlWhere . "and office_id = '" . $office . "'";
        }

        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('Sales')
                ->selectRaw("*")
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Successfully get Sales');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/sales/getSales",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *            @OA\Property(property="region", type="string", example="ABC"),
     *            @OA\Property(property="office", type="string", example="ABC"),
     *           @OA\Property(property="division", type="string", example="ABC"),
     *           @OA\Property(property="sales_type", type="string", example="ABC"),
     *           @OA\Property(property="active_flag", type="string", example="Y"),
     *            
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get sales",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getSales(Request $request)
    {
        $sqlWhere = "1=1";

        $region = $request->region;
        $office = $request->office;
        $division = $request->division;
        $sales_type = $request->sales_type;
        $active_flag = $request->active_flag;
        $SalesId = $request->SalesId;

        if ($region) {
            $sqlWhere = $sqlWhere . "and Sales.region = '" . $region . "'";
        }
        if ($office) {
            $sqlWhere = $sqlWhere . "and Sales.office_id = '" . $office . "'";
        }
        if ($division) {
            $sqlWhere = $sqlWhere . "and Sales.division = '" . $division . "'";
        }
        if ($sales_type) {
            $sqlWhere = $sqlWhere . "and Sales.sales_type = '" . $sales_type . "'";
        }
        if ($active_flag) {
            $sqlWhere = $sqlWhere . "and Sales.active_flag = '" . $active_flag . "'";
        }
        if ($SalesId) {
            $sqlWhere = $sqlWhere . "and Sales.SalesId = '" . $SalesId . "'";
        }

        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('Sales')
                ->selectRaw("   
                                            Sales.SalesId, 
                                            Sales.NamaSales, 
                                            Sales.Alamat, 
                                            Sales.Kota, 
                                            Sales.Telepon, 
                                            Sales.active_flag, 
                                            TRIM (Sales.ka_cab) AS ka_cab, 
                                            TRIM (Sales.region) AS region, 
                                            Sales.division, 
                                            TRIM (Sales.e_mail) AS email, 
                                            Sales.office_id, 
                                            TRIM (Sales.bank) AS bank, 
                                            TRIM (Sales.rek_id) AS rek_id, 
                                            Sales.sales_type, 
                                            Sales.user_id2, 
                                            Sales.dt_active, 
                                            kacab.NamaSales AS ka_cab_nama, 
                                            division_constant.str2 AS division_str2, 
                                            sales_type_constant.str2 AS sales_type_str2,
                                            TRIM (branch_office.office) AS office,
                                            cl.province_code as Provinsi,
                                            cl.city as city_name,
                                            trim(Sales.idemployee) as idemployee,
                                            Sales.area as sales_area_id
                                        ")
                ->leftJoin('Sales as kacab', 'Sales.ka_cab', '=', 'kacab.SalesId')
                // ->leftJoin('appl_constant as division_constant', 'Sales.division', '=', 'division_constant.str1')
                // ->join('appl_constant as sales_type_constant', 'Sales.sales_type', '=', 'sales_type_constant.str1')
                ->join('appl_constant as division_constant', function ($join) {
                    $join->on('Sales.division', '=', 'division_constant.str1')
                        ->where('division_constant.key_code', '=', 'STYPE');
                })
                ->join('appl_constant as sales_type_constant', function ($join) {
                    $join->on('Sales.sales_type', '=', 'sales_type_constant.str1')
                        ->where('sales_type_constant.key_code', '=', 'SPOSITION');
                })
                ->leftJoin('branch_office', 'Sales.office_id', '=', 'branch_office.office_id')
                ->leftJoin('city_list as cl', 'Sales.Kota', '=', 'cl.city_code')
                // ->join('region', 'Sales.region', '=', 'region.region_id')
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Successfully get Sales');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function updateSales(Request $request)
    {
        try {
            $jabatan = $request->sales_type_id;
            $officeAndRegion = DB::connection('sqlSrvFPI')
                ->table('Sales_area')
                ->selectRaw("   
                                                        Sales_area.id_area, 
                                                        branch_office.office_id, 
                                                        branch_office.region
                                                        ")
                ->leftJoin('branch_office', 'Sales_area.office_id', '=', 'branch_office.office_id')
                ->where('Sales_area.id_area', $request->area)
                ->first();
            // dd($officeAndRegion);
            if ($jabatan == 'SPV') {
                $updateSalesType = DB::connection('sqlSrvFPI')
                    ->table('Sales')
                    ->where('area', $request->area)
                    ->where('sales_type', 'SPV')
                    ->update([
                        'sales_type' => 'SLS'
                    ]);
                // dd($updateSalesType);
                $sales_id = DB::connection('sqlSrvFPI')
                    ->table('Sales')
                    ->where('SalesId', $request->sales_id)
                    ->first();
                // dd($sales_id);
                if ($sales_id) {
                    $save = DB::connection('sqlSrvFPI')
                        ->table('Sales')
                        ->where('SalesId', $request->sales_id)
                        ->update([
                            "NamaSales" => $request->sales_name ? $request->sales_name : '',
                            "Alamat" => $request->sales_address ? $request->sales_address : '',
                            "Kota" => $request->sales_city ? $request->sales_city : '',
                            "Telepon" => $request->phone_number ? $request->phone_number : '',
                            "region" => $officeAndRegion->region ?? '',
                            "division" => $request->division_id ?? 'P',
                            "e_mail" => $request->email ? $request->email : '',
                            "office_id" => $officeAndRegion->office_id ?? '',
                            "bank" => $request->bank ? $request->bank : '',
                            "rek_id" => $request->bank_acccount ? $request->bank_acccount : '',
                            "sales_type" => $request->sales_type_id ? $request->sales_type_id : '',
                            "dt_modified" => now(),
                            "modified_by" => getAuth($request)->user_id2,
                            "idemployee" => $request->idemployee ? $request->idemployee : '',
                            'area' => $request->area ? $request->area : ''
                        ]);
                    // dd($save);
                    $updateArea = DB::connection('sqlSrvFPI')
                        ->table('Sales_area')
                        ->where('id_area', $request->area)
                        ->update([
                            'spv_area' => $request->sales_id,
                            'dt_modified' => now(),
                            'modified_by' => getAuth($request)->user_id2,
                        ]);
                    // dd($updateArea);
                    // $updateSales = DB::connection('sqlSrvFPI')
                    //     ->table('Sales')
                    //     ->where('office_id', $request->office_id)
                    //     ->update([
                    //         'ka_cab' => $request->sales_id
                    //     ]);

                    return $this->sendResponse('Success Update', 'Successfully update Sales');
                } else {
                    return $this->sendError('Sales tidak ditemukan', 'Error', 500);
                }
            } elseif ($jabatan == 'MGR') {
                $updateSalesType = DB::connection('sqlSrvFPI')
                    ->table('Sales')
                    ->where('office_id', $request->office_id)
                    ->where('sales_type', 'MGR')
                    ->update([
                        'sales_type' => 'SLS'
                    ]);

                $sales_id = DB::connection('sqlSrvFPI')
                    ->table('Sales')
                    ->where('SalesId', $request->sales_id)
                    ->first();

                if ($sales_id) {
                    $save = DB::connection('sqlSrvFPI')
                        ->table('Sales')
                        ->where('SalesId', $request->sales_id)
                        ->update([
                            "NamaSales" => $request->sales_name ? $request->sales_name : '',
                            "Alamat" => $request->sales_address ? $request->sales_address : '',
                            "Kota" => $request->sales_city ? $request->sales_city : '',
                            "Telepon" => $request->phone_number ? $request->phone_number : '',
                            "region" => $request->region ?? '',
                            "division" => $request->division_id ? $request->division_id : '',
                            "e_mail" => $request->email ? $request->email : '',
                            "office_id" => $officeAndRegion->office_id ?? '',
                            "bank" => $request->bank ? $request->bank : '',
                            "rek_id" => $request->bank_acccount ? $request->bank_acccount : '',
                            "sales_type" => $request->sales_type_id ? $request->sales_type_id : '',
                            "dt_modified" => now(),
                            "modified_by" => getAuth($request)->user_id2,
                            "idemployee" => $request->idemployee ? $request->idemployee : '',
                            'area' => $request->area ? $request->area : ''
                        ]);

                    $updateArea = DB::connection('sqlSrvFPI')
                        ->table('Sales_area')
                        ->where('id_area', $request->area)
                        ->update([
                            'mgr_area' => $request->sales_id,
                            'dt_modified' => now(),
                            'modified_by' => getAuth($request)->user_id2,
                        ]);

                    // $updateSales = DB::connection('sqlSrvFPI')
                    //     ->table('Sales')
                    //     ->where('office_id', $request->office_id)
                    //     ->update([
                    //         'ka_cab' => $request->sales_id
                    //     ]);

                    return $this->sendResponse('Success Update', 'Successfully update Sales');
                } else {
                    return $this->sendError('Sales tidak ditemukan', 'Error', 500);
                }
            } else {
                $sales_id = DB::connection('sqlSrvFPI')
                    ->table('Sales')
                    ->where('SalesId', $request->sales_id)
                    ->first();

                if ($sales_id) {
                    $save = DB::connection('sqlSrvFPI')
                        ->table('Sales')
                        ->where('SalesId', $request->sales_id)
                        ->update([
                            "NamaSales" => $request->sales_name ? $request->sales_name : '',
                            "Alamat" => $request->sales_address ? $request->sales_address : '',
                            "Kota" => $request->sales_city ? $request->sales_city : '',
                            "Telepon" => $request->phone_number ? $request->phone_number : '',
                            "region" => $officeAndRegion->region ?? '',
                            "division" => $request->division_id ?? 'P',
                            "e_mail" => $request->email ? $request->email : '',
                            "office_id" => $officeAndRegion->office_id ?? '',
                            "bank" => $request->bank ? $request->bank : '',
                            "rek_id" => $request->bank_acccount ? $request->bank_acccount : '',
                            "sales_type" => $request->sales_type_id ? $request->sales_type_id : '',
                            "dt_modified" => now(),
                            "modified_by" => getAuth($request)->user_id2,
                            "idemployee" => $request->idemployee ? $request->idemployee : '',
                            'area' => $request->area ? $request->area : ''
                        ]);

                    return $this->sendResponse('Success Update', 'Successfully update Sales');
                } else {
                    return $this->sendError('Sales tidak ditemukan', 'Error', 500);
                }
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function deleteSales(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $sales = DB::connection('sqlSrvFPI')
                ->table('Sales')
                ->where('SalesId', $request->SalesId)
                ->first();

            if ($sales) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('Sales')
                    ->where('SalesId', $request->SalesId)
                    ->update([
                        'active_flag' => 'N',
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Update', 'Successfully Delete Sales');
            } else {
                return $this->sendError('Sales tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function activateSales(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $sales = DB::connection('sqlSrvFPI')
                ->table('Sales')
                ->where('SalesId', $request->SalesId)
                ->first();

            if ($sales) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('Sales')
                    ->where('SalesId', $request->SalesId)
                    ->update([
                        'active_flag' => 'Y',
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,

                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Update', 'Successfully Activate Sales');
            } else {
                return $this->sendError('Sales tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function storeSales(Request $request)
    {
        try {
            $jabatan = $request->sales_type_id;

            $officeAndRegion = DB::connection('sqlSrvFPI')
                ->table('Sales_area')
                ->selectRaw("   
                    Sales_area.id_area, 
                    branch_office.office_id, 
                    branch_office.region
                ")
                ->leftJoin('branch_office', 'Sales_area.office_id', '=', 'branch_office.office_id')
                ->where('Sales_area.id_area', $request->area)
                ->first();

            if ($jabatan == 'SPV') {
                $sales_id = $this->generateSalesID($request);

                DB::connection('sqlSrvFPI')
                    ->table('Sales')
                    ->where('area', $request->area)
                    ->where('sales_type', 'SPV')
                    ->update([
                        'sales_type' => 'SLS'
                    ]);

                $getSales = DB::connection('sqlSrvFPI')
                    ->table('Sales')
                    ->where('SalesId', $request->sales_id)
                    ->first();

                if ($getSales) {
                    $save = DB::connection('sqlSrvFPI')
                        ->table('Sales')
                        ->where('SalesId', $getSales->SalesId)
                        ->update([
                            'NamaSales' => $request->sales_name,
                            'Alamat' => $request->sales_address,
                            'Kota' => $request->sales_city,
                            'Telepon' => $request->phone_number,
                            'active_flag' => 'Y',
                            'ka_cab' => '',
                            'region' => $officeAndRegion->region ?? '',
                            'division' => $request->division_id ?? 'P',
                            'e_mail' => $request->email ?? '',
                            'office_id' => $officeAndRegion->office_id ?? '',
                            'bank' => $request->bank,
                            'rek_id' => $request->bank_acccount,
                            'sales_type' => $request->sales_type_id,
                            'user_id2' => getAuth($request)->user_id2,
                            'dt_active' => now(),
                            'idemployee' => $request->idemployee ? $request->idemployee : '',
                            'area' => $request->area ? $request->area : '',
                            'province' => $request->province ? $request->province : ''
                        ]);
                } else {
                    $save = DB::connection('sqlSrvFPI')
                        ->table('Sales')
                        ->insert([
                            'SalesId' => $sales_id,
                            'NamaSales' => $request->sales_name,
                            'Alamat' => $request->sales_address,
                            'Kota' => $request->sales_city,
                            'Telepon' => $request->phone_number,
                            'active_flag' => 'Y',
                            'ka_cab' => '',
                            'region' => $officeAndRegion->region ?? '',
                            'division' => $request->division_id ?? 'P',
                            'e_mail' => $request->email ?? '',
                            'office_id' => $officeAndRegion->office_id ?? '',
                            'bank' => $request->bank,
                            'rek_id' => $request->bank_acccount,
                            'sales_type' => $request->sales_type_id,
                            'user_id2' => getAuth($request)->user_id2,
                            'dt_active' => now(),
                            'idemployee' => $request->idemployee ? $request->idemployee : '',
                            'area' => $request->area ? $request->area : '',
                            'province' => $request->province ? $request->province : ''
                        ]);
                }

                DB::connection('sqlSrvFPI')
                    ->table('Sales_area')
                    ->where('id_area', $request->area)
                    ->update([
                        'spv_area' => $sales_id,
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);
            } elseif ($jabatan == 'MGR') {
                $sales_id = $this->generateSalesID($request);

                DB::connection('sqlSrvFPI')
                    ->table('Sales')
                    ->where('area', $request->area)
                    ->where('sales_type', 'MGR')
                    ->update([
                        'sales_type' => 'SLS'
                    ]);

                $getSales = DB::connection('sqlSrvFPI')
                    ->table('Sales')
                    ->where('SalesId', $request->sales_id)
                    ->first();

                if ($getSales) {
                    $save = DB::connection('sqlSrvFPI')
                        ->table('Sales')
                        ->where('SalesId', $getSales->SalesId)
                        ->update([
                            'NamaSales' => $request->sales_name,
                            'Alamat' => $request->sales_address,
                            'Kota' => $request->sales_city,
                            'Telepon' => $request->phone_number,
                            'active_flag' => 'Y',
                            'ka_cab' => '',
                            'region' => $officeAndRegion->region ?? '',
                            'division' => $request->division_id ?? 'P',
                            'e_mail' => $request->email ?? '',
                            'office_id' => $officeAndRegion->office_id ?? '',
                            'bank' => $request->bank,
                            'rek_id' => $request->bank_acccount,
                            'sales_type' => $request->sales_type_id,
                            'user_id2' => getAuth($request)->user_id2,
                            'dt_active' => now(),
                            'idemployee' => $request->idemployee ? $request->idemployee : '',
                            'area' => $request->area ? $request->area : '',
                            'province' => $request->province ? $request->province : ''
                        ]);
                } else {
                    $save = DB::connection('sqlSrvFPI')
                        ->table('Sales')
                        ->insert([
                            'SalesId' => $sales_id,
                            'NamaSales' => $request->sales_name,
                            'Alamat' => $request->sales_address,
                            'Kota' => $request->sales_city,
                            'Telepon' => $request->phone_number,
                            'active_flag' => 'Y',
                            'ka_cab' => '',
                            'region' => $officeAndRegion->region ?? '',
                            'division' => $request->division_id ?? 'P',
                            'e_mail' => $request->email ?? '',
                            'office_id' => $officeAndRegion->office_id ?? '',
                            'bank' => $request->bank,
                            'rek_id' => $request->bank_acccount,
                            'sales_type' => $request->sales_type_id,
                            'user_id2' => getAuth($request)->user_id2,
                            'dt_active' => now(),
                            'idemployee' => $request->idemployee ? $request->idemployee : '',
                            'area' => $request->area ? $request->area : '',
                            'province' => $request->province ? $request->province : ''
                        ]);
                }


                DB::connection('sqlSrvFPI')
                    ->table('Sales_area')
                    ->where('office_id', $officeAndRegion->office_id)
                    ->update([
                        'mgr_area' => $sales_id,
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);
            } else {
                $sales_id = $this->generateSalesID($request);

                $save = DB::connection('sqlSrvFPI')
                    ->table('Sales')
                    ->insert([
                        'SalesId' => $sales_id,
                        'NamaSales' => $request->sales_name,
                        'Alamat' => $request->sales_address,
                        'Kota' => $request->sales_city,
                        'Telepon' => $request->phone_number,
                        'active_flag' => 'Y',
                        'ka_cab' => '',
                        'region' => $officeAndRegion->region ?? '',
                        'division' => $request->division_id ?? 'P',
                        'e_mail' => $request->email,
                        'office_id' => $officeAndRegion->office_id ?? '',
                        'bank' => $request->bank ?? '',
                        'rek_id' => $request->bank_acccount ?? '',
                        'sales_type' => $request->sales_type_id,
                        'user_id2' => getAuth($request)->user_id2,
                        'dt_active' => now(),
                        'idemployee' => $request->idemployee ? $request->idemployee : '',
                        'area' => $request->area ? $request->area : '',
                        'province' => $request->province ? $request->province : ''
                    ]);
            }
            return $this->sendResponse($save, 'Successfully saved sales');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function generateSalesID($request)
    {
        $dateYear = date("Y");
        $dateMonth = date("m");
        $gen_cluster_id = '';

        $getLastTrID = DB::connection('sqlSrvFPI')
            ->table('Sales')
            ->max('SalesId');

        if ($getLastTrID) {
            $seq = substr(trim($getLastTrID), -4) + 1;
            $gen_cluster_id = 'S' . str_pad($seq, 4, '0', STR_PAD_LEFT);
        } else {
            $gen_cluster_id = 'S' . '0001';
        }

        return $gen_cluster_id;
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/sales/getPayTerm",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *            
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get pay term",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getPayTerm()
    {
        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('appl_constant')
                ->selectRaw("key_code, str1, str2")
                ->where('key_code', 'PAYTYPE')
                ->get();

            return $this->sendResponse($datas, 'Successfully get Pay Term');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/sales/getIncludePrice",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *            
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get include price",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getIncludePrice()
    {
        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('appl_constant')
                ->selectRaw("key_code, str1, str2")
                ->where('key_code', 'ORDERADDON')
                ->where('int1', '0')
                ->where('int2', '0')
                ->get();

            return $this->sendResponse($datas, 'Successfully get Add On');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/master/getPayTerm2",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *            
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get pay term",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getPayTerm2()
    { //dari tabel pay_term
        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('pay_term')
                ->selectRaw("pay_term_id, pay_term_desc")
                ->where('active_flag', 'Y')
                ->get();

            return $this->sendResponse($datas, 'Successfully get Pay Term');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/master/getbankaccount",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *            
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get bank account",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getbankaccount()
    {
        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('account')
                ->selectraw('trim(bank_name) as bank_name')
                ->distinct()
                ->get();
            return $this->sendResponse($datas, 'Successfully get Bank Account');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/master/getbankacc",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *            @OA\Property(property="bank_name", type="string", example="Bank ABC"),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get bank account",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getbankacc(Request $request)
    {
        try {
            $bank_name = $request->bank_name;
            $datas = DB::connection("sqlSrvFPI")
                ->table('account as a')
                ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
                ->selectraw('trim(a.bank_acc) as bank_acc, trim(b.ac_name) as ac_name')
                ->where('bank_name', $bank_name)
                ->get();
            return $this->sendResponse($datas, 'Successfully get Bank Account');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/sales/getEmployeeForSales",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *            
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get employee for sales",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getEmployeeForSales()
    {
        try {
            $data = DB::connection('sqlsrvHrd')
                ->table('employee')
                ->selectRaw("   
                                employee.company_id, 
                                employee.idemployee, 
                                employee.office_id, 
                                employee.dept_id, 
                                employee.division_id, 
                                employee.section_id, 
                                employee.group_id, 
                                employee.name, 
                                employee.address1, 
                                employee.address2, 
                                employee.sex, 
                                employee.child_num, 
                                employee.jabatan, 
                                employee.salesid, 
                                employee.remark,
                                CONCAT(employee.idemployee,' - ',employee.name) AS gabungan
                            ")
                ->whereIn('company_id', ['EXT', 'FDPI'])
                ->where('active_flag', '=', 'Y')
                ->get();
            return $this->sendResponse($data, 'Successfully get employee');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/sales/getSalesArea",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *            
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get sales area",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getSalesArea()
    {
        try {
            $data = DB::connection('sqlSrvFPI')
                ->table('Sales_area')
                ->selectRaw("   
                                *
                            ")
                ->where('active_flag', '=', 'Y')
                ->get();
            return $this->sendResponse($data, 'Successfully get sales area');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    // ======================= SITE TARGET =========================== //
    /**
     * @OA\Post(
     *     path="/api/fpi/site_target/getSiteTarget",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *           @OA\Property(property="province_id", type="string", example="P01"),
     *           @OA\Property(property="city_id", type="string", example="C01"),
     *          @OA\Property(property="site_id", type="string", example="S01"),
     *          @OA\Property(property="cluster_id", type="string", example="CL01"),
     *          @OA\Property(property="year", type="string", example="2023"),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get site target",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getSiteTarget(Request $request)
    {
        $province_id = $request->province_id ?? '';
        $city_id = $request->city_id ?? '';
        $site_id = $request->site_id ?? '';
        $cluster_id = $request->cluster_id ?? '';
        $year = $request->year ?? '';

        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('site_cluster_target as st')
                ->selectRaw('
                    st.id_site,
                    si.site_name,
                    st.id_cluster,
                    sc.cluster_name,
                    pl.province_code,
                    pl.province,
                    cl.city_code,
                    cl.city,
                    st.period_year,
                    st.period_month,
                    st.periode as period,
                    st.target_unit,
                    st.target_amt,
                    st.target_amt_akad,
                    st.target_unit_akad
                ')
                ->join('site as si', 'st.id_site', '=', 'si.id_site')
                ->join('site_cluster as sc', 'st.id_cluster', '=', 'sc.id_cluster')
                ->join('city_list as cl', 'si.city', '=', 'cl.city_code')
                ->join('province_list as pl', 'cl.province_code', '=', 'pl.province_code');

            if ($province_id) {
                $datas->where('pl.province_code', $province_id);
            }

            if ($city_id) {
                $datas->where('cl.city_code', $city_id);
            }

            if ($site_id) {
                $datas->where('st.id_site', $site_id);
            }

            if ($cluster_id) {
                $datas->where('st.id_cluster', $cluster_id);
            }

            if ($year) {
                $datas->where('st.period_year', $year);
            }

            $datas = $datas->orderBy('si.site_name')
                ->get()
                ->map(function ($item) {
                    $date = Carbon::createFromDate($item->period_year, $item->period_month, 1);

                    $item->month_name = strtoupper($date->format('M'));
                    $item->target_amt = (int) $item->target_amt;
                    $item->target_amt_akad = (int) $item->target_amt_akad;

                    return $item;
                });

            return $this->sendResponse($datas, 'Successfully get Site Target');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function updateUnitPriceSiteTarget(Request $request)
    {
        $site_id = $request->site_id ?? '';
        $cluster_id = $request->cluster_id ?? '';
        $period = $request->period ?? '';
        $unit = $request->unit ?? 0;
        $price = $request->price ?? 0;
        $unit_akad = $request->unit_akad ?? 0;
        $price_akad = $request->price_akad ?? 0;

        try {
            DB::connection("sqlSrvFPI")
                ->table('site_cluster_target')
                ->where('id_site', $site_id)
                ->where('id_cluster', $cluster_id)
                ->where('periode', $period)
                ->update([
                    'target_unit' => (int) $unit,
                    'target_amt' => (int) $price,
                    'target_unit_akad' => (int) $unit_akad,
                    'target_amt_akad' => (int) $price_akad,
                    'dt_modified' => now(),
                    'modified_by' => getAuth($request)->user_id2,
                ]);

            return $this->sendResponse("Success", 'Successfully update Site Target');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/master/getVendor",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *            
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get vendor",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getVendor(Request $request)
    {
        try {
            $vendor_id = $request->vendor_id ?? '';
            $sqlWhere = '1=1';
            if ($vendor_id) {
                $sqlWhere .= " and vendor_id = '$vendor_id'";
            }

            $datas = DB::connection("sqlSrvFPI")
                ->table('vendor')
                ->selectRaw("trim(vendor_id) as vendor_id, trim(vendor_name) as vendor_name,
                    case
                        when tax1 = 'Y' then 'E'
                        else 'N'
                    end AS ppn,
                    case
                        when tax2 = 'Y' then 'E'
                        else 'N'
                    end AS pph
                ")
                ->where('active_flag', 'Y')
                ->orderBy('vendor_name', 'asc')
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Successfully get Vendor');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/master/getDeptHrdOl",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *            
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get departemen",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getDeptHrdOl()
    {
        try {

            $departmenthrd = DB::connection("sqlsrvHrd")
                ->select(DB::raw("select dept_id, dept_name 
                                from  department 
                                where company_id = 'FDPI'"));

            return response()->json(['departmenthrd' => $departmenthrd]);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/master/getEmployeeHrdOl",
     *     tags={"Master"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *           @OA\Property(property="dept_id", type="string", example="D01"),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get employee",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getEmployeeHrdOl(Request $request)
    {
        try {

            $sqlWhere = '1=1 ';

            if ($request->dept_id) {
                $sqlWhere .= "and a.dept_id = '$request->dept_id'";
            }

            $employee = DB::connection("sqlsrvHrd")
                ->select(DB::raw("select RTRIM(a.idemployee) as idemployee, name, a.dept_id, b.dept_name , 
                                CONCAT(a.idemployee,' - ',a.name) AS gabungan
                                from employee a left outer join
                                department AS b ON a.company_id = b.company_id and a.dept_id = b.dept_id 
                                where $sqlWhere and a.active_flag = 'Y' and a.company_id='FDPI' "));

            return response()->json(['employee' => $employee]);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    // ======================= MASTER SEPERLUNYA YANG BUTUH PAKE AJA =========================== //

    //Cari siapa approval
    public function getWhoWillApprove(Request $request)
    {
        $sqlWhere = "1=1";
        $var_id = $request->var_id;
        $appl_id = $request->appl_id;

        if (empty($var_id)) {
            return $this->sendError("var_id Kosong", 'Error', 500);
        }
        if (empty($appl_id)) {
            // return $this->sendError("var_id Kosong", 'Error', 500);
        } else {
            $sqlWhere = $sqlWhere . " and env_conf_belong.appl_id = " . "'" . $appl_id . "'";
        }

        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('env_conf_belong')
                ->selectRaw("*")
                ->where('var_id', $var_id)
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Successfully get person who will approve');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getDocumentStatus(Request $request)
    {
        $sqlWhere = "1=1";
        $doc_id = $request->doc_id;
        $doc_type = $request->doc_type;
        $doc_cat = $request->doc_cat;

        if (empty($doc_id)) {
            return $this->sendError("doc_id Kosong", 'Error', 500);
        } else {
            $sqlWhere = $sqlWhere . " and v_document_status.doc_id = " . "'" . $doc_id . "'";
        }
        if (empty($doc_type)) {
            return $this->sendError("doc_type Kosong", 'Error', 500);
        } else {
            $sqlWhere = $sqlWhere . " and v_document_status.doc_type = " . "'" . $doc_type . "'";
        }
        if (empty($doc_cat)) {
            // return $this->sendError("doc_cat Kosong", 'Error', 500);
        } else {
            $sqlWhere = $sqlWhere . " and v_document_status.doc_cat = " . "'" . $doc_cat . "'";
        }

        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('v_document_status')
                ->selectRaw("*")
                // ->where('doc_id', $doc_id)
                // ->where('doc_type', $doc_type)
                // ->where('doc_cat', $doc_cat)
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Successfully document status');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function getDataMasterFinance(Request $request)
    {
        $type = $request->type;

        try {
            $connection = DB::connection("sqlSrvFPI");

            switch ($type) {
                case 'coa':
                    $result = $connection->table('g_ledger')
                        ->selectRaw("TRIM(ac_num) AS ad_num, TRIM(ac_name) AS ac_name, curr_id")
                        ->get();
                    $message = 'Successfully retrieved COA data';
                    break;

                case 'bank_master':
                    $result = $connection->table('account')
                        ->selectRaw("DISTINCT trim(bank_name) as bank_name")
                        ->whereIn('a_type', ['B', 'R'])
                        ->where('posting', 'Y')
                        ->get();
                    $message = 'Successfully retrieved bank master data';
                    break;

                case 'bank_account':
                    $bankId = $request->bank_id;
                    if (empty($bankId)) {
                        return $this->sendError("Empty Bank ID", 'Error', 500);
                    }

                    $result = $connection->table('account as a')
                        ->selectRaw("trim(a.bank_acc) as bank_acc, trim(b.ac_name) as description")
                        ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
                        ->where('bank_name', $bankId)
                        ->get();
                    // if ($result->isEmpty()) {
                    //     return $this->sendError("No bank account data found for the given bank name", 'Data Not Found', 500);
                    // }
                    $message = 'Successfully retrieved bank account data';
                    break;
                case 'kwitansi':
                    $result = $connection->table('kwitansi as a')
                        ->selectRaw("trim(a.tr_id) No_Kwitansi,
                            trim(a.type_code) as type_code,
                            trim(b.site_name) as site_name,
                            a.tr_amt Kwitansi_Amt,
                            a.tr_date,
                            trim(a.remark) as remark
                            ")
                        ->join('site as b', 'a.id_site', '=', 'b.id_site')
                        ->where('a.tr_id_ref', '')
                        ->get();
                    // if ($result->isEmpty()) {
                    //     return $this->sendError("No kwitansi data found", 'Data Not Found', 500);
                    // }
                    $message = 'Successfully retrieved kwitansi data';
                    break;
                case 'giro':
                    // sSQL = "select a.giro_num,bank_name,bank_cust_name,giro_amt,dt_giro,dt_due " & _
                    // " from giro_trans a inner join bank_mast b on a.bank_id=b.bank_id    " & _
                    // " where a.stat='B' and a.tr_id='' and tr_type='IG' and a.bank_acc='" & Trim(FAccount) & "' And A.giro_amt = " & CDbl(AmtFrom)
                    $result = $connection->table('giro_trans as a')
                        ->selectRaw("trim(a.giro_num) as giro_num,trim(bank_name) as bank_name,trim(bank_cust_name) as bank_cust_name,giro_amt,dt_giro,dt_due")
                        ->join('bank_mast as b', 'a.bank_id', '=', 'b.bank_id')
                        ->where('a.stat', 'B')
                        ->where('a.tr_id', '')
                        ->where('tr_type', 'IG')
                        ->where('a.stat', 'B')
                        ->get();
                    // if ($result->isEmpty()) {
                    //     return $this->sendError("No kwitansi data found", 'Data Not Found', 500);
                    // }
                    $message = 'Successfully retrieved kwitansi data';
                    break;
                case 'site':
                    $result = $connection->table('site')
                        ->selectRaw("trim(id_site) as id_site,trim(site_name) as site_name")
                        ->get();
                    $message = 'Successfully retrieved site data';
                    break;
                case 'cluster':
                    $site_id = $request->site_id;
                    if (empty($site_id)) {
                        return $this->sendError("Empty Site ID", 'Error', 500);
                    }

                    $result = $connection->table('site_cluster')
                        ->selectRaw("trim(id_cluster) as id_cluster, trim(cluster_name) as cluster_name")
                        ->where('id_site', $site_id)
                        ->get();
                    // if ($result->isEmpty()) {
                    //     return $this->sendError("No cluster data found for the given Site", 'Data Not Found', 500);
                    // }
                    $message = 'Successfully retrieved bank account data';
                    break;
                case 'house':
                    $site_id = $request->site_id;
                    $cluster_id = $request->cluster_id;
                    if (empty($site_id)) {
                        return $this->sendError("Empty Site ID", 'Error', 500);
                    }
                    if (empty($cluster_id)) {
                        return $this->sendError("Empty Cluster ID", 'Error', 500);
                    }

                    $result = $connection->table('house_unit')
                        ->selectRaw("trim(id_house) as id_house, trim(house_name) as house_name")
                        ->where('id_site', $site_id)
                        ->where('id_cluster', $cluster_id)
                        ->get();

                    // if ($result->isEmpty()) {
                    //     return $this->sendError("No house data found for the given Site and Cluster ID", 'Data Not Found', 500);
                    // }
                    $message = 'Successfully retrieved bank account data';
                    break;
                case 'includeprice':
                    $type = $request->type;
                    $order_id = $request->order_id;
                    if (empty($type)) {
                        return $this->sendError("Empty Type", 'Error', 500);
                    }
                    if (empty($order_id)) {
                        return $this->sendError("Empty spr id", 'Error', 500);
                    }

                    $result = $connection->table('CustomerorderAddonUnit as a')
                        ->selectRaw("a.tr_seq,trim(b.description) as description,amount,trim(remark) as remark")
                        ->join('article as b', 'a.article_id', '=', 'b.article_id')
                        ->where('order_id', $order_id)
                        ->where('cat_id', '6')
                        ->get();

                    // if ($result->isEmpty()) {
                    //     return $this->sendError("No house data found for the given Site and Cluster ID", 'Data Not Found', 500);
                    // }
                    $message = 'Successfully retrieved bank account data';
                    break;
                case 'spr':
                    $sqlWhere = "1=1";
                    $type = $request->type;
                    $idCluster = $request->id_cluster;
                    $IDHouse = $request->id_house;
                    $idSite = $request->id_site;

                    if (empty($type)) {
                        return $this->sendError("Empty type", 'Error', 500);
                    }
                    if (empty($idSite)) {
                        return $this->sendError("Empty site", 'Error', 500);
                    }
                    if ($idSite) {
                        $sqlWhere .= " AND a.id_site =  '" . $idSite . "'";
                    }
                    if ($idCluster) {
                        $sqlWhere .= " AND d.id_cluster =  '" . $idCluster . "'";
                    }
                    if ($idCluster) {
                        $sqlWhere .= " AND d.id_house =  '" . $IDHouse . "'";
                    }

                    $result = $connection->table('customerorder as a')
                        ->selectRaw("a.order_id,b.namacustomer,c.site_name,cluster_name,house_name")
                        ->join('customer as b', 'a.customerid', '=', 'b.customerid')
                        ->join('site as c', 'a.id_site', '=', 'c.id_site')
                        ->join('customerorderdetil as d', 'a.order_id', '=', 'd.order_id')
                        ->join('site_cluster as t', function ($join) {
                            $join->on('t.id_site', '=', 'a.id_site')
                                ->on('t.id_cluster', '=', 'd.id_cluster');
                        })
                        ->join('house_unit as h', function ($join) {
                            $join->on('h.id_site', '=', 'a.id_site')
                                ->on('h.id_house', '=', 'd.id_house')
                                ->on('h.id_cluster', '=', 'd.id_cluster');
                        })
                        ->whereRaw($sqlWhere)
                        ->get();

                    $message = 'Successfully retrieved bank account data';
                    break;
                case 'debit_note':
                    $result = $connection->table('credit_note as a')
                        ->selectRaw("TRIM(a.tr_id) tr_id ,tr_date, TRIM(c.vendor_name) AS vendor_name, tr_amt")
                        ->join('bank_note  as b', 'a.bk', '=', 'b.tr_id')
                        ->join('vendor  as c', 'a.cust_id', '=', 'c.vendor_id')
                        ->get();
                    $message = 'Successfully retrieved debit note data';
                    break;
                default:
                    return $this->sendError("Invalid type parameter", 'Error', 500);
            }

            return $this->sendResponse($result, $message);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    // ======================= MASTER COA =========================== //
    public function getCurrency(Request $request)
    {

        $sqlWhere = "1=1";
        $office_id = $request->office_id;
        if ($office_id) {
            $sqlWhere .= " AND office_id = '$office_id'";
        }

        $datas = DB::connection('sqlSrvFPI')
            ->table('currency')
            ->whereRaw($sqlWhere)
            ->selectRaw('TRIM(curr_id) as curr_id')
            ->orderBy('curr_id')
            ->get();

        return $this->sendResponse($datas, 'Successfully get Currency');
    }

    public function storeCOA(Request $request)
    {
        try {
            $office_id   = $request->office_id;
            $ac_num      = $request->ac_num;
            $ac_par      = $request->ac_par;
            $ac_name     = $request->ac_name;
            $ac_type     = $request->ac_type;
            $tax         = $request->tax;
            $dr_cr       = $request->dr_cr;
            $currId      = $request->currId;
            $active_Flag = $request->active_Flag;
            $ac_num_old  = $request->ac_num_old;
            $userId      = getAuth($request)->user_id2;
            $now         = Carbon::now();
            $currPeriod  = DB::connection('sqlSrvFPI')
                ->table('period')
                ->where('office_id', $office_id)
                ->value('curr_period'); // langsung ambil nilai kolom

            $yearId = $currPeriod ? substr($currPeriod, 0, 4) : null;

            // === Insert or Update g_ledger ===
            $exists = DB::connection('sqlSrvFPI')
                ->table('g_ledger')
                ->where('office_id', $office_id)
                ->where('ac_num', $ac_num)
                ->exists();

            if ($exists) {
                DB::connection('sqlSrvFPI')
                    ->table('g_ledger')
                    ->where('office_id', $office_id)
                    ->where('ac_num', $ac_num)
                    ->update([
                        'ac_name'     => $ac_name ?? '',
                        'ac_type'     => $ac_type ?? '',
                        'ac_par'      => $ac_par ?? '',
                        'drcr'        => $dr_cr ?? '',
                        'tax'         => $tax ?? '',
                        'curr_id'     => $currId ?? '',
                        'Active_flag' => $activeFlag ?? 'Y',
                        'dt_modified' => $now ?? now(),
                        'user_id'     => $userId ?? getAuth($request)->user_id2,
                        'ac_num_old'  => $ac_num_old ?? ''
                    ]);
            } else {
                DB::connection('sqlSrvFPI')
                    ->table('g_ledger')
                    ->insert([
                        'office_id'   => $office_id ?? '',
                        'ac_num'      => $ac_num ?? '',
                        'ac_par'      => $ac_par ?? '',
                        'ac_name'     => $ac_name ?? '',
                        'ac_type'     => $ac_type ?? '',
                        'tax'         => $tax ?? '',
                        'drcr'        => $dr_cr ?? '',
                        'curr_id'     => $currId ?? '',
                        'Active_flag' => $activeFlag ?? 'Y',
                        'dt_created'  => $now ?? now(),
                        'dt_modified' => $now ?? now(),
                        'user_id'     => $userId ?? getAuth($request)->user_id2,
                        'ac_num_old'  => $ac_num_old ?? ''
                    ]);
            }

            // === Insert or Update g_ledger_year ===
            $existsYear = DB::connection('sqlSrvFPI')
                ->table('g_ledger_year')
                ->where('office_id', $office_id)
                ->where('year_id', $yearId)
                ->where('ac_num', $ac_num)
                ->exists();

            if ($existsYear) {
                DB::connection('sqlSrvFPI')
                    ->table('g_ledger_year')
                    ->where('office_id', $office_id)
                    ->where('year_id', $yearId)
                    ->where('ac_num', $ac_num)
                    ->update([
                        'dt_modified' => $now,
                        'user_id'     => $userId,
                        'amt_type'    => $dr_cr ?? ''
                    ]);
            } else {
                DB::connection('sqlSrvFPI')
                    ->table('g_ledger_year')
                    ->insert([
                        'office_id'   => $office_id,
                        'year_id'     => $yearId,
                        'ac_num'      => $ac_num,
                        'amt_type'    => $dr_cr ?? '',
                        'dt_created'  => $now,
                        'dt_modified' => $now,
                        'user_id'     => $userId
                    ]);
            }

            // return $this->sendResponse($datas, 'Successfully document status');
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menyimpan data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getDataAcNum(Request $request)
    {
        $sqlWhere = "1=1";
        $office_id = $request->office_id;
        $ac_type = $request->ac_type;
        $dr_cr = $request->dr_cr;
        $currency = $request->currency;

        if ($office_id) {
            $sqlWhere .= " AND office_id = '$office_id'";
        }
        if ($ac_type) {
            $sqlWhere .= " AND ac_type = '$ac_type'";
        }
        if ($dr_cr) {
            $sqlWhere .= " AND drcr = '$dr_cr'";
        }
        if ($currency) {
            $sqlWhere .= " AND curr_id = '$currency'";
        }


        try {
            $data = DB::connection('sqlSrvFPI')
                ->table('g_ledger')
                ->selectRaw("
                    TRIM(ac_num) as ac_num,
                    TRIM(ac_name) as ac_name,
                    TRIM(ac_type) as ac_type,
                    TRIM(curr_id) as curr_id,
                    TRIM(drcr) as drcr,
                    TRIM(Active_flag) as Active_flag
                ")
                ->whereraw($sqlWhere)
                ->orderBy('ac_num')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getSource(Request $request)
    {
        $office_id = $request->office_id;
        $sqlWhere = "1=1";
        if ($office_id) {
            $sqlWhere .= " AND office_id = '$office_id'";
        }

        $data = DB::connection('sqlSrvFPI')
            ->table('journal_voucher')
            ->selectRaw('trim(source) as source') // Select the 'source' column
            ->distinct()      // Apply the DISTINCT keyword
            ->whereRaw($sqlWhere) // Apply the WHERE condition
            ->orderBy('source') // Order by the selected column (which is the first column, 'source')
            ->get();           // Execute the query and get the results

        return $this->sendResponse($data, 'Successfully get Source');
    }


    /*================================================================================*/
    /*===================================| VENDOR |===================================*/
    /*================================================================================*/

    public function getVendorMaster(Request $request)
    {
        $sqlWhere = "1=1";

        $office_id = $request->office_id;
        $status = $request->active_flag;

        if ($status == "Y") {
            $sqlWhere = $sqlWhere . "and vendor.active_flag = 'Y'";
        } else if ($status == "N") {
            $sqlWhere = $sqlWhere . "and vendor.active_flag = 'N'";
        } else {
        }

        if ($office_id) {
            $sqlWhere = $sqlWhere . "and vendor.office_id = '$office_id'";
        }

        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('vendor')
                ->selectRaw("
                                            vendor.office_id,
                                            TRIM(vendor.vendor_id) AS vendor_id,
                                            TRIM(vendor.vendor_name) AS vendor_name,
                                            TRIM(vendor.address1) AS address1,
                                            TRIM(vendor.address2) AS address2,
                                            TRIM(isnull(cl.city, vendor.city)) AS city,
                                            TRIM(isnull(pl.province, vendor.prov)) AS prov,
                                            TRIM(vendor.zip_code) AS zip_code,
                                            TRIM(vendor.country) AS country,
                                            TRIM(vendor.phone) AS phone,
                                            TRIM(vendor.fax) AS fax,
                                            TRIM(vendor.contact_name) AS contact_name,
                                            TRIM(vendor.email) AS email,
                                            TRIM(vendor.tax_address1) AS tax_address1,
                                            TRIM(vendor.tax_address2) AS tax_address2,
                                            TRIM(vendor.tax1) AS tax1,
                                            TRIM(vendor.tax2) AS tax2,
                                            TRIM(vendor.tax_type) AS tax_type,
                                            TRIM(vendor.pay_term_id) AS pay_term_id,
                                            TRIM(vendor.curr_id) AS curr_id,
                                            TRIM(vendor.npwp) AS npwp,
                                            vendor.amt_balance,
                                            vendor.amt_tax,
                                            vendor.amt_dp,
                                            vendor.amt_dp_used,
                                            vendor.amt_begin,
                                            TRIM(vendor.vendor_type) AS vendor_type,
                                            TRIM(vendor.active_flag) AS active_flag,
                                            vendor.dt_created,
                                            vendor.dt_modified,
                                            TRIM(vendor.user_id) AS user_id,
                                            TRIM(vendor.import) AS import,
                                            TRIM(vendor.no_rek) AS no_rek,
                                            TRIM(vendor.bank_name) AS bank_name,
                                            TRIM(vendor.bank_addr) AS bank_addr,
                                            TRIM(vendor.bank_city) AS bank_city,
                                            TRIM(vendor.swift_code) AS swift_code,
                                            vendor.office_vendor,
                                            TRIM(vendor.region_vendor) AS region_vendor,
                                            TRIM(vendor.tax3) AS tax3,
                                            TRIM(vendor.modified_by) AS modified_by,
                                            branch_office.office,
                                            TRIM(pay_term.pay_term_desc) AS pay_term_desc,
                                            CASE 
                                                WHEN vendor.active_flag = 'Y' THEN 'Active'
                                                WHEN vendor.active_flag = 'N' THEN 'Non-Active'
                                                ELSE '-'
                                            END AS active_flag_kata,
                                            CASE 
                                                WHEN vendor.vendor_type = 'I' THEN 'Import'
                                                WHEN vendor.vendor_type = 'L' THEN 'Local'
                                                WHEN vendor.vendor_type = 'X' THEN 'XPDC'
                                                ELSE '-'
                                            END AS vendor_type_kata
                                ")
                ->leftJoin('branch_office', function ($join) {
                    $join->on('vendor.office_id', '=', 'branch_office.office_id');
                })
                ->leftJoin('pay_term', function ($join) {
                    $join->on('vendor.pay_term_id', '=', 'pay_term.pay_term_id');
                })
                ->leftJoin('city_list as cl', function ($join) {
                    $join->on('vendor.city', '=', 'cl.city_code');
                })
                ->leftJoin('province_list as pl', function ($join) {
                    $join->on('vendor.prov', '=', 'pl.province_code');
                })
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Successfully get Vendor');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function updateVendor(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $vendor = DB::connection('sqlSrvFPI')
                ->table('vendor')
                ->where('vendor_id', $request->vendor_id)
                ->first();

            if ($vendor) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('vendor')
                    ->where('vendor_id', $request->vendor_id)
                    ->update([
                        'vendor_name' => $request->vendor_name ? $request->vendor_name : '',
                        'address1' => $request->address1 ? $request->address1 : '',
                        'address2' => $request->address2 ? $request->address2 : '',
                        'city' => $request->city ? $request->city : '',
                        'prov' => $request->province ? $request->province : '',
                        'country' => $request->country ? $request->country : '',
                        'zip_code' => $request->zip_code ? $request->zip_code : '',
                        'vendor_type' => $request->vendor_type ? $request->vendor_type : '',
                        'import' => $request->import_flag ? $request->import_flag : '',
                        'tax1' => $request->ppn ? $request->ppn : '',
                        'tax3' => $request->pph ? $request->pph : '',
                        'email' => $request->email ? $request->email : '',
                        'phone' => $request->telepon ? $request->telepon : '',
                        'curr_id' => $request->currency_id ? $request->currency_id : '',
                        'pay_term_id' => $request->pay_term_id ? $request->pay_term_id : '',
                        'office_id' => '1',
                        'npwp' => $request->npwp ? $request->npwp : '',
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Update', 'Successfully update vendor');
            } else {
                return $this->sendError('Vendor tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function deleteVendor(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $vendor = DB::connection('sqlSrvFPI')
                ->table('vendor')
                ->where('vendor_id', $request->vendor_id)
                ->first();

            if ($vendor) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('vendor')
                    ->where('vendor_id', $request->vendor_id)
                    ->update([
                        'active_flag' => 'N',
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,

                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Update', 'Successfully Delete vendor');
            } else {
                return $this->sendError('vendor tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function activateVendor(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $vendor = DB::connection('sqlSrvFPI')
                ->table('vendor')
                ->where('vendor_id', $request->vendor_id)
                ->first();

            if ($vendor) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('vendor')
                    ->where('vendor_id', $request->vendor_id)
                    ->update([
                        'active_flag' => 'Y',
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,

                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Update', 'Successfully Activate vendor');
            } else {
                return $this->sendError('vendor tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function storeVendor(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {
            $vendor_id = $this->generateVendorID($request);
            // dd($region_id);

            $save = DB::connection('sqlSrvFPI')
                ->table('vendor')
                ->insert([
                    'vendor_id' => $vendor_id,
                    'vendor_name' => $request->vendor_name ? $request->vendor_name : '',
                    'address1' => $request->address1 ? $request->address1 : '',
                    'address2' => $request->address2 ? $request->address2 : '',
                    'city' => $request->city ? $request->city : '',
                    'prov' => $request->province ? $request->province : '',
                    'country' => $request->country ? $request->country : '',
                    'zip_code' => $request->zip_code ? $request->zip_code : '',
                    'vendor_type' => $request->vendor_type ? $request->vendor_type : '',
                    'import' => $request->import_flag ? $request->import_flag : '',
                    'tax1' => $request->ppn == 'Y' && $request->pph == 'Y' ? '' : $request->ppn,
                    'tax2' => $request->ppn == 'Y' && $request->pph == 'Y' ? '' : $request->pph,
                    'tax3' => $request->ppn == 'Y' && $request->pph == 'Y' ? 'Y' : '',
                    'email' => $request->email ? $request->email : '',
                    'phone' => $request->telepon ? $request->telepon : '',
                    'curr_id' => $request->currency_id ? $request->currency_id : '',
                    'pay_term_id' => $request->pay_term_id ? $request->pay_term_id : '',
                    'office_id' => '1',
                    'npwp' => $request->npwp ? $request->npwp : '',
                    'active_flag' => 'Y',
                    'user_id'    => getAuth($request)->user_id2,
                    'dt_created'   => now(),
                ]);
            DB::connection('sqlSrvFPI')->commit();
            return $this->sendResponse($save, 'Successfully saved Region');
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function generateVendorID($request)
    {
        $gen_vendor_id = '';

        $getLastTrID =  DB::connection('sqlSrvFPI')
            ->table('vendor')
            ->max('vendor_id');

        if ($getLastTrID) {
            $seq = (int) substr(trim($getLastTrID), 1) + 1;
            $gen_vendor_id = 'V' . str_pad($seq, 5, '0', STR_PAD_LEFT);
        } else {
            $gen_vendor_id = 'V00001';
        }

        return $gen_vendor_id;
    }
}