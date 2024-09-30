<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ParkingMaster;
use App\Models\ParkingLotMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Parking Spot API",
 *      description="API documentation for Parking Lot Management System",
 *      @OA\Contact(
 *          email="support@example.com"
 *      ),
 *      @OA\License(
 *          name="Apache 2.0",
 *          url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *      )
 * )
 *
 * @OA\Server(
 *      url="http://localhost:8000/api",
 *      description="Local Development Server"
 * )
 *
 * @OA\Tag(
 *     name="Parking Management",
 *     description="API Endpoints related to Parking Management"
 * )
 */
class ParkingLotcopyController extends Controller
{
    /**
     * @OA\Info(
     *     title="Parking Lot API",
     *     version="1.0.0",
     *     description="API Documentation for Parking Lot Management"
     * )
     */

    /**
     * @OA\Get(
     *     path="/parking-lot",
     *     summary="Get availability for all parking lots",
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="parking_lot_id", type="integer"),
     *                 @OA\Property(property="total_spots", type="integer"),
     *                 @OA\Property(property="occupied_spots", type="integer"),
     *                 @OA\Property(property="available_spots", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function getAvailability()
    {
        try {
            $cacheKey = 'parking_lot_availability';
            // Cache for 10 minutes (600 seconds)
            $parkingLotAvailability = Cache::remember($cacheKey, 600, function () {
                                                        $parkingLots = ParkingMaster::withCount(['parkingLotMasters as occupied_spots' => function ($query) {
                                                            $query->whereNull('out_time');
                                                        }])
                                                        ->where('is_active', true)
                                                        ->get();

                return $parkingLots->map(function ($parkingLot) {
                    $availableSpotsCount = $parkingLot->total_spots - $parkingLot->occupied_spots;
                    return [
                        'parking_lot_id' => $parkingLot->id,
                        'total_spots' => $parkingLot->total_spots,
                        'occupied_spots' => $parkingLot->occupied_spots,
                        'available_spots' => $availableSpotsCount,
                    ];
                });
            });
            return response()->json([
                'status' => 200,
                'message' => 'Available parking lots',
                'data' => [
                    'parking_lots' => $parkingLotAvailability
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving parking lot availability.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/parking-lot/{parkingMasterId}",
     *     summary="Get parking lot status by ID",
     *     @OA\Parameter(
     *         name="parkingMasterId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Parking Lot Status",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="parking_lot_id", type="integer"),
     *             @OA\Property(property="total_spots", type="integer"),
     *             @OA\Property(property="available_spots", type="integer"),
     *             @OA\Property(property="occupied_spots", type="integer"),
     *             @OA\Property(property="spots", type="array", @OA\Items(
     *                 @OA\Property(property="spot_no", type="integer"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="vehicle_type", type="string"),
     *                 @OA\Property(property="vehicle_number", type="string"),
     *                 @OA\Property(property="in_time", type="string")
     *             ))
     *         )
     *     )
     * )
     */
   public function getParkingLotStatusById($parkingMasterId)
    {
        try {
            $parkingMaster = ParkingMaster::findOrFail($parkingMasterId);
            $totalSpots = $parkingMaster->total_spots;
            $parkingLotStatus = [];
            //Check for each spot and check if it's occupied or available
            for ($spotNo = 1; $spotNo <= $totalSpots; $spotNo++) {
                $spot = ParkingLotMaster::where('parking_master_id', $parkingMasterId)
                    ->where('parking_spot_no', $spotNo)
                    ->whereNull('out_time')
                    ->first();

                //if Condition - if spot is occupied
                if ($spot) {
                    $parkingLotStatus[] = [
                        'spot_no' => $spotNo,
                        'status' => 'occupied',
                        'vehicle_type' => $spot->vehicle_type,
                        'vehicle_number' => $spot->vehicle_number,
                        'in_time' => $spot->in_time,
                    ];
                } else {
                    // Spot is available
                    $parkingLotStatus[] = [
                        'spot_no' => $spotNo,
                        'status' => 'available',
                    ];
                }
            }
            return response()->json([
                'status' => 200,
                'message' => 'Available parking lots',
                'data' => [
                            'parking_lot_id' => $parkingMasterId,
                            'total_spots' => $totalSpots,
                            'available_spots' => $totalSpots - count(array_filter($parkingLotStatus, fn($spot) => $spot['status'] === 'occupied')),
                            'occupied_spots' => count(array_filter($parkingLotStatus, fn($spot) => $spot['status'] === 'occupied')),
                            'spots' => $parkingLotStatus
                            ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while retrieving the parking lot status.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/parking-spot/{spot_id}/park",
     *     summary="Park a vehicle",
     *     @OA\Parameter(
     *         name="spot_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"vehicle_type", "vehicle_number", "parking_master_id"},
     *             @OA\Property(property="vehicle_type", type="string"),
     *             @OA\Property(property="vehicle_number", type="string"),
     *             @OA\Property(property="parking_master_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Vehicle parked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
      // Import Cache Facade

    public function park(Request $request, $spot_id)
    {
        $rules = [
            'vehicle_type' => "required|string|max:255",
            'vehicle_number' => "required|string|max:50",
            'parking_master_id' => "required|integer|exists:parking_master,id",
        ];
        $validation = Validator::make($request->all(), $rules);
        if ($validation->fails()) {
            return response()->json(['errors' => $validation->errors()], 400);
        }

        $parkingMasterId = $request->parking_master_id;
        $vehicleNumber = $request->vehicle_number;
        $vehicleType = $request->vehicle_type;

        try {
            return DB::transaction(function () use ($spot_id, $parkingMasterId, $vehicleNumber, $vehicleType,$request) {
                $parkingMaster = Cache::remember("parking_master_{$parkingMasterId}", 60, function () use ($parkingMasterId) {
                    return ParkingMaster::findOrFail($parkingMasterId);
                });
                $totalSpots = $parkingMaster->total_spots;
                // Check if the requested spot is valid
                if ($spot_id > $totalSpots || $spot_id <= 0) {
                    //return response()->json(['message' => "Invalid spot number. This parking lot only has spots from 1 to $totalSpots."], 400);
                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid spot number. This parking lot only has spots from 1 to $totalSpots.',
                    ]);
                }
                // Check if the vehicle is already parked
                $existingVehicle = ParkingLotMaster::where('parking_master_id', $parkingMasterId)
                    ->where('vehicle_number', $vehicleNumber)
                    ->whereNull('out_time')
                    ->first();
                if ($existingVehicle) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'This vehicle is already parked in the lot.',
                    ]);
                    //return response()->json(['message' => 'This vehicle is already parked in the lot.'], 400);
                }
                // Count occupied spots using a more efficient query
                $occupiedSpots = ParkingLotMaster::where('parking_master_id', $parkingMasterId)
                    ->whereNull('out_time')
                    ->count();
                if ($occupiedSpots >= $totalSpots) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Parking Full. No available parking space.',
                    ]);
                    //return response()->json(['message' => 'Parking Full. No available parking space.'], 400);
                }
                // Handle van parking (3 consecutive spots required)
                if ($vehicleType === 'van') {
                    $startSpotId = $spot_id;
                    $forwardAvailable = $this->checkConsecutiveSpots($parkingMasterId, $startSpotId, 3);

                    if (!$forwardAvailable) {
                        $backwardAvailable = $this->checkConsecutiveSpots($parkingMasterId, $startSpotId, -3);
                        if (!$backwardAvailable) {
                            //return response()->json(['message' => 'No available parking spots for a van.'], 400);
                            return response()->json([
                                'status' => 400,
                                'message' => 'No available parking spots for a van.',
                            ]);
                        } else {
                            $this->parkVan(request: $request, parkingMasterId: $parkingMasterId, spots: $backwardAvailable);
                        }
                    } else {
                        $this->parkVan(request: $request, parkingMasterId: $parkingMasterId, spots: $forwardAvailable);
                    }

                    // Invalidate cache after parking the van
                    Cache::forget('parking_lot_availability');
                    return response()->json([
                        'status' => 200,
                        'message' => 'Van parked successfully.',
                    ]);
                    //return response()->json(data: ['message' => 'Van parked successfully.'], status: 200);
                }

                // Check if the requested spot is already taken
                $spot = ParkingLotMaster::where('parking_master_id', $parkingMasterId)
                    ->where('parking_spot_no', $spot_id)
                    ->first();

                if ($spot) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'No available parking spot.',
                    ]);
                    //return response()->json(['message' => 'No available parking spot.'], 400);
                }

