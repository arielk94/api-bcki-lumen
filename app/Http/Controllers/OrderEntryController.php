<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Str;
use Image, Storage;

class OrderEntryController extends Controller
{
    public function getData(Request $request){

        $sqlWhere = "1=1";
        $CustomerId = $request->CustomerId;
        $SalesId = $request->SalesId;
        $id_site = $request->id_site;
        $pay_term_id = $request->pay_term_id;
        $order_id = $request->order_id;
        $status = $request->status;
        
        if($status){
            $sqlWhere.= " and CustomerOrder.status = '$status'";
        }
        if($order_id){
            $sqlWhere.= " and CustomerOrder.order_id = '$order_id'";
        }
        if($CustomerId){
            $sqlWhere.= " and CustomerOrder.CustomerId = '$CustomerId'";
        }
        if($SalesId){
            $sqlWhere.= " and CustomerOrder.SalesId = '$SalesId'";
        }
        if($id_site){
            $sqlWhere.= " and CustomerOrder.id_site = '$id_site'";
        }
        if($pay_term_id){
            $sqlWhere.= " and CustomerOrder.pay_method = '$pay_term_id'";
        }

        $start_date = $request->start_date;
        if(!$start_date)
        {
            $start_date = '';
        }

        $end_date = $request->end_date;
        if(!$end_date)
        {
            $end_date = '';
        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and CustomerOrder.order_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and CustomerOrder.order_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
            }
        }

        $result = DB::connection('sqlSrvFPI')
            ->table('CustomerOrder')
            ->selectraw('CustomerOrder.order_id,
                    CustomerOrder.office_id,
                    CustomerOrder.CustomerId,
                    CustomerOrder.SalesId,
                    CustomerOrder.id_site,
                    CustomerOrder.order_date,
                    ISNULL(CustomerOrderRevision.ord_amt, CustomerOrder.ord_amt) AS ord_amt,
                    ISNULL(CustomerOrderRevision.utj, CustomerOrder.utj) AS utj,
                    CustomerOrder.dp_amt,
                    CustomerOrder.pay_method,
                    CustomerOrder.remark,
                    CustomerOrder.userid,
                    CustomerOrder.dt_modified,
                    CustomerOrder.book_id,
                    CustomerOrder.dt_cancel,
                    CustomerOrder.cancel_by,
                    CustomerOrder.status,
                    CustomerOrder.no_kwitansi,
                    CustomerOrder.plafon_naik,
                    CustomerOrder.dt_aprv1,
                    CustomerOrder.aprv1_by,
                    CustomerOrder.dt_reject,
                    CustomerOrder.reject_by,
                    CustomerOrder.bonus_amt,
                    CustomerOrder.channel_id,
                    Customer.NamaCustomer, Sales.NamaSales, site.site_name,
                    trim(site_cluster.cluster_name) as cluster_name,
                    trim(house_unit.house_name) as house_name,
                    trim(house_type.common_name) as common_name,
                    trim(house_type.sbk_name) as sbk_name')
            ->leftjoin('Customer', 'CustomerOrder.CustomerId', '=', 'Customer.CustomerId')
            ->leftjoin('Sales', 'CustomerOrder.SalesId', '=', 'Sales.SalesId')
            ->leftjoin('site', 'CustomerOrder.id_site', '=', 'site.id_site')
            ->leftjoin('CustomerOrderDetil', 'CustomerOrder.order_id', '=', 'CustomerOrderDetil.order_id')
            ->leftJoin(DB::raw("(
                    SELECT TOP 1 
                        ord_amt, 
                        utj, 
                        tr_id, 
                        order_id
                    FROM CustomerOrderRevision
                    WHERE tr_type = 'REV' 
                    AND active_flag = 'Y'
                    ORDER BY tr_id DESC
                ) AS CustomerOrderRevision"),
                 'CustomerOrder.order_id', '=', 'CustomerOrderRevision.order_id')
            ->leftjoin('site_cluster', 'CustomerOrderDetil.id_cluster', '=', 'site_cluster.id_cluster')
            ->leftjoin('house_unit', 'CustomerOrderDetil.id_house', '=', 'house_unit.id_house')
            ->leftjoin('house_type', 'house_unit.id_house_type', '=', 'house_type.id_house_type')
            ->whereRaw($sqlWhere)
            ->orderByRaw('CAST(RIGHT(CustomerOrder.order_id, 4) AS INT) DESC')
            ->get();
        
        return $this->sendResponse($result, 'Successfully get data Customer Order');
    }

