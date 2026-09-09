<?php

namespace Suzuran;

class WavConvertValue
{
    public function __construct(string $file)
    {
        // fopen is php function for reading binary files
        // mode r is open only reading mode
        $stream = fopen($file, 'r');

        // fread is reading binary safe
        // skip header for wav files
        fread($stream, 44);

        // test 後で消すこと
        echo fread($stream, 44);
        echo '区切る';
        echo fread($stream, 44);
        // reading wav data chucks
    
    }



}