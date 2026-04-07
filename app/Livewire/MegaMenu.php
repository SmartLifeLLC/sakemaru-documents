<?php

namespace App\Livewire;

use App\Enums\EMenuCategory;
use App\Models\ClientSetting;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Livewire\Component;

class MegaMenu extends Component
{
    public array $menuStructure = [];

    public string $systemDateDisplay = '';

    public string $systemDayOfWeek = '';

    public function mount()
    {
        $this->menuStructure = $this->buildMenuStructure();

        $systemDate = ClientSetting::systemDate();
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        $this->systemDateDisplay = $systemDate ? $systemDate->format('m.d') : '';
        $this->systemDayOfWeek = $systemDate ? $weekdays[$systemDate->dayOfWeek] : '';
    }

    public function render()
    {
        return view('livewire.mega-menu');
    }

    public function getUserMenuItems(): array
    {
        return Filament::getUserMenuItems();
    }

    protected function buildMenuStructure(): array
    {
        $navigation = Filament::getNavigation();

        $tabs = [
            'documents' => [
                'label' => '帳票',
                'icon' => 'fa-file-lines',
                'categories' => [
                    EMenuCategory::Documents,
                ],
            ],
            'master' => [
                'label' => 'マスタ',
                'icon' => 'fa-database',
                'categories' => [
                    EMenuCategory::Master,
                ],
            ],
            'system' => [
                'label' => 'システム',
                'icon' => 'fa-gear',
                'categories' => [
                    EMenuCategory::System,
                ],
            ],
        ];

        $structure = [];

        foreach ($tabs as $key => $tab) {
            $groupsForTab = [];

            foreach ($tab['categories'] as $category) {
                $categoryLabel = $category->label();

                foreach ($navigation as $navGroup) {
                    if (! ($navGroup instanceof NavigationGroup) || $navGroup->getLabel() !== $categoryLabel) {
                        continue;
                    }

                    $items = $navGroup->getItems();

                    if (count($items) === 0) {
                        continue;
                    }

                    $groupsForTab[] = [
                        'label' => $categoryLabel,
                        'icon' => $category->icon(),
                        'items' => collect($items)->map(fn (NavigationItem $item) => [
                            'label' => $item->getLabel(),
                            'url' => $item->getUrl(),
                            'isActive' => $item->isActive(),
                            'icon' => $this->getIconString($item->getIcon()),
                            'openInSplitView' => $item->shouldOpenUrlInNewTab(),
                        ])->toArray(),
                    ];
                }
            }

            if (count($groupsForTab) > 0) {
                $structure[] = [
                    'id' => $key,
                    'label' => $tab['label'],
                    'icon' => $tab['icon'],
                    'groups' => $groupsForTab,
                ];
            }
        }

        // 酒丸シリーズ（外部システム）のメニューを追加
        $sakemaruTab = $this->buildSakemaruSeriesTab();
        if ($sakemaruTab) {
            $structure[] = $sakemaruTab;
        }

        return $structure;
    }

    /**
     * 酒丸シリーズの外部システムメニュータブを構築
     */
    protected function buildSakemaruSeriesTab(): ?array
    {
        $appUrl = config('app.url');
        $parsed = parse_url($appUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? 'localhost';

        // documents.sakemaru.test → sakemaru.test
        $baseDomain = preg_replace('/^[^.]+\./', '', $host);

        $sakemaruSystems = [
            ['label' => '酒丸（基幹システム）', 'subdomain' => null, 'desc' => '基幹業務システム'],
            ['label' => '酒丸蔵', 'subdomain' => 'wms', 'desc' => '倉庫管理システム'],
            ['label' => '酒丸千里眼', 'subdomain' => 'search', 'desc' => '高度検索システム'],
            ['label' => '酒丸乃蓮', 'subdomain' => 'trade', 'desc' => '取引管理システム'],
            ['label' => '酒丸飛脚', 'subdomain' => 'delivery', 'desc' => '配送管理システム'],
            ['label' => '酒丸算盤', 'subdomain' => 'insights', 'desc' => '分析・レポートシステム'],
            ['label' => '酒丸通い帳', 'subdomain' => 'knowledge', 'desc' => 'ナレッジ管理システム'],
        ];

        $items = [];

        foreach ($sakemaruSystems as $system) {
            $url = $system['subdomain']
                ? "{$scheme}://{$system['subdomain']}.{$baseDomain}"
                : "{$scheme}://{$baseDomain}";

            $items[] = [
                'label' => $system['label'],
                'url' => $url,
                'isActive' => false,
                'icon' => null,
                'openInSplitView' => true,
                'desc' => $system['desc'],
            ];
        }

        return [
            'id' => 'sakemaru_series',
            'label' => '酒丸',
            'icon' => 'fa-box',
            'groups' => [
                [
                    'label' => '酒丸シリーズ',
                    'icon' => 'heroicon-o-arrow-top-right-on-square',
                    'items' => $items,
                ],
            ],
        ];
    }

    protected function getIconString($icon): ?string
    {
        $iconClass = null;

        if ($icon instanceof \BackedEnum) {
            $iconClass = $icon->value;
        } elseif (is_string($icon)) {
            $iconClass = $icon;
        }

        if ($iconClass && (str_starts_with($iconClass, 'o-') || str_starts_with($iconClass, 's-')) && ! str_starts_with($iconClass, 'heroicon-')) {
            return 'heroicon-' . $iconClass;
        }

        return $iconClass;
    }
}
