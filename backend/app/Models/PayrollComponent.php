<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollComponent extends Model
{
    use BelongsToTenant, SoftDeletes;

    public const BASES = ['basic', 'gross', 'taxable'];

    protected $fillable = [
        'school_id', 'name', 'type', 'calculation', 'base', 'default_amount', 'bands',
        'apply_to_all', 'pre_tax', 'employer_paid',
    ];

    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2', 'apply_to_all' => 'boolean', 'bands' => 'array',
            'pre_tax' => 'boolean', 'employer_paid' => 'boolean',
        ];
    }

    /**
     * The money value this component is worth. $gross and $taxable let a
     * percentage (or a band table) work off something other than basic pay;
     * callers that only know the basic salary keep the original behaviour.
     */
    public function amountFor(float $basic, ?float $override = null, ?float $gross = null, ?float $taxable = null): float
    {
        $gross ??= $basic;
        $taxable ??= $gross;

        if (! empty($this->bands)) {
            return self::progressive($this->bands, $taxable);
        }

        $value = $override ?? (float) $this->default_amount;

        if ($this->calculation !== 'percent') {
            return round($value, 2);
        }

        $base = match ($this->base) {
            'gross' => $gross,
            'taxable' => $taxable,
            default => $basic,
        };

        return round($base * $value / 100, 2);
    }

    /**
     * Marginal (progressive) calculation: each band's rate applies only to
     * the slice of income inside that band. `upto` null means "and above".
     *
     * @param  array<int, array{upto: int|float|null, rate: int|float}>  $bands
     */
    public static function progressive(array $bands, float $income): float
    {
        $tax = 0.0;
        $lower = 0.0;

        foreach ($bands as $band) {
            if ($income <= $lower) {
                break;
            }

            $upper = isset($band['upto']) && $band['upto'] !== null ? (float) $band['upto'] : null;
            $slice = ($upper === null ? $income : min($income, $upper)) - $lower;
            $tax += $slice * (float) $band['rate'] / 100;

            if ($upper === null) {
                break;
            }

            $lower = $upper;
        }

        return round($tax, 2);
    }

    public function isAllowance(): bool
    {
        return $this->type === 'allowance';
    }

    public function hasBands(): bool
    {
        return ! empty($this->bands);
    }
}
