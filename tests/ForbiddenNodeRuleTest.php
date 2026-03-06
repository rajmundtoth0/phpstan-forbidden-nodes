<?php

declare(strict_types=1);

use Tests\Support\RuleHarness;

uses(RuleHarness::class);

/**
 * @return array<string,mixed>
 */
function defaultLikeConfig(): array
{
    return [
        'include_paths' => [],
        'exclude_paths' => [],
        'nodes'         => [
            ['type' => 'Stmt_Echo', 'functions' => null],
            ['type' => 'Expr_Eval', 'functions' => null],
            ['type' => 'Expr_Exit', 'functions' => null],
            [
                'type'      => 'Expr_FuncCall',
                'functions' => [
                    'dd',
                    'debug_backtrace',
                    'dump',
                    'exec',
                    'passthru',
                    'phpinfo',
                    'print_r',
                    'proc_open',
                    'shell_exec',
                    'system',
                    'var_dump',
                ],
            ],
            ['type' => 'Expr_Print', 'functions' => null],
            ['type' => 'Expr_ShellExec', 'functions' => null],
        ],
        'use_from_tests'                => true,
        'forbid_dynamic_function_calls' => false,
        'non_ignorable'                 => true,
    ];
}

/**
 * @return array<string,mixed>
 */
function globalPathFilterConfig(): array
{
    $config                  = defaultLikeConfig();
    $config['include_paths'] = ['/Fixtures/included'];
    $config['exclude_paths'] = ['/Fixtures/excluded'];

    return $config;
}

/**
 * @return array<string,mixed>
 */
function nodeLevelPathFilterConfig(): array
{
    $config          = defaultLikeConfig();
    $config['nodes'] = [
        [
            'type'          => 'Expr_FuncCall',
            'functions'     => ['var_dump'],
            'include_paths' => ['/Fixtures/included'],
            'exclude_paths' => [],
        ],
        [
            'type'          => 'Expr_FuncCall',
            'functions'     => ['phpinfo'],
            'include_paths' => [],
            'exclude_paths' => ['/Fixtures/app'],
        ],
    ];

    return $config;
}

