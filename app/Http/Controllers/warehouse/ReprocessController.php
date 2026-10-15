<?php

namespace App\Http\Controllers\warehouse;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\WarehouseTrait;

class ReprocessController extends Controller
{
    use WarehouseTrait;

    public function getRegion(Request $request)
    {
        $where = "1=1";
        $region_id = $request->region_id;
        $active_flag = $request->active_flag;
        
        if (!empty($region_id))
        {
            $where = $where . "and region_id = ". "'" . $region_id . "'";
        }
        if (!empty($active_flag))
        {
            $where = $where . "and active_flag = ". "'" . $active_flag . "'";
        }else{
            $where = $where . "and active_flag = ". "'Y'";
        }

        $result = DB::connection('sqlSrvBCKI')
                ->table('region')
                ->selectRaw('region_id, TRIM(descr) as descr')
                ->whereRaw($where)
                ->orderBy('descr', 'asc')
                ->get();

        return $this->sendResponse($result, 'Successfully get Region');
    }

    public function getSubregion(Request $request)
    {
        $where = "1=1";
        $region_id = $request->region_id;
        $subregion_id = $request->subregion_id;
        $active_flag = $request->active_flag;
        
        if (!empty($region_id))
        {
            $where = $where . "and region = ". "'" . $region_id . "'";
        }
        if (!empty($subregion_id))
        {
            $where = $where . "and subregion_id = ". "'" . $subregion_id . "'";
        }
        if (!empty($active_flag))
        {
            $where = $where . "and active_flag = ". "'" . $active_flag . "'";
        }else{
            $where = $where . "and active_flag = ". "'Y'";
        }

        $result = DB::connection('sqlSrvBCKI')
                ->table('subregion')
                ->selectRaw('subregion_id, TRIM(descr) as descr, TRIM(region) as region')
                ->whereRaw($where)
                ->orderBy('descr', 'asc')
                ->get();

        return $this->sendResponse($result, 'Successfully get SubRegion');
    }

    public function getOffice(Request $request)
    {
        $where = "1=1";
        $region_id = $request->region_id;
        $subregion_id = $request->subregion_id;
        $office_id = $request->office_id;
        $active_flag = $request->active_flag;
        
        if (!empty($region_id))
        {
            $where = $where . "and region = ". "'" . $region_id . "'";
        }
        if (!empty($subregion_id))
        {
            $where = $where . "and subregion = ". "'" . $subregion_id . "'";
        }
        if (!empty($office_id))
        {
            $where = $where . "and office_id = ". "'" . $office_id . "'";
        }
        if (!empty($active_flag))
        {
            $where = $where . "and active_flag = ". "'" . $active_flag . "'";
        }else{
            $where = $where . "and active_flag = ". "'Y'";
        }

        $result = DB::connection('sqlSrvBCKI')
                ->table('branch_office')
                ->selectRaw('office_id, TRIM(office) as office, TRIM(address) as address, TRIM(city) as city, TRIM(region) as region')
                ->whereRaw($where)
                ->orderBy('office', 'asc')
                ->get();

        return $this->sendResponse($result, 'Successfully get Office');
    }

    public function index(Request $request) 
    {
        $sqlWhere = "1=1";
        $office_id = $request->office_id;
        $wh_id = $request->wh_id;
        $tr_type = $request->tr_type;

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

        if ($tr_type) {
    
            $sqlWhere.= " and inv_trans_hdr.tr_type = '$tr_type'";
        }

        if ($office_id) {
    
            $sqlWhere.= " and inv_trans_hdr.office_id = '$office_id'";
        }

        if ($wh_id) {
    
            $sqlWhere.= " and inv_trans_hdr.wh_id = '$wh_id'";
        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and inv_trans_hdr.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and inv_trans_hdr.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
            }
        }

