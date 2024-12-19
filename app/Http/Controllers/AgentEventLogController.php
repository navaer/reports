<?php

namespace App\Http\Controllers;

use App\Models\AgentsEventLogs;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AgentEventLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $day = '2024-11-05'; // Cambia por la fecha deseada
        $segments = [];

        $startOfDay = Carbon::parse("$day 00:00:00");
        $endOfDay = Carbon::parse("$day 23:59:59");

        $current = $startOfDay;

        while ($current < $endOfDay) {
            $segmentEnd = (clone $current)->addMinutes(30)->min($endOfDay);

            $segments[] = [
                'segment_start' => $current->toDateTimeString(),
                'segment_end' => $segmentEnd->toDateTimeString(),
            ];

            $current = $segmentEnd;
        }

        $breakEvents = AgentsEventLogs::where('event_type', 'break')
            ->whereDate('event_date', $day)
            ->orderBy('user_id')
            ->orderBy('event_date')
            ->get();

        $breakSegments = [];

        foreach ($breakEvents->groupBy('user_id') as $userId => $userEvents) {
            $ongoingBreak = null;

            foreach ($userEvents as $event) {
                if ($event->event_subtype === 'start') {
                    $ongoingBreak = $event; // Guarda todo el evento de inicio
                } elseif ($event->event_subtype === 'stop' && $ongoingBreak) {
                    $breakStart = Carbon::parse($ongoingBreak->event_date);
                    $breakEnd = Carbon::parse($event->event_date);

                    // Calcula el tiempo total del break
                    $totalBreakDuration = $breakStart->diffInMinutes($breakEnd);

                    foreach ($segments as $segment) {
                        $segmentStart = Carbon::parse($segment['segment_start']);
                        $segmentEnd = Carbon::parse($segment['segment_end']);

                        // Calcula intersección entre intervalo de break y segmento
                        $overlapStart = $segmentStart->max($breakStart);
                        $overlapEnd = $segmentEnd->min($breakEnd);

                        if ($overlapStart < $overlapEnd) {
                            $breakSegments[] = [
                                'user_id' => $userId,
                                'segment_start' => $segmentStart->toDateTimeString(),
                                'segment_end' => $segmentEnd->toDateTimeString(),
                                'break_duration_minutes' => $overlapStart->diffInMinutes($overlapEnd),
                                'start_event_date' => $ongoingBreak->event_date, // Fecha de inicio original
                                'stop_event_date' => $event->event_date,         // Fecha de fin original
                                'total_break_duration' => $totalBreakDuration,   // Tiempo total del break en minutos
                            ];
                        }
                    }

                    $ongoingBreak = null;
                }
            }
        }



        return $breakSegments;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //ho
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function agent_break_time()
    {
        $startDay = '2024-10-29'; // Cambia por la fecha deseada
        $endDay = '2024-11-12';   // Cambia por la fecha deseada

        $segments = [];

        $startOfDay = Carbon::parse("$startDay 00:00:00");
        $endOfDay = Carbon::parse("$endDay 23:59:59");

        $current = $startOfDay;

        // Crear todos los segmentos de media hora
        while ($current < $endOfDay) {
            $segmentEnd = (clone $current)->addMinutes(30)->min($endOfDay);

            $segments[] = [
                'segment_start' => $current->toDateTimeString(),
                'segment_end' => $segmentEnd->toDateTimeString(),
                'break_duration_seconds' => 0,
                'user_ids' => [],
            ];

            $current = $segmentEnd;
        }

        // Obtener eventos de break
        $breakEvents = AgentsEventLogs::where('event_type', 'break')
            ->where('event_date', '>=', $startOfDay)
            ->where('event_date', '<=', $endOfDay)
            ->orderBy('user_id')
            ->orderBy('event_date')
            ->get();

        // Procesar los eventos y asignarlos a los segmentos
        foreach ($breakEvents->groupBy('user_id') as $userId => $userEvents) {
            $ongoingBreak = null;

            foreach ($userEvents as $event) {
                if ($event->event_subtype === 'start') {
                    $ongoingBreak = $event;
                } elseif ($event->event_subtype === 'stop' && $ongoingBreak) {
                    $breakStart = Carbon::parse($ongoingBreak->event_date);
                    $breakEnd = Carbon::parse($event->event_date);

                    foreach ($segments as &$segment) {
                        $segmentStart = Carbon::parse($segment['segment_start']);
                        $segmentEnd = Carbon::parse($segment['segment_end']);

                        // Calcular intersección entre intervalo de break y segmento
                        $overlapStart = $segmentStart->max($breakStart);
                        $overlapEnd = $segmentEnd->min($breakEnd);

                        if ($overlapStart < $overlapEnd) {
                            $timeInSeconds = $overlapStart->diffInSeconds($overlapEnd);
                            $segment['break_duration_seconds'] += $timeInSeconds;

                            // Agregar usuario con tiempo al segmento
                            $userExists = false;
                            foreach ($segment['user_ids'] as &$userData) {
                                if ($userData['user_id'] === $userId) {
                                    $userData['time_seconds'] += $timeInSeconds;
                                    $userExists = true;
                                    break;
                                }
                            }
                            unset($userData);

                            if (!$userExists) {
                                $segment['user_ids'][] = [
                                    'user_id' => $userId,
                                    'time_seconds' => $timeInSeconds,
                                ];
                            }
                        }
                    }

                    unset($segment);
                    $ongoingBreak = null;
                }
            }

            // Si hay un break abierto al inicio del día
            if (!$userEvents->firstWhere('event_subtype', 'start')) {
                foreach ($segments as &$segment) {
                    $segmentStart = Carbon::parse($segment['segment_start']);
                    $segmentEnd = Carbon::parse($segment['segment_end']);

                    if ($segmentStart >= $startOfDay) {
                        $timeInSeconds = $segmentStart->diffInSeconds($segmentEnd);
                        $segment['break_duration_seconds'] += $timeInSeconds;

                        $segment['user_ids'][] = [
                            'user_id' => $userId,
                            'time_seconds' => $timeInSeconds,
                        ];
                    }
                }
                unset($segment);
            }

            // Si hay un break abierto al final del día
            if ($ongoingBreak) {
                foreach ($segments as &$segment) {
                    $breakStart = Carbon::parse($ongoingBreak->event_date);
                    $segmentStart = Carbon::parse($segment['segment_start']);
                    $segmentEnd = Carbon::parse($segment['segment_end']);

                    if ($breakStart < $segmentEnd) {
                        $overlapStart = $segmentStart->max($breakStart);
                        $overlapEnd = $segmentEnd->min($endOfDay);

                        if ($overlapStart < $overlapEnd) {
                            $timeInSeconds = $overlapStart->diffInSeconds($overlapEnd);
                            $segment['break_duration_seconds'] += $timeInSeconds;

                            $segment['user_ids'][] = [
                                'user_id' => $userId,
                                'time_seconds' => $timeInSeconds,
                            ];
                        }
                    }
                }
                unset($segment);
            }
        }

        // Retornar todos los segmentos
        return $segments;
    }

    function sumBreakTimesBySegment($breakSegments)
    {
        $segmentTotals = [];

        foreach ($breakSegments as $segment) {
            $key = $segment['segment_start'] . '-' . $segment['segment_end'];

            if (!isset($segmentTotals[$key])) {
                $segmentTotals[$key] = [
                    'segment_start' => $segment['segment_start'],
                    'segment_end' => $segment['segment_end'],
                    'break_duration_seconds' => 0,
                    'user_ids' => [],
                ];
            }

            // Sumar tiempo en segundos
            $segmentTotals[$key]['break_duration_seconds'] += $segment['break_duration_minutes'] * 60;

            // Evitar duplicar user_id
            if (!in_array($segment['user_id'], $segmentTotals[$key]['user_ids'])) {
                $segmentTotals[$key]['user_ids'][] = $segment['user_id'];
            }
        }

        // Convertir a un arreglo numerado
        return array_values($segmentTotals);
    }

    function sumBreakTimesBySegmentWithUserTimes($breakSegments)
    {
        $segmentTotals = [];

        foreach ($breakSegments as $segment) {
            $key = $segment['segment_start'] . '-' . $segment['segment_end'];

            if (!isset($segmentTotals[$key])) {
                $segmentTotals[$key] = [
                    'segment_start' => $segment['segment_start'],
                    'segment_end' => $segment['segment_end'],
                    'break_duration_seconds' => 0,
                    'user_ids' => [],
                ];
            }

            // Sumar tiempo en segundos al segmento general
            $timeInSeconds = $segment['break_duration_minutes'] * 60;
            $segmentTotals[$key]['break_duration_seconds'] += $timeInSeconds;

            // Agregar o actualizar el tiempo del usuario en este segmento
            $userExists = false;
            foreach ($segmentTotals[$key]['user_ids'] as &$userData) {
                if ($userData['user_id'] === $segment['user_id']) {
                    $userData['time_seconds'] += $timeInSeconds;
                    $userExists = true;
                    break;
                }
            }
            unset($userData);

            if (!$userExists) {
                $segmentTotals[$key]['user_ids'][] = [
                    'user_id' => $segment['user_id'],
                    'time_seconds' => $timeInSeconds,
                ];
            }
        }

        // Convertir a un arreglo numerado
        return array_values($segmentTotals);
    }

    public function exportAgentBreakTimeToCsv()  //ok
    {
        $segments = $this->agent_break_time(); // Llama a la función que genera los segmentos

        // Nombre del archivo CSV
        $fileName = "agent_break_time_" . now()->format('Ymd_His') . ".csv";

        // Ruta para guardar el archivo (puede ser en un almacenamiento temporal)
        $filePath = storage_path("app/public/$fileName");

        // Abrir el archivo en modo escritura
        $file = fopen($filePath, 'w');

        // Encabezados del archivo CSV
        fputcsv($file, ['segment_start', 'segment_end', 'break_duration_seconds']);

        // Escribir cada segmento en el CSV
        foreach ($segments as $segment) {
            fputcsv($file, [
                $segment['segment_start'],
                $segment['segment_end'],
                $segment['break_duration_seconds']
            ]);
        }

        // Cerrar el archivo
        fclose($file);

        // Opcional: Retornar la URL de descarga
        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function agent_connection_time_with_segments()
    {
        $startDay = '2024-10-29'; // Cambia por la fecha deseada
        $endDay = '2024-11-12';   // Cambia por la fecha deseada
        $segments = [];

        $startOfDay = Carbon::parse("$startDay 00:00:00");
        $endOfDay = Carbon::parse("$endDay 23:59:59");

        $current = $startOfDay;

        // Crear segmentos de media hora
        while ($current < $endOfDay) {
            $segmentEnd = (clone $current)->addMinutes(30)->min($endOfDay);

            $segments[] = [
                'segment_start' => $current->toDateTimeString(),
                'segment_end' => $segmentEnd->toDateTimeString(),
            ];

            $current = $segmentEnd;
        }

        // Obtener los eventos de tipo 'join' y 'leave' con subtipo 'voice-campaign'
        $connectionEvents = AgentsEventLogs::whereIn('event_type', ['login', 'logout'])
            ->where('event_subtype', 'main-channel')
            ->where('event_date', '>=', $startOfDay)
            ->where('event_date', '<=', $endOfDay)
            ->orderBy('user_id')
            ->orderBy('event_date')
            ->get();

        $connectionSegments = [];

        foreach ($connectionEvents->groupBy('user_id') as $userId => $userEvents) {
            $ongoingConnection = null;

            foreach ($userEvents as $event) {
                if ($event->event_type === 'login') {
                    $ongoingConnection = $event;
                } elseif ($event->event_type === 'logout' && $ongoingConnection) {
                    $joinTime = Carbon::parse($ongoingConnection->event_date);
                    $leaveTime = Carbon::parse($event->event_date);

                    foreach ($segments as $segment) {
                        $segmentStart = Carbon::parse($segment['segment_start']);
                        $segmentEnd = Carbon::parse($segment['segment_end']);

                        // Calcular intersección entre el intervalo de conexión y el segmento
                        $overlapStart = $segmentStart->max($joinTime);
                        $overlapEnd = $segmentEnd->min($leaveTime);

                        if ($overlapStart < $overlapEnd) {
                            $connectionDuration = $overlapStart->diffInSeconds($overlapEnd);

                            $key = $segment['segment_start'] . '-' . $segment['segment_end'];

                            // Inicializar el segmento si no existe
                            if (!isset($connectionSegments[$key])) {
                                $connectionSegments[$key] = [
                                    'segment_start' => $segment['segment_start'],
                                    'segment_end' => $segment['segment_end'],
                                    'total_connection_seconds' => 0,
                                    'user_ids' => [],
                                ];
                            }

                            // Sumar duración al segmento
                            $connectionSegments[$key]['total_connection_seconds'] += $connectionDuration;

                            // Agregar usuario con su tiempo al segmento
                            $userExists = false;
                            foreach ($connectionSegments[$key]['user_ids'] as &$userData) {
                                if ($userData['user_id'] === $userId) {
                                    $userData['time_seconds'] += $connectionDuration;
                                    $userExists = true;
                                    break;
                                }
                            }
                            unset($userData);

                            if (!$userExists) {
                                $connectionSegments[$key]['user_ids'][] = [
                                    'user_id' => $userId,
                                    'time_seconds' => $connectionDuration,
                                ];
                            }
                        }
                    }

                    $ongoingConnection = null;
                }
            }

            // Si no hay un evento de inicio, asumir conexión desde el inicio del día
            if (!$userEvents->firstWhere('event_type', 'join')) {
                foreach ($segments as $segment) {
                    $segmentStart = Carbon::parse($segment['segment_start']);
                    $segmentEnd = Carbon::parse($segment['segment_end']);

                    if ($segmentStart >= $startOfDay) {
                        $timeInSeconds = $segmentStart->diffInSeconds($segmentEnd);

                        $key = $segment['segment_start'] . '-' . $segment['segment_end'];

                        if (!isset($connectionSegments[$key])) {
                            $connectionSegments[$key] = [
                                'segment_start' => $segment['segment_start'],
                                'segment_end' => $segment['segment_end'],
                                'total_connection_seconds' => 0,
                                'user_ids' => [],
                            ];
                        }

                        $connectionSegments[$key]['total_connection_seconds'] += $timeInSeconds;
                        $connectionSegments[$key]['user_ids'][] = [
                            'user_id' => $userId,
                            'time_seconds' => $timeInSeconds,
                        ];
                    }
                }
            }

            // Si no hay un evento de cierre, asumir conexión hasta el final del día
            if ($ongoingConnection) {
                foreach ($segments as $segment) {
                    $joinTime = Carbon::parse($ongoingConnection->event_date);
                    $segmentStart = Carbon::parse($segment['segment_start']);
                    $segmentEnd = Carbon::parse($segment['segment_end']);

                    if ($joinTime < $segmentEnd) {
                        $overlapStart = $segmentStart->max($joinTime);
                        $overlapEnd = $segmentEnd->min($endOfDay);

                        if ($overlapStart < $overlapEnd) {
                            $timeInSeconds = $overlapStart->diffInSeconds($overlapEnd);

                            $key = $segment['segment_start'] . '-' . $segment['segment_end'];

                            if (!isset($connectionSegments[$key])) {
                                $connectionSegments[$key] = [
                                    'segment_start' => $segment['segment_start'],
                                    'segment_end' => $segment['segment_end'],
                                    'total_connection_seconds' => 0,
                                    'user_ids' => [],
                                ];
                            }

                            $connectionSegments[$key]['total_connection_seconds'] += $timeInSeconds;
                            $connectionSegments[$key]['user_ids'][] = [
                                'user_id' => $userId,
                                'time_seconds' => $timeInSeconds,
                            ];
                        }
                    }
                }
            }
        }

        // Agregar segmentos vacíos con valores en cero
        foreach ($segments as $segment) {
            $key = $segment['segment_start'] . '-' . $segment['segment_end'];

            if (!isset($connectionSegments[$key])) {
                $connectionSegments[$key] = [
                    'segment_start' => $segment['segment_start'],
                    'segment_end' => $segment['segment_end'],
                    'total_connection_seconds' => 0,
                    'user_ids' => [],
                ];
            }
        }

        // Ordenar los segmentos por fecha de inicio
        usort($connectionSegments, function ($a, $b) {
            return strcmp($a['segment_start'], $b['segment_start']);
        });

        return array_values($connectionSegments);
    }

    public function export_connection_time_to_csv() //ok connection
    {
        $connectionSegments = $this->agent_connection_time_with_segments();

        // Nombre del archivo CSV
        $fileName = "connection_time_segments_" . now()->format('Y_m_d_H_i_s') . ".csv";

        // Crear un archivo temporal para escribir el CSV
        $filePath = storage_path("app/public/$fileName");
        $file = fopen($filePath, 'w');

        // Escribir encabezados
        fputcsv($file, ['segment_start', 'segment_end', 'total_connection_seconds']);

        // Escribir datos
        foreach ($connectionSegments as $segment) {
            fputcsv($file, [
                $segment['segment_start'],
                $segment['segment_end'],
                $segment['total_connection_seconds'],
            ]);
        }

        fclose($file);

        // Retornar archivo descargable
        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function export_connection_hours_to_csv()
    {
        $connectionSegments = $this->agent_connection_time_with_segments();

        // Nombre del archivo CSV
        $fileName = "connection_time_segments_" . now()->format('Y_m_d_H_i_s') . ".csv";

        // Crear un archivo temporal para escribir el CSV
        $filePath = storage_path("app/public/$fileName");
        $file = fopen($filePath, 'w');

        // Escribir encabezados
        fputcsv($file, ['segment_start', 'segment_end', 'total_connection_time']);

        // Escribir datos
        foreach ($connectionSegments as $segment) {
            // Convierte los segundos a hora:minutos:segundos
            $totalSeconds = $segment['total_connection_seconds'];
            $hours = floor($totalSeconds / 3600);
            $minutes = floor(($totalSeconds % 3600) / 60);
            $seconds = $totalSeconds % 60;

            // Formato hh:mm:ss
            $formattedTime = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);

            fputcsv($file, [
                $segment['segment_start'],
                $segment['segment_end'],
                $formattedTime, // Se agrega el tiempo en formato hh:mm:ss
            ]);
        }

        fclose($file);

        // Retornar archivo descargable
        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function groupConnectionByDateAgentSegment()
    {
        $segments = $this->agent_connection_time_with_segments(); // Usar la función existente para obtener segmentos
        $groupedData = [];

        foreach ($segments as $segment) {
            $date = Carbon::parse($segment['segment_start'])->format('Y-m-d');

            foreach ($segment['user_ids'] as $userData) {
                $userId = $userData['user_id'];
                $timeSeconds = $userData['time_seconds'];

                // Obtener el nombre completo del agente
                $userFullName = AgentsEventLogs::where('user_id', $userId)->value('user_full_name') ?? 'Unknown';

                // Inicializar la estructura si no existe
                if (!isset($groupedData[$date][$userId])) {
                    $groupedData[$date][$userId] = [
                        'user_full_name' => $userFullName,
                        'segments' => [],
                    ];
                }

                // Agregar el segmento al usuario correspondiente
                $groupedData[$date][$userId]['segments'][] = [
                    'segment_start' => $segment['segment_start'],
                    'segment_end' => $segment['segment_end'],
                    'connection_seconds' => $timeSeconds,
                ];
            }
        }

        return $groupedData;
    }


    public function exportGroupedConnectionToCsv()
    {
        $groupedData = $this->groupConnectionByDateAgentSegment();

        // Nombre del archivo CSV
        $fileName = "grouped_connection_segments_" . now()->format('Y_m_d_H_i_s') . ".csv";

        // Crear un archivo temporal para escribir el CSV
        $filePath = storage_path("app/public/$fileName");
        $file = fopen($filePath, 'w');

        // Escribir encabezados
        fputcsv($file, ['date', 'agent_name', 'segment_start', 'segment_end', 'connection_seconds']);

        // Escribir datos agrupados
        foreach ($groupedData as $date => $agents) {
            foreach ($agents as $agentId => $agentData) {
                foreach ($agentData['segments'] as $segment) {
                    fputcsv($file, [
                        $date,
                        $agentData['user_full_name'],
                        $segment['segment_start'],
                        $segment['segment_end'],
                        $segment['connection_seconds'],
                    ]);
                }
            }
        }

        fclose($file);

        // Retornar archivo descargable
        return response()->download($filePath)->deleteFileAfterSend(true);
    }



    public function agent_break_time_grouped_with_name()
    {
        $startDay = '2024-10-29'; // Cambia por la fecha deseada
        $endDay = '2024-11-04';   // Cambia por la fecha deseada

        $startOfDay = Carbon::parse("$startDay 00:00:00");
        $endOfDay = Carbon::parse("$endDay 23:59:59");

        // Obtener eventos de break
        $breakEvents = AgentsEventLogs::where('event_type', 'break')
            ->where('event_date', '>=', $startOfDay)
            ->where('event_date', '<=', $endOfDay)
            ->orderBy('event_date')
            ->orderBy('user_full_name')
            ->get();

        $groupedData = [];

        foreach (
            $breakEvents->groupBy(function ($event) {
                return Carbon::parse($event->event_date)->format('Y-m-d'); // Agrupar por fecha
            }) as $date => $dailyEvents
        ) {
            foreach ($dailyEvents->groupBy('user_full_name') as $userId => $userEvents) {
                $userFullName = $userEvents->first()->user_full_name ?? "Unknown User"; // Obtener el nombre completo del usuario

                // Crear segmentos de media hora para el día actual
                $startOfDay = Carbon::parse("$date 00:00:00");
                $endOfDay = Carbon::parse("$date 23:59:59");

                $segments = [];
                $current = $startOfDay;
                while ($current < $endOfDay) {
                    $segmentEnd = (clone $current)->addMinutes(30)->min($endOfDay);

                    $segments[] = [
                        'date' => $date,
                        'user_full_name' => $userFullName,
                        'segment_start' => $current->toDateTimeString(),
                        'segment_end' => $segmentEnd->toDateTimeString(),
                        'break_duration_seconds' => 0,
                    ];

                    $current = $segmentEnd;
                }

                // Procesar los eventos de break para este usuario
                $ongoingBreak = null;

                foreach ($userEvents as $event) {
                    if ($event->event_subtype === 'start') {
                        $ongoingBreak = $event;
                    } elseif ($event->event_subtype === 'stop' && $ongoingBreak) {
                        $breakStart = Carbon::parse($ongoingBreak->event_date);
                        $breakEnd = Carbon::parse($event->event_date);

                        foreach ($segments as &$segment) {
                            $segmentStart = Carbon::parse($segment['segment_start']);
                            $segmentEnd = Carbon::parse($segment['segment_end']);

                            // Calcular intersección entre intervalo de break y segmento
                            $overlapStart = $segmentStart->max($breakStart);
                            $overlapEnd = $segmentEnd->min($breakEnd);

                            if ($overlapStart < $overlapEnd) {
                                $segment['break_duration_seconds'] += $overlapStart->diffInSeconds($overlapEnd);
                            }
                        }

                        unset($segment);
                        $ongoingBreak = null;
                    }
                }

                // Manejar breaks abiertos al inicio del día (sin evento 'start')
                if ($userEvents->where('event_subtype', 'start')->isEmpty()) {
                    foreach ($segments as &$segment) {
                        $segment['break_duration_seconds'] += Carbon::parse($segment['segment_start'])->diffInSeconds(Carbon::parse($segment['segment_end']));
                    }
                    unset($segment);
                }

                // Manejar breaks abiertos al final del día (sin evento 'stop')
                if ($ongoingBreak) {
                    $breakStart = Carbon::parse($ongoingBreak->event_date);
                    $breakEnd = Carbon::parse("$date 23:59:59");

                    foreach ($segments as &$segment) {
                        $segmentStart = Carbon::parse($segment['segment_start']);
                        $segmentEnd = Carbon::parse($segment['segment_end']);

                        // Calcular intersección entre intervalo de break y segmento
                        $overlapStart = $segmentStart->max($breakStart);
                        $overlapEnd = $segmentEnd->min($breakEnd);

                        if ($overlapStart < $overlapEnd) {
                            $segment['break_duration_seconds'] += $overlapStart->diffInSeconds($overlapEnd);
                        }
                    }
                    unset($segment);
                }

                // Agregar segmentos al resultado final (excluyendo los de 0 segundos)
                foreach ($segments as $segment) {
                    if ($segment['break_duration_seconds'] > 0) {
                        $groupedData[] = $segment;
                    }
                }
            }
        }

        // Retornar los datos listos para exportar
        return $groupedData;
    }


    public function exportBreakDataToCSV()
    {
        // Obtener los datos procesados
        $breakData = $this->agent_break_time_grouped_with_name();

        // Definir el nombre del archivo CSV
        $filename = 'break_data_' . Carbon::now()->format('Y_m_d_H_i_s') . '.csv';

        // Abrir el archivo en modo de escritura
        $handle = fopen(public_path($filename), 'w');

        // Escribir los encabezados del archivo CSV
        fputcsv($handle, ['date', 'user_full_name', 'segment_start', 'segment_end', 'break_duration_seconds']);

        // Escribir cada línea de datos en el archivo CSV
        foreach ($breakData as $row) {
            fputcsv($handle, $row);
        }

        // Cerrar el archivo después de escribir los datos
        fclose($handle);

        // Retornar el enlace al archivo CSV
        return response()->download(public_path($filename));
    }

    public function groupBreaksByDayAgentSegment()
    {
        $segments = $this->agent_break_time(); // Obtener los segmentos ya procesados

        $groupedData = [];

        foreach ($segments as $segment) {
            // Extraer fecha del segmento
            $date = Carbon::parse($segment['segment_start'])->format('Y-m-d');

            // Procesar cada usuario en el segmento
            foreach ($segment['user_ids'] as $userData) {
                $userId = $userData['user_id'];
                $timeSeconds = $userData['time_seconds'];

                // Obtener información del usuario (agregar full_name si no existe)
                $userFullName = AgentsEventLogs::where('user_id', $userId)->value('user_full_name') ?? 'Unknown';

                // Inicializar la estructura si no existe
                if (!isset($groupedData[$date][$userId])) {
                    $groupedData[$date][$userId] = [
                        'user_full_name' => $userFullName,
                        'segments' => [],
                    ];
                }

                // Agregar el segmento y su duración al usuario correspondiente
                $groupedData[$date][$userId]['segments'][] = [
                    'segment_start' => $segment['segment_start'],
                    'segment_end' => $segment['segment_end'],
                    'break_duration_seconds' => $timeSeconds,
                ];
            }
        }

        return $groupedData;
    }


    public function exportGroupedBreaksToCsv() //ok
    {
        $groupedData = $this->groupBreaksByDayAgentSegment(); // Llama a la función que agrupa los datos

        // Nombre del archivo CSV
        $fileName = "grouped_breaks_" . now()->format('Ymd_His') . ".csv";

        // Ruta para guardar el archivo (puede ser en almacenamiento temporal o público)
        $filePath = storage_path("app/public/$fileName");

        // Abrir el archivo en modo escritura
        $file = fopen($filePath, 'w');

        // Encabezados del archivo CSV
        fputcsv($file, [
            'Date',
            'User ID',
            'User Full Name',
            'Segment Start',
            'Segment End',
            'Break Duration (Seconds)'
        ]);

        // Recorrer los datos agrupados y escribir en el archivo CSV
        foreach ($groupedData as $date => $users) {
            foreach ($users as $userId => $userData) {
                foreach ($userData['segments'] as $segment) {
                    fputcsv($file, [
                        $date,
                        $userId,
                        $userData['user_full_name'],
                        $segment['segment_start'],
                        $segment['segment_end'],
                        $segment['break_duration_seconds'],
                    ]);
                }
            }
        }

        // Cerrar el archivo
        fclose($file);

        // Retornar el archivo para su descarga y eliminarlo después de enviar
        return response()->download($filePath)->deleteFileAfterSend(true);
    }


    public function validateBreakTimeTotals()
    {
        $segments = $this->agent_break_time();
        $groupedData = $this->groupBreaksByDayAgentSegment();

        // Sumar total de agent_break_time
        $originalTotal = array_sum(array_column($segments, 'break_duration_seconds'));

        // Sumar total de groupBreaksByDayAgentSegment
        $groupedTotal = 0;
        foreach ($groupedData as $date => $users) {
            foreach ($users as $userData) {
                foreach ($userData['segments'] as $segment) {
                    $groupedTotal += $segment['break_duration_seconds'];
                }
            }
        }

        return [
            'original_total' => $originalTotal,
            'grouped_total' => $groupedTotal,
            'is_equal' => $originalTotal === $groupedTotal,
        ];
    }


    public function exportAgentReport()
    {
        // Ejecutar el query
        $results = DB::select("
        WITH RECURSIVE date_range AS (
            SELECT '2024-10-29' AS date_start
            UNION ALL
            SELECT DATE_ADD(date_start, INTERVAL 1 DAY)
            FROM date_range
            WHERE date_start < '2024-11-12'  -- Ajusta este rango de fechas según sea necesario
        ),

        all_time_segments AS (
            SELECT
                dr.date_start AS call_date,
                ts.time_start,
                ts.time_end
            FROM
                date_range dr
            CROSS JOIN
                time_segments ts
        )

        SELECT
            ats.call_date,
            c.agent_first_name,
            ats.time_start,
            ats.time_end,
            COALESCE(SUM(c.talk_time >= 0), 0) AS answered,
            COALESCE(SUM(CASE WHEN c.talk_time >= 0 THEN c.talk_time ELSE NULL END), 0) AS sum_talk_time,
            COALESCE(COUNT(c.call_outcome_group), 0) AS call_count,
            COALESCE(AVG(CASE WHEN c.talk_time >= 0 THEN c.talk_time ELSE NULL END), 0) AS avg_talk_time,
            COALESCE(SUM(CASE WHEN c.talk_time >= 0 THEN c.call_length ELSE 0 END), 0) AS sum_call_length_non_system,
            COALESCE(SUM(CASE WHEN c.talk_time >= 0 THEN c.hold_time ELSE 0 END), 0) AS sum_hold_time_non_system,
            COALESCE(SUM(CASE WHEN c.talk_time >= 0 THEN c.wrap_up_time ELSE 0 END), 0) AS sum_wrap_up_time_non_system,
            COALESCE(SUM(CASE WHEN c.talk_time >= 0 THEN c.ring_time ELSE 0 END), 0) AS sum_ring_time_non_system,
            COALESCE(SUM(CASE WHEN c.talk_time >= 0 THEN c.wait_time ELSE 0 END), 0) AS sum_wait_time_non_system,
            COALESCE(COUNT(CASE WHEN c.talk_time >= 0 THEN 1 ELSE NULL END), 0) AS non_system_call_count,
            COALESCE(
                CASE
                    WHEN COUNT(DISTINCT c.agent_id) > 0 THEN COUNT(CASE WHEN c.talk_time >= 0 THEN 1 ELSE NULL END) / COUNT(DISTINCT c.agent_id)
                    ELSE 0
                END, 0
            ) AS avg_non_system_calls_per_agent,
            COALESCE(COUNT(CASE WHEN c.call_type = 'manual' THEN 1 ELSE NULL END), 0) AS call_count_manual,
            COALESCE(SUM(CASE WHEN c.call_type = 'manual' THEN c.talk_time ELSE 0 END), 0) AS sum_talk_time_manual
        FROM
            all_time_segments ats
        LEFT JOIN
            campaigns c
        ON
            TIME(c.call_start) >= ats.time_start
            AND TIME(c.call_start) < ats.time_end
            AND DATE(c.call_start) = ats.call_date
        GROUP BY
            ats.call_date,
            c.agent_first_name,
            ats.time_start,
            ats.time_end
        ORDER BY
            ats.call_date,
            c.agent_first_name,
            ats.time_start
    ");

        // Crear archivo CSV
        $filename = 'agent_report.csv';
        $csvData = [];

        // Encabezados
        $headers = [
            'call_date',
            'agent_first_name',
            'time_start',
            'time_end',
            'answered',
            'sum_talk_time',
            'call_count',
            'avg_talk_time',
            'sum_call_length_non_system',
            'sum_hold_time_non_system',
            'sum_wrap_up_time_non_system',
            'sum_ring_time_non_system',
            'sum_wait_time_non_system',
            'non_system_call_count',
            'call_count_manual',
            'sum_talk_time_manual'

        ];
        $csvData[] = $headers;

        // Agregar los datos al CSV
        foreach ($results as $row) {
            $csvData[] = [
                $row->call_date,
                $row->agent_first_name,
                $row->time_start,
                $row->time_end,
                $row->answered,
                $row->sum_talk_time,
                $row->call_count,
                $row->avg_talk_time,
                $row->sum_call_length_non_system,
                $row->sum_hold_time_non_system,
                $row->sum_wrap_up_time_non_system,
                $row->sum_ring_time_non_system,
                $row->sum_wait_time_non_system,
                $row->non_system_call_count,
                $row->call_count_manual,
                $row->sum_talk_time_manual
            ];
        }

        // Guardar el CSV
        $handle = fopen(storage_path("app/$filename"), 'w');
        foreach ($csvData as $line) {
            fputcsv($handle, $line);
        }
        fclose($handle);

        return response()->download(storage_path("app/$filename"));
    }



    public function exportCallStatisticsToCsv()
    {
        // Construir el query
        $query = "
            WITH RECURSIVE date_range AS (
    SELECT '2024-10-29' AS date_start
    UNION ALL
    SELECT DATE_ADD(date_start, INTERVAL 1 DAY)
    FROM date_range
    WHERE date_start < '2024-11-12' -- Ajusta el rango de fechas aquí
),
all_time_segments AS (
    SELECT
        dr.date_start AS call_date,
        ts.time_start,
        ts.time_end
    FROM
        date_range dr
    CROSS JOIN
        time_segments ts
)
SELECT
    ats.call_date,
    ats.time_start,
    ats.time_end,
    COALESCE(COUNT(c.call_outcome_group), 0) AS call_count,
    COALESCE(SUM(c.call_outcome_group != 'System' AND c.talk_time > 0), 0) AS answered,
    COALESCE(SUM(c.talk_time >= 0), 0) AS sum_talk_time,
    COALESCE(SUM(CASE WHEN c.agent_id > 0 THEN 1 ELSE 0 END), 0) AS answered_agent,
    COALESCE(SUM(CASE WHEN c.call_outcome_name = 'Drop' THEN 1 ELSE 0 END), 0) AS drop_count,
    COALESCE(
        CASE
            WHEN COUNT(c.call_start) > 0 THEN (SUM(CASE WHEN c.call_outcome_name = 'Drop' THEN 1 ELSE 0 END) / COUNT(c.call_start)) * 100
            ELSE 0
        END, 0
    ) AS drop_percentage,
    COALESCE(
        CASE
            WHEN COUNT(c.call_start) > 0 THEN (SUM(c.call_outcome_group != 'System' AND c.talk_time > 0) / COUNT(c.call_start)) * 100
            ELSE 0
        END, 0
    ) AS answered_percentage,
    COALESCE(AVG(CASE WHEN c.call_outcome_group != 'System' THEN c.talk_time ELSE NULL END), 0) AS avg_talk_time,
    COALESCE(COUNT(DISTINCT c.agent_id), 0) AS distinct_agents,
    COALESCE(AVG(CASE WHEN c.call_outcome_group != 'System' THEN c.call_length ELSE NULL END), 0) AS avg_call_length_seconds,
    COALESCE(SUM(CASE WHEN c.call_outcome_name = 'Cancel' THEN 1 ELSE 0 END), 0) AS cancel_count,
    COALESCE(SUM(CASE WHEN c.call_outcome_name = 'ACEPTA RENOVACION CON TELEFÓNICO' THEN 1 ELSE 0 END), 0) AS acepta_count,
    COALESCE(COUNT(CASE WHEN c.call_outcome_group != 'System' THEN 1 ELSE NULL END), 0) AS non_system_call_count,
    COALESCE(
        CASE
            WHEN COUNT(DISTINCT c.agent_id) > 0 THEN COUNT(CASE WHEN c.call_outcome_group != 'System' THEN 1 ELSE NULL END) / COUNT(DISTINCT c.agent_id)
            ELSE 0
        END, 0
    ) AS avg_non_system_calls_per_agent,
    COALESCE(AVG(CASE WHEN c.call_outcome_group != 'System' THEN c.hold_time ELSE NULL END), 0) AS avg_hold_time
FROM
    all_time_segments ats
LEFT JOIN
    campaigns c
ON
    TIME(c.call_start) >= ats.time_start
    AND TIME(c.call_start) < ats.time_end
    AND DATE(c.call_start) = ats.call_date
GROUP BY
    ats.call_date,
    ats.time_start,
    ats.time_end
ORDER BY
    ats.call_date,
    ats.time_start;

        ";

        // Ejecutar el query
        $results = DB::select($query);

        // Crear un archivo CSV
        $csvFileName = 'call_statistics_' . now()->format('Ymd_His') . '.csv';
        $csvFilePath = storage_path("app/public/$csvFileName");

        $fileHandle = fopen($csvFilePath, 'w');

        // Agregar encabezados
        $headers = array_keys((array) $results[0]); // Usamos el primer resultado para los encabezados
        fputcsv($fileHandle, $headers);

        // Agregar datos
        foreach ($results as $row) {
            fputcsv($fileHandle, (array) $row);
        }

        fclose($fileHandle);

        // Retornar el archivo para descarga
        return response()->download($csvFilePath)->deleteFileAfterSend(true);
    }
}
