<?php

namespace RavelryApi\Console;

class Manifest
{
    public static function getVersion()
    {
        if (('@@' . 'ravapicli-git-tag' . '@@') != '@@ravapicli-git-tag@@') {
            return ltrim('@@ravapicli-git-tag@@', 'v');
        }

        return 'dev';
    }
}
