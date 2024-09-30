<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParkingLotMaster extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'parking_lot_master';

    protected $fillable = [
        'parking_master_id',
        'parking_spot_no',
        'vehicle_type',
        'vehicle_number',
        'in_time',
        'out_time',
    ];

    protected $dates = ['deleted_at'];


    public function parkingLot()
    {
        return $this->belongsTo(ParkingMaster::class, 'parking_master_id');
    }
}
