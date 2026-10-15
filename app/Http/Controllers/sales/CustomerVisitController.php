<?php

namespace App\Http\Controllers\sales;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use DB;
use DateTime;
use Image;
use Storage;
use Illuminate\Support\Facades\Http;
use Str;

class CustomerVisitController extends Controller
{
    
    public function index()
    {
    
        $last_visit = NULL;
        $last = NULL;
        $lat = "";
        $long = "";
        $officeSales = [];
        $office = NULL;

        // $salesid = DB::connection('sqlsrv')
        //             ->table('sec_env_conf')
        //             ->select('var_value')
        //             ->where('entity_id', '=', 'bcki')
        //             ->where('var_id', '=', 'SALESID' )
        //             ->where('active_flag', '=', 'Y' )
        //             ->where('user_id2', '=', getAuth($request)->user_id2 )
        //             ->value('var_value');

        $salesid = DB::connection('sqlsrvHrd')
                    ->table('employee')
                    ->select('salesid')
                    ->value('salesid');

        $category = DB::connection('sqlSrvKmb')
                    ->table('appl_constant')
                    ->selectRaw("TRIM(key_id) as key_id, TRIM(str1) as str1, TRIM(str2) as str2")
                    ->where('key_id', '=', 'SACATEG')
                    ->get();

        $province = DB::connection('sqlsrvHrd')
                    ->table('list_province')
                    ->selectRaw("province_code, UPPER(TRIM(province)) as province")
                    ->get();

        $city = DB::connection('sqlsrvHrd')
                    ->table('list_city')
                    ->selectRaw("city_code, UPPER(TRIM(city)) as city")
                    ->get();

        $district = DB::connection('sqlsrvHrd')
                    ->table('list_district')
                    ->selectRaw("district_code, UPPER(TRIM(district)) as district")
                    ->get();

        $village = DB::connection('sqlsrvHrd')
                    ->table('list_village')
                    ->selectRaw("village_code, UPPER(TRIM(village)) as village")
                    ->get();

        $kind_of_bussiness = DB::connection('sqlSrvKmb')
                    ->table('appl_constant')
                    ->selectRaw("TRIM(key_id) as key_id, TRIM(str1) as str1")
                    ->where('key_id', '=', 'JUSAHA')
                    ->get();

        if ($salesid) {

            $sales = DB::connection('sqlSrvBCKI')
                    ->table('sales')
                    ->where('SalesId', $salesid)
                    ->where('active_flag', '=', 'Y')
                    ->first();

            $office = DB::connection('sqlSrvBCKI')
                    ->table('branch_office')
                    ->where('office_id', '=', $sales->office_id)
                    ->where('active_flag', '=', 'Y')
                    ->first();
        }

        return response()->json([
            'sales' => $salesid ? $salesid : null, 
            'last' => $last, 
            'office' => $office ? $office : NULL, 
            'category' => $category,
            'province' => $province,
            'kind_of_bussiness' => $kind_of_bussiness,
            'city' => $city,
            'district' => $district,
            'village' => $village,
        ]);

    }

