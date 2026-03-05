# PHPStan Banned Code (reworked)

A small PHPStan extension that reports banned constructs (echo, eval, exit, specific function calls, etc.) via a simple config.

## Installation

```bash
composer require --dev rajmundtoth0/phpstan-banned-code
```

If you use `phpstan/extension-installer`, you’re done.

Otherwise, include the extension in your `phpstan.neon`:

```neon
includes:
  - vendor/rajmundtoth0/phpstan-banned-code/extension.neon
```

## Configuration

Default config is shipped in `neon/defaults.neon` and is **overrideable** from your project config:

```neon
parameters:
  forbidden_node:
    # optional: analyse only these paths (substring match)
    include_paths:
      - /app

    # optional: always ignore these paths (substring match)
    exclude_paths:
      - /vendor
      - /storage

    # detect `use Tests\\...` in non-test files
    use_from_tests: true

    # optional: ban dynamic function calls like `$fn()`
    forbid_dynamic_function_calls: false

    # make errors non-ignorable (default: true)
    non_ignorable: true

    nodes:
      -
        type: Stmt_Echo
        functions: null
      -
        type: Expr_FuncCall
        functions:
          - var_dump
          - dd

      # instance method calls (class + method, supports * wildcard)
      -
        type: Expr_MethodCall
        methods:
          - class: App\Service\Mailer
            method: send
          - class: App\*
            method: save*

      # static method calls
      -
        type: Expr_StaticCall
        methods:
          - class: Illuminate\Support\Facades\DB
            method: raw

        # optional node-level path filters:
        include_paths:
          - /app/legacy
        exclude_paths:
          - /app/legacy/safe
```

`methods: null` on `Expr_MethodCall` or `Expr_StaticCall` bans all calls of that node type.

### Tip: no defaults

If you prefer **no defaults**, include only the services file:

```neon
includes:
  - vendor/rajmundtoth0/phpstan-banned-code/neon/services.neon
```

…and define `parameters.forbidden_node` yourself.
