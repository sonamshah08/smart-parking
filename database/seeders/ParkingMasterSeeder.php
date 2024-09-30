<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ParkingMaster;

class ParkingMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $parkingLots = [
            [
                'parking_lot_name' => 'Main Street Parking',
                'total_spots' => 50,
                'is_active' => true,
            ],
            [
                'parking_lot_name' => 'Downtown Garage',
                'total_spots' => 75,
                'is_active' => true,
            ],
            [
                'parking_lot_name' => 'City Center Parking',
                'total_spots' => 100,
                'is_active' => false,
            ],
        ];

        foreach ($parkingLots as $lot) {
            ParkingMaster::create($lot);
        }
    }
}
