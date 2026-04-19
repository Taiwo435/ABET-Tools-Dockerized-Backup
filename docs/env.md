# Environment Variables

Environment variables are how we supply our applications with credentials WITHOUT hard-coding them.
This allows us to track the project without exposing important information.
These files are typically named `.env` and will NOT be tracked by version control.

> [!IMPORTANT]
> Due to the important nature of this file, changing any part of `.env` will require people to copy your changes to .env. This works in the other way.
> As you pull changes from the repo, it is always recommended for you to copy `demo.env` into `.env`.
>
> We don't track `.env` in the repo because we want to prevent people from accidentally committing API keys to the repo. 
>
> Any (non-api-key) change you make to `.env` should be copied into `demo.env` so others have the same config as you.

## `docker/.env`

This .env file is used by docker-compose to supply important information, such as database credentials or API keys.
It ensures that all containers have the same credentials.

This is the **Central Configuration** of the entire project. Any config set here is meant to propagate to the entire application