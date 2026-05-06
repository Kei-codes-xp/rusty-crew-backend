<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EmployeeController extends Controller
{

    public function index(): JsonResponse
    {
        $employees = Employee::orderBy('first_name')->get();
        return response()->json(EmployeeResource::collection($employees));
    }


    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['qr_token'] = Str::random(64);

        $employee = Employee::create($data);

        return response()->json(new EmployeeResource($employee), 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        return response()->json(new EmployeeResource($employee));
    }


    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee->update($request->validated());
        return response()->json(new EmployeeResource($employee->fresh()));
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->update(['status' => 'Resigned']);
        return response()->json(new EmployeeResource($employee->fresh()));
    }

    // public function qrCode(Employee $employee)
    // {
    //     $png = QrCode::format('png')
    //                  ->size(300)
    //                  ->errorCorrection('H')
    //                  ->generate($employee->qr_token);

    //     return response($png, 200)->header('Content-Type', 'image/png');
    // }

    public function qrCode(Employee $employee)
    {
        $url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data="
            . urlencode($employee->qr_token);

        return redirect()->away($url);
    }

    public function qrToken(Employee $employee): JsonResponse
    {
        return response()->json(['qrToken' => $employee->qr_token]);
    }
}
