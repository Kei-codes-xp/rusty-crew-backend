<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'firstName'     => ['sometimes', 'string', 'max:100'],
            'lastName'      => ['sometimes', 'string', 'max:100'],
            'phone'         => ['sometimes', 'string', 'max:30'],
            'emergency'     => ['sometimes', 'string', 'max:30'],
            'role'          => ['sometimes', 'in:Barista,Cashier,Manager,Admin'],
            'status'        => ['sometimes', 'in:Active,Inactive,Resigned'],
            'hourlyRate'    => ['sometimes', 'numeric', 'min:0'],
            'isSalaried'    => ['sometimes', 'boolean'],
            'monthlySalary' => ['sometimes', 'numeric', 'min:0'],
            'leaveBalance'  => ['sometimes', 'integer', 'min:0'],
            'avatarColor'   => ['sometimes', 'string',  'max:2'],
            'pin'           => ['sometimes', 'digits:4'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data   = parent::validated();
        $mapped = [];

        if (isset($data['firstName']))     $mapped['first_name']        = $data['firstName'];
        if (isset($data['lastName']))      $mapped['last_name']         = $data['lastName'];
        if (isset($data['phone']))         $mapped['phone']             = $data['phone'];
        if (isset($data['emergency']))     $mapped['emergency_contact'] = $data['emergency'];
        if (isset($data['role']))          $mapped['role']              = $data['role'];
        if (isset($data['status']))        $mapped['status']            = $data['status'];
        if (isset($data['hourlyRate']))    $mapped['hourly_rate']       = $data['hourlyRate'];
        if (isset($data['isSalaried']))    $mapped['is_salaried']       = $data['isSalaried'];
        if (isset($data['monthlySalary'])) $mapped['monthly_salary']   = $data['monthlySalary'];
        if (isset($data['leaveBalance']))  $mapped['leave_balance']     = $data['leaveBalance'];
        if (isset($data['avatarColor']))   $mapped['avatar_color']      = $data['avatarColor'];
        if (isset($data['pin']))           $mapped['pin']               = bcrypt($data['pin']);

        return $mapped;
    }
}
