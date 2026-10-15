<?php

namespace App\Http\Controllers\finance;

use App\Http\Controllers\Controller;
use App\Traits\getFilterTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CairDanaBankController extends Controller
{
    public function getData(Request $request)
    {
        $province_id = $request->province_id ?? '';
        $city_id = $request->city_id ?? '';
        $site_id = $request->site_id ?? '';
        $cluster_id = $request->cluster_id ?? '';
        $id_house = $request->id_house ?? '';

        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('v_penjualan_payment as vpp')
                ->where('vpp.pay_method', 'KPR') // only KPR
                ->where('vpp.status', 'O') // -- Open
                ->when($site_id, function ($query) use ($site_id) {
                    $query->where('vpp.id_site', $site_id);
                })
                ->when($cluster_id, function ($query) use ($cluster_id) {
                    $query->where('vpp.id_cluster', $cluster_id);
                })
                ->when($id_house, function ($query) use ($id_house) {
                    $query->where('vpp.id_house', $id_house);
                })
                ->selectRaw("
                    vpp.id_site,
                    vpp.site_name,
                    vpp.id_cluster,
                    vpp.cluster_name,
                    vpp.id_house,
                    vpp.house_name,
                    vpp.CustomerId,
                    vpp.NamaCustomer,
                    vpp.SalesId,
                    vpp.NamaSales,
                    vpp.pay_method,
                    CAST(vpp.order_id AS VARCHAR) AS order_id,
                    CAST((SELECT SUM(amt_request) FROM pencairan_spr WHERE doc_id = vpp.order_id AND doc_type = vpp.pay_method) AS DECIMAL(18, 2)) AS amt_request,
                    CAST((SELECT SUM(amt_cair) FROM pencairan_spr WHERE doc_id = vpp.order_id AND doc_type = vpp.pay_method) AS DECIMAL(18, 2)) AS amt_cair
                ")
                ->get()
                ->map(function ($item) {
                    // -- status docs
                    $item->status_docs = $this->docStatus($item->order_id);
                    $item->payment_status = $this->paymentStatus($item->order_id);
                    return $item;
                });

            return $this->sendResponse($result, 'Successfully get Data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getDocData(Request $request)
    {
        $qc_trans_id = $request->qc_trans_id ?? '';
        $cat_id = $request->cat_id ?? '';

        try {
            // -- data pernah save
            $saved = DB::connection("sqlSrvBCKI")
                ->table('pencairan_spr')
                ->where('qc_trans_id', $qc_trans_id)
                ->where('cat_id', $cat_id)
                ->first();

            // -- data bank, progress bangunan image per type
            $progress = DB::connection("sqlSrvBCKI")
                ->table('qc_trans_hdr as qth')
                ->join("v_penjualan_payment as vpp", "qth.id_house", "=", "vpp.id_house")
                ->join('qc_trans_dtl as qtd', 'qtd.qc_trans_id', '=', 'qth.qc_trans_id')
                ->join('qc_item as qi', 'qtd.id_qc_item', '=', 'qi.id_qc_item')
                ->where('qth.doc_type', 'SPR') // only SPR
                ->where('qth.active_flag', 'Y')
                ->where('qth.qc_trans_id', $qc_trans_id)
                ->selectRaw("
                    qi.id_qc_item,
                    qi.qc_item,
                    qi.cat_id,
                    qi.persentase,
                    qtd.dt_aprv,
                    vpp.ord_amt,
                    qtd.img_link
                ")
                ->orderBy('qtd.id_qc_item')
                ->get()
                ->map(function ($item) {
                    $item->persentase = (float) $item->persentase;
                    $item->ord_amt = (int) $item->ord_amt;

                    return $item;
                });

            $danaDitahan = $progress
                ->filter(function ($item) use ($cat_id) {
                    return $item->cat_id == $cat_id;
                })
                ->sum(function ($item) {
                    return $item->persentase * $item->ord_amt;
                });

            $image = $progress
                ->filter(function ($item) use ($cat_id) {
                    return $item->cat_id == $cat_id;
                })
                ->sortByDesc('id_qc_item')
                ->first();

            $result = [
                'progress' => $progress,
                'image' => $image?->img_link ? url("/storage/{$image->img_link}") : null,
                'dana_ditahan' => $danaDitahan,
                'input' => $saved
            ];

            return $this->sendResponse($result, 'Successfully get Data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function saveLogPrint(Request $request)
    {
        $printId = '';
        $qc_trans_id = $request->qc_trans_id ?? '';
        $cat_id = $request->cat_id ?? '';
        $bank_name = $request->bank_name ?? '';
        $cabang = $request->cabang ?? '';
        $ac_num = $request->ac_num ?? '';
        $dana_ditahan = $request->dana_ditahan ?? '';
        $tanggal = $request->tanggal ?? '';

        try {
            $cekDoc = DB::connection("sqlSrvBCKI")
                ->table('pencairan_spr')
                ->where('qc_trans_id', $qc_trans_id)
                ->where('cat_id', $cat_id)
                ->first();

            $data = [
                'qc_trans_id' => $qc_trans_id,
                'cat_id' => $cat_id,
                'bank_name' => $bank_name,
                'cabang' => $cabang,
                'ac_num' => $ac_num,
                'dana_ditahan' => $dana_ditahan,
                'dt_cetak' => $tanggal,
            ];

            if ($cekDoc) {
                $printId = $cekDoc->print_id;
                $data['dt_modified'] = now();
                $data['modified_by'] = getAuth($request)->user_id2;

                DB::connection("sqlSrvBCKI")
                    ->table('pencairan_spr')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('cat_id', $cat_id)
                    ->update($data);
            } else {
                $printId = $this->genPrintId();
                $data['print_id'] = $printId;
                $data['dt_created'] = now();
                $data['created_by'] = getAuth($request)->user_id2;

                DB::connection("sqlSrvBCKI")
                    ->table('pencairan_spr')
                    ->insert($data);
            }

            // -- result
            $data = [
                'print_id' => $printId
            ];

            return $this->sendResponse($data, 'Successfully save log');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getRekening(Request $request)
    {
        $bank = $request->bank_name;

        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('account as acc')
                ->join('g_ledger as gl', 'gl.ac_num', '=', 'acc.ac_num')
                ->where('acc.bank_name', $bank)
                ->selectRaw("
                    TRIM(acc.ac_num) as ac_num,
                    TRIM(gl.ac_name) as ac_name
                ")
                ->get();

            return $this->sendResponse($result, 'Successfully save log');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function docStatus($order_id)
    {
        $result = DB::connection("sqlSrvBCKI")
            ->table('operational_progress as op')
            ->join('appl_constant as ac', 'op.job_code', '=', 'ac.str1')
            ->where('ac.key_code', 'OPL_PROG')
            ->where('op.order_id', $order_id)
            ->where('op.date_aprv', '!=', '1900-01-01 00:00:00.000')
            ->get();

        $data = [
            'pondasi' => $result->contains('job_code', 'APRV03'),
            'atap' => $result->contains('job_code', 'APRV05'),
            'surat' => $result->contains('job_code', 'APRV07'),
            'dajam' => $result->contains('job_code', 'APRV08')
        ];

        return $data;
    }

    private function genPrintId()
    {
        $year = date('Y');
        $month = date('n'); // -- bulan dalam angka tanpa leading zero (1-12)
        $romanMonths = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII'
        ];
        $romanMonth = $romanMonths[$month];

        // -- nomor urut terakhir berdasarkan tahun
        $lastDoc = DB::connection("sqlSrvBCKI")
            ->table('pencairan_spr')
            ->whereYear('dt_created', $year)
            ->whereNotNull('print_id')
            ->orderByDesc('print_id')
            ->first();

        if ($lastDoc && preg_match('/^(\d{4})\/FPDI\/[IVXL]+\/\d{4}$/', $lastDoc->print_id, $matches)) {
            $lastNumber = (int) $matches[1];
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '0001';
        }

        $printId = "{$nextNumber}/FPDI/{$romanMonth}/{$year}";

        return $printId;
    }

    private function paymentStatus($order_id)
    {
        $result = DB::connection("sqlSrvBCKI")
            ->table('pay_term_dtl AS p')
            ->leftjoin('pencairan_spr as ps', function ($join) {
                $join->on('ps.doc_id', '=', 'p.order_id')
                    ->on('p.tr_seq', '=', 'ps.item_num')
                    ->where('ps.doc_type', '=', 'KPR');
            })
            ->where('p.order_id', $order_id)
            ->where('p.param1', '>', 0)
            ->selectRaw("
                CONCAT('T', CAST(CAST(p.tr_seq AS INT) AS VARCHAR)) AS pay_term,
		        ISNULL(ps.amt_request, 0) AS amt_request
            ")
            ->get();

        // dd($result);

        $data = [
            'T1' => null,
            'T2' => null,
            'T3' => null,
            'T4' => null,
            'T5' => null
        ];

        foreach ($result as $row) {
            if (array_key_exists($row->pay_term, $data)) {
                $data[$row->pay_term] = $row->amt_request;
            }
        }
        // dd($data);
        return $data;
    }

    public function getDetilPencairan(Request $request)
    {
        $doc_id = $request->doc_id ?? '';
        $doc_type = $request->doc_type ?? '';
        $item_num = $request->item_num ?? '';

        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('pencairan_spr as ps')
                ->join('v_penjualan_payment as vpp', 'ps.doc_id', '=', 'vpp.order_id')
                ->where('ps.doc_type', $doc_type)
                ->where('ps.doc_id', $doc_id)
                ->where('ps.item_num', $item_num)
                ->where('ps.active_flag', 'Y')
                ->selectRaw("
                                tr_id,
                                doc_type,
                                doc_id,
                                item_num,
                                bank_name,
                                bank_acc,
                                ac_num,
                                cabang,
                                amt_request,
                                amt_cair,
                                dt_created,
                                dt_cair,
                                print_id,
                                dt_cetak,
                                order_id,
                                NamaCustomer,
                                NamaSales,
                                site_name,
                                cluster_name,
                                house_name,
                                pay_method,
                                status,
                                no_urut,
                                $item_num as item_num
                            ")
                ->first();

            return $this->sendResponse($result, 'Successfully get Data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getNilaiPencairan(Request $request)
    {
        $doc_id = $request->doc_id ?? '';
        $doc_type = $request->doc_type ?? '';
        $item_num = $request->item_num ?? '';

        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('pay_term_dtl as p')
                ->join('CustomerOrder as co', 'p.order_id', '=', 'co.order_id')
                ->join('CustomerOrderDetil as cod', 'p.order_id', '=', 'cod.order_id')
                ->leftjoin('account as a', 'p.bank_acc', '=', 'a.bank_acc')
                ->where('p.pay_term_id', $doc_type)
                ->where('p.order_id', $doc_id)
                ->where('p.tr_seq', $item_num)
                ->selectRaw("
                                co.pay_method,
                                p.bank_name,
                                TRIM(p.bank_acc) as bank_acc,
                                p.param1 as percentage,
                                CONCAT(CAST(CAST(p.param1 AS INT) AS VARCHAR),'%') AS w_percentage,
                                CONCAT('T', CAST(CAST(p.tr_seq AS INT) AS VARCHAR)) AS pay_term,
                                (CAST(p.param1 AS DECIMAL(18,2)) / 100) * 
                                CAST((cod.unit_price - cod.disc_unit - co.dp_amt - co.utj) AS DECIMAL(18,2)) AS pay_term_amt,
                                cod.unit_price,
                                cod.disc_unit,
                                co.dp_amt,
                                co.utj,
                                TRIM(a.ac_num) AS ac_num,
                                $item_num as item_num
                            ")
                ->first();

            return $this->sendResponse($result, 'Successfully get Data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function saveCDB_KPR(Request $request)
    {
        $doc_type = $request->doc_type ?? '';
        $doc_id = $request->doc_id ?? '';
        $item_num = $request->item_num ?? '';
        $bank_name = $request->bank_name ?? '';
        $cabang = $request->cabang ?? '';
        $bank_acc = $request->bank_acc ?? '';
        $ac_num = $request->ac_num ?? '';
        $amt_request = $request->amt_request ?? 0;
        $no_urut = $request->no_urut ?? $this->generateNoUrut();
        $tr_id = $request->tr_id ?? '';
        // dd($tr_id);
        if (empty($bank_acc)) {
            $bank_acc_cek = DB::connection("sqlSrvBCKI")
                ->table('account')
                ->selectraw('TRIM(bank_acc) AS bank_acc')
                ->where("ac_num", $ac_num)
                ->value('bank_acc');
            $bank_acc = $bank_acc_cek;
        }

        try {
            DB::connection('sqlSrvBCKI')->beginTransaction();

            // $tr_id = $this->generateTrId($request);
            // dd($tr_id);
            $existingData = DB::connection('sqlSrvBCKI')
                ->table('pencairan_spr')
                ->where('tr_id', $tr_id)
                ->first();
            // dd($existingData);
            $data = [
                'doc_type' => $doc_type ?? '',
                'doc_id' => $doc_id ?? '',
                'item_num' => $item_num ?? '',
                'bank_name' => $bank_name ?? '',
                'bank_acc' => $bank_acc ?? '',
                'ac_num' => $ac_num ?? '',
                'cabang' => $cabang ?? '',
                'amt_request' => $amt_request ?? '',
                'no_urut' => $no_urut ?? '',
                'stat' => 'O',
                'amt_cair' => '0',
                // Gunakan updated_at jika update, created_at jika insert
                ($existingData ? 'dt_created' : 'dt_created') => now("Asia/Jakarta"),
                ($existingData ? 'created_by' : 'created_by') => getAuth($request)->user_id2
            ];

            if ($existingData) {
                // PROSES UPDATE
                $save_log = DB::connection('sqlSrvBCKI')
                    ->table('pencairan_spr')
                    ->where('tr_id', $tr_id)
                    ->update($data);
                
                $message = 'Successfully updated log Pencairan Dana Bank';
            } else {
                // PROSES INSERT
                $tr_id = $this->generateTrId($request); // Generate ID baru jika insert
                $data['tr_id'] = $tr_id;
                
                $save_log = DB::connection('sqlSrvBCKI')
                    ->table('pencairan_spr')
                    ->insert($data);
                    
                $message = 'Successfully saved log Pencairan Dana Bank';
            }

            if ($save_log) {
                DB::connection('sqlSrvBCKI')->commit();
                return $this->sendResponse(true, $message);
            } else {
                DB::connection('sqlSrvBCKI')->rollBack();
                return $this->sendResponse(false, $message);
            }
        } catch (\Throwable $e) {
            DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getLastnoUrut(){
        $noUrut = $this->generateNoUrut();

        return $this->sendResponse($noUrut, 'Successfully get Last no Urut');

    }

    public function generateTrId()
    {
        $now = now("Asia/Jakarta"); // gunakan Carbon jika belum diset
        $datePrefix = $now->format('ymd'); // YYMMDD

        // Ambil tr_id terakhir di hari ini
        $last = DB::connection("sqlSrvBCKI")
            ->table('pencairan_spr')
            ->whereRaw("LEFT(tr_id, 6) = ?", [$datePrefix])
            ->orderByDesc('tr_id')
            ->first(['tr_id']);

        if ($last) {
            $lastSequence = (int) substr($last->tr_id, 6); // ambil 5 digit terakhir
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }

        // Format ke 5 digit
        $formattedSequence = str_pad($newSequence, 5, '0', STR_PAD_LEFT);

        return $datePrefix . $formattedSequence; // gabungkan
    }

    public function generateNoUrut()
    {
        $year = date('Y');
        $monthNum = (int)date('n'); // Bulan dalam angka (1-12)
        
        $romanMonths = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        $romanMonth = $romanMonths[$monthNum];
        // 1. Ambil data terakhir khusus di bulan dan tahun yang sedang berjalan saja
        // Agar nomor urut bisa reset ke 0001 setiap ganti bulan/tahun
        $lastDoc = DB::connection("sqlSrvBCKI")
            ->table('pencairan_spr')
            ->whereYear('dt_created', $year)
            ->whereMonth('dt_created', $monthNum) // Filter bulan aktif
            ->whereNotNull('no_urut') // Menggunakan field no_urut sesuai instruksi
            ->orderBy('no_urut', 'desc')
            ->first();
        // 2. Logika Penentuan Nomor Urut Next
        if ($lastDoc) {
            // Ambil 4 digit pertama dari string no_urut (XXXX)
            $lastNumber = (int) substr($lastDoc->no_urut, 0, 4);
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            // Jika tidak ada data di bulan/tahun tersebut, reset ke 0001
            $nextNumber = '0001';
        }

        // 3. Gabungkan Format: XXXX/bcki-FIN/BULAN/TAHUN
        $noUrut = "{$nextNumber}/bcki-FIN/{$romanMonth}/{$year}";

        return $noUrut;
    }
}
