<?php

namespace App\Http\Controllers\transaction;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function getListBooking(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $id_site = $request->id_site;
            $id_cluster = $request->id_cluster;
            $start_date = $request->start_date;
            $end_date = $request->end_date;
            $active_flag = $request->active_flag;


            if (!empty($id_site)) {
                $sqlWhere = $sqlWhere . " and house_unit.id_site = " . "'" . $id_site . "'";
            }
            if (!empty($id_prov_city)) {
                $sqlWhere = $sqlWhere . " and house_unit.id_cluster = " . "'" . $id_cluster . "'";
            }
            if (!empty($id_prov_city)) {
                $sqlWhere = $sqlWhere . " and house_unit.id_cluster = " . "'" . $id_cluster . "'";
            }
            if (!empty($active_flag)) {
                $sqlWhere = $sqlWhere . " and CustomerBooking.active_flag = " . "'" . $active_flag . "'";
            }else{
                $sqlWhere = $sqlWhere . " and CustomerBooking.active_flag = 'Y'";
            }

            if (!empty($start_date)){
                if (!empty($end_date)){
                    $sqlWhere .= " and CustomerBooking.dt_created BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
                }else{
                    $sqlWhere .= " and CustomerBooking.dt_created BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND GETDATE()";
                }
            }

            // dd($sqlWhere);
            $datas = DB::connection("sqlSrvBCKI")
                ->table('CustomerBooking')
                ->selectRaw("
                                CustomerBooking.*, 
                            	house_unit.id_site,
                                site.site_name, 
                                house_unit.id_cluster, 
                                site_cluster.cluster_name, 
                                house_unit.house_name
                            ")
                ->leftjoin('house_unit', 'CustomerBooking.house_item', '=', 'house_unit.id_house')
                ->leftjoin('site_cluster', 'house_unit.id_cluster', '=', 'site_cluster.id_cluster')
                ->leftjoin('site', 'house_unit.id_site', '=', 'site.id_site')
                ->whereRaw($sqlWhere)
                ->get();
            return $this->sendResponse($datas, 'Successfully get Booking Data');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function storeBooking(Request $request)
    {
        $status = '';
        $tr_type = '';
        $nomor_hp = preg_replace('/\D+/', '', $request->nomor_hp);
        
        if (!empty($request->discount)) {
            $discount = $request->discount;
        } else {
            $discount = 0;
        }

        if($discount == 0 || $discount === 0){
            $status = 'U';
            $tr_type = 'BOOK';
        }else{
            $status = 'I';
            $tr_type = 'NEGO';
        }

        if (!empty($request->exp_date)) {
            $exp_date = $request->exp_date;
        } else {
            $exp_date = 0;
        }

        $salesId = DB::connection("sqlSrvBCKI")
                ->table('Sales')
                ->whereRaw("idemployee = " . "'" . getAuth($request)->user_id2 . "'")
                ->value("SalesId");
        
        // dd($salesId);
        
        $telpSPV = DB::connection("sqlSrvBCKI")
                ->table('Sales as S')
                ->join('Sales_area as SA', 'S.area', '=', 'SA.id_area')
                ->join('Sales as S2', 'SA.spv_area', '=', 'S2.SalesId')
                ->where('S.SalesId', $salesId)
                ->selectRaw("S.SalesId, S.NamaSales, SA.spv_area, S2.NamaSales as SPV, S2.Telepon, S2.idemployee")
                ->first();

        $idSPV = $telpSPV->idemployee ?? null;
        $phoneSPV = $telpSPV->Telepon ?? null;

        // DB::connection('sqlSrvBCKI')->beginTransaction();

        try {
            $book_id = $this->generateBookID($request);
            // dd($book_id);

            $save = DB::connection('sqlSrvBCKI')
                ->table('CustomerBooking')
                ->insert([
                    'book_id' => $book_id,
                    'nama_customer' => $request->nama_customer,
                    'alamat_customer' => $request->alamat_customer ?? '',
                    'nomor_hp' => $nomor_hp,
                    'telepon' => $request->telepon ?? '',
                    'sales_id' => $salesId ?? '',
                    'house_item' => $request->house_item,
                    'price_list' => $request->price_list,
                    'discount' => $discount,
                    'payterm' => $request->payterm,
                    'bank' => $request->bank ?? '',
                    'exp_date' => now()->addDays($exp_date)->format('Y-m-d H:i:s.v'),
                    'status' => $status,
                    'dt_created' => now(),
                    'created_by' => getAuth($request)->user_id2,
                    'tr_type' => $tr_type,
                    'active_flag' => 'Y',
                    'remark' => $request->remark ?? ''
                ]);
            // DB::connection('sqlSrvBCKI')->commit();
            
            $book_id = trim($book_id);
            $getEmp = trim($idSPV);
            $getPhone = trim($phoneSPV);

            if($discount > 0){
                $whatsapp = $this->sendNotifBookingApproval($book_id, $getEmp, $getPhone);
                
                if($whatsapp['stat'] == 1){
                    // return $this->sendResponse($save, 'Successfully saved Nego & Send Notifikasi');
                    return $this->sendResponse($whatsapp['msg'], 'Successfully saved Nego & Send Notifikasi');
                }else{
                    return $this->sendResponse($whatsapp['msg'], 'Successfully saved Nego');
                }
            }else{
                $save = DB::connection('sqlSrvBCKI')
                        ->table('house_unit')
                        ->where('id_house', $request->house_item)
                        ->update([
                            'sold_stat' => 'B'
                        ]);

                return $this->sendResponse($save, 'Successfully saved Book');
            }
        } catch (\Exception $e) {
            // DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function generateBookID($request)
    {
        // $dateYear = substr( date("Y"), 0);
        $dateYear = date("Y");
        $dateMonth = date("m");
        $dateDay = date("d");
        $book_id = '';

        $getLastTrID = DB::connection('sqlSrvBCKI')
            ->table('CustomerBooking')
            ->where('book_id', 'like', "%" . $dateYear . $dateMonth . $dateDay . "%")
            ->max('book_id');

        if ($getLastTrID) {
            $seq = substr(trim($getLastTrID), -3) + 1;
            $book_id = $dateYear . $dateMonth . $dateDay . str_pad($seq, 3, '0', STR_PAD_LEFT);
        } else {
            $book_id = $dateYear . $dateMonth . $dateDay . '001';
        }

        return $book_id;
    }

    

    public function sendNotifBookingApproval($book_id, $getEmp, $getPhone)
    {
        try {
            $getLink = "bcki" . now()->format('ymdH') . rand(10000, 99999);
            DB::connection('sqlsrv')
            ->table('sec_reset_password')
            ->insert([
                'user_id2' => $getEmp ? $getEmp : '',
                'link' => $getLink,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $message = "*FASINDO PROPERTI INDONESIA*\n\n" .
            "Silahkan lakukan Approval untuk booking Nomor: {$book_id}\n" .
            "Silahkan login pada https://viva.kencana.org/approve/{$getEmp}/{$getLink} untuk Approval 1.\nTerima kasih.";
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
            
            return $dataRes;

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

}