<?php

namespace App\Http\Controllers\project;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use DateTime;
use Image;
use Storage;
use App\Traits\getFilterTrait;

class ProjectRegisterController extends Controller
{

    use getFilterTrait;
    
    public function index(Request $request)
    {

        $userGroup = $this->queryGetUserGroup($request);
        $region = $this->queryGetRegions($userGroup); 
        $office = $this->queryGetOffices($userGroup);
        $sales = $this->queryGetSales($userGroup);

        $mill = DB::connection('sqlSrvBCKI')
                    ->table('vendor as a')
                    ->selectRaw("trim(a.vendor_id) as mill_id, trim(a.vendor_name) as mill_name")
                    ->where('a.active_flag', '=', 'Y')
                    ->get();

        $category = DB::connection('sqlSrvBCKI')
                    ->table('appl_constant')
                    ->selectRaw('trim(str1) as str1, trim(str2) as str2')
                    ->where('key_code', '=', 'PRJCAT')
                    ->get();

        $stage = DB::connection('sqlSrvBCKI')
                    ->table('appl_constant')
                    ->selectRaw('trim(str1) as str1, trim(str2) as str2')
                    ->where('key_code', '=', 'PRJSTAT')
                    ->orderBy('int1', 'ASC')
                    ->get();

        $province = DB::connection('sqlsrvHrd')
					->table('list_province')
                    ->selectRaw('province_code, trim(UPPER(province)) as province')
                    ->orderBy('province', 'ASC')
					->get();

        $customer = DB::connection('sqlSrvBCKI')
                    ->table('customer')
                    ->selectRaw('CustomerId, trim(NamaCustomer) as customer_name')
                    ->where('active_flag', '=', 'Y')
                    ->orderBy('NamaCustomer', 'ASC')
                    ->get();

        return response()->json([
            'mill' => $mill,
            'region' => $region, 
            'office' => $office, 
            'sales' => $sales,
            'category' => $category,
            'stage' => $stage,
            'province' => $province,
            'customer' => $customer,
        ]);
    }

    public function searchDataFinance(Request $request){
        $dateFrom = $request->dateFrom;
        $dateTo = $request->dateTo;
        $sqlWhere = "1=1 ";
        $category = $request->category;
        $entity_id = 'bcki';
        $office = $request->office;

        if( $dateFrom && $dateTo){$sqlWhere .= "and a.dt_modified between '$dateFrom 00:00:00' and '$dateTo 23:59:59' ";}
        if( $category){$sqlWhere .= "and a.project_category = '$category' ";}
        if( $entity_id){$sqlWhere .= "and a.entity_id = '$entity_id' ";}
        if( $office){$sqlWhere .= "and a.office_id = '$office' ";}
        
        $result = DB::connection("sqlsrvHrd")
                    ->table('V_project_hdr as a')
                    ->whereRaw($sqlWhere)
                    ->orderBy('a.dt_modified', 'DESC')
                    ->get();   

        return response()->json(['result' => $result]);

    }

    public function submitData(Request $request){
        
        $id = $request->id;
        $entity_id = 'bcki';
        $region = $request->region;
        $office = $request->office ?? 'bcki';
        $sales = $request->sales ?? '';
        $type = $request->type ?? '';
        $projName = $request->projName ?? '';
        $category = $request->category ?? '';
        $stage = $request->stage ?? '';
        $mill = $request->mill ?? '';
        $address = $request->address ?? '';
        $city = $request->city ?? '';
        $district = $request->district ?? '';
        $village = $request->village ?? '';
        $province = $request->province ?? '';
        $dateSurvey = $request->dateSurvey ?? '';
        $PICName = $request->PICName ?? '';
        $PICPhone = $request->PICPhone ?? '';
        $startDate = $request->startDate;
        $endDate = $request->endDate;
        $remark = $request->remark;
        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $gpsAddress = $request->gpsAddress;
        
        if(!$id)
        {

            $dateNow  = date('Ymd'); // Format: 20251017
            $prefix   = "PR/" . $dateNow . "/" . $office . "/"; // Format pencarian: PR/20251017/19/

            // 2. Cari ID terakhir yang formatnya mirip dengan prefix hari ini & office ini
            $checkPrID = DB::connection('sqlsrvHrd')
                        ->table('project_hdr')
                        ->where('pr_id', 'like', $prefix . '%')
                        ->max('pr_id'); // Ambil nilai maksimum langsung

            // 3. Logika penentuan nomor urut
            if ($checkPrID) {
                // Jika ada (misal: PR/20251017/19/005), ambil 3 digit terakhir
                $lastSequence = (int) substr($checkPrID, -3);
                $nextSequence = $lastSequence + 1;
            } else {
                // Jika belum ada data hari ini untuk office tersebut
                $nextSequence = 1;
            }

            // 4. Buat padding nol (misal 1 jadi 001, 10 jadi 010)
            $seqCode = str_pad($nextSequence, 3, '0', STR_PAD_LEFT);

            // 5. Gabungkan menjadi ID Final
            $startID = $prefix . $seqCode;

            // Hasil: PR/20251017/19/001

            $result = DB::connection('sqlsrvHrd')
                    ->table('project_hdr')
                    ->insert([
                        'entity_id' => $entity_id,
                        'pr_id' => $startID,
                        'office_id' => $office,
                        'sales_id' => $sales,
                        'survey_date' => $dateSurvey,
                        'project_name' => $projName,
                        'project_location' => $address,
                        'province' => $province,
                        'city' => $city,
                        'district' => $district,
                        'village' => $village,
                        'project_jenis' => $type,
                        'project_category' => $category,
                        'project_pic' => $PICName,
                        'pic_phone' => $PICPhone,
                        'project_start' => $startDate ?? '01/01/1900',
                        'project_end' => $endDate ?? '01/01/1900',
                        'mill_id' => $mill,
                        'stat' => 'O',
                        'project_stage' => $stage,
                        'user_id' => getAuth($request)->user_id2,
                        'remark' => $remark,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'gps_address' => $gpsAddress,
                        'dt_modified' => now('Asia/Jakarta'),
                        'dt_created' => now('Asia/Jakarta'),
                        'employee_id' => getAuth($request)->idemployee,
                    ]);

            return response()->json(['result' => 'ok']);

        } else {

            $result = DB::connection('sqlsrvHrd')
                    ->table('project_hdr')
                    ->where('pr_id', '=', $id)
                    ->where('entity_id', '=', $entity_id)
                    ->update([
                        'office_id' => $office,
                        'sales_id' => $sales,
                        'survey_date' => $dateSurvey,
                        'project_name' => $projName,
                        'project_location' => $address,
                        'province' => $province,
                        'city' => $city,
                        'district' => $district,
                        'village' => $village,
                        'project_jenis' => $type,
                        'project_category' => $category,
                        'project_pic' => $PICName,
                        'pic_phone' => $PICPhone,
                        'project_start' => $startDate ?? '01/01/1900',
                        'project_end' => $endDate ?? '01/01/1900',
                        'mill_id' => $mill,
                        'project_stage' => $stage,
                        'user_id' => getAuth($request)->user_id2,
                        'remark' => $remark,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'gps_address' => $gpsAddress,
                        'dt_modified' => now('Asia/Jakarta'),
                        'dt_created' => now('Asia/Jakarta'),
                        'employee_id' => getAuth($request)->idemployee,
                    ]);

            return response()->json(['result' => 'ok']);

        }

    }