    public function submitData(Request $request)
    {
        
        $customerId = $request->customer_id;
        $custName = $request->custName;
        $custKtpNpwp = $request->custKtpNpwp;
        $custPhone = $request->custPhone;
        $custEmail = $request->custEmail;
        $custAddress = $request->custAddress;
        $custProvince = $request->custProvince;
        $custCity = $request->custCity;
        $custDistrict = $request->custDistrict;
        $custVillage = $request->custVillage;
        $custBussiness = $request->custBussiness;
        $custBussinessStatus = $request->custBussinessStatus;
        $custBussinessType = $request->custBussinessType;
        $custTaxType = $request->custTaxType;
        $custOfficeType = $request->custOfficeType;
        $custOfficeOwnership = $request->custOfficeOwnership;
        $custType = $request->custType;
        $checkboxCar = $request->checkboxCar;
        $checkbox1 = $request->checkbox1;
        $checkbox2 = $request->checkbox2;
        $checkbox3 = $request->checkbox3;
        $checkbox4 = $request->checkbox4;
        $checkbox5 = $request->checkbox5;
        $checkbox6 = $request->checkbox6;
        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $currentLocation = $request->currentLocation;
        $remark = $request->remark;
        $images = $request->images;
        $speedoKmModel = $request->speedoKmModel;
        $checkpoint = $request->checkpoint;
        $salesid = $request->salesid;
        $office_id = '';

        $payload = (object)[
            'customer_id' => $customerId,
            'custName' => $custName,
            'custKtpNpwp' => $custKtpNpwp,
            'custPhone' => $custPhone,
            'custEmail' => $custEmail,
            'custAddress' => $custAddress,
            'custProvince' => $custProvince,
            'custCity' => $custCity,
            'custDistrict' => $custDistrict,
            'custVillage' => $custVillage,
            'custBussiness' => $custBussiness,
            'custBussinessStatus' => $custBussinessStatus,
            'custBussinessType' => $custBussinessType,
            'custTaxType' => $custTaxType,
            'custOfficeType' => $custOfficeType,
            'custOfficeOwnership' => $custOfficeOwnership,
            'custType' => $custType,
            'checkboxCar' => $checkboxCar,
            'checkbox1' => $checkbox1,
            'checkbox2' => $checkbox2,
            'checkbox3' => $checkbox3,
            'checkbox4' => $checkbox4,
            'checkbox5' => $checkbox5,
            'checkbox6' => $checkbox6,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'checkpoint' => $checkpoint,
            'currentLocation' => $currentLocation,
            'remark' => $remark,
            'images' => $images,
            'speedoKmModel' => $speedoKmModel,
            'salesid' => $salesid,
            'user_id2' => getAuth($request)->user_id2,
        ];

        if($payload->salesid != null | $payload->salesid != '') {
            
            $office_id = DB::connection('sqlSrvBCKI')
                    ->table('sales')
                    ->where('SalesId', $payload->salesid)
                    ->first();

        }

        $checkTodayActivity = DB::connection('sqlSrvBCKI')
                    ->table('sales_activity_hdr')
                    ->where('user_id2', $payload->user_id2)
                    ->whereDate('tr_date', '=', now()->toDateString())
                    ->first();

        // dd(now()->toDateString());

        if (!$checkTodayActivity) {

            $checkLastTrId = DB::connection('sqlSrvBCKI')
                    ->table('sales_activity_hdr')
                    ->whereDate('tr_date', '=', now()->toDateString())
                    ->first();

            if (!$checkLastTrId) {

                $trId = 'SA/bcki/' . date('Ymd') . '/' . '0001';

            } else {
                $tr_id = $checkLastTrId->tr_id;
                $parts = explode('/', $tr_id);
                $last_number = $parts[count($parts) - 1];
                $new_number = str_pad((int)$last_number + 1, 4, '0', STR_PAD_LEFT);
                $parts[count($parts) - 1] = $new_number;
                $trId = implode('/', $parts);
            }

            $insertHeader = DB::connection('sqlSrvBCKI')
                        ->table('sales_activity_hdr')
                        ->insert([
                            'entity_id' => 'bcki',
                            'tr_id' => $trId,
                            'sales_id' => $payload->salesid,
                            'office_id' => $office_id ? $office_id->office_id : null,
                            'user_id2' => $payload->user_id2,
                            'tr_date' => Carbon::now(),
                            'employee_id' => getAuth($request)->idemployee,
                        ]);
                
            $insertDetail = DB::connection('sqlSrvBCKI')
                        ->table('sales_activity_dtl')
                        ->insert([
                            'entity_id' => 'bcki',
                            'sales_id' => $payload->salesid ? $payload->salesid : '',
                            'tr_id' => $trId,
                            'seq_id' => 1,
                            'customer_id' => $payload->customer_id ? $payload->customer_id : 'NEW',
                            'customer_name' => $payload->custName,
                            'customer_ktp_npwp' => $payload->custKtpNpwp,
                            'customer_phone' => $payload->custPhone,
                            'customer_email' => $payload->custEmail,
                            'customer_address' => $payload->custAddress,
                            'customer_province' => $payload->custProvince,
                            'customer_city' => $payload->custCity,
                            'customer_district' => $payload->custDistrict,
                            'customer_village' => $payload->custVillage,
                            'customer_kind_of_business' => $payload->custBussiness,
                            'customer_business_status' => $payload->custBussinessStatus,  
                            'customer_business_type' => $payload->custBussinessType,
                            'customer_tax_type' => $payload->custTaxType,
                            'customer_office_type' => $payload->custOfficeType,
                            'customer_business_ownership' => $payload->custOfficeOwnership,  
                            'customer_type' => $payload->custType,
                            'sales_vehicle' => $payload->checkboxCar,
                            'start_end_point' => $payload->checkpoint,  
                            'product_offer' => $payload->checkbox1 ? 'Y' : '',
                            'take_order' => $payload->checkbox2 ? 'Y' : '',
                            'promo_info' => $payload->checkbox3 ? 'Y' : '',
                            'penagihan' => $payload->checkbox4 ? 'Y' : '',
                            'customer_visit' => $payload->checkbox5 ? 'Y' : '',
                            'latitude' => $payload->latitude,
                            'longitude' => $payload->longitude,
                            'gps_address' => $payload->currentLocation,
                            'remark' => $payload->remark,
                            'tr_date' => Carbon::now(),
                            'user_id2' => getAuth($request)->user_id2,
                            'vehicle_km' => $speedoKmModel,
                            'customer_new' => $checkbox6 ? 'Y' : '',
                            'gps_distance' => 0
                        ]);

            foreach ($payload->images as $key => $value) {

                $last = DB::connection('sqlSrvBCKI')
                        ->table('sales_activity_image')
                        ->where('tr_id', $trId)
                        ->max('seq_id_img');
    
                $medium = Image::make($value['src'])->encode('jpg', 75);
                $nameFinal = Str::random(40). '.jpg';
    
                Storage::disk('public')->put('sales-activity/bcki/' . $nameFinal, $medium->__toString());
    
                DB::connection('sqlSrvBCKI')
                ->table('sales_activity_image')
                ->insert([
                    'tr_id' => $trId,
                    'seq_id' => '1',
                    'seq_id_img' => $last ? $last + 1 : 1,
                    'price' => $value['price'] ? $value['price'] : 0,
                    'img_url' => $nameFinal,
                    'created_date' => now(),
                    'modified_date' => now(),
                    'created_by' => getAuth($request)->user_id2,
                    'remark' => $value['remark'] ? $value['remark'] : '',
                ]);

            }

            return response()->json(['result' => 'OK'], 200);

        } else {

            $checkLastDetail = DB::connection('sqlSrvBCKI')
                        ->table('sales_activity_dtl')
                        ->where('sales_id', $payload->salesid ?? '')
                        ->where('tr_id', $checkTodayActivity->tr_id)
                        ->orderByDesc('seq_id')
                        ->first();
                        
            $seq_id = $checkLastDetail ? $checkLastDetail->seq_id + 1 : 1;

            $distance = $this->getMapboxDistance($checkLastDetail->longitude, $checkLastDetail->latitude, $payload->longitude, $payload->latitude);

            $distanceInKm = $distance['routes'][0]['distance'] / 1000;

            $insertDetail = DB::connection('sqlSrvBCKI')
                        ->table('sales_activity_dtl')
                        ->insert([
                            'entity_id' => 'bcki',
                            'sales_id' => $payload->salesid ? $payload->salesid : '',
                            'tr_id' => $checkTodayActivity->tr_id,
                            'seq_id' => $seq_id,
                            'customer_id' => $payload->customer_id ? $payload->customer_id : 'NEW',
                            'customer_name' => $payload->custName,
                            'customer_ktp_npwp' => $payload->custKtpNpwp,
                            'customer_phone' => $payload->custPhone,
                            'customer_email' => $payload->custEmail,
                            'customer_address' => $payload->custAddress,
                            'customer_province' => $payload->custProvince,
                            'customer_city' => $payload->custCity,
                            'customer_district' => $payload->custDistrict,
                            'customer_village' => $payload->custVillage,
                            'customer_kind_of_business' => $payload->custBussiness,
                            'customer_business_status' => $payload->custBussinessStatus,  
                            'customer_business_type' => $payload->custBussinessType,
                            'customer_tax_type' => $payload->custTaxType,
                            'customer_office_type' => $payload->custOfficeType,
                            'customer_business_ownership' => $payload->custOfficeOwnership,  
                            'customer_type' => $payload->custType,
                            'sales_vehicle' => $payload->checkboxCar,
                            'start_end_point' => $payload->checkpoint,  
                            'product_offer' => $payload->checkbox1 ? 'Y' : '',
                            'take_order' => $payload->checkbox2 ? 'Y' : '',
                            'promo_info' => $payload->checkbox3 ? 'Y' : '',
                            'penagihan' => $payload->checkbox4 ? 'Y' : '',
                            'customer_visit' => $payload->checkbox5 ? 'Y' : '',
                            'latitude' => $payload->latitude,
                            'longitude' => $payload->longitude,
                            'gps_address' => $payload->currentLocation,
                            'remark' => $payload->remark,
                            'tr_date' => Carbon::now(),
                            'user_id2' => getAuth($request)->user_id2,
                            'vehicle_km' => $speedoKmModel,
                            'customer_new' => $checkbox6 ? 'Y' : '',
                            'gps_distance' => $distanceInKm
                        ]);

            foreach ($payload->images as $key => $value) {

                $last = DB::connection('sqlSrvBCKI')
                        ->table('sales_activity_image')
                        ->where('tr_id', $checkTodayActivity->tr_id)
                        ->where('seq_id', $seq_id)
                        ->max('seq_id_img');
    
                $medium = Image::make($value['src'])->encode('jpg', 75);
                $nameFinal = Str::random(40). '.jpg';
    
                Storage::disk('public')->put('sales-activity/bcki/' . $nameFinal, $medium->__toString());
    
                DB::connection('sqlSrvBCKI')
                ->table('sales_activity_image')
                ->insert([
                    'tr_id' => $checkTodayActivity->tr_id,
                    'seq_id' => $seq_id,
                    'seq_id_img' => $last ? $last + 1 : 1,
                    'price' => $value['price'] ? $value['price'] : 0,
                    'img_url' => $nameFinal,
                    'created_date' => now(),
                    'modified_date' => now(),
                    'created_by' => getAuth($request)->user_id2,
                    'remark' => $value['remark'] ? $value['remark'] : '',
                ]);
            }

            return response()->json(['result' => 'OK'], 200);

        }
        
    }


