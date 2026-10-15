<?php

namespace App\Http\Controllers\transaction;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OpnamePekerjaanController extends Controller
{
    public function index(Request $request)
    {
        $sqlWhere = "1=1";
        $id_site = $request->id_site;
        $id_cluster = $request->id_cluster;
        $status = $request->status;
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

        if ($id_site) {
    
            $sqlWhere.= " and site.id_site = '$id_site'";
        }

        if ($id_cluster) {
    
            $sqlWhere.= " and site_cluster.id_cluster = '$id_cluster'";
        }

        if (isset($status)) {
            $status = trim($status);
            if ($status === 'Y') {
                $sqlWhere .= " AND qc_opname.dt_confirmed <> '1900-01-01 00:00:00.000'";
            } elseif ($status === 'N') {
                $sqlWhere .= " AND qc_opname.dt_confirmed = '1900-01-01 00:00:00.000'";
            }
        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and qc_opname.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00.001') AND CONVERT(datetime,'" . $end_date . " 23:59:59.000')";
            }
            else
            {
                $sqlWhere .= " and qc_opname.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00.001') AND CONVERT(datetime,'" . $start_date . " 23:59:59.000')";
            }
        }

        // $datas = DB::connection('sqlSrvBCKI')->select(DB::raw("
        //     SELECT
        //         site.id_site,
        //         site.site_name + ' ' + site.site_address AS site_name,
        //         site_cluster.id_cluster,
        //         site_cluster.cluster_name,
        //         house_unit.house_name,
        //         house_unit.id_house,
        //         vendor.vendor_name,
        //         TRIM(vendor.vendor_id) as vendor_id,
        //         appl_constant.str2 as spk_type,
        //         appl_constant.str1 as str1_spk_type,
        //         qc_opname.*,
        //         spk_hdr.amt as total_spk
        //     FROM
        //         qc_opname AS qc_opname
        //         INNER JOIN qc_trans_hdr AS qhdr ON qhdr.qc_trans_id = qc_opname.qc_trans_id
        //         INNER JOIN house_unit AS house_unit ON qhdr.id_house = house_unit.id_house
        //         INNER JOIN site_cluster AS site_cluster ON house_unit.id_cluster = site_cluster.id_cluster
        //         INNER JOIN site AS site ON house_unit.id_site = site.id_site
        //         INNER JOIN spk_hdr AS spk_hdr ON spk_hdr.id_spk = qc_opname.spk_id
        //         LEFT OUTER JOIN appl_constant appl_constant ON appl_constant.str1 = spk_hdr.spk_type AND appl_constant.key_code = 'SPK_TYPE'
        //         LEFT OUTER JOIN vendor AS vendor ON vendor.vendor_id = spk_hdr.pihak_2 
        //     WHERE $sqlWhere
        //     AND EXISTS (
        //         SELECT 1
        //         FROM qc_opname_dtl
        //         WHERE qc_opname_dtl.tr_id = qc_opname.tr_id AND qc_opname_dtl.spk_id = qc_opname.spk_id AND qc_opname_dtl.qc_trans_id = qc_opname.qc_trans_id
        //     )
        //     ORDER BY qc_opname.closed_date ASC

        // "));

