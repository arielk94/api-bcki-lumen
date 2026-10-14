<?php

namespace App\Http\Controllers\warehouse;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\User;
use DB;
use Auth;

class WarehouseIssueController extends Controller
{

    public function index(Request $request)
    {

        $getOfficeEnvVar = DB::connection('sqlsrv')
            ->table('sec_env_conf')
            ->where('entity_id', 'FPI')
            ->where('appl_id', 'WAREHOUSE')
            ->where('user_id2', getAuth($request)->user_id2)
            ->where('var_id', 'OFFICEID')
            ->first();

        if (!$getOfficeEnvVar) {

            $office = DB::connection('sqlSrvFPI')
                ->table('branch_office')
                ->selectRaw("office_id, trim(office) + '(' + trim(city) + ')' as office")
                ->where('active_flag', 'Y')
                ->orderBy('office_id', 'asc')
                ->get();
        } else {

            $office = DB::connection('sqlSrvFPI')
                ->table('branch_office')
                ->selectRaw("office_id, trim(office) + '(' + trim(city) + ')' as office")
                ->where('active_flag', 'Y')
                ->where('office_id', $getOfficeEnvVar->var_value)
                ->orderBy('office_id', 'asc')
                ->get();
        }

        $category = DB::connection('sqlSrvFPI')
            ->table('category')
            ->selectRaw("cat_id, trim(descr) as descr")
            ->where('active_flag', 'Y')
            ->get();

        return response()->json(['office' => $office, 'category' => $category]);
    }

