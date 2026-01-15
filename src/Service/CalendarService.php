<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tournament;

/**
 * Service for generating ICS calendar files (FR64).
 *
 * Generates valid iCalendar (ICS) format files for tournament events
 * that can be imported into calendar applications.
 */
class CalendarService
{
    /**
     * Generate an ICS file content for a tournament.
     */
    public function generateICS(Tournament $tournament): string
    {
        $uid = sprintf('tournament-%d@alteredtournament.tools', $tournament->getId());
        $dtstamp = (new \DateTimeImmutable())->format('Ymd\THis\Z');

        // Calculate start and end times
        $startDate = $tournament->getDate();
        $time = $tournament->getTime();

        if ($time !== null) {
            $startDateTime = $startDate->setTime(
                (int) $time->format('H'),
                (int) $time->format('i')
            );
        } else {
            // Default to 14:00 if no time specified
            $startDateTime = $startDate->setTime(14, 0);
        }

        // Estimate 6 hours for tournament duration
        $endDateTime = $startDateTime->modify('+6 hours');

        $dtstart = $startDateTime->format('Ymd\THis');
        $dtend = $endDateTime->format('Ymd\THis');

        // Prepare event details
        $summary = $this->escapeIcsText($tournament->getName());
        $location = $tournament->getLocation()
            ? $this->escapeIcsText($tournament->getLocation())
            : '';

        $description = $this->buildDescription($tournament);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//AlteredTournamentTools//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            sprintf('UID:%s', $uid),
            sprintf('DTSTAMP:%s', $dtstamp),
            sprintf('DTSTART:%s', $dtstart),
            sprintf('DTEND:%s', $dtend),
            sprintf('SUMMARY:%s', $summary),
        ];

        if ($location !== '') {
            $lines[] = sprintf('LOCATION:%s', $location);
        }

        $lines[] = sprintf('DESCRIPTION:%s', $description);
        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'TRANSP:OPAQUE';

        // Add alarm 1 hour before
        $lines[] = 'BEGIN:VALARM';
        $lines[] = 'ACTION:DISPLAY';
        $lines[] = 'DESCRIPTION:Rappel tournoi';
        $lines[] = 'TRIGGER:-PT1H';
        $lines[] = 'END:VALARM';

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // ICS files use CRLF line endings
        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Get the filename for the ICS file.
     */
    public function getFilename(Tournament $tournament): string
    {
        $slug = $this->slugify($tournament->getName());

        return sprintf('tournoi-%s-%s.ics', $slug, $tournament->getDate()->format('Y-m-d'));
    }

    /**
     * Build the description for the calendar event.
     */
    private function buildDescription(Tournament $tournament): string
    {
        $parts = [];

        $parts[] = sprintf('Format: %s', $tournament->getFormat()->getLabel());
        $parts[] = sprintf('Structure: %s', $tournament->getStructure()->getLabel());

        if ($tournament->getEntryFee() !== null) {
            $parts[] = sprintf("Prix d'entree: %s", $tournament->getEntryFee());
        }

        $parts[] = sprintf('Organisateur: %s', $tournament->getOrganizer()->getPseudo());

        if ($tournament->getDescription() !== null) {
            $parts[] = '';
            $parts[] = $tournament->getDescription();
        }

        return $this->escapeIcsText(implode('\n', $parts));
    }

    /**
     * Escape text for ICS format.
     *
     * According to RFC 5545, special characters need to be escaped.
     */
    private function escapeIcsText(string $text): string
    {
        // Replace newlines with \n
        $text = str_replace(["\r\n", "\r", "\n"], '\n', $text);

        // Escape backslashes, semicolons, and commas
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);

        return $text;
    }

    /**
     * Convert a string to a URL-safe slug.
     */
    private function slugify(string $text): string
    {
        // Convert to ASCII
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);

        // Replace non-alphanumeric characters with hyphens
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);

        // Trim hyphens from ends
        return trim($text, '-');
    }
}
