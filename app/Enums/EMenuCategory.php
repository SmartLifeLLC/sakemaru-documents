<?php

namespace App\Enums;

enum EMenuCategory: string
{
    case Documents = 'documents';
    case Master = 'master';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Documents => '帳票管理',
            self::Master => 'マスタ',
            self::System => '同期運用',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Documents => 'heroicon-o-document-text',
            self::Master => 'heroicon-o-circle-stack',
            self::System => 'heroicon-o-arrow-path',
        };
    }
}
