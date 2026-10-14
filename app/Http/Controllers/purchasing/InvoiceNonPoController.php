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
use Illuminate\Http\ResponseTrait;
use App\Services\AccountingService;
use App\Traits\getFilterTrait;

class InvoiceNonPoController extends Controller
{
    use MasterFPI, ResponseTrait, getFilterTrait;

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

        $AcNumDr = DB::connection("sqlSrvFPI")
            ->select(DB::raw("select ac_num, concat(trim(ac_name),' | ', trim(ac_num)) as ac_name from g_ledger where  ac_type in ('EX', 'AS') and active_flag = 'Y' order by ac_num"));

        $AcNumCr = DB::connection("sqlSrvFPI")
            ->select(DB::raw("select ac_num, concat(trim(ac_name),' | ', trim(ac_num)) as ac_name from g_ledger where ac_type = 'LI' and active_flag = 'Y' order by ac_num"));

        return $this->sendResponse([
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
            'AcNumDr' => $AcNumDr,
            'AcNumCr' => $AcNumCr,
        ], 'Successful');
    }

    public function getMaster(Request $request)
    {

        switch ($request->type) {
            case 'article':

                if ($request->value) {
                    $article = $this->getArticleId($request->value);
                }

                return response()->json(['article' => $article], 200);

                break;

            case 'vendor':

                if ($request->value) {
                    $vendor = $this->getArticleId($request->value);
                }

                return response()->json(['vendor' => $vendor], 200);

                break;

            default:
                # code...
                break;
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

    public function getInvoiceList(request $request)
    {

        $sSQL = "select DISTINCT a.*, f.office, b.vendor_name, ISNULL(c.num_of_days,0) as num_of_days, 
                ISNULL(e.sj_no,' ') as sj_no, d.po_id
                FROM purc_inv_mast a 
                inner JOIN vendor b ON a.vendor_id = b.vendor_id 
                LEFT OUTER JOIN pay_term c ON a.pay_term_id = c.pay_term_id
                inner JOIN purc_inv_item d ON a.office_id = d.office_id AND a.inv_id = d.inv_id
                LEFT OUTER JOIN purc_rcv_hdr e ON a.inv_id = e.inv_id
                left outer join branch_office f on a.office_id = f.office_id
                WHERE 1=1 ";

        $sqlWhere = ' ';

        if ($request->OfficeId) {
            $sqlWhere .= "and a.office_id = '$request->OfficeId'";
        }
        if ($request->VendorId) {
            $sqlWhere .= "and a.vendor_id = '$request->VendorId'";
        }
        if ($request->InvoiceNo) {
            $sqlWhere .= "and a.inv_id = '$request->InvoiceNo'";
        }
        if ($request->PoId) {
            $sqlWhere .= "and d.po_id = '$request->PoId'";
        }

        if ($request->InvoiceDate) {
            $sqlWhere .= "and invoice_date = '$request->InvoiceDate'";
        }

        if ($request->InvType) {
            if ($request->InvType == 'Y') {
                $sqlWhere .= "and a.inv_type <> 'N' ";
            } else {
                $sqlWhere .= "and a.inv_type = 'N' ";
            }
        }
        if ($request->Paid) {
            if ($request->Paid == 'Y') {
                $sqlWhere .= "and a.paid_flag = 'P' ";
            } else {
                $sqlWhere .= "and a.paid_flag != 'P' ";
            }
        }

        if ($request->dtawal) {
            if ($request->dtakhir) {
                $sqlWhere .= "and a.dt_inv between '" . $request->dtawal . " 00:00:00' and '" . $request->dtakhir . " 23:59:59' ";
            } else {
                $sqlWhere .= "and a.dt_inv = '$request->dtawal' ";
            }
        }

        try {
            $invoice = DB::connection("sqlSrvFPI")
                ->select(DB::raw($sSQL . $sqlWhere . ' ORDER BY a.dt_inv, a.inv_id '));

            return $this->sendResponse($invoice, 'Successfull');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getInvoiceDetail(request $request)
    {
        $sqlWhere = '1=1 ';
        if ($request->OfficeId) {
            $sqlWhere .= "and a.office_id = '$request->OfficeId' ";
        }

        if ($request->TrId) {
            $sqlWhere .= "and a.tr_id = '$request->TrId' ";
        }
        if ($request->InvId) {
            $sqlWhere .= "and a.inv_id = '$request->InvId' ";
        }

        $invoiceDetail = DB::connection("sqlSrvFPI")
            ->select(DB::raw("select a.office_id, a.tr_id, a.inv_id, a.inv_item, a.article_id,
                        c.description, a.qty, a.unit_meas, a.unit_price, a.amt_item, a.remark, 
                        ISNULL(s.site_name,' ') as site_name, ISNULL(t.cluster_name,' ') as cluster_name, ISNULL(h.house_name,' ') as house_name
                        FROM purc_inv_item a join purc_inv_mast b on a.office_id = b.office_id
                        and a.tr_id = b.tr_id join article c on a.article_id = c.article_id
                        left outer join pr_item d on a.pr_id = d.pr_id and a.pr_item = d.pr_item
                        left outer join site s on s.id_site=a.id_site
                        left outer join site_cluster t on a.id_site=t.id_site and a.id_cluster=t.id_cluster 
                        left outer join house_unit h on a.id_site=h.id_site and a.id_cluster=h.id_cluster and a.id_house=h.id_house 
                        WHERE $sqlWhere ORDER BY inv_item"));

        return response()->json(['invoiceDetail' => $invoiceDetail, 'sqlWhere' => $sqlWhere], 200);
    }

    public function getListHeader(Request $request)
    {
        try {
            $OptInv = $request->OptInv;
            if ($OptInv == 'R') {
                $PrHdr = DB::connection("sqlSrvFPI")
                    ->table('pr_hdr as a')
                    ->selectRaw('a.office_id, a.dt_pr, a.pr_id, a.dept_id, c.pic_name, a.memo_txt, a.vendor_id, vnd.vendor_name, TRIM(vnd.pay_term_id) as payterm')
                    ->join('department as b', 'a.dept_id', '=', 'b.dept_id')
                    ->join('pr_req_user as c', 'a.pic_id', '=', 'c.pic_id')
                    ->leftJoin('vendor as vnd', 'a.vendor_id', '=', 'vnd.vendor_id')
                    ->where('a.vendor_id', $request->vendor_id)
                    ->whereRaw('YEAR(a.dt_aprv) > 2000')
                    ->where('a.stat', 'O')
                    ->where('a.type_pr', 'N')
                    ->get();

                return $this->sendResponse($PrHdr, 'Successfull');
            }

            if ($OptInv == 'P') {
                $RcvHdr = DB::connection("sqlSrvFPI")
                    ->table('purc_rcv_hdr')
                    ->selectRaw('office_id, dt_rcv, purc_rcv_id, sj_no, memo_txt')
                    ->where('vendor_id', $request->vendor_id)
                    ->whereRaw("trim(inv_id) = ''")
                    ->get();

                return $this->sendResponse($RcvHdr, 'Successfull');
            }

            if ($OptInv == 'A') {
                $cat_id = $request->cat_id;
                $com_id = $request->com_id;

                $Article = DB::connection("sqlSrvFPI")
                    ->table('article as a')
                    ->selectRaw('a.article_id, a.description, a.uom, a.cat_id, a.com_id, b.descr as cat_name, c.com_desc as com_name')
                    ->join('category as b', 'a.cat_id', '=', 'b.cat_id')
                    ->leftjoin('commodity as c', function ($join) {
                        $join->on('a.com_id', '=', 'c.com_id')
                            ->on('a.cat_id', '=', 'c.cat_id');
                    })
                    ->when($cat_id, function ($query, $cat_id) {
                        return $query->where('a.cat_id', $cat_id);
                    })
                    ->when($com_id, function ($query, $com_id) {
                        return $query->where('a.com_id', $com_id);
                    })
                    ->where('a.active_flag', 'Y')
                    ->get();
                return $this->sendResponse($Article, 'Successfull');
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getListDtlHeader(Request $request)
    {
        try {
            $OptInv = $request->OptInv;

            if ($OptInv == 'R') {
                $PrItem = DB::connection("sqlSrvFPI")
                    ->table('pr_item as a')
                    ->selectRaw("office_id, a.pr_id, a.pr_item, a.article_id, b.description, a.qty, a.unit_meas, 
                            b.ac_num_exp as ac_num_dr, a.remark,  
                            a.unit_price, a.amount, a.id_site, isnull(c.site_name, '') as site_name,
                            a.id_cluster, isnull(d.cluster_name, '') as cluster_name, 
                            a.id_house, isnull(e.house_name, '') as house_name, a.id_house")
                    ->join('article as b', 'a.article_id', '=', 'b.article_id')
                    ->leftjoin('site as c', 'a.id_site', '=', 'c.id_site')
                    ->leftjoin('site_cluster as d', 'a.id_cluster', '=', 'd.id_cluster')
                    ->leftjoin('house_unit as e', 'a.id_house', '=', 'e.id_house')
                    ->where('a.pr_id', $request->pr_id)
                    ->where('a.stat', 'O')
                    ->get();

                return $this->sendResponse($PrItem, 'Successfull');
            }

            if ($OptInv == 'P') {
                $RcvItem = DB::connection("sqlSrvFPI")
                    ->table('purc_rcv_dtl as a')
                    ->selectRaw('a.office_id, a.purc_rcv_id, a.seq_num, a.sj_no, a.pr_id, a.pr_item, 
                            a.po_id, a.po_item, a.article_id, b.description, 
                            a.qty, a.unit_meas, a.unit_price, a.amt_item')
                    ->join('article as b', 'a.article_id', '=', 'b.article_id')
                    ->where('a.purc_rcv_id', $request->purc_rcv_id)
                    ->get();

                return $this->sendResponse($RcvItem, 'Successfull');
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getNumInvItem(Request $request)
    {

        $sqlWhere = '1=1 ';

        if ($request->TrId) {
            $sqlWhere .= "and tr_id = '$request->TrId' ";
        }
        if ($request->OfficeId) {
            $sqlWhere .= "and office_id = '$request->OfficeId' ";
        }

        $InvItem = DB::connection("sqlSrvFPI")
            // ->select(DB::raw("select isnull(max(inv_item), 0) + 1 as inv_item FROM purc_inv_item where $sqlWhere"));
            ->table('purc_inv_item')
            ->selectRaw("isnull(max(inv_item), 0) + 1 as inv_item")
            ->whereRaw($sqlWhere)
            ->first();

        return response()->json(['InvItem' => $InvItem->inv_item], 200);
    }

    private function getGenInvId($OfficeId, $DtInv, $type)
    {
        try {
            if (isset($type) && ($type == 'R' || $type == 'A')) {
                $TrId = 'PIN' . substr(date('Y', strtotime($DtInv)), -2) . date('m', strtotime($DtInv)) . $OfficeId;
                $InvId = 'PINVN' . substr(date('Y', strtotime($DtInv)), -2) . date('m', strtotime($DtInv)) . $OfficeId;
            } else {
                $TrId = 'PI' . substr(date('Y', strtotime($DtInv)), -2) . date('m', strtotime($DtInv)) . $OfficeId;
                $InvId = 'PINV' . substr(date('Y', strtotime($DtInv)), -2) . date('m', strtotime($DtInv)) . $OfficeId;
            }

            $datas = DB::connection("sqlSrvFPI")
                ->table('purc_inv_mast')
                ->selectRaw("concat('$TrId', format(isnull(max(inv_num),0) + 1,'000')) as tr_id,
                                 concat('$InvId', format(isnull(max(inv_num),0) + 1,'000')) as inv_id, 
                                 isnull(max(inv_num),0) + 1 as inv_num")
                ->where('tr_id', 'like', "$TrId%")
                ->first();

            if ($datas) {
                return [
                    'inv_num' => $datas->inv_num,
                    'tr_id' => $datas->tr_id,
                    'inv_id' => $datas->inv_id
                ];
            } else {
                return [
                    'inv_num' => null,
                    'tr_id' => null,
                    'inv_id' => null
                ];
            }
        } catch (\Exception $e) {
            return [
                'inv_num' => null,
                'tr_id' => null,
                'inv_id' => null
            ];
        }
    }

    public function getSaveInv(Request $request)
    {
        $getAcNum = $this->GetAcNum();
        $ac_num_hut = $getAcNum['acNumHut'];
        $ac_num_disc = $getAcNum['acNumDisc'];
        $ac_num_bbt = $getAcNum['acNumBbt'];
        $ac_num_ppn = $getAcNum['acNumPpn'];
        $ac_num_pph = $getAcNum['acNumPph'];
        $ac_num_pph21 = $getAcNum['acNumPph21'];
        $ac_num_pph4 = $getAcNum['acNumPph4'];
        $kurs = $request->kurs ?? 1;

        try {

            $tr_id = $request->tr_id ?? '';
            $detailArr = $request->detail ?? [];

            if ($tr_id == '') {
                $inv = $this->getGenInvId($request->office_id, $request->tanggal, $request->type);
                $tr_id = $inv['tr_id'];
                $inv_id = $inv['inv_id'];
                $tr_num = $inv['inv_num'];
                $pr_id = @$detailArr[0]['pr_id'] ?? '';

                DB::connection("sqlSrvFPI")->beginTransaction();
                DB::connection("sqlSrvFPI")
                    ->table('purc_inv_mast')
                    ->insert([
                        'office_id'         => $request->office_id ?? '',
                        'tr_id'             => $tr_id ?? '',
                        'inv_id'            => $inv_id ?? '',
                        'tr_num'            => $tr_num ?? 0,
                        'inv_num'           => $tr_num ?? 0,
                        'vendor_id'         => $request->vendor_id ?? '',
                        'vendor_inv'        => $request->vendor_invoice ?? '',
                        'dt_inv'            => $request->tanggal ?? '1900-01-01',
                        'dt_ship'           => $request->tanggal ?? '1900-01-01',
                        'dt_inv_tt'         => $request->tanggal_tt ?? '1900-01-01',
                        'dt_ext_inv'        => $request->ext_inv_date ?? '1900-01-01',
                        'pay_term_id'       => $request->payterm_id ?? '',
                        'curr_id'           => $request->currency_id ?? '',
                        'pr_id'             => $pr_id ?? '',
                        'tax_inv'           => $request->faktur_pajak ?? '',
                        'rate'              => $request->kurs ?? 0,
                        'amt_subtotal'      => $request->sub_total ?? 0,
                        'amt_disc'          => $request->disc ?? 0,
                        'amt_ppn'           => $request->ppn ?? 0,
                        'ppn'               => $request->pct_ppn ?? 0,
                        'amt_pph'           => $request->pph23 ?? 0,
                        'pct_pph'           => $request->pct_pph23 ?? 0,
                        'amt_pph21'         => $request->pph21 ?? 0,
                        'pct_pph21'         => $request->pct_pph21 ?? 0,
                        'amt_pph4'          => $request->pph4 ?? 0,
                        'pct_pph4'          => $request->pct_pph4 ?? 0,
                        'amt_total'         => $request->total ?? 0,
                        'inv_said'          => terbilang($request->total ?? ''),
                        'inv_type'          => ($request->type == 'R' || $request->type == 'A') ? 'N' : '',
                        'ac_num_hut'        => $ac_num_hut ?? '',
                        'ac_num_disc'       => $ac_num_disc ?? '',
                        'ac_num_bbt'        => $ac_num_bbt ?? '',
                        'ac_num_ppn'        => $ac_num_ppn ?? '',
                        'ac_num_pph'        => $ac_num_pph ?? '',
                        'ac_num_pph21'      => $ac_num_pph21 ?? '',
                        'ac_num_pph4'       => $ac_num_pph4 ?? '',
                        'tax1'              => $request->tax1 ?? '',
                        'tax2'              => $request->tax2 ?? '',
                        'tax3'              => $request->tax3 ?? '',
                        'tax4'              => $request->tax4 ?? '',
                        'remark'            => $request->memo ?? '',
                        'dt_due'            => date('Y-m-d', strtotime(($request->tanggal ?? '1900-01-01') . ' +' . ($request->payterm_days ?? 0) . ' days')),
                        'dt_created'        => DB::raw('getdate()'),
                        'dt_modified'       => DB::raw('getdate()'),
                        'user_id'           => getAuth($request)->user_id2 ?? ''
                    ]);


                foreach ($detailArr as $key => $value) {

                    $value = (object) $value;
                    $dataInsert = [
                        'office_id'         => $request->office_id ?? '',
                        'tr_id'             => $tr_id ?? '',
                        'seq_num'           => $key + 1,
                        'inv_id'            => $inv_id ?? '',
                        'inv_item'          => $key + 1,
                        'sj_no'             => $value->sj_no ?? '',
                        'po_id'             => $value->po_id ?? '',
                        'po_item'           => $value->po_item ?? '',
                        'pr_id'             => $value->pr_id ?? '',
                        'pr_item'           => $value->pr_item ?? '',
                        'article_id'        => $value->article_id ?? '',
                        'qty'               => $value->qty ?? 0,
                        'unit_meas'         => $value->unit_meas ?? '',
                        'unit_price'        => $value->price ?? 0,
                        'amt_item'          => $value->amount ?? 0,
                        'remark'            => $value->remark ?? '',
                        'inv_rate'          => $kurs ?? 0,
                        'lpb_rate'          => $kurs ?? 0,
                        'ac_num_diff_rate'  => $value->ac_num_dr ?? '',
                        'ac_num_bbt'        => $value->ac_num_cr ?? '',
                        'dt_created'        => DB::raw('getdate()'),
                        'dt_modified'       => DB::raw('getdate()'),
                        'id_site'           => $value->site_id ?? '',
                        'id_cluster'        => $value->cluster_id ?? '',
                        'id_house'          => $value->house_id ?? '',
                        'user_id'           => getAuth($request)->user_id2 ?? ''

                    ];

                    DB::connection("sqlSrvFPI")
                        ->table('purc_inv_item')
                        ->insert($dataInsert);

                    if ($request->type == 'R') {
                        // Update qty_po dan stat pada pr_item
                        $sumQty = DB::connection("sqlSrvFPI")
                            ->table('purc_inv_item')
                            ->where('pr_id', $value->pr_id)
                            ->where('pr_item', $value->pr_item)
                            ->sum('qty');

                        $qtyPr = DB::connection("sqlSrvFPI")
                            ->table('pr_item')
                            ->where('pr_id', $value->pr_id)
                            ->where('pr_item', $value->pr_item)
                            ->value('qty');

                        DB::connection("sqlSrvFPI")
                            ->table('pr_item')
                            ->where('pr_id', $value->pr_id)
                            ->where('pr_item', $value->pr_item)
                            ->update([
                                'qty_po' => $sumQty,
                                'stat' => ($sumQty >= $qtyPr) ? 'C' : 'O'
                            ]);

                        // Update stat pr_hdr jika semua item sudah 'C'
                        $isClosed = !DB::connection("sqlSrvFPI")
                            ->table('pr_item')
                            ->where('pr_id', $value->pr_id)
                            ->where('stat', 'O')
                            ->exists();

                        DB::connection("sqlSrvFPI")
                            ->table('pr_hdr')
                            ->where('pr_id', $value->pr_id)
                            ->update(['stat' => $isClosed ? 'C' : 'O']);
                    }

                    if ($request->type == 'P') {
                        foreach (['purc_rcv_dtl', 'purc_rcv_hdr'] as $table) {
                            $updateData = ['inv_id' => $inv_id];
                            if ($table === 'purc_rcv_dtl') {
                                $updateData += [
                                    'tr_id_invoice' => $tr_id,
                                    'inv_item' => $key + 1,
                                    'seq_num_invoice' => $key + 1
                                ];
                            }
                            $dbSave = DB::connection("sqlSrvFPI")
                                ->table($table)
                                ->where('sj_no', $value->sj_no);

                            if ($table === 'purc_rcv_dtl') {
                                $dbSave->where('po_id', $value->po_id)
                                    ->where('po_item', $value->po_item);
                            }

                            $dbSave->update($updateData);
                        }
                    }
                }

                DB::connection("sqlSrvFPI")->commit();
                if ($request->type == 'R' || $request->type == 'A') {
                    $this->ServiceAccounting->JrnlPurcInvNonPO(1, $tr_id, date("Y", strtotime($request->tanggal)), getAuth($request)->user_id2);
                }
                if ($request->type == 'P') {
                    $this->ServiceAccounting->JrnlPurcInv(1, $tr_id, date("Y", strtotime($request->tanggal)), getAuth($request)->user_id2);
                }

                return $this->sendResponse([], 'Save Inv Successfull');
            } else {
                $allowedPaidSave = $this->getEnvConf('FINANCE', 'FINAL_CLOSING', getAuth($request)->user_id2 ?? '');

                $datas = DB::connection("sqlSrvFPI")
                    ->table('purc_inv_mast')
                    ->selectRaw('amt_paid, inv_id, tr_id, office_id')
                    ->where('office_id', $request->office_id)
                    ->where('tr_id', $request->tr_id)
                    ->first();

                if ($datas->amt_paid > 0 && $allowedPaidSave != 'Y') {
                    return $this->sendError('Invoice already paid', 'Error', 500);
                }

                $inv_id = $datas->inv_id;
                $tr_id = $datas->tr_id;

                $dataUpdate = [
                    'vendor_inv'        => $request->vendor_invoice ?? '',
                    'dt_inv'            => $request->tanggal ?? '1900-01-01',
                    'dt_inv_tt'         => $request->tanggal_tt ?? '1900-01-01',
                    'dt_ship'           => $request->tanggal ?? '1900-01-01',
                    'dt_ext_inv'        => $request->ext_inv_date ?? '1900-01-01',
                    'pay_term_id'       => $request->payterm_id ?? '',
                    'curr_id'           => $request->currency_id ?? '',
                    'rate'              => $request->kurs ?? 0,
                    'amt_subtotal'      => $request->sub_total ?? 0,
                    'amt_disc'          => $request->disc ?? 0,
                    'amt_ppn'           => $request->ppn ?? 0,
                    'ppn'               => $request->pct_ppn ?? 0,
                    'tax_inv'           => $request->faktur_pajak ?? '',
                    'amt_pph'           => $request->pph23 ?? 0,
                    'pct_pph'           => $request->pct_pph23 ?? 0,
                    'amt_pph21'         => $request->pph21 ?? 0,
                    'pct_pph21'         => $request->pct_pph21 ?? 0,
                    'amt_pph4'          => $request->pph4 ?? 0,
                    'pct_pph4'          => $request->pct_pph4 ?? 0,
                    'amt_total'         => $request->total ?? 0,
                    'inv_said'          => terbilang($request->total ?? ''),
                    'amt_disc'          => $request->disc ?? 0,
                    'amt_ppn'           => $request->ppn ?? 0,
                    'ppn'               => $request->pct_ppn ?? 0,
                    'amt_pph'           => $request->pph23 ?? 0,
                    'pct_pph'           => $request->pct_pph23 ?? 0,
                    'amt_pph21'         => $request->pph21 ?? 0,
                    'pct_pph21'         => $request->pct_pph21 ?? 0,
                    'amt_total'         => $request->total ?? 0,
                    'inv_said'          => terbilang($request->total ?? ''),
                    'inv_type'          => ($request->type == 'R' || $request->type == 'A') ? 'N' : '',
                    'tax1'              => $request->tax1 ?? '',
                    'tax2'              => $request->tax2 ?? '',
                    'tax3'              => $request->tax3 ?? '',
                    'tax4'              => $request->tax4 ?? '',
                    'remark'            => $request->memo ?? '',
                    'dt_due'            => date('Y-m-d', strtotime(($request->tanggal ?? '1900-01-01') . ' +' . ($request->payterm_days ?? 0) . ' days')),
                    'dt_modified'       => DB::raw('getdate()'),
                    'user_id'           => getAuth($request)->user_id2 ?? ''

                ];

                DB::connection("sqlSrvFPI")->beginTransaction();
                DB::connection("sqlSrvFPI")
                    ->table('purc_inv_mast')
                    ->where('tr_id', $request->tr_id)
                    ->where('office_id', $request->office_id)
                    ->update($dataUpdate);

                DB::connection("sqlSrvFPI")
                    ->table('purc_inv_item')
                    ->where('tr_id', $request->tr_id)
                    ->where('office_id', $request->office_id)
                    ->delete();

                $detailArr = $request->detail ?? [];
                foreach ($detailArr as $key => $value) {
                    $value = (object) $value;

                    // if ($value->seq_num) {
                    //     $dataUpdate = [
                    //         'article_id'        => $value->article_id ?? '',
                    //         'amt_item'          => $value->amount ?? 0,
                    //         'qty'               => $value->qty ?? 0,
                    //         'unit_meas'         => $value->unit_meas ?? '',
                    //         'unit_price'        => $value->price ?? 0,
                    //         'amt_item'          => $value->amount ?? 0,
                    //         'inv_rate'          => $kurs ?? 0,
                    //         'lpb_rate'          => $kurs ?? 0,
                    //         'remark'            => $value->remark ?? '',
                    //         'ac_num_diff_rate'  => $value->ac_num_dr ?? '',
                    //         'ac_num_bbt'        => $value->ac_num_cr ?? '',
                    //         'id_site'           => $value->site_id ?? '',
                    //         'id_cluster'        => $value->cluster_id ?? '',
                    //         'id_house'          => $value->house_id ?? '',
                    //         'dt_modified'       => DB::raw('getdate()'),
                    //         'user_id'           => getAuth($request)->user_id2 ?? ''
                    //     ];
                    //     DB::connection("sqlSrvFPI")
                    //         ->table('purc_inv_item')
                    //         ->where('tr_id', $request->tr_id)
                    //         ->where('office_id', $request->office_id)
                    //         ->where('seq_num', $key + 1)
                    //         ->update($dataUpdate);
                    // } else {
                    $dataInsert = [
                        'office_id'         => $request->office_id ?? '',
                        'tr_id'             => $tr_id ?? '',
                        'seq_num'           => $key + 1,
                        'inv_id'            => $inv_id ?? '',
                        'inv_item'          => $key + 1,
                        'sj_no'             => $value->sj_no ?? '',
                        'po_id'             => $value->po_id ?? '',
                        'po_item'           => $value->po_item ?? '',
                        'pr_id'             => $value->pr_id ?? '',
                        'pr_item'           => $value->pr_item ?? '',
                        'article_id'        => $value->article_id ?? '',
                        'qty'               => $value->qty ?? 0,
                        'unit_meas'         => $value->unit_meas ?? '',
                        'unit_price'        => $value->price ?? 0,
                        'amt_item'          => $value->amount ?? 0,
                        'remark'            => $value->remark ?? '',
                        'inv_rate'          => $kurs ?? 0,
                        'lpb_rate'          => $kurs ?? 0,
                        'ac_num_diff_rate'  => $value->ac_num_dr ?? '',
                        'ac_num_bbt'        => $value->ac_num_cr ?? '',
                        'dt_created'        => DB::raw('getdate()'),
                        'dt_modified'       => DB::raw('getdate()'),
                        'id_site'           => $value->site_id ?? '',
                        'id_cluster'        => $value->cluster_id ?? '',
                        'id_house'          => $value->house_id ?? '',
                        'user_id'           => getAuth($request)->user_id2 ?? ''

                    ];

                    DB::connection("sqlSrvFPI")
                        ->table('purc_inv_item')
                        ->insert($dataInsert);

                    if ($request->type == 'R') {
                        // Update qty_po dan stat pada pr_item
                        $sumQty = DB::connection("sqlSrvFPI")
                            ->table('purc_inv_item')
                            ->where('pr_id', $value->pr_id)
                            ->where('pr_item', $value->pr_item)
                            ->sum('qty');

                        $qtyPr = DB::connection("sqlSrvFPI")
                            ->table('pr_item')
                            ->where('pr_id', $value->pr_id)
                            ->where('pr_item', $value->pr_item)
                            ->value('qty');

                        DB::connection("sqlSrvFPI")
                            ->table('pr_item')
                            ->where('pr_id', $value->pr_id)
                            ->where('pr_item', $value->pr_item)
                            ->update([
                                'qty_po' => $sumQty,
                                'stat' => ($sumQty >= $qtyPr) ? 'C' : 'O'
                            ]);

                        // Update stat pr_hdr jika semua item sudah 'C'
                        $isClosed = !DB::connection("sqlSrvFPI")
                            ->table('pr_item')
                            ->where('pr_id', $value->pr_id)
                            ->where('stat', 'O')
                            ->exists();

                        DB::connection("sqlSrvFPI")
                            ->table('pr_hdr')
                            ->where('pr_id', $value->pr_id)
                            ->update(['stat' => $isClosed ? 'C' : 'O']);
                    }

                    if ($request->type == 'P') {
                        foreach (['purc_rcv_dtl', 'purc_rcv_hdr'] as $table) {
                            $updateData = ['inv_id' => $inv_id];
                            if ($table === 'purc_rcv_dtl') {
                                $updateData += [
                                    'tr_id_invoice' => $tr_id,
                                    'inv_item' => $key + 1,
                                    'seq_num_invoice' => $key + 1
                                ];
                            }
                            $dbSave = DB::connection("sqlSrvFPI")
                                ->table($table)
                                ->where('sj_no', $value->sj_no);

                            if ($table === 'purc_rcv_dtl') {
                                $dbSave->where('po_id', $value->po_id)
                                    ->where('po_item', $value->po_item);
                            }


                            $dbSave->update($updateData);
                        }
                    }
                }

                DB::connection("sqlSrvFPI")->commit();
                if ($request->type == 'R' || $request->type == 'A') {
                    $this->ServiceAccounting->JrnlPurcInvNonPO(1, $tr_id, date("Y", strtotime($request->tanggal)), getAuth($request)->user_id2);
                }
                if ($request->type == 'P') {
                    $this->ServiceAccounting->JrnlPurcInv(1, $tr_id, date("Y", strtotime($request->tanggal)), getAuth($request)->user_id2);
                }
                return $this->sendResponse([], 'Invoice updated successfully');
            }
        } catch (\Exception $e) {
            DB::connection("sqlSrvFPI")->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getDataInv(request $request)
    {
        $sqlwhere = '1=1 ';
        if ($request->tr_id) {
            $sqlwhere .= "and a.tr_id = '$request->tr_id' ";
        }
        if ($request->office_id) {
            $sqlwhere .= "and a.office_id = '$request->office_id' ";
        }

        $dataHdr = DB::connection("sqlSrvFPI")
            ->table('purc_inv_mast as a')
            ->selectRaw('a.tr_id, a.office_id, a.inv_id, a.inv_type as type, a.tr_num, a.vendor_id, 
                        b.vendor_name, a.vendor_inv as vendor_invoice, a.tax_inv as faktur_pajak, a.pr_id,
                        a.dt_inv as tanggal, a.dt_ext_inv as ext_inv_date, a.pay_term_id as payterm_id,
                        a.curr_id as currency_id, a.rate as kurs, a.amt_subtotal as sub_total, 
                        a.amt_disc as disc, a.amt_ppn as ppn, a.ppn as pct_ppn, a.amt_pph as pph23, 
                        a.pct_pph as pct_pph23, a.amt_pph21 as pph21, a.pct_pph21 as pct_pph21, 
                        a.amt_pph4, a.pct_pph4, a.amt_total as total,
                        a.ac_num_hut, a.ac_num_disc, a.ac_num_bbt, a.ac_num_ppn, a.ac_num_pph, 
                        a.ac_num_pph21, a.ac_num_pph4, a.tax1, a.tax2, a.tax3, a.tax4, a.remark as memo,
                        a.dt_inv_tt as tanggal_tt')
            ->join('vendor as b', 'a.vendor_id', '=', 'b.vendor_id')
            ->whereRaw($sqlwhere)
            ->first();

        $dataDtl = DB::connection("sqlSrvFPI")
            ->table('purc_inv_item as a')
            ->selectRaw('a.office_id, a.tr_id, a.seq_num, a.inv_id, a.inv_item, a.sj_no, a.po_id, a.po_item, a.pr_id, a.pr_item,
                            a.article_id, b.description as article_desc, a.qty, a.unit_meas, a.unit_price as price, a.remark, 
                            a.amt_item as amount, a.inv_rate, a.lpb_rate, a.ac_num_diff_rate as ac_num_dr, a.ac_num_bbt as ac_num_cr, 
                            a.id_site, c.site_name, a.id_cluster, d.cluster_name, a.id_house, e.house_name, f.purc_rcv_id')
            ->join('article as b', 'a.article_id', '=', 'b.article_id')
            ->leftJoin('site as c', 'a.id_site', '=', 'c.id_site')
            ->leftJoin('site_cluster as d', 'a.id_cluster', '=', 'd.id_cluster')
            ->leftJoin('house_unit as e', 'a.id_house', '=', 'e.id_house')
            ->leftJoin('purc_rcv_dtl as f', function ($e) {
                $e->on('a.tr_id', '=', 'f.tr_id_invoice')
                    ->on('a.seq_num', '=', 'f.seq_num_invoice');
            })
            ->whereRaw($sqlwhere)
            ->get();


        return $this->sendResponse(['dataHdr' => $dataHdr, 'dataDtl' => $dataDtl], 'Successfull');
    }

    public function getDeleteInv(request $request)
    {
        try {
            $tr_id = $request->tr_id;
            $office_id = $request->office_id;
            $type = $request->type;

            $checkPaid = DB::connection("sqlSrvFPI")
                ->table('purc_inv_mast')
                ->where('tr_id', $tr_id)
                ->where('amt_paid', '>', 0)
                ->exists();

            if ($checkPaid) {
                return $this->sendError('Invoice has been paid', 'Error', 500);
            }

            DB::connection("sqlSrvFPI")->beginTransaction();
            $seq_num_inv = DB::connection("sqlSrvFPI")
                ->table('purc_inv_item')
                ->selectRaw('tr_id, office_id, seq_num, inv_id, inv_item, sj_no, po_id, po_item, pr_id, pr_item, article_id, qty, sj_no')
                ->where('tr_id', $tr_id)
                ->where('office_id', $office_id)
                ->orderBy('seq_num', 'asc')
                ->get();

            foreach ($seq_num_inv as $key => $value) {
                if ($type == 'R') {
                    DB::connection("sqlSrvFPI")
                        ->table('pr_item')
                        ->where('pr_id', $value->pr_id)
                        ->where('pr_item', $value->pr_item)
                        ->where('office_id', $value->office_id)
                        ->update([
                            'qty_po' => DB::raw('qty_po - ' . $value->qty),
                            'stat'   => 'O'
                        ]);

                    DB::connection("sqlSrvFPI")
                        ->table('pr_hdr')
                        ->where('pr_id', $value->pr_id)
                        ->where('office_id', $value->office_id)
                        ->update(['stat' => 'O']);
                }

                if ($type == 'P') {
                    DB::connection("sqlSrvFPI")
                        ->table('purc_rcv_dtl')
                        ->where('inv_id', $value->inv_id)
                        ->where('inv_item', $value->inv_item)
                        ->where('office_id', $value->office_id)
                        ->update([
                            'inv_id' => ' ',
                            'inv_item' => 0,
                            'tr_id_invoice' => ' ',
                            'seq_num_invoice' => 0
                        ]);

                    DB::connection("sqlSrvFPI")
                        ->table('purc_rcv_hdr')
                        ->where('inv_id', $value->inv_id)
                        ->where('office_id', $value->office_id)
                        ->update(['inv_id' => ' ']);
                }
            }

            $datas = DB::connection("sqlSrvFPI")
                ->table('purc_inv_mast')
                ->selectRaw('tr_id, office_id, reff_id')
                ->where('tr_id', $tr_id)
                ->where('office_id', $office_id)
                ->where('reff_id', '!=', ' ')
                ->first();

            if ($datas) {
                $reff_id = $datas->reff_id;

                DB::connection("sqlSrvFPI")
                    ->table('qc_opname')
                    ->where('tr_id', $reff_id)
                    ->update([
                        'dt_confirmed' => '1900-01-01 00:00:00',
                        'confirmed_by' => ' ',
                    ]);

                DB::connection("sqlSrvFPI")
                    ->table('kb_hdr')
                    ->where('qc_opname_id', $reff_id)
                    ->update([
                        'qc_opname_id' => ' '
                    ]);
            }

            DB::connection("sqlSrvFPI")
                ->table('purc_inv_mast')
                ->where('tr_id', $tr_id)
                ->where('office_id', $office_id)
                ->delete();

            DB::connection("sqlSrvFPI")
                ->table('purc_inv_item')
                ->where('tr_id', $tr_id)
                ->where('office_id', $office_id)
                ->delete();

            DB::connection("sqlSrvFPI")->commit();
            return $this->sendResponse([], 'Invoice deleted successfully');
        } catch (\Exception $e) {
            DB::connection("sqlSrvFPI")->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
}
