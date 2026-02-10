<?php

namespace App\Livewire;

use App\Enums\EMenuCategory;
use App\Filament\Resources\Documents\DocumentResource;
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
