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
            self::Documents => '帳票',
            self::Master => 'マスタ',
            self::System => 'システム',
        };
    }
}
