<?php

namespace App\Http\Controllers\warehouse;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListTransactionWarehouseController extends Controller
{
    public function index(Request $request) 
    {
        $sqlWhere = "1=1";
        $office_id = $request->office_id;
        $article_id = $request->article_id;
        $wh_id = $request->wh_id;
        $tr_type = $request->tr_type;
        $type_transaction = $request->type_transaction;
        $period = $request->period;

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
    
            $sqlWhere.= " and inv_trans_dtl.tr_type = '$tr_type'";
        }

        if ($period) {
    
            $sqlWhere.= " and inv_trans_dtl.period = '$period'";
        }

    
        if ($office_id) {
    
            $sqlWhere.= " and inv_trans_dtl.office_id = '$office_id'";
        }

        if ($wh_id) {
    
            $sqlWhere.= " and inv_trans_dtl.wh_id = '$wh_id'";
        }

        if ($article_id) {
    
            $sqlWhere.= " and inv_trans_dtl.article_id = '$article_id'";
        }

        if ($type_transaction) {
            if ($type_transaction == 'I') {
                $sqlWhere.= " and inv_trans_dtl.tr_type IN ('IS', 'IW', 'MI', 'TI', 'PR', 'SA')";
            } elseif ($type_transaction == 'R') {
                $sqlWhere.= " and inv_trans_dtl.tr_type IN ('MR', 'RC', 'RW', 'SR', 'TR', 'RP')";
            } elseif ($type_transaction == 'A') {
                $sqlWhere.= " and inv_trans_dtl.tr_type IN ('IA')";
            }
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
                    inv_trans_dtl.*, inv_trans_hdr.tr_date, inv_trans_hdr.id_site,
                    isnull(site.site_name,'') as site_name,
                    inv_trans_hdr.id_cluster,
                    isnull(site_cluster.cluster_name,'') as cluster_name,
                    inv_trans_hdr.id_house,
                    isnull(house_unit.house_name,'') as house_name,
                    inv_trans_hdr.no_spk,
                    whouse.descr as warehouse, branch_office.office,
                    article.description as article, article.uom as uom_article
                ")
                ->join('inv_trans_hdr', function($join){
                    $join->on('inv_trans_dtl.office_id', '=', 'inv_trans_hdr.office_id')
                    ->on('inv_trans_dtl.tr_id', '=', 'inv_trans_hdr.tr_id');
                })
                ->join('branch_office', function($join){
                    $join->on('inv_trans_dtl.office_id', '=', 'branch_office.office_id');
                })
                ->join('whouse', function($join){
                    $join->on('inv_trans_dtl.office_id', '=', 'whouse.office_id')
                    ->on('inv_trans_dtl.wh_id', '=', 'whouse.wh_id');
                })
                ->join('article', function($join){
                    $join->on('inv_trans_dtl.article_id', '=', 'article.article_id');
                })
                ->leftJoin('site', 'inv_trans_hdr.id_site', '=', 'site.id_site')
                ->leftJoin('site_cluster', function ($join) {
                    $join->on('inv_trans_hdr.id_site', '=', 'site_cluster.id_site')
                        ->on('inv_trans_hdr.id_cluster', '=', 'site_cluster.id_cluster');
                })
                ->leftJoin('house_unit', function ($join) {
                    $join->on('inv_trans_hdr.id_site', '=', 'house_unit.id_site')
                        ->on('inv_trans_hdr.id_cluster', '=', 'house_unit.id_cluster')
                        ->on('inv_trans_hdr.id_house', '=', 'house_unit.id_house');
                })
                ->whereRaw($sqlWhere)
                ->orderBy('inv_trans_hdr.tr_date', 'ASC')
                ->get();

        return $this->sendResponse($datas, 'Successfully get List Transaction Warehouse');
    }
}
