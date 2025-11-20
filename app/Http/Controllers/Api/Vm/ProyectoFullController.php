<?php
namespace App\Http\Controllers\Api\Vm;

use App\Http\Controllers\Controller;
use App\Models\VmProyecto;
use App\Services\Auth\EpScopeService;
use Illuminate\Http\Request;

class ProyectoFullController extends Controller
{
    /**
     * GET /api/vm/proyectos/full
     * Devuelve proyecto + ciclos + procesos + sesiones + imágenes (TODAS), sin Resources.
     * Filtros opcionales:
     *  - estado=PLANIFICADO|EN_CURSO|CERRADO|CANCELADO
     *  - solo_mi_ep_sede=1
     */
    public function index(Request $request)
    {
        $query = VmProyecto::query()
            ->with([
                'ciclos',
                'procesos' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
                'procesos.sesiones' => fn ($q) => $q->orderBy('fecha')->orderBy('hora_inicio'),
                'imagenes' => fn ($q) => $q->orderByDesc('id'), // TODAS las imágenes
            ]);

        if ($estado = $request->get('estado')) {
            $query->where('estado', $estado);
        }

        if ($request->boolean('solo_mi_ep_sede') && $request->user()) {
            $ids = EpScopeService::epSedesIdsManagedBy((int) $request->user()->id);
            $query->whereIn('ep_sede_id', $ids ?: [-1]);
        }

        $proyectos = $query->orderByDesc('id')->get();

        $data = $proyectos->map(function (VmProyecto $p) {
            return [
                'id'       => (int) $p->id,
                'codigo'   => $p->codigo,
                'titulo'   => $p->titulo,
                'descripcion' => $p->descripcion,
                'tipo'     => $p->tipo,
                'modalidad'=> $p->modalidad,
                'estado'   => $p->estado,
                'horas_planificadas'         => (int) $p->horas_planificadas,
                'horas_minimas_participante' => $p->horas_minimas_participante ? (int) $p->horas_minimas_participante : null,

                'ciclos'   => $p->relationLoaded('ciclos')
                    ? $p->ciclos->map(fn ($c) => [
                        'id'         => (int) $c->id,
                        'nivel'      => (int) $c->nivel,
                        'ep_sede_id' => (int) $c->ep_sede_id,
                        'periodo_id' => (int) $c->periodo_id,
                    ])
                    : [],

                'procesos' => $p->relationLoaded('procesos')
                    ? $p->procesos->map(fn ($pr) => [
                        'id'            => (int) $pr->id,
                        'nombre'        => $pr->nombre,
                        'descripcion'   => $pr->descripcion,
                        'tipo_registro' => $pr->tipo_registro,
                        'horas_asignadas' => $pr->horas_asignadas ? (int) $pr->horas_asignadas : null,
                        'nota_minima'   => $pr->nota_minima ? (int) $pr->nota_minima : null,
                        'requiere_asistencia' => (bool) $pr->requiere_asistencia,
                        'estado'        => $pr->estado,
                        'orden'         => $pr->orden ? (int) $pr->orden : null,
                        'sesiones'      => $pr->relationLoaded('sesiones')
                            ? $pr->sesiones->map(fn ($s) => [
                                'id'          => (int) $s->id,
                                'fecha'       => $s->fecha,
                                'hora_inicio' => $s->hora_inicio,
                                'hora_fin'    => $s->hora_fin,
                                'estado'      => $s->estado,
                            ])
                            : [],
                    ])
                    : [],

                'imagenes' => $p->relationLoaded('imagenes')
                    ? $p->imagenes->map(fn ($img) => [
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
     * GET /api/vm/proyectos/{proyecto}/full
     * Un solo proyecto con TODO (árbol completo)
     */
    public function show(VmProyecto $proyecto)
    {
        $proyecto->load([
            'ciclos',
            'procesos' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
            'procesos.sesiones' => fn ($q) => $q->orderBy('fecha')->orderBy('hora_inicio'),
            'imagenes' => fn ($q) => $q->orderByDesc('id'),
        ]);

        $p = $proyecto;

        $out = [
            'id'       => (int) $p->id,
            'codigo'   => $p->codigo,
            'titulo'   => $p->titulo,
            'descripcion' => $p->descripcion,
            'tipo'     => $p->tipo,
            'modalidad'=> $p->modalidad,
            'estado'   => $p->estado,
            'horas_planificadas'         => (int) $p->horas_planificadas,
            'horas_minimas_participante' => $p->horas_minimas_participante ? (int) $p->horas_minimas_participante : null,
            'ciclos'   => $p->ciclos->map(fn ($c) => [
                'id'         => (int) $c->id,
                'nivel'      => (int) $c->nivel,
                'ep_sede_id' => (int) $c->ep_sede_id,
                'periodo_id' => (int) $c->periodo_id,
            ]),
            'procesos' => $p->procesos->map(fn ($pr) => [
                'id'            => (int) $pr->id,
                'nombre'        => $pr->nombre,
                'descripcion'   => $pr->descripcion,
                'tipo_registro' => $pr->tipo_registro,
                'horas_asignadas' => $pr->horas_asignadas ? (int) $pr->horas_asignadas : null,
                'nota_minima'   => $pr->nota_minima ? (int) $pr->nota_minima : null,
                'requiere_asistencia' => (bool) $pr->requiere_asistencia,
                'estado'        => $pr->estado,
                'orden'         => $pr->orden ? (int) $pr->orden : null,
                'sesiones'      => $pr->sesiones->map(fn ($s) => [
                    'id'          => (int) $s->id,
                    'fecha'       => $s->fecha,
                    'hora_inicio' => $s->hora_inicio,
                    'hora_fin'    => $s->hora_fin,
                    'estado'      => $s->estado,
                ]),
            ]),
            'imagenes' => $p->imagenes->map(fn ($img) => [
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