        $datas = DB::connection('sqlSrvBCKI')->select(DB::raw("
                SELECT
                    site.id_site,
                    site.site_name + ' ' + site.site_address AS site_name,
                    site_cluster.id_cluster,
                    site_cluster.cluster_name,
                    house_unit.house_name,
                    house_unit.id_house,
                    vendor.vendor_name,
                    TRIM(vendor.vendor_id) as vendor_id,
                    appl_constant.str2 as spk_type,
                    appl_constant.str1 as str1_spk_type,
                    qc_opname.*,
                    spk_hdr.amt as total_spk,
                    dtl.com_id,
                    dtl.type,
                    qhdr.id_qc_form,
                    purc_inv_mast.tr_id as tr_id_inv,
                    purc_inv_mast.inv_id
                FROM qc_opname
                INNER JOIN qc_trans_hdr AS qhdr 
                    ON qhdr.qc_trans_id = qc_opname.qc_trans_id
                INNER JOIN house_unit 
                    ON qhdr.id_house = house_unit.id_house
                INNER JOIN site_cluster 
                    ON house_unit.id_cluster = site_cluster.id_cluster
                INNER JOIN site 
                    ON house_unit.id_site = site.id_site
                INNER JOIN spk_hdr 
                    ON spk_hdr.id_spk = qc_opname.spk_id

                OUTER APPLY (
                    SELECT TOP 1 com_id, type
                    FROM qc_opname_dtl
                    WHERE tr_id = qc_opname.tr_id
                    AND spk_id = qc_opname.spk_id
                    AND qc_trans_id = qc_opname.qc_trans_id
                ) dtl

                LEFT JOIN appl_constant 
                    ON appl_constant.str1 = spk_hdr.spk_type 
                    AND appl_constant.key_code = 'SPK_TYPE'
                LEFT JOIN vendor 
                    ON vendor.vendor_id = spk_hdr.pihak_2
                LEFT JOIN purc_inv_mast ON purc_inv_mast.reff_id = qc_opname.tr_id
                WHERE $sqlWhere
                ORDER BY qc_opname.closed_date ASC

        "));

        return $this->sendResponse($datas, 'Successfully get Delivery');
    }

    public function indexDistinct(Request $request)
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
                site.site_name + ' ' + site.site_address AS site_name,
                site_cluster.id_cluster,
                site_cluster.cluster_name,
                house_unit.house_name,
                vendor.vendor_name,
                qhdr.doc_type,
                qhdr.doc_id AS spk_id,
                qtl.qc_trans_id,
                appl_constant.str2 as spk_type,
                spk_hdr.amt
            FROM
                qc_closing_weekly AS qtl
                INNER JOIN qc_trans_hdr AS qhdr ON qhdr.qc_trans_id = qtl.qc_trans_id
                INNER JOIN qc_item AS qc_item ON qc_item.id_qc_item = qtl.qc_item_id
                INNER JOIN qc_item_commodity AS commodity ON qc_item.com_id = commodity.com_id
                INNER JOIN house_unit AS house_unit ON qhdr.id_house = house_unit.id_house
                INNER JOIN site_cluster AS site_cluster ON house_unit.id_cluster = site_cluster.id_cluster
                INNER JOIN site AS site ON house_unit.id_site = site.id_site
                INNER JOIN spk_hdr AS spk_hdr ON spk_hdr.id_spk = qhdr.doc_id
                LEFT OUTER JOIN appl_constant appl_constant ON appl_constant.str1 = spk_hdr.spk_type AND appl_constant.key_code = 'SPK_TYPE'
                LEFT OUTER JOIN vendor AS vendor ON vendor.vendor_id = spk_hdr.pihak_2 
            WHERE $sqlWhere AND qtl.opname_id = ' ' AND ( check2 = 'Y' OR check3 = 'Y')
            GROUP BY 
            site.id_site,
            site.site_name + ' ' + site.site_address,
            site_cluster.id_cluster,
            site_cluster.cluster_name,
            house_unit.house_name,
            vendor.vendor_name,
            qhdr.doc_type,
            qhdr.doc_id,
            qtl.qc_trans_id,
            appl_constant.str2,
            spk_hdr.amt
        "));

        return $this->sendResponse($datas, 'Successfully get Delivery');

    }

    public function showTransaction(Request $request)
    {
        $sqlWhere = "1=1";
        $id_site = $request->id_site;
        $id_cluster = $request->id_cluster;
        $spk_id = $request->spk_id;
        $com_id = $request->com_id;

        if ($id_site) {
    
            $sqlWhere.= " and site.id_site = '$id_site'";
        }

        if ($id_cluster) {
    
            $sqlWhere.= " and site_cluster.id_cluster = '$id_cluster'";
        }

        if ($spk_id) {
    
            $sqlWhere.= " and qhdr.doc_id = '$spk_id'";
        }

        if ($com_id) {
    
            $sqlWhere.= " and commodity.com_id = $com_id";
        }

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
                    qtl.qc_trans_id,
                    qtl.id_qc_item,
                    qc_item.qc_item,
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
                    LEFT OUTER JOIN vendor AS vendor ON vendor.vendor_id = spk_hdr.pihak_2 
                WHERE $sqlWhere AND qhdr.doc_type = 'SPK'
                ORDER BY
                    qtl.qc_trans_id,
                    qtl.id_qc_item
        "));

        return $this->sendResponse($datas, 'Successfully get opname');

    }