    public function getData(Request $request)
    {

        $office = $request->office;
        $wh_id = $request->wh_id;
        $bin_id = $request->bin_id;
        $category = $request->category;
        $sqlWhere = '1=1 ';

        if ($office) {
            $sqlWhere .= "and b.office_id = '$office' ";
        }

        if ($wh_id) {
            $sqlWhere .= "and b.wh_id = '$wh_id' ";
        }

        if ($bin_id) {
            $sqlWhere .= "and b.bin_id = '$bin_id' ";
        }

        if ($category) {
            $sqlWhere .= "and c.cat_id = '$category' ";
        }

        $result = DB::connection('sqlSrvFPI')
            ->table('article as a')
            ->selectRaw("a.article_id, trim(a.description) as description, trim(a.uom) as uom, trim(c.descr) as category, format(b.length, 'N2') as length, trim(b.wh_id) as wh_id,
                    b.bin_id, a.add_id, a.tor_id, b.qty_oh, format(b.wgt_oh, 'N2') as wgt_oh")
            ->join('inv_bal as b', 'a.article_id', 'b.article_id')
            ->join('category as c', 'a.cat_id', 'c.cat_id')
            ->where('a.active_flag', 'Y')
            ->whereRaw($sqlWhere)
            ->get();

        return response()->json(['result' => $result]);
    }

    public function submitData_(Request $request)
    {

        $tr_id = $request->tr_id;
        $office_id = $request->office;
        $order_id = $request->order_id;
        $item_num = $request->item_num;
        $uom = $request->uom;
        $wgtUnit = $request->wgtUnit;
        $prod_code = $request->prod_code;
        $length = $request->length;
        $price = $request->price;
        $total_price = $request->total_price;
        $qty_retur = $request->qty_retur;
        $remark = $request->remark;
        $wh_id = $request->wh_id;
        $bin_id = $request->bin_id;
        $add_id = $request->add_id;
        $tor_id = $request->tor_id;
        $batch_id = $request->batch_id;
        $wh_id_from = $request->wh_id_from;
        $bin_id_from = $request->bin_id_from;
        $needReplace = $request->needReplace;
        $price_mode = $request->price_mode;
        $totalWgtRetur = $price_mode == 'M' ? (int) $qty_retur * $length * $wgtUnit : (int) $qty_retur * $wgtUnit;

        $now = now();
        $period = $now->format('Y') . $now->format('m');

        if ($tr_id == '' || $tr_id == null) {

            $arrayParams1 = (object)[
                'period' => $period,
                'office_id' => $office_id ? $office_id : '',
                'tr_type' => 'SR',
                'tr_date' => $now,
                'tr_id_ref' => $order_id,
                'wh_id' => $wh_id,
                'bin_id' => $bin_id,
                'remark' => $remark ? $remark : ''
            ];

            $storeHeader = $this->storeTransHdr($arrayParams1);

            if ($storeHeader->status == FALSE) {
                return $storeHeader;
            } else {

                $arrayParams2 = (object)[
                    'tr_id' => $storeHeader->data,
                    'period' => $period ? $period : '',
                    'article_id' => $prod_code ? $prod_code : '',
                    'office_id' => $office_id ? $office_id : '',
                    'tr_type' => 'SR',
                    'tr_date' => $now,
                    'tr_id_ref' => $order_id,
                    'tr_id_seq_ref' => $item_num,
                    'wh_id' => $wh_id,
                    'bin_id' => $bin_id,
                    'prod_code' => $prod_code,
                    'add_id' => $add_id,
                    'tor_id' => $tor_id,
                    'batch_id' => $batch_id,
                    'length' => $length,
                    'satuan' => $uom,
                    'amt_item' => $uom == 'M' ? ((int)$price * (int)$length) * (int)$qty_retur : (int)$price * (int)$qty_retur,
                    'unit_price' => $price,
                    'berat' => $wgtUnit,
                    'qty_oh' => $qty_retur ? $qty_retur : '',
                    'defect' => '',
                    'spk_id' => '',
                    'spk_item' => '',
                    'BarangJadiId' => '',
                    'remark' => 'RETUR KTI'
                ];

                $storeDtl = $this->storeTransWH($arrayParams2);

                $currentCustOrdDetil = DB::connection('sqlSrvFPI')
                    ->table('CustomerOrderDetil')
                    ->where('CustomerOrderNo', $order_id)
                    ->where('item_num', $item_num)
                    ->first();

                $updateCustOrdDetil = DB::connection('sqlSrvFPI')
                    ->table('CustomerOrderDetil')
                    ->where('CustomerOrderNo', $order_id)
                    ->where('item_num', $item_num)
                    ->update([
                        'qty_retur' => (int) $currentCustOrdDetil->qty_retur + (int) $qty_retur,
                    ]);

                return $storeDtl;
            }
        } else {

            $arrayParams2 = (object)[
                'tr_id' => $tr_id,
                'period' => $period ? $period : '',
                'article_id' => $prod_code ? $prod_code : '',
                'office_id' => $office_id ? $office_id : '',
                'tr_type' => 'SR',
                'tr_date' => $now,
                'tr_id_ref' => $order_id,
                'tr_id_seq_ref' => $item_num,
                'wh_id' => $wh_id,
                'bin_id' => $bin_id,
                'prod_code' => $prod_code,
                'add_id' => $add_id,
                'tor_id' => $tor_id,
                'batch_id' => $batch_id,
                'length' => $length,
                'satuan' => $uom,
                'amt_item' => $uom == 'M' ? ((int)$price * (int)$length) * (int)$qty_retur : (int)$price * (int)$qty_retur,
                'unit_price' => $price,
                'berat' => $wgtUnit,
                'qty_oh' => $qty_retur ? $qty_retur : '',
                'defect' => '',
                'spk_id' => '',
                'spk_item' => '',
                'BarangJadiId' => '',
                'remark' => 'ISSUE KTI'
            ];

            $storeDtl = $this->storeTransWH($arrayParams2);

            $currentCustOrdDetil = DB::connection('sqlSrvFPI')
                ->table('CustomerOrderDetil')
                ->where('CustomerOrderNo', $order_id)
                ->where('item_num', $item_num)
                ->first();

            $updateCustOrdDetil = DB::connection('sqlSrvFPI')
                ->table('CustomerOrderDetil')
                ->where('CustomerOrderNo', $order_id)
                ->where('item_num', $item_num)
                ->update([
                    'qty_retur' => (int) $currentCustOrdDetil->qty_retur + (int) $qty_retur,
                ]);

            return $storeDtl;
        }
    }

    public function submitData(Request $request)
    {
        $office_id = $request->office_id ?? '';
        $wh_id = $request->wh_id ?? '';
        $bin_id = $request->bin_id ?? '';
        $requester = $request->requester ?? '';
        $remark = $request->remark ?? '';
        $site_id = $request->site_id ?? '';
        $cluster_id = $request->cluster_id ?? '';
        $house_item_id = $request->house_item_id ?? '';
        $no_spk = $request->no_spk ?? '';
        $dataInvDtl = $request->summaries ?? [];

        if (!count($dataInvDtl)) {
            return response()->json(['result' => false, 'error' => 'Enter at least 1 item']);
        }

        $now = now();
        $sqlWhere = "1=1 ";

        $check_daily_closing = DB::connection('sqlSrvFPI')
            ->table('period')
            ->where('dept_id', 'PRODWH')
            ->where('office_id', $office_id)
            ->where('curr_period', $now->format('Y') . $now->format('m'))
            ->first();

        if ($check_daily_closing) {
            try {
                $max_tr_id = DB::connection('sqlSrvFPI')
                    ->table('inv_trans_hdr')
                    ->select('tr_id')
                    ->where('tr_type', 'IS')
                    ->max('tr_id');

                $tr_id = 'IS' .  $now->format('d') . $now->format('m') . substr($now->format('Y'), -2) . str_pad((int)substr($max_tr_id, 10, 12) + 1, 3, '0', STR_PAD_LEFT);

                // -- insert inv_hdr
                DB::connection("sqlSrvFPI")
                    ->table('inv_trans_hdr')
                    ->insert([
                        'office_id' => $office_id,
                        'tr_type' => 'IS',
                        'tr_id' => $tr_id,
                        'wh_id' => $wh_id,
                        'bin_id' => $bin_id,
                        'remark' => 'WAREHOUSE ISSUE FDPI',
                        'tr_date' => now(),
                        'user_id' => getAuth($request)->user_id2,
                        'id_site' => $site_id,
                        'id_cluster' => $cluster_id,
                        'id_house' => $house_item_id,
                        'no_spk' => $no_spk,
                    ]);


                // -- start inv_dtl loop
                foreach ($dataInvDtl as $item) {
                    $article_id = $item['article_id'];
                    $qty_iss = $item['qty'];
                    $length = $item['length'];
                    $add_id = $item['add_id'] ?? '';
                    $tor_id = $item['tor_id'] ?? '';
                    $qty_oh = (int) $item['qty_oh'];

                    $articleDetail = DB::connection('sqlSrvFPI')
                        ->table('article')
                        ->where('active_flag', 'Y')
                        ->where('article_id', $article_id)
                        ->first();

                    // -- skip article kosong, qty_req > qty_oh
                    if (!$articleDetail || $qty_iss > $qty_oh) {
                        continue;
                    }

                    $wgt_iss = (int) $qty_iss * (float) $articleDetail->berat;

                    DB::connection("sqlSrvFPI")
                        ->table('inv_trans_dtl')
                        ->insert([
                            'office_id' => $office_id,
                            'tr_type' => 'IS',
                            'tr_id' => $tr_id,
                            'seq_id' => 1,
                            'article_id' => $article_id,
                            'wh_id' => $wh_id,
                            'bin_id' => $bin_id,
                            'remark' => $remark,
                            'qty' => $qty_iss, // qty request,
                            'amt_item' => $qty_iss, // qty request
                            'wgt' => $wgt_iss, // qty_req * qgt
                            'period' => $now->format('Y') . $now->format('m'),
                            'wgt_unit' => $articleDetail->berat,
                            'uom' => $articleDetail->uom,
                            'length' => $length,
                            'add_id' => $add_id,
                            'tor_id' => $tor_id,
                            'requester' => $requester,
                            'dt_modified' => now(),
                            'user_id' => getAuth($request)->user_id2
                        ]);

                    $getCurrentInvBal = DB::connection("sqlSrvFPI")
                        ->table('inv_bal')
                        ->selectRaw("isnull(qty_awal, 0) as qty_awal, isnull(qty_oh, 0) as qty_oh, qty_rcv, wgt_oh, wgt_rcv, qty_iss, qty_ia, qty_waste, isnull(wgt_awal, 0) as wgt_awal, wgt_iss, wgt_ia, wgt_waste")
                        ->where('office_id', $office_id)
                        ->where('article_id', $article_id)
                        ->where('wh_id', trim($wh_id))
                        ->where('bin_id', $bin_id)
                        ->whereRaw($sqlWhere)
                        ->first();

                    $getQtyTransInvDetil = DB::connection('sqlSrvFPI')
                        ->table('inv_trans_dtl')
                        ->selectRaw('sum(qty) as qty, sum(wgt) as wgt')
                        ->where('office_id', $office_id)
                        ->whereIn('tr_type', ['IS', 'IW', 'MI', 'TI', 'PR', 'SA'])
                        ->where('article_id', $article_id)
                        ->where('wh_id', $wh_id)
                        ->where('bin_id', $bin_id)
                        ->where('period', $now->format('Y') . $now->format('m'))
                        ->where('length', $length)
                        ->first();

                    $getCurrentInvBal = DB::connection("sqlSrvFPI")
                        ->table('inv_bal')
                        ->selectRaw("isnull(qty_awal, 0) as qty_awal, qty_iss, qty_rcv, qty_ia, qty_waste, isnull(wgt_awal, 0) as wgt_awal, wgt_iss, wgt_rcv, wgt_ia, wgt_waste")
                        ->where('office_id', $office_id)
                        ->where('article_id', $article_id)
                        ->where('wh_id', $wh_id)
                        ->where('bin_id', $bin_id)
                        ->first();

                    $hitung_qty_oh = $getCurrentInvBal->qty_awal + $getCurrentInvBal->qty_rcv - (int) $getQtyTransInvDetil->qty + (int) $getCurrentInvBal->qty_ia - (int) $getCurrentInvBal->qty_waste;
                    $hitung_wgt_oh = $getCurrentInvBal->wgt_awal + $getCurrentInvBal->wgt_rcv - (float) $getQtyTransInvDetil->wgt + (float) $getCurrentInvBal->wgt_ia - (float) $getCurrentInvBal->wgt_waste;

                    DB::connection("sqlSrvFPI")
                        ->table('inv_bal')
                        ->where('office_id', $office_id)
                        ->where('article_id', $article_id)
                        ->where('wh_id', $wh_id)
                        ->where('bin_id', $bin_id)
                        ->whereRaw($sqlWhere)
                        ->update([
                            'qty_iss' => (int) $getQtyTransInvDetil->qty,
                            'wgt_iss' => (float) $getQtyTransInvDetil->wgt,
                            'qty_oh' => (int) $hitung_qty_oh,
                            'wgt_oh' => (float) $hitung_wgt_oh,
                            'dt_modified' => now(),
                            'user_id' => getAuth($request)->user_id2
                        ]);
                }

                // -- end inv_dtl loop

                return response()->json(['result' => true, 'tr_id' => $tr_id]);
            } catch (Exception $e) {

                return response()->json(['result' => false, 'error' => $e]);
            }
        } else {
            return response()->json(['result' => false, 'error' => 'Need closing !']);
        }
    }

    public function deleteIssue(Request $request)
    {

        $office_id = $request->office_id;
        $article_id = $request->article_id;
        $tr_id = $request->tr_id;
        $seq_id = $request->seq_id;
        $wh_id = $request->wh_id;
        $bin_id = $request->bin_id;
        $current_qty_iss = '';
        $current_wgt_iss = '';
        $qty_iss = '';
        $wgt_iss = '';
        $sqlWhere = "1=1 ";
        $now = now();

        $checkClosing = DB::connection('sqlSrvFPI')
            ->table('period_daily')
            ->where('office_id', $office_id)
            ->where('dept_id', 'PRODWH')
            ->where('close_date', $now->format('Y') . '-' . $now->format('m') . '-' . $now->format('d'))
            ->where('curr_period', $now->format('Y') . $now->format('m'))
            ->first();

        if (!$checkClosing) {
            $deleteIssue = DB::connection("sqlSrvFPI")
                ->table('inv_trans_dtl')
                ->where('tr_id', $tr_id)
                ->where('tr_type', 'IS')
                ->where('seq_id', $seq_id)
                ->delete();

            $checkCurrentIssue = DB::connection('sqlSrvFPI')
                ->table('inv_trans_dtl')
                ->where('tr_id', $tr_id)
                ->where('tr_type', 'IS')
                ->first();

            if (!$checkCurrentIssue) {
                $deleteIssue = DB::connection("sqlSrvFPI")
                    ->table('inv_trans_hdr')
                    ->where('tr_id', $tr_id)
                    ->where('tr_type', 'IS')
                    ->delete();
            }

            $getQtyTransInvDetil = DB::connection('sqlSrvFPI')
                ->table('inv_trans_dtl')
                ->selectRaw('sum(qty) as qty, sum(wgt) as wgt')
                ->where('office_id', $office_id)
                ->whereIn('tr_type', ['IS', 'IW', 'MI', 'TI', 'PR', 'SA'])
                ->where('article_id', $article_id)
                ->where('wh_id', $wh_id)
                ->where('bin_id', $bin_id)
                ->where('period', $now->format('Y') . $now->format('m'))
                ->first();

            $getCurrentInvBal = DB::connection("sqlSrvFPI")
                ->table('inv_bal')
                ->selectRaw("isnull(qty_awal, 0) as qty_awal, qty_rcv, qty_iss, qty_ia, qty_waste, isnull(wgt_awal, 0) as wgt_awal, wgt_iss, wgt_ia, wgt_waste")
                ->where('office_id', $office_id)
                ->where('article_id', $article_id)
                ->where('wh_id', $wh_id)
                ->where('bin_id', $bin_id)
                ->first();

            $hitung_qty_oh = $getCurrentInvBal->qty_awal + $getCurrentInvBal->qty_rcv - (int) $getQtyTransInvDetil->qty + (int) $getCurrentInvBal->qty_ia - (int) $getCurrentInvBal->qty_waste;
            $hitung_wgt_oh = $getCurrentInvBal->wgt_awal + $getCurrentInvBal->qty_rcv - (float) $getQtyTransInvDetil->wgt + (float) $getCurrentInvBal->wgt_ia - (float) $getCurrentInvBal->wgt_waste;

            $update = DB::connection("sqlSrvFPI")
                ->table('inv_bal')
                ->where('office_id', $office_id)
                ->where('article_id', $article_id)
                ->where('wh_id', $wh_id)
                ->where('bin_id', $bin_id)
                ->whereRaw($sqlWhere)
                ->update([
                    'qty_iss' => (int) $getQtyTransInvDetil->qty,
                    'wgt_iss' => (float) $getQtyTransInvDetil->wgt,
                    'qty_oh' => (int) $hitung_qty_oh,
                    'wgt_oh' => (float) $hitung_wgt_oh,
                    'dt_modified' => now(),
                    'user_id' => getAuth($request)->user_id2
                ]);


            return response()->json(['result' => true]);
        } else {
            return response()->json(['result' => false]);
        }
    }

    public function getListIssue(Request $request)
    {

        $tr_id = $request->tr_id;

        $result = DB::connection('sqlSrvFPI')
            ->table('inv_trans_hdr as a')
            ->selectRaw("a.tr_id, a.office_id, b.article_id, b.seq_id, trim(c.description) as description, trim(c.uom) as uom, trim(d.descr) as category, format(c.length, 'N2') as length, trim(b.wh_id) as wh_id,
                    b.bin_id, b.add_id, b.tor_id, format(b.qty, 'N0') as qty, format(b.wgt, 'N2') as wgt")
            ->join('inv_trans_dtl as b', 'a.tr_id', 'b.tr_id')
            ->join('article as c', 'b.article_id', 'c.article_id')
            ->join('category as d', 'c.cat_id', 'd.cat_id')
            ->where('a.tr_id', $tr_id)
            ->get();

        return response()->json(['result' => $result]);
    }

    public function getWHID(Request $request)
    {

        $office_id = $request->office_id;

        $result = DB::connection('sqlSrvFPI')
            ->table('whouse')
            ->selectRaw("trim(wh_id) as wh_id, trim(descr) + '(' + trim(wh_id) + ')' as descr")
            ->where('active_flag', 'Y')
            ->where('office_id', $office_id)
            ->orderBy('wh_id', 'asc')
            ->get();

        return response()->json(['result' => $result]);
    }

    public function getBinID(Request $request)
    {

        $office_id = $request->office_id;
        $wh_id = $request->wh_id;

        $result = DB::connection('sqlSrvFPI')
            ->table('wh_bin')
            ->selectRaw("bin_id, trim(descr) + '(' + trim(wh_id) + ')' + '(' + trim(bin_id) + ')' as descr")
            ->where('active_flag', 'Y')
            ->where('office_id', $office_id)
            ->where('wh_id', $wh_id)
            ->orderBy('bin_id', 'asc')
            ->get();

        return response()->json(['result' => $result]);
    }

    function getListIssueToday(Request $request)
    {

        $office = $request->office;
        $now = now();

        $result = DB::connection('sqlSrvFPI')
            ->table('inv_trans_hdr')
            ->selectRaw("tr_id, wh_id, bin_id, format(tr_date, 'dd/MM/yyyy') as tr_date, user_id")
            ->where('tr_type', 'IS')
            ->whereRaw("CAST(tr_date AS DATE) = CAST(GETDATE() AS DATE)")
            ->where('office_id', $office)
            ->get();

        return response()->json(['result' => $result]);
    }
}
