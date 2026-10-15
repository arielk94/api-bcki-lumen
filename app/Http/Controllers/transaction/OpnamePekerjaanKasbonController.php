<?php

namespace App\Http\Controllers\transaction;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OpnamePekerjaanKasbonController extends Controller
{
    public function show(Request $request)
    {
        $tr_id = trim($request->tr_id);
        $spk_id = trim($request->spk_id);

        $datas = DB::connection('sqlSrvBCKI')->select(DB::raw("
            SELECT *
            FROM qc_opname_kasbon
            WHERE tr_id = '$tr_id' AND spk_id = '$spk_id'
            ORDER BY item_num ASC

        "));

        return $this->sendResponse($datas, 'Successfully get kasbon');
    }

    public function store(Request $request)
    {
        $spk_id = $request->spk_id;
        $tr_id = $request->tr_id;
        $remark = '';

        $qc_opname = DB::connection('sqlSrvBCKI')->selectOne("
            SELECT qc_opname.*
            FROM qc_opname as qc_opname WITH (NOLOCK)
            WHERE qc_opname.tr_id = ? AND qc_opname.spk_id = ?
        ", [trim($request->tr_id), trim($request->spk_id)]);

        if ($qc_opname) {
            if ($qc_opname->dt_confirmed != '1900-01-01 00:00:00.000'){
                if ((int) $request->type == 1) {
                    $arrayReusable = [];
                    $kb_hdrs = DB::connection('sqlSrvBCKI')->select("
                                    SELECT hdr.* 
                                    FROM kb_dtl as dtl 
                                    JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'EX'
                                    JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id
                                    WHERE hdr.tr_id_ref = ? AND dtl.article_id IN ('99990002', '99990001') AND hdr.dt_aprv4 != '1900-01-01 00:00:00.000' AND hdr.qc_opname_id = ?
                                    ", [trim($spk_id), trim($tr_id)]);
                    foreach ($kb_hdrs as $key => $value) {
                        array_push($arrayReusable, $value->remark);
                    }
                    $remark = count($arrayReusable) > 0 ? implode(',', $arrayReusable) : ' ';
                } elseif ((int) $request->type == 2) {
                    $arrayReusable = [];
                    $kb_hdrs = DB::connection('sqlSrvBCKI')->select("
                                    SELECT hdr.* 
                                    FROM kb_dtl as dtl 
                                    JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'EX'
                                    JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id
                                    WHERE hdr.tr_id_ref = ? AND dtl.article_id NOT IN ('99990002', '99990001') AND hdr.dt_aprv4 != '1900-01-01 00:00:00.000' AND hdr.qc_opname_id = ?
                                    ", [trim($spk_id), trim($tr_id)]);
                    foreach ($kb_hdrs as $key => $value) {
                        array_push($arrayReusable, $value->remark);
                    }
                    $remark = count($arrayReusable) > 0 ? implode(',', $arrayReusable) : ' ';
                } elseif ((int) $request->type == 3) {
                    $arrayReusable = [];
                    $kb_hdrs = DB::connection('sqlSrvBCKI')->select("
                                    SELECT hdr.* 
                                    FROM kb_dtl as dtl 
                                    JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'OT'
                                    JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id
                                    WHERE hdr.tr_id_ref = ? AND hdr.dt_aprv4 != '1900-01-01 00:00:00.000' AND hdr.qc_opname_id = ?
                                    ", [trim($spk_id), trim($tr_id)]);
                    foreach ($kb_hdrs as $key => $value) {
                        array_push($arrayReusable, $value->remark);
                    }
                    $remark = count($arrayReusable) > 0 ? implode(',', $arrayReusable) : ' ';
                } else {
                    $remark = '';
                }
            } else {
                
                if ((int) $request->type == 1) {
                    $arrayReusable = [];
                    $kb_hdrs = DB::connection('sqlSrvBCKI')->select("
                                    SELECT hdr.* 
                                    FROM kb_dtl as dtl 
                                    JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'EX'
                                    JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id
                                    WHERE hdr.tr_id_ref = ? AND dtl.article_id IN ('99990002', '99990001') AND hdr.dt_aprv4 != '1900-01-01 00:00:00.000' AND (hdr.qc_opname_id IS NULL OR hdr.qc_opname_id = '')
                                    ", [trim($spk_id)]);
                    foreach ($kb_hdrs as $key => $value) {
                        array_push($arrayReusable, $value->remark);
                    }
                    $remark = count($arrayReusable) > 0 ? implode(',', $arrayReusable) : ' ';
                } elseif ((int) $request->type == 2) {
                    $arrayReusable = [];
                    $kb_hdrs = DB::connection('sqlSrvBCKI')->select("
                                    SELECT hdr.* 
                                    FROM kb_dtl as dtl 
                                    JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'EX'
                                    JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id
                                    WHERE hdr.tr_id_ref = ? AND dtl.article_id NOT IN ('99990002', '99990001') AND hdr.dt_aprv4 != '1900-01-01 00:00:00.000' AND (hdr.qc_opname_id IS NULL OR hdr.qc_opname_id = '')
                                    ", [trim($spk_id)]);
                    foreach ($kb_hdrs as $key => $value) {
                        array_push($arrayReusable, $value->remark);
                    }
                    $remark = count($arrayReusable) > 0 ? implode(',', $arrayReusable) : ' ';
                } elseif ((int) $request->type == 3) {
                    $arrayReusable = [];
                    $kb_hdrs = DB::connection('sqlSrvBCKI')->select("
                                    SELECT hdr.* 
                                    FROM kb_dtl as dtl 
                                    JOIN kb_hdr as hdr ON hdr.office_id = dtl.office_id AND hdr.tr_id = dtl.tr_id AND hdr.tr_type = 'OT'
                                    JOIN bank_note as bank_note ON hdr.tr_id = bank_note.doc_id
                                    WHERE hdr.tr_id_ref = ? AND hdr.dt_aprv4 != '1900-01-01 00:00:00.000' AND (hdr.qc_opname_id IS NULL OR hdr.qc_opname_id = '')
                                    ", [trim($spk_id), trim($tr_id)]);
                    foreach ($kb_hdrs as $key => $value) {
                        array_push($arrayReusable, $value->remark);
                    }
                    $remark = count($arrayReusable) > 0 ? implode(',', $arrayReusable) : ' ';
                } else {
                    $remark = '';
                }
            }
        } else {
            $remark = '';
        }

        return $this->sendResponse($remark, 'Successfully get opname detil');

    }
    
}
