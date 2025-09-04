<?php

namespace KDuma\PKV\Generator\Enums;

/**
 * Enum representing available hash algorithms.
 */
enum HashType: string
{
    case Crc32 = 'Crc32';
    case Fnv1A = 'Fnv1A';
    case GeneralizedCrc = 'GeneralizedCrc';
    case Jenkins06 = 'Jenkins06';
    case Jenkins96 = 'Jenkins96';
    case OneAtATime = 'OneAtATime';
    case SuperFast = 'SuperFast';
}
