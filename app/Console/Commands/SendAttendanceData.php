<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AttData;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;


class SendAttendanceData extends Command
{
    protected $signature = 'send-attendance-data';

    public function handle()
    {
        // Get only new attendance data from the att_data table
        $data = DB::table('att_punches')
            ->join('hr_employee', 'hr_employee.id', '=', 'att_punches.employee_id')
            ->orderBy('att_punches.id', 'desc')
            ->take(25)
            ->select(
                'att_punches.id',
                'hr_employee.emp_pin',
                'att_punches.punch_time',
                DB::raw("CASE
                            WHEN att_punches.workstate = 0 THEN 'IN'
                            WHEN att_punches.workstate = 1 THEN 'OUT'
                            WHEN att_punches.workstate = 10 THEN 'OUT'
                            WHEN att_punches.workstate = 4 THEN 'IN'
                        END AS work_state"),
                'att_punches.terminal_id'
            )
            ->get();

        $data = $data->sortBy('id')->values()->all();

        // Send the attendance data to the API
        $headers = [
            'Authorization' => 'token f050b3f2beebb64:162bc81f252ba45',
            'Content-Type' => 'application/json'
        ];
        foreach ($data as $attendance) {
            $payload = [
                'employee_field_value' => $attendance->emp_pin,
                'timestamp' => $attendance->punch_time,
                'device_id' => $attendance->terminal_id,
                'log_type' => $attendance->work_state,
                'skip_auto_attendance' => 0
            ];
            $response = Http::withHeaders($headers)->post('http://103.136.41.122/api/method/erpnext.hr.doctype.employee_checkin.employee_checkin.add_log_based_on_employee_field', $payload);

            // If the request is successful, update the data_sent column
            if ($response->successful()) {
                DB::table('att_punches')
                    ->where('id', '=', $attendance->id)
                    ->update(['data_sent' => 1]);
            }
        }
    }
}
