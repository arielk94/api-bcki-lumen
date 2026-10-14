<?php

namespace App\Http\Controllers\purchasing;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;
use App\Traits\WarehouseTrait;
use App\Services\AccountingService;
use App\Services\PerhitunganIncludeService;


class PurchaseReceiveController extends Controller
{
    use WarehouseTrait;
    protected $serviceAcc;
    protected $servicePerhitungan;

    public function __construct(AccountingService $serviceAcc,PerhitunganIncludeService $servicePerhitungan)
    {
        $this->serviceAcc = $serviceAcc;
        $this->servicePerhitungan = $servicePerhitungan;
    }

    public function getPurcRcv(Request $request)
    {
        $sqlWhere = "1=1";
        $office_id = $request->office_id;
        $vendor_id = $request->vendor_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $purc_rcv_id = $request->purc_rcv_id;

        if ($office_id)
        {
            $sqlWhere .= " and a.office_id = '" . $office_id . "'";
        }
        if ($vendor_id)
        {
            $sqlWhere .= " and a.vendor_id = '" . $vendor_id . "'";
        }
        if ($purc_rcv_id)
        {
            $sqlWhere .= " and a.purc_rcv_id = '" . $purc_rcv_id . "'";
        }

        if (!$start_date)
        {
            $start_date = '';
        }

        if (!$end_date)
        {
            $end_date = '';
        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and a.dt_rcv BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and a.dt_rcv BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
            }
        }

