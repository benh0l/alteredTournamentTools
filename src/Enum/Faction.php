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
                'gretel-rust' => 'Gretel & Rust',
            ],
            self::LYRA => [
                'nevenka-blotch' => 'Nevenka & Blotch',
                'fen-crowbar' => 'Fen & Crowbar',
                'auraq-kibble' => 'Auraq & Kibble',
                'nadir-bubbles' => 'Nadir & Bubbles',
                'yeong-gi-ember' => 'Yeong-Gi & Ember',
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
                'sam-spook' => 'Sam & Spook',
            ],
        };
    }

    /**
     * Get expedition heroes for the Krak'N format.
     * Returns only the specific heroes allowed in this format.
     */
    public function getExpeditionHeroes(): array
    {
        return match ($this) {
            self::AXIOM => [
                'jamie-phils-s-redder' => 'Jamie-Phils & S-Redder',
            ],
            self::BRAVOS => [
                'krenen-huits-vents' => 'Krenen & les Huits vents',
            ],
            self::LYRA => [
                'hileon-gwirp' => 'Hiléon & Gwirp',
            ],
            self::MUNA => [
                'leanne-nounou' => 'Léanne & Nounou',
            ],
            self::ORDIS => [
                'occirea-hushgulan' => 'Occiréa & Hushgulan',
            ],
            self::YZMIR => [
                'roboutchou-giz' => 'Roboutchou & Giz',
            ],
        };
    }

    /**
     * Get heroes for a specific tournament format.
     * Returns standard heroes for most formats, expedition heroes for Krak'N format.
     */
    public function getHeroesForFormat(?TournamentFormat $format = null): array
    {
        if ($format !== null && $format->isExpeditionKrakn()) {
            return $this->getExpeditionHeroes();
        }

        return $this->getHeroes();
    }

    /**
     * Get all heroes across all factions as a flat array
     */
    public static function getAllHeroes(?TournamentFormat $format = null): array
    {
        $heroes = [];
        foreach (self::cases() as $faction) {
            foreach ($faction->getHeroesForFormat($format) as $key => $label) {
                $heroes[$key] = $label;
            }
        }
        return $heroes;
    }

    /**
     * Get hero label by key (searches both standard and expedition heroes)
     */
    public static function getHeroLabel(string $heroKey): ?string
    {
        foreach (self::cases() as $faction) {
            // Check standard heroes
            $heroes = $faction->getHeroes();
            if (isset($heroes[$heroKey])) {
                return $heroes[$heroKey];
            }
            // Check expedition heroes
            $expeditionHeroes = $faction->getExpeditionHeroes();
            if (isset($expeditionHeroes[$heroKey])) {
                return $expeditionHeroes[$heroKey];
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
     * Get faction by hero key (searches both standard and expedition heroes)
     */
    public static function getFactionByHero(string $heroKey): ?self
    {
        foreach (self::cases() as $faction) {
            if (array_key_exists($heroKey, $faction->getHeroes())) {
                return $faction;
            }
            if (array_key_exists($heroKey, $faction->getExpeditionHeroes())) {
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
    public static function getHeroesGroupedByFaction(?TournamentFormat $format = null): array
    {
        $grouped = [];
        foreach (self::cases() as $faction) {
            $grouped[$faction->value] = $faction->getHeroesForFormat($format);
        }
        return $grouped;
    }

    /**
     * Check if a hero key is valid for a given format.
     */
    public static function isHeroValidForFormat(string $heroKey, ?TournamentFormat $format = null): bool
    {
        $allHeroes = self::getAllHeroes($format);
        return isset($allHeroes[$heroKey]);
    }
}
