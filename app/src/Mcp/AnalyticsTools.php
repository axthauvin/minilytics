<?php

declare(strict_types=1);

namespace Minilytics\Mcp;

use Closure;
use InvalidArgumentException;
use Minilytics\Analytics\AnalyticsFilters;
use Minilytics\Analytics\Period;
use Minilytics\Analytics\SiteAnalytics;
use Minilytics\Sites\SiteRegistry;

/**
 * The analytics exposed to AI assistants over MCP.
 *
 * Every report tool shares the same `site_id`, period and filter arguments and
 * reads its numbers from SiteAnalytics: exposing a new report is one more
 * entry in all().
 */
final class AnalyticsTools
{
    private const ACQUISITION_REPORTS = ['channels', 'sources', 'mediums', 'campaigns', 'contents', 'terms', 'landing_pages', 'exit_pages'];
    private const FILTER_DIMENSIONS = ['page', 'referrer', 'browser', 'os', 'device', 'country', 'event'];

    /** @return list<Tool> */
    public static function all(): array
    {
        $limit = ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10, 'description' => 'Maximum number of rows to return.']];

        return [
            new Tool(
                'list_sites',
                'List websites',
                'Lists the websites tracked by this Minilytics instance. Call it first: every other tool takes one of these site_id values.',
                [],
                [],
                static fn(): array => ['sites' => array_map(static fn(array $site): array => [
                    'site_id' => $site['id'],
                    'name' => $site['name'] ?? $site['id'],
                    'domain' => $site['domain'] ?? '',
                ], SiteRegistry::all())],
            ),
            self::report(
                'get_overview',
                'Traffic overview',
                'Visitors, returning visitors (seen in an earlier visit, within the same calendar month at most), visits, pageviews, events, bounce rate (%), average visit duration (seconds) and visitors active in the last 5 minutes. A visit ends after 30 minutes without activity, so one visitor can have several visits. Each metric comes with the value of the previous period of the same length, the absolute change and the change in percent (null when there is no previous value to compare with).',
                static fn(SiteAnalytics $analytics): array => ['summary' => self::overview($analytics->summary())],
            ),
            self::report(
                'get_timeseries',
                'Traffic over time',
                'Pageviews, visits, visitors and events per hour (periods up to 2 days), per day, or per week (periods over a year).',
                static fn(SiteAnalytics $analytics): array => ['timeseries' => array_map(
                    static fn(array $slot): array => ['date' => $slot['full_label']] + array_intersect_key($slot, array_flip(['timestamp', 'pageviews', 'sessions', 'visitors', 'events'])),
                    $analytics->timeseries(),
                )],
            ),
            self::report(
                'get_top_pages',
                'Top pages',
                'Most viewed pages with their views, unique visitors and share of all pageviews. Use search to find pages beyond the top ones, such as every page under /blog.',
                static fn(SiteAnalytics $analytics, array $args): array => ['pages' => $analytics->topPages(self::limit($args), self::text($args, 'search'))],
                ['search' => ['type' => 'string', 'description' => 'Only pages whose path contains this text, case-insensitive (e.g. "/blog").']] + $limit,
            ),
            self::report(
                'get_top_referrers',
                'Top referrers',
                'Websites sending traffic, by visits, each visit counted once from its first page ("Direct / None" when there is no referrer). The website\'s own domains are excluded.',
                static fn(SiteAnalytics $analytics, array $args): array => ['referrers' => $analytics->topReferrers(self::limit($args))],
                $limit,
            ),
            self::report(
                'get_top_events',
                'Top custom events',
                'Custom events (everything except pageviews) by number of occurrences. Use search to find events by name, such as every event containing "error". Then call get_event_details for one event.',
                static fn(SiteAnalytics $analytics, array $args): array => ['events' => $analytics->topEvents(self::limit($args), self::text($args, 'search'))],
                ['search' => ['type' => 'string', 'description' => 'Only events whose name contains this text, case-insensitive (e.g. "error").']] + $limit,
            ),
            self::report(
                'get_event_details',
                'Event details',
                'One custom event in detail: occurrences, unique visitors and visits, its evolution over the period, the pages where it fires, and the most frequent values of its own properties (the data the website sent with it, such as an error reason or a plan name). Use the exact event name from get_top_events.',
                static function (SiteAnalytics $analytics, array $args): array {
                    $event = self::text($args, 'event') ?? throw new InvalidArgumentException('event is required: use a name from get_top_events.');
                    return $analytics->eventDetails($event, self::text($args, 'property'), self::limit($args));
                },
                [
                    'event' => ['type' => 'string', 'description' => 'Exact event name.'],
                    'property' => ['type' => 'string', 'description' => 'Only break down this property.'],
                ] + $limit,
                ['event'],
            ),
            self::report(
                'get_funnel',
                'Funnel conversion',
                'How many visits go through ordered steps (pages or custom events), and the conversion between them. Pass steps for any funnel, or the name of a funnel saved in the dashboard. Without either, lists the saved funnels.',
                static fn(SiteAnalytics $analytics, array $args): array => self::funnel($analytics, $args),
                [
                    'steps' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 12,
                        'description' => 'Steps in order, each a page path or an exact event name, e.g. [{"page": "/pricing"}, {"event": "Signup"}].',
                        'items' => ['type' => 'object', 'properties' => ['page' => ['type' => 'string'], 'event' => ['type' => 'string']], 'additionalProperties' => false],
                    ],
                    'funnel' => ['type' => 'string', 'description' => 'Name of a funnel saved in the dashboard.'],
                ],
            ),
            self::report(
                'get_countries',
                'Visitors by country',
                'Unique visitors per country, with their share of all visitors.',
                static fn(SiteAnalytics $analytics, array $args): array => ['countries' => $analytics->countries(self::limit($args))],
                $limit,
            ),
            self::report(
                'get_environment',
                'Browsers, operating systems and devices',
                'Unique visitors per browser, operating system or device type. A visitor using several values is counted once for each.',
                static function (SiteAnalytics $analytics, array $args): array {
                    $dimension = self::choice($args, 'dimension', SiteAnalytics::ENVIRONMENT_FIELDS);
                    return ['dimension' => $dimension, 'values' => $analytics->environment($dimension, self::limit($args))];
                },
                ['dimension' => ['type' => 'string', 'enum' => SiteAnalytics::ENVIRONMENT_FIELDS]] + $limit,
                ['dimension'],
            ),
            self::report(
                'get_acquisition',
                'Acquisition',
                'Visits per marketing channel, source, UTM medium/campaign/content/term, landing page or exit page. Each visit is attributed from its first pageview.',
                static function (SiteAnalytics $analytics, array $args): array {
                    $report = self::choice($args, 'report', self::ACQUISITION_REPORTS);
                    return ['report' => $report, 'values' => array_slice($analytics->acquisition()[$report], 0, self::limit($args))];
                },
                ['report' => ['type' => 'string', 'enum' => self::ACQUISITION_REPORTS]] + $limit,
                ['report'],
            ),
            self::report(
                'get_sessions',
                'Visits',
                'Individual anonymous visits, newest first: when they started, how long they lasted, where the visitor came from, their country and device, and every page they viewed and event they triggered, in order, with the events\' properties. visit_number tells a returning visitor apart (2 for their second visit). Visitors are recognised for a calendar month at most and no IP address is ever stored. Pass visit_id to read one visit in full.',
                static function (SiteAnalytics $analytics, array $args): array {
                    $visitId = self::text($args, 'visit_id');
                    if ($visitId === null) {
                        return ['visits' => $analytics->latestVisits(min(50, self::limit($args)))];
                    }
                    return ['visit' => $analytics->visit($visitId) ?? throw new InvalidArgumentException('No visit has the ID "' . $visitId . '": use a visit_id from get_sessions.')];
                },
                [
                    'visit_id' => ['type' => 'string', 'description' => 'One visit to read in full, from a previous get_sessions answer.'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10, 'description' => 'Maximum number of visits to return.'],
                ],
            ),
            self::report(
                'get_journey',
                'Journey between two steps',
                'The paths visits took from an entry step to an exit step (each a page or a custom event): how many visits went through the entry, how many then reached the exit, and the most common paths from the entry to the exit (the 3 most common next steps at each point, up to 12 steps). Pass entry and exit, or the name of a journey saved in the dashboard. Without either, lists the saved journeys.',
                static fn(SiteAnalytics $analytics, array $args): array => self::journey($analytics, $args),
                [
                    'entry' => ['type' => 'object', 'description' => 'A page path or an exact event name, e.g. {"page": "/"}.', 'properties' => ['page' => ['type' => 'string'], 'event' => ['type' => 'string']], 'additionalProperties' => false],
                    'exit' => ['type' => 'object', 'description' => 'A page path or an exact event name, e.g. {"event": "Signup"}.', 'properties' => ['page' => ['type' => 'string'], 'event' => ['type' => 'string']], 'additionalProperties' => false],
                    'journey' => ['type' => 'string', 'description' => 'Name of a journey saved in the dashboard.'],
                ],
            ),
            self::report(
                'get_blocked_traffic',
                'Blocked traffic',
                'Requests kept out of the analytics: bots and crawlers by category (search engines, AI crawlers, monitoring, scripts...) with the named robots that sent them, the reasons they were rejected and the websites they targeted, plus internal traffic and visitors who left without interacting. Filters do not apply to it.',
                static fn(SiteAnalytics $analytics, array $args): array => self::blockedTraffic($analytics->botActivity(), self::limit($args)),
                $limit,
            ),
        ];
    }

    /**
     * A tool reporting on one website over one period.
     *
     * @param Closure(SiteAnalytics, array<string, mixed>): array<string, mixed> $report
     * @param array<string, array<string, mixed>> $properties arguments added to the shared ones
     * @param list<string> $required
     */
    private static function report(string $name, string $title, string $description, Closure $report, array $properties = [], array $required = []): Tool
    {
        return new Tool($name, $title, $description, self::sharedProperties() + $properties, $required, static function (array $args) use ($report): array {
            $analytics = SiteAnalytics::open(self::siteId($args), self::period($args), self::filters($args));
            return [
                'site_id' => $analytics->siteId,
                'period' => ['range' => $analytics->period->range, 'start' => gmdate('c', $analytics->period->start), 'end' => gmdate('c', $analytics->period->end)],
            ] + $report($analytics, $args);
        });
    }

    /** @return array<string, array<string, mixed>> */
    private static function sharedProperties(): array
    {
        $values = ['type' => 'array', 'items' => ['type' => 'string']];
        return [
            'site_id' => ['type' => 'string', 'description' => 'Website ID from list_sites. Optional when the instance tracks a single website.'],
            'range' => ['type' => 'string', 'enum' => Period::RANGES, 'default' => '7d', 'description' => 'Period ending now ("6m" is 180 days), or "custom" with from/to.'],
            'from' => ['type' => 'string', 'format' => 'date', 'description' => 'First day (YYYY-MM-DD, UTC) of a custom period.'],
            'to' => ['type' => 'string', 'format' => 'date', 'description' => 'Last day (YYYY-MM-DD, UTC) of a custom period, included.'],
            'filters' => [
                'type' => 'object',
                'description' => 'Only count visits that viewed a matching page, or for "event" that triggered one of these events (exact names). Values of one dimension are combined with OR, dimensions with AND. Referrers are domains such as "google.com" or "direct".',
                'properties' => array_fill_keys(self::FILTER_DIMENSIONS, $values),
                'additionalProperties' => false,
            ],
        ];
    }

    private static function siteId(array $args): string
    {
        if (isset($args['site_id']) && is_string($args['site_id']) && $args['site_id'] !== '') {
            return $args['site_id'];
        }
        $sites = SiteRegistry::all();
        if (count($sites) === 1) {
            return (string) $sites[0]['id'];
        }
        throw new InvalidArgumentException('site_id is required: call list_sites to get the available websites.');
    }

    private static function period(array $args): Period
    {
        $range = $args['range'] ?? '7d';
        if (!is_string($range) || !in_array($range, Period::RANGES, true)) {
            throw new InvalidArgumentException('range must be one of: ' . implode(', ', Period::RANGES) . '.');
        }
        foreach (['from', 'to'] as $key) {
            if (isset($args[$key]) && (!is_string($args[$key]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $args[$key]))) {
                throw new InvalidArgumentException("{$key} must be a date formatted as YYYY-MM-DD.");
            }
        }
        if ($range === 'custom' && (empty($args['from']) || empty($args['to']))) {
            throw new InvalidArgumentException('A custom range needs both from and to.');
        }
        return Period::from($range, $args['from'] ?? null, $args['to'] ?? null);
    }

    /** @return array<string, string[]> */
    private static function filters(array $args): array
    {
        $filters = is_array($args['filters'] ?? null) ? $args['filters'] : [];
        $query = [];
        foreach (self::FILTER_DIMENSIONS as $dimension) {
            if (isset($filters[$dimension])) {
                $query['filter_' . $dimension] = $filters[$dimension];
            }
        }
        return AnalyticsFilters::fromRequest($query);
    }

    private static function limit(array $args): int
    {
        return max(1, min(100, (int) ($args['limit'] ?? 10)));
    }

    /** A non-empty string argument, or null. */
    private static function text(array $args, string $key): ?string
    {
        $value = $args[$key] ?? null;
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Reshapes the summary for an assistant: plain numbers, visits only, and for
     * every metric its previous value, its absolute change and its change in percent.
     */
    private static function overview(array $summary): array
    {
        $current = [
            'visitors' => $summary['visitors'],
            'returning_visitors' => $summary['returning_visitors'],
            'visits' => $summary['sessions'],
            'pageviews' => $summary['pageviews'],
            'events' => $summary['events'],
            'bounce_rate' => $summary['bounce_rate'],
            'avg_duration_seconds' => $summary['avg_duration_seconds'],
        ];
        $previous = $summary['previous'] === null ? null : ['visits' => $summary['previous']['sessions']] + $summary['previous'];

        $metrics = [];
        foreach ($current as $name => $value) {
            $before = $previous[$name] ?? null;
            $metrics[$name] = ['value' => $value, 'previous' => $before];
            if ($before !== null) {
                $metrics[$name]['change'] = round($value - $before, 1);
                $metrics[$name]['change_percent'] = $before == 0 ? null : round(($value - $before) / $before * 100, 1);
            }
        }
        return $metrics + ['live_visitors' => ['value' => $summary['live_visitors']]];
    }

    /** Conversion through ad-hoc or saved funnel steps; the saved funnels when neither is given. */
    private static function funnel(SiteAnalytics $analytics, array $args): array
    {
        $saved = $analytics->savedFunnels();
        $name = self::text($args, 'funnel');
        if ($name !== null) {
            $match = array_values(array_filter($saved, static fn(array $funnel): bool => strcasecmp($funnel['name'], $name) === 0));
            if ($match === []) {
                throw new InvalidArgumentException('No saved funnel is named "' . $name . '". Saved funnels: ' . (implode(', ', array_column($saved, 'name')) ?: 'none') . '.');
            }
            $steps = $match[0]['steps'];
        } elseif (isset($args['steps'])) {
            $steps = self::funnelSteps($args['steps']);
        } else {
            return ['saved_funnels' => array_map(static fn(array $funnel): array => [
                'name' => $funnel['name'],
                'kind' => $funnel['kind'],
                'steps' => array_map(static fn(array $step): string => self::describeStep($step), $funnel['steps']),
            ], $saved)];
        }

        $progress = $analytics->funnelProgress($steps);
        $first = $progress[0] ?? 0;
        $rows = [];
        foreach ($steps as $i => $step) {
            $previous = $i === 0 ? $first : $progress[$i - 1];
            $rows[] = [
                'step' => self::describeStep($step),
                'visits' => $progress[$i],
                'conversion_from_previous' => $previous > 0 ? round($progress[$i] / $previous * 100, 1) : 0,
                'conversion_from_first' => $first > 0 ? round($progress[$i] / $first * 100, 1) : 0,
            ];
        }
        return ['steps' => $rows];
    }

    /** The paths between a journey's entry and exit; the saved journeys when neither is given. */
    private static function journey(SiteAnalytics $analytics, array $args): array
    {
        $saved = array_values(array_filter($analytics->savedFunnels(), static fn(array $funnel): bool => $funnel['kind'] === 'journey'));
        $name = self::text($args, 'journey');
        if ($name !== null) {
            $match = array_values(array_filter($saved, static fn(array $journey): bool => strcasecmp($journey['name'], $name) === 0));
            if ($match === []) {
                throw new InvalidArgumentException('No saved journey is named "' . $name . '". Saved journeys: ' . (implode(', ', array_column($saved, 'name')) ?: 'none') . '.');
            }
            $steps = array_slice($match[0]['steps'], 0, 2);
        } elseif (isset($args['entry']) || isset($args['exit'])) {
            $steps = self::funnelSteps([$args['entry'] ?? null, $args['exit'] ?? null]);
        } else {
            return ['saved_journeys' => array_map(static fn(array $journey): array => [
                'name' => $journey['name'],
                'entry' => self::describeStep($journey['steps'][0] ?? []),
                'exit' => self::describeStep($journey['steps'][1] ?? []),
            ], $saved)];
        }

        $journey = $analytics->journey($steps);
        $exit = self::describeStep($steps[1]);
        // Every branch of the tree stops at the exit: a path is complete when it reaches it.
        $paths = [];
        $walk = static function (array $node, array $path) use (&$walk, &$paths, $exit): void {
            foreach ($node['children'] ?? [] as $child) {
                $next = [...$path, self::describeStep($child)];
                if ($next[count($next) - 1] === $exit) {
                    $paths[] = ['steps' => $next, 'visits' => $child['count']];
                } else {
                    $walk($child, $next);
                }
            }
        };
        $walk($journey['tree'], []);
        usort($paths, static fn(array $a, array $b): int => $b['visits'] <=> $a['visits']);

        return [
            'entry' => self::describeStep($steps[0]),
            'exit' => $exit,
            'entered' => $journey['entered'],
            'completed' => $journey['completed'],
            'completion_rate' => $journey['entered'] > 0 ? round($journey['completed'] / $journey['entered'] * 100, 1) : 0,
            'top_paths' => array_slice($paths, 0, 10),
        ];
    }

    /** The bot report without its hourly series and raw user agents. */
    private static function blockedTraffic(array $bots, int $limit): array
    {
        return [
            'total' => $bots['total'],
            'internal_traffic' => $bots['internal'],
            'left_without_interacting' => $bots['left'],
            'categories' => array_values(array_filter(
                array_map(static fn(array $category): array => ['category' => $category['category'], 'requests' => $category['count']], $bots['categories']),
                static fn(array $category): bool => $category['requests'] > 0,
            )),
            'robots' => array_slice(array_map(static fn(array $bot): array => [
                'name' => $bot['name'],
                'operator' => $bot['operator'] ?? '',
                'category' => $bot['category'],
                'requests' => $bot['count'],
            ], $bots['bots']), 0, $limit),
            'reasons' => array_slice($bots['reasons'], 0, $limit),
            'targeted_websites' => array_slice($bots['origins'], 0, $limit),
        ];
    }

    /** @return list<array{type: string, value: string}> */
    private static function funnelSteps(mixed $steps): array
    {
        if (!is_array($steps) || $steps === [] || count($steps) > 12) {
            throw new InvalidArgumentException('steps must list between 1 and 12 steps, e.g. [{"page": "/pricing"}, {"event": "Signup"}].');
        }
        return array_map(static function (mixed $step): array {
            $page = is_array($step) ? self::text($step, 'page') : null;
            $event = is_array($step) ? self::text($step, 'event') : null;
            if (($page === null) === ($event === null)) {
                throw new InvalidArgumentException('Each step needs either a page or an event, e.g. {"page": "/pricing"} or {"event": "Signup"}.');
            }
            return $page !== null ? ['type' => 'pageview', 'value' => $page] : ['type' => 'event', 'value' => (string) $event];
        }, array_values($steps));
    }

    private static function describeStep(array $step): string
    {
        return ($step['type'] ?? '') === 'pageview' ? 'Page ' . ($step['value'] ?? '') : 'Event ' . ($step['value'] ?? '');
    }

    /** @param list<string> $allowed */
    private static function choice(array $args, string $key, array $allowed): string
    {
        $value = $args[$key] ?? null;
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("{$key} must be one of: " . implode(', ', $allowed) . '.');
        }
        return $value;
    }
}
