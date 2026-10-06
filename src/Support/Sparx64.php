<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

use InvalidArgumentException;

final class Sparx64
{
    private const int BRANCHES = 2;

    private const int ROUNDS_PER_STEP = 3;

    private const int STEPS = 8;

    /** @var array<int, array<int, int>> */
    private array $subkeys;

public function __construct(#[\SensitiveParameter] string $key)
    {
        if (strlen($key) !== 16) {
            throw new InvalidArgumentException('SPARX64 key must be exactly 16 bytes');
        }

        $master = [];
        for ($index = 0; $index < 16; $index += 2) {
            $master[] = (ord($key[$index]) << 8) | ord($key[$index + 1]);
        }

        $this->subkeys = [];
        for ($counter = 0; $counter < (self::BRANCHES * self::STEPS) + 1; ++$counter) {
            $this->subkeys[$counter] = array_slice($master, 0, 2 * self::ROUNDS_PER_STEP);
            self::permuteKey($master, $counter + 1);
        }
    }

public function decrypt(string $block): string
    {
        $state = self::unpackBlock($block);
        $last = self::BRANCHES * self::STEPS;
        for ($branch = 0; $branch < self::BRANCHES; ++$branch) {
            $state[2 * $branch] ^= $this->subkeys[$last][2 * $branch];
            $state[(2 * $branch) + 1] ^= $this->subkeys[$last][(2 * $branch) + 1];
        }

        for ($step = self::STEPS - 1; $step >= 0; --$step) {
            self::linearInverse($state);
            for ($branch = 0; $branch < self::BRANCHES; ++$branch) {
                for ($round = self::ROUNDS_PER_STEP - 1; $round >= 0; --$round) {
                    self::roundInverse($state[2 * $branch], $state[(2 * $branch) + 1]);
                    $state[2 * $branch] ^= $this->subkeys[(self::BRANCHES * $step) + $branch][2 * $round];
                    $state[(2 * $branch) + 1] ^= $this->subkeys[(self::BRANCHES * $step) + $branch][(2 * $round) + 1];
                }
            }
        }

        return self::packBlock($state);
    }

public function encrypt(string $block): string
    {
        $state = self::unpackBlock($block);
        for ($step = 0; $step < self::STEPS; ++$step) {
            for ($branch = 0; $branch < self::BRANCHES; ++$branch) {
                for ($round = 0; $round < self::ROUNDS_PER_STEP; ++$round) {
                    $state[2 * $branch] ^= $this->subkeys[(self::BRANCHES * $step) + $branch][2 * $round];
                    $state[(2 * $branch) + 1] ^= $this->subkeys[(self::BRANCHES * $step) + $branch][(2 * $round) + 1];
                    self::round($state[2 * $branch], $state[(2 * $branch) + 1]);
                }
            }

            self::linear($state);
        }

        $last = self::BRANCHES * self::STEPS;
        for ($branch = 0; $branch < self::BRANCHES; ++$branch) {
            $state[2 * $branch] ^= $this->subkeys[$last][2 * $branch];
            $state[(2 * $branch) + 1] ^= $this->subkeys[$last][(2 * $branch) + 1];
        }

        return self::packBlock($state);
    }

/** @param array<int, int> $state */
    private static function linear(array &$state): void
    {
        $temporary = self::rotateLeft16($state[0] ^ $state[1], 8);
        $state[2] ^= $state[0] ^ $temporary;
        $state[3] ^= $state[1] ^ $temporary;
        [$state[0], $state[2]] = [$state[2] & 0xffff, $state[0] & 0xffff];
        [$state[1], $state[3]] = [$state[3] & 0xffff, $state[1] & 0xffff];
    }

/** @param array<int, int> $state */
    private static function linearInverse(array &$state): void
    {
        [$state[0], $state[2]] = [$state[2], $state[0]];
        [$state[1], $state[3]] = [$state[3], $state[1]];
        $temporary = self::rotateLeft16($state[0] ^ $state[1], 8);
        $state[2] = ($state[2] ^ $state[0] ^ $temporary) & 0xffff;
        $state[3] = ($state[3] ^ $state[1] ^ $temporary) & 0xffff;
    }

/** @param array<int, int> $state */
    private static function packBlock(array $state): string
    {
        $output = '';
        foreach ($state as $value) {
            $output .= chr(($value >> 8) & 0xff) . chr($value & 0xff);
        }

        return $output;
    }

/** @param array<int, int> $key */
    private static function permuteKey(array &$key, int $counter): void
    {
        self::round($key[0], $key[1]);
        $key[2] = ($key[2] + $key[0]) & 0xffff;
        $key[3] = ($key[3] + $key[1]) & 0xffff;
        $key[7] = ($key[7] + $counter) & 0xffff;
        $six = $key[6];
        $seven = $key[7];
        for ($index = 7; $index >= 2; --$index) {
            $key[$index] = $key[$index - 2];
        }
        $key[0] = $six;
        $key[1] = $seven;
    }

private static function rotateLeft16(int $value, int $bits): int
    {
        $value &= 0xffff;

        return (($value << $bits) | ($value >> (16 - $bits))) & 0xffff;
    }

private static function round(int &$left, int &$right): void
    {
        $left = (self::rotateLeft16($left, 9) + $right) & 0xffff;
        $right = (self::rotateLeft16($right, 2) ^ $left) & 0xffff;
    }

private static function roundInverse(int &$left, int &$right): void
    {
        $right = self::rotateLeft16($right ^ $left, 14);
        $left = self::rotateLeft16(($left - $right) & 0xffff, 7);
    }

/** @return array<int, int> */
    private static function unpackBlock(string $block): array
    {
        if (strlen($block) !== 8) {
            throw new InvalidArgumentException('SPARX64 block must be exactly 8 bytes');
        }

        return [
            (ord($block[0]) << 8) | ord($block[1]),
            (ord($block[2]) << 8) | ord($block[3]),
            (ord($block[4]) << 8) | ord($block[5]),
            (ord($block[6]) << 8) | ord($block[7]),
        ];
    }

}
