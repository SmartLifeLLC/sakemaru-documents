<?php

namespace App\Livewire;

use App\Filament\Resources\BuyerInvoicesResource;
use App\Enums\EMenuCategory;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\SyncRunItemsResource;
use App\Filament\Resources\Users\UserResource;
use Livewire\Component;

class MegaMenu extends Component
{
    /**
     * @return array<int, array{category: EMenuCategory, items: array<int, array{label: string, url: string}>}>
     */
    public function getMenus(): array
    {
        return [
            [
                'category' => EMenuCategory::Documents,
                'items' => [
                    [
                        'label' => '書類一覧',
                        'url' => DocumentResource::getUrl(),
                    ],
                    [
                        'label' => '請求書（酒丸）',
                        'url' => BuyerInvoicesResource::getUrl(),
                    ],
                ],
            ],
            [
                'category' => EMenuCategory::Master,
                'items' => [
                    [
                        'label' => '取引先',
                        'url' => UserResource::getUrl(),
                    ],
                ],
            ],
            [
                'category' => EMenuCategory::System,
                'items' => [
                    [
                        'label' => 'ダッシュボード',
                        'url' => filament()->getUrl(),
                    ],
                    [
                        'label' => '同期明細',
                        'url' => SyncRunItemsResource::getUrl(),
                    ],
                ],
            ],
        ];
    }

    public function render()
    {
        return view('livewire.mega-menu', [
            'menus' => $this->getMenus(),
        ]);
    }
}
