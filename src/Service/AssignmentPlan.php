<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Entity\Staff;
use App\Entity\Survey;
use App\Repository\AssignmentRepository;

/**
 * Builds a survey's assignment plan and renders it for the assignment e-mail: a personal summary of the
 * recipient's events, followed by every event with its leader and crew. Skills are deliberately left out.
 *
 * @phpstan-type PlanEntry array{event: Event, leader: ?Staff, crew: list<Staff>}
 */
class AssignmentPlan
{
    private const WEEKDAYS = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

    private const HIGHLIGHT_STYLE = 'background:#fef3c7;font-weight:bold;padding:0 4px;border-radius:3px';

    public function __construct(private readonly AssignmentRepository $assignmentRepo)
    {
    }

    /**
     * Returns one entry per event, sorted by date. The crew excludes the leader and is sorted by last name;
     * a staff member assigned with several skills appears once.
     *
     * @return list<PlanEntry>
     */
    public function build(Survey $survey): array
    {
        $crewByEvent = [];
        foreach ($this->assignmentRepo->findForSurveyOrderedByName($survey) as $assignment) {
            $staff = $assignment->getStaff();
            $crewByEvent[$assignment->getEvent()->getId()][$staff->getId()] = $staff;
        }

        $plan = [];
        foreach ($survey->getEvents() as $event) {
            $leader = $event->getLeader();
            $crew = $crewByEvent[$event->getId()] ?? [];
            if ($leader) {
                unset($crew[$leader->getId()]);
            }
            $plan[] = ['event' => $event, 'leader' => $leader, 'crew' => array_values($crew)];
        }

        return $plan;
    }

    /**
     * @param list<PlanEntry> $plan
     */
    public function isAssigned(array $plan, Staff $staff): bool
    {
        return $this->entriesFor($plan, $staff) !== [];
    }

    /**
     * @param list<PlanEntry> $plan
     */
    public function text(array $plan, Staff $recipient): string
    {
        $lines = [];
        $own = $this->entriesFor($plan, $recipient);
        if ($own === []) {
            $lines[] = 'Du bist diesmal nicht eingeteilt.';
        } else {
            $lines[] = 'Du bist an folgenden Daten eingeteilt:';
            foreach ($own as $entry) {
                $lines[] = '  ▶ ' . $this->eventLabel($entry['event'], '  ') . $this->leaderSuffix($entry, $recipient);
            }
        }

        $lines[] = '';
        $lines[] = '── Gesamter Plan ──────────────────────';

        foreach ($plan as $entry) {
            $lines[] = '';
            $lines[] = $this->eventLabel($entry['event'], ' · ');
            if ($entry['event']->getDescription()) {
                $lines[] = '  ' . $entry['event']->getDescription();
            }
            if ($entry['leader']) {
                $lines[] = '  ★ Leitung: ' . $entry['leader']->getFullName();
            }
            foreach ($entry['crew'] as $staff) {
                $lines[] = ($staff === $recipient ? '    ┃ ' : '    ') . $staff->getFullName();
            }
            if (!$entry['leader'] && $entry['crew'] === []) {
                $lines[] = '    Keine Einteilung';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<PlanEntry> $plan
     */
    public function html(array $plan, Staff $recipient): string
    {
        $own = $this->entriesFor($plan, $recipient);
        if ($own === []) {
            $html = '<p style="margin:0 0 16px">Du bist diesmal nicht eingeteilt.</p>';
        } else {
            $html = '<p style="margin:0 0 4px;font-weight:bold">Du bist an folgenden Daten eingeteilt:</p>'
                . '<ul style="margin:0 0 16px;padding-left:20px">';
            foreach ($own as $entry) {
                $html .= '<li>' . $this->e($this->eventLabel($entry['event'], ' · ')
                    . $this->leaderSuffix($entry, $recipient)) . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '<div style="font-weight:bold;font-size:15px;margin:16px 0 8px;padding-bottom:4px;'
            . 'border-bottom:1px solid #e5e7eb">Gesamter Plan</div>';

        foreach ($plan as $entry) {
            $html .= '<div style="margin:0 0 14px">'
                . '<div style="font-weight:bold">' . $this->e($this->eventLabel($entry['event'], ' · ')) . '</div>';
            if ($entry['event']->getDescription()) {
                $html .= '<div style="color:#6b7280;font-size:13px">'
                    . $this->e($entry['event']->getDescription()) . '</div>';
            }
            if ($entry['leader']) {
                $html .= '<div style="padding-left:12px"><span style="color:#d97706">★</span> Leitung: '
                    . $this->htmlName($entry['leader'], $recipient) . '</div>';
            }
            foreach ($entry['crew'] as $staff) {
                $html .= '<div style="padding-left:12px">' . $this->htmlName($staff, $recipient) . '</div>';
            }
            if (!$entry['leader'] && $entry['crew'] === []) {
                $html .= '<div style="padding-left:12px;color:#9ca3af">Keine Einteilung</div>';
            }
            $html .= '</div>';
        }

        return '<div>' . $html . '</div>';
    }

    /**
     * @param list<PlanEntry> $plan
     * @return list<PlanEntry> the entries the staff member leads or is part of
     */
    private function entriesFor(array $plan, Staff $staff): array
    {
        return array_values(array_filter(
            $plan,
            fn(array $entry) => $entry['leader'] === $staff || in_array($staff, $entry['crew'], true),
        ));
    }

    /**
     * @param PlanEntry $entry
     */
    private function leaderSuffix(array $entry, Staff $recipient): string
    {
        return $entry['leader'] === $recipient ? ' (Leitung)' : '';
    }

    /** E.g. "So 06.12.2026 · Gottesdienst". */
    private function eventLabel(Event $event, string $separator): string
    {
        $date = $event->getDate();

        return self::WEEKDAYS[(int) $date->format('w')] . ' ' . $date->format('d.m.Y') . $separator . $event->getTitle();
    }

    private function htmlName(Staff $staff, Staff $recipient): string
    {
        if ($staff !== $recipient) {
            return $this->e($staff->getFullName());
        }

        return '<span style="' . self::HIGHLIGHT_STYLE . '">' . $this->e($staff->getFullName()) . '</span>';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
