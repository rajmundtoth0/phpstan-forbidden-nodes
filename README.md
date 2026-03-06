# PHPStan Forbidden Nodes

A PHPStan extension that reports forbidden PHP AST nodes and call patterns:

- node types (for example `Stmt_Echo`, `Expr_Eval`, `Expr_Print`)
- specific function calls
- specific instance/static method calls (class + method patterns with `*` wildcard)
- dynamic function calls (`$fn()`) when enabled
- `use Tests\...` imports inside non-test files

## Installation

```bash
composer require --dev rajmundtoth0/phpstan-forbidden
```

If you use `phpstan/extension-installer`, `extension.neon` is loaded automatically.

Otherwise add this to your `phpstan.neon`:

```neon
includes:
  - vendor/rajmundtoth0/phpstan-forbidden/extension.neon
```

## Configuration

Default config is shipped in `neon/defaults.neon`. Override any part in your project config:

```neon
parameters:
  forbidden_node:
    # Optional: analyse only these paths (substring match).
    include_paths:
      - /app

    # Optional: skip these paths (substring match).
    exclude_paths:
      - /vendor
      - /storage

    # Detect `use Tests\...` in non-test files.
    use_from_tests: true

    # Ban dynamic function calls like `$fn()`.
    forbid_dynamic_function_calls: false

    # Emit non-ignorable errors.
    non_ignorable: true

    nodes:
      # Ban all echo statements.
      - type: Stmt_Echo

      # Ban selected function calls.
      - type: Expr_FuncCall
        functions:
          - dd
          - var_dump

      # Ban selected instance method calls.
      - type: Expr_MethodCall
        methods:
          - class: App\Service\Mailer
            method: send
          - class: App\*
            method: save*

      # Ban selected static method calls.
      - type: Expr_StaticCall
        methods:
          - class: Illuminate\Support\Facades\DB
            method: raw

      # Node-level path filters (optional per node entry).
      - type: Expr_Print
        include_paths:
          - /app/legacy
        exclude_paths:
          - /app/legacy/safe
```

## Notes

- `functions: null` on `Expr_FuncCall` bans all function calls.
- `methods: null` on `Expr_MethodCall` or `Expr_StaticCall` bans all calls of that node type.
- `methods` supports both `class/method` and `class_pattern/method_pattern` keys.
- For backward compatibility, `functions` on `Expr_MethodCall` and `Expr_StaticCall` is treated as `methods` with class `*`.

## No Defaults Mode

If you want full control and no packaged defaults, include only services:

```neon
includes:
  - vendor/rajmundtoth0/phpstan-forbidden/neon/services.neon
```

Then define `parameters.forbidden_node` yourself.