dataset('forbidden_cases', [
    'all default forbidden function calls' => [
        defaultLikeConfig(),
        [__DIR__.'/Fixtures/app/ForbiddenFunctionsAll.php'],
        [
            ['Forbidden code: function dd() is not allowed.', 3],
            ['Forbidden code: function debug_backtrace() is not allowed.', 4],
            ['Forbidden code: function dump() is not allowed.', 5],
            ['Forbidden code: function exec() is not allowed.', 6],
            ['Forbidden code: function passthru() is not allowed.', 7],
            ['Forbidden code: function phpinfo() is not allowed.', 8],
            ['Forbidden code: function print_r() is not allowed.', 9],
            ['Forbidden code: function proc_open() is not allowed.', 10],
            ['Forbidden code: function shell_exec() is not allowed.', 11],
            ['Forbidden code: function system() is not allowed.', 12],
            ['Forbidden code: function var_dump() is not allowed.', 13],
            ['Forbidden code: function VAR_DUMP() is not allowed.', 14],
        ],
    ],
    'all default forbidden node types' => [
        defaultLikeConfig(),
        [__DIR__.'/Fixtures/app/ForbiddenNodeTypes.php'],
        [
            ['Forbidden code: Stmt_Echo is not allowed.', 3],
            ['Forbidden code: Expr_Eval is not allowed.', 4],
            ['Forbidden code: Expr_Print is not allowed.', 5],
            ['Forbidden code: Expr_ShellExec is not allowed.', 6],
            ['Forbidden code: Expr_Exit is not allowed.', 7],
        ],
    ],
    'all function calls when functions is null' => [
        [
            'include_paths' => [],
            'exclude_paths' => [],
            'nodes'         => [
                ['type' => 'Expr_FuncCall', 'functions' => null],
            ],
            'use_from_tests'                => true,
            'forbid_dynamic_function_calls' => false,
            'non_ignorable'                 => true,
        ],
        [__DIR__.'/Fixtures/app/AnyFunctionCalls.php'],
        [
            ['Forbidden code: Expr_FuncCall is not allowed.', 3],
            ['Forbidden code: Expr_FuncCall is not allowed.', 4],
        ],
    ],
    'dynamic function call when flag enabled' => [
        (static function (): array {
            $config                                  = defaultLikeConfig();
            $config['forbid_dynamic_function_calls'] = true;

            return $config;
        })(),
        [__DIR__.'/Fixtures/app/DynamicFunctionCall.php'],
        [
            ['Forbidden code: dynamic function call is not allowed.', 4],
        ],
    ],
    'tests imports in non-test files' => [
        defaultLikeConfig(),
        [dirname(__DIR__).'/fixtures/non-test/UsesTestsImports.php'],
        [
            ['Forbidden code: importing Tests\\ namespace in non-test file.', 5],
            ['Forbidden code: importing Tests\\ namespace in non-test file.', 6],
        ],
    ],
    'global include/exclude catches included file' => [
        globalPathFilterConfig(),
        [__DIR__.'/Fixtures/included/ForbiddenIncludedFunction.php'],
        [
            ['Forbidden code: function var_dump() is not allowed.', 3],
        ],
    ],
    'node-level include/exclude catches included file' => [
        nodeLevelPathFilterConfig(),
        [__DIR__.'/Fixtures/included/ForbiddenIncludedFunction.php'],
        [
            ['Forbidden code: function var_dump() is not allowed.', 3],
        ],
    ],
    'method call class+method exact match' => [
        [
            'include_paths' => [],
            'exclude_paths' => [],
            'nodes'         => [
                [
                    'type'    => 'Expr_MethodCall',
                    'methods' => [
                        ['class' => \DateTimeImmutable::class, 'method' => 'format'],
                    ],
                ],
            ],
            'use_from_tests'                => true,
            'forbid_dynamic_function_calls' => false,
            'non_ignorable'                 => true,
        ],
        [__DIR__.'/Fixtures/app/ForbiddenMethodStaticCalls.php'],
        [
            ['Forbidden code: method DateTimeImmutable::format() is not allowed.', 6],
        ],
    ],
    'method call wildcard class match' => [
        [
            'include_paths' => [],
            'exclude_paths' => [],
            'nodes'         => [
                [
                    'type'    => 'Expr_MethodCall',
                    'methods' => [
                        ['class' => 'DateTime*', 'method' => 'format'],
                    ],
                ],
            ],
            'use_from_tests'                => true,
            'forbid_dynamic_function_calls' => false,
            'non_ignorable'                 => true,
        ],
        [__DIR__.'/Fixtures/app/ForbiddenMethodStaticCalls.php'],
        [
            ['Forbidden code: method DateTimeImmutable::format() is not allowed.', 6],
            ['Forbidden code: method DateTime::format() is not allowed.', 7],
        ],
    ],
    'static call wildcard method match' => [
        [
            'include_paths' => [],
            'exclude_paths' => [],
            'nodes'         => [
                [
                    'type'    => 'Expr_StaticCall',
                    'methods' => [
                        ['class' => \DateTimeImmutable::class, 'method' => 'createFrom*'],
                    ],
                ],
            ],
            'use_from_tests'                => true,
            'forbid_dynamic_function_calls' => false,
            'non_ignorable'                 => true,
        ],
        [__DIR__.'/Fixtures/app/ForbiddenMethodStaticCalls.php'],
        [
            ['Forbidden code: method DateTimeImmutable::createFromFormat() is not allowed.', 10],
            ['Forbidden code: method DateTimeImmutable::createFromMutable() is not allowed.', 12],
        ],
    ],
    'all method calls when methods is null' => [
        [
            'include_paths' => [],
            'exclude_paths' => [],
            'nodes'         => [
                [
                    'type'    => 'Expr_MethodCall',
                    'methods' => null,
                ],
            ],
            'use_from_tests'                => true,
            'forbid_dynamic_function_calls' => false,
            'non_ignorable'                 => true,
        ],
        [__DIR__.'/Fixtures/app/ForbiddenMethodStaticCalls.php'],
        [
            ['Forbidden code: method DateTimeImmutable::format() is not allowed.', 6],
            ['Forbidden code: method DateTime::format() is not allowed.', 7],
            ['Forbidden code: method DateTimeImmutable::getTimestamp() is not allowed.', 8],
        ],
    ],
    'all method calls when methods key is omitted' => [
        [
            'include_paths' => [],
            'exclude_paths' => [],
            'nodes'         => [
                [
                    'type' => 'Expr_MethodCall',
                ],
            ],
            'use_from_tests'                => true,
            'forbid_dynamic_function_calls' => false,
            'non_ignorable'                 => true,
        ],
        [__DIR__.'/Fixtures/app/ForbiddenMethodStaticCalls.php'],
        [
            ['Forbidden code: method DateTimeImmutable::format() is not allowed.', 6],
            ['Forbidden code: method DateTime::format() is not allowed.', 7],
            ['Forbidden code: method DateTimeImmutable::getTimestamp() is not allowed.', 8],
        ],
    ],
    'legacy functions key works for method calls' => [
        [
            'include_paths' => [],
            'exclude_paths' => [],
            'nodes'         => [
                [
                    'type'      => 'Expr_MethodCall',
                    'functions' => ['format'],
                ],
            ],
            'use_from_tests'                => true,
            'forbid_dynamic_function_calls' => false,
            'non_ignorable'                 => true,
        ],
        [__DIR__.'/Fixtures/app/ForbiddenMethodStaticCalls.php'],
        [
            ['Forbidden code: method DateTimeImmutable::format() is not allowed.', 6],
            ['Forbidden code: method DateTime::format() is not allowed.', 7],
        ],
    ],
    'all static calls when methods is null' => [
        [
            'include_paths' => [],
            'exclude_paths' => [],
            'nodes'         => [
                [
                    'type'    => 'Expr_StaticCall',
                    'methods' => null,
                ],
            ],
            'use_from_tests'                => true,
            'forbid_dynamic_function_calls' => false,
            'non_ignorable'                 => true,
        ],
        [__DIR__.'/Fixtures/app/ForbiddenMethodStaticCalls.php'],
        [
            ['Forbidden code: method DateTimeImmutable::createFromFormat() is not allowed.', 10],
            ['Forbidden code: method DateTime::createFromFormat() is not allowed.', 11],
            ['Forbidden code: method DateTimeImmutable::createFromMutable() is not allowed.', 12],
        ],
    ],
    'dynamic static call reports method without class name' => [
        [
            'include_paths' => [],
            'exclude_paths' => [],
            'nodes'         => [
                [
                    'type'    => 'Expr_StaticCall',
                    'methods' => null,
                ],
            ],
            'use_from_tests'                => true,
            'forbid_dynamic_function_calls' => false,
            'non_ignorable'                 => true,
        ],
        [__DIR__.'/Fixtures/app/DynamicStaticCall.php'],
        [
            ['Forbidden code: method createFromFormat() is not allowed.', 4],
        ],
    ],
]);

