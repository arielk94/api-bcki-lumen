<?php

namespace App\Http\Controllers\master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesChannelController extends Controller
{
    public function index(Request $request)
    {
        $sqlWhere = "1=1";
        $active_flag = $request->active_flag;

        if ($active_flag) {
            $sqlWhere .= " and active_flag = '$active_flag'";
        }

        $datas = DB::connection('sqlSrvFPI') 
                    ->table('channel_fee_sales')
                    ->selectRaw("channel_id, channel_name, active_flag")
                    ->whereRaw($sqlWhere)
                    ->orderByRaw("CAST(channel_id AS INT) desc")
                    ->get(); 

        return $this->sendResponse($datas, 'Successfully get Sales Channel List');
    }

    private function checkChannel($channel_id)
    {
        return DB::connection('sqlSrvFPI')
            ->table('channel_fee_sales')
            ->where('channel_id', $channel_id);
    }

    public function saveChannel(Request $request)
    {
        try {
            $datas = DB::connection('sqlSrvFPI') 
                ->table('channel_fee_sales')
                ->selectRaw("MAX(CAST(channel_id AS INT)) as max_id")
                ->first(); 
            $next_id = ($datas && $datas->max_id) ? (int)$datas->max_id + 1 : 1;

            $insert = DB::connection('sqlSrvFPI')
                ->table('channel_fee_sales')
                ->insert([
                    'channel_id' => (string)$next_id,
                    'channel_name' => $request->channel_name ? $request->channel_name : ' ',
                    'active_flag' => $request->active_flag ? $request->active_flag : 'Y',
                ]);
            return $this->sendResponse('Success Insert Channel', 'Successfully insert Sales Channel');  
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function updateChannel(Request $request)
    {
        try {
            $channel_id = $request->channel_id;
            $data = $this->checkChannel($channel_id)->first();
                    
            if ($data) {
                DB::connection('sqlSrvFPI')
                    ->table('channel_fee_sales')
                    ->where('channel_id', $channel_id)
                    ->update([
                        'channel_name' => $request->channel_name,
                        'active_flag' => $request->active_flag,
                    ]);
                return $this->sendResponse('Success Update', 'Successfully Update Sales Channel');  
            } else {
                return $this->sendError('Upss Sorry tidak bisa update karena channel tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function switchActive(Request $request)
    {
        try {
            $channel_id = $request->channel_id;
            $active_flag = $request->active_flag;
            $data = $this->checkChannel($channel_id)->first();
            if ($data) {
                DB::connection('sqlSrvFPI')
                    ->table('channel_fee_sales')
                    ->where('channel_id', $channel_id)
                    ->update([
                        'active_flag' => $active_flag,
                    ]);
                return $this->sendResponse('Success Switch Status', 'Successfully Switch Status');
            } else {
                return $this->sendError('Sales Channel not found', 'Error', 404);
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
}
