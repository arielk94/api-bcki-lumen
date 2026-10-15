<?php

namespace App\Http\Controllers\master;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/bcki/customer/showcust",
     *     tags={"Master Customer"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *              @OA\Property(property="CustomerId", type="string", example="ABC"),
     *              @OA\Property(property="NamaCustomer", type="string", example="ABC"),
     *              @OA\Property(property="active_flag", type="string", example="Y"),
     *              @OA\Property(property="coffice_id", type="string", example="ABC"),
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all Customer",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */

    public function index(Request $request)
    {
        $sqlWhere = "1=1";
        $CustomerId = $request->CustomerId;
        $NamaCustomer = $request->NamaCustomer;
        $active_flag = $request->active_flag;
        $coffice_id = $request->coffice_id;

        if ($CustomerId) {
            $sqlWhere .= " and Customer.CustomerId = '$CustomerId'";
        }
        if ($NamaCustomer) {
            $sqlWhere .= " and Customer.NamaCustomer = '$NamaCustomer'";
        }
        if ($active_flag) {
            $sqlWhere .= " and Customer.active_flag = '$active_flag'";
        }
        if ($coffice_id) {
            $sqlWhere .= " and Customer.coffice_id = '$coffice_id'";
        }

        $datas = DB::connection('sqlSrvBCKI')
            ->table('Customer')
            ->selectraw("
                        TRIM(Customer.CustomerId) as CustomerId, 
                        TRIM(Customer.NamaCustomer) as NamaCustomer, 
                        TRIM(Customer.Alamat) as Alamat, --alamat usaha customer
                        TRIM(Customer.Kota) as Kota, 
                        TRIM(Customer.Telepon) as Telepon, 
                        TRIM(Customer.active_flag) as active_flag, 
                        TRIM(Customer.cust_group) as cust_group,
                        TRIM(Customer.Fax) as Fax, --fax customer
                        TRIM(Customer.Email) as Email, --email customer
                        TRIM(Customer.no_nib) as no_nib,
                        TRIM(Customer.no_skd) as no_skd,
                        TRIM(Customer.Propinsi) as Propinsi, --propinsi usaha
                        TRIM(Customer.district) as district, --kecamatan usaha
                        TRIM(Customer.Region) as Region, --region usaha
                        TRIM(Customer.vilage) as vilage, --desa/kel usaha
                        TRIM(Customer.office_tipe) as office_tipe, --tipe office
                        TRIM(Customer.employee_num) as employee_num,
                        TRIM(Customer.npwp) as npwp,
                        TRIM(Customer.skbdn) as skbdn,
                        TRIM(Customer.business_status) as business_status, --status usaha
                        TRIM(Customer.business_entities) as business_entities, --status badan usaha
                        TRIM(Customer.tax_status) as tax_status, --status perpajakan
                        TRIM(Customer.kind_of_business) as kind_of_business, --jenis usaha
                        TRIM(Customer.ownership) as ownership, --kepemilikan usaha
                        TRIM(Customer.building) as building, --fisik usaha
                        TRIM(Customer.omzet) as omzet, --omset
                        TRIM(Customer.id_card_no) as id_card_no, --no ktp
                        Customer.credit_limit, --credit limit,
                        Customer.coffice_id, --coffice id,
                        Customer.business_name,
                        Customer.owner_name,
                        Customer.date_of_birth as dob,
                        cl.city as city_name,
                        CASE
                            when TRIM(Customer.cust_type) = 'O' then 'OTHER'
                            when TRIM(Customer.cust_type) = 'P' then 'PROJECT'
                            when TRIM(Customer.cust_type) = 'R' then 'RETAIL'
                            ELSE ''
                        END AS cust_type,
                        FORMAT(Customer.business_start_date,'dd-MM-yyy') as tanggal_mulai_usaha, --tanggal mulai usaha
                        TRIM(branch_office.office) as office, 
                        FORMAT(Customer.PerTanggal,'dd-MM-yyyy') as date
                    ")
            ->leftJoin('branch_office', function ($join) {
                $join->on('Customer.coffice_id', '=', 'branch_office.office_id');
            })
            ->leftJoin('city_list as cl', 'Customer.Kota', '=', 'cl.city_code')
            ->whereRaw($sqlWhere)
            ->orderBy('Customer.NamaCustomer', 'ASC')
            ->get();

        return $this->sendResponse($datas, 'Successfully get cust');
    }

    /**
     * @OA\Post(
     *     path="/api/bcki/customer/CustGroup",
     *     tags={"Master Customer"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *              @OA\Property(property="NamaCustomer", type="string", example="ABC"),
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all Customer Group",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */

    public function showCustGroup(Request $request)
    {
        $sqlWhere = "1=1";
        $NamaCustomer = $request->NamaCustomer;

        if ($NamaCustomer) {
            $sqlWhere .= " and Customer.NamaCustomer like '%$NamaCustomer%'";
        }

        $result = DB::connection('sqlSrvBCKI')
            ->table('Customer')
            ->selectRaw('DISTINCT CustomerId,NamaCustomer,Alamat')
            ->whereRaw($sqlWhere)
            ->get();

        return $this->sendResponse($result, 'Successfully get Customer');
    }

    public function switchActive(Request $request)
    {
        $active_flag = $request->active_flag;
        $customer_id = $request->customer_id;

        $result = DB::connection('sqlSrvBCKI')
            ->table('Customer')
            ->where('CustomerId', '=', $customer_id)
            ->update([
                'active_flag' => $active_flag
            ]);

        return $this->sendResponse($result, 'Successfully get Customer');
    }

    private function insCustType($cust_type)
    {

        if ($cust_type == 'OTHER') {
            $cust_type = 'O';
        } else if ($cust_type == 'PROJECT') {
            $cust_type = 'P';
        } else if ($cust_type == 'RETAIL') {
            $cust_type = 'R';
        } else {
            $cust_type = ' ';
        }
        return $cust_type;
    }

    private function genCustID($CustomerId)
    {
        $gen_cust_id = '';

        $getLastCustID =  DB::connection('sqlSrvBCKI')
            ->table('Customer')
            ->select(DB::raw('max(cast(Customerid as int))'))
            ->value('max(cast(Customerid as int))');

        if ($getLastCustID) {
            $gen_cust_id = (int)$getLastCustID + 1;

            if ($gen_cust_id < 10) {
                $gen_cust_id = '00000' . $gen_cust_id;
            } else if ($gen_cust_id >= 10 and $gen_cust_id < 100) {
                $gen_cust_id = '0000' . $gen_cust_id;
            } else if ($gen_cust_id >= 100 and $gen_cust_id < 1000) {
                $gen_cust_id = '000' . $gen_cust_id;
            } else if ($gen_cust_id >= 1000 and $gen_cust_id < 10000) {
                $gen_cust_id = '00' . $gen_cust_id;
            } else if ($gen_cust_id >= 10000 and $gen_cust_id < 100000) {
                $gen_cust_id = '0' . $gen_cust_id;
            } else if ($gen_cust_id >= 100000 and $gen_cust_id < 1000000) {
                $gen_cust_id = $gen_cust_id;
            }
        } else {
            return '000001';
        }

        return $gen_cust_id;
    }

    public function saveCustomer(Request $request)
    {
        try {
            if ($request->CustomerId != '') {
                $cust_type = $this->insCustType($request->cust_type);
                $update = DB::connection('sqlSrvBCKI')
                    ->table('Customer')
                    ->where('CustomerId', $request->CustomerId)
                    ->update([
                        'SalesId' => $request->SalesId ? $request->SalesId : ' ',
                        'NamaCustomer' => $request->NamaCustomer ? $request->NamaCustomer : ' ',
                        'Alamat' => $request->Alamat ? $request->Alamat : ' ',
                        'Kota' => $request->Kota ? $request->Kota : ' ',
                        'Telepon' => $request->Telepon ? $request->Telepon : ' ',
                        'PerTanggal' => $request->PerTanggal ? $request->PerTanggal : now(), //tanggal
                        'Fax' => $request->Fax ? $request->Fax : ' ',
                        'NPWP' => $request->NPWP ? $request->NPWP : ' ',
                        'Person' => $request->Person ? $request->Person : ' ',
                        'Jabatan' => $request->Jabatan ? $request->Jabatan : ' ',
                        'Real_City' => $request->Real_City ? $request->Real_City : ' ',
                        'Propinsi' => $request->Propinsi ? $request->Propinsi : ' ',
                        'Region' => $request->Region ? $request->Region : ' ',
                        'Banned' => $request->Banned ? $request->Banned : ' ',
                        'payterm' => $request->payterm ? $request->payterm : ' ',
                        'credit_limit' => $request->credit_limit ? $request->credit_limit : ' ',
                        'banned_fee' => $request->banned_fee ? $request->banned_fee : ' ',
                        'cust_group' => $request->cust_group ? $request->cust_group : ' ',
                        'email' => $request->email ? $request->email : ' ',
                        'business_start_date' => $request->business_start_date ? $request->business_start_date : now(),
                        'business_status' => $request->business_status ? $request->business_status : ' ',
                        'business_entities' => $request->business_entities ? $request->business_entities : ' ',
                        'kind_of_business' => $request->kind_of_business ? $request->kind_of_business : ' ',
                        'building' => $request->building ? $request->building : ' ',
                        'employee_num' => $request->employee_num ? $request->employee_num : ' ',
                        'tax_status' => $request->tax_status ? $request->tax_status : ' ',
                        'office_tipe' => $request->office_tipe ? $request->office_tipe : ' ',
                        'ownership' => $request->ownership ? $request->ownership : ' ',
                        'known_from' => $request->known_from ? $request->known_from : ' ',
                        'omzet' => $request->omzet ? $request->omzet : ' ',
                        'owner_name' => $request->owner_name ? $request->owner_name : ' ',
                        'date_of_birth' => $request->dob ? $request->dob : now(), //tanggal
                        'city_of_birth' => $request->city_of_birth ? $request->city_of_birth : ' ',
                        'husband_wife' => $request->husband_wife ? $request->husband_wife : ' ',
                        'id_card' => $request->id_card ? $request->id_card : 'KTP', //KTP
                        'id_card_no' => $request->id_card_no ? $request->id_card_no : ' ',
                        'copi_id_card' => $request->copi_id_card ? $request->copi_id_card : ' ',
                        'owner_address' => $request->owner_address ? $request->owner_address : ' ',
                        'owner_province' => $request->owner_province ? $request->owner_province : ' ',
                        'owner_city' => $request->owner_city ? $request->owner_city : ' ',
                        'owner_phone' => $request->owner_phone ? $request->owner_phone : ' ',
                        'owner_hp' => $request->owner_hp ? $request->owner_hp : ' ',
                        'owner_email' => $request->owner_email ? $request->owner_email : ' ',
                        'hari_raya' => $request->hari_raya ? $request->hari_raya : ' ',
                        'bank1' => $request->bank1 ? $request->bank1 : ' ',
                        'bank2' => $request->bank2 ? $request->bank2 : ' ',
                        'bank3' => $request->bank3 ? $request->bank3 : ' ',
                        'bank_atas_nama1' => $request->bank_atas_nama1 ? $request->bank_atas_nama1 : ' ',
                        'bank_atas_nama2' => $request->bank_atas_nama2 ? $request->bank_atas_nama2 : ' ',
                        'bank_atas_nama3' => $request->bank_atas_nama3 ? $request->bank_atas_nama3 : ' ',
                        'payment_tipe1' => $request->payment_tipe1 ? $request->payment_tipe1 : ' ',
                        'payment_tipe2' => $request->payment_tipe2 ? $request->payment_tipe2 : ' ',
                        'payment_tipe3' => $request->payment_tipe3 ? $request->payment_tipe3 : ' ',
                        'alamat_penagihan' => $request->alamat_penagihan ? $request->alamat_penagihan : ' ',
                        'propinsi_penagihan' => $request->propinsi_penagihan ? $request->propinsi_penagihan : ' ',
                        'kota_penagihan' => $request->kota_penagihan ? $request->kota_penagihan : ' ',
                        'tlp_penagihan' => $request->tlp_penagihan ? $request->tlp_penagihan : ' ',
                        'fax_penagihan' => $request->fax_penagihan ? $request->fax_penagihan : ' ',
                        'hari_penagihan' => $request->hari_penagihan ? $request->hari_penagihan : ' ',
                        'character' => $request->character ? $request->character : ' ',
                        'character_desc' => $request->character_desc ? $request->character_desc : ' ',
                        'capacity' => $request->capacity ? $request->capacity : ' ',
                        'capacity_desc' => $request->capacity_desc ? $request->capacity_desc : ' ',
                        'capital' => $request->capital ? $request->capital : ' ',
                        'capital_desc' => $request->capital_desc ? $request->capital_desc : ' ',
                        'collateral' => $request->collateral ? $request->collateral : ' ',
                        'collateral_dessc' => $request->collateral_dessc ? $request->collateral_dessc : ' ',
                        'condition' => $request->condition ? $request->condition : ' ',
                        'condition_desc' => $request->condition_desc ? $request->condition_desc : ' ',
                        'cust_note' => $request->cust_note ? $request->cust_note : ' ',
                        'tr_date' => $request->tr_date ? $request->tr_date : now(), //tanggal
                        'approve1_date' => $request->approve1_date ? $request->approve1_date : now(), //tanggal
                        'approve2_date' => $request->approve2_date ? $request->approve2_date : now(), //tanggal
                        'active_flag' => $request->active_flag ? $request->active_flag : ' ',
                        'coffice_id' => $request->coffice_id ? $request->coffice_id : ' ',
                        'salesman' => $request->salesman ? $request->salesman : ' ',
                        'affiliate' => $request->affiliate ? $request->affiliate : ' ',
                        'district' => $request->district ? $request->district : ' ',
                        'vilage' => $request->vilage ? $request->vilage : ' ',
                        'cv' => $request->cv ? $request->cv : ' ',
                        'price_role' => $request->price_role ? $request->price_role : ' ',
                        'price_role2' => $request->price_role2 ? $request->price_role2 : ' ',
                        'cv_id' => $request->cv_id ? $request->cv_id : ' ',
                        'no_nib' => $request->no_nib ? $request->no_nib : ' ',
                        'no_skd' => $request->no_skd ? $request->no_skd : ' ',
                        'pic_order' => $request->pic_order ? $request->pic_order : ' ',
                        'pic_order_jabatan' => $request->pic_order_jabatan ? $request->pic_order_jabatan : ' ',
                        'pic_order_telp' => $request->pic_order_telp ? $request->pic_order_telp : ' ',
                        'pic_order_email' => $request->pic_order_email ? $request->pic_order_email : ' ',
                        'pic_tagihan' => $request->pic_tagihan ? $request->pic_tagihan : ' ',
                        'pic_tagihan_jabatan' => $request->pic_tagihan_jabatan ? $request->pic_tagihan_jabatan : ' ',
                        'pic_tagihan_telp' => $request->pic_tagihan_telp ? $request->pic_tagihan_telp : ' ',
                        'pic_tagihan_email' => $request->pic_tagihan_email ? $request->pic_tagihan_email : ' ',
                        'metode_bayar' => $request->metode_bayar ? $request->metode_bayar : ' ',
                        'dt_modified' => now(),
                        'skbdn' => $request->skbdn ? $request->skbdn : ' ',
                        'cust_type' => $cust_type,
                        'business_name' => $request->business_name ?? ''
                    ]);
                return $this->sendResponse('Success Edit', 'Successfully edit Customer Data');
            } else {
                $CustID = $this->genCustID($request->CustomerId);
                $cust_type = $this->insCustType($request->cust_type);
                $insert = DB::connection('sqlSrvBCKI')
                    ->table('Customer')
                    ->insert([
                        'CustomerId' => $CustID,
                        'SalesId' => $request->SalesId ? $request->SalesId : ' ',
                        'NamaCustomer' => $request->NamaCustomer ? $request->NamaCustomer : ' ',
                        'Alamat' => $request->Alamat ? $request->Alamat : ' ',
                        'Kota' => $request->Kota ? $request->Kota : ' ',
                        'Telepon' => $request->Telepon ? $request->Telepon : ' ',
                        'PerTanggal' => $request->PerTanggal ? $request->PerTanggal : now(), //tanggal
                        'Fax' => $request->Fax ? $request->Fax : ' ',
                        'NPWP' => $request->NPWP ? $request->NPWP : ' ',
                        'Person' => $request->Person ? $request->Person : ' ',
                        'Jabatan' => $request->Jabatan ? $request->Jabatan : ' ',
                        'Real_City' => $request->Real_City ? $request->Real_City : ' ',
                        'Propinsi' => $request->Propinsi ? $request->Propinsi : ' ',
                        'Region' => $request->Region ? $request->Region : ' ',
                        'Banned' => $request->Banned ? $request->Banned : ' ',
                        'payterm' => $request->payterm ? $request->payterm : ' ',
                        'credit_limit' => $request->credit_limit ? $request->credit_limit : ' ',
                        'banned_fee' => $request->banned_fee ? $request->banned_fee : ' ',
                        'cust_group' => $request->cust_group ? $request->cust_group : ' ',
                        'email' => $request->email ? $request->email : ' ',
                        'business_start_date' => $request->business_start_date ? $request->business_start_date : now(), //tanggal mulai usaha
                        'business_status' => $request->business_status ? $request->business_status : ' ',
                        'business_entities' => $request->business_entities ? $request->business_entities : ' ',
                        'kind_of_business' => $request->kind_of_business ? $request->kind_of_business : ' ',
                        'building' => $request->building ? $request->building : ' ',
                        'employee_num' => $request->employee_num ? $request->employee_num : ' ',
                        'tax_status' => $request->tax_status ? $request->tax_status : ' ',
                        'office_tipe' => $request->office_tipe ? $request->office_tipe : ' ',
                        'ownership' => $request->ownership ? $request->ownership : ' ',
                        'known_from' => $request->known_from ? $request->known_from : ' ',
                        'omzet' => $request->omzet ? $request->omzet : ' ',
                        'owner_name' => $request->owner_name ? $request->owner_name : ' ',
                        'date_of_birth' => $request->dob ? $request->dob : now(), //tanggal
                        'city_of_birth' => $request->city_of_birth ? $request->city_of_birth : ' ',
                        'husband_wife' => $request->husband_wife ? $request->husband_wife : ' ',
                        'id_card' => $request->id_card ? $request->id_card : 'KTP', //KTP
                        'id_card_no' => $request->id_card_no ? $request->id_card_no : ' ',
                        'copi_id_card' => $request->copi_id_card ? $request->copi_id_card : ' ',
                        'owner_address' => $request->owner_address ? $request->owner_address : ' ',
                        'owner_province' => $request->owner_province ? $request->owner_province : ' ',
                        'owner_city' => $request->owner_city ? $request->owner_city : ' ',
                        'owner_phone' => $request->owner_phone ? $request->owner_phone : ' ',
                        'owner_hp' => $request->owner_hp ? $request->owner_hp : ' ',
                        'owner_email' => $request->owner_email ? $request->owner_email : ' ',
                        'hari_raya' => $request->hari_raya ? $request->hari_raya : ' ',
                        'bank1' => $request->bank1 ? $request->bank1 : ' ',
                        'bank2' => $request->bank2 ? $request->bank2 : ' ',
                        'bank3' => $request->bank3 ? $request->bank3 : ' ',
                        'bank_atas_nama1' => $request->bank_atas_nama1 ? $request->bank_atas_nama1 : ' ',
                        'bank_atas_nama2' => $request->bank_atas_nama2 ? $request->bank_atas_nama2 : ' ',
                        'bank_atas_nama3' => $request->bank_atas_nama3 ? $request->bank_atas_nama3 : ' ',
                        'payment_tipe1' => $request->payment_tipe1 ? $request->payment_tipe1 : ' ',
                        'payment_tipe2' => $request->payment_tipe2 ? $request->payment_tipe2 : ' ',
                        'payment_tipe3' => $request->payment_tipe3 ? $request->payment_tipe3 : ' ',
                        'alamat_penagihan' => $request->alamat_penagihan ? $request->alamat_penagihan : ' ',
                        'propinsi_penagihan' => $request->propinsi_penagihan ? $request->propinsi_penagihan : ' ',
                        'kota_penagihan' => $request->kota_penagihan ? $request->kota_penagihan : ' ',
                        'tlp_penagihan' => $request->tlp_penagihan ? $request->tlp_penagihan : ' ',
                        'fax_penagihan' => $request->fax_penagihan ? $request->fax_penagihan : ' ',
                        'hari_penagihan' => $request->hari_penagihan ? $request->hari_penagihan : ' ',
                        'character' => $request->character ? $request->character : ' ',
                        'character_desc' => $request->character_desc ? $request->character_desc : ' ',
                        'capacity' => $request->capacity ? $request->capacity : ' ',
                        'capacity_desc' => $request->capacity_desc ? $request->capacity_desc : ' ',
                        'capital' => $request->capital ? $request->capital : ' ',
                        'capital_desc' => $request->capital_desc ? $request->capital_desc : ' ',
                        'collateral' => $request->collateral ? $request->collateral : ' ',
                        'collateral_dessc' => $request->collateral_dessc ? $request->collateral_dessc : ' ',
                        'condition' => $request->condition ? $request->condition : ' ',
                        'condition_desc' => $request->condition_desc ? $request->condition_desc : ' ',
                        'cust_note' => $request->cust_note ? $request->cust_note : ' ',
                        'tr_date' => $request->tr_date ? $request->tr_date : now(), //tanggal
                        'approve1_date' => $request->approve1_date ? $request->approve1_date : now(), //tanggal
                        'approve2_date' => $request->approve2_date ? $request->approve2_date : now(), //tanggal
                        'active_flag' => $request->active_flag ? $request->active_flag : 'Y',
                        'coffice_id' => $request->coffice_id ? $request->coffice_id : ' ',
                        'salesman' => $request->salesman ? $request->salesman : ' ',
                        'affiliate' => $request->affiliate ? $request->affiliate : ' ',
                        'district' => $request->district ? $request->district : ' ',
                        'vilage' => $request->vilage ? $request->vilage : ' ',
                        'cv' => $request->cv ? $request->cv : ' ',
                        'price_role' => $request->price_role ? $request->price_role : ' ',
                        'price_role2' => $request->price_role2 ? $request->price_role2 : ' ',
                        'cv_id' => $request->cv_id ? $request->cv_id : ' ',
                        'no_nib' => $request->no_nib ? $request->no_nib : ' ',
                        'no_skd' => $request->no_skd ? $request->no_skd : ' ',
                        'pic_order' => $request->pic_order ? $request->pic_order : ' ',
                        'pic_order_jabatan' => $request->pic_order_jabatan ? $request->pic_order_jabatan : ' ',
                        'pic_order_telp' => $request->pic_order_telp ? $request->pic_order_telp : ' ',
                        'pic_order_email' => $request->pic_order_email ? $request->pic_order_email : ' ',
                        'pic_tagihan' => $request->pic_tagihan ? $request->pic_tagihan : ' ',
                        'pic_tagihan_jabatan' => $request->pic_tagihan_jabatan ? $request->pic_tagihan_jabatan : ' ',
                        'pic_tagihan_telp' => $request->pic_tagihan_telp ? $request->pic_tagihan_telp : ' ',
                        'pic_tagihan_email' => $request->pic_tagihan_email ? $request->pic_tagihan_email : ' ',
                        'metode_bayar' => $request->metode_bayar ? $request->metode_bayar : ' ',
                        'dt_modified' => now(),
                        'skbdn' => $request->skbdn ? $request->skbdn : ' ',
                        'cust_type' => $cust_type,
                        'business_name' => $request->business_name ?? ''
                    ]);
                return $this->sendResponse('Success Insert', 'Successfully insert Customer Data');
            }
        } catch (\Exception $e) {
            // DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/bcki/customer/office",
     *     tags={"Master Customer"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *              @OA\Property(property="office_id", type="string", example="ABC"),
     *              @OA\Property(property="region", type="string", example="ABC"), 
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all office",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */

    public function office(Request $request)
    {
        $where = "1=1";

        if ($request->office_id != null) {
            $office_id = $request->office_id;
        } else {
            $office_id = '';
        }

        if ($request->region != null) {
            $region = $request->region;
        } else {
            $region = '';
        }


        if (!empty($office_id)) {
            $where = $where . "and office_id = " . "'" . $office_id . "'";
        }

        if (!empty($region)) {
            $where = $where . "and region = " . "'" . $region . "'";
        }

        $result = DB::connection('sqlSrvBCKI')
            ->table('branch_office')
            ->selectRaw('office_id, 
                TRIM(office) as office, 
                TRIM(address) as address, 
                TRIM(city) as city, 
                TRIM(region) as region, 
                TRIM(tlp) as tlp,
                TRIM(e_mail) as e_mail,
                TRIM(zip_code) as zip_code, active_flag')
            ->whereRaw($where)
            ->where('active_flag', 'Y')
            ->orderBy('office', 'asc')
            ->get();

        return $this->sendResponse($result, 'Successfully get Branch Office');
    }

    /**
     * @OA\Post(
     *     path="/api/bcki/customer/getDocument",
     *     tags={"Master Customer"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *              @OA\Property(property="cust_id", type="string", example="ABC"),
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all document",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */

    public function getDocument(Request $request)
    {
        $custId = $request->cust_id;

        try {
            $result = DB::connection('sqlSrvBCKI')
                ->table('Customer_doc')
                ->selectRaw('
                    doc_id,
                    doc_name,
                    loc_file,
                    n_file
                ')
                ->where('CustomerId', $custId)
                ->orderBy('doc_id')
                ->get()
                ->map(function ($item) {
                    $item->doc_file = url("storage/$item->loc_file/{$item->n_file}");
                    return $item;
                });

            return $this->sendResponse($result, 'Successfully get documents');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function storeDocument(Request $request)
    {
        $docId = $request->doc_id ?? '';
        $custId = $request->cust_id ?? '';
        $file = $request->file('doc_file');
        $path = 'fpis/customer_doc';

        $extension = $file->getClientOriginalExtension();
        $originalName = $file->getClientOriginalName();
        $fileName = Str::random(40) . '.' . $extension;

        // -- save file
        $file->storeAs($path, $fileName, 'public');

        $db = DB::connection('sqlSrvBCKI')
            ->table('Customer_doc');

        try {
            if ($docId) {
                $dataDoc = $db->where('doc_id', $docId)->first();

                if (!$dataDoc) {
                    return $this->sendError('Documents not found', 'Error', 500);
                }

                // -- delete file
                if (Storage::disk('public')->exists("$path/{$dataDoc->n_file}")) {
                    Storage::disk('public')->delete("$path/{$dataDoc->n_file}");
                }

                $db->where('doc_id', $docId)
                    ->update([
                        'doc_name' => $originalName,
                        'n_file' => $fileName,
                        'dt_modified' => now(),
                        'user_id' => getAuth($request)->user_id2,
                    ]);
            } else {
                $genId = $this->genNoDoc();

                $db->insert([
                    'doc_id' => $genId,
                    'doc_name' => $originalName,
                    'n_file' => $fileName,
                    'dt_modified' => now(),
                    'user_id' => getAuth($request)->user_id2,
                    'loc_file' => $path,
                    'CustomerId' => $custId,
                ]);
            }

            return $this->sendResponse('Success', 'Successfully save document');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }


    public function setActiveCust(Request $request)
    {
        $custId = $request->cust_id;
        $activeFlag = $request->active_flag;

        try {
            DB::connection('sqlSrvBCKI')
                ->table('Customer')
                ->where('CustomerId', $custId)
                ->update([
                    'active_flag' => $activeFlag,
                    'dt_modified' => now()
                ]);

            return $this->sendResponse('Success', 'Successfully set Active Flag');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getProvince(Request $request)
    {
        try {
            $datas = DB::connection("sqlSrvBCKI")
                ->table('province_list')
                ->selectRaw("
                    province_list.province_code as id_province, 
                    province_list.province_code as kemendagri_province_code, 
                    province_list.province
                ")
                ->get();

            return $this->sendResponse($datas, 'Successfully get Province');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getCity(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $id_province = $request->id_province;

            $sqlWhere = $sqlWhere . "and city_list.province_code = " . "'" . $id_province . "'";

            $datas = DB::connection("sqlSrvBCKI")
                ->table('city_list')
                ->selectRaw("
                    city_list.city_code as id_city,
                    city_list.city_code as id_prov_city, 
                    city_list.city as city_name, 
                    city_list.province_code as id_province,
                    city_list.province_code kemendagri_province_code
                ")
                ->whereRaw($sqlWhere)
                ->get();
            return $this->sendResponse($datas, 'Successfully get City');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getDistrict(Request $request)
    {
        $province_code = $request->province_code;
        $city_code = $request->city_code;
        $sqlWhere = "1=1";

        if (!empty($province_code)) {
            $sqlWhere = $sqlWhere . "and province_code = " . "'" . $province_code . "'";
        }

        if (!empty($city_code)) {
            $sqlWhere = $sqlWhere . "and city_code = " . "'" . $city_code . "'";
        }

        $result = DB::connection('sqlSrvBCKI')
            ->table('district_list')
            ->selectRaw("TRIM(district_code) as district_code, district, city_code, province_code")
            ->whereRaw($sqlWhere)
            ->orderBy('district', 'asc')
            ->get();

        return $this->sendResponse($result, 'Successfully get district');
    }

    public function getVillage(Request $request)
    {
        $province_code = $request->province_code;
        $city_code = $request->city_code;
        $district_code = $request->district_code;

        $sqlWhere = "1=1";

        if (!empty($province_code)) {
            $sqlWhere = $sqlWhere . "and province_code = " . "'" . $province_code . "'";
        }

        if (!empty($city_code)) {
            $sqlWhere = $sqlWhere . "and city_code = " . "'" . $city_code . "'";
        }

        if (!empty($district_code)) {
            $sqlWhere = $sqlWhere . "and district_code = " . "'" . $district_code . "'";
        }

        $result = DB::connection('sqlSrvBCKI')
            ->table('village_list')
            ->selectRaw("TRIM(village_code) as village_code, village, district_code, city_code, province_code")
            ->whereRaw($sqlWhere)
            ->orderBy('village', 'asc')
            ->get();

        return $this->sendResponse($result, 'Successfully get village');
    }

    public function getListNpwp(Request $request)
    {
        try {
            $customer_id = $request->customerId;

            if (!$customer_id) return $this->sendError('Customer id not found', 'Error', 500);

            $datas = DB::connection('sqlSrvBCKI')
                ->table('customer_nik_npwp as a')
                ->select(
                    'a.nik_npwp',
                    'a.customerId',
                    'a.CustomerName',
                    'a.address1',
                    'a.address2',
                    DB::raw("
                        CASE 
                            WHEN a.tr_type = 'K' THEN 'NIK' 
                            WHEN a.tr_type = 'P' THEN 'NPWP' 
                        END as tr_type_name
                    "),
                    'a.tr_type',
                    'd.province as province_name',
                    'c.city as city_name',
                    'e.district as district_name',
                    'f.village as village_name',
                    'a.province as province',
                    'a.city as city',
                    'a.district as district',
                    'a.village as village',
                )
                // ->leftJoin('customer_npwp as b', 'a.customerid', '=', 'b.customerid')
                ->leftJoin('city_list as c', 'c.city_code', '=', 'a.city')
                ->leftJoin('province_list as d', 'd.province_code', '=', 'a.province')
                ->leftJoin('district_list as e', 'e.district_code', '=', 'a.district')
                ->leftJoin('village_list as f', 'f.village_code', '=', 'a.village')
                ->where('a.customerid', '=', $customer_id)
                ->where('a.active_flag', '=', 'Y')
                ->get();

            return $this->sendResponse($datas, 'Successfully get npwp');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function storeNpwp(Request $request)
    {
        try {
            $customer_id = $request->customer_id;
            $nik_npwp = $request->nik_npwp;
            $nik_npwp_old = $request->nik_npwp_old;
            $tr_type = $request->tr_type;
            $customer_name = $request->customer_name;
            $address1 = $request->address1;
            $address2 = $request->address2;
            $province = $request->province;
            $city = $request->city;
            $district = $request->district;
            $village = $request->village;


            $cekUpdate = DB::connection('sqlSrvBCKI')
                ->table('customer_nik_npwp')
                ->where('customerid', $customer_id)
                ->where('nik_npwp', $nik_npwp_old)
                ->first();

            if ($cekUpdate) {

                if ($nik_npwp_old != $nik_npwp) {
                    $resDelt = DB::connection('sqlSrvBCKI')
                        ->table('customer_nik_npwp')
                        ->where('nik_npwp', $nik_npwp_old)
                        ->where('customerid', $customer_id)
                        ->delete();

                    if ($resDelt) {
                        $response = DB::connection('sqlSrvBCKI')
                            ->table('customer_nik_npwp')
                            ->insert([
                                'customerid' => $customer_id,
                                'nik_npwp' => $nik_npwp,
                                'tr_type' => $tr_type,
                                'CustomerName' => $customer_name,
                                'address1' => $address1,
                                'address2' => $address2,
                                'province' => $province,
                                'city' => $city,
                                'district' => $district,
                                'village' => $village,
                                'tr_id' => $nik_npwp,
                                'userID' => getAuth($request)->user_id2,
                                'dt_modified' => now(),
                                'active_flag' => 'Y',
                            ]);
                    } else {
                        return $this->sendError('Failed update', 'Error', 500);
                    }
                } else {
                    $response = DB::connection('sqlSrvBCKI')
                        ->table('customer_nik_npwp')
                        ->where('nik_npwp', $nik_npwp)
                        ->where('customerid', $customer_id)
                        ->update([
                            'customerid' => $customer_id,
                            'nik_npwp' => $nik_npwp,
                            'tr_type' => $tr_type,
                            'CustomerName' => $customer_name,
                            'address1' => $address1,
                            'address2' => $address2,
                            'province' => $province,
                            'city' => $city,
                            'district' => $district,
                            'village' => $village,
                            'tr_id' => $nik_npwp,
                            'userID' => getAuth($request)->user_id2,
                            'dt_modified' => now(),
                            'active_flag' => 'Y',
                        ]);
                }

                return $this->sendResponse($response, 'Successfully Update npwp');
            } else {
                $response = DB::connection('sqlSrvBCKI')
                    ->table('customer_nik_npwp')
                    ->insert([
                        'customerid' => $customer_id,
                        'nik_npwp' => $nik_npwp,
                        'tr_type' => $tr_type,
                        'CustomerName' => $customer_name,
                        'address1' => $address1,
                        'address2' => $address2,
                        'province' => $province,
                        'city' => $city,
                        'district' => $district,
                        'village' => $village,
                        'tr_id' => $nik_npwp,
                        'userID' => getAuth($request)->user_id2,
                        'dt_modified' => now(),
                        'active_flag' => 'Y',
                    ]);

                if ($response) {
                    DB::connection('sqlSrvBCKI')
                        ->table('customer_npwp')
                        ->insert([
                            'customer_nik_npwp' => $nik_npwp,
                            'customerid' => $customer_id,
                            'active_flag' => 'Y',
                        ]);
                }

                return $this->sendResponse($response, 'Successfully save npwp');
            }
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getListNitku(Request $request)
    {
        try {
            $npwp = $request->npwp;

            if (!$npwp) return $this->sendError('npwp not found', 'Error', 500);

            $datas = DB::connection('sqlSrvBCKI')
                ->table('customer_nitku')
                ->where('npwp', '=', $npwp)
                ->where('active_flag', '=', 'Y')
                ->get();

            return $this->sendResponse($datas, 'Successfully get nitku');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function storeNitku(Request $request)
    {
        try {
            $npwp = $request->npwp;
            $nitku = $request->nitku;
            $nitku_old = $request->nitku_old;
            $descr = $request->descr;


            $cekUpdate = DB::connection('sqlSrvBCKI')
                ->table('customer_nitku')
                ->where('npwp', $npwp)
                ->where('nitku', $nitku_old)
                ->first();

            if ($cekUpdate) {

                if ($nitku_old != $nitku) {
                    $resDelt = DB::connection('sqlSrvBCKI')
                        ->table('customer_nitku')
                        ->where('nitku', $nitku_old)
                        ->where('npwp', $npwp)
                        ->delete();

                    if ($resDelt) {
                        $response = DB::connection('sqlSrvBCKI')
                            ->table('customer_nitku')
                            ->insert([
                                'npwp' => $npwp,
                                'nitku' => $nitku,
                                'descr' => $descr,
                                'user_id' => getAuth($request)->user_id2,
                                'dt_modified' => now(),
                                'active_flag' => 'Y',
                            ]);
                    } else {
                        return $this->sendError('Failed update', 'Error', 500);
                    }
                } else {
                    $response = DB::connection('sqlSrvBCKI')
                        ->table('customer_nitku')
                        ->where('nitku', $nitku)
                        ->where('npwp', $npwp)
                        ->update([
                            'npwp' => $npwp,
                            'nitku' => $nitku,
                            'descr' => $descr,
                            'user_id' => getAuth($request)->user_id2,
                            'dt_modified' => now(),
                            'active_flag' => 'Y',
                        ]);
                }

                return $this->sendResponse($response, 'Successfully Update NITKU');
            } else {
                $response = DB::connection('sqlSrvBCKI')
                    ->table('customer_nitku')
                    ->insert([
                        'npwp' => $npwp,
                        'nitku' => $nitku,
                        'descr' => $descr,
                        'user_id' => getAuth($request)->user_id2,
                        'dt_modified' => now(),
                        'active_flag' => 'Y',
                    ]);

                return $this->sendResponse($response, 'Successfully save NITKU');
            }
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function setActiveFlagNitku(Request $request)
    {
        try {
            $nitku = $request->nitku;
            $active_flag = $request->active_flag;

            $response = DB::connection('sqlSrvBCKI')
                ->table('customer_nitku')
                ->where('nitku', $nitku)
                ->update([
                    'active_flag' => $active_flag,
                ]);

            return $this->sendResponse($response, 'Successfully change active flag');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }


    private function genNoDoc()
    {
        $yearPrefix = date('y');

        $latestDocId = DB::connection('sqlSrvBCKI')
            ->table('Customer_doc')
            ->whereRaw("LEFT(CAST(doc_id AS VARCHAR), 2) = ?", [$yearPrefix])
            ->orderByDesc('doc_id')
            ->value('doc_id');

        if ($latestDocId) {
            // -- ambil bagian urutan (5 digit terakhir)
            $lastNumber = (int) substr($latestDocId, 2);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $yearPrefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
