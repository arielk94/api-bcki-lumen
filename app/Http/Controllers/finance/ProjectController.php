<?php

namespace App\Http\Controllers\finance;

use App\Services\AccountingService;
use App\Http\Controllers\Controller;
use App\Traits\getFilterTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProjectController extends Controller
{
    public function getProjectData(Request $request){
        $id_site = $request->id_site;
        $id_cluster = $request->id_cluster;
        $id_house = $request->id_house;
        $id_customer = $request->id_customer;
        $sqlWhere = '1=1';

        if($id_site){
            $sqlWhere .= " AND vhh.id_site = '$id_site'";
        }
        if($id_cluster){
            $sqlWhere .= " AND vhh.id_cluster = '$id_cluster'";
        }
        if($id_house){
            $sqlWhere .= " AND vhh.id_house = '$id_house'";
        }
        if($id_customer){
            $sqlWhere .= " AND vhh.CustomerId = '$id_customer'";
        }

        $data = db::connection('sqlSrvBCKI')
        ->table('view_house_hist as vhh')
        ->select([
            'vhh.id_site',
            'vhh.id_cluster',
            'vhh.id_house',
            'vhh.site_name',
            'vhh.cluster_name',
            'vhh.house_name',
            'vhh.CustomerId',
            'vhh.status',
            DB::raw("ISNULL(vhh.NamaCustomer, '') as NamaCustomer"),
            DB::raw("ISNULL(vhh.ord_amt, 0) as total_spr"),
            DB::raw("ISNULL(vhh.amt_spk_a, 0) + ISNULL(vhh.amt_spk_b, 0) + ISNULL(vhh.amt_spk_k, 0) + ISNULL(vhh.amt_spk_l, 0) as total_spk"),
            DB::raw("ISNULL(vhh.total_spend, 0) as expense"),
            DB::raw("ISNULL(vhh.invoice, 0) as income"),
            DB::raw("ISNULL(pii_agg.liability, 0) AS liability"),
        ])
        ->leftJoin(
            DB::raw("(
                SELECT
                    pii.id_site,
                    pii.id_cluster,
                    pii.id_house,
                    SUM(pii.amt_item) AS liability
                FROM
                    purc_inv_item pii
                GROUP BY
                    pii.id_site,
                    pii.id_cluster,
                    pii.id_house
            ) AS pii_agg"),
            function ($join) {
                $join->on('pii_agg.id_site', '=', 'vhh.id_site')
                    ->on('pii_agg.id_cluster', '=', 'vhh.id_cluster')
                    ->on('pii_agg.id_house', '=', 'vhh.id_house');
            }
        )
        ->whereRaw($sqlWhere)
        ->get();
        return $this->sendResponse($data, 'Successfully get project card data');
    }

    public function getProjectDataDtl(Request $request){
        $id_site = $request->id_site;
        $id_cluster = $request->id_cluster;
        $id_house = $request->id_house;

        $dtlSPK = db::connection('sqlSrvBCKI')
            ->table('spk_hdr as sh')
            ->where('sh.id_site', $id_site)
            ->where('sh.id_cluster', $id_cluster)
            ->where('sh.id_house_unit', $id_house)
            ->leftJoin('spk_article as sa', 'sh.id_spk', '=', 'sa.id_spk')
            ->leftJoin('appl_constant as ac', function ($join) {
                $join->on('sh.spk_type', '=', 'ac.str1')
                    ->where('ac.key_code', '=', 'SPK_TYPE');
            })
            ->select([
                'sh.id_spk', 
                'sh.id_site', 
                'sh.id_cluster', 
                'sh.id_house_unit', 
                'sh.date_start', 
                'sh.date_target', 
                'sh.date_finish', 
                'sa.harga_satuan', 
                'sh.spk_type', 
                'ac.str2'
            ])
            ->get();

        $dtlSPR = db::connection('sqlSrvBCKI')
            ->table('CustomerOrderDetil')
            ->where('CustomerOrder.id_site', $id_site)
            ->where('CustomerOrderDetil.id_cluster', $id_cluster)
            ->where('CustomerOrderDetil.id_house', $id_house)
            ->leftJoin('CustomerOrder', 'CustomerOrderDetil.order_id', '=', 'CustomerOrder.order_id')
            ->select([
                    'CustomerOrder.ord_amt',
                    'CustomerOrder.utj',
                    'CustomerOrder.dp_amt',
                    'CustomerOrder.pay_method',
                    'CustomerOrder.remark',
                    'CustomerOrderDetil.*' // Ambil semua kolom dari tabel CustomerOrderDetil
                ])
            ->get();

        $dtlExpense = db::connection('sqlSrvBCKI')
            ->table('cash_tran_hdr')
            ->where('cash_tran_hdr.id_site', $id_site)
            ->where('cash_tran_hdr.id_cluster', $id_cluster)
            ->where('cash_tran_hdr.id_house', $id_house)
            ->leftJoin('cash_tran_dtl', 'cash_tran_hdr.tr_id', '=', 'cash_tran_dtl.tr_id')
            ->where('cash_tran_hdr.tr_type', 'OP')
            ->select('cash_tran_hdr.*', 'cash_tran_dtl.*')
            ->get();
            
        
        $dtlIncome = db::connection('sqlSrvBCKI')
            ->table('Penjualan as pj')
            ->where('co.id_site', $id_site)
            ->where('cod.id_cluster', $id_cluster)
            ->where('cod.id_house', $id_house)
            ->select('pj.CustomerOrderNo','pj.tgllunas','pj.Pelunasan', 'pjd.*', 'co.id_site', 'cod.id_cluster', 'cod.id_house')
            ->leftJoin('PenjualanDetil as pjd', 'pj.PenjualanNO', '=', 'pjd.PenjualanNO')
            ->leftJoin('CustomerOrder as co', 'pj.CustomerOrderNo', '=', 'co.order_id')
            ->leftJoin('customerorderdetil as cod', 'co.order_id', '=', 'cod.order_id')
            ->get();
        
        $dtlLiability = db::connection('sqlSrvBCKI')
            ->table('purc_inv_item')
            ->join('purc_inv_mast', 'purc_inv_item.tr_id', '=', 'purc_inv_mast.tr_id')
            ->select('purc_inv_item.*', 'purc_inv_mast.dt_inv')
            ->where('purc_inv_item.id_site', $id_site)
            ->where('purc_inv_item.id_cluster', $id_cluster)
            ->where('purc_inv_item.id_house', $id_house)
            ->get();

        $data = [
            'SPK' => $dtlSPK,
            'SPR' => $dtlSPR,
            'Expense' => $dtlExpense,
            'Income' => $dtlIncome,
            'Liability' => $dtlLiability
        ];

        return $this->sendResponse($data, 'Successfully get project card data');
    }
}