        $datas = DB::connection("sqlSrvBCKI")
                ->table('inv_trans_dtl')
                ->selectRaw("
                inv_trans_dtl.office_id, 
                inv_trans_dtl.tr_type, 
                inv_trans_dtl.tr_id, 
                inv_trans_dtl.seq_id, 
                TRIM(inv_trans_dtl.article_id) as article_id, 
                TRIM(inv_trans_dtl.wh_id) as wh_id, 
                TRIM(inv_trans_dtl.bin_id) as bin_id, 
                TRIM(inv_trans_dtl.batch_id) as batch_id, 
                inv_trans_dtl.defect,
                inv_trans_dtl.wgt_unit,
                inv_trans_dtl.uom,
                CAST(inv_trans_dtl.length as numeric(10,2)) as length,  
                TRIM(inv_trans_dtl.add_id) as add_id, 
                TRIM(inv_trans_dtl.tor_id) as tor_id, 
                TRIM(inv_trans_dtl.spk_id) as spk_id, 
                inv_trans_dtl.spk_item,                
                CAST(inv_trans_dtl.qty as numeric(10,2)) as qty,  
                CAST(inv_trans_dtl.wgt as numeric(10,2)) as wgt, 
                inv_trans_dtl.remark,
                inv_trans_dtl.period,
                inv_trans_dtl.tr_id_ref,
                inv_trans_dtl.tr_id_seq_ref,
                article.description, 
                FORMAT(inv_trans_dtl.dt_modified,'yyyy-MM-dd hh:mm:ss') as dt_modified,
                whouse.descr as whouse_name,
                branch_office.office,
                inv_trans_hdr.tr_date
                ")
                ->join('inv_trans_hdr', function($join){
                    $join->on('inv_trans_dtl.office_id', '=', 'inv_trans_hdr.office_id')
                    ->on('inv_trans_dtl.tr_id', '=', 'inv_trans_hdr.tr_id');
                })
                ->join('article', 'inv_trans_dtl.article_id', '=', 'article.article_id')
                ->join('branch_office', function($join){
                    $join->on('inv_trans_dtl.office_id', '=', 'branch_office.office_id');
                })
                ->join('whouse', function($join){
                    $join->on('inv_trans_dtl.office_id', '=', 'whouse.office_id')
                    ->on('inv_trans_dtl.wh_id', '=', 'whouse.wh_id');
                })
                ->whereRaw($sqlWhere)
                ->orderBy('inv_trans_hdr.tr_date', 'ASC')
                ->get();

        return $this->sendResponse($datas, 'Successfully get Warehouse');
    }

