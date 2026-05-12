<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_categories');
    }

    /**
     * Lấy toàn bộ ID con cháu (để chặn circular reference).
     * Dùng iterative BFS thay vì đệ quy để tránh stack overflow.
     */
    public function getDescendantIds(): array
    {
        $descendantIds = [];
        $queue = [$this->id];

        while (! empty($queue)) {
            $currentIds = $queue;
            $queue = [];
            $children = static::whereIn('parent_id', $currentIds)->pluck('id')->toArray();
            foreach ($children as $childId) {
                if (! in_array($childId, $descendantIds)) {
                    $descendantIds[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $descendantIds;
    }

    /**
     * Tính độ sâu của danh mục này trong cây (root = 0).
     */
    public function getDepth(): int
    {
        $depth = 0;
        $current = $this;
        $visited = [$this->id];

        while ($current->parent_id !== null) {
            if (in_array($current->parent_id, $visited)) {
                break;
            }
            $visited[] = $current->parent_id;
            $current = $current->parent;
            if (! $current) {
                break;
            }
            $depth++;
        }

        return $depth;
    }

    /**
     * Lấy chuỗi đường dẫn tên danh mục từ Root tới danh mục hiện tại.
     * VD: Văn học > Tiểu thuyết > Trinh thám
     */
    public function getBreadcrumb(): string
    {
        $names = [$this->name];
        $current = $this->parent;

        while ($current !== null) {
            array_unshift($names, $current->name);
            $current = $current->parent;
        }

        return implode(' > ', $names);
    }

    /**
     * Tìm khoảng cách đến nhánh con sâu nhất của danh mục này (Không có con = 0).
     * Đã tối ưu hiệu năng: Đếm số tầng bằng BFS, loại bỏ hoàn toàn N+1 Query.
     */
    public function getMaxDescendantDepth(): int
    {
        $depth = 0;
        $queue = [$this->id];

        while (! empty($queue)) {
            $queue = static::whereIn('parent_id', $queue)->pluck('id')->toArray();

            if (! empty($queue)) {
                $depth++;
            }
        }

        return $depth;
    }
}
