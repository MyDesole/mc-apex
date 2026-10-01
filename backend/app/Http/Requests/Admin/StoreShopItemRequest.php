<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreShopItemRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $item = $this->route('shopItem') ?? $this->route('item');
        $sometimes = $item ? 'sometimes' : 'required';

        $types = 'avatar_frame,profile_effect,accent_color,card_background,badge,tier_priority,coin_bundle';

        return [
            'name' => [$sometimes, 'string', 'max:128'],
            'slug' => [
                'nullable', 'string', 'max:64',
                $item
                    ? Rule::unique('shop_items', 'slug')->ignore($item->id)
                    : Rule::unique('shop_items', 'slug'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => [$sometimes, 'in:' . $types],
            'rarity' => ['nullable', 'in:common,rare,epic,legendary'],
            'effect_value' => ['nullable', 'string', 'max:128'],
            'price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_consumable' => ['nullable', 'boolean'],
            'is_repeatable' => ['nullable', 'boolean'],
            'max_quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
