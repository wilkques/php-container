# Container

[![Latest Stable Version](https://poser.pugx.org/wilkques/container/v/stable)](https://packagist.org/packages/wilkques/container)
[![License](https://poser.pugx.org/wilkques/container/license)](https://packagist.org/packages/wilkques/container)

[English](README.md) | 繁體中文

一個輕量、無外部相依、符合 PSR-11 的依賴注入容器,語意上盡量對齊
`Illuminate\Container\Container`,並保持與 PHP 5.3 相容。

> **從 4.x 升級?** 5.0 版有幾個方法的行為改變了,尤其是 `bind()` 和
> `get()`。升級前請先閱讀 [UPGRADING.md](UPGRADING.md)。

## 如何使用

```
composer require wilkques/container
```

就這樣——不需要手動 `require` 任何檔案。Composer 的 `files` autoload 機制
(在本套件的 `composer.json` 裡宣告)會自動載入 `src/helpers.php`,
它定義了全域的 `container()` 輔助函式(本文件全程使用),並且在系統裡
還沒安裝真正的 `psr/container` 套件時,自動註冊內建的 PSR-11 介面
(見下方 [PSR-11](#psr-11) 章節)。

## 方法

1. `register`

    把一個**已經建構好的物件**註冊進容器,對應到指定的名稱。這是
    [`instance()`](#5-instance) 的一層薄包裝——你傳進去的物件會被原樣儲存,
    之後每次解析都原樣回傳(等同永遠是「shared」)。單一組合與批次陣列組合
    兩種寫法都支援:

    ```php
    container()->register(
        '<your class name>',
        new \Your\Class\Name
    );

    // 或者,批次傳入多組 [abstract, concrete]

    container()->register([
        [
            '<your class name1>',
            new \Your\Class\Name1
        ],
        [
            '<your class name2>',
            new \Your\Class\Name2
        ],

        // ...
    ]);
    ```

1. `bind($abstract, $concrete = null, $shared = false)`

    註冊一個**延遲、非 shared 的工廠**。`$concrete` 這個閉包(或類別名稱)
    在你呼叫 `bind()` 的當下**不會被執行**——只有在該 abstract 被解析時才會
    執行,而且**每一次 `make()`/`get()` 呼叫都會重新執行一次**,每次都回傳
    一個全新的實例。

    > **與 v4 的行為差異:** 在 4.x,`bind()` 的行為像 singleton——工廠只
    > 執行一次,之後永遠回傳同一個快取的實例。在 5.0,`bind()` 是真正的
    > transient/工廠綁定。如果你想要舊版那種快取行為,請改用
    > [`singleton()`](#3-singleton)。詳見
    > [UPGRADING.md](UPGRADING.md#1-bindsingletonscoped-are-now-genuinely-lazy)。

    ```php
    container()->bind('<your class name>', function ($container) {
        return new \Your\Class\Name;
    });

    container()->make('<your class name>'); // 新實例
    container()->make('<your class name>'); // 另一個全新的實例
    ```

1. `singleton($abstract, $concrete = null)`

    把一個類別或介面綁定進容器,只解析一次。singleton 綁定一旦被解析過,
    之後每次呼叫容器都會回傳同一個物件實例:

    ```php
    container()->singleton('<your class name>', function ($container) {
        return new \Your\Class\Name;
    });

    container()->make('<your class name>') === container()->make('<your class name>'); // true
    ```

1. `scoped($abstract, $concrete = null)`

    行為與 `singleton()` 完全相同,但這個綁定會額外被追蹤,方便用
    [`forgetScopedInstances()`](#15-forgetinstance-forgetinstances-forgetscopedinstances-flush)
    一次全部清除——適合長駐程序裡「單一請求週期」的狀態管理。

    ```php
    container()->scoped('<your class name>', function ($container) {
        return new \Your\Class\Name;
    });
    ```

1. `instance($abstract, $instance)`

    直接、明確地把一個已建構好的物件註冊為 shared 實例——`register()` 和
    `singleton()` 的已解析快取,底層都是呼叫這個方法。回傳你傳入的那個實例。

    ```php
    $object = new \Your\Class\Name;

    container()->instance('<your class name>', $object);

    container()->make('<your class name>') === $object; // true
    ```

1. `alias($abstract, $alias)`

    為既有的 abstract 註冊一個別名。別名可以串接(別名本身也可以再被
    設別名),並且會被 `make()`/`get()`/`has()` 透明解析。把一個 abstract
    設成自己的別名會丟出 `LogicException`。

    ```php
    container()->instance('<your class name>', new \Your\Class\Name);

    container()->alias('<your class name>', '<your class alias>');

    container()->make('<your class alias>'); // 與 '<your class name>' 是同一個物件
    ```

1. `tag($abstracts, $tags)` / `tagged($tag)`

    把一個或多個 abstract 歸類到一個或多個標籤下,之後可以一次呼叫就解析
    出某個標籤底下的全部 abstract。`$tags` 可以是單一標籤字串、標籤字串
    陣列,或是變參字串。`tagged()` **會立即解析出該標籤下所有的 abstract,
    回傳一個純陣列**——絕不是 generator,因為本套件的目標是 PHP 5.3,
    而 5.3 沒有 generator。

    ```php
    container()->bind('report.csv', function () { return new \Reports\Csv; });
    container()->bind('report.pdf', function () { return new \Reports\Pdf; });

    container()->tag(['report.csv', 'report.pdf'], 'reports');

    foreach (container()->tagged('reports') as $report) {
        // $report 是已解析的 \Reports\Csv / \Reports\Pdf 實例
    }
    ```

1. `extend($abstract, \Closure $closure)`

    註冊一個裝飾器,用來包裝/修改某個 abstract 解析出來的實例。如果該
    abstract 已經被解析過(例如已經建構好的 singleton),閉包會立刻套用在
    那個已快取的實例上;否則,會在該 abstract 下一次被建構時套用。同一個
    abstract 上的多個裝飾器,會依照註冊順序依序套用。

    ```php
    container()->bind('<your class name>', function () {
        return new \Your\Class\Name;
    });

    container()->extend('<your class name>', function ($instance, $container) {
        return new \Your\Class\Decorator($instance);
    });

    container()->make('<your class name>'); // 一個包住 \Your\Class\Name 的 \Your\Class\Decorator
    ```

1. `resolving($abstract, $callback = null)` / `afterResolving($abstract, $callback = null)`

    註冊生命週期 hook,在容器每次解析出一個實體時執行——`resolving()` 會在
    物件建構完成後(尚未被快取/回傳之前)立刻執行,`afterResolving()` 則緊接
    在那之後執行。兩者都支援兩種寫法:

    - **全域形式**——只傳一個 `\Closure`,不指定 abstract,會在*每一次*
      解析時觸發:

        ```php
        container()->resolving(function ($object, $container) {
            // 每次解析出任何物件都會執行
        });
        ```

    - **指定 abstract 形式**——傳入 abstract 名稱與回呼函式,只有在解析
      該 abstract(或該型別的實例)時才會觸發:

        ```php
        container()->resolving('<your class name>', function ($object, $container) {
            // 只有在解析 '<your class name>' 時才會執行
        });
        ```

    `resolving()` 與 `afterResolving()` 都會回傳 `$this` 以便串接呼叫。
    單次解析中,回呼觸發的順序是:全域 `resolving()` 回呼 → 指定 abstract
    的 `resolving()` 回呼 → 全域 `afterResolving()` 回呼 → 指定 abstract
    的 `afterResolving()` 回呼。

1. `when($concrete)->needs($abstractOrParamName)->give($implementation)`

    Contextual binding(情境綁定):針對*特定*一個具體類別,覆寫它某個
    建構子依賴要拿到的值,而不影響該依賴在全域的綁定。`$implementation`
    可以是一個 `\Closure`(呼叫時會帶入容器)、一個類別/介面名稱(會透過
    容器解析)、或任何其他值(原樣回傳)——包括 `null`、`0`、`''`,這些都
    會被當成明確指定的值來處理。

    `needs()` 支援兩種寫法,解析依賴時會依序檢查:

    1. **依參數名稱**——`needs('$paramName')`(注意開頭的 `$`)。
       這個會被優先檢查。
    2. **依型別名稱**——`needs(SomeInterface::class)`,對應該依賴宣告的
       類別/介面型別提示。只有在沒有比對到參數名稱綁定時才會檢查這個。

    ```php
    // 依參數名稱:
    container()->when(\Your\Class\Name::class)
        ->needs('$connection')
        ->give('mysql');

    // 依型別名稱:
    container()->when(\Your\Class\Name::class)
        ->needs(\Your\Contract\LoggerInterface::class)
        ->give(\Your\Class\FileLogger::class);

    container()->make(\Your\Class\Name::class);
    ```

1. `has($abstract)`

    PSR-11 的 `ContainerInterface::has()`。如果容器對 `$abstract` 有綁定、
    有已註冊的實例、或有可解析的別名,回傳 `true`。**不保證**
    `get()`/`make()` 一定不會拋例外(一個已綁定的工廠仍然可能在建構時
    失敗)。

    ```php
    container()->has('<your class name>');
    ```

1. `get($abstract)`

    PSR-11 的 `ContainerInterface::get()`。解析並回傳該項目。

    - 如果 `$abstract` 沒有被綁定/註冊,而且無法自動裝配(例如未知的類別
      名稱,或是沒有綁定的介面/抽象類別),會丟出
      `Psr\Container\NotFoundExceptionInterface`(實際上是
      `Wilkques\Container\Exceptions\NotFoundException`)。
    - 如果 `$abstract` *有*被綁定,但因為其他原因建構失敗(例如它的工廠
      本身拋例外,或某個依賴無法解析),會丟出
      `Psr\Container\ContainerExceptionInterface`(實際上是
      `Wilkques\Container\Exceptions\ContainerException`,通常是
      `BindingResolutionException` 或 `CircularDependencyException`)。

    > **與 v4 的行為差異:** 在 4.x,`get()` 對任何無法解析的東西都回傳
    > `null`,而不是拋例外。詳見
    > [UPGRADING.md](UPGRADING.md#2-getabstract-now-throws-instead-of-returning-null)。

    ```php
    container()->get('<your class name>');
    ```

1. `make($abstract, $arguments = array())`

    解析 `$abstract`,如果需要就建構它(含建構子自動裝配)。`$arguments`
    讓你可以依名稱或位置覆寫特定的建構子參數——`$arguments` 裡任何對不上
    實際建構子參數的鍵,都會被單純忽略(不會被當成多餘的位置參數塞進去)。

    ```php
    container('<your class name>');

    // 或

    container()->make('<your class name>');

    // 依位置覆寫第 2 個建構子參數
    container()->make('<your class name>', [1 => 'value']);

    // 依名稱覆寫某個建構子參數
    container()->make('<your class name>', ['paramName' => 'value']);
    ```

1. `call($callable, $arguments = array())`

    呼叫指定的 callable,並自動裝配 `$arguments` 裡沒提供的參數。
    支援以下寫法:

    - `[$object, 'method']`——在**你傳入的那個 `$object` 實例本身**上呼叫
      `method`(絕不會被重建或重新透過容器解析,即使該類別已經有
      singleton 存在)。
    - `['ClassName', 'method']`——如果 `method` 是 `static`,會直接呼叫,
      完全不會建構任何實例;否則會透過 `make()` 建構一個 `ClassName` 的
      實例(所以既有的 singleton 綁定會被沿用),再呼叫 `method`。
    - `'ClassName@method'` 與 `'ClassName::method'`——兩者都會被解析成
      上面的 `['ClassName', 'method']` 形式,行為完全相同(包括
      靜態/實例的判斷——`'::'` **不代表**該方法一定要是靜態方法)。
    - 一個 `\Closure`。
    - 一個可呼叫物件(任何有 `__invoke()` 且本身不是 `\Closure` 的物件)——
      會呼叫它的 `__invoke()` 方法。

    ```php
    container()->call(['<your class name>', '<your class method name>'], ['<your class method vars name>' => '<your class method vars value>']);

    // 或者,對一個已經建好的特定實例

    container()->call([new \Your\Class\Name, '<your class method name>'], ['<your class method vars name>' => '<your class method vars value>']);

    // 或者

    container()->call('\Your\Class\Name@<your class method name>');

    // 或者

    container()->call('\Your\Class\Name::<your class method name>');

    // 或者

    container()->call(function (\Your\Class\Name $abstract) {
        // 做些什麼
    });
    ```

1. `forgetInstance($abstract)`、`forgetInstances()`、`forgetScopedInstances()`、`flush()`

    - `forgetInstance($abstract)`——從實例快取中移除單一個已解析的實例。

        ```php
        container()->forgetInstance('<your class name>');
        ```

    - `forgetInstances()`——清除容器內所有已解析的實例。

    - `forgetScopedInstances()`——只清除透過 `scoped()` 註冊的實例。

    - `flush()`——把容器重置回全新狀態:所有綁定、別名、標籤、裝飾器、
      resolving 回呼、以及已解析的實例都會被清空,然後容器會重新註冊
      自己(所以 flush 之後立刻呼叫 `container()->make(Container::class)`
      仍然可以正常運作)。`flush()` 回傳 `$this` 以便串接呼叫。

      > **與 v4 的行為差異:** `flush()` 以前回傳 `void`。現在會回傳
      > `$this`,而且會重新註冊容器自身的綁定(v4 在 flush 之後會遺失
      > 這個綁定)。

## 例外(Exceptions)

所有容器專屬的例外都在 `Wilkques\Container\Exceptions` 命名空間下:

- `ContainerException extends \Exception implements Psr\Container\ContainerExceptionInterface`
  ——所有容器錯誤的基底例外。
- `BindingResolutionException extends ContainerException`
  ——當容器無法建構/解析某個綁定時丟出:目標類別不存在、無法被實例化
  (沒有綁定的介面/抽象類別)、或它的某個依賴無法解析。
- `CircularDependencyException extends BindingResolutionException`
  ——當解析某個 abstract 會需要再往下解析回自己(直接或透過一連串依賴)
  時丟出,取代原本會耗盡記憶體的無限遞迴。
- `NotFoundException extends ContainerException implements Psr\Container\NotFoundExceptionInterface`
  ——只有 `get()` 會丟出這個例外,而且只在 `$abstract` 沒有被綁定/註冊、
  也無法自動裝配時(對應 PSR-11 「找不到這個識別碼」的情境)。

## PSR-11

`Wilkques\Container\Container implements Psr\Container\ContainerInterface`。

因為這個套件必須維持在 PHP 5.3 上可用,它不能依賴需要 PHP >= 7.2 的
`psr/container` 版本。因此 `composer.json` 把版本釘選在
`psr/container: >=1.0 <1.1`:PSR-11 1.1+ 為 `ContainerInterface::has()`
加上了 `: bool` 回傳型別宣告,而一個要跟 PHP 5.3 相容的類別沒辦法宣告
這個型別。如果你的應用程式本來就要求真正的 `psr/container` 套件,本
套件會直接沿用它(`provide: psr/container-implementation: 1.0`);如果
沒有,`src/helpers.php` 會自己定義 `Psr\Container\ContainerInterface`、
`ContainerExceptionInterface`、`NotFoundExceptionInterface` 這幾個介面
(只有在它們還不存在時才定義),所以不論環境裡裝了什麼,這個容器永遠
都是 PSR-11 型別。

## PHP 相容性

目標支援 `php >= 5.3`(見 `composer.json`)。已驗證版本:

- **PHP 5.3.29**——`src/` 底下每個檔案都能通過 `php -l`(沒有使用短陣列、
  `finally`、`??`、`::class`,或任何型別宣告)。
- **PHP 7.0.33**——這是最容易出問題的版本,因為
  `ReflectionNamedType::getName()` 要到 PHP 7.1 才存在,7.0 的
  `ReflectionType` 只有 `__toString()`。容器用 `method_exists($type,
  'getName')` 做防護。已直接驗證:自動裝配類別型別參數、`self` 型別參數、
  `parent` 型別參數(正好會觸發這段程式碼的情境)都能正確解析,加上下面
  的一般行為驗證。
- **PHP 7.4.33** 與 **PHP 8.2.33**——完整行為煙霧測試:介面綁定實作、
  循環依賴偵測(丟出 `CircularDependencyException` 而不是耗盡記憶體)、
  PSR-11 的 `has()`/`get()`(包括未綁定時的 `NotFoundExceptionInterface`)、
  依型別名稱的 contextual binding、`tag()`/`tagged()`、`extend()`、以及
  透過 contextual `give()` 的變參注入——在兩個版本上(以及 7.0)全部
  一致通過。

完整的 PHPUnit 測試套件(78 個測試)是在 PHP 8.2 搭配 PHPUnit 9.6 上跑的,
因為沒有任何一版 PHPUnit 能同時支援 PHP 5.3 與現代 PHP;函式庫原始碼在
PHP 5.3 上的相容性,則是靠上面的 `php -l` 檢查來保證——這比語法相容性
檢查工具的保證更強(但它本身無法像上面針對 7.0 的檢查那樣,抓出「語法
合法但執行期行為不同」的 API 差異)。