                // Create a new parking entry for a regular vehicle
                $parkingLot = new ParkingLotMaster([
                    'parking_master_id' => $parkingMasterId,
                    'parking_spot_no' => $spot_id,
                    'vehicle_type' => $vehicleType,
                    'vehicle_number' => $vehicleNumber,
                    'in_time' => now(),
                ]);
                $parkingLot->save();

                // Invalidate cache after successful parking
                Cache::forget('parking_lot_availability');

                return response()->json([
                    'status' => 200,
                    'message' => 'Vehicle parked successfully',
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error during parking operation: '.$e->getMessage(), ['stack' => $e->getTraceAsString()]);
            return response()->json(['message' => 'An error occurred while parking the vehicle.', 'error' => 'Something went wrong. Please try again later.'], 500);
        }
    }

    private function checkConsecutiveSpots($parkingMasterId, $startSpotId, $range)
{
    // Get the total number of spots in this parking lot
    $parkingMaster = ParkingMaster::findOrFail($parkingMasterId);
    $totalSpots = $parkingMaster->total_spots;

    $occupiedSpots = ParkingLotMaster::where('parking_master_id', $parkingMasterId)
        ->whereNull('out_time')
        ->pluck('parking_spot_no')
        ->toArray();

    $consecutiveSpots = [];
    $step = $range > 0 ? 1 : -1;
    $limit = abs($range);

    for ($i = 0; $i < $limit; $i++) {
        //current spot ID based on the starting ID and step direction
        $currentSpotId = $startSpotId + ($i * $step);
        // Check if current spot ID is within valid range
        if ($currentSpotId <= 0 || $currentSpotId > $totalSpots) {
            return false; // Spot out of bounds
        }
        // Check if the spot is occupied
        if (in_array($currentSpotId, $occupiedSpots)) {
            return false; // Spot is occupied
        }
        $consecutiveSpots[] = $currentSpotId;
    }

    return $consecutiveSpots;
    }

