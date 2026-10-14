<?php

namespace App\Http\Controllers\purchasing;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\MasterFPI;
use App\Services\PerhitunganIncludeService;
use App\Services\AccountingService;


class PurchaseReturController extends Controller
{

    use MasterFPI;

    protected $ServiceInclude;
    protected $ServiceAccounting;

    public function __construct(PerhitunganIncludeService $ServiceInclude)
    {
        $this->ServiceInclude = $ServiceInclude;
        $this->ServiceAccounting = new AccountingService();
    }

    public function index(Request $request)
    {

        $GVendor = $this->getVendor("");
        $GOffice = $this->getOffice("");
        $GDepartment = $this->getDepartment("", "");
        $gpic = $this->getPicName("", "", "");
        $GArticleId = $this->getArticleId("");
        $GIdSite = $this->getIdSite("");
        $GIdCluster = $this->getIdCluster("");
        $GIdHouse = $this->getIdHouse("");
        $GPayterm = $this->getPayTerm("");
        $getAuth = getAuth($request);
        $currId = $this->getCurrId("");

        return response()->json([
            'message' => 'ok',
            'vendor' => $GVendor,
            'office' => $GOffice,
            'department' => $GDepartment,
            'pic' => $gpic,
            'articleId' => $GArticleId,
            'idSite' => $GIdSite,
            'idCluster' => $GIdCluster,
            'idHouse' => $GIdHouse,
            'payterm' => $GPayterm,
            'userId' => $getAuth,
            'currency' => $currId,
            'status_code' => 200
        ], 200);
    }
    public function getReturHdr(Request $request)
    {
        try {
            $sqlWhere = '1=1 ';
            if ($request->vendor_id) {
                $sqlWhere .= " and a.vendor_id = '" . $request->vendor_id . "'";
            }
            if ($request->vendor_name) {
                $sqlWhere .= " and b.vendor_name = '" . $request->vendor_name . "'";
            }
            if ($request->office_id) {
                $sqlWhere .= " and a.office_id = '" . $request->office_id . "'";
            }
            if ($request->purc_rtn_id) {
                $sqlWhere .= " and a.purc_rtn_id = '" . $request->purc_rtn_id . "'";
            }
            if ($request->dtawal) {
                if ($request->dtakhir) {
                    $sqlWhere .= " and a.dt_rtn between '" . $request->dtawal . " 00:00:00' and '" . $request->dtakhir . " 23:59:59' ";
                } else {
                    $sqlWhere .= " and a.dt_rtn = '$request->dtawal' ";
                }
            }
            // dd($sqlWhere);
            $datas = DB::connection("sqlSrvFPI")
                ->table('purc_rtn_hdr as a')
                ->selectRaw("a.office_id, c.office, a.dt_rtn, a.purc_rtn_id, a.vendor_id, b.vendor_name
                        ,a.sj_no,a.qty, a.amt_total, a.curr_id, a.rate, a.remark, a.stat, STRING_AGG(LTRIM(RTRIM(ISNULL(d.po_id, ''))), ', ') AS po_id, a.pct_ppn, a.pct_pph, a.tax1, a.tax2")
                ->join('vendor as b', function ($join) {
                    $join->on('a.vendor_id', '=', 'b.vendor_id');
                })
                ->join('branch_office as c', function ($join) {
                    $join->on('a.office_id', '=', 'c.office_id');
                })
                ->leftjoin('purc_rtn_dtl as d', function ($join) {
                    $join->on('a.purc_rtn_id', '=', 'd.purc_rtn_id');
                })
                ->whereRaw($sqlWhere)
                ->groupBy(
                    'a.office_id',
                    'c.office',
                    'a.dt_rtn',
                    'a.purc_rtn_id',
                    'a.vendor_id',
                    'b.vendor_name',
                    'a.sj_no',
                    'a.qty',
                    'a.amt_total',
                    'a.curr_id',
                    'a.rate',
                    'a.remark',
                    'a.stat',
                    'a.pct_ppn',
                    'a.pct_pph',
                    'a.tax1',
                    'a.tax2'
                )
                ->orderBy('a.purc_rtn_id', 'asc')
                ->orderby('a.dt_rtn', 'asc')
                ->get();

            return $this->sendResponse($datas, 'Success');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getReturDtl(Request $request)
    {
        try {
            $sqlWhere = '1=1 ';
            if ($request->purc_rtn_id) {
                $sqlWhere .= " and a.purc_rtn_id = '" . $request->purc_rtn_id . "'";
            }
            // dd($sqlWhere);
            $datas = DB::connection("sqlSrvFPI")
                ->table('purc_rtn_dtl as a')
                ->selectRaw("a.office_id, a.purc_rtn_id, a.seq_num, a.article_id, 
                            b.description, a.qty, a.unit_meas, a.unit_price, a.amt_item, 
                            a.wh_id, a.bin_id, a.purc_rcv_id, a.inv_id, a.inv_item, a.purc_seq_num,
                            CASE
                                WHEN a.purc_rcv_id IS NOT NULL AND TRIM(a.purc_rcv_id) <> '' THEN f.qty
                                ELSE e.qty_oh
                            END AS qty_oh,
                            isnull(e.qty_oh, 0) AS inv_qty_oh,
                            isnull(f.qty, 0) AS rcv_qty_oh,
                            CASE
                                WHEN a.purc_rcv_id IS NOT NULL AND TRIM(a.purc_rcv_id) <> '' THEN 'P'
                                ELSE 'A'
                            END AS type, a.remark, trim(c.descr) as wh_name, trim(d.descr) as bin_name,
                            a.po_id, a.po_item, a.pr_id, a.pr_item,
                            CASE
                                WHEN g.tax1 = 'E' THEN 'Y'  -- Kondisi 1: Jika 'E', ganti menjadi 'Y'
                                WHEN g.tax1 IS NULL OR g.tax1 = '' THEN 'N' -- Kondisi 2: Jika NULL atau string kosong, ganti menjadi 'N'
                                ELSE g.tax1 -- Selain itu, gunakan nilai tax1 yang ada
                            END AS tax1")
                ->join('article as b', function ($join) {
                    $join->on('a.article_id', '=', 'b.article_id');
                })
                ->leftjoin('whouse as c', function ($join) {
                    $join->on('a.wh_id', '=', 'c.wh_id');
                })
                ->leftjoin('wh_bin as d', function ($join) {
                    $join->on('a.bin_id', '=', 'd.bin_id')
                        ->on('a.wh_id', '=', 'd.wh_id');
                })
                ->join('inv_bal as e', function ($join) {
                    $join->on('a.article_id', '=', 'e.article_id')
                         ->on('a.wh_id', '=', 'e.wh_id')
                         ->on('a.bin_id', '=', 'e.bin_id');
                })
                ->leftJoin('purc_rcv_dtl as f', function ($join) {
                    $join->on('a.purc_rcv_id', '=', 'f.purc_rcv_id')
                        ->on('a.purc_seq_num', '=', 'f.seq_num')
                        ->on('a.article_id', '=', 'f.article_id');
                })
                ->leftjoin('po_hdr as g', function ($join) {
                    $join->on('a.po_id', '=', 'g.po_id');
                })
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Success');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function CategoryOnChange(Request $request)
    {
        try {
            $sqlWhere = '1=1 ';
            if ($request->cat_id) {
                $sqlWhere .= " and (cat_id = '" . $request->cat_id . "' or descr like '%" . $request->cat_id . "%')";
            }
            $datas = DB::connection("sqlSrvFPI")
                ->table('category')
                ->selectRaw('cat_id, descr as category')
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Success');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function CommodityOnChange(Request $request)
    {
        try {
            $sqlWhere = '1=1 ';
            if ($request->com_id) {
                $sqlWhere .= " and (com_id = '" . $request->com_id . "' or com_desc like '%" . $request->com_id . "%')";
            }
            $datas = DB::connection("sqlSrvFPI")
                ->table('commodity')
                ->selectRaw('com_id, com_desc as commodity')
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Success');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getListArticle(Request $request)
    {
        try {

            $type_rtn = $request->type_rtn;

            if ($type_rtn == 'A') {

                $sqlWhere = '1=1 ';
                if ($request->article_id) {
                    $sqlWhere .= "and (b.article_id = '$request->article_id' or b.description like '%" . $request->article_id . "%')";
                }
                if ($request->com_id) {
                    $sqlWhere .= "and b.com_id = '$request->com_id'";
                }
                if ($request->cat_id) {
                    $sqlWhere .= "and b.cat_id = '$request->cat_id'";
                }

                $datas = DB::connection("sqlSrvFPI")
                    ->table('inv_bal as a')
                    ->selectRaw("a.article_id, b.description, b.uom, b.cat_id, c.descr as category, 
                            b.com_id, d.com_desc as commodity, a.office_id, a.wh_id, a.bin_id,
                            a.qty_oh as qty, a.wgt_oh, 'N' as tax1")
                    ->join('article as b', function ($join) {
                        $join->on('a.article_id', '=', 'b.article_id');
                    })
                    ->join('category as c', function ($join) {
                        $join->on('b.cat_id', '=', 'c.cat_id');
                    })
                    ->join('commodity as d', function ($join) {
                        $join->on('b.com_id', '=', 'd.com_id');
                        $join->on('b.cat_id', '=', 'd.cat_id');
                    })

                    ->where('b.active_flag', 'Y')
                    ->whereRaw($sqlWhere)
                    ->get();

                return $this->sendResponse($datas, 'Successfull');

            } else {

                $sqlWhere = '1=1 ';
                if ($request->purc_rcv_id   ) {
                    $sqlWhere .= "and a.purc_rcv_id = '$request->purc_rcv_id'";
                }

                $datas = DB::connection("sqlSrvFPI")
                    ->table('purc_rcv_dtl as a')
                    ->selectRaw("a.purc_rcv_id, a.article_id, b.description, a.seq_num, a.sj_no,
                            a.pr_id, a.pr_item, a.po_id, a.po_item, a.amt_item, e.unit_meas, e.qty, e.unit_price, 
                            b.cat_id, c.descr as category, b.com_id, d.com_desc as commodity,
                            CASE
                                WHEN f.tax1 = 'E' THEN 'Y'  -- Kondisi 1: Jika 'E', ganti menjadi 'Y'
                                WHEN f.tax1 IS NULL OR f.tax1 = '' THEN 'N' -- Kondisi 2: Jika NULL atau string kosong, ganti menjadi 'N'
                                ELSE 'N' -- Selain itu, gunakan nilai tax1 yang ada
                            END AS tax1")
                    ->join('article as b', function ($join) {
                        $join->on('a.article_id', '=', 'b.article_id');
                    })
                    ->join('category as c', function ($join) {
                        $join->on('b.cat_id', '=', 'c.cat_id');
                    })
                    ->join('commodity as d', function ($join) {
                        $join->on('b.cat_id', '=', 'd.cat_id');
                        $join->on('b.com_id', '=', 'd.com_id');
                    })
                    ->join('po_item as e', function ($join) {
                        $join->on('a.po_id', '=', 'e.po_id');
                        $join->on('a.po_item', '=', 'e.po_item');
                    })
                    ->join('po_hdr as f', function ($join) {
                        $join->on('a.po_id', '=', 'f.po_id');
                    })
                    ->whereRaw($sqlWhere)
                    ->get();
                
                return $this->sendResponse($datas, 'Successfull');

            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getRcvHeader(Request $request)
    {
        try {
            $sqlWhere = '1=1 ';
            if ($request->vendor_id) {
                $sqlWhere.= "and vendor_id = '$request->vendor_id'";
            }
            $datas = DB::connection("sqlSrvFPI")
                ->table('purc_rcv_hdr')
                ->selectRaw('office_id, purc_rcv_id, sj_no, dt_rcv, vendor_id ')
                ->whereRaw($sqlWhere)
                ->orderBy('dt_rcv', 'desc')
                ->get();

            return $this->sendResponse($datas, 'Successfull');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function GetAcNum()
    {
        $AcNumHut = DB::connection("sqlSrvFPI")
            ->table('g_ledger_std')
            ->selectRaw("ac_num")
            ->where('ac_name', '=', 'HUTANG PEMBELIAN')
            ->value("ac_num");

        $AcNumBbt = DB::connection("sqlSrvFPI")
            ->table('g_ledger_std')
            ->selectRaw("ac_num")
            ->where('ac_name', '=', 'PERSEDIAAN BARANG BELUM TERTAGIH')
            ->value("ac_num");

        $AcNumDisc = DB::connection("sqlSrvFPI")
            ->table('g_ledger_std')
            ->selectRaw("ac_num")
            ->where('ac_name', '=', 'DISKON PEMBELIAN')
            ->value("ac_num");

        $AcNumPpn = DB::connection("sqlSrvFPI")
            ->table('g_ledger_std')
            ->selectRaw("ac_num")
            ->where('ac_name', '=', 'PPN MASUKAN')
            ->value("ac_num");

        $AcNumPph = DB::connection("sqlSrvFPI")
            ->table('g_ledger_std')
            ->selectRaw("ac_num")
            ->where('ac_name', '=', 'PPH23')
            ->value("ac_num");

        $AcNumPph21 = DB::connection("sqlSrvFPI")
            ->table('g_ledger_std')
            ->selectRaw("ac_num")
            ->where('ac_name', '=', 'PPH21')
            ->where('divisi', '=', 'L')
            ->value("ac_num");

        $AcNumPph4 = DB::connection("sqlSrvFPI")
            ->table('g_ledger_std')
            ->selectRaw("ac_num")
            ->where('ac_name', '=', 'PPH4')
            ->value("ac_num");

        return [
            'acNumHut' => $AcNumHut,
            'acNumBbt' => $AcNumBbt,
            'acNumDisc' => $AcNumDisc,
            'acNumPpn' => $AcNumPpn,
            'acNumPph' => $AcNumPph,
            'acNumPph21' => $AcNumPph21,
            'acNumPph4' => $AcNumPph4,
        ];
    }

    public function SaveReturn(Request $request)
    {
        try {
            $purc_rcv_id = $request->purc_rcv_id;
            $rtnDtlData = $request->input('detail');

            $office_id = $request->office_id;
            $user_id = getAuth($request)->user_id2;

            // Mendeteksi mode: buat baru atau edit
            // Cukup dengan memeriksa apakah purc_rtn_id dikirim dari frontend
            $isEditing = !empty(trim($request->purc_rtn_id));
            $purc_rtn_id = $isEditing ? trim($request->purc_rtn_id) : $this->genReturId($request->dt_rtn, $office_id);

            $getRcv = null;
            $getRcvDtl = null;

            if($purc_rcv_id){
                $getRcv = DB::connection("sqlSrvFPI")
                    ->table('purc_rcv_hdr')
                    ->where('purc_rcv_id', $purc_rcv_id)
                    ->first();
                // dd($getRcv);
                $getRcvDtl = DB::connection("sqlSrvFPI")
                    ->table('purc_rcv_dtl')
                    ->where('purc_rcv_id', $purc_rcv_id)
                    ->get();
                    // dd($getRcvDtl);
            }
            
            // dd($getRcv);
            // ----------------------------------------------------
            // Logika untuk mendapatkan rtn_num (otomatis)
            // ----------------------------------------------------
            $rtn_num = null;
            if ($isEditing) {
                // Jika edit, ambil rtn_num dari database yang sudah ada
                $existingRtnHdr = DB::connection('sqlSrvFPI')
                    ->table('purc_rtn_hdr')
                    ->where('purc_rtn_id', $purc_rtn_id)
                    ->first();
                $rtn_num = optional($existingRtnHdr)->rtn_num;
            } else {
                // Jika buat baru, generate rtn_num yang baru secara otomatis
                $date = Carbon::parse($request->dt_rtn);
                $prefix = 'PR' . $date->format('ym') . (int) $office_id;
                $getLastRtnNum = DB::connection('sqlSrvFPI')
                    ->table('purc_rtn_hdr')
                    ->where('office_id', $office_id)
                    ->where(DB::raw("LTRIM(RTRIM(purc_rtn_id))"), 'like', $prefix . '%')
                    ->selectRaw('MAX(rtn_num) AS max_rtn')
                    ->value('max_rtn');
                $rtn_num = $getLastRtnNum ? $getLastRtnNum + 1 : 1;
            }

            $acnum = $this->GetAcNum();
            // dd($acnum);
            $retursaid = terbilang($request->amt_total);
            $sj_no = trim($getRcv ? $getRcv->sj_no : '');

            DB::connection('sqlSrvFPI')
            ->table('purc_rtn_hdr')
            ->updateOrInsert(['purc_rtn_id' => $purc_rtn_id], [
                'office_id' => $office_id,
                'purc_rtn_id' => $purc_rtn_id,
                'dt_rtn' => $request->dt_rtn,
                'vendor_id' => $request->vendor_id,
                'sj_no' => $sj_no,
                'expd_name' => '',
                'no_pol' => '',
                'driver_name' => '',
                'rtn_num' => $rtn_num,
                'qty' => $request->qty ? $request->qty : 0,
                'wgt' => 1,
                'amt_subtotal' => $request->amt_subtotal ? $request->amt_subtotal : 0,
                'amt_ppn' => $request->amt_ppn ? $request->amt_ppn : 0,
                'amt_pph' => $request->amt_pph ? $request->amt_pph : 0,
                'amt_total' => $request->amt_total ? $request->amt_total : 0,
                'retur_said' => $retursaid,
                'remark' => $request->remark ?? '',
                'curr_id' => $request->curr_id ?? optional($getRcv)->curr_id,
                'rate' => $request->rate ?? optional($getRcv)->rate,
                'ac_num_hut' => trim($acnum['acNumHut']),
                'ac_num_ppn' => trim($acnum['acNumPpn']),
                'ac_num_pph' => trim($acnum['acNumPph']),
                'ac_num_bbt' => trim($acnum['acNumBbt']),
                'ac_num_invent' => trim($getRcv ? $getRcv->ac_num_invent : ''),
                'dt_created' => Carbon::now('Asia/Jakarta'),
                'dt_modified' => Carbon::now('Asia/Jakarta'),
                'tax1' => $request->tax1 ? $request->tax1 : 0,
                'tax2' => $request->tax2 ? $request->tax2 : 0,
                'pct_ppn' => $request->pct_ppn ? $request->pct_ppn : 0,
                'pct_pph' => $request->pct_pph ? $request->pct_pph : 0,
                'tr_id_credit_note' => $purc_rtn_id,
                'user_id' => $user_id
            ]);
            // $unit_price = $getRcvDtl ? $getRcvDtl->unit_price : 0;
            // $amt_subtotal = $request->amt_subtotal ? $request->amt_subtotal : 0;


            // dd($retursaid);
            // dd($ac_num_hut, $ac_num_ppn, $ac_num_pph, $ac_num_bbt, $ac_num_invent, $purc_rtn_id, $rtn_num,$getLastRtnNum,$sj_no,$unit_price,$amt_subtotal,$retursaid);
            // $datas = DB::connection("sqlSrvFPI")
            //     ->table('purc_rtn_dtl')
            //     ->insert($request->all());
            // DB::connection('sqlSrvFPI')
            // ->table('purc_rtn_hdr')
            // ->updateOrInsert(['purc_rtn_id' => $purc_rtn_id],
            //     [
            //         'office_id' => $office_id,
            //         'purc_rtn_id' => $purc_rtn_id,
            //         'dt_rtn' => $dt_rtn,
            //         'vendor_id' => $vendor_id,
            //         'sj_no' => $sj_no,
            //         'expd_name' => '',
            //         'no_pol' => '',
            //         'driver_name' => '',
            //         'rtn_num' => $rtn_num,
            //         'qty' => $request->qty ? $request->qty : 0,
            //         'wgt' => 1,
            //         'amt_subtotal' => $request->amt_subtotal ? $request->amt_subtotal : 0,
            //         'amt_ppn' => $request->amt_ppn ? $request->amt_ppn : 0,
            //         'amt_pph' => $request->amt_pph ? $request->amt_pph : 0,
            //         'amt_total' => $request->amt_total ? $request->amt_total : 0,
            //         'retur_said' => $retursaid,
            //         'remark' => $request->remark ?? '',
            //         // 'invoice_flag' => 'Y',
            //         'curr_id' => $request->curr_id ?? optional($getRcv)->curr_id,
            //         'rate' => $request->rate ?? optional($getRcv)->rate,
            //         'ac_num_hut' => $ac_num_hut,
            //         'ac_num_ppn' => $ac_num_ppn,
            //         'ac_num_pph' => $ac_num_pph,
            //         'ac_num_bbt' => $ac_num_bbt,
            //         'ac_num_invent' => $ac_num_invent,
            //         'dt_created' => Carbon::now('Asia/Jakarta'),
            //         'dt_modified' => Carbon::now('Asia/Jakarta'),
            //         'tax1' => $request->tax1 ? $request->tax1 : 0,
            //         'tax2' => $request->tax2 ? $request->tax2 : 0,
            //         'pct_ppn' => $request->pct_ppn ? $request->pct_ppn : 0,
            //         'pct_pph' => $request->pct_pph ? $request->pct_pph : 0,
            //         'user_id' => $user_id

            // ]);

            $arrayParams = (object)[
                'period'           => date('Ym'),
                'office_id'        => $request->office_id,
                'tr_type'          => 'PR',
                'tr_id'            => $purc_rtn_id,
                'tr_date'          => now('Asia/Jakarta')->format('Ymd'),
                'user_id'          => $user_id,
                'dt_modified'      => now('Asia/Jakarta'),
                'remark'           => $request->remark ?? '',
                'tr_id_ref'        => $purc_rcv_id ?? ''
            ];

            $check = $this->storeTransHdr($arrayParams);
            // dd('stop', $check, $purc_rtn_id);
            
            if (isset($check->status) && $check->status === false) {
                return response()->json([
                    'success' => false,
                    'message' => $check->message ?? 'Gagal menyimpan Transaksi Header',
                ], 500);
            }
            // dd($purc_rtn_id);
            // $this->updateTransHdrWithWH($purc_rtn_id, $office_id, 'PR', $wh_id, $bin_id);

            //insert ke purc_rtn_dtl
            
            // $matchingRcvDtl = $getRcvDtl->firstWhere('purc_rcv_id', $request->purc_rcv_id);

            $oldRtnDtl = [];
            if ($isEditing) {
                $oldRtnDtl = DB::connection('sqlSrvFPI')
                                ->table('purc_rtn_dtl')
                                ->where('purc_rtn_id', $purc_rtn_id)
                                ->get()
                                ->keyBy('seq_num')
                                ->toArray();
            }

            // Hapus detail yang tidak ada di request
            if ($isEditing) {
                $newSeqNums = collect($rtnDtlData)->pluck('seq_num')->all();
                $oldSeqNums = array_keys($oldRtnDtl);
                $toDelete = array_diff($oldSeqNums, $newSeqNums);

                if (!empty($toDelete)) {
                    DB::connection('sqlSrvFPI')
                        ->table('purc_rtn_dtl')
                        ->where('purc_rtn_id', $purc_rtn_id)
                        ->whereIn('seq_num', $toDelete)
                        ->delete();
                }
                DB::connection('sqlSrvFPI')
                ->table('inv_trans_dtl')
                ->where('tr_id', $purc_rtn_id)
                ->where('tr_type', 'PR')
                ->delete();
            }
            $manualSeqNum = 1;
            foreach ($rtnDtlData as $item) {
                // dd($rtnDtlData);
                // dd($item['purc_rcv_id']);
                // $matchingRcvDtl = $getRcvDtl->firstWhere('purc_rcv_id', trim($item['purc_rcv_id']));
                // dd($matchingRcvDtl);
                // dd($item);
                $matchingRcvDtl = null;
                if ($getRcvDtl) {
                    $matchingRcvDtl = $getRcvDtl->firstWhere(function ($rcvItem) use ($item) {
                        return trim($rcvItem->purc_rcv_id) === trim($item['purc_rcv_id']) && trim($rcvItem->article_id) === trim($item['article_id']);
                    });
                }
                                            // dd($matchingRcvDtl);
                $inv_item = optional($matchingRcvDtl)->seq_num ?? ''; //$matchingRcvDtl ? $matchingRcvDtl->seq_num : '';
                // $unit_price = $matchingRcvDtl ? $matchingRcvDtl->unit_price : 0;
                $purc_seq_num = optional($matchingRcvDtl)->seq_num ?? '';//$matchingRcvDtl ? $matchingRcvDtl->seq_num : '';
                $inv_id = $getRcv ? $getRcv->inv_id : '';
                $curr_id = $getRcv ? $getRcv->curr_id : '';
                $rate = $getRcv ? $getRcv->rate : 1;
                
                $wh_id = $item['wh_id'];
                $bin_id = $item['bin_id'];
                
                // Tentukan purc_seq_num
                if (empty(trim($item['purc_rcv_id']))) {
                    // Jika purc_rcv_id kosong, gunakan auto-increment manual
                    $purc_seq_num_to_use = $manualSeqNum;
                    $manualSeqNum++; // Naikkan counter untuk item berikutnya
                } else {
                    // Jika purc_rcv_id ada, gunakan nilai dari $matchingRcvDtl
                    $purc_seq_num_to_use = optional($matchingRcvDtl)->seq_num ?? '';
                }


                DB::connection('sqlSrvFPI')
                    ->table('purc_rtn_dtl')
                    ->updateOrInsert(['purc_rtn_id' => $purc_rtn_id,
                                      'seq_num'     => $item['seq_num']],
                    [
                        'office_id'    => $office_id,
                        'purc_rtn_id'  => $purc_rtn_id,
                        'sj_no'        => $sj_no,
                        'pr_id'        => $item['pr_id'] ?? '',
                        'pr_item'      => $item['pr_item'] ?? 0,
                        'po_id'        => $item['po_id'] ?? '',
                        'po_item'      => $item['po_item'] ?? 0,
                        'inv_id'       => $inv_id, //optional($getRcv)->inv_id ?? '',
                        'inv_item'     => $inv_item, //optional($matchingRcvDtl)->seq_num ?? '',
                        'curr_id'      => $curr_id, //optional($getRcv)->curr_id ?? '',
                        'rate'         => $rate, //optional($getRcv)->rate ?? 1,
                        'article_id'   => $item['article_id'],
                        'defect'       => $item['defect'] ?? '',
                        'qty'          => $item['qty'],
                        'wgt'          => 1 * $item['qty'],
                        'unit_meas'    => $item['unit_meas'],
                        'wgt_unit'     => 1 * $item['qty'],
                        'unit_price'   => $item['price'],
                        'um_price'     => 0,
                        'amt_item'     => $item['amount'],
                        'wh_id'        => $item['wh_id'],
                        'bin_id'       => $item['bin_id'],
                        'batch_id'     => $item['batch_id'] ?? '',
                        'purc_rcv_id'  => $purc_rcv_id ?? '',
                        'purc_seq_num' => $purc_seq_num_to_use, //optional($matchingRcvDtl)->seq_num ?? '',
                        // 'tr_id'        => $purc_rtn_id,
                        // 'seq_id'       => $item['seq_id'],
                        'dt_created'   => Carbon::now('Asia/Jakarta'),
                        'dt_modified'  => Carbon::now('Asia/Jakarta'),
                        'user_id'      => $user_id,
                        'remark'       => $item['remark'] ?? '',
                        'length'       => $item['length'] ?? 1
                    ]);

                $this->updateTransHdrWithWH($purc_rtn_id, $office_id, 'PR', $wh_id, $bin_id);

                $arrayParams_invdtl = (object)[
                    'tr_id' => $purc_rtn_id,
                    'period' => date('Ym'),
                    'article_id' => $item['article_id'],
                    'office_id' => $office_id,
                    'defect' => 0,
                    'tr_type' => 'PR',
                    'qty' => $item['qty'],
                    'wgt' => 1 * $item['qty'],
                    'tr_id_ref' => $purc_rtn_id,
                    'tr_id_seq_ref' => $item['seq_num'],
                    'user_id' => $user_id,
                    'amt_item' => $item['amount'],
                    'unit_price' => $item['price'],
                    'length' => 1,
                    'satuan' => $item['unit_meas'] ?? '',
                    'batch_id' => $item['batch_id'] ?? '',
                    'qty_oh' => $item['qty'],
                    'add_id' => ' ',
                    'tor_id' => ' ',
                    'spk_id' => ' ',
                    'spk_item' => 0,
                    'wh_id' => $item['wh_id'],
                    'bin_id' => $item['bin_id'],
                    'remark' => $item['remark'] ?? '',
                    'uom' => $item['unit_meas'] ?? '',
                    'berat' => 1

                ];
                //hapus inv_trans_dtl


                $check = $this->storeTransWH($arrayParams_invdtl);
                $tanggal = Carbon::parse($request->dt_rtn);
                $tahun4digit = $tanggal->format('Y');
                $this->ServiceAccounting->JrnlPurcRetur($office_id, $purc_rtn_id, $tahun4digit, $user_id);

                if (isset($check->status) && $check->status === false) {
                    // DB::connection('sqlSrvFPI')->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => $check->message ?? 'Gagal menyimpan Transaksi Detail',
                    ], 500);
                }

            }


            return $this->sendResponse(true, 'Successfull');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function DeleteReturn(Request $request)
    {
        // try {
        //     $datas = DB::connection("sqlSrvFPI")
        //         ->table('purc_rtn_dtl')
        //         ->where('purc_rtn_id', $request->purc_rtn_id)
        //         ->delete();
        //     return $this->sendResponse($datas, 'Successfull');
        // } catch (\Exception $e) {
        //     return $this->sendError($e->getMessage(), 'Error', 500);
        // }

        // DB::connection('sqlSrvFPI')->beginTransaction();

        try {
            $office_id = $request->office_id;
            $purc_rtn_id = $request->purc_rtn_id;
            $seq_num = $request->seq_num;

            $rtnDtl = DB::connection('sqlSrvFPI')->table('purc_rtn_dtl')
                ->where('office_id', $office_id)
                ->where('purc_rtn_id', $purc_rtn_id)
                ->where('seq_num', $seq_num)
                ->first();
            // dd($rtnDtl);
            if (!$rtnDtl) {
                return response()->json(['error' => 'Data tidak ditemukan'], 404);
            }

            // $tr_id_invoice = $rtnDtl->tr_id_invoice;
            // $inv_id = $rtnDtl->inv_id;
            
            $prevQty    = $rtnDtl->qty;
            $prevWgt    = $rtnDtl->wgt;
            $prevAmount = $rtnDtl->amt_item;
            // $po_id = $rtnDtl->po_id;
            // $po_item = $rtnDtl->po_item;
            // $pr_id = $rtnDtl->pr_id;
            // $pr_item = $rtnDtl->pr_item;
            // $item = $rtnDtl->item;
            // $warehouse = $rtnDtl->warehouse;
            // $trans_id = $rtnDtl->trans_id;

            // Hapus dari inv_trans_dtl & inv_trans_hdr
            $user_id = getAuth($request)->user_id2;

            $arrayParams = (object)[
                'tr_type' => 'PR',
                'office_id' => $office_id,
                'tr_id' => $purc_rtn_id,
                'seq_id' => $seq_num,
                'article_id' => $rtnDtl->article_id ?? '',
                'length' => $rtnDtl->length ?? 1,
                'user_id' => $user_id,
                'period' => date('Ym'),
                'wh_id' => $rtnDtl->wh_id ?? '',
                'bin_id' => $rtnDtl->bin_id ?? ''
            ];
            // dd($arrayParams);
            $check = $this->deleteTransWh($arrayParams);

            if (isset($check->status) && $check->status === false) {
                return response()->json([
                    'success' => false,
                    'message' => $check->message ?? 'Gagal menyimpan Transaksi Header',
                ], 500);
            }

            // Hapus purc_rtn_dtl (baris detail yang sedang dihapus)
            $deleted = DB::connection('sqlSrvFPI')->table('purc_rtn_dtl')
                ->where('office_id', $office_id)
                ->where('purc_rtn_id', $purc_rtn_id)
                ->where('seq_num', $seq_num)
                ->delete();

            if (!$deleted) {
                // DB::connection('sqlSrvFPI')->rollBack();
                return response()->json(['error' => 'Gagal menghapus purc_rtn_dtl'], 500);
            }

             // Update / hapus purc_rtn_hdr
            $cekDtl = DB::connection('sqlSrvFPI')->table('purc_rtn_dtl')
                ->where('office_id', $office_id)
                ->where('purc_rtn_id', $purc_rtn_id)
                ->count();

            if ($cekDtl == 0) {
                DB::connection('sqlSrvFPI')->table('purc_rtn_hdr')
                    ->where('office_id', $office_id)
                    ->where('purc_rtn_id', $purc_rtn_id)
                    ->delete();

                $jvnumpurchrtn= DB::connection('sqlSrvFPI')->table('journal_voucher')
                ->distinct()
                ->where('source_num', $purc_rtn_id)
                ->pluck('jv_num');
                // ->get();
                
                $tanggal = Carbon::parse($rtnDtl->dt_created);
                $tahun4digit = $tanggal->format('Y');
                foreach ($jvnumpurchrtn as $jv_num) {
                    $this->ServiceAccounting->DeleteJournalByJvNum(1, (float)$jv_num, $tahun4digit);
                }

            } else {
                DB::connection('sqlSrvFPI')->table('purc_rtn_hdr')
                    ->where('office_id', $office_id)
                    ->where('purc_rtn_id', $purc_rtn_id)
                    ->decrementEach([
                        'qty' => $prevQty,
                        'wgt' => $prevWgt,
                        'amt_total' => $prevAmount
                    ]);
            }
            return $this->sendResponse('success' , 'Successfully Delete data');

        } catch (\Exception $e) {
            // DB::connection('sqlSrvFPI')->rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function ConfirmReturn(Request $request){
        $purc_rtn_id = $request->purc_rtn_id;

        try {
            DB::connection('sqlSrvFPI')
                ->table('purc_rtn_hdr')
                ->where('purc_rtn_id', $purc_rtn_id)
                ->update([
                    'stat' => 'C'
                    // 'dt_modified' => Carbon::now('Asia/Jakarta'),
                    // 'user_id' => getAuth($request)->user_id2
                ]);

            $data = DB::connection('sqlSrvFPI')
                ->table('purc_rtn_hdr')
                ->where('purc_rtn_id', $purc_rtn_id)
                ->first();
            
            $tanggal = Carbon::parse($data->dt_rtn);
            $tahun4digit = $tanggal->format('Y');
            // $doc_id = $this->genDocIdCreditNote(date('Ym'));
            // dd($doc_id);

                // DB::connection('sqlSrvFPI')
                // ->table('credit_note')
                // ->insert([
                //     'office_id' => $data->office_id,
                //     'tr_id' => $purc_rtn_id,
                //     'tr_type' => 'C',
                //     'cust_id' => $data->vendor_id,
                //     'rtn_id' => $purc_rtn_id,
                //     'doc_id' => $this->genDocIdCreditNote(date('Ym')), //formatnya 'yyyyMM+001'
                //     'bk' => $purc_rtn_id,
                //     'period' => date('Ym'),
                //     'tr_date' => $data->dt_rtn,
                //     'inv_id ' => '',
                //     'remark' => '',
                //     'tr_amt' => $data->amt_total,
                //     'stat' => '',
                //     'user_id' => getAuth($request)->user_id2,
                //     'dt_modified' => now('Asia/Jakarta')
                // ]);

                DB::connection('sqlSrvFPI')
                ->table('bank_note')
                ->insert([
                    'office_id' => $data->office_id,
                    'tr_id' => $purc_rtn_id,
                    // 'cust_id' => $data->vendor_id,
                    // 'rtn_id' => $purc_rtn_id,
                    'doc_id' => $this->genDocIdCreditNote(date('Ym')), //formatnya 'yyyyMM+001'
                    // 'bk' => $purc_rtn_id,
                    'period' => date('Ym'),
                    // 'stat' => '',
                    'user_id' => getAuth($request)->user_id2,
                    'dt_modified' => now('Asia/Jakarta'),
                    'tr_type' => 'D',
                    'bank_acc' => '',
                    'dt_trans' => $data->dt_rtn,
                    'descr' => 'RETUR',
                    'cabang' => '',
                    'db_amt' => $data->amt_total,
                    'user_id' => getAuth($request)->user_id2,
                    'voucher' => 1,
                    'db_code' => '',
                    'remark' => 'RETUR',
                    'customer' => $data->vendor_id,
                    'curr_id' => 'IDR',
                    'rate' => 1,
                    'pay_id' => '',
                    'used_amt' => '',
                    'link_id' => '',
                    'doc_id' => $purc_rtn_id,
                    'mill_id' => '',
                    'trx_mode' => ''
                ]);

                $this->ServiceAccounting->JrnlPurcRetur($data->office_id, $purc_rtn_id, $tahun4digit, getAuth($request)->user_id2);

            return $this->sendResponse($data, 'Successfull');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function genDocIdCreditNote($tanggal){
        $prefix = $tanggal; // Format: 'yyyyMM'
        $getlastDocId = DB::connection('sqlSrvFPI')
            ->table('credit_note')
            ->selectRaw('MAX(doc_id) AS max_doc_id')
            ->where('doc_id', 'like', $prefix . '%')
            ->value('max_doc_id');
        // dd($getlastDocId);

        if ($getlastDocId) {
            $lastSequence = (int) substr($getlastDocId, 6); // Ambil bagian urutan (3 digit terakhir)
            $nextSequence = $lastSequence + 1; // Tambah 1 untuk urutan berikutnya
        } else {
            $nextSequence = 1; // Jika tidak ada data, mulai dari 1
        }
        $getlastDocId = $prefix . str_pad($nextSequence, 3, '0', STR_PAD_LEFT); // Gabungkan prefix dengan urutan 3 digit
        return $getlastDocId;
    }

    private function genReturId($tr_date,$office_id){ //PR25081001
        $date = Carbon::parse($tr_date);
        $ym = $date->format('ym'); // Contoh: 2507
        // dd($tr_date, $date, $ym);
        $prefix = 'PR' . $ym . (int) $office_id; // Hasil: PI25075

        $getLastTrID = DB::connection('sqlSrvFPI')
            ->table('purc_rtn_hdr')
            ->where('office_id', $office_id)
            ->where(DB::raw("LTRIM(RTRIM(purc_rtn_id))"), 'like', $prefix . '%')
            ->selectRaw('MAX(rtn_num) AS max_rtn')
            ->value('max_tr');
        
        $nextUrut = $getLastTrID ? $getLastTrID + 1 : 1;

        $gen_purc_rtn_id = $prefix . str_pad($nextUrut, 3, '0', STR_PAD_LEFT);

        return $gen_purc_rtn_id;
    }

    private function updateTransHdrWithWH($tr_id, $office_id, $tr_type, $wh_id, $bin_id)
    {   
        // dd($tr_id, $office_id, $tr_type, $wh_id, $bin_id);
        $data = DB::connection('sqlSrvFPI')
            ->table('inv_trans_hdr')
            ->where('tr_id', $tr_id)
            ->where('office_id', $office_id)
            ->where('tr_type', $tr_type)
            // ->get();
        // dd($data);
            ->update([
                'wh_id' => $wh_id,
                'bin_id' => $bin_id,
                'wh_dest' => $wh_id,
                'bin_dest' => $bin_id,
            ]);
    }

    /*--------------------------dari warehouse trait----------------------------- */
    public function storeTransHdr($arrayParams)
    {
        try {
            // $yesterday_date = Carbon::parse($arrayParams->tr_date)->subDays(1)->format('Y-m-d');

            $current =  DB::connection("sqlSrvFPI")
                            ->table('period')
                            ->where('dept_id', 'PRODWH')
                            ->where('office_id', $arrayParams->office_id)
                            ->first();

            // dd($arrayParams->period);

            if ($current) {
                $period = $arrayParams->period;
                if ($current->curr_period == $period) {

                //     $yesterday_daily =  DB::connection("sqlSrvFPI")
                //                         ->table('period_daily')
                //                         ->where('dept_id', 'PRODWH')
                //                         ->where('office_id', $arrayParams->office_id)
                //                         ->where('close_date', $yesterday_date)
                //                         ->first();

                    // if ($yesterday_daily) {

                            // $data = DB::connection('sqlSrvFPI')
                            //             ->table('inv_trans_hdr')
                            //             ->where('office_id', $arrayParams->office_id)
                            //             ->where('tr_type', $arrayParams->tr_type)
                            //             ->where('tr_date', $arrayParams->tr_date)
                            //             // ->where('wh_id', $arrayParams->wh_id)
                            //             // ->where('bin_id', $arrayParams->bin_id)
                            //             ->first();

                            // if ($data) {
                            //     $transId = $data->tr_id;
                            // } else {
                            //     $transId = $arrayParams->tr_id;

                            //     $header = DB::connection('sqlSrvFPI')
                            //                 ->table('inv_trans_hdr')
                            //                 ->insert([
                            //                     'office_id' => $arrayParams->office_id,
                            //                     'tr_type' => $arrayParams->tr_type,
                            //                     'tr_id' => $transId,
                            //                     'tr_date' => $arrayParams->tr_date,
                            //                     // 'wh_id' => $arrayParams->wh_id,
                            //                     // 'bin_id' => $arrayParams->bin_id,
                            //                     'tr_id_ref'    => property_exists($arrayParams, 'tr_id_ref') ? $arrayParams->tr_id_ref : '',
                            //                     'tr_id_ref2'   => property_exists($arrayParams, 'tr_id_ref2') ? $arrayParams->tr_id_ref2 : '',
                            //                     'dt_modified' => now(),
                            //                     'user_id' => $arrayParams->user_id
                            //                     // 'payment' => $arrayParams->payment
                            //                 ]);
                            // }
                            $transId = $arrayParams->tr_id;

                            DB::connection('sqlSrvFPI')
                            ->table('inv_trans_hdr')
                            ->updateOrInsert(
                                [
                                    'office_id' => $arrayParams->office_id,
                                    'tr_type' => $arrayParams->tr_type,
                                    'tr_id' => $transId,
                                ],
                                [
                                    'tr_date' => $arrayParams->tr_date,
                                    'tr_id_ref' => property_exists($arrayParams, 'tr_id_ref') ? $arrayParams->tr_id_ref : '',
                                    'tr_id_ref2' => property_exists($arrayParams, 'tr_id_ref2') ? $arrayParams->tr_id_ref2 : '',
                                    'dt_modified' => now('Asia/Jakarta'),
                                    'user_id' => $arrayParams->user_id
                                ]
                            );

                            return (object)[
                                'status' => TRUE,
                                'message' => 'Success',
                                'response_time' => (microtime(TRUE) - LARAVEL_START),
                                'data' => $transId ? $transId : NULL
                            ];

                    // } else {
                    //     return (object)[
                    //         'status' => FALSE,
                    //         'message' => 'Belum di closing Harian',
                    //         'response_time' => (microtime(TRUE) - LARAVEL_START),
                    //         'data' => NULL
                    //     ];
                    // }  

                    
                } 
                else {
                    return (object)[
                        'status' => FALSE,
                        'message' => 'Belum di closing',
                        'response_time' => (microtime(TRUE) - LARAVEL_START),
                        'data' => NULL
                    ];
                }   
            } 
            else {
                return (object)[
                    'status' => FALSE,
                    'message' => 'Belum di closing',
                    'response_time' => (microtime(TRUE) - LARAVEL_START),
                    'data' => NULL
                ];
            } 
            
        }
        catch (\Exception $e) {
            return (object)[
                'status' => FALSE,
                'message' => $e->getMessage(),
                'response_time' => (microtime(TRUE) - LARAVEL_START),
                'data' => NULL
            ];
        }

    }

    public function storeTransWH($arrayParams)
    {
        // $yesterday_date = Carbon::parse($arrayParams->tr_date)->subDays(1)->format('Y-m-d');

        $current =  DB::connection("sqlSrvFPI")
                        ->table('period')
                        ->where('dept_id', 'PRODWH')
                        ->where('office_id', $arrayParams->office_id)
                        ->first();

        if ($current) {
            $period = $arrayParams->period;
            if ($current->curr_period == $period) {

                // $yesterday_daily =  DB::connection("sqlSrvFPI")
                //                     ->table('period_daily')
                //                     ->where('dept_id', 'PRODWH')
                //                     ->where('office_id', $arrayParams->office_id)
                //                     ->where('close_date', $yesterday_date)
                //                     ->first();

                // if ($yesterday_daily) {

                        $tr_type = $this->caseTrType($arrayParams->tr_type);
                        if ($tr_type == 'R') {
                            $tr_types = ['MR', 'RC', 'RW', 'SR', 'TR', 'RP'];
                        } elseif ($tr_type == 'I') {
                            $tr_types = ['IS', 'IW', 'MI', 'TI', 'PR', 'SA'];
                        } elseif ($tr_type == 'A') {
                            $tr_types = ['IA'];
                        }  else {
                            return (object)[
                                'status' => FALSE,
                                'message' => 'Please insert TR Type',
                                'response_time' => (microtime(true) - LARAVEL_START),
                                'data' => NULL
                            ];
                        }

                        $cek_stock = DB::connection("sqlSrvFPI")
                                    ->table('inv_bal')
                                    ->where('office_id', $arrayParams->office_id)
                                    ->where('article_id',$arrayParams->article_id)
                                    ->where('defect',$arrayParams->defect ?? 0)
                                    ->where('batch_id', $arrayParams->batch_id ?? ' ')
                                    // ->where('length', $arrayParams->length)
                                    // ->where('add_id', $arrayParams->add_id ? $arrayParams->add_id : ' ')
                                    // ->where('tor_id', $arrayParams->tor_id ? $arrayParams->tor_id : ' ')
                                    ->where('wh_id', $arrayParams->wh_id)
                                    ->where('bin_id',$arrayParams->bin_id)
                                    // ->where('spk_id',$arrayParams->spk_id ? $arrayParams->spk_id : ' ')
                                    // ->where('spk_item',$arrayParams->spk_item ? $arrayParams->spk_item : 0)
                                    ->first();

                        if ((float) $arrayParams->qty_oh < 0) {
                            if ($cek_stock) {
                                $calculate_stock = (float) $cek_stock->qty_oh + (float) $arrayParams->qty_oh;
                                if ($calculate_stock < 0) {
                                    return (object)[
                                        'status' => FALSE,
                                        'message' => 'Maaf stock anda kurang',
                                        'response_time' => (microtime(true) - LARAVEL_START),
                                        'data' => NULL
                                    ];
                                }
                            } else {
                                return (object)[
                                    'status' => FALSE,
                                    'message' => 'Maaf stock anda belum ada',
                                    'response_time' => (microtime(true) - LARAVEL_START),
                                    'data' => NULL
                                ];
                            }                      
                        }

                        $seq_id = DB::connection("sqlSrvFPI")
                                    ->table('inv_trans_dtl')
                                    ->select('seq_id')
                                    ->where('office_id', $arrayParams->office_id)
                                    ->where('tr_type', $arrayParams->tr_type)
                                    ->where('tr_id', $arrayParams->tr_id)
                                    ->max('seq_id');

                        // $insertItem = DB::connection('sqlSrvFPI')
                        //             ->table('inv_trans_dtl')
                        //             ->insert([
                        //                 'office_id' => $arrayParams->office_id,
                        //                 'tr_type' => $arrayParams->tr_type,
                        //                 'tr_id' => $arrayParams->tr_id,
                        //                 'seq_id' => $seq_id + 1,
                        //                 'article_id' => $arrayParams->article_id,
                        //                 'wh_id' => $arrayParams->wh_id ?? '',
                        //                 'bin_id' => $arrayParams->bin_id ?? '',       
                        //                 'defect' => $arrayParams->defect ?? 0,
                        //                 'batch_id' => $arrayParams->batch_id ?? ' ',
                        //                 'add_id' => $arrayParams->add_id ?? ' ',
                        //                 'tor_id' => $arrayParams->tor_id ?? ' ',
                        //                 'spk_id' => $arrayParams->spk_id ?? ' ',
                        //                 'spk_item' => $arrayParams->spk_item ?? 0,
                        //                 'length' => (float)$arrayParams->length,
                        //                 'amt_item' => (float)$arrayParams->amt_item ? (float)$arrayParams->amt_item : 0,
                        //                 'unit_price' => (float)$arrayParams->unit_price ? (float)$arrayParams->unit_price : 0,                                    
                        //                 'qty' => (int) $arrayParams->qty_oh,
                        //                 'wgt' => $arrayParams->satuan == 'M' ? (int) $arrayParams->qty_oh * (float)$arrayParams->length * (float)$arrayParams->berat : (int) $arrayParams->qty_oh * (float)$arrayParams->berat,                                    
                        //                 'remark' => $arrayParams->remark ? $arrayParams->remark : '',
                        //                 'period' => $arrayParams->period,
                        //                 'tr_id_ref' => $arrayParams->tr_id_ref ? $arrayParams->tr_id_ref : ' ',
                        //                 'tr_id_seq_ref' => $arrayParams->tr_id_seq_ref ? $arrayParams->tr_id_seq_ref : ' ',
                        //                 'dt_modified' => now('Asia/Jakarta'),
                        //                 'user_id' => $arrayParams->user_id,
                        //                 'uom' => $arrayParams->satuan ? $arrayParams->satuan : '',
                        //                 'wgt_unit' => $arrayParams->berat ? (float)$arrayParams->berat : 0
                        //             ]);

                        $insertItem = DB::connection('sqlSrvFPI')
                            ->table('inv_trans_dtl')
                            ->updateOrInsert(
                                [
                                    'office_id' => $arrayParams->office_id,
                                    'tr_type' => $arrayParams->tr_type,
                                    'tr_id' => $arrayParams->tr_id,
                                    'seq_id' => $seq_id + 1,
                                ],
                                [
                                    'article_id' => $arrayParams->article_id,
                                    'wh_id' => $arrayParams->wh_id ?? '',
                                    'bin_id' => $arrayParams->bin_id ?? '',
                                    'defect' => $arrayParams->defect ?? 0,
                                    'batch_id' => $arrayParams->batch_id ?? ' ',
                                    'add_id' => $arrayParams->add_id ?? ' ',
                                    'tor_id' => $arrayParams->tor_id ?? ' ',
                                    'spk_id' => $arrayParams->spk_id ?? ' ',
                                    'spk_item' => $arrayParams->spk_item ?? 0,
                                    'length' => (float)$arrayParams->length,
                                    'amt_item' => (float)$arrayParams->amt_item ? (float)$arrayParams->amt_item : 0,
                                    'unit_price' => (float)$arrayParams->unit_price ? (float)$arrayParams->unit_price : 0,
                                    'qty' => (float)$arrayParams->qty_oh,
                                    'wgt' => $arrayParams->satuan == 'M' ? (float)$arrayParams->qty_oh * (float)$arrayParams->length * (float)$arrayParams->berat : (float)$arrayParams->qty_oh * (float)$arrayParams->berat,
                                    'remark' => $arrayParams->remark ? $arrayParams->remark : '',
                                    'period' => $arrayParams->period,
                                    'tr_id_ref' => $arrayParams->tr_id_ref ? $arrayParams->tr_id_ref : ' ',
                                    'tr_id_seq_ref' => $arrayParams->tr_id_seq_ref ? $arrayParams->tr_id_seq_ref : ' ',
                                    'dt_modified' => now(),
                                    'user_id' => $arrayParams->user_id,
                                    'uom' => $arrayParams->satuan ? $arrayParams->satuan : '',
                                    'wgt_unit' => $arrayParams->berat ? (float)$arrayParams->berat : 0
                                ]
                            );

                        $transQty = $this->GetQtyTransInvDetil($arrayParams, $tr_type, $tr_types);
                        $updateQty = $this->updateQtyInvBal($transQty ? ($transQty->qty ? (float) $transQty->qty : 0) : 0, $transQty ? ($transQty->wgt ? (double) $transQty->wgt : 0) : 0, $arrayParams, $tr_type, $tr_types);

                        return (object)[
                            'status' => TRUE,
                            'message' => 'Successfully saved',
                            'response_time' => (microtime(true) - LARAVEL_START),
                            'data' => $arrayParams->tr_id
                        ];
                // }                           
                
            } else {
                return (object)[
                    'status' => FALSE,
                    'message' => 'Belum di closing',
                    'response_time' => (microtime(true) - LARAVEL_START),
                    'data' => NULL
                ];
            }   
        } else {
            return (object)[
                'status' => FALSE,
                'message' => 'Belum di closing',
                'response_time' => (microtime(true) - LARAVEL_START),
                'data' => NULL
            ];
        } 

    }

    public function deleteTransWh($arrayParams)
    {
        // dd('masuk');
        // $yesterday_date = Carbon::parse($arrayParams->tr_date)->subDays(1)->format('Y-m-d');
        // dd($arrayParams);
        $current =  DB::connection("sqlSrvFPI")
                        ->table('period')
                        ->where('dept_id', 'PRODWH')
                        ->where('office_id', $arrayParams->office_id)
                        ->first();
        // dd($current);
        if ($current) {
            $period = $arrayParams->period;
            if ($current->curr_period == $period) {

                // $yesterday_daily =  DB::connection("sqlSrvFPI")
                //                     ->table('period_daily')
                //                     ->where('dept_id', 'PRODWH')
                //                     ->where('office_id', $arrayParams->office_id)
                //                     ->where('close_date', $yesterday_date)
                //                     ->first();

                // if ($yesterday_daily) {

                        $tr_type = $this->caseTrType($arrayParams->tr_type);
                        if ($tr_type == 'R') {
                            $tr_types = ['MR', 'RC', 'RW', 'SR', 'TR', 'RP'];
                        } elseif ($tr_type == 'I') {
                            $tr_types = ['IS', 'IW', 'MI', 'TI', 'PR', 'SA'];
                        } elseif ($tr_type == 'A') {
                            $tr_types = ['IA'];
                        }  else {
                            return (object)[
                                'status' => FALSE,
                                'message' => 'Please insert TR Type',
                                'response_time' => (microtime(true) - LARAVEL_START),
                                'data' => NULL
                            ];
                        }
                        
                        $data = DB::connection("sqlSrvFPI")
                                ->table('inv_trans_dtl')
                                ->where('office_id', $arrayParams->office_id)
                                ->where('tr_type', $arrayParams->tr_type)
                                ->where('tr_id', $arrayParams->tr_id)
                                ->where('seq_id', $arrayParams->seq_id)
                                ->first();
                        // dd($data);
                        if ($data){
                            $cek_stock = DB::connection("sqlSrvFPI")
                                        ->table('inv_bal')
                                        ->where('office_id', $arrayParams->office_id)
                                        ->where('article_id',$arrayParams->article_id)
                                        ->where('defect',$arrayParams->defect ?? 0)
                                        ->where('batch_id', $arrayParams->batch_id ?? ' ')
                                        // ->where('length', $arrayParams->length)
                                        ->where('add_id', $arrayParams->add_id ?? ' ')
                                        ->where('tor_id', $arrayParams->tor_id ?? ' ')
                                        ->where('wh_id', $arrayParams->wh_id)
                                        ->where('bin_id',$arrayParams->bin_id)
                                        ->where('spk_id',$arrayParams->spk_id ?? ' ')
                                        ->where('spk_item',$arrayParams->spk_item ?? 0)
                                        ->first();
                            // dd($cek_stock);
                            // dd($data->qty, $cek_stock->qty_oh);
                            if ((float) $data->qty > 0) {
                                if ($cek_stock) {
                                    if ((float) $cek_stock->qty_oh < (float) $data->qty) {
                                        return (object)[
                                            'status' => FALSE,
                                            'message' => 'Maaf stock anda kurang',
                                            'response_time' => (microtime(true) - LARAVEL_START),
                                            'data' => NULL
                                        ];
                                    }
                                } else {
                                    return (object)[
                                        'status' => FALSE,
                                        'message' => 'Maaf stock anda belum ada',
                                        'response_time' => (microtime(true) - LARAVEL_START),
                                        'data' => NULL
                                    ];
                                } 
                            }

                            $delete = DB::connection("sqlSrvFPI")
                                    ->table('inv_trans_dtl')
                                    ->where('office_id', $arrayParams->office_id)
                                    ->where('tr_type', $arrayParams->tr_type)
                                    ->where('tr_id', $arrayParams->tr_id)
                                    ->where('seq_id', $arrayParams->seq_id)
                                    ->delete();

                            $data_hdr_wh_transfer = DB::connection("sqlSrvFPI")
                                                    ->table('inv_trans_dtl')
                                                    ->where('office_id', $arrayParams->office_id)
                                                    ->where('tr_type', $arrayParams->tr_type)
                                                    ->where('tr_id', $arrayParams->tr_id)
                                                    ->count();

                            if ((int) $data_hdr_wh_transfer == 0) {
                                $delete_wh_hdr_transfer = DB::connection("sqlSrvFPI")
                                                            ->table('inv_trans_hdr')
                                                            ->where('office_id', $arrayParams->office_id)
                                                            ->where('tr_type', $arrayParams->tr_type)
                                                            ->where('tr_id', $arrayParams->tr_id)
                                                            ->delete();
                            }


                            $transQty = $this->GetQtyTransInvDetil($arrayParams, $tr_type, $tr_types);
                            
                            $updateQty = $this->updateQtyInvBal($transQty ? ($transQty->qty ? (float) $transQty->qty : 0) : 0, $transQty ? ($transQty->wgt ? (double) $transQty->wgt : 0) : 0, $arrayParams, $tr_type, $tr_types);

                            return (object)[
                                'status' => TRUE,
                                'message' => 'Successfully saved',
                                'response_time' => (microtime(true) - LARAVEL_START),
                                'data' => NULL
                            ];


                        } else {
                            return (object)[
                                'status' => FALSE,
                                'message' => 'Data not exists',
                                'response_time' => (microtime(true) - LARAVEL_START),
                                'data' => NULL
                            ];
                    }                    
                // }  else {
                //     return (object)[
                //         'status' => FALSE,
                //         'message' => 'Belum di closing',
                //         'response_time' => (microtime(true) - LARAVEL_START),
                //         'data' => NULL
                //     ];
                // }                          
                
            } else {
                return (object)[
                    'status' => FALSE,
                    'message' => 'Belum di closing',
                    'response_time' => (microtime(true) - LARAVEL_START),
                    'data' => NULL
                ];
            }   
        } 
        else {
            return (object)[
                'status' => FALSE,
                'message' => 'Belum di closing',
                'response_time' => (microtime(true) - LARAVEL_START),
                'data' => NULL
            ];
        } 

    }

    private function GetQtyTransInvDetil($arrayParams, $tr_type, $tr_types)
    {
        $data = DB::connection('sqlSrvFPI')
                    ->table('inv_trans_dtl')
                    ->selectRaw('sum(qty) as qty, sum(wgt) as wgt')
                    ->where('office_id', $arrayParams->office_id)
                    ->whereIn('tr_type', $tr_types)
                    ->where('article_id', $arrayParams->article_id)
                    ->where('wh_id', $arrayParams->wh_id ?? ' ')
                    ->where('bin_id', $arrayParams->bin_id ?? ' ')
                    // ->where('batch_id', $arrayParams->batch_id ?? ' ')
                    // ->where('period', $arrayParams->period ?? ' ')
                    // ->where('add_id', $arrayParams->add_id ?? ' ')
                    // ->where('tor_id', $arrayParams->tor_id ?? ' ')
                    // ->where('length', $arrayParams->length)
                    // ->where('defect', $arrayParams->defect ?? 0)
                    // ->where('spk_id', $arrayParams->spk_id ?? ' ')
                    // ->where('spk_item', $arrayParams->spk_item ?? 0)                    
                    ->first();
        // dd($data);
        return $data;
    }

    public function updateQtyInvBal($QtyTrans, $WgtTrans, $arrayParams, $tr_type, $tr_types)
    {
        $data = DB::connection("sqlSrvFPI")
                ->table('inv_bal')
                ->where('office_id', $arrayParams->office_id)
                ->where('article_id',$arrayParams->article_id)
                ->where('defect',$arrayParams->defect ?? 0)
                ->where('batch_id', $arrayParams->batch_id ?? ' ')
                ->where('length', $arrayParams->length)
                ->where('add_id', $arrayParams->add_id ?? ' ')
                ->where('tor_id', $arrayParams->tor_id ?? ' ')
                ->where('wh_id', $arrayParams->wh_id ?? ' ')
                ->where('bin_id',$arrayParams->bin_id ?? ' ')
                ->where('spk_id',$arrayParams->spk_id ?? ' ')
                ->where('spk_item',$arrayParams->spk_item ?? 0)
                ->first();

        if ($data) {
            if ($tr_type == 'R') {
                $hitung_qty_oh = (float) $data->qty_awal + (float) $QtyTrans - (float) $data->qty_iss + (float) $data->qty_ia - (float) $data->qty_waste;
                $hitung_wgt_oh = (double) $data->wgt_awal + (double) $WgtTrans - (double) $data->wgt_iss + (double) $data->wgt_ia - (double) $data->wgt_waste;

                $update = DB::connection("sqlSrvFPI")
                        ->table('inv_bal')
                        ->where('office_id', $arrayParams->office_id)
                        ->where('article_id',$arrayParams->article_id)
                        ->where('defect',$arrayParams->defect ?? 0)
                        ->where('batch_id', $arrayParams->batch_id ?? ' ')
                        ->where('length', $arrayParams->length)
                        ->where('add_id', $arrayParams->add_id ?? ' ')
                        ->where('tor_id', $arrayParams->tor_id ?? ' ')
                        ->where('wh_id', $arrayParams->wh_id ?? ' ')
                        ->where('bin_id',$arrayParams->bin_id ?? ' ')
                        ->where('spk_id',$arrayParams->spk_id ?? ' ')
                        ->where('spk_item',$arrayParams->spk_item ?? 0)
                        ->update([
                            'qty_rcv' => $QtyTrans,
                            'wgt_rcv' => $WgtTrans,
                            'qty_oh' => (float) $hitung_qty_oh,
                            'wgt_oh' => (double) $hitung_wgt_oh,
                            'dt_modified' => now('Asia/Jakarta'),
                            'user_id' => $arrayParams->user_id
                        ]);

            } elseif ($tr_type == 'I') {
                $hitung_qty_oh = (float) $data->qty_awal + (float) $data->qty_rcv - (float) $QtyTrans + (float) $data->qty_ia - (float) $data->qty_waste;
                $hitung_wgt_oh = (double) $data->wgt_awal + (double) $data->wgt_rcv - (double) $WgtTrans + (double) $data->wgt_ia - (double) $data->wgt_waste;

                $update = DB::connection("sqlSrvFPI")
                        ->table('inv_bal')
                        ->where('office_id', $arrayParams->office_id)
                        ->where('article_id',$arrayParams->article_id)
                        ->where('defect',$arrayParams->defect ?? 0)
                        ->where('batch_id', $arrayParams->batch_id ?? ' ')
                        ->where('length', $arrayParams->length)
                        ->where('add_id', $arrayParams->add_id ?? ' ')
                        ->where('tor_id', $arrayParams->tor_id ?? ' ')
                        ->where('wh_id', $arrayParams->wh_id ?? ' ')
                        ->where('bin_id',$arrayParams->bin_id ?? ' ')
                        ->where('spk_id',$arrayParams->spk_id ?? ' ')
                        ->where('spk_item',$arrayParams->spk_item ?? 0)
                        ->update([
                            'qty_iss' => $QtyTrans,
                            'wgt_iss' => $WgtTrans,
                            'qty_oh' => (float) $hitung_qty_oh,
                            'wgt_oh' => (double) $hitung_wgt_oh,
                            'dt_modified' => now('Asia/Jakarta'),
                            'user_id' => $arrayParams->user_id
                        ]);

            } elseif ($tr_type == 'A') {
                $hitung_qty_oh = (float) $data->qty_awal + (float) $data->qty_rcv - (float) $data->qty_iss + (float) $QtyTrans - (float) $data->qty_waste;
                $hitung_wgt_oh = (double) $data->wgt_awal + (double) $data->wgt_rcv - (double) $data->wgt_iss + (double) $WgtTrans - (double) $data->wgt_waste;


                $update = DB::connection("sqlSrvFPI")
                        ->table('inv_bal')
                        ->where('office_id', $arrayParams->office_id)
                        ->where('article_id',$arrayParams->article_id)
                        ->where('defect',$arrayParams->defect ?? 0)
                        ->where('batch_id', $arrayParams->batch_id ?? ' ')
                        ->where('length', $arrayParams->length)
                        ->where('add_id', $arrayParams->add_id ?? ' ')
                        ->where('tor_id', $arrayParams->tor_id ?? ' ')
                        ->where('wh_id', $arrayParams->wh_id ?? ' ')
                        ->where('bin_id',$arrayParams->bin_id ?? ' ')
                        ->where('spk_id',$arrayParams->spk_id ?? ' ')
                        ->where('spk_item',$arrayParams->spk_item ?? 0)
                        ->update([
                            'qty_ia' => $QtyTrans,
                            'wgt_ia' => $WgtTrans,
                            'qty_oh' => (float) $hitung_qty_oh,
                            'wgt_oh' => (double) $hitung_wgt_oh,
                            'dt_modified' => now('Asia/Jakarta'),
                            'user_id' => $arrayParams->user_id
                        ]);

            } else {
                $hitung_qty_oh = 0;
                $hitung_wgt_oh = 0;
            }
        } else {
            if ($tr_type == 'R') {
                $insert = DB::connection("sqlSrvFPI")
                        ->table('inv_bal')
                        ->insert([
                            'office_id' => $arrayParams->office_id,
                            'article_id' => $arrayParams->article_id,
                            'defect' => $arrayParams->defect ?? 0,
                            'batch_id' => $arrayParams->batch_id ?? " ",
                            'length' => $arrayParams->length,
                            'add_id' => $arrayParams->add_id ?? " ",
                            'tor_id' => $arrayParams->tor_id ?? " ",
                            'wh_id' => $arrayParams->wh_id ?? " ",
                            'bin_id' => $arrayParams->bin_id ?? " ",
                            'spk_id' => $arrayParams->spk_id ?? ' ',
                            'spk_item' => $arrayParams->spk_item ?? 0,
                            'qty_awal' => 0,
                            'qty_oh' => 0 + 0 + $QtyTrans - 0 + 0 - 0 - 0,
                            'qty_rcv' => $QtyTrans,
                            'qty_iss' => 0,
                            'qty_ia' => 0,
                            'qty_waste' => 0,
                            'wgt_awal' => 0,
                            'wgt_oh' => 0 + 0 + $WgtTrans - 0 + 0 - 0 - 0,
                            'wgt_rcv' => $WgtTrans,
                            'wgt_iss' => 0,
                            'wgt_ia' => 0,
                            'wgt_waste' => 0,
                            'avg_price' => 0,
                            'amt_balance' => 0,
                            'dt_modified' => now('Asia/Jakarta'),
                            'user_id' => $arrayParams->user_id
                        ]);

            } elseif ($tr_type == 'A') {
                $insert = DB::connection("sqlSrvFPI")
                        ->table('inv_bal')
                        ->insert([
                            'office_id' => $arrayParams->office_id,
                            'article_id' => $arrayParams->article_id,
                            'defect' => $arrayParams->defect ?? 0,
                            'batch_id' => $arrayParams->batch_id ?? " ",
                            'length' => $arrayParams->length,
                            'add_id' => $arrayParams->add_id ?? " ",
                            'tor_id' => $arrayParams->tor_id ?? " ",
                            'wh_id' => $arrayParams->wh_id ?? " ",
                            'bin_id' => $arrayParams->bin_id ?? " ",
                            'spk_id' => $arrayParams->spk_id ?? " ",
                            'spk_item' => $arrayParams->spk_item ?? 0,
                            'qty_awal' => 0,
                            'qty_oh' => 0 + 0 + 0 - 0 + $QtyTrans - 0 - 0,
                            'qty_rcv' => 0,
                            'qty_iss' => 0,
                            'qty_ia' => $QtyTrans,
                            'qty_waste' => 0,
                            'wgt_awal' => 0,
                            'wgt_oh' => 0 + 0 + 0 - 0 + $WgtTrans - 0 - 0,
                            'wgt_rcv' => 0,
                            'wgt_iss' => 0,
                            'wgt_ia' => $WgtTrans,
                            'wgt_waste' => 0,
                            'avg_price' => 0,
                            'amt_balance' => 0,
                            'dt_modified' => now('Asia/Jakarta'),
                            'user_id' => $arrayParams->user_id
                        ]);

            } elseif ($tr_type == 'I') {
                $insert = DB::connection("sqlSrvFPI")
                        ->table('inv_bal')
                        ->insert([
                            'office_id' => $arrayParams->office_id,
                            'article_id' => $arrayParams->article_id,
                            'defect' => $arrayParams->defect ?? 0,
                            'batch_id' => $arrayParams->batch_id ?? " ",
                            'length' => $arrayParams->length,
                            'add_id' => $arrayParams->add_id ?? " ",
                            'tor_id' => $arrayParams->tor_id ?? " ",
                            'wh_id' => $arrayParams->wh_id ?? " ",
                            'bin_id' => $arrayParams->bin_id ?? " ",
                            'spk_id' => $arrayParams->spk_id ?? " ",
                            'spk_item' => $arrayParams->spk_item ?? 0,
                            'qty_awal' => 0,
                            'qty_oh' => 0 + 0 + 0 - $QtyTrans + 0 - 0 - 0,
                            'qty_rcv' => 0,
                            'qty_iss' => $QtyTrans,
                            'qty_ia' => 0,
                            'qty_waste' => 0,
                            'wgt_awal' => 0,
                            'wgt_oh' => 0 + 0 + 0 - $WgtTrans + 0 - 0 - 0,
                            'wgt_rcv' => 0,
                            'wgt_iss' => $WgtTrans,
                            'wgt_ia' => 0,
                            'wgt_waste' => 0,
                            'avg_price' => 0,
                            'amt_balance' => 0,
                            'dt_modified' => now('Asia/Jakarta'),
                            'user_id' => $arrayParams->user_id
                        ]);

            }
        }

        return $data;
    }

    private function caseTrType($tr_type)
    {
        if ($tr_type == 'IS' || $tr_type == 'IW' || $tr_type == 'MI' || $tr_type == 'TI' || $tr_type == 'PR' || $tr_type == 'SA') {
            $tr_type = 'I';
            // Issue
        } elseif ($tr_type == 'MR' || $tr_type == 'RC' || $tr_type == 'RW' || $tr_type == 'SR' || $tr_type == 'TR' || $tr_type == 'RP') {
            $tr_type = 'R';
            //Receive
        } elseif ($tr_type == 'IA') {
            $tr_type = 'A';
            //Adjust
        } else {
            $tr_type = '';
        }        

        return $tr_type;
    }
}
