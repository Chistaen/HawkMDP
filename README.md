## Hawk MDP

Markdown Parser in PHP


### Installation

Include `Hawk.php` or install the Composer package.

### Example

``` php
$hawk = new Hawk();

echo $hawk->text('Hello _Hawk_!'); # prints: <p>Hello <em>Hawk</em>!</p>
```

### Questions

**How is Hawk different from Parsedown?**
Parsedown is a great markdown parser, but while it works perfectly on blogs and static websites, wiki owners might find it helpful to have more wiki-related tags. Hawk also aims to provide a more customizable parser that allows you to add extra tags yourself very easily.