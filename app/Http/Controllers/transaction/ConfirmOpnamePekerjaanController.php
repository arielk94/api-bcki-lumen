<?php

namespace App\Http\Controllers\transaction;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\{AccountingService, PerhitunganIncludeService};

class ConfirmOpnamePekerjaanController extends Controller
{
    protected $serviceAcc;
    protected $includeService;

    public function __construct(AccountingService $serviceAcc, PerhitunganIncludeService $includeService)
    {
        $this->serviceAcc = $serviceAcc;
        $this->includeService = $includeService;
    }

    public function store(Request $request)
    {
        // $payload = ['tr_id, spk_id, qc_trans_id, id_site, id_cluster, id_house, vendor_id, user_id2']
        $tr_id = trim($request->tr_id);
        $spk_id = trim($request->spk_id);
        $qc_trans_id = trim($request->qc_trans_id);
        $qc_opname = DB::connection('sqlSrvBCKI')->selectOne("
            SELECT qc_opname.*, 
            TRIM(site.site_name) as site_name,
            TRIM(site_cluster.cluster_name) as cluster_name,
            TRIM(house_unit.house_name) as house_name,
            vendor.tax1,
            vendor.tax2,
            vendor.tax3,
            vendor.tax4,
            vendor.ppn_21,
            vendor.ppn_23
            FROM qc_opname as qc_opname WITH (NOLOCK)
            INNER JOIN qc_trans_hdr AS qhdr ON qhdr.qc_trans_id = qc_opname.qc_trans_id
            INNER JOIN house_unit AS house_unit ON qhdr.id_house = house_unit.id_house
            INNER JOIN site_cluster AS site_cluster ON house_unit.id_cluster = site_cluster.id_cluster
            INNER JOIN site AS site ON house_unit.id_site = site.id_site
            INNER JOIN spk_hdr AS spk_hdr ON spk_hdr.id_spk = qc_opname.spk_id
            INNER JOIN vendor AS vendor ON vendor.vendor_id = spk_hdr.pihak_2 
            WHERE qc_opname.tr_id = ? AND qc_opname.spk_id = ? AND qc_opname.qc_trans_id = ?
        ", [trim($request->tr_id), trim($request->spk_id), trim($request->qc_trans_id)]);

        if ($qc_opname) {
            if ($qc_opname->dt_confirmed != '1900-01-01 00:00:00.000') {
                return $this->sendError('Opname Pekerjaan ini sudah di confirm', 'Error', 500);
            }

            // $ppn = DB::connection('sqlSrvBCKI')
            //     ->table('appl_constant')
            //     ->where('key_code', 'PPN')
            //     ->first();

            // $percent_ppn = $ppn ? $ppn->int1 : 11; 
            // $percent_ppn_21 = $qc_opname->ppn_21; 
            // $percent_ppn_23 = $qc_opname->ppn_23; 
            // $type_ppn = $qc_opname->tax3;

            // $amt_ppn = $percent_ppn / 100;
            // $amt_pph21 = $percent_ppn_21 / 100;
            // $amt_pph23 = $percent_ppn_23 / 100;

            // $CurrYear = Carbon::now()->format('Y');

            DB::connection('sqlSrvBCKI')->beginTransaction();

            try {
                // $transId = $this->generateTransID($request);
                // $invId = $this->generateInvID($request);

                // // -- seq num
                // $trSeq = substr($transId, -3);
                // $invSeq = substr($invId, -3);

                // DB::connection('sqlSrvBCKI')
                // ->table('purc_inv_mast')
                // ->insert([
                //     'office_id' => 1,
                //     'tr_id' => $transId,
                //     'inv_id' => $invId,
                //     'tr_num' => (int) $trSeq,
                //     'inv_num' => (int) $invSeq,
                //     'id_site' => $request->id_site ? $request->id_site : ' ',
                //     'id_cluster' => $request->id_cluster ? $request->id_cluster : ' ',
                //     'id_house' => $request->id_house ? $request->id_house : ' ',
                //     'vendor_id' => $request->vendor_id ? $request->vendor_id : ' ',
                //     'inv_type' => 'N',
                //     'dt_inv' => now(),
                //     'dt_ship' => now(),
                //     'pay_term_id' => 'CBD',
                //     'curr_id' => 'IDR',
                //     'tax1' => trim($qc_opname->tax1) == 'Y' ? 'T' : ' ',
                //     'tax2' => trim($qc_opname->tax2) == 'Y' ? 'T' : ' ',
                //     'tax3' => trim($type_ppn) == 'Y' ? 'T' : ' ',
                //     'tax4' => trim($qc_opname->tax4) == 'Y' ? 'T' : ' ',
                //     'rate' => 1,
                //     'ppn' => $percent_ppn,
                //     'remark' => 'CLOSING SPK ' . trim($request->spk_id) . ', SITE ' . $qc_opname->site_name . ', CLUSTER ' . $qc_opname->cluster_name . ', UNIT ' . $qc_opname->house_name,
                //     'dt_created' => now(),
                //     'user_id' => $request->user_id2 ? $request->user_id2 : ' ',
                //     'reff_id' => trim($request->tr_id),
                //     'dt_due' => Carbon::now()->addDays(7)
                // ]);

                // $qc_opname_dtls = DB::connection('sqlSrvBCKI')->select(DB::raw("
                //         SELECT
                //             qc_opname_dtl.*,
                //             commodity.com_desc
                //         FROM
                //             [dbo].[qc_opname_dtl] AS qc_opname_dtl
                //             INNER JOIN qc_item_commodity AS commodity ON qc_opname_dtl.com_id = commodity.com_id
                //         WHERE qc_opname_dtl.tr_id = '$tr_id' AND qc_opname_dtl.spk_id = '$spk_id' AND qc_opname_dtl.qc_trans_id = '$qc_trans_id'
                //         ORDER BY
                //             qc_opname_dtl.item_num
                // "));

                // foreach ($qc_opname_dtls as $key => $value) {
                //     if (trim($type_ppn) == 'Y') {
                //         // $total_pajak = 1 + $amt_ppn - $amt_pph23 - $amt_pph21;
                //         // $price = $value->amt / $total_pajak;
                //         $price = $value->amt_final;
                //     } else {
                //         $price = $value->amt_final;
                //     }
                //     DB::connection('sqlSrvBCKI')
                //     ->table('purc_inv_item')
                //     ->insert([
                //         'office_id' => 1,
                //         'tr_id' => $transId,
                //         'seq_num' => $value->item_num,
                //         'inv_id' => $invId,
                //         'inv_item' => $value->item_num,
                //         'article_id' => '03000145',
                //         'qty' => 1,
                //         'unit_meas' => 'PCS',
                //         'unit_price' => $price,
                //         'amt_item' => $price,
                //         'id_site' => $request->id_site ? $request->id_site : ' ',
                //         'id_cluster' => $request->id_cluster ? $request->id_cluster : ' ',
                //         'id_house' => $request->id_house ? $request->id_house : ' ',
                //         'dt_created' => now(),
                //         'user_id' => $request->user_id2 ? $request->user_id2 : ' ',
                //         'ac_num_bbt' => '211-010-002',
                //         'ac_num_diff_rate' => '510-010-004',
                //         'remark' => $value->com_desc . ' ' .  $value->description,
                //         'wgt_unit' => 0,
                //         'um_price' => 0,
                //     ]);
                // }
                // // ADJUSTMENT CLOSING SHEET
                // if ($qc_opname->amt_adj > 0) {
                //     $seq_num = DB::connection('sqlSrvBCKI')->table('purc_inv_item')
                //         ->select('seq_num')
                //         ->where('tr_id', $transId)
                //         ->max('seq_num');
                //     $item_num = $seq_num + 1;
                //     DB::connection('sqlSrvBCKI')
                //     ->table('purc_inv_item')
                //     ->insert([
                //         'office_id' => 1,
                //         'tr_id' => $transId,
                //         'seq_num' => $item_num,
                //         'inv_id' => $invId,
                //         'inv_item' => $item_num,
                //         'article_id' => '03000314', //article adjust
                //         'qty' => 1,
                //         'unit_meas' => 'PCS',
                //         'unit_price' => $qc_opname->amt_adj,
                //         'amt_item' => $qc_opname->amt_adj,
                //         'id_site' => $request->id_site ? $request->id_site : ' ',
                //         'id_cluster' => $request->id_cluster ? $request->id_cluster : ' ',
                //         'id_house' => $request->id_house ? $request->id_house : ' ',
                //         'dt_created' => now(),
                //         'user_id' => $request->user_id2 ? $request->user_id2 : ' ',
                //         'ac_num_bbt' => '211-010-002',
                //         'ac_num_diff_rate' => '510-010-004',
                //         'remark' => 'ADJUSTMENT CLOSING SHEET',
                //         'wgt_unit' => 0,
                //         'um_price' => 0,
                //     ]);
                // }


                // $this->updateInvMaster(trim($request->tr_id), trim($request->spk_id), trim($request->qc_trans_id), $transId, $type_ppn, $amt_ppn, $amt_pph21, $amt_pph23, $invId);

                // $this->serviceAcc->JrnlPurcInvNonPO(1, $transId, $CurrYear, $request->user_id2 ? $request->user_id2 : ' ');

                // $this->updateKasbonCash(trim($request->tr_id), trim($request->spk_id), trim($request->qc_trans_id));
                // $this->updateKasbonMaterial(trim($request->tr_id), trim($request->spk_id), trim($request->qc_trans_id));
                // $this->updateKasbonOther(trim($request->tr_id), trim($request->spk_id), trim($request->qc_trans_id));

                DB::connection('sqlSrvBCKI')->update("
                    UPDATE qc_opname
                    SET dt_confirmed = ?, confirmed_by = ?
                    WHERE tr_id = ? AND spk_id = ? AND qc_trans_id = ?
                ", [now(), $request->user_id2 ? $request->user_id2 : ' ', trim($request->tr_id), trim($request->spk_id), trim($request->qc_trans_id)]);

                DB::connection('sqlSrvBCKI')->commit();

                $this->updateKasbonCash(trim($request->tr_id), trim($request->spk_id), trim($request->qc_trans_id));
                $this->updateKasbonMaterial(trim($request->tr_id), trim($request->spk_id), trim($request->qc_trans_id));
                $this->updateKasbonOther(trim($request->tr_id), trim($request->spk_id), trim($request->qc_trans_id));

                return $this->sendResponse('', 'Successfully confirmed');
            }
            catch (\Exception $e) {
                DB::connection('sqlSrvBCKI')->rollBack();
                return $this->sendError($e->getMessage(), 'Error', 500);
            }
        } else {
            return $this->sendError('Opname Pekerjaan tidak ditemukan', 'Error', 500);
        }
    }

    public function approve(Request $request)
    {
        // $payload = ['tr_id, spk_id, qc_trans_id, id_site, id_cluster, id_house, vendor_id, user_id2']
        
        $tr_id = trim($request->tr_id);
        $spk_id = trim($request->spk_id);
        $qc_trans_id = trim($request->qc_trans_id);
        $qc_opname = DB::connection('sqlSrvBCKI')->selectOne("
            SELECT qc_opname.*, 
            TRIM(site.site_name) as site_name,
            TRIM(site_cluster.cluster_name) as cluster_name,
            TRIM(house_unit.house_name) as house_name,
            vendor.tax1,
            vendor.tax2,
            vendor.tax3,
            vendor.tax4,
            vendor.ppn_21,
            vendor.ppn_23
            FROM qc_opname as qc_opname WITH (NOLOCK)
            INNER JOIN qc_trans_hdr AS qhdr ON qhdr.qc_trans_id = qc_opname.qc_trans_id
            INNER JOIN house_unit AS house_unit ON qhdr.id_house = house_unit.id_house
            INNER JOIN site_cluster AS site_cluster ON house_unit.id_cluster = site_cluster.id_cluster
            INNER JOIN site AS site ON house_unit.id_site = site.id_site
            INNER JOIN spk_hdr AS spk_hdr ON spk_hdr.id_spk = qc_opname.spk_id
            INNER JOIN vendor AS vendor ON vendor.vendor_id = spk_hdr.pihak_2 
            WHERE qc_opname.tr_id = ? AND qc_opname.spk_id = ? AND qc_opname.qc_trans_id = ?
        ", [trim($request->tr_id), trim($request->spk_id), trim($request->qc_trans_id)]);

        if ($qc_opname) {
            if ($qc_opname->dt_confirmed == '1900-01-01 00:00:00.000') {
                return $this->sendError('Opname Pekerjaan ini harus di konfirm dulu', 'Error', 500);
            }
            if ($qc_opname->dt_apprv != '1900-01-01 00:00:00.000') {
                return $this->sendError('Opname Pekerjaan ini sudah di setujui', 'Error', 500);
            }

            if ($this->serviceAcc->ifAcctClosed(now(), $request)) {
                return $this->sendResponse(null, 'This periode already closed, cannot be modified');
            }

            $ppn = DB::connection('sqlSrvBCKI')
                ->table('appl_constant')
                ->where('key_code', 'PPN')
                ->first();

            $pct21 = DB::connection('sqlSrvBCKI')
                ->table('appl_constant')
                ->where('key_code', 'PPH21')
                ->first();

            $pct23 = DB::connection('sqlSrvBCKI')
                ->table('appl_constant')
                ->where('key_code', 'PPH23')
                ->first();

            $percent_ppn = $ppn ? $ppn->int1 : 11; 
            
            $percent_ppn_21 = $pct21 ? (float) $pct21->str3 : 0; 
            $percent_ppn_23 = $pct23 ? (float) $pct23->str3 : 0; 

            // $percent_ppn_21 = $qc_opname->ppn_21; 
            // $percent_ppn_23 = $qc_opname->ppn_23; 
            $type_ppn = $qc_opname->tax1;

            $amt_ppn = $percent_ppn / 100;
            $amt_pph21 = $percent_ppn_21 / 100;
            $amt_pph23 = $percent_ppn_23 / 100;

            $CurrYear = Carbon::now()->format('Y');

            DB::connection('sqlSrvBCKI')->beginTransaction();

            try {
                $transId = $this->generateTransID($request);
                $invId = $this->generateInvID($request);

                // -- seq num
                $trSeq = substr($transId, -3);
                $invSeq = substr($invId, -3);

                DB::connection('sqlSrvBCKI')
                ->table('purc_inv_mast')
                ->insert([
                    'office_id' => 1,
                    'tr_id' => $transId,
                    'inv_id' => $invId,
                    'tr_num' => (int) $trSeq,
                    'inv_num' => (int) $invSeq,
                    'id_site' => $request->id_site ? $request->id_site : ' ',
                    'id_cluster' => $request->id_cluster ? $request->id_cluster : ' ',
                    'id_house' => $request->id_house ? $request->id_house : ' ',
                    'vendor_id' => $request->vendor_id ? $request->vendor_id : ' ',
                    'inv_type' => 'N',
                    'dt_inv' => now(),
                    'dt_ship' => now(),
                    'pay_term_id' => 'CBD',
                    'curr_id' => 'IDR',
                    // 'tax1' => trim($qc_opname->tax1), // type PPN
                    'tax1' => 'E', // type PPN
                    // 'tax2' => trim($qc_opname->tax2), // PPH 23 type
                    'tax2' => 'E', // PPH 23 type
                    'pct_pph' => $percent_ppn_23, // PCT PPH 23
                    // 'tax3' => trim($qc_opname->tax3), // PPH 4 type
                    'tax3' => 'N', // PPH 4 type
                    // 'tax4' => trim($qc_opname->tax4), // PPH 21
                    'tax4' => 'E', // PPH 21
                    'pct_pph21' => $percent_ppn_21, // PCT PPH 21
                    'rate' => 1,
                    'ppn' => $percent_ppn,
                    'remark' => 'CLOSING SPK ' . trim($request->spk_id) . ', SITE ' . $qc_opname->site_name . ', CLUSTER ' . $qc_opname->cluster_name . ', UNIT ' . $qc_opname->house_name,
                    'dt_created' => now(),
                    'user_id' => $request->user_id2 ? $request->user_id2 : ' ',
                    'reff_id' => trim($request->tr_id),
                    'dt_due' => Carbon::now()->addDays(7)
                ]);

                $qc_opname_dtls = DB::connection('sqlSrvBCKI')->select(DB::raw("
                        SELECT
                            qc_opname_dtl.*,
                            commodity.com_desc
                        FROM
                            [dbo].[qc_opname_dtl] AS qc_opname_dtl
                            INNER JOIN qc_item_commodity AS commodity ON qc_opname_dtl.com_id = commodity.com_id
                        WHERE qc_opname_dtl.tr_id = '$tr_id' AND qc_opname_dtl.spk_id = '$spk_id' AND qc_opname_dtl.qc_trans_id = '$qc_trans_id'
                        ORDER BY
                            qc_opname_dtl.item_num
                "));

                foreach ($qc_opname_dtls as $key => $value) {
                    // $calcHargaInEx = $this->includeService->hitungHargaDasarIncludeExclude(
                    //     $value->amt_final,
                    //     ((float) $amt_ppn),
                    //     ((float) $amt_pph23),
                    //     ((float) $amt_pph21),
                    //     [trim($qc_opname->tax1), trim($qc_opname->tax2), trim($qc_opname->tax4), ''],
                    //     0
                    // );
                    DB::connection('sqlSrvBCKI')
                    ->table('purc_inv_item')
                    ->insert([
                        'office_id' => 1,
                        'tr_id' => $transId,
                        'seq_num' => $value->item_num,
                        'inv_id' => $invId,
                        'inv_item' => $value->item_num,
                        'article_id' => '03000145',
                        'qty' => 1,
                        'unit_meas' => 'PCS',
                        'unit_price' => $value->amt_final,
                        'amt_item' => $value->amt_final,
                        'id_site' => $request->id_site ? $request->id_site : ' ',
                        'id_cluster' => $request->id_cluster ? $request->id_cluster : ' ',
                        'id_house' => $request->id_house ? $request->id_house : ' ',
                        'dt_created' => now(),
                        'user_id' => $request->user_id2 ? $request->user_id2 : ' ',
                        'ac_num_bbt' => '211-010-002',
                        'ac_num_diff_rate' => '510-010-004',
                        'remark' => $value->com_desc . ' ' .  $value->description,
                        'wgt_unit' => 0,
                        'um_price' => 0,
                    ]);
                }
                // ADJUSTMENT CLOSING SHEET
                if ($qc_opname->amt_adj > 0) {
                    $seq_num = DB::connection('sqlSrvBCKI')->table('purc_inv_item')
                        ->select('seq_num')
                        ->where('tr_id', $transId)
                        ->max('seq_num');
                    $item_num = $seq_num + 1;
                    DB::connection('sqlSrvBCKI')
                    ->table('purc_inv_item')
                    ->insert([
                        'office_id' => 1,
                        'tr_id' => $transId,
                        'seq_num' => $item_num,
                        'inv_id' => $invId,
                        'inv_item' => $item_num,
                        'article_id' => '03000314', //article adjust
                        'qty' => 1,
                        'unit_meas' => 'PCS',
                        'unit_price' => $qc_opname->amt_adj,
                        'amt_item' => $qc_opname->amt_adj,
                        'id_site' => $request->id_site ? $request->id_site : ' ',
                        'id_cluster' => $request->id_cluster ? $request->id_cluster : ' ',
                        'id_house' => $request->id_house ? $request->id_house : ' ',
                        'dt_created' => now(),
                        'user_id' => $request->user_id2 ? $request->user_id2 : ' ',
                        'ac_num_bbt' => '211-010-002',
                        'ac_num_diff_rate' => '510-010-004',
                        'remark' => 'ADJUSTMENT CLOSING SHEET',
                        'wgt_unit' => 0,
                        'um_price' => 0,
                    ]);
                }

                DB::connection('sqlSrvBCKI')->commit();

                $this->updateInvMaster(trim($request->tr_id), trim($request->spk_id), trim($request->qc_trans_id), $transId, 'E', 0.11, 0.025, 0.02, $invId, $qc_opname);

                $this->serviceAcc->JrnlPurcInvNonPO(1, $transId, $CurrYear, $request->user_id2 ? $request->user_id2 : ' ');

                DB::connection('sqlSrvBCKI')->update("
                    UPDATE qc_opname
                    SET dt_apprv = ?, apprv_by = ?
                    WHERE tr_id = ? AND spk_id = ? AND qc_trans_id = ?
                ", [now(), $request->user_id2 ? $request->user_id2 : ' ', trim($request->tr_id), trim($request->spk_id), trim($request->qc_trans_id)]);

                return $this->sendResponse('', 'Successfully confirmed');
            }
            catch (\Exception $e) {
                DB::connection('sqlSrvBCKI')->rollBack();
                return $this->sendError($e->getMessage(), 'Error', 500);
            }
        } else {
            return $this->sendError('Opname Pekerjaan tidak ditemukan', 'Error', 500);
        }
    }

    private function generateTransID($request)
    {
        $dateYear = substr( date("Y"), -2);
        $dateMonth = date("m");
        $prefix = 'PIN' .$dateYear.$dateMonth; // contoh: 0001
        $padLength = 4;

        // Ambil ID terakhir yang sesuai prefix
        $lastId = DB::connection('sqlSrvBCKI')
                    ->table('purc_inv_mast')
                    ->where('tr_id', 'like', "{$prefix}%")
                    ->orderBy('tr_id', 'desc')
                    ->value('tr_id');

        if ($lastId) {
            $lastSequence = (int) substr($lastId, strlen($prefix));
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        $newId = $prefix . str_pad($nextSequence, $padLength, '0', STR_PAD_LEFT);

        // Validasi unik terakhir kali
        while (
            DB::connection('sqlSrvBCKI')->table('purc_inv_mast')->where('tr_id', $newId)->exists()
        ) {
            $nextSequence++;
            $newId = $prefix . str_pad($nextSequence, $padLength, '0', STR_PAD_LEFT);
        }

        return $newId;
    }

    private function generateInvID($request)
    {
        $dateYear = substr( date("Y"), -2);
        $dateMonth = date("m");
        $prefix = 'PINVN' .$dateYear.$dateMonth; // contoh: 0001
        $padLength = 4;

        // Ambil ID terakhir yang sesuai prefix
        $lastId = DB::connection('sqlSrvBCKI')
                    ->table('purc_inv_mast')
                    ->where('inv_id', 'like', "{$prefix}%")
                    ->orderBy('inv_id', 'desc')
                    ->value('inv_id');

        if ($lastId) {
            $lastSequence = (int) substr($lastId, strlen($prefix));
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        $newId = $prefix . str_pad($nextSequence, $padLength, '0', STR_PAD_LEFT);

        // Validasi unik terakhir kali
        while (
            DB::connection('sqlSrvBCKI')->table('purc_inv_mast')->where('inv_id', $newId)->exists()
        ) {
            $nextSequence++;
            $newId = $prefix . str_pad($nextSequence, $padLength, '0', STR_PAD_LEFT);
        }

        return $newId;
    }

    private function updateKasbonCash($tr_id, $spk_id, $qc_trans_id){
        $kasbons = DB::connection('sqlSrvBCKI')->select("
                        SELECT DISTINCT hdr.tr_id
                        FROM kb_dtl as dtl 
                        JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'EX'
                        JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id AND (bank_note.voucher = 0 OR bank_note.pay_id IS NULL OR LTRIM(RTRIM(bank_note.pay_id)) = '')
                        WHERE hdr.tr_id_ref = ? AND dtl.article_id IN ('99990002', '99990001') AND hdr.dt_aprv4 != '1900-01-01 00:00:00.000' AND (hdr.qc_opname_id IS NULL OR hdr.qc_opname_id = '')
                    ", [trim($spk_id)]);

        foreach ($kasbons as $key => $kasbon) {
            DB::connection('sqlSrvBCKI')->update(
                "UPDATE kb_hdr
                SET qc_opname_id = ?
                WHERE tr_id = ?", 
                [$tr_id, trim($kasbon->tr_id)]
            );   
        }
    }

    private function updateKasbonMaterial($tr_id, $spk_id, $qc_trans_id){
        $kasbons = DB::connection('sqlSrvBCKI')->select("
                        SELECT DISTINCT hdr.tr_id
                        FROM kb_dtl as dtl 
                        JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'EX'
                        JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id AND (bank_note.voucher = 0 OR bank_note.pay_id IS NULL OR LTRIM(RTRIM(bank_note.pay_id)) = '')
                        WHERE hdr.tr_id_ref = ? AND dtl.article_id NOT IN ('99990002', '99990001') AND hdr.dt_aprv4 != '1900-01-01 00:00:00.000' AND (hdr.qc_opname_id IS NULL OR hdr.qc_opname_id = '')
                    ", [trim($spk_id)]);

        foreach ($kasbons as $key => $kasbon) {
            DB::connection('sqlSrvBCKI')->update(
                "UPDATE kb_hdr
                SET qc_opname_id = ?
                WHERE tr_id = ?", 
                [$tr_id, trim($kasbon->tr_id)]
            );   
        }
    }

    private function updateKasbonOther($tr_id, $spk_id, $qc_trans_id){
        $kasbons = DB::connection('sqlSrvBCKI')->select("
                        SELECT DISTINCT hdr.tr_id
                        FROM kb_dtl as dtl 
                        JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'OT'
                        JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id AND (bank_note.voucher = 0 OR bank_note.pay_id IS NULL OR LTRIM(RTRIM(bank_note.pay_id)) = '')
                        WHERE hdr.tr_id_ref = ? AND hdr.dt_aprv4 != '1900-01-01 00:00:00.000' AND (hdr.qc_opname_id IS NULL OR hdr.qc_opname_id = '')
                    ", [trim($spk_id)]);

        foreach ($kasbons as $key => $kasbon) {
            DB::connection('sqlSrvBCKI')->update(
                "UPDATE kb_hdr
                SET qc_opname_id = ?
                WHERE tr_id = ?", 
                [$tr_id, trim($kasbon->tr_id)]
            );   
        }
    }


    private function updateInvMaster($tr_id_opname, $spk_id, $qc_trans_id, $transId, $type_ppn, $amt_ppn, $amt_pph21, $amt_pph23, $invId, $qc_opname){  

        $sum_amt = DB::connection('sqlSrvBCKI')->selectOne("
                        SELECT 
                            SUM(amt_item) as amt
                        FROM purc_inv_item WITH (NOLOCK)
                        WHERE office_id = 1 AND tr_id = ?  AND inv_id = ?
                    ", [trim($transId), trim($invId)]);

        $amt = $sum_amt ? ($sum_amt->amt ? $sum_amt->amt : 0) : 0;

        $calcHargaInEx = $this->includeService->hitungHargaDasarIncludeExclude(
                            $amt,
                            ((float) $amt_ppn),
                            ((float) $amt_pph23),
                            ((float) $amt_pph21),
                            ['E', 'E', 'E', 'N'],
                            0
                        );
                        
        $subtotal = $calcHargaInEx[0];
        $total_ppn = $calcHargaInEx[1];
        $total_pph23 = $calcHargaInEx[2];
        $total_pph21 = $calcHargaInEx[3];
        $total = $calcHargaInEx[5];

        $terbilang = terbilang($total);

        DB::connection('sqlSrvBCKI')->update(
            "UPDATE purc_inv_mast
             SET amt_subtotal = ?, inv_said = ? , amt_ppn = ? , amt_pph = ? , amt_pph21 = ?, amt_total = ?
             WHERE office_id = 1  AND tr_id = ?", 
            [$subtotal, $terbilang, $total_ppn, $total_pph23, $total_pph21, $total, $transId]
        );   

    }

    public function reJournal(Request $request)
    {
        $datas = DB::connection('sqlSrvBCKI')->select(DB::raw("
                    SELECT
                        a.tr_id,
                        a.office_id,
                        a.dt_inv,
                        vendor.tax1,
                        vendor.tax2,
                        vendor.tax3,
                        vendor.tax4,
                        vendor.ppn_21,
                        vendor.ppn_23
                    FROM purc_inv_mast AS a
                    INNER JOIN vendor
                        ON vendor.vendor_id = a.vendor_id
                    WHERE a.reff_id <> '' AND YEAR(a.dt_inv) = 2026 AND MONTH(a.dt_inv) = 7;
                "));

        foreach ($datas as $key => $value) {
            $CurrYear = Carbon::parse($value->dt_inv)->year;
            $this->serviceAcc->JrnlPurcInvNonPO(1, $value->tr_id, $CurrYear, 'mardian');
        }

        return $this->sendResponse('', 'Successfully confirmed');
    }

    public function reUpdateInv(Request $request){
        
        $TrID = $request->tr_id ? $request->tr_id : 'PIN25111417';
        $officeId = 1;
        $userId = getAuth($request)->user_id2;

        $inv = DB::connection('sqlSrvBCKI')->selectOne("
                SELECT qc_opname.*, 
                vendor.tax1,
                vendor.tax2,
                vendor.tax3,
                vendor.tax4,
                vendor.ppn_21,
                vendor.ppn_23,
                purc_inv_mast.reff_id,
                purc_inv_mast.inv_id,
                purc_inv_mast.dt_inv,
                spk_hdr.id_site,
                spk_hdr.id_cluster,
                spk_hdr.id_house_unit
                FROM purc_inv_mast as purc_inv_mast WITH (NOLOCK)
                INNER JOIN qc_opname AS qc_opname ON qc_opname.tr_id = purc_inv_mast.reff_id
                INNER JOIN spk_hdr AS spk_hdr ON spk_hdr.id_spk = qc_opname.spk_id
                INNER JOIN vendor AS vendor ON vendor.vendor_id = purc_inv_mast.vendor_id 
                WHERE purc_inv_mast.tr_id = ?
            ", [trim($TrID)]);

        if ($inv) {
            DB::connection('sqlSrvBCKI')->beginTransaction();

            try {
                // $ppn = DB::connection('sqlSrvBCKI')
                //     ->table('appl_constant')
                //     ->where('key_code', 'PPN')
                //     ->first();

                // $pct21 = DB::connection('sqlSrvBCKI')
                //     ->table('appl_constant')
                //     ->where('key_code', 'PPH21')
                //     ->first();

                // $pct23 = DB::connection('sqlSrvBCKI')
                //     ->table('appl_constant')
                //     ->where('key_code', 'PPH23')
                //     ->first();

                // $percent_ppn = $ppn ? $ppn->int1 : 11;
                // $percent_ppn_21 = $pct21 ? (float) $pct21->str3 : 0;
                // $percent_ppn_23 = $pct23 ? (float) $pct23->str3 : 0;
                // // $percent_ppn_21 = $inv->ppn_21; 
                // // $percent_ppn_23 = $inv->ppn_23; 
                // $type_ppn = $inv->tax1;

                // $amt_ppn = $percent_ppn / 100;
                // $amt_pph21 = $percent_ppn_21 / 100;
                // $amt_pph23 = $percent_ppn_23 / 100;

                DB::connection('sqlSrvBCKI')->table('purc_inv_mast')->where('tr_id', $TrID)->update([
                    // 'tax1' => trim($inv->tax1), // type PPN
                    // 'tax2' => trim($inv->tax2), // PPH 23 type
                    // 'pct_pph' => $percent_ppn_23, // PCT PPH 23
                    // 'tax3' => trim($inv->tax3), // PPH 4 type
                    // 'tax4' => trim($inv->tax4), // PPH 21
                    // 'pct_pph21' => $percent_ppn_21, // PCT PPH 21
                    'tax1' => 'E',
                    'tax2' => 'E',
                    'tax3' => 'N',
                    'tax4' => 'E',
                    'pct_pph21' => 2.5,
                    'pct_pph' => 2,

                ]);
                DB::connection('sqlSrvBCKI')->table('purc_inv_item')->where('tr_id', $TrID)->delete();

                $qc_opname_dtls = DB::connection('sqlSrvBCKI')->select("
                    SELECT
                        qc_opname_dtl.*,
                        commodity.com_desc
                    FROM qc_opname_dtl
                    INNER JOIN qc_item_commodity commodity
                        ON commodity.com_id = qc_opname_dtl.com_id
                    WHERE qc_opname_dtl.tr_id = ?
                    ORDER BY qc_opname_dtl.item_num
                ", [$inv->reff_id]);

                foreach ($qc_opname_dtls as $key => $value) {
                    // $calcHargaInEx = $this->includeService->hitungHargaDasarIncludeExclude(
                    //     $value->amt_final,
                    //     0.11,
                    //     0.2,
                    //     0.25,
                    //     ['E', 'E', 'E', 'N'],
                    //     0
                    // );
                    DB::connection('sqlSrvBCKI')
                        ->table('purc_inv_item')
                        ->insert([
                            'office_id' => 1,
                            'tr_id' => $TrID,
                            'seq_num' => $value->item_num,
                            'inv_id' => $inv->inv_id,
                            'inv_item' => $value->item_num,
                            'article_id' => '03000145',
                            'qty' => 1,
                            'unit_meas' => 'PCS',
                            'unit_price' => $value->amt_final,
                            'amt_item' => $value->amt_final,
                            'id_site' => $inv->id_site,
                            'id_cluster' => $inv->id_cluster,
                            'id_house' => $inv->id_house_unit,
                            'dt_created' => now(),
                            'user_id' => getAuth($request)->user_id2 ?? null,
                            'ac_num_bbt' => '211-010-002',
                            'ac_num_diff_rate' => '510-010-004',
                            'remark' => $value->com_desc . ' ' .  $value->description,
                            'wgt_unit' => 0,
                            'um_price' => 0,
                        ]);
                }

                if ($inv->amt_adj != 0) {
                    $seq_num = DB::connection('sqlSrvBCKI')->table('purc_inv_item')
                        ->select('seq_num')
                        ->where('tr_id', $TrID)
                        ->max('seq_num');
                    $item_num = $seq_num + 1;

                    // $calcHargaInEx = $this->includeService->hitungHargaDasarIncludeExclude(
                    //     $inv->amt_adj,
                    //     0.11,
                    //     0.2,
                    //     0.25,
                    //     ['E', 'E', 'E', 'N'],
                    //     0
                    // );
                    
                    DB::connection('sqlSrvBCKI')
                    ->table('purc_inv_item')
                    ->insert([
                        'office_id' => 1,
                        'tr_id' => $TrID,
                        'seq_num' => $item_num,
                        'inv_id' => $inv->inv_id,
                        'inv_item' => $item_num,
                        'article_id' => '03000314', //article adjust
                        'qty' => 1,
                        'unit_meas' => 'PCS',
                        'unit_price' => $inv->amt_adj,
                        'amt_item' => $inv->amt_adj,
                        'id_site' => $inv->id_site,
                        'id_cluster' => $inv->id_cluster,
                        'id_house' => $inv->id_house_unit,
                        'dt_created' => now(),
                        'user_id' => $request->user_id2 ? $request->user_id2 : ' ',
                        'ac_num_bbt' => '211-010-002',
                        'ac_num_diff_rate' => '510-010-004',
                        'remark' => 'ADJUSTMENT CLOSING SHEET',
                        'wgt_unit' => 0,
                        'um_price' => 0,
                    ]);
                }

                DB::connection('sqlSrvBCKI')->commit();

                $this->updateInvMasterRe($TrID, $inv->inv_id, 0.11, 0.025, 0.02, $inv);

                
                $tanggal = Carbon::parse($inv->dt_inv);
                $tahun4digit = $tanggal->format('Y');

                $this->serviceAcc->JrnlPurcInvNonPO($officeId, $TrID, $tahun4digit, $userId);
                
                return $this->sendResponse('', 'Successfully update');

            } catch (\Exception $e) {
                DB::connection('sqlSrvBCKI')->rollBack();
                return $this->sendError($e->getMessage(), 'Error', 500);
            }
        }

    }

    private function updateInvMasterRe($TrID, $invId, $amt_ppn, $amt_pph21, $amt_pph23, $inv)
    {
        $sum_amt = DB::connection('sqlSrvBCKI')->selectOne("
                        SELECT 
                            SUM(amt_item) as amt
                        FROM purc_inv_item WITH (NOLOCK)
                        WHERE office_id = 1 AND tr_id = ?  AND inv_id = ?
                    ", [trim($TrID), trim($invId)]);

        $amt = $sum_amt ? ($sum_amt->amt ? $sum_amt->amt : 0) : 0;

        $calcHargaInEx = $this->includeService->hitungHargaDasarIncludeExclude(
            $amt,
            0.11,
            0.02,
            0.025,
            ['E', 'E', 'E', 'N'],
            0
        );

        $subtotal = $calcHargaInEx[0];
        $total_ppn = $calcHargaInEx[1];
        $total_pph23 = $calcHargaInEx[2];
        $total_pph21 = $calcHargaInEx[3];
        $total = $calcHargaInEx[5];

        $terbilang = terbilang($total);

        DB::connection('sqlSrvBCKI')->update(
            "UPDATE purc_inv_mast
             SET amt_subtotal = ?, inv_said = ? , amt_ppn = ? , amt_pph = ? , amt_pph21 = ?, amt_total = ?
             WHERE office_id = 1  AND tr_id = ?",
            [$subtotal, $terbilang, $total_ppn, $total_pph23, $total_pph21, $total, $TrID]
        );
    }
}