    public function show(Request $request)
    {
        $datas = DB::connection("sqlSrvBCKI")
                ->table('inv_trans_dtl')
                ->selectRaw("
                inv_trans_dtl.office_id, 
                inv_trans_dtl.tr_type, 
                inv_trans_dtl.tr_id, 
                inv_trans_dtl.seq_id, 
                TRIM(inv_trans_dtl.article_id) as article_id, 
                TRIM(inv_trans_dtl.wh_id) as wh_id, 
                TRIM(inv_trans_dtl.bin_id) as bin_id, 
                TRIM(inv_trans_dtl.batch_id) as batch_id, 
                inv_trans_dtl.defect,
                inv_trans_dtl.wgt_unit,
                inv_trans_dtl.uom,
                CAST(inv_trans_dtl.length as numeric(10,2)) as length,  
                TRIM(inv_trans_dtl.add_id) as add_id, 
                TRIM(inv_trans_dtl.tor_id) as tor_id, 
                TRIM(inv_trans_dtl.spk_id) as spk_id, 
                inv_trans_dtl.spk_item,                
                CAST(inv_trans_dtl.qty as numeric(10,2)) as qty,  
                CAST(inv_trans_dtl.wgt as numeric(10,2)) as wgt, 
                inv_trans_dtl.remark,
                inv_trans_dtl.period,
                inv_trans_dtl.tr_id_ref,
                inv_trans_dtl.tr_id_seq_ref,
                article.description, 
                FORMAT(inv_trans_dtl.dt_modified,'yyyy-MM-dd hh:mm:ss') as dt_modified,
                whouse.descr as whouse_name
                ")
                ->join('article', 'inv_trans_dtl.article_id', '=', 'article.article_id')
                ->join('whouse', function($join){
                    $join->on('inv_trans_dtl.office_id', '=', 'whouse.office_id')
                    ->on('inv_trans_dtl.wh_id', '=', 'whouse.wh_id');
                })
                ->where('inv_trans_dtl.office_id', $request->office_id)
                ->where('inv_trans_dtl.tr_id', $request->tr_id)
                ->get();

        return $this->sendResponse($datas, 'Successfully get WH Bal Warehouse');
    }

    public function store(Request $request){
        try {
            
            $current =  DB::connection("sqlSrvBCKI")
                            ->table('period')
                            ->where('dept_id', 'PRODWH')
                            ->where('office_id', $request->office_id)
                            ->first();

            if ($current) {
                $period = $request->period;
                if ($current->curr_period == $period) {
                    $transId = $request->tr_id;
                    if ($transId) {
                        $transId = $request->tr_id;
                    } else {
                        $transId = $this->genTrIdTrans($request->office_id, 'MP');
                        $header = DB::connection('sqlSrvBCKI')
                                ->table('inv_trans_hdr')
                                ->insert([
                                    'office_id' => $request->office_id,
                                    'tr_type' => 'MP',
                                    'tr_id' => $transId,
                                    'tr_date' => $request->tr_date,
                                    'wh_id' => ' ',
                                    'bin_id' => ' ',
                                    'office_id_dest' => ' ',
                                    'wh_dest' => ' ',
                                    'bin_dest' => ' ',
                                    'remark' => $request->remark ? $request->remark : '',
                                    'tr_id_ref' => ' ',
                                    'dt_modified' => now(),
                                    'user_id' => getAuth($request)->user_id2,
                                    // 'payment' => $arrayParams->payment
                                ]);
                    }
                    $arrayParams = (object)[
                        'tr_id' => $transId,
                        'period' => $request->period,
                        'article_id' => $request->article_id,
                        'office_id' => $request->office_id,
                        'tr_type' => $request->tr_type,
                        'tr_date' => $request->tr_date,
                        'tr_id_ref' => $request->tr_id_ref ? $request->tr_id_ref : ' ',
                        'tr_id_seq_ref' => $request->tr_id_seq_ref ? $request->tr_id_seq_ref : 0,
                        'wh_id' => $request->wh_id,
                        'bin_id' => $request->bin_id,
                        'prod_code' => $request->article_id,
                        'add_id' => $request->add_id ? $request->add_id : ' ',
                        'tor_id' => $request->tor_id ? $request->tor_id : ' ',
                        'batch_id' => $request->batch_id ? $request->batch_id : ' ',
                        'length' => (float) $request->length, 
                        'satuan' => $request->satuan,
                        'berat' => $request->berat,
                        'qty_oh' => (float) $request->qty_oh,
                        'defect' => $request->defect ? $request->defect : 0,
                        'spk_id' => $request->spk_id ? $request->spk_id : ' ',
                        'spk_item' => $request->spk_item ? $request->spk_item : 0,
                        'BarangJadiId' => $request->article_id,
                        'remark' => $request->remark_item ? $request->remark_item : '',
                        'amt_item' => 0,
                        'unit_price' => 0,
                        'payment' => 0
                    ];

                    $check = $this->storeTransWH($arrayParams, $request);

                    if($check->status == TRUE){
                        return $this->sendResponse($transId, 'Successfully saved');
                    } else {
                        return $this->sendError($check->message, 'Error', 500);
                    }
                } else {
                    return $this->sendError('Belum di closing', 'Error', 500);
                }
            } else {
                return $this->sendError('Belum di closing', 'Error', 500);
            }

            
        }   catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }

    }

