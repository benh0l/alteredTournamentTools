<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tournament;
use App\Exception\TournamentValidationException;
use Symfony\Component\Uid\Uuid;

final class RecurringTournamentService
{
    private const MAX_COUNT = 52;

    /**
     * Generate additional tournament instances for a recurring series.
     *
     * @param array{unit: string, interval: int, count: int} $params
     *
     * @return Tournament[] the additional copies (excludes $source itself)
     *
     * @throws TournamentValidationException if params are invalid or generated dates are in the past
     */
    public function generateCopies(Tournament $source, array $params): array
    {
        $unit = $params['unit'] ?? 'weeks';
        $interval = (int) ($params['interval'] ?? 1);
        $count = (int) ($params['count'] ?? 2);

        if ($interval < 1) {
            throw new TournamentValidationException('tournament.wizard.recurrence.interval_range');
        }

        if ($count < 2 || $count > self::MAX_COUNT) {
            throw new TournamentValidationException('tournament.wizard.recurrence.count_range');
        }

        $groupId = Uuid::v4()->toRfc4122();
        $source->setRecurrenceGroupId($groupId);

        $copies = [];
        $baseDate = $source->getDate();
        $now = new \DateTimeImmutable('today');

        for ($i = 1; $i < $count; ++$i) {
            $nextDate = $this->addInterval($baseDate, $unit, $interval * $i);

            if ($nextDate < $now) {
                throw new TournamentValidationException('tournament.wizard.recurrence.date_in_past');
            }

            $copy = $this->cloneTournament($source, $nextDate);
            $copy->setRecurrenceGroupId($groupId);
            $copies[] = $copy;
        }

        return $copies;
    }

    private function addInterval(\DateTimeImmutable $date, string $unit, int $amount): \DateTimeImmutable
    {
        return match ($unit) {
            'days' => $date->modify("+{$amount} days"),
            'months' => $date->modify("+{$amount} months"),
            default => $date->modify("+{$amount} weeks"),
        };
    }

    private function cloneTournament(Tournament $source, \DateTimeImmutable $date): Tournament
    {
        $copy = new Tournament();
        $copy->setName($source->getName());
        $copy->setDate($date);

        if ($source->getTime() !== null) {
            $copy->setTime($source->getTime());
        }
        if ($source->getLocation() !== null) {
            $copy->setLocation($source->getLocation());
        }
        if ($source->getLatitude() !== null) {
            $copy->setLatitude($source->getLatitude());
        }
        if ($source->getLongitude() !== null) {
            $copy->setLongitude($source->getLongitude());
        }

        $copy->setFormat($source->getFormat());
        $copy->setStructure($source->getStructure());
        $copy->setSwissMatchFormat($source->getSwissMatchFormat());
        $copy->setEliminationMatchFormat($source->getEliminationMatchFormat());
        $copy->setExpectedPlayers($source->getExpectedPlayers());
        $copy->setSwissRounds($source->getSwissRounds());
        $copy->setTopCutSize($source->getTopCutSize());
        $copy->setVisibility($source->getVisibility());
        $copy->setDecklistTransparency($source->getDecklistTransparency());

        if ($source->getDescription() !== null) {
            $copy->setDescription($source->getDescription());
        }
        if ($source->getEntryFee() !== null) {
            $copy->setEntryFee($source->getEntryFee());
        }
        if ($source->getPrizes() !== null) {
            $copy->setPrizes($source->getPrizes());
        }
        if ($source->getAlteredGgLink() !== null) {
            $copy->setAlteredGgLink($source->getAlteredGgLink());
        }
        if ($source->getGroupCount() !== null) {
            $copy->setGroupCount($source->getGroupCount());
        }
        if ($source->getPlayersPerGroup() !== null) {
            $copy->setPlayersPerGroup($source->getPlayersPerGroup());
        }
        if ($source->getQualifiersPerGroup() !== null) {
            $copy->setQualifiersPerGroup($source->getQualifiersPerGroup());
        }
        if ($source->getGroupFormationMethod() !== null) {
            $copy->setGroupFormationMethod($source->getGroupFormationMethod());
        }

        return $copy;
    }
}
