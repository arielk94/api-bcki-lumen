<?php

namespace App\Traits\transaction;

use DB;
use Auth;
use Carbon\Carbon;

trait SuratPerintahKerjaTrait
{

    public function queryGetEmployeUser()
    {
        $group = DB::connection('sqlsrvHrd')
            ->table('employee')
            ->selectRaw("   
                        employee.company_id, 
                        employee.idemployee, 
                        employee.office_id, 
                        employee.dept_id, 
                        employee.division_id, 
                        employee.section_id, 
                        employee.group_id, 
                        employee.name, 
                        employee.address1, 
                        employee.address2, 
                        employee.sex, 
                        employee.child_num, 
                        employee.jabatan, 
                        employee.salesid, 
                        employee.remark,
                        CONCAT(employee.idemployee,' - ',employee.name) AS gabungan
                    ")
            ->where('company_id', 'FDPI')
            ->where('active_flag', '=', 'Y')
            ->get();

        return $group;

    }

}