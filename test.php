<?php

require 'vendor/autoload.php';

$scorer = new Suzuran\CosineScorer();

echo $scorer->score([1, 2, 3], [1, 2, 3]), "\n";
echo $scorer->score([1, 2, 3], [9, 0.1, 5]);