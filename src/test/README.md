# Testing grounds

These are scripts that you may run to test the application. I added a selenium docker container that can be optionally run to execute these test cases.

## Getting Started

Starting the tests assume that you have done the setup from the base [README](../../README.md).

Spin up the selenium grid testing ground:

```bash
cd docker                               # make sure you are in docker/
docker compose down                     # 
docker compose --profile testing up       # spins up the server containers + testing containers (just selenium)
```

Now, you can run the testing scripts!

```bash
cd src/test                         # this directory
pip install -r requirements.txt     # you may use any version control system you desire
pytest .                            # initiate tests
```

## Organization

the only important files are `requirements.txt` and `test_*.py`. Pytest will treat all files starting with "test" as a testing suite, running all methods that start with "test". A template for using selenium for a testing suite is in `main.py` with a template method.

## More Information

Once the testing environment is up, you can access your sessions at [http://localhost:4444](http://localhost:4444). It lets you manage sessions in the case that you accidentally forget to delete a session (shouldn't happen if you follow the template, but you never know...).

If you need to, here's informatoin from the selenium container i use: [docker-selenium docs](https://github.com/SeleniumHQ/docker-selenium?tab=readme-ov-file)
