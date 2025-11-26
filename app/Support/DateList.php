<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class DateList
{
    /** Intenta parsear con formatos conocidos (prioriza d/m/Y). Devuelve string Y-m-d o null. */
    private static function parseDateFlexible(?string $raw): ?string
    {
        if (!$raw || trim($raw) === '') return null;
        $raw = trim($raw);

        $formats = [
            'Y-m-d', // ISO
            'd/m/Y', 'd-m-Y', // ES
            'm/d/Y', 'm-d-Y', // US
        ];

        foreach ($formats as $fmt) {
            try {
                // parsea
                $dt = \Carbon\Carbon::createFromFormat($fmt, $raw);
                // valida que NO hubo desbordes ni errores
                $errors = \DateTime::getLastErrors();
                if (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                    return $dt->toDateString();
                }
            } catch (\Throwable) {
                // intenta siguiente formato
            }
        }

        // último intento: solo cadenas ISO-like sin ambigüedad (YYYY-MM-DD o YYYY.MM.DD)
        if (preg_match('/^\d{4}[-.]\d{2}[-.]\d{2}$/', $raw)) {
            try {
                $dt = \Carbon\Carbon::parse($raw);
                // parse simple sin ambigüedad, validamos también errores
                $errors = \DateTime::getLastErrors();
                if (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                    return $dt->toDateString();
                }
            } catch (\Throwable) {}
        }

        return null; // inválida
    }


    /**
     * Expande el payload batch a una lista de fechas (YYYY-MM-DD).
     * @param  array $data
     * @return \Illuminate\Support\Collection<string>
     */
    public static function fromBatchPayload(array $data): Collection
    {
        $mode = (string) ($data['mode'] ?? 'list');

        if ($mode === 'list') {
            return collect($data['fechas'] ?? [])
                ->filter(fn($d) => !is_null($d) && $d !== '')
                ->map(fn($d) => self::parseDateFlexible((string)$d))
                ->filter()    // quita nulls
                ->unique()
                ->values();
        }

        // mode === 'range'
        $fiRaw = $data['fecha_inicio'] ?? null;
        $ffRaw = $data['fecha_fin'] ?? null;

        if (!$fiRaw || !$ffRaw) {
            return collect(); // payload incompleto
        }

        $fiStr = self::parseDateFlexible((string)$fiRaw);
        $ffStr = self::parseDateFlexible((string)$ffRaw);
        if (!$fiStr || !$ffStr) {
            return collect();
        }

        try {
            $fi = Carbon::createFromFormat('Y-m-d', $fiStr)->startOfDay();
            $ff = Carbon::createFromFormat('Y-m-d', $ffStr)->startOfDay();
        } catch (\Throwable) {
            return collect();
        }

        if ($fi->gt($ff)) {
            return collect(); // rango inválido
        }

        // Normaliza días de semana
        $map = [
            'DO'=>0,'DOM'=>0,'SUN'=>0, 0=>0,
            'LU'=>1,'LUN'=>1,'MON'=>1, 1=>1,
            'MA'=>2,'MAR'=>2,'TUE'=>2, 2=>2,
            'MI'=>3,'MIE'=>3,'WED'=>3, 3=>3,
            'JU'=>4,'JUE'=>4,'THU'=>4, 4=>4,
            'VI'=>5,'VIE'=>5,'FRI'=>5, 5=>5,
            'SA'=>6,'SAB'=>6,'SAT'=>6, 6=>6,
        ];

        $diasSemana = collect($data['dias_semana'] ?? [])
            ->map(function ($v) use ($map) {
                $k = is_int($v) ? $v : strtoupper((string)$v);
                return $map[$k] ?? null;
            })
            ->filter(fn($v) => $v !== null)
            ->unique()
            ->values();

        $out = collect();
        foreach (CarbonPeriod::create($fi, $ff) as $day) {
            /** @var CarbonInterface $day */
            if ($diasSemana->isNotEmpty() && !$diasSemana->contains($day->dayOfWeek)) {
                continue;
            }
            $out->push($day->toDateString());
        }

        return $out->unique()->values();
    }
}