    public function getCityByprovince(Request $request){
        $sqlWhere = "1=1 ";
        $province_code = $request->province_code;
        if($province_code){$sqlWhere .= "and province_code = '$province_code' ";}

        // $city = DB::connection('sqlsrvHrd')
		// 			->table('list_city')
        //             ->selectRaw('city_code, trim(UPPER(city)) as city')
        //             ->orderBy('province', 'ASC')
		// 			->get();

        $result = DB::connection('sqlsrvHrd')
                ->table('list_city')
                ->selectRaw('city_code, trim(UPPER(city)) as city')
                ->whereRaw($sqlWhere)
                ->orderBy('province', 'ASC')
                ->get();

        return response()->json(['result' => $result]);

    }

    public function getSubDistrictByCity(Request $request)
    {
        $sqlWhere = "1=1 ";
        $province_code = $request->province_code;
        $city_code = $request->city_code;
        if($province_code){$sqlWhere .= "and province_code = '$province_code' ";}
        if($city_code){$sqlWhere .= "and city_code = '$city_code' ";}
                // $district = DB::connection('sqlsrvHrd')
		// 			->table('list_district')
        //             ->selectRaw('district_code, trim(UPPER(district)) as districts')
        //             ->orderBy('city', 'ASC')
		// 			->get();
            $result = DB::connection('sqlsrvHrd')
					->table('list_district')
                    ->selectRaw('district_code, trim(UPPER(district)) as districts')
                    ->whereRaw($sqlWhere)
                    ->orderBy('city', 'ASC')
					->get();

		    return response()->json(['result' => $result]);

    }

    public function getVillageBySubDistrict(Request $request)
    {
        $sqlWhere = "1=1 ";
        $province_code = $request->province_code;
        $city_code = $request->city_code;
        $district_code = $request->district_code;
        if($province_code){$sqlWhere .= "and province_code = '$province_code' ";}
        if($city_code){$sqlWhere .= "and city_code = '$city_code' ";}
        if($district_code){$sqlWhere .= "and district_code = '$district_code' ";}
        // $village = DB::connection('sqlsrvHrd')
		// 			->table('list_village')
        //             ->selectRaw('village_code, trim(UPPER(village)) as vilage')
        //             ->orderBy('city', 'ASC')
		// 			->get();
        $result = DB::connection('sqlsrvHrd')
					->table('list_village')
                    ->selectRaw('village_code, trim(UPPER(village)) as vilage')
                    ->whereRaw($sqlWhere)
                    ->orderBy('city', 'ASC')
					->get();

        return response()->json(['result' => $result]);
    }

    public function inactiveproject(Request $request){
        $project_id = $request->project_id;

        DB::connection('sqlsrvHrd')
        ->table('project_hdr')
        ->where('pr_id', '=', $project_id)
        ->update([
            'stat' => 'N',
        ]);

        return $this->sendResponse('Success','Successfully inactive project');

    }

    public function activeproject(Request $request){
        $project_id = $request->project_id;

        DB::connection('sqlsrvHrd')
        ->table('project_hdr')
        ->where('pr_id', '=', $project_id)
        ->update([
            'stat' => 'O',
        ]);

        return $this->sendResponse('Success','Successfully active project');
    }
}