    public function provinceOnChange(Request $request)
    {
        $province = $request->province;
        $city = DB::connection('sqlsrvHrd')
                    ->table('list_city')
                    ->selectRaw("city_code, UPPER(TRIM(city)) as city")
                    ->where('province_code', '=', $province)
                    ->get();
        return response()->json(['city' => $city]);
    }

    public function cityOnChange(Request $request)
    {
        $city = $request->city;
        $district = DB::connection('sqlsrvHrd')
                    ->table('list_district')
                    ->selectRaw("district_code, UPPER(TRIM(district)) as district")
                    ->where('city_code', '=', $city)
                    ->get();
        return response()->json(['district' => $district]);
    }

    public function districtOnChange(Request $request)
    {
        $district = $request->district;
        $village = DB::connection('sqlsrvHrd')
                    ->table('list_village')
                    ->selectRaw("village_code, UPPER(TRIM(village)) as village")
                    ->where('district_code', '=', $district)
                    ->get();
        return response()->json(['village' => $village]);
    }

    public function getCustomer(Request $request)
    {

        $customer_id = $request->customer_id;

        $customer = DB::connection('sqlSrvBCKI')
                    ->table('customer')
                    ->where('CustomerId', '=', $customer_id)
                    ->where('active_flag', '=', 'Y')
                    ->first();

        return response()->json(['customer' => $customer]);

    }

