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

class PurchaseOrderController extends Controller
{
    
    use MasterFPI;

    protected $ServiceInclude;

    public function __construct(PerhitunganIncludeService $ServiceInclude)
    {
        $this->ServiceInclude = $ServiceInclude;
    }
    
    public function index(Request $request)
    {

        $GVendor = $this->getVendor("");
        $GOffice = $this->getOffice("");
        $GDepartment = $this->getDepartment("","");
        $gpic = $this->getPicName("","","");
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

    public function getListPoHdr(Request $request)
    {
        try
        {
            $sqlWhere = '1=1 ';
        
            if ($request->PoId) {
                $sqlWhere .= "and a.po_id = '$request->PoId'";
            }
            if ($request->OfficeId) {
                $sqlWhere .= "and a.office_id = '$request->OfficeId'";
            }
            if ($request->VendorId) {
                $sqlWhere .= "and a.vendor_id = '$request->VendorId'";
            }
            if ($request->Stat) {
                $sqlWhere .= "and a.stat = '$request->Stat'";
            }
            if ($request->dtawal) {
                if($request->dtakhir){
                    $sqlWhere .= "and a.dt_po between '" . $request->dtawal . " 00:00:00' and '" . $request->dtakhir . " 23:59:59' ";
                }else{
                    $sqlWhere .= "and a.dt_po = '$request->dtawal' ";
                }
            }
            
            $PoHdr = DB::connection("sqlSrvFPI")
                ->table('po_hdr as a')
                ->selectRaw("
                            a.office_id,
                            TRIM(a.po_id) AS po_id,
                            TRIM(a.vendor_id) AS vendor_id,
                            a.dt_po,
                            a.dt_shipped,
                            TRIM(a.pay_term_id) AS pay_term_id,
                            TRIM(a.pr_id) AS pr_id,
                            TRIM(a.pq_id) AS pq_id,
                            TRIM(a.vessel_no) AS vessel_no,
                            TRIM(a.vessel_name) AS vessel_name,
                            TRIM(a.dept_id) AS dept_id,
                            TRIM(a.pic_id) AS pic_id,
                            TRIM(a.curr_id) AS curr_id,
                            a.curr_rate,
                            TRIM(a.tax1) AS tax1,
                            TRIM(a.tax2) AS tax2,
                            a.qty,
                            a.wgt,
                            a.amt_subtotal,
                            a.pct_disc,
                            a.amt_disc,
                            a.amt_freight,
                            a.ppn,
                            a.amt_ppn,
                            a.amt_net,
                            TRIM(a.po_said) AS po_said,
                            a.po_num,
                            TRIM(a.stat) AS stat,
                            a.memo_txt,
                            TRIM(a.print_flag) AS print_flag,
                            TRIM(a.imp_flag) AS imp_flag,
                            TRIM(a.aprv_flag) AS aprv_flag,
                            a.dt_aprv,
                            a.dt_rjc,
                            TRIM(a.no_po) AS no_po,
                            TRIM(a.exp_id) AS exp_id,
                            TRIM(a.contact_nam) AS contact_nam,
                            TRIM(a.aprv_by) AS aprv_by,
                            TRIM(a.rjc_by) AS rjc_by,
                            TRIM(a.order_id) AS order_id,
                            a.dt_sp,
                            TRIM(a.sp_id) AS sp_id,
                            a.dt_created,
                            a.dt_modified,
                            TRIM(a.user_id) AS user_id,
                            TRIM(f.name) AS nama_user_id,
                            a.dt_aprv2,
                            TRIM(a.aprv2_by) AS aprv2_by,
                            TRIM(a.tr_type) AS tr_type,
                            TRIM(a.consignee) AS consignee,
                            a.dt_rjc2,
                            TRIM(a.rjc2_by) AS rjc2_by,
                            TRIM(a.po_type) AS po_type,
                            a.ship_to,
                            TRIM(a.tax3) AS tax3,
                            TRIM(a.project) AS project,
                            TRIM(a.pekerjaan) AS pekerjaan,
                            a.amt_pph, 
                            TRIM(b.vendor_name) AS vendor_name, 
                            TRIM(b.address1) as address1, 
                            TRIM(b.address2) as address2, 
                            TRIM(b.city) as city, 
                            TRIM(b.country) as country,  
                            TRIM(b.phone) as vendor_tlp,  
                            TRIM(b.fax) as vendor_fax,  
                            TRIM(c.office) as office_desc, 
                            TRIM(c.address) as office_addr, 
                            TRIM(c.city) as office_city, 
                            TRIM(c.zip_code) as office_zip_code, 
                            TRIM(c.tlp) as office_tlp, 
                            TRIM(c.e_mail) as office_email, 
                            TRIM(d.pay_term_desc) as pay_term_desc,
                            TRIM(e.alamat_kirim) as pr_alamat_kirim,
                            TRIM(e.project) as pr_project,
                            TRIM(e.pekerjaan) as pr_pekerjaan,
                            TRIM(e.penerima) as pr_penerima
                            ")
                ->join('vendor as b', 'a.vendor_id', '=', 'b.vendor_id')
                ->join('branch_office as c', 'a.office_id', '=', 'c.office_id')
                ->leftJoin('pay_term as d', 'a.pay_term_id', '=', 'd.pay_term_id')
                ->leftJoin('pr_hdr as e', 'a.pr_id', '=', 'e.pr_id')
                ->leftJoin(DB::raw('[hrd_ol].[dbo].[employee] as f'), 'a.user_id', '=', 'f.idemployee')
                ->whereRaw($sqlWhere)
                ->get();

            return response()->json([
                'PoHdr' => $PoHdr,
                'status_code' => 200
            ], 200);
     
        } catch (\Exception $e) {
            return $this->sendError('Validation Error', $e->getMessage());
        }   
    }
    
    public function getListPoDtl(Request $request)
    {
        try
        {
            $sqlWhere = '1=1 ';
        
            if ($request->PoId) {
                $sqlWhere .= "and a.po_id = '$request->PoId'";
            }
            if ($request->OfficeId) {
                $sqlWhere .= "and a.office_id = '$request->OfficeId'";
            }
            if ($request->ArticleId) {
                $sqlWhere .= "and a.article_id = '$request->ArticleId'";
            }
            if ($request->ArticleName) {
                $sqlWhere .= "and b.description like '%$request->ArticleName%'";
            }
            $PoDtl = DB::connection("sqlSrvFPI")
                    ->table('po_item as a')
                    ->selectRaw("
                                a.office_id,
                                TRIM(a.po_id) AS po_id,
                                a.po_item,
                                a.article_id, 
                                a.qty,
                                a.wgt,
                                TRIM(a.unit_meas) AS unit_meas,
                                a.unit_price,
                                a.um_price,
                                a.amt_net,
                                TRIM(a.stat) AS stat,
                                TRIM(a.pr_id) AS pr_id,
                                a.pr_item,
                                TRIM(a.pq_id) AS pq_id,
                                a.pq_item,
                                TRIM(a.order_id) AS order_id,
                                a.item_num,
                                a.qty_rcv,
                                a.wgt_rcv,
                                a.qty_retur,
                                a.wgt_retur,
                                a.length,
                                a.dt_created,
                                a.dt_modified,
                                TRIM(a.user_id) AS user_id,
                                a.unit_price_2,
                                a.amt_net_2,
                                TRIM(a.remark) AS remark,
                                TRIM(a.trx_id) AS trx_id,
                                a.num,
                                a.fix_price, 
                                b.description, 
                                b.uom, 
                                d.vendor_name, 
                                d.vendor_name
                                ")
                    ->join('article as b', 'a.article_id', '=', 'b.article_id')
                    ->join('po_hdr as c', function($join) {
                        $join->on('a.po_id', '=', 'c.po_id')
                             ->on('a.office_id', '=', 'c.office_id');
                    })
                    ->join('vendor as d', 'c.vendor_id', '=', 'd.vendor_id')
                    ->whereRaw($sqlWhere)
                    ->get();

            return response()->json([
                'PoDtl' => $PoDtl,
                'status_code' => 200
            ], 200); 
     
        } catch (\Exception $e) {
            return $this->sendError('Validation Error', $e->getMessage());
        }   
    } 

    public function getCategory(Request $request)
    {
        try
        {
            $sqlWhere = '1=1 ';
        
            if ($request->CategoryId) {
                // Menggunakan parameter binding untuk keamanan
                $sqlWhere .= "and cat_id = ?";
            }
            if ($request->CategoryName) {
                // Menggunakan parameter binding untuk keamanan
                $sqlWhere .= "and descr like ?";
            }

            // Siapkan array untuk parameter binding
            $bindings = [];
            if ($request->CategoryId) {
                $bindings[] = $request->CategoryId;
            }
            if ($request->CategoryName) {
                $bindings[] = '%' . $request->CategoryName . '%';
            }
            
            $Category = DB::connection("sqlSrvFPI")
                    ->table('category')
                        ->select(
                            'cat_id',
                            DB::raw('TRIM(descr) AS descr'),
                            DB::raw('TRIM(consume_able) AS consume_able'),
                            DB::raw('TRIM(wh_id) AS wh_id'),
                            DB::raw('TRIM(bin_id) AS bin_id'),
                            DB::raw('TRIM(ac_num_persediaan) AS ac_num_persediaan'),
                            DB::raw('TRIM(ac_num_penjualan) AS ac_num_penjualan'),
                            DB::raw('TRIM(ac_num_biaya) AS ac_num_biaya'),
                            DB::raw('TRIM(active_flag) AS active_flag'),
                            'dt_created',
                            'dt_modified',
                            DB::raw('TRIM(user_id) AS user_id')
                        )
                        ->whereRaw($sqlWhere, $bindings)
                    ->where('active_flag', 'Y')
                    ->orderby('cat_id', 'asc')
                    ->get();
                        
            return response()->json(['Category' => $Category], 200); 
     
        } catch (\Exception $e) {
            return $this->sendError('Validation Error', $e->getMessage());
        }   
    }

    public function getCommodity(Request $request)
    {
        try
        {
            $query = DB::connection("sqlSrvFPI")
                        ->table('commodity')
                        ->select(
                            'cat_id',
                            'com_id',
                            DB::raw('TRIM(com_desc) AS com_desc'),
                            DB::raw('TRIM(wh_id) AS wh_id'),
                            DB::raw('TRIM(bin_id) AS bin_id'),
                            DB::raw('TRIM(ac_num_persediaan) AS ac_num_persediaan'),
                            DB::raw('TRIM(ac_num_penjualan) AS ac_num_penjualan'),
                            DB::raw('TRIM(ac_num_biaya) AS ac_num_biaya'),
                            DB::raw('TRIM(active_flag) AS active_flag'),
                            'dt_created',
                            'dt_modified',
                            DB::raw('TRIM(user_id) AS user_id')
                        );
            if ($request->CategoryId) {
                $query->where('cat_id', $request->CategoryId);
            }
        
            if ($request->CommodityId) {
                $query->where('com_id', $request->CommodityId);
            }
            
            if ($request->CommodityName) {
                $query->where('com_desc', 'like', '%' . $request->CommodityName . '%');
            }
            
            $Commodity = $query
                    ->where('active_flag', 'Y')
                        ->orderBy('com_id', 'asc')
                    ->get();

            return response()->json([
                'Commodity' => $Commodity,
                'status_code' => 200,
            ], 200); 
     
        } catch (\Exception $e) {
            return $this->sendError('Validation Error', $e->getMessage());
        }   
    }

    public function getCommodityGroup(Request $request)
    {
        try
        {
            $query = DB::connection("sqlSrvFPI")
                        ->table('commodity_grp')
                        ->select(
                            'cat_id',
                            'com_id',
                            'com_grp',
                            DB::raw('TRIM(com_grp_desc) AS com_grp_desc'),
                            DB::raw('TRIM(active_flag) AS active_flag')
                        );
            if ($request->CategoryId) {
                $query->where('cat_id', $request->CategoryId);
            }
        
            if ($request->CommodityId) {
                $query->where('com_id', $request->CommodityId);
            }

            if ($request->CommodityGrpId) {
                $query->where('com_grp', $request->CommodityGrpId);
            }
            
            if ($request->CommodityGrpName) {
                $query->where('com_grp_desc', 'like', '%' . $request->CommodityGrpName . '%');
            }
            
            $CommodityGroup = $query
                    ->where('active_flag', 'Y')
                        ->orderBy('com_id', 'asc')
                    ->get();

            return response()->json([
                'CommodityGroup' => $CommodityGroup,
                'status_code' => 200,
            ], 200); 
     
        } catch (\Exception $e) {
            return $this->sendError('Validation Error', $e->getMessage());
        }   
    }

    public function getPrHdr(Request $request)
    {
        $cat_id    = $request->cat_id;
        $com_id    = $request->com_id;
        $com_grp_id = $request->com_grp_id;

        try {
            $query = DB::connection("sqlSrvFPI")
                ->table('pr_hdr as ph')
                ->selectRaw("
                    ph.office_id,
                    TRIM(ph.pr_id) AS pr_id,
                    ph.dt_pr,
                    TRIM(ph.dept_id) AS dept_id,
                    TRIM(ph.pic_id) AS pic_id,
                    TRIM(ph.contr_id) AS contr_id,
                    ph.qty,
                    ph.wgt,
                    ph.pr_num,
                    TRIM(ph.stat) AS stat,
                    TRIM(ph.memo_txt) AS memo_txt,
                    ph.dt_created,
                    ph.dt_modified,
                    TRIM(ph.user_id) AS user_id,
                    ph.dt_aprv,
                    ph.dt_aprv2,
                    TRIM(ph.aprv_by) AS aprv_by,
                    TRIM(ph.aprv2_by) AS aprv2_by,
                    TRIM(ph.tr_type) AS tr_type,
                    ph.dt_rjc,
                    TRIM(ph.rjc_by) AS rjc_by,
                    ph.dt_rjc2,
                    TRIM(ph.rjc2_by) AS rjc2_by,
                    TRIM(ph.vendor_id) AS vendor_id,
                    TRIM(ph.id_site) AS id_site,
                    TRIM(ph.id_cluster) AS id_cluster,
                    TRIM(ph.id_house) AS id_house,
                    TRIM(ph.type_pr) AS type_pr,
                    TRIM(ph.penerima) AS penerima,
                    TRIM(ph.project) AS project,
                    TRIM(ph.alamat_kirim) AS alamat_kirim,
                    TRIM(ph.pekerjaan) AS pekerjaan,
                    CONCAT(TRIM(ph.pr_id), '-',TRIM(ph.dept_id), '-',TRIM(pu.pic_name)) as label
                ")
                ->leftJoin('pr_req_user as pu', function ($join) {
                    $join->on('pu.dept_id', '=', 'ph.dept_id')
                        ->on('pu.pic_id', '=', 'ph.pic_id');
                });

            // Filter by PR Id
            if ($request->PrId) {
                $query->where('ph.pr_id', trim($request->PrId));
            }

            // Filter by Office Id
            if ($request->OfficeId) {
                $query->where('ph.office_id', trim($request->OfficeId));
            }

            // Filter by date range
            if ($request->start_date) {
                $start_date = $request->start_date;
                if ($request->end_date) {
                    $end_date = Carbon::parse($request->end_date)->addDay()->format('Y-m-d');
                    $query->whereBetween('ph.dt_pr', [$start_date, $end_date]);
                } else {
                    $query->where('ph.dt_pr', '>=', $start_date);
                }
            }

            // Tambah filter kategori / komoditi / grup
            if ($cat_id || $com_id || $com_grp_id) {
                $query->whereExists(function ($sub) use ($cat_id, $com_id, $com_grp_id) {
                    $sub->select(DB::raw(1))
                        ->from('pr_item as pi')
                        ->join('article as a', 'a.article_id', '=', 'pi.article_id')
                        ->whereColumn('pi.pr_id', 'ph.pr_id');

                    if ($cat_id) {
                        $sub->where('a.cat_id', $cat_id);
                    }
                    if ($com_id) {
                        $sub->where('a.com_id', $com_id);
                    }
                    if ($com_grp_id) {
                        $sub->where('a.com_grp', $com_grp_id);
                    }
                });
            }

            $PrHdr = $query
                ->where('ph.stat', 'O')
                ->where('ph.type_pr', '<>', 'N')
                ->get();

            return response()->json(['PrHdr' => $PrHdr], 200);
        } catch (\Exception $e) {
            return $this->sendError('Validation Error', $e->getMessage());
        }
    }


    public function getListPr(Request $request)
    {
        try
        {
            $sqlWhere = '1=1 ';

            if ($request->OfficeId) {
                $sqlWhere .= "and a.office_id = '$request->OfficeId'";
            }
            if ($request->PrId) {
                $sqlWhere .= "and a.pr_id = '$request->PrId'";
            }
            
            if ($request->ArticleId) {
                $sqlWhere .= "and a.article_id = '$request->ArticleId'";
            }
            if ($request->ArticleName) {
                $sqlWhere .= "and d.description like '%$request->ArticleName%'";
            }
            if ($request->CategoryId) {
                $sqlWhere .= "and d.cat_id = '$request->CategoryId'";
            }
            if ($request->CommodityId) {
                $sqlWhere .= "and d.com_id = '$request->CommodityId'";
            }
            if ($request->CommodityGrpId) {
                $sqlWhere .= "and d.com_grp = '$request->CommodityGrpId'";
            }

            $ListPr = DB::connection("sqlSrvFPI")
                    ->table('pr_item as a')
                    ->selectRaw("a.office_id, a.pr_id, a.pr_item, a.article_id, d.description, a.length, d.berat, d.cat_id, f.descr as cat_name,
                                d.com_id, g.com_desc as com_name, a.qty - a.qty_po as qty, a.unit_meas as uom, TRIM(a.remark) AS remark,
                                a.id_site, h.site_name, a.id_cluster, i.cluster_name, a.id_house, j.house_name")
                    ->join('pr_hdr as b', 'a.pr_id', '=', 'b.pr_id') 
                    ->join('branch_office as c', 'b.office_id', '=', 'c.office_id') 
                    ->join('article as d', 'a.article_id', '=', 'd.article_id')
                    ->join('category as f', 'd.cat_id', '=', 'f.cat_id')
                    ->leftjoin('commodity as g', function ($join) {
                        $join->on('d.com_id', '=', 'g.com_id')
                            ->on('d.cat_id', '=', 'g.cat_id');
                    })
                    ->leftjoin('site as h', 'a.id_site', '=', 'h.id_site')
                    ->leftjoin('site_cluster as i', 'a.id_cluster', '=', 'i.id_cluster')
                    ->leftjoin('house_unit as j', 'a.id_house', '=', 'j.id_house')
                    ->whereRaw($sqlWhere . " and a.stat = 'O' and b.type_pr <> 'N'")
                    ->orderby('a.pr_id', 'asc')
                    ->orderby('a.pr_item', 'asc')
                    ->get();

            return response()->json(['ListPr' => $ListPr], 200);
     
        } catch (\Exception $e) {
            return $this->sendError('Validation Error', $e->getMessage());
        }   
    }

    public function getGenPoId(Request $request)
    {
        try {
            $PoId = 'PO' . $request->DtPo . $request->OfficeId;
            $GenPoId = DB::connection("sqlSrvFPI")
                    ->select(DB::raw("SELECT concat('$PoId', format(isnull(max(po_num),1) + 1,'000')) as po_id, 
                                        isnull(max(po_num),1) + 1 as po_num FROM po_hdr where po_id like'$PoId%'"));

            if ($GenPoId) {
                $PoId = $GenPoId[0]->po_id;
            }
            return response()->json(['PoId' => $PoId], 200);

        } catch (\Exception $e) {
            return $e;
        }
        
    }

    public function getPoItem(Request $request)
    {
        $sqlWhere = '1=1 ';
        if ($request->PoId) {
            $sqlWhere .= "AND po_id = '$request->PoId' ";
        }
        if ($request->OfficeId) {
            $sqlWhere .= "AND office_id = '$request->OfficeId' ";
        }

        $PoItem = DB::connection("sqlSrvFPI")
            ->select(DB::raw("SELECT ISNULL(MAX(po_item), 0) + 1 AS po_item FROM po_item WHERE $sqlWhere"));

        return response()->json(['PoItem' => $PoItem[0]->po_item], 200);  
    }

    public function getSavePoItem(request $request)
    {
        try {
            $FindPoItem = DB::connection("sqlSrvFPI")
                    ->select(DB::raw("Select po_id, po_item, stat from po_item where po_id = '$request->PoId' 
                            and office_id = '$request->OfficeId' and po_item = '$request->PoItem'"));

            if(!$FindPoItem){
                $PoItem = DB::connection("sqlSrvFPI")
                ->table('po_item')
                ->insert([
                    'office_id' => $request->OfficeId ? $request->OfficeId : ' ',
                    'po_id' => $request->PoId ? $request->PoId : ' ',
                    'po_item' => $request->PoItem ? $request->PoItem :  0,
                    'article_id' => $request->ArticleId ? $request->ArticleId : ' ',
                    'qty' => $request->Qty ? $request->Qty : 0,
                    'wgt' => $request->Wgt ? $request->Wgt : 0,
                    'unit_meas' => $request->Uom ? $request->UnitMeas : ' ',
                    'unit_price' => $request->Price ? $request->Price : 0,
                    'amt_net' => $request->AmtNet ? $request->AmtNet : 0,
                    'stat' => 'O',
                    'pr_id' => $request->PrId ? $request->PrId : ' ',
                    'pr_item' => $request->PrItem ? $request->PrItem : 0,
                    'length' => $request->Length ? $request->Length : 0,
                    'remark' => $request->Remark ? $request->Remark : ' ',
                    'dt_created' => date('Y-m-d H:i:s'),
                    'dt_modified' => date('Y-m-d H:i:s'),
                    'user_id' => getAuth($request)->user_id2
                ]);
               
            } else {
                if ($FindPoItem[0]->stat =='C') {
                    return response()->json(['message' => 'PO Item Already Closed'], 200); 
                } else {
                    $PoItem = DB::connection("sqlSrvFPI")
                    ->table('po_item')
                    ->where('po_id', $request->PoId)
                    ->where('office_id', $request->OfficeId)
                    ->where('po_item', $request->PoItem)
                    ->update([
                        'article_id' => $request->ArticleId ? $request->ArticleId : ' ',
                        'qty' => $request->Qty ? $request->Qty : 0,
                        'wgt' => $request->Wgt ? $request->Wgt : 0,
                        'unit_meas' => $request->Uom ? $request->UnitMeas : ' ',
                        'unit_price' => $request->Price ? $request->Price : 0,
                        'amt_net' => $request->AmtNet ? $request->AmtNet : 0,
                        'stat' => 'O',
                        'pr_id' => $request->PrId ? $request->PrId : ' ',
                        'pr_item' => $request->PrItem ? $request->PrItem : 0,
                        'length' => $request->Length ? $request->Length : 0,
                        'remark' => $request->Remark ? $request->Remark : ' ',
                        'dt_modified' => date('Y-m-d H:i:s'),
                        'user_id' => getAuth($request)->user_id2
                    ]);
                }
            }

            return response()->json(['message' => 'Po Item saved successfully'], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getSavePoHdr(request $request)
    {
        try {
            $FindPoHdr = DB::connection("sqlSrvFPI")
                ->select(DB::raw("Select po_id from po_hdr where po_id = '$request->PoId' 
                        and office_id = '$request->OfficeId'"));
            if($FindPoHdr){
                $PoHdr = DB::connection("sqlSrvFPI")
                ->table('po_hdr')
                ->insert([
                    'po_id' => $request->PoId,
                    'po_num' => $request->PoNum,
                    'office_id' => $request->OfficeId,
                    'vendor_id' => $request->VendorId,
                    'dt_po' => $request->DtPo,
                    'dt_shipped' => $request->DtShip,
                    'pay_term_id' => $request->PayTermId,
                    'curr_id' => $request->CurrId,
                    'curr_rate' => $request->CurrRate,
                    'tax1' => $request->Tax1 ?? '',
                    'tax2' => $request->Tax2 ?? '',
                    'tax3' => $request->Tax3 ?? '',
                    'qty' => $request->TotQty ? $request->TotQty : 0,
                    'wgt' => $request->TotWgt ? $request->TotWgt : 0,
                    'amt_subtotal' => $request->AmtSubtotal ? $request->AmtSubtotal : 0,
                    'amt_disc' => $request->AmtDisc ? $request->AmtDisc : 0,
                    'ppn' => $request->PctPpn ? $request->PctPpn : 0,
                    'amt_ppn' => $request->AmtPpn ? $request->AmtPpn : 0,
                    'amt_pph'=> $request->AmtPph23 ? $request->AmtPph23 : 0,
                    'amt_net' => $request->GrandTotal ? $request->GrandTotal : 0,
                    'po_said' => terbilang($request->GrandTotal ? $request->GrandTotal : 0),
                    'stat' => 'O',
                    'memo_txt' => $request->MemoTxt,
                    'contact_nam' => $request->ContactName,
                    'tr_type' => 'G',
                    'po_type' => 'G',
                ]);
            } else {
                $PoHdr = DB::connection("sqlSrvFPI")
                ->table('po_hdr')
                ->where('po_id', $request->PoId)
                ->where('office_id', $request->OfficeId)
                ->update([
                    'vendor_id' => $request->VendorId,
                    'dt_po' => $request->DtPo,
                    'dt_shipped' => $request->DtShip,
                    'pay_term_id' => $request->PayTermId,
                    'curr_id' => $request->CurrId,
                    'curr_rate' => $request->CurrRate,
                    'tax1' => $request->Tax1 ?? '',
                    'tax2' => $request->Tax2 ?? '',
                    'tax3' => $request->Tax3 ?? '',
                    'qty' => $request->TotQty ? $request->TotQty : 0,
                    'wgt' => $request->TotWgt ? $request->TotWgt : 0,
                    'amt_subtotal' => $request->AmtSubtotal ? $request->AmtSubtotal : 0,
                    'amt_disc' => $request->AmtDisc ? $request->AmtDisc : 0,
                    'ppn' => $request->PctPpn ? $request->PctPpn : 0,
                    'amt_ppn' => $request->AmtPpn ? $request->AmtPpn : 0,
                    'amt_pph'=> $request->AmtPph23 ? $request->AmtPph23 : 0,
                    'amt_net' => $request->GrandTotal ? $request->GrandTotal : 0,
                    'po_said' => terbilang($request->GrandTotal ? $request->GrandTotal : 0),
                    'stat' => 'O',
                    'memo_txt' => $request->MemoTxt,
                    'contact_nam' => $request->ContactName,
                    'tr_type' => 'G',
                    'po_type' => 'G',
                ]);
            }

            return response()->json(['message' => 'Po Header saved successfully'], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function getGrandTotal(request $request)
    {
        
        $sqlWhere = '1=1 ';
        if ($request->PoId) {
            $sqlWhere .= "and po_id = '$request->PoId' ";
        }
        if ($request->OfficeId) {
            $sqlWhere .= "and office_id = '$request->OfficeId' ";
        }
        

        // Get subtotal
        $AmtSubtotalResult = DB::connection("sqlSrvFPI")
            ->select(DB::raw("select SUM(amt_net) as subtotal FROM po_item WHERE $sqlWhere"));
        $AmtSubtotal = $AmtSubtotalResult[0]->subtotal ?? 0;

        // Calculate discount
        $PctDisc = floatval($request->PctDisc ?? 0);
        $AmtDisc = $AmtSubtotal * $PctDisc / 100;
        $AmtAfterDisc = $AmtSubtotal - $AmtDisc;

        // Get PPN
        $PctPpn = 0;
        $AmtPpn = 0;
        if ($request->tax1) {
            $getppn = DB::connection("sqlSrvFPI")
            ->select(DB::raw("SELECT str3 from appl_constant WHERE key_code = 'PPN'"));
            $PctPpn = floatval($getppn[0]->str3 ?? 0);
            $AmtPpn = $AmtAfterDisc * $PctPpn / 100;
        }

        // Get PPH23
        $PctPph23 = 0;
        $AmtPph23 = 0;
        if ($request->tax2) {
            $getpph23 = DB::connection("sqlSrvFPI")
            ->select(DB::raw("SELECT str3 from appl_constant WHERE key_code = 'PPH23'"));
            $PctPph23 = floatval($getpph23[0]->str3 ?? 0);
            $AmtPph23 = $AmtAfterDisc * $PctPph23 / 100;
        }

        // Get PPH21
        $PctPph21 = 0;
        $AmtPph21 = 0;
        if ($request->tax4) {
            $getpph21 = DB::connection("sqlSrvFPI")
            ->select(DB::raw("SELECT str3 from appl_constant WHERE key_code = 'PPH21'"));
            $PctPph21 = floatval($getpph21[0]->str3 ?? 0);
            $AmtPph21 = $AmtAfterDisc * $PctPph21 / 100;
        }

        // Get PPH4 if any (optional, default 0)
        $AmtPph4 = floatval($request->AmtPph4 ?? 0);

        // Calculate Grand Total
        if ($request->tax3) {
            // Perhitungan untuk harga sudah termasuk pajak (include)
            $hargaInclude = $AmtSubtotal - $AmtDisc;
            $ppnRate = $PctPpn / 100;
            $pph23Rate = $PctPph23 / 100;
            $pph21Rate = $PctPph21 / 100;
            $result = $this->ServiceInclude->hitungHargaDasarTanpaSelisih($hargaInclude, $ppnRate, $pph23Rate, $pph21Rate);

            return response()->json([
            'harga_dasar' => $result['harga_dasar'] ?? 0,
            'AmtSubtotal' => $AmtSubtotal,
            'PctDisc' => $PctDisc,
            'AmtDisc' => $AmtDisc,
            'AmtAfterDisc' => $AmtAfterDisc,
            'PctPpn' => $PctPpn,
            'AmtPpn' => $result['ppn'] ?? 0,
            'PctPph23' => $PctPph23,
            'AmtPph23' => $result['pph23'] ?? 0,
            'PctPph21' => $PctPph21,
            'AmtPph21' => $result['pph21'] ?? 0,
            'AmtPph4' => $AmtPph4,
            'GrandTotal' => $result['total'] ?? 0
            ], 200);
        } else {
            $GrandTotal = $AmtAfterDisc + $AmtPpn - $AmtPph23 - $AmtPph21 - $AmtPph4;
            return response()->json([
            'AmtSubtotal' => $AmtSubtotal,
            'PctDisc' => $PctDisc,
            'AmtDisc' => $AmtDisc,
            'AmtAfterDisc' => $AmtAfterDisc,
            'PctPpn' => $PctPpn,
            'AmtPpn' => $AmtPpn,
            'PctPph23' => $PctPph23,
            'AmtPph23' => $AmtPph23,
            'PctPph21' => $PctPph21,
            'AmtPph21' => $AmtPph21,
            'AmtPph4' => $AmtPph4,
            'GrandTotal' => $GrandTotal
            ], 200);
        }

    }

    public function getUpdatePr(request $request)
    {
        try {
        $TotQty = DB::connection("sqlSrvFPI")
        ->select(DB::raw("SELECT isnull(sum(qty),0) as qty FROM po_item WHERE pr_id = '$request->PrId' and pr_item = '$request->PrItem'"));
           if ($TotQty > 0) {
                DB::connection('sqlSrvFPI')
                ->table('pr_item')
                ->where('pr_id', $request->PrId)
                ->where('pr_item', $request->PrItem)
                ->update(['qty_po' => $TotQty[0]->qty]);

                $UpdatStat = DB::connection('sqlSrvFPI')
                ->table('po_item')
                ->selectRaw('pr_id, pr_item, qty, qty_po, stat')
                ->where('pr_id', $request->PrId)
                ->where('pr_item', $request->PrItem)
                ->get();

                if ($UpdatStat[0]->qty <= $UpdatStat[0]->qty_po) {
                    DB::connection('sqlSrvFPI')
                    ->table('pr_item')
                    ->where('pr_id', $request->PrId)
                    ->where('pr_item', $request->PrItem)
                    ->update(['stat' => 'C']);

                } else {
                    DB::connection('sqlSrvFPI')
                    ->table('pr_item')
                    ->where('pr_id', $request->PrId)
                    ->where('pr_item', $request->PrItem)
                    ->update(['stat' => 'O']);
                }
           }

           $UpdatePrHdr = DB::connection('sqlSrvFPI')
            ->table('pr_hdr')
            ->where('pr_id', $request->PrId)
            ->where('stat', 'O')
            ->count();

            if ($UpdatePrHdr == 0) {
                DB::connection('sqlSrvFPI')
                ->table('pr_hdr')
                ->where('pr_id', $request->PrId)
                ->update(['stat' => 'C']);
            }

            return response()->json(['message' => 'Success'], 200);
        }
        catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

    }

    public function getRcvList (request $request)
    {
        try {
            $RcvList = DB::connection('sqlSrvFPI')
            ->table('purc_rcv_dtl as a')
            ->selectRaw('a.po_id, a.po_item, a.rcv_id, b.rcv_no, a.article_id, a.qty, a.wgt, a.unit_meas, a.unit_price, a.amt_net')
            ->join('purc_rcv_hdr as b', 'a.purc_rcv_id', '=', 'b.purc_rcv_id')
            ->where('po_id', $request->PoId)
            ->where('po_item', $request->PoItem)
            ->orderby('a.rcv_id', 'asc')
            ->get();
            return response()->json(['RcvList' => $RcvList], 200);
        }
        catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getDelPO(request $request)
    {
        try {
            DB::connection('sqlSrvFPI')
            ->table('po_item')            
            ->where('po_id', $request->PoId)            
            ->where('po_item', $request->PoItem)            
            ->delete();

            return response()->json(['result' => 'success'], 200);  
        }
        catch (\Exception $e) {
            return response()->json(['result' => 'error', 'message' => $e->getMessage()], 500);         
        
        }

    }

    private function generatePoId()
    {
        $dateMonth = date("m");
        $dateNow = date("y").date("m");

        $gen_cluster_id = '';

        
       $getLastTrID = DB::connection('sqlSrvFPI')
                            ->table('po_hdr')
                            ->select(DB::raw('TRIM(po_id) as po_id'))
                            ->where('po_id', 'like', '%' . $dateNow . '%')
                            ->orderBy('po_id', 'desc')
                            ->value('po_id');
        
        if ($getLastTrID) {
            $month = substr(trim($getLastTrID), 4, 2);

            if ($month != $dateMonth) {
                $gen_cluster_id = 'PO' . $dateNow . '001';
            } else {
                $seq = substr(trim($getLastTrID), -3) + 1;
                $gen_cluster_id = 'PO' . $dateNow . str_pad($seq, 3, '0', STR_PAD_LEFT);
            }
        } else {
            $gen_cluster_id = 'PO' . $dateNow . '001';
        }
        return $gen_cluster_id;
    }
    
    public function getTaxValue()
    {
        $keys = ['PPN', 'PPH23', 'PPH21', 'PPH4'];

        $result = DB::connection('sqlSrvFPI')
            ->table('appl_constant')
            ->whereIn('key_code', $keys)
            ->pluck('str3', 'key_code'); // key_code jadi key array, str3 jadi value

        return response()->json([
            'message' => 'ok',
            'ppn'     => $result->get('PPN'),
            'pph23'   => $result->get('PPH23'),
            'pph21'   => $result->get('PPH21'),
            'pph4'    => $result->get('PPH4'),
            'status_code' => 200
        ], 200);
    }

    public function storePo(Request $request)
    {
        $all_success = true;
        DB::beginTransaction();

        try {            
            if($request->create_flag=='Y'){

                $noPo = $this->generatePoId();
                $last3 = intval(substr($noPo, -3));
    
                $simpanPoHdr = $this->storePoHdr($noPo, $last3, $request);
                $simpanPoDtl = $this->storePoDtl($noPo, $last3, $request);
                
                
                foreach ($request->articles as $detail) {
                    $detail = (object) $detail;
                    
                    if($detail->flag=='PR'){
                        $ubahPrItem = $this->updatePr($detail);

                        if (!$ubahPrItem) {
                            $all_success = false; // kalau ada gagal, set false
                        }
                    }
                }

                DB::commit();
                
                foreach ($request->articles as $detail) {
                    $detail = (object) $detail;
                    
                    if($detail->flag=='PR'){
                        $ubahPrItem = $this->updatePrHdr($detail->pr_id);
                        
                        if (!$ubahPrItem) {
                            $all_success = false; 
                        }
                    }
                }

                return response()->json([
                    'po_id' => $noPo,
                    'simpanPoHdr' => $simpanPoHdr,
                    'simpanPoDtl' => $simpanPoDtl,
                    'ubahPrItem' => $ubahPrItem,
                    'status_code' => 200,
                    'message' => 'Berhasil buat PO'
                ], 200);
            }else{

                $noPo = $request->po_id;
                $last3 = intval(substr($noPo, -3));

                $ubahPoHdr = $this->updatePoHdr($noPo, $last3, $request);
                
                if($ubahPoHdr->original['status_code']!=200){
                    DB::rollBack(); // Batalkan semua operasi database jika ada
                    return response()->json([
                        'status_code' => 500,
                        'message' => $ubahPoHdr->original['error']
                    ], 500);
                }

                $resetPR = $this->resetPr($noPo);
                $hapisItemPo = DB::connection('sqlSrvFPI')
                                        ->table('po_item')
                                        ->where('po_id', $noPo)
                                        ->delete();
                $simpanPoDtl = $this->storePoDtl($noPo, $last3, $request);
                
                foreach ($request->articles as $detail) {
                    $detail = (object) $detail;
                    
                    if($detail->flag=='PR'){
                        $ubahPrItem = $this->updatePr($detail);
                        
                        if (!$ubahPrItem) {
                            $all_success = false; // kalau ada gagal, set false
                        }
                    }
                }
                
                DB::commit();

                foreach ($request->articles as $detail) {
                    $detail = (object) $detail;
                    
                    if($detail->flag=='PR'){
                        $ubahPrItem = $this->updatePrHdr($detail->pr_id);
                        
                        if (!$ubahPrItem) {
                            $all_success = false; 
                        }
                    }
                }

                return response()->json([
                    'po_id' => $noPo,
                    'ubahPoHdr' => $ubahPoHdr,
                    'resetPr' => $resetPR,
                    'simpanPoDtl' => $simpanPoDtl,
                    'updatePr' => $all_success,
                    'status_code' => 200,
                    'message' => 'Berhasil update PO'
                ], 200);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function storePoHdr($noPo,$last3,$request)
    {        
        try {
            $nilai_uang = terbilang($request->amt_net);
    
            $PoHdr = DB::connection("sqlSrvFPI")
                ->table('po_hdr')
                ->insert([
                            'po_id' => $noPo,
                            'po_num' => $last3,
                            'office_id' => $request->office_id,
                            'vendor_id' => $request->vendor_id,
                            'dt_po' => $request->dt_po,
                            'dt_shipped' => $request->dt_shipped,
                            'pay_term_id' => $request->pay_term_id,
                            'curr_id' => $request->curr_id,
                            'curr_rate' => $request->curr_rate,
                            'tax1' => $request->tax1 ?? '',
                            'tax2' => $request->tax2 ?? '',
                            'tax3' => $request->tax3 ?? '',
                            'qty' => $request->qty ?? 0,
                            'wgt' => $request->wgt ?? 0,
                            'amt_subtotal' => $request->harga_dasar,
                            'amt_disc' => $request->amt_disc ?? 0,
                            'ppn' => $request->ppn ?? 0,
                            'amt_ppn' => $request->nilai_ppn,
                            'amt_pph'=> $request->nilai_pph23,
                            'amt_net' => $request->total,
                            'po_said' => terbilang($request->total),
                            'stat' => 'O',
                            'memo_txt' => $request->memo_txt,
                            'contact_nam' => $request->contact_nam,
                            'tr_type' => 'G',
                            'po_type' => 'G',
                            'pr_id' => $request->pr_id,
                            'user_id' => getAuth($request)->user_id2,
                        ]);
            if ($PoHdr) {
                return response()->json([
                    'success' => true,
                    'status_code' => 200
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Gagal insert PO Header',
                    'status_code' => 403
                ], 403);
            }
        }catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function storePoDtl($noPo,$last3,$request)
    {
        $item_new = 1;
        $all_success = true;
        
        try {
            foreach ($request->articles as $detail) {
                $detail = (object) $detail;
                $item_new = $item_new+1;
            
                $save_item = DB::connection('sqlSrvFPI')
                        ->table('po_item')
                        ->insert([
                            'office_id' => $request->office_id,
                            'po_id' => $noPo,
                            'po_item' => $item_new,
                            'article_id' => $detail->article_id,
                            'qty' => $detail-> qtyPo,
                            'wgt' => $detail-> subwgt,
                            'unit_meas' => $detail->uom,
                            'unit_price' => $detail->pricePo,
                            'um_price' => 0,
                            'amt_net' => $detail->subtotal,
                            'stat' => 'O',
                            'pr_id' => $detail->pr_id ?? '',
                            'pr_item' => $detail->pr_item ?? '',
                            'length' => $detail->length,
                            'dt_created' => date('Y-m-d H:i:s'),
                            'user_id' => getAuth($request)->user_id2,
                            'remark' => $detail->memo ?? ''
                        ]);
                if (!$save_item) {
                    $all_success = false; // kalau ada gagal, set false
                }
            }
            if ($all_success) {
                return response()->json([
                    'success' => true,
                    'status_code' => 200
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Ada item PO yang gagal disimpan',
                    'status_code' => 403
                ], 403);
            }
        }catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function updatePr($detail)
    {
        $all_success = true;

        try {
            if($detail->qtyPo > 0) {
                $prItem = DB::connection("sqlSrvFPI")
                                    ->table('pr_item')
                                    ->select('qty_po')
                                    ->where('pr_id', $detail->pr_id)
                                    ->where('pr_item', $detail->pr_item)
                                    ->value('qty_po');
                $prItem = (float) $prItem;

                $UpdateQtyPOdiPR = DB::connection('sqlSrvFPI')
                                    ->table('pr_item')
                                    ->where('pr_id', $detail->pr_id)
                                    ->where('pr_item', $detail->pr_item)
                                    ->update(['qty_po' => $prItem + $detail->qtyPo]);

                $UpdatStat =  DB::connection('sqlSrvFPI')
                                    ->table('pr_item')
                                    ->selectRaw('pr_id, pr_item, qty, qty_po, stat')
                                    ->where('pr_id', $detail->pr_id)
                                    ->where('pr_item', $detail->pr_item)
                                    ->get();

                if ($UpdatStat[0]->qty <= $UpdatStat[0]->qty_po) {
                    $UpdatePrDtl = DB::connection('sqlSrvFPI')
                                            ->table('pr_item')
                                            ->where('pr_id', $detail->pr_id)
                                            ->where('pr_item', $detail->pr_item)
                                            ->update(['stat' => 'C']);

                    // $UpdatePrHdr = DB::connection('sqlSrvFPI')
                    //                         ->table('pr_hdr')
                    //                         ->where('pr_id', $detail->pr_id)
                    //                         ->update(['stat' => 'C']);
                    
                } else {
                    $UpdatePrDtl = DB::connection('sqlSrvFPI')
                                        ->table('pr_item')
                                        ->where('pr_id', $detail->pr_id)
                                        ->where('pr_item', $detail->pr_item)
                                        ->update(['stat' => 'O']);

                    // $UpdatePrHdr = DB::connection('sqlSrvFPI')
                    //                         ->table('pr_hdr')
                    //                         ->where('pr_id', $detail->pr_id)
                    //                         ->update(['stat' => 'O']);
                }

                // $fItemStat = DB::connection('sqlSrvFPI')
                //                 ->table('pr_item')
                //                 ->selectRaw('pr_id, pr_item, qty, qty_po, stat')
                //                 ->where('pr_id', $detail->pr_id)
                //                 ->where('stat', 'O')
                //                 ->get();
                // if ($fItemStat) {
                //     $UpdatePrHdr = DB::connection('sqlSrvFPI')
                //                         ->table('pr_hdr')
                //                         ->where('pr_id', $detail->pr_id)
                //                         ->update(['stat' => 'O']);
                // }else {
                //     $UpdatePrHdr = DB::connection('sqlSrvFPI')
                //                         ->table('pr_hdr')
                //                         ->where('pr_id', $detail->pr_id)
                //                         ->update(['stat' => 'C']);
                // }
           }
            return ['success' => true];
        }catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function updatePrHdr($pr_id)
    {        
        $hasOpenItem = DB::connection('sqlSrvFPI')
            ->table('pr_item')
            ->where('pr_id', $pr_id)
            ->where('stat', 'O')
            ->exists(); // true kalau masih ada

        if ($hasOpenItem) {
            // kalau masih ada O, header tetap O
            $update = DB::connection('sqlSrvFPI')
                ->table('pr_hdr')
                ->where('pr_id', $pr_id)
                ->update(['stat' => 'O']);
        } else {
            // kalau tidak ada O sama sekali, ubah ke C
            $update = DB::connection('sqlSrvFPI')
                ->table('pr_hdr')
                ->where('pr_id', $pr_id)
                ->update(['stat' => 'C']);
        }

        return ['success' => (bool) $update];
    }

    private function updatePoHdr($noPo,$last3,$request)
    {        
        $po = DB::connection("sqlSrvFPI")
                    ->table('po_hdr')
                    ->where('po_id', $noPo)
                    ->first();
                    
        if (!$po) {
            return response()->json([
                'error' => 'PO tidak ditemukan',
                'status_code' => 404
            ], 404);
        }

        if ($po->dt_aprv2 != '1900-01-01 00:00:00.000') {
            return response()->json([
                'error' => 'PO sudah di-approve, tidak bisa diupdate',
                'status_code' => 403
            ], 403);
        }

        $nilai_uang = terbilang($request->amt_net);

        $PoHdr = DB::connection("sqlSrvFPI")
                        ->table('po_hdr')
                        ->where('po_id', $noPo)
                        ->update([
                            'vendor_id' => $request->vendor_id,
                            'dt_po' => $request->dt_po,
                            'dt_shipped' => $request->dt_shipped,
                            'pay_term_id' => $request->pay_term_id,
                            'curr_id' => $request->curr_id,
                            'curr_rate' => $request->curr_rate,
                            'tax1' => $request->tax1 ?? '',
                            'tax2' => $request->tax2 ?? '',
                            'tax3' => $request->tax3 ?? '',
                            'qty' => $request->qty ?? 0,
                            'wgt' => $request->wgt ?? 0,
                            'amt_subtotal' => $request->harga_dasar,
                            'amt_disc' => $request->amt_disc ?? 0,
                            'ppn' => $request->ppn ?? 0,
                            'amt_ppn' => $request->nilai_ppn,
                            'amt_pph'=> $request->nilai_pph23,
                            'amt_net' => $request->total,
                            'po_said' => terbilang($request->total),
                            'stat' => 'O',
                            'memo_txt' => $request->memo_txt,
                            'contact_nam' => $request->contact_nam,
                            'tr_type' => 'G',
                            'po_type' => 'G',
                            'pr_id' => $request->pr_id,
                            'user_id' => getAuth($request)->user_id2,
                        ]);
        
        return response()->json([
            'success' => true,
            'status_code' => 200
        ], 200);
    }

    private function resetPr($poId)
    {
        try {
            $list_pr = DB::connection("sqlSrvFPI")
                            ->select(DB::raw("
                                SELECT TRIM(pr_id) as pr_id, pr_item, SUM(qty) as qty_po_item
                                FROM po_item
                                WHERE po_id = ?
                                GROUP BY pr_id, pr_item
                            "), [$poId]);

            foreach ($list_pr as $detail) {
                $prItem = DB::connection("sqlSrvFPI")
                                    ->table('pr_item')
                                    ->select('qty_po')
                                    ->where('pr_id', $detail->pr_id)
                                    ->where('pr_item', $detail->pr_item)
                                    ->first();

                if ($prItem) {
                    $newQty = max(0, $prItem->qty_po - $detail->qty_po_item); // supaya tidak minus

                    DB::connection("sqlSrvFPI")
                        ->table('pr_item')
                        ->where('pr_id', $detail->pr_id)
                        ->where('pr_item', $detail->pr_item)
                        ->update([
                            'qty_po' => $newQty,
                            'stat'   => 'O',
                        ]);

                    DB::connection("sqlSrvFPI")
                        ->table('pr_hdr')
                        ->where('pr_id', $detail->pr_id)
                        ->update([
                            'stat'   => 'O',
                        ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'PR berhasil di-reset',
                'status_code' => 200
            ], 200);
        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status_code' => 500,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deletePo(Request $request)
    {
        $noPo = $request->po_id;
        $all_success = true;
        DB::beginTransaction();

        try {
            $po = DB::connection("sqlSrvFPI")
                    ->table('po_hdr')
                    ->where('po_id', $noPo)
                    ->first();

            if (!$po) {
                return response()->json([
                    'error' => 'PO tidak ditemukan',
                    'status_code' => 404
                ], 404);
            }

            if ($po->dt_aprv2 != '1900-01-01 00:00:00.000') {
                return response()->json([
                    'error' => 'PO sudah di-approve, tidak bisa dihapus',
                    'status_code' => 403
                ], 403);
            }

            $list_pr = DB::connection("sqlSrvFPI")
                            ->select(DB::raw("
                                SELECT TRIM(pr_id) as pr_id, pr_item, sum(qty) as qty, sum(qty_rcv) as qty_rcv
                                FROM po_item
                                WHERE po_id = ?
                                GROUP BY pr_id, pr_item
                            "), [$noPo]);

            foreach ($list_pr as $detail) {
                $detail = (object) $detail;
                
                if($detail->qty_rcv > 0){
                    $all_success = false; // kalau ada gagal, set false
                }
            }
            
            if ($all_success==false) {
                return response()->json([
                    'error' => 'Sudah ada PO Item yang diterima, tidak bisa dihapus',
                    'status_code' => 404
                ], 404);
            }

            $resetPR = $this->resetPr($noPo);

            $hapusPoHdr = DB::connection('sqlSrvFPI')
                                    ->table('po_hdr')
                                    ->where('po_id', $noPo)
                                    ->delete();

            $hapusPoItem = DB::connection('sqlSrvFPI')
                                    ->table('po_item')
                                    ->where('po_id', $noPo)
                                    ->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PO berhasil di-hapus',
                'status_code' => 200
            ], 200);
        }
        catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'status_code' => 500,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function rejectPOitem(Request $request){
        $po_id = $request->po_id;
        $po_item = $request->po_item;
        $remark2 = $request->remark2 ?? '';

        try {
            DB::connection('sqlSrvFPI')
            ->table('po_item')
            ->where('po_id', $po_id)
            ->where('po_item', $po_item)
            ->update([
                'stat' => 'X',
                'remark2' => $remark2,
                'dt_modified' =>now('Asia/Jakarta')
            ]);

            $countActiveItems = DB::connection('sqlSrvFPI')
            ->table('po_item')
            ->where('po_id', $po_id)
            ->where('stat', '!=', 'X') // Hitung item yang statusnya BUKAN 'X'
            ->count();

            if ($countActiveItems == 0) {
                DB::connection('sqlSrvFPI')
                ->table('po_hdr') // Asumsikan nama tabel header adalah po_hdr
                ->where('po_id', $po_id)
                ->update([
                    'stat' => 'X', // Set status header menjadi 'X'
                    'dt_rjc' => now('Asia/Jakarta'),
                    'rjc_by' => getAuth($request)->user_id2,
                    'dt_rjc2' => now('Asia/Jakarta'),
                    'rjc2_by' => getAuth($request)->user_id2
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => ($countActiveItems == 0) ? 
                         'PO Item berhasil di-reject. Semua item PO sudah di-reject.' : 
                         'PO Item berhasil di-reject.',
                'status_code' => 200
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status_code' => 500,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}