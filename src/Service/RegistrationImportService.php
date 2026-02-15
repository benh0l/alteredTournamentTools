<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ColumnMapping;
use App\DTO\ImportResult;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\Faction;
use App\Exception\AlreadyRegisteredException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Service for bulk importing registrations from CSV data.
 *
 * Features:
 * - Links existing users by email
 * - Creates guest accounts for new users
 * - Validates and maps faction/hero data
 * - Handles duplicates gracefully
 * - Atomic transaction for data integrity
 */
final class RegistrationImportService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly RegistrationService $registrationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Import registrations from parsed CSV data.
     *
     * @param Tournament $tournament Target tournament
     * @param array<int, array<int, string>> $rows CSV rows (including header)
     * @param ColumnMapping $mapping Column to field mapping
     * @param bool $sendEmails Whether to send confirmation emails
     *
     * @return ImportResult Results of the import operation
     */
    public function import(
        Tournament $tournament,
        array $rows,
        ColumnMapping $mapping,
        bool $sendEmails = false
    ): ImportResult {
        $result = new ImportResult();
        array_shift($rows); // Remove header row

        $this->entityManager->beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $lineNumber = $index + 2; // +2 because: 0-indexed + header row

                try {
                    $this->processRow($tournament, $row, $mapping, $result, $lineNumber, $sendEmails);
                } catch (\Exception $e) {
                    $result->addError($lineNumber, $e->getMessage());
                    $this->logger->warning('Import row error', [
                        'line' => $lineNumber,
                        'error' => $e->getMessage(),
                        'tournament_id' => $tournament->getId(),
                    ]);
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Bulk import completed', [
                'tournament_id' => $tournament->getId(),
                'tournament_name' => $tournament->getName(),
                'success_count' => $result->getSuccessCount(),
                'created_count' => $result->getCreatedCount(),
                'linked_count' => $result->getLinkedCount(),
                'skipped_count' => $result->getSkippedCount(),
                'error_count' => $result->getErrorCount(),
            ]);

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Bulk import failed', [
                'tournament_id' => $tournament->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $result;
    }

    /**
     * Process a single row from the CSV.
     */
    private function processRow(
        Tournament $tournament,
        array $row,
        ColumnMapping $mapping,
        ImportResult $result,
        int $lineNumber,
        bool $sendEmails
    ): void {
        $email = $this->extractValue($row, $mapping->emailIndex);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result->addError($lineNumber, 'Email invalide ou manquant');

            return;
        }

        $email = mb_strtolower(trim($email));

        // Check for existing user
        $user = $this->userRepository->findOneBy(['email' => $email]);
        $isNewUser = false;

        if ($user === null) {
            // Create guest user
            $user = $this->createGuestUser(
                $email,
                $this->extractValue($row, $mapping->nameIndex),
                $this->extractValue($row, $mapping->pseudoIndex)
            );
            $this->entityManager->persist($user);
            // Flush to get user ID for registration
            $this->entityManager->flush();
            $isNewUser = true;
        }

        // Check if already registered
        if ($this->registrationService->isPlayerRegistered($user, $tournament)) {
            $result->addSkipped($lineNumber, 'Deja inscrit');

            return;
        }

        // Extract optional fields
        $faction = $this->parseFaction($this->extractValue($row, $mapping->factionIndex));
        $faction2 = $this->parseFaction($this->extractValue($row, $mapping->faction2Index));
        $hero = $this->extractValue($row, $mapping->heroIndex);
        $decklistUrl = $this->extractValue($row, $mapping->decklistUrlIndex);

        // Validate decklist URL if provided
        if ($decklistUrl && !filter_var($decklistUrl, FILTER_VALIDATE_URL)) {
            $decklistUrl = null;
        }

        // Create registration
        try {
            $this->registrationService->registerPlayerByOrganizer(
                $user,
                $tournament,
                $decklistUrl,
                $faction,
                $hero,
                $faction2,
                !$sendEmails // skipEmail = inverse of sendEmails
            );
        } catch (AlreadyRegisteredException $e) {
            $result->addSkipped($lineNumber, 'Deja inscrit');

            return;
        }

        if ($isNewUser) {
            $result->addCreated($lineNumber, $email);
        } else {
            $result->addLinked($lineNumber, $email);
        }

        $result->addSuccess();
    }

    /**
     * Create a guest user account.
     */
    private function createGuestUser(string $email, ?string $name, ?string $pseudo): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setIsGuest(true);

        if ($name !== null && $name !== '') {
            $user->setName($name);
        }

        // Generate unique pseudo if not provided or already taken
        if ($pseudo !== null && $pseudo !== '' && !$this->userRepository->findOneBy(['pseudo' => $pseudo])) {
            $user->setPseudo($pseudo);
        } else {
            $user->setPseudo($this->generateUniquePseudo());
        }

        // Set unusable password (random UUID)
        $user->setPassword($this->passwordHasher->hashPassword($user, Uuid::v4()->toRfc4122()));

        // Generate claim token for account activation
        $user->generateClaimToken();

        return $user;
    }

    /**
     * Generate a unique pseudo for guest accounts.
     */
    private function generateUniquePseudo(): string
    {
        do {
            $pseudo = 'Player_' . strtoupper(substr(Uuid::v4()->toRfc4122(), 0, 6));
        } while ($this->userRepository->findOneBy(['pseudo' => $pseudo]));

        return $pseudo;
    }

    /**
     * Extract a value from a row by column index.
     */
    private function extractValue(array $row, ?int $index): ?string
    {
        if ($index === null || !isset($row[$index])) {
            return null;
        }

        $value = trim($row[$index]);

        return $value === '' ? null : $value;
    }

    /**
     * Parse a faction string to Faction enum with fuzzy matching.
     */
    private function parseFaction(?string $value): ?Faction
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        // Direct match by value
        foreach (Faction::cases() as $faction) {
            if (mb_strtolower($faction->value) === $normalized) {
                return $faction;
            }
        }

        // Fuzzy match by name/label
        $factionMap = [
            'axiom' => Faction::AXIOM,
            'bravos' => Faction::BRAVOS,
            'lyra' => Faction::LYRA,
            'muna' => Faction::MUNA,
            'ordis' => Faction::ORDIS,
            'yzmir' => Faction::YZMIR,
        ];

        foreach ($factionMap as $key => $faction) {
            // Match if starts with first 3 letters of faction name
            if (str_starts_with($normalized, substr($key, 0, 3))) {
                return $faction;
            }
        }

        // Try exact match on label
        foreach (Faction::cases() as $faction) {
            if (mb_strtolower($faction->getLabel()) === $normalized) {
                return $faction;
            }
        }

        return null;
    }
}
