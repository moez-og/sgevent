<?php

namespace App\Enum;

enum LieuCategorie: string
{
    case RESTAURANT = 'RESTO';
    case RESTO_BAR = 'RESTO_BAR';
    case LIEU_PUBLIC = 'LIEU_PUBLIC';
    case PARC_ATTRACTION = 'PARC_ATTRACTION';
    case MUSEE = 'MUSEE';
    case PLAGE = 'PLAGE';
    case CENTRE_COMMERCIAL = 'CENTRE_COMMERCIAL';
    case CAFE = 'CAFE';
    case HOTEL = 'HOTEL';
    case SALLE = 'SALLE';
    case PARC = 'PARC';
    case AUTRE = 'AUTRE';

    public function getLabel(): string
    {
        return match ($this) {
            self::RESTAURANT => 'Restaurant',
            self::RESTO_BAR => 'Resto/Bar',
            self::LIEU_PUBLIC => 'Lieu public',
            self::PARC_ATTRACTION => 'Parc d\'attraction',
            self::MUSEE => 'Musée',
            self::PLAGE => 'Plage',
            self::CENTRE_COMMERCIAL => 'Centre commercial',
            self::CAFE => 'Café',
            self::HOTEL => 'Hôtel',
            self::SALLE => 'Salle',
            self::PARC => 'Parc',
            self::AUTRE => 'Autre',
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
            'Restaurant' => self::RESTAURANT,
            'Resto/Bar' => self::RESTO_BAR,
            'Lieu public' => self::LIEU_PUBLIC,
            'Parc d\'attraction' => self::PARC_ATTRACTION,
            'Musée' => self::MUSEE,
            'Plage' => self::PLAGE,
            'Centre commercial' => self::CENTRE_COMMERCIAL,
            'Café' => self::CAFE,
            'Hôtel' => self::HOTEL,
            'Salle' => self::SALLE,
            'Parc' => self::PARC,
            'Autre' => self::AUTRE,
        ];
    }
}
