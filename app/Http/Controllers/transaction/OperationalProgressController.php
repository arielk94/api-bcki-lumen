<?php

namespace App\Http\Controllers\transaction;

use App\Http\Controllers\Controller;
use App\Traits\getFilterTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OperationalProgressController extends Controller
{
    use getFilterTrait;

    public function index()
    {
        // dd("test");
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
            ->where('key_code', 'OPL_PROG')
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
            ]
        ]);
    }

    public function getData(Request $request)
    {
        $province_id = $request->province_id ?? '';
        $city_id = $request->city_id ?? '';
        $site_id = $request->site_id ?? '';
        $cluster_id = $request->cluster_id ?? '';
        $house_id = $request->house_id ?? '';

        // return $this->sendResponse([], 'Successfully get Payment Progress');

        try {
            // $datas = DB::connection("sqlSrvBCKI")
            //     ->table('v_operational_progress as pp')
            //     ->selectRaw('
            //         pp.*,
            //         CASE WHEN op.presentase IS NULL THEN 0 ELSE op.presentase END as progress
            //     ')
            //     ->join('site as si', 'pp.id_site', '=', 'si.id_site')
            //     ->join('city_list as cl', 'si.city', '=', 'cl.city_code')
            //     ->join('province_list as pl', 'cl.province_code', '=', 'pl.province_code')
            //     ->leftjoin('operational_progress as op', function ($join) {
            //         $join->on('pp.order_id', '=', 'op.order_id')
            //             ->where('op.job_code', '=', `APRV9`);
            //     })
            //     ->where('pp.status', 'O');
            $datas = DB::connection("sqlSrvBCKI")
              ->table('v_operational_progress as pp')
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

            if ($cluster_id) {
                $datas->where('pp.id_cluster', $cluster_id);
            }

            if ($house_id) {
                $datas->where('pp.id_house', $house_id);
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
                    $item->dt_aprv10 = $item->dt_aprv10 ? date('Y-m-d', strtotime($item->dt_aprv10)) : '1900-01-01';
                    $item->order_date = $item->order_date ? date('Y-m-d', strtotime($item->order_date)) : '1900-01-01';

                    return $item;
                });

            return $this->sendResponse($datas, 'Successfully get Operational Progress');
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
        $progress = $request->progress ?? '';
        $date_aprv = $request->date_aprv ?? '';
        $bank_name = $request->bank_name ?? '';
        $no_dokumen = $request->no_dokumen ?? '';
        $bank_va = $request->bank_va ?? '';
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
                    $path = 'fpis/operational_progress';

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
                        'dt_modified' => now(),
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
            "no_dokumen" => $no_dokumen,
            "bank_va" => $bank_va,
            "dt_created" => now(),
            "created_by" => getAuth($request)->user_id2,
        ];

        // --  ANALISA KREDIT
        if ($approve_type == 'APRV03') {
            $dataSave['bank_name'] = $bank_name;
        }

        // -- PUTUSAN
        if ($approve_type == 'APRV04') {
            $dataSave['id_putusan'] = $putusan_id;
            $dataSave['bank_name'] = $bank_name;
        }

        if($approve_type == 'APRV09') {
            $dataSave['presentase'] = $progress;
        }

        try {

          if($approve_type == 'APRV09') {
            DB::connection("sqlSrvBCKI")
                ->table('operational_progress')
                ->updateOrInsert([
                  'order_id' => $order_id
                ],
                $dataSave);
          }else{
            // -- approve data            
            DB::connection("sqlSrvBCKI")
                ->table('operational_progress')
                ->insert($dataSave);
          }

          // -- file attachments
          if ($file_attachments) {
              DB::connection("sqlSrvBCKI")
                  ->table('operational_progress_doc')
                  ->insert($file_attachments);
          }

          return $this->sendResponse('Success', 'Successfully Approve');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function storeProgress(Request $request)
    {
        $order_id = $request->order_id ?? '';
        $progress = $request->progress ?? '';

        $resp = DB::connection("sqlSrvBCKI")
            ->table('operational_progress')
            ->select('tr_id')
            ->where('order_id', $order_id)
            ->where('job_code', 'APRV09')
            ->first();

        if (!$resp) {
          // -- tr id
          $trId = $this->genTrId();
  
          $dataSave = [
              "tr_id" => $trId,
              "order_id" => $order_id,
              "job_code" => 'APRV09',
              "presentase" => $progress,
              "dt_created" => now(),
              "created_by" => getAuth($request)->user_id2,
          ];
        }else{
          $dataSave = [
              "presentase" => $progress,
              "dt_created" => now(),
              "created_by" => getAuth($request)->user_id2,
          ];
        }

        try {
          DB::connection("sqlSrvBCKI")
              ->table('operational_progress')
              ->updateOrInsert([
                'order_id' => $order_id,
                'job_code' => 'APRV09'
              ],
              $dataSave);

          return $this->sendResponse('Success', 'Successfully Approve');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function generateQcTrans(Request $request)
    {
        $order_id = $request->order_id ?? '';
        $id_house = $request->id_house ?? '';

        $userLogin = getAuth($request)->user_id2 ?? 'SYSTEM';

        $defaultDate = '1900-01-01 00:00:00.000';
        $now = now();

        try {

            // ======================
            // GET QC FORM
            // ======================
            $form = DB::connection('sqlSrvBCKI')
                ->table('qc_form')
                ->where('qc_type', 'KK')
                ->where('active_flag', 'Y')
                ->first();

            if (!$form) {
                return $this->sendError('QC Form tidak ditemukan', 'Error', 404);
            }

            // ======================
            // CEK / INSERT HEADER
            // ======================
            $existingHdr = DB::connection('sqlSrvBCKI')
                ->table('qc_trans_hdr')
                ->where('id_qc_form', $form->id_qc_form)
                ->where('id_house', $id_house)
                ->first();

            if (!$existingHdr) {

                $qc_trans_id = $this->generateQcTransId();

                DB::connection('sqlSrvBCKI')
                    ->table('qc_trans_hdr')
                    ->insert([
                        'qc_trans_id' => $qc_trans_id,
                        'id_qc_form' => $form->id_qc_form,
                        'id_house' => $id_house,
                        'dt_created' => $now,
                        'created_by' => $userLogin,
                        'dt_target' => $defaultDate,
                        'dt_rencana' => $defaultDate,
                        'dt_finish' => $defaultDate,
                        'doc_type' => 'KK',
                        'doc_id' => $order_id,
                        'dt_modified' => $now,
                        'modified_by' => $userLogin,
                        'active_flag' => 'Y'
                    ]);

            } else {
                $qc_trans_id = $existingHdr->qc_trans_id;
            }

            // ======================
            // AMBIL QC ITEM
            // ======================
            // dd($form->id_qc_form);
            $qcItems = DB::connection('sqlSrvBCKI')
                ->table('qc_item')
                ->where('id_qc_form', $form->id_qc_form)
                ->get();
            // dd($qcItems);

            if ($qcItems->isEmpty()) {
                return $this->sendResponse([], 'Tidak ada QC Item');
            }

            // mapping qc_item → array
            $qcItemIds = [];
            foreach ($qcItems as $item) {
                $qcItemIds[] = (string) $item->id_qc_item;
            }

            // ======================
            // AMBIL EXISTING DETAIL
            // ======================
            $existingDtl = DB::connection('sqlSrvBCKI')
                ->table('qc_trans_dtl')
                ->where('qc_trans_id', $qc_trans_id)
                ->get();

            // mapping existing dtl → array
            $existingDtlIds = [];
            foreach ($existingDtl as $dtl) {
                $existingDtlIds[] = (string) $dtl->id_qc_item;
            }

            // ======================
            // FILTER ITEM BARU
            // ======================
            $newItems = array_diff($qcItemIds, $existingDtlIds);

            // ======================
            // PREPARE BULK INSERT
            // ======================
            $insertData = [];

            foreach ($newItems as $id_qc_item) {
                $insertData[] = [
                    'qc_trans_id' => $qc_trans_id,
                    'id_qc_item' => $id_qc_item,
                    'stat' => 'O',
                    'dt_created' => $now,
                    'created_by' => $userLogin,
                    'dt_aprv' => $defaultDate,
                    'aprv_by' => '',
                    'dt_aprv2' => $defaultDate,
                    'aprv2_by' => '',
                    'remark' => '',
                    'img_link' => '',
                    'stat_closing' => 'N',
                    'stat_closing2' => 'N',
                    'stat_closing3' => 'N',
                    'dt_aprv3' => $defaultDate,
                    'aprv3_by' => '',
                    'img_link2' => '',
                    'img_link3' => '',
                    'remark2' => '',
                    'remark3' => '',
                    'longitude1' => '',
                    'latitude1' => '',
                    'longitude2' => '',
                    'latitude2' => '',
                    'longitude3' => '',
                    'latitude3' => '',
                ];
            }

            // ======================
            // BULK INSERT
            // ======================
            if (!empty($insertData)) {
                DB::connection('sqlSrvBCKI')
                    ->table('qc_trans_dtl')
                    ->insert($insertData);
            }

            return $this->sendResponse([], 'QC Trans & Detail synced (stable version)');

        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function generateQcTransId()
    {
        $dateYear2digit = date("y");
        $dateMonth = date("m");
        $dateDay = date("d");

        $prefix = $dateYear2digit . $dateMonth . $dateDay;

        $getLastIdQC = DB::connection('sqlSrvBCKI')
            ->table('qc_trans_hdr')
            ->where('qc_trans_id', 'like', "%$prefix%")
            ->max('qc_trans_id');

        if ($getLastIdQC) {
            $lastThree = substr($getLastIdQC, -3);
            $next_id = (int) $lastThree + 1;
            $seq = str_pad($next_id, 3, '0', STR_PAD_LEFT);
        } else {
            $seq = '001';
        }

        return 'T' . $prefix . $seq;
    }

    

    public function getApproveData(Request $request)
    {
        $order_id = $request->order_id ?? '';
        $approve_type = $request->approve_type;
        $tr_id = $request->tr_id;

        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('operational_progress_doc')
                ->where('doc_type', $order_id)
                ->where('aprv_type', $approve_type)
                ->when($tr_id, function ($query, $tr_id) {
                    return $query->where('tr_id', $tr_id);
                })
                ->selectRaw("
                        doc_name,
                        n_file as doc_url,
                        loc_file
                    ")
                ->orderBy('seq_num')
                ->get()
                ->map(function ($item) {
                    return [
                        'doc_name' => $item->doc_name,
                        'doc_url' => url("/storage/{$item->loc_file}/{$item->doc_url}"),
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
                ->table('operational_progress as pp')
                ->join('appl_constant as ack', function ($q) {
                    $q->on('pp.job_code', '=', 'ack.str1')
                        ->where('ack.key_code', 'OPL_PROG');
                })
                // ->leftJoin('appl_constant as acp', function ($q) {
                //     $q->on('pp.id_putusan', '=', 'acp.str1')
                //         ->where('acp.key_code', 'PUTUSAN_AK');
                // })
                ->where('order_id', $order_id)
                ->selectRaw("
                    ack.str1 as code,    
                    ack.str2 as code_desc,
                    pp.tr_id,
                    pp.order_id,
                    pp.bank_name,
                    pp.id_putusan,
                    pp.no_dokumen,
                    pp.bank_va,
                    --acp.str2 as putusan,
                    pp.remark_putusan as remark,
                    pp.date_aprv
                ")
                ->orderBy('tr_id')
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
        $datePrefix = now()->format('ymd'); // YYMMDD
        $lastRecord = DB::connection("sqlSrvBCKI")
            ->table('operational_progress')
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
                ->table('operational_progress'); // Fokus langsung pada tabel yang akan dihapus

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
                ->table('operational_progress_doc')
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
                ->table('operational_progress_doc')
                ->where('doc_type', $order_id)
                ->where('tr_id', $tr_id)
                ->where('aprv_type', $code)
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
}
