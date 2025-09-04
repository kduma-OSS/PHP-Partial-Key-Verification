<?php

declare(strict_types=1);

namespace KDuma\PKV;

use LogicException;

use function array_fill;
use function ord;
use function pack;
use function strlen;
use function strpos;

final class Base32
{
    private const MAP = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Convert a binary string to Base32 (no padding), same as C# ToBase32(byte[]).
     */
    public static function toBase32(string $data): string
    {
        $map = self::MAP;
        $len = strlen($data) - 1;
        if ($len < 0) {
            return '';
        }

        $ret = '';
        $offset = 0;

        for ($i = 0; $i <= $len; $i++) {
            $cur = ord($data[$i]);
            $ip1 = 0;
            if ($i !== $len) {
                $ip1 = ord($data[$i + 1]);
            }

            switch ($offset) {
                case 0:
                    $ret .= $map[$cur >> 3];
                    $ret .= $map[((($cur << 2) & 0x1F) | ($ip1 >> 6)) & 0x1F];
                    $offset = 2;
                    break;

                case 1:
                    $ret .= $map[(($cur >> 2) & 0x1F)];
                    $ret .= $map[((($cur << 3) & 0x1F) | ($ip1 >> 5)) & 0x1F];
                    $offset = 3;
                    break;

                case 2:
                    $ret .= $map[(($cur >> 1) & 0x1F)];
                    $ret .= $map[((($cur << 4) & 0x1F) | ($ip1 >> 4)) & 0x1F];
                    $offset = 4;
                    break;

                case 3:
                    $ret .= $map[$cur & 0x1F];
                    $offset = 0;
                    break;

                case 4:
                    $ret .= $map[((($cur << 1) & 0x1F) | ($ip1 >> 7)) & 0x1F];
                    $offset = 1;
                    break;

                default:
                    // Debug.Assert(false) in C#
                    throw new LogicException('Unexpected offset state');
            }
        }

        return $ret;
    }

    /**
     * Convert a Base32 string (no padding) to a binary string, same as C# FromBase32(string) -> byte[].
     */
    public static function fromBase32(string $data): string
    {
        $map = self::MAP;
        $dataLen = strlen($data);
        if ($dataLen === 0) {
            return '';
        }

        $outLen = intdiv($dataLen * 5, 8); // floor
        $ret = array_fill(0, $outLen, 0);

        $b = 0;
        $offset = 0;

        for ($i = 0, $j = 0; $i < $outLen; $i++) {
            switch ($offset) {
                case 0:
                    $b = (int) strpos($map, $data[$j++]);
                    $ret[$i] = ($b << 3) & 0xFF;

                    $b = (int) strpos($map, $data[$j++]);
                    $ret[$i] |= ($b >> 2) & 0xFF;

                    $offset = 3;
                    break;

                case 3:
                    $ret[$i] = (($b << 6) & 0xFF);

                    $b = (int) strpos($map, $data[$j++]);
                    $ret[$i] |= (($b << 1) & 0xFF);

                    $b = (int) strpos($map, $data[$j++]);
                    $ret[$i] |= (($b >> 4) & 0xFF);

                    $offset = 1;
                    break;

                case 1:
                    $ret[$i] = (($b << 4) & 0xFF);

                    $b = (int) strpos($map, $data[$j++]);
                    $ret[$i] |= (($b >> 1) & 0xFF);

                    $offset = 4;
                    break;

                case 4:
                    $ret[$i] = (($b << 7) & 0xFF);

                    $b = (int) strpos($map, $data[$j++]);
                    $ret[$i] |= (($b << 2) & 0xFF);

                    $b = (int) strpos($map, $data[$j++]);
                    $ret[$i] |= (($b >> 3) & 0xFF);

                    $offset = 2;
                    break;

                case 2:
                    $ret[$i] = (($b << 5) & 0xFF);

                    $b = (int) strpos($map, $data[$j++]);
                    $ret[$i] |= $b & 0xFF;

                    $offset = 0;
                    break;

                default:
                    throw new LogicException('Unexpected offset state');
            }
        }

        // Return as binary string (equivalent to byte[])
        return pack('C*', ...$ret);
    }
}
