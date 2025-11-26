<?php
namespace Tests\Unit\Support;

use Tests\TestCase;
use App\Support\DateList;
use Illuminate\Support\Collection;

class DateListTest extends TestCase
{
    /** @test */
    public function list_mode_parses_filters_and_uniques()
    {
        $in = [
            'mode'   => 'list',
            'fechas' => ['2025-03-01', '01/03/2025', null, '', '2025-03-01'], // dup + nulos
        ];

        $out = DateList::fromBatchPayload($in);

        $this->assertInstanceOf(Collection::class, $out);
        $this->assertSame(['2025-03-01'], $out->all()); // único y normalizado
    }

    /** @test */
    public function list_mode_ignores_unparsable_dates()
    {
        $in = [
            'mode'   => 'list',
            'fechas' => ['not-a-date', '32/13/2025', '2025-02-28'],
        ];
        $out = DateList::fromBatchPayload($in);
        $this->assertSame(['2025-02-28'], $out->all());
    }

    /** @test */
    public function range_mode_returns_all_days_inclusive_when_no_weekday_filter()
    {
        $in = [
            'mode'          => 'range',
            'fecha_inicio'  => '2025-03-01', // Sábado
            'fecha_fin'     => '2025-03-03', // Lunes
            // sin dias_semana
        ];
        $out = DateList::fromBatchPayload($in);
        $this->assertSame(['2025-03-01','2025-03-02','2025-03-03'], $out->all());
    }

    /** @test */
    public function range_mode_filters_by_weekdays_numbers()
    {
        $in = [
            'mode'          => 'range',
            'fecha_inicio'  => '2025-03-01', // Sábado (6)
            'fecha_fin'     => '2025-03-07', // Viernes (5)
            'dias_semana'   => [1, 3, 5],    // LU(1), MI(3), VI(5)
        ];
        $out = DateList::fromBatchPayload($in);
        $this->assertSame(['2025-03-03','2025-03-05','2025-03-07'], $out->all());
    }

    /** @test */
    public function range_mode_filters_by_weekday_aliases_mixed_languages()
    {
        $in = [
            'mode'          => 'range',
            'fecha_inicio'  => '2025-03-01', // Sábado
            'fecha_fin'     => '2025-03-07', // Viernes
            'dias_semana'   => ['LU','WED','VIE','sun','sab'], // mezcla; map acepta SA/VI/…
        ];
        $out = DateList::fromBatchPayload($in);

        // Espera: LU(3/3), WED(MI=3/5), VIE(5/7) + posibles 'sun','sab' mapeados a 0 y 6
        // Rango 1..7 marzo 2025: 1=SÁ, 2=DO, 3=LU, 4=MA, 5=MI, 6=JU, 7=VI
        // DO(0)=2/3, SAB(6)=1/3
        $this->assertSame([
            '2025-03-01', // sábado (sab)
            '2025-03-02', // domingo (sun)
            '2025-03-03', // lunes (LU)
            '2025-03-05', // miércoles (WED)
            '2025-03-07', // viernes (VIE)
        ], $out->all());
    }

    /** @test */
    public function range_mode_returns_empty_when_missing_bounds()
    {
        $out1 = DateList::fromBatchPayload(['mode'=>'range','fecha_inicio'=>'2025-03-01']);
        $out2 = DateList::fromBatchPayload(['mode'=>'range','fecha_fin'=>'2025-03-02']);
        $this->assertSame([], $out1->all());
        $this->assertSame([], $out2->all());
    }

    /** @test */
    public function range_mode_returns_empty_when_unparsable_bounds()
    {
        $in = ['mode'=>'range','fecha_inicio'=>'bad','fecha_fin'=>'2025-03-02'];
        $this->assertSame([], DateList::fromBatchPayload($in)->all());
    }

    /** @test */
    public function range_mode_start_after_end_yields_empty()
    {
        $in = [
            'mode'=>'range',
            'fecha_inicio'=>'2025-03-10',
            'fecha_fin'=>'2025-03-05',
        ];
        $this->assertSame([], DateList::fromBatchPayload($in)->all());
    }

    /** @test */
    public function default_mode_is_list_when_not_provided()
    {
        $in = ['fechas'=>['2025-01-01','2025-01-02','2025-01-01']];
        $this->assertSame(['2025-01-01','2025-01-02'], DateList::fromBatchPayload($in)->all());
    }
}
