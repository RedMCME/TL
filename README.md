# TL
Translation (i18n) Manager as a virion

## Translation
### use hook-like
```php
$t = $tl->useTranslation($player->getLocale());
$player->sendMessage($t("message-key"));
```

```php
$t = $tl->useTranslation("lang", "namespace"));
$player->sendMessage($t("message-with-params", [
  "count" => 1
]));
```

### use directly
```php
public function translate(string $key, array $params = [], ?string $lang = null, ?string $namespace = null): string;
```

## Get Started
```php
use xerenahmed\TL\TL;

$tl = TL::init();
$tl->load("locales/");
```

If you use in plugin use the `$this->getFile() . "locales/"`.

> "locales" is optional but in this readme we'll use this name as directory.

## Directory Tree
locales directory tree should be like this:
```sh
.
└── namespace name
    ├── de.json
    ├── en.json
    └── tr.json
```

in a plugin, this is the recommended way to use it:
```sh
.
├── locales
│   └── common
│       ├── de.json
│       ├── en.json
│       └── tr.json
├── plugin.yml
└── src
```
