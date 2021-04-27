# Hydro
**PACKAGE IS NOT READY FOR PRODUCTION. WORK STILL IN PROGRESS**

***
**[Installation](https://github.com/adhuham/hydro/wiki/01.-Installation)** &mdash; 
**[Setup](https://github.com/adhuham/hydro/wiki/02.-Setup)** &mdash;
**[Usage](https://github.com/adhuham/hydro/wiki/03.-Usage)** &mdash;
**[Performance](https://github.com/adhuham/hydro/wiki/10.-Performance)** &mdash;
**[Credits](https://github.com/adhuham/hydro/wiki/11.-Credits)**
*** 

Hydro is a fast and lightweight Query Builder. It is incredibly easy to use with its natural and intuitive syntax.

Hydro is designed with speed and memory efficiency in mind. As a result, it has extremely low footprint (consist of only just few classes) and is completely dependency-free, which takes up less of your memory.

The cost of speed and performance gain is kept minimal. With Hydro, you don't have to trade the ease-of-use for better performance.

## Installation & Setup
Use Composer.
```
composer require adhuham/hydro
```

```php
use Hydro\Hydro;

$pdo = PDOConnection(...);
$hydro = new Hydro($pdo);
```

## Basic Usage
```php
// multiple
$hydro->table('post')->select('id', 'title', 'content')->get();

// single
$hydro->table('post')->select('id', 'title', 'content')->one();

// where
$hydro->table('post')->select('id', 'title', 'content')->where('id', 1)->get();

// joins
$hydro->table('post')
  ->select('id', 'title', 'content')
  ->leftJoin('tag', 'post.tag_id', '=', 'tag.id')
  ->get();
```
### See [Documentation](https://github.com/adhuham/hydro/wiki)

## TODO
- [ ] Documentation
- [ ] Testing

## Performance
Memory usage comparison between Eloquent, Laravel's Query Builder, Hydro and raw PDO. (Lower is better)
```
Eloquent
==============================================================================  7,542,904

Laravel Query Builder
=============== 1,455,720

Hydro 
== 219,008

Hydro (using raw statements)
== 219,112

PDO
= 138,688
```
### See [Performance](https://github.com/adhuham/hydro/wiki/09.-Performance)
