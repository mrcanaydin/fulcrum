<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Attributes;

/**
 * Guards a Query or Mutation field — requires an authenticated user in the
 * RequestContext.  If the context carries no user, the Executor returns an
 * "Unauthenticated." error and does NOT call the resolver.
 *
 * Usage:
 *   #[Query(name: 'me', type: 'User!')]
 *   #[Authenticated]
 *   public function me($root, array $args, RequestContext $ctx): array { ... }
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Authenticated {}
