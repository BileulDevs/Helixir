<?php

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Remplace les réponses d'erreur générées par Scramble (format Laravel par défaut)
 * par le format de l'API : { "error": { "code", "message" } } (voir bootstrap/app.php),
 * et documente le 401 des routes protégées par une clé d'API.
 */
class ApiErrorResponses implements OperationTransformer
{
    /** Statut HTTP => [code d'erreur, description]. */
    private const ERRORS = [
        401 => ['unauthenticated', 'Missing, unknown or revoked API key'],
        404 => ['not_found', 'Resource not found'],
        422 => ['validation_failed', 'Invalid query parameters'],
        429 => ['rate_limited', 'Rate limit exceeded, see the Retry-After header'],
    ];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $responses = array_map(
            fn (Response|Reference $response) => $this->replaceIfError($response),
            $operation->responses ?? [],
        );

        if (in_array('api.key', $routeInfo->route->gatherMiddleware(), true)) {
            $responses[] = $this->errorResponse(401);
        }

        usort($responses, fn (Response $a, Response $b) => (int) $a->code <=> (int) $b->code);

        $operation->responses = $responses;
    }

    private function replaceIfError(Response|Reference $response): Response|Reference
    {
        $resolved = $response instanceof Reference ? $response->resolve() : $response;

        return isset(self::ERRORS[(int) $resolved->code])
            ? $this->errorResponse((int) $resolved->code)
            : $resolved;
    }

    private function errorResponse(int $status): Response
    {
        [$code, $description] = self::ERRORS[$status];

        $error = (new ObjectType)
            ->addProperty('code', (new StringType)->enum([$code]))
            ->addProperty('message', new StringType)
            ->setRequired(['code', 'message']);

        if ($status === 422) {
            $error->addProperty('fields', (new ObjectType)
                ->additionalProperties((new ArrayType)->setItems(new StringType))
                ->setDescription('Error messages for each invalid parameter.'));
            $error->addRequired(['fields']);
        }

        $body = (new ObjectType)
            ->addProperty('error', $error)
            ->setRequired(['error']);

        return Response::make($status)
            ->setDescription($description)
            ->setContent('application/json', Schema::fromType($body));
    }
}
