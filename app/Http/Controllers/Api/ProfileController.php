<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(new EmployeeResource($request->user()));
    }

    // PATCH /api/employee/profile
    // Only allows updating safe personal fields
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'phone'     => 'sometimes|string|max:30',
            'emergency' => 'sometimes|string|max:30',
            'pin'       => 'sometimes|digits:4',
        ]);

        $emp  = $request->user();
        $data = [];

        if ($request->has('phone'))     $data['phone']             = $request->phone;
        if ($request->has('emergency')) $data['emergency_contact'] = $request->emergency;
        if ($request->has('pin'))       $data['pin']               = Hash::make($request->pin);

        $emp->update($data);

        return response()->json(new EmployeeResource($emp->fresh()));
    }
}
