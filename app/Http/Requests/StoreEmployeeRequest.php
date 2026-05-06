<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'firstName'     => ['required', 'string', 'max:100'],
            'lastName'      => ['required', 'string', 'max:100'],
            'email'         => ['required', 'email',  'unique:employees,email'],
            'phone'         => ['required', 'string', 'max:30'],
            'emergency'     => ['required', 'string', 'max:30'],
            'role'          => ['required', 'in:Barista,Cashier,Manager,Admin'],
            'status'        => ['sometimes', 'in:Active,Inactive,Resigned'],
            'hourlyRate'    => ['required', 'numeric', 'min:0'],
            'isSalaried'    => ['required', 'boolean'],
            'monthlySalary' => ['required_if:isSalaried,true', 'numeric', 'min:0'],
            'pin'           => ['nullable','digits:4'],
            'password'      => ['sometimes', 'nullable', 'string', 'min:8'],
            'avatarColor'   => ['sometimes', 'string', 'max:2'],
        ];
    }


    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        return [
            'first_name'        => $data['firstName'],
            'last_name'         => $data['lastName'],
            'email'             => $data['email'],
            'phone'             => $data['phone'],
            'emergency_contact' => $data['emergency'],
            'role'              => $data['role'],
            'status'            => $data['status']        ?? 'Active',
            'hourly_rate'       => $data['hourlyRate'],
            'is_salaried'       => $data['isSalaried'],
            'monthly_salary'    => $data['monthlySalary'] ?? 0,
            'pin'               => bcrypt($data['pin']),
            'password'          => isset($data['password']) ? bcrypt($data['password']) : null,
            'avatar_color'      => $data['avatarColor']   ?? '0',
        ];
    }
}
