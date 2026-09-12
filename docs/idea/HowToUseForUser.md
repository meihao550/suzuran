# 最終的にライブラリの使用者がどのように使えるのかのアイデア

- PHP上でまずは話者照合ができるようになる

## 話者照合のアイデア

```php
<?php
require 'vendor/autoload.php';

$suzuran = new Suzuran\Suzuran();
$value = $suzuran->isMatch("file1.wav", "file2.wav", 0.7);
```

- `isMatch(string $wav1, string $wav2, float $threshold): bool`
- 閾値は 0 から 1 の間の浮動小数点で、どのくらい声が一致していれば本人として出すかを決められる
