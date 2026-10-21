<?php

declare(strict_types=1);

namespace Minilytics\Mcp;

use InvalidArgumentException;
use stdClass;
use Throwable;

/**
 * Minimal Model Context Protocol server: JSON-RPC 2.0 with the `tools`
 * capability only. The HTTP transport lives in mcp.php.
 */
final class McpServer
{
    /**
     * Newest first: offered when the client asks for a version we do not know.
     * Older versions required JSON-RPC batches, which this server does not accept.
     */
    public const PROTOCOL_VERSIONS = ['2025-11-25', '2025-06-18'];
    private const VERSION = '1.0.0';

    /** @var array<string, Tool> */
    private array $tools = [];

    /**
     * @param list<Tool> $tools
     * @param bool $canWrite whether the connection may run the tools that save funnels
     */
    public function __construct(array $tools, private readonly bool $canWrite = false)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name] = $tool;
        }
    }

    /**
     * Handles one decoded JSON-RPC message. Returns the response, or null when
     * there is nothing to answer (notifications).
     */
    public function handle(mixed $message): ?array
    {
        if (!is_array($message) || ($message['jsonrpc'] ?? null) !== '2.0') {
            return self::error(null, -32600, 'Invalid request.');
        }
        if (!isset($message['method'])) {
            // A response to a server request: this server never sends any.
            return null;
        }
        if (!is_string($message['method'])) {
            return self::error($message['id'] ?? null, -32600, 'Invalid request.');
        }
        if (!array_key_exists('id', $message)) {
            // Notifications (initialized, cancelled…) need no answer.
            return null;
        }
        $id = $message['id'];
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        return match ($message['method']) {
            'initialize' => $this->result($id, $this->initialize($params)),
            'ping' => $this->result($id, new stdClass()),
            'tools/list' => $this->result($id, ['tools' => array_values(array_map(static fn(Tool $tool): array => $tool->definition(), $this->tools))]),
            'tools/call' => $this->callTool($id, $params),
            default => self::error($id, -32601, 'Method not found: ' . $message['method']),
        };
    }

    public static function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }

    private function initialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;
        return [
            'protocolVersion' => in_array($requested, self::PROTOCOL_VERSIONS, true) ? $requested : self::PROTOCOL_VERSIONS[0],
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => 'minilytics', 'title' => 'Minilytics', 'version' => self::VERSION],
            'instructions' => 'Access to the web analytics of this Minilytics instance. Call list_sites first, then pass a site_id to the other tools. Periods default to the last 7 days; dates are in UTC. Every tool reads, except create_funnel and update_funnel, which save funnels, goals and journeys in the dashboard when the user allowed this connection to edit. Nothing can be deleted.',
        ];
    }

    private function callTool(mixed $id, array $params): array
    {
        $tool = $this->tools[$params['name'] ?? ''] ?? null;
        if ($tool === null) {
            return self::error($id, -32602, 'Unknown tool: ' . (is_string($params['name'] ?? null) ? $params['name'] : ''));
        }
        if ($tool->writes && !$this->canWrite) {
            return $this->result($id, ['content' => [['type' => 'text', 'text' => 'This connection can only read the analytics: nothing was saved. Ask the user to allow it to edit funnels, goals and journeys: in Minilytics, open Settings → AI assistants, find this assistant under Authorized access, turn on "Can edit funnels", then try again.']], 'isError' => true]);
        }
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        try {
            $data = $tool->call($arguments);
        } catch (InvalidArgumentException $e) {
            // Invalid arguments are reported to the model so it can correct the call.
            return $this->result($id, ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true]);
        } catch (Throwable $e) {
            // Database errors can mention SQL or file paths: keep them in the server log, not in the conversation.
            error_log('Minilytics MCP tool ' . $tool->name . ' failed: ' . $e->getMessage());
            $failure = $tool->writes ? 'Nothing could be saved because of a server error.' : 'The analytics could not be read because of a server error.';
            return $this->result($id, ['content' => [['type' => 'text', 'text' => $failure . ' Try again later.']], 'isError' => true]);
        }
        return $this->result($id, ['content' => [['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]]]);
    }

    private function result(mixed $id, array|stdClass $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }
}
