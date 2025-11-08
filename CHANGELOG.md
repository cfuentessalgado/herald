# Changelog

All notable changes to `herald` will be documented in this file.

## [Unreleased]

### Added
- Initial release
- RabbitMQ connection driver with automatic exchange/queue setup
- Redis Streams connection driver with consumer groups
- Herald worker command (`herald:work`) with topic filtering
- Event mapping configuration (message types → Laravel events)
- Signal handling for graceful shutdown (SIGTERM/SIGINT)
- Idempotent message processing with ack/nack support
- Comprehensive test suite (32 tests, 73 assertions)
- Full documentation with PHP 5.6+ publisher examples

### Architecture
- `Message` value object for type-safe message handling
- `ConnectionInterface` contract for driver implementations
- `HeraldManager` for connection factory and event mapping lookup
- `HeraldServiceProvider` for Laravel integration
- `Herald` facade for convenient access

## [1.0.0] - TBD

Initial stable release.