dataset('allowed_cases', [
    'allowed function' => [
        defaultLikeConfig(),
        [__DIR__.'/Fixtures/app/AllowedFunction.php'],
    ],
    'dynamic function call with default function list' => [
        defaultLikeConfig(),
        [__DIR__.'/Fixtures/app/DynamicFunctionCall.php'],
    ],
    'tests imports in test file' => [
        defaultLikeConfig(),
        [__DIR__.'/Fixtures/tests/UsesTestsNamespaceInTestFile.php'],
    ],
    'tests imports in spec-like file' => [
        defaultLikeConfig(),
        [dirname(__DIR__).'/fixtures/non-test/UsesTestsNamespaceSpec.php'],
    ],
    'tests import check disabled' => [
        (static function (): array {
            $config                   = defaultLikeConfig();
            $config['use_from_tests'] = false;

            return $config;
        })(),
        [dirname(__DIR__).'/fixtures/non-test/UsesTestsImports.php'],
    ],
    'non-tests import in non-test file is ignored' => [
        defaultLikeConfig(),
        [dirname(__DIR__).'/fixtures/non-test/UsesAppNamespace.php'],
    ],
    'global include/exclude ignores excluded file' => [
        globalPathFilterConfig(),
        [__DIR__.'/Fixtures/excluded/ForbiddenExcludedNodes.php'],
    ],
    'global include/exclude ignores non-included file' => [
        globalPathFilterConfig(),
        [__DIR__.'/Fixtures/app/ForbiddenFunctionsAll.php'],
    ],
    'global exclude wins over include' => [
        (static function (): array {
            $config                  = defaultLikeConfig();
            $config['include_paths'] = ['/Fixtures/app'];
            $config['exclude_paths'] = ['/Fixtures/app'];

            return $config;
        })(),
        [__DIR__.'/Fixtures/app/ForbiddenFunctionsAll.php'],
    ],
    'node-level include/exclude ignores app file' => [
        nodeLevelPathFilterConfig(),
        [__DIR__.'/Fixtures/app/ForbiddenFunctionsAll.php'],
    ],
    'method call class mismatch is ignored' => [
        [
            'include_paths' => [],
            'exclude_paths' => [],
            'nodes'         => [
                [
                    'type'    => 'Expr_MethodCall',
                    'methods' => [
                        ['class' => 'UnknownClass', 'method' => 'send'],
                    ],
                ],
            ],
            'use_from_tests'                => true,
            'forbid_dynamic_function_calls' => false,
            'non_ignorable'                 => true,
        ],
        [__DIR__.'/Fixtures/app/ForbiddenMethodStaticCalls.php'],
    ],
    'static call method mismatch is ignored' => [
        [
            'include_paths' => [],
            'exclude_paths' => [],
            'nodes'         => [
                [
                    'type'    => 'Expr_StaticCall',
                    'methods' => [
                        ['class' => \DateTimeImmutable::class, 'method' => 'unknown*'],
                    ],
                ],
            ],
            'use_from_tests'                => true,
            'forbid_dynamic_function_calls' => false,
            'non_ignorable'                 => true,
        ],
        [__DIR__.'/Fixtures/app/ForbiddenMethodStaticCalls.php'],
    ],
]);

it('flags forbidden cases', function (array $config, array $files, array $expectedErrors): void {
    /** @var RuleHarness $this */
    /** @var array<string, mixed> $config */
    /** @var list<string> $files */
    /** @var list<array{string, int}> $expectedErrors */
    $this->setConfig($config);
    $this->runAnalyse($files, $expectedErrors);
})->with('forbidden_cases');

it('ignores allowed cases', function (array $config, array $files): void {
    /** @var RuleHarness $this */
    /** @var array<string, mixed> $config */
    /** @var list<string> $files */
    $this->setConfig($config);
    $this->runAnalyse($files, []);
})->with('allowed_cases');
