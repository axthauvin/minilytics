<?php

declare(strict_types=1);

use Minilytics\Analytics\Period;
use Minilytics\Analytics\SiteAnalytics;
use Minilytics\Auth\Auth;
use Minilytics\Database\Connections;
use Minilytics\Database\DatabaseConnection;
use Minilytics\Sites\SiteRegistry;

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../vendor/autoload.php';
// Funnels of public demo sites are readable; creating or deleting them needs an account.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    Auth::requireSiteAccess((string) ($_GET['site_id'] ?? ''));
} else {
    Auth::requireLogin();
}

function setupFunnels(DatabaseConnection $db): void
{
    if ($db->isMysql()) {
        $db->exec("CREATE TABLE IF NOT EXISTS funnels (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, kind VARCHAR(20) NOT NULL DEFAULT 'funnel', steps JSON NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS funnels (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, kind TEXT NOT NULL DEFAULT 'funnel', steps TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $columns = $db->query('PRAGMA table_info(funnels)');
    $hasKind = false;
    while ($column = $columns->fetchArray(SQLITE3_ASSOC)) {
        if ($column['name'] === 'kind') {
            $hasKind = true;
        }
    }
    if (!$hasKind) {
        $db->exec("ALTER TABLE funnels ADD COLUMN kind TEXT NOT NULL DEFAULT 'funnel'");
    }
}
function cleanSteps(mixed $steps): array
{
    if (!is_array($steps) || count($steps) < 1 || count($steps) > 12) {
        throw new InvalidArgumentException('Add between 1 and 12 steps.');
    }
    $out = [];
    foreach ($steps as $step) {
        $type = ($step['type'] ?? '') === 'pageview' ? 'pageview' : 'event';
        $value = trim((string) ($step['value'] ?? ''));
        $label = trim((string) ($step['label'] ?? ''));
        if ($value === '') {
            throw new InvalidArgumentException('Every step needs an event or page.');
        }
        $out[] = ['type' => $type, 'value' => mb_substr($value, 0, 180), 'label' => mb_substr($label ?: $value, 0, 80)];
    }
    return $out;
}
try {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $siteId = $_GET['site_id'] ?? $payload['site_id'] ?? null;
    $site = SiteRegistry::requireId($siteId);
    $db = Connections::forSite($site);
    setupFunnels($db);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Give this funnel a name.');
        }
        $steps = cleanSteps($payload['steps'] ?? []);
        $kind = in_array($payload['kind'] ?? 'funnel', ['funnel', 'goal', 'journey'], true) ? $payload['kind'] : 'funnel';
        if ($kind === 'goal') {
            $steps = [reset($steps)];
        }
        if ($kind === 'journey') {
            if (count($steps) < 2) {
                throw new InvalidArgumentException('A journey needs an entry and an exit.');
            } $steps = [$steps[0], $steps[1]];
        }
        $id = (int) ($payload['id'] ?? 0);
        if ($id) {
            $st = $db->prepare('UPDATE funnels SET name=:name, kind=:kind, steps=:steps WHERE id=:id');
            $st->bindValue(':id', $id, SQLITE3_INTEGER);
        } else {
            $st = $db->prepare('INSERT INTO funnels (name, kind, steps) VALUES (:name, :kind, :steps)');
        }
        $st->bindValue(':name', mb_substr($name, 0, 80), SQLITE3_TEXT);
        $st->bindValue(':kind', $kind, SQLITE3_TEXT);
        $st->bindValue(':steps', json_encode($steps), SQLITE3_TEXT);
        $st->execute();
        echo json_encode(['success' => true, 'id' => $id ?: $db->lastInsertRowID()]);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        $st = $db->prepare('DELETE FROM funnels WHERE id=:id');
        $st->bindValue(':id', $id, SQLITE3_INTEGER);
        $st->execute();
        echo json_encode(['success' => true]);
        exit;
    }
    $period = Period::fromRequest($_GET);
    [$start, $end] = [$period->startText(), $period->endText()];
    $analytics = new SiteAnalytics($db, $site, $period);
    $items = [];
    $result = $db->query('SELECT * FROM funnels ORDER BY created_at DESC, id DESC');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $steps = json_decode($row['steps'], true) ?: [];
        $kind = $row['kind'] ?: 'funnel';
        $item = ['id' => (int) $row['id'], 'name' => $row['name'], 'kind' => $kind, 'steps' => $steps, 'progress' => $analytics->funnelProgress($steps)];
        if ($kind === 'journey') {
            $item['journey'] = $analytics->journey($steps);
            if (($item['journey']['completed'] ?? 0) === 0) {
                $item['journey']['fallback'] = $analytics->journey($steps, true);
            }
        } $items[] = $item;
    }
    // The builder exposes the site's complete vocabulary. The selected period
    // only affects the funnel's figures, never which event/page can be chosen.
    $events = [];
    $er = $db->query("SELECT DISTINCT json_extract(action, '$.name') AS name FROM user_activity ORDER BY name");
    while ($r = $er->fetchArray(SQLITE3_ASSOC)) {
        if ($r['name'] && $r['name'] !== 'pageview') {
            $events[] = $r['name'];
        }
    }
    $pages = [];
    $pr = $db->query("SELECT DISTINCT json_extract(action, '$.data.path') AS path FROM user_activity WHERE json_extract(action, '$.name')='pageview' ORDER BY path");
    while ($r = $pr->fetchArray(SQLITE3_ASSOC)) {
        if ($r['path'] !== null && $r['path'] !== '') {
            $pages[] = $r['path'];
        }
    }
    $audienceStmt = $db->prepare('SELECT COUNT(DISTINCT COALESCE(visitor_id, session_id)) FROM user_activity WHERE timestamp >= :start AND timestamp <= :end');
    $audienceStmt->bindValue(':start', $start, SQLITE3_TEXT);
    $audienceStmt->bindValue(':end', $end, SQLITE3_TEXT);
    $audience = (int) $audienceStmt->execute()->fetchArray(SQLITE3_NUM)[0];
    echo json_encode(['success' => true,'funnels' => $items,'audience' => $audience,'events' => $events,'pages' => $pages]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