    private function parkVan($request, $parkingMasterId, $spots)
    {
        DB::transaction(function () use ($request, $parkingMasterId, $spots) {
            $inTime = Carbon::now();
            $parkingLotData = [];
            foreach ($spots as $spotNo) {
                $parkingLotData[] = [
                    'parking_master_id' => $parkingMasterId,
                    'parking_spot_no' => $spotNo,
                    'vehicle_type' => $request->vehicle_type,
                    'vehicle_number' => $request->vehicle_number,
                    'in_time' => $inTime,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            ParkingLotMaster::insert($parkingLotData);
        });
    }


    /**
 * @OA\Delete(
 *     path="/parking-spot/{spot_id}/unpark",
 *     summary="Unpark a vehicle from a specific spot",
 *     description="Marks the vehicle as unparked by setting the out_time and soft deleting the parking record.",
 *     @OA\Parameter(
 *         name="spot_id",
 *         in="path",
 *         required=true,
 *         description="The ID of the parking spot",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"parking_master_id"},
 *             @OA\Property(property="parking_master_id", type="integer", description="The ID of the parking lot master")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Vehicle unparked successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Vehicle unparked successfully. Spot is now free.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="No vehicle found or already unparked",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="No vehicle found in this spot or it has already been unparked.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Server error while unparking the vehicle",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="An error occurred while unparking the vehicle.")
 *         )
 *     ),
 *     tags={"Parking Management"}
 * )
 */
    public function unpark($spot_id, Request $request)
    {
        $parkingMasterId = $request->parking_master_id;
        try {
            $parkingLot = ParkingLotMaster::where('parking_master_id', $parkingMasterId)
                ->where('parking_spot_no', $spot_id)
                ->whereNull('out_time')
                ->first();
            if (!$parkingLot) {
                return response()->json(['message' => 'No vehicle found in this spot or it has already been unparked.'], 400);
            }
            $parkingLot->out_time = now();
            $parkingLot->save();
            $parkingLot->delete();

            Cache::forget('parking_lot_availability');
            //return response()->json(['message' => 'Vehicle unparked successfully. Spot is now free.'], 200);
            return response()->json([
                'status' => 200,
                'message' => 'Vehicle unparked successfully. Spot is now free',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while unparking the vehicle.', 'error' => $e->getMessage()], 500);
        }
    }

}
