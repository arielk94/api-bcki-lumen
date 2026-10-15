<?php

namespace App\Http\Controllers\master;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class gLedgerMapController extends Controller
{
    public function index(Request $request)
    {
        try {
            $sqlWhere = "1=1";
            if ($request->rep_id) {
                $sqlWhere .= " AND rep_id = '" . $request->rep_id . "'";
            }
            if ($request->cat) {
                $sqlWhere .= " AND cat = '" . $request->cat . "'";
            }

            $data = DB::connection('sqlSrvBCKI')
                ->table('g_ledger_map')
                ->whereRaw($sqlWhere)
                ->orderBy('rep_id', 'ASC')
                ->orderBy('cat_seq', 'ASC')
                ->orderBy('sub_cat_seq', 'ASC')
                ->get();

            return $this->sendResponse($data, 'Successfully retrieved COA mappings');
        } catch (\Exception $e) {
            // return $this->sendError('Internal Error', $e->getMessage(), 500);
            return $this->sendError($e->getMessage(), 'Internal Error', 500);
        }
    }

    // public function store(Request $request)
    // {
    //     try {
    //         $acc_num = trim($request->acc_num);
    //         // if (empty($acc_num)) {
    //         //     return $this->sendError('Validation Error', 'Account Number (acc_num) is required', 400);
    //         // }

    //         // Find all matching active accounts in g_ledger that start with $acc_num
    //         $ledgers = DB::connection('sqlSrvBCKI')
    //             ->table('g_ledger')
    //             ->where('active_flag', 'Y')
    //             ->where('ac_num', 'like', $acc_num . '%')
    //             ->get();

    //         if ($ledgers->isEmpty()) {
    //             $ledgers = collect([
    //                 (object)[
    //                     'ac_num' => $acc_num,
    //                     'ac_name' => '',
    //                     'drcr' => $request->drcr
    //                 ]
    //             ]);
    //         }

    //         $rep_id = trim($request->rep_id);
    //         $cat = trim($request->cat);

    //         // Auto-calculate sequence: use existing sequence for the same category, or increment max sequence
    //         $existing_seq = DB::connection('sqlSrvBCKI')
    //             ->table('g_ledger_map')
    //             ->where('rep_id', $rep_id)
    //             ->where('cat', $cat)
    //             ->whereNotNull('cat_seq')
    //             ->value('cat_seq');

    //         if ($existing_seq !== null) {
    //             $cat_seq = $existing_seq;
    //         } else {
    //             $max_seq = DB::connection('sqlSrvBCKI')
    //                 ->table('g_ledger_map')
    //                 ->where('rep_id', $rep_id)
    //                 ->max('cat_seq');
    //             $cat_seq = $max_seq ? ($max_seq + 1) : 1;
    //         }

    //         foreach ($ledgers as $ledger) {
    //             $curr_acc_num = trim($ledger->ac_num);

    //             // Avoid inserting if this exact mapping (rep_id, cat, sub_cat, acc_num) already exists
    //             $exists = DB::connection('sqlSrvBCKI')
    //                 ->table('g_ledger_map')
    //                 ->where('rep_id', $rep_id)
    //                 ->where('cat', $cat)
    //                 ->where('sub_cat', $request->sub_cat)
    //                 ->where('acc_num', $curr_acc_num)
    //                 ->exists();

    //             if ($exists) {
    //                 continue;
    //             }

    //             $acc_name = $ledger->ac_name ? trim($ledger->ac_name) : '';
    //             $drcr = !empty($ledger->drcr) ? trim($ledger->drcr) : $request->drcr;
    //             $acc_par = substr($curr_acc_num, 0, 2);

    //             DB::connection('sqlSrvBCKI')->table('g_ledger_map')->insert([
    //                 'rep_id' => $rep_id,
    //                 'rep_type' => $request->rep_type,
    //                 'cat' => $cat,
    //                 'cat_seq' => $cat_seq,
    //                 'sub_cat' => $request->sub_cat,
    //                 'acc_par' => $acc_par,
    //                 'acc_num' => $curr_acc_num,
    //                 'acc_name' => $acc_name,
    //                 'drcr' => $drcr,
    //                 'remark' => $request->remark
    //             ]);
    //         }
    //     //ac_num, ac_name, drcr kosong tidak usah foreach
    //         return $this->sendResponse('success', 'COA mapping created successfully');
    //     } catch (\Exception $e) {
    //         // return $this->sendError('Internal Error', $e->getMessage(), 500);
    //         return $this->sendError($e->getMessage(), 'Internal Error', 500);            
    //     }
    // }
    //code store lama

