# Changelog

All notable changes to `herald` will be documented in this file.

## [Unreleased]

### Added
- **`Herald::on()` API** - Flexible handler registration with minimal surface area
  - Support for class strings (resolved from container)
  - Support for object instances (pre-configured handlers)
  - Support for closures (always synchronous)
  - Multiple handlers per event type
- **Smart queue detection** - Automatically queues handlers implementing `ShouldQueue`
- **`HandleHeraldMessage` job** - Queue wrapper for async handler execution
- Initial release
- RabbitMQ connection driver with automatic exchange/queue setup
- Herald worker command (`herald:work`) with topic filtering
- Event mapping configuration (message types → Laravel events)
- Signal handling for graceful shutdown (SIGTERM/SIGINT)
- Idempotent message processing with ack/nack support
- Comprehensive test suite (21 tests, 48 assertions)
- Full documentation with PHP 5.6+ publisher examples

### Architecture
- `Message` value object for type-safe message handling
- `ConnectionInterface` contract for driver implementations
- `HeraldManager` for connection factory, event mapping lookup, and handler registration
- `HeraldServiceProvider` for Laravel integration
- `Herald` facade for convenient access

### Changed
- Worker command now prioritizes `Herald::on()` registered handlers over config-based mappings
- Config-based event mappings now optional (backward compatible)
- Updated README with comprehensive handler examples and quick reference

## [1.0.0] - TBD

Initial stable release.
