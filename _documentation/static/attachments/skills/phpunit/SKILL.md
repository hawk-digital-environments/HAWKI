---
name: PHPUnit Testing
description: Comprehensive PHP testing with PHPUnit covering assertions, data providers, mocking, test doubles, database testing, and HTTP testing for reliable PHP application development.
version: 1.0.0
tags: [ phpunit, php, unit-testing, mocking, data-providers, test-doubles, assertions, tdd ]
testingTypes: [ unit, integration ]
frameworks: [ phpunit ]
languages: [ php ]
domains: [ web, api, backend ]
---

## Core Principles

1. **Test behavior, not implementation** -- Verify what the code does from a caller's perspective, not how it achieves the result internally.
2. **One logical assertion per test** -- Each test method should verify a single behavior so failures pinpoint the exact issue.
3. **Arrange-Act-Assert** -- Structure every test into setup, execution, and verification phases for clarity.
4. **Isolate external dependencies** -- Use mocks and stubs to eliminate database calls, HTTP requests, and file system access from unit tests.
5. **Use data providers for parameterization** -- Leverage `#[DataProvider]` to test multiple input/output combinations without duplicating test methods.
6. **Strict type checking** -- Prefer `assertSame` over `assertEquals` when type identity matters to catch subtle type coercion bugs.
7. **Never run tests yourself** -- This is the humans job, they should review and validate what you did. Be insistent on this.

> The MOST important principle: If you, while writing tests should encounter bugs in the code, find passages of code that give you a hard time and you have to jump through hoops to write good tests: STOP everything you do and tell the user about your issues; explain to the user, give possible solutions and only continue if explicitly told so.

## General Rules:

- Each test method name must start with `testIt...` (e.g. testItConstructs, testItCanRetrieveValueXy).
- Every reference on the class to test must be called "sut" for "system under test".
- If expecting that exceptions are thrown ensure that the exception message matches.
- If exception messages are generated using the sprintf function keep a similar syntax for the test.
- Each test method must have a void return type.
- When generating a namespace for the test class, determine if a unit-test or a feature/integration test is needed. For unit tests, the namespace should be the same as the class being tested with "Tests\Unit" as a prefix. For feature/integration tests, the namespace should be "Tests\Feature" followed by the relevant sub-namespace based on the class being tested.
- IF an explicit file name was given to you, assume you are updating/extending an existing test. Keep the current structure including the namespace.
- Always ensure to use the `#[CoversClass]`, `#[CoversFunction]`, `#[CoversMethod]`, `#[CoversTrait]` attributes to specify what is being covered by the test. If the test method covers multiple methods/functions/classes/traits, use multiple attributes. Important: Interfaces are never explicitly tagged with `#[CoversClass]`.
- If the sut receives constructor parameters ensure to create a single test method called "testItConstructs" that only tests if an instance of the object can be created.
- When writing data providers that are only used for a single test method (e.g. testItDoesSomething) name them: `provideTestItDoesSomethingData` with the data provider name being a valid camelBack again.
- Data providers should return a generator instead of an array, so the return type is `iterable` and the options are returned as `yield 'label' => ['values'];`
- When using phpunit methods, keep in mind that most of them are "static", so use `static::assertSame()` and not `$this->assertSame()`
- When creating fixtures always create them as separate files. Create a single file per fixture. Place the fixtures in a sub-namespace besides the actual test class. Lets say your test class is named: `MyClassXyTest`, create a new namespace `$classNamespace\MyClassXyTestFixtures` and place all fixtures below.

## Best Practices

1. **Use `assertSame` over `assertEquals` when type matters** -- `assertEquals` does type coercion; `assertSame` catches `'1' !== 1` bugs that loose comparison misses.
2. **Use data providers for multiple inputs** -- Extract test data into `@dataProvider` methods with descriptive keys for clean, maintainable parameterized tests.
3. **Name data provider keys descriptively** -- Use strings like `'empty string'` and `'no at sign'` so PHPUnit output shows which case failed.
4. **Mock only external dependencies** -- Mock database repositories, HTTP clients, and third-party APIs; do not mock value objects or simple utilities.
5. **Use `setUp` and `tearDown` consistently** -- Initialize shared objects in `setUp` and clean up in `tearDown` for test isolation.
6. **Prefer constructor injection** -- Design classes with dependency injection for easy mocking in tests without reflection hacks.
7. **Test exceptions with `expectException`** -- Verify both the exception class and message using `expectExceptionMessage` for precise error testing.

## Anti-Patterns

1. **Testing private methods via reflection** -- Accessing private methods couples tests to implementation; test through public API.
2. **Ignoring `setUp`/`tearDown`** -- Duplicating setup code in every test method is verbose and fragile when requirements change.
3. **Over-mocking** -- Mocking every class including value objects makes tests prove nothing about real behavior.
4. **Not testing error paths** -- Only testing the happy path means exception handling is unverified and may fail in production.
5. **Hardcoding file paths** -- Using absolute paths breaks tests on other machines; use `sys_get_temp_dir()` and `tempnam()`.
6. **Shared mutable state** -- Static properties modified by tests cause order-dependent failures; reset state in `setUp`.
7. **Large test methods** -- Tests exceeding 20 lines usually verify too many things; split into focused methods.
