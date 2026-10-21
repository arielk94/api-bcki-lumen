<?php

namespace App\Http\Controllers\finance;

use App\Http\Controllers\Controller;
use App\Traits\getFilterTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BookApprovalController extends Controller
{
    use getFilterTrait;

    public function index()
    {
        // -- get sites
        $sites = DB::connection("sqlSrvBCKI")
            ->table('site as st')
            ->join('city_list as lc', 'st.city', 'lc.city_code')
            ->where('stat', 'Y')
            ->selectRaw("
                st.id_site,
                st.site_name,
                st.city as id_prov_city,
                lc.province_code as id_province
            ")
            ->get();

        // -- get province
        $provinces = DB::connection("sqlSrvBCKI")
            ->table('province_list')
            ->selectRaw("
                province_code as id_province,
                province
            ")
            ->get();

        // -- get sales area
        $salesArea = DB::connection("sqlSrvBCKI")
            ->table('Sales_area')
            ->selectRaw("
                id_area,
                name_area
            ")
            ->get();

        // -- sec_env_conf
        $aprv1 = $this->getEnvConf('FINANCE', 'APRV1');
        $aprv2 = $this->getEnvConf('FINANCE', 'APRV2');

        return response()->json([
            'sites' => $sites,
            'provinces' => $provinces,
            'sales_area' => $salesArea,
            'env_conf' => [
                'aprv_1' => $aprv1,
                'aprv_2' => $aprv2,
            ]
        ]);
    }

    public function getData(Request $request)
    {
        $province_id = $request->province_id ?? '';
        $city_id = $request->city_id ?? '';
        $site_id = $request->site_id ?? '';
        $cluster_id = $request->cluster_id ?? '';
        $sales_area_id = $request->sales_area_id ?? '';
        $date_from = $request->date_from ?? '';
        $date_to = $request->date_to ?? date('Y-m-d');

        try {
            $datas = DB::connection("sqlSrvBCKI")
                ->table('CustomerBooking as cb')
                ->selectRaw('
                    cb.*,
                    sl.NamaSales,
                    hu.house_name,
                    sc.cluster_name,
                    st.site_name
                ')
                ->join('Sales as sl', 'cb.sales_id', '=', 'sl.SalesId')
                ->join('house_unit as hu', 'cb.house_item', '=', 'hu.id_house')
                ->join('site_cluster as sc', 'hu.id_cluster', '=', 'sc.id_cluster')
                ->join('site as st', 'hu.id_site', '=', 'st.id_site')
                ->join('city_list as cl', 'st.city', '=', 'cl.city_code')
                ->join('province_list as pl', 'cl.province_code', '=', 'pl.province_code')
                ->where('status', 'I');

            // -- dropdown filter
            if ($province_id) {
                $datas->where('pl.province_code', $province_id);
            }

            if ($city_id) {
                $datas->where('cl.city_code', $city_id);
            }

            if ($site_id) {
                $datas->where('hu.id_site', $site_id);
            }

            if ($cluster_id) {
                $datas->where('hu.id_cluster', $cluster_id);
            }

            if ($sales_area_id) {
                $datas->where('sl.area', $sales_area_id);
            }

            // -- date filter
            if ($date_from) {
                $datas->where('cb.dt_created', '>=', $date_from . ' 00:00:00');
            }

            if ($date_to) {
                $datas->where('cb.dt_created', '<=', $date_to . ' 23:59:59');
            }

            $datas = $datas->orderBy('cb.book_id')
                ->get();

            return $this->sendResponse($datas, 'Successfully get Data');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getDiscountLimit(Request $request)
    {
        try {
            $discount_limit = DB::connection("sqlSrvBCKI")
                ->table('appl_constant')
                ->where('key_code', 'DISC_LIMIT')
                ->value("int1");

            return $this->sendResponse($discount_limit, 'Successfully get Data');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function approveBooking(Request $request)
    {
        
        $discount_limit = DB::connection("sqlSrvBCKI")
            ->table('appl_constant')
            ->where('key_code', 'DISC_LIMIT')
            ->value("int1");
        // dd($discount_limit);

        $book_type = DB::connection("sqlSrvBCKI")
            ->table('appl_constant')
            ->where('key_code', 'DISC_LIMIT')
            ->value("str1");
        // dd($book_type);

        $order = DB::connection('sqlSrvBCKI')
                ->table('CustomerBooking')
                ->where('book_id', $request->book_id)
                ->first();
        // dd($order);
        if ($order) {
            if($order->discount > $discount_limit){ //diskon diatas 5 juta, 2x approve
                // dd('diskon diatas 5 juta');
                if ($order->dt_aprv1 == '1900-01-01 00:00:00.000') {
                    $save = DB::connection('sqlSrvBCKI')
                            ->table('CustomerBooking')
                            ->where('book_id', $request->book_id)
                            ->update([
                                'dt_aprv1' => now(),
                                'aprv1_by' => getAuth($request)->user_id2
                            ]);
                        $whatsapp = $this->sendNotifBookingApproval($request->book_id);
                        return $this->sendResponse('Success Approved', 'Successfully saved');
                    
                    } else {
                        return $this->sendError('No Order sudah di setujui, please reload page', 'Error', 500);
                    }
                }
            }
        if($order->discount <= $discount_limit){ //diskon dibawah 5 juta, 1x approve
            // dd('diskon dibawah 5 juta');                
            if ($order->dt_aprv1 == '1900-01-01 00:00:00.000') {
                $save = DB::connection('sqlSrvBCKI')
                        ->table('CustomerBooking')
                        ->where('book_id', $request->book_id)
                        ->update([
                            'status' => 'U',
                            'dt_aprv1' => now(),
                            'aprv1_by' => getAuth($request)->user_id2
                        ]);
                
                $saveHouseUnit = DB::connection('sqlSrvBCKI')
                        ->table('house_unit')
                        ->where('id_house', $order->house_item)
                        ->update([
                            'sold_stat' => 'B'
                        ]);
                    return $this->sendResponse('Success Approved', 'Successfully saved');
                
                } else {
                    return $this->sendError('No Order sudah di setujui, please reload page', 'Error', 500);
                }
            }
        }
    
        public function approveBooking2(Request $request)
        {
            $order = DB::connection('sqlSrvBCKI')
                    ->table('CustomerBooking')
                    ->where('book_id', $request->book_id)
                    ->first();
            // dd($order);
            if ($order) {
                if ($order->dt_aprv2 == '1900-01-01 00:00:00.000') {
                    $save = DB::connection('sqlSrvBCKI')
                            ->table('CustomerBooking')
                            ->where('book_id', $request->book_id)
                            ->update([
                                'status' => 'U',
                                'dt_aprv2' => now(),
                                'aprv2_by' => getAuth($request)->user_id2
                            ]);
                    $saveHouseUnit = DB::connection('sqlSrvBCKI')
                            ->table('house_unit')
                            ->where('id_house', $order->house_item)
                            ->update([
                                'sold_stat' => 'B'
                            ]);
                        return $this->sendResponse('Success Approved', 'Successfully saved');
                    
                    } else {
                        return $this->sendError('No Order sudah di setujui, please reload page', 'Error', 500);
                    }
                }
            return $this->sendError('No Order tidak ditemukan', 'Error', 500);        
        }

        public function sendNotifBookingApproval($book_id)
        {
        try {

                $datas = DB::connection('sqlSrvBCKI')
                ->table('appl_constant')
                ->where('key_code', 'APRVBOOKING')
                ->get();

/* ============================================= send WA ============================================= */

                foreach ($datas as $data) {
                    $getEmp = $data->str1 ?? '';     // pastikan field ini sesuai
                    $getPhone = $data->str2 ?? '';   // pastikan field ini sesuai

                    if (!$getEmp || !$getPhone) {
                        continue; // skip jika data tidak lengkap
                    }
                

                $getLink = "bcki" . Carbon::now()->format('ymdH') . rand(10000, 99999);
                DB::connection('sqlsrv')
                ->table('sec_reset_password')
                ->insert([
                    'user_id2' => $getEmp ? $getEmp : '',
                    'link' => $getLink,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
                // $message = '*BANGUN CIPTA KARYA INDONESIA*'.'\n\n'.'Silahkan lakukan Approval 2 untuk '.$type_name.' Nomor : ' . $po_id . '\n' . 'Silahkan login pada https://viva.kencana.org/approve untuk Approval 2. Terima kasih '; 
                // /https://viva.kencana.org/approve/09210216/VIVA23081509mardian4673958770904091
                $message = "*BANGUN CIPTA KARYA INDONESIA*\n\n" .
                "Silahkan lakukan Approval 2 untuk booking Nomor: {$book_id}\n" .
                "Silahkan login pada https://viva.kencana.org/approve/{$getEmp}/{$getLink} untuk Approval 2.\nTerima kasih.";
                // dd($message);
                $url = 'https://notifapi.com/send_message';
                // dd($message);
                $data = array(
                "phone_no" => $getPhone,
                "key"      => '397bb50fd21e7c0f41b71eed483006ac45d42a47d6d43b79',
                "message"  => $message
                );

                $data_string = json_encode($data,1);

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_VERBOSE, 0);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                curl_setopt($ch, CURLOPT_TIMEOUT, 360);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data_string),
                    'Authorization: Basic dXNtYW5ydWJpYW50b3JvcW9kcnFvZHJiZWV3b293YToyNjM3NmVkeXV3OWUwcmkzNDl1ZA=='
                ));

                $res = curl_exec($ch);
                curl_close($ch);

                if($res == 'Success' || $res == 'success' ){
                $stat = 1;
                $msg = $res;
                } else {
                $stat = 0;
                $msg = $res;
                }

                $dataRes = ['stat' => $stat, 'msg' => $msg];

            }

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function rejectBooking(Request $request)
    {
        $order = DB::connection('sqlSrvBCKI')
                ->table('CustomerBooking')
                ->where('book_id', $request->book_id)
                ->first();
        // dd($order);
        if ($order) {
            if ($order->dt_reject1 == '1900-01-01 00:00:00.000') {
                $save = DB::connection('sqlSrvBCKI')
                        ->table('CustomerBooking')
                        ->where('book_id', $request->book_id)
                        ->update([
                            'status' => 'X',
                            'dt_reject1' => now(),
                            'dt_reject2' => now(),
                            'reject1_by' => getAuth($request)->user_id2,
                            'reject2_by' => getAuth($request)->user_id2
                        ]);
                $saveHouseUnit = DB::connection('sqlSrvBCKI')
                        ->table('house_unit')
                        ->where('id_house', $order->house_item)
                        ->update([
                            'sold_stat' => 'V'
                        ]);
                    return $this->sendResponse('Success Rejected', 'Successfully saved');
                
                } else {
                    return $this->sendError('No Order sudah di setujui, please reload page', 'Error', 500);
                }
            }
        return $this->sendError('No Order tidak ditemukan', 'Error', 500);
    }

    public function rejectBooking2(Request $request)
    {
        $order = DB::connection('sqlSrvBCKI')
                ->table('CustomerBooking')
                ->where('book_id', $request->book_id)
                ->first();
        // dd($order);
        if ($order) {
            if ($order->dt_reject2 == '1900-01-01 00:00:00.000') {
                $save = DB::connection('sqlSrvBCKI')
                        ->table('CustomerBooking')
                        ->where('book_id', $request->book_id)
                        ->update([
                            'status' => 'X',
                            'dt_reject1' => now(),
                            'dt_reject2' => now(),
                            'reject1_by' => getAuth($request)->user_id2,
                            'reject2_by' => getAuth($request)->user_id2
                        ]);
                $saveHouseUnit = DB::connection('sqlSrvBCKI')
                        ->table('house_unit')
                        ->where('id_house', $order->house_item)
                        ->update([
                            'sold_stat' => 'V'
                        ]);        
                    return $this->sendResponse('Success Rejected', 'Successfully saved');
                
                } else {
                    return $this->sendError('No Order sudah di setujui, please reload page', 'Error', 500);
                }
            }
        return $this->sendError('No Order tidak ditemukan', 'Error', 500);
    }
}
