<?php

declare(strict_types=1);

namespace Minilytics\Analytics;

use Minilytics\Analytics\Reports\AcquisitionReport;
use Minilytics\Analytics\Reports\BotsReport;
use Minilytics\Analytics\Reports\CountriesReport;
use Minilytics\Analytics\Reports\EnvironmentReport;
use Minilytics\Analytics\Reports\EventsReport;
use Minilytics\Analytics\Reports\FunnelsReport;
use Minilytics\Analytics\Reports\PagesReport;
use Minilytics\Analytics\Reports\ReferrersReport;
use Minilytics\Analytics\Reports\SessionsReport;
use Minilytics\Analytics\Reports\SummaryReport;
use Minilytics\Analytics\Reports\TimeseriesReport;
use Minilytics\Database\Connections;
use Minilytics\Database\DatabaseConnection;
use Minilytics\Sites\SiteRegistry;

/**
 * Analytics reports for one website over one period, with the dashboard
 * filters applied. Every analytics endpoint reads its numbers from here
 * instead of querying the database itself; each report lives in its own
 * class under Reports/.
 */
final class SiteAnalytics
{
    public const ENVIRONMENT_FIELDS = EnvironmentReport::FIELDS;

    public readonly string $siteId;
    public readonly Period $period;
    /** @var array<string, string[]> */
    public readonly array $filters;

    private readonly ReportQuery $query;
    private readonly SummaryReport $summary;

    /** @param array<string, string[]> $filters see AnalyticsFilters::fromRequest() */
    public function __construct(DatabaseConnection $db, string $siteId, Period $period, array $filters = [])
    {
        $this->siteId = $siteId;
        $this->period = $period;
        $this->filters = $filters;
        $this->query = new ReportQuery($db, $siteId, $period, $filters);
        $this->summary = new SummaryReport($this->query);
    }

    /** @param array<string, string[]> $filters */
    public static function open(?string $siteId, Period $period, array $filters = []): self
    {
        $siteId = SiteRegistry::requireId($siteId);
        return new self(Connections::forSite($siteId), $siteId, $period, $filters);
    }

    /** Distinct visitors active in the last 5 minutes. */
    public function liveVisitors(): int
    {
        return $this->summary->liveVisitors();
    }

    /** @see SummaryReport::get() */
    public function summary(): array
    {
        return $this->summary->get();
    }

    /** @see TimeseriesReport::get() */
    public function timeseries(): array
    {
        return (new TimeseriesReport($this->query))->get();
    }

    /** @see PagesReport::top() */
    public function topPages(int $limit = 100, ?string $search = null): array
    {
        return (new PagesReport($this->query, $this->summary))->top($limit, $search);
    }

    /** @see ReferrersReport::top() */
    public function topReferrers(int $limit = 100): array
    {
        return (new ReferrersReport($this->query))->top($limit);
    }

    /** @see EventsReport::top() */
    public function topEvents(int $limit = 100, ?string $search = null): array
    {
        return (new EventsReport($this->query))->top($limit, $search);
    }

    /** @see EventsReport::details() */
    public function eventDetails(string $event, ?string $property = null, int $limit = 10): array
    {
        return (new EventsReport($this->query))->details($event, $property, $limit);
    }

    /**
     * @see FunnelsReport::progress()
     * @param list<array{type: string, value: string}> $steps
     * @return list<int>
     */
    public function funnelProgress(array $steps): array
    {
        return (new FunnelsReport($this->query))->progress($steps);
    }

    /**
     * @see FunnelsReport::journey()
     *
     * @param list<array{type: string, value: string}> $steps
     */
    public function journey(array $steps, bool $allTime = false): array
    {
        return (new FunnelsReport($this->query))->journey($steps, $allTime);
    }

    /**
     * @see FunnelsReport::saved()
     * @return list<array{id: int, name: string, kind: string, steps: list<array{type: string, value: string, label?: string}>}>
     */
    public function savedFunnels(): array
    {
        return (new FunnelsReport($this->query))->saved();
    }

    /** @see EnvironmentReport::get() */
    public function environment(string $field, int $limit = 50): array
    {
        return (new EnvironmentReport($this->query, $this->summary))->get($field, $limit);
    }

    /** @see CountriesReport::get() */
    public function countries(int $limit = 100): array
    {
        return (new CountriesReport($this->query, $this->summary))->get($limit);
    }

    /**
     * @see AcquisitionReport::get()
     * @return array<string, list<array{name: string, sessions: int}>>
     */
    public function acquisition(): array
    {
        return (new AcquisitionReport($this->query))->get();
    }

    /** @see SessionsReport::latest() */
    public function latestVisits(int $limit = 10): array
    {
        return (new SessionsReport($this->query))->latest($limit);
    }

    /** @see SessionsReport::visit() */
    public function visit(string $visitId): ?array
    {
        return (new SessionsReport($this->query))->visit($visitId);
    }

    /** @see BotsReport::summary() */
    public function botActivity(): array
    {
        return (new BotsReport($this->query))->summary();
    }
}
