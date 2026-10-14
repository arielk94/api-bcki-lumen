<?php

namespace App\Http\Controllers\transaction;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class SPKInformationController extends Controller
{
    public function getSPKInformation(Request $request)
    {
        $id_site = $request->id_site;
        $id_cluster = $request->id_cluster;
        $id_house = $request->id_house;
        $id_spk = $request->id_spk;
        $sqlWhere = '1=1';

        if ($id_site) {
            $sqlWhere .= " AND spk_hdr.id_site = '$id_site'";
        }
        if ($id_cluster) {
            $sqlWhere .= " AND spk_hdr.id_cluster = '$id_cluster'";
        }
        if ($id_house) {
            $sqlWhere .= " AND spk_hdr.id_house_unit = '$id_house'";
        }
        if ($id_spk) {
            $sqlWhere .= " AND spk_hdr.id_spk = '$id_spk'";
        }

        $data = db::connection('sqlSrvFPI')
            ->table('spk_hdr')
            ->select('spk_hdr.*', DB::raw("CONCAT(appl_constant.str2, ' - ', spk_hdr.remark) as str2"), 'site.site_name', 'site_cluster.cluster_name', 'house_unit.house_name', 'vendor.vendor_name', 'qc_trans_hdr.id_qc_form')
            ->leftJoin('appl_constant', function ($join) {
                $join->on('spk_hdr.spk_type', '=', 'appl_constant.str1')
                    ->where('appl_constant.key_code', '=', 'SPK_type');
            })
            ->leftJoin('house_unit', 'spk_hdr.id_house_unit', '=', 'house_unit.id_house')
            ->leftJoin('site', 'spk_hdr.id_site', '=', 'site.id_site')
            ->leftJoin('site_cluster', 'spk_hdr.id_cluster', '=', 'site_cluster.id_cluster')
            ->leftJoin('vendor', 'spk_hdr.pihak_2', '=', 'vendor.vendor_id')
            ->leftJoin('qc_trans_hdr', 'doc_id', '=', 'spk_hdr.id_spk')
            ->whereRaw($sqlWhere)
            ->get();
        return $this->sendResponse($data, 'Successfully get project card data');

    }

    public function getKasbonbySPK(Request $request)
    {
        $id_spk = $request->id_spk;
        $sqlWhereKasbon = '1=1';
        $sqlWhereQCOpname = '1=1';

        if ($id_spk) {
            $sqlWhereKasbon .= " AND kb_hdr.tr_id_ref = '$id_spk'";
            $sqlWhereQCOpname .= " AND a.spk_id = '$id_spk'";
        }

        $dataKasbon = db::connection('sqlSrvFPI')
            ->table('kb_hdr')
            // Menambahkan join ke view v_kasbon_list
            ->join('v_kasbon_list', 'kb_hdr.tr_id', '=', 'v_kasbon_list.tr_id')
            ->whereRaw($sqlWhereKasbon)
            ->where('kb_hdr.stat', '=', 'O')
            ->whereIn('kb_hdr.tr_type', ['EX', 'OT'])
            // Mengambil semua kolom dari kb_hdr dan kolom tr_type dari view
            ->select('kb_hdr.*', 'v_kasbon_list.tipe as view_tr_type')
            ->get();

        $dataQCOpname = db::connection('sqlSrvFPI')
            ->table('qc_opname as a')
            ->select(
                'a.tr_date',
                DB::raw('(ISNULL(d.amt_final, 0) + CASE WHEN ROW_NUMBER() OVER(PARTITION BY a.tr_id ORDER BY (SELECT NULL)) = 1 THEN ISNULL(a.amt_adj, 0) ELSE 0 END) as amt_total'),
                'a.dt_confirmed',
                'a.amt',
                'a.amt_retensi',
                DB::raw('CASE WHEN ROW_NUMBER() OVER(PARTITION BY a.tr_id ORDER BY (SELECT NULL)) = 1 THEN ISNULL(a.amt_adj, 0) ELSE 0 END as amt_adj'),
                'a.remark_adj',
                'b.spk_type',
                'c.str2',
                'd.description',
                'd.amt_final',
                'e.com_desc',
                'a.tr_id'
            )
            ->join('spk_hdr as b', 'a.spk_id', '=', 'b.id_spk')
            ->join('appl_constant as c', function ($join) {
                $join->on('b.spk_type', '=', 'c.str1')
                    ->where('c.key_code', '=', 'SPK_TYPE');
            })
            ->join('qc_opname_dtl as d', function ($join) {
                $join->on('a.tr_id', '=', 'd.tr_id')
                    ->on('a.spk_id', '=', 'd.spk_id')
                    ->on('a.qc_trans_id', '=', 'd.qc_trans_id');
            })
            ->join('qc_item_commodity as e', 'd.com_id', '=', 'e.com_id')->whereRaw($sqlWhereQCOpname)
            ->get();

        $reff_id = db::connection('sqlSrvFPI')
            ->table('qc_opname as a')
            ->whereRaw($sqlWhereQCOpname)
            ->pluck('tr_id')->toArray();

        // dd($reff_id);
        $dataPayment = db::connection('sqlSrvFPI')
            ->table('purc_inv_mast as a')
            ->leftJoin('purc_pay_item as b', 'a.inv_id', '=', 'b.inv_id')
            ->leftJoin('purc_pay_mast as f', 'b.pay_id', '=', 'f.pay_id')
            ->leftJoin('qc_opname as c', 'a.reff_id', '=', 'c.tr_id')
            ->leftJoin('qc_opname_dtl as d', function ($join) {
                $join->on('c.tr_id', '=', 'd.tr_id')
                    ->on('c.spk_id', '=', 'd.spk_id')
                    ->on('c.qc_trans_id', '=', 'd.qc_trans_id');
            })
            ->leftJoin('qc_item_commodity as e', 'd.com_id', '=', 'e.com_id')
            ->select(
                'a.reff_id',
                'a.amt_total',
                'b.inv_id',
                'b.dt_inv',
                'f.dt_pay',
                'f.remark',
                'f.bkk',
                DB::raw("ISNULL(SUM(b.pay_amt), 0) as pay_amt"),
                DB::raw("ISNULL(e.com_desc, '') as com_desc")
            )
            ->groupBy(
                'a.reff_id',
                'a.amt_total',
                'e.com_desc',
                'c.tr_id',
                'b.inv_id',
                'b.dt_inv',
                'f.dt_pay',
                'f.remark',
                'f.bkk'
            )
            ->where('b.pay_amt', '>', 0)
            ->whereIn('a.reff_id', $reff_id)
            ->get();
        //purc pay item - com desc di dataqcopname
        return $this->sendResponse(['Kasbon' => $dataKasbon, 'QC_Opname' => $dataQCOpname, 'Payment' => $dataPayment], 'Successfully get Kasbon by SPK data');
    }
}