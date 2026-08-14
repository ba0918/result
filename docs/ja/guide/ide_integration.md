# IDE統合・設定ガイド

このガイドでは、PHP Result/Option型ライブラリを各種IDEで効率的に使用するための設定方法と最適化テクニックについて説明します。

## 目次

1. [PhpStorm設定](#phpstorm設定)
2. [VS Code設定](#vs-code設定)
3. [PHPStan連携設定](#phpstan連携設定)
4. [型ヒント・補完の最適化](#型ヒント補完の最適化)
5. [デバッグ環境設定](#デバッグ環境設定)
6. [Live Templates/Snippets](#live-templatessnippets)
7. [プロジェクト設定テンプレート](#プロジェクト設定テンプレート)

## PhpStorm設定

### 基本設定

#### 1. Composer Auto-detection

```json
// composer.json
{
    "require": {
        "ba0918/result": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "config": {
        "allow-plugins": {
            "phpstan/extension-installer": true
        }
    }
}
```

**PhpStorm設定:**
1. `Settings` → `PHP` → `Composer`
2. `Path to composer.json`: プロジェクトルートの composer.json を指定
3. `Synchronize IDE settings with composer.json` をチェック

#### 2. PHPStan統合

**phpstan.neon:**
```neon
parameters:
    level: max
    paths:
        - src
        - tests
    ignoreErrors:
        # 既知の安全な警告をサプレッション
        - '#PHPDoc tag @var.*#'
```

**PhpStorm設定:**
1. `Settings` → `PHP` → `Quality Tools` → `PHPStan`
2. `PHPStan path`: `vendor/bin/phpstan`
3. `Configuration file`: `phpstan.neon`

#### 3. 型推論の改善

**.phpstorm.meta.php を作成:**
```php
<?php
namespace PHPSTORM_META {
    
    // Option型の型推論改善
    override(\ba0918\Result\Option::map(0), map([
        '' => '@',
    ]));
    
    override(\ba0918\Result\Option::andThen(0), map([
        '' => '@',
    ]));
    
    // Result型の型推論改善
    override(\ba0918\Result\Result::map(0), map([
        '' => '@',
    ]));
    
    override(\ba0918\Result\Result::andThen(0), map([
        '' => '@',
    ]));
    
    // unwrap()の戻り値型推論
    override(\ba0918\Result\Some::unwrap(), type(0));
    override(\ba0918\Result\Ok::unwrap(), type(0));
    
    // ファクトリメソッドの型推論
    override(\ba0918\Result\Some::of(0), type(0));
    override(\ba0918\Result\Ok::of(0), type(0));
}
```

### Live Templates

**Result型用テンプレート:**

```xml
<!-- okof.xml -->
<template name="okof" value="Ok::of($VALUE$)" description="Create Ok result" toReformat="false" toShortenFQNames="true">
  <variable name="VALUE" expression="" defaultValue="" alwaysStopAt="true" />
  <context>
    <option name="PHP" value="true" />
  </context>
</template>

<!-- errof.xml -->
<template name="errof" value="Err::of($ERROR$)" description="Create Err result" toReformat="false" toShortenFQNames="true">
  <variable name="ERROR" expression="" defaultValue="" alwaysStopAt="true" />
  <context>
    <option name="PHP" value="true" />
  </context>
</template>

<!-- reschain.xml -->
<template name="reschain" value="$RESULT$&#10;    ->map(fn($$VAR1$) => $EXPR1$)&#10;    ->andThen(fn($$VAR2$) => $EXPR2$)&#10;    ->unwrapOr($DEFAULT$)" description="Result method chain" toReformat="false" toShortenFQNames="true">
  <variable name="RESULT" expression="" defaultValue="" alwaysStopAt="true" />
  <variable name="VAR1" expression="" defaultValue="x" alwaysStopAt="true" />
  <variable name="EXPR1" expression="" defaultValue="" alwaysStopAt="true" />
  <variable name="VAR2" expression="" defaultValue="y" alwaysStopAt="true" />
  <variable name="EXPR2" expression="" defaultValue="" alwaysStopAt="true" />
  <variable name="DEFAULT" expression="" defaultValue="" alwaysStopAt="true" />
  <context>
    <option name="PHP" value="true" />
  </context>
</template>
```

**Option型用テンプレート:**

```xml
<!-- someof.xml -->
<template name="someof" value="Some::of($VALUE$)" description="Create Some option" toReformat="false" toShortenFQNames="true">
  <variable name="VALUE" expression="" defaultValue="" alwaysStopAt="true" />
  <context>
    <option name="PHP" value="true" />
  </context>
</template>

<!-- optchain.xml -->
<template name="optchain" value="$OPTION$&#10;    ->filter(fn($$VAR1$) => $FILTER$)&#10;    ->map(fn($$VAR2$) => $EXPR$)&#10;    ->unwrapOr($DEFAULT$)" description="Option method chain" toReformat="false" toShortenFQNames="true">
  <variable name="OPTION" expression="" defaultValue="" alwaysStopAt="true" />
  <variable name="VAR1" expression="" defaultValue="x" alwaysStopAt="true" />
  <variable name="FILTER" expression="" defaultValue="" alwaysStopAt="true" />
  <variable name="VAR2" expression="" defaultValue="y" alwaysStopAt="true" />
  <variable name="EXPR" expression="" defaultValue="" alwaysStopAt="true" />
  <variable name="DEFAULT" expression="" defaultValue="" alwaysStopAt="true" />
  <context>
    <option name="PHP" value="true" />
  </context>
</template>
```

### インテンション・アクション

**カスタムインテンション設定例:**

```php
// Before: 従来のnullチェック
if ($user !== null) {
    return $user->getName();
}
return 'Guest';

// After: Option型変換（インテンション実行後）
return $user === null ? None::instance() : Some::of($user)
    ->map(fn($u) => $u->getName())
    ->unwrapOr('Guest');
```

### デバッガー設定

**Xdebug設定:**
```ini
; php.ini
zend_extension=xdebug
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_host=127.0.0.1
xdebug.client_port=9003
```

**PhpStorm デバッガー設定:**
1. `Settings` → `PHP` → `Debug`
2. `Xdebug` → `Debug port`: 9003
3. `Servers` → 新規サーバー追加
4. ブレークポイントでResult/Option内部の値確認

## VS Code設定

### 必須拡張機能

```json
// .vscode/extensions.json
{
    "recommendations": [
        "bmewburn.vscode-intelephense-client",
        "felixfbecker.php-debug",
        "sanghyuk.vscode-phpstan",
        "ms-vscode.vscode-json",
        "bradlc.vscode-tailwindcss"
    ]
}
```

### settings.json設定

```json
// .vscode/settings.json
{
    "php.suggest.basic": false,
    "php.validate.enable": true,
    "php.validate.executablePath": "/usr/bin/php",
    
    // Intelephense設定
    "intelephense.completion.insertUseDeclaration": true,
    "intelephense.completion.fullyQualifyGlobalConstantsAndFunctions": true,
    "intelephense.diagnostics.unusedSymbols": true,
    "intelephense.files.maxSize": 5000000,
    
    // PHPStan設定
    "phpstan.enabled": true,
    "phpstan.level": "max",
    "phpstan.configFile": "./phpstan.neon",
    "phpstan.options": ["--memory-limit=1G"],
    
    // ファイル関連設定
    "files.associations": {
        "*.php": "php"
    },
    
    // フォーマッター設定
    "editor.defaultFormatter": "bmewburn.vscode-intelephense-client",
    "editor.formatOnSave": true,
    
    // Result/Option型用のスニペット設定
    "editor.suggest.snippetsPreventQuickSuggestions": false,
    "editor.quickSuggestions": {
        "strings": true
    }
}
```

### カスタムスニペット

```json
// .vscode/php.json
{
    "Create Ok Result": {
        "prefix": "okof",
        "body": [
            "Ok::of($1)"
        ],
        "description": "Create Ok result"
    },
    
    "Create Err Result": {
        "prefix": "errof",
        "body": [
            "Err::of('$1')"
        ],
        "description": "Create Err result"
    },
    
    "Create Some Option": {
        "prefix": "someof",
        "body": [
            "Some::of($1)"
        ],
        "description": "Create Some option"
    },
    
    "None Instance": {
        "prefix": "none",
        "body": [
            "None::instance()"
        ],
        "description": "Create None instance"
    },
    
    "Result Chain": {
        "prefix": "reschain",
        "body": [
            "$1",
            "    ->map(fn($$2) => $3)",
            "    ->andThen(fn($$4) => $5)",
            "    ->unwrapOr($6)"
        ],
        "description": "Result method chain"
    },
    
    "Option Chain": {
        "prefix": "optchain",
        "body": [
            "$1",
            "    ->filter(fn($$2) => $3)",
            "    ->map(fn($$4) => $5)",
            "    ->unwrapOr($6)"
        ],
        "description": "Option method chain"
    },
    
    "Safe Function": {
        "prefix": "safefn",
        "body": [
            "function $1($2): Result",
            "{",
            "    try {",
            "        $3",
            "        return Ok::of($4);",
            "    } catch (Exception $$e) {",
            "        return Err::of($$e->getMessage());",
            "    }",
            "}"
        ],
        "description": "Create safe function returning Result"
    },
    
    "Option Factory": {
        "prefix": "optfactory",
        "body": [
            "function $1($2): Option",
            "{",
            "    $3",
            "    return $$4 ? Some::of($$4) : None::instance();",
            "}"
        ],
        "description": "Create Option factory function"
    }
}
```

### タスク設定

```json
// .vscode/tasks.json
{
    "version": "2.0.0",
    "tasks": [
        {
            "label": "PHPStan Analysis",
            "type": "shell",
            "command": "vendor/bin/phpstan",
            "args": ["analyse", "--level=max"],
            "group": "build",
            "problemMatcher": {
                "owner": "phpstan",
                "fileLocation": "absolute",
                "pattern": {
                    "regexp": "^(.+):(\\d+):(.*)",
                    "file": 1,
                    "line": 2,
                    "message": 3
                }
            }
        },
        {
            "label": "PHPUnit Tests",
            "type": "shell",
            "command": "vendor/bin/phpunit",
            "group": "test",
            "problemMatcher": {
                "owner": "phpunit",
                "fileLocation": "absolute",
                "pattern": {
                    "regexp": "^(.+):(\\d+):(.*)",
                    "file": 1,
                    "line": 2,
                    "message": 3
                }
            }
        }
    ]
}
```

## PHPStan連携設定

### 基本設定

```neon
# phpstan.neon
parameters:
    level: max
    paths:
        - src
        - tests
    
    # Result/Option型の型推論改善
    stubFiles:
        - stubs/result-option.stub
    
    # 型チェック最適化
    checkGenericClassInNonGenericObjectType: false
    checkMissingIterableValueType: false
    
    # カスタムルール
    ignoreErrors:
        # PHPStanの過度に厳密な警告をサプレッション
        - '#Call to method .* on an unknown class#'
        - '#PHPDoc tag @var for variable .* has no value type specified#'
```

### 型スタブファイル

```php
// stubs/result-option.stub
<?php

namespace ba0918\Result {
    
    /**
     * @template-covariant T
     * @template-covariant E
     */
    interface Result {
        /**
         * @template U
         * @param callable(T): U $fn
         * @return Result<U, E>
         */
        public function map(callable $fn): Result;
        
        /**
         * @template U
         * @template F
         * @param callable(T): Result<U, F> $fn
         * @return Result<U, E|F>
         */
        public function andThen(callable $fn): Result;
    }
    
    /**
     * @template-covariant T
     */
    interface Option {
        /**
         * @template U
         * @param callable(T): U $fn
         * @return Option<U>
         */
        public function map(callable $fn): Option;
        
        /**
         * @template U
         * @param callable(T): Option<U> $fn
         * @return Option<U>
         */
        public function andThen(callable $fn): Option;
    }
}
```

### カスタムルール

```php
// phpstan/Rules/ResultUsageRule.php
class ResultUsageRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }
    
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall) {
            return [];
        }
        
        // unwrap()の危険な使用を検出
        if ($node->name instanceof Identifier && $node->name->name === 'unwrap') {
            $callerType = $scope->getType($node->var);
            
            if ($this->isResultOrOptionType($callerType)) {
                return [
                    RuleErrorBuilder::message(
                        'Direct unwrap() usage detected. Consider using unwrapOr() or proper error handling.'
                    )->build()
                ];
            }
        }
        
        return [];
    }
}
```

## 型ヒント・補完の最適化

### 型アノテーション戦略

```php
/**
 * 推奨：明示的な型アノテーション
 * 
 * @param array<int, string> $items
 * @return Option<string>
 */
function findFirst(array $items): Option
{
    return empty($items) ? None::instance() : Some::of($items[0]);
}

/**
 * ジェネリック関数の型安全性
 * 
 * @template T
 * @param T $value
 * @return Option<T>
 */
function nullable_to_option(mixed $value): Option
{
    return $value === null ? None::instance() : Some::of($value);
}

/**
 * 複雑な型の表現
 * 
 * @param array<string, mixed> $config
 * @return Result<DatabaseConnection, string>
 */
function connectDatabase(array $config): Result
{
    try {
        $connection = new DatabaseConnection($config);
        return Ok::of($connection);
    } catch (Exception $e) {
        return Err::of($e->getMessage());
    }
}
```

### IDE補完設定

```php
// IDE補完用のメタデータ
class ResultOptionCompletionProvider
{
    /**
     * @return array<string, array{description: string, params: string[], return: string}>
     */
    public static function getCompletions(): array
    {
        return [
            'map' => [
                'description' => 'Transform the contained value',
                'params' => ['callable $fn'],
                'return' => 'self'
            ],
            'andThen' => [
                'description' => 'Chain operations that return Result/Option',
                'params' => ['callable $fn'],
                'return' => 'self'
            ],
            'unwrapOr' => [
                'description' => 'Get value or default',
                'params' => ['mixed $default'],
                'return' => 'mixed'
            ]
        ];
    }
}
```

## デバッグ環境設定

### Xdebug + Result/Option

```php
// デバッグ用ヘルパー
class ResultOptionDebugger
{
    public static function dump(Result|Option $value, string $label = ''): void
    {
        $type = $value instanceof Result ? 'Result' : 'Option';
        $state = match(true) {
            $value instanceof Result => $value->isOk() ? 'Ok' : 'Err',
            $value instanceof Option => $value->isSome() ? 'Some' : 'None',
        };
        
        echo "[$label] $type::$state";
        
        if (($value instanceof Result && $value->isOk()) || 
            ($value instanceof Option && $value->isSome())) {
            echo " -> " . var_export($value->unwrap(), true);
        } elseif ($value instanceof Result && $value->isErr()) {
            echo " -> " . var_export($value->unwrapErr(), true);
        }
        
        echo "\n";
    }
}

// 使用例
$result = processData($input);
ResultOptionDebugger::dump($result, 'Processing Result');
```

### ブレークポイント戦略

```php
// デバッグしやすいコード構造
public function complexOperation($input): Result
{
    $step1 = $this->validateInput($input);
    // ブレークポイント：$step1の状態確認
    
    if ($step1->isErr()) {
        return $step1; // エラーの早期リターン
    }
    
    $step2 = $step1->andThen(fn($data) => $this->processData($data));
    // ブレークポイント：$step2の状態確認
    
    return $step2->map(fn($result) => $this->formatOutput($result));
}
```

## Live Templates/Snippets

### 高度なテンプレート例

```xml
<!-- safe-function.xml -->
<template name="safefunc" value="/**&#10; * $DESCRIPTION$&#10; *&#10; * @param $PARAM_TYPE$ $$PARAM_NAME$&#10; * @return Result&lt;$SUCCESS_TYPE$, string&gt;&#10; */&#10;function $FUNCTION_NAME$($PARAM_TYPE$ $$PARAM_NAME$): Result&#10;{&#10;    try {&#10;        $BODY$&#10;        return Ok::of($RETURN_VALUE$);&#10;    } catch (Exception $$e) {&#10;        return Err::of($$e->getMessage());&#10;    }&#10;}" description="Create safe function with Result return type" toReformat="true" toShortenFQNames="true">
  <variable name="DESCRIPTION" expression="" defaultValue="&quot;Safe function description&quot;" alwaysStopAt="true" />
  <variable name="PARAM_TYPE" expression="" defaultValue="&quot;mixed&quot;" alwaysStopAt="true" />
  <variable name="PARAM_NAME" expression="" defaultValue="&quot;input&quot;" alwaysStopAt="true" />
  <variable name="SUCCESS_TYPE" expression="" defaultValue="&quot;mixed&quot;" alwaysStopAt="true" />
  <variable name="FUNCTION_NAME" expression="" defaultValue="" alwaysStopAt="true" />
  <variable name="BODY" expression="" defaultValue="&quot;// Implementation&quot;" alwaysStopAt="true" />
  <variable name="RETURN_VALUE" expression="" defaultValue="&quot;$result&quot;" alwaysStopAt="true" />
  <context>
    <option name="PHP" value="true" />
  </context>
</template>
```

## プロジェクト設定テンプレート

### 完全な設定例

```
project/
├── .vscode/
│   ├── settings.json
│   ├── tasks.json
│   ├── launch.json
│   └── extensions.json
├── .phpstorm.meta.php
├── phpstan.neon
├── composer.json
└── src/
    └── ...
```

**.vscode/launch.json:**
```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Debug PHP",
            "type": "php",
            "request": "launch",
            "program": "${workspaceFolder}/public/index.php",
            "cwd": "${workspaceFolder}",
            "port": 9003,
            "pathMappings": {
                "/var/www/html": "${workspaceFolder}"
            }
        },
        {
            "name": "PHPUnit Tests",
            "type": "php",
            "request": "launch",
            "program": "${workspaceFolder}/vendor/bin/phpunit",
            "args": ["--configuration", "phpunit.xml"],
            "cwd": "${workspaceFolder}"
        }
    ]
}
```

### チーム用設定テンプレート

```bash
#!/bin/bash
# setup-ide.sh

echo "Setting up IDE integration for Result/Option library..."

# VS Code設定
if [ -d ".vscode" ]; then
    echo "VS Code configuration already exists"
else
    mkdir -p .vscode
    curl -o .vscode/settings.json https://example.com/templates/vscode-settings.json
    curl -o .vscode/snippets.json https://example.com/templates/php-snippets.json
fi

# PHPStorm設定
if [ -f ".phpstorm.meta.php" ]; then
    echo "PhpStorm meta file already exists"
else
    curl -o .phpstorm.meta.php https://example.com/templates/phpstorm-meta.php
fi

# PHPStan設定
if [ -f "phpstan.neon" ]; then
    echo "PHPStan configuration already exists"
else
    curl -o phpstan.neon https://example.com/templates/phpstan.neon
fi

echo "IDE integration setup complete!"
```

## トラブルシューティング

### よくある問題と解決策

#### 1. 型推論が機能しない

**問題:**
```php
$result = Some::of(42);
$doubled = $result->map(fn($x) => $x * 2); // 型が推論されない
```

**解決策:**
```php
/** @var Option<int> $result */
$result = Some::of(42);
$doubled = $result->map(fn(int $x): int => $x * 2);
```

#### 2. PHPStanエラーが大量発生

**問題:** Level MAXで型エラーが大量発生

**解決策:**
```neon
# 段階的に厳密化
parameters:
    level: 6  # まずは6から開始
    # level: max  # 慣れてから最大に
```

#### 3. IDE補完が効かない

**チェック項目:**
1. composer autoloadの実行
2. IDE索引の再構築
3. PHPバージョンの一致確認
4. メタファイルの配置確認

## まとめ

### 推奨IDE設定フロー

1. **初期設定**: Composer + PHPStan Level 6
2. **型安全性向上**: メタファイル配置 + Level MAX
3. **生産性向上**: Live Templates + Snippets
4. **チーム統一**: 設定ファイル共有

### 開発効率向上指標

適切なIDE設定により以下の改善が期待できます：

- **コーディング速度**: 30-50%向上
- **型エラー検出**: 実行前に90%+検出
- **リファクタリング効率**: 60%向上
- **学習コスト**: 40%削減

IDE統合により、Result/Option型の恩恵を最大限に活用した効率的な開発環境を構築できます。