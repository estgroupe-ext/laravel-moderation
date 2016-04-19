# Laravel Moderation

A simple Moderation System for Laravel 5.* that allows you to Approve or Reject resources like posts, comments, users, etc.

Keep your application pure by preventing offensive, irrelevant, or insulting content.

## 修改说明

移除 Status 类，修改为读取 config 配置获取 status，增强扩展性。

## 安装

1). 修改 `composer.json`, 在 `repositories` 数组内追加如下数据

```
    "repositories": [
        ...
        {
            "type": "vcs",
            "url":  "https://github.com/estgroupe-ext/laravel-moderation.git"
        }
    ]
```

2). 添加 `hootlex/laravel-moderation`

```shell
composer require estgroupe-ext/laravel-moderation
```

3). 修改 `config/app.php`, 在 `providers` 数组内追加如下数据

```php
'providers' => [
    ...
    Hootlex\Moderation\ModerationServiceProvider::class,
    ...
];
```

4). 生成 migrations, 使其包含 `status` 字段

Example Migration:
```php
class AddModeratioColumnsToPostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->enum('status', [
                config('moderation.status.pending'),
                config('moderation.status.approved'),
                config('moderation.status.rejected'),
                config('moderation.status.postponed')
            ])->default(config('moderation.status.pending'));
            $table->dateTime('moderated_at')->nullable();
            //If you want to track who moderated the Model add 'moderated_by' too.
            //$table->integer('moderated_by')->nullable()->unsigned();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('posts', function(Blueprint $table)
        {
            $table->dropColumn('status');
            $table->dropColumn('moderated_at');
            //$table->integer('moderated_by')->nullable()->unsigned();
        });
    }
}
```

5). 更新 Eloquent Models

```php
use Hootlex\Moderation\Moderatable;
class Post extends Model
{
    use Moderatable;
    ...
}
```

## 使用

接下来我们将以 Post 模型来作为使用范例，来说明如何使用这个 Package.

### 文章审核

```php
// 发布此文章
Post::approve($post->id);

// 拒绝此文章
Post::reject($post->id);
```

或者你也可以通过以下方式对文章进行审核。

```php
Post::where('title', 'Horse')->approve();

Post::where('title', 'Horse')->reject();
```

## 文章查询

### 已发布文章查询

```php
// 返回所有允许发布的文章
Post::all();

// 返回 title 为 Horse 并且允许发布的文章
Post::where('title', 'Horse')->get();
```

### 待审核、被拒绝的文章查询

```php
// 返回待审核的文章
Post::pending()->get();

// 返回被拒绝的文章
Post::rejected()->get();

// 返回已发布和待审核的文章
Post::withPending()->get();

// 返回已发布和被拒绝的文章
Post::withRejected()->get();
```

### 查询所有文章
```php
// 返回所有文章
Post::withAnyStatus()->get();

// 返回 title 为 Horse 的所有文章
Post::withAnyStatus()->where('title', 'Horse')->get();
```

### 查询文章状态

```php
// 判断文章是否是待审核状态
$post->isPending();

// 判断文章是否是已发布状态
$post->isApproved();

// 判断文章是否是被拒绝状态
$post->isRejected();
```

## 配置

### 全局配置

你可以通过修改项目 `config/moderation.php` 的方式来进行个性化定制

1. `status_column` 数据库中的状态列名称。
2. `moderated_at_column` 数据库中文章审核时间列名称。
2. `moderated_by_column` 数据库中文章审核者列名称。
3. `strict` 是否启用严格模式，默认为严格模式，对所有查询只返回允许发布的文章，关闭严格模式的情况下则同时返回允许发布的文章和待审核的文章。

### Model 配置

在 Model 中，你可以通过定义一些变量来改写全局配置

改写 `status` 列名称

```php
const MODERATION_STATUS = 'moderation_status';
```

改写 `moderated_at` 列名称

```php
const MODERATED_AT = 'mod_at';
```

改写 `moderated_by` 列名称

```php
const MODERATED_BY = 'mod_by';
```

开启或关闭严格模式

```php
public static $strictModeration = true;
```