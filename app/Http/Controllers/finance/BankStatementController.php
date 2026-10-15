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

class BankStatementController extends Controller
{
    public function getDataMaster(Request $request)
    {
        $type = $request->type;

        try {
            $connection = DB::connection("sqlSrvBCKI");

            switch ($type) {
                case 'customer':
                    $result = $connection->table('customer')
                        ->selectRaw("TRIM(customerid) AS customerid, TRIM(namacustomer) AS namacustomer, trim(alamat_id_card)as Alamat")
                        ->get();
                    $message = 'Successfully retrieved customer data';
                    break;
                case 'vendor':
                    $result = $connection->table('vendor')
                        ->selectRaw("TRIM(vendor_id) AS vendor_id, TRIM(vendor_name) AS vendor_name, trim(address1)+' '+trim(address2)+', ' +trim(city) as Alamat")
                        ->get();
                    $message = 'Successfully retrieved vendor data';
                    break;
                case 'bank_master':
                    $result = $connection->table('account')
                        ->selectRaw("DISTINCT trim(bank_name) as bank_name")
                        ->whereIn('a_type', ['B', 'R'])
                        ->where('posting', 'Y')
                        ->get();
                    $message = 'Successfully retrieved bank master data';
                    break;

                case 'bank_account':
                    $bankId = $request->bank_id;
                    if (empty($bankId)) {
                        return $this->sendError("Empty Bank ID", 'Error', 500);
                    }

                    $result = $connection->table('account as a')
                        ->selectRaw("trim(a.bank_acc) as bank_acc, trim(b.ac_name) as description")
                        ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
                        ->where('bank_name', $bankId)
                        ->get();
                    $message = 'Successfully retrieved bank account data';
                    break;
                default:
                    return $this->sendError("Invalid type parameter", 'Error', 500);
            }

            return $this->sendResponse($result, $message);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    function getBankAccMap($BankAcc)
    {
        $accMap = DB::connection("sqlSrvBCKI")
            ->table('account')
            ->selectRaw('acc_map')
            ->where('bank_acc', '=', $BankAcc)
            ->first();
        return ($accMap->acc_map ?? '00');
    }

    function generateNoBuktiTransferNew($sType, $sBankAc, $sAcMap, $sTgl)
    {
        $tglYYMMDD = date('ymd', strtotime($sTgl));

        $row = DB::connection("sqlSrvBCKI")
            ->table('bank_note')
            ->select(DB::raw('MAX(tr_id) as tid'))
            ->where('tr_type', trim($sType))
            ->where('bank_acc', trim($sBankAc))
            ->whereDate('dt_trans', $sTgl)
            ->first();
        $lNumber = 1;

        if ($row && $row->tid) {
            $lastNumber = (int)substr(trim($row->tid), -3);
            $lNumber = $lastNumber + 1;
        }

        $prefix = $sType === 'C' ? 'M' : ($sType === 'D' ? 'K' : '');

        $noBukti = $prefix . $tglYYMMDD . str_pad($sAcMap, 2, '0', STR_PAD_LEFT) . str_pad($lNumber, 3, '0', STR_PAD_LEFT);

        return $noBukti;
    }
    function GenNoBukti($sType, $sBankAc,  $sTgl)
    {
        $acMap = $this->getBankAccMap($sBankAc);
        $TrID = $this->generateNoBuktiTransferNew($sType, $sBankAc, $acMap, $sTgl);
        return $TrID;
    }
    function GenNoBuktiSplit($sType, $sBankAc,  $sTgl)
    {
        $acMap = $this->getBankAccMap($sBankAc);
        $TrID = $this->generateNoBuktiTransferNew($sType, $sBankAc, $acMap, $sTgl);
        $TrID = 'S' . str::substr(trim($TrID), -11);
        //dd($TrID);
        return $TrID;
    }
    public function getBankStatement(Request $request)
    {
        $sqlWhere = "1=1 ";

        $bank_acc = $request->bank_acc;
        $if_Posting = $request->if_posting;
        $tr_date_from = $request->tr_date_from;
        $tr_date_to = $request->tr_date_to;
        $tr_type = $request->tr_type;

        if (!$tr_date_to) {
            return $this->sendError("Empty transaction date", 'Error', 500);
        }
        if (empty($bank_acc)) {
            return $this->sendError("Empty bank account", 'Error', 500);
        }

        if (!empty($bank_acc)) {
            $sqlWhere = $sqlWhere . " and bn.bank_acc = " . "'" . $bank_acc . "'";
        }
        if ($tr_date_from) {
            $sqlWhere =  $sqlWhere . " and bn.dt_trans  >= CONVERT(DATE, '" . $tr_date_from . "')";
        }
        if ($tr_date_to) {
            $sqlWhere = $sqlWhere . " and bn.dt_trans  <= CONVERT(DATE, '" . $tr_date_to . "')";
        }
        //type bank other payment D, deposit C, split S, kas bon K
        if (!empty($tr_type)) {
            //$sqlWhere = $sqlWhere . " and bn.tr_type = " . "'" . $tr_type . "'";
            $sqlWhere .= " AND bn.tr_type IN ('$tr_type','S')";
        }
        if (!empty($if_Posting)) {
            if (strtoupper($if_Posting) == 'Y') {
                $sqlWhere = $sqlWhere . " and bn.voucher >0";
            } else {
                $sqlWhere = $sqlWhere . " and bn.voucher = 0";
            }
        }
        //dd($sqlWhere);
        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('bank_note as bn')
                ->selectRaw("
                        trim(bn.Descr) Descr,trim(bn.cabang) cabang,bn.db_amt,bn.cr_amt,bn.saldo,trim(bn.tr_id) tr_id,bn.voucher,bn.run_number,bn.dt_trans,
                        trim(bn.tr_type) tr_type,trim(bn.trx_mode) trx_mode,trim(bn.tr_type) tr_type,
                        trim(ac.bank_name) bank_name,trim(isnull(br.branch_descr,'')) as cabang1,trim(isnull(br.city,'')) city,trim(ac.ac_num) ac_num,
                        case when substring(bn.link_id,1,1)='K' or substring(bn.link_id,1,1)='M' then trim(bn.link_id) else bn.tr_id end mother_id,
                        case when trim(bn.link_id)='SPLIT CUST' then 'MOTHER' 
                        when  substring(bn.link_id,1,1)='K' or substring(bn.link_id,1,1)='M' then 'CHILD' else 'MOTHER' end as mother_child,
                        case when trim(bn.link_id) <> ''  then 'Y' else 'N' end split_status
                    ")
                ->join('account as ac', 'bn.bank_acc', '=', 'ac.bank_acc')
                ->leftjoin('bank_branch as br', 'br.bank_branch_code', '=', 'bn.cabang')
                ->whereRaw($sqlWhere)
                ->orderBy('bn.run_number', 'desc')
                ->get();
            // if ($result->isEmpty()) {
            //     return $this->sendError("Bank statement data not found", 'Data Not Found', 500);
            // }
            return $this->sendResponse($result, 'Successfully get bank statement data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function StoreBank(Request $request)
    {
        $result = '';
        $data = $request->all();

        $userId = getAuth($request)->user_id2 ?? null;
        $trId = $this->GenNoBukti($data['type'], $data['bank_acc'], $data['tr_date']);

        try {
            DB::connection('sqlSrvBCKI')->transaction(function () use ($data, $userId, $trId) {
                DB::connection('sqlSrvBCKI')->table('bank_note')->insert([
                    'tr_type' => $data['type'],
                    'bank_acc' => $data['bank_acc'],
                    'tr_id' => $trId,
                    'dt_trans' => $data['tr_date'],
                    'descr' => $data['transaction'],
                    'rate' => '1',
                    'curr_id' => 'IDR',
                    'db_amt' => $data['type'] === 'D'  ? $data['nominal'] : 0,
                    'cr_amt' => $data['type'] === 'C'  ? $data['nominal'] : 0,
                    'remark' => $data['remark'],
                    'customer' => $data['type'] === 'C'  ? $data['customer_id'] : $data['vendor_id'],
                    'doc_id' => 'B' . $trId,
                    'dt_modified' => now(),
                    'user_id' => $userId
                ]);
            });


            return $this->sendResponse($result, 'Transaction created successfully.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function uploadFile(Request $request)
    {
        $sukses = '';
        $userId = getAuth($request)->user_id2 ?? null;
        $bankAcc = $request->bank_acc;
        if (!$request->hasFile('csv_file')) {
            return $this->sendError('No file selected.', 'Error', 500);
        }
        if (empty($bankAcc)) {
            return $this->sendError('Empty account number', 'Error', 500);
        }

        $file = $request->file('csv_file');
        $path = $file->getRealPath();
        //try {
        //dd(!$bankAcc);
        if (strtoupper($request->bank_name) === 'BCA') {
            $this->uploadBcaFile($path, $bankAcc, $userId);
        } else if (strtoupper(trim($request->bank_name)) === 'DANAMON') {
            $this->uploadDanamonFile($path, $bankAcc, $userId);
        } else if (strtoupper(trim($request->bank_name)) === 'MANDIRI') {
            $this->uploadMandiriFile($path, $bankAcc, $userId);
        } else if (strtoupper(trim($request->bank_name)) === 'BRI') {
            $this->uploadBriFile($path, $bankAcc, $userId);
        } else if (strtoupper(trim($request->bank_name)) === 'BNI') {
            $this->uploadBniFile($path, $bankAcc, $userId);
        } else if (strtoupper(trim($request->bank_name)) === 'BTN') {
            $this->uploadBtnFile($path, $bankAcc, $userId);
        } else if (strtoupper(trim($request->bank_name)) === 'PERMATA') {
            $this->uploadPermataFile($path, $bankAcc, $userId);
        } else {
            return $this->sendResponse($sukses, 'Unsupport bank');
        }

        return $this->sendResponse($sukses, 'CSV uploaded successfully.');
        //} catch (\Exception $e) {
        //    return $this->sendError($e->getMessage(), 'Error', 500);
        // }
    }

    function uploadBcaFile($csvFilePath, $bankAcc, $userId)
    {
        $sukses = '';
        if (!file_exists($csvFilePath)) {
            return $this->sendError('File not found.', 'Error', 500);
        }
        if (empty($bankAcc)) {
            return $this->sendError('Empty account number', 'Error', 500);
        }
        $data = array_map('str_getcsv', file($csvFilePath));

        if (count($data) < 2) {
            return $this->sendError('CSV file is empty or only has headers.', 'Error', 500);
        }
        // Ambil header dari baris keenam
        $header = array_map('trim', $data[6]);

        $records = [];
        //$arrayCoba = [];
        for ($i = 7; $i < count($data); $i++) {

            if (count($data[$i]) !== count($header)) continue;
            $row = array_combine($header, $data[$i]);
            $records[] = $row;
            if (trim($records[$i - 7]['Tanggal Transaksi']) != 'PEND' && $this->isValidDate(trim($records[$i - 7]['Tanggal Transaksi']))) {

                $tanggalString = str_replace("-", " ", $records[$i - 7]['Tanggal Transaksi']) . ' ' . date('Y'); // contoh: "2 Jul 2025"
                // $arrayCoba[] = $tanggalString;
                // continue;

                $tanggal = Carbon::createFromFormat('j M Y', $tanggalString);
                //dd($tanggal);
                $tr_date = $tanggal->toDateString(); // Hasil: 2025-07-02

                //dd($tr_date);
                $type = Str::substr(trim($records[$i - 7]['Jumlah']), -2) === 'CR'  ? "C" : "D";
                $toNumber = str_replace(",", "", $records[$i - 7]['Jumlah']);
                $saldo = (float)str_replace(",", "", $records[$i - 7]['Saldo']);
                preg_match('/\d+/', $toNumber, $matches);
                $nominal = isset($matches[0]) ? (float) $matches[0] : 0;
                //dd($nominal);
                $remarkClean = str_replace("'", "", $records[$i - 7]['Keterangan']);
                $trId = $this->GenNoBukti($type, $bankAcc, $tr_date);
                $result = DB::connection("sqlSrvBCKI")
                    ->table('bank_note')
                    ->selectRaw("run_number")
                    ->where('descr', '=', trim($remarkClean))
                    ->where('dt_trans', '=', $tr_date)
                    ->where('bank_acc', '=', $bankAcc)
                    ->where('cabang', '=', $records[$i - 7]['Cabang'])
                    ->where('saldo', '=', $saldo)
                    ->orderBy('run_number', 'desc')
                    ->first();
                //dd($result, $remarkClean, $tr_date, $bankAcc, $records[$i - 7]['Cabang'], $records[$i - 7]['Saldo']);
                if ($result) {
                    //dd('update');
                    DB::connection('sqlSrvBCKI')->table('bank_note')
                        ->where('run_number', $result->run_number)
                        ->update([
                            'tr_type' => $type,
                            'bank_acc' => $bankAcc,
                            'dt_trans' => $tr_date,
                            'descr' => $remarkClean,
                            'rate' => '1',
                            'curr_id' => 'IDR',
                            'db_amt' => $type === 'D'  ? $nominal : 0,
                            'cr_amt' => $type === 'C'  ? $nominal : 0,
                            'cabang' => $records[$i - 7]['Cabang'],
                            'saldo' => $saldo,
                            'remark' =>  $remarkClean,
                            'doc_id' => 'B' . $trId,
                            'dt_modified' => now(),
                            'user_id' => $userId
                        ]);
                } else {
                    DB::connection('sqlSrvBCKI')->table('bank_note')->insert([
                        'tr_type' => $type,
                        'bank_acc' => $bankAcc,
                        'tr_id' => $trId,
                        'dt_trans' => $tr_date,
                        'descr' => $remarkClean,
                        'rate' => '1',
                        'curr_id' => 'IDR',
                        'db_amt' => $type === 'D'  ? $nominal : 0,
                        'cr_amt' => $type === 'C'  ? $nominal : 0,
                        'cabang' => $records[$i - 7]['Cabang'],
                        'saldo' => $saldo,
                        'remark' =>  $remarkClean,
                        'doc_id' => 'B' . $trId,
                        'dt_modified' => now(),
                        'user_id' => $userId
                    ]);
                }
            }
        }
        //dd($arrayCoba);

        return $this->sendResponse($sukses, 'CSV uploaded successfully.');
    }
    function uploadDanamonFile($csvFilePath, $bankAcc, $userId)
    {
        $sukses = '';
        if (!file_exists($csvFilePath)) {
            return $this->sendError('CSV file is empty or only has headers.', 'Error', 500);
        }
        if (empty($bankAcc)) {
            return $this->sendError('Empty bank account number.', 'Error', 500);
        }
        $data = array_map('str_getcsv', file($csvFilePath));

        if (count($data) < 2) {
            return $this->sendError('CSV file is empty or only has headers.', 'Error', 500);
        }
        // Ambil header dari baris keenam
        $header = array_map('trim', $data[0]);

        $records = [];
        $pengurang = 1;
        for ($i = 1; $i < count($data); $i++) {
            if (count($data[$i]) !== count($header)) continue;
            $row = array_combine($header, $data[$i]);
            $records[] = $row;
            //if (trim($records[$i - $pengurang]['Tanggal Transaksi']) != 'PEND') {
            $tanggalString = str_replace(" ", "-", $records[$i - $pengurang]['Posting Date']); //. ' ' . date('Y'); // contoh: "02-Jul-25"
            $tanggal = Carbon::createFromFormat('d-M-y', $tanggalString);
            $tr_date = $tanggal->format('Y-m-d'); // Hasil: 2025-07-02
            $type = (float)($records[$i - $pengurang]['Debit']) > 0  ? "D" : "C";
            //$toNumber = str_replace(",", "", $records[$i - $pengurang]['Jumlah']);
            $saldo = (float)$records[$i - $pengurang]['Balance'];
            //dd($nominal);
            $remarkClean = str_replace("'", "", $records[$i - $pengurang]['Description']);
            $trId = $this->GenNoBukti($type, $bankAcc, $tr_date);
            $result = DB::connection("sqlSrvBCKI")
                ->table('bank_note')
                ->selectRaw("run_number")
                ->where('descr', '=', trim($remarkClean))
                ->where('dt_trans', '=', $tr_date)
                ->where('bank_acc', '=', $bankAcc)
                ->where('cabang', '=', $records[$i - $pengurang]['Transaction Branch'])
                ->where('saldo', '=', $saldo)
                ->orderBy('run_number', 'desc')
                ->first();
            //dd($result, $remarkClean, $tr_date, $bankAcc, $records[$i - 7]['Cabang'], $records[$i - 7]['Saldo']);
            if ($result) {
                DB::connection('sqlSrvBCKI')->table('bank_note')
                    ->where('run_number', $result->run_number)
                    ->update([
                        'tr_type' => $type,
                        'bank_acc' => $bankAcc,
                        'dt_trans' => $tr_date,
                        'descr' => $remarkClean,
                        'rate' => '1',
                        'curr_id' => 'IDR',
                        'db_amt' => (float)($records[$i - $pengurang]['Debit']),
                        'cr_amt' => (float)($records[$i - $pengurang]['Credit']),
                        'cabang' => $records[$i - $pengurang]['Transaction Branch'],
                        'saldo' => $saldo,
                        'remark' =>  $remarkClean,
                        'doc_id' => 'B' . $trId,
                        'dt_modified' => now(),
                        'user_id' => $userId
                    ]);
            } else {
                DB::connection('sqlSrvBCKI')->table('bank_note')->insert([
                    'tr_type' => $type,
                    'bank_acc' => $bankAcc,
                    'tr_id' => $trId,
                    'dt_trans' => $tr_date,
                    'descr' => $remarkClean,
                    'rate' => '1',
                    'curr_id' => 'IDR',
                    'db_amt' => (float)($records[$i - $pengurang]['Debit']),
                    'cr_amt' => (float)($records[$i - $pengurang]['Credit']),
                    'cabang' => $records[$i - $pengurang]['Transaction Branch'],
                    'saldo' => $saldo,
                    'remark' =>  $remarkClean,
                    'doc_id' => 'B' . $trId,
                    'dt_modified' => now(),
                    'user_id' => $userId
                ]);
            }
            //}
        }

        return $this->sendResponse($sukses, 'CSV uploaded successfully.');
    }
    function uploadBniFile($csvFilePath, $bankAcc, $userId)
    {
        $sukses = '';
        if (!file_exists($csvFilePath)) {
            return $this->sendError('File not found.', 'Error', 500);
        }
        if (empty($bankAcc)) {
            return $this->sendError('Empty bank account number.', 'Error', 500);
        }
        $data = array_map('str_getcsv', file($csvFilePath));

        if (count($data) < 2) {
            return $this->sendError('CSV file is empty or only has headers.', 'Error', 500);
        }
        // Ambil header dari baris keenam
        $header = array_map('trim', $data[0]);

        $records = [];
        $pengurang = 1;
        for ($i = 1; $i < count($data); $i++) {
            if (count($data[$i]) !== count($header)) continue;
            $row = array_combine($header, $data[$i]);
            $records[] = $row;
            //if (trim($records[$i - $pengurang]['Tanggal Transaksi']) != 'PEND') {
            $tanggalString = str_replace("/", "-", Str::substr($records[$i - $pengurang]['Post Date'], 0, 8)); //. ' ' . date('Y'); // contoh: "02-Jul-25"
            $tanggal = Carbon::createFromFormat('d-m-y', trim($tanggalString));
            $tr_date = $tanggal->format('Y-m-d'); // Hasil: 2025-07-02
            $type = (float)(str_replace(',', '', $records[$i - $pengurang]['Debit'])) > 0  ? "D" : "C";
            //$toNumber = (float)(str_replace(',', '', $records[$i - $pengurang]['Debit']));
            //DD($toNumber);
            $saldo = 0; //(float)$records[$i - $pengurang]['Balance'];
            //dd($nominal);
            //dd($records);
            $remarkClean = str_replace("'", "", $records[$i - $pengurang]['Description']);
            $trId = $this->GenNoBukti($type, $bankAcc, $tr_date);
            $result = DB::connection("sqlSrvBCKI")
                ->table('bank_note')
                ->selectRaw("run_number")
                ->where('descr', '=', trim($remarkClean))
                ->where('dt_trans', '=', $tr_date)
                ->where('bank_acc', '=', $bankAcc)
                ->where('cabang', '=', $records[$i - $pengurang]['Branch'])
                ->where('doc_id', '=', $records[$i - $pengurang]['Journal No.'])
                ->orderBy('run_number', 'desc')
                ->first();
            //dd($result, $remarkClean, $tr_date, $bankAcc, $records[$i - 7]['Cabang'], $records[$i - 7]['Saldo']);
            if ($result) {
                DB::connection('sqlSrvBCKI')->table('bank_note')
                    ->where('run_number', $result->run_number)
                    ->update([
                        'tr_type' => $type,
                        'bank_acc' => $bankAcc,
                        'dt_trans' => $tr_date,
                        'descr' => $remarkClean,
                        'rate' => '1',
                        'curr_id' => 'IDR',
                        'db_amt' => (float)(str_replace(',', '', $records[$i - $pengurang]['Debit'])),
                        'cr_amt' => (float)(str_replace(',', '', $records[$i - $pengurang]['Credit'])),
                        'cabang' => $records[$i - $pengurang]['Branch'],
                        'saldo' => $saldo,
                        'remark' =>  $remarkClean,
                        'doc_id' => $records[$i - $pengurang]['Journal No.'],
                        'dt_modified' => now(),
                        'user_id' => $userId
                    ]);
            } else {
                DB::connection('sqlSrvBCKI')->table('bank_note')->insert([
                    'tr_type' => $type,
                    'bank_acc' => $bankAcc,
                    'tr_id' => $trId,
                    'dt_trans' => $tr_date,
                    'descr' => $remarkClean,
                    'rate' => '1',
                    'curr_id' => 'IDR',
                    'db_amt' => (float)(str_replace(',', '', $records[$i - $pengurang]['Debit'])),
                    'cr_amt' => (float)(str_replace(',', '', $records[$i - $pengurang]['Credit'])),
                    'cabang' => $records[$i - $pengurang]['Branch'],
                    'saldo' => $saldo,
                    'remark' =>  $remarkClean,
                    'doc_id' => 'B' . $trId,
                    'dt_modified' => now(),
                    'user_id' => $userId
                ]);
            }
            //}
        }

        return $this->sendResponse($sukses, 'CSV uploaded successfully.');
    }

    function uploadPermataFile($csvFilePath, $bankAcc, $userId)
    {
        $sukses = '';
        if (!file_exists($csvFilePath)) {
            return $this->sendError('File not found.', 'Error', 500);
        }
        if (empty($bankAcc)) {
            return $this->sendError('Empty bank account number.', 'Error', 500);
        }
        $data = array_map('str_getcsv', file($csvFilePath));

        if (count($data) < 2) {
            return $this->sendError('CSV file is empty or only has headers.', 'Error', 500);
        }
        foreach ($data as $key => $datas) {
            if ($key >= 10) {
                if (Str::substr((strtoupper(trim($datas[0]))), 0, 7) != "CLOSING") {
                    $tanggalString = str_replace(" ", "-", $datas[0]); // contoh: "28-May-2025"
                    $tanggal = Carbon::createFromFormat('d-M-Y', trim($tanggalString));
                    $tr_date = $tanggal->format('Y-m-d'); // Hasil: 2025-05-02
                    $toNumber = (float)(str_replace(',', '', $datas[7]));
                    $type = $toNumber > 0  ? "C" : "D";
                    $remarkClean = str_replace("'", "", $datas[6]);
                    $trId = $this->GenNoBukti($type, $bankAcc, $tr_date);
                    $result = DB::connection("sqlSrvBCKI")
                        ->table('bank_note')
                        ->selectRaw("run_number")
                        ->where('descr', '=', trim($remarkClean))
                        ->where('dt_trans', '=', $tr_date)
                        ->where('bank_acc', '=', $bankAcc)
                        ->where('doc_id', '=', $datas[5])
                        ->orderBy('run_number', 'desc')
                        ->first();
                    if ($result) {
                        DB::connection('sqlSrvBCKI')->table('bank_note')
                            ->where('run_number', $result->run_number)
                            ->update([
                                'tr_type' => $type,
                                'bank_acc' => $bankAcc,
                                'dt_trans' => $tr_date,
                                'descr' => $remarkClean,
                                'rate' => '1',
                                'curr_id' => 'IDR',
                                'db_amt' => $toNumber < 0 ?  $toNumber * -1 : 0,
                                'cr_amt' => $toNumber >= 0 ? $toNumber : 0,
                                'remark' =>  $remarkClean,
                                'doc_id' => $datas[5],
                                'dt_modified' => now(),
                                'user_id' => $userId
                            ]);
                    } else {
                        DB::connection('sqlSrvBCKI')->table('bank_note')->insert([
                            'tr_type' => $type,
                            'bank_acc' => $bankAcc,
                            'tr_id' => $trId,
                            'dt_trans' => $tr_date,
                            'descr' => $remarkClean,
                            'rate' => '1',
                            'curr_id' => 'IDR',
                            'db_amt' => $toNumber < 0 ?  $toNumber * -1 : 0,
                            'cr_amt' => $toNumber >= 0 ? $toNumber : 0,
                            'remark' =>  $remarkClean,
                            'doc_id' => $datas[5],
                            'dt_modified' => now(),
                            'user_id' => $userId
                        ]);
                    }
                }
            }
        }


        return $this->sendResponse($sukses, 'CSV uploaded successfully.');
    }
    function uploadBtnFile($csvFilePath, $bankAcc, $userId)
    {
        $sukses = '';
        if (!file_exists($csvFilePath)) {
            return $this->sendError('File not found.', 'Error', 500);
        }
        if (empty($bankAcc)) {
            return $this->sendError('Empty bank account number.', 'Error', 500);
        }
        $data = array_map('str_getcsv', file($csvFilePath));
        $tahun = Str::substr(trim($data[7][0]), -4);
        if (count($data) < 2) {
            return $this->sendError('CSV file is empty or only has headers.', 'Error', 500);
        }
        foreach ($data as $key => $datas) {
            if ($key >= 4) {
                //dd($datas[0], $datas[1], $this->isValidDate2($datas[1]));
                if (is_numeric($datas[0]) && $this->isValidDate2($datas[1])) {
                    $tanggalString = str_replace(" ", "-", $datas[1]); //. '-' . $tahun; // contoh: "28-May-2025"

                    $tanggal = Carbon::createFromFormat('d/m/Y', $tanggalString);
                    //dd($tanggalString, $datas[1]);
                    $tr_date = $tanggal->format('Y/m/d'); // Hasil: 2025-05-02
                    $toNumber = (float)($datas[6]);
                    $type = $toNumber > 0  ? "D" : "C";
                    $remarkClean = str_replace("'", "", $datas[5]);
                    $trId = $this->GenNoBukti($type, $bankAcc, $tr_date);

                    $result = DB::connection("sqlSrvBCKI")
                        ->table('bank_note')
                        ->selectRaw("run_number")
                        ->where('descr', '=', trim($remarkClean))
                        ->where('dt_trans', '=', $tr_date)
                        ->where('bank_acc', '=', $bankAcc)
                        ->where('saldo', '=', (float)($datas[8]))
                        ->orderBy('run_number', 'desc')
                        ->first();
                    if ($result) {
                        DB::connection('sqlSrvBCKI')->table('bank_note')
                            ->where('run_number', $result->run_number)
                            ->update([
                                'tr_type' => $type,
                                'bank_acc' => $bankAcc,
                                'dt_trans' => $tr_date,
                                'descr' => $remarkClean,
                                'rate' => '1',
                                'curr_id' => 'IDR',
                                'db_amt' => (float)($datas[6]),
                                'cr_amt' => (float)($datas[7]),
                                'saldo' => (float)($datas[8]),
                                'remark' =>  $remarkClean,
                                'dt_modified' => now(),
                                'user_id' => $userId
                            ]);
                    } else {
                        DB::connection('sqlSrvBCKI')->table('bank_note')->insert([
                            'tr_type' => $type,
                            'bank_acc' => $bankAcc,
                            'tr_id' => $trId,
                            'dt_trans' => $tr_date,
                            'descr' => $remarkClean,
                            'rate' => '1',
                            'curr_id' => 'IDR',
                            'db_amt' => (float)($datas[6]),
                            'cr_amt' => (float)($datas[7]),
                            'remark' =>  $remarkClean,
                            'saldo' => (float)($datas[8]),
                            'dt_modified' => now(),
                            'user_id' => $userId
                        ]);
                    }
                }
            }
        }
        return $this->sendResponse($sukses, 'CSV uploaded successfully.');
    }

    function uploadMandiriFile($csvFilePath, $bankAcc, $userId)
    {
        $sukses = '';
        if (!file_exists($csvFilePath)) {
            return $this->sendError('File not found.', 'Error', 500);
        }
        if (empty($bankAcc)) {
            return $this->sendError('Empty bank account number.', 'Error', 500);
        }
        $data = array_map('str_getcsv', file($csvFilePath));

        if (count($data) < 2) {
            return $this->sendError('CSV file is empty or only has headers.', 'Error', 500);
        }
        // Ambil header dari baris keenam
        $header = array_map('trim', $data[0]);
        //dd($data);
        $records = [];
        $pengurang = 1;
        for ($i = 1; $i < count($data); $i++) {
            if (count($data[$i]) !== count($header)) continue;
            $row = array_combine($header, $data[$i]);
            $records[] = $row;
            //if (trim($records[$i - $pengurang]['Tanggal Transaksi']) != 'PEND') {
            $tanggalString = str_replace("/", "-",  Str::substr(trim($records[$i - $pengurang]['PostDate']), 0, 10)); //. ' ' . date('Y'); // contoh: "02-Jul-25"
            $tanggal = Carbon::createFromFormat('d-m-Y', $tanggalString);
            $tr_date = $tanggal->format('Y-m-d'); // Hasil: 2025-07-02
            $type = (float)($records[$i - $pengurang]['Credit Amount']) > 0  ? "C" : "D";
            //$toNumber = str_replace(",", "", $records[$i - $pengurang]['Jumlah']);
            $saldo = (float)$records[$i - $pengurang]['Close Balance'];
            //dd($nominal);
            $remarkClean = str_replace("'", "", $records[$i - $pengurang]['Remarks']);
            $trId = $this->GenNoBukti($type, $bankAcc, $tr_date);
            $result = DB::connection("sqlSrvBCKI")
                ->table('bank_note')
                ->selectRaw("run_number")
                ->where('descr', '=', trim($remarkClean))
                ->where('dt_trans', '=', $tr_date)
                ->where('bank_acc', '=', $bankAcc)
                ->where('saldo', '=', $saldo)
                ->orderBy('run_number', 'desc')
                ->first();
            //dd($result, $remarkClean, $tr_date, $bankAcc, $records[$i - 7]['Cabang'], $records[$i - 7]['Saldo']);
            if ($result) {
                DB::connection('sqlSrvBCKI')->table('bank_note')
                    ->where('run_number', $result->run_number)
                    ->update([
                        'tr_type' => $type,
                        'bank_acc' => $bankAcc,
                        'dt_trans' => $tr_date,
                        'descr' => $remarkClean,
                        'rate' => '1',
                        'curr_id' => 'IDR',
                        'db_amt' => (float)($records[$i - $pengurang]['Debit Amount']),
                        'cr_amt' => (float)($records[$i - $pengurang]['Credit Amount']),
                        'saldo' => $saldo,
                        'remark' =>  $remarkClean,
                        'doc_id' => 'B' . $trId,
                        'dt_modified' => now(),
                        'user_id' => $userId
                    ]);
            } else {
                DB::connection('sqlSrvBCKI')->table('bank_note')->insert([
                    'tr_type' => $type,
                    'bank_acc' => $bankAcc,
                    'tr_id' => $trId,
                    'dt_trans' => $tr_date,
                    'descr' => $remarkClean,
                    'rate' => '1',
                    'curr_id' => 'IDR',
                    'db_amt' => (float)($records[$i - $pengurang]['Debit Amount']),
                    'cr_amt' => (float)($records[$i - $pengurang]['Credit Amount']),
                    'saldo' => $saldo,
                    'remark' =>  $remarkClean,
                    'doc_id' => 'B' . $trId,
                    'dt_modified' => now(),
                    'user_id' => $userId
                ]);
            }
            //}
        }

        return $this->sendResponse($sukses, 'CSV uploaded successfully.');
    }
    function uploadBriFile($csvFilePath, $bankAcc, $userId)
    {
        $sukses = '';
        if (!file_exists($csvFilePath)) {
            return $this->sendError('File not found.', 'Error', 500);
        }
        if (empty($bankAcc)) {
            return $this->sendError('Empty bank account number.', 'Error', 500);
        }
        $data = array_map('str_getcsv', file($csvFilePath));
        //dd($data);
        if (count($data) < 2) {
            return $this->sendError('CSV file is empty or only has headers.', 'Error', 500);
        }
        // Ambil header dari baris keenam
        $header = array_map('trim', $data[0]);
        //dd($data);
        $records = [];
        $pengurang = 1;

        for ($i = 1; $i < count($data); $i++) {
            if (count($data[$i]) !== count($header)) continue;
            $row = array_combine($header, $data[$i]);
            $records[] = $row;
            //if (trim($records[$i - $pengurang]['Tanggal Transaksi']) != 'PEND') {
            $tanggalString = str_replace("-", " ", Str::substr($records[$i - $pengurang]['TGL_TRAN'], 0, 10)); //. ' ' . date('Y'); // contoh: "2 Jul 2025"
            //dd($tanggalString);
            $tanggal = Carbon::createFromFormat('d/m/Y', $tanggalString);
            //dd($tanggal);

            $tr_date = $tanggal->toDateString(); // Hasil: 2025-07-02
            $type = (float)($records[$i - $pengurang]['MUTASI_KREDIT']) > 0  ? "C" : "D";
            //$toNumber = str_replace(",", "", $records[$i - $pengurang]['Jumlah']);
            $saldo = (float)$records[$i - $pengurang]['SALDO_AKHIR_MUTASI'];
            //dd($nominal);
            $remarkClean = str_replace("'", "", $records[$i - $pengurang]['DESK_TRAN']);
            $trId = $this->GenNoBukti($type, $bankAcc, $tr_date);
            $result = DB::connection("sqlSrvBCKI")
                ->table('bank_note')
                ->selectRaw("run_number")
                ->where('descr', '=', trim($remarkClean))
                ->where('dt_trans', '=', $tr_date)
                ->where('bank_acc', '=', $bankAcc)
                ->where('saldo', '=', $saldo)
                ->orderBy('run_number', 'desc')
                ->first();
            //dd($result, $remarkClean, $tr_date, $bankAcc, $records[$i - 7]['Cabang'], $records[$i - 7]['Saldo']);
            if ($result) {
                DB::connection('sqlSrvBCKI')->table('bank_note')
                    ->where('run_number', $result->run_number)
                    ->update([
                        'tr_type' => $type,
                        'bank_acc' => $bankAcc,
                        'dt_trans' => $tr_date,
                        'descr' => $remarkClean,
                        'rate' => '1',
                        'curr_id' => 'IDR',
                        'db_amt' => (float)($records[$i - $pengurang]['MUTASI_DEBET']),
                        'cr_amt' => (float)($records[$i - $pengurang]['MUTASI_KREDIT']),
                        'saldo' => $saldo,
                        'remark' =>  $remarkClean,
                        'doc_id' => 'B' . $trId,
                        'dt_modified' => now(),
                        'user_id' => $userId
                    ]);
            } else {
                DB::connection('sqlSrvBCKI')->table('bank_note')->insert([
                    'tr_type' => $type,
                    'bank_acc' => $bankAcc,
                    'tr_id' => $trId,
                    'dt_trans' => $tr_date,
                    'descr' => $remarkClean,
                    'rate' => '1',
                    'curr_id' => 'IDR',
                    'db_amt' => (float)($records[$i - $pengurang]['MUTASI_DEBET']),
                    'cr_amt' => (float)($records[$i - $pengurang]['MUTASI_KREDIT']),
                    'saldo' => $saldo,
                    'remark' =>  $remarkClean,
                    'doc_id' => 'B' . $trId,
                    'dt_modified' => now(),
                    'user_id' => $userId
                ]);
            }
            //}
        }

        return $this->sendResponse($sukses, 'CSV uploaded successfully.');
    }
    public function SplitBank(Request $request)
    {
        $data = $request->all();
        if ($this->ifPosting($data['tr_id'], "C") || $this->ifPosting($data['tr_id'], "S")) {
            return $this->sendError('Bank ' . $data['tr_id'] . ' already posted, cannot be splited', 'Error', 500);
        }
        // if ($this->ifChild($data['tr_id'])) {
        //     return $this->sendError('Splitted bank cannot be splited', 'Error', 500);
        // }
        DB::connection('sqlSrvBCKI')->table('bank_note')
            ->where('link_id', $data['tr_id'])
            ->delete();
        $result = DB::connection("sqlSrvBCKI")
            ->table('bank_note')
            ->selectRaw("run_number,bank_acc,tr_type,dt_trans")
            ->where('tr_id', '=', trim($data['tr_id']))
            ->first();
        $bank_acc = $result->bank_acc;
        $tr_type = $result->tr_type;
        $tr_date = $result->dt_trans;
        $userId = getAuth($request)->user_id2 ?? null;
        $trId = $this->GenNoBuktiSplit('S', $bank_acc, $tr_date);
        try {
            DB::connection('sqlSrvBCKI')->transaction(function () use ($data, $userId, $bank_acc, $tr_type, $tr_date, $trId) {
                foreach ($data['detail'] as $key => $detail) {
                    $trIdSeq = (float)(Str::substr($trId, -3)) + 1;
                    $trIdNew = Str::substr($trId, 0, 9) . str_pad($trIdSeq, 3, '0', STR_PAD_LEFT);
                    $trId = $trIdNew;
                    DB::connection('sqlSrvBCKI')->table('bank_note')->insert([
                        'tr_type' => 'S',
                        'bank_acc' => $bank_acc,
                        'tr_id' => $trIdNew,
                        'dt_trans' => $tr_date,
                        'descr' =>  empty($detail['vendor_id'])  ? 'SPLIT BANK ' . $data['tr_id']  .  ' to Customer ' . $detail['customer_id'] : 'SPLIT BANK ' . $data['tr_id']  .  ' to Vendor ' . $detail['vendor_id'],
                        'rate' => '1',
                        'curr_id' => 'IDR',
                        'db_amt' => $tr_type === 'D'  ? $detail['split_amt'] : 0,
                        'cr_amt' => $tr_type === 'C'  ? $detail['split_amt'] : 0,
                        'remark' => empty($detail['vendor_id'])  ? 'SPLIT BANK ' . $data['tr_id']  .  ' to Customer ' . $detail['customer_id'] : 'SPLIT BANK ' . $data['tr_id']  .  ' to Vendor ' . $detail['vendor_id'],
                        'customer' => empty($detail['vendor_id'])  ? $detail['customer_id'] : $detail['vendor_id'],
                        'doc_id' =>  $trIdNew,
                        'dt_modified' => now(),
                        'link_id' => $data['tr_id'],
                        'giro_num' => $detail['giro_num'] ? $detail['giro_num'] : '',
                        'kb_id' => $detail['kb_id'] ? $detail['kb_id'] : '',
                        'user_id' => $userId
                    ]);
                }
                DB::connection('sqlSrvBCKI')->table('bank_note')
                    ->where('tr_id', trim($data['tr_id']))
                    ->update([
                        'dt_confirm2' =>  $tr_date,
                        'voucher' => 1,
                        'link_id' => 'SPLIT CUST',
                        'dt_modified' => now(),
                        'user_id' => $userId
                    ]);
            });
            return $this->sendResponse(null, 'Bank statement splitted successfully.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function displaySplitBank(Request $request)
    {
        $sqlWhere = "1=1 ";

        $tr_id = $request->tr_id;

        if (empty($tr_id)) {
            return $this->sendError("Empty tr id", 'Error', 500);
        }

        if (!empty($tr_id)) {
            $sqlWhere = $sqlWhere . " and bn.link_id = " . "'" . $tr_id . "'";
        }
        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('bank_note as bn')
                ->selectRaw("customer,
                case when tr_type='C' then trim(namacustomer) else  trim(vendor_name) end as descr,
                case when tr_type='C' then cr_amt else db_amt end as amt,voucher
                    ")
                ->join('account as ac', 'bn.bank_acc', '=', 'ac.bank_acc')
                ->leftjoin('customer as cu', 'bn.customer', '=', 'cu.customerid')
                ->leftjoin('vendor as ve', 'bn.customer', '=', 've.vendor_id')
                ->leftjoin('bank_branch as br', 'br.bank_branch_code', '=', 'bn.cabang')
                ->whereRaw($sqlWhere)
                ->orderBy('bn.run_number', 'desc')
                ->get();
            return $this->sendResponse($result, 'Successfully get bank statement data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    function ifPosting($trId, $type)
    {
        if ($type == "S") {
            return DB::connection("sqlSrvBCKI")
                ->table('bank_note')
                ->where('link_id', trim($trId))
                ->where('voucher', '>', 0)
                ->exists();
        } else {
            return DB::connection("sqlSrvBCKI")
                ->table('bank_note')
                ->where('tr_id', trim($trId))
                ->where('voucher', '>', 0)
                ->where('link_id', '<>', 'SPLIT CUST')
                ->exists();
        }
    }

    function isValidDate($date)
    {
        try {
            Carbon::parse($date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    function isValidDate2($date)
    {
        try {
            Carbon::createFromFormat('d/m/Y', $date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    function ifChild($trId)
    {
        $result = DB::connection("sqlSrvBCKI")
            ->table('bank_note')
            ->selectRaw("tr_id")
            ->where('tr_id', '=', trim($trId))
            ->where('link_id', '<>', '')
            ->first();
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    public function GetListKasbon(Request $request)
    {
        $vendor_id = $request->vendor_id;
        $kb_amt = $request->kb_amt;
        if (!$vendor_id) {
            return $this->sendError("Empty vendor", 'Error', 500);
        }
        try {
            $sqlWhere = " stat='O' and tr_type='EX' ";
            $result = DB::connection("sqlSrvBCKI")
                ->table('kb_hdr')
                ->selectRaw("
                    trim(tr_id) tr_id,trim(remark) remark, kb_amt, vendor_id, stat, tr_type
                ")
                ->where('vendor_id', '=', $vendor_id)
                ->where('kb_amt', '=', $kb_amt)
                ->where('stat', '=', 'O')
                ->where('tr_type', '=', 'EX')
                ->where('dt_aprv4', '!=', '1900-01-01 00:00:00.000')
                ->whereRaw($sqlWhere)
                ->orderBy('tr_id', 'desc')
                ->get();
            return $this->sendResponse($result, 'Successfully get purchase deposit data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
}