        $data = DB::connection('sqlSrvFPI')
            ->table('purc_rcv_hdr as a')
            ->join('vendor as b', function ($join) {
                $join->on(DB::raw('1'), '=', 'b.office_id')
                    ->on('a.vendor_id', '=', 'b.vendor_id');
            })
            ->join('branch_office as e','a.office_id','=','e.office_id')
            ->leftJoin('purc_inv_mast as d', 'a.inv_id', '=', 'd.inv_id')
            ->leftjoin('hrd_ol.dbo.employee as f', 'a.user_id', '=', 'f.user_id2')
            ->select([
                'a.*',
                'b.vendor_name',
                'd.amt_total',
                'd.amt_paid',
                DB::raw("TRIM(e.office) as office_desc"), 
                DB::raw("TRIM(e.address) as office_addr"), 
                DB::raw("TRIM(e.city) as office_city"), 
                DB::raw("TRIM(e.zip_code) as office_zip_code"), 
                DB::raw("TRIM(e.tlp) as office_tlp"), 
                DB::raw("TRIM(e.e_mail) as office_email"), 'f.name',
                DB::raw("STUFF((
                    SELECT DISTINCT ', ' + trim(c.po_id)
                    FROM purc_rcv_dtl AS c
                    WHERE c.office_id = a.office_id
                    AND c.purc_rcv_id = a.purc_rcv_id
                    FOR XML PATH('')
                ), 1, 2, '') AS po_id"),
                DB::raw("(SELECT TOP 1 c.pr_id FROM purc_rcv_dtl AS c WHERE c.office_id = a.office_id AND c.purc_rcv_id = a.purc_rcv_id) AS pr_id"),
                DB::raw("CASE WHEN d.amt_paid > 0 THEN 'Y' ELSE 'N' END AS editable")
            ])
            ->whereRaw($sqlWhere)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('purc_rcv_dtl as c')
                    ->whereRaw('c.office_id = a.office_id AND c.purc_rcv_id = a.purc_rcv_id');
            })
            ->orderBy('a.dt_rcv')
            ->orderBy('a.purc_rcv_id')
            ->get();

        return $this->sendResponse($data, 'Successfully get Purchase Receive data');

    }

    public function getPOID(Request $request){
        $sqlWhere = "1=1";
        $office_id = $request->office_id;
        $vendor_id = $request->vendor_id;

        if ($office_id)
        {
            $sqlWhere .= " and a.office_id = '" . $office_id . "'";
        }
        if ($vendor_id)
        {
            $sqlWhere .= " and a.vendor_id = '" . $vendor_id . "'";
        }

        $sqlWhere .= " and a.stat not in ('C','X')";

        $data = DB::connection('sqlSrvFPI')
            ->table('po_hdr as a')
            ->join('vendor as b', function ($join) {
                $join->on(DB::raw('1'), '=', 'b.office_id')
                    ->on('a.vendor_id', '=', 'b.vendor_id');
            })
            ->select([
                DB::raw('trim(a.po_id) as po_id'),
                DB::raw('trim(b.vendor_name) as vendor_name')
            ])
            ->whereRaw($sqlWhere)
            
            ->get();

        return $this->sendResponse($data, 'Successfully get Purchase Receive data');
    }
    
    public function getPurcRcvDtl(Request $request)
    {
        $sqlWhere = "1=1";
        $office_id = $request->office_id;
        $purc_rcv_id = $request->purc_rcv_id;

        if ($office_id)
        {
            $sqlWhere .= " and a.office_id = '" . $office_id . "'";
        }
        if ($purc_rcv_id)
        {
            $sqlWhere .= " and a.purc_rcv_id = '" . $purc_rcv_id . "'";
        }
        $data = DB::connection('sqlSrvFPI')
            ->table('purc_rcv_dtl as a')
            ->whereRaw($sqlWhere)
            ->select('a.*',
            'd.description',
            'c.memo_txt')
            ->join('article as d', 'a.article_id', '=', 'd.article_id')
            ->join('purc_rcv_hdr as c', 'a.purc_rcv_id', '=', 'c.purc_rcv_id')
            ->orderBy('a.purc_rcv_id')
            ->get();
            

        return $this->sendResponse($data, 'Successfully get Purchase Receive data');

    }

    public function storePurcRcvHdr(Request $request){
        DB::connection('sqlSrvFPI')->beginTransaction();
        try {
        
        $office_id      = $request->office_id;
        $remark         = $request->remark;
        $vendor_id      = $request->vendor_id;
        $dt_rcv         = $request->dt_rcv ?? now('Asia/Jakarta');
        $expd_name      = $request->expd_name ?? '';
        $no_pol         = $request->no_pol ?? '';
        $driver_name    = $request->driver_name ?? '';
        $curr_id        = $request->curr_id ?? 'IDR';
        $rate           = $request->rate ?? 1;
        $amount         = $request->amount ?? 0;
        $amt_freight    = $request->amt_freight ?? 0;
        $wgt_scale      = $request->wgt_scale ?? 0;
        $ac_num_invent  = $request->ac_num_invent ?? '';
        $ac_num_bbt     = $this->getStdAccountCode(1, "PERSEDIAAN BARANG BELUM TERTAGIH", "IDR", "N") ?? '';
        $user_id        = getAuth($request)->user_id2;
        $upd_flag       = $request->upd_flag ?? 'N';

        $date = Carbon::parse($dt_rcv);
        $ym = $date->format('ym'); // Contoh: 2507
        // dd($tr_date, $date, $ym);
        $prefix = 'PRC' . $ym . (int) $office_id; // Hasil: PRC25075

        $autoInv = $this->getInt1ApplConst('AUTOINV', 'AUTOINV');
            
        $getLastInvID = DB::connection('sqlSrvFPI')
            ->table('purc_rcv_hdr')
            ->where('office_id', $office_id)
            ->where(DB::raw("LTRIM(RTRIM(purc_rcv_id))"), 'like', $prefix . '%')
            ->selectRaw('MAX(rcv_num) AS max_tr')
            ->value('max_tr');
        // dd($getLastInvID+1);
        $purc_rcv_id = !empty(trim($request->purc_rcv_id)) ? $request->purc_rcv_id : $this->genPurcRcvId($dt_rcv, $office_id);
        // dd($purc_rcv_id);
        $sj_no = !empty(trim($request->sj_no)) ? $request->sj_no : $this->genNoSJ($dt_rcv, $office_id);
        $invdata = $this->GetGenInvTrID($purc_rcv_id);
            // dd($invdata);
        if($autoInv == 1){
            // dd($invdata);
            $inv_id = !empty(trim($request->inv_id)) ? $request->inv_id : $invdata['inv_id'];//$this->genInvId($dt_rcv, $office_id);
        }
        else{
            $inv_id = $request->inv_id ?? '';
        }
        // dd($inv_id);
        // $sj_num = (int) substr($sj_no, -3);  // misalnya PSJ2507005 → 5
        // $rcv_num = (int) substr($purc_rcv_id, -3); // misalnya PRC25071005 → 5

        // Insert ke tabel
        DB::connection('sqlSrvFPI')
            ->table('purc_rcv_hdr')
            ->updateOrInsert(['purc_rcv_id' => $purc_rcv_id],
                [
                'office_id'        => $office_id,
                'purc_rcv_id'      => $purc_rcv_id,
                'dt_rcv'           => $dt_rcv,
                'vendor_id'        => $vendor_id,
                'sj_no'            => $sj_no,
                'expd_name'        => $expd_name,
                'no_pol'           => $no_pol,
                'driver_name'      => $driver_name,
                'rcv_num'          => $getLastInvID + 1,
                'sj_num'           => $getLastInvID + 1,
                'qty'              => 0,
                'wgt'              => 0,
                'curr_id'          => $curr_id,
                'rate'             => $rate,
                'amount'           => $amount,
                'amt_freight'      => $amt_freight,
                'memo_txt'         => $remark,
                'wgt_scale'        => $wgt_scale,
                'ac_num_invent'    => $ac_num_invent,
                'ac_num_bbt'       => $ac_num_bbt,
                'dt_created'       => now('Asia/Jakarta'),
                'dt_modified'      => now('Asia/Jakarta'),
                'user_id'          => $user_id,
                'inv_id'           => $inv_id,
                'upd_flag'         => $upd_flag,
            ]);

            $arrayParams = (object)[
                'period'           => date('Ym'),
                'office_id'        => $request->office_id,
                'tr_type'          => 'RP',
                'tr_id'            => $purc_rcv_id,
                'tr_date'          => now('Asia/Jakarta')->format('Ymd'),
                'user_id'          => $user_id,
                'dt_modified'      => now('Asia/Jakarta'),
                'remark'           => $request->remark
            ];

            $check = $this->storeTransHdr($arrayParams);

            
            if (isset($check->status) && $check->status === false) {
                DB::connection('sqlSrvFPI')->rollBack();
            return response()->json([
                'success' => false,
                'message' => $check->message ?? 'Gagal menyimpan Transaksi Header',
            ], 500);
        }
            // $tanggal = Carbon::parse($request->dt_rcv);
            // $tahun4digit = $tanggal->format('Y');
            // $this->serviceAcc->JrnlPurcReceive(1, $purc_rcv_id, $tahun4digit, $user_id);
            DB::connection('sqlSrvFPI')->commit();
            return $this->sendResponse([ 'purc_rcv_id' => $purc_rcv_id , 'sj_no' => $sj_no , 'check' => $check], 'Successfully Save Purchase Receive data');
        } catch (\Throwable $e) {
            DB::connection('sqlSrvFPI')->rollBack(); // <-- TAMBAHKAN ROLLBACK DI SINI
            return response()->json(['status' => 'error', 'message' => 'Gagal menyimpan header.', 'error' => $e->getMessage()], 500);
        }    
    }

    public function storePurcRcvDtl(Request $request){
        DB::connection('sqlSrvFPI')->beginTransaction();
        try {
            $purc_rcv_id = $request->purc_rcv_id ?? '';
            $sj_no = $request->sj_no ?? '';
            $rcvDtlData = $request->input('items');
            $office_id = $request->office_id;
            $user_id = getAuth($request)->user_id2;
            $dataPurcRcvHdr = DB::connection('sqlSrvFPI')
                ->table('purc_rcv_hdr as a')
                ->where('a.purc_rcv_id', $purc_rcv_id)
                ->first();
            
            if (!$dataPurcRcvHdr) {
                DB::connection('sqlSrvFPI')->rollBack();
                return response()->json(['status' => 'error', 'message' => 'Header penerimaan tidak ditemukan.'], 404);
            }
            $remark = $dataPurcRcvHdr->memo_txt ?? '';
            // --- Perbaikan 1: Koleksi PO ID Unik ---
            $poIdsToUpdate = [];
            $savedItems = [];
            $autoInv = $this->getInt1ApplConst('AUTOINV', 'AUTOINV');
            // ----------------------------------------
            foreach ($rcvDtlData as $item) {
                $po_item = $item['po_item'] ?? 0;
                $po_id = $item['po_id'] ?? '';
                $article_id = $item['article_id'] ?? '';
                $wh_id = $item['wh_id'] ?? '';
                $bin_id = $item['bin_id'] ?? '';                
                $qtyNow = $item['qty'] ?? 0;

                // Tambahkan PO ID ke array koleksi
                if (!empty($po_id)) {
                    $poIdsToUpdate[$po_id] = true;
                }
                $ac_num_cr = $this->getStdAccountCode(1, "PERSEDIAAN BARANG BELUM TERTAGIH", "IDR", "N");
                
                $dataPO = DB::connection("sqlSrvFPI")
                    ->table('po_item as a')
                    ->join('po_hdr AS b', 'a.po_id', '=', 'b.po_id')
                    ->leftjoin('article AS c', 'a.article_id', '=', 'c.article_id')
                    ->leftjoin('pay_term as d', 'a.office_id', '=', 'd.office_id')
                    ->where('a.po_id', $po_id)
                    ->where('a.po_item', $po_item)
                    ->where('a.article_id', $article_id)
                    ->select('a.*','b.*','a.qty', 'c.consumable', 'c.ac_num_persd', 'd.num_of_days')
                    ->first();
                // dd($dataPO);
                if (!$dataPO) {
                    DB::connection('sqlSrvFPI')->rollBack();
                    return response()->json(['status' => 'error', 'message' => "Data PO untuk item '$article_id' tidak ditemukan."], 404);
                }
                $ac_num_dr = trim($dataPO->ac_num_persd);
                if(!$ac_num_dr){
                    DB::connection('sqlSrvFPI')->rollBack();
                    return response()->json(['status' => 'error', 'message' => "Ac num article yang direceive tidak lengkap. Silahkan hubungi accounting"], 404);
                }
                $unitPrice = $dataPO->unit_price ?? 0;
                $totalPoQty = round($dataPO->qty,2);
                
                $existingDtl = DB::connection("sqlSrvFPI")
                    ->table('purc_rcv_dtl')
                    ->where('purc_rcv_id', $purc_rcv_id)
                    ->where('po_id', $po_id)
                    ->where('po_item', $po_item)
                    ->first();
                // dd($existingDtl);
                // --- LOGIKA PERBAIKAN 1: Hitung Selisih Qty & Wgt ---
                $oldQty = $existingDtl ? $existingDtl->qty : 0;
                $oldWgt = $existingDtl ? $existingDtl->wgt : 0;
                $qtyNow = $item['qty'] ?? 0;
                
                // Hitung Wgt Baru (Qty * Wgt Unit dari PO)
                $wgtUnit = $dataPO->wgt ?? 0;
                $wgtNow = $qtyNow * $wgtUnit; // Berat total baru
                
                // Hitung selisih (change) untuk update PO/PR
                $qtyChange = $qtyNow - $oldQty; 
                $wgtChange = $wgtNow - $oldWgt;
                // ----------------------------------------------------

                $received_qty_other = DB::connection("sqlSrvFPI")
                    ->table('purc_rcv_dtl')
                    ->where('po_id', $po_id)
                    ->where('po_item', $po_item)
                    ->where('purc_rcv_id', '!=', $purc_rcv_id)
                    ->sum('qty');
                    
                $totalReceivedAfter = $received_qty_other + $qtyNow;
                
                if ($totalReceivedAfter > $totalPoQty) {
                    DB::connection('sqlSrvFPI')->rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => "Kuantitas yang diinput ($qtyNow) untuk PO item $po_item melebihi total kuantitas yang dapat diterima ($totalPoQty)."
                    ], 400);
                }

                // ✅ SOLUSI PREVENTIF 2: Mencegah Pengurangan Stok Melebihi Stok Fisik
                // ---------------------------------------------------------------------
                if ($qtyChange < 0) {
                    // Ambil Qty On Hand (QtyOH) saat ini dari inv_bal
                    // Pastikan fungsi getInvOh mengembalikan stok aktual, BUKAN stok PR/PO
                    $qtyOH = $this->getInvOh(
                        $office_id, 
                        $article_id, 
                        $dataPO->batch_id ?? '', 
                        $wh_id, 
                        $bin_id, 
                        $dataPO->panjang ?? 0, 
                        $dataPO->unit_meas
                    );

                    // Jika nilai absolut pengurangan melebihi stok yang ada
                    if (abs($qtyChange) > (float)$qtyOH) {
                        DB::connection('sqlSrvFPI')->rollBack();
                        return response()->json([
                            'status' => false,
                            'message' => "Pengurangan kuantitas (" . abs($qtyChange) . ") untuk item '$article_id' melebihi stok yang tersedia di gudang ($qtyOH)."
                        ], 400);
                    }
                }
                // ---------------------------------------------------------------------
                // Inisialisasi awal agar tidak undefined
                // $tr_id_invoice = '';
                // $nextSeqNumInvoice = 0;
                // dd($existingDtl);
                if ($existingDtl) {
                    $nextSeqNum = $existingDtl->seq_num;
                    $nextInvItem = $existingDtl->inv_item;
                    $tr_id_invoice = $existingDtl->tr_id_invoice;
                    $nextSeqNumInvoice = $existingDtl->seq_num_invoice;
                } else {
                    // $lastSeqNum = DB::connection("sqlSrvFPI")
                    //     ->table('purc_rcv_dtl')
                    //     ->where('purc_rcv_id', $purc_rcv_id)
                    //     ->max('seq_num');
                    // $nextSeqNum = ($lastSeqNum ?? 0) + 1;
                    // $nextInvItem = $nextSeqNum;

                    // // --- KUNCI PERBAIKAN: Generate Invoice ID jika ini item pertama atau data baru ---
                    // $invdata = $this->GetGenInvTrID($purc_rcv_id); // Pastikan fungsi ini generate ID unik (misal: INV26010001)
                    // $tr_id_invoice = $invdata['tr_id_invoice'];
                    // // dd($invdata,$tr_id_invoice);

                    // $lastInvItem = DB::connection("sqlSrvFPI")
                    //     ->table('purc_rcv_dtl')
                    //     ->where('purc_rcv_id', $purc_rcv_id)
                    //     ->max('inv_item');
                    // $nextInvItem = ($lastInvItem ?? 0) + 1;
                    // dd($nextSeqNum,$nextInvItem,$tr_id_invoice);
                    // $nextSeqNum = (DB::connection("sqlSrvFPI")->table('purc_rcv_dtl')->where('purc_rcv_id', $purc_rcv_id)->max('seq_num') ?? 0) + 1;
                    // $nextInvItem = (DB::connection("sqlSrvFPI")->table('purc_rcv_dtl')->where('purc_rcv_id', $purc_rcv_id)->max('inv_item') ?? 0) + 1;
                    
                    // Ambil ID Invoice Baru jika Insert
                    // $invdata = $this->GetGenInvTrID($purc_rcv_id);
                    // $tr_id_invoice = $invdata['tr_id_invoice'];
                    // $nextSeqNumInvoice = (DB::connection("sqlSrvFPI")->table('purc_rcv_dtl')->where('purc_rcv_id', $purc_rcv_id)->max('seq_num_invoice') ?? 0) + 1;
                    // 1. Ambil ID Invoice baru dari generator
                    // $invdata = $this->GetGenInvTrID($purc_rcv_id);
                    // $tr_id_invoice = $invdata['tr_id_invoice'];

                    // // 2. Cari Sequence Invoice Terakhir (Berdasarkan ID Invoice yang baru didapat)
                    // $lastInvSeq = DB::connection("sqlSrvFPI")
                    //     ->table('purc_rcv_dtl')
                    //     ->where('tr_id_invoice', $tr_id_invoice)
                    //     ->max('seq_num_invoice');
                    
                    // // Jika null, maka jadikan 1. Jika ada, maka +1
                    // $nextSeqNumInvoice = ($lastInvSeq ?? 0) + 1;

                    // // 3. Sequence Header Detail
                    // $nextSeqNum = (DB::connection("sqlSrvFPI")->table('purc_rcv_dtl')->where('purc_rcv_id', $purc_rcv_id)->max('seq_num') ?? 0) + 1;
                    // $nextInvItem = (DB::connection("sqlSrvFPI")->table('purc_rcv_dtl')->where('purc_rcv_id', $purc_rcv_id)->max('inv_item') ?? 0) + 1;
                    $nextSeqNum = (DB::connection("sqlSrvFPI")->table('purc_rcv_dtl')->where('purc_rcv_id', $purc_rcv_id)->max('seq_num') ?? 0) + 1;
                    $nextInvItem = $nextSeqNum;

                    $invdata = $this->GetGenInvTrID($purc_rcv_id);
                    $tr_id_invoice = $invdata['tr_id_invoice'];

                    $lastInvSeq = DB::connection("sqlSrvFPI")
                        ->table('purc_rcv_dtl')
                        ->where('tr_id_invoice', $tr_id_invoice)
                        ->max('seq_num_invoice');
                    $nextSeqNumInvoice = ($lastInvSeq ?? 0) + 1;
                }

                $this->TrSeq = (int)$nextSeqNumInvoice; 
                $this->TrIDInv = $tr_id_invoice;
                // dd($this->TrSeq,$this->TrIDInv);
                if (trim($dataPO->consumable) === 'N' && trim($dataPO->po_type) !== 'D') {
                    
                    // Gunakan $oldQty daripada melakukan query DB tambahan
                    $qtyPrev = $oldQty; 
                    
                    if ($qtyPrev > 0 && $qtyChange < 0) { // Hanya cek jika ada data lama dan ada pengurangan
                        $qtyOH = $this->getInvOh($dataPO->office_id, $article_id, $dataPO->batch_id ?? '', $wh_id, $bin_id, $dataPO->panjang ?? 0, $dataPO->unit_meas);
                        
                        // Logika yang setara: Jika stok gudang < jumlah yang akan dikurangi (abs($qtyChange))
                        // Atau, menggunakan logika asli: Stok tersedia < (Qty Lama - Qty Baru)
                        if ((float)$qtyOH < ((float)$qtyPrev - $qtyNow)) {
                            // DB::connection('sqlSrvFPI')->rollBack();
                            return response()->json(['status' => false, 'message' => "Qty Tidak Cukup (Stok tersedia: $qtyOH) untuk item '$article_id'"], 400);
                        }
                    }
                }

                $amtNow = $qtyNow * $unitPrice;
                // $tr_id_invoice = $existingDtl->tr_id_invoice ?? ''; // Pertahankan ID Invoice jika sudah ada
                // $nextSeqNumInvoice = $existingDtl->seq_num_invoice ?? ''; // Pertahankan Seq Num Invoice jika sudah ada
                // dd($tr_id_invoice,$nextSeqNum,$existingDtl);
                $dataDtl = [
                    'office_id' => $office_id,
                    'purc_rcv_id' => $purc_rcv_id,
                    'seq_num' => $nextSeqNum,
                    'sj_no' => $sj_no ?? '',
                    'vessel_no' => '',
                    'vessel_name' => '',
                    'po_id' => $po_id ?? '',
                    'po_item' => $po_item ?? 0,
                    'curr_id' => 'IDR',
                    'rate' => 1,
                    'pr_id' => trim($dataPO->pr_id),
                    'pr_item' => trim($dataPO->pr_item),
                    'article_id' => $article_id ?? '',
                    'wh_id' => $wh_id ?? '',
                    'qty' => $qtyNow,
                    'wgt' => $wgtNow,
                    'unit_meas' => trim($dataPO->unit_meas),
                    'qty_stock' => $qtyNow,
                    'wgt_unit' => $dataPO->wgt ?? 0,
                    'unit_price' => $unitPrice ?? 0,
                    'um_price' => $dataPO->um_price ?? 0,
                    'amt_item' => $amtNow,
                    'ac_num_dr' => $ac_num_dr,
                    'ac_num_cr' => $ac_num_cr,
                    'inv_id' => $dataPurcRcvHdr->inv_id ?? '',
                    'inv_item' => $nextInvItem,
                    'lab_flag' => '',
                    'lab_id' => '',
                    'lab_num' => 0,
                    'qty_onspec' => $qtyNow,
                    'wgt_onspec' => $wgtNow,
                    'refraksi' => 0,
                    'qty_accept' => $qtyNow,
                    'wgt_accept' => $wgtNow,
                    'bin_id' => $bin_id ?? '',
                    'tr_id' => '',
                    'seq_id' => 0,
                    'tr_id_invoice' => $tr_id_invoice,
                    'seq_num_invoice' => $nextSeqNumInvoice,
                    'dt_created' => now('Asia/Jakarta'),
                    'dt_modified' => now('Asia/Jakarta'),
                    'user_id' => $user_id,
                    'length' => 1,
                    'fix_price' => 0,
                ];
                // dd($dataDtl);
                DB::connection('sqlSrvFPI')->table('purc_rcv_dtl')->updateOrInsert(
                    [
                        'purc_rcv_id' => $dataDtl['purc_rcv_id'],
                        'po_id' => $dataDtl['po_id'],
                        'po_item' => $dataDtl['po_item']
                    ],
                    $dataDtl
                );
                $savedItems[] = $dataDtl;
                if ($autoInv == 1) {
                    // $lastSeqNumInvoice = DB::connection("sqlSrvFPI")
                    //     ->table('purc_rcv_dtl')
                    //     ->where('purc_rcv_id', $purc_rcv_id)
                    //     ->max('seq_num_invoice');
                    // $nextSeqNumInvoice = ($lastSeqNumInvoice ?? 0) + 1;
                    // $invdata = $this->GetGenInvTrID($purc_rcv_id);
                    
                    // $tr_id_invoice = $invdata['tr_id_invoice'];//$this->genPurcInvId($dataPurcRcvHdr->dt_rcv, $dataPurcRcvHdr->office_id);

                    $this->TrSeq = $nextSeqNumInvoice;
                    $this->TrIDInv = $tr_id_invoice;
                    $this->MillID = (int)$office_id;
                    $this->RcvID = $purc_rcv_id;
                    $this->gsUserID = $user_id;
                    $this->DtPR = $dataPurcRcvHdr->dt_rcv;
                    $this->InvID = $dataPurcRcvHdr->inv_id ?? '';
                    $this->ConsumAble = trim($dataPO->consumable) === 'Y';
                    $this->SjNo = $sj_no;
                    $this->PoID = $po_id;
                    $this->PoItem = $po_item;
                    $this->PrID = trim($dataPO->pr_id);
                    $this->PrItem = trim($dataPO->pr_item);
                    $this->ArticleID = $article_id;
                    $this->QtyRcv = $qtyNow;
                    $this->WgtRcv = $wgtNow;
                    $this->UnitMeas = trim($dataPO->unit_meas);
                    $this->WgtUnit = $dataPO->wgt ?? 0;
                    $this->optUmPrice = [true];
                    $this->UnitPrice = $unitPrice;
                    $this->FixPrice = 0;
                    $this->AmountItem = $amtNow;
                    $this->Rate = 1;
                    $this->VendorID = $dataPurcRcvHdr->vendor_id;
                    $this->PayTermID = $dataPO->pay_term_id;
                    $this->NumOfDays = $dataPO->num_of_days;
                    $this->Tax1 = $dataPO->tax1;
                    $this->Tax2 = $dataPO->tax2;
                    $this->Tax3 = $dataPO->tax3;
                    $this->CurrID = $dataPO->curr_id ?? 'IDR';
                    $this->PctDisc = $dataPO->pct_disc ?? 0;
                    $this->AmtDisc = $dataPO->amt_disc ?? 0;
                    $this->ppn = $dataPO->ppn ?? 0;
                    $this->AmtFreight = $dataPO->amt_freight ?? 0;
                    $this->InvoiceNum = $invdata['inv_num'] ?? 0;//$dataPurcRcvHdr->rcv_num ?? 0;
                    $this->trNum = $invdata['tr_num'] ?? 0;//$dataPurcRcvHdr->rcv_num ?? 0;
                    $this->AcNumBBT = $ac_num_cr;
                    $this->AcNumPPH = '';
                    $this->PrNum = trim($dataPO->pr_item) ?? '';
                    $this->optImport = false;
                    // dd($dataPO);
                    $result = $this->createPurcInv($office_id,$purc_rcv_id);
                    if (!$result['status']) {
                        DB::connection('sqlSrvFPI')->rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => $result['message']
                        ], 500);
                    }
                }

                // dd('stop, cek purc rcv dtl');
                if ($qtyChange != 0) {
                    // Gunakan $qtyChange, bukan $qtyNow!
                    $this->updatePoItemAndHdr($po_id, $po_item, $qtyChange, $wgtChange, $dataPO->qty); 
                    
                    if (!empty($dataPO->pr_id) && !empty($dataPO->pr_item)) {
                        // Gunakan $qtyChange, bukan $qtyNow!
                        $this->updatePrItem($dataPO->pr_id, $dataPO->pr_item, $qtyChange, $wgtChange);
                    }
                
                    $remark = $existingDtl ?? ''; // Ganti remark agar lebih deskriptif
                    
                    $arrayParams = (object)[
                        'tr_id' => $purc_rcv_id,
                        'period' => date('Ym'),
                        'article_id' => $article_id,
                        'office_id' => $office_id,
                        'defect' => 0,
                        'tr_type' => 'RP',
                        'qty' => $qtyChange, // **KUNCI:** Mengirim selisih (positif = tambah, negatif = kurang)
                        'wgt' => $wgtChange, // **KUNCI:** Mengirim selisih (positif = tambah, negatif = kurang)
                        'tr_id_ref' => $po_id,
                        'tr_id_seq_ref' => $po_item,
                        'user_id' => $user_id,
                        'amt_item' => $qtyChange * $unitPrice,
                        'unit_price' => $unitPrice,
                        'length' => $dataPO->panjang ?? 1,
                        'satuan' => $dataPO->unit_meas ?? '',
                        'batch_id' => $dataPO->batch_id ?? '',
                        'qty_oh' => $qtyNow, // Ini bisa disesuaikan, tapi qty_oh sering dihitung di dalam storeTransWH
                        'add_id' => '',
                        'tor_id' => '',
                        'spk_id' => '',
                        'spk_item' => 0,
                        'wh_id' => $wh_id,
                        'bin_id' => $bin_id,
                        'remark' => $remark,
                        'uom' => $dataPO->unit_meas ?? '',
                        'berat' => $wgtChange
                    ];

                    $check = $this->storeTransWH($arrayParams);
                    if (isset($check->status) && $check->status === false) {
                        DB::connection('sqlSrvFPI')->rollBack(); 
                        return response()->json([
                            'success' => false,
                            'message' => $check->message ?? 'Gagal menyimpan Transaksi Gudang',
                        ], 500);
                    }

                    
                }
                // END TRANSAKSI GUDANG

                $this->updateTransHdrWithWH($purc_rcv_id, $office_id, 'RP', $wh_id, $bin_id);
            }

            // dd('stop, cek purc rcv dtl selesai');
            
            if($remark === '') {
                $remark = $dataPO->memo_txt ?? '';
            }

            $this->updatePurcRcvHdrQtyWgt($purc_rcv_id, $ac_num_dr, $remark);
            // $this->updateStatusPoHdr($po_id);
            // --- Perbaikan 2: Panggil updateStatusPoHdr per PO ID unik ---
            $uniquePoIds = array_keys($poIdsToUpdate);
            foreach ($uniquePoIds as $poId) {
                // Pemanggilan ini akan mengevaluasi SEMUA ITEM PO secara keseluruhan
                $this->updateStatusPoHdr($poId);
                // $this->updateTransHdrWithWH($purc_rcv_id, $office_id, 'RP', $wh_id, $bin_id);
            }
            // -------------------------------------------------------------
            $tanggal = Carbon::parse($dataPurcRcvHdr->dt_rcv);
            $tahun4digit = $tanggal->format('Y');
            // dd($purc_rcv_id);
            $this->serviceAcc->JrnlPurcReceive(1, $purc_rcv_id, $tahun4digit, $user_id);
            DB::connection('sqlSrvFPI')->commit();
            return response()->json(['status' => 'success', 'message' => 'Data berhasil disimpan.',
            'data' => [
                'purc_rcv_id' => $purc_rcv_id,
                'items' => $savedItems // <--- Menampilkan semua item yang diproses
            ]
            ], 200);
            
        } catch (\Throwable $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return response()->json(['status' => 'error', 'message' => 'Gagal menyimpan data.', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateStatusPoHdr($poId)
    {
        $conn = 'sqlSrvFPI'; // ganti sesuai koneksi gcnDB2

        $status = 'O'; // default

        // 1. Kalau ada item stat = 'R', prioritas tertinggi
        $hasR = DB::connection($conn)
            ->table('po_item')
            ->where('po_id', $poId)
            ->where('stat', 'R')
            ->exists();

        if ($hasR) {
            $status = 'R';
        } else {
            // 2. Kalau ada stat = 'C'
            $hasC = DB::connection($conn)
                ->table('po_item')
                ->where('po_id', $poId)
                ->where('stat', 'C')
                ->exists();

            if ($hasC) {
                $status = 'C';

                // 3. Tapi kalau ada 'O', timpa jadi O
                $hasO = DB::connection($conn)
                    ->table('po_item')
                    ->where('po_id', $poId)
                    ->where('stat', 'O')
                    ->exists();

                if ($hasO) {
                    $status = 'O';
                }
            }
        }

        // 4. Update po_hdr
        DB::connection($conn)
            ->table('po_hdr')
            ->where('po_id', $poId)
            ->update([
                'stat' => $status
            ]);
    }

    private function updatePurcRcvHdrQtyWgt($purc_rcv_id, $ac_num_dr, $remark) //revisi
    {
        $total = DB::connection('sqlSrvFPI')->table('purc_rcv_dtl')
            ->where('purc_rcv_id', $purc_rcv_id)
            ->selectRaw('SUM(qty) as total_qty, SUM(wgt) as total_wgt, SUM(amt_item) as total_amt')
            ->first();

        DB::connection('sqlSrvFPI')->table('purc_rcv_hdr')
            ->where('purc_rcv_id', $purc_rcv_id)
            ->update([
                'qty' => $total->total_qty ?? 0,
                'wgt' => $total->total_wgt ?? 0,
                'amount' => $total->total_amt ?? 0,
                'ac_num_invent' => $ac_num_dr,
                'memo_txt' => $remark,
                'dt_modified' => now('Asia/Jakarta'),
            ]);
    }

    private function updatePoItemAndHdr($poId, $poItem, $qtyChange, $wgtChange, $maxQty)
    {
        // $poItemData = DB::connection('sqlSrvFPI')->table('po_item')
        //     ->where('po_id', $poId)
        //     ->where('po_item', $poItem)
        //     ->first();
        // // dd($poItemData, $qtyNow, $wgtNow, $maxQty);

        // $qtyRcvTotal = ($poItemData->qty_rcv ?? 0) + $qtyNow;
        // $wgtRcvTotal = ($poItemData->wgt_rcv ?? 0) + $wgtNow;
        
        // $stat = ($qtyRcvTotal >= $maxQty) ? 'C' : 'R'; //cekini
        
        // DB::connection('sqlSrvFPI')->table('po_item')
        //     ->where('po_id', $poId)
        //     ->where('po_item', $poItem)
        //     ->update([
        //         'qty_rcv' => $qtyRcvTotal,
        //         'wgt_rcv' => $wgtRcvTotal,
        //         'stat' => $stat,
        //     ]);

        // DB::connection('sqlSrvFPI')->table('po_hdr')
        //     ->where('po_id', $poId)
        //     ->update(['stat' => 'R']);

        // Lakukan UPDATE menggunakan DB::raw() untuk menambahkan/mengurangi $qtyChange
        DB::connection('sqlSrvFPI')->table('po_item')
            ->where('po_id', $poId)
            ->where('po_item', $poItem)
            ->update([
                // Tambahkan $qtyChange (jika $qtyChange positif, ditambah; jika negatif, dikurangi)
                'qty_rcv' => DB::raw("qty_rcv + $qtyChange"), 
                'wgt_rcv' => DB::raw("wgt_rcv + $wgtChange"),
            ]);
            
        // Ambil data yang sudah di-update untuk menghitung status
        $updatedPoItemData = DB::connection('sqlSrvFPI')->table('po_item')
            ->where('po_id', $poId)
            ->where('po_item', $poItem)
            ->first();
            
        // Hitung status baru
        $qtyRcvTotal = $updatedPoItemData->qty_rcv ?? 0;
        $stat = ($qtyRcvTotal >= $maxQty) ? 'C' : 'R'; 

        // Update status
        DB::connection('sqlSrvFPI')->table('po_item')
            ->where('po_id', $poId)
            ->where('po_item', $poItem)
            ->update(['stat' => $stat]);

        // Update po_hdr
        DB::connection('sqlSrvFPI')->table('po_hdr')
            ->where('po_id', $poId)
            ->update(['stat' => 'R']);
    }

    private function updatePrItem($prId, $prItem, $qtyChange, $wgtChange)
    {
        DB::connection('sqlSrvFPI')->table('pr_item')
            ->where('pr_id', $prId)
            ->where('pr_item', $prItem)
            ->update([
                'qty_rcv' => DB::raw("qty_rcv + $qtyChange"),
                'wgt_rcv' => DB::raw("wgt_rcv + $wgtChange"),
            ]);
    }

    private function genPurcInvId($tr_date, $office_id){ //PI
        $date = Carbon::parse($tr_date);
        $ym = $date->format('ym'); // Contoh: 2507
        // dd($tr_date, $date, $ym);
        $prefix = 'PI' . $ym . (int) $office_id; // Hasil: PI25075

        $getLastTrID = DB::connection('sqlSrvFPI')
            ->table('purc_inv_mast')
            ->where('office_id', $office_id)
            ->where(DB::raw("LTRIM(RTRIM(tr_id))"), 'like', $prefix . '%')
            ->selectRaw('MAX(tr_num) AS max_tr')
            ->value('max_tr');
        
        $nextUrut = $getLastTrID ? $getLastTrID + 1 : 1;

        $gen_purc_inv_id = $prefix . str_pad($nextUrut, 3, '0', STR_PAD_LEFT);

        return $gen_purc_inv_id;
    }

    private function genInvId($tr_date, $office_id){ //PINV
        $date = Carbon::parse($tr_date);
        $ym = $date->format('ym'); // Contoh: 2507
        // dd($tr_date, $date, $ym);
        $prefix = 'PINV' . $ym . (int) $office_id; // Hasil: PINV25075
        // dd($prefix);
        $getLastInvID = DB::connection('sqlSrvFPI')
            ->table('purc_rcv_hdr')
            ->where('office_id', $office_id)
            ->where(DB::raw("LTRIM(RTRIM(inv_id))"), 'like', $prefix . '%')
            ->selectRaw('MAX(rcv_num) AS max_tr')
            ->value('max_tr');
        // DD($getLastInvID);
        $nextUrut = $getLastInvID ? $getLastInvID + 1 : 1;

        $gen_inv_id = $prefix . str_pad($nextUrut, 3, '0', STR_PAD_LEFT);

        return $gen_inv_id;
    }

    private function genPurcRcvId($tr_date, $office_id){ //PRC+yyMM+office_id+urut
        $date = Carbon::parse($tr_date);
        $ym = $date->format('ym'); // Contoh: 2507
        // dd($tr_date, $date, $ym);
        $prefix = 'PRC' . $ym . (int) $office_id; // Hasil: PRC25075

        $getLastTrID = DB::connection('sqlSrvFPI')
            ->table('purc_rcv_hdr')
            ->where('office_id', $office_id)
            ->where(DB::raw("LTRIM(RTRIM(purc_rcv_id))"), 'like', $prefix . '%')
            ->selectRaw('MAX(rcv_num) AS max_tr')
            ->value('max_tr');
        // dd($getLastTrID);
        $nextUrut = $getLastTrID ? $getLastTrID + 1 : 1;

        $gen_purc_rcv_id = $prefix . str_pad($nextUrut, 3, '0', STR_PAD_LEFT);

        return $gen_purc_rcv_id;
    }

    private function genNoSJ($tr_date, $office_id){ //PSJ+yyMM+office+id+urut
        $date = Carbon::parse($tr_date);
        $ym = $date->format('ym'); // Contoh: 2507

        $prefix = 'PSJ' . $ym . (int) $office_id; // Hasil: PRC25075

        $getLastTrID = DB::connection('sqlSrvFPI') // sesuaikan dengan koneksi kamu
        ->table('purc_rcv_hdr')
        ->where('office_id', $office_id)
        ->where(DB::raw("LTRIM(RTRIM(sj_no))"), 'like', $prefix . '%')
        ->selectRaw('MAX(sj_num) AS max_sj')
        ->value('max_sj');

        $nextUrut = $getLastTrID ? $getLastTrID + 1 : 1;

        $gen_no_sj = $prefix . str_pad($nextUrut, 3, '0', STR_PAD_LEFT);

        return $gen_no_sj;
    }

    private function GetGenInvTrID($purc_rcv_id){
        // $data = DB::connection('sqlSrvFPI')
        //     ->table('purc_rcv_hdr')
        //     ->selectRaw("
        //         purc_rcv_id,
        //         CONCAT(REPLACE(LEFT(TRIM('$purc_rcv_id'), 7), 'PRC', 'PI'), SUBSTRING('$purc_rcv_id', 8, 1), RIGHT(TRIM('$purc_rcv_id'), 3)) AS tr_id_invoice,
        //         CONCAT(REPLACE(LEFT(TRIM('$purc_rcv_id'), 7), 'PRC', 'PINV'), SUBSTRING('$purc_rcv_id', 8, 1), RIGHT(TRIM('$purc_rcv_id'), 3)) AS inv_id,
        //         CAST(RIGHT(TRIM('$purc_rcv_id'), 3) AS NUMERIC) AS tr_num,
        //         CAST(RIGHT(TRIM('$purc_rcv_id'), 3) AS NUMERIC) AS inv_num
        //     ")
        //     ->first();

        // if($data){
        //     return[
        //         'tr_id_invoice' => $data->tr_id_invoice,
        //         'inv_id' => $data->inv_id,
        //         'tr_num' => $data->tr_num,
        //         'inv_num' => $data->inv_num
        //     ];
        // }
        if ($purc_rcv_id == '' || $purc_rcv_id == null) {
            return [
                'tr_id_invoice' => '',
                'inv_id' => '',
                'tr_num' => '',
                'inv_num' => ''
            ];
        } else {
            $trId = str_replace('PRC', 'PI', $purc_rcv_id);
            $invId = str_replace('PRC', 'PINV', $purc_rcv_id);
            $num = substr($purc_rcv_id, -3);

            return [
                'tr_id_invoice' => $trId,
                'inv_id' => $invId,
                'tr_num' => (int) $num,
                'inv_num' => (int) $num
            ];
        }
    }

    public function destroypurcrcv(Request $request)
    {
        // DB::connection('sqlSrvFPI')->beginTransaction();

        try {
            $office_id = $request->office_id;
            $purc_rcv_id = $request->purc_rcv_id;
            $seq_num = $request->seq_num;

            $rcvDtl = DB::connection('sqlSrvFPI')->table('purc_rcv_dtl')
                ->where('office_id', $office_id)
                ->where('purc_rcv_id', $purc_rcv_id)
                ->where('seq_num', $seq_num)
                ->first();
            // dd($rcvDtl);
            if (!$rcvDtl) {
                return response()->json(['error' => 'Data tidak ditemukan'], 404);
            }

            $tr_id_invoice = $rcvDtl->tr_id_invoice;
            $inv_id = $rcvDtl->inv_id ?? '';
            
            $prevQty    = $rcvDtl->qty;
            $prevWgt    = $rcvDtl->wgt;
            $prevAmount = $rcvDtl->amt_item;
            $po_id = $rcvDtl->po_id;
            $po_item = $rcvDtl->po_item;
            $pr_id = $rcvDtl->pr_id;
            $pr_item = $rcvDtl->pr_item;
            // $item = $rcvDtl->item;
            // $warehouse = $rcvDtl->warehouse;
            // $trans_id = $rcvDtl->trans_id;

            // Hapus dari inv_trans_dtl & inv_trans_hdr
            $user_id = getAuth($request)->user_id2;

            $arrayParams = (object)[
                'tr_type' => 'RP',
                'office_id' => $office_id,
                'tr_id' => $purc_rcv_id,
                'seq_id' => $seq_num,
                'article_id' => $rcvDtl->article_id ?? '',
                'length' => $rcvDtl->length ?? 1,
                'user_id' => $user_id,
                'period' => date('Ym'),
                'wh_id' => $rcvDtl->wh_id ?? '',
                'bin_id' => $rcvDtl->bin_id ?? ''
            ];
            // dd($arrayParams);
            $check = $this->deleteTransWh($arrayParams);

            if (isset($check->status) && $check->status === false) {
                return response()->json([
                    'success' => false,
                    'message' => $check->message ?? 'Gagal menyimpan Transaksi Header',
                ], 500);
            }

            // Hapus purc_rcv_dtl (baris detail yang sedang dihapus)
            $deleted = DB::connection('sqlSrvFPI')->table('purc_rcv_dtl')
                ->where('office_id', $office_id)
                ->where('purc_rcv_id', $purc_rcv_id)
                ->where('seq_num', $seq_num)
                ->delete();

            if (!$deleted) {
                // DB::connection('sqlSrvFPI')->rollBack();
                return response()->json(['error' => 'Gagal menghapus purc_rcv_dtl'], 500);
            }

             // Update / hapus purc_rcv_hdr
            $cekDtl = DB::connection('sqlSrvFPI')->table('purc_rcv_dtl')
                ->where('office_id', $office_id)
                ->where('purc_rcv_id', $purc_rcv_id)
                ->count();

            if ($cekDtl == 0) {
                DB::connection('sqlSrvFPI')->table('purc_rcv_hdr')
                    ->where('office_id', $office_id)
                    ->where('purc_rcv_id', $purc_rcv_id)
                    ->delete();
            } else {
                DB::connection('sqlSrvFPI')->table('purc_rcv_hdr')
                    ->where('office_id', $office_id)
                    ->where('purc_rcv_id', $purc_rcv_id)
                    ->decrementEach([
                        'qty' => $prevQty,
                        'wgt' => $prevWgt,
                        'amount' => $prevAmount
                    ]);
            }

            
            // DB::connection('sqlSrvFPI')->table('inv_trans_dtl')
            //     ->where('office_id', $office_id)
            //     ->where('trans_id', $trans_id)
            //     ->delete();

            // $cekInvDtl = DB::connection('sqlSrvFPI')->table('inv_trans_dtl')
            //     ->where('office_id', $office_id)
            //     ->where('trans_id', $trans_id)
            //     ->count();

            // if ($cekInvDtl == 0) {
            //     DB::connection('sqlSrvFPI')->table('inv_trans_hdr')
            //         ->where('office_id', $office_id)
            //         ->where('trans_id', $trans_id)
            //         ->delete();
            // }

            //  Update PO item & header
            if ($po_id !== null && $po_item !== null) {
                // DB::connection('sqlSrvFPI')->table('po_item')
                //     ->where('office_id', $office_id)
                //     ->where('po_id', $po_id)
                //     // ->where('item', $item)
                //     ->where('po_item', $po_item)
                //     ->decrementEach([
                //         'qty_rcv' => $prevQty,
                //         'wgt_rcv' => $prevWgt
                //     ]);

                // // Cek apakah semua po_item selesai?
                // $cekPOOpen = DB::connection('sqlSrvFPI')->table('po_item')
                //     ->where('office_id', $office_id)
                //     ->where('po_id', $po_id)
                //     ->where('stat', '<>', 'C')
                //     ->count();

                // $poStat = ($cekPOOpen > 0) ? 'O' : 'C';
                // DB::connection('sqlSrvFPI')->table('po_hdr')
                //     ->where('office_id', $office_id)
                //     ->where('po_id', $po_id)
                //     ->update(['stat' => $poStat]);

                // 1. Ambil po_item
                $poItemData = DB::connection('sqlSrvFPI') // ganti sesuai koneksi DB1
                    ->table('po_item')
                    ->where('po_id', $po_id)
                    ->where('po_item', (int) $po_item)
                    ->first();

                if ($poItemData) {
                    // Hitung qty & wgt baru
                    $newQty = ($poItemData->qty_rcv ?? 0) - $prevQty;
                    $newWgt = ($poItemData->wgt_rcv ?? 0) - $prevWgt;

                    // Tentukan status
                    $newStatus = (($newQty + $newWgt) == 0) ? 'O' : 'R';

                    // Update po_item
                    DB::connection('sqlSrvFPI')
                        ->table('po_item')
                        ->where('po_id', $po_id)
                        ->where('po_item', (int) $po_item)
                        ->update([
                            'qty_rcv' => $newQty,
                            'wgt_rcv' => $newWgt,
                            'stat'    => $newStatus
                        ]);
                    }

                    // 2. Cek apakah masih ada item yang statusnya bukan 'C'
                    $hasNonC = DB::connection('sqlSrvFPI')
                        ->table('po_item')
                        ->where('po_id', $po_id)
                        ->whereNotIn('stat', ['C'])
                        ->exists();

                    // Update po_hdr sesuai hasil pengecekan
                    DB::connection('sqlSrvFPI')
                        ->table('po_hdr')
                        ->where('po_id', $po_id)
                        ->update([
                            'stat' => $hasNonC ? 'O' : 'C'
                        ]);
            }

            //  Update PR item & header
            if ($pr_id !== null && $pr_item !== null) {
                // DB::connection('sqlSrvFPI')->table('pr_item')
                //     ->where('office_id', $office_id)
                //     ->where('pr_id', $pr_id)
                //     // ->where('item', $item)
                //     ->where('pr_item', $pr_item)
                //     ->decrementEach([
                //         'qty_rcv' => $prevQty,
                //         'wgt_rcv' => $prevWgt
                //     ]);

                // $cekPROpen = DB::connection('sqlSrvFPI')->table('pr_item')
                //     ->where('office_id', $office_id)
                //     ->where('pr_id', $pr_id)
                //     ->where('stat', '<>', 'C')
                //     ->count();

                // $prStat = ($cekPROpen > 0) ? 'O' : 'C';
                // DB::connection('sqlSrvFPI')->table('pr_hdr')
                //     ->where('office_id', $office_id)
                //     ->where('pr_id', $pr_id)
                //     ->update(['stat' => $prStat]);
                // 1. Ambil data pr_item
                $prItemData = DB::connection('sqlSrvFPI') // ganti koneksi sesuai gcnDB1
                    ->table('pr_item')
                    ->where('pr_id', $pr_id)
                    ->where('pr_item', (int) $pr_item)
                    ->first();

                if ($prItemData) {
                    // Hitung qty & wgt baru
                    $newQty = ($prItemData->qty_rcv ?? 0) - $prevQty;
                    $newWgt = ($prItemData->wgt_rcv ?? 0) - $prevWgt;

                    // Tentukan status
                    $newStatus = (($newQty + $newWgt) == 0) ? 'O' : 'R';

                    // Update pr_item
                    DB::connection('sqlSrvFPI')
                        ->table('pr_item')
                        ->where('pr_id', $pr_id)
                        ->where('pr_item', (int) $pr_item)
                        ->update([
                            'qty_rcv' => $newQty,
                            'wgt_rcv' => $newWgt,
                            'stat'    => $newStatus
                        ]);
                }

                // 2. Cek apakah masih ada item yang statusnya bukan 'C'
                $hasNonC = DB::connection('sqlSrvFPI')
                    ->table('pr_item')
                    ->where('pr_id', $pr_id)
                    ->whereNotIn('stat', ['C'])
                    ->exists();

                // Update pr_hdr sesuai hasil pengecekan
                DB::connection('sqlSrvFPI')
                    ->table('pr_hdr')
                    ->where('pr_id', $pr_id)
                    ->update([
                        'stat' => $hasNonC ? 'O' : 'C'
                    ]);
            }


            //  Hapus dari purc_inv_item dan purc_inv_mast jika tidak ada detail
            DB::connection('sqlSrvFPI')->table('purc_inv_item')
                ->where('office_id', $office_id)
                ->where('tr_id', $tr_id_invoice)
                ->where('inv_id', $inv_id)
                ->where('seq_num', $seq_num)
                ->delete();

            $cekInvItem = DB::connection('sqlSrvFPI')->table('purc_inv_item')
                ->where('office_id', $office_id)
                ->where('tr_id', $tr_id_invoice)
                ->where('inv_id', $inv_id)
                ->count();

            if ($cekInvItem == 0) {
                DB::connection('sqlSrvFPI')->table('purc_inv_mast')
                    ->where('office_id', $office_id)
                    ->where('tr_id', $tr_id_invoice)
                    ->where('inv_id', $inv_id)
                    ->delete();
            }

            // 7. Update inventory balance (stok)
            // $this->updateInvBal([
            //     'office_id' => $office_id,
            //     'item' => $item,
            //     'warehouse' => $warehouse,
            //     'qty' => -$prevQty,
            //     'weight' => -$prevWgt,
            // ]);

            // 8. Update vendor year summary
            // $this->updateVendorYear([
            //     'vendor_id' => $rcvDtl->vendor_id ?? null,
            //     'amount' => -$prevAmount,
            //     'date' => $rcvDtl->rcv_date ?? now()
            // ]);

            // DB::connection('sqlSrvFPI')->commit();
            // return response()->json(['success' => true]);
            // return response()->json(['status' => 'success', 'message' => 'Data berhasil disimpan.']);

            $jvnumpurchrcv= DB::connection('sqlSrvFPI')->table('journal_voucher')
                ->distinct()
                ->where('source_num', $purc_rcv_id)
                ->pluck('jv_num');
                // ->get();


            $jvnumpurchinv= DB::connection('sqlSrvFPI')->table('journal_voucher')
                ->distinct()
                ->where('inv_id', $inv_id)
                ->pluck('jv_num');
                // ->get();

            
            // dd($jvnumpurchrcv, $jvnumpurchinv);
            $tanggal = Carbon::parse($rcvDtl->dt_created);
            $tahun4digit = $tanggal->format('Y');

            foreach ($jvnumpurchrcv as $jv_num) {
                $this->serviceAcc->DeleteJournalByJvNum(1, (float)$jv_num, $tahun4digit);
            }
            $autoInv = $this->getInt1ApplConst('AUTOINV', 'AUTOINV');
            if ($autoInv == 1) {
                foreach ($jvnumpurchinv as $jv_num) {
                    $this->serviceAcc->DeleteJournalByJvNum(1, (float)$jv_num, $tahun4digit);
                }
            }
            return $this->sendResponse('success' , 'Successfully Delete data');

        } catch (\Exception $e) {
            // DB::connection('sqlSrvFPI')->rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }

    }

    // getInvOh($dataPO->office_id, $articleId, $dataPO->batch_id, $dataPO->wh_id, $dataPO->bin_id, $dataPO->panjang, $dataPO->unit_meas);

    private function getInvOh($officeId, $pCode, $batch = null, $wh = null, $bin = null, $panjang, $unitMeas)
    {
        $query = DB::connection('sqlSrvFPI')->table('inv_bal')
            ->select('qty_oh')
            ->where('office_id', (int)$officeId)
            ->where('article_id', trim($pCode));
            // ->where('defect', (int)$isDefect);

        if (!empty($batch)) {
            $query->where('batch_id', trim($batch));
        }

        if (!empty($wh)) {
            $query->where('wh_id', trim($wh));
        }

        if (!empty($bin)) {
            $query->where('bin_id', trim($bin));
        }

        if (trim($unitMeas) === 'M') {
            $query->where('length', (float)$panjang);
        }

        $result = $query->first();

        return $result ? (float)$result->qty_oh : 0;
    }

    //input wh_id, wh_dest dan bin_dest pada tabel inv_trans_hdr
    private function updateTransHdrWithWH($tr_id, $office_id, $tr_type, $wh_id, $bin_id)
    {
        DB::connection('sqlSrvFPI')
            ->table('inv_trans_hdr')
            ->where('tr_id', $tr_id)
            ->where('office_id', $office_id)
            ->where('tr_type', $tr_type)
            ->update([
                'wh_id' => $wh_id,
                'wh_dest' => $wh_id,
                'bin_dest' => $bin_id,
            ]);
    }

    /*--------------------------dari warehouse trait----------------------------- */
    public function storeTransHdr($arrayParams)
    {
        try {
            // $yesterday_date = Carbon::parse($arrayParams->tr_date)->subDays(1)->format('Y-m-d');

            $current =  DB::connection("sqlSrvFPI")
                            ->table('period')
                            ->where('dept_id', 'PRODWH')
                            ->where('office_id', $arrayParams->office_id)
                            ->first();

            // dd($arrayParams->period);

            if ($current) {
                $period = $arrayParams->period;
                if ($current->curr_period == $period) {

                //     $yesterday_daily =  DB::connection("sqlSrvFPI")
                //                         ->table('period_daily')
                //                         ->where('dept_id', 'PRODWH')
                //                         ->where('office_id', $arrayParams->office_id)
                //                         ->where('close_date', $yesterday_date)
                //                         ->first();

                    // if ($yesterday_daily) {

                            // $data = DB::connection('sqlSrvFPI')
                            //             ->table('inv_trans_hdr')
                            //             ->where('office_id', $arrayParams->office_id)
                            //             ->where('tr_type', $arrayParams->tr_type)
                            //             ->where('tr_date', $arrayParams->tr_date)
                            //             // ->where('wh_id', $arrayParams->wh_id)
                            //             // ->where('bin_id', $arrayParams->bin_id)
                            //             ->first();

                            // if ($data) {
                            //     $transId = $data->tr_id;
                            // } else {
                            //     $transId = $arrayParams->tr_id;

                            //     $header = DB::connection('sqlSrvFPI')
                            //                 ->table('inv_trans_hdr')
                            //                 ->insert([
                            //                     'office_id' => $arrayParams->office_id,
                            //                     'tr_type' => $arrayParams->tr_type,
                            //                     'tr_id' => $transId,
                            //                     'tr_date' => $arrayParams->tr_date,
                            //                     // 'wh_id' => $arrayParams->wh_id,
                            //                     // 'bin_id' => $arrayParams->bin_id,
                            //                     'tr_id_ref'    => property_exists($arrayParams, 'tr_id_ref') ? $arrayParams->tr_id_ref : '',
                            //                     'tr_id_ref2'   => property_exists($arrayParams, 'tr_id_ref2') ? $arrayParams->tr_id_ref2 : '',
                            //                     'dt_modified' => now(),
                            //                     'user_id' => $arrayParams->user_id
                            //                     // 'payment' => $arrayParams->payment
                            //                 ]);
                            // }
                            $transId = $arrayParams->tr_id;

                            DB::connection('sqlSrvFPI')
                            ->table('inv_trans_hdr')
                            ->updateOrInsert(
                                [
                                    'office_id' => $arrayParams->office_id,
                                    'tr_type' => $arrayParams->tr_type,
                                    'tr_id' => $transId,
                                ],
                                [
                                    'tr_date' => $arrayParams->tr_date,
                                    'tr_id_ref' => property_exists($arrayParams, 'tr_id_ref') ? $arrayParams->tr_id_ref : '',
                                    'tr_id_ref2' => property_exists($arrayParams, 'tr_id_ref2') ? $arrayParams->tr_id_ref2 : '',
                                    'dt_modified' => now('Asia/Jakarta'),
                                    'user_id' => $arrayParams->user_id
                                ]
                            );

                            return (object)[
                                'status' => TRUE,
                                'message' => 'Success',
                                'response_time' => (microtime(TRUE) - LARAVEL_START),
                                'data' => $transId ? $transId : NULL
                            ];

                    // } else {
                    //     return (object)[
                    //         'status' => FALSE,
                    //         'message' => 'Belum di closing Harian',
                    //         'response_time' => (microtime(TRUE) - LARAVEL_START),
                    //         'data' => NULL
                    //     ];
                    // }  

                    
                } 
                else {
                    return (object)[
                        'status' => FALSE,
                        'message' => 'Belum di closing',
                        'response_time' => (microtime(TRUE) - LARAVEL_START),
                        'data' => NULL
                    ];
                }   
            } 
            else {
                return (object)[
                    'status' => FALSE,
                    'message' => 'Belum di closing',
                    'response_time' => (microtime(TRUE) - LARAVEL_START),
                    'data' => NULL
                ];
            } 
            
        }
        catch (\Exception $e) {
            return (object)[
                'status' => FALSE,
                'message' => $e->getMessage(),
                'response_time' => (microtime(TRUE) - LARAVEL_START),
                'data' => NULL
            ];
        }

    }

    public function storeTransWH($arrayParams)
    {
        // $yesterday_date = Carbon::parse($arrayParams->tr_date)->subDays(1)->format('Y-m-d');

        $current =  DB::connection("sqlSrvFPI")
                        ->table('period')
                        ->where('dept_id', 'PRODWH')
                        ->where('office_id', $arrayParams->office_id)
                        ->first();

        if ($current) {
            $period = $arrayParams->period;
            if ($current->curr_period == $period) {

                // $yesterday_daily =  DB::connection("sqlSrvFPI")
                //                     ->table('period_daily')
                //                     ->where('dept_id', 'PRODWH')
                //                     ->where('office_id', $arrayParams->office_id)
                //                     ->where('close_date', $yesterday_date)
                //                     ->first();

                // if ($yesterday_daily) {

                        $tr_type = $this->caseTrType($arrayParams->tr_type);
                        if ($tr_type == 'R') {
                            $tr_types = ['MR', 'RC', 'RW', 'SR', 'TR', 'RP'];
                        } elseif ($tr_type == 'I') {
                            $tr_types = ['IS', 'IW', 'MI', 'TI', 'PR', 'SA'];
                        } elseif ($tr_type == 'A') {
                            $tr_types = ['IA'];
                        }  else {
                            return (object)[
                                'status' => FALSE,
                                'message' => 'Please insert TR Type',
                                'response_time' => (microtime(true) - LARAVEL_START),
                                'data' => NULL
                            ];
                        }

                        $cek_stock = DB::connection("sqlSrvFPI")
                                    ->table('inv_bal')
                                    ->where('office_id', $arrayParams->office_id)
                                    ->where('article_id',$arrayParams->article_id)
                                    ->where('defect',$arrayParams->defect ?? 0)
                                    ->where('batch_id', $arrayParams->batch_id ?? '')
                                    ->where('length', $arrayParams->length)
                                    ->where('add_id', $arrayParams->add_id ? $arrayParams->add_id : '')
                                    ->where('tor_id', $arrayParams->tor_id ? $arrayParams->tor_id : '')
                                    ->where('wh_id', $arrayParams->wh_id)
                                    ->where('bin_id',$arrayParams->bin_id)
                                    ->where('spk_id',$arrayParams->spk_id ? $arrayParams->spk_id : '')
                                    ->where('spk_item',$arrayParams->spk_item ? $arrayParams->spk_item : 0)
                                    ->first();

                        if ((float) $arrayParams->qty_oh < 0) {
                            if ($cek_stock) {
                                $calculate_stock = (float) $cek_stock->qty_oh + (float) $arrayParams->qty_oh;
                                if ($calculate_stock < 0) {
                                    return (object)[
                                        'status' => FALSE,
                                        'message' => 'Maaf stock anda kurang',
                                        'response_time' => (microtime(true) - LARAVEL_START),
                                        'data' => NULL
                                    ];
                                }
                            } else {
                                return (object)[
                                    'status' => FALSE,
                                    'message' => 'Maaf stock anda belum ada',
                                    'response_time' => (microtime(true) - LARAVEL_START),
                                    'data' => NULL
                                ];
                            }                      
                        }

                        $seq_id = DB::connection("sqlSrvFPI")
                                    ->table('inv_trans_dtl')
                                    ->select('seq_id')
                                    ->where('office_id', $arrayParams->office_id)
                                    ->where('tr_type', $arrayParams->tr_type)
                                    ->where('tr_id', $arrayParams->tr_id)
                                    ->max('seq_id');

                        // $insertItem = DB::connection('sqlSrvFPI')
                        //             ->table('inv_trans_dtl')
                        //             ->insert([
                        //                 'office_id' => $arrayParams->office_id,
                        //                 'tr_type' => $arrayParams->tr_type,
                        //                 'tr_id' => $arrayParams->tr_id,
                        //                 'seq_id' => $seq_id + 1,
                        //                 'article_id' => $arrayParams->article_id,
                        //                 'wh_id' => $arrayParams->wh_id ?? '',
                        //                 'bin_id' => $arrayParams->bin_id ?? '',       
                        //                 'defect' => $arrayParams->defect ?? 0,
                        //                 'batch_id' => $arrayParams->batch_id ?? '',
                        //                 'add_id' => $arrayParams->add_id ?? '',
                        //                 'tor_id' => $arrayParams->tor_id ?? '',
                        //                 'spk_id' => $arrayParams->spk_id ?? '',
                        //                 'spk_item' => $arrayParams->spk_item ?? 0,
                        //                 'length' => (float)$arrayParams->length,
                        //                 'amt_item' => (float)$arrayParams->amt_item ? (float)$arrayParams->amt_item : 0,
                        //                 'unit_price' => (float)$arrayParams->unit_price ? (float)$arrayParams->unit_price : 0,                                    
                        //                 'qty' => (int) $arrayParams->qty_oh,
                        //                 'wgt' => $arrayParams->satuan == 'M' ? (int) $arrayParams->qty_oh * (float)$arrayParams->length * (float)$arrayParams->berat : (int) $arrayParams->qty_oh * (float)$arrayParams->berat,                                    
                        //                 'remark' => $arrayParams->remark ? $arrayParams->remark : '',
                        //                 'period' => $arrayParams->period,
                        //                 'tr_id_ref' => $arrayParams->tr_id_ref ? $arrayParams->tr_id_ref : '',
                        //                 'tr_id_seq_ref' => $arrayParams->tr_id_seq_ref ? $arrayParams->tr_id_seq_ref : '',
                        //                 'dt_modified' => now('Asia/Jakarta'),
                        //                 'user_id' => $arrayParams->user_id,
                        //                 'uom' => $arrayParams->satuan ? $arrayParams->satuan : '',
                        //                 'wgt_unit' => $arrayParams->berat ? (float)$arrayParams->berat : 0
                        //             ]);

                        $insertItem = DB::connection('sqlSrvFPI')
                            ->table('inv_trans_dtl')
                            ->updateOrInsert(
                                [
                                    'office_id' => $arrayParams->office_id,
                                    'tr_type' => $arrayParams->tr_type,
                                    'tr_id' => $arrayParams->tr_id,
                                    'seq_id' => $seq_id + 1,
                                ],
                                [
                                    'article_id' => $arrayParams->article_id,
                                    'wh_id' => $arrayParams->wh_id ?? '',
                                    'bin_id' => $arrayParams->bin_id ?? '',
                                    'defect' => $arrayParams->defect ?? 0,
                                    'batch_id' => $arrayParams->batch_id ?? '',
                                    'add_id' => $arrayParams->add_id ?? '',
                                    'tor_id' => $arrayParams->tor_id ?? '',
                                    'spk_id' => $arrayParams->spk_id ?? '',
                                    'spk_item' => $arrayParams->spk_item ?? 0,
                                    'length' => (float)$arrayParams->length,
                                    'amt_item' => (float)$arrayParams->amt_item ? (float)$arrayParams->amt_item : 0,
                                    'unit_price' => (float)$arrayParams->unit_price ? (float)$arrayParams->unit_price : 0,
                                    'qty' => (float)$arrayParams->qty_oh,
                                    'wgt' => $arrayParams->satuan == 'M' ? (float)$arrayParams->qty_oh * (float)$arrayParams->length * (float)$arrayParams->berat : (float)$arrayParams->qty_oh * (float)$arrayParams->berat,
                                    'remark' => $arrayParams->remark ? $arrayParams->remark : '',
                                    'period' => $arrayParams->period,
                                    'tr_id_ref' => $arrayParams->tr_id_ref ? $arrayParams->tr_id_ref : '',
                                    'tr_id_seq_ref' => $arrayParams->tr_id_seq_ref ? $arrayParams->tr_id_seq_ref : '',
                                    'dt_modified' => now('Asia/Jakarta'),
                                    'user_id' => $arrayParams->user_id,
                                    'uom' => $arrayParams->satuan ? $arrayParams->satuan : '',
                                    'wgt_unit' => $arrayParams->berat ? (float)$arrayParams->berat : 0
                                ]
                            );

                        $transQty = $this->GetQtyTransInvDetil($arrayParams, $tr_type, $tr_types);
                        $updateQty = $this->updateQtyInvBal($transQty ? ($transQty->qty ? (float) $transQty->qty : 0) : 0, $transQty ? ($transQty->wgt ? (double) $transQty->wgt : 0) : 0, $arrayParams, $tr_type, $tr_types,false);

                        return (object)[
                            'status' => TRUE,
                            'message' => 'Successfully saved',
                            'response_time' => (microtime(true) - LARAVEL_START),
                            'data' => $arrayParams->tr_id
                        ];
                // }                           
                
            } else {
                return (object)[
                    'status' => FALSE,
                    'message' => 'Belum di closing',
                    'response_time' => (microtime(true) - LARAVEL_START),
                    'data' => NULL
                ];
            }   
        } else {
            return (object)[
                'status' => FALSE,
                'message' => 'Belum di closing',
                'response_time' => (microtime(true) - LARAVEL_START),
                'data' => NULL
            ];
        } 

    }

    public function deleteTransWh($arrayParams)
    {
        // dd('masuk');
        // $yesterday_date = Carbon::parse($arrayParams->tr_date)->subDays(1)->format('Y-m-d');

        $current =  DB::connection("sqlSrvFPI")
                        ->table('period')
                        ->where('dept_id', 'PRODWH')
                        ->where('office_id', $arrayParams->office_id)
                        ->first();

        if ($current) {
            $period = $arrayParams->period;
            if ($current->curr_period == $period) {

                // $yesterday_daily =  DB::connection("sqlSrvFPI")
                //                     ->table('period_daily')
                //                     ->where('dept_id', 'PRODWH')
                //                     ->where('office_id', $arrayParams->office_id)
                //                     ->where('close_date', $yesterday_date)
                //                     ->first();

                // if ($yesterday_daily) {

                        $tr_type = $this->caseTrType($arrayParams->tr_type);
                        if ($tr_type == 'R') {
                            $tr_types = ['MR', 'RC', 'RW', 'SR', 'TR', 'RP'];
                        } elseif ($tr_type == 'I') {
                            $tr_types = ['IS', 'IW', 'MI', 'TI', 'PR', 'SA'];
                        } elseif ($tr_type == 'A') {
                            $tr_types = ['IA'];
                        }  else {
                            return (object)[
                                'status' => FALSE,
                                'message' => 'Please insert TR Type',
                                'response_time' => (microtime(true) - LARAVEL_START),
                                'data' => NULL
                            ];
                        }
                        
                        $data = DB::connection("sqlSrvFPI")
                                ->table('inv_trans_dtl')
                                ->where('office_id', $arrayParams->office_id)
                                ->where('tr_type', $arrayParams->tr_type)
                                ->where('tr_id', $arrayParams->tr_id)
                                ->where('seq_id', $arrayParams->seq_id)
                                ->first();
                        // dd($data->qty);
                        if ($data){
                            $cek_stock = DB::connection("sqlSrvFPI")
                                        ->table('inv_bal')
                                        ->where('office_id', $arrayParams->office_id)
                                        ->where('article_id',$arrayParams->article_id)
                                        ->where('defect',$arrayParams->defect ?? 0)
                                        ->where('batch_id', $arrayParams->batch_id ?? '')
                                        // ->where('length', $arrayParams->length)
                                        ->where('add_id', $arrayParams->add_id ?? '')
                                        ->where('tor_id', $arrayParams->tor_id ?? '')
                                        ->where('wh_id', $arrayParams->wh_id)
                                        ->where('bin_id',$arrayParams->bin_id)
                                        ->where('spk_id',$arrayParams->spk_id ?? '')
                                        ->where('spk_item',$arrayParams->spk_item ?? 0)
                                        ->first();
                            // dd($cek_stock);
                            // dd($data->qty, $cek_stock->qty_oh);
                            if ((float) $data->qty > 0) {
                                if ($cek_stock) {
                                    if ((float) $cek_stock->qty_oh < (float) $data->qty) {
                                        return (object)[
                                            'status' => FALSE,
                                            'message' => 'Maaf stock anda kurang',
                                            'response_time' => (microtime(true) - LARAVEL_START),
                                            'data' => NULL
                                        ];
                                    }
                                } else {
                                    return (object)[
                                        'status' => FALSE,
                                        'message' => 'Maaf stock anda belum ada',
                                        'response_time' => (microtime(true) - LARAVEL_START),
                                        'data' => NULL
                                    ];
                                } 
                            }
                            // BUAT OBJECT BARU UNTUK MENGIRIM DATA YANG BENAR KE FUNGSI UPDATE
                            // KARENA $data BERISI QTY & HARGA DARI TRANSAKSI YANG DIHAPUS
                            $reversalParams = clone $arrayParams;
                            $reversalParams->qty_oh = $data->qty; // Gunakan qty dari tabel inv_trans_dtl
                            $reversalParams->unit_price = $data->unit_price; // Gunakan unit_price dari inv_trans_dtl

                            $delete = DB::connection("sqlSrvFPI")
                                    ->table('inv_trans_dtl')
                                    ->where('office_id', $arrayParams->office_id)
                                    ->where('tr_type', $arrayParams->tr_type)
                                    ->where('tr_id', $arrayParams->tr_id)
                                    ->where('seq_id', $arrayParams->seq_id)
                                    ->delete();

                            $data_hdr_wh_transfer = DB::connection("sqlSrvFPI")
                                                    ->table('inv_trans_dtl')
                                                    ->where('office_id', $arrayParams->office_id)
                                                    ->where('tr_type', $arrayParams->tr_type)
                                                    ->where('tr_id', $arrayParams->tr_id)
                                                    ->count();

                            if ((int) $data_hdr_wh_transfer == 0) {
                                $delete_wh_hdr_transfer = DB::connection("sqlSrvFPI")
                                                            ->table('inv_trans_hdr')
                                                            ->where('office_id', $arrayParams->office_id)
                                                            ->where('tr_type', $arrayParams->tr_type)
                                                            ->where('tr_id', $arrayParams->tr_id)
                                                            ->delete();
                            }


                            $transQty = $this->GetQtyTransInvDetil($arrayParams, $tr_type, $tr_types);
                            
                            $updateQty = $this->updateQtyInvBal(
                                $transQty ? ($transQty->qty ? (float) $transQty->qty : 0) : 0,
                                $transQty ? ($transQty->wgt ? (double) $transQty->wgt : 0) : 0,
                                // $arrayParams,
                                $reversalParams,
                                $tr_type,
                                $tr_types,
                                true
                            );

                            return (object)[
                                'status' => TRUE,
                                'message' => 'Successfully saved',
                                'response_time' => (microtime(true) - LARAVEL_START),
                                'data' => NULL
                            ];


                        } else {
                            return (object)[
                                'status' => FALSE,
                                'message' => 'Data not exists',
                                'response_time' => (microtime(true) - LARAVEL_START),
                                'data' => NULL
                            ];
                    }                    
                // }  else {
                //     return (object)[
                //         'status' => FALSE,
                //         'message' => 'Belum di closing',
                //         'response_time' => (microtime(true) - LARAVEL_START),
                //         'data' => NULL
                //     ];
                // }                          
                
            } else {
                return (object)[
                    'status' => FALSE,
                    'message' => 'Belum di closing',
                    'response_time' => (microtime(true) - LARAVEL_START),
                    'data' => NULL
                ];
            }   
        } 
        else {
            return (object)[
                'status' => FALSE,
                'message' => 'Belum di closing',
                'response_time' => (microtime(true) - LARAVEL_START),
                'data' => NULL
            ];
        } 

    }

    private function GetQtyTransInvDetil($arrayParams, $tr_type, $tr_types)
    {
        $data = DB::connection('sqlSrvFPI')
                    ->table('inv_trans_dtl')
                    ->selectRaw('sum(qty) as qty, sum(wgt) as wgt')
                    ->where('office_id', $arrayParams->office_id)
                    ->whereIn('tr_type', $tr_types)
                    ->where('article_id', $arrayParams->article_id)
                    ->where('wh_id', $arrayParams->wh_id ?? '')
                    ->where('bin_id', $arrayParams->bin_id ?? '')
                    ->where('batch_id', $arrayParams->batch_id ?? '')
                    ->where('period', $arrayParams->period ?? '')
                    ->where('add_id', $arrayParams->add_id ?? '')
                    ->where('tor_id', $arrayParams->tor_id ?? '')
                    ->where('length', $arrayParams->length)
                    ->where('defect', $arrayParams->defect ?? 0)
                    ->where('spk_id', $arrayParams->spk_id ?? '')
                    ->where('spk_item', $arrayParams->spk_item ?? 0)                    
                    ->first();
        // dd($data);
        return $data;
    }

    public function updateQtyInvBal($QtyTrans, $WgtTrans, $arrayParams, $tr_type, $tr_types,$is_reversal = false)
    {
        $data = DB::connection("sqlSrvFPI")
                ->table('inv_bal')
                ->where('office_id', $arrayParams->office_id)
                ->where('article_id',$arrayParams->article_id)
                ->where('defect',$arrayParams->defect ?? 0)
                ->where('batch_id', $arrayParams->batch_id ?? '')
                ->where('length', $arrayParams->length)
                ->where('add_id', $arrayParams->add_id ?? '')
                ->where('tor_id', $arrayParams->tor_id ?? '')
                ->where('wh_id', $arrayParams->wh_id ?? '')
                ->where('bin_id',$arrayParams->bin_id ?? '')
                ->where('spk_id',$arrayParams->spk_id ?? '')
                ->where('spk_item',$arrayParams->spk_item ?? 0)
                ->first();

        if ($data) {
            $hitung_qty_oh = 0;
            $hitung_wgt_oh = 0;
            if ($tr_type == 'R') {
                $hitung_qty_oh = (float) $data->qty_awal + (float) $QtyTrans - (float) $data->qty_iss + (float) $data->qty_ia - (float) $data->qty_waste;
                $hitung_wgt_oh = (double) $data->wgt_awal + (double) $WgtTrans - (double) $data->wgt_iss + (double) $data->wgt_ia - (double) $data->wgt_waste;
            } elseif ($tr_type == 'I') {
                $hitung_qty_oh = (float) $data->qty_awal + (float) $data->qty_rcv - (float) $QtyTrans + (float) $data->qty_ia - (float) $data->qty_waste;
                $hitung_wgt_oh = (double) $data->wgt_awal + (double) $data->wgt_rcv - (double) $WgtTrans + (double) $data->wgt_ia - (double) $data->wgt_waste;
            } elseif ($tr_type == 'A') {
                $hitung_qty_oh = (float) $data->qty_awal + (float) $data->qty_rcv - (float) $data->qty_iss + (float) $QtyTrans - (float) $data->qty_waste;
                $hitung_wgt_oh = (double) $data->wgt_awal + (double) $data->wgt_rcv - (double) $data->wgt_iss + (double) $WgtTrans - (double) $data->wgt_waste;
            }
            // ========== LOGIKA BARU UNTUK HARGA DIMULAI DI SINI ==========
            $new_avg_price = (float) $data->avg_price; // Default ke harga lama
            $new_last_price = (float) $data->last_price; // Default ke harga lama

            $transaction_qty = (float) $arrayParams->qty_oh;
            $transaction_price = (float) $arrayParams->unit_price;
        
            // Hanya hitung jika qty_oh akhir lebih dari 0
            if ($hitung_qty_oh > 0) {
                if ($is_reversal) {
                    // RUMUS UNTUK MENGHAPUS DATA
                    $current_total_value = (float) $data->last_price * (float) $data->qty_oh;
                    $reversed_value = $transaction_qty * $transaction_price;
                    $denominator = (float) $data->qty_oh - $transaction_qty;
                    
                    if ($denominator > 0) {
                        $new_avg_price = ($current_total_value - $reversed_value) / $denominator;
                    } else {
                        $new_avg_price = 0; // Jika stok jadi 0, harga rata-rata 0
                    }

                } else {
                    // RUMUS UNTUK MENYIMPAN DATA BARU (INSERT)
                    $current_total_value = (float) $data->last_price * (float) $data->qty_oh;
                    $new_value = $transaction_qty * $transaction_price;
                    $denominator = (float) $data->qty_oh + $transaction_qty;
                    
                    if ($denominator != 0) {
                        $new_avg_price = ($current_total_value + $new_value) / $denominator;
                    } else {
                        // Jika stok awal 0, avg_price baru adalah harga transaksi itu sendiri
                        $new_avg_price = $transaction_price;
                    }
                }
            } else {
                $new_avg_price = 0; // Jika stok 0 atau minus, harga rata-rata 0
            }

            // Update last_price hanya jika ada barang masuk (Receive) dan bukan operasi hapus
            if ($tr_type == 'R' && !$is_reversal) {
                $new_last_price = $transaction_price;
            }
            // ========== LOGIKA BARU UNTUK HARGA SELESAI ==========

            // Sesuaikan blok update Anda
            $update_data = [
                'qty_oh' => (float) $hitung_qty_oh,
                'wgt_oh' => (double) $hitung_wgt_oh,
                'avg_price' => $new_avg_price,   // TAMBAHKAN
                'last_price' => $new_last_price, // TAMBAHKAN
                'dt_modified' => now('Asia/Jakarta'),
                'user_id' => $arrayParams->user_id
            ];

            if ($tr_type == 'R') {
                $update_data['qty_rcv'] = $QtyTrans;
                $update_data['wgt_rcv'] = $WgtTrans;
            } elseif ($tr_type == 'I') {
                $update_data['qty_iss'] = $QtyTrans;
                $update_data['wgt_iss'] = $WgtTrans;
            } elseif ($tr_type == 'A') {
                $update_data['qty_ia'] = $QtyTrans;
                $update_data['wgt_ia'] = $WgtTrans;
            }

            DB::connection("sqlSrvFPI")
                ->table('inv_bal')
                ->where('office_id', $arrayParams->office_id)
                ->where('article_id',$arrayParams->article_id)
                ->where('defect',$arrayParams->defect ?? 0)
                ->where('batch_id', $arrayParams->batch_id ?? '')
                ->where('length', $arrayParams->length)
                ->where('add_id', $arrayParams->add_id ?? '')
                ->where('tor_id', $arrayParams->tor_id ?? '')
                ->where('wh_id', $arrayParams->wh_id ?? '')
                ->where('bin_id',$arrayParams->bin_id ?? '')
                ->where('spk_id',$arrayParams->spk_id ?? '')
                ->where('spk_item',$arrayParams->spk_item ?? 0)            
                ->update($update_data);
        //     if ($tr_type == 'R') {
        //         $hitung_qty_oh = (float) $data->qty_awal + (float) $QtyTrans - (float) $data->qty_iss + (float) $data->qty_ia - (float) $data->qty_waste;
        //         $hitung_wgt_oh = (double) $data->wgt_awal + (double) $WgtTrans - (double) $data->wgt_iss + (double) $data->wgt_ia - (double) $data->wgt_waste;

        //         $update = DB::connection("sqlSrvFPI")
        //                 ->table('inv_bal')
        //                 ->where('office_id', $arrayParams->office_id)
        //                 ->where('article_id',$arrayParams->article_id)
        //                 ->where('defect',$arrayParams->defect ?? 0)
        //                 ->where('batch_id', $arrayParams->batch_id ?? '')
        //                 ->where('length', $arrayParams->length)
        //                 ->where('add_id', $arrayParams->add_id ?? '')
        //                 ->where('tor_id', $arrayParams->tor_id ?? '')
        //                 ->where('wh_id', $arrayParams->wh_id ?? '')
        //                 ->where('bin_id',$arrayParams->bin_id ?? '')
        //                 ->where('spk_id',$arrayParams->spk_id ?? '')
        //                 ->where('spk_item',$arrayParams->spk_item ?? 0)
        //                 ->update([
        //                     'qty_rcv' => $QtyTrans,
        //                     'wgt_rcv' => $WgtTrans,
        //                     'qty_oh' => (float) $hitung_qty_oh,
        //                     'wgt_oh' => (double) $hitung_wgt_oh,
        //                     'dt_modified' => now('Asia/Jakarta'),
        //                     'user_id' => $arrayParams->user_id
        //                 ]);

        //     } elseif ($tr_type == 'I') {
        //         $hitung_qty_oh = (float) $data->qty_awal + (float) $data->qty_rcv - (float) $QtyTrans + (float) $data->qty_ia - (float) $data->qty_waste;
        //         $hitung_wgt_oh = (double) $data->wgt_awal + (double) $data->wgt_rcv - (double) $WgtTrans + (double) $data->wgt_ia - (double) $data->wgt_waste;

        //         $update = DB::connection("sqlSrvFPI")
        //                 ->table('inv_bal')
        //                 ->where('office_id', $arrayParams->office_id)
        //                 ->where('article_id',$arrayParams->article_id)
        //                 ->where('defect',$arrayParams->defect ?? 0)
        //                 ->where('batch_id', $arrayParams->batch_id ?? '')
        //                 ->where('length', $arrayParams->length)
        //                 ->where('add_id', $arrayParams->add_id ?? '')
        //                 ->where('tor_id', $arrayParams->tor_id ?? '')
        //                 ->where('wh_id', $arrayParams->wh_id ?? '')
        //                 ->where('bin_id',$arrayParams->bin_id ?? '')
        //                 ->where('spk_id',$arrayParams->spk_id ?? '')
        //                 ->where('spk_item',$arrayParams->spk_item ?? 0)
        //                 ->update([
        //                     'qty_iss' => $QtyTrans,
        //                     'wgt_iss' => $WgtTrans,
        //                     'qty_oh' => (float) $hitung_qty_oh,
        //                     'wgt_oh' => (double) $hitung_wgt_oh,
        //                     'dt_modified' => now('Asia/Jakarta'),
        //                     'user_id' => $arrayParams->user_id
        //                 ]);

        //     } elseif ($tr_type == 'A') {
        //         $hitung_qty_oh = (float) $data->qty_awal + (float) $data->qty_rcv - (float) $data->qty_iss + (float) $QtyTrans - (float) $data->qty_waste;
        //         $hitung_wgt_oh = (double) $data->wgt_awal + (double) $data->wgt_rcv - (double) $data->wgt_iss + (double) $WgtTrans - (double) $data->wgt_waste;


        //         $update = DB::connection("sqlSrvFPI")
        //                 ->table('inv_bal')
        //                 ->where('office_id', $arrayParams->office_id)
        //                 ->where('article_id',$arrayParams->article_id)
        //                 ->where('defect',$arrayParams->defect ?? 0)
        //                 ->where('batch_id', $arrayParams->batch_id ?? '')
        //                 ->where('length', $arrayParams->length)
        //                 ->where('add_id', $arrayParams->add_id ?? '')
        //                 ->where('tor_id', $arrayParams->tor_id ?? '')
        //                 ->where('wh_id', $arrayParams->wh_id ?? '')
        //                 ->where('bin_id',$arrayParams->bin_id ?? '')
        //                 ->where('spk_id',$arrayParams->spk_id ?? '')
        //                 ->where('spk_item',$arrayParams->spk_item ?? 0)
        //                 ->update([
        //                     'qty_ia' => $QtyTrans,
        //                     'wgt_ia' => $WgtTrans,
        //                     'qty_oh' => (float) $hitung_qty_oh,
        //                     'wgt_oh' => (double) $hitung_wgt_oh,
        //                     'dt_modified' => now('Asia/Jakarta'),
        //                     'user_id' => $arrayParams->user_id
        //                 ]);

        //     } else {
        //         $hitung_qty_oh = 0;
        //         $hitung_wgt_oh = 0;
        //     }
        } else {
            if ($tr_type == 'R') {
                $insert = DB::connection("sqlSrvFPI")
                        ->table('inv_bal')
                        ->insert([
                            'office_id' => $arrayParams->office_id,
                            'article_id' => $arrayParams->article_id,
                            'defect' => $arrayParams->defect ?? 0,
                            'batch_id' => $arrayParams->batch_id ?? '',
                            'length' => $arrayParams->length,
                            'add_id' => $arrayParams->add_id ?? '',
                            'tor_id' => $arrayParams->tor_id ?? '',
                            'wh_id' => $arrayParams->wh_id ?? '',
                            'bin_id' => $arrayParams->bin_id ?? '',
                            'spk_id' => $arrayParams->spk_id ?? '',
                            'spk_item' => $arrayParams->spk_item ?? 0,
                            'qty_awal' => 0,
                            'qty_oh' => 0 + 0 + $QtyTrans - 0 + 0 - 0 - 0,
                            'qty_rcv' => $QtyTrans,
                            'qty_iss' => 0,
                            'qty_ia' => 0,
                            'qty_waste' => 0,
                            'wgt_awal' => 0,
                            'wgt_oh' => 0 + 0 + $WgtTrans - 0 + 0 - 0 - 0,
                            'wgt_rcv' => $WgtTrans,
                            'wgt_iss' => 0,
                            'wgt_ia' => 0,
                            'wgt_waste' => 0,
                            'avg_price' => $arrayParams->unit_price ? (float)$arrayParams->unit_price : 0,
                            'last_price' => $arrayParams->unit_price ? (float)$arrayParams->unit_price : 0,
                            'amt_balance' => 0,
                            'dt_modified' => now('Asia/Jakarta'),
                            'user_id' => $arrayParams->user_id
                        ]);

            } elseif ($tr_type == 'A') {
                $insert = DB::connection("sqlSrvFPI")
                        ->table('inv_bal')
                        ->insert([
                            'office_id' => $arrayParams->office_id,
                            'article_id' => $arrayParams->article_id,
                            'defect' => $arrayParams->defect ?? 0,
                            'batch_id' => $arrayParams->batch_id ?? '',
                            'length' => $arrayParams->length,
                            'add_id' => $arrayParams->add_id ?? '',
                            'tor_id' => $arrayParams->tor_id ?? '',
                            'wh_id' => $arrayParams->wh_id ?? '',
                            'bin_id' => $arrayParams->bin_id ?? '',
                            'spk_id' => $arrayParams->spk_id ?? '',
                            'spk_item' => $arrayParams->spk_item ?? 0,
                            'qty_awal' => 0,
                            'qty_oh' => 0 + 0 + 0 - 0 + $QtyTrans - 0 - 0,
                            'qty_rcv' => 0,
                            'qty_iss' => 0,
                            'qty_ia' => $QtyTrans,
                            'qty_waste' => 0,
                            'wgt_awal' => 0,
                            'wgt_oh' => 0 + 0 + 0 - 0 + $WgtTrans - 0 - 0,
                            'wgt_rcv' => 0,
                            'wgt_iss' => 0,
                            'wgt_ia' => $WgtTrans,
                            'wgt_waste' => 0,
                            'avg_price' => $arrayParams->unit_price ? (float)$arrayParams->unit_price : 0,
                            'last_price' => $arrayParams->unit_price ? (float)$arrayParams->unit_price : 0,
                            'amt_balance' => 0,
                            'dt_modified' => now('Asia/Jakarta'),
                            'user_id' => $arrayParams->user_id
                        ]);

            } elseif ($tr_type == 'I') {
                $insert = DB::connection("sqlSrvFPI")
                        ->table('inv_bal')
                        ->insert([
                            'office_id' => $arrayParams->office_id,
                            'article_id' => $arrayParams->article_id,
                            'defect' => $arrayParams->defect ?? 0,
                            'batch_id' => $arrayParams->batch_id ?? '',
                            'length' => $arrayParams->length,
                            'add_id' => $arrayParams->add_id ?? '',
                            'tor_id' => $arrayParams->tor_id ?? '',
                            'wh_id' => $arrayParams->wh_id ?? '',
                            'bin_id' => $arrayParams->bin_id ?? '',
                            'spk_id' => $arrayParams->spk_id ?? '',
                            'spk_item' => $arrayParams->spk_item ?? 0,
                            'qty_awal' => 0,
                            'qty_oh' => 0 + 0 + 0 - $QtyTrans + 0 - 0 - 0,
                            'qty_rcv' => 0,
                            'qty_iss' => $QtyTrans,
                            'qty_ia' => 0,
                            'qty_waste' => 0,
                            'wgt_awal' => 0,
                            'wgt_oh' => 0 + 0 + 0 - $WgtTrans + 0 - 0 - 0,
                            'wgt_rcv' => 0,
                            'wgt_iss' => $WgtTrans,
                            'wgt_ia' => 0,
                            'wgt_waste' => 0,
                            'avg_price' => $arrayParams->unit_price ? (float)$arrayParams->unit_price : 0,
                            'last_price' => $arrayParams->unit_price ? (float)$arrayParams->unit_price : 0,
                            'amt_balance' => 0,
                            'dt_modified' => now('Asia/Jakarta'),
                            'user_id' => $arrayParams->user_id
                        ]);

            }
        }

        return $data;
    }

    private function caseTrType($tr_type)
    {
        if ($tr_type == 'IS' || $tr_type == 'IW' || $tr_type == 'MI' || $tr_type == 'TI' || $tr_type == 'PR' || $tr_type == 'SA') {
            $tr_type = 'I';
            // Issue
        } elseif ($tr_type == 'MR' || $tr_type == 'RC' || $tr_type == 'RW' || $tr_type == 'SR' || $tr_type == 'TR' || $tr_type == 'RP') {
            $tr_type = 'R';
            //Receive
        } elseif ($tr_type == 'IA') {
            $tr_type = 'A';
            //Adjust
        } else {
            $tr_type = '';
        }        

        return $tr_type;
    }

    /*--------------------------dari create purc inv----------------------------- */

    private function getInt1ApplConst($sKey, $sStr1)
    {
        return (float) DB::connection('sqlSrvFPI') // sesuaikan nama koneksi
            ->table('appl_constant')
            ->where('key_code', trim($sKey))
            ->where('str1', trim($sStr1))
            ->value('int1') ?? 0; // default 0 kalau null
    }

    // public function createPurcInv($office_id, $purc_rcv_id)
    // {
    //     try {
    //         // -- select rcv hdr, rcv dtl
    //         $rcvDtl = DB::connection('sqlSrvFPI')
    //             ->table('purc_rcv_dtl as prd')
    //             ->leftJoin('article as art', 'prd.article_id', '=', 'art.article_id')
    //             ->where('purc_rcv_id', $purc_rcv_id)
    //             ->where('office_id', $office_id)
    //             ->selectRaw("
    //                 prd.*,
    //                 art.consumable,
    //                 art.ac_num_persd
    //             ")
    //             ->get()
    //             ->toArray();

    //         if (!$rcvDtl) {
    //             throw new \Exception('Save auto invoice gagal, receive dtl kosong !');
    //         }

    //         // -- get po_hdr
    //         $poId = $rcvDtl[0]->po_id;
    //         $dataPo = DB::connection('sqlSrvFPI')
    //             ->table('po_hdr as ph')
    //             ->where('po_id', $poId)
    //             ->first();

    //         if (!$dataPo) {
    //             throw new \Exception("Data PO {$poId} tidak ada");
    //         }            
    //         // 1. Inisialisasi Pajak & Data Transaksi
    //         // Ambil persentase PPh23 dan PPN dari konstanta/properti
    //         $xPph23 = (float) $this->getInt1ApplConst('PPH23', 'PPH23'); // Persentase PPh23
    //         $ppn = (float) $dataPo->ppn ?? 0; // Persentase PPN (misal 11)

    //         $invSeq = $this->TrSeq;
    //         $trId   = $this->TrIDInv;
            
    //         // **INISIALISASI PROPERTI PAJAK WAJIB**
    //         $this->amt_ppn = 0; 
    //         $this->pph23 = 0; 

    //         // Tentukan skema: Include Tax jika Tax1 = 'E'
    //         $isIncludeTax = (trim($this->Tax1) === 'E'); 

    //         // 2. Kontrol Persentase Pajak Berdasarkan Flag 'T' atau 'E'
    //         // Jika flag BUKAN 'E' (Include) dan BUKAN 'T' (Exclude), maka persentase pajak dianggap 0
            
    //         // Kontrol PPN (Tax1)
    //         if (trim($this->Tax1) !== 'T' && trim($this->Tax1) !== 'E') {
    //             $ppn = 0; // PPN tidak berlaku
    //         }
            
    //         // Kontrol PPh23 (Tax2)
    //         if (trim($this->Tax2) !== 'T' && trim($this->Tax2) !== 'E') {
    //             $xPph23 = 0; // PPh23 tidak berlaku
    //         }
            
    //         $subTotalAmount = array_reduce($rcvDtl, function ($add, $item) {
    //             return $add + (float) $item->amt_item;
    //         }, 0);

    //         // Perhitungan pajak    
    //         if (filled(trim($dataPo->tax3)) && trim($dataPo->tax3) != 'N') {
    //             $calcHargaInEx = $this->servicePerhitungan->hitungHargaDasarIncludeExclude(
    //                 ($subTotalAmount - $dataPo->amt_disc),
    //                 ($ppn / 100),
    //                 ($xPph23 / 100),
    //                 0,
    //                 [trim($dataPo->tax3), trim($dataPo->tax3), '']
    //             );
    //         } else {
    //             $calcHargaInEx = $this->servicePerhitungan->hitungHargaDasarIncludeExclude(
    //                 ($subTotalAmount - $dataPo->amt_disc),
    //                 ($ppn / 100),
    //                 ($xPph23 / 100),
    //                 0,
    //                 [trim($dataPo->tax1), trim($dataPo->tax2), '']
    //             );
    //         }

    //         // === 5. Insert/Update purc_inv_mast ===
    //         $invMast = DB::connection('sqlSrvFPI')
    //             ->table('purc_inv_mast')
    //             ->where('office_id', $this->MillID)
    //             ->where('tr_id', $trId)
    //             ->first();
                
    //         $dataMast = [
    //             'inv_id'       => trim($this->InvID),
    //             'vendor_id'    => trim($this->VendorID),
    //             'dt_inv'       => $this->DtPR,
    //             'dt_ship'      => $this->DtPR,
    //             'pay_term_id'  => $dataPo->pay_term_id ?? null,
    //             'dt_due'       => Carbon::parse($this->DtPR)->addDays($this->NumOfDays),
    //             'Tax1'         => $dataPo->tax1,
    //             'Tax2'         => $dataPo->tax2,
    //             'Tax3'         => $dataPo->tax3, 
    //             'curr_id'      => $dataPo->curr_id ?? 'IDR',
    //             'Rate'         => $dataPo->curr_rate ?? 1,
    //             'lpb_rate'     => $dataPo->curr_rate ?? 1,
    //             'pct_disc'     => ($dataPo->pct_disc ?? 0) / 100,
    //             'amt_disc'     => $dataPo->amt_disc ?? 0,
    //             'ppn'          => $ppn,
    //             'amt_freight'  => (float) ($dataPo->amt_freight ?? 0),
    //             'dt_modified'  => now('Asia/Jakarta'),
    //             'user_id'      => $this->gsUserID,
    //             'inv_num'      => (int)$this->InvoiceNum,
    //             'tr_num'       => (int)$this->trNum,
    //             'ac_num_bbt'   => trim($this->AcNumBBT),
    //             'ac_num_hut'   => trim($this->getStdAccountCode(1, 'HUTANG PEMBELIAN', trim($this->CurrID), 'N')),
    //             'ac_num_pph'   => trim($this->AcNumPPH),                
    //             'amt_ppn'      => $calcHargaInEx[1], 
    //             'amt_pph'      => $calcHargaInEx[2], 
    //             'amt_subtotal' => $calcHargaInEx[0],
    //             'amt_total'    => $calcHargaInEx[4],
    //         ];
            
    //         // --- PENETAPAN SUBTOTAL BERDASARKAN SKEMA ---
            
    //         // if ($isIncludeTax) {
    //         //     // Include Tax: Subtotal di dataMast adalah HARGA DASAR (DPP sebelum diskon)
    //         //     $dataMast['amt_subtotal'] = $vhargaDasar + (float)$this->AmtDisc; 
    //         // } else {
    //         //     // Exclude Tax: Subtotal di dataMast adalah Harga Bruto sebelum diskon
    //         //     $dataMast['amt_subtotal'] = $subTotalAmount; 
    //         // }

    //         // PERHITUNGAN AKHIR TOTAL
    //         // Total = (Subtotal di Mast - Diskon) + PPN + Freight - PPh
    //         // $dataMast['amt_total'] = ($dataMast['amt_subtotal'] - $dataMast['amt_disc']) 
    //         //                         + ($dataMast['amt_ppn']) 
    //         //                         + $dataMast['amt_freight'] 
    //         //                         - ($dataMast['amt_pph']);

    //         $dataMast['inv_said']   = terbilang($dataMast['amt_total']) . ' ' . (trim($this->CurrID) !== 'IDR' ? trim($this->CurrID) : '');
    //         $dataMast['ac_num_ppn'] = trim($this->getStdAccountCode(1, 'PPN MASUKAN', 'IDR', 'N'));


    //         if (!$invMast) {
    //             $dataMast['office_id'] = $this->MillID;
    //             $dataMast['tr_id']     = trim($trId);
    //             $dataMast['tr_num']    = $this->InvoiceNum;
    //             $dataMast['dt_created']= $this->DtPR;
    //             DB::connection('sqlSrvFPI')->table('purc_inv_mast')->insert($dataMast);
    //         } else {
    //             DB::connection('sqlSrvFPI')->table('purc_inv_mast')
    //                 ->where('office_id', $this->MillID)
    //                 ->where('tr_id', $trId)
    //                 ->update($dataMast);
    //         }
    //         // -- insert purc_inv_dtl
    //         $bulkRcvDtl = [];
    //         // === 3. Insert/Update purc_inv_item ===
            
    //         foreach ($rcvDtl as $item) {
    //             $invItem = DB::connection('sqlSrvFPI')
    //             ->table('purc_inv_item')
    //             ->where('office_id', $item->office_id)
    //             ->where('tr_id', $trId)
    //             ->where('po_id', $item->po_id)
    //             ->where('po_item', $item->po_item)
    //             ->where('article_id', $item->article_id)
    //             ->first();

    //             $dataItem = [
    //                 'office_id'    => $item->office_id,
    //                 'tr_id'        => trim($trId),
    //                 'seq_num'      => (int)$invSeq,
    //                 'inv_id'       => trim($this->InvID),
    //                 'inv_item'     => (int)$invSeq,
    //                 'consume_able' => $item->consumable ? 'Y' : 'N',
    //                 'sj_no'        => trim($this->SjNo),
    //                 'po_id'        => $item->po_id,
    //                 'po_item'      => $item->po_item,
    //                 'pr_id'        => $item->pr_id,
    //                 'pr_item'      => $item->pr_item,
    //                 'article_id'   => $item->article_id,
    //                 'qty'          => $item->qty,
    //                 'wgt'          => $item->wgt,
    //                 'unit_meas'    => $item->unit_meas,
    //                 'wgt_unit'     => $item->wgt,
    //                 'um_price'     => $this->optUmPrice[0] == true ? 0 : 1,
    //                 'unit_price'   => $item->unit_price,
    //                 'fix_price'    => $this->FixPrice,
    //                 'amt_item'     => (float) $item->qty * (float) $item->unit_price,
    //                 'inv_rate'     => $dataPo->curr_rate,
    //                 'lpb_rate'     => $dataPo->curr_rate,
    //                 'dt_modified'  => now('Asia/Jakarta'),
    //                 'user_id'      => $this->gsUserID,
    //             ];
    //         }
    //         if (!$invItem) {
    //             $dataItem['dt_created'] = $this->DtPR;
    //             DB::connection('sqlSrvFPI')->table('purc_inv_item')->insert($dataItem);
    //         } else {
    //             DB::connection('sqlSrvFPI')->table('purc_inv_item')
    //                 ->where('office_id', $item->office_id)
    //                 ->where('tr_id', $trId)
    //                 ->where('seq_num', (int)$invSeq)
    //                 ->update($dataItem);
    //         }

    //         // // === 4. Hitung subtotal & Aplikasikan Skema Pajak ===
    //         // $subTotalAmount = $this->getSubTotal($this->TrIDInv); // Subtotal Bruto
            
    //         // // Inisialisasi DPP (Harga Dasar) untuk dataMast: Bruto - Diskon
    //         // $vhargaDasar = $subTotalAmount - (float)$this->AmtDisc; 

    //         // if ($isIncludeTax && $ppn > 0) {
    //         //     // Logika INCLUDE TAX (Jika Tax1 = 'E')
    //         //     // Harga Bruto - Diskon sudah termasuk PPN. Dipecah menjadi DPP dan PPN.
                
    //         //     // Memecah harga bruto menjadi DPP dan PPN
    //         //     [$vhargaDasar, $vppn, $vpph23, $vpph21, $vtotal] =
    //         //         $this->hitungHargaDasarTanpaSelisih_All(
    //         //             ($subTotalAmount - (float)$this->AmtDisc), // Bruto - Diskon
    //         //             ($ppn / 100),
    //         //             ($xPph23 / 100),
    //         //             0
    //         //         );
                
    //         //     // Simpan nilai hasil pemecahan
    //         //     $this->amt_ppn = $vppn;     
    //         //     $this->pph23  = $vpph23;   
                
    //         //     // $vhargaDasar sekarang berisi DPP setelah diskon

    //         // } else {
    //         //     // Logika EXCLUDE TAX (Jika Tax1 != 'E' dan Tax2 != 'E')
    //         //     // Harga Bruto - Diskon adalah DPP. PPN/PPh dihitung di atas DPP.
                
    //         //     // DPP = Bruto - Diskon sudah diinisialisasi di atas: $vhargaDasar = $subTotalAmount - (float)$this->AmtDisc;
                
    //         //     if ($ppn > 0) { // Cek PPN setelah kontrol T/E
    //         //         // PPN dihitung dari DPP
    //         //         $this->amt_ppn = round($vhargaDasar * $ppn / 100, 0); 
    //         //     } 
    //         //     if ($xPph23 > 0) { // Cek PPh23 setelah kontrol T/E
    //         //         // PPh dihitung dari DPP
    //         //         $this->pph23 = round($vhargaDasar * $xPph23 / 100, 0); 
    //         //     }
    //         // }
            
    //         // // Perhitungan Total Invoice Akhir
    //         // // Total = (DPP) + PPN + Freight - PPh23
    //         // $totalAmtPO = $vhargaDasar + $this->amt_ppn + (float)$this->AmtFreight - $this->pph23;
    //         // $poSaid = terbilang($totalAmtPO); 

            

    //         // === 6. Update purc_rcv_dtl & purc_rcv_hdr ===
    //         DB::connection('sqlSrvFPI')
    //             ->table('purc_rcv_dtl')
    //             ->where('office_id', (int)$this->MillID)
    //             ->where('purc_rcv_id', $this->RcvID)
    //             ->where('seq_num', $this->TrSeq)
    //             ->update([
    //                 'inv_id'          => trim($this->InvID),
    //                 'inv_item'        => $invSeq,
    //                 'tr_id_invoice'   => trim($trId),
    //                 'seq_num_invoice' => $invSeq
    //             ]);

    //         DB::connection('sqlSrvFPI')
    //             ->table('purc_rcv_hdr')
    //             ->where('office_id', (int)$this->MillID)
    //             ->where('purc_rcv_id', $this->RcvID)
    //             ->update([
    //                 'inv_id' => trim($this->InvID)
    //             ]);

    //         // === 7. Jurnal ===
    //         $tanggal = Carbon::parse($this->DtPR);
    //         $tahun4digit = $tanggal->format('Y');
    //         $autoInv = $this->getInt1ApplConst('AUTOINV', 'AUTOINV');
    //         if($autoInv == 1){
    //             $this->serviceAcc->JrnlPurcInv(1, $trId, $tahun4digit, $this->gsUserID);
    //         }
            
    //         return ['status' => true, 'message' => 'Invoice berhasil dibuat.'];
    //     }
    //     catch (\Throwable $e) {
    //         return ['status' => false, 'message' => "Kesalahan pada `createPurcInv`: " . $e->getMessage()];
    //     }
    // }

    public function createPurcInv($office_id, $purc_rcv_id)
    {
        try {
            // 1. Ambil Data Receive Detail & Header PO
            // $rcvDtl = DB::connection('sqlSrvFPI')
            //     ->table('purc_rcv_dtl as prd')
            //     ->leftJoin('article as art', 'prd.article_id', '=', 'art.article_id')
            //     ->where('purc_rcv_id', $purc_rcv_id)
            //     ->where('office_id', $office_id)
            //     ->selectRaw("prd.*, art.consumable, art.ac_num_persd")
            //     ->get()
            //     ->toArray();
            $tr_date = $this->DtPR; 
        
            if ($this->serviceAcc->ifAcctClosed($tr_date, request())) {
                return [
                    'status'  => false, 
                    'message' => 'This period is already closed, cannot be modified.'
                ];
            }
            $this->TrSeq = 1;
            $rcvDtl = DB::connection('sqlSrvFPI')
                ->table('purc_rcv_dtl as prd')
                ->leftJoin('article as art', function($join) {
                    $join->on('prd.article_id', '=', 'art.article_id');
                    // Jika tabel article punya filter tambahan (misal status aktif), tambahkan di sini
                })
                ->where('prd.purc_rcv_id', $purc_rcv_id)
                ->where('prd.office_id', $office_id)
                ->selectRaw("prd.*, art.consumable, art.ac_num_persd")
                ->get()
                ->unique(function ($item) {
                    // Proteksi tambahan: unikkan berdasarkan po_item agar tidak double
                    return $item->po_id . $item->po_item . $item->article_id;
                })
                ->values()
                ->toArray();
                
            if (empty($rcvDtl)) {
                throw new \Exception('Save auto invoice gagal, receive dtl kosong !');
            }
            $trId = !blank($this->TrIDInv) ? $this->TrIDInv : str_replace('PRC', 'PI', $purc_rcv_id);
            // dd($trId);
            $this->TrSeq = 1;
            $invSeq = (int)($this->TrSeq ?? 1); 
            // dd($trId, $invSeq);
            $poId = $rcvDtl[0]->po_id;
            $dataPo = DB::connection('sqlSrvFPI')
                ->table('po_hdr as ph')
                ->where('po_id', $poId)
                ->first();

            if (!$dataPo) {
                throw new \Exception("Data PO {$poId} tidak ada");
            }

            // 2. Inisialisasi Pajak & Parameter
            $xPph23 = (float) $this->getInt1ApplConst('PPH23', 'PPH23'); 
            $ppnPercent = (float) ($dataPo->ppn ?? 0); 

            // Kontrol Flag Pajak (T = Exclude, E = Include, N/Lainnya = 0%)
            $ppnRate = (trim($dataPo->tax1) === 'T' || trim($dataPo->tax1) === 'E') ? ($ppnPercent / 100) : 0;
            $pphRate = (trim($dataPo->tax2) === 'T' || trim($dataPo->tax2) === 'E') ? ($xPph23 / 100) : 0;
            // dd($this);
            // $trId = $this->TrIDInv;
            // $invSeq = (int)$this->TrSeq;
            
            $amt_freight = (float)($dataPo->amt_freight ?? 0);
            $amt_disc_total = (float)($dataPo->amt_disc ?? 0);
            // dd($trId);
            // Hitung Total Bruto dari semua item untuk alokasi diskon
            $totalBrutoRcv = array_reduce($rcvDtl, function ($carry, $item) {
                return $carry + ((float)$item->qty * (float)$item->unit_price);
            }, 0);

            // 3. Hitung Kalkulasi Header Menggunakan Service
            // Ini untuk mendapatkan angka DPP, PPN, dan PPh secara total
            $calcHargaInEx = $this->servicePerhitungan->hitungHargaDasarIncludeExclude(
                ($totalBrutoRcv - $amt_disc_total),
                $ppnRate,
                $pphRate,
                0,
                [
                    trim($dataPo->tax1),
                    trim($dataPo->tax2),
                    'N', // pph21Type
                    'N'  // pph4Type
                ],
                0
            );

            DB::connection('sqlSrvFPI')->table('purc_inv_item')
                ->where('office_id', $office_id)
                ->where('tr_id', trim($trId))
                ->delete();
                
            // 4. LOOPING DETAIL: Simpan ke purc_inv_item
            foreach ($rcvDtl as $key => $item) {
                
                $currentSeq = $invSeq + $key;
                $brutoItem = (float)$item->qty * (float)$item->unit_price;
                
                // Alokasi Diskon Proporsional (Penting agar matching dengan Header)
                $porsiDiskon = ($totalBrutoRcv > 0) ? ($brutoItem / $totalBrutoRcv) * $amt_disc_total : 0;
                $nettoItem = $brutoItem - $porsiDiskon;

                // Jika skema INCLUDE (E), maka amt_item harus di-DPP-kan (dikeluarkan pajaknya)
                // Agar Sum(amt_item) detail == amt_subtotal header
                //fix bug waktu invoice 
                // $amtItemFinal = $nettoItem;
                // if (trim($dataPo->tax1) === 'E' && $ppnRate > 0) {
                //     $amtItemFinal = $nettoItem / (1 + $ppnRate);
                // }

                $dataItem = [
                    'office_id'    => $item->office_id,
                    'tr_id'        => trim($trId),
                    'seq_num'      => $currentSeq,
                    'inv_id'       => trim($this->InvID),
                    'inv_item'     => $currentSeq,
                    'consume_able' => $item->consumable ? 'Y' : 'N',
                    'sj_no'        => trim($this->SjNo),
                    'po_id'        => $item->po_id,
                    'po_item'      => $item->po_item,
                    'pr_id'        => $item->pr_id,
                    'pr_item'      => $item->pr_item,
                    'article_id'   => $item->article_id,
                    'qty'          => $item->qty,
                    'wgt'          => $item->wgt,
                    'unit_meas'    => $item->unit_meas,
                    'wgt_unit'     => $item->wgt,
                    'um_price'     => ($this->optUmPrice[0] ?? false) == true ? 0 : 1,
                    'unit_price'   => $item->unit_price,
                    'fix_price'    => $this->FixPrice,
                    'amt_item'     => round($nettoItem, 2),//round($amtItemFinal, 2), // Nilai DPP per baris
                    'inv_rate'     => $dataPo->curr_rate ?? 1,
                    'lpb_rate'     => $dataPo->curr_rate ?? 1,
                    'dt_modified'  => now('Asia/Jakarta'),
                    'user_id'      => $this->gsUserID,
                ];

                // Update atau Insert per baris (DI DALAM LOOP)
                DB::connection('sqlSrvFPI')->table('purc_inv_item')->updateOrInsert(
                    [
                        'office_id' => $item->office_id,
                        'tr_id'     => trim($trId),
                        'seq_num'   => $currentSeq
                    ],
                    $dataItem
                );
            }

            // 5. SIMPAN HEADER: purc_inv_mast
            $dataMast = [
                'inv_id'       => trim($this->InvID),
                'vendor_id'    => trim($this->VendorID),
                'dt_inv'       => $this->DtPR,
                'dt_ship'      => $this->DtPR,
                'pay_term_id'  => $dataPo->pay_term_id ?? null,
                'dt_due'       => Carbon::parse($this->DtPR)->addDays($this->NumOfDays),
                'Tax1'         => $dataPo->tax1,
                'Tax2'         => $dataPo->tax2,
                'Tax3'         => $dataPo->tax3, 
                'curr_id'      => $dataPo->curr_id ?? 'IDR',
                'Rate'         => $dataPo->curr_rate ?? 1,
                'lpb_rate'     => $dataPo->curr_rate ?? 1,
                'pct_disc'     => ($dataPo->pct_disc ?? 0) / 100,
                'amt_disc'     => $amt_disc_total,
                'ppn'          => $ppnPercent,
                'amt_freight'  => $amt_freight,
                'dt_modified'  => now('Asia/Jakarta'),
                'user_id'      => $this->gsUserID,
                'inv_num'      => (int)$this->InvoiceNum,
                'tr_num'       => (int)$this->trNum,
                'ac_num_bbt'   => trim($this->AcNumBBT),
                'ac_num_hut'   => trim($this->getStdAccountCode(1, 'HUTANG PEMBELIAN', trim($this->CurrID), 'N')),
                'ac_num_pph'   => trim($this->AcNumPPH),
                'ac_num_ppn'   => trim($this->getStdAccountCode(1, 'PPN MASUKAN', 'IDR', 'N')),
                'amt_ppn'      => round($calcHargaInEx[1], 2), 
                'amt_pph'      => round($calcHargaInEx[2], 2), 
                'amt_subtotal' => round($calcHargaInEx[0], 2), // DPP
                'amt_total'    => round($calcHargaInEx[5] + $amt_freight, 2),
            ];
            $dataMast['inv_said'] = terbilang($dataMast['amt_total']) . ' ' . (trim($this->CurrID) !== 'IDR' ? trim($this->CurrID) : '');

            DB::connection('sqlSrvFPI')->table('purc_inv_mast')->updateOrInsert(
                ['office_id' => $this->MillID, 'tr_id' => trim($trId)],
                array_merge($dataMast, [
                    'dt_created' => $this->DtPR, // Hanya untuk insert jika pakai updateOrInsert manual
                ])
            );

            // 6. Update Referensi di Tabel Receive
            DB::connection('sqlSrvFPI')->table('purc_rcv_dtl')
                ->where('office_id', (int)$this->MillID)
                ->where('purc_rcv_id', $this->RcvID)
                ->update([
                    'inv_id' => trim($this->InvID),
                    'tr_id_invoice' => trim($trId),
                ]);

            DB::connection('sqlSrvFPI')->table('purc_rcv_hdr')
                ->where('office_id', (int)$this->MillID)
                ->where('purc_rcv_id', $this->RcvID)
                ->update(['inv_id' => trim($this->InvID)]);

            // 7. Eksekusi Jurnal
            $autoInv = $this->getInt1ApplConst('AUTOINV', 'AUTOINV');
            if($autoInv == 1){
                $tahun = Carbon::parse($this->DtPR)->format('Y');
                $this->serviceAcc->JrnlPurcInv(1, $trId, $tahun, $this->gsUserID);
            }
            
            return ['status' => true, 'message' => 'Invoice berhasil dibuat.'];

        } catch (\Throwable $e) {
            return ['status' => false, 'message' => "Kesalahan pada `createPurcInv`: " . $e->getMessage()];
        }
    }

    private function hitungHargaDasarTanpaSelisih_All($hargaInclude, $tarifPPN, $tarifPPh23, $tarifPPh21)
    {
        // 1. Hitung harga dasar
        $dasar = $hargaInclude / (1 + $tarifPPN - $tarifPPh23 - $tarifPPh21);
        $hargaDasar = round($dasar, 0);

        // 2. Hitung PPh23 dan PPh21
        $pph23_full = $hargaDasar * $tarifPPh23;
        $pph23 = round($pph23_full, 0);

        $pph21_full = $hargaDasar * $tarifPPh21;
        $pph21 = round($pph21_full, 0);

        // 3. Hitung ppn (agar total pas)
        $ppn = $hargaInclude - $hargaDasar + $pph23 + $pph21;

        // 4. Total akhir
        $total = $hargaDasar + $ppn - $pph23 - $pph21;

        // Return semua nilai sebagai array
        return [
            $hargaDasar, // index 0
            $ppn,        // index 1
            $pph23,      // index 2
            $pph21,      // index 3
            $total       // index 4
        ];
    }

    private function cIndo($bilang, $mataUang)
    {
        if ($bilang < 0.009) {
            return "Nol";
        }

        // Format angka dengan 2 desimal
        $A = number_format($bilang, 2, '.', '');
        $Tail = substr($mataUang, -2);

        if ((int)substr($A, -2) === 0) {
            $Tail = 0;
        } elseif (in_array(substr($Tail, 0, 1), ['.', ','])) {
            $Tail = substr($Tail, -1) . "0";
        }

        // Ambil bagian ribuan (tanpa koma)
        $bil = str_pad(substr($A, 0, -3), 15, " ", STR_PAD_LEFT);

        $konv = "";

        if ((int)substr($bil, 0, 3) !== 0) {
            $konv .= $this->fnKonv($bil, 0) . " Trillion ";
        }
        if ((int)substr($bil, 3, 3) !== 0) {
            $konv .= $this->fnKonv($bil, 3) . " Milyar ";
        }
        if ((int)substr($bil, 6, 3) !== 0) {
            $konv .= $this->fnKonv($bil, 6) . " Juta ";
        }

        if ((int)substr($bil, 9, 3) !== 0) {
            if ((int)substr($bil, 12, 3) === 0) {
                if (substr($this->fnKonv($bil, 9), -2) === "Se") {
                    $ribu = $this->fnKonv($bil, 9) . "ribu";
                } else {
                    $ribu = $this->fnKonv($bil, 9) . " Ribu";
                }
            } else {
                if (substr($this->fnKonv($bil, 9), -2) === "Se") {
                    $ribu = $this->fnKonv($bil, 9) . "ribu ";
                } else {
                    $ribu = $this->fnKonv($bil, 9) . " Ribu ";
                }
            }
            $konv .= $ribu;
        }

        $hasil = $konv . $this->fnKonv($bil, 12);

        if ((int)$Tail === 0) {
            $hasil .= $bilang != 0 ? " " : "";
        } else {
            $hasil .= " " . $Tail . "/100";
        }

        return trim($hasil);
    }

    private function fnKonv($bil, $b)
    {
        $st = [
            "", "Satu", "Dua", "Tiga", "Empat", "Lima",
            "Enam", "Tujuh", "Delapan", "Sembilan"
        ];

        $c1 = (int)substr($bil, $b - 1, 1);
        $c2 = (int)substr($bil, $b, 1);
        $c3 = (int)substr($bil, $b + 1, 1);

        $hasil = "";

        switch ($c2) {
            case 0:
                if ($c3 != 0) {
                    if ($c3 == 1 && $b == 10) {
                        $hasil = "Se";
                    } else {
                        $hasil = $st[$c3] . " ";
                    }
                }
                break;
            case 1:
                switch ($c3) {
                    case 0: $hasil = "Sepuluh "; break;
                    case 1: $hasil = "Sebelas "; break;
                    case 2: $hasil = "Dua Belas "; break;
                    case 3: $hasil = "Tiga Belas "; break;
                    case 4: $hasil = "Empat Belas "; break;
                    case 5: $hasil = "Lima Belas "; break;
                    case 6: $hasil = "Enam Belas "; break;
                    case 7: $hasil = "Tujuh Belas "; break;
                    case 8: $hasil = "Delapan Belas "; break;
                    case 9: $hasil = "Sembilan Belas "; break;
                }
                break;
            case 2: $hasil = "Dua Puluh " . $st[$c3]; break;
            case 3: $hasil = "Tiga Puluh " . $st[$c3]; break;
            case 4: $hasil = "Empat Puluh " . $st[$c3]; break;
            case 5: $hasil = "Lima Puluh " . $st[$c3]; break;
            case 6: $hasil = "Enam Puluh " . $st[$c3]; break;
            case 7: $hasil = "Tujuh Puluh " . $st[$c3]; break;
            case 8: $hasil = "Delapan Puluh " . $st[$c3]; break;
            case 9: $hasil = "Sembilan Puluh " . $st[$c3]; break;
        }

        if ($c1 == 1) {
            $hasil = "Seratus " . $hasil;
        } elseif ($c1 > 1) {
            if ($c2 != 0 || $c3 != 0) {
                $hasil = $st[$c1] . " Ratus " . $hasil;
            } elseif ($c2 == 0 && $c3 == 0) {
                $hasil = $st[$c1] . " Ratus" . $hasil;
            }
        }

        return trim($hasil);
    }

    public function getStdAccountCode($officeId, $desc, $currId = null, $import = null, $flag = null, $divisionId = null)
    {
        // Pencarian pertama: berdasarkan office_id + ac_name
        $query = DB::connection('sqlSrvFPI')
            ->table('g_ledger_std')
            ->select('ac_num')
            ->where('office_id', $officeId)
            ->where('ac_name', trim($desc));

        if (!empty($currId)) {
            $query->where('curr_id', trim($currId));
        }
        if (!empty($import)) {
            $query->where('import', trim($import));
        }
        if (!empty($flag)) {
            $query->where('office_id', trim($flag)); // di VB6: sOffice
        }
        if (!empty($divisionId)) {
            $query->where('divisi', trim($divisionId));
        }

        $result = $query->first();

        // Kalau ketemu langsung return
        if ($result) {
            return trim($result->ac_num);
        }

        // Pencarian fallback: tanpa filter office_id pertama
        $query2 = DB::connection('sqlSrvFPI')
            ->table('g_ledger_std')
            ->select('ac_num')
            ->where('ac_name', trim($desc));

        if (!empty($currId)) {
            $query2->where('curr_id', trim($currId));
        }
        if (!empty($import)) {
            $query2->where('import', trim($import));
        }
        if (!empty($flag)) {
            $query2->where('office_id', trim($flag));
        }
        if (!empty($divisionId)) {
            $query2->where('divisi', trim($divisionId));
        }

        $result2 = $query2->first();

        return $result2 ? trim($result2->ac_num) : null;
    }

    public function getSubTotal($trIdInv)
    {
        $subtotal = DB::connection('sqlSrvFPI')
            ->table('purc_inv_item')
            ->where('office_id', $this->MillID)
            ->where('tr_id', $trIdInv)
            ->sum('amt_item');

        return $subtotal ?? 0;
    }

    public function jurnalulangrcv(Request $request)
    {
        $purc_rcv_id = $request->purc_rcv_id ?? '';        
        $dataPurcRcvHdr = DB::connection('sqlSrvFPI')
                ->table('purc_rcv_hdr as a')
                ->where('a.purc_rcv_id', $purc_rcv_id)
                ->first();
        $user_id = getAuth($request)->user_id2;        
        $tahun = Carbon::parse($dataPurcRcvHdr->dt_rcv)->format('Y');
        $this->serviceAcc->JrnlPurcReceive(1, $purc_rcv_id, $tahun, $user_id);
        return $this->sendResponse('success', 'Successfully revise receive journal');

    }

    public function jurnalulanginv(Request $request)
    {
        // dd('');
        // $purc_inv_id = $request->purc_inv_id ?? '';
        $inv_id = $request->inv_id ?? '';
        $tr_id = $request->tr_id ?? '';
        $user_id = getAuth($request)->user_id2;
        $dataPurcRcvHdr = DB::connection('sqlSrvFPI')
            ->table('purc_rcv_hdr as a')
            ->where('a.inv_id', $inv_id)
            ->first();
        // dd($dataPurcRcvHdr);
        if(!$dataPurcRcvHdr){
            // dd('masuk 1');
            $dataPurcRcvHdr = DB::connection('sqlSrvFPI')
            ->table('purc_inv_mast as a')
            ->where('a.tr_id', $tr_id)
            ->first();
            // dd($dataPurcRcvHdr);
            $tahun = Carbon::parse($dataPurcRcvHdr->dt_inv)->format('Y');
            $this->serviceAcc->JrnlPurcInvNonPO(1, $tr_id, $tahun, $user_id);
        }
        else{
            // dd('masuk 2');
            $tahun = Carbon::parse($dataPurcRcvHdr->dt_rcv)->format('Y');
            $this->serviceAcc->JrnlPurcInv(1, $tr_id, $tahun, $user_id);
        }
        return $this->sendResponse('success', 'Successfully revise invoice journal');
    }
}