    public function getCustomerById(Request $request)
    {

        $id = $request->id;

        $result = DB::connection('sqlSrvBCKI')
                    ->table('customer as a')
                    ->selectRaw("top 20 a.CustomerId, upper(a.CustomerId + ' - ' + a.NamaCustomer + ' - ' + b.city) as NamaCustomer")
                    ->join('hrd_ol.dbo.list_city as b', 'a.Kota', 'b.city_code')
                    ->whereRaw("a.active_flag = 'Y' and a.NamaCustomer like '%$id%' or a.CustomerId like '%$id%'")
                    ->where('a.active_flag', '<>', "N")
                    ->orderBy('a.NamaCustomer', 'asc')
                    ->get();

        return response()->json(['result' => $result]);
        
    }

    public function getMapboxDistance($lngStart, $latStart, $lngEnd, $latEnd) 
    {
        
        $response = Http::get("https://api.mapbox.com/directions/v5/mapbox/driving/$lngStart,$latStart;$lngEnd,$latEnd", [
            'alternatives' => 'false',
            'geometries' => 'geojson',
            'language' => 'en',
            'overview' => 'full',
            'steps' => 'true',
            // 'access_token' => 'pk.eyJ1IjoiaXQtdml2YSIsImEiOiJjbGlsN3hpZmMwZWJnM2VtbW1td3VpcnQ0In0.UKADhbm0cwuePXLF3pJJQA',
        ]);

        $data = $response->json();

        return $data;

    }

    public function getAddress(Request $request)
    {
        $lat = $request->lat;
        $lon = $request->lon;

        // $response = Http::get("https://api.mapbox.com/geocoding/v5/mapbox.places/$lon,$lat.json?access_token=pk.eyJ1IjoiaXQtdml2YSIsImEiOiJjbGlsN3hpZmMwZWJnM2VtbW1td3VpcnQ0In0.UKADhbm0cwuePXLF3pJJQA");

        return $response->json();

    }

}