    public function store(Request $request)
    {
        try {
        $acc_num_raw = $request->acc_num;
        $acc_name_raw = $request->acc_name;
        $drcr_raw = $request->drcr;

        if (is_array($acc_num_raw)) {
            $acc_num = trim($acc_num_raw['ac_num'] ?? '');
            $acc_name_input = !empty($acc_name_raw) ? trim($acc_name_raw) : trim($acc_num_raw['ac_name'] ?? '');
            $drcr_input = !empty($drcr_raw) ? trim($drcr_raw) : trim($acc_num_raw['drcr'] ?? '');
        } elseif (is_object($acc_num_raw)) {
            $acc_num = trim($acc_num_raw->ac_num ?? '');
            $acc_name_input = !empty($acc_name_raw) ? trim($acc_name_raw) : trim($acc_num_raw->ac_name ?? '');
            $drcr_input = !empty($drcr_raw) ? trim($drcr_raw) : trim($acc_num_raw->drcr ?? '');
        } else {
            $acc_num = trim($acc_num_raw ?? '');
            $acc_name_input = trim($acc_name_raw ?? '');
            $drcr_input = trim($drcr_raw ?? '');
        }
        
        $rep_id = trim($request->rep_id ?? '');
        $cat = trim($request->cat ?? '');
        $sub_cat = trim($request->sub_cat ?? '');
        
        $show_on_report = ($request->show_on_report === 'N') ? 'N' : 'Y';

        // 1. Get sequence from input or auto-calculate if not provided
        if ($request->filled('cat_seq')) {
            $cat_seq = $request->cat_seq;
        } else {
            $existing_seq = DB::connection('sqlSrvBCKI')
                ->table('g_ledger_map')
                ->where('rep_id', $rep_id)
                ->where('cat', $cat)
                ->whereNotNull('cat_seq')
                ->value('cat_seq');

            if ($existing_seq !== null) {
                $cat_seq = $existing_seq;
            } else {
                $max_seq = DB::connection('sqlSrvBCKI')
                    ->table('g_ledger_map')
                    ->where('rep_id', $rep_id)
                    ->max('cat_seq');
                $cat_seq = $max_seq ? ($max_seq + 1) : 1;
            }
        }

        // Get sub_cat_seq from input or auto-calculate if not provided
        if ($request->filled('sub_cat_seq')) {
            $sub_cat_seq = $request->sub_cat_seq;
        } else {
            $existing_sub_seq = DB::connection('sqlSrvBCKI')
                ->table('g_ledger_map')
                ->where('rep_id', $rep_id)
                ->where('cat', $cat)
                ->where('sub_cat', $sub_cat)
                ->whereNotNull('sub_cat_seq')
                ->value('sub_cat_seq');

            if ($existing_sub_seq !== null) {
                $sub_cat_seq = $existing_sub_seq;
            } else {
                $max_sub_seq = DB::connection('sqlSrvBCKI')
                    ->table('g_ledger_map')
                    ->where('rep_id', $rep_id)
                    ->where('cat', $cat)
                    ->max('sub_cat_seq');
                $sub_cat_seq = $max_sub_seq ? ($max_sub_seq + 1) : 1;
            }
        }

        // 2. CEK KONDISI: Jika ketiga parameter utama kosong
        if (empty($acc_num) && empty($acc_name_input) && empty($drcr_input)) {
            
            // Cek duplikasi untuk data kosong
            $exists = DB::connection('sqlSrvBCKI')
                ->table('g_ledger_map')
                ->where('rep_id', $rep_id)
                ->where('cat', $cat)
                ->where('sub_cat', $sub_cat)
                ->where('acc_num', '')
                ->exists();

            if (!$exists) {
                // Langsung insert satu kali tanpa foreach
                DB::connection('sqlSrvBCKI')->table('g_ledger_map')->insert([
                    'rep_id' => $rep_id,
                    'rep_type' => $request->rep_type ?? '',
                    'cat' => $cat,
                    'cat_seq' => $cat_seq,
                    'sub_cat' => $sub_cat,
                    'sub_cat_seq' => $sub_cat_seq,
                    'acc_par' => '',
                    'acc_num' => '',
                    'acc_name' => '',
                    'drcr' => '',
                    'remark' => $request->remark ?? '',
                    'show_on_report' => $show_on_report
                ]);
            }

        } else {
            // 3. KONDISI JIKA BERISI: Jalankan pencarian ledger dan foreach seperti semula
            $ledgers = DB::connection('sqlSrvBCKI')
                ->table('g_ledger')
                ->where('active_flag', 'Y')
                ->where('ac_num', 'like', $acc_num . '%')
                ->get();

            if ($ledgers->isEmpty()) {
                $ledgers = collect([
                    (object)[
                        'ac_num' => $acc_num,
                        'ac_name' => $acc_name_input,
                        'drcr' => $drcr_input
                    ]
                ]);
            }

            foreach ($ledgers as $ledger) {
                $curr_acc_num = trim($ledger->ac_num);

                // Hindari duplikasi mapping
                $exists = DB::connection('sqlSrvBCKI')
                    ->table('g_ledger_map')
                    ->where('rep_id', $rep_id)
                    ->where('cat', $cat)
                    ->where('sub_cat', $sub_cat)
                    ->where('acc_num', $curr_acc_num)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $acc_name = $ledger->ac_name ? trim($ledger->ac_name) : '';
                $drcr = !empty($ledger->drcr) ? trim($ledger->drcr) : $drcr_input;
                $acc_par = !empty($curr_acc_num) ? substr($curr_acc_num, 0, 2) : '';

                DB::connection('sqlSrvBCKI')->table('g_ledger_map')->insert([
                    'rep_id' => $rep_id,
                    'rep_type' => $request->rep_type ?? '',
                    'cat' => $cat,
                    'cat_seq' => $cat_seq,
                    'sub_cat' => $sub_cat,
                    'sub_cat_seq' => $sub_cat_seq,
                    'acc_par' => $acc_par,
                    'acc_num' => $curr_acc_num,
                    'acc_name' => $acc_name,
                    'drcr' => $drcr,
                    'remark' => $request->remark ?? '',
                    'show_on_report' => $show_on_report
                ]);
            }
        }

        // Propagate show_on_report update to all mappings in the same cat
        DB::connection('sqlSrvBCKI')
            ->table('g_ledger_map')
            ->where('rep_id', $rep_id)
            ->where('cat', $cat)
            ->update(['show_on_report' => $show_on_report]);

        return $this->sendResponse('success', 'COA mapping created successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Internal Error', 500);            
        }
    }
        

