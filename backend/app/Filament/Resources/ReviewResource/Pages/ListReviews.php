<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Enums\Review\ReviewStatus;
use App\Filament\Resources\ReviewResource;
use App\Models\Review;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Tổng đánh giá')
                ->badge(static fn (): int => Review::query()->count())
                ->badgeColor('primary')
                ->deferBadge(),
            'approved' => Tab::make('Đã phê duyệt')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ReviewStatus::APPROVED))
                ->badge(static fn (): int => Review::query()->where('status', ReviewStatus::APPROVED)->count())
                ->badgeColor('success')
                ->deferBadge(),
            'rejected' => Tab::make('Đã từ chối')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ReviewStatus::REJECTED))
                ->badge(static fn (): int => Review::query()->where('status', ReviewStatus::REJECTED)->count())
                ->badgeColor('danger')
                ->deferBadge(),
        ];
    }
}
