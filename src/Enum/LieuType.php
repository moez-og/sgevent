<?php

namespace App\Enum;

enum LieuType: string
{
    case PUBLIC = 'PUBLIC';
    case PRIVE = 'PRIVE';

    public function getLabel(): string
    {
        return match ($this) {
            self::PUBLIC => 'Public',
            self::PRIVE => 'Privé',
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        return [
            'Public' => self::PUBLIC,
            'Privé' => self::PRIVE,
        ];
    }
}
