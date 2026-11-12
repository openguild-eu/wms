<?php

declare(strict_types=1);

namespace Modules\Backorders\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BackorderStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::PartiallyFulfilled => __('Partially fulfilled'),
            self::Fulfilled => __('Fulfilled'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::PartiallyFulfilled => 'warning',
            self::Fulfilled => 'success',
            self::Cancelled => 'gray',
        };
    }
}
