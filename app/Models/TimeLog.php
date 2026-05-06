<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeLog extends Model
{
    use HasFactory;
  protected $fillable = [
        'employee_id',
        'date',
        'clock_in',      
        'clock_out',     
        'hours_worked',   
        'overtime',       
        'status',         
        'method',         
    ];
 
    protected $casts = [
        'date'        => 'date:Y-m-d',
        'hours_worked'=> 'decimal:2',
        'overtime'    => 'decimal:2',
    ];
 
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
