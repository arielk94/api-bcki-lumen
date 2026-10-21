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

class KasbonController extends Controller
{
    //

    protected $serviceAcc;

    public function __construct(AccountingService $serviceAcc)
    {
        $this->serviceAcc = $serviceAcc;
    }

    public function getKasbonSPK(Request $request)
    {
        $sqlWhere = '1=1';
        $vendor_id = $request->vendor_id;
        $active_flag = $request->active_flag;

        if (empty($vendor_id)) {
            return $this->sendError("Vendor ID Kosong", 'Error', 500);
        }
        if (empty($active_flag)) {
            $sqlWhere = $sqlWhere . " and sh.active_flag = 'Y'";
        } else {
            $sqlWhere = $sqlWhere . " and sh.active_flag = " . "'" . $active_flag . "'";
        }

        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('spk_hdr as sh')
                ->selectRaw("
                        sh.id_spk,
                        sh.pihak_2 as vendor_id,
                        TRIM(v.vendor_name) as vendor_name,
                        sh.id_site,
                        st.site_name,
                        sh.id_cluster,
                        sc.cluster_name,
                        sh.id_house_unit,
                        hu.house_name,
                        sh.active_flag,
                        sh.status,
                        sh.id_cetak,
                        sh.id_spb,
                        sh.amt,
                        sh.amt_include,
                        sh.amt_exclude,
                        sh.spk_type,
                        ac.str2 as w_spk_type, 
                        (select TOP 1 vvcl.credit_limit from view_vendor_credit_limit_spk vvcl where vvcl.id_spk = sh.id_spk AND vvcl.status_closing = 'N' ORDER BY vvcl.com_id ASC) AS credit_limit
                    ")
                ->when($vendor_id, function ($query, $vendor_id) {
                    return $query->where('sh.pihak_2', $vendor_id);
                })
                ->leftjoin('house_unit as hu', 'sh.id_house_unit', '=', 'hu.id_house')
                ->leftjoin('site_cluster as sc', 'hu.id_cluster', '=', 'sc.id_cluster')
                ->leftjoin('site as st', 'sc.id_site', '=', 'st.id_site')
                ->leftjoin('vendor as v', 'sh.pihak_2', '=', 'v.vendor_id')
                ->leftJoin('appl_constant as ac', function ($join) {
                    $join->on('sh.spk_type', '=', 'ac.str1')
                        ->where('ac.key_code', '=', 'SPK_TYPE');
                })
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($result, 'Successfully get Kasbon SPK');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getKasbonDetail(Request $request)
    {
        $datas = DB::connection("sqlSrvBCKI")
            ->table('kb_dtl')
            ->selectRaw("
                    ROW_NUMBER() OVER (ORDER BY kb_dtl.tr_id) AS no,
                    trim(kb_dtl.office_id) as office_id,
                    trim(branch_office.office) as office, 
                    trim(branch_office.address) as address, 
                    trim(branch_office.city) as city, 
                    trim(kb_dtl.tr_id) as tr_id,
                    trim(kb_dtl.article_id) as article_id, 
                    trim(article.description) as description,                
                    kb_dtl.seq_num,
                    kb_dtl.qty,
                    kb_dtl.price,
                    kb_dtl.amt,
                    kb_dtl.stat,
                    trim(kb_dtl.remark) as remark,
                    kb_dtl.dt_created,
                    kb_dtl.dt_modified,
                    trim(kb_dtl.user_id) as user_id,
                    trim(kb_dtl.ac_num) as ac_num
                ")
            ->leftJoin('branch_office', 'kb_dtl.office_id', '=', 'branch_office.office_id')
            ->leftJoin('article', 'kb_dtl.article_id', '=', 'article.article_id')
            ->where('kb_dtl.tr_id', $request->tr_id)
            ->get();

        return $this->sendResponse($datas, 'Successfully get PR Detail');
    }

    public function storeKasbon(Request $request)
    {
        $tr_date = $request->tr_date;
        $str1 = $request->str1;
        $amount = $request->amount;
        $vendor_id = $request->vendor_id;
        $remark = $request->remark;
        $id_spk = $request->id_spk;

        // dd($image);
        if (empty($tr_date)) {
            return $this->sendError("Transaction Date Kosong", 'Error', 500);
        }
        if (empty($str1)) {
            return $this->sendError("Tipe Kasbon Kosong", 'Error', 500);
        } else {
            if ($str1 == 'G') {
                $kode_artikel = '99990001';
            } elseif ($str1 == 'M') {
                $kode_artikel = '99990002';
            }
        }
        if (empty($amount)) {
            return $this->sendError("Jumlah Kasbon Kosong", 'Error', 500);
        }
        if (empty($vendor_id)) {
            return $this->sendError("Vendor ID Kosong", 'Error', 500);
        }
        if (empty($id_spk)) {
            return $this->sendError("ID SPK Kosong", 'Error', 500);
        }

        try {
            DB::connection('sqlSrvBCKI')->beginTransaction();

            $tr_id = $this->generateTrId($request);
            // dd($tr_id);

            $save_hdr = DB::connection('sqlSrvBCKI')
                ->table('kb_hdr')
                ->insert([
                    'office_id' => 1,
                    'tr_id' => $tr_id,
                    'tr_type' => 'EX',
                    'kb_num' => 1,
                    'vendor_id' => $vendor_id,
                    'dt_kb' => $tr_date,
                    'kb_amt' => $amount,
                    'remark' => $remark ?? '',
                    'stat' => 'O',
                    'dt_created' => Carbon::now(),
                    'user_id' => getAuth($request)->user_id2,
                    'tr_id_ref' => $id_spk
                ]);

            $save_dtl = DB::connection('sqlSrvBCKI')
                ->table('kb_dtl')
                ->insert([
                    'office_id' => 1,
                    'tr_id' => $tr_id,
                    'seq_num' => 1,
                    'article_id' => $kode_artikel,
                    'qty' => 1,
                    'price' => $amount,
                    'amt' => $amount,
                    'stat' => 'O',
                    'remark' => $remark ?? '',
                    'dt_created' => Carbon::now(),
                    'user_id' => getAuth($request)->user_id2
                ]);

            if ($save_hdr && $save_dtl) {
                DB::connection('sqlSrvBCKI')->commit();
                return $this->sendResponse(true, 'Successfully saved KasBon');
            } else {
                DB::connection('sqlSrvBCKI')->rollBack();
                return $this->sendError('Failed to save either header or detail data.', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function generateTrId()
    {
        $prefix = 'KBE';
        $year = date('y');   // contoh: 25
        $month = date('m');  // contoh: 05

        // Ambil record terakhir berdasarkan tahun (bukan bulan)
        // $last = DB::connection("sqlSrvBCKI")
        //             ->table('kb_hdr')
        //             ->whereRaw("SUBSTRING(tr_id, 5, 2) = ?", [$year])
        //             ->whereRaw("SUBSTRING(tr_id, 1, 3) = 'KBE'")
        //             ->orderByDesc('tr_id')
        //             ->first('tr_id');

        // Ambil record terakhir berdasarkan bulan
        $last = DB::connection("sqlSrvBCKI")
            ->table('kb_hdr')
            ->whereRaw("SUBSTRING(tr_id, 5, 2) = ?", [$year])     // ambil YY
            ->whereRaw("SUBSTRING(tr_id, 8, 2) = ?", [$month])    // ambil MM
            ->whereRaw("SUBSTRING(tr_id, 1, 3) = 'KBE'")          // pastikan prefix
            ->orderByDesc('tr_id')
            ->first(['tr_id']);
        // dd($last);

        // Susun nomor baru berdasarkan tahun (bukan bulan)
        // if ($last) {
        //     $lastParts = explode('/', $last->tr_id);
        //     $lastYear = $lastParts[1];          // contoh: 25
        //     $lastNumber = (int) $lastParts[3];  // contoh: 001

        //     $newNumber = $lastYear == $year ? $lastNumber + 1 : 1;
        // } else {
        //     $newNumber = 1;
        // }

        // Susun nomor baru berdasarkan bulan
        if ($last) {
            $lastParts = explode('/', $last->tr_id);
            $lastYear = $lastParts[1];          // contoh: 25
            $lastMonth = $lastParts[2];         // contoh: 06
            $lastNumber = (int) $lastParts[3];  // contoh: 001

            // Reset nomor jika bulan berbeda
            $newNumber = ($lastYear == $year && $lastMonth == $month) ? $lastNumber + 1 : 1;
        } else {
            $newNumber = 1;
        }

        // Format ke 3 digit
        $formattedNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);

        return "{$prefix}/{$year}/{$month}/{$formattedNumber}";
    }

    public function getKasbonVendor(Request $request)
    {
        $sqlWhere = '1=1';
        $vendor_id = $request->vendor_id;
        $active_flag = $request->active_flag;


        if (empty($active_flag)) {
            $sqlWhere = $sqlWhere . " and sh.active_flag = 'Y'";
        } else {
            $sqlWhere = $sqlWhere . " and sh.active_flag = " . "'" . $active_flag . "'";
        }
        if (empty($vendor_id)) {
        } else {
            $sqlWhere = $sqlWhere . " and v.vendor_id = " . "'" . $vendor_id . "'";
        }

        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('vendor as v')
                ->selectRaw("
                        DISTINCT
                        TRIM(v.vendor_id) as vendor_id,
                        TRIM(v.vendor_name) as vendor_name,
                        TRIM(v.address1) as address1,
                        TRIM(v.address2) as address2,
                        TRIM(v.city) as city
                    ")
                ->leftjoin('spk_hdr as sh', 'sh.pihak_2', '=', 'v.vendor_id')
                ->whereRaw($sqlWhere)
                ->orderBy('vendor_name', 'asc')
                ->get();

            return $this->sendResponse($result, 'Successfully get Vendor Only Those Who Have SPK');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function genKBID($id, $tgl)
    {
        if ($id == 'IN') {
            $prefix = 'KBI';
        } else {
            $prefix = 'KBE';
        }
        $year = Carbon::parse($tgl)->format('y');   // contoh: 25
        $month =  Carbon::parse($tgl)->format('m');  // contoh: 05

        // Ambil record terakhir berdasarkan tahun dan bulan
        $last = DB::connection("sqlSrvBCKI")
            ->table('kb_hdr')
            // ->selectRaw("top 1 tr_id")
            ->whereRaw("SUBSTRING(tr_id, 5, 2) = ?", [$year])
            ->whereRaw("SUBSTRING(tr_id, 8, 2) = ?", [$month])
            // ->where('tr_type', $id)
            ->orderByDesc('tr_id')
            ->first('tr_id');
        // dd($last);

        if ($last) {
            $lastParts = explode('/', $last->tr_id);
            $lastNumber = (int) $lastParts[3];  // contoh: 001

            $newNumber =  $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        // Format ke 3 digit
        $formattedNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);

        return "{$prefix}/{$year}/{$month}/{$formattedNumber}";
    }

    public function getLastSeqKB($tr_id)
    {
        // Ambil record terakhir berdasarkan tahun dan bulan
        $last = DB::connection("sqlSrvBCKI")
            ->table('kb_dtl')
            ->where('tr_id', $tr_id)
            ->orderByDesc('seq_num')
            ->first('seq_num');
        // dd($last);

        if ($last) {
            $lastParts = $last->seq_num;

            $newNumber = $lastParts + 1;
        } else {
            $newNumber = 1;
        }

        return "{$newNumber}";
    }

    public function getListKBHdr(Request $request)
    {
        try {

            $sqlWhere = '1=1 ';
            $sqlWhere .= " and a.office_id = '$request->office_id'";
            $sqlWhere .= " and a.tipe = '$request->tr_type'"; // 'ININTERNAL' or 'EX_MONEY' or 'EX_MAT'

            if ($request->tr_id) {
                $sqlWhere .= "and a.tr_id  = '$request->tr_id'";
            }
            if ($request->req_id) {
                $sqlWhere .= "and a.req_id  = '$request->req_id'";
            }
            if ($request->dept_id) {
                $sqlWhere .= "and a.dept_id  = '$request->dept_id'";
            }
            if ($request->spk_id) {
                $sqlWhere .= "and a.spk_id2 = '$request->spk_id'";
            }
            if ($request->start_date) {
                $sqlWhere .= "and a.dt_kb  >= '$request->start_date'";
            }
            if ($request->end_date) {
                $sqlWhere .= "and a.dt_kb  <= '$request->end_date'";
            }
            if ($request->start_cdate) {
                $sqlWhere .= "and a.dt_complete  >= '$request->start_cdate'";
            }
            if ($request->end_cdate) {
                $sqlWhere .= "and a.dt_complete  <= '$request->end_cdate'";
            }

            if ($request->stat_jurnal == 'Y') {
                $sqlWhere .= "and a.ac_num  != ''";
            } else if ($request->stat_jurnal == 'N') {
                $sqlWhere .= "and a.ac_num  = ''";
            }

            if ($request->stat) {
                // $sqlWhere .= "and a.stat  = '$request->stat'";
                if ($request->stat == 'A1') {
                    $sqlWhere .= "and a.dt_aprv1 != '1900-01-01 00:00:00.000'";
                } elseif ($request->stat == 'A2') {
                    $sqlWhere .= "and a.dt_aprv2 != '1900-01-01 00:00:00.000' and a.dt_aprv1 != '1900-01-01 00:00:00.000'";
                } elseif ($request->stat == 'A3') {
                    $sqlWhere .= "and a.dt_aprv3 != '1900-01-01 00:00:00.000' and a.dt_aprv2 != '1900-01-01 00:00:00.000' and a.dt_aprv1 != '1900-01-01 00:00:00.000'";
                } elseif ($request->stat == 'N') {
                    $sqlWhere .= "and a.dt_aprv1 = '1900-01-01 00:00:00.000' and a.dt_aprv2 = '1900-01-01 00:00:00.000' and a.dt_aprv1 = '1900-01-01 00:00:00.000'";
                }
            }
            // if($request->stat_dn){ //EX belum jadi debit note, EO sudah jadi debit note tapi belum posting, EC sudah jadi debit note dan sudah posting
            if ($request->stat_dn == 'EX') {
                $sqlWhere .= " and a.dt_confirm  = '1900-01-01 00:00:00.000'";
            } else if ($request->stat_dn == 'EO') {
                $sqlWhere .= " and c.pay_id  = ' ' and a.dt_confirm  != '1900-01-01 00:00:00.000'";
            } else if ($request->stat_dn == 'EC') {
                $sqlWhere .= " and c.pay_id  != ' ' and a.dt_confirm  != '1900-01-01 00:00:00.000'";
            }
            // }

            $getListKBHdr = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select a.office_id, a.tipe, a.tr_type, 
                                    a.dt_kb, a.tr_id, 
                                    a.req_id, a.req_name, 
                                    a.dept_id, a.dept_name, 
                                    a.kb_amt, a.remark, 
                                    a.spk_id, a.site_name, 
                                    a.cluster_name, a.house_name, 
                                    a.dt_aprv1, a.user_aprv1, 
                                    a.dt_aprv2, a.user_aprv2, 
                                    a.dt_aprv3, a.user_aprv3, 
                                    a.dt_aprv4, a.user_aprv4, 
                                    a.ac_num, a.amt_paid, 
                                    a.dt_complete, a.spk_id2, case when year(a.dt_aprv4) > 1900 then 'Approve4'
                                                                    when year(a.dt_aprv3) > 1900 then 'Approve3' 
                                                                    when year(a.dt_aprv2) > 1900 then 'Approve2' 
                                                                    when year(a.dt_aprv1) > 1900 then 'Approve1' else 'No' end as stat_aprv,
                                    a.stat, a.dt_confirm, isnull(c.pay_id,'') as pay_id ,
                                    CASE
                                        WHEN c.doc_id IS NULL or c.doc_id='' THEN 'N'
                                        ELSE 'Y'
                                    END AS sudahDN
                                    from v_kasbon_list a
                                    left join credit_note b on a.tr_id = b.tr_id
                                    left join bank_note c on b.bk = c.tr_id
                                where $sqlWhere order by a.office_id ,a.tr_type ,a.dt_kb,a.tr_id "));

            return $this->sendResponse($getListKBHdr, 'Successfully get List KB Header');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getListKBDtl(Request $request)
    {
        try {

            $sqlWhere = '1=1 ';
            $sqlWhere .= "and a.office_id = '$request->office_id'";
            $sqlWhere .= "and a.tr_id  = '$request->tr_id'";

            $getListKBDtl = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("
                                select a.office_id, 
                                        a.tr_id, 
                                        c.idemployee, d.employee_name,
                                        d.dept_id, upper(d.dept_name) dept_name,
                                        seq_num, 
                                        a.article_id, 
                                        case when b.description is null then upper(a.remark) else upper (b.description ) end as description,
                                        qty, 
                                        price, 
                                        amt, 
                                        a.stat, 
                                        a.article_id,
                                        b.description as article_name,
                                        upper(a.remark) remark,
                                        case when e.ac_name is null then '' else upper(rtrim(e.ac_name) ) + ' (' +  rtrim(e.ac_num)  + ')' end as ac_num_hdr,
                                        case when f.ac_name is null then '' else upper(rtrim(f.ac_name) ) + ' (' +  rtrim(f.ac_num)  + ')' end as ac_num_dtl
                                from kb_dtl a 
                                left outer join article b on a.article_id= b.article_id
                                inner join kb_hdr c on a.tr_id=c.tr_id 
                                inner join kb_mast d on d.idemployee =c.idemployee 
                                left outer join g_ledger e on c.ac_num=e.ac_num  
                                left outer join g_ledger f on a.ac_num=e.ac_num 
                                where $sqlWhere
                                order by  a.office_id, a.tr_id,seq_num
                                "));

            //return response()->json(['getListKBDtl' => $getListKBDtl]);
            return $this->sendResponse($getListKBDtl, 'Successfully get List KB Detail');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function saveKasBonPengembalian(Request $request)
    {
        try {

            //DB::connection('sqlSrvBCKI')->beginTransaction();
            // save hdr
            $tr_id = $request->tr_id;
            $office_id = $request->office_id;
            $amt_paid = $request->amt_paid ?? 0;

            $cekDoc = DB::connection("sqlSrvBCKI")
                ->table('kb_hdr')
                ->where('tr_id', $tr_id)
                ->first();

            $data = [
                'office_id' => $office_id,
                'tr_id' => $tr_id,
                'stat' => "C"
            ];

            if ($cekDoc) {
                //$printId = $cekDoc->print_id;
                $data['amt_paid'] =  (float)$amt_paid;
                $data['dt_complete'] = Carbon::now();

                DB::connection("sqlSrvBCKI")
                    ->table('kb_hdr')
                    ->where('tr_id', $tr_id)
                    ->update($data);
            }
            //DB::connection('sqlSrvBCKI')->commit();

            return $this->sendResponse($data, 'Successfully save KasBon');
            //return response()->json(['return' => $data]);
        } catch (\Throwable $e) {
            //DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function deleteKasBonPengembalian($tr_id, $office_id)
    {
        try {

            DB::connection('sqlSrvBCKI')->beginTransaction();
            // save hdr
            $cekDoc = DB::connection("sqlSrvBCKI")
                ->table('kb_hdr')
                ->where('tr_id', $tr_id)
                ->first();

            $data = [
                'office_id' => $office_id,
                'tr_id' => $tr_id,
                'stat' => "O"
            ];

            if ($cekDoc) {
                //$printId = $cekDoc->print_id;
                $data['amt_paid'] = 0;
                $data['dt_complete'] = "1900-01-01 00:00:00";

                DB::connection("sqlSrvBCKI")
                    ->table('kb_hdr')
                    ->where('tr_id', $tr_id)
                    ->update($data);
            }
            DB::connection('sqlSrvBCKI')->commit();

            return $this->sendResponse($data, 'Successfully delete Pengembalian');
            //return response()->json(['return' => $data]);
        } catch (\Throwable $e) {
            DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function saveKasBon(Request $request)
    {
        $tr_id = $request->tr_id ?? '';
        $office_id = $request->office_id ?? '';
        $tr_type = $request->tr_type ?? '';
        $vendor_id = $request->vendor_id ?? ''; // ext Mat & ext mon

        $idemployee = $request->idemployee ?? ''; // in
        $employeeName = $request->employeeName ?? '';
        $dept_id = $request->dept_id ?? ''; // in
        $dept_name = $request->dept_name ?? ''; // in

        $dt_kb = $request->dt_kb ?? '';
        $note = $request->note ?? '';
        $tr_id_ref = $request->tr_id_ref ?? ''; // ext Mat ( SPK )
        $ac_num = $request->ac_num ?? ''; // in ext jurnal
        $tahun4digit = $dt_kb ? Carbon::parse($dt_kb)->format('Y') : '';

        $seq_num =  $request->seq_num ?? '';
        $article_id =  $request->article_id ?? ''; // ext Mat
        $qty =  $request->qty ?? 0; // ext Mat
        $price =  $request->price ?? 0;
        // $amt  =  $request->amt ?? ''; 
        $amt =  $tr_type == "IN" ? $request->amt : $qty * $price;
        $remark  =  $request->remark ?? '';

        $idemployeeLama = '';

        $prevAmt = 0;

        try {
            if (!$tr_id) {
                $tr_id = $this->genKBID($tr_type, $dt_kb);
            }
            if ($seq_num <= 0) {
                $seq_num  = $this->getLastSeqKB($tr_id);
            }
            DB::connection('sqlSrvBCKI')->beginTransaction();

            if ((trim($tr_type) != "IN" && trim($ac_num) != "")) {
                // gak lapo lapo
            } else {
                // save Dtl
                $cekDoc = DB::connection("sqlSrvBCKI")
                    ->table('kb_dtl')
                    ->where('tr_id', $tr_id)
                    ->where('seq_num', $seq_num)
                    ->first();

                $data = [
                    'office_id' => $office_id,
                    'tr_id' => $tr_id,
                    'seq_num' => $seq_num,
                    'article_id' => $article_id,
                    'qty' => $tr_type == "IN" ? 1 : $qty,
                    'price' => $tr_type == "IN" ? $amt : $price,
                    'amt' =>  $tr_type == "IN" ? $amt : $qty * $price,
                    'remark' => $remark,
                ];

                if ($cekDoc) {
                    $prevAmt = $cekDoc->amt;
                    $data['dt_modified'] = Carbon::now();
                    $data['user_id'] = getAuth($request)->user_id2;

                    DB::connection("sqlSrvBCKI")
                        ->table('kb_dtl')
                        ->where('tr_id', $tr_id)
                        ->where('seq_num', $seq_num)
                        ->update($data);
                } else {
                    //$printId = $this->genPrintId();
                    $data['stat'] = "O";
                    $data['dt_created'] = Carbon::now();
                    $data['dt_modified'] = Carbon::now();
                    $data['user_id'] = getAuth($request)->user_id2;

                    DB::connection("sqlSrvBCKI")
                        ->table('kb_dtl')
                        ->insert($data);
                }
            }

            // save hdr
            $cekDoc = DB::connection("sqlSrvBCKI")
                ->table('kb_hdr')
                ->where('tr_id', $tr_id)
                ->first();

            $lastParts = explode('/', $tr_id);
            $lastNumber = (int) $lastParts[3];

            $data = [
                'office_id' => $office_id,
                'tr_id' => $tr_id,
                'tr_type' => $tr_type,
                'kb_num' => $lastNumber,
                'vendor_id' => $vendor_id,
                'idemployee' => $idemployee,
                'dt_kb' => $dt_kb,
                'remark' => $note,
                'tr_id_ref' => $tr_id_ref,
                'ac_num' => $ac_num
            ];

            if ($cekDoc) {
                $idemployeeLama = $cekDoc->idemployee;
                $data['kb_amt'] = (float)$cekDoc->kb_amt - (float)$prevAmt + (float)$amt;
                $data['dt_modified'] = Carbon::now();
                $data['user_id'] = getAuth($request)->user_id2;

                DB::connection("sqlSrvBCKI")
                    ->table('kb_hdr')
                    ->where('tr_id', $tr_id)
                    ->update($data);
            } else {
                //$printId = $this->genPrintId();
                $data['stat'] = "O";
                $data['kb_amt'] =  $amt;
                $data['dt_created'] = Carbon::now();
                $data['dt_modified'] = Carbon::now();
                $data['user_id'] = getAuth($request)->user_id2;

                DB::connection("sqlSrvBCKI")
                    ->table('kb_hdr')
                    ->insert($data);
            }
            DB::connection('sqlSrvBCKI')->commit();

            if (trim($tr_type) == "IN") {

                if ($idemployeeLama != $idemployee) {
                    if (trim($idemployeeLama) != "") {
                        $this->saveKBMast($tr_type, $idemployeeLama, $employeeName, $dept_id, $dept_name, $amt, getAuth($request)->user_id2);
                    }
                    $this->saveKBMast($tr_type, $idemployee, $employeeName, $dept_id, $dept_name, $amt, getAuth($request)->user_id2);
                }

                if (trim($ac_num) != "") {
                    $this->serviceAcc->JrnlkasBonInternal($office_id, $tr_id, $tahun4digit, getAuth($request)->user_id2);
                }
            } else {
                if (trim($ac_num) != "") {
                    $this->serviceAcc->JrnlkasBonExternal($office_id, $tr_id, $tahun4digit, getAuth($request)->user_id2);
                }
            }

            $user = DB::connection('sqlSrvBCKI')
              ->table('spk_aprv_env')
              ->selectRaw('aprv_1, no_hp_1, nama_employee_1')
              ->where('doc_type', '=', 'KB')
              ->where('active_flag', '=', 'Y')
              ->get();

            foreach ($user as $key => $value) {
                $aprv_1 = $value->aprv_1;
                $no_hp_1 = $value->no_hp_1;

                $this->sendNotifApproval($tr_id, $aprv_1, $no_hp_1);
            }

            return $this->sendResponse($data, 'Successfully save KasBon');
            //return response()->json(['return' => $data]);
        } catch (\Throwable $e) {
            DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }



    public function deleteKasBon(Request $request)
    {
        $tr_id = $request->tr_id;
        $seq_num = $request->seq_num;
        $tr_type = $request->tr_type;
        $idemployee = $request->idemployee;
        $employee_name = "";
        $dept_id =  "";
        $dept_name =  "";
        $amt = 0;

        if ($tr_id && ($seq_num  > 0)) {

            $delete = DB::connection("sqlSrvBCKI")
                ->table('kb_dtl')
                ->where('tr_id', $tr_id)
                ->where('seq_num', $seq_num)
                ->first();

            if ($delete) {
                DB::connection('sqlSrvBCKI')
                    ->table('kb_dtl')
                    ->where('tr_id',  $tr_id)
                    ->where('seq_num', $seq_num)
                    ->delete();

                if (trim($tr_type) == "IN") {
                    $this->saveKBMast($tr_type, $idemployee, $employee_name, $dept_id, $dept_name, $amt, getAuth($request)->user_id2);
                }
            }

            $cekDocHdr = DB::connection("sqlSrvBCKI")
                ->table('kb_hdr')
                ->where('tr_id', $tr_id)
                ->first();

            if ($cekDocHdr) {
                $ac_num = $cekDocHdr->ac_num ?? '';
                $tahun4digit =  Carbon::parse($cekDocHdr->dt_kb)->format('Y');
                $office_id = $cekDocHdr->office_id;
            }

            $cekDoc = DB::connection("sqlSrvBCKI")
                ->table('kb_dtl')
                ->where('tr_id', $tr_id)
                ->first();

            if ($cekDoc) {
                DB::connection("sqlSrvBCKI")
                    ->statement(DB::raw("update kb_hdr set 
                                kb_amt = (select isnull(sum(amt),0) from kb_dtl where tr_id = '$tr_id'), 
                                dt_modified = getdate(), 
                                user_id = '" . getAuth($request)->user_id2 . "' 
                                where tr_id = '$tr_id' "));
            } else {
                DB::connection('sqlSrvBCKI')
                    ->table('kb_hdr')
                    ->where('tr_id',  $tr_id)
                    ->delete();
            }
            if (trim($ac_num) != "") {
                if (trim($tr_type) == "IN") {
                    $this->serviceAcc->JrnlkasBonInternal($office_id, $tr_id, $tahun4digit, getAuth($request)->user_id2);
                } else {
                    $this->serviceAcc->JrnlkasBonExternal($office_id, $tr_id, $tahun4digit, getAuth($request)->user_id2);
                }
            }

            return response()->json(['success' => true, 'message' => 'Data deleted successfully!'], 200);
        }
        return response()->json(['success' => false, 'message' => 'Data not found!'], 404);
    }

    public function deleteKasBonMat(Request $request)
    {
        $tr_id = $request->tr_id;

        if ($tr_id) {

            $delete = DB::connection("sqlSrvBCKI")
                ->table('kb_dtl')
                ->where('tr_id', $tr_id)
                ->first();

            if ($delete) {
                DB::connection('sqlSrvBCKI')
                    ->table('kb_dtl')
                    ->where('tr_id',  $tr_id)
                    ->delete();
            }

            $cekDocHdr = DB::connection("sqlSrvBCKI")
                ->table('kb_hdr')
                ->where('tr_id', $tr_id)
                ->first();

            if ($cekDocHdr) {
                $ac_num = $cekDocHdr->ac_num ?? '';
                $tahun4digit =  Carbon::parse($cekDocHdr->dt_kb)->format('Y');
                $office_id = $cekDocHdr->office_id;
            }

            $cekDoc = DB::connection("sqlSrvBCKI")
                ->table('kb_dtl')
                ->where('tr_id', $tr_id)
                ->first();

            if ($cekDoc) {
                DB::connection("sqlSrvBCKI")
                    ->statement(DB::raw("update kb_hdr set 
                                kb_amt = (select isnull(sum(amt),0) from kb_dtl where tr_id = '$tr_id'), 
                                dt_modified = getdate(), 
                                user_id = '" . getAuth($request)->user_id2 . "' 
                                where tr_id = '$tr_id' "));
            } else {
                DB::connection('sqlSrvBCKI')
                    ->table('kb_hdr')
                    ->where('tr_id',  $tr_id)
                    ->delete();
            }

            if (trim($ac_num) != "") {
                $this->serviceAcc->JrnlkasBonExternal($office_id, $tr_id, $tahun4digit, getAuth($request)->user_id2);
            }

            return response()->json(['success' => true, 'message' => 'Data deleted successfully!'], 200);
        }
        return response()->json(['success' => false, 'message' => 'Data not found!'], 404);
    }

    public function saveKBMast($tr_type, $idemployee, $employee_name, $dept_id, $dept_name, $amt, $user_id2)
    {
        // save kb_mast
        if ($tr_type == 'IN') {

            $cekDoc = DB::connection("sqlSrvBCKI")
                ->table('kb_mast')
                ->where('idemployee', $idemployee)
                ->first();


            if ($cekDoc) {
                DB::connection("sqlSrvBCKI")
                    ->statement(DB::raw("update kb_mast set 
                                    amt_bal = (select isnull(sum(amt),0) 
                                                from kb_dtl a
                                                inner join kb_hdr b on a.tr_id=b.tr_id and b.tr_type = 'IN'
                                                where b.idemployee = '$idemployee'),
                                    dt_modified = getdate(), 
                                    user_id = '$user_id2' 
                                    where idemployee = '$idemployee' "));
            } else {

                $data = [
                    'idemployee' => $idemployee,
                    'employee_name' => $employee_name,
                    'dept_id' => $dept_id,
                    'dept_name' => $dept_name,
                    'amt_bal' => $amt,
                    'dt_created' => Carbon::now(),
                    'dt_modified' => Carbon::now(),
                    'user_id' => $user_id2
                ];

                DB::connection("sqlSrvBCKI")
                    ->table('kb_mast')
                    ->insert($data);
            }

            return response()->json(['success' => true, 'message' => 'Data update successfully!'], 200);
        }
        return response()->json(['success' => false, 'message' => 'Data not found!'], 404);
    }

    public function SaveOtherPaymentBreakdown(Request $request)
    {
        // dd('oke');
        $detailData = $request->input('items');
        $other_pay_ref = $request->tr_id;
        $office_id = $request->office_id;
        $user_id = getAuth($request)->user_id2;
        $now = Carbon::now('Asia/Jakarta');
        // $success = true;

        // Pastikan other_pay_ref tersedia
        if (empty($other_pay_ref)) {
            return $this->sendError('Reference ID (tr_id) is required for processing.', 'Validation Error', 400);
        }

        // Cek data item
        if (!is_array($detailData) || empty($detailData)) {
            return $this->sendError('Items data is empty or invalid.', 'Validation Error', 400);
        }

        try {
            // Mulai transaksi untuk memastikan atomicity
            DB::connection('sqlSrvBCKI')->beginTransaction();

            // 1. **DAPATKAN DAFTAR TR_ID LAMA**
            // Ambil semua tr_id dari kb_hdr yang memiliki other_pay_ref yang sama.
            $old_tr_ids = DB::connection('sqlSrvBCKI')
                ->table('kb_hdr')
                ->where('other_pay_ref', $other_pay_ref)
                ->pluck('tr_id')
                ->map(fn($id) => trim($id))
                ->toArray();

            // dd($old_tr_ids); // Cek tr_id lama

            // 2. **HAPUS DATA LAMA (KB_DTL, KB_HDR, RECAP_BREAKDOWN)**
            // if (!empty($old_tr_ids)) {
            //     DB::connection('sqlSrvBCKI')
            //         ->table('kb_dtl')
            //         ->whereIn('tr_id', $old_tr_ids)
            //         ->delete();
            // }

            // DB::connection('sqlSrvBCKI')
            //     ->table('kb_hdr')
            //     ->where('other_pay_ref', $other_pay_ref)
            //     ->delete();

            // Hapus dari recap_breakdown
            DB::connection('sqlSrvBCKI')
                ->table('recap_breakdown')
                ->where('other_pay_ref', $other_pay_ref)
                ->delete();

            // 3. **HAPUS DATA LAMA DARI KB_HDR**
            // Hapus semua baris di kb_hdr yang memiliki other_pay_ref yang sama
            // $delete_hdr = DB::connection('sqlSrvBCKI')
            //     ->table('kb_hdr')
            //     ->where('other_pay_ref', $other_pay_ref)
            //     ->delete();

            $processedKbIds = [];

            // 4. **INSERT DATA BARU (Write-back)**
            foreach ($detailData as $item) {
                $vendor_id = $item['vendor_id'] ?? '';
            $id_spk = $item['spk_id'] ?? '';
            $is_full_kasbon = !empty($vendor_id) && !empty($id_spk);
            $kasbon_ref_client = trim($item['kasbon_ref'] ?? '');
            
            $final_kb_id = ''; // Default jika bukan kasbon

            if ($is_full_kasbon) {
                // STRATEGI: Pertahankan data KB_HDR & KB_DTL jika kasbon_ref ada di DB
                if (!empty($kasbon_ref_client) && in_array($kasbon_ref_client, $old_tr_ids)) {
                    // --- UPDATE KB DATA ---
                    DB::connection('sqlSrvBCKI')->table('kb_hdr')
                        ->where('tr_id', $kasbon_ref_client)
                        ->update([
                            'vendor_id'   => $vendor_id,
                            'dt_kb'       => $item['date'],
                            'kb_amt'      => $item['amount'],
                            'remark'      => $item['remark'],
                            'dt_modified' => $now,
                            'tr_id_ref'   => $id_spk
                        ]);

                    // Refresh detail (Delete-Insert detail agar simple tapi Header tetap)
                    DB::connection('sqlSrvBCKI')->table('kb_dtl')->where('tr_id', $kasbon_ref_client)->delete();
                    DB::connection('sqlSrvBCKI')->table('kb_dtl')->insert([
                        'office_id'  => $office_id,
                        'tr_id'      => $kasbon_ref_client,
                        'seq_num'    => 1,
                        'article_id' => $item['article_id'],
                        'qty'        => $item['qty'] ?? 1,
                        'price'      => $item['price'] ?? 0,
                        'amt'        => $item['amount'],
                        'stat'       => 'O',
                        'remark'     => $item['remark'],
                        'dt_created' => $now,
                        'user_id'    => $user_id
                    ]);

                    $final_kb_id = $kasbon_ref_client;
                } else {
                    // --- INSERT KB BARU ---
                    $final_kb_id = $this->generateTrId($request);
                    
                    DB::connection('sqlSrvBCKI')->table('kb_hdr')->insert([
                        'office_id'     => $office_id,
                        'tr_id'         => $final_kb_id,
                        'tr_type'       => 'OT',
                        'kb_num'        => 1,
                        'vendor_id'     => $vendor_id,
                        'dt_kb'         => $item['date'],
                        'kb_amt'        => $item['amount'],
                        'remark'        => $item['remark'],
                        'stat'          => 'O',
                        'dt_created'    => $now,
                        'user_id'       => $user_id,
                        'tr_id_ref'     => $id_spk,
                        'other_pay_ref' => $other_pay_ref,
                        'dt_aprv1' => $now, 'user_aprv1' => $user_id,
                        'dt_aprv2' => $now, 'user_aprv2' => $user_id,
                        'dt_aprv3' => $now, 'user_aprv3' => $user_id,
                        'dt_aprv4' => $now, 'user_aprv4' => $user_id
                    ]);

                    DB::connection('sqlSrvBCKI')->table('kb_dtl')->insert([
                        'office_id'  => $office_id,
                        'tr_id'      => $final_kb_id,
                        'seq_num'    => 1,
                        'article_id' => $item['article_id'],
                        'qty'        => $item['qty'] ?? 1,
                        'price'      => $item['price'] ?? 0,
                        'amt'        => $item['amount'],
                        'stat'       => 'O',
                        'remark'     => $item['remark'],
                        'dt_created' => $now,
                        'user_id'    => $user_id
                    ]);
                }
                $processedKbIds[] = $final_kb_id;
            }
                DB::connection('sqlSrvBCKI')->table('recap_breakdown')->insert([
                'office_id'     => $office_id,
                'tr_id'         => $this->generateTrIdRecap(),
                'tr_date'       => $item['date'],
                'supplier_id'   => $item['supplier_id'] ?? null,
                'article_id'    => $item['article_id'],
                'qty'           => $item['qty'] ?? 1,
                'price'         => $item['price'] ?? 0,
                'amt'           => $item['amount'],
                'remark'        => $item['remark'],
                'vendor_id'     => $vendor_id,
                'tr_id_ref'     => $id_spk,
                'pot_kasbon'    => $is_full_kasbon ? 'Y' : 'N',
                'other_pay_ref' => $other_pay_ref,
                'dt_created'    => $now,
                'dt_modifed'    => $now,
                'user_id'       => $user_id,
                'kasbon_ref'    => $final_kb_id // Berisi TR_ID atau string kosong
            ]);
        }

        // 5. **CLEANUP KB DATA**
        // Hapus KB yang sudah tidak ada di list frontend (user menghapus baris di UI)
        $toDelete = array_diff($old_tr_ids, $processedKbIds);
        if (!empty($toDelete)) {
            DB::connection('sqlSrvBCKI')->table('kb_dtl')->whereIn('tr_id', $toDelete)->delete();
            DB::connection('sqlSrvBCKI')->table('kb_hdr')->whereIn('tr_id', $toDelete)->delete();
        }
            // 4. **COMMIT TRANSAKSI**
            // Commit HANYA jika operasi DELETE dan SEMUA operasi INSERT berhasil
            DB::connection('sqlSrvBCKI')->commit();
            return $this->sendResponse(true, 'Successfully synchronized breakdowns (Recap refreshed, KB preserved)');
        } catch (\Exception $e) {
            DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Database Error', 500);
        }
    }


    public function GetOtherPaymentBreakdown(Request $request)
    {
        $tr_id = $request->tr_id;
        // $data = DB::connection('sqlSrvBCKI')
        //     ->table('kb_hdr', 'a')
        //     ->join('kb_dtl as b', 'a.tr_id', '=', 'b.tr_id')
        //     ->join('vendor as c', 'a.vendor_id', '=', 'c.vendor_id')
        //     ->leftJoin('bank_note as d', function ($join) {
        //         $join->on('a.tr_id', '=', 'd.doc_id')
        //             ->on('b.tr_id', '=', 'd.doc_id');
        //     })
        //     ->selectRaw("
        //             a.dt_kb,
        //             trim(a.vendor_id) as vendor_id,
        //             trim(c.vendor_name) as vendor_name,
        //             trim(a.tr_id_ref) as tr_id_ref,
        //             CASE
        //                 WHEN d.doc_id IS NULL THEN 'N'
        //                 ELSE 'Y'
        //             END AS sudahDN,
        //             b.*
        //         ")
        $data = DB::connection('sqlSrvBCKI')
            // ->table('kb_hdr', 'a')
            // ->join('kb_dtl as b', 'a.tr_id', '=', 'b.tr_id')
            ->table('recap_breakdown as a')
            ->leftjoin('vendor as c', 'a.vendor_id', '=', 'c.vendor_id')
            ->leftJoin('bank_note as d', function ($join) {
                $join->on('a.tr_id', '=', 'd.doc_id');
                // ->on('b.tr_id', '=', 'd.doc_id');
            })
            ->selectRaw("
                    a.*,
                    isnull(TRIM(c.vendor_name),'') AS vendor_name,
                    CASE
                        WHEN d.doc_id IS NULL THEN 'N'
                        ELSE 'Y'
                    END AS sudahDN
                ")
            ->where('a.other_pay_ref', $tr_id)
            ->get();
        return $this->sendResponse($data, 'Successfully get Other Payment Breakdown');
    }

    public function DeleteOtherPaymentBreakdown(Request $request)
    {
        $tr_id = trim($request->tr_id);
        if (empty($tr_id)) {
            return $this->sendError('Transaction ID (tr_id) is required for deletion.', 'Validation Error', 400);
        }
        try {
            DB::connection('sqlSrvBCKI')->beginTransaction();
            // 1. Cek item di recap_breakdown untuk menentukan apakah KasBon dibuat
            $recapItem = DB::connection('sqlSrvBCKI')
                ->table('recap_breakdown')
                ->select('kasbon_ref')
                ->where('tr_id', $tr_id)
                ->first();

            if (!$recapItem) {
                DB::connection('sqlSrvBCKI')->rollBack();
                // Jika record tidak ditemukan, kembalikan error not found
                return $this->sendError('Record not found in breakdown.', 'Not Found', 404);
            }

            // **MODIFIKASI**: Tentukan apakah KasBon Header/Detail pernah dibuat untuk item ini
            // Berdasarkan keberadaan nilai di kolom kasbon_ref
            $kasbonRefExists = !empty($recapItem->kasbon_ref);

            // 2. Penghapusan Bersyarat (Jika KasBon Header/Detail pernah dibuat)
            if ($kasbonRefExists) {
                // Hapus detail kb_dtl
                DB::connection('sqlSrvBCKI')
                    ->table('kb_dtl')
                    ->where('tr_id', $recapItem->kasbon_ref)
                    ->delete();

                // Hapus header kb_hdr
                DB::connection('sqlSrvBCKI')
                    ->table('kb_hdr')
                    ->where('tr_id', $recapItem->kasbon_ref)
                    ->delete();
            }

            // 3. Selalu hapus dari recap_breakdown
            DB::connection('sqlSrvBCKI')
                ->table('recap_breakdown')
                ->where('tr_id', $tr_id)
                ->delete();

            DB::connection('sqlSrvBCKI')->commit();
            return $this->sendResponse(true, 'Successfully deleted Other Payment Breakdown');
        } catch (\Exception $e) {
            DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function generateTrIdRecap()
    {
        $prefix = 'RKP';
        $year = date('y');   // contoh: 25
        $month = date('m');  // contoh: 05

        // Ambil record terakhir berdasarkan tahun (bukan bulan)
        // $last = DB::connection("sqlSrvBCKI")
        //             ->table('kb_hdr')
        //             ->whereRaw("SUBSTRING(tr_id, 5, 2) = ?", [$year])
        //             ->whereRaw("SUBSTRING(tr_id, 1, 3) = 'KBE'")
        //             ->orderByDesc('tr_id')
        //             ->first('tr_id');

        // Ambil record terakhir berdasarkan bulan
        $last = DB::connection("sqlSrvBCKI")
            ->table('recap_breakdown')
            ->whereRaw("SUBSTRING(tr_id, 5, 2) = ?", [$year])     // ambil YY
            ->whereRaw("SUBSTRING(tr_id, 8, 2) = ?", [$month])    // ambil MM
            ->whereRaw("SUBSTRING(tr_id, 1, 3) = 'RKP'")          // pastikan prefix
            ->orderByDesc('tr_id')
            ->first(['tr_id']);
        // dd($last);

        // Susun nomor baru berdasarkan tahun (bukan bulan)
        // if ($last) {
        //     $lastParts = explode('/', $last->tr_id);
        //     $lastYear = $lastParts[1];          // contoh: 25
        //     $lastNumber = (int) $lastParts[3];  // contoh: 001

        //     $newNumber = $lastYear == $year ? $lastNumber + 1 : 1;
        // } else {
        //     $newNumber = 1;
        // }

        // Susun nomor baru berdasarkan bulan
        if ($last) {
            $lastParts = explode('/', $last->tr_id);
            $lastYear = $lastParts[1];          // contoh: 25
            $lastMonth = $lastParts[2];         // contoh: 06
            $lastNumber = (int) $lastParts[3];  // contoh: 001

            // Reset nomor jika bulan berbeda
            $newNumber = ($lastYear == $year && $lastMonth == $month) ? $lastNumber + 1 : 1;
        } else {
            $newNumber = 1;
        }

        // Format ke 3 digit
        $formattedNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);

        return "{$prefix}/{$year}/{$month}/{$formattedNumber}";
    }

    private function cekDebitNote($tr_id)
    {

        $data = DB::connection('sqlSrvBCKI')
            ->table('recap_breakdown as a')
            ->leftjoin('vendor as c', 'a.vendor_id', '=', 'c.vendor_id')
            ->leftJoin('bank_note as d', function ($join) {
                $join->on('a.tr_id', '=', 'd.doc_id');
            })
            ->selectRaw("
                    a.*,
                    isnull(TRIM(c.vendor_name),'') AS vendor_name,
                    CASE
                        WHEN d.doc_id IS NULL THEN 'N'
                        ELSE 'Y'
                    END AS sudahDN
                ")
            ->where('a.other_pay_ref', $tr_id)
            ->get();
        return $this->sendResponse($data, 'Successfully get Other Payment Breakdown');
    }
    private function sendNotifApproval($kb_id, $getEmp, $getPhone)
    {
      try {
        $getLink = "bcki" . now()->format('ymdH') . rand(10000, 99999);

        DB::connection('sqlsrv')
          ->table('sec_reset_password')
          ->insert([
            'user_id2' => $getEmp ? $getEmp : '',
            'link' => $getLink,
            'created_at' => now(),
            'updated_at' => now(),
          ]);

        // $message = "*BANGUN CIPTA KARYA INDONESIA*\n\n" .
        // "Silahkan lakukan Approval 2 untuk Kasbon ID: {$kb_id}\n" .
        // "Silahkan login pada https://viva.kencana.org/approve/{$getEmp}/{$getLink} untuk Approval 2.\nTerima kasih.";
        $message = "*BANGUN CIPTA KARYA INDONESIA*\n\n" .
          "Silahkan lakukan Approval untuk Kasbon ID: {$kb_id}\n";

        $url = 'https://notifapi.com/send_message';

        $data = array(
          "phone_no" => $getPhone,
          "key"      => '397bb50fd21e7c0f41b71eed483006ac45d42a47d6d43b79',
          "message"  => $message
        );

        $data_string = json_encode($data, 1);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 360);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'Content-Length: ' . strlen($data_string),
          'Authorization: Basic dXNtYW5ydWJpYW50b3JvcW9kcnFvZHJiZWV3b293YToyNjM3NmVkeXV3OWUwcmkzNDl1ZA=='
        ));

        $res = curl_exec($ch);
        curl_close($ch);

        if ($res == 'Success' || $res == 'success') {
          $stat = 1;
          $msg = $res;
        } else {
          $stat = 0;
          $msg = $res;
        }

        $dataRes = ['stat' => $stat, 'msg' => $msg];

        return $dataRes;
      } catch (\Exception $e) {
        return $this->sendError($e->getMessage(), 'Error', 500);
      }
    }
}