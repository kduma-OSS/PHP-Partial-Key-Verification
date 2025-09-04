<?php

namespace KDuma\PKV\Generator\Enums;

/**
 * Enum representing available checksum algorithms.
 */
enum ChecksumType: string
{
    case Adler16 = 'Adler16';
    case Crc16 = 'Crc16';
    case CrcCcitt = 'CrcCcitt';
}
