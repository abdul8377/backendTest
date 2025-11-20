<?php
namespace App\Http\Controllers\Api\Vm;

use App\Http\Controllers\Controller;
use App\Models\VmEvento;
use App\Services\Auth\EpScopeService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EventoFullController extends Controller
{
    /**
     * GET /api/vm/eventos/full
     * Devuelve TODOS los campos + sesiones + imágenes, sin Resources.
     * Filtros opcionales:
     *   - estado=PLANIFICADO|EN_CURSO|CERRADO|CANCELADO
     *   - solo_mi_ep_sede=1
     *   - inscribibles=1 (solo ventana abierta HOY y requiere_inscripcion)
     */
    public function index(Request $request)
    {
        $query = VmEvento::query()
            ->with([
                'periodo',
                'categoria',
                'sesiones' => fn ($q) => $q->orderBy('fecha')->orderBy('hora_inicio'),
                'imagenes' => fn ($q) => $q->orderByDesc('id'), // TODAS las imágenes
            ]);

        if ($estado = $request->get('estado')) {
            $query->where('estado', $estado);
        }

        if ($request->boolean('solo_mi_ep_sede') && $request->user()) {
            $epSedeId = EpScopeService::epSedeIdForUser($request->user()->id);
            $query->where('targetable_type', 'ep_sede')
                  ->where('targetable_id', $epSedeId);
        }

        // Si piden solo "inscribibles", filtra por ventana abierta y requiere_inscripcion
        if ($request->boolean('inscribibles')) {
            $hoy = Carbon::today()->toDateString();
            $query->where('requiere_inscripcion', true)
                  ->where(function ($q) use ($hoy) {
                      $q->whereNull('inscripcion_desde')->orWhere('inscripcion_desde', '<=', $hoy);
                  })
                  ->where(function ($q) use ($hoy) {
                      $q->whereNull('inscripcion_hasta')->orWhere('inscripcion_hasta', '>=', $hoy);
                  });
        }

        // Puedes paginar o traer todo
        $eventos = $query->orderByDesc('id')->get();

        // Armar el árbol manualmente (sin resources)
        $data = $eventos->map(function (VmEvento $e) {
            return [
                'id'                   => (int) $e->id,
                'codigo'               => $e->codigo,
                'titulo'               => $e->titulo,
                'subtitulo'            => $e->subtitulo,
                'descripcion_corta'    => $e->descripcion_corta,
                'descripcion_larga'    => $e->descripcion_larga,
                'modalidad'            => $e->modalidad,
                'lugar_detallado'      => $e->lugar_detallado,
                'url_imagen_portada'   => $e->url_imagen_portada,
                'url_enlace_virtual'   => $e->url_enlace_virtual,
                'requiere_inscripcion' => (bool) $e->requiere_inscripcion,
                'cupo_maximo'          => $e->cupo_maximo ? (int) $e->cupo_maximo : null,
                'inscripcion_desde'    => $e->inscripcion_desde,
                'inscripcion_hasta'    => $e->inscripcion_hasta,
                'estado'               => $e->estado,
                'periodo'              => $e->relationLoaded('periodo') ? [
                    'id'     => (int) $e->periodo->id,
                    'codigo' => $e->periodo->codigo ?? null,
                    'anio'   => $e->periodo->anio ?? null,
                    'ciclo'  => $e->periodo->ciclo ?? null,
                ] : null,
                'categoria'            => $e->relationLoaded('categoria') ? [
                    'id'          => (int) $e->categoria->id,
                    'nombre'      => $e->categoria->nombre,
                    'descripcion' => $e->categoria->descripcion,
                ] : null,
                'sesiones'             => $e->relationLoaded('sesiones')
                    ? $e->sesiones->map(fn ($s) => [
                        'id'          => (int) $s->id,
                        'fecha'       => $s->fecha,
                        'hora_inicio' => $s->hora_inicio,
                        'hora_fin'    => $s->hora_fin,
                        'estado'      => $s->estado,
                    ])
                    : [],
                'imagenes'             => $e->relationLoaded('imagenes')
                    ? $e->imagenes->map(fn ($img) => [
                        'id'          => (int) $img->id,
                        'url'         => $img->url,
                        'disk'        => $img->disk,
                        'path'        => $img->path,
                        'titulo'      => $img->titulo,
                        'visibilidad' => $img->visibilidad,
                    ])
                    : [],
            ];
        });

        return response()->json(['ok' => true, 'data' => $data], 200);
    }

    /**
     * GET /api/vm/eventos/{evento}/full
     * Un solo evento con TODO (árbol completo)
     */
    public function show(VmEvento $evento)
    {
        $evento->load([
            'periodo',
            'categoria',
            'sesiones' => fn ($q) => $q->orderBy('fecha')->orderBy('hora_inicio'),
            'imagenes' => fn ($q) => $q->orderByDesc('id'),
        ]);

        $e = $evento;

        $out = [
            'id'                   => (int) $e->id,
            'codigo'               => $e->codigo,
            'titulo'               => $e->titulo,
            'subtitulo'            => $e->subtitulo,
            'descripcion_corta'    => $e->descripcion_corta,
            'descripcion_larga'    => $e->descripcion_larga,
            'modalidad'            => $e->modalidad,
            'lugar_detallado'      => $e->lugar_detallado,
            'url_imagen_portada'   => $e->url_imagen_portada,
            'url_enlace_virtual'   => $e->url_enlace_virtual,
            'requiere_inscripcion' => (bool) $e->requiere_inscripcion,
            'cupo_maximo'          => $e->cupo_maximo ? (int) $e->cupo_maximo : null,
            'inscripcion_desde'    => $e->inscripcion_desde,
            'inscripcion_hasta'    => $e->inscripcion_hasta,
            'estado'               => $e->estado,
            'periodo'              => $e->relationLoaded('periodo') ? [
                'id'     => (int) $e->periodo->id,
                'codigo' => $e->periodo->codigo ?? null,
                'anio'   => $e->periodo->anio ?? null,
                'ciclo'  => $e->periodo->ciclo ?? null,
            ] : null,
            'categoria'            => $e->relationLoaded('categoria') ? [
                'id'          => (int) $e->categoria->id,
                'nombre'      => $e->categoria->nombre,
                'descripcion' => $e->categoria->descripcion,
            ] : null,
            'sesiones'             => $e->sesiones->map(fn ($s) => [
                'id'          => (int) $s->id,
                'fecha'       => $s->fecha,
                'hora_inicio' => $s->hora_inicio,
                'hora_fin'    => $s->hora_fin,
                'estado'      => $s->estado,
            ]),
            'imagenes'             => $e->imagenes->map(fn ($img) => [
                'id'          => (int) $img->id,
                'url'         => $img->url,
                'disk'        => $img->disk,
                'path'        => $img->path,
                'titulo'      => $img->titulo,
                'visibilidad' => $img->visibilidad,
            ]),
        ];

        return response()->json(['ok' => true, 'data' => $out], 200);
    }
}
