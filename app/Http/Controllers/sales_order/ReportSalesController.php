<?php

namespace App\Http\Controllers\sales_order;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ReportSalesController extends Controller
{
    public function getNoOrder(Request $request)
    {
        $dateYear = substr( date("Y"), -2);
        $dateMonth = date("m");
        $dateDay = date("d");
        $gen_tr_id = '';

        $getLastTrID =  DB::connection('sqlSrvFPI')
                        ->table('CustomerOrder')
                        ->where('CustomerOrderNo', 'like', "%".$request->office_id.'INV'.$dateYear.$dateMonth.$dateDay."%")
                        ->max('CustomerOrderNo');

        if($getLastTrID){
            $seq = substr( trim($getLastTrID), -3) + 1;
            $gen_tr_id = $request->office_id.'INV'.$dateYear.$dateMonth.$dateDay.str_pad($seq, 3, '0', STR_PAD_LEFT);
        } else {
            $gen_tr_id = $request->office_id.'INV'.$dateYear.$dateMonth.$dateDay.'001';
        }


        return $this->sendResponse($gen_tr_id, 'Successfully get number of cash register');
    }

    public function getPayTerm(Request $request)
    {
        $where = "cash_register_only='Y'";
        $office_id = $request->office_id;

        if (!empty($office_id))
        {
            $where = $where . "and office_id = ". "'" . $office_id . "'";
            $result = DB::connection('sqlSrvFPI')
                ->table('pay_term')
                ->selectRaw('office_id, 
                TRIM(pay_term_id) as pay_term_id, 
                TRIM(pay_term_desc) as pay_term_desc, 
                num_of_days,
                bank_id,
                rek_id,
                active_flag')
                ->whereRaw($where)
                ->where('active_flag', 'Y')
                ->where('cash_register_only', 'Y')
                ->orderBy('pay_term_id', 'asc')
                ->get();

            return $this->sendResponse($result, 'Successfully get Pay Term');
        }        
    }
/* ====================================================================================== */
/* ===================================| RETUR REPORT |=================================== */
/* ====================================================================================== */

    public function showReturnReportOI(Request $request)
    {
        // $sqlWhere = "inv_trans_hdr.tr_type = 'SR'";
        $sqlWhere = "1=1";
        $tr_type = $request->tr_type;
        $office_id = $request->office_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        if ($office_id) {
            $sqlWhere.= " and inv_trans_hdr.office_id = '$office_id'";
        }
        if ($tr_type) {
            $sqlWhere.= " and inv_trans_hdr.tr_type = '$tr_type'";
        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and inv_trans_hdr.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and inv_trans_hdr.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,GETDATE())";
            }
        }
        // dd($sqlWhere);
        $datas = DB::connection('sqlSrvFPI')
                    ->table('inv_trans_hdr')
                    ->selectRaw("ROW_NUMBER() OVER (ORDER BY inv_trans_hdr.tr_id) AS No, 
                                 inv_trans_hdr.office_id, 
                                 inv_trans_hdr.tr_type, 
                                 inv_trans_hdr.tr_id, 
                                 inv_trans_hdr.tr_id_ref, 
                                 inv_trans_hdr.wh_id, 
                                 inv_trans_hdr.tr_date, 
                                 inv_trans_hdr.remark,
                                 whouse.descr, 
                                 branch_office.office,
                                 (
                                    SELECT SUM(inv_trans_dtl.amt_item)
                                    FROM
                                    inv_trans_dtl WHERE inv_trans_dtl.tr_id = inv_trans_hdr.tr_id
                                ) as total
                                ")
                    ->leftjoin('branch_office',function($join){
                        $join->on('inv_trans_hdr.office_id','=','branch_office.office_id');
                    })
                    ->leftJoin('whouse',function($join){
                        $join->on('inv_trans_hdr.wh_id','=','whouse.wh_id')
                             ->on('branch_office.office_id','=','whouse.office_id');
                    })
                    ->whereRaw($sqlWhere)
                    ->get();


        return $this->sendResponse($datas, 'Successfully get List');

    }

