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

class OtherDepositController extends Controller
{
    protected $serviceAcc;

    public function __construct(AccountingService $serviceAcc)
    {
        $this->serviceAcc = $serviceAcc;
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/OtherDeposit/getDataMasterFinance",
     *     tags={"Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="type", type="string", example="ABC"),
     *             @OA\Property(property="bank_d", type="string", example="XYZ"),
     *             @OA\Property(property="site_id", type="string", example="ABC"),
     *             @OA\Property(property="cluster_id", type="string", example="ABC"),
     *             @OA\Property(property="order_id", type="string", example="ABC"),
     *             @OA\Property(property="id_cluster", type="string", example="ABC"),
     *             @OA\Property(property="id_house", type="string", example="ABC"),
     *             @OA\Property(property="id_site", type="string", example="ABC"),
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all data",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getDataMasterFinance(Request $request)
    {
        $type = $request->type;

        try {
            $connection = DB::connection("sqlSrvFPI");

            switch ($type) {
                case 'vendor':
                    $result = $connection->table('vendor')
                        ->selectRaw("TRIM(vendor_id) AS vendor_id, TRIM(vendor_name) AS vendor_name, trim(address1) address1,trim(address2) address2
                        ,trim(city) city")
                        ->where('active_flag', 'Y')
                        ->get();
                    $message = 'Successfully retrieved COA data';
                    break;
                case 'coa':
                    $result = $connection->table('g_ledger')
                        ->selectRaw("TRIM(ac_num) AS ad_num, TRIM(ac_name) AS ac_name, curr_id")
                        ->get();
                    $message = 'Successfully retrieved COA data';
                    break;
                case 'coaKas':
                    $result = $connection->table('g_ledger')
                        ->selectRaw("TRIM(ac_num) AS ad_num, TRIM(ac_name) AS ac_name, curr_id")
                        ->where('ac_name', 'LIKE', '%Kas%')
                        ->where('ac_num', 'LIKE', '%110-010-%')
                        ->where('active_flag', 'Y')
                        ->get();
                    $message = 'Successfully retrieved COA data';
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
                    // if ($result->isEmpty()) {
                    //     return $this->sendError("No bank account data found for the given bank name", 'Data Not Found', 500);
                    // }
                    $message = 'Successfully retrieved bank account data';
                    break;
                case 'kwitansi':
                    $result = $connection->table('kwitansi as a')
                        ->selectRaw("trim(a.tr_id) No_Kwitansi,
                            trim(a.type_code) as type_code,
                            trim(b.site_name) as site_name,
                            a.tr_amt Kwitansi_Amt,
                            a.tr_date,
                            trim(a.remark) as remark
                            ")
                        ->join('site as b', 'a.id_site', '=', 'b.id_site')
                        ->where('a.tr_id_ref', '')
                        ->get();
                    // if ($result->isEmpty()) {
                    //     return $this->sendError("No kwitansi data found", 'Data Not Found', 500);
                    // }
                    $message = 'Successfully retrieved kwitansi data';
                    break;
                case 'giro':
                    // sSQL = "select a.giro_num,bank_name,bank_cust_name,giro_amt,dt_giro,dt_due " & _
                    // " from giro_trans a inner join bank_mast b on a.bank_id=b.bank_id    " & _
                    // " where a.stat='B' and a.tr_id='' and tr_type='IG' and a.bank_acc='" & Trim(FAccount) & "' And A.giro_amt = " & CDbl(AmtFrom)
                    $result = $connection->table('giro_trans as a')
                        ->selectRaw("trim(a.giro_num) as giro_num,trim(b.bank_name) as bank_name,trim(bank_cust_name) as bank_cust_name,giro_amt,dt_giro,dt_due")
                        ->join('bank_mast as b', 'a.bank_id', '=', 'b.bank_id')
                        ->where('a.stat', 'B')
                        ->where('a.tr_id', '')
                        ->where('tr_type', 'IG')
                        ->where('a.stat', 'B')
                        ->get();
                    // if ($result->isEmpty()) {
                    //     return $this->sendError("No kwitansi data found", 'Data Not Found', 500);
                    // }
                    $message = 'Successfully retrieved kwitansi data';
                    break;
                case 'site':
                    $result = $connection->table('site')
                        ->selectRaw("trim(id_site) as id_site,trim(site_name) as site_name")
                        ->get();
                    $message = 'Successfully retrieved site data';
                    break;
                case 'cluster':
                    $site_id = $request->site_id;
                    if (empty($site_id)) {
                        return $this->sendError("Empty Site ID", 'Error', 500);
                    }

                    $result = $connection->table('site_cluster')
                        ->selectRaw("trim(id_cluster) as id_cluster, trim(cluster_name) as cluster_name")
                        ->where('id_site', $site_id)
                        ->get();
                    // if ($result->isEmpty()) {
                    //     return $this->sendError("No cluster data found for the given Site", 'Data Not Found', 500);
                    // }
                    $message = 'Successfully retrieved bank account data';
                    break;
                case 'house':
                    $site_id = $request->site_id;
                    $cluster_id = $request->cluster_id;
                    if (empty($site_id)) {
                        return $this->sendError("Empty Site ID", 'Error', 500);
                    }
                    if (empty($cluster_id)) {
                        return $this->sendError("Empty Cluster ID", 'Error', 500);
                    }

                    $result = $connection->table('house_unit')
                        ->selectRaw("trim(id_house) as id_house, trim(house_name) as house_name")
                        ->where('id_site', $site_id)
                        ->where('id_cluster', $cluster_id)
                        ->get();

                    // if ($result->isEmpty()) {
                    //     return $this->sendError("No house data found for the given Site and Cluster ID", 'Data Not Found', 500);
                    // }
                    $message = 'Successfully retrieved bank account data';
                    break;
                case 'includeprice':
                    $type = $request->type;
                    $order_id = $request->order_id;
                    if (empty($type)) {
                        return $this->sendError("Empty Type", 'Error', 500);
                    }
                    if (empty($order_id)) {
                        return $this->sendError("Empty spr id", 'Error', 500);
                    }

                    $result = $connection->table('CustomerorderAddonUnit as a')
                        ->selectRaw("a.tr_seq,trim(b.description) as description,amount,trim(remark) as remark")
                        ->join('article as b', 'a.article_id', '=', 'b.article_id')
                        ->where('order_id', $order_id)
                        ->where('cat_id', '6')
                        ->get();

                    // if ($result->isEmpty()) {
                    //     return $this->sendError("No house data found for the given Site and Cluster ID", 'Data Not Found', 500);
                    // }
                    $message = 'Successfully retrieved bank account data';
                    break;
                case 'spr':
                    $sqlWhere = "1=1";
                    $type = $request->type;
                    $idCluster = $request->id_cluster;
                    $IDHouse = $request->id_house;
                    $idSite = $request->id_site;

                    if (empty($type)) {
                        return $this->sendError("Empty type", 'Error', 500);
                    }
                    if (empty($idSite)) {
                        return $this->sendError("Empty site", 'Error', 500);
                    }
                    if ($idSite) {
                        $sqlWhere .= " AND a.id_site =  '" . $idSite . "'";
                    }
                    if ($idCluster) {
                        $sqlWhere .= " AND d.id_cluster =  '" . $idCluster . "'";
                    }
                    if ($idCluster) {
                        $sqlWhere .= " AND d.id_house =  '" . $IDHouse . "'";
                    }

                    $result = $connection->table('customerorder as a')
                        ->selectRaw("a.order_id,b.namacustomer,c.site_name,cluster_name,house_name")
                        ->join('customer as b', 'a.customerid', '=', 'b.customerid')
                        ->join('site as c', 'a.id_site', '=', 'c.id_site')
                        ->join('customerorderdetil as d', 'a.order_id', '=', 'd.order_id')
                        ->join('site_cluster as t', function ($join) {
                            $join->on('t.id_site', '=', 'a.id_site')
                                ->on('t.id_cluster', '=', 'd.id_cluster');
                        })
                        ->join('house_unit as h', function ($join) {
                            $join->on('h.id_site', '=', 'a.id_site')
                                ->on('h.id_house', '=', 'd.id_house')
                                ->on('h.id_cluster', '=', 'd.id_cluster');
                        })
                        ->whereRaw($sqlWhere)
                        ->get();

                    $message = 'Successfully retrieved bank account data';
                    break;
                case 'debit_note':
                    $result = $connection->table('credit_note as a')
                        ->selectRaw("TRIM(a.tr_id) tr_id ,tr_date, TRIM(c.vendor_name) AS vendor_name, tr_amt")
                        ->join('bank_note  as b', 'a.bk', '=', 'b.tr_id')
                        ->join('vendor  as c', 'a.cust_id', '=', 'c.vendor_id')
                        ->get();
                    $message = 'Successfully retrieved debit note data';
                    break;
                default:
                    return $this->sendError("Invalid type parameter", 'Error', 500);
            }

            return $this->sendResponse($result, $message);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/fpi/OtherDeposit/getDataById",
     *     tags={"Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="type", type="string", example="ABC"),
     *             @OA\Property(property="tr_type", type="string", example="XYZ"),
     *             @OA\Property(property="office_id", type="string", example="ABC"),
     *             @OA\Property(property="tr_id", type="string", example="ABC"),
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all data",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getDataById(Request $request)
    {
        $type = $request->type;
        $tr_type = $request->tr_type;
        $office_id = $request->office_id;
        if (empty($type)) {
            return $this->sendError("Empty Type", 'Error', 500);
        }
        if (empty($tr_type)) {
            return $this->sendError("Empty transaction type", 'Error', 500);
        }
        if (empty($office_id)) {
            return $this->sendError("Empty office ID", 'Error', 500);
        }
        try {
            $connection = DB::connection("sqlSrvFPI");

            switch ($type) {
                case 'header':
                    $tr_id = $request->tr_id;
                    //$cluster_id = $request->cluster_id;
                    if (empty($tr_id)) {
                        return $this->sendError("Empty Transaction ID", 'Error', 500);
                    }
                    $result = $connection->table('cash_tran_hdr as ct')
                        ->selectRaw("ct.office_id,trim(tr_type) tr_type,trim(tr_id) tr_id, tr_date,trim(ct.ac_num) ac_num,
                                    trim(cek_no) cek_no,trim(memo_txt) memo_txt,trim(paid_to_txt) paid_to_txt,trim(ct.curr_id) curr_id,exchange_rate,
                                    amount,trim(amt_said) amt_said,tr_num,
                                    ct.dt_created,ct.dt_modified,trim(ct.user_id) user_id,trim(budget_id) budget_id,trim(ct.id_site) id_site,trim(ct.id_cluster) id_cluster,trim(ct.id_house) id_house
                                    ,trim(kwitansi) kwitansi,ct.tr_id_ref,
                                    trim(gl.ac_name) ac_name,trim(site_name) site_name,trim(cluster_name) cluster_name,trim(house_name) house_name
                                    ,trim(ac.bank_acc) bank_acc,trim(ac.bank_name) bank_name
                            ")
                        ->join('g_ledger as gl', 'ct.ac_num', '=', 'gl.ac_num')
                        ->leftjoin('site as s', 's.id_site', '=', 'ct.id_site')
                        ->leftjoin('site_cluster as t', function ($join) {
                            $join->on('t.id_site', '=', 'ct.id_site')
                                ->on('t.id_cluster', '=', 'ct.id_cluster');
                        })
                        ->leftjoin('house_unit as h', function ($join) {
                            $join->on('h.id_site', '=', 'ct.id_site')
                                ->on('h.id_house', '=', 'ct.id_house')
                                ->on('h.id_cluster', '=', 'ct.id_cluster');
                        })
                        ->leftJoin('account as ac', 'ac.ac_num', '=', 'ct.ac_num')
                        ->where('ct.tr_id', $tr_id)
                        ->where('ct.tr_type', $tr_type)
                        ->where('ct.office_id', $office_id)
                        ->first();
                    // if ($result->isEmpty()) {
                    //     return $this->sendError("data not found", 'Data Not Found', 500);
                    // }
                    $message = 'Successfully retrieved  data';
                    break;

                case 'detail':
                    $tr_id = $request->tr_id;
                    if (empty($tr_id)) {
                        return $this->sendError("Empty Transaction ID", 'Error', 500);
                    }
                    $result = $connection->table('cash_tran_dtl as ct')
                        ->selectRaw("ct.*, gl.ac_name")
                        ->join('g_ledger as gl', 'ct.ac_num', '=', 'gl.ac_num')
                        ->where('ct.tr_id', $tr_id)
                        ->where('ct.tr_type', $tr_type)
                        ->where('ct.office_id', $office_id)
                        ->get();
                    // if ($result->isEmpty()) {
                    //     return $this->sendError("data not found", 'Data Not Found', 500);
                    // }
                    $message = 'Successfully retrieved  data';
                    break;
                default:
                    return $this->sendError("Invalid type parameter", 'Error', 500);
            }

            return $this->sendResponse($result, $message);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/fpi/OtherDeposit/storeDeposit",
     *     tags={"Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="tr_type", type="string", example="ABC"),
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all data",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function storeDeposit(Request $request)
    {
        $result = '';
        $data = $request->all();
        $userId = getAuth($request)->user_id2 ?? null;
        if ($this->serviceAcc->ifAcctClosed($data['tanggal'], $request)) {
            return $this->sendError("This periode already closed, cannot be modified", 'Error', 500);
        }
        $exist = DB::connection('sqlSrvFPI')->table('cash_tran_hdr')
            ->select('tr_date')
            ->where('tr_type', $data['tr_type'])
            ->where('office_id', $data['office_id'])
            ->where('tr_id', $data['tr_id'])
            ->first();
        if ($exist) {
            $this->updateDeposit($request);
            return $this->sendResponse($result, 'Transaction updated successfully.');
        } else {
            if (empty($data['tr_id'])) {
                $trId = $this->generateTrId($data['tanggal'], $data['tr_type']);
            } else {
                $trId = $data['tr_id'];
            }
            try {
                DB::connection('sqlSrvFPI')->transaction(function () use ($data, $userId, $trId) {


                    DB::connection('sqlSrvFPI')->table('cash_tran_hdr')->insert([
                        'office_id' => trim($data['office_id']),
                        'tr_id' => $trId,
                        'tr_type' => $data['tr_type'],
                        'tr_date' => $data['tanggal'],
                        'id_site' => $data['site_id'],
                        'id_cluster' => $data['cluster_id'],
                        'id_house' => $data['house_item_id'],
                        'kwitansi' => !@$data['kwitansi_id']  ? '' : $data['kwitansi_id'], //$data['kwitansi_id'] ?? '',
                        'ac_num' => $data['ac_num'],
                        'amount' => (float) $data['debit'],
                        'memo_txt' => $data['memo'],
                        'paid_to_txt' => empty($data['paid_to'])  ? '' : $data['paid_to'], // $data['paid_to'],
                        'exchange_rate' => $data['rate'],
                        'curr_id' => 'IDR',
                        'dt_created' => now(),
                        'dt_modified' => now(),
                        'cek_no' => !@$data['no_giro']  ? '' : $data['no_giro'], //$data['no_giro'] ?? '',
                        'tr_id_ref' => !@$data['tr_id_ref'] ? '' : $data['tr_id_ref'],
                        'user_id' => $userId
                    ]);

                    foreach ($data['details'] as $key => $detail) {

                        DB::connection('sqlSrvFPI')->table('cash_tran_dtl')->insert([
                            'office_id' => trim($data['office_id']),
                            'tr_id' => $trId,
                            'tr_type' => $data['tr_type'],
                            'ac_num' => $detail['account'],
                            'amount' => (float) $detail['jumlah'],
                            'item_notes' => $detail['catatan'],
                            'order_id' => !@$data['no_spr']  ? '' : $data['no_spr'], //$detail['no_spr'] ?? '',
                            'item_bonus' => !@$data['included_price']  ? '' : $data['included_price'], //$detail['included_price'] ?? '',
                            'debit_note' => !@$data['debit_note']  ? '' : $data['debit_note'], //$data['debit_note'] ?? '',
                            'seq_num' => $key + 1,
                        ]);
                        if (@$detail['included_price']) {
                            $includedPriceAmt = DB::connection('sqlSrvFPI')->table('cash_tran_dtl')
                                ->where('order_id', $detail['no_spr'])
                                ->where('item_bonus', $detail['included_price'])
                                ->selectRaw('ISNULL(SUM(amount), 0) as jumlah')
                                ->value("jumlah");
                            DB::connection('sqlSrvFPI')->table('CustomerorderAddonUnit')
                                ->where('order_id', $detail['no_spr'])
                                ->where('tr_seq', $detail['included_price'])
                                ->update([
                                    'p_amount' => $includedPriceAmt
                                ]);
                        }
                    }
                    if (!empty(trim($data['tr_id']))) {
                        DB::connection('sqlSrvFPI')->table('bank_note')
                            ->where('tr_id', $data['tr_id'])
                            ->update([
                                'voucher' => 1,
                                'pay_id' => $data['tr_id']
                            ]);

                        DB::connection('sqlSrvFPI')->table('kb_hdr')
                            ->where('tr_id', $data['tr_id'])
                            ->update([
                                'voucher' => 1
                            ]);
                    }

                    if (@$data['kwitansi_id']) {
                        DB::connection('sqlSrvFPI')->table('kwitansi')
                            ->where('tr_id', $data['kwitansi_id'])
                            ->update([
                                'tr_id_ref' => $data['tr_id']
                            ]);
                    }
                    if (@$data['no_giro']) {
                        DB::connection('sqlSrvFPI')->table('giro_trans')
                            ->where('giro_num', $data['no_giro'])
                            ->update([
                                'stat' => 'C',
                                'clear_flag' => 'C',
                                'tr_id' => $trId
                            ]);
                    }
                    if (@$data['debit_note']) {
                        DB::connection('sqlSrvFPI')->table('credit_note')
                            ->where('tr_id', $data['debit_note'])
                            ->update([
                                'stat' => 'P'
                            ]);
                    }
                    if (@$data['tr_id_ref']) {
                        DB::connection('sqlSrvFPI')->table('bank_note')
                            ->where('tr_id', $data['tr_id_ref'])
                            ->update([
                                'voucher' => 1,
                                'pay_id' => $data['tr_id']
                            ]);
                    }
                });
                $tanggal = Carbon::parse($data['tanggal']);
                $tahun4digit = $tanggal->format('Y');
                if (trim($data['tr_type'] == "OD")) {
                    $this->serviceAcc->JrnlOtherDeposit($data['office_id'], $trId, $tahun4digit, $userId);
                } else {
                    $this->serviceAcc->JrnlOtherPayment($data['office_id'], $trId, $tahun4digit, $userId);
                }

                return $this->sendResponse($result, 'Transaction created successfully.');
            } catch (\Exception $e) {
                return $this->sendError($e->getMessage(), 'Error', 500);
            }
        }
    }
    /**
     * @OA\Post(
     *     path="/api/fpi/OtherDeposit/getBankStatement",
     *     tags={"Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="bank_acc", type="string", example="ABC"),
     *             @OA\Property(property="if_posting", type="string", example="XYZ"),
     *             @OA\Property(property="tr_date_to", type="string", example="ABC"),
     *             @OA\Property(property="tr_date_from", type="string", example="ABC"),
     *             @OA\Property(property="tr_type", type="string", example="ABC"),
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all data",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getBankStatement(Request $request)
    {
        $sqlWhere = "1=1";

        $bank_acc = $request->bank_acc;
        $if_Posting = $request->if_posting;
        $tr_date_to = $request->tr_date_to;
        $tr_date_from = $request->tr_date_from;
        $tr_type = $request->tr_type;
        if (!$tr_date_to) {
            return $this->sendError("Empty transaction date", 'Error', 500);
        }
        if (!$tr_date_from) {
            return $this->sendError("Empty transaction date", 'Error', 500);
        }
        if (trim($tr_type != 'K')) {
            if (empty($bank_acc)) {
                return $this->sendError("Empty bank account", 'Error', 500);
            }

            if (!empty($bank_acc)) {
                $sqlWhere = $sqlWhere . "and bn.bank_acc = " . "'" . $bank_acc . "'";
            }
            if ($tr_date_to) {
                $sqlWhere .= " AND CAST(bn.dt_trans AS DATE) <= CONVERT(DATE, '" . $tr_date_to . "')";
            }
            if ($tr_date_from) {
                $sqlWhere .= " AND CAST(bn.dt_trans AS DATE) >= CONVERT(DATE, '" . $tr_date_from . "')";
            }
            //type bank other payment D, deposit C, split S, kas bon K

            if (!empty($tr_type)) {
                $sqlWhere = $sqlWhere . "and bn.tr_type = " . "'" . $tr_type . "'";
            }
            if (!empty($if_Posting)) {
                if (strtoupper($if_Posting) == 'Y') {
                    $sqlWhere = $sqlWhere . "and bn.voucher >0";
                } else {
                    $sqlWhere = $sqlWhere . "and bn.voucher = 0";
                }
            }
        }
        try {
            if (trim($tr_type === 'K')) {
                $result = DB::connection("sqlSrvFPI")
                    ->table('kb_hdr as a')
                    ->selectRaw("
                         a.remark Descr,'' cabang,'' cabang1,'' City,'' cr_code,0 diprint,'' remark1,'' link_id,'' db_code,kb_amt db_amt,0 cr_amt,0 saldo,trim(a.tr_id) tr_id,
                        voucher, 0 run_number,a.dt_kb dt_trans,'C' tr_type,'' trx_mode,'C' tr_type, trim(a.tr_id) pay_id,
                        trim((select top 1 ac_num from g_ledger_std where ac_name ='BON SEMENTARA'))  ac_num
                        ")
                    ->join('kb_dtl as b', function ($join) {
                        $join->on('a.tr_id', '=', 'b.tr_id')
                            ->on('a.office_id', '=', 'b.office_id');
                    })
                    ->where('a.stat', '=', 'C')
                    ->where('tr_type', '=', 'IN')
                    ->whereDate('a.dt_kb', '>=', $tr_date_from)
                    ->whereDate('a.dt_kb', '<=', $tr_date_to)
                    ->get();
                //where a.stat='C' and a.dt_kb='20250710' and voucher=0 CAST(bn.dt_trans AS DATE) = CONVERT(DATE, '" . $tr_date . "')
            } else {
                $result = DB::connection("sqlSrvFPI")
                    ->table('bank_note as bn')
                    ->selectRaw("
                        bn.Descr,bn.cabang,bn.db_amt,bn.cr_amt,bn.saldo,bn.tr_id,bn.voucher,bn.run_number,bn.dt_trans,
                        bn.tr_type,bn.trx_mode,bn.tr_type,trim(bn.pay_id) pay_id,
                        trim(ac.bank_name) bank_name,br.branch_descr as cabang1,br.city,trim(ac.ac_num) ac_num
                    ")
                    ->join('account as ac', 'bn.bank_acc', '=', 'ac.bank_acc')
                    ->leftjoin('bank_branch as br', 'br.bank_branch_code', '=', 'bn.cabang')
                    ->whereRaw($sqlWhere)
                    ->orderBy('bn.run_number', 'desc')
                    ->get();
            }
            // if ($result->isEmpty()) {
            //     return $this->sendError("Bank statement data not found", 'Data Not Found', 500);
            // }
            return $this->sendResponse($result, 'Successfully get bank statement data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/fpi/OtherDeposit/getDataTransaction",
     *     tags={"Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="tr_type", type="string", example="ABC"),
     *             @OA\Property(property="start_date", type="string", example="XYZ"),
     *             @OA\Property(property="end_date", type="string", example="ABC"),
     *             @OA\Property(property="ac_num", type="string", example="ABC"),
     *             @OA\Property(property="id_site", type="string", example="ABC"),
     *             @OA\Property(property="id_cluster", type="string", example="ABC"),
     *             @OA\Property(property="id_house", type="string", example="ABC"),
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all data",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getDataTransaction(Request $request)
    {
        $sqlWhere = "1=1";
        $tr_type = $request->tr_type;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $ac_num = $request->ac_num;
        $id_site = $request->id_site;
        $id_cluster = $request->id_cluster;
        $id_house = $request->id_house;
        if (!$tr_type) {
            return $this->sendError("Empty transaction type", 'Error', 500);
        }
        if (!$start_date) {
            return $this->sendError("Empty transaction start date", 'Error', 500);
        }
        if (!$end_date) {
            return $this->sendError("Empty transaction end date", 'Error', 500);
        }
        if ($start_date) {
            $sqlWhere .= " AND CAST(ct.tr_date AS DATE) >= CONVERT(DATE, '" . $start_date . "')";
        }
        if ($end_date) {
            $sqlWhere .= " AND CAST(ct.tr_date AS DATE) <= CONVERT(DATE, '" . $end_date . "')";
        }
        if (!empty($tr_type)) {
            $sqlWhere = $sqlWhere . "and ct.tr_type = " . "'" . $tr_type . "'";
        }
        if (!empty($ac_num)) {
            $sqlWhere = $sqlWhere . "and ct.ac_num = " . "'" . $ac_num . "'";
        }
        if (!empty($id_site)) {
            $sqlWhere = $sqlWhere . "and ct.id_site = " . "'" . $id_site . "'";
        }
        if (!empty($id_cluster)) {
            $sqlWhere = $sqlWhere . "and ct.id_cluster = " . "'" . $id_cluster . "'";
        }
        if (!empty($id_house)) {
            $sqlWhere = $sqlWhere . "and ct.id_house = " . "'" . $id_house . "'";
        }
        try {
            $connection = DB::connection("sqlSrvFPI");

            $result = $connection->table('cash_tran_hdr as ct')
                ->selectRaw("ct.*, gl.ac_name,site_name,cluster_name,house_name, 
                            CASE
                                WHEN EXISTS (
                                    SELECT 1
                                    FROM recap_breakdown AS kh
                                    WHERE kh.other_pay_ref = ct.tr_id
                                ) THEN 'Y'
                                ELSE 'N'
                            END AS sudahBreakdown
                            ")
                ->join('g_ledger as gl', 'ct.ac_num', '=', 'gl.ac_num')
                ->leftjoin('site as s', 's.id_site', '=', 'ct.id_site')
                ->leftjoin('site_cluster as t', function ($join) {
                    $join->on('t.id_site', '=', 'ct.id_site')
                        ->on('t.id_cluster', '=', 'ct.id_cluster');
                })
                ->leftjoin('house_unit as h', function ($join) {
                    $join->on('h.id_site', '=', 'ct.id_site')
                        ->on('h.id_house', '=', 'ct.id_house')
                        ->on('h.id_cluster', '=', 'ct.id_cluster');
                })
                // ->leftjoin('kb_hdr as kh', 'kh.other_pay_ref', '=', 'ct.tr_id')
                ->whereRaw($sqlWhere)
                ->orderBy('ct.tr_date', 'desc')
                ->get();

            // if ($result->isEmpty()) {
            //     return $this->sendError("data not found", 'Data Not Found', 500);
            // }
            $message = 'Successfully retrieved  data';
            return $this->sendResponse($result, $message);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function updateDeposit(Request $request)
    {

        $result = "";
        $tr_type = $request->tr_type;
        $office_id = $request->office_id;
        $tr_id = $request->tr_id;

        if (empty($tr_type)) {
            return $this->sendError("Empty transaction type", 'Error', 500);
        }
        if (empty($office_id)) {
            return $this->sendError("Empty office", 'Error', 500);
        }
        if (empty($tr_id)) {
            return $this->sendError("Empty transaction id", 'Error', 500);
        }
        $data = $request->all();
        $userId = getAuth($request)->user_id2 ?? null;
        if ($this->serviceAcc->ifAcctClosed($data['tanggal'], $request)) {
            return $this->sendError("This periode already closed, cannot be modified", 'Error', 500);
        }
        try {
            DB::connection('sqlSrvFPI')->transaction(function () use ($data, $userId) {

                DB::connection('sqlSrvFPI')->table('cash_tran_hdr')
                    ->where('tr_type', $data['tr_type'])
                    ->where('office_id', $data['office_id'])
                    ->where('tr_id', $data['tr_id'])
                    ->update([
                        'tr_date' => $data['tanggal'],
                        'id_site' => $data['site_id'],
                        'id_cluster' => $data['cluster_id'],
                        'id_house' => $data['house_item_id'],
                        'kwitansi' => !@$data['kwitansi_id']  ? '' : $data['kwitansi_id'], //$data['kwitansi_id'] ?? '',
                        'ac_num' => $data['ac_num'],
                        'amount' => (float)  $data['debit'],
                        'memo_txt' => $data['memo'],
                        'paid_to_txt' => empty($data['paid_to'])  ? '' : $data['paid_to'],
                        'exchange_rate' => $data['rate'],
                        'curr_id' => 'IDR',
                        'cek_no' => !@$data['no_giro']  ? '' : $data['no_giro'], //$data['no_giro'] ?? '',
                        'tr_id_ref' => !@$data['tr_id_ref'] ? '' : $data['tr_id_ref'],
                        'dt_modified' => now(),
                        'user_id' => $userId
                    ]);

                DB::connection('sqlSrvFPI')->table('cash_tran_dtl')
                    ->where('tr_id', $data['tr_id'])
                    ->where('tr_type', $data['tr_type'])
                    ->where('office_id', $data['office_id'])
                    ->delete();

                foreach ($data['details'] as $key => $detail) {

                    DB::connection('sqlSrvFPI')->table('cash_tran_dtl')->insert([
                        'office_id' => trim($data['office_id']),
                        'tr_id' => $data['tr_id'],
                        'tr_type' => $data['tr_type'],
                        'ac_num' => $detail['account'],
                        'amount' => (float) $detail['jumlah'],
                        'item_notes' => $detail['catatan'],
                        'order_id' => !@$data['no_spr']  ? '' : $data['no_spr'], // $detail['no_spr'] ?? '',
                        'item_bonus' => !@$data['included_price']  ? '' : $data['included_price'], //$detail['included_price'] ?? 0,
                        'debit_note' => !@$data['debit_note']  ? '' : $data['debit_note'], //$data['debit_note'] ?? '',
                        'seq_num' => $key + 1,
                    ]);
                }
                if (!empty($data['tr_id'])) {
                    DB::connection('sqlSrvFPI')->table('bank_note')
                        ->where('pay_id', $data['tr_id'])
                        ->update([
                            'voucher' => 1,
                            'pay_id' => $data['tr_id']
                        ]);
                }

                if (@$data['tr_id_ref']) {
                    DB::connection('sqlSrvFPI')->table('bank_note')
                        ->where('tr_id', $data['tr_id_ref'])
                        ->update([
                            'voucher' => 1,
                            'pay_id' => $data['tr_id']
                        ]);
                }
                if (@$data['kwitansi_id']) {
                    DB::connection('sqlSrvFPI')->table('kwitansi')
                        ->where('tr_id', $data['kwitansi_id'])
                        ->update([
                            'tr_id_ref' => $data['tr_id']
                        ]);
                }
                if (@$data['no_giro']) {
                    DB::connection('sqlSrvFPI')->table('giro_trans')
                        ->where('giro_num', $data['no_giro'])
                        ->update([
                            'stat' => 'C',
                            'clear_flag' => 'C',
                            'tr_id' => $data['tr_id']
                        ]);
                }
                if (@$data['debit_note']) {
                    DB::connection('sqlSrvFPI')->table('credit_note')
                        ->where('tr_id', $data['debit_note'])
                        ->update([
                            'stat' => 'P'
                        ]);
                }
            });
            $tanggal = Carbon::parse($data['tanggal']);
            $tahun4digit = $tanggal->format('Y');
            if (trim($data['tr_type'] == "OD")) {
                $this->serviceAcc->JrnlOtherDeposit($data['office_id'], $tr_id, $tahun4digit, $userId);
            } else {
                $this->serviceAcc->JrnlOtherPayment($data['office_id'], $tr_id, $tahun4digit, $userId);
            }

            return $this->sendResponse($result, 'Transaction edited successfully.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function destroyDeposit(Request $request)
    {
        $result = "";
        $tr_type = $request->tr_type;
        $office_id = $request->office_id;
        $tr_id = $request->tr_id;
        $userId = getAuth($request)->user_id2 ?? null;

        if (empty($tr_type)) {
            return $this->sendError("Empty transaction type", 'Error', 500);
        }
        if (empty($office_id)) {
            return $this->sendError("Empty office", 'Error', 500);
        }
        if (empty($tr_id)) {
            return $this->sendError("Empty transaction id", 'Error', 500);
        }

        try {
            $result = DB::connection('sqlSrvFPI')->table('cash_tran_hdr')
                ->selectRaw("isnull(tr_date,'') tr_date")
                ->where('tr_type', $tr_type)
                ->where('office_id', $office_id)
                ->where('tr_id', $tr_id)
                ->first();
            $tr_date = $result->tr_date ?? now();
            if ($this->serviceAcc->ifAcctClosed($tr_date, $request)) {
                return $this->sendError("This periode already closed, cannot be modified", 'Error', 500);
            }
            DB::connection('sqlSrvFPI')->transaction(function () use ($tr_id, $tr_type, $office_id) {
                DB::connection('sqlSrvFPI')->table('cash_tran_dtl')
                    ->where('tr_type', $tr_type)
                    ->where('office_id', $office_id)
                    ->where('tr_id', $tr_id)
                    ->delete();
                DB::connection('sqlSrvFPI')->table('cash_tran_hdr')
                    ->where('tr_type', $tr_type)
                    ->where('office_id', $office_id)
                    ->where('tr_id', $tr_id)
                    ->delete();
                //if (!empty($data['tr_id'])) {
                DB::connection('sqlSrvFPI')->table('bank_note')
                    ->where('pay_id', $tr_id)
                    ->update([
                        'voucher' => 0,
                        'pay_id' => ''
                    ]);
                //}

                //if (!empty($tr_id_ref)) {
                DB::connection('sqlSrvFPI')->table('bank_note')
                    ->where('tr_id', $tr_id)
                    ->update([
                        'voucher' => 0,
                        'pay_id' => ''
                    ]);
                //}


            });

            $tanggal = Carbon::parse($result->tr_date);
            $tahun4digit = $tanggal->format('Y');
            if (trim($tr_type  == "OD")) {
                $this->serviceAcc->JrnlOtherDeposit($office_id, $tr_id, $tahun4digit, $userId);
            } else {
                $this->serviceAcc->JrnlOtherPayment($office_id, $tr_id, $tahun4digit, $userId);
            }
            return $this->sendResponse($result, 'Transaction deleted successfully.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function generateTrId($tanggal, $type)
    {
        $prefix = ($type == 'OD') ? 'BKM01' : 'BKK01';
        $tahun2digit = date('y', strtotime($tanggal));
        $bulan2digit = date('m', strtotime($tanggal));
        $last = DB::connection("sqlSrvFPI")
            ->table('cash_tran_hdr')
            ->selectRaw('RIGHT(trim(tr_id), 3) as kode_akhir')
            ->where('tr_id', 'like', "%{$prefix}{$tahun2digit}{$bulan2digit}%")
            ->orderByDesc('tr_id')
            ->first('tr_id');
        // dd($last);

        if ($last) {
            $lastParts =  (int) $last->kode_akhir;    // contoh: 25
            $newNumber = $lastParts + 1;
        } else {
            $newNumber = 1;
        }

        // Format ke 3 digit
        $formattedNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);

        return "{$prefix}{$tahun2digit}{$bulan2digit}{$formattedNumber}";
    }

    public function jurnalKasBon(Request $request)
    {
        $mill = $request->MillId;
        $trID1 = $request->trId;
        $curr = $request->CurrYear;
        $user = $request->UserId;
        //dd($mill, $trID1, $curr, $user);
        //$Mill, $TrID, $CurrYear, $userId
        $this->serviceAcc->JrnlCustomerDeposit($mill,  $trID1, $curr, $user);
    }
    public function ifAcctClosed(Request $request)
    {
        $tr_date = $request->tr_date;
        //dd($mill, $trID1, $curr, $user);
        //$Mill, $TrID, $CurrYear, $userId
        //$ifClosed = $this->serviceAcc->ifAcctClosed($tr_date);
        if ($this->serviceAcc->ifAcctClosed($tr_date, $request)) {
            return $this->sendResponse(null, 'sudah closing');
        } else {
            return $this->sendResponse(null, 'belum closing');
        }
    }
}