    public function update(Request $request)
    {
        try {
            $show_on_report = ($request->show_on_report === 'N') ? 'N' : 'Y';

            $acc_num_raw = $request->acc_num;
            if (is_array($acc_num_raw)) {
                $acc_num = trim($acc_num_raw['ac_num'] ?? '');
            } elseif (is_object($acc_num_raw)) {
                $acc_num = trim($acc_num_raw->ac_num ?? '');
            } else {
                $acc_num = trim($acc_num_raw ?? '');
            }

            if (empty($acc_num)) {
                return $this->sendError('Validation Error', 'Account Number (acc_num) is required', 400);
            }

            // Trim request variables to sanitize input strings
            $rep_id = trim($request->rep_id ?? '');
            $rep_type = trim($request->rep_type ?? '');
            $cat = trim($request->cat ?? '');
            $sub_cat = trim($request->sub_cat ?? '');
            $remark = trim($request->remark ?? '');

            // Fetch acc_name and drcr from g_ledger
            $ledger = DB::connection('sqlSrvBCKI')
                ->table('g_ledger')
                ->where('ac_num', $acc_num)
                ->first();

            $acc_name = $ledger ? trim($ledger->ac_name) : '';
            $drcr = $ledger ? trim($ledger->drcr) : trim($request->drcr ?? '');
            $acc_par = substr($acc_num, 0, 2);

            $query = DB::connection('sqlSrvBCKI')->table('g_ledger_map');

            $original_acc_num_raw = $request->original_acc_num;
            if (is_array($original_acc_num_raw)) {
                $original_acc_num = trim($original_acc_num_raw['ac_num'] ?? '');
            } elseif (is_object($original_acc_num_raw)) {
                $original_acc_num = trim($original_acc_num_raw->ac_num ?? '');
            } else {
                $original_acc_num = trim($original_acc_num_raw ?? '');
            }

            if ($request->filled('original_acc_num') || !empty($original_acc_num)) {
                $original_rep_id = trim($request->original_rep_id ?? '');
                $original_rep_type = trim($request->original_rep_type ?? '');
                $original_cat = trim($request->original_cat ?? '');
                $original_sub_cat = trim($request->original_sub_cat ?? '');

                $query->where([
                    ['rep_id', '=', $original_rep_id],
                    ['rep_type', '=', $original_rep_type],
                    ['cat', '=', $original_cat],
                    ['sub_cat', '=', $original_sub_cat],
                    ['acc_num', '=', $original_acc_num]
                ]);
            } else {
                $id = $request->id;
                if (empty($id)) {
                    return $this->sendError('Validation Error', 'ID or composite keys are required for update', 400);
                }
                $query->where('id', $id);
            }

            // Get old record before update to retrieve old cat_seq, rep_id, and cat
            $old_record = (clone $query)->first();
            if ($old_record && $request->filled('cat_seq')) {
                $old_cat_seq = $old_record->cat_seq;
                $new_cat_seq = $request->cat_seq;
                $old_rep_id = trim($old_record->rep_id);
                $old_cat = trim($old_record->cat);

                if ($old_cat_seq != $new_cat_seq) {
                    DB::connection('sqlSrvBCKI')
                        ->table('g_ledger_map')
                        ->where('rep_id', $old_rep_id)
                        ->where('cat', $old_cat)
                        ->where('cat_seq', $old_cat_seq)
                        ->update(['cat_seq' => $new_cat_seq]);
                }
            }

            if ($old_record && $request->filled('sub_cat_seq')) {
                $old_sub_cat_seq = $old_record->sub_cat_seq;
                $new_sub_cat_seq = $request->sub_cat_seq;
                $old_rep_id = trim($old_record->rep_id);
                $old_cat = trim($old_record->cat);
                $old_sub_cat = trim($old_record->sub_cat);

                if ($old_sub_cat_seq != $new_sub_cat_seq) {
                    DB::connection('sqlSrvBCKI')
                        ->table('g_ledger_map')
                        ->where('rep_id', $old_rep_id)
                        ->where('cat', $old_cat)
                        ->where('sub_cat', $old_sub_cat)
                        ->update(['sub_cat_seq' => $new_sub_cat_seq]);
                }
            }

            $query->update([
                'rep_id' => $rep_id,
                'rep_type' => $rep_type,
                'cat' => $cat,
                'cat_seq' => $request->cat_seq,
                'sub_cat' => $sub_cat,
                'sub_cat_seq' => $request->sub_cat_seq,
                'acc_par' => $acc_par,
                'acc_num' => $acc_num,
                'acc_name' => $acc_name,
                'drcr' => $drcr,
                'remark' => $remark,
                'show_on_report' => $show_on_report
            ]);

            // Also map child accounts of the updated $acc_num
            $ledgers = DB::connection('sqlSrvBCKI')
                ->table('g_ledger')
                ->where('active_flag', 'Y')
                ->where('ac_num', 'like', $acc_num . '%')
                ->get();

            $rep_id = trim($request->rep_id);
            $cat = trim($request->cat);
            $sub_cat = trim($request->sub_cat);

            // Use the parent's cat_seq if available, otherwise check existing or calculate
            if ($request->filled('cat_seq')) {
                $child_cat_seq = $request->cat_seq;
            } else {
                $existing_seq = DB::connection('sqlSrvBCKI')
                    ->table('g_ledger_map')
                    ->where('rep_id', $rep_id)
                    ->where('cat', $cat)
                    ->whereNotNull('cat_seq')
                    ->value('cat_seq');

                if ($existing_seq !== null) {
                    $child_cat_seq = $existing_seq;
                } else {
                    $max_seq = DB::connection('sqlSrvBCKI')
                        ->table('g_ledger_map')
                        ->where('rep_id', $rep_id)
                        ->max('cat_seq');
                    $child_cat_seq = $max_seq ? ($max_seq + 1) : 1;
                }
            }

            // Use the parent's sub_cat_seq if available, otherwise check existing or calculate
            if ($request->filled('sub_cat_seq')) {
                $child_sub_cat_seq = $request->sub_cat_seq;
            } else {
                $existing_sub_seq = DB::connection('sqlSrvBCKI')
                    ->table('g_ledger_map')
                    ->where('rep_id', $rep_id)
                    ->where('cat', $cat)
                    ->where('sub_cat', $sub_cat)
                    ->whereNotNull('sub_cat_seq')
                    ->value('sub_cat_seq');

                if ($existing_sub_seq !== null) {
                    $child_sub_cat_seq = $existing_sub_seq;
                } else {
                    $max_sub_seq = DB::connection('sqlSrvBCKI')
                        ->table('g_ledger_map')
                        ->where('rep_id', $rep_id)
                        ->where('cat', $cat)
                        ->max('sub_cat_seq');
                    $child_sub_cat_seq = $max_sub_seq ? ($max_sub_seq + 1) : 1;
                }
            }

            foreach ($ledgers as $ledger) {
                $curr_acc_num = trim($ledger->ac_num);

                // Skip the exact acc_num that was just updated
                if ($curr_acc_num === $acc_num) {
                    continue;
                }

                // Check if this exact mapping (rep_id, cat, sub_cat, acc_num) already exists
                $exists = DB::connection('sqlSrvBCKI')
                    ->table('g_ledger_map')
                    ->where('rep_id', $rep_id)
                    ->where('cat', $cat)
                    ->where('sub_cat', $sub_cat)
                    ->where('acc_num', $curr_acc_num)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $child_acc_name = $ledger->ac_name ? trim($ledger->ac_name) : '';
                $child_drcr = !empty($ledger->drcr) ? trim($ledger->drcr) : $request->drcr;
                $child_acc_par = substr($curr_acc_num, 0, 2);

                DB::connection('sqlSrvBCKI')->table('g_ledger_map')->insert([
                    'rep_id' => $rep_id,
                    'rep_type' => $request->rep_type,
                    'cat' => $cat,
                    'cat_seq' => $child_cat_seq,
                    'sub_cat' => $sub_cat,
                    'sub_cat_seq' => $child_sub_cat_seq,
                    'acc_par' => $child_acc_par,
                    'acc_num' => $curr_acc_num,
                    'acc_name' => $child_acc_name,
                    'drcr' => $child_drcr,
                    'remark' => $request->remark,
                    'show_on_report' => $show_on_report
                ]);
            }

            // Propagate show_on_report update to all mappings in the same cat
            DB::connection('sqlSrvBCKI')
                ->table('g_ledger_map')
                ->where('rep_id', $rep_id)
                ->where('cat', $cat)
                ->update(['show_on_report' => $show_on_report]);

            return $this->sendResponse(null, 'COA mapping updated successfully');
        } catch (\Exception $e) {
            // return $this->sendError('Internal Error', $e->getMessage(), 500);
            return $this->sendError($e->getMessage(), 'Internal Error', 500);
        }
    }

