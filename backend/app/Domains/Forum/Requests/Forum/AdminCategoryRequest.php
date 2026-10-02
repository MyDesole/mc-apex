<?php

namespace App\Domains\Forum\Requests\Forum;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class AdminCategoryRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $category = $this->route('category');
        $sometimes = $category ? 'sometimes' : 'required';

        return [
            'name' => [$sometimes, 'string', 'min:2', 'max:96'],
            'slug' => [
                'nullable', 'string', 'max:64',
                $category
                    ? Rule::unique('forum_categories', 'slug')->ignore($category->id)
                    : Rule::unique('forum_categories', 'slug'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:32'],
            'color' => ['nullable', 'string', 'max:16'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'post_policy' => ['nullable', 'in:all,verified,staff'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
