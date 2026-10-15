<?php

namespace App\Http\Controllers\kwitansi;

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

class KwitansiController extends Controller
{
    
    public function index(Request $request)
    {
                
        $site = DB::connection('sqlSrvBCKI')
                    ->table('site')
                    ->selectRaw("id_site, UPPER(TRIM(site_name)) as site_name")
                    ->where('stat', '=', 'Y')
                    ->get();

        $cluster = DB::connection('sqlSrvBCKI')
                    ->table('site_cluster')
                    ->selectRaw("id_cluster, UPPER(TRIM(cluster_name)) as cluster_name")
                    ->where('stat', '=', 'Y')
                    ->get();

        $house = DB::connection('sqlSrvBCKI')
                    ->table('house_unit')
                    ->selectRaw("id_house, UPPER(TRIM(house_name)) as house_name")
                    ->where('active_flag', '=', 'Y')
                    ->get();

        $bankAccount = DB::connection('sqlSrvBCKI')
                    ->table('account as a')
                    ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
                    ->selectRaw("a.bank_acc, trim(b.ac_name) as ac_name")
                    ->get();

        $city = DB::connection('sqlSrvBCKI')
                    ->table('city_list')
                    ->selectRaw("city_code, UPPER(TRIM(city)) as city")
                    ->get();

        $province = DB::connection('sqlSrvBCKI')
                    ->table('province_list')
                    ->selectRaw("province_code, UPPER(TRIM(province)) as province")
                    ->get();

        return response()->json([
            'site' => $site, 
            'cluster' => $cluster, 
            'house' => $house, 
            'bankAccount' => $bankAccount,
            'city' => $city,
            'province' => $province
        ], 200);

    }

    public function saveData(Request $request) 
    {

        $site = $request->site;
        $cluster = $request->cluster;
        $house = $request->house;
        $spr = $request->spr;
        $trxType = $request->trxType;
        $amount = $request->amount;
        $payment = $request->payment;
        $bankAcc = $request->bankAcc;
        $custId = $request->custId;
        $custName = $request->custName;
        $custAddress = $request->custAddress;
        $custCity = $request->custCity;
        $custProvince = $request->custProvince;
        $custPhone = $request->custPhone;
        $custIdCard = $request->custIdCard;
        $custRemark = $request->custRemark;
        $date = $request->date;
        $installment = $request->installment;

        $lastID = DB::connection('sqlSrvBCKI')
                ->table('kwitansi')
                ->max('tr_id');

        $lastID = (int)$lastID + 1;
        $formattedID = str_pad($lastID, 6, '0', STR_PAD_LEFT);

        try {
            
            $save = DB::connection('sqlSrvBCKI')
                ->table('kwitansi')
                ->insert([
                    'tr_id' => $formattedID,
                    'id_site' => $site ? $site : '',
                    'id_cluster' => $cluster ? $cluster : '',
                    'id_house' => $house ? $house : '',
                    'doc_id' => $spr ? $spr : '',
                    'type_code' => $trxType ? $trxType : '',
                    'tr_type' => $payment ? $payment : '',
                    'tr_amt' => $amount ? $amount : 0,
                    'bank_acc' => $bankAcc ? $bankAcc : '',
                    'customerid' => $custId ? $custId : '',
                    'customer_name' => $custName,
                    'remark' => $custRemark ? $custRemark : '',
                    'tr_date' => $date ? $date : now(),
                    'userid' => getAuth($request)->username,
                    'customer_id_card_no' => $custIdCard ? $custIdCard : '',
                    'customer_phone' => $custPhone ? $custPhone : '',
                    'customer_address' => $custAddress ? $custAddress : '',
                    'customer_city' => $custCity ? $custCity : '',
                    'customer_province' => $custProvince ? $custProvince : '',
                    'installment_seq' => $installment ? $installment : '',
                ]);

            return response()->json(['success' => true, 'message' => 'Data saved successfully!'], 200);

        } catch (\Throwable $th) {
            return $th;
        }

    }