    // public function update(Request $request)
    // {
    //     try {
    //         $acc_num = trim($request->acc_num ?? '');

    //     // 1. Ambil data ledger untuk data utama jika acc_num tidak kosong
    //     $ledger = null;
    //     if (!empty($acc_num)) {
    //         $ledger = DB::connection('sqlSrvBCKI')
    //             ->table('g_ledger')
    //             ->where('ac_num', $acc_num)
    //             ->first();
    //     }

    //     $acc_name = $ledger ? trim($ledger->ac_name) : '';
    //     $drcr = $ledger ? trim($ledger->drcr) : ($request->drcr ?? '');
    //     $acc_par = !empty($acc_num) ? substr($acc_num, 0, 2) : '';

    //     // 2. Siapkan query untuk Update Data Utama
    //     $query = DB::connection('sqlSrvBCKI')->table('g_ledger_map');

    //     if ($request->filled('original_acc_num')) {
    //         $query->where([
    //             ['rep_id', '=', $request->original_rep_id ?? ''],
    //             ['rep_type', '=', $request->original_rep_type ?? ''],
    //             ['cat', '=', $request->original_cat ?? ''],
    //             ['sub_cat', '=', $request->original_sub_cat ?? ''],
    //             ['acc_num', '=', $request->original_acc_num ?? '']
    //         ]);
    //     } else {
    //         $id = $request->id;
    //         if (empty($id)) {
    //             return $this->sendError('Validation Error', 'ID or composite keys are required for update', 400);
    //         }
    //         $query->where('id', $id);
    //     }