    private function genTrIdTrans($office_id, $tr_type)
    {
        $dateYear = substr( date("Y"), -2);
        $dateMonth = date("m");
        $dateDay = date("d");
        $gen_tr_id = '';

        $getLastTrID =  DB::connection('sqlSrvBCKI')
                        ->table('inv_trans_hdr')
                        ->where('office_id', '=', trim($office_id))
                        ->where('tr_id', 'like', "%".$tr_type.$dateYear.$dateMonth.$dateDay."%")
                        ->where('tr_type', $tr_type)
                        ->max('tr_id');

        if($getLastTrID){
            $seq = substr( trim($getLastTrID), -3) + 1;
            $gen_tr_id = $tr_type.$dateYear.$dateMonth.$dateDay.str_pad($seq, 3, '0', STR_PAD_LEFT);
        } else {
            $gen_tr_id = $tr_type.$dateYear.$dateMonth.$dateDay.'001';
        }

        return $gen_tr_id;

    }

    public function show2(Request $request){
        $inv_bal = DB::connection("sqlSrvBCKI")
                    ->table('inv_bal')
                    ->selectRaw("
                    inv_bal.office_id, 
                    TRIM(inv_bal.article_id) as article_id, 
                    inv_bal.defect, 
                    TRIM(inv_bal.batch_id) as batch_id, 
                    CAST(inv_bal.length as numeric(10,2)) as length,  
                    TRIM(inv_bal.add_id) as add_id, 
                    TRIM(inv_bal.tor_id) as tor_id, 
                    TRIM(inv_bal.wh_id) as wh_id, 
                    TRIM(inv_bal.bin_id) as bin_id, 
                    TRIM(inv_bal.spk_id) as spk_id, 
                    inv_bal.spk_item,  
                    CAST(inv_bal.qty_oh as numeric(10,2)) as qty_oh,  
                    CAST(inv_bal.wgt_oh as numeric(10,2)) as wgt_oh, 
                    CAST(inv_bal.avg_price as numeric(10,2)) as avg_price, 
                    CAST(inv_bal.last_price as numeric(10,2)) as last_price, 
                    CAST(inv_bal.amt_balance as numeric(10,2)) as amt_balance, 
                    article.description, 
                    article.uom, 
                    article.ppn, 
                    article.berat, 
                    category.consume_able as consumeable,
                    category.cat_id,
                    '' as order_id,
                    0 as item_num,
                    FORMAT(inv_bal.dt_modified,'yyyy-MM-dd hh:mm:ss') as dt_modified,
                    whouse.descr as whouse_name,
                    article.uom as Satuan,
                    matrl_price_unit.hpp as price_unit,
                    matrl_price_unit.po_price as po_price
                    ")
                    ->join('article', 'inv_bal.article_id', '=', 'article.article_id')
                    ->join('category', 'category.cat_id', '=', 'article.cat_id')
                    ->join('whouse', function($join){
                        $join->on('inv_bal.office_id', '=', 'whouse.office_id')
                        ->on('inv_bal.wh_id', '=', 'whouse.wh_id');
                    })
                    ->join('branch_office', function($join){
                        $join->on('inv_bal.office_id', '=', 'branch_office.office_id');
                    })
                    ->leftJoin('matrl_price_unit', function($join){
                        $join->on('branch_office.region', '=', 'matrl_price_unit.region')
                        ->on('inv_bal.article_id', '=', 'matrl_price_unit.barangjadiid');
                    })  
                    ->where('inv_bal.office_id', $request->office_id)  
                    ->where('inv_bal.article_id', $request->article_id)     
                    ->where('inv_bal.length', $request->length)     
                    ->where('inv_bal.batch_id', $request->batch_id ? $request->batch_id : ' ')     
                    ->where('inv_bal.add_id', $request->add_id ? $request->add_id : ' ')     
                    ->where('inv_bal.wh_id', $request->wh_id)     
                    ->where('inv_bal.bin_id', $request->bin_id)     
                    ->first();   

        return $this->sendResponse($inv_bal, 'Successfully get Inv Bal Warehouse');

    }

}
