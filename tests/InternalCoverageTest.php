<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Print_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Echo_;
use rajmundtoth0\PHPStanForbidden\Enums\NodeType;
use rajmundtoth0\PHPStanForbidden\Models\ForbiddenMethodPattern;
use rajmundtoth0\PHPStanForbidden\Models\ForbiddenNode;
use rajmundtoth0\PHPStanForbidden\Models\ForbiddenNodes;
use rajmundtoth0\PHPStanForbidden\Services\ForbiddenNodeService;
use rajmundtoth0\PHPStanForbidden\Trait\PathNormalizer;

it('covers forbidden method pattern guard branches', function (): void {
    $wildcard = new ForbiddenMethodPattern('*', '*');
    expect($wildcard->matches(null, null))->toBeFalse();
    expect($wildcard->matches(null, 'anything'))->toBeTrue();

    $exact = new ForbiddenMethodPattern(\DateTimeImmutable::class, 'format');
    expect($exact->matches(null, 'format'))->toBeFalse();
});

it('covers forbidden node parsing and matching edge cases', function (): void {
    expect(ForbiddenNode::fromArray(['type' => 'Not_A_Real_Type']))->toBeNull();

    $functionNode = ForbiddenNode::fromArray([
        'type'          => 'Expr_FuncCall',
        'functions'     => 'invalid',
        'include_paths' => ['src', '', 1],
    ]);
    expect($functionNode)->toBeInstanceOf(ForbiddenNode::class);
    expect($functionNode?->functions)->toBeNull();
    expect($functionNode?->isFunctionForbidden(null))->toBeTrue();

    $namedFunctionNode = new ForbiddenNode(NodeType::NODE_EXPR_FUNC_CALL, ['print_r', '', 1, 'PRINT_R']);
    expect($namedFunctionNode->functions)->toBe(['print_r']);
    expect($namedFunctionNode->isFunctionForbidden(null))->toBeFalse();

    $invalidMethodsNode = ForbiddenNode::fromArray([
        'type'    => 'Expr_MethodCall',
        'methods' => 'invalid',
    ]);
    expect($invalidMethodsNode?->methods)->toBe([]);

    $methodsNode = ForbiddenNode::fromArray([
        'type'    => 'Expr_MethodCall',
        'methods' => [
            'format',
            123,
            ['class' => \DateTimeImmutable::class],
            ['class' => 123, 'method' => 'SEND'],
            ['class' => '*', 'method' => 'send'],
            ['class' => '*', 'method' => 'send'],
        ],
    ]);
    expect($methodsNode?->methods)->toHaveCount(2);
    expect($methodsNode?->isMethodForbidden([], null))->toBeFalse();
    expect($methodsNode?->isMethodForbidden([], 'format'))->toBeTrue();

    $legacyMethodsNode = ForbiddenNode::fromArray([
        'type'      => 'Expr_MethodCall',
        'functions' => ['format', '', 123],
    ]);
    expect($legacyMethodsNode?->methods)->toHaveCount(1);
});

it('covers forbidden nodes container filtering branches', function (): void {
    $config = ForbiddenNodes::fromArray([
        'nodes' => [
            'invalid',
            ['type' => 'Not_A_Real_Type'],
            ['type' => 'Expr_Print', 'functions' => null],
        ],
        'include_paths' => ['tests', '', 123],
    ]);

    expect($config->nodes)->toHaveCount(1);
    expect($config->includePaths)->toBe(['tests/']);
});

it('covers path normalizer invalid path filtering', function (): void {
    $normalizer = new class {
        use PathNormalizer;
    };

    expect($normalizer->normalizePaths(['foo\\bar', '', 123, 'foo/bar']))->toBe(['foo/bar/']);
});

it('covers forbidden node service class-name normalization filtering', function (): void {
    $service             = new ForbiddenNodeService();
    $normalizeClassNames = Closure::bind(
        /**
         * @param list<mixed> $classNames
         * @return list<string>
         */
        fn (array $classNames): array => $this->normalizeClassNames($classNames),
        $service,
        ForbiddenNodeService::class,
    );

    expect($normalizeClassNames(['', \DateTimeImmutable::class, \DateTimeImmutable::class]))->toBe([\DateTimeImmutable::class]);
});

it('covers forbidden node service prefiltering', function (): void {
    $config = ForbiddenNodes::fromArray([
        'nodes' => [
            ['type' => 'Expr_Print', 'functions' => null],
        ],
        'use_from_tests'                => false,
        'forbid_dynamic_function_calls' => true,
    ]);
    $service = new ForbiddenNodeService();

    expect($config->hasNodeType('Expr_Print'))->toBeTrue();
    expect($config->hasNodeType('Stmt_Echo'))->toBeFalse();
    expect($config->nodesForType('Expr_Print'))->toHaveCount(1);
    expect($config->nodesForType('Stmt_Echo'))->toBe([]);

    expect($service->shouldProcessNode($config, new Print_(new String_('x'))))->toBeTrue();
    expect($service->shouldProcessNode($config, new Echo_([new String_('x')])))->toBeFalse();
    expect($service->shouldProcessNode($config, new FuncCall(new Variable('fn'))))->toBeTrue();

    $isDynamicFunctionCall = Closure::bind(
        fn (Node $node): bool => $this->isDynamicFunctionCall($node),
        $service,
        ForbiddenNodeService::class,
    );
    expect($isDynamicFunctionCall(new Echo_([new String_('x')])))->toBeFalse();
});
