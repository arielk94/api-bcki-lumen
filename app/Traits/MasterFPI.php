<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait MasterFPI
{

    public function getVendor($id)
    {

        $sqlWhere = '1=1 ';

        if ($id) {
            $sqlWhere .= "and vendor_id = '$id' or vendor_name like '%$id%'";
        }

        $GVendor = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select trim(vendor_id) as vendor_id, concat(trim(vendor_name), ' (', trim(vendor_id), ')') as vendor_name from vendor where $sqlWhere and active_flag = 'Y'"));

        return $GVendor;
    }
    
    public function getOffice($id)
    {

        $sqlWhere = '1=1 ';

        if ($id) {
            $sqlWhere .= "and office_id = '$id' or office_name like '%$id%'";
        }

        $GOffice = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select office_id,
                                TRIM(office) AS office,
                                TRIM(address) AS address,
                                TRIM(city) AS city,
                                TRIM(zip_code) AS zip_code,
                                TRIM(e_mail) AS e_mail,
                                TRIM(ka_cab) AS ka_cab,
                                TRIM(mill_id) AS mill_id,
                                TRIM(mach_ip) AS mach_ip,
                                TRIM(finger_sn) AS finger_sn,
                                TRIM(finger_VP) AS finger_VP,
                                TRIM(finger_ac) AS finger_ac,
                                TRIM(finger_vkey) AS finger_vkey,
                                TRIM(active_flag) AS active_flag,
                                TRIM(region) AS region,
                                TRIM(rm) AS rm,
                                TRIM(lat) AS lat,
                                TRIM(lng) AS lng,
                                TRIM(region_proyek) AS region_proyek,
                                TRIM(rm_proyek) AS rm_proyek,
                                TRIM(tlp) AS tlp,
                                dt_modified,
                                TRIM(modified_by) AS modified_by,
                                TRIM(sales_mgr) AS sales_mgr from branch_office where $sqlWhere and active_flag = 'Y'"));

        return $GOffice;
    }
    
    public function getDepartment($UserId, $id)
    {
        // $UserId = Auth::user()->user_id2;
        $AllDept = DB::connection("sqlSrvBCKI")
                ->table('sec_env_conf')
                ->where('user_id2', '=', $UserId)
                ->where('var_id', '=', 'ALLDEPT')
                ->where('var_value', '=', 'Y')
                ->first();
        
        if ($AllDept) {
           $sqlWhere = '1=1 ';
           if($id) {
                $sqlWhere .= "and dept_id = '$id' or dept_name like '%$id%'";
            }

            $GDepartment = DB::connection("sqlSrvBCKI")
                    ->select(DB::raw("select office_id,
                                    TRIM(dept_id) AS dept_id,
                                    TRIM(dept_name) AS dept_name,
                                    TRIM(nama_pic) AS nama_pic,
                                    TRIM(login_user_name) AS login_user_name,
                                    TRIM(cost_ctr_id) AS cost_ctr_id,
                                    TRIM(active_flag) AS active_flag,
                                    dt_created,
                                    dt_modified,
                                    TRIM(user_id) AS user_id,
                                    TRIM(pr1) AS pr1,
                                    TRIM(pr2) AS pr2,
                                    TRIM(po1) AS po1,
                                    TRIM(po2) AS po2,
                                    TRIM(nik_pr1) AS nik_pr1,
                                    TRIM(nik_pr2) AS nik_pr2,
                                    TRIM(nik_po1) AS nik_po1,
                                    TRIM(nik_po2) AS nik_po2,
                                    TRIM(kb1) AS kb1,
                                    TRIM(kb2) AS kb2,                                    
                                    TRIM(nik_kb1) AS nik_kb1,                                    
                                    TRIM(nik_kb2) AS nik_kb2 from department where $sqlWhere and active_flag = 'Y'"));
            
        } else {
            $UserPic = DB::connection("sqlSrvBCKI")
                    ->table('pr_req_user')
                    ->where('nik', '=', $UserId)
                    ->where('active_flag', '=', 'Y')
                    ->first();

            if ($UserPic) {
                $sqlWhere = '1=1 ';
                if($id) {
                    $sqlWhere .= "and dept_id = '$id' or dept_name like '%$id%'"; 
                }  

                $GDepartment = DB::connection("sqlSrvBCKI")
                        ->select(DB::raw("select a.office_id,
                                        TRIM(a.dept_id) AS dept_id,
                                        TRIM(a.dept_name) AS dept_name,
                                        TRIM(a.nama_pic) AS nama_pic,
                                        TRIM(a.login_user_name) AS login_user_name,
                                        TRIM(a.cost_ctr_id) AS cost_ctr_id,
                                        TRIM(a.active_flag) AS active_flag,
                                        a.dt_created,
                                        a.dt_modified,
                                        TRIM(a.user_id) AS user_id,
                                        TRIM(a.pr1) AS pr1,
                                        TRIM(a.pr2) AS pr2,
                                        TRIM(a.po1) AS po1,
                                        TRIM(a.po2) AS po2,
                                        TRIM(a.nik_pr1) AS nik_pr1,
                                        TRIM(a.nik_pr2) AS nik_pr2,
                                        TRIM(a.nik_po1) AS nik_po1,
                                        TRIM(a.nik_po2) AS nik_po2,
                                        TRIM(a.kb1) AS kb1,
                                        TRIM(a.kb2) AS kb2,
                                        TRIM(a.nik_kb1) AS nik_kb1,
                                        TRIM(a.nik_kb2) AS nik_kb2 from department a    
                                        join pr_req_user b on a.dept_id = b.dept_id where $sqlWhere 
                                        and b.nik = '$UserId' and a.active_flag = 'Y'"));               
            } else {
                $sqlWhere = '1=1 ';
                if($id) {
                        $sqlWhere .= "and dept_id = '$id' or dept_name like '%$id%'";
                    }

                    $GDepartment = DB::connection("sqlSrvBCKI")
                            ->select(DB::raw("select office_id,
                                            TRIM(dept_id) AS dept_id,
                                            TRIM(dept_name) AS dept_name,
                                            TRIM(nama_pic) AS nama_pic,
                                            TRIM(login_user_name) AS login_user_name,
                                            TRIM(cost_ctr_id) AS cost_ctr_id,
                                            TRIM(active_flag) AS active_flag,
                                            dt_created,
                                            dt_modified,
                                            TRIM(user_id) AS user_id,
                                            TRIM(pr1) AS pr1,
                                            TRIM(pr2) AS pr2,
                                            TRIM(po1) AS po1,
                                            TRIM(po2) AS po2,
                                            TRIM(nik_pr1) AS nik_pr1,
                                            TRIM(nik_pr2) AS nik_pr2,
                                            TRIM(nik_po1) AS nik_po1,
                                            TRIM(nik_po2) AS nik_po2,
                                            TRIM(kb1) AS kb1,
                                            TRIM(kb2) AS kb2,                                    
                                            TRIM(nik_kb1) AS nik_kb1,                                    
                                            TRIM(nik_kb2) AS nik_kb2 from department where $sqlWhere and active_flag = 'Y'"));
            }
        }

        return $GDepartment;
    }

    public function getPicName($UserId, $DeptId, $id)
    {
        //$UserId = Auth::user()->user_id2;
        $AllDept = DB::connection("sqlSrvBCKI")
                ->table('sec_env_conf')
                ->where('user_id', '=', $UserId)
                ->where('var_id', '=', 'ALLDEPT')
                ->where('var_value', '=', 'Y')
                ->first();
        if ($AllDept) {
            $sqlWhere = '1=1 ';
            if ($id) {
                $sqlWhere .= "and pic_id = '$id' or pic_name like '%$id%'";
            }
            if ($DeptId) {
                $sqlWhere .= "and dept_id = '$DeptId'";
            }
    
            $GPic = DB::connection("sqlSrvBCKI")
                    ->select(DB::raw("select office_id,
                                    TRIM(dept_id) AS dept_id,
                                    TRIM(pic_id) AS pic_id,
                                    TRIM(pic_name) AS pic_name,
                                    TRIM(active_flag) AS active_flag,
                                    dt_created,
                                    dt_modified,
                                    TRIM(user_id) AS user_id,
                                    TRIM(nik) AS nik from pr_req_user where $sqlWhere and active_flag = 'Y'"));
        } else {
            $Pic = DB::connection("sqlSrvBCKI")
                ->table('pr_req_user')
                ->where('nik', '=', $UserId)
                ->first();
            if ($Pic) {
                $sqlWhere = '1=1 ';
                if($id) {
                    $sqlWhere .= "and pic_id = '$id' or pic_name like '%$id%'";    
                }
                if($DeptId) {
                    $sqlWhere .= "and dept_id = '$DeptId'";
                }
                $GPic = DB::connection("sqlSrvBCKI")
                        ->select(DB::raw("select office_id,
                                        TRIM(dept_id) AS dept_id,
                                        TRIM(pic_id) AS pic_id,
                                        TRIM(pic_name) AS pic_name,
                                        TRIM(active_flag) AS active_flag,
                                        dt_created,
                                        dt_modified,
                                        TRIM(user_id) AS user_id,
                                        TRIM(nik) AS nik from pr_req_user where $sqlWhere and active_flag = 'Y' and nik = '$UserId'"));
            } else {
                $sqlWhere = '1=1 ';
                if ($id) {
                    $sqlWhere .= "and pic_id = '$id' or pic_name like '%$id%'";
                }
                if ($DeptId) {
                    $sqlWhere .= "and dept_id = '$DeptId'";
                }
        
                $GPic = DB::connection("sqlSrvBCKI")
                        ->select(DB::raw("select office_id,
                                        TRIM(dept_id) AS dept_id,
                                        TRIM(pic_id) AS pic_id,
                                        TRIM(pic_name) AS pic_name,
                                        TRIM(active_flag) AS active_flag,
                                        dt_created,
                                        dt_modified,
                                        TRIM(user_id) AS user_id,
                                        TRIM(nik) AS nik from pr_req_user where $sqlWhere and active_flag = 'Y'"));
            }
                      
        }

        return $GPic;
    }

    public function getArticleId($id)
    {
        $sqlWhere = '1=1 ';
        if ($id) {
            $sqlWhere .= "and article_id = '$id' or description like '%$id%'";
        }

        $GArticle = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select article_id,
                                TRIM(barcode) AS barcode,
                                TRIM(description) AS description,
                                TRIM(varian) AS varian,
                                TRIM(mku) AS mku,
                                TRIM(uom) AS uom,
                                TRIM(uom2) AS uom2,
                                uom_qty2,
                                TRIM(uom3) AS uom3,
                                uom_qty3,
                                TRIM(uom4) AS uom4,
                                uom_qty4,
                                TRIM(uom5) AS uom5,
                                uom_qty5,
                                TRIM(ppn) AS ppn,
                                TRIM(brand) AS brand,
                                cat_id,
                                com_id,
                                com_grp,
                                min_stock,
                                berat,
                                TRIM(import_flag) AS import_flag,
                                TRIM(user_id) AS user_id,
                                TRIM(img) AS img,
                                TRIM(consumable) AS consumable,
                                TRIM(ac_num_persd) AS ac_num_persd,
                                TRIM(ac_num_exp) AS ac_num_exp,
                                TRIM(ac_num_penj) AS ac_num_penj,
                                TRIM(ac_num_aktiva) AS ac_num_aktiva,
                                TRIM(cost_ctr_id) AS cost_ctr_id,
                                TRIM(active_flag) AS active_flag,
                                dt_modified,
                                TRIM(BarangJadiId) AS BarangJadiId,
                                TRIM(add_id) AS add_id,
                                TRIM(tor_id) AS tor_id,
                                [length], -- Kolom 'length' perlu diapit tanda kurung siku karena merupakan reserved keyword
                                vendor_description,
                                internal_brand,
                                vendor_id,
                                TRIM(article_id_supp) AS article_id_supp,
                                TRIM(az) AS az,
                                TRIM(grade) AS grade,
                                thick,
                                TRIM(material) AS material,
                                TRIM(modified_by) AS modified_by from article where $sqlWhere and active_flag = 'Y'"));

        return $GArticle;
    }

    public function getIdSite($id)
    {
        $sqlWhere = '1=1 ';

        if ($id) {
            $sqlWhere .= "and id_site = '$id' or site_name like '%$id%'";
        }

        $GSite = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select * from site where $sqlWhere and stat = 'Y'"));
        
        return $GSite;
    }

    public function getIdCluster($id)
    {
        $sqlWhere = '1=1 ';

        if ($id) {
            $sqlWhere .= "and id_cluster = '$id' or cluster_name like '%$id%'";
        }

        $GCluster = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select * from site_cluster where $sqlWhere and stat = 'Y' "));
        return $GCluster;
    }
      
    public function getIdHouse($id)
    {
        $sqlWhere = '1=1 ';

        if ($id) {
            $sqlWhere .= "and id_house = '$id' or house_name like '%$id%'";
        }

        $GHouse = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select * from house_unit where $sqlWhere and active_flag = 'Y' "));
        return $GHouse;
        
    }
    public function getPayTerm($id) 
    {
        $sqlWhere = '1=1 ';

        if ($id) {
            $sqlWhere .= "and pay_term_id = '$id' or pay_term_desc ike '%$id%'";
        }

        $GPayTerm = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select trim(pay_term_id) as pay_term_id, trim(pay_term_desc) as pay_term_desc, num_of_days from pay_term where $sqlWhere and active_flag = 'Y'"));

        return $GPayTerm;
    }                    

    public function getCurrId($id) 
    {
        $sqlWhere = '1=1 ';

        if ($id) {
            $sqlWhere .= "and curr_id = '$id' or symbol like '%$id%'";
        }

        $GCurrId = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select 
                                    office_id,
                                    TRIM(curr_id) AS curr_id,
                                    exchange_rate,
                                    rate,
                                    TRIM(country_name) AS country_name,
                                    TRIM(symbol) AS symbol,
                                    active_flag,
                                    dt_created,
                                    dt_modified,
                                    TRIM(user_id) AS user_id
                                    from currency where $sqlWhere and active_flag = 'Y'"));

        return $GCurrId;
    }       

    public function sendWA($phone, $message)
    {

        try {

            $url = 'https://notifapi.com/send_message';

            $data = array(
                "phone_no" => $phone,
                "key"      => '397bb50fd21e7c0f41b71eed483006ac45d42a47d6d43b79',
                "message"  => $message
            );

            $data_string = json_encode($data,1);

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

            if($res == 'Success' || $res == 'success' ){
                $stat = 1;
                $msg = $res;
            } else {
                $stat = 0;
                $msg = 'There something wrong, please check the phone number. err : '.$res;
            }

            $dataRes = ['stat' => $stat, 'msg' => $msg];

            return $dataRes;

        } catch (\Exception $e) {
            return $e;
        }

    }

    public function fetchEnvConf($appl_id,$var_id,$active_flag,$user_id2) 
    {
        try {
            $query = DB::connection('sqlsrv')
                            ->table('sec_env_conf')
                            ->select('var_value')
                            ->where('user_id2', $user_id2)
                            ->where('entity_id', 'bcki')
                            ->where('appl_id', $appl_id)
                            ->where('var_id', $var_id)
                            ->where('active_flag', $active_flag)
                            ->value('var_value');

            return $query;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }       
}