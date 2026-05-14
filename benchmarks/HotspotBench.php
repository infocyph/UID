<?php

declare(strict_types=1);

namespace Infocyph\UID\Benchmarks;

use DateTimeImmutable;
use Infocyph\UID\CUID2;
use Infocyph\UID\DeterministicId;
use Infocyph\UID\KSUID;
use Infocyph\UID\NanoID;
use Infocyph\UID\OpaqueId;
use Infocyph\UID\Randflake;
use Infocyph\UID\Snowflake;
use Infocyph\UID\Sonyflake;
use Infocyph\UID\TBSL;
use Infocyph\UID\ULID;
use Infocyph\UID\UUID;
use Infocyph\UID\XID;
use InvalidArgumentException;
use PhpBench\Attributes as Bench;

final class HotspotBench
{
    private string $cuid2;

    private string $ksuid;

    private int $leaseEnd;

    private int $leaseStart;

    private string $nanoid;

    private string $randflake;

    private string $randflakeSecret;

    private string $snowflake;

    private string $sonyflake;

    private string $tbsl;

    private string $ulid;

    private string $uuid;

    private string $xid;

    public function __construct()
    {
        require_once __DIR__ . '/BenchBootstrap.php';
        BenchBootstrap::load();
        $this->prepareRandflakeContext();

        $this->uuid = UUID::v7();
        $this->ulid = ULID::generate();
        $this->snowflake = Snowflake::generate();
        $this->sonyflake = Sonyflake::generate();
        $this->tbsl = TBSL::generate();
        $this->ksuid = KSUID::generate();
        $this->xid = XID::generate();
        $this->nanoid = NanoID::generate();
        $this->cuid2 = CUID2::generate();
        $this->randflake = Randflake::generate(42, $this->leaseStart, $this->leaseEnd, $this->randflakeSecret);
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\ParamProviders('provideGenerationSubjects')]
    public function benchGeneration(array $params): void
    {
        $this->runSubjectBench('generation', $params);
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\ParamProviders('provideParseSubjects')]
    public function benchParse(array $params): void
    {
        $this->runSubjectBench('parse', $params);
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    public function benchUlidMonotonicBurstSameMs(): void
    {
        $fixed = DateTimeImmutable::createFromFormat('U.u', '1700000000.123000');
        ULID::generate($fixed);
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\ParamProviders('provideValidationSubjects')]
    public function benchValidation(array $params): void
    {
        $this->runSubjectBench('validation', $params);
    }

    /**
     * @return array<string, array{subject: string}>
     */
    public function provideGenerationSubjects(): array
    {
        return [
            'cuid2' => ['subject' => 'cuid2'],
            'deterministic' => ['subject' => 'deterministic'],
            'ksuid' => ['subject' => 'ksuid'],
            'nanoid' => ['subject' => 'nanoid'],
            'opaque' => ['subject' => 'opaque'],
            'randflake' => ['subject' => 'randflake'],
            'snowflake' => ['subject' => 'snowflake'],
            'sonyflake' => ['subject' => 'sonyflake'],
            'tbsl' => ['subject' => 'tbsl'],
            'ulid' => ['subject' => 'ulid'],
            'uuid_v7' => ['subject' => 'uuid_v7'],
            'xid' => ['subject' => 'xid'],
        ];
    }

    /**
     * @return array<string, array{subject: string}>
     */
    public function provideParseSubjects(): array
    {
        return [
            'ksuid' => ['subject' => 'ksuid'],
            'randflake_inspect' => ['subject' => 'randflake_inspect'],
            'randflake_parse' => ['subject' => 'randflake_parse'],
            'snowflake' => ['subject' => 'snowflake'],
            'sonyflake' => ['subject' => 'sonyflake'],
            'tbsl' => ['subject' => 'tbsl'],
            'ulid_get_time' => ['subject' => 'ulid_get_time'],
            'uuid' => ['subject' => 'uuid'],
            'xid' => ['subject' => 'xid'],
        ];
    }

    /**
     * @return array<string, array{subject: string}>
     */
    public function provideValidationSubjects(): array
    {
        return [
            'cuid2' => ['subject' => 'cuid2'],
            'nanoid' => ['subject' => 'nanoid'],
            'randflake' => ['subject' => 'randflake'],
            'snowflake' => ['subject' => 'snowflake'],
            'sonyflake' => ['subject' => 'sonyflake'],
            'tbsl' => ['subject' => 'tbsl'],
        ];
    }

    private function prepareRandflakeContext(): void
    {
        [$this->leaseStart, $this->leaseEnd, $this->randflakeSecret] = BenchBootstrap::randflakeContext();
    }

    /**
     * @param array{subject?: mixed} $params
     */
    private function runSubjectBench(string $operation, array $params): void
    {
        $subject = $this->subject($params);
        $runner = match ($operation) {
            'generation' => [
                'cuid2' => fn() => CUID2::generate(),
                'deterministic' => fn() => DeterministicId::fromPayload('payload', 24, 'bench'),
                'ksuid' => fn() => KSUID::generate(),
                'nanoid' => fn() => NanoID::generate(),
                'opaque' => fn() => OpaqueId::random(12),
                'randflake' => fn() => Randflake::generate(42, $this->leaseStart, $this->leaseEnd, $this->randflakeSecret),
                'snowflake' => fn() => Snowflake::generate(),
                'sonyflake' => fn() => Sonyflake::generate(),
                'tbsl' => fn() => TBSL::generate(),
                'ulid' => fn() => ULID::generate(),
                'uuid_v7' => fn() => UUID::v7(),
                'xid' => fn() => XID::generate(),
            ],
            'parse' => [
                'ksuid' => fn() => KSUID::parse($this->ksuid),
                'randflake_inspect' => fn() => Randflake::inspect($this->randflake, $this->randflakeSecret),
                'randflake_parse' => fn() => Randflake::parse($this->randflake, $this->randflakeSecret),
                'snowflake' => fn() => Snowflake::parse($this->snowflake),
                'sonyflake' => fn() => Sonyflake::parse($this->sonyflake),
                'tbsl' => fn() => TBSL::parse($this->tbsl),
                'ulid_get_time' => fn() => ULID::getTime($this->ulid),
                'uuid' => fn() => UUID::parse($this->uuid),
                'xid' => fn() => XID::parse($this->xid),
            ],
            'validation' => $this->validationRunners(),
            default => throw new InvalidArgumentException("Unknown benchmark operation: $operation"),
        };
        $handler = $runner[$subject] ?? throw new InvalidArgumentException("Unknown {$operation} subject: $subject");
        $handler();
    }

    /**
     * @param array{subject?: mixed} $params
     */
    private function subject(array $params): string
    {
        $subject = $params['subject'] ?? null;
        if (!is_string($subject) || $subject === '') {
            throw new InvalidArgumentException('Benchmark subject is required.');
        }

        return $subject;
    }

    private function validateCuid2(): void
    {
        CUID2::isValid($this->cuid2);
    }

    private function validateNanoid(): void
    {
        NanoID::isValid($this->nanoid);
    }

    private function validateRandflake(): void
    {
        Randflake::isValid($this->randflake);
    }

    private function validateSnowflake(): void
    {
        Snowflake::isValid($this->snowflake);
    }

    private function validateSonyflake(): void
    {
        Sonyflake::isValid($this->sonyflake);
    }

    private function validateTbsl(): void
    {
        TBSL::isValid($this->tbsl);
    }

    /**
     * @return array<string, callable():void>
     */
    private function validationRunners(): array
    {
        return [
            'cuid2' => fn() => $this->validateCuid2(),
            'nanoid' => fn() => $this->validateNanoid(),
            'randflake' => fn() => $this->validateRandflake(),
            'snowflake' => fn() => $this->validateSnowflake(),
            'sonyflake' => fn() => $this->validateSonyflake(),
            'tbsl' => fn() => $this->validateTbsl(),
        ];
    }
}
