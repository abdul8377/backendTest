<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EpSedeStaffHistorial extends Model
{
    use HasFactory;

    protected $table = 'ep_sede_staff_historial';

    protected $fillable = [
        'ep_sede_id',
        'user_id',
        'role',
        'evento',
        'desde',
        'hasta',
        'actor_id',
        'motivo',
    ];
}
