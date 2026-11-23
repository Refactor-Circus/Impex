<?php

declare(strict_types=1);

namespace Workbench\App\OpenApi;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\Type;
use RefactorCircus\Impex\Domains\Channel\Resources\ChannelResource;
use RefactorCircus\Impex\Domains\Flow\Mcp\Tools\ListFlowsTool;
use RefactorCircus\Impex\Domains\Message\Data\ListMessagesData;
use RefactorCircus\Impex\Domains\Message\Resources\MessageResource;
use RefactorCircus\Impex\Domains\Run\Data\ListRunsData;
use RefactorCircus\Impex\Domains\Run\Http\Resources\RunStatsResource;
use RefactorCircus\Impex\Domains\Run\Http\Resources\SettingsSummaryResource;
use RefactorCircus\Impex\Domains\Run\Resources\RunResource;
use RefactorCircus\Impex\Domains\Subscription\Data\ListSubscriptionsData;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriptionResource;

/**
 * Writes the success response of the operator API's read operations from the
 * resources' own `schema()`.
 *
 * The controllers answer through `$request->persist()`, so Scramble cannot
 * always see the resource behind a response: it leaves the flow and channel
 * lists untyped. The resource schemas are what the API and MCP tools promise,
 * so for these operations the document states them rather than an inference.
 */
final class ResourceResponses extends OperationExtension
{
    /**
     * The success response body of each operation, keyed by route name.
     *
     * @return array<string, ObjectType>
     */
    public static function responses(JsonSchema $schema): array
    {
        return [
            'impex.stats.show' => self::item($schema, RunStatsResource::schema($schema)),
            'impex.search.index' => self::items($schema, RunResource::schema($schema)),
            'impex.settings-summary.show' => self::item($schema, SettingsSummaryResource::schema($schema)),
            'impex.runs.index' => $schema->object(RunResource::collectionSchema($schema, ListRunsData::pagination())),
            'impex.runs.show' => self::item($schema, RunResource::schema($schema)),
            'impex.messages.index' => $schema->object(MessageResource::collectionSchema($schema, ListMessagesData::pagination())),
            // The flow list has no resource: its MCP tool declares the same payload.
            'impex.flows.index' => $schema->object(app(ListFlowsTool::class)->outputSchema($schema)),
            'impex.channels.index' => self::items($schema, ChannelResource::schema($schema)),
            'impex.subscriptions.index' => $schema->object(SubscriptionResource::collectionSchema($schema, ListSubscriptionsData::pagination())),
        ];
    }

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $body = self::responses(new JsonSchemaTypeFactory)[(string) $routeInfo->route->getName()] ?? null;

        if ($body === null) {
            return;
        }

        $description = '';
        $others = [];

        foreach ($operation->responses ?? [] as $response) {
            if ($response instanceof Response && self::isSuccess($response)) {
                $description = $description ?: $response->description;
            } else {
                $others[] = $response;
            }
        }

        $operation->responses = [
            Response::make(200)
                ->setDescription($description)
                ->setContent('application/json', Schema::fromType(self::raw($body->toArray()))),
            ...$others,
        ];
    }

    /**
     * @param  array<string, Type>  $fields
     */
    private static function item(JsonSchema $schema, array $fields): ObjectType
    {
        return $schema->object(['data' => $schema->object($fields)->required()]);
    }

    /**
     * @param  array<string, Type>  $fields
     */
    private static function items(JsonSchema $schema, array $fields): ObjectType
    {
        return $schema->object(['data' => $schema->array()->items($schema->object($fields))->required()]);
    }

    private static function isSuccess(Response $response): bool
    {
        return is_numeric($response->code) && (int) $response->code >= 200 && (int) $response->code < 300;
    }

    /**
     * A JSON Schema written as it is: OpenAPI 3.1 schemas are JSON Schema.
     *
     * @param  array<string, mixed>  $schema
     */
    private static function raw(array $schema): OpenApiType
    {
        return new class($schema) extends OpenApiType
        {
            /**
             * @param  array<string, mixed>  $schema
             */
            public function __construct(private readonly array $schema)
            {
                parent::__construct('object');
            }

            public function serializeAs31(): mixed
            {
                return $this->schema;
            }

            public function serializeAs32(): mixed
            {
                return $this->schema;
            }
        };
    }
}
