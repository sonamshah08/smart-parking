<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParkingMaster extends Model
{
    use HasFactory;
    protected $table = 'parking_master';

    protected $fillable = [
        'parking_lot_name',
        'is_active',
        'total_spots'
    ];

    public function parkingSpots()
    {
        return $this->hasMany(ParkingLotMaster::class, 'parking_master_id');
    }

    public function parkingLotMasters()
    {
        return $this->hasMany(ParkingLotMaster::class, 'parking_master_id');
    }
}
