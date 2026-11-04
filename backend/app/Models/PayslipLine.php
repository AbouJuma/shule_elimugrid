<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayslipLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['payslip_id', 'name', 'type', 'amount', 'is_employer'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_employer' => 'boolean'];
    }
}
