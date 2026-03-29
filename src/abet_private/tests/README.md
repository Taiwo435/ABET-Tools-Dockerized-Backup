# PHP Pest Tests

These tests are a bit different from the other tests in the project.
I had to add them into this directory because they are very related to everything PHP and the backend.
They are NOT the same as the `src/test` directory tests, those are end to end.
The goal of these tests is to test the specific functions of our PHP libraries. (Feature and Unit)

## Running the test

1) Install composer with [these instructions](../../../README.md) if you haven't already.
2) Then, ensure that a mysql container is running on port 3306. `docker compose up --build`

Then, run these commands:

```bash
composer install
composer test
```

That's it! you should see the tests run.
