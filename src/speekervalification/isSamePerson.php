<?php

namespace suzuran;
require 'WavConvertValue.php';

class SamePerson {
    // WAVファイルを比較して、閾値をもとに本人かどうかをだす話者照合のユーザー側が触れるメソッド
    public function isSamePerson(string $file1, string $file2, $threshold): bool{
        wavConvertValue = new WavConvertValue();
    }
}