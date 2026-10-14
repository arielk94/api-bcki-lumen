<?php

namespace App\Traits;

use DB;
use Auth;
use Carbon\Carbon;

trait SendNotificationAppBarTrait
{
    public function sendNotifLonceng($deptId,$notif_title,$notif_message,$appl_id,$menu_id)
    {
        $id_notif = $this->generateIdNotif();

        $data = DB::connection("sqlsrv")
            ->table('notification')
            ->insert([
                'entity_id' => 'FDPI',
                'notif_id' => $id_notif,
                'dept_id' => $deptId,
                'notif_title' => $notif_title,
                'notif_message' => $notif_message,
                'created_at' => Carbon::now(),
                'appl_id' => $appl_id,
                'menu_id' => $menu_id
            ]);
        
        return $this->sendResponse($data, 'Successfully Send Notification');
    }
    private function generateIdNotif(){
        $dateYear = date("Y");
        $dateMonth = date("m");
        $dateDay = date("d");

        $getLastIdNotif = DB::connection('sqlsrv')
            ->table('notification')
            ->where('notif_id', 'like', "%" . $dateYear . $dateMonth . $dateDay . "%")
            ->max('notif_id');

        if ($getLastIdNotif) {
            $lastId = substr($getLastIdNotif, -4);
            $next_id = (int) $lastId + 1;
            $seq = str_pad($next_id, 4, '0', STR_PAD_LEFT);
            $getIdNotif =  $dateYear . $dateMonth . $dateDay . $seq;
        } else {
            $getIdNotif = $dateYear . $dateMonth . $dateDay . '0001';
        }

        return $getIdNotif; // ← return string, bukan array
    }
}