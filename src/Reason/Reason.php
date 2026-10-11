<?php

declare(strict_types=1);

namespace RickRole\Reason;

use RickRole\Rick;
use RickRole\Voter\VoterInterface;
use RickRole\Voter\VoteResult;

/**
 * Explains a permission decision made by Rick.
 *
 * A reason holds the final decision, which always allows or denies, and every vote that was cast to reach it, in
 * vote order.
 */
final readonly class Reason
{
    /**
     * @param string $decision Either VoteResult::ALLOW or VoteResult::DENY
     * @param string $message The message given by the strategy for the final decision
     * @param string|\BackedEnum|null $code The final code: the deciding vote's code, or the strategy's own code
     * @param list<Vote> $votes Every vote cast in this decision, in vote order
     * @param Vote|null $decidingVote The vote that decided, or null when the strategy decided by itself
     * @param bool $complete Whether every configured voter voted
     */
    public function __construct(
        public string|int $userId,
        public string $permission,
        public mixed $subject,
        public string $decision,
        public string $message,
        public string|\BackedEnum|null $code = null,
        public array $votes = [],
        public ?Vote $decidingVote = null,
        private bool $complete = true
    ) {
        if ($decision !== VoteResult::ALLOW && $decision !== VoteResult::DENY) {
            throw new \InvalidArgumentException(sprintf('A reason must allow or deny, not "%s"', $decision));
        }
    }

    /**
     * Check if the decision allows.
     */
    public function isAllow(): bool
    {
        return $this->decision === VoteResult::ALLOW;
    }

    /**
     * Check if the decision denies.
     */
    public function isDeny(): bool
    {
        return $this->decision === VoteResult::DENY;
    }

    /**
     * Check if the final code is the given code.
     *
     * The final code comes from the deciding vote, so when several votes agree it depends on voter order. Use has()
     * to ask whether any vote gave a code.
     */
    public function is(string|\BackedEnum $code): bool
    {
        return $this->code === $code;
    }

    /**
     * Check if the final code is not the given code.
     */
    public function isNot(string|\BackedEnum $code): bool
    {
        return $this->is($code) === false;
    }

    /**
     * Check if the final code, or the code of any vote in this decision, is the given code.
     *
     * Unlike is(), this does not depend on voter order. Voters after an early stop never vote, so see wasComplete().
     */
    public function has(string|\BackedEnum $code): bool
    {
        if ($this->is($code)) {
            return true;
        }

        foreach ($this->votes as $vote) {
            if ($vote->code === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if neither the final code nor the code of any vote in this decision is the given code.
     *
     * If voting stopped early, a voter that did not vote might have given the code, so this is only certain when
     * wasComplete() is true.
     */
    public function hasNot(string|\BackedEnum $code): bool
    {
        return $this->has($code) === false;
    }

    /**
     * Get the codes given by the votes in this decision, in vote order, skipping votes without a code.
     *
     * @return list<string|\BackedEnum>
     */
    public function codes(): array
    {
        $codes = [];
        foreach ($this->votes as $vote) {
            if ($vote->code !== null) {
                $codes[] = $vote->code;
            }
        }

        return $codes;
    }

    /**
     * Check if every configured voter voted, so that has(), codes() and votes saw them all.
     */
    public function wasComplete(): bool
    {
        return $this->complete;
    }

    /**
     * Check if voting stopped early, so that some voters did not vote.
     */
    public function wasNotComplete(): bool
    {
        return $this->wasComplete() === false;
    }

    /**
     * Get the message given by the deciding vote, or the final message when the strategy decided by itself.
     */
    public function given(): string
    {
        return $this->decidingVote->message ?? $this->message;
    }

    /**
     * Check if the given voter cast the deciding vote.
     *
     * @param class-string<VoterInterface> $voter
     */
    public function wasDecidedBy(string $voter): bool
    {
        return $this->decidingVote?->voter === $voter;
    }

    /**
     * Check if the given voter did not cast the deciding vote.
     *
     * @param class-string<VoterInterface> $voter
     */
    public function wasNotDecidedBy(string $voter): bool
    {
        return $this->wasDecidedBy($voter) === false;
    }

    /**
     * Get a line for each vote in vote order, then a line for the final decision.
     */
    public function trace(): string
    {
        $lines = [];
        foreach ($this->votes as $vote) {
            $lines[] = self::traceLine($vote->decision, $vote->voter, $vote->message, $vote->code);
        }
        $lines[] = self::traceLine($this->decision, Rick::class, $this->message, $this->code);

        return implode("\n", $lines);
    }

    /**
     * Get the decision and every vote as an array, for example to log it.
     *
     * @return array{
     *     user_id: string|int,
     *     permission: string,
     *     decision: string,
     *     message: string,
     *     code: string|\BackedEnum|null,
     *     decided_by: class-string<VoterInterface>|null,
     *     complete: bool,
     *     votes: list<array{voter: class-string<VoterInterface>, decision: string, message: string, code: string|\BackedEnum|null}>
     * }
     */
    public function toArray(): array
    {
        $votes = [];
        foreach ($this->votes as $vote) {
            $votes[] = [
                'voter' => $vote->voter,
                'decision' => $vote->decision,
                'message' => $vote->message,
                'code' => $vote->code,
            ];
        }

        return [
            'user_id' => $this->userId,
            'permission' => $this->permission,
            'decision' => $this->decision,
            'message' => $this->message,
            'code' => $this->code,
            'decided_by' => $this->decidingVote?->voter,
            'complete' => $this->complete,
            'votes' => $votes,
        ];
    }

    /**
     * Convert the reason to a string.
     */
    public function __toString(): string
    {
        return sprintf(
            'Permission "%s" for user "%s" was %s by %s: %s',
            $this->permission,
            $this->userId,
            $this->isAllow() ? 'allowed' : 'denied',
            $this->decidingVote->voter ?? Rick::class,
            $this->given()
        );
    }

    private static function traceLine(string $decision, string $voter, string $message, string|\BackedEnum|null $code): string
    {
        $line = sprintf('[%s] %s: %s', $decision, $voter, $message);

        if ($code instanceof \BackedEnum) {
            return $line . sprintf(' (%s::%s)', $code::class, $code->name);
        }

        if ($code !== null) {
            return $line . sprintf(' (%s)', $code);
        }

        return $line;
    }
}
