<?php

declare(strict_types=1);

namespace App\Enum;

enum Faction: string
{
    case AXIOM = 'axiom';
    case BRAVOS = 'bravos';
    case LYRA = 'lyra';
    case MUNA = 'muna';
    case ORDIS = 'ordis';
    case YZMIR = 'yzmir';

    public function getLabel(): string
    {
        return match ($this) {
            self::AXIOM => 'Axiom',
            self::BRAVOS => 'Bravos',
            self::LYRA => 'Lyra',
            self::MUNA => 'Muna',
            self::ORDIS => 'Ordis',
            self::YZMIR => 'Yzmir',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AXIOM => '#7a4d25',   // Amber/Brown
            self::BRAVOS => '#ef4444',  // Red
            self::LYRA => '#e394da',    // Pink
            self::MUNA => '#22c55e',    // Green
            self::ORDIS => '#3b82f6',   // Blue
            self::YZMIR => '#a855f7',    // Purple
        };
    }

    public function getHeroes(): array
    {
        return match ($this) {
            self::AXIOM => [
                'sierra-oddball' => 'Sierra & Oddball',
                'subhash-marmo' => 'Subhash & Marmo',
                'treyst-rossum' => 'Treyst & Rossum',
                'isaree-pebble' => 'Isaree & Pebble',
                'della-bolt' => 'Della & Bolt',
            ],
            self::BRAVOS => [
                'kojo-booda' => 'Kojo & Booda',
                'atsadi-surge' => 'Atsadi & Surge',
                'basira-kaizaimon' => 'Basira & Kaizaimon',
                'sol-halua' => 'Sol & Halua',
            ],
            self::LYRA => [
                'nevenka-blotch' => 'Nevenka & Blotch',
                'fen-crowbar' => 'Fen & Crowbar',
                'auraq-kibble' => 'Auraq & Kibble',
                'nadir-bubbles' => 'Nadir & Bubbles',
            ],
            self::MUNA => [
                'teija-nauraa' => 'Teija & Nauraa',
                'arjun-spike' => 'Arjun & Spike',
                'rin-orchid' => 'Rin & Orchid',
                'kauri-puff' => 'Kauri & Puff',
                'turuun-benih' => 'Turuun & Benih',
            ],
            self::ORDIS => [
                'sigismar-wingspan' => 'Sigismar & Wingspan',
                'gulrang-tocsin' => 'Gulrang & Tocsin',
                'waru-mack' => 'Waru & Mack',
                'zhen-zephyr' => 'Zhen & Zephyr',
                'matz-hive' => 'Matz & Hive',
            ],
            self::YZMIR => [
                'akesha-taru' => 'Akesha & Taru',
                'afanas-senka' => 'Afanas & Senka',
                'lindiwe-maw' => 'Lindiwe & Maw',
                'moyo-silk' => 'Moyo & Silk',
            ],
        };
    }

    /**
     * Get all heroes across all factions as a flat array
     */
    public static function getAllHeroes(): array
    {
        $heroes = [];
        foreach (self::cases() as $faction) {
            foreach ($faction->getHeroes() as $key => $label) {
                $heroes[$key] = $label;
            }
        }
        return $heroes;
    }

    /**
     * Get hero label by key
     */
    public static function getHeroLabel(string $heroKey): ?string
    {
        foreach (self::cases() as $faction) {
            $heroes = $faction->getHeroes();
            if (isset($heroes[$heroKey])) {
                return $heroes[$heroKey];
            }
        }
        return null;
    }

    /**
     * Get hero image path by key
     */
    public static function getHeroImage(string $heroKey): ?string
    {
        $path = '/images/heroes/' . $heroKey . '.png';
        return $path;
    }

    /**
     * Get faction by hero key
     */
    public static function getFactionByHero(string $heroKey): ?self
    {
        foreach (self::cases() as $faction) {
            if (array_key_exists($heroKey, $faction->getHeroes())) {
                return $faction;
            }
        }
        return null;
    }

    /**
     * Get all factions as choices for forms
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $faction) {
            $choices[$faction->getLabel()] = $faction->value;
        }
        return $choices;
    }

    /**
     * Get all heroes grouped by faction for JavaScript
     */
    public static function getHeroesGroupedByFaction(): array
    {
        $grouped = [];
        foreach (self::cases() as $faction) {
            $grouped[$faction->value] = $faction->getHeroes();
        }
        return $grouped;
    }
}
