<?php

namespace App\Enums\Recommendation;

enum BookInteractionType: string
{
    case View = 'view';
    case CartAdd = 'cart_add';
}
