<?php

namespace App\Http\Controllers\finance;

use App\Http\Controllers\Controller;
use App\Traits\getFilterTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class KasBontoDebitNoteController extends Controller
{
    public function getData(){
        $data = DB::connection('sqlSrvFPI')
            ->table('v_kasbon_list')
            ->where('tipe', 'EX_MONEY')
            ->get();

        return $this->sendResponse($data, 'Successfully retrieved Kasbon data');
    }

    public function confirmKasbon(Request $request)
    {
        $tr_id = $request->tr_id;
        $tipe = $request->tipe; 
        $tgl = now('Asia/Jakarta');
        $userId = getAuth($request)->user_id2;

        // 1. Update dt_confirm di kb_hdr
        DB::connection('sqlSrvFPI')
        ->table('kb_hdr')
        ->where('tr_id', $tr_id)        
        ->update([
            'dt_confirm' => $tgl
        ]);

        // 2. Generate BK ID
        $bk_id = $this->genID($tgl);
        // dd($bk_id);
        // 3. Ambil data dari kb_hdr + kb_dtl + vendor
        $data = DB::connection('sqlSrvFPI')
            ->table('kb_hdr as a')
            ->join('kb_dtl as b', 'a.tr_id', '=', 'b.tr_id')
            ->join('vendor as c', 'a.vendor_id', '=', 'c.vendor_id')
            ->where('a.tr_id', $tr_id)
            ->where('a.tr_type', $tipe)
            ->selectRaw("
                a.dt_kb,
                a.tr_id_ref,
                c.vendor_name,
                a.office_id,
                a.vendor_id,
                SUM(b.amt) as total
            ")
            ->groupBy('a.dt_kb', 'a.tr_id_ref', 'c.vendor_name', 'a.office_id', 'a.vendor_id')
            ->first();
        // dd($data);
        if ($data) {
            $periode = Carbon::parse($data->dt_kb)->format('Ym');

            // 4. Insert ke bank_note
            DB::connection('sqlSrvFPI')->table('bank_note')->insert([
                'tr_type'     => 'D',
                'bank_acc'    => 'DEBIT NOTE',
                'tr_id'       => $bk_id,
                'dt_trans'    => $data->dt_kb,
                'descr'       => trim($data->tr_id_ref) . ' - ' . trim($data->vendor_name),
                'db_amt'      => $data->total,
                'user_id'     => $userId,
                'remark'      => trim($data->tr_id_ref) . ' - ' . trim($data->vendor_name),
                'period'      => $periode,
                'dt_modified' => $tgl,
                'office_id'   => $data->office_id,
                'curr_id'     => 'IDR',
                'Rate'        => 1,
                'doc_id'      => $tr_id,
                'customer'    => $data->vendor_id,
            ]);

            // 5. Hapus credit_note lama jika ada
            DB::connection('sqlSrvFPI')
                ->table('credit_note')
                ->where('tr_id', $tr_id)
                ->delete();

            // 6. Insert ke credit_note
            DB::connection('sqlSrvFPI')->table('credit_note')->insert([
                'office_id'   => $data->office_id,
                'tr_id'       => $tr_id,
                'tr_type'     => 'D',
                'cust_id'     => $data->vendor_id,
                'rtn_id'      => $tr_id,
                'doc_id'      => $data->tr_id_ref,
                'bk'          => $bk_id,
                'period'      => $periode,
                'tr_date'     => $data->dt_kb,
                'remark'      => '',
                'tr_amt'      => $data->total,
                'user_id'     => $userId,
                'dt_modified' => $tgl,
            ]);
        }


        return $this->sendResponse($bk_id, 'Successfully Confirmed Kasbon');
        
    }

    public function genID($tgl)
    {
        $prefix = 'K';
        $fixed = '21';
        $datePart = date('ymd', strtotime($tgl));
        $kodeAwal = $prefix . $datePart . $fixed;

        // Ambil semua kode hari ini yang sudah ada
        $existingIds = DB::connection('sqlSrvFPI')
            ->table('bank_note')
            ->whereRaw("LEFT(tr_id, 9) = ?", [$kodeAwal])
            ->pluck('tr_id')
            ->toArray();
        
        // // Ambil tr_id terakhir yang dimulai dengan $kodeAwal
        // $lastTrId = DB::connection('sqlSrvFPI') // sesuaikan jika perlu
        //     ->table('credit_note') // Ganti dengan nama tabel yang menyimpan tr_id
        //     ->whereRaw("LEFT(bk, 9) = ?", [$kodeAwal])
        //     ->orderByDesc('bk')
        //     ->value('bk');

        // $urut = 1;
        // if ($lastTrId) {
        //     $lastUrut = (int) substr($lastTrId, -3); // Ambil 3 digit terakhir
        //     $urut = $lastUrut + 1;
        // }

        // return $kodeAwal . str_pad($urut, 3, '0', STR_PAD_LEFT);

        // Ambil hanya 3 digit terakhir dari kode
        $usedNumbers = collect($existingIds)
            ->map(function ($bk) {
                return (int) substr($bk, -3); // contoh: 001 → 1
            })
            ->sort()
            ->values();
        // dd($usedNumbers);
        // Cari nomor urut berikutnya
        $nextUrut = 1;
        while ($usedNumbers->contains($nextUrut)) {
            $nextUrut++;
        }

        return $kodeAwal . str_pad($nextUrut, 3, '0', STR_PAD_LEFT);
    }

}
