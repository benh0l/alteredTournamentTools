<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service for parsing CSV files for bulk registration import.
 *
 * Features:
 * - UTF-8 BOM handling
 * - Multiple delimiter detection (comma, semicolon, tab)
 * - Intelligent column suggestion based on header names
 * - File size and row count limits
 */
final class CsvParserService
{
    private const MAX_FILE_SIZE = 1024 * 1024; // 1MB
    private const MAX_ROWS = 500;

    /**
     * Common header names mapped to field types.
     * Used for automatic column detection.
     */
    private const COLUMN_SUGGESTIONS = [
        'email' => ['email', 'mail', 'e-mail', 'courriel', 'adresse email', 'adresse mail', 'adresse e-mail'],
        'pseudo' => ['pseudo', 'nickname', 'username', 'discord', 'joueur', 'player', 'nom utilisateur', 'nom d\'utilisateur'],
        'name' => ['nom', 'name', 'prénom', 'prenom', 'firstname', 'lastname', 'nom complet', 'full name', 'prénom nom', 'prenom nom'],
        'faction' => ['faction', 'faction1', 'faction principale', 'main faction'],
        'faction2' => ['faction2', 'faction secondaire', 'secondary faction', 'faction 2'],
        'hero' => ['héros', 'heros', 'hero', 'personnage', 'character'],
        'decklistUrl' => ['decklist', 'deck', 'liste', 'url', 'lien', 'deck url', 'lien decklist', 'decklist url'],
    ];

    /**
     * Parse a CSV file and return all rows.
     *
     * @return array<int, array<int, string>> Array of rows, each row is an array of column values
     *
     * @throws \InvalidArgumentException If file is too large or has insufficient data
     */
    public function parse(UploadedFile $file): array
    {
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('Le fichier depasse la taille maximale de 1 Mo.');
        }

        $content = file_get_contents($file->getPathname());

        if ($content === false) {
            throw new \InvalidArgumentException('Impossible de lire le fichier.');
        }

        // Handle UTF-8 BOM
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Normalize line endings
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Detect delimiter
        $delimiter = $this->detectDelimiter($content);

        $lines = explode("\n", $content);
        $rows = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS + 1) { // +1 for header
                break;
            }

            $rows[] = str_getcsv($line, $delimiter);
        }

        if (count($rows) < 2) {
            throw new \InvalidArgumentException('Le fichier doit contenir au moins une ligne d\'en-tete et une ligne de donnees.');
        }

        return $rows;
    }

    /**
     * Detect columns and suggest field mappings based on header names.
     *
     * @param array<int, string> $headerRow First row of the CSV (headers)
     *
     * @return array<int, array{original: string, suggested: string|null}> Column suggestions
     */
    public function detectColumns(array $headerRow): array
    {
        $suggestions = [];

        foreach ($headerRow as $index => $column) {
            $normalizedColumn = $this->normalizeString($column);
            $suggestions[$index] = [
                'original' => $column,
                'suggested' => $this->findBestMatch($normalizedColumn),
            ];
        }

        return $suggestions;
    }

    /**
     * Get a preview of the data (first N rows including header).
     *
     * @param array<int, array<int, string>> $rows All parsed rows
     * @param int $limit Number of data rows to include (header always included)
     *
     * @return array<int, array<int, string>> Preview rows
     */
    public function getPreview(array $rows, int $limit = 5): array
    {
        return array_slice($rows, 0, $limit + 1); // +1 to include header
    }

    /**
     * Get maximum allowed file size in bytes.
     */
    public function getMaxFileSize(): int
    {
        return self::MAX_FILE_SIZE;
    }

    /**
     * Get maximum allowed number of rows.
     */
    public function getMaxRows(): int
    {
        return self::MAX_ROWS;
    }

    /**
     * Detect the delimiter used in the CSV content.
     */
    private function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\n");

        if ($firstLine === false) {
            return ',';
        }

        $commaCount = substr_count($firstLine, ',');
        $semicolonCount = substr_count($firstLine, ';');
        $tabCount = substr_count($firstLine, "\t");

        if ($semicolonCount > $commaCount && $semicolonCount > $tabCount) {
            return ';';
        }
        if ($tabCount > $commaCount && $tabCount > $semicolonCount) {
            return "\t";
        }

        return ',';
    }

    /**
     * Normalize a string for comparison (lowercase, remove accents and special chars).
     */
    private function normalizeString(string $value): string
    {
        $value = mb_strtolower($value);
        $value = trim($value);
        // Remove accents
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        // Keep only alphanumeric
        $value = preg_replace('/[^a-z0-9]/', '', $value) ?: '';

        return $value;
    }

    /**
     * Find the best field match for a normalized column name.
     */
    private function findBestMatch(string $normalizedColumn): ?string
    {
        foreach (self::COLUMN_SUGGESTIONS as $field => $keywords) {
            foreach ($keywords as $keyword) {
                $normalizedKeyword = $this->normalizeString($keyword);
                if (str_contains($normalizedColumn, $normalizedKeyword)) {
                    return $field;
                }
            }
        }

        return null;
    }
}
