<?php

namespace App\Http\Controllers\finance;

use App\Services\AccountingService;
use App\Http\Controllers\Controller;
use App\Traits\getFilterTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class OtherJournalController extends Controller
{
    protected $serviceAcc;

    public function __construct(AccountingService $serviceAcc)
    {
        $this->serviceAcc = $serviceAcc;
    }
    public function getDataById(Request $request)
    {
        $tr_id = $request->tr_id;
        $office_id = $request->office_id;
        if (empty($office_id)) {
            return $this->sendError("Empty office ID", 'Error', 500);
        }
        if (empty($tr_id)) {
            return $this->sendError("Empty transaction id", 'Error', 500);
        }
        try {
            $connection = DB::connection("sqlSrvFPI");
            $result = $connection->table('jv_trans_hdr as a')
                ->selectRaw("trim(a.tr_id) as tr_id,a.dt_jv,trim(a.descr) as keterangan,trim(a.paid_to_txt) as paid_to_txt
                ,b.item_num,trim(b.ac_num) as ac_num, trim(c.ac_name) as ac_name,b.amt_dr,b.amt_cr,trim(b.item_memo) as item_memo,trim(b.tr_id_ref) as tr_id_ref")
                ->join('jv_trans_dtl as b', function ($join) {
                    $join->on('b.office_id', '=', 'a.office_id')
                        ->on('b.tr_id', '=', 'a.tr_id');
                })
                ->join('g_ledger as c', 'b.ac_num', '=', 'c.ac_num')
                ->where('a.tr_id', $tr_id)
                ->where('a.office_id', $office_id)
                ->orderBy('b.item_num')
                ->get();
            $message = 'Successfully retrieved  data';
            return $this->sendResponse($result, $message);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function storeOtherJurnal(Request $request)
    {
        $result = '';
        $data = $request->all();
        $userId = getAuth($request)->user_id2 ?? null;
        if ($this->serviceAcc->ifAcctClosed($data['tanggal'], $request)) {
            return $this->sendResponse(null, 'This periode already closed, cannot be modified');
        }
        if (empty($data['tr_id'])) {
            $trId = $this->generateTrId($data['tanggal'],$data['tr_type']);
        } else {
            $trId = $data['tr_id'];
        }
        try {
            DB::connection('sqlSrvFPI')->transaction(function () use ($data, $userId, $trId) {
                DB::connection('sqlSrvFPI')->table('jv_trans_hdr')->insert([
                    'office_id' => 1,
                    'tr_id' => $trId,
                    'dt_jv' => $data['tanggal'],
                    'descr' => $data['memo'],
                    'paid_to_txt' => empty($data['pay_to'])  ? '' : $data['pay_to'], // $data['paid_to'],
                    'rate' => '1',
                    'curr_id' => 'IDR',
                    'tr_num' => (int) substr($trId, -3),
                    'site_id' => $data['site_id'] ?? '',
                    'cluster_id' => $data['cluster_id'] ?? '',
                    'house_id' => $data['house_item_id'] ?? '',
                    'dt_created' => now(),
                    'dt_modified' => now(),
                    'tr_type' =>$data['tr_type'],
                    'user_id' => $userId
                ]);
                foreach ($data['details'] as $key => $detail) {

                    DB::connection('sqlSrvFPI')->table('jv_trans_dtl')->insert([
                        'office_id' => 1,
                        'tr_id' => $trId,
                        'ac_num' => $detail['account'],
                        'amt_dr' => (float)  $detail['debit'],
                        'amt_cr' => (float)  $detail['credit'],
                        'item_memo' => $detail['memo'],
                        'tr_id_ref' => empty($detail['bank_statment_id'])  ? '' : $detail['bank_statment_id'], //$detail['no_spr'] ?? '',
                        'dt_created' => now(),
                        'dt_modified' => now(),
                        'user_id' => $userId,
                        'item_num' => $key + 1,
                    ]);

                    if (!empty(trim($detail['bank_statment_id']))) {
                        DB::connection('sqlSrvFPI')->table('bank_note')
                            ->where('tr_id', $detail['bank_statment_id'])
                            ->update([
                                'voucher' => 1,
                                'pay_id' => $trId
                            ]);
                    }
                }
            });
            $detailJv = DB::connection('sqlSrvFPI')->table('jv_trans_dtl')
                ->selectRaw("sum(amt_dr) as amt_dr,sum(amt_cr) as amt_cr ")
                ->where('tr_id', $trId)
                ->where('office_id', 1)
                ->first();
            //dd($detailJv);
            DB::connection('sqlSrvFPI')->table('jv_trans_hdr')
                ->where('tr_id',  $trId)
                ->update([
                    'amt_dr' => $detailJv->amt_dr,
                    'amt_cr' => $detailJv->amt_cr
                ]);
            //dd("masuk");
            $tanggal = Carbon::parse($data['tanggal']);
            $tahun4digit = $tanggal->format('Y');
            $this->serviceAcc->JrnlOtherJournal(1, $trId, $tahun4digit, $userId);


            return $this->sendResponse($result, 'Transaction created successfully.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function getDataTransaction(Request $request)
    {
        $sqlWhere = "1=1";
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $tr_type = $request->tr_type;
        if (!$start_date) {
            return $this->sendError("Empty transaction start date", 'Error', 500);
        }
        if (!$end_date) {
            return $this->sendError("Empty transaction end date", 'Error', 500);
        }
        if ($start_date) {
            $sqlWhere .= " AND CAST(ct.dt_jv AS DATE) >= CONVERT(DATE, '" . $start_date . "')";
        }
        if ($end_date) {
            $sqlWhere .= " AND CAST(ct.dt_jv AS DATE) <= CONVERT(DATE, '" . $end_date . "')";
        }
        if ($tr_type) {
            $sqlWhere .= " AND ct.tr_type = '$tr_type' ";
        }
        try {
            $connection = DB::connection("sqlSrvFPI");

            $result = $connection->table('jv_trans_hdr as ct')
                ->selectRaw("
                    ct.office_id,
                    ct.tr_id,
                    ct.dt_jv,
                    ct.descr,
                    ct.amt_dr,
                    ct.amt_cr,
                    ct.paid_to_txt, 
                    ct.site_id, 
                    ct.cluster_id, 
                    ct.house_id,
                    ct.tr_type
                ")
                ->whereRaw($sqlWhere)
                ->orderBy('ct.dt_jv', 'desc')
                ->get();
            $message = 'Successfully retrieved  data';
            return $this->sendResponse($result, $message);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function updateOtherJurnal(Request $request)
    {

        $result = "";
        $tr_id = $request->tr_id;
        if (empty($tr_id)) {
            return $this->sendError("Empty transaction id", 'Error', 500);
        }
        $data = $request->all();
        $userId = getAuth($request)->user_id2 ?? null;
        if ($this->serviceAcc->ifAcctClosed($data['tanggal'], $request)) {
            return $this->sendResponse(null, 'This periode already closed, cannot be modified');
        }
        try {
            DB::connection('sqlSrvFPI')->transaction(function () use ($data, $userId) {

                DB::connection('sqlSrvFPI')->table('jv_trans_hdr')
                    ->where('office_id', 1)
                    ->where('tr_id', $data['tr_id'])
                    ->update([
                        'dt_jv' => $data['tanggal'],
                        'descr' => $data['memo'],
                        'paid_to_txt' => empty($data['pay_to'])  ? '' : $data['pay_to'], // $data['paid_to'],
                        'rate' => '1',
                        'curr_id' => 'IDR',
                        'site_id' => $data['site_id'] ?? '',
                        'cluster_id' => $data['cluster_id'] ?? '',
                        'house_id' => $data['house_item_id'] ?? '',
                        'dt_created' => now(),
                        'dt_modified' => now(),
                        'user_id' => $userId
                    ]);

                $detailJv = DB::connection('sqlSrvFPI')->table('jv_trans_dtl')
                    ->select('tr_id_ref')
                    ->where('tr_id', $data['tr_id'])
                    ->where('office_id', 1)
                    ->get();
                if ($detailJv) {
                    foreach ($detailJv as $key => $detail1) {
                        if (trim($detail1->tr_id_ref) !== "") {
                            DB::connection('sqlSrvFPI')->table('bank_note')
                                ->where('tr_id', $detail1->tr_id_ref)
                                ->update([
                                    'voucher' => 0,
                                    'pay_id' => ''
                                ]);
                        }
                    };
                };
                DB::connection('sqlSrvFPI')->table('jv_trans_dtl')
                    ->where('tr_id', $data['tr_id'])
                    ->where('office_id', 1)
                    ->delete();

                foreach ($data['details'] as $key => $detail) {

                    DB::connection('sqlSrvFPI')->table('jv_trans_dtl')->insert([
                        'office_id' => 1,
                        'tr_id' => $data['tr_id'],
                        'ac_num' => $detail['account'],
                        'amt_dr' => (float) $detail['debit'],
                        'amt_cr' => (float)  $detail['credit'],
                        'item_memo' => $detail['memo'],
                        'tr_id_ref' => empty($detail['bank_statment_id'])  ? '' : $detail['bank_statment_id'], //$detail['no_spr'] ?? '',
                        'dt_created' => now(),
                        'dt_modified' => now(),
                        'user_id' => $userId,
                        'item_num' => $key + 1,
                    ]);
                    if (!empty($detail['bank_statment_id'])) {
                        DB::connection('sqlSrvFPI')->table('bank_note')
                            ->where('tr_id', $detail['bank_statment_id'])
                            ->update([
                                'voucher' => 1,
                                'pay_id' => $data['tr_id']
                            ]);
                    }
                }
            });
            $detailJv = DB::connection('sqlSrvFPI')->table('jv_trans_dtl')
                ->selectRaw("sum(amt_dr) as amt_dr,sum(amt_cr) as amt_cr ")
                ->where('tr_id', $tr_id)
                ->where('office_id', 1)
                ->first();
            DB::connection('sqlSrvFPI')->table('jv_trans_hdr')
                ->where('tr_id',  $tr_id)
                ->update([
                    'amt_dr' => $detailJv->amt_dr,
                    'amt_cr' => $detailJv->amt_cr
                ]);
            $tanggal = Carbon::parse($data['tanggal']);
            $tahun4digit = $tanggal->format('Y');
            $this->serviceAcc->JrnlOtherJournal(1, $tr_id, $tahun4digit, $userId);


            return $this->sendResponse($result, 'Transaction edited successfully.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function destroyOtherJurnal(Request $request)
    {
        $result = "";
        $tr_id = $request->tr_id;
        $userId = getAuth($request)->user_id2 ?? null;
        //$userId = getAuth($request)->user_id2 ?? null;
        if (empty($tr_id)) {
            return $this->sendError("Empty transaction id", 'Error', 500);
        }

        try {
            $detailJv = DB::connection('sqlSrvFPI')->table('jv_trans_dtl as a')
                ->selectRaw("a.tr_id_ref,b.dt_jv")
                ->join('jv_trans_hdr as b', function ($join) {
                    $join->on('b.office_id', '=', 'a.office_id')
                        ->on('b.tr_id', '=', 'a.tr_id');
                })
                ->where('a.tr_id', $tr_id)
                ->where('a.office_id', 1)
                ->get();
            if ($this->serviceAcc->ifAcctClosed($detailJv[0]->dt_jv, $request)) {
                return $this->sendResponse(null, 'This periode already closed, cannot be modified');
            }
            if ($detailJv) {
                foreach ($detailJv as $key => $detail1) {
                    if (trim($detail1->tr_id_ref) !== "") {
                        DB::connection('sqlSrvFPI')->table('bank_note')
                            ->where('tr_id', $detail1->tr_id_ref)
                            ->update([
                                'voucher' => 0,
                                'pay_id' => ''
                            ]);
                    }
                };
            };

            DB::connection('sqlSrvFPI')->transaction(function () use ($tr_id) {
                DB::connection('sqlSrvFPI')->table('jv_trans_dtl')
                    ->where('office_id', 1)
                    ->where('tr_id', $tr_id)
                    ->delete();
                DB::connection('sqlSrvFPI')->table('jv_trans_hdr')
                    ->where('office_id', 1)
                    ->where('tr_id', $tr_id)
                    ->delete();
            });
            $tanggal = Carbon::parse($detailJv[0]->dt_jv);
            $tahun4digit = $tanggal->format('Y');
            $this->serviceAcc->JrnlOtherJournal(1, $tr_id, $tahun4digit, $userId);

            return $this->sendResponse($result, 'Transaction deleted successfully.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function generateTrId($tanggal,$tr_type)
    {
        //$prefix = "OJV"; //($type == 'OD') ? 'BKM01' : 'BKK01';
        $tahun2digit = date('y', strtotime($tanggal));
        $bulan2digit = date('m', strtotime($tanggal));
        $last = DB::connection("sqlSrvFPI")
            ->table('jv_trans_hdr')
            ->selectRaw('max(tr_num) as kode_akhir ')
            ->where('tr_id', 'like', "%{$tr_type}{$tahun2digit}{$bulan2digit}%")
            ->first('');
        //dd($last);

        if ($last) {
            $lastParts =  (int) $last->kode_akhir;    // contoh: 25
            $newNumber = $lastParts + 1;
        } else {
            $newNumber = 1;
        }

        // Format ke 3 digit
        $formattedNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);

        return "{$tr_type}{$tahun2digit}{$bulan2digit}{$formattedNumber}";
    }
    public function getBankStatement(Request $request)
    {
        $sqlWhere = " bn.voucher=0 ";
        $tr_date = $request->tanggal;
        $account = $request->account;
        $debit = $request->debit;
        $credit = $request->credit;
        if (!$tr_date) {
            return $this->sendError("Empty transaction date", 'Error', 500);
        }
        if ($tr_date) {
            $sqlWhere .= " AND CAST(bn.dt_trans AS DATE) = CONVERT(DATE, '" . $tr_date . "')";
        }
        if (!empty($tr_type)) {
            $sqlWhere = $sqlWhere . "and bn.tr_type = " . "'" . $tr_type . "'";
        }
        if (!empty($account)) {
            $sqlWhere = $sqlWhere . "and ac.ac_num = " . "'" . $account . "'";
        }

        if ((int)$debit > 0) {
            $sqlWhere = $sqlWhere . "and bn.tr_type='C' and bn.cr_amt= " . "" . $debit . "";
        }
        if ((int)$credit > 0) {
            $sqlWhere = $sqlWhere . "and bn.tr_type='D' and bn.db_amt= " . "" . $credit . "";
        }
        // dd($credit);
        //dd($sqlWhere);
        try {
            $result = DB::connection("sqlSrvFPI")
                ->table('bank_note as bn')
                ->selectRaw("
                        bn.tr_id , bn.bank_acc, ge.ac_name,bn.dt_trans,bn.descr,format(bn.db_amt,'#,0') as db_amt,format(bn.cr_amt,'#,0') as cr_amt,trim(bn.pay_id) pay_id
                    ")
                ->join('account as ac', 'bn.bank_acc', '=', 'ac.bank_acc')
                ->join('g_ledger as ge', 'ac.ac_num', '=', 'ge.ac_num')
                ->whereRaw($sqlWhere)
                ->orderBy('bn.run_number', 'desc')
                ->get();
            return $this->sendResponse($result, 'Successfully get bank statement data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
}