    public function store(Request $request)
    {
        $spk_hdr = DB::connection('sqlSrvBCKI')->selectOne("
            SELECT * FROM spk_hdr WITH (NOLOCK)
            WHERE id_spk = ?
        ", [trim($request->spk_id)]);

        if ($spk_hdr) {
            $sum_closing = DB::connection('sqlSrvBCKI')->selectOne("
                        SELECT 
                            SUM(CASE WHEN check2 = 'Y' THEN 1 ELSE 0 END) AS total_checked,
                            SUM(CASE WHEN check3 = 'Y' THEN 1 ELSE 0 END) AS total_qc
                        FROM qc_closing_weekly WITH (NOLOCK)
                        WHERE spk_id = ? AND qc_trans_id = ?  AND com_id = ? AND opname_id = ' '
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

            if ($sum_closing) {
                if ($sum_trans_dtl) {
                    $qc_trans_id = trim($request->qc_trans_id);
                    $closing_total_checked = $sum_closing->total_checked;
                    $closing_total_qc = $sum_closing->total_qc;

                    $trans_total_checked = $sum_trans_dtl->total_checked;
                    $trans_total_qc = $sum_trans_dtl->total_qc;

                    if (!empty($request->tr_id)){
                        $transId = $request->tr_id;
                    } else {
                        $transId = $this->generateID($request);
                    }
                    
                    if ($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0) {
                        $cek2 = DB::connection("sqlSrvBCKI")->table('qc_opname_dtl')->where('tr_id', $transId)->where('spk_id', $request->spk_id)->where('qc_trans_id', $request->qc_trans_id)->where('com_id', $request->com_id)->where('type', 2)->first();
                        if($cek2) {
                            return $this->sendError('Commodity dan yang sudah di cek PC sudah ada', 'Error', 500);
                        }
                    }

                    if ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) {
                        $cek3 = DB::connection("sqlSrvBCKI")->table('qc_opname_dtl')->where('tr_id', $transId)->where('spk_id', $request->spk_id)->where('qc_trans_id', $request->qc_trans_id)->where('com_id', $request->com_id)->where('type', 3)->first();
                        if($cek3) {
                            return $this->sendError('Commodity dan yang sudah di cek QC PC sudah ada', 'Error', 500);
                        }
                    }

                    DB::connection('sqlSrvBCKI')->beginTransaction();

                    try {

                        if ($request->tr_id){
                            DB::connection('sqlSrvBCKI')
                            ->table('qc_opname')
                            ->where('tr_id', $transId)
                            ->where('spk_id', $request->spk_id)
                            ->where('qc_trans_id', $request->qc_trans_id)
                            ->update([
                                'description' => $request->description
                            ]);
                        } else {
                            DB::connection('sqlSrvBCKI')
                            ->table('qc_opname')
                            ->insert([
                                'tr_id' => $transId,
                                'spk_id' => trim($request->spk_id),
                                'qc_trans_id' => trim($request->qc_trans_id),
                                'tr_date' => now(),
                                'description' => $request->description,
                                'dt_created' => now(),
                                'created_by' => $request->user_id2 ? $request->user_id2 : ''
                            ]);
                        }                        

                        $total_original = 0;
                        $last_percent = 0;

                        foreach ($request->commodities as $com) {
                            $com = (object) $com;

                            if ($com->com_id == $request->com_id) {
                                $total_original = ($com->percent - $last_percent) * $spk_hdr->amt;
                                break;
                            }

                            $last_percent = $com->percent;
                        }                        

                        if ($closing_total_checked >= $trans_total_checked && $closing_total_checked > 0) {
                            $total_final = 0.75 * $total_original;
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
                                'amt' => $total_final,
                                'dt_created' => now(),
                                'created_by' => $request->user_id2 ? $request->user_id2 : ''
                            ]);

                        }

                        if ($closing_total_qc >= $trans_total_qc && $closing_total_qc > 0) {
                            $total_final = 0.25 * $total_original;
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
                                'amt' => $total_final,
                                'dt_created' => now(),
                                'created_by' => $request->user_id2 ? $request->user_id2 : ''
                            ]);
                        }

                        DB::connection('sqlSrvBCKI')->commit();

                        $this->updateOpnameHdr($transId, trim($request->spk_id), trim($request->qc_trans_id), $request);

                        return $this->sendResponse($transId, 'Successfully saved');

                    }
                    catch (\Exception $e) {
                        DB::connection('sqlSrvBCKI')->rollBack();
                        return $this->sendError($e->getMessage(), 'Error', 500);
                    }

                } else {
                    return $this->sendError('Belum di checklist', 'Error', 500);
                }

            } else {
                return $this->sendError('Belum di Closing', 'Error', 500);
            }
        } else {
            return $this->sendError('SPK tidak ditemukan', 'Error', 500);
        }
    }

    public function closeSpk(Request $request)
    {
        $spk_hdr = DB::connection('sqlSrvBCKI')->selectOne("
            SELECT * FROM spk_hdr WITH (NOLOCK)
            WHERE id_spk = ?
        ", [trim($request->spk_id)]);

        if ($spk_hdr) {
            DB::connection('sqlSrvBCKI')
            ->table('spk_hdr')
            ->where('id_spk', trim($request->spk_id))
            ->update([
                'dt_completed' => now()
            ]);

            return $this->sendResponse($request->spk_id, 'Successfully saved');

        } else {
            return $this->sendError('SPK tidak ditemukan', 'Error', 500);
        }
    }
    public function update(Request $request)
    {
        try {
            DB::connection('sqlSrvBCKI')
            ->table('qc_opname')
            ->where('tr_id', $request->tr_id)
            ->where('spk_id', $request->spk_id)
            ->where('qc_trans_id', $request->qc_trans_id)
            ->update([
                'description' => $request->description
            ]);

            return $this->sendResponse($request->tr_id, 'Successfully saved');

        }
        catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }

    }

    public function adjust(Request $request)
    {
        try {

            $opname = DB::connection('sqlSrvBCKI')
            ->table('qc_opname')
            ->where('tr_id', $request->tr_id)
            ->where('spk_id', $request->spk_id)
            ->where('qc_trans_id', $request->qc_trans_id)
            ->first();

            if ($opname) {
                if ($opname->dt_confirmed != '1900-01-01 00:00:00.000') {
                    return $this->sendError('Maaf tidak bisa adjust, sudah di confirm', 'Error', 500);
                }
                $sum_amt = DB::connection('sqlSrvBCKI')->selectOne("
                            SELECT 
                                SUM(amt) as amt,
                                SUM(amt_retensi) as amt_retensi,
                                SUM(amt_final) as amt_final
                            FROM qc_opname_dtl WITH (NOLOCK)
                            WHERE tr_id = ?  AND spk_id = ? AND qc_trans_id = ?
                        ", [trim($request->tr_id), trim($request->spk_id), trim($request->qc_trans_id)]);
                $amt_final = $sum_amt ? ($sum_amt->amt_final ? $sum_amt->amt_final : 0) : 0;
                $total_final = $amt_final + $request->amt_adj;
                DB::connection('sqlSrvBCKI')
                ->table('qc_opname')
                ->where('tr_id', $request->tr_id)
                ->where('spk_id', $request->spk_id)
                ->where('qc_trans_id', $request->qc_trans_id)
                ->update([
                    'amt_adj' => $request->amt_adj,
                    'remark_adj' => $request->remark,
                    'amt_total' => $total_final,
                    'terbilang' => terbilang($total_final)
                ]);

                return $this->sendResponse($request->tr_id, 'Successfully saved');
            } else {
                return $this->sendError('Closing sheet tidak ditemukan', 'Error', 500);
            }

        }
        catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }

    }

    public function show(Request $request)
    {
        $tr_id = $request->tr_id;
        $spk_id = $request->spk_id;
        $qc_trans_id = $request->qc_trans_id;

        $datas = DB::connection('sqlSrvBCKI')->select(DB::raw("
                SELECT
                    qc_opname_dtl.*,
                    commodity.com_desc,
                    commodity.[percent]
                FROM
                    [dbo].[qc_opname_dtl] AS qc_opname_dtl
                    INNER JOIN qc_item_commodity AS commodity ON qc_opname_dtl.com_id = commodity.com_id
                WHERE qc_opname_dtl.tr_id = '$tr_id' AND qc_opname_dtl.spk_id = '$spk_id' AND qc_opname_dtl.qc_trans_id = '$qc_trans_id'
                ORDER BY
                    qc_opname_dtl.item_num
        "));

        return $this->sendResponse($datas, 'Successfully get opname detil');

    }

    public function showKasbon(Request $request)
    {
        $spk_id = $request->spk_id;
        $tr_id = $request->tr_id;
        $kasbons = [];

        $qc_opname = DB::connection('sqlSrvBCKI')->selectOne("
            SELECT qc_opname.*
            FROM qc_opname as qc_opname WITH (NOLOCK)
            WHERE qc_opname.tr_id = ? AND qc_opname.spk_id = ?
        ", [trim($request->tr_id), trim($request->spk_id)]);

        if ($qc_opname) {
            if ($qc_opname->dt_confirmed != '1900-01-01 00:00:00.000') {
                $kasbons = DB::connection('sqlSrvBCKI')->select("
                SELECT hdr.tr_id, hdr.dt_kb, hdr.remark, SUM(dtl.amt) as kasbon 
                FROM kb_dtl as dtl 
                JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'EX'
                JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id
                WHERE hdr.tr_id_ref = ? AND dtl.article_id IN ('99990002', '99990001') AND hdr.dt_confirm != '1900-01-01 00:00:00.000' AND hdr.qc_opname_id = ?
                GROUP by hdr.tr_id, hdr.dt_kb, hdr.remark
                ", [trim($spk_id), trim($tr_id)]);
            } else {
                $kasbons = DB::connection('sqlSrvBCKI')->select("
                SELECT hdr.tr_id, hdr.dt_kb, hdr.remark, SUM(dtl.amt) as kasbon 
                FROM kb_dtl as dtl 
                JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'EX'
                JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id AND (bank_note.voucher = 0 OR bank_note.pay_id IS NULL OR LTRIM(RTRIM(bank_note.pay_id)) = '')
                WHERE hdr.tr_id_ref = ? AND dtl.article_id IN ('99990002', '99990001') AND hdr.dt_confirm != '1900-01-01 00:00:00.000' AND (hdr.qc_opname_id IS NULL OR hdr.qc_opname_id = '')
                GROUP by hdr.tr_id, hdr.dt_kb, hdr.remark
                ", [trim($spk_id)]);
            }

            return $this->sendResponse($kasbons, 'Successfully get opname detil');

        }        

    }

    public function sumKasbon(Request $request)
    {
        $spk_id = $request->spk_id;
        $tr_id = $request->tr_id;
        $kasbon = 0;

        $qc_opname = DB::connection('sqlSrvBCKI')->selectOne("
            SELECT qc_opname.*
            FROM qc_opname as qc_opname WITH (NOLOCK)
            WHERE qc_opname.tr_id = ? AND qc_opname.spk_id = ?
        ", [trim($request->tr_id), trim($request->spk_id)]);

        if ($qc_opname) {
            if ($qc_opname->dt_confirmed != '1900-01-01 00:00:00.000') {
                $sum_kasbon = DB::connection('sqlSrvBCKI')->selectOne("
                SELECT 
                SUM(dtl.amt) as kasbon 
                FROM kb_dtl as dtl 
                JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'EX'
                JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id
                WHERE hdr.tr_id_ref = ? AND dtl.article_id IN ('99990002', '99990001') AND hdr.dt_confirm != '1900-01-01 00:00:00.000' AND hdr.qc_opname_id = ?
                ", [trim($spk_id), trim($tr_id)]);
                $kasbon = $sum_kasbon ? ($sum_kasbon->kasbon ? $sum_kasbon->kasbon : 0) : 0;
            } else {
                $sum_kasbon = DB::connection('sqlSrvBCKI')->selectOne("
                SELECT 
                SUM(dtl.amt) as kasbon 
                FROM kb_dtl as dtl 
                JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'EX'
                JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id AND (bank_note.voucher = 0 OR bank_note.pay_id IS NULL OR LTRIM(RTRIM(bank_note.pay_id)) = '')
                WHERE hdr.tr_id_ref = ? AND dtl.article_id IN ('99990002', '99990001') AND hdr.dt_confirm != '1900-01-01 00:00:00.000' AND (hdr.qc_opname_id IS NULL OR hdr.qc_opname_id = '')
                ", [trim($spk_id)]);
                $kasbon = $sum_kasbon ? ($sum_kasbon->kasbon ? $sum_kasbon->kasbon : 0) : 0;
            }

            return $this->sendResponse($kasbon, 'Successfully get opname detil');

        }        

    }

    public function showKasbonMaterial(Request $request)
    {
        $spk_id = $request->spk_id;
        $tr_id = $request->tr_id;
        $kasbons = [];

        $qc_opname = DB::connection('sqlSrvBCKI')->selectOne("
            SELECT qc_opname.*
            FROM qc_opname as qc_opname WITH (NOLOCK)
            WHERE qc_opname.tr_id = ? AND qc_opname.spk_id = ?
        ", [trim($request->tr_id), trim($request->spk_id)]);

        if ($qc_opname) {
            if ($qc_opname->dt_confirmed != '1900-01-01 00:00:00.000') {
                $kasbons = DB::connection('sqlSrvBCKI')->select("
                SELECT hdr.tr_id, hdr.dt_kb, hdr.remark, SUM(dtl.amt) as kasbon 
                FROM kb_dtl as dtl 
                JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'EX'
                JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id
                WHERE hdr.tr_id_ref = ? AND dtl.article_id NOT IN ('99990002', '99990001') AND hdr.dt_confirm != '1900-01-01 00:00:00.000' AND hdr.qc_opname_id = ?
                GROUP By hdr.tr_id, hdr.dt_kb, hdr.remark
                ", [trim($spk_id), trim($tr_id)]);
            } else {
                $kasbons = DB::connection('sqlSrvBCKI')->select("
                SELECT  hdr.tr_id, hdr.dt_kb, hdr.remark, SUM(dtl.amt) as kasbon 
                FROM kb_dtl as dtl 
                JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'EX'
                JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id AND (bank_note.voucher = 0 OR bank_note.pay_id IS NULL OR LTRIM(RTRIM(bank_note.pay_id)) = '')
                WHERE hdr.tr_id_ref = ? AND dtl.article_id NOT IN ('99990002', '99990001') AND hdr.dt_confirm != '1900-01-01 00:00:00.000' AND (hdr.qc_opname_id IS NULL OR hdr.qc_opname_id = '')
                GROUP By hdr.tr_id, hdr.dt_kb, hdr.remark
                ", [trim($spk_id)]);
            }

            return $this->sendResponse($kasbons, 'Successfully get opname detil');

        } 

    }

    public function sumKasbonMaterial(Request $request)
    {
        $spk_id = $request->spk_id;
        $tr_id = $request->tr_id;
        $kasbon = 0;

        $qc_opname = DB::connection('sqlSrvBCKI')->selectOne("
            SELECT qc_opname.*
            FROM qc_opname as qc_opname WITH (NOLOCK)
            WHERE qc_opname.tr_id = ? AND qc_opname.spk_id = ?
        ", [trim($request->tr_id), trim($request->spk_id)]);

        if ($qc_opname) {
            if ($qc_opname->dt_confirmed != '1900-01-01 00:00:00.000') {
                $sum_kasbon = DB::connection('sqlSrvBCKI')->selectOne("
                SELECT 
                SUM(dtl.amt) as kasbon 
                FROM kb_dtl as dtl 
                JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'EX'
                JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id
                WHERE hdr.tr_id_ref = ? AND dtl.article_id NOT IN ('99990002', '99990001') AND hdr.dt_confirm != '1900-01-01 00:00:00.000' AND hdr.qc_opname_id = ?
                ", [trim($spk_id), trim($tr_id)]);
                $kasbon = $sum_kasbon ? ($sum_kasbon->kasbon ? $sum_kasbon->kasbon : 0) : 0;
            } else {
                $sum_kasbon = DB::connection('sqlSrvBCKI')->selectOne("
                SELECT 
                SUM(dtl.amt) as kasbon 
                FROM kb_dtl as dtl 
                JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'EX'
                JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id AND (bank_note.voucher = 0 OR bank_note.pay_id IS NULL OR LTRIM(RTRIM(bank_note.pay_id)) = '')
                WHERE hdr.tr_id_ref = ? AND dtl.article_id NOT IN ('99990002', '99990001') AND hdr.dt_confirm != '1900-01-01 00:00:00.000' AND (hdr.qc_opname_id IS NULL OR hdr.qc_opname_id = '')
                ", [trim($spk_id)]);
                $kasbon = $sum_kasbon ? ($sum_kasbon->kasbon ? $sum_kasbon->kasbon : 0) : 0;
            }

            return $this->sendResponse($kasbon, 'Successfully get opname detil');

        } 

    }
    
    public function showKasbonOther(Request $request)
    {
        $spk_id = $request->spk_id;
        $tr_id = $request->tr_id;
        $kasbons = [];

        $qc_opname = DB::connection('sqlSrvBCKI')->selectOne("
            SELECT qc_opname.*
            FROM qc_opname as qc_opname WITH (NOLOCK)
            WHERE qc_opname.tr_id = ? AND qc_opname.spk_id = ?
        ", [trim($request->tr_id), trim($request->spk_id)]);

        if ($qc_opname) {
            if ($qc_opname->dt_confirmed != '1900-01-01 00:00:00.000') {
                $kasbons = DB::connection('sqlSrvBCKI')->select("
                SELECT hdr.tr_id, hdr.dt_kb, hdr.remark, SUM(dtl.amt) as kasbon 
                FROM kb_dtl as dtl 
                JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'OT'
                JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id
                WHERE hdr.tr_id_ref = ? AND hdr.dt_confirm != '1900-01-01 00:00:00.000' AND hdr.qc_opname_id = ?
                GROUP By hdr.tr_id, hdr.dt_kb, hdr.remark
                ", [trim($spk_id), trim($tr_id)]);
            } else {
                $kasbons = DB::connection('sqlSrvBCKI')->select("
                SELECT hdr.tr_id, hdr.dt_kb, hdr.remark, SUM(dtl.amt) as kasbon 
                FROM kb_dtl as dtl 
                JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'OT'
                JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id AND (bank_note.voucher = 0 OR bank_note.pay_id IS NULL OR LTRIM(RTRIM(bank_note.pay_id)) = '')
                WHERE hdr.tr_id_ref = ? AND hdr.dt_confirm != '1900-01-01 00:00:00.000' AND (hdr.qc_opname_id IS NULL OR hdr.qc_opname_id = '')
                GROUP By hdr.tr_id, hdr.dt_kb, hdr.remark
                ", [trim($spk_id)]);
            }

            return $this->sendResponse($kasbons, 'Successfully get opname detil');

        } 

    }

    public function sumKasbonOther(Request $request)
    {
        $spk_id = $request->spk_id;
        $tr_id = $request->tr_id;
        $kasbon = 0;

        $qc_opname = DB::connection('sqlSrvBCKI')->selectOne("
            SELECT qc_opname.*
            FROM qc_opname as qc_opname WITH (NOLOCK)
            WHERE qc_opname.tr_id = ? AND qc_opname.spk_id = ?
        ", [trim($request->tr_id), trim($request->spk_id)]);

        if ($qc_opname) {
            if ($qc_opname->dt_confirmed != '1900-01-01 00:00:00.000') {
                $sum_kasbon = DB::connection('sqlSrvBCKI')->selectOne("
                SELECT 
                SUM(dtl.amt) as kasbon 
                FROM kb_dtl as dtl 
                JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'OT'
                JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id
                WHERE hdr.tr_id_ref = ? AND hdr.dt_confirm != '1900-01-01 00:00:00.000' AND hdr.qc_opname_id = ?
                ", [trim($spk_id), trim($tr_id)]);
                $kasbon = $sum_kasbon ? ($sum_kasbon->kasbon ? $sum_kasbon->kasbon : 0) : 0;
            } else {
                $sum_kasbon = DB::connection('sqlSrvBCKI')->selectOne("
                SELECT 
                SUM(dtl.amt) as kasbon 
                FROM kb_dtl as dtl 
                JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'OT'
                JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id AND (bank_note.voucher = 0 OR bank_note.pay_id IS NULL OR LTRIM(RTRIM(bank_note.pay_id)) = '')
                WHERE hdr.tr_id_ref = ? AND hdr.dt_confirm != '1900-01-01 00:00:00.000' AND (hdr.qc_opname_id IS NULL OR hdr.qc_opname_id = '')
                ", [trim($spk_id)]);
                $kasbon = $sum_kasbon ? ($sum_kasbon->kasbon ? $sum_kasbon->kasbon : 0) : 0;
            }

            return $this->sendResponse($kasbon, 'Successfully get opname detil');

        } 

    }

    public function destroy(Request $request)
    {
        $spk_hdr = DB::connection('sqlSrvBCKI')->selectOne("
            SELECT * FROM spk_hdr WITH (NOLOCK)
            WHERE id_spk = ?
        ", [trim($request->spk_id)]);

        if ($spk_hdr) {
            DB::connection('sqlSrvBCKI')->beginTransaction();

            try {

                DB::connection('sqlSrvBCKI')
                ->table('qc_opname_dtl')
                ->where('tr_id', $request->tr_id)
                ->where('spk_id', $request->spk_id)
                ->where('qc_trans_id', $request->qc_trans_id)
                ->delete();

                DB::connection('sqlSrvBCKI')->update(
                    "UPDATE qc_closing_weekly
                     SET opname_id = ' '
                     WHERE spk_id = ? AND qc_trans_id = ?  AND opname_id = ?",
                    [trim($request->spk_id), trim($request->qc_trans_id), trim($request->tr_id)]
                ); 

                DB::connection('sqlSrvBCKI')->commit();

                $this->updateOpname($request->tr_id, trim($request->spk_id), trim($request->qc_trans_id), $request);

                return $this->sendResponse($request->tr_id, 'Successfully saved');

            }
            catch (\Exception $e) {
                DB::connection('sqlSrvBCKI')->rollBack();
                return $this->sendError($e->getMessage(), 'Error', 500);
            }
        } else {
            return $this->sendError('SPK tidak ditemukan', 'Error', 500);
        }
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

    private function updateOpnameHdr($transId, $spk_id, $qc_trans_id, $request){  

        $sum_amt = DB::connection('sqlSrvBCKI')->selectOne("
                        SELECT 
                            SUM(amt) as amt
                        FROM qc_opname_dtl WITH (NOLOCK)
                        WHERE tr_id = ?  AND spk_id = ? AND qc_trans_id = ?
                    ", [trim($transId), trim($spk_id), trim($qc_trans_id)]);

        $amt = $sum_amt ? ($sum_amt->amt ? $sum_amt->amt : 0) : 0;
        $terbilang = terbilang($amt);

        DB::connection('sqlSrvBCKI')->update(
            "UPDATE qc_opname
             SET amt_total = ?, terbilang = ? 
             WHERE tr_id = ?  AND spk_id = ? AND qc_trans_id = ?", 
            [$amt, $terbilang, trim($transId), trim($spk_id), trim($qc_trans_id)]
        );   

        DB::connection('sqlSrvBCKI')->update(
            "UPDATE qc_closing_weekly
             SET opname_id = ?
             WHERE spk_id = ? AND qc_trans_id = ?  AND com_id = ? AND opname_id = ' '",
            [trim($transId), trim($request->spk_id), trim($request->qc_trans_id), $request->com_id]
        ); 
    }

    private function updateOpname($transId, $spk_id, $qc_trans_id, $request){  

        $sum_amt = DB::connection('sqlSrvBCKI')->selectOne("
                        SELECT 
                            SUM(amt) as amt
                        FROM qc_opname_dtl WITH (NOLOCK)
                        WHERE tr_id = ?  AND spk_id = ? AND qc_trans_id = ?
                    ", [trim($transId), trim($spk_id), trim($qc_trans_id)]);

        $amt = $sum_amt ? ($sum_amt->amt ? $sum_amt->amt : 0) : 0;
        $terbilang = terbilang($amt);

        DB::connection('sqlSrvBCKI')->update(
            "UPDATE qc_opname
             SET amt_total = ?, terbilang = ? 
             WHERE tr_id = ?  AND spk_id = ? AND qc_trans_id = ?", 
            [$amt, $terbilang, trim($transId), trim($spk_id), trim($qc_trans_id)]
        );  
       
    }
}
