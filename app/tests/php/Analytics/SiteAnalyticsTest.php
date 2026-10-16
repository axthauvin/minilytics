<?php

declare(strict_types=1);

namespace Minilytics\Tests\Analytics;

use Minilytics\Analytics\Period;
use Minilytics\Analytics\SiteAnalytics;
use Minilytics\Database\Connections;
use PHPUnit\Framework\TestCase;

final class SiteAnalyticsTest extends TestCase
{
    private int $now;

    protected function setUp(): void
    {
        $this->now = time();
        Connections::forSite('test_site')->exec('DELETE FROM user_activity');
        Connections::forSite('test_site')->exec('DELETE FROM bot_activity');
    }

    public function testSummaryCountsVisitorsSessionsAndPageviews(): void
    {
        $this->record('s1', 'v1', 'pageview', ['path' => '/'], 3600);
        $this->record('s1', 'v1', 'pageview', ['path' => '/pricing'], 3500);
        $this->record('s2', 'v1', 'pageview', ['path' => '/'], 1800);
        $this->record('s3', 'v2', 'signup', [], 600);

        $summary = $this->analytics('7d')->summary();

        $this->assertSame(2, $summary['visitors']);
        $this->assertSame(3, $summary['sessions']);
        $this->assertSame(3, $summary['pageviews']);
        $this->assertSame(4, $summary['events']);
    }

    public function testVisitsWithASingleActionCountAsBounces(): void
    {
        $this->record('s1', 'v1', 'pageview', ['path' => '/'], 3600);
        $this->record('s1', 'v1', 'pageview', ['path' => '/pricing'], 3540);
        $this->record('s2', 'v2', 'pageview', ['path' => '/'], 600);

        $summary = $this->analytics('7d')->summary();

        $this->assertSame(50.0, $summary['bounce_rate']);
        $this->assertSame(30.0, $summary['avg_duration_seconds']);
    }

    public function testEventsOutsideThePeriodAreIgnored(): void
    {
        $this->record('old', 'v1', 'pageview', ['path' => '/'], 10 * 86400);
        $this->record('new', 'v2', 'pageview', ['path' => '/'], 3600);

        $this->assertSame(1, $this->analytics('7d')->summary()['visitors']);
    }

    public function testSummaryComparesWithThePreviousPeriod(): void
    {
        $this->record('before', 'v1', 'pageview', ['path' => '/'], 8 * 86400);
        $this->record('now1', 'v2', 'pageview', ['path' => '/'], 3600);
        $this->record('now2', 'v3', 'pageview', ['path' => '/'], 1800);

        $this->assertSame('+1', $this->analytics('7d')->summary()['deltas']['visitors']);
    }

    public function testTopPagesAreSortedByViews(): void
    {
        $this->record('s1', 'v1', 'pageview', ['path' => '/pricing', 'title' => 'Pricing'], 3600);
        $this->record('s2', 'v2', 'pageview', ['path' => '/pricing', 'title' => 'Pricing'], 3000);
        $this->record('s2', 'v2', 'pageview', ['path' => '/'], 2900);

        $pages = $this->analytics('7d')->topPages();

        $this->assertSame(['/pricing', '/'], array_column($pages, 'path'));
        $this->assertSame('Pricing', $pages[0]['title']);
        $this->assertSame(2, $pages[0]['views']);
        $this->assertSame(66.7, $pages[0]['percentage']);
    }

    public function testReferrersCountEachVisitOnceFromItsFirstPageview(): void
    {
        $this->record('s1', 'v1', 'pageview', ['path' => '/', 'referrer' => 'google.com'], 3600);
        $this->record('s1', 'v1', 'pageview', ['path' => '/pricing', 'referrer' => 'google.com'], 3500);
        $this->record('s1', 'v1', 'pageview', ['path' => '/about', 'referrer' => 'google.com'], 3400);
        $this->record('s2', 'v2', 'pageview', ['path' => '/', 'referrer' => 'news.ycombinator.com'], 1800);
        $this->record('s3', 'v3', 'pageview', ['path' => '/'], 900);
        $this->record('s3', 'v3', 'pageview', ['path' => '/pricing', 'referrer' => 'google.com'], 800);

        $referrers = $this->analytics('7d')->topReferrers();

        $this->assertEquals(['google.com' => 1, 'news.ycombinator.com' => 1, 'direct' => 1], array_column($referrers, 'visits', 'domain'));
        $this->assertSame([33.3, 33.3, 33.3], array_column($referrers, 'percentage'));
    }

