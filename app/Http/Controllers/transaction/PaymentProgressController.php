<?php

namespace App\Http\Controllers\transaction;

use App\Http\Controllers\Controller;
use App\Traits\getFilterTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PaymentProgressController extends Controller
{
    use getFilterTrait;

    public function index()
    {
        // -- get sites
        $sites = DB::connection("sqlSrvBCKI")
            ->table('site as st')
            ->join('city_list as lc', 'st.city', 'lc.city_code')
            ->where('stat', 'Y')
            ->selectRaw("
                st.id_site,
                st.site_name,
                st.city as id_prov_city,
                lc.province_code as id_province
            ")
            ->get();

        // -- get province
        $provinces = DB::connection("sqlSrvBCKI")
            ->table('province_list')
            ->selectRaw("
                province_code as id_province,
                province
            ")
            ->get();

        // -- get appl_contant PUTUSAN_AK
        $putusan = DB::connection("sqlSrvBCKI")
            ->table('appl_constant')
            ->where('key_code', 'PUTUSAN_AK')
            ->selectRaw("
                str1 as value,
                str2 as label
            ")
            ->get();

        // -- get banks
        $bank = DB::connection("sqlSrvBCKI")
            ->table('account')
            ->selectraw('trim(bank_name) as bank_name')
            ->distinct()
            ->get();

        // -- sec_env_conf
        $aprvPP = $this->getEnvConf('FINANCE', 'APRV_PP');

        // -- get appl_constant PAY_PROG
        $payprog = DB::connection("sqlSrvBCKI")
            ->table('appl_constant')
            ->where('key_code', 'PAY_PROG')
            ->selectRaw("
                str1 as value,
                str2 as label
            ")
            ->get();

        $check = DB::connection("sqlSrvBCKI")
            ->table('appl_constant')
            ->where('key_code', 'DOC_REQUIRED')
            ->selectRaw("
                str1 as value,
                str2 as label
            ")
            ->get();
        
        return response()->json([
            'sites' => $sites,
            'provinces' => $provinces,
            'putusan' => $putusan,
            'bank' => $bank,
            'payprog' => $payprog,
            'env_conf' => [
                'aprv_pp' => $aprvPP
            ],
            'check' => $check
        ]);
    }

    public function getData(Request $request)
    {
        $province_id = $request->province_id ?? '';
        $city_id = $request->city_id ?? '';
        $site_id = $request->site_id ?? '';

        // return $this->sendResponse([], 'Successfully get Payment Progress');

        try {
            $datas = DB::connection("sqlSrvBCKI")
                ->table('v_payment_progress as pp')
                ->selectRaw('
                    pp.*
                ')
                ->join('site as si', 'pp.id_site', '=', 'si.id_site')
                ->join('city_list as cl', 'si.city', '=', 'cl.city_code')
                ->join('province_list as pl', 'cl.province_code', '=', 'pl.province_code')
                ->where('pp.status', 'O');

            if ($province_id) {
                $datas->where('pl.province_code', $province_id);
            }

            if ($city_id) {
                $datas->where('cl.city_code', $city_id);
            }

            if ($site_id) {
                $datas->where('si.id_site', $site_id);
            }

            $datas = $datas->orderBy('pp.order_id')
                ->get()
                ->map(function ($item) {
                    $item->dt_aprv1 = $item->dt_aprv1 ? date('Y-m-d', strtotime($item->dt_aprv1)) : '1900-01-01';
                    $item->dt_aprv2 = $item->dt_aprv2 ? date('Y-m-d', strtotime($item->dt_aprv2)) : '1900-01-01';
                    $item->dt_aprv3 = $item->dt_aprv3 ? date('Y-m-d', strtotime($item->dt_aprv3)) : '1900-01-01';
                    $item->dt_aprv4 = $item->dt_aprv4 ? date('Y-m-d', strtotime($item->dt_aprv4)) : '1900-01-01';
                    $item->dt_aprv5 = $item->dt_aprv5 ? date('Y-m-d', strtotime($item->dt_aprv5)) : '1900-01-01';
                    $item->dt_aprv6 = $item->dt_aprv6 ? date('Y-m-d', strtotime($item->dt_aprv6)) : '1900-01-01';
                    $item->dt_aprv7 = $item->dt_aprv7 ? date('Y-m-d', strtotime($item->dt_aprv7)) : '1900-01-01';
                    $item->dt_aprv8 = $item->dt_aprv8 ? date('Y-m-d', strtotime($item->dt_aprv8)) : '1900-01-01';
                    $item->order_date = $item->order_date ? date('Y-m-d', strtotime($item->order_date)) : '1900-01-01';

                    return $item;
                });

            return $this->sendResponse($datas, 'Successfully get Payment Progress');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function approveData(Request $request)
    {
        $order_id = $request->order_id ?? '';
        $approve_type = $request->approve_type;
        $putusan_id = $request->putusan_id ?? '';
        $remark = $request->remark ?? '';
        $amount = $request->amount ?? '';
        $date_aprv = $request->date_aprv ?? '';
        $bank_name = $request->bank_name ?? '';
        $check = json_decode($request->check) ?? [];
        $file_attachments = [];

        // -- tr id
        $trId = $this->genTrId();

        // -- attachments
        if ($request->hasFile('file_attachments')) {
            foreach ($request->file('file_attachments') as $key => $file) {
                if ($file && $file->isValid()) {
                    $extension = $file->getClientOriginalExtension();
                    $originalName = $file->getClientOriginalName();

                    $filename = Str::random(40) . '.' . $extension;
                    $path = 'fpis/payment_progress';

                    // -- save file local
                    Storage::disk('public')->put("$path/$filename", file_get_contents($file));
                    $file_attachments[] = [
                        'tr_id' => $trId,
                        'doc_type' => $order_id,
                        'doc_name' => $originalName,
                        'seq_num' => $key + 1,
                        'n_file' => $filename,
                        'loc_file' => $path,
                        'aprv_type' => $approve_type,
                        'dt_modified' => now('Asia/Jakarta'),
                        'user_id' => getAuth($request)->user_id2,
                        'office_id' => "1",
                    ];
                }
            }
        }

        $dataSave = [
            "tr_id" => $trId,
            "order_id" => $order_id,
            "job_code" => $approve_type,
            "date_aprv" => $date_aprv,
            "remark_putusan" => $remark,
            "nilai_putusan" => $amount,
            "dt_created" => now('Asia/Jakarta'),
            "created_by" => getAuth($request)->user_id2,
        ];

        // --  ANALISA KREDIT
        if ($approve_type == 'APRV3') {
            $dataSave['bank_name'] = $bank_name;
        }

        // -- PUTUSAN
        if ($approve_type == 'APRV4') {
            $dataSave['id_putusan'] = $putusan_id;
            $dataSave['bank_name'] = $bank_name;
        }

        try {
            // -- approve data            
            DB::connection("sqlSrvBCKI")
                ->table('payment_progress')
                ->insert($dataSave);

            // -- file attachments
            if ($file_attachments) {
                DB::connection("sqlSrvBCKI")
                    ->table('payment_progress_doc')
                    ->insert($file_attachments);
            }

            $getDocId = DB::connection("sqlSrvBCKI")
              ->table('document_required')
              ->orderBy('tr_id', 'desc')
              ->first();
              
            $data = [];
            $doc_tr_id = $getDocId->tr_id ?? 0;
            
            foreach ($check as $key => $item) {
              $doc_tr_id++;

              if($item->value){
                $data[] = [
                  'tr_id'      => $doc_tr_id,
                  'order_id'   => $order_id,
                  'job_code'   => $item->code,
                  'date_aprv'  => now('Asia/Jakarta'),
                  'dt_created' => now('Asia/Jakarta'),
                  'created_by' => getAuth($request)->user_id2,
                ];
              }

            }

            DB::connection("sqlSrvBCKI")
              ->table('document_required')
              ->insert($data);

            return $this->sendResponse('Success', 'Successfully Approve');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function approveDataLegal(Request $request)
    {
        $order_id = $request->order_id ?? '';
        $approve_type = $request->approve_type;
        $putusan_id = $request->putusan_id ?? '';
        $remark = $request->remark ?? '';
        $amount = $request->amount ?? '';
        $date_aprv = $request->date_aprv ?? '';
        $bank_name = $request->bank_name ?? '';
        $remark_legal = $request->remark_legal ?? '';
        $nama_notaris = $request->nama_notaris ?? '';
        $no_pbg = $request->no_pbg ?? '';
        $no_pbb = $request->no_pbb ?? '';
        $check = json_decode($request->check) ?? [];
        $file_attachments = [];

        $existingProgress = DB::connection("sqlSrvBCKI")
        ->table('payment_progress')
        ->where('order_id', $order_id)
        ->where('job_code', $approve_type)
        ->first();

        // -- tr id
        $trId = $existingProgress ? $existingProgress->tr_id : $this->genTrId();

        $lastSeq = 0;
        if ($existingProgress) {
            $lastSeq = DB::connection("sqlSrvBCKI")
                ->table('payment_progress_doc')
                ->where('tr_id', $trId)
                ->max('seq_num') ?? 0;
        }

        // -- attachments
        if ($request->hasFile('file_attachments')) {
            foreach ($request->file('file_attachments') as $key => $file) {
                if ($file && $file->isValid()) {
                    $extension = $file->getClientOriginalExtension();
                    $originalName = $file->getClientOriginalName();
                    $filename = Str::random(40) . '.' . $extension;
                    $path = 'fpis/payment_progress';

                    // -- save file local
                    Storage::disk('public')->put("$path/$filename", file_get_contents($file));
                    $lastSeq++;
                    $file_attachments[] = [
                        'tr_id' => $trId,
                        'doc_type' => $order_id,
                        'doc_name' => $originalName,
                        'seq_num' => $lastSeq,
                        'n_file' => $filename,
                        'loc_file' => $path,
                        'aprv_type' => $approve_type,
                        'dt_modified' => now('Asia/Jakarta'),
                        'user_id' => getAuth($request)->user_id2,
                        'office_id' => "1",
                    ];
                }
            }
        }

        $dataSave = [
            "tr_id" => $trId,
            "order_id" => $order_id,
            "job_code" => $approve_type,
            "date_aprv" => $date_aprv,
            "remark_putusan" => $remark,
            "nilai_putusan" => $amount,
            "remark_legal" => $remark_legal,
            "nama_notaris" => $nama_notaris,
            "no_pbg" => $no_pbg,
            "no_pbb" => $no_pbb,
            "dt_created" => now('Asia/Jakarta'),
            "created_by" => getAuth($request)->user_id2,
        ];

        // --  ANALISA KREDIT
        if ($approve_type == 'APRV3') {
            $dataSave['bank_name'] = $bank_name;
        }

        // -- PUTUSAN
        if ($approve_type == 'APRV4') {
            $dataSave['id_putusan'] = $putusan_id;
            $dataSave['bank_name'] = $bank_name;
        }

        try {
            // -- approve data            
            if ($existingProgress) {
            DB::connection("sqlSrvBCKI")
                ->table('payment_progress')
                ->where('tr_id', $trId)
                ->update($dataSave);
            } else {
                $dataSave['tr_id'] = $trId;
                DB::connection("sqlSrvBCKI")
                    ->table('payment_progress')
                    ->insert($dataSave);
            }

            // -- file attachments
            if ($file_attachments) {
                DB::connection("sqlSrvBCKI")
                    ->table('payment_progress_doc')
                    ->insert($file_attachments);
            }

            $getDocId = DB::connection("sqlSrvBCKI")
              ->table('document_required')
              ->orderBy('tr_id', 'desc')
              ->first();
              
            $data = [];
            $doc_tr_id = $getDocId->tr_id ?? 0;
            
            foreach ($check as $key => $item) {
              $doc_tr_id++;

              if($item->value){
                $data[] = [
                  'tr_id'      => $doc_tr_id,
                  'order_id'   => $order_id,
                  'job_code'   => $item->code,
                  'date_aprv'  => now('Asia/Jakarta'),
                  'dt_created' => now('Asia/Jakarta'),
                  'created_by' => getAuth($request)->user_id2,
                ];
              }

            }

            DB::connection("sqlSrvBCKI")
              ->table('document_required')
              ->insert($data);

            return $this->sendResponse('Success', 'Successfully Approve');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getApproveData(Request $request)
    {
        $order_id = $request->order_id ?? '';
        $approve_type = $request->approve_type;
        $tr_id = $request->tr_id;

        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('payment_progress_doc')
                ->where('doc_type', $order_id)
                ->where('aprv_type', $approve_type)
                ->when($tr_id, function ($query, $tr_id) {
                    return $query->where('tr_id', $tr_id);
                })
                ->selectRaw("
                        trim(doc_name) as doc_name,
                        n_file as doc_url,
                        loc_file,
                        seq_num,
                        trim(doc_type) as order_id,
                        tr_id
                    ")
                ->orderBy('seq_num')
                ->get()
                ->map(function ($item) {
                    return [
                        'doc_name' => $item->doc_name,
                        'doc_url' => url("/storage/{$item->loc_file}/{$item->doc_url}"),
                        'seq_num' => $item->seq_num,
                        'order_id' => $item->order_id,
                        'tr_id' => $item->tr_id
                    ];
                });

            return $this->sendResponse($result, 'Successfully get Approve Data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getDetailData(Request $request)
    {
        $order_id = $request->order_id ?? '';

        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('payment_progress as pp')
                ->join('appl_constant as ack', function ($q) {
                    $q->on('pp.job_code', '=', 'ack.str1')
                        ->where('ack.key_code', 'PAY_PROG');
                })
                ->leftJoin('appl_constant as acp', function ($q) {
                    $q->on('pp.id_putusan', '=', 'acp.str1')
                        ->where('acp.key_code', 'PUTUSAN_AK');
                })
                ->where('order_id', $order_id)
                ->selectRaw("
                    ack.str1 as code,    
                    ack.str2 as code_desc,
                    pp.tr_id,
                    pp.order_id,
                    pp.bank_name,
                    pp.id_putusan,
                    acp.str2 as putusan,
                    pp.remark_putusan as remark,
                    pp.nilai_putusan as amount,
                    pp.date_aprv
                ")
                ->orderBy('tr_id')
                ->whereNotIn('ack.str1', ['APRV9']) // -- filter data yg ditampilkan di detail, hanya APRV3 (analisa kredit) dan APRV4 (putusan)
                ->get();

            return $this->sendResponse($result, 'Successfully get Detail Data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getDetailDataLegal(Request $request)
    {
        $order_id = $request->order_id ?? '';

        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('payment_progress as pp')
                ->join('appl_constant as ack', function ($q) {
                    $q->on('pp.job_code', '=', 'ack.str1')
                        ->where('ack.key_code', 'PAY_PROG');
                })
                ->leftJoin('appl_constant as acp', function ($q) {
                    $q->on('pp.id_putusan', '=', 'acp.str1')
                        ->where('acp.key_code', 'PUTUSAN_AK');
                })
                ->where('order_id', $order_id)
                ->selectRaw("
                    ack.str1 as code,    
                    ack.str2 as code_desc,
                    pp.tr_id,
                    pp.order_id,
                    pp.bank_name,
                    pp.id_putusan,
                    acp.str2 as putusan,
                    pp.remark_putusan as remark,
                    pp.nilai_putusan as amount,
                    pp.date_aprv,
                    pp.nama_notaris,
                    pp.no_pbg,
                    pp.no_pbb,
                    pp.remark_legal
                ")
                ->orderBy('tr_id')
                ->whereIn('ack.str1', ['APRV9']) // -- filter data yg ditampilkan di detail, hanya APRV3 (analisa kredit) dan APRV4 (putusan)
                ->get();

            return $this->sendResponse($result, 'Successfully get Detail Data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getNoSpr()
    {
        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('CustomerOrder as co')
                ->join('Customer as cu', 'co.CustomerId', '=', 'cu.CustomerId')
                ->join('site as st', 'co.id_site', '=', 'st.id_site')
                ->where('status', 'O') // -- open
                ->selectRaw("
                    co.order_id as value,
                    CONCAT(co.order_id, ' - ', cu.NamaCustomer) as label
                ")
                ->get();

            return $this->sendResponse($result, 'Successfully get SPR');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function genTrId()
    {
        $datePrefix = now('Asia/Jakarta')->format('ymd'); // YYMMDD
        $lastRecord = DB::connection("sqlSrvBCKI")
            ->table('payment_progress')
            ->where('tr_id', 'like', $datePrefix . '%')
            ->orderByDesc('tr_id')
            ->first();

        if ($lastRecord) {
            $lastNumber = (int)substr($lastRecord->tr_id, -3);
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }

        return $datePrefix . $newNumber;
    }

    public function deleteData(Request $request)
    {
        $order_id = $request->order_id ?? '';
        $tr_id = $request->tr_id ?? '';
        $code = $request->code ?? '';

        if (empty($order_id) && empty($tr_id) && empty($code)) {
            return $this->sendError('Setidaknya salah satu parameter (order_id, tr_id, atau code) harus disediakan untuk menghapus data.', 'Invalid Parameters', 400);
        }

        try {
            // Memulai transaksi database untuk memastikan operasi atomik
            DB::connection("sqlSrvBCKI")->beginTransaction();

            $query = DB::connection("sqlSrvBCKI")
                ->table('payment_progress'); // Fokus langsung pada tabel yang akan dihapus

            // Menambahkan kondisi WHERE berdasarkan parameter yang diberikan
            if (!empty($order_id)) {
                $query->where('order_id', $order_id);
            }

            if (!empty($tr_id)) {
                $query->where('tr_id', $tr_id);
            }

            if (!empty($code)) {
                $query->where('job_code', $code);
            }

            // Melakukan operasi penghapusan dan mendapatkan jumlah baris yang terpengaruh
            $deletedRows = $query->delete();

            // Update yang ada, hapus gambar yg mau dihapus
            $listAttch = DB::connection('sqlSrvBCKI')
                ->table('payment_progress_doc')
                ->where('doc_type', $order_id)
                ->where('tr_id', $tr_id)
                ->where('aprv_type', $code)
                ->get();
            
            foreach ($listAttch as $item) {
                $filePath = $item->loc_file . '/' . $item->n_file;

            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
                }
            }

            //delete image
            DB::connection('sqlSrvBCKI')
                ->table('payment_progress_doc')
                ->where('doc_type', $order_id)
                ->where('tr_id', $tr_id)
                ->where('aprv_type', $code)
                ->delete();

            // Delete Checklist

            DB::connection('sqlSrvBCKI')
                ->table('document_required')
                ->where('order_id', $order_id)
                ->delete();

            // Melakukan commit transaksi jika berhasil
            DB::connection("sqlSrvBCKI")->commit();

            if ($deletedRows > 0) {
                return $this->sendResponse([], 'Data berhasil dihapus. Jumlah baris yang dihapus: ' . $deletedRows);
            } else {
                return $this->sendResponse([], 'Tidak ada data yang ditemukan atau dihapus berdasarkan kriteria yang diberikan.');
            }

        } catch (\Exception $e) {
            // Melakukan rollback transaksi jika terjadi kesalahan
            DB::connection("sqlSrvBCKI")->rollBack();
            return $this->sendError($e->getMessage(), 'Gagal menghapus data', 500);
        }
    }

    public function deleteAttachment(Request $request)
    {
        $tr_id = $request->tr_id ?? '';
        $seq_num = $request->seq_num ?? '';
        $order_id = $request->order_id ?? '';
        $aprv_type = $request->aprv_type ?? '';

        if (empty($tr_id) || empty($seq_num) || empty($order_id) || empty($aprv_type)) {
            return $this->sendError('Parameter tr_id, seq_num, order_id, dan aprv_type harus disediakan untuk menghapus attachment.', 'Invalid Parameters', 400);
        }

        try {
            // Memulai transaksi database untuk memastikan operasi atomik
            DB::connection("sqlSrvBCKI")->beginTransaction();

            // Mendapatkan data attachment yang akan dihapus
            $attachment = DB::connection("sqlSrvBCKI")
                ->table('payment_progress_doc')
                ->where('tr_id', $tr_id)
                ->where('seq_num', $seq_num)
                ->where('doc_type', $order_id)
                ->where('aprv_type', $aprv_type)
                ->first();

            if (!$attachment) {
                return $this->sendError('Attachment tidak ditemukan dengan kriteria yang diberikan.', 'Not Found', 404);
            }

            // Menghapus file dari storage
            $filePath = $attachment->loc_file . '/' . $attachment->n_file;
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            // Menghapus record attachment dari database
            DB::connection("sqlSrvBCKI")
                ->table('payment_progress_doc')
                ->where('tr_id', $tr_id)
                ->where('seq_num', $seq_num)
                ->where('doc_type', $order_id)
                ->where('aprv_type', $aprv_type)
                ->delete();

            // Melakukan commit transaksi jika berhasil
            DB::connection("sqlSrvBCKI")->commit();

            return $this->sendResponse([], 'Attachment berhasil dihapus.');
        } catch (\Exception $e) {
            // Melakukan rollback transaksi jika terjadi kesalahan
            DB::connection("sqlSrvBCKI")->rollBack();
            return $this->sendError($e->getMessage(), 'Gagal menghapus attachment', 500);
        }
    }

    public function updateChecklist(Request $request)
    {
      $order_id = $request->order_id ?? '';
      $checklist = $request->check ?? [];

      if (is_string($checklist)) {
        $checklist = json_decode($checklist);
      }

      try {

        DB::connection("sqlSrvBCKI")->beginTransaction();

        DB::connection("sqlSrvBCKI")
          ->table('document_required')
          ->where('order_id', $order_id)
          ->delete();

        $lastId = DB::connection("sqlSrvBCKI")
          ->table('document_required')
          ->max('tr_id') ?? 0;

        $data = [];
        $doc_tr_id = $lastId;

        foreach ($checklist as $item) {

          $doc_tr_id++;

          if($item['value']) {
            $data[] = [
              'tr_id'      => $doc_tr_id,
              'order_id'   => $order_id,
              'job_code'   => $item['code'],
              'date_aprv'  => now('Asia/Jakarta'),
              'dt_created' => now('Asia/Jakarta'),
              'created_by' => getAuth($request)->user_id2,
            ];
          }

        }

        if (!empty($data)) {
          DB::connection("sqlSrvBCKI")
            ->table('document_required')
            ->insert($data);
        }

        DB::connection("sqlSrvBCKI")->commit();

        return response()->json([
          'success' => true,
          'message' => 'Checklist berhasil diperbarui'
        ]);
      } catch (\Exception $e) {

        DB::connection("sqlSrvBCKI")->rollBack();

        return $this->sendError($e->getMessage(), 'Gagal update checklist', 500);
      }
    }

    public function getChecklist(Request $request)
    {
      $order_id = $request->order_id ?? '';
    
      try {
        $result = DB::connection("sqlSrvBCKI")
          ->table('document_required')
          ->where('order_id', $order_id)
          ->orderBy('tr_id')
          ->get();
    
        return $this->sendResponse($result, 'Successfully get data');
      } catch (Throwable $e) {
        return $this->sendError($e->getMessage());
      }
    }
}
