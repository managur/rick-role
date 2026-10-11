# Changelog

All notable changes to Rick-Role are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html). Before 1.0.0, a minor version may contain breaking changes.

## [Unreleased]

## [0.3.0] - 2026-10-11

This release replaces the chain of `Reason` objects with a single `Reason` that holds the final decision and every
vote cast to reach it.

### Added

- `RickRole\Reason\Vote`, a single voter's vote, with `voter`, `decision`, `message` and `code`, plus `isAllow()`,
  `isDeny()` and `isAbstain()`.
- `RickRole\Strategy\Decision`, the outcome of a strategy. Build it with `Decision::byVote()` when one of the votes
  decided, or `Decision::byStrategy()` when no single vote decided, for example because every voter abstained.
- `Reason::$votes`, every vote cast in the check, in vote order, and `Reason::$decidingVote`, the vote that decided or
  `null`.
- `Reason::has()` and `Reason::hasNot()`, to check whether the final decision or any vote gave a code, regardless of
  voter order.
- `Reason::codes()`, the codes given by the votes, in vote order.
- `Reason::wasComplete()` and `Reason::wasNotComplete()`, to check whether every configured voter voted or the strategy
  stopped voting early.
- `Reason::toArray()`, the decision and every vote as an array, for logging.
- `CLIBootstrap::createApplication()` accepts the directory to load a `.env` file from.

### Changed

- **Breaking:** the `because` out-parameter is now a single `Reason` rather than a chain of reasons, one per voter.
  Read the individual votes from `$reason->votes`.
- **Breaking:** `Reason` is now a readonly value object, and its constructor takes `decision`, `message`, `code`,
  `votes`, `decidingVote` and `complete`. A reason must allow or deny, so its constructor throws an
  `InvalidArgumentException` for any other decision.
- **Breaking:** `StrategyInterface::decide()` returns a `Decision` instead of a `VoteResult`. Custom strategies must
  return `Decision::byVote()` with one of the vote results they were given, or `Decision::byStrategy()`. Rick throws a
  `LogicException` if a strategy decides by a vote that was not cast in the check.
- **Breaking:** `AllowWinsStrategy` and `DenyWinsStrategy` deny, rather than abstain, when every voter abstains or
  there are no votes. Rick has always denied in those cases, so permission checks give the same answer.
- `Reason::given()` always returns a string.
- `Reason::trace()` returns one line per vote, naming the voter, followed by a line for the final decision.
- Casting a `Reason` to a string names the deciding voter, or `RickRole\Rick` when the strategy decided by itself, and
  uses the deciding vote's message.
- `voter_decisions` in the final log entry includes each vote's `code`.

### Removed

- **Breaking:** `Reason::$previous`, `Reason::$voter`, `Reason::$chain`, `Reason::$lastDecision` and
  `Reason::$voters`.
- **Breaking:** `Reason::addDecision()`, `Reason::addVoter()`, `Reason::getFullTrace()` and `Reason::isAbstain()`.

## [0.2.1] - 2026-10-10

### Changed

- Require `doctrine/orm` `^3.4`, and enable Doctrine's native lazy objects.

### Fixed

- Resetting the database no longer uses DBAL's deprecated `quoteIdentifier()`.
- Documented that only the reason returned through `because` knows the deciding vote, so `wasDecidedBy()` returns
  `false` on earlier reasons in the `previous` chain.

## [0.2.0] - 2026-10-09

### Added

- Voters can give their vote a code, as a string or backed enum, through the new `code` argument of
  `VoteResult::allow()`, `VoteResult::deny()` and `VoteResult::abstain()`, and read it with `VoteResult::code()`.
- `Reason::$code`, the code from the deciding vote, and `Reason::is()` and `Reason::isNot()` to check it.
- `Reason::wasDecidedBy()` and `Reason::wasNotDecidedBy()`, to check which voter cast the deciding vote.
- `Reason::given()`, the message given by the deciding vote.
- Log entries include the `code`.

### Changed

- `AllowWinsStrategy` and `DenyWinsStrategy` pass the deciding vote's code through to their result.

## [0.1.0] - 2025-08-10

### Added

- Initial release: role-based access control with voters, strategies, Doctrine storage and a CLI.

[Unreleased]: https://github.com/managur/rick-role/compare/0.3.0...HEAD
[0.3.0]: https://github.com/managur/rick-role/compare/0.2.1...0.3.0
[0.2.1]: https://github.com/managur/rick-role/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/managur/rick-role/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/managur/rick-role/releases/tag/0.1.0
