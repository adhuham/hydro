# Hydro
**PACKAGE IS NOT READY FOR PRODUCTION. WORK STILL IN PROGRESS**

Fast and lightweight Query Builder. Dependency-free and consists of only few classes.

## Usage
```
$pdo = new PDOConnection(...);
$hydro = new Hydro($pdo);
```

#### SELECT
```
// multiple
$hydro->table('post')->select('id', 'title', 'content')->get();

// single
$hydro->table('post')->select('id', 'title', 'content')->one();
```

#### WHERE
```
// simple
$hydro->table('post')->select('id', 'title', 'content')->where('id', 1)->get();

// nested where support
$hydro->table('post')
  ->select('id', 'title', 'content')
  ->where(function ($q) {
    $q->where('id', 1)->orWhere('title', 'Untitled');
  })
  ->get();
```

#### JOINS

```
// simple
$hydro->table('post')
  ->select('id', 'title', 'content')
  ->leftJoin('tag', 'post.tag_id', '=', 'tag.id')
  ->get();

// complex join
$hydro->table('post')
  ->select('id', 'title', 'content')
  ->leftJoin('tag', function ($q) {
    $q->on('post.tag_id', '=', 'tag.id')->orOn('post.tag2_id', '=', 'tag.id');
  })
  ->get();
```
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
