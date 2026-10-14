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
use App\Traits\getFilterTrait;

class VisitReportController extends Controller
{

    use getFilterTrait;
    
    public function index()
    {
        
        $userGroup = $this->queryGetUserGroup();
        $region = $this->queryGetRegions($userGroup);
        $office = $this->queryGetOffices($userGroup);
        $sales = $this->queryGetSales($userGroup);

        return response()->json(['userGroup' => $userGroup, 'region' => $region, 'office' => $office, 'sales' => $sales]);

    }

    public function searchData(Request $request)
    {
        $region = $request->region;
        $office = $request->office;
        $sales = $request->sales;
        $customer = $request->customer;
        $startDate = $request->startDate;
        $endDate = $request->endDate;
        $sqlWhere = '1=1 ';
        $sqlWhere2 = '1=1 ';

        if($region){
            $sqlWhere .= " and c.region = '$region'";
        }

        if($office){
            $sqlWhere .= " and a.office_id = '$office'";
        }

        if($sales){
            $sqlWhere .= " and a.sales_id = '$sales'";
            $sqlWhere2 .= " and a.sales_id = '$sales'";
        }

        if($customer){
            $sqlWhere2 .= " and a.customer_id = '$customer'";
        }
        
        if($startDate){
            $sqlWhere .= " and a.tr_date >= '$startDate'";
            $sqlWhere2 .= " and a.tr_date >= '$startDate'";
            if($endDate){
                $sqlWhere .= " and a.tr_date between '$startDate' and '$endDate'";
                $sqlWhere2 .= " and a.tr_date between '$startDate' and '$endDate'";
            }
        }

        $activity_hdr = DB::connection('sqlSrvFPI')
                        ->table('sales_activity_hdr as a')
                        ->selectRaw("a.tr_id, format(a.tr_date, 'hh:mm tt dd/MM/yyyy') as tr_date, upper(a.sales_id) as sales_id, e.name, a.office_id, case when b.division = 'R' then 'PROJECT' else 'RETAIL' end as division, c.office, count(a.tr_id) as total, 
                        SUM(CASE WHEN d.take_order = 'Y' THEN 1 ELSE 0 END) AS take_order, max(cast(d.vehicle_km as float)) - min(cast(d.vehicle_km as float)) as total_km, format(max(cast(d.gps_distance as float)) - min(cast(d.gps_distance as float)), 'N2') as total_km_gps")
                        ->leftJoin('Sales as b', 'a.sales_id', '=', 'b.SalesId')
                        ->leftJoin('branch_office as c', 'a.office_id', '=', 'c.office_id')
                        ->join('sales_activity_dtl as d', 'a.tr_id', '=', 'd.tr_id')
                        ->leftJoin('hrd_ol.dbo.employee as e', 'a.user_id2', '=', 'e.user_id2')
                        ->whereRaw($sqlWhere)
                        ->orderBy('a.tr_date', 'desc')
                        ->groupByRaw("a.tr_id, a.tr_date, a.sales_id, e.name, a.office_id, case when b.division = 'R' then 'PROJECT' else 'RETAIL' end, c.office")
                        ->get();

        $activity_dtl = DB::connection('sqlSrvFPI')
                        ->table('sales_activity_dtl as a')
                        ->selectRaw("a.tr_id, a.seq_id, upper(a.customer_name) as customer_name, a.customer_address, a.customer_city, upper(a.gps_address) as gps_address, a.latitude, a.longitude, a.remark, a.start_end_point, a.vehicle_km")
                        ->leftjoin('sales as b', 'a.sales_id', '=', 'b.SalesId')
                        ->join('sales_activity_hdr as d', 'a.tr_id', '=', 'd.tr_id')
                        ->join('hrd_ol.dbo.employee as e', 'd.user_id2', '=', 'e.user_id2')
                        ->whereRaw($sqlWhere2)
                        ->get();

        $distance = $this->getMapboxDistance('112.4214405','-7.5107182','112.630893','-7.980279');

        // $response = Http::get("https://api.mapbox.com/directions/v5/mapbox/driving/112.4214405,-7.5107182;112.630893,-7.980279", [
        //     'alternatives' => 'false',
        //     'geometries' => 'geojson',
        //     'language' => 'en',
        //     'overview' => 'full',
        //     'steps' => 'true',
        //     'access_token' => 'pk.eyJ1IjoiaXQtdml2YSIsImEiOiJjbGlsN3hpZmMwZWJnM2VtbW1td3VpcnQ0In0.UKADhbm0cwuePXLF3pJJQA',
        // ]);

        // dd($response->json());
        // dd($distance['routes'][0]['distance']);

        return response()->json(['activity_hdr' => $activity_hdr, 'activity_dtl' => $activity_dtl]);

    }

    public function getCustomer(Request $request)
    {
        $customer = $request->customerId;
        if($customer != ''){
            $result = DB::connection('sqlSrvFPI')
                        ->table('Customer')
                        ->selectRaw("top 20 CustomerId, (NamaCustomer + '(' + CustomerId + ')') as NamaCustomer")
                        ->whereRaw("active_flag = 'Y' and NamaCustomer like '%$customer%' or CustomerId like '%$customer%'")
                        ->orderBy('NamaCustomer', 'asc')
                        ->get();
            return response()->json(['result' => $result]);
        } else {
            $result = DB::connection('sqlSrvFPI')
                        ->table('Customer')
                        ->selectRaw("top 20 CustomerId, (NamaCustomer + '(' + CustomerId + ')') as NamaCustomer")
                        ->where('active_flag', '=', 'Y')
                        ->orderBy('NamaCustomer', 'asc')
                        ->get();
            return response()->json(['result' => $result]);
        }

    }

    public function getOffice(Request $request)
    {
        $id = $request->region;

        if($id){
            $office = DB::connection('sqlSrvFPI')
                    ->table('branch_office')
                    ->selectRaw("office_id, concat(trim(office), '(', office_id, ')' ) as office")
                    ->where('region', '=', $id)
                    ->where('active_flag', '=', 'Y')
                    ->get();

            return response()->json(['office' => $office]);
        } else {
            $office = DB::connection('sqlSrvFPI')
                    ->table('branch_office')
                    ->selectRaw("office_id, concat(trim(office), '(', office_id, ')' ) as office")
                    ->where('active_flag', '=', 'Y')
                    ->get();

            return response()->json(['office' => $office]);
        }

    }

    public function getSales(Request $request)
    {
        $id = $request->office;

        if($id){
            $sales = DB::connection('sqlSrvFPI')
                    ->table('sales')
                    ->selectRaw("SalesId, concat(trim(NamaSales), '(', SalesId, ')' ) as NamaSales")
                    ->where('office_id', '=', $id)
                    ->where('active_flag', '=', 'Y')
                    ->get();

            return response()->json(['sales' => $sales]);
        } else {
            $sales = DB::connection('sqlSrvFPI')
                    ->table('sales')
                    ->selectRaw("SalesId, concat(trim(NamaSales), '(', SalesId, ')' ) as NamaSales")
                    ->where('active_flag', '=', 'Y')
                    ->get();

            return response()->json(['sales' => $sales]);
        }

    }

    public function getDetailImages(Request $request)
    {
        $tr_id = $request->tr_id;
        $seqId = $request->seqId;

        $result = DB::connection('sqlSrvFPI')
                    ->table('sales_activity_image')
                    ->where('tr_id', '=', $tr_id)
                    ->where('seq_id', '=', $seqId)
                    ->get();

        return response()->json(['result' => $result]);

    }

    public function addDetailImages(Request $request) 
    {
        
        $tr_id = $request->tr_id;
        $seqId = $request->seqId;
        $image = $request->image;
        $remark = $request->remark;
        $price = $request->price;

        try {
            
            $last = DB::connection('sqlSrvFPI')
                    ->table('sales_activity_image')
                    ->where('tr_id', $tr_id)
                    ->where('seq_id', $seqId)
                    ->max('seq_id_img');

            $medium = Image::make($image)->encode('jpg', 75);
            $nameFinal = Str::random(40). '.jpg';

            Storage::disk('public')->put('sales-activity/KMB/' . $nameFinal, $medium->__toString());

            DB::connection('sqlSrvFPI')
            ->table('sales_activity_image')
            ->insert([
                'tr_id' => $tr_id,
                'seq_id' => $seqId,
                'seq_id_img' => $last ? $last + 1 : 1,
                'price' => $price,
                'img_url' => $nameFinal,
                'created_date' => now(),
                'modified_date' => now(),
                'created_by' => getAuth($request)->user_id2,
                'remark' => $remark,
            ]);

        } catch (\Throwable $th) {
            return response()->json(['err' => $th]);
        }

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

}
