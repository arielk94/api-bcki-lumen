<?php

namespace App\Http\Controllers\transaction;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClosingWeeklyController extends Controller
{
    public function index(Request $request)
    {
        $sqlWhere = "1=1";
        $id_site = $request->id_site;
        $id_cluster = $request->id_cluster;

        if ($id_site) {
    
            $sqlWhere.= " and site.id_site = '$id_site'";
        }

        if ($id_cluster) {
    
            $sqlWhere.= " and site_cluster.id_cluster = '$id_cluster'";
        }

        $datas = DB::connection('sqlSrvBCKI')->select(DB::raw("
            SELECT
                site.id_site,
                site.site_name,
                site_cluster.id_cluster,
                site_cluster.cluster_name,
                house_unit.id_house,
                house_unit.house_name,
                vendor.vendor_name,
                commodity.com_desc,
                commodity.com_id,
                qhdr.doc_type,
                qhdr.doc_id AS spk_id,
                spk_hdr.spk_type,
                spk_hdr.amt,
                CAST(spk_hdr.remark AS VARCHAR(MAX)) as remark,
                appl_constant.str2 as spk_type_descr,
                qtl.qc_trans_id,
                'BELUM CLOSING' as stat_closing,
                'BELUM DIKONFIRM' as stat_opname
            FROM
                [dbo].[qc_trans_dtl] AS qtl
                INNER JOIN qc_trans_hdr AS qhdr ON qhdr.qc_trans_id = qtl.qc_trans_id
                INNER JOIN qc_item AS qc_item ON qc_item.id_qc_item = qtl.id_qc_item
                INNER JOIN qc_item_commodity AS commodity ON qc_item.com_id = commodity.com_id
                INNER JOIN house_unit AS house_unit ON qhdr.id_house = house_unit.id_house
                INNER JOIN site_cluster AS site_cluster ON house_unit.id_cluster = site_cluster.id_cluster
                INNER JOIN site AS site ON house_unit.id_site = site.id_site
                INNER JOIN spk_hdr AS spk_hdr ON spk_hdr.id_spk = qhdr.doc_id
                INNER JOIN appl_constant AS appl_constant ON spk_hdr.spk_type = appl_constant.str1 AND appl_constant.key_code = 'SPK_TYPE'
                INNER JOIN vendor AS vendor ON vendor.vendor_id = spk_hdr.pihak_2 
            WHERE $sqlWhere AND qhdr.doc_type = 'SPK' 
                AND (
                    ( qtl.dt_aprv != '1900-01-01 00:00:00.000' AND qtl.stat_closing = 'N' ) 
                    OR ( qtl.dt_aprv2 != '1900-01-01 00:00:00.000' AND qtl.stat_closing2 = 'N' ) 
                    OR ( qtl.dt_aprv3 != '1900-01-01 00:00:00.000' AND qtl.stat_closing3 = 'N' ) 
                ) 
            GROUP BY
                site.id_site,
                site.site_name,
                site_cluster.id_cluster,
                site_cluster.cluster_name,
                house_unit.id_house,
                house_unit.house_name,
                vendor.vendor_name,
                commodity.com_desc,
                commodity.com_id,
                qhdr.doc_type,
                qhdr.doc_id,
                spk_hdr.spk_type,
                spk_hdr.amt,
                CAST(spk_hdr.remark AS VARCHAR(MAX)),
                appl_constant.str2,
                qtl.qc_trans_id
            ORDER BY
                site.id_site,
                site_cluster.id_cluster,
                house_unit.id_house,
                commodity.com_id
        "));

        return $this->sendResponse($datas, 'Successfully get Delivery');

    }

    public function indexClosed(Request $request)
    {
        $sqlWhere = "1=1";
        $id_site = $request->id_site;
        $id_cluster = $request->id_cluster;

        if ($id_site) {
    
            $sqlWhere.= " and site.id_site = '$id_site'";
        }

        if ($id_cluster) {
    
            $sqlWhere.= " and site_cluster.id_cluster = '$id_cluster'";
        }

        $datas = DB::connection('sqlSrvBCKI')->select(DB::raw("
            SELECT
                site.id_site,
                site.site_name,
                site_cluster.id_cluster,
                site_cluster.cluster_name,
                house_unit.id_house,
                house_unit.house_name,
                vendor.vendor_name,
                commodity.com_desc,
                commodity.com_id,
                qhdr.doc_type,
                qhdr.doc_id AS spk_id,
                spk_hdr.spk_type,
                spk_hdr.amt,
                CAST(spk_hdr.remark AS VARCHAR(MAX)) as remark,
                appl_constant.str2 as spk_type_descr,
                qtl.qc_trans_id,
                'SUDAH CLOSING' as stat_closing,
                'BELUM DIKONFIRM' as stat_opname
            FROM
                [dbo].[qc_trans_dtl] AS qtl
                INNER JOIN qc_trans_hdr AS qhdr ON qhdr.qc_trans_id = qtl.qc_trans_id
                INNER JOIN qc_item AS qc_item ON qc_item.id_qc_item = qtl.id_qc_item
                INNER JOIN qc_item_commodity AS commodity ON qc_item.com_id = commodity.com_id
                INNER JOIN house_unit AS house_unit ON qhdr.id_house = house_unit.id_house
                INNER JOIN site_cluster AS site_cluster ON house_unit.id_cluster = site_cluster.id_cluster
                INNER JOIN site AS site ON house_unit.id_site = site.id_site
                INNER JOIN spk_hdr AS spk_hdr ON spk_hdr.id_spk = qhdr.doc_id
                INNER JOIN appl_constant AS appl_constant ON spk_hdr.spk_type = appl_constant.str1 AND appl_constant.key_code = 'SPK_TYPE'
                INNER JOIN vendor AS vendor ON vendor.vendor_id = spk_hdr.pihak_2 
            WHERE $sqlWhere AND qhdr.doc_type = 'SPK' 
                AND (
                    ( qtl.dt_aprv != '1900-01-01 00:00:00.000' AND qtl.stat_closing = 'Y' ) 
                    OR ( qtl.dt_aprv2 != '1900-01-01 00:00:00.000' AND qtl.stat_closing2 = 'Y' ) 
                    OR ( qtl.dt_aprv3 != '1900-01-01 00:00:00.000' AND qtl.stat_closing3 = 'Y' ) 
                ) 
            GROUP BY
                site.id_site,
                site.site_name,
                site_cluster.id_cluster,
                site_cluster.cluster_name,
                house_unit.id_house,
                house_unit.house_name,
                vendor.vendor_name,
                commodity.com_desc,
                commodity.com_id,
                qhdr.doc_type,
                qhdr.doc_id,
                spk_hdr.spk_type,
                spk_hdr.amt,
                CAST(spk_hdr.remark AS VARCHAR(MAX)),
                appl_constant.str2,
                qtl.qc_trans_id
            ORDER BY
                site.id_site,
                site_cluster.id_cluster,
                house_unit.id_house,
                commodity.com_id
        "));

        return $this->sendResponse($datas, 'Successfully get Delivery');

    }

    public function show(Request $request)
    {
        $sqlWhere = "1=1";
        $spk_id = trim($request->spk_id);
        $spk_type = trim($request->spk_type);
        $com_id = $request->com_id;

        $datas = DB::connection('sqlSrvBCKI')->select(DB::raw("
                    SELECT
                        site.id_site,
                        site.site_name + ' ' + site.site_address AS site_name,
                        site_cluster.id_cluster,
                        site_cluster.cluster_name,
                        house_unit.house_name,
                        vendor.vendor_name,
                        commodity.com_desc,
                        commodity.com_id,
                        qhdr.doc_type,
                        qhdr.doc_id AS spk_id,
                        spk_hdr.spk_type,
                        spk_hdr.amt,
                        appl_constant.str2 as spk_type_descr,
                        qtl.qc_trans_id,
                        qtl.id_qc_item,
                        qc_item.qc_item,
	                    qc_item.qc_item_html,
                        qtl.stat,
                        qtl.dt_aprv,
                        qtl.aprv_by,
                        qtl.dt_aprv2,
                        qtl.aprv2_by,
                        qtl.dt_aprv3,
                        qtl.aprv3_by,
                        qtl.remark,
                        qtl.stat_closing,
                        qtl.stat_closing2,
                        qtl.stat_closing3,
                        qhdr.id_qc_form,
                        qhdr.id_house 
                    FROM
                        [dbo].[qc_trans_dtl] AS qtl
                        INNER JOIN qc_trans_hdr AS qhdr ON qhdr.qc_trans_id = qtl.qc_trans_id
                        INNER JOIN qc_item AS qc_item ON qc_item.id_qc_item = qtl.id_qc_item
                        INNER JOIN qc_item_commodity AS commodity ON qc_item.com_id = commodity.com_id
                        INNER JOIN house_unit AS house_unit ON qhdr.id_house = house_unit.id_house
                        INNER JOIN site_cluster AS site_cluster ON house_unit.id_cluster = site_cluster.id_cluster
                        INNER JOIN site AS site ON house_unit.id_site = site.id_site
                        INNER JOIN spk_hdr AS spk_hdr ON spk_hdr.id_spk = qhdr.doc_id
                        INNER JOIN appl_constant AS appl_constant ON spk_hdr.spk_type = appl_constant.str1 AND appl_constant.key_code = 'SPK_TYPE'
                        LEFT OUTER JOIN vendor AS vendor ON vendor.vendor_id = spk_hdr.pihak_2 
                    WHERE
                        qhdr.doc_type = 'SPK' AND qhdr.doc_id = '$spk_id' AND spk_hdr.spk_type = '$request->spk_type' AND qc_item.com_id = $request->com_id
                    ORDER BY
                        qtl.qc_trans_id,
                        qtl.id_qc_item
        "));

        return $this->sendResponse($datas, 'Successfully get Delivery');
    }

    public function store(Request $request)
    {
        // $payload = ['period, closed_date, items, spk_id, spk_type, qc_trans_id, com_id, description, user_id2, commodities, amt']
        $closed_date = Carbon::parse($request->closed_date)->format('Y-m-d');
        $user_id = getAuth($request)->user_id2;

        $opname_hdr = DB::connection('sqlSrvBCKI')
                        ->table('qc_opname')
                        ->whereDate('tr_date', $closed_date)
                        ->where('spk_id', trim($request->spk_id))
                        ->where('qc_trans_id', trim($request->qc_trans_id))
                        ->first();

        if ($opname_hdr) {
            if ($opname_hdr->dt_confirmed != '1900-01-01 00:00:00.000') {
                return $this->sendError('SPK dengan trans ID tsb sudah jadi Invoice, hapus Invoice dulu', 'Error', 500);
            }
        }
        // $qc_period_weekly = DB::connection('sqlSrvBCKI')
        // ->table('qc_period_weekly')
        // ->where('close_date', $closed_date)
        // ->where('spk_id', $request->spk_id)
        // ->where('spk_type', $request->spk_type)
        // ->where('com_id', $request->com_id)
        // ->first();

        // if ($qc_period_weekly) {
        //     return $this->sendError('Upps maaf sudah di closing untuk tgl ' . $closed_date, 'Error', 500);
        // }
        DB::connection('sqlSrvBCKI')->beginTransaction();
        try {

            foreach ($request->items as $key => $value) {
                $value = (object) $value;

                $closed = DB::connection("sqlSrvBCKI")
                ->table('qc_closing_weekly')
                ->select('item_num')
                ->where('close_date', $closed_date)
                ->where('spk_id', $value->spk_id)
                ->where('qc_trans_id', $value->qc_trans_id)
                ->where('qc_item_id', $value->id_qc_item)
                ->first();

                if ($closed) {
                    DB::connection("sqlSrvBCKI")
                    ->table('qc_closing_weekly')
                    ->select('item_num')
                    ->where('close_date', $closed_date)
                    ->where('spk_id', $value->spk_id)
                    ->where('qc_trans_id', $value->qc_trans_id)
                    ->where('qc_item_id', $value->id_qc_item)
                    ->delete();

                    $item_num = DB::connection("sqlSrvBCKI")
                            ->table('qc_closing_weekly')
                            ->select('item_num')
                            ->where('close_date', $closed_date)
                            ->where('spk_id', $value->spk_id)
                            ->where('qc_trans_id', $value->qc_trans_id)
                            ->where('qc_item_id', $value->id_qc_item)
                            ->max('item_num');

                    DB::connection('sqlSrvBCKI')
                    ->table('qc_closing_weekly')
                    ->insert([
                        'close_date' => $closed_date,
                        'spk_id' => $value->spk_id,
                        'qc_trans_id' => $value->qc_trans_id,
                        'qc_item_id' => $value->id_qc_item,
                        'item_num' => $item_num + 1,
                        'com_id' => $value->com_id,
                        'check1' => $value->stat_closing == 'Y' ? 'Y' : ( $value->dt_aprv != '1900-01-01 00:00:00.000' ? 'Y' : 'N'),
                        'check2' => $value->stat_closing2 == 'Y' ? 'Y' : ( $value->dt_aprv2 != '1900-01-01 00:00:00.000' ? 'Y' : 'N'),
                        'check3' => $value->stat_closing3 == 'Y' ? 'Y' : ( $value->dt_aprv3 != '1900-01-01 00:00:00.000' ? 'Y' : 'N'),
                        'closing_by' => $user_id
                    ]);
                } else {
                    $item_num = DB::connection("sqlSrvBCKI")
                            ->table('qc_closing_weekly')
                            ->select('item_num')
                            ->where('close_date', $closed_date)
                            ->where('spk_id', $value->spk_id)
                            ->where('qc_trans_id', $value->qc_trans_id)
                            ->where('qc_item_id', $value->id_qc_item)
                            ->max('item_num');

                    DB::connection('sqlSrvBCKI')
                    ->table('qc_closing_weekly')
                    ->insert([
                        'close_date' => $closed_date,
                        'spk_id' => $value->spk_id,
                        'qc_trans_id' => $value->qc_trans_id,
                        'qc_item_id' => $value->id_qc_item,
                        'item_num' => $item_num + 1,
                        'com_id' => $value->com_id,
                        'check1' => $value->stat_closing == 'Y' ? 'N' : ( $value->dt_aprv != '1900-01-01 00:00:00.000' ? 'Y' : 'N'),
                        'check2' => $value->stat_closing2 == 'Y' ? 'N' : ( $value->dt_aprv2 != '1900-01-01 00:00:00.000' ? 'Y' : 'N'),
                        'check3' => $value->stat_closing3 == 'Y' ? 'N' : ( $value->dt_aprv3 != '1900-01-01 00:00:00.000' ? 'Y' : 'N'),
                        'closing_by' => $user_id
                    ]);
                }                
                 
                DB::connection('sqlSrvBCKI')->update(
                    "UPDATE qc_trans_dtl
                    SET stat_closing = ?, stat_closing2 = ?, stat_closing3 = ? 
                    WHERE qc_trans_id = ? AND id_qc_item = ?", 
                    [$value->stat_closing == 'Y' ? 'Y' : ( $value->dt_aprv != '1900-01-01 00:00:00.000' ? 'Y' : 'N'), $value->stat_closing2 == 'Y' ? 'Y' : ( $value->dt_aprv2 != '1900-01-01 00:00:00.000' ? 'Y' : 'N'), $value->stat_closing3 == 'Y' ? 'Y' : ( $value->dt_aprv3 != '1900-01-01 00:00:00.000' ? 'Y' : 'N'),
                    $value->qc_trans_id, $value->id_qc_item]
                );
            }

            $qc_period_weekly = DB::connection('sqlSrvBCKI')
            ->table('qc_period_weekly')
            ->where('close_date', $closed_date)
            ->where('spk_id', $request->spk_id)
            ->where('spk_type', $request->spk_type)
            ->where('com_id', $request->com_id)
            ->first();

            if (!$qc_period_weekly) {
                DB::connection('sqlSrvBCKI')
                ->table('qc_period_weekly')
                ->insert([
                    'close_date' => $closed_date,
                    'curr_period' => $request->period,
                    'closed_by' => $user_id,
                    'spk_id' => $request->spk_id,
                    'spk_type' => $request->spk_type,
                    'com_id' => $request->com_id,
                    'created_date' => now()
                ]);
            }

            $qc_trans_hdr = DB::connection('sqlSrvBCKI')
            ->table('qc_trans_hdr')
            ->where('doc_id', $request->spk_id)
            ->where('doc_type', 'SPK')
            ->first();

            $id_qc_from = $qc_trans_hdr ? $qc_trans_hdr->id_qc_form : '';

            $this->storeOpname($request, $closed_date, $id_qc_from);

            DB::connection('sqlSrvBCKI')->commit();

            return $this->sendResponse('', 'Successfully closed');

        }
        catch (\Exception $e) {
            DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function unclosing(Request $request)
    {
        // $payload = ['spk_id, spk_type, qc_trans_id, com_id, items']

        $opname_hdr = DB::connection('sqlSrvBCKI')
                        ->table('qc_opname_dtl as qc_opname_dtl')
                        ->join('qc_opname',function($join){
                            $join->on('qc_opname.tr_id','=','qc_opname_dtl.tr_id')
                            ->on('qc_opname.spk_id','=','qc_opname_dtl.spk_id')
                            ->on('qc_opname.qc_trans_id','=','qc_opname_dtl.qc_trans_id');
                        })
                        ->where('qc_opname_dtl.spk_id', trim($request->spk_id))
                        ->where('qc_opname_dtl.qc_trans_id', trim($request->qc_trans_id))
                        ->where('qc_opname_dtl.com_id', $request->com_id)
                        ->first();

        if ($opname_hdr) {
            if ($opname_hdr->dt_confirmed != '1900-01-01 00:00:00.000') {
                return $this->sendError('Tidak bisa unclosing, SPK sudah jadi Invoice', 'Error', 500);
            }
        }
        DB::connection('sqlSrvBCKI')->beginTransaction();
        try {

            foreach ($request->items as $key => $value) {
                $value = (object) $value;

                DB::connection("sqlSrvBCKI")
                ->table('qc_closing_weekly')
                ->where('spk_id', $value->spk_id)
                ->where('qc_trans_id', $value->qc_trans_id)
                ->where('qc_item_id', $value->id_qc_item)
                ->where('com_id', $request->com_id)
                ->delete();
                 
                DB::connection('sqlSrvBCKI')->update(
                    "UPDATE qc_trans_dtl
                    SET stat_closing = ?, stat_closing2 = ?, stat_closing3 = ? 
                    WHERE qc_trans_id = ? AND id_qc_item = ?", 
                    ['N','N','N',
                    $value->qc_trans_id, $value->id_qc_item]
                );
            }

            if ($opname_hdr) {
                DB::connection("sqlSrvBCKI")
                ->table('qc_opname_kasbon')
                ->where('spk_id', $request->spk_id)
                ->where('tr_id', $opname_hdr->tr_id)
                ->delete();
            }

            DB::connection("sqlSrvBCKI")
            ->table('qc_opname_dtl')
            ->where('spk_id', $request->spk_id)
            ->where('qc_trans_id', $request->qc_trans_id)
            ->where('com_id', $request->com_id)
            ->delete();

            $countHeader = DB::connection("sqlSrvBCKI")
            ->table('qc_opname_dtl')
            ->where('spk_id', $request->spk_id)
            ->where('qc_trans_id', $request->qc_trans_id)
            ->count();

            if ((int) $countHeader == 0) {
                DB::connection("sqlSrvBCKI")
                ->table('qc_opname')
                ->where('spk_id', $request->spk_id)
                ->where('qc_trans_id', $request->qc_trans_id)
                ->delete();
            }

            $qc_period_weekly = DB::connection('sqlSrvBCKI')
            ->table('qc_period_weekly')
            ->where('spk_id', $request->spk_id)
            ->where('spk_type', $request->spk_type)
            ->where('com_id', $request->com_id)
            ->delete();

            DB::connection("sqlSrvBCKI")
            ->table('qc_opname_kasbon')
            ->where('spk_id', $request->spk_id)
            ->delete();

            DB::connection('sqlSrvBCKI')->commit();

            return $this->sendResponse('', 'Successfully unclosed');

        }
        catch (\Exception $e) {
            DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function storeOpname($request, $closed_date, $id_qc_from){
        $sum_closing = DB::connection('sqlSrvBCKI')->selectOne("
                    SELECT 
                        SUM(CASE WHEN check2 = 'Y' THEN 1 ELSE 0 END) AS total_checked,
                        SUM(CASE WHEN check3 = 'Y' THEN 1 ELSE 0 END) AS total_qc
                    FROM qc_closing_weekly WITH (NOLOCK)
                    WHERE spk_id = ? AND qc_trans_id = ?  AND com_id = ?
                ", [trim($request->spk_id), trim($request->qc_trans_id), $request->com_id]);

        $sum_trans_dtl = DB::connection('sqlSrvBCKI')->selectOne("
                    SELECT 
                        SUM(CASE WHEN qtl.dt_aprv2 IS NOT NULL THEN 1 ELSE 0 END) AS total_checked,
                        SUM(CASE WHEN qtl.dt_aprv3 IS NOT NULL THEN 1 ELSE 0 END) AS total_qc
                    FROM qc_trans_dtl as qtl WITH (NOLOCK)
                    INNER JOIN qc_trans_hdr AS qhdr ON qhdr.qc_trans_id = qtl.qc_trans_id
                    INNER JOIN qc_item AS qc_item ON qc_item.id_qc_item = qtl.id_qc_item
                    INNER JOIN qc_item_commodity AS commodity ON qc_item.com_id = commodity.com_id 
                    WHERE qhdr.doc_type = 'SPK' AND qhdr.doc_id = ? AND qtl.qc_trans_id = ?  AND commodity.com_id = ?
                ", [trim($request->spk_id), trim($request->qc_trans_id), $request->com_id]);

        $qc_trans_id = trim($request->qc_trans_id);
        $closing_total_checked = $sum_closing ? $sum_closing->total_checked : 0;
        $closing_total_qc = $sum_closing ? $sum_closing->total_qc : 0;

        $trans_total_checked = $sum_trans_dtl ? $sum_trans_dtl->total_checked : 0;
        $trans_total_qc = $sum_trans_dtl ? $sum_trans_dtl->total_qc : 0;

        if (trim($request->spk_type) == 'A' || trim($request->spk_type) == 'L') {
            if (($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0) && ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0)) {
                try {
                    $opname_hdr = DB::connection('sqlSrvBCKI')
                    ->table('qc_opname')
                    ->whereDate('tr_date', $closed_date)
                    ->where('spk_id', trim($request->spk_id))
                    ->where('qc_trans_id', trim($request->qc_trans_id))
                    ->first();

                    if ($opname_hdr) {
                        $transId = $opname_hdr->tr_id;
                    } else {
                        $transId = $this->generateID($request);
                        DB::connection('sqlSrvBCKI')
                        ->table('qc_opname')
                        ->insert([
                            'tr_id' => $transId,
                            'spk_id' => trim($request->spk_id),
                            'qc_trans_id' => trim($request->qc_trans_id),
                            'tr_date' => now(),
                            'description' => $request->description ? $request->description : ' ',
                            'dt_created' => now(),
                            'created_by' => $request->user_id2 ? $request->user_id2 : ' ',
                            'closed_date' => $closed_date
                        ]);
                    }

                    $total_original = $request->amt;
                    // $last_percent = 0;

                    // foreach ($request->commodities as $com) {
                    //     $com = (object) $com;

                    //     if ($com->com_id == $request->com_id) {
                    //         $total_original = ($com->percent - $last_percent) * $request->amt;
                    //         break;
                    //     }

                    //     $last_percent = $com->percent;
                    // }                        

                    if ((int) $request->com_id != 8) { // cek apakah commodity ikut retensi 5% apa tidak, jika tidak then
                        if ($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0) {
                            $amt = 0.75 * $total_original;
                            $amt_retensi = 0;
                            $total_final = $amt - $amt_retensi;

                            $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                            ->table('qc_opname_dtl')
                            ->where('spk_id', $request->spk_id)
                            ->where('qc_trans_id', $request->qc_trans_id)
                            ->where('com_id', $request->com_id)
                            ->where('type', 2)
                            ->first();

                            if (!$qc_opname_dtl) {
                                $item_num = DB::connection("sqlSrvBCKI")->table('qc_opname_dtl')
                                            ->select('item_num')
                                            ->where('tr_id', $transId)
                                            ->where('spk_id', $request->spk_id)
                                            ->where('qc_trans_id', $request->qc_trans_id)
                                            ->max('item_num');

                                DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->insert([
                                    'tr_id' => $transId,
                                    'spk_id' => trim($request->spk_id),
                                    'qc_trans_id' => trim($request->qc_trans_id),
                                    'item_num' => $item_num + 1,
                                    'com_id' => $request->com_id,
                                    'type' => 2,
                                    'description' => 'Sudah di periksa oleh pemeriksa',
                                    'amt' => $amt,
                                    'amt_retensi' => $amt_retensi,
                                    'amt_final' => $total_final,
                                    'dt_created' => now(),
                                    'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                ]);
                            }                   

                        }

                        if ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) {
                            $amt = 0.25 * $total_original;
                            $amt_retensi = 0;
                            $total_final = $amt - $amt_retensi;

                            $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                            ->table('qc_opname_dtl')
                            ->where('spk_id', $request->spk_id)
                            ->where('qc_trans_id', $request->qc_trans_id)
                            ->where('com_id', $request->com_id)
                            ->where('type', 3)
                            ->first();

                            if (!$qc_opname_dtl) {

                                $item_num = DB::connection("sqlSrvBCKI")
                                            ->table('qc_opname_dtl')
                                            ->select('item_num')
                                            ->where('tr_id', $transId)
                                            ->where('spk_id', $request->spk_id)
                                            ->where('qc_trans_id', $request->qc_trans_id)
                                            ->max('item_num');

                                DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->insert([
                                    'tr_id' => $transId,
                                    'spk_id' => trim($request->spk_id),
                                    'qc_trans_id' => trim($request->qc_trans_id),
                                    'item_num' => $item_num + 1,
                                    'com_id' => $request->com_id,
                                    'type' => 3,
                                    'description' => 'Sudah di periksa oleh QC',
                                    'amt' => $amt,
                                    'amt_retensi' => $amt_retensi,
                                    'amt_final' => $total_final,
                                    'dt_created' => now(),
                                    'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                ]);
                            }
                        }

                        $this->updateOpnameHdr($transId, trim($request->spk_id), trim($request->qc_trans_id), $request);
                    } 
                    // else {
                    //     if ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) {
                    //         $amt = 1 * $total_original;
                    //         $amt_retensi = 0;
                    //         $total_qc = $amt - $amt_retensi;

                    //         $sum_retensi = DB::connection('sqlSrvBCKI')->selectOne("
                    //             SELECT 
                    //                 SUM(amt_retensi) as amt_retensi
                    //             FROM qc_opname_dtl WITH (NOLOCK)
                    //             WHERE spk_id = ?  AND qc_trans_id = ?
                    //         ", [trim($request->spk_id), trim($request->qc_trans_id)]);

                    //         $total_retensi = $sum_retensi ? ($sum_retensi->amt_retensi ? $sum_retensi->amt_retensi : 0) : 0;
                    //         $total_final = $total_qc + $total_retensi;

                    //         $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                    //         ->table('qc_opname_dtl')
                    //         ->where('spk_id', $request->spk_id)
                    //         ->where('qc_trans_id', $request->qc_trans_id)
                    //         ->where('com_id', $request->com_id)
                    //         ->where('type', 3)
                    //         ->first();

                    //         if (!$qc_opname_dtl) {

                    //             $item_num = DB::connection("sqlSrvBCKI")
                    //                         ->table('qc_opname_dtl')
                    //                         ->select('item_num')
                    //                         ->where('tr_id', $transId)
                    //                         ->where('spk_id', $request->spk_id)
                    //                         ->where('qc_trans_id', $request->qc_trans_id)
                    //                         ->max('item_num');

                    //             DB::connection('sqlSrvBCKI')
                    //             ->table('qc_opname_dtl')
                    //             ->insert([
                    //                 'tr_id' => $transId,
                    //                 'spk_id' => trim($request->spk_id),
                    //                 'qc_trans_id' => trim($request->qc_trans_id),
                    //                 'item_num' => $item_num + 1,
                    //                 'com_id' => $request->com_id,
                    //                 'type' => 3,
                    //                 'description' => 'Sudah di periksa oleh QC dan ditambahkan total simpanan retensi 5%',
                    //                 'amt' => $amt,
                    //                 'amt_retensi' => $amt_retensi,
                    //                 'amt_final' => $total_final,
                    //                 'dt_created' => now(),
                    //                 'created_by' => $request->user_id2 ? $request->user_id2 : ''
                    //             ]);

                    //             $this->updateOpnameHdr($transId, trim($request->spk_id), trim($request->qc_trans_id), $request);

                    //         }
                    //     }
                    // }
                }
                catch (\Exception $e) {
                    throw new \Exception($e->getMessage());
                }
            }
        } elseif (trim($request->spk_type) == 'K') {
            if (($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0) || ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0)) {

                try {
                    $opname_hdr = DB::connection('sqlSrvBCKI')
                    ->table('qc_opname')
                    ->whereDate('tr_date', $closed_date)
                    ->where('spk_id', trim($request->spk_id))
                    ->where('qc_trans_id', trim($request->qc_trans_id))
                    ->first();

                    if ($opname_hdr) {
                        $transId = $opname_hdr->tr_id;
                    } else {
                        $transId = $this->generateID($request);
                        DB::connection('sqlSrvBCKI')
                        ->table('qc_opname')
                        ->insert([
                            'tr_id' => $transId,
                            'spk_id' => trim($request->spk_id),
                            'qc_trans_id' => trim($request->qc_trans_id),
                            'tr_date' => now(),
                            'description' => $request->description ? $request->description : ' ',
                            'dt_created' => now(),
                            'created_by' => $request->user_id2 ? $request->user_id2 : ' ',
                            'closed_date' => $closed_date
                        ]);
                    }

                    $total_original = $request->amt;

                    if ((int) $request->com_id != 8) { // cek apakah commodity ikut retensi 5% apa tidak, jika tidak then
                        if ($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0) {
                            $amt = 0.75 * $total_original;
                            $amt_retensi = 0;
                            $total_final = $amt - $amt_retensi;

                            $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                            ->table('qc_opname_dtl')
                            ->where('spk_id', $request->spk_id)
                            ->where('qc_trans_id', $request->qc_trans_id)
                            ->where('com_id', $request->com_id)
                            ->where('type', 2)
                            ->first();

                            if (!$qc_opname_dtl) {
                                $item_num = DB::connection("sqlSrvBCKI")->table('qc_opname_dtl')
                                            ->select('item_num')
                                            ->where('tr_id', $transId)
                                            ->where('spk_id', $request->spk_id)
                                            ->where('qc_trans_id', $request->qc_trans_id)
                                            ->max('item_num');

                                DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->insert([
                                    'tr_id' => $transId,
                                    'spk_id' => trim($request->spk_id),
                                    'qc_trans_id' => trim($request->qc_trans_id),
                                    'item_num' => $item_num + 1,
                                    'com_id' => $request->com_id,
                                    'type' => 2,
                                    'description' => 'Sudah di periksa oleh pemeriksa',
                                    'amt' => $amt,
                                    'amt_retensi' => $amt_retensi,
                                    'amt_final' => $total_final,
                                    'dt_created' => now(),
                                    'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                ]);
                            }                   

                        }

                        if ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) {
                            $amt = 0.25 * $total_original;
                            $amt_retensi = 0;
                            $total_final = $amt - $amt_retensi;

                            $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                            ->table('qc_opname_dtl')
                            ->where('spk_id', $request->spk_id)
                            ->where('qc_trans_id', $request->qc_trans_id)
                            ->where('com_id', $request->com_id)
                            ->where('type', 3)
                            ->first();

                            if (!$qc_opname_dtl) {

                                $item_num = DB::connection("sqlSrvBCKI")
                                            ->table('qc_opname_dtl')
                                            ->select('item_num')
                                            ->where('tr_id', $transId)
                                            ->where('spk_id', $request->spk_id)
                                            ->where('qc_trans_id', $request->qc_trans_id)
                                            ->max('item_num');

                                DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->insert([
                                    'tr_id' => $transId,
                                    'spk_id' => trim($request->spk_id),
                                    'qc_trans_id' => trim($request->qc_trans_id),
                                    'item_num' => $item_num + 1,
                                    'com_id' => $request->com_id,
                                    'type' => 3,
                                    'description' => 'Sudah di periksa oleh QC',
                                    'amt' => $amt,
                                    'amt_retensi' => $amt_retensi,
                                    'amt_final' => $total_final,
                                    'dt_created' => now(),
                                    'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                ]);
                            }
                        }

                        $this->updateOpnameHdr($transId, trim($request->spk_id), trim($request->qc_trans_id), $request);
                    } 
                }
                catch (\Exception $e) {
                    throw new \Exception($e->getMessage());
                }
            }
        } elseif (trim($request->spk_type) == 'C') {
            if (($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0) || ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0)) {

                try {

                    $opname_hdr = DB::connection('sqlSrvBCKI')
                    ->table('qc_opname')
                    ->whereDate('tr_date', $closed_date)
                    ->where('spk_id', trim($request->spk_id))
                    ->where('qc_trans_id', trim($request->qc_trans_id))
                    ->first();

                    if ($opname_hdr) {
                        $transId = $opname_hdr->tr_id;
                    } else {
                        $transId = $this->generateID($request);
                        DB::connection('sqlSrvBCKI')
                        ->table('qc_opname')
                        ->insert([
                            'tr_id' => $transId,
                            'spk_id' => trim($request->spk_id),
                            'qc_trans_id' => trim($request->qc_trans_id),
                            'tr_date' => now(),
                            'description' => $request->description ? $request->description : ' ',
                            'dt_created' => now(),
                            'created_by' => $request->user_id2 ? $request->user_id2 : ' ',
                            'closed_date' => $closed_date
                        ]);
                    }

                    $total_original = 0;
                    $last_percent = 0;

                    foreach ($request->commodities as $com) {
                        $com = (object) $com;

                        if ($com->com_id == $request->com_id) {
                            // $total_original = ($com->percent - $last_percent) * $request->amt;
                            $total_original = $com->percent * $request->amt;
                            break;
                        }

                        // $last_percent = $com->percent;
                    }                        

                    if ((int) $request->com_id != 8) { // cek apakah commodity ikut retensi 5% apa tidak, jika tidak then
                        if ($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0) {
                            $amt = 0.75 * $total_original;
                            $amt_retensi = 0;
                            $total_final = $amt - $amt_retensi;

                            $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                            ->table('qc_opname_dtl')
                            ->where('spk_id', $request->spk_id)
                            ->where('qc_trans_id', $request->qc_trans_id)
                            ->where('com_id', $request->com_id)
                            ->where('type', 2)
                            ->first();

                            if (!$qc_opname_dtl) {
                                $item_num = DB::connection("sqlSrvBCKI")->table('qc_opname_dtl')
                                            ->select('item_num')
                                            ->where('tr_id', $transId)
                                            ->where('spk_id', $request->spk_id)
                                            ->where('qc_trans_id', $request->qc_trans_id)
                                            ->max('item_num');

                                DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->insert([
                                    'tr_id' => $transId,
                                    'spk_id' => trim($request->spk_id),
                                    'qc_trans_id' => trim($request->qc_trans_id),
                                    'item_num' => $item_num + 1,
                                    'com_id' => $request->com_id,
                                    'type' => 2,
                                    'description' => 'Sudah di periksa oleh pemeriksa',
                                    'amt' => $amt,
                                    'amt_retensi' => $amt_retensi,
                                    'amt_final' => $total_final,
                                    'dt_created' => now(),
                                    'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                ]);
                            }                   

                        }

                        if ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) {
                            $amt = 0.25 * $total_original;
                            $amt_retensi = 0;
                            $total_final = $amt - $amt_retensi;

                            $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                            ->table('qc_opname_dtl')
                            ->where('spk_id', $request->spk_id)
                            ->where('qc_trans_id', $request->qc_trans_id)
                            ->where('com_id', $request->com_id)
                            ->where('type', 3)
                            ->first();

                            if (!$qc_opname_dtl) {

                                $item_num = DB::connection("sqlSrvBCKI")
                                            ->table('qc_opname_dtl')
                                            ->select('item_num')
                                            ->where('tr_id', $transId)
                                            ->where('spk_id', $request->spk_id)
                                            ->where('qc_trans_id', $request->qc_trans_id)
                                            ->max('item_num');

                                DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->insert([
                                    'tr_id' => $transId,
                                    'spk_id' => trim($request->spk_id),
                                    'qc_trans_id' => trim($request->qc_trans_id),
                                    'item_num' => $item_num + 1,
                                    'com_id' => $request->com_id,
                                    'type' => 3,
                                    'description' => 'Sudah di periksa oleh QC',
                                    'amt' => $amt,
                                    'amt_retensi' => $amt_retensi,
                                    'amt_final' => $total_final,
                                    'dt_created' => now(),
                                    'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                ]);
                            }
                        }

                        $this->updateOpnameHdr($transId, trim($request->spk_id), trim($request->qc_trans_id), $request);
                    } else {
                        if ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) {
                            $amt = 1 * $total_original;
                            $amt_retensi = 0;
                            $total_final = $amt - $amt_retensi;
                            $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                            ->table('qc_opname_dtl')
                            ->where('spk_id', $request->spk_id)
                            ->where('qc_trans_id', $request->qc_trans_id)
                            ->where('com_id', $request->com_id)
                            ->where('type', 3)
                            ->first();

                            if (!$qc_opname_dtl) {

                                $item_num = DB::connection("sqlSrvBCKI")
                                            ->table('qc_opname_dtl')
                                            ->select('item_num')
                                            ->where('tr_id', $transId)
                                            ->where('spk_id', $request->spk_id)
                                            ->where('qc_trans_id', $request->qc_trans_id)
                                            ->max('item_num');

                                DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->insert([
                                    'tr_id' => $transId,
                                    'spk_id' => trim($request->spk_id),
                                    'qc_trans_id' => trim($request->qc_trans_id),
                                    'item_num' => $item_num + 1,
                                    'com_id' => $request->com_id,
                                    'type' => 3,
                                    'description' => 'Sudah di periksa oleh QC dan ditambahkan total simpanan retensi 5%',
                                    'amt' => $amt,
                                    'amt_retensi' => $amt_retensi,
                                    'amt_final' => $total_final,
                                    'dt_created' => now(),
                                    'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                ]);

                                $this->updateOpnameHdr($transId, trim($request->spk_id), trim($request->qc_trans_id), $request);

                            }
                        }
                    }
                    
                }
                catch (\Exception $e) {
                    throw new \Exception($e->getMessage());
                }
            }
        } elseif (trim($request->spk_type) == 'T') { 
            if ($id_qc_from == 'F250711001') {
                // Form SPK Tambahan 1 (dgn pembayaran 100%)
                if (($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0) && ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0)) {
                    try {
                        $opname_hdr = DB::connection('sqlSrvBCKI')
                        ->table('qc_opname')
                        ->whereDate('tr_date', $closed_date)
                        ->where('spk_id', trim($request->spk_id))
                        ->where('qc_trans_id', trim($request->qc_trans_id))
                        ->first();

                        if ($opname_hdr) {
                            $transId = $opname_hdr->tr_id;
                        } else {
                            $transId = $this->generateID($request);
                            DB::connection('sqlSrvBCKI')
                            ->table('qc_opname')
                            ->insert([
                                'tr_id' => $transId,
                                'spk_id' => trim($request->spk_id),
                                'qc_trans_id' => trim($request->qc_trans_id),
                                'tr_date' => now(),
                                'description' => $request->description ? $request->description : ' ',
                                'dt_created' => now(),
                                'created_by' => $request->user_id2 ? $request->user_id2 : ' ',
                                'closed_date' => $closed_date
                            ]);
                        }

                        $total_original = $request->amt;

                        if ((int) $request->com_id != 8) { 
                            if (($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) && ($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0)) {
                                $amt = 1 * $total_original;
                                $amt_retensi = 0;
                                $total_final = $amt - $amt_retensi;

                                $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->where('spk_id', $request->spk_id)
                                ->where('qc_trans_id', $request->qc_trans_id)
                                ->where('com_id', $request->com_id)
                                ->where('type', 3)
                                ->first();

                                if (!$qc_opname_dtl) {

                                    $item_num = DB::connection("sqlSrvBCKI")
                                                ->table('qc_opname_dtl')
                                                ->select('item_num')
                                                ->where('tr_id', $transId)
                                                ->where('spk_id', $request->spk_id)
                                                ->where('qc_trans_id', $request->qc_trans_id)
                                                ->max('item_num');

                                    DB::connection('sqlSrvBCKI')
                                    ->table('qc_opname_dtl')
                                    ->insert([
                                        'tr_id' => $transId,
                                        'spk_id' => trim($request->spk_id),
                                        'qc_trans_id' => trim($request->qc_trans_id),
                                        'item_num' => $item_num + 1,
                                        'com_id' => $request->com_id,
                                        'type' => 3,
                                        'description' => 'Sudah di periksa oleh QC',
                                        'amt' => $amt,
                                        'amt_retensi' => $amt_retensi,
                                        'amt_final' => $total_final,
                                        'dt_created' => now(),
                                        'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                    ]);
                                }
                            }

                            $this->updateOpnameHdr($transId, trim($request->spk_id), trim($request->qc_trans_id), $request);
                        } 

                    }
                    catch (\Exception $e) {
                        throw new \Exception($e->getMessage());
                    }
                }
            } elseif ($id_qc_from == 'F250711002') {
                // Form SPK Tambahan 2 (dgn pembayaran 95% lalu 5%)
                if (($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0) && ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0)) {
                    try {
                        $opname_hdr = DB::connection('sqlSrvBCKI')
                        ->table('qc_opname')
                        ->whereDate('tr_date', $closed_date)
                        ->where('spk_id', trim($request->spk_id))
                        ->where('qc_trans_id', trim($request->qc_trans_id))
                        ->first();

                        if ($opname_hdr) {
                            $transId = $opname_hdr->tr_id;
                        } else {
                            $transId = $this->generateID($request);
                            DB::connection('sqlSrvBCKI')
                            ->table('qc_opname')
                            ->insert([
                                'tr_id' => $transId,
                                'spk_id' => trim($request->spk_id),
                                'qc_trans_id' => trim($request->qc_trans_id),
                                'tr_date' => now(),
                                'description' => $request->description ? $request->description : ' ',
                                'dt_created' => now(),
                                'created_by' => $request->user_id2 ? $request->user_id2 : ' ',
                                'closed_date' => $closed_date
                            ]);
                        }

                        $total_original = $request->amt;

                        if ((int) $request->com_id != 8) { 
                            if (($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) && ($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0)) {
                                $amt = 1 * $total_original;
                                $amt_retensi = 0.05 * $amt;
                                $total_final = $amt - $amt_retensi;

                                $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->where('spk_id', $request->spk_id)
                                ->where('qc_trans_id', $request->qc_trans_id)
                                ->where('com_id', $request->com_id)
                                ->where('type', 3)
                                ->first();

                                if (!$qc_opname_dtl) {

                                    $item_num = DB::connection("sqlSrvBCKI")
                                                ->table('qc_opname_dtl')
                                                ->select('item_num')
                                                ->where('tr_id', $transId)
                                                ->where('spk_id', $request->spk_id)
                                                ->where('qc_trans_id', $request->qc_trans_id)
                                                ->max('item_num');

                                    DB::connection('sqlSrvBCKI')
                                    ->table('qc_opname_dtl')
                                    ->insert([
                                        'tr_id' => $transId,
                                        'spk_id' => trim($request->spk_id),
                                        'qc_trans_id' => trim($request->qc_trans_id),
                                        'item_num' => $item_num + 1,
                                        'com_id' => $request->com_id,
                                        'type' => 3,
                                        'description' => 'Sudah di periksa oleh QC',
                                        'amt' => $amt,
                                        'amt_retensi' => $amt_retensi,
                                        'amt_final' => $total_final,
                                        'dt_created' => now(),
                                        'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                    ]);
                                }
                            }

                            $this->updateOpnameHdr($transId, trim($request->spk_id), trim($request->qc_trans_id), $request);
                        } else {
                            if ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) {
                                $amt = 1 * $total_original;
                                $amt_retensi = 0;
                                $total_qc = $amt - $amt_retensi;

                                $sum_retensi = DB::connection('sqlSrvBCKI')->selectOne("
                                    SELECT 
                                        SUM(amt_retensi) as amt_retensi
                                    FROM qc_opname_dtl WITH (NOLOCK)
                                    WHERE spk_id = ?  AND qc_trans_id = ?
                                ", [trim($request->spk_id), trim($request->qc_trans_id)]);

                                $total_retensi = $sum_retensi ? ($sum_retensi->amt_retensi ? $sum_retensi->amt_retensi : 0) : 0;
                                $total_final = $total_qc + $total_retensi;

                                $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->where('spk_id', $request->spk_id)
                                ->where('qc_trans_id', $request->qc_trans_id)
                                ->where('com_id', $request->com_id)
                                ->where('type', 3)
                                ->first();

                                if (!$qc_opname_dtl) {

                                    $item_num = DB::connection("sqlSrvBCKI")
                                                ->table('qc_opname_dtl')
                                                ->select('item_num')
                                                ->where('tr_id', $transId)
                                                ->where('spk_id', $request->spk_id)
                                                ->where('qc_trans_id', $request->qc_trans_id)
                                                ->max('item_num');

                                    DB::connection('sqlSrvBCKI')
                                    ->table('qc_opname_dtl')
                                    ->insert([
                                        'tr_id' => $transId,
                                        'spk_id' => trim($request->spk_id),
                                        'qc_trans_id' => trim($request->qc_trans_id),
                                        'item_num' => $item_num + 1,
                                        'com_id' => $request->com_id,
                                        'type' => 3,
                                        'description' => 'Sudah di periksa oleh QC dan ditambahkan total simpanan retensi 5%',
                                        'amt' => $amt,
                                        'amt_retensi' => $amt_retensi,
                                        'amt_final' => $total_final,
                                        'dt_created' => now(),
                                        'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                    ]);

                                    $this->updateOpnameHdr($transId, trim($request->spk_id), trim($request->qc_trans_id), $request);

                                }
                            }
                        }

                    }
                    catch (\Exception $e) {
                        throw new \Exception($e->getMessage());
                    }
                }
            } elseif ($id_qc_from == 'F250711003') {
                // Form SPK Tambahan 3 (dgn pembayaran 95% (75% dan 25%) lalu 5%)
                if (($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0) || ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0)) {
                    try {
                        $opname_hdr = DB::connection('sqlSrvBCKI')
                        ->table('qc_opname')
                        ->whereDate('tr_date', $closed_date)
                        ->where('spk_id', trim($request->spk_id))
                        ->where('qc_trans_id', trim($request->qc_trans_id))
                        ->first();

                        if ($opname_hdr) {
                            $transId = $opname_hdr->tr_id;
                        } else {
                            $transId = $this->generateID($request);
                            DB::connection('sqlSrvBCKI')
                            ->table('qc_opname')
                            ->insert([
                                'tr_id' => $transId,
                                'spk_id' => trim($request->spk_id),
                                'qc_trans_id' => trim($request->qc_trans_id),
                                'tr_date' => now(),
                                'description' => $request->description ? $request->description : ' ',
                                'dt_created' => now(),
                                'created_by' => $request->user_id2 ? $request->user_id2 : ' ',
                                'closed_date' => $closed_date
                            ]);
                        }

                        $total_original = $request->amt;

                        if ((int) $request->com_id != 8) { 
                            if ($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0) {
                                $amt = 0.75 * $total_original;
                                $amt_retensi = 0.05 * $amt;
                                $total_final = $amt - $amt_retensi;
                                
                                $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->where('spk_id', $request->spk_id)
                                ->where('qc_trans_id', $request->qc_trans_id)
                                ->where('com_id', $request->com_id)
                                ->where('type', 2)
                                ->first();

                                if (!$qc_opname_dtl) {
                                    $item_num = DB::connection("sqlSrvBCKI")->table('qc_opname_dtl')
                                                ->select('item_num')
                                                ->where('tr_id', $transId)
                                                ->where('spk_id', $request->spk_id)
                                                ->where('qc_trans_id', $request->qc_trans_id)
                                                ->max('item_num');

                                    DB::connection('sqlSrvBCKI')
                                    ->table('qc_opname_dtl')
                                    ->insert([
                                        'tr_id' => $transId,
                                        'spk_id' => trim($request->spk_id),
                                        'qc_trans_id' => trim($request->qc_trans_id),
                                        'item_num' => $item_num + 1,
                                        'com_id' => $request->com_id,
                                        'type' => 2,
                                        'description' => 'Sudah di periksa oleh pemeriksa',
                                        'amt' => $amt,
                                        'amt_retensi' => $amt_retensi,
                                        'amt_final' => $total_final,
                                        'dt_created' => now(),
                                        'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                    ]);
                                }                   

                            }

                            if ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) {
                                $amt = 0.25 * $total_original;
                                $amt_retensi = 0.05 * $amt;
                                $total_final = $amt - $amt_retensi;

                                $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->where('spk_id', $request->spk_id)
                                ->where('qc_trans_id', $request->qc_trans_id)
                                ->where('com_id', $request->com_id)
                                ->where('type', 3)
                                ->first();

                                if (!$qc_opname_dtl) {

                                    $item_num = DB::connection("sqlSrvBCKI")
                                                ->table('qc_opname_dtl')
                                                ->select('item_num')
                                                ->where('tr_id', $transId)
                                                ->where('spk_id', $request->spk_id)
                                                ->where('qc_trans_id', $request->qc_trans_id)
                                                ->max('item_num');

                                    DB::connection('sqlSrvBCKI')
                                    ->table('qc_opname_dtl')
                                    ->insert([
                                        'tr_id' => $transId,
                                        'spk_id' => trim($request->spk_id),
                                        'qc_trans_id' => trim($request->qc_trans_id),
                                        'item_num' => $item_num + 1,
                                        'com_id' => $request->com_id,
                                        'type' => 3,
                                        'description' => 'Sudah di periksa oleh QC',
                                        'amt' => $amt,
                                        'amt_retensi' => $amt_retensi,
                                        'amt_final' => $total_final,
                                        'dt_created' => now(),
                                        'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                    ]);
                                }
                            }

                            $this->updateOpnameHdr($transId, trim($request->spk_id), trim($request->qc_trans_id), $request);
                        } else {
                            if ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) {
                                $amt = 1 * $total_original;
                                $amt_retensi = 0;
                                $total_qc = $amt - $amt_retensi;

                                $sum_retensi = DB::connection('sqlSrvBCKI')->selectOne("
                                    SELECT 
                                        SUM(amt_retensi) as amt_retensi
                                    FROM qc_opname_dtl WITH (NOLOCK)
                                    WHERE spk_id = ?  AND qc_trans_id = ?
                                ", [trim($request->spk_id), trim($request->qc_trans_id)]);

                                $total_retensi = $sum_retensi ? ($sum_retensi->amt_retensi ? $sum_retensi->amt_retensi : 0) : 0;
                                $total_final = $total_qc + $total_retensi;

                                $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->where('spk_id', $request->spk_id)
                                ->where('qc_trans_id', $request->qc_trans_id)
                                ->where('com_id', $request->com_id)
                                ->where('type', 3)
                                ->first();

                                if (!$qc_opname_dtl) {

                                    $item_num = DB::connection("sqlSrvBCKI")
                                                ->table('qc_opname_dtl')
                                                ->select('item_num')
                                                ->where('tr_id', $transId)
                                                ->where('spk_id', $request->spk_id)
                                                ->where('qc_trans_id', $request->qc_trans_id)
                                                ->max('item_num');

                                    DB::connection('sqlSrvBCKI')
                                    ->table('qc_opname_dtl')
                                    ->insert([
                                        'tr_id' => $transId,
                                        'spk_id' => trim($request->spk_id),
                                        'qc_trans_id' => trim($request->qc_trans_id),
                                        'item_num' => $item_num + 1,
                                        'com_id' => $request->com_id,
                                        'type' => 3,
                                        'description' => 'Sudah di periksa oleh QC dan ditambahkan total simpanan retensi 5%',
                                        'amt' => $amt,
                                        'amt_retensi' => $amt_retensi,
                                        'amt_final' => $total_final,
                                        'dt_created' => now(),
                                        'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                    ]);

                                    $this->updateOpnameHdr($transId, trim($request->spk_id), trim($request->qc_trans_id), $request);

                                }
                            }
                        }

                    }
                    catch (\Exception $e) {
                        throw new \Exception($e->getMessage());
                    }
                }
            } elseif ($id_qc_from == 'F260101007') {
                // Form SPK Tambahan 4 (dgn pembayaran 40% lalu 60%) tanpa retensi)
                if ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) {
                    try {

                        $opname_hdr = DB::connection('sqlSrvBCKI')
                        ->table('qc_opname')
                        ->whereDate('tr_date', $closed_date)
                        ->where('spk_id', trim($request->spk_id))
                        ->where('qc_trans_id', trim($request->qc_trans_id))
                        ->first();

                        if ($opname_hdr) {
                            $transId = $opname_hdr->tr_id;
                        } else {
                        $transId = $this->generateID($request);
                            DB::connection('sqlSrvBCKI')
                            ->table('qc_opname')
                            ->insert([
                                'tr_id' => $transId,
                                'spk_id' => trim($request->spk_id),
                                'qc_trans_id' => trim($request->qc_trans_id),
                                'tr_date' => now(),
                                'description' => $request->description ? $request->description : ' ',
                                'dt_created' => now(),
                                'created_by' => $request->user_id2 ? $request->user_id2 : ' ',
                                'closed_date' => $closed_date
                            ]);
                        }

                        $total_original = 0;
                        $last_percent = 0;

                        foreach ($request->commodities as $com) {
                            $com = (object) $com;

                            if ($com->com_id == $request->com_id) {
                                // $total_original = ($com->percent - $last_percent) * $request->amt;
                                $total_original = $com->percent * $request->amt;
                                break;
                            }

                            // $last_percent = $com->percent;
                        }                        

                        if ((int) $request->com_id != 8) { 

                            if ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) {
                                $amt = 1 * $total_original;
                                $amt_retensi = 0;
                                $total_final = $amt - $amt_retensi;

                                $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->where('spk_id', $request->spk_id)
                                ->where('qc_trans_id', $request->qc_trans_id)
                                ->where('com_id', $request->com_id)
                                ->where('type', 3)
                                ->first();

                                if (!$qc_opname_dtl) {

                                    $item_num = DB::connection("sqlSrvBCKI")
                                                ->table('qc_opname_dtl')
                                                ->select('item_num')
                                                ->where('tr_id', $transId)
                                                ->where('spk_id', $request->spk_id)
                                                ->where('qc_trans_id', $request->qc_trans_id)
                                                ->max('item_num');

                                    DB::connection('sqlSrvBCKI')
                                    ->table('qc_opname_dtl')
                                    ->insert([
                                        'tr_id' => $transId,
                                        'spk_id' => trim($request->spk_id),
                                        'qc_trans_id' => trim($request->qc_trans_id),
                                        'item_num' => $item_num + 1,
                                        'com_id' => $request->com_id,
                                        'type' => 3,
                                        'description' => 'Sudah di periksa oleh QC',
                                        'amt' => $amt,
                                        'amt_retensi' => $amt_retensi,
                                        'amt_final' => $total_final,
                                        'dt_created' => now(),
                                        'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                    ]);
                                }
                            }

                            $this->updateOpnameHdr($transId, trim($request->spk_id), trim($request->qc_trans_id), $request);
                        }
                        
                    }
                    catch (\Exception $e) {
                        throw new \Exception($e->getMessage());
                    }
                }
            }
        } else {
            if (($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0) || ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0)) {

                try {

                    $opname_hdr = DB::connection('sqlSrvBCKI')
                    ->table('qc_opname')
                    ->whereDate('tr_date', $closed_date)
                    ->where('spk_id', trim($request->spk_id))
                    ->where('qc_trans_id', trim($request->qc_trans_id))
                    ->first();

                    if ($opname_hdr) {
                        $transId = $opname_hdr->tr_id;
                    } else {
                        $transId = $this->generateID($request);
                        DB::connection('sqlSrvBCKI')
                        ->table('qc_opname')
                        ->insert([
                            'tr_id' => $transId,
                            'spk_id' => trim($request->spk_id),
                            'qc_trans_id' => trim($request->qc_trans_id),
                            'tr_date' => now(),
                            'description' => $request->description ? $request->description : ' ',
                            'dt_created' => now(),
                            'created_by' => $request->user_id2 ? $request->user_id2 : ' ',
                            'closed_date' => $closed_date
                        ]);
                    }

                    $total_original = 0;
                    $last_percent = 0;

                    foreach ($request->commodities as $com) {
                        $com = (object) $com;

                        // SKIP RETENSI
                        if ((int) $com->com_id == 8) {
                            continue;
                        }

                        if ($com->com_id == $request->com_id) {
                            $percent = (int) $com->com_id == 3 ? $com->percent + 0.05 : $com->percent;
                            $total_original = ($percent - $last_percent) * $request->amt;
                            break;
                        }

                        $last_percent = $com->percent;
                    }                        
                    if ((int) $request->com_id != 8) { // cek apakah commodity ikut retensi 5% apa tidak, jika tidak then
                        if ($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0) {
                            $amt = 0.75 * $total_original;
                            $amt_retensi = 0.05 * $amt;
                            $total_final = $amt - $amt_retensi;
                            
                            $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                            ->table('qc_opname_dtl')
                            ->where('spk_id', $request->spk_id)
                            ->where('qc_trans_id', $request->qc_trans_id)
                            ->where('com_id', $request->com_id)
                            ->where('type', 2)
                            ->first();

                            if (!$qc_opname_dtl) {
                                $item_num = DB::connection("sqlSrvBCKI")->table('qc_opname_dtl')
                                            ->select('item_num')
                                            ->where('tr_id', $transId)
                                            ->where('spk_id', $request->spk_id)
                                            ->where('qc_trans_id', $request->qc_trans_id)
                                            ->max('item_num');

                                DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->insert([
                                    'tr_id' => $transId,
                                    'spk_id' => trim($request->spk_id),
                                    'qc_trans_id' => trim($request->qc_trans_id),
                                    'item_num' => $item_num + 1,
                                    'com_id' => $request->com_id,
                                    'type' => 2,
                                    'description' => 'Sudah di periksa oleh pemeriksa',
                                    'amt' => $amt,
                                    'amt_retensi' => $amt_retensi,
                                    'amt_final' => $total_final,
                                    'dt_created' => now(),
                                    'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                ]);
                            }                   

                        }

                        if ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) {
                            $amt = 0.25 * $total_original;
                            $amt_retensi = 0.05 * $amt;
                            $total_final = $amt - $amt_retensi;

                            $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                            ->table('qc_opname_dtl')
                            ->where('spk_id', $request->spk_id)
                            ->where('qc_trans_id', $request->qc_trans_id)
                            ->where('com_id', $request->com_id)
                            ->where('type', 3)
                            ->first();

                            if (!$qc_opname_dtl) {

                                $item_num = DB::connection("sqlSrvBCKI")
                                            ->table('qc_opname_dtl')
                                            ->select('item_num')
                                            ->where('tr_id', $transId)
                                            ->where('spk_id', $request->spk_id)
                                            ->where('qc_trans_id', $request->qc_trans_id)
                                            ->max('item_num');

                                DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->insert([
                                    'tr_id' => $transId,
                                    'spk_id' => trim($request->spk_id),
                                    'qc_trans_id' => trim($request->qc_trans_id),
                                    'item_num' => $item_num + 1,
                                    'com_id' => $request->com_id,
                                    'type' => 3,
                                    'description' => 'Sudah di periksa oleh QC',
                                    'amt' => $amt,
                                    'amt_retensi' => $amt_retensi,
                                    'amt_final' => $total_final,
                                    'dt_created' => now(),
                                    'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                ]);
                            }
                        }

                        $this->updateOpnameHdr($transId, trim($request->spk_id), trim($request->qc_trans_id), $request);
                    } else {
                        if ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) {
                            $amt = 1 * $total_original;
                            $amt_retensi = 0;
                            $total_qc = $amt - $amt_retensi;

                            $sum_retensi = DB::connection('sqlSrvBCKI')->selectOne("
                                SELECT 
                                    SUM(amt_retensi) as amt_retensi
                                FROM qc_opname_dtl WITH (NOLOCK)
                                WHERE spk_id = ?  AND qc_trans_id = ?
                            ", [trim($request->spk_id), trim($request->qc_trans_id)]);

                            $total_retensi = $sum_retensi ? ($sum_retensi->amt_retensi ? $sum_retensi->amt_retensi : 0) : 0;
                            $total_final = $total_qc + $total_retensi;

                            $qc_opname_dtl = DB::connection('sqlSrvBCKI')
                            ->table('qc_opname_dtl')
                            ->where('spk_id', $request->spk_id)
                            ->where('qc_trans_id', $request->qc_trans_id)
                            ->where('com_id', $request->com_id)
                            ->where('type', 3)
                            ->first();

                            if (!$qc_opname_dtl) {

                                $item_num = DB::connection("sqlSrvBCKI")
                                            ->table('qc_opname_dtl')
                                            ->select('item_num')
                                            ->where('tr_id', $transId)
                                            ->where('spk_id', $request->spk_id)
                                            ->where('qc_trans_id', $request->qc_trans_id)
                                            ->max('item_num');

                                DB::connection('sqlSrvBCKI')
                                ->table('qc_opname_dtl')
                                ->insert([
                                    'tr_id' => $transId,
                                    'spk_id' => trim($request->spk_id),
                                    'qc_trans_id' => trim($request->qc_trans_id),
                                    'item_num' => $item_num + 1,
                                    'com_id' => $request->com_id,
                                    'type' => 3,
                                    'description' => 'Sudah di periksa oleh QC dan ditambahkan total simpanan retensi 5%',
                                    'amt' => $amt,
                                    'amt_retensi' => $amt_retensi,
                                    'amt_final' => $total_final,
                                    'dt_created' => now(),
                                    'created_by' => $request->user_id2 ? $request->user_id2 : ''
                                ]);

                                $this->updateOpnameHdr($transId, trim($request->spk_id), trim($request->qc_trans_id), $request);

                            }
                        }
                    }
                    
                }
                catch (\Exception $e) {
                    throw new \Exception($e->getMessage());
                }
            }
        }
    }

    private function updateOpnameHdr($transId, $spk_id, $qc_trans_id, $request){  

        $sum_amt = DB::connection('sqlSrvBCKI')->selectOne("
                        SELECT 
                            SUM(amt) as amt,
                            SUM(amt_retensi) as amt_retensi,
                            SUM(amt_final) as amt_final
                        FROM qc_opname_dtl WITH (NOLOCK)
                        WHERE tr_id = ?  AND spk_id = ? AND qc_trans_id = ?
                    ", [trim($transId), trim($spk_id), trim($qc_trans_id)]);

        $amt = $sum_amt ? ($sum_amt->amt ? $sum_amt->amt : 0) : 0;
        $amt_retensi = $sum_amt ? ($sum_amt->amt_retensi ? $sum_amt->amt_retensi : 0) : 0;
        $amt_final = $sum_amt ? ($sum_amt->amt_final ? $sum_amt->amt_final : 0) : 0;

        $terbilang = terbilang($amt_final);

        DB::connection('sqlSrvBCKI')->update(
            "UPDATE qc_opname
             SET amt = ?, amt_retensi = ?, amt_total = ?, terbilang = ? 
             WHERE tr_id = ?  AND spk_id = ? AND qc_trans_id = ?", 
            [$amt, $amt_retensi, $amt_final, $terbilang, trim($transId), trim($spk_id), trim($qc_trans_id)]
        );   

        DB::connection('sqlSrvBCKI')->update(
            "UPDATE qc_closing_weekly
             SET opname_id = ?
             WHERE spk_id = ? AND qc_trans_id = ?  AND com_id = ? AND opname_id = ' '",
            [trim($transId), trim($request->spk_id), trim($request->qc_trans_id), $request->com_id]
        ); 
    }

    private function generateID($request)
    {
        $dateYear = substr( date("Y"), -2);
        $dateMonth = date("m");
        $dateDay = date("d");
        $gen_tr_id = '';

        $getLastTrID =  DB::connection('sqlSrvBCKI')
                        ->table('qc_opname')
                        ->where('tr_id', 'like', "%".'9'.$dateYear.$dateMonth.$dateDay."%")
                        ->max('tr_id');

        if($getLastTrID){
            $seq = substr( trim($getLastTrID), -4) + 1;
            $gen_tr_id = '9'.$dateYear.$dateMonth.$dateDay.str_pad($seq, 4, '0', STR_PAD_LEFT);
        } else {
            $gen_tr_id = '9'.$dateYear.$dateMonth.$dateDay.'0001';
        }

        return $gen_tr_id;
    }
}