    public function showReturnReportMonthly(Request $request)
    {
        // $sqlWhere = "inv_trans_hdr.tr_type = 'SR'";
        $sqlWhere = "1=1";
        $tr_type = $request->tr_type;
        $office_id = $request->office_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        if ($office_id) {
    
            $sqlWhere.= " and inv_trans_hdr.office_id = '$office_id'";
        }
        if ($tr_type) {
            $sqlWhere.= " and inv_trans_hdr.tr_type = '$tr_type'";
        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and inv_trans_hdr.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and inv_trans_hdr.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,GETDATE())";
            }
        }
        // dd($sqlWhere);
        
        $sqlRaw = " ROW_NUMBER() OVER (ORDER BY MONTH(inv_trans_hdr.tr_date)) AS No, 
                    YEAR(inv_trans_hdr.tr_date) as Thn, 
                    MONTH(inv_trans_hdr.tr_date) as Bln, 
                    CASE 
                        WHEN MONTH(inv_trans_hdr.tr_date) = 1 THEN 'January'
                                        WHEN MONTH(inv_trans_hdr.tr_date) = 2 THEN 'February'
                                        WHEN MONTH(inv_trans_hdr.tr_date) = 3 THEN 'March'
                                        WHEN MONTH(inv_trans_hdr.tr_date) = 4 THEN 'April'
                                        WHEN MONTH(inv_trans_hdr.tr_date) = 5 THEN 'May'
                                        WHEN MONTH(inv_trans_hdr.tr_date) = 6 THEN 'June'
                                        WHEN MONTH(inv_trans_hdr.tr_date) = 7 THEN 'July'
                                        WHEN MONTH(inv_trans_hdr.tr_date) = 8 THEN 'August'
                                        WHEN MONTH(inv_trans_hdr.tr_date) = 9 THEN 'September'
                                        WHEN MONTH(inv_trans_hdr.tr_date) = 10 THEN 'October'
                                        WHEN MONTH(inv_trans_hdr.tr_date) = 11 THEN 'November'
                                        WHEN MONTH(inv_trans_hdr.tr_date) = 12 THEN 'December'
                        ELSE '-'
                    END AS Bulan,
                    SUM(inv_trans_dtl.qty) as qty,
                    SUM(inv_trans_dtl.wgt) as wgt,
                    SUM(inv_trans_dtl.amt_item) as total";
        
        if ($office_id) {
            $sqlRaw.= " ,'$office_id' as office_id";
        }

        $datas = DB::connection('sqlSrvFPI')
                    ->table('inv_trans_hdr')
                    ->selectRaw($sqlRaw)
                    ->leftJoin('inv_trans_dtl',function($join){
                        $join->on('inv_trans_hdr.tr_id','=','inv_trans_dtl.tr_id')
                             ->on('inv_trans_hdr.tr_type','=','inv_trans_dtl.tr_type');
                        })

                    ->whereRaw($sqlWhere)
                    ->groupby(DB::raw('MONTH(inv_trans_hdr.tr_date), YEAR(inv_trans_hdr.tr_date)'))
                    ->get();
        
        return $this->sendResponse($datas, 'Successfully get List');

    }

    public function showReturnReportDaily(Request $request)
    {
        // $sqlWhere = "inv_trans_hdr.tr_type = 'SR'";
        $sqlWhere = "1=1";
        $tr_type = $request->tr_type;
        $office_id = $request->office_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        if ($office_id) {
            $sqlWhere.= " and inv_trans_hdr.office_id = '$office_id'";
        }
        if ($tr_type) {
            $sqlWhere.= " and inv_trans_hdr.tr_type = '$tr_type'";
        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and inv_trans_hdr.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and inv_trans_hdr.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,GETDATE())";
            }
        }
        // dd($sqlWhere);
        $sqlRaw = " ROW_NUMBER() OVER (ORDER BY CAST(inv_trans_hdr.tr_date AS DATE)) AS No,
                    CAST(inv_trans_hdr.tr_date AS DATE) AS tr_date,
                    SUM(inv_trans_dtl.qty) as qty,
                    SUM(inv_trans_dtl.wgt) as wgt,
                    SUM(inv_trans_dtl.amt_item) as total
                    ";
        
        if ($office_id) {
            $sqlRaw.= " ,'$office_id' as office_id";
        }
    
        $datas = DB::connection('sqlSrvFPI')
                ->table('inv_trans_hdr')
                ->selectRaw($sqlRaw)
                ->leftJoin('inv_trans_dtl',function($join){
                    $join->on('inv_trans_hdr.tr_id','=','inv_trans_dtl.tr_id')
                         ->on('inv_trans_hdr.tr_type','=','inv_trans_dtl.tr_type');
                    })
                ->whereRaw($sqlWhere)
                ->groupby(DB::raw('CAST(inv_trans_hdr.tr_date AS DATE)'))
                ->get();
        

        return $this->sendResponse($datas, 'Successfully get List');

    }
    
    public function showReturnDetailReportOI(Request $request)
    {
        // $sqlWhere = "inv_trans_dtl.tr_type = 'SR'";
        $sqlWhere = "1=1";
        $tr_type = $request->tr_type;
        $tr_id = $request->tr_id;

        if ($tr_id) {
            $sqlWhere.= " and inv_trans_dtl.tr_id = '$tr_id'";
        }
        if ($tr_type) {
            $sqlWhere.= " and inv_trans_dtl.tr_type = '$tr_type'";
        }

        // dd($sqlWhere);
        $datas = DB::connection('sqlSrvFPI')
                    ->table('inv_trans_dtl')
                    ->selectRaw("ROW_NUMBER() OVER (ORDER BY inv_trans_dtl.article_id) AS No,
                                inv_trans_dtl.office_id, 
                                inv_trans_dtl.tr_type, 
                                branch_office.office, 
                                inv_trans_dtl.tr_id, 
                                article.description, 
                                whouse.descr AS gudang, 
                                inv_trans_dtl.article_id, 
                                inv_trans_dtl.wh_id, 
                                inv_trans_dtl.qty, 
                                inv_trans_dtl.wgt, 
                                inv_trans_dtl.remark, 
                                inv_trans_dtl.period, 
                                inv_trans_dtl.amt_item, 
                                inv_trans_dtl.unit_price, 
                                inv_trans_dtl.length")
                    ->leftJoin('branch_office',function($join){
                        $join->on('inv_trans_dtl.office_id','=','branch_office.office_id');
                    })
                    ->leftJoin('article',function($join){
                        $join->on('inv_trans_dtl.article_id','=','article.article_id');
                    })
                    ->leftJoin('whouse',function($join){
                        $join->on('inv_trans_dtl.wh_id','=','whouse.wh_id')
                             ->on('branch_office.office_id','=','whouse.office_id');
                    })

                    ->whereRaw($sqlWhere)
                    ->get();


        return $this->sendResponse($datas, 'Successfully get List');

    }

    public function showReturnDetailReportMonthly(Request $request)
    {
        // $sqlWhere = "inv_trans_dtl.tr_type = 'SR'";
        $sqlWhere = "1=1";
        $tr_type = $request->tr_type;
        $office_id = $request->office_id;
        $Tahun = $request->Tahun;
        $Bulan = $request->Bulan;

        if ($office_id) {
    
            $sqlWhere.= " and inv_trans_dtl.office_id = '$office_id'";
        }
        if ($tr_type) {
            $sqlWhere.= " and inv_trans_dtl.tr_type = '$tr_type'";
        }
        if ($Tahun) {
    
            $sqlWhere.= " and YEAR(inv_trans_hdr.tr_date) = '$Tahun'";
        }
        if ($Bulan) {
    
            $sqlWhere.= " and MONTH(inv_trans_hdr.tr_date) = '$Bulan'";
        }
        // dd($sqlWhere);
        $datas = DB::connection('sqlSrvFPI')
                    ->table('inv_trans_dtl')
                    ->selectRaw("ROW_NUMBER() OVER (ORDER BY inv_trans_dtl.tr_id) AS No, 
                                inv_trans_hdr.tr_date, 
                                inv_trans_dtl.office_id, 
                                inv_trans_dtl.tr_type, 
                                inv_trans_dtl.tr_id, 
                                inv_trans_dtl.article_id, 
                                inv_trans_dtl.qty, 
                                inv_trans_dtl.wgt, 
                                inv_trans_dtl.remark, 
                                inv_trans_dtl.unit_price, 
                                inv_trans_dtl.amt_item, 
                                inv_trans_dtl.length, 
                                article.description, 
                                whouse.descr, 
                                branch_office.office")
                    ->leftJoin('branch_office',function($join){
                        $join->on('inv_trans_dtl.office_id','=','branch_office.office_id');
                    })
                    ->leftJoin('inv_trans_hdr',function($join){
                        $join->on('inv_trans_dtl.tr_type','=','inv_trans_hdr.tr_type')
                             ->on('inv_trans_dtl.tr_id','=','inv_trans_hdr.tr_id');
                    })
                    ->leftJoin('whouse',function($join){
                        $join->on('inv_trans_dtl.wh_id','=','whouse.wh_id')
                             ->on('branch_office.office_id','=','whouse.office_id');
                    })
                    ->leftJoin('article',function($join){
                        $join->on('inv_trans_dtl.article_id','=','article.article_id');
                    })
                    ->whereRaw($sqlWhere)
                    ->get();


        return $this->sendResponse($datas, 'Successfully get List');

    }

    public function showReturnDetailReportDaily(Request $request)
    {
        // $sqlWhere = "inv_trans_dtl.tr_type = 'SR'";
        $sqlWhere = "1=1";
        $tr_type = $request->tr_type;
        $office_id = $request->office_id;
        $tr_date = $request->tr_date;
        $formattedDate = date('Y-m-d', strtotime($tr_date));

        if ($office_id) {
            $sqlWhere.= " and inv_trans_dtl.office_id = '$office_id'";
        }
        if ($tr_type) {
            $sqlWhere.= " and inv_trans_dtl.tr_type = '$tr_type'";
        }
        if ($tr_date) {
            $sqlWhere.= " and CAST(inv_trans_hdr.tr_date AS DATE) = '$formattedDate'";
        }
        // dd($sqlWhere);
        $datas = DB::connection('sqlSrvFPI')
                    ->table('inv_trans_dtl')
                    ->selectRaw("ROW_NUMBER() OVER (ORDER BY inv_trans_dtl.tr_id) AS No, 
                                inv_trans_hdr.tr_date, 
                                inv_trans_dtl.office_id, 
                                inv_trans_dtl.tr_type, 
                                inv_trans_dtl.tr_id, 
                                inv_trans_dtl.article_id, 
                                inv_trans_dtl.qty, 
                                inv_trans_dtl.wgt, 
                                inv_trans_dtl.remark, 
                                inv_trans_dtl.unit_price, 
                                inv_trans_dtl.amt_item, 
                                inv_trans_dtl.length, 
                                article.description, 
                                whouse.descr, 
                                branch_office.office")
                    ->leftJoin('branch_office',function($join){
                        $join->on('inv_trans_dtl.office_id','=','branch_office.office_id');
                    })
                    ->leftJoin('inv_trans_hdr',function($join){
                        $join->on('inv_trans_dtl.tr_type','=','inv_trans_hdr.tr_type')
                                ->on('inv_trans_dtl.tr_id','=','inv_trans_hdr.tr_id');
                    })
                    ->leftJoin('whouse',function($join){
                        $join->on('inv_trans_dtl.wh_id','=','whouse.wh_id')
                                ->on('branch_office.office_id','=','whouse.office_id');
                    })
                    ->leftJoin('article',function($join){
                        $join->on('inv_trans_dtl.article_id','=','article.article_id');
                    })
                    ->whereRaw($sqlWhere)
                    ->get();


        return $this->sendResponse($datas, 'Successfully get List');

    }

    public function exportCSVReturnReport(Request $request)
    {
        // $sqlWhere = "inv_trans_hdr.tr_type = 'SR'";
        $sqlWhere = "1=1";
        $tr_type = $request->tr_type;
        $office_id = $request->office_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        if ($office_id) {
            $sqlWhere.= " and inv_trans_hdr.office_id = '$office_id'";
        }
        if ($tr_type) {
            $sqlWhere.= " and inv_trans_hdr.tr_type = '$tr_type'";
        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and inv_trans_hdr.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and inv_trans_hdr.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,GETDATE())";
            }
        }
        // dd($sqlWhere);
        $datas = DB::connection('sqlSrvFPI')
                    ->table('inv_trans_dtl')
                    ->selectRaw("	ROW_NUMBER() OVER (ORDER BY inv_trans_dtl.tr_id) AS No,
                                    FORMAT(inv_trans_hdr.tr_date, 'dd-MM-yyyy') as Tanggal,
                                    branch_office.office as Office,
                                    whouse.descr as Warehouse,
                                    inv_trans_dtl.tr_id as 'Trx ID', 
                                    article.description as 'Item Name', 
                                    inv_trans_dtl.remark as Remark,
                                    inv_trans_dtl.wgt as Wgt, 
                                    inv_trans_dtl.length as Length,
                                    inv_trans_dtl.qty as Qty, 
                                    inv_trans_dtl.unit_price as 'Unit Price', 
                                    inv_trans_dtl.amt_item 'Amount'		
                                ")
                    ->leftjoin('branch_office',function($join){
                        $join->on('inv_trans_dtl.office_id','=','branch_office.office_id');
                    })
                    ->leftJoin('inv_trans_hdr',function($join){
                        $join->on('inv_trans_dtl.tr_type','=','inv_trans_hdr.tr_type')
                             ->on('inv_trans_dtl.tr_id','=','inv_trans_hdr.tr_id');
                    })
                    ->leftJoin('whouse',function($join){
                        $join->on('inv_trans_dtl.wh_id','=','whouse.wh_id')
                             ->on('branch_office.office_id','=','whouse.office_id');
                    })
                    ->leftJoin('article',function($join){
                        $join->on('inv_trans_dtl.article_id','=','article.article_id');
                    })
                    ->whereRaw($sqlWhere)
                    ->get();
        return $this->sendResponse($datas, 'Successfully get List');

    }

    public function printReturDiv(Request $request)
    {
      
        $tr_id = $request->tr_id;
        $now = now();

        $trans_hdr = DB::connection('sqlSrvFPI')
                        ->table('inv_trans_hdr')
                        ->where('tr_type', 'SR')
                        ->where('tr_id', $tr_id)
                        ->first();

        $trans_amt = DB::connection('sqlSrvFPI')
                        ->table('inv_trans_dtl')
                        ->selectRaw("sum(amt_item) as amt_item")
                        ->where('tr_id', $tr_id)
                        ->first();

        $checkCreditNote = DB::connection('sqlSrvFPI')
                        ->table('credit_note')
                        ->where('ref_no', $trans_hdr->tr_id)
                        ->first();

        if(!$checkCreditNote){

            $insertCreditNote = DB::connection("sqlSrvFPI")
                            ->table('credit_note')
                            ->insert([
                                'tr_type' => 'D',
                                'ref_no' => $trans_hdr->tr_id,
                                'customerorderno' => $trans_hdr->tr_id_ref,
                                'period' => $now->format('Y') . $now->format('m'),
                                'tr_date' => $now,
                                'inv_id' => $trans_hdr->tr_id_ref,
                                'remark' => 'DROPSHIPPER RETUR KTI NO ORDER ' . $trans_hdr->tr_id_ref,
                                'tr_amt' => $trans_amt->amt_item,
                                'payment' => '',
                                'user_id' => getAuth($request)->user_id2
                            ]);  

        }

    }

/* ====================================================================================== */
/* ================================| SALES ORDER REPORT |================================ */
/* ====================================================================================== */
    public function showReportOI(Request $request)
    {
        $sqlWhere = "1=1";
        $payterm = $request->payterm;
        $ord_type = $request->ord_type;
        $office_id = $request->office_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $customer_id = $request->customer_id;
        $status = $request->status;
        $shift = $request->shift;

        if ($office_id) {
            $sqlWhere.= " and CustomerOrder.office_id = '$office_id'";
        }

        if ($payterm) {
            $sqlWhere.= " and CustomerOrder.pay_term = '$payterm'";
        }

        if ($ord_type=='Retail') {
            $sqlWhere.= " and CustomerOrder.ord_type = 'R'";
        }else if($ord_type=='Dropship') {
            $sqlWhere.= " and CustomerOrder.ord_type = 'D'";
        }else{

        }

        if ($customer_id) {
            $sqlWhere.= " and CustomerOrder.CustomerId = '$customer_id'";
        }

        if ($shift) {
            $sqlWhere.= " and CustomerOrder.shift = '$shift'";
        }

        if ($status=='Open') {
            $sqlWhere.= " and CustomerOrder.stat = 'O'";
        }else if($status=='Closed') {
            $sqlWhere.= " and CustomerOrder.stat = 'C'";
        }else if($status=='Rejected') {
            $sqlWhere.= " and CustomerOrder.stat = 'X'";
        }else{

        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and CustomerOrder.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and CustomerOrder.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime, GETDATE() )";
            }
        }
        // dd($sqlWhere);
        $datas = DB::connection('sqlSrvFPI')
                    ->table('CustomerOrder')
                    ->selectRaw("ROW_NUMBER() OVER (ORDER BY CustomerOrder.CustomerOrderNo) AS No, 
                                CustomerOrder.*, 
                                (CustomerOrder.ord_amt - CustomerOrder.discount) as total,
                                Customer.NamaCustomer, 
                                branch_office.office, 
                                pay_term.pay_term_desc
                                ")
                    ->join('Customer',function($join){
                        $join->on('CustomerOrder.CustomerId','=','Customer.CustomerId');
                    })
                    ->join('branch_office',function($join){
                        $join->on('CustomerOrder.office_id','=','branch_office.office_id');
                    })
                    ->leftjoin('pay_term',function($join){
                        $join->on('CustomerOrder.pay_term','=','pay_term.pay_term_id');
                    })
                    // ->leftjoin('pay_term',function($join){
                    //     $join->on('CustomerOrder.pay_term','=','pay_term.pay_term_id')
                    //          ->on('CustomerOrder.office_id','=','pay_term.office_id');
                    // })
                    ->whereRaw($sqlWhere)
                    ->orderBy('CustomerOrder.TglPesan')
                    ->get();


        return $this->sendResponse($datas, 'Successfully get List');

    }

    public function showReportMonthly(Request $request)
    {
        $status = $request->status;
        if($status || $status != '' || $status != null){
            $sqlWhere = "1=1";
            $office_id = $request->office_id;
            $payterm = $request->payterm;
            $ord_type = $request->ord_type;
            $start_date = date('Y-m-d', strtotime($request->start_date));

            if($request->end_date){
                $end_date   = date('Y-m-d', strtotime($request->end_date));    
            }else{
                $end_date   = date('Y-m-d', strtotime(now()));
            }
            
            $status = $request->status;
            $customer_id = $request->customer_id;

            $sqlRaw = " ROW_NUMBER() OVER (ORDER BY MONTH(co.TglPesan)) AS No, YEAR(co.TglPesan) as Thn, MONTH(co.TglPesan) as Bln, 
                            CASE 
                                WHEN MONTH(co.TglPesan) = 1 THEN 'January'
                                                WHEN MONTH(co.TglPesan) = 2 THEN 'February'
                                                WHEN MONTH(co.TglPesan) = 3 THEN 'March'
                                                WHEN MONTH(co.TglPesan) = 4 THEN 'April'
                                                WHEN MONTH(co.TglPesan) = 5 THEN 'May'
                                                WHEN MONTH(co.TglPesan) = 6 THEN 'June'
                                                WHEN MONTH(co.TglPesan) = 7 THEN 'July'
                                                WHEN MONTH(co.TglPesan) = 8 THEN 'August'
                                                WHEN MONTH(co.TglPesan) = 9 THEN 'September'
                                                WHEN MONTH(co.TglPesan) = 10 THEN 'October'
                                                WHEN MONTH(co.TglPesan) = 11 THEN 'November'
                                                WHEN MONTH(co.TglPesan) = 12 THEN 'December'
                                ELSE '-'
                            END AS Bulan,
                        SUM(cod.Total) as ord_amt,
                        SUM(co.discount) as discount,
                        SUM(cod.Total)-SUM(co.discount) as total
                    ";
            
            if ($office_id) {
                $sqlWhere.= " and co.office_id = '$office_id'";
                $sqlRaw.= " ,'$office_id' as office_id";
            }
            
            if ($payterm) {
                $sqlWhere.= " and co.pay_term = '$payterm'";
                $sqlRaw.= " ,'$payterm' as payterm";
            }

            if ($ord_type=='Retail') {
                $sqlWhere.= " and co.ord_type = 'R'";
                $sqlRaw.= " ,'$ord_type' as order_type";
            }else if($ord_type=='Dropship') {
                $sqlWhere.= " and co.ord_type = 'D'";
                $sqlRaw.= " ,'$ord_type' as order_type";
            }else{
            }

            if ($start_date)
            {
                if ($end_date)
                {
                    $sqlWhere .= " and co.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
                }
                else
                {
                    $sqlWhere .= " and co.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,GETDATE())";
                }
            }

            if ($status=='Open') {
                $sqlWhere.= " and cod.stat = 'O'";
                $sqlRaw.= " ,'$status' as status";
            }else if($status=='Closed') {
                $sqlWhere.= " and cod.stat = 'C'";
                $sqlRaw.= " ,'$status' as status";
            }else if($status=='Rejected') {
                $sqlWhere.= " and cod.stat = 'X'";
                $sqlRaw.= " ,'$status' as status";
            }else{
            }

            if ($customer_id) {
                $sqlWhere.= " and co.CustomerId = '$customer_id'";
                $sqlRaw.= " ,'$customer_id' as customer_id";
            }

            $datas = DB::connection('sqlSrvFPI')
                    ->table('CustomerOrder AS co')
                    ->selectRaw($sqlRaw) 
                    ->leftjoin('CustomerOrderDetil as cod',function($join){
                        $join->on('co.CustomerOrderNo','=','cod.CustomerOrderNo');
                    })
                    ->whereRaw($sqlWhere)
                    ->groupby(DB::raw('MONTH(co.TglPesan), YEAR(co.TglPesan)'))
                    ->get();
            
            return $this->sendResponse($datas, 'Successfully get List');
        }else{
            $sqlWhere = "cod.stat <> 'X'";
            $office_id = $request->office_id;
            $payterm = $request->payterm;
            $ord_type = $request->ord_type;
            $start_date = date('Y-m-d', strtotime($request->start_date));

            if($request->end_date){
                $end_date   = date('Y-m-d', strtotime($request->end_date));    
            }else{
                $end_date   = date('Y-m-d', strtotime(now()));
            }
            
            $status = $request->status;
            $customer_id = $request->customer_id;

            $sqlRaw = " ROW_NUMBER() OVER (ORDER BY MONTH(co.TglPesan)) AS No, YEAR(co.TglPesan) as Thn, MONTH(co.TglPesan) as Bln, 
                            CASE 
                                WHEN MONTH(co.TglPesan) = 1 THEN 'January'
                                                WHEN MONTH(co.TglPesan) = 2 THEN 'February'
                                                WHEN MONTH(co.TglPesan) = 3 THEN 'March'
                                                WHEN MONTH(co.TglPesan) = 4 THEN 'April'
                                                WHEN MONTH(co.TglPesan) = 5 THEN 'May'
                                                WHEN MONTH(co.TglPesan) = 6 THEN 'June'
                                                WHEN MONTH(co.TglPesan) = 7 THEN 'July'
                                                WHEN MONTH(co.TglPesan) = 8 THEN 'August'
                                                WHEN MONTH(co.TglPesan) = 9 THEN 'September'
                                                WHEN MONTH(co.TglPesan) = 10 THEN 'October'
                                                WHEN MONTH(co.TglPesan) = 11 THEN 'November'
                                                WHEN MONTH(co.TglPesan) = 12 THEN 'December'
                                ELSE '-'
                            END AS Bulan,
                        SUM(cod.Total) as ord_amt,
                        SUM(co.discount) as discount,
                        SUM(cod.Total)-SUM(co.discount) as total
                    ";
            
            if ($office_id) {
                $sqlWhere.= " and co.office_id = '$office_id'";
                $sqlRaw.= " ,'$office_id' as office_id";
            }
            
            if ($payterm) {
                $sqlWhere.= " and co.pay_term = '$payterm'";
                $sqlRaw.= " ,'$payterm' as payterm";
            }

            if ($ord_type=='Retail') {
                $sqlWhere.= " and co.ord_type = 'R'";
                $sqlRaw.= " ,'$ord_type' as order_type";
            }else if($ord_type=='Dropship') {
                $sqlWhere.= " and co.ord_type = 'D'";
                $sqlRaw.= " ,'$ord_type' as order_type";
            }else{
            }

            if ($start_date)
            {
                if ($end_date)
                {
                    $sqlWhere .= " and co.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
                }
                else
                {
                    $sqlWhere .= " and co.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,GETDATE())";
                }
            }

            if ($customer_id) {
                $sqlWhere.= " and co.CustomerId = '$customer_id'";
                $sqlRaw.= " ,'$customer_id' as customer_id";
            }

            $datas = DB::connection('sqlSrvFPI')
                    ->table('CustomerOrder AS co')
                    ->selectRaw($sqlRaw) 
                    ->leftjoin('CustomerOrderDetil as cod',function($join){
                        $join->on('co.CustomerOrderNo','=','cod.CustomerOrderNo');
                    })
                    ->whereRaw($sqlWhere)
                    ->groupby(DB::raw('MONTH(co.TglPesan), YEAR(co.TglPesan)'))
                    ->get();
            
            return $this->sendResponse($datas, 'Successfully get List');
        }

        /*
        $sqlWhere = "1=1";
        $office_id = $request->office_id;
        $payterm = $request->payterm;
        $ord_type = $request->ord_type;
        $start_date = date('Y-m-d', strtotime($request->start_date));

        if($request->end_date){
            $end_date   = date('Y-m-d', strtotime($request->end_date));    
        }else{
            $end_date   = date('Y-m-d', strtotime(now()));
        }
        
        $status = $request->status;
        $customer_id = $request->customer_id;

        $sqlRaw = " ROW_NUMBER() OVER (ORDER BY MONTH(co.TglPesan)) AS No, YEAR(co.TglPesan) as Thn, MONTH(co.TglPesan) as Bln, 
                        CASE 
                            WHEN MONTH(co.TglPesan) = 1 THEN 'January'
                                            WHEN MONTH(co.TglPesan) = 2 THEN 'February'
                                            WHEN MONTH(co.TglPesan) = 3 THEN 'March'
                                            WHEN MONTH(co.TglPesan) = 4 THEN 'April'
                                            WHEN MONTH(co.TglPesan) = 5 THEN 'May'
                                            WHEN MONTH(co.TglPesan) = 6 THEN 'June'
                                            WHEN MONTH(co.TglPesan) = 7 THEN 'July'
                                            WHEN MONTH(co.TglPesan) = 8 THEN 'August'
                                            WHEN MONTH(co.TglPesan) = 9 THEN 'September'
                                            WHEN MONTH(co.TglPesan) = 10 THEN 'October'
                                            WHEN MONTH(co.TglPesan) = 11 THEN 'November'
                                            WHEN MONTH(co.TglPesan) = 12 THEN 'December'
                            ELSE '-'
                        END AS Bulan,
                    SUM(cod.Total) as ord_amt,
                    SUM(co.discount) as discount,
                    SUM(cod.Total)-SUM(co.discount) as total
                ";
        
        if ($office_id) {
            $sqlWhere.= " and co.office_id = '$office_id'";
            $sqlRaw.= " ,'$office_id' as office_id";
        }
        
        if ($payterm) {
            $sqlWhere.= " and co.pay_term = '$payterm'";
            $sqlRaw.= " ,'$payterm' as payterm";
        }

        if ($ord_type=='Retail') {
            $sqlWhere.= " and co.ord_type = 'R'";
            $sqlRaw.= " ,'$ord_type' as order_type";
        }else if($ord_type=='Dropship') {
            $sqlWhere.= " and co.ord_type = 'D'";
            $sqlRaw.= " ,'$ord_type' as order_type";
        }else{
        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and co.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and co.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,GETDATE())";
            }
        }

        if ($status=='Open') {
            $sqlWhere.= " and cod.stat = 'O'";
            $sqlRaw.= " ,'$status' as status";
        }else if($status=='Closed') {
            $sqlWhere.= " and cod.stat = 'C'";
            $sqlRaw.= " ,'$status' as status";
        }else if($status=='Rejected') {
            $sqlWhere.= " and cod.stat = 'X'";
            $sqlRaw.= " ,'$status' as status";
        }else{
        }

        if ($customer_id) {
            $sqlWhere.= " and co.CustomerId = '$customer_id'";
            $sqlRaw.= " ,'$customer_id' as customer_id";
        }

        $datas = DB::connection('sqlSrvFPI')
                ->table('CustomerOrder AS co')
                ->selectRaw($sqlRaw) 
                ->leftjoin('CustomerOrderDetil as cod',function($join){
                    $join->on('co.CustomerOrderNo','=','cod.CustomerOrderNo');
                })
                ->whereRaw($sqlWhere)
                ->groupby(DB::raw('MONTH(co.TglPesan), YEAR(co.TglPesan)'))
                ->get();
        
        return $this->sendResponse($datas, 'Successfully get List');
        */
    }

    public function showReportDaily(Request $request)
    {
        $status = $request->status;
        if($status || $status != '' || $status != null){
            $sqlWhere = "1=1";
            $office_id = $request->office_id;
            $payterm = $request->payterm;
            $ord_type = $request->ord_type;
            $start_date = date('Y-m-d', strtotime($request->start_date));

            if($request->end_date){
                $end_date   = date('Y-m-d', strtotime($request->end_date));    
            }else{
                $end_date   = date('Y-m-d', strtotime(now()));
            }

            $status = $request->status;
            $customer_id = $request->customer_id;

            $sqlRaw = " ROW_NUMBER() OVER (ORDER BY CONVERT(date, co.TglPesan)) AS No, 
                        CONVERT(date, co.TglPesan) as TglPesan,
                        SUM(cod.Total) as ord_amt,
                        SUM(co.discount) as discount,
                        SUM(cod.Total) - SUM(co.discount) as total
                    ";

            if ($office_id) {
                $sqlWhere.= " and co.office_id = '$office_id'";
                $sqlRaw.= " ,'$office_id' as office_id";
            }
            
            if ($payterm) {
                $sqlWhere.= " and co.pay_term = '$payterm'";
                $sqlRaw.= " ,'$payterm' as payterm";
            }

            if ($ord_type=='Retail') {
                $sqlWhere.= " and co.ord_type = 'R'";
                $sqlRaw.= " ,'$ord_type' as order_type";
            }else if($ord_type=='Dropship') {
                $sqlWhere.= " and co.ord_type = 'D'";
                $sqlRaw.= " ,'$ord_type' as order_type";
            }else{
            }

            if ($start_date)
            {
                if ($end_date)
                {
                    $sqlWhere .= " and co.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
                }
                else
                {
                    $sqlWhere .= " and co.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,GETDATE())";
                }
            }

            if ($status=='Open') {
                $sqlWhere.= " and cod.stat = 'O'";
                $sqlRaw.= " ,'$status' as status";
            }else if($status=='Closed') {
                $sqlWhere.= " and cod.stat = 'C'";
                $sqlRaw.= " ,'$status' as status";
            }else if($status=='Rejected') {
                $sqlWhere.= " and cod.stat = 'X'";
                $sqlRaw.= " ,'$status' as status";
            }else{
            }

            if ($customer_id) {
                $sqlWhere.= " and co.CustomerId = '$customer_id'";
                $sqlRaw.= " ,'$customer_id' as customer_id";
            }
            
            $datas = DB::connection('sqlSrvFPI')
                        ->table('CustomerOrder AS co')
                        ->selectRaw($sqlRaw)
                        ->leftjoin('CustomerOrderDetil as cod',function($join){
                            $join->on('co.CustomerOrderNo','=','cod.CustomerOrderNo');
                        })
                        ->whereRaw($sqlWhere)
                        ->groupby(DB::raw('CONVERT(date, co.TglPesan)'))
                        ->get();

            return $this->sendResponse($datas, 'Successfully get List');
        }else{
            $sqlWhere = "cod.stat <> 'X'";
            $office_id = $request->office_id;
            $payterm = $request->payterm;
            $ord_type = $request->ord_type;
            $start_date = date('Y-m-d', strtotime($request->start_date));

            if($request->end_date){
                $end_date   = date('Y-m-d', strtotime($request->end_date));    
            }else{
                $end_date   = date('Y-m-d', strtotime(now()));
            }

            $status = $request->status;
            $customer_id = $request->customer_id;

            $sqlRaw = " ROW_NUMBER() OVER (ORDER BY CONVERT(date, co.TglPesan)) AS No, 
                        CONVERT(date, co.TglPesan) as TglPesan,
                        SUM(cod.Total) as ord_amt,
                        SUM(co.discount) as discount,
                        SUM(cod.Total) - SUM(co.discount) as total
                    ";

            if ($office_id) {
                $sqlWhere.= " and co.office_id = '$office_id'";
                $sqlRaw.= " ,'$office_id' as office_id";
            }
            
            if ($payterm) {
                $sqlWhere.= " and co.pay_term = '$payterm'";
                $sqlRaw.= " ,'$payterm' as payterm";
            }

            if ($ord_type=='Retail') {
                $sqlWhere.= " and co.ord_type = 'R'";
                $sqlRaw.= " ,'$ord_type' as order_type";
            }else if($ord_type=='Dropship') {
                $sqlWhere.= " and co.ord_type = 'D'";
                $sqlRaw.= " ,'$ord_type' as order_type";
            }else{
            }

            if ($start_date)
            {
                if ($end_date)
                {
                    $sqlWhere .= " and co.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
                }
                else
                {
                    $sqlWhere .= " and co.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,GETDATE())";
                }
            }

            if ($customer_id) {
                $sqlWhere.= " and co.CustomerId = '$customer_id'";
                $sqlRaw.= " ,'$customer_id' as customer_id";
            }
            
            $datas = DB::connection('sqlSrvFPI')
                        ->table('CustomerOrder AS co')
                        ->selectRaw($sqlRaw)
                        ->leftjoin('CustomerOrderDetil as cod',function($join){
                            $join->on('co.CustomerOrderNo','=','cod.CustomerOrderNo');
                        })
                        ->whereRaw($sqlWhere)
                        ->groupby(DB::raw('CONVERT(date, co.TglPesan)'))
                        ->get();

            return $this->sendResponse($datas, 'Successfully get List');
        }
        /*
        $sqlWhere = "1=1";
        $office_id = $request->office_id;
        $payterm = $request->payterm;
        $ord_type = $request->ord_type;
        $start_date = date('Y-m-d', strtotime($request->start_date));

        if($request->end_date){
            $end_date   = date('Y-m-d', strtotime($request->end_date));    
        }else{
            $end_date   = date('Y-m-d', strtotime(now()));
        }

        $status = $request->status;
        $customer_id = $request->customer_id;

        $sqlRaw = " ROW_NUMBER() OVER (ORDER BY CONVERT(date, co.TglPesan)) AS No, 
                    CONVERT(date, co.TglPesan) as TglPesan,
                    SUM(cod.Total) as ord_amt,
                    SUM(co.discount) as discount,
                    SUM(cod.Total) - SUM(co.discount) as total
                ";

        if ($office_id) {
            $sqlWhere.= " and co.office_id = '$office_id'";
            $sqlRaw.= " ,'$office_id' as office_id";
        }
        
        if ($payterm) {
            $sqlWhere.= " and co.pay_term = '$payterm'";
            $sqlRaw.= " ,'$payterm' as payterm";
        }

        if ($ord_type=='Retail') {
            $sqlWhere.= " and co.ord_type = 'R'";
            $sqlRaw.= " ,'$ord_type' as order_type";
        }else if($ord_type=='Dropship') {
            $sqlWhere.= " and co.ord_type = 'D'";
            $sqlRaw.= " ,'$ord_type' as order_type";
        }else{
        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and co.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and co.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,GETDATE())";
            }
        }

        if ($status=='Open') {
            $sqlWhere.= " and cod.stat = 'O'";
            $sqlRaw.= " ,'$status' as status";
        }else if($status=='Closed') {
            $sqlWhere.= " and cod.stat = 'C'";
            $sqlRaw.= " ,'$status' as status";
        }else if($status=='Rejected') {
            $sqlWhere.= " and cod.stat = 'X'";
            $sqlRaw.= " ,'$status' as status";
        }else{
        }

        if ($customer_id) {
            $sqlWhere.= " and co.CustomerId = '$customer_id'";
            $sqlRaw.= " ,'$customer_id' as customer_id";
        }
        
        $datas = DB::connection('sqlSrvFPI')
                    ->table('CustomerOrder AS co')
                    ->selectRaw($sqlRaw)
                    ->leftjoin('CustomerOrderDetil as cod',function($join){
                        $join->on('co.CustomerOrderNo','=','cod.CustomerOrderNo');
                    })
                    ->whereRaw($sqlWhere)
                    ->groupby(DB::raw('CONVERT(date, co.TglPesan)'))
                    ->get();

        return $this->sendResponse($datas, 'Successfully get List');
        */
    }
    
    public function showDetailReportOI(Request $request)
    {
        $sqlWhere = "1=1";
        $CustomerOrderNo = $request->CustomerOrderNo;

        if ($CustomerOrderNo) {
    
            $sqlWhere.= " and CustomerOrderDetil.CustomerOrderNo = '$CustomerOrderNo'";
        }
        // dd($sqlWhere);
        $datas = DB::connection('sqlSrvFPI')
                    ->table('CustomerOrderDetil')
                    ->selectRaw("   ROW_NUMBER() OVER (ORDER BY CustomerOrderDetil.BarangJadiId) AS No, 
                                    Customer.NamaCustomer, 
                                    CustomerOrderDetil.BarangJadiId, 
                                    CustomerOrderDetil.CustomerOrderNo, 
                                    CustomerOrderDetil.Lembar, 
                                    CustomerOrderDetil.Panjang, 
                                    CustomerOrderDetil.HargaSatuan, 
                                    CustomerOrderDetil.Total, 
                                    CustomerOrderDetil.stat, 
                                    article.description")
                    ->leftjoin('article ',function($join){
                        $join->on('CustomerOrderDetil.BarangJadiId','=','article.article_id');
                    })
                    ->leftjoin('CustomerOrder ',function($join){
                        $join->on('CustomerOrderDetil.CustomerOrderNo','=','CustomerOrder.CustomerOrderNo');
                    })
                    ->leftjoin('Customer ',function($join){
                        $join->on('CustomerOrder.CustomerId','=','Customer.CustomerId');
                    })
                    ->leftjoin('branch_office ',function($join){
                        $join->on('CustomerOrder.office_id','=','branch_office.office_id');
                    })

                    ->whereRaw($sqlWhere)
                    ->get();


        return $this->sendResponse($datas, 'Successfully get List');

    }

    public function showDetailReportMonthly(Request $request)
    {
        $sqlWhere = "1=1";
        $office_id = $request->office_id;
        $ord_type = $request->ord_type;
        $payterm = $request->payterm;
        $Tahun = $request->Tahun;
        $Bulan = $request->Bulan;
        $customer_id = $request->customer_id;
        $status = $request->status;

        if ($office_id) {
            $sqlWhere.= " and CustomerOrder.office_id = '$office_id'";
        }
        if ($payterm) {
    
            $sqlWhere.= " and CustomerOrder.pay_term = '$payterm'";
        }
        if ($ord_type=='Retail') {
            $sqlWhere.= " and CustomerOrder.ord_type = 'R'";
        }else if($ord_type=='Dropship') {
            $sqlWhere.= " and CustomerOrder.ord_type = 'D'";
        }else{

        }
        if ($Tahun) {
            $sqlWhere.= " and YEAR(CustomerOrder.TglPesan) = '$Tahun'";
        }
        if ($Bulan) {
            $sqlWhere.= " and MONTH(CustomerOrder.TglPesan) = '$Bulan'";
        }

        if ($status=='Open') {
            $sqlWhere.= " and CustomerOrderDetil.stat = 'O'";
        }else if($status=='Closed') {
            $sqlWhere.= " and CustomerOrderDetil.stat = 'C'";
        }else if($status=='Rejected') {
            $sqlWhere.= " and CustomerOrderDetil.stat = 'X'";
        }else{
        }

        if ($customer_id) {
            $sqlWhere.= " and CustomerOrder.CustomerId = '$customer_id'";
        }

        // dd($sqlWhere);
        $datas = DB::connection('sqlSrvFPI')
                    ->table('CustomerOrderDetil')
                    ->selectRaw("   ROW_NUMBER() OVER (ORDER BY CustomerOrder.CustomerOrderNo) AS No, 
                                    Customer.NamaCustomer, 
                                    CustomerOrderDetil.BarangJadiId, 
                                    CustomerOrderDetil.CustomerOrderNo, 
                                    CustomerOrderDetil.Lembar, 
                                    CustomerOrderDetil.Panjang, 
                                    CustomerOrderDetil.HargaSatuan, 
                                    CustomerOrderDetil.Total, 
                                    CustomerOrderDetil.stat, 
                                    CustomerOrder.user_id,
                                    article.description, 
                                    CustomerOrder.DikirimKe, 
                                    CustomerOrder.ord_type, 
                                    pay_term.pay_term_desc,
                                    CustomerOrder.Penerima")
                    ->leftjoin('article ',function($join){
                        $join->on('CustomerOrderDetil.BarangJadiId','=','article.article_id');
                    })
                    ->leftjoin('CustomerOrder ',function($join){
                        $join->on('CustomerOrderDetil.CustomerOrderNo','=','CustomerOrder.CustomerOrderNo');
                    })
                    ->leftjoin('Customer ',function($join){
                        $join->on('CustomerOrder.CustomerId','=','Customer.CustomerId');
                    })
                    ->leftjoin('branch_office ',function($join){
                        $join->on('CustomerOrder.office_id','=','branch_office.office_id');
                    })
                    ->leftjoin('pay_term',function($join){
                        $join->on('CustomerOrder.pay_term','=','pay_term.pay_term_id');
                    })
                    // ->leftjoin('pay_term',function($join){
                    //     $join->on('CustomerOrder.pay_term','=','pay_term.pay_term_id')
                    //          ->on('CustomerOrder.office_id','=','pay_term.office_id');
                    // })
                    ->whereRaw($sqlWhere)
                    ->get();


        return $this->sendResponse($datas, 'Successfully get List');

    }

    public function showDetailReportDaily(Request $request)
    {
        $sqlWhere = "1=1";
        $office_id = $request->office_id;
        $ord_type = $request->ord_type;
        $payterm = $request->payterm;
        $TglPesan = $request->TglPesan;
        $formattedDate = date('Y-m-d', strtotime($TglPesan));
        $customer_id = $request->customer_id;
        $status = $request->status;

        if ($office_id) {
            $sqlWhere.= " and CustomerOrder.office_id = '$office_id'";
        }

        if ($payterm) {
            $sqlWhere.= " and CustomerOrder.pay_term = '$payterm'";
        }
        
        if ($ord_type=='Retail') {
            $sqlWhere.= " and CustomerOrder.ord_type = 'R'";
        }else if($ord_type=='Dropship') {
            $sqlWhere.= " and CustomerOrder.ord_type = 'D'";
        }else{

        }
        if ($TglPesan) {
            $sqlWhere.= " and CONVERT(date, CustomerOrder.TglPesan) = '$formattedDate'";
        }

        if ($status=='Open') {
            $sqlWhere.= " and CustomerOrderDetil.stat = 'O'";
        }else if($status=='Closed') {
            $sqlWhere.= " and CustomerOrderDetil.stat = 'C'";
        }else if($status=='Rejected') {
            $sqlWhere.= " and CustomerOrderDetil.stat = 'X'";
        }else{
        }

        if ($customer_id) {
            $sqlWhere.= " and CustomerOrder.CustomerId = '$customer_id'";
        }
        // dd($sqlWhere);
        $datas = DB::connection('sqlSrvFPI')
                    ->table('CustomerOrderDetil')
                    ->selectRaw("   ROW_NUMBER() OVER (ORDER BY CustomerOrder.CustomerOrderNo) AS No, 
                                    Customer.NamaCustomer, 
                                    CustomerOrderDetil.BarangJadiId, 
                                    CustomerOrderDetil.CustomerOrderNo, 
                                    CustomerOrderDetil.Lembar, 
                                    CustomerOrderDetil.Panjang, 
                                    CustomerOrderDetil.HargaSatuan, 
                                    CustomerOrderDetil.Total, 
                                    CustomerOrderDetil.stat, 
                                    article.description,
                                    CustomerOrder.user_id, 
                                    CustomerOrder.DikirimKe, 
                                    CustomerOrder.Penerima,
                                    CustomerOrder.ord_type, 
                                    pay_term.pay_term_desc
                                    ")
                    ->leftjoin('article ',function($join){
                        $join->on('CustomerOrderDetil.BarangJadiId','=','article.article_id');
                    })
                    ->leftjoin('CustomerOrder ',function($join){
                        $join->on('CustomerOrderDetil.CustomerOrderNo','=','CustomerOrder.CustomerOrderNo');
                    })
                    ->leftjoin('Customer ',function($join){
                        $join->on('CustomerOrder.CustomerId','=','Customer.CustomerId');
                    })
                    ->leftjoin('branch_office ',function($join){
                        $join->on('CustomerOrder.office_id','=','branch_office.office_id');
                    })
                    ->leftjoin('pay_term',function($join){
                        $join->on('CustomerOrder.pay_term','=','pay_term.pay_term_id');
                    })
                    // ->leftjoin('pay_term',function($join){
                    //     $join->on('CustomerOrder.pay_term','=','pay_term.pay_term_id')
                    //          ->on('CustomerOrder.office_id','=','pay_term.office_id');
                    // })
                    ->whereRaw($sqlWhere)
                    ->get();


        return $this->sendResponse($datas, 'Successfully get List');

    }

    public function exportCSVOrderReport(Request $request)
    {
        $sqlWhere = "CustomerOrderDetil.stat = 'C'";
        $payterm = $request->payterm;
        $ord_type = $request->ord_type;
        $office_id = $request->office_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        if ($office_id) {
    
            $sqlWhere.= " and CustomerOrder.office_id = '$office_id'";
        }
        if ($payterm) {
    
            $sqlWhere.= " and CustomerOrder.pay_term = '$payterm'";
        }
        if ($ord_type=='Retail') {
            $sqlWhere.= " and CustomerOrder.ord_type = 'R'";
        }else if($ord_type=='Dropship') {
            $sqlWhere.= " and CustomerOrder.ord_type = 'D'";
        }else{

        }
        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and CustomerOrder.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and CustomerOrder.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime, GETDATE() )";
            }
        }
        // dd($sqlWhere);
        $datas = DB::connection('sqlSrvFPI')
                    ->table('CustomerOrderDetil')
                    ->selectRaw("	ROW_NUMBER() OVER (ORDER BY CustomerOrderDetil.CustomerOrderNo) AS No,
                                    FORMAT(CustomerOrder.TglPesan, 'dd MMMM yyyy') as 'Date Order', 
                                    FORMAT(CustomerOrder.TglPesan, 'HH:mm') as 'Time Order', 
                                    TRIM(branch_office.office) as 'Office',
                                    CustomerOrderDetil.CustomerOrderNo AS 'Trx Id', 
                                    article.description AS 'Item Name', 
                                    commodity.com_desc AS 'Commodity',
                                    Customer.NamaCustomer AS 'Customer Name', 
                                    REPLACE(CustomerOrder.DikirimKe, ',',' ') AS 'Send Address', 
                                    CustomerOrder.Penerima AS 'Receiver',
                                    TRIM(pay_term.pay_term_desc) AS Payterm,
                                    CASE
                                        WHEN CustomerOrder.ord_type = 'R' THEN 'Retail'
                                        WHEN CustomerOrder.ord_type = 'D' THEN 'Dropship'
                                        ELSE '-'
                                    END AS 'Order Type',
                                    CAST(CustomerOrderDetil.Lembar as int) AS 'Sheet', 
                                    CAST(CustomerOrderDetil.Panjang as int) AS 'Length', 
                                    CAST(CustomerOrderDetil.HargaSatuan as int) AS 'Unit Price', 
                                    CASE 
                                    WHEN CustomerOrder.discount = 0 THEN '0'
                                    ELSE CONCAT(CAST(CustomerOrder.discount AS int),' berlaku untuk 1 Transaction ID') 
                                    END AS Discount,
                                    CAST(CustomerOrderDetil.Total as int) AS 'Total'
                                ")
                    ->leftJoin('article',function($join){
                        $join->on('CustomerOrderDetil.BarangJadiId','=','article.article_id');
                    })
                    ->leftJoin('CustomerOrder',function($join){
                        $join->on('CustomerOrderDetil.CustomerOrderNo','=','CustomerOrder.CustomerOrderNo');
                    })
                    ->leftJoin('Customer',function($join){
                        $join->on('CustomerOrder.CustomerId','=','Customer.CustomerId');
                    })
                    ->leftjoin('branch_office',function($join){
                        $join->on('CustomerOrder.office_id','=','branch_office.office_id');
                    })
                    ->leftjoin('pay_term',function($join){
                        $join->on('CustomerOrder.pay_term','=','pay_term.pay_term_id');
                    })
                    ->leftjoin('commodity',function($join){
                        $join->on('article.com_id','=','commodity.com_id')
                             ->on('article.cat_id','=','commodity.cat_id');
                    })
                    // ->leftjoin('pay_term',function($join){
                    //     $join->on('CustomerOrder.pay_term','=','pay_term.pay_term_id')
                    //          ->on('CustomerOrder.office_id','=','pay_term.office_id');
                    // })
                    ->whereRaw($sqlWhere)
                    ->get();
        return $this->sendResponse($datas, 'Successfully get List');
    }

    public function exportExcelOrderReportPerInvoice(Request $request)
    {
        $sqlWhere = "CustomerOrder.stat = 'C'";
        $payterm = $request->payterm;
        $ord_type = $request->ord_type;
        $office_id = $request->office_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        if ($office_id) {
    
            $sqlWhere.= " and CustomerOrder.office_id = '$office_id'";
        }
        if ($payterm) {
    
            $sqlWhere.= " and CustomerOrder.pay_term = '$payterm'";
        }
        if ($ord_type=='Retail') {
            $sqlWhere.= " and CustomerOrder.ord_type = 'R'";
        }else if($ord_type=='Dropship') {
            $sqlWhere.= " and CustomerOrder.ord_type = 'D'";
        }else{

        }
        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and CustomerOrder.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and CustomerOrder.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime, GETDATE() )";
            }
        }
        // dd($sqlWhere);
        $datas = DB::connection('sqlSrvFPI')
                    ->table('CustomerOrder')
                    ->selectRaw("ROW_NUMBER() OVER (ORDER BY CustomerOrder.CustomerOrderNo) AS No, 
                                FORMAT(CustomerOrder.TglPesan, 'dd MMMM yyyy') as 'Date Order', 
                                FORMAT(CustomerOrder.TglPesan, 'HH:mm') as 'Time Order', 
                                TRIM(branch_office.office) as 'Office',
                                CustomerOrder.CustomerOrderNo AS 'Trx Id', 
                                branch_office.office as 'Office',
                                Customer.NamaCustomer AS 'Customer Name', 
                                REPLACE(CustomerOrder.DikirimKe, ',',' ') AS 'Send Address', 
                                CustomerOrder.Penerima AS 'Receiver',
                                TRIM(pay_term.pay_term_desc) AS Payterm,
                                CASE
                                    WHEN CustomerOrder.ord_type = 'R' THEN 'Retail'
                                    WHEN CustomerOrder.ord_type = 'D' THEN 'Dropship'
                                    ELSE '-'
                                END AS 'Order Type',
                                CASE 
                                        WHEN CustomerOrder.discount = 0 THEN '0'
                                        ELSE CONCAT(CAST(CustomerOrder.discount AS int),' berlaku untuk 1 Transaction ID') 
                                END AS Discount,
                                CAST((CustomerOrder.ord_amt - CustomerOrder.discount) AS int) as Total
                                ")
                    ->join('Customer',function($join){
                        $join->on('CustomerOrder.CustomerId','=','Customer.CustomerId');
                    })
                    ->join('branch_office',function($join){
                        $join->on('CustomerOrder.office_id','=','branch_office.office_id');
                    })
                    ->leftjoin('pay_term',function($join){
                        $join->on('CustomerOrder.pay_term','=','pay_term.pay_term_id');
                    })
                    ->whereRaw($sqlWhere)
                    ->get();

        return $this->sendResponse($datas, 'Successfully get List');
    }

