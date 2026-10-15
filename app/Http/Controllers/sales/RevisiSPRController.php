<?php

namespace App\Http\Controllers\sales;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use DateTime;
use Image;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class RevisiSPRController extends Controller
{

  public function index(Request $request)
  {
    $whereRaw = '1=1 ';
    $customer_id = $request->customer_id;
    $sales_id = $request->sales_id;
    $payterm_id = $request->payterm_id;
    $site_id = $request->site_id;
    $start_date = $request->start_date;
    $date_end = $request->date_end;
    $active_flag = $request->flag;
    $type = $request->type;

    if($customer_id){
      $whereRaw .= " AND vlp.CustomerId = '$customer_id'";
    }

    if($sales_id){
      $whereRaw .= " AND vlp.sales_id = '$sales_id'";
    }

    if($payterm_id){
      $whereRaw .= " AND vlp.payterm_id = '$payterm_id'";
    }

    if($site_id){
      $whereRaw .= " AND vlp.site_id = '$site_id'";
    }

    if($start_date && $date_end){
      $whereRaw .= " AND cor.tr_date BETWEEN '$start_date' AND '$date_end'";
    }

    if($active_flag){
      $whereRaw .= " AND cor.active_flag = '$active_flag'";
    }

    if($type){
      $whereRaw .= " AND cor.tr_type = '$type'";
    }

    $datas = DB::connection('sqlSrvBCKI')
              ->table('CustomerOrderRevision as cor')
              ->selectRaw('
                cor.order_id,
                cor.tr_id,
                vlp.CustomerId,
                vlp.NamaCustomer,
                vlp.SalesId,
                vlp.NamaSales,
                vlp.id_site,
                vlp.site_name,
                vlp.id_cluster,
                vlp.cluster_name,
                vlp.order_date,
                vlp.pay_method,
                cor.unit_price,
                cor.disc_unit,
                cor.ord_amt,
                cor.utj,
                cor.dp_amt,
                cor.remark,
                vlp.status,
                cor.tr_type,
                cor.tr_date,
                cor.active_flag
              ')
              ->join('v_list_penjualan as vlp', 'cor.order_id', '=', 'vlp.order_id')
              ->whereRaw($whereRaw)
              ->get();

    foreach ($datas as $key => $value) {

      $result = DB::connection('sqlSrvBCKI')
        ->table('doc_attach')
        ->where('tr_id', '=', $value->tr_id)
        ->get();

        $value->images = $result;
    }


    return $this->sendResponse($datas, 'Successfully get data');
  }

  public function getCustomer(Request $request)
  {
    $customer = $request->search;
    if ($customer != '') {
      $result = DB::connection('sqlSrvBCKI')
        ->table('Customer')
        ->selectRaw("top 20 CustomerId, NamaCustomer")
        ->whereRaw("active_flag = 'Y' and NamaCustomer like '%$customer%' or CustomerId like '%$customer%'")
        ->orderBy('NamaCustomer', 'asc')
        ->get();
      return $this->sendResponse($result, 'Successfully get Customer');
    } else {
      $result = DB::connection('sqlSrvBCKI')
        ->table('Customer')
        ->selectRaw("top 20 CustomerId, NamaCustomer")
        ->where('active_flag', '=', 'Y')
        ->orderBy('NamaCustomer', 'asc')
        ->get();
        return $this->sendResponse($result, 'Successfully get Customer');
    }
  }

  public function getCustOrder(Request $request)
  {
    $customer = $request->cust_id;
      $result = DB::connection('sqlSrvBCKI')
        ->table('CustomerOrder')
        ->selectRaw("top 20 order_id")
        ->whereRaw("CustomerId = '$customer'")
        ->orderBy('CustomerId', 'asc')
        ->get();
      return $this->sendResponse($result, 'Successfully get Customer');
  }

  public function getFormOrder(Request $request)
  {
    $order_id = $request->order_id;
    try{
      $result = DB::connection('sqlSrvBCKI')
        ->table('v_list_penjualan')
        ->selectRaw("
          order_id,
          unit_price,
          disc_unit,
          ord_amt,
          utj,
          dp_amt,
          id_house")
        ->where('order_id', '=', $order_id)
        ->first();

      return $this->sendResponse($result, 'Successfully get data');
    }catch(Throwable $e){
      return $this->sendError($e->getMessage());
    }
  }

  public function activateRevisiSPR(Request $request) {
    try{
      $active_flag =  $request->active_flag;
      DB::connection('sqlSrvBCKI')
        ->table('CustomerOrderRevision')
        ->where('order_id', '=', $request->order_id)
        ->update([
          'active_flag' => $active_flag
        ]);

      return $this->sendResponse(null, 'Successfully update data');
    }catch(Throwable $e){
      return $this->sendError($e->getMessage());
    }
  }

  public function storeRevisi(Request $request) {
    try {
      $revID = $this->generateSPRRevisiID();
      // -- file uploads
      $fileAttachments = [];

      if ($request->hasFile('file_attachments')) {
        foreach ($request->file('file_attachments') as $key => $file) {
          if ($file && $file->isValid()) {
            $extension = $file->getClientOriginalExtension();
            $originalName = $file->getClientOriginalName();

            $filename = Str::random(40) . '.' . $extension;
            $path = 'fpis/rev-spr';

            // -- save file local
            Storage::disk('public')->put("$path/$filename", file_get_contents($file));
            $fileAttachments[] = [
              "tr_id" => $revID,
              "doc_type" => $request->tr_type,
              "doc_name" => $filename,
              "seq_num" => $key + 1,
              "n_file" => $path,
              "dt_modified" => now(),
              "user_id" => getAuth($request)->user_id2,
              "office_id" => "1"
            ];
          }
        }
      }

      if ($fileAttachments) {
        DB::connection('sqlSrvBCKI')
          ->table('doc_attach')
          ->insert($fileAttachments);
      }

      DB::connection('sqlSrvBCKI')
        ->table('CustomerOrderRevision')
        ->insert([
          'tr_id' => $revID,
          'order_id' => $request->order_id,
          'unit_price' => $request->unit_price ?? 0,
          'disc_unit' => $request->disc_unit ?? 0,
          'ord_amt' => $request->order_amt ?? 0,
          'utj' => $request->utj ?? 0,
          'dp_amt' => $request->dp_amt ?? 0,
          'tr_date' => $request->tr_date,
          'tr_type' => $request->tr_type,
          'remark' => $request->remark,
          'active_flag' => 'Y',
          'userid' => getAuth($request)->user_id2,
          'dt_modified' => date('Y-m-d H:i:s'),
          'dt_created' => date('Y-m-d H:i:s')
        ]);

      if($request->tr_type == 'CNL'){
        DB::connection('sqlSrvBCKI')
          ->table('CustomerOrder')
          ->where('order_id', '=', $request->order_id)
          ->update([
            'status' => 'C',
            'dt_modified' => date('Y-m-d H:i:s'),
            'dt_cancel' => $request->tr_date
          ]);

        DB::connection('sqlSrvBCKI')
          ->table('house_unit')
          ->where('id_house', '=', $request->id_house)
          ->update([
            'sold_stat' => 'V'
          ]);
      }

      return $this->sendResponse(null, 'Successfully store data');
    } catch (Throwable $e) {
      return $this->sendError($e->getMessage());
    }
  }

  private function generateSPRRevisiID()
  {
    $lastData = DB::connection('sqlSrvBCKI')
      ->table('CustomerOrderRevision')
      ->select('tr_id')
      ->orderBy('tr_id', 'desc')
      ->first();

    if ($lastData) {
      $lastNumber = (int) substr($lastData->tr_id, 3);
      $newNumber = $lastNumber + 1;
    } else {
      $newNumber = 1;
    }

    $newID = 'REV' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);

    return $newID;
  }
}