    //     // Jalankan Update Utama
    //     $query->update([
    //         'rep_id' => $request->rep_id,
    //         'rep_type' => $request->rep_type ?? '',
    //         'cat' => $request->cat,
    //         'cat_seq' => $request->cat_seq,
    //         'sub_cat' => $request->sub_cat,
    //         'acc_par' => $acc_par,
    //         'acc_num' => $acc_num,
    //         'acc_name' => $acc_name,
    //         'drcr' => $drcr,
    //         'remark' => $request->remark ?? ''
    //     ]);

    //     // 3. CEK KONDISI: Jika acc_num kosong, langsung bypass proses child account & foreach
    //     if (!empty($acc_num)) {
            
    //         // Fetch child accounts of the updated $acc_num
    //         $ledgers = DB::connection('sqlSrvBCKI')
    //             ->table('g_ledger')
    //             ->where('active_flag', 'Y')
    //             ->where('ac_num', 'like', $acc_num . '%')
    //             ->get();

    //         $rep_id = trim($request->rep_id ?? '');
    //         $cat = trim($request->cat ?? '');

    //         // Hitung atau ambil cat_seq untuk child
    //         if ($request->filled('cat_seq')) {
    //             $child_cat_seq = $request->cat_seq;
    //         } else {
    //             $existing_seq = DB::connection('sqlSrvBCKI')
    //                 ->table('g_ledger_map')
    //                 ->where('rep_id', $rep_id)
    //                 ->where('cat', $cat)
    //                 ->whereNotNull('cat_seq')
    //                 ->value('cat_seq');

