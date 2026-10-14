<?php

namespace App\Http\Controllers\master;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use DB;

class BankMasterController extends Controller
{

    public function index(Request $request)
    {
        try {

            $datas = DB::connection('sqlSrvFPI')->table('account')
                        ->selectRaw("DISTINCT trim(bank_name) as bank_name")
                        ->whereIn('a_type', ['B'])
                        ->where('posting', 'Y')
                        ->get();
        
            return $this->sendResponse($datas, 200);


        } catch (\Exception $e) {
            return $this->sendError('Internal Error', $e->getMessage(), 500);
        }
    }

    public function account(Request $request)
    {
        try {

            $bankId = $request->bank_id;
            if (empty($bankId)) {
                return $this->sendError("Empty Bank ID", 'Error', 500);
            }

            $datas = DB::connection('sqlSrvFPI')->table('account as a')
                ->selectRaw("trim(a.bank_acc) as bank_acc, trim(b.ac_name) as description")
                ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
                ->where('a.bank_name', '=', $bankId)
                ->get();
        
            return $this->sendResponse($datas, 200);


        } catch (\Exception $e) {
            return $this->sendError('Internal Error', $e->getMessage(), 500);
        }
    }

    public function bank_mast(Request $request)
    {
        try {

            $datas = DB::connection('sqlSrvFPI')
                    ->table('bank_mast')
                    ->where('active_flag', 'Y')
                    ->get();
        
            return $this->sendResponse($datas, 200);


        } catch (\Exception $e) {
            return $this->sendError('Internal Error', $e->getMessage(), 500);
        }
    }

    public function getData(Request $request)
    {
        try {    
            $where = "1=1";

            if($request->a_type != null)
            {
                $a_type = $request->a_type;
            }
            else
            {
                $a_type = '';
            }
            $active_flag = $request->active_flag ? $request->active_flag : '';
            if (!empty($a_type))
            {
                $where = $where . " and a.a_type = ". "'" . $a_type . "'";
            }
            if (!empty($active_flag))
            {
                // $where = $where . " and whouse.active_flag = ". "'" . $active_flag . "'";
            } else {
                $where = $where . " and a.posting = 'Y'";
            }

            $datas = DB::connection('sqlSrvFPI')->table('account as a')
                ->selectRaw("a.*, trim(b.ac_name) as description")
                ->leftJoin('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
                ->whereRaw($where)
                ->get();
        
            return $this->sendResponse($datas, 200);

        } catch (\Exception $e) {
            return $this->sendError('Internal Error', $e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {    
            $save = DB::connection('sqlSrvFPI')->table('account')
                    ->insert([
                        'a_type' => $request->a_type,
                        'bank_acc' => $request->bank_acc,
                        'bank_name' => $request->bank_name,
                        'ac_num' => $request->ac_num,
                        'acc_code' => $request->ac_num,
                        'curr_id' => $request->curr_id ? $request->curr_id : 'IDR',
                        'saldo' => $request->saldo ? $request->saldo : 0,
                        'posting' => $request->posting ? $request->posting : 'Y',
                    ]);
            
            return $this->sendResponse('Successfully Created', 200);

        } catch (\Exception $e) {
            return $this->sendError('Internal Error', $e->getMessage(), 500);
        }
    }

    public function update(Request $request)
    {
        try {    
            $save = DB::connection('sqlSrvFPI')->table('account')
                    ->where('a_type', $request->a_type)
                    ->where('bank_acc', $request->bank_acc)
                    ->update([
                        'bank_name' => $request->bank_name,
                        'ac_num' => $request->ac_num,
                        'acc_code' => $request->ac_num,
                        'curr_id' => $request->curr_id ? $request->curr_id : 'IDR',
                        'saldo' => $request->saldo ? $request->saldo : 0,
                        'posting' => $request->posting ? $request->posting : 'Y',
                    ]);
            
            return $this->sendResponse('Successfully Created', 200);

        } catch (\Exception $e) {
            return $this->sendError('Internal Error', $e->getMessage(), 500);
        }
    }

    public function updateStatus(Request $request)
    {
        try {    

            $data = DB::connection('sqlSrvFPI')->table('account')
                    ->selectRaw('*')
                    ->where('a_type', $request->a_type)
                    ->where('bank_acc', $request->bank_acc)
                    ->first();

            if ($data) {

                $save = DB::connection('sqlSrvFPI')->table('account')
                        ->where('a_type', $request->a_type)
                        ->where('bank_acc', $request->bank_acc)
                        ->update([
                            'posting' => $request->posting
                        ]);

                return $this->sendResponse('Successfully Updated', 200);
                
            } else {
                return $this->sendError('Data not Found', 'Error', 500);
            }

        } catch (\Exception $e) {
            return $this->sendError('Internal Error', $e->getMessage(), 500);
        }
    }

    public function upload_csv(Request $request)
    {
        try {    
            $sukses = '';
            if (!$request->hasFile('csv_file')) {
                return $this->sendError('No file selected.', 'Error', 500);
            }

            $file = $request->file('csv_file');
            $path = $file->getRealPath();

            if (!file_exists($path)) {
                return $this->sendError('CSV file is empty or only has headers.', 'Error', 500);
            }
            $data = array_map('str_getcsv', file($path));

            if (count($data) < 2) {
                return $this->sendError('CSV file is empty or only has headers.', 'Error', 500);
            }
            // Ambil header dari baris keenam
            $header = array_map('trim', $data[0]);

            $records = [];
            $pengurang = 1;
            for ($i = 1; $i < count($data); $i++) {
                if (count($data[$i]) !== count($header)) continue;
                $row = array_combine($header, $data[$i]);
                $records[] = $row;
                $result = DB::connection("mysqlEntity")
                    ->table('account')
                    ->where('a_type', '=', trim($records[$i - $pengurang]['a_type']))
                    ->where('bank_acc', '=', trim($records[$i - $pengurang]['bank_acc']))
                    ->first();
                if ($result) {
                    DB::connection("mysqlEntity")
                    ->table('account')
                    ->where('a_type', '=', trim($records[$i - $pengurang]['a_type']))
                    ->where('bank_acc', '=', trim($records[$i - $pengurang]['bank_acc']))
                    ->update([
                        'bank_name' => $records[$i - $pengurang]['bank_name'],
                        'ac_num' => $records[$i - $pengurang]['ac_num'],
                        'acc_code' => $records[$i - $pengurang]['acc_code'],
                        'curr_id' => $records[$i - $pengurang]['curr_id'],
                        'saldo' => (float) $records[$i - $pengurang]['saldo'],
                        'posting' => $records[$i - $pengurang]['posting'],
                    ]);
                } else {
                    DB::connection('sqlSrvFPI')->table('account')->insert([
                        'a_type' => $records[$i - $pengurang]['a_type'],
                        'bank_acc' => $records[$i - $pengurang]['bank_acc'],
                        'bank_name' => $records[$i - $pengurang]['bank_name'],
                        'ac_num' => $records[$i - $pengurang]['ac_num'],
                        'acc_code' => $records[$i - $pengurang]['acc_code'],
                        'curr_id' => $records[$i - $pengurang]['curr_id'],
                        'saldo' => (float) $records[$i - $pengurang]['saldo'],
                        'posting' => $records[$i - $pengurang]['posting'],
                    ]);
                }
            }

            return $this->sendResponse('Successfully Created', 200);
        } catch (\Exception $e) {
            return $this->sendError('Internal Error', $e->getMessage(), 500);
        }
    }
}
