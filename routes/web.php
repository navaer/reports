<?php

use App\Http\Controllers\AgentEventLogController;
use App\Models\AgentsEventLogs;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});




Route::get('exportGroupedConnectionToCsv', [AgentEventLogController::class, 'exportGroupedConnectionToCsv']);
Route::get('validateBreakTimeTotals', [AgentEventLogController::class, 'validateBreakTimeTotals']);
Route::get('exportGroupedBreaksToCsv', [AgentEventLogController::class, 'exportGroupedBreaksToCsv']);
Route::get('groupBreaksByDayAgentSegment', [AgentEventLogController::class, 'groupBreaksByDayAgentSegment']);
Route::get('exportCallStatisticsToCsv', [AgentEventLogController::class, 'exportCallStatisticsToCsv']);
Route::get('exportAgentReport', [AgentEventLogController::class, 'exportAgentReport']);
Route::get('exportBreakDataToCSV', [AgentEventLogController::class, 'exportBreakDataToCSV']);
Route::get('agent_break_time_grouped_with_name', [AgentEventLogController::class, 'agent_break_time_grouped_with_name']);
Route::get('export_connection_hours_to_csv', [AgentEventLogController::class, 'export_connection_hours_to_csv']);
Route::get('export_connection_time_to_csv', [AgentEventLogController::class, 'export_connection_time_to_csv']);
Route::get('agent_connection_time_with_segments', [AgentEventLogController::class, 'agent_connection_time_with_segments']);
Route::get('exportAgentBreakTimeToCsv', [AgentEventLogController::class, 'exportAgentBreakTimeToCsv']);
Route::get('agent_break_time', [AgentEventLogController::class, 'agent_break_time']);
Route::resource('logs', AgentEventLogController::class);