    //             if ($existing_seq !== null) {
    //                 $child_cat_seq = $existing_seq;
    //             } else {
    //                 $max_seq = DB::connection('sqlSrvBCKI')
    //                     ->table('g_ledger_map')
    //                     ->where('rep_id', $rep_id)
    //                     ->max('cat_seq');
    //                 $child_cat_seq = $max_seq ? ($max_seq + 1) : 1;
    //             }
    //         }

    //         // Jalankan foreach hanya jika acc_num tidak kosong dan child ditemukan
    //         foreach ($ledgers as $ledger) {
    //             $curr_acc_num = trim($ledger->ac_num);

    //             // Skip account utama yang baru saja di-update di atas
    //             if ($curr_acc_num === $acc_num) {
    //                 continue;
    //             }

    //             // Cek duplikasi mapping child
    //             $exists = DB::connection('sqlSrvBCKI')
    //                 ->table('g_ledger_map')
    //                 ->where('rep_id', $rep_id)
    //                 ->where('cat', $cat)
    //                 ->where('sub_cat', $request->sub_cat)
    //                 ->where('acc_num', $curr_acc_num)
    //                 ->exists();

    //             if ($exists) {
    //                 continue;
    //             }

    //             $child_acc_name = $ledger->ac_name ? trim($ledger->ac_name) : '';
    //             $child_drcr = !empty($ledger->drcr) ? trim($ledger->drcr) : ($request->drcr ?? '');
    //             $child_acc_par = substr($curr_acc_num, 0, 2);

