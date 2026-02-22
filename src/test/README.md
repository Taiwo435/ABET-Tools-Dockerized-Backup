# Testing grounds

These are scripts that you may run to test the application. I added a selenium docker container that can be optionally run to execute these test cases.

## Getting Started

Spin up the selenium grid testing ground:

```bash
cd docker                               # make sure you are in docker/
docker compose down
docker compose --profile debug up       # spins up the server containers + testing containers
```

Now, you can run the testing scripts!

```bash
cd src/test                         # this directory
pip install -r requirements.txt     # you may use any version control system you desire
pytest .                            # initiate tests
```

## Organization

the only important files are `requirements.txt` and `test_*.py`. Pytest will treat all files starting with "test" as a testing suite, running all methods that start with "test". A template for using selenium for a testing suite is in `main.py` with a template method. 