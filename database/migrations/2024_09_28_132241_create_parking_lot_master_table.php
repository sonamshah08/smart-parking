<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parking_lot_master', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_master_id')->constrained('parking_master')->onDelete('cascade');
            $table->integer('parking_spot_no');
            $table->string('vehicle_type');
            $table->string('vehicle_number');
            $table->timestamp('in_time');
            $table->timestamp('out_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parking_lot_master');
    }
};