    //             DB::connection('sqlSrvBCKI')->table('g_ledger_map')->insert([
    //                 'rep_id' => $rep_id,
    //                 'rep_type' => $request->rep_type ?? '',
    //                 'cat' => $cat,
    //                 'cat_seq' => $child_cat_seq,
    //                 'sub_cat' => $request->sub_cat,
    //                 'acc_par' => $child_acc_par,
    //                 'acc_num' => $curr_acc_num,
    //                 'acc_name' => $child_acc_name,
    //                 'drcr' => $child_drcr,
    //                 'remark' => $request->remark ?? ''
    //             ]);
    //         }
    //     }

    //         return $this->sendResponse(null, 'COA mapping updated successfully');
    //     } catch (\Exception $e) {
    //         // return $this->sendError('Internal Error', $e->getMessage(), 500);
    //         return $this->sendError($e->getMessage(), 'Internal Error', 500);
    //     }
    // }

    public function delete(Request $request)
    {
        try {
            $query = DB::connection('sqlSrvBCKI')->table('g_ledger_map');

            $acc_num_raw = $request->acc_num;
            if (is_array($acc_num_raw)) {
                $acc_num = trim($acc_num_raw['ac_num'] ?? '');
            } elseif (is_object($acc_num_raw)) {
                $acc_num = trim($acc_num_raw->ac_num ?? '');
            } else {
                $acc_num = trim($acc_num_raw ?? '');
            }

            if ($request->filled('acc_num') || !empty($acc_num)) {
                $query->where([
                    ['rep_id', '=', $request->rep_id ?? ''],
                    ['rep_type', '=', $request->rep_type ?? ''],
                    ['cat', '=', $request->cat ?? ''],
                    ['sub_cat', '=', $request->sub_cat ?? ''],
                    ['acc_num', '=', $acc_num]
                ]);
            } else {
                // $id = $request->id;
                // if (empty($id)) {
                //     return $this->sendError('Validation Error', 'ID or composite keys are required for delete', 400);
                // }
                // $query->where('id', $id);
                $query->where([
                    ['rep_id', '=', $request->rep_id ?? ''],
                    // ['rep_type', '=', $request->rep_type ?? ''],
                    ['acc_num', '=', ''],
                    ['acc_par', '=', ''],
                    ['acc_name', '=', ''],
                    ['cat', '=', $request->cat ?? ''],
                    ['sub_cat', '=', $request->sub_cat ?? '']
                ]);
            }

            $query->delete();

            return $this->sendResponse(null, 'COA mapping deleted successfully');
        } catch (\Exception $e) {
            // return $this->sendError('Internal Error', $e->getMessage(), 500);
            return $this->sendError($e->getMessage(), 'Internal Error', 500);
        }
    }

    // public function getCOA(Request $request)
    // {
    //     try {
    //         $data = DB::connection('sqlSrvBCKI')
    //             ->table('g_ledger')
    //             ->selectRaw("TRIM(ac_num) as ac_num, TRIM(ac_name) as ac_name, TRIM(drcr) as drcr")
    //             ->where('active_flag', 'Y')
    //             ->where('ac_num', '<>', ' ')
    //             ->orderBy('ac_num', 'ASC')
    //             ->get();

    //         return $this->sendResponse($data, 'Successfully retrieved active COA list');
    //     } catch (\Exception $e) {
    //         return $this->sendError('Internal Error', $e->getMessage(), 500);
    //     }
    // }

    public function getDistinctRep(Request $request)
    {
        try {
            $data = DB::connection('sqlSrvBCKI')
                ->table('g_ledger_map')
                ->selectRaw("DISTINCT TRIM(rep_id) as rep_id")
                ->whereNotNull('rep_id')
                ->where('rep_id', '<>', '')
                ->orderBy('rep_id', 'ASC')
                ->pluck('rep_id');

            return $this->sendResponse($data, 'Successfully retrieved distinct Report IDs');
        } catch (\Exception $e) {
            // return $this->sendError('Internal Error', $e->getMessage(), 500);
            return $this->sendError($e->getMessage(), 'Internal Error', 500);
        }
    }
}