    public function siteOnChange(Request $request)
    {

        $site = $request->site;
        $sqlWhere = "1=1 ";

        if ($site) {
            $sqlWhere .= "and id_site = '$site' ";
        }

        $cluster = DB::connection('sqlSrvBCKI')
                    ->table('site_cluster')
                    ->selectRaw("id_cluster, UPPER(TRIM(cluster_name)) as cluster_name")
                    ->where('stat', '=', 'Y')
                    ->whereRaw($sqlWhere)
                    ->get();


        return response()->json(['cluster' => $cluster], 200);

    }

    public function clusterOnChange(Request $request)
    {

        $cluster = $request->cluster;
        $sqlWhere = "1=1 ";


        if ($cluster) {
            $sqlWhere .= "and a.id_cluster = '$cluster' ";
        }

        $house = DB::connection('sqlSrvBCKI')
                    ->table('house_unit as a')
                    ->join('site_cluster as b', 'a.id_cluster', '=', 'b.id_cluster')
                    ->selectRaw("a.id_house, concat(UPPER(TRIM(a.house_name)), ' (', UPPER(TRIM(b.cluster_name)), ')') as house_name")
                    ->where('a.active_flag', '=', 'Y')
                    ->whereRaw($sqlWhere)
                    ->get();


        return response()->json(['house' => $house], 200);

    }

    public function populateSPR(Request $request) 
    {

        $sprId = $request->sprId;

        if($sprId)
        {

            $spr = DB::connection('sqlSrvBCKI')
                    ->table('v_list_penjualan')
                    ->selectRaw("order_id, concat(UPPER(TRIM(NamaCustomer)), ' (', CustomerId, ')') as NamaCustomer")
                    ->whereRaw("order_id like '%$sprId%'")
                    ->get();

            return response()->json(['spr' => $spr], 200);

        }
        
    }