/* ======================================================================================= */
/* ====================================| CREDIT NOTE |==================================== */
/* ======================================================================================= */
    public function showCreditNote(Request $request)
    {
        $sqlWhere = "1=1";
        $customer = $request->customer;
        $office_id = $request->office_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        if ($office_id) {

            $sqlWhere.= " and CustomerOrder.office_id = '$office_id'";
        }

        if ($customer) {

            $sqlWhere.= " and Customer.CustomerId = '$customer'";
        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and CustomerOrder.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and CustomerOrder.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime, GETDATE())";
            }
        }
        // dd($sqlWhere);
        $datas = DB::connection('sqlSrvFPI')
                    ->table('credit_note')
                    ->selectRaw("   ROW_NUMBER() OVER (ORDER BY credit_note.customerorderno) AS No, 
                                    credit_note.ref_no, 
                                    credit_note.customerorderno, 
                                    credit_note.bk, 
                                    credit_note.period, 
                                    credit_note.tr_date, 
                                    credit_note.tr_type, 
                                    credit_note.doc_id, 
                                    credit_note.inv_id, 
                                    credit_note.remark, 
                                    credit_note.tr_amt,
                                    Customer.NamaCustomer, 
                                    CustomerOrder.office_id, 
                                    Customer.CustomerId,
                                    branch_office.office
                                ")
                    ->leftJoin('CustomerOrder',function($join){
                        $join->on('credit_note.customerorderno','=','CustomerOrder.CustomerOrderNo');
                    })
                    ->leftJoin('Customer',function($join){
                        $join->on('CustomerOrder.CustomerId','=','Customer.CustomerId');
                    })
                    ->leftJoin('branch_office',function($join){
                        $join->on('CustomerOrder.office_id','=','branch_office.office_id');
                    })
                    ->whereRaw($sqlWhere)
                    ->get();


        return $this->sendResponse($datas, 'Successfully get List');
    }

    public function getCustInList(Request $request)
    {
        $sqlWhere = "1=1";
        $office_id = $request->office_id;
        
        if ($office_id) {

            $sqlWhere.= " and CustomerOrder.office_id = '$office_id'";
        }
            $datas = DB::connection('sqlSrvFPI')
                        ->table('credit_note')
                        ->selectRaw ("
                                        Customer.CustomerId, 
                                        Customer.NamaCustomer
                                    ")
                        ->leftJoin('CustomerOrder', function($join) {
                            $join->on('credit_note.customerorderno', '=', 'CustomerOrder.CustomerOrderNo');
                        })
                        ->leftJoin('Customer', function($join) {
                            $join->on('CustomerOrder.CustomerId', '=', 'Customer.CustomerId');
                        })
                        ->whereRaw($sqlWhere)
                        ->groupBy('Customer.CustomerId', 'Customer.NamaCustomer')
                        ->get();


            return $this->sendResponse($datas, 'Successfully get List');
        // }else{
        //     return $this->sendResponse('', 'Kosong');
        // }

        
       
    }
    
    public function getDetailCreditNote(Request $request)
    {
        $ref_no = $request->ref_no;

        if ($ref_no) {
    
            $sqlWhere= "credit_note.ref_no = '$ref_no'";
        }
        // dd($sqlWhere);
        $datas = DB::connection('sqlSrvFPI')
                    ->table('credit_note')
                    ->selectRaw("
                                ROW_NUMBER() OVER (ORDER BY inv_trans_dtl.article_id) AS No,
                                inv_trans_dtl.article_id,
                                article.description, 
                                inv_trans_dtl.qty, 
                                inv_trans_dtl.unit_price, 
                                inv_trans_dtl.amt_item
                                ")
                    ->leftJoin('inv_trans_dtl',function($join){
                        $join->on('credit_note.ref_no','=','inv_trans_dtl.tr_id');
                    })
                    ->leftJoin('article',function($join){
                        $join->on('inv_trans_dtl.article_id','=','article.article_id');
                    })
                    ->whereRaw($sqlWhere)
                    ->get();


        return $this->sendResponse($datas, 'Successfully get List');

    }

    public function exportCSVCreditNote(Request $request)
    {
        $sqlWhere = "1=1";
        $customer = $request->customer;
        $office_id = $request->office_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        if ($office_id) {
    
            $sqlWhere.= " and CustomerOrder.office_id = '$office_id'";
        }
        if ($customer) {
    
            $sqlWhere.= " and Customer.CustomerId = '$customer'";
        }
        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and CustomerOrder.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and CustomerOrder.TglPesan BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime, GETDATE() )";
            }
        }
        // dd($sqlWhere);
        $datas = DB::connection('sqlSrvFPI')
                    ->table('credit_note')
                    ->selectRaw("	ROW_NUMBER() OVER (ORDER BY credit_note.customerorderno) AS No, 
                                    FORMAT(CustomerOrder.TglPesan, 'dd MMMM yyyy') as 'Date Order', 
                                    credit_note.customerorderno as 'Order Number', 
                                    credit_note.ref_no as 'Retur Number', 
                                    Customer.NamaCustomer as 'Customer Name', 
                                    credit_note.remark as 'Description', 
                                    credit_note.tr_amt as 'Price'
                                ")
                    ->leftJoin('CustomerOrder',function($join){
                        $join->on('credit_note.customerorderno','=','CustomerOrder.CustomerOrderNo');
                    })
                    ->leftJoin('Customer',function($join){
                        $join->on('CustomerOrder.CustomerId','=','Customer.CustomerId');
                    })
                    ->whereRaw($sqlWhere)
                    ->get();
        return $this->sendResponse($datas, 'Successfully get List');

    }
    
}
