<?php

namespace App\Http\Controllers\warehouse;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\WarehouseTrait;

class WarehouseController extends Controller
{
    use WarehouseTrait;

    public function index(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $office_id = $request->office_id;
            $wh_id = $request->wh_id;
            $bin_id = $request->bin_id;
            $period = $request->period;
            $search = $request->search;
            $panjang = $request->panjang;
            $prod_code = $request->prod_code;
            $batch_id = $request->batch_id;
            $add_id = $request->add_id;
            $tor_id = $request->tor_id;
            $brand_id = $request->brand_id;
            $type = $request->type;

            if (!empty($office_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.office_id = ". "'" . $office_id . "'";
            }

            if (!empty($wh_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.wh_id = ". "'" . $wh_id . "'";
            }

            if (!empty($bin_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.bin_id = ". "'" . $bin_id . "'";
            }

            if (!empty($search))
            {
                $sqlWhere = $sqlWhere . "and article.description LIKE '%$search%'";
            }

            if (!empty($brand_id))
            {
                $sqlWhere = $sqlWhere . "and article.brand = ". "'" . $brand_id . "'";
            }

            if (!empty($panjang))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.length = ". "'" . $panjang . "'";
            }

            if (!empty($prod_code))
            {
                $sqlWhere = $sqlWhere . "and (article.description LIKE '%$prod_code%' OR article.article_id LIKE '%$prod_code%')";
            }

            if (!empty($batch_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.batch_id = ". "'" . $batch_id . "'";
            }

            if (!empty($add_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.add_id = ". "'" . $add_id . "'";
            }

            if (!empty($tor_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.tor_id = ". "'" . $tor_id . "'";
            }

            if (empty($type))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.qty_oh > 0";
            }

            $sqlWhere = $sqlWhere . "and article.cat_id IN (1,2)";

            $inv_bal = DB::connection("sqlSrvBCKI")
                                ->table('inv_bal')
                                ->selectRaw("
                                inv_bal.office_id, 
                                TRIM(inv_bal.article_id) as article_id, 
                                inv_bal.defect, 
                                TRIM(inv_bal.batch_id) as batch_id, 
                                inv_bal.length,  
                                TRIM(inv_bal.add_id) as add_id, 
                                TRIM(inv_bal.tor_id) as tor_id, 
                                TRIM(inv_bal.wh_id) as wh_id, 
                                TRIM(inv_bal.bin_id) as bin_id, 
                                TRIM(inv_bal.spk_id) as spk_id, 
                                inv_bal.spk_item,  
                                inv_bal.qty_oh,  
                                inv_bal.wgt_oh, 
                                inv_bal.avg_price, 
                                inv_bal.last_price, 
                                inv_bal.amt_balance, 
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
                                matrl_price_unit.price_mode as Satuan,
                                COALESCE(matrl_price_unit.hpp, 0) as price_unit,
                                COALESCE(matrl_price_unit.po_price, 0) as po_price
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
                                ->whereRaw($sqlWhere);

            $jasa_article = DB::connection("sqlSrvBCKI")
                            ->table('article')
                            ->selectRaw("
                                1 as office_id, 
                                TRIM(article.article_id) as article_id, 
                                0 as defect, 
                                '' as batch_id, 
                                1 as length,  
                                '' as add_id, 
                                '' as tor_id, 
                                '' as wh_id, 
                                '' as bin_id, 
                                '' as spk_id, 
                                0 as spk_item,  
                                1 as qty_oh,  
                                0 as wgt_oh, 
                                0 as avg_price, 
                                0 as last_price, 
                                0 as amt_balance, 
                                article.description, 
                                article.uom, 
                                article.ppn, 
                                article.berat, 
                                category.consume_able as consumeable,
                                category.cat_id,
                                '' as order_id,
                                0 as item_num,
                                '' as dt_modified,
                                '' as whouse_name,
                                'LOT' as Satuan,
                                0 as price_unit,
                                0 as po_price
                            ")
                            ->join('category', 'category.cat_id', '=', 'article.cat_id')
                            ->whereIn('article.article_id', ['04000008', '04000024', '04000023', '04000044']);

            $datas = $inv_bal->union($jasa_article)->orderBy('description')->get();

            return $this->sendResponse($datas, 'Successfully get Inv Bal Warehouse');


        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
        
    }

    public function getArticle(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $office_id = $request->office_id;
            $wh_id = $request->wh_id;
            $bin_id = $request->bin_id;
            $period = $request->period;
            $search = $request->search;
            $panjang = $request->panjang;
            $prod_code = $request->prod_code;
            $batch_id = $request->batch_id;
            $add_id = $request->add_id;
            $tor_id = $request->tor_id;
            $brand_id = $request->brand_id;
            $type = $request->type;

            if (!empty($office_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.office_id = ". "'" . $office_id . "'";
            }

            if (!empty($wh_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.wh_id = ". "'" . $wh_id . "'";
            }

            if (!empty($bin_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.bin_id = ". "'" . $bin_id . "'";
            }

            if (!empty($search))
            {
                $sqlWhere = $sqlWhere . "and article.description LIKE '%$search%'";
            }

            if (!empty($brand_id))
            {
                $sqlWhere = $sqlWhere . "and article.brand = ". "'" . $brand_id . "'";
            }

            if (!empty($panjang))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.length = ". "'" . $panjang . "'";
            }

            if (!empty($prod_code))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.article_id = ". "'" . $prod_code . "'";
            }

            if (!empty($batch_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.batch_id = ". "'" . $batch_id . "'";
            }

            if (!empty($add_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.add_id = ". "'" . $add_id . "'";
            }

            if (!empty($tor_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.tor_id = ". "'" . $tor_id . "'";
            }

            if (empty($type))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.qty_oh > 0";
            }

            $datas = DB::connection("sqlSrvBCKI")
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
                                CAST(inv_bal.qty_oh as numeric) as qty_oh,  
                                CAST(inv_bal.wgt_oh as numeric(10,2)) as wgt_oh, 
                                CAST(inv_bal.avg_price as numeric(10,2)) as avg_price, 
                                CAST(inv_bal.last_price as numeric(10,2)) as last_price, 
                                CAST(inv_bal.amt_balance as numeric(10,2)) as amt_balance, 
                                article.description, 
                                article.uom, 
                                article.ppn, 
                                article.berat, 
                                '' as order_id,
                                0 as item_num,
                                FORMAT(inv_bal.dt_modified,'yyyy-MM-dd hh:mm:ss') as dt_modified,
                                whouse.descr as whouse_name,
                                matrl_price_unit.price_mode as Satuan,
                                matrl_price_unit.hpp as price_unit,
                                matrl_price_unit.po_price as po_price
                                ")
                                ->join('article', 'inv_bal.article_id', '=', 'article.article_id')
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
                                ->whereRaw($sqlWhere)
                                ->orderBy('inv_bal.dt_modified', 'desc')
                                ->get();

            return $this->sendResponse($datas, 'Successfully get Inv Bal Warehouse');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
        
    }

    public function storeHeaderIssue(Request $request)
    {
        try {
            $site_id = $request->site_id ?? '';
            $cluster_id = $request->cluster_id ?? '';
            $house_item_id = $request->house_item_id ?? '';
            $no_spk = $request->no_spk ?? '';
            $now = now();
            
            $arrayParams = (object)[
                'period' => $now->format('Y') . $now->format('m'),
                'office_id' => $request->office_id,
                'tr_type' => 'IS',
                'tr_date' => now(),
                'tr_id_ref' => $request->tr_id_ref ? $request->tr_id_ref : '',
                'wh_id' => $request->wh_id,
                'bin_id' => $request->bin_id,
                'remark' => $request->remark ? $request->remark : '',
                'payment' => 0,
                'id_site' => $site_id,
                'id_cluster' => $cluster_id,
                'id_house' => $house_item_id,
                'no_spk' => $no_spk,
            ];

            $check = $this->storeTransHdrIssue($arrayParams, $request);

            if($check->status == TRUE){
                return $this->sendResponse($check->data, 'Successfully saved');
            } else {
                return $this->sendError($check->message, 'Error', 500);
            }

        }   catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function storeHeader(Request $request)
    {
        try {
            $arrayParams = (object)[
                'period' => $request->period,
                'office_id' => $request->office_id,
                'tr_type' => $request->tr_type,
                'tr_date' => $request->tr_date,
                'tr_id_ref' => $request->tr_id_ref ? $request->tr_id_ref : '',
                'wh_id' => $request->wh_id,
                'bin_id' => $request->bin_id,
                'remark' => $request->remark ? $request->remark : '',
                'payment' => 0
            ];

            $check = $this->storeTransHdr($arrayParams, $request);

            if($check->status == TRUE){
                return $this->sendResponse($check->data, 'Successfully saved');
            } else {
                return $this->sendError($check->message, 'Error', 500);
            }

        }   catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function store(Request $request){
        try {
            $arrayParams = (object)[
                'tr_id' => $request->tr_id,
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
                'length' => $request->length, 
                'satuan' => $request->satuan,
                'berat' => $request->berat,
                'qty_oh' => (float) str_replace(',', '.', $request->qty_oh),
                'defect' => $request->defect ? $request->defect : 0,
                'spk_id' => $request->spk_id ? $request->spk_id : ' ',
                'spk_item' => $request->spk_item ? $request->spk_item : 0,
                'BarangJadiId' => $request->article_id,
                'remark' => $request->remark ? $request->remark : '',
                'amt_item' => 0,
                'unit_price' => 0,
                'payment' => 0
            ];

            $check = $this->storeTransWH($arrayParams, $request);

            if($check->status == TRUE){
                return $this->sendResponse($check->message, 'Successfully saved');
            } else {
                return $this->sendError($check->message, 'Error', 500);
            }
        }   catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }

    }

    // private function convertWgt($BarangJadiId, $panjang)
    // {
    //     $GetBeratBarangJadi = 0;

    //     $data = DB::connection("sqlSrvBCKI")
    //             ->table('BarangJadi')
    //             ->selectRaw('conversion.wgt_unit, BarangJadi.matrl_width')
    //             ->join('conversion', 'BarangJadi.tebal', '=', 'conversion.thick')
    //             ->where('barangjadiid', $BarangJadiId)
    //             ->first();

    //     if ($data) { 
    //         $GetBeratBarangJadi = ($data->matrl_width * $panjang * $data->wgt_unit) / 1000;
    //         return $GetBeratBarangJadi;
    //     } else {
    //         return $GetBeratBarangJadi;
    //     }
    // }

    public function destroy(Request $request)
    {
        try {
            $arrayParams = (object)[
                'tr_id' => $request->tr_id,
                'office_id' => $request->office_id,
                'period' => $request->period,
                'tr_type' => $request->tr_type,
                'tr_date' => $request->tr_date,
                'seq_id' => $request->seq_id,
                'article_id' => $request->article_id,
                'wh_id' => $request->wh_id,
                'bin_id' => $request->bin_id,
                'prod_code' => $request->article_id,
                'add_id' => $request->add_id ? $request->add_id : ' ',
                'tor_id' => $request->tor_id ? $request->tor_id : ' ',
                'batch_id' => $request->batch_id ? $request->batch_id : ' ',
                'length' => $request->length, 
                'defect' => $request->defect ? $request->defect : 0,
                'spk_id' => $request->spk_id ? $request->spk_id : ' ',
                'spk_item' => $request->spk_item ? $request->spk_item : 0,
                'BarangJadiId' => $request->article_id,
                'payment' => 0
            ];

            $check = $this->deleteTransWh($arrayParams, $request);

            if($check->status == TRUE){
                return $this->sendResponse($check->message, 'Successfully saved');
            } else {
                return $this->sendError($check->message, 'Error', 500);
            }
        }   catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function destroyDropShipper(Request $request)
    {
        try {
            $arrayParams = (object)[
                'tr_id' => $request->tr_id,
                'office_id' => $request->office_id,
                'period' => $request->period,
                'tr_type' => $request->tr_type,
                'tr_date' => $request->tr_date,
                'seq_id' => $request->seq_id,
                'article_id' => $request->article_id,
                'wh_id' => $request->wh_id,
                'bin_id' => $request->bin_id,
                'prod_code' => $request->article_id,
                'add_id' => $request->add_id ? $request->add_id : ' ',
                'tor_id' => $request->tor_id ? $request->tor_id : ' ',
                'batch_id' => $request->batch_id ? $request->batch_id : ' ',
                'length' => $request->length, 
                'defect' => $request->defect ? $request->defect : 0,
                'spk_id' => $request->spk_id ? $request->spk_id : ' ',
                'spk_item' => $request->spk_item ? $request->spk_item : 0,
                'BarangJadiId' => $request->article_id
            ];

            $check = $this->deleteTransWh($arrayParams);

            if($check->status == TRUE){
                return $this->sendResponse($check->message, 'Successfully saved');
            } else {
                return $this->sendError($check->message, 'Error', 500);
            }
        }   catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function listDetailTransaction(Request $request)
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
                CAST(inv_trans_dtl.length as DECIMAL(10,2)) as length,  
                TRIM(inv_trans_dtl.add_id) as add_id, 
                TRIM(inv_trans_dtl.tor_id) as tor_id, 
                TRIM(inv_trans_dtl.spk_id) as spk_id, 
                inv_trans_dtl.spk_item,                
                CAST(inv_trans_dtl.qty as DECIMAL(18,2)) as qty,  
                CAST(inv_trans_dtl.wgt as DECIMAL(10,2)) as wgt, 
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
                ->where('inv_trans_dtl.tr_type', $request->tr_type)
                ->where('inv_trans_dtl.tr_id', $request->tr_id)
                ->get();

        return $this->sendResponse($datas, 'Successfully get WH Bal Warehouse');

    }

    public function listTransaction(Request $request) 
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
                ->table('inv_trans_hdr')
                ->selectRaw("
                    inv_trans_hdr.*, whouse.descr as warehouse, branch_office.office
                ")
                ->join('branch_office', function($join){
                    $join->on('inv_trans_hdr.office_id', '=', 'branch_office.office_id');
                })
                ->leftJoin('whouse', function($join){
                    $join->on('inv_trans_hdr.office_id', '=', 'whouse.office_id')
                    ->on('inv_trans_hdr.wh_id', '=', 'whouse.wh_id');
                })
                ->whereRaw($sqlWhere)
                ->orderBy('inv_trans_hdr.tr_date', 'ASC')
                ->get();

        return $this->sendResponse($datas, 'Successfully get Warehouse');
    }

    public function test1(Request $request)
    {
        
        return $request->str;

    }

}
