<?php

namespace App\Http\Controllers\purchasing;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\MasterFPI;

class PurchaseRequestController extends Controller
{
    
    use MasterFPI;

    public function index(Request $request)
    {

        $GVendor = $this->getVendor("");
        $GOffice = $this->getOffice("");
        $GDepartment = $this->getDepartment(getAuth($request)->user_id2, "");
        // $gpic = $this->getPicName("");
        $gpic = [];
        $GArticleId = $this->getArticleId("");
        $GIdSite = $this->getIdSite("");
        $GIdCluster = $this->getIdCluster("");
        $GIdHouse = $this->getIdHouse("");
        $getAuth = getAuth($request);
        //$GDepartment = $this->getEnvUser($getAuth->user_id2);

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
            'userId' => $getAuth
        ], 200);

    }

    public function departmentOnChange(Request $request)
    {
        $dept = $request->dept;
        $getAuth = getAuth($request);
        $picList = $this->getPicName($getAuth->user_id2, $dept, "");

        return response()->json([
            'message' => 'ok', 
            'pic' => $picList,
        ], 200);

    }

    public function getMaster(Request $request)
    {

        switch ($request->type) {
            case 'article':
                
                if($request->value) {
                    $article = $this->getArticleId($request->value);
                }

                return response()->json(['article' => $article], 200);

                break;

            case 'vendor':
                
                if($request->value) {
                    $vendor = $this->getArticleId($request->value);
                }

                return response()->json(['vendor' => $vendor], 200);

                break;
            
            default:
                # code...
                break;
        }
        
    }

    public function getPrId(Request $request)
    {

        $sqlWhere = '1=1 ';

        if ($request->PrId) {
            $sqlWhere .= "and a.pr_id = '$request->PrId'";
        }

        if ($request->OfficeId) {
            $sqlWhere .= "and a.office_id = '$request->OfficeId'";
        }

        $PrId = DB::connection("sqlSrvFPI")
                ->select(DB::raw(" SELECT a.*, b.dept_name, c.pic_name FROM pr_hdr a JOIN department b
                                    ON a.office_id = b.office_id AND a.dept_id = b.dept_id JOIN pr_req_user c ON a.office_id = c.office_id 
                                    AND a.pic_id = c.pic_id AND a.dept_id = c.dept_id where $sqlWhere"));

        return response()->json(['PrId' => $PrId], 200);
    }

    public function getPrItem(Request $request)
    {

        $sqlWhere = '1=1 ';

        if ($request->PrId) {
            $sqlWhere .= "and a.pr_id = '$request->PrId'";
        }

        if ($request->officeId) {
            $sqlWhere .= "and a.office_id = '$request->officeId'";
        }

        if ($request->PrItem) {
            $sqlWhere .= "and a.pr_item = '$request->PrItem'";
        }

        $PrItem = DB::connection("sqlSrvFPI")
                ->select(DB::raw("select a.*, b.description, b.berat, b.vendor_description, site_name,cluster_name,house_name, a.dt_req
                                    FROM pr_item a JOIN article b
                                    ON a.article_id = b.article_id
                                    left outer join site s on a.id_site = s.id_site
                                    left outer join site_cluster t on a.id_site = t.id_site and a.id_cluster = t.id_cluster
                                    left outer join house_unit h on a.id_site = h.id_site and a.id_cluster = h.id_cluster and a.id_house = h.id_house
                                    where $sqlWhere"));

        return response()->json(['PrItem' => $PrItem], 200);
    }

   public function getPo(Request $request)
    {

        $sqlWhere = '1=1 ';
        if ($request->TypePr == 'Y'){

            if ($request->PrId) {
                $sqlWhere .= "and pr_id = '$request->PrId'";
            }

            if ($request->PrItem) {
                $sqlWhere .= "and pr_item = '$request->PrItem'";
            }

            $Po = DB::connection("sqlSrvFPI")
                    ->select(DB::raw("select po_id, po_item, qty FROM po_item where $sqlWhere"));

            return response()->json(['Po' => $Po], 200);

        } else {

            if ($request->PrId) {
            $sqlWhere .= "and pr_id = '$request->PrId'";
            }

            if ($request->PrItem) {
                $sqlWhere .= "and pr_item = '$request->PrItem'";
            }

            $Inv = DB::connection("sqlSrvFPI")
                    ->select(DB::raw("select tr_id, inv_item, qty FROM purc_inv_item where $sqlWhere"));

            return response()->json(['Inv' => $Inv], 200);
        }
    }

   public function getNumPrItem(Request $request)
    {
        
        $sqlWhere = '1=1 ';

        if ($request->PrId) {
            $sqlWhere .= "and pr_id = '$request->PrId'";
        }

        $PrItem = DB::connection("sqlSrvFPI")
                ->select(DB::raw("SELECT isnull(max(pr_item),1) + 1 as pr_item FROM pr_item where $sqlWhere"));

        return response()->json(['NumPrItem' => $PrItem], 200);
    }

    public function getGenPrId(Request $request)
    {
        try {
            $PrId = 'PR' . $request->DtPr ;
            $GenPrId= DB::connection("sqlSrvFPI")
                    ->select(DB::raw("SELECT concat('$PrId', format(isnull(max(pr_num),1) + 1,'000')) as pr_id, isnull(max(pr_num),1) + 1 as pr_num FROM pr_hdr where pr_id like'$PrId%'"));

            return response()->json(['GenPrId' => $GenPrId], 200);          
        } catch (\Exception $e) {
            return $e;
        }
        
    }
    
    public function InsertPr(Request $request)
    {
        
        try {

            $sqlWhere = '1=1 ';
            if ($request->PrId) {
                $sqlWhere .= "and pr_id = '$request->PrId'";
            }
            if ($request->PrItem) {
                $sqlWhere .= "and pr_item = '$request->PrItem'";
            }

            $FindPrItem = DB::connection("sqlSrvFPI")
                    ->select(DB::raw("SELECT pr_id from pr_item where $sqlWhere"));

            DB::connection("sqlSrvFPI")->beginTransaction();

            if(!$FindPrItem){

                $insertUpdate = DB::connection("sqlSrvFPI")
                    ->statement(DB::raw("insert into pr_item (office_id, pr_id, pr_item, article_id, qty, wgt, unit_meas, length, 
                                        dt_req, id_site, id_cluster, id_house, remark, unit_price, amount, stat, 
                                        dt_created, dt_modified, user_id) 
                                        values ('$request->OfficeId', '$request->PrId', '$request->PrItem', '$request->ArticleId', 
                                        '$request->Qty', '$request->wgt', '$request->Uom', '$request->Length', '$request->DtRequest', 
                                        '$request->IdSite', '$request->IdCluster', '$request->IdHouse', '$request->Remark',
                                        '$request->UnitPrice', '$request->Amount', 'O', getdate(), getdate(),
                                        '".getAuth($request)->user_id2."')"));
                
                
            } else {
                $insertUpdate = DB::connection("sqlSrvFPI")
                    ->statement(DB::raw("update pr_item set office_id = '$request->OfficeId', pr_id = '$request->PrId', 
                                        article_id = '$request->ArticleId', qty = '$request->Qty', wgt = '$request->wgt', 
                                        unit_meas = '$request->Uom', length = '$request->Length',
                                        dt_req = '$request->DtRequest', 
                                        id_site = '$request->IdSite', id_cluster = '$request->IdCluster', id_house = '$request->IdHouse',
                                        remark = '$request->Remark', unit_price = '$request->UnitPrice',
                                        amount = '$request->Amount', dt_modified = getdate(), user_id = '".getAuth($request)->user_id2."' 
                                        where pr_id = '$request->PrId' and pr_item = '$request->PrItem'")); 
                
            }

            $sqlWhere = '1=1 ';
            if ($request->PrId) {
                $sqlWhere .= "and pr_id = '$request->PrId'";
            }

            $FindPr = DB::connection("sqlSrvFPI")
                    ->select(DB::raw("SELECT pr_id from pr_hdr where $sqlWhere"));
                    
            if(!$FindPr){
                $insertUpdate = DB::connection("sqlSrvFPI")
                    ->statement(DB::raw("insert into pr_hdr (office_id, dt_pr, pr_id, pr_num, dept_id, pic_id, vendor_id, 
                                        memo_txt, stat, tr_type, type_pr, penerima, project, alamat_kirim, pekerjaan, id_site, dt_created, dt_modified, user_id) 
                                        values ('$request->OfficeId', '$request->DtPr', '$request->PrId', '$request->PrNum', '$request->DeptId', 
                                        '$request->PicId', '$request->VendorId', '$request->memo_txt', 'O', 'G',
                                        '$request->PrType', '$request->Penerima', '$request->project', '$request->AlamatKirim', 
                                        '$request->pekerjaan', '$request->IdSite', getdate(), getdate(), '".getAuth($request)->user_id2."')"));
            } else {
                $insertUpdate = DB::connection("sqlSrvFPI")
                    ->statement(DB::raw("update pr_hdr set dept_id = '$request->DeptId', memo_txt = '$request->memo_txt', vendor_id = '$request->VendorId',
                                        pic_id = '$request->PicId', dt_pr = '$request->DtPr', id_site = '$request->IdSite', penerima = '$request->Penerima',
                                        project = '$request->project', alamat_kirim = '$request->AlamatKirim', pekerjaan = '$request->pekerjaan', 
                                        dt_modified = getdate(), user_id = '".getAuth($request)->user_id2."' 
                                        where pr_id = '$request->PrId'"));
            }   
            
            $insertUpdate = DB::connection("sqlSrvFPI")
                ->statement(DB::raw("update a set a.qty = b.qty, a.wgt = b.wgt from pr_hdr a join 
                                    (select pr_id, sum(qty) as qty, sum(wgt) as wgt from pr_item GROUP BY pr_id) b on a.pr_id = b.pr_id
                                    where a.pr_id = '$request->PrId'"));
          
            DB::connection("sqlSrvFPI")->commit();
            return response()->json(['result' => 'Sukses'], 200);
            
        } catch (\Exception $e) {
            return $e;
        }

    }

    public function deletePr(Request $request)
    {
        try {
            DB::connection("sqlSrvFPI")->beginTransaction();

            $po = DB::connection("sqlSrvFPI")
                ->select(DB::raw("SELECT po_id from po_item where pr_id = '$request->PrId' and pr_item = '$request->PrItem'"));
            if ($po) {
                return response()->json(['Error' => 'Sudah Ada PO !!'], 200);
            }

            $delete = DB::connection("sqlSrvFPI")
                ->statement(DB::raw("delete from pr_item where pr_id = '$request->PrId' and pr_item = '$request->PrItem'"));
            
            $checkPrItem = DB::connection("sqlSrvFPI")
                    ->select(DB::raw("SELECT pr_id from pr_item where pr_id = '$request->PrId'"));

            if(!$checkPrItem){
                $delete = DB::connection("sqlSrvFPI")
                    ->statement(DB::raw("delete from pr_hdr where pr_id = '$request->PrId'"));
            }

            DB::connection("sqlSrvFPI")->commit();
        
            return response()->json(['result' => 'success'], 200);

        } catch (\Exception $e) {
            DB::connection("sqlSrvFPI")->rollBack();
            return $this->sendError('Validation Error', $e->getMessage());
        }
    }

    public function complitePr(Request $request)
    {
        try {

            DB::connection("sqlSrvFPI")->beginTransaction();
            $Stat = DB::connection("sqlSrvFPI")
                ->select(DB::raw("SELECT stat from pr_item where pr_id = '$request->PrId' and pr_item = '$request->PrItem'"));
            if ($Stat[0]->stat == 'O') {
                $stat = 'C';  
            } else {
                $stat = 'O';
            }

            $Update = DB::connection("sqlSrvFPI")
                ->statement(DB::raw("update pr_item set stat = '$stat' where pr_id = '$request->PrId' and pr_item = '$request->PrItem'"));
            
            $stat = DB::connection("sqlSrvFPI")
                ->select(DB::raw("SELECT stat from pr_item where pr_id = '$request->PrId' and stat = 'O'"));                   
            if(!$stat){         
                $Update = DB::connection("sqlSrvFPI")
                    ->statement(DB::raw("update pr_hdr set stat = 'C' where pr_id = '$request->PrId'"));
            } else {
                $Update = DB::connection("sqlSrvFPI")
                    ->statement(DB::raw("update pr_hdr set stat = 'O' where pr_id = '$request->PrId'"));
            }   

            DB::connection("sqlSrvFPI")->commit();
        
            return response()->json(['result' => 'success'], 200);
            
        } catch (\Exception $e) {
            DB::connection("sqlSrvFPI")->rollBack();
            return $this->sendError('Validation Error', $e->getMessage());
        }
    }
    public function SendWaPr(Request $request)
    {

        $sendWa = DB::connection("sqlSrvFPI")
            ->select(DB::raw("SELECT pr1, nik_pr1, left(replace(newid(),'-',''),16) as token 
                            from department where office_id = '$request->OfficeId' and dept_id = '$request->DeptId'"));    
            $nikpr1 = $sendWa[0]->nik_pr1;
            $token = $sendWa[0]->token;
            $pr1= $sendWa[0]->pr1;
            //return response()->json(['nikpr1' => $nikpr1, 'token' => $token], 200);
        if ($sendWa) {
            $insert = DB::connection("sqlSrvFPI")
                ->statement(DB::raw("insert into USER_MANAGEMENT.dbo.sec_reset_password (user_id2, link, created_at, updated_at)
                                    values ('$nikpr1', '$token', getdate(), getdate())"));
        } else {
            return response()->json(['Error' => 'Tidak Ada Data PR'], 200);
        }
        $message = "Info FDPI : Mohon Untuk Segera Di Approve PR no : " . $request->PrId . "Pada Link Berikut : https://viva.kencana.org/approve/" . $nikpr1 . "/" . $token; 
        $url = $this->sendWA($pr1, $message);
        if ($url['stat'] == 1) {
            return response()->json(['result' => 'success', 'msg' => $url['msg']], 200);
        } else {
            return response()->json(['result' => 'error', 'msg' => $url['msg']], 200);
        }
    }

    public function getEnvUser($userId2)
    {
        // $userId2 = '18001347' ; 
        // $xPIC = '';
        $AllDept = DB::connection("sqlSrvFPI")
            ->select(DB::raw("SELECT * FROM sec_env_conf WHERE var_id = 'ALLDEPT' and user_id2 = '$userId2'"));

        $AllDeptValue = ($AllDept && isset($AllDept[0]->var_value)) ? $AllDept[0]->var_value : 'N';
        $PicData = DB::connection("sqlSrvFPI")
                ->select(DB::raw("select a.dept_id, b.dept_name, a.pic_id, a.pic_name FROM pr_req_user a 
                                join department b on a.office_id = b.office_id and a.dept_id = b.dept_id 
                                WHERE a.nik = '$userId2'"));
        
        $PicId = $PicData[0]->pic_id;
        $PicName = $PicData[0]->pic_name;
        $DeptId = $PicData[0]->dept_id;
        $DeptName = $PicData[0]->dept_name;
        $picList = [];

                foreach ($PicData as $key => $value) {
                    array_push($picList, [
                        'pic_id' => $value->pic_id,
                        'pic_name' => $value->pic_name
                    ]);
                }
        if ($AllDeptValue == 'N') {
            // $PicData = DB::connection("sqlSrvFPI")
            //     ->select(DB::raw("select a.dept_id, b.dept_name, a.pic_id, a.pic_name FROM pr_req_user a 
            //                     join department b on a.office_id = b.office_id and a.dept_id = b.dept_id 
            //                     WHERE a.nik = '$userId2'"));                          
                
                // $PicId = $PicData[0]->pic_id;
                // $PicName = $PicData[0]->pic_name;
                // $DeptId = $PicData[0]->dept_id;
                // $DeptName = $PicData[0]->dept_name;
                

            if ($PicData && isset($PicData[0])) {

                
                

                if (substr($DeptId, 0, 2) == 'EN') {
                    $DeptList = DB::connection("sqlSrvFPI")
                        ->select(DB::raw("SELECT dept_id, dept_name FROM department WHERE left(dept_id,2) = 'EN'"));
                    return response()->json([
                        // 'AllDept' => 'N',
                        // 'PicId' => $PicId,
                        // 'PicName' => $PicName,
                        'DeptId' => $DeptList,
                        'picList' => $picList
                        // 'DeptName' => ' '
                    ], 200);
                 } else {

                    $DeptList = DB::connection("sqlSrvFPI")
                        ->select(DB::raw("SELECT dept_id, dept_name FROM department WHERE dept_id = '$DeptId'"));
                    return response()->json([
                        // 'AllDept' => 'N',
                        // 'PicId' => $PicId,
                        // 'PicName' => $PicName,
                        'DeptId' => $DeptList,
                        // 'DeptName' => $DeptName
                        'picList' => $picList
                    ], 200);
                }
            } else {
                   
                    return response()->json([
                        // 'AllDept' => '',
                        // 'PicId' => '',
                        // 'PicName' => '',
                        'DeptId' => [],
                        // 'DeptName' => ''
                        'picList' => $picList
                    ], 404);
            }
        } else {
                $PicId = $PicData[0]->pic_id;
                $PicName = $PicData[0]->pic_name;
                $DeptId = $PicData[0]->dept_id;
                $DeptName = $PicData[0]->dept_name;
                $DeptList = DB::connection("sqlSrvFPI")
                        ->select(DB::raw("SELECT dept_id, dept_name FROM department"));
                    return response()->json([
                        // 'AllDept' => 'Y',
                        // 'PicId' => $PicId,
                        // 'PicName' => $PicName,
                        'DeptId' => $DeptList,
                        'picList' => $picList

                        // 'DeptName' => ''
                    ], 200);
            // Jika ALLDEPT = 'Y', bisa tambahkan logic lain jika perlu
            // return response()->json(['AllDept' => 'Y'], 200);
        }
    }


    public function getPrHdrList(Request $request)
    {

        $sqlWhere = '1=1 ';

        if ($request->dtawal) {
            if($request->dtakhir){
                $sqlWhere .= "and a.dt_pr between '" . $request->dtawal . "' and '" . $request->dtakhir . "' ";
            }else{
                $sqlWhere .= "and a.dt_pr = '$request->dtawal' ";
            }
        }

        if ($request->PrId) {
            $sqlWhere .= "and a.pr_id = '$request->PrId'";
        }
        if ($request->OfficeId) {
            $sqlWhere .= "and a.office_id = '$request->OfficeId'";
        }
        if ($request->DeptId) {
            $sqlWhere .= "and a.dept_id = '$request->DeptId'";
        } 
        if ($request->PicId) {
            $sqlWhere .= "and a.pic_id = '$request->PicId'";
        }
        if ($request->VendorId) {
            $sqlWhere .= "and a.vendor_id = '$request->VendorId'";
        }
        if ($request->Stat) {
            $sqlWhere .= "and a.stat = '$request->Stat'";
        }  
        if ($request->TypePr) {
            $sqlWhere .= "and a.type_pr = '$request->TypePr'";
        }
        if ($request->MemoTxt) {
            $sqlWhere .= "and a.memo_txt like '%$request->ArticleId%'";
        }   
        if ($request->MaxApprv) {
            $sqlWhere .= "and d.apprv = '$request->MaxApprv'";
        }

        if ($request->Apprv1 == 'Y') {
            $sqlWhere .= "and year(a.dt_aprv) > '2000' ";
        } else if ($request->Apprv1 == 'N') {
            $sqlWhere .= "and year(a.dt_aprv) < '2000' ";
        } 
                        
        if ($request->Apprv2 == 'Y') {
            $sqlWhere .= "and year(a.dt_aprv2) > '2000' ";
        } else if ($request->Apprv2 == 'N') {
            $sqlWhere .= "and year(a.dt_aprv2) < '2000' ";
        }

        if ($request->OfficeId) {
            $sqlWhere .= "and a.office_id = '$request->OfficeId' ";
        }
        $GridPr = DB::connection("sqlSrvFPI")
                ->select(DB::raw("SELECT a.office_id, a.pr_id, dt_pr, a.vendor_id, isnull(d.vendor_name,' ') as vendor_name, memo_txt,b.pic_name,
                                    a.dept_id,dept_name, dt_aprv,aprv_by,dt_aprv2,aprv2_by,dt_rjc, a.stat,
                                    (select sum(amount) from pr_item where pr_id = a.pr_id) as amount, 
                                    d.apprv, type_pr as type, isnull(v.tr_id,' ') as tr_id, isnull(v.inv_id,' ') as inv_id, a.memo_txt,
                                    a.penerima, a.project, a.alamat_kirim, a.pekerjaan,
                                    isnull(w.bank_name,' ') as bank_name, isnull(w.ac_bank_num,' ') as ac_bank_num, 
                                    isnull(w.ac_own_name,' ') as ac_name 
                                    FROM pr_hdr a 
                                    left outer join pr_req_user b on a.pic_id=b.pic_id
                                    left outer join department c on a.dept_id=c.dept_id 
                                    left outer join vendor d on a.vendor_id = d.vendor_id 
                                    left outer join purc_inv_mast v on a.pr_id = v.pr_id 
                                    left outer join v_vendor_bank w on a.vendor_id = w.vendor_id
                                    WHERE $sqlWhere order by a.dt_pr, a.pr_id desc"));

         $GridDtl = DB::connection("sqlSrvFPI")
                ->select(DB::raw("SELECT a.office_id, a.pr_id, a.dt_pr, e.dept_name, f.pic_name, isnull(a.vendor_id,' ') as vendor_id, 
                                    isnull(d.vendor_name,' ') as vendor_name, b.pr_item, b.article_id, c.description, b.stat, b.remark, b.qty, 
                                    b.unit_meas, b.unit_meas, b.amount, b.qty_po, b.dt_req FROM pr_hdr a JOIN pr_item b ON a.pr_id = b.pr_id 
                                    left outer join article c ON b.article_id = c.article_id 
                                    left outer join vendor d on a.vendor_id = d.vendor_id
                                    join department e on a.dept_id = e.dept_id
                                    join pr_req_user f on a.pic_id = f.pic_id
                                    left outer join site s on b.id_site = s.id_site
                                    left outer join site_cluster t on b.id_site = t.id_site and b.id_cluster = t.id_cluster
                                    left outer join house_unit h on b.id_site = h.id_site and b.id_cluster = h.id_cluster and b.id_house = h.id_house
                                    where $sqlWhere order by a.pr_id, b.pr_item"));

        return response()->json(['GridPr' => $GridPr, 'GridDtl' => $GridDtl], 200);
    }

    public function getDetailPrList(Request $request)
    {

        $sqlWhere = '1=1 ';

        if ($request->dtawal) {
            if($request->dtakhir){
                $sqlWhere .= "and dt_pr between '$request->dtawal' and '$request->dtakhir' ";
            }else{
                $sqlWhere .= "and dt_pr = '$request->dtawal' ";
            }
        }

        if ($request->PrId) {
            $sqlWhere .= "and pr_id = '$request->PrId'";
        }
        if ($request->OfficeId) {
            $sqlWhere .= "and office_id = '$request->OfficeId'";
        }
        if ($request->DeptId) {
            $sqlWhere .= "and dept_id = '$request->DeptId'";
        }
        if ($request->PicId) {
            $sqlWhere .= "and pic_id = '$request->PicId'";
        }
        if ($request->VendorId) {
            $sqlWhere .= "and vendor_id = '$request->VendorId'";
        }
        if ($request->Stat) {
            $sqlWhere .= "and stat = '$request->Stat'";
        }  
        if ($request->typePr) {
            $sqlWhere .= "and tr_type = '$request->TypePr'";
        }
        if ($request->ArticleId) {
            $sqlWhere .= "and article_id = '$request->ArticleId'";
        }
        if( $request-> IdSite) {
            $sqlWhere .= "and id_site = '$request->IdSite'";
        }
        if( $request-> IdCluster) {
            $sqlWhere .= "and id_cluster = '$request->IdCluster'";
        }
        if( $request-> IdHouse) {
            $sqlWhere .= "and id_house = '$request->IdHouse'";
        }
            
        $GridPr = DB::connection("sqlSrvFPI")
                ->select(DB::raw("SELECT * from v_progress_pr where $sqlWhere"));

        return response()->json(['GridPr' => $GridPr], 200);
    }

    public function printDoc(Request $request) 
    {
        
        $office_id = $request->office_id;
        $pr_id = $request->pr_id;
        $sqlWhere = '1=1 ';

        if ($office_id) {
            $sqlWhere .= "and a.office_id = '$office_id'";
        }

        if ($pr_id) {
            $sqlWhere .= "and a.pr_id = '$pr_id'";
        }

        $pr_hdr = DB::connection("sqlSrvFPI")
                    ->table('pr_hdr as a')
                    ->leftJoin('pr_req_user as b', function($join) {
                        $join->on('a.pic_id', '=', 'b.pic_id')
                            ->on('a.dept_id', '=', 'b.dept_id');
                    })
                    ->leftJoin('department as c', 'a.dept_id', '=', 'c.dept_id')
                    ->leftJoin('vendor as d', 'a.vendor_id', '=', 'd.vendor_id')
                    ->leftJoin('purc_inv_mast as v', 'a.pr_id', '=', 'v.pr_id')
                    ->leftJoin('v_vendor_bank as w', 'a.vendor_id', '=', 'w.vendor_id')
                    ->selectRaw("
                        a.office_id, a.pr_id, dt_pr, a.vendor_id, isnull(d.vendor_name,' ') as vendor_name, memo_txt, b.pic_name,
                        a.dept_id, dept_name, dt_aprv, aprv_by, dt_aprv2, aprv2_by, dt_rjc, a.stat,
                        (select sum(amount) from pr_item where pr_id = a.pr_id) as amount, 
                        d.apprv, type_pr as type, isnull(v.tr_id,' ') as tr_id, isnull(v.inv_id,' ') as inv_id, a.memo_txt,
                        a.penerima, a.project, a.alamat_kirim, a.pekerjaan,
                        isnull(w.bank_name,' ') as bank_name, isnull(w.ac_bank_num,' ') as ac_bank_num, 
                        isnull(w.ac_own_name,' ') as ac_name
                    ")
                    ->whereRaw($sqlWhere)
                    ->orderBy('a.dt_pr', 'desc')
                    ->first();

        $pr_dtl = DB::connection("sqlSrvFPI")
                ->select(DB::raw("select a.*, b.description, b.berat, b.vendor_description, site_name,cluster_name,house_name, a.dt_req
                                    FROM pr_item a JOIN article b
                                    ON a.article_id = b.article_id
                                    left outer join site s on a.id_site = s.id_site
                                    left outer join site_cluster t on a.id_site = t.id_site and a.id_cluster = t.id_cluster
                                    left outer join house_unit h on a.id_site = h.id_site and a.id_cluster = h.id_cluster and a.id_house = h.id_house
                                    where $sqlWhere"));

        return response()->json([
            'message' => 'ok', 
            'pr_hdr' => $pr_hdr,
            'pr_dtl' => $pr_dtl
        ], 200);

    }

    public function siteOnChange(Request $request)
    {
        $site = $request->site;
        $sqlWhere = '1=1 ';

        if ($site) {
            $sqlWhere .= "and id_site = '$site'";
        }

        $cluster = DB::connection("sqlSrvFPI")
                ->table('site_cluster')
                ->selectRaw('trim(id_cluster) as id_cluster, trim(cluster_name) as cluster_name')
                ->whereRaw($sqlWhere)
                ->get();        

        return response()->json([
            'message' => 'ok', 
            'cluster' => $cluster
        ], 200);
    }

    public function clusterOnChange(Request $request)
    {
        $cluster = $request->cluster;
        $sqlWhere = '1=1 ';

        if ($cluster) {
            $sqlWhere .= "and id_cluster = '$cluster'";
        }

        $house = DB::connection("sqlSrvFPI")
                ->table('house_unit')
                ->selectRaw('trim(id_house) as id_house, trim(house_name) as house_name')
                ->whereRaw($sqlWhere . "and active_flag = 'Y'")
                ->get();

        return response()->json([
            'message' => 'ok', 
            'house' => $house,
        ], 200);
    }

    public function closePurchaseRequest(Request $request)
    {
        
        $user_id = getAuth($request)->user_id2;

        try {
            DB::connection("sqlSrvFPI")->beginTransaction();

            $update = DB::connection("sqlSrvFPI")
                ->statement(DB::raw("update pr_hdr set stat = 'C', dt_modified = getdate(), user_id = '$user_id' where pr_id = '$request->PrId'"));

            $updateItem = DB::connection("sqlSrvFPI")
                ->statement(DB::raw("update pr_item set stat = 'C', dt_modified = getdate(), user_id = '$user_id' where pr_id = '$request->PrId'"));

            DB::connection("sqlSrvFPI")->commit();
        
            return response()->json(['result' => 'success'], 200);
            
        } catch (\Exception $e) {
            DB::connection("sqlSrvFPI")->rollBack();
            return $this->sendError('Validation Error', $e->getMessage());
        }

    }

}