    public function getDetail(Request $request){
        $sqlWhere = "1=1";
        $order_id = $request->order_id;
        $item_num = $request->item_num;
        $article_id = $request->article_id;
        $id_cluster = $request->id_cluster;
        $id_house = $request->id_house;
        if($order_id){
            $sqlWhere.= " and CustomerOrderDetil.order_id = '$order_id'";
        }
        if($item_num){
            $sqlWhere.= " and CustomerOrderDetil.item_num = '$item_num'";
        }
        if($article_id){
            $sqlWhere.= " and CustomerOrderDetil.article_id = '$article_id'";
        }
        if($id_cluster){
            $sqlWhere.= " and CustomerOrderDetil.id_cluster = '$id_cluster'";
        }
        if($id_house){
            $sqlWhere.= " and CustomerOrderDetil.id_house = '$id_house'";
        }
        $result = DB::connection('sqlSrvFPI')
            ->table('CustomerOrderDetil')
            ->selectraw('
            trim(CustomerOrderDetil.order_id) as order_id,
            CustomerOrderDetil.item_num,
            trim(CustomerOrderDetil.article_id) as article_id,
            trim(CustomerOrderDetil.id_cluster) as id_cluster,
            trim(CustomerOrderDetil.id_house) as id_house,
            CustomerOrderDetil.qty,
            isnull(CustomerOrderRevision.unit_price,CustomerOrderDetil.unit_price) as unit_price,
            isnull(CustomerOrderRevision.disc_unit,CustomerOrderDetil.disc_unit) as disc_unit,
            trim(CustomerOrderDetil.stat) as stat,
            trim(CustomerOrderDetil.remark) as remark,
            trim(CustomerOrderDetil.userid) as userid,
            CustomerOrderDetil.dt_modified,
            CustomerOrderDetil.excess_value,
            CustomerOrderDetil.excess_area,
            trim(article.description) as description,
            trim(site_cluster.cluster_name) as cluster_name,
            trim(house_unit.house_name) as house_name,
            trim(house_type.common_name) as common_name,
            trim(house_type.sbk_name) as sbk_name
            ')
            ->leftjoin('article', 'CustomerOrderDetil.article_id', '=', 'article.article_id')
            ->leftJoin(DB::raw("(
                    SELECT TOP 1 
                        unit_price, 
                        disc_unit, 
                        tr_id, 
                        order_id
                    FROM CustomerOrderRevision
                    WHERE tr_type = 'REV' 
                    AND active_flag = 'Y'
                    ORDER BY tr_id DESC
                ) AS CustomerOrderRevision"),
                 'CustomerOrderDetil.order_id', '=', 'CustomerOrderRevision.order_id')
            ->leftjoin('site_cluster', 'CustomerOrderDetil.id_cluster', '=', 'site_cluster.id_cluster')
            ->leftjoin('house_unit', 'CustomerOrderDetil.id_house', '=', 'house_unit.id_house')
            ->leftjoin('house_type', 'house_unit.id_house_type', '=', 'house_type.id_house_type')
            ->whereRaw($sqlWhere)
            ->get();
        
        return $this->sendResponse($result, 'Successfully get Customer Order Detail');
    }

    public function getDetailAddOn(Request $request){
        $order_id = $request->order_id;
        // $item_num = $request->item_num;

        //included price
        $result1 = DB::connection('sqlSrvFPI')
            ->table('CustomerOrderAddonUnit')
            ->selectraw('
                trim(CustomerOrderAddonUnit.order_id) as order_id,
                CustomerOrderAddonUnit.item_num,
                CustomerOrderAddonUnit.tr_seq as item_id,
                trim(CustomerOrderAddonUnit.article_id) as article_id,
                CustomerOrderAddonUnit.qty,
                trim(CustomerOrderAddonUnit.remark) as remark,
                trim(CustomerOrderAddonUnit.userid) as userid,
                CustomerOrderAddonUnit.dt_modified,
                trim(article.description) as barang_id
            ')
            ->leftjoin('article', 'CustomerOrderAddonUnit.article_id', '=', 'article.article_id')
            ->where('CustomerOrderAddonUnit.order_id', $order_id)
            ->where('article.cat_id', '6')
            ->get();
        
        //bonus
        $result2 = DB::connection('sqlSrvFPI')
            ->table('CustomerOrderAddonUnit')
            ->selectraw('
                trim(CustomerOrderAddonUnit.order_id) as order_id,
                CustomerOrderAddonUnit.item_num,
                CustomerOrderAddonUnit.tr_seq as item_id,
                trim(CustomerOrderAddonUnit.article_id) as article_id,
                CustomerOrderAddonUnit.qty,
                trim(CustomerOrderAddonUnit.remark) as remark,
                trim(CustomerOrderAddonUnit.userid) as userid,
                CustomerOrderAddonUnit.dt_modified,
                trim(article.description) as barang_id
            ')
            ->leftjoin('article', 'CustomerOrderAddonUnit.article_id', '=', 'article.article_id')
            ->where('CustomerOrderAddonUnit.order_id', $order_id)
            ->where('article.cat_id', '!=' ,'6')
            ->get();
            

        $response = [
            'data1' => $result1,
            'data2' => $result2
        ];

        return $this->sendResponse($response, 'Successfully get Customer Order Addon Unit Detail');
    }

    //insert customer order
    public function saveCustomerOrder(Request $request){
        try{
            $book_id = $request->book_id;
            $idproyek = DB::connection('sqlSrvFPI')
                    ->table('site')
                    ->selectRaw('proyek_id')
                    ->where('id_site', $request->id_site)
                    ->first();

            // $order_id = $this->genOrderId($request->order_date,$idproyek);
            // $order_id = $request->order_id ?? $this->genOrderId($request->order_date, $idproyek);

            $order_id = !empty($request->order_id) ? $request->order_id : $this->genOrderId($request->order_date, $idproyek);

            // dd($order_id);
            // Cek apakah order_id sudah ada
            $existingOrder = DB::connection('sqlSrvFPI')
            ->table('CustomerOrder')
            ->where('order_id', $order_id)
            ->first();

            // Ambil book_id lama jika ada
            $book_id_old = $existingOrder->book_id ?? null;

            $data = [
                'order_id' => $order_id,
                'office_id' => $request->office_id ? $request->office_id : '',
                'CustomerId' => $request->customer_id ? $request->customer_id : '',
                'SalesId' => $request->sales_id ? $request->sales_id : '',
                'id_site' => $request->id_site ? $request->id_site : '',
                'order_date' => $request->order_date ? $request->order_date : '',
                'ord_amt' => $request->total_amount ? $request->total_amount : 0,
                'utj' => $request->utj ? $request->utj : 0,
                'dp_amt' => $request->dp_amt ? $request->dp_amt : 0,
                'pay_method' => $request->pay_method ? $request->pay_method : '',
                'remark' => $request->remark ? $request->remark : '',
                'userid' => getAuth($request)->user_id2,
                'dt_modified' => Carbon::now('Asia/Jakarta'),
                'book_id' => $book_id ? $book_id : '',
                'bonus_amt' => $request->bonus_amt ? $request->bonus_amt : 0,
                'channel_id' => $request->channel_id ? $request->channel_id : '',
                'status' => 'O'
            ];

            if ($existingOrder) {
                // Update jika sudah ada
                DB::connection('sqlSrvFPI')
                    ->table('CustomerOrder')
                    ->where('order_id', $order_id)
                    ->update($data);

                //return $this->sendResponse($order_id, 'Successfully updated Customer Order');
            } else {
                // Insert jika belum ada
                DB::connection('sqlSrvFPI')
                    ->table('CustomerOrder')
                    ->insert($data);

                //return $this->sendResponse($order_id, 'Successfully inserted Customer Order');
            }

            // Cek jika ada perubahan book_id
            if (!empty($book_id)) {
                if ($book_id_old && $book_id_old !== $book_id) {
                    // Kosongkan order_id pada book_id lama
                    DB::connection('sqlSrvFPI')
                        ->table('CustomerBooking')
                        ->where('book_id', $book_id_old)
                        ->update([
                            'order_id' => '',
                        ]);
                }

                // Set order_id pada book_id baru
                DB::connection('sqlSrvFPI')
                    ->table('CustomerBooking')
                    ->where('book_id', $book_id)
                    ->update([
                        'order_id' => $order_id,
                    ]);
            }    
            
            // DB::connection('sqlSrvFPI')
            //     ->table('payment_progress')
            //     ->insert([
            //         'order_id' => $order_id,
            //     ]);

            return $this->sendResponse($order_id, 'Successfully saved Customer Order');
        }
        catch (\Exception $e) {
            // DB::connection('sqlSrvKencanaStore')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function saveCustomerOrderDetil(Request $request)
    {
        try {
            // Ambil nilai item_num dari request (bisa null)
            $item_num = $request->item_num ? $request->item_num : 1;

            // Siapkan data umum
            $data = [
                'article_id' => $request->article_id ? $request->article_id : '',
                'id_cluster' => $request->id_cluster ? $request->id_cluster : '',
                'id_house' => $request->id_house ? $request->id_house : '',
                'qty' => $request->qty ? $request->qty : 0,
                'unit_price' => $request->unit_price ? $request->unit_price : 0,
                'disc_unit' => $request->disc_unit ? $request->disc_unit : 0,
                'stat' => $request->stat ? $request->stat : '',
                'remark' => $request->remark ? $request->remark : '',
                'excess_value' => $request->excess_value ? $request->excess_value : '',
                'excess_area' => $request->excess_area ? $request->excess_area : '',
                'userid' => getAuth($request)->user_id2,
                'dt_modified' => Carbon::now('Asia/Jakarta')
            ];

            // if ($item_num) {
                    // 🔄 Ambil data lama
                $existing = DB::connection('sqlSrvFPI')
                ->table('CustomerOrderDetil')
                ->where('order_id', $request->order_id)
                ->where('item_num', $item_num)
                ->first();

                if ($existing) {
                    $old_id_house = $existing->id_house;
                    $old_id_cluster = $existing->id_cluster;

                    $new_id_house = $request->id_house;
                    $new_id_cluster = $request->id_cluster;

                    // Kalau berubah, update status rumah lama ke 'V'
                    if ($old_id_house !== $new_id_house || $old_id_cluster !== $new_id_cluster) {
                        DB::connection('sqlSrvFPI')
                            ->table('house_unit')
                            ->where('id_house', $old_id_house)
                            ->where('id_cluster', $old_id_cluster)
                            ->update(['sold_stat' => 'V']);
                    }
                // }

                // 🔄 Jika item_num dikirim → update data
                DB::connection('sqlSrvFPI')
                    ->table('CustomerOrderDetil')
                    ->where('order_id', $request->order_id)
                    ->where('item_num', $item_num)
                    ->update($data);

            } else {
                // ➕ Jika item_num tidak dikirim → insert dengan auto nomor
                // $lastItem = DB::connection('sqlSrvFPI')
                //     ->table('CustomerOrderDetil')
                //     ->where('order_id', $request->order_id)
                //     // ->orderByDesc('item_num')
                //     ->value('item_num');

                // $new_item_num = $lastItem ? $lastItem + 1 : 1;

                // Tambahkan order_id dan item_num ke data untuk insert
                $data['order_id'] = $request->order_id;
                $data['item_num'] = $item_num;

                DB::connection('sqlSrvFPI')
                    ->table('CustomerOrderDetil')
                    ->insert($data);                
            }

            // ✅ Update rumah baru jadi 'P'
            DB::connection('sqlSrvFPI')
                ->table('house_unit')
                ->where('id_house', $request->id_house)
                ->where('id_cluster', $request->id_cluster)
                ->update(['sold_stat' => 'P']);

            //update di tabel kwitansi
            DB::connection('sqlSrvFPI')
                ->table('kwitansi')
                ->where('tr_id', $request->no_kwitansi)
                ->update([
                    'doc_id' => $request->order_id
                ]);
            
            //update di tabel CustomerOrder
            DB::connection('sqlSrvFPI')
                ->table('CustomerOrder')
                ->where('order_id', $request->order_id)
                ->update([
                    'no_kwitansi' => $request->no_kwitansi
                ]);
            
            // 👉 Ambil order_date untuk kebutuhan QC insert
            $order = DB::connection('sqlSrvFPI')
                ->table('CustomerOrder')
                ->select('order_date')
                ->where('order_id', $request->order_id)
                ->first();
            
            if($order){
            //insert data ke tabel qc_trans_hdr dan qc_trans_dtl
            // $qctransid = $this->genQCTransId(Carbon::now());
            // Cari qc_trans_id yang sudah ada berdasarkan rumah dan dokumen SPR
            $existingTrans = DB::connection('sqlSrvFPI')
                ->table('qc_trans_hdr')
                ->where('id_house', $request->id_house)
                ->where('doc_id', $request->order_id)
                ->where('doc_type', 'SPR')
                ->orderByDesc('dt_created')
                ->first();

            $qctransid = $existingTrans ? $existingTrans->qc_trans_id : $this->genQCTransId(Carbon::now('Asia/Jakarta'));

            $id_qc_forms =  DB::connection('sqlSrvFPI')
                ->table('qc_form')
                ->selectRaw("LTRIM(RTRIM(id_qc_form)) as id_qc_form, LTRIM(RTRIM(qc_form)) as qc_form")
                ->where('qc_type', 'BP')
                ->where('active_flag', 'Y')
                ->get();

            $id_qc_items = DB::connection('sqlSrvFPI')
                ->table('qc_item')
                ->selectRaw("LTRIM(RTRIM(id_qc_item)) as id_qc_item, LTRIM(RTRIM(qc_item)) as qc_item, LTRIM(RTRIM(id_qc_form)) as id_qc_form")
                ->where('id_qc_form', 'F250505002')
                ->get();

            foreach ($id_qc_forms as $form) {
                 // Cek apakah qc_trans_hdr sudah ada
                // $existingHdr = DB::connection('sqlSrvFPI')
                //     ->table('qc_trans_hdr')
                //     // ->where('qc_trans_id', $qctransid)
                //     ->where('id_qc_form', $form->id_qc_form)
                //     ->where('id_house', $request->id_house)
                //     ->whereDate('dt_created', Carbon::now()->toDateString())
                //     ->first();

                $existingHdr = DB::connection('sqlSrvFPI')
                ->table('qc_trans_hdr')
                ->where('id_qc_form', $form->id_qc_form)
                ->where('id_house', $request->id_house)
                ->where('doc_id', $request->order_id)
                ->where('doc_type', 'SPR')
                ->first();

                $dataHdr = [
                    // 'qc_trans_id' => $qctransid,
                    // 'id_qc_form' => $form->id_qc_form,
                    // 'id_house' => $request->id_house,
                    'dt_modified' => Carbon::now('Asia/Jakarta'),
                    'modified_by' => getAuth($request)->user_id2,
                    'doc_type' => 'SPR',
                    'doc_id' => $request->order_id,
                ];

                if ($existingHdr) {
                    // 🔄 Update
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_hdr')
                        // ->where('qc_trans_id', $qctransid)
                        ->where('qc_trans_id', $existingHdr->qc_trans_id)
                        ->where('id_qc_form', $form->id_qc_form)
                        ->where('id_house', $request->id_house)
                        // ->whereDate('dt_created', Carbon::now()->toDateString())
                        ->update($dataHdr);
                } else {
                    // ➕ Insert
                    $dataHdr = array_merge($dataHdr, [
                        'qc_trans_id' => $qctransid,
                        'id_qc_form' => $form->id_qc_form,
                        'id_house' => $request->id_house,
                        'dt_created' => Carbon::now('Asia/Jakarta'),
                        'created_by' => getAuth($request)->user_id2,
                    ]);

                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_hdr')
                        ->insert($dataHdr);
                }
            }
            
            foreach ($id_qc_items as $item) {
                // Cek apakah detail sudah ada untuk id_item & qc_trans_id pada tanggal yang sama
                $existingDtl = DB::connection('sqlSrvFPI')
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qctransid)
                    ->where('id_qc_item', $item->id_qc_item)
                    // ->whereDate('dt_created', Carbon::now()->toDateString())
                    ->first();

                // $dataDtl = [
                //     'qc_trans_id' => $qctransid,
                //     'id_qc_item' => $item->id_qc_item,
                //     'stat' => 'O',
                    // 'dt_modified' => Carbon::now(),
                //     'created_by' => getAuth($request)->user_id2,
                //     'aprv_by' => '',
                //     'aprv2_by' => '',
                //     'remark' => '',
                    // 'img_link' => '',
                    // 'stat_closing' => 'N',
                    // 'stat_closing2' => 'N',
                // ];

                // $existingDtl = DB::connection('sqlSrvFPI')
                //     ->table('qc_trans_dtl')
                //     ->where('qc_trans_id', $qctransid)
                //     ->where('id_qc_item', $item->id_qc_item)
                //     ->first();

                // if ($existingDtl) {
                //     // 🔄 Update
                //     DB::connection('sqlSrvFPI')
                //         ->table('qc_trans_dtl')
                //         ->where('qc_trans_id', $qctransid)
                //         ->where('id_qc_item', $item->id_qc_item)
                //         ->whereDate('dt_created', Carbon::now()->toDateString())
                //         ->update($dataDtl);
                // } else {
                //     // ➕ Insert
                //     $dataDtl['dt_created'] = Carbon::now();

                //     DB::connection('sqlSrvFPI')
                //         ->table('qc_trans_dtl')
                //         ->insert($dataDtl);
                // }
                if (!$existingDtl) {
                    // ➕ Insert only
                    $dataDtl = [
                        'qc_trans_id' => $qctransid,
                        'id_qc_item' => $item->id_qc_item,
                        'stat' => 'O',
                        'created_by' => getAuth($request)->user_id2,
                        'dt_created' => Carbon::now('Asia/Jakarta'),
                        'aprv_by' => '',
                        'aprv2_by' => '',
                        'remark' => '',
                        'img_link' => '',
                        'stat_closing' => 'N',
                        'stat_closing2' => 'N',
                    ];

                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_dtl')
                        ->insert($dataDtl);
                }
            }
        }
            return $this->sendResponse('Success', 'Successfully inserted or updated Customer Order Detil');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function saveCustomerOrderAddonUnit(Request $request){ //untuk bonus
        try {
            $order_id = $request->order_id;
            $item_num = $request->item_num;
            $items = $request->items;

            $conn = DB::connection('sqlSrvFPI');
            $table = 'CustomerorderAddonUnit';

            // DELETE dengan INNER JOIN berdasarkan kriteria tertentu
            DB::connection('sqlSrvFPI')->statement("
            DELETE CAU
            FROM CustomerOrderAddonUnit CAU
            INNER JOIN article A ON CAU.article_id = A.article_id
            WHERE A.cat_id != '6' and CAU.order_id = ? AND CAU.item_num = ?
            ", [$order_id, $item_num]);

            // Cek nilai terakhir tr_seq yang tersisa (dari cat_id = 6, karena cat_id != 6 sudah dihapus)
            $lastTrSeq = $conn->table($table)
            ->where('order_id', $order_id)
            ->where('item_num', $item_num)
            ->max('tr_seq');

            // Mulai dari tr_seq berikutnya
            $tr_seq = $lastTrSeq ? $lastTrSeq + 1 : 1;
            $insertData = [];

            foreach ($items as $item) {
                $insertData[] = [
                    'order_id'    => $order_id,
                    'item_num'    => $item_num,
                    'tr_seq'      => $tr_seq++,
                    'article_id'  => $item['article_id'],
                    'qty'         => $item['qty'],
                    // 'amount'      => $item['amount'],
                    'remark'      => isset($item['remark']) && trim($item['remark']) !== '' ? $item['remark'] : '',
                    'userid'      => getAuth($request)->user_id2,
                    'dt_modified' => Carbon::now('Asia/Jakarta')
                ];
            }

            if (!empty($insertData)) {
                $conn->table($table)->insert($insertData);
            }

            return $this->sendResponse('Success', 'Data berhasil disimpan.');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Insert Error', 500);
        }
    }

    // public function saveCustomerOrderAddonUnit(Request $request) //untuk bonus - motong stok wh_bal
    // {
    //     try {
    //         DB::connection('sqlSrvFPI')->beginTransaction();
    //         $order_id = $request->order_id;
    //         $item_num = $request->item_num;
    //         $items = $request->items;

    //         $conn = DB::connection('sqlSrvFPI');
    //         $table = 'CustomerorderAddonUnit';

    //         // Ambil data lama yang akan dihapus (hanya cat_id != 6)
    //         $toDelete = $conn->table('CustomerOrderAddonUnit AS cau')
    //             ->join('article AS a', 'cau.article_id', '=', 'a.article_id')
    //             ->where('a.cat_id', '!=', '6')
    //             ->where('cau.order_id', $order_id)
    //             ->where('cau.item_num', $item_num)
    //             ->get(['cau.article_id', 'cau.qty']);

    //         // Rollback stok ke wh_bal
    //         foreach ($toDelete as $item) {
    //             $conn->table('wh_bal')
    //                 ->where([
    //                     ['prod_code', '=', $item->article_id],
    //                     ['wh_id', '=', 'G01'],
    //                     ['bin_id', '=', '01'],
    //                 ])
    //                 ->update([
    //                     'qty_oh' => DB::raw("qty_oh + $item->qty"),
    //                     'qty_iss' => DB::raw("qty_iss - $item->qty"),
    //                 ]);
    //         }

    //         // Hapus data lama dari ketiga tabel
    //         $conn->table('CustomerOrderAddonUnit')
    //             ->where('order_id', $order_id)
    //             ->where('item_num', $item_num)
    //             ->delete();

    //         $conn->table('wh_trans_dtl')
    //             ->where('tr_id', $order_id)
    //             ->where('tr_type', 'IS')
    //             ->where('office_id', 1)
    //             ->delete();

    //         $conn->table('wh_trans_hdr')
    //             ->where('tr_id', $order_id)
    //             ->where('tr_type', 'IS')
    //             ->where('office_id', 1)
    //             ->delete();

    //         // Hitung tr_seq awal
    //         $lastTrSeq = $conn->table($table)
    //             ->where('order_id', $order_id)
    //             ->where('item_num', $item_num)
    //             ->max('tr_seq');
    //         $tr_seq = $lastTrSeq ? $lastTrSeq + 1 : 1;

    //         $insertData = [];
    //         $insertDtl = [];
    //         $dtl_seq = 1;
    //         $tr_id = $order_id;

    //         foreach ($items as $item) {
    //             $prodCode = $item['article_id'];
    //             $qty = $item['qty'];

    //             // Validasi stok cukup
    //             $currentStock = $conn->table('wh_bal')
    //                 ->where('prod_code', $prodCode)
    //                 ->where('wh_id', 'G01')
    //                 ->where('bin_id', '01')
    //                 ->value('qty_oh');

    //             if ($currentStock < $qty) {
    //                 throw new \Exception("Stok tidak mencukupi untuk artikel $prodCode. Stok tersedia: $currentStock, diminta: $qty");
    //             }

    //             // Update stok wh_bal
    //             $conn->table('wh_bal')
    //                 ->where([
    //                     ['prod_code', '=', $prodCode],
    //                     ['wh_id', '=', 'G01'],
    //                     ['bin_id', '=', '01'],
    //                 ])
    //                 ->update([
    //                     'qty_oh' => DB::raw("qty_oh - $qty"),
    //                     'qty_iss' => DB::raw("qty_iss + $qty"),
    //                 ]);

    //             // Insert ke CustomerOrderAddonUnit
    //             $insertData[] = [
    //                 'order_id'    => $order_id,
    //                 'item_num'    => $item_num,
    //                 'tr_seq'      => $tr_seq,
    //                 'article_id'  => $prodCode,
    //                 'qty'         => $qty,
    //                 'remark'      => $item['remark'] ?? '',
    //                 'userid'      => getAuth($request)->user_id2,
    //                 'dt_modified' => now(),
    //             ];

    //             // Insert ke wh_trans_dtl
    //             $insertDtl[] = [
    //                 'office_id'      => 1,
    //                 'tr_type'        => 'IS',
    //                 'tr_id'          => $tr_id,
    //                 'tr_seq'         => $tr_seq,
    //                 'dtl_seq'        => $dtl_seq++,
    //                 'prod_code'      => $prodCode,
    //                 'add_id'         => '',
    //                 'tor_id'         => '',
    //                 'panjang'        => 0,
    //                 'batch_id'       => '',
    //                 'wh_id'          => 'G01',
    //                 'bin_id'         => '01',
    //                 'qty'            => $qty,
    //                 'wgt'            => 0,
    //                 'remark'         => $item['remark'] ?? '',
    //                 'period'         => '',
    //                 'tr_id_ref'      => $order_id,
    //                 'tr_id_seq_ref'  => $tr_seq,
    //                 'dt_modified'    => now(),
    //                 'user_id'        => getAuth($request)->user_id2,
    //                 'repl'           => '',
    //                 'claim'          => '',
    //             ];

    //             $tr_seq++;
    //         }

    //         // Simpan data jika ada
    //         if (!empty($insertData)) {
    //             $conn->table($table)->insert($insertData);
    //         }

    //         if (!empty($insertDtl)) {
    //             // Insert ke wh_trans_hdr hanya jika ada dtl
    //             $conn->table('wh_trans_hdr')->insert([
    //                 'office_id'     => 1,
    //                 'tr_type'       => 'IS',
    //                 'tr_id'         => $tr_id,
    //                 'tr_date'       => now(),
    //                 'wh_id'         => 'G01',
    //                 'bin_id'        => '01',
    //                 'wh_dest'       => '',
    //                 'bin_dest'      => '',
    //                 'remark'        => 'Untuk Bonus Customer Order',
    //                 'tr_id_ref'     => $order_id,
    //                 'dt_modified'   => now(),
    //                 'user_id'       => getAuth($request)->user_id2,
    //                 'bk'            => '',
    //                 'claim'         => '',
    //                 'doc_id'        => '',
    //                 'category_id'   => '',
    //                 'dt_confirm1'   => now(),
    //                 'dt_confirm2'   => now(),
    //                 'dt_confirm3'   => now(),
    //                 'dt_trans'      => now(),
    //             ]);

    //             $conn->table('wh_trans_dtl')->insert($insertDtl);
    //         }

    //         DB::connection('sqlSrvFPI')->commit();
    //         return $this->sendResponse('Success', 'Data berhasil disimpan.');
    //     } catch (\Exception $e) {
    //         DB::connection('sqlSrvFPI')->rollBack();
    //         return $this->sendError($e->getMessage(), 'Insert Error', 500);
    //     }
    // }

    public function saveCustomerOrderAddonUnitIncludedPrice(Request $request){ //untuk included price
        try {
            $order_id = $request->order_id;
            $item_num = $request->item_num;
            $items = $request->items;

            $conn = DB::connection('sqlSrvFPI');
            $table = 'CustomerorderAddonUnit';

            // DELETE dengan INNER JOIN berdasarkan kriteria tertentu
            DB::connection('sqlSrvFPI')->statement("
            DELETE CAU
            FROM CustomerOrderAddonUnit CAU
            INNER JOIN article A ON CAU.article_id = A.article_id
            WHERE A.cat_id = '6' and CAU.order_id = ? AND CAU.item_num = ?
            ", [$order_id, $item_num]);

            // Cek nilai terakhir tr_seq yang tersisa (dari cat_id = 6, karena cat_id != 6 sudah dihapus)
            $lastTrSeq = $conn->table($table)
            ->where('order_id', $order_id)
            ->where('item_num', $item_num)
            ->max('tr_seq');

            // Mulai dari tr_seq berikutnya
            $tr_seq = $lastTrSeq ? $lastTrSeq + 1 : 1;
            $insertData = [];

            foreach ($items as $item) {
                $insertData[] = [
                    'order_id'    => $order_id,
                    'item_num'    => $item_num,
                    'tr_seq'      => $tr_seq++,
                    'article_id'  => $item['article_id'],
                    'qty'         => $item['qty'],
                    // 'amount'      => $item['amount'],
                    'remark'      => isset($item['remark']) && trim($item['remark']) !== '' ? $item['remark'] : '',
                    'userid'      => getAuth($request)->user_id2,
                    'dt_modified' => Carbon::now('Asia/Jakarta')
                ];
            }

            if (!empty($insertData)) {
                $conn->table($table)->insert($insertData);
            }

            return $this->sendResponse('Success', 'Data berhasil disimpan.');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Insert Error', 500);
        }
    }

    private function genOrderId($orderdate,$idproyek) //2025-04-14 - YYYY-MM-DD - 01/nokav/period/nourut
    {
        $period = Carbon::parse($orderdate)->format('Ym'); // output: "202504"
        $proyekid = $idproyek->proyek_id;
        
        // $getLastCustID = DB::connection('sqlSrvFPI')
        // ->table('CustomerOrder')
        // ->select('order_id')
        // ->where('order_id', 'like', '01/' . $proyekid . '/' . $period . '/%')
        // ->orderByDesc('order_id')
        // ->first();
        
        // Cari order terakhir berdasarkan tahun, bukan bulan
        // $getLastCustID = DB::connection('sqlSrvFPI')
        // ->table('CustomerOrder')
        // ->select('order_id')
        // ->where('order_id', 'like', '01/%/' . substr($period, 0, 4) . '%') // hanya tahun
        // // ->orderByDesc('order_id')
        // ->orderByRaw('CAST(RIGHT(order_id, 4) AS INT) DESC')
        // ->first();

        $getLastCustID = DB::connection('sqlSrvFPI')
        ->table('CustomerOrder')
        ->select('order_id')
        // ->where('order_id', 'like', '01/%/2025%') // sesuaikan tahun bila perlu
        ->where('order_id', 'like', '01/%/' . substr($period, 0, 4) . '%') // hanya tahun
        ->whereRaw("
            CAST(
                SUBSTRING(
                    order_id,
                    CHARINDEX('/', order_id, CHARINDEX('/', order_id) + 1) + 1,
                    6
                ) AS INT
            ) > 202503
        ") //jika nomer urut sudah melewati 700 remark bagian ini
        ->orderByRaw('CAST(RIGHT(order_id, 4) AS INT) DESC')
        ->first();

        // dd($getLastCustID);
        $no_urut = 0;

        if ($getLastCustID) {
            $parts = explode('/', $getLastCustID->order_id);
            $no_urut = (int) end($parts);
            
        }
    
        $new_urut = str_pad($no_urut + 1, 4, '0', STR_PAD_LEFT);
        // dd($new_urut);
        $gen_order_id = "01/{$proyekid}/{$period}/{$new_urut}";
        return $gen_order_id;
    }

    // public function saveCustomerOrderDetilPayterm(Request $request)
    // {
    //     try {
    //         $input = $request->all();

    //         $pay_term_id = strtoupper($input['pay_term_id'] ?? ''); // "SOFT CASH", "HARD CASH", "KPR"
    //         $order_id = $input['order_id'] ?? '';
    //         $bank_name = $input['bank_name'] ?? '';
    //         $numofinstallment = $input['numofinstallment'] ?? 0;
    //         $userid = getAuth($request)->user_id2;

    //         $conn = DB::connection('sqlSrvFPI');
    //         $table = 'pay_term_dtl';

    //         // Cek apakah data untuk order_id sudah ada
    //         $existingPayTerm = $conn->table($table)
    //             ->where('order_id', $order_id)
    //             ->select('pay_term_id')
    //             ->first();

    //         // Jika pay_term_id berbeda dengan yang sebelumnya, hapus data lama
    //         if ($existingPayTerm && strtoupper($existingPayTerm->pay_term_id) !== $pay_term_id) {
    //             $conn->table($table)->where('order_id', $order_id)->delete();
    //         }

    //         $tr_seq = 1;
    //         $tFields = [];

    //         // Tentukan field T mana yang akan diproses
    //         if (in_array($pay_term_id, ['SOFT CASH', 'HARD CASH'])) {
    //             $tFields = ['T1'];
    //         } elseif ($pay_term_id === 'KPR') {
    //             $tFields = ['T1', 'T2', 'T3', 'T4', 'T5'];
    //         }

    //         foreach ($tFields as $field) {
    //             $value = $input[$field] ?? null;
    //             $trimmedValue = trim((string) $value);

    //             // Khusus KPR, hanya simpan jika ada isinya dan numeric
    //             if ($pay_term_id === 'KPR') {
    //                 if ($trimmedValue === '' || !is_numeric($trimmedValue)) {
    //                     continue; // skip field kosong
    //                 }
    //             }// Untuk semua tipe, normalisasi value jadi integer (kosong jadi 0)
    //             $value = ($trimmedValue === '' || $value === null) ? 0 : (int) $value;
    //             // if ($value !== null && trim($value) !== '' && is_numeric($value) && (int)$value != 0) {
    //                 $data = [
    //                     'pay_term_id'       => $pay_term_id,
    //                     'order_id'          => $order_id,
    //                     'param1'            => $value,
    //                     'tr_seq'            => $tr_seq,
    //                     'remark'            => '',
    //                     'dt_create'         => Carbon::now(),
    //                     'dt_modified'       => Carbon::now(),
    //                     'userid'            => $userid,
    //                     'numofinstallment'  => (int) $numofinstallment,
    //                     'bank_name'         => $bank_name
    //                 ];

    //                 $conn->table($table)->insert($data);
    //                 $tr_seq++;
    //             // }
    //         }

    //         return $this->sendResponse('Success', 'Data berhasil disimpan atau diperbarui.');
    //     } catch (\Exception $e) {
    //         return $this->sendError($e->getMessage(), 'Insert/Update Error', 500);
    //     }
    // }

    public function saveCustomerOrderDetilPayterm(Request $request)
    {
        try {
            $input = $request->all();
            // \Log::info('FULL_REQUEST_INPUT', $input);

            $pay_term_id = strtoupper($input['pay_term_id'] ?? '');
            $order_id = $input['order_id'] ?? '';
            $bank_name = $input['bank_name'] ?? '';
            $bank_acc = $input['bank_acc'] ?? '';
            $numofinstallment = (int)($input['numofinstallment'] ?? 0);
            $userid = getAuth($request)->user_id2;
            $dp_amt = (int)($input['dp_amt'] ?? 0);
            $dt_akad = isset($input['dt_akad']) ? Carbon::parse($input['dt_akad']) : null;

            $conn = DB::connection('sqlSrvFPI');
            $table = 'pay_term_dtl';
            $now = Carbon::now('Asia/Jakarta');
            
            $existingOrder = DB::connection('sqlSrvFPI')
            ->table('CustomerOrder')
            ->where('order_id', $order_id)
            ->first();

            if($existingOrder) {
                DB::connection('sqlSrvFPI')
                    ->table('CustomerOrder')
                    ->where('order_id', $order_id)
                    ->update([
                        'dp_amt' => $dp_amt,
                    ]);
            }

            // Cek existing
            $existingPayTerm = $conn->table($table)
                ->where('order_id', $order_id)
                ->select('pay_term_id')
                ->first();
    
            if ($existingPayTerm && strtoupper($existingPayTerm->pay_term_id) !== $pay_term_id) {
                $conn->table($table)->where('order_id', $order_id)->delete();
            }
    
            // Hapus existing
            // $conn->table($table)->where('order_id', $order_id)->delete();
    
            $tr_seq = 1;

            $numofinstallment_dp = (int)($input['numofinstallment_dp'] ?? 0);
            $amt_dp = isset($input['dp_amt']) 
                ? (int)str_replace(['.', ','], '', $input['dp_amt']) 
                : 0;
            // $dt_due_dp = $input['dt_due_dp'] ?? [];
            $details_dpt = $input['details_dpt'] ?? [];
                // \Log::info('DEBUG_CICILAN_DP', [
                //     'pay_term_id' => $pay_term_id,
                //     'numofinstallment_dp' => $numofinstallment_dp,
                //     'amt_dp' => $amt_dp,
                //     'details_dpt_count' => is_array($details_dpt) ? count($details_dpt) : 'not array',
                //     'details_dpt' => $details_dpt
                // ]);
            
            /*=============================| cicilan DP softcash dan hardcash |==============================*/

            // Hanya proses jika metode adalah SOFT CASH atau HARD CASH
            if (in_array($pay_term_id, ['SOFT CASH', 'HARD CASH'])) {
            // === CICILAN DP ===
            $details_dpt = $input['details_dpt'] ?? [];
                if ($numofinstallment_dp > 0 && $amt_dp > 0 && is_array($details_dpt) && count($details_dpt) > 0) {
                    // \Log::info('CICILAN_DP_MULAI_INSERT', [
                    //     'numofinstallment_dp' => $numofinstallment_dp,
                    //     'amt_dp' => $amt_dp,
                    //     'details_dpt' => $details_dpt
                    // ]);

                    foreach ($details_dpt as $index => $item) {
                        $dpAmount = isset($item['amount']) ? (int)str_replace(['.', ','], '', $item['amount']) : 0;
                        $dpDue = $item['dt_due'] ?? null;

                        if (empty($dpDue)) {
                            throw new \Exception("Tanggal jatuh tempo DP ke-" . ($index + 1) . " belum diisi");
                        }

                        $conn->table($table)->insert([
                            'pay_term_id'           => $pay_term_id,
                            'order_id'              => $order_id,
                            'tr_seq'                => $tr_seq++,
                            'remark'                => 'Cicilan DP',
                            'dt_create'             => $now,
                            'dt_modified'           => $now,
                            'userid'                => $userid,
                            'bank_name'             => $bank_name,
                            'bank_acc'              => $bank_acc,
                            'dt_due_dp'             => Carbon::parse($dpDue),
                            'amt_dp'                => $dpAmount,
                            'numofinstallment_dp'   => $numofinstallment_dp
                        ]);
                    }

                    // \Log::info('CICILAN_DP_SELESAI_INSERT', ['order_id' => $order_id]);
                }
            }
            /*===================================| endline |=============================================*/


            if ($pay_term_id === 'SOFT CASH') {
                $details = $input['details'] ?? [];

                foreach ($details as $item) {
                    $amount = isset($item['amount']) 
                    ? (int)str_replace(['.', ','], '', $item['amount']) 
                    : 0;
                    $dueDate = $item['dt_due'] ?? null;
    
                    if ($amount <= 0) {
                        continue;
                    }
                    if (empty($dueDate)) {
                        throw new \Exception("Field 'dt_due' wajib diisi untuk SOFT CASH");
                    }
    
                    $conn->table($table)->insert([
                        'pay_term_id'       => $pay_term_id,
                        'order_id'          => $order_id,
                        'param1'            => $amount,
                        'tr_seq'            => $tr_seq++,
                        'remark'            => '',
                        'dt_create'         => $now,
                        'dt_modified'       => $now,
                        'userid'            => $userid,
                        'numofinstallment'  => $numofinstallment,
                        'bank_name'         => $bank_name,
                        'bank_acc'          => $bank_acc,
                        'install_amt'       => 0,
                        'dt_due'            => Carbon::parse($dueDate)                    
                    ]);
                }
            } elseif ($pay_term_id === 'HARD CASH') {
                for ($i = 1; $i <= 5; $i++) {
                    $amountField = 'T' . $i;
                    // T1: kalau tidak ada tetap dianggap 0
                    if ($i === 1) {
                        $amount = isset($input[$amountField]) && is_numeric($input[$amountField]) ? (int)$input[$amountField] : 0;

                        // Tetap insert meskipun 0
                        $conn->table($table)->insert([
                            'pay_term_id'       => $pay_term_id,
                            'order_id'          => $order_id,
                            'param1'            => $amount,
                            'tr_seq'            => $tr_seq++,
                            'remark'            => '',
                            'dt_create'         => $now,
                            'dt_modified'       => $now,
                            'userid'            => $userid,
                            'numofinstallment'  => $numofinstallment,
                            'bank_name'         => $bank_name,
                            'bank_acc'          => $bank_acc,
                            'dt_due'            => $now,
                            'install_amt'       => 0
                        ]);
                    } else {
                        // T2–T5: hanya insert jika > 0
                        if (isset($input[$amountField]) && is_numeric($input[$amountField]) && (int)$input[$amountField] > 0) {
                            $conn->table($table)->insert([
                                'pay_term_id'       => $pay_term_id,
                                'order_id'          => $order_id,
                                'param1'            => (int)$input[$amountField],
                                'tr_seq'            => $tr_seq++,
                                'remark'            => '',
                                'dt_create'         => $now,
                                'dt_modified'       => $now,
                                'userid'            => $userid,
                                'numofinstallment'  => $numofinstallment,
                                'bank_name'         => $bank_name,
                                'bank_acc'          => $bank_acc,
                                'dt_due'            => $now,
                                'install_amt'       => 0
                            ]);
                        }
                    }
                }
            }elseif($pay_term_id === 'KPR'){
                // Cek apakah ada input T1–T5
                $hasTFields = false;
                for ($i = 1; $i <= 5; $i++) {
                    if (!empty($input['T' . $i]) || $i === 1) { // T1 wajib diinsert meski 0
                        $hasTFields = true;
                        break;
                    }
                }

                // Jika ada input T1–T5, hapus hanya data yang param1 terisi (install_amt = 0)
                if ($hasTFields) {
                    $conn->table($table)
                        ->where('order_id', $order_id)
                        ->where('install_amt', 0)
                        ->delete();

                    for ($i = 1; $i <= 5; $i++) {
                        $amountField = 'T' . $i;

                        if ($i === 1) {
                            // T1 wajib dimasukkan meski 0
                            $amount = isset($input[$amountField]) && is_numeric($input[$amountField]) ? (int)$input[$amountField] : 0;

                            $conn->table($table)->insert([
                                'pay_term_id'       => $pay_term_id,
                                'order_id'          => $order_id,
                                'param1'            => $amount,
                                'tr_seq'            => $tr_seq++,
                                'remark'            => '',
                                'dt_create'         => $now,
                                'dt_modified'       => $now,
                                'userid'            => $userid,
                                'numofinstallment'  => $numofinstallment,
                                'bank_name'         => $bank_name,
                                'bank_acc'          => $bank_acc,
                                'dt_due'            => $now,
                                'dt_akad'           => $dt_akad, // Tambahkan dt_akad
                                'install_amt'       => 0
                            ]);
                        } else {
                            if (isset($input[$amountField]) && is_numeric($input[$amountField]) && (int)$input[$amountField] > 0) {
                                $conn->table($table)->insert([
                                    'pay_term_id'       => $pay_term_id,
                                    'order_id'          => $order_id,
                                    'param1'            => (int)$input[$amountField],
                                    'tr_seq'            => $tr_seq++,
                                    'remark'            => '',
                                    'dt_create'         => $now,
                                    'dt_modified'       => $now,
                                    'userid'            => $userid,
                                    'numofinstallment'  => $numofinstallment,
                                    'bank_name'         => $bank_name,
                                    'bank_acc'          => $bank_acc,
                                    'dt_due'            => $now,
                                    'dt_akad'           => $dt_akad, // Tambahkan dt_akad
                                    'install_amt'       => 0
                                ]);
                            }
                        }
                    }
                }

                // Jika ada input details[], hapus hanya data yang install_amt terisi
                $details = $input['details'] ?? [];
                // Cek apakah ada detail yang valid untuk diproses
                $hasValidDetails = false;
                foreach ($details as $item) {
                    // $amount = isset($item['amount']) ? (int)$item['amount'] : 0;
                    $amount = isset($item['amount']) 
                    ? (int)str_replace(['.', ','], '', $item['amount']) 
                    : 0;
                    $dueDate = $item['dt_due'] ?? null;
                    if ($amount > 0 && !empty($dueDate)) {
                        $hasValidDetails = true;
                        break;
                    }
                }

                if ($hasValidDetails) {
                    $conn->table($table)
                        ->where('order_id', $order_id)
                        ->where('install_amt', '>', 0)
                        ->delete();

                    foreach ($details as $item) {
                        // $amount = isset($item['amount']) ? (int)$item['amount'] : 0;
                        $amount = isset($item['amount']) 
                        ? (int)str_replace(['.', ','], '', $item['amount']) 
                        : 0;
                        $dueDate = $item['dt_due'] ?? null;

                        if ($amount <= 0 || empty($dueDate)) {
                            continue;
                        }

                        $conn->table($table)->insert([
                            'pay_term_id'       => $pay_term_id,
                            'order_id'          => $order_id,
                            'param1'            => 0,
                            'install_amt'       => $amount,
                            'tr_seq'            => $tr_seq++,
                            'remark'            => '',
                            'dt_create'         => $now,
                            'dt_modified'       => $now,
                            'userid'            => $userid,
                            'numofinstallment'  => $numofinstallment,
                            'bank_name'         => $bank_name,
                            'bank_acc'          => $bank_acc,
                            'dt_akad'           => $dt_akad, // Tambahkan dt_akad
                            'dt_due'            => Carbon::parse($dueDate)
                        ]);
                    }
                }
            }


            return $this->sendResponse('Success', 'Data berhasil disimpan atau diperbarui.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Insert/Update Error', 500);
        }
    }

    public function getDetailPayTerm(Request $request){ //untuk softcash & hardcash
        $sqlWhere = "1=1";
        $order_id = $request->order_id;
        // $item_num = $request->item_num;

        // if($item_num){
        //     $sqlWhere.= " and tr_seq = '$item_num'";
        // }
        
        //included price
        $result = DB::connection('sqlSrvFPI')
            ->table('pay_term_dtl')
            ->selectraw('
                trim(pay_term_id) as pay_term_id,
                tr_seq,
                param1,
                remark,
                order_id,
                numofinstallment,
                bank_name,
                trim(bank_acc) as bank_acc,
                dt_due,
                dt_akad,
                install_amt,
                numofinstallment_dp,
                amt_dp,
                dt_due_dp
            ')
            // ->leftjoin('', 'CustomerOrderAddonUnit.article_id', '=', 'article.article_id')
            ->where('order_id',$order_id)
            ->whereRaw($sqlWhere)
            // ->where('tr_seq', $item_num)
            // ->where('article.cat_id', '6')
            ->get();
            // Kelompokkan hasil
            $withInstallAmt = [];
            $withoutInstallAmt = [];
            $cicilanDPdetails = [];

                foreach ($result as $row) {
                    
                    $payTerm = strtoupper(trim($row->pay_term_id));
                    // dd($row->pay_term_id, $payTerm);
                    $param1 = (float) ($row->param1 ?? 0);
                    $install_amt = (float) ($row->install_amt ?? 0);

                    $bothZero = $param1 === 0 && $install_amt === 0;

                    if ($row->remark === 'Cicilan DP') {
                        $cicilanDPdetails[] = $row;
                        continue; // Skip proses lain, langsung masuk ke cicilan DP
                    }

                    if ($payTerm === 'SOFT CASH' && $row->remark === '') {
                        $row->install_amt = $param1;
                        $withInstallAmt[] = $row;
                        // dd($withInstallAmt);
                    } elseif ($payTerm === 'HARD CASH' && $row->remark === '') {
                        
                        $withoutInstallAmt[] = $row;
                        // dd($withoutInstallAmt);
                    } elseif ($payTerm === 'KPR') {
                        if ($install_amt > 0 || $bothZero) {
                            $withInstallAmt[] = $row;
                        } else {
                            $withoutInstallAmt[] = $row;
                        }
                    } else {
                        // pay_term_id selain SOFT CASH / HARD CASH / KPR
                        if ($bothZero && in_array($payTerm, ['SOFT CASH', 'KPR'])) {
                            $withInstallAmt[] = $row;
                        } else {
                            $withoutInstallAmt[] = $row;
                        }
                    }

                    // if($row->remark === 'Cicilan DP') {
                    //     $cicilanDPdetails[] = $row;
                    // }
                }
                
                return $this->sendResponse([
                    'with_install_amt' => $withInstallAmt,
                    'without_install_amt' => $withoutInstallAmt,
                    'cicilan_dp_details' => $cicilanDPdetails,
                ], 'Successfully get Customer Order Pay Term Details');
        // return $this->sendResponse($result, 'Successfully get Customer Order Addon Unit Detail');
    }

    public function getNoBooking(Request $request){
        $book_id = $request->book_id;
        $sqlWhere = "1=1";
        
        if($book_id){
            $sqlWhere.= " and book_id = '$book_id'";
        }

        $no_booking = DB::connection('sqlSrvFPI')
            ->table('CustomerBooking')
            ->selectraw('
                trim(CustomerBooking.book_id) as book_id,
                trim(CustomerBooking.nama_customer) as nama_customer,
                trim(CustomerBooking.alamat_customer) as alamat_customer,
                trim(CustomerBooking.nomor_hp) as nomor_hp,
                trim(CustomerBooking.telepon) as telepon,
                trim(CustomerBooking.sales_id) as sales_id,
                trim(CustomerBooking.house_item) as house_item,
                CustomerBooking.price_list,
                CustomerBooking.discount,
                trim(CustomerBooking.payterm) as payterm,
                trim(CustomerBooking.bank) as bank,
                CustomerBooking.exp_date,
                trim(CustomerBooking.status) as status,
                CustomerBooking.dt_created,
                trim(CustomerBooking.created_by) as created_by,
                CustomerBooking.dt_aprv1,
                trim(CustomerBooking.aprv1_by) as aprv1_by,
                CustomerBooking.dt_aprv2,
                trim(CustomerBooking.aprv2_by) as aprv2_by,
                trim(CustomerBooking.tr_type) as tr_type,
                trim(CustomerBooking.active_flag) as active_flag,
                CustomerBooking.remark,
                trim(CustomerBooking.order_id) as order_id,
                trim(site_cluster.cluster_name) as cluster_name,
                trim(house_unit.house_name) as house_name,
                trim(house_type.common_name) as common_name,
                trim(house_type.sbk_name) as sbk_name,
                trim(site_cluster.id_cluster) as id_cluster,
                trim(site_cluster.id_site) as id_site,
                trim(house_unit.id_house_type) as id_house_type
            ')
            ->leftjoin('house_unit', 'CustomerBooking.house_item', '=', 'house_unit.id_house')
            ->leftjoin('site_cluster', 'house_unit.id_cluster', '=', 'site_cluster.id_cluster')            
            ->leftjoin('house_type', 'house_unit.id_house_type', '=', 'house_type.id_house_type')
            ->where('status', 'U')
            ->where('order_id','=', '')
            ->whereRaw($sqlWhere)
            ->get();
        
        return $this->sendResponse($no_booking, 'Successfully get No Booking');
    }

    public function cancelSPR(Request $request){
        $order = DB::connection('sqlSrvFPI')
        ->table('CustomerOrderDetil')
        ->where('order_id', $request->order_id)
        ->first();

        if ($order) {
            // Ambil data header berdasarkan doc_id
        $existingTransHDR = DB::connection('sqlSrvFPI')
            ->table('qc_trans_hdr')
            ->where('doc_id', $request->order_id)
            ->first();
        // dd($existingTransHDR->qc_trans_id);
        
        if ($existingTransHDR) {
        // Hapus semua detail yang terkait dengan qc_trans_id
        // DB::connection('sqlSrvFPI')
        //     ->table('qc_trans_dtl')
        //     ->where('qc_trans_id', $existingTransHDR->qc_trans_id)
        //     ->delete();

        // Hapus header
        DB::connection('sqlSrvFPI')
            ->table('qc_trans_hdr')
            ->where('qc_trans_id', $existingTransHDR->qc_trans_id)
            // ->delete();
            ->update([
                'active_flag' => 'N'
            ]); 
        }

        $updateStatHouseUnit = DB::connection('sqlSrvFPI')
                ->table('house_unit')
                ->where('id_house', $order->id_house)
                ->where('id_cluster', $order->id_cluster)
                ->update([
                    'sold_stat' => 'V'
                ]);    
        $updateCustomerBooking = DB::connection('sqlSrvFPI')
                ->table('CustomerOrder')
                ->where('order_id', $request->order_id)
                ->update([
                    'status' => 'X',
                    'cancel_by' => getAuth($request)->user_id2,
                    'dt_cancel' => Carbon::now('Asia/Jakarta')
                ]);    
            return $this->sendResponse('Success cancel Booking', 'Successfully cancel Booking');
        } else {
            return $this->sendError('No Order tidak ditemukan', 'Error', 500);
        }

        
    }
    
    public function getKPRdata(Request $request){
        $CustomerId = $request->CustomerId;
        $SalesId = $request->SalesId;
        $id_site = $request->id_site;
        $pay_term_id = $request->pay_term_id;
        $order_id = $request->order_id;
        $sqlWhere = "1=1";
        
        if($order_id){
            $sqlWhere.= " and a.order_id = '$order_id'";
        }
        if($CustomerId){
            $sqlWhere.= " and a.CustomerId = '$CustomerId'";
        }
        if($SalesId){
            $sqlWhere.= " and a.SalesId = '$SalesId'";
        }
        if($id_site){
            $sqlWhere.= " and a.id_site = '$id_site'";
        }
        if($pay_term_id){
            $sqlWhere.= " and a.pay_method = '$pay_term_id'";
        }

        $start_date = $request->start_date;
        if(!$start_date)
        {
            $start_date = '';
        }

        $end_date = $request->end_date;
        if(!$end_date)
        {
            $end_date = '';
        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and a.order_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and a.order_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
            }
        }

        // Raw SQL: FROM CustomerOrder OUTER APPLY ...
        $query = "
            SELECT 
                a.*, 
                b.*, 
                Customer.NamaCustomer, 
                Sales.NamaSales, 
                site.site_name,
                LTRIM(RTRIM(site_cluster.cluster_name)) AS cluster_name,
                LTRIM(RTRIM(house_unit.house_name)) AS house_name,
                LTRIM(RTRIM(house_type.common_name)) AS common_name,
                LTRIM(RTRIM(house_type.sbk_name)) AS sbk_name,
                Customer.Alamat_id_card,
                CustomerOrderDetil.disc_unit,
                Customer.Telepon,
                house_unit.building_area,
                house_unit.land_area,
                house_unit.house_price,
                district_list.district AS district_name,
                village_list.village AS village_name,
                city_list.city AS city_name
            FROM CustomerOrder a
            OUTER APPLY (
                SELECT TOP 1 * FROM pay_term_dtl WHERE pay_term_dtl.order_id = a.order_id
            ) b
            LEFT JOIN Customer ON a.CustomerId = Customer.CustomerId
            LEFT JOIN Sales ON a.SalesId = Sales.SalesId
            LEFT JOIN site ON a.id_site = site.id_site
            LEFT JOIN CustomerOrderDetil ON a.order_id = CustomerOrderDetil.order_id
            LEFT JOIN site_cluster ON CustomerOrderDetil.id_cluster = site_cluster.id_cluster
            LEFT JOIN house_unit ON CustomerOrderDetil.id_house = house_unit.id_house
            LEFT JOIN house_type ON house_unit.id_house_type = house_type.id_house_type
            LEFT JOIN district_list ON site.kecamatan = district_list.district_code
            LEFT JOIN village_list ON site.kelurahan_desa = village_list.village_code
            LEFT JOIN city_list ON site.city = city_list.city_code
            WHERE a.pay_method = 'KPR'
            AND a.status <> 'X'
            AND b.order_id IS NOT NULL
            AND $sqlWhere
        ";

        $results = DB::connection('sqlSrvFPI')->select(DB::raw($query));
        return $this->sendResponse($results, 'Successfully get KPR data');
    }

    public function getKPRdataNull(Request $request){ //yang tidak ada detil paymentnya
        $sqlWhere = "1=1";
        $CustomerId = $request->CustomerId;

        if($CustomerId){
            $sqlWhere.= " and a.CustomerId = '$CustomerId'";
        }

        $results = DB::connection('sqlSrvFPI')
        ->table('CustomerOrder as a')
        ->leftJoin('pay_term_dtl as b', 'a.order_id', '=', 'b.order_id')
        // ->leftjoin('Customer', 'a.CustomerId', '=', 'Customer.CustomerId')
        // ->leftjoin('Sales', 'a.SalesId', '=', 'Sales.SalesId')
        // ->leftjoin('site', 'a.id_site', '=', 'site.id_site')
        // ->leftjoin('CustomerOrderDetil', 'a.order_id', '=', 'CustomerOrderDetil.order_id')
        // ->leftjoin('site_cluster', 'CustomerOrderDetil.id_cluster', '=', 'site_cluster.id_cluster')
        // ->leftjoin('house_unit', 'CustomerOrderDetil.id_house', '=', 'house_unit.id_house')
        // ->leftjoin('house_type', 'house_unit.id_house_type', '=', 'house_type.id_house_type')
        // ->selectraw('a.*,b.*')
        ->selectRaw('a.*')
        ->where('a.pay_method', 'KPR')
        ->where('a.status', '!=', 'X')
        ->whereRaw($sqlWhere)
        ->whereNull('b.order_id')
        ->get();

        return $this->sendResponse($results, 'Successfully get KPR data');
    }

    public function getNoKwitansi(Request $request){
        $customer_id = $request->customer_id;
        $tr_id = $request->tr_id;
        $sqlWhere = "1=1";
        
        if($customer_id){
            $sqlWhere.= " and customerid = '$customer_id'";
        }

        if($tr_id){
            $sqlWhere.= " and tr_id = '$tr_id'";
        }

        $no_kwitansi = DB::connection('sqlSrvFPI')
            ->table('kwitansi')
            // ->selectraw('
            //     trim(order_id) as order_id,
            //     trim(no_kwitansi) as no_kwitansi
            // ')
            ->whereRaw($sqlWhere)
            ->where('type_code', '=', 'UTJ')
            ->where('doc_id', '=', '')
            ->get();
        
        return $this->sendResponse($no_kwitansi, 'Successfully get No Kwitansi');

    }

    private function genQCTransId($orderdate) //2025-04-14 - YYYY-MM-DD - 01/nokav/period/nourut
    {
        $tanggal = Carbon::parse($orderdate)->format('ymd'); // output: "250516"
        $prefix = "T" . $tanggal;

        // Ambil record terakhir untuk tanggal tersebut
        $getLast = DB::connection('sqlSrvFPI')
            ->table('qc_trans_hdr')
            ->select('qc_trans_id')
            ->where('qc_trans_id', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING(qc_trans_id, 8, 3) AS INT) DESC') // ambil 3 digit terakhir
            ->first();

        $no_urut = 0;
        if ($getLast) {
            $lastUrut = substr($getLast->qc_trans_id, 7, 3); // Ambil "001" dari T250516001
            $no_urut = (int) $lastUrut;
        }

        $new_urut = str_pad($no_urut + 1, 3, '0', STR_PAD_LEFT);
        $gen_qc_trans_id = $prefix . $new_urut; // hasil: T250516001

        return $gen_qc_trans_id;
    }

    public function updatePlafon (Request $request){
        $order_id = $request->order_id;
        $plafon_amt = (int)($request->plafon_amt ?? 0);

            DB::connection('sqlSrvFPI')
            ->table('CustomerOrder')
            ->where('order_id', $order_id)
            ->update([
                'plafon_naik' => $plafon_amt
            ]);    
        return $this->sendResponse('Success update plafon', 'Successfully update plafon');  
    }

    public function getDataforExport(Request $request){

        $sqlWhere = "1=1";
        $CustomerId = $request->CustomerId;
        $SalesId = $request->SalesId;
        $id_site = $request->id_site;
        $pay_term_id = $request->pay_term_id;
        $order_id = $request->order_id;
        $status = $request->status;
        
        // if($status){
        //     $sqlWhere.= " and CustomerOrder.status = '$status'";
        // }
        // if($order_id){
        //     $sqlWhere.= " and CustomerOrder.order_id = '$order_id'";
        // }
        if($CustomerId){
            $sqlWhere.= " and CustomerId = '$CustomerId'";
        }
        if($SalesId){
            $sqlWhere.= " and SalesId = '$SalesId'";
        }
        if($id_site){
            $sqlWhere.= " and id_site = '$id_site'";
        }
        if($pay_term_id){
            $sqlWhere.= " and pay_method = '$pay_term_id'";
        }

        $start_date = $request->start_date;
        if(!$start_date)
        {
            $start_date = '';
        }

        $end_date = $request->end_date;
        if(!$end_date)
        {
            $end_date = '';
        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and order_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and order_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
            }
        }

        $result = DB::connection('sqlSrvFPI')
            ->table('v_list_penjualan')
            ->selectraw('*')
            ->whereRaw($sqlWhere)
            ->orderByRaw('CAST(RIGHT(order_id, 4) AS INT) DESC')
            ->get();
        
        return $this->sendResponse($result, 'Successfully get data Customer Order');
    }
}