    public function searchData(Request $request) 
    {

        $site = $request->site;
        $cluster = $request->cluster;
        $house = $request->house;
        $sprId = $request->spr;
        $trxType = $request->trxType;
        $startDate = $request->startDate;
        $endDate = $request->endDate;
        $isPosted = $request->isPosted;
        $sqlWhere = "1=1 ";

        if ($site) {
            $sqlWhere .= "and a.id_site = '$site' ";
        }

        if ($cluster) {
            $sqlWhere .= "and a.id_cluster = '$cluster' ";
        }

        if ($house) {
            $sqlWhere .= "and a.id_house = '$house' ";
        }

        if ($sprId) {
            $sqlWhere .= "and a.doc_id = '$sprId' ";
        }

        if ($trxType) {
            $sqlWhere .= "and a.type_code = '$trxType' ";
        }

        if ($startDate) {
            if($endDate){
                $sqlWhere .= "and a.tr_date between '$startDate' and '$endDate' ";
            }else{
                $sqlWhere .= "and a.tr_date = '$startDate' ";
            }
        }

        if ($isPosted != null) {
            if ($isPosted == 'C') {
                $sqlWhere .= "and a.tr_id_ref <> '' ";
            } elseif ($isPosted == 'N') {
                $sqlWhere .= "and a.tr_id_ref = '' ";
            }
        }

        $result = DB::connection('sqlSrvBCKI')
                    ->table('kwitansi as a')
                    ->selectRaw("
                        a.id_site, 
                        a.id_cluster, 
                        a.id_house, 
                        format(a.tr_date, 'dd/MM/yyyy') as tr_date, 
                        a.tr_id,
                        upper(b.site_name) as site_name, 
                        upper(c.cluster_name) as cluster_name, 
                        upper(d.house_name) as house_name, 
                        a.tr_amt, 
                        a.type_code, 
                        a.doc_id, 
                        a.customerid, 
                        upper(a.customer_name) as customer_name, 
                        e.bank_name,
                        f.ac_name, 
                        a.bank_acc, 
                        a.remark, 
                        a.tr_id_ref, 
                        a.tr_type,
                        a.customer_id_card_no,
                        a.customer_phone,
                        a.customer_address,
                        a.userid,
                        case
                            when a.type_code = 'UM' then 'Uang Muka(UM)'
                            when a.type_code = 'UTJ' then 'Uang tanda Jadi(UTJ)'
                            when a.type_code = 'CICILAN' then 'Cicilan'
                            else a.type_code
                        END as typeCodeStr,
                        a.installment_seq,
                        a.tr_date as tr_date_ori,
                        a.tr_id_ref,
                        format(g.tr_date, 'dd/MM/yyyy') as posted_date")
                    ->join('site as b', 'a.id_site', 'b.id_site')
                    ->join('site_cluster as c', 'a.id_cluster', 'c.id_cluster')
                    ->join('house_unit as d', 'a.id_house', 'd.id_house')
                    ->leftJoin('account as e', 'a.bank_acc', 'e.bank_acc')
                    ->leftJoin('g_ledger as f', 'e.ac_num', 'f.ac_num')
                    ->leftJoin('payment_hdr as g', 'a.tr_id_ref', 'g.pay_id')
                    ->orderBy('a.tr_date', 'desc')
                    ->whereRaw($sqlWhere)
                    ->get();

        return response()->json(['result' => $result], 200);
        
    }

    public function getCustomer(Request $request) 
    {

        $customerId = $request->customerId;

        if($customerId)
        {

            $customer = DB::connection('sqlSrvBCKI')
                        ->select(DB::raw("select CustomerId, concat(UPPER(TRIM(NamaCustomer)), ' (', CustomerId, ')') as NamaCustomer, NamaCustomer as NamaCustomerStr, Alamat_id_card, Telepon from Customer 
                        where CustomerId like '%$customerId%' or NamaCustomer like '%$customerId%'"));

            return response()->json(['customer' => $customer], 200);

        }
        
    }

    public function getCustomerById(Request $request) 
    {

        $CustomerId = $request->CustomerId;

        if($CustomerId)
        {

            $customer = DB::connection('sqlSrvBCKI')
                        ->select(DB::raw("select * from Customer 
                        where CustomerId = '$CustomerId'"));

            return response()->json(['customer' => $customer], 200);

        }
        
    }

    public function deleteData(Request $request) 
    {

        $trId = $request->id;

        if($trId)
        {

            $delete = DB::connection('sqlSrvBCKI')
                    ->table('kwitansi')
                    ->where('tr_id', '=', $trId)
                    ->delete();

            return response()->json(['success' => true, 'message' => 'Data deleted successfully!'], 200);

        }
        return response()->json(['success' => false, 'message' => 'Data not found!'], 404);
        
    }

    public function editData(Request $request) 
    {

        $trId = $request->id;

        if($trId)
        {
            $site = $request->site;
            $cluster = $request->cluster;
            $house = $request->house;
            $spr = $request->spr;
            $trxType = $request->trxType;
            $amount = $request->amount;
            $payment = $request->payment;
            $bankAcc = $request->bankAcc;
            $remark = $request->remark;
            $bank = [];
            $date =  $request->date;

            $editData = DB::connection('sqlSrvBCKI')
                    ->table('kwitansi')
                    ->where('tr_id', '=', $trId)
                    ->update([
                        'tr_date' => $date ? $date : '',
                        'id_site' => $site ? $site : '',
                        'id_cluster' => $cluster ? $cluster : '',
                        'id_house' => $house ? $house : '',
                        'doc_id' => $spr ? $spr : '',
                        'type_code' => $trxType ? $trxType : '',
                        'tr_type' => $payment ? $payment : '',
                        'tr_amt' => $amount ? $amount : 0,
                        'bank_acc' => $bankAcc ? $bankAcc : '',
                        'remark' => $remark ? $remark : '',
                        'dt_modified' => now(),
                        'userid' => getAuth($request)->username,
                        'installment_seq' => $request->installment ? $request->installment : '',
                    ]);

            return response()->json(['success' => true, 'message' => 'Data edited successfully!'], 200);

        }
        return response()->json(['success' => false, 'message' => 'Data not found!'], 404);
       
        
    }

    public function getSPR(Request $request) 
    {

        $id = $request->id;

        if($id)
        {

            $spr = DB::connection('sqlSrvBCKI')
                        ->select(DB::raw("select top 5 order_id, concat(order_id, '(', trim(NamaCustomer), ')') as NamaCustomer from v_list_penjualan 
                        where order_id like '%$id%' or NamaCustomer like '%$id%'"));

            return response()->json(['spr' => $spr], 200);

        }
        
    }

}
