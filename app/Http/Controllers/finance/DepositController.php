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

class DepositController extends Controller
{

    protected $serviceAcc;

    public function __construct(AccountingService $serviceAcc)
    {
        $this->serviceAcc = $serviceAcc;
    }

    /**
     * @OA\Post(
     *     path="/api/bcki/deposit/getListBankNote",
     *     tags={"Deposit"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="TrDate", type="date", example="2023-01-01"),
     *             @OA\Property(property="TrDate2", type="date", example="2023-12-31"),
     *             @OA\Property(property="FBank", type="string", example="Bank ABC"),
     *             @OA\Property(property="FAccount", type="string", example="123456789"),
     *             @OA\Property(property="Posting", type="string", example="Y"),
     *             @OA\Property(property="tr_type", type="string", example="CUSTOMER"),
     * 
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List Bank Note yang akan di posting ke deposit",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getListBankNote(Request $request)
    {
        try {

            $sqlWhere = '1=1 ';

            if ($request->type == 'C') {
                if ($request->tr_type == 'CUSTOMER') {
                    $sqlWhere .= " and bn.tr_type = 'C' and bn.link_id='' ";
                } else if ($request->tr_type == 'VENDOR') {
                    $sqlWhere .= " and bn.tr_type = 'D' ";
                } else {
                    return $this->sendError("TR TYPE Harus di isi", 'Error', 500);
                }
            } else {
                if ($request->tr_type == 'CUSTOMER') {
                    $sqlWhere .= " and bn.tr_type = 'S' and bn.cr_amt > 0 ";
                } else if ($request->tr_type == 'VENDOR') {
                    $sqlWhere .= " and bn.tr_type = 'S' and bn.db_amt > 0 ";
                } else {
                    return $this->sendError("TR TYPE Harus di isi", 'Error', 500);
                }
            }

            if ($request->TrDate) {
                $sqlWhere .= "and dt_trans  >= '$request->TrDate'";
            }
            if ($request->TrDate2) {
                $sqlWhere .= "and dt_trans  <= '$request->TrDate2'";
            }
            if ($request->FBank) {
                $sqlWhere .= "and ac.bank_name  = '$request->FBank'";
            }
            if ($request->FAccount) {
                $sqlWhere .= "and bn.bank_acc = '$request->FAccount'";
            }
            if ($request->Posting == 'Y') {
                $sqlWhere .= "AND voucher = 1";
            } else if ($request->Posting == 'N') {
                $sqlWhere .= "AND voucher = 0";
            }

            // Get List Bank Note
            $getListBankNote = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("Select descr, 
                                    case when rtrim(isnull(case when bn.tr_type='S' then (select office from branch_office where office_id=cast( bn.Cabang as int)) end,'')) = '' 
                                    then bn.cabang +' : ' + rTrim(isnull(branch_descr,'')) + ' : ' + rTrim(isnull(City,''))
                                    else isnull(case when bn.tr_type='S' then (select office from branch_office where office_id=cast( bn.Cabang as int)) end,'') end as cabang, 
                                    db_amt,  cr_amt, bn.saldo, tr_id, Voucher, bn.run_number, dt_trans, case when bn.tr_type = 'C' then cr_code else db_code end as Perkiraan,
                                    bn.tr_type, rtrim(IsNull(dp_id, '')) dp_id, IsNull(customer,'') customer, IsNull(ac.ac_num,'') ac_num, Voucher, isnull(bal_amt,0) bal_amt,
                                    isnull((select top 1 order_id from dp_sales_dtl where dp_id=ds.dp_id),'') order_id 
                                from bank_note bn 
                                inner join account ac on bn.bank_acc = ac.bank_acc and ac.a_type in ('B','R') 
                                left outer join bkm_print bp on bp.run_number=bn.run_number
                                left outer join bank_branch br on br.bank_branch_code=bn.cabang
                                left outer join dp_sales ds on ds.bkm=bn.tr_id
                                where $sqlWhere
                                order by bn.run_number asc "));

            //return response()->json(['getListKBHdr' => $getListKBHdr]);
            return $this->sendResponse($getListBankNote, 'Successfully get List Bank Note');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/bcki/deposit/getListDeposit",
     *     tags={"Deposit"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="start_date", type="date", example="2023-01-01"),
     *             @OA\Property(property="end_date", type="date", example="2023-12-31"),
     *             @OA\Property(property="dp_id", type="string", example="123456789"),
     *             @OA\Property(property="cust_id", type="string", example="123456789"),
     *             @OA\Property(property="sisa", type="string", example="Y"),
     * 
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List Deposit yang sudah di simpan",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getListDeposit(Request $request)
    {
        try {
            $sqlWhere = '1=1 ';
            $sqlWhere2 = '1=1 ';
            $sqlWhere .= " and a.tr_type  = 'DP'";

            if ($request->dp_id) {
                $sqlWhere .= " and a.dp_id  = '$request->dp_id'";
                $sqlWhere2 .= " and di.dp_id  = '$request->dp_id'";
            }
            if ($request->cust_id) {
                $sqlWhere .= " and cust_id  = '$request->cust_id'";
                $sqlWhere2 .= " and cust_id  = '$request->cust_id'";
            }
            if ($request->start_date) {
                $sqlWhere .= " and dt_dp  >= '$request->start_date'";
                $sqlWhere2 .= " and dt_dp  >= '$request->start_date'";
            }
            if ($request->end_date) {
                $sqlWhere .= " and dt_dp  <= '$request->end_date'";
                $sqlWhere2 .= " and dt_dp  <= '$request->end_date'";
            }
            if ($request->sisa == 'Y') {
                $sqlWhere .= " and dp_amt - a.used_amt > 0 ";
                $sqlWhere2 .= " and dp_amt - a.used_amt > 0 ";
            }
            //Get List Deposit ini query lama
            $getListDeposit = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select  distinct rtrim(cust_id) as cust_id, namacustomer, rtrim(dp_id) as dp_id,dt_dp,
                                        a.curr_id, inv_id, rtrim(a.ac_num) as ac_num, isnull(ac_name,'') ac_name, rtrim(ac_num_DP) as ac_num_DP,  dp_amt, a.used_amt,
                                        bal_amt, dp_num, a.Remark,  bkm, IsNull(alamat_id_card, '') alamat_id_card, 
                                        kota + ' ' + IsNull(telepon,'') BAddress2, propinsi BAddress3, credit_flag, dp_pajak,
                                        ISNULL(a.rate,1) as exchange_rate,a.Stat,
                                        case when a.Stat = 'S' then 'SPLIT' when a.Stat = 'C' then 'CLOSE' else 'OPEN' end as status,
                                        (select isnull(sum(dp_amt),0) dp_sp from dp_sales_dtl where dp_id=a.dp_id) dp_sp , 'H' baris, 
                                        case when e.bukti is not null then 'Y' else 'N' end as split, f.run_number, rtrim(f.bank_acc) as faccount
                                from dp_sales a inner join customer b  ON a.cust_id = b.customerid 
                                left outer join currency c ON a.curr_id = c.curr_id
                                left outer join g_ledger d ON  a.ac_num = d.ac_num 
                                left outer join payment_hdr e ON  a.dp_id = e.bukti
                                left outer join bank_note f ON  a.bkm=f.tr_id
                                where $sqlWhere                                
                                union all
                                select rtrim(cust_id) as cust_id, namacustomer, rtrim(dp_id) as dp_id, a.tr_date,
                                        '',b.penjualanno, '', '', '',  0, 0,b.pay_amt,b.voucher,'',b.pay_id, 
                                        '', '', 0, '', '',0, '', '', 0 , 'D' baris , 'N' split, 0 run_number, '' bank_acc
                                from dp_sales di inner join 
                                payment_hdr a on di.dp_id=a.bukti inner join 
                                payment_dtl b on a.voucher=b.voucher inner join 
                                penjualan im on  im.penjualanno= b.penjualanno 
                                inner join customer c on im.customerid=c.customerid 
                                where $sqlWhere2                                
                                ORDER BY dp_id "));
           
            return $this->sendResponse($getListDeposit, 'Successfully get List Deposit');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/bcki/deposit/getListInvoice",
     *     tags={"Deposit"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="dp_id", type="string", example="123456789"),    
     *   
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List Invoice yang di posting dari deposit",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getListInvoice(Request $request)
    {
        try {
            $sqlWhere = '1=1 ';
            $sqlWhere .= " and di.dp_id  = '$request->dp_id'";
            // Get List Invoice
            $getListInvoice = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select a.tr_date,rtrim(b.penjualanno) penjualanno,b.pay_amt,b.voucher,b.pay_id,c.namacustomer 
                                from dp_sales di inner join 
                                payment_hdr a on di.dp_id=a.bukti inner join 
                                payment_dtl b on a.voucher=b.voucher inner join 
                                penjualan im on  im.penjualanno= b.penjualanno 
                                inner join customer c on im.customerid=c.customerid 
                                where $sqlWhere
                                order by di.dp_id "));

            //return response()->json(['getListKBHdr' => $getListKBHdr]);
            return $this->sendResponse($getListInvoice, 'Successfully get List Invoice');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    function genDPID($tgl)
    {
        $prefix = 'TDP';
        $year = Carbon::parse($tgl)->format('y');   // contoh: 25
        $month =  Carbon::parse($tgl)->format('m');  // contoh: 05

        // Ambil record terakhir berdasarkan tahun dan bulan
        $last = DB::connection("sqlSrvBCKI")
            ->table('dp_sales')
            // ->selectRaw("top 1 tr_id")
            ->whereRaw("SUBSTRING(dp_id, 4, 2) = ?", [$year])
            ->whereRaw("SUBSTRING(dp_id, 6, 2) = ?", [$month])
            ->orderByDesc('dp_num')
            ->first('dp_num');
        // dd($last);

        if ($last) {
            $newNumber =  $last->dp_num + 1;
        } else {
            $newNumber = 1;
        }

        // Format ke 4 digit
        $formattedNumber = str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        $data = [
            'tr_id' => "{$prefix}{$year}{$month}{$formattedNumber}",
            'dp_num' => $newNumber
        ];
        return $data; //"{$prefix}{$year}{$month}{$formattedNumber}";
    }

    /**
     * @OA\Post(
     *     path="/api/bcki/deposit/saveDeposit",
     *     tags={"Deposit"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="dp_id", type="string", example="123456789"),    
     *  
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Simpan Deposit",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function saveDeposit(Request $request)
    {
        $office_id = '1';
        $tr_type = 'DP';
        $dp_id = trim($request->dp_id ?? '');
        $dt_dp = $request->dt_dp ?? '';
        $tahun4digit =  Carbon::parse($dt_dp)->format('Y');
        $cust_id = $request->cust_id ?? '';
        $curr_id = 'IDR';
        $Rate = 1;
        $ac_num = $request->ac_num ?? '';
        $ac_num_DP = $request->ac_num_DP ?? '';
        $dp_amt = $request->dp_amt ?? '';
        //$inv_id = $request->inv_id ?? '';
        $Remark = $request->Remark ?? '';
        $bkm = $request->bkm ?? '';
        $run_number = $request->run_number ?? '';
        //$dp_pajak = $request->dp_pajak ?? '';
        $tr_typeDN =  $request->tr_typeDN ?? '';
        $faccount =  $request->faccount ?? '';

        if ($this->serviceAcc->ifAcctClosed($dt_dp, $request)) {
            return $this->sendError("Periode ini sudah closing", 'Error', 500);
        }
        if (!$ac_num) {
            return $this->sendError("AC NUM harus di isi", 'Error', 500);
        }
        if (!$ac_num_DP) {
            return $this->sendError("AC NUM DP harus di isi", 'Error', 500);
        }
        try {
            if (!$dp_id) {
                // cek apakah ada Voucher yang sudah digunakan
                $cekTRIDUse = DB::connection("sqlSrvBCKI")
                    ->table('bank_note')
                    ->where('tr_id', $bkm)
                    ->where('tr_type', 'C')
                    ->where('voucher', '<>', 1)
                    ->first('voucher');
                if ($cekTRIDUse && $cekTRIDUse->voucher > 0) {
                    return $this->sendError('Voucher sudah digunakan, silahkan gunakan Voucher lain', 'Error', 500);
                }

                // cek apakah ada BKM yang sudah digunakan
                $cekBKMUse = DB::connection("sqlSrvBCKI")
                    ->table('dp_sales')
                    ->where('bkm', $bkm)
                    ->first();
                if ($cekBKMUse) {
                    return $this->sendError('BKM sudah digunakan, silahkan gunakan BKM lain', 'Error', 500);
                }

                // Generate DP ID
                $genID = $this->genDPID($dt_dp);
                $dp_id = $genID['tr_id'];
                $dp_num = $genID['dp_num'];
            }

            $cekDoc = DB::connection("sqlSrvBCKI")
                ->table('dp_sales')
                ->where('dp_id', $dp_id)
                ->first();

            $data = [
                'office_id' => $office_id,
                'dp_id' => $dp_id,
                'tr_type' => $tr_type,
                'dt_dp' => $dt_dp,
                'cust_id' => $cust_id,
                'curr_id' => $curr_id,
                'Rate' => $Rate,
                'ac_num' => $ac_num,
                'ac_num_DP' => $ac_num_DP,
                'dp_amt' => $dp_amt,
                'dp_num' => $dp_num ?? $cekDoc->dp_num,
                'Remark' => $Remark,
                'bkm' => $bkm,
                'dt_modified' => Carbon::now(),
                'user_id' => getAuth($request)->user_id2
            ];

            if ($cekDoc) {
                DB::connection("sqlSrvBCKI")
                    ->table('dp_sales')
                    ->where('dp_id', $dp_id)
                    ->update($data);
            } else {
                //$printId = $this->genPrintId();
                $data['stat'] = "O";
                $data['dt_created'] = Carbon::now();

                DB::connection("sqlSrvBCKI")
                    ->table('dp_sales')
                    ->insert($data);
            }

            $cekDoc = DB::connection("sqlSrvBCKI")
                ->table('bank_note')
                ->where('run_number', $run_number)
                ->where('tr_type', $tr_typeDN)
                ->where('bank_acc', $faccount)
                ->where('tr_id', $bkm)
                ->first();

            $data = [
                'Voucher' => 1,
                'customer' => $cust_id
            ];

            if ($cekDoc) {
                DB::connection("sqlSrvBCKI")
                    ->table('bank_note')
                    ->where('run_number', $run_number)
                    ->where('tr_type', $tr_typeDN)
                    ->where('bank_acc', $faccount)
                    ->where('tr_id', $bkm)
                    ->update($data);
            }

            if (trim($ac_num != "")) {
                $this->serviceAcc->JrnlCustomerDeposit($office_id, $dp_id, $tahun4digit, getAuth($request)->user_id2);
            }

            return response()->json(['success' => true, 'message' => 'Data update successfully!'], 200);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/bcki/deposit/deleteDeposit",
     *     tags={"Deposit"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="dp_id", type="string", example="123456789"),    
     *  
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Delete Deposit",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function deleteDeposit(Request $request)
    {
        $office_id = '1';
        $tr_type = 'DP';
        $dp_id = $request->dp_id ?? '';

        try {
            // cek apakah DP Sudah dibuatkan Faktur Pajak
            $cekDPdiPajak = DB::connection("sqlSrvBCKI")
                ->table('dp_sales')
                ->where('dp_id', $dp_id)
                ->where('dp_pajak', '<>', '')
                ->first();
            if ($cekDPdiPajak) {
                return $this->sendError('Sudah dibuatkan Faktur Pajak DP', 'Error', 500);
            }

            $cekDoc = DB::connection("sqlSrvBCKI")
                ->table('dp_sales')
                ->where('dp_id', $dp_id)
                ->first();

            if ($cekDoc) {

                $dt_dp = $cekDoc->dt_dp ?? '';
                $tahun4digit =  Carbon::parse($dt_dp)->format('Y');
                $bkm = $cekDoc->bkm ?? '';

                if ($this->serviceAcc->ifAcctClosed($dt_dp, $request)) {
                    return $this->sendError("Periode ini sudah closing", 'Error', 500);
                }
                if ($cekDoc->used_amt > 0) {
                    return $this->sendError('Sudah Terpakai Tidak Bisa Dihapus', 'Error', 500);
                } else {
                    DB::connection("sqlSrvBCKI")
                        ->table('dp_sales')
                        ->where('dp_id', $dp_id)
                        ->where('office_id', '1')
                        ->where('tr_type', 'DP')
                        ->delete();
                }
                $cekDoc2 = DB::connection("sqlSrvBCKI")
                    ->table('bank_note')
                    ->where('tr_id', $bkm)
                    ->first();

                $data = [
                    'Voucher' => 0,
                    'customer' => ''
                ];

                if ($cekDoc2) {
                    DB::connection("sqlSrvBCKI")
                        ->table('bank_note')
                        ->where('tr_id', $bkm)
                        ->update($data);
                }
                $this->serviceAcc->JrnlCustomerDeposit($office_id, $dp_id, $tahun4digit, getAuth($request)->user_id2);
                return response()->json(['success' => true, 'message' => 'Data deleted successfully!'], 200);
            }
            return response()->json(['success' => false, 'message' => 'Data not found!'], 404);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/bcki/deposit/getListPurchDeposit",
     *     tags={"Deposit"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="start_date", type="date", example="2023-01-01"),
     *             @OA\Property(property="end_date", type="date", example="2023-12-31"),
     *             @OA\Property(property="dp_id", type="string", example="123456789"),
     *             @OA\Property(property="vendor_id", type="string", example="123456789"),
     *             @OA\Property(property="sisa", type="string", example="Y"),
     * 
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List Purchase Deposit yang sudah di simpan",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getListPurchDeposit(Request $request)
    {
        try {
            $sqlWhere = 'a.office_id = 1 ';

            if ($request->dp_id) {
                $sqlWhere .= " and a.dp_id  = '$request->dp_id'";
            }
            if ($request->vendor_id) {
                $sqlWhere .= " and a.vendor_id  = '$request->vendor_id'";
            }
            if ($request->start_date) {
                $sqlWhere .= "and dt_dp  >= '$request->start_date'";
            }
            if ($request->end_date) {
                $sqlWhere .= "and dt_dp  <= '$request->end_date'";
            }
            if ($request->sisa == 'Y') {
                $sqlWhere .= " and dp_amt - dp_used > 0 ";
            }
            // Get List Deposit
            // $getListPurchDeposit = DB::connection("sqlSrvBCKI")
            //     ->select(DB::raw("select distinct dt_dp, rtrim(a.dp_id) dp_id, rtrim(a.vendor_id) vendor_id, vendor_name, dp_amt, a.inv_amt, a.Remark, 'H' baris, 
            //                         case when c.dp_id is not null then 'Y' else 'N' end as split, 
            //                         f.run_number, rtrim(f.bank_acc) as faccount, rtrim(a.ac_num) as ac_num, rtrim(a.ac_num_dp) as ac_num_dp, bkm, 
            //                         a.dt_cek, a.cek_id, rtrim(g.bank_name) bank_name, h.ac_name, a.curr_id ,
            //                         concat(rtrim(b.address1), rtrim(b.address2), CASE WHEN rtrim(b.city) = '' THEN '' ELSE ', ' END , rtrim(b.city), CASE WHEN rtrim(b.prov) = '' THEN '' ELSE ', ' END ,rtrim(b.prov)) as alamat_vendor, 
            //                         rtrim(b.city) as city_vendor 
            //                     from purc_down_payment a
            //                         inner join vendor b on a.office_id = b.office_id AND a.vendor_id = b.vendor_id
            //                         inner join account g on g.ac_num = a.ac_num
            //                         inner join g_ledger h on g.ac_num = h.ac_num
            //                         left outer join purc_dp_inv c on a.dp_id=c.dp_id    
            //                         left outer join bank_note f ON  a.bkm=f.tr_id
            //                     where $sqlWhere                                
            //                     union all
            //                     select c.dt_pay, rtrim(a.dp_id) dp_id, inv_id, c.pay_id,  0, c.pay_amt,'', 'D' baris,  'N'  as split
            //                         , 0 run_number, '' bank_acc, '' ac_num, '' ac_num_dp, '' bkm, '' dt_cek, '' cek_id, '' bank_name, '' ac_name, '' curr_id,
            //                         '' alamat, '' city_vendor
            //                     from purc_down_payment a
            //                         inner join vendor b on a.office_id = b.office_id AND a.vendor_id = b.vendor_id
            //                         inner join purc_dp_inv c on a.dp_id=c.dp_id 
            //                         inner join purc_pay_mast d on c.office_id = d.office_id AND c.pay_id = d.pay_id 
            //                         left outer join bank_note f ON  a.bkm=f.tr_id 
            //                     where $sqlWhere                                
            //                         order by dp_id "));
             $getListPurchDeposit = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select distinct dt_dp, rtrim(a.dp_id) dp_id, rtrim(a.vendor_id) vendor_id, vendor_name, dp_amt, a.dp_used inv_amt, a.Remark, 'H' baris, 
                                    case when c.dp_id is not null then 'Y' else 'N' end as split, 
                                    f.run_number, rtrim(f.bank_acc) as faccount, rtrim(a.ac_num) as ac_num, rtrim(a.ac_num_dp) as ac_num_dp, bkm, 
                                    a.dt_cek, a.cek_id, rtrim(g.bank_name) bank_name, h.ac_name, a.curr_id ,
                                    concat(rtrim(b.address1), rtrim(b.address2), CASE WHEN rtrim(b.city) = '' THEN '' ELSE ', ' END , rtrim(b.city), CASE WHEN rtrim(b.prov) = '' THEN '' ELSE ', ' END ,rtrim(b.prov)) as alamat_vendor, 
                                    rtrim(b.city) as city_vendor 
                                from purc_down_payment a
                                    inner join vendor b on a.office_id = b.office_id AND a.vendor_id = b.vendor_id
                                    inner join account g on g.ac_num = a.ac_num
                                    inner join g_ledger h on g.ac_num = h.ac_num
                                    left outer join purc_dp_inv c on a.dp_id=c.dp_id    
                                    left outer join bank_note f ON  a.bkm=f.tr_id
                                where $sqlWhere                                
                                union all
                                select d.dt_pay, rtrim(a.dp_id) dp_id, inv_id, c.pay_id,  0, c.pay_amt,'', 'D' baris,  'N'  as split
                                    , 0 run_number, '' bank_acc, '' ac_num, '' ac_num_dp, '' bkm, '' dt_cek, '' cek_id, '' bank_name, '' ac_name, '' curr_id,
                                    '' alamat, '' city_vendor
                                from purc_down_payment a
                                    inner join vendor b on a.office_id = b.office_id AND a.vendor_id = b.vendor_id
                                    inner join purc_pay_mast d on a.dp_id=d.dp_id
                                    inner join purc_pay_item c on d.pay_id=c.pay_id and d.office_id=c.office_id
                                    left outer join bank_note f ON  a.bkm=f.tr_id 
                                where $sqlWhere                                
                                    order by dp_id "));

            //return response()->json(['getListKBHdr' => $getListKBHdr]);
            return $this->sendResponse($getListPurchDeposit, 'Successfully get List Deposit');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/bcki/deposit/getListPInvoice",
     *     tags={"Deposit"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="dp_id", type="string", example="123456789"),    
     *   
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List Invoice yang di posting dari deposit",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getListPInvoice(Request $request)
    {
        try {
            $sqlWhere = '1=1 ';
            $sqlWhere .= " and a.dp_id  = '$request->dp_id'";
            // Get List Invoice
            $getListPInvoice = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select distinct a.dt_dp,rtrim(j.inv_id) inv_id,rtrim(c.pay_id) pay_id,rtrim(voucher) voucher,rtrim(b.vendor_name) vendor_name,
                                    rtrim(isnull(k.inv_id,'')) BPH, rtrim(isnull(k.vendor_inv,'')) Ext_Inv, k.dt_inv inv_date, k.dt_due due_date, isnull(j.pay_amt ,0) pay_amt
                                from purc_down_payment a
                                    inner join vendor b on a.office_id = b.office_id AND a.vendor_id = b.vendor_id
                                    inner join purc_dp_inv c on a.dp_id=c.dp_id 
                                    inner join purc_pay_mast d on c.office_id = d.office_id AND c.pay_id = d.pay_id 
                                    left outer join purc_pay_item j on d.office_id=j.office_id and d.pay_id=j.pay_id 
                                    left outer join purc_inv_mast k on j.inv_id = k.inv_id 
                                    left outer join bank_note f ON  a.bkm=f.tr_id  
                                where $sqlWhere
                                order by a.dt_dp "));

            //return response()->json(['getListKBHdr' => $getListKBHdr]);
            return $this->sendResponse($getListPInvoice, 'Successfully get List Invoice');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    function genPurchDPID($tgl)
    {
        $prefix = 'FAA/PDP/';
        $year = Carbon::parse($tgl)->format('y');   // contoh: 25
        $month =  Carbon::parse($tgl)->format('m');  // contoh: 05

        // Ambil record terakhir berdasarkan tahun dan bulan
        $last = DB::connection("sqlSrvBCKI")
            ->table('purc_down_payment')
            // ->selectRaw("top 1 tr_id")
            ->whereRaw("SUBSTRING(dp_id, 9, 2) = ?", [$year])
            ->whereRaw("SUBSTRING(dp_id, 11, 2) = ?", [$month])
            ->orderByDesc('dp_num')
            ->first('dp_num');
        // dd($last);

        if ($last) {
            $newNumber =  $last->dp_num + 1;
        } else {
            $newNumber = 1;
        }

        // Format ke 3 digit
        $formattedNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);
        $data = [
            'tr_id' => "{$prefix}{$year}{$month}/{$formattedNumber}",
            'dp_num' => $newNumber
        ];
        return $data;
    }

    /**
     * @OA\Post(
     *     path="/api/bcki/deposit/savePDeposit",
     *     tags={"Deposit"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="dp_id", type="string", example="123456789"),    
     *  
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Simpan Deposit",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function savePDeposit(Request $request)
    {
        $office_id = '1';
        $dp_id = trim($request->dp_id ?? '');
        $dt_dp = $request->dt_dp ?? '';
        $tahun4digit =  Carbon::parse($dt_dp)->format('Y');
        $vendor_id = $request->vendor_id ?? '';
        $curr_id = 'IDR';
        $Rate = 1;
        $ac_num = $request->ac_num ?? '';
        $ac_num_DP = $request->ac_num_DP ?? '';
        $dp_amt = $request->dp_amt ?? '';
        //$inv_id = $request->inv_id ?? '';
        $Remark = $request->Remark ?? '';
        $bkm = $request->bkm ?? '';
        $run_number = $request->run_number ?? '';
        //$dp_pajak = $request->dp_pajak ?? '';
        $tr_typeDN =  $request->tr_typeDN ?? '';
        $faccount =  $request->faccount ?? '';
        $cek_id =  $request->cek_id ?? '';
        $dt_cek =  $request->dt_cek ?? '';

        if ($this->serviceAcc->ifAcctClosed($dt_dp, $request)) {
            return $this->sendError("Periode ini sudah closing", 'Error', 500);
        }
        if (!$ac_num) {
            return $this->sendError("AC NUM harus di isi", 'Error', 500);
        }
        if (!$ac_num_DP) {
            return $this->sendError("AC NUM DP harus di isi", 'Error', 500);
        }
        try {
            if (!$dp_id) {
                // cek apakah ada Voucher yang sudah digunakan
                $cekTRIDUse = DB::connection("sqlSrvBCKI")
                    ->table('bank_note')
                    ->where('tr_id', $bkm)
                    ->where('tr_type', 'D')
                    ->where('voucher', '<>', 1)
                    ->first('voucher');
                if ($cekTRIDUse && $cekTRIDUse->voucher > 0) {
                    return $this->sendError('Voucher sudah digunakan, silahkan gunakan Voucher lain', 'Error', 500);
                }

                // Generate DP ID
                $genID = $this->genPurchDPID($dt_dp);
                $dp_id = $genID['tr_id'];
                $dp_num = $genID['dp_num'];
            }

            $cekDoc = DB::connection("sqlSrvBCKI")
                ->table('purc_down_payment')
                ->where('dp_id', $dp_id)
                ->first();

            $data = [
                'office_id' => $office_id,
                'dp_id' => $dp_id,
                'dt_dp' => $dt_dp,
                'vendor_id' => $vendor_id,
                'curr_id' => $curr_id,
                'Rate' => $Rate,
                'ac_num' => $ac_num,
                'ac_num_DP' => $ac_num_DP,
                'dp_amt' => $dp_amt,
                'dp_num' => $dp_num ?? $cekDoc->dp_num,
                'Remark' => $Remark,
                'bkm' => $bkm,
                'dt_modified' => Carbon::now(),
                'user_id' => getAuth($request)->user_id2,
                'cek_id' => $cek_id,
                'dt_cek' => $dt_cek
            ];

            if ($cekDoc) {
                DB::connection("sqlSrvBCKI")
                    ->table('purc_down_payment')
                    ->where('dp_id', $dp_id)
                    ->update($data);
            } else {
                //$printId = $this->genPrintId();
                //$data['stat'] = "O";
                $data['dt_created'] = Carbon::now();

                DB::connection("sqlSrvBCKI")
                    ->table('purc_down_payment')
                    ->insert($data);
            }

            $cekDoc = DB::connection("sqlSrvBCKI")
                ->table('bank_note')
                ->where('run_number', $run_number)
                ->where('tr_type', $tr_typeDN)
                ->where('bank_acc', $faccount)
                ->where('tr_id', $bkm)
                ->first();

            $data = [
                'Voucher' => 1,
                'customer' => $vendor_id
            ];

            if ($cekDoc) {
                DB::connection("sqlSrvBCKI")
                    ->table('bank_note')
                    ->where('run_number', $run_number)
                    ->where('tr_type', $tr_typeDN)
                    ->where('bank_acc', $faccount)
                    ->where('tr_id', $bkm)
                    ->update($data);
            }

            if (trim($ac_num != "")) {
                $this->serviceAcc->JrnlPurcDP($office_id, $dp_id, $tahun4digit, getAuth($request)->user_id2);
            }

            return response()->json(['success' => true, 'message' => 'Data update successfully!'], 200);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/bcki/deposit/deleteDeposit",
     *     tags={"Deposit"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="dp_id", type="string", example="123456789"),    
     *  
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Delete Deposit",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function deletePDeposit(Request $request)
    {
        $office_id = '1';
        //$tr_type = 'DP';
        $dp_id = $request->dp_id ?? '';

        try {
            // cek apakah DP Sudah dibuatkan Faktur Pajak
            // $cekDPdiPajak = DB::connection("sqlSrvBCKI")
            //     ->table('purc_down_payment')
            //     ->where('dp_id', $dp_id)
            //     ->first();
            // if ($cekDPdiPajak) {
            //     return $this->sendError('Sudah dibuatkan Faktur Pajak DP', 'Error', 500);
            // }

            $cekDoc = DB::connection("sqlSrvBCKI")
                ->table('purc_down_payment')
                ->where('dp_id', $dp_id)
                ->first();

            if ($cekDoc) {

                $dt_dp = $cekDoc->dt_dp ?? '';
                $tahun4digit =  Carbon::parse($dt_dp)->format('Y');
                $bkm = $cekDoc->bkm ?? '';

                if ($this->serviceAcc->ifAcctClosed($dt_dp, $request)) {
                    return $this->sendError("Periode ini sudah closing", 'Error', 500);
                }
                if ($cekDoc->dp_used > 0) {
                    return $this->sendError('Sudah Terpakai Tidak Bisa Dihapus', 'Error', 500);
                } else {
                    DB::connection("sqlSrvBCKI")
                        ->table('purc_down_payment')
                        ->where('dp_id', $dp_id)
                        ->where('office_id', '1')
                        ->delete();
                }
                $cekDoc2 = DB::connection("sqlSrvBCKI")
                    ->table('bank_note')
                    ->where('tr_id', $bkm)
                    ->first();

                $data = [
                    'Voucher' => 0,
                    'customer' => ''
                ];

                if ($cekDoc2) {
                    DB::connection("sqlSrvBCKI")
                        ->table('bank_note')
                        ->where('tr_id', $bkm)
                        ->update($data);
                }
                $this->serviceAcc->JrnlPurcDP($office_id, $dp_id, $tahun4digit, getAuth($request)->user_id2);
                return response()->json(['success' => true, 'message' => 'Data deleted successfully!'], 200);
            }
            return response()->json(['success' => false, 'message' => 'Data not found!'], 404);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
}
