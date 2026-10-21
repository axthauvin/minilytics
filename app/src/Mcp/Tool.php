<?php

declare(strict_types=1);

namespace Minilytics\Mcp;

use Closure;

/**
 * Defines a MCP tool. Tools read the analytics, except the few that save
 * funnels, goals and journeys ($writes): those only run for a connection the
 * user allowed to edit, and no tool ever deletes anything.
 */
final class Tool
{
    /**
     * @param array<string, array<string, mixed>> $properties JSON Schema of each argument
     * @param list<string> $required
     * @param Closure(array<string, mixed>): array<string, mixed> $handler
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $description,
        private readonly array $properties,
        private readonly array $required,
        private readonly Closure $handler,
        public readonly bool $writes = false,
    ) {}

    /** The tool as listed by `tools/list`. */
    public function definition(): array
    {
        $schema = ['type' => 'object', 'properties' => (object) $this->properties, 'additionalProperties' => false];
        if ($this->required) {
            $schema['required'] = $this->required;
        }
        return [
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'inputSchema' => $schema,
            'annotations' => $this->writes
                ? ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => false]
                : ['readOnlyHint' => true, 'openWorldHint' => false],
        ];
    }

    /** @param array<string, mixed> $arguments */
    public function call(array $arguments): array
    {
        return ($this->handler)($arguments);
    }
}
