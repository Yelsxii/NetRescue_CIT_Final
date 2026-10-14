<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EnrollmentCode extends Model
{
    protected $fillable = ['code', 'active', 'max_uses', 'uses', 'expires_at'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'max_uses' => 'integer',
            'uses' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function getUsableAttribute(): bool
    {
        return $this->active
            && $this->uses < $this->max_uses
            && (!$this->expires_at || $this->expires_at->isFuture());
    }

    public static function current(): self
    {
        $code = static::query()->where('active', true)->latest('id')->first();

        if ($code && $code->usable) {
            return $code;
        }

        return static::issue();
    }

    public static function issue(int $hours = 24, int $maxUses = 50): self
    {
        static::query()->where('active', true)->update(['active' => false]);

        return static::create([
            'code' => 'NR-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)),
            'active' => true,
            'max_uses' => $maxUses,
            'uses' => 0,
            'expires_at' => now()->addHours($hours),
        ]);
    }
}
