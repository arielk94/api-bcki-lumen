<?php

namespace App\Http\Controllers\warehouse;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClosingDailyController extends Controller
{
    public function period_daily(Request $request)
    {
        $current_daily =  DB::connection("sqlSrvFPI")
                            ->table('period')
                            ->selectRaw("MAX(end_date) as close_date, MAX(curr_period) as curr_period")
                            ->where('dept_id', 'PRODWH')
                            ->where('office_id', $request->office_id)
                            ->first();

        $data = [
            'close_date' => $current_daily ? Carbon::parse($current_daily->close_date)->addDays(1)->format('Y-m-d') : NULL,
            'curr_period' => $current_daily ? Carbon::parse($current_daily->close_date)->addDays(1)->format('Ym') : NULL
        ];

        return $this->sendResponse($data, 'Successfully get Data');
    }

    public function index(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $region = $request->region;
            $office_id = $request->office_id;
            $wh_id = $request->wh_id;
            $article_id = $request->article_id;
            $avail = $request->avail;

            $start_date = $request->start_date;
            if(!$start_date)
            {
                $start_date = '';
            }

            $end_date = $request->end_date;
            if(!$end_date)
            {
                $end_date = '';
            }
            
            if (!empty($office_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal_hist.office_id = ". "'" . $office_id . "'";
            }

            if (!empty($wh_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal_hist.wh_id = ". "'" . $wh_id . "'";
            }

            if (!empty($article_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal_hist.article_id = ". "'" . $article_id . "'";
            }

            if ($start_date)
            {
                if ($end_date)
                {
                    $sqlWhere .= " and inv_bal_hist.period BETWEEN '" .$start_date. "' AND '" . $end_date . "'";
                }
                else
                {
                    $sqlWhere .= " and inv_bal_hist.period = '" .$start_date. "'";
                }
            }

            // if ($avail == 'Y') {
            //     $sqlWhere = $sqlWhere . "and inv_bal_hist.qty_oh <> 0";
            // }

            $datas = DB::connection("sqlSrvFPI")
                    ->table('inv_bal_hist')
                    ->selectRaw("
                        inv_bal_hist.period, inv_bal_hist.office_id, inv_bal_hist.article_id, inv_bal_hist.defect, inv_bal_hist.batch_id, inv_bal_hist.length, inv_bal_hist.add_id, inv_bal_hist.tor_id, inv_bal_hist.wh_id, inv_bal_hist.bin_id, inv_bal_hist.spk_id,inv_bal_hist.spk_item,inv_bal_hist.qty_awal,inv_bal_hist.qty_oh,inv_bal_hist.qty_rcv,inv_bal_hist.qty_iss,inv_bal_hist.qty_ia,inv_bal_hist.qty_waste, inv_bal_hist.wgt_awal,inv_bal_hist.wgt_oh,inv_bal_hist.wgt_rcv,inv_bal_hist.wgt_iss,inv_bal_hist.wgt_ia,inv_bal_hist.wgt_waste,
                        TRIM(article.description) as article,
                        whouse.descr as whouse_name,
                        branch_office.office
                    ")
                    ->join('article', 'inv_bal_hist.article_id', '=', 'article.article_id')
                    ->join('whouse', function($join){
                        $join->on('inv_bal_hist.office_id', '=', 'whouse.office_id')
                        ->on('inv_bal_hist.wh_id', '=', 'whouse.wh_id');
                    })
                    ->join('branch_office', function($join){
                        $join->on('inv_bal_hist.office_id', '=', 'branch_office.office_id');
                    })                           
                    ->whereRaw($sqlWhere)
                    ->orderBy('inv_bal_hist.period', 'ASC')
                    ->get();

            return $this->sendResponse($datas, 'Successfully get inv_bal_hist ');


        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
        
    }

    public function check_transaction(Request $request)
    {
        $arrayReusable = [];

        // DB::connection('sqlSrvFPI')->beginTransaction();

        // try {
            $yesterday_date = Carbon::parse($request->tr_date)->subDays(1)->format('Y-m-d');

            // $current =  DB::connection("sqlSrvFPI")
            //                 ->table('period')
            //                 ->where('dept_id', 'PRODWH')
            //                 ->where('office_id', $request->office_id)
            //                 ->first();

            // if ($current) {
                $period = $request->period;
                // if ($current->curr_period == $period) {

                //     $current_daily =  DB::connection("sqlSrvFPI")
                //                         ->table('period_daily')
                //                         ->where('dept_id', 'PRODWH')
                //                         ->where('office_id', $request->office_id)
                //                         ->where('close_date', $yesterday_date)
                //                         ->first();
                                        
                //     if ($current_daily) {
                        $trans_details = DB::connection("sqlSrvFPI")
                                        ->select(DB::raw("
                                        SELECT 
                                            TRIM(branch_office.office) as office, 
                                            TRIM(whouse.descr) as whouse_name,
                                            TRIM(article.description) as NamaBarang,
                                            inv_trans_dtl.office_id,
                                            inv_trans_dtl.article_id,
                                            inv_trans_dtl.defect,
                                            inv_trans_dtl.batch_id,
                                            inv_trans_dtl.length,
                                            inv_trans_dtl.add_id,
                                            inv_trans_dtl.tor_id,
                                            inv_trans_dtl.wh_id,
                                            inv_trans_dtl.bin_id,
                                            inv_trans_dtl.spk_id,
                                            inv_trans_dtl.spk_item,
                                            COALESCE ( inv_bal.qty_awal, 0 ) AS qty_awal,
                                            COALESCE ( inv_bal.qty_oh, 0 ) AS qty_oh,
                                            COALESCE ( inv_bal.qty_rcv, 0 ) AS qty_rcv,
                                            COALESCE ( inv_bal.qty_iss, 0 ) AS qty_iss,
                                            COALESCE ( inv_bal.qty_ia, 0 ) AS qty_ia,
                                            COALESCE ( inv_bal.qty_waste, 0 ) AS qty_waste,
                                        -- 	inv_trans_dtl.tr_type,
                                            SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'MR', 'RC', 'RW', 'SR', 'TR', 'RP' ) THEN inv_trans_dtl.qty ELSE 0 END ) AS qty_rcv_trans,
                                            SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'IS', 'IW', 'MI', 'TI', 'PR', 'SA' ) THEN inv_trans_dtl.qty ELSE 0 END ) AS qty_iss_trans,
                                            SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'IA' ) THEN inv_trans_dtl.qty ELSE 0 END ) AS qty_ia_trans 
                                        FROM
                                            inv_trans_dtl
                                            LEFT OUTER JOIN inv_bal ON inv_trans_dtl.office_id = inv_bal.office_id 
                                            AND inv_trans_dtl.article_id = inv_bal.article_id 
                                            AND inv_trans_dtl.wh_id = inv_bal.wh_id 
                                            AND inv_trans_dtl.bin_id = inv_bal.bin_id 
                                            AND inv_trans_dtl.batch_id = inv_bal.batch_id 
                                            AND inv_trans_dtl.defect = inv_bal.defect 
                                            AND inv_trans_dtl.length = inv_bal.length 
                                            AND inv_trans_dtl.add_id = inv_bal.add_id 
                                            AND inv_trans_dtl.tor_id = inv_bal.tor_id 
                                            AND inv_trans_dtl.spk_id = inv_bal.spk_id 
                                            AND inv_trans_dtl.spk_item = inv_bal.spk_item
                                            JOIN branch_office
                                            ON
                                                    inv_trans_dtl.office_id = branch_office.office_id
                                            JOIN whouse
                                            ON
                                                    inv_trans_dtl.office_id = whouse.office_id AND
                                                    inv_trans_dtl.wh_id = whouse.wh_id
                                            JOIN article
                                            ON
                                                    inv_trans_dtl.article_id = article.article_id
                                        WHERE inv_trans_dtl.period = '$period' AND inv_trans_dtl.office_id = '$request->office_id'
                                        GROUP BY
                                            branch_office.office,
                                            whouse.descr,
                                            article.description,
                                            inv_trans_dtl.office_id,
                                            inv_trans_dtl.article_id,
                                            inv_trans_dtl.defect,
                                            inv_trans_dtl.batch_id,
                                            inv_trans_dtl.length,
                                            inv_trans_dtl.add_id,
                                            inv_trans_dtl.tor_id,
                                            inv_trans_dtl.wh_id,
                                            inv_trans_dtl.bin_id,
                                            inv_trans_dtl.spk_id,
                                            inv_trans_dtl.spk_item,
                                            inv_bal.qty_awal,
                                            inv_bal.qty_oh,
                                            inv_bal.qty_rcv,
                                            inv_bal.qty_iss,
                                            inv_bal.qty_ia,
                                            inv_bal.qty_waste -- 	inv_trans_dtl.tr_type
                                            
                                        HAVING
                                            COALESCE ( inv_bal.qty_rcv, 0 ) <> SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'MR', 'RC', 'RW', 'SR', 'TR', 'RP' ) THEN inv_trans_dtl.qty ELSE 0 END ) 
                                            OR COALESCE ( inv_bal.qty_iss, 0 ) <> SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'IS', 'IW', 'MI', 'TI', 'PR', 'SA' ) THEN inv_trans_dtl.qty ELSE 0 END ) 
                                            OR COALESCE ( inv_bal.qty_ia, 0 ) <> SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'IA' ) THEN inv_trans_dtl.qty ELSE 0 END )
                                        "));

                        // DB::connection('sqlSrvFPI')->commit();

                        return $this->sendResponse($trans_details, 'Successfully get Data');


                    // } else {
                    //     return $this->sendError('Belum di closing harian', 'Error', 500);
                    // }                     
                    
                // } else {
                //     return $this->sendError('Belum di closing bulanan', 'Error', 500);
                // }
            // }
            // else {
            //     return $this->sendError('Belum di closing bulanan', 'Error', 500);
            // }
        // }
        // catch (\Exception $e) {
        //     DB::connection('sqlSrvFPI')->rollBack();
        //     return $this->sendError($e->getMessage(), 'Error', 500);
        // }
    }

    public function check_balance(Request $request)
    {
        $arrayReusable = [];

        // DB::connection('sqlSrvFPI')->beginTransaction();

        // try {
            $yesterday_date = Carbon::parse($request->tr_date)->subDays(1)->format('Y-m-d');
            $last_month = Carbon::parse($request->tr_date)->subMonthNoOverflow(1)->format('Ym');

            // $current =  DB::connection("sqlSrvFPI")
            //                 ->table('period')
            //                 ->where('dept_id', 'PRODWH')
            //                 ->where('office_id', $request->office_id)
            //                 ->first();

            // if ($current) {
                $period = $request->period;
                // if ($current->curr_period == $period) {

                //     $current_daily =  DB::connection("sqlSrvFPI")
                //                         ->table('period_daily')
                //                         ->where('dept_id', 'PRODWH')
                //                         ->where('office_id', $request->office_id)
                //                         ->where('close_date', $yesterday_date)
                //                         ->first();
                                        
                //     if ($current_daily) {
                        $trans_details = DB::connection("sqlSrvFPI")
                                        ->select(DB::raw("
                                        SELECT 
                                            TRIM(branch_office.office) as office, 
                                            TRIM(whouse.descr) as whouse_name,
                                            TRIM(article.description) as NamaBarang,
                                            inv_bal.office_id,
                                            inv_bal.article_id,
                                            inv_bal.defect,
                                            inv_bal.batch_id,
                                            inv_bal.length,
                                            inv_bal.add_id,
                                            inv_bal.tor_id,
                                            inv_bal.wh_id,
                                            inv_bal.bin_id,
                                            inv_bal.spk_id,
                                            inv_bal.spk_item,
                                            COALESCE ( inv_bal.qty_awal, 0 ) AS qty_awal,
                                            COALESCE ( inv_bal.qty_oh, 0 ) AS qty_oh,
                                            COALESCE ( inv_bal.qty_rcv, 0 ) AS qty_rcv,
                                            COALESCE ( inv_bal.qty_iss, 0 ) AS qty_iss,
                                            COALESCE ( inv_bal.qty_ia, 0 ) AS qty_ia,
                                            COALESCE ( inv_bal.qty_waste, 0 ) AS qty_waste,
                                        -- 	inv_trans_dtl.tr_type,
                                            SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'MR', 'RC', 'RW', 'SR', 'TR', 'RP' ) THEN inv_trans_dtl.qty ELSE 0 END ) AS qty_rcv_trans,
                                            SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'IS', 'IW', 'MI', 'TI', 'PR', 'SA' ) THEN inv_trans_dtl.qty ELSE 0 END ) AS qty_iss_trans,
                                            SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'IA' ) THEN inv_trans_dtl.qty ELSE 0 END ) AS qty_ia_trans 
                                        FROM
                                            inv_bal
                                            LEFT OUTER JOIN inv_trans_dtl ON inv_trans_dtl.office_id = inv_bal.office_id 
                                            AND inv_trans_dtl.article_id = inv_bal.article_id 
                                            AND inv_trans_dtl.wh_id = inv_bal.wh_id 
                                            AND inv_trans_dtl.bin_id = inv_bal.bin_id 
                                            AND inv_trans_dtl.batch_id = inv_bal.batch_id 
                                            AND inv_trans_dtl.defect = inv_bal.defect 
                                            AND inv_trans_dtl.length = inv_bal.length 
                                            AND inv_trans_dtl.add_id = inv_bal.add_id 
                                            AND inv_trans_dtl.tor_id = inv_bal.tor_id 
                                            AND inv_trans_dtl.spk_id = inv_bal.spk_id 
                                            AND inv_trans_dtl.spk_item = inv_bal.spk_item
                                            JOIN branch_office
                                            ON
                                                    inv_bal.office_id = branch_office.office_id
                                            JOIN whouse
                                            ON
                                                    inv_bal.office_id = whouse.office_id AND
                                                    inv_bal.wh_id = whouse.wh_id
                                            JOIN article
                                            ON
                                                    inv_bal.article_id = article.article_id
                                        WHERE inv_trans_dtl.period = '$period' AND inv_trans_dtl.office_id = '$request->office_id'
                                        GROUP BY
                                            inv_bal.office_id,
                                            inv_bal.article_id,
                                            inv_bal.defect,
                                            inv_bal.batch_id,
                                            inv_bal.length,
                                            inv_bal.add_id,
                                            inv_bal.tor_id,
                                            inv_bal.wh_id,
                                            inv_bal.bin_id,
                                            inv_bal.spk_id,
                                            inv_bal.spk_item,
                                            inv_bal.qty_awal,
                                            inv_bal.qty_oh,
                                            inv_bal.qty_rcv,
                                            inv_bal.qty_iss,
                                            inv_bal.qty_ia,
                                            inv_bal.qty_waste, -- 	inv_trans_dtl.tr_type
                                            branch_office.office,
                                            whouse.descr,
                                            article.description
                                        HAVING
                                            COALESCE ( inv_bal.qty_rcv, 0 ) <> SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'MR', 'RC', 'RW', 'SR', 'TR', 'RP' ) THEN inv_trans_dtl.qty ELSE 0 END ) 
                                            OR COALESCE ( inv_bal.qty_iss, 0 ) <> SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'IS', 'IW', 'MI', 'TI', 'PR', 'SA' ) THEN inv_trans_dtl.qty ELSE 0 END ) 
                                            OR COALESCE ( inv_bal.qty_ia, 0 ) <> SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'IA' ) THEN inv_trans_dtl.qty ELSE 0 END )
                                        "));

                        // DB::connection('sqlSrvFPI')->commit();

                        return $this->sendResponse($trans_details, 'Successfully get Data');


                //     } else {
                //         return $this->sendError('Belum di closing harian', 'Error', 500);
                //     }                     
                    
                // } else {
                //     return $this->sendError('Belum di closing bulanan', 'Error', 500);
                // }
            // }
            // else {
            //     return $this->sendError('Belum di closing bulanan', 'Error', 500);
            // }
        // }
        // catch (\Exception $e) {
        //     DB::connection('sqlSrvFPI')->rollBack();
        //     return $this->sendError($e->getMessage(), 'Error', 500);
        // }
    }

    public function calculate(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            foreach ($request->products as $key => $value) {

                $trans_detail = DB::connection("sqlSrvFPI")
                                ->table('inv_trans_dtl')
                                ->selectRaw("
                                SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'MR', 'RC', 'RW', 'SR', 'TR', 'RP' ) THEN inv_trans_dtl.qty ELSE 0 END ) AS qty_rcv_trans,
                                SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'IS', 'IW', 'MI', 'TI', 'PR', 'SA' ) THEN inv_trans_dtl.qty ELSE 0 END ) AS qty_iss_trans,
                                SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'IA' ) THEN inv_trans_dtl.qty ELSE 0 END ) AS qty_ia_trans,
                                SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'MR', 'RC', 'RW', 'SR', 'TR', 'RP' ) THEN inv_trans_dtl.wgt ELSE 0 END ) AS wgt_rcv_trans,
                                SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'IS', 'IW', 'MI', 'TI', 'PR', 'SA' ) THEN inv_trans_dtl.wgt ELSE 0 END ) AS wgt_iss_trans,
                                SUM ( CASE WHEN inv_trans_dtl.tr_type IN ( 'IA' ) THEN inv_trans_dtl.wgt ELSE 0 END ) AS wgt_ia_trans
                                ")
                                ->where('office_id', $value['office_id'])
                                ->where('article_id', $value['article_id'])
                                ->where('wh_id', $value['wh_id'])
                                ->where('bin_id', $value['bin_id'])
                                ->where('batch_id', $value['batch_id'] ? $value['batch_id'] : '')
                                ->where('period', $request->period)
                                ->where('add_id', $value['add_id'] ? $value['add_id'] : '')
                                ->where('tor_id', $value['tor_id'] ? $value['tor_id'] : '')
                                ->where('length', $value['length'])
                                ->where('defect', $value['defect'] ? $value['defect'] : 0)
                                ->where('spk_id', $value['spk_id'] ? $value['spk_id'] : ' ')
                                ->where('spk_item', $value['spk_item'] ? $value['spk_item'] : 0)
                                ->first();

                if ($trans_detail) {
                    $inv_bal =  DB::connection("sqlSrvFPI")
                                ->table('inv_bal')
                                ->where('office_id', $value['office_id'])
                                ->where('article_id', $value['article_id'])
                                ->where('wh_id', $value['wh_id'])
                                ->where('bin_id', $value['bin_id'])
                                ->where('batch_id', $value['batch_id'] ? $value['batch_id'] : '')
                                ->where('add_id', $value['add_id'] ? $value['add_id'] : '')
                                ->where('tor_id', $value['tor_id'] ? $value['tor_id'] : '')
                                ->where('length', $value['length'])
                                ->where('defect', $value['defect'] ? $value['defect'] : 0)
                                ->where('spk_id', $value['spk_id'] ? $value['spk_id'] : ' ')
                                ->where('spk_item', $value['spk_item'] ? $value['spk_item'] : 0)
                                ->first();

                    if ($inv_bal) {
                        $hitung_qty_oh = (double) $inv_bal->qty_awal + (double) $trans_detail->qty_rcv_trans - (double) $trans_detail->qty_iss_trans + (double) $trans_detail->qty_ia_trans - (double) $inv_bal->qty_waste;

                        $hitung_wgt_oh = (double) $inv_bal->wgt_awal + (double) $trans_detail->wgt_rcv_trans - (double) $trans_detail->wgt_iss_trans + (double) $trans_detail->wgt_ia_trans - (double) $inv_bal->wgt_waste;

                        DB::connection("sqlSrvFPI")
                        ->table('inv_bal')
                        ->where('office_id', $value['office_id'])
                        ->where('article_id', $value['article_id'])
                        ->where('wh_id', $value['wh_id'])
                        ->where('bin_id', $value['bin_id'])
                        ->where('batch_id', $value['batch_id'] ? $value['batch_id'] : '')
                        ->where('add_id', $value['add_id'] ? $value['add_id'] : '')
                        ->where('tor_id', $value['tor_id'] ? $value['tor_id'] : '')
                        ->where('length', $value['length'])
                        ->where('defect', $value['defect'] ? $value['defect'] : 0)
                        ->where('spk_id', $value['spk_id'] ? $value['spk_id'] : ' ')
                        ->where('spk_item', $value['spk_item'] ? $value['spk_item'] : 0)
                        ->update([
                            'qty_rcv' => (double) $trans_detail->qty_rcv_trans,
                            'wgt_rcv' => (double) $trans_detail->wgt_rcv_trans,
                            'qty_iss' => (double) $trans_detail->qty_iss_trans,
                            'wgt_iss' => (double) $trans_detail->wgt_iss_trans,
                            'qty_ia' => (double) $trans_detail->qty_ia_trans,
                            'wgt_ia' => (double) $trans_detail->wgt_ia_trans,
                            'qty_oh' => (double) $hitung_qty_oh,
                            'wgt_oh' => (double) $hitung_wgt_oh,
                            'dt_modified' => now(),
                            // 'user_id' => Auth::user()->user_id2
                            'user_id' => getAuth($request)->user_id2,
                        ]);
                    }
                    
                }
            }

            DB::connection('sqlSrvFPI')->commit();

            return $this->sendResponse('Successfully repair balance', 'Successfully get Data');

        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getDataClosing(Request $request)
    {
        $datas = DB::connection("sqlSrvFPI")
                    ->table('inv_bal_daily')
                    ->selectRaw("
                    inv_bal_daily.*,
                    TRIM(branch_office.office) as office, 
                    TRIM(whouse.descr) as whouse_name,
                    TRIM(article.description) as NamaBarang
                    ")
                    ->join('branch_office AS branch_office',function($join){
                        $join->on('inv_bal_daily.office_id','=','branch_office.office_id');
                    })
                    ->join('whouse', function($join){
                        $join->on('inv_bal_daily.office_id', '=', 'whouse.office_id')
                        ->on('inv_bal_daily.wh_id', '=', 'whouse.wh_id');
                    })
                    ->join('article AS article',function($join){
                        $join->on('article.article_id','=','inv_bal_daily.article_id');
                    })
                    ->where('inv_bal_daily.close_date', $request->tr_date)
                    ->where('inv_bal_daily.office_id', trim($request->office_id))
                    ->orderBy('inv_bal_daily.article_id')
                    ->get();

        return $this->sendResponse($datas, 'Successfully get Data');
    }

    public function closing(Request $request)
    {
        // DB::connection('sqlSrvFPI')->beginTransaction();

        // try {
            $yesterday_date = Carbon::parse($request->tr_date)->subDays(1)->format('Y-m-d');
            $nextday_date = Carbon::parse(now())->addDays(1)->format('Y-m-d');

            $end = Carbon::parse($yesterday_date)->endOfMonth()->format('Y-m-d');
            $current_date = Carbon::parse($request->tr_date)->format('Y-m-d');
            
            if ($nextday_date == $current_date) {
                return $this->sendError('Maaf anda tidak bisa closing melebihi hari ini', 'Warning', 500);
            }

            $period = $request->period;

            $current =  DB::connection("sqlSrvFPI")
                        ->table('period')
                        ->where('dept_id', 'PRODWH')
                        ->where('office_id', $request->office_id)
                        ->first();

            if ($current_date == $end) {
                $this->closingMonthlyFast($request, $request->tr_date);
                $this->updatePeriod($request, $request->tr_date, $period);
                // DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Closing', 'Successfully Closing Monthly');
            } else {
                return $this->sendError('Upps, sorry belum akhir bulan', 'Error', 500);
            }

        // }
        // catch (\Exception $e) {
        //     DB::connection('sqlSrvFPI')->rollBack();
        //     return $this->sendError($e->getMessage(), 'Error', 500);
        // }
    }

    private function saveInvBalDaily($request, $qty_awal, $wgt_awal, $value)
    {
        $inv_bal_daily = DB::connection("sqlSrvFPI")
                        ->table('inv_bal_daily')
                        ->where('close_date', $request->tr_date)
                        ->where('office_id', trim($value->office_id))
                        ->where('article_id', trim($value->article_id))
                        ->where('defect', $value->defect)
                        ->where('batch_id', trim($value->batch_id))
                        ->where('length', $value->length)
                        ->where('add_id', trim($value->add_id))
                        ->where('tor_id', trim($value->tor_id))
                        ->where('wh_id', trim($value->wh_id))
                        ->where('bin_id', trim($value->bin_id))
                        ->where('spk_id', trim($value->spk_id))
                        ->where('spk_item', $value->spk_item)
                        ->first();

        if ($inv_bal_daily) {
            DB::connection("sqlSrvFPI")
            ->table('inv_bal_daily')
            ->where('close_date', $request->tr_date)
            ->where('office_id', trim($value->office_id))
            ->where('article_id', trim($value->article_id))
            ->where('defect', $value->defect)
            ->where('batch_id', trim($value->batch_id))
            ->where('length', $value->length)
            ->where('add_id', trim($value->add_id))
            ->where('tor_id', trim($value->tor_id))
            ->where('wh_id', trim($value->wh_id))
            ->where('bin_id', trim($value->bin_id))
            ->where('spk_id', trim($value->spk_id))
            ->where('spk_item', $value->spk_item)
            ->update([
                'qty_awal' => (float) str_replace(',', '.', $value->qty_awal),
                'qty_oh' => (float) str_replace(',', '.', $value->qty_awal) + (float) str_replace(',', '.', $value->qty_rcv) - (float) str_replace(',', '.', $value->qty_iss) + (float) str_replace(',', '.', $value->qty_ia),
                'qty_rcv' => (float) str_replace(',', '.', $value->qty_rcv),
                'qty_iss' => (float) str_replace(',', '.', $value->qty_iss),
                'qty_ia' => (float) str_replace(',', '.', $value->qty_ia),
                'qty_waste' => 0,
                'wgt_awal' => $wgt_awal,
                'wgt_oh' => (double) $wgt_awal + (double) $value->wgt_rcv - (double) $value->wgt_iss + (double) $value->wgt_ia,
                'wgt_rcv' => (double) $value->wgt_rcv,
                'wgt_iss' => (double) $value->wgt_iss,
                'wgt_ia' => (double)  $value->wgt_ia,
                'wgt_waste' => 0,
                'dt_modified' => now(),
                
                'user_id' => getAuth($request)->user_id2,
                // 'user_id' => Auth::user()->user_id2
            ]);

        } else {
            DB::connection("sqlSrvFPI")
                ->table('inv_bal_daily')
                ->insert([
                    'close_date' => $request->tr_date,
                    'office_id' => trim($value->office_id),
                    'article_id' => trim($value->article_id),
                    'defect' => $value->defect,
                    'length' => $value->length,
                    'add_id' => trim($value->add_id),
                    'tor_id' => trim($value->tor_id),
                    'wh_id' => trim($value->wh_id),
                    'bin_id' => trim($value->bin_id),
                    'spk_id' => trim($value->spk_id),
                    'spk_item' => $value->spk_item,
                    'qty_awal' => (float) str_replace(',', '.', $value->qty_awal),
                    'qty_oh' => (float) str_replace(',', '.', $value->qty_awal) + (float) str_replace(',', '.', $value->qty_rcv) - (float) str_replace(',', '.', $value->qty_iss) + (float) str_replace(',', '.', $value->qty_ia),
                    'qty_rcv' => (float) str_replace(',', '.', $value->qty_rcv),
                    'qty_iss' => (float) str_replace(',', '.', $value->qty_iss),
                    'qty_ia' => (float) str_replace(',', '.', $value->qty_ia),
                    'qty_waste' => 0,
                    'wgt_awal' => $wgt_awal,
                    'wgt_oh' => (double) $wgt_awal + (double) $value->wgt_rcv - (double) $value->wgt_iss + (double) $value->wgt_ia,
                    'wgt_rcv' => (double) $value->wgt_rcv,
                    'wgt_iss' => (double) $value->wgt_iss,
                    'wgt_ia' => (double)  $value->wgt_ia,
                    'wgt_waste' => 0,
                    'dt_modified' => now(),
                    // 'user_id' => Auth::user()->user_id2
                    'user_id' => getAuth($request)->user_id2,

                ]);
        }
    }

    private function savePeriodDaily($request){
        $period_daily = DB::connection("sqlSrvFPI")
                        ->table('period_daily')
                        ->where('office_id', trim($request->office_id))
                        ->where('dept_id', 'PRODWH')
                        ->where('close_date', $request->tr_date)
                        ->where('curr_period', $request->period)
                        ->first();

        if ($period_daily) {
            DB::connection("sqlSrvFPI")
            ->table('period_daily')
            ->where('office_id', trim($request->office_id))
            ->where('dept_id', 'PRODWH')
            ->where('close_date', $request->tr_date)
            ->where('curr_period', $request->period)
            ->update([
                'closed_by' => Auth::user()->user_id2,
                'created_date' => now()
            ]);

        } else {
            DB::connection("sqlSrvFPI")
            ->table('period_daily')
            ->insert([
                'office_id' => $request->office_id,
                'dept_id' => 'PRODWH',
                'close_date' => $request->tr_date,
                'curr_period' => $request->period,
                'closed_by' => Auth::user()->user_id2,
                'created_date' => now()
            ]);
        }
        
    }

    // private function closingMonthly($request, $end_date, $yesterday_date, $current){
    //     $period = $current ? $current->curr_period : Carbon::parse($end_date)->format('Ym');
    //     $last_period = $current ? $current->prev_period : Carbon::parse($end_date)->subMonthNoOverflow(1)->format('Ym');

    //     $trans_details = DB::connection("sqlSrvFPI")
    //                     ->select(DB::raw("
    //                     SELECT
    //                         inv_bal.office_id,
    //                         inv_bal.article_id,
    //                         inv_bal.defect,
    //                         inv_bal.batch_id,
    //                         inv_bal.length,
    //                         inv_bal.add_id,
    //                         inv_bal.tor_id,
    //                         inv_bal.wh_id,
    //                         inv_bal.bin_id,
    //                         inv_bal.spk_id,
    //                         inv_bal.spk_item,
    //                         inv_bal.qty_awal,
    //                         COALESCE(inv_trans_dtl.qty_rcv, 0) as qty_rcv,
    //                         COALESCE(inv_trans_dtl.qty_iss, 0) as qty_iss,
    //                         COALESCE(inv_trans_dtl.qty_adj, 0) as qty_ia,
    //                         inv_bal.qty_waste,
    //                         inv_bal.wgt_awal,
    //                         COALESCE(inv_trans_dtl.wgt_rcv, 0) as wgt_rcv,
    //                         COALESCE(inv_trans_dtl.wgt_iss, 0) as wgt_iss,
    //                         COALESCE(inv_trans_dtl.wgt_adj, 0) as wgt_ia,
    //                         inv_bal.wgt_waste
    //                     FROM
    //                         (
    //                             SELECT
    //                                 inv_trans_dtl.period, 
    //                                 inv_trans_dtl.office_id, 
    //                                 inv_trans_dtl.article_id, 
    //                                 inv_trans_dtl.defect, 
    //                                 inv_trans_dtl.batch_id, 
    //                                 inv_trans_dtl.length, 
    //                                 inv_trans_dtl.add_id, 
    //                                 inv_trans_dtl.tor_id, 
    //                                 inv_trans_dtl.wh_id, 
    //                                 inv_trans_dtl.bin_id, 
    //                                 inv_trans_dtl.spk_id, 
    //                                 inv_trans_dtl.spk_item, 
    //                                 sum(case when inv_trans_dtl.tr_type in ('MR', 'RC', 'RW', 'SR', 'TR', 'RP') then inv_trans_dtl.qty else 0 end) AS qty_rcv, 
    //                                 sum(case when inv_trans_dtl.tr_type in ('IS', 'IW', 'MI', 'TI', 'PR', 'SA') then inv_trans_dtl.qty else 0 end) AS qty_iss, 
    //                                 sum(case when inv_trans_dtl.tr_type in ('IA') then  inv_trans_dtl.qty else 0 end) AS qty_adj, 
    //                                 sum(case when inv_trans_dtl.tr_type in ('MR', 'RC', 'RW', 'SR', 'TR', 'RP') then inv_trans_dtl.wgt else 0 end) AS wgt_rcv, 
    //                                 sum(case when inv_trans_dtl.tr_type in ('IS', 'IW', 'MI', 'TI', 'PR', 'SA') then inv_trans_dtl.wgt else 0 end) AS wgt_iss, 
    //                                 sum(case when inv_trans_dtl.tr_type in ('IA') then  inv_trans_dtl.wgt else 0 end) AS wgt_adj
    //                             FROM
    //                                 inv_trans_dtl
    //                                 INNER JOIN inv_trans_hdr on (inv_trans_hdr.office_id = inv_trans_dtl.office_id and inv_trans_hdr.tr_type = inv_trans_dtl.tr_type and  inv_trans_hdr.tr_id = inv_trans_dtl.tr_id)
    //                             WHERE 
    //                                 inv_trans_dtl.period = '$period' format(inv_trans_hdr.tr_date, 'yyyyMM') = '$period' AND inv_trans_hdr.office_id = '$request->office_id'
    //                             GROUP BY
    //                                 inv_trans_dtl.period, 
    //                                 inv_trans_dtl.office_id, 
    //                                 inv_trans_dtl.article_id, 
    //                                 inv_trans_dtl.defect, 
    //                                 inv_trans_dtl.batch_id, 
    //                                 inv_trans_dtl.length, 
    //                                 inv_trans_dtl.add_id, 
    //                                 inv_trans_dtl.tor_id, 
    //                                 inv_trans_dtl.wh_id, 
    //                                 inv_trans_dtl.bin_id, 
    //                                 inv_trans_dtl.spk_id, 
    //                                 inv_trans_dtl.spk_item
    //                         ) AS inv_trans_dtl
    //                         RIGHT JOIN
    //                         inv_bal
    //                         ON 
    //                             inv_trans_dtl.office_id = inv_bal.office_id AND
    //                             inv_trans_dtl.article_id = inv_bal.article_id AND
    //                             inv_trans_dtl.wh_id = inv_bal.wh_id AND
    //                             inv_trans_dtl.bin_id = inv_bal.bin_id AND
    //                             inv_trans_dtl.batch_id = inv_bal.batch_id AND
    //                             inv_trans_dtl.defect = inv_bal.defect AND
    //                             inv_trans_dtl.length = inv_bal.length AND
    //                             inv_trans_dtl.add_id = inv_bal.add_id AND
    //                             inv_trans_dtl.tor_id = inv_bal.tor_id AND
    //                             inv_trans_dtl.spk_id = inv_bal.spk_id AND
    //                             inv_trans_dtl.spk_item = inv_bal.spk_item
    //                     WHERE
    //                         inv_bal.office_id = '$request->office_id'
    //                     "));

    //     foreach ($trans_details as $key => $val) {
    //         $value = (object) $val;

    //         $inv_bal_hist = DB::connection("sqlSrvFPI")
    //                     ->table('inv_bal_hist')
    //                     ->where('period', $last_period)
    //                     ->where('office_id', trim($value->office_id))
    //                     ->where('article_id', trim($value->article_id))
    //                     ->where('defect', $value->defect)
    //                     ->where('batch_id', trim($value->batch_id))
    //                     ->where('length', $value->length)
    //                     ->where('add_id', trim($value->add_id))
    //                     ->where('tor_id', trim($value->tor_id))
    //                     ->where('wh_id', trim($value->wh_id))
    //                     ->where('bin_id', trim($value->bin_id))
    //                     ->where('spk_id', trim($value->spk_id))
    //                     ->where('spk_item', $value->spk_item)
    //                     ->first();

    //         $qty_awal = $inv_bal_hist ? (float) $inv_bal_hist->qty_oh : 0;
    //         $wgt_awal = $inv_bal_hist ? (float) $inv_bal_hist->wgt_oh : 0;

    //         $this->saveInvBalHist($request, $qty_awal, $wgt_awal, $value, $period);
    //         $this->updateInvBal($request, $qty_awal, $wgt_awal, $value, $period);
    //     }

    //     $this->updatePeriod($request, $end_date, $yesterday_date);
    // }

    private function saveInvBalHist($request, $qty_awal, $wgt_awal, $value, $period)
    {
        $inv_bal_hist = DB::connection("sqlSrvFPI")
                        ->table('inv_bal_hist')
                        ->where('period', $period)
                        ->where('office_id', trim($value->office_id))
                        ->where('article_id', trim($value->article_id))
                        ->where('defect', $value->defect)
                        ->where('batch_id', trim($value->batch_id))
                        ->where('length', $value->length)
                        ->where('add_id', trim($value->add_id))
                        ->where('tor_id', trim($value->tor_id))
                        ->where('wh_id', trim($value->wh_id))
                        ->where('bin_id', trim($value->bin_id))
                        ->where('spk_id', trim($value->spk_id))
                        ->where('spk_item', $value->spk_item)
                        ->first();

        if ($inv_bal_hist) {
            DB::connection("sqlSrvFPI")
            ->table('inv_bal_hist')
            ->where('period', $period)
            ->where('office_id', trim($value->office_id))
            ->where('article_id', trim($value->article_id))
            ->where('defect', $value->defect)
            ->where('batch_id', trim($value->batch_id))
            ->where('length', $value->length)
            ->where('add_id', trim($value->add_id))
            ->where('tor_id', trim($value->tor_id))
            ->where('wh_id', trim($value->wh_id))
            ->where('bin_id', trim($value->bin_id))
            ->where('spk_id', trim($value->spk_id))
            ->where('spk_item', $value->spk_item)
            ->update([
                'qty_awal' => (float) str_replace(',', '.', $value->qty_awal),
                'qty_oh' => (float) str_replace(',', '.', $value->qty_awal) + (float) str_replace(',', '.', $value->qty_rcv) - (float) str_replace(',', '.', $value->qty_iss) + (float) str_replace(',', '.', $value->qty_ia),
                'qty_rcv' => (float) str_replace(',', '.', $value->qty_rcv),
                'qty_iss' => (float) str_replace(',', '.', $value->qty_iss),
                'qty_ia' => (float) str_replace(',', '.', $value->qty_ia),
                'qty_waste' => 0,
                'wgt_awal' => $wgt_awal,
                'wgt_oh' => (double) $wgt_awal + (double) $value->wgt_rcv - (double) $value->wgt_iss + (double) $value->wgt_ia,
                'wgt_rcv' => (double) $value->wgt_rcv,
                'wgt_iss' => (double) $value->wgt_iss,
                'wgt_ia' => (double)  $value->wgt_ia,
                'wgt_waste' => 0,
                'dt_modified' => now(),
                // 'user_id' => Auth::user()->user_id2
                'user_id' => getAuth($request)->user_id2,

            ]);

        } else {
            DB::connection("sqlSrvFPI")
                ->table('inv_bal_hist')
                ->insert([
                    'period' => $period,
                    'office_id' => trim($value->office_id),
                    'article_id' => trim($value->article_id),
                    'batch_id' => trim($value->batch_id),
                    'defect' => $value->defect,
                    'length' => $value->length,
                    'add_id' => trim($value->add_id),
                    'tor_id' => trim($value->tor_id),
                    'wh_id' => trim($value->wh_id),
                    'bin_id' => trim($value->bin_id),
                    'spk_id' => trim($value->spk_id),
                    'spk_item' => $value->spk_item,
                    'qty_awal' => (float) str_replace(',', '.', $value->qty_awal),
                    'qty_oh' => (float) str_replace(',', '.', $value->qty_awal) + (float) str_replace(',', '.', $value->qty_rcv) - (float) str_replace(',', '.', $value->qty_iss) + (float) str_replace(',', '.', $value->qty_ia),
                    'qty_rcv' => (float) str_replace(',', '.', $value->qty_rcv),
                    'qty_iss' => (float) str_replace(',', '.', $value->qty_iss),
                    'qty_ia' => (float) str_replace(',', '.', $value->qty_ia),
                    'qty_waste' => 0,
                    'wgt_awal' => $wgt_awal,
                    'wgt_oh' => (double) $wgt_awal + (double) $value->wgt_rcv - (double) $value->wgt_iss + (double) $value->wgt_ia,
                    'wgt_rcv' => (double) $value->wgt_rcv,
                    'wgt_iss' => (double) $value->wgt_iss,
                    'wgt_ia' => (double)  $value->wgt_ia,
                    'wgt_waste' => 0,
                    'dt_modified' => now(),
                    // 'user_id' => Auth::user()->user_id2
                    'user_id' => getAuth($request)->user_id2,
                ]);
        }
    }

    private function updateInvBal($request, $qty_awal, $wgt_awal, $value, $period)
    {
        $inv_bal = DB::connection("sqlSrvFPI")
                        ->table('inv_bal')
                        ->where('office_id', trim($value->office_id))
                        ->where('article_id', trim($value->article_id))
                        ->where('defect', $value->defect)
                        ->where('batch_id', trim($value->batch_id))
                        ->where('length', $value->length)
                        ->where('add_id', trim($value->add_id))
                        ->where('tor_id', trim($value->tor_id))
                        ->where('wh_id', trim($value->wh_id))
                        ->where('bin_id', trim($value->bin_id))
                        ->where('spk_id', trim($value->spk_id))
                        ->where('spk_item', $value->spk_item)
                        ->first();

        if ($inv_bal) {
            DB::connection("sqlSrvFPI")
            ->table('inv_bal')
            ->where('office_id', trim($value->office_id))
            ->where('article_id', trim($value->article_id))
            ->where('defect', $value->defect)
            ->where('batch_id', trim($value->batch_id))
            ->where('length', $value->length)
            ->where('add_id', trim($value->add_id))
            ->where('tor_id', trim($value->tor_id))
            ->where('wh_id', trim($value->wh_id))
            ->where('bin_id', trim($value->bin_id))
            ->where('spk_id', trim($value->spk_id))
            ->where('spk_item', $value->spk_item)
            ->update([
                'qty_awal' => (float) str_replace(',', '.', $value->qty_awal) + (float) str_replace(',', '.', $value->qty_rcv) - (float) str_replace(',', '.', $value->qty_iss) + (float) str_replace(',', '.', $value->qty_ia),
                'qty_oh' => (float) str_replace(',', '.', $value->qty_awal) + (float) str_replace(',', '.', $value->qty_rcv) - (float) str_replace(',', '.', $value->qty_iss) + (float) str_replace(',', '.', $value->qty_ia),
                'qty_rcv' => 0,
                'qty_iss' => 0,
                'qty_ia' => 0,
                'qty_waste' => 0,
                'wgt_awal' => (double) $wgt_awal + (double) $value->wgt_rcv - (double) $value->wgt_iss + (double) $value->wgt_ia,
                'wgt_oh' => (double) $wgt_awal + (double) $value->wgt_rcv - (double) $value->wgt_iss + (double) $value->wgt_ia,
                'wgt_rcv' => 0,
                'wgt_iss' => 0,
                'wgt_ia' => 0,
                'wgt_waste' => 0,
                'dt_modified' => now(),
                // 'user_id' => Auth::user()->user_id2
                'user_id' => getAuth($request)->user_id2,
            ]);

        } else {
            DB::connection("sqlSrvFPI")
                ->table('inv_bal')
                ->insert([
                    'office_id' => trim($value->office_id),
                    'article_id' => trim($value->article_id),
                    'defect' => $value->defect,
                    'length' => $value->length,
                    'add_id' => trim($value->add_id),
                    'tor_id' => trim($value->tor_id),
                    'wh_id' => trim($value->wh_id),
                    'bin_id' => trim($value->bin_id),
                    'spk_id' => trim($value->spk_id),
                    'spk_item' => $value->spk_item,
                    'qty_awal' => (float) str_replace(',', '.', $value->qty_awal) + (float) str_replace(',', '.', $value->qty_rcv) - (float) str_replace(',', '.', $value->qty_iss) + (float) str_replace(',', '.', $value->qty_ia),
                    'qty_oh' => (float) str_replace(',', '.', $value->qty_awal) + (float) str_replace(',', '.', $value->qty_rcv) - (float) str_replace(',', '.', $value->qty_iss) + (float) str_replace(',', '.', $value->qty_ia),
                    'qty_rcv' => 0,
                    'qty_iss' => 0,
                    'qty_ia' => 0,
                    'qty_waste' => 0,
                    'wgt_awal' => (double) $wgt_awal + (double) $value->wgt_rcv - (double) $value->wgt_iss + (double) $value->wgt_ia,
                    'wgt_oh' => (double) $wgt_awal + (double) $value->wgt_rcv - (double) $value->wgt_iss + (double) $value->wgt_ia,
                    'wgt_rcv' => 0,
                    'wgt_iss' => 0,
                    'wgt_ia' => 0,
                    'wgt_waste' => 0,
                    'dt_modified' => now(),
                    // 'user_id' => Auth::user()->user_id2
                    'user_id' => getAuth($request)->user_id2,
                ]);
        }
    }

    private function updateInvBalDaily($request, $period)
    {
        $trans_details = DB::connection("sqlSrvFPI")
                        ->select(DB::raw("
                        SELECT
                            inv_bal.office_id,
                            inv_bal.article_id,
                            inv_bal.defect,
                            inv_bal.batch_id,
                            inv_bal.length,
                            inv_bal.add_id,
                            inv_bal.tor_id,
                            inv_bal.wh_id,
                            inv_bal.bin_id,
                            inv_bal.spk_id,
                            inv_bal.spk_item,
                            inv_bal.qty_awal,
                            COALESCE(inv_trans_dtl.qty_rcv, 0) as qty_rcv,
                            COALESCE(inv_trans_dtl.qty_iss, 0) as qty_iss,
                            COALESCE(inv_trans_dtl.qty_adj, 0) as qty_ia,
                            inv_bal.qty_waste,
                            inv_bal.wgt_awal,
                            COALESCE(inv_trans_dtl.wgt_rcv, 0) as wgt_rcv,
                            COALESCE(inv_trans_dtl.wgt_iss, 0) as wgt_iss,
                            COALESCE(inv_trans_dtl.wgt_adj, 0) as wgt_ia,
                            inv_bal.wgt_waste
                        FROM
                            (
                                SELECT
                                    inv_trans_dtl.period, 
                                    inv_trans_dtl.office_id, 
                                    inv_trans_dtl.article_id, 
                                    inv_trans_dtl.defect, 
                                    inv_trans_dtl.batch_id, 
                                    inv_trans_dtl.length, 
                                    inv_trans_dtl.add_id, 
                                    inv_trans_dtl.tor_id, 
                                    inv_trans_dtl.wh_id, 
                                    inv_trans_dtl.bin_id, 
                                    inv_trans_dtl.spk_id, 
                                    inv_trans_dtl.spk_item, 
                                    sum(case when inv_trans_dtl.tr_type in ('MR', 'RC', 'RW', 'SR', 'TR', 'RP') then inv_trans_dtl.qty else 0 end) AS qty_rcv, 
                                    sum(case when inv_trans_dtl.tr_type in ('IS', 'IW', 'MI', 'TI', 'PR', 'SA') then inv_trans_dtl.qty else 0 end) AS qty_iss, 
                                    sum(case when inv_trans_dtl.tr_type in ('IA') then  inv_trans_dtl.qty else 0 end) AS qty_adj, 
                                    sum(case when inv_trans_dtl.tr_type in ('MR', 'RC', 'RW', 'SR', 'TR', 'RP') then inv_trans_dtl.wgt else 0 end) AS wgt_rcv, 
                                    sum(case when inv_trans_dtl.tr_type in ('IS', 'IW', 'MI', 'TI', 'PR', 'SA') then inv_trans_dtl.wgt else 0 end) AS wgt_iss, 
                                    sum(case when inv_trans_dtl.tr_type in ('IA') then  inv_trans_dtl.wgt else 0 end) AS wgt_adj
                                FROM
                                    inv_trans_dtl
                                    INNER JOIN inv_trans_hdr on (inv_trans_hdr.office_id = inv_trans_dtl.office_id and inv_trans_hdr.tr_type = inv_trans_dtl.tr_type and  inv_trans_hdr.tr_id = inv_trans_dtl.tr_id)
                                WHERE 
                                    format(inv_trans_hdr.tr_date, 'yyyyMM') = '$period' AND inv_trans_hdr.office_id = '$request->office_id'
                                GROUP BY
                                    inv_trans_dtl.period, 
                                    inv_trans_dtl.office_id, 
                                    inv_trans_dtl.article_id, 
                                    inv_trans_dtl.defect, 
                                    inv_trans_dtl.batch_id, 
                                    inv_trans_dtl.length, 
                                    inv_trans_dtl.add_id, 
                                    inv_trans_dtl.tor_id, 
                                    inv_trans_dtl.wh_id, 
                                    inv_trans_dtl.bin_id, 
                                    inv_trans_dtl.spk_id, 
                                    inv_trans_dtl.spk_item
                            ) AS inv_trans_dtl
                            RIGHT JOIN
                            inv_bal
                            ON 
                                inv_trans_dtl.office_id = inv_bal.office_id AND
                                inv_trans_dtl.article_id = inv_bal.article_id AND
                                inv_trans_dtl.wh_id = inv_bal.wh_id AND
                                inv_trans_dtl.bin_id = inv_bal.bin_id AND
                                inv_trans_dtl.batch_id = inv_bal.batch_id AND
                                inv_trans_dtl.defect = inv_bal.defect AND
                                inv_trans_dtl.length = inv_bal.length AND
                                inv_trans_dtl.add_id = inv_bal.add_id AND
                                inv_trans_dtl.tor_id = inv_bal.tor_id AND
                                inv_trans_dtl.spk_id = inv_bal.spk_id AND
                                inv_trans_dtl.spk_item = inv_bal.spk_item
                        "));

        foreach ($trans_details as $key => $val) {
            $value = (object) $val;

            $inv_bal = DB::connection("sqlSrvFPI")
                        ->table('inv_bal')
                        ->where('office_id', trim($value->office_id))
                        ->where('article_id', trim($value->article_id))
                        ->where('defect', $value->defect)
                        ->where('batch_id', trim($value->batch_id))
                        ->where('length', $value->length)
                        ->where('add_id', trim($value->add_id))
                        ->where('tor_id', trim($value->tor_id))
                        ->where('wh_id', trim($value->wh_id))
                        ->where('bin_id', trim($value->bin_id))
                        ->where('spk_id', trim($value->spk_id))
                        ->where('spk_item', $value->spk_item)
                        ->first();

            if ($inv_bal) {
                DB::connection("sqlSrvFPI")
                ->table('inv_bal')
                ->where('office_id', trim($value->office_id))
                ->where('article_id', trim($value->article_id))
                ->where('defect', $value->defect)
                ->where('batch_id', trim($value->batch_id))
                ->where('length', $value->length)
                ->where('add_id', trim($value->add_id))
                ->where('tor_id', trim($value->tor_id))
                ->where('wh_id', trim($value->wh_id))
                ->where('bin_id', trim($value->bin_id))
                ->where('spk_id', trim($value->spk_id))
                ->where('spk_item', $value->spk_item)
                ->update([
                    'qty_oh' => (float) $inv_bal->qty_awal + (float) str_replace(',', '.', $value->qty_rcv) - (float) str_replace(',', '.', $value->qty_iss) + (float) str_replace(',', '.', $value->qty_ia),
                    'qty_rcv' => (float) str_replace(',', '.', $value->qty_rcv),
                    'qty_iss' => (float) str_replace(',', '.', $value->qty_iss),
                    'qty_ia' => (float) str_replace(',', '.', $value->qty_ia),
                    'qty_waste' => 0,
                    'wgt_oh' => (double) $inv_bal->wgt_awal + (double) $value->wgt_rcv - (double) $value->wgt_iss + (double) $value->wgt_ia,
                    'wgt_rcv' => (double) $value->wgt_rcv,
                    'wgt_iss' => (double) $value->wgt_iss,
                    'wgt_ia' => (double) $value->wgt_ia,
                    'wgt_waste' => 0,
                    'dt_modified' => now(),
                    // 'user_id' => Auth::user()->user_id2
                    'user_id' => getAuth($request)->user_id2,
                ]);

            } else {
                DB::connection("sqlSrvFPI")
                ->table('inv_bal')
                ->insert([
                    'office_id' => trim($value->office_id),
                    'article_id' => trim($value->article_id),
                    'defect' => $value->defect,
                    'length' => $value->length,
                    'add_id' => trim($value->add_id),
                    'tor_id' => trim($value->tor_id),
                    'wh_id' => trim($value->wh_id),
                    'bin_id' => trim($value->bin_id),
                    'spk_id' => trim($value->spk_id),
                    'spk_item' => $value->spk_item,
                    'qty_awal' => 0,
                    'qty_oh' => (float) $inv_bal->qty_awal + (float) str_replace(',', '.', $value->qty_rcv) - (float) str_replace(',', '.', $value->qty_iss) + (float) str_replace(',', '.', $value->qty_ia),
                    'qty_rcv' => (float) str_replace(',', '.', $value->qty_rcv),
                    'qty_iss' => (float) str_replace(',', '.', $value->qty_iss),
                    'qty_ia' => (float) str_replace(',', '.', $value->qty_ia),
                    'qty_waste' => 0,
                    'wgt_awal' => 0,
                    'wgt_oh' => (double) $inv_bal->wgt_awal + (double) $value->wgt_rcv - (double) $value->wgt_iss + (double) $value->wgt_ia,
                    'wgt_rcv' => (double) $value->wgt_rcv,
                    'wgt_iss' => (double) $value->wgt_iss,
                    'wgt_ia' => (double) $value->wgt_ia,
                    'wgt_waste' => 0,
                    'dt_modified' => now(),
                    // 'user_id' => Auth::user()->user_id2,
                    'user_id' => getAuth($request)->user_id2,
                ]);
            }
        }

        
    }

    // private function updatePeriod($request, $end_date, $yesterday_date)
    // {
    //     $period = Carbon::parse($end_date)->format('Ym');
    //     $prev_year = Carbon::parse($end_date)->subYear(1)->format('Y');

    //     $curr_period = Carbon::parse($yesterday_date)->addMonthNoOverflow(1)->format('Ym');
    //     $curr_year = Carbon::parse($yesterday_date)->addMonthNoOverflow(1)->format('Y');

    //     $next_period = Carbon::parse($curr_period)->addMonthNoOverflow(1)->format('Ym');
    //     $next_year = Carbon::parse($curr_period)->addYear(1)->format('Y');

    //     $end_date = Carbon::parse($curr_period)->endOfMonth()->format('Y-m-d');
    //     $begin_date = Carbon::parse($curr_period)->startOfMonth()->format('Y-m-d');


    //     $close_period = DB::connection("sqlSrvFPI")
    //                     ->table('period')
    //                     ->where('dept_id', 'PRODWH')
    //                     ->where('office_id', $request->office_id)
    //                     ->first();

    //     if ($close_period) {
    //         DB::connection("sqlSrvFPI")
    //         ->table('period')
    //         ->where('dept_id', 'PRODWH')
    //         ->where('office_id', $request->office_id)
    //         ->update([
    //             'prev_period' => $period,
    //             'curr_period' => $curr_period,
    //             'next_period' => $next_period,
    //             'curr_year' => $curr_year,
    //             'prev_year' => $prev_year,
    //             'next_year' => $next_year,
    //             'begin_date' => $begin_date,
    //             'end_date' => $end_date
    //         ]);
    //     }
    // }

    private function updatePeriod($request, $tr_date, $period)
    {
        $prev_year = Carbon::parse($tr_date)->subYear(1)->format('Y');

        $curr_period = Carbon::parse($tr_date)->addMonthNoOverflow(1)->format('Ym');
        $curr_year = Carbon::parse($tr_date)->addMonthNoOverflow(1)->format('Y');

        $next_period = Carbon::parse($tr_date)->addMonthNoOverflow(2)->format('Ym');
        $next_year = Carbon::parse($tr_date)->addYear(1)->format('Y');

        $tr_date = Carbon::parse($tr_date)->endOfMonth()->format('Y-m-d');
        $begin_date = Carbon::parse($next_period)->startOfMonth()->format('Y-m-d');
        $end_date = Carbon::parse($next_period)->endOfMonth()->format('Y-m-d');


        $close_period = DB::connection("sqlSrvFPI")
                        ->table('period')
                        ->where('dept_id', 'PRODWH')
                        ->where('office_id', $request->office_id)
                        ->first();

        if ($close_period) {
            DB::connection("sqlSrvFPI")
            ->table('period')
            ->where('dept_id', 'PRODWH')
            ->where('office_id', $request->office_id)
            ->update([
                'prev_period' => $period,
                'curr_period' => $curr_period,
                'next_period' => $next_period,
                'curr_year' => $curr_year,
                'prev_year' => $prev_year,
                'next_year' => $next_year,
                'begin_date' => $begin_date,
                'end_date' => $end_date
            ]);
        }
    }

    private function closingMonthlyFast($request, $tr_date)
    {
        $office_id = $request->office_id;
        $period = Carbon::parse($tr_date)->format('Ym');
        $last_period = Carbon::parse($tr_date)->subMonthNoOverflow(1)->format('Ym');
        $user_id = getAuth($request)->user_id2;
        DB::connection('sqlSrvFPI')->unprepared("
            /* ==========================================
            1. DROP TEMP TABLE JIKA ADA
            ========================================== */
            IF OBJECT_ID('tempdb..#tmp_inv_closing') IS NOT NULL
                DROP TABLE #tmp_inv_closing;

            /* ==========================================
            2. AMBIL TRANSAKSI BULAN BERJALAN
            ========================================== */
            SELECT
                b.office_id,
                b.article_id,
                b.defect,
                b.batch_id,
                b.length,
                b.add_id,
                b.tor_id,
                b.wh_id,
                b.bin_id,
                b.spk_id,
                b.spk_item,

                SUM(CASE WHEN d.tr_type IN ('RW','RP','RC','SR','MR','TR','KR') THEN d.qty ELSE 0 END) AS qty_rcv,
                SUM(CASE WHEN d.tr_type IN ('IW','IS','PR','IP','MI','TI','KI','SA') THEN d.qty ELSE 0 END) AS qty_iss,
                SUM(CASE WHEN d.tr_type = 'IA' THEN d.qty ELSE 0 END) AS qty_ia,

                SUM(CASE WHEN d.tr_type IN ('RW','RP','RC','SR','MR','TR','KR') THEN d.wgt ELSE 0 END) AS wgt_rcv,
                SUM(CASE WHEN d.tr_type IN ('IW','IS','PR','IP','MI','TI','KI','SA') THEN d.wgt ELSE 0 END) AS wgt_iss,
                SUM(CASE WHEN d.tr_type = 'IA' THEN d.wgt ELSE 0 END) AS wgt_ia
            INTO #tmp_inv_closing
            FROM inv_bal b
            LEFT JOIN inv_trans_dtl d
                ON b.office_id = d.office_id
                AND b.article_id = d.article_id
                AND b.defect = d.defect
                AND b.batch_id = d.batch_id
                AND b.length = d.length
                AND b.add_id = d.add_id
                AND b.tor_id = d.tor_id
                AND b.wh_id = d.wh_id
                AND b.bin_id = d.bin_id
                AND b.spk_id = d.spk_id
                AND b.spk_item = d.spk_item
                AND d.period = '$period'
            WHERE b.office_id = '$office_id'
            GROUP BY
                b.office_id, b.article_id, b.defect, b.batch_id, b.length,
                b.add_id, b.tor_id, b.wh_id, b.bin_id, b.spk_id, b.spk_item;

            /* ==========================================
            3. INSERT / UPDATE inv_bal_hist
            ========================================== */
            MERGE inv_bal_hist AS tgt
            USING (
                SELECT
                    t.*,
                    ISNULL(h.qty_oh,0) AS qty_awal,
                    ISNULL(h.wgt_oh,0) AS wgt_awal
                FROM #tmp_inv_closing t
                LEFT JOIN inv_bal_hist h
                    ON h.period = '$last_period'
                    AND h.office_id = t.office_id
                    AND h.article_id = t.article_id
                    AND h.defect = t.defect
                    AND h.batch_id = t.batch_id
                    AND h.length = t.length
                    AND h.add_id = t.add_id
                    AND h.tor_id = t.tor_id
                    AND h.wh_id = t.wh_id
                    AND h.bin_id = t.bin_id
                    AND h.spk_id = t.spk_id
                    AND h.spk_item = t.spk_item
            ) src
            ON (
                tgt.period = '$period'
                AND tgt.office_id = src.office_id
                AND tgt.article_id = src.article_id
                AND tgt.defect = src.defect
                AND tgt.batch_id = src.batch_id
                AND tgt.length = src.length
                AND tgt.add_id = src.add_id
                AND tgt.tor_id = src.tor_id
                AND tgt.wh_id = src.wh_id
                AND tgt.bin_id = src.bin_id
                AND tgt.spk_id = src.spk_id
                AND tgt.spk_item = src.spk_item
            )
            WHEN MATCHED THEN
                UPDATE SET
                    qty_awal = src.qty_awal,
                    qty_oh = src.qty_awal + src.qty_rcv - src.qty_iss + src.qty_ia,
                    qty_rcv = src.qty_rcv,
                    qty_iss = src.qty_iss,
                    qty_ia  = src.qty_ia,
                    qty_waste = 0,
                    wgt_awal = src.wgt_awal,
                    wgt_oh = src.wgt_awal + src.wgt_rcv - src.wgt_iss + src.wgt_ia,
                    wgt_rcv = src.wgt_rcv,
                    wgt_iss = src.wgt_iss,
                    wgt_ia  = src.wgt_ia,
                    wgt_waste = 0,
                    dt_modified = GETDATE(),
                    user_id = '$user_id'
            WHEN NOT MATCHED THEN
                INSERT (
                    period, office_id, article_id, defect, batch_id, length,
                    add_id, tor_id, wh_id, bin_id, spk_id, spk_item,
                    qty_awal, qty_oh, qty_rcv, qty_iss, qty_ia, qty_waste,
                    wgt_awal, wgt_oh, wgt_rcv, wgt_iss, wgt_ia, wgt_waste,
                    dt_modified, user_id
                )
                VALUES (
                    '$period', src.office_id, src.article_id, src.defect, src.batch_id, src.length,
                    src.add_id, src.tor_id, src.wh_id, src.bin_id, src.spk_id, src.spk_item,
                    src.qty_awal,
                    src.qty_awal + src.qty_rcv - src.qty_iss + src.qty_ia,
                    src.qty_rcv, src.qty_iss, src.qty_ia, 0,
                    src.wgt_awal,
                    src.wgt_awal + src.wgt_rcv - src.wgt_iss + src.wgt_ia,
                    src.wgt_rcv, src.wgt_iss, src.wgt_ia, 0,
                    GETDATE(), '$user_id'
                );

            /* ==========================================
            4B. UPDATE inv_bal (ROLL OVER)
            ========================================== */
            UPDATE b
            SET
                b.qty_awal = h.qty_oh,
                b.qty_oh = h.qty_oh,
                b.qty_rcv = 0,
                b.qty_iss = 0,
                b.qty_ia  = 0,
                b.qty_waste = 0,
                b.wgt_awal = h.wgt_oh,
                b.wgt_oh = h.wgt_oh,
                b.wgt_rcv = 0,
                b.wgt_iss = 0,
                b.wgt_ia  = 0,
                b.wgt_waste = 0,
                b.dt_modified = GETDATE(),
                b.user_id = '$user_id'
            FROM inv_bal b
            JOIN inv_bal_hist h
                ON h.period = '$period'
                AND h.office_id = b.office_id
                AND h.article_id = b.article_id
                AND h.defect = b.defect
                AND h.batch_id = b.batch_id
                AND h.length = b.length
                AND h.add_id = b.add_id
                AND h.tor_id = b.tor_id
                AND h.wh_id = b.wh_id
                AND h.bin_id = b.bin_id
                AND h.spk_id = b.spk_id
                AND h.spk_item = b.spk_item;

            DROP TABLE #tmp_inv_closing;
        ");
    }
}
