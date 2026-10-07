<?php

declare(strict_types=1);

namespace Infocyph\UID\Runtime;

use Infocyph\Runwire\Coroutine\CoroutineScope;
use Infocyph\Runwire\Coroutine\TaskLocal;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\Runtime\Enum\RuntimeCapability;
use Infocyph\Runwire\RuntimeContext;
use LogicException;

final class RunwireBinding
{
    private static ?TaskLocal $scopeProbe = null;

    public function __construct(
        public readonly RuntimeContext $runtime,
        public readonly ?RequestContext $request = null,
        public readonly ?CoroutineScope $scope = null,
    ) {
        $this->assertActive();
    }

    public function assertActive(): void
    {
        $pid = getmypid();
        if (!is_int($pid) || $pid !== $this->runtime->pid) {
            throw new LogicException('Runwire binding belongs to a different process.');
        }

        if ($this->request !== null) {
            if ($this->request->runtime() !== $this->runtime) {
                throw new LogicException('Runwire request belongs to a different runtime context.');
            }
            if ($this->request->completed()) {
                throw new LogicException('Runwire request has already completed.');
            }

            $this->request->cancellation->throwIfCancelled();
        }

        if ($this->scope !== null) {
            if (!$this->runtime->supports(RuntimeCapability::RUNWIRE_COROUTINES)) {
                throw new LogicException('Runwire coroutine scope requires coroutine capability.');
            }

            self::$scopeProbe ??= new TaskLocal();
            $this->scope->hasLocal(self::$scopeProbe);
            $this->scope->cancellation()->throwIfCancelled();
        }
    }

    public function deadlineNanoseconds(): ?int
    {
        $this->assertActive();
        $requestDeadline = $this->request?->deadline()->monotonicNanoseconds;
        $scopeDeadline = $this->scope?->cancellation()->deadline()->monotonicNanoseconds;

        if ($requestDeadline === null) {
            return $scopeDeadline;
        }
        if ($scopeDeadline === null) {
            return $requestDeadline;
        }

        return min($requestDeadline, $scopeDeadline);
    }

    public function sleep(float $seconds): void
    {
        $this->assertActive();

        if ($this->scope !== null) {
            $this->scope->sleep($seconds);
        } else {
            usleep((int) ceil($seconds * 1_000_000));
        }

        $this->assertActive();
    }
}