    public function testBotActivityIsSummarisedByReasonAndAgent(): void
    {
        $this->recordBot('known_bot_user_agent', 'Googlebot/2.1', 'ip-1', 3600);
        $this->recordBot('known_bot_user_agent', 'Googlebot/2.1', 'ip-2', 1800);
        $this->recordBot('webdriver', 'Mozilla/5.0', 'ip-1', 600);
        $this->recordBot('webdriver', 'Old', 'ip-3', 10 * 86400);

        $bots = $this->analytics('7d')->botActivity();

        $this->assertSame(3, $bots['total']);
        $this->assertSame(2, $bots['sources']);
        $this->assertSame([['reason' => 'known_bot_user_agent', 'count' => 2], ['reason' => 'webdriver', 'count' => 1]], $bots['reasons']);
        $this->assertCount(3, $bots['recent']);
    }

    public function testBotActivityIsGroupedByRobotAndCategory(): void
    {
        $chrome = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36';
        $this->recordBot('known_bot_user_agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'ip-1', 3600);
        $this->recordBot('known_bot_user_agent', 'Googlebot-Image/1.0', 'ip-2', 1800);
        $this->recordBot('known_bot_user_agent', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ChatGPT-User/1.0; +https://openai.com/bot)', 'ip-3', 1800);
        $this->recordBot('client:platform_mismatch', $chrome, 'ip-4', 600);
        $this->recordBot('internal_traffic', $chrome, 'ip-5', 600);

        $bots = $this->analytics('7d')->botActivity();

        $this->assertSame(1, $bots['internal']);
        $this->assertSame(['ai_answers' => 1, 'ai_training' => 0, 'search' => 2, 'automated' => 1, 'other' => 0], array_column($bots['categories'], 'count', 'category'));
        $this->assertSame(['Google' => 2, 'ChatGPT' => 1, 'Chrome · Linux' => 1], array_column($bots['bots'], 'count', 'name'));
        $this->assertSame(4, array_sum(array_column($bots['series'], 'count')));
        $this->assertSame('Chrome · Linux', $bots['recent'][1]['bot']['name']);
        $this->assertNull($bots['recent'][0]['bot']);
    }

    private function analytics(string $range): SiteAnalytics
    {
        return SiteAnalytics::open('test_site', Period::from($range, now: $this->now));
    }

    /** Stores an event the way track.php does, `$secondsAgo` before now. */
    private function record(string $sessionId, string $visitorId, string $name, array $data, int $secondsAgo): void
    {
        $stmt = Connections::forSite('test_site')->prepare('INSERT INTO user_activity (session_id, visitor_id, action, timestamp) VALUES (:session, :visitor, :action, :timestamp)');
        $stmt->bindValue(':session', $sessionId, SQLITE3_TEXT);
        $stmt->bindValue(':visitor', $visitorId, SQLITE3_TEXT);
        $stmt->bindValue(':action', (string) json_encode(['name' => $name, 'data' => $data]), SQLITE3_TEXT);
        $stmt->bindValue(':timestamp', gmdate('Y-m-d H:i:s', $this->now - $secondsAgo), SQLITE3_TEXT);
        $stmt->execute();
    }

    private function recordBot(string $reason, string $userAgent, string $ip, int $secondsAgo): void
    {
        $stmt = Connections::forSite('test_site')->prepare('INSERT INTO bot_activity (reason, user_agent, origin, ip_hash, timestamp) VALUES (:reason, :ua, :origin, :ip, :timestamp)');
        $stmt->bindValue(':reason', $reason, SQLITE3_TEXT);
        $stmt->bindValue(':ua', $userAgent, SQLITE3_TEXT);
        $stmt->bindValue(':origin', 'example.com', SQLITE3_TEXT);
        $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
        $stmt->bindValue(':timestamp', gmdate('Y-m-d H:i:s', $this->now - $secondsAgo), SQLITE3_TEXT);
        $stmt->execute();
    }